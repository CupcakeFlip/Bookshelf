<?php

declare(strict_types=1);

/**
 * Popular-books API.
 *
 * GET requests Hardcover for trending IDs, fetches their metadata, restores
 * locally persisted reading statuses, and returns the result in trend order.
 */
const HARDCOVER_API_URL = 'https://api.hardcover.app/v1/graphql';
const BOOK_LIMIT = 20;

/**
 * Sends a JSON response and stops execution.
 *
 * @param array $data Response data.
 * @param int $status HTTP status code.
 * @return never
 */
function respond(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        $data,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    exit;
}

/**
 * Sends a GraphQL request to Hardcover.
 *
 * @param string $query GraphQL query.
 * @param array $variables Query variables.
 * @param string $token Hardcover API token.
 * @return array GraphQL response data.
 */
function hardcoverRequest(
    string $query,
    array  $variables,
    string $token
): array
{
    $curl = curl_init(HARDCOVER_API_URL);

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'query' => $query,
            'variables' => $variables,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ],
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
    ]);

    $body = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if ($body === false) {
        respond(['error' => 'Could not reach Hardcover.'], 502);
    }

    if ($status === 429) {
        respond(['error' => 'Hardcover rate limit reached.'], 503);
    }

    $response = json_decode($body, true);

    if (
        $status < 200 ||
        $status >= 300 ||
        !is_array($response) ||
        !empty($response['errors'])
    ) {
        respond(['error' => 'Hardcover API request failed.'], 502);
    }

    if (!isset($response['data']) || !is_array($response['data'])) {
        respond(['error' => 'Invalid Hardcover response.'], 502);
    }

    return $response['data'];
}

/**
 * Converts Hardcover book data into the frontend format.
 *
 * @param array $book Raw Hardcover book.
 * @return array Normalized book.
 */
function normalizeBook(array $book): array
{
    $authors = [];

    foreach ($book['contributions'] ?? [] as $contribution) {
        $author = $contribution['author'] ?? null;

        if (is_array($author) && !empty($author['name'])) {
            $authors[] = [
                'id' => $author['id'] ?? null,
                'name' => $author['name'],
            ];
        }
    }

    $series = null;
    $relation = $book['book_series'][0] ?? null;

    if (is_array($relation) && is_array($relation['series'] ?? null)) {
        $series = [
            'id' => $relation['series']['id'] ?? null,
            'name' => $relation['series']['name'] ?? null,
            'position' => $relation['position'] ?? null,
        ];
    }

    $genres = [];
    $cachedTags = $book['cached_tags'] ?? [];
    $rawGenres = is_array($cachedTags)
        ? ($cachedTags['Genre'] ?? [])
        : [];

    foreach ($rawGenres as $genre) {
        if (!is_array($genre) || empty($genre['tag'])) {
            continue;
        }

        $genres[] = [
            'name' => $genre['tag'],
            'slug' => $genre['tagSlug'] ?? null,
        ];
    }

    return [
        'id' => (int)$book['id'],
        'hardcover_id' => (string)$book['id'],
        'title' => $book['title'] ?? '',
        'headline' => $book['headline'] ?? null,
        'description' => $book['description'] ?? null,
        'cover_url' => $book['cached_image'] ?? null,
        'release_date' => $book['release_date'] ?? null,
        'authors' => $authors,
        'series' => $series,
        'genres' => $genres,
        'status' => null,
    ];
}

/**
 * Adds locally saved reading statuses with one indexed lookup for the result set.
 *
 * @param PDO $pdo Active database connection.
 * @param array $books Normalized Hardcover books.
 * @return array Books enriched with status or null.
 */
function enrichStatuses(PDO $pdo, array $books): array
{
    if ($books === []) return $books;
    $ids = array_map(static fn(array $book): string => (string)$book['hardcover_id'], $books);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT b.external_api_id, ub.status FROM books b JOIN user_books ub ON ub.book_id = b.id WHERE b.external_api_id IN ($placeholders)");
    $stmt->execute($ids);
    $statuses = [];
    foreach ($stmt->fetchAll() as $row) $statuses[(string)$row['external_api_id']] = $row['status'];
    foreach ($books as &$book) $book['status'] = $statuses[(string)$book['hardcover_id']] ?? null;
    unset($book);
    return $books;
}

// Only allow GET requests.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    respond(['error' => 'Method not allowed.'], 405);
}

// Read the Hardcover token from Docker environment variables.
$token = trim((string)getenv('HARDCOVER_API_TOKEN'));

if ($token === '') {
    respond(['error' => 'Hardcover API is not configured.'], 503);
}
$pdo = require __DIR__ . '/../settings/init.php';

// Use the last 30 days to find currently trending books.
$to = new DateTimeImmutable('today', new DateTimeZone('UTC'));
$from = $to->modify('-30 days');

// Step 1: Get the IDs of the 20 trending books.
$trendingQuery = <<<'GRAPHQL'
query TrendingBooks(
    $from: date!
    $to: date!
    $limit: Int!
) {
    books_trending(
        from: $from
        to: $to
        limit: $limit
        offset: 0
    ) {
        ids
    }
}
GRAPHQL;

$trendingData = hardcoverRequest(
    $trendingQuery,
    [
        'from' => $from->format('Y-m-d'),
        'to' => $to->format('Y-m-d'),
        'limit' => BOOK_LIMIT,
    ],
    $token
);

$ids = $trendingData['books_trending']['ids'] ?? [];

if (!is_array($ids) || $ids === []) {
    respond([]);
}

// Validate and limit the returned IDs.
$ids = array_slice(
    array_values(
        array_unique(
            array_filter(
                array_map('intval', $ids),
                fn(int $id): bool => $id > 0
            )
        )
    ),
    0,
    BOOK_LIMIT
);

if ($ids === []) {
    respond([]);
}

// Step 2: Fetch details for all 20 books in one request.
$booksQuery = <<<'GRAPHQL'
query PopularBookDetails($ids: [Int!]!) {
    books(where: {id: {_in: $ids}}) {
        id
        title
        headline
        description
        release_date
        cached_image(path: "url")
        cached_tags
        contributions {
            author {
                id
                name
            }
        }
        book_series {
            position
            series {
                id
                name
            }
        }
    }
}
GRAPHQL;

$booksData = hardcoverRequest(
    $booksQuery,
    ['ids' => $ids],
    $token
);

// Index books by Hardcover ID.
$booksById = [];

foreach ($booksData['books'] ?? [] as $book) {
    if (is_array($book) && isset($book['id'])) {
        $booksById[(int)$book['id']] = normalizeBook($book);
    }
}

// Preserve the original trending order.
$popularBooks = [];

foreach ($ids as $id) {
    if (isset($booksById[$id])) {
        $popularBooks[] = $booksById[$id];
    }
}

respond(enrichStatuses($pdo, $popularBooks));

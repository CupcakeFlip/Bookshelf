<?php
/** Made with AI */

declare(strict_types=1);

/**
 * Metadata synchronization endpoint.
 *
 * POST-only and intended for the application-triggered sync action. It
 * refreshes shared book metadata and relationships in batches while leaving
 * user_books reading statuses untouched. A 207 response reports partial
 * success; 409 indicates another synchronization already holds the lock.
 */
const HARDCOVER_API_URL = 'https://api.hardcover.app/v1/graphql';
const SYNC_BATCH_SIZE = 20;

/**
 * Sends a JSON response and stops execution.
 *
 * @param array $payload Response data.
 * @param int $status HTTP response status.
 * @return never
 */
function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Returns the first non-null, non-empty value.
 *
 * @param array $values Candidate values in priority order.
 * @return mixed Selected value, or null when no value is usable.
 */
function firstValue(array $values): mixed
{
    foreach ($values as $value) {
        if ($value !== null && $value !== '') {
            return $value;
        }
    }
    return null;
}

/**
 * Makes supported Hardcover fields consistent with the existing book API shape.
 *
 * @param array $book Raw Hardcover book.
 * @return array Normalized metadata and relationships.
 */
function normalizeBook(array $book): array
{
    $authors = [];
    foreach ($book['contributions'] ?? [] as $contribution) {
        $author = $contribution['author'] ?? null;
        if (is_array($author) && !empty($author['name'])) {
            $authors[(string)($author['id'] ?? $author['name'])] =
                ['id' => $author['id'] ?? null, 'name' => $author['name']];
        }
    }
    $series = null;
    $relation = $book['book_series'][0] ?? null;
    if (is_array($relation) && is_array($relation['series'] ?? null) && !empty($relation['series']['name'])) {
        $series =
            [
                'id' => $relation['series']['id'] ?? null,
                'name' => $relation['series']['name'],
                'position' => $relation['position'] ?? null
            ];
    }
    $genres = [];
    foreach (($book['cached_tags']['Genre'] ?? []) as $genre) {
        if (is_array($genre) && !empty($genre['tag']) && !empty($genre['tagSlug'])) {
            $genres[$genre['tagSlug']] =
                ['name' => $genre['tag'], 'slug' => $genre['tagSlug']];
        }
    }
    return [
        'hardcover_id' => (string)$book['id'],
        'title' => firstValue([$book['title'] ?? null]),
        'headline' => array_key_exists('headline', $book) ? $book['headline'] : null,
        'description' => $book['description'] ?? null,
        'cover_url' => firstValue([$book['cached_image'] ?? null]),
        'release_date' => $book['release_date'] ?? null,
        'authors' => array_values($authors),
        'series' => $series,
        'genres' => array_values($genres),
    ];
}

/**
 * Performs one Hardcover request and returns only its data payload.
 *
 * @param string $query GraphQL query.
 * @param array $variables GraphQL variables.
 * @param string $token Hardcover API token.
 * @return array GraphQL data payload.
 * @throws RuntimeException When Hardcover returns invalid or unsuccessful data.
 */
function hardcoverRequest(string $query, array $variables, string $token): array
{
    $curl = curl_init(HARDCOVER_API_URL);
    curl_setopt_array(
        $curl,
        [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['query' => $query, 'variables' => $variables]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
        ]
    );
    $body = curl_exec($curl);
    $httpStatus = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $response = is_string($body) ? json_decode($body, true) : null;
    if (
        $body === false ||
        $httpStatus < 200 ||
        $httpStatus >= 300 ||
        !is_array($response) ||
        !empty($response['errors'])
    ) {
        throw new RuntimeException('Hardcover request failed.');
    }
    return is_array($response['data'] ?? null) ? $response['data'] :
        throw new RuntimeException('Invalid Hardcover response.');
}

/** Returns a deterministic author representation matching the persisted join data. */
function comparableAuthors(array $authors): array
{
    $normalized = [];
    foreach ($authors as $author) {
        $id = trim((string)($author['id'] ?? ''));
        $name = trim((string)($author['name'] ?? ''));
        // Authors without an external ID cannot be persisted by updateBook().
        if ($id === '' || $name === '') {
            continue;
        }
        $normalized[] = ['id' => $id, 'name' => $name];
    }
    usort($normalized, static fn(array $a, array $b): int => [$a['id'], $a['name']] <=> [$b['id'], $b['name']]);
    return $normalized;
}

/** Returns a deterministic genre representation with stable associative keys. */
function comparableGenres(array $genres): array
{
    $normalized = [];
    foreach ($genres as $genre) {
        $slug = trim((string)($genre['slug'] ?? ''));
        $name = trim((string)($genre['name'] ?? ''));
        if ($slug === '' || $name === '') {
            continue;
        }
        $normalized[] = ['slug' => $slug, 'name' => $name];
    }
    usort($normalized, static fn(array $a, array $b): int => [$a['slug'], $a['name']] <=> [$b['slug'], $b['name']]);
    return $normalized;
}

/** Compares values as persisted strings, avoiding numeric/string representation noise. */
function comparableValue(mixed $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    return trim((string)$value);
}

/** Compares decimal series positions by numeric value rather than formatting. */
function comparablePosition(mixed $value): ?string
{
    $value = comparableValue($value);
    return $value === null ? null : number_format((float)$value, 6, '.', '');
}

/**
 * Updates one existing book and its metadata joins without touching user_books.
 *
 * @param PDO $pdo Active database connection.
 * @param array $book Normalized Hardcover book.
 * @return bool True when at least one persisted field or relationship changed.
 * @throws RuntimeException If the local book no longer exists.
 * @throws Throwable If the transaction fails.
 */
function updateBook(PDO $pdo, array $book): bool
{
    $stmt =
        $pdo->prepare(
            'SELECT
                b.*,
                s.external_api_id AS series_external_api_id,
                s.name AS series_name
            FROM books AS b
            LEFT JOIN series AS s
                ON s.id = b.series_id
            WHERE b.external_api_id = ?'
        );
    $stmt->execute([$book['hardcover_id']]);
    $local = $stmt->fetch();
    if (!$local) {
        throw new RuntimeException('Book no longer exists.');
    }
    $stmt =
        $pdo->prepare(
            'SELECT
                a.external_api_id,
                a.name
            FROM book_authors AS ba
            JOIN authors AS a
                ON a.id = ba.author_id
            WHERE ba.book_id = ?
            ORDER BY a.external_api_id, a.name'
        );
    $stmt->execute([$local['id']]);
    $localAuthors = comparableAuthors(
        array_map(
            static fn(array $author): array => ['id' => $author['external_api_id'], 'name' => $author['name']],
            $stmt->fetchAll()
        )
    );
    $stmt =
        $pdo->prepare(
            'SELECT
                g.slug,
                g.name
            FROM book_genres AS bg
            JOIN genres AS g
                ON g.id = bg.genre_id
            WHERE bg.book_id = ?
            ORDER BY g.slug, g.name'
        );
    $stmt->execute([$local['id']]);
    $localGenres = comparableGenres($stmt->fetchAll());
    $remoteAuthors = comparableAuthors($book['authors']);
    $remoteGenres = comparableGenres($book['genres']);
    $localHeadline = comparableValue($local['headline']);
    $localDescription = comparableValue($local['description']);
    $localCover = comparableValue($local['cover_url']);
    $localReleaseDate = comparableValue($local['release_date']);
    $scalarChanges =
        (comparableValue($book['title']) !== null &&
            comparableValue($book['title']) !== comparableValue($local['title'])
        ) ||
        (comparableValue($book['headline'] ?? null) !== null &&
            comparableValue($book['headline'] ?? null) !== $localHeadline
        ) ||
        (comparableValue($book['description']) !== null && comparableValue($book['description']) !== $localDescription
        ) ||
        (comparableValue($book['cover_url']) !== null && comparableValue($book['cover_url']) !== $localCover
        ) ||
        (comparableValue($book['release_date']) !== null &&
            comparableValue($book['release_date']) !== $localReleaseDate);
    $seriesChanged =
        is_array($book['series']) &&
        $book['series']['id'] !== null &&
        (
            comparableValue($book['series']['id']) !== comparableValue($local['series_external_api_id'] ?? null) ||
            comparableValue($book['series']['name']) !== comparableValue($local['series_name'] ?? null) ||
            (
                $book['series']['position'] !==
                null &&
                comparablePosition($book['series']['position']) !==
                comparablePosition($local['series_position'] ?? null)
            ));
    $authorsChanged =
        $remoteAuthors !== [] && $remoteAuthors !== $localAuthors;

    $genresChanged =
        $remoteGenres !== [] && $remoteGenres !== $localGenres;

    if (!$scalarChanges && !$seriesChanged && !$authorsChanged && !$genresChanged) {
        return false;
    }
    $pdo->beginTransaction();
    try {
        $seriesId = $local['series_id'];
        if (
            is_array($book['series']) &&
            $book['series']['id'] !== null &&
            $book['series']['name'] !==
            ''
        ) {
            $stmt = $pdo->prepare('SELECT id FROM series WHERE external_api_id = ?');

            $stmt->execute([(string)$book['series']['id']]);

            $seriesId = $stmt->fetchColumn();

            if (!$seriesId) {
                $stmt = $pdo->prepare('INSERT INTO series (external_api_id, name) VALUES (?, ?)');
                $stmt->execute([(string)$book['series']['id'], $book['series']['name']]);
                $seriesId = $pdo->lastInsertId();
            } else {
                $pdo->prepare('UPDATE series SET name = ? WHERE id = ?')->execute([$book['series']['name'], $seriesId]);
            }
        }
        $fields = [
            'title = COALESCE(NULLIF(?, \'\'), title)',
            'headline = COALESCE(NULLIF(?, \'\'), headline)',
            'description = COALESCE(NULLIF(?, \'\'), description)',
            'cover_url = COALESCE(NULLIF(?, \'\'), cover_url)',
            'release_date = COALESCE(NULLIF(?, \'\'), release_date)'
        ];
        $params = [
            $book['title'],
            $book['headline'],
            $book['description'],
            $book['cover_url'],
            $book['release_date']
        ];
        if ($seriesId !== null) {
            $fields[] = 'series_id = ?';
            $params[] = $seriesId;
            if (is_array($book['series']) && $book['series']['position'] !== null) {
                $fields[] = 'series_position = ?';
                $params[] = $book['series']['position'];
            }
        }
        $params[] = $local['id'];
        if ($scalarChanges || $seriesChanged) {
            $pdo->prepare(
                'UPDATE books SET ' .
                implode(', ', $fields) .
                ' WHERE id = ?'
            )->execute($params);
        }
        if ($authorsChanged) {
            $pdo->prepare('DELETE FROM book_authors WHERE book_id = ?')->execute([$local['id']]);
            foreach ($book['authors'] as $author) {
                if ($author['id'] === null) {
                    continue;
                }
                $stmt = $pdo->prepare('SELECT id FROM authors WHERE external_api_id = ?');
                $stmt->execute([(string)$author['id']]);
                $authorId = $stmt->fetchColumn();
                if (!$authorId) {
                    $stmt = $pdo->prepare('INSERT INTO authors (external_api_id, name) VALUES (?, ?)');
                    $stmt->execute([(string)$author['id'], $author['name']]);
                    $authorId = $pdo->lastInsertId();
                }
                $pdo
                    ->prepare('INSERT IGNORE INTO book_authors (book_id, author_id) VALUES (?, ?)')
                    ->execute([$local['id'], $authorId]);
            }
        }
        if ($genresChanged) {
            $pdo->prepare('DELETE FROM book_genres WHERE book_id = ?')->execute([$local['id']]);
            foreach ($book['genres'] as $genre) {
                $stmt = $pdo->prepare('SELECT id FROM genres WHERE slug = ?');
                $stmt->execute([$genre['slug']]);
                $genreId = $stmt->fetchColumn();
                if (!$genreId) {
                    $stmt = $pdo->prepare('INSERT INTO genres (name, slug) VALUES (?, ?)');
                    $stmt->execute([$genre['name'], $genre['slug']]);
                    $genreId = $pdo->lastInsertId();
                }
                $pdo
                    ->prepare('INSERT IGNORE INTO book_genres (book_id, genre_id) VALUES (?, ?)')
                    ->execute([$local['id'], $genreId]);
            }
        }
        $pdo->commit();
        return true;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    respond(['error' => 'Method not allowed.'], 405);
}
if (
    ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !==
    'XMLHttpRequest'
) {
    respond(['error' => 'This action must be initiated by the application.'], 403);
}
$origin = $_SERVER['HTTP_ORIGIN'] ?? null;
$originHost = $origin !== null ? parse_url($origin, PHP_URL_HOST) : null;
$requestHost = parse_url(
    'http://' . ($_SERVER['HTTP_HOST'] ?? ''),
    PHP_URL_HOST
);

if ($origin !== null && $originHost !== $requestHost) {
    respond(['error' => 'Invalid request origin.'], 403);
}
$token = trim((string)getenv('HARDCOVER_API_TOKEN'));
if ($token === '') {
    respond(['error' => 'Hardcover API is not configured.'], 503);
}
$lock = fopen(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bookshelf-hardcover-sync.lock', 'c+');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    respond(
        ['error' => 'A synchronization is already running.'],
        409
    );
}
$pdo = require __DIR__ . '/../settings/init.php';
$rows =
    $pdo
        ->query(
            "SELECT
                id,
                external_api_id
            FROM books
            WHERE external_api_id IS NOT NULL
                AND external_api_id <> ''
            ORDER BY id"
        )
        ->fetchAll();
$result = [
    'total' => count($rows),
    'selected' => count($rows),
    'processed' => 0,
    'updated' => 0,
    'unchanged' => 0,
    'failed' => 0,
    'errors' => [],
];
if ($rows === []) {
    $result['message'] = 'No eligible books were selected for synchronization.';
    respond($result);
}
$query = <<<'GRAPHQL'
query SyncBooks($ids: [Int!]!) { books(where: {id: {_in: $ids}}) { id title headline description release_date cached_image(path: "url") cached_tags contributions { author { id name } } book_series { position series { id name } } } }
GRAPHQL;
foreach (array_chunk($rows, SYNC_BATCH_SIZE) as $batch) {
    try {
        $ids =
            array_values(
                array_filter(
                    array_map(static fn(array $row): int => (int)$row['external_api_id'], $batch),
                    static fn(int $id): bool => $id > 0
                )
            );
        if ($ids === []) {
            throw new RuntimeException('No valid Hardcover IDs in batch.');
        }
        $data = hardcoverRequest($query, ['ids' => $ids], $token);
        $byId = [];
        foreach ($data['books'] ?? [] as $raw) {
            if (is_array($raw) && isset($raw['id'])) {
                $byId[(string)$raw['id']] = normalizeBook($raw);
            }
        }
        foreach ($batch as $row) {
            try {
                $result['processed']++;
                if (!isset($byId[(string)$row['external_api_id']])) {
                    throw new RuntimeException(
                        'Hardcover book was not found.'
                    );
                }
                if (updateBook($pdo, $byId[(string)$row['external_api_id']])) {
                    $result['updated']++;
                } else {
                    $result['unchanged']++;
                }
            } catch (Throwable $exception) {
                $result['failed']++;
                $result['errors'][] =
                    ['hardcover_id' => (string)$row['external_api_id'], 'error' => $exception->getMessage()];
            }
        }
    } catch (Throwable $exception) {
        foreach ($batch as $row) {
            $result['failed']++;
            $result['errors'][] =
                ['hardcover_id' => (string)$row['external_api_id'], 'error' => 'Batch request failed.'];
        }
    }
}
flock($lock, LOCK_UN);
fclose($lock);
respond($result, $result['failed'] > 0 ? 207 : 200);

<?php

declare(strict_types=1);

// This endpoint searches the Hardcover API for books matching the user's query.
// It validates the request, calls the external API, normalizes the response,
// and then returns a clean JSON structure to the frontend.

const HARDCOVER_API_URL = 'https://api.hardcover.app/v1/graphql';

/**
 * Sends a JSON response and stops execution immediately.
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
 * Returns the first value that is not null or empty.
 *
 * @param array $values Values to inspect in order.
 * @return mixed The first usable value, or null when none exists.
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
 * Normalizes author data from the different Hardcover response shapes.
 *
 * @param mixed $value Raw author data.
 * @return array Unique authors with IDs and names.
 */
function normalizeAuthors(mixed $value): array
{
    if (!is_array($value)) {
        return [];
    }

    $authors = [];
    foreach ($value as $author) {
        if (is_string($author)) {
            $name = $author;
            $id = null;
        } elseif (is_array($author)) {
            $authorData = is_array($author['author'] ?? null) ? $author['author'] : $author;
            $name = $authorData['name'] ?? null;
            $id = $authorData['id'] ?? null;
        } else {
            continue;
        }

        if (is_string($name) && $name !== '') {
            $authors[] = ['id' => $id, 'name' => $name];
        }
    }

    $uniqueAuthors = [];
    foreach ($authors as $author) {
        $key = ($author['id'] ?? '') . '|' . $author['name'];
        $uniqueAuthors[$key] = $author;
    }

    return array_values($uniqueAuthors);
}

/**
 * Normalizes a series relationship or direct series object.
 *
 * @param mixed $value Raw series data.
 * @return array|null Normalized series data, or null when unavailable.
 */
function normalizeSeries(mixed $value): ?array
{
    if (!is_array($value)) {
        return null;
    }

    if (array_is_list($value)) {
        $value = $value[0] ?? null;
        if (!is_array($value)) {
            return null;
        }
    }

    $series = is_array($value['series'] ?? null) ? $value['series'] : [];
    $name = firstValue([$series['name'] ?? null, $value['name'] ?? null]);

    if ($name === null) {
        return null;
    }

    $id = firstValue([
        $series['id'] ?? null,
        $value['series_id'] ?? null,
    ]);

    // A direct series object may legitimately expose its own id. Relation objects must use series.id above.
    if ($id === null && $series === []) {
        $id = $value['id'] ?? null;
    }

    return [
        'id' => $id,
        'name' => $name,
        'position' => firstValue([$value['position'] ?? null, $value['series_position'] ?? null]),
    ];
}

/**
 * Converts Hardcover genre tags into the frontend format.
 *
 * @param mixed $cachedTags Raw cached tag data.
 * @return array Normalized genres.
 */
function normalizeGenres(mixed $cachedTags): array
{
    $genres = is_array($cachedTags) && is_array($cachedTags['Genre'] ?? null)
        ? $cachedTags['Genre']
        : [];
    $normalized = [];

    foreach ($genres as $genre) {
        if (!is_array($genre) || !isset($genre['tag'], $genre['tagSlug'])) {
            continue;
        }

        $normalized[] = [
            'name' => $genre['tag'],
            'slug' => $genre['tagSlug'],
        ];
    }

    return $normalized;
}

/**
 * Converts a raw Hardcover result into the frontend book shape.
 *
 * @param mixed $book Raw search result or book document.
 * @param array|null $details Optional detail response for description and tags.
 * @return array Normalized book data.
 */
function normalizeBook(mixed $book, ?array $details = null): array
{
    if (!is_array($book)) {
        return [
            'hardcover_id' => null,
            'title' => null,
            'authors' => [],
            'cover_url' => null,
            'series' => null,
            'release_date' => null,
        ];
    }

    // Hardcover wraps each search hit in a hit object; book fields are in document.
    if (is_array($book['document'] ?? null)) {
        $book = $book['document'];
    }

    $image = is_array($book['image'] ?? null) ? $book['image'] : [];
    $cachedImage = is_array($book['cached_image'] ?? null) ? $book['cached_image'] : [];
    $contributions = is_array($book['contributions'] ?? null) ? $book['contributions'] : [];
    $authorNames = is_array($book['author_names'] ?? null) ? $book['author_names'] : [];

    return [
        'hardcover_id' => firstValue([$book['id'] ?? null, $book['book_id'] ?? null]),
        'title' => firstValue([$book['title'] ?? null, $book['name'] ?? null]),
        'description' => firstValue([$details['description'] ?? null, $book['description'] ?? null]),
        'authors' => normalizeAuthors($contributions !== [] ? $contributions : $authorNames),
        'cover_url' => firstValue([
            $book['cover_url'] ?? null,
            $book['image_url'] ?? null,
            $image['url'] ?? null,
            $cachedImage['url'] ?? null,
        ]),
        'series' => normalizeSeries(firstValue([
            $book['featured_series'] ?? null,
            $book['book_series'] ?? null,
            $book['series'] ?? null,
        ])),
        'genres' => normalizeGenres($details['cached_tags'] ?? null),
        'release_date' => firstValue([
            $book['release_date'] ?? null,
            $book['publication_date'] ?? null,
            $book['published_date'] ?? null,
        ]),
    ];
}

/**
 * Fetches description and genre details for one Hardcover book.
 *
 * @param int $bookId Hardcover book identifier.
 * @param string $token Hardcover API token.
 * @return array|null Detail data, or null when the request fails.
 */
function fetchBookDetails(int $bookId, string $token): ?array
{
    $graphql = <<<'GRAPHQL'
query BookDetail($id: Int!) {
  books_by_pk(id: $id) {
    id
    description
    cached_tags
  }
}
GRAPHQL;

    $curl = curl_init(HARDCOVER_API_URL);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'query' => $graphql,
            'variables' => ['id' => $bookId],
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
    ]);

    $responseBody = curl_exec($curl);
    $httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);


    if ($responseBody === false || $httpStatus < 200 || $httpStatus >= 300) {
        return null;
    }

    $response = json_decode($responseBody, true);
    if (!is_array($response) || isset($response['errors'])) {
        return null;
    }

    return is_array($response['data']['books_by_pk'] ?? null)
        ? $response['data']['books_by_pk']
        : null;
}

// Validate the incoming search term before the API call.
$query = trim((string) ($_GET['q'] ?? ''));
if ($query === '') {
    respond(['error' => 'A non-empty q search parameter is required.'], 400);
}

if (strlen($query) > 200) {
    respond(['error' => 'The search query is too long.'], 422);
}

// Make sure the external API token is configured before contacting Hardcover.
$token = trim((string) getenv('HARDCOVER_API_TOKEN'));
if ($token === '') {
    respond(['error' => 'The Hardcover API is not configured.'], 503);
}

// Build the GraphQL request used to search for books.
$graphql = <<<'GRAPHQL'
query SearchBooks($query: String!) {
  search(query: $query, query_type: "book", per_page: 20) {
    error
    results
  }
}
GRAPHQL;

$requestBody = json_encode([
    'query' => $graphql,
    'variables' => ['query' => $query],
]);

// Send the request to Hardcover with the bearer token and a short timeout.
$curl = curl_init(HARDCOVER_API_URL);
curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $requestBody,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 15,
]);

$responseBody = curl_exec($curl);
$curlError = curl_error($curl);
$httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

if ($responseBody === false) {
    respond(['error' => 'Could not reach the Hardcover API.'], 502);
}

// Decode the API response and fail early if the payload is malformed.
$response = json_decode($responseBody, true);
if (!is_array($response)) {
    respond(['error' => 'The Hardcover API returned malformed data.'], 502);
}

if ($httpStatus === 401 || $httpStatus === 403) {
    respond(['error' => 'The Hardcover API token was rejected.'], 502);
}

if ($httpStatus < 200 || $httpStatus >= 300) {
    respond(['error' => 'The Hardcover API returned an HTTP error.'], 502);
}

if (isset($response['errors']) && is_array($response['errors'])) {
    respond(['error' => 'The Hardcover API returned a GraphQL error.'], 502);
}

// The GraphQL response has a nested data.search object that always needs checking.
$search = $response['data']['search'] ?? null;
if (!is_array($search)) {
    respond(['error' => 'The Hardcover API returned an unexpected response.'], 502);
}

if (!empty($search['error'])) {
    respond(['error' => 'The Hardcover API could not complete the search.'], 502);
}

// Extract the hit list and prepare a simplified list for the frontend.
$searchResults = is_array($search['results'] ?? null) ? $search['results'] : [];
$hits = is_array($searchResults['hits'] ?? null) ? $searchResults['hits'] : [];
$normalizedBooks = [];

foreach ($hits as $hit) {
    $book = is_array($hit['document'] ?? null) ? $hit['document'] : null;
    $bookId = is_array($book) && is_numeric($book['id'] ?? null) ? (int) $book['id'] : null;
    $details = $bookId === null ? null : fetchBookDetails($bookId, $token);
    $normalizedBooks[] = normalizeBook($hit, $details);
}

respond([
    'query' => $query,
    'results' => $normalizedBooks,
    'metadata' => [
        'found' => $searchResults['found'] ?? 0,
        'page' => $searchResults['page'] ?? 1,
        'out_of' => $searchResults['out_of'] ?? 0,
    ],
]);

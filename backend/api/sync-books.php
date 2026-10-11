<?php

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
    foreach ($values as $value) if ($value !== null && $value !== '') return $value;
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
        if (is_array($author) && !empty($author['name'])) $authors[(string)($author['id'] ?? $author['name'])] = ['id' => $author['id'] ?? null, 'name' => $author['name']];
    }
    $series = null;
    $relation = $book['book_series'][0] ?? null;
    if (is_array($relation) && is_array($relation['series'] ?? null) && !empty($relation['series']['name'])) {
        $series = ['id' => $relation['series']['id'] ?? null, 'name' => $relation['series']['name'], 'position' => $relation['position'] ?? null];
    }
    $genres = [];
    foreach (($book['cached_tags']['Genre'] ?? []) as $genre) {
        if (is_array($genre) && !empty($genre['tag']) && !empty($genre['tagSlug'])) $genres[$genre['tagSlug']] = ['name' => $genre['tag'], 'slug' => $genre['tagSlug']];
    }
    return [
        'hardcover_id' => (string)$book['id'],
        'title' => firstValue([$book['title'] ?? null]),
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
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['query' => $query, 'variables' => $variables]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
    ]);
    $body = curl_exec($curl);
    $httpStatus = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $response = is_string($body) ? json_decode($body, true) : null;
    if ($body === false || $httpStatus < 200 || $httpStatus >= 300 || !is_array($response) || !empty($response['errors'])) throw new RuntimeException('Hardcover request failed.');
    return is_array($response['data'] ?? null) ? $response['data'] : throw new RuntimeException('Invalid Hardcover response.');
}

/**
 * Updates one existing book and its metadata joins without touching user_books.
 *
 * @param PDO $pdo Active database connection.
 * @param array $book Normalized Hardcover book.
 * @return void
 * @throws RuntimeException If the local book no longer exists.
 * @throws Throwable If the transaction fails.
 */
function updateBook(PDO $pdo, array $book): void
{
    $stmt = $pdo->prepare('SELECT id, series_id FROM books WHERE external_api_id = ?');
    $stmt->execute([$book['hardcover_id']]);
    $local = $stmt->fetch();
    if (!$local) throw new RuntimeException('Book no longer exists.');
    $pdo->beginTransaction();
    try {
        $seriesId = $local['series_id'];
        if (is_array($book['series']) && $book['series']['id'] !== null && $book['series']['name'] !== '') {
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
        $fields = ['title = COALESCE(NULLIF(?, \'\'), title)', 'description = COALESCE(NULLIF(?, \'\'), description)', 'cover_url = COALESCE(NULLIF(?, \'\'), cover_url)', 'release_date = COALESCE(NULLIF(?, \'\'), release_date)'];
        $params = [$book['title'], $book['description'], $book['cover_url'], $book['release_date']];
        if ($seriesId !== null) {
            $fields[] = 'series_id = ?';
            $params[] = $seriesId;
            if (is_array($book['series']) && $book['series']['position'] !== null) {
                $fields[] = 'series_position = ?';
                $params[] = $book['series']['position'];
            }
        }
        $params[] = $local['id'];
        $pdo->prepare('UPDATE books SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
        if ($book['authors'] !== []) {
            $pdo->prepare('DELETE FROM book_authors WHERE book_id = ?')->execute([$local['id']]);
            foreach ($book['authors'] as $author) {
                if ($author['id'] === null) continue;
                $stmt = $pdo->prepare('SELECT id FROM authors WHERE external_api_id = ?');
                $stmt->execute([(string)$author['id']]);
                $authorId = $stmt->fetchColumn();
                if (!$authorId) {
                    $stmt = $pdo->prepare('INSERT INTO authors (external_api_id, name) VALUES (?, ?)');
                    $stmt->execute([(string)$author['id'], $author['name']]);
                    $authorId = $pdo->lastInsertId();
                }
                $pdo->prepare('INSERT IGNORE INTO book_authors (book_id, author_id) VALUES (?, ?)')->execute([$local['id'], $authorId]);
            }
        }
        if ($book['genres'] !== []) {
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
                $pdo->prepare('INSERT IGNORE INTO book_genres (book_id, genre_id) VALUES (?, ?)')->execute([$local['id'], $genreId]);
            }
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') respond(['error' => 'Method not allowed.'], 405);
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') respond(['error' => 'This action must be initiated by the application.'], 403);
$origin = $_SERVER['HTTP_ORIGIN'] ?? null;
if ($origin !== null && parse_url($origin, PHP_URL_HOST) !== ($_SERVER['HTTP_HOST'] ?? null)) respond(['error' => 'Invalid request origin.'], 403);
$token = trim((string)getenv('HARDCOVER_API_TOKEN'));
if ($token === '') respond(['error' => 'Hardcover API is not configured.'], 503);
$lock = fopen(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bookshelf-hardcover-sync.lock', 'c+');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) respond(['error' => 'A synchronization is already running.'], 409);
$pdo = require __DIR__ . '/../settings/init.php';
$rows = $pdo->query("SELECT id, external_api_id FROM books WHERE external_api_id IS NOT NULL AND external_api_id <> '' ORDER BY id")->fetchAll();
$result = ['total' => count($rows), 'updated' => 0, 'failed' => 0, 'errors' => []];
$query = <<<'GRAPHQL'
query SyncBooks($ids: [Int!]!) { books(where: {id: {_in: $ids}}) { id title description release_date cached_image(path: "url") cached_tags contributions { author { id name } } book_series { position series { id name } } } }
GRAPHQL;
foreach (array_chunk($rows, SYNC_BATCH_SIZE) as $batch) {
    try {
        $ids = array_values(array_filter(array_map(static fn(array $row): int => (int)$row['external_api_id'], $batch), static fn(int $id): bool => $id > 0));
        $data = hardcoverRequest($query, ['ids' => $ids], $token);
        $byId = [];
        foreach ($data['books'] ?? [] as $raw) if (is_array($raw) && isset($raw['id'])) $byId[(string)$raw['id']] = normalizeBook($raw);
        foreach ($batch as $row) {
            try {
                if (!isset($byId[(string)$row['external_api_id']])) throw new RuntimeException('Hardcover book was not found.');
                updateBook($pdo, $byId[(string)$row['external_api_id']]);
                $result['updated']++;
            } catch (Throwable $exception) {
                $result['failed']++;
                $result['errors'][] = ['hardcover_id' => (string)$row['external_api_id'], 'error' => $exception->getMessage()];
            }
        }
    } catch (Throwable $exception) {
        foreach ($batch as $row) {
            $result['failed']++;
            $result['errors'][] = ['hardcover_id' => (string)$row['external_api_id'], 'error' => 'Batch request failed.'];
        }
    }
}
flock($lock, LOCK_UN);
fclose($lock);
respond($result, $result['failed'] > 0 ? 207 : 200);

<?php

declare(strict_types=1);

const HARDCOVER_API_URL = 'https://api.hardcover.app/v1/graphql';

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function firstValue(array $values): mixed
{
    foreach ($values as $value) {
        if ($value !== null && $value !== '') return $value;
    }
    return null;
}

function normalizeBook(array $book): array
{
    return [
        'id' => (int) $book['id'],
        'hardcover_id' => (string) $book['external_api_id'],
        'title' => $book['title'],
        'description' => $book['description'],
        'cover_url' => $book['cover_url'],
        'release_date' => $book['release_date'],
        'authors' => $book['authors'] ?? [],
        'series' => $book['series_local_id'] ? [
            'id' => (int) $book['series_local_id'],
            'hardcover_id' => (string) $book['series_external_api_id'],
            'name' => $book['series_name'],
            'position' => $book['series_position'],
        ] : null,
        'genres' => $book['genres'] ?? [],
        'status' => $book['status'] ?? null,
    ];
}

function fetchHardcoverBook(int $bookId, string $token): array
{
    $graphql = <<<'GRAPHQL'
query BookDetail($id: Int!) {
  books_by_pk(id: $id) {
    id
    title
    description
    release_date
    cached_tags
  }
}
GRAPHQL;
    $curl = curl_init(HARDCOVER_API_URL);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['query' => $graphql, 'variables' => ['id' => $bookId]]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($curl);
    $httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($body === false) respond(['error' => 'Could not reach the Hardcover API.'], 502);
    $response = json_decode($body, true);
    if (!is_array($response) || $httpStatus < 200 || $httpStatus >= 300 || isset($response['errors'])) {
        respond(['error' => 'The Hardcover API returned an error.'], 502);
    }
    $raw = $response['data']['books_by_pk'] ?? null;
    if (!is_array($raw)) respond(['error' => 'The Hardcover book was not found.'], 404);

    // The catalog search payload supplies relationships and cover data; detail data above supplies description/tags.
    $searchQuery = <<<'GRAPHQL'
query SearchBooks($query: String!) { search(query: $query, query_type: "book", per_page: 20) { results } }
GRAPHQL;
    curl_setopt_array($curl = curl_init(HARDCOVER_API_URL), [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['query' => $searchQuery, 'variables' => ['query' => (string) $raw['title']]]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        CURLOPT_TIMEOUT => 15,
    ]);
    $searchBody = curl_exec($curl);
    curl_close($curl);
    $searchResponse = json_decode((string) $searchBody, true);
    foreach ($searchResponse['data']['search']['results']['hits'] ?? [] as $hit) {
        if ((string) ($hit['document']['id'] ?? '') === (string) $bookId) {
            $raw = array_merge($raw, $hit['document']);
            break;
        }
    }

    $authors = [];
    foreach (is_array($raw['contributions'] ?? null) ? $raw['contributions'] : [] as $contribution) {
        $author = $contribution['author'] ?? null;
        if (is_array($author) && isset($author['id'], $author['name'])) $authors[(string) $author['id']] = ['id' => (string) $author['id'], 'name' => $author['name']];
    }
    $series = null;
    $relation = $raw['book_series'][0] ?? ($raw['featured_series'] ?? ($raw['series'][0] ?? $raw['series'] ?? null));
    if (is_array($relation) && is_array($relation['series'] ?? null) && isset($relation['series']['id'], $relation['series']['name'])) {
        // book_series.id is the relationship ID; series.id is the real Hardcover series ID.
        $series = ['id' => (string) $relation['series']['id'], 'name' => $relation['series']['name'], 'position' => $relation['position'] ?? null];
    }
    $genres = [];
    foreach (is_array($raw['cached_tags']['Genre'] ?? null) ? $raw['cached_tags']['Genre'] : [] as $genre) {
        if (is_array($genre) && isset($genre['tag'], $genre['tagSlug'])) $genres[$genre['tagSlug']] = ['name' => $genre['tag'], 'slug' => $genre['tagSlug']];
    }
    return [
        'hardcover_id' => (string) $raw['id'],
        'title' => (string) ($raw['title'] ?? ''),
        'description' => $raw['description'] ?? null,
        'cover_url' => firstValue([$raw['cover_url'] ?? null, $raw['image']['url'] ?? null, $raw['cached_image']['url'] ?? null]),
        'release_date' => $raw['release_date'] ?? null,
        'authors' => array_values($authors),
        'series' => $series,
        'genres' => array_values($genres),
    ];
}

function savedBook(PDO $pdo, int $bookId): array
{
    $stmt = $pdo->prepare('SELECT b.*, s.id AS series_local_id, s.external_api_id AS series_external_api_id, s.name AS series_name, ub.status FROM books b LEFT JOIN series s ON s.id = b.series_id LEFT JOIN user_books ub ON ub.book_id = b.id WHERE b.id = ?');
    $stmt->execute([$bookId]);
    $book = $stmt->fetch();
    if (!$book) throw new RuntimeException('Saved book could not be loaded.');
    $stmt = $pdo->prepare('SELECT a.id, a.external_api_id AS hardcover_id, a.name FROM book_authors ba JOIN authors a ON a.id = ba.author_id WHERE ba.book_id = ? ORDER BY a.id');
    $stmt->execute([$bookId]);
    $book['authors'] = $stmt->fetchAll();
    $stmt = $pdo->prepare('SELECT g.id, g.name, g.slug FROM book_genres bg JOIN genres g ON g.id = bg.genre_id WHERE bg.book_id = ? ORDER BY g.id');
    $stmt->execute([$bookId]);
    $book['genres'] = $stmt->fetchAll();
    return normalizeBook($book);
}

function saveBook(PDO $pdo, array $book): array
{
    // One transaction covers series, canonical book, relationships, and user status.
    $pdo->beginTransaction();
    try {
        $seriesId = null;
        if ($book['series']) {
            $stmt = $pdo->prepare('SELECT id FROM series WHERE external_api_id = ?');
            $stmt->execute([$book['series']['id']]);
            $seriesId = $stmt->fetchColumn();
            if (!$seriesId) {
                $stmt = $pdo->prepare('INSERT INTO series (external_api_id, name) VALUES (?, ?)');
                $stmt->execute([$book['series']['id'], $book['series']['name']]);
                $seriesId = (int) $pdo->lastInsertId();
            }
        }
        $stmt = $pdo->prepare('SELECT id FROM books WHERE external_api_id = ?');
        $stmt->execute([$book['hardcover_id']]);
        $bookId = $stmt->fetchColumn();
        if (!$bookId) {
            $stmt = $pdo->prepare('INSERT INTO books (external_api_id, title, description, cover_url, release_date, series_id, series_position) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$book['hardcover_id'], $book['title'], $book['description'], $book['cover_url'], $book['release_date'], $seriesId, $book['series']['position'] ?? null]);
            $bookId = (int) $pdo->lastInsertId();
        }
        foreach ($book['authors'] as $author) {
            $stmt = $pdo->prepare('SELECT id FROM authors WHERE external_api_id = ?');
            $stmt->execute([$author['id']]);
            $authorId = $stmt->fetchColumn();
            if (!$authorId) {
                $stmt = $pdo->prepare('INSERT INTO authors (external_api_id, name) VALUES (?, ?)');
                $stmt->execute([$author['id'], $author['name']]);
                $authorId = (int) $pdo->lastInsertId();
            }
            $pdo->prepare('INSERT IGNORE INTO book_authors (book_id, author_id) VALUES (?, ?)')->execute([$bookId, $authorId]);
        }
        foreach ($book['genres'] as $genre) {
            $stmt = $pdo->prepare('SELECT id FROM genres WHERE slug = ?');
            $stmt->execute([$genre['slug']]);
            $genreId = $stmt->fetchColumn();
            if (!$genreId) {
                $stmt = $pdo->prepare('INSERT INTO genres (name, slug) VALUES (?, ?)');
                $stmt->execute([$genre['name'], $genre['slug']]);
                $genreId = (int) $pdo->lastInsertId();
            }
            $pdo->prepare('INSERT IGNORE INTO book_genres (book_id, genre_id) VALUES (?, ?)')->execute([$bookId, $genreId]);
        }
        $pdo->prepare("INSERT IGNORE INTO user_books (book_id, status) VALUES (?, 'want_to_read')")->execute([$bookId]);
        $pdo->commit();
        return savedBook($pdo, (int) $bookId);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

$pdo = require __DIR__ . '/../config/database.php';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    $books = [];
    foreach ($pdo->query('SELECT id FROM books ORDER BY id')->fetchAll() as $row) $books[] = savedBook($pdo, (int) $row['id']);
    respond($books);
}
if ($method !== 'POST') respond(['error' => 'Method not allowed.'], 405);

$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload) || !array_key_exists('hardcover_id', $payload)) respond(['error' => 'A JSON body with hardcover_id is required.'], 400);
$hardcoverId = filter_var($payload['hardcover_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($hardcoverId === false) respond(['error' => 'hardcover_id must be a positive integer.'], 400);

// Identify existing books before contacting Hardcover so repeated saves are idempotent.
$stmt = $pdo->prepare('SELECT id FROM books WHERE external_api_id = ?');
$stmt->execute([(string) $hardcoverId]);
$existingId = $stmt->fetchColumn();
if ($existingId) {
    $pdo->prepare("INSERT IGNORE INTO user_books (book_id, status) VALUES (?, 'want_to_read')")->execute([$existingId]);
    respond(savedBook($pdo, (int) $existingId));
}
$token = trim((string) getenv('HARDCOVER_API_TOKEN'));
if ($token === '') respond(['error' => 'The Hardcover API is not configured.'], 503);
try {
    $saved = saveBook($pdo, fetchHardcoverBook((int) $hardcoverId, $token));
    respond($saved, 201);
} catch (Throwable $exception) {
    respond(['error' => 'The book could not be saved.'], 500);
}

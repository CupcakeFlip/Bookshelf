<?php

declare(strict_types=1);

require_once __DIR__ . '/reading_sessions.php';

const BOOKS_HARDCOVER_API_URL = 'https://api.hardcover.app/v1/graphql';

function booksFirstValue(array $values): mixed
{
    foreach ($values as $value) {
        if ($value !== null && $value !== '') {
            return $value;
        }
    }
    return null;
}

function booksNormalizeHardcover(array $raw): array
{
    $authors = [];
    foreach (is_array($raw['contributions'] ?? null) ? $raw['contributions'] : [] as $contribution) {
        $author = $contribution['author'] ?? null;
        if (is_array($author) && isset($author['id'], $author['name'])) {
            $authors[(string)$author['id']] = ['id' => (string)$author['id'], 'name' => (string)$author['name']];
        }
    }
    $relation = $raw['book_series'][0] ?? ($raw['featured_series'] ?? ($raw['series'][0] ?? ($raw['series'] ?? null)));
    $series = null;
    if (is_array($relation)) {
        $seriesData = is_array($relation['series'] ?? null) ? $relation['series'] : $relation;
        if (isset($seriesData['id'], $seriesData['name'])) {
            $series =
                [
                    'id' => (string)$seriesData['id'],
                    'name' => (string)$seriesData['name'],
                    'position' => $relation['position'] ?? $relation['series_position'] ?? null
                ];
        }
    }
    $genres = [];
    foreach (is_array($raw['cached_tags']['Genre'] ?? null) ? $raw['cached_tags']['Genre'] : [] as $genre) {
        if (is_array($genre) && isset($genre['tag'], $genre['tagSlug'])) {
            $genres[(string)$genre['tagSlug']] = ['name' => (string)$genre['tag'], 'slug' => (string)$genre['tagSlug']];
        }
    }
    return [
        'hardcover_id' => (string)$raw['id'],
        'title' => (string)($raw['title'] ?? ''),
        'headline' => $raw['headline'] ?? null,
        'description' => $raw['description'] ?? null,
        'cover_url' => booksFirstValue(
            [
                $raw['cover_url'] ?? null,
                $raw['image_url'] ?? null,
                $raw['image']['url'] ?? null,
                $raw['cached_image']['url'] ?? null
            ]
        ),
        'release_date' => $raw['release_date'] ?? null,
        'authors' => array_values($authors),
        'series' => $series,
        'genres' => array_values($genres),
    ];
}

function fetchHardcoverBook(int $bookId, string $token): array
{
    $query = <<<'GRAPHQL'
query BookDetail($id: Int!) {
  books_by_pk(id: $id) { id title headline description release_date cached_tags }
}
GRAPHQL;
    $curl = curl_init(BOOKS_HARDCOVER_API_URL);
    curl_setopt_array(
        $curl,
        [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['query' => $query, 'variables' => ['id' => $bookId]]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15
        ]
    );
    $body = curl_exec($curl);
    $httpStatus = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);
    if ($body === false || $curlError !== '') {
        throw new RuntimeException('Could not reach the Hardcover API.', 502);
    }
    $response = json_decode($body, true);
    if (!is_array($response) || $httpStatus < 200 || $httpStatus >= 300 || isset($response['errors'])) {
        throw new RuntimeException('The Hardcover API returned an error.', 502);
    }
    $raw = $response['data']['books_by_pk'] ?? null;
    if (!is_array($raw)) {
        throw new RuntimeException('The Hardcover book was not found.', 404);
    }
    // The canonical lookup above identifies the book. Search is only an optional
    // enrichment step for fields not exposed by the current books_by_pk schema.
    if (($raw['title'] ?? '') !== '') {
        $search =
            '{ search(query: "' .
            addslashes((string)$raw['title']) .
            '", query_type: "book", per_page: 20) { results } }';
        $enrich = curl_init(BOOKS_HARDCOVER_API_URL);
        curl_setopt_array(
            $enrich,
            [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode(['query' => $search]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $token,
                    'Content-Type: application/json'
                ],
                CURLOPT_TIMEOUT => 15
            ]
        );
        $enrichBody = curl_exec($enrich);
        curl_close($enrich);
        $enrichResponse = json_decode((string)$enrichBody, true);
        foreach ($enrichResponse['data']['search']['results']['hits'] ?? [] as $hit) {
            if ((string)($hit['document']['id'] ?? '') === (string)$bookId && is_array($hit['document'])) {
                $raw = array_merge($raw, $hit['document']);
                break;
            }
        }
    }
    return booksNormalizeHardcover($raw);
}

function normalizeSavedBook(array $book): array
{
    return [
        'id' => (int)$book['id'],
        'hardcover_id' => (string)$book['external_api_id'],
        'title' => $book['title'],
        'headline' => $book['headline'] ?? null,
        'description' => $book['description'],
        'cover_url' => $book['cover_url'],
        'release_date' => $book['release_date'],
        'authors' => $book['authors'] ?? [],
        'series' => $book['series_local_id'] ?
            [
                'id' => (int)$book['series_local_id'],
                'hardcover_id' => (string)$book['series_external_api_id'],
                'name' => $book['series_name'],
                'position' => $book['series_position']
            ] : null,
        'genres' => $book['genres'] ?? [],
        'lists' => $book['lists'] ?? [],
        'tags' => $book['tags'] ?? [],
        'status' => $book['status'] ?? null,
        'updated_at' => $book['status_updated_at'] ?? null
    ];
}

function savedBook(PDO $pdo, int $bookId): array
{
    $stmt =
        $pdo->prepare(
            'SELECT b.*, s.id series_local_id, s.external_api_id series_external_api_id, s.name series_name, ub.status, ub.updated_at status_updated_at FROM books b LEFT JOIN series s ON s.id=b.series_id LEFT JOIN user_books ub ON ub.book_id=b.id WHERE b.id=?'
        );
    $stmt->execute([$bookId]);
    $book = $stmt->fetch();
    if (!$book) {
        throw new RuntimeException('Saved book could not be loaded.');
    }
    foreach (
        [
            'authors' => 'SELECT a.id, a.external_api_id hardcover_id, a.name FROM book_authors ba JOIN authors a ON a.id=ba.author_id WHERE ba.book_id=? ORDER BY a.id',
            'genres' => 'SELECT g.id, g.name, g.slug FROM book_genres bg JOIN genres g ON g.id=bg.genre_id WHERE bg.book_id=? ORDER BY g.id',
            'lists' => 'SELECT l.id, l.name, l.description FROM book_lists bl JOIN lists l ON l.id=bl.lists_id WHERE bl.book_id=? ORDER BY l.name,l.id',
            'tags' => 'SELECT t.id, t.name, t.slug FROM book_tags bt JOIN tags t ON t.id=bt.tags_id WHERE bt.book_id=? ORDER BY t.name,t.id',
        ] as $key => $sql
    ) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$bookId]);
        $book[$key] = $stmt->fetchAll();
    }
    return normalizeSavedBook($book);
}

function savedBooks(PDO $pdo, array $bookIds): array
{
    if (!$bookIds) {
        return [];
    }
    $marks = implode(',', array_fill(0, count($bookIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT b.*, s.id series_local_id, s.external_api_id series_external_api_id, s.name series_name, ub.status, ub.updated_at status_updated_at FROM books b LEFT JOIN series s ON s.id=b.series_id LEFT JOIN user_books ub ON ub.book_id=b.id WHERE b.id IN ($marks)"
    );
    $stmt->execute($bookIds);
    $books = [];
    foreach ($stmt->fetchAll() as $book) {
        $book['authors'] = $book['genres'] = $book['lists'] = $book['tags'] = [];
        $books[(int)$book['id']] = $book;
    }
    foreach ([
        'authors' => 'SELECT ba.book_id, a.id, a.external_api_id hardcover_id, a.name FROM book_authors ba JOIN authors a ON a.id=ba.author_id',
        'genres' => 'SELECT bg.book_id, g.id, g.name, g.slug FROM book_genres bg JOIN genres g ON g.id=bg.genre_id',
        'lists' => 'SELECT bl.book_id, l.id, l.name, l.description FROM book_lists bl JOIN lists l ON l.id=bl.lists_id',
        'tags' => 'SELECT bt.book_id, t.id, t.name, t.slug FROM book_tags bt JOIN tags t ON t.id=bt.tags_id',
    ] as $key => $sql) {
        $order = $key === 'authors' || $key === 'genres' ? 'id' : 'name,id';
        $stmt = $pdo->prepare("$sql WHERE book_id IN ($marks) ORDER BY book_id,$order");
        $stmt->execute($bookIds);
        foreach ($stmt->fetchAll() as $row) {
            $id = (int)$row['book_id'];
            unset($row['book_id']);
            $books[$id][$key][] = $row;
        }
    }
    $out = [];
    foreach ($bookIds as $id) {
        $out[] = normalizeSavedBook($books[$id]);
    }
    return $out;
}

function findSavedBookByHardcoverId(PDO $pdo, int $hardcoverId): ?array
{
    $stmt = $pdo->prepare('SELECT id FROM books WHERE external_api_id=?');
    $stmt->execute([(string)$hardcoverId]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : savedBook($pdo, (int)$id);
}

function externalBookResponse(array $book): array
{
    return [
        'id' => null,
        'hardcover_id' => $book['hardcover_id'],
        'title' => $book['title'],
        'headline' => $book['headline'] ?? null,
        'description' => $book['description'],
        'cover_url' => $book['cover_url'],
        'release_date' => $book['release_date'],
        'authors' => $book['authors'],
        'series' => $book['series'],
        'genres' => $book['genres'],
        'lists' => [],
        'tags' => [],
        'status' => null
    ];
}

function saveBook(PDO $pdo, array $book, string $status = 'want_to_read', array $sessionDetails = []): array
{
    $pdo->beginTransaction();
    try {
        $seriesId = null;
        if ($book['series']) {
            $s = $pdo->prepare('SELECT id FROM series WHERE external_api_id=?');
            $s->execute([$book['series']['id']]);
            $seriesId = $s->fetchColumn();
            if (!$seriesId) {
                $s = $pdo->prepare('INSERT INTO series (external_api_id,name) VALUES (?,?)');
                $s->execute([$book['series']['id'], $book['series']['name']]);
                $seriesId = (int)$pdo->lastInsertId();
            }
        }
        $s = $pdo->prepare('SELECT id FROM books WHERE external_api_id=?');
        $s->execute([$book['hardcover_id']]);
        $bookId = $s->fetchColumn();
        if (!$bookId) {
            $s =
                $pdo->prepare(
                    'INSERT INTO books (external_api_id,title,headline,description,cover_url,release_date,series_id,series_position) VALUES (?,?,?,?,?,?,?,?)'
                );
            $s->execute(
                [
                    $book['hardcover_id'],
                    $book['title'],
                    $book['headline'] ?? null,
                    $book['description'],
                    $book['cover_url'],
                    $book['release_date'],
                    $seriesId,
                    $book['series']['position'] ?? null
                ]
            );
            $bookId = (int)$pdo->lastInsertId();
        }
        foreach ($book['authors'] as $author) {
            $s = $pdo->prepare('SELECT id FROM authors WHERE external_api_id=?');
            $s->execute([$author['id']]);
            $id = $s->fetchColumn();
            if (!$id) {
                $s = $pdo->prepare('INSERT INTO authors (external_api_id,name) VALUES (?,?)');
                $s->execute([$author['id'], $author['name']]);
                $id = $pdo->lastInsertId();
            }
            $pdo
                ->prepare('INSERT IGNORE INTO book_authors VALUES (?,?)')->execute([$bookId, $id]);
        }
        foreach ($book['genres'] as $genre) {
            $s = $pdo->prepare('SELECT id FROM genres WHERE slug=?');
            $s->execute([$genre['slug']]);
            $id = $s->fetchColumn();
            if (!$id) {
                $s = $pdo->prepare('INSERT INTO genres (name,slug) VALUES (?,?)');
                $s->execute([$genre['name'], $genre['slug']]);
                $id = $pdo->lastInsertId();
            }
            $pdo
                ->prepare('INSERT IGNORE INTO book_genres VALUES (?,?)')->execute([$bookId, $id]);
        }
        $pdo
            ->prepare(
                'INSERT INTO user_books (book_id,status) VALUES (?,?) ON DUPLICATE KEY UPDATE status=VALUES(status)'
            )->execute([$bookId, $status]);
        transitionSession($pdo, (int)$bookId, $status, $sessionDetails);
        $pdo->commit();
        return savedBook($pdo, (int)$bookId);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

<?php

declare(strict_types=1);

// Read-only integration checks against the running PHP and MariaDB services.
$base = getenv('BOOKSHELF_TEST_URL') ?: 'http://localhost:8000';
function getPage(string $path): array
{
    global $base;
    $body = file_get_contents($base . $path, false, stream_context_create(['http' => ['ignore_errors' => true]]));
    if ($body === false) {
        throw new RuntimeException("Request failed: $path");
    }
    return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
}
function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$statusTotals = [];
foreach (['', 'want_to_read', 'reading', 'finished', 'paused', 'dnf'] as $status) {
    $filter = $status === '' ? '' : '&status=' . $status;
    foreach ([12, 24, 48, 96] as $limit) {
        $first = getPage('/api/books.php?page=1&limit=' . $limit . $filter);
        check($first['page'] === 1 && $first['limit'] === $limit, "Wrong pagination metadata: $status/$limit");
        check(count($first['books']) <= $limit, "Too many books: $status/$limit");
        check($first['totalPages'] === (int)ceil($first['total'] / $limit), "Wrong count: $status/$limit");
        if ($limit === 24) $statusTotals[$status] = $first['total'];
        check(count($first['books']) === min($first['total'], $limit), "Wrong page length: $status/$limit");
        if ($status !== '') {
            check(count(array_filter($first['books'], fn($book) => $book['status'] !== $status)) === 0, "Wrong status: $status");
        }
        $last = getPage('/api/books.php?page=999999&limit=' . $limit . $filter);
        check($last['page'] === max(1, $first['totalPages']), "Out-of-range page was not clamped: $status/$limit");
        if ($first['totalPages'] > 1) {
            $second = getPage('/api/books.php?page=2&limit=' . $limit . $filter);
            check($second['total'] === $first['total'], "Count changed between pages: $status/$limit");
            check(!array_intersect(array_column($first['books'], 'id'), array_column($second['books'], 'id')), "Page overlap: $status/$limit");
        }
    }
}
check(array_sum(array_diff_key($statusTotals, ['' => true])) === $statusTotals[''], 'Status counts do not add up');

$empty = getPage('/api/books.php?q=' . rawurlencode('no-book-matches-this-unique-phrase-319745') . '&page=20');
check($empty['total'] === 0 && $empty['totalPages'] === 0 && $empty['page'] === 1 && $empty['books'] === [], 'Empty result metadata is wrong');
$summary = getPage('/api/books.php?summary=lists');
check(array_keys($summary['lists']) === ['want_to_read', 'reading', 'finished', 'paused', 'dnf'], 'Lists summary is incomplete');
foreach ($summary['lists'] as $previews) check(count($previews) <= 8, 'Too many list previews');

$firstBook = getPage('/api/books.php?limit=12')['books'][0] ?? null;
if ($firstBook !== null) {
    $search = getPage('/api/books.php?q=' . rawurlencode($firstBook['title']) . '&limit=12');
    check($search['total'] >= 1, 'Title search lost a saved book');
    check(in_array($firstBook['id'], array_column($search['books'], 'id'), true), 'Title search did not return the book');
    $genre = $firstBook['genres'][0]['id'] ?? null;
    if ($genre !== null) {
        $filtered = getPage('/api/books.php?genres=' . $genre . '&limit=96');
        check($filtered['total'] <= $statusTotals[''], 'Genre count exceeds library count');
        check(in_array($firstBook['id'], array_column($filtered['books'], 'id'), true), 'Genre filter lost the book');
    }
}

foreach (['author_asc', 'author_desc'] as $sort) {
    $books = getPage('/api/books.php?sort=' . $sort . '&limit=96')['books'];
    $keys = array_map(static function ($book) {
        $authors = array_column($book['authors'], 'name');
        sort($authors, SORT_STRING);
        return $authors[0] ?? null;
    }, $books);
    foreach (array_slice($keys, 1, null, true) as $index => $key) {
        $previous = $keys[$index - 1];
        if ($previous === null) check($key === null, 'Null authors should sort last');
        elseif ($key !== null) {
            $comparison = strcasecmp($previous, $key);
            check($sort === 'author_asc' ? $comparison <= 0 : $comparison >= 0, "Author sorting failed: $sort");
        }
    }
}

$popular = getPage('/api/popular-books.php?page=1&limit=24');
if (isset($popular['books'])) {
    check($popular['page'] === 1 && $popular['limit'] === 24, 'Popular page metadata is wrong');
    check(count($popular['books']) <= 24, 'Too many popular books');
    if ($popular['hasNext']) {
        $next = getPage('/api/popular-books.php?page=2&limit=24');
        check($next['page'] === 2, 'Popular page did not advance');
        check(!array_intersect(array_column($popular['books'], 'hardcover_id'), array_column($next['books'], 'hardcover_id')), 'Popular pages overlap');
    }
} elseif (($popular['error'] ?? '') !== 'Hardcover API is not configured.') {
    throw new RuntimeException('Popular endpoint failed');
}

echo "Pagination integration checks passed.\n";

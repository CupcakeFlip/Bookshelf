<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/books.php';

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// Keep this explicit list aligned with frontend/src/utils/bookFormatting.js.
const BOOK_GENRE_CATEGORIES = [
    'Paranormal' => [
        'Paranormal',
        'Paranormal Romance',
        'Paranormal Fantasy'
    ],
    'Dark' => [
        'Dark',
        'Dark Romance',
        'Dark Fantasy'
    ],
    'Urban' => [
        'Urban',
        'Urban Fantasy'
    ],
    'Historical' => [
        'Historical',
        'Historical Romance',
        'Historical Fiction',
        'History'
    ],
    'Fantasy' => [
        'Fantasy',
        'Fantasy Romance',
        'Fantasy romance',
        'Romantasy',
        'Epic Fantasy',
        'Urban Fantasy',
        'Dark Fantasy',
        'High Fantasy',
        'Paranormal Fantasy',
        'Science Fiction & Fantasy',
        'Science Fiction and Fantasy',
        'Fantasy & Science Fiction',
        'Fantasy and Science Fiction ',
        'Romance|science Fiction & Fantasy',
        'Magic',
        'dragons'
    ],
    'Romance' => [
        'Romance',
        'Fantasy Romance',
        'Fantasy romance',
        'Romantasy',
        'Dark Romance',
        'Contemporary Romance',
        'Paranormal Romance',
        'Romance|science Fiction & Fantasy',
        'Sports Romance'
    ],
    'Science Fiction' => [
        'Science Fiction & Fantasy',
        'Science Fiction and Fantasy',
        'Fantasy & Science Fiction',
        'Fantasy and Science Fiction ',
        'Romance|science Fiction & Fantasy'
    ],
    'Action' => [
        'Action',
        'Action & Adventure'
    ],
    'Adventure' => [
        'Adventure',
        'Action & Adventure'
    ],
];

$pdo = require __DIR__ . '/../settings/init.php';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    if (($_GET['summary'] ?? '') === 'lists') {
        $rows = $pdo->query('SELECT status, id, title, cover_url FROM (SELECT ub.status, b.id, b.title, b.cover_url, ROW_NUMBER() OVER (PARTITION BY ub.status ORDER BY ub.updated_at DESC, b.id) AS position FROM user_books ub JOIN books b ON b.id=ub.book_id) previews WHERE position <= 8 ORDER BY status, position')->fetchAll(PDO::FETCH_ASSOC);
        $lists = array_fill_keys(['want_to_read', 'reading', 'finished', 'paused', 'dnf'], []);
        foreach ($rows as $row) {
            $lists[$row['status']][] = ['id' => (int)$row['id'], 'title' => $row['title'], 'cover_url' => $row['cover_url']];
        }
        respond(['lists' => $lists]);
    }
    $page = filter_var($_GET['page'] ?? '1', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $limit = filter_var($_GET['limit'] ?? '24', FILTER_VALIDATE_INT);
    if ($page === false || !in_array($limit, [12, 24, 48, 96], true)) {
        respond(['error' => 'page or limit is invalid.'], 422);
    }
    $q = trim((string)($_GET['q'] ?? ''));
    $status = trim((string)($_GET['status'] ?? ''));
    $allowed = ['want_to_read', 'reading', 'finished', 'paused', 'dnf'];
    $sort = (string)($_GET['sort'] ?? 'recently_updated_desc');
    $sorts =
        [
            'recently_updated_desc' => 'ub.updated_at IS NULL, ub.updated_at DESC, b.id',
            'recently_updated_asc' => 'ub.updated_at IS NULL, ub.updated_at ASC, b.id',
            'title_asc' => 'b.title IS NULL, b.title ASC, b.id',
            'title_desc' => 'b.title IS NULL, b.title DESC, b.id',
            'release_date_desc' => 'b.release_date IS NULL, b.release_date DESC, b.id',
            'release_date_asc' => 'b.release_date IS NULL, b.release_date ASC, b.id',
            'author_asc' => '(SELECT MIN(a2.name) FROM book_authors ba2 JOIN authors a2 ON a2.id=ba2.author_id WHERE ba2.book_id=b.id) IS NULL, (SELECT MIN(a2.name) FROM book_authors ba2 JOIN authors a2 ON a2.id=ba2.author_id WHERE ba2.book_id=b.id) ASC, b.id',
            'author_desc' => '(SELECT MIN(a2.name) FROM book_authors ba2 JOIN authors a2 ON a2.id=ba2.author_id WHERE ba2.book_id=b.id) IS NULL, (SELECT MIN(a2.name) FROM book_authors ba2 JOIN authors a2 ON a2.id=ba2.author_id WHERE ba2.book_id=b.id) DESC, b.id'
        ];
    if ($status !== '' && !in_array($status, $allowed, true)) {
        respond(['error' => 'status is invalid.'], 422);
    }
    if (!array_key_exists($sort, $sorts)) {
        respond(['error' => 'sort is invalid.'], 422);
    }
    $genreIds = [];
    if (isset($_GET['genres']) && trim((string)$_GET['genres']) !== '') {
        foreach (
            explode(',', (string)$_GET['genres']) as
            $genreId
        ) {
            $genreId = filter_var(trim($genreId), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($genreId === false) {
                respond(['error' => 'genres must contain positive integer IDs.'], 422);
            }
            $genreIds[] = (int)$genreId;
        }
    }
    $categoryValues = [];
    if (isset($_GET['categories']) && trim((string)$_GET['categories']) !== '') {
        foreach (
            explode(
                ',',
                (string)$_GET['categories']
            ) as $category
        ) {
            $category = trim($category);
            if (!array_key_exists($category, BOOK_GENRE_CATEGORIES)) {
                respond(
                    ['error' => 'categories contains an unsupported category.'],
                    422
                );
            }
            $categoryValues[] = $category;
        }
    }
    $categoryValues = array_values(array_unique($categoryValues));
    $sql = 'SELECT b.id FROM books b JOIN user_books ub ON ub.book_id=b.id';
    $conditions = [];
    $params = [];
    if ($q !== '') {
        $conditions[] =
            '(b.title LIKE ? OR EXISTS (SELECT 1 FROM book_authors baq JOIN authors aq ON aq.id=baq.author_id WHERE baq.book_id=b.id AND aq.name LIKE ?))';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }
    if ($status !== '') {
        $conditions[] = 'ub.status=?';
        $params[] = $status;
    }
    if ($genreIds) {
        $marks = implode(',', array_fill(0, count($genreIds), '?'));
        $conditions[] = "EXISTS (SELECT 1 FROM book_genres bgf WHERE bgf.book_id=b.id AND bgf.genre_id IN ($marks))";
        foreach ($genreIds as $genreId) {
            $params[] = $genreId;
        }
    }
    foreach ($categoryValues as $category) {
        $names = BOOK_GENRE_CATEGORIES[$category];
        $marks = implode(',', array_fill(0, count($names), '?'));
        $conditions[] =
            "EXISTS (SELECT 1 FROM book_genres bgc JOIN genres gc ON gc.id=bgc.genre_id WHERE bgc.book_id=b.id AND gc.name IN ($marks))";
        foreach ($names as $name) {
            $params[] = $name;
        }
    }
    if ($conditions) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }
    $countSql = preg_replace('/^SELECT b.id/', 'SELECT COUNT(*)', $sql, 1);
    $count = $pdo->prepare($countSql);
    $count->execute($params);
    $total = (int)$count->fetchColumn();
    $totalPages = (int)ceil($total / $limit);
    $page = min($page, max(1, $totalPages));
    $sql .= ' ORDER BY ' . $sorts[$sort] . ' LIMIT ? OFFSET ?';
    $s = $pdo->prepare($sql);
    foreach ($params as $index => $value) {
        $s->bindValue($index + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $s->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
    $s->bindValue(count($params) + 2, ($page - 1) * $limit, PDO::PARAM_INT);
    $s->execute();
    respond(['books' => savedBooks($pdo, array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN))), 'page' => $page, 'limit' => $limit, 'total' => $total, 'totalPages' => $totalPages]);
}
if ($method === 'DELETE') {
    $id = filter_var($_GET['hardcover_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        respond(['error' => 'hardcover_id must be a positive integer.'], 400);
    }
    $bookId = sessionBookId($pdo, $id);
    if ($bookId === null) {
        respond(['error' => 'Book not found.'], 404);
    }
    $pdo->beginTransaction();
    try {
        transitionSession($pdo, $bookId, 'want_to_read');
        $pdo
            ->prepare('DELETE FROM user_books WHERE book_id=?')->execute([$bookId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        respond(['error' => 'The book could not be removed from the library.'], 500);
    }
    respond(['status' => null]);
}
if ($method !== 'POST') {
    respond(['error' => 'Method not allowed.'], 405);
}
$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload) || !array_key_exists('hardcover_id', $payload)) {
    respond(
        ['error' => 'A JSON body with hardcover_id is required.'],
        400
    );
}
$id = filter_var($payload['hardcover_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false) {
    respond(['error' => 'hardcover_id must be a positive integer.'], 400);
}
$status = $payload['status'] ?? 'want_to_read';
if (!is_string($status) || !in_array($status, ['want_to_read', 'reading', 'finished', 'paused', 'dnf'], true)) {
    respond(
        ['error' => 'status is invalid.'],
        422
    );
}
$details = is_array($payload['session'] ?? null) ? $payload['session'] : [];
$existing = $pdo->prepare('SELECT id FROM books WHERE external_api_id=?');
$existing->execute([(string)$id]);
$bookId = $existing->fetchColumn();
if ($bookId !== false) {
    $bookId = (int)$bookId;
    $pdo->beginTransaction();
    try {
        $pdo
            ->prepare(
                'INSERT INTO user_books (book_id,status) VALUES (?,?) ON DUPLICATE KEY UPDATE status=VALUES(status)'
            )
            ->execute([$bookId, $status]);
        transitionSession($pdo, $bookId, $status, $details);
        $pdo->commit();
    } catch (InvalidArgumentException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        respond(['error' => $e->getMessage()], 422);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        respond(['error' => 'The book status could not be updated.'], 500);
    }
    respond(savedBook($pdo, $bookId));
}
$token = trim((string)getenv('HARDCOVER_API_TOKEN'));
if ($token === '') {
    respond(['error' => 'The Hardcover API is not configured.'], 503);
}
try {
    respond(saveBook($pdo, fetchHardcoverBook($id, $token), $status, $details), 201);
} catch (RuntimeException $e) {
    respond(['error' => $e->getMessage()], $e->getCode() >= 400 ? $e->getCode() : 500);
} catch (Throwable $e) {
    respond(['error' => 'The book could not be saved.'], 500);
}

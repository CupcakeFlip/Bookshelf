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
        'Historical Fiction'
    ],
    'Fantasy' => [
        'Fantasy',
        'Fantasy Romance',
        'Romantasy',
        'Epic Fantasy',
        'Urban Fantasy',
        'Dark Fantasy',
        'High Fantasy',
        'Paranormal Fantasy',
        'Science Fiction & Fantasy',
        'Romance|science Fiction & Fantasy'
    ],
    'Romance' => [
        'Romance',
        'Fantasy Romance',
        'Romantasy',
        'Dark Romance',
        'Contemporary Romance',
        'Paranormal Romance',
        'Romance|science Fiction & Fantasy',
        'Sports Romance'
    ],
    'Science Fiction' => [
        'Science Fiction & Fantasy',
        'Romance|science Fiction & Fantasy'
    ],
];

$pdo = require __DIR__ . '/../settings/init.php';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
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
            'author_asc' => '(SELECT MIN(a2.name) FROM book_authors ba2 JOIN authors a2 ON a2.id=ba2.author_id) IS NULL, (SELECT MIN(a2.name) FROM book_authors ba2 JOIN authors a2 ON a2.id=ba2.author_id) ASC, b.id',
            'author_desc' => '(SELECT MIN(a2.name) FROM book_authors ba2 JOIN authors a2 ON a2.id=ba2.author_id) IS NULL, (SELECT MIN(a2.name) FROM book_authors ba2 JOIN authors a2 ON a2.id=ba2.author_id) DESC, b.id'
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
    $sql .= ' ORDER BY ' . $sorts[$sort];
    $s = $pdo->prepare($sql);
    $s->execute($params);
    respond(savedBooks($pdo, array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN))));
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

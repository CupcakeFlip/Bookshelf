<?php

declare(strict_types=1);

/** Owned-format API. Formats are independent from the user_books reading row. */
const OWNED_FORMATS = ['physical', 'ebook', 'audiobook'];

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Reads book_id from the query string or, for write requests, from JSON.
 * The decoded body is retained because the POST handler needs it again.
 * 
 * @return int Existing local book identifier.
 */
function bookId(): int
{
    $value = $_GET['book_id'] ?? null;
    if ($value === null && in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST', 'PUT'], true)) {
        $payload = json_decode((string)file_get_contents('php://input'), true);
        $value = is_array($payload) ? ($payload['book_id'] ?? null) : null;
        $GLOBALS['format_payload'] = $payload;
    }
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        respond(['error' => 'book_id must be a positive integer.'], 400);
    }
    return (int)$id;
}

/** Returns the canonical, sorted format set persisted for one local book. */
function formatsFor(PDO $pdo, int $bookId): array
{
    $stmt = $pdo->prepare('SELECT format FROM user_book_formats WHERE book_id = ? ORDER BY format');
    $stmt->execute([$bookId]);
    return array_column($stmt->fetchAll(), 'format');
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST', 'DELETE'], true)) {
    respond(['error' => 'Method not allowed.'], 405);
}
$pdo = require __DIR__ . '/../settings/init.php';
$id = bookId();
$stmt = $pdo->prepare('SELECT id FROM books WHERE id = ?');
$stmt->execute([$id]);
if (!$stmt->fetchColumn()) {
    respond(['error' => 'Book not found.'], 404);
}

if ($method === 'GET') {
    respond(['book_id' => $id, 'formats' => formatsFor($pdo, $id)]);
}

if ($method === 'DELETE') {
    // Omitting format means clear the complete set; supplying it removes one
    // value while the transaction keeps the replacement atomic.
    $format = $_GET['format'] ?? null;
    if ($format !== null && !in_array($format, OWNED_FORMATS, true)) {
        respond(['error' => 'format is invalid.'], 422);
    }
    $pdo->beginTransaction();
    try {
        if ($format === null) {
            $pdo->prepare('DELETE FROM user_book_formats WHERE book_id = ?')->execute([$id]);
        } else {
            $pdo->prepare('DELETE FROM user_book_formats WHERE book_id = ? AND format = ?')->execute([$id, $format]);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        respond(['error' => 'Owned formats could not be updated.'], 500);
    }
    respond(['book_id' => $id, 'formats' => formatsFor($pdo, $id)]);
}

$payload = $GLOBALS['format_payload'] ?? null;
if (!is_array($payload) || !array_key_exists('formats', $payload) || !is_array($payload['formats'])) {
    respond(
        ['error' => 'formats must be an array.'],
        400
    );
}
$formats = array_values(array_unique($payload['formats']));
foreach ($formats as $format) {
    if (!is_string($format) || !in_array($format, OWNED_FORMATS, true)) {
        respond(['error' => 'format is invalid.'], 422);
    }
}
sort($formats);
$current = formatsFor($pdo, $id);
// A sorted set comparison makes repeated saves a no-op and avoids needless
// DELETE/INSERT work when the user submits the existing selection.
if ($current === $formats) {
    respond(['book_id' => $id, 'formats' => $current]);
}
$pdo->beginTransaction();
try {
    $pdo->prepare('DELETE FROM user_book_formats WHERE book_id = ?')->execute([$id]);
    $stmt = $pdo->prepare('INSERT INTO user_book_formats (book_id, format) VALUES (?, ?)');
    foreach ($formats as $format) {
        $stmt->execute([$id, $format]);
    }
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    respond(['error' => 'Owned formats could not be updated.'], 500);
}
respond(['book_id' => $id, 'formats' => formatsFor($pdo, $id)]);

<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/reading_sessions.php';

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/** Decodes a write request and rejects scalar, empty, or malformed JSON. */
function requestPayload(): array
{
    $payload = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        respond(['error' => 'A JSON object is required.'], 400);
    }
    return $payload;
}

/** Identifies a database uniqueness conflict for the one-active-session rule. */
function conflict(Throwable $exception): bool
{
    return $exception instanceof PDOException && (string)$exception->getCode() === '23000';
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'], true)) {
    respond(
        ['error' => 'Method not allowed.'],
        405
    );
}
$pdo = require __DIR__ . '/../settings/init.php';

$payload = in_array($method, ['POST', 'PATCH', 'PUT'], true) ? requestPayload() : [];
$sessionFields =
    [
        'status',
        'start_date',
        'start_date_precision',
        'finish_date',
        'finish_date_precision',
        'dnf_progress_type',
        'dnf_progress_value',
        'formats'
    ];
$sessionPayload = array_intersect_key($payload, array_flip($sessionFields));
// Only whitelisted fields reach the session validator and SQL statements;
// identifiers remain separate so a client cannot overwrite another row.
$hardcoverId = $_GET['hardcover_id'] ?? ($payload['hardcover_id'] ?? null);
$bookId = sessionBookId($pdo, $hardcoverId);
if ($bookId === null && in_array($method, ['PATCH', 'PUT', 'DELETE'], true)) {
    $lookupId =
        filter_var($_GET['id'] ?? ($payload['id'] ?? null), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($lookupId !== false) {
        $lookup = $pdo->prepare('SELECT book_id FROM reading_sessions WHERE id = ?');
        $lookup->execute([(int)$lookupId]);
        $bookId = ($value = $lookup->fetchColumn()) === false ? null : (int)$value;
    }
}
if ($bookId === null) {
    respond(['error' => 'Book not found.'], 404);
}

if ($method === 'GET') {
    // Session IDs are fetched first, then expanded through sessionData() so
    // the response uses one consistent representation for every endpoint.
    $stmt =
        $pdo->prepare(
            'SELECT id FROM reading_sessions WHERE book_id = ? ORDER BY COALESCE(start_date, finish_date, created_at) DESC, id DESC'
        );
    $stmt->execute([$bookId]);
    $sessions = [];
    foreach ($stmt->fetchAll() as $row) {
        $sessions[] = sessionData($pdo, (int)$row['id']);
    }
    respond(['hardcover_id' => (string)$hardcoverId, 'sessions' => $sessions]);
}

if ($method === 'POST') {
    try {
        $data = validateSessionPayload($sessionPayload);
    } catch (InvalidArgumentException $exception) {
        respond(['error' => $exception->getMessage()], 422);
    }
    if (!isset($data['status'])) {
        respond(['error' => 'status is required.'], 400);
    }
    $pdo->beginTransaction();
    try {
        // Lock the book before checking for an active session. This serializes
        // competing requests and preserves the single-active-session rule.
        lockBook($pdo, $bookId);
        if (in_array($data['status'], ['reading', 'paused'], true) && activeSession($pdo, $bookId)) {
            $pdo->rollBack();
            respond(['error' => 'The book already has an active reading session.'], 409);
        }
        if ($data['status'] === 'reading' && !array_key_exists('start_date', $data)) {
            $data['start_date'] = sessionToday();
            $data['start_date_precision'] = 'day';
        }
        $data = normalizeSessionDates($data);
        $stmt =
            $pdo->prepare(
                'INSERT INTO reading_sessions (book_id, status, start_date, start_date_precision, finish_date, finish_date_precision, dnf_progress_type, dnf_progress_value) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
        $stmt->execute(
            [
                $bookId,
                $data['status'],
                $data['start_date'] ?? null,
                $data['start_date_precision'] ?? 'unknown',
                $data['finish_date'] ?? null,
                $data['finish_date_precision'] ?? 'unknown',
                $data['status'] === 'dnf' ? ($data['dnf_progress_type'] ?? null) : null,
                $data['status'] === 'dnf' ? ($data['dnf_progress_value'] ?? null) : null
            ]
        );
        $id = (int)$pdo->lastInsertId();
        replaceSessionFormats($pdo, $id, $data['formats'] ?? []);
        reconcileBookStatus($pdo, $bookId, $data['status']);
        $pdo->commit();
        respond(sessionData($pdo, $id), 201);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (conflict($exception)) {
            respond(['error' => 'The book already has an active reading session.'], 409);
        }
        respond(['error' => 'Reading session could not be created.'], 500);
    }
}

$sessionId =
    filter_var($_GET['id'] ?? ($payload['id'] ?? null), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($sessionId === false) {
    respond(['error' => 'A positive session id is required.'], 400);
}
$session = sessionData($pdo, (int)$sessionId);
if (!$session || (int)$session['book_id'] !== $bookId) {
    respond(['error' => 'Reading session not found.'], 404);
}

if ($method === 'DELETE') {
    $pdo->beginTransaction();
    try {
        lockBook($pdo, $bookId);
        $current = sessionData($pdo, (int)$sessionId);
        if (!$current) {
            throw new RuntimeException('Reading session not found.');
        }
        $pdo->prepare('DELETE FROM reading_sessions WHERE id = ?')->execute([(int)$sessionId]);
        if (in_array($current['status'], ['reading', 'paused'], true)) {
            reconcileBookStatus($pdo, $bookId, 'want_to_read');
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        respond(['error' => 'Reading session could not be deleted.'], 500);
    }
    respond(['deleted' => true]);
}

try {
    $data = validateSessionPayload($sessionPayload, true);
} catch (InvalidArgumentException $exception) {
    respond(['error' => $exception->getMessage()], 422);
}
$merged = array_merge($session, $data);
foreach (['id', 'book_id', 'hardcover_id', 'active_book_id', 'created_at', 'updated_at'] as $field) {
    unset($merged[$field]);
}
try {
    validateSessionPayload($merged);
} catch (InvalidArgumentException $exception) {
    respond(['error' => $exception->getMessage()], 422);
}
$pdo->beginTransaction();
try {
    lockBook($pdo, $bookId);
    $current = sessionData($pdo, (int)$sessionId);
    if (!$current) {
        throw new RuntimeException('Reading session not found.');
    }
    $merged = normalizeSessionDates($merged);
    $newStatus = $merged['status'];
    if (in_array($newStatus, ['reading', 'paused'], true)) {
        $other = activeSession($pdo, $bookId);
        if ($other && (int)$other['id'] !== (int)$sessionId) {
            $pdo->rollBack();
            respond(['error' => 'The book already has an active reading session.'], 409);
        }
    }
    foreach (['start_date', 'start_date_precision', 'finish_date', 'finish_date_precision'] as $field) {
        if (array_key_exists($field, $data)) {
            $data[$field] = $merged[$field];
        }
    }
    if (array_key_exists('status', $data) && $data['status'] !== 'dnf') {
        $data['dnf_progress_type'] = null;
        $data['dnf_progress_value'] = null;
    }
    $fields = [];
    $values = [];
    foreach (
        [
            'status',
            'start_date',
            'start_date_precision',
            'finish_date',
            'finish_date_precision',
            'dnf_progress_type',
            'dnf_progress_value'
        ] as $field
    ) {
        if (array_key_exists($field, $data)) {
            $fields[] = "$field = ?";
            $values[] = $data[$field];
        }
    }
    if ($fields) {
        $values[] = (int)$sessionId;
        $pdo
            ->prepare('UPDATE reading_sessions SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($values);
    }
    if (array_key_exists('formats', $data)) {
        replaceSessionFormats($pdo, (int)$sessionId, $data['formats']);
    }
    if (
        in_array($current['status'], ['reading', 'paused'], true) ||
        in_array($newStatus, ['reading', 'paused'], true)
    ) {
        reconcileBookStatus($pdo, $bookId, $newStatus);
    }
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (conflict($exception)) {
        respond(['error' => 'The book already has an active reading session.'], 409);
    }
    respond(['error' => 'Reading session could not be updated.'], 500);
}
respond(sessionData($pdo, (int)$sessionId));

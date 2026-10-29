<?php
/** Made with AI */

declare(strict_types=1);

/**
 * Lists API: GET reads lists, POST creates, PUT/PATCH updates, and DELETE removes.
 * Responses are JSON with validation errors reported as 400/422 and missing
 * records as 404.
 */

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
 * Reads and validates a positive integer from a request value.
 *
 * @param mixed $value Raw query or JSON value.
 * @param string $field Field name used in validation errors.
 * @return int Validated positive integer.
 */
function positiveId(mixed $value, string $field): int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        respond(['error' => $field . ' must be a positive integer.'], 400);
    }
    return (int)$id;
}

/**
 * Reads a JSON request body.
 *
 * @return array Decoded request object.
 */
function requestBody(): array
{
    $body = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($body)) {
        respond(['error' => 'A valid JSON body is required.'], 400);
    }
    return $body;
}

/**
 * Loads one list and its books through the book_lists relation.
 *
 * @param PDO $pdo Active database connection.
 * @param int $listId Local list identifier.
 * @return array List data with normalized title and book_count fields.
 */
function savedList(PDO $pdo, int $listId): array
{
    $stmt = $pdo->prepare(
        'SELECT
            id,
            name,
            description,
            created_at,
            updated_at
        FROM lists
        WHERE id = ?'
    );
    $stmt->execute([$listId]);
    $list = $stmt->fetch();
    if (!$list) {
        respond(['error' => 'The list was not found.'], 404);
    }

    $stmt = $pdo->prepare(
        'SELECT
            b.id,
            b.external_api_id AS hardcover_id,
            b.title,
            b.cover_url
        FROM book_lists AS bl
        JOIN books AS b
            ON b.id = bl.book_id
        WHERE bl.lists_id = ?
        ORDER BY b.title, b.id'
    );
    $stmt->execute([$listId]);
    $list['id'] = (int)$list['id'];
    $list['title'] = $list['name'];
    $list['books'] = $stmt->fetchAll();
    $list['book_count'] = count($list['books']);
    return $list;
}

/**
 * Validates book IDs before a list transaction begins.
 *
 * @param PDO $pdo Active database connection.
 * @param array $bookIds Candidate local book IDs.
 * @return array Unique, validated local book IDs.
 */
function validatedBookIds(PDO $pdo, array $bookIds): array
{
    $bookIds = array_values(
        array_unique(
            array_map(
                static fn(mixed $bookId): int => positiveId($bookId, 'book_id'),
                $bookIds
            )
        )
    );

    if ($bookIds !== []) {
        $placeholders = implode(',', array_fill(0, count($bookIds), '?'));
        $stmt = $pdo->prepare("SELECT id FROM books WHERE id IN ($placeholders)");
        $stmt->execute($bookIds);
        $existingIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        if (count($existingIds) !== count($bookIds)) {
            respond(['error' => 'One or more books could not be found.'], 404);
        }
    }
    return $bookIds;
}

/**
 * Replaces the books in a list with already validated IDs.
 *
 * The caller owns the surrounding transaction so list metadata and relation
 * rows are committed or rolled back together.
 *
 * @param PDO $pdo Active database connection.
 * @param int $listId Local list identifier.
 * @param array $bookIds Validated local book IDs.
 * @return void
 */
function replaceListBooks(PDO $pdo, int $listId, array $bookIds): void
{
    $pdo->prepare('DELETE FROM book_lists WHERE lists_id = ?')->execute([$listId]);
    if ($bookIds === []) {
        return;
    }

    $stmt = $pdo->prepare('INSERT INTO book_lists (book_id, lists_id) VALUES (?, ?)');
    foreach ($bookIds as $bookId) {
        $stmt->execute([$bookId, $listId]);
    }
}

$pdo = require __DIR__ . '/../settings/init.php';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestedId = $_GET['id'] ?? null;

if ($method === 'GET') {
    if ($requestedId !== null) {
        respond(savedList($pdo, positiveId($requestedId, 'id')));
    }

    $lists = [];
    foreach ($pdo->query('SELECT id FROM lists ORDER BY name, id')->fetchAll(PDO::FETCH_COLUMN) as $listId) {
        $lists[] = savedList($pdo, (int)$listId);
    }
    respond($lists);
}

if ($method === 'POST') {
    $payload = requestBody();
    $name = trim((string)($payload['name'] ?? $payload['title'] ?? ''));
    if ($name === '' || strlen($name) > 255) {
        respond(['error' => 'name is required and must be at most 255 characters.'], 422);
    }
    $description = $payload['description'] ?? null;
    if ($description !== null && (!is_string($description) || strlen($description) > 255)) {
        respond(['error' => 'description must be at most 255 characters.'], 422);
    }
    $bookIds = $payload['book_ids'] ?? [];
    if (!is_array($bookIds)) {
        respond(['error' => 'book_ids must be an array.'], 422);
    }
    $bookIds = validatedBookIds($pdo, $bookIds);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO lists (name, description) VALUES (?, ?)');
        $stmt->execute([$name, $description]);
        $listId = (int)$pdo->lastInsertId();
        replaceListBooks($pdo, $listId, $bookIds);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    respond(savedList($pdo, $listId), 201);
}

$listId = positiveId($requestedId ?? ($_GET['list_id'] ?? null), 'id');
if ($method === 'PUT' || $method === 'PATCH') {
    $payload = requestBody();
    $current = savedList($pdo, $listId);
    $name = array_key_exists('name', $payload) || array_key_exists('title', $payload)
        ? trim((string)($payload['name'] ?? $payload['title']))
        : $current['name'];
    $description = array_key_exists('description', $payload) ? $payload['description'] : $current['description'];
    if ($name === '' || strlen($name) > 255) {
        respond(['error' => 'name must be at most 255 characters.'], 422);
    }
    if ($description !== null && (!is_string($description) || strlen($description) > 255)) {
        respond(['error' => 'description must be at most 255 characters.'], 422);
    }
    $bookIds = null;
    if (array_key_exists('book_ids', $payload)) {
        if (!is_array($payload['book_ids'])) {
            respond(['error' => 'book_ids must be an array.'], 422);
        }
        $bookIds = validatedBookIds($pdo, $payload['book_ids']);
    }

    $pdo->beginTransaction();
    try {
        $pdo
            ->prepare('UPDATE lists SET name = ?, description = ? WHERE id = ?')->execute(
                [$name, $description, $listId]
            );
        if ($bookIds !== null) {
            replaceListBooks($pdo, $listId, $bookIds);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    respond(savedList($pdo, $listId));
}

if ($method === 'DELETE') {
    $stmt = $pdo->prepare('DELETE FROM lists WHERE id = ?');
    $stmt->execute([$listId]);
    if ($stmt->rowCount() === 0) {
        respond(['error' => 'The list was not found.'], 404);
    }
    respond(['message' => 'The list was deleted.']);
}

respond(['error' => 'Method not allowed.'], 405);

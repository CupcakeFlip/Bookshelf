<?php
/** Made with AI */

/**
 * Handles reading sessions and reading history.
 *
 * Validates reading session data, manages reading status changes,
 * and stores information about reading dates, formats, and progress.
 * Also keeps book statuses synchronized with their reading sessions.
 *
 * Used by the books and reading sessions API endpoints.
 */

declare(strict_types=1);

const SESSION_STATUSES = ['reading', 'paused', 'finished', 'dnf', 'abandoned'];
const SESSION_FORMATS = ['physical', 'ebook', 'audiobook'];
const DATE_PRECISIONS = ['day', 'month', 'year', 'unknown'];

/**
 * Checks a full wire-format date before precision is applied.
 * @param mixed $value Candidate YYYY-MM-DD value.
 * @return bool Whether the date exists in the calendar.
 */
function validSessionDate(mixed $value): bool
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && DateTimeImmutable::getLastErrors() === false;
}

/**
 * Resolves an external Hardcover ID to a canonical local book.
 * @param PDO $pdo Active connection.
 * @param mixed $hardcoverId Candidate external ID.
 * @return int|null Local ID, or null for invalid or missing books.
 */
function sessionBookId(PDO $pdo, mixed $hardcoverId): ?int
{
    if (!is_scalar($hardcoverId) || !preg_match('/^[1-9][0-9]*$/', (string)$hardcoverId)) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT id FROM books WHERE external_api_id = ?');
    $stmt->execute([(string)$hardcoverId]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int)$id;
}

/**
 * Loads the formats recorded for a historical reading attempt.
 * @param PDO $pdo Active connection.
 * @param int $sessionId Local session ID.
 * @return string[] Sorted format names.
 */
function sessionFormats(PDO $pdo, int $sessionId): array
{
    $stmt = $pdo->prepare('SELECT format FROM reading_session_formats WHERE reading_session_id = ? ORDER BY format');
    $stmt->execute([$sessionId]);
    return array_column($stmt->fetchAll(), 'format');
}

/**
 * Expands one session for JSON responses.
 * @param PDO $pdo Active connection.
 * @param int $sessionId Local session ID.
 * @return array|null Session with formats and Hardcover ID, or null.
 */
function sessionData(PDO $pdo, int $sessionId): ?array
{
    $stmt =
        $pdo->prepare(
            'SELECT
                rs.*,
                b.external_api_id AS hardcover_id
            FROM reading_sessions AS rs
            JOIN books AS b
                ON b.id = rs.book_id
            WHERE rs.id = ?'
        );
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();
    if (!$session) {
        return null;
    }
    $session['id'] = (int)$session['id'];
    $session['book_id'] = (int)$session['book_id'];
    $session['formats'] = sessionFormats($pdo, $sessionId);
    return $session;
}

/**
 * Validates API fields and cross-field date, format, and DNF rules.
 * @param array $payload Session fields only, without response identifiers.
 * @param bool $partial Whether fields may be omitted before merging with an existing session.
 * @return array Validated fields; dates are normalized separately.
 * @throws InvalidArgumentException If a field or combination is invalid.
 */
function validateSessionPayload(array $payload, bool $partial = false): array
{
    $allowed =
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
    foreach ($payload as $key => $_) {
        if (!in_array($key, $allowed, true)) {
            throw new InvalidArgumentException("Unknown session field: $key");
        }
    }
    foreach (['status', 'start_date_precision', 'finish_date_precision', 'dnf_progress_type'] as $key) {
        if (array_key_exists($key, $payload) && $payload[$key] !== null && !is_string($payload[$key])) {
            throw new InvalidArgumentException("$key is invalid.");
        }
    }
    if (isset($payload['status']) && !in_array($payload['status'], SESSION_STATUSES, true)) {
        throw new InvalidArgumentException('status is invalid.');
    }
    if (
        isset($payload['dnf_progress_type']) &&
        !in_array($payload['dnf_progress_type'], ['percentage', 'page', 'time'], true)) {
        throw new InvalidArgumentException('dnf_progress_type is invalid.');
    }
    foreach (['start_date_precision', 'finish_date_precision'] as $key) {
        if (isset($payload[$key]) && !in_array($payload[$key], DATE_PRECISIONS, true)) {
            throw new InvalidArgumentException("$key is invalid.");
        }
    }
    foreach (['start_date', 'finish_date'] as $key) {
        if (array_key_exists($key, $payload) && $payload[$key] !== null && !validSessionDate($payload[$key])) {
            throw new InvalidArgumentException("$key must be a valid YYYY-MM-DD date or null.");
        }
    }
    foreach ([['start_date', 'start_date_precision'], ['finish_date', 'finish_date_precision']] as [$date, $precision])
    {
        if (!$partial || array_key_exists($date, $payload) || array_key_exists($precision, $payload)) {
            $dateValue = $payload[$date] ?? null;
            $precisionValue = $payload[$precision] ?? 'unknown';
            if (($dateValue === null) !== ($precisionValue === 'unknown')) {
                throw new InvalidArgumentException("$date and $precision must agree.");
            }
        }
    }
    if (array_key_exists('formats', $payload)) {
        if (!is_array($payload['formats'])) {
            throw new InvalidArgumentException('formats must be an array.');
        }
        foreach ($payload['formats'] as $format) {
            if (!is_string($format) || !in_array($format, SESSION_FORMATS, true)) {
                throw new InvalidArgumentException('format is invalid.');
            }
        }
    }
    if (
        !$partial &&
        (array_key_exists('dnf_progress_type', $payload) !== array_key_exists('dnf_progress_value', $payload))
    ) {
        throw new InvalidArgumentException('DNF progress type and value must be supplied together.');
    }
    if (
        !$partial &&
        array_key_exists('dnf_progress_type', $payload) &&
        (($payload['dnf_progress_type'] === null) !== ($payload['dnf_progress_value'] === null))
    ) {
        throw new InvalidArgumentException('DNF progress type and value must be both null or both present.');
    }
    if (
        ($payload['status'] ?? null) !== null &&
        ($payload['status'] ?? null) !== 'dnf' &&
        (($payload['dnf_progress_type'] ?? null) !== null || ($payload['dnf_progress_value'] ?? null) !== null)
    ) {
        throw new InvalidArgumentException('DNF progress is only valid for DNF sessions.');
    }
    if (
        array_key_exists('dnf_progress_value', $payload) &&
        $payload['dnf_progress_value'] !== null &&
        (!is_int($payload['dnf_progress_value']) &&
            !is_float($payload['dnf_progress_value']) ||
            !is_finite((float)$payload['dnf_progress_value']) ||
            (float)$payload['dnf_progress_value'] < 0)
    ) {
        throw new InvalidArgumentException('dnf_progress_value must be a non-negative number.');
    }
    if (($payload['dnf_progress_type'] ?? null) === 'percentage' && (float)$payload['dnf_progress_value'] > 100) {
        throw new InvalidArgumentException('Percentage must be between 0 and 100.');
    }
    if (
        ($payload['dnf_progress_type'] ?? null) === 'page' &&
        ((float)$payload['dnf_progress_value'] < 0 ||
            floor((float)$payload['dnf_progress_value']) !== (float)$payload['dnf_progress_value'])
    ) {
        throw new InvalidArgumentException('Page progress must be a non-negative integer.');
    }
    if (
        ($payload['dnf_progress_type'] ?? null) === 'time' &&
        isset($payload['formats']) &&
        $payload['formats'] !== [] &&
        !in_array('audiobook', $payload['formats'], true)
    ) {
        throw new InvalidArgumentException('Time progress requires an audiobook format.');
    }
    if (
        ($payload['dnf_progress_type'] ?? null) === 'page' &&
        isset($payload['formats']) &&
        !array_intersect(['physical', 'ebook'], $payload['formats'])
    ) {
        throw new InvalidArgumentException('Page progress requires a physical or ebook format.');
    }
    if (
        isset($payload['start_date'], $payload['finish_date']) &&
        $payload['start_date'] !== null &&
        $payload['finish_date'] !== null &&
        ($payload['start_date_precision'] ?? 'unknown') === 'day' &&
        ($payload['finish_date_precision'] ?? 'unknown') === 'day' &&
        $payload['finish_date'] < $payload['start_date']
    ) {
        throw new InvalidArgumentException('finish_date cannot be before start_date.');
    }
    return $payload;
}

/**
 * Stores month/year precision as the first day of that period.
 * @param array $data Validated session fields.
 * @return array Fields with normalized dates; unknown dates become null.
 */
function normalizeSessionDates(array $data): array
{
    foreach ([['start_date', 'start_date_precision'], ['finish_date', 'finish_date_precision']] as [$date, $precision])
    {
        if (($data[$precision] ?? 'unknown') === 'unknown') {
            $data[$date] = null;
        } elseif (($data[$precision] ?? null) === 'month') {
            $data[$date] = substr((string)$data[$date], 0, 7) . '-01';
        } elseif (($data[$precision] ?? null) === 'year') {
            $data[$date] = substr((string)$data[$date], 0, 4) . '-01-01';
        }
    }
    return $data;
}

/**
 * Serializes related status/session changes using the canonical book row.
 * @param PDO $pdo Connection inside a caller-owned transaction.
 * @param int $bookId Local book ID.
 * @return void
 * @throws RuntimeException If the book no longer exists.
 */
function lockBook(PDO $pdo, int $bookId): void
{
    $stmt = $pdo->prepare('SELECT id FROM books WHERE id = ? FOR UPDATE');
    $stmt->execute([$bookId]);
    if (!$stmt->fetchColumn()) {
        throw new RuntimeException('Book not found.');
    }
}

/**
 * Gives an active session priority when updating the current library status.
 * @param PDO $pdo Connection inside a transaction with the book locked.
 * @param int $bookId Local book ID.
 * @param string|null $preferred Fallback status when no active session exists.
 * @return void
 */
function reconcileBookStatus(PDO $pdo, int $bookId, ?string $preferred = null): void
{
    $active = activeSession($pdo, $bookId);
    $status = $active['status'] ?? ($preferred === 'abandoned' ? 'want_to_read' : $preferred);
    if ($status === null) {
        return;
    }
    $exists = $pdo->prepare('SELECT id FROM user_books WHERE book_id = ? FOR UPDATE');
    $exists->execute([$bookId]);
    if ($exists->fetchColumn()) {
        $pdo
            ->prepare('UPDATE user_books SET status = ? WHERE book_id = ?')->execute([$status, $bookId]);
    } elseif (in_array($status, ['reading', 'paused'], true)) {
        $pdo
            ->prepare('INSERT INTO user_books (book_id, status) VALUES (?, ?)')->execute([$bookId, $status]);
    }
}

/**
 * Replaces session formats as a set without changing owned formats.
 * @param PDO $pdo Connection inside a caller-owned transaction.
 * @param int $sessionId Local session ID.
 * @param string[] $formats Validated format values.
 * @return void
 */
function replaceSessionFormats(PDO $pdo, int $sessionId, array $formats): void
{
    $pdo->prepare('DELETE FROM reading_session_formats WHERE reading_session_id = ?')->execute([$sessionId]);
    $stmt = $pdo->prepare('INSERT INTO reading_session_formats (reading_session_id, format) VALUES (?, ?)');
    foreach (array_unique($formats) as $format) {
        $stmt->execute([$sessionId, $format]);
    }
}

/**
 * Finds the newest reading or paused attempt.
 * @param PDO $pdo Active connection.
 * @param int $bookId Local book ID.
 * @return array|null Expanded active session, or null.
 */
function activeSession(PDO $pdo, int $bookId): ?array
{
    $stmt =
        $pdo->prepare(
            "SELECT id
            FROM reading_sessions
            WHERE book_id = ?
                AND status IN ('reading', 'paused')
            ORDER BY id DESC
            LIMIT 1"
        );
    $stmt->execute([$bookId]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : sessionData($pdo, (int)$id);
}

/**
 * Uses the PHP installation timezone for automatic start dates.
 * @return string Today as YYYY-MM-DD.
 */
function sessionToday(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get())))->format('Y-m-d');
}

/**
 * Applies the session changes associated with a library status selection.
 * Reading starts or resumes an attempt; want_to_read abandons an active attempt.
 * @param PDO $pdo Connection inside a caller-owned transaction.
 * @param int $bookId Local book ID.
 * @param string $status Validated library status.
 * @param array $details Optional Finished or DNF session fields.
 * @return void
 * @throws InvalidArgumentException If the supplied session details are invalid.
 */
function transitionSession(PDO $pdo, int $bookId, string $status, array $details = []): void
{
    lockBook($pdo, $bookId);
    $active = activeSession($pdo, $bookId);
    if ($status === 'reading') {
        if ($active && $active['status'] === 'paused') {
            $pdo
                ->prepare("UPDATE reading_sessions SET status = 'reading' WHERE id = ?")->execute([$active['id']]);
        } elseif (!$active) {
            $stmt =
                $pdo->prepare(
                    "INSERT INTO reading_sessions (
                        book_id,
                        status,
                        start_date,
                        start_date_precision
                    )
                    VALUES (?, 'reading', ?, 'day')"
                );
            $stmt->execute([$bookId, sessionToday()]);
        }
        return;
    }
    if ($status === 'paused') {
        if ($active) {
            $pdo
                ->prepare("UPDATE reading_sessions SET status = 'paused' WHERE id = ?")->execute([$active['id']]);
        }
        return;
    }
    if ($status === 'want_to_read') {
        if ($active) {
            $pdo
                ->prepare("UPDATE reading_sessions SET status = 'abandoned' WHERE id = ?")->execute([$active['id']]);
        }
        return;
    }
    if (in_array($status, ['finished', 'dnf'], true)) {
        if ($active && !array_key_exists('formats', $details)) {
            $details['formats'] = $active['formats'];
        }
        $data = normalizeSessionDates(validateSessionPayload(array_merge($details, ['status' => $status])));
        if ($active) {
            $id = (int)$active['id'];
            $fields = ['status = ?'];
            $values = [$status];
            foreach (['finish_date', 'finish_date_precision', 'dnf_progress_type', 'dnf_progress_value'] as $key) {
                if (array_key_exists($key, $data)) {
                    $fields[] = "$key = ?";
                    $values[] = $data[$key];
                }
            }
            $values[] = $id;
            $pdo->prepare('UPDATE reading_sessions SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($values);
            if (array_key_exists('formats', $data)) {
                replaceSessionFormats($pdo, $id, $data['formats']);
            }
        } else {
            $stmt =
                $pdo->prepare(
                    'INSERT INTO reading_sessions (
                        book_id,
                        status,
                        start_date,
                        start_date_precision,
                        finish_date,
                        finish_date_precision,
                        dnf_progress_type,
                        dnf_progress_value
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
            $stmt->execute(
                [
                    $bookId,
                    $status,
                    $data['start_date'] ?? null,
                    $data['start_date_precision'] ?? 'unknown',
                    $data['finish_date'] ?? null,
                    $data['finish_date_precision'] ?? 'unknown',
                    $status === 'dnf' ? ($data['dnf_progress_type'] ?? null) : null,
                    $status === 'dnf' ? ($data['dnf_progress_value'] ?? null) : null
                ]
            );
            if (array_key_exists('formats', $data)) {
                replaceSessionFormats($pdo, (int)$pdo->lastInsertId(), $data['formats']);
            }
        }
    }
}

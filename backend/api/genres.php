<?php
declare(strict_types=1);

function genreRespond(array $payload, int $status = 200): never
{
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') genreRespond(['error' => 'Method not allowed.'], 405);
$pdo = require __DIR__ . '/../settings/init.php';
$status = trim((string)($_GET['status'] ?? ''));
$allowed = ['want_to_read', 'reading', 'finished', 'paused', 'dnf'];
if ($status !== '' && !in_array($status, $allowed, true)) genreRespond(['error' => 'status is invalid.'], 422);
$sql =
  'SELECT DISTINCT g.id,g.name,g.slug FROM genres g JOIN book_genres bg ON bg.genre_id=g.id JOIN books b ON b.id=bg.book_id JOIN user_books ub ON ub.book_id=b.id';
$params = [];
if ($status !== '') {
  $sql .= ' WHERE ub.status=?';
  $params[] = $status;
}
$sql .= ' ORDER BY g.name,g.id';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
genreRespond($stmt->fetchAll());

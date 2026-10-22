<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/cover_images.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    exit;
}
$url = $_GET['url'] ?? null;
$width = filter_var($_GET['w'] ?? null, FILTER_VALIDATE_INT);
if (!is_string($url) || !trustedCoverUrl($url) || !in_array($width, COVER_WIDTHS, true)) {
    http_response_code(400);
    exit;
}
$cacheDir = getenv('COVER_CACHE_DIR') ?: '/var/cache/bookshelf/covers';
try {
    $path = optimizedCover($url, $width, $cacheDir);
} catch (Throwable $error) {
    // Missing codecs or hosting limits must not turn an optional optimization into a broken cover.
    error_log('Cover optimization failed: ' . $error->getMessage());
    $path = null;
}
if ($path === null) {
    header('Cache-Control: no-store');
    header('Location: ' . $url, true, 302);
    exit;
}
$etag = '"' . hash_file('sha256', $path) . '"';
header('Content-Type: image/webp');
header('Cache-Control: public, max-age=31536000, immutable');
header('ETag: ' . $etag);
header('X-Content-Type-Options: nosniff');
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}
header('Content-Length: ' . filesize($path));
readfile($path);

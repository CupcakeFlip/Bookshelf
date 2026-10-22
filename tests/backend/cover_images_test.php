<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/lib/cover_images.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

// Also run with -d disable_functions=imagewebp or curl_init to model missing hosting capabilities.
if (!function_exists('imagewebp')) {
    check(optimizedCover('https://assets.hardcover.app/test/cover.png', 320, sys_get_temp_dir()) === null,
        'missing WebP support did not fall back');
    echo "missing WebP fallback test passed\n";
    exit;
}
if (!function_exists('curl_init')) {
    check(downloadCover('https://assets.hardcover.app/test/cover.png') === null,
        'missing cURL did not fall back');
}

$directory = sys_get_temp_dir() . '/bookshelf-cover-test-' . bin2hex(random_bytes(6));
mkdir($directory);
try {
    $fixture = imagecreatetruecolor(400, 600);
    imagealphablending($fixture, false);
    imagesavealpha($fixture, true);
    $transparent = imagecolorallocatealpha($fixture, 0, 0, 0, 127);
    imagefill($fixture, 0, 0, $transparent);
    ob_start();
    imagepng($fixture);
    $png = ob_get_clean();
    imagedestroy($fixture);
    $downloads = 0;
    $download = static function () use (&$downloads, $png): string {
        $downloads++;
        return $png;
    };
    $url = 'https://assets.hardcover.app/test/cover.png';
    $small = optimizedCover($url, 320, $directory, $download);
    check($small !== null && is_file($small), '320px variant was not created');
    check(getimagesize($small)[0] === 320 && getimagesize($small)[1] === 480, 'aspect ratio changed');
    check(getimagesize($small)[2] === IMAGETYPE_WEBP, 'variant is not WebP');
    $pixel = imagecolorat(imagecreatefromwebp($small), 0, 0);
    check(($pixel & 0x7f000000) !== 0, 'transparency was lost');
    touch($small, time() - 60);
    clearstatcache(true, $small);
    $mtime = filemtime($small);
    check(optimizedCover($url, 320, $directory, $download) === $small, 'cache hit failed');
    clearstatcache(true, $small);
    check(filemtime($small) === $mtime && $downloads === 1, 'cache hit repeated work');
    file_put_contents($small, 'broken');
    check(optimizedCover($url, 320, $directory, $download) === $small
        && getimagesize($small)[2] === IMAGETYPE_WEBP && $downloads === 1, 'invalid variant was not regenerated from cached source');
    $large = optimizedCover($url, 960, $directory, $download);
    check(getimagesize($large)[0] === 400 && getimagesize($large)[1] === 600, 'small original was upscaled');
    check($downloads === 1, 'second variant downloaded original again');
    check(optimizedCover('https://localhost/private.png', 320, $directory, $download) === null, 'untrusted host accepted');
    check(optimizedCover('https://assets.hardcover.app:444/cover.png', 320, $directory, $download) === null, 'custom port accepted');
    check(optimizedCover($url, 321, $directory, $download) === null, 'invalid width accepted');
    check(optimizedCover('https://assets.hardcover.app/corrupt', 320, $directory, static fn() => 'bad image') === null, 'corrupt input converted');
    $photo = imagecreatetruecolor(400, 600);
    ob_start();
    imagejpeg($photo);
    $jpeg = ob_get_clean();
    ob_start();
    imagewebp($photo);
    $webp = ob_get_clean();
    imagedestroy($photo);
    foreach (['jpeg' => $jpeg, 'webp' => $webp] as $format => $bytes) {
        $path = optimizedCover("https://assets.hardcover.app/test/$format", 320, $directory, static fn() => $bytes);
        check($path !== null && getimagesize($path)[2] === IMAGETYPE_WEBP, "$format conversion failed");
    }
    check(optimizedCover('https://assets.hardcover.app/test/gif', 320, $directory, static fn() => 'GIF89a') === null, 'unsupported format accepted');
    $blocked = $directory . '/blocked';
    file_put_contents($blocked, 'not a directory');
    $warnings = [];
    set_error_handler(static function (int $level, string $message) use (&$warnings): bool {
        if (error_reporting() & $level) $warnings[] = $message;
        return true;
    });
    try {
        $beforeDownloads = $downloads;
        check(optimizedCover($url, 320, $blocked, $download) === null, 'unavailable cache did not fall back');
        check($downloads === $beforeDownloads, 'unavailable cache still downloaded the image');
        check($warnings === [], 'cache failure emitted warnings that could break redirect headers');
    } finally {
        restore_error_handler();
        unlink($blocked);
    }
    echo "cover image tests passed\n";
} finally {
    foreach (glob($directory . '/*/*') ?: [] as $file) unlink($file);
    foreach (glob($directory . '/*') ?: [] as $subdirectory) rmdir($subdirectory);
    rmdir($directory);
}

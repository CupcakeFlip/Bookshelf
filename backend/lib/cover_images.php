<?php
/** Made with AI */

/**
 * Handles book cover image processing and caching.
 *
 * Downloads and validates cover images from Hardcover,
 * resizes and converts them to WebP, and caches the results
 * to reduce unnecessary downloads and improve performance.
 *
 * Used by the cover API endpoint.
 */

declare(strict_types=1);

const COVER_WIDTHS = [320, 640, 960];
const COVER_MAX_BYTES = 10485760;
const COVER_MAX_PIXELS = 24000000;

/**
 * Checks the fixed HTTPS allowlist before the cover proxy makes a request.
 * @param string $url Original cover URL.
 * @return bool Whether the URL is supported and contains no credentials or control characters.
 */
function trustedCoverUrl(string $url): bool
{
    $parts = parse_url($url);
    return is_array($parts)
        && ($parts['scheme'] ?? '') === 'https'
        && strtolower($parts['host'] ?? '') === 'assets.hardcover.app'
        && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['port'])
        && !isset($parts['fragment'])
        && isset($parts['path'])
        && !preg_match('/[\x00-\x1f\x7f]/', $url)
        && strlen($url) <= 2048;
}

/**
 * Rejects unsupported or oversized image data before allocating a decoded image.
 * @param string $bytes Downloaded or cached image bytes.
 * @return array|null Image dimensions and type, or null when rejected.
 */
function coverImageInfo(string $bytes): ?array
{
    if ($bytes === '' || strlen($bytes) > COVER_MAX_BYTES) {
        return null;
    }
    $info = @getimagesizefromstring($bytes);
    if (
        !$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
        || $info[0] < 1 || $info[1] < 1 || max($info[0], $info[1]) > 8000
        || $info[0] * $info[1] > COVER_MAX_PIXELS) {
        return null;
    }
    return $info;
}

/**
 * Downloads a validated cover using a pinned public IP and no redirects.
 * @param string $url URL already accepted by trustedCoverUrl().
 * @return string|null Valid image bytes, or null on network or validation failure.
 */
function downloadCover(string $url): ?string
{
    if (!function_exists('curl_init') || !function_exists('dns_get_record')) {
        return null;
    }
    $host = 'assets.hardcover.app';
    $records = dns_get_record($host, DNS_A | DNS_AAAA);
    $addresses = [];
    foreach ($records ?: [] as $record) {
        $ip = $record['ip'] ?? $record['ipv6'] ?? null;
        if ($ip !== null && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            $addresses[] = str_contains($ip, ':') ? "[$ip]" : $ip;
        }
    }
    if (!$addresses) {
        return null;
    }
    $bytes = '';
    $curl = curl_init($url);
    curl_setopt_array(
        $curl,
        [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROXY => '',
            CURLOPT_RESOLVE => [$host . ':443:' . $addresses[0]],
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$bytes): int {
                if (strlen($bytes) + strlen($chunk) > COVER_MAX_BYTES) {
                    return 0;
                }
                $bytes .= $chunk;
                return strlen($chunk);
            },
        ]
    );
    $ok = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    return $ok && $status === 200 && coverImageInfo($bytes) ? $bytes : null;
}

/**
 * Checks whether a cached WebP can satisfy the requested width.
 * @param string $path Cache file path.
 * @param int $width Maximum pixel width.
 * @return bool Whether the variant is usable.
 */
function validCoverVariant(string $path, int $width): bool
{
    if (!is_file($path) || filesize($path) < 12) {
        return false;
    }
    $info = @getimagesize($path);
    return $info !== false && $info[2] === IMAGETYPE_WEBP && $info[0] <= $width && $info[0] > 0;
}

/**
 * Publishes a cache file by renaming a temporary file in the same directory.
 * @param string $path Destination path.
 * @param string $bytes Complete file contents.
 * @return bool Whether the complete file was published.
 */
function writeCoverFile(string $path, string $bytes): bool
{
    $temporary = @tempnam(dirname($path), 'cover-');
    if ($temporary === false) {
        return false;
    }
    $ok = @file_put_contents($temporary, $bytes) === strlen($bytes) && @rename($temporary, $path);
    if (!$ok) {
        @unlink($temporary);
    }
    return $ok;
}

/**
 * Reuses or creates a WebP variant while locking work for the same source URL.
 * The original bytes are shared across sizes; small images are never upscaled.
 * @param string $url Original cover URL.
 * @param int $width Supported pixel width.
 * @param string $cacheDir Writable cache directory.
 * @param callable|null $downloader Optional byte loader used by fixture tests.
 * @return string|null Cached variant path, or null to use the original cover.
 */
function optimizedCover(string $url, int $width, string $cacheDir, ?callable $downloader = null): ?string
{
    if (
        !trustedCoverUrl($url) || !in_array($width, COVER_WIDTHS, true)
        || !extension_loaded('gd') || !function_exists('imagewebp')
        || !(imagetypes() & IMG_WEBP)) {
        return null;
    }
    $key = hash('sha256', $url);
    $directory = rtrim($cacheDir, '/\\') . DIRECTORY_SEPARATOR . substr($key, 0, 2);
    if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
        return null;
    }
    $variant = $directory . DIRECTORY_SEPARATOR . $key . '-' . $width . '.webp';
    if (validCoverVariant($variant, $width)) {
        return $variant;
    }
    $lock = @fopen($directory . DIRECTORY_SEPARATOR . $key . '.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) {
        if ($lock) {
            fclose($lock);
        }
        return null;
    }
    try {
        if (validCoverVariant($variant, $width)) {
            return $variant;
        }
        $source = $directory . DIRECTORY_SEPARATOR . $key . '.source';
        $bytes = is_file($source) && filesize($source) <= COVER_MAX_BYTES ? @file_get_contents($source) : false;
        $info = is_string($bytes) ? coverImageInfo($bytes) : null;
        if (!$info) {
            $bytes = ($downloader ?? 'downloadCover')($url);
            $info = is_string($bytes) ? coverImageInfo($bytes) : null;
            if (!$info || !writeCoverFile($source, $bytes)) {
                return null;
            }
        }
        $image = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
            IMAGETYPE_PNG => @imagecreatefrompng($source),
            IMAGETYPE_WEBP => @imagecreatefromwebp($source),
        };
        if (!$image) {
            return null;
        }
        $targetWidth = min($width, $info[0]);
        $targetHeight = max(1, (int)round($info[1] * $targetWidth / $info[0]));
        $resized = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $info[0], $info[1]);
        imagedestroy($image);
        $temporary = @tempnam($directory, 'cover-');
        if ($temporary === false) {
            imagedestroy($resized);
            return null;
        }
        $ok = @imagewebp($resized, $temporary, 82) && validCoverVariant($temporary, $width)
            && @rename($temporary, $variant);
        imagedestroy($resized);
        if (!$ok) {
            @unlink($temporary);
            return null;
        }
        return $variant;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

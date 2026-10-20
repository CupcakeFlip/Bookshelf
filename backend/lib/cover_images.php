<?php

declare(strict_types=1);

const COVER_WIDTHS = [320, 640, 960];
const COVER_MAX_BYTES = 10485760;
const COVER_MAX_PIXELS = 24000000;

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

function coverImageInfo(string $bytes): ?array
{
    if ($bytes === '' || strlen($bytes) > COVER_MAX_BYTES) {
        return null;
    }
    $info = @getimagesizefromstring($bytes);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
        || $info[0] < 1 || $info[1] < 1 || max($info[0], $info[1]) > 8000
        || $info[0] * $info[1] > COVER_MAX_PIXELS) {
        return null;
    }
    return $info;
}

function downloadCover(string $url): ?string
{
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
    curl_setopt_array($curl, [
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
    ]);
    $ok = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    return $ok && $status === 200 && coverImageInfo($bytes) ? $bytes : null;
}

function validCoverVariant(string $path, int $width): bool
{
    if (!is_file($path) || filesize($path) < 12) {
        return false;
    }
    $info = @getimagesize($path);
    return $info !== false && $info[2] === IMAGETYPE_WEBP && $info[0] <= $width && $info[0] > 0;
}

function writeCoverFile(string $path, string $bytes): bool
{
    $temporary = tempnam(dirname($path), 'cover-');
    if ($temporary === false) {
        return false;
    }
    $ok = file_put_contents($temporary, $bytes) === strlen($bytes) && rename($temporary, $path);
    if (!$ok) {
        @unlink($temporary);
    }
    return $ok;
}

function optimizedCover(string $url, int $width, string $cacheDir, ?callable $downloader = null): ?string
{
    if (!trustedCoverUrl($url) || !in_array($width, COVER_WIDTHS, true) || !extension_loaded('gd')) {
        return null;
    }
    $key = hash('sha256', $url);
    $directory = rtrim($cacheDir, '/\\') . DIRECTORY_SEPARATOR . substr($key, 0, 2);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        return null;
    }
    $variant = $directory . DIRECTORY_SEPARATOR . $key . '-' . $width . '.webp';
    if (validCoverVariant($variant, $width)) {
        return $variant;
    }
    $lock = fopen($directory . DIRECTORY_SEPARATOR . $key . '.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) {
        if ($lock) fclose($lock);
        return null;
    }
    try {
        if (validCoverVariant($variant, $width)) {
            return $variant;
        }
        $source = $directory . DIRECTORY_SEPARATOR . $key . '.source';
        $bytes = is_file($source) && filesize($source) <= COVER_MAX_BYTES ? file_get_contents($source) : false;
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
        $temporary = tempnam($directory, 'cover-');
        if ($temporary === false) {
            imagedestroy($resized);
            return null;
        }
        $ok = imagewebp($resized, $temporary, 82) && validCoverVariant($temporary, $width)
            && rename($temporary, $variant);
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

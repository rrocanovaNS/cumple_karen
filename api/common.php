<?php

declare(strict_types=1);

date_default_timezone_set('America/Montevideo');

const MAX_IMAGE_BYTES = 80 * 1024 * 1024;
const MAX_VIDEO_BYTES = 450 * 1024 * 1024;

const ALLOWED_MIME_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'video/mp4' => 'mp4',
    'video/quicktime' => 'mov',
    'video/webm' => 'webm',
];

const ALLOWED_CATEGORIES = [
    'recuerdos',
    'familia',
];

function baseMediaDirectory(): string
{
    $configuredDirectory = getenv('MEDIA_UPLOAD_DIR');

    if (is_string($configuredDirectory) && $configuredDirectory !== '') {
        $configuredDirectory = rtrim($configuredDirectory, '/');

        if (basename($configuredDirectory) === 'media') {
            return $configuredDirectory;
        }

        return $configuredDirectory . '/media';
    }

    return dirname(__DIR__) . '/media';
}

function requestedCategory(): string
{
    $category = $_REQUEST['category'] ?? 'recuerdos';

    if (!is_string($category) || !in_array($category, ALLOWED_CATEGORIES, true)) {
        jsonResponse(400, [
            'ok' => false,
            'error' => 'Categoria no valida.',
        ]);
    }

    return $category;
}

function mediaDirectory(?string $category = null): string
{
    $category = $category ?? requestedCategory();
    $baseDirectory = baseMediaDirectory();

    if ($category === 'recuerdos') {
        return $baseDirectory;
    }

    return $baseDirectory . '/' . $category;
}

function jsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function ensureMediaDirectoryExists(string $directory): void
{
    if (is_dir($directory)) {
        return;
    }

    if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
        jsonResponse(500, [
            'ok' => false,
            'error' => 'No se pudo crear la carpeta de almacenamiento.',
        ]);
    }
}

function requestScheme(): string
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        return $_SERVER['HTTP_X_FORWARDED_PROTO'];
    }

    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return 'https';
    }

    return 'http';
}

function mediaBaseUrl(): string
{
    $configuredBaseUrl = getenv('MEDIA_BASE_URL');

    if (is_string($configuredBaseUrl) && $configuredBaseUrl !== '') {
        return rtrim($configuredBaseUrl, '/');
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return requestScheme() . '://' . $host . '/media';
}

function randomSuffix(int $length = 8): string
{
    $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $result = '';
    $maxIndex = strlen($characters) - 1;

    for ($i = 0; $i < $length; $i++) {
        $result .= $characters[random_int(0, $maxIndex)];
    }

    return $result;
}

function buildStoredFilename(string $extension): string
{
    return date('Y_m_d_H_i_s') . '_' . randomSuffix(8) . '.' . $extension;
}

function detectMimeType(string $temporaryPath): string
{
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($temporaryPath);

    return is_string($mimeType) ? $mimeType : '';
}

function classifyMimeType(string $mimeType): string
{
    if (str_starts_with($mimeType, 'image/')) {
        return 'image';
    }

    if (str_starts_with($mimeType, 'video/')) {
        return 'video';
    }

    return 'unknown';
}

function maxAllowedBytesForMime(string $mimeType): int
{
    return classifyMimeType($mimeType) === 'image' ? MAX_IMAGE_BYTES : MAX_VIDEO_BYTES;
}

function publicFileUrl(string $filename, ?string $category = null): string
{
    $category = $category ?? requestedCategory();
    $baseUrl = mediaBaseUrl();

    if ($category === 'recuerdos') {
        return $baseUrl . '/' . rawurlencode($filename);
    }

    return $baseUrl . '/' . rawurlencode($category) . '/' . rawurlencode($filename);
}

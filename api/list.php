<?php

declare(strict_types=1);

require __DIR__ . '/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(405, [
        'ok' => false,
        'error' => 'Metodo no permitido.',
    ]);
}

$category = requestedCategory();
$storageDirectory = mediaDirectory($category);
ensureMediaDirectoryExists($storageDirectory);

$entries = scandir($storageDirectory);

if ($entries === false) {
    jsonResponse(500, [
        'ok' => false,
        'error' => 'No se pudo leer la carpeta de archivos.',
    ]);
}

$files = [];

foreach ($entries as $entry) {
    if ($entry === '.' || $entry === '..') {
        continue;
    }

    $fullPath = $storageDirectory . '/' . $entry;

    if (!is_file($fullPath)) {
        continue;
    }

    $mimeType = detectMimeType($fullPath);

    if (!isset(ALLOWED_MIME_TYPES[$mimeType])) {
        continue;
    }

    $files[] = [
        'name' => $entry,
        'mime_type' => $mimeType,
        'type' => classifyMimeType($mimeType),
        'category' => $category,
        'size' => filesize($fullPath),
        'modified' => date(DATE_ATOM, (int) filemtime($fullPath)),
        'url' => publicFileUrl($entry, $category),
    ];
}

usort(
    $files,
    static fn (array $left, array $right): int => strcmp($right['modified'], $left['modified'])
);

jsonResponse(200, [
    'ok' => true,
    'count' => count($files),
    'files' => $files,
]);

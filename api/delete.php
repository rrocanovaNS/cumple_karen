<?php

declare(strict_types=1);

require __DIR__ . '/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, [
        'ok' => false,
        'error' => 'Metodo no permitido.',
    ]);
}

$category = requestedCategory();
$filename = requestedFilename();
$filePath = mediaFilePath($filename, $category);

if (!is_file($filePath)) {
    jsonResponse(404, [
        'ok' => false,
        'error' => 'El archivo no existe.',
    ]);
}

if (!unlink($filePath)) {
    jsonResponse(500, [
        'ok' => false,
        'error' => 'No se pudo eliminar el archivo.',
    ]);
}

jsonResponse(200, [
    'ok' => true,
    'category' => $category,
    'filename' => $filename,
]);

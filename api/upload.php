<?php

declare(strict_types=1);

require __DIR__ . '/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, [
        'ok' => false,
        'error' => 'Metodo no permitido.',
    ]);
}

if (!isset($_FILES['file'])) {
    jsonResponse(400, [
        'ok' => false,
        'error' => 'Debe enviarse un archivo en el campo "file".',
    ]);
}

$upload = $_FILES['file'];

if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $errorCode = is_array($upload) ? ($upload['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;

    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'El archivo supera el limite configurado en PHP.',
        UPLOAD_ERR_FORM_SIZE => 'El archivo supera el limite permitido por el formulario.',
        UPLOAD_ERR_PARTIAL => 'El archivo se subio parcialmente.',
        UPLOAD_ERR_NO_FILE => 'No se recibio ningun archivo.',
        UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal de PHP.',
        UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo en disco.',
        UPLOAD_ERR_EXTENSION => 'Una extension de PHP detuvo la subida.',
    ];

    jsonResponse(400, [
        'ok' => false,
        'error' => $errorMessages[$errorCode] ?? 'No se pudo procesar la subida.',
    ]);
}

$temporaryPath = $upload['tmp_name'] ?? '';
$originalName = (string) ($upload['name'] ?? '');
$fileSize = (int) ($upload['size'] ?? 0);

if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
    jsonResponse(400, [
        'ok' => false,
        'error' => 'El archivo recibido no es valido.',
    ]);
}

$mimeType = detectMimeType($temporaryPath);
$category = requestedCategory();

if (!isset(ALLOWED_MIME_TYPES[$mimeType])) {
    jsonResponse(415, [
        'ok' => false,
        'error' => 'Tipo de archivo no permitido.',
        'mime_type' => $mimeType,
    ]);
}

if ($category === 'familia' && classifyMimeType($mimeType) !== 'video') {
    jsonResponse(415, [
        'ok' => false,
        'error' => 'En la categoria familia solo se permiten videos.',
        'mime_type' => $mimeType,
    ]);
}

$maxAllowedBytes = maxAllowedBytesForMime($mimeType);

if ($fileSize > $maxAllowedBytes) {
    jsonResponse(413, [
        'ok' => false,
        'error' => 'El archivo supera el tamano maximo permitido.',
        'max_bytes' => $maxAllowedBytes,
    ]);
}

$storageDirectory = mediaDirectory($category);
ensureMediaDirectoryExists($storageDirectory);

$extension = ALLOWED_MIME_TYPES[$mimeType];
$storedFilename = buildStoredFilename($extension);
$destinationPath = $storageDirectory . '/' . $storedFilename;

if (!move_uploaded_file($temporaryPath, $destinationPath)) {
    jsonResponse(500, [
        'ok' => false,
        'error' => 'No se pudo guardar el archivo subido.',
    ]);
}

jsonResponse(201, [
    'ok' => true,
    'file' => [
        'original_name' => $originalName,
        'stored_name' => $storedFilename,
        'mime_type' => $mimeType,
        'type' => classifyMimeType($mimeType),
        'category' => $category,
        'size' => filesize($destinationPath),
        'url' => publicFileUrl($storedFilename, $category),
    ],
]);

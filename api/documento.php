<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers/response.php';
require_once __DIR__ . '/services/auth.php';
require_once __DIR__ . '/services/glpi.php';

startPortalSession();
requireAuthentication();

$documentId = sanitizeInt($_GET['id'] ?? 0);

if ($documentId <= 0) {
    errorResponse('Informe um ID de documento válido.', 422);
}

try {
    $glpi = new GlpiService(getSessionToken());
    $download = $glpi->downloadDocumento($documentId);

    $filename = sanitizeString($download['filename'] ?? ('anexo-' . $documentId), 255);
    $contentType = (string) ($download['content_type'] ?? 'application/octet-stream');
    $content = $download['content'] ?? '';

    if (!is_string($content) || $content === '') {
        errorResponse('Conteúdo do documento indisponível.', 404);
    }

    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
    header('Content-Length: ' . (string) strlen($content));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');

    echo $content;
    exit;
} catch (Throwable $exception) {
    $code = $exception->getCode();
    $httpCode = is_int($code) && $code >= 400 && $code < 600 ? $code : 500;
    errorResponse($exception->getMessage(), $httpCode);
}

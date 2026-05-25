<?php

declare(strict_types=1);

/**
 * Padroniza respostas JSON dos endpoints da API.
 */

function sendJsonResponse(array $payload, int $httpCode = 200): void
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function successResponse(string $message, mixed $data = null, int $httpCode = 200): void
{
    $payload = [
        'status' => true,
        'message' => $message,
        'data' => $data ?? new stdClass(),
    ];

    sendJsonResponse($payload, $httpCode);
}

function errorResponse(string $message, int $httpCode = 400, mixed $data = null): void
{
    $payload = [
        'status' => false,
        'message' => $message,
        'data' => $data ?? new stdClass(),
    ];

    sendJsonResponse($payload, $httpCode);
}

function requirePostMethod(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        errorResponse('Método não permitido. Utilize POST.', 405);
    }
}

function getJsonInput(): array
{
    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        return $_POST;
    }

    $decoded = json_decode($raw, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        errorResponse('JSON inválido na requisição.', 400);
    }

    return is_array($decoded) ? $decoded : [];
}

function sanitizeString(mixed $value, int $maxLength = 5000): string
{
    $text = trim((string) $value);
    $text = strip_tags($text);

    if (mb_strlen($text) > $maxLength) {
        $text = mb_substr($text, 0, $maxLength);
    }

    return $text;
}

function sanitizeInt(mixed $value): int
{
    return (int) filter_var($value, FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
}

/**
 * Normaliza estrutura $_FILES para lista única (um ou vários arquivos).
 *
 * @return array<int, array{name: string, type: string, tmp_name: string, error: int, size: int}>
 */
function normalizeUploadedFiles(array $fileField): array
{
    if (!is_array($fileField['name'] ?? null)) {
        return [[
            'name' => (string) ($fileField['name'] ?? ''),
            'type' => (string) ($fileField['type'] ?? ''),
            'tmp_name' => (string) ($fileField['tmp_name'] ?? ''),
            'error' => (int) ($fileField['error'] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int) ($fileField['size'] ?? 0),
        ]];
    }

    $files = [];
    $count = count($fileField['name']);

    for ($i = 0; $i < $count; $i += 1) {
        $files[] = [
            'name' => (string) ($fileField['name'][$i] ?? ''),
            'type' => (string) ($fileField['type'][$i] ?? ''),
            'tmp_name' => (string) ($fileField['tmp_name'][$i] ?? ''),
            'error' => (int) ($fileField['error'][$i] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int) ($fileField['size'][$i] ?? 0),
        ];
    }

    return $files;
}

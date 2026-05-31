<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers/response.php';
require_once __DIR__ . '/services/auth.php';
require_once __DIR__ . '/services/glpi.php';

startPortalSession();
requireAuthentication();
requirePostMethod();

$userId = getAuthenticatedUserId();
$isMultipart = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data');

$input = $isMultipart ? $_POST : getJsonInput();

$titulo = sanitizeString($input['titulo'] ?? $input['title'] ?? '', 200);
$descricao = sanitizeString($input['descricao'] ?? $input['description'] ?? '', 10000);
$categoria = sanitizeInt($input['categoria'] ?? $input['category'] ?? 0);
$ticketType = sanitizeString($input['ticket_type'] ?? $input['tipo'] ?? '', 32);
$urgencyKey = sanitizeString($input['urgency'] ?? $input['prioridade'] ?? $input['urgencia'] ?? '', 32);

if ($descricao === '') {
    errorResponse('A descrição do chamado é obrigatória.', 422);
}

if ($titulo === '') {
    $titulo = 'Chamado via portal - ' . date('d/m/Y H:i');
}

$ticketInput = array_merge([
    'name' => $titulo,
    'content' => $descricao,
    'type' => mapTicketTypeToGlpi($ticketType !== '' ? $ticketType : null),
    'urgency' => mapUrgencyToGlpi($urgencyKey !== '' ? $urgencyKey : null),
    'priority' => mapUrgencyToGlpi($urgencyKey !== '' ? $urgencyKey : null),
], buildTicketRequesterInput($userId));

if ($categoria > 0) {
    $ticketInput['itilcategories_id'] = $categoria;
}

try {
    $glpi = new GlpiService(getSessionToken());
    $created = $glpi->criarChamado($ticketInput);
    $ticketId = (int) ($created['body']['id'] ?? 0);

    if ($ticketId <= 0) {
        errorResponse('Chamado criado, mas o ID não foi retornado pelo GLPI.', 500, $created['body']);
    }

    $uploadErrors = [];
    $maxBytes = 40 * 1024 * 1024;
    $totalSize = 0;
    $normalizedFiles = collectRequestAttachments();

    if (!empty($normalizedFiles)) {
        foreach ($normalizedFiles as $file) {
            if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                $uploadErrors[] = 'Falha no upload do arquivo: ' . ($file['name'] ?? 'desconhecido');
                continue;
            }

            $totalSize += (int) ($file['size'] ?? 0);
        }

        if ($totalSize > $maxBytes) {
            errorResponse('O tamanho total dos anexos ultrapassa 40 MB.', 422);
        }

        foreach ($normalizedFiles as $file) {
            if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                continue;
            }

            $tmpPath = $file['tmp_name'] ?? '';
            $originalName = sanitizeString($file['name'] ?? 'anexo', 255);

            try {
                $glpi->uploadDocument($ticketId, 'Ticket', $tmpPath, $originalName);
            } catch (Throwable $uploadException) {
                $uploadErrors[] = $originalName . ': ' . $uploadException->getMessage();
            }
        }
    }

    successResponse('Chamado criado com sucesso.', [
        'ticket_id' => $ticketId,
        'upload_errors' => $uploadErrors,
    ]);
} catch (Throwable $exception) {
    $code = $exception->getCode();
    $httpCode = is_int($code) && $code >= 400 && $code < 600 ? $code : 500;
    errorResponse($exception->getMessage(), $httpCode);
}

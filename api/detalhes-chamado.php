<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers/response.php';
require_once __DIR__ . '/services/auth.php';
require_once __DIR__ . '/services/glpi.php';

startPortalSession();
requireAuthentication();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!in_array($method, ['GET', 'POST'], true)) {
    errorResponse('Método não permitido.', 405);
}

$input = $method === 'POST' ? getJsonInput() : $_GET;
$ticketId = sanitizeInt($input['id'] ?? $input['ticket_id'] ?? 0);

if ($ticketId <= 0) {
    errorResponse('Informe um ID de chamado válido.', 422);
}

try {
    $glpi = new GlpiService(getSessionToken());
    $result = $glpi->buscarChamado($ticketId);
    $ticket = normalizeTicketDetail(is_array($result['body']) ? $result['body'] : []);

    if ($ticket['id'] <= 0) {
        errorResponse('Chamado não encontrado.', 404);
    }

    $userId = getAuthenticatedUserId();

    if ($ticket['solicitante_id'] > 0 && $ticket['solicitante_id'] !== $userId) {
        errorResponse('Você não tem permissão para visualizar este chamado.', 403);
    }

    successResponse('Detalhes do chamado obtidos com sucesso.', [
        'ticket' => $ticket,
    ]);
} catch (Throwable $exception) {
    $code = $exception->getCode();
    $httpCode = is_int($code) && $code >= 400 && $code < 600 ? $code : 500;
    errorResponse($exception->getMessage(), $httpCode);
}

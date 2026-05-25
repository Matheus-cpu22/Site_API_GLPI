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

$userId = getAuthenticatedUserId();
$rangeStart = 0;
$rangeEnd = 49;

if ($method === 'POST') {
    $input = getJsonInput();
    $rangeStart = max(0, sanitizeInt($input['range_start'] ?? 0));
    $rangeEnd = max($rangeStart, sanitizeInt($input['range_end'] ?? 49));
}

try {
    $glpi = new GlpiService(getSessionToken());
    $result = $glpi->listarChamados($userId, $rangeStart, $rangeEnd);
    $tickets = normalizeTicketSearchResult($result['body']);

    successResponse('Chamados listados com sucesso.', [
        'tickets' => $tickets,
        'total' => (int) ($result['body']['totalcount'] ?? count($tickets)),
    ]);
} catch (Throwable $exception) {
    $code = $exception->getCode();
    $httpCode = is_int($code) && $code >= 400 && $code < 600 ? $code : 500;
    errorResponse($exception->getMessage(), $httpCode);
}

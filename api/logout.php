<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers/response.php';
require_once __DIR__ . '/services/auth.php';
require_once __DIR__ . '/services/glpi.php';

startPortalSession();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!in_array($method, ['POST', 'GET'], true)) {
    errorResponse('Método não permitido.', 405);
}

try {
    $sessionToken = getSessionToken();

    if ($sessionToken) {
        $glpi = new GlpiService($sessionToken);
        $glpi->killSession();
    }
} catch (Throwable) {
    // Encerra sessão local mesmo se o GLPI já tiver expirado.
}

clearPortalSession();
successResponse('Logout realizado com sucesso.');

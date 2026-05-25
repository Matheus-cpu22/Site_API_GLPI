<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers/response.php';
require_once __DIR__ . '/services/auth.php';

startPortalSession();

if (!isAuthenticated()) {
    errorResponse('Não autenticado.', 401);
}

successResponse('Sessão ativa.', [
    'user' => getAuthenticatedUser(),
]);

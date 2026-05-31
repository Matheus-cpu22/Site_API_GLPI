<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers/response.php';
require_once __DIR__ . '/services/auth.php';
require_once __DIR__ . '/services/glpi.php';

startPortalSession();
requirePostMethod();

$input = getJsonInput();
$login = sanitizeString($input['login'] ?? $input['email'] ?? '', 128);
$password = (string) ($input['password'] ?? '');

if ($login === '' || $password === '') {
    errorResponse('Informe login e senha.', 422);
}

try {
    $glpi = new GlpiService();
    $sessionResponse = $glpi->initSession($login, $password);
    $sessionToken = (string) ($sessionResponse['body']['session_token'] ?? '');

    if ($sessionToken === '') {
        errorResponse('Não foi possível autenticar no GLPI.', 401);
    }

    $glpiAuthenticated = new GlpiService($sessionToken);
    $fullSession = $glpiAuthenticated->getFullSession();
    $sessionData = $fullSession['body']['session'] ?? $fullSession['body'] ?? [];
    $glpiUserId = (int) (
        $sessionData['glpiID']
        ?? $sessionData['id']
        ?? $sessionData['glpiactiveprofile']['users_id']
        ?? 0
    );

    if ($glpiUserId <= 0) {
        $glpiAuthenticated->killSession();
        errorResponse('Sessão GLPI inválida: ID do usuário não encontrado.', 401);
    }

    $user = [
        'id' => $glpiUserId,
        'name' => (string) ($sessionData['glpifriendlyname'] ?? $sessionData['glpirealname'] ?? $sessionData['glpiname'] ?? $login),
        'login' => (string) ($sessionData['glpiname'] ?? $login),
    ];

    setAuthenticatedUser($sessionToken, $user);

    successResponse('Login realizado com sucesso.', [
        'user' => getAuthenticatedUser(),
    ]);
} catch (Throwable $exception) {
    $code = $exception->getCode();
    $httpCode = is_int($code) && $code >= 400 && $code < 600 ? $code : 500;
    errorResponse($exception->getMessage(), $httpCode);
}

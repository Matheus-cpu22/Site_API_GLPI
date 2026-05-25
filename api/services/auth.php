<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/response.php';

/**
 * Controle de sessão PHP do portal (não confundir com Session-Token do GLPI).
 */

function startPortalSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name(PORTAL_SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

function isAuthenticated(): bool
{
    return !empty($_SESSION['glpi_session_token']) && !empty($_SESSION['glpi_user_id']);
}

function requireAuthentication(): void
{
    if (!isAuthenticated()) {
        errorResponse('Sessão expirada ou não autenticado. Faça login novamente.', 401);
    }
}

function setAuthenticatedUser(string $sessionToken, array $user): void
{
    $_SESSION['glpi_session_token'] = $sessionToken;
    $_SESSION['glpi_user_id'] = (int) ($user['id'] ?? 0);
    $_SESSION['glpi_user_name'] = (string) ($user['name'] ?? '');
    $_SESSION['glpi_user_login'] = (string) ($user['login'] ?? '');
}

function getSessionToken(): ?string
{
    return $_SESSION['glpi_session_token'] ?? null;
}

function getAuthenticatedUserId(): int
{
    return (int) ($_SESSION['glpi_user_id'] ?? 0);
}

function getAuthenticatedUser(): array
{
    return [
        'id' => getAuthenticatedUserId(),
        'name' => (string) ($_SESSION['glpi_user_name'] ?? ''),
        'login' => (string) ($_SESSION['glpi_user_login'] ?? ''),
    ];
}

function clearPortalSession(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

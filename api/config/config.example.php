<?php
/**
 * Copie este arquivo para config.php e preencha com os valores reais.
 * Nunca versione config.php com tokens em repositórios públicos.
 */

declare(strict_types=1);

define('GLPI_URL', 'http://localhost:8080/apirest.php');
define('APP_TOKEN', 'SEU_APP_TOKEN_AQUI');
define('USER_TOKEN', 'SEU_USER_TOKEN_OPCIONAL');

/** Timeout das requisições cURL ao GLPI (segundos). */
define('GLPI_TIMEOUT', 30);

/** Nome da sessão PHP do portal. */
define('PORTAL_SESSION_NAME', 'TVF_PORTAL_SESSION');

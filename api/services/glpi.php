<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

/**
 * Cliente centralizado para a API REST do GLPI 10.
 */
class GlpiService
{
    private string $baseUrl;
    private ?string $sessionToken;

    public function __construct(?string $sessionToken = null)
    {
        $this->baseUrl = rtrim(GLPI_URL, '/');
        $this->sessionToken = $sessionToken;
    }

    /**
     * Inicia sessão no GLPI com login e senha do usuário.
     */
    public function initSession(string $login, string $password): array
    {
        $authorization = 'Basic ' . base64_encode($login . ':' . $password);

        return $this->request('GET', 'initSession', null, [
            'Authorization: ' . $authorization,
        ]);
    }

    /**
     * Encerra sessão ativa no GLPI.
     */
    public function killSession(): array
    {
        return $this->request('GET', 'killSession');
    }

    /**
     * Retorna dados da sessão (inclui usuário autenticado).
     */
    public function getFullSession(): array
    {
        return $this->request('GET', 'getFullSession');
    }

    /**
     * Cria um chamado (Ticket) no GLPI.
     */
    public function criarChamado(array $input): array
    {
        return $this->request('POST', 'Ticket/', ['input' => $input]);
    }

    /**
     * Lista chamados do solicitante informado.
     */
    public function listarChamados(int $userId, int $rangeStart = 0, int $rangeEnd = 49): array
    {
        $query = http_build_query([
            'criteria[0][field]' => '4',
            'criteria[0][searchtype]' => 'equals',
            'criteria[0][value]' => $userId,
            'forcedisplay[0]' => '1',
            'forcedisplay[1]' => '2',
            'forcedisplay[2]' => '12',
            'forcedisplay[3]' => '15',
            'forcedisplay[4]' => '21',
            'range' => $rangeStart . '-' . $rangeEnd,
        ]);

        return $this->request('GET', 'search/Ticket?' . $query);
    }

    /**
     * Busca um chamado pelo ID.
     */
    public function buscarChamado(int $ticketId): array
    {
        return $this->request('GET', 'Ticket/' . $ticketId);
    }

    /**
     * Anexa documento a um item (ex.: Ticket).
     */
    public function uploadDocument(int $itemsId, string $itemtype, string $filePath, string $fileName): array
    {
        if (!is_readable($filePath)) {
            throw new RuntimeException('Arquivo não encontrado para upload.');
        }

        $manifest = json_encode([
            'input' => [
                'name' => $fileName,
                'items_id' => $itemsId,
                'itemtype' => $itemtype,
            ],
        ], JSON_UNESCAPED_UNICODE);

        $mime = mime_content_type($filePath) ?: 'application/octet-stream';
        $file = new CURLFile($filePath, $mime, $fileName);

        return $this->requestMultipart('POST', 'Document/', [
            'uploadManifest' => $manifest,
            'filename' => $file,
        ]);
    }

  private function request(string $method, string $endpoint, ?array $body = null, array $extraHeaders = []): array
    {
        $url = $this->buildUrl($endpoint);
        $ch = curl_init($url);

        $headers = array_merge($this->defaultHeaders(false), $extraHeaders);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => GLPI_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        }

        return $this->executeCurl($ch, $url);
    }

    private function requestMultipart(string $method, string $endpoint, array $fields): array
    {
        $url = $this->buildUrl($endpoint);
        $ch = curl_init($url);

        $headers = $this->defaultHeaders(true);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $fields,
            CURLOPT_TIMEOUT => GLPI_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        return $this->executeCurl($ch, $url);
    }

    private function defaultHeaders(bool $multipart): array
    {
        $headers = [
            'App-Token: ' . APP_TOKEN,
        ];

        if (!$multipart) {
            $headers[] = 'Content-Type: application/json';
        }

        if ($this->sessionToken) {
            $headers[] = 'Session-Token: ' . $this->sessionToken;
        } elseif (defined('USER_TOKEN') && USER_TOKEN !== '') {
            $headers[] = 'Authorization: user_token ' . USER_TOKEN;
        }

        return $headers;
    }

    private function buildUrl(string $endpoint): string
    {
        if (str_starts_with($endpoint, 'http')) {
            return $endpoint;
        }

        return $this->baseUrl . '/' . ltrim($endpoint, '/');
    }

    private function executeCurl(CurlHandle $ch, string $url): array
    {
        $responseBody = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false) {
            throw new RuntimeException('Falha na comunicação com o GLPI: ' . $curlError);
        }

        $decoded = json_decode($responseBody, true);

        if ($httpCode >= 400) {
            $message = is_array($decoded)
                ? ($decoded[0] ?? $decoded['message'] ?? 'Erro na API GLPI')
                : 'Erro na API GLPI';

            if (is_array($message)) {
                $message = json_encode($message, JSON_UNESCAPED_UNICODE);
            }

            throw new RuntimeException((string) $message, $httpCode);
        }

        return [
            'http_code' => $httpCode,
            'body' => $decoded ?? $responseBody,
            'raw' => $responseBody,
        ];
    }
}

/**
 * Mapeia urgência do formulário para valores do GLPI (1–5).
 */
function mapUrgencyToGlpi(?string $urgency): int
{
    return match ($urgency) {
        'muito_baixa' => 1,
        'baixa' => 2,
        'media' => 3,
        'alta' => 4,
        'muito_alta' => 5,
        default => 3,
    };
}

/**
 * Mapeia tipo do formulário para GLPI (1 = Incidente, 2 = Requisição).
 */
function mapTicketTypeToGlpi(?string $type): int
{
    return match ($type) {
        'incidente' => 1,
        'requisicao' => 2,
        default => 1,
    };
}

/**
 * Normaliza resultado da busca do GLPI em lista amigável ao frontend.
 */
function normalizeTicketSearchResult(array $searchBody): array
{
    $rows = [];
    $data = $searchBody['data'] ?? [];
    $rawRows = $data['data'] ?? $data;

    if (!is_array($rawRows)) {
        return [];
    }

    foreach ($rawRows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $rows[] = [
            'id' => (int) ($row['2'] ?? $row['id'] ?? 0),
            'titulo' => (string) ($row['1'] ?? $row['name'] ?? ''),
            'status' => (string) ($row['12'] ?? ''),
            'data_abertura' => (string) ($row['15'] ?? ''),
            'urgencia' => (string) ($row['21'] ?? ''),
        ];
    }

    return $rows;
}

/**
 * Normaliza ticket individual retornado pelo GLPI.
 */
function normalizeTicketDetail(array $ticket): array
{
    return [
        'id' => (int) ($ticket['id'] ?? 0),
        'titulo' => (string) ($ticket['name'] ?? ''),
        'descricao' => (string) ($ticket['content'] ?? ''),
        'status' => (int) ($ticket['status'] ?? 0),
        'urgencia' => (int) ($ticket['urgency'] ?? 0),
        'prioridade' => (int) ($ticket['priority'] ?? 0),
        'tipo' => (int) ($ticket['type'] ?? 0),
        'data_abertura' => (string) ($ticket['date'] ?? ''),
        'data_modificacao' => (string) ($ticket['date_mod'] ?? ''),
        'solicitante_id' => (int) ($ticket['_users_id_requester'] ?? 0),
    ];
}

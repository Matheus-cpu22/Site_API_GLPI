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
     * Lista chamados do solicitante informado (múltiplas estratégias compatíveis com GLPI 10).
     */
    public function listarChamados(int $userId, ?string $userLogin = null, int $rangeStart = 0, int $rangeEnd = 49): array
    {
        $attempts = [];

        if ($userId > 0) {
            $attempts[] = $this->buildTicketSearchQuery(4, (string) $userId, $rangeStart, $rangeEnd);
            $attempts[] = $this->buildTicketSearchQuery(71, (string) $userId, $rangeStart, $rangeEnd);
        }

        if ($userLogin !== null && $userLogin !== '') {
            $attempts[] = $this->buildTicketSearchQuery(4, $userLogin, $rangeStart, $rangeEnd);
        }

        foreach ($attempts as $query) {
            try {
                $result = $this->request('GET', 'search/Ticket?' . $query);
                $rows = normalizeTicketSearchResult($result['body']);

                if (!empty($rows)) {
                    return $result;
                }
            } catch (Throwable) {
                continue;
            }
        }

        // Fallback: tickets visíveis ao perfil do usuário logado no GLPI.
        return $this->request('GET', 'Ticket/?range=' . $rangeStart . '-' . $rangeEnd . '&sort=id&order=DESC');
    }

    private function buildTicketSearchQuery(int $field, string $value, int $rangeStart, int $rangeEnd): string
    {
        return http_build_query([
            'criteria[0][link]' => 'AND',
            'criteria[0][field]' => $field,
            'criteria[0][searchtype]' => 'equals',
            'criteria[0][value]' => $value,
            'forcedisplay[0]' => '2',
            'forcedisplay[1]' => '1',
            'forcedisplay[2]' => '12',
            'forcedisplay[3]' => '15',
            'forcedisplay[4]' => '19',
            'forcedisplay[5]' => '3',
            'forcedisplay[6]' => '21',
            'sort' => '2',
            'order' => 'DESC',
            'range' => $rangeStart . '-' . $rangeEnd,
        ]);
    }

    /**
     * Busca um chamado pelo ID.
     */
    public function buscarChamado(int $ticketId): array
    {
        return $this->request('GET', 'Ticket/' . $ticketId);
    }

    /**
     * Lista follow-ups (respostas/interações) vinculados ao chamado.
     */
    public function listarFollowups(int $ticketId): array
    {
        return $this->request('GET', 'Ticket/' . $ticketId . '/ITILFollowup/');
    }

    /**
     * Lista documentos/anexos vinculados ao chamado.
     */
    public function listarDocumentos(int $ticketId): array
    {
        return $this->listarDocumentosDeItem('Ticket', $ticketId);
    }

    public function listarDocumentosDeItem(string $itemtype, int $itemsId): array
    {
        $itemtype = preg_replace('/[^A-Za-z0-9_]/', '', $itemtype) ?: 'Ticket';

        $endpoints = [
            $itemtype . '/' . $itemsId . '/Document_Item/',
            $itemtype . '/' . $itemsId . '/Document/',
        ];

        foreach ($endpoints as $endpoint) {
            try {
                $result = $this->request('GET', $endpoint);
                $items = normalizeDocumentList($this, $result['body']);

                if (!empty($items)) {
                    return ['http_code' => $result['http_code'], 'body' => $items, 'raw' => $result['raw']];
                }
            } catch (Throwable) {
                continue;
            }
        }

        return ['http_code' => 200, 'body' => [], 'raw' => '[]'];
    }

    /**
     * Busca metadados de um documento.
     */
    public function buscarDocumento(int $documentId): array
    {
        return $this->request('GET', 'Document/' . $documentId);
    }

    /**
     * Baixa conteúdo binário de um documento do GLPI.
     */
    public function downloadDocumento(int $documentId): array
    {
        $meta = $this->buscarDocumento($documentId);
        $body = is_array($meta['body']) ? $meta['body'] : [];
        $filename = (string) ($body['filename'] ?? $body['name'] ?? ('anexo-' . $documentId));
        $mime = (string) ($body['mime'] ?? 'application/octet-stream');

        if (!empty($body['content']) && is_string($body['content'])) {
            $decoded = base64_decode($body['content'], true);

            if ($decoded !== false && $decoded !== '') {
                return [
                    'filename' => $filename,
                    'content' => $decoded,
                    'content_type' => $mime,
                ];
            }
        }

        $endpoints = [
            'Document/' . $documentId . '?alt=media',
            $this->buildGlpiWebUrl('front/document.send.php?docid=' . $documentId),
            'Document/' . $documentId,
        ];

        foreach ($endpoints as $endpoint) {
            $isAbsolute = str_starts_with($endpoint, 'http');
            $url = $isAbsolute ? $endpoint : $this->buildUrl($endpoint);
            $ch = curl_init($url);

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => array_merge($this->defaultHeaders(false), [
                    'Accept: application/octet-stream, */*',
                ]),
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => GLPI_TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => 10,
            ]);

            $responseBody = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($responseBody === false || $httpCode >= 400) {
                continue;
            }

            $trimmed = ltrim((string) $responseBody);
            $normalizedContentType = strtolower($contentType);

            if (str_contains($normalizedContentType, 'text/html')) {
                continue;
            }

            if ($trimmed !== '' && $trimmed[0] !== '{' && $trimmed[0] !== '[') {
                return [
                    'filename' => $filename,
                    'content' => $responseBody,
                    'content_type' => $contentType !== '' ? $contentType : $mime,
                ];
            }

            $json = json_decode((string) $responseBody, true);

            if (is_array($json) && !empty($json['content']) && is_string($json['content'])) {
                $decoded = base64_decode($json['content'], true);

                if ($decoded !== false && $decoded !== '') {
                    return [
                        'filename' => (string) ($json['filename'] ?? $filename),
                        'content' => $decoded,
                        'content_type' => (string) ($json['mime'] ?? $mime),
                    ];
                }
            }
        }

        throw new RuntimeException('Documento não disponível para download.', 404);
    }

    /**
     * Anexa documento a um chamado (GLPI 10 — upload + vínculo quando necessário).
     */
    public function uploadDocument(int $itemsId, string $itemtype, string $filePath, string $fileName): array
    {
        if (!is_readable($filePath)) {
            throw new RuntimeException('Arquivo não encontrado para upload.');
        }

        $safeName = basename($fileName);
        $mime = function_exists('mime_content_type')
            ? (mime_content_type($filePath) ?: 'application/octet-stream')
            : 'application/octet-stream';

        // Estratégia 1: upload já vinculado ao ticket (formato oficial GLPI).
        $manifestLinked = json_encode([
            'input' => [
                'name' => $safeName,
                '_filename' => [$safeName],
                'itemtype' => $itemtype,
                'items_id' => $itemsId,
            ],
        ], JSON_UNESCAPED_UNICODE);

        try {
            $createdLinked = $this->requestMultipart('POST', 'Document/', [
                'uploadManifest' => $manifestLinked,
                'filename[0]' => new CURLFile($filePath, $mime, $safeName),
            ]);

            $documentId = extractCreatedDocumentId($createdLinked['body']);

            if ($documentId > 0) {
                $this->ensureDocumentLinked($documentId, $itemsId, $itemtype);
            }

            return $createdLinked;
        } catch (Throwable $linkedError) {
            // Estratégia 2: cria documento e vincula via Document_Item.
            $manifestOnly = json_encode([
                'input' => [
                    'name' => $safeName,
                    '_filename' => [$safeName],
                ],
            ], JSON_UNESCAPED_UNICODE);

            $created = $this->requestMultipart('POST', 'Document/', [
                'uploadManifest' => $manifestOnly,
                'filename[0]' => new CURLFile($filePath, $mime, $safeName),
            ]);

            $documentId = extractCreatedDocumentId($created['body']);

            if ($documentId <= 0) {
                throw $linkedError;
            }

            $this->request('POST', 'Document_Item/', [
                'input' => [
                    'documents_id' => $documentId,
                    'items_id' => $itemsId,
                    'itemtype' => $itemtype,
                ],
            ]);

            return $created;
        }
    }

    public function isDocumentLinkedToItem(int $documentId, int $itemsId, string $itemtype): bool
    {
        $documents = $this->listarDocumentos($itemsId);
        $items = is_array($documents['body']) ? $documents['body'] : [];

        foreach ($items as $item) {
            if ((int) ($item['id'] ?? 0) === $documentId) {
                return true;
            }
        }

        return false;
    }

    private function ensureDocumentLinked(int $documentId, int $itemsId, string $itemtype): void
    {
        try {
            $this->request('POST', 'Document_Item/', [
                'input' => [
                    'documents_id' => $documentId,
                    'items_id' => $itemsId,
                    'itemtype' => $itemtype,
                ],
            ]);
        } catch (Throwable) {
            // O GLPI pode retornar erro quando o upload ja criou esse vinculo.
        }
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
            CURLOPT_POST => true,
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

    private function buildGlpiWebUrl(string $path): string
    {
        $base = preg_replace('#/apirest\.php/?$#', '', $this->baseUrl);

        return rtrim($base ?: $this->baseUrl, '/') . '/' . ltrim($path, '/');
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
    $rawRows = extractGlpiSearchRows($searchBody);
    $rows = [];

    foreach ($rawRows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $ticket = normalizeTicketRow($row);
        if ($ticket['id'] > 0) {
            $rows[] = $ticket;
        }
    }

    return $rows;
}

/**
 * Normaliza resposta do endpoint REST Ticket/ (lista direta).
 */
function normalizeTicketRestList(mixed $body, int $userId = 0): array
{
    if (!is_array($body)) {
        return [];
    }

    $items = array_is_list($body) ? $body : [$body];
    $rows = [];

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $ticket = normalizeTicketFromRestItem($item);

        if ($userId > 0 && $ticket['solicitante_id'] > 0 && $ticket['solicitante_id'] !== $userId) {
            continue;
        }

        if ($ticket['id'] > 0) {
            $rows[] = $ticket;
        }
    }

    return $rows;
}

function extractGlpiSearchRows(array $searchBody): array
{
    if (isset($searchBody['data']) && is_array($searchBody['data'])) {
        if (array_is_list($searchBody['data'])) {
            return $searchBody['data'];
        }

        if (isset($searchBody['data']['data']) && is_array($searchBody['data']['data'])) {
            return $searchBody['data']['data'];
        }
    }

    return [];
}

function normalizeTicketRow(array $row): array
{
    $id = (int) ($row['2'] ?? $row['id'] ?? 0);
    $title = (string) ($row['1'] ?? $row['name'] ?? '');
    $statusRaw = $row['12'] ?? $row['status'] ?? 0;
    $statusCode = is_numeric($statusRaw) ? (int) $statusRaw : mapGlpiStatusLabelToCode((string) $statusRaw);
    $priorityCode = (int) ($row['3'] ?? $row['priority'] ?? 0);
    $urgencyCode = (int) ($row['21'] ?? $row['urgency'] ?? 0);
    $dateOpen = (string) ($row['15'] ?? $row['date'] ?? '');
    $dateMod = (string) ($row['19'] ?? $row['date_mod'] ?? '');

    return buildNormalizedTicket(
        $id,
        $title,
        $statusCode,
        $priorityCode,
        $urgencyCode,
        $dateOpen,
        $dateMod,
        (int) ($row['solicitante_id'] ?? 0)
    );
}

function normalizeTicketFromRestItem(array $item): array
{
    $requester = extractTicketRequesterId($item);

    return buildNormalizedTicket(
        (int) ($item['id'] ?? 0),
        (string) ($item['name'] ?? ''),
        (int) ($item['status'] ?? 0),
        (int) ($item['priority'] ?? 0),
        (int) ($item['urgency'] ?? 0),
        (string) ($item['date'] ?? ''),
        (string) ($item['date_mod'] ?? ''),
        $requester
    );
}

function buildNormalizedTicket(
    int $id,
    string $title,
    int $statusCode,
    int $priorityCode,
    int $urgencyCode,
    string $dateOpen,
    string $dateMod,
    int $requesterId = 0
): array {
    $dateReference = $dateMod !== '' ? $dateMod : $dateOpen;

    return [
        'id' => $id,
        'titulo' => $title !== '' ? $title : 'Sem título',
        'status' => mapGlpiStatusLabel($statusCode),
        'status_code' => $statusCode,
        'status_grupo' => mapGlpiStatusGroup($statusCode),
        'prioridade' => mapGlpiPriorityLabel($priorityCode, $urgencyCode),
        'data_abertura' => $dateOpen,
        'data_atualizacao' => $dateReference,
        'data_label' => formatGlpiDateLabel($dateReference),
        'urgencia' => (string) $urgencyCode,
        'solicitante_id' => $requesterId,
    ];
}

function extractTicketRequesterId(array $ticket): int
{
    if (isset($ticket['_users_id_requester'])) {
        $requester = $ticket['_users_id_requester'];
        return is_array($requester) ? (int) ($requester['id'] ?? 0) : (int) $requester;
    }

    if (isset($ticket['_actors']['requester'][0]['items_id'])) {
        return (int) $ticket['_actors']['requester'][0]['items_id'];
    }

    return 0;
}

function mapGlpiStatusLabel(int $status): string
{
    return match ($status) {
        1 => 'Aberto',
        2, 3 => 'Em andamento',
        4 => 'Pendente',
        5 => 'Resolvido',
        6 => 'Fechado',
        default => 'Aberto',
    };
}

function mapGlpiStatusLabelToCode(string $label): int
{
    $normalized = mb_strtolower(trim($label));

    return match (true) {
        str_contains($normalized, 'fech') => 6,
        str_contains($normalized, 'resolv') => 5,
        str_contains($normalized, 'pend') => 4,
        str_contains($normalized, 'andamento'), str_contains($normalized, 'process') => 2,
        default => 1,
    };
}

function mapGlpiStatusGroup(int $status): string
{
    return match ($status) {
        1, 4 => 'abertos',
        2, 3 => 'andamento',
        5, 6 => 'resolvidos',
        default => 'abertos',
    };
}

function mapGlpiUrgencyLabel(int $urgency): string
{
    return match ($urgency) {
        1, 2 => 'Baixa',
        3 => 'Média',
        4, 5 => 'Alta',
        default => 'Média',
    };
}

function mapGlpiPriorityLabel(int $priority, int $urgency): string
{
    if ($priority > 0) {
        return match ($priority) {
            1, 2 => 'Baixa',
            3 => 'Média',
            4, 5, 6 => 'Alta',
            default => mapGlpiUrgencyLabel($urgency),
        };
    }

    return mapGlpiUrgencyLabel($urgency);
}

function formatGlpiDateLabel(string $dateValue): string
{
    if ($dateValue === '') {
        return '-';
    }

    $timestamp = strtotime($dateValue);

    if ($timestamp === false) {
        return $dateValue;
    }

    $diff = time() - $timestamp;

    if ($diff < 3600) {
        $mins = max(1, (int) floor($diff / 60));
        return 'Atualizado há ' . $mins . ' min';
    }

    if ($diff < 86400) {
        $hours = max(1, (int) floor($diff / 3600));
        return 'Atualizado há ' . $hours . ' h';
    }

    if ($diff < 604800) {
        $days = max(1, (int) floor($diff / 86400));
        return 'Atualizado há ' . $days . ' dia' . ($days > 1 ? 's' : '');
    }

    return date('d/m/Y H:i', $timestamp);
}

/**
 * Normaliza follow-ups do GLPI para exibição no portal.
 */
function normalizeFollowupList(mixed $body, ?GlpiService $glpi = null): array
{
    $items = [];

    if (!is_array($body)) {
        return $items;
    }

    $list = isset($body['data']) && is_array($body['data']) ? $body['data'] : $body;

    if (!is_array($list)) {
        return $items;
    }

    foreach ($list as $followup) {
        if (!is_array($followup)) {
            continue;
        }

        $followupId = (int) ($followup['id'] ?? 0);
        $rawContent = (string) ($followup['content'] ?? '');
        $decodedContent = decodeGlpiHtmlContent($rawContent);
        $images = extractFollowupImages($decodedContent);
        $attachments = extractFollowupLinks($decodedContent);

        if ($glpi !== null && $followupId > 0) {
            $followupDocuments = getFollowupDocuments($glpi, $followupId);
            $attachments = mergeDocumentItems($attachments, $followupDocuments['attachments']);
            $images = array_merge($images, $followupDocuments['images']);
        }

        $content = normalizeGlpiTextContent($decodedContent);

        if ($content === '' && empty($images) && empty($attachments)) {
            continue;
        }

        $author = $followup['users_id'] ?? 'Equipe TVF';
        if (is_numeric($author)) {
            $author = 'Equipe TVF';
        }

        $date = (string) ($followup['date'] ?? $followup['date_mod'] ?? '');

        $items[] = [
            'autor' => (string) $author,
            'mensagem' => $content,
            'imagens' => $images,
            'anexos' => $attachments,
            'data' => formatGlpiDateLabel($date),
            'data_raw' => $date,
        ];
    }

    usort($items, static function (array $a, array $b): int {
        return strcmp($a['data_raw'] ?? '', $b['data_raw'] ?? '');
    });

    return $items;
}

function decodeGlpiHtmlContent(string $content): string
{
    $decoded = $content;

    for ($i = 0; $i < 2; $i += 1) {
        $next = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if ($next === $decoded) {
            break;
        }

        $decoded = $next;
    }

    return $decoded;
}

function normalizeGlpiTextContent(string $content): string
{
    $content = preg_replace('#<\s*br\s*/?\s*>#i', "\n", $content) ?? $content;
    $content = preg_replace('#</\s*p\s*>#i', "\n", $content) ?? $content;
    $content = preg_replace('#<\s*p(?:\s[^>]*)?>#i', '', $content) ?? $content;
    $content = strip_tags($content);
    $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $content = preg_replace("/[ \t]+\n/", "\n", $content) ?? $content;
    $content = preg_replace("/\n{3,}/", "\n\n", $content) ?? $content;

    return trim($content);
}

function extractFollowupImages(string $content): array
{
    $images = [];

    if (!preg_match_all('#<img\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1[^>]*>#i', $content, $matches, PREG_SET_ORDER)) {
        return $images;
    }

    foreach ($matches as $match) {
        $src = html_entity_decode((string) ($match[2] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $url = normalizeGlpiImageUrl($src);

        if ($url === '') {
            continue;
        }

        $images[] = [
            'url' => $url,
            'nome' => 'Imagem da resposta',
        ];
    }

    return $images;
}

function normalizeGlpiImageUrl(string $src): string
{
    $src = trim($src);

    if ($src === '') {
        return '';
    }

    if (str_starts_with($src, 'data:image/')) {
        return $src;
    }

    $query = parse_url($src, PHP_URL_QUERY);
    parse_str(is_string($query) ? $query : '', $params);
    $documentId = (int) ($params['docid'] ?? $params['id'] ?? 0);

    if ($documentId > 0) {
        return '../api/documento.php?id=' . $documentId;
    }

    return '';
}

function extractFollowupLinks(string $content): array
{
    $attachments = [];

    if (!preg_match_all('#<a\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)</a>#is', $content, $matches, PREG_SET_ORDER)) {
        return $attachments;
    }

    foreach ($matches as $match) {
        $href = html_entity_decode((string) ($match[2] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $label = trim(strip_tags(decodeGlpiHtmlContent((string) ($match[3] ?? ''))));
        $documentId = extractDocumentIdFromUrl($href);

        if ($documentId <= 0) {
            continue;
        }

        $attachments[] = [
            'id' => $documentId,
            'nome' => $label !== '' ? $label : ('Anexo #' . $documentId),
            'download_url' => '../api/documento.php?id=' . $documentId,
        ];
    }

    return $attachments;
}

function extractDocumentIdFromUrl(string $url): int
{
    $query = parse_url($url, PHP_URL_QUERY);
    parse_str(is_string($query) ? $query : '', $params);

    return (int) ($params['docid'] ?? $params['id'] ?? 0);
}

function mergeDocumentItems(array $base, array $extra): array
{
    $merged = [];
    $seen = [];

    foreach (array_merge($base, $extra) as $item) {
        if (!is_array($item)) {
            continue;
        }

        $id = (int) ($item['id'] ?? 0);

        if ($id <= 0 || isset($seen[$id])) {
            continue;
        }

        $seen[$id] = true;
        $merged[] = $item;
    }

    return $merged;
}

function getFollowupDocuments(GlpiService $glpi, int $followupId): array
{
    $result = [
        'attachments' => [],
        'images' => [],
    ];

    try {
        $documents = $glpi->listarDocumentosDeItem('ITILFollowup', $followupId);
        $items = is_array($documents['body']) ? $documents['body'] : [];
    } catch (Throwable) {
        return $result;
    }

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $id = (int) ($item['id'] ?? 0);
        $name = (string) ($item['nome'] ?? ('Anexo #' . $id));
        $url = (string) ($item['download_url'] ?? ('../api/documento.php?id=' . $id));

        if ($id <= 0) {
            continue;
        }

        if (isImageDocumentName($name)) {
            $result['images'][] = [
                'url' => $url,
                'nome' => $name,
            ];
            continue;
        }

        $result['attachments'][] = [
            'id' => $id,
            'nome' => $name,
            'download_url' => $url,
        ];
    }

    return $result;
}

function isImageDocumentName(string $name): bool
{
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true);
}

/**
 * Normaliza ticket individual retornado pelo GLPI.
 */
function normalizeTicketDetail(array $ticket): array
{
    $statusCode = (int) ($ticket['status'] ?? 0);
    $urgencyCode = (int) ($ticket['urgency'] ?? 0);
    $priorityCode = (int) ($ticket['priority'] ?? 0);
    $requesterId = extractTicketRequesterId($ticket);

    return [
        'id' => (int) ($ticket['id'] ?? 0),
        'titulo' => (string) ($ticket['name'] ?? ''),
        'descricao' => strip_tags((string) ($ticket['content'] ?? '')),
        'status' => mapGlpiStatusLabel($statusCode),
        'status_code' => $statusCode,
        'status_grupo' => mapGlpiStatusGroup($statusCode),
        'urgencia' => $urgencyCode,
        'urgencia_label' => mapGlpiUrgencyLabel($urgencyCode),
        'prioridade' => mapGlpiPriorityLabel($priorityCode, $urgencyCode),
        'tipo' => (int) ($ticket['type'] ?? 0),
        'data_abertura' => (string) ($ticket['date'] ?? ''),
        'data_modificacao' => (string) ($ticket['date_mod'] ?? ''),
        'data_label' => formatGlpiDateLabel((string) ($ticket['date_mod'] ?? $ticket['date'] ?? '')),
        'solicitante_id' => $requesterId,
        'respostas' => [],
        'anexos' => [],
    ];
}

function buildTicketRequesterInput(int $userId): array
{
    if ($userId <= 0) {
        return [];
    }

    return [
        '_users_id_requester' => $userId,
        '_actors' => [
            'requester' => [
                [
                    'itemtype' => 'User',
                    'items_id' => $userId,
                    'use_notification' => 1,
                ],
            ],
        ],
    ];
}

/**
 * Ordena chamados pelo número (ID) — padrão: maior número primeiro.
 */
function sortTicketsById(array $tickets, string $direction = 'DESC'): array
{
    usort($tickets, static function (array $a, array $b) use ($direction): int {
        $compare = (int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0);

        return $direction === 'DESC' ? -$compare : $compare;
    });

    return $tickets;
}

function extractCreatedDocumentId(mixed $body): int
{
    if (!is_array($body)) {
        return 0;
    }

    if (isset($body['id'])) {
        return is_array($body['id']) ? (int) ($body['id'][0] ?? 0) : (int) $body['id'];
    }

    if (isset($body[0]) && is_array($body[0])) {
        return extractCreatedDocumentId($body[0]);
    }

    if (isset($body['data']) && is_array($body['data'])) {
        return extractCreatedDocumentId($body['data']);
    }

    return 0;
}

/**
 * Normaliza lista de documentos vinculados a um chamado.
 */
function normalizeDocumentList(GlpiService $glpi, mixed $body): array
{
    if (!is_array($body)) {
        return [];
    }

    $list = isset($body['data']) && is_array($body['data']) ? $body['data'] : $body;

    if (!is_array($list)) {
        return [];
    }

    if (!array_is_list($list)) {
        $list = [$list];
    }

    $documents = [];
    $seen = [];

    foreach ($list as $item) {
        if (!is_array($item)) {
            continue;
        }

        $documentId = (int) ($item['documents_id'] ?? $item['id'] ?? 0);
        $name = (string) ($item['name'] ?? $item['filename'] ?? '');

        if ($documentId <= 0) {
            continue;
        }

        if (isset($seen[$documentId])) {
            continue;
        }

        if ($name === '') {
            try {
                $meta = $glpi->buscarDocumento($documentId);
                $metaBody = is_array($meta['body']) ? $meta['body'] : [];
                $name = (string) ($metaBody['filename'] ?? $metaBody['name'] ?? ('Anexo #' . $documentId));
            } catch (Throwable) {
                $name = 'Anexo #' . $documentId;
            }
        }

        $seen[$documentId] = true;
        $documents[] = [
            'id' => $documentId,
            'nome' => $name,
            'download_url' => '../api/documento.php?id=' . $documentId,
        ];
    }

    return $documents;
}

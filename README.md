# Portal de Chamados TVF — Integração GLPI 10

Portal web para abertura e acompanhamento de chamados de suporte, com **frontend estático** (HTML/CSS/JS) publicado no **IIS** e **backend PHP 8.2** intermediando a comunicação com a **API REST do GLPI 10** (interno, via XAMPP).

## Arquitetura

```text
Navegador (Frontend IIS)
        │
        ▼ fetch() JSON / multipart
Backend PHP (api/*.php)
        │
        ▼ cURL (rede local)
GLPI 10 API REST (XAMPP — interno)
```

O frontend **nunca** acessa tokens ou a API do GLPI diretamente.

## Tecnologias

| Camada    | Tecnologia                          |
|-----------|-------------------------------------|
| Frontend  | HTML5, CSS3, JavaScript (Vanilla)   |
| Backend   | PHP 8.2 (puro, sem framework)       |
| Servidor  | IIS                                 |
| GLPI      | GLPI 10 — API REST (`apirest.php`)  |

## Estrutura de pastas

```text
Extensao-5/
├── assets/
│   ├── js/
│   │   └── api-client.js      # Cliente fetch compartilhado
│   └── logo.png
├── api/
│   ├── config/
│   │   ├── config.example.php # Modelo de configuração
│   │   └── config.php         # Config local (não versionar)
│   ├── helpers/
│   │   └── response.php       # Respostas JSON padronizadas
│   ├── services/
│   │   ├── auth.php           # Sessão PHP do portal
│   │   └── glpi.php           # Cliente cURL GLPI
│   ├── login.php
│   ├── logout.php
│   ├── abrir-chamado.php
│   ├── listar-chamados.php
│   ├── detalhes-chamado.php
│   └── session.php            # Verifica sessão ativa
├── login/                     # Tela de login (frontend existente)
├── chamados/                  # Abertura de chamado (frontend existente)
├── views/
│   ├── login.html             # Redireciona para login/
│   ├── chamados.html          # Acompanhamento de chamados (tabela + filtros)
│   ├── chamados-list.js       # Lógica da listagem e filtros
│   ├── acompanhamento.css     # Estilos da tela de acompanhamento/detalhe
│   ├── detalhe.html           # Detalhes do chamado + histórico
│   └── detalhe.js
├── web.config                 # IIS — documento padrão
├── .gitignore
└── README.md
```

## Configuração

### 1. GLPI — API REST

No GLPI (Configuração → Geral → API):

1. Ativar a API REST.
2. Gerar **App-Token** (cliente API).
3. Garantir que o usuário do portal possui permissão para criar/visualizar tickets.

URL típica no XAMPP:

`http://localhost:8080/apirest.php`

### 2. Tokens e URL (`api/config/config.php`)

Copie o exemplo:

```bash
copy api\config\config.example.php api\config\config.php
```

Edite `api/config/config.php`:

```php
define('GLPI_URL', 'http://localhost:8080/apirest.php');
define('APP_TOKEN', 'SEU_APP_TOKEN');
define('USER_TOKEN', ''); // opcional — usado apenas se não houver sessão de usuário
```

> **Segurança:** não commite `config.php` com tokens reais. O arquivo está no `.gitignore`.

### 3. IIS

1. Instale **PHP 8.2** (FastCGI) no Windows.
2. Crie um site/aplicação apontando para a pasta do projeto.
3. Confirme que arquivos `.php` em `/api` são executados pelo PHP (não servidos como texto).
4. Ajuste `web.config` se necessário (documento padrão: `login/index.html`).

### 4. PHP (`php.ini`)

Recomendado para upload de anexos (até 40 MB no portal):

```ini
upload_max_filesize = 40M
post_max_size = 45M
max_execution_time = 120
extension=curl
extension=fileinfo
```

## Como executar

1. Suba o **XAMPP** com GLPI acessível em `GLPI_URL`.
2. Configure `api/config/config.php`.
3. Publique o projeto no **IIS**.
4. Acesse: `http://seu-servidor/login/index.html`
5. Após login → abertura em `chamados/index.html` → listagem em `views/chamados.html`.

## Endpoints da API

Todos retornam JSON no padrão:

```json
{ "status": true|false, "message": "...", "data": {} }
```

| Endpoint               | Método   | Autenticação | Descrição                          |
|------------------------|----------|--------------|------------------------------------|
| `api/login.php`        | POST     | Não          | Login (usuário/senha GLPI)         |
| `api/logout.php`       | POST/GET | Opcional     | Encerra sessão portal + GLPI       |
| `api/session.php`      | GET      | Sim          | Verifica sessão ativa              |
| `api/abrir-chamado.php`| POST     | Sim          | Cria ticket + anexos opcionais     |
| `api/listar-chamados.php` | GET/POST | Sim       | Lista chamados do usuário logado   |
| `api/detalhes-chamado.php` | GET/POST | Sim      | Detalhes de um chamado por ID      |

### Login — `POST api/login.php`

```json
{ "login": "usuario_glpi", "password": "senha" }
```

### Abrir chamado — `POST api/abrir-chamado.php`

`multipart/form-data` ou JSON:

| Campo          | Obrigatório | Descrição                                      |
|----------------|-------------|------------------------------------------------|
| `descricao`    | Sim         | Texto do chamado                               |
| `titulo`       | Não         | Título (gerado automaticamente se vazio)       |
| `ticket_type`  | Não         | `incidente` \| `requisicao`                    |
| `urgency`      | Não         | `muito_baixa` … `muito_alta`                   |
| `attachments[]`| Não         | Arquivos (total máx. 40 MB)                    |

### Listar — `GET api/listar-chamados.php`

Retorna `data.tickets[]` com:

| Campo | Descrição |
|-------|-----------|
| `id` | Número do chamado |
| `titulo` | Assunto |
| `status` | Label legível (Aberto, Em andamento, Resolvido…) |
| `status_grupo` | `abertos` \| `andamento` \| `resolvidos` (para filtros) |
| `prioridade` | Alta, Média ou Baixa |
| `data_label` | Texto relativo (ex.: "Atualizado há 10 min") |
| `data_abertura` / `data_atualizacao` | Datas brutas do GLPI |

### Detalhes — `GET api/detalhes-chamado.php?id=123`

Retorna `data.ticket` com campos normalizados e, quando disponível no GLPI, `respostas[]`:

```json
{
  "autor": "Equipe TVF",
  "mensagem": "Estamos verificando o problema.",
  "data": "Atualizado há 10 min"
}
```

Valida se o solicitante é o usuário logado.

## Fluxo de autenticação

1. Frontend envia login/senha para `api/login.php`.
2. PHP chama `initSession` no GLPI (Basic Auth + App-Token).
3. PHP obtém `getFullSession` e grava `Session-Token` + `glpi_user_id` na **sessão PHP**.
4. Demais endpoints usam o token GLPI armazenado no servidor.
5. `logout.php` chama `killSession` no GLPI e destrói a sessão PHP.

## Integração frontend (sem alterar visual)

- `login/script.js` + `login/login-auth.js` → `POST api/login.php`
- `chamados/script.js` → verifica `session.php`, envia `abrir-chamado.php` via `FormData`
- `views/chamados-list.js` → `GET session.php` + `GET listar-chamados.php`
- `views/detalhe.js` → `GET detalhes-chamado.php?id=`

Cliente compartilhado: `assets/js/api-client.js` (`window.PortalApi`).

## Tela de acompanhamento (`views/chamados.html`)

### Arquivos alterados/criados

| Arquivo | Função |
|---------|--------|
| `views/chamados.html` | Layout da tela (header, cards, tabela, botões) |
| `views/chamados-list.js` | Filtros, paginação, renderização, API |
| `views/acompanhamento.css` | Estilos específicos (tabela, badges, cards) |
| `views/detalhe.html` | Layout de detalhes + histórico |
| `views/detalhe.js` | Carrega detalhe e renderiza respostas |
| `api/services/glpi.php` | Normalização de status, prioridade e follow-ups |

### Como funciona

1. Usuário autenticado acessa `views/chamados.html`.
2. `chamados-list.js` chama `session.php` para exibir **"Olá, Nome!"**.
3. Em seguida chama `listar-chamados.php` e preenche a tabela.
4. Cards de resumo contam chamados por grupo de status.
5. Botão **Recarregar status** repete a listagem.
6. Botão **+ Novo Chamado** leva para `chamados/index.html`.

### Filtros por status

| Card | Grupo (`status_grupo`) |
|------|------------------------|
| Abertos | `abertos` (novo, pendente) |
| Em andamento | `andamento` |
| Resolvidos | `resolvidos` (resolvido, fechado) |
| Total | todos os chamados |

Ao clicar em um card, a tabela é filtrada no frontend (sem nova chamada à API).

### Botão "Ver"

Cada linha possui link para `detalhe.html?id={id}`. A tela de detalhe exibe:

- Número, assunto, descrição, status, prioridade, data de abertura
- Histórico de respostas (`ticket.respostas[]`) quando o GLPI retorna follow-ups

### Responsividade

- **Desktop:** tabela completa com 6 colunas.
- **Mobile:** tabela oculta; cada chamado vira um card (`tickets-mobile-list`).

### O que ainda pode evoluir

- Nome real do técnico no histórico (hoje exibe "Equipe TVF" quando o GLPI retorna apenas ID numérico).
- Paginação server-side quando houver muitos chamados (>50).
- Filtro por busca textual no assunto.

## Mapeamento GLPI

| Formulário     | GLPI Ticket field   |
|----------------|---------------------|
| `incidente`    | `type = 1`          |
| `requisicao`   | `type = 2`          |
| Urgência 1–5   | `urgency` / `priority` |
| Descrição      | `content`           |
| Título         | `name`              |

> O campo de busca do solicitante na listagem usa critério `field=4` (padrão GLPI). Se sua instalação usar outro ID de campo na busca, ajuste em `api/services/glpi.php` → `listarChamados()`.

## Manutenção e evolução

- Reforçar validação de **40 MB** e tipos de arquivo no **backend**.
- Adicionar barra de progresso de upload no frontend.
- Implementar refresh token / renovação de sessão GLPI.
- Logs estruturados em arquivo (sem expor tokens).

## Documentação adicional

- `chamados/DOCUMENTACAO_CHAMADOS.md` — detalhes da tela de abertura (UI).

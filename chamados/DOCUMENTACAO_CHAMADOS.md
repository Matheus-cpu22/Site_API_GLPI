# Documentação — Tela de Abertura de Chamados

## Visão geral

Foi implementada uma tela completa de abertura de chamados na pasta `chamados`, com foco em:

- Alta fidelidade visual em relação à referência fornecida
- Identidade futurista/tecnológica (tons azul/ciano, glow, background dinâmico)
- Responsividade real para desktop, notebook, tablet e mobile
- Estrutura limpa e escalável para evolução futura

## Estrutura criada

```text
chamados/
  index.html
  style.css
  script.js
  DOCUMENTACAO_CHAMADOS.md
```

### Responsabilidade de cada arquivo

- `index.html`: estrutura semântica da tela (topo, painel esquerdo, formulário e rodapé).
- `style.css`: design system local (tokens de cor/spacing/raio/sombra), layout responsivo, glassmorphism, glow e estados de interação.
- `script.js`: efeito visual de rede tecnológica no background via canvas e controle de animação com `prefers-reduced-motion`.

## O que foi implementado

### 1) Topo / Hero

- Bloco superior com:
  - Logo/identidade da empresa no canto esquerdo
  - Título central “Central de Atendimento TVF”
  - Subtítulo em uppercase com tracking controlado
  - Contatos no canto direito (telefone e e-mail)
- Moldura com borda iluminada e sombra sutil para reforçar estética premium.

### 2) Lado esquerdo (benefícios)

- Headline principal com palavra-chave em neon (`rapidez e confiança`).
- Texto complementar orientando o usuário sobre retorno do suporte.
- Lista de benefícios com ícones modernos inline (SVG), incluindo:
  - Atendimento organizado
  - Histórico de solicitações
  - Acompanhamento em tempo real
  - Equipe especializada
- Card inferior “Já abriu um chamado?” com CTA “Acompanhe aqui”.

### 3) Lado direito (formulário)

Formulário completo com:

- Nome
- E-mail
- Departamento (select)
- Assunto
- Descrição (textarea)
- Upload de arquivos (múltiplos anexos)
- Botão principal de envio

#### Estilo e UX do formulário

- Fundo escuro translúcido (glassmorphism)
- Bordas suaves com iluminação ciano
- Hover/focus states elegantes
- Botão com gradiente neon e glow
- Hierarquia visual clara e leitura confortável
- Estrutura pensada para validações e integração futura com backend

## Background tecnológico

- Camadas combinadas para alto nível de fidelidade visual:
  - Gradientes radiais e lineares escuros
  - Grid tecnológico suave
  - Glows posicionados estrategicamente
  - Rede animada de conexões (canvas com partículas)
- A animação respeita acessibilidade com `prefers-reduced-motion`.

## Responsividade aplicada

Foram definidos breakpoints para garantir adaptação entre resoluções amplas e telas pequenas:

- `<= 1080px`: empilhamento do conteúdo principal e ajuste de header
- `<= 760px`: grid do formulário vira coluna única, tipografia ajustada
- `<= 460px`: redução de paddings/radius/letter-spacing para preservar proporção e legibilidade

### Técnicas usadas para evitar problemas de layout

- `clamp()` em tamanhos de fonte e espaçamento
- `minmax()` e `grid` para colunas flexíveis
- `max-width` controlado no container principal
- Sem alturas fixas rígidas em blocos críticos
- Controle de overflow horizontal

## Decisões técnicas

- Mantido stack atual do projeto (HTML/CSS/JS puro) para consistência.
- Sem dependências externas adicionais (evita acoplamento desnecessário).
- Ícones SVG inline para performance e controle visual fino.
- Tokens CSS centralizados (`:root`) para facilitar manutenção e evolução.

## Acessibilidade básica aplicada

- Estrutura semântica com `header`, `section`, `aside`, `footer`.
- Labels explícitos em todos os campos do formulário.
- `focus-visible` visível em inputs e botão.
- Suporte a redução de movimento (`prefers-reduced-motion`).

## Como a tela funciona

1. Usuário acessa `chamados/index.html`.
2. Visualiza informações institucionais e benefícios do suporte.
3. Preenche formulário de abertura de chamado.
4. Faz upload opcional de arquivos.
5. Envia solicitação (atualmente com `preventDefault` para integração futura).

## Dependências utilizadas

- Google Fonts (família `Inter`)
- Recursos nativos do navegador (SVG inline, CSS moderno, Canvas API)

Não foram adicionadas bibliotecas JS/CSS extras.

## Melhorias futuras planejadas

- Integração com endpoint real de criação de chamado
- Validação de formulário robusta (frontend + backend)
- Upload com barra de progresso e validação de tipo/tamanho de arquivo
- Máscaras e validações adicionais (telefone, domínio de e-mail corporativo)
- Internacionalização (i18n), caso necessário
- Testes E2E de fluxo principal e regressão visual

## Próximos passos recomendados para evolução

1. Definir contrato de API (`payload`, `schema`, códigos de erro).
2. Implementar tratamento de erros e feedback visual ao usuário.
3. Criar tela de acompanhamento do chamado integrada ao card “Acompanhe aqui”.
4. Adicionar telemetria básica de eventos de envio/falha para observabilidade.

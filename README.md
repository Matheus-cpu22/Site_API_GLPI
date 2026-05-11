# Extensao-5

## Nova funcionalidade: Abertura de Chamados

Foi adicionada uma nova tela profissional de abertura de chamados para central de suporte de TI, com foco em fidelidade visual premium (estilo futurista/tecnológico), organização da interface e experiência moderna.

### Objetivo da tela

Permitir que usuários registrem chamados de suporte com clareza e agilidade, incluindo:

- formulário completo de solicitação
- seleção de departamento
- descrição detalhada do problema
- upload de anexos
- comunicação visual de segurança e tempo de resposta

### Estrutura criada

```text
chamados/
  index.html
  style.css
  script.js
  DOCUMENTACAO_CHAMADOS.md
```

### Tecnologias utilizadas

- HTML5 semântico
- CSS3 moderno (glassmorphism, glow, grid/flex, clamp, minmax, media queries)
- JavaScript Vanilla (Canvas API para background tecnológico animado)
- SVG inline para ícones
- Google Fonts (Inter)

### Organização da pasta `chamados`

- `index.html`: composição estrutural da página (topo, conteúdo, formulário e rodapé)
- `style.css`: identidade visual, tokens, responsividade e efeitos
- `script.js`: animação de rede tecnológica no background + controle de acessibilidade para movimento reduzido
- `DOCUMENTACAO_CHAMADOS.md`: documentação técnica detalhada da implementação
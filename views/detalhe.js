(function () {
  "use strict";

  var feedbackEl = document.getElementById("detail-feedback");
  var contentEl = document.getElementById("ticket-detail-content");

  function setFeedback(message) {
    if (!feedbackEl) return;
    feedbackEl.textContent = message || "";
    feedbackEl.hidden = !message;
  }

  function getTicketIdFromQuery() {
    var params = new URLSearchParams(window.location.search);
    return parseInt(params.get("id") || "0", 10);
  }

  function setText(id, value) {
    var el = document.getElementById(id);
    if (el) el.textContent = value;
  }

  var ticketId = getTicketIdFromQuery();

  if (!ticketId) {
    setFeedback("ID do chamado inválido.");
    return;
  }

  if (!window.PortalApi) {
    setFeedback("Cliente de API não carregado.");
    return;
  }

  window.PortalApi.get("detalhes-chamado.php?id=" + encodeURIComponent(ticketId))
    .then(function (result) {
      if (result.status === 401) {
        window.location.href = "../login/index.html";
        return;
      }

      if (!result.ok || !result.payload.status) {
        setFeedback(result.payload.message || "Não foi possível carregar o chamado.");
        return;
      }

      var ticket = result.payload.data && result.payload.data.ticket;
      if (!ticket) {
        setFeedback("Chamado não encontrado.");
        return;
      }

      setText("detail-id", String(ticket.id));
      setText("detail-title", ticket.titulo || "-");
      setText("detail-description", ticket.descricao || "-");
      setText("detail-status", String(ticket.status));
      setText("detail-urgency", String(ticket.urgencia));
      setText("detail-date", ticket.data_abertura || "-");

      if (contentEl) contentEl.hidden = false;
    })
    .catch(function () {
      setFeedback("Erro de comunicação com o servidor.");
    });
})();

(function () {
  "use strict";

  var feedbackEl = document.getElementById("detail-feedback");
  var contentEl = document.getElementById("ticket-detail-content");
  var badgesEl = document.getElementById("detail-badges");
  var historyList = document.getElementById("detail-history-list");
  var historyEmpty = document.getElementById("detail-history-empty");

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

  function getStatusBadgeClass(statusLabel) {
    var label = (statusLabel || "").toLowerCase();

    if (label.indexOf("resolv") !== -1 || label.indexOf("fechad") !== -1) {
      return "badge--resolvido";
    }

    if (label.indexOf("andamento") !== -1) return "badge--andamento";
    if (label.indexOf("pendent") !== -1) return "badge--pendente";

    return "badge--aberto";
  }

  function getPriorityBadgeClass(priorityLabel) {
    var label = (priorityLabel || "").toLowerCase();

    if (label.indexOf("alta") !== -1) return "badge--prioridade-alta";
    if (label.indexOf("média") !== -1 || label.indexOf("media") !== -1) {
      return "badge--prioridade-media";
    }

    return "badge--prioridade-baixa";
  }

  function renderBadges(ticket) {
    if (!badgesEl) return;

    badgesEl.innerHTML =
      '<span class="badge ' +
      getStatusBadgeClass(ticket.status) +
      '">' +
      (ticket.status || "-") +
      "</span>" +
      '<span class="badge ' +
      getPriorityBadgeClass(ticket.prioridade) +
      '">Prioridade: ' +
      (ticket.prioridade || ticket.urgencia_label || "Média") +
      "</span>";
  }

  /**
   * Renderiza array de respostas retornado pela API.
   * Formato esperado: { autor, mensagem, data }
   */
  function renderHistory(respostas) {
    if (!historyList) return;

    historyList.innerHTML = "";

    if (!respostas || !respostas.length) {
      if (historyEmpty) historyEmpty.hidden = false;
      return;
    }

    if (historyEmpty) historyEmpty.hidden = true;

    respostas.forEach(function (item) {
      var li = document.createElement("li");
      li.className = "history-item";
      li.innerHTML =
        '<div class="history-item__head">' +
        '<span class="history-item__author">' +
        (item.autor || "Equipe TVF") +
        "</span>" +
        '<span class="history-item__date">' +
        (item.data || "-") +
        "</span>" +
        "</div>" +
        '<p class="history-item__message">' +
        (item.mensagem || "") +
        "</p>";
      historyList.appendChild(li);
    });
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

  setFeedback("Carregando detalhes...");

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

      setFeedback("");
      setText("detail-id", "#" + ticket.id);
      setText("detail-title", ticket.titulo || "-");
      setText("detail-description", ticket.descricao || "-");
      setText("detail-date", ticket.data_abertura || "-");

      renderBadges(ticket);
      renderHistory(ticket.respostas || []);

      if (contentEl) contentEl.hidden = false;
    })
    .catch(function () {
      setFeedback("Erro de comunicação com o servidor.");
    });
})();

(function () {
  "use strict";

  var feedbackEl = document.getElementById("detail-feedback");
  var contentEl = document.getElementById("ticket-detail-content");
  var badgesEl = document.getElementById("detail-badges");
  var historyList = document.getElementById("detail-history-list");
  var historyEmpty = document.getElementById("detail-history-empty");
  var attachmentsList = document.getElementById("detail-attachments-list");
  var attachmentsEmpty = document.getElementById("detail-attachments-empty");

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

  function appendText(parent, className, value) {
    var el = document.createElement("p");
    el.className = className;
    el.textContent = value || "";
    parent.appendChild(el);
  }

  /**
   * Renderiza array de respostas retornado pela API.
   * Formato esperado: { autor, mensagem, data, imagens[] }
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
      var head = document.createElement("div");
      var author = document.createElement("span");
      var date = document.createElement("span");

      li.className = "history-item";
      head.className = "history-item__head";
      author.className = "history-item__author";
      date.className = "history-item__date";
      author.textContent = item.autor || "Equipe TVF";
      date.textContent = item.data || "-";
      head.appendChild(author);
      head.appendChild(date);
      li.appendChild(head);

      if (item.mensagem) {
        appendText(li, "history-item__message", item.mensagem);
      }

      if (item.imagens && item.imagens.length) {
        var media = document.createElement("div");
        media.className = "history-item__media";

        item.imagens.forEach(function (image) {
          var img = document.createElement("img");
          img.src = image.url;
          img.alt = image.nome || "Imagem da resposta";
          img.loading = "lazy";
          media.appendChild(img);
        });

        li.appendChild(media);
      }

      historyList.appendChild(li);
    });
  }

  function renderAttachments(anexos) {
    if (!attachmentsList) return;

    attachmentsList.innerHTML = "";

    if (!anexos || !anexos.length) {
      if (attachmentsEmpty) attachmentsEmpty.hidden = false;
      return;
    }

    if (attachmentsEmpty) attachmentsEmpty.hidden = true;

    anexos.forEach(function (item) {
      var li = document.createElement("li");
      var link = document.createElement("a");

      link.className = "attachment-link";
      link.href = item.download_url || "../api/documento.php?id=" + encodeURIComponent(item.id);
      link.textContent = item.nome || "Anexo #" + item.id;
      link.target = "_blank";
      link.rel = "noopener noreferrer";

      li.appendChild(link);
      attachmentsList.appendChild(li);
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
      renderAttachments(ticket.anexos || []);
      renderHistory(ticket.respostas || []);

      if (contentEl) contentEl.hidden = false;
    })
    .catch(function () {
      setFeedback("Erro de comunicação com o servidor.");
    });
})();

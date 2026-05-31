(function () {
  "use strict";

  var PAGE_SIZE = 6;
  var state = {
    allTickets: [],
    activeFilter: "abertos",
    currentPage: 1,
  };

  var tableBody = document.getElementById("ticket-table-body");
  var mobileList = document.getElementById("ticket-mobile-list");
  var feedbackEl = document.getElementById("list-feedback");
  var emptyEl = document.getElementById("list-empty");
  var logoutLink = document.getElementById("logout-link");
  var reloadBtn = document.getElementById("reload-btn");
  var userGreeting = document.getElementById("user-greeting");
  var userAvatar = document.getElementById("user-avatar");
  var paginationEl = document.getElementById("table-pagination");
  var pageLabel = document.getElementById("page-label");
  var pagePrev = document.getElementById("page-prev");
  var pageNext = document.getElementById("page-next");

  var countEls = {
    abertos: document.getElementById("count-abertos"),
    andamento: document.getElementById("count-andamento"),
    resolvidos: document.getElementById("count-resolvidos"),
    total: document.getElementById("count-total"),
  };

  var filterButtons = document.querySelectorAll(".status-card[data-filter]");

  function setFeedback(message) {
    if (!feedbackEl) return;
    feedbackEl.textContent = message || "";
    feedbackEl.hidden = !message;
  }

  function redirectLogin() {
    window.location.href = "../login/index.html";
  }

  function isUnauthorized(result) {
    return result.status === 401 || (result.payload && result.payload.message === "Não autenticado.");
  }

  /** Agrupa status GLPI para os cards de filtro. */
  function getStatusGroup(ticket) {
    if (ticket.status_grupo) return ticket.status_grupo;

    var label = (ticket.status || "").toLowerCase();

    if (label.indexOf("resolv") !== -1 || label.indexOf("fechad") !== -1) {
      return "resolvidos";
    }

    if (label.indexOf("andamento") !== -1 || label.indexOf("process") !== -1) {
      return "andamento";
    }

    return "abertos";
  }

  function getStatusBadgeClass(statusLabel) {
    var label = (statusLabel || "").toLowerCase();

    if (label.indexOf("resolv") !== -1 || label.indexOf("fechad") !== -1) {
      return "badge--resolvido";
    }

    if (label.indexOf("andamento") !== -1 || label.indexOf("process") !== -1) {
      return "badge--andamento";
    }

    if (label.indexOf("pendent") !== -1) {
      return "badge--pendente";
    }

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

  function updateSummaryCounts() {
    var counts = { abertos: 0, andamento: 0, resolvidos: 0, total: state.allTickets.length };

    state.allTickets.forEach(function (ticket) {
      var group = getStatusGroup(ticket);
      if (counts[group] !== undefined) counts[group] += 1;
    });

    Object.keys(countEls).forEach(function (key) {
      if (countEls[key]) countEls[key].textContent = String(counts[key]);
    });
  }

  function getFilteredTickets() {
    if (state.activeFilter === "total") {
      return state.allTickets.slice();
    }

    return state.allTickets.filter(function (ticket) {
      return getStatusGroup(ticket) === state.activeFilter;
    });
  }

  function getPaginatedTickets(tickets) {
    var totalPages = Math.max(1, Math.ceil(tickets.length / PAGE_SIZE));
    state.currentPage = Math.min(state.currentPage, totalPages);

    var start = (state.currentPage - 1) * PAGE_SIZE;
    return {
      items: tickets.slice(start, start + PAGE_SIZE),
      totalPages: totalPages,
    };
  }

  function buildDetailUrl(ticketId) {
    return "detalhe.html?id=" + encodeURIComponent(ticketId);
  }

  function renderTableRow(ticket) {
    var statusClass = getStatusBadgeClass(ticket.status);
    var priorityClass = getPriorityBadgeClass(ticket.prioridade);
    var detailUrl = buildDetailUrl(ticket.id);

    return (
      "<tr>" +
      '<td class="ticket-id">#' +
      ticket.id +
      "</td>" +
      "<td>" +
      escapeHtml(ticket.titulo || "Sem título") +
      "</td>" +
      '<td><span class="badge ' +
      statusClass +
      '">' +
      escapeHtml(ticket.status || "-") +
      "</span></td>" +
      '<td><span class="badge ' +
      priorityClass +
      '">' +
      escapeHtml(ticket.prioridade || "Média") +
      "</span></td>" +
      "<td>" +
      escapeHtml(ticket.data_label || ticket.data_atualizacao || ticket.data_abertura || "-") +
      "</td>" +
      '<td><a class="btn-ver" href="' +
      detailUrl +
      '">Ver &rsaquo;</a></td>' +
      "</tr>"
    );
  }

  function renderMobileCard(ticket) {
    var statusClass = getStatusBadgeClass(ticket.status);
    var priorityClass = getPriorityBadgeClass(ticket.prioridade);
    var detailUrl = buildDetailUrl(ticket.id);

    return (
      '<article class="ticket-mobile-card">' +
      '<div class="ticket-mobile-card__head">' +
      '<strong class="ticket-id">#' +
      ticket.id +
      "</strong>" +
      '<a class="btn-ver" href="' +
      detailUrl +
      '">Ver &rsaquo;</a>' +
      "</div>" +
      '<p class="ticket-mobile-card__title">' +
      escapeHtml(ticket.titulo || "Sem título") +
      "</p>" +
      '<div class="ticket-mobile-card__meta">' +
      '<span class="badge ' +
      statusClass +
      '">' +
      escapeHtml(ticket.status || "-") +
      "</span>" +
      '<span class="badge ' +
      priorityClass +
      '">' +
      escapeHtml(ticket.prioridade || "Média") +
      "</span>" +
      "</div>" +
      "<span>" +
      escapeHtml(ticket.data_label || ticket.data_atualizacao || "-") +
      "</span>" +
      "</article>"
    );
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function renderTickets() {
    var filtered = getFilteredTickets();
    var pageData = getPaginatedTickets(filtered);
    var items = pageData.items;

    if (tableBody) {
      tableBody.innerHTML = items.map(renderTableRow).join("");
    }

    if (mobileList) {
      mobileList.innerHTML = items.map(renderMobileCard).join("");
    }

    if (emptyEl) emptyEl.hidden = filtered.length > 0;
    if (paginationEl) paginationEl.hidden = filtered.length <= PAGE_SIZE;

    if (pageLabel) {
      pageLabel.textContent = "Página " + state.currentPage + " de " + pageData.totalPages;
    }

    if (pagePrev) pagePrev.disabled = state.currentPage <= 1;
    if (pageNext) pageNext.disabled = state.currentPage >= pageData.totalPages;
  }

  function setActiveFilter(filterKey) {
    state.activeFilter = filterKey;
    state.currentPage = 1;

    filterButtons.forEach(function (button) {
      button.classList.toggle("is-active", button.getAttribute("data-filter") === filterKey);
    });

    renderTickets();
  }

  function setUserGreeting(user) {
    if (!user) return;

    var name = user.name || user.login || "Usuário";
    var firstName = name.split(" ")[0];

    if (userGreeting) userGreeting.textContent = "Olá, " + firstName + "!";
    if (userAvatar) userAvatar.textContent = firstName.charAt(0).toUpperCase();
  }

  function loadSession() {
    return window.PortalApi.get("session.php").then(function (result) {
      if (isUnauthorized(result)) {
        redirectLogin();
        return null;
      }

      if (!result.ok || !result.payload.status) return null;

      var user = result.payload.data && result.payload.data.user;
      setUserGreeting(user);
      return user;
    });
  }

  /** Carrega chamados via API e atualiza tabela + cards de resumo. */
  function loadTickets() {
    if (!window.PortalApi) {
      setFeedback("Cliente de API não carregado.");
      return Promise.resolve();
    }

    setFeedback("Carregando chamados...");

    return window.PortalApi.get("listar-chamados.php")
      .then(function (result) {
        if (isUnauthorized(result)) {
          redirectLogin();
          return;
        }

        if (!result.ok || !result.payload.status) {
          setFeedback(result.payload.message || "Não foi possível carregar os chamados.");
          return;
        }

        state.allTickets = (result.payload.data && result.payload.data.tickets) || [];
        state.allTickets.sort(function (a, b) {
          return (b.id || 0) - (a.id || 0);
        });
        setFeedback("");

        if (!state.allTickets.length) {
          if (emptyEl) {
            emptyEl.hidden = false;
            emptyEl.textContent =
              "Nenhum chamado encontrado. Abra um novo chamado ou clique em Recarregar status.";
          }
        }

        updateSummaryCounts();
        renderTickets();
      })
      .catch(function () {
        setFeedback("Erro de comunicação com o servidor.");
      });
  }

  filterButtons.forEach(function (button) {
    button.addEventListener("click", function () {
      setActiveFilter(button.getAttribute("data-filter") || "total");
    });
  });

  if (pagePrev) {
    pagePrev.addEventListener("click", function () {
      if (state.currentPage > 1) {
        state.currentPage -= 1;
        renderTickets();
      }
    });
  }

  if (pageNext) {
    pageNext.addEventListener("click", function () {
      state.currentPage += 1;
      renderTickets();
    });
  }

  if (reloadBtn) {
    reloadBtn.addEventListener("click", function () {
      loadTickets();
    });
  }

  if (logoutLink) {
    logoutLink.addEventListener("click", function (event) {
      event.preventDefault();
      if (!window.PortalApi) return;

      window.PortalApi.postJson("logout.php", {}).finally(function () {
        redirectLogin();
      });
    });
  }

  if (!window.PortalApi) {
    setFeedback("Cliente de API não carregado.");
    return;
  }

  loadSession().then(function () {
    loadTickets();
  });
})();

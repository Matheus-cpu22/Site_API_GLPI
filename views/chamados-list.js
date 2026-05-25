(function () {
  "use strict";

  var listEl = document.getElementById("ticket-list");
  var feedbackEl = document.getElementById("list-feedback");
  var emptyEl = document.getElementById("list-empty");
  var logoutLink = document.getElementById("logout-link");

  function setFeedback(message) {
    if (!feedbackEl) return;
    feedbackEl.textContent = message || "";
    feedbackEl.hidden = !message;
  }

  function redirectLogin() {
    window.location.href = "../login/index.html";
  }

  function renderTickets(tickets) {
    if (!listEl) return;
    listEl.innerHTML = "";

    if (!tickets.length) {
      if (emptyEl) emptyEl.hidden = false;
      return;
    }

    if (emptyEl) emptyEl.hidden = true;

    tickets.forEach(function (ticket) {
      var li = document.createElement("li");
      li.className = "ticket-list__item";
      li.innerHTML =
        '<a href="detalhe.html?id=' +
        encodeURIComponent(ticket.id) +
        '">#' +
        ticket.id +
        " — " +
        (ticket.titulo || "Sem título") +
        '</a><div class="ticket-list__meta">Status: ' +
        (ticket.status || "-") +
        " | Abertura: " +
        (ticket.data_abertura || "-") +
        "</div>";
      listEl.appendChild(li);
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

  window.PortalApi.get("listar-chamados.php")
    .then(function (result) {
      if (result.status === 401 || (result.payload && !result.payload.status && result.status === 401)) {
        redirectLogin();
        return;
      }

      if (!result.ok || !result.payload.status) {
        if (result.status === 401) {
          redirectLogin();
          return;
        }
        setFeedback(result.payload.message || "Não foi possível carregar os chamados.");
        return;
      }

      var tickets = (result.payload.data && result.payload.data.tickets) || [];
      renderTickets(tickets);
    })
    .catch(function () {
      setFeedback("Erro de comunicação com o servidor.");
    });
})();

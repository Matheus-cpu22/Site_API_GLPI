(function () {
  "use strict";

  var form = document.getElementById("login-form");
  var feedbackEl = document.getElementById("login-feedback");
  var submitBtn = document.getElementById("login-submit");

  if (!form) return;

  function setFeedback(message) {
    if (!feedbackEl) return;
    feedbackEl.textContent = message || "";
    feedbackEl.hidden = !message;
  }

  form.addEventListener("submit", function (event) {
    event.preventDefault();

    if (!window.PortalApi) {
      setFeedback("Cliente de API não carregado. Verifique se assets/js/api-client.js está acessível.");
      return;
    }

    var loginInput = form.querySelector('[name="login"]');
    var passwordInput = form.querySelector('[name="password"]');
    var login = loginInput ? loginInput.value.trim() : "";
    var password = passwordInput ? passwordInput.value : "";

    if (!login || !password) {
      setFeedback("Informe usuário e senha.");
      return;
    }

    setFeedback("Autenticando...");
    if (submitBtn) submitBtn.disabled = true;

    window.PortalApi.postJson("login.php", { login: login, password: password })
      .then(function (result) {
        if (!result.ok || !result.payload || !result.payload.status) {
          var msg =
            (result.payload && result.payload.message) ||
            "Falha ao autenticar. Verifique usuário, senha e configuração do GLPI.";
          setFeedback(msg);
          return;
        }

        window.location.href = "../views/chamados.html";
      })
      .catch(function () {
        setFeedback(
          "Erro de comunicação com o servidor. Confirme se o PHP está ativo em /api/login.php."
        );
      })
      .finally(function () {
        if (submitBtn) submitBtn.disabled = false;
      });
  });
})();

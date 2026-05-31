(function () {
  "use strict";

  var canvas = document.getElementById("network-canvas");
  if (!canvas || !canvas.getContext) return;

  var context = canvas.getContext("2d");
  var points = [];
  var width = 0;
  var height = 0;
  var maxDistance = 0;
  var pointCount = 0;
  var pixelRatio = Math.min(window.devicePixelRatio || 1, 2);
  var frameId = 0;

  function reducedMotionEnabled() {
    return window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  }

  function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
  }

  function updateCanvasSize() {
    width = window.innerWidth;
    height = window.innerHeight;
    canvas.width = Math.floor(width * pixelRatio);
    canvas.height = Math.floor(height * pixelRatio);
    canvas.style.width = width + "px";
    canvas.style.height = height + "px";
    context.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);
    maxDistance = Math.min(width, height) * 0.14;
    pointCount = Math.round((width * height) / 20000);
    pointCount = clamp(pointCount, 42, 108);
    initializePoints();
  }

  function initializePoints() {
    points.length = 0;
    for (var i = 0; i < pointCount; i += 1) {
      points.push({
        x: Math.random() * width,
        y: Math.random() * height,
        vx: (Math.random() - 0.5) * 0.22,
        vy: (Math.random() - 0.5) * 0.22,
        radius: Math.random() * 1.6 + 0.7,
      });
    }
  }

  function draw() {
    var i;
    var j;
    var a;
    var b;
    var dx;
    var dy;
    var distance;
    var alpha;

    context.clearRect(0, 0, width, height);
    context.strokeStyle = "rgba(69, 216, 255, 0.26)";
    context.fillStyle = "rgba(117, 227, 255, 0.68)";

    for (i = 0; i < points.length; i += 1) {
      a = points[i];
      a.x += a.vx;
      a.y += a.vy;

      if (a.x < 0 || a.x > width) {
        a.vx *= -1;
      }

      if (a.y < 0 || a.y > height) {
        a.vy *= -1;
      }

      a.x = clamp(a.x, 0, width);
      a.y = clamp(a.y, 0, height);
    }

    for (i = 0; i < points.length; i += 1) {
      a = points[i];
      for (j = i + 1; j < points.length; j += 1) {
        b = points[j];
        dx = a.x - b.x;
        dy = a.y - b.y;
        distance = Math.sqrt(dx * dx + dy * dy);
        if (distance > maxDistance) continue;

        alpha = (1 - distance / maxDistance) * 0.44;
        context.globalAlpha = alpha;
        context.beginPath();
        context.moveTo(a.x, a.y);
        context.lineTo(b.x, b.y);
        context.stroke();
      }
    }

    context.globalAlpha = 1;
    for (i = 0; i < points.length; i += 1) {
      a = points[i];
      context.beginPath();
      context.arc(a.x, a.y, a.radius, 0, Math.PI * 2);
      context.fill();
    }
  }

  function animate() {
    draw();
    frameId = window.requestAnimationFrame(animate);
  }

  function startBackground() {
    updateCanvasSize();

    if (reducedMotionEnabled()) {
      draw();
      return;
    }

    if (frameId) {
      window.cancelAnimationFrame(frameId);
    }

    frameId = window.requestAnimationFrame(animate);
  }

  window.addEventListener("resize", function () {
    updateCanvasSize();
    if (reducedMotionEnabled()) draw();
  });

  startBackground();

  var MAX_ATTACHMENT_BYTES = 40 * 1024 * 1024;
  var form = document.getElementById("ticket-form");
  var attachmentsInput = document.getElementById("attachments");
  var feedbackEl = document.getElementById("form-feedback");

  function setFormFeedback(message) {
    if (!feedbackEl) return;
    feedbackEl.textContent = message || "";
    feedbackEl.hidden = !message;
    feedbackEl.classList.remove("form-feedback--success");
  }

  function totalAttachmentSize(files) {
    var total = 0;
    var i;
    for (i = 0; i < files.length; i += 1) {
      total += files[i].size;
    }
    return total;
  }

  function validateAttachments() {
    if (!attachmentsInput || !attachmentsInput.files) return true;
    var total = totalAttachmentSize(attachmentsInput.files);
    if (total > MAX_ATTACHMENT_BYTES) {
      setFormFeedback(
        "O tamanho total dos anexos ultrapassa 40 MB. Remova ou substitua arquivos e tente novamente."
      );
      attachmentsInput.setAttribute("aria-invalid", "true");
      return false;
    }
    attachmentsInput.removeAttribute("aria-invalid");
    setFormFeedback("");
    return true;
  }

  if (attachmentsInput) {
    attachmentsInput.addEventListener("change", function () {
      validateAttachments();
    });
  }

  var submitButton = form ? form.querySelector(".submit-button") : null;

  function ensureAuthenticated() {
    if (!window.PortalApi) return Promise.resolve(false);

    return window.PortalApi.get("session.php").then(function (result) {
      if (!result.ok || !result.payload.status) {
        window.location.href = "../login/index.html";
        return false;
      }
      return true;
    });
  }

  ensureAuthenticated();

  if (!form) return;

  form.addEventListener("submit", function (event) {
    event.preventDefault();

    if (!validateAttachments()) return;
    if (!form.reportValidity()) return;

    if (!window.PortalApi) {
      setFormFeedback("Cliente de API não carregado.");
      return;
    }

    var formData = new FormData();
    var titleInput = form.querySelector('[name="title"]');
    var descriptionInput = form.querySelector('[name="description"]');
    var typeInput = form.querySelector('[name="ticket_type"]');
    var urgencyInput = form.querySelector('[name="urgency"]');

    formData.append("titulo", titleInput ? titleInput.value.trim() : "");
    formData.append("descricao", descriptionInput ? descriptionInput.value.trim() : "");
    formData.append("ticket_type", typeInput ? typeInput.value : "");
    formData.append("urgency", urgencyInput ? urgencyInput.value : "");

    if (attachmentsInput && attachmentsInput.files) {
      var f;
      for (f = 0; f < attachmentsInput.files.length; f += 1) {
        formData.append("attachments[]", attachmentsInput.files[f]);
      }
    }

    if (submitButton) submitButton.disabled = true;
    setFormFeedback("");

    window.PortalApi.postForm("abrir-chamado.php", formData)
      .then(function (result) {
        if (!result.ok || !result.payload.status) {
          setFormFeedback(result.payload.message || "Não foi possível abrir o chamado.");
          return;
        }

        var ticketId = result.payload.data && result.payload.data.ticket_id;
        var uploadErrors =
          result.payload.data && result.payload.data.upload_errors
            ? result.payload.data.upload_errors
            : [];

        var message = "Chamado #" + ticketId + " criado com sucesso.";
        if (uploadErrors.length) {
          message += " Mas alguns anexos não foram enviados: " + uploadErrors.join(" | ");
          setFormFeedback(message);
          return;
        }

        setFormFeedback(message);
        if (feedbackEl) feedbackEl.classList.add("form-feedback--success");
        form.reset();

        window.setTimeout(function () {
          window.location.href = "../views/chamados.html";
        }, 1200);
      })
      .catch(function () {
        setFormFeedback("Erro de comunicação com o servidor.");
      })
      .finally(function () {
        if (submitButton) submitButton.disabled = false;
      });
  });
})();

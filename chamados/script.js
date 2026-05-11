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

  var form = document.getElementById("ticket-form");
  if (!form) return;

  form.addEventListener("submit", function (event) {
    event.preventDefault();
  });
})();

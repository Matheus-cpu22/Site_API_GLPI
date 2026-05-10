(function () {
  "use strict";

  var canvas = document.getElementById("network-canvas");
  if (!canvas || !canvas.getContext) return;

  var ctx = canvas.getContext("2d");
  var particles = [];
  var dpr = Math.min(window.devicePixelRatio || 1, 2);
  var w = 0;
  var h = 0;
  var maxDist = 0;
  var count = 0;

  function prefersReducedMotion() {
    return window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  }

  function resize() {
    w = window.innerWidth;
    h = window.innerHeight;
    canvas.width = Math.floor(w * dpr);
    canvas.height = Math.floor(h * dpr);
    canvas.style.width = w + "px";
    canvas.style.height = h + "px";
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    maxDist = Math.min(w, h) * 0.12;
    count = Math.round((w * h) / 22000);
    count = Math.max(35, Math.min(count, 95));
    initParticles();
  }

  function initParticles() {
    particles.length = 0;
    for (var i = 0; i < count; i++) {
      particles.push({
        x: Math.random() * w,
        y: Math.random() * h,
        vx: (Math.random() - 0.5) * 0.35,
        vy: (Math.random() - 0.5) * 0.35,
        r: Math.random() * 1.4 + 0.6,
      });
    }
  }

  function step() {
    var i;
    var j;
    var p;
    var q;
    var dx;
    var dy;
    var d;

    ctx.clearRect(0, 0, w, h);
    ctx.strokeStyle = "rgba(0, 198, 255, 0.14)";
    ctx.fillStyle = "rgba(0, 198, 255, 0.45)";

    for (i = 0; i < particles.length; i++) {
      p = particles[i];
      p.x += p.vx;
      p.y += p.vy;
      if (p.x < 0 || p.x > w) p.vx *= -1;
      if (p.y < 0 || p.y > h) p.vy *= -1;
      p.x = Math.max(0, Math.min(w, p.x));
      p.y = Math.max(0, Math.min(h, p.y));
    }

    for (i = 0; i < particles.length; i++) {
      p = particles[i];
      for (j = i + 1; j < particles.length; j++) {
        q = particles[j];
        dx = p.x - q.x;
        dy = p.y - q.y;
        d = Math.sqrt(dx * dx + dy * dy);
        if (d < maxDist) {
          ctx.globalAlpha = (1 - d / maxDist) * 0.5;
          ctx.beginPath();
          ctx.moveTo(p.x, p.y);
          ctx.lineTo(q.x, q.y);
          ctx.stroke();
        }
      }
    }

    ctx.globalAlpha = 1;
    for (i = 0; i < particles.length; i++) {
      p = particles[i];
      ctx.beginPath();
      ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
      ctx.fill();
    }
  }

  var rafId = 0;
  function loop() {
    step();
    rafId = requestAnimationFrame(loop);
  }

  function start() {
    resize();
    if (prefersReducedMotion()) {
      step();
      return;
    }
    if (rafId) cancelAnimationFrame(rafId);
    rafId = requestAnimationFrame(loop);
  }

  window.addEventListener("resize", function () {
    resize();
    if (prefersReducedMotion()) step();
  });

  start();

  var form = document.getElementById("login-form");
  if (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();
    });
  }
})();

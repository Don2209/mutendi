<?php
/**
 * Mutendi CMS — Super Admin login (static UI mockup).
 *
 * Standalone by design: no sidebar, no top bar, no dashboard includes, and its
 * own stylesheet. Nothing here authenticates — the form does not submit and no
 * credential, token or session is created. LATER this posts to the auth
 * controller, which issues the session and the two-factor challenge.
 */

/* Self-locating base URL, so the page works from any folder depth without
   needing the dashboard's includes. */
$docRoot = str_replace('\\', '/', rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/'));
$musDir  = str_replace('\\', '/', __DIR__);
$musUrl  = ($docRoot !== '' && strpos($musDir, $docRoot) === 0)
    ? substr($musDir, strlen($docRoot))
    : '/mus';
$musUrl  = rtrim($musUrl, '/') ?: '/mus';
$rootUrl = rtrim(dirname($musUrl), '/');

/* Figures shown on the left panel.
   LATER:
     SELECT COUNT(*) FROM churches WHERE status = 'active';
     SELECT COUNT(*) FROM members;
   plus the uptime figure from the monitoring service. */
$stats = [
    ['fa-church',       '47',     'Churches'],
    ['fa-users',        '12,480', 'Members'],
    ['fa-signal',       '99.9%',  'Uptime'],
];

/* LATER: read the deployed build from the release manifest. */
$version = 'v1.0.4';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#0a0e1a">
<title>Sign In — Mutendi CMS</title>
<link rel="icon" type="image/png" href="<?= $rootUrl ?>/resources/img/logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Space+Grotesk:wght@300;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="<?= $musUrl ?>/assets/css/login.css">
</head>
<body>

<div class="auth">

  <!-- ══════════════════════ LEFT — THE ATMOSPHERE ══════════════════════ -->
  <section class="stage">
    <canvas class="stage__canvas" id="constellation" aria-hidden="true"></canvas>
    <div class="stage__topo" aria-hidden="true"></div>
    <div class="stage__grid" aria-hidden="true"></div>
    <div class="stage__glow" aria-hidden="true"></div>

    <div class="stage__body">
      <div class="brandrow rise rise-1">
        <span class="badge">
          <span class="badge__plate" aria-hidden="true"></span>
          <span class="badge__ring" aria-hidden="true"></span>
          <i class="fa-solid fa-church badge__mark" aria-hidden="true"></i>
        </span>
        <div>
          <h1 class="wordmark">MUTENDI</h1>
          <p class="subword">Church Management System</p>
        </div>
      </div>

      <p class="tagline rise rise-2">
        Managing <strong>47 churches</strong>. <strong>12,480 members</strong>.
        One platform.
      </p>

      <div class="rule rise rise-3" aria-hidden="true"></div>

      <ul class="chips rise rise-4">
        <?php foreach ($stats as [$icon, $value, $label]): ?>
          <li class="chip">
            <i class="fa-solid <?= $icon ?>" aria-hidden="true"></i>
            <span><strong><?= $value ?></strong> <?= $label ?></span>
          </li>
        <?php endforeach; ?>
      </ul>

    </div>

    <p class="status rise rise-5">
      <span class="status__dot" aria-hidden="true"></span>
      <span>All systems operational</span>
      <span class="status__sep" aria-hidden="true">&middot;</span>
      <span class="status__ver"><?= $version ?></span>
    </p>
  </section>

  <!-- ══════════════════════ RIGHT — THE FORM ══════════════════════ -->
  <section class="panel">

    <div class="panel__top rise rise-1">
      <button class="themebtn" type="button" id="themeBtn"
              title="Switch theme" aria-label="Switch theme">
        <i class="fa-regular fa-moon" aria-hidden="true"></i>
      </button>
    </div>

    <div class="panel__mid">
      <div class="form" id="form">
        <div class="steps" id="steps">
          <div class="steps__track">

            <!-- ─────────── STEP 1 — CREDENTIALS ─────────── -->
            <div class="step" id="step1" role="group" aria-label="Sign in">

              <p class="overline rise rise-2">Super Admin</p>
              <h2 class="title rise rise-2">Welcome back</h2>
              <p class="sub rise rise-3">Sign in to continue to your dashboard</p>

              <!-- Hidden until the server refuses a sign-in. -->
              <div class="alert alert--error" id="alertError" role="alert" hidden>
                <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                <span><strong>Invalid email or password</strong>
                  Check your details and try again.</span>
              </div>

              <!-- Hidden until the attempt limit is reached. -->
              <div class="alert alert--locked" id="alertLocked" role="alert" hidden>
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                <span><strong>Too many attempts</strong>
                  Try again in 15 minutes, or reset your password.</span>
              </div>

              <div class="field rise rise-4">
                <input class="field__input" id="email" type="email" placeholder=" "
                       autocomplete="username" spellcheck="false">
                <i class="fa-regular fa-envelope field__icon" aria-hidden="true"></i>
                <label class="field__label" for="email">Email address</label>
              </div>

              <div class="field rise rise-4">
                <input class="field__input" id="password" type="password" placeholder=" "
                       autocomplete="current-password">
                <i class="fa-solid fa-lock field__icon" aria-hidden="true"></i>
                <label class="field__label" for="password">Password</label>
                <button class="field__toggle" type="button" id="pwToggle"
                        aria-label="Show password" aria-pressed="false">
                  <i class="fa-regular fa-eye" aria-hidden="true"></i>
                </button>
              </div>

              <!-- Appears the moment Caps Lock is detected. -->
              <p class="caps" id="capsWarn" hidden>
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                Caps Lock is on
              </p>

              <div class="optrow rise rise-5">
                <label class="check">
                  <input type="checkbox" id="remember" checked>
                  <span class="check__box" aria-hidden="true"><i class="fa-solid fa-check"></i></span>
                  <span>Remember me for 30 days</span>
                </label>
                <a class="linky" href="#">Forgot password?</a>
              </div>

              <button class="btn rise rise-6" type="button" id="signIn">
                <span class="btn__label">Sign In <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
                <span class="btn__loading"><span class="spinner" aria-hidden="true"></span> Authenticating&hellip;</span>
                <span class="btn__done"><i class="fa-solid fa-check" aria-hidden="true"></i> Verified</span>
              </button>

              <p class="seam rise rise-6" aria-hidden="true">SECURED</p>

              <p class="trust rise rise-6">
                <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                Protected by two-factor authentication
              </p>
            </div>

            <!-- ─────────── STEP 2 — TWO-FACTOR ─────────── -->
            <div class="step" id="step2" role="group" aria-label="Two-factor authentication" aria-hidden="true">

              <button class="back" type="button" id="backBtn">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back
              </button>

              <span class="shield" aria-hidden="true">
                <i class="fa-solid fa-shield-halved"></i>
              </span>

              <h2 class="title">Two-factor authentication</h2>
              <p class="sub">Enter the 6-digit code from your authenticator app</p>

              <div class="code" id="code">
                <?php for ($i = 1; $i <= 6; $i++): ?>
                  <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1"
                         autocomplete="one-time-code"
                         aria-label="Digit <?= $i ?> of 6">
                <?php endfor; ?>
              </div>

              <p class="expiry" id="expiry">
                <i class="fa-regular fa-clock" aria-hidden="true"></i>
                Code expires in <time id="countdown" datetime="PT4M32S">04:32</time>
              </p>

              <button class="btn" type="button" id="verify">
                <span class="btn__label">Verify &amp; Sign In <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
                <span class="btn__loading"><span class="spinner" aria-hidden="true"></span> Verifying&hellip;</span>
                <span class="btn__done"><i class="fa-solid fa-check" aria-hidden="true"></i> Signed in</span>
              </button>

              <a class="recovery" href="#">Use a recovery code instead</a>
            </div>

          </div>
        </div>
      </div>
    </div>

    <div class="panel__foot rise rise-6">
      <span>&copy; <?= date('Y') ?> Mutendi CMS</span>
      <span class="panel__links">
        <a href="#">Privacy</a><span aria-hidden="true">&middot;</span>
        <a href="#">Terms</a><span aria-hidden="true">&middot;</span>
        <a href="#">Support</a>
      </span>
    </div>

  </section>
</div>

<script>
(function () {
  'use strict';

  var still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ================================================== constellation ==== */
  /* A slow drift of points with lines drawn between near neighbours. The
     count scales with the panel's area and is capped hard, so a phone is
     never asked to run the same load as a 27" display. */
  (function () {
    var canvas = document.getElementById('constellation');
    if (!canvas || !canvas.getContext) { return; }

    var ctx = canvas.getContext('2d'),
        dots = [], w = 0, h = 0, dpr = 1, frame = null;

    var LINK = 128;

    function build() {
      var rect = canvas.getBoundingClientRect();
      dpr = Math.min(window.devicePixelRatio || 1, 2);
      w = rect.width;
      h = rect.height;
      canvas.width  = Math.round(w * dpr);
      canvas.height = Math.round(h * dpr);
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

      var target = Math.round((w * h) / 15000);
      var count  = Math.max(14, Math.min(w < 700 ? 26 : 62, target));

      dots = [];
      for (var i = 0; i < count; i++) {
        dots.push({
          x:  Math.random() * w,
          y:  Math.random() * h,
          vx: (Math.random() - .5) * .16,
          vy: (Math.random() - .5) * .16,
          r:  Math.random() * 1.5 + .7
        });
      }
    }

    function draw() {
      ctx.clearRect(0, 0, w, h);

      for (var i = 0; i < dots.length; i++) {
        var a = dots[i];

        for (var j = i + 1; j < dots.length; j++) {
          var b  = dots[j],
              dx = a.x - b.x,
              dy = a.y - b.y,
              d  = Math.sqrt(dx * dx + dy * dy);

          if (d < LINK) {
            ctx.globalAlpha = (1 - d / LINK) * 0.22;
            ctx.strokeStyle = '#8b5cf6';
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(a.x, a.y);
            ctx.lineTo(b.x, b.y);
            ctx.stroke();
          }
        }

        ctx.globalAlpha = .75;
        ctx.fillStyle = '#a5b4fc';
        ctx.beginPath();
        ctx.arc(a.x, a.y, a.r, 0, Math.PI * 2);
        ctx.fill();
      }

      ctx.globalAlpha = 1;
    }

    function step() {
      for (var i = 0; i < dots.length; i++) {
        var d = dots[i];
        d.x += d.vx;
        d.y += d.vy;
        if (d.x < -20) { d.x = w + 20; } else if (d.x > w + 20) { d.x = -20; }
        if (d.y < -20) { d.y = h + 20; } else if (d.y > h + 20) { d.y = -20; }
      }
      draw();
      frame = requestAnimationFrame(step);
    }

    function start() {
      if (still) { draw(); return; }          /* one static frame, no loop */
      if (frame === null) { frame = requestAnimationFrame(step); }
    }
    function stop() {
      if (frame !== null) { cancelAnimationFrame(frame); frame = null; }
    }

    build();
    start();

    /* Nothing to animate while the tab is in the background. */
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) { stop(); } else { start(); }
    });

    var resizeTimer;
    window.addEventListener('resize', function () {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function () { stop(); build(); start(); }, 160);
    });
  })();

  /* ================================================ password visibility ==== */
  var pw       = document.getElementById('password'),
      pwToggle = document.getElementById('pwToggle');

  pwToggle.addEventListener('click', function () {
    var show = pw.type === 'password';
    pw.type = show ? 'text' : 'password';
    pwToggle.setAttribute('aria-pressed', String(show));
    pwToggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    pwToggle.querySelector('i').className = show
      ? 'fa-regular fa-eye-slash'
      : 'fa-regular fa-eye';
    pw.focus();
  });

  /* ===================================================== caps lock ==== */
  var capsWarn = document.getElementById('capsWarn');

  function readCaps(e) {
    if (typeof e.getModifierState !== 'function') { return; }
    capsWarn.hidden = !e.getModifierState('CapsLock');
  }
  pw.addEventListener('keydown', readCaps);
  pw.addEventListener('keyup', readCaps);
  pw.addEventListener('blur', function () { capsWarn.hidden = true; });

  /* ======================================================== steps ==== */
  var steps = document.getElementById('steps'),
      step1 = document.getElementById('step1'),
      step2 = document.getElementById('step2'),
      form  = document.getElementById('form'),
      codeBoxes = [].slice.call(document.querySelectorAll('#code input'));

  /* The two steps are different heights, so the viewport is measured rather
     than left at the taller of the two — otherwise step 2 sits above a gap. */
  function fit() {
    var active = steps.classList.contains('is-two') ? step2 : step1;
    steps.style.height = active.offsetHeight + 'px';
  }

  function show(which) {
    var two = which === 2;
    steps.classList.toggle('is-two', two);
    step1.setAttribute('aria-hidden', String(two));
    step2.setAttribute('aria-hidden', String(!two));
    step1.inert = two;
    step2.inert = !two;
    fit();
    if (two) {
      startCountdown();
      setTimeout(function () { codeBoxes[0].focus(); }, still ? 0 : 420);
    }
  }

  step2.inert = true;
  fit();
  window.addEventListener('resize', fit);
  /* Web fonts land after first paint and change the measured height. */
  if (document.fonts && document.fonts.ready) { document.fonts.ready.then(fit); }

  /* Anything that changes a step's height at runtime — an alert appearing, the
     Caps Lock line, a wrapped label — has to re-measure, or the viewport clips
     whatever now sits past the old height. */
  if (window.ResizeObserver) {
    var ro = new ResizeObserver(function () { fit(); });
    ro.observe(step1);
    ro.observe(step2);
  }

  /* ================================================ button states ==== */
  function run(btn, done) {
    btn.classList.add('is-loading');
    setTimeout(function () {
      btn.classList.remove('is-loading');
      btn.classList.add('is-done');
      setTimeout(function () {
        btn.classList.remove('is-done');
        done();
      }, still ? 0 : 600);
    }, still ? 0 : 1500);
  }

  document.getElementById('signIn').addEventListener('click', function () {
    document.getElementById('alertError').hidden = true;
    document.getElementById('alertLocked').hidden = true;
    run(this, function () { show(2); });
  });

  document.getElementById('verify').addEventListener('click', function () {
    /* LATER: this is where the verified session hands over to the dashboard. */
    run(this, function () {});
  });

  document.getElementById('backBtn').addEventListener('click', function () {
    stopCountdown();
    show(1);
    setTimeout(function () { document.getElementById('email').focus(); }, still ? 0 : 420);
  });

  /* The form flinches when a sign-in is refused. Kept here so the error
     state can be demonstrated from the console without a server. */
  window.mutendiShake = function () {
    if (still) { return; }
    form.classList.remove('is-shaking');
    void form.offsetWidth;
    form.classList.add('is-shaking');
  };

  /* ==================================================== code boxes ==== */
  codeBoxes.forEach(function (box, i) {
    box.addEventListener('input', function () {
      box.value = box.value.replace(/\D/g, '').slice(0, 1);
      box.classList.toggle('is-filled', box.value !== '');
      if (box.value && i < codeBoxes.length - 1) { codeBoxes[i + 1].focus(); }
    });

    box.addEventListener('keydown', function (e) {
      if (e.key === 'Backspace' && !box.value && i > 0) {
        e.preventDefault();
        codeBoxes[i - 1].focus();
        codeBoxes[i - 1].value = '';
        codeBoxes[i - 1].classList.remove('is-filled');
      }
      if (e.key === 'ArrowLeft'  && i > 0) { codeBoxes[i - 1].focus(); }
      if (e.key === 'ArrowRight' && i < codeBoxes.length - 1) { codeBoxes[i + 1].focus(); }
    });

    /* A pasted code fills every box, wherever it was pasted. */
    box.addEventListener('paste', function (e) {
      e.preventDefault();
      var text = (e.clipboardData || window.clipboardData).getData('text') || '';
      var digits = text.replace(/\D/g, '').slice(0, codeBoxes.length).split('');
      if (!digits.length) { return; }
      codeBoxes.forEach(function (b, n) {
        b.value = digits[n] || '';
        b.classList.toggle('is-filled', b.value !== '');
      });
      codeBoxes[Math.min(digits.length, codeBoxes.length) - 1].focus();
    });

    box.addEventListener('focus', function () { box.select(); });
  });

  /* ===================================================== countdown ==== */
  var expiry    = document.getElementById('expiry'),
      countdown = document.getElementById('countdown'),
      left      = 272,                       /* 04:32 */
      ticker    = null;

  function paint() {
    var m = Math.floor(left / 60), s = left % 60;
    var text = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
    countdown.textContent = text;
    countdown.setAttribute('datetime', 'PT' + m + 'M' + s + 'S');
    expiry.classList.toggle('is-urgent', left <= 30);
  }

  function startCountdown() {
    stopCountdown();
    left = 272;
    paint();
    ticker = setInterval(function () {
      left = Math.max(0, left - 1);
      paint();
      if (left === 0) { stopCountdown(); }
    }, 1000);
  }
  function stopCountdown() {
    if (ticker) { clearInterval(ticker); ticker = null; }
  }

  /* ======================================================= theme ==== */
  /* Visual only — the page is dark either way. */
  document.getElementById('themeBtn').addEventListener('click', function () {
    var icon = this.querySelector('i');
    var sun  = icon.classList.contains('fa-sun');
    icon.className = sun ? 'fa-regular fa-moon' : 'fa-regular fa-sun';
  });

})();
</script>

</body>
</html>

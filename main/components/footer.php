    </main>
  </div><!-- /.shell -->
</div><!-- /.app -->

<!-- Positioned by script so it escapes the sidebar's scroll box. -->
<div class="tip" id="tip" role="tooltip" hidden></div>

<script>
(function () {
  'use strict';

  var app   = document.getElementById('app'),
      side  = document.getElementById('sidebar'),
      scrim = document.getElementById('scrim'),
      still = window.matchMedia('(prefers-reduced-motion: reduce)').matches,
      MOBILE = '(max-width: 900px)';

  function isMobile() { return window.matchMedia(MOBILE).matches; }

  /* ================================================= sidebar collapse ==== */
  /* Desktop: a 72px icon rail. Mobile: an off-canvas panel. One button, so
     which behaviour applies is decided by the viewport, not the markup. */

  var COLLAPSE_KEY = 'mutendi-main-collapsed';

  function setCollapsed(on) {
    app.classList.toggle('is-collapsed', on);
    try { sessionStorage.setItem(COLLAPSE_KEY, on ? '1' : '0'); } catch (e) {}
  }

  try {
    if (sessionStorage.getItem(COLLAPSE_KEY) === '1') { app.classList.add('is-collapsed'); }
  } catch (e) {}

  function openDrawer() {
    app.classList.add('is-drawer');
    scrim.hidden = false;
    document.body.style.overflow = 'hidden';
    document.getElementById('navToggle').setAttribute('aria-expanded', 'true');
  }
  function closeDrawer() {
    app.classList.remove('is-drawer');
    scrim.hidden = true;
    document.body.style.overflow = '';
    document.getElementById('navToggle').setAttribute('aria-expanded', 'false');
  }

  document.getElementById('navToggle').addEventListener('click', function () {
    if (isMobile()) {
      app.classList.contains('is-drawer') ? closeDrawer() : openDrawer();
    } else {
      setCollapsed(!app.classList.contains('is-collapsed'));
      hideTip();
    }
  });

  document.getElementById('navClose').addEventListener('click', closeDrawer);
  scrim.addEventListener('click', closeDrawer);

  /* A resize past the breakpoint must not leave a drawer state behind. */
  window.addEventListener('resize', function () {
    if (!isMobile()) { closeDrawer(); }
  });

  /* ========================================================= accordions ==== */
  /* The group holding the current page is opened server-side; anything the
     user opens or closes after that is remembered for the session. */

  var OPEN_KEY = 'mutendi-main-open-groups';

  function readOpen() {
    try { return JSON.parse(sessionStorage.getItem(OPEN_KEY) || 'null'); } catch (e) { return null; }
  }
  function writeOpen() {
    var open = [].slice.call(document.querySelectorAll('.nav-group.is-open'))
                .map(function (g) { return g.getAttribute('data-group'); });
    try { sessionStorage.setItem(OPEN_KEY, JSON.stringify(open)); } catch (e) {}
  }

  var stored = readOpen();
  if (stored) {
    [].forEach.call(document.querySelectorAll('.nav-group'), function (g) {
      /* The current group always opens, whatever the stored state says. */
      var on = stored.indexOf(g.getAttribute('data-group')) !== -1 || g.classList.contains('is-open');
      g.classList.toggle('is-open', on);
      g.querySelector('.nav-group__head').setAttribute('aria-expanded', String(on));
    });
  }

  [].forEach.call(document.querySelectorAll('.nav-group__head'), function (btn) {
    btn.addEventListener('click', function () {
      var group = btn.closest('.nav-group');
      /* Collapsed rail: the heading is a flyout trigger, not an accordion. */
      if (app.classList.contains('is-collapsed') && !isMobile()) { return; }
      var on = !group.classList.contains('is-open');
      group.classList.toggle('is-open', on);
      btn.setAttribute('aria-expanded', String(on));
      writeOpen();
    });
  });

  /* ==================================================== rail flyouts ==== */
  /* The sidebar scrolls, so a flyout drawn inside it would be clipped. Each
     one is lifted to fixed position and placed against its trigger instead. */

  function railActive() { return app.classList.contains('is-collapsed') && !isMobile(); }

  function placeFlyout(group) {
    var list = group.querySelector('[data-flyout]');
    if (!list) { return; }
    var r = group.getBoundingClientRect();
    list.style.top = Math.max(8, Math.min(r.top, window.innerHeight - list.offsetHeight - 8)) + 'px';
    list.style.left = (r.right + 10) + 'px';
  }

  [].forEach.call(document.querySelectorAll('.nav-group'), function (group) {
    group.addEventListener('mouseenter', function () { if (railActive()) { placeFlyout(group); } });
    group.addEventListener('focusin',    function () { if (railActive()) { placeFlyout(group); } });
  });

  /* ========================================================== tooltips ==== */

  var tip = document.getElementById('tip'), tipFor = null;

  function showTip(el) {
    var label = el.getAttribute('data-tip');
    if (!label) { return; }
    tip.textContent = label;
    tip.hidden = false;
    var r = el.getBoundingClientRect();
    tip.style.top  = (r.top + r.height / 2 - tip.offsetHeight / 2) + 'px';
    tip.style.left = (r.right + 12) + 'px';
    tipFor = el;
  }
  function hideTip() { tip.hidden = true; tipFor = null; }

  [].forEach.call(document.querySelectorAll('[data-tip]'), function (el) {
    el.addEventListener('mouseenter', function () { if (railActive()) { showTip(el); } });
    el.addEventListener('focus',      function () { if (railActive()) { showTip(el); } });
    el.addEventListener('mouseleave', hideTip);
    el.addEventListener('blur',       hideTip);
  });
  window.addEventListener('scroll', hideTip, true);

  /* ========================================================= dropdowns ==== */

  function closeMenus(except) {
    [].forEach.call(document.querySelectorAll('[data-menu]'), function (d) {
      if (d === except) { return; }
      d.classList.remove('is-open');
      d.querySelector('[data-menu-btn]').setAttribute('aria-expanded', 'false');
      d.querySelector('[data-menu-panel]').hidden = true;
    });
  }

  [].forEach.call(document.querySelectorAll('[data-menu]'), function (drop) {
    var btn = drop.querySelector('[data-menu-btn]'),
        panel = drop.querySelector('[data-menu-panel]');

    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      var on = !drop.classList.contains('is-open');
      closeMenus(drop);
      drop.classList.toggle('is-open', on);
      btn.setAttribute('aria-expanded', String(on));
      panel.hidden = !on;
    });
    panel.addEventListener('click', function (e) { e.stopPropagation(); });
  });

  document.addEventListener('click', function () { closeMenus(null); });

  /* ===================================================== mobile search ==== */

  var bar = document.getElementById('searchBar');

  document.getElementById('searchOpen').addEventListener('click', function () {
    bar.hidden = false;
    document.getElementById('mobileSearch').focus();
  });
  document.getElementById('searchClose').addEventListener('click', function () {
    bar.hidden = true;
  });

  /* =============================================================== keys ==== */

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') { return; }
    if (app.classList.contains('is-drawer')) { closeDrawer(); }
    if (!bar.hidden) { bar.hidden = true; }
    closeMenus(null);
    hideTip();
  });

})();
</script>

</body>
</html>

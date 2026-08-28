<?php
/**
 * Mutendi CMS — add a {branch_singular}.
 *
 * A five-step wizard in the same shape as members/add.php, with a live
 * preview of the branch card being built. UI only: nothing is submitted.
 */

require __DIR__ . '/../includes/config.php';

/* ══════════════ DEMO ONLY — REMOVE BEFORE PRODUCTION ══════════════ */
$demo_role       = isset($_GET['role'], $demo_roles[$_GET['role']]) ? $_GET['role'] : 'church_admin';
$user            = array_merge($user, $demo_roles[$demo_role]['user']);
$permissions     = $demo_roles[$demo_role]['perms'];
$enabled_modules = $demo_roles[$demo_role]['modules'];
/* ═══════════════════════════ END DEMO ═══════════════════════════ */

if (!function_exists('mu_can')) {
    function mu_can(string $perm): bool { global $permissions; return in_array($perm, $permissions, true); }
    function mu_mod(string $mod): bool  { global $enabled_modules; return in_array($mod, $enabled_modules, true); }
}

/* Organisation scope and the add permission are both required. */
$may_add = ($user['scope'] ?? 'organisation') !== 'branch' && mu_can('branches.add');

/* LATER: SELECT MAX(code) FROM branches WHERE org_id = :org_id — continue the
   organisation's own numbering rather than inventing one. */
$next_code = strtoupper(substr($organisation['code'], 0, 3)) . '-PR-'
           . str_pad((string) (count($branches) + 1), 2, '0', STR_PAD_LEFT);

$page_title = 'Add ' . t('branch_singular');
require __DIR__ . '/../components/header.php';
?>

<div class="page">

  <header class="page__head">
    <div>
      <nav class="crumbs" aria-label="Breadcrumb">
        <a href="<?= $base_url ?>index.php">Dashboard</a>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span><?= htmlspecialchars(t('org_singular')) ?></span>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <a href="<?= $base_url ?>branches/index.php">All <?= htmlspecialchars(t('branch_plural')) ?></a>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span aria-current="page">Add</span>
      </nav>
      <h1 class="page__title">Add <?= htmlspecialchars(t('branch_singular')) ?></h1>
      <p class="page__sub">Register a new <?= htmlspecialchars(mb_strtolower(t('branch_singular'))) ?> under <?= htmlspecialchars($organisation['name']) ?>.</p>
    </div>
    <?php if ($may_add): ?>
      <div class="page__actions">
        <a class="btn btn--ghost" href="<?= $base_url ?>branches/index.php" id="cancelBtn">Cancel</a>
        <button class="btn btn--ghost" type="button" data-toast="Draft saved"><i class="fa-regular fa-floppy-disk" aria-hidden="true"></i> Save as draft</button>
        <button class="btn" type="button" id="saveTop"><i class="fa-solid fa-check" aria-hidden="true"></i> Save <?= htmlspecialchars(t('branch_singular')) ?></button>
      </div>
    <?php endif; ?>
  </header>

<?php if (!$may_add): ?>

  <section class="panel">
    <div class="empty">
      <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-lock"></i></span>
      <h3>You can't add a <?= htmlspecialchars(mb_strtolower(t('branch_singular'))) ?></h3>
      <p>
        <?php if (($user['scope'] ?? 'organisation') === 'branch'): ?>
          Your account is scoped to a single <?= htmlspecialchars(mb_strtolower(t('branch_singular'))) ?>, so it cannot create new ones.
        <?php else: ?>
          Your role does not include the <strong>branches.add</strong> permission. Ask a <?= htmlspecialchars(mb_strtolower(t('org_singular'))) ?> administrator if you need it.
        <?php endif; ?>
      </p>
      <a class="btn" href="<?= $base_url ?>branches/index.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to all <?= htmlspecialchars(mb_strtolower(t('branch_plural'))) ?></a>
    </div>
  </section>

<?php else: ?>

  <section class="panel" style="padding:18px;margin-bottom:16px">
    <div class="stepper" id="stepper">
      <?php
        $steps = [t('branch_singular') . ' Details', 'Location', 'Leadership', 'Services', 'Review'];
        foreach ($steps as $i => $label): $n = $i + 1;
      ?>
        <div class="stepper__item<?= $n === 1 ? ' is-on' : '' ?>" data-step-item="<?= $n ?>">
          <button class="stepper__btn" type="button" data-goto-step="<?= $n ?>" <?= $n === 1 ? '' : 'disabled' ?>>
            <span class="stepper__num"><?= $n ?></span>
            <span class="stepper__label"><?= htmlspecialchars($label) ?></span>
          </button>
          <?php if ($n < count($steps)): ?><span class="stepper__line"></span><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="stepper-mobile">
      <p class="stepper-mobile__label"><span>Step <span data-step-now>1</span> of 5</span><span data-step-name><?= htmlspecialchars($steps[0]) ?></span></p>
      <div class="progressbar"><span style="width:20%" data-step-bar></span></div>
    </div>
  </section>

  <div class="form-layout" style="display:grid;grid-template-columns:2fr 1fr;gap:16px;align-items:start">

    <section class="panel" style="padding:20px">
      <div class="err-summary" id="errSummary" role="alert">
        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
        <div><strong>Please complete these first</strong><ul id="errList"></ul></div>
      </div>

      <!-- ─────────── STEP 1 ─────────── -->
      <div class="wizard-step" data-step="1">
        <h2 class="wiz-h">Details</h2>
        <p class="wiz-p">What is this <?= htmlspecialchars(mb_strtolower(t('branch_singular'))) ?> called, and where does it sit?</p>

        <label class="avatar-drop" for="logo" style="margin-bottom:18px">
          <i class="fa-solid fa-camera" aria-hidden="true"></i>
          <span>Drop a logo or click to upload</span>
          <input type="file" id="logo" accept="image/*" hidden>
        </label>

        <div class="form-grid">
          <div class="field col-2">
            <label for="bName">Name <span class="req" aria-hidden="true">*</span></label>
            <input class="input" id="bName" data-req data-preview="name" placeholder="e.g. St Andrew's Marlborough">
            <p class="err">A name is required.</p><p class="ok-tick"><i class="fa-solid fa-check"></i> Looks good</p>
          </div>

          <div class="field">
            <label for="bCode">Code</label>
            <div style="display:flex;gap:8px">
              <input class="input" id="bCode" value="<?= htmlspecialchars($next_code) ?>" data-preview="code">
              <button class="iconbtn" type="button" id="regenCode" aria-label="Generate a new code"><i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i></button>
            </div>
            <p class="hint">Auto-generated. Edit it if your <?= htmlspecialchars(mb_strtolower(t('org_singular'))) ?> numbers differently.</p>
          </div>

          <div class="field">
            <label for="bGroup"><?= htmlspecialchars(t('group_singular')) ?> <span class="req" aria-hidden="true">*</span></label>
            <select class="select" id="bGroup" data-req data-preview="group">
              <option value="">Select&hellip;</option>
              <?php foreach (array_unique(array_column($branches, 'group_name')) as $g): ?>
                <option><?= htmlspecialchars($g) ?></option>
              <?php endforeach; ?>
            </select>
            <p class="err">Choose a <?= htmlspecialchars(mb_strtolower(t('group_singular'))) ?>.</p>
          </div>

          <div class="field col-2">
            <label>Type</label>
            <div class="radio-cards">
              <label class="rcard">
                <input type="radio" name="btype" value="branch" checked>
                <span class="rcard__box"><i class="fa-solid fa-church" aria-hidden="true"></i><span class="rcard__label"><?= htmlspecialchars(t('branch_singular')) ?></span></span>
              </label>
              <label class="rcard">
                <input type="radio" name="btype" value="head_office">
                <span class="rcard__box"><i class="fa-solid fa-building-columns" aria-hidden="true"></i><span class="rcard__label">Head Office</span></span>
              </label>
            </div>
          </div>

          <div class="field"><label for="bEst">Established</label><input class="input" type="date" id="bEst" value="<?= date('Y-m-d') ?>"></div>
          <div class="field">
            <label for="bStatus">Status</label>
            <select class="select" id="bStatus" data-preview="status">
              <option value="planting">Planting</option>
              <option value="active" selected>Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
          <div class="field col-2">
            <label for="bDesc">Description</label>
            <textarea class="textarea" id="bDesc" rows="3" placeholder="Anything worth recording about this <?= htmlspecialchars(mb_strtolower(t('branch_singular'))) ?>&hellip;"></textarea>
          </div>
        </div>
      </div>

      <!-- ─────────── STEP 2 ─────────── -->
      <div class="wizard-step" data-step="2" hidden>
        <h2 class="wiz-h">Location</h2>
        <p class="wiz-p">Where do people find it?</p>

        <div class="form-grid">
          <div class="field col-2">
            <label for="bAddr">Address <span class="req" aria-hidden="true">*</span></label>
            <input class="input" id="bAddr" data-req placeholder="House number and street">
            <p class="err">An address is required.</p><p class="ok-tick"><i class="fa-solid fa-check"></i> Looks good</p>
          </div>
          <div class="field">
            <label for="bSuburb">Suburb</label>
            <input class="input" id="bSuburb" list="subList" data-preview="suburb">
            <datalist id="subList">
              <?php foreach ($suburbs_demo as $s): ?><option value="<?= htmlspecialchars($s) ?>"></option><?php endforeach; ?>
            </datalist>
          </div>
          <div class="field"><label for="bCity">City</label><input class="input" id="bCity" value="Harare"></div>
          <div class="field">
            <label for="bProvince">Province</label>
            <select class="select" id="bProvince">
              <?php foreach ($provinces_demo as $p): ?><option<?= $p === 'Harare' ? ' selected' : '' ?>><?= htmlspecialchars($p) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="field"><label for="bLat">Latitude</label><input class="input" id="bLat" placeholder="-17.8292" inputmode="decimal"></div>
          <div class="field"><label for="bLng">Longitude</label><input class="input" id="bLng" placeholder="31.0522" inputmode="decimal"></div>
          <div class="field col-2">
            <label for="bDirections">Directions</label>
            <textarea class="textarea" id="bDirections" rows="2" placeholder="Landmarks, turnings, anything that helps a first-time visitor&hellip;"></textarea>
          </div>
        </div>

        <div class="minimap" style="margin-top:16px" aria-label="Map placeholder">
          <span class="mappin" style="left:50%;top:52%">
            <span class="mappin__dot" style="background:var(--brand-500)"><span><i class="fa-solid fa-church" style="font-size:10px"></i></span></span>
          </span>
          <span class="minimap__coords" data-map-coords>Coordinates will appear here</span>
        </div>
      </div>

      <!-- ─────────── STEP 3 ─────────── -->
      <div class="wizard-step" data-step="3" hidden>
        <h2 class="wiz-h">Leadership</h2>
        <p class="wiz-p">Who leads this <?= htmlspecialchars(mb_strtolower(t('branch_singular'))) ?>?</p>

        <div class="field" style="margin-bottom:14px">
          <label><?= htmlspecialchars(t('leader_title')) ?> <span class="req" aria-hidden="true">*</span></label>
          <div class="radio-cards">
            <label class="rcard">
              <input type="radio" name="leadMode" value="existing" checked>
              <span class="rcard__box"><i class="fa-solid fa-user-check" aria-hidden="true"></i><span class="rcard__label">Select existing member</span></span>
            </label>
            <label class="rcard">
              <input type="radio" name="leadMode" value="invite">
              <span class="rcard__box"><i class="fa-solid fa-envelope-open-text" aria-hidden="true"></i><span class="rcard__label">Invite new</span></span>
            </label>
          </div>
        </div>

        <div class="field" id="leadExisting" style="margin-bottom:14px">
          <label for="leadPick">Find a member</label>
          <div class="search-field">
            <i class="fa-solid fa-magnifying-glass search-field__icon" aria-hidden="true"></i>
            <input class="input" id="leadPick" data-req data-preview="leader" list="memberList" placeholder="Search members&hellip;">
          </div>
          <p class="err">Choose a <?= htmlspecialchars(mb_strtolower(t('leader_title'))) ?>.</p>
        </div>

        <div class="form-grid" id="leadInvite" hidden>
          <div class="field"><label for="invName">Full name</label><input class="input" id="invName" data-preview="leader"></div>
          <div class="field"><label for="invEmail">Email</label><input class="input" type="email" id="invEmail" data-email placeholder="name@example.com"><p class="err">That email address does not look right.</p></div>
          <div class="field col-2">
            <label for="invPhone">Phone</label>
            <div class="phone-input"><span class="phone-input__prefix"><span aria-hidden="true">🇿🇼</span> +263</span><input class="input" id="invPhone" data-phone inputmode="tel" placeholder="77 123 4567"></div>
          </div>
        </div>

        <div class="form-grid" style="margin-top:18px;padding-top:16px;border-top:1px solid var(--line)">
          <div class="field"><label for="asstPick">Assistant <?= htmlspecialchars(mb_strtolower(t('leader_title'))) ?></label><input class="input" id="asstPick" list="memberList" placeholder="Optional"></div>
          <div class="field"><label for="secPick">Secretary</label><input class="input" id="secPick" list="memberList" placeholder="Optional"></div>
          <div class="field col-2"><label for="treasPick">Treasurer</label><input class="input" id="treasPick" list="memberList" placeholder="Optional"></div>
        </div>
        <datalist id="memberList">
          <?php foreach ($members_demo as $m): ?><option value="<?= htmlspecialchars($m['name']) ?>"></option><?php endforeach; ?>
        </datalist>
      </div>

      <!-- ─────────── STEP 4 ─────────── -->
      <div class="wizard-step" data-step="4" hidden>
        <h2 class="wiz-h">Services &amp; Schedule</h2>
        <p class="wiz-p">When does it meet? Add a row per service.</p>

        <div id="serviceRows"></div>
        <button class="btn btn--ghost" type="button" id="addService" style="margin-top:12px">
          <i class="fa-solid fa-plus" aria-hidden="true"></i> Add a service
        </button>
        <p class="hint" style="margin-top:8px"><span data-service-count>0</span> service(s) scheduled</p>
      </div>

      <!-- ─────────── STEP 5 ─────────── -->
      <div class="wizard-step" data-step="5" hidden>
        <h2 class="wiz-h">Setup &amp; Review</h2>
        <p class="wiz-p">Copy a starting setup from an existing <?= htmlspecialchars(mb_strtolower(t('branch_singular'))) ?>, then check everything.</p>

        <div class="copy-list">
          <?php
            $copyables = [];
            if (mu_mod('departments')) { $copyables[] = ['copyDepts', 'Copy departments', 'The same department list as another ' . mb_strtolower(t('branch_singular')) . '.']; }
            if (mu_can('finance.view')) { $copyables[] = ['copyTypes', 'Copy contribution types', 'Tithe, offering, building fund and so on.']; }
            $copyables[] = ['copyFields', 'Copy member fields', 'Any custom fields already in use.'];
            foreach ($copyables as [$id, $label, $note]):
          ?>
            <div class="copy-row">
              <label class="copy-row__main">
                <input class="check" type="checkbox" id="<?= $id ?>" data-copy-toggle>
                <span>
                  <strong><?= htmlspecialchars($label) ?></strong>
                  <small><?= htmlspecialchars($note) ?></small>
                </span>
              </label>
              <select class="select" data-copy-source disabled aria-label="Source for <?= htmlspecialchars($label) ?>">
                <?php foreach ($branches as $b): ?><option><?= htmlspecialchars($b['name']) ?></option><?php endforeach; ?>
              </select>
            </div>
          <?php endforeach; ?>
        </div>

        <div id="reviewOut" style="margin-top:20px"></div>

        <?php if (mu_mod('communication')): ?>
          <label style="display:flex;align-items:center;gap:10px;margin-top:16px;padding-top:16px;border-top:1px solid var(--line);color:var(--ink-2);font-size:13px;font-weight:600">
            <input class="check" type="checkbox" checked>
            Send a welcome message to the <?= htmlspecialchars(mb_strtolower(t('leader_title'))) ?>
          </label>
        <?php endif; ?>
      </div>

      <div class="wizard-nav">
        <button class="btn btn--ghost" type="button" id="backBtn" hidden><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back</button>
        <span class="spacer"></span>
        <button class="btn" type="button" id="nextBtn">Continue <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
        <button class="btn" type="button" id="saveBtn" hidden><i class="fa-solid fa-check" aria-hidden="true"></i> Save <?= htmlspecialchars(t('branch_singular')) ?></button>
      </div>
    </section>

    <!-- ═══════════════════════ LIVE PREVIEW ═══════════════════════ -->
    <aside class="preview" aria-label="Live preview">
      <p class="preview__eyebrow">Live preview</p>
      <span class="av av--xl av-c0 preview__av" id="pvAv" aria-hidden="true">?</span>
      <p class="preview__name" id="pvName">New <?= htmlspecialchars(t('branch_singular')) ?></p>
      <p class="preview__no" id="pvCode"><?= htmlspecialchars($next_code) ?></p>
      <p style="margin-top:8px"><span class="spill is-active" data-pv="status">Active</span></p>
      <dl class="preview__list">
        <div><dt><?= htmlspecialchars(t('group_singular')) ?></dt><dd data-pv="group" class="preview__empty">—</dd></div>
        <div><dt>Suburb</dt><dd data-pv="suburb" class="preview__empty">—</dd></div>
        <div><dt><?= htmlspecialchars(t('leader_title')) ?></dt><dd data-pv="leader" class="preview__empty">—</dd></div>
        <div><dt>Services</dt><dd data-pv="services">0</dd></div>
      </dl>
    </aside>
  </div>

<?php endif; ?>
</div>

<div class="toasts" id="toasts" aria-live="polite"></div>

<?php /* ══════════ DEMO ONLY — REMOVE BEFORE PRODUCTION ══════════ */ ?>
<details class="demo" aria-label="Demo role switcher">
  <summary class="demo__summary">
    <i class="fa-solid fa-flask" aria-hidden="true"></i>
    <span class="demo__summary-role"><?= htmlspecialchars($demo_roles[$demo_role]['user']['role_label']) ?></span>
    <i class="fa-solid fa-chevron-up demo__summary-chev" aria-hidden="true"></i>
  </summary>
  <p class="demo__warn"><i class="fa-solid fa-flask" aria-hidden="true"></i> DEMO ONLY — remove before production</p>
  <p class="demo__hint">branches.add is not in any demo role yet</p>
  <ul class="demo__list">
    <?php foreach ($demo_roles as $key => $r): ?>
      <li><a class="demo__role<?= $key === $demo_role ? ' is-on' : '' ?>" href="?role=<?= urlencode($key) ?>"
             <?= $key === $demo_role ? 'aria-current="true"' : '' ?>>
        <span class="demo__av" aria-hidden="true"><?= htmlspecialchars($r['user']['initials']) ?></span>
        <?= htmlspecialchars($r['user']['role_label']) ?>
      </a></li>
    <?php endforeach; ?>
  </ul>
</details>
<?php /* ═══════════════════════ END DEMO ═══════════════════════ */ ?>

<script>
(function () {
  'use strict';

  var toasts = document.getElementById('toasts');
  function toast(msg, kind) {
    kind = kind || 'success';
    var icons = { success: 'fa-circle-check', error: 'fa-circle-exclamation', info: 'fa-circle-info' };
    var el = document.createElement('div');
    el.className = 'toast is-' + kind;
    el.innerHTML = '<i class="fa-solid ' + icons[kind] + ' toast__icon" aria-hidden="true"></i>' +
      '<div class="toast__body"><p class="toast__title">' + msg + '</p></div>' +
      '<button class="toast__close" type="button" aria-label="Dismiss"><i class="fa-solid fa-xmark"></i></button>';
    toasts.appendChild(el);
    var kill = function () { el.classList.add('is-out'); setTimeout(function () { el.remove(); }, 250); };
    el.querySelector('.toast__close').addEventListener('click', kill);
    setTimeout(kill, 3400);
  }
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-toast]');
    if (t) { e.preventDefault(); toast(t.getAttribute('data-toast')); }
  });

  var first = document.querySelector('.wizard-step');
  if (!first) { return; }                       /* no permission: nothing to wire */

  var still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var STEPS = 5, step = 1, dirty = false;
  var stepNames = <?= json_encode($steps ?? []) ?>;

  /* ── crc32, matching PHP, so the preview avatar keeps its colour ── */
  var CRC_TABLE = (function () {
    var t = [], c, n, k;
    for (n = 0; n < 256; n++) { c = n; for (k = 0; k < 8; k++) { c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1); } t[n] = c >>> 0; }
    return t;
  })();
  function crc32(str) {
    var bytes = new TextEncoder().encode(str), crc = -1, i;
    for (i = 0; i < bytes.length; i++) { crc = (crc >>> 8) ^ CRC_TABLE[(crc ^ bytes[i]) & 0xFF]; }
    return (crc ^ -1) >>> 0;
  }
  function initials(name) {
    var clean = name.replace(/^(St\.?|Saint|Holy|All)\s+/i, '');
    var p = clean.trim().split(/\s+/).filter(Boolean);
    if (!p.length) { return '?'; }
    return ((p[0] || '')[0] + (p.length > 1 ? p[p.length - 1][0] : '')).toUpperCase();
  }

  /* ── steps ── */
  function show(n) {
    step = n;
    [].forEach.call(document.querySelectorAll('[data-step]'), function (s) {
      s.hidden = parseInt(s.getAttribute('data-step'), 10) !== n;
    });
    [].forEach.call(document.querySelectorAll('[data-step-item]'), function (it) {
      var i = parseInt(it.getAttribute('data-step-item'), 10);
      it.classList.toggle('is-on', i === n);
      it.classList.toggle('is-done', i < n);
      it.querySelector('[data-goto-step]').disabled = i > n;
    });
    document.querySelector('[data-step-now]').textContent = n;
    document.querySelector('[data-step-name]').textContent = stepNames[n - 1] || '';
    document.querySelector('[data-step-bar]').style.width = (n / STEPS * 100) + '%';
    document.getElementById('backBtn').hidden = n === 1;
    document.getElementById('nextBtn').hidden = n === STEPS;
    document.getElementById('saveBtn').hidden = n !== STEPS;
    document.getElementById('errSummary').classList.remove('is-on');
    if (n === STEPS) { buildReview(); }
    window.scrollTo({ top: 0, behavior: still ? 'auto' : 'smooth' });
  }

  /* ── validation ── */
  function fieldOf(el) { return el.closest('.field'); }
  function validate(el) {
    var f = fieldOf(el);
    if (!f || el.closest('[hidden]')) { return true; }      /* skip hidden branches */
    var v = (el.value || '').trim(), ok = true;
    if (el.hasAttribute('data-req') && !v) { ok = false; }
    if (ok && el.hasAttribute('data-email') && v && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) { ok = false; }
    f.classList.toggle('is-bad', !ok);
    f.classList.toggle('is-good', ok && v !== '');
    return ok;
  }
  [].forEach.call(document.querySelectorAll('[data-req], [data-email]'), function (el) {
    el.addEventListener('blur', function () { validate(el); });
    el.addEventListener('input', function () {
      dirty = true;
      if (fieldOf(el).classList.contains('is-bad')) { validate(el); }
    });
  });

  function validateStep(n) {
    var pane = document.querySelector('[data-step="' + n + '"]');
    var bad = [];
    [].forEach.call(pane.querySelectorAll('[data-req], [data-email]'), function (el) {
      if (el.closest('[hidden]')) { return; }
      if (!validate(el)) {
        var lab = pane.querySelector('label[for="' + el.id + '"]');
        bad.push(lab ? lab.textContent.replace('*', '').trim() : el.id);
      }
    });
    if (n === 4 && document.querySelectorAll('[data-service-row]').length === 0) {
      bad.push('At least one service');
    }
    var box = document.getElementById('errSummary'), list = document.getElementById('errList');
    if (bad.length) {
      list.innerHTML = '';
      bad.forEach(function (b) { var li = document.createElement('li'); li.textContent = b; list.appendChild(li); });
      box.classList.add('is-on');
      box.scrollIntoView({ block: 'nearest', behavior: still ? 'auto' : 'smooth' });
      return false;
    }
    box.classList.remove('is-on');
    return true;
  }

  document.getElementById('nextBtn').addEventListener('click', function () {
    if (!validateStep(step)) { toast('Some fields still need attention', 'error'); return; }
    if (step < STEPS) { show(step + 1); }
  });
  document.getElementById('backBtn').addEventListener('click', function () { if (step > 1) { show(step - 1); } });
  [].forEach.call(document.querySelectorAll('[data-goto-step]'), function (b) {
    b.addEventListener('click', function () {
      var target = parseInt(b.getAttribute('data-goto-step'), 10);
      if (target <= step) { show(target); }
    });
  });

  function save() {
    if (!validateStep(step)) { toast('Some fields still need attention', 'error'); return; }
    dirty = false;
    toast('<?= addslashes(t('branch_singular')) ?> saved');
    setTimeout(function () { window.location.href = '<?= $base_url ?>branches/index.php'; }, 900);
  }
  document.getElementById('saveBtn').addEventListener('click', save);
  var saveTop = document.getElementById('saveTop');
  if (saveTop) { saveTop.addEventListener('click', save); }

  /* ── live preview ── */
  var pvName = document.getElementById('pvName'), pvAv = document.getElementById('pvAv');
  function syncPreview() {
    var name = (document.getElementById('bName').value || '').trim();
    pvName.textContent = name || 'New <?= addslashes(t('branch_singular')) ?>';
    pvAv.textContent = name ? initials(name) : '?';
    pvAv.className = 'av av--xl preview__av ' + (name ? 'av-c' + (crc32(name) % 10) : 'av-c0');

    [].forEach.call(document.querySelectorAll('[data-preview]'), function (el) {
      if (el.closest('[hidden]')) { return; }
      var key = el.getAttribute('data-preview');
      var slot = document.querySelector('[data-pv="' + key + '"]');
      if (!slot) { return; }
      var val = el.value;
      if (key === 'status') {
        slot.textContent = val ? val.charAt(0).toUpperCase() + val.slice(1) : 'Active';
        slot.className = 'spill is-' + (val || 'active');
        return;
      }
      slot.textContent = val || '—';
      slot.classList.toggle('preview__empty', !val);
    });

    var code = document.getElementById('bCode');
    if (code) { document.getElementById('pvCode').textContent = code.value; }
    var sv = document.querySelector('[data-pv="services"]');
    if (sv) { sv.textContent = document.querySelectorAll('[data-service-row]').length; }
  }
  [].forEach.call(document.querySelectorAll('[data-preview], #bName, #bCode'), function (el) {
    el.addEventListener('input', syncPreview);
    el.addEventListener('change', syncPreview);
  });

  document.getElementById('regenCode').addEventListener('click', function () {
    var n = Math.floor(Math.random() * 89 + 10);
    document.getElementById('bCode').value = '<?= addslashes(strtoupper(substr($organisation['code'], 0, 3))) ?>-PR-' + n;
    syncPreview();
    toast('New code generated', 'info');
  });

  /* ── leader mode ── */
  [].forEach.call(document.querySelectorAll('[name="leadMode"]'), function (r) {
    r.addEventListener('change', function () {
      var existing = r.value === 'existing';
      document.getElementById('leadExisting').hidden = !existing;
      document.getElementById('leadInvite').hidden = existing;
      syncPreview();
    });
  });

  /* ── phone auto-format ── */
  [].forEach.call(document.querySelectorAll('[data-phone]'), function (el) {
    el.addEventListener('input', function () {
      var d = el.value.replace(/\D/g, '').slice(0, 9);
      var out = d.slice(0, 2);
      if (d.length > 2) { out += ' ' + d.slice(2, 5); }
      if (d.length > 5) { out += ' ' + d.slice(5, 9); }
      el.value = out;
      dirty = true;
    });
  });

  /* ── coordinates feed the map placeholder ── */
  function syncCoords() {
    var la = document.getElementById('bLat').value.trim(), ln = document.getElementById('bLng').value.trim();
    var out = document.querySelector('[data-map-coords]');
    out.textContent = (la && ln) ? (la + ', ' + ln) : 'Coordinates will appear here';
  }
  document.getElementById('bLat').addEventListener('input', syncCoords);
  document.getElementById('bLng').addEventListener('input', syncCoords);

  /* ── repeatable service rows ── */
  var rowsBox = document.getElementById('serviceRows');
  var DAYS = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

  function serviceRow(day, from, to, name) {
    var d = document.createElement('div');
    d.className = 'svc-row';
    d.setAttribute('data-service-row', '');
    d.innerHTML =
      '<select class="select" aria-label="Day">' + DAYS.map(function (x) {
        return '<option' + (x === day ? ' selected' : '') + '>' + x + '</option>';
      }).join('') + '</select>' +
      '<input class="input" type="time" value="' + (from || '09:00') + '" aria-label="Start time">' +
      '<input class="input" type="time" value="' + (to || '11:00') + '" aria-label="End time">' +
      '<input class="input" placeholder="Service name" value="' + (name || '') + '" aria-label="Service name">' +
      '<input class="input" type="number" min="0" placeholder="Expected" aria-label="Expected attendance">' +
      '<button class="iconbtn" type="button" aria-label="Remove this service"><i class="fa-solid fa-xmark"></i></button>';
    d.querySelector('button').addEventListener('click', function () {
      d.remove();
      countServices();
      dirty = true;
    });
    return d;
  }
  function countServices() {
    var n = document.querySelectorAll('[data-service-row]').length;
    document.querySelector('[data-service-count]').textContent = n;
    syncPreview();
  }
  document.getElementById('addService').addEventListener('click', function () {
    rowsBox.appendChild(serviceRow());
    countServices();
    dirty = true;
  });
  /* One sensible row to start from. */
  rowsBox.appendChild(serviceRow('Sunday', '09:00', '11:00', 'First Service'));
  countServices();

  /* ── copy-from toggles enable their source dropdown ── */
  [].forEach.call(document.querySelectorAll('[data-copy-toggle]'), function (cb) {
    cb.addEventListener('change', function () {
      cb.closest('.copy-row').querySelector('[data-copy-source]').disabled = !cb.checked;
    });
  });

  /* ── review ── */
  function val(id) { var el = document.getElementById(id); return el && el.value ? el.value : '—'; }

  function buildReview() {
    var type = document.querySelector('[name="btype"]:checked');
    var mode = document.querySelector('[name="leadMode"]:checked');
    var leader = mode && mode.value === 'invite' ? val('invName') : val('leadPick');

    var services = [].slice.call(document.querySelectorAll('[data-service-row]')).map(function (r) {
      var f = r.querySelectorAll('select, input');
      return f[0].value + ' ' + f[1].value + '–' + f[2].value + (f[3].value ? ' · ' + f[3].value : '');
    });

    var blocks = [
      ['Details', 1, [
        ['Name', val('bName')], ['Code', val('bCode')],
        ['<?= addslashes(t('group_singular')) ?>', val('bGroup')],
        ['Type', type ? (type.value === 'head_office' ? 'Head Office' : '<?= addslashes(t('branch_singular')) ?>') : '—'],
        ['Established', val('bEst')], ['Status', val('bStatus')]
      ]],
      ['Location', 2, [
        ['Address', val('bAddr')], ['Suburb', val('bSuburb')],
        ['City', val('bCity')], ['Province', val('bProvince')],
        ['Coordinates', (val('bLat') !== '—' && val('bLng') !== '—') ? val('bLat') + ', ' + val('bLng') : '—']
      ]],
      ['Leadership', 3, [
        ['<?= addslashes(t('leader_title')) ?>', leader],
        ['Assistant', val('asstPick')], ['Secretary', val('secPick')], ['Treasurer', val('treasPick')]
      ]],
      ['Services', 4, services.length
        ? services.map(function (s, i) { return ['Service ' + (i + 1), s]; })
        : [['Services', 'None added']]]
    ];

    var out = document.getElementById('reviewOut');
    out.innerHTML = '';
    blocks.forEach(function (blk) {
      var wrap = document.createElement('div');
      wrap.className = 'review-block';
      var head = document.createElement('div');
      head.className = 'review-block__head';
      head.innerHTML = '<h3></h3><a href="#">Edit</a>';
      head.querySelector('h3').textContent = blk[0];
      head.querySelector('a').addEventListener('click', function (e) { e.preventDefault(); show(blk[1]); });
      wrap.appendChild(head);

      var dl = document.createElement('dl');
      dl.className = 'deflist';
      blk[2].forEach(function (pair) {
        var row = document.createElement('div');
        var dt = document.createElement('dt'); dt.textContent = pair[0];
        var dd = document.createElement('dd'); dd.textContent = pair[1] || '—';
        row.appendChild(dt); row.appendChild(dd); dl.appendChild(row);
      });
      wrap.appendChild(dl);
      out.appendChild(wrap);
    });
  }

  /* ── unsaved-changes guard ── */
  document.addEventListener('input', function () { dirty = true; });
  window.addEventListener('beforeunload', function (e) {
    if (!dirty) { return; }
    e.preventDefault();
    e.returnValue = '';
  });
  document.getElementById('cancelBtn').addEventListener('click', function (e) {
    if (dirty && !window.confirm('You have unsaved changes. Leave without saving?')) { e.preventDefault(); }
    else { dirty = false; }
  });

  syncPreview();
  show(1);
})();
</script>

<?php require __DIR__ . '/../components/footer.php'; ?>

<?php
/**
 * Mutendi CMS — Modules (static UI mockup).
 *
 * The master list of feature modules, plus bulk control of which churches get
 * which. Trial and Paying churches are the same kind of tenant here — they
 * differ only by the account-type badge and the default set applied at
 * creation. Every dataset is hardcoded; each block carries the query that will
 * replace it. Toggles, search and filters are visual only.
 */

/* Path constants only — the sidebar markup is required further down. */
$musPathsOnly = true;
require __DIR__ . '/../components/sidebar.php';
$musPathsOnly = false;

/* ── Feature modules ───────────────────────────────────────────────────────
   LATER:
     SELECT m.*, COUNT(cm.church_id) AS enabled_for
       FROM modules m
       LEFT JOIN church_modules cm ON cm.module_id = m.id AND cm.enabled = 1
      GROUP BY m.id ORDER BY m.type DESC, m.name;
   `trial_default` / `paying_default` are what a new church receives at
   creation, matching the account type chosen in the Add New Church form. */
$modules = [
    ['name'=>'Members','key'=>'members','icon'=>'fa-users','tone'=>'brand','desc'=>'Member records, households and contact details.','type'=>'Core','trial'=>true,'paying'=>true,'churches'=>47,'active'=>true],
    ['name'=>'Attendance','key'=>'attendance','icon'=>'fa-clipboard-check','tone'=>'brand','desc'=>'Service registers and attendance history.','type'=>'Core','trial'=>true,'paying'=>true,'churches'=>47,'active'=>true],
    ['name'=>'Departments','key'=>'departments','icon'=>'fa-sitemap','tone'=>'brand','desc'=>'Choirs, ushers, youth and other ministry groups.','type'=>'Core','trial'=>true,'paying'=>true,'churches'=>47,'active'=>true],
    ['name'=>'Communication','key'=>'communication','icon'=>'fa-comment-dots','tone'=>'brand','desc'=>'SMS and email to members and departments.','type'=>'Core','trial'=>true,'paying'=>true,'churches'=>47,'active'=>true],
    ['name'=>'Reports','key'=>'reports','icon'=>'fa-chart-column','tone'=>'brand','desc'=>'Membership, attendance and growth reporting.','type'=>'Core','trial'=>true,'paying'=>true,'churches'=>47,'active'=>true],

    ['name'=>'Finance & Contributions','key'=>'finance','icon'=>'fa-hand-holding-dollar','tone'=>'green','desc'=>'Tithes, offerings and contribution records.','type'=>'Optional','trial'=>true,'paying'=>true,'churches'=>38,'active'=>true],
    ['name'=>'Cell Groups','key'=>'cell_groups','icon'=>'fa-people-group','tone'=>'indigo','desc'=>'Home cells, leaders and cell attendance.','type'=>'Optional','trial'=>true,'paying'=>true,'churches'=>29,'active'=>true],
    ['name'=>'Events & Calendar','key'=>'events','icon'=>'fa-calendar-days','tone'=>'gold','desc'=>'Services, meetings and the church calendar.','type'=>'Optional','trial'=>true,'paying'=>true,'churches'=>34,'active'=>true],
    ['name'=>'Sermons & Media','key'=>'media','icon'=>'fa-microphone-lines','tone'=>'indigo','desc'=>'Sermon archive, audio and media library.','type'=>'Optional','trial'=>false,'paying'=>true,'churches'=>21,'active'=>true],
    ['name'=>'Assets & Inventory','key'=>'assets','icon'=>'fa-boxes-stacked','tone'=>'grey','desc'=>'Church property, equipment and stock.','type'=>'Optional','trial'=>false,'paying'=>true,'churches'=>15,'active'=>true],
    ['name'=>'Payroll','key'=>'payroll','icon'=>'fa-money-check-dollar','tone'=>'green','desc'=>'Staff salaries, stipends and payslips.','type'=>'Optional','trial'=>false,'paying'=>false,'churches'=>6,'active'=>false],
    ['name'=>'Visitors & Follow-Up','key'=>'visitors','icon'=>'fa-user-plus','tone'=>'gold','desc'=>'First-time visitors and follow-up tracking.','type'=>'Optional','trial'=>true,'paying'=>true,'churches'=>31,'active'=>true],
    ['name'=>'Projects & Pledges','key'=>'projects','icon'=>'fa-trowel-bricks','tone'=>'brand','desc'=>'Building projects, pledges and progress.','type'=>'Optional','trial'=>false,'paying'=>true,'churches'=>18,'active'=>true],
    ['name'=>'Library','key'=>'library','icon'=>'fa-book-open','tone'=>'grey','desc'=>'Book lending and resource catalogue.','type'=>'Optional','trial'=>false,'paying'=>false,'churches'=>4,'active'=>false],
];

$optional     = array_values(array_filter($modules, fn($m) => $m['type'] === 'Optional'));
$coreCount    = count($modules) - count($optional);
$activeCount  = count(array_filter($modules, fn($m) => $m['active']));

/* ── Stat strip — derived so the tiles cannot drift from the list. ───────── */
$statTiles = [
    ['label' => 'Total Modules', 'value' => count($modules),              'tone' => 'indigo', 'icon' => 'fa-cubes',        'on' => true],
    ['label' => 'Active',        'value' => $activeCount,                 'tone' => 'green',  'icon' => 'fa-circle-check', 'on' => false],
    ['label' => 'Core Modules',  'value' => $coreCount,                   'tone' => 'brand',  'icon' => 'fa-lock',         'on' => false],
    ['label' => 'Disabled',      'value' => count($modules) - $activeCount,'tone' => 'grey',  'icon' => 'fa-ban',          'on' => false],
];

/* ── Church access matrix ──────────────────────────────────────────────────
   LATER:
     SELECT c.id, c.name, c.code, c.account_type, cm.module_id
       FROM churches c LEFT JOIN church_modules cm ON cm.church_id = c.id AND cm.enabled = 1
      ORDER BY c.name;
   `on` lists the module keys currently enabled for that church. */
$matrix = [
    ['ZM','ZCC Mbungo','ZCC-001','Paying',['finance','cell_groups','events','media','visitors','projects']],
    ['AW','AFM Waterfalls','AFM-002','Paying',['finance','cell_groups','events','media','assets','visitors','projects']],
    ['GM','Grace Ministries','GRM-003','Paying',['finance','events','visitors']],
    ['JM','Johane Masowe eChishanu','JME-004','Paying',['finance','cell_groups','events','visitors','projects']],
    ['CC','Celebration Church Harare','CCH-005','Paying',['finance','cell_groups','events','media','assets','payroll','visitors','projects']],
    ['FG','Family of God Bulawayo','FOG-006','Trial',['finance','events','visitors']],
    ['MM','Methodist Mutare Circuit','MMC-007','Paying',['finance','cell_groups','events','visitors']],
    ['NL','New Life Chitungwiza','NLC-061','Trial',['finance','cell_groups','events','visitors']],
    ['RH','Rhema Bulawayo','RHB-063','Trial',['finance','events','visitors']],
    ['AD','Anglican Diocese Masvingo','ADM-009','Paying',['finance','cell_groups','events','media','assets','visitors','projects','library']],
    ['ST','St Thomas Mutare','STM-064','Trial',['finance','events']],
    ['UF','UFIC Chinhoyi','UFI-013','Paying',['finance','cell_groups','events','media','visitors','projects']],
];

$activePage    = 'access/modules';
$sidebarBadges = ['pending' => 10, 'expiring' => 12];
$sidebarUser   = ['name' => 'Super Admin', 'role' => 'System Owner'];

/** Tick or cross for the default indicators. */
function yn(bool $on): string {
    return $on
        ? '<i class="fa-solid fa-check yn yn--yes"></i>'
        : '<i class="fa-solid fa-xmark yn yn--no"></i>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Modules — Mutendi CMS Super Admin</title>
<link rel="icon" type="image/png" href="<?= MUS_ROOT_URL ?>/resources/img/logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="<?= $base_url ?>assets/css/style.css">
</head>
<body>

<div class="page-grid" aria-hidden="true"></div>
<div class="page-glow" aria-hidden="true"></div>

<!-- ==================== TOP BAR ==================== -->
<header class="topbar">
  <div class="topbar__search">
    <i class="fa-solid fa-magnifying-glass"></i>
    <input type="search" placeholder="Search church, admin, phone...">
  </div>
  <div class="topbar__right">
    <button class="icon-btn icon-btn--bell" type="button" aria-label="Notifications">
      <i class="fa-regular fa-bell"></i><span class="bell-badge">5</span>
    </button>
    <a class="btn btn--primary" href="<?= $base_url ?>churches/all.php"><i class="fa-solid fa-plus"></i> <span>Add Church</span></a>
    <div class="avatar-menu">
      <button class="avatar-menu__trigger" type="button">
        <span class="avatar">SA</span><i class="fa-solid fa-chevron-down"></i>
      </button>
      <div class="avatar-menu__list">
        <a href="<?= $base_url ?>admin/profile.php"><i class="fa-regular fa-user"></i> Profile</a>
        <a href="#"><i class="fa-solid fa-gear"></i> Settings</a>
        <a href="<?= $base_url ?>logout.php" class="is-danger"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
      </div>
    </div>
  </div>
</header>

<?php require __DIR__ . '/../components/sidebar.php'; ?>

<main class="main">

  <div class="page-head">
    <div>
      <h1>Modules</h1>
      <p class="crumb">
        <a href="<?= $base_url ?>index.php">Dashboard</a> <i class="fa-solid fa-chevron-right"></i>
        Access &amp; Licensing <i class="fa-solid fa-chevron-right"></i> Modules
      </p>
      <p class="page-hint">Define the features available in the system and control which churches can access them.</p>
    </div>
    <div class="head-actions">
      <button class="btn btn--primary" type="button" data-modal="modalModule"><i class="fa-solid fa-plus"></i> Add Module</button>
    </div>
  </div>

  <div class="statstrip">
    <?php foreach ($statTiles as $t): ?>
      <a class="stat-tile stat-tile--<?= $t['tone'] ?><?= $t['on'] ? ' is-on' : '' ?>" href="#">
        <span class="stat-tile__icon"><i class="fa-solid <?= $t['icon'] ?>"></i></span>
        <span class="stat-tile__body">
          <span class="stat-tile__value"><?= $t['value'] ?></span>
          <span class="stat-tile__label"><?= htmlspecialchars($t['label']) ?></span>
        </span>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Tabs -->
  <div class="tabs" role="tablist">
    <button class="tab is-on" type="button" role="tab" data-tab="all">All Modules</button>
    <button class="tab" type="button" role="tab" data-tab="access">Church Access</button>
    <button class="tab" type="button" role="tab" data-tab="defaults">Default Sets</button>
  </div>

  <!-- ───────────────── TAB 1: All Modules ───────────────── -->
  <div class="tabpanel" data-panel="all">
    <div class="modgrid">
      <?php foreach ($modules as $m): ?>
        <?php $core = $m['type'] === 'Core'; ?>
        <div class="modcard">
          <div class="modcard__top">
            <span class="modcard__icon modcard__icon--<?= $m['tone'] ?>"><i class="fa-solid <?= $m['icon'] ?>"></i></span>
            <span class="modcard__head">
              <strong><?= htmlspecialchars($m['name']) ?></strong>
              <code class="keytext"><?= $m['key'] ?></code>
            </span>
            <label class="switch" title="<?= $core ? 'Core modules cannot be disabled' : 'Enable or disable system-wide' ?>">
              <input type="checkbox"<?= $m['active'] ? ' checked' : '' ?><?= $core ? ' disabled' : '' ?>>
              <span class="switch__track"></span>
            </label>
            <?php if ($core): ?><i class="fa-solid fa-lock modcard__lock" title="Core module — always on"></i><?php endif; ?>
          </div>

          <p class="modcard__desc"><?= htmlspecialchars($m['desc']) ?></p>

          <span class="pill <?= $core ? 'pill--trial' : 'pill--suspended' ?> modcard__type"><?= $m['type'] ?></span>

          <div class="modcard__defaults">
            <span>Default for Trial <?= yn($m['trial']) ?></span>
            <span>Default for Paying <?= yn($m['paying']) ?></span>
          </div>

          <p class="modcard__count">Enabled for <strong><?= $m['churches'] ?></strong> of 47 churches</p>

          <div class="modcard__foot">
            <a href="#" data-modal="modalModule">Edit</a>
            <a href="#" data-modal="modalChurches">Manage Churches</a>
            <?php if (!$core): ?><a href="#" class="is-danger" data-modal="modalDeleteModule">Delete</a><?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ───────────────── TAB 2: Church Access ───────────────── -->
  <div class="tabpanel" data-panel="access" hidden>
    <div class="card filterbar">
      <div class="filterbar__row filterbar__row--4">
        <label class="field field--search">
          <span class="field__label">Search</span>
          <span class="field__input">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" placeholder="Search church by name or code...">
          </span>
        </label>
        <label class="field"><span class="field__label">Account Type</span>
          <select><option>All</option><option>Trial</option><option>Paying</option></select></label>
        <label class="field"><span class="field__label">Module</span>
          <select><option>All Modules</option><?php foreach ($optional as $m): ?><option><?= htmlspecialchars($m['name']) ?></option><?php endforeach; ?></select></label>
        <label class="field"><span class="field__label">Status</span>
          <select><option>All</option><option>Enabled</option><option>Disabled</option></select></label>
      </div>
      <div class="filterbar__foot">
        <div class="filterbar__actions">
          <button class="btn btn--primary" type="button"><i class="fa-solid fa-filter"></i> Apply Filters</button>
          <a class="link-reset" href="#">Reset</a>
        </div>
      </div>
    </div>

    <div class="bulkbar" id="bulkBar" hidden>
      <span class="bulkbar__count"><strong id="bulkCount">0</strong> selected</span>
      <div class="bulkbar__actions">
        <button class="btn btn--sm" type="button"><i class="fa-solid fa-toggle-on"></i> Enable Module</button>
        <button class="btn btn--sm" type="button"><i class="fa-solid fa-toggle-off"></i> Disable Module</button>
        <button class="btn btn--sm" type="button"><i class="fa-solid fa-copy"></i> Copy Settings From&hellip;</button>
        <button class="btn btn--sm" type="button"><i class="fa-solid fa-star"></i> Apply Trial Defaults</button>
        <button class="btn btn--sm" type="button"><i class="fa-solid fa-credit-card"></i> Apply Paying Defaults</button>
      </div>
      <button class="bulkbar__clear" type="button" id="bulkClear" aria-label="Clear selection"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <div class="card">
      <div class="table-wrap">
        <table class="table table--churches table--matrix">
          <thead>
            <tr>
              <th class="col-church"><input type="checkbox" id="checkAll" aria-label="Select all churches"> Church</th>
              <?php foreach ($optional as $m): ?>
                <th class="ta-center"><span class="matrix__head"><?= htmlspecialchars($m['name']) ?></span></th>
              <?php endforeach; ?>
              <th class="ta-right">Modules Enabled</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($matrix as [$initials, $name, $code, $acct, $on]): ?>
              <tr>
                <td class="col-church">
                  <div class="church">
                    <input type="checkbox" class="row-check" aria-label="Select <?= htmlspecialchars($name) ?>">
                    <span class="church__avatar"><?= $initials ?></span>
                    <span class="church__text">
                      <strong><?= htmlspecialchars($name) ?></strong>
                      <small><?= $code ?> <span class="pill pill--<?= strtolower($acct) ?> pill--xs"><?= $acct ?></span></small>
                    </span>
                  </div>
                </td>
                <?php foreach ($optional as $m): ?>
                  <td class="ta-center">
                    <label class="switch switch--sm">
                      <input type="checkbox"<?= in_array($m['key'], $on, true) ? ' checked' : '' ?>>
                      <span class="switch__track"></span>
                    </label>
                  </td>
                <?php endforeach; ?>
                <td class="ta-right strong"><?= count($on) ?>/<?= count($optional) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="tablefoot">
        <p class="tablefoot__count">Showing 1 to <?= count($matrix) ?> of 47 churches</p>
        <nav class="pagination" aria-label="Pagination">
          <a class="pagination__btn is-disabled" href="#">Previous</a>
          <a class="pagination__btn is-on" href="#">1</a>
          <a class="pagination__btn" href="#">2</a>
          <a class="pagination__btn" href="#">3</a>
          <a class="pagination__btn" href="#">4</a>
          <a class="pagination__btn" href="#">Next</a>
        </nav>
      </div>
    </div>
  </div>

  <!-- ───────────────── TAB 3: Default Sets ───────────────── -->
  <div class="tabpanel" data-panel="defaults" hidden>
    <div class="grid grid--2">
      <?php foreach ([['Trial', 'trial', 'star'], ['Paying', 'paying', 'credit-card']] as [$label, $key, $icon]): ?>
        <div class="card">
          <div class="card__head">
            <h2><i class="fa-solid fa-<?= $icon ?>"></i> <?= $label ?> Defaults</h2>
          </div>
          <ul class="deflist">
            <?php foreach ($optional as $m): ?>
              <li>
                <span class="deflist__name">
                  <i class="fa-solid <?= $m['icon'] ?>"></i>
                  <?= htmlspecialchars($m['name']) ?>
                </span>
                <label class="switch">
                  <input type="checkbox"<?= $m[$key] ? ' checked' : '' ?>>
                  <span class="switch__track"></span>
                </label>
              </li>
            <?php endforeach; ?>
          </ul>
          <div class="card__foot card__foot--split">
            <span class="defnote">Applied automatically when a new church is created as <?= $label ?>.</span>
            <button class="btn btn--primary btn--sm" type="button">Save Defaults</button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <footer class="foot">
    <p class="foot__brand">Mutendi CMS</p>
    <p class="foot__copy">&copy; <?= date('Y') ?> Mutendi. All rights reserved.</p>
    <p class="foot__meta">Super Admin &middot; v1.0</p>
  </footer>

</main>

<!-- ==================== MODALS (static) ==================== -->

<!-- a) ADD / EDIT MODULE -->
<div class="modal" id="modalModule" hidden>
  <div class="modal__box">
    <div class="modal__head">
      <h2><i class="fa-solid fa-cubes"></i> Add Module</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <div class="field-row">
        <label class="field"><span class="field__label">Module name</span>
          <input type="text" placeholder="e.g. Finance &amp; Contributions"></label>
        <label class="field"><span class="field__label">System key</span>
          <input type="text" placeholder="finance" class="keyinput"></label>
      </div>
      <label class="field"><span class="field__label">Description</span>
        <textarea rows="2" placeholder="One line describing what this module does."></textarea></label>

      <span class="field__label">Icon</span>
      <div class="iconpick">
        <?php foreach (['fa-users','fa-hand-holding-dollar','fa-people-group','fa-calendar-days','fa-microphone-lines',
                        'fa-boxes-stacked','fa-money-check-dollar','fa-user-plus','fa-trowel-bricks','fa-book-open',
                        'fa-clipboard-check','fa-sitemap','fa-comment-dots','fa-chart-column'] as $i => $ic): ?>
          <label class="iconpick__opt">
            <input type="radio" name="modicon"<?= $i === 1 ? ' checked' : '' ?>>
            <span><i class="fa-solid <?= $ic ?>"></i></span>
          </label>
        <?php endforeach; ?>
      </div>

      <span class="field__label">Colour</span>
      <div class="swatches">
        <?php foreach (['brand','green','gold','berry','indigo','grey'] as $i => $tone): ?>
          <label class="swatch">
            <input type="radio" name="modtone"<?= $i === 0 ? ' checked' : '' ?>>
            <span class="swatch__dot swatch__dot--<?= $tone ?>"></span>
          </label>
        <?php endforeach; ?>
      </div>

      <span class="field__label">Type</span>
      <div class="radios">
        <label class="radio"><input type="radio" name="modtype"><span>Core</span></label>
        <label class="radio"><input type="radio" name="modtype" checked><span>Optional</span></label>
      </div>

      <label class="check-row"><input type="checkbox" checked>
        <span>Enabled by default for new <strong>Trial</strong> churches</span></label>
      <label class="check-row"><input type="checkbox" checked>
        <span>Enabled by default for new <strong>Paying</strong> churches</span></label>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--primary" type="button">Save Module</button>
    </div>
  </div>
</div>

<!-- b) MANAGE CHURCHES -->
<div class="modal" id="modalChurches" hidden>
  <div class="modal__box">
    <div class="modal__head">
      <h2><i class="fa-solid fa-hand-holding-dollar"></i> Finance &amp; Contributions</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <div class="field-row">
        <label class="field"><span class="field__label">Search</span>
          <span class="field__input"><i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" placeholder="Search churches..."></span></label>
        <label class="field"><span class="field__label">Account type</span>
          <select><option>All</option><option>Trial</option><option>Paying</option></select></label>
      </div>

      <div class="picklist__bar">
        <span><strong id="pickCount">34</strong> of 47 selected</span>
        <span class="picklist__links">
          <a href="#" id="pickAll">Select All</a>
          <a href="#" id="pickNone">Deselect All</a>
        </span>
      </div>

      <ul class="picklist" id="pickList">
        <?php foreach ($matrix as $i => [$initials, $name, $code, $acct, $on]): ?>
          <li>
            <span class="church">
              <span class="church__avatar"><?= $initials ?></span>
              <span class="church__text">
                <strong><?= htmlspecialchars($name) ?></strong>
                <small><?= $code ?> <span class="pill pill--<?= strtolower($acct) ?> pill--xs"><?= $acct ?></span></small>
              </span>
            </span>
            <label class="switch">
              <input type="checkbox" class="pick-check"<?= in_array('finance', $on, true) ? ' checked' : '' ?>>
              <span class="switch__track"></span>
            </label>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--primary" type="button">Save Changes</button>
    </div>
  </div>
</div>

<!-- c) DELETE MODULE -->
<div class="modal" id="modalDeleteModule" hidden>
  <div class="modal__box modal__box--sm">
    <div class="modal__head">
      <h2><i class="fa-solid fa-trash note--berry"></i> Delete Module</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <p class="modal__warn"><i class="fa-solid fa-triangle-exclamation"></i>
        Deleting <strong>Finance &amp; Contributions</strong> removes it from every church. Any data captured through this module stays in the database but becomes inaccessible from the app.</p>
      <label class="field"><span class="field__label">Type <strong>finance</strong> to confirm</span>
        <input type="text" placeholder="finance"></label>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--danger-solid" type="button">Delete Module</button>
    </div>
  </div>
</div>

<script>
/* Tabs, bulk-selection bar, pick list count, dropdowns and modals. */
(function () {
  'use strict';

  /* --- Tabs --- */
  var tabs = [].slice.call(document.querySelectorAll('.tab[data-tab]'));
  tabs.forEach(function (t) {
    t.addEventListener('click', function () {
      tabs.forEach(function (x) { x.classList.toggle('is-on', x === t); });
      document.querySelectorAll('.tabpanel').forEach(function (p) {
        p.hidden = p.dataset.panel !== t.dataset.tab;
      });
    });
  });

  /* --- Bulk selection on the matrix --- */
  var all   = document.getElementById('checkAll'),
      rows  = [].slice.call(document.querySelectorAll('.row-check')),
      bar   = document.getElementById('bulkBar'),
      count = document.getElementById('bulkCount');

  if (all) {
    var refresh = function () {
      var n = rows.filter(function (c) { return c.checked; }).length;
      count.textContent = n;
      bar.hidden = n === 0;
      all.checked = n === rows.length && n > 0;
      all.indeterminate = n > 0 && n < rows.length;
    };
    all.addEventListener('change', function () {
      rows.forEach(function (c) { c.checked = all.checked; });
      refresh();
    });
    rows.forEach(function (c) { c.addEventListener('change', refresh); });
    document.getElementById('bulkClear').addEventListener('click', function () {
      rows.forEach(function (c) { c.checked = false; });
      all.checked = false;
      refresh();
    });
  }

  /* --- Manage Churches pick list --- */
  var picks = [].slice.call(document.querySelectorAll('.pick-check')),
      pickCount = document.getElementById('pickCount');
  if (picks.length) {
    var tally = function () { pickCount.textContent = picks.filter(function (p) { return p.checked; }).length; };
    picks.forEach(function (p) { p.addEventListener('change', tally); });
    document.getElementById('pickAll').addEventListener('click', function (e) {
      e.preventDefault(); picks.forEach(function (p) { p.checked = true; }); tally();
    });
    document.getElementById('pickNone').addEventListener('click', function (e) {
      e.preventDefault(); picks.forEach(function (p) { p.checked = false; }); tally();
    });
    tally();
  }

  /* --- Dropdowns --- */
  document.addEventListener('click', function (e) {
    var trigger = e.target.closest('.dropdown__trigger');
    document.querySelectorAll('.dropdown.is-open').forEach(function (d) {
      if (!trigger || d !== trigger.parentNode) { d.classList.remove('is-open'); }
    });
    if (trigger) { e.preventDefault(); trigger.parentNode.classList.toggle('is-open'); }
  });

  /* --- Modals --- */
  function close(m) { m.hidden = true; document.body.classList.remove('modal-open'); }
  document.addEventListener('click', function (e) {
    var opener = e.target.closest('[data-modal]');
    if (opener) {
      e.preventDefault();
      document.querySelectorAll('.modal:not([hidden])').forEach(function (m) { m.hidden = true; });
      var m = document.getElementById(opener.dataset.modal);
      if (m) { m.hidden = false; document.body.classList.add('modal-open'); }
      return;
    }
    if (e.target.closest('[data-close]') || e.target.classList.contains('modal')) {
      var box = e.target.closest('.modal');
      if (box) { close(box); }
    }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal:not([hidden])').forEach(close);
      document.querySelectorAll('.dropdown.is-open').forEach(function (d) { d.classList.remove('is-open'); });
    }
  });
})();
</script>
</body>
</html>

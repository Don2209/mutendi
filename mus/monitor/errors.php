<?php
/**
 * Mutendi CMS — Error Log (static UI mockup).
 *
 * System errors and exceptions, so problems are caught before a church
 * reports them. Every dataset is hardcoded; each block carries its query.
 */

/* The Device & Session Details modal is defined once in activity.php. */
$deviceModalOnly = true;
require __DIR__ . '/activity.php';
$deviceModalOnly = false;

$musPathsOnly = true;
require __DIR__ . '/../components/sidebar.php';
$musPathsOnly = false;

/* ── Errors over 14 days, stacked by severity ──────────────────────────────
   LATER: daily COUNT(*) GROUP BY severity. */
$errDays  = ['12','13','14','15','16','17','18','19','20','21','22','23','24','25'];
$sevCrit  = [0,1,0,0,2,0,1,0,0,1,0,0,1,2];
$sevError = [3,2,4,1,5,2,3,2,4,3,2,1,4,5];
$sevWarn  = [8,6,9,5,11,7,8,6,10,9,7,5,9,12];
$sevNotice= [12,9,14,8,16,11,13,10,15,13,10,8,14,17];

/* ── Errors by type ────────────────────────────────────────────────────────
   LATER: SELECT type, COUNT(*) FROM error_logs GROUP BY type; */
$errTypes = [['Database',14,'#662F97'],['PHP',11,'#A93254'],['Permission',7,'#96701F'],
             ['Validation',9,'#5A57B5'],['Network',4,'#1E8265'],['Other',3,'#8C8398']];

/* ── The error list ────────────────────────────────────────────────────────
   LATER:
     SELECT e.*, c.name AS church, c.code, u.name AS user
       FROM error_logs e
       LEFT JOIN churches c ON c.id = e.church_id
       LEFT JOIN admin_users u ON u.id = e.user_id
      WHERE (:severity IS NULL OR e.severity = :severity)
        AND (:status IS NULL OR e.status = :status)
      ORDER BY e.created_at DESC; */
$errors = [
 ['25 Aug 2026','14:41:02','Critical','SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded','/app/Models/Church.php:214','Database','ZCC Mbungo','ZCC-001','Paying','Super Admin','System Owner',7,'197.221.44.18','Unresolved'],
 ['25 Aug 2026','14:12:37','Error','Call to a member function format() on null','/app/Reports/Renewal.php:88','PHP','—','','','System','',3,'127.0.0.1','Unresolved'],
 ['25 Aug 2026','13:55:19','Warning','Undefined array key "expiry_date"','/app/Views/churches/row.php:42','PHP','Grace Ministries','GRM-003','Paying','Rutendo Chikore','Secretary',12,'196.27.88.140','Unresolved'],
 ['25 Aug 2026','13:30:44','Error','Permission denied writing to /storage/backups','/app/Jobs/Backup.php:61','Permission','—','','','System','',2,'127.0.0.1','Unresolved'],
 ['25 Aug 2026','13:02:11','Notice','Deprecated: strftime() is deprecated','/app/Helpers/Date.php:19','PHP','—','','','System','',48,'127.0.0.1','Ignored'],
 ['25 Aug 2026','12:44:58','Critical','Connection refused to SMS gateway endpoint','/app/Services/Sms.php:130','Network','Celebration Church Harare','CCH-005','Paying','Loveness Mhaka','Treasurer',4,'102.130.44.7','Unresolved'],
 ['25 Aug 2026','12:19:03','Warning','Validation failed: phone must match +263 format','/app/Requests/MemberRequest.php:57','Validation','AFM Waterfalls','AFM-002','Paying','Simba Banda','Pastor',9,'41.221.16.90','Resolved'],
 ['25 Aug 2026','11:52:40','Error','SQLSTATE[23000]: Duplicate entry for key members.email_unique','/app/Models/Member.php:96','Database','Johane Masowe eChishanu','JME-004','Paying','Munashe Zvobgo','Church Admin',5,'196.43.132.55','Resolved'],
 ['25 Aug 2026','11:28:17','Warning','Session token regenerated mid-request','/app/Middleware/Session.php:34','PHP','—','','','System','',6,'127.0.0.1','Ignored'],
 ['25 Aug 2026','10:57:55','Error','Failed to open stream: No such file /storage/uploads/tmp','/app/Services/Upload.php:72','Permission','UFIC Chinhoyi','UFI-013','Paying','Danai Kanyemba','Department Head',3,'41.221.19.204','Unresolved'],
 ['25 Aug 2026','10:22:36','Notice','Undefined variable $province in view','/app/Views/members/form.php:88','PHP','New Life Chitungwiza','NLC-061','Trial','Anesu Mabika','Church Admin',14,'102.130.51.88','Resolved'],
 ['25 Aug 2026','09:48:29','Warning','Slow query: 2.4s on activity_logs','/app/Models/ActivityLog.php:41','Database','—','','','System','',18,'127.0.0.1','Unresolved'],
 ['25 Aug 2026','09:14:07','Error','cURL error 28: Operation timed out after 30000ms','/app/Services/Email.php:104','Network','Methodist Mutare Circuit','MMC-007','Paying','Gilbert Nyathi','Pastor',2,'41.85.220.14','Resolved'],
 ['25 Aug 2026','08:39:52','Notice','Cache miss on church_modules lookup','/app/Cache/Modules.php:27','Other','—','','','System','',31,'127.0.0.1','Ignored'],
 ['25 Aug 2026','08:05:14','Warning','Validation failed: membership_number already in use','/app/Requests/MemberRequest.php:71','Validation','Anglican Diocese Masvingo','ADM-009','Paying','Edmore Marange','Church Admin',7,'196.27.104.19','Resolved'],
];

/* ── Headline ──────────────────────────────────────────────────────────────
   Counted off $errors so the tiles can never drift from the table below.
   LATER: COUNT(*) over `error_logs` grouped by severity, status and church. */
$sevOf    = array_column($errors, 2);
$statusOf = array_column($errors, 13);
$hitChurches = array_filter(array_column($errors, 6), fn ($c) => $c !== '—');

$tiles = [
    ['Errors Today',      (string) (count(array_keys($sevOf, 'Critical')) + count(array_keys($sevOf, 'Error'))),
                            'berry',  'fa-circle-exclamation',   true],
    ['Warnings',          (string) count(array_keys($sevOf, 'Warning')),
                            'gold',   'fa-triangle-exclamation', false],
    ['Unresolved',        (string) count(array_keys($statusOf, 'Unresolved')),
                            'brand',  'fa-clock-rotate-left',    false],
    ['Affected Churches', (string) count(array_unique($hitChurches)),
                            'grey',   'fa-church',               false],
];
$churchList = ['All Churches','ZCC Mbungo','AFM Waterfalls','Grace Ministries','Johane Masowe eChishanu',
               'Celebration Church Harare','Methodist Mutare Circuit','Anglican Diocese Masvingo',
               'New Life Chitungwiza','UFIC Chinhoyi'];

$activePage    = 'monitor/errors';
$sidebarBadges = ['pending' => 10, 'expiring' => 12];
$sidebarUser   = ['name' => 'Super Admin', 'role' => 'System Owner'];
$pageTitle     = 'Error Log';
$pageHint      = 'System errors and exceptions across the platform.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $pageTitle ?> — Mutendi CMS Super Admin</title>
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
      <h1><?= $pageTitle ?></h1>
      <p class="crumb">
        <a href="<?= $base_url ?>index.php">Dashboard</a> <i class="fa-solid fa-chevron-right"></i>
        Monitoring <i class="fa-solid fa-chevron-right"></i> <?= $pageTitle ?>
      </p>
      <p class="page-hint"><?= $pageHint ?></p>
    </div>
    <div class="head-actions">
      <div class="rangebar">
        <div class="range">
          <?php foreach (['Today','7 Days','30 Days','90 Days'] as $r): ?>
            <a href="#" class="range__btn<?= $r === 'Today' ? ' is-on' : '' ?>"><?= $r ?></a>
          <?php endforeach; ?>
          <button class="range__btn" type="button" id="rangeCustom">Custom</button>
        </div>
        <div class="rangedates" id="rangeDates" hidden>
          <input type="date" aria-label="From"><span>to</span><input type="date" aria-label="To">
        </div>
      </div>
      <div class="dropdown">
        <button class="btn dropdown__trigger" type="button">
          <i class="fa-solid fa-file-export"></i> Export <i class="fa-solid fa-chevron-down dropdown__caret"></i>
        </button>
        <div class="dropdown__menu dropdown__menu--right">
          <a href="#"><i class="fa-solid fa-file-csv"></i> CSV</a>
          <a href="#"><i class="fa-solid fa-file-excel"></i> Excel</a>
          <a href="#"><i class="fa-solid fa-file-pdf"></i> PDF</a>
        </div>
      </div>
      <button class="ico-btn ico-btn--framed" type="button" title="Print" aria-label="Print"><i class="fa-solid fa-print"></i></button>
    </div>
  </div>

  <div class="statstrip">
    <?php foreach ($tiles as [$l,$v,$t,$ic,$on]): ?>
      <a class="stat-tile stat-tile--<?= $t ?><?= $on ? ' is-on' : '' ?>" href="#">
        <span class="stat-tile__icon"><i class="fa-solid <?= $ic ?>"></i></span>
        <span class="stat-tile__body"><span class="stat-tile__value"><?= $v ?></span>
          <span class="stat-tile__label"><?= $l ?></span></span></a>
    <?php endforeach; ?>
  </div>

  <div class="grid grid--2-1">
    <div class="card card--chartfill">
      <div class="card__head"><h2>Errors Over Time</h2><span class="card__note">Last 14 days</span></div>
      <div class="card__body"><div class="chart-wrap"><canvas id="errChart"></canvas></div></div>
    </div>
    <div class="card">
      <div class="card__head"><h2>Errors by Type</h2></div>
      <div class="card__body">
        <div class="chart-wrap chart-wrap--donut"><canvas id="typeChart"></canvas></div>
        <ul class="legend">
          <?php foreach ($errTypes as [$l,$c,$col]): ?>
            <li><span class="legend__dot" style="background: <?= $col ?>"></span>
              <span class="legend__label"><?= $l ?></span><span class="legend__count"><?= $c ?></span></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>

  <div class="card filterbar">
    <div class="filterbar__row">
      <label class="field field--search"><span class="field__label">Search</span>
        <span class="field__input"><i class="fa-solid fa-magnifying-glass"></i>
          <input type="search" placeholder="Search by message, file or type..."></span></label>
      <label class="field"><span class="field__label">Severity</span>
        <select><option>All</option><option>Critical</option><option>Error</option><option>Warning</option><option>Notice</option></select></label>
      <label class="field"><span class="field__label">Type</span>
        <select><option>All Types</option><?php foreach ($errTypes as $t): ?><option><?= $t[0] ?></option><?php endforeach; ?></select></label>
      <label class="field"><span class="field__label">Church</span>
        <select><?php foreach ($churchList as $c): ?><option><?= $c ?></option><?php endforeach; ?></select></label>
      <label class="field"><span class="field__label">Status</span>
        <select><option>All</option><option>Unresolved</option><option>Resolved</option><option>Ignored</option></select></label>
      <label class="field"><span class="field__label">From</span><input type="date"></label>
      <label class="field"><span class="field__label">To</span><input type="date"></label>
    </div>
    <div class="filterbar__foot">
      <div class="filterbar__actions">
        <button class="btn btn--primary" type="button"><i class="fa-solid fa-filter"></i> Apply Filters</button>
        <a class="link-reset" href="#">Reset</a>
      </div>
    </div>
  </div>

  <div class="bulkbar" data-bulkbar="errors" hidden>
    <span class="bulkbar__count"><strong data-bulkcount="errors">0</strong> selected</span>
    <div class="bulkbar__actions">
      <button class="btn btn--sm btn--go" type="button"><i class="fa-solid fa-circle-check"></i> Mark Resolved</button>
      <button class="btn btn--sm" type="button"><i class="fa-solid fa-eye-slash"></i> Mark Ignored</button>
      <button class="btn btn--sm btn--danger" type="button"><i class="fa-solid fa-trash"></i> Delete</button>
    </div>
    <button class="bulkbar__clear" type="button" data-bulkclear="errors" aria-label="Clear selection"><i class="fa-solid fa-xmark"></i></button>
  </div>

  <div class="card">
    <div class="table-wrap">
      <table class="table table--churches">
        <thead>
          <tr><th class="col-check"><input type="checkbox" data-checkall="errors" aria-label="Select all errors"></th>
            <th class="col-num">#</th><th>Timestamp</th><th>Severity</th><th>Error Message</th><th>Type</th>
            <th>Church</th><th>Triggered By</th><th class="ta-center">Occurrences</th><th>IP</th>
            <th>Status</th><th class="col-actions">Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($errors as $i => $e): ?>
            <?php [$date,$time,$sev,$msg,$file,$type,$church,$code,$acct,$user,$role,$count,$ip,$status] = $e; ?>
            <tr>
              <td class="col-check"><input type="checkbox" data-rowcheck="errors" aria-label="Select error <?= $i + 1 ?>"></td>
              <td class="col-num muted"><?= $i + 1 ?></td>
              <td class="nowrap"><span class="stack"><strong><?= $date ?></strong><small><?= $time ?></small></span></td>
              <td><span class="pill pill--<?= strtolower($sev) ?>"><?= $sev ?></span></td>
              <td>
                <span class="church__text anntext">
                  <strong><?= htmlspecialchars($msg) ?></strong>
                  <small><code class="keytext"><?= $file ?></code></small>
                </span>
              </td>
              <td><span class="role"><?= $type ?></span></td>
              <td class="nowrap">
                <?php if ($church === '—'): ?><span class="muted">&mdash;</span>
                <?php else: ?>
                  <span class="stack"><strong><?= htmlspecialchars($church) ?></strong>
                    <small><?= $code ?> <span class="pill pill--<?= strtolower($acct) ?> pill--xs"><?= $acct ?></span></small></span>
                <?php endif; ?>
              </td>
              <td class="nowrap">
                <?php if ($role === ''): ?><span class="muted"><?= $user ?></span>
                <?php else: ?><span class="stack"><strong><?= $user ?></strong><small><?= $role ?></small></span><?php endif; ?>
              </td>
              <td class="ta-center"><span class="countdown countdown--berry">&times;<?= $count ?></span></td>
              <td><code class="keytext"><?= $ip ?></code></td>
              <td><span class="pill pill--<?= strtolower($status) ?>"><?= $status ?></span></td>
              <td class="col-actions">
                <div class="row-actions">
                  <button class="ico-btn" type="button" title="View Details" aria-label="View Details" data-modal="modalError"><i class="fa-regular fa-eye"></i></button>
                  <button class="ico-btn" type="button" title="Device Details" aria-label="Device Details" data-modal="modalDevice"><i class="fa-solid fa-desktop"></i></button>
                  <button class="ico-btn" type="button" title="Mark Resolved" aria-label="Mark Resolved"><i class="fa-solid fa-circle-check"></i></button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="tablefoot">
      <p class="tablefoot__count">Showing all <?= count($errors) ?> entries recorded today</p>
      <nav class="pagination" aria-label="Pagination">
        <a class="pagination__btn is-disabled" href="#">Previous</a>
        <a class="pagination__btn is-on" href="#">1</a>
        <a class="pagination__btn is-disabled" href="#">Next</a>
      </nav>
    </div>
  </div>
  <footer class="foot">
    <p class="foot__brand">Mutendi CMS</p>
    <p class="foot__copy">&copy; <?= date('Y') ?> Mutendi. All rights reserved.</p>
    <p class="foot__meta">Super Admin &middot; v1.0</p>
  </footer>

</main>

<div class="modal" id="modalError" hidden>
  <div class="modal__box modal__box--lg">
    <div class="modal__head">
      <h2><i class="fa-solid fa-circle-exclamation note--berry"></i> Error Details</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <p class="modal__warn"><i class="fa-solid fa-triangle-exclamation"></i>
        SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded; try restarting transaction</p>

      <section class="msec">
        <h3 class="msec__title">Context</h3>
        <dl class="summary">
          <div><dt>Severity</dt><dd><span class="pill pill--critical">Critical</span></dd></div>
          <div><dt>Type</dt><dd><span class="role">Database</span></dd></div>
          <div><dt>File</dt><dd><code class="keytext">/app/Models/Church.php</code></dd></div>
          <div><dt>Line</dt><dd>214</dd></div>
          <div><dt>Church</dt><dd>ZCC Mbungo (ZCC-001) <span class="pill pill--paying pill--xs">Paying</span></dd></div>
          <div><dt>Triggered by</dt><dd>Super Admin <span class="role">System Owner</span></dd></div>
          <div><dt>First seen</dt><dd>25 Aug 2026, 09:12:44</dd></div>
          <div><dt>Occurrences</dt><dd><span class="countdown countdown--berry">&times;7</span></dd></div>
        </dl>
      </section>

      <section class="msec">
        <h3 class="msec__title">Stack Trace</h3>
        <div class="monobox monobox--tall">
          <pre id="stackTrace">#0 /app/Models/Church.php(214): PDOStatement->execute()
#1 /app/Services/Subscription.php(88): App\Models\Church->extend(12)
#2 /app/Controllers/ChurchController.php(146): App\Services\Subscription->apply(12, '12 months')
#3 /app/Http/Router.php(72): App\Controllers\ChurchController->extend(Object(Request))
#4 /app/Http/Kernel.php(41): App\Http\Router->dispatch(Object(Request))
#5 /public/index.php(18): App\Http\Kernel->handle(Object(Request))
#6 {main}</pre>
          <button class="btn btn--sm monobox__copy" type="button" data-copy="stackTrace"><i class="fa-regular fa-copy"></i> Copy</button>
        </div>
      </section>

      <section class="msec">
        <h3 class="msec__title">Request</h3>
        <dl class="summary">
          <div><dt>Method</dt><dd><code class="keytext">POST</code></dd></div>
          <div><dt>URL</dt><dd><code class="keytext">/mus/churches/extend</code></dd></div>
          <div><dt>IP</dt><dd><code class="keytext">197.221.44.18</code></dd></div>
          <div><dt>Execution time</dt><dd>30,142 ms</dd></div>
        </dl>
        <div class="monobox">
          <pre id="reqParams">{
  "church_id": 12,
  "duration": "12 months",
  "amount": "50.00",
  "method": "ecocash",
  "reference": "ECO-88412"
}</pre>
          <button class="btn btn--sm monobox__copy" type="button" data-copy="reqParams"><i class="fa-regular fa-copy"></i> Copy</button>
        </div>
      </section>

      <section class="msec">
        <h3 class="msec__title">Occurrence History</h3>
        <ul class="feed feed--flat">
          <?php foreach ([['25 Aug 2026, 14:41','ZCC Mbungo'],['25 Aug 2026, 13:08','ZCC Mbungo'],
                          ['25 Aug 2026, 11:52','Grace Ministries'],['25 Aug 2026, 10:19','ZCC Mbungo'],
                          ['25 Aug 2026, 09:12','ZCC Mbungo']] as [$when,$where]): ?>
            <li class="feed__row"><span class="dot dot--red"></span>
              <span class="feed__text"><?= $where ?><small><?= $when ?></small></span></li>
          <?php endforeach; ?>
        </ul>
      </section>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Close</button>
      <button class="btn" type="button" data-modal="modalDevice"><i class="fa-solid fa-desktop"></i> Device Details</button>
      <button class="btn" type="button"><i class="fa-solid fa-eye-slash"></i> Ignore</button>
      <button class="btn btn--go" type="button"><i class="fa-solid fa-circle-check"></i> Mark Resolved</button>
    </div>
  </div>
</div>

<?php device_modal(); ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
/* Shared chrome: custom range, dropdowns, tabs, modals, copy buttons,
   per-tab bulk bars and the diff modal's toggles. */
(function () {
  'use strict';
  var custom = document.getElementById('rangeCustom');
  if (custom) {
    custom.addEventListener('click', function () {
      var d = document.getElementById('rangeDates');
      d.hidden = !d.hidden; custom.classList.toggle('is-on', !d.hidden);
    });
  }
  document.addEventListener('click', function (e) {
    var trigger = e.target.closest('.dropdown__trigger');
    document.querySelectorAll('.dropdown.is-open').forEach(function (d) {
      if (!trigger || d !== trigger.parentNode) { d.classList.remove('is-open'); }
    });
    if (trigger) { e.preventDefault(); trigger.parentNode.classList.toggle('is-open'); }
  });
  var tabs = [].slice.call(document.querySelectorAll('.tab[data-tab]'));
  tabs.forEach(function (t) {
    t.addEventListener('click', function () {
      tabs.forEach(function (x) { x.classList.toggle('is-on', x === t); });
      document.querySelectorAll('.tabpanel').forEach(function (p) {
        p.hidden = p.dataset.panel !== t.dataset.tab;
      });
    });
  });
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

  /* Copy buttons on any monospace box. */
  document.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var src = document.getElementById(btn.dataset.copy);
      if (!src) { return; }
      try { navigator.clipboard.writeText(src.textContent.trim()); } catch (e) {}
      var was = btn.innerHTML;
      btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied';
      setTimeout(function () { btn.innerHTML = was; }, 1400);
    });
  });

  /* Diff modal: unchanged rows and raw JSON are both opt-in. */
  var showUnchanged = document.getElementById('showUnchanged');
  if (showUnchanged) {
    var apply = function () {
      document.querySelectorAll('.diff__row--same').forEach(function (r) { r.hidden = !showUnchanged.checked; });
    };
    showUnchanged.addEventListener('change', apply); apply();
  }
  var showJson = document.getElementById('showJson');
  if (showJson) {
    var applyJson = function () {
      document.getElementById('diffTable').hidden = showJson.checked;
      document.getElementById('diffJson').hidden = !showJson.checked;
    };
    showJson.addEventListener('change', applyJson); applyJson();
  }

  /* Bulk bars keyed by name. */
  document.querySelectorAll('[data-checkall]').forEach(function (all) {
    var key = all.dataset.checkall,
        rows = [].slice.call(document.querySelectorAll('[data-rowcheck="' + key + '"]')),
        bar = document.querySelector('[data-bulkbar="' + key + '"]'),
        count = document.querySelector('[data-bulkcount="' + key + '"]'),
        clear = document.querySelector('[data-bulkclear="' + key + '"]');
    function refresh() {
      var n = rows.filter(function (c) { return c.checked; }).length;
      count.textContent = n; bar.hidden = n === 0;
      all.checked = n === rows.length && n > 0;
      all.indeterminate = n > 0 && n < rows.length;
    }
    all.addEventListener('change', function () {
      rows.forEach(function (c) { c.checked = all.checked; }); refresh();
    });
    rows.forEach(function (c) { c.addEventListener('change', refresh); });
    if (clear) { clear.addEventListener('click', function () {
      rows.forEach(function (c) { c.checked = false; }); all.checked = false; refresh();
    }); }
  });
})();
</script>
<script>
(function () {
  var grid = 'rgba(102,47,151,.10)', tick = '#9A93A6';
  new Chart(document.getElementById('errChart'), {
    type: 'bar',
    data: { labels: <?= json_encode($errDays) ?>,
      datasets: [
        { label: 'Critical', data: <?= json_encode($sevCrit) ?>, backgroundColor: '#A93254', borderRadius: 5, maxBarThickness: 26, stack: 'e' },
        { label: 'Error',    data: <?= json_encode($sevError) ?>, backgroundColor: '#C4633F', borderRadius: 5, maxBarThickness: 26, stack: 'e' },
        { label: 'Warning',  data: <?= json_encode($sevWarn) ?>, backgroundColor: '#96701F', borderRadius: 5, maxBarThickness: 26, stack: 'e' },
        { label: 'Notice',   data: <?= json_encode($sevNotice) ?>, backgroundColor: '#8C8398', borderRadius: 5, maxBarThickness: 26, stack: 'e' }
      ] },
    options: { responsive: true, maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true, color: tick } } },
      scales: { x: { stacked: true, grid: { display: false }, ticks: { color: tick } },
                y: { stacked: true, beginAtZero: true, grid: { color: grid }, ticks: { color: tick } } } }
  });
  new Chart(document.getElementById('typeChart'), {
    type: 'doughnut',
    data: { labels: <?= json_encode(array_column($errTypes, 0)) ?>,
      datasets: [{ data: <?= json_encode(array_column($errTypes, 1)) ?>,
        backgroundColor: <?= json_encode(array_column($errTypes, 2)) ?>, borderWidth: 0, hoverOffset: 6 }] },
    options: { responsive: true, maintainAspectRatio: false, cutout: '64%', plugins: { legend: { display: false } } }
  });
})();
</script>
</body>
</html>

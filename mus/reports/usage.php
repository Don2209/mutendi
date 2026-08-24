<?php
/**
 * Mutendi CMS — Usage Report (static UI mockup).
 *
 * Which churches actually use the system and which have gone quiet. Low usage
 * predicts non-renewal, so this is the early-warning screen. Trial and Paying
 * churches sit in the same list. Every dataset is hardcoded; each block
 * carries the query that replaces it.
 */
$musPathsOnly = true;
require __DIR__ . '/../components/sidebar.php';
$musPathsOnly = false;

/* ── Headline usage ────────────────────────────────────────────────────────
   LATER: COUNT(DISTINCT admin_id) and COUNT(*) over `logins` for the window. */
$statTiles = [
    ['Active Users This Period', '88',    'green',  'fa-user-check',     true],
    ['Total Logins',             '1,240', 'indigo', 'fa-right-to-bracket', false],
    ['Highly Engaged Churches',  '22',    'brand',  'fa-fire-flame-curved', false],
    ['Dormant Churches',         '6',     'berry',  'fa-moon',           false],
];

/* ── Platform activity, last 30 days ───────────────────────────────────────
   LATER: daily COUNT(*) from `logins` and from every record-creating table. */
$activityDays = [];
for ($d = 30; $d >= 1; $d--) { $activityDays[] = $d . 'd'; }
$logins  = [38,42,35,50,47,52,61,44,39,48,55,58,43,41,50,62,57,46,44,53,60,49,45,51,64,58,47,43,56,66];
$records = [120,145,110,180,165,190,220,150,132,170,195,205,148,140,175,225,200,160,152,185,215,172,158,178,230,205,166,150,196,240];

/* ── Engagement distribution ───────────────────────────────────────────────
   LATER: bucket churches by a score over logins and records captured. */
$engagement = [['High', 22, '#1E8265'], ['Medium', 13, '#96701F'], ['Low', 6, '#A93254'], ['Dormant', 6, '#8C8398']];

/* ── Module usage across churches ──────────────────────────────────────────
   LATER: SELECT m.name, COUNT(DISTINCT cm.church_id) FROM church_modules cm
          JOIN modules m ON m.id = cm.module_id WHERE cm.enabled = 1 GROUP BY m.id; */
$moduleUse = ['Members'=>47,'Attendance'=>44,'Communication'=>39,'Finance'=>38,'Events'=>34,
              'Reports'=>32,'Visitors'=>31,'Cell Groups'=>29,'Assets'=>15];

/* ── Data captured, last 6 months ──────────────────────────────────────────
   LATER: monthly COUNT(*) from members, attendance and contributions. */
$capMonths   = ['Mar','Apr','May','Jun','Jul','Aug'];
$capMembers  = [420, 510, 480, 605, 560, 690];
$capAttend   = [1850, 2100, 1980, 2450, 2260, 2780];
$capContrib  = [640, 720, 690, 880, 810, 960];

/* ── Church usage detail ───────────────────────────────────────────────────
   LATER:
     SELECT c.*, COUNT(DISTINCT l.id) AS logins, COUNT(DISTINCT m.id) AS members,
            MAX(l.created_at) AS last_login
       FROM churches c
       LEFT JOIN logins l ON l.church_id = c.id AND l.created_at >= :from
       LEFT JOIN members m ON m.church_id = c.id
      GROUP BY c.id ORDER BY logins DESC; */
$rows = [
 ['JM','Johane Masowe eChishanu','JME-004','Paying',3420,4000,412,88,'4 of 4',9,9,'520 MB','5 hours ago',false,'High'],
 ['AD','Anglican Diocese Masvingo','ADM-009','Paying',2480,3000,368,74,'3 of 3',8,9,'410 MB','4 hours ago',false,'High'],
 ['AW','AFM Waterfalls','AFM-002','Paying',2105,2500,340,66,'4 of 5',7,9,'365 MB','1 hour ago',false,'High'],
 ['CC','Celebration Church Harare','CCH-005','Paying',1780,2000,296,61,'3 of 4',8,9,'310 MB','Yesterday',false,'High'],
 ['UF','UFIC Chinhoyi','UFI-013','Paying',1640,2000,254,52,'2 of 3',6,9,'275 MB','12 hours ago',false,'High'],
 ['MM','Methodist Mutare Circuit','MMC-007','Paying',1315,1500,208,44,'2 of 2',6,9,'230 MB','6 hours ago',false,'Medium'],
 ['ZM','ZCC Mbungo','ZCC-001','Paying',1240,1500,186,39,'2 of 3',6,9,'215 MB','2 hours ago',false,'Medium'],
 ['ZA','Zion Apostolic Marondera','ZAM-012','Paying',1120,1500,164,33,'2 of 2',5,9,'190 MB','Yesterday',false,'Medium'],
 ['FW','Faith World Ministries','FWM-010','Paying',1050,1500,132,27,'1 of 2',5,9,'175 MB','8 days ago',false,'Medium'],
 ['NL','New Life Chitungwiza','NLC-061','Trial',88,100,96,24,'1 of 1',4,9,'42 MB','2 hours ago',false,'High'],
 ['FG','Family of God Bulawayo','FOG-006','Trial',940,1200,84,21,'1 of 2',3,9,'145 MB','3 days ago',false,'Medium'],
 ['RH','Rhema Bulawayo','RHB-063','Trial',61,100,48,18,'1 of 1',3,9,'28 MB','Yesterday',false,'Low'],
 ['GM','Grace Ministries','GRM-003','Paying',860,1000,36,9,'1 of 2',3,9,'130 MB','34 days ago',true,'Low'],
 ['GG','Glory Ministries Gweru','GMG-008','Paying',620,800,4,2,'0 of 2',2,9,'96 MB','62 days ago',true,'Dormant'],
 ['SH','SDA Hwange','SDA-014','Paying',705,900,2,1,'0 of 1',2,9,'104 MB','71 days ago',true,'Dormant'],
];

/* ── Most active and at-risk lists ─────────────────────────────────────────
   LATER: ORDER BY activity DESC LIMIT 5, and last_login < NOW() - 30 DAY. */
$mostActive = [['Johane Masowe eChishanu',412],['Anglican Diocese Masvingo',368],
               ['AFM Waterfalls',340],['Celebration Church Harare',296],['UFIC Chinhoyi',254]];
$atRisk = [['SDA Hwange','SDA-014',71,'05 Aug 2026'],['Glory Ministries Gweru','GMG-008',62,'30 Jul 2026'],
           ['Christ Embassy Chitungwiza','CEC-011',41,'03 Oct 2026'],['AFM Kwekwe Assembly','AFK-016',37,'21 Nov 2026'],
           ['Grace Ministries','GRM-003',34,'12 Aug 2026']];

$provinceList = ['All Provinces','Harare','Bulawayo','Manicaland','Midlands','Masvingo',
                 'Mashonaland East','Mashonaland West','Mashonaland Central',
                 'Matabeleland North','Matabeleland South'];

$activePage    = 'reports/usage';
$sidebarBadges = ['pending' => 10, 'expiring' => 12];
$sidebarUser   = ['name' => 'Super Admin', 'role' => 'System Owner'];
$pageTitle     = 'Usage Report';
$pageHint      = 'How actively each church is using the system.';
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
        Reports <i class="fa-solid fa-chevron-right"></i> <?= $pageTitle ?>
      </p>
      <p class="page-hint"><?= $pageHint ?></p>
    </div>
    <div class="head-actions">
      <div class="rangebar">
        <div class="range">
          <?php foreach (['Today','7 Days','30 Days','90 Days','This Year'] as $r): ?>
            <a href="#" class="range__btn<?= $r === '30 Days' ? ' is-on' : '' ?>"><?= $r ?></a>
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
    <?php foreach ($statTiles as [$label, $value, $tone, $icon, $on]): ?>
      <a class="stat-tile stat-tile--<?= $tone ?><?= $on ? ' is-on' : '' ?>" href="#">
        <span class="stat-tile__icon"><i class="fa-solid <?= $icon ?>"></i></span>
        <span class="stat-tile__body">
          <span class="stat-tile__value"><?= $value ?></span>
          <span class="stat-tile__label"><?= htmlspecialchars($label) ?></span>
        </span>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Row 1 — activity + engagement -->
  <div class="grid grid--2-1">
    <div class="card">
      <div class="card__head"><h2>Platform Activity Over Time</h2><span class="card__note">Last 30 days</span></div>
      <div class="card__body"><div class="chart-wrap"><canvas id="activityChart"></canvas></div></div>
    </div>
    <div class="card">
      <div class="card__head"><h2>Engagement Distribution</h2></div>
      <div class="card__body">
        <div class="chart-wrap chart-wrap--donut"><canvas id="engageChart"></canvas></div>
        <ul class="legend">
          <?php foreach ($engagement as [$label, $count, $colour]): ?>
            <li><span class="legend__dot" style="background: <?= $colour ?>"></span>
              <span class="legend__label"><?= $label ?></span>
              <span class="legend__count"><?= $count ?></span></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>

  <!-- Row 2 — module usage + data captured -->
  <div class="grid grid--2">
    <div class="card">
      <div class="card__head"><h2>Module Usage Across Churches</h2></div>
      <div class="card__body"><div class="chart-wrap"><canvas id="moduleChart"></canvas></div></div>
    </div>
    <div class="card">
      <div class="card__head"><h2>Data Captured Over Time</h2><span class="card__note">Last 6 months</span></div>
      <div class="card__body"><div class="chart-wrap"><canvas id="capturedChart"></canvas></div></div>
    </div>
  </div>

  <!-- Filter bar -->
  <div class="card filterbar">
    <div class="filterbar__row">
      <label class="field field--search"><span class="field__label">Search</span>
        <span class="field__input"><i class="fa-solid fa-magnifying-glass"></i>
          <input type="search" placeholder="Search by church name or code..."></span></label>
      <label class="field"><span class="field__label">Account Type</span>
        <select><option>All</option><option>Trial</option><option>Paying</option></select></label>
      <label class="field"><span class="field__label">Engagement</span>
        <select><option>All</option><option>High</option><option>Medium</option><option>Low</option><option>Dormant</option></select></label>
      <label class="field"><span class="field__label">Province</span>
        <select><?php foreach ($provinceList as $p): ?><option><?= $p ?></option><?php endforeach; ?></select></label>
      <label class="field"><span class="field__label">Sort by</span>
        <select><option>Most Active</option><option>Least Active</option><option>Most Members</option><option>Longest Dormant</option></select></label>
    </div>
    <div class="filterbar__foot">
      <div class="filterbar__actions">
        <button class="btn btn--primary" type="button"><i class="fa-solid fa-filter"></i> Apply Filters</button>
        <a class="link-reset" href="#">Reset</a>
      </div>
    </div>
  </div>

  <!-- Church usage detail -->
  <div class="card">
    <div class="card__head"><h2>Church Usage Detail</h2></div>
    <div class="table-wrap">
      <table class="table table--churches">
        <thead>
          <tr>
            <th class="col-num">#</th><th>Church</th>
            <th>Members Captured</th><th class="ta-right">Records</th><th class="ta-right">Logins</th>
            <th>Active Admins</th><th>Modules Used</th><th class="ta-right">Storage</th>
            <th>Last Login</th><th>Engagement</th><th class="col-actions">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $i => $r): ?>
            <?php [$in,$name,$code,$acct,$mem,$memMax,$rec,$log,$admins,$mods,$modMax,$storage,$last,$stale,$eng] = $r; ?>
            <tr>
              <td class="col-num muted"><?= $i + 1 ?></td>
              <td>
                <div class="church">
                  <span class="church__avatar"><?= $in ?></span>
                  <span class="church__text">
                    <strong><?= htmlspecialchars($name) ?></strong>
                    <small><?= $code ?> <span class="pill pill--<?= strtolower($acct) ?> pill--xs"><?= $acct ?></span></small>
                  </span>
                </div>
              </td>
              <td>
                <span class="setup"><span class="setup__num"><?= number_format($mem) ?></span>
                  <span class="bar"><i style="width: <?= (int) round($mem / $memMax * 100) ?>%"></i></span></span>
              </td>
              <td class="ta-right"><?= number_format($rec) ?></td>
              <td class="ta-right strong"><?= $log ?></td>
              <td class="nowrap muted"><?= $admins ?></td>
              <td>
                <span class="setup"><span class="setup__num"><?= $mods ?> of <?= $modMax ?></span>
                  <span class="bar"><i style="width: <?= (int) round($mods / $modMax * 100) ?>%"></i></span></span>
              </td>
              <td class="ta-right muted nowrap"><?= $storage ?></td>
              <td class="nowrap <?= $stale ? 'muted' : '' ?>"><?= $last ?></td>
              <td><span class="engage engage--<?= strtolower($eng) ?>"><?= $eng ?></span></td>
              <td class="col-actions">
                <div class="row-actions">
                  <a class="ico-btn" href="#" title="View" aria-label="View"><i class="fa-regular fa-eye"></i></a>
                  <a class="ico-btn" href="#" title="Login As" aria-label="Login As"><i class="fa-solid fa-right-to-bracket"></i></a>
                  <button class="ico-btn" type="button" title="Send Reminder" aria-label="Send Reminder"><i class="fa-solid fa-bell"></i></button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="tablefoot">
      <p class="tablefoot__count">Showing 1 to <?= count($rows) ?> of 47 entries</p>
      <nav class="pagination" aria-label="Pagination">
        <a class="pagination__btn is-disabled" href="#">Previous</a>
        <a class="pagination__btn is-on" href="#">1</a>
        <a class="pagination__btn" href="#">2</a>
        <a class="pagination__btn" href="#">3</a>
        <a class="pagination__btn" href="#">Next</a>
      </nav>
    </div>
  </div>

  <!-- Bottom row -->
  <div class="grid grid--2">
    <div class="card">
      <div class="card__head"><h2>Most Active Churches</h2></div>
      <ul class="ranks">
        <?php $topMax = $mostActive[0][1]; foreach ($mostActive as [$name, $acts]): ?>
          <li>
            <span class="ranks__top">
              <span class="ranks__name"><?= htmlspecialchars($name) ?></span>
              <span class="ranks__num"><?= number_format($acts) ?></span>
            </span>
            <span class="bar"><i style="width: <?= round($acts / $topMax * 100) ?>%"></i></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="card">
      <div class="card__head"><h2>At Risk &mdash; Dormant Churches</h2><span class="card__note">No login in 30+ days</span></div>
      <ul class="attn">
        <?php foreach ($atRisk as [$riskName, $riskCode, $dormantDays, $riskExpiry]): ?>
          <li class="attn__row">
            <span class="dot dot--<?= $dormantDays >= 60 ? 'red' : 'amber' ?>"></span>
            <span class="attn__text">
              <strong><?= htmlspecialchars($riskName) ?></strong>
              <small><?= $riskCode ?> &middot; <?= $dormantDays ?> days dormant &middot; expires <?= $riskExpiry ?></small>
            </span>
            <button class="btn btn--sm" type="button"><i class="fa-solid fa-bell"></i> Remind</button>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
  <footer class="foot">
    <p class="foot__brand">Mutendi CMS</p>
    <p class="foot__copy">&copy; <?= date('Y') ?> Mutendi. All rights reserved.</p>
    <p class="foot__meta">Super Admin &middot; v1.0</p>
  </footer>

</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
/* Shared page chrome: custom date range, dropdowns, tabs and modals. */
(function () {
  'use strict';
  var custom = document.getElementById('rangeCustom');
  if (custom) {
    custom.addEventListener('click', function () {
      var d = document.getElementById('rangeDates');
      d.hidden = !d.hidden;
      custom.classList.toggle('is-on', !d.hidden);
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
})();
</script>
<script>
(function () {
  var grid = 'rgba(102,47,151,.10)', tick = '#9A93A6';
  var base = { responsive: true, maintainAspectRatio: false,
               interaction: { mode: 'index', intersect: false },
               plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true, color: tick } } } };

  new Chart(document.getElementById('activityChart'), {
    type: 'line',
    data: { labels: <?= json_encode($activityDays) ?>,
      datasets: [
        { label: 'Daily logins', data: <?= json_encode($logins) ?>,
          borderColor: '#662F97', backgroundColor: '#662F97', borderWidth: 2, tension: .35, pointRadius: 0, yAxisID: 'y' },
        { label: 'Records created', data: <?= json_encode($records) ?>,
          borderColor: '#1E8265', backgroundColor: '#1E8265', borderWidth: 2, tension: .35, pointRadius: 0, yAxisID: 'y1' }
      ] },
    options: Object.assign({}, base, { scales: {
      x:  { grid: { display: false }, ticks: { color: tick, maxTicksLimit: 10 } },
      y:  { position: 'left', beginAtZero: true, grid: { color: grid }, ticks: { color: tick } },
      y1: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, ticks: { color: tick } }
    }})
  });

  new Chart(document.getElementById('engageChart'), {
    type: 'doughnut',
    data: { labels: <?= json_encode(array_column($engagement, 0)) ?>,
      datasets: [{ data: <?= json_encode(array_column($engagement, 1)) ?>,
        backgroundColor: <?= json_encode(array_column($engagement, 2)) ?>, borderWidth: 0, hoverOffset: 6 }] },
    options: { responsive: true, maintainAspectRatio: false, cutout: '64%', plugins: { legend: { display: false } } }
  });

  new Chart(document.getElementById('moduleChart'), {
    type: 'bar',
    data: { labels: <?= json_encode(array_keys($moduleUse)) ?>,
      datasets: [{ label: 'Churches using', data: <?= json_encode(array_values($moduleUse)) ?>,
        backgroundColor: '#662F97', borderRadius: 5, maxBarThickness: 18 }] },
    options: Object.assign({}, base, { indexAxis: 'y', plugins: { legend: { display: false } },
      scales: { x: { beginAtZero: true, grid: { color: grid }, ticks: { color: tick } },
                y: { grid: { display: false }, ticks: { color: tick } } } })
  });

  new Chart(document.getElementById('capturedChart'), {
    type: 'bar',
    data: { labels: <?= json_encode($capMonths) ?>,
      datasets: [
        { label: 'Members added', data: <?= json_encode($capMembers) ?>, backgroundColor: '#662F97', borderRadius: 5, maxBarThickness: 30, stack: 'c' },
        { label: 'Attendance records', data: <?= json_encode($capAttend) ?>, backgroundColor: '#B48FDA', borderRadius: 5, maxBarThickness: 30, stack: 'c' },
        { label: 'Contributions', data: <?= json_encode($capContrib) ?>, backgroundColor: '#1E8265', borderRadius: 5, maxBarThickness: 30, stack: 'c' }
      ] },
    options: Object.assign({}, base, { scales: {
      x: { stacked: true, grid: { display: false }, ticks: { color: tick } },
      y: { stacked: true, beginAtZero: true, grid: { color: grid }, ticks: { color: tick } } } })
  });
})();
</script>
</body>
</html>

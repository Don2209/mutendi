<?php
/**
 * Mutendi CMS — Growth Report (static UI mockup).
 *
 * Acquisition, conversion and churn over time. Trial and Paying churches are
 * the same kind of tenant — the split below is descriptive, not a plan.
 * Every dataset is hardcoded; each block carries the query that replaces it.
 */
$musPathsOnly = true;
require __DIR__ . '/../components/sidebar.php';
$musPathsOnly = false;

/* ── Headline movement vs the previous period ──────────────────────────────
   LATER: COUNT(*) over `churches` for the selected window, compared with the
   same window immediately before it. */
$statTiles = [
    ['New Churches This Period', 6, 'indigo', 'fa-church',        '+2',  'up',   true],
    ['Trials Started',           4, 'brand',  'fa-star',          '+1',  'up',   false],
    ['Trials Converted',         3, 'green',  'fa-circle-check',  '0',   'flat', false],
    ['Churned',                  2, 'berry',  'fa-user-slash',    '-1',  'down', false],
];

/* ── Growth over time ──────────────────────────────────────────────────────
   LATER:
     SELECT DATE_FORMAT(created_at,'%b') m,
            SUM(account_type='trial')  AS new_trial,
            SUM(account_type='paying') AS new_paying
       FROM churches GROUP BY m ORDER BY created_at LIMIT 12; */
$months     = ['Sep','Oct','Nov','Dec','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug'];
$newTrial   = [2, 3, 2, 4, 2, 3, 2, 4, 3, 5, 3, 4];
$newPaying  = [1, 1, 2, 2, 1, 3, 2, 2, 3, 2, 2, 2];
$cumulative = [18, 21, 23, 26, 27, 30, 31, 33, 34, 36, 37, 38];

/* ── Trial conversion funnel ───────────────────────────────────────────────
   LATER: COUNT(*) at each stage of the trial lifecycle. */
$funnel = [
    ['Trials Started',       24, 'brand'],
    ['Actively Used',        18, 'indigo'],
    ['Converted to Paying',  14, 'green'],
];

/* ── Churn and retention ───────────────────────────────────────────────────
   LATER: churn = COUNT(*) of churches lapsed per month; retention derived. */
$churn     = [1, 0, 2, 1, 1, 2, 1, 0, 2, 1, 1, 2];
$retention = [94, 100, 91, 95, 96, 92, 95, 100, 91, 96, 95, 91];

/* ── Growth by province ────────────────────────────────────────────────────
   LATER: SELECT province, COUNT(*) FROM churches GROUP BY province ORDER BY 2 DESC; */
$provinces = [
    'Harare' => 12, 'Midlands' => 7, 'Masvingo' => 6, 'Manicaland' => 5,
    'Bulawayo' => 4, 'Mash. West' => 3, 'Mash. Central' => 2,
    'Mash. East' => 2, 'Mat. North' => 1, 'Mat. South' => 1,
];

/* ── Account type split ────────────────────────────────────────────────────
   LATER: SELECT account_type, COUNT(*) FROM churches GROUP BY account_type; */
$split = [['Paying', 38, '#1E8265'], ['Trial', 5, '#5A57B5']];
$splitTotal = array_sum(array_column($split, 1));

/* ── Monthly breakdown table ───────────────────────────────────────────────
   LATER: the same aggregate as the chart, listed newest first. */
$breakdown = [
    ['Aug 2026', 4, 2, 3, 2, 38], ['Jul 2026', 3, 2, 2, 1, 37],
    ['Jun 2026', 5, 2, 3, 1, 36], ['May 2026', 3, 3, 2, 2, 34],
    ['Apr 2026', 4, 2, 2, 0, 33], ['Mar 2026', 2, 2, 1, 1, 31],
    ['Feb 2026', 3, 3, 3, 2, 30], ['Jan 2026', 2, 1, 1, 1, 27],
    ['Dec 2025', 4, 2, 2, 1, 26], ['Nov 2025', 2, 2, 2, 2, 23],
    ['Oct 2025', 3, 1, 1, 0, 21], ['Sep 2025', 2, 1, 1, 1, 18],
];

$activePage    = 'reports/growth';
$sidebarBadges = ['pending' => 10, 'expiring' => 12];
$sidebarUser   = ['name' => 'Super Admin', 'role' => 'System Owner'];
$pageTitle     = 'Growth Report';
$pageHint      = 'Church acquisition, conversion and churn over time.';
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

  <!-- Stat strip with period-on-period movement -->
  <div class="statstrip">
    <?php foreach ($statTiles as [$label, $value, $tone, $icon, $delta, $dir, $on]): ?>
      <a class="stat-tile stat-tile--<?= $tone ?><?= $on ? ' is-on' : '' ?>" href="#">
        <span class="stat-tile__icon"><i class="fa-solid <?= $icon ?>"></i></span>
        <span class="stat-tile__body">
          <span class="stat-tile__value"><?= $value ?></span>
          <span class="stat-tile__label"><?= htmlspecialchars($label) ?></span>
        </span>
        <span class="delta delta--<?= $dir ?>">
          <i class="fa-solid fa-<?= $dir === 'up' ? 'arrow-up' : ($dir === 'down' ? 'arrow-down' : 'minus') ?>"></i><?= $delta ?>
        </span>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Row 1 — growth over time -->
  <div class="card">
    <div class="card__head"><h2>Church Growth Over Time</h2><span class="card__note">Last 12 months</span></div>
    <div class="card__body"><div class="chart-wrap"><canvas id="growthChart"></canvas></div></div>
  </div>

  <!-- Row 2 — funnel + churn -->
  <div class="grid grid--2">
    <div class="card">
      <div class="card__head"><h2>Trial Conversion Funnel</h2></div>
      <div class="card__body">
        <div class="funnel">
          <?php foreach ($funnel as $i => [$stage, $count, $tone]): ?>
            <div class="funnel__stage">
              <div class="funnel__row">
                <span class="funnel__label"><?= $stage ?></span>
                <span class="funnel__count"><?= $count ?></span>
              </div>
              <span class="funnel__bar funnel__bar--<?= $tone ?>" style="width: <?= round($count / $funnel[0][1] * 100) ?>%"></span>
            </div>
            <?php if (isset($funnel[$i + 1])): ?>
              <p class="funnel__conv">
                <i class="fa-solid fa-arrow-down"></i>
                <?= round($funnel[$i + 1][1] / $count * 100) ?>% continue
              </p>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
        <p class="preview"><span>Overall conversion rate</span>
          <strong><?= round($funnel[2][1] / $funnel[0][1] * 100) ?>%</strong></p>
      </div>
    </div>

    <div class="card">
      <div class="card__head"><h2>Churn &amp; Retention</h2></div>
      <div class="card__body">
        <div class="chart-wrap chart-wrap--sm"><canvas id="churnChart"></canvas></div>
        <div class="inline-stats">
          <div><span>Retention Rate</span><strong class="pos">91%</strong></div>
          <div><span>Average Lifespan</span><strong>14 months</strong></div>
          <div><span>Churn Rate</span><strong>9%</strong></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Row 3 — province + account type -->
  <div class="grid grid--2">
    <div class="card">
      <div class="card__head"><h2>Growth by Province</h2></div>
      <div class="card__body"><div class="chart-wrap"><canvas id="provinceChart"></canvas></div></div>
    </div>

    <div class="card">
      <div class="card__head"><h2>Account Type Split</h2></div>
      <div class="card__body">
        <div class="chart-wrap chart-wrap--donut"><canvas id="splitChart"></canvas></div>
        <ul class="legend">
          <?php foreach ($split as [$label, $count, $colour]): ?>
            <li>
              <span class="legend__dot" style="background: <?= $colour ?>"></span>
              <span class="legend__label"><?= $label ?></span>
              <span class="legend__count"><?= $count ?> &middot; <?= round($count / $splitTotal * 100) ?>%</span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>

  <!-- Monthly breakdown -->
  <div class="card">
    <div class="card__head"><h2>Monthly Breakdown</h2></div>
    <div class="table-wrap">
      <table class="table table--churches">
        <thead>
          <tr>
            <th>Month</th><th class="ta-right">New Trials</th><th class="ta-right">New Paying</th>
            <th class="ta-right">Converted</th><th class="ta-right">Churned</th>
            <th class="ta-right">Net Growth</th><th class="ta-right">Total Active</th>
          </tr>
        </thead>
        <tbody>
          <?php
            $tT = $tP = $tC = $tCh = 0;
            foreach ($breakdown as [$m, $nt, $np, $conv, $ch, $total]):
              $tT += $nt; $tP += $np; $tC += $conv; $tCh += $ch;
              $net = $nt + $np - $ch;
          ?>
            <tr>
              <td class="strong nowrap"><?= $m ?></td>
              <td class="ta-right"><?= $nt ?></td>
              <td class="ta-right"><?= $np ?></td>
              <td class="ta-right"><?= $conv ?></td>
              <td class="ta-right"><?= $ch ?></td>
              <td class="ta-right">
                <span class="netgrowth netgrowth--<?= $net >= 0 ? 'up' : 'down' ?>">
                  <i class="fa-solid fa-<?= $net >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i><?= abs($net) ?>
                </span>
              </td>
              <td class="ta-right strong"><?= $total ?></td>
            </tr>
          <?php endforeach; ?>
          <tr class="table__total">
            <td>Totals</td>
            <td class="ta-right"><?= $tT ?></td>
            <td class="ta-right"><?= $tP ?></td>
            <td class="ta-right"><?= $tC ?></td>
            <td class="ta-right"><?= $tCh ?></td>
            <td class="ta-right">
              <span class="netgrowth netgrowth--up"><i class="fa-solid fa-arrow-up"></i><?= $tT + $tP - $tCh ?></span>
            </td>
            <td class="ta-right">38</td>
          </tr>
        </tbody>
      </table>
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

  new Chart(document.getElementById('growthChart'), {
    data: {
      labels: <?= json_encode($months) ?>,
      datasets: [
        { type: 'bar', label: 'New Trial', data: <?= json_encode($newTrial) ?>,
          backgroundColor: '#B48FDA', borderRadius: 5, maxBarThickness: 28, stack: 'new', yAxisID: 'y' },
        { type: 'bar', label: 'New Paying', data: <?= json_encode($newPaying) ?>,
          backgroundColor: '#662F97', borderRadius: 5, maxBarThickness: 28, stack: 'new', yAxisID: 'y' },
        { type: 'line', label: 'Total active churches', data: <?= json_encode($cumulative) ?>,
          borderColor: '#1E8265', backgroundColor: '#1E8265', borderWidth: 2, tension: .35, pointRadius: 3, yAxisID: 'y1' }
      ]
    },
    options: Object.assign({}, base, { scales: {
      x:  { stacked: true, grid: { display: false }, ticks: { color: tick } },
      y:  { stacked: true, position: 'left', beginAtZero: true, grid: { color: grid }, ticks: { color: tick } },
      y1: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, ticks: { color: tick } }
    }})
  });

  new Chart(document.getElementById('churnChart'), {
    type: 'line',
    data: {
      labels: <?= json_encode($months) ?>,
      datasets: [
        { label: 'Churned churches', data: <?= json_encode($churn) ?>,
          borderColor: '#A93254', backgroundColor: '#A93254', borderWidth: 2, tension: .35, pointRadius: 3, yAxisID: 'y' },
        { label: 'Retention rate (%)', data: <?= json_encode($retention) ?>,
          borderColor: '#1E8265', backgroundColor: '#1E8265', borderWidth: 2, tension: .35, pointRadius: 3, yAxisID: 'y1' }
      ]
    },
    options: Object.assign({}, base, { scales: {
      x:  { grid: { display: false }, ticks: { color: tick } },
      y:  { position: 'left', beginAtZero: true, grid: { color: grid }, ticks: { color: tick } },
      y1: { position: 'right', min: 80, max: 100, grid: { drawOnChartArea: false },
            ticks: { color: tick, callback: function (v) { return v + '%'; } } }
    }})
  });

  new Chart(document.getElementById('provinceChart'), {
    type: 'bar',
    data: {
      labels: <?= json_encode(array_keys($provinces)) ?>,
      datasets: [{ label: 'Churches', data: <?= json_encode(array_values($provinces)) ?>,
                   backgroundColor: '#662F97', borderRadius: 5, maxBarThickness: 18 }]
    },
    options: Object.assign({}, base, { indexAxis: 'y',
      plugins: { legend: { display: false } },
      scales: { x: { beginAtZero: true, grid: { color: grid }, ticks: { color: tick } },
                y: { grid: { display: false }, ticks: { color: tick } } } })
  });

  new Chart(document.getElementById('splitChart'), {
    type: 'doughnut',
    data: { labels: <?= json_encode(array_column($split, 0)) ?>,
            datasets: [{ data: <?= json_encode(array_column($split, 1)) ?>,
                         backgroundColor: <?= json_encode(array_column($split, 2)) ?>,
                         borderWidth: 0, hoverOffset: 6 }] },
    options: { responsive: true, maintainAspectRatio: false, cutout: '64%',
               plugins: { legend: { display: false } } }
  });
})();
</script>
</body>
</html>

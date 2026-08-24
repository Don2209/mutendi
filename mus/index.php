<?php
/**
 * Mutendi CMS — Super Admin Dashboard (static UI mockup).
 *
 * Every dataset below is hardcoded. Each block is marked with the database
 * query that will replace it once the schema exists.
 */

/* ── Sidebar ───────────────────────────────────────────────────────────────
   Navigation lives in mus/components/sidebar.php (the reusable component).
   These two variables are read by it. */
$activePage    = 'index';
$sidebarBadges = ['pending' => 3, 'expiring' => 5];
$sidebarUser   = ['name' => 'Super Admin', 'role' => 'System Owner'];

/* ── Provinces for the Add New Church form ─────────────────────────────────
   LATER: SELECT name FROM provinces ORDER BY name; */
$provinces = ['Harare', 'Bulawayo', 'Manicaland', 'Midlands', 'Masvingo',
              'Mashonaland East', 'Mashonaland West', 'Mashonaland Central',
              'Matabeleland North', 'Matabeleland South'];

/* ── Date range selector ─────────────────────────────────────────────────── */
$dateRanges  = ['Today', '7 Days', '30 Days', 'This Year', 'Custom'];
$activeRange = '30 Days';

/* ── 1. Primary KPI cards ──────────────────────────────────────────────────
   LATER: SELECT COUNT(*) FROM churches;
          SELECT COUNT(*) FROM churches WHERE status = 'active';
          SELECT COUNT(*) FROM churches WHERE expiry_date BETWEEN NOW() AND NOW() + INTERVAL 30 DAY;
          SELECT COUNT(*), SUM(amount_due) FROM churches WHERE status IN ('expired','suspended'); */
$primaryKpis = [
    ['label' => 'Total Churches',      'value' => '47', 'sub' => '&#9650; 4 this month',  'icon' => 'fa-church',              'tone' => 'blue'],
    ['label' => 'Active Subscriptions','value' => '38', 'sub' => '81% of total',          'icon' => 'fa-circle-check',        'tone' => 'green'],
    ['label' => 'Expiring in 30 Days', 'value' => '5',  'sub' => '~$250 at risk',         'icon' => 'fa-triangle-exclamation','tone' => 'amber'],
    ['label' => 'Expired / Suspended', 'value' => '4',  'sub' => '$200 outstanding',      'icon' => 'fa-ban',                 'tone' => 'red'],
];

/* ── 2. Secondary metrics ──────────────────────────────────────────────────
   LATER: SELECT SUM(member_count) FROM churches;
          SELECT SUM(sms_credits) FROM churches;
          SELECT COUNT(*) FROM church_admins WHERE status = 'active';
          SELECT SUM(storage_bytes) FROM churches; */
$secondaryKpis = [
    ['label' => 'Total Members Managed', 'value' => '12,480',          'icon' => 'fa-users',        'bar' => null],
    ['label' => 'SMS Credits Remaining', 'value' => '8,450',           'icon' => 'fa-comment-sms',  'bar' => null],
    ['label' => 'Active Church Admins',  'value' => '96',              'icon' => 'fa-user-shield',  'bar' => null],
    ['label' => 'Storage Used',          'value' => '4.2 GB / 20 GB',  'icon' => 'fa-hard-drive',   'bar' => 21],
];

/* ── 3a. Revenue & growth chart ────────────────────────────────────────────
   LATER: SELECT DATE_FORMAT(paid_at,'%b') m, SUM(amount) FROM payments
          GROUP BY m ORDER BY paid_at LIMIT 12; */
$chartMonths      = ['Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug'];
$chartRevenue     = [320, 410, 380, 520, 450, 610, 540, 700, 660, 780, 620, 850];
$chartCumulative  = [18, 21, 23, 26, 27, 30, 31, 33, 34, 36, 37, 38];
$revenueStats     = ['collected' => '$850', 'last' => '$620', 'change' => '+37%'];

/* ── 3b. Subscription status doughnut ──────────────────────────────────────
   LATER: SELECT status, COUNT(*) FROM churches GROUP BY status; */
$statusBreakdown = [
    ['label' => 'Active',    'count' => 38, 'colour' => '#1E8265'],
    ['label' => 'Trial',     'count' => 3,  'colour' => '#B48FDA'],
    ['label' => 'Expiring',  'count' => 5,  'colour' => '#96701F'],
    ['label' => 'Expired',   'count' => 2,  'colour' => '#A93254'],
    ['label' => 'Suspended', 'count' => 2,  'colour' => '#8C8398'],
];

/* ── 4a. Needs your attention ──────────────────────────────────────────────
   LATER: union of expiry, activation, sms-balance and backup-failure queries. */
$attention = [
    ['tone' => 'red',   'text' => '3 churches expired',            'sub' => 'Grace Ministries – 12 days ago', 'action' => 'Extend'],
    ['tone' => 'amber', 'text' => '5 expiring within 30 days',     'sub' => 'ZCC Mbungo – in 4 days',         'action' => 'Extend'],
    ['tone' => 'blue',  'text' => '3 pending activation',          'sub' => 'Awaiting payment confirmation',  'action' => 'Activate'],
    ['tone' => 'amber', 'text' => '2 SMS balances below 50 credits','sub' => 'Glory Ministries, AFM Kwekwe',  'action' => 'Top Up'],
    ['tone' => 'red',   'text' => '1 backup failed last night',    'sub' => 'Storage timeout at 02:15',       'action' => 'Retry'],
];

/* ── 4b. Dormant churches ──────────────────────────────────────────────────
   LATER: SELECT * FROM churches WHERE last_login_at < NOW() - INTERVAL 30 DAY; */
$dormant = [
    ['church' => 'Glory Ministries Gweru',     'last' => '62 days ago', 'members' => '620',   'expiry' => '30 Jul 2026'],
    ['church' => 'Faith World Ministries',     'last' => '48 days ago', 'members' => '1,050', 'expiry' => '15 Sep 2026'],
    ['church' => 'Christ Embassy Chitungwiza', 'last' => '41 days ago', 'members' => '480',   'expiry' => '03 Oct 2026'],
    ['church' => 'AFM Kwekwe Assembly',        'last' => '37 days ago', 'members' => '735',   'expiry' => '21 Nov 2026'],
    ['church' => 'Grace Ministries',           'last' => '34 days ago', 'members' => '860',   'expiry' => '12 Aug 2026'],
];

/* ── 5. Recently registered churches ───────────────────────────────────────
   LATER: SELECT * FROM churches ORDER BY created_at DESC LIMIT 10; */
$recentChurches = [
    ['name' => 'ZCC Mbungo',                'code' => 'MZ-0147', 'contact' => 'Bishop N. Mutendi', 'phone' => '+263 772 145 880', 'members' => '1,240', 'status' => 'Expiring',  'expiry' => '28 Aug 2026', 'registered' => '12 Aug 2026'],
    ['name' => 'Grace Ministries',          'code' => 'MZ-0146', 'contact' => 'Pastor T. Chikore', 'phone' => '+263 771 902 335', 'members' => '860',   'status' => 'Expired',   'expiry' => '12 Aug 2026', 'registered' => '09 Aug 2026'],
    ['name' => 'AFM Waterfalls',            'code' => 'MZ-0145', 'contact' => 'Rev. S. Banda',     'phone' => '+263 778 411 207', 'members' => '2,105', 'status' => 'Active',    'expiry' => '14 Mar 2027', 'registered' => '07 Aug 2026'],
    ['name' => 'Johane Masowe eChishanu',   'code' => 'MZ-0144', 'contact' => 'Elder M. Zvobgo',   'phone' => '+263 712 660 194', 'members' => '3,420', 'status' => 'Active',    'expiry' => '02 Feb 2027', 'registered' => '05 Aug 2026'],
    ['name' => 'Celebration Church Harare', 'code' => 'MZ-0143', 'contact' => 'Pastor L. Mhaka',   'phone' => '+263 773 508 621', 'members' => '1,780', 'status' => 'Active',    'expiry' => '19 Jan 2027', 'registered' => '03 Aug 2026'],
    ['name' => 'Family of God Bulawayo',    'code' => 'MZ-0142', 'contact' => 'Rev. P. Ndlovu',    'phone' => '+263 776 233 018', 'members' => '940',   'status' => 'Trial',     'expiry' => '05 Sep 2026', 'registered' => '01 Aug 2026'],
    ['name' => 'Methodist Mutare Circuit',  'code' => 'MZ-0141', 'contact' => 'Rev. G. Nyathi',    'phone' => '+263 774 887 552', 'members' => '1,315', 'status' => 'Active',    'expiry' => '22 Dec 2026', 'registered' => '29 Jul 2026'],
    ['name' => 'Glory Ministries Gweru',    'code' => 'MZ-0140', 'contact' => 'Pastor C. Dube',    'phone' => '+263 717 340 776', 'members' => '620',   'status' => 'Suspended', 'expiry' => '30 Jul 2026', 'registered' => '26 Jul 2026'],
    ['name' => 'Anglican Diocese Masvingo', 'code' => 'MZ-0139', 'contact' => 'Fr. E. Marange',    'phone' => '+263 779 115 442', 'members' => '2,480', 'status' => 'Active',    'expiry' => '11 Nov 2026', 'registered' => '24 Jul 2026'],
    ['name' => 'Faith World Ministries',    'code' => 'MZ-0138', 'contact' => 'Pastor R. Sibanda', 'phone' => '+263 771 662 903', 'members' => '1,050', 'status' => 'Expiring',  'expiry' => '15 Sep 2026', 'registered' => '21 Jul 2026'],
];

/* ── 6a. Recent activity ───────────────────────────────────────────────────
   LATER: SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 6; */
$activity = [
    ['text' => 'You logged in as <strong>Grace Ministries</strong>',          'time' => '10:42',            'tone' => 'blue'],
    ['text' => '<strong>ZCC Mbungo</strong> subscription extended 12 months', 'time' => '09:15',            'tone' => 'green'],
    ['text' => 'New church registered: <strong>Faith World Ministries</strong>','time' => 'Yesterday 16:20','tone' => 'blue'],
    ['text' => 'SMS top-up 2,000 credits &rarr; <strong>Celebration Church</strong>','time' => 'Yesterday 14:05','tone' => 'green'],
    ['text' => 'Backup job failed <em>(storage timeout)</em>',                'time' => '02:15',            'tone' => 'red'],
    ['text' => '<strong>Glory Ministries</strong> suspended for non-payment', 'time' => '21 Aug, 11:30',    'tone' => 'amber'],
];

/* ── 6b. Top churches by size ──────────────────────────────────────────────
   LATER: SELECT name, member_count FROM churches ORDER BY member_count DESC LIMIT 5; */
$topChurches = [
    ['name' => 'Johane Masowe eChishanu',  'members' => 3420],
    ['name' => 'Anglican Diocese Masvingo','members' => 2480],
    ['name' => 'AFM Waterfalls',           'members' => 2105],
    ['name' => 'Celebration Church Harare','members' => 1780],
    ['name' => 'Methodist Mutare Circuit', 'members' => 1315],
];
$topMax = max(array_column($topChurches, 'members'));

/* ── 6c. System health ─────────────────────────────────────────────────────
   LATER: read from cron_runs, backups, error_log tables and server info. */
$systemHealth = [
    ['label' => 'Cron last run',    'value' => '02:00',          'state' => 'ok'],
    ['label' => 'Last backup',      'value' => '02:15 (4.2 GB)', 'state' => 'ok'],
    ['label' => 'Errors last 24h',  'value' => '3',              'state' => 'warn'],
    ['label' => 'Database size',    'value' => '820 MB',         'state' => 'plain'],
    ['label' => 'Uptime',           'value' => '99.9%',          'state' => 'ok'],
    ['label' => 'PHP version',      'value' => '8.2',            'state' => 'plain'],
];
?>
<?php
// Path constants only; the sidebar markup is required further down.
$musPathsOnly = true;
require __DIR__ . '/components/sidebar.php';
$musPathsOnly = false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard — Mutendi CMS Super Admin</title>
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
      <i class="fa-regular fa-bell"></i>
      <span class="bell-badge">5</span>
    </button>
    <a class="btn btn--primary" href="#" data-modal="modalAdd"><i class="fa-solid fa-plus"></i> <span>Add Church</span></a>

    <div class="avatar-menu">
      <button class="avatar-menu__trigger" type="button">
        <span class="avatar">SA</span>
        <i class="fa-solid fa-chevron-down"></i>
      </button>
      <div class="avatar-menu__list">
        <a href="#"><i class="fa-regular fa-user"></i> Profile</a>
        <a href="#"><i class="fa-solid fa-gear"></i> Settings</a>
        <a href="#" class="is-danger"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
      </div>
    </div>
  </div>
</header>

<!-- ==================== SIDEBAR (shared component) ==================== -->
<?php require __DIR__ . '/components/sidebar.php'; ?>

<!-- ==================== MAIN ==================== -->
<main class="main">

  <!-- 0. Page header -->
  <div class="page-head">
    <div>
      <h1>Dashboard</h1>
      <p class="crumb">Home <i class="fa-solid fa-chevron-right"></i> Dashboard</p>
    </div>
    <div class="range">
      <?php foreach ($dateRanges as $r): ?>
        <a href="#" class="range__btn<?= $r === $activeRange ? ' is-on' : '' ?>"><?= htmlspecialchars($r) ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- 1. Primary KPI cards -->
  <section class="grid grid--4 kpis">
    <?php foreach ($primaryKpis as $k): ?>
      <a class="kpi kpi--<?= $k['tone'] ?>" href="#">
        <span class="kpi__icon"><i class="fa-solid <?= $k['icon'] ?>"></i></span>
        <span class="kpi__body">
          <span class="kpi__label"><?= htmlspecialchars($k['label']) ?></span>
          <span class="kpi__value"><?= htmlspecialchars($k['value']) ?></span>
          <span class="kpi__sub"><?= $k['sub'] ?></span>
        </span>
      </a>
    <?php endforeach; ?>
  </section>

  <!-- 2. Secondary metric cards -->
  <section class="grid grid--4 metrics">
    <?php foreach ($secondaryKpis as $m): ?>
      <div class="metric">
        <span class="metric__icon"><i class="fa-solid <?= $m['icon'] ?>"></i></span>
        <div class="metric__body">
          <span class="metric__label"><?= htmlspecialchars($m['label']) ?></span>
          <span class="metric__value"><?= htmlspecialchars($m['value']) ?></span>
          <?php if ($m['bar'] !== null): ?>
            <span class="bar"><i style="width: <?= (int) $m['bar'] ?>%"></i></span>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </section>

  <!-- 3. Revenue & growth + subscription status -->
  <section class="grid grid--2-1">
    <div class="card">
      <div class="card__head"><h2>Revenue &amp; Growth</h2></div>
      <div class="card__body">
        <div class="chart-wrap"><canvas id="revenueChart"></canvas></div>
        <div class="inline-stats">
          <div><span>Collected This Month</span><strong><?= $revenueStats['collected'] ?></strong></div>
          <div><span>Last Month</span><strong><?= $revenueStats['last'] ?></strong></div>
          <div><span>Change</span><strong class="pos"><?= $revenueStats['change'] ?></strong></div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card__head"><h2>Subscription Status</h2></div>
      <div class="card__body">
        <div class="chart-wrap chart-wrap--donut"><canvas id="statusChart"></canvas></div>
        <ul class="legend">
          <?php foreach ($statusBreakdown as $s): ?>
            <li>
              <span class="legend__dot" style="background: <?= $s['colour'] ?>"></span>
              <span class="legend__label"><?= htmlspecialchars($s['label']) ?></span>
              <span class="legend__count"><?= (int) $s['count'] ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </section>

  <!-- 4. Needs attention + dormant churches -->
  <section class="grid grid--2">
    <div class="card card--attention">
      <div class="card__head"><h2>Needs Your Attention</h2></div>
      <ul class="attn">
        <?php foreach ($attention as $a): ?>
          <li class="attn__row">
            <span class="dot dot--<?= $a['tone'] ?>"></span>
            <span class="attn__text">
              <strong><?= htmlspecialchars($a['text']) ?></strong>
              <small><?= htmlspecialchars($a['sub']) ?></small>
            </span>
            <a class="btn btn--sm" href="#"><?= htmlspecialchars($a['action']) ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="card">
      <div class="card__head"><h2>Dormant Churches</h2><span class="card__note">No login in 30+ days</span></div>
      <div class="table-wrap">
        <table class="table table--stack">
          <thead>
            <tr><th>Church</th><th>Last Login</th><th>Members</th><th>Expiry</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($dormant as $d): ?>
              <tr>
                <td data-label="Church" class="nowrap"><?= htmlspecialchars($d['church']) ?></td>
                <td data-label="Last Login" class="muted"><?= htmlspecialchars($d['last']) ?></td>
                <td data-label="Members"><?= htmlspecialchars($d['members']) ?></td>
                <td data-label="Expiry" class="muted"><?= htmlspecialchars($d['expiry']) ?></td>
                <td data-label="Actions" class="nowrap">
                  <a class="btn btn--sm btn--ghost" href="#"><i class="fa-solid fa-phone"></i> Call</a>
                  <a class="btn btn--sm btn--ghost" href="#"><i class="fa-regular fa-envelope"></i> Remind</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <!-- 5. Recently registered churches -->
  <section class="card">
    <div class="card__head"><h2>Recently Registered Churches</h2></div>
    <div class="table-wrap">
      <table class="table table--stack">
        <thead>
          <tr>
            <th>Church Name</th><th>Code</th><th>Contact Person</th><th>Phone</th>
            <th>Members</th><th>Status</th><th>Expiry Date</th><th>Registered</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentChurches as $c): ?>
            <tr>
              <td data-label="Church Name" class="strong nowrap"><?= htmlspecialchars($c['name']) ?></td>
              <td data-label="Code" class="muted"><?= htmlspecialchars($c['code']) ?></td>
              <td data-label="Contact Person" class="nowrap"><?= htmlspecialchars($c['contact']) ?></td>
              <td data-label="Phone" class="muted nowrap"><?= htmlspecialchars($c['phone']) ?></td>
              <td data-label="Members"><?= htmlspecialchars($c['members']) ?></td>
              <td data-label="Status"><span class="pill pill--<?= strtolower($c['status']) ?>"><?= htmlspecialchars($c['status']) ?></span></td>
              <td data-label="Expiry Date" class="muted nowrap"><?= htmlspecialchars($c['expiry']) ?></td>
              <td data-label="Registered" class="muted nowrap"><?= htmlspecialchars($c['registered']) ?></td>
              <td data-label="Actions" class="nowrap">
                <a class="ico-btn" href="#" title="View"><i class="fa-regular fa-eye"></i></a>
                <a class="ico-btn" href="#" title="Login As"><i class="fa-solid fa-right-to-bracket"></i></a>
                <a class="ico-btn" href="#" title="Extend"><i class="fa-solid fa-calendar-plus"></i></a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="card__foot"><a href="#">View all churches &rarr;</a></div>
  </section>

  <!-- 6. Activity / top churches / system health -->
  <section class="grid grid--3">
    <div class="card">
      <div class="card__head"><h2>Recent Activity</h2></div>
      <ul class="feed">
        <?php foreach ($activity as $a): ?>
          <li class="feed__row">
            <span class="dot dot--<?= $a['tone'] ?>"></span>
            <span class="feed__text"><?= $a['text'] ?><small><?= htmlspecialchars($a['time']) ?></small></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="card">
      <div class="card__head"><h2>Top Churches by Size</h2></div>
      <ul class="ranks">
        <?php foreach ($topChurches as $t): ?>
          <li>
            <span class="ranks__top">
              <span class="ranks__name"><?= htmlspecialchars($t['name']) ?></span>
              <span class="ranks__num"><?= number_format($t['members']) ?></span>
            </span>
            <span class="bar"><i style="width: <?= round($t['members'] / $topMax * 100) ?>%"></i></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="card">
      <div class="card__head"><h2>System Health</h2></div>
      <ul class="health">
        <?php foreach ($systemHealth as $h): ?>
          <li>
            <span class="health__label"><?= htmlspecialchars($h['label']) ?></span>
            <span class="health__value health__value--<?= $h['state'] ?>">
              <?php if ($h['state'] === 'ok'): ?><i class="fa-solid fa-circle-check"></i>
              <?php elseif ($h['state'] === 'warn'): ?><i class="fa-solid fa-triangle-exclamation"></i><?php endif; ?>
              <?= htmlspecialchars($h['value']) ?>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <!-- Footer -->
  <footer class="foot">
    <p class="foot__brand">Mutendi CMS</p>
    <p class="foot__copy">&copy; <?= date('Y') ?> Mutendi. All rights reserved.</p>
    <p class="foot__meta">Super Admin &middot; v1.0</p>
  </footer>

</main>

<!-- Add New Church — account type decides which fields apply. -->
<div class="modal" id="modalAdd" hidden>
  <div class="modal__box">
    <div class="modal__head">
      <h2><i class="fa-solid fa-plus"></i> Add New Church</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">

      <span class="field__label">Account type</span>
      <div class="typepick">
        <label class="typepick__opt">
          <input type="radio" name="acctype" value="paying" checked data-acctype="paying">
          <span class="typepick__body">
            <i class="fa-solid fa-credit-card"></i>
            <strong>Paying church</strong>
            <small>Choose a plan and record the payment</small>
          </span>
        </label>
        <label class="typepick__opt">
          <input type="radio" name="acctype" value="trial" data-acctype="trial">
          <span class="typepick__body">
            <i class="fa-solid fa-star"></i>
            <strong>Free trial</strong>
            <small>Let them evaluate first, convert later</small>
          </span>
        </label>
      </div>

      <div class="field-row">
        <label class="field"><span class="field__label">Church name</span>
          <input type="text" placeholder="e.g. ZCC Mbungo"></label>
        <label class="field"><span class="field__label">Church code</span>
          <input type="text" placeholder="ZCC-048"></label>
      </div>

      <div class="field-row">
        <label class="field"><span class="field__label">Contact person</span>
          <input type="text" placeholder="Bishop N. Mutendi"></label>
        <label class="field"><span class="field__label">Phone</span>
          <input type="tel" placeholder="+263 772 000 000"></label>
      </div>

      <label class="field"><span class="field__label">Email address</span>
        <input type="email" placeholder="admin@church.co.zw"></label>

      <div class="field-row">
        <label class="field"><span class="field__label">City</span>
          <input type="text" placeholder="Masvingo"></label>
        <label class="field"><span class="field__label">Province</span>
          <select><?php foreach ($provinces as $p): ?><option><?= htmlspecialchars($p) ?></option><?php endforeach; ?></select></label>
      </div>

      <!-- Paying churches only -->
      <div class="acc-block" data-acc="paying">
        <span class="field__label">Subscription plan</span>
        <div class="radios">
          <?php foreach (['Basic', 'Standard', 'Premium'] as $i => $plan): ?>
            <label class="radio"><input type="radio" name="newplan"<?= $i === 1 ? ' checked' : '' ?>><span><?= $plan ?></span></label>
          <?php endforeach; ?>
        </div>
        <div class="field-row">
          <label class="field"><span class="field__label">Starts on</span><input type="date"></label>
          <label class="field"><span class="field__label">Duration</span>
            <select><option>1 month</option><option>3 months</option><option>6 months</option><option selected>12 months</option></select></label>
        </div>
        <div class="field-row">
          <label class="field"><span class="field__label">Amount paid</span><input type="text" placeholder="$360.00"></label>
          <label class="field"><span class="field__label">Payment method</span>
            <select><option>Cash</option><option>EcoCash</option><option>Bank Transfer</option><option>Other</option></select></label>
        </div>
        <label class="field"><span class="field__label">Payment reference</span>
          <input type="text" placeholder="Receipt number or transaction ID"></label>
        <p class="preview"><span>Subscription runs until</span><strong>24 Aug 2027</strong></p>
      </div>

      <!-- Trials only -->
      <div class="acc-block" data-acc="trial" hidden>
        <span class="field__label">Trial length</span>
        <div class="radios">
          <?php foreach (['7 days', '14 days', '21 days', '30 days', 'Custom'] as $i => $d): ?>
            <label class="radio"><input type="radio" name="triallen"<?= $i === 1 ? ' checked' : '' ?>><span><?= $d ?></span></label>
          <?php endforeach; ?>
        </div>
        <div class="field-row">
          <label class="field"><span class="field__label">Starts on</span><input type="date"></label>
          <label class="field"><span class="field__label">Custom end date</span><input type="date"></label>
        </div>
        <p class="preview"><span>Trial ends</span><strong>07 Sep 2026</strong></p>
        <p class="notebox">No payment is recorded for a trial. Full access is granted for the period above, and the church appears under <strong>Trial Accounts</strong> where you can convert it to a paying plan at any time.</p>
      </div>

      <label class="field"><span class="field__label">Note (optional)</span>
        <textarea rows="2" placeholder="How they were referred, agreed price, anything worth recording..."></textarea></label>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--primary" type="button" id="addSubmit">Create Church</button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
/* Sidebar toggle + chart initialisation. */
(function () {
  // The sidebar collapses itself; the shell just follows its state so the
  // topbar and content keep their left edge against it.
  var bar = document.getElementById('musSidebar');
  var syncRail = function () {
    document.body.classList.toggle('nav-collapsed', bar.classList.contains('is-rail'));
  };
  syncRail();
  new MutationObserver(syncRail).observe(bar, { attributes: true, attributeFilter: ['class'] });

  /* Modal open/close — same behaviour as the churches page. */
  var closeModal = function (m) { m.hidden = true; document.body.classList.remove('modal-open'); };
  document.addEventListener('click', function (e) {
    var opener = e.target.closest('[data-modal]');
    if (opener) {
      e.preventDefault();
      var m = document.getElementById(opener.dataset.modal);
      if (m) { m.hidden = false; document.body.classList.add('modal-open'); }
      return;
    }
    if (e.target.closest('[data-close]') || e.target.classList.contains('modal')) {
      var box = e.target.closest('.modal');
      if (box) { closeModal(box); }
    }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { document.querySelectorAll('.modal:not([hidden])').forEach(closeModal); }
  });

  var grid = 'rgba(102,47,151,.10)', tick = '#9A93A6';

  new Chart(document.getElementById('revenueChart'), {
    data: {
      labels: <?= json_encode($chartMonths) ?>,
      datasets: [
        { type: 'bar', label: 'Activation revenue ($)',
          data: <?= json_encode($chartRevenue) ?>,
          backgroundColor: '#662F97', borderRadius: 5, maxBarThickness: 28, yAxisID: 'y' },
        { type: 'line', label: 'Active churches',
          data: <?= json_encode($chartCumulative) ?>,
          borderColor: '#1E8265', backgroundColor: '#1E8265',
          borderWidth: 2, tension: .35, pointRadius: 3, yAxisID: 'y1' }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true, color: tick } } },
      scales: {
        x:  { grid: { display: false }, ticks: { color: tick } },
        y:  { position: 'left',  beginAtZero: true, grid: { color: grid }, ticks: { color: tick, callback: function (v) { return '$' + v; } } },
        y1: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, ticks: { color: tick } }
      }
    }
  });

  new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
      labels: <?= json_encode(array_column($statusBreakdown, 'label')) ?>,
      datasets: [{
        data: <?= json_encode(array_column($statusBreakdown, 'count')) ?>,
        backgroundColor: <?= json_encode(array_column($statusBreakdown, 'colour')) ?>,
        borderWidth: 0, hoverOffset: 6
      }]
    },
    options: { responsive: true, maintainAspectRatio: false, cutout: '64%',
               plugins: { legend: { display: false } } }
  });
  /* Add New Church: the account type decides which block of fields shows,
     so trial and paying never appear as one confusing list. */
  var typeInputs = [].slice.call(document.querySelectorAll('[data-acctype]'));
  if (typeInputs.length) {
    var applyType = function () {
      var picked = document.querySelector('[data-acctype]:checked');
      var type = picked ? picked.dataset.acctype : 'paying';
      document.querySelectorAll('.acc-block').forEach(function (blk) {
        blk.hidden = blk.dataset.acc !== type;
      });
      var submit = document.getElementById('addSubmit');
      if (submit) { submit.textContent = type === 'trial' ? 'Start Trial' : 'Create Church'; }
    };
    typeInputs.forEach(function (i) { i.addEventListener('change', applyType); });

    // A button may ask for a type up front, e.g. "Start New Trial".
    document.addEventListener('click', function (e) {
      var opener = e.target.closest('[data-preset]');
      if (!opener) { return; }
      var radio = document.querySelector('[data-acctype="' + opener.dataset.preset + '"]');
      if (radio) { radio.checked = true; }
      applyType();
    });

    applyType();
  }
})();
</script>
</body>
</html>

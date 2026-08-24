<?php
/**
 * Mutendi CMS — Renewals & Activations (static UI mockup).
 *
 * Two views of the same money: who is due to renew, and every activation and
 * payment recorded so far. Payments are recorded manually when a church is
 * activated or extended — those notes are the only financial history, so there
 * is no plan or pricing model behind them. Trial and Paying churches differ
 * only by badge. Every dataset is hardcoded; each block carries its query.
 */
$musPathsOnly = true;
require __DIR__ . '/../components/sidebar.php';
$musPathsOnly = false;

/* ── TAB 1 headline ────────────────────────────────────────────────────────
   LATER: COUNT(*) over `churches` by expiry window, SUM of expected value. */
$renewTiles = [
    ['Due This Month',   '7',    'gold',   'fa-clock',        true],
    ['Renewed This Month','5',   'green',  'fa-circle-check', false],
    ['Overdue',          '3',    'berry',  'fa-triangle-exclamation', false],
    ['Revenue Expected', '$350', 'brand',  'fa-sack-dollar',  false],
];

/* ── Renewals forecast, next 6 months ──────────────────────────────────────
   LATER: SUM(last_amount) and COUNT(*) grouped by expiry month. */
$fcMonths  = ['Sep','Oct','Nov','Dec','Jan','Feb'];
$fcValue   = [350, 280, 420, 310, 260, 390];
$fcCount   = [9, 7, 11, 8, 6, 10];

/* ── Renewal outcome ───────────────────────────────────────────────────────
   LATER: SELECT outcome, COUNT(*) FROM renewals WHERE period = :window GROUP BY outcome; */
$outcome = [['Renewed',5,'#1E8265'],['Pending',7,'#96701F'],['Overdue',3,'#A93254'],['Lost',2,'#8C8398']];

/* ── Upcoming and overdue renewals ─────────────────────────────────────────
   LATER:
     SELECT c.*, p.amount AS last_amount, r.sent_at AS last_reminder,
            COUNT(a.id) AS renewal_count
       FROM churches c
       LEFT JOIN activations a ON a.church_id = c.id
       LEFT JOIN payments p ON p.id = c.last_payment_id
       LEFT JOIN reminders r ON r.church_id = c.id
      WHERE c.expiry_date <= NOW() + INTERVAL :window DAY
      GROUP BY c.id ORDER BY c.expiry_date; */
$renewals = [
 ['GM','Grace Ministries','GRM-003','Paying','Pastor T. Chikore','+263 771 902 335','12 Aug 2026',-12,30,30,3,'2 days ago','Overdue'],
 ['GG','Glory Ministries Gweru','GMG-008','Paying','Pastor C. Dube','+263 717 340 776','30 Jul 2026',-25,15,15,2,'Never','Overdue'],
 ['SH','SDA Hwange','SDA-014','Paying','Elder J. Sibanda','+263 719 447 630','05 Aug 2026',-19,15,15,1,'6 days ago','Overdue'],
 ['ZM','ZCC Mbungo','ZCC-001','Paying','Bishop N. Mutendi','+263 772 145 880','26 Aug 2026',2,50,50,4,'2 days ago','Due'],
 ['GH','Glad Tidings Harare','GTH-018','Paying','Pastor F. Nyamande','+263 771 604 337','28 Aug 2026',4,30,30,2,'Never','Due'],
 ['SM','St Marks Anglican Mutare','SMA-022','Paying','Rev. T. Chidziva','+263 778 229 415','30 Aug 2026',6,15,15,1,'6 days ago','Due'],
 ['FW','Faith World Ministries','FWM-010','Paying','Pastor R. Sibanda','+263 771 662 903','02 Sep 2026',9,30,30,2,'Never','Due'],
 ['CB','Christ Bethel Kwekwe','CBK-031','Paying','Pastor M. Ncube','+263 717 883 250','04 Sep 2026',11,15,15,1,'4 days ago','Due'],
 ['UM','UFIC Marondera','UFM-027','Paying','Pastor S. Kanyemba','+263 773 015 662','06 Sep 2026',13,50,50,3,'Never','Due'],
 ['AW','AFM Waterfalls','AFM-002','Paying','Rev. S. Banda','+263 778 411 207','14 Mar 2027',202,50,50,3,'—','Renewed'],
 ['CC','Celebration Church Harare','CCH-005','Paying','Pastor L. Mhaka','+263 773 508 621','19 Jan 2027',148,50,50,4,'—','Renewed'],
 ['FG','Family of God Bulawayo','FOG-006','Trial','Rev. P. Ndlovu','+263 776 233 018','05 Sep 2026',12,0,15,0,'3 days ago','Due'],
];

/* ── TAB 2 headline ────────────────────────────────────────────────────────
   LATER: SUM(amount) and COUNT(*) over `payments`. */
$actTiles = [
    ['Total Collected', '$2,480', 'green',  'fa-sack-dollar',  true],
    ['This Month',      '$850',   'indigo', 'fa-calendar-day', false],
    ['Activations',     '62',     'brand',  'fa-bolt',         false],
    ['Average Value',   '$40',    'grey',   'fa-equals',       false],
];

/* ── Collections over time ─────────────────────────────────────────────────
   LATER: SUM(amount), COUNT(*) FROM payments GROUP BY month ORDER BY paid_at; */
$colMonths = ['Sep','Oct','Nov','Dec','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug'];
$colAmount = [320, 410, 380, 520, 450, 610, 540, 700, 660, 780, 620, 850];
$colCount  = [8, 10, 9, 13, 11, 15, 13, 17, 16, 19, 15, 21];

/* ── Collections by payment method ─────────────────────────────────────────
   LATER: SELECT method, SUM(amount) FROM payments GROUP BY method; */
$methods = [['Cash',980,'#1E8265'],['EcoCash',870,'#662F97'],['Bank Transfer',480,'#5A57B5'],['Other',150,'#8C8398']];

/* ── Top paying churches by lifetime total ─────────────────────────────────
   LATER: SELECT church_id, SUM(amount) t FROM payments GROUP BY church_id ORDER BY t DESC LIMIT 5; */
$topPaying = [['Anglican Diocese Masvingo',260],['AFM Waterfalls',210],['Celebration Church Harare',200],
              ['Johane Masowe eChishanu',180],['ZCC Mbungo',160]];

/* ── Activation and payment history ────────────────────────────────────────
   LATER:
     SELECT p.*, c.name, c.code, c.account_type, u.name AS recorded_by
       FROM payments p
       JOIN churches c ON c.id = p.church_id
       JOIN admin_users u ON u.id = p.recorded_by_id
      ORDER BY p.paid_at DESC LIMIT :per_page OFFSET :offset; */
$history = [
 ['24 Aug 2026','14:22','ZM','ZCC Mbungo','ZCC-001','Paying','Renewal','12 months','26 Aug 2026 – 26 Aug 2027',50,'EcoCash','Receipt ECO-88412, paid by Bishop Mutendi','Super Admin'],
 ['23 Aug 2026','10:05','NL','New Life Chitungwiza','NLC-061','Trial','Trial Conversion','12 months','23 Aug 2026 – 23 Aug 2027',30,'Cash','Converted after 21-day trial','R. Dube'],
 ['21 Aug 2026','16:40','AW','AFM Waterfalls','AFM-002','Paying','Renewal','12 months','14 Mar 2026 – 14 Mar 2027',50,'Bank Transfer','CBZ ref 55231','Super Admin'],
 ['19 Aug 2026','09:12','SM','St Marks Anglican Mutare','SMA-022','Paying','Extension','3 months','30 Aug 2026 – 30 Nov 2026',15,'EcoCash','Short extension while awaiting funds','T. Moyo'],
 ['16 Aug 2026','11:58','CC','Celebration Church Harare','CCH-005','Paying','Renewal','12 months','19 Jan 2026 – 19 Jan 2027',50,'Cash','Paid in person at the office','Super Admin'],
 ['14 Aug 2026','15:30','JM','Johane Masowe eChishanu','JME-004','Paying','New Activation','12 months','14 Aug 2026 – 14 Aug 2027',50,'Bank Transfer','Steward Bank ref 90118','R. Dube'],
 ['11 Aug 2026','08:45','MM','Methodist Mutare Circuit','MMC-007','Paying','Renewal','6 months','22 Dec 2025 – 22 Jun 2026',30,'EcoCash','Receipt ECO-77220','Super Admin'],
 ['08 Aug 2026','13:20','FG','Family of God Bulawayo','FOG-006','Trial','New Activation','14 days','01 Aug 2026 – 15 Aug 2026',0,'Other','Trial account, no payment','T. Moyo'],
 ['05 Aug 2026','17:04','AD','Anglican Diocese Masvingo','ADM-009','Paying','Renewal','12 months','11 Nov 2025 – 11 Nov 2026',50,'Bank Transfer','Diocesan cheque cleared 05 Aug','Super Admin'],
 ['02 Aug 2026','10:33','UF','UFIC Chinhoyi','UFI-013','Paying','Renewal','12 months','30 Apr 2026 – 30 Apr 2027',30,'Cash','Collected during a site visit','R. Dube'],
 ['28 Jul 2026','14:50','ZA','Zion Apostolic Marondera','ZAM-012','Paying','Extension','6 months','08 Jun 2026 – 08 Dec 2026',30,'EcoCash','Receipt ECO-71005','Super Admin'],
 ['24 Jul 2026','09:18','FW','Faith World Ministries','FWM-010','Paying','Renewal','6 months','15 Sep 2025 – 15 Mar 2026',30,'Cash','Paid at Kwekwe branch','T. Moyo'],
 ['19 Jul 2026','16:12','CB','Christ Bethel Kwekwe','CBK-031','Paying','New Activation','12 months','19 Jul 2026 – 19 Jul 2027',15,'EcoCash','First subscription, discounted','Super Admin'],
 ['12 Jul 2026','11:40','GH','Glad Tidings Harare','GTH-018','Paying','Renewal','12 months','28 Aug 2025 – 28 Aug 2026',30,'Bank Transfer','ZB Bank ref 40922','R. Dube'],
 ['06 Jul 2026','15:55','SH','SDA Hwange','SDA-014','Paying','New Activation','12 months','05 Aug 2025 – 05 Aug 2026',15,'Cash','Signed up at the Hwange conference','Super Admin'],
];
$historyTotal = array_sum(array_column($history, 9));

$provinceList = ['All Provinces','Harare','Bulawayo','Manicaland','Midlands','Masvingo',
                 'Mashonaland East','Mashonaland West','Mashonaland Central',
                 'Matabeleland North','Matabeleland South'];

$activePage    = 'reports/renewals';
$sidebarBadges = ['pending' => 10, 'expiring' => 12];
$sidebarUser   = ['name' => 'Super Admin', 'role' => 'System Owner'];
$pageTitle     = 'Renewals & Activations';
$pageHint      = 'Upcoming renewals and a full history of activations and payments recorded.';

/** Payment type to badge modifier. */
function ptype(string $t): string { return 'ptype--' . strtolower(str_replace(' ', '-', $t)); }
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

  <div class="tabs" role="tablist">
    <button class="tab is-on" type="button" role="tab" data-tab="renewals">Renewals</button>
    <button class="tab" type="button" role="tab" data-tab="history">Activation History</button>
  </div>

  <!-- ═══════════ TAB 1 — RENEWALS ═══════════ -->
  <div class="tabpanel" data-panel="renewals">
    <div class="statstrip">
      <?php foreach ($renewTiles as [$label, $value, $tone, $icon, $on]): ?>
        <a class="stat-tile stat-tile--<?= $tone ?><?= $on ? ' is-on' : '' ?>" href="#">
          <span class="stat-tile__icon"><i class="fa-solid <?= $icon ?>"></i></span>
          <span class="stat-tile__body">
            <span class="stat-tile__value"><?= $value ?></span>
            <span class="stat-tile__label"><?= htmlspecialchars($label) ?></span>
          </span>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="grid grid--2-1">
      <div class="card">
        <div class="card__head"><h2>Renewals Forecast</h2><span class="card__note">Next 6 months</span></div>
        <div class="card__body"><div class="chart-wrap"><canvas id="forecastChart"></canvas></div></div>
      </div>
      <div class="card">
        <div class="card__head"><h2>Renewal Outcome</h2></div>
        <div class="card__body">
          <div class="chart-wrap chart-wrap--donut"><canvas id="outcomeChart"></canvas></div>
          <ul class="legend">
            <?php foreach ($outcome as [$l, $c, $col]): ?>
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
            <input type="search" placeholder="Search by church, code or contact..."></span></label>
        <label class="field"><span class="field__label">Renewal Window</span>
          <select><option>Next 7 days</option><option>Next 14 days</option><option selected>Next 30 days</option><option>Next 60 days</option><option>Next 90 days</option></select></label>
        <label class="field"><span class="field__label">Status</span>
          <select><option>All</option><option>Due</option><option>Overdue</option><option>Renewed</option><option>Lost</option></select></label>
        <label class="field"><span class="field__label">Province</span>
          <select><?php foreach ($provinceList as $p): ?><option><?= $p ?></option><?php endforeach; ?></select></label>
        <label class="field"><span class="field__label">Sort by</span>
          <select><option>Soonest Due</option><option>Highest Value</option><option>Longest Overdue</option></select></label>
      </div>
      <div class="filterbar__foot">
        <div class="filterbar__actions">
          <button class="btn btn--primary" type="button"><i class="fa-solid fa-filter"></i> Apply Filters</button>
          <a class="link-reset" href="#">Reset</a>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card__head"><h2>Upcoming &amp; Overdue Renewals</h2></div>
      <div class="table-wrap">
        <table class="table table--churches">
          <thead>
            <tr>
              <th class="col-num">#</th><th>Church</th><th>Contact</th><th>Expiry Date</th><th>Days</th>
              <th class="ta-right">Last Paid</th><th class="ta-right">Expected</th><th>Renewals</th>
              <th>Last Reminder</th><th>Status</th><th class="col-actions">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($renewals as $i => $r): ?>
              <?php
                [$in,$name,$code,$acct,$contact,$phone,$expiry,$days,$lastPaid,$expected,$count,$reminder,$status] = $r;
                $overdue = $days < 0;
                $tone = $overdue ? 'berry' : ($days <= 14 ? 'gold' : 'indigo');
                $ord = $count === 0 ? 'First renewal' : ($count . ($count === 1 ? 'st' : ($count === 2 ? 'nd' : ($count === 3 ? 'rd' : 'th'))) . ' renewal');
              ?>
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
                <td><span class="stack"><strong><?= htmlspecialchars($contact) ?></strong><small><?= $phone ?></small></span></td>
                <td class="nowrap">
                  <span class="stack"><strong><?= $expiry ?></strong>
                    <small class="note--<?= $tone ?>"><?= $overdue ? 'overdue by ' . abs($days) . ' days' : 'in ' . $days . ' days' ?></small></span>
                </td>
                <td><span class="countdown countdown--<?= $tone ?>"><?= abs($days) ?>d</span></td>
                <td class="ta-right"><?= $lastPaid ? '$' . $lastPaid : '<span class="muted">&mdash;</span>' ?></td>
                <td class="ta-right strong">$<?= $expected ?></td>
                <td class="nowrap muted"><?= $ord ?></td>
                <td class="nowrap <?= $reminder === 'Never' ? 'muted' : '' ?>"><?= $reminder ?></td>
                <td><span class="pill pill--<?= strtolower($status) ?>"><?= $status ?></span></td>
                <td class="col-actions">
                  <div class="row-actions">
                    <button class="btn btn--sm btn--go" type="button" data-modal="modalExtend"><i class="fa-solid fa-calendar-plus"></i> Extend</button>
                    <button class="ico-btn" type="button" title="Send Reminder" aria-label="Send Reminder" data-modal="modalRemind"><i class="fa-solid fa-bell"></i></button>
                    <div class="dropdown dropdown--menu">
                      <button class="ico-btn dropdown__trigger" type="button" title="More" aria-label="More actions"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                      <div class="dropdown__menu dropdown__menu--right">
                        <a href="#"><i class="fa-solid fa-eye"></i> View Church</a>
                        <a href="#"><i class="fa-solid fa-right-to-bracket"></i> Login As</a>
                        <a href="#"><i class="fa-solid fa-note-sticky"></i> Add Note</a>
                        <span class="dropdown__sep"></span>
                        <a href="#" class="is-danger" data-modal="modalLost"><i class="fa-solid fa-circle-xmark"></i> Mark as Lost</a>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="tablefoot">
        <p class="tablefoot__count">Showing 1 to <?= count($renewals) ?> of <?= count($renewals) ?> entries</p>
        <nav class="pagination" aria-label="Pagination">
          <a class="pagination__btn is-disabled" href="#">Previous</a>
          <a class="pagination__btn is-on" href="#">1</a>
          <a class="pagination__btn is-disabled" href="#">Next</a>
        </nav>
      </div>
    </div>
  </div>

  <!-- ═══════════ TAB 2 — ACTIVATION HISTORY ═══════════ -->
  <div class="tabpanel" data-panel="history" hidden>
    <div class="statstrip">
      <?php foreach ($actTiles as [$label, $value, $tone, $icon, $on]): ?>
        <a class="stat-tile stat-tile--<?= $tone ?><?= $on ? ' is-on' : '' ?>" href="#">
          <span class="stat-tile__icon"><i class="fa-solid <?= $icon ?>"></i></span>
          <span class="stat-tile__body">
            <span class="stat-tile__value"><?= $value ?></span>
            <span class="stat-tile__label"><?= htmlspecialchars($label) ?></span>
          </span>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="card">
      <div class="card__head"><h2>Collections Over Time</h2><span class="card__note">Last 12 months</span></div>
      <div class="card__body">
        <div class="chart-wrap"><canvas id="collectChart"></canvas></div>
        <div class="inline-stats">
          <div><span>This Month</span><strong>$850</strong></div>
          <div><span>Last Month</span><strong>$620</strong></div>
          <div><span>Change</span><strong class="pos">+37%</strong></div>
        </div>
      </div>
    </div>

    <div class="grid grid--2">
      <div class="card">
        <div class="card__head"><h2>Collections by Payment Method</h2></div>
        <div class="card__body">
          <div class="chart-wrap chart-wrap--donut"><canvas id="methodChart"></canvas></div>
          <ul class="legend">
            <?php foreach ($methods as [$l, $amt, $col]): ?>
              <li><span class="legend__dot" style="background: <?= $col ?>"></span>
                <span class="legend__label"><?= $l ?></span><span class="legend__count">$<?= number_format($amt) ?></span></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>

      <div class="card">
        <div class="card__head"><h2>Top Paying Churches</h2><span class="card__note">Lifetime total</span></div>
        <ul class="ranks">
          <?php $tMax = $topPaying[0][1]; foreach ($topPaying as [$name, $amt]): ?>
            <li>
              <span class="ranks__top">
                <span class="ranks__name"><?= htmlspecialchars($name) ?></span>
                <span class="ranks__num">$<?= number_format($amt) ?></span>
              </span>
              <span class="bar"><i style="width: <?= round($amt / $tMax * 100) ?>%"></i></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>

    <div class="card filterbar">
      <div class="filterbar__row">
        <label class="field field--search"><span class="field__label">Search</span>
          <span class="field__input"><i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" placeholder="Search by church, code or reference..."></span></label>
        <label class="field"><span class="field__label">Type</span>
          <select><option>All Types</option><option>New Activation</option><option>Renewal</option><option>Extension</option><option>Trial Conversion</option></select></label>
        <label class="field"><span class="field__label">Payment Method</span>
          <select><option>All Methods</option><option>Cash</option><option>EcoCash</option><option>Bank Transfer</option><option>Other</option></select></label>
        <label class="field"><span class="field__label">Min Amount</span><input type="number" placeholder="$0"></label>
        <label class="field"><span class="field__label">Max Amount</span><input type="number" placeholder="$500"></label>
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

    <div class="card">
      <div class="card__head"><h2>Activation &amp; Payment History</h2></div>
      <div class="table-wrap">
        <table class="table table--churches">
          <thead>
            <tr>
              <th class="col-num">#</th><th>Date</th><th>Church</th><th>Type</th><th>Duration</th>
              <th>Period Covered</th><th class="ta-right">Amount</th><th>Method</th>
              <th>Reference / Note</th><th>Recorded By</th><th class="col-actions">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($history as $i => $h): ?>
              <?php [$date,$time,$in,$name,$code,$acct,$type,$dur,$period,$amt,$method,$note,$by] = $h; ?>
              <tr>
                <td class="col-num muted"><?= $i + 1 ?></td>
                <td class="nowrap"><span class="stack"><strong><?= $date ?></strong><small><?= $time ?></small></span></td>
                <td>
                  <div class="church">
                    <span class="church__avatar"><?= $in ?></span>
                    <span class="church__text">
                      <strong><?= htmlspecialchars($name) ?></strong>
                      <small><?= $code ?> <span class="pill pill--<?= strtolower($acct) ?> pill--xs"><?= $acct ?></span></small>
                    </span>
                  </div>
                </td>
                <td><span class="ptype <?= ptype($type) ?>"><?= $type ?></span></td>
                <td class="nowrap muted"><?= $dur ?></td>
                <td class="nowrap muted"><?= $period ?></td>
                <td class="ta-right strong"><?= $amt ? '$' . number_format($amt) : '<span class="muted">&mdash;</span>' ?></td>
                <td><span class="pmethod"><?= $method ?></span></td>
                <td class="muted descell"><?= htmlspecialchars($note) ?></td>
                <td class="nowrap muted"><?= $by ?></td>
                <td class="col-actions">
                  <div class="row-actions">
                    <button class="ico-btn" type="button" title="View Details" aria-label="View Details" data-modal="modalPayment"><i class="fa-regular fa-eye"></i></button>
                    <div class="dropdown dropdown--menu">
                      <button class="ico-btn dropdown__trigger" type="button" title="More" aria-label="More actions"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                      <div class="dropdown__menu dropdown__menu--right">
                        <a href="#"><i class="fa-solid fa-church"></i> View Church</a>
                        <a href="#" data-modal="modalPayment"><i class="fa-solid fa-note-sticky"></i> View Note</a>
                        <a href="#"><i class="fa-solid fa-print"></i> Print Receipt</a>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <tr class="table__total">
              <td colspan="6">Total for current filter</td>
              <td class="ta-right">$<?= number_format($historyTotal) ?></td>
              <td colspan="4"></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="tablefoot">
        <p class="tablefoot__count">Showing 1 to <?= count($history) ?> of 62 entries</p>
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

<!-- ==================== MODALS (shared across both tabs) ==================== -->

<div class="modal" id="modalExtend" hidden>
  <div class="modal__box">
    <div class="modal__head">
      <h2><i class="fa-solid fa-calendar-plus"></i> Extend Subscription</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <label class="field"><span class="field__label">Church</span>
        <input type="text" value="ZCC Mbungo (ZCC-001)" readonly></label>
      <span class="field__label">Duration</span>
      <div class="radios">
        <?php foreach (['1 month','3 months','6 months','12 months','Custom'] as $i => $d): ?>
          <label class="radio"><input type="radio" name="exdur"<?= $i === 3 ? ' checked' : '' ?>><span><?= $d ?></span></label>
        <?php endforeach; ?>
      </div>
      <div class="field-row">
        <label class="field"><span class="field__label">Custom expiry date</span><input type="date"></label>
        <label class="field"><span class="field__label">Amount paid</span><input type="text" placeholder="$50.00"></label>
      </div>
      <label class="field"><span class="field__label">Payment method</span>
        <select><option>Cash</option><option>EcoCash</option><option>Bank Transfer</option><option>Other</option></select></label>
      <label class="field"><span class="field__label">Reference / note</span>
        <textarea rows="2" placeholder="Receipt number, who paid, any note..."></textarea></label>
      <p class="preview"><span>New expiry date</span><strong>26 Aug 2027</strong></p>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--go" type="button">Confirm Extension</button>
    </div>
  </div>
</div>

<div class="modal" id="modalRemind" hidden>
  <div class="modal__box">
    <div class="modal__head">
      <h2><i class="fa-solid fa-bell"></i> Send Reminder</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <p class="modal__lead">Reminder for <strong>ZCC Mbungo (ZCC-001)</strong>, expiring in 2 days.</p>
      <span class="field__label">Channel</span>
      <div class="radios">
        <label class="radio"><input type="checkbox" checked><span>Email</span></label>
        <label class="radio"><input type="checkbox" checked><span>SMS</span></label>
      </div>
      <label class="field"><span class="field__label">Template</span>
        <select><option>Renewal due — friendly</option><option>Renewal due — final notice</option><option>Payment instructions</option><option>Custom message</option></select></label>
      <label class="field"><span class="field__label">Message</span>
        <textarea rows="4">Dear Bishop Mutendi, your Mutendi CMS subscription for ZCC Mbungo expires on 26 Aug 2026. Please arrange renewal to avoid interruption.</textarea></label>
      <p class="notebox">Preview: sent to +263 772 145 880 and the church admin email.</p>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--primary" type="button">Send Reminder</button>
    </div>
  </div>
</div>

<div class="modal" id="modalPayment" hidden>
  <div class="modal__box">
    <div class="modal__head">
      <h2><i class="fa-regular fa-eye"></i> Payment Details</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <section class="msec">
        <h3 class="msec__title">Record</h3>
        <dl class="summary">
          <div><dt>Church</dt><dd>ZCC Mbungo (ZCC-001) <span class="pill pill--paying pill--xs">Paying</span></dd></div>
          <div><dt>Type</dt><dd><span class="ptype ptype--renewal">Renewal</span></dd></div>
          <div><dt>Duration</dt><dd>12 months</dd></div>
          <div><dt>Period covered</dt><dd>26 Aug 2026 – 26 Aug 2027</dd></div>
          <div><dt>Amount</dt><dd><strong>$50.00</strong></dd></div>
          <div><dt>Payment method</dt><dd><span class="pmethod">EcoCash</span></dd></div>
          <div><dt>Recorded by</dt><dd>Super Admin</dd></div>
          <div><dt>Recorded at</dt><dd>24 Aug 2026, 14:22</dd></div>
        </dl>
      </section>
      <section class="msec">
        <h3 class="msec__title">Reference / Note</h3>
        <p class="notebox">Receipt ECO-88412, paid by Bishop Mutendi. Confirmed by SMS from the EcoCash merchant line the same afternoon.</p>
      </section>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Close</button>
      <button class="btn" type="button"><i class="fa-solid fa-print"></i> Print Receipt</button>
      <button class="btn btn--primary" type="button">View Church</button>
    </div>
  </div>
</div>

<div class="modal" id="modalLost" hidden>
  <div class="modal__box modal__box--sm">
    <div class="modal__head">
      <h2><i class="fa-solid fa-triangle-exclamation note--berry"></i> Mark as Lost</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <p class="modal__lead">Marking <strong>Grace Ministries (GRM-003)</strong> as lost removes it from the renewal forecast. Its records are untouched.</p>
      <label class="field"><span class="field__label">Reason</span>
        <select><option>Could not afford</option><option>Switched provider</option><option>Church closed</option><option>No response</option><option>Other</option></select></label>
      <label class="field"><span class="field__label">Notes</span>
        <textarea rows="3" placeholder="Anything worth recording for later..."></textarea></label>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--danger-solid" type="button">Mark as Lost</button>
    </div>
  </div>
</div>

<script>
(function () {
  var grid = 'rgba(102,47,151,.10)', tick = '#9A93A6';
  var base = { responsive: true, maintainAspectRatio: false,
               interaction: { mode: 'index', intersect: false },
               plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true, color: tick } } } };

  new Chart(document.getElementById('forecastChart'), {
    data: { labels: <?= json_encode($fcMonths) ?>,
      datasets: [
        { type: 'bar', label: 'Expected value ($)', data: <?= json_encode($fcValue) ?>,
          backgroundColor: '#662F97', borderRadius: 5, maxBarThickness: 30, yAxisID: 'y' },
        { type: 'line', label: 'Churches renewing', data: <?= json_encode($fcCount) ?>,
          borderColor: '#1E8265', backgroundColor: '#1E8265', borderWidth: 2, tension: .35, pointRadius: 3, yAxisID: 'y1' }
      ] },
    options: Object.assign({}, base, { scales: {
      x:  { grid: { display: false }, ticks: { color: tick } },
      y:  { position: 'left', beginAtZero: true, grid: { color: grid },
            ticks: { color: tick, callback: function (v) { return '$' + v; } } },
      y1: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, ticks: { color: tick } }
    }})
  });

  new Chart(document.getElementById('outcomeChart'), {
    type: 'doughnut',
    data: { labels: <?= json_encode(array_column($outcome, 0)) ?>,
      datasets: [{ data: <?= json_encode(array_column($outcome, 1)) ?>,
        backgroundColor: <?= json_encode(array_column($outcome, 2)) ?>, borderWidth: 0, hoverOffset: 6 }] },
    options: { responsive: true, maintainAspectRatio: false, cutout: '64%', plugins: { legend: { display: false } } }
  });

  var deferred = [];
  deferred.push(new Chart(document.getElementById('collectChart'), {
    data: { labels: <?= json_encode($colMonths) ?>,
      datasets: [
        { type: 'bar', label: 'Collected ($)', data: <?= json_encode($colAmount) ?>,
          backgroundColor: '#662F97', borderRadius: 5, maxBarThickness: 28, yAxisID: 'y' },
        { type: 'line', label: 'Activations', data: <?= json_encode($colCount) ?>,
          borderColor: '#1E8265', backgroundColor: '#1E8265', borderWidth: 2, tension: .35, pointRadius: 3, yAxisID: 'y1' }
      ] },
    options: Object.assign({}, base, { scales: {
      x:  { grid: { display: false }, ticks: { color: tick } },
      y:  { position: 'left', beginAtZero: true, grid: { color: grid },
            ticks: { color: tick, callback: function (v) { return '$' + v; } } },
      y1: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, ticks: { color: tick } }
    }})
  }));

  deferred.push(new Chart(document.getElementById('methodChart'), {
    type: 'doughnut',
    data: { labels: <?= json_encode(array_column($methods, 0)) ?>,
      datasets: [{ data: <?= json_encode(array_column($methods, 1)) ?>,
        backgroundColor: <?= json_encode(array_column($methods, 2)) ?>, borderWidth: 0, hoverOffset: 6 }] },
    options: { responsive: true, maintainAspectRatio: false, cutout: '64%', plugins: { legend: { display: false } } }
  }));

  /* Charts built inside a hidden tab have no size yet — give them one the
     moment their panel is shown. */
  document.querySelectorAll('.tab[data-tab]').forEach(function (t) {
    t.addEventListener('click', function () {
      setTimeout(function () { deferred.forEach(function (c) { c.resize(); }); }, 30);
    });
  });
})();
</script>
</body>
</html>

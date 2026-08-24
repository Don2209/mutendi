<?php
/**
 * Mutendi CMS — Expiring Soon (static UI mockup).
 *
 * The renewal chase list — subscriptions lapsing within 30 days, most urgent first.
 * Every dataset is hardcoded; each block carries the query that will replace
 * it. Search, filters, sorting and pagination are visual only.
 */

/* Path constants only — the sidebar markup is required further down. */
$musPathsOnly = true;
require __DIR__ . '/../components/sidebar.php';
$musPathsOnly = false;

/* ── Renewal chase list ────────────────────────────────────────────────────
   LATER:
     SELECT c.*, p.name AS plan, p.price AS renewal_value, r.sent_at AS last_reminder
       FROM churches c
       JOIN plans p ON p.id = c.plan_id
       LEFT JOIN reminders r ON r.church_id = c.id AND r.type = 'renewal'
      WHERE c.expiry_date BETWEEN NOW() AND NOW() + INTERVAL :window DAY
      ORDER BY c.expiry_date ASC;
   `days` is DATEDIFF(expiry_date, NOW()); the colour tone is derived from it. */
$rows = [
    ['initials'=>'ZM','name'=>'ZCC Mbungo','code'=>'ZCC-001','contact'=>'Bishop N. Mutendi','phone'=>'+263 772 145 880','plan'=>'Premium','members'=>1240,'expiry'=>'26 Aug 2026','days'=>2,'value'=>50,'reminder'=>'2 days ago','login'=>'5 hours ago'],
    ['initials'=>'GH','name'=>'Glad Tidings Harare','code'=>'GTH-018','contact'=>'Pastor F. Nyamande','phone'=>'+263 771 604 337','plan'=>'Standard','members'=>720,'expiry'=>'28 Aug 2026','days'=>4,'value'=>30,'reminder'=>'Never','login'=>'Yesterday'],
    ['initials'=>'SM','name'=>'St Marks Anglican Mutare','code'=>'SMA-022','contact'=>'Rev. T. Chidziva','phone'=>'+263 778 229 415','plan'=>'Basic','members'=>380,'expiry'=>'30 Aug 2026','days'=>6,'value'=>15,'reminder'=>'6 days ago','login'=>'3 days ago'],
    ['initials'=>'FW','name'=>'Faith World Ministries','code'=>'FWM-010','contact'=>'Pastor R. Sibanda','phone'=>'+263 771 662 903','plan'=>'Standard','members'=>1050,'expiry'=>'02 Sep 2026','days'=>9,'value'=>30,'reminder'=>'Never','login'=>'8 days ago'],
    ['initials'=>'CB','name'=>'Christ Bethel Kwekwe','code'=>'CBK-031','contact'=>'Pastor M. Ncube','phone'=>'+263 717 883 250','plan'=>'Basic','members'=>465,'expiry'=>'04 Sep 2026','days'=>11,'value'=>15,'reminder'=>'4 days ago','login'=>'2 days ago'],
    ['initials'=>'UM','name'=>'UFIC Marondera','code'=>'UFM-027','contact'=>'Pastor S. Kanyemba','phone'=>'+263 773 015 662','plan'=>'Premium','members'=>1580,'expiry'=>'06 Sep 2026','days'=>13,'value'=>50,'reminder'=>'Never','login'=>'12 hours ago'],
    ['initials'=>'AG','name'=>'AFM Gweru Central','code'=>'AFG-014','contact'=>'Rev. D. Moyo','phone'=>'+263 774 337 108','plan'=>'Standard','members'=>880,'expiry'=>'11 Sep 2026','days'=>18,'value'=>30,'reminder'=>'9 days ago','login'=>'4 days ago'],
    ['initials'=>'MZ','name'=>'Methodist Zvishavane','code'=>'MTZ-035','contact'=>'Rev. G. Ndlovu','phone'=>'+263 776 442 719','plan'=>'Basic','members'=>310,'expiry'=>'14 Sep 2026','days'=>21,'value'=>15,'reminder'=>'Never','login'=>'6 days ago'],
    ['initials'=>'JN','name'=>'Johane Marange Norton','code'=>'JMN-019','contact'=>'Elder P. Zvobgo','phone'=>'+263 712 550 384','plan'=>'Premium','members'=>2260,'expiry'=>'17 Sep 2026','days'=>24,'value'=>50,'reminder'=>'11 days ago','login'=>'Yesterday'],
    ['initials'=>'CV','name'=>'Celebration Victoria Falls','code'=>'CVF-041','contact'=>'Pastor L. Dube','phone'=>'+263 779 226 550','plan'=>'Standard','members'=>640,'expiry'=>'20 Sep 2026','days'=>27,'value'=>30,'reminder'=>'Never','login'=>'9 days ago'],
    ['initials'=>'SD','name'=>'SDA Chegutu','code'=>'SDC-029','contact'=>'Elder J. Mangwiro','phone'=>'+263 771 908 244','plan'=>'Basic','members'=>420,'expiry'=>'22 Sep 2026','days'=>29,'value'=>15,'reminder'=>'3 days ago','login'=>'5 days ago'],
    ['initials'=>'RC','name'=>'Roman Catholic Chinhoyi','code'=>'RCC-036','contact'=>'Fr. E. Marimo','phone'=>'+263 778 661 903','plan'=>'Premium','members'=>1930,'expiry'=>'23 Sep 2026','days'=>30,'value'=>50,'reminder'=>'Never','login'=>'2 days ago'],
];

/** Urgency tone: red inside a week, amber inside a fortnight, calm beyond. */
function urgency(int $days): string {
    if ($days <= 7)  { return 'berry'; }
    if ($days <= 14) { return 'gold'; }
    return 'indigo';
}

$rowCount = count($rows);

/* ── Stat strip ───────────────────────────────────────────────────────────
   Derived from the list so the tiles can never drift from the table.
   LATER: COUNT(*) with the matching DATEDIFF windows, SUM(p.price). */
$in7    = count(array_filter($rows, fn($c) => $c['days'] <= 7));
$in14   = count(array_filter($rows, fn($c) => $c['days'] <= 14));
$atRisk = array_sum(array_column($rows, 'value'));

$statTiles = [
    ['label' => 'Expiring in 7 Days',   'value' => $in7,             'tone' => 'berry',  'icon' => 'fa-fire',        'on' => false],
    ['label' => 'Expiring in 14 Days',  'value' => $in14,            'tone' => 'gold',   'icon' => 'fa-clock',       'on' => false],
    ['label' => 'Expiring in 30 Days',  'value' => $rowCount,        'tone' => 'indigo', 'icon' => 'fa-calendar-day','on' => true],
    ['label' => 'Total Revenue at Risk','value' => '$' . number_format($atRisk), 'tone' => 'brand', 'icon' => 'fa-sack-dollar', 'on' => false],
];

$provinces = ['Harare', 'Bulawayo', 'Manicaland', 'Midlands', 'Masvingo',
              'Mashonaland East', 'Mashonaland West', 'Mashonaland Central',
              'Matabeleland North', 'Matabeleland South'];

$columns = [
    ['label' => 'Church',        'sort' => null],
    ['label' => 'Contact',       'sort' => null],
    ['label' => 'Plan',          'sort' => null],
    ['label' => 'Members',       'sort' => null, 'align' => 'right'],
    ['label' => 'Expiry Date',   'sort' => 'asc'],
    ['label' => 'Days Left',     'sort' => null],
    ['label' => 'Renewal Value', 'sort' => null, 'align' => 'right'],
    ['label' => 'Last Reminder', 'sort' => null],
    ['label' => 'Last Login',    'sort' => null],
];

/* LATER: gate entries on the signed-in admin's permissions. */
$rowMenu = [
    ['label' => 'View Church',      'icon' => 'fa-eye'],
    ['label' => 'Login As',         'icon' => 'fa-right-to-bracket'],
    ['label' => 'Send Reminder',    'icon' => 'fa-bell',        'modal' => 'modalRemind'],
    ['label' => 'Add Note',         'icon' => 'fa-note-sticky', 'modal' => 'modalNote'],
    ['label' => 'Edit Church',      'icon' => 'fa-pen'],
    ['label' => 'Suspend',          'icon' => 'fa-ban',         'sep' => true],
    ['label' => 'View Activity Log','icon' => 'fa-wave-square'],
];

$activePage    = 'churches/expiring';
$sidebarBadges = ['pending' => 10, 'expiring' => $rowCount];
$sidebarUser   = ['name' => 'Super Admin', 'role' => 'System Owner'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Expiring Soon — Mutendi CMS Super Admin</title>
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
    <a class="btn btn--primary" href="<?= $base_url ?>churches/all.php"><i class="fa-solid fa-plus"></i> <span>Add Church</span></a>

    <div class="avatar-menu">
      <button class="avatar-menu__trigger" type="button">
        <span class="avatar">SA</span>
        <i class="fa-solid fa-chevron-down"></i>
      </button>
      <div class="avatar-menu__list">
        <a href="<?= $base_url ?>admin/profile.php"><i class="fa-regular fa-user"></i> Profile</a>
        <a href="<?= $base_url ?>system/general.php"><i class="fa-solid fa-gear"></i> Settings</a>
        <a href="<?= $base_url ?>logout.php" class="is-danger"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
      </div>
    </div>
  </div>
</header>

<!-- ==================== SIDEBAR (shared component) ==================== -->
<?php require __DIR__ . '/../components/sidebar.php'; ?>

<!-- ==================== MAIN ==================== -->
<main class="main">

  <!-- 1. Page header -->
  <div class="page-head">
    <div>
      <h1>Expiring Soon <span class="title-badge"><?= $rowCount ?></span></h1>
      <p class="crumb">
        <a href="<?= $base_url ?>index.php">Dashboard</a> <i class="fa-solid fa-chevron-right"></i>
        Churches <i class="fa-solid fa-chevron-right"></i> Expiring Soon
      </p>
      <p class="page-hint">Subscriptions expiring within the next 30 days — contact these churches to renew.</p>
    </div>
    <div class="head-actions">
      <button class="btn btn--primary" type="button" data-modal="modalRemind"><i class="fa-solid fa-bell"></i> Send Bulk Reminder</button>
      <div class="dropdown">
        <button class="btn dropdown__trigger" type="button">
          <i class="fa-solid fa-file-export"></i> Export <i class="fa-solid fa-chevron-down dropdown__caret"></i>
        </button>
        <div class="dropdown__menu">
          <a href="#"><i class="fa-solid fa-file-csv"></i> CSV</a>
          <a href="#"><i class="fa-solid fa-file-excel"></i> Excel</a>
          <a href="#"><i class="fa-solid fa-file-pdf"></i> PDF</a>
        </div>
      </div>
    </div>
  </div>

  <!-- 2. Stat strip -->
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

  <!-- 3. Filter bar -->
  <div class="card filterbar">
    <div class="filterbar__row">
      <label class="field field--search">
        <span class="field__label">Search</span>
        <span class="field__input">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="search" placeholder="Search by church name, code, contact or phone...">
        </span>
      </label>
      <label class="field"><span class="field__label">Expiry Window</span>
        <select><option>Next 7 days</option><option>Next 14 days</option><option>Next 30 days</option><option>Next 60 days</option></select></label>
      <label class="field"><span class="field__label">Plan</span>
        <select><option>All Plans</option><option>Basic</option><option>Standard</option><option>Premium</option></select></label>
      <label class="field"><span class="field__label">Province</span>
        <select><option>All Provinces</option><?php foreach ($provinces as $o): ?><option><?= htmlspecialchars($o) ?></option><?php endforeach; ?></select></label>
      <label class="field"><span class="field__label">Reminder Status</span>
        <select><option>All</option><option>Reminder Sent</option><option>Not Contacted</option></select></label>
      <label class="field"><span class="field__label">Sort by</span>
        <select><option>Soonest Expiry</option><option>Highest Value</option><option>Church Name</option></select></label>
    </div>

    <div class="filterbar__foot">
      <div class="filterbar__actions">
        <button class="btn btn--primary" type="button"><i class="fa-solid fa-filter"></i> Apply Filters</button>
        <a class="link-reset" href="#">Reset</a>
      </div>
      <label class="entries">
        Show
        <select><?php foreach ([10, 25, 50, 100] as $n): ?><option<?= $n === 25 ? ' selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select>
        entries
      </label>
    </div>
  </div>

  <!-- 4. Bulk action bar -->
  <div class="bulkbar" id="bulkBar" hidden>
    <span class="bulkbar__count"><strong id="bulkCount">0</strong> selected</span>
    <div class="bulkbar__actions">
      <button class="btn btn--sm" type="button" data-modal="modalRemind"><i class="fa-solid fa-bell"></i> Send Reminder</button>
      <button class="btn btn--sm btn--go" type="button" data-modal="modalExtend"><i class="fa-solid fa-calendar-plus"></i> Extend Subscription</button>
      <button class="btn btn--sm" type="button"><i class="fa-solid fa-file-export"></i> Export Selected</button>
    </div>
    <button class="bulkbar__clear" type="button" id="bulkClear" aria-label="Clear selection"><i class="fa-solid fa-xmark"></i></button>
  </div>

  <!-- 5. Main table -->
  <div class="card">
    <div class="table-wrap">
      <table class="table table--churches">
        <thead>
          <tr>
            <th class="col-check"><input type="checkbox" id="checkAll" aria-label="Select all"></th>
            <th class="col-num">#</th>
            <?php foreach ($columns as $c): ?>
              <th class="<?= ($c['align'] ?? '') === 'right' ? 'ta-right ' : '' ?>th-sort<?= !empty($c['sort']) ? ' is-sorted' : '' ?>">
                <button type="button" class="th-sort__btn">
                  <?= htmlspecialchars($c['label']) ?>
                  <i class="fa-solid fa-sort<?= !empty($c['sort']) ? '-' . $c['sort'] : '' ?>"></i>
                </button>
              </th>
            <?php endforeach; ?>
            <th class="col-actions">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $i => $c): ?>
            <tr>
              <td class="col-check"><input type="checkbox" class="row-check" aria-label="Select <?= htmlspecialchars($c['name']) ?>"></td>
              <td class="col-num muted"><?= $i + 1 ?></td>
              <td>
                <div class="church">
                  <span class="church__avatar"><?= htmlspecialchars($c['initials']) ?></span>
                  <span class="church__text">
                    <strong><?= htmlspecialchars($c['name']) ?></strong>
                    <small><?= htmlspecialchars($c['code']) ?></small>
                  </span>
                </div>
              </td>
              <td>
                <span class="stack">
                  <strong><?= htmlspecialchars($c['contact']) ?></strong>
                  <small><?= htmlspecialchars($c['phone']) ?></small>
                </span>
              </td>
              <td><span class="plan plan--<?= strtolower($c['plan']) ?>"><?= $c['plan'] ?></span></td>
              <td class="ta-right strong"><?= number_format($c['members']) ?></td>
              <?php $tone = urgency($c['days']); ?>
              <td class="nowrap">
                <span class="stack">
                  <strong><?= $c['expiry'] ?></strong>
                  <small class="note--<?= $tone ?>">in <?= $c['days'] ?> days</small>
                </span>
              </td>
              <td><span class="countdown countdown--<?= $tone ?>"><?= $c['days'] ?>d</span></td>
              <td class="ta-right strong">$<?= $c['value'] ?></td>
              <td class="nowrap <?= $c['reminder'] === 'Never' ? 'muted' : '' ?>"><?= $c['reminder'] ?></td>
              <td class="nowrap muted"><?= $c['login'] ?></td>
              <td class="col-actions">
                <div class="row-actions">
                  <button class="btn btn--sm btn--go" type="button" data-modal="modalExtend"><i class="fa-solid fa-calendar-plus"></i> Extend</button>
                  <button class="ico-btn" type="button" title="Send Reminder" aria-label="Send Reminder" data-modal="modalRemind"><i class="fa-solid fa-bell"></i></button>
                  <a class="ico-btn" href="#" title="Call" aria-label="Call"><i class="fa-solid fa-phone"></i></a>
                  <div class="dropdown dropdown--menu">
                    <button class="ico-btn dropdown__trigger" type="button" title="More" aria-label="More actions"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                    <div class="dropdown__menu dropdown__menu--right">
                      <?php foreach ($rowMenu as $m): ?>
                        <?php if (!empty($m['sep'])): ?><span class="dropdown__sep"></span><?php endif; ?>
                        <a href="#"<?= !empty($m['danger']) ? ' class="is-danger"' : '' ?><?= !empty($m['modal']) ? ' data-modal="' . $m['modal'] . '"' : '' ?>>
                          <i class="fa-solid <?= $m['icon'] ?>"></i> <?= htmlspecialchars($m['label']) ?>
                        </a>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- 7. Empty state -->
    <div class="empty" id="emptyState" hidden>
      <span class="empty__icon empty__icon--go"><i class="fa-solid fa-circle-check"></i></span>
      <p class="empty__title">Nothing expiring in this window</p>
      <p class="empty__text">No subscription falls due in the period you have selected. Widen the expiry window to look further ahead.</p>
    </div>

    <!-- 6. Table footer -->
    <div class="tablefoot">
      <p class="tablefoot__count">Showing 1 to <?= $rowCount ?> of <?= $rowCount ?> entries</p>
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

<!-- ==================== MODALS (static) ==================== -->
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
        <?php foreach (['1 month', '3 months', '6 months', '12 months', 'Custom'] as $i => $d): ?>
          <label class="radio"><input type="radio" name="dur"<?= $i === 3 ? ' checked' : '' ?>><span><?= $d ?></span></label>
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

<div class="modal" id="modalNote" hidden>
  <div class="modal__box modal__box--sm">
    <div class="modal__head">
      <h2><i class="fa-solid fa-note-sticky"></i> Add Note</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <p class="modal__lead">Internal note for <strong>ZCC Mbungo</strong>. Only super admins see this.</p>
      <label class="field"><span class="field__label">Note</span>
        <textarea rows="4" placeholder="What was agreed, who you spoke to, what to follow up..."></textarea></label>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--primary" type="button">Save Note</button>
    </div>
  </div>
</div>

<script>
/* Bulk-selection bar, dropdown menus, modal open/close, gated confirms. */
(function () {
  'use strict';

  var all   = document.getElementById('checkAll'),
      rows  = [].slice.call(document.querySelectorAll('.row-check')),
      bar   = document.getElementById('bulkBar'),
      count = document.getElementById('bulkCount');

  function refresh() {
    var n = rows.filter(function (c) { return c.checked; }).length;
    count.textContent = n;
    bar.hidden = n === 0;
    all.checked = n === rows.length && n > 0;
    all.indeterminate = n > 0 && n < rows.length;
  }
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

  document.addEventListener('click', function (e) {
    var trigger = e.target.closest('.dropdown__trigger');
    document.querySelectorAll('.dropdown.is-open').forEach(function (d) {
      if (!trigger || d !== trigger.parentNode) { d.classList.remove('is-open'); }
    });
    if (trigger) {
      e.preventDefault();
      trigger.parentNode.classList.toggle('is-open');
    }
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

  /* Destructive confirms stay disabled until their gate checkbox is ticked. */
  document.querySelectorAll('[data-gate]').forEach(function (box) {
    var btn = document.getElementById(box.dataset.gate);
    if (!btn) { return; }
    var sync = function () { btn.disabled = !box.checked; };
    sync();
    box.addEventListener('change', sync);
  });
})();
</script>
</body>
</html>

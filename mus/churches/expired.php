<?php
/**
 * Mutendi CMS — Expired / Suspended (static UI mockup).
 *
 * Lapsed and cut-off tenants — the recovery and outstanding-money list.
 * Every dataset is hardcoded; each block carries the query that will replace
 * it. Search, filters, sorting and pagination are visual only.
 */

/* Path constants only — the sidebar markup is required further down. */
$musPathsOnly = true;
require __DIR__ . '/../components/sidebar.php';
$musPathsOnly = false;

/* ── Lapsed and suspended tenants ──────────────────────────────────────────
   LATER:
     SELECT c.*, p.name AS plan, p.price, s.reason AS suspension_reason
       FROM churches c
       JOIN plans p ON p.id = c.plan_id
       LEFT JOIN suspensions s ON s.church_id = c.id AND s.lifted_at IS NULL
      WHERE c.status IN ('expired', 'suspended')
      ORDER BY c.expiry_date DESC;
   `lapsed` is DATEDIFF(NOW(), expiry_date); retention comes from the purge job. */
$rows = [
    ['initials'=>'GM','name'=>'Grace Ministries','code'=>'GRM-003','contact'=>'Pastor T. Chikore','phone'=>'+263 771 902 335','plan'=>'Standard','members'=>860,'status'=>'Expired','expired'=>'12 Aug 2026','lapsed'=>12,'owing'=>30,'reason'=>'','data'=>'Data retained','data_tone'=>'go'],
    ['initials'=>'SH','name'=>'SDA Hwange','code'=>'SDA-014','contact'=>'Elder J. Sibanda','phone'=>'+263 719 447 630','plan'=>'Basic','members'=>705,'status'=>'Expired','expired'=>'05 Aug 2026','lapsed'=>19,'owing'=>15,'reason'=>'','data'=>'Data retained','data_tone'=>'go'],
    ['initials'=>'GG','name'=>'Glory Ministries Gweru','code'=>'GMG-008','contact'=>'Pastor C. Dube','phone'=>'+263 717 340 776','plan'=>'Basic','members'=>620,'status'=>'Suspended','expired'=>'30 Jul 2026','lapsed'=>25,'owing'=>15,'reason'=>'Non-payment after 3 notices','data'=>'Data retained','data_tone'=>'go'],
    ['initials'=>'EM','name'=>'Ebenezer Masvingo','code'=>'EBM-044','contact'=>'Rev. K. Chirwa','phone'=>'+263 772 331 908','plan'=>'Standard','members'=>540,'status'=>'Expired','expired'=>'16 Jul 2026','lapsed'=>39,'owing'=>30,'reason'=>'','data'=>'Data retained','data_tone'=>'go'],
    ['initials'=>'LB','name'=>'Light of Bulawayo','code'=>'LOB-052','contact'=>'Pastor N. Moyo','phone'=>'+263 778 114 663','plan'=>'Premium','members'=>1420,'status'=>'Suspended','expired'=>'28 Jun 2026','lapsed'=>57,'owing'=>50,'reason'=>'Disputed billing — under review','data'=>'Data retained','data_tone'=>'go'],
    ['initials'=>'HK','name'=>'Harvest Kadoma','code'=>'HVK-037','contact'=>'Pastor B. Mutasa','phone'=>'+263 771 550 229','plan'=>'Basic','members'=>295,'status'=>'Expired','expired'=>'19 May 2026','lapsed'=>97,'owing'=>15,'reason'=>'','data'=>'Scheduled for deletion in 14 days','data_tone'=>'berry'],
    ['initials'=>'ZC','name'=>'Zion Chitungwiza','code'=>'ZNC-021','contact'=>'Elder M. Gwatidzo','phone'=>'+263 712 806 447','plan'=>'Standard','members'=>780,'status'=>'Suspended','expired'=>'02 May 2026','lapsed'=>114,'owing'=>30,'reason'=>'Repeated abuse of SMS credits','data'=>'Scheduled for deletion in 8 days','data_tone'=>'berry'],
    ['initials'=>'AB','name'=>'Apostolic Beitbridge','code'=>'APB-049','contact'=>'Pastor S. Ndlovu','phone'=>'+263 776 229 015','plan'=>'Basic','members'=>360,'status'=>'Expired','expired'=>'14 Apr 2026','lapsed'=>132,'owing'=>15,'reason'=>'','data'=>'Data retained','data_tone'=>'go'],
    ['initials'=>'MC','name'=>'Methodist Chegutu','code'=>'MTC-033','contact'=>'Rev. P. Marimo','phone'=>'+263 774 663 118','plan'=>'Standard','members'=>610,'status'=>'Suspended','expired'=>'08 Mar 2026','lapsed'=>169,'owing'=>30,'reason'=>'Church requested a pause','data'=>'Data retained','data_tone'=>'go'],
    ['initials'=>'SP','name'=>'St Pauls Kariba','code'=>'SPK-058','contact'=>'Fr. D. Nyathi','phone'=>'+263 773 447 902','plan'=>'Basic','members'=>240,'status'=>'Expired','expired'=>'21 Jan 2026','lapsed'=>215,'owing'=>15,'reason'=>'','data'=>'Data retained','data_tone'=>'go'],
];

$rowCount = count($rows);

/* ── Stat strip — derived from the list above. ────────────────────────────
   LATER: GROUP BY status, SUM(outstanding), and a DATEDIFF < 30 filter. */
$expiredCount   = count(array_filter($rows, fn($c) => $c['status'] === 'Expired'));
$suspendedCount = $rowCount - $expiredCount;
$outstanding    = array_sum(array_column($rows, 'owing'));
$recoverable    = count(array_filter($rows, fn($c) => $c['lapsed'] < 30));

$statTiles = [
    ['label' => 'Expired',             'value' => $expiredCount,   'tone' => 'berry', 'icon' => 'fa-ban',           'on' => true],
    ['label' => 'Suspended',           'value' => $suspendedCount, 'tone' => 'grey',  'icon' => 'fa-circle-pause',  'on' => false],
    ['label' => 'Outstanding Amount',  'value' => '$' . number_format($outstanding), 'tone' => 'gold', 'icon' => 'fa-receipt', 'on' => false],
    ['label' => 'Recoverable',         'value' => $recoverable,    'tone' => 'green', 'icon' => 'fa-rotate-left',   'on' => false],
];

$provinces = ['Harare', 'Bulawayo', 'Manicaland', 'Midlands', 'Masvingo',
              'Mashonaland East', 'Mashonaland West', 'Mashonaland Central',
              'Matabeleland North', 'Matabeleland South'];

$columns = [
    ['label' => 'Church',            'sort' => null],
    ['label' => 'Contact',           'sort' => null],
    ['label' => 'Plan',              'sort' => null],
    ['label' => 'Members',           'sort' => null, 'align' => 'right'],
    ['label' => 'Status',            'sort' => null],
    ['label' => 'Expired On',        'sort' => 'desc'],
    ['label' => 'Days Lapsed',       'sort' => null],
    ['label' => 'Outstanding',       'sort' => null, 'align' => 'right'],
    ['label' => 'Suspension Reason', 'sort' => null],
    ['label' => 'Data Status',       'sort' => null],
];

$rowMenu = [
    ['label' => 'View Church',      'icon' => 'fa-eye'],
    ['label' => 'Edit Church',      'icon' => 'fa-pen'],
    ['label' => 'Backup Data',      'icon' => 'fa-cloud-arrow-down'],
    ['label' => 'Export Data',      'icon' => 'fa-file-export'],
    ['label' => 'Send Notice',      'icon' => 'fa-paper-plane', 'modal' => 'modalNotice'],
    ['label' => 'Add Note',         'icon' => 'fa-note-sticky', 'modal' => 'modalNote'],
    ['label' => 'View Activity Log','icon' => 'fa-wave-square'],
    ['label' => 'Archive',          'icon' => 'fa-box-archive', 'sep' => true],
    ['label' => 'Delete',           'icon' => 'fa-trash',       'danger' => true, 'modal' => 'modalDelete'],
];

$activePage    = 'churches/expired';
$sidebarBadges = ['pending' => 10, 'expiring' => 12];
$sidebarUser   = ['name' => 'Super Admin', 'role' => 'System Owner'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Expired / Suspended — Mutendi CMS Super Admin</title>
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
      <h1>Expired / Suspended <span class="title-badge"><?= $rowCount ?></span></h1>
      <p class="crumb">
        <a href="<?= $base_url ?>index.php">Dashboard</a> <i class="fa-solid fa-chevron-right"></i>
        Churches <i class="fa-solid fa-chevron-right"></i> Expired / Suspended
      </p>
      <p class="page-hint">Churches with lapsed subscriptions or suspended access.</p>
    </div>
    <div class="head-actions">

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
      <label class="field"><span class="field__label">Status</span>
        <select><option>All</option><option>Expired</option><option>Suspended</option></select></label>
      <label class="field"><span class="field__label">Lapsed Duration</span>
        <select><option>Any duration</option><option>Under 30 days</option><option>30-90 days</option><option>Over 90 days</option></select></label>
      <label class="field"><span class="field__label">Plan</span>
        <select><option>All Plans</option><option>Basic</option><option>Standard</option><option>Premium</option></select></label>
      <label class="field"><span class="field__label">Province</span>
        <select><option>All Provinces</option><?php foreach ($provinces as $o): ?><option><?= htmlspecialchars($o) ?></option><?php endforeach; ?></select></label>
      <label class="field"><span class="field__label">Sort by</span>
        <select><option>Recently Expired</option><option>Longest Expired</option><option>Highest Value</option></select></label>
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
      <button class="btn btn--sm btn--go" type="button" data-modal="modalReactivate"><i class="fa-solid fa-rotate-left"></i> Reactivate</button>
      <button class="btn btn--sm" type="button" data-modal="modalNotice"><i class="fa-solid fa-paper-plane"></i> Send Notice</button>
      <button class="btn btn--sm" type="button"><i class="fa-solid fa-file-export"></i> Export Selected</button>
      <button class="btn btn--sm" type="button"><i class="fa-solid fa-box-archive"></i> Archive</button>
      <button class="btn btn--sm btn--danger" type="button" data-modal="modalDelete"><i class="fa-solid fa-trash"></i> Delete</button>
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
              <td><span class="pill pill--<?= strtolower($c['status']) ?>"><?= $c['status'] ?></span></td>
              <td class="nowrap">
                <span class="stack">
                  <strong><?= $c['expired'] ?></strong>
                  <small class="note--berry">expired <?= $c['lapsed'] ?> days ago</small>
                </span>
              </td>
              <td><span class="countdown countdown--berry"><?= $c['lapsed'] ?>d</span></td>
              <td class="ta-right strong">$<?= $c['owing'] ?></td>
              <td class="muted reason-cell"><?= $c['reason'] !== '' ? htmlspecialchars($c['reason']) : '&mdash;' ?></td>
              <td class="nowrap"><span class="note--<?= $c['data_tone'] ?>"><?= $c['data'] ?></span></td>
              <td class="col-actions">
                <div class="row-actions">
                  <button class="btn btn--sm btn--go" type="button" data-modal="modalReactivate"><i class="fa-solid fa-rotate-left"></i> Reactivate</button>
                  <button class="ico-btn" type="button" title="Send Notice" aria-label="Send Notice" data-modal="modalNotice"><i class="fa-solid fa-paper-plane"></i></button>
                  <a class="ico-btn" href="#" title="View" aria-label="View"><i class="fa-regular fa-eye"></i></a>
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
      <p class="empty__title">No lapsed or suspended churches</p>
      <p class="empty__text">Every church on the platform has an active subscription. Lapsed accounts will appear here automatically.</p>
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
<div class="modal" id="modalReactivate" hidden>
  <div class="modal__box">
    <div class="modal__head">
      <h2><i class="fa-solid fa-rotate-left note--go"></i> Reactivate Church</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <label class="field"><span class="field__label">Church</span>
        <input type="text" value="Grace Ministries (GRM-003)" readonly></label>
      <span class="field__label">Duration</span>
      <div class="radios">
        <?php foreach (['1 month', '3 months', '6 months', '12 months', 'Custom'] as $i => $d): ?>
          <label class="radio"><input type="radio" name="rdur"<?= $i === 3 ? ' checked' : '' ?>><span><?= $d ?></span></label>
        <?php endforeach; ?>
      </div>
      <div class="field-row">
        <label class="field"><span class="field__label">Custom expiry date</span><input type="date"></label>
        <label class="field"><span class="field__label">Amount paid</span><input type="text" placeholder="$30.00"></label>
      </div>
      <label class="field"><span class="field__label">Payment method</span>
        <select><option>Cash</option><option>EcoCash</option><option>Bank Transfer</option><option>Other</option></select></label>
      <label class="field"><span class="field__label">Reference / note</span>
        <textarea rows="2" placeholder="Receipt number, who paid, any note..."></textarea></label>
      <p class="preview"><span>New expiry date</span><strong>24 Aug 2027</strong></p>
      <label class="check-row"><input type="checkbox" checked><span>Notify church admin by email and SMS</span></label>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--go" type="button"><i class="fa-solid fa-rotate-left"></i> Reactivate Church</button>
    </div>
  </div>
</div>

<div class="modal" id="modalNotice" hidden>
  <div class="modal__box">
    <div class="modal__head">
      <h2><i class="fa-solid fa-paper-plane"></i> Send Notice</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <p class="modal__lead">Notice to <strong>Grace Ministries (GRM-003)</strong>, lapsed 12 days ago.</p>
      <span class="field__label">Channel</span>
      <div class="radios">
        <label class="radio"><input type="checkbox" checked><span>Email</span></label>
        <label class="radio"><input type="checkbox" checked><span>SMS</span></label>
      </div>
      <label class="field"><span class="field__label">Template</span>
        <select><option>Subscription lapsed — how to restore</option><option>Outstanding balance reminder</option><option>Final notice before archiving</option><option>Custom message</option></select></label>
      <label class="field"><span class="field__label">Message</span>
        <textarea rows="4">Dear Pastor Chikore, the Mutendi CMS subscription for Grace Ministries lapsed on 12 Aug 2026 and access is currently suspended. Settle $30 to restore access — your records are safe in the meantime.</textarea></label>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--primary" type="button">Send Notice</button>
    </div>
  </div>
</div>

<div class="modal" id="modalDelete" hidden>
  <div class="modal__box modal__box--sm">
    <div class="modal__head">
      <h2><i class="fa-solid fa-trash note--berry"></i> Delete Church</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <p class="modal__warn"><i class="fa-solid fa-triangle-exclamation"></i>
        This permanently removes <strong>Grace Ministries</strong> and every record it holds — members, contributions, attendance and uploaded files. This cannot be undone.</p>
      <label class="field"><span class="field__label">Type <strong>GRM-003</strong> to confirm</span>
        <input type="text" placeholder="GRM-003"></label>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--danger-solid" type="button">Delete Permanently</button>
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

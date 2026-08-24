<?php
/**
 * Mutendi CMS — Archived / Deleted (static UI mockup).
 *
 * The recycle bin and historical record — restore, or permanently purge.
 * Every dataset is hardcoded; each block carries the query that will replace
 * it. Search, filters, sorting and pagination are visual only.
 */

/* Path constants only — the sidebar markup is required further down. */
$musPathsOnly = true;
require __DIR__ . '/../components/sidebar.php';
$musPathsOnly = false;

/* ── Recycle bin and historical record ─────────────────────────────────────
   LATER:
     SELECT a.*, c.name, c.code, c.contact_name, u.name AS archived_by
       FROM archives a
       JOIN churches c ON c.id = a.church_id
       JOIN admin_users u ON u.id = a.archived_by_id
      ORDER BY a.archived_at DESC;
   `retention` comes from the scheduled purge job; `tone` flags the ones close
   to permanent deletion. */
$rows = [
    ['initials'=>'HK','name'=>'Harvest Kadoma','code'=>'HVK-037','contact'=>'Pastor B. Mutasa','members'=>295,'archived'=>'10 Jul 2026','ago'=>45,'by'=>'R. Dube','reason'=>'Non-Payment','retention'=>'Permanent deletion in 8 days','tone'=>'berry','size'=>'240 MB'],
    ['initials'=>'ZC','name'=>'Zion Chitungwiza','code'=>'ZNC-021','contact'=>'Elder M. Gwatidzo','members'=>780,'archived'=>'02 Jul 2026','ago'=>53,'by'=>'Super Admin','reason'=>'Non-Payment','retention'=>'Permanent deletion in 3 days','tone'=>'berry','size'=>'410 MB'],
    ['initials'=>'BM','name'=>'Bethesda Mberengwa','code'=>'BSM-046','contact'=>'Pastor K. Ncube','members'=>180,'archived'=>'28 Jun 2026','ago'=>57,'by'=>'R. Dube','reason'=>'Church Request','retention'=>'Retained until 28 Jun 2027','tone'=>'go','size'=>'96 MB'],
    ['initials'=>'TN','name'=>'Tabernacle Norton','code'=>'TBN-054','contact'=>'Rev. S. Marimo','members'=>520,'archived'=>'14 Jun 2026','ago'=>71,'by'=>'Super Admin','reason'=>'Inactive','retention'=>'Retained until 14 Jun 2027','tone'=>'go','size'=>'185 MB'],
    ['initials'=>'LC','name'=>'Living Church Rusape','code'=>'LCR-039','contact'=>'Pastor D. Nyathi','members'=>340,'archived'=>'30 May 2026','ago'=>86,'by'=>'T. Moyo','reason'=>'Duplicate','retention'=>'Retained until 30 May 2027','tone'=>'go','size'=>'62 MB'],
    ['initials'=>'CS','name'=>'Christ Sanctuary Gokwe','code'=>'CSG-050','contact'=>'Pastor L. Chirwa','members'=>410,'archived'=>'12 May 2026','ago'=>104,'by'=>'Super Admin','reason'=>'Non-Payment','retention'=>'Retained until 12 May 2027','tone'=>'go','size'=>'128 MB'],
    ['initials'=>'SM','name'=>'St Michaels Shurugwi','code'=>'SMS-043','contact'=>'Fr. J. Zvobgo','members'=>225,'archived'=>'25 Apr 2026','ago'=>121,'by'=>'R. Dube','reason'=>'Church Request','retention'=>'Retained until 25 Apr 2027','tone'=>'go','size'=>'74 MB'],
    ['initials'=>'EG','name'=>'Emmanuel Gwanda','code'=>'EMG-057','contact'=>'Pastor P. Sibanda','members'=>390,'archived'=>'08 Apr 2026','ago'=>138,'by'=>'T. Moyo','reason'=>'Inactive','retention'=>'Retained until 08 Apr 2027','tone'=>'go','size'=>'143 MB'],
    ['initials'=>'VC','name'=>'Victory Chapel Bindura','code'=>'VCB-048','contact'=>'Pastor A. Mangwiro','members'=>610,'archived'=>'19 Mar 2026','ago'=>158,'by'=>'Super Admin','reason'=>'Other','retention'=>'Retained until 19 Mar 2027','tone'=>'go','size'=>'221 MB'],
    ['initials'=>'FG','name'=>'Faith Gate Beitbridge','code'=>'FGB-060','contact'=>'Elder N. Moyo','members'=>150,'archived'=>'02 Mar 2026','ago'=>175,'by'=>'R. Dube','reason'=>'Duplicate','retention'=>'Retained until 02 Mar 2027','tone'=>'go','size'=>'48 MB'],
];

$rowCount = count($rows);

/* ── Stat strip — derived from the archive above. ─────────────────────────
   LATER: COUNT(*) by state and SUM(data_bytes). */
$purging     = count(array_filter($rows, fn($c) => $c['tone'] === 'berry'));
$restorable  = $rowCount - $purging;

$statTiles = [
    ['label' => 'Total Archived',            'value' => $rowCount,   'tone' => 'grey',   'icon' => 'fa-box-archive',   'on' => true],
    ['label' => 'Restorable',                'value' => $restorable, 'tone' => 'green',  'icon' => 'fa-rotate-left',   'on' => false],
    ['label' => 'Pending Permanent Deletion','value' => $purging,    'tone' => 'berry',  'icon' => 'fa-trash',         'on' => false],
    ['label' => 'Storage Recoverable',       'value' => '1.2 GB',    'tone' => 'indigo', 'icon' => 'fa-hard-drive',    'on' => false],
];

$columns = [
    ['label' => 'Church',            'sort' => null],
    ['label' => 'Church Code',       'sort' => null],
    ['label' => 'Contact Person',    'sort' => null],
    ['label' => 'Members at Archive','sort' => null, 'align' => 'right'],
    ['label' => 'Archived On',       'sort' => 'desc'],
    ['label' => 'Archived By',       'sort' => null],
    ['label' => 'Reason',            'sort' => null],
    ['label' => 'Data Retention',    'sort' => null],
    ['label' => 'Data Size',         'sort' => null, 'align' => 'right'],
];

$rowMenu = [
    ['label' => 'View Details',      'icon' => 'fa-eye',            'modal' => 'modalDetails'],
    ['label' => 'Restore Church',    'icon' => 'fa-rotate-left',    'modal' => 'modalRestore'],
    ['label' => 'Download Backup',   'icon' => 'fa-cloud-arrow-down'],
    ['label' => 'Export Data',       'icon' => 'fa-file-export'],
    ['label' => 'View Activity Log', 'icon' => 'fa-wave-square'],
    ['label' => 'Permanently Delete','icon' => 'fa-trash', 'danger' => true, 'modal' => 'modalPurge', 'sep' => true],
];

$activePage    = 'churches/archived';
$sidebarBadges = ['pending' => 10, 'expiring' => 12];
$sidebarUser   = ['name' => 'Super Admin', 'role' => 'System Owner'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Archived / Deleted — Mutendi CMS Super Admin</title>
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
      <h1>Archived / Deleted <span class="title-badge"><?= $rowCount ?></span></h1>
      <p class="crumb">
        <a href="<?= $base_url ?>index.php">Dashboard</a> <i class="fa-solid fa-chevron-right"></i>
        Churches <i class="fa-solid fa-chevron-right"></i> Archived / Deleted
      </p>
      <p class="page-hint">Archived churches and records pending permanent deletion.</p>
    </div>
    <div class="head-actions">
      <button class="btn btn--outline-danger" type="button" data-modal="modalEmpty"><i class="fa-solid fa-trash"></i> Empty Archive</button>
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
          <input type="search" placeholder="Search by church name, code or contact...">
        </span>
      </label>
      <label class="field"><span class="field__label">Type</span>
        <select><option>All</option><option>Archived</option><option>Deleted</option><option>Cancelled</option></select></label>
      <label class="field"><span class="field__label">Reason</span>
        <select><option>All Reasons</option><option>Non-Payment</option><option>Church Request</option><option>Duplicate</option><option>Inactive</option><option>Other</option></select></label>
      <label class="field"><span class="field__label">Archived From</span><input type="date"></label>
      <label class="field"><span class="field__label">To</span><input type="date"></label>
      <label class="field"><span class="field__label">Sort by</span>
        <select><option>Recently Archived</option><option>Oldest</option><option>Church Name</option></select></label>
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
      <button class="btn btn--sm btn--go" type="button" data-modal="modalRestore"><i class="fa-solid fa-rotate-left"></i> Restore</button>
      <button class="btn btn--sm" type="button"><i class="fa-solid fa-file-export"></i> Export Data</button>
      <button class="btn btn--sm btn--danger" type="button" data-modal="modalPurge"><i class="fa-solid fa-trash"></i> Permanently Delete</button>
    </div>
    <button class="bulkbar__clear" type="button" id="bulkClear" aria-label="Clear selection"><i class="fa-solid fa-xmark"></i></button>
  </div>

  <!-- 5. Main table -->
  <div class="card">
    <div class="table-wrap">
      <table class="table table--churches table--muted">
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
                  <span class="church__text"><strong><?= htmlspecialchars($c['name']) ?></strong></span>
                </div>
              </td>
              <td class="muted nowrap"><?= $c['code'] ?></td>
              <td class="nowrap"><?= htmlspecialchars($c['contact']) ?></td>
              <td class="ta-right strong"><?= number_format($c['members']) ?></td>
              <td class="nowrap">
                <span class="stack">
                  <strong><?= $c['archived'] ?></strong>
                  <small><?= $c['ago'] ?> days ago</small>
                </span>
              </td>
              <td class="nowrap muted"><?= htmlspecialchars($c['by']) ?></td>
              <td><span class="tag-reason"><?= htmlspecialchars($c['reason']) ?></span></td>
              <td class="nowrap"><span class="note--<?= $c['tone'] ?>"><?= $c['retention'] ?></span></td>
              <td class="ta-right muted nowrap"><?= $c['size'] ?></td>
              <td class="col-actions">
                <div class="row-actions">
                  <a class="ico-btn" href="#" title="View" aria-label="View" data-modal="modalDetails"><i class="fa-regular fa-eye"></i></a>
                  <button class="ico-btn" type="button" title="Restore" aria-label="Restore" data-modal="modalRestore"><i class="fa-solid fa-rotate-left"></i></button>
                  <a class="ico-btn" href="#" title="Download Data" aria-label="Download Data"><i class="fa-solid fa-cloud-arrow-down"></i></a>
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
      <span class="empty__icon "><i class="fa-solid fa-box-open"></i></span>
      <p class="empty__title">The archive is empty</p>
      <p class="empty__text">Nothing has been archived or deleted. Archived churches will appear here with their retention dates.</p>
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
<div class="modal" id="modalRestore" hidden>
  <div class="modal__box modal__box--sm">
    <div class="modal__head">
      <h2><i class="fa-solid fa-rotate-left note--go"></i> Restore Church</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <p class="modal__lead">Restoring <strong>Harvest Kadoma (HVK-037)</strong> from the archive.</p>
      <p class="notebox">The church returns with the status <strong>Suspended</strong> and will need reactivating before its admins can sign in again.</p>
      <dl class="summary">
        <div><dt>Restore point</dt><dd>10 Jul 2026, 02:15</dd></div>
        <div><dt>Records included</dt><dd>295 members, 240 MB</dd></div>
      </dl>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--go" type="button"><i class="fa-solid fa-rotate-left"></i> Restore Church</button>
    </div>
  </div>
</div>

<div class="modal" id="modalPurge" hidden>
  <div class="modal__box modal__box--sm">
    <div class="modal__head">
      <h2><i class="fa-solid fa-trash note--berry"></i> Permanently Delete</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <p class="modal__warn"><i class="fa-solid fa-triangle-exclamation"></i>
        <strong>Harvest Kadoma (HVK-037)</strong> and everything it holds will be destroyed immediately.</p>
      <ul class="destroy-list">
        <li><i class="fa-solid fa-xmark"></i> 295 member records</li>
        <li><i class="fa-solid fa-xmark"></i> All contribution history</li>
        <li><i class="fa-solid fa-xmark"></i> All attendance records</li>
        <li><i class="fa-solid fa-xmark"></i> 240 MB of uploaded files and backups</li>
      </ul>
      <label class="field"><span class="field__label">Type <strong>HVK-037</strong> to confirm</span>
        <input type="text" placeholder="HVK-037"></label>
      <label class="check-row"><input type="checkbox" data-gate="purgeBtn">
        <span>I understand this cannot be undone</span></label>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--danger-solid" type="button" id="purgeBtn" disabled>Delete Permanently</button>
    </div>
  </div>
</div>

<div class="modal" id="modalDetails" hidden>
  <div class="modal__box">
    <div class="modal__head">
      <h2><i class="fa-regular fa-eye"></i> Archived Record</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <section class="msec">
        <h3 class="msec__title">Church</h3>
        <dl class="summary">
          <div><dt>Church name</dt><dd>Harvest Kadoma</dd></div>
          <div><dt>Church code</dt><dd>HVK-037</dd></div>
          <div><dt>Contact person</dt><dd>Pastor B. Mutasa</dd></div>
          <div><dt>Members at archive</dt><dd>295</dd></div>
        </dl>
      </section>
      <section class="msec">
        <h3 class="msec__title">Archive</h3>
        <dl class="summary">
          <div><dt>Archived on</dt><dd>10 Jul 2026</dd></div>
          <div><dt>Archived by</dt><dd>R. Dube</dd></div>
          <div><dt>Reason</dt><dd><span class="tag-reason">Non-Payment</span></dd></div>
          <div><dt>Data size</dt><dd>240 MB</dd></div>
          <div><dt>Retention</dt><dd><span class="note--berry">Permanent deletion in 8 days</span></dd></div>
        </dl>
      </section>
      <section class="msec">
        <h3 class="msec__title">Internal Notes</h3>
        <p class="notebox">Three renewal notices sent with no response. Pastor confirmed by phone on 08 Jul that they were closing the branch.</p>
      </section>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-modal="modalPurge">Permanently Delete</button>
      <button class="btn btn--go" type="button" data-modal="modalRestore"><i class="fa-solid fa-rotate-left"></i> Restore</button>
    </div>
  </div>
</div>

<div class="modal" id="modalEmpty" hidden>
  <div class="modal__box modal__box--sm">
    <div class="modal__head">
      <h2><i class="fa-solid fa-trash note--berry"></i> Empty Archive</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <p class="modal__warn"><i class="fa-solid fa-triangle-exclamation"></i>
        This destroys <strong>every archived church</strong> at once. There is no recovery.</p>
      <dl class="summary">
        <div><dt>Records destroyed</dt><dd><?= $rowCount ?> churches</dd></div>
        <div><dt>Member records</dt><dd><?= number_format(array_sum(array_column($rows, 'members'))) ?></dd></div>
        <div><dt>Storage freed</dt><dd>1.2 GB</dd></div>
      </dl>
      <label class="field"><span class="field__label">Type <strong>EMPTY ARCHIVE</strong> to confirm</span>
        <input type="text" placeholder="EMPTY ARCHIVE"></label>
      <label class="check-row"><input type="checkbox" data-gate="emptyBtn">
        <span>I understand this cannot be undone</span></label>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--danger-solid" type="button" id="emptyBtn" disabled>Empty Archive</button>
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

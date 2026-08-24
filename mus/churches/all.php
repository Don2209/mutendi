<?php
/**
 * Mutendi CMS — All Churches (static UI mockup).
 *
 * The master tenant list. Every dataset below is hardcoded; each block carries
 * the query that will replace it once the schema exists. Search, filters,
 * sorting and pagination are visual only.
 */

/* Path constants only — the sidebar markup is required further down. */
$musPathsOnly = true;
require __DIR__ . '/../components/sidebar.php';
$musPathsOnly = false;

/* Sidebar inputs. */
$activePage    = 'churches/all';
$sidebarBadges = ['pending' => 3, 'expiring' => 5];
$sidebarUser   = ['name' => 'Super Admin', 'role' => 'System Owner'];

/* ── Stat strip / quick filters ────────────────────────────────────────────
   LATER: SELECT status, COUNT(*) FROM churches GROUP BY status; */
$statTiles = [
    ['label' => 'All Churches',       'value' => 47, 'tone' => 'brand', 'icon' => 'fa-church',              'on' => true],
    ['label' => 'Active',             'value' => 38, 'tone' => 'green', 'icon' => 'fa-circle-check',        'on' => false],
    ['label' => 'Expiring Soon',      'value' => 5,  'tone' => 'gold',  'icon' => 'fa-triangle-exclamation','on' => false],
    ['label' => 'Expired / Suspended','value' => 4,  'tone' => 'berry', 'icon' => 'fa-ban',                 'on' => false],
];

/* ── Filter bar options ────────────────────────────────────────────────────
   LATER: statuses and plans come from lookup tables; provinces from `provinces`. */
$statusOptions   = ['All Statuses', 'Active', 'Trial', 'Expiring Soon', 'Expired', 'Suspended', 'Pending Activation'];
$planOptions     = ['All Plans', 'Basic', 'Standard', 'Premium'];
$provinceOptions = ['All Provinces', 'Harare', 'Bulawayo', 'Manicaland', 'Midlands', 'Masvingo',
                    'Mashonaland East', 'Mashonaland West', 'Mashonaland Central',
                    'Matabeleland North', 'Matabeleland South'];
$entryOptions    = [10, 25, 50, 100];
$provinces       = array_slice($provinceOptions, 1);

/* ── Sortable column headings ──────────────────────────────────────────────
   LATER: the active heading drives ORDER BY on the query below. */
$columns = [
    ['label' => 'Church',     'sort' => 'asc'],
    ['label' => 'Contact',    'sort' => null],
    ['label' => 'Location',   'sort' => null],
    ['label' => 'Plan',       'sort' => null],
    ['label' => 'Members',    'sort' => null,  'align' => 'right'],
    ['label' => 'Status',     'sort' => null],
    ['label' => 'Expiry',     'sort' => null],
    ['label' => 'Last Login', 'sort' => null],
    ['label' => 'Registered', 'sort' => null],
];

/* ── The tenant list ───────────────────────────────────────────────────────
   LATER:
     SELECT c.*, p.name AS plan, l.city, l.province
       FROM churches c
       JOIN plans p ON p.id = c.plan_id
       JOIN locations l ON l.id = c.location_id
      WHERE (:search IS NULL OR c.name LIKE :search OR c.code LIKE :search
             OR c.contact_name LIKE :search OR c.phone LIKE :search)
        AND (:status IS NULL OR c.status = :status)
        AND (:plan IS NULL OR c.plan_id = :plan)
        AND (:province IS NULL OR l.province = :province)
        AND (:from IS NULL OR c.created_at >= :from)
        AND (:to IS NULL OR c.created_at <= :to)
      ORDER BY :sort
      LIMIT :per_page OFFSET :offset;

   `expiry_note` / `expiry_tone` and `login_stale` are derived in PHP here;
   later they come from DATEDIFF() on expiry_date and last_login_at. */
$churches = [
    ['initials'=>'ZM','name'=>'ZCC Mbungo','code'=>'ZCC-001','contact'=>'Bishop N. Mutendi','phone'=>'+263 772 145 880','city'=>'Masvingo','province'=>'Masvingo','plan'=>'Premium','members'=>1240,'status'=>'Expiring','expiry'=>'28 Aug 2026','expiry_note'=>'in 4 days','expiry_tone'=>'gold','login'=>'2 hours ago','login_stale'=>false,'registered'=>'12 Aug 2024'],
    ['initials'=>'AW','name'=>'AFM Waterfalls','code'=>'AFM-002','contact'=>'Rev. S. Banda','phone'=>'+263 778 411 207','city'=>'Harare','province'=>'Harare','plan'=>'Premium','members'=>2105,'status'=>'Active','expiry'=>'14 Mar 2027','expiry_note'=>'in 7 months','expiry_tone'=>'','login'=>'1 hour ago','login_stale'=>false,'registered'=>'07 Mar 2024'],
    ['initials'=>'GM','name'=>'Grace Ministries','code'=>'GRM-003','contact'=>'Pastor T. Chikore','phone'=>'+263 771 902 335','city'=>'Chitungwiza','province'=>'Harare','plan'=>'Standard','members'=>860,'status'=>'Expired','expiry'=>'12 Aug 2026','expiry_note'=>'expired 12 days ago','expiry_tone'=>'berry','login'=>'34 days ago','login_stale'=>true,'registered'=>'09 Jan 2024'],
    ['initials'=>'JM','name'=>'Johane Masowe eChishanu','code'=>'JME-004','contact'=>'Elder M. Zvobgo','phone'=>'+263 712 660 194','city'=>'Gweru','province'=>'Midlands','plan'=>'Premium','members'=>3420,'status'=>'Active','expiry'=>'02 Feb 2027','expiry_note'=>'in 5 months','expiry_tone'=>'','login'=>'5 hours ago','login_stale'=>false,'registered'=>'05 Feb 2024'],
    ['initials'=>'CC','name'=>'Celebration Church Harare','code'=>'CCH-005','contact'=>'Pastor L. Mhaka','phone'=>'+263 773 508 621','city'=>'Harare','province'=>'Harare','plan'=>'Premium','members'=>1780,'status'=>'Active','expiry'=>'19 Jan 2027','expiry_note'=>'in 5 months','expiry_tone'=>'','login'=>'Yesterday','login_stale'=>false,'registered'=>'03 Aug 2023'],
    ['initials'=>'FG','name'=>'Family of God Bulawayo','code'=>'FOG-006','contact'=>'Rev. P. Ndlovu','phone'=>'+263 776 233 018','city'=>'Bulawayo','province'=>'Bulawayo','plan'=>'Basic','members'=>940,'status'=>'Trial','expiry'=>'05 Sep 2026','expiry_note'=>'in 12 days','expiry_tone'=>'gold','login'=>'3 days ago','login_stale'=>false,'registered'=>'01 Aug 2026'],
    ['initials'=>'MM','name'=>'Methodist Mutare Circuit','code'=>'MMC-007','contact'=>'Rev. G. Nyathi','phone'=>'+263 774 887 552','city'=>'Mutare','province'=>'Manicaland','plan'=>'Standard','members'=>1315,'status'=>'Active','expiry'=>'22 Dec 2026','expiry_note'=>'in 4 months','expiry_tone'=>'','login'=>'6 hours ago','login_stale'=>false,'registered'=>'29 Jul 2023'],
    ['initials'=>'GG','name'=>'Glory Ministries Gweru','code'=>'GMG-008','contact'=>'Pastor C. Dube','phone'=>'+263 717 340 776','city'=>'Gweru','province'=>'Midlands','plan'=>'Basic','members'=>620,'status'=>'Suspended','expiry'=>'30 Jul 2026','expiry_note'=>'expired 25 days ago','expiry_tone'=>'berry','login'=>'62 days ago','login_stale'=>true,'registered'=>'26 Jul 2024'],
    ['initials'=>'AD','name'=>'Anglican Diocese Masvingo','code'=>'ADM-009','contact'=>'Fr. E. Marange','phone'=>'+263 779 115 442','city'=>'Masvingo','province'=>'Masvingo','plan'=>'Premium','members'=>2480,'status'=>'Active','expiry'=>'11 Nov 2026','expiry_note'=>'in 3 months','expiry_tone'=>'','login'=>'4 hours ago','login_stale'=>false,'registered'=>'24 Nov 2022'],
    ['initials'=>'FW','name'=>'Faith World Ministries','code'=>'FWM-010','contact'=>'Pastor R. Sibanda','phone'=>'+263 771 662 903','city'=>'Kwekwe','province'=>'Midlands','plan'=>'Standard','members'=>1050,'status'=>'Expiring','expiry'=>'15 Sep 2026','expiry_note'=>'in 22 days','expiry_tone'=>'gold','login'=>'8 days ago','login_stale'=>false,'registered'=>'21 Jul 2024'],
    ['initials'=>'CE','name'=>'Christ Embassy Chitungwiza','code'=>'CEC-011','contact'=>'Pastor B. Moyo','phone'=>'+263 715 908 224','city'=>'Chitungwiza','province'=>'Harare','plan'=>'Basic','members'=>480,'status'=>'Pending Activation','expiry'=>'—','expiry_note'=>'awaiting payment','expiry_tone'=>'gold','login'=>'Never','login_stale'=>true,'registered'=>'22 Aug 2026'],
    ['initials'=>'ZA','name'=>'Zion Apostolic Marondera','code'=>'ZAM-012','contact'=>'Bishop K. Chigumba','phone'=>'+263 772 004 118','city'=>'Marondera','province'=>'Mashonaland East','plan'=>'Standard','members'=>1120,'status'=>'Active','expiry'=>'08 Jun 2027','expiry_note'=>'in 10 months','expiry_tone'=>'','login'=>'Yesterday','login_stale'=>false,'registered'=>'14 Apr 2024'],
    ['initials'=>'UF','name'=>'UFIC Chinhoyi','code'=>'UFI-013','contact'=>'Pastor D. Kanyemba','phone'=>'+263 778 552 907','city'=>'Chinhoyi','province'=>'Mashonaland West','plan'=>'Standard','members'=>1640,'status'=>'Active','expiry'=>'30 Apr 2027','expiry_note'=>'in 8 months','expiry_tone'=>'','login'=>'12 hours ago','login_stale'=>false,'registered'=>'30 Apr 2023'],
    ['initials'=>'SH','name'=>'Seventh Day Adventist Hwange','code'=>'SDA-014','contact'=>'Elder J. Sibanda','phone'=>'+263 719 447 630','city'=>'Hwange','province'=>'Matabeleland North','plan'=>'Basic','members'=>705,'status'=>'Expired','expiry'=>'05 Aug 2026','expiry_note'=>'expired 19 days ago','expiry_tone'=>'berry','login'=>'71 days ago','login_stale'=>true,'registered'=>'05 Aug 2023'],
    ['initials'=>'AB','name'=>'Apostolic Faith Mission Bindura','code'=>'AFB-015','contact'=>'Rev. T. Mangwiro','phone'=>'+263 773 221 985','city'=>'Bindura','province'=>'Mashonaland Central','plan'=>'Standard','members'=>890,'status'=>'Active','expiry'=>'17 Oct 2026','expiry_note'=>'in 2 months','expiry_tone'=>'','login'=>'2 days ago','login_stale'=>false,'registered'=>'17 Oct 2023'],
];

/* ── Row overflow menu ─────────────────────────────────────────────────────
   LATER: entries gate on the signed-in admin's permissions. */
$rowMenu = [
    ['label' => 'Edit Church',          'icon' => 'fa-pen'],
    ['label' => 'Manage Modules',       'icon' => 'fa-cubes'],
    ['label' => 'Manage Admins',        'icon' => 'fa-user-tie'],
    ['label' => 'Reset Admin Password', 'icon' => 'fa-key'],
    ['label' => 'Send Notification',    'icon' => 'fa-paper-plane'],
    ['label' => 'Backup Data',          'icon' => 'fa-cloud-arrow-down'],
    ['label' => 'Export Data',          'icon' => 'fa-file-export'],
    ['label' => 'View Activity Log',    'icon' => 'fa-wave-square'],
    ['label' => 'Suspend Church',       'icon' => 'fa-ban',          'sep' => true],
    ['label' => 'Archive',              'icon' => 'fa-box-archive'],
    ['label' => 'Delete',               'icon' => 'fa-trash',        'danger' => true],
];

/* ── Pagination ────────────────────────────────────────────────────────────
   LATER: derive from COUNT(*) over the filtered set. */
$pager = ['from' => 1, 'to' => 15, 'total' => 47, 'pages' => 4, 'current' => 1];

/** Map a status label to its pill modifier. */
function pill_class(string $status): string {
    return 'pill--' . strtolower(str_replace(' ', '-', $status));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>All Churches — Mutendi CMS Super Admin</title>
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
      <h1>All Churches</h1>
      <p class="crumb">
        <a href="<?= $base_url ?>index.php">Dashboard</a> <i class="fa-solid fa-chevron-right"></i>
        Churches <i class="fa-solid fa-chevron-right"></i> All Churches
      </p>
    </div>
    <div class="head-actions">
      <a class="btn btn--primary" href="#" data-modal="modalAdd"><i class="fa-solid fa-plus"></i> Add New Church</a>

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

      <button class="ico-btn ico-btn--framed" type="button" title="Print" aria-label="Print"><i class="fa-solid fa-print"></i></button>
    </div>
  </div>

  <!-- 2. Stat strip — quick filters -->
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

      <label class="field">
        <span class="field__label">Status</span>
        <select><?php foreach ($statusOptions as $o): ?><option><?= htmlspecialchars($o) ?></option><?php endforeach; ?></select>
      </label>

      <label class="field">
        <span class="field__label">Plan</span>
        <select><?php foreach ($planOptions as $o): ?><option><?= htmlspecialchars($o) ?></option><?php endforeach; ?></select>
      </label>

      <label class="field">
        <span class="field__label">Province</span>
        <select><?php foreach ($provinceOptions as $o): ?><option><?= htmlspecialchars($o) ?></option><?php endforeach; ?></select>
      </label>

      <label class="field">
        <span class="field__label">Registered From</span>
        <input type="date">
      </label>

      <label class="field">
        <span class="field__label">To</span>
        <input type="date">
      </label>
    </div>

    <div class="filterbar__foot">
      <div class="filterbar__actions">
        <button class="btn btn--primary" type="button"><i class="fa-solid fa-filter"></i> Apply Filters</button>
        <a class="link-reset" href="#">Reset</a>
      </div>
      <label class="entries">
        Show
        <select><?php foreach ($entryOptions as $n): ?><option<?= $n === 25 ? ' selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select>
        entries
      </label>
    </div>
  </div>

  <!-- 4. Bulk action bar — revealed by the checkboxes below -->
  <div class="bulkbar" id="bulkBar" hidden>
    <span class="bulkbar__count"><strong id="bulkCount">0</strong> selected</span>
    <div class="bulkbar__actions">
      <button class="btn btn--sm" type="button" data-modal="modalExtend"><i class="fa-solid fa-calendar-plus"></i> Extend Subscription</button>
      <button class="btn btn--sm" type="button" data-modal="modalSuspend"><i class="fa-solid fa-ban"></i> Suspend</button>
      <button class="btn btn--sm" type="button"><i class="fa-solid fa-circle-check"></i> Activate</button>
      <button class="btn btn--sm" type="button"><i class="fa-solid fa-paper-plane"></i> Send Notification</button>
      <button class="btn btn--sm" type="button"><i class="fa-solid fa-file-export"></i> Export Selected</button>
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
            <th class="col-check"><input type="checkbox" id="checkAll" aria-label="Select all churches"></th>
            <th class="col-num">#</th>
            <?php foreach ($columns as $c): ?>
              <th class="<?= ($c['align'] ?? '') === 'right' ? 'ta-right ' : '' ?>th-sort<?= $c['sort'] ? ' is-sorted' : '' ?>">
                <button type="button" class="th-sort__btn">
                  <?= htmlspecialchars($c['label']) ?>
                  <i class="fa-solid fa-sort<?= $c['sort'] ? '-' . $c['sort'] : '' ?>"></i>
                </button>
              </th>
            <?php endforeach; ?>
            <th class="col-actions">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($churches as $i => $c): ?>
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

              <td class="nowrap">
                <span class="stack">
                  <strong><?= htmlspecialchars($c['city']) ?></strong>
                  <small><?= htmlspecialchars($c['province']) ?></small>
                </span>
              </td>

              <td><span class="plan plan--<?= strtolower($c['plan']) ?>"><?= $c['plan'] ?></span></td>
              <td class="ta-right strong"><?= number_format($c['members']) ?></td>
              <td><span class="pill <?= pill_class($c['status']) ?>"><?= htmlspecialchars($c['status']) ?></span></td>

              <td class="nowrap">
                <span class="stack">
                  <strong><?= htmlspecialchars($c['expiry']) ?></strong>
                  <small class="<?= $c['expiry_tone'] ? 'note--' . $c['expiry_tone'] : '' ?>"><?= htmlspecialchars($c['expiry_note']) ?></small>
                </span>
              </td>

              <td class="nowrap <?= $c['login_stale'] ? 'muted' : '' ?>"><?= htmlspecialchars($c['login']) ?></td>
              <td class="nowrap muted"><?= htmlspecialchars($c['registered']) ?></td>

              <td class="col-actions">
                <div class="row-actions">
                  <a class="ico-btn" href="#" title="View" aria-label="View"><i class="fa-regular fa-eye"></i></a>
                  <a class="ico-btn" href="#" title="Login As" aria-label="Login As"><i class="fa-solid fa-right-to-bracket"></i></a>
                  <button class="ico-btn" type="button" title="Extend" aria-label="Extend" data-modal="modalExtend"><i class="fa-solid fa-calendar-plus"></i></button>

                  <div class="dropdown dropdown--menu">
                    <button class="ico-btn dropdown__trigger" type="button" title="More" aria-label="More actions"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                    <div class="dropdown__menu dropdown__menu--right">
                      <?php foreach ($rowMenu as $m): ?>
                        <?php if (!empty($m['sep'])): ?><span class="dropdown__sep"></span><?php endif; ?>
                        <a href="#"<?= !empty($m['danger']) ? ' class="is-danger" data-modal="modalDelete"' : '' ?>>
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

    <!-- 7. Empty state — shown when filters return nothing -->
    <div class="empty" id="emptyState" hidden>
      <span class="empty__icon"><i class="fa-solid fa-church"></i></span>
      <p class="empty__title">No churches found</p>
      <p class="empty__text">No church matches the filters you have applied. Try widening your search.</p>
      <button class="btn btn--primary" type="button">Reset Filters</button>
    </div>

    <!-- 6. Table footer -->
    <div class="tablefoot">
      <p class="tablefoot__count">Showing <?= $pager['from'] ?> to <?= $pager['to'] ?> of <?= $pager['total'] ?> entries</p>
      <nav class="pagination" aria-label="Pagination">
        <a class="pagination__btn is-disabled" href="#">Previous</a>
        <?php for ($n = 1; $n <= $pager['pages']; $n++): ?>
          <a class="pagination__btn<?= $n === $pager['current'] ? ' is-on' : '' ?>" href="#"><?= $n ?></a>
        <?php endfor; ?>
        <a class="pagination__btn" href="#">Next</a>
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
          <label class="radio"><input type="radio" name="duration"<?= $i === 3 ? ' checked' : '' ?>><span><?= $d ?></span></label>
        <?php endforeach; ?>
      </div>

      <div class="field-row">
        <label class="field"><span class="field__label">Custom expiry date</span><input type="date"></label>
        <label class="field"><span class="field__label">Amount paid</span><input type="text" placeholder="$50.00"></label>
      </div>

      <label class="field"><span class="field__label">Payment method</span>
        <select><option>Cash</option><option>EcoCash</option><option>Bank Transfer</option><option>Other</option></select></label>

      <label class="field"><span class="field__label">Reference / note</span>
        <textarea rows="3" placeholder="Receipt number, who paid, any note..."></textarea></label>

      <p class="preview"><span>New expiry date</span><strong>28 Aug 2027</strong></p>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--primary" type="button">Confirm Extension</button>
    </div>
  </div>
</div>

<div class="modal" id="modalSuspend" hidden>
  <div class="modal__box modal__box--sm">
    <div class="modal__head">
      <h2><i class="fa-solid fa-triangle-exclamation note--gold"></i> Suspend Church</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <p class="modal__lead">You are about to suspend <strong>ZCC Mbungo (ZCC-001)</strong>. Their admins will lose access until the church is reactivated.</p>
      <label class="field"><span class="field__label">Reason for suspension</span>
        <textarea rows="3" placeholder="Non-payment, policy breach, requested by church..."></textarea></label>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--primary" type="button">Suspend Church</button>
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
        This permanently removes the church, its members and every record it holds. This cannot be undone.</p>
      <label class="field"><span class="field__label">Type <strong>ZCC-001</strong> to confirm</span>
        <input type="text" placeholder="ZCC-001"></label>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--danger" type="button">Delete Permanently</button>
    </div>
  </div>
</div>

<script>
/* Bulk-selection bar, dropdown menus and modal open/close. No form handling. */
(function () {
  'use strict';

  /* --- Bulk selection ------------------------------------------------- */
  var all    = document.getElementById('checkAll'),
      rows   = [].slice.call(document.querySelectorAll('.row-check')),
      bar    = document.getElementById('bulkBar'),
      count  = document.getElementById('bulkCount');

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

  /* --- Dropdowns ------------------------------------------------------ */
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

  /* --- Modals --------------------------------------------------------- */
  function close(m) { m.hidden = true; document.body.classList.remove('modal-open'); }

  document.addEventListener('click', function (e) {
    var open = e.target.closest('[data-modal]');
    if (open) {
      e.preventDefault();
      var m = document.getElementById(open.dataset.modal);
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

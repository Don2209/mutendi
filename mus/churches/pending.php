<?php
/**
 * Mutendi CMS — Pending Activation (static UI mockup).
 *
 * The approval queue: churches that have registered or been captured but are
 * not yet live. Every dataset is hardcoded; each block carries the query that
 * will replace it. Search, filters and pagination are visual only.
 */

/* Path constants only — the sidebar markup is required further down. */
$musPathsOnly = true;
require __DIR__ . '/../components/sidebar.php';
$musPathsOnly = false;

/* ── The approval queue ────────────────────────────────────────────────────
   LATER:
     SELECT r.*, d.name AS denomination
       FROM church_requests r
       LEFT JOIN denominations d ON d.id = r.denomination_id
      WHERE r.status = 'pending'
        AND (:search IS NULL OR r.name LIKE :search OR r.contact_name LIKE :search
             OR r.email LIKE :search OR r.phone LIKE :search)
        AND (:source IS NULL OR r.source = :source)
        AND (:province IS NULL OR r.province = :province)
        AND (:from IS NULL OR r.submitted_at >= :from)
        AND (:to IS NULL OR r.submitted_at <= :to)
      ORDER BY :sort;

   `waited` is the day count since submission; `wait_label` and the amber/red
   tone are derived from it below, and later come from DATEDIFF(). */
$pending = [
    ['initials'=>'RC','name'=>'Roman Catholic Parish Bindura','denom'=>'Catholic','contact'=>'Fr. P. Chidziva','email'=>'rcbindura@gmail.com','phone'=>'+263 772 806 155','city'=>'Bindura','province'=>'Mashonaland Central','plan'=>'Premium','members'=>2240,'source'=>'Self-Registered','submitted'=>'23 Aug 2026','waited'=>1],
    ['initials'=>'SB','name'=>'Seventh Day Adventist Beitbridge','denom'=>'Adventist','contact'=>'Elder K. Ndlovu','email'=>'sda.beitbridge@gmail.com','phone'=>'+263 771 447 209','city'=>'Beitbridge','province'=>'Matabeleland South','plan'=>'Basic','members'=>380,'source'=>'Added by Admin','submitted'=>'24 Aug 2026','waited'=>0],
    ['initials'=>'GR','name'=>'Grace Revival Church','denom'=>'Pentecostal','contact'=>'Pastor T. Mabhena','email'=>'grace.revival@gmail.com','phone'=>'+263 772 445 190','city'=>'Chitungwiza','province'=>'Harare','plan'=>'Standard','members'=>640,'source'=>'Self-Registered','submitted'=>'22 Aug 2026','waited'=>2],
    ['initials'=>'ZB','name'=>'Zion Christian Church Bikita','denom'=>'Zionist','contact'=>'Elder S. Chirwa','email'=>'zcc.bikita@outlook.com','phone'=>'+263 771 338 072','city'=>'Bikita','province'=>'Masvingo','plan'=>'Premium','members'=>1850,'source'=>'Self-Registered','submitted'=>'21 Aug 2026','waited'=>3],
    ['initials'=>'HC','name'=>'Holy Cross Anglican Kadoma','denom'=>'Anglican','contact'=>'Fr. M. Nyoni','email'=>'holycross.kdm@gmail.com','phone'=>'+263 778 210 664','city'=>'Kadoma','province'=>'Mashonaland West','plan'=>'Basic','members'=>410,'source'=>'Added by Admin','submitted'=>'19 Aug 2026','waited'=>5],
    ['initials'=>'LW','name'=>'Living Waters Chapel','denom'=>'Non-Denominational','contact'=>'Pastor R. Gwatidzo','email'=>'info@livingwaters.co.zw','phone'=>'+263 712 907 553','city'=>'Bulawayo','province'=>'Bulawayo','plan'=>'Standard','members'=>780,'source'=>'Self-Registered','submitted'=>'17 Aug 2026','waited'=>7],
    ['initials'=>'MR','name'=>'Methodist Church Rusape','denom'=>'Methodist','contact'=>'Rev. C. Marimo','email'=>'rusape.circuit@gmail.com','phone'=>'+263 773 664 281','city'=>'Rusape','province'=>'Manicaland','plan'=>'Not specified','members'=>520,'source'=>'Added by Admin','submitted'=>'15 Aug 2026','waited'=>9],
    ['initials'=>'AG','name'=>'Apostolic Faith Mission Gokwe','denom'=>'Apostolic','contact'=>'Pastor D. Sibanda','email'=>'afm.gokwe@gmail.com','phone'=>'+263 776 118 340','city'=>'Gokwe','province'=>'Midlands','plan'=>'Basic','members'=>295,'source'=>'Self-Registered','submitted'=>'14 Aug 2026','waited'=>10],
    ['initials'=>'SA','name'=>'St Andrews Presbyterian Gweru','denom'=>'Presbyterian','contact'=>'Rev. J. Moyo','email'=>'standrews.gweru@gmail.com','phone'=>'+263 774 552 908','city'=>'Gweru','province'=>'Midlands','plan'=>'Premium','members'=>1120,'source'=>'Added by Admin','submitted'=>'10 Aug 2026','waited'=>14],
    ['initials'=>'FT','name'=>'Faith Tabernacle Chinhoyi','denom'=>'Pentecostal','contact'=>'Pastor L. Mutasa','email'=>'faithtab.chy@outlook.com','phone'=>'+263 719 330 476','city'=>'Chinhoyi','province'=>'Mashonaland West','plan'=>'Standard','members'=>660,'source'=>'Self-Registered','submitted'=>'08 Aug 2026','waited'=>16],
];

/** How long a request has waited, and how loudly to say so. */
function wait_label(int $days): string {
    if ($days === 0) { return 'Today'; }
    if ($days === 1) { return 'Yesterday'; }
    return $days . ' days ago';
}
function wait_tone(int $days): string {
    if ($days >= 14) { return 'berry'; }   // 14+ days — overdue
    if ($days >= 7)  { return 'gold'; }    // 7+ days  — needs attention
    return '';
}

/* ── Stat strip ────────────────────────────────────────────────────────────
   Derived from the queue above so the tiles can never drift from the table.
   LATER: SELECT source, COUNT(*) ... GROUP BY source; and a DATEDIFF filter. */
$totalPending = count($pending);
$selfCount    = count(array_filter($pending, fn($c) => $c['source'] === 'Self-Registered'));
$adminCount   = $totalPending - $selfCount;
$waitingLong  = count(array_filter($pending, fn($c) => $c['waited'] >= 7));

$statTiles = [
    ['label' => 'Total Pending',        'value' => $totalPending, 'tone' => 'brand',  'icon' => 'fa-hourglass-half', 'on' => true],
    ['label' => 'Self-Registered',      'value' => $selfCount,    'tone' => 'indigo', 'icon' => 'fa-globe',          'on' => false],
    ['label' => 'Added by Admin',       'value' => $adminCount,   'tone' => 'grey',   'icon' => 'fa-user-tie',       'on' => false],
    ['label' => 'Waiting Over 7 Days',  'value' => $waitingLong,  'tone' => 'gold',   'icon' => 'fa-clock',          'on' => false],
];

/* Sidebar inputs — the badge mirrors the live queue length. */
$activePage    = 'churches/pending';
$sidebarBadges = ['pending' => $totalPending, 'expiring' => 5];
$sidebarUser   = ['name' => 'Super Admin', 'role' => 'System Owner'];

/* ── Filter bar options ────────────────────────────────────────────────────
   LATER: provinces from the `provinces` table. */
$sourceOptions = ['All Sources', 'Self-Registered', 'Added by Admin'];
$provinces     = ['Harare', 'Bulawayo', 'Manicaland', 'Midlands', 'Masvingo',
                  'Mashonaland East', 'Mashonaland West', 'Mashonaland Central',
                  'Matabeleland North', 'Matabeleland South'];
$sortOptions   = ['Newest First', 'Oldest First', 'Longest Waiting'];

/* ── Sortable column headings (visual only) ───────────────────────────────── */
$columns = [
    ['label' => 'Church',         'sort' => null],
    ['label' => 'Contact Person', 'sort' => null],
    ['label' => 'Location',       'sort' => null],
    ['label' => 'Requested Plan', 'sort' => null],
    ['label' => 'Est. Members',   'sort' => null, 'align' => 'right'],
    ['label' => 'Source',         'sort' => null],
    ['label' => 'Submitted',      'sort' => 'desc'],
];

/* ── Row overflow menu ─────────────────────────────────────────────────────
   LATER: entries gate on the signed-in admin's permissions. */
$rowMenu = [
    ['label' => 'Edit Details',   'icon' => 'fa-pen'],
    ['label' => 'Contact Church', 'icon' => 'fa-phone'],
    ['label' => 'Send Reminder',  'icon' => 'fa-bell'],
    ['label' => 'Add Note',       'icon' => 'fa-note-sticky', 'modal' => 'modalNote'],
    ['label' => 'Reject',         'icon' => 'fa-circle-xmark','modal' => 'modalReject', 'sep' => true],
    ['label' => 'Delete',         'icon' => 'fa-trash',       'danger' => true],
];

/* ── Pagination ────────────────────────────────────────────────────────────
   LATER: derive from COUNT(*) over the filtered set. */
$pager = ['from' => 1, 'to' => $totalPending, 'total' => $totalPending, 'pages' => 1, 'current' => 1];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pending Activation — Mutendi CMS Super Admin</title>
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
        <a href="#"><i class="fa-solid fa-gear"></i> Settings</a>
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
      <h1>Pending Activation <span class="title-badge"><?= $totalPending ?></span></h1>
      <p class="crumb">
        <a href="<?= $base_url ?>index.php">Dashboard</a> <i class="fa-solid fa-chevron-right"></i>
        Churches <i class="fa-solid fa-chevron-right"></i> Pending Activation
      </p>
      <p class="page-hint">Churches awaiting your review and activation.</p>
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
          <input type="search" placeholder="Search by church name, contact person, email or phone...">
        </span>
      </label>

      <label class="field"><span class="field__label">Source</span>
        <select><?php foreach ($sourceOptions as $o): ?><option><?= htmlspecialchars($o) ?></option><?php endforeach; ?></select></label>

      <label class="field"><span class="field__label">Province</span>
        <select><option>All Provinces</option><?php foreach ($provinces as $o): ?><option><?= htmlspecialchars($o) ?></option><?php endforeach; ?></select></label>

      <label class="field"><span class="field__label">Submitted From</span><input type="date"></label>
      <label class="field"><span class="field__label">To</span><input type="date"></label>

      <label class="field"><span class="field__label">Sort by</span>
        <select><?php foreach ($sortOptions as $o): ?><option><?= htmlspecialchars($o) ?></option><?php endforeach; ?></select></label>
    </div>

    <div class="filterbar__foot">
      <div class="filterbar__actions">
        <button class="btn btn--primary" type="button"><i class="fa-solid fa-filter"></i> Apply Filters</button>
        <a class="link-reset" href="#">Reset</a>
      </div>
    </div>
  </div>

  <!-- 4. Bulk action bar -->
  <div class="bulkbar" id="bulkBar" hidden>
    <span class="bulkbar__count"><strong id="bulkCount">0</strong> selected</span>
    <div class="bulkbar__actions">
      <button class="btn btn--sm btn--go" type="button" data-modal="modalActivate"><i class="fa-solid fa-circle-check"></i> Approve &amp; Activate</button>
      <button class="btn btn--sm" type="button" data-modal="modalReject"><i class="fa-solid fa-circle-xmark"></i> Reject</button>
      <button class="btn btn--sm" type="button"><i class="fa-solid fa-bell"></i> Send Reminder</button>
      <button class="btn btn--sm btn--danger" type="button"><i class="fa-solid fa-trash"></i> Delete</button>
    </div>
    <button class="bulkbar__clear" type="button" id="bulkClear" aria-label="Clear selection"><i class="fa-solid fa-xmark"></i></button>
  </div>

  <!-- 5. Approval queue -->
  <div class="card">
    <div class="table-wrap">
      <table class="table table--churches">
        <thead>
          <tr>
            <th class="col-check"><input type="checkbox" id="checkAll" aria-label="Select all requests"></th>
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
          <?php foreach ($pending as $i => $c): ?>
            <?php $tone = wait_tone($c['waited']); ?>
            <tr>
              <td class="col-check"><input type="checkbox" class="row-check" aria-label="Select <?= htmlspecialchars($c['name']) ?>"></td>
              <td class="col-num muted"><?= $i + 1 ?></td>

              <td>
                <div class="church">
                  <span class="church__avatar"><?= htmlspecialchars($c['initials']) ?></span>
                  <span class="church__text">
                    <strong><?= htmlspecialchars($c['name']) ?></strong>
                    <small><?= htmlspecialchars($c['denom']) ?></small>
                  </span>
                </div>
              </td>

              <td>
                <span class="stack">
                  <strong><?= htmlspecialchars($c['contact']) ?></strong>
                  <small><?= htmlspecialchars($c['email']) ?></small>
                  <small><?= htmlspecialchars($c['phone']) ?></small>
                </span>
              </td>

              <td class="nowrap">
                <span class="stack">
                  <strong><?= htmlspecialchars($c['city']) ?></strong>
                  <small><?= htmlspecialchars($c['province']) ?></small>
                </span>
              </td>

              <td>
                <?php if ($c['plan'] === 'Not specified'): ?>
                  <span class="plan plan--none">Not specified</span>
                <?php else: ?>
                  <span class="plan plan--<?= strtolower($c['plan']) ?>"><?= $c['plan'] ?></span>
                <?php endif; ?>
              </td>

              <td class="ta-right strong"><?= number_format($c['members']) ?></td>

              <td>
                <span class="src src--<?= $c['source'] === 'Self-Registered' ? 'self' : 'admin' ?>">
                  <?= htmlspecialchars($c['source']) ?>
                </span>
              </td>

              <td class="nowrap">
                <span class="stack">
                  <strong><?= htmlspecialchars($c['submitted']) ?></strong>
                  <small class="<?= $tone ? 'note--' . $tone : '' ?>"><?= wait_label($c['waited']) ?></small>
                </span>
              </td>

              <td class="col-actions">
                <div class="row-actions">
                  <button class="btn btn--sm btn--go" type="button" data-modal="modalActivate"><i class="fa-solid fa-circle-check"></i> Activate</button>
                  <button class="ico-btn" type="button" title="View Details" aria-label="View Details" data-modal="modalDetails"><i class="fa-regular fa-eye"></i></button>
                  <button class="ico-btn" type="button" title="Reject" aria-label="Reject" data-modal="modalReject"><i class="fa-solid fa-circle-xmark"></i></button>

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

    <!-- 7. Empty state — shown when the queue is clear -->
    <div class="empty" id="emptyState" hidden>
      <span class="empty__icon empty__icon--go"><i class="fa-solid fa-circle-check"></i></span>
      <p class="empty__title">No pending activations — you're all caught up</p>
      <p class="empty__text">Every church that has registered has been reviewed. New requests will appear here as they arrive.</p>
    </div>

    <!-- 6. Table footer -->
    <div class="tablefoot">
      <p class="tablefoot__count">Showing <?= $pager['from'] ?> to <?= $pager['to'] ?> of <?= $pager['total'] ?> entries</p>
      <nav class="pagination" aria-label="Pagination">
        <a class="pagination__btn is-disabled" href="#">Previous</a>
        <?php for ($n = 1; $n <= $pager['pages']; $n++): ?>
          <a class="pagination__btn<?= $n === $pager['current'] ? ' is-on' : '' ?>" href="#"><?= $n ?></a>
        <?php endfor; ?>
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

<!-- a) ACTIVATE — the primary action on this page -->
<div class="modal" id="modalActivate" hidden>
  <div class="modal__box modal__box--lg">
    <div class="modal__head">
      <h2><i class="fa-solid fa-circle-check note--go"></i> Activate Church</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">

      <section class="msec">
        <h3 class="msec__title">Church Details</h3>
        <dl class="summary">
          <div><dt>Church name</dt><dd>Grace Revival Church</dd></div>
          <div><dt>Contact person</dt><dd>Pastor T. Mabhena</dd></div>
          <div><dt>Email</dt><dd>grace.revival@gmail.com</dd></div>
          <div><dt>Phone</dt><dd>+263 772 445 190</dd></div>
          <div><dt>Location</dt><dd>Chitungwiza, Harare</dd></div>
          <div><dt>Denomination</dt><dd>Pentecostal</dd></div>
          <div><dt>Estimated members</dt><dd>640</dd></div>
        </dl>
      </section>

      <section class="msec">
        <h3 class="msec__title">Assign Plan</h3>
        <div class="plancards">
          <?php foreach ([['Basic','Core records only','$15 / mo'],
                          ['Standard','Records + messaging','$30 / mo'],
                          ['Premium','Everything, multi-branch','$50 / mo']] as $i => [$n, $d, $p]): ?>
            <label class="plancard">
              <input type="radio" name="actplan"<?= $i === 1 ? ' checked' : '' ?>>
              <span class="plancard__body">
                <strong><?= $n ?></strong>
                <small><?= $d ?></small>
                <em><?= $p ?></em>
              </span>
            </label>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="msec">
        <h3 class="msec__title">Subscription Period</h3>
        <div class="radios">
          <?php foreach (['1 month', '3 months', '6 months', '12 months', 'Custom'] as $i => $d): ?>
            <label class="radio"><input type="radio" name="actdur"<?= $i === 3 ? ' checked' : '' ?>><span><?= $d ?></span></label>
          <?php endforeach; ?>
        </div>
        <div class="field-row">
          <label class="field"><span class="field__label">Custom expiry date</span><input type="date"></label>
          <p class="preview"><span>Expiry date will be</span><strong>24 Aug 2027</strong></p>
        </div>
      </section>

      <section class="msec">
        <h3 class="msec__title">Payment Record</h3>
        <div class="field-row">
          <label class="field"><span class="field__label">Amount paid</span><input type="text" placeholder="$360.00"></label>
          <label class="field"><span class="field__label">Payment method</span>
            <select><option>Cash</option><option>EcoCash</option><option>Bank Transfer</option><option>Other</option></select></label>
        </div>
        <label class="field"><span class="field__label">Reference / note</span>
          <textarea rows="2" placeholder="Receipt number, who paid, any note..."></textarea></label>
      </section>

      <section class="msec">
        <h3 class="msec__title">Church Code</h3>
        <label class="field"><span class="field__label">Suggested code</span><input type="text" value="GRC-009"></label>
      </section>

      <section class="msec">
        <h3 class="msec__title">Admin Account</h3>
        <div class="field-row">
          <label class="field"><span class="field__label">Full name</span><input type="text" value="Pastor T. Mabhena"></label>
          <label class="field"><span class="field__label">Email</span><input type="email" value="grace.revival@gmail.com"></label>
        </div>
        <label class="field"><span class="field__label">Phone</span><input type="tel" value="+263 772 445 190"></label>
        <label class="check-row"><input type="checkbox" checked>
          <span>Send login credentials via email and SMS</span></label>
      </section>

    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--go" type="button"><i class="fa-solid fa-circle-check"></i> Activate Church</button>
    </div>
  </div>
</div>

<!-- b) REJECT -->
<div class="modal" id="modalReject" hidden>
  <div class="modal__box modal__box--sm">
    <div class="modal__head">
      <h2><i class="fa-solid fa-triangle-exclamation note--berry"></i> Reject Request</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <p class="modal__lead">You are about to reject <strong>Grace Revival Church</strong>. The request stays on file but the church will not be activated.</p>
      <label class="field"><span class="field__label">Reason</span>
        <select>
          <option>Duplicate registration</option>
          <option>Incomplete information</option>
          <option>Not a genuine request</option>
          <option>Payment not received</option>
          <option>Other</option>
        </select></label>
      <label class="field"><span class="field__label">Notes</span>
        <textarea rows="3" placeholder="Anything worth recording for later..."></textarea></label>
      <label class="check-row"><input type="checkbox" checked><span>Notify the church by email</span></label>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--danger-solid" type="button">Reject Request</button>
    </div>
  </div>
</div>

<!-- c) VIEW DETAILS -->
<div class="modal" id="modalDetails" hidden>
  <div class="modal__box">
    <div class="modal__head">
      <h2><i class="fa-regular fa-eye"></i> Request Details</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <section class="msec">
        <h3 class="msec__title">Church Information</h3>
        <dl class="summary">
          <div><dt>Church name</dt><dd>Grace Revival Church</dd></div>
          <div><dt>Denomination</dt><dd>Pentecostal</dd></div>
          <div><dt>Estimated members</dt><dd>640</dd></div>
          <div><dt>Requested plan</dt><dd><span class="plan plan--standard">Standard</span></dd></div>
        </dl>
      </section>
      <section class="msec">
        <h3 class="msec__title">Contact</h3>
        <dl class="summary">
          <div><dt>Contact person</dt><dd>Pastor T. Mabhena</dd></div>
          <div><dt>Email</dt><dd>grace.revival@gmail.com</dd></div>
          <div><dt>Phone</dt><dd>+263 772 445 190</dd></div>
          <div><dt>Location</dt><dd>Chitungwiza, Harare</dd></div>
        </dl>
      </section>
      <section class="msec">
        <h3 class="msec__title">Submission</h3>
        <dl class="summary">
          <div><dt>Submitted</dt><dd>22 Aug 2026</dd></div>
          <div><dt>Waiting</dt><dd>2 days</dd></div>
          <div><dt>Source</dt><dd><span class="src src--self">Self-Registered</span></dd></div>
        </dl>
      </section>
      <section class="msec">
        <h3 class="msec__title">Internal Notes</h3>
        <p class="notebox">Called on 23 Aug — pastor confirmed they want Standard and will pay by EcoCash before month end.</p>
      </section>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-modal="modalReject">Reject</button>
      <button class="btn btn--go" type="button" data-modal="modalActivate"><i class="fa-solid fa-circle-check"></i> Activate</button>
    </div>
  </div>
</div>

<!-- d) ADD NOTE -->
<div class="modal" id="modalNote" hidden>
  <div class="modal__box modal__box--sm">
    <div class="modal__head">
      <h2><i class="fa-solid fa-note-sticky"></i> Add Note</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <p class="modal__lead">Internal note for <strong>Grace Revival Church</strong>. Only super admins see this.</p>
      <label class="field"><span class="field__label">Note</span>
        <textarea rows="4" placeholder="What was agreed, who you spoke to, what to follow up..."></textarea></label>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--primary" type="button">Save Note</button>
    </div>
  </div>
</div>

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

<script>
/* Bulk-selection bar, dropdown menus and modal open/close. No form handling. */
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
      // Opening from inside another modal swaps them rather than stacking.
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

    // Every opener sets the type: "Start New Trial" presets to trial, any
    // other Add button resets to paying so the choice never carries over.
    document.addEventListener('click', function (e) {
      var opener = e.target.closest('[data-modal="modalAdd"]');
      if (!opener) { return; }
      var radio = document.querySelector('[data-acctype="' + (opener.dataset.preset || 'paying') + '"]');
      if (radio) { radio.checked = true; }
      applyType();
    });

    applyType();
  }
})();
</script>
</body>
</html>

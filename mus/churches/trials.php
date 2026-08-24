<?php
/**
 * Mutendi CMS — Trial Accounts (static UI mockup).
 *
 * The conversion pipeline — who is engaged enough to buy, and who is about to walk away.
 * Every dataset is hardcoded; each block carries the query that will replace
 * it. Search, filters, sorting and pagination are visual only.
 */

/* Path constants only — the sidebar markup is required further down. */
$musPathsOnly = true;
require __DIR__ . '/../components/sidebar.php';
$musPathsOnly = false;

/* ── Conversion pipeline ───────────────────────────────────────────────────
   LATER:
     SELECT t.*, c.name, c.code, c.contact_name, c.phone,
            (SELECT COUNT(*) FROM members m WHERE m.church_id = c.id) AS captured,
            (SELECT COUNT(*) FROM logins l WHERE l.church_id = c.id) AS logins
       FROM trials t JOIN churches c ON c.id = t.church_id
      WHERE t.status = 'active'
      ORDER BY t.ends_at ASC;
   `engage` is scored from login frequency and records captured. */
$rows = [
    ['initials'=>'NL','name'=>'New Life Chitungwiza','code'=>'NLC-061','contact'=>'Pastor A. Mabika','phone'=>'+263 772 118 904','started'=>'04 Aug 2026','ends'=>'25 Aug 2026','left'=>1,'captured'=>88,'target'=>100,'engage'=>'High','logins'=>24,'login'=>'2 hours ago'],
    ['initials'=>'RH','name'=>'Rhema Bulawayo','code'=>'RHB-063','contact'=>'Pastor V. Ncube','phone'=>'+263 778 337 220','started'=>'07 Aug 2026','ends'=>'28 Aug 2026','left'=>4,'captured'=>61,'target'=>100,'engage'=>'High','logins'=>18,'login'=>'Yesterday'],
    ['initials'=>'ST','name'=>'St Thomas Mutare','code'=>'STM-064','contact'=>'Rev. B. Chidziva','phone'=>'+263 771 446 093','started'=>'10 Aug 2026','ends'=>'31 Aug 2026','left'=>7,'captured'=>45,'target'=>100,'engage'=>'Medium','logins'=>11,'login'=>'3 days ago'],
    ['initials'=>'GV','name'=>'Grace Valley Gweru','code'=>'GVG-066','contact'=>'Pastor H. Moyo','phone'=>'+263 774 902 651','started'=>'14 Aug 2026','ends'=>'04 Sep 2026','left'=>11,'captured'=>32,'target'=>100,'engage'=>'Medium','logins'=>8,'login'=>'5 days ago'],
    ['initials'=>'KM','name'=>'Kingdom Masvingo','code'=>'KDM-067','contact'=>'Pastor T. Zvobgo','phone'=>'+263 712 663 448','started'=>'17 Aug 2026','ends'=>'07 Sep 2026','left'=>14,'captured'=>19,'target'=>100,'engage'=>'Low','logins'=>4,'login'=>'9 days ago'],
    ['initials'=>'AC','name'=>'Agape Chinhoyi','code'=>'AGC-069','contact'=>'Pastor R. Mutasa','phone'=>'+263 776 220 137','started'=>'19 Aug 2026','ends'=>'09 Sep 2026','left'=>16,'captured'=>12,'target'=>100,'engage'=>'Low','logins'=>3,'login'=>'11 days ago'],
    ['initials'=>'BT','name'=>'Bethel Kwekwe','code'=>'BTK-070','contact'=>'Elder S. Dube','phone'=>'+263 719 553 806','started'=>'21 Aug 2026','ends'=>'11 Sep 2026','left'=>18,'captured'=>4,'target'=>100,'engage'=>'Low','logins'=>2,'login'=>'14 days ago'],
    ['initials'=>'SS','name'=>'Shalom Norton','code'=>'SHN-072','contact'=>'Pastor J. Nyamande','phone'=>'+263 773 008 219','started'=>'23 Aug 2026','ends'=>'13 Sep 2026','left'=>20,'captured'=>0,'target'=>100,'engage'=>'None','logins'=>0,'login'=>'Never'],
];

/** Urgency of the trial ending: red in the last 3 days, amber in the last week. */
function trial_tone(int $days): string {
    if ($days <= 3) { return 'berry'; }
    if ($days <= 7) { return 'gold'; }
    return 'indigo';
}

$rowCount = count($rows);

/* ── Stat strip — derived from the pipeline above. ────────────────────────
   LATER: COUNT(*) windows plus a conversions-this-month query. */
$endingWeek = count(array_filter($rows, fn($c) => $c['left'] <= 7));
$engaged    = count(array_filter($rows, fn($c) => $c['engage'] === 'High'));

$statTiles = [
    ['label' => 'Active Trials',       'value' => $rowCount,   'tone' => 'indigo', 'icon' => 'fa-star',        'on' => true],
    ['label' => 'Ending This Week',    'value' => $endingWeek, 'tone' => 'gold',   'icon' => 'fa-hourglass-end','on' => false],
    ['label' => 'Highly Engaged',      'value' => $engaged,    'tone' => 'green',  'icon' => 'fa-fire-flame-curved', 'on' => false],
    ['label' => 'Converted This Month','value' => 4,           'tone' => 'brand',  'icon' => 'fa-circle-check','on' => false],
];

$provinces = ['Harare', 'Bulawayo', 'Manicaland', 'Midlands', 'Masvingo',
              'Mashonaland East', 'Mashonaland West', 'Mashonaland Central',
              'Matabeleland North', 'Matabeleland South'];

$columns = [
    ['label' => 'Church',           'sort' => null],
    ['label' => 'Contact',          'sort' => null],
    ['label' => 'Trial Started',    'sort' => null],
    ['label' => 'Trial Ends',       'sort' => 'asc'],
    ['label' => 'Days Remaining',   'sort' => null],
    ['label' => 'Members Captured', 'sort' => null],
    ['label' => 'Engagement',       'sort' => null],
    ['label' => 'Logins',           'sort' => null, 'align' => 'right'],
    ['label' => 'Last Login',       'sort' => null],
];

$rowMenu = [
    ['label' => 'View Church',   'icon' => 'fa-eye'],
    ['label' => 'Send Follow-Up','icon' => 'fa-paper-plane', 'modal' => 'modalFollow'],
    ['label' => 'Add Note',      'icon' => 'fa-note-sticky', 'modal' => 'modalNote'],
    ['label' => 'Edit Church',   'icon' => 'fa-pen'],
    ['label' => 'End Trial',     'icon' => 'fa-circle-stop', 'modal' => 'modalEnd', 'sep' => true],
    ['label' => 'Delete',        'icon' => 'fa-trash',       'danger' => true],
];

$activePage    = 'churches/trials';
$sidebarBadges = ['pending' => 10, 'expiring' => 12];
$sidebarUser   = ['name' => 'Super Admin', 'role' => 'System Owner'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Trial Accounts — Mutendi CMS Super Admin</title>
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
      <h1>Trial Accounts <span class="title-badge"><?= $rowCount ?></span></h1>
      <p class="crumb">
        <a href="<?= $base_url ?>index.php">Dashboard</a> <i class="fa-solid fa-chevron-right"></i>
        Churches <i class="fa-solid fa-chevron-right"></i> Trial Accounts
      </p>
      <p class="page-hint">Churches currently evaluating the system — convert them before their trial ends.</p>
    </div>
    <div class="head-actions">
      <button class="btn btn--primary" type="button" data-modal="modalAdd" data-preset="trial"><i class="fa-solid fa-plus"></i> Start New Trial</button>
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
      <label class="field"><span class="field__label">Trial Status</span>
        <select><option>All</option><option>Active</option><option>Ending Soon</option><option>Expired Trial</option><option>Converted</option></select></label>
      <label class="field"><span class="field__label">Engagement</span>
        <select><option>All</option><option>High</option><option>Medium</option><option>Low</option><option>No Activity</option></select></label>
      <label class="field"><span class="field__label">Province</span>
        <select><option>All Provinces</option><?php foreach ($provinces as $o): ?><option><?= htmlspecialchars($o) ?></option><?php endforeach; ?></select></label>
      <label class="field"><span class="field__label">Started From</span><input type="date"></label>
      <label class="field"><span class="field__label">To</span><input type="date"></label>
      <label class="field"><span class="field__label">Sort by</span>
        <select><option>Ending Soonest</option><option>Most Engaged</option><option>Newest</option></select></label>
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
      <button class="btn btn--sm btn--go" type="button" data-modal="modalConvert"><i class="fa-solid fa-circle-check"></i> Convert to Paid</button>
      <button class="btn btn--sm" type="button" data-modal="modalExtendTrial"><i class="fa-solid fa-calendar-plus"></i> Extend Trial</button>
      <button class="btn btn--sm" type="button" data-modal="modalFollow"><i class="fa-solid fa-paper-plane"></i> Send Follow-Up</button>
      <button class="btn btn--sm" type="button"><i class="fa-solid fa-file-export"></i> Export Selected</button>
      <button class="btn btn--sm btn--danger" type="button" data-modal="modalEnd"><i class="fa-solid fa-circle-stop"></i> End Trial</button>
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
              <td class="nowrap muted"><?= $c['started'] ?></td>
              <?php $tone = trial_tone($c['left']); ?>
              <td class="nowrap">
                <span class="stack">
                  <strong><?= $c['ends'] ?></strong>
                  <small class="note--<?= $tone ?>">in <?= $c['left'] ?> days</small>
                </span>
              </td>
              <td><span class="countdown countdown--<?= $tone ?>"><?= $c['left'] ?>d</span></td>
              <td>
                <span class="setup">
                  <span class="setup__num"><?= $c['captured'] ?>/<?= $c['target'] ?></span>
                  <span class="bar"><i style="width: <?= (int) round($c['captured'] / $c['target'] * 100) ?>%"></i></span>
                </span>
              </td>
              <td><span class="engage engage--<?= strtolower($c['engage']) ?>"><?= $c['engage'] ?></span></td>
              <td class="ta-right strong"><?= $c['logins'] ?></td>
              <td class="nowrap <?= $c['login'] === 'Never' ? 'muted' : '' ?>"><?= $c['login'] ?></td>
              <td class="col-actions">
                <div class="row-actions">
                  <button class="btn btn--sm btn--go" type="button" data-modal="modalConvert"><i class="fa-solid fa-circle-check"></i> Convert to Paid</button>
                  <button class="ico-btn" type="button" title="Extend Trial" aria-label="Extend Trial" data-modal="modalExtendTrial"><i class="fa-solid fa-calendar-plus"></i></button>
                  <a class="ico-btn" href="#" title="Login As" aria-label="Login As"><i class="fa-solid fa-right-to-bracket"></i></a>
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
      <span class="empty__icon "><i class="fa-solid fa-star"></i></span>
      <p class="empty__title">No active trials</p>
      <p class="empty__text">Nobody is currently evaluating the system. Start a trial from the button above to add one.</p>
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
<div class="modal" id="modalConvert" hidden>
  <div class="modal__box modal__box--lg">
    <div class="modal__head">
      <h2><i class="fa-solid fa-circle-check note--go"></i> Convert to Paid</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <label class="field"><span class="field__label">Church</span>
        <input type="text" value="New Life Chitungwiza (NLC-061)" readonly></label>
      <p class="summary-line"><i class="fa-solid fa-chart-simple"></i>
        Trial ran 21 days &middot; 88 members captured &middot; 24 logins</p>

      <section class="msec">
        <h3 class="msec__title">Assign Plan</h3>
        <div class="plancards">
          <?php foreach ([['Basic','Core records only','$15 / mo'],
                          ['Standard','Records + messaging','$30 / mo'],
                          ['Premium','Everything, multi-branch','$50 / mo']] as $i => [$n, $d, $pr]): ?>
            <label class="plancard">
              <input type="radio" name="cplan"<?= $i === 1 ? ' checked' : '' ?>>
              <span class="plancard__body"><strong><?= $n ?></strong><small><?= $d ?></small><em><?= $pr ?></em></span>
            </label>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="msec">
        <h3 class="msec__title">Subscription Period</h3>
        <div class="radios">
          <?php foreach (['1 month', '3 months', '6 months', '12 months', 'Custom'] as $i => $d): ?>
            <label class="radio"><input type="radio" name="cdur"<?= $i === 3 ? ' checked' : '' ?>><span><?= $d ?></span></label>
          <?php endforeach; ?>
        </div>
        <p class="preview"><span>Expiry date will be</span><strong>25 Aug 2027</strong></p>
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
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--go" type="button"><i class="fa-solid fa-circle-check"></i> Convert to Paid</button>
    </div>
  </div>
</div>

<div class="modal" id="modalExtendTrial" hidden>
  <div class="modal__box modal__box--sm">
    <div class="modal__head">
      <h2><i class="fa-solid fa-calendar-plus"></i> Extend Trial</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <p class="modal__lead">Current trial for <strong>New Life Chitungwiza</strong> ends <strong>25 Aug 2026</strong>.</p>
      <span class="field__label">Extend by</span>
      <div class="radios">
        <?php foreach (['7 days', '14 days', '30 days', 'Custom'] as $i => $d): ?>
          <label class="radio"><input type="radio" name="tdur"<?= $i === 1 ? ' checked' : '' ?>><span><?= $d ?></span></label>
        <?php endforeach; ?>
      </div>
      <label class="field"><span class="field__label">Custom end date</span><input type="date"></label>
      <label class="field"><span class="field__label">Reason</span>
        <textarea rows="3" placeholder="Why the trial is being extended..."></textarea></label>
      <p class="preview"><span>New trial end date</span><strong>08 Sep 2026</strong></p>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--primary" type="button">Extend Trial</button>
    </div>
  </div>
</div>

<div class="modal" id="modalFollow" hidden>
  <div class="modal__box">
    <div class="modal__head">
      <h2><i class="fa-solid fa-paper-plane"></i> Send Follow-Up</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <p class="modal__lead">Follow-up to <strong>New Life Chitungwiza</strong>, trial ending in 1 day.</p>
      <span class="field__label">Channel</span>
      <div class="radios">
        <label class="radio"><input type="checkbox" checked><span>Email</span></label>
        <label class="radio"><input type="checkbox" checked><span>SMS</span></label>
      </div>
      <label class="field"><span class="field__label">Template</span>
        <select><option>Trial ending — ready to continue?</option><option>How are you finding it?</option><option>Help getting set up</option><option>Custom message</option></select></label>
      <label class="field"><span class="field__label">Message</span>
        <textarea rows="4">Dear Pastor Mabika, your Mutendi CMS trial ends on 25 Aug 2026. You have captured 88 members so far — let us know if you would like to continue and we will set up your subscription.</textarea></label>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--primary" type="button">Send Follow-Up</button>
    </div>
  </div>
</div>

<div class="modal" id="modalEnd" hidden>
  <div class="modal__box modal__box--sm">
    <div class="modal__head">
      <h2><i class="fa-solid fa-triangle-exclamation note--gold"></i> End Trial</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <p class="modal__lead">Ending the trial for <strong>Shalom Norton</strong> closes their access immediately. Captured records are retained for 90 days.</p>
      <label class="field"><span class="field__label">Reason</span>
        <select><option>Trial period complete</option><option>No activity</option><option>Church declined</option><option>Duplicate trial</option><option>Other</option></select></label>
      <label class="field"><span class="field__label">Notes</span>
        <textarea rows="3" placeholder="Anything worth recording..."></textarea></label>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--danger-solid" type="button">End Trial</button>
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

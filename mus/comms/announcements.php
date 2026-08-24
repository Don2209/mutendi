<?php
/**
 * Mutendi CMS — Announcements (static UI mockup).
 *
 * In-app notices shown to church admins when they sign in. Trial and Paying
 * churches are the same kind of tenant here — audience targeting simply
 * distinguishes them by account type. Every dataset is hardcoded; each block
 * carries the query that replaces it. All form actions are visual only.
 */

/* Path constants only — the sidebar markup is required further down. */
$musPathsOnly = true;
require __DIR__ . '/../components/sidebar.php';
$musPathsOnly = false;

/* ── Announcement types ────────────────────────────────────────────────────
   LATER: SELECT * FROM announcement_types ORDER BY sort_order; */
$types = [
    'Info'        => ['icon' => 'fa-circle-info',        'tone' => 'indigo'],
    'Update'      => ['icon' => 'fa-arrow-up',           'tone' => 'brand'],
    'Warning'     => ['icon' => 'fa-triangle-exclamation','tone' => 'gold'],
    'Maintenance' => ['icon' => 'fa-screwdriver-wrench', 'tone' => 'grey'],
    'Feature'     => ['icon' => 'fa-wand-magic-sparkles','tone' => 'green'],
];

/* ── The announcements ─────────────────────────────────────────────────────
   LATER:
     SELECT a.*, COUNT(v.church_id) AS views
       FROM announcements a
       LEFT JOIN announcement_views v ON v.announcement_id = a.id
      WHERE (:search IS NULL OR a.title LIKE :search OR a.body LIKE :search)
        AND (:status IS NULL OR a.status = :status)
        AND (:type IS NULL OR a.type = :type)
        AND (:audience IS NULL OR a.audience = :audience)
      GROUP BY a.id ORDER BY a.published_at DESC
      LIMIT :per_page OFFSET :offset; */
$rows = [
    ['type'=>'Maintenance','title'=>'Scheduled maintenance — Sunday 02:00 to 04:00','excerpt'=>'The system will be briefly unavailable while we upgrade the database servers.','audience'=>'All Churches','style'=>'Banner','published'=>'23 Aug 2026','ago'=>'Yesterday','expires'=>'25 Aug 2026','views'=>34,'total'=>47,'status'=>'Published'],
    ['type'=>'Feature','title'=>'Sermon archive now available','excerpt'=>'Upload and organise recordings under the new Sermons & Media module.','audience'=>'Paying Only','style'=>'Popup','published'=>'21 Aug 2026','ago'=>'3 days ago','expires'=>'','views'=>28,'total'=>38,'status'=>'Published'],
    ['type'=>'Warning','title'=>'Renewals due before the end of the month','excerpt'=>'Twelve subscriptions lapse within 30 days. Please arrange payment early.','audience'=>'12 Churches','style'=>'Banner','published'=>'19 Aug 2026','ago'=>'5 days ago','expires'=>'31 Aug 2026','views'=>9,'total'=>12,'status'=>'Published'],
    ['type'=>'Info','title'=>'New guide: capturing your baptism register','excerpt'=>'A step-by-step walkthrough for moving your paper registers across.','audience'=>'All Churches','style'=>'Notice','published'=>'16 Aug 2026','ago'=>'8 days ago','expires'=>'','views'=>41,'total'=>47,'status'=>'Published'],
    ['type'=>'Update','title'=>'Attendance reports have been rebuilt','excerpt'=>'Faster reports with a new week-on-week comparison view.','audience'=>'All Churches','style'=>'Notice','published'=>'26 Aug 2026','ago'=>'in 2 days','expires'=>'','views'=>0,'total'=>47,'status'=>'Scheduled'],
    ['type'=>'Feature','title'=>'Cell group attendance is coming','excerpt'=>'Home cell registers will arrive in the next release.','audience'=>'Trial Only','style'=>'Popup','published'=>'28 Aug 2026','ago'=>'in 4 days','expires'=>'','views'=>0,'total'=>9,'status'=>'Scheduled'],
    ['type'=>'Info','title'=>'Contact details for support have changed','excerpt'=>'Support now runs on a single WhatsApp line during office hours.','audience'=>'All Churches','style'=>'Notice','published'=>'','ago'=>'','expires'=>'','views'=>0,'total'=>47,'status'=>'Draft'],
    ['type'=>'Warning','title'=>'SMS costs increasing from October','excerpt'=>'Network tariffs rise on 1 October and bundle prices will follow.','audience'=>'Paying Only','style'=>'Banner','published'=>'','ago'=>'','expires'=>'','views'=>0,'total'=>38,'status'=>'Draft'],
    ['type'=>'Maintenance','title'=>'Backup window moving to 03:00','excerpt'=>'Nightly backups shift by one hour from next week.','audience'=>'All Churches','style'=>'Notice','published'=>'','ago'=>'','expires'=>'','views'=>0,'total'=>47,'status'=>'Draft'],
    ['type'=>'Update','title'=>'Member import now accepts Excel files','excerpt'=>'You can upload .xlsx directly instead of converting to CSV first.','audience'=>'All Churches','style'=>'Notice','published'=>'02 Jul 2026','ago'=>'53 days ago','expires'=>'01 Aug 2026','views'=>44,'total'=>47,'status'=>'Expired'],
    ['type'=>'Info','title'=>'Easter service scheduling tips','excerpt'=>'How to set up multiple services and track attendance across them.','audience'=>'All Churches','style'=>'Popup','published'=>'18 Mar 2026','ago'=>'159 days ago','expires'=>'20 Apr 2026','views'=>39,'total'=>45,'status'=>'Expired'],
    ['type'=>'Feature','title'=>'Two-factor authentication available','excerpt'=>'Church admins can now secure their accounts with an authenticator app.','audience'=>'All Churches','style'=>'Popup','published'=>'11 Jun 2026','ago'=>'74 days ago','expires'=>'','views'=>36,'total'=>47,'status'=>'Published'],
];

/* ── Stat strip — derived so tiles cannot drift from the table. ──────────── */
$byStatus = array_count_values(array_column($rows, 'status'));
$statTiles = [
    ['label' => 'Total',     'value' => 18,                        'tone' => 'indigo', 'icon' => 'fa-bullhorn',      'on' => true],
    ['label' => 'Published', 'value' => $byStatus['Published'] ?? 0,'tone' => 'green',  'icon' => 'fa-circle-check',  'on' => false],
    ['label' => 'Scheduled', 'value' => $byStatus['Scheduled'] ?? 0,'tone' => 'gold',   'icon' => 'fa-clock',         'on' => false],
    ['label' => 'Drafts',    'value' => $byStatus['Draft'] ?? 0,    'tone' => 'grey',   'icon' => 'fa-file-pen',      'on' => false],
];

/* ── Churches for the "Selected Churches" audience picker ──────────────────
   LATER: SELECT id, name, code, account_type FROM churches ORDER BY name; */
$churches = [
    ['ZM','ZCC Mbungo','ZCC-001','Paying'],
    ['AW','AFM Waterfalls','AFM-002','Paying'],
    ['GM','Grace Ministries','GRM-003','Paying'],
    ['JM','Johane Masowe eChishanu','JME-004','Paying'],
    ['CC','Celebration Church Harare','CCH-005','Paying'],
    ['FG','Family of God Bulawayo','FOG-006','Trial'],
    ['MM','Methodist Mutare Circuit','MMC-007','Paying'],
    ['NL','New Life Chitungwiza','NLC-061','Trial'],
    ['RH','Rhema Bulawayo','RHB-063','Trial'],
    ['AD','Anglican Diocese Masvingo','ADM-009','Paying'],
];

$columns = [
    ['label' => 'Announcement', 'sort' => null],
    ['label' => 'Type',         'sort' => null],
    ['label' => 'Audience',     'sort' => null],
    ['label' => 'Display',      'sort' => null],
    ['label' => 'Published On', 'sort' => 'desc'],
    ['label' => 'Expires On',   'sort' => null],
    ['label' => 'Views',        'sort' => null],
    ['label' => 'Status',       'sort' => null],
];

$rowMenu = [
    ['label' => 'View Details',    'icon' => 'fa-eye',        'modal' => 'modalView'],
    ['label' => 'Edit',            'icon' => 'fa-pen',        'modal' => 'modalAnnounce'],
    ['label' => 'Duplicate',       'icon' => 'fa-copy'],
    ['label' => 'View Recipients', 'icon' => 'fa-users'],
    ['label' => 'Publish Now',     'icon' => 'fa-paper-plane'],
    ['label' => 'Unpublish',       'icon' => 'fa-eye-slash'],
    ['label' => 'Delete',          'icon' => 'fa-trash', 'modal' => 'modalDeleteAnn', 'danger' => true, 'sep' => true],
];

$activePage    = 'comms/announcements';
$sidebarBadges = ['pending' => 10, 'expiring' => 12];
$sidebarUser   = ['name' => 'Super Admin', 'role' => 'System Owner'];

function ann_pill(string $s): string { return 'pill--' . strtolower($s); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Announcements — Mutendi CMS Super Admin</title>
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
      <h1>Announcements</h1>
      <p class="crumb">
        <a href="<?= $base_url ?>index.php">Dashboard</a> <i class="fa-solid fa-chevron-right"></i>
        Communication <i class="fa-solid fa-chevron-right"></i> Announcements
      </p>
      <p class="page-hint">Publish notices that appear inside the church admin panel.</p>
    </div>
    <div class="head-actions">
      <button class="btn btn--primary" type="button" data-modal="modalAnnounce"><i class="fa-solid fa-plus"></i> New Announcement</button>
    </div>
  </div>

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

  <div class="card filterbar">
    <div class="filterbar__row">
      <label class="field field--search">
        <span class="field__label">Search</span>
        <span class="field__input">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="search" placeholder="Search by title or content...">
        </span>
      </label>
      <label class="field"><span class="field__label">Status</span>
        <select><option>All Statuses</option><option>Published</option><option>Scheduled</option><option>Draft</option><option>Expired</option></select></label>
      <label class="field"><span class="field__label">Type</span>
        <select><option>All Types</option><?php foreach (array_keys($types) as $t): ?><option><?= $t ?></option><?php endforeach; ?></select></label>
      <label class="field"><span class="field__label">Audience</span>
        <select><option>All Churches</option><option>Trial Only</option><option>Paying Only</option><option>Selected Churches</option></select></label>
      <label class="field"><span class="field__label">From</span><input type="date"></label>
      <label class="field"><span class="field__label">To</span><input type="date"></label>
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

  <div class="bulkbar" id="bulkBar" hidden>
    <span class="bulkbar__count"><strong id="bulkCount">0</strong> selected</span>
    <div class="bulkbar__actions">
      <button class="btn btn--sm btn--go" type="button"><i class="fa-solid fa-paper-plane"></i> Publish</button>
      <button class="btn btn--sm" type="button"><i class="fa-solid fa-eye-slash"></i> Unpublish</button>
      <button class="btn btn--sm btn--danger" type="button" data-modal="modalDeleteAnn"><i class="fa-solid fa-trash"></i> Delete</button>
    </div>
    <button class="bulkbar__clear" type="button" id="bulkClear" aria-label="Clear selection"><i class="fa-solid fa-xmark"></i></button>
  </div>

  <div class="card">
    <div class="table-wrap">
      <table class="table table--churches">
        <thead>
          <tr>
            <th class="col-check"><input type="checkbox" id="checkAll" aria-label="Select all announcements"></th>
            <th class="col-num">#</th>
            <?php foreach ($columns as $c): ?>
              <th class="th-sort<?= !empty($c['sort']) ? ' is-sorted' : '' ?>">
                <button type="button" class="th-sort__btn"><?= htmlspecialchars($c['label']) ?>
                  <i class="fa-solid fa-sort<?= !empty($c['sort']) ? '-' . $c['sort'] : '' ?>"></i></button>
              </th>
            <?php endforeach; ?>
            <th class="col-actions">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $i => $r): ?>
            <?php $t = $types[$r['type']]; ?>
            <tr>
              <td class="col-check"><input type="checkbox" class="row-check" aria-label="Select <?= htmlspecialchars($r['title']) ?>"></td>
              <td class="col-num muted"><?= $i + 1 ?></td>

              <td>
                <div class="church">
                  <span class="modcard__icon modcard__icon--<?= $t['tone'] ?> annicon"><i class="fa-solid <?= $t['icon'] ?>"></i></span>
                  <span class="church__text anntext">
                    <strong><?= htmlspecialchars($r['title']) ?></strong>
                    <small><?= htmlspecialchars($r['excerpt']) ?></small>
                  </span>
                </div>
              </td>

              <td><span class="tbadge tbadge--<?= strtolower($r['type']) ?>"><?= $r['type'] ?></span></td>
              <td class="nowrap"><?= htmlspecialchars($r['audience']) ?></td>
              <td class="muted nowrap"><?= $r['style'] ?></td>

              <td class="nowrap">
                <?php if ($r['published'] !== ''): ?>
                  <span class="stack"><strong><?= $r['published'] ?></strong><small><?= $r['ago'] ?></small></span>
                <?php else: ?><span class="muted">&mdash;</span><?php endif; ?>
              </td>

              <td class="nowrap"><?= $r['expires'] !== '' ? $r['expires'] : '<span class="muted">No expiry</span>' ?></td>

              <td>
                <span class="setup">
                  <span class="setup__num"><?= $r['views'] ?> / <?= $r['total'] ?> viewed</span>
                  <span class="bar"><i style="width: <?= (int) round($r['views'] / $r['total'] * 100) ?>%"></i></span>
                </span>
              </td>

              <td><span class="pill <?= ann_pill($r['status']) ?>"><?= $r['status'] ?></span></td>

              <td class="col-actions">
                <div class="row-actions">
                  <button class="ico-btn" type="button" title="View" aria-label="View" data-modal="modalView"><i class="fa-regular fa-eye"></i></button>
                  <button class="ico-btn" type="button" title="Edit" aria-label="Edit" data-modal="modalAnnounce"><i class="fa-solid fa-pen"></i></button>
                  <button class="ico-btn" type="button" title="<?= $r['status'] === 'Published' ? 'Unpublish' : 'Publish' ?>" aria-label="Publish or unpublish">
                    <i class="fa-solid <?= $r['status'] === 'Published' ? 'fa-eye-slash' : 'fa-paper-plane' ?>"></i></button>
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

    <div class="empty" id="emptyState" hidden>
      <span class="empty__icon"><i class="fa-solid fa-bullhorn"></i></span>
      <p class="empty__title">No announcements found</p>
      <p class="empty__text">No announcement matches the filters you have applied. Try widening your search.</p>
      <button class="btn btn--primary" type="button">Reset Filters</button>
    </div>

    <div class="tablefoot">
      <p class="tablefoot__count">Showing 1 to <?= count($rows) ?> of 18 entries</p>
      <nav class="pagination" aria-label="Pagination">
        <a class="pagination__btn is-disabled" href="#">Previous</a>
        <a class="pagination__btn is-on" href="#">1</a>
        <a class="pagination__btn" href="#">2</a>
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

<!-- a) NEW / EDIT ANNOUNCEMENT -->
<div class="modal" id="modalAnnounce" hidden>
  <div class="modal__box modal__box--lg">
    <div class="modal__head">
      <h2><i class="fa-solid fa-bullhorn"></i> New Announcement</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">

      <section class="msec">
        <h3 class="msec__title">Message</h3>
        <label class="field"><span class="field__label">Title</span>
          <input type="text" id="annTitle" value="Scheduled maintenance — Sunday 02:00 to 04:00"></label>

        <div class="editor">
          <div class="fmtbar">
            <button class="fmtbar__btn" type="button" title="Bold"><i class="fa-solid fa-bold"></i></button>
            <button class="fmtbar__btn" type="button" title="Italic"><i class="fa-solid fa-italic"></i></button>
            <button class="fmtbar__btn" type="button" title="Link"><i class="fa-solid fa-link"></i></button>
            <button class="fmtbar__btn" type="button" title="List"><i class="fa-solid fa-list-ul"></i></button>
          </div>
          <textarea rows="4" id="annBody">The system will be briefly unavailable while we upgrade the database servers. No action is needed on your side.</textarea>
        </div>
      </section>

      <section class="msec">
        <h3 class="msec__title">Type</h3>
        <div class="optcards optcards--5">
          <?php foreach ($types as $name => $t): ?>
            <label class="optcard">
              <input type="radio" name="anntype" data-anntype="<?= strtolower($name) ?>"<?= $name === 'Maintenance' ? ' checked' : '' ?>>
              <span class="optcard__body">
                <i class="fa-solid <?= $t['icon'] ?> optcard__icon optcard__icon--<?= $t['tone'] ?>"></i>
                <strong><?= $name ?></strong>
              </span>
            </label>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="msec">
        <h3 class="msec__title">Display Style</h3>
        <div class="radios">
          <label class="radio"><input type="radio" name="annstyle" data-annstyle="banner" checked><span>Banner (top strip)</span></label>
          <label class="radio"><input type="radio" name="annstyle" data-annstyle="popup"><span>Popup (on login)</span></label>
          <label class="radio"><input type="radio" name="annstyle" data-annstyle="notice"><span>Notice (notifications list)</span></label>
        </div>
      </section>

      <section class="msec">
        <h3 class="msec__title">Audience</h3>
        <div class="radios">
          <label class="radio"><input type="radio" name="annaud" data-annaud="all" checked><span>All Churches</span></label>
          <label class="radio"><input type="radio" name="annaud" data-annaud="trial"><span>Trial Only</span></label>
          <label class="radio"><input type="radio" name="annaud" data-annaud="paying"><span>Paying Only</span></label>
          <label class="radio"><input type="radio" name="annaud" data-annaud="selected"><span>Selected Churches</span></label>
        </div>

        <div id="annPick" hidden>
          <label class="field"><span class="field__label">Find churches</span>
            <span class="field__input"><i class="fa-solid fa-magnifying-glass"></i>
              <input type="search" placeholder="Search churches..."></span></label>
          <ul class="picklist">
            <?php foreach ($churches as [$in, $name, $code, $acct]): ?>
              <li>
                <span class="church">
                  <span class="church__avatar"><?= $in ?></span>
                  <span class="church__text">
                    <strong><?= htmlspecialchars($name) ?></strong>
                    <small><?= $code ?> <span class="pill pill--<?= strtolower($acct) ?> pill--xs"><?= $acct ?></span></small>
                  </span>
                </span>
                <input type="checkbox" class="bigcheck">
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </section>

      <section class="msec">
        <h3 class="msec__title">Schedule &amp; Expiry</h3>
        <div class="radios">
          <label class="radio"><input type="radio" name="annwhen" data-annwhen="now" checked><span>Publish immediately</span></label>
          <label class="radio"><input type="radio" name="annwhen" data-annwhen="later"><span>Schedule for later</span></label>
        </div>
        <div class="field-row" id="annSchedule" hidden>
          <label class="field"><span class="field__label">Date</span><input type="date"></label>
          <label class="field"><span class="field__label">Time</span><input type="time"></label>
        </div>

        <label class="check-row"><input type="checkbox" id="annExpiryToggle"><span>Set an expiry date</span></label>
        <label class="field" id="annExpiry" hidden><span class="field__label">Expires on</span><input type="date"></label>
      </section>

      <section class="msec">
        <h3 class="msec__title">Options</h3>
        <label class="check-row"><input type="checkbox" checked><span>Dismissible by the church admin</span></label>
        <label class="check-row"><input type="checkbox"><span>Show on every login until dismissed</span></label>
      </section>

      <section class="msec">
        <h3 class="msec__title">Preview</h3>
        <div class="annpreview" id="annPreview" data-style="banner" data-type="maintenance">
          <div class="annpreview__frame">
            <div class="annpreview__item">
              <i class="fa-solid fa-screwdriver-wrench annpreview__icon"></i>
              <span class="annpreview__text">
                <strong id="annPvTitle">Scheduled maintenance — Sunday 02:00 to 04:00</strong>
                <small id="annPvBody">The system will be briefly unavailable while we upgrade the database servers.</small>
              </span>
              <i class="fa-solid fa-xmark annpreview__x"></i>
            </div>
          </div>
          <p class="annpreview__note" id="annPvNote">Shown as a strip across the top of the church admin panel.</p>
        </div>
      </section>

    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn" type="button">Save as Draft</button>
      <button class="btn btn--primary" type="button">Publish</button>
    </div>
  </div>
</div>

<!-- b) VIEW DETAILS -->
<div class="modal" id="modalView" hidden>
  <div class="modal__box">
    <div class="modal__head">
      <h2><i class="fa-regular fa-eye"></i> Announcement Details</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <div class="annpreview annpreview--static" data-style="banner" data-type="maintenance">
        <div class="annpreview__frame">
          <div class="annpreview__item">
            <i class="fa-solid fa-screwdriver-wrench annpreview__icon"></i>
            <span class="annpreview__text">
              <strong>Scheduled maintenance — Sunday 02:00 to 04:00</strong>
              <small>The system will be briefly unavailable while we upgrade the database servers. No action is needed on your side.</small>
            </span>
          </div>
        </div>
      </div>

      <section class="msec">
        <h3 class="msec__title">Details</h3>
        <dl class="summary">
          <div><dt>Type</dt><dd><span class="tbadge tbadge--maintenance">Maintenance</span></dd></div>
          <div><dt>Display style</dt><dd>Banner</dd></div>
          <div><dt>Audience</dt><dd>All Churches (47)</dd></div>
          <div><dt>Published</dt><dd>23 Aug 2026</dd></div>
          <div><dt>Expires</dt><dd>25 Aug 2026</dd></div>
          <div><dt>Status</dt><dd><span class="pill pill--published">Published</span></dd></div>
        </dl>
      </section>

      <section class="msec">
        <h3 class="msec__title">Views &mdash; 34 of 47 churches</h3>
        <span class="bar"><i style="width: 72%"></i></span>
        <ul class="picklist">
          <?php foreach (array_slice($churches, 0, 6) as $k => [$in, $name, $code, $acct]): ?>
            <li>
              <span class="church">
                <span class="church__avatar"><?= $in ?></span>
                <span class="church__text">
                  <strong><?= htmlspecialchars($name) ?></strong>
                  <small><?= $code ?> <span class="pill pill--<?= strtolower($acct) ?> pill--xs"><?= $acct ?></span></small>
                </span>
              </span>
              <span class="pill <?= $k < 4 ? 'pill--delivered' : 'pill--draft' ?>"><?= $k < 4 ? 'Viewed' : 'Not yet' ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Close</button>
      <button class="btn btn--primary" type="button" data-modal="modalAnnounce">Edit</button>
    </div>
  </div>
</div>

<!-- c) DELETE -->
<div class="modal" id="modalDeleteAnn" hidden>
  <div class="modal__box modal__box--sm">
    <div class="modal__head">
      <h2><i class="fa-solid fa-trash note--berry"></i> Delete Announcement</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <p class="modal__warn"><i class="fa-solid fa-triangle-exclamation"></i>
        Deleting <strong>Scheduled maintenance — Sunday 02:00 to 04:00</strong> removes it from every church admin panel immediately, along with its view statistics. This cannot be undone.</p>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--danger-solid" type="button">Delete Announcement</button>
    </div>
  </div>
</div>

<script>
/* Bulk bar, dropdowns, modals, and the announcement composer's live preview. */
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
    all.checked = false; refresh();
  });

  document.addEventListener('click', function (e) {
    var trigger = e.target.closest('.dropdown__trigger');
    document.querySelectorAll('.dropdown.is-open').forEach(function (d) {
      if (!trigger || d !== trigger.parentNode) { d.classList.remove('is-open'); }
    });
    if (trigger) { e.preventDefault(); trigger.parentNode.classList.toggle('is-open'); }
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

  /* --- Composer: conditional fields + live preview ------------------- */
  var preview = document.getElementById('annPreview');
  if (!preview) { return; }

  var ICONS = { info: 'fa-circle-info', update: 'fa-arrow-up', warning: 'fa-triangle-exclamation',
                maintenance: 'fa-screwdriver-wrench', feature: 'fa-wand-magic-sparkles' };
  var NOTES = { banner: 'Shown as a strip across the top of the church admin panel.',
                popup:  'Shown as a dialog the first time the admin signs in.',
                notice: 'Listed in the notifications panel with no interruption.' };

  function pick(sel) { var el = document.querySelector(sel + ':checked'); return el || null; }

  function render() {
    var type  = (pick('[data-anntype]')  || {}).dataset ? pick('[data-anntype]').dataset.anntype  : 'info';
    var style = (pick('[data-annstyle]') || {}).dataset ? pick('[data-annstyle]').dataset.annstyle : 'banner';
    preview.dataset.type = type;
    preview.dataset.style = style;
    preview.querySelector('.annpreview__icon').className = 'fa-solid ' + ICONS[type] + ' annpreview__icon';
    document.getElementById('annPvNote').textContent = NOTES[style];
    document.getElementById('annPvTitle').textContent =
      document.getElementById('annTitle').value || 'Untitled announcement';
    document.getElementById('annPvBody').textContent =
      document.getElementById('annBody').value.split('\n')[0] || '';
  }

  ['[data-anntype]', '[data-annstyle]'].forEach(function (sel) {
    document.querySelectorAll(sel).forEach(function (i) { i.addEventListener('change', render); });
  });
  ['annTitle', 'annBody'].forEach(function (id) {
    document.getElementById(id).addEventListener('input', render);
  });

  /* Audience picker, schedule and expiry only appear when they apply. */
  document.querySelectorAll('[data-annaud]').forEach(function (i) {
    i.addEventListener('change', function () {
      document.getElementById('annPick').hidden = i.dataset.annaud !== 'selected' || !i.checked;
    });
  });
  document.querySelectorAll('[data-annwhen]').forEach(function (i) {
    i.addEventListener('change', function () {
      document.getElementById('annSchedule').hidden = !(i.dataset.annwhen === 'later' && i.checked);
    });
  });
  document.getElementById('annExpiryToggle').addEventListener('change', function () {
    document.getElementById('annExpiry').hidden = !this.checked;
  });

  render();
})();
</script>
</body>
</html>

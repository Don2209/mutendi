<?php
/**
 * Mutendi CMS — Activity Log (static UI mockup).
 *
 * Every action taken across the platform, plus the exact database writes
 * behind each one. Trial and Paying churches differ only by badge.
 * Every dataset is hardcoded; each block carries the query that replaces it.
 *
 * SHARED COMPONENT
 * ----------------
 * The "Device & Session Details" modal is defined ONCE here, as device_modal().
 * logins.php and errors.php pull it in with:
 *
 *     $deviceModalOnly = true;
 *     require __DIR__ . '/activity.php';
 *     $deviceModalOnly = false;
 *
 * which returns immediately after the definition below, so the rest of this
 * page never runs for them. One definition, three call sites.
 */

if (!function_exists('device_modal')) {
    /** Read-only technical profile behind an action, login or error. */
    function device_modal(): void { ?>
<div class="modal" id="modalDevice" hidden>
  <div class="modal__box modal__box--lg">
    <div class="modal__head">
      <h2><i class="fa-solid fa-desktop"></i> Device &amp; Session Details</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">

      <section class="msec">
        <h3 class="msec__title">Identity</h3>
        <dl class="summary">
          <div><dt>Full name</dt><dd>Tendai Mabhena</dd></div>
          <div><dt>Email</dt><dd>t.mabhena@zccmbungo.co.zw</dd></div>
          <div><dt>Role</dt><dd><span class="role">Church Admin</span></dd></div>
          <div><dt>Church</dt><dd>ZCC Mbungo (ZCC-001) <span class="pill pill--paying pill--xs">Paying</span></dd></div>
          <div><dt>User ID</dt><dd><code class="keytext">usr_4821</code></dd></div>
          <div><dt>Session ID</dt><dd><code class="keytext">ses_9f34ba21c7</code></dd></div>
        </dl>
      </section>

      <section class="msec">
        <h3 class="msec__title">Connection</h3>
        <dl class="summary">
          <div><dt>IP address</dt><dd><code class="keytext">197.221.44.18</code></dd></div>
          <div><dt>IP type</dt><dd>Residential</dd></div>
          <div><dt>ISP</dt><dd>Liquid Telecom Zimbabwe</dd></div>
          <div><dt>Connection</dt><dd>Fibre broadband</dd></div>
          <div><dt>Hostname</dt><dd><code class="keytext">197-221-44-18.liquidtelecom.co.zw</code></dd></div>
        </dl>
      </section>

      <section class="msec">
        <h3 class="msec__title">Location <span class="msec__note">approximate, from IP</span></h3>
        <dl class="summary">
          <div><dt>City</dt><dd>Masvingo</dd></div>
          <div><dt>Province</dt><dd>Masvingo</dd></div>
          <div><dt>Country</dt><dd>&#127487;&#127484; Zimbabwe</dd></div>
          <div><dt>Coordinates</dt><dd><code class="keytext">-20.0637, 30.8277</code></dd></div>
          <div><dt>Timezone</dt><dd>Africa/Harare (CAT, UTC+2)</dd></div>
        </dl>
        <div class="mapbox"><i class="fa-solid fa-location-dot"></i><span>Approximate location &mdash; Masvingo, Zimbabwe</span></div>
      </section>

      <section class="msec">
        <h3 class="msec__title">Device</h3>
        <dl class="summary">
          <div><dt>Type</dt><dd><i class="fa-solid fa-laptop devicon"></i> Laptop</dd></div>
          <div><dt>Brand &amp; model</dt><dd>HP ProBook 450 G8</dd></div>
          <div><dt>Operating system</dt><dd>Windows 11 (22H2)</dd></div>
          <div><dt>Screen resolution</dt><dd>1920 &times; 1080</dd></div>
          <div><dt>Viewport</dt><dd>1536 &times; 730</dd></div>
          <div><dt>Pixel ratio</dt><dd>1.25</dd></div>
          <div><dt>Colour depth</dt><dd>24-bit</dd></div>
          <div><dt>Touch support</dt><dd><i class="fa-solid fa-xmark yn yn--no"></i> No</dd></div>
          <div><dt>CPU cores</dt><dd>8</dd></div>
          <div><dt>Device memory</dt><dd>8 GB</dd></div>
          <div><dt>Battery</dt><dd>72% (not charging)</dd></div>
        </dl>
      </section>

      <section class="msec">
        <h3 class="msec__title">Browser</h3>
        <dl class="summary">
          <div><dt>Browser</dt><dd>Chrome 128.0.6613.120</dd></div>
          <div><dt>Engine</dt><dd>Blink</dd></div>
          <div><dt>Language</dt><dd>en-GB</dd></div>
          <div><dt>Platform</dt><dd>Win32</dd></div>
          <div><dt>Cookies</dt><dd><i class="fa-solid fa-check yn yn--yes"></i> Enabled</dd></div>
          <div><dt>JavaScript</dt><dd><i class="fa-solid fa-check yn yn--yes"></i> Enabled</dd></div>
          <div><dt>Do Not Track</dt><dd>Not set</dd></div>
        </dl>
        <div class="monobox">
          <pre id="uaString">Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.6613.120 Safari/537.36</pre>
          <button class="btn btn--sm monobox__copy" type="button" data-copy="uaString"><i class="fa-regular fa-copy"></i> Copy</button>
        </div>
      </section>

      <section class="msec">
        <h3 class="msec__title">Session</h3>
        <dl class="summary">
          <div><dt>Login time</dt><dd>25 Aug 2026, 12:04</dd></div>
          <div><dt>Duration</dt><dd>2h 28m</dd></div>
          <div><dt>Pages visited</dt><dd>34</dd></div>
          <div><dt>Last activity</dt><dd>25 Aug 2026, 14:32</dd></div>
          <div><dt>Referrer</dt><dd>Direct</dd></div>
          <div><dt>Entry page</dt><dd><code class="keytext">/login</code></dd></div>
          <div><dt>Impersonated</dt><dd><i class="fa-solid fa-xmark yn yn--no"></i> No</dd></div>
        </dl>
      </section>

      <section class="msec">
        <h3 class="msec__title">Risk Assessment</h3>
        <p class="riskline"><span class="risk risk--normal"><i class="fa-solid fa-shield-halved"></i> Normal</span>
          No unusual signals for this account.</p>
        <div class="minichips">
          <span class="minichip">Known device</span>
          <span class="minichip">Known location</span>
          <span class="minichip">Usual hours</span>
        </div>
      </section>

    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Close</button>
      <button class="btn" type="button"><i class="fa-regular fa-copy"></i> Copy All Details</button>
      <button class="btn" type="button"><i class="fa-solid fa-wave-square"></i> View All Activity from This Device</button>
      <button class="btn btn--danger-solid" type="button"><i class="fa-solid fa-ban"></i> Block IP</button>
    </div>
  </div>
</div>
<?php }
}

/* logins.php and errors.php stop here — they only wanted the modal. */
if (!empty($deviceModalOnly)) {
    return;
}

$musPathsOnly = true;
require __DIR__ . '/../components/sidebar.php';
$musPathsOnly = false;

/* ── TAB 1 headline ────────────────────────────────────────────────────────
   LATER: COUNT(*) over `activity_logs` for the selected window, by action. */
$actTiles = [
    ['Total Events Today', '342', 'indigo', 'fa-wave-square',  true],
    ['Data Changes',       '84',  'brand',  'fa-pen-to-square', false],
    ['Deletions',          '6',   'berry',  'fa-trash',        false],
    ['Impersonations',     '4',   'gold',   'fa-user-secret',  false],
];

/* ── The activity feed ─────────────────────────────────────────────────────
   LATER:
     SELECT a.*, u.name, u.role, c.name AS church, c.code, c.account_type
       FROM activity_logs a
       LEFT JOIN admin_users u ON u.id = a.user_id
       LEFT JOIN churches c ON c.id = a.church_id
      WHERE (:search IS NULL OR a.description LIKE :search OR a.ip LIKE :search)
        AND (:action IS NULL OR a.action = :action)
        AND (:module IS NULL OR a.module = :module)
        AND (:severity IS NULL OR a.severity = :severity)
      ORDER BY a.created_at DESC LIMIT :per_page OFFSET :offset; */
$activity = [
 ['25 Aug 2026','14:32:18','SA','Super Admin','System Owner','','','','Extend','Extended subscription for ZCC Mbungo by 12 months','Churches','Church #12','3 fields changed','197.221.44.18','laptop','Chrome 128 · Windows 11','Info'],
 ['25 Aug 2026','14:20:02','SA','Super Admin','System Owner','','','','Impersonate','Started an impersonated session as Grace Ministries','Churches','Church #3','—','197.221.44.18','laptop','Chrome 128 · Windows 11','Warning'],
 ['25 Aug 2026','14:02:47','TM','Tendai Mabhena','Church Admin','ZCC Mbungo','ZCC-001','Paying','Create','Added 14 members to the Youth department','Members','Member #488','14 records created','197.221.44.18','laptop','Chrome 128 · Windows 11','Info'],
 ['25 Aug 2026','13:48:11','SB','Simba Banda','Pastor','AFM Waterfalls','AFM-002','Paying','Update','Updated the church contact phone number','Churches','Church #2','1 field changed','41.221.16.90','mobile','Safari 17 · iOS 17','Info'],
 ['25 Aug 2026','13:31:55','SA','Super Admin','System Owner','','','','Permission','Changed permissions for the Treasurer role template','Settings','Role #4','6 fields changed','197.221.44.18','laptop','Chrome 128 · Windows 11','Critical'],
 ['25 Aug 2026','13:15:30','LM','Loveness Mhaka','Treasurer','Celebration Church Harare','CCH-005','Paying','Create','Recorded 32 contributions for Sunday service','Finance','Contribution #9021','32 records created','102.130.44.7','desktop','Edge 127 · Windows 10','Info'],
 ['25 Aug 2026','12:58:04','SA','Super Admin','System Owner','','','','Delete','Deleted archived church Harvest Kadoma','Churches','Church #37','Full record','197.221.44.18','laptop','Chrome 128 · Windows 11','Critical'],
 ['25 Aug 2026','12:40:22','MZ','Munashe Zvobgo','Church Admin','Johane Masowe eChishanu','JME-004','Paying','Export','Exported the full member register to CSV','Members','—','—','196.43.132.55','desktop','Chrome 127 · Ubuntu 22.04','Info'],
 ['25 Aug 2026','12:22:19','SA','Super Admin','System Owner','','','','Settings','Enabled the Sermons & Media module for 4 churches','Modules','Module #9','4 fields changed','197.221.44.18','laptop','Chrome 128 · Windows 11','Warning'],
 ['25 Aug 2026','12:04:07','TM','Tendai Mabhena','Church Admin','ZCC Mbungo','ZCC-001','Paying','Login','Signed in successfully','Admins','User #4821','—','197.221.44.18','laptop','Chrome 128 · Windows 11','Info'],
 ['25 Aug 2026','11:47:53','GN','Gilbert Nyathi','Pastor','Methodist Mutare Circuit','MMC-007','Paying','Update','Edited the Sunday service attendance register','Attendance','Register #712','2 fields changed','41.85.220.14','tablet','Chrome 128 · Android 14','Info'],
 ['25 Aug 2026','11:20:38','SA','Super Admin','System Owner','','','','Create','Activated Grace Revival Church on a 12-month subscription','Churches','Church #48','Full record','197.221.44.18','laptop','Chrome 128 · Windows 11','Info'],
 ['25 Aug 2026','10:58:12','CD','Chipo Dube','Department Head','Glory Ministries Gweru','GMG-008','Paying','Login','Failed sign-in — account locked','Admins','User #5510','—','154.120.88.33','mobile','Chrome 128 · Android 13','Warning'],
 ['25 Aug 2026','10:44:29','EM','Edmore Marange','Church Admin','Anglican Diocese Masvingo','ADM-009','Paying','Update','Changed the membership status of 8 members','Members','Member #1204','8 records updated','196.27.104.19','desktop','Firefox 129 · Windows 11','Info'],
 ['25 Aug 2026','10:19:46','SA','Super Admin','System Owner','','','','Settings','Updated the default Trial module set','Settings','Defaults #1','2 fields changed','197.221.44.18','laptop','Chrome 128 · Windows 11','Warning'],
 ['25 Aug 2026','09:55:03','DK','Danai Kanyemba','Department Head','UFIC Chinhoyi','UFI-013','Paying','Delete','Removed a duplicate member record','Members','Member #977','Full record','41.221.19.204','mobile','Safari 17 · iOS 16','Warning'],
 ['25 Aug 2026','09:32:41','AM','Anesu Mabika','Church Admin','New Life Chitungwiza','NLC-061','Trial','Create','Created the Ushers department','Departments','Department #55','Full record','102.130.51.88','laptop','Chrome 128 · Windows 11','Info'],
 ['25 Aug 2026','09:11:27','SA','Super Admin','System Owner','','','','Export','Exported the activation history for July','Finance','—','—','197.221.44.18','laptop','Chrome 128 · Windows 11','Info'],
 ['25 Aug 2026','08:47:58','VN','Vimbai Ncube','Secretary','Rhema Bulawayo','RHB-063','Trial','Logout','Signed out','Admins','User #6033','—','154.120.91.7','mobile','Chrome 128 · Android 14','Info'],
 ['25 Aug 2026','08:20:15','SA','Super Admin','System Owner','','','','Update','Published the maintenance announcement to all churches','Communication','Announcement #18','4 fields changed','197.221.44.18','laptop','Chrome 128 · Windows 11','Info'],
];

/* ── TAB 2 headline ────────────────────────────────────────────────────────
   LATER: COUNT(*) over `data_changes` grouped by operation. */
$changeTiles = [
    ['Records Created', '42', 'green',  'fa-plus',   true],
    ['Records Updated', '36', 'indigo', 'fa-pen',    false],
    ['Records Deleted', '6',  'berry',  'fa-trash',  false],
    ['Tables Affected', '11', 'grey',   'fa-table',  false],
];

/* ── Database writes ───────────────────────────────────────────────────────
   LATER:
     SELECT d.*, u.name, u.role, c.name AS church
       FROM data_changes d
       LEFT JOIN admin_users u ON u.id = d.user_id
       LEFT JOIN churches c ON c.id = d.church_id
      ORDER BY d.created_at DESC; */
$changes = [
 ['25 Aug 2026','14:32:18','UPDATE','churches','#12','ZCC Mbungo','ZCC-001','Paying','Super Admin','System Owner',['expiry_date','status','last_payment_id'],'197.221.44.18'],
 ['25 Aug 2026','14:02:47','INSERT','members','#488','ZCC Mbungo','ZCC-001','Paying','Tendai Mabhena','Church Admin',['full_name','phone','department_id','date_joined','+2 more'],'197.221.44.18'],
 ['25 Aug 2026','13:48:11','UPDATE','churches','#2','AFM Waterfalls','AFM-002','Paying','Simba Banda','Pastor',['phone'],'41.221.16.90'],
 ['25 Aug 2026','13:31:55','UPDATE','role_templates','#4','—','','','Super Admin','System Owner',['permissions','updated_at','+4 more'],'197.221.44.18'],
 ['25 Aug 2026','13:15:30','INSERT','contributions','#9021','Celebration Church Harare','CCH-005','Paying','Loveness Mhaka','Treasurer',['member_id','type_id','amount','recorded_at'],'102.130.44.7'],
 ['25 Aug 2026','12:58:04','DELETE','churches','#37','Harvest Kadoma','HVK-037','Paying','Super Admin','System Owner',['(full record)'],'197.221.44.18'],
 ['25 Aug 2026','12:22:19','UPDATE','church_modules','#204','—','','','Super Admin','System Owner',['enabled','enabled_at','+2 more'],'197.221.44.18'],
 ['25 Aug 2026','11:47:53','UPDATE','attendance','#712','Methodist Mutare Circuit','MMC-007','Paying','Gilbert Nyathi','Pastor',['present_count','notes'],'41.85.220.14'],
 ['25 Aug 2026','11:20:38','INSERT','churches','#48','Grace Revival Church','GRC-048','Paying','Super Admin','System Owner',['name','code','contact_name','expiry_date','+6 more'],'197.221.44.18'],
 ['25 Aug 2026','10:44:29','UPDATE','members','#1204','Anglican Diocese Masvingo','ADM-009','Paying','Edmore Marange','Church Admin',['membership_status'],'196.27.104.19'],
 ['25 Aug 2026','10:19:46','UPDATE','settings','#1','—','','','Super Admin','System Owner',['trial_default_modules','updated_at'],'197.221.44.18'],
 ['25 Aug 2026','09:55:03','DELETE','members','#977','UFIC Chinhoyi','UFI-013','Paying','Danai Kanyemba','Department Head',['(full record)'],'41.221.19.204'],
 ['25 Aug 2026','09:32:41','INSERT','departments','#55','New Life Chitungwiza','NLC-061','Trial','Anesu Mabika','Church Admin',['name','leader_id','created_at'],'102.130.51.88'],
 ['25 Aug 2026','08:20:15','UPDATE','announcements','#18','—','','','Super Admin','System Owner',['status','published_at','audience','+1 more'],'197.221.44.18'],
 ['25 Aug 2026','07:58:22','INSERT','church_admins','#6104','Rhema Bulawayo','RHB-063','Trial','Super Admin','System Owner',['name','email','role','church_id'],'197.221.44.18'],
];

$churchList = ['All Churches','ZCC Mbungo','AFM Waterfalls','Grace Ministries','Johane Masowe eChishanu',
               'Celebration Church Harare','Methodist Mutare Circuit','Anglican Diocese Masvingo',
               'New Life Chitungwiza','Rhema Bulawayo','UFIC Chinhoyi'];
$moduleList = ['All Modules','Churches','Members','Finance','Admins','Modules','Communication','Attendance','Departments','Settings'];
$tableList  = ['All Tables','churches','church_admins','members','contributions','attendance','departments','modules','church_modules','announcements','settings','role_templates'];

$activePage    = 'monitor/activity';
$sidebarBadges = ['pending' => 10, 'expiring' => 12];
$sidebarUser   = ['name' => 'Super Admin', 'role' => 'System Owner'];
$pageTitle     = 'Activity Log';
$pageHint      = 'Every action performed across the platform, with full change history and device details.';

/** Action name to its coloured icon. */
function action_icon(string $a): array {
    $map = ['Create'=>['fa-plus','green'], 'Update'=>['fa-pen','indigo'], 'Delete'=>['fa-trash','berry'],
            'Login'=>['fa-right-to-bracket','grey'], 'Logout'=>['fa-right-from-bracket','grey'],
            'Export'=>['fa-file-export','brand'], 'Impersonate'=>['fa-user-secret','gold'],
            'Settings'=>['fa-sliders','brand'], 'Permission'=>['fa-key','berry'], 'Extend'=>['fa-calendar-plus','green']];
    return $map[$a] ?? ['fa-circle-info','grey'];
}
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
        Monitoring <i class="fa-solid fa-chevron-right"></i> <?= $pageTitle ?>
      </p>
      <p class="page-hint"><?= $pageHint ?></p>
    </div>
    <div class="head-actions">
      <div class="rangebar">
        <div class="range">
          <?php foreach (['Today','7 Days','30 Days','90 Days'] as $r): ?>
            <a href="#" class="range__btn<?= $r === 'Today' ? ' is-on' : '' ?>"><?= $r ?></a>
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
    <button class="tab is-on" type="button" role="tab" data-tab="log">Activity Log</button>
    <button class="tab" type="button" role="tab" data-tab="changes">Data Changes</button>
  </div>

  <!-- ═══════════ TAB 1 — ACTIVITY LOG ═══════════ -->
  <div class="tabpanel" data-panel="log">
    <div class="statstrip">
      <?php foreach ($actTiles as [$l,$v,$t,$ic,$on]): ?>
        <a class="stat-tile stat-tile--<?= $t ?><?= $on ? ' is-on' : '' ?>" href="#">
          <span class="stat-tile__icon"><i class="fa-solid <?= $ic ?>"></i></span>
          <span class="stat-tile__body"><span class="stat-tile__value"><?= $v ?></span>
            <span class="stat-tile__label"><?= $l ?></span></span></a>
      <?php endforeach; ?>
    </div>

    <div class="card filterbar">
      <div class="filterbar__row">
        <label class="field field--search"><span class="field__label">Search</span>
          <span class="field__input"><i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" placeholder="Search by user, church, action or IP..."></span></label>
        <label class="field"><span class="field__label">User Type</span>
          <select><option>All</option><option>Super Admin</option><option>Church Admin</option><option>System</option></select></label>
        <label class="field"><span class="field__label">Church</span>
          <select><?php foreach ($churchList as $c): ?><option><?= $c ?></option><?php endforeach; ?></select></label>
        <label class="field"><span class="field__label">Action Type</span>
          <select><option>All</option><option>Create</option><option>Update</option><option>Delete</option><option>Login</option><option>Logout</option><option>Export</option><option>Impersonate</option><option>Settings Change</option><option>Permission Change</option></select></label>
        <label class="field"><span class="field__label">Module</span>
          <select><?php foreach ($moduleList as $m): ?><option><?= $m ?></option><?php endforeach; ?></select></label>
        <label class="field"><span class="field__label">Severity</span>
          <select><option>All</option><option>Info</option><option>Warning</option><option>Critical</option></select></label>
        <label class="field"><span class="field__label">From</span><input type="date"></label>
        <label class="field"><span class="field__label">To</span><input type="date"></label>
      </div>
      <div class="filterbar__foot">
        <div class="filterbar__actions">
          <button class="btn btn--primary" type="button"><i class="fa-solid fa-filter"></i> Apply Filters</button>
          <a class="link-reset" href="#">Reset</a>
        </div>
        <label class="entries">Show
          <select><?php foreach ([20,50,100] as $n): ?><option><?= $n ?></option><?php endforeach; ?></select> entries</label>
      </div>
    </div>

    <div class="card">
      <div class="table-wrap">
        <table class="table table--churches">
          <thead>
            <tr><th class="col-num">#</th><th>Timestamp</th><th>User</th><th>Church</th><th>Action</th>
              <th>Module</th><th>Record</th><th>Changes</th><th>IP Address</th><th>Device</th>
              <th>Severity</th><th class="col-actions">Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($activity as $i => $a): ?>
              <?php
                [$date,$time,$in,$user,$role,$church,$code,$acct,$action,$desc,$module,$record,$chg,$ip,$dev,$browser,$sev] = $a;
                [$aIcon,$aTone] = action_icon($action);
                $devIcon = ['laptop'=>'fa-laptop','desktop'=>'fa-desktop','mobile'=>'fa-mobile-screen','tablet'=>'fa-tablet-screen-button'][$dev];
              ?>
              <tr>
                <td class="col-num muted"><?= $i + 1 ?></td>
                <td class="nowrap"><span class="stack"><strong><?= $date ?></strong><small><?= $time ?></small></span></td>
                <td class="nowrap">
                  <div class="church">
                    <span class="church__avatar"><?= $in ?></span>
                    <span class="church__text"><strong><?= $user ?></strong><small><?= $role ?></small></span>
                  </div>
                </td>
                <td class="nowrap">
                  <?php if ($church === ''): ?>
                    <span class="role">Super Admin</span>
                  <?php else: ?>
                    <span class="stack"><strong><?= htmlspecialchars($church) ?></strong>
                      <small><?= $code ?> <span class="pill pill--<?= strtolower($acct) ?> pill--xs"><?= $acct ?></span></small></span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="actioncell">
                    <span class="actionicon actionicon--<?= $aTone ?>"><i class="fa-solid <?= $aIcon ?>"></i></span>
                    <span class="actioncell__text"><?= htmlspecialchars($desc) ?></span>
                  </span>
                </td>
                <td><span class="role"><?= $module ?></span></td>
                <td class="muted nowrap"><?= $record ?></td>
                <td class="nowrap">
                  <?php if ($chg === '—'): ?><span class="muted">&mdash;</span>
                  <?php else: ?><button class="chgchip" type="button" data-modal="modalDiff"><?= $chg ?></button><?php endif; ?>
                </td>
                <td><code class="keytext"><?= $ip ?></code></td>
                <td>
                  <button class="devcell" type="button" data-modal="modalDevice">
                    <i class="fa-solid <?= $devIcon ?> devicon"></i>
                    <span><?= $browser ?></span>
                  </button>
                </td>
                <td><span class="pill pill--<?= strtolower($sev) ?>"><?= $sev ?></span></td>
                <td class="col-actions">
                  <div class="row-actions">
                    <button class="ico-btn" type="button" title="View Details" aria-label="View Details" data-modal="modalActDetails"><i class="fa-regular fa-eye"></i></button>
                    <button class="ico-btn" type="button" title="View Changes" aria-label="View Changes" data-modal="modalDiff"><i class="fa-solid fa-code-compare"></i></button>
                    <button class="ico-btn" type="button" title="Device Details" aria-label="Device Details" data-modal="modalDevice"><i class="fa-solid fa-desktop"></i></button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="tablefoot">
        <p class="tablefoot__count">Showing 1 to <?= count($activity) ?> of 342 entries</p>
        <nav class="pagination" aria-label="Pagination">
          <a class="pagination__btn is-disabled" href="#">Previous</a>
          <a class="pagination__btn is-on" href="#">1</a><a class="pagination__btn" href="#">2</a>
          <a class="pagination__btn" href="#">3</a><a class="pagination__btn" href="#">Next</a>
        </nav>
      </div>
    </div>
  </div>

  <!-- ═══════════ TAB 2 — DATA CHANGES ═══════════ -->
  <div class="tabpanel" data-panel="changes" hidden>
    <div class="statstrip">
      <?php foreach ($changeTiles as [$l,$v,$t,$ic,$on]): ?>
        <a class="stat-tile stat-tile--<?= $t ?><?= $on ? ' is-on' : '' ?>" href="#">
          <span class="stat-tile__icon"><i class="fa-solid <?= $ic ?>"></i></span>
          <span class="stat-tile__body"><span class="stat-tile__value"><?= $v ?></span>
            <span class="stat-tile__label"><?= $l ?></span></span></a>
      <?php endforeach; ?>
    </div>

    <div class="card filterbar">
      <div class="filterbar__row">
        <label class="field field--search"><span class="field__label">Search</span>
          <span class="field__input"><i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" placeholder="Search by table, record or user..."></span></label>
        <label class="field"><span class="field__label">Operation</span>
          <select><option>All</option><option>INSERT</option><option>UPDATE</option><option>DELETE</option></select></label>
        <label class="field"><span class="field__label">Table</span>
          <select><?php foreach ($tableList as $t): ?><option><?= $t ?></option><?php endforeach; ?></select></label>
        <label class="field"><span class="field__label">User</span>
          <select><option>All Users</option><option>Super Admin</option><option>Church Admins</option></select></label>
        <label class="field"><span class="field__label">Church</span>
          <select><?php foreach ($churchList as $c): ?><option><?= $c ?></option><?php endforeach; ?></select></label>
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
      <div class="table-wrap">
        <table class="table table--churches">
          <thead>
            <tr><th class="col-num">#</th><th>Timestamp</th><th>Operation</th><th>Table</th><th>Record ID</th>
              <th>Church</th><th>Changed By</th><th>Fields Changed</th><th>IP</th><th class="col-actions">Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($changes as $i => $c): ?>
              <?php [$date,$time,$op,$table,$rec,$church,$code,$acct,$by,$byRole,$fields,$ip] = $c; ?>
              <tr>
                <td class="col-num muted"><?= $i + 1 ?></td>
                <td class="nowrap"><span class="stack"><strong><?= $date ?></strong><small><?= $time ?></small></span></td>
                <td><span class="op op--<?= strtolower($op) ?>"><?= $op ?></span></td>
                <td><code class="keytext"><?= $table ?></code></td>
                <td class="muted nowrap"><?= $rec ?></td>
                <td class="nowrap">
                  <?php if ($church === '—'): ?><span class="muted">&mdash;</span>
                  <?php else: ?>
                    <span class="stack"><strong><?= htmlspecialchars($church) ?></strong>
                      <small><?= $code ?><?php if ($acct): ?> <span class="pill pill--<?= strtolower($acct) ?> pill--xs"><?= $acct ?></span><?php endif; ?></small></span>
                  <?php endif; ?>
                </td>
                <td class="nowrap"><span class="stack"><strong><?= $by ?></strong><small><?= $byRole ?></small></span></td>
                <td>
                  <span class="minichips">
                    <?php foreach ($fields as $f): ?><span class="minichip"><?= $f ?></span><?php endforeach; ?>
                  </span>
                </td>
                <td><code class="keytext"><?= $ip ?></code></td>
                <td class="col-actions">
                  <div class="row-actions">
                    <button class="ico-btn" type="button" title="View Diff" aria-label="View Diff" data-modal="modalDiff"><i class="fa-solid fa-code-compare"></i></button>
                    <button class="ico-btn" type="button" title="Device Details" aria-label="Device Details" data-modal="modalDevice"><i class="fa-solid fa-desktop"></i></button>
                    <?php if ($op === 'DELETE'): ?>
                      <button class="ico-btn" type="button" title="Restore" aria-label="Restore"><i class="fa-solid fa-rotate-left"></i></button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="tablefoot">
        <p class="tablefoot__count">Showing 1 to <?= count($changes) ?> of 84 entries</p>
        <nav class="pagination" aria-label="Pagination">
          <a class="pagination__btn is-disabled" href="#">Previous</a>
          <a class="pagination__btn is-on" href="#">1</a><a class="pagination__btn" href="#">2</a>
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

<!-- ==================== MODALS ==================== -->

<!-- a) VIEW CHANGES / DIFF -->
<div class="modal" id="modalDiff" hidden>
  <div class="modal__box modal__box--lg">
    <div class="modal__head">
      <h2><i class="fa-solid fa-code-compare"></i> Record Changes</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">

      <dl class="summary">
        <div><dt>Table</dt><dd><code class="keytext">churches</code></dd></div>
        <div><dt>Record</dt><dd>#12 &mdash; ZCC Mbungo (ZCC-001)</dd></div>
        <div><dt>Operation</dt><dd><span class="op op--update">UPDATE</span></dd></div>
        <div><dt>When</dt><dd>25 Aug 2026, 14:32:18</dd></div>
        <div><dt>By</dt><dd>Super Admin <span class="role">System Owner</span></dd></div>
        <div><dt>IP</dt><dd><code class="keytext">197.221.44.18</code></dd></div>
      </dl>

      <div class="difftoggles">
        <label class="check-row check-row--slim"><input type="checkbox" id="showUnchanged"><span>Show unchanged fields</span></label>
        <label class="check-row check-row--slim"><input type="checkbox" id="showJson"><span>Raw JSON</span></label>
      </div>

      <!-- Field-by-field comparison -->
      <div class="table-wrap" id="diffTable">
        <table class="table diff">
          <thead><tr><th>Field</th><th>Before</th><th>After</th></tr></thead>
          <tbody>
            <tr>
              <td class="diff__field"><code class="keytext">expiry_date</code></td>
              <td class="diff__old"><s>2026-08-26</s></td>
              <td class="diff__new">2027-08-26</td>
            </tr>
            <tr>
              <td class="diff__field"><code class="keytext">status</code></td>
              <td class="diff__old"><s>expiring</s></td>
              <td class="diff__new">active</td>
            </tr>
            <tr>
              <td class="diff__field"><code class="keytext">last_payment_id</code></td>
              <td class="diff__old"><s>NULL</s></td>
              <td class="diff__new">9184</td>
            </tr>
            <tr class="diff__row--same" hidden>
              <td class="diff__field"><code class="keytext">name</code></td>
              <td class="diff__same">ZCC Mbungo</td><td class="diff__same">ZCC Mbungo</td>
            </tr>
            <tr class="diff__row--same" hidden>
              <td class="diff__field"><code class="keytext">code</code></td>
              <td class="diff__same">ZCC-001</td><td class="diff__same">ZCC-001</td>
            </tr>
            <tr class="diff__row--same" hidden>
              <td class="diff__field"><code class="keytext">account_type</code></td>
              <td class="diff__same">paying</td><td class="diff__same">paying</td>
            </tr>
            <tr class="diff__row--same" hidden>
              <td class="diff__field"><code class="keytext">contact_name</code></td>
              <td class="diff__same">Bishop N. Mutendi</td><td class="diff__same">Bishop N. Mutendi</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Raw JSON view -->
      <div class="jsonpair" id="diffJson" hidden>
        <div class="jsonpane">
          <div class="jsonpane__head"><span>Before</span>
            <button class="btn btn--sm" type="button" data-copy="jsonBefore"><i class="fa-regular fa-copy"></i> Copy</button></div>
          <pre id="jsonBefore">{
  "id": 12,
  "name": "ZCC Mbungo",
  "code": "ZCC-001",
  "account_type": "paying",
  "expiry_date": "2026-08-26",
  "status": "expiring",
  "last_payment_id": null
}</pre>
        </div>
        <div class="jsonpane">
          <div class="jsonpane__head"><span>After</span>
            <button class="btn btn--sm" type="button" data-copy="jsonAfter"><i class="fa-regular fa-copy"></i> Copy</button></div>
          <pre id="jsonAfter">{
  "id": 12,
  "name": "ZCC Mbungo",
  "code": "ZCC-001",
  "account_type": "paying",
  "expiry_date": "2027-08-26",
  "status": "active",
  "last_payment_id": 9184
}</pre>
        </div>
      </div>

      <!-- Shown instead of the diff when the operation was a DELETE. -->
      <p class="notebox">For a <strong>DELETE</strong> the full removed record is shown here in place of the
        comparison, with a <strong>Restore Record</strong> button beside it.</p>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Close</button>
      <button class="btn" type="button" data-modal="modalDevice"><i class="fa-solid fa-desktop"></i> Device Details</button>
      <button class="btn btn--primary" type="button"><i class="fa-regular fa-copy"></i> Copy Diff</button>
    </div>
  </div>
</div>

<!-- b) ACTIVITY DETAILS -->
<div class="modal" id="modalActDetails" hidden>
  <div class="modal__box">
    <div class="modal__head">
      <h2><i class="fa-regular fa-eye"></i> Activity Details</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <p class="summary-line"><i class="fa-solid fa-calendar-plus"></i>
        Extended subscription for ZCC Mbungo by 12 months</p>
      <section class="msec">
        <h3 class="msec__title">Context</h3>
        <dl class="summary">
          <div><dt>User</dt><dd>Super Admin <span class="role">System Owner</span></dd></div>
          <div><dt>Church</dt><dd>ZCC Mbungo (ZCC-001) <span class="pill pill--paying pill--xs">Paying</span></dd></div>
          <div><dt>Module</dt><dd><span class="role">Churches</span></dd></div>
          <div><dt>Record affected</dt><dd>Church #12</dd></div>
          <div><dt>Timestamp</dt><dd>25 Aug 2026, 14:32:18</dd></div>
          <div><dt>Severity</dt><dd><span class="pill pill--info">Info</span></dd></div>
        </dl>
      </section>
      <section class="msec">
        <h3 class="msec__title">Request</h3>
        <dl class="summary">
          <div><dt>Method</dt><dd><code class="keytext">POST</code></dd></div>
          <div><dt>Endpoint</dt><dd><code class="keytext">/mus/churches/extend</code></dd></div>
          <div><dt>Execution time</dt><dd>184 ms</dd></div>
          <div><dt>IP</dt><dd><code class="keytext">197.221.44.18</code></dd></div>
        </dl>
      </section>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Close</button>
      <button class="btn" type="button" data-modal="modalDiff"><i class="fa-solid fa-code-compare"></i> View Changes</button>
      <button class="btn btn--primary" type="button" data-modal="modalDevice"><i class="fa-solid fa-desktop"></i> Device Details</button>
    </div>
  </div>
</div>

<?php device_modal(); ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
/* Shared chrome: custom range, dropdowns, tabs, modals, copy buttons,
   per-tab bulk bars and the diff modal's toggles. */
(function () {
  'use strict';
  var custom = document.getElementById('rangeCustom');
  if (custom) {
    custom.addEventListener('click', function () {
      var d = document.getElementById('rangeDates');
      d.hidden = !d.hidden; custom.classList.toggle('is-on', !d.hidden);
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

  /* Copy buttons on any monospace box. */
  document.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var src = document.getElementById(btn.dataset.copy);
      if (!src) { return; }
      try { navigator.clipboard.writeText(src.textContent.trim()); } catch (e) {}
      var was = btn.innerHTML;
      btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied';
      setTimeout(function () { btn.innerHTML = was; }, 1400);
    });
  });

  /* Diff modal: unchanged rows and raw JSON are both opt-in. */
  var showUnchanged = document.getElementById('showUnchanged');
  if (showUnchanged) {
    var apply = function () {
      document.querySelectorAll('.diff__row--same').forEach(function (r) { r.hidden = !showUnchanged.checked; });
    };
    showUnchanged.addEventListener('change', apply); apply();
  }
  var showJson = document.getElementById('showJson');
  if (showJson) {
    var applyJson = function () {
      document.getElementById('diffTable').hidden = showJson.checked;
      document.getElementById('diffJson').hidden = !showJson.checked;
    };
    showJson.addEventListener('change', applyJson); applyJson();
  }

  /* Bulk bars keyed by name. */
  document.querySelectorAll('[data-checkall]').forEach(function (all) {
    var key = all.dataset.checkall,
        rows = [].slice.call(document.querySelectorAll('[data-rowcheck="' + key + '"]')),
        bar = document.querySelector('[data-bulkbar="' + key + '"]'),
        count = document.querySelector('[data-bulkcount="' + key + '"]'),
        clear = document.querySelector('[data-bulkclear="' + key + '"]');
    function refresh() {
      var n = rows.filter(function (c) { return c.checked; }).length;
      count.textContent = n; bar.hidden = n === 0;
      all.checked = n === rows.length && n > 0;
      all.indeterminate = n > 0 && n < rows.length;
    }
    all.addEventListener('change', function () {
      rows.forEach(function (c) { c.checked = all.checked; }); refresh();
    });
    rows.forEach(function (c) { c.addEventListener('change', refresh); });
    if (clear) { clear.addEventListener('click', function () {
      rows.forEach(function (c) { c.checked = false; }); all.checked = false; refresh();
    }); }
  });
})();
</script>
</body>
</html>

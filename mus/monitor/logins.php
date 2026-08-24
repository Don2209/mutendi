<?php
/**
 * Mutendi CMS — Login History (static UI mockup).
 *
 * Every login attempt with device and location forensics. Every dataset is
 * hardcoded; each block carries the query that replaces it.
 */

/* The Device & Session Details modal is defined once in activity.php.
   This pulls in just that definition and returns before the rest of it runs. */
$deviceModalOnly = true;
require __DIR__ . '/activity.php';
$deviceModalOnly = false;

$musPathsOnly = true;
require __DIR__ . '/../components/sidebar.php';
$musPathsOnly = false;

/* ── Headline ──────────────────────────────────────────────────────────────
   LATER: COUNT(*) over `login_history` for the window, by result. */
$tiles = [
    ['Logins Today',          '128', 'indigo', 'fa-right-to-bracket', true],
    ['Failed Attempts',       '14',  'berry',  'fa-circle-xmark',     false],
    ['Unique Devices',        '96',  'brand',  'fa-desktop',          false],
    ['Flagged as Suspicious', '3',   'gold',   'fa-shield-halved',    false],
];

/* ── Login activity, last 30 days ──────────────────────────────────────────
   LATER: daily COUNT(*) split by result = 'success' / 'failed'. */
$days = [];
for ($d = 30; $d >= 1; $d--) { $days[] = $d . 'd'; }
$okLogins   = [96,112,88,131,124,140,158,118,102,127,144,152,114,108,133,162,150,122,116,139,157,129,119,134,168,152,124,113,147,172];
$badLogins  = [4,6,3,9,7,11,14,5,4,8,10,12,6,5,9,15,13,7,6,10,12,8,6,9,16,13,7,5,11,14];

/* ── Devices used ──────────────────────────────────────────────────────────
   LATER: SELECT device_type, COUNT(DISTINCT device_hash) GROUP BY device_type; */
$devices = [['Desktop',54,'#662F97'],['Mobile',33,'#1E8265'],['Tablet',9,'#5A57B5']];
$devTotal = array_sum(array_column($devices, 1));

/* ── Login attempts ────────────────────────────────────────────────────────
   LATER:
     SELECT h.*, u.name, u.email, c.name AS church, c.code, c.account_type
       FROM login_history h
       LEFT JOIN church_admins u ON u.id = h.user_id
       LEFT JOIN churches c ON c.id = h.church_id
      ORDER BY h.created_at DESC LIMIT :per_page OFFSET :offset; */
$logins = [
 ['25 Aug 2026','14:04:12','TM','Tendai Mabhena','t.mabhena@zccmbungo.co.zw','ZCC Mbungo','ZCC-001','Paying','Success','197.221.44.18','Liquid Telecom','Masvingo','Masvingo','laptop','HP ProBook 450','Chrome 128 · Windows 11','2h 28m','Normal'],
 ['25 Aug 2026','13:52:40','SA','Super Admin','admin@mutendi.co.zw','','','','Success','197.221.44.18','Liquid Telecom','Harare','Harare','laptop','Dell Latitude 5420','Chrome 128 · Windows 11','3h 10m','Normal'],
 ['25 Aug 2026','13:41:07','SB','Simba Banda','s.banda@afmwaterfalls.org','AFM Waterfalls','AFM-002','Paying','Success','41.221.16.90','Econet Wireless','Harare','Harare','mobile','iPhone 13','Safari 17 · iOS 17','48 min','Normal'],
 ['25 Aug 2026','13:12:55','CD','Chipo Dube','c.dube@glorygweru.co.zw','Glory Ministries Gweru','GMG-008','Paying','Failed','154.120.88.33','NetOne','Gweru','Midlands','mobile','Tecno Spark 10','Chrome 128 · Android 13','—','Unusual'],
 ['25 Aug 2026','13:12:31','CD','Chipo Dube','c.dube@glorygweru.co.zw','Glory Ministries Gweru','GMG-008','Paying','Failed','154.120.88.33','NetOne','Gweru','Midlands','mobile','Tecno Spark 10','Chrome 128 · Android 13','—','Unusual'],
 ['25 Aug 2026','13:12:04','CD','Chipo Dube','c.dube@glorygweru.co.zw','Glory Ministries Gweru','GMG-008','Paying','Locked Out','154.120.88.33','NetOne','Gweru','Midlands','mobile','Tecno Spark 10','Chrome 128 · Android 13','—','Suspicious'],
 ['25 Aug 2026','12:47:19','LM','Loveness Mhaka','l.mhaka@celebration.co.zw','Celebration Church Harare','CCH-005','Paying','Success','102.130.44.7','TelOne','Harare','Harare','desktop','Custom build','Edge 127 · Windows 10','1h 52m','Normal'],
 ['25 Aug 2026','12:19:33','MZ','Munashe Zvobgo','m.zvobgo@jmasowe.co.zw','Johane Masowe eChishanu','JME-004','Paying','Success','196.43.132.55','ZOL Zimbabwe','Gweru','Midlands','desktop','Lenovo ThinkCentre','Chrome 127 · Ubuntu 22.04','2h 04m','Normal'],
 ['25 Aug 2026','11:58:02','—','Unknown','admin@zccmbungo.co.zw','ZCC Mbungo','ZCC-001','Paying','Failed','185.220.101.44','Unknown (VPN)','Amsterdam','—','desktop','Unknown','Firefox 128 · Linux','—','Suspicious'],
 ['25 Aug 2026','11:36:44','GN','Gilbert Nyathi','g.nyathi@methodistmutare.org','Methodist Mutare Circuit','MMC-007','Paying','Success','41.85.220.14','Econet Wireless','Mutare','Manicaland','tablet','Samsung Galaxy Tab A8','Chrome 128 · Android 14','36 min','Normal'],
 ['25 Aug 2026','11:14:28','EM','Edmore Marange','e.marange@anglicanmasvingo.org','Anglican Diocese Masvingo','ADM-009','Paying','Success','196.27.104.19','TelOne','Masvingo','Masvingo','desktop','HP EliteDesk','Firefox 129 · Windows 11','1h 18m','Normal'],
 ['25 Aug 2026','10:52:11','AM','Anesu Mabika','a.mabika@newlife.co.zw','New Life Chitungwiza','NLC-061','Trial','Success','102.130.51.88','TelOne','Chitungwiza','Harare','laptop','Acer Aspire 5','Chrome 128 · Windows 11','54 min','Normal'],
 ['25 Aug 2026','10:31:47','DK','Danai Kanyemba','d.kanyemba@ufic-chinhoyi.org','UFIC Chinhoyi','UFI-013','Paying','Success','41.221.19.204','Econet Wireless','Chinhoyi','Mash. West','mobile','Samsung Galaxy A54','Safari 17 · iOS 16','22 min','Normal'],
 ['25 Aug 2026','10:08:19','JS','Joseph Sibanda','j.sibanda@sdahwange.org','SDA Hwange','SDA-014','Paying','Blocked','154.120.77.19','NetOne','Hwange','Mat. North','mobile','Itel A60','Chrome 127 · Android 12','—','Unusual'],
 ['25 Aug 2026','09:44:52','VN','Vimbai Ncube','v.ncube@rhemabyo.org','Rhema Bulawayo','RHB-063','Trial','Success','154.120.91.7','NetOne','Bulawayo','Bulawayo','mobile','Huawei Nova 9','Chrome 128 · Android 14','1h 06m','Normal'],
 ['25 Aug 2026','09:22:30','SA','Super Admin','admin@mutendi.co.zw','','','','Success','197.221.44.18','Liquid Telecom','Harare','Harare','laptop','Dell Latitude 5420','Chrome 128 · Windows 11','4h 22m','Normal'],
 ['25 Aug 2026','08:59:14','RC','Rutendo Chikore','r.chikore@graceministries.co.zw','Grace Ministries','GRM-003','Paying','Failed','196.27.88.140','TelOne','Chitungwiza','Harare','laptop','Lenovo IdeaPad','Chrome 128 · Windows 10','—','Normal'],
 ['25 Aug 2026','08:36:41','BC','Blessing Chidziva','b.chidziva@stthomas.org','St Thomas Mutare','STM-064','Trial','Success','41.85.211.66','Econet Wireless','Mutare','Manicaland','mobile','Tecno Camon 20','Chrome 128 · Android 13','18 min','Normal'],
 ['25 Aug 2026','08:12:07','GA','Grace Adeyemi','g.adeyemi@zccmbungo.co.zw','ZCC Mbungo','ZCC-001','Paying','Success','197.221.44.22','Liquid Telecom','Masvingo','Masvingo','desktop','Dell OptiPlex','Chrome 128 · Windows 11','2h 41m','Normal'],
 ['25 Aug 2026','07:48:55','PN','Peter Ndlovu','p.ndlovu@fogbulawayo.org','Family of God Bulawayo','FOG-006','Trial','Success','196.43.117.28','ZOL Zimbabwe','Bulawayo','Bulawayo','laptop','HP Pavilion 15','Edge 127 · Windows 11','1h 33m','Normal'],
];

/* ── Failed attempts grouped by IP ─────────────────────────────────────────
   LATER: SELECT ip, COUNT(*) n, MAX(created_at) FROM login_history
          WHERE result = 'failed' GROUP BY ip ORDER BY n DESC; */
$failedIps = [
 ['154.120.88.33','NetOne','Gweru',3,'13:12:55'],
 ['185.220.101.44','Unknown (VPN)','Amsterdam',5,'11:58:02'],
 ['196.27.88.140','TelOne','Chitungwiza',2,'08:59:14'],
 ['41.85.199.4','Econet Wireless','Rusape',2,'07:20:18'],
 ['102.130.61.90','TelOne','Harare',2,'06:44:02'],
];

/* ── Devices seen for the first time ───────────────────────────────────────
   LATER: rows from `login_history` whose device_hash had no prior match. */
$newDevices = [
 ['GN','Gilbert Nyathi','Methodist Mutare Circuit','Samsung Galaxy Tab A8 · Android 14','Mutare, Manicaland','11:36'],
 ['BC','Blessing Chidziva','St Thomas Mutare','Tecno Camon 20 · Android 13','Mutare, Manicaland','08:36'],
 ['PN','Peter Ndlovu','Family of God Bulawayo','HP Pavilion 15 · Windows 11','Bulawayo','07:48'],
 ['AM','Anesu Mabika','New Life Chitungwiza','Acer Aspire 5 · Windows 11','Chitungwiza, Harare','10:52'],
 ['DK','Danai Kanyemba','UFIC Chinhoyi','Samsung Galaxy A54 · iOS 16','Chinhoyi, Mash. West','10:31'],
];

$churchList = ['All Churches','ZCC Mbungo','AFM Waterfalls','Grace Ministries','Johane Masowe eChishanu',
               'Celebration Church Harare','Methodist Mutare Circuit','Anglican Diocese Masvingo',
               'New Life Chitungwiza','Rhema Bulawayo','UFIC Chinhoyi'];

$activePage    = 'monitor/logins';
$sidebarBadges = ['pending' => 10, 'expiring' => 12];
$sidebarUser   = ['name' => 'Super Admin', 'role' => 'System Owner'];
$pageTitle     = 'Login History';
$pageHint      = 'All login attempts across the platform, with device and location details.';
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

  <div class="statstrip">
    <?php foreach ($tiles as [$l,$v,$t,$ic,$on]): ?>
      <a class="stat-tile stat-tile--<?= $t ?><?= $on ? ' is-on' : '' ?>" href="#">
        <span class="stat-tile__icon"><i class="fa-solid <?= $ic ?>"></i></span>
        <span class="stat-tile__body"><span class="stat-tile__value"><?= $v ?></span>
          <span class="stat-tile__label"><?= $l ?></span></span></a>
    <?php endforeach; ?>
  </div>

  <div class="grid grid--2-1">
    <div class="card card--chartfill">
      <div class="card__head"><h2>Login Activity</h2><span class="card__note">Last 30 days</span></div>
      <div class="card__body"><div class="chart-wrap"><canvas id="loginChart"></canvas></div></div>
    </div>
    <div class="card">
      <div class="card__head"><h2>Devices Used</h2></div>
      <div class="card__body">
        <div class="chart-wrap chart-wrap--donut"><canvas id="deviceChart"></canvas></div>
        <ul class="legend">
          <?php foreach ($devices as [$l,$c,$col]): ?>
            <li><span class="legend__dot" style="background: <?= $col ?>"></span>
              <span class="legend__label"><?= $l ?></span>
              <span class="legend__count"><?= $c ?> &middot; <?= round($c / $devTotal * 100) ?>%</span></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>

  <div class="card filterbar">
    <div class="filterbar__row">
      <label class="field field--search"><span class="field__label">Search</span>
        <span class="field__input"><i class="fa-solid fa-magnifying-glass"></i>
          <input type="search" placeholder="Search by name, email, IP or device..."></span></label>
      <label class="field"><span class="field__label">Result</span>
        <select><option>All</option><option>Success</option><option>Failed</option><option>Blocked</option><option>Locked Out</option></select></label>
      <label class="field"><span class="field__label">User Type</span>
        <select><option>All</option><option>Super Admin</option><option>Church Admin</option></select></label>
      <label class="field"><span class="field__label">Church</span>
        <select><?php foreach ($churchList as $c): ?><option><?= $c ?></option><?php endforeach; ?></select></label>
      <label class="field"><span class="field__label">Device Type</span>
        <select><option>All</option><option>Desktop</option><option>Mobile</option><option>Tablet</option></select></label>
      <label class="field"><span class="field__label">Browser</span>
        <select><option>All</option><option>Chrome</option><option>Safari</option><option>Firefox</option><option>Edge</option></select></label>
      <label class="field"><span class="field__label">Location</span>
        <select><option>All Provinces</option><option>Harare</option><option>Bulawayo</option><option>Manicaland</option><option>Midlands</option><option>Masvingo</option><option>Mash. West</option><option>Mat. North</option></select></label>
      <label class="field"><span class="field__label">Risk</span>
        <select><option>All</option><option>Normal</option><option>Unusual</option><option>Suspicious</option></select></label>
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
          <tr><th class="col-num">#</th><th>Timestamp</th><th>User</th><th>Church</th><th>Result</th>
            <th>IP Address</th><th>Location</th><th>Device</th><th>Browser &amp; OS</th>
            <th>Session</th><th>Risk</th><th class="col-actions">Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($logins as $i => $r): ?>
            <?php
              [$date,$time,$in,$user,$email,$church,$code,$acct,$result,$ip,$isp,$city,$prov,$dev,$model,$browser,$dur,$risk] = $r;
              $failed  = in_array($result, ['Failed','Blocked','Locked Out'], true);
              $devIcon = ['laptop'=>'fa-laptop','desktop'=>'fa-desktop','mobile'=>'fa-mobile-screen','tablet'=>'fa-tablet-screen-button'][$dev];
            ?>
            <tr>
              <td class="col-num muted"><?= $i + 1 ?></td>
              <td class="nowrap"><span class="stack"><strong><?= $date ?></strong><small><?= $time ?></small></span></td>
              <td>
                <div class="church">
                  <span class="church__avatar"><?= $in === '—' ? '?' : $in ?></span>
                  <span class="church__text">
                    <strong><?= $failed && $in === '—' ? 'Unknown' : htmlspecialchars($user) ?></strong>
                    <small class="<?= $failed ? 'attempted' : '' ?>"><?= htmlspecialchars($email) ?></small>
                  </span>
                </div>
              </td>
              <td class="nowrap">
                <?php if ($church === ''): ?><span class="role">Super Admin</span>
                <?php else: ?>
                  <span class="stack"><strong><?= htmlspecialchars($church) ?></strong>
                    <small><?= $code ?> <span class="pill pill--<?= strtolower($acct) ?> pill--xs"><?= $acct ?></span></small></span>
                <?php endif; ?>
              </td>
              <td><span class="pill pill--<?= strtolower(str_replace(' ', '-', $result)) ?>"><?= $result ?></span></td>
              <td class="nowrap"><span class="stack"><code class="keytext"><?= $ip ?></code><small><?= $isp ?></small></span></td>
              <td class="nowrap"><span class="stack"><strong><?= $city ?></strong><small><?= $prov ?></small></span></td>
              <td>
                <button class="devcell" type="button" data-modal="modalDevice">
                  <i class="fa-solid <?= $devIcon ?> devicon"></i><span><?= $model ?></span>
                </button>
              </td>
              <td class="nowrap muted"><?= $browser ?></td>
              <td class="nowrap <?= $dur === '—' ? 'muted' : '' ?>"><?= $dur ?></td>
              <td><span class="risk risk--<?= strtolower($risk) ?>"><?= $risk ?></span></td>
              <td class="col-actions">
                <div class="row-actions">
                  <button class="ico-btn" type="button" title="Device Details" aria-label="Device Details" data-modal="modalDevice"><i class="fa-solid fa-desktop"></i></button>
                  <button class="ico-btn" type="button" title="View Activity" aria-label="View Activity" data-modal="modalSession"><i class="fa-solid fa-wave-square"></i></button>
                  <button class="ico-btn" type="button" title="Block IP" aria-label="Block IP" data-modal="modalBlock"><i class="fa-solid fa-ban"></i></button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="tablefoot">
      <p class="tablefoot__count">Showing 1 to <?= count($logins) ?> of 128 entries</p>
      <nav class="pagination" aria-label="Pagination">
        <a class="pagination__btn is-disabled" href="#">Previous</a>
        <a class="pagination__btn is-on" href="#">1</a><a class="pagination__btn" href="#">2</a>
        <a class="pagination__btn" href="#">3</a><a class="pagination__btn" href="#">Next</a>
      </nav>
    </div>
  </div>

  <div class="grid grid--2">
    <div class="card">
      <div class="card__head"><h2>Failed Attempts by IP</h2></div>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>IP Address</th><th>Location</th><th class="ta-right">Attempts</th><th>Last</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($failedIps as [$ip,$isp,$loc,$n,$last]): ?>
              <tr>
                <td><span class="stack"><code class="keytext keytext--strong"><?= $ip ?></code><small><?= $isp ?></small></span></td>
                <td class="muted nowrap"><?= $loc ?></td>
                <td class="ta-right strong"><?= $n ?></td>
                <td class="muted nowrap"><?= $last ?></td>
                <td class="ta-right"><button class="btn btn--sm btn--danger" type="button" data-modal="modalBlock">Block</button></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card__head"><h2>Recently Seen New Devices</h2></div>
      <ul class="attn">
        <?php foreach ($newDevices as [$in,$user,$church,$device,$loc,$time]): ?>
          <li class="attn__row">
            <span class="church__avatar"><?= $in ?></span>
            <span class="attn__text">
              <strong><?= htmlspecialchars($user) ?> &middot; <?= htmlspecialchars($church) ?></strong>
              <small><?= $device ?> &middot; <?= $loc ?> &middot; <?= $time ?></small>
            </span>
            <button class="btn btn--sm" type="button" data-modal="modalDevice">Review</button>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
  <footer class="foot">
    <p class="foot__brand">Mutendi CMS</p>
    <p class="foot__copy">&copy; <?= date('Y') ?> Mutendi. All rights reserved.</p>
    <p class="foot__meta">Super Admin &middot; v1.0</p>
  </footer>

</main>

<div class="modal" id="modalBlock" hidden>
  <div class="modal__box modal__box--sm">
    <div class="modal__head">
      <h2><i class="fa-solid fa-ban note--berry"></i> Block IP Address</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <p class="modal__lead">Blocking <code class="keytext">154.120.88.33</code> (NetOne, Gweru). Anyone on this address will be unable to reach the platform.</p>
      <label class="field"><span class="field__label">Reason</span>
        <select><option>Repeated failed logins</option><option>Suspected credential stuffing</option><option>VPN or proxy</option><option>Reported by a church</option><option>Other</option></select></label>
      <span class="field__label">Duration</span>
      <div class="radios">
        <?php foreach (['24 hours','7 days','30 days','Permanent'] as $i => $d): ?>
          <label class="radio"><input type="radio" name="blockdur"<?= $i === 0 ? ' checked' : '' ?>><span><?= $d ?></span></label>
        <?php endforeach; ?>
      </div>
      <label class="field"><span class="field__label">Notes</span>
        <textarea rows="3" placeholder="Anything worth recording..."></textarea></label>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--danger-solid" type="button">Block IP</button>
    </div>
  </div>
</div>

<div class="modal" id="modalSession" hidden>
  <div class="modal__box">
    <div class="modal__head">
      <h2><i class="fa-solid fa-wave-square"></i> Session Activity</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <dl class="summary">
        <div><dt>User</dt><dd>Tendai Mabhena</dd></div>
        <div><dt>Church</dt><dd>ZCC Mbungo (ZCC-001) <span class="pill pill--paying pill--xs">Paying</span></dd></div>
        <div><dt>Session</dt><dd><code class="keytext">ses_9f34ba21c7</code></dd></div>
        <div><dt>Duration</dt><dd>2h 28m &middot; 34 pages</dd></div>
      </dl>
      <section class="msec">
        <h3 class="msec__title">Timeline</h3>
        <ul class="feed feed--flat">
          <?php foreach ([['Signed in from Masvingo','12:04','blue'],
                          ['Opened the member register','12:06','blue'],
                          ['Added 14 members to Youth','14:02','green'],
                          ['Exported the attendance report','14:18','blue'],
                          ['Updated the church phone number','14:26','amber'],
                          ['Last activity recorded','14:32','blue']] as [$txt,$t,$tone]): ?>
            <li class="feed__row"><span class="dot dot--<?= $tone ?>"></span>
              <span class="feed__text"><?= $txt ?><small><?= $t ?></small></span></li>
          <?php endforeach; ?>
        </ul>
      </section>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Close</button>
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
<script>
(function () {
  var grid = 'rgba(102,47,151,.10)', tick = '#9A93A6';
  new Chart(document.getElementById('loginChart'), {
    type: 'line',
    data: { labels: <?= json_encode($days) ?>,
      datasets: [
        { label: 'Successful logins', data: <?= json_encode($okLogins) ?>,
          borderColor: '#1E8265', backgroundColor: '#1E8265', borderWidth: 2, tension: .35, pointRadius: 0 },
        { label: 'Failed attempts', data: <?= json_encode($badLogins) ?>,
          borderColor: '#A93254', backgroundColor: '#A93254', borderWidth: 2, tension: .35, pointRadius: 0 }
      ] },
    options: { responsive: true, maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true, color: tick } } },
      scales: { x: { grid: { display: false }, ticks: { color: tick, maxTicksLimit: 10 } },
                y: { beginAtZero: true, grid: { color: grid }, ticks: { color: tick } } } }
  });
  new Chart(document.getElementById('deviceChart'), {
    type: 'doughnut',
    data: { labels: <?= json_encode(array_column($devices, 0)) ?>,
      datasets: [{ data: <?= json_encode(array_column($devices, 1)) ?>,
        backgroundColor: <?= json_encode(array_column($devices, 2)) ?>, borderWidth: 0, hoverOffset: 6 }] },
    options: { responsive: true, maintainAspectRatio: false, cutout: '64%', plugins: { legend: { display: false } } }
  });
})();
</script>
</body>
</html>

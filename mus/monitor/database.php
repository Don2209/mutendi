<?php
/**
 * Mutendi CMS — Database Health (static UI mockup).
 *
 * Size, growth and query performance of the platform database. Every dataset
 * below is hardcoded; each block carries the query that will replace it.
 */

$musPathsOnly = true;
require __DIR__ . '/../components/sidebar.php';
$musPathsOnly = false;

/* ── Headline ──────────────────────────────────────────────────────────────
   LATER: SELECT SUM(data_length + index_length), COUNT(*) FROM information_schema.TABLES
          WHERE table_schema = DATABASE(); */
$tiles = [
    ['Database Size',   '2.4 GB', 'brand',  'fa-database',      true],
    ['Total Tables',    '42',     'indigo', 'fa-table-list',    false],
    ['Total Records',   '1.28 M', 'green',  'fa-layer-group',   false],
    ['Growth / Month',  '+184 MB','gold',   'fa-arrow-trend-up',false],
];

/* ── Live status indicators ────────────────────────────────────────────────
   LATER: SHOW GLOBAL STATUS / SHOW VARIABLES, plus the backup job's own table. */
$status = [
    ['Connection',      'Healthy',  'ok',   'fa-plug',            '3 ms response'],
    ['Query Cache',     'Enabled',  'ok',   'fa-bolt',            '94.2% hit rate'],
    ['Last Backup',     '4h ago',   'ok',   'fa-cloud-arrow-up',  '25 Aug, 02:00'],
    ['Disk Space',      '62% used', 'warn', 'fa-hard-drive',      '2.4 GB of 4 GB'],
    ['Slow Queries',    '7 today',  'warn', 'fa-gauge-high',      'threshold 1.0 s'],
    ['Replication',     'In sync',  'ok',   'fa-code-branch',     'lag 0 s'],
];

/* ── Database growth, 12 months ────────────────────────────────────────────
   LATER: monthly snapshots from `db_size_history`. */
$months  = ['Sep','Oct','Nov','Dec','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug'];
$dbSize  = [0.61,0.74,0.88,1.02,1.21,1.38,1.54,1.72,1.90,2.08,2.24,2.40];

/* ── Storage split by data kind ────────────────────────────────────────────
   LATER: group the information_schema table sizes by their module prefix. */
$storage = [['Members & Families',920,'#662F97'],['Attendance',540,'#5A57B5'],
            ['Activity & Logs',380,'#8C8398'],['Contributions',260,'#1E8265'],
            ['Documents & Media',190,'#96701F'],['Everything Else',110,'#B48FDA']];
$storeTotal = array_sum(array_column($storage, 1));

/* ── Table statistics ──────────────────────────────────────────────────────
   LATER:
     SELECT table_name, table_rows, data_length, index_length, engine, update_time
       FROM information_schema.TABLES
      WHERE table_schema = DATABASE()
      ORDER BY (data_length + index_length) DESC; */
$tables = [
 ['members',              '412,880','486 MB','128 MB','InnoDB','25 Aug 2026, 14:41','2%',  'Healthy'],
 ['attendance_records',   '386,214','402 MB','96 MB', 'InnoDB','25 Aug 2026, 14:38','4%',  'Healthy'],
 ['activity_logs',        '298,540','244 MB','52 MB', 'InnoDB','25 Aug 2026, 14:41','18%', 'Needs Optimising'],
 ['contributions',        '132,908','168 MB','44 MB', 'InnoDB','25 Aug 2026, 13:22','3%',  'Healthy'],
 ['login_history',        '118,442','92 MB', '31 MB', 'InnoDB','25 Aug 2026, 14:40','21%', 'Needs Optimising'],
 ['families',             '96,310', '74 MB', '18 MB', 'InnoDB','25 Aug 2026, 12:04','1%',  'Healthy'],
 ['sacramental_records',  '68,772', '66 MB', '20 MB', 'InnoDB','25 Aug 2026, 11:48','2%',  'Healthy'],
 ['documents',            '24,506', '58 MB', '9 MB',  'InnoDB','24 Aug 2026, 19:12','6%',  'Healthy'],
 ['error_logs',           '41,220', '36 MB', '12 MB', 'InnoDB','25 Aug 2026, 14:41','27%', 'Needs Optimising'],
 ['sms_messages',         '52,118', '31 MB', '8 MB',  'InnoDB','25 Aug 2026, 10:55','5%',  'Healthy'],
 ['departments',          '3,204',  '12 MB', '3 MB',  'InnoDB','24 Aug 2026, 16:30','0%',  'Healthy'],
 ['churches',             '132',    '4 MB',  '1 MB',  'InnoDB','25 Aug 2026, 09:14','0%',  'Healthy'],
 ['admin_users',          '318',    '3 MB',  '1 MB',  'InnoDB','25 Aug 2026, 14:12','1%',  'Healthy'],
 ['announcements',        '486',    '2 MB',  '1 MB',  'InnoDB','24 Aug 2026, 15:02','0%',  'Healthy'],
 ['sessions',             '2,940',  '2 MB',  '1 MB',  'InnoDB','25 Aug 2026, 14:41','34%', 'Needs Optimising'],
];

/* ── Storage by church ─────────────────────────────────────────────────────
   LATER: per-tenant row counts and byte totals, ORDER BY bytes DESC LIMIT 10. */
$byChurch = [
 ['ZCC Mbungo','ZCC-001','Paying','Masvingo','18,420','412 MB',17],
 ['Johane Masowe eChishanu','JME-004','Paying','Harare','14,880','338 MB',14],
 ['AFM Waterfalls','AFM-002','Paying','Harare','11,240','268 MB',11],
 ['Anglican Diocese Masvingo','ADM-009','Paying','Masvingo','9,610','221 MB',9],
 ['Celebration Church Harare','CCH-005','Paying','Harare','8,470','196 MB',8],
 ['Methodist Mutare Circuit','MMC-007','Paying','Manicaland','7,120','164 MB',7],
 ['Grace Ministries','GRM-003','Paying','Bulawayo','6,380','148 MB',6],
 ['UFIC Chinhoyi','UFI-013','Paying','Mash. West','4,905','112 MB',5],
 ['Baptist Gweru Central','BGC-011','Paying','Midlands','3,740','86 MB',4],
 ['New Life Chitungwiza','NLC-061','Trial','Harare','1,180','28 MB',1],
];

/* ── Slow queries ──────────────────────────────────────────────────────────
   LATER: read the slow query log or performance_schema.events_statements_summary. */
$slowQueries = [
 ['SELECT * FROM activity_logs WHERE church_id = ? ORDER BY created_at DESC','2.84 s',412,'activity_logs','No index on (church_id, created_at)'],
 ['SELECT COUNT(*) FROM attendance_records WHERE service_date BETWEEN ? AND ?','2.11 s',188,'attendance_records','Full scan over 386k rows'],
 ['SELECT m.*, f.name FROM members m LEFT JOIN families f ON f.id = m.family_id','1.76 s',96,'members','Join without covering index'],
 ['SELECT * FROM login_history WHERE ip_address = ?','1.42 s',240,'login_history','No index on ip_address'],
 ['SELECT SUM(amount) FROM contributions GROUP BY church_id, month','1.18 s',64,'contributions','Grouping on an unindexed expression'],
];

$activePage    = 'monitor/database';
$sidebarBadges = ['pending' => 10, 'expiring' => 12];
$sidebarUser   = ['name' => 'Super Admin', 'role' => 'System Owner'];
$pageTitle     = 'Database Health';
$pageHint      = 'Size, growth and query performance of the platform database.';
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

  <div class="card">
    <div class="card__head"><h2>System Status</h2><span class="card__note">Checked a moment ago</span></div>
    <ul class="indicators">
      <?php foreach ($status as [$label,$value,$tone,$icon,$note]): ?>
        <li class="indicator indicator--<?= $tone ?>">
          <span class="indicator__icon"><i class="fa-solid <?= $icon ?>"></i></span>
          <span class="indicator__body">
            <span class="indicator__label"><?= $label ?></span>
            <span class="indicator__value"><?= $value ?><span class="indicator__led"></span></span>
            <small class="indicator__note"><?= $note ?></small>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>

  <div class="grid grid--2-1">
    <div class="card card--chartfill">
      <div class="card__head"><h2>Database Growth</h2><span class="card__note">Last 12 months, GB</span></div>
      <div class="card__body"><div class="chart-wrap"><canvas id="growthChart"></canvas></div></div>
    </div>
    <div class="card">
      <div class="card__head"><h2>Storage Used</h2></div>
      <div class="card__body">
        <div class="chart-wrap chart-wrap--donut"><canvas id="storeChart"></canvas></div>
        <ul class="legend">
          <?php foreach ($storage as [$l,$mb,$col]): ?>
            <li><span class="legend__dot" style="background: <?= $col ?>"></span>
              <span class="legend__label"><?= $l ?></span>
              <span class="legend__count"><?= $mb ?> MB &middot; <?= round($mb / $storeTotal * 100) ?>%</span></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card__head">
      <h2>Table Statistics</h2>
      <div class="card__tools">
        <label class="field field--search field--inline"><span class="field__label sr-only">Search tables</span>
          <span class="field__input"><i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" placeholder="Search tables..."></span></label>
        <button class="btn btn--sm btn--primary" type="button" data-modal="modalOptimise"><i class="fa-solid fa-broom"></i> Optimise All</button>
      </div>
    </div>
    <div class="table-wrap">
      <table class="table table--churches">
        <thead>
          <tr><th class="col-num">#</th><th>Table Name</th><th class="ta-right">Rows</th>
            <th class="ta-right">Data Size</th><th class="ta-right">Index Size</th><th>Engine</th>
            <th>Last Updated</th><th>Overhead</th><th>Status</th><th class="col-actions">Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($tables as $i => $t): ?>
            <?php
              [$name,$rows,$data,$index,$engine,$updated,$overhead,$tstatus] = $t;
              $pct  = (int) rtrim($overhead, '%');
              $tone = $pct >= 20 ? 'berry' : ($pct >= 10 ? 'gold' : 'green');
            ?>
            <tr>
              <td class="col-num muted"><?= $i + 1 ?></td>
              <td><code class="keytext keytext--strong"><?= $name ?></code></td>
              <td class="ta-right"><?= $rows ?></td>
              <td class="ta-right strong"><?= $data ?></td>
              <td class="ta-right muted"><?= $index ?></td>
              <td><span class="role"><?= $engine ?></span></td>
              <td class="nowrap muted"><?= $updated ?></td>
              <td>
                <span class="setup">
                  <span class="setup__num"><?= $overhead ?></span>
                  <span class="bar bar--<?= $tone ?>"><i style="width: <?= min(100, $pct * 3) ?>%"></i></span>
                </span>
              </td>
              <td><span class="pill pill--<?= $tstatus === 'Healthy' ? 'healthy' : 'needs-optimising' ?>"><?= $tstatus ?></span></td>
              <td class="col-actions">
                <div class="row-actions">
                  <button class="ico-btn" type="button" title="Table Details" aria-label="Table Details" data-modal="modalTable"><i class="fa-regular fa-eye"></i></button>
                  <button class="ico-btn" type="button" title="Optimise" aria-label="Optimise" data-modal="modalOptimise"><i class="fa-solid fa-broom"></i></button>
                  <button class="ico-btn" type="button" title="Export" aria-label="Export"><i class="fa-solid fa-file-arrow-down"></i></button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="tablefoot">
      <p class="tablefoot__count">Showing the <?= count($tables) ?> largest of 42 tables</p>
      <nav class="pagination" aria-label="Pagination">
        <a class="pagination__btn is-disabled" href="#">Previous</a>
        <a class="pagination__btn is-on" href="#">1</a><a class="pagination__btn" href="#">2</a>
        <a class="pagination__btn" href="#">3</a><a class="pagination__btn" href="#">Next</a>
      </nav>
    </div>
  </div>

  <div class="card">
    <div class="card__head"><h2>Storage by Church</h2><span class="card__note">Top 10 by size</span></div>
    <div class="table-wrap">
      <table class="table table--churches">
        <thead>
          <tr><th class="col-num">#</th><th>Church</th><th>Province</th><th class="ta-right">Members</th>
            <th class="ta-right">Storage</th><th>Share of Total</th><th class="col-actions">Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($byChurch as $i => [$name,$code,$acct,$prov,$members,$size,$share]): ?>
            <tr>
              <td class="col-num muted"><?= $i + 1 ?></td>
              <td>
                <div class="church">
                  <span class="church__avatar"><?= substr($code, 0, 1) ?></span>
                  <span class="church__text"><strong><?= htmlspecialchars($name) ?></strong>
                    <small><?= $code ?> <span class="pill pill--<?= strtolower($acct) ?> pill--xs"><?= $acct ?></span></small></span>
                </div>
              </td>
              <td class="nowrap muted"><?= $prov ?></td>
              <td class="ta-right"><?= $members ?></td>
              <td class="ta-right strong"><?= $size ?></td>
              <td>
                <span class="setup">
                  <span class="setup__num"><?= $share ?>%</span>
                  <span class="bar"><i style="width: <?= $share * 5 ?>%"></i></span>
                </span>
              </td>
              <td class="col-actions">
                <div class="row-actions">
                  <button class="ico-btn" type="button" title="View Church" aria-label="View Church"><i class="fa-regular fa-eye"></i></button>
                  <button class="ico-btn" type="button" title="Export Data" aria-label="Export Data"><i class="fa-solid fa-file-arrow-down"></i></button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card__head"><h2>Slow Queries</h2><span class="card__note">Over 1.0 s, last 24 hours</span></div>
    <ul class="slowlist">
      <?php foreach ($slowQueries as [$sql,$time,$calls,$table,$why]): ?>
        <li class="slowlist__row">
          <span class="slowlist__time"><?= $time ?></span>
          <span class="slowlist__body">
            <code class="keytext slowlist__sql"><?= htmlspecialchars($sql) ?></code>
            <small><?= $calls ?> calls &middot; <code class="keytext"><?= $table ?></code> &middot; <?= $why ?></small>
          </span>
          <button class="btn btn--sm" type="button" data-modal="modalSlow">Details</button>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <footer class="foot">
    <p class="foot__brand">Mutendi CMS</p>
    <p class="foot__copy">&copy; <?= date('Y') ?> Mutendi. All rights reserved.</p>
    <p class="foot__meta">Super Admin &middot; v1.0</p>
  </footer>

</main>

<div class="modal" id="modalTable" hidden>
  <div class="modal__box modal__box--lg">
    <div class="modal__head">
      <h2><i class="fa-solid fa-table-list"></i> Table Details &mdash; <code class="keytext">activity_logs</code></h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <section class="msec">
        <h3 class="msec__title">Overview</h3>
        <dl class="summary">
          <div><dt>Rows</dt><dd>298,540</dd></div>
          <div><dt>Data size</dt><dd>244 MB</dd></div>
          <div><dt>Index size</dt><dd>52 MB</dd></div>
          <div><dt>Engine</dt><dd><span class="role">InnoDB</span></dd></div>
          <div><dt>Collation</dt><dd><code class="keytext">utf8mb4_unicode_ci</code></dd></div>
          <div><dt>Auto increment</dt><dd>298,541</dd></div>
          <div><dt>Overhead</dt><dd><span class="pill pill--needs-optimising">18% &mdash; 44 MB reclaimable</span></dd></div>
          <div><dt>Last updated</dt><dd>25 Aug 2026, 14:41</dd></div>
        </dl>
      </section>

      <section class="msec">
        <h3 class="msec__title">Columns</h3>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr></thead>
            <tbody>
              <?php foreach ([['id','BIGINT UNSIGNED','No','PRIMARY','—'],
                              ['church_id','INT UNSIGNED','Yes','MUL','NULL'],
                              ['user_id','INT UNSIGNED','Yes','MUL','NULL'],
                              ['action','VARCHAR(64)','No','','—'],
                              ['module','VARCHAR(64)','No','','—'],
                              ['record_ref','VARCHAR(96)','Yes','','NULL'],
                              ['ip_address','VARCHAR(45)','Yes','','NULL'],
                              ['payload','JSON','Yes','','NULL'],
                              ['created_at','DATETIME','No','MUL','—']] as [$c,$ty,$nul,$key,$def]): ?>
                <tr><td><code class="keytext"><?= $c ?></code></td><td class="muted"><?= $ty ?></td>
                  <td class="muted"><?= $nul ?></td>
                  <td><?= $key === '' ? '<span class="muted">&mdash;</span>' : '<span class="role">' . $key . '</span>' ?></td>
                  <td class="muted"><?= $def ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section class="msec">
        <h3 class="msec__title">Indexes</h3>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Name</th><th>Columns</th><th>Type</th><th class="ta-right">Cardinality</th></tr></thead>
            <tbody>
              <?php foreach ([['PRIMARY','id','BTREE','298,540'],
                              ['idx_church','church_id','BTREE','132'],
                              ['idx_user','user_id','BTREE','318'],
                              ['idx_created','created_at','BTREE','86,204']] as [$n,$cols,$ty,$card]): ?>
                <tr><td><code class="keytext"><?= $n ?></code></td><td class="muted"><?= $cols ?></td>
                  <td class="muted"><?= $ty ?></td><td class="ta-right"><?= $card ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="notebox"><i class="fa-solid fa-lightbulb"></i>
          A composite index on <code class="keytext">(church_id, created_at)</code> would let the
          per-church activity feed skip the sort entirely.</p>
      </section>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Close</button>
      <button class="btn" type="button"><i class="fa-solid fa-file-arrow-down"></i> Export Table</button>
      <button class="btn btn--primary" type="button" data-modal="modalOptimise"><i class="fa-solid fa-broom"></i> Optimise Table</button>
    </div>
  </div>
</div>

<div class="modal" id="modalOptimise" hidden>
  <div class="modal__box modal__box--sm">
    <div class="modal__head">
      <h2><i class="fa-solid fa-broom"></i> Optimise Tables</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <p class="modal__lead">This rebuilds the selected tables and reclaims the space left behind by
        deleted rows. Nothing is lost &mdash; but the tables are locked while it runs.</p>
      <dl class="summary">
        <div><dt>Tables</dt><dd>4 marked as needing optimising</dd></div>
        <div><dt>Space to reclaim</dt><dd>≈ 74 MB</dd></div>
        <div><dt>Estimated time</dt><dd>40&ndash;90 seconds</dd></div>
      </dl>
      <ul class="picklist">
        <?php foreach ([['activity_logs','18% &middot; 44 MB'],['sessions','34% &middot; 1 MB'],
                        ['error_logs','27% &middot; 10 MB'],['login_history','21% &middot; 19 MB']] as [$t,$note]): ?>
          <li class="picklist__row">
            <label class="check"><input type="checkbox" checked>
              <span><code class="keytext"><?= $t ?></code></span></label>
            <small class="muted"><?= $note ?></small>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="modal__warn"><i class="fa-solid fa-triangle-exclamation"></i>
        Run this outside service hours. Churches writing to these tables will wait until it finishes.</p>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--primary" type="button"><i class="fa-solid fa-broom"></i> Run Optimise</button>
    </div>
  </div>
</div>

<div class="modal" id="modalSlow" hidden>
  <div class="modal__box modal__box--lg">
    <div class="modal__head">
      <h2><i class="fa-solid fa-gauge-high note--gold"></i> Slow Query Details</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <dl class="summary">
        <div><dt>Average time</dt><dd><span class="pill pill--warning">2.84 s</span></dd></div>
        <div><dt>Slowest run</dt><dd>4.12 s</dd></div>
        <div><dt>Calls today</dt><dd>412</dd></div>
        <div><dt>Rows examined</dt><dd>298,540</dd></div>
        <div><dt>Rows returned</dt><dd>50</dd></div>
        <div><dt>Table</dt><dd><code class="keytext">activity_logs</code></dd></div>
      </dl>

      <section class="msec">
        <h3 class="msec__title">Query</h3>
        <div class="monobox">
          <pre id="slowSql">SELECT *
  FROM activity_logs
 WHERE church_id = ?
 ORDER BY created_at DESC
 LIMIT 50;</pre>
          <button class="btn btn--sm monobox__copy" type="button" data-copy="slowSql"><i class="fa-regular fa-copy"></i> Copy</button>
        </div>
      </section>

      <section class="msec">
        <h3 class="msec__title">Explain</h3>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Table</th><th>Type</th><th>Possible Keys</th><th>Key Used</th><th class="ta-right">Rows</th><th>Extra</th></tr></thead>
            <tbody>
              <tr><td><code class="keytext">activity_logs</code></td><td><span class="pill pill--warning">ALL</span></td>
                <td class="muted">idx_church</td><td class="muted">NULL</td><td class="ta-right">298,540</td>
                <td class="muted">Using where; Using filesort</td></tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="msec">
        <h3 class="msec__title">Suggested Fix</h3>
        <div class="monobox">
          <pre id="slowFix">CREATE INDEX idx_church_created
    ON activity_logs (church_id, created_at DESC);</pre>
          <button class="btn btn--sm monobox__copy" type="button" data-copy="slowFix"><i class="fa-regular fa-copy"></i> Copy</button>
        </div>
        <p class="notebox"><i class="fa-solid fa-lightbulb"></i>
          With this index the query reads 50 rows instead of 298,540, and the filesort disappears.</p>
      </section>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Close</button>
      <button class="btn btn--primary" type="button"><i class="fa-solid fa-wrench"></i> Apply Suggested Index</button>
    </div>
  </div>
</div>
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
  new Chart(document.getElementById('growthChart'), {
    type: 'line',
    data: { labels: <?= json_encode($months) ?>,
      datasets: [{ label: 'Database size (GB)', data: <?= json_encode($dbSize) ?>,
        borderColor: '#662F97', backgroundColor: 'rgba(102,47,151,.10)',
        borderWidth: 2, tension: .35, pointRadius: 3, fill: true }] },
    options: { responsive: true, maintainAspectRatio: false,
      plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true, color: tick } } },
      scales: { x: { grid: { display: false }, ticks: { color: tick } },
                y: { beginAtZero: true, grid: { color: grid },
                     ticks: { color: tick, callback: function (v) { return v + ' GB'; } } } } }
  });
  new Chart(document.getElementById('storeChart'), {
    type: 'doughnut',
    data: { labels: <?= json_encode(array_column($storage, 0)) ?>,
      datasets: [{ data: <?= json_encode(array_column($storage, 1)) ?>,
        backgroundColor: <?= json_encode(array_column($storage, 2)) ?>, borderWidth: 0, hoverOffset: 6 }] },
    options: { responsive: true, maintainAspectRatio: false, cutout: '64%', plugins: { legend: { display: false } } }
  });
})();
</script>
</body>
</html>

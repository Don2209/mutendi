<?php
/**
 * Mutendi CMS — Church Admins (static UI mockup).
 *
 * Every administrator login across every church, in one searchable list — the
 * support screen for password resets and lockouts. Admins of Trial and Paying
 * churches sit in the same list, distinguished only by the account-type badge.
 * Every dataset is hardcoded; each block carries the query that replaces it.
 */

/* Path constants only — the sidebar markup is required further down. */
$musPathsOnly = true;
require __DIR__ . '/../components/sidebar.php';
$musPathsOnly = false;

/* ── Admin accounts ────────────────────────────────────────────────────────
   LATER:
     SELECT a.*, c.name AS church, c.code AS church_code, c.account_type
       FROM church_admins a
       JOIN churches c ON c.id = a.church_id
      WHERE (:search IS NULL OR a.name LIKE :search OR a.email LIKE :search OR a.phone LIKE :search)
        AND (:church IS NULL OR a.church_id = :church)
        AND (:type IS NULL OR c.account_type = :type)
        AND (:role IS NULL OR a.role = :role)
        AND (:status IS NULL OR a.status = :status)
      ORDER BY a.name
      LIMIT :per_page OFFSET :offset;
   `stale` flags a login older than 30 days; later a DATEDIFF on last_login_at. */
$admins = [
    ['in'=>'TM','name'=>'Tendai Mabhena','email'=>'t.mabhena@zccmbungo.co.zw','phone'=>'+263 772 145 880','church'=>'ZCC Mbungo','code'=>'ZCC-001','acct'=>'Paying','role'=>'Church Admin','status'=>'Active','login'=>'2 hours ago','stale'=>false,'logins'=>412,'twofa'=>true,'created'=>'12 Aug 2024'],
    ['in'=>'SB','name'=>'Simba Banda','email'=>'s.banda@afmwaterfalls.org','phone'=>'+263 778 411 207','church'=>'AFM Waterfalls','code'=>'AFM-002','acct'=>'Paying','role'=>'Pastor','status'=>'Active','login'=>'1 hour ago','stale'=>false,'logins'=>688,'twofa'=>true,'created'=>'07 Mar 2024'],
    ['in'=>'RC','name'=>'Rutendo Chikore','email'=>'r.chikore@graceministries.co.zw','phone'=>'+263 771 902 335','church'=>'Grace Ministries','code'=>'GRM-003','acct'=>'Paying','role'=>'Secretary','status'=>'Locked','login'=>'34 days ago','stale'=>true,'logins'=>96,'twofa'=>false,'created'=>'09 Jan 2024'],
    ['in'=>'MZ','name'=>'Munashe Zvobgo','email'=>'m.zvobgo@jmasowe.co.zw','phone'=>'+263 712 660 194','church'=>'Johane Masowe eChishanu','code'=>'JME-004','acct'=>'Paying','role'=>'Church Admin','status'=>'Active','login'=>'5 hours ago','stale'=>false,'logins'=>530,'twofa'=>true,'created'=>'05 Feb 2024'],
    ['in'=>'LM','name'=>'Loveness Mhaka','email'=>'l.mhaka@celebration.co.zw','phone'=>'+263 773 508 621','church'=>'Celebration Church Harare','code'=>'CCH-005','acct'=>'Paying','role'=>'Treasurer','status'=>'Active','login'=>'Yesterday','stale'=>false,'logins'=>274,'twofa'=>true,'created'=>'03 Aug 2023'],
    ['in'=>'PN','name'=>'Peter Ndlovu','email'=>'p.ndlovu@fogbulawayo.org','phone'=>'+263 776 233 018','church'=>'Family of God Bulawayo','code'=>'FOG-006','acct'=>'Trial','role'=>'Church Admin','status'=>'Active','login'=>'3 days ago','stale'=>false,'logins'=>41,'twofa'=>false,'created'=>'01 Aug 2026'],
    ['in'=>'GN','name'=>'Gilbert Nyathi','email'=>'g.nyathi@methodistmutare.org','phone'=>'+263 774 887 552','church'=>'Methodist Mutare Circuit','code'=>'MMC-007','acct'=>'Paying','role'=>'Pastor','status'=>'Active','login'=>'6 hours ago','stale'=>false,'logins'=>355,'twofa'=>true,'created'=>'29 Jul 2023'],
    ['in'=>'CD','name'=>'Chipo Dube','email'=>'c.dube@glorygweru.co.zw','phone'=>'+263 717 340 776','church'=>'Glory Ministries Gweru','code'=>'GMG-008','acct'=>'Paying','role'=>'Department Head','status'=>'Suspended','login'=>'62 days ago','stale'=>true,'logins'=>58,'twofa'=>false,'created'=>'26 Jul 2024'],
    ['in'=>'EM','name'=>'Edmore Marange','email'=>'e.marange@anglicanmasvingo.org','phone'=>'+263 779 115 442','church'=>'Anglican Diocese Masvingo','code'=>'ADM-009','acct'=>'Paying','role'=>'Church Admin','status'=>'Active','login'=>'4 hours ago','stale'=>false,'logins'=>801,'twofa'=>true,'created'=>'24 Nov 2022'],
    ['in'=>'AM','name'=>'Anesu Mabika','email'=>'a.mabika@newlife.co.zw','phone'=>'+263 772 118 904','church'=>'New Life Chitungwiza','code'=>'NLC-061','acct'=>'Trial','role'=>'Church Admin','status'=>'Active','login'=>'2 hours ago','stale'=>false,'logins'=>24,'twofa'=>false,'created'=>'04 Aug 2026'],
    ['in'=>'VN','name'=>'Vimbai Ncube','email'=>'v.ncube@rhemabyo.org','phone'=>'+263 778 337 220','church'=>'Rhema Bulawayo','code'=>'RHB-063','acct'=>'Trial','role'=>'Secretary','status'=>'Pending First Login','login'=>'Never','stale'=>true,'logins'=>0,'twofa'=>false,'created'=>'07 Aug 2026'],
    ['in'=>'BC','name'=>'Blessing Chidziva','email'=>'b.chidziva@stthomas.org','phone'=>'+263 771 446 093','church'=>'St Thomas Mutare','code'=>'STM-064','acct'=>'Trial','role'=>'Treasurer','status'=>'Pending First Login','login'=>'Never','stale'=>true,'logins'=>0,'twofa'=>false,'created'=>'10 Aug 2026'],
    ['in'=>'DK','name'=>'Danai Kanyemba','email'=>'d.kanyemba@ufic-chinhoyi.org','phone'=>'+263 778 552 907','church'=>'UFIC Chinhoyi','code'=>'UFI-013','acct'=>'Paying','role'=>'Department Head','status'=>'Active','login'=>'12 hours ago','stale'=>false,'logins'=>187,'twofa'=>true,'created'=>'30 Apr 2023'],
    ['in'=>'JS','name'=>'Joseph Sibanda','email'=>'j.sibanda@sdahwange.org','phone'=>'+263 719 447 630','church'=>'SDA Hwange','code'=>'SDA-014','acct'=>'Paying','role'=>'Secretary','status'=>'Locked','login'=>'71 days ago','stale'=>true,'logins'=>63,'twofa'=>false,'created'=>'05 Aug 2023'],
    ['in'=>'TN','name'=>'Tafadzwa Mangwiro','email'=>'t.mangwiro@afmbindura.org','phone'=>'+263 773 221 985','church'=>'AFM Bindura','code'=>'AFB-015','acct'=>'Paying','role'=>'Pastor','status'=>'Active','login'=>'2 days ago','stale'=>false,'logins'=>129,'twofa'=>true,'created'=>'17 Oct 2023'],
];

/* ── Stat strip ────────────────────────────────────────────────────────────
   LATER: SELECT status, COUNT(*) FROM church_admins GROUP BY status; */
$statTiles = [
    ['label' => 'Total Admins',      'value' => 96, 'tone' => 'indigo', 'icon' => 'fa-user-tie',      'on' => true],
    ['label' => 'Active',            'value' => 88, 'tone' => 'green',  'icon' => 'fa-circle-check',  'on' => false],
    ['label' => 'Locked / Suspended','value' => 4,  'tone' => 'berry',  'icon' => 'fa-lock',          'on' => false],
    ['label' => 'Never Logged In',   'value' => 4,  'tone' => 'gold',   'icon' => 'fa-hourglass-half','on' => false],
];

/* ── Filter options ────────────────────────────────────────────────────────
   LATER: churches from the churches table; roles from a lookup. */
$churchList = ['All Churches', 'ZCC Mbungo', 'AFM Waterfalls', 'Grace Ministries', 'Johane Masowe eChishanu',
               'Celebration Church Harare', 'Family of God Bulawayo', 'Methodist Mutare Circuit',
               'Anglican Diocese Masvingo', 'New Life Chitungwiza', 'Rhema Bulawayo', 'UFIC Chinhoyi'];
$roleList   = ['All Roles', 'Church Admin', 'Pastor', 'Secretary', 'Treasurer', 'Department Head'];
$statusList = ['All Statuses', 'Active', 'Locked', 'Suspended', 'Pending First Login'];
$loginList  = ['Any time', 'Today', 'This Week', 'This Month', 'Over 30 Days', 'Never'];

$columns = [
    ['label' => 'Admin',       'sort' => 'asc'],
    ['label' => 'Phone',       'sort' => null],
    ['label' => 'Church',      'sort' => null],
    ['label' => 'Role',        'sort' => null],
    ['label' => 'Status',      'sort' => null],
    ['label' => 'Last Login',  'sort' => null],
    ['label' => 'Logins',      'sort' => null, 'align' => 'right'],
    ['label' => '2FA',         'sort' => null, 'align' => 'center'],
    ['label' => 'Created',     'sort' => null],
];

$rowMenu = [
    ['label' => 'View Profile',          'icon' => 'fa-id-card',        'modal' => 'modalProfile'],
    ['label' => 'Edit Admin',            'icon' => 'fa-pen',            'modal' => 'modalAdmin'],
    ['label' => 'Reset Password',        'icon' => 'fa-key',            'modal' => 'modalReset'],
    ['label' => 'Send Credentials',      'icon' => 'fa-paper-plane'],
    ['label' => 'Unlock Account',        'icon' => 'fa-lock-open'],
    ['label' => 'Force Password Change', 'icon' => 'fa-rotate'],
    ['label' => 'View Login History',    'icon' => 'fa-right-to-bracket'],
    ['label' => 'View Activity Log',     'icon' => 'fa-wave-square'],
    ['label' => 'Suspend Account',       'icon' => 'fa-ban',   'modal' => 'modalSuspend', 'sep' => true],
    ['label' => 'Delete',                'icon' => 'fa-trash', 'modal' => 'modalDeleteAdmin', 'danger' => true],
];

$activePage    = 'access/admins';
$sidebarBadges = ['pending' => 10, 'expiring' => 12];
$sidebarUser   = ['name' => 'Super Admin', 'role' => 'System Owner'];

/** Status label to pill modifier. */
function admin_pill(string $s): string {
    return 'pill--' . strtolower(str_replace(' ', '-', $s));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Church Admins — Mutendi CMS Super Admin</title>
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
      <h1>Church Admins <span class="title-badge">96</span></h1>
      <p class="crumb">
        <a href="<?= $base_url ?>index.php">Dashboard</a> <i class="fa-solid fa-chevron-right"></i>
        Access &amp; Licensing <i class="fa-solid fa-chevron-right"></i> Church Admins
      </p>
      <p class="page-hint">All administrator accounts across every church on the platform.</p>
    </div>
    <div class="head-actions">
      <button class="btn btn--primary" type="button" data-modal="modalAdmin"><i class="fa-solid fa-plus"></i> Add Admin</button>
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
          <input type="search" placeholder="Search by name, email or phone...">
        </span>
      </label>
      <label class="field"><span class="field__label">Church</span>
        <select><?php foreach ($churchList as $o): ?><option><?= htmlspecialchars($o) ?></option><?php endforeach; ?></select></label>
      <label class="field"><span class="field__label">Account Type</span>
        <select><option>All</option><option>Trial</option><option>Paying</option></select></label>
      <label class="field"><span class="field__label">Role</span>
        <select><?php foreach ($roleList as $o): ?><option><?= htmlspecialchars($o) ?></option><?php endforeach; ?></select></label>
      <label class="field"><span class="field__label">Status</span>
        <select><?php foreach ($statusList as $o): ?><option><?= htmlspecialchars($o) ?></option><?php endforeach; ?></select></label>
      <label class="field"><span class="field__label">Last Login</span>
        <select><?php foreach ($loginList as $o): ?><option><?= htmlspecialchars($o) ?></option><?php endforeach; ?></select></label>
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
      <button class="btn btn--sm" type="button" data-modal="modalReset"><i class="fa-solid fa-key"></i> Reset Password</button>
      <button class="btn btn--sm" type="button"><i class="fa-solid fa-paper-plane"></i> Send Notification</button>
      <button class="btn btn--sm" type="button"><i class="fa-solid fa-lock-open"></i> Unlock</button>
      <button class="btn btn--sm" type="button" data-modal="modalSuspend"><i class="fa-solid fa-ban"></i> Suspend</button>
      <button class="btn btn--sm" type="button"><i class="fa-solid fa-file-export"></i> Export Selected</button>
    </div>
    <button class="bulkbar__clear" type="button" id="bulkClear" aria-label="Clear selection"><i class="fa-solid fa-xmark"></i></button>
  </div>

  <div class="card">
    <div class="table-wrap">
      <table class="table table--churches">
        <thead>
          <tr>
            <th class="col-check"><input type="checkbox" id="checkAll" aria-label="Select all admins"></th>
            <th class="col-num">#</th>
            <?php foreach ($columns as $c): ?>
              <th class="<?= ($c['align'] ?? '') === 'right' ? 'ta-right ' : (($c['align'] ?? '') === 'center' ? 'ta-center ' : '') ?>th-sort<?= !empty($c['sort']) ? ' is-sorted' : '' ?>">
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
          <?php foreach ($admins as $i => $a): ?>
            <tr>
              <td class="col-check"><input type="checkbox" class="row-check" aria-label="Select <?= htmlspecialchars($a['name']) ?>"></td>
              <td class="col-num muted"><?= $i + 1 ?></td>

              <td>
                <div class="church">
                  <span class="church__avatar"><?= $a['in'] ?></span>
                  <span class="church__text">
                    <strong><?= htmlspecialchars($a['name']) ?></strong>
                    <small><?= htmlspecialchars($a['email']) ?></small>
                  </span>
                </div>
              </td>

              <td class="muted nowrap"><?= $a['phone'] ?></td>

              <td class="nowrap">
                <a class="churchlink stack" href="<?= $base_url ?>churches/all.php">
                  <strong><?= htmlspecialchars($a['church']) ?></strong>
                  <small><?= $a['code'] ?> <span class="pill pill--<?= strtolower($a['acct']) ?> pill--xs"><?= $a['acct'] ?></span></small>
                </a>
              </td>

              <td><span class="role"><?= htmlspecialchars($a['role']) ?></span></td>
              <td><span class="pill <?= admin_pill($a['status']) ?>"><?= $a['status'] ?></span></td>
              <td class="nowrap <?= $a['stale'] ? 'muted' : '' ?>"><?= $a['login'] ?></td>
              <td class="ta-right strong"><?= number_format($a['logins']) ?></td>
              <td class="ta-center"><?= $a['twofa']
                    ? '<i class="fa-solid fa-check yn yn--yes"></i>'
                    : '<i class="fa-solid fa-xmark yn yn--no"></i>' ?></td>
              <td class="nowrap muted"><?= $a['created'] ?></td>

              <td class="col-actions">
                <div class="row-actions">
                  <button class="ico-btn" type="button" title="View" aria-label="View" data-modal="modalProfile"><i class="fa-regular fa-eye"></i></button>
                  <button class="ico-btn" type="button" title="Reset Password" aria-label="Reset Password" data-modal="modalReset"><i class="fa-solid fa-key"></i></button>
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

    <div class="empty" id="emptyState" hidden>
      <span class="empty__icon"><i class="fa-solid fa-user-tie"></i></span>
      <p class="empty__title">No admins found</p>
      <p class="empty__text">No administrator matches the filters you have applied. Try widening your search.</p>
      <button class="btn btn--primary" type="button">Reset Filters</button>
    </div>

    <div class="tablefoot">
      <p class="tablefoot__count">Showing 1 to <?= count($admins) ?> of 96 entries</p>
      <nav class="pagination" aria-label="Pagination">
        <a class="pagination__btn is-disabled" href="#">Previous</a>
        <a class="pagination__btn is-on" href="#">1</a>
        <a class="pagination__btn" href="#">2</a>
        <a class="pagination__btn" href="#">3</a>
        <a class="pagination__btn" href="#">4</a>
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

<!-- a) ADD / EDIT ADMIN -->
<div class="modal" id="modalAdmin" hidden>
  <div class="modal__box">
    <div class="modal__head">
      <h2><i class="fa-solid fa-user-tie"></i> Add Admin</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <label class="field"><span class="field__label">Full name</span>
        <input type="text" placeholder="Tendai Mabhena"></label>
      <div class="field-row">
        <label class="field"><span class="field__label">Email</span>
          <input type="email" placeholder="admin@church.co.zw"></label>
        <label class="field"><span class="field__label">Phone</span>
          <input type="tel" placeholder="+263 772 000 000"></label>
      </div>
      <label class="field"><span class="field__label">Church</span>
        <select>
          <?php foreach ($admins as $a): ?>
            <option><?= htmlspecialchars($a['church']) ?> (<?= $a['code'] ?>) — <?= $a['acct'] ?></option>
          <?php endforeach; ?>
        </select></label>
      <div class="field-row">
        <label class="field"><span class="field__label">Role</span>
          <select><?php foreach (array_slice($roleList, 1) as $r): ?><option><?= $r ?></option><?php endforeach; ?></select></label>
        <label class="field"><span class="field__label">Status</span>
          <select><?php foreach (array_slice($statusList, 1) as $s): ?><option><?= $s ?></option><?php endforeach; ?></select></label>
      </div>
      <label class="check-row"><input type="checkbox" checked>
        <span>Send login credentials via email and SMS</span></label>
      <label class="check-row"><input type="checkbox" checked>
        <span>Require password change on first login</span></label>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--primary" type="button">Save Admin</button>
    </div>
  </div>
</div>

<!-- b) RESET PASSWORD -->
<div class="modal" id="modalReset" hidden>
  <div class="modal__box modal__box--sm">
    <div class="modal__head">
      <h2><i class="fa-solid fa-key"></i> Reset Password</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <div class="field-row">
        <label class="field"><span class="field__label">Admin</span>
          <input type="text" value="Rutendo Chikore" readonly></label>
        <label class="field"><span class="field__label">Email</span>
          <input type="text" value="r.chikore@graceministries.co.zw" readonly></label>
      </div>
      <span class="field__label">New password</span>
      <div class="radios">
        <label class="radio"><input type="radio" name="pwmode" value="auto" checked data-pwmode="auto"><span>Generate random password</span></label>
        <label class="radio"><input type="radio" name="pwmode" value="manual" data-pwmode="manual"><span>Set password manually</span></label>
      </div>
      <label class="field" id="pwManual" hidden><span class="field__label">Password</span>
        <input type="text" placeholder="Enter a password"></label>
      <span class="field__label">Send the new password by</span>
      <div class="radios">
        <label class="radio"><input type="checkbox" checked><span>Email</span></label>
        <label class="radio"><input type="checkbox" checked><span>SMS</span></label>
      </div>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--primary" type="button">Reset Password</button>
    </div>
  </div>
</div>

<!-- c) VIEW PROFILE -->
<div class="modal" id="modalProfile" hidden>
  <div class="modal__box">
    <div class="modal__head">
      <h2><i class="fa-solid fa-id-card"></i> Admin Profile</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <div class="profilehead">
        <span class="church__avatar profilehead__avatar">TM</span>
        <span class="profilehead__text">
          <strong>Tendai Mabhena</strong>
          <small>t.mabhena@zccmbungo.co.zw</small>
          <span class="pill pill--active">Active</span>
        </span>
      </div>

      <section class="msec">
        <h3 class="msec__title">Account</h3>
        <dl class="summary">
          <div><dt>Phone</dt><dd>+263 772 145 880</dd></div>
          <div><dt>Church</dt><dd>ZCC Mbungo (ZCC-001) <span class="pill pill--paying pill--xs">Paying</span></dd></div>
          <div><dt>Role</dt><dd><span class="role">Church Admin</span></dd></div>
          <div><dt>Two-factor</dt><dd><i class="fa-solid fa-check yn yn--yes"></i> Enabled</dd></div>
          <div><dt>Created</dt><dd>12 Aug 2024</dd></div>
          <div><dt>Last login</dt><dd>2 hours ago</dd></div>
          <div><dt>Login count</dt><dd>412</dd></div>
          <div><dt>Last IP</dt><dd>197.221.44.18</dd></div>
        </dl>
      </section>

      <section class="msec">
        <h3 class="msec__title">Recent Activity</h3>
        <ul class="feed feed--flat">
          <li class="feed__row"><span class="dot dot--blue"></span>
            <span class="feed__text">Signed in from Harare<small>2 hours ago</small></span></li>
          <li class="feed__row"><span class="dot dot--green"></span>
            <span class="feed__text">Added 14 members to <strong>Youth</strong><small>Yesterday 16:20</small></span></li>
          <li class="feed__row"><span class="dot dot--blue"></span>
            <span class="feed__text">Sent an SMS to 240 members<small>21 Aug, 09:05</small></span></li>
          <li class="feed__row"><span class="dot dot--amber"></span>
            <span class="feed__text">Password changed<small>02 Aug, 11:44</small></span></li>
        </ul>
      </section>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-modal="modalReset">Reset Password</button>
      <button class="btn" type="button">Login As</button>
      <button class="btn btn--primary" type="button" data-modal="modalAdmin">Edit</button>
    </div>
  </div>
</div>

<!-- d) SUSPEND ACCOUNT -->
<div class="modal" id="modalSuspend" hidden>
  <div class="modal__box modal__box--sm">
    <div class="modal__head">
      <h2><i class="fa-solid fa-triangle-exclamation note--gold"></i> Suspend Account</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <p class="modal__lead">Suspending <strong>Chipo Dube</strong> blocks their sign-in immediately. The church itself is unaffected.</p>
      <label class="field"><span class="field__label">Reason</span>
        <textarea rows="3" placeholder="Why this account is being suspended..."></textarea></label>
      <label class="check-row"><input type="checkbox" checked><span>Notify the admin by email</span></label>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--danger-solid" type="button">Suspend Account</button>
    </div>
  </div>
</div>

<!-- e) DELETE ADMIN -->
<div class="modal" id="modalDeleteAdmin" hidden>
  <div class="modal__box modal__box--sm">
    <div class="modal__head">
      <h2><i class="fa-solid fa-trash note--berry"></i> Delete Admin</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <p class="modal__warn"><i class="fa-solid fa-triangle-exclamation"></i>
        This removes <strong>Rutendo Chikore</strong>'s access to Grace Ministries. Church data and anything they captured is kept.</p>
      <label class="field"><span class="field__label">Type <strong>r.chikore@graceministries.co.zw</strong> to confirm</span>
        <input type="text" placeholder="r.chikore@graceministries.co.zw"></label>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--danger-solid" type="button">Delete Admin</button>
    </div>
  </div>
</div>

<script>
/* Bulk-selection bar, dropdowns, modals and the reset-password mode toggle. */
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

  /* The manual password field only appears when it is actually needed. */
  var modes = [].slice.call(document.querySelectorAll('[data-pwmode]')),
      manual = document.getElementById('pwManual');
  if (modes.length && manual) {
    var applyMode = function () {
      var picked = document.querySelector('[data-pwmode]:checked');
      manual.hidden = !picked || picked.dataset.pwmode !== 'manual';
    };
    modes.forEach(function (m) { m.addEventListener('change', applyMode); });
    applyMode();
  }
})();
</script>
</body>
</html>

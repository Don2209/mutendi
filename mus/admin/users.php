<?php
/**
 * Mutendi CMS — Super Admin Users (static UI mockup).
 *
 * These are the VENDOR'S OWN staff accounts — the people who run the platform.
 * Church admin accounts are a different thing entirely and live in
 * access/admins.php. Nothing here touches a church's own user list.
 *
 * Every dataset is hardcoded; each block carries the query that replaces it.
 */

/* The Device & Session Details modal is written once, in monitor/activity.php.
   This pulls in just that definition and returns before the rest of it runs. */
$deviceModalOnly = true;
require __DIR__ . '/../monitor/activity.php';
$deviceModalOnly = false;

$musPathsOnly = true;
require __DIR__ . '/../components/sidebar.php';
$musPathsOnly = false;

/* ── Platform permission totals ────────────────────────────────────────────
   admin/roles.php counts these off its own grant table; the per-role figures
   here mirror it. LATER both come from one query:
     SELECT role_id, COUNT(*) FROM staff_role_permissions GROUP BY role_id; */
$totalPlatformPerms = 42;

/* ── Staff accounts ────────────────────────────────────────────────────────
   LATER:
     SELECT u.*, r.name AS role, r.tone,
            (SELECT COUNT(*) FROM staff_role_permissions WHERE role_id = u.role_id) AS perms
       FROM staff_users u
       JOIN staff_roles r ON r.id = u.role_id
      WHERE (:role   IS NULL OR r.key = :role)
        AND (:status IS NULL OR u.status = :status)
      ORDER BY u.created_at ASC;

   'you' marks the signed-in account; 'owner' is the protected system owner —
   the UI must never offer to suspend or delete it. */
$users = [
 ['in'=>'RM','name'=>'Rufaro Mutasa','email'=>'rufaro@mutendi.co.zw','phone'=>'+263 77 412 8890',
  'role'=>'Owner','tone'=>'brand','perms'=>42,'status'=>'Active','twofa'=>true,
  'last'=>'2 hours ago','lastFull'=>'25 Aug 2026, 12:41','ip'=>'197.221.44.18',
  'dev'=>'laptop','device'=>'Chrome 128 · Windows 11','created'=>'04 Jan 2025',
  'logins'=>1284,'you'=>true,'owner'=>true],

 ['in'=>'TC','name'=>'Tapiwa Chirwa','email'=>'tapiwa@mutendi.co.zw','phone'=>'+263 71 665 2043',
  'role'=>'Administrator','tone'=>'indigo','perms'=>38,'status'=>'Active','twofa'=>true,
  'last'=>'35 minutes ago','lastFull'=>'25 Aug 2026, 14:08','ip'=>'41.221.16.90',
  'dev'=>'desktop','device'=>'Edge 128 · Windows 11','created'=>'17 Mar 2025',
  'logins'=>612,'you'=>false,'owner'=>false],

 ['in'=>'NM','name'=>'Nyasha Mudimu','email'=>'nyasha@mutendi.co.zw','phone'=>'+263 78 330 5517',
  'role'=>'Support Agent','tone'=>'green','perms'=>17,'status'=>'Active','twofa'=>true,
  'last'=>'Yesterday, 16:22','lastFull'=>'24 Aug 2026, 16:22','ip'=>'196.27.88.140',
  'dev'=>'mobile','device'=>'Chrome 128 · Android 14','created'=>'02 Sep 2025',
  'logins'=>341,'you'=>false,'owner'=>false],

 ['in'=>'KS','name'=>'Kudzai Sibanda','email'=>'kudzai@mutendi.co.zw','phone'=>'+263 77 208 4471',
  'role'=>'Support Agent','tone'=>'green','perms'=>17,'status'=>'Suspended','twofa'=>false,
  'last'=>'11 days ago','lastFull'=>'14 Aug 2026, 09:07','ip'=>'102.130.44.7',
  'dev'=>'laptop','device'=>'Firefox 128 · Ubuntu 24.04','created'=>'19 Nov 2025',
  'logins'=>128,'you'=>false,'owner'=>false],

 ['in'=>'FM','name'=>'Farai Moyo','email'=>'farai@mutendi.co.zw','phone'=>'+263 71 954 6620',
  'role'=>'Sales','tone'=>'gold','perms'=>13,'status'=>'Pending Invite','twofa'=>false,
  'last'=>'Never','lastFull'=>'—','ip'=>'—',
  'dev'=>'—','device'=>'—','created'=>'21 Aug 2026',
  'logins'=>0,'you'=>false,'owner'=>false],
];

/* ── Headline ──────────────────────────────────────────────────────────────
   Counted off $users so the tiles cannot drift from the table.
   LATER: COUNT(*) over `staff_users` grouped by status and two_factor_enabled. */
$statusOf = array_column($users, 'status');
$tiles = [
    ['Total Users',  (string) count($users),                          'indigo', 'fa-users',       true],
    ['Active',       (string) count(array_keys($statusOf, 'Active')), 'green',  'fa-circle-check', false],
    ['Suspended',    (string) count(array_keys($statusOf, 'Suspended')), 'grey', 'fa-ban',        false],
    ['2FA Enabled',  (string) count(array_filter($users, fn ($u) => $u['twofa'])), 'brand', 'fa-shield-halved', false],
];

/* Role vocabulary, shared with admin/roles.php.
   LATER: SELECT name, key, tone FROM staff_roles ORDER BY sort_order; */
$roleList = ['All Roles','Owner','Administrator','Support Agent','Sales'];

$activePage    = 'admin/users';
$sidebarBadges = ['pending' => 10, 'expiring' => 12];
$sidebarUser   = ['name' => 'Super Admin', 'role' => 'System Owner'];
$pageTitle     = 'Super Admin Users';
$pageBadge     = count($users);
$pageHint      = 'Manage vendor staff accounts and their access to the platform.';
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
      <h1><?= $pageTitle ?><?php if (!empty($pageBadge)): ?> <span class="title-badge"><?= $pageBadge ?></span><?php endif; ?></h1>
      <p class="crumb">
        <a href="<?= $base_url ?>index.php">Dashboard</a> <i class="fa-solid fa-chevron-right"></i>
        Administration <i class="fa-solid fa-chevron-right"></i> <?= $pageTitle ?>
      </p>
      <p class="page-hint"><?= $pageHint ?></p>
    </div>
    <div class="head-actions">
      <button class="btn btn--primary" type="button" data-modal="modalUser"><i class="fa-solid fa-plus"></i> Add User</button>
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

  <div class="card filterbar">
    <div class="filterbar__row">
      <label class="field field--search"><span class="field__label">Search</span>
        <span class="field__input"><i class="fa-solid fa-magnifying-glass"></i>
          <input type="search" placeholder="Search by name, email or phone..."></span></label>
      <label class="field"><span class="field__label">Role</span>
        <select><?php foreach ($roleList as $r): ?><option><?= $r ?></option><?php endforeach; ?></select></label>
      <label class="field"><span class="field__label">Status</span>
        <select><option>All</option><option>Active</option><option>Suspended</option><option>Pending Invite</option></select></label>
      <label class="field"><span class="field__label">Last Login</span>
        <select><option>All</option><option>Today</option><option>This Week</option><option>This Month</option><option>Over 30 Days</option><option>Never</option></select></label>
    </div>
    <div class="filterbar__foot">
      <div class="filterbar__actions">
        <button class="btn btn--primary" type="button"><i class="fa-solid fa-filter"></i> Apply Filters</button>
        <a class="link-reset" href="#">Reset</a>
      </div>
      <label class="entries">Show
        <select><?php foreach ([10,25,50] as $n): ?><option><?= $n ?></option><?php endforeach; ?></select> entries</label>
    </div>
  </div>

  <div class="bulkbar" data-bulkbar="users" hidden>
    <span class="bulkbar__count"><strong data-bulkcount="users">0</strong> selected</span>
    <div class="bulkbar__actions">
      <button class="btn btn--sm" type="button"><i class="fa-regular fa-paper-plane"></i> Send Notification</button>
      <button class="btn btn--sm" type="button" data-modal="modalReset"><i class="fa-solid fa-key"></i> Reset Password</button>
      <button class="btn btn--sm" type="button" data-modal="modalSuspend"><i class="fa-solid fa-ban"></i> Suspend</button>
      <button class="btn btn--sm btn--danger" type="button" data-modal="modalDeleteUser"><i class="fa-solid fa-trash"></i> Delete</button>
    </div>
    <button class="bulkbar__clear" type="button" data-bulkclear="users" aria-label="Clear selection"><i class="fa-solid fa-xmark"></i></button>
  </div>

  <div class="card">
    <div class="table-wrap">
      <table class="table table--churches">
        <thead>
          <tr><th class="col-check"><input type="checkbox" data-checkall="users" aria-label="Select all users"></th>
            <th class="col-num">#</th><th>User</th><th>Phone</th><th>Role</th><th>Permissions</th>
            <th>Status</th><th class="ta-center">2FA</th><th>Last Login</th><th>Last Device</th>
            <th>Created</th><th class="col-actions">Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($users as $i => $u): ?>
            <?php $devIcon = ['laptop'=>'fa-laptop','desktop'=>'fa-desktop','mobile'=>'fa-mobile-screen','tablet'=>'fa-tablet-screen-button'][$u['dev']] ?? 'fa-circle-minus'; ?>
            <tr>
              <td class="col-check">
                <?php if ($u['owner']): ?>
                  <i class="fa-solid fa-lock lockmark" title="The Owner account cannot be modified"></i>
                <?php else: ?>
                  <input type="checkbox" data-rowcheck="users" aria-label="Select <?= htmlspecialchars($u['name']) ?>">
                <?php endif; ?>
              </td>
              <td class="col-num muted"><?= $i + 1 ?></td>
              <td class="nowrap">
                <div class="church">
                  <span class="church__avatar"><?= $u['in'] ?></span>
                  <span class="church__text">
                    <strong><?= htmlspecialchars($u['name']) ?><?php if ($u['you']): ?> <span class="youchip">You</span><?php endif; ?></strong>
                    <small><?= htmlspecialchars($u['email']) ?></small>
                  </span>
                </div>
              </td>
              <td class="nowrap muted"><?= $u['phone'] ?></td>
              <td><span class="role role--<?= $u['tone'] ?>"><?= $u['role'] ?></span></td>
              <td>
                <span class="setup">
                  <span class="setup__num"><?= $u['perms'] ?> of <?= $totalPlatformPerms ?></span>
                  <span class="bar"><i style="width: <?= (int) round($u['perms'] / $totalPlatformPerms * 100) ?>%"></i></span>
                </span>
              </td>
              <td><span class="pill pill--<?= strtolower(str_replace(' ', '-', $u['status'])) ?>"><?= $u['status'] ?></span></td>
              <td class="ta-center">
                <?php if ($u['twofa']): ?><i class="fa-solid fa-circle-check yn--yes" title="2FA enabled"></i>
                <?php else: ?><i class="fa-solid fa-circle-xmark yn--no" title="2FA not set up"></i><?php endif; ?>
              </td>
              <td class="nowrap <?= $u['last'] === 'Never' ? 'muted' : '' ?>">
                <span class="stack"><strong><?= $u['last'] ?></strong>
                  <small><?= $u['lastFull'] ?></small></span>
              </td>
              <td>
                <?php if ($u['device'] === '—'): ?><span class="muted">&mdash;</span>
                <?php else: ?>
                  <?php [$brw, $os] = array_map('trim', explode('·', $u['device'])); ?>
                  <button class="devcell" type="button" data-modal="modalDevice">
                    <i class="fa-solid <?= $devIcon ?> devicon"></i>
                    <span class="stack"><strong><?= $brw ?></strong><small><?= $os ?></small></span>
                  </button>
                <?php endif; ?>
              </td>
              <td class="nowrap muted"><?= $u['created'] ?></td>
              <td class="col-actions">
                <div class="row-actions">
                  <button class="ico-btn" type="button" title="View Profile" aria-label="View Profile" data-modal="modalViewUser"><i class="fa-regular fa-eye"></i></button>
                  <button class="ico-btn" type="button" title="Edit User" aria-label="Edit User" data-modal="modalUser"><i class="fa-regular fa-pen-to-square"></i></button>
                  <button class="ico-btn" type="button" title="Reset Password" aria-label="Reset Password" data-modal="modalReset"><i class="fa-solid fa-key"></i></button>
                  <div class="dropdown">
                    <button class="ico-btn dropdown__trigger" type="button" title="More" aria-label="More actions"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                    <div class="dropdown__menu dropdown__menu--right">
                      <a href="#" data-modal="modalViewUser"><i class="fa-regular fa-eye"></i> View Profile</a>
                      <a href="#" data-modal="modalUser"><i class="fa-regular fa-pen-to-square"></i> Edit User</a>
                      <a href="#" data-modal="modalReset"><i class="fa-solid fa-key"></i> Reset Password</a>
                      <a href="#"><i class="fa-regular fa-paper-plane"></i> Resend Invite</a>
                      <a href="#"><i class="fa-solid fa-rotate-left"></i> Force Password Change</a>
                      <span class="dropdown__sep"></span>
                      <a href="<?= $base_url ?>monitor/activity.php"><i class="fa-solid fa-wave-square"></i> View Activity Log</a>
                      <a href="<?= $base_url ?>monitor/logins.php"><i class="fa-solid fa-right-to-bracket"></i> View Login History</a>
                      <?php if ($u['owner']): ?>
                        <span class="dropdown__sep"></span>
                        <span class="dropdown__note"><i class="fa-solid fa-lock"></i> The Owner account cannot be modified</span>
                      <?php else: ?>
                        <span class="dropdown__sep"></span>
                        <a href="#" data-modal="modalSuspend"><i class="fa-solid fa-ban"></i> Suspend Account</a>
                        <a href="#" class="is-danger" data-modal="modalDeleteUser"><i class="fa-solid fa-trash"></i> Delete</a>
                      <?php endif; ?>
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
      <span class="empty__icon"><i class="fa-solid fa-user-shield"></i></span>
      <p class="empty__title">No users found</p>
      <p class="empty__text">No staff account matches the filters you have applied. Try widening your search.</p>
      <button class="btn btn--primary" type="button">Reset Filters</button>
    </div>

    <div class="tablefoot">
      <p class="tablefoot__count">Showing 1 to <?= count($users) ?> of <?= count($users) ?> entries</p>
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

<!-- ═══════════ ADD / EDIT USER ═══════════ -->
<div class="modal" id="modalUser" hidden>
  <div class="modal__box">
    <div class="modal__head">
      <h2><i class="fa-solid fa-user-plus"></i> Add Staff User</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <p class="modal__lead">This creates an account on <strong>your</strong> team, not inside a church.
        Church admin accounts are managed under Access &amp; Licensing.</p>

      <section class="msec">
        <h3 class="msec__title">Details</h3>
        <div class="field-row">
          <label class="field"><span class="field__label">Full name</span>
            <input type="text" placeholder="e.g. Tapiwa Chirwa"></label>
          <label class="field"><span class="field__label">Email</span>
            <input type="email" placeholder="name@mutendi.co.zw"></label>
        </div>
        <div class="field-row">
          <label class="field"><span class="field__label">Phone</span>
            <input type="tel" placeholder="+263 77 000 0000"></label>
          <label class="field"><span class="field__label">Status</span>
            <select><option>Pending Invite</option><option>Active</option><option>Suspended</option></select></label>
        </div>
        <label class="field"><span class="field__label">Role</span>
          <select id="userRole">
            <?php foreach (['Administrator','Support Agent','Sales'] as $r): ?><option><?= $r ?></option><?php endforeach; ?>
          </select></label>
        <p class="fieldnote">The Owner role cannot be assigned — there is exactly one Owner account.</p>
      </section>

      <section class="msec">
        <h3 class="msec__title">What this role can do</h3>
        <?php
          /* LATER: read these summaries from the role record itself so they
             stay in step with admin/roles.php. */
          $roleBlurbs = [
            'Administrator' => ['tone'=>'indigo','perms'=>38,
              'can'  => ['Everything across churches, modules and reports','Manage church admin accounts','Record payments and extend subscriptions','Read the full activity and error logs'],
              'cant' => ['Add, edit or remove super admin users','Permanently delete church data']],
            'Support Agent' => ['tone'=>'green','perms'=>17,
              'can'  => ['View any church and its setup','Log in as a church for support','Reset church admin passwords','Read activity and login history'],
              'cant' => ['Delete any data','Record payments or extend subscriptions','Manage staff accounts']],
            'Sales'         => ['tone'=>'gold','perms'=>13,
              'can'  => ['Register new churches','Activate and extend subscriptions','Record payments','View renewal and growth reports'],
              'cant' => ['Open church member data','Delete anything','Reach the monitoring pages']],
          ];
        ?>
        <?php foreach ($roleBlurbs as $name => $b): ?>
          <div class="roleblurb" data-roleblurb="<?= $name ?>"<?= $name === 'Administrator' ? '' : ' hidden' ?>>
            <div class="roleblurb__top">
              <span class="role role--<?= $b['tone'] ?>"><?= $name ?></span>
              <span class="muted"><?= $b['perms'] ?> of <?= $totalPlatformPerms ?> permissions</span>
            </div>
            <ul class="canlist">
              <?php foreach ($b['can'] as $c): ?>
                <li><i class="fa-solid fa-check yn--yes"></i><span><?= $c ?></span></li>
              <?php endforeach; ?>
              <?php foreach ($b['cant'] as $c): ?>
                <li><i class="fa-solid fa-xmark yn--no"></i><span class="muted"><?= $c ?></span></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endforeach; ?>
      </section>

      <section class="msec">
        <h3 class="msec__title">On creation</h3>
        <label class="check-row"><input type="checkbox" checked><span>Send invite email</span></label>
        <label class="check-row"><input type="checkbox" checked><span>Require 2FA on this account</span></label>
        <label class="check-row"><input type="checkbox" checked><span>Require password change on first login</span></label>
      </section>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--primary" type="button">Save User</button>
    </div>
  </div>
</div>

<!-- ═══════════ RESET PASSWORD ═══════════ -->
<div class="modal" id="modalReset" hidden>
  <div class="modal__box modal__box--sm">
    <div class="modal__head">
      <h2><i class="fa-solid fa-key"></i> Reset Password</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <dl class="summary">
        <div><dt>Name</dt><dd>Nyasha Mudimu</dd></div>
        <div><dt>Email</dt><dd>nyasha@mutendi.co.zw</dd></div>
      </dl>
      <span class="field__label">New password</span>
      <div class="radios">
        <label class="radio"><input type="radio" name="pwmode" id="pwAuto" checked><span>Generate a random password</span></label>
        <label class="radio"><input type="radio" name="pwmode" id="pwManual"><span>Set it manually</span></label>
      </div>
      <label class="field" id="pwManualField" hidden><span class="field__label">Password</span>
        <input type="text" placeholder="At least 12 characters"></label>
      <span class="field__label">Send it by</span>
      <label class="check-row check-row--slim"><input type="checkbox" checked><span>Email &mdash; nyasha@mutendi.co.zw</span></label>
      <label class="check-row check-row--slim"><input type="checkbox"><span>SMS &mdash; +263 78 330 5517</span></label>
      <p class="notebox"><i class="fa-solid fa-circle-info"></i>
        They will be asked to choose their own password the next time they sign in.</p>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--primary" type="button">Reset Password</button>
    </div>
  </div>
</div>

<!-- ═══════════ VIEW PROFILE ═══════════ -->
<div class="modal" id="modalViewUser" hidden>
  <div class="modal__box modal__box--lg">
    <div class="modal__head">
      <h2><i class="fa-regular fa-user"></i> Staff Profile</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <div class="uhead">
        <span class="uhead__avatar">TC</span>
        <div class="uhead__text">
          <strong>Tapiwa Chirwa</strong>
          <small>tapiwa@mutendi.co.zw &middot; +263 71 665 2043</small>
          <span class="uhead__tags">
            <span class="role role--indigo">Administrator</span>
            <span class="pill pill--active">Active</span>
          </span>
        </div>
      </div>

      <section class="msec">
        <h3 class="msec__title">Account</h3>
        <dl class="summary">
          <div><dt>Permissions</dt><dd>38 of <?= $totalPlatformPerms ?></dd></div>
          <div><dt>Two-factor</dt><dd><i class="fa-solid fa-circle-check yn--yes"></i> Enabled &middot; authenticator app</dd></div>
          <div><dt>Created</dt><dd>17 Mar 2025</dd></div>
          <div><dt>Last login</dt><dd>25 Aug 2026, 14:08 (35 minutes ago)</dd></div>
          <div><dt>Total logins</dt><dd>612</dd></div>
          <div><dt>Last IP</dt><dd><code class="keytext">41.221.16.90</code> &middot; Econet Wireless</dd></div>
          <div><dt>Last device</dt><dd>
            <button class="devcell" type="button" data-modal="modalDevice">
              <i class="fa-solid fa-desktop devicon"></i><span>Edge 128 &middot; Windows 11</span></button></dd></div>
          <div><dt>Location</dt><dd>Harare, Harare</dd></div>
        </dl>
      </section>

      <section class="msec">
        <h3 class="msec__title">Recent Activity</h3>
        <ul class="feed feed--flat">
          <?php foreach ([['Extended ZCC Mbungo by 12 months','14:32','blue'],
                          ['Reset the password for AFM Waterfalls','13:48','amber'],
                          ['Enabled the Sermons & Media module for 4 churches','12:22','blue'],
                          ['Activated Grace Revival Church','11:20','green'],
                          ['Exported the renewals report for August','09:11','blue']] as [$txt,$t,$tone]): ?>
            <li class="feed__row"><span class="dot dot--<?= $tone ?>"></span>
              <span class="feed__text"><?= $txt ?><small>25 Aug 2026, <?= $t ?></small></span></li>
          <?php endforeach; ?>
        </ul>
      </section>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Close</button>
      <button class="btn" type="button" data-modal="modalReset"><i class="fa-solid fa-key"></i> Reset Password</button>
      <a class="btn" href="<?= $base_url ?>monitor/activity.php"><i class="fa-solid fa-wave-square"></i> View Activity Log</a>
      <button class="btn btn--primary" type="button" data-modal="modalUser"><i class="fa-regular fa-pen-to-square"></i> Edit</button>
    </div>
  </div>
</div>

<!-- ═══════════ SUSPEND ═══════════ -->
<div class="modal" id="modalSuspend" hidden>
  <div class="modal__box modal__box--sm">
    <div class="modal__head">
      <h2><i class="fa-solid fa-triangle-exclamation note--gold"></i> Suspend Account</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <p class="modal__lead">Suspending <strong>Kudzai Sibanda</strong> blocks them from signing in.
        Their account, permissions and activity history are all kept.</p>
      <label class="field"><span class="field__label">Reason</span>
        <textarea rows="3" placeholder="Why is this account being suspended?"></textarea></label>
      <label class="check-row"><input type="checkbox" checked><span>End all active sessions immediately</span></label>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--danger-solid" type="button">Suspend Account</button>
    </div>
  </div>
</div>

<!-- ═══════════ DELETE ═══════════ -->
<div class="modal" id="modalDeleteUser" hidden>
  <div class="modal__box modal__box--sm">
    <div class="modal__head">
      <h2><i class="fa-solid fa-trash note--berry"></i> Delete Staff User</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <p class="modal__warn"><i class="fa-solid fa-triangle-exclamation"></i>
        This removes <strong>Kudzai Sibanda</strong> from the platform for good. Their entries in the
        activity log are kept, so the record of what they did stays intact.</p>
      <label class="field"><span class="field__label">Type <code class="keytext">kudzai@mutendi.co.zw</code> to confirm</span>
        <input type="text" placeholder="kudzai@mutendi.co.zw"></label>
      <p class="notebox"><i class="fa-solid fa-circle-info"></i>
        Consider suspending instead if they may return &mdash; it is reversible.</p>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--danger-solid" type="button">Delete User</button>
    </div>
  </div>
</div>

<?php device_modal(); ?>
<script>
/* Shared chrome: dropdowns, tabs, modals, copy buttons, bulk bars and the
   permission matrix. */
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

  /* Permission matrix — same behaviour as the church role templates in
     setup/index.php, over the platform permission catalogue. */
  var permBoxes = [].slice.call(document.querySelectorAll(".permbox")),
      permCount = document.getElementById("permCount");

  function tallyPerms() {
    if (permCount) { permCount.textContent = permBoxes.filter(function (b) { return b.checked; }).length; }
    document.querySelectorAll("[data-permall]").forEach(function (master) {
      var group = master.dataset.permall,
          kids  = permBoxes.filter(function (b) { return b.dataset.permgroup === group; }),
          on    = kids.filter(function (b) { return b.checked; }).length;
      master.checked = on === kids.length && on > 0;
      master.indeterminate = on > 0 && on < kids.length;
    });
  }
  permBoxes.forEach(function (b) { b.addEventListener("change", tallyPerms); });
  document.querySelectorAll("[data-permall]").forEach(function (master) {
    master.addEventListener("change", function () {
      permBoxes.filter(function (b) { return b.dataset.permgroup === master.dataset.permall; })
               .forEach(function (b) { b.checked = master.checked; });
      tallyPerms();
    });
  });
  document.querySelectorAll(".permgroup__toggle").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var grp = btn.closest(".permgroup"),
          open = grp.classList.toggle("is-open");
      btn.setAttribute("aria-expanded", String(open));
    });
  });
  tallyPerms();})();
</script>
<script>
/* Page-specific: the role summary panel and the manual-password field. */
(function () {
  var role = document.getElementById('userRole');
  if (role) {
    var syncRole = function () {
      document.querySelectorAll('[data-roleblurb]').forEach(function (b) {
        b.hidden = b.dataset.roleblurb !== role.value;
      });
    };
    role.addEventListener('change', syncRole); syncRole();
  }
  var manual = document.getElementById('pwManual'),
      auto   = document.getElementById('pwAuto'),
      field  = document.getElementById('pwManualField');
  if (manual && field) {
    var syncPw = function () { field.hidden = !manual.checked; };
    manual.addEventListener('change', syncPw);
    auto.addEventListener('change', syncPw);
    syncPw();
  }
})();
</script>
</body>
</html>

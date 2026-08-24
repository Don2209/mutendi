<?php
/**
 * Mutendi CMS — Roles & Permissions (static UI mockup).
 *
 * PLATFORM roles for the vendor's own staff — what a salesperson or support
 * agent may do inside the super admin panel. These are NOT the church-side
 * role templates (Pastor, Secretary, Treasurer…), which live in setup/index.php
 * and govern what a member of a church's own staff can do inside their church.
 *
 * Every dataset is hardcoded; each block carries the query that replaces it.
 */

$musPathsOnly = true;
require __DIR__ . '/../components/sidebar.php';
$musPathsOnly = false;

/* ── Platform permission catalogue ─────────────────────────────────────────
   LATER: SELECT * FROM platform_permissions ORDER BY group_order, sort_order;

   Each row is [label, description, actions, high-risk?]. The actions list is
   what that permission can be granted for; anything not listed shows as "—".
   The count of every action across every group is the platform total. */
$permGroups = [
    'Dashboard' => [
        ['Dashboard', 'The headline figures and charts on the landing screen.', ['View'], false],
    ],
    'Churches' => [
        ['Church records',     'Open, register and edit subscribed churches.',         ['View','Add','Edit'], false],
        ['Activate & extend',  'Activate a pending church and extend a subscription.', ['Edit'],              false],
        ['Record payments',    'Log a renewal payment against a church.',              ['Add','Edit'],        false],
        ['Church member data', 'Read the members, families and registers inside a church.', ['View'],         false],
        ['Archive church',     'Move a church out of active use, reversibly.',         ['Edit'],              false],
        ['Church notes',       'Internal notes kept against a church account.',        ['View','Add','Edit'], false],
    ],
    'Church Admins' => [
        ['Church admin accounts', 'The admin users belonging to each church.',   ['View','Add','Edit'], false],
        ['Reset admin password',  'Issue a new password to a church admin.',     ['Edit'],              false],
    ],
    'Modules' => [
        ['Module access', 'Turn modules on or off for a church.', ['View','Edit'], false],
    ],
    'Setup Data' => [
        ['Setup data', 'Role templates, contribution types and member fields.', ['View','Edit'], false],
    ],
    'Communication' => [
        ['Announcements',     'Publish notices to churches.',            ['View','Add','Edit'], false],
        ['Send notifications','Send an SMS or email to church admins.',  ['Add'],               false],
        ['Message log',       'What was sent, to whom and whether it arrived.', ['View'],            false],
    ],
    'Reports' => [
        ['Growth & usage report', 'Sign-ups, active churches and engagement.', ['View'], false],
        ['Renewals report',       'What is due, renewed or lost.',             ['View'], false],
        ['Export church data',    'Download church records as a file.',        ['View'], true],
        ['Scheduled reports',     'Reports that run and send themselves.',      ['View'], false],
    ],
    'Monitoring' => [
        ['View activity log',  'Every action taken across the platform.', ['View'], true],
        ['Login history',      'All sign-in attempts and their devices.',  ['View'], false],
        ['Error log',          'System errors and exceptions.',            ['View','Edit'], false],
        ['Database health',    'Size, growth and slow queries.',           ['View'], false],
    ],
    'Administration' => [
        ['Manage super admin users', 'Add, edit and remove vendor staff accounts.', ['View','Add','Edit'], true],
        ['Manage roles',             'Change what each staff role may do.',         ['View','Edit'],       false],
    ],
    'Impersonation' => [
        ['Login as church', 'Enter a church as one of its admins, for support.', ['Edit'], true],
    ],
    'Data Deletion' => [
        ['Permanently delete church data', 'Erase a church and everything in it. Cannot be undone.', ['Delete'], true],
        ['Delete records',                 'Remove individual records inside a church.',             ['Delete'], false],
    ],
];
$totalPerms = 0;
foreach ($permGroups as $rows) { foreach ($rows as $r) { $totalPerms += count($r[2]); } }

/* ── Staff roles ───────────────────────────────────────────────────────────
   LATER:
     SELECT r.*, COUNT(DISTINCT rp.permission_id) AS perms,
            (SELECT COUNT(*) FROM staff_users WHERE role_id = r.id) AS users
       FROM staff_roles r
       LEFT JOIN staff_role_permissions rp ON rp.role_id = r.id
      GROUP BY r.id ORDER BY r.sort_order; */
$roles = [
 ['name'=>'Owner','key'=>'owner','icon'=>'fa-crown','tone'=>'brand','system'=>true,'locked'=>true,
  'desc'=>'Full access to everything, including staff accounts and permanent deletion.',
  'users'=>1,
  'areas'=>['Churches','Administration','Data Deletion','Impersonation']],

 ['name'=>'Administrator','key'=>'administrator','icon'=>'fa-user-shield','tone'=>'indigo','system'=>true,'locked'=>false,
  'desc'=>'Runs the platform day to day — everything except staff accounts and permanent deletion.',
  'users'=>1,
  'areas'=>['Churches','Reports','Monitoring','Communication']],

 ['name'=>'Support Agent','key'=>'support_agent','icon'=>'fa-headset','tone'=>'green','system'=>false,'locked'=>false,
  'desc'=>'Helps churches with problems. Can look and assist, but cannot delete or take payment.',
  'users'=>2,
  'areas'=>['Churches','Church Admins','Monitoring','Impersonation']],

 ['name'=>'Sales','key'=>'sales','icon'=>'fa-handshake','tone'=>'gold','system'=>false,'locked'=>false,
  'desc'=>'Brings churches on board and keeps them subscribed. No access to member data.',
  'users'=>1,
  'areas'=>['Churches','Reports']],
];

/* ── Which roles hold which permission ─────────────────────────────────────
   LATER: SELECT role_id, permission_id, action FROM staff_role_permissions;

   Keyed by permission label; the value lists the roles that hold it. Owner is
   omitted throughout because Owner always holds everything. */
$grants = [
    'Dashboard'                       => ['administrator','support_agent','sales'],
    'Church records'                  => ['administrator','support_agent','sales'],
    'Activate & extend'               => ['administrator','sales'],
    'Record payments'                 => ['administrator','sales'],
    'Church member data'              => ['administrator','support_agent'],
    'Archive church'                  => ['administrator'],
    'Church notes'                    => ['administrator','support_agent','sales'],
    'Church admin accounts'           => ['administrator','support_agent'],
    'Reset admin password'            => ['administrator','support_agent'],
    'Module access'                   => ['administrator'],
    'Setup data'                      => ['administrator'],
    'Announcements'                   => ['administrator'],
    'Send notifications'              => ['administrator','support_agent'],
    'Message log'                     => ['administrator','support_agent'],
    'Growth & usage report'           => ['administrator','sales'],
    'Renewals report'                 => ['administrator','sales'],
    'Export church data'              => ['administrator'],
    'Scheduled reports'               => ['administrator','sales'],
    'View activity log'               => ['administrator','support_agent'],
    'Login history'                   => ['administrator','support_agent'],
    'Error log'                       => ['administrator'],
    'Database health'                 => ['administrator'],
    'Manage super admin users'        => [],
    'Manage roles'                    => ['administrator'],
    'Login as church'                 => ['administrator','support_agent'],
    'Permanently delete church data'  => [],
    'Delete records'                  => ['administrator'],
];

/* Each role's permission count is counted off $grants rather than typed in, so
   the cards, the matrix and the role editor can never disagree with each other.
   admin/users.php carries the same figures — keep the two in step. */
foreach ($roles as &$r) {
    $n = 0;
    foreach ($permGroups as $rows) {
        foreach ($rows as [$label, , $actions]) {
            if ($r['key'] === 'owner' || in_array($r['key'], $grants[$label] ?? [], true)) {
                $n += count($actions);
            }
        }
    }
    $r['perms'] = $n;
}
unset($r);
$activePage    = 'admin/roles';
$sidebarBadges = ['pending' => 10, 'expiring' => 12];
$sidebarUser   = ['name' => 'Super Admin', 'role' => 'System Owner'];
$pageTitle     = 'Roles & Permissions';
$pageHint      = 'Control what each staff role can access and do on the platform.';
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
      <button class="btn btn--primary" type="button" data-modal="modalRole"><i class="fa-solid fa-plus"></i> Add Role</button>
    </div>
  </div>

  <?php
    $systemRoles = count(array_filter($roles, fn ($r) => $r['system']));
    $tiles = [
        ['Total Roles',       (string) count($roles),                'indigo', 'fa-user-tag', true],
        ['System Roles',      (string) $systemRoles,                 'brand',  'fa-lock',     false],
        ['Custom Roles',      (string) (count($roles) - $systemRoles),'grey',  'fa-sliders',  false],
        ['Total Permissions', (string) $totalPerms,                  'green',  'fa-key',      false],
    ];
  ?>
  <div class="statstrip">
    <?php foreach ($tiles as [$l,$v,$t,$ic,$on]): ?>
      <a class="stat-tile stat-tile--<?= $t ?><?= $on ? ' is-on' : '' ?>" href="#">
        <span class="stat-tile__icon"><i class="fa-solid <?= $ic ?>"></i></span>
        <span class="stat-tile__body"><span class="stat-tile__value"><?= $v ?></span>
          <span class="stat-tile__label"><?= $l ?></span></span></a>
    <?php endforeach; ?>
  </div>

  <p class="notebox notebox--wide"><i class="fa-solid fa-circle-info"></i>
    These are <strong>platform</strong> permissions &mdash; what your own staff may do inside this panel.
    The role templates a church uses for its pastors, secretaries and treasurers are separate, and live
    under <a href="<?= $base_url ?>setup/index.php">Setup Data</a>.</p>

  <div class="tabs">
    <button class="tab is-on" type="button" data-tab="roles">Roles</button>
    <button class="tab" type="button" data-tab="matrix">Permission Matrix</button>
  </div>

  <!-- ═══════════════ TAB 1 — ROLE CARDS ═══════════════ -->
  <div class="tabpanel" data-panel="roles">
    <div class="modgrid">
      <?php foreach ($roles as $r): ?>
        <div class="modcard">
          <div class="modcard__top">
            <span class="modcard__icon modcard__icon--<?= $r['tone'] ?>"><i class="fa-solid <?= $r['icon'] ?>"></i></span>
            <span class="modcard__head">
              <strong><?= htmlspecialchars($r['name']) ?></strong>
              <code class="keytext"><?= $r['key'] ?></code>
            </span>
            <?php if ($r['locked']): ?>
              <i class="fa-solid fa-lock modcard__lock" title="The Owner role cannot be edited or deleted"></i>
            <?php endif; ?>
          </div>

          <p class="modcard__desc"><?= htmlspecialchars($r['desc']) ?></p>
          <span class="pill <?= $r['system'] ? 'pill--system' : 'pill--custom' ?> modcard__type">
            <?= $r['system'] ? 'System Role' : 'Custom' ?>
          </span>

          <div class="permline">
            <span class="permline__num"><strong><?= $r['perms'] ?></strong> of <?= $totalPerms ?> permissions</span>
            <span class="bar"><i style="width: <?= (int) round($r['perms'] / $totalPerms * 100) ?>%"></i></span>
          </div>

          <p class="modcard__users"><i class="fa-solid fa-users"></i>
            <?= $r['users'] ?> user<?= $r['users'] === 1 ? '' : 's' ?> assigned</p>

          <div class="minichips">
            <?php foreach ($r['areas'] as $a): ?><span class="minichip"><?= $a ?></span><?php endforeach; ?>
          </div>

          <div class="modcard__foot">
            <?php if ($r['locked']): ?>
              <span class="modcard__locked"><i class="fa-solid fa-lock"></i> Locked</span>
              <a href="#" data-modal="modalRoleUsers">View Users</a>
            <?php else: ?>
              <a href="#" data-modal="modalRole">Edit Permissions</a>
              <a href="#" data-modal="modalRole">Duplicate</a>
              <a href="#" data-modal="modalRoleUsers">View Users</a>
              <a href="#" class="is-danger" data-modal="modalDeleteRole">Delete</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ═══════════════ TAB 2 — PERMISSION MATRIX ═══════════════ -->
  <div class="tabpanel" data-panel="matrix" hidden>
    <div class="card">
      <div class="card__head">
        <h2>Platform Permission Matrix</h2>
        <span class="card__note">Owner holds everything and cannot be changed</span>
      </div>
      <div class="table-wrap">
        <table class="table table--matrix table--freeze">
          <thead>
            <tr>
              <th class="matrix__perm">Permission</th>
              <?php foreach ($roles as $r): ?>
                <th class="ta-center">
                  <span class="matrix__role">
                    <span class="modcard__icon modcard__icon--<?= $r['tone'] ?> modcard__icon--sm"><i class="fa-solid <?= $r['icon'] ?>"></i></span>
                    <span><?= htmlspecialchars($r['name']) ?></span>
                    <?php if ($r['locked']): ?><i class="fa-solid fa-lock matrix__lock" title="Locked"></i><?php endif; ?>
                  </span>
                </th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($permGroups as $group => $rows): ?>
              <?php $gid = 'mg' . preg_replace('/[^a-z0-9]/i', '', $group); ?>
              <tr class="table__group" data-mgroup="<?= $gid ?>">
                <td colspan="<?= count($roles) + 1 ?>">
                  <button class="groupbtn" type="button" data-mtoggle="<?= $gid ?>" aria-expanded="true">
                    <i class="fa-solid fa-chevron-down"></i> <?= htmlspecialchars($group) ?>
                    <span class="table__groupcount"><?= count($rows) ?></span>
                  </button>
                </td>
              </tr>
              <?php foreach ($rows as [$label, $desc, $actions, $risky]): ?>
                <tr data-mrow="<?= $gid ?>">
                  <td class="matrix__perm">
                    <span class="church__text">
                      <strong><?= $risky ? '<i class="fa-solid fa-triangle-exclamation note--berry"></i> ' : '' ?><?= htmlspecialchars($label) ?></strong>
                      <small><?= htmlspecialchars($desc) ?> &middot; <?= implode(' / ', $actions) ?></small>
                    </span>
                  </td>
                  <?php foreach ($roles as $r): ?>
                    <td class="ta-center">
                      <?php if ($r['locked']): ?>
                        <i class="fa-solid fa-check yn--yes" title="Owner always holds every permission"></i>
                      <?php else: ?>
                        <label class="switch" title="<?= htmlspecialchars($r['name'] . ' — ' . $label) ?>">
                          <input type="checkbox"<?= in_array($r['key'], $grants[$label] ?? [], true) ? ' checked' : '' ?>>
                          <span class="switch__track"></span>
                        </label>
                      <?php endif; ?>
                    </td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card__foot card__foot--split">
        <p class="muted">Changes apply the next time each user signs in.</p>
        <button class="btn btn--primary" type="button"><i class="fa-solid fa-check"></i> Save Changes</button>
      </div>
    </div>
  </div>
  <footer class="foot">
    <p class="foot__brand">Mutendi CMS</p>
    <p class="foot__copy">&copy; <?= date('Y') ?> Mutendi. All rights reserved.</p>
    <p class="foot__meta">Super Admin &middot; v1.0</p>
  </footer>

</main>

<!-- ═══════════ ADD / EDIT ROLE ═══════════ -->
<div class="modal" id="modalRole" hidden>
  <div class="modal__box modal__box--lg">
    <div class="modal__head">
      <h2><i class="fa-solid fa-user-tag"></i> Staff Role</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <section class="msec">
        <h3 class="msec__title">Details</h3>
        <div class="field-row">
          <label class="field"><span class="field__label">Role name</span>
            <input type="text" value="Support Agent"></label>
          <label class="field"><span class="field__label">System key</span>
            <input type="text" value="support_agent" class="keyinput"></label>
        </div>
        <label class="field"><span class="field__label">Description</span>
          <textarea rows="2">Helps churches with problems. Can look and assist, but cannot delete or take payment.</textarea></label>

        <span class="field__label">Icon</span>
        <div class="iconpick">
          <?php foreach (['fa-crown','fa-user-shield','fa-headset','fa-handshake','fa-user-tag',
                          'fa-key','fa-briefcase','fa-chart-line','fa-life-ring','fa-star'] as $i => $ic): ?>
            <label class="iconpick__opt"><input type="radio" name="staffroleicon"<?= $i === 2 ? ' checked' : '' ?>>
              <span><i class="fa-solid <?= $ic ?>"></i></span></label>
          <?php endforeach; ?>
        </div>

        <span class="field__label">Colour</span>
        <div class="swatches">
          <?php foreach (['brand','green','gold','berry','indigo','grey'] as $i => $tone): ?>
            <label class="swatch"><input type="radio" name="stafffroletone"<?= $i === 1 ? ' checked' : '' ?>>
              <span class="swatch__dot swatch__dot--<?= $tone ?>"></span></label>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="msec">
        <h3 class="msec__title">Platform Permissions</h3>
        <p class="msec__note">What this role may do inside the super admin panel. Anything flagged
          <i class="fa-solid fa-triangle-exclamation note--berry"></i> is high-risk &mdash; grant it deliberately.</p>

        <?php foreach ($permGroups as $group => $rows): ?>
          <?php $gid = 'pg' . preg_replace('/[^a-z0-9]/i', '', $group); ?>
          <div class="permgroup is-open">
            <div class="permgroup__head">
              <label class="permgroup__all">
                <input type="checkbox" class="bigcheck" data-permall="<?= $gid ?>">
                <span><?= htmlspecialchars($group) ?></span>
              </label>
              <button class="permgroup__toggle" type="button" aria-expanded="true">
                <i class="fa-solid fa-chevron-down"></i>
              </button>
            </div>
            <div class="permgroup__body">
              <?php foreach ($rows as [$label, $desc, $actions, $risky]): ?>
                <div class="permrow<?= $risky ? ' permrow--risky' : '' ?>">
                  <span class="permrow__label">
                    <?= $risky ? '<i class="fa-solid fa-triangle-exclamation note--berry"></i> ' : '' ?><?= htmlspecialchars($label) ?>
                    <small><?= htmlspecialchars($desc) ?></small>
                  </span>
                  <span class="permrow__acts">
                    <?php foreach (['View','Add','Edit','Delete'] as $act): ?>
                      <?php if (in_array($act, $actions, true)): ?>
                        <label class="permact"><input type="checkbox" class="permbox" data-permgroup="<?= $gid ?>"<?= in_array('support_agent', $grants[$label] ?? [], true) ? ' checked' : '' ?>><span><?= $act ?></span></label>
                      <?php else: ?>
                        <span class="permact permact--na"><span>&mdash;</span></span>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>

        <p class="permtotal"><strong id="permCount">0</strong> of <?= $totalPerms ?> permissions selected</p>
      </section>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--primary" type="button">Save Role</button>
    </div>
  </div>
</div>

<!-- ═══════════ VIEW USERS IN A ROLE ═══════════ -->
<div class="modal" id="modalRoleUsers" hidden>
  <div class="modal__box">
    <div class="modal__head">
      <h2><i class="fa-solid fa-users"></i> Users with the Support Agent role</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <p class="modal__lead">Two staff accounts currently hold this role.</p>
      <ul class="picklist">
        <?php
          /* LATER: SELECT name, email, status FROM staff_users WHERE role_id = :role; */
          $roleUsers = [
            ['NM','Nyasha Mudimu','nyasha@mutendi.co.zw','Active'],
            ['KS','Kudzai Sibanda','kudzai@mutendi.co.zw','Suspended'],
          ];
        ?>
        <?php foreach ($roleUsers as [$in,$name,$email,$status]): ?>
          <li class="picklist__row">
            <span class="church">
              <span class="church__avatar"><?= $in ?></span>
              <span class="church__text"><strong><?= htmlspecialchars($name) ?></strong><small><?= $email ?></small></span>
            </span>
            <span class="picklist__end">
              <span class="pill pill--<?= strtolower($status) ?>"><?= $status ?></span>
              <button class="btn btn--sm" type="button">Reassign</button>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Close</button>
      <a class="btn btn--primary" href="<?= $base_url ?>admin/users.php"><i class="fa-solid fa-user-shield"></i> Open Super Admin Users</a>
    </div>
  </div>
</div>

<!-- ═══════════ DELETE ROLE ═══════════ -->
<div class="modal" id="modalDeleteRole" hidden>
  <div class="modal__box modal__box--sm">
    <div class="modal__head">
      <h2><i class="fa-solid fa-trash note--berry"></i> Delete Role</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <p class="modal__warn"><i class="fa-solid fa-triangle-exclamation"></i>
        <strong>2 users</strong> currently hold the Support Agent role. They must be moved to another
        role before this one can be removed.</p>
      <label class="field"><span class="field__label">Move those users to</span>
        <select>
          <?php foreach ($roles as $r): ?>
            <?php if ($r['key'] !== 'support_agent'): ?><option><?= htmlspecialchars($r['name']) ?></option><?php endif; ?>
          <?php endforeach; ?>
        </select></label>
      <label class="field"><span class="field__label">Type <code class="keytext">support_agent</code> to confirm</span>
        <input type="text" placeholder="support_agent"></label>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--danger-solid" type="button">Delete Role</button>
    </div>
  </div>
</div>
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
/* Page-specific: collapsible groups inside the wide permission matrix. */
(function () {
  document.querySelectorAll('[data-mtoggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var gid  = btn.dataset.mtoggle,
          open = btn.getAttribute('aria-expanded') !== 'true';
      btn.setAttribute('aria-expanded', String(open));
      btn.classList.toggle('is-shut', !open);
      document.querySelectorAll('[data-mrow="' + gid + '"]').forEach(function (r) { r.hidden = !open; });
    });
  });
})();
</script>
</body>
</html>

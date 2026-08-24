<?php
/**
 * Mutendi CMS — Setup Data (static UI mockup).
 *
 * Global reference data that every NEW church inherits at creation, so no
 * church starts empty and none needs configuring by hand. Nothing here is
 * per-church. Every dataset is hardcoded; each block carries the query that
 * replaces it. Sorting, drag-reordering and all form actions are visual only.
 */

/* Path constants only — the sidebar markup is required further down. */
$musPathsOnly = true;
require __DIR__ . '/../components/sidebar.php';
$musPathsOnly = false;

/* ── Permission catalogue ──────────────────────────────────────────────────
   LATER: SELECT * FROM permissions ORDER BY group_order, sort_order;
   Each row lists the actions that permission supports; the checkbox count
   across every group is the system total. */
$permGroups = [
    'Members'                 => [['Member records', ['View','Add','Edit','Delete']], ['Import & export', ['View','Add']]],
    'Attendance'              => [['Attendance registers', ['View','Add','Edit','Delete']]],
    'Departments'             => [['Departments', ['View','Add','Edit','Delete']]],
    'Finance & Contributions' => [['Contributions', ['View','Add','Edit','Delete']], ['Financial reports', ['View']]],
    'Cell Groups'             => [['Cell groups', ['View','Add','Edit','Delete']]],
    'Events'                  => [['Events & calendar', ['View','Add','Edit','Delete']]],
    'Communication'           => [['SMS & email', ['View','Add']], ['Templates', ['View','Add','Edit']]],
    'Reports'                 => [['Standard reports', ['View']], ['Custom reports', ['View','Add','Edit','Delete']]],
    'Settings'                => [['Church settings', ['View','Edit']], ['User accounts', ['View','Add','Edit']]],
];
$totalPerms = 0;
foreach ($permGroups as $rows) { foreach ($rows as $r) { $totalPerms += count($r[1]); } }

/* ── TAB 1: Role templates ─────────────────────────────────────────────────
   LATER:
     SELECT r.*, COUNT(rp.permission_id) AS perms
       FROM role_templates r
       LEFT JOIN role_template_permissions rp ON rp.role_id = r.id
      GROUP BY r.id ORDER BY r.sort_order; */
$roles = [
    ['name'=>'Church Admin','key'=>'church_admin','icon'=>'fa-user-shield','tone'=>'brand','desc'=>'Full access to every module and setting in the church.','default'=>true,'locked'=>true,'perms'=>42,'areas'=>['Members','Finance','Settings','Reports']],
    ['name'=>'Pastor','key'=>'pastor','icon'=>'fa-hands-praying','tone'=>'indigo','desc'=>'Oversight of members, attendance and reporting.','default'=>true,'locked'=>false,'perms'=>28,'areas'=>['Members','Attendance','Reports','Events']],
    ['name'=>'Secretary','key'=>'secretary','icon'=>'fa-file-pen','tone'=>'gold','desc'=>'Day-to-day record keeping and communication.','default'=>true,'locked'=>false,'perms'=>22,'areas'=>['Members','Communication','Events']],
    ['name'=>'Treasurer','key'=>'treasurer','icon'=>'fa-hand-holding-dollar','tone'=>'green','desc'=>'Contributions, financial records and giving reports.','default'=>true,'locked'=>false,'perms'=>18,'areas'=>['Finance','Reports','Members']],
    ['name'=>'Department Head','key'=>'department_head','icon'=>'fa-people-roof','tone'=>'brand','desc'=>'Manages one department and its members.','default'=>true,'locked'=>false,'perms'=>14,'areas'=>['Departments','Attendance','Members']],
    ['name'=>'Cell Group Leader','key'=>'cell_leader','icon'=>'fa-people-group','tone'=>'indigo','desc'=>'Runs a home cell and records its attendance.','default'=>false,'locked'=>false,'perms'=>9,'areas'=>['Cell Groups','Attendance']],
    ['name'=>'Usher','key'=>'usher','icon'=>'fa-clipboard-check','tone'=>'grey','desc'=>'Marks service attendance and greets visitors.','default'=>false,'locked'=>false,'perms'=>6,'areas'=>['Attendance','Members']],
];
$roleDefaults = count(array_filter($roles, fn($r) => $r['default']));

/* ── TAB 2: Contribution types ─────────────────────────────────────────────
   LATER: SELECT * FROM contribution_types ORDER BY sort_order; */
$contribs = [
    ['name'=>'Tithe','key'=>'tithe','icon'=>'fa-hand-holding-dollar','tone'=>'green','desc'=>'Regular tithe given by members.','cat'=>'Regular','recurring'=>true,'default'=>true,'active'=>true],
    ['name'=>'Offering','key'=>'offering','icon'=>'fa-basket-shopping','tone'=>'brand','desc'=>'General offering collected at services.','cat'=>'Regular','recurring'=>true,'default'=>true,'active'=>true],
    ['name'=>'Thanksgiving','key'=>'thanksgiving','icon'=>'fa-heart','tone'=>'gold','desc'=>'Given in thanks for answered prayer.','cat'=>'Regular','recurring'=>false,'default'=>true,'active'=>true],
    ['name'=>'Building Fund','key'=>'building_fund','icon'=>'fa-trowel-bricks','tone'=>'indigo','desc'=>'Contributions toward church construction.','cat'=>'Project','recurring'=>true,'default'=>true,'active'=>true],
    ['name'=>'Seed','key'=>'seed','icon'=>'fa-seedling','tone'=>'green','desc'=>'Seed offering toward a specific need.','cat'=>'Special','recurring'=>false,'default'=>true,'active'=>true],
    ['name'=>'Pledge','key'=>'pledge','icon'=>'fa-file-signature','tone'=>'gold','desc'=>'Amount promised and paid over time.','cat'=>'Project','recurring'=>true,'default'=>true,'active'=>true],
    ['name'=>'Missions','key'=>'missions','icon'=>'fa-globe','tone'=>'indigo','desc'=>'Support for outreach and mission work.','cat'=>'Special','recurring'=>false,'default'=>false,'active'=>true],
    ['name'=>'Welfare','key'=>'welfare','icon'=>'fa-hand-holding-heart','tone'=>'berry','desc'=>'Assistance for members in need.','cat'=>'Special','recurring'=>false,'default'=>false,'active'=>true],
    ['name'=>'Special Offering','key'=>'special_offering','icon'=>'fa-star','tone'=>'grey','desc'=>'One-off collection for a named purpose.','cat'=>'Special','recurring'=>false,'default'=>false,'active'=>false],
];

/* ── TAB 3: Member fields ──────────────────────────────────────────────────
   LATER: SELECT * FROM member_field_presets ORDER BY field_group, sort_order;
   `system` fields ship with the product and cannot be deleted. */
$fieldGroups = [
    'Personal Information' => [
        ['Full Name','full_name','Text','','system'=>true,'req'=>true,'list'=>true,'def'=>true,'active'=>true],
        ['Date of Birth','date_of_birth','Date','','system'=>true,'req'=>true,'list'=>false,'def'=>true,'active'=>true],
        ['Gender','gender','Dropdown','Male, Female','system'=>true,'req'=>true,'list'=>true,'def'=>true,'active'=>true],
        ['Marital Status','marital_status','Dropdown','Single, Married, Widowed, Divorced','system'=>false,'req'=>false,'list'=>false,'def'=>true,'active'=>true],
        ['National ID','national_id','Text','','system'=>false,'req'=>false,'list'=>false,'def'=>false,'active'=>true],
    ],
    'Contact Details' => [
        ['Phone','phone','Phone','','system'=>true,'req'=>true,'list'=>true,'def'=>true,'active'=>true],
        ['Alternative Phone','alt_phone','Phone','','system'=>false,'req'=>false,'list'=>false,'def'=>false,'active'=>true],
        ['Email','email','Email','','system'=>true,'req'=>false,'list'=>true,'def'=>true,'active'=>true],
        ['Physical Address','address','Textarea','','system'=>true,'req'=>true,'list'=>false,'def'=>true,'active'=>true],
        ['City','city','Text','','system'=>false,'req'=>false,'list'=>true,'def'=>true,'active'=>true],
        ['Province','province','Dropdown','Harare, Bulawayo, Manicaland, Midlands, Masvingo, Mash. East, Mash. West, Mash. Central, Mat. North, Mat. South','system'=>false,'req'=>false,'list'=>false,'def'=>true,'active'=>true],
    ],
    'Church Information' => [
        ['Membership Number','membership_no','Text','','system'=>true,'req'=>true,'list'=>true,'def'=>true,'active'=>true],
        ['Date Joined','date_joined','Date','','system'=>true,'req'=>true,'list'=>false,'def'=>true,'active'=>true],
        ['Membership Status','membership_status','Dropdown','Active, Inactive, Transferred','system'=>true,'req'=>true,'list'=>true,'def'=>true,'active'=>true],
        ['Baptism Date','baptism_date','Date','','system'=>false,'req'=>false,'list'=>false,'def'=>true,'active'=>true],
        ['Confirmation Date','confirmation_date','Date','','system'=>false,'req'=>false,'list'=>false,'def'=>true,'active'=>true],
        ['Department','department','Dropdown','Choir, Ushers, Youth, Women, Men, Sunday School','system'=>false,'req'=>false,'list'=>true,'def'=>true,'active'=>true],
        ['Cell Group','cell_group','Dropdown','Assigned per church','system'=>false,'req'=>false,'list'=>false,'def'=>false,'active'=>true],
        ['Ministry Role','ministry_role','Text','','system'=>false,'req'=>false,'list'=>false,'def'=>false,'active'=>true],
    ],
    'Additional Information' => [
        ['Occupation','occupation','Text','','system'=>false,'req'=>false,'list'=>false,'def'=>false,'active'=>true],
        ['Emergency Contact Name','emergency_name','Text','','system'=>false,'req'=>false,'list'=>false,'def'=>true,'active'=>true],
        ['Emergency Contact Phone','emergency_phone','Phone','','system'=>false,'req'=>false,'list'=>false,'def'=>true,'active'=>true],
        ['Photo','photo','File','','system'=>true,'req'=>false,'list'=>true,'def'=>true,'active'=>true],
        ['Notes','notes','Textarea','','system'=>false,'req'=>false,'list'=>false,'def'=>false,'active'=>false],
    ],
];
$allFields   = array_merge(...array_values($fieldGroups));
$sysFields   = count(array_filter($allFields, fn($f) => $f['system']));
$reqFields   = count(array_filter($allFields, fn($f) => $f['req']));

$activePage    = 'setup/index';
$sidebarBadges = ['pending' => 10, 'expiring' => 12];
$sidebarUser   = ['name' => 'Super Admin', 'role' => 'System Owner'];

/** Render a stat strip from a list of [label, value, tone, icon, active]. */
function strip(array $tiles): void {
    echo '<div class="statstrip">';
    foreach ($tiles as [$label, $value, $tone, $icon, $on]) {
        echo '<a class="stat-tile stat-tile--' . $tone . ($on ? ' is-on' : '') . '" href="#">'
           . '<span class="stat-tile__icon"><i class="fa-solid ' . $icon . '"></i></span>'
           . '<span class="stat-tile__body"><span class="stat-tile__value">' . $value . '</span>'
           . '<span class="stat-tile__label">' . htmlspecialchars($label) . '</span></span></a>';
    }
    echo '</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Setup Data — Mutendi CMS Super Admin</title>
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
      <h1>Setup Data</h1>
      <p class="crumb">
        <a href="<?= $base_url ?>index.php">Dashboard</a> <i class="fa-solid fa-chevron-right"></i>
        Defaults &amp; Setup Data <i class="fa-solid fa-chevron-right"></i> Setup Data
      </p>
      <p class="page-hint">Default reference data automatically applied to every new church at creation.</p>
    </div>
    <div class="head-actions">
      <button class="btn btn--primary" type="button" id="addBtn" data-modal="modalRole">
        <i class="fa-solid fa-plus"></i> <span id="addLabel">Add Role</span>
      </button>
    </div>
  </div>

  <div class="tabs" role="tablist">
    <button class="tab is-on" type="button" role="tab" data-tab="roles">Role Templates</button>
    <button class="tab" type="button" role="tab" data-tab="contribs">Contribution Types</button>
    <button class="tab" type="button" role="tab" data-tab="fields">Member Fields</button>
  </div>

  <!-- ═══════════════ TAB 1 — ROLE TEMPLATES ═══════════════ -->
  <div class="tabpanel" data-panel="roles">
    <?php strip([
      ['Total Roles',       count($roles),                 'indigo', 'fa-user-tag',   true],
      ['Default Roles',     $roleDefaults,                 'green',  'fa-check',      false],
      ['Custom Roles',      count($roles) - $roleDefaults, 'brand',  'fa-sliders',    false],
      ['Total Permissions', $totalPerms,                   'grey',   'fa-key',        false],
    ]); ?>

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
              <i class="fa-solid fa-lock modcard__lock" title="System role — cannot be deleted"></i>
            <?php endif; ?>
          </div>

          <p class="modcard__desc"><?= htmlspecialchars($r['desc']) ?></p>
          <span class="pill <?= $r['default'] ? 'pill--published' : 'pill--suspended' ?> modcard__type">
            <?= $r['default'] ? 'Default' : 'Optional' ?>
          </span>

          <div class="permline">
            <span class="permline__num"><strong><?= $r['perms'] ?></strong> of <?= $totalPerms ?> permissions</span>
            <span class="bar"><i style="width: <?= (int) round($r['perms'] / $totalPerms * 100) ?>%"></i></span>
          </div>

          <div class="minichips">
            <?php foreach ($r['areas'] as $a): ?><span class="minichip"><?= $a ?></span><?php endforeach; ?>
          </div>

          <div class="modcard__foot">
            <a href="#" data-modal="modalRole">Edit Permissions</a>
            <a href="#">Duplicate</a>
            <?php if (!$r['locked']): ?><a href="#" class="is-danger" data-modal="modalDeleteRole">Delete</a><?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ═══════════════ TAB 2 — CONTRIBUTION TYPES ═══════════════ -->
  <div class="tabpanel" data-panel="contribs" hidden>
    <?php
      $cActive  = count(array_filter($contribs, fn($c) => $c['active']));
      $cDefault = count(array_filter($contribs, fn($c) => $c['default']));
      strip([
        ['Total Types', count($contribs),            'indigo', 'fa-tags',         true],
        ['Active',      $cActive,                    'green',  'fa-circle-check', false],
        ['Default',     $cDefault,                   'brand',  'fa-star',         false],
        ['Inactive',    count($contribs) - $cActive, 'grey',   'fa-ban',          false],
      ]);
    ?>

    <div class="card filterbar filterbar--slim">
      <div class="filterbar__row filterbar__row--4">
        <label class="field field--search">
          <span class="field__label">Search</span>
          <span class="field__input"><i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" placeholder="Search contribution types..."></span></label>
        <label class="field"><span class="field__label">Category</span>
          <select><option>All Categories</option><option>Regular</option><option>Special</option><option>Project</option></select></label>
        <label class="field"><span class="field__label">Status</span>
          <select><option>All</option><option>Active</option><option>Inactive</option></select></label>
        <label class="field"><span class="field__label">&nbsp;</span>
          <a class="btn btn--ghost" href="#">Reset</a></label>
      </div>
    </div>

    <div class="bulkbar" data-bulkbar="contribs" hidden>
      <span class="bulkbar__count"><strong data-bulkcount="contribs">0</strong> selected</span>
      <div class="bulkbar__actions">
        <button class="btn btn--sm" type="button"><i class="fa-solid fa-star"></i> Set as Default</button>
        <button class="btn btn--sm" type="button"><i class="fa-regular fa-star"></i> Remove Default</button>
        <button class="btn btn--sm btn--go" type="button"><i class="fa-solid fa-circle-check"></i> Activate</button>
        <button class="btn btn--sm" type="button"><i class="fa-solid fa-ban"></i> Deactivate</button>
        <button class="btn btn--sm btn--danger" type="button" data-modal="modalDeleteContrib"><i class="fa-solid fa-trash"></i> Delete</button>
      </div>
      <button class="bulkbar__clear" type="button" data-bulkclear="contribs" aria-label="Clear selection"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <div class="card">
      <div class="table-wrap">
        <table class="table table--churches">
          <thead>
            <tr>
              <th class="col-drag"></th>
              <th class="col-num">#</th>
              <th class="col-check"><input type="checkbox" data-checkall="contribs" aria-label="Select all types"></th>
              <th>Type</th><th>Description</th><th>Category</th>
              <th class="ta-center">Recurring</th><th class="ta-center">Default</th>
              <th>Status</th><th class="col-actions">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($contribs as $i => $c): ?>
              <tr>
                <td class="col-drag"><span class="draghandle" title="Drag to reorder"><i class="fa-solid fa-grip-vertical"></i></span></td>
                <td class="col-num muted"><?= $i + 1 ?></td>
                <td class="col-check"><input type="checkbox" data-rowcheck="contribs" aria-label="Select <?= htmlspecialchars($c['name']) ?>"></td>
                <td>
                  <div class="church">
                    <span class="modcard__icon modcard__icon--<?= $c['tone'] ?> annicon"><i class="fa-solid <?= $c['icon'] ?>"></i></span>
                    <span class="church__text">
                      <strong><?= htmlspecialchars($c['name']) ?></strong>
                      <code class="keytext"><?= $c['key'] ?></code>
                    </span>
                  </div>
                </td>
                <td class="muted descell"><?= htmlspecialchars($c['desc']) ?></td>
                <td><span class="cat cat--<?= strtolower($c['cat']) ?>"><?= $c['cat'] ?></span></td>
                <td class="ta-center"><?= $c['recurring']
                      ? '<i class="fa-solid fa-check yn yn--yes"></i>'
                      : '<i class="fa-solid fa-xmark yn yn--no"></i>' ?></td>
                <td class="ta-center">
                  <label class="switch switch--sm"><input type="checkbox"<?= $c['default'] ? ' checked' : '' ?>><span class="switch__track"></span></label>
                </td>
                <td><span class="pill <?= $c['active'] ? 'pill--active' : 'pill--draft' ?>"><?= $c['active'] ? 'Active' : 'Inactive' ?></span></td>
                <td class="col-actions">
                  <div class="row-actions">
                    <button class="ico-btn" type="button" title="Edit" aria-label="Edit" data-modal="modalContrib"><i class="fa-solid fa-pen"></i></button>
                    <div class="dropdown dropdown--menu">
                      <button class="ico-btn dropdown__trigger" type="button" title="More" aria-label="More actions"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                      <div class="dropdown__menu dropdown__menu--right">
                        <a href="#" data-modal="modalContrib"><i class="fa-solid fa-pen"></i> Edit</a>
                        <a href="#"><i class="fa-solid fa-copy"></i> Duplicate</a>
                        <a href="#"><i class="fa-solid fa-toggle-on"></i> Toggle Status</a>
                        <span class="dropdown__sep"></span>
                        <a href="#" class="is-danger" data-modal="modalDeleteContrib"><i class="fa-solid fa-trash"></i> Delete</a>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="tablefoot">
        <p class="tablefoot__count">Showing <?= count($contribs) ?> of <?= count($contribs) ?> contribution types</p>
        <nav class="pagination" aria-label="Pagination">
          <a class="pagination__btn is-disabled" href="#">Previous</a>
          <a class="pagination__btn is-on" href="#">1</a>
          <a class="pagination__btn is-disabled" href="#">Next</a>
        </nav>
      </div>
    </div>
  </div>

  <!-- ═══════════════ TAB 3 — MEMBER FIELDS ═══════════════ -->
  <div class="tabpanel" data-panel="fields" hidden>
    <?php strip([
      ['Total Fields',  count($allFields),              'indigo', 'fa-list-check', true],
      ['System Fields', $sysFields,                     'grey',   'fa-lock',       false],
      ['Custom Fields', count($allFields) - $sysFields, 'brand',  'fa-sliders',    false],
      ['Required',      $reqFields,                     'gold',   'fa-asterisk',   false],
    ]); ?>

    <div class="card filterbar filterbar--slim">
      <div class="filterbar__row filterbar__row--4">
        <label class="field field--search">
          <span class="field__label">Search</span>
          <span class="field__input"><i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" placeholder="Search member fields..."></span></label>
        <label class="field"><span class="field__label">Field Group</span>
          <select><option>All Groups</option><?php foreach (array_keys($fieldGroups) as $g): ?><option><?= $g ?></option><?php endforeach; ?></select></label>
        <label class="field"><span class="field__label">Field Type</span>
          <select><option>All Types</option><?php foreach (['Text','Number','Date','Dropdown','Checkbox','Textarea','Phone','Email','File'] as $t): ?><option><?= $t ?></option><?php endforeach; ?></select></label>
        <label class="field"><span class="field__label">&nbsp;</span>
          <span class="inline-filter">
            <label class="check-row check-row--slim"><input type="checkbox"><span>Required only</span></label>
            <a class="btn btn--ghost" href="#">Reset</a>
          </span></label>
      </div>
    </div>

    <div class="bulkbar" data-bulkbar="fields" hidden>
      <span class="bulkbar__count"><strong data-bulkcount="fields">0</strong> selected</span>
      <div class="bulkbar__actions">
        <button class="btn btn--sm" type="button"><i class="fa-solid fa-asterisk"></i> Set Required</button>
        <button class="btn btn--sm" type="button"><i class="fa-solid fa-eraser"></i> Remove Required</button>
        <button class="btn btn--sm" type="button"><i class="fa-solid fa-star"></i> Set as Default</button>
        <button class="btn btn--sm btn--go" type="button"><i class="fa-solid fa-circle-check"></i> Activate</button>
        <button class="btn btn--sm" type="button"><i class="fa-solid fa-ban"></i> Deactivate</button>
        <button class="btn btn--sm btn--danger" type="button" data-modal="modalDeleteField"><i class="fa-solid fa-trash"></i> Delete</button>
      </div>
      <button class="bulkbar__clear" type="button" data-bulkclear="fields" aria-label="Clear selection"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <div class="card">
      <div class="table-wrap">
        <table class="table table--churches">
          <thead>
            <tr>
              <th class="col-drag"></th>
              <th class="col-num">#</th>
              <th class="col-check"><input type="checkbox" data-checkall="fields" aria-label="Select all fields"></th>
              <th>Field Label</th><th>Type</th><th>Options</th>
              <th class="ta-center">Required</th><th class="ta-center">List View</th><th class="ta-center">Default</th>
              <th>Status</th><th class="col-actions">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php $n = 0; foreach ($fieldGroups as $group => $fields): ?>
              <tr class="table__group">
                <td colspan="11"><i class="fa-solid fa-layer-group"></i> <?= htmlspecialchars($group) ?>
                  <span class="table__groupcount"><?= count($fields) ?> fields</span></td>
              </tr>
              <?php foreach ($fields as $f): $n++; ?>
                <tr>
                  <td class="col-drag"><span class="draghandle" title="Drag to reorder"><i class="fa-solid fa-grip-vertical"></i></span></td>
                  <td class="col-num muted"><?= $n ?></td>
                  <td class="col-check"><input type="checkbox" data-rowcheck="fields" aria-label="Select <?= htmlspecialchars($f[0]) ?>"></td>
                  <td>
                    <span class="church__text">
                      <strong><?= htmlspecialchars($f[0]) ?>
                        <?php if ($f['system']): ?><i class="fa-solid fa-lock modcard__lock" title="System field"></i><?php endif; ?>
                      </strong>
                      <code class="keytext"><?= $f[1] ?></code>
                    </span>
                  </td>
                  <td><span class="ftype ftype--<?= strtolower($f[2]) ?>"><?= $f[2] ?></span></td>
                  <td class="muted descell"><?= $f[3] !== '' ? htmlspecialchars($f[3]) : '&mdash;' ?></td>
                  <td class="ta-center"><label class="switch switch--sm"><input type="checkbox"<?= $f['req'] ? ' checked' : '' ?>><span class="switch__track"></span></label></td>
                  <td class="ta-center"><label class="switch switch--sm"><input type="checkbox"<?= $f['list'] ? ' checked' : '' ?>><span class="switch__track"></span></label></td>
                  <td class="ta-center"><label class="switch switch--sm"><input type="checkbox"<?= $f['def'] ? ' checked' : '' ?>><span class="switch__track"></span></label></td>
                  <td><span class="pill <?= $f['active'] ? 'pill--active' : 'pill--draft' ?>"><?= $f['active'] ? 'Active' : 'Inactive' ?></span></td>
                  <td class="col-actions">
                    <div class="row-actions">
                      <button class="ico-btn" type="button" title="Edit" aria-label="Edit" data-modal="modalField"><i class="fa-solid fa-pen"></i></button>
                      <div class="dropdown dropdown--menu">
                        <button class="ico-btn dropdown__trigger" type="button" title="More" aria-label="More actions"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                        <div class="dropdown__menu dropdown__menu--right">
                          <a href="#" data-modal="modalField"><i class="fa-solid fa-pen"></i> Edit</a>
                          <a href="#"><i class="fa-solid fa-copy"></i> Duplicate</a>
                          <a href="#"><i class="fa-solid fa-toggle-on"></i> Toggle Status</a>
                          <?php if (!$f['system']): ?>
                            <span class="dropdown__sep"></span>
                            <a href="#" class="is-danger" data-modal="modalDeleteField"><i class="fa-solid fa-trash"></i> Delete</a>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="tablefoot">
        <p class="tablefoot__count">Showing <?= count($allFields) ?> of <?= count($allFields) ?> fields across <?= count($fieldGroups) ?> groups</p>
        <nav class="pagination" aria-label="Pagination">
          <a class="pagination__btn is-disabled" href="#">Previous</a>
          <a class="pagination__btn is-on" href="#">1</a>
          <a class="pagination__btn is-disabled" href="#">Next</a>
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

<!-- ==================== MODALS (static) ==================== -->

<!-- ROLE: add / edit with the permission matrix -->
<div class="modal" id="modalRole" hidden>
  <div class="modal__box modal__box--lg">
    <div class="modal__head">
      <h2><i class="fa-solid fa-user-tag"></i> Edit Role Template</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">

      <section class="msec">
        <h3 class="msec__title">Details</h3>
        <div class="field-row">
          <label class="field"><span class="field__label">Role name</span><input type="text" value="Treasurer"></label>
          <label class="field"><span class="field__label">System key</span><input type="text" value="treasurer" class="keyinput"></label>
        </div>
        <label class="field"><span class="field__label">Description</span>
          <textarea rows="2">Contributions, financial records and giving reports.</textarea></label>

        <span class="field__label">Icon</span>
        <div class="iconpick">
          <?php foreach (['fa-user-shield','fa-hands-praying','fa-file-pen','fa-hand-holding-dollar','fa-people-roof',
                          'fa-people-group','fa-clipboard-check','fa-user-tag','fa-key','fa-star'] as $i => $ic): ?>
            <label class="iconpick__opt"><input type="radio" name="roleicon"<?= $i === 3 ? ' checked' : '' ?>>
              <span><i class="fa-solid <?= $ic ?>"></i></span></label>
          <?php endforeach; ?>
        </div>

        <span class="field__label">Colour</span>
        <div class="swatches">
          <?php foreach (['brand','green','gold','berry','indigo','grey'] as $i => $tone): ?>
            <label class="swatch"><input type="radio" name="roletone"<?= $i === 1 ? ' checked' : '' ?>>
              <span class="swatch__dot swatch__dot--<?= $tone ?>"></span></label>
          <?php endforeach; ?>
        </div>

        <label class="check-row"><input type="checkbox" checked>
          <span>Apply to every new church by default</span></label>
      </section>

      <section class="msec">
        <h3 class="msec__title">Permissions</h3>
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
              <?php foreach ($rows as [$label, $actions]): ?>
                <div class="permrow">
                  <span class="permrow__label"><?= htmlspecialchars($label) ?></span>
                  <span class="permrow__acts">
                    <?php foreach (['View','Add','Edit','Delete'] as $act): ?>
                      <?php if (in_array($act, $actions, true)): ?>
                        <label class="permact"><input type="checkbox" class="permbox" data-permgroup="<?= $gid ?>"><span><?= $act ?></span></label>
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

<div class="modal" id="modalDeleteRole" hidden>
  <div class="modal__box modal__box--sm">
    <div class="modal__head">
      <h2><i class="fa-solid fa-trash note--berry"></i> Delete Role Template</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <p class="modal__warn"><i class="fa-solid fa-triangle-exclamation"></i>
        Deleting <strong>Treasurer</strong> removes it from the default set. Admin users already assigned this role in existing churches will need reassigning before they can sign in.</p>
      <label class="field"><span class="field__label">Type <strong>treasurer</strong> to confirm</span>
        <input type="text" placeholder="treasurer"></label>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--danger-solid" type="button">Delete Role</button>
    </div>
  </div>
</div>

<!-- CONTRIBUTION TYPE: add / edit -->
<div class="modal" id="modalContrib" hidden>
  <div class="modal__box">
    <div class="modal__head">
      <h2><i class="fa-solid fa-tags"></i> Edit Contribution Type</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <div class="field-row">
        <label class="field"><span class="field__label">Name</span><input type="text" value="Tithe"></label>
        <label class="field"><span class="field__label">System key</span><input type="text" value="tithe" class="keyinput"></label>
      </div>
      <label class="field"><span class="field__label">Description</span>
        <textarea rows="2">Regular tithe given by members.</textarea></label>

      <span class="field__label">Category</span>
      <div class="radios">
        <label class="radio"><input type="radio" name="ccat" checked><span>Regular</span></label>
        <label class="radio"><input type="radio" name="ccat"><span>Special</span></label>
        <label class="radio"><input type="radio" name="ccat"><span>Project</span></label>
      </div>

      <span class="field__label">Icon</span>
      <div class="iconpick">
        <?php foreach (['fa-hand-holding-dollar','fa-basket-shopping','fa-heart','fa-trowel-bricks','fa-seedling',
                        'fa-file-signature','fa-globe','fa-hand-holding-heart','fa-star','fa-gift'] as $i => $ic): ?>
          <label class="iconpick__opt"><input type="radio" name="cicon"<?= $i === 0 ? ' checked' : '' ?>>
            <span><i class="fa-solid <?= $ic ?>"></i></span></label>
        <?php endforeach; ?>
      </div>

      <span class="field__label">Colour</span>
      <div class="swatches">
        <?php foreach (['brand','green','gold','berry','indigo','grey'] as $i => $tone): ?>
          <label class="swatch"><input type="radio" name="ctone"<?= $i === 1 ? ' checked' : '' ?>>
            <span class="swatch__dot swatch__dot--<?= $tone ?>"></span></label>
        <?php endforeach; ?>
      </div>

      <label class="check-row"><input type="checkbox" checked><span>Recurring contribution</span></label>
      <label class="check-row"><input type="checkbox" checked><span>Apply to every new church by default</span></label>
      <label class="check-row"><input type="checkbox" checked><span>Active</span></label>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--primary" type="button">Save Type</button>
    </div>
  </div>
</div>

<div class="modal" id="modalDeleteContrib" hidden>
  <div class="modal__box modal__box--sm">
    <div class="modal__head">
      <h2><i class="fa-solid fa-trash note--berry"></i> Delete Contribution Type</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <p class="modal__warn"><i class="fa-solid fa-triangle-exclamation"></i>
        Removing <strong>Tithe</strong> from the defaults means new churches will not receive it. Churches already using it keep their historical records — nothing is deleted from their books.</p>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--danger-solid" type="button">Delete Type</button>
    </div>
  </div>
</div>

<!-- MEMBER FIELD: add / edit -->
<div class="modal" id="modalField" hidden>
  <div class="modal__box">
    <div class="modal__head">
      <h2><i class="fa-solid fa-list-check"></i> Edit Member Field</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <div class="field-row">
        <label class="field"><span class="field__label">Field label</span><input type="text" value="Marital Status"></label>
        <label class="field"><span class="field__label">System key</span><input type="text" value="marital_status" class="keyinput"></label>
      </div>
      <div class="field-row">
        <label class="field"><span class="field__label">Field group</span>
          <select><?php foreach (array_keys($fieldGroups) as $g): ?><option><?= $g ?></option><?php endforeach; ?></select></label>
        <label class="field"><span class="field__label">Field type</span>
          <select id="fieldType">
            <?php foreach (['Text','Number','Date','Dropdown','Checkbox','Textarea','Phone','Email','File'] as $t): ?>
              <option<?= $t === 'Dropdown' ? ' selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
          </select></label>
      </div>

      <!-- Options only apply to Dropdown and Checkbox fields. -->
      <div id="fieldOptions">
        <span class="field__label">Options</span>
        <div class="optrows" id="optRows">
          <?php foreach (['Single', 'Married', 'Widowed', 'Divorced'] as $o): ?>
            <div class="optrow">
              <input type="text" value="<?= $o ?>">
              <button class="ico-btn optrow__x" type="button" data-optremove aria-label="Remove option"><i class="fa-solid fa-xmark"></i></button>
            </div>
          <?php endforeach; ?>
        </div>
        <button class="btn btn--sm" type="button" id="optAdd"><i class="fa-solid fa-plus"></i> Add option</button>
      </div>

      <div class="field-row">
        <label class="field"><span class="field__label">Placeholder text</span><input type="text" placeholder="Shown inside the empty field"></label>
        <label class="field"><span class="field__label">Help text</span><input type="text" placeholder="Shown beneath the field"></label>
      </div>

      <label class="check-row"><input type="checkbox"><span>Required</span></label>
      <label class="check-row"><input type="checkbox"><span>Show in list view</span></label>
      <label class="check-row"><input type="checkbox" checked><span>Searchable</span></label>
      <label class="check-row"><input type="checkbox" checked><span>Apply to every new church by default</span></label>
      <label class="check-row"><input type="checkbox" checked><span>Active</span></label>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--primary" type="button">Save Field</button>
    </div>
  </div>
</div>

<div class="modal" id="modalDeleteField" hidden>
  <div class="modal__box modal__box--sm">
    <div class="modal__head">
      <h2><i class="fa-solid fa-trash note--berry"></i> Delete Member Field</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <p class="modal__warn"><i class="fa-solid fa-triangle-exclamation"></i>
        Deleting <strong>Marital Status</strong> removes it from the defaults. Member data already captured in this field in existing churches stays in the database but becomes inaccessible from the member profile.</p>
      <label class="field"><span class="field__label">Type <strong>marital_status</strong> to confirm</span>
        <input type="text" placeholder="marital_status"></label>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--danger-solid" type="button">Delete Field</button>
    </div>
  </div>
</div>

<script>
/* Tabs, per-tab bulk bars, the permission matrix, dynamic field options,
   dropdowns and modals. Nothing is submitted. */
(function () {
  'use strict';

  /* --- Tabs: switch panel and relabel the header's Add button ---------- */
  var ADD = { roles:    { label: 'Add Role',              modal: 'modalRole' },
              contribs: { label: 'Add Contribution Type', modal: 'modalContrib' },
              fields:   { label: 'Add Member Field',      modal: 'modalField' } };

  var tabs = [].slice.call(document.querySelectorAll('.tab[data-tab]')),
      addBtn = document.getElementById('addBtn'),
      addLabel = document.getElementById('addLabel');

  tabs.forEach(function (t) {
    t.addEventListener('click', function () {
      tabs.forEach(function (x) { x.classList.toggle('is-on', x === t); });
      document.querySelectorAll('.tabpanel').forEach(function (p) {
        p.hidden = p.dataset.panel !== t.dataset.tab;
      });
      var cfg = ADD[t.dataset.tab];
      addLabel.textContent = cfg.label;
      addBtn.dataset.modal = cfg.modal;
    });
  });

  /* --- One bulk bar per tab, keyed by name ---------------------------- */
  ['contribs', 'fields'].forEach(function (key) {
    var all   = document.querySelector('[data-checkall="' + key + '"]'),
        rows  = [].slice.call(document.querySelectorAll('[data-rowcheck="' + key + '"]')),
        bar   = document.querySelector('[data-bulkbar="' + key + '"]'),
        count = document.querySelector('[data-bulkcount="' + key + '"]'),
        clear = document.querySelector('[data-bulkclear="' + key + '"]');
    if (!all) { return; }

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
    clear.addEventListener('click', function () {
      rows.forEach(function (c) { c.checked = false; });
      all.checked = false; refresh();
    });
  });

  /* --- Permission matrix ---------------------------------------------- */
  var permBoxes = [].slice.call(document.querySelectorAll('.permbox')),
      permCount = document.getElementById('permCount');

  function tallyPerms() {
    if (permCount) { permCount.textContent = permBoxes.filter(function (b) { return b.checked; }).length; }
    document.querySelectorAll('[data-permall]').forEach(function (master) {
      var group = master.dataset.permall,
          kids  = permBoxes.filter(function (b) { return b.dataset.permgroup === group; }),
          on    = kids.filter(function (b) { return b.checked; }).length;
      master.checked = on === kids.length && on > 0;
      master.indeterminate = on > 0 && on < kids.length;
    });
  }
  permBoxes.forEach(function (b) { b.addEventListener('change', tallyPerms); });
  document.querySelectorAll('[data-permall]').forEach(function (master) {
    master.addEventListener('change', function () {
      permBoxes.filter(function (b) { return b.dataset.permgroup === master.dataset.permall; })
               .forEach(function (b) { b.checked = master.checked; });
      tallyPerms();
    });
  });
  document.querySelectorAll('.permgroup__toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var grp = btn.closest('.permgroup'),
          open = grp.classList.toggle('is-open');
      btn.setAttribute('aria-expanded', String(open));
    });
  });
  tallyPerms();

  /* --- Member field: options only apply to Dropdown and Checkbox ------- */
  var fieldType = document.getElementById('fieldType'),
      fieldOpts = document.getElementById('fieldOptions'),
      optRows   = document.getElementById('optRows');

  if (fieldType) {
    var syncType = function () {
      var t = fieldType.value;
      fieldOpts.hidden = !(t === 'Dropdown' || t === 'Checkbox');
    };
    fieldType.addEventListener('change', syncType);
    syncType();

    document.getElementById('optAdd').addEventListener('click', function () {
      var row = document.createElement('div');
      row.className = 'optrow';
      row.innerHTML = '<input type="text" placeholder="New option">'
        + '<button class="ico-btn optrow__x" type="button" data-optremove aria-label="Remove option">'
        + '<i class="fa-solid fa-xmark"></i></button>';
      optRows.appendChild(row);
    });
    optRows.addEventListener('click', function (e) {
      var x = e.target.closest('[data-optremove]');
      if (x && optRows.children.length > 1) { x.closest('.optrow').remove(); }
    });
  }

  /* --- Dropdowns + modals --------------------------------------------- */
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
})();
</script>
</body>
</html>

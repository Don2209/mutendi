<?php
/**
 * Mutendi CMS — Departments.
 *
 * Church departments and ministries and who serves in them.
 * UI only. Requires the 'departments' module.
 */

require __DIR__ . '/../includes/config.php';

/* ══════════════ DEMO ONLY — REMOVE BEFORE PRODUCTION ══════════════ */
$demo_role       = isset($_GET['role'], $demo_roles[$_GET['role']]) ? $_GET['role'] : 'church_admin';
/* array_merge, not assignment: the scope keys from config must survive
   so a branch-scope user stays scoped on this page. */
$user            = array_merge($user, $demo_roles[$demo_role]['user']);
$permissions     = $demo_roles[$demo_role]['perms'];
$enabled_modules = $demo_roles[$demo_role]['modules'];
/* ═══════════════════════════ END DEMO ═══════════════════════════ */

if (!function_exists('mu_can')) {
    function mu_can(string $perm): bool { global $permissions; return in_array($perm, $permissions, true); }
    function mu_mod(string $mod): bool  { global $enabled_modules; return in_array($mod, $enabled_modules, true); }
    function mu_initials(string $name): string {
        $p = preg_split('/\s+/', trim($name)) ?: [];
        $a = mb_substr($p[0] ?? '', 0, 1);
        $b = count($p) > 1 ? mb_substr((string) end($p), 0, 1) : '';
        return mb_strtoupper($a . $b);
    }
    function mu_avc(string $name): string { return 'av-c' . (crc32($name) % 10); }
    function mu_av(string $name, string $size = 'md'): string {
        return '<span class="av av--' . $size . ' ' . mu_avc($name) . '" aria-hidden="true">'
             . htmlspecialchars(mu_initials($name)) . '</span>';
    }
    function mu_date(string $iso, string $fmt = 'd M Y'): string { return date($fmt, strtotime($iso)); }
}

/** A few stand-in faces for a department's avatar cluster. */
function mu_dept_faces(array $members, int $n, int $max = 4): string {
    $out = '<span class="av-stack">';
    foreach (array_slice($members, 0, $max) as $m) { $out .= mu_av($m['name'], 'xs'); }
    $extra = $n - $max;
    if ($extra > 0) { $out .= '<span class="av-stack__more">+' . $extra . '</span>'; }
    return $out . '</span>';
}

$has_module = mu_mod('departments');
$rows  = $has_module ? $departments_demo : [];

/* ─────────────────────────── BRANCH AWARENESS ───────────────────────────
   Resolves which branch is in view (the top bar's switcher sets ?branch=) and
   scopes this page's data to it. Every addition below is inert for a single
   church: is_multi_branch() is false, so no column, chip, filter or toggle is
   rendered and the page behaves exactly as it did before.
   ──────────────────────────────────────────────────────────────────────── */
require_once __DIR__ . '/../components/branch-switcher.php';

$branch_aware   = is_multi_branch();
$viewing_all    = !$branch_aware || $current_branch === 'all' || $current_branch === null;
$show_branch    = $branch_aware && $viewing_all;      /* column, chip and filter */
$branch_options = $branch_aware ? get_visible_branches() : [];

if (!function_exists('mu_branch_for')) {
    /**
     * Which branch a demo record belongs to. Deterministic from the record's
     * own key, so a person or a group never hops between branches on reload.
     * LATER: the row carries its own branch_id and this helper disappears.
     */
    function mu_branch_for(string $key): ?array {
        static $pool = null;
        if ($pool === null) { $pool = get_visible_branches(); }
        if (!$pool) { return null; }
        return $pool[crc32($key) % count($pool)];
    }

    /** One colour per group, so branches in the same group read together. */
    function mu_branch_tone(array $b): string {
        static $tones = [];
        static $pool = ['var(--info)', 'var(--brand-500)', '#0F766E', 'var(--warn)', '#6D28D9'];
        $g = $b['group_name'] ?? '';
        if (!isset($tones[$g])) { $tones[$g] = $pool[count($tones) % count($pool)]; }
        return $tones[$g];
    }

    /** The small coloured chip naming a record's branch. */
    function mu_branch_chip(?array $b): string {
        if (!$b) { return ''; }
        return '<span class="bchip" title="' . htmlspecialchars($b['name']) . '">'
             . '<span class="bchip__dot" style="background:' . mu_branch_tone($b) . '" aria-hidden="true"></span>'
             . htmlspecialchars($b['name']) . '</span>';
    }

    /**
     * Scales an organisation-wide headline figure to the selected branch, by
     * that branch's share of the roll.
     * LATER: the figure arrives from a query already scoped to :branch_id.
     */
    function mu_branch_share($orgValue) {
        global $current_branch, $organisation;
        if ($current_branch === 'all' || $current_branch === null) { return $orgValue; }
        $b = get_branch($current_branch);
        if (!$b) { return $orgValue; }
        $total = max(1, (int) ($organisation['total_members'] ?? 1));
        return $orgValue * ((int) $b['members_count'] / $total);
    }
}

$stats = $people_stats['departments'];

/* Departments belong to a branch. An organisation-scope user viewing every
   branch can narrow to just the one they are switched to; a branch-scope user
   is already scoped, so no toggle is offered. */
$scope_mode = ($_GET['scope'] ?? 'all') === 'mine' ? 'mine' : 'all';
$show_scope_toggle = $branch_aware && $viewing_all && ($user['scope'] ?? 'organisation') !== 'branch';
$scope_branch = $show_scope_toggle && $scope_mode === 'mine'
    ? get_branch($user['branch_id'] ?? 0)
    : null;

if ($branch_aware && $rows) {
    foreach ($rows as $i => $d) { $rows[$i]['_branch'] = mu_branch_for($d['name'] . $d['id']); }
    $pin = $scope_branch ? (int) $scope_branch['id'] : (!$viewing_all ? (int) $current_branch : null);
    if ($pin !== null) {
        $rows = array_values(array_filter($rows, function ($d) use ($pin) {
            return $d['_branch'] && (int) $d['_branch']['id'] === $pin;
        }));
    }
    foreach ($stats as $k => $v) { $stats[$k] = (int) round(mu_branch_share($v)); }
}
$faces = $members_demo;

/* LATER: SELECT * FROM members WHERE id NOT IN (SELECT member_id FROM member_departments); */
$not_serving = array_slice($members_demo, 6, 6);
$max_members = $rows ? max(array_column($rows, 'members')) : 1;

$page_title = 'Departments';
require __DIR__ . '/../components/header.php';
?>

<?php /* ─────────────────────────────────────────────────────────────────────
   The shared sidebar matcher (main_is_active in components/sidebar.php)
   returns true for a bare "index.php" menu url against ANY page named
   index.php, so Dashboard lights up on this page too. sidebar.php is out of
   scope for this task, so the false positive is corrected here instead.
   Proper one-line fix, when sidebar.php can be edited:
       return ($folder === '.' || $folder === '') ? ($dir === basename(dirname($_SERVER['PHP_SELF']))) : $folder === $dir;
   ───────────────────────────────────────────────────────────────────── */ ?>
<script>
(function () {
  var here = window.location.pathname;
  [].forEach.call(document.querySelectorAll('.nav-item.is-active'), function (a) {
    if (a.getAttribute('href') === here) { return; }      /* the genuine match */
    a.classList.remove('is-active');
    a.removeAttribute('aria-current');
    var g = a.closest('.nav-group');
    if (g && !g.querySelector('.nav-item.is-active')) {
      g.classList.remove('is-open');
      g.querySelector('.nav-group__head').setAttribute('aria-expanded', 'false');
    }
  });
})();
</script>

<div class="page">

  <header class="page__head">
    <div>
      <nav class="crumbs" aria-label="Breadcrumb">
        <a href="<?= $base_url ?>index.php">Dashboard</a>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span>People</span>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span aria-current="page">Departments</span>
      </nav>
      <div class="title-row">
        <h1 class="page__title">Departments</h1>
        <span class="count-chip" data-count="<?= $stats['total'] ?>">0</span>
      </div>
      <p class="page__sub">Manage your church departments and the members who serve in them.</p>
    </div>
    <?php if ($has_module): ?>
      <div class="page__actions">
        <?php if (mu_can('members.edit')): ?>
          <button class="btn" type="button" data-open="modalDept"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add Department</button>
        <?php endif; ?>
        <?php if (mu_can('members.export')): ?>
          <div class="drop" data-menu>
            <button class="btn btn--ghost" type="button" aria-haspopup="true" aria-expanded="false" data-menu-btn>
              <i class="fa-solid fa-file-export" aria-hidden="true"></i> Export
              <i class="fa-solid fa-chevron-down" style="font-size:10px;opacity:.7" aria-hidden="true"></i>
            </button>
            <div class="menu" data-menu-panel hidden>
              <a class="menu__item" href="#" data-toast="CSV export started"><i class="fa-solid fa-file-csv" aria-hidden="true"></i> Export as CSV</a>
              <a class="menu__item" href="#" data-toast="PDF export started"><i class="fa-solid fa-file-pdf" aria-hidden="true"></i> Export as PDF</a>
            </div>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </header>

<?php if (!$has_module): ?>

  <section class="panel">
    <div class="empty">
      <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-plug-circle-xmark"></i></span>
      <h3>The Departments module is switched off</h3>
      <p>Your church's plan does not include departments. A church administrator can request it from the platform team.</p>
      <a class="btn" href="<?= $base_url ?>index.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Dashboard</a>
    </div>
  </section>

<?php else: ?>

  <div class="stat-strip">
    <?php foreach ([
      ['Total Departments',      $stats['total'],       'fa-sitemap',      'blue'],
      ['Active Members Serving', $stats['serving'],     'fa-hands-holding-circle', 'green'],
      ['Department Heads',       $stats['heads'],       'fa-user-tie',     'purple'],
      ['Members Not Serving',    $stats['not_serving'], 'fa-user-slash',   'amber'],
    ] as [$label, $value, $icon, $tone]): ?>
      <div class="stat-tile" style="cursor:default">
        <span class="stat-tile__icon tone-<?= $tone ?>" aria-hidden="true"><i class="fa-solid <?= $icon ?>"></i></span>
        <span class="stat-tile__body">
          <span class="stat-tile__value" data-count="<?= $value ?>">0</span>
          <span class="stat-tile__label"><?= $label ?></span>
        </span>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="toolbar">
    <div class="viewswitch" role="group" aria-label="View">
      <button type="button" data-view="cards" aria-pressed="true"  aria-label="Card grid view"><i class="fa-solid fa-table-cells-large" aria-hidden="true"></i></button>
      <button type="button" data-view="table" aria-pressed="false" aria-label="Table view"><i class="fa-solid fa-table-list" aria-hidden="true"></i></button>
      <button type="button" data-view="org"   aria-pressed="false" aria-label="Org chart view"><i class="fa-solid fa-diagram-project" aria-hidden="true"></i></button>
    </div>
    <?php if ($show_scope_toggle): ?>
      <div class="scope-toggle" role="group" aria-label="Scope">
        <a href="?scope=all" role="button" aria-pressed="<?= $scope_mode === 'all' ? 'true' : 'false' ?>">All <?= htmlspecialchars(t('branch_plural')) ?></a>
        <a href="?scope=mine" role="button" aria-pressed="<?= $scope_mode === 'mine' ? 'true' : 'false' ?>">This <?= htmlspecialchars(t('branch_singular')) ?> only</a>
      </div>
    <?php endif; ?>
    <div class="search-field" style="flex:1;max-width:320px">
      <i class="fa-solid fa-magnifying-glass search-field__icon" aria-hidden="true"></i>
      <input class="input" type="search" id="fSearch" data-search placeholder="Search departments&hellip;">
      <button class="search-field__clear" type="button" data-search-clear hidden aria-label="Clear search"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </div>
    <p style="color:var(--muted);font-size:12.5px;font-weight:600"><span data-result-count><?= count($rows) ?></span> shown</p>
  </div>

  <section class="panel" id="listPanel" aria-live="polite">
    <div data-skeleton style="padding:16px">
      <div class="cardgrid cardgrid--3">
        <?php for ($i = 0; $i < 6; $i++): ?>
          <div class="sk-card">
            <span class="sk" style="width:44px;height:44px;border-radius:13px;display:block"></span>
            <span class="sk sk--text" style="width:60%;display:block;margin-top:12px"></span>
            <span class="sk sk--line" style="width:80%"></span>
            <div style="display:flex;gap:8px;margin-top:14px"><span class="sk sk--av"></span><span class="sk sk--av"></span></div>
          </div>
        <?php endfor; ?>
      </div>
    </div>

    <div data-content>

      <!-- ─────────────── CARD GRID ─────────────── -->
      <div data-view-panel="cards" style="padding:16px">
        <div class="cardgrid cardgrid--3 stagger">
          <?php foreach ($rows as $d): ?>
            <article class="gcard" data-card data-name="<?= htmlspecialchars(mb_strtolower($d['name'])) ?>"
                     <?= $branch_aware ? 'data-branch="' . htmlspecialchars($d['_branch']['name'] ?? '') . '"' : '' ?>>
              <span class="stat-tile__icon tone-<?= htmlspecialchars($d['color']) ?>" style="width:44px;height:44px;border-radius:13px;font-size:17px" aria-hidden="true">
                <i class="fa-solid <?= htmlspecialchars($d['icon']) ?>"></i>
              </span>

              <h3 style="margin-top:13px;color:var(--ink);font-size:15px;font-weight:800;letter-spacing:-.02em"><?= htmlspecialchars($d['name']) ?></h3>
              <p style="margin-top:4px;color:var(--muted);font-size:12px;line-height:1.5"><?= htmlspecialchars($d['description']) ?></p>
              <?php if ($show_branch): ?><p style="margin-top:10px"><?= mu_branch_chip($d['_branch'] ?? null) ?></p><?php endif; ?>

              <div style="display:flex;align-items:center;gap:9px;margin-top:14px">
                <?= mu_av($d['head'], 'sm') ?>
                <span style="min-width:0">
                  <span style="display:block;color:var(--ink);font-size:12.5px;font-weight:700"><?= htmlspecialchars($d['head']) ?></span>
                  <a href="tel:<?= htmlspecialchars(str_replace(' ', '', $d['head_phone'])) ?>" style="color:var(--muted);font-size:11.5px">
                    <i class="fa-solid fa-phone" style="color:var(--brand-300)" aria-hidden="true"></i> <?= htmlspecialchars($d['head_phone']) ?>
                  </a>
                </span>
              </div>

              <div style="display:flex;align-items:center;gap:12px;margin-top:15px">
                <span style="color:var(--ink);font-size:1.6rem;font-weight:800;letter-spacing:-.03em;line-height:1"><?= (int) $d['members'] ?></span>
                <?= mu_dept_faces($faces, (int) $d['members']) ?>
              </div>

              <p style="margin-top:12px;color:var(--muted);font-size:12px">
                <i class="fa-regular fa-calendar" style="color:var(--brand-300)" aria-hidden="true"></i>
                <?= htmlspecialchars($d['day']) ?>s at <?= htmlspecialchars($d['time']) ?>
              </p>

              <div style="display:flex;gap:16px;margin-top:12px;padding-top:12px;border-top:1px solid var(--line);font-size:11.5px;color:var(--muted);font-weight:600">
                <span><?= (int) $d['active'] ?> active</span>
                <span><?= (int) $d['attendance_rate'] ?>% attendance</span>
              </div>

              <div style="display:flex;gap:14px;margin-top:12px">
                <button class="chip-btn" type="button" style="border:0;padding:0;color:var(--brand-600)" data-open-dept="<?= (int) $d['id'] ?>">View</button>
                <?php if (mu_can('members.edit')): ?>
                  <button class="chip-btn" type="button" style="border:0;padding:0;color:var(--brand-600)" data-open="modalDept">Edit</button>
                  <button class="chip-btn" type="button" style="border:0;padding:0;color:var(--brand-600)" data-open="modalAddMembers">Members</button>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- ─────────────── TABLE ─────────────── -->
      <div data-view-panel="table" hidden>
        <div class="dt-wrap">
          <table class="dt">
            <thead>
              <tr>
                <th style="width:38px"><input class="check" type="checkbox" data-check-all aria-label="Select all departments"></th>
                <th style="width:44px">#</th>
                <th>Department</th>
                <?php if ($show_branch): ?><th><?= htmlspecialchars(t('branch_singular')) ?></th><?php endif; ?>
                <th>Head</th>
                <th>Members</th>
                <th>Meeting Schedule</th>
                <th>Attendance Rate</th>
                <th>Status</th>
                <th>Created</th>
                <th style="text-align:right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $i => $d): ?>
                <tr data-row data-name="<?= htmlspecialchars(mb_strtolower($d['name'])) ?>"
                    <?= $branch_aware ? 'data-branch="' . htmlspecialchars($d['_branch']['name'] ?? '') . '"' : '' ?>>
                  <td><input class="check" type="checkbox" data-row-check aria-label="Select <?= htmlspecialchars($d['name']) ?>"></td>
                  <td class="num"><?= $i + 1 ?></td>
                  <td>
                    <button class="person" type="button" data-open-dept="<?= (int) $d['id'] ?>">
                      <span class="stat-tile__icon tone-<?= htmlspecialchars($d['color']) ?>" style="width:32px;height:32px;border-radius:10px;font-size:13px" aria-hidden="true">
                        <i class="fa-solid <?= htmlspecialchars($d['icon']) ?>"></i>
                      </span>
                      <span class="person__text">
                        <span class="person__name"><?= htmlspecialchars($d['name']) ?></span>
                        <span class="tsub"><?= htmlspecialchars($d['description']) ?></span>
                      </span>
                    </button>
                  </td>
                  <?php if ($show_branch): ?><td class="nowrap"><?= mu_branch_chip($d['_branch'] ?? null) ?></td><?php endif; ?>
                  <td>
                    <span class="person"><?= mu_av($d['head'], 'sm') ?>
                      <span class="person__text"><span class="person__name"><?= htmlspecialchars($d['head']) ?></span>
                      <span class="tsub"><?= htmlspecialchars($d['head_phone']) ?></span></span>
                    </span>
                  </td>
                  <td style="min-width:120px">
                    <span class="minibar">
                      <strong style="color:var(--ink)"><?= (int) $d['members'] ?></strong>
                      <span class="minibar__track"><span class="minibar__fill" style="width:<?= (int) round($d['members'] / $max_members * 100) ?>%;background:var(--brand-500)"></span></span>
                    </span>
                  </td>
                  <td class="nowrap"><?= htmlspecialchars($d['day']) ?>s <?= htmlspecialchars($d['time']) ?><span class="tsub"><?= htmlspecialchars($d['venue']) ?></span></td>
                  <td style="min-width:120px">
                    <span class="minibar">
                      <strong style="color:var(--ink)"><?= (int) $d['attendance_rate'] ?>%</strong>
                      <span class="minibar__track"><span class="minibar__fill" style="width:<?= (int) $d['attendance_rate'] ?>%;background:var(--ok)"></span></span>
                    </span>
                  </td>
                  <td><span class="spill is-<?= strtolower($d['status']) ?>"><?= htmlspecialchars($d['status']) ?></span></td>
                  <td class="nowrap"><?= mu_date($d['created']) ?></td>
                  <td>
                    <div class="rowacts">
                      <button class="iconbtn iconbtn--sm" type="button" data-open-dept="<?= (int) $d['id'] ?>" aria-label="View <?= htmlspecialchars($d['name']) ?>"><i class="fa-regular fa-eye" aria-hidden="true"></i></button>
                      <?php if (mu_can('members.edit')): ?>
                        <button class="iconbtn iconbtn--sm" type="button" data-open="modalDept" aria-label="Edit <?= htmlspecialchars($d['name']) ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i></button>
                      <?php endif; ?>
                      <div class="drop" data-menu>
                        <button class="iconbtn iconbtn--sm" type="button" aria-haspopup="true" aria-expanded="false" data-menu-btn aria-label="More actions"><i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i></button>
                        <div class="menu menu--sm" data-menu-panel hidden>
                          <a class="menu__item" href="#" data-open-dept="<?= (int) $d['id'] ?>"><i class="fa-regular fa-eye" aria-hidden="true"></i> View</a>
                          <?php if (mu_can('members.edit')): ?>
                            <a class="menu__item" href="#" data-open="modalDept"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</a>
                            <a class="menu__item" href="#" data-open="modalAddMembers"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Add Members</a>
                          <?php endif; ?>
                          <?php if (mu_mod('communication')): ?><a class="menu__item" href="#" data-toast="Message composer opened"><i class="fa-regular fa-comment" aria-hidden="true"></i> Message Department</a><?php endif; ?>
                          <?php if (mu_can('members.delete')): ?>
                            <div class="menu__sep" role="separator"></div>
                            <a class="menu__item menu__item--danger" href="#" data-open="modalDeleteDept" data-name="<?= htmlspecialchars($d['name']) ?>"><i class="fa-solid fa-trash" aria-hidden="true"></i> Delete</a>
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

        <div class="dt-cards" style="padding:12px">
          <?php foreach ($rows as $d): ?>
            <article class="pcard" data-card data-name="<?= htmlspecialchars(mb_strtolower($d['name'])) ?>"
                     <?= $branch_aware ? 'data-branch="' . htmlspecialchars($d['_branch']['name'] ?? '') . '"' : '' ?>>
              <button class="pcard__main" type="button" data-card-toggle>
                <span class="stat-tile__icon tone-<?= htmlspecialchars($d['color']) ?>" style="width:40px;height:40px;border-radius:12px;font-size:15px" aria-hidden="true">
                  <i class="fa-solid <?= htmlspecialchars($d['icon']) ?>"></i>
                </span>
                <span class="pcard__text">
                  <span class="pcard__name"><?= htmlspecialchars($d['name']) ?></span>
                  <span class="pcard__meta"><?= (int) $d['members'] ?> members &middot; <?= htmlspecialchars($d['day']) ?>s</span>
                </span>
                <i class="fa-solid fa-chevron-down pcard__chev" aria-hidden="true"></i>
              </button>
              <div class="pcard__more">
                <dl>
                  <?php if ($show_branch): ?><div class="pcard__row"><dt><?= htmlspecialchars(t('branch_singular')) ?></dt><dd><?= htmlspecialchars($d['_branch']['name'] ?? '—') ?></dd></div><?php endif; ?>
                  <div class="pcard__row"><dt>Head</dt><dd><?= htmlspecialchars($d['head']) ?></dd></div>
                  <div class="pcard__row"><dt>Phone</dt><dd><?= htmlspecialchars($d['head_phone']) ?></dd></div>
                  <div class="pcard__row"><dt>Meets</dt><dd><?= htmlspecialchars($d['day']) ?>s <?= htmlspecialchars($d['time']) ?></dd></div>
                  <div class="pcard__row"><dt>Venue</dt><dd><?= htmlspecialchars($d['venue']) ?></dd></div>
                  <div class="pcard__row"><dt>Attendance</dt><dd><?= (int) $d['attendance_rate'] ?>%</dd></div>
                </dl>
                <div class="pcard__acts">
                  <button class="chip-btn" type="button" data-open-dept="<?= (int) $d['id'] ?>">View</button>
                  <?php if (mu_can('members.edit')): ?><button class="chip-btn" type="button" data-open="modalAddMembers">Add Members</button><?php endif; ?>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- ─────────────── ORG CHART ─────────────── -->
      <div data-view-panel="org" hidden>
        <div style="display:flex;gap:8px;justify-content:flex-end;padding:12px 16px 0">
          <button class="iconbtn" type="button" id="zoomOut" aria-label="Zoom out"><i class="fa-solid fa-magnifying-glass-minus" aria-hidden="true"></i></button>
          <button class="iconbtn" type="button" id="zoomIn" aria-label="Zoom in"><i class="fa-solid fa-magnifying-glass-plus" aria-hidden="true"></i></button>
        </div>
        <div class="org" id="orgWrap">
          <div class="org__inner" id="orgInner">
            <div class="org__root">
              <div class="onode onode--root">
                <span class="onode__icon tone-brand" aria-hidden="true"><i class="fa-solid fa-church"></i></span>
                <span class="onode__name"><?= htmlspecialchars($church['name']) ?></span>
                <span class="onode__count"><?= count($rows) ?> departments</span>
              </div>
            </div>
            <div class="org__row">
              <?php foreach ($rows as $d): ?>
                <div class="org__node">
                  <button class="onode" type="button" data-open-dept="<?= (int) $d['id'] ?>">
                    <span class="onode__icon tone-<?= htmlspecialchars($d['color']) ?>" aria-hidden="true"><i class="fa-solid <?= htmlspecialchars($d['icon']) ?>"></i></span>
                    <span class="onode__name"><?= htmlspecialchars($d['name']) ?></span>
                    <?= mu_av($d['head'], 'xs') ?>
                    <span class="onode__count"><?= (int) $d['members'] ?> members</span>
                    <?php if ($show_branch && !empty($d['_branch'])): ?>
                      <span class="onode__count" style="color:var(--brand-500)"><?= htmlspecialchars($d['_branch']['name']) ?></span>
                    <?php endif; ?>
                  </button>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="empty" data-empty hidden>
        <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-sitemap"></i></span>
        <h3>No departments match that search</h3>
        <p>Try another name, or clear the search to see every department again.</p>
        <button class="btn" type="button" data-reset-filters><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Clear search</button>
      </div>
    </div>
  </section>

  <!-- ═══════════════ SIZES + NOT SERVING ═══════════════ -->
  <div class="grid grid--2" style="margin-top:16px">
    <section class="panel">
      <header class="panel__head">
        <span class="stat-tile__icon tone-purple" style="width:32px;height:32px;font-size:13px" aria-hidden="true"><i class="fa-solid fa-chart-simple"></i></span>
        <h2>Department Sizes</h2>
      </header>
      <div class="panel__body">
        <div class="chart-wrap" style="height:330px">
          <canvas id="deptChart" role="img" aria-label="Member counts by department"></canvas>
        </div>
      </div>
    </section>

    <section class="panel">
      <header class="panel__head">
        <span class="stat-tile__icon tone-amber" style="width:32px;height:32px;font-size:13px" aria-hidden="true"><i class="fa-solid fa-user-slash"></i></span>
        <h2>Members Not Serving</h2>
        <span class="count-chip"><?= $stats['not_serving'] ?></span>
      </header>
      <div class="panel__body" style="padding:0">
        <div class="clist" style="border:0;border-radius:0">
          <?php foreach ($not_serving as $m): ?>
            <div class="crow">
              <?= mu_av($m['name'], 'xs') ?>
              <span class="crow__name"><?= htmlspecialchars($m['name']) ?></span>
              <span class="crow__phone"><?= htmlspecialchars($m['suburb']) ?></span>
              <?php if (mu_can('members.edit')): ?>
                <button class="chip-btn" type="button" data-open="modalAddMembers">Assign</button>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  </div>

<?php endif; ?>
</div>

<?php if ($has_module): ?>

<!-- ═════════════════ DEPARTMENT DETAIL DRAWER ═════════════════ -->
<div class="drawer-scrim" data-drawer-scrim hidden></div>
<aside class="drawer" id="deptDrawer" role="dialog" aria-modal="true" aria-labelledby="dName" hidden>
  <header class="drawer__head">
    <span class="stat-tile__icon tone-brand" style="width:48px;height:48px;border-radius:14px;font-size:18px" data-d-icon aria-hidden="true"><i class="fa-solid fa-sitemap"></i></span>
    <div class="drawer__title">
      <h2 id="dName">Department</h2>
      <p data-d-desc>—</p>
    </div>
    <button class="iconbtn" type="button" data-drawer-close aria-label="Close panel"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
  </header>

  <div class="drawer__body">
    <div class="tabs" role="tablist">
      <button role="tab" aria-selected="true"  data-tab="members">Members</button>
      <button role="tab" aria-selected="false" data-tab="attendance">Attendance</button>
      <button role="tab" aria-selected="false" data-tab="activities">Activities</button>
      <button role="tab" aria-selected="false" data-tab="settings">Settings</button>
    </div>

    <div class="tabpanel" data-panel="members">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
        <p style="flex:1;color:var(--muted);font-size:12.5px;font-weight:600"><span data-d-count>0</span> members serving</p>
        <?php if (mu_can('members.edit')): ?>
          <button class="btn btn--ghost" type="button" data-open="modalAddMembers"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Add Members</button>
        <?php endif; ?>
      </div>
      <div class="clist">
        <?php foreach (array_slice($members_demo, 0, 8) as $i => $m): ?>
          <div class="crow">
            <?= mu_av($m['name'], 'xs') ?>
            <span class="crow__name"><?= htmlspecialchars($m['name']) ?>
              <span class="tsub" style="display:block;color:var(--faint);font-size:11px;font-weight:500">
                <?= ['Coordinator','Member','Member','Assistant','Member','Member','Secretary','Member'][$i] ?>
                &middot; joined <?= mu_date($m['joined'], 'M Y') ?>
              </span>
            </span>
            <?php if (mu_can('members.edit')): ?>
              <button class="iconbtn iconbtn--sm" type="button" data-toast="Removed from department" aria-label="Remove <?= htmlspecialchars($m['name']) ?>"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="tabpanel" data-panel="attendance" hidden>
      <div class="chart-wrap" style="height:200px"><canvas id="deptAttChart" role="img" aria-label="Department attendance over the last 6 meetings"></canvas></div>
      <dl class="deflist" style="margin-top:14px">
        <div><dt>Attendance rate</dt><dd data-d-rate>—</dd></div>
        <div><dt>Active members</dt><dd data-d-active>—</dd></div>
      </dl>
    </div>

    <div class="tabpanel" data-panel="activities" hidden>
      <div class="timeline">
        <?php foreach ([
          ['26 Aug 2026', 'Rehearsal', 'Full turnout ahead of Sunday.'],
          ['19 Aug 2026', 'Planning meeting', 'Agreed the roster for September.'],
          ['12 Aug 2026', 'Training', 'New members shown the sound desk.'],
        ] as [$dt, $title, $note]): ?>
          <div class="tl-item">
            <div class="tl-item__head">
              <span class="tl-item__method"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i> <?= $title ?></span>
              <span class="tl-item__date"><?= $dt ?></span>
            </div>
            <p class="tl-item__notes"><?= $note ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="tabpanel" data-panel="settings" hidden>
      <dl class="deflist">
        <div><dt>Head of department</dt><dd data-d-head>—</dd></div>
        <div><dt>Assistant head</dt><dd data-d-asst>—</dd></div>
        <div><dt>Meeting day</dt><dd data-d-day>—</dd></div>
        <div><dt>Meeting time</dt><dd data-d-time>—</dd></div>
        <div><dt>Venue</dt><dd data-d-venue>—</dd></div>
        <div><dt>Status</dt><dd data-d-status>—</dd></div>
      </dl>
    </div>
  </div>

  <footer class="drawer__foot">
    <?php if (mu_can('members.edit')): ?>
      <button class="btn btn--ghost" type="button" data-open="modalDept"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</button>
      <button class="btn" type="button" data-open="modalAddMembers"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Add Members</button>
    <?php else: ?>
      <button class="btn" type="button" data-drawer-close>Close</button>
    <?php endif; ?>
  </footer>
</aside>

<!-- ═══════════════════ ADD / EDIT DEPARTMENT ═══════════════════ -->
<?php if (mu_can('members.edit')): ?>
<div class="modal-scrim" id="modalDept" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="dModalTitle">
    <header class="modal__head">
      <h2 id="dModalTitle">Add Department</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>
    <div class="modal__body">
      <div class="form-grid">
        <div class="field col-2"><label for="dpName">Name</label><input class="input" id="dpName" placeholder="e.g. Ushering"></div>
        <div class="field col-2"><label for="dpDesc">Description</label><textarea class="textarea" id="dpDesc" rows="2" placeholder="What does this department do?"></textarea></div>

        <div class="field col-2">
          <label>Icon</label>
          <div class="seg" role="group" aria-label="Icon" id="iconPicker" style="gap:7px">
            <?php foreach (['fa-hands-holding-circle','fa-music','fa-guitar','fa-fire','fa-hands-praying','fa-people-group','fa-child-reaching','fa-sliders','fa-user-tie','fa-bullhorn','fa-heart','fa-book-open','fa-van-shuttle','fa-camera'] as $i => $ic): ?>
              <button type="button" data-icon="<?= $ic ?>" aria-pressed="<?= $i === 0 ? 'true' : 'false' ?>" aria-label="<?= $ic ?>" style="width:38px;padding:8px 0;text-align:center"><i class="fa-solid <?= $ic ?>" aria-hidden="true"></i></button>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="field col-2">
          <label>Colour</label>
          <div style="display:flex;gap:8px;flex-wrap:wrap" id="colourPicker">
            <?php foreach (['brand','info','violet','warn','pink','teal','ok','slate','danger'] as $i => $tone): ?>
              <button type="button" class="stat-tile__icon tone-<?= $tone ?>" data-colour="<?= $tone ?>"
                      aria-pressed="<?= $i === 0 ? 'true' : 'false' ?>" aria-label="<?= $tone ?>"
                      style="width:32px;height:32px;border-radius:9px;border:2px solid transparent"></button>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="field"><label for="dpHead">Head of department</label><input class="input" id="dpHead" list="memberList" placeholder="Search members&hellip;"></div>
        <div class="field"><label for="dpAsst">Assistant head</label><input class="input" id="dpAsst" list="memberList" placeholder="Search members&hellip;"></div>
        <div class="field">
          <label for="dpDay">Meeting day</label>
          <select class="select" id="dpDay"><?php foreach (['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $day): ?><option><?= $day ?></option><?php endforeach; ?></select>
        </div>
        <div class="field"><label for="dpTime">Meeting time</label><input class="input" type="time" id="dpTime" value="18:00"></div>
        <div class="field col-2"><label for="dpVenue">Meeting venue</label><input class="input" id="dpVenue" placeholder="e.g. Fellowship Room"></div>
        <div class="field col-2">
          <label style="display:flex;align-items:center;gap:10px">
            <span class="switch"><input type="checkbox" id="dpActive" checked><span class="switch__track" aria-hidden="true"></span></span>
            <span style="color:var(--ink-2);font-size:13px;font-weight:600">Active</span>
          </label>
        </div>
      </div>
      <datalist id="memberList">
        <?php foreach ($members_demo as $m): ?><option value="<?= htmlspecialchars($m['name']) ?>"></option><?php endforeach; ?>
      </datalist>
    </div>
    <footer class="modal__foot">
      <span class="modal__spacer"></span>
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <button class="btn" type="button" data-close data-toast="Department saved">Save Department</button>
    </footer>
  </div>
</div>

<!-- ═══════════════ ADD MEMBERS TO DEPARTMENT ═══════════════ -->
<div class="modal-scrim" id="modalAddMembers" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="amTitle">
    <header class="modal__head">
      <h2 id="amTitle">Add Members to Department</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>
    <div class="modal__body">
      <div class="search-field" style="margin-bottom:10px">
        <i class="fa-solid fa-magnifying-glass search-field__icon" aria-hidden="true"></i>
        <input class="input" type="search" id="amSearch" placeholder="Search members&hellip;">
      </div>
      <div style="display:flex;gap:8px;margin-bottom:12px">
        <select class="select" id="amGender" style="flex:1"><option>All genders</option><option>Male</option><option>Female</option></select>
        <select class="select" id="amAge" style="flex:1">
          <option>All ages</option><option>Children (0-12)</option><option>Youth (13-24)</option><option>Adults (25-59)</option><option>Seniors (60+)</option>
        </select>
      </div>

      <div class="clist" style="max-height:270px;overflow-y:auto">
        <?php foreach ($members_demo as $m): ?>
          <label class="crow" style="cursor:pointer" data-am-row
                 data-name="<?= htmlspecialchars(mb_strtolower($m['name'])) ?>"
                 data-gender="<?= htmlspecialchars($m['gender']) ?>"
                 data-age="<?= (int) $m['age'] ?>">
            <input class="check" type="checkbox" data-am-check>
            <?= mu_av($m['name'], 'xs') ?>
            <span class="crow__name"><?= htmlspecialchars($m['name']) ?></span>
            <span class="crow__phone"><?= (int) $m['age'] ?> &middot; <?= htmlspecialchars($m['gender']) ?></span>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="field" style="margin-top:14px">
        <label for="amRole">Role in department</label>
        <select class="select" id="amRole"><option>Member</option><option>Coordinator</option><option>Assistant</option><option>Secretary</option><option>Treasurer</option></select>
      </div>
    </div>
    <footer class="modal__foot">
      <span style="color:var(--muted);font-size:12.5px;font-weight:700"><span data-am-count>0</span> selected</span>
      <span class="modal__spacer"></span>
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <button class="btn" type="button" data-close data-toast="Members added to department">Add Selected</button>
    </footer>
  </div>
</div>
<?php endif; ?>

<?php if (mu_can('members.delete')): ?>
<div class="modal-scrim" id="modalDeleteDept" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="ddTitle">
    <header class="modal__head">
      <h2 id="ddTitle">Delete department</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>
    <div class="modal__body">
      <div class="err-summary is-on" style="align-items:center">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        <span>Members currently serving here will be unassigned, but their member records are <strong>not</strong> deleted.</span>
      </div>
      <div class="field">
        <label for="ddConfirm">Type <strong data-dd-name>the department name</strong> to confirm</label>
        <input class="input" type="text" id="ddConfirm" autocomplete="off">
      </div>
    </div>
    <footer class="modal__foot">
      <span class="modal__spacer"></span>
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <button class="btn" type="button" id="ddGo" disabled style="background:linear-gradient(120deg,#8f1d33,var(--danger))">Delete department</button>
    </footer>
  </div>
</div>
<?php endif; ?>

<?php endif; ?>

<div class="toasts" id="toasts" aria-live="polite"></div>

<?php /* ══════════ DEMO ONLY — REMOVE BEFORE PRODUCTION ══════════ */ ?>
<details class="demo" aria-label="Demo role switcher">
  <summary class="demo__summary">
    <i class="fa-solid fa-flask" aria-hidden="true"></i>
    <span class="demo__summary-role"><?= htmlspecialchars($demo_roles[$demo_role]['user']['role_label']) ?></span>
    <i class="fa-solid fa-chevron-up demo__summary-chev" aria-hidden="true"></i>
  </summary>
  <p class="demo__warn"><i class="fa-solid fa-flask" aria-hidden="true"></i> DEMO ONLY — remove before production</p>
  <p class="demo__hint">Switch role to see this page filter itself</p>
  <ul class="demo__list">
    <?php foreach ($demo_roles as $key => $r): ?>
      <li><a class="demo__role<?= $key === $demo_role ? ' is-on' : '' ?>" href="?role=<?= urlencode($key) ?>"
             <?= $key === $demo_role ? 'aria-current="true"' : '' ?>>
        <span class="demo__av" aria-hidden="true"><?= htmlspecialchars($r['user']['initials']) ?></span>
        <?= htmlspecialchars($r['user']['role_label']) ?>
      </a></li>
    <?php endforeach; ?>
  </ul>
</details>
<?php /* ═══════════════════════ END DEMO ═══════════════════════ */ ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function () {
  'use strict';
  var still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var panel = document.getElementById('listPanel');
  var DEPTS = <?= json_encode(array_column($rows, null, 'id'), JSON_UNESCAPED_UNICODE) ?>;

  var toasts = document.getElementById('toasts');
  function toast(msg, kind) {
    kind = kind || 'success';
    var icons = { success: 'fa-circle-check', error: 'fa-circle-exclamation', info: 'fa-circle-info' };
    var el = document.createElement('div');
    el.className = 'toast is-' + kind;
    el.innerHTML = '<i class="fa-solid ' + icons[kind] + ' toast__icon" aria-hidden="true"></i>' +
      '<div class="toast__body"><p class="toast__title">' + msg + '</p></div>' +
      '<button class="toast__close" type="button" aria-label="Dismiss"><i class="fa-solid fa-xmark"></i></button>';
    toasts.appendChild(el);
    var kill = function () { el.classList.add('is-out'); setTimeout(function () { el.remove(); }, 250); };
    el.querySelector('.toast__close').addEventListener('click', kill);
    setTimeout(kill, 3400);
  }
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-toast]');
    if (t) { e.preventDefault(); toast(t.getAttribute('data-toast')); }
  });

  if (!panel) { return; }
  setTimeout(function () { panel.classList.add('is-loaded'); }, still ? 0 : 620);

  [].forEach.call(document.querySelectorAll('[data-count]'), function (el) {
    var target = parseFloat(el.getAttribute('data-count')) || 0;
    if (still) { el.textContent = target.toLocaleString(); return; }
    var start = performance.now();
    (function step(now) {
      var p = Math.min(1, (now - start) / 900), eased = 1 - Math.pow(1 - p, 3);
      el.textContent = Math.round(target * eased).toLocaleString();
      if (p < 1) { requestAnimationFrame(step); }
    })(start);
  });

  /* view switcher */
  var VIEW_KEY = 'mutendi-depts-view';
  function setView(v) {
    [].forEach.call(document.querySelectorAll('[data-view]'), function (b) {
      b.setAttribute('aria-pressed', String(b.getAttribute('data-view') === v));
    });
    [].forEach.call(document.querySelectorAll('[data-view-panel]'), function (p) {
      p.hidden = p.getAttribute('data-view-panel') !== v;
    });
    try { sessionStorage.setItem(VIEW_KEY, v); } catch (e) {}
  }
  [].forEach.call(document.querySelectorAll('[data-view]'), function (b) {
    b.addEventListener('click', function () { setView(b.getAttribute('data-view')); });
  });
  try { var sv = sessionStorage.getItem(VIEW_KEY); if (sv) { setView(sv); } } catch (e) {}

  /* search */
  var search = document.querySelector('[data-search]'),
      clearBtn = document.querySelector('[data-search-clear]'),
      resultCount = document.querySelector('[data-result-count]'),
      emptyState = document.querySelector('[data-empty]');

  function apply() {
    var q = search && search.value.trim() ? search.value.trim().toLowerCase() : '';
    var shown = 0;
    [].forEach.call(document.querySelectorAll('[data-row], [data-card]'), function (el) {
      var ok = !q || (el.getAttribute('data-name') || '').indexOf(q) !== -1;
      el.hidden = !ok;
      if (ok && el.hasAttribute('data-row')) { shown++; }
    });
    resultCount.textContent = shown;
    emptyState.hidden = shown !== 0;
    if (clearBtn) { clearBtn.hidden = !(search && search.value); }
  }
  if (search) { search.addEventListener('input', apply); }
  if (clearBtn) { clearBtn.addEventListener('click', function () { search.value = ''; apply(); search.focus(); }); }
  [].forEach.call(document.querySelectorAll('[data-reset-filters]'), function (b) {
    b.addEventListener('click', function () { if (search) { search.value = ''; } apply(); });
  });

  /* mobile cards */
  [].forEach.call(document.querySelectorAll('[data-card-toggle]'), function (b) {
    b.addEventListener('click', function () { b.closest('.pcard').classList.toggle('is-open'); });
  });

  /* org chart zoom */
  var zoom = 1, inner = document.getElementById('orgInner');
  function setZoom(z) { zoom = Math.min(1.4, Math.max(.6, z)); inner.style.transform = 'scale(' + zoom + ')'; }
  var zi = document.getElementById('zoomIn'), zo = document.getElementById('zoomOut');
  if (zi) { zi.addEventListener('click', function () { setZoom(zoom + .15); }); }
  if (zo) { zo.addEventListener('click', function () { setZoom(zoom - .15); }); }

  /* drawer */
  var drawer = document.getElementById('deptDrawer'), dScrim = document.querySelector('[data-drawer-scrim]'), attChart = null;

  function openDrawer(id) {
    var d = DEPTS[id];
    if (!d) { return; }
    drawer.querySelector('#dName').textContent = d.name;
    drawer.querySelector('[data-d-desc]').textContent = d.description;
    var ic = drawer.querySelector('[data-d-icon]');
    ic.className = 'stat-tile__icon tone-' + d.color;
    ic.style.cssText = 'width:48px;height:48px;border-radius:14px;font-size:18px';
    ic.innerHTML = '<i class="fa-solid ' + d.icon + '"></i>';

    drawer.querySelector('[data-d-count]').textContent = d.members;
    drawer.querySelector('[data-d-rate]').textContent = d.attendance_rate + '%';
    drawer.querySelector('[data-d-active]').textContent = d.active;
    drawer.querySelector('[data-d-head]').textContent = d.head;
    drawer.querySelector('[data-d-asst]').textContent = d.assistant;
    drawer.querySelector('[data-d-day]').textContent = d.day + 's';
    drawer.querySelector('[data-d-time]').textContent = d.time;
    drawer.querySelector('[data-d-venue]').textContent = d.venue;
    drawer.querySelector('[data-d-status]').textContent = d.status;

    dScrim.hidden = false; drawer.hidden = false;
    document.body.style.overflow = 'hidden';
    drawer.querySelector('[data-drawer-close]').focus();

    var cv = document.getElementById('deptAttChart');
    if (cv && window.Chart) {
      var base = Math.max(4, Math.round(d.members * d.attendance_rate / 100));
      var series = [0, 1, 2, 3, 4, 5].map(function (i) { return Math.max(2, base + ((i % 3) - 1) * 3); });
      if (attChart) { attChart.destroy(); }
      attChart = new Chart(cv, {
        type: 'line',
        data: { labels: ['M1','M2','M3','M4','M5','M6'],
                datasets: [{ label: 'Present', data: series, borderColor: '#662F97',
                  backgroundColor: 'rgba(102,47,151,.1)', fill: true, tension: .35,
                  pointRadius: 3, borderWidth: 2 }] },
        options: { responsive: true, maintainAspectRatio: false,
          animation: still ? false : { duration: 500 },
          plugins: { legend: { display: false } },
          scales: { x: { grid: { display: false }, border: { display: false } },
                    y: { grid: { color: '#ECE7F3' }, border: { display: false }, beginAtZero: true } } }
      });
    }
  }
  function closeDrawer() { drawer.hidden = true; dScrim.hidden = true; document.body.style.overflow = ''; }
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-open-dept]');
    if (t) { e.preventDefault(); openDrawer(parseInt(t.getAttribute('data-open-dept'), 10)); }
  });
  drawer.querySelector('[data-drawer-close]').addEventListener('click', closeDrawer);
  dScrim.addEventListener('click', closeDrawer);
  [].forEach.call(drawer.querySelectorAll('[data-tab]'), function (b) {
    b.addEventListener('click', function () {
      [].forEach.call(drawer.querySelectorAll('[data-tab]'), function (o) { o.setAttribute('aria-selected', String(o === b)); });
      [].forEach.call(drawer.querySelectorAll('[data-panel]'), function (p) {
        p.hidden = p.getAttribute('data-panel') !== b.getAttribute('data-tab');
      });
    });
  });

  /* modals */
  document.addEventListener('click', function (e) {
    var open = e.target.closest('[data-open]');
    if (open) {
      e.preventDefault();
      var m = document.getElementById(open.getAttribute('data-open'));
      if (!m) { return; }
      if (open.getAttribute('data-open') === 'modalDeleteDept') {
        var n = open.getAttribute('data-name') || '';
        m.querySelector('[data-dd-name]').textContent = n;
        var inp = m.querySelector('#ddConfirm'), go = m.querySelector('#ddGo');
        inp.value = ''; go.disabled = true;
        inp.oninput = function () { go.disabled = inp.value.trim() !== n; };
      }
      m.hidden = false; document.body.style.overflow = 'hidden';
      return;
    }
    var close = e.target.closest('[data-close]');
    if (close) { e.preventDefault(); close.closest('.modal-scrim').hidden = true; document.body.style.overflow = ''; return; }
    if (e.target.classList.contains('modal-scrim')) { e.target.hidden = true; document.body.style.overflow = ''; }
  });
  var ddGo = document.getElementById('ddGo');
  if (ddGo) {
    ddGo.addEventListener('click', function () {
      ddGo.closest('.modal-scrim').hidden = true; document.body.style.overflow = '';
      toast('Department deleted', 'error');
    });
  }

  /* icon + colour pickers */
  [].forEach.call(document.querySelectorAll('#iconPicker [data-icon]'), function (b) {
    b.addEventListener('click', function () {
      [].forEach.call(document.querySelectorAll('#iconPicker [data-icon]'), function (o) { o.setAttribute('aria-pressed', String(o === b)); });
    });
  });
  [].forEach.call(document.querySelectorAll('#colourPicker [data-colour]'), function (b) {
    b.addEventListener('click', function () {
      [].forEach.call(document.querySelectorAll('#colourPicker [data-colour]'), function (o) {
        o.setAttribute('aria-pressed', String(o === b));
        o.style.borderColor = o === b ? 'var(--ink)' : 'transparent';
      });
    });
  });

  /* add-members modal: search, filters, live count */
  var amSearch = document.getElementById('amSearch');
  function amFilter() {
    var q = amSearch ? amSearch.value.trim().toLowerCase() : '';
    var g = document.getElementById('amGender').value;
    var a = document.getElementById('amAge').value;
    [].forEach.call(document.querySelectorAll('[data-am-row]'), function (r) {
      var age = parseInt(r.getAttribute('data-age'), 10);
      var band = age <= 12 ? 'Children (0-12)' : age <= 24 ? 'Youth (13-24)' : age <= 59 ? 'Adults (25-59)' : 'Seniors (60+)';
      var ok = true;
      if (q && (r.getAttribute('data-name') || '').indexOf(q) === -1) { ok = false; }
      if (ok && g !== 'All genders' && r.getAttribute('data-gender') !== g) { ok = false; }
      if (ok && a !== 'All ages' && band !== a) { ok = false; }
      r.hidden = !ok;
    });
  }
  if (amSearch) {
    amSearch.addEventListener('input', amFilter);
    document.getElementById('amGender').addEventListener('change', amFilter);
    document.getElementById('amAge').addEventListener('change', amFilter);
    [].forEach.call(document.querySelectorAll('[data-am-check]'), function (cb) {
      cb.addEventListener('change', function () {
        document.querySelector('[data-am-count]').textContent = document.querySelectorAll('[data-am-check]:checked').length;
      });
    });
  }

  /* row menus escape the table's scroll box */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-menu-btn]');
    if (!btn || !btn.closest('.dt-wrap')) { return; }
    var p2 = btn.parentElement.querySelector('[data-menu-panel]');
    if (!p2 || p2.hidden) { return; }
    var r = btn.getBoundingClientRect();
    p2.style.position = 'fixed';
    p2.style.top = Math.min(r.bottom + 8, window.innerHeight - p2.offsetHeight - 12) + 'px';
    p2.style.left = Math.max(12, r.right - p2.offsetWidth) + 'px';
    p2.style.right = 'auto';
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') { return; }
    if (!drawer.hidden) { closeDrawer(); }
    [].forEach.call(document.querySelectorAll('.modal-scrim'), function (m) {
      if (!m.hidden) { m.hidden = true; document.body.style.overflow = ''; }
    });
  });

  /* department sizes chart — same palette and options as the dashboard */
  var sizes = document.getElementById('deptChart');
  if (sizes && window.Chart) {
    Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.color = '#6E6880';
    var names = Object.keys(DEPTS).map(function (k) { return DEPTS[k].name; });
    var counts = Object.keys(DEPTS).map(function (k) { return DEPTS[k].members; });
    new Chart(sizes, {
      type: 'bar',
      data: { labels: names, datasets: [{ label: 'Members', data: counts, backgroundColor: '#662F97', borderRadius: 6, maxBarThickness: 18 }] },
      options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false,
        animation: still ? false : { duration: 500 },
        plugins: { legend: { display: false } },
        scales: { x: { grid: { color: '#ECE7F3' }, border: { display: false }, beginAtZero: true },
                  y: { grid: { display: false }, border: { display: false } } } }
    });
  }

  apply();
})();
</script>

<?php require __DIR__ . '/../components/footer.php'; ?>

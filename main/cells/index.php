<?php
/**
 * Mutendi CMS — Cell Groups.
 *
 * Home cells / small groups, their leaders, members and meetings.
 * UI only. Requires the 'cell_groups' module.
 *
 * A Cell Group Leader sees ONLY the cell they lead: the page drops the
 * directory and renders that single group's detail instead (see $own_cell).
 */

require __DIR__ . '/../includes/config.php';

/* ══════════════ DEMO ONLY — REMOVE BEFORE PRODUCTION ══════════════ */
$demo_role       = isset($_GET['role'], $demo_roles[$_GET['role']]) ? $_GET['role'] : 'church_admin';
/* array_merge, not assignment: the scope keys from config must survive
   so a branch-scope user stays scoped on this page. */
$user            = array_merge($user, $demo_roles[$demo_role]['user']);
$permissions     = $demo_roles[$demo_role]['perms'];
$enabled_modules = $demo_roles[$demo_role]['modules'];
$own_cell_name   = $demo_roles[$demo_role]['own_cell'] ?? null;
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
    function mu_ago(?int $days): string {
        if ($days === null) { return 'Never'; }
        if ($days <= 0) { return 'Today'; }
        if ($days === 1) { return 'Yesterday'; }
        if ($days < 7)  { return $days . ' days ago'; }
        if ($days < 31) { $w = (int) floor($days / 7); return $w . ' week' . ($w > 1 ? 's' : '') . ' ago'; }
        $m = (int) floor($days / 30);
        return $m . ' month' . ($m > 1 ? 's' : '') . ' ago';
    }
    function mu_date(string $iso, string $fmt = 'd M Y'): string { return date($fmt, strtotime($iso)); }
}

/** Sparkline of the last six meetings' attendance. */
function mu_spark(array $vals, int $cap): string {
    $max = max(1, max($vals));
    $out = '<span class="spark" aria-hidden="true">';
    foreach ($vals as $v) {
        $out .= '<span style="height:' . max(12, (int) round($v / $max * 100)) . '%"></span>';
    }
    return $out . '</span>';
}

$has_module = mu_mod('cell_groups');

/* The whole directory, or just this leader's own cell. */
$all_cells = $has_module ? $cells_demo : [];
$is_leader_view = $own_cell_name !== null;
$rows = $is_leader_view
    ? array_values(array_filter($all_cells, fn($c) => $c['name'] === $own_cell_name))
    : $all_cells;

$stats = $people_stats['cells'];

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

$faces = $members_demo;

/* Cell groups belong to a branch. Same rule as departments: the toggle is
   offered only to an organisation-scope user looking at every branch. */
$scope_mode = ($_GET['scope'] ?? 'all') === 'mine' ? 'mine' : 'all';
$show_scope_toggle = $branch_aware && $viewing_all && ($user['scope'] ?? 'organisation') !== 'branch';
$scope_branch = $show_scope_toggle && $scope_mode === 'mine'
    ? get_branch($user['branch_id'] ?? 0)
    : null;

if ($branch_aware && $rows) {
    foreach ($rows as $i => $c) { $rows[$i]['_branch'] = mu_branch_for($c['name'] . $c['id']); }
    $pin = $scope_branch ? (int) $scope_branch['id'] : (!$viewing_all ? (int) $current_branch : null);
    if ($pin !== null) {
        $rows = array_values(array_filter($rows, function ($c) use ($pin) {
            return $c['_branch'] && (int) $c['_branch']['id'] === $pin;
        }));
    }
    foreach ($stats as $k => $v) { $stats[$k] = $k === 'avg_size' ? $v : (int) round(mu_branch_share($v)); }
}
$max_members = $all_cells ? max(array_column($all_cells, 'members')) : 1;

/* Cells with no meeting recorded in 14+ days, or a falling average. */
$attention = array_values(array_filter($all_cells, fn($c) => $c['last_meeting_days'] >= 14 || $c['avg_attendance'] < 55));

$zone_tones = ['North Zone' => 'var(--info)', 'South Zone' => 'var(--brand-500)', 'East Zone' => '#0F766E',
               'West Zone' => 'var(--warn)', 'Central Zone' => '#6D28D9'];

$page_title = 'Cell Groups';
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
        <span aria-current="page">Cell Groups</span>
      </nav>
      <div class="title-row">
        <h1 class="page__title"><?= $is_leader_view ? 'My Cell Group' : 'Cell Groups' ?></h1>
        <?php if (!$is_leader_view): ?>
          <span class="count-chip" data-count="<?= $stats['total'] ?>">0</span>
        <?php endif; ?>
      </div>
      <p class="page__sub">
        <?= $is_leader_view
            ? 'The cell group you lead, its members and its meetings.'
            : 'Manage your home cells and small groups.' ?>
      </p>
    </div>
    <?php if ($has_module && !$is_leader_view): ?>
      <div class="page__actions">
        <?php if (mu_can('members.edit')): ?>
          <button class="btn" type="button" data-open="modalCell"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add Cell Group</button>
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
    <?php elseif ($has_module && $is_leader_view): ?>
      <div class="page__actions">
        <button class="btn" type="button" data-open="modalMeeting"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i> Record Meeting</button>
      </div>
    <?php endif; ?>
  </header>

<?php if (!$has_module): ?>

  <section class="panel">
    <div class="empty">
      <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-plug-circle-xmark"></i></span>
      <h3>The Cell Groups module is switched off</h3>
      <p>Your church's plan does not include cell groups. A church administrator can request it from the platform team.</p>
      <a class="btn" href="<?= $base_url ?>index.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Dashboard</a>
    </div>
  </section>

<?php elseif ($is_leader_view): ?>

  <!-- ═════════ SINGLE-CELL VIEW — Cell Group Leaders only ═════════ -->
  <?php $c = $rows[0] ?? null; ?>
  <?php if (!$c): ?>
    <section class="panel">
      <div class="empty">
        <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-people-group"></i></span>
        <h3>You do not lead a cell group yet</h3>
        <p>Once a church administrator assigns you a cell, it will appear here.</p>
      </div>
    </section>
  <?php else: ?>

    <div class="stat-strip">
      <?php foreach ([
        ['Members in My Cell', $c['members'],          'fa-people-group', 'blue'],
        ['Last Attendance',    $c['last_attendance'],  'fa-clipboard-check', 'green'],
        ['Average Attendance', $c['avg_attendance'],   'fa-chart-simple', 'purple'],
        ['Days Since Meeting', $c['last_meeting_days'],'fa-calendar-day', 'amber'],
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

    <section class="panel">
      <header class="panel__head">
        <span class="stat-tile__icon tone-purple" style="width:36px;height:36px;font-size:14px" aria-hidden="true"><i class="fa-solid fa-people-group"></i></span>
        <h2><?= htmlspecialchars($c['name']) ?></h2>
        <span class="pill is-brand"><?= htmlspecialchars($c['zone']) ?></span>
      </header>
      <div class="panel__body">
        <div class="grid grid--2" style="margin:0">
          <div>
            <dl class="deflist">
              <div><dt>Leader</dt><dd><?= htmlspecialchars($c['leader']) ?></dd></div>
              <div><dt>Assistant leader</dt><dd><?= htmlspecialchars($c['assistant']) ?></dd></div>
              <div><dt>Meets</dt><dd><?= htmlspecialchars($c['day']) ?>s at <?= htmlspecialchars($c['time']) ?></dd></div>
              <div><dt>Venue</dt><dd><?= htmlspecialchars($c['venue']) ?>, <?= htmlspecialchars($c['suburb']) ?></dd></div>
              <div><dt>Last meeting</dt><dd><?= mu_date($c['last_meeting']) ?> &middot; <?= (int) $c['last_attendance'] ?> present</dd></div>
            </dl>
          </div>
          <div>
            <p class="modal__group" style="margin-top:0">Attendance, last 6 meetings</p>
            <div class="chart-wrap" style="height:190px"><canvas id="myCellChart" role="img" aria-label="Attendance over the last 6 meetings"></canvas></div>
          </div>
        </div>

        <p class="modal__group" style="margin-top:20px">Members</p>
        <div class="clist">
          <?php foreach (array_slice($members_demo, 0, 10) as $m): ?>
            <div class="crow">
              <?= mu_av($m['name'], 'xs') ?>
              <span class="crow__name"><?= htmlspecialchars($m['name']) ?></span>
              <span class="crow__phone"><?= htmlspecialchars($m['phone']) ?></span>
              <a class="iconbtn iconbtn--sm" href="tel:<?= htmlspecialchars(str_replace(' ', '', $m['phone'])) ?>" aria-label="Call <?= htmlspecialchars($m['name']) ?>"><i class="fa-solid fa-phone" aria-hidden="true"></i></a>
            </div>
          <?php endforeach; ?>
        </div>

        <p class="modal__group" style="margin-top:20px">Recent meetings</p>
        <div class="timeline" style="margin-top:10px">
          <?php foreach ($cell_meetings_demo as $mt): ?>
            <div class="tl-item">
              <div class="tl-item__head">
                <span class="tl-item__method"><i class="fa-solid fa-book-open" aria-hidden="true"></i> <?= htmlspecialchars($mt['topic']) ?></span>
                <span class="tl-item__date"><?= mu_date($mt['date']) ?></span>
              </div>
              <p class="tl-item__who"><?= (int) $mt['attendance'] ?> present</p>
              <p class="tl-item__notes"><?= htmlspecialchars($mt['notes']) ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>

<?php else: ?>

  <div class="stat-strip">
    <?php foreach ([
      ['Total Cell Groups',      $stats['total'],        'fa-people-group',   'blue'],
      ['Members in Cells',       $stats['in_cells'],     'fa-users',          'green'],
      ['Average Cell Size',      $stats['avg_size'],     'fa-chart-simple',   'purple'],
      ['Cells Meeting This Week',$stats['meeting_week'], 'fa-calendar-week',  'amber'],
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
      <button type="button" data-view="map"   aria-pressed="false" aria-label="Map view"><i class="fa-solid fa-map-location-dot" aria-hidden="true"></i></button>
    </div>
    <?php if ($show_scope_toggle): ?>
      <div class="scope-toggle" role="group" aria-label="Scope">
        <a href="?scope=all" role="button" aria-pressed="<?= $scope_mode === 'all' ? 'true' : 'false' ?>">All <?= htmlspecialchars(t('branch_plural')) ?></a>
        <a href="?scope=mine" role="button" aria-pressed="<?= $scope_mode === 'mine' ? 'true' : 'false' ?>">This <?= htmlspecialchars(t('branch_singular')) ?> only</a>
      </div>
    <?php endif; ?>
    <div class="search-field" style="flex:1;max-width:300px">
      <i class="fa-solid fa-magnifying-glass search-field__icon" aria-hidden="true"></i>
      <input class="input" type="search" id="fSearch" data-search placeholder="Search cells or leaders&hellip;">
      <button class="search-field__clear" type="button" data-search-clear hidden aria-label="Clear search"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </div>
    <select class="select" id="fZone" data-filter style="width:auto">
      <option>All zones</option>
      <?php foreach ($zones_demo as $z): ?><option><?= htmlspecialchars($z) ?></option><?php endforeach; ?>
    </select>
    <p style="color:var(--muted);font-size:12.5px;font-weight:600"><span data-result-count><?= count($rows) ?></span> shown</p>
  </div>

  <section class="panel" id="listPanel" aria-live="polite">
    <div data-skeleton style="padding:16px">
      <div class="cardgrid cardgrid--3">
        <?php for ($i = 0; $i < 6; $i++): ?>
          <div class="sk-card">
            <span class="sk sk--text" style="width:55%;display:block"></span>
            <div style="display:flex;gap:9px;align-items:center;margin-top:14px"><span class="sk sk--av"></span><span class="sk sk--text" style="flex:1"></span></div>
            <span class="sk sk--line" style="width:70%"></span>
            <span class="sk" style="height:30px;display:block;margin-top:14px"></span>
          </div>
        <?php endfor; ?>
      </div>
    </div>

    <div data-content>

      <!-- ─────────────── CARD GRID ─────────────── -->
      <div data-view-panel="cards" style="padding:16px">
        <div class="cardgrid cardgrid--3 stagger">
          <?php foreach ($rows as $c): ?>
            <article class="gcard" data-card
                     data-name="<?= htmlspecialchars(mb_strtolower($c['name'] . ' ' . $c['leader'])) ?>"
                     data-zone="<?= htmlspecialchars($c['zone']) ?>"
                     <?= $branch_aware ? 'data-branch="' . htmlspecialchars($c['_branch']['name'] ?? '') . '"' : '' ?>>
              <div style="display:flex;align-items:center;gap:9px;flex-wrap:wrap">
                <h3 style="color:var(--ink);font-size:15px;font-weight:800;letter-spacing:-.02em"><?= htmlspecialchars($c['name']) ?></h3>
                <span class="pill is-brand"><?= htmlspecialchars($c['zone']) ?></span>
              </div>
              <?php if ($show_branch): ?><p style="margin-top:10px"><?= mu_branch_chip($c['_branch'] ?? null) ?></p><?php endif; ?>

              <div style="display:flex;align-items:center;gap:9px;margin-top:13px">
                <?= mu_av($c['leader'], 'sm') ?>
                <span style="min-width:0">
                  <span style="display:block;color:var(--ink);font-size:12.5px;font-weight:700"><?= htmlspecialchars($c['leader']) ?></span>
                  <a href="tel:<?= htmlspecialchars(str_replace(' ', '', $c['leader_phone'])) ?>" style="color:var(--muted);font-size:11.5px">
                    <i class="fa-solid fa-phone" style="color:var(--brand-300)" aria-hidden="true"></i> <?= htmlspecialchars($c['leader_phone']) ?>
                  </a>
                </span>
              </div>

              <div style="display:flex;align-items:center;gap:12px;margin-top:14px">
                <span style="color:var(--ink);font-size:1.5rem;font-weight:800;letter-spacing:-.03em;line-height:1"><?= (int) $c['members'] ?></span>
                <span class="av-stack">
                  <?php foreach (array_slice($faces, 0, 4) as $m): ?><?= mu_av($m['name'], 'xs') ?><?php endforeach; ?>
                  <?php if ($c['members'] > 4): ?><span class="av-stack__more">+<?= (int) $c['members'] - 4 ?></span><?php endif; ?>
                </span>
              </div>

              <p style="margin-top:12px;color:var(--muted);font-size:12px">
                <i class="fa-regular fa-calendar" style="color:var(--brand-300)" aria-hidden="true"></i> <?= htmlspecialchars($c['day']) ?>s <?= htmlspecialchars($c['time']) ?><br>
                <i class="fa-solid fa-location-dot" style="color:var(--brand-300)" aria-hidden="true"></i> <?= htmlspecialchars($c['venue']) ?>
              </p>

              <div style="margin-top:14px">
                <p style="color:var(--faint);font-size:10.5px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;margin-bottom:6px">Last 6 meetings</p>
                <?= mu_spark($c['sparkline'], (int) $c['members']) ?>
              </div>

              <p style="margin-top:12px;padding-top:12px;border-top:1px solid var(--line);color:var(--muted);font-size:11.5px;font-weight:600">
                <?php if ($c['recorded']): ?>
                  <i class="fa-solid fa-circle-check" style="color:var(--ok)" aria-hidden="true"></i>
                  Last met <?= mu_ago((int) $c['last_meeting_days']) ?>
                <?php else: ?>
                  <i class="fa-solid fa-triangle-exclamation" style="color:var(--warn)" aria-hidden="true"></i>
                  No attendance recorded for <?= (int) $c['last_meeting_days'] ?> days
                <?php endif; ?>
              </p>

              <div style="display:flex;gap:14px;margin-top:12px">
                <button class="chip-btn" type="button" style="border:0;padding:0;color:var(--brand-600)" data-open-cell="<?= (int) $c['id'] ?>">View</button>
                <?php if (mu_can('members.edit')): ?>
                  <button class="chip-btn" type="button" style="border:0;padding:0;color:var(--brand-600)" data-open="modalCell">Edit</button>
                  <button class="chip-btn" type="button" style="border:0;padding:0;color:var(--brand-600)" data-open="modalMeeting">Record Meeting</button>
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
                <th style="width:38px"><input class="check" type="checkbox" data-check-all aria-label="Select all cell groups"></th>
                <th style="width:44px">#</th>
                <th>Cell Group</th>
                <?php if ($show_branch): ?><th><?= htmlspecialchars(t('branch_singular')) ?></th><?php endif; ?>
                <th>Leader</th>
                <th>Assistant Leader</th>
                <th>Members</th>
                <th>Meeting</th>
                <th>Zone / Area</th>
                <th>Last Meeting</th>
                <th>Avg Attendance</th>
                <th>Status</th>
                <th style="text-align:right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $i => $c): ?>
                <tr data-row
                    data-name="<?= htmlspecialchars(mb_strtolower($c['name'] . ' ' . $c['leader'])) ?>"
                    data-zone="<?= htmlspecialchars($c['zone']) ?>"
                    <?= $branch_aware ? 'data-branch="' . htmlspecialchars($c['_branch']['name'] ?? '') . '"' : '' ?>>
                  <td><input class="check" type="checkbox" data-row-check aria-label="Select <?= htmlspecialchars($c['name']) ?>"></td>
                  <td class="num"><?= $i + 1 ?></td>
                  <td>
                    <button class="person" type="button" data-open-cell="<?= (int) $c['id'] ?>">
                      <span class="person__text">
                        <span class="person__name"><?= htmlspecialchars($c['name']) ?></span>
                        <span class="tsub"><?= htmlspecialchars($c['zone']) ?></span>
                      </span>
                    </button>
                  </td>
                  <?php if ($show_branch): ?><td class="nowrap"><?= mu_branch_chip($c['_branch'] ?? null) ?></td><?php endif; ?>
                  <td>
                    <span class="person"><?= mu_av($c['leader'], 'sm') ?>
                      <span class="person__text"><span class="person__name"><?= htmlspecialchars($c['leader']) ?></span>
                      <span class="tsub"><?= htmlspecialchars($c['leader_phone']) ?></span></span>
                    </span>
                  </td>
                  <td class="nowrap"><?= htmlspecialchars($c['assistant']) ?></td>
                  <td style="min-width:120px">
                    <span class="minibar">
                      <strong style="color:var(--ink)"><?= (int) $c['members'] ?></strong>
                      <span class="minibar__track"><span class="minibar__fill" style="width:<?= (int) round($c['members'] / $max_members * 100) ?>%;background:var(--brand-500)"></span></span>
                    </span>
                  </td>
                  <td class="nowrap"><?= htmlspecialchars($c['day']) ?>s <?= htmlspecialchars($c['time']) ?><span class="tsub"><?= htmlspecialchars($c['venue']) ?></span></td>
                  <td class="nowrap"><?= htmlspecialchars($c['zone']) ?></td>
                  <td class="nowrap"><?= mu_date($c['last_meeting']) ?><span class="tsub"><?= (int) $c['last_attendance'] ?> present</span></td>
                  <td style="min-width:120px">
                    <span class="minibar">
                      <strong style="color:var(--ink)"><?= (int) $c['avg_attendance'] ?>%</strong>
                      <span class="minibar__track"><span class="minibar__fill" style="width:<?= (int) $c['avg_attendance'] ?>%;background:<?= $c['avg_attendance'] < 55 ? 'var(--warn)' : 'var(--ok)' ?>"></span></span>
                    </span>
                  </td>
                  <td><span class="spill is-<?= strtolower($c['status']) ?>"><?= htmlspecialchars($c['status']) ?></span></td>
                  <td>
                    <div class="rowacts">
                      <button class="iconbtn iconbtn--sm" type="button" data-open-cell="<?= (int) $c['id'] ?>" aria-label="View <?= htmlspecialchars($c['name']) ?>"><i class="fa-regular fa-eye" aria-hidden="true"></i></button>
                      <?php if (mu_can('members.edit')): ?>
                        <button class="iconbtn iconbtn--sm" type="button" data-open="modalMeeting" aria-label="Record meeting for <?= htmlspecialchars($c['name']) ?>"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i></button>
                        <button class="iconbtn iconbtn--sm" type="button" data-open="modalCell" aria-label="Edit <?= htmlspecialchars($c['name']) ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i></button>
                      <?php endif; ?>
                      <div class="drop" data-menu>
                        <button class="iconbtn iconbtn--sm" type="button" aria-haspopup="true" aria-expanded="false" data-menu-btn aria-label="More actions"><i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i></button>
                        <div class="menu menu--sm" data-menu-panel hidden>
                          <a class="menu__item" href="#" data-open-cell="<?= (int) $c['id'] ?>"><i class="fa-regular fa-eye" aria-hidden="true"></i> View Details</a>
                          <?php if (mu_can('members.edit')): ?>
                            <a class="menu__item" href="#" data-open="modalMeeting"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i> Record Meeting</a>
                            <a class="menu__item" href="#" data-open="modalCell"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</a>
                            <a class="menu__item" href="#" data-open="modalAddToCell"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Add Members</a>
                          <?php endif; ?>
                          <?php if (mu_mod('communication')): ?><a class="menu__item" href="#" data-open="modalMsgGroup"><i class="fa-regular fa-comment" aria-hidden="true"></i> Message Group</a><?php endif; ?>
                          <a class="menu__item" href="#" data-open-cell="<?= (int) $c['id'] ?>"><i class="fa-solid fa-chart-line" aria-hidden="true"></i> View Attendance History</a>
                          <?php if (mu_can('members.edit')): ?>
                            <div class="menu__sep" role="separator"></div>
                            <a class="menu__item" href="#" data-toast="Cell group deactivated"><i class="fa-solid fa-ban" aria-hidden="true"></i> Deactivate</a>
                          <?php endif; ?>
                          <?php if (mu_can('members.delete')): ?>
                            <a class="menu__item menu__item--danger" href="#" data-toast="Cell group deleted"><i class="fa-solid fa-trash" aria-hidden="true"></i> Delete</a>
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
          <?php foreach ($rows as $c): ?>
            <article class="pcard" data-card
                     data-name="<?= htmlspecialchars(mb_strtolower($c['name'] . ' ' . $c['leader'])) ?>"
                     data-zone="<?= htmlspecialchars($c['zone']) ?>"
                     <?= $branch_aware ? 'data-branch="' . htmlspecialchars($c['_branch']['name'] ?? '') . '"' : '' ?>>
              <button class="pcard__main" type="button" data-card-toggle>
                <?= mu_av($c['leader'], 'md') ?>
                <span class="pcard__text">
                  <span class="pcard__name"><?= htmlspecialchars($c['name']) ?></span>
                  <span class="pcard__meta"><?= (int) $c['members'] ?> members &middot; <?= htmlspecialchars($c['zone']) ?></span>
                </span>
                <i class="fa-solid fa-chevron-down pcard__chev" aria-hidden="true"></i>
              </button>
              <div class="pcard__more">
                <dl>
                  <?php if ($show_branch): ?><div class="pcard__row"><dt><?= htmlspecialchars(t('branch_singular')) ?></dt><dd><?= htmlspecialchars($c['_branch']['name'] ?? '—') ?></dd></div><?php endif; ?>
                  <div class="pcard__row"><dt>Leader</dt><dd><?= htmlspecialchars($c['leader']) ?></dd></div>
                  <div class="pcard__row"><dt>Phone</dt><dd><?= htmlspecialchars($c['leader_phone']) ?></dd></div>
                  <div class="pcard__row"><dt>Meets</dt><dd><?= htmlspecialchars($c['day']) ?>s <?= htmlspecialchars($c['time']) ?></dd></div>
                  <div class="pcard__row"><dt>Venue</dt><dd><?= htmlspecialchars($c['venue']) ?></dd></div>
                  <div class="pcard__row"><dt>Last meeting</dt><dd><?= mu_ago((int) $c['last_meeting_days']) ?></dd></div>
                  <div class="pcard__row"><dt>Avg attendance</dt><dd><?= (int) $c['avg_attendance'] ?>%</dd></div>
                </dl>
                <div class="pcard__acts">
                  <button class="chip-btn" type="button" data-open-cell="<?= (int) $c['id'] ?>">View</button>
                  <?php if (mu_can('members.edit')): ?><button class="chip-btn" type="button" data-open="modalMeeting">Record Meeting</button><?php endif; ?>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- ─────────────── MAP (styled placeholder) ─────────────── -->
      <div data-view-panel="map" hidden>
        <div class="mapbox">
          <div class="mapbox__canvas">
            <?php
              /* Fixed positions: a static illustration, not a real map API. */
              $spots = [[18,26],[34,54],[52,22],[68,60],[42,78],[78,34],[26,66],[60,42],[86,70],[12,48],[72,16],[46,60]];
              foreach ($rows as $i => $c):
                [$x, $y] = $spots[$i % count($spots)];
                $tone = $zone_tones[$c['zone']] ?? 'var(--brand-500)';
            ?>
              <button class="mappin" type="button" style="left:<?= $x ?>%;top:<?= $y ?>%" data-open-cell="<?= (int) $c['id'] ?>"
                      data-zone="<?= htmlspecialchars($c['zone']) ?>" aria-label="<?= htmlspecialchars($c['name']) ?>">
                <span class="mappin__dot" style="background:<?= $tone ?>"><span><?= (int) $c['members'] ?></span></span>
                <span class="mappin__label"><?= htmlspecialchars($c['name']) ?></span>
              </button>
            <?php endforeach; ?>
          </div>

          <aside class="mapbox__side">
            <div class="mapbox__legend">
              <p style="color:var(--faint);font-size:10px;font-weight:800;letter-spacing:.12em;text-transform:uppercase">Zones</p>
              <?php foreach ($zones_demo as $z): ?>
                <button class="leg" type="button" data-zone-filter="<?= htmlspecialchars($z) ?>" style="text-align:left">
                  <span class="leg__sw" style="background:<?= $zone_tones[$z] ?? 'var(--brand-500)' ?>"></span>
                  <?= htmlspecialchars($z) ?>
                  <span style="margin-left:auto;color:var(--faint)"><?= count(array_filter($rows, fn($c) => $c['zone'] === $z)) ?></span>
                </button>
              <?php endforeach; ?>
            </div>
            <div class="mapbox__list">
              <div class="clist" style="border:0;border-radius:0">
                <?php foreach ($rows as $c): ?>
                  <button class="crow" type="button" data-map-row data-zone="<?= htmlspecialchars($c['zone']) ?>"
                          data-open-cell="<?= (int) $c['id'] ?>" style="width:100%;text-align:left">
                    <span class="crow__dot" style="background:<?= $zone_tones[$c['zone']] ?? 'var(--brand-500)' ?>"></span>
                    <span class="crow__name"><?= htmlspecialchars($c['name']) ?></span>
                    <span class="crow__phone"><?= (int) $c['members'] ?></span>
                  </button>
                <?php endforeach; ?>
              </div>
            </div>
          </aside>
        </div>
      </div>

      <div class="empty" data-empty hidden>
        <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-people-group"></i></span>
        <h3>No cell groups match that search</h3>
        <p>Try another name or zone, or clear the search to see every cell again.</p>
        <button class="btn" type="button" data-reset-filters><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Clear search</button>
      </div>
    </div>
  </section>

  <!-- ═══════════════ ATTENDANCE + ATTENTION ═══════════════ -->
  <div class="grid grid--2" style="margin-top:16px">
    <section class="panel">
      <header class="panel__head">
        <span class="stat-tile__icon tone-purple" style="width:32px;height:32px;font-size:13px" aria-hidden="true"><i class="fa-solid fa-chart-simple"></i></span>
        <h2>Attendance by Cell Group</h2>
      </header>
      <div class="panel__body">
        <div class="chart-wrap" style="height:320px"><canvas id="cellChart" role="img" aria-label="Average attendance by cell group"></canvas></div>
      </div>
    </section>

    <section class="panel">
      <header class="panel__head">
        <span class="stat-tile__icon tone-amber" style="width:32px;height:32px;font-size:13px" aria-hidden="true"><i class="fa-solid fa-triangle-exclamation"></i></span>
        <h2>Cells Needing Attention</h2>
        <span class="count-chip"><?= count($attention) ?></span>
      </header>
      <div class="panel__body" style="padding:0">
        <?php if (!$attention): ?>
          <div class="empty" style="padding:34px 16px">
            <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></span>
            <h3>Every cell is on track</h3>
            <p>All cells have met recently and attendance is holding up.</p>
          </div>
        <?php else: ?>
          <div class="clist" style="border:0;border-radius:0">
            <?php foreach ($attention as $c): ?>
              <div class="crow">
                <?= mu_av($c['leader'], 'xs') ?>
                <span class="crow__name"><?= htmlspecialchars($c['name']) ?>
                  <span style="display:block;color:var(--faint);font-size:11px;font-weight:500">
                    <?= $c['last_meeting_days'] >= 14
                        ? 'No meeting for ' . (int) $c['last_meeting_days'] . ' days'
                        : 'Attendance down to ' . (int) $c['avg_attendance'] . '%' ?>
                  </span>
                </span>
                <a class="chip-btn" href="tel:<?= htmlspecialchars(str_replace(' ', '', $c['leader_phone'])) ?>">Contact Leader</a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </div>

<?php endif; ?>
</div>

<?php if ($has_module): ?>

<!-- ═════════════════ CELL DETAIL DRAWER ═════════════════ -->
<div class="drawer-scrim" data-drawer-scrim hidden></div>
<aside class="drawer" id="cellDrawer" role="dialog" aria-modal="true" aria-labelledby="cName" hidden>
  <header class="drawer__head">
    <span class="stat-tile__icon tone-purple" style="width:48px;height:48px;border-radius:14px;font-size:18px" aria-hidden="true"><i class="fa-solid fa-people-group"></i></span>
    <div class="drawer__title">
      <h2 id="cName">Cell Group</h2>
      <p><span class="pill is-brand" data-c-zone>Zone</span> &middot; <span data-c-meet>—</span></p>
    </div>
    <button class="iconbtn" type="button" data-drawer-close aria-label="Close panel"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
  </header>

  <div class="drawer__body">
    <div class="tabs" role="tablist">
      <button role="tab" aria-selected="true"  data-tab="members">Members</button>
      <button role="tab" aria-selected="false" data-tab="meetings">Meetings</button>
      <button role="tab" aria-selected="false" data-tab="attendance">Attendance</button>
      <button role="tab" aria-selected="false" data-tab="notes">Notes</button>
    </div>

    <div class="tabpanel" data-panel="members">
      <dl class="deflist" style="margin-bottom:14px">
        <div><dt>Leader</dt><dd data-c-leader>—</dd></div>
        <div><dt>Assistant leader</dt><dd data-c-asst>—</dd></div>
        <div><dt>Venue</dt><dd data-c-venue>—</dd></div>
        <div><dt>Members</dt><dd data-c-members>—</dd></div>
      </dl>
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
        <p style="flex:1;color:var(--muted);font-size:12.5px;font-weight:600">Cell members</p>
        <?php if (mu_can('members.edit')): ?>
          <button class="btn btn--ghost" type="button" data-open="modalAddToCell"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Add Members</button>
        <?php endif; ?>
      </div>
      <div class="clist">
        <?php foreach (array_slice($members_demo, 0, 8) as $m): ?>
          <div class="crow">
            <?= mu_av($m['name'], 'xs') ?>
            <span class="crow__name"><?= htmlspecialchars($m['name']) ?></span>
            <span class="crow__phone"><?= htmlspecialchars($m['suburb']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="tabpanel" data-panel="meetings" hidden>
      <div class="timeline">
        <?php foreach ($cell_meetings_demo as $mt): ?>
          <div class="tl-item">
            <div class="tl-item__head">
              <span class="tl-item__method"><i class="fa-solid fa-book-open" aria-hidden="true"></i> <?= htmlspecialchars($mt['topic']) ?></span>
              <span class="tl-item__date"><?= mu_date($mt['date']) ?></span>
            </div>
            <p class="tl-item__who"><?= (int) $mt['attendance'] ?> present</p>
            <p class="tl-item__notes"><?= htmlspecialchars($mt['notes']) ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="tabpanel" data-panel="attendance" hidden>
      <div class="chart-wrap" style="height:220px"><canvas id="cellHistChart" role="img" aria-label="Attendance over the last 12 meetings"></canvas></div>
    </div>

    <div class="tabpanel" data-panel="notes" hidden>
      <div class="field">
        <label for="cNotes">Notes</label>
        <textarea class="textarea" id="cNotes" rows="6" placeholder="Prayer requests, pastoral concerns, plans&hellip;"></textarea>
      </div>
      <button class="btn btn--ghost" type="button" style="margin-top:10px" data-toast="Notes saved"><i class="fa-regular fa-floppy-disk" aria-hidden="true"></i> Save notes</button>
    </div>
  </div>

  <footer class="drawer__foot">
    <?php if (mu_can('members.edit')): ?>
      <button class="btn btn--ghost" type="button" data-open="modalCell"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</button>
      <button class="btn" type="button" data-open="modalMeeting"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i> Record Meeting</button>
    <?php else: ?>
      <button class="btn" type="button" data-drawer-close>Close</button>
    <?php endif; ?>
  </footer>
</aside>

<?php if (mu_can('members.edit')): ?>
<!-- ═══════════════════ ADD / EDIT CELL GROUP ═══════════════════ -->
<div class="modal-scrim" id="modalCell" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="cgTitle">
    <header class="modal__head">
      <h2 id="cgTitle">Add Cell Group</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>
    <div class="modal__body">
      <div class="form-grid">
        <div class="field col-2"><label for="cgName">Name</label><input class="input" id="cgName" placeholder="e.g. Westgate Cell"></div>
        <div class="field">
          <label for="cgZone">Zone / area</label>
          <select class="select" id="cgZone"><?php foreach ($zones_demo as $z): ?><option><?= htmlspecialchars($z) ?></option><?php endforeach; ?></select>
        </div>
        <div class="field"><label for="cgCap">Capacity</label><input class="input" type="number" id="cgCap" value="25" min="1"></div>
        <div class="field"><label for="cgLeader">Leader</label><input class="input" id="cgLeader" list="memberList" placeholder="Search members&hellip;"></div>
        <div class="field"><label for="cgAsst">Assistant leader</label><input class="input" id="cgAsst" list="memberList" placeholder="Search members&hellip;"></div>
        <div class="field">
          <label for="cgDay">Meeting day</label>
          <select class="select" id="cgDay"><?php foreach (['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $d): ?><option><?= $d ?></option><?php endforeach; ?></select>
        </div>
        <div class="field"><label for="cgTime">Meeting time</label><input class="input" type="time" id="cgTime" value="18:00"></div>
        <div class="field col-2"><label for="cgVenue">Venue address</label><input class="input" id="cgVenue" placeholder="House number and street"></div>
        <div class="field col-2"><label for="cgDesc">Description</label><textarea class="textarea" id="cgDesc" rows="2"></textarea></div>
        <div class="field col-2">
          <label style="display:flex;align-items:center;gap:10px">
            <span class="switch"><input type="checkbox" id="cgActive" checked><span class="switch__track" aria-hidden="true"></span></span>
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
      <button class="btn" type="button" data-close data-toast="Cell group saved">Save Cell Group</button>
    </footer>
  </div>
</div>

<!-- ═══════════════════════ RECORD MEETING ═══════════════════════ -->
<div class="modal-scrim" id="modalMeeting" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="mtTitle">
    <header class="modal__head">
      <h2 id="mtTitle">Record Meeting</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>
    <div class="modal__body">
      <div class="form-grid" style="margin-bottom:16px">
        <div class="field"><label for="mtDate">Meeting date</label><input class="input" type="date" id="mtDate" value="<?= date('Y-m-d') ?>"></div>
        <div class="field"><label for="mtTopic">Topic / scripture</label><input class="input" id="mtTopic" placeholder="e.g. Romans 12"></div>
      </div>

      <p class="modal__group">Attendance</p>
      <div style="max-height:250px;overflow-y:auto;margin-top:8px">
        <?php foreach (array_slice($members_demo, 0, 10) as $i => $m): ?>
          <div class="att-row" data-att-row>
            <?= mu_av($m['name'], 'xs') ?>
            <span class="att-row__name"><?= htmlspecialchars($m['name']) ?></span>
            <span class="att-seg" role="group" aria-label="Attendance for <?= htmlspecialchars($m['name']) ?>">
              <button class="is-p" type="button" data-att="present" aria-pressed="<?= $i < 7 ? 'true' : 'false' ?>">P</button>
              <button class="is-a" type="button" data-att="absent"  aria-pressed="<?= $i >= 7 ? 'true' : 'false' ?>">A</button>
              <button class="is-e" type="button" data-att="excused" aria-pressed="false">E</button>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
      <p class="hint" style="margin-top:10px;font-weight:700;color:var(--ink-2)"><span data-present-count">0</span> present</p>

      <div class="form-grid" style="margin-top:16px">
        <div class="field"><label for="mtVisitors">Visitors</label><input class="input" type="number" id="mtVisitors" value="0" min="0"></div>
        <?php if (mu_mod('finance')): ?>
          <div class="field"><label for="mtOffering">Offering (USD)</label><input class="input" type="number" id="mtOffering" value="0" min="0" step="0.01"></div>
        <?php endif; ?>
        <div class="field col-2"><label for="mtNotes">Meeting notes</label><textarea class="textarea" id="mtNotes" rows="3"></textarea></div>
        <div class="field col-2"><label for="mtPrayer">Prayer requests</label><textarea class="textarea" id="mtPrayer" rows="3"></textarea></div>
      </div>
    </div>
    <footer class="modal__foot">
      <span class="modal__spacer"></span>
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <button class="btn" type="button" data-close data-toast="Meeting recorded">Save Meeting</button>
    </footer>
  </div>
</div>

<!-- ═══════════════════ ADD MEMBERS TO CELL ═══════════════════ -->
<div class="modal-scrim" id="modalAddToCell" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="acTitle">
    <header class="modal__head">
      <h2 id="acTitle">Add Members to Cell</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>
    <div class="modal__body">
      <div class="search-field" style="margin-bottom:10px">
        <i class="fa-solid fa-magnifying-glass search-field__icon" aria-hidden="true"></i>
        <input class="input" type="search" id="acSearch" placeholder="Search members&hellip;">
      </div>
      <div class="field" style="margin-bottom:12px">
        <label for="acSuburb">Suggest members near</label>
        <select class="select" id="acSuburb">
          <option>All suburbs</option>
          <?php foreach ($suburbs_demo as $s): ?><option><?= htmlspecialchars($s) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="clist" style="max-height:260px;overflow-y:auto">
        <?php foreach ($members_demo as $m): ?>
          <label class="crow" style="cursor:pointer" data-ac-row
                 data-name="<?= htmlspecialchars(mb_strtolower($m['name'])) ?>"
                 data-suburb="<?= htmlspecialchars($m['suburb']) ?>">
            <input class="check" type="checkbox" data-ac-check>
            <?= mu_av($m['name'], 'xs') ?>
            <span class="crow__name"><?= htmlspecialchars($m['name']) ?></span>
            <span class="crow__phone"><?= htmlspecialchars($m['suburb']) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
    <footer class="modal__foot">
      <span style="color:var(--muted);font-size:12.5px;font-weight:700"><span data-ac-count>0</span> selected</span>
      <span class="modal__spacer"></span>
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <button class="btn" type="button" data-close data-toast="Members added to cell">Add Selected</button>
    </footer>
  </div>
</div>
<?php endif; ?>

<?php if (mu_mod('communication')): ?>
<div class="modal-scrim" id="modalMsgGroup" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="mgTitle">
    <header class="modal__head">
      <h2 id="mgTitle">Message Group</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>
    <div class="modal__body">
      <p class="modal__hint">Sending to <strong data-mg-count>18 members</strong> of this cell group.</p>
      <div class="field" style="margin-bottom:14px">
        <label>Channel</label>
        <div class="seg">
          <input type="checkbox" id="mgEmail" checked><label for="mgEmail"><i class="fa-regular fa-envelope" aria-hidden="true"></i> Email</label>
          <input type="checkbox" id="mgSms" checked><label for="mgSms"><i class="fa-solid fa-comment-sms" aria-hidden="true"></i> SMS</label>
        </div>
      </div>
      <div class="field" style="margin-bottom:14px">
        <label for="mgTemplate">Template</label>
        <select class="select" id="mgTemplate"><option>No template</option><option>Meeting reminder</option><option>Venue change</option><option>Prayer request</option></select>
      </div>
      <div class="field">
        <label for="mgBody">Message</label>
        <textarea class="textarea" id="mgBody" rows="5" maxlength="480" placeholder="Type your message&hellip;"></textarea>
        <p class="hint"><span data-mg-chars>0</span> / 480 characters</p>
      </div>
    </div>
    <footer class="modal__foot">
      <span class="modal__spacer"></span>
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <button class="btn" type="button" data-close data-toast="Message queued for sending">Send Message</button>
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
  <p class="demo__hint">Cell Group Leader sees only their own cell</p>
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
  var CELLS = <?= json_encode(array_column($rows, null, 'id'), JSON_UNESCAPED_UNICODE) ?>;
  var ALL   = <?= json_encode($all_cells, JSON_UNESCAPED_UNICODE) ?>;

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

  /* counters run on every variant of the page */
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

  /* modals are shared by both the directory and the leader view */
  document.addEventListener('click', function (e) {
    var open = e.target.closest('[data-open]');
    if (open) {
      e.preventDefault();
      var m = document.getElementById(open.getAttribute('data-open'));
      if (m) { m.hidden = false; document.body.style.overflow = 'hidden'; }
      return;
    }
    var close = e.target.closest('[data-close]');
    if (close) { e.preventDefault(); close.closest('.modal-scrim').hidden = true; document.body.style.overflow = ''; return; }
    if (e.target.classList.contains('modal-scrim')) { e.target.hidden = true; document.body.style.overflow = ''; }
  });

  /* Present/Absent/Excused toggles with a running count. */
  function syncPresent() {
    var n = document.querySelectorAll('[data-att="present"][aria-pressed="true"]').length;
    var out = document.querySelector('[data-present-count]');
    if (out) { out.textContent = n; }
  }
  [].forEach.call(document.querySelectorAll('[data-att]'), function (b) {
    b.addEventListener('click', function () {
      [].forEach.call(b.parentElement.querySelectorAll('[data-att]'), function (o) {
        o.setAttribute('aria-pressed', String(o === b));
      });
      syncPresent();
    });
  });
  syncPresent();

  var mg = document.getElementById('mgBody');
  if (mg) {
    mg.addEventListener('input', function () { document.querySelector('[data-mg-chars]').textContent = mg.value.length; });
  }

  /* add-to-cell modal */
  var acSearch = document.getElementById('acSearch');
  if (acSearch) {
    var acFilter = function () {
      var q = acSearch.value.trim().toLowerCase();
      var sub = document.getElementById('acSuburb').value;
      [].forEach.call(document.querySelectorAll('[data-ac-row]'), function (r) {
        var ok = true;
        if (q && (r.getAttribute('data-name') || '').indexOf(q) === -1) { ok = false; }
        if (ok && sub !== 'All suburbs' && r.getAttribute('data-suburb') !== sub) { ok = false; }
        r.hidden = !ok;
      });
    };
    acSearch.addEventListener('input', acFilter);
    document.getElementById('acSuburb').addEventListener('change', acFilter);
    [].forEach.call(document.querySelectorAll('[data-ac-check]'), function (cb) {
      cb.addEventListener('change', function () {
        document.querySelector('[data-ac-count]').textContent = document.querySelectorAll('[data-ac-check]:checked').length;
      });
    });
  }

  /* ── the single-cell leader view stops here ── */
  var myChart = document.getElementById('myCellChart');
  if (myChart && window.Chart) {
    var c0 = ALL.filter(function (c) { return c.name === <?= json_encode($own_cell_name) ?>; })[0];
    if (c0) {
      new Chart(myChart, {
        type: 'line',
        data: { labels: ['M1','M2','M3','M4','M5','M6'],
                datasets: [{ label: 'Present', data: c0.sparkline, borderColor: '#662F97',
                  backgroundColor: 'rgba(102,47,151,.1)', fill: true, tension: .35, pointRadius: 3, borderWidth: 2 }] },
        options: { responsive: true, maintainAspectRatio: false,
          animation: still ? false : { duration: 500 },
          plugins: { legend: { display: false } },
          scales: { x: { grid: { display: false }, border: { display: false } },
                    y: { grid: { color: '#ECE7F3' }, border: { display: false }, beginAtZero: true } } }
      });
    }
  }

  var panel = document.getElementById('listPanel');
  if (!panel) { return; }
  setTimeout(function () { panel.classList.add('is-loaded'); }, still ? 0 : 620);

  /* view switcher */
  var VIEW_KEY = 'mutendi-cells-view';
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

  /* search + zone filter */
  var search = document.querySelector('[data-search]'),
      clearBtn = document.querySelector('[data-search-clear]'),
      resultCount = document.querySelector('[data-result-count]'),
      emptyState = document.querySelector('[data-empty]'),
      zoneSel = document.getElementById('fZone');

  function apply() {
    var q = search && search.value.trim() ? search.value.trim().toLowerCase() : '';
    var z = zoneSel ? zoneSel.value : 'All zones';
    var shown = 0;
    [].forEach.call(document.querySelectorAll('[data-row], [data-card], [data-map-row], .mappin'), function (el) {
      var ok = true;
      if (q && (el.getAttribute('data-name') || '').indexOf(q) === -1 && el.hasAttribute('data-name')) { ok = false; }
      if (ok && z !== 'All zones' && el.getAttribute('data-zone') && el.getAttribute('data-zone') !== z) { ok = false; }
      el.hidden = !ok;
      if (ok && el.hasAttribute('data-row')) { shown++; }
    });
    resultCount.textContent = shown;
    emptyState.hidden = shown !== 0;
    if (clearBtn) { clearBtn.hidden = !(search && search.value); }
  }
  if (search) { search.addEventListener('input', apply); }
  if (clearBtn) { clearBtn.addEventListener('click', function () { search.value = ''; apply(); search.focus(); }); }
  if (zoneSel) { zoneSel.addEventListener('change', apply); }
  [].forEach.call(document.querySelectorAll('[data-reset-filters]'), function (b) {
    b.addEventListener('click', function () {
      if (search) { search.value = ''; }
      if (zoneSel) { zoneSel.value = 'All zones'; }
      apply();
    });
  });
  [].forEach.call(document.querySelectorAll('[data-zone-filter]'), function (b) {
    b.addEventListener('click', function () {
      if (zoneSel) { zoneSel.value = b.getAttribute('data-zone-filter'); }
      apply();
      toast('Showing ' + b.getAttribute('data-zone-filter'), 'info');
    });
  });

  [].forEach.call(document.querySelectorAll('[data-card-toggle]'), function (b) {
    b.addEventListener('click', function () { b.closest('.pcard').classList.toggle('is-open'); });
  });

  /* drawer */
  var drawer = document.getElementById('cellDrawer'), dScrim = document.querySelector('[data-drawer-scrim]'), histChart = null;
  function openDrawer(id) {
    var c = CELLS[id];
    if (!c) { return; }
    drawer.querySelector('#cName').textContent = c.name;
    drawer.querySelector('[data-c-zone]').textContent = c.zone;
    drawer.querySelector('[data-c-meet]').textContent = c.day + 's at ' + c.time;
    drawer.querySelector('[data-c-leader]').textContent = c.leader;
    drawer.querySelector('[data-c-asst]').textContent = c.assistant;
    drawer.querySelector('[data-c-venue]').textContent = c.venue + ', ' + c.suburb;
    drawer.querySelector('[data-c-members]').textContent = c.members;

    dScrim.hidden = false; drawer.hidden = false;
    document.body.style.overflow = 'hidden';
    drawer.querySelector('[data-drawer-close]').focus();

    var cv = document.getElementById('cellHistChart');
    if (cv && window.Chart) {
      /* Twelve meetings: the six known points, mirrored to fill the history. */
      var pts = c.sparkline.concat(c.sparkline).slice(0, 12);
      if (histChart) { histChart.destroy(); }
      histChart = new Chart(cv, {
        type: 'line',
        data: { labels: pts.map(function (_, i) { return 'M' + (i + 1); }),
                datasets: [{ label: 'Present', data: pts, borderColor: '#662F97',
                  backgroundColor: 'rgba(102,47,151,.1)', fill: true, tension: .35, pointRadius: 3, borderWidth: 2 }] },
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
    var t = e.target.closest('[data-open-cell]');
    if (t) { e.preventDefault(); openDrawer(parseInt(t.getAttribute('data-open-cell'), 10)); }
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

  /* attendance-by-cell chart — dashboard palette and options */
  var cc = document.getElementById('cellChart');
  if (cc && window.Chart) {
    Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.color = '#6E6880';
    new Chart(cc, {
      type: 'bar',
      data: { labels: ALL.map(function (c) { return c.name.replace(' Cell', ''); }),
              datasets: [{ label: 'Avg attendance %', data: ALL.map(function (c) { return c.avg_attendance; }),
                backgroundColor: '#662F97', borderRadius: 6, maxBarThickness: 18 }] },
      options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false,
        animation: still ? false : { duration: 500 },
        plugins: { legend: { display: false } },
        scales: { x: { grid: { color: '#ECE7F3' }, border: { display: false }, beginAtZero: true, max: 100 },
                  y: { grid: { display: false }, border: { display: false } } } }
    });
  }

  apply();
})();
</script>

<?php require __DIR__ . '/../components/footer.php'; ?>

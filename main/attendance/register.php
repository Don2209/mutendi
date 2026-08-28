<?php
/**
 * Mutendi CMS — Attendance Register.
 *
 * The historical record. Two questions have to be answerable in one glance:
 * "what was attendance on this date?" and "how often does this person come?"
 * Those are different shapes of the same data, so the page carries three
 * views over one dataset rather than three pages.
 *
 *   By Service    one row per register taken
 *   By Member     one row per person, their behaviour over the period
 *   Calendar      the month, tinted by how well each day went
 *
 * Reading needs attendance.view; amending a past register needs
 * attendance.edit, which is deliberately narrower than attendance.add — an
 * usher may take a register but not rewrite one.
 *
 * UI only. No register is read from or written to anywhere.
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

/* ------------------------------------------------------------- helpers -- */
/* Guarded so this page can carry its own copy without colliding with the
   People pages or record.php — they are never loaded together. */
if (!function_exists('mu_can')) {

    function mu_can(string $perm): bool { global $permissions; return in_array($perm, $permissions, true); }
    function mu_mod(string $mod): bool  { global $enabled_modules; return in_array($mod, $enabled_modules, true); }

    function mu_initials(string $name): string {
        $p = preg_split('/\s+/', trim($name)) ?: [];
        $a = mb_substr($p[0] ?? '', 0, 1);
        $b = count($p) > 1 ? mb_substr((string) end($p), 0, 1) : '';
        return mb_strtoupper($a . $b);
    }

    /* The same name always resolves to the same colour, so a person looks
       identical everywhere in the system without storing a colour. */
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
        if ($days < 31) { $w = (int) floor($days / 7);  return $w . ' week'  . ($w > 1 ? 's' : '') . ' ago'; }
        $m = (int) floor($days / 30);
        return $m . ' month' . ($m > 1 ? 's' : '') . ' ago';
    }

    function mu_date(string $iso, string $fmt = 'd M Y'): string { return date($fmt, strtotime($iso)); }
}

/* One rule for what a rate means, used by the bars, the bands and the
   calendar tint so they can never disagree. */
if (!function_exists('reg_band')) {
    function reg_band(float $rate): string {
        if ($rate >= 75) { return 'good'; }
        if ($rate >= 40) { return 'fair'; }
        return 'poor';
    }

    /** The small inline bar that follows every percentage on this page. */
    function reg_bar(float $rate, bool $tone = true): string {
        $w = max(0, min(100, $rate));
        $cls = 'rbar' . ($tone ? ' is-' . reg_band($rate) : '');
        return '<span class="' . $cls . '"><span class="rbar__fill" style="width:' . round($w, 1) . '%"></span></span>';
    }
}

$has_module = mu_mod('attendance');
$can_view   = mu_can('attendance.view');
$can_edit   = mu_can('attendance.edit');
$can_add    = mu_can('attendance.add');

/* ─────────────────────────── BRANCH AWARENESS ───────────────────────────
   Which branch's history is in view. Entirely inert for a single church:
   is_multi_branch() is false, so no column, chip or filter is rendered.
   ──────────────────────────────────────────────────────────────────────── */
require_once __DIR__ . '/../components/branch-switcher.php';

$branch_aware   = is_multi_branch();
$viewing_all    = !$branch_aware || $current_branch === 'all' || $current_branch === null;
$show_branch    = $branch_aware && $viewing_all;      /* column, chip and filter */
$branch_options = $branch_aware ? get_visible_branches() : [];

if (!function_exists('mu_branch_for')) {
    /**
     * Which branch a demo record belongs to. Deterministic from the record's
     * own key, so a service or a person never hops between branches on reload.
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
}

/* ══════════════════════════ VIEW A — BY SERVICE ══════════════════════════
   The register rows, resolved from the demo shape into what the table needs.
   ────────────────────────────────────────────────────────────────────── */
$svc_by_id = array_column($service_types_demo, null, 'id');
$services  = [];

if (!function_exists('reg_date')) {
    /**
     * The date a demo row falls on: N weeks before the most recent occurrence
     * of that service's own weekday. Keeps a "Sunday Service" on a Sunday
     * whatever day the demo happens to be opened.
     * LATER: the row carries its own service_date and this disappears.
     */
    function reg_date(string $service, int $weeks_ago): string
    {
        global $svc_by_id;
        $dow  = $svc_by_id[$service]['dow'] ?? 'Sunday';
        $last = (date('l') === $dow) ? strtotime('today') : strtotime('last ' . $dow);
        return date('Y-m-d', strtotime('-' . $weeks_ago . ' weeks', $last));
    }
}

if ($has_module && $can_view) {
    foreach ($attendance_register_demo as $r) {
        $date  = reg_date($r['service'], (int) $r['weeks_ago']);
        $total = (int) $r['present'] + (int) $r['absent'] + (int) $r['excused'];
        $rate  = $r['expected'] > 0 ? ((int) $r['present'] / (int) $r['expected']) * 100 : 0;

        $services[] = $r + [
            'date'    => $date,
            'name'    => $svc_by_id[$r['service']]['name'] ?? 'Service',
            'icon'    => $svc_by_id[$r['service']]['icon'] ?? 'fa-church',
            'total'   => $total,
            'rate'    => round($rate, 1),
            '_branch' => $branch_aware ? mu_branch_for('svc-' . $r['id']) : null,
        ];
    }
}

if ($branch_aware && !$viewing_all) {
    $services = array_values(array_filter($services, static function ($s) use ($current_branch) {
        return $s['_branch'] && (int) $s['_branch']['id'] === (int) $current_branch;
    }));
}

/* Newest first, whatever order the demo rows were written in. */
usort($services, static fn($a, $b) => strcmp($b['date'], $a['date']));

/* ══════════════════════════ VIEW B — BY MEMBER ══════════════════════════ */
$member_rows = [];

if ($has_module && $can_view) {
    foreach ($members_demo as $m) {
        $a = $attendance_by_member_demo[$m['id']] ?? null;
        if (!$a) { continue; }
        $rate = $a['of'] > 0 ? ($a['attended'] / $a['of']) * 100 : 0;

        $member_rows[] = [
            'id'         => (int) $m['id'],
            'name'       => $m['name'],
            'member_no'  => $m['member_no'],
            'phone'      => $m['phone'],
            'department' => (string) ($m['department'] ?? ''),
            'cell_group' => $m['cell_group'],
            'attended'   => (int) $a['attended'],
            'of'         => (int) $a['of'],
            'rate'       => round($rate, 1),
            'last_days'  => (int) $a['last_days'],
            'streak'     => (int) $a['streak'],
            'trend'      => (int) $a['trend'],
            '_branch'    => $branch_aware ? mu_branch_for($m['member_no']) : null,
        ];
    }
}

if ($branch_aware && !$viewing_all) {
    $member_rows = array_values(array_filter($member_rows, static function ($m) use ($current_branch) {
        return $m['_branch'] && (int) $m['_branch']['id'] === (int) $current_branch;
    }));
}

/* Only the departments actually present on these rows — a filter that can
   only ever return nothing is worse than no filter. */
$roll_departments = array_values(array_unique(array_filter(array_column($member_rows, 'department'))));
sort($roll_departments);

/* ════════════════════════════ BOTTOM ROW CARDS ════════════════════════════ */
$missing = [];
if ($has_module && $can_view) {
    foreach ($attendance_missing_demo as $x) {
        $missing[] = $x + [
            'date' => reg_date($x['service'], (int) $x['weeks_ago']),
            'name' => $svc_by_id[$x['service']]['name'] ?? 'Service',
        ];
    }
}

$at_risk = [];
if ($has_module && $can_view) {
    $by_id = array_column($member_rows, null, 'id');
    foreach ($attendance_at_risk_demo as $x) {
        $m = $by_id[$x['member_id']] ?? null;
        if ($m) { $at_risk[] = $m + ['reason' => $x['reason']]; }
    }
}

/* ─────────────────────────── HEADLINE FIGURES ───────────────────────────
   Scaled to the branch in view, the same way the People pages do it.
   LATER: the figures arrive from a query already scoped to :branch_id. */
$stats = $attendance_stats;
if ($branch_aware && !$viewing_all) {
    $b     = get_branch($current_branch);
    $share = $b ? ((int) $b['members_count'] / max(1, (int) ($organisation['total_members'] ?? 1))) : 1;
    foreach (['services_month', 'average', 'highest'] as $k) {
        $stats[$k] = max(1, (int) round($stats[$k] * ($k === 'services_month' ? 1 : $share)));
    }
}
/* The register behind the "highest" figure, so the date shown is a real one
   and moves with the branch filter instead of being a fixed offset. */
$highest_row = $services
    ? array_reduce($services, static fn($carry, $x) => (!$carry || $x['present'] > $carry['present']) ? $x : $carry)
    : null;
$highest_date     = $highest_row['date'] ?? date('Y-m-d');
$stats['highest'] = $highest_row ? (int) $highest_row['present'] : 0;

$page_title = 'Attendance Register';
require __DIR__ . '/../components/header.php';
?>

<div class="page">

  <!-- ═════════════════════════════ PAGE HEADER ═════════════════════════════ -->
  <header class="page__head">
    <div>
      <nav class="crumbs" aria-label="Breadcrumb">
        <a href="<?= $base_url ?>index.php">Dashboard</a>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span>Attendance</span>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span aria-current="page">Attendance Register</span>
      </nav>
      <div class="title-row">
        <h1 class="page__title">Attendance Register</h1>
        <?php if ($has_module && $can_view): ?>
          <span class="count-chip" data-count="<?= count($services) ?>">0</span>
        <?php endif; ?>
      </div>
      <p class="page__sub">Complete attendance history for your church.</p>
    </div>

    <?php if ($has_module && $can_view): ?>
      <div class="page__actions">
        <?php if ($can_add): ?>
          <a class="btn" href="<?= $base_url ?>attendance/record.php">
            <i class="fa-solid fa-plus" aria-hidden="true"></i> Record Attendance
          </a>
        <?php endif; ?>

        <div class="drop" data-menu>
          <button class="btn btn--ghost" type="button" aria-haspopup="true" aria-expanded="false" data-menu-btn>
            <i class="fa-solid fa-file-export" aria-hidden="true"></i> Export
            <i class="fa-solid fa-chevron-down" style="font-size:10px;opacity:.7" aria-hidden="true"></i>
          </button>
          <div class="menu" data-menu-panel hidden>
            <a class="menu__item" href="#" data-toast="CSV export started"><i class="fa-solid fa-file-csv" aria-hidden="true"></i> Export as CSV</a>
            <a class="menu__item" href="#" data-toast="Excel export started"><i class="fa-solid fa-file-excel" aria-hidden="true"></i> Export as Excel</a>
            <a class="menu__item" href="#" data-toast="PDF export started"><i class="fa-solid fa-file-pdf" aria-hidden="true"></i> Export as PDF</a>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </header>


<?php if (!$has_module): ?>

  <section class="panel">
    <div class="empty">
      <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-plug-circle-xmark"></i></span>
      <h3>The Attendance module is switched off</h3>
      <p>Your church's plan does not include attendance recording. A church administrator can request it from the platform team.</p>
      <a class="btn" href="<?= $base_url ?>index.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Dashboard</a>
    </div>
  </section>

<?php elseif (!$can_view): ?>

  <section class="panel">
    <div class="empty">
      <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-lock"></i></span>
      <h3>You cannot view the attendance register</h3>
      <p>Reading the register needs the <code>attendance.view</code> permission. Ask a church administrator to grant it.</p>
      <a class="btn" href="<?= $base_url ?>index.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Dashboard</a>
    </div>
  </section>

<?php else: ?>

  <!-- ═════════════════════════════ STAT STRIP ═════════════════════════════ -->
  <div class="stat-strip">
    <div class="stat-tile is-static">
      <span class="stat-tile__icon tone-blue" aria-hidden="true"><i class="fa-solid fa-calendar-check"></i></span>
      <span class="stat-tile__body">
        <span class="stat-tile__value" data-count="<?= (int) $stats['services_month'] ?>">0</span>
        <span class="stat-tile__label">Services This Month</span>
      </span>
    </div>

    <div class="stat-tile is-static">
      <span class="stat-tile__icon tone-purple" aria-hidden="true"><i class="fa-solid fa-users"></i></span>
      <span class="stat-tile__body">
        <span class="stat-tile__value" data-count="<?= (int) $stats['average'] ?>">0</span>
        <span class="stat-tile__label">Average Attendance</span>
      </span>
    </div>

    <div class="stat-tile is-static">
      <span class="stat-tile__icon tone-green" aria-hidden="true"><i class="fa-solid fa-arrow-trend-up"></i></span>
      <span class="stat-tile__body">
        <span class="stat-tile__value" data-count="<?= (int) $stats['highest'] ?>">0</span>
        <span class="stat-tile__label">Highest &middot; <?= mu_date($highest_date, 'd M') ?></span>
      </span>
    </div>

    <div class="stat-tile is-static">
      <span class="stat-tile__icon tone-amber" aria-hidden="true"><i class="fa-solid fa-percent"></i></span>
      <span class="stat-tile__body">
        <span class="stat-tile__value"><span data-count="<?= (int) $stats['rate'] ?>">0</span>%</span>
        <span class="stat-tile__label">Attendance Rate</span>
        <?= reg_bar((float) $stats['rate']) ?>
      </span>
    </div>
  </div>


  <!-- ═════════════════════════ TOOLBAR / VIEW SWITCH ═════════════════════════ -->
  <div class="toolbar">
    <div class="regviews" role="group" aria-label="View">
      <button class="regview is-on" type="button" data-regview="service" aria-pressed="true">
        <i class="fa-solid fa-table-list" aria-hidden="true"></i> <span>By Service</span>
      </button>
      <button class="regview" type="button" data-regview="member" aria-pressed="false">
        <i class="fa-solid fa-user-check" aria-hidden="true"></i> <span>By Member</span>
      </button>
      <button class="regview" type="button" data-regview="calendar" aria-pressed="false">
        <i class="fa-regular fa-calendar" aria-hidden="true"></i> <span>Calendar Grid</span>
      </button>
    </div>
    <p style="color:var(--muted);font-size:12.5px;font-weight:600">
      <span data-result-count><?= count($services) ?></span> shown
    </p>
  </div>


  <!-- ═══════════════════════════════ FILTERS ═══════════════════════════════ -->
  <section class="filters" id="filters">
    <button class="filters__toggle" type="button" id="filtersToggle" aria-expanded="false">
      <i class="fa-solid fa-sliders" aria-hidden="true"></i> Filters
      <span class="count-chip" data-active-filters hidden>0</span>
      <span style="flex:1"></span>
      <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
    </button>

    <div class="filters__grid">
      <div class="field field--wide">
        <label for="fSearch">Search</label>
        <div class="search-field">
          <i class="fa-solid fa-magnifying-glass search-field__icon" aria-hidden="true"></i>
          <input class="input" type="search" id="fSearch" data-search
                 placeholder="Service, theme, preacher, member name or number&hellip;">
          <button class="search-field__clear" type="button" data-search-clear hidden aria-label="Clear search">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
          </button>
        </div>
      </div>

      <div class="field field--wide">
        <label>Date range</label>
        <div class="daterange">
          <input class="input" type="date" id="fFrom" data-filter aria-label="From date">
          <span class="daterange__to" aria-hidden="true">to</span>
          <input class="input" type="date" id="fTo" data-filter aria-label="To date">
        </div>
        <div class="chips-row" style="margin-top:8px">
          <button class="rchip" type="button" data-preset="week">This Week</button>
          <button class="rchip" type="button" data-preset="month">This Month</button>
          <button class="rchip" type="button" data-preset="quarter">Last 3 Months</button>
          <button class="rchip" type="button" data-preset="year">This Year</button>
        </div>
      </div>

      <div class="field">
        <label for="fService">Service Type</label>
        <select class="select" id="fService" data-filter>
          <option>All</option>
          <?php foreach ($service_types_demo as $s): ?>
            <option><?= htmlspecialchars($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if ($show_branch): ?>
        <div class="field">
          <label for="fBranch"><?= htmlspecialchars(t('branch_singular')) ?></label>
          <select class="select" id="fBranch" data-filter>
            <option>All</option>
            <?php foreach ($branch_options as $b): ?><option><?= htmlspecialchars($b['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <?php if (mu_mod('departments') && $roll_departments): ?>
        <div class="field" data-member-only hidden>
          <label for="fDept">Department</label>
          <select class="select" id="fDept" data-filter>
            <option>All</option>
            <?php foreach ($roll_departments as $d): ?><option><?= htmlspecialchars($d) ?></option><?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <!-- Only meaningful against member rows, so it hides with that view. -->
      <div class="field" data-member-only hidden>
        <label for="fBand">Attendance Rate</label>
        <select class="select" id="fBand" data-filter>
          <option>All</option>
          <option>Above 75%</option>
          <option>40&ndash;75%</option>
          <option>Below 40%</option>
        </select>
      </div>

      <div class="filters__actions">
        <button class="btn" type="button" data-toast="Filters applied"><i class="fa-solid fa-check" aria-hidden="true"></i> Apply</button>
        <button class="btn btn--ghost" type="button" data-reset-filters><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Reset</button>
      </div>
    </div>

    <div class="chips-row" data-filter-chips hidden></div>
  </section>


  <!-- ═══════════════════════════════ CONTENT ═══════════════════════════════ -->
  <section class="panel" id="listPanel" aria-live="polite">

    <div data-skeleton>
      <?php for ($i = 0; $i < 6; $i++): ?>
        <div class="sk-row">
          <span class="sk sk--av"></span>
          <span><span class="sk sk--text" style="width:42%"></span><span class="sk sk--line" style="width:24%"></span></span>
          <span class="sk sk--pill" style="width:120px"></span>
        </div>
      <?php endfor; ?>
    </div>

    <div data-content>

      <!-- ══════════════════ VIEW A — BY SERVICE ══════════════════ -->
      <div data-pane="service">
        <div class="dt-wrap">
          <table class="dt">
            <thead>
              <tr>
                <th style="width:44px">#</th>
                <th class="is-sortable" data-sort="date">Date <i class="fa-solid fa-sort sort" aria-hidden="true"></i></th>
                <th class="is-sortable" data-sort="name">Service <i class="fa-solid fa-sort sort" aria-hidden="true"></i></th>
                <?php if ($show_branch): ?><th><?= htmlspecialchars(t('branch_singular')) ?></th><?php endif; ?>
                <th class="is-sortable" data-sort="present">Present <i class="fa-solid fa-sort sort" aria-hidden="true"></i></th>
                <th>Absent</th>
                <th>Visitors</th>
                <th>Total</th>
                <th class="is-sortable" data-sort="rate" style="min-width:132px">Rate <i class="fa-solid fa-sort sort" aria-hidden="true"></i></th>
                <?php if (mu_mod('finance')): ?><th>Offering</th><?php endif; ?>
                <th>Recorded By</th>
                <th style="text-align:right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($services as $i => $s): ?>
                <tr data-row data-kind="service" data-id="<?= (int) $s['id'] ?>"
                    data-date="<?= $s['date'] ?>"
                    data-name="<?= htmlspecialchars(mb_strtolower($s['name'])) ?>"
                    data-service="<?= htmlspecialchars($s['name']) ?>"
                    data-preacher="<?= htmlspecialchars(mb_strtolower($s['preacher'])) ?>"
                    data-theme="<?= htmlspecialchars(mb_strtolower($s['theme'])) ?>"
                    data-present="<?= (int) $s['present'] ?>"
                    data-rate="<?= $s['rate'] ?>"
                    <?= $show_branch && $s['_branch'] ? 'data-branch="' . htmlspecialchars($s['_branch']['name']) . '"' : '' ?>>
                  <td class="num"><?= $i + 1 ?></td>
                  <td class="nowrap">
                    <span class="strong"><?= mu_date($s['date'], 'd M Y') ?></span>
                    <span class="tsub"><?= mu_date($s['date'], 'l') ?></span>
                  </td>
                  <td class="svccell">
                    <span class="svcname">
                      <i class="fa-solid <?= htmlspecialchars($s['icon']) ?>" aria-hidden="true"></i>
                      <?= htmlspecialchars($s['name']) ?>
                    </span>
                    <span class="tsub"><?= htmlspecialchars($s['theme']) ?></span>
                  </td>
                  <?php if ($show_branch): ?><td><?= mu_branch_chip($s['_branch']) ?></td><?php endif; ?>
                  <td><span class="bignum"><?= number_format((int) $s['present']) ?></span></td>
                  <td class="num"><?= number_format((int) $s['absent']) ?></td>
                  <td class="num"><?= number_format((int) $s['visitors']) ?></td>
                  <td class="nowrap"><?= number_format((int) $s['total']) ?></td>
                  <td>
                    <span class="ratecell">
                      <b><?= round($s['rate']) ?>%</b>
                      <?= reg_bar((float) $s['rate']) ?>
                    </span>
                  </td>
                  <?php if (mu_mod('finance')): ?>
                    <td class="nowrap"><?= htmlspecialchars($church['currency'] ?? 'USD') ?> <?= number_format((float) $s['offering'], 2) ?></td>
                  <?php endif; ?>
                  <td>
                    <span class="person">
                      <?= mu_av($s['by'], 'sm') ?>
                      <span class="person__text"><span class="person__name"><?= htmlspecialchars($s['by']) ?></span></span>
                    </span>
                  </td>
                  <td>
                    <div class="rowacts">
                      <button class="iconbtn" type="button" data-open-service="<?= (int) $s['id'] ?>" aria-label="View <?= htmlspecialchars($s['name']) ?>">
                        <i class="fa-regular fa-eye" aria-hidden="true"></i>
                      </button>
                      <?php if ($can_edit): ?>
                        <button class="iconbtn" type="button" data-edit-service="<?= (int) $s['id'] ?>" aria-label="Edit <?= htmlspecialchars($s['name']) ?>">
                          <i class="fa-solid fa-pen" aria-hidden="true"></i>
                        </button>
                      <?php endif; ?>
                      <button class="iconbtn" type="button" data-toast="Register exported" aria-label="Export <?= htmlspecialchars($s['name']) ?>">
                        <i class="fa-solid fa-file-export" aria-hidden="true"></i>
                      </button>

                      <div class="drop" data-menu>
                        <button class="iconbtn" type="button" aria-haspopup="true" aria-expanded="false" data-menu-btn aria-label="More actions">
                          <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                        </button>
                        <div class="menu menu--end" data-menu-panel hidden>
                          <button class="menu__item" type="button" data-open-service="<?= (int) $s['id'] ?>"><i class="fa-regular fa-eye" aria-hidden="true"></i> View Details</button>
                          <?php if ($can_edit): ?>
                            <button class="menu__item" type="button" data-edit-service="<?= (int) $s['id'] ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</button>
                          <?php endif; ?>
                          <?php if ($can_add): ?>
                            <button class="menu__item" type="button" data-toast="Register duplicated as a draft"><i class="fa-regular fa-copy" aria-hidden="true"></i> Duplicate</button>
                          <?php endif; ?>
                          <button class="menu__item" type="button" data-toast="Sent to printer"><i class="fa-solid fa-print" aria-hidden="true"></i> Print Register</button>
                          <?php if ($can_edit): ?>
                            <button class="menu__item is-danger" type="button" data-delete-service="<?= (int) $s['id'] ?>"><i class="fa-regular fa-trash-can" aria-hidden="true"></i> Delete</button>
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

        <!-- Stacked cards below 768px — never a shrunken table. -->
        <div class="dt-cards">
          <?php foreach ($services as $s): ?>
            <article class="pcard" data-card data-kind="service" data-id="<?= (int) $s['id'] ?>"
                     data-date="<?= $s['date'] ?>"
                     data-name="<?= htmlspecialchars(mb_strtolower($s['name'])) ?>"
                     data-service="<?= htmlspecialchars($s['name']) ?>"
                     data-preacher="<?= htmlspecialchars(mb_strtolower($s['preacher'])) ?>"
                     data-theme="<?= htmlspecialchars(mb_strtolower($s['theme'])) ?>"
                     data-present="<?= (int) $s['present'] ?>"
                     data-rate="<?= $s['rate'] ?>"
                     <?= $show_branch && $s['_branch'] ? 'data-branch="' . htmlspecialchars($s['_branch']['name']) . '"' : '' ?>>
              <button class="pcard__main" type="button" data-card-toggle aria-expanded="false">
                <span class="stat-tile__icon tone-purple" aria-hidden="true"><i class="fa-solid <?= htmlspecialchars($s['icon']) ?>"></i></span>
                <span class="pcard__text">
                  <span class="pcard__name"><?= htmlspecialchars($s['name']) ?></span>
                  <span class="pcard__meta"><?= mu_date($s['date'], 'D, d M Y') ?> &middot; <?= number_format((int) $s['present']) ?> present</span>
                </span>
                <i class="fa-solid fa-chevron-down pcard__chev" aria-hidden="true"></i>
              </button>
              <div class="pcard__more">
                <?php if ($show_branch && $s['_branch']): ?>
                  <div class="pcard__row"><span><?= htmlspecialchars(t('branch_singular')) ?></span><span><?= mu_branch_chip($s['_branch']) ?></span></div>
                <?php endif; ?>
                <div class="pcard__row"><span>Present</span><span class="strong"><?= number_format((int) $s['present']) ?></span></div>
                <div class="pcard__row"><span>Absent</span><span><?= number_format((int) $s['absent']) ?></span></div>
                <div class="pcard__row"><span>Visitors</span><span><?= number_format((int) $s['visitors']) ?></span></div>
                <div class="pcard__row"><span>Rate</span><span class="ratecell"><b><?= round($s['rate']) ?>%</b><?= reg_bar((float) $s['rate']) ?></span></div>
                <?php if (mu_mod('finance')): ?>
                  <div class="pcard__row"><span>Offering</span><span><?= htmlspecialchars($church['currency'] ?? 'USD') ?> <?= number_format((float) $s['offering'], 2) ?></span></div>
                <?php endif; ?>
                <div class="pcard__row"><span>Recorded by</span><span><?= htmlspecialchars($s['by']) ?></span></div>
                <div class="pcard__acts">
                  <button class="btn btn--ghost btn--sm" type="button" data-open-service="<?= (int) $s['id'] ?>"><i class="fa-regular fa-eye" aria-hidden="true"></i> View</button>
                  <?php if ($can_edit): ?>
                    <button class="btn btn--ghost btn--sm" type="button" data-edit-service="<?= (int) $s['id'] ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</button>
                    <button class="btn btn--ghost btn--sm" type="button" data-delete-service="<?= (int) $s['id'] ?>"><i class="fa-regular fa-trash-can" aria-hidden="true"></i> Delete</button>
                  <?php endif; ?>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>


      <!-- ══════════════════ VIEW B — BY MEMBER ══════════════════ -->
      <div data-pane="member" hidden>
        <div class="dt-wrap">
          <table class="dt">
            <thead>
              <tr>
                <th style="width:44px">#</th>
                <th class="is-sortable" data-sort="name">Member <i class="fa-solid fa-sort sort" aria-hidden="true"></i></th>
                <?php if (mu_mod('departments')): ?><th>Department</th><?php endif; ?>
                <th class="is-sortable" data-sort="attended">Services Attended <i class="fa-solid fa-sort sort" aria-hidden="true"></i></th>
                <th class="is-sortable" data-sort="rate" style="min-width:150px">Attendance Rate <i class="fa-solid fa-sort sort" aria-hidden="true"></i></th>
                <th class="is-sortable" data-sort="last">Last Attended <i class="fa-solid fa-sort sort" aria-hidden="true"></i></th>
                <th>Streak</th>
                <th>Trend</th>
                <th style="text-align:right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($member_rows as $i => $m): ?>
                <?php $stale = $m['last_days'] > 30; ?>
                <tr data-row data-kind="member" data-id="<?= $m['id'] ?>"
                    data-name="<?= htmlspecialchars(mb_strtolower($m['name'])) ?>"
                    data-no="<?= htmlspecialchars(mb_strtolower($m['member_no'])) ?>"
                    data-dept="<?= htmlspecialchars($m['department']) ?>"
                    data-attended="<?= $m['attended'] ?>"
                    data-rate="<?= $m['rate'] ?>"
                    data-last="<?= $m['last_days'] ?>"
                    data-band="<?= reg_band((float) $m['rate']) ?>"
                    <?= $show_branch && $m['_branch'] ? 'data-branch="' . htmlspecialchars($m['_branch']['name']) . '"' : '' ?>>
                  <td class="num"><?= $i + 1 ?></td>
                  <td>
                    <span class="person">
                      <?= mu_av($m['name'], 'sm') ?>
                      <span class="person__text">
                        <span class="person__name"><?= htmlspecialchars($m['name']) ?></span>
                        <span class="tsub"><?= htmlspecialchars($m['member_no']) ?></span>
                      </span>
                    </span>
                  </td>
                  <?php if (mu_mod('departments')): ?>
                    <td>
                      <?php if ($m['department'] !== ''): ?>
                        <span class="dchip"><?= htmlspecialchars($m['department']) ?></span>
                      <?php else: ?>
                        <span class="num">&mdash;</span>
                      <?php endif; ?>
                    </td>
                  <?php endif; ?>
                  <td class="nowrap"><span class="strong"><?= $m['attended'] ?></span> of <?= $m['of'] ?></td>
                  <td>
                    <span class="ratecell">
                      <b class="is-<?= reg_band((float) $m['rate']) ?>"><?= round($m['rate']) ?>%</b>
                      <?= reg_bar((float) $m['rate']) ?>
                    </span>
                  </td>
                  <td class="nowrap<?= $stale ? ' is-stale' : '' ?>"><?= mu_ago($m['last_days']) ?></td>
                  <td class="nowrap">
                    <?php if ($m['streak'] > 4): ?>
                      <span class="streak is-hot"><i class="fa-solid fa-fire" aria-hidden="true"></i> <?= $m['streak'] ?></span>
                    <?php elseif ($m['streak'] > 0): ?>
                      <span class="streak"><?= $m['streak'] ?></span>
                    <?php else: ?>
                      <span class="num">&mdash;</span>
                    <?php endif; ?>
                  </td>
                  <td class="nowrap">
                    <?php if ($m['trend'] > 0): ?>
                      <span class="trend is-up"><i class="fa-solid fa-caret-up" aria-hidden="true"></i> <?= $m['trend'] ?></span>
                    <?php elseif ($m['trend'] < 0): ?>
                      <span class="trend is-down"><i class="fa-solid fa-caret-down" aria-hidden="true"></i> <?= abs($m['trend']) ?></span>
                    <?php else: ?>
                      <span class="num">&mdash;</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="rowacts">
                      <button class="iconbtn" type="button" data-open-member="<?= $m['id'] ?>" aria-label="Attendance history for <?= htmlspecialchars($m['name']) ?>">
                        <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                      </button>
                      <?php if (mu_mod('communication')): ?>
                        <button class="iconbtn" type="button" data-toast="Message composer opened" aria-label="Message <?= htmlspecialchars($m['name']) ?>">
                          <i class="fa-regular fa-comment" aria-hidden="true"></i>
                        </button>
                      <?php endif; ?>
                      <a class="iconbtn" href="tel:<?= htmlspecialchars(preg_replace('/\s+/', '', $m['phone'])) ?>" aria-label="Call <?= htmlspecialchars($m['name']) ?>">
                        <i class="fa-solid fa-phone" aria-hidden="true"></i>
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="dt-cards">
          <?php foreach ($member_rows as $m): ?>
            <?php $stale = $m['last_days'] > 30; ?>
            <article class="pcard" data-card data-kind="member" data-id="<?= $m['id'] ?>"
                     data-name="<?= htmlspecialchars(mb_strtolower($m['name'])) ?>"
                     data-no="<?= htmlspecialchars(mb_strtolower($m['member_no'])) ?>"
                     data-dept="<?= htmlspecialchars($m['department']) ?>"
                     data-attended="<?= $m['attended'] ?>"
                     data-rate="<?= $m['rate'] ?>"
                     data-last="<?= $m['last_days'] ?>"
                     data-band="<?= reg_band((float) $m['rate']) ?>"
                     <?= $show_branch && $m['_branch'] ? 'data-branch="' . htmlspecialchars($m['_branch']['name']) . '"' : '' ?>>
              <button class="pcard__main" type="button" data-card-toggle aria-expanded="false">
                <?= mu_av($m['name'], 'md') ?>
                <span class="pcard__text">
                  <span class="pcard__name"><?= htmlspecialchars($m['name']) ?></span>
                  <span class="pcard__meta"><?= $m['attended'] ?> of <?= $m['of'] ?> &middot; <?= round($m['rate']) ?>%</span>
                </span>
                <i class="fa-solid fa-chevron-down pcard__chev" aria-hidden="true"></i>
              </button>
              <div class="pcard__more">
                <div class="pcard__row"><span>Rate</span><span class="ratecell"><b class="is-<?= reg_band((float) $m['rate']) ?>"><?= round($m['rate']) ?>%</b><?= reg_bar((float) $m['rate']) ?></span></div>
                <div class="pcard__row"><span>Last attended</span><span<?= $stale ? ' class="is-stale"' : '' ?>><?= mu_ago($m['last_days']) ?></span></div>
                <div class="pcard__row"><span>Streak</span><span><?= $m['streak'] > 0 ? $m['streak'] . ' services' : '—' ?></span></div>
                <div class="pcard__row"><span>Trend</span><span><?= $m['trend'] > 0 ? '▲ ' . $m['trend'] : ($m['trend'] < 0 ? '▼ ' . abs($m['trend']) : '—') ?></span></div>
                <?php if (mu_mod('departments')): ?>
                  <div class="pcard__row"><span>Department</span><span><?= $m['department'] !== '' ? htmlspecialchars($m['department']) : '—' ?></span></div>
                <?php endif; ?>
                <div class="pcard__acts">
                  <button class="btn btn--ghost btn--sm" type="button" data-open-member="<?= $m['id'] ?>"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> History</button>
                  <?php if (mu_mod('communication')): ?>
                    <button class="btn btn--ghost btn--sm" type="button" data-toast="Message composer opened"><i class="fa-regular fa-comment" aria-hidden="true"></i> Message</button>
                  <?php endif; ?>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>


      <!-- ══════════════════ VIEW C — CALENDAR GRID ══════════════════ -->
      <div data-pane="calendar" hidden>
        <div class="regcal">
          <div class="regcal__bar">
            <button class="iconbtn" type="button" id="calPrev" aria-label="Previous month">
              <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
            </button>
            <h2 class="regcal__month" id="calMonth" aria-live="polite">&nbsp;</h2>
            <button class="iconbtn" type="button" id="calNext" aria-label="Next month">
              <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
            </button>
            <button class="btn btn--ghost btn--sm" type="button" id="calToday">Today</button>
            <span style="flex:1"></span>
            <ul class="regcal__key">
              <li><span class="regcal__swatch is-good" aria-hidden="true"></span> Above 75%</li>
              <li><span class="regcal__swatch is-fair" aria-hidden="true"></span> 40&ndash;75%</li>
              <li><span class="regcal__swatch is-poor" aria-hidden="true"></span> Below 40%</li>
              <li><span class="regcal__swatch is-miss" aria-hidden="true"></span> Not recorded</li>
            </ul>
          </div>

          <div class="regcal__dow" aria-hidden="true">
            <span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span>
          </div>
          <div class="regcal__grid" id="calGrid" role="grid" aria-labelledby="calMonth"></div>
        </div>
      </div>

      <!-- Shared by the two table views. -->
      <div class="empty" id="listEmpty" hidden>
        <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-filter-circle-xmark"></i></span>
        <h3>Nothing matches those filters</h3>
        <p>Try a wider date range, or clear the filters to see the whole register again.</p>
        <button class="btn btn--ghost" type="button" data-reset-filters>
          <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Reset filters
        </button>
      </div>
    </div>
  </section>


  <!-- ═════════════════════════════ BOTTOM ROW ═════════════════════════════ -->
  <div class="regbottom">

    <section class="panel panel--pad">
      <div class="panel__head">
        <h2>Missing Records</h2>
        <span class="pill tone-warn"><?= count($missing) ?></span>
      </div>
      <?php if (!$missing): ?>
        <div class="card__empty">
          <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
          <p>Every expected service has a register.</p>
        </div>
      <?php else: ?>
        <ul class="misslist">
          <?php foreach ($missing as $x): ?>
            <li class="missrow">
              <span class="missrow__icon" aria-hidden="true"><i class="fa-solid fa-calendar-xmark"></i></span>
              <span class="missrow__text">
                <span class="missrow__name"><?= htmlspecialchars($x['name']) ?></span>
                <span class="missrow__meta"><?= mu_date($x['date'], 'D, d M Y') ?> &middot; <?= htmlspecialchars($x['note']) ?></span>
              </span>
              <?php if ($can_add): ?>
                <a class="btn btn--sm" href="<?= $base_url ?>attendance/record.php">Record Now</a>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="panel panel--pad">
      <div class="panel__head">
        <h2>At-Risk Members</h2>
        <span class="pill tone-danger"><?= count($at_risk) ?></span>
      </div>
      <?php if (!$at_risk): ?>
        <div class="card__empty">
          <i class="fa-solid fa-heart" aria-hidden="true"></i>
          <p>Nobody has dropped off. </p>
        </div>
      <?php else: ?>
        <ul class="misslist">
          <?php foreach ($at_risk as $m): ?>
            <li class="missrow">
              <?= mu_av($m['name'], 'sm') ?>
              <span class="missrow__text">
                <span class="missrow__name"><?= htmlspecialchars($m['name']) ?></span>
                <span class="missrow__meta"><?= htmlspecialchars($m['reason']) ?> &middot; <?= round($m['rate']) ?>% rate</span>
              </span>
              <?php if (mu_mod('communication')): ?>
                <button class="btn btn--sm" type="button" data-toast="Follow-up queued for <?= htmlspecialchars($m['name']) ?>">Follow Up</button>
              <?php else: ?>
                <button class="btn btn--ghost btn--sm" type="button" data-open-member="<?= $m['id'] ?>">History</button>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>

<?php endif; ?>

</div><!-- /.page -->


<?php if ($has_module && $can_view): ?>

<!-- ══════════════════════ SERVICE DETAIL DRAWER ══════════════════════ -->
<div class="drawer-scrim" data-drawer-scrim hidden></div>

<aside class="drawer" id="svcDrawer" role="dialog" aria-modal="true" aria-labelledby="svcName" hidden>
  <header class="drawer__head">
    <span class="stat-tile__icon tone-purple" style="width:48px;height:48px;border-radius:14px;font-size:18px" aria-hidden="true">
      <i class="fa-solid fa-church" data-svc-icon></i>
    </span>
    <div class="drawer__title">
      <h2 id="svcName">Service</h2>
      <p><span data-svc-date>—</span> &middot; <span data-svc-time>—</span></p>
    </div>
    <button class="iconbtn" type="button" data-drawer-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
  </header>

  <div class="drawer__body">
    <div class="svcsum">
      <canvas id="svcChart" width="132" height="132" aria-hidden="true"></canvas>
      <ul class="svclegend">
        <li><span class="svclegend__dot" style="background:var(--ok)" aria-hidden="true"></span> Present <b data-svc-present>0</b></li>
        <li><span class="svclegend__dot" style="background:var(--faint)" aria-hidden="true"></span> Absent <b data-svc-absent>0</b></li>
        <li><span class="svclegend__dot" style="background:var(--warn)" aria-hidden="true"></span> Excused <b data-svc-excused>0</b></li>
        <li><span class="svclegend__dot" style="background:var(--info)" aria-hidden="true"></span> Visitors <b data-svc-visitors>0</b></li>
      </ul>
    </div>

    <dl class="deflist" style="margin-bottom:16px">
      <div><dt>Preacher</dt><dd data-svc-preacher>—</dd></div>
      <div><dt>Theme</dt><dd data-svc-theme>—</dd></div>
      <div><dt>Attendance rate</dt><dd data-svc-rate>—</dd></div>
      <?php if (mu_mod('finance')): ?>
        <div><dt>Offering</dt><dd data-svc-offering>—</dd></div>
      <?php endif; ?>
      <div><dt>Weather</dt><dd data-svc-weather>—</dd></div>
      <div><dt>Recorded by</dt><dd data-svc-by>—</dd></div>
    </dl>

    <div class="tabs" role="tablist" aria-label="Members in this register">
      <button type="button" role="tab" aria-selected="true"  data-svctab="present">Present <span data-tabn="present">0</span></button>
      <button type="button" role="tab" aria-selected="false" data-svctab="absent">Absent <span data-tabn="absent">0</span></button>
      <button type="button" role="tab" aria-selected="false" data-svctab="excused">Excused <span data-tabn="excused">0</span></button>
      <button type="button" role="tab" aria-selected="false" data-svctab="visitors">Visitors <span data-tabn="visitors">0</span></button>
    </div>

    <div class="search-field" style="margin-bottom:12px">
      <i class="fa-solid fa-magnifying-glass search-field__icon" aria-hidden="true"></i>
      <input class="input" type="search" id="svcSearch" placeholder="Search this list&hellip;" autocomplete="off">
    </div>

    <div class="minilist" id="svcList" role="tabpanel"></div>
  </div>

  <?php if ($can_edit): ?>
    <footer class="drawer__foot">
      <button class="btn" type="button" id="svcEdit">
        <i class="fa-solid fa-pen" aria-hidden="true"></i> Edit Attendance
      </button>
    </footer>
  <?php endif; ?>
</aside>


<!-- ══════════════════════ MEMBER HISTORY DRAWER ══════════════════════ -->
<aside class="drawer" id="memDrawer" role="dialog" aria-modal="true" aria-labelledby="memName" hidden>
  <header class="drawer__head">
    <span class="av av--lg" data-mem-av aria-hidden="true">MM</span>
    <div class="drawer__title">
      <h2 id="memName">Member</h2>
      <p><span data-mem-no>—</span> &middot; <span data-mem-dept>—</span></p>
    </div>
    <button class="iconbtn" type="button" data-drawer-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
  </header>

  <div class="drawer__body">
    <div class="ring-wrap">
      <div class="ring" data-mem-ring style="--pct:0deg">
        <span class="ring__pct" data-mem-pct>0%</span>
      </div>
      <p class="ring__caption" data-mem-caption>0 of 0 services attended</p>
    </div>

    <div class="sparkwrap">
      <p class="sparkwrap__label">Last 12 services</p>
      <div class="spark" data-mem-spark role="img" aria-label="Attendance across the last 12 services"></div>
    </div>

    <p class="minilist__head">Service history</p>
    <div class="minilist" data-mem-history></div>
  </div>

  <footer class="drawer__foot">
    <?php if (mu_mod('communication')): ?>
      <button class="btn btn--ghost" type="button" data-toast="Message composer opened">
        <i class="fa-regular fa-comment" aria-hidden="true"></i> Message
      </button>
    <?php endif; ?>
    <a class="btn" href="<?= $base_url ?>members/all.php">
      <i class="fa-regular fa-user" aria-hidden="true"></i> View Profile
    </a>
  </footer>
</aside>


<?php if ($can_edit): ?>
<!-- ══════════════════════ EDIT ATTENDANCE MODAL ══════════════════════ -->
<div class="modal-scrim" id="modalEdit" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="edTitle">
    <header class="modal__head">
      <h2 id="edTitle">Edit Attendance</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>

    <div class="modal__body">
      <div class="at-notice at-notice--warn" role="note" style="margin-bottom:14px">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        <div class="at-notice__body">
          <strong>You are amending a historical record</strong>
          <span>This register was signed off by <span data-ed-by>—</span>. Every change is logged against your name.</span>
        </div>
      </div>

      <p class="modal__hint" data-ed-service>Service &middot; date</p>

      <div class="minilist" id="edList"></div>

      <div class="field" style="margin-top:16px">
        <label for="edReason">Reason for change <span class="req">*</span></label>
        <textarea class="textarea" id="edReason" rows="3"
                  placeholder="Why is this register being amended?"></textarea>
        <p class="hint">Required. Stored with the amendment so the change can be explained later.</p>
      </div>
    </div>

    <footer class="modal__foot">
      <span class="modal__spacer"></span>
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <button class="btn" type="button" id="edGo" disabled>
        <i class="fa-solid fa-check" aria-hidden="true"></i> Save Changes
      </button>
    </footer>
  </div>
</div>


<!-- ══════════════════════════ DELETE MODAL ══════════════════════════ -->
<div class="modal-scrim" id="modalDelete" hidden>
  <div class="modal modal--sm" role="dialog" aria-modal="true" aria-labelledby="delTitle">
    <header class="modal__head">
      <h2 id="delTitle">Delete this register?</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>

    <div class="modal__body">
      <div class="at-notice at-notice--danger" role="note" style="margin-bottom:14px">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        <div class="at-notice__body">
          <strong>This cannot be undone</strong>
          <span>Deleting <span data-del-service>this register</span> removes every member's attendance mark for that service and changes their attendance rates.</span>
        </div>
      </div>

      <div class="field">
        <label for="delConfirm">Type the service date <strong data-del-date>—</strong> to confirm</label>
        <input class="input" type="text" id="delConfirm" placeholder="YYYY-MM-DD" autocomplete="off">
      </div>
    </div>

    <footer class="modal__foot">
      <span class="modal__spacer"></span>
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <button class="btn btn--danger" type="button" id="delGo" disabled>
        <i class="fa-regular fa-trash-can" aria-hidden="true"></i> Delete Register
      </button>
    </footer>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>

<div class="toasts" id="toasts" aria-live="polite" aria-atomic="false"></div>

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

<?php if ($has_module && $can_view): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
/* Attendance register — view switching, filtering, sorting, the calendar
   grid, both drawers and the amend/delete flows. All client-side. */
(function () {
  'use strict';

  var still    = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var CAN_EDIT = <?= $can_edit ? 'true' : 'false' ?>;
  var CUR      = <?= json_encode($church['currency'] ?? 'USD') ?>;

  var SERVICES = <?= json_encode(array_map(static function ($s) {
        return [
            'id' => (int) $s['id'], 'date' => $s['date'], 'name' => $s['name'], 'icon' => $s['icon'],
            'present' => (int) $s['present'], 'absent' => (int) $s['absent'],
            'excused' => (int) $s['excused'], 'visitors' => (int) $s['visitors'],
            'total' => (int) $s['total'], 'rate' => $s['rate'],
            'offering' => (float) $s['offering'], 'by' => $s['by'],
            'preacher' => $s['preacher'], 'theme' => $s['theme'],
            'start' => $s['start'], 'end' => $s['end'], 'weather' => $s['weather'],
        ];
    }, $services), JSON_UNESCAPED_UNICODE) ?>;

  var MEMBERS = <?= json_encode(array_map(static function ($m) {
        return ['id' => $m['id'], 'name' => $m['name'], 'no' => $m['member_no'],
                'dept' => $m['department'], 'attended' => $m['attended'], 'of' => $m['of'],
                'rate' => $m['rate'], 'last' => $m['last_days'], 'streak' => $m['streak'],
                'trend' => $m['trend']];
    }, $member_rows), JSON_UNESCAPED_UNICODE) ?>;

  var MISSING = <?= json_encode(array_map(static function ($x) {
        return ['date' => $x['date'], 'name' => $x['name']];
    }, $missing), JSON_UNESCAPED_UNICODE) ?>;

  var $  = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };

  /* footer.php closes menus from a document-level click, which the panel's own
     stopPropagation prevents — so a menu whose item did something would stay
     open over the drawer it just opened. Every handler here closes its own. */
  function closeOwnMenu(el) {
    var drop = el.closest('[data-menu]');
    if (!drop) { return; }
    drop.classList.remove('is-open');
    drop.querySelector('[data-menu-btn]').setAttribute('aria-expanded', 'false');
    drop.querySelector('[data-menu-panel]').hidden = true;
  }

  /* ───────────────────────────── toasts ───────────────────────────── */
  var toasts = $('#toasts');
  function toast(msg, kind) {
    kind = kind || 'success';
    var icons = { success: 'fa-circle-check', error: 'fa-circle-exclamation', info: 'fa-circle-info' };
    var el = document.createElement('div');
    el.className = 'toast is-' + kind;
    el.innerHTML = '<i class="fa-solid ' + icons[kind] + ' toast__icon" aria-hidden="true"></i>' +
      '<div class="toast__body"><p class="toast__title"></p></div>' +
      '<button class="toast__close" type="button" aria-label="Dismiss"><i class="fa-solid fa-xmark"></i></button>';
    $('.toast__title', el).textContent = msg;
    toasts.appendChild(el);
    var kill = function () { el.classList.add('is-out'); setTimeout(function () { el.remove(); }, 250); };
    $('.toast__close', el).addEventListener('click', kill);
    setTimeout(kill, 3600);
  }
  /* Capture phase, deliberately. footer.php stops propagation on clicks
     inside any [data-menu-panel], so a bubble-phase delegated handler never
     sees the three-dot menu's items. Capturing runs before that and leaves
     the shared menu behaviour untouched. */
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-toast]');
    if (t) { e.preventDefault(); closeOwnMenu(t); toast(t.getAttribute('data-toast')); }
  }, true);

  /* ──────────────────── skeleton → content swap ──────────────────── */
  var panel = $('#listPanel');
  setTimeout(function () { panel.classList.add('is-loaded'); }, still ? 0 : 600);

  /* ─────────────────────── counts tick up ─────────────────────── */
  $$('[data-count]').forEach(function (el) {
    var target = parseFloat(el.getAttribute('data-count')) || 0;
    if (still) { el.textContent = target.toLocaleString(); return; }
    var start = performance.now();
    (function step(now) {
      var p = Math.min(1, (now - start) / 900);
      el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3))).toLocaleString();
      if (p < 1) { requestAnimationFrame(step); }
    })(start);
  });

  /* ═════════════════════════ view switching ═════════════════════════ */

  var view = 'service';
  var memberOnly = $$('[data-member-only]');

  function setView(next) {
    view = next;
    $$('[data-regview]').forEach(function (b) {
      var on = b.getAttribute('data-regview') === next;
      b.classList.toggle('is-on', on);
      b.setAttribute('aria-pressed', String(on));
    });
    $$('[data-pane]').forEach(function (p) { p.hidden = p.getAttribute('data-pane') !== next; });

    /* The rate band only means something against a person. */
    memberOnly.forEach(function (f) { f.hidden = next !== 'member'; });
    if (next !== 'member') { var b = $('#fBand'); if (b) { b.value = 'All'; } }

    if (next === 'calendar') { renderCal(); }
    apply();
  }

  $$('[data-regview]').forEach(function (b) {
    b.addEventListener('click', function () { setView(b.getAttribute('data-regview')); });
  });

  /* ═════════════════════════ filtering ═════════════════════════ */

  var search    = $('#fSearch'),
      clearBtn  = $('[data-search-clear]'),
      chipsRow  = $('[data-filter-chips]'),
      activeN   = $('[data-active-filters]'),
      resultN   = $('[data-result-count]'),
      emptyState = $('#listEmpty'),
      fFrom = $('#fFrom'), fTo = $('#fTo');

  /* Which controls apply to which view — a Department filter must not silently
     empty the service table, which has no departments. */
  var SCOPE = {
    fService: ['service'], fBranch: ['service', 'member'],
    fDept: ['member'], fBand: ['member']
  };

  function activeFilters() {
    var f = {};
    if (search && search.value.trim()) { f.q = search.value.trim(); }
    $$('[data-filter]').forEach(function (el) {
      var v = (el.value || '').trim();
      if (!v || v === 'All') { return; }
      var scope = SCOPE[el.id];
      if (scope && scope.indexOf(view) === -1) { return; }
      f[el.id] = v;
    });
    return f;
  }

  function inRange(iso) {
    if (fFrom.value && iso < fFrom.value) { return false; }
    if (fTo.value && iso > fTo.value) { return false; }
    return true;
  }

  function matches(el, f) {
    var kind = el.getAttribute('data-kind');

    if (f.fFrom || f.fTo) {
      /* Member rows carry no single date, so a date range simply does not
         constrain them — it constrains the services behind them. */
      if (kind === 'service' && !inRange(el.getAttribute('data-date'))) { return false; }
    }

    if (f.q) {
      var q = f.q.toLowerCase(), hay = [
        el.getAttribute('data-name') || '',
        el.getAttribute('data-no') || '',
        el.getAttribute('data-preacher') || '',
        el.getAttribute('data-theme') || ''
      ].join(' ');
      if (hay.indexOf(q) === -1) { return false; }
    }

    if (f.fService && el.getAttribute('data-service') !== f.fService) { return false; }
    if (f.fBranch  && el.getAttribute('data-branch')  !== f.fBranch)  { return false; }
    if (f.fDept    && el.getAttribute('data-dept')    !== f.fDept)    { return false; }

    if (f.fBand) {
      var band = el.getAttribute('data-band');
      var want = f.fBand === 'Above 75%' ? 'good' : (f.fBand === 'Below 40%' ? 'poor' : 'fair');
      if (band !== want) { return false; }
    }
    return true;
  }

  function apply() {
    var f = activeFilters(), shown = 0;
    var pane = $('[data-pane="' + view + '"]');

    if (view !== 'calendar' && pane) {
      $$('[data-row], [data-card]', pane).forEach(function (el) {
        var ok = matches(el, f);
        el.hidden = !ok;
        if (ok && el.hasAttribute('data-row')) { shown++; }
      });
      emptyState.hidden = shown !== 0;
    } else {
      emptyState.hidden = true;
      shown = view === 'calendar' ? SERVICES.length : 0;
    }

    if (resultN) { resultN.textContent = shown; }

    /* removable chips */
    var keys = Object.keys(f);
    chipsRow.innerHTML = '';
    keys.forEach(function (k) {
      var label = k === 'q' ? 'Search: ' + f[k]
                : k === 'fFrom' ? 'From ' + f[k]
                : k === 'fTo' ? 'To ' + f[k] : f[k];
      var chip = document.createElement('span');
      chip.className = 'fchip';
      chip.innerHTML = '<span></span><button type="button" aria-label="Remove filter"><i class="fa-solid fa-xmark"></i></button>';
      $('span', chip).textContent = label;
      $('button', chip).addEventListener('click', function () {
        if (k === 'q') { search.value = ''; }
        else {
          var el = document.getElementById(k);
          el.value = el.tagName === 'SELECT' ? 'All' : '';
        }
        apply();
      });
      chipsRow.appendChild(chip);
    });
    chipsRow.hidden = keys.length === 0;

    if (activeN) { activeN.textContent = keys.length; activeN.hidden = keys.length === 0; }
    if (clearBtn) { clearBtn.hidden = !(search && search.value); }
  }

  if (search) { search.addEventListener('input', apply); }
  if (clearBtn) {
    clearBtn.addEventListener('click', function () { search.value = ''; apply(); search.focus(); });
  }
  $$('[data-filter]').forEach(function (el) {
    el.addEventListener('change', apply);
    el.addEventListener('input', apply);
  });

  /* date range presets */
  function iso(d) { return d.toISOString().slice(0, 10); }
  $$('[data-preset]').forEach(function (b) {
    b.addEventListener('click', function () {
      var now = new Date(), from = new Date(now);
      switch (b.getAttribute('data-preset')) {
        case 'week':    from.setDate(now.getDate() - ((now.getDay() + 6) % 7)); break;
        case 'month':   from = new Date(now.getFullYear(), now.getMonth(), 1); break;
        case 'quarter': from = new Date(now.getFullYear(), now.getMonth() - 3, now.getDate()); break;
        case 'year':    from = new Date(now.getFullYear(), 0, 1); break;
      }
      fFrom.value = iso(from);
      fTo.value   = iso(now);
      $$('[data-preset]').forEach(function (o) { o.classList.toggle('is-on', o === b); });
      apply();
    });
  });

  $$('[data-reset-filters]').forEach(function (b) {
    b.addEventListener('click', function () {
      if (search) { search.value = ''; }
      $$('[data-filter]').forEach(function (el) {
        el.value = el.tagName === 'SELECT' ? 'All' : '';
      });
      $$('[data-preset]').forEach(function (o) { o.classList.remove('is-on'); });
      apply();
      toast('Filters cleared', 'info');
    });
  });

  /* the mobile filter drawer */
  var fToggle = $('#filtersToggle');
  if (fToggle) {
    fToggle.addEventListener('click', function () {
      var open = $('#filters').classList.toggle('is-open');
      fToggle.setAttribute('aria-expanded', String(open));
    });
  }

  /* ═════════════════════════ sorting ═════════════════════════ */

  $$('.dt th.is-sortable').forEach(function (th) {
    th.addEventListener('click', function () {
      var key = th.getAttribute('data-sort');
      var asc = th.getAttribute('aria-sort') !== 'ascending';
      var table = th.closest('table');
      $$('th', table).forEach(function (o) { o.removeAttribute('aria-sort'); });
      th.setAttribute('aria-sort', asc ? 'ascending' : 'descending');

      var tbody = $('tbody', table);
      var rows  = $$('tr', tbody);
      var numeric = ['present', 'rate', 'attended', 'last'].indexOf(key) !== -1;

      rows.sort(function (a, b) {
        var av = a.getAttribute('data-' + key) || '', bv = b.getAttribute('data-' + key) || '';
        var r = numeric ? (parseFloat(av) - parseFloat(bv)) : String(av).localeCompare(String(bv));
        return asc ? r : -r;
      });
      rows.forEach(function (r) { tbody.appendChild(r); });
      /* Keep the # column meaning "position in this ordering". */
      rows.forEach(function (r, i) { var c = $('.num', r); if (c) { c.textContent = i + 1; } });
      toast('Sorted by ' + key, 'info');
    });
  });

  /* stacked cards expand in place */
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-card-toggle]');
    if (!t) { return; }
    var card = t.closest('.pcard');
    var open = card.classList.toggle('is-open');
    t.setAttribute('aria-expanded', String(open));
  });

  /* ═════════════════════════ CALENDAR GRID ═════════════════════════ */

  var calGrid = $('#calGrid'), calMonth = $('#calMonth');
  var cursor = new Date();
  cursor.setDate(1);

  /* date → what happened that day, built once. */
  var BY_DATE = {};
  SERVICES.forEach(function (s) { (BY_DATE[s.date] = BY_DATE[s.date] || []).push(s); });
  var MISS_BY_DATE = {};
  MISSING.forEach(function (m) { (MISS_BY_DATE[m.date] = MISS_BY_DATE[m.date] || []).push(m); });

  function band(rate) { return rate >= 75 ? 'good' : (rate >= 40 ? 'fair' : 'poor'); }
  function pad(n) { return (n < 10 ? '0' : '') + n; }

  function renderCal() {
    var y = cursor.getFullYear(), m = cursor.getMonth();
    calMonth.textContent = cursor.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });

    var first = new Date(y, m, 1);
    var lead  = (first.getDay() + 6) % 7;              /* weeks start Monday */
    var days  = new Date(y, m + 1, 0).getDate();
    var today = new Date(); today.setHours(0, 0, 0, 0);

    calGrid.innerHTML = '';

    for (var i = 0; i < lead; i++) {
      var blank = document.createElement('div');
      blank.className = 'regcal__day is-blank';
      blank.setAttribute('aria-hidden', 'true');
      calGrid.appendChild(blank);
    }

    for (var d = 1; d <= days; d++) {
      var key  = y + '-' + pad(m + 1) + '-' + pad(d);
      var list = BY_DATE[key] || [];
      var miss = MISS_BY_DATE[key] || [];

      var cell = document.createElement('button');
      cell.type = 'button';
      cell.className = 'regcal__day';
      cell.setAttribute('data-date', key);
      cell.setAttribute('role', 'gridcell');

      var label = d + ' ' + cursor.toLocaleDateString(undefined, { month: 'long' });

      if (list.length) {
        var present = 0, expected = 0;
        list.forEach(function (s) { present += s.present; expected += Math.round(s.present / (s.rate / 100)); });
        var rate = expected ? (present / expected) * 100 : 0;
        cell.classList.add('is-' + band(rate));
        label += ': ' + present + ' present across ' + list.length + ' service' + (list.length > 1 ? 's' : '');
      }
      if (miss.length) { cell.classList.add('has-miss'); label += ', ' + miss.length + ' not recorded'; }
      if (key === (today.getFullYear() + '-' + pad(today.getMonth() + 1) + '-' + pad(today.getDate()))) {
        cell.classList.add('is-today');
      }
      if (!list.length && !miss.length) { cell.classList.add('is-quiet'); }

      cell.setAttribute('aria-label', label);

      var dots = '';
      list.slice(0, 4).forEach(function () { dots += '<span class="regcal__dot"></span>'; });
      miss.slice(0, 2).forEach(function () { dots += '<span class="regcal__dot is-miss"></span>'; });

      var totalPresent = list.reduce(function (a, s) { return a + s.present; }, 0);

      cell.innerHTML =
        '<span class="regcal__num">' + d + '</span>' +
        (dots ? '<span class="regcal__dots">' + dots + '</span>' : '') +
        (totalPresent ? '<span class="regcal__n">' + totalPresent.toLocaleString() + '</span>' : '');

      calGrid.appendChild(cell);
    }
  }

  $('#calPrev').addEventListener('click', function () { cursor.setMonth(cursor.getMonth() - 1); renderCal(); });
  $('#calNext').addEventListener('click', function () { cursor.setMonth(cursor.getMonth() + 1); renderCal(); });
  $('#calToday').addEventListener('click', function () {
    cursor = new Date(); cursor.setDate(1); renderCal(); toast('Back to this month', 'info');
  });

  calGrid.addEventListener('click', function (e) {
    var cell = e.target.closest('.regcal__day');
    if (!cell || cell.classList.contains('is-blank')) { return; }
    openDay(cell.getAttribute('data-date'));
  });

  /* ═════════════════════════ drawers ═════════════════════════ */

  var scrim = $('[data-drawer-scrim]'),
      svcDrawer = $('#svcDrawer'),
      memDrawer = $('#memDrawer'),
      lastFocus = null;

  function openDrawer(d) {
    lastFocus = document.activeElement;
    d.hidden = false; scrim.hidden = false;
    document.body.style.overflow = 'hidden';
    $('[data-drawer-close]', d).focus();
  }
  function closeDrawers() {
    [svcDrawer, memDrawer].forEach(function (d) { if (d) { d.hidden = true; } });
    scrim.hidden = true;
    document.body.style.overflow = '';
    if (lastFocus) { lastFocus.focus(); lastFocus = null; }
  }
  scrim.addEventListener('click', closeDrawers);
  $$('[data-drawer-close]').forEach(function (b) { b.addEventListener('click', closeDrawers); });

  /* ── the service drawer ────────────────────────────────────────── */

  var chart = null, currentSvc = null, svcTab = 'present';

  /* Stand-in names for the tabbed lists. The real page reads the marks
     themselves; here the roll is sliced deterministically so a service always
     shows the same faces. */
  function slice(svc, kind) {
    var out = [], n = MEMBERS.length;
    if (!n) { return out; }
    var take = { present: 8, absent: 4, excused: 2, visitors: 3 }[kind];
    var off  = { present: 0, absent: 8, excused: 12, visitors: 14 }[kind];
    for (var i = 0; i < take; i++) {
      var m = MEMBERS[(svc.id + off + i) % n];
      out.push(kind === 'visitors'
        ? { name: m.name.split(' ')[0] + ' (visitor)', meta: 'Invited by ' + MEMBERS[(svc.id + i) % n].name }
        : { name: m.name, meta: m.no + (m.dept ? ' · ' + m.dept : '') });
    }
    return out;
  }

  function initials(name) {
    var p = name.trim().split(/\s+/);
    return (p[0].charAt(0) + (p.length > 1 ? p[p.length - 1].charAt(0) : '')).toUpperCase();
  }

  /* Mirrors mu_avc() in PHP so a face is the same colour either side. */
  var crcTable = (function () {
    var t = [], c, n, k;
    for (n = 0; n < 256; n++) {
      c = n;
      for (k = 0; k < 8; k++) { c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1); }
      t[n] = c >>> 0;
    }
    return t;
  })();
  function crc32(str) {
    var c = 0xFFFFFFFF;
    for (var i = 0; i < str.length; i++) { c = crcTable[(c ^ str.charCodeAt(i)) & 0xFF] ^ (c >>> 8); }
    return (c ^ 0xFFFFFFFF) >>> 0;
  }
  function avc(name) { return 'av-c' + (crc32(name) % 10); }

  function miniRow(item, badge) {
    var row = document.createElement('div');
    row.className = 'minirow';
    row.setAttribute('data-name', item.name.toLowerCase());
    row.innerHTML =
      '<span class="av av--sm ' + avc(item.name) + '" aria-hidden="true">' + initials(item.name) + '</span>' +
      '<span class="minirow__text"><b></b><span></span></span>' +
      (badge || '');
    $('b', row).textContent = item.name;
    $('.minirow__text span', row).textContent = item.meta;
    return row;
  }

  function paintSvcList() {
    var list = $('#svcList');
    list.innerHTML = '';
    slice(currentSvc, svcTab).forEach(function (item) { list.appendChild(miniRow(item)); });
    filterSvcList();
  }

  function filterSvcList() {
    var q = ($('#svcSearch').value || '').trim().toLowerCase();
    $$('.minirow', $('#svcList')).forEach(function (r) {
      r.hidden = q !== '' && r.getAttribute('data-name').indexOf(q) === -1;
    });
  }
  $('#svcSearch').addEventListener('input', filterSvcList);

  $$('[data-svctab]').forEach(function (t) {
    t.addEventListener('click', function () {
      svcTab = t.getAttribute('data-svctab');
      $$('[data-svctab]').forEach(function (o) { o.setAttribute('aria-selected', String(o === t)); });
      paintSvcList();
    });
  });

  function openService(id) {
    var s = SERVICES.filter(function (x) { return String(x.id) === String(id); })[0];
    if (!s) { return; }
    currentSvc = s;

    $('#svcName').textContent = s.name;
    $('[data-svc-icon]').className = 'fa-solid ' + s.icon;
    $('[data-svc-date]').textContent = new Date(s.date + 'T00:00:00')
      .toLocaleDateString(undefined, { weekday: 'long', day: '2-digit', month: 'short', year: 'numeric' });
    $('[data-svc-time]').textContent = s.start + ' – ' + s.end;
    $('[data-svc-preacher]').textContent = s.preacher;
    $('[data-svc-theme]').textContent = s.theme;
    $('[data-svc-rate]').textContent = Math.round(s.rate) + '%';
    $('[data-svc-weather]').textContent = s.weather;
    $('[data-svc-by]').textContent = s.by;
    var off = $('[data-svc-offering]');
    if (off) {
      off.textContent = CUR + ' ' + s.offering.toLocaleString(undefined, {
        minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    $('[data-svc-present]').textContent  = s.present.toLocaleString();
    $('[data-svc-absent]').textContent   = s.absent.toLocaleString();
    $('[data-svc-excused]').textContent  = s.excused.toLocaleString();
    $('[data-svc-visitors]').textContent = s.visitors.toLocaleString();

    ['present', 'absent', 'excused', 'visitors'].forEach(function (k) {
      $('[data-tabn="' + k + '"]').textContent = s[k];
    });

    svcTab = 'present';
    $$('[data-svctab]').forEach(function (o) {
      o.setAttribute('aria-selected', String(o.getAttribute('data-svctab') === 'present'));
    });
    $('#svcSearch').value = '';
    paintSvcList();

    if (chart) { chart.destroy(); }
    chart = new Chart($('#svcChart'), {
      type: 'doughnut',
      data: {
        labels: ['Present', 'Absent', 'Excused', 'Visitors'],
        datasets: [{
          data: [s.present, s.absent, s.excused, s.visitors],
          backgroundColor: ['#15803D', '#948CA6', '#B45309', '#1D4ED8'],
          borderWidth: 0
        }]
      },
      options: {
        cutout: '64%', responsive: false,
        animation: still ? false : { duration: 600 },
        plugins: { legend: { display: false } }
      }
    });

    openDrawer(svcDrawer);
  }

  /* ── the day drawer reuses the service drawer, one service at a time ── */
  function openDay(key) {
    var list = BY_DATE[key] || [], miss = MISS_BY_DATE[key] || [];
    if (list.length === 1 && !miss.length) { openService(list[0].id); return; }
    if (!list.length && !miss.length) {
      toast('Nothing recorded on ' + key, 'info');
      return;
    }
    /* More than one thing happened: show the day as a pick-list. */
    currentSvc = null;
    $('#svcName').textContent = new Date(key + 'T00:00:00')
      .toLocaleDateString(undefined, { weekday: 'long', day: '2-digit', month: 'long' });
    $('[data-svc-icon]').className = 'fa-regular fa-calendar';
    $('[data-svc-date]').textContent = list.length + ' service' + (list.length === 1 ? '' : 's') + ' recorded';
    $('[data-svc-time]').textContent = miss.length ? miss.length + ' not recorded' : 'all recorded';

    var wrap = $('#svcList');
    wrap.innerHTML = '';
    list.forEach(function (s) {
      var row = document.createElement('button');
      row.type = 'button';
      row.className = 'minirow minirow--btn';
      row.innerHTML =
        '<span class="stat-tile__icon tone-purple" style="width:34px;height:34px;border-radius:10px;font-size:12px" aria-hidden="true"><i class="fa-solid ' + s.icon + '"></i></span>' +
        '<span class="minirow__text"><b></b><span></span></span>' +
        '<span class="ratecell"><b>' + Math.round(s.rate) + '%</b></span>';
      $('b', row).textContent = s.name;
      $('.minirow__text span', row).textContent = s.present + ' present · ' + s.absent + ' absent';
      row.addEventListener('click', function () { openService(s.id); });
      wrap.appendChild(row);
    });
    miss.forEach(function (m) {
      var row = document.createElement('div');
      row.className = 'minirow is-missing';
      row.innerHTML =
        '<span class="missrow__icon" aria-hidden="true"><i class="fa-solid fa-calendar-xmark"></i></span>' +
        '<span class="minirow__text"><b></b><span>No register taken</span></span>';
      $('b', row).textContent = m.name;
      wrap.appendChild(row);
    });

    if (chart) { chart.destroy(); chart = null; }
    openDrawer(svcDrawer);
  }

  /* ── the member history drawer ─────────────────────────────────── */

  function openMember(id) {
    var m = MEMBERS.filter(function (x) { return String(x.id) === String(id); })[0];
    if (!m) { return; }

    $('#memName').textContent = m.name;
    $('[data-mem-no]').textContent = m.no;
    $('[data-mem-dept]').textContent = m.dept || '—';
    var av = $('[data-mem-av]');
    av.className = 'av av--lg ' + avc(m.name);
    av.textContent = initials(m.name);

    var pct = Math.round(m.rate);
    $('[data-mem-ring]').style.setProperty('--pct', (pct * 3.6) + 'deg');
    $('[data-mem-pct]').textContent = pct + '%';
    $('[data-mem-caption]').textContent = m.attended + ' of ' + m.of + ' services attended';

    /* A 12-service sparkline. The count of present bars is derived from the
       member's own rate rather than sampled from it, so the shape can never
       contradict the percentage in the ring directly above it. Which twelve
       slots those are is deterministic from the membership number, so a
       member's pattern is stable across reloads.
       LATER: the member's actual last twelve marks. */
    var spark = $('[data-mem-spark]');
    spark.innerHTML = '';

    var nPresent = Math.round((m.rate / 100) * 12);
    var nExcused = Math.min(12 - nPresent, m.rate >= 100 ? 0 : (m.rate < 40 ? 2 : 1));

    /* Slot order shuffled deterministically, then filled present → excused →
       absent, so the marks are spread rather than blocked at one end. */
    var slots = [];
    for (var i = 0; i < 12; i++) { slots.push(i); }
    slots.sort(function (x, y) { return (crc32(m.no + ':' + x) % 997) - (crc32(m.no + ':' + y) % 997); });

    var marks = new Array(12);
    slots.forEach(function (slot, rank) {
      marks[slot] = rank < nPresent ? 'present'
                  : (rank < nPresent + nExcused ? 'excused' : 'absent');
    });
    marks.forEach(function (k, i) {
      var b = document.createElement('span');
      b.className = 'spark__bar is-' + k;
      b.title = 'Service ' + (12 - i) + ' ago: ' + k;
      spark.appendChild(b);
    });

    /* the chronological list */
    var hist = $('[data-mem-history]');
    hist.innerHTML = '';
    SERVICES.slice(0, 12).forEach(function (s, i) {
      var state = marks[i];
      var row = document.createElement('div');
      row.className = 'minirow';
      row.innerHTML =
        '<span class="statedot is-' + state + '" aria-hidden="true"></span>' +
        '<span class="minirow__text"><b></b><span></span></span>' +
        '<span class="statetag is-' + state + '"></span>';
      $('b', row).textContent = s.name;
      $('.minirow__text span', row).textContent = new Date(s.date + 'T00:00:00')
        .toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' });
      $('.statetag', row).textContent = state.charAt(0).toUpperCase() + state.slice(1);
      hist.appendChild(row);
    });

    openDrawer(memDrawer);
  }

  document.addEventListener('click', function (e) {
    var s = e.target.closest('[data-open-service]');
    if (s) { closeOwnMenu(s); openService(s.getAttribute('data-open-service')); return; }
    var m = e.target.closest('[data-open-member]');
    if (m) { closeOwnMenu(m); openMember(m.getAttribute('data-open-member')); }
  }, true);

  /* ═════════════════════════ amend and delete ═════════════════════════ */

  if (CAN_EDIT) {
    var edModal = $('#modalEdit'), delModal = $('#modalDelete'), editing = null;

    function openModal(mo) {
      mo.hidden = false; document.body.style.overflow = 'hidden';
      $('[data-close]', mo).focus();
    }
    function closeModal(mo) {
      mo.hidden = true;
      /* A drawer may still be open behind the modal. */
      if (svcDrawer.hidden && memDrawer.hidden) { document.body.style.overflow = ''; }
    }

    function openEdit(id) {
      var s = SERVICES.filter(function (x) { return String(x.id) === String(id); })[0];
      if (!s) { return; }
      editing = s;

      $('[data-ed-by]').textContent = s.by;
      $('[data-ed-service]').textContent = s.name + ' · ' + new Date(s.date + 'T00:00:00')
        .toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' });

      var list = $('#edList');
      list.innerHTML = '';
      ['present', 'absent', 'excused'].forEach(function (kind) {
        slice(s, kind).forEach(function (item) {
          var row = miniRow(item);
          var seg = document.createElement('span');
          seg.className = 'edseg';
          ['present', 'absent', 'excused'].forEach(function (k) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'edseg__btn is-' + k;
            b.setAttribute('aria-pressed', String(k === kind));
            b.setAttribute('aria-label', k.charAt(0).toUpperCase() + k.slice(1) + ' — ' + item.name);
            b.innerHTML = '<i class="fa-solid ' +
              (k === 'present' ? 'fa-check' : k === 'absent' ? 'fa-xmark' : 'fa-user-clock') + '"></i>';
            b.addEventListener('click', function () {
              $$('.edseg__btn', seg).forEach(function (o) { o.setAttribute('aria-pressed', String(o === b)); });
            });
            seg.appendChild(b);
          });
          row.appendChild(seg);
          list.appendChild(row);
        });
      });

      $('#edReason').value = '';
      $('#edGo').disabled = true;
      openModal(edModal);
    }

    /* The reason is what makes the amendment auditable, so it gates Save. */
    $('#edReason').addEventListener('input', function () {
      $('#edGo').disabled = this.value.trim().length < 5;
    });
    $('#edGo').addEventListener('click', function () {
      closeModal(edModal);
      toast('Register amended — change logged', 'success');
    });

    function openDelete(id) {
      var s = SERVICES.filter(function (x) { return String(x.id) === String(id); })[0];
      if (!s) { return; }
      $('[data-del-service]').textContent = s.name + ' on ' + s.date;
      $('[data-del-date]').textContent = s.date;
      var inp = $('#delConfirm'), go = $('#delGo');
      inp.value = ''; go.disabled = true;
      inp.oninput = function () { go.disabled = inp.value.trim() !== s.date; };
      openModal(delModal);
    }
    $('#delGo').addEventListener('click', function () {
      closeModal(delModal);
      toast('Register deleted', 'error');
    });

    document.addEventListener('click', function (e) {
      var ed = e.target.closest('[data-edit-service]');
      if (ed) { closeOwnMenu(ed); openEdit(ed.getAttribute('data-edit-service')); return; }
      var dl = e.target.closest('[data-delete-service]');
      if (dl) { closeOwnMenu(dl); openDelete(dl.getAttribute('data-delete-service')); return; }
      var cl = e.target.closest('[data-close]');
      if (cl) { closeModal(cl.closest('.modal-scrim')); return; }
      if (e.target.classList.contains('modal-scrim')) { closeModal(e.target); }
    }, true);

    var svcEdit = $('#svcEdit');
    if (svcEdit) {
      svcEdit.addEventListener('click', function () {
        if (currentSvc) { openEdit(currentSvc.id); }
      });
    }
  }

  /* ────────────────────────── escape key ────────────────────────── */
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') { return; }
    var open = $$('.modal-scrim').filter(function (m) { return !m.hidden; });
    if (open.length) { open.forEach(function (m) { m.hidden = true; }); }
    else if (!svcDrawer.hidden || !memDrawer.hidden) { closeDrawers(); }
    if (svcDrawer.hidden && memDrawer.hidden) { document.body.style.overflow = ''; }
  });

  /* ─────────────────────────────── first paint ─────────────────────────────── */
  renderCal();
  apply();
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/../components/footer.php'; ?>

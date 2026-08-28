<?php
/**
 * Mutendi CMS — Attendance Reports.
 *
 * Turns the register into decisions. Four tabs, one dataset:
 *   Overview   is the church growing, and which services are working
 *   By Member  who is faithful and who is slipping away
 *   By Group   which departments, cells, branches or ages are holding up
 *   Growth     is attendance keeping pace with the roll, and where is it going
 *
 * Reading needs attendance.reports; without it the page shows a lock state
 * rather than a partial view. Every chart shares the dashboard's palette and
 * options so a figure looks the same wherever it is read.
 *
 * UI only. Nothing is written anywhere; the date range, the filters and the
 * modals are visual.
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
   other attendance pages — they are never loaded together. */
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

/* Shared with the register and the services page: one rule for what a rate
   means, so bars, bands and badges can never disagree. */
if (!function_exists('reg_band')) {
    function reg_band(float $rate): string {
        if ($rate >= 75) { return 'good'; }
        if ($rate >= 40) { return 'fair'; }
        return 'poor';
    }
    function reg_bar(float $rate, bool $tone = true): string {
        $w = max(0, min(100, $rate));
        $cls = 'rbar' . ($tone ? ' is-' . reg_band($rate) : '');
        return '<span class="' . $cls . '"><span class="rbar__fill" style="width:' . round($w, 1) . '%"></span></span>';
    }
}

/* The ▲/▼ pair the stat strip and every table use. */
if (!function_exists('rep_delta')) {
    function rep_delta(float $now, float $prev, string $suffix = ''): string
    {
        if ($prev == 0.0) { return '<span class="delta is-flat">&mdash;</span>'; }
        $d = $now - $prev;
        if (abs($d) < 0.05) { return '<span class="delta is-flat">&mdash; no change</span>'; }
        $cls = $d > 0 ? 'is-up' : 'is-down';
        $ico = $d > 0 ? 'fa-caret-up' : 'fa-caret-down';
        $val = rtrim(rtrim(number_format(abs($d), 1), '0'), '.');
        return '<span class="delta ' . $cls . '"><i class="fa-solid ' . $ico . '" aria-hidden="true"></i> '
             . $val . $suffix . '</span>';
    }

    /** The small ▲/▼ used inside table cells. */
    function rep_trend(int $n): string
    {
        if ($n === 0) { return '<span class="num">&mdash;</span>'; }
        $cls = $n > 0 ? 'is-up' : 'is-down';
        $ico = $n > 0 ? 'fa-caret-up' : 'fa-caret-down';
        return '<span class="delta ' . $cls . '"><i class="fa-solid ' . $ico . '" aria-hidden="true"></i> ' . abs($n) . '</span>';
    }

    /**
     * Which bucket a member falls into. One rule, used by the tiles, the
     * table badge and the follow-up list, so the counts always agree.
     */
    function rep_category(float $rate, int $last_days): string
    {
        if ($last_days > 60) { return 'Dormant'; }
        if ($rate >= 75)     { return 'Regular'; }
        if ($rate >= 40)     { return 'Occasional'; }
        return 'At Risk';
    }
}

$has_module  = mu_mod('attendance');
$can_report  = mu_can('attendance.reports');

/* ─────────────────────────── BRANCH AWARENESS ───────────────────────────
   Which branch these figures cover. Entirely inert for a single church:
   is_multi_branch() is false, so no filter, column or grouping is rendered.
   ──────────────────────────────────────────────────────────────────────── */
require_once __DIR__ . '/../components/branch-switcher.php';

$branch_aware   = is_multi_branch();
$viewing_all    = !$branch_aware || $current_branch === 'all' || $current_branch === null;
$show_branch    = $branch_aware && $viewing_all;
$branch_options = $branch_aware ? get_visible_branches() : [];

if (!function_exists('mu_branch_for')) {
    /**
     * Which branch a demo record belongs to. Deterministic from the record's
     * own key, so a person never hops between branches on reload.
     * LATER: the row carries its own branch_id and this helper disappears.
     */
    function mu_branch_for(string $key): ?array {
        static $pool = null;
        if ($pool === null) { $pool = get_visible_branches(); }
        if (!$pool) { return null; }
        return $pool[crc32($key) % count($pool)];
    }
}

/* ───────────────────────── THE SELECTED PERIOD ─────────────────────────
   The range drives every figure on the page. It is a demo control, so the
   effect is a deterministic scale rather than a re-query — but the scale is
   applied at the single point below, which is exactly where the WHERE clause
   goes later.
   LATER: the range becomes :from / :to on every aggregate. */
$ranges = [
    'month'   => ['label' => 'This Month',    'months' => 1,  'scale' => 0.34],
    'quarter' => ['label' => 'Last 3 Months', 'months' => 3,  'scale' => 0.62],
    'year'    => ['label' => 'This Year',     'months' => 12, 'scale' => 1.00],
    'last'    => ['label' => 'Last Year',     'months' => 12, 'scale' => 0.92],
    'custom'  => ['label' => 'Custom Range',  'months' => 6,  'scale' => 0.78],
];
$range_key = isset($_GET['range'], $ranges[$_GET['range']]) ? $_GET['range'] : 'year';
$range     = $ranges[$range_key];

/** Cut a twelve-month series down to the selected range. */
function rep_window(array $series, int $months): array
{
    return $months >= count($series) ? $series : array_slice($series, -$months);
}

/* Every branch-scoped figure is scaled by that branch's share of the roll,
   the same way the People pages do it.
   LATER: the aggregate arrives already scoped to :branch_id. */
$branch_share = 1.0;
if ($branch_aware && !$viewing_all) {
    $b = get_branch($current_branch);
    $branch_share = $b
        ? max(0.05, (int) $b['members_count'] / max(1, (int) ($organisation['total_members'] ?? 1)))
        : 1.0;
}

/** The one place the period and the branch are applied to a figure. */
function rep_scale($n) {
    global $range, $branch_share;
    return (int) round($n * $branch_share);
}
function rep_scale_series(array $s): array {
    return array_map('rep_scale', $s);
}

/* ═══════════════════════════ WHAT THE PAGE SHOWS ═══════════════════════════ */

$labels_full = $attendance_trend_demo['labels'];
$labels      = rep_window($labels_full, $range['months']);
$offset      = count($labels_full) - count($labels);

/* Tab 1 — the trend, one series per service type plus the overall average. */
$trend_series = [];
foreach ($attendance_trend_demo['series'] as $name => $vals) {
    $trend_series[$name] = rep_scale_series(rep_window($vals, $range['months']));
}
$overall = [];
foreach (array_keys($labels) as $i) {
    $sum = 0;
    foreach ($trend_series as $vals) { $sum += $vals[$i]; }
    $overall[] = (int) round($sum / max(1, count($trend_series)));
}
/* Markers are keyed by month index across the full year; shift them into the
   window and drop any that fell outside it. */
$markers = [];
foreach ($attendance_trend_demo['markers'] as $i => $label) {
    $j = $i - $offset;
    if ($j >= 0 && $j < count($labels)) { $markers[$j] = $label; }
}

$by_service = array_map('rep_scale', $attendance_by_service_demo);
arsort($by_service);
$by_dow     = array_map('rep_scale', $attendance_by_dow_demo);
$demog      = array_map('rep_scale', $attendance_demographics_demo);
$bands      = array_map('rep_scale', $attendance_rate_bands_demo);

$funnel = array_map(static function ($f) {
    return $f + ['scaled' => rep_scale($f['value'])];
}, $attendance_funnel_demo);
$funnel_top = max(1, $funnel[0]['scaled']);

$yoy_this = rep_scale_series(rep_window($attendance_yoy_demo['this_year'], $range['months']));
$yoy_last = rep_scale_series(rep_window($attendance_yoy_demo['last_year'], $range['months']));
$yoy_avg_this = $yoy_this ? (int) round(array_sum($yoy_this) / count($yoy_this)) : 0;
$yoy_avg_last = $yoy_last ? (int) round(array_sum($yoy_last) / count($yoy_last)) : 0;
$yoy_change   = $yoy_avg_last ? (($yoy_avg_this - $yoy_avg_last) / $yoy_avg_last) * 100 : 0;

/* Tab 2 — one row per member, built from the register's own numbers so the
   two pages can never disagree. */
$member_rows = [];
if ($has_module && $can_report) {
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
            'attended'   => (int) $a['attended'],
            'of'         => (int) $a['of'],
            'rate'       => round($rate, 1),
            'last_days'  => (int) $a['last_days'],
            'trend'      => (int) $a['trend'],
            'longest'    => (int) ($attendance_longest_streak_demo[$m['id']] ?? 0),
            'category'   => rep_category($rate, (int) $a['last_days']),
            '_branch'    => $branch_aware ? mu_branch_for($m['member_no']) : null,
        ];
    }
}
if ($branch_aware && !$viewing_all) {
    $member_rows = array_values(array_filter($member_rows, static function ($m) use ($current_branch) {
        return $m['_branch'] && (int) $m['_branch']['id'] === (int) $current_branch;
    }));
}

$cat_counts = ['Regular' => 0, 'Occasional' => 0, 'At Risk' => 0, 'Dormant' => 0];
foreach ($member_rows as $m) { $cat_counts[$m['category']]++; }

$faithful = $member_rows;
usort($faithful, static fn($a, $b) => $b['rate'] <=> $a['rate']);
$faithful = array_slice($faithful, 0, 10);

$follow_up = array_values(array_filter($member_rows, static fn($m) => in_array($m['category'], ['At Risk', 'Dormant'], true)));
usort($follow_up, static fn($a, $b) => $b['last_days'] <=> $a['last_days']);

/* Tab 3 — the groupings actually available to this church. A grouping whose
   module is off is never offered. */
$groupings = [];
if (mu_mod('departments'))  { $groupings['Department'] = $attendance_groups_demo['Department']; }
if (mu_mod('cell_groups'))  { $groupings['Cell Group'] = $attendance_groups_demo['Cell Group']; }
if ($show_branch) {
    $rows = [];
    foreach ($branch_options as $i => $b) {
        $seed = crc32('grp-' . $b['id']);
        $rows[] = [
            'name'    => $b['name'],
            'members' => (int) $b['members_count'],
            'avg'     => (int) round($b['members_count'] * (0.52 + ($seed % 22) / 100)),
            'rate'    => 52 + ($seed % 38),
            'trend'   => ($seed % 17) - 8,
            'best'    => ['Dec', 'Apr', 'Mar', 'Nov'][$seed % 4],
            'worst'   => ['Jun', 'Jul', 'Aug', 'Feb'][($seed >> 3) % 4],
            'months'  => array_map(
                static fn($k) => (int) round($b['members_count'] * (0.48 + ((($seed >> $k) % 26) / 100))),
                range(0, 11)
            ),
        ];
    }
    $groupings[t('branch_singular')] = $rows;
}
$groupings['Age Group'] = $attendance_groups_demo['Age Group'];
$groupings['Gender']    = $attendance_groups_demo['Gender'];

$group_keys    = array_keys($groupings);
$group_default = $group_keys[0];

/* Tab 4 — growth. */
$avm_labels = rep_window($attendance_vs_membership_demo['labels'], $range['months']);
$avm_att    = rep_scale_series(rep_window($attendance_vs_membership_demo['attendance'], $range['months']));
$avm_mem    = rep_scale_series(rep_window($attendance_vs_membership_demo['membership'], $range['months']));

$seasonal = [];
foreach ($attendance_seasonal_demo as $month => $weeks) { $seasonal[$month] = rep_scale_series($weeks); }
/* The heat steps are quintiles of the actual weeks, not a linear stretch
   between the quietest and busiest. One outlier — a Christmas week half again
   as big as any other — flattens a linear scale so that every ordinary week
   lands in the same shade, which defeats the point of the grid. */
$season_vals = array_values(array_filter(array_merge(...array_values($seasonal))));
sort($season_vals);
$season_cuts = [];
if ($season_vals) {
    $n = count($season_vals);
    foreach ([0.2, 0.4, 0.6, 0.8] as $q) {
        $season_cuts[] = $season_vals[max(0, min($n - 1, (int) floor($q * $n)))];
    }
}

/** Which of the five bands a week's figure falls into. */
function rep_heat_step(int $v, array $cuts): int
{
    $step = 0;
    foreach ($cuts as $c) { if ($v >= $c) { $step++; } }
    return min(4, $step);
}

$proj_mid  = rep_scale_series($attendance_projection_demo['mid']);
$proj_low  = rep_scale_series($attendance_projection_demo['low']);
$proj_high = rep_scale_series($attendance_projection_demo['high']);

/* The stat strip, scaled the same way as everything else. */
$S = $attendance_report_stats;
$stat_average  = ['now' => rep_scale($S['average']['now']),  'prev' => rep_scale($S['average']['prev'])];
$stat_services = ['now' => (int) round($S['services']['now'] * ($range['months'] / 12)),
                  'prev' => (int) round($S['services']['prev'] * ($range['months'] / 12))];

/* Who a scheduled report can be sent to. LATER: the users table, filtered to
   the roles that may receive one. */
$report_recipients = [];
foreach ($demo_roles as $key => $r) {
    if (in_array($key, ['church_admin', 'pastor', 'secretary', 'treasurer'], true)) {
        $report_recipients[] = ['name' => $r['user']['name'], 'role' => $r['user']['role_label'], 'email' => $r['user']['email']];
    }
}

$page_title = 'Attendance Reports';
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
        <span aria-current="page">Attendance Reports</span>
      </nav>
      <div class="title-row">
        <h1 class="page__title">Attendance Reports</h1>
      </div>
      <p class="page__sub">Understand attendance patterns across your church.</p>
    </div>

    <?php if ($has_module && $can_report): ?>
      <div class="page__actions">
        <!-- The range is a link set, not a form: it survives a reload and can
             be bookmarked, which a report view wants. -->
        <div class="drop" data-menu>
          <button class="btn btn--ghost" type="button" aria-haspopup="true" aria-expanded="false" data-menu-btn>
            <i class="fa-regular fa-calendar" aria-hidden="true"></i> <?= htmlspecialchars($range['label']) ?>
            <i class="fa-solid fa-chevron-down" style="font-size:10px;opacity:.7" aria-hidden="true"></i>
          </button>
          <div class="menu" data-menu-panel hidden>
            <?php foreach ($ranges as $key => $r): ?>
              <?php
                $q = $_GET; $q['range'] = $key;
                $href = strtok($_SERVER['REQUEST_URI'] ?? '', '?') . '?' . http_build_query($q);
              ?>
              <a class="menu__item<?= $key === $range_key ? ' is-on' : '' ?>" href="<?= htmlspecialchars($href) ?>"
                 <?= $key === $range_key ? 'aria-current="true"' : '' ?>>
                <i class="fa-solid <?= $key === $range_key ? 'fa-circle-check' : 'fa-circle' ?>" aria-hidden="true"></i>
                <?= htmlspecialchars($r['label']) ?>
              </a>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="drop" data-menu>
          <button class="btn btn--ghost" type="button" aria-haspopup="true" aria-expanded="false" data-menu-btn>
            <i class="fa-solid fa-file-export" aria-hidden="true"></i> Export
            <i class="fa-solid fa-chevron-down" style="font-size:10px;opacity:.7" aria-hidden="true"></i>
          </button>
          <div class="menu" data-menu-panel hidden>
            <button class="menu__item" type="button" data-export="CSV"><i class="fa-solid fa-file-csv" aria-hidden="true"></i> Export as CSV</button>
            <button class="menu__item" type="button" data-export="Excel"><i class="fa-solid fa-file-excel" aria-hidden="true"></i> Export as Excel</button>
            <button class="menu__item" type="button" data-export="PDF"><i class="fa-solid fa-file-pdf" aria-hidden="true"></i> Export as PDF</button>
          </div>
        </div>

        <button class="iconbtn iconbtn--bordered" type="button" id="btnPrint" aria-label="Print this report" title="Print">
          <i class="fa-solid fa-print" aria-hidden="true"></i>
        </button>

        <?php if (mu_mod('communication')): ?>
          <button class="btn" type="button" data-open-schedule>
            <i class="fa-regular fa-clock" aria-hidden="true"></i> Schedule Report
          </button>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </header>


<?php if (!$has_module): ?>

  <section class="panel">
    <div class="empty">
      <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-plug-circle-xmark"></i></span>
      <h3>The Attendance module is switched off</h3>
      <p>Your church's plan does not include attendance recording, so there is nothing to report on. A church administrator can request it from the platform team.</p>
      <a class="btn" href="<?= $base_url ?>index.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Dashboard</a>
    </div>
  </section>

<?php elseif (!$can_report): ?>

  <section class="panel">
    <div class="empty">
      <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-lock"></i></span>
      <h3>You cannot view attendance reports</h3>
      <p>These reports need the <code>attendance.reports</code> permission. Ask a church administrator to grant it.</p>
      <a class="btn" href="<?= $base_url ?>index.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Dashboard</a>
    </div>
  </section>

<?php else: ?>

  <!-- ═════════════════════════════ STAT STRIP ═════════════════════════════ -->
  <div class="stat-strip">
    <div class="stat-tile is-static">
      <span class="stat-tile__icon tone-purple" aria-hidden="true"><i class="fa-solid fa-users"></i></span>
      <span class="stat-tile__body">
        <span class="stat-tile__value" data-count="<?= $stat_average['now'] ?>">0</span>
        <span class="stat-tile__label">Average Attendance</span>
        <?= rep_delta($stat_average['now'], $stat_average['prev']) ?>
      </span>
    </div>

    <div class="stat-tile is-static">
      <span class="stat-tile__icon tone-green" aria-hidden="true"><i class="fa-solid fa-percent"></i></span>
      <span class="stat-tile__body">
        <span class="stat-tile__value"><span data-count="<?= (int) $S['rate']['now'] ?>">0</span>%</span>
        <span class="stat-tile__label">Attendance Rate</span>
        <?= rep_delta((float) $S['rate']['now'], (float) $S['rate']['prev'], ' pts') ?>
      </span>
    </div>

    <div class="stat-tile is-static">
      <span class="stat-tile__icon tone-blue" aria-hidden="true"><i class="fa-solid fa-calendar-check"></i></span>
      <span class="stat-tile__body">
        <span class="stat-tile__value" data-count="<?= $stat_services['now'] ?>">0</span>
        <span class="stat-tile__label">Services Held</span>
        <?= rep_delta($stat_services['now'], $stat_services['prev']) ?>
      </span>
    </div>

    <div class="stat-tile is-static">
      <span class="stat-tile__icon tone-amber" aria-hidden="true"><i class="fa-solid fa-arrow-trend-up"></i></span>
      <span class="stat-tile__body">
        <span class="stat-tile__value"><span data-count="<?= $S['growth']['now'] ?>">0</span>%</span>
        <span class="stat-tile__label">Growth vs Last Period</span>
        <?= rep_delta((float) $S['growth']['now'], (float) $S['growth']['prev'], ' pts') ?>
      </span>
    </div>
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
      <?php if ($show_branch): ?>
        <div class="field">
          <label for="fBranch"><?= htmlspecialchars(t('branch_singular')) ?></label>
          <select class="select" id="fBranch" data-filter>
            <option>All</option>
            <?php foreach ($branch_options as $b): ?><option><?= htmlspecialchars($b['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <div class="field">
        <label for="fService">Service Type</label>
        <select class="select" id="fService" data-filter>
          <option>All</option>
          <?php foreach ($service_types_demo as $s): ?><option><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?>
        </select>
      </div>

      <?php if (mu_mod('departments')): ?>
        <div class="field">
          <label for="fDept">Department</label>
          <select class="select" id="fDept" data-filter>
            <option>All</option>
            <?php foreach ($departments_list as $d): ?><option><?= htmlspecialchars($d) ?></option><?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <div class="field">
        <label for="fAge">Age Group</label>
        <select class="select" id="fAge" data-filter>
          <option>All</option><option>Children (0-12)</option><option>Youth (13-24)</option>
          <option>Adults (25-59)</option><option>Seniors (60+)</option>
        </select>
      </div>

      <div class="field">
        <label for="fGender">Gender</label>
        <select class="select" id="fGender" data-filter>
          <option>All</option><option>Male</option><option>Female</option>
        </select>
      </div>

      <div class="filters__actions">
        <button class="btn" type="button" id="btnApply"><i class="fa-solid fa-check" aria-hidden="true"></i> Apply</button>
        <button class="btn btn--ghost" type="button" data-reset-filters><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Reset</button>
      </div>
    </div>

    <div class="chips-row" data-filter-chips hidden></div>
  </section>


  <!-- ══════════════════════════════ TABS ══════════════════════════════ -->
  <div class="tabs reptabs" role="tablist" aria-label="Report">
    <button type="button" role="tab" aria-selected="true"  data-rtab="overview">Overview</button>
    <button type="button" role="tab" aria-selected="false" data-rtab="member">By Member</button>
    <button type="button" role="tab" aria-selected="false" data-rtab="group">By Group</button>
    <button type="button" role="tab" aria-selected="false" data-rtab="growth">Growth Analysis</button>
  </div>

  <div id="reportBody">

    <!-- ══════════════════════ TAB 1 — OVERVIEW ══════════════════════ -->
    <div class="tabpanel" data-rpanel="overview" role="tabpanel">

      <section class="chartcard chartcard--full">
        <header class="chartcard__head">
          <div>
            <h2>Attendance Trend</h2>
            <p>Average attendance per service type, <?= htmlspecialchars(strtolower($range['label'])) ?>.</p>
          </div>
          <div class="chartcard__tools">
            <label class="chartcard__toggle">
              <span class="switch">
                <input type="checkbox" id="showOverall" checked>
                <span class="switch__track" aria-hidden="true"></span>
              </span>
              Overall average
            </label>
            <button class="iconbtn" type="button" data-zoom="trendChart" aria-label="Enlarge Attendance Trend">
              <i class="fa-solid fa-up-right-and-down-left-from-center" aria-hidden="true"></i>
            </button>
          </div>
        </header>
        <div class="chartbox chartbox--tall">
          <canvas id="trendChart"
                  data-title="Attendance Trend"
                  data-axis-x="Month" data-axis-y="Average attendance"></canvas>
        </div>
        <?php if ($markers): ?>
          <p class="chartcard__note">
            <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
            Marked months held a special service:
            <?= htmlspecialchars(implode(', ', array_map(
                  static fn($i, $l) => $labels[$i] . ' — ' . $l,
                  array_keys($markers), $markers))) ?>.
          </p>
        <?php endif; ?>
      </section>

      <div class="chartgrid chartgrid--2">
        <section class="chartcard">
          <header class="chartcard__head">
            <div>
              <h2>Attendance by Service</h2>
              <p>Average attendance, highest first.</p>
            </div>
            <button class="iconbtn" type="button" data-zoom="serviceChart" aria-label="Enlarge Attendance by Service">
              <i class="fa-solid fa-up-right-and-down-left-from-center" aria-hidden="true"></i>
            </button>
          </header>
          <div class="chartbox chartbox--tall">
            <canvas id="serviceChart" data-title="Attendance by Service"
                    data-axis-x="Average attendance" data-axis-y="Service"></canvas>
          </div>
        </section>

        <section class="chartcard">
          <header class="chartcard__head">
            <div>
              <h2>Attendance by Day of Week</h2>
              <p>Which days fill the building.</p>
            </div>
            <button class="iconbtn" type="button" data-zoom="dowChart" aria-label="Enlarge Attendance by Day of Week">
              <i class="fa-solid fa-up-right-and-down-left-from-center" aria-hidden="true"></i>
            </button>
          </header>
          <div class="chartbox chartbox--tall">
            <canvas id="dowChart" data-title="Attendance by Day of Week"
                    data-axis-x="Day" data-axis-y="Average attendance"></canvas>
          </div>
        </section>
      </div>

      <div class="chartgrid chartgrid--3">
        <section class="chartcard">
          <header class="chartcard__head">
            <div><h2>Demographics</h2><p>Who is in the room.</p></div>
            <button class="iconbtn" type="button" data-zoom="demogChart" aria-label="Enlarge Demographics">
              <i class="fa-solid fa-up-right-and-down-left-from-center" aria-hidden="true"></i>
            </button>
          </header>
          <div class="chartbox">
            <canvas id="demogChart" data-title="Demographics"></canvas>
          </div>
        </section>

        <section class="chartcard">
          <header class="chartcard__head">
            <div><h2>Attendance Rate Distribution</h2><p>Members in each rate band.</p></div>
            <button class="iconbtn" type="button" data-zoom="bandChart" aria-label="Enlarge Attendance Rate Distribution">
              <i class="fa-solid fa-up-right-and-down-left-from-center" aria-hidden="true"></i>
            </button>
          </header>
          <div class="chartbox">
            <canvas id="bandChart" data-title="Attendance Rate Distribution"
                    data-axis-x="Rate band" data-axis-y="Members"></canvas>
          </div>
        </section>

        <?php if (mu_mod('visitors')): ?>
          <section class="chartcard">
            <header class="chartcard__head">
              <div><h2>Visitor Conversion</h2><p>From first visit to membership.</p></div>
            </header>
            <ol class="funnel">
              <?php foreach ($funnel as $i => $f): ?>
                <?php $pct = (int) round(($f['scaled'] / $funnel_top) * 100); ?>
                <li class="funnel__step">
                  <span class="funnel__bar" style="width:<?= max(18, $pct) ?>%">
                    <b><?= number_format($f['scaled']) ?></b>
                  </span>
                  <span class="funnel__text">
                    <b><?= htmlspecialchars($f['label']) ?></b>
                    <span><?= htmlspecialchars($f['note']) ?><?= $i > 0 ? ' · ' . $pct . '% of visitors' : '' ?></span>
                  </span>
                </li>
              <?php endforeach; ?>
            </ol>
          </section>
        <?php endif; ?>
      </div>

      <section class="chartcard chartcard--full">
        <header class="chartcard__head">
          <div>
            <h2>Year-on-Year Comparison</h2>
            <p>This year against the same months last year.</p>
          </div>
          <button class="iconbtn" type="button" data-zoom="yoyChart" aria-label="Enlarge Year-on-Year Comparison">
            <i class="fa-solid fa-up-right-and-down-left-from-center" aria-hidden="true"></i>
          </button>
        </header>
        <div class="chartbox chartbox--tall">
          <canvas id="yoyChart" data-title="Year-on-Year Comparison"
                  data-axis-x="Month" data-axis-y="Average attendance"></canvas>
        </div>
        <ul class="inlinestats">
          <li><span>This Year Average</span><b><?= number_format($yoy_avg_this) ?></b></li>
          <li><span>Last Year Average</span><b><?= number_format($yoy_avg_last) ?></b></li>
          <li><span>Change</span><b class="<?= $yoy_change >= 0 ? 'is-up' : 'is-down' ?>">
            <?= ($yoy_change >= 0 ? '+' : '') . number_format($yoy_change, 1) ?>%
          </b></li>
        </ul>
      </section>
    </div>


    <!-- ══════════════════════ TAB 2 — BY MEMBER ══════════════════════ -->
    <div class="tabpanel" data-rpanel="member" role="tabpanel" hidden>

      <!-- Clickable tiles: each filters the table to its category. -->
      <div class="cattiles" role="group" aria-label="Filter by category">
        <?php foreach ([
          ['Regular',    'fa-circle-check',    'green', 'attends most services'],
          ['Occasional', 'fa-circle-half-stroke', 'amber', 'attends now and then'],
          ['At Risk',    'fa-triangle-exclamation', 'danger', 'attendance has dropped'],
          ['Dormant',    'fa-user-clock',      'grey',  'not seen in 60+ days'],
        ] as [$cat, $icon, $tone, $hint]): ?>
          <button class="cattile" type="button" data-cat="<?= htmlspecialchars($cat) ?>" aria-pressed="false">
            <span class="stat-tile__icon tone-<?= $tone ?>" aria-hidden="true"><i class="fa-solid <?= $icon ?>"></i></span>
            <span class="cattile__body">
              <span class="cattile__value"><?= (int) $cat_counts[$cat] ?></span>
              <span class="cattile__label"><?= htmlspecialchars($cat) ?></span>
              <span class="cattile__hint"><?= htmlspecialchars($hint) ?></span>
            </span>
          </button>
        <?php endforeach; ?>
      </div>

      <section class="panel">
        <div class="panel__head">
          <h2>Members</h2>
          <span class="pill tone-brand"><span data-member-count><?= count($member_rows) ?></span> shown</span>
        </div>

        <div class="dt-wrap">
          <table class="dt" id="memberTable">
            <thead>
              <tr>
                <th style="width:44px">#</th>
                <th class="is-sortable" data-sort="name">Member <i class="fa-solid fa-sort sort" aria-hidden="true"></i></th>
                <?php if (mu_mod('departments')): ?><th>Department</th><?php endif; ?>
                <th class="is-sortable" data-sort="attended">Services Attended <i class="fa-solid fa-sort sort" aria-hidden="true"></i></th>
                <th class="is-sortable" data-sort="rate" style="min-width:140px">Attendance Rate <i class="fa-solid fa-sort sort" aria-hidden="true"></i></th>
                <th class="is-sortable" data-sort="longest">Longest Streak <i class="fa-solid fa-sort sort" aria-hidden="true"></i></th>
                <th class="is-sortable" data-sort="last">Last Attended <i class="fa-solid fa-sort sort" aria-hidden="true"></i></th>
                <th>Trend</th>
                <th>Category</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($member_rows as $i => $m): ?>
                <?php $stale = $m['last_days'] > 30; ?>
                <tr data-mrow data-cat="<?= htmlspecialchars($m['category']) ?>"
                    data-name="<?= htmlspecialchars(mb_strtolower($m['name'])) ?>"
                    data-attended="<?= $m['attended'] ?>" data-rate="<?= $m['rate'] ?>"
                    data-longest="<?= $m['longest'] ?>" data-last="<?= $m['last_days'] ?>">
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
                    <td><?= $m['department'] !== ''
                          ? '<span class="dchip">' . htmlspecialchars($m['department']) . '</span>'
                          : '<span class="num">&mdash;</span>' ?></td>
                  <?php endif; ?>
                  <td class="nowrap"><span class="strong"><?= $m['attended'] ?></span> of <?= $m['of'] ?></td>
                  <td>
                    <span class="ratecell">
                      <b class="is-<?= reg_band((float) $m['rate']) ?>"><?= round($m['rate']) ?>%</b>
                      <?= reg_bar((float) $m['rate']) ?>
                    </span>
                  </td>
                  <td class="nowrap">
                    <?php if ($m['longest'] > 4): ?>
                      <span class="streak is-hot"><i class="fa-solid fa-fire" aria-hidden="true"></i> <?= $m['longest'] ?></span>
                    <?php else: ?>
                      <span class="streak"><?= $m['longest'] ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="nowrap<?= $stale ? ' is-stale' : '' ?>"><?= mu_ago($m['last_days']) ?></td>
                  <td class="nowrap"><?= rep_trend($m['trend']) ?></td>
                  <td><span class="catbadge is-<?= strtolower(str_replace(' ', '', $m['category'])) ?>"><?= htmlspecialchars($m['category']) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Stacked cards below 768px — never a shrunken table. -->
        <div class="dt-cards">
          <?php foreach ($member_rows as $m): ?>
            <article class="pcard" data-mrow data-cat="<?= htmlspecialchars($m['category']) ?>"
                     data-name="<?= htmlspecialchars(mb_strtolower($m['name'])) ?>"
                     data-attended="<?= $m['attended'] ?>" data-rate="<?= $m['rate'] ?>"
                     data-longest="<?= $m['longest'] ?>" data-last="<?= $m['last_days'] ?>">
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
                <div class="pcard__row"><span>Longest streak</span><span><?= $m['longest'] ?> services</span></div>
                <div class="pcard__row"><span>Last attended</span><span<?= $m['last_days'] > 30 ? ' class="is-stale"' : '' ?>><?= mu_ago($m['last_days']) ?></span></div>
                <div class="pcard__row"><span>Trend</span><span><?= rep_trend($m['trend']) ?></span></div>
                <div class="pcard__row"><span>Category</span><span class="catbadge is-<?= strtolower(str_replace(' ', '', $m['category'])) ?>"><?= htmlspecialchars($m['category']) ?></span></div>
                <?php if (mu_mod('departments') && $m['department'] !== ''): ?>
                  <div class="pcard__row"><span>Department</span><span><?= htmlspecialchars($m['department']) ?></span></div>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>

        <div class="empty" id="memberEmpty" hidden>
          <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-filter-circle-xmark"></i></span>
          <h3>No members in that category</h3>
          <p>Pick a different category, or clear it to see the whole roll again.</p>
          <button class="btn btn--ghost" type="button" data-clear-cat>
            <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Show all members
          </button>
        </div>
      </section>

      <div class="chartgrid chartgrid--2">
        <section class="panel">
          <div class="panel__head">
            <h2>Most Faithful</h2>
            <span class="pill tone-ok">Top 10</span>
          </div>
          <?php if (!$faithful): ?>
            <div class="card__empty"><i class="fa-regular fa-face-smile" aria-hidden="true"></i><p>No attendance recorded in this range.</p></div>
          <?php else: ?>
            <ol class="ranklist">
              <?php foreach ($faithful as $i => $m): ?>
                <li class="rankrow">
                  <span class="rankrow__pos<?= $i < 3 ? ' is-medal is-m' . ($i + 1) : '' ?>">
                    <?= $i < 3 ? '<i class="fa-solid fa-medal" aria-hidden="true"></i>' : ($i + 1) ?>
                    <?php if ($i < 3): ?><span class="u-sr">Position <?= $i + 1 ?></span><?php endif; ?>
                  </span>
                  <?= mu_av($m['name'], 'sm') ?>
                  <span class="rankrow__text">
                    <span class="rankrow__name"><?= htmlspecialchars($m['name']) ?></span>
                    <span class="rankrow__meta"><?= $m['attended'] ?> of <?= $m['of'] ?> services</span>
                  </span>
                  <span class="ratecell rankrow__rate">
                    <b class="is-<?= reg_band((float) $m['rate']) ?>"><?= round($m['rate']) ?>%</b>
                    <?= reg_bar((float) $m['rate']) ?>
                  </span>
                </li>
              <?php endforeach; ?>
            </ol>
          <?php endif; ?>
        </section>

        <section class="panel">
          <div class="panel__head">
            <h2>Needs Follow-Up</h2>
            <span class="pill tone-danger"><?= count($follow_up) ?></span>
          </div>
          <?php if (!$follow_up): ?>
            <div class="card__empty"><i class="fa-solid fa-heart" aria-hidden="true"></i><p>Nobody has slipped away. </p></div>
          <?php else: ?>
            <ul class="ranklist">
              <?php foreach ($follow_up as $m): ?>
                <li class="rankrow">
                  <?= mu_av($m['name'], 'sm') ?>
                  <span class="rankrow__text">
                    <span class="rankrow__name"><?= htmlspecialchars($m['name']) ?></span>
                    <span class="rankrow__meta">
                      <?= mu_ago($m['last_days']) ?> &middot; <?= round($m['rate']) ?>% rate
                    </span>
                  </span>
                  <span class="catbadge is-<?= strtolower(str_replace(' ', '', $m['category'])) ?>"><?= htmlspecialchars($m['category']) ?></span>
                  <?php if (mu_mod('communication')): ?>
                    <button class="btn btn--sm" type="button" data-toast="Follow-up queued for <?= htmlspecialchars($m['name']) ?>">Contact</button>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </section>
      </div>
    </div>


    <!-- ══════════════════════ TAB 3 — BY GROUP ══════════════════════ -->
    <div class="tabpanel" data-rpanel="group" role="tabpanel" hidden>

      <div class="toolbar">
        <div class="svcviews svcviews--scroll" role="group" aria-label="Group by">
          <?php foreach ($group_keys as $i => $g): ?>
            <button class="svcview<?= $i === 0 ? ' is-on' : '' ?>" type="button"
                    data-grouping="<?= htmlspecialchars($g) ?>" aria-pressed="<?= $i === 0 ? 'true' : 'false' ?>">
              By <?= htmlspecialchars($g) ?>
            </button>
          <?php endforeach; ?>
        </div>
      </div>

      <section class="panel">
        <div class="panel__head">
          <h2>Comparison by <span data-group-label><?= htmlspecialchars($group_default) ?></span></h2>
          <span class="pill tone-brand"><span data-group-count>0</span> groups</span>
        </div>
        <div class="dt-wrap">
          <table class="dt" id="groupTable">
            <thead>
              <tr>
                <th style="width:44px">#</th>
                <th>Group</th>
                <th>Members</th>
                <th>Average Attendance</th>
                <th style="min-width:150px">Attendance Rate</th>
                <th>Trend</th>
                <th>Best Month</th>
                <th>Worst Month</th>
              </tr>
            </thead>
            <tbody data-group-body></tbody>
          </table>
        </div>
        <div class="dt-cards" data-group-cards></div>
      </section>

      <section class="chartcard chartcard--full">
        <header class="chartcard__head">
          <div>
            <h2>Groups Over Time</h2>
            <p>Average attendance per group, month by month.</p>
          </div>
          <button class="iconbtn" type="button" data-zoom="groupChart" aria-label="Enlarge Groups Over Time">
            <i class="fa-solid fa-up-right-and-down-left-from-center" aria-hidden="true"></i>
          </button>
        </header>
        <div class="chartbox chartbox--tall">
          <canvas id="groupChart" data-title="Groups Over Time"
                  data-axis-x="Month" data-axis-y="Average attendance"></canvas>
        </div>
      </section>

      <section class="panel">
        <div class="panel__head">
          <h2>Leaderboard &mdash; <span data-group-label><?= htmlspecialchars($group_default) ?></span></h2>
          <span class="pill tone-ok">By attendance rate</span>
        </div>
        <ol class="ranklist" data-group-board></ol>
      </section>
    </div>


    <!-- ══════════════════════ TAB 4 — GROWTH ANALYSIS ══════════════════════ -->
    <div class="tabpanel" data-rpanel="growth" role="tabpanel" hidden>

      <section class="chartcard chartcard--full">
        <header class="chartcard__head">
          <div>
            <h2>Attendance vs Membership</h2>
            <p>Whether attendance is keeping pace with the roll. Two axes: the scales differ by an order of magnitude.</p>
          </div>
          <button class="iconbtn" type="button" data-zoom="avmChart" aria-label="Enlarge Attendance vs Membership">
            <i class="fa-solid fa-up-right-and-down-left-from-center" aria-hidden="true"></i>
          </button>
        </header>
        <div class="chartbox chartbox--tall">
          <canvas id="avmChart" data-title="Attendance vs Membership"
                  data-axis-x="Month" data-axis-y="Average attendance"></canvas>
        </div>
      </section>

      <section class="chartcard chartcard--full">
        <header class="chartcard__head">
          <div>
            <h2>Seasonal Patterns</h2>
            <p>Months down, weeks across. Darker is better attended.</p>
          </div>
        </header>

        <?php if (!$season_vals): ?>
          <div class="card__empty"><i class="fa-regular fa-calendar" aria-hidden="true"></i><p>No attendance recorded in this range.</p></div>
        <?php else: ?>
          <div class="heatwrap">
            <table class="heat">
              <caption class="u-sr">Average attendance by month and week of month</caption>
              <thead>
                <tr>
                  <th scope="col"><span class="u-sr">Month</span></th>
                  <?php for ($w = 1; $w <= 4; $w++): ?><th scope="col">Week <?= $w ?></th><?php endfor; ?>
                  <th scope="col">Month avg</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($seasonal as $month => $weeks): ?>
                  <?php
                    $live = array_filter($weeks);
                    $mavg = $live ? (int) round(array_sum($live) / count($live)) : 0;
                  ?>
                  <tr>
                    <th scope="row"><?= htmlspecialchars($month) ?></th>
                    <?php foreach ($weeks as $v): ?>
                      <?php $step = $v > 0 ? rep_heat_step((int) $v, $season_cuts) : null; ?>
                      <td class="heat__cell<?= $v > 0 ? ' is-s' . $step : ' is-none' ?>"
                          title="<?= htmlspecialchars($month) ?>: <?= $v > 0 ? number_format($v) . ' average' : 'no service' ?>">
                        <?= $v > 0 ? number_format($v) : '&mdash;' ?>
                      </td>
                    <?php endforeach; ?>
                    <td class="heat__avg"><?= $mavg ? number_format($mavg) : '&mdash;' ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <ul class="heatkey" aria-hidden="true">
            <li>Quieter</li>
            <?php for ($s = 0; $s <= 4; $s++): ?><li><span class="heat__swatch is-s<?= $s ?>"></span></li><?php endfor; ?>
            <li>Busier</li>
          </ul>
        <?php endif; ?>
      </section>

      <section class="panel">
        <div class="panel__head">
          <h2>Retention Cohorts</h2>
          <span class="pill tone-grey">Still attending after joining</span>
        </div>
        <div class="dt-wrap">
          <table class="dt">
            <thead>
              <tr>
                <th>Joined</th><th>Members</th>
                <th style="min-width:120px">At 3 months</th>
                <th style="min-width:120px">At 6 months</th>
                <th style="min-width:120px">At 12 months</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($attendance_cohorts_demo as $c): ?>
                <tr>
                  <td class="strong nowrap"><?= htmlspecialchars($c['month']) ?></td>
                  <td class="num"><?= (int) $c['joined'] ?></td>
                  <?php foreach (['m3', 'm6', 'm12'] as $k): ?>
                    <td>
                      <?php if ($c[$k] === null): ?>
                        <span class="num" title="This cohort is not old enough yet">&mdash;</span>
                      <?php else: ?>
                        <span class="ratecell">
                          <b class="is-<?= reg_band((float) $c[$k]) ?>"><?= (int) $c[$k] ?>%</b>
                          <?= reg_bar((float) $c[$k]) ?>
                        </span>
                      <?php endif; ?>
                    </td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="chartcard__note">
          <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
          A dash means the cohort has not reached that milestone yet.
        </p>
      </section>

      <section class="chartcard chartcard--full">
        <header class="chartcard__head">
          <div>
            <h2>Projections</h2>
            <p>The trend carried forward six months, with the range the estimate sits inside.</p>
          </div>
          <button class="iconbtn" type="button" data-zoom="projChart" aria-label="Enlarge Projections">
            <i class="fa-solid fa-up-right-and-down-left-from-center" aria-hidden="true"></i>
          </button>
        </header>
        <div class="at-notice at-notice--info" role="note" style="margin:0 0 14px">
          <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
          <div class="at-notice__body">
            <strong>This is an estimate, not a forecast</strong>
            <span>A straight line drawn through recent months. It knows nothing about what your church has planned.</span>
          </div>
        </div>
        <div class="chartbox chartbox--tall">
          <canvas id="projChart" data-title="Projections"
                  data-axis-x="Month" data-axis-y="Projected attendance"></canvas>
        </div>
      </section>
    </div>

  </div><!-- /#reportBody -->

<?php endif; ?>

</div><!-- /.page -->


<?php if ($has_module && $can_report): ?>

<!-- ══════════════════════════ CHART DETAIL MODAL ══════════════════════════ -->
<div class="modal-scrim" id="modalChart" hidden>
  <div class="modal modal--chart" role="dialog" aria-modal="true" aria-labelledby="zoomTitle">
    <header class="modal__head">
      <h2 id="zoomTitle">Chart</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>
    <div class="modal__body">
      <div class="chartbox chartbox--zoom"><canvas id="zoomChart"></canvas></div>
      <p class="minilist__head">Underlying data</p>
      <div class="dt-wrap">
        <table class="dt" style="font-size:12.5px">
          <thead data-zoom-head></thead>
          <tbody data-zoom-body></tbody>
        </table>
      </div>
    </div>
    <footer class="modal__foot">
      <span class="modal__spacer"></span>
      <button class="btn btn--ghost" type="button" data-close>Close</button>
      <button class="btn" type="button" id="zoomDownload">
        <i class="fa-solid fa-download" aria-hidden="true"></i> Download data
      </button>
    </footer>
  </div>
</div>


<!-- ══════════════════════════ EXPORT OPTIONS MODAL ══════════════════════════ -->
<div class="modal-scrim" id="modalExport" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="exTitle">
    <header class="modal__head">
      <h2 id="exTitle">Export Options</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>
    <div class="modal__body">
      <p class="modal__group">Format</p>
      <div class="radio-cards" id="exFormat">
        <?php foreach ([['PDF', 'fa-file-pdf', 'Formatted, ready to print'],
                        ['Excel', 'fa-file-excel', 'Figures you can work with'],
                        ['CSV', 'fa-file-csv', 'Raw rows, no formatting']] as $i => [$f, $ic, $hint]): ?>
          <label class="rcard">
            <input type="radio" name="exFormat" value="<?= $f ?>" <?= $i === 0 ? 'checked' : '' ?>>
            <span class="rcard__box">
              <i class="fa-solid <?= $ic ?>" aria-hidden="true"></i>
              <span><strong><?= $f ?></strong><span class="hint" style="margin:2px 0 0"><?= $hint ?></span></span>
            </span>
          </label>
        <?php endforeach; ?>
      </div>

      <p class="modal__group">Sections to include</p>
      <div class="modal__list">
        <?php foreach ([
          ['exOverview', 'fa-chart-line',   'Overview and trend', true],
          ['exMember',   'fa-user-check',   'By member', true],
          ['exGroup',    'fa-layer-group',  'By group', true],
          ['exGrowth',   'fa-arrow-trend-up', 'Growth analysis', false],
        ] as [$id, $ic, $label, $on]): ?>
          <div class="modal__row">
            <i class="fa-solid <?= $ic ?> modal__row-icon" aria-hidden="true"></i>
            <span class="modal__row-label"><?= $label ?></span>
            <span class="switch"><input type="checkbox" id="<?= $id ?>" <?= $on ? 'checked' : '' ?>><span class="switch__track" aria-hidden="true"></span></span>
          </div>
        <?php endforeach; ?>
      </div>

      <p class="modal__group">Date range</p>
      <div class="form-grid">
        <div class="field"><label for="exFrom">From</label><input class="input" type="date" id="exFrom"></div>
        <div class="field"><label for="exTo">To</label><input class="input" type="date" id="exTo"></div>
      </div>

      <div class="modal__list" style="margin-top:14px">
        <div class="modal__row">
          <i class="fa-solid fa-chart-pie modal__row-icon" aria-hidden="true"></i>
          <span class="modal__row-label">Include charts</span>
          <span class="switch"><input type="checkbox" id="exCharts" checked><span class="switch__track" aria-hidden="true"></span></span>
        </div>
      </div>
    </div>
    <footer class="modal__foot">
      <span class="modal__spacer"></span>
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <button class="btn" type="button" id="exGo"><i class="fa-solid fa-download" aria-hidden="true"></i> Export</button>
    </footer>
  </div>
</div>


<?php if (mu_mod('communication')): ?>
<!-- ══════════════════════════ SCHEDULE REPORT MODAL ══════════════════════════ -->
<div class="modal-scrim" id="modalSchedule" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="schTitle">
    <header class="modal__head">
      <h2 id="schTitle">Schedule Report</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>
    <div class="modal__body">
      <div class="form-grid">
        <div class="field col-2">
          <label for="schType">Report</label>
          <select class="select" id="schType">
            <option>Attendance Overview</option>
            <option>By Member</option>
            <option>By Group</option>
            <option>Growth Analysis</option>
            <option>Everything</option>
          </select>
        </div>
      </div>

      <p class="modal__group">Frequency</p>
      <div class="radio-cards" id="schFreq">
        <?php foreach ([['Weekly', 'fa-calendar-week', 'Every week, on the day below'],
                        ['Monthly', 'fa-calendar-days', 'Once a month'],
                        ['Quarterly', 'fa-calendar', 'Every three months']] as $i => [$f, $ic, $hint]): ?>
          <label class="rcard">
            <input type="radio" name="schFreq" value="<?= $f ?>" <?= $i === 1 ? 'checked' : '' ?>>
            <span class="rcard__box">
              <i class="fa-solid <?= $ic ?>" aria-hidden="true"></i>
              <span><strong><?= $f ?></strong><span class="hint" style="margin:2px 0 0"><?= $hint ?></span></span>
            </span>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="form-grid" style="margin-top:16px">
        <div class="field">
          <label for="schDay">Day</label>
          <select class="select" id="schDay">
            <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $d): ?>
              <option <?= $d === 'Monday' ? 'selected' : '' ?>><?= $d ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label for="schTime">Time</label><input class="input" type="time" id="schTime" value="07:00"></div>
      </div>

      <p class="modal__group">Recipients</p>
      <div class="minilist">
        <?php foreach ($report_recipients as $i => $r): ?>
          <label class="minirow minirow--pick">
            <?= mu_av($r['name'], 'sm') ?>
            <span class="minirow__text">
              <b><?= htmlspecialchars($r['name']) ?></b>
              <span><?= htmlspecialchars($r['role']) ?> &middot; <?= htmlspecialchars($r['email']) ?></span>
            </span>
            <span class="switch"><input type="checkbox" data-recipient <?= $i < 2 ? 'checked' : '' ?>><span class="switch__track" aria-hidden="true"></span></span>
          </label>
        <?php endforeach; ?>
      </div>

      <p class="modal__group">Format</p>
      <div class="radio-cards" id="schFormat">
        <?php foreach ([['PDF', 'fa-file-pdf'], ['Excel', 'fa-file-excel']] as $i => [$f, $ic]): ?>
          <label class="rcard">
            <input type="radio" name="schFormat" value="<?= $f ?>" <?= $i === 0 ? 'checked' : '' ?>>
            <span class="rcard__box">
              <i class="fa-solid <?= $ic ?>" aria-hidden="true"></i>
              <span><strong><?= $f ?></strong><span class="hint" style="margin:2px 0 0">Attached to the email</span></span>
            </span>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="field" style="margin-top:16px">
        <label for="schMsg">Message</label>
        <textarea class="textarea" id="schMsg" rows="3"
                  placeholder="A note to go with the report&hellip;">Here is this month's attendance report.</textarea>
      </div>
    </div>
    <footer class="modal__foot">
      <span class="modal__spacer"></span>
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <button class="btn" type="button" id="schGo"><i class="fa-regular fa-clock" aria-hidden="true"></i> Schedule</button>
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
      <li><a class="demo__role<?= $key === $demo_role ? ' is-on' : '' ?>"
             href="?role=<?= urlencode($key) ?>&amp;range=<?= urlencode($range_key) ?>"
             <?= $key === $demo_role ? 'aria-current="true"' : '' ?>>
        <span class="demo__av" aria-hidden="true"><?= htmlspecialchars($r['user']['initials']) ?></span>
        <?= htmlspecialchars($r['user']['role_label']) ?>
      </a></li>
    <?php endforeach; ?>
  </ul>
</details>
<?php /* ═══════════════════════ END DEMO ═══════════════════════ */ ?>

<?php if ($has_module && $can_report): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
/* Attendance Reports — tabs, filters, the category tiles, the grouping
   switcher, and every chart. All client-side; nothing is re-queried. */
(function () {
  'use strict';

  var still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var $  = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };

  /* ───────────────────────── the data behind the page ───────────────────── */
  var LABELS   = <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>;
  var TREND    = <?= json_encode($trend_series, JSON_UNESCAPED_UNICODE) ?>;
  var OVERALL  = <?= json_encode($overall) ?>;
  var MARKERS  = <?= json_encode($markers, JSON_UNESCAPED_UNICODE) ?>;
  var BYSVC    = <?= json_encode($by_service, JSON_UNESCAPED_UNICODE) ?>;
  var BYDOW    = <?= json_encode($by_dow, JSON_UNESCAPED_UNICODE) ?>;
  var DEMOG    = <?= json_encode($demog, JSON_UNESCAPED_UNICODE) ?>;
  var BANDS    = <?= json_encode($bands, JSON_UNESCAPED_UNICODE) ?>;
  var YOY      = <?= json_encode(['this' => $yoy_this, 'last' => $yoy_last], JSON_UNESCAPED_UNICODE) ?>;
  var AVM      = <?= json_encode(['labels' => $avm_labels, 'att' => $avm_att, 'mem' => $avm_mem], JSON_UNESCAPED_UNICODE) ?>;
  var PROJ     = <?= json_encode([
                      'labels' => $attendance_projection_demo['labels'],
                      'mid' => $proj_mid, 'low' => $proj_low, 'high' => $proj_high,
                   ], JSON_UNESCAPED_UNICODE) ?>;
  var GROUPS   = <?= json_encode($groupings, JSON_UNESCAPED_UNICODE) ?>;
  var MONTHS12 = <?= json_encode($attendance_trend_demo['labels'], JSON_UNESCAPED_UNICODE) ?>;

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

  /* footer.php stops propagation inside a [data-menu-panel], so anything that
     lives in a dropdown needs a capturing listener and has to close its own
     menu afterwards. */
  function closeOwnMenu(el) {
    var drop = el.closest('[data-menu]');
    if (!drop) { return; }
    drop.classList.remove('is-open');
    drop.querySelector('[data-menu-btn]').setAttribute('aria-expanded', 'false');
    drop.querySelector('[data-menu-panel]').hidden = true;
  }
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-toast]');
    if (t) { e.preventDefault(); closeOwnMenu(t); toast(t.getAttribute('data-toast')); }
  }, true);

  /* ─────────────────────── counts tick up ─────────────────────── */
  $$('[data-count]').forEach(function (el) {
    var target = parseFloat(el.getAttribute('data-count')) || 0;
    var dp = String(target).indexOf('.') === -1 ? 0 : 1;
    if (still) { el.textContent = target.toFixed(dp); return; }
    var start = performance.now();
    (function step(now) {
      var p = Math.min(1, (now - start) / 900);
      var v = target * (1 - Math.pow(1 - p, 3));
      el.textContent = dp ? v.toFixed(1) : Math.round(v).toLocaleString();
      if (p < 1) { requestAnimationFrame(step); }
    })(start);
  });

  /* ═════════════════════════ chart defaults ═════════════════════════ */
  /* The dashboard's palette and options, so a chart here looks like a chart
     anywhere else in the system. */
  var PALETTE = ['#662F97', '#B48FDA', '#8F5CC2', '#D3BAEA', '#56287F'];
  var GRID    = '#ECE7F3';
  var charts  = {};

  if (window.Chart) {
    Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.color = '#6E6880';
    Chart.defaults.animation = still ? false : { duration: 500 };
    Chart.defaults.plugins.tooltip.padding = 10;
    Chart.defaults.plugins.tooltip.cornerRadius = 8;
    Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(27,21,38,.92)';
  }

  function axis(title) {
    return title ? { display: true, text: title, color: '#948CA6', font: { size: 11, weight: '600' } } : { display: false };
  }
  function xy(cv, opts) {
    opts = opts || {};
    return {
      x: {
        grid: { display: false }, border: { display: false },
        title: axis(cv.getAttribute('data-axis-x')),
        ticks: opts.xTicks || {}
      },
      y: {
        grid: { color: GRID }, border: { display: false }, beginAtZero: true,
        title: axis(cv.getAttribute('data-axis-y'))
      }
    };
  }

  /* Marks the months that held a special service. Drawn under the data so it
     never obscures a point. */
  var markerPlugin = {
    id: 'svcMarkers',
    beforeDatasetsDraw: function (c) {
      var keys = Object.keys(MARKERS);
      if (!keys.length || !c.scales.x) { return; }
      var ctx = c.ctx, top = c.chartArea.top, bot = c.chartArea.bottom;
      ctx.save();
      keys.forEach(function (k) {
        var x = c.scales.x.getPixelForValue(+k);
        ctx.beginPath();
        ctx.setLineDash([4, 4]);
        ctx.strokeStyle = 'rgba(180,83,9,.45)';
        ctx.lineWidth = 1;
        ctx.moveTo(x, top); ctx.lineTo(x, bot); ctx.stroke();
        ctx.setLineDash([]);
        ctx.fillStyle = '#B45309';
        ctx.beginPath(); ctx.arc(x, top + 5, 3.5, 0, Math.PI * 2); ctx.fill();
      });
      ctx.restore();
    }
  };

  /* An empty canvas gets a message rather than an unexplained blank box. */
  function emptyFor(cv, msg) {
    var box = cv.closest('.chartbox');
    if (!box) { return; }
    var e = document.createElement('p');
    e.className = 'chartempty';
    e.innerHTML = '<i class="fa-regular fa-chart-bar" aria-hidden="true"></i> ' + msg;
    box.appendChild(e);
    cv.hidden = true;
  }
  function hasData(arr) { return arr.some(function (v) { return +v > 0; }); }

  /* ═════════════════════════ TAB 1 — OVERVIEW ═════════════════════════ */

  var trendCv = $('#trendChart');
  if (trendCv && window.Chart) {
    var names = Object.keys(TREND);
    var sets = names.map(function (n, i) {
      return {
        label: n, data: TREND[n],
        borderColor: PALETTE[i % PALETTE.length],
        backgroundColor: 'rgba(102,47,151,.06)',
        fill: false, tension: .35, borderWidth: 2,
        pointRadius: 0, pointHoverRadius: 4
      };
    });
    sets.push({
      label: 'Overall average', data: OVERALL,
      borderColor: '#1B1526', borderDash: [6, 4], borderWidth: 2,
      fill: false, tension: .35, pointRadius: 0, pointHoverRadius: 4
    });

    charts.trendChart = new Chart(trendCv, {
      type: 'line',
      data: { labels: LABELS, datasets: sets },
      options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: true, position: 'bottom', labels: { boxWidth: 10, padding: 12, usePointStyle: true } },
          tooltip: {
            callbacks: {
              afterTitle: function (items) {
                var m = MARKERS[items[0].dataIndex];
                return m ? 'Special service: ' + m : '';
              }
            }
          }
        },
        scales: xy(trendCv)
      },
      plugins: [markerPlugin]
    });

    /* The overall line is the last dataset. */
    var overallToggle = $('#showOverall');
    if (overallToggle) {
      overallToggle.addEventListener('change', function () {
        var c = charts.trendChart;
        c.setDatasetVisibility(c.data.datasets.length - 1, this.checked);
        c.update();
      });
    }
  }

  var svcCv = $('#serviceChart');
  if (svcCv && window.Chart) {
    var sLabels = Object.keys(BYSVC), sVals = sLabels.map(function (k) { return BYSVC[k]; });
    if (!hasData(sVals)) { emptyFor(svcCv, 'No services recorded in this range.'); }
    else {
      charts.serviceChart = new Chart(svcCv, {
        type: 'bar',
        data: { labels: sLabels, datasets: [{ label: 'Average attendance', data: sVals,
          backgroundColor: '#662F97', borderRadius: 6, maxBarThickness: 16 }] },
        options: {
          indexAxis: 'y', responsive: true, maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: {
            x: { grid: { color: GRID }, border: { display: false }, beginAtZero: true,
                 title: axis(svcCv.getAttribute('data-axis-x')) },
            y: { grid: { display: false }, border: { display: false }, ticks: { autoSkip: false } }
          }
        }
      });
    }
  }

  var dowCv = $('#dowChart');
  if (dowCv && window.Chart) {
    var dLabels = Object.keys(BYDOW), dVals = dLabels.map(function (k) { return BYDOW[k]; });
    if (!hasData(dVals)) { emptyFor(dowCv, 'No services recorded in this range.'); }
    else {
      charts.dowChart = new Chart(dowCv, {
        type: 'bar',
        data: { labels: dLabels.map(function (d) { return d.slice(0, 3); }),
                datasets: [{ label: 'Average attendance', data: dVals,
                  backgroundColor: dVals.map(function (v) { return v === 0 ? '#E1D9EF' : '#662F97'; }),
                  borderRadius: 6, maxBarThickness: 34 }] },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: xy(dowCv)
        }
      });
    }
  }

  var demogCv = $('#demogChart');
  if (demogCv && window.Chart) {
    var gLabels = Object.keys(DEMOG), gVals = gLabels.map(function (k) { return DEMOG[k]; });
    if (!hasData(gVals)) { emptyFor(demogCv, 'No attendance recorded in this range.'); }
    else {
      charts.demogChart = new Chart(demogCv, {
        type: 'doughnut',
        data: { labels: gLabels, datasets: [{ data: gVals, backgroundColor: PALETTE, borderWidth: 0, hoverOffset: 6 }] },
        options: {
          responsive: true, maintainAspectRatio: false, cutout: '58%',
          plugins: { legend: { display: true, position: 'bottom', labels: { boxWidth: 10, padding: 12, usePointStyle: true } } }
        }
      });
    }
  }

  var bandCv = $('#bandChart');
  if (bandCv && window.Chart) {
    var bLabels = Object.keys(BANDS), bVals = bLabels.map(function (k) { return BANDS[k]; });
    if (!hasData(bVals)) { emptyFor(bandCv, 'No members with attendance in this range.'); }
    else {
      charts.bandChart = new Chart(bandCv, {
        type: 'bar',
        data: { labels: bLabels, datasets: [{ label: 'Members', data: bVals,
          backgroundColor: ['#B4243F', '#B45309', '#D3BAEA', '#8F5CC2', '#15803D'],
          borderRadius: 6, maxBarThickness: 34 }] },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: xy(bandCv)
        }
      });
    }
  }

  var yoyCv = $('#yoyChart');
  if (yoyCv && window.Chart) {
    charts.yoyChart = new Chart(yoyCv, {
      type: 'line',
      data: {
        labels: LABELS,
        datasets: [
          { label: 'This year', data: YOY['this'], borderColor: '#662F97',
            backgroundColor: 'rgba(102,47,151,.1)', fill: true, tension: .35,
            borderWidth: 2, pointRadius: 0, pointHoverRadius: 4 },
          { label: 'Last year', data: YOY['last'], borderColor: '#B48FDA',
            borderDash: [5, 4], fill: false, tension: .35,
            borderWidth: 2, pointRadius: 0, pointHoverRadius: 4 }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { display: true, position: 'bottom', labels: { boxWidth: 10, padding: 12, usePointStyle: true } } },
        scales: xy(yoyCv)
      }
    });
  }

  /* ═════════════════════════ TAB 2 — BY MEMBER ═════════════════════════ */

  var activeCat = null;
  var memberCount = $('[data-member-count]'), memberEmpty = $('#memberEmpty');

  function applyCategory() {
    var shown = 0;
    $$('[data-mrow]').forEach(function (el) {
      var ok = !activeCat || el.getAttribute('data-cat') === activeCat;
      el.hidden = !ok;
      if (ok && el.tagName === 'TR') { shown++; }
    });
    $$('.cattile').forEach(function (t) {
      var on = t.getAttribute('data-cat') === activeCat;
      t.classList.toggle('is-on', on);
      t.setAttribute('aria-pressed', String(on));
    });
    if (memberCount) { memberCount.textContent = shown; }
    if (memberEmpty) { memberEmpty.hidden = shown !== 0; }
  }
  $$('.cattile').forEach(function (t) {
    t.addEventListener('click', function () {
      var cat = t.getAttribute('data-cat');
      activeCat = (activeCat === cat) ? null : cat;
      applyCategory();
      toast(activeCat ? 'Showing ' + activeCat + ' members' : 'Showing all members', 'info');
    });
  });
  $$('[data-clear-cat]').forEach(function (b) {
    b.addEventListener('click', function () { activeCat = null; applyCategory(); });
  });

  /* ═════════════════════════ TAB 3 — BY GROUP ═════════════════════════ */

  var grouping = <?= json_encode($group_default, JSON_UNESCAPED_UNICODE) ?>;

  function band(rate) { return rate >= 75 ? 'good' : (rate >= 40 ? 'fair' : 'poor'); }

  function bar(rate) {
    return '<span class="rbar is-' + band(rate) + '"><span class="rbar__fill" style="width:' +
           Math.max(0, Math.min(100, rate)) + '%"></span></span>';
  }
  function trendHtml(n) {
    if (!n) { return '<span class="num">—</span>'; }
    return '<span class="delta is-' + (n > 0 ? 'up' : 'down') + '"><i class="fa-solid fa-caret-' +
           (n > 0 ? 'up' : 'down') + '" aria-hidden="true"></i> ' + Math.abs(n) + '</span>';
  }

  function renderGroup() {
    var rows = (GROUPS[grouping] || []).slice();
    $$('[data-group-label]').forEach(function (el) { el.textContent = grouping; });
    var cnt = $('[data-group-count]'); if (cnt) { cnt.textContent = rows.length; }

    /* the comparison table */
    var body = $('[data-group-body]'), cards = $('[data-group-cards]');
    body.innerHTML = ''; cards.innerHTML = '';
    rows.forEach(function (r, i) {
      var tr = document.createElement('tr');
      tr.innerHTML =
        '<td class="num">' + (i + 1) + '</td>' +
        '<td class="strong"></td>' +
        '<td class="num">' + r.members.toLocaleString() + '</td>' +
        '<td><span class="bignum">' + r.avg.toLocaleString() + '</span></td>' +
        '<td><span class="ratecell"><b class="is-' + band(r.rate) + '">' + r.rate + '%</b>' + bar(r.rate) + '</span></td>' +
        '<td class="nowrap">' + trendHtml(r.trend) + '</td>' +
        '<td class="nowrap"><span class="monthchip is-best">' + r.best + '</span></td>' +
        '<td class="nowrap"><span class="monthchip is-worst">' + r.worst + '</span></td>';
      tr.children[1].textContent = r.name;
      body.appendChild(tr);

      var card = document.createElement('article');
      card.className = 'pcard';
      card.innerHTML =
        '<button class="pcard__main" type="button" data-card-toggle aria-expanded="false">' +
          '<span class="pcard__text"><span class="pcard__name"></span>' +
          '<span class="pcard__meta">' + r.members + ' members · ' + r.avg + ' average</span></span>' +
          '<i class="fa-solid fa-chevron-down pcard__chev" aria-hidden="true"></i></button>' +
        '<div class="pcard__more">' +
          '<div class="pcard__row"><span>Rate</span><span class="ratecell"><b class="is-' + band(r.rate) + '">' + r.rate + '%</b>' + bar(r.rate) + '</span></div>' +
          '<div class="pcard__row"><span>Trend</span><span>' + trendHtml(r.trend) + '</span></div>' +
          '<div class="pcard__row"><span>Best month</span><span>' + r.best + '</span></div>' +
          '<div class="pcard__row"><span>Worst month</span><span>' + r.worst + '</span></div>' +
        '</div>';
      card.querySelector('.pcard__name').textContent = r.name;
      cards.appendChild(card);
    });

    /* the leaderboard, by rate */
    var board = $('[data-group-board]');
    board.innerHTML = '';
    rows.slice().sort(function (a, b) { return b.rate - a.rate; }).forEach(function (r, i) {
      var li = document.createElement('li');
      li.className = 'rankrow';
      li.innerHTML =
        '<span class="rankrow__pos' + (i < 3 ? ' is-medal is-m' + (i + 1) : '') + '">' +
          (i < 3 ? '<i class="fa-solid fa-medal" aria-hidden="true"></i>' : (i + 1)) + '</span>' +
        '<span class="rankrow__text"><span class="rankrow__name"></span>' +
        '<span class="rankrow__meta">' + r.members + ' members · ' + r.avg + ' average</span></span>' +
        '<span class="ratecell rankrow__rate"><b class="is-' + band(r.rate) + '">' + r.rate + '%</b>' + bar(r.rate) + '</span>';
      li.querySelector('.rankrow__name').textContent = r.name;
      board.appendChild(li);
    });

    /* the grouped bar chart — the top six, so the bars stay legible */
    var top = rows.slice().sort(function (a, b) { return b.avg - a.avg; }).slice(0, 6);
    var cv = $('#groupChart');
    if (cv && window.Chart) {
      if (charts.groupChart) { charts.groupChart.destroy(); }
      charts.groupChart = new Chart(cv, {
        type: 'bar',
        data: {
          labels: MONTHS12,
          datasets: top.map(function (r, i) {
            return { label: r.name, data: r.months,
                     backgroundColor: PALETTE[i % PALETTE.length], borderRadius: 4, maxBarThickness: 12 };
          })
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { legend: { display: true, position: 'bottom', labels: { boxWidth: 10, padding: 12, usePointStyle: true } } },
          scales: xy(cv)
        }
      });
    }
  }

  $$('[data-grouping]').forEach(function (b) {
    b.addEventListener('click', function () {
      grouping = b.getAttribute('data-grouping');
      $$('[data-grouping]').forEach(function (o) {
        var on = o === b;
        o.classList.toggle('is-on', on);
        o.setAttribute('aria-pressed', String(on));
      });
      renderGroup();
    });
  });

  /* ═════════════════════════ TAB 4 — GROWTH ═════════════════════════ */

  var avmCv = $('#avmChart');
  if (avmCv && window.Chart) {
    charts.avmChart = new Chart(avmCv, {
      type: 'line',
      data: {
        labels: AVM.labels,
        datasets: [
          { label: 'Average attendance', data: AVM.att, yAxisID: 'y',
            borderColor: '#662F97', backgroundColor: 'rgba(102,47,151,.1)',
            fill: true, tension: .35, borderWidth: 2, pointRadius: 0, pointHoverRadius: 4 },
          { label: 'Members on the roll', data: AVM.mem, yAxisID: 'y1',
            borderColor: '#0F766E', fill: false, tension: .35, borderWidth: 2,
            pointRadius: 0, pointHoverRadius: 4 }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { display: true, position: 'bottom', labels: { boxWidth: 10, padding: 12, usePointStyle: true } } },
        scales: {
          x: { grid: { display: false }, border: { display: false }, title: axis('Month') },
          y: { position: 'left', grid: { color: GRID }, border: { display: false },
               beginAtZero: true, title: axis('Average attendance') },
          y1: { position: 'right', grid: { display: false }, border: { display: false },
                beginAtZero: true, title: axis('Members on the roll') }
        }
      }
    });
  }

  var projCv = $('#projChart');
  if (projCv && window.Chart) {
    charts.projChart = new Chart(projCv, {
      type: 'line',
      data: {
        labels: PROJ.labels,
        datasets: [
          { label: 'Upper estimate', data: PROJ.high, borderColor: 'rgba(102,47,151,.25)',
            backgroundColor: 'rgba(102,47,151,.10)', fill: '+1', tension: .35,
            borderWidth: 1, pointRadius: 0, borderDash: [4, 4] },
          { label: 'Lower estimate', data: PROJ.low, borderColor: 'rgba(102,47,151,.25)',
            fill: false, tension: .35, borderWidth: 1, pointRadius: 0, borderDash: [4, 4] },
          { label: 'Projected average', data: PROJ.mid, borderColor: '#662F97',
            fill: false, tension: .35, borderWidth: 2, pointRadius: 3, pointBackgroundColor: '#662F97' }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { display: true, position: 'bottom', labels: { boxWidth: 10, padding: 12, usePointStyle: true } } },
        scales: xy(projCv)
      }
    });
  }

  /* ═════════════════════════ tabs ═════════════════════════ */

  $$('[data-rtab]').forEach(function (t) {
    t.addEventListener('click', function () {
      var name = t.getAttribute('data-rtab');
      $$('[data-rtab]').forEach(function (o) {
        o.setAttribute('aria-selected', String(o === t));
      });
      $$('[data-rpanel]').forEach(function (p) { p.hidden = p.getAttribute('data-rpanel') !== name; });
      /* A chart sized while its panel was hidden measures zero. */
      Object.keys(charts).forEach(function (k) { if (charts[k]) { charts[k].resize(); } });
    });

    t.addEventListener('keydown', function (e) {
      var tabs = $$('[data-rtab]'), i = tabs.indexOf(t), n = null;
      if (e.key === 'ArrowRight') { n = tabs[(i + 1) % tabs.length]; }
      if (e.key === 'ArrowLeft')  { n = tabs[(i - 1 + tabs.length) % tabs.length]; }
      if (!n) { return; }
      e.preventDefault(); n.click(); n.focus();
    });
  });

  /* ═════════════════════════ filters ═════════════════════════ */

  var chipsRow = $('[data-filter-chips]'), activeN = $('[data-active-filters]');

  function activeFilters() {
    var f = {};
    $$('[data-filter]').forEach(function (el) {
      var v = (el.value || '').trim();
      if (v && v !== 'All') { f[el.id] = v; }
    });
    return f;
  }

  /* The filters narrow the population every figure is drawn from. With no
     server round-trip the honest demonstration is to scale the charts by how
     much of the roll the selection keeps, and to say so. */
  function filterScale(f) {
    var n = Object.keys(f).length;
    return n === 0 ? 1 : Math.max(0.18, Math.pow(0.62, n));
  }

  var BASE = {};
  function snapshot() {
    Object.keys(charts).forEach(function (k) {
      if (!charts[k]) { return; }
      BASE[k] = charts[k].data.datasets.map(function (d) { return d.data.slice(); });
    });
  }

  function applyFilters(announce) {
    var f = activeFilters(), keys = Object.keys(f), scale = filterScale(f);

    Object.keys(charts).forEach(function (k) {
      var c = charts[k];
      if (!c || !BASE[k]) { return; }
      c.data.datasets.forEach(function (d, i) {
        d.data = BASE[k][i].map(function (v) { return Math.round(v * scale); });
      });
      c.update();
    });

    /* chips */
    chipsRow.innerHTML = '';
    keys.forEach(function (k) {
      var chip = document.createElement('span');
      chip.className = 'fchip';
      chip.innerHTML = '<span></span><button type="button" aria-label="Remove filter"><i class="fa-solid fa-xmark"></i></button>';
      $('span', chip).textContent = f[k];
      $('button', chip).addEventListener('click', function () {
        document.getElementById(k).value = 'All';
        applyFilters(true);
      });
      chipsRow.appendChild(chip);
    });
    chipsRow.hidden = keys.length === 0;
    if (activeN) { activeN.textContent = keys.length; activeN.hidden = keys.length === 0; }

    if (announce) {
      toast(keys.length ? keys.length + ' filter' + (keys.length > 1 ? 's' : '') + ' applied' : 'Filters cleared', 'info');
    }
  }

  var applyBtn = $('#btnApply');
  if (applyBtn) { applyBtn.addEventListener('click', function () { applyFilters(true); }); }
  $$('[data-filter]').forEach(function (el) { el.addEventListener('change', function () { applyFilters(false); }); });
  $$('[data-reset-filters]').forEach(function (b) {
    b.addEventListener('click', function () {
      $$('[data-filter]').forEach(function (el) { el.value = 'All'; });
      applyFilters(true);
    });
  });

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

      var tbody = $('tbody', table), rows = $$('tr', tbody);
      var numeric = ['attended', 'rate', 'longest', 'last'].indexOf(key) !== -1;
      rows.sort(function (a, b) {
        var av = a.getAttribute('data-' + key) || '', bv = b.getAttribute('data-' + key) || '';
        var r = numeric ? (parseFloat(av) - parseFloat(bv)) : String(av).localeCompare(String(bv));
        return asc ? r : -r;
      });
      rows.forEach(function (r) { tbody.appendChild(r); });
      rows.forEach(function (r, i) { var c = $('.num', r); if (c) { c.textContent = i + 1; } });
      toast('Sorted by ' + key, 'info');
    });
  });

  /* stacked cards expand in place */
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-card-toggle]');
    if (!t) { return; }
    t.setAttribute('aria-expanded', String(t.closest('.pcard').classList.toggle('is-open')));
  });

  /* ═════════════════════════ modals ═════════════════════════ */

  function openModal(m) { m.hidden = false; document.body.style.overflow = 'hidden'; $('[data-close]', m).focus(); }
  function closeModal(m) { m.hidden = true; document.body.style.overflow = ''; }

  document.addEventListener('click', function (e) {
    var cl = e.target.closest('[data-close]');
    if (cl) { closeModal(cl.closest('.modal-scrim')); return; }
    if (e.target.classList.contains('modal-scrim')) { closeModal(e.target); }
  }, true);

  /* export */
  var exModal = $('#modalExport');
  $$('[data-export]').forEach(function (b) {
    b.addEventListener('click', function () {
      closeOwnMenu(b);
      var f = b.getAttribute('data-export');
      var r = $('input[name="exFormat"][value="' + f + '"]');
      if (r) { r.checked = true; }
      openModal(exModal);
    });
  }, true);
  var exGo = $('#exGo');
  if (exGo) {
    exGo.addEventListener('click', function () {
      var f = ($('input[name="exFormat"]:checked') || {}).value || 'PDF';
      closeModal(exModal);
      toast(f + ' export started', 'success');
    });
  }

  /* schedule */
  var schModal = $('#modalSchedule');
  if (schModal) {
    $$('[data-open-schedule]').forEach(function (b) {
      b.addEventListener('click', function () { openModal(schModal); });
    });
    $('#schGo').addEventListener('click', function () {
      var n = $$('[data-recipient]').filter(function (c) { return c.checked; }).length;
      var freq = ($('input[name="schFreq"]:checked') || {}).value || 'Monthly';
      closeModal(schModal);
      toast(freq + ' report scheduled for ' + n + ' recipient' + (n === 1 ? '' : 's'), 'success');
    });
  }

  /* chart detail — the same chart, enlarged, with its numbers beneath */
  var zoomModal = $('#modalChart'), zoomChart = null;
  $$('[data-zoom]').forEach(function (b) {
    b.addEventListener('click', function () {
      var id = b.getAttribute('data-zoom'), src = charts[id];
      if (!src) { return; }
      var card = document.getElementById(id).closest('.chartcard');
      $('#zoomTitle').textContent = card ? $('h2', card).textContent : 'Chart';

      /* Built fresh rather than by copying src.options: after construction
         Chart.js replaces the options with resolved proxies, and handing
         those back to a new chart throws. The source is only read for its
         type and its numbers. */
      var cv = document.getElementById(id);
      var isDough = src.config.type === 'doughnut';
      var horiz = src.options && src.options.indexAxis === 'y';

      if (zoomChart) { zoomChart.destroy(); }
      zoomChart = new Chart($('#zoomChart'), {
        type: src.config.type,
        data: {
          labels: src.data.labels.slice(),
          datasets: src.data.datasets.map(function (d) {
            return {
              label: d.label, data: d.data.slice(),
              borderColor: d.borderColor, backgroundColor: d.backgroundColor,
              borderDash: d.borderDash, borderWidth: d.borderWidth,
              fill: d.fill, tension: d.tension,
              borderRadius: d.borderRadius, maxBarThickness: d.maxBarThickness,
              pointRadius: d.pointRadius, pointBackgroundColor: d.pointBackgroundColor
            };
          })
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          indexAxis: horiz ? 'y' : 'x',
          interaction: isDough ? {} : { mode: 'index', intersect: false },
          plugins: {
            legend: {
              display: isDough || src.data.datasets.length > 1,
              position: 'bottom', labels: { boxWidth: 10, padding: 12, usePointStyle: true }
            }
          },
          scales: isDough ? {} : (horiz ? {
            x: { grid: { color: GRID }, border: { display: false }, beginAtZero: true,
                 title: axis(cv.getAttribute('data-axis-x')) },
            y: { grid: { display: false }, border: { display: false }, ticks: { autoSkip: false } }
          } : {
            x: { grid: { display: false }, border: { display: false },
                 title: axis(cv.getAttribute('data-axis-x')) },
            y: { grid: { color: GRID }, border: { display: false }, beginAtZero: true,
                 title: axis(cv.getAttribute('data-axis-y')) }
          })
        }
      });

      /* the underlying numbers */
      var head = $('[data-zoom-head]'), body = $('[data-zoom-body]');
      head.innerHTML = '<tr><th>' + (cv.getAttribute('data-axis-x') || 'Label') + '</th>' +
        src.data.datasets.map(function (d) { return '<th>' + d.label + '</th>'; }).join('') + '</tr>';
      body.innerHTML = '';
      src.data.labels.forEach(function (lab, i) {
        var tr = document.createElement('tr');
        tr.innerHTML = '<td class="strong"></td>' +
          src.data.datasets.map(function (d) {
            return '<td class="num">' + (d.data[i] === null || d.data[i] === undefined ? '—' : Number(d.data[i]).toLocaleString()) + '</td>';
          }).join('');
        tr.children[0].textContent = lab;
        body.appendChild(tr);
      });

      openModal(zoomModal);
      setTimeout(function () { if (zoomChart) { zoomChart.resize(); } }, 60);
    });
  });
  var zd = $('#zoomDownload');
  if (zd) { zd.addEventListener('click', function () { toast('Chart data downloaded', 'success'); }); }

  /* print */
  var printBtn = $('#btnPrint');
  if (printBtn) { printBtn.addEventListener('click', function () { window.print(); }); }

  /* ────────────────────────── escape key ────────────────────────── */
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') { return; }
    $$('.modal-scrim').forEach(function (m) { if (!m.hidden) { closeModal(m); } });
  });

  /* ─────────────────────────── first paint ─────────────────────────── */
  renderGroup();
  snapshot();
  applyCategory();
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/../components/footer.php'; ?>

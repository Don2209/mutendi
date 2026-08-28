<?php
/**
 * Mutendi CMS — Services & Meetings.
 *
 * The setup page behind attendance. Everything the church actually holds is
 * defined here, and record.php's dropdown is built from this same list, so a
 * service added here is immediately something a register can attach to.
 *
 * Three views over one list:
 *   Card Grid        the services as cards, with their shape at a glance
 *   Weekly Schedule  where they sit in the week, overlaps side by side
 *   Table            the dense view, for comparing figures
 *
 * Reading needs the attendance module; changing a definition needs
 * attendance.manage, which is narrower than attendance.edit — a secretary may
 * amend a past register but not redefine what the church holds.
 *
 * UI only. Nothing is written anywhere.
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

/* Shared with the register: one rule for what an attendance rate means, so
   the bars, the bands and the figures can never disagree. */
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

/* Minutes since midnight — the week grid, the durations and the "upcoming"
   ordering all need the same arithmetic. */
if (!function_exists('svc_min')) {
    function svc_min(string $hhmm): int {
        [$h, $m] = array_map('intval', explode(':', $hhmm) + [0, 0]);
        return $h * 60 + $m;
    }

    /** How long a service runs, in minutes, wrapping past midnight. */
    function svc_duration(array $s): int {
        $a = svc_min($s['default_start']);
        $b = svc_min($s['default_end']);
        return $b > $a ? $b - $a : (1440 - $a) + $b;
    }

    function svc_hhmm(int $mins): string {
        return sprintf('%02d:%02d', intdiv($mins, 60) % 24, $mins % 60);
    }

    /** "1h 30m", "45m", "6h". */
    function svc_len(int $mins): string {
        $h = intdiv($mins, 60); $m = $mins % 60;
        if ($h && $m) { return $h . 'h ' . $m . 'm'; }
        return $h ? $h . 'h' : $m . 'm';
    }

    /** The day half of the schedule, whatever the recurrence is. */
    function svc_day(array $s): string {
        if ($s['type'] === 'Monthly') { return $s['day_of_month'] ?? $s['dow']; }
        if ($s['type'] === 'One-off') { return 'By arrangement'; }
        if ($s['type'] === 'Special') { return 'Quarterly, ' . $s['dow']; }
        return 'Every ' . $s['dow'];
    }

    /** The whole schedule line: day, then the times. */
    function svc_when(array $s): string {
        return svc_day($s) . ' · ' . $s['default_start'] . ' – ' . $s['default_end'];
    }

    /**
     * The eight-service sparkline, drawn as a filled line rather than bars.
     * At this size spaced bars read as a loading skeleton; a line is
     * unmistakably a trend. Scaled to the series' own range — attendance at a
     * single service barely varies in absolute terms, so a zero-based plot is
     * a flat wall that shows nothing.
     */
    function svc_spark(array $values, string $label): string
    {
        $real = array_values(array_filter($values, static fn($v) => $v > 0));
        $lo   = $real ? min($real) : 0;
        $hi   = $real ? max($real) : 1;
        $span = max(1, $hi - $lo);
        $n    = max(1, count($values) - 1);

        $pts = [];
        foreach (array_values($values) as $i => $v) {
            $x = round(($i / $n) * 100, 2);
            $y = $v > 0 ? round(24 - (($v - $lo) / $span) * 20, 2) : 24;
            $pts[] = $x . ',' . $y;
        }
        $line = implode(' ', $pts);

        return '<svg class="spark8" viewBox="0 0 100 26" preserveAspectRatio="none" role="img"'
             . ' aria-label="' . htmlspecialchars($label) . '">'
             . '<polygon class="spark8__fill" points="0,26 ' . $line . ' 100,26"></polygon>'
             . '<polyline class="spark8__line" points="' . $line . '"></polyline>'
             . '</svg>';
    }
}

$has_module = mu_mod('attendance');
$can_manage = mu_can('attendance.manage');
$can_add    = mu_can('attendance.add');

/* ─────────────────────────── BRANCH AWARENESS ───────────────────────────
   Which branch's services are in view. Entirely inert for a single church:
   is_multi_branch() is false, so no chip, column or filter is rendered.
   ──────────────────────────────────────────────────────────────────────── */
require_once __DIR__ . '/../components/branch-switcher.php';

$branch_aware   = is_multi_branch();
$viewing_all    = !$branch_aware || $current_branch === 'all' || $current_branch === null;
$show_branch    = $branch_aware && $viewing_all;
$branch_options = $branch_aware ? get_visible_branches() : [];

if (!function_exists('mu_branch_for')) {
    /**
     * Which branch a demo record belongs to. Deterministic from the record's
     * own key, so a service never hops between branches on reload.
     * LATER: the row carries its own branch_id and this helper disappears.
     */
    function mu_branch_for(string $key): ?array {
        static $pool = null;
        if ($pool === null) { $pool = get_visible_branches(); }
        if (!$pool) { return null; }
        return $pool[crc32($key) % count($pool)];
    }
    function mu_branch_tone(array $b): string {
        static $tones = [];
        static $pool = ['var(--info)', 'var(--brand-500)', '#0F766E', 'var(--warn)', '#6D28D9'];
        $g = $b['group_name'] ?? '';
        if (!isset($tones[$g])) { $tones[$g] = $pool[count($tones) % count($pool)]; }
        return $tones[$g];
    }
    function mu_branch_chip(?array $b): string {
        if (!$b) { return ''; }
        return '<span class="bchip" title="' . htmlspecialchars($b['name']) . '">'
             . '<span class="bchip__dot" style="background:' . mu_branch_tone($b) . '" aria-hidden="true"></span>'
             . htmlspecialchars($b['name']) . '</span>';
    }
}

/* ═════════════════════════════ THE SERVICE LIST ═════════════════════════════ */
$services = [];

if ($has_module) {
    foreach ($services_demo as $s) {
        $dur  = svc_duration($s);
        $rate = $s['expected'] > 0 ? ($s['average'] / $s['expected']) * 100 : 0;

        /* A weekly service unseen for a fortnight, or a monthly one for six
           weeks, is overdue — the register has missed something. */
        $limit = $s['type'] === 'Weekly' ? 14 : ($s['type'] === 'Monthly' ? 45 : 120);

        $services[] = $s + [
            'duration' => $dur,
            'len'      => svc_len($dur),
            'rate'     => round($rate, 1),
            'day'      => svc_day($s),
            'when'     => svc_when($s),
            'overdue'  => (int) $s['last_held_days'] > $limit,
            'last_iso' => date('Y-m-d', strtotime('-' . (int) $s['last_held_days'] . ' days')),
            '_branch'  => $branch_aware ? mu_branch_for('svc-' . $s['id']) : null,
        ];
    }
}

if ($branch_aware && !$viewing_all) {
    $services = array_values(array_filter($services, static function ($s) use ($current_branch) {
        return $s['_branch'] && (int) $s['_branch']['id'] === (int) $current_branch;
    }));
}

/* ─────────────────────────── HEADLINE FIGURES ─────────────────────────── */
$weekly  = array_values(array_filter($services, static fn($s) => $s['type'] === 'Weekly'));
$avg_all = $services ? (int) round(array_sum(array_column($services, 'average')) / count($services)) : 0;

$stats = [
    'total'   => count($services),
    'weekly'  => count($weekly),
    'average' => $avg_all,
    /* Everything weekly, plus the monthly services actually due this month. */
    'week'    => count($weekly) + count(array_filter(
        $services, static fn($s) => $s['type'] === 'Monthly' && $s['last_held_days'] > 21
    )),
];

/* ══════════════════════ VIEW B — WEEKLY SCHEDULE ══════════════════════
   Blocks are placed by start time and sized by duration. Where services on
   the same day overlap, each takes a lane so they sit side by side.
   ──────────────────────────────────────────────────────────────────────── */
$DAYS      = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$GRID_FROM = 5 * 60;      /* 05:00 */
$GRID_TO   = 24 * 60;     /* midnight — past this a block is marked continuing */
$SLOT      = 30;          /* one grid row per half hour */
$ROWS      = (int) (($GRID_TO - $GRID_FROM) / $SLOT);

$week = array_fill_keys($DAYS, []);

foreach ($services as $s) {
    if (!$s['active'] || $s['type'] === 'One-off') { continue; }
    if (!isset($week[$s['dow']])) { continue; }

    $start = svc_min($s['default_start']);
    $end   = $start + $s['duration'];

    /* Clamped to the axis; a service running past midnight is flagged so its
       block can say it continues rather than pretending to stop. */
    $from = max($start, $GRID_FROM);
    $to   = min($end, $GRID_TO);
    if ($to <= $from) { continue; }

    $week[$s['dow']][] = $s + [
        'row_start' => (int) floor(($from - $GRID_FROM) / $SLOT) + 1,
        'row_span'  => max(1, (int) round(($to - $from) / $SLOT)),
        'clipped'   => $end > $GRID_TO,
        '_start'    => $start,
        '_end'      => $end,
    ];
}

/* Lane assignment, per overlapping cluster rather than per day: a block takes
   the first lane whose previous occupant has finished, and the width is split
   only between the blocks it actually collides with. A service alone in its
   slot therefore keeps the full column even when the morning was busy. */
foreach ($week as $day => $blocks) {
    usort($blocks, static fn($a, $b) => $a['_start'] <=> $b['_start']);

    $cluster = []; $lane_end = []; $reach = -1;

    foreach ($blocks as $i => $b) {
        $end = min($b['_end'], $GRID_TO);

        if ($cluster && $b['_start'] >= $reach) {
            foreach ($cluster as $j) { $blocks[$j]['lanes'] = max(1, count($lane_end)); }
            $cluster = []; $lane_end = []; $reach = -1;
        }

        $lane = 0;
        while (isset($lane_end[$lane]) && $lane_end[$lane] > $b['_start']) { $lane++; }
        $lane_end[$lane] = $end;

        $blocks[$i]['lane'] = $lane;
        $cluster[] = $i;
        $reach = max($reach, $end);
    }
    foreach ($cluster as $j) { $blocks[$j]['lanes'] = max(1, count($lane_end)); }

    $week[$day] = $blocks;
}

/* Only the services actually on the grid appear in its legend. */
$placed = array_values(array_filter($services, static fn($s) => $s['active'] && $s['type'] !== 'One-off'));

/* ════════════════════════ BOTTOM CARD — UPCOMING ════════════════════════
   The current week, Monday to Sunday, in the order the services happen. The
   week is the window rather than "the next seven days": a card headed "this
   week" that hides Tuesday because Tuesday has passed is no use to someone
   chasing a register that was never taken.
   ──────────────────────────────────────────────────────────────────────── */
$upcoming = [];

if ($has_module) {
    $now       = time();
    $week_from = strtotime('monday this week');

    foreach ($services as $s) {
        if (!$s['active']) { continue; }
        if ($s['type'] === 'One-off' || $s['type'] === 'Special') { continue; }
        if ($s['type'] === 'Monthly' && $s['last_held_days'] < 21) { continue; }

        $day = array_search($s['dow'], $DAYS, true);
        if ($day === false) { continue; }

        $at         = strtotime('+' . $day . ' days ' . $s['default_start'], $week_from);
        $past       = $at < $now;
        $days_since = (int) floor(($now - $at) / 86400);

        $upcoming[] = $s + [
            'at'   => $at,
            'past' => $past,
            /* Past, and the register still has not caught up with it: the
               service was last held longer ago than this occurrence. */
            'unrecorded' => $past && (int) $s['last_held_days'] > $days_since,
        ];
    }
    usort($upcoming, static fn($a, $b) => $a['at'] <=> $b['at']);
}

$member_names = array_column($members_demo, 'name');
sort($member_names);

$page_title = 'Services & Meetings';
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
        <span aria-current="page">Services &amp; Meetings</span>
      </nav>
      <div class="title-row">
        <h1 class="page__title">Services &amp; Meetings</h1>
        <?php if ($has_module): ?>
          <span class="count-chip" data-count="<?= count($services) ?>">0</span>
        <?php endif; ?>
      </div>
      <p class="page__sub">Set up the services and meetings your church holds.</p>
    </div>

    <?php if ($has_module): ?>
      <div class="page__actions">
        <?php if ($can_manage): ?>
          <button class="btn" type="button" data-add-service>
            <i class="fa-solid fa-plus" aria-hidden="true"></i> Add Service
          </button>
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

<?php else: ?>

  <!-- ═════════════════════════════ STAT STRIP ═════════════════════════════ -->
  <div class="stat-strip">
    <?php foreach ([
      ['Total Services',     $stats['total'],   'fa-list-check',    'blue'],
      ['Weekly Services',    $stats['weekly'],  'fa-calendar-week', 'purple'],
      ['Average Attendance', $stats['average'], 'fa-users',         'green'],
      ['Services This Week', $stats['week'],    'fa-calendar-day',  'amber'],
    ] as [$label, $value, $icon, $tone]): ?>
      <div class="stat-tile is-static">
        <span class="stat-tile__icon tone-<?= $tone ?>" aria-hidden="true"><i class="fa-solid <?= $icon ?>"></i></span>
        <span class="stat-tile__body">
          <span class="stat-tile__value" data-count="<?= (int) $value ?>">0</span>
          <span class="stat-tile__label"><?= $label ?></span>
        </span>
      </div>
    <?php endforeach; ?>
  </div>


  <!-- ═════════════════════════ TOOLBAR / VIEW SWITCH ═════════════════════════ -->
  <div class="toolbar">
    <div class="svcviews" role="group" aria-label="View">
      <button class="svcview is-on" type="button" data-svcview="cards" aria-pressed="true">
        <i class="fa-solid fa-table-cells-large" aria-hidden="true"></i> <span>Card Grid</span>
      </button>
      <button class="svcview" type="button" data-svcview="week" aria-pressed="false">
        <i class="fa-regular fa-calendar" aria-hidden="true"></i> <span>Weekly Schedule</span>
      </button>
      <button class="svcview" type="button" data-svcview="table" aria-pressed="false">
        <i class="fa-solid fa-table-list" aria-hidden="true"></i> <span>Table</span>
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
                 placeholder="Service name, venue or person&hellip;">
          <button class="search-field__clear" type="button" data-search-clear hidden aria-label="Clear search">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
          </button>
        </div>
      </div>

      <div class="field">
        <label for="fType">Type</label>
        <select class="select" id="fType" data-filter>
          <option>All</option><option>Weekly</option><option>Monthly</option>
          <option>Special</option><option>One-off</option>
        </select>
      </div>

      <div class="field">
        <label for="fDay">Day of week</label>
        <select class="select" id="fDay" data-filter>
          <option>All</option>
          <?php foreach ($DAYS as $d): ?><option><?= $d ?></option><?php endforeach; ?>
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

      <div class="field">
        <label for="fStatus">Status</label>
        <select class="select" id="fStatus" data-filter>
          <option>All</option><option>Active</option><option>Inactive</option>
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
          <span><span class="sk sk--text" style="width:40%"></span><span class="sk sk--line" style="width:22%"></span></span>
          <span class="sk sk--pill" style="width:110px"></span>
        </div>
      <?php endfor; ?>
    </div>

    <div data-content>

      <!-- ══════════════════ VIEW A — CARD GRID ══════════════════ -->
      <div data-pane="cards">
        <div class="svcgrid stagger">
          <?php foreach ($services as $s): ?>
            <?php $sid = htmlspecialchars($s['id']); ?>
            <article class="svccard<?= $s['active'] ? '' : ' is-off' ?>"
                     style="--c:<?= htmlspecialchars($s['colour']) ?>"
                     data-item data-id="<?= $sid ?>"
                     data-name="<?= htmlspecialchars(mb_strtolower($s['name'])) ?>"
                     data-venue="<?= htmlspecialchars(mb_strtolower($s['venue'])) ?>"
                     data-person="<?= htmlspecialchars(mb_strtolower($s['responsible'])) ?>"
                     data-type="<?= htmlspecialchars($s['type']) ?>"
                     data-day="<?= htmlspecialchars($s['dow']) ?>"
                     data-status="<?= $s['active'] ? 'Active' : 'Inactive' ?>"
                     <?= $show_branch && $s['_branch'] ? 'data-branch="' . htmlspecialchars($s['_branch']['name']) . '"' : '' ?>>

              <!-- The coloured header strip: the service's own colour, its
                   icon, and the one control that governs the whole card. -->
              <header class="svccard__strip">
                <span class="svccard__icon" aria-hidden="true"><i class="fa-solid <?= htmlspecialchars($s['icon']) ?>"></i></span>
                <label class="svccard__switch">
                  <span class="switch switch--strip">
                    <input type="checkbox" data-active-toggle <?= $s['active'] ? 'checked' : '' ?>
                           <?= $can_manage ? '' : 'disabled' ?>
                           aria-label="<?= htmlspecialchars($s['name']) ?> active">
                    <span class="switch__track" aria-hidden="true"></span>
                  </span>
                  <span class="svccard__state" data-active-label><?= $s['active'] ? 'Active' : 'Inactive' ?></span>
                </label>
              </header>

              <div class="svccard__body">
                <h3 class="svccard__name" title="<?= htmlspecialchars($s['name']) ?>"><?= htmlspecialchars($s['name']) ?></h3>

                <p class="svccard__chips">
                  <span class="tchip is-<?= strtolower(str_replace('-', '', $s['type'])) ?>"><?= htmlspecialchars($s['type']) ?></span>
                  <?php if ($show_branch): ?><?= mu_branch_chip($s['_branch']) ?><?php endif; ?>
                </p>

                <ul class="svccard__meta">
                  <li>
                    <i class="fa-regular fa-clock" aria-hidden="true"></i>
                    <span><?= htmlspecialchars($s['when']) ?></span>
                  </li>
                  <li>
                    <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                    <span><?= htmlspecialchars($s['venue']) ?></span>
                  </li>
                </ul>

                <div class="svccard__stat">
                  <p class="svccard__stat-top">
                    <b class="svccard__avg"><?= number_format((int) $s['average']) ?></b>
                    <span class="svccard__of">avg of <?= number_format((int) $s['expected']) ?></span>
                    <span class="svccard__pct is-<?= reg_band((float) $s['rate']) ?>"><?= round($s['rate']) ?>%</span>
                  </p>
                  <?= reg_bar((float) $s['rate']) ?>
                  <?= svc_spark($s['spark'], 'Attendance across the last eight occurrences of ' . $s['name']) ?>
                </div>
              </div>

              <footer class="svccard__foot">
                <button class="svccard__act" type="button" data-open="<?= $sid ?>">
                  <i class="fa-regular fa-eye" aria-hidden="true"></i> View
                </button>
                <?php if ($can_manage): ?>
                  <button class="svccard__act" type="button" data-edit="<?= $sid ?>">
                    <i class="fa-solid fa-pen" aria-hidden="true"></i> Edit
                  </button>
                <?php endif; ?>
                <?php if ($can_add): ?>
                  <a class="svccard__act" href="<?= $base_url ?>attendance/record.php">
                    <i class="fa-solid fa-square-check" aria-hidden="true"></i> Record
                  </a>
                <?php endif; ?>
              </footer>
            </article>
          <?php endforeach; ?>
        </div>
      </div>


      <!-- ══════════════════ VIEW B — WEEKLY SCHEDULE ══════════════════ -->
      <div data-pane="week" hidden>
        <div class="wk">
          <div class="wk__scroll">
            <div class="wk__head">
              <span class="wk__corner" aria-hidden="true"></span>
              <?php foreach ($DAYS as $d): ?>
                <span class="wk__day<?= date('l') === $d ? ' is-today' : '' ?>"><?= substr($d, 0, 3) ?></span>
              <?php endforeach; ?>
            </div>

            <div class="wk__body" style="--rows:<?= $ROWS ?>">
              <div class="wk__times" aria-hidden="true">
                <?php for ($r = 0; $r < $ROWS; $r++): ?>
                  <?php $mins = $GRID_FROM + $r * $SLOT; ?>
                  <span class="wk__time"><?= $mins % 60 === 0 ? svc_hhmm($mins) : '' ?></span>
                <?php endfor; ?>
              </div>

              <?php foreach ($DAYS as $d): ?>
                <div class="wk__col<?= date('l') === $d ? ' is-today' : '' ?>" role="list" aria-label="<?= $d ?>">
                  <?php for ($r = 0; $r < $ROWS; $r++): ?>
                    <span class="wk__cell<?= ($GRID_FROM + $r * $SLOT) % 60 === 0 ? ' is-hour' : '' ?>" aria-hidden="true"></span>
                  <?php endfor; ?>

                  <?php foreach ($week[$d] as $b): ?>
                    <?php
                      /* A block sharing its column has half the width and
                         cannot hold a wrapped name and a time; the time moves
                         to the tooltip and the accessible name there. */
                      $cls = 'wk__block';
                      if ($b['clipped'])       { $cls .= ' is-clipped'; }
                      if ($b['row_span'] <= 3) { $cls .= ' is-short'; }
                      if ($b['lanes'] > 1)     { $cls .= ' is-narrow'; }
                    ?>
                    <button class="<?= $cls ?>" type="button" role="listitem"
                            data-open="<?= htmlspecialchars($b['id']) ?>"
                            style="--r:<?= $b['row_start'] ?>;--span:<?= $b['row_span'] ?>;--lane:<?= $b['lane'] ?>;--lanes:<?= $b['lanes'] ?>;--c:<?= htmlspecialchars($b['colour']) ?>"
                            title="<?= htmlspecialchars($b['name'] . ' · ' . $b['default_start'] . '–' . $b['default_end'] . ' · ' . $b['venue']) ?>">
                      <span class="wk__block-name"><?= htmlspecialchars($b['name']) ?></span>
                      <span class="wk__block-time"><?= htmlspecialchars($b['default_start']) ?><?= $b['clipped'] ? ' →' : '–' . htmlspecialchars($b['default_end']) ?></span>
                    </button>
                  <?php endforeach; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="wk__foot">
            <ul class="wk__legend">
              <?php foreach ($placed as $s): ?>
                <li title="<?= htmlspecialchars($s['name']) ?>">
                  <span class="wk__swatch" style="background:<?= htmlspecialchars($s['colour']) ?>" aria-hidden="true"></span>
                  <?= htmlspecialchars($s['name']) ?>
                </li>
              <?php endforeach; ?>
            </ul>
            <p class="wk__note">Inactive and one-off services are not placed. &ldquo;&rarr;&rdquo; runs past midnight.</p>
          </div>
        </div>
      </div>


      <!-- ══════════════════ VIEW C — TABLE ══════════════════ -->
      <div data-pane="table" hidden>
        <div class="dt-wrap">
          <table class="dt" id="svcTable">
            <thead>
              <tr>
                <th style="width:40px">#</th>
                <th style="width:34px"><input class="check" type="checkbox" data-check-all aria-label="Select all services"></th>
                <th class="is-sortable" data-sort="name">Service <i class="fa-solid fa-sort sort" aria-hidden="true"></i></th>
                <th>Schedule</th>
                <th>Duration</th>
                <th>Venue</th>
                <?php if ($show_branch): ?><th><?= htmlspecialchars(t('branch_singular')) ?></th><?php endif; ?>
                <th class="is-sortable" data-sort="expected">Expected <i class="fa-solid fa-sort sort" aria-hidden="true"></i></th>
                <th class="is-sortable" data-sort="average">Average Actual <i class="fa-solid fa-sort sort" aria-hidden="true"></i></th>
                <th class="is-sortable" data-sort="rate" style="min-width:120px">Rate <i class="fa-solid fa-sort sort" aria-hidden="true"></i></th>
                <th class="is-sortable" data-sort="last">Last Held <i class="fa-solid fa-sort sort" aria-hidden="true"></i></th>
                <th>Status</th>
                <th class="col-actions" style="text-align:right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($services as $i => $s): ?>
                <?php $sid = htmlspecialchars($s['id']); ?>
                <tr data-item data-row data-id="<?= $sid ?>"
                    data-name="<?= htmlspecialchars(mb_strtolower($s['name'])) ?>"
                    data-venue="<?= htmlspecialchars(mb_strtolower($s['venue'])) ?>"
                    data-person="<?= htmlspecialchars(mb_strtolower($s['responsible'])) ?>"
                    data-type="<?= htmlspecialchars($s['type']) ?>"
                    data-day="<?= htmlspecialchars($s['dow']) ?>"
                    data-status="<?= $s['active'] ? 'Active' : 'Inactive' ?>"
                    data-expected="<?= (int) $s['expected'] ?>"
                    data-average="<?= (int) $s['average'] ?>"
                    data-rate="<?= $s['rate'] ?>"
                    data-last="<?= (int) $s['last_held_days'] ?>"
                    <?= $show_branch && $s['_branch'] ? 'data-branch="' . htmlspecialchars($s['_branch']['name']) . '"' : '' ?>>
                  <td class="num"><?= $i + 1 ?></td>
                  <td><input class="check" type="checkbox" data-check aria-label="Select <?= htmlspecialchars($s['name']) ?>"></td>
                  <td class="svccell">
                    <span class="svcname">
                      <span class="svctile" style="--c:<?= htmlspecialchars($s['colour']) ?>" aria-hidden="true">
                        <i class="fa-solid <?= htmlspecialchars($s['icon']) ?>"></i>
                      </span>
                      <?= htmlspecialchars($s['name']) ?>
                    </span>
                    <span class="tsub"><?= htmlspecialchars($s['type']) ?></span>
                  </td>
                  <td class="nowrap">
                    <span class="strong"><?= htmlspecialchars($s['day']) ?></span>
                    <span class="tsub"><?= htmlspecialchars($s['default_start']) ?>&ndash;<?= htmlspecialchars($s['default_end']) ?></span>
                  </td>
                  <td class="nowrap"><?= htmlspecialchars($s['len']) ?></td>
                  <td class="svccol-venue" title="<?= htmlspecialchars($s['venue']) ?>"><?= htmlspecialchars($s['venue']) ?></td>
                  <?php if ($show_branch): ?><td><?= mu_branch_chip($s['_branch']) ?></td><?php endif; ?>
                  <td class="num"><?= number_format((int) $s['expected']) ?></td>
                  <td><span class="bignum"><?= number_format((int) $s['average']) ?></span></td>
                  <td>
                    <span class="ratecell">
                      <b class="is-<?= reg_band((float) $s['rate']) ?>"><?= round($s['rate']) ?>%</b>
                      <?= reg_bar((float) $s['rate']) ?>
                    </span>
                  </td>
                  <td class="nowrap<?= $s['overdue'] ? ' is-overdue' : '' ?>">
                    <?= mu_ago((int) $s['last_held_days']) ?>
                    <?php if ($s['overdue']): ?>
                      <i class="fa-solid fa-triangle-exclamation" title="Overdue" aria-label="Overdue"></i>
                    <?php endif; ?>
                  </td>
                  <td><span class="pill <?= $s['active'] ? 'tone-ok' : 'tone-grey' ?>"><?= $s['active'] ? 'Active' : 'Inactive' ?></span></td>
                  <td class="col-actions">
                    <div class="rowacts">
                      <button class="iconbtn" type="button" data-open="<?= $sid ?>" aria-label="View <?= htmlspecialchars($s['name']) ?>">
                        <i class="fa-regular fa-eye" aria-hidden="true"></i>
                      </button>
                      <?php if ($can_manage): ?>
                        <button class="iconbtn" type="button" data-edit="<?= $sid ?>" aria-label="Edit <?= htmlspecialchars($s['name']) ?>">
                          <i class="fa-solid fa-pen" aria-hidden="true"></i>
                        </button>
                      <?php endif; ?>
                      <?php if ($can_add): ?>
                        <a class="iconbtn" href="<?= $base_url ?>attendance/record.php" aria-label="Record attendance for <?= htmlspecialchars($s['name']) ?>">
                          <i class="fa-solid fa-square-check" aria-hidden="true"></i>
                        </a>
                      <?php endif; ?>

                      <div class="drop" data-menu>
                        <button class="iconbtn" type="button" aria-haspopup="true" aria-expanded="false" data-menu-btn aria-label="More actions">
                          <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                        </button>
                        <div class="menu menu--end" data-menu-panel hidden>
                          <button class="menu__item" type="button" data-open="<?= $sid ?>"><i class="fa-regular fa-eye" aria-hidden="true"></i> View Details</button>
                          <?php if ($can_manage): ?>
                            <button class="menu__item" type="button" data-edit="<?= $sid ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</button>
                            <button class="menu__item" type="button" data-duplicate="<?= $sid ?>"><i class="fa-regular fa-copy" aria-hidden="true"></i> Duplicate</button>
                          <?php endif; ?>
                          <?php if ($can_add): ?>
                            <a class="menu__item" href="<?= $base_url ?>attendance/record.php"><i class="fa-solid fa-square-check" aria-hidden="true"></i> Record Attendance</a>
                          <?php endif; ?>
                          <a class="menu__item" href="<?= $base_url ?>attendance/register.php"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> View History</a>
                          <?php if ($can_manage): ?>
                            <button class="menu__item" type="button" data-deactivate="<?= $sid ?>">
                              <i class="fa-solid fa-power-off" aria-hidden="true"></i> <?= $s['active'] ? 'Deactivate' : 'Activate' ?>
                            </button>
                            <button class="menu__item is-danger" type="button" data-delete="<?= $sid ?>"><i class="fa-regular fa-trash-can" aria-hidden="true"></i> Delete</button>
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
            <?php $sid = htmlspecialchars($s['id']); ?>
            <article class="pcard" data-item data-card data-id="<?= $sid ?>"
                     data-name="<?= htmlspecialchars(mb_strtolower($s['name'])) ?>"
                     data-venue="<?= htmlspecialchars(mb_strtolower($s['venue'])) ?>"
                     data-person="<?= htmlspecialchars(mb_strtolower($s['responsible'])) ?>"
                     data-type="<?= htmlspecialchars($s['type']) ?>"
                     data-day="<?= htmlspecialchars($s['dow']) ?>"
                     data-status="<?= $s['active'] ? 'Active' : 'Inactive' ?>"
                     <?= $show_branch && $s['_branch'] ? 'data-branch="' . htmlspecialchars($s['_branch']['name']) . '"' : '' ?>>
              <button class="pcard__main" type="button" data-card-toggle aria-expanded="false">
                <span class="svctile" style="--c:<?= htmlspecialchars($s['colour']) ?>" aria-hidden="true">
                  <i class="fa-solid <?= htmlspecialchars($s['icon']) ?>"></i>
                </span>
                <span class="pcard__text">
                  <span class="pcard__name"><?= htmlspecialchars($s['name']) ?></span>
                  <span class="pcard__meta"><?= htmlspecialchars($s['type']) ?> &middot; <?= htmlspecialchars($s['dow']) ?> <?= htmlspecialchars($s['default_start']) ?></span>
                </span>
                <i class="fa-solid fa-chevron-down pcard__chev" aria-hidden="true"></i>
              </button>
              <div class="pcard__more">
                <div class="pcard__row"><span>Venue</span><span><?= htmlspecialchars($s['venue']) ?></span></div>
                <div class="pcard__row"><span>Duration</span><span><?= htmlspecialchars($s['len']) ?></span></div>
                <div class="pcard__row"><span>Expected</span><span><?= number_format((int) $s['expected']) ?></span></div>
                <div class="pcard__row"><span>Average</span><span class="strong"><?= number_format((int) $s['average']) ?></span></div>
                <div class="pcard__row"><span>Rate</span><span class="ratecell"><b class="is-<?= reg_band((float) $s['rate']) ?>"><?= round($s['rate']) ?>%</b><?= reg_bar((float) $s['rate']) ?></span></div>
                <div class="pcard__row"><span>Last held</span><span<?= $s['overdue'] ? ' class="is-overdue"' : '' ?>><?= mu_ago((int) $s['last_held_days']) ?></span></div>
                <div class="pcard__row"><span>Status</span><span class="pill <?= $s['active'] ? 'tone-ok' : 'tone-grey' ?>"><?= $s['active'] ? 'Active' : 'Inactive' ?></span></div>
                <?php if ($show_branch && $s['_branch']): ?>
                  <div class="pcard__row"><span><?= htmlspecialchars(t('branch_singular')) ?></span><span><?= mu_branch_chip($s['_branch']) ?></span></div>
                <?php endif; ?>
                <div class="pcard__acts">
                  <button class="btn btn--ghost btn--sm" type="button" data-open="<?= $sid ?>"><i class="fa-regular fa-eye" aria-hidden="true"></i> View</button>
                  <?php if ($can_manage): ?>
                    <button class="btn btn--ghost btn--sm" type="button" data-edit="<?= $sid ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</button>
                    <button class="btn btn--ghost btn--sm" type="button" data-delete="<?= $sid ?>"><i class="fa-regular fa-trash-can" aria-hidden="true"></i> Delete</button>
                  <?php endif; ?>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>

      <?php if (!$services): ?>
        <!-- Nothing to filter in the first place: a branch, or a church, that
             has not defined any services yet. "No match" would be untrue. -->
        <div class="empty">
          <span class="empty__icon" aria-hidden="true"><i class="fa-regular fa-calendar-plus"></i></span>
          <h3><?= $branch_aware && !$viewing_all
                ? htmlspecialchars(current_branch_name()) . ' has no services yet'
                : 'No services set up yet' ?></h3>
          <p>Attendance can only be recorded against a service. Add the first one and it appears on the recorder straight away.</p>
          <?php if ($can_manage): ?>
            <button class="btn" type="button" data-add-service>
              <i class="fa-solid fa-plus" aria-hidden="true"></i> Add Service
            </button>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="empty" id="listEmpty" hidden>
        <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-filter-circle-xmark"></i></span>
        <h3>No services match those filters</h3>
        <p>Try a different type or day, or clear the filters to see everything the church holds.</p>
        <button class="btn btn--ghost" type="button" data-reset-filters>
          <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Reset filters
        </button>
      </div>
    </div>
  </section>


  <!-- ═══════════════════════ BOTTOM — UPCOMING THIS WEEK ═══════════════════════ -->
  <section class="panel" style="margin-top:16px">
    <div class="panel__head">
      <h2>Upcoming This Week</h2>
      <span class="pill tone-brand"><?= count($upcoming) ?></span>
    </div>

    <?php if (!$upcoming): ?>
      <div class="card__empty">
        <i class="fa-regular fa-calendar-check" aria-hidden="true"></i>
        <p>Nothing scheduled in the next seven days.</p>
      </div>
    <?php else: ?>
      <ul class="upnext">
        <?php foreach ($upcoming as $u): ?>
          <li class="uprow<?= $u['past'] ? ' is-past' : '' ?>">
            <span class="uprow__when">
              <b><?= date('D', $u['at']) ?></b>
              <span><?= date('d M', $u['at']) ?></span>
            </span>
            <span class="uprow__time"><?= htmlspecialchars($u['default_start']) ?></span>
            <span class="uprow__text">
              <span class="uprow__name">
                <span class="uprow__dot" style="background:<?= htmlspecialchars($u['colour']) ?>" aria-hidden="true"></span>
                <?= htmlspecialchars($u['name']) ?>
              </span>
              <span class="uprow__meta" title="<?= htmlspecialchars($u['venue']) ?>">
                <?= htmlspecialchars($u['venue']) ?> &middot; <?= number_format((int) $u['expected']) ?> expected
              </span>
            </span>
            <?php /* Only a row that wants something gets a control; a column
                     of identical "Upcoming" pills says nothing. */ ?>
            <?php if ($u['unrecorded'] && $can_add): ?>
              <a class="btn btn--sm" href="<?= $base_url ?>attendance/record.php">Record</a>
            <?php elseif ($u['past']): ?>
              <span class="uprow__done" title="Recorded">
                <i class="fa-solid fa-check" aria-hidden="true"></i>
                <span class="u-sr">Recorded</span>
              </span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

<?php endif; ?>

</div><!-- /.page -->


<?php if ($has_module): ?>

<!-- ══════════════════════ SERVICE DETAIL DRAWER ══════════════════════ -->
<div class="drawer-scrim" data-drawer-scrim hidden></div>

<aside class="drawer" id="svcDrawer" role="dialog" aria-modal="true" aria-labelledby="dName" hidden>
  <!-- Coloured header, matching the card it was opened from. -->
  <header class="drawer__head svcdhead" data-d-head>
    <span class="svcdhead__icon" aria-hidden="true"><i class="fa-solid fa-church" data-d-icon></i></span>
    <div class="svcdhead__title">
      <h2 id="dName">Service</h2>
      <p data-d-type>—</p>
    </div>
    <button class="svcdhead__close" type="button" data-drawer-close aria-label="Close">
      <i class="fa-solid fa-xmark" aria-hidden="true"></i>
    </button>
  </header>

  <div class="drawer__body">
    <dl class="deflist">
      <div><dt>Schedule</dt><dd data-d-when>—</dd></div>
      <div><dt>Venue</dt><dd data-d-venue>—</dd></div>
      <div><dt>Responsible</dt><dd data-d-person>—</dd></div>
    </dl>

    <div class="tabs" role="tablist" aria-label="Service detail">
      <button type="button" role="tab" aria-selected="true"  data-dtab="overview">Overview</button>
      <button type="button" role="tab" aria-selected="false" data-dtab="history">Attendance History</button>
      <button type="button" role="tab" aria-selected="false" data-dtab="settings">Settings</button>
    </div>

    <div class="tabpanel" data-dpanel="overview" role="tabpanel">
      <p class="minilist__head">Description</p>
      <p class="drawer__prose" data-d-desc>—</p>
      <p class="minilist__head">Notes</p>
      <p class="drawer__prose" data-d-notes>—</p>

      <div class="svcmeter">
        <p class="svcmeter__top">
          <span>Average attendance <b data-d-avg>0</b></span>
          <span class="num">of <span data-d-exp>0</span> expected</span>
        </p>
        <span class="rbar" data-d-bar><span class="rbar__fill" style="width:0%"></span></span>
      </div>
    </div>

    <div class="tabpanel" data-dpanel="history" role="tabpanel" hidden>
      <div class="svcchart"><canvas id="dChart" aria-hidden="true"></canvas></div>
      <div class="dt-wrap" style="margin-top:14px">
        <table class="dt" style="font-size:12.5px">
          <thead><tr><th>Date</th><th>Present</th><th>Expected</th><th>Rate</th></tr></thead>
          <tbody data-d-history></tbody>
        </table>
      </div>
    </div>

    <div class="tabpanel" data-dpanel="settings" role="tabpanel" hidden>
      <dl class="deflist">
        <div><dt>Recurrence</dt><dd data-d-recur>—</dd></div>
        <div><dt>Track individual attendance</dt><dd data-d-track>—</dd></div>
        <?php if (mu_mod('finance')): ?>
          <div><dt>Record offering</dt><dd data-d-offering>—</dd></div>
        <?php endif; ?>
        <div><dt>Status</dt><dd data-d-status>—</dd></div>
        <div><dt>Last held</dt><dd data-d-last>—</dd></div>
        <?php if ($show_branch): ?>
          <div><dt><?= htmlspecialchars(t('branch_singular')) ?></dt><dd data-d-branch>—</dd></div>
        <?php endif; ?>
      </dl>
    </div>
  </div>

  <footer class="drawer__foot">
    <?php if ($can_manage): ?>
      <button class="btn btn--ghost" type="button" id="dEdit">
        <i class="fa-solid fa-pen" aria-hidden="true"></i> Edit
      </button>
    <?php endif; ?>
    <?php if ($can_add): ?>
      <a class="btn" href="<?= $base_url ?>attendance/record.php">
        <i class="fa-solid fa-square-check" aria-hidden="true"></i> Record Attendance
      </a>
    <?php endif; ?>
  </footer>
</aside>


<?php if ($can_manage): ?>
<!-- ══════════════════════ ADD / EDIT SERVICE MODAL ══════════════════════ -->
<div class="modal-scrim" id="modalService" hidden>
  <div class="modal modal--wide" role="dialog" aria-modal="true" aria-labelledby="msTitle">
    <header class="modal__head">
      <h2 id="msTitle">Add Service</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>

    <div class="modal__body">

      <p class="modal__group">Basics</p>
      <div class="form-grid">
        <div class="field col-2">
          <label for="msName">Service name <span class="req">*</span></label>
          <input class="input" type="text" id="msName" placeholder="e.g. Sunday First Service" autocomplete="off">
          <p class="err">Give the service a name.</p>
        </div>
        <div class="field col-2">
          <label for="msDesc">Description</label>
          <textarea class="textarea" id="msDesc" rows="2" placeholder="What happens at this service?"></textarea>
        </div>
      </div>

      <p class="modal__group">Type</p>
      <div class="radio-cards" id="msType">
        <?php foreach ([
          ['Weekly',  'fa-calendar-week', 'Same day every week'],
          ['Monthly', 'fa-calendar-days', 'Once a month'],
          ['Special', 'fa-star',          'Occasional, as needed'],
          ['One-off', 'fa-calendar-day',  'A single dated event'],
        ] as $i => [$label, $icon, $hint]): ?>
          <label class="rcard">
            <input type="radio" name="msType" value="<?= $label ?>" <?= $i === 0 ? 'checked' : '' ?>>
            <span class="rcard__box">
              <i class="fa-solid <?= $icon ?>" aria-hidden="true"></i>
              <span>
                <strong><?= $label ?></strong>
                <span class="hint" style="margin:2px 0 0"><?= $hint ?></span>
              </span>
            </span>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="form-grid" style="margin-top:16px">
        <div class="field">
          <label>Icon</label>
          <div class="pickgrid" id="msIcons" role="radiogroup" aria-label="Service icon">
            <?php foreach ($service_icons_demo as $i => $ic): ?>
              <button class="pick pick--icon<?= $i === 0 ? ' is-on' : '' ?>" type="button"
                      role="radio" aria-checked="<?= $i === 0 ? 'true' : 'false' ?>"
                      data-icon="<?= htmlspecialchars($ic) ?>" aria-label="<?= htmlspecialchars(str_replace('fa-', '', $ic)) ?>">
                <i class="fa-solid <?= htmlspecialchars($ic) ?>" aria-hidden="true"></i>
              </button>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="field">
          <label>Colour</label>
          <div class="pickgrid" id="msColours" role="radiogroup" aria-label="Service colour">
            <?php foreach ($service_colours_demo as $i => $col): ?>
              <button class="pick pick--colour<?= $i === 0 ? ' is-on' : '' ?>" type="button"
                      role="radio" aria-checked="<?= $i === 0 ? 'true' : 'false' ?>"
                      data-colour="<?= htmlspecialchars($col) ?>" style="--c:<?= htmlspecialchars($col) ?>"
                      aria-label="Colour <?= htmlspecialchars($col) ?>"></button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <p class="modal__group">Schedule</p>
      <div class="form-grid">
        <!-- Which recurrence control shows depends on the type chosen above. -->
        <div class="field" data-recur="Weekly">
          <label for="msDow">Day of week</label>
          <select class="select" id="msDow">
            <?php foreach ($DAYS as $d): ?><option><?= $d ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field" data-recur="Monthly" hidden>
          <label for="msDom">Day of month</label>
          <select class="select" id="msDom">
            <?php foreach (['First', 'Second', 'Third', 'Fourth', 'Last'] as $ord): ?>
              <?php foreach ($DAYS as $d): ?><option><?= $ord ?> <?= $d ?></option><?php endforeach; ?>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field" data-recur="One-off" hidden>
          <label for="msDate">Date</label>
          <input class="input" type="date" id="msDate">
        </div>
        <div class="field" data-recur="Special" hidden>
          <label for="msFreq">How often</label>
          <select class="select" id="msFreq">
            <option>Quarterly</option><option>Twice a year</option><option>Annually</option><option>As needed</option>
          </select>
        </div>

        <div class="field"><label for="msStart">Start time</label><input class="input" type="time" id="msStart" value="09:00"></div>
        <div class="field"><label for="msEnd">End time</label><input class="input" type="time" id="msEnd" value="11:00"></div>

        <div class="field">
          <label for="msVenue">Venue</label>
          <input class="input" type="text" id="msVenue" list="venueList" placeholder="Where it is held" autocomplete="off">
          <datalist id="venueList">
            <?php foreach ($service_venues_demo as $v): ?><option value="<?= htmlspecialchars($v) ?>"></option><?php endforeach; ?>
          </datalist>
        </div>

        <?php if ($branch_aware): ?>
          <div class="field">
            <label for="msBranch"><?= htmlspecialchars(t('branch_singular')) ?></label>
            <select class="select" id="msBranch">
              <?php foreach ($branch_options as $b): ?>
                <option value="<?= (int) $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <div class="field">
          <label for="msPerson">Responsible person</label>
          <input class="input" type="text" id="msPerson" list="peopleList" placeholder="Pick a member" autocomplete="off">
          <datalist id="peopleList">
            <?php foreach ($member_names as $n): ?><option value="<?= htmlspecialchars($n) ?>"></option><?php endforeach; ?>
          </datalist>
        </div>

        <div class="field">
          <label for="msExpected">Expected attendance</label>
          <input class="input" type="number" id="msExpected" min="0" max="99999" value="100">
        </div>
      </div>

      <p class="modal__group">Options</p>
      <div class="modal__list">
        <div class="modal__row">
          <i class="fa-solid fa-list-check modal__row-icon" aria-hidden="true"></i>
          <span class="modal__row-label">Track individual attendance</span>
          <span class="switch"><input type="checkbox" id="msTrack" checked><span class="switch__track" aria-hidden="true"></span></span>
        </div>
        <?php if (mu_mod('finance')): ?>
          <div class="modal__row">
            <i class="fa-solid fa-hand-holding-dollar modal__row-icon" aria-hidden="true"></i>
            <span class="modal__row-label">Record offering</span>
            <span class="switch"><input type="checkbox" id="msOffering" checked><span class="switch__track" aria-hidden="true"></span></span>
          </div>
        <?php endif; ?>
        <div class="modal__row">
          <i class="fa-solid fa-power-off modal__row-icon" aria-hidden="true"></i>
          <span class="modal__row-label">Active</span>
          <span class="switch"><input type="checkbox" id="msActive" checked><span class="switch__track" aria-hidden="true"></span></span>
        </div>
      </div>
    </div>

    <footer class="modal__foot">
      <span class="modal__spacer"></span>
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <button class="btn" type="button" id="msGo">
        <i class="fa-solid fa-check" aria-hidden="true"></i> <span id="msGoLabel">Save Service</span>
      </button>
    </footer>
  </div>
</div>


<!-- ══════════════════════════ DELETE MODAL ══════════════════════════ -->
<div class="modal-scrim" id="modalDelete" hidden>
  <div class="modal modal--sm" role="dialog" aria-modal="true" aria-labelledby="delTitle">
    <header class="modal__head">
      <h2 id="delTitle">Delete this service?</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>

    <div class="modal__body">
      <div class="at-notice at-notice--danger" role="note" style="margin-bottom:14px">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        <div class="at-notice__body">
          <strong>This cannot be undone</strong>
          <span>
            <strong data-del-name>This service</strong> will stop appearing on the recorder and the schedule.
            Attendance already recorded against it is kept, so the register and every member's history stay intact.
          </span>
        </div>
      </div>

      <div class="field">
        <label for="delConfirm">Type <strong data-del-echo>the service name</strong> to confirm</label>
        <input class="input" type="text" id="delConfirm" placeholder="Service name" autocomplete="off">
      </div>
    </div>

    <footer class="modal__foot">
      <span class="modal__spacer"></span>
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <button class="btn btn--danger" type="button" id="delGo" disabled>
        <i class="fa-regular fa-trash-can" aria-hidden="true"></i> Delete Service
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

<?php if ($has_module): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
/* Services & Meetings — view switching, filtering, sorting, the detail drawer
   and the add/edit/delete flows. All client-side. */
(function () {
  'use strict';

  var still      = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var CAN_MANAGE = <?= $can_manage ? 'true' : 'false' ?>;

  var SERVICES = <?= json_encode(array_map(static function ($s) {
        return [
            'id' => $s['id'], 'name' => $s['name'], 'type' => $s['type'],
            'icon' => $s['icon'], 'colour' => $s['colour'], 'dow' => $s['dow'],
            'start' => $s['default_start'], 'end' => $s['default_end'],
            'venue' => $s['venue'], 'person' => $s['responsible'],
            'expected' => (int) $s['expected'], 'average' => (int) $s['average'],
            'rate' => $s['rate'], 'active' => (bool) $s['active'],
            'last' => (int) $s['last_held_days'], 'overdue' => (bool) $s['overdue'],
            'len' => $s['len'], 'when' => $s['when'], 'day' => $s['day'],
            'desc' => $s['description'], 'notes' => $s['notes'],
            'track' => (bool) $s['track_individual'], 'offering' => (bool) $s['record_offering'],
            'dom' => $s['day_of_month'] ?? null, 'spark' => array_values($s['spark']),
            'branch' => $s['_branch']['name'] ?? null,
        ];
    }, $services), JSON_UNESCAPED_UNICODE) ?>;

  var $  = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };

  var byId = {};
  SERVICES.forEach(function (s) { byId[s.id] = s; });

  /* footer.php stops propagation on clicks inside a [data-menu-panel], so the
     delegated handlers below capture rather than bubble, and each closes its
     own menu — otherwise a menu whose item did something would stay open. */
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

  var view = 'cards';
  function setView(next) {
    view = next;
    $$('[data-svcview]').forEach(function (b) {
      var on = b.getAttribute('data-svcview') === next;
      b.classList.toggle('is-on', on);
      b.setAttribute('aria-pressed', String(on));
    });
    $$('[data-pane]').forEach(function (p) { p.hidden = p.getAttribute('data-pane') !== next; });
    apply();
  }
  $$('[data-svcview]').forEach(function (b) {
    b.addEventListener('click', function () { setView(b.getAttribute('data-svcview')); });
  });

  /* ═════════════════════════ filtering ═════════════════════════ */

  var search   = $('#fSearch'),
      clearBtn = $('[data-search-clear]'),
      chipsRow = $('[data-filter-chips]'),
      activeN  = $('[data-active-filters]'),
      resultN  = $('[data-result-count]'),
      emptyState = $('#listEmpty');

  function activeFilters() {
    var f = {};
    if (search && search.value.trim()) { f.q = search.value.trim(); }
    $$('[data-filter]').forEach(function (el) {
      var v = (el.value || '').trim();
      if (v && v !== 'All') { f[el.id] = v; }
    });
    return f;
  }

  /* One rule, fed either from a DOM node's data attributes or from the
     SERVICES record behind a week block, so all three views agree. */
  function matchesData(d, f) {
    if (f.q) {
      var q = f.q.toLowerCase();
      if ([d.name, d.venue, d.person].join(' ').toLowerCase().indexOf(q) === -1) { return false; }
    }
    if (f.fType   && d.type   !== f.fType)   { return false; }
    if (f.fDay    && d.day    !== f.fDay)    { return false; }
    if (f.fBranch && d.branch !== f.fBranch) { return false; }
    if (f.fStatus && d.status !== f.fStatus) { return false; }
    return true;
  }

  function matches(el, f) {
    return matchesData({
      name:   el.getAttribute('data-name')   || '',
      venue:  el.getAttribute('data-venue')  || '',
      person: el.getAttribute('data-person') || '',
      type:   el.getAttribute('data-type'),
      day:    el.getAttribute('data-day'),
      branch: el.getAttribute('data-branch'),
      status: el.getAttribute('data-status')
    }, f);
  }

  function apply() {
    var f = activeFilters(), shown = 0;

    /* Cards, table rows and stacked cards all carry data-item; only the table
       rows are counted, so one service is not counted three times. */
    $$('[data-item]').forEach(function (el) {
      var ok = matches(el, f);
      el.hidden = !ok;
      if (ok && el.hasAttribute('data-row')) { shown++; }
    });

    var wkShown = 0;
    $$('.wk__block').forEach(function (b) {
      var s = byId[b.getAttribute('data-open')];
      if (!s) { return; }
      var ok = matchesData({
        name: s.name, venue: s.venue, person: s.person, type: s.type,
        day: s.dow, branch: s.branch, status: s.active ? 'Active' : 'Inactive'
      }, f);
      b.hidden = !ok;
      if (ok) { wkShown++; }
    });

    if (view === 'week') { shown = wkShown; }
    /* Silent when the list is empty for a reason other than the filters —
       the server-rendered empty state already explains that case. */
    emptyState.hidden = shown !== 0 || view === 'week' || SERVICES.length === 0;
    if (resultN) { resultN.textContent = shown; }

    /* removable chips */
    var keys = Object.keys(f);
    chipsRow.innerHTML = '';
    keys.forEach(function (k) {
      var chip = document.createElement('span');
      chip.className = 'fchip';
      chip.innerHTML = '<span></span><button type="button" aria-label="Remove filter"><i class="fa-solid fa-xmark"></i></button>';
      $('span', chip).textContent = k === 'q' ? 'Search: ' + f[k] : f[k];
      $('button', chip).addEventListener('click', function () {
        if (k === 'q') { search.value = ''; } else { document.getElementById(k).value = 'All'; }
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
  $$('[data-filter]').forEach(function (el) { el.addEventListener('change', apply); });

  $$('[data-reset-filters]').forEach(function (b) {
    b.addEventListener('click', function () {
      if (search) { search.value = ''; }
      $$('[data-filter]').forEach(function (el) { el.value = 'All'; });
      apply();
      toast('Filters cleared', 'info');
    });
  });

  var fToggle = $('#filtersToggle');
  if (fToggle) {
    fToggle.addEventListener('click', function () {
      var open = $('#filters').classList.toggle('is-open');
      fToggle.setAttribute('aria-expanded', String(open));
    });
  }

  /* ═════════════════════════ table: sort and select ═════════════════════════ */

  $$('.dt th.is-sortable').forEach(function (th) {
    th.addEventListener('click', function () {
      var key = th.getAttribute('data-sort');
      var asc = th.getAttribute('aria-sort') !== 'ascending';
      var table = th.closest('table');
      $$('th', table).forEach(function (o) { o.removeAttribute('aria-sort'); });
      th.setAttribute('aria-sort', asc ? 'ascending' : 'descending');

      var tbody = $('tbody', table), rows = $$('tr', tbody);
      var numeric = ['expected', 'average', 'rate', 'last'].indexOf(key) !== -1;
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

  var checkAll = $('[data-check-all]');
  if (checkAll) {
    checkAll.addEventListener('change', function () {
      $$('[data-check]').forEach(function (c) {
        if (!c.closest('tr').hidden) { c.checked = checkAll.checked; }
      });
    });
    $$('[data-check]').forEach(function (c) {
      c.addEventListener('change', function () {
        var all = $$('[data-check]').filter(function (x) { return !x.closest('tr').hidden; });
        checkAll.checked = all.length > 0 && all.every(function (x) { return x.checked; });
        checkAll.indeterminate = !checkAll.checked && all.some(function (x) { return x.checked; });
      });
    });
  }

  /* stacked cards expand in place */
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-card-toggle]');
    if (!t) { return; }
    t.setAttribute('aria-expanded', String(t.closest('.pcard').classList.toggle('is-open')));
  });

  /* the per-card active toggle */
  $$('[data-active-toggle]').forEach(function (input) {
    input.addEventListener('change', function () {
      var card = input.closest('.svccard');
      var on = input.checked;
      card.classList.toggle('is-off', !on);
      card.setAttribute('data-status', on ? 'Active' : 'Inactive');
      $('[data-active-label]', card).textContent = on ? 'Active' : 'Inactive';
      toast(byId[card.getAttribute('data-id')].name + (on ? ' activated' : ' deactivated'), on ? 'success' : 'info');
      apply();
    });
  });

  /* ═════════════════════════ the detail drawer ═════════════════════════ */

  var scrim = $('[data-drawer-scrim]'),
      drawer = $('#svcDrawer'),
      chart = null, current = null, lastFocus = null;

  function openDrawer() {
    lastFocus = document.activeElement;
    drawer.hidden = false; scrim.hidden = false;
    document.body.style.overflow = 'hidden';
    $('[data-drawer-close]', drawer).focus();
  }
  function closeDrawer() {
    drawer.hidden = true; scrim.hidden = true;
    document.body.style.overflow = '';
    if (lastFocus) { lastFocus.focus(); lastFocus = null; }
  }
  scrim.addEventListener('click', closeDrawer);
  $$('[data-drawer-close]').forEach(function (b) { b.addEventListener('click', closeDrawer); });

  function setTab(name) {
    $$('[data-dtab]').forEach(function (t) {
      t.setAttribute('aria-selected', String(t.getAttribute('data-dtab') === name));
    });
    $$('[data-dpanel]').forEach(function (p) { p.hidden = p.getAttribute('data-dpanel') !== name; });
    if (name === 'history') { drawChart(); }
  }
  $$('[data-dtab]').forEach(function (t) {
    t.addEventListener('click', function () { setTab(t.getAttribute('data-dtab')); });
  });

  function drawChart() {
    if (!current) { return; }
    if (chart) { chart.destroy(); }
    chart = new Chart($('#dChart'), {
      type: 'line',
      data: {
        labels: current.spark.map(function (_, i) { return '#' + (i + 1); }),
        datasets: [{
          data: current.spark, borderColor: current.colour, backgroundColor: current.colour + '22',
          borderWidth: 2, fill: true, tension: .35, pointRadius: 3, pointBackgroundColor: current.colour
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        animation: still ? false : { duration: 550 },
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { display: false }, ticks: { font: { size: 10 } } },
          y: { beginAtZero: true, grid: { color: '#ECE7F3' }, ticks: { font: { size: 10 } } }
        }
      }
    });
  }

  function openService(id) {
    var s = byId[id];
    if (!s) { return; }
    current = s;

    $('#dName').textContent = s.name;
    $('[data-d-type]').textContent = s.type + ' service';
    $('[data-d-head]').style.setProperty('--c', s.colour);
    $('[data-d-icon]').className = 'fa-solid ' + s.icon;
    $('[data-d-when]').textContent = s.when;
    $('[data-d-venue]').textContent = s.venue;
    $('[data-d-person]').textContent = s.person;
    $('[data-d-desc]').textContent = s.desc || 'No description yet.';
    $('[data-d-notes]').textContent = s.notes || 'No notes.';
    $('[data-d-avg]').textContent = s.average.toLocaleString();
    $('[data-d-exp]').textContent = s.expected.toLocaleString();

    var bar = $('[data-d-bar]');
    bar.className = 'rbar is-' + (s.rate >= 75 ? 'good' : s.rate >= 40 ? 'fair' : 'poor');
    $('.rbar__fill', bar).style.width = Math.min(100, s.rate) + '%';

    $('[data-d-recur]').textContent =
      s.type === 'Monthly' ? (s.dom || s.dow) :
      s.type === 'Weekly'  ? 'Every ' + s.dow :
      s.type === 'Special' ? 'Quarterly, ' + s.dow : 'By arrangement';
    $('[data-d-track]').textContent = s.track ? 'Yes — each member marked' : 'No — head count only';
    var off = $('[data-d-offering]');
    if (off) { off.textContent = s.offering ? 'Yes' : 'No'; }
    $('[data-d-status]').textContent = s.active ? 'Active' : 'Inactive';
    $('[data-d-last]').textContent = (s.last === 0 ? 'Today' : s.last + ' days ago') + (s.overdue ? ' — overdue' : '');
    var br = $('[data-d-branch]');
    if (br) { br.textContent = s.branch || '—'; }

    /* the history table, newest first */
    var body = $('[data-d-history]');
    body.innerHTML = '';
    s.spark.slice().reverse().forEach(function (v, i) {
      if (!v) { return; }
      var when = new Date();
      when.setDate(when.getDate() - s.last - i * (s.type === 'Weekly' ? 7 : 30));
      var rate = s.expected ? Math.round((v / s.expected) * 100) : 0;
      var tr = document.createElement('tr');
      tr.innerHTML = '<td class="nowrap"></td><td class="strong"></td><td class="num"></td>' +
        '<td><span class="ratecell"><b class="is-' + (rate >= 75 ? 'good' : rate >= 40 ? 'fair' : 'poor') + '">' +
        rate + '%</b></span></td>';
      tr.children[0].textContent = when.toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' });
      tr.children[1].textContent = v.toLocaleString();
      tr.children[2].textContent = s.expected.toLocaleString();
      body.appendChild(tr);
    });

    setTab('overview');
    openDrawer();
  }

  document.addEventListener('click', function (e) {
    var o = e.target.closest('[data-open]');
    if (o) { closeOwnMenu(o); openService(o.getAttribute('data-open')); }
  }, true);

  /* ═════════════════════════ add / edit / delete ═════════════════════════ */

  if (CAN_MANAGE) {
    var modal = $('#modalService'), delModal = $('#modalDelete'), editingId = null;

    function openModal(m) { m.hidden = false; document.body.style.overflow = 'hidden'; $('[data-close]', m).focus(); }
    function closeModal(m) {
      m.hidden = true;
      if (drawer.hidden) { document.body.style.overflow = ''; }
    }

    /* Only the recurrence control that fits the chosen type is shown. */
    function syncRecurrence() {
      var t = ($('input[name="msType"]:checked') || {}).value || 'Weekly';
      $$('[data-recur]').forEach(function (f) { f.hidden = f.getAttribute('data-recur') !== t; });
    }
    $$('input[name="msType"]').forEach(function (r) { r.addEventListener('change', syncRecurrence); });

    function pickIn(container, el) {
      $$('.pick', container).forEach(function (p) {
        var on = p === el;
        p.classList.toggle('is-on', on);
        p.setAttribute('aria-checked', String(on));
      });
    }
    $('#msIcons').addEventListener('click', function (e) {
      var p = e.target.closest('.pick'); if (p) { pickIn(this, p); }
    });
    $('#msColours').addEventListener('click', function (e) {
      var p = e.target.closest('.pick'); if (p) { pickIn(this, p); }
    });
    function selectPick(container, attr, value) {
      var found = $$('.pick', container).filter(function (p) { return p.getAttribute(attr) === value; })[0];
      pickIn(container, found || $$('.pick', container)[0]);
    }

    function openEditor(id) {
      editingId = id || null;
      var s = id ? byId[id] : null;

      $('#msTitle').textContent = s ? 'Edit Service' : 'Add Service';
      $('#msGoLabel').textContent = s ? 'Save Changes' : 'Create Service';

      $('#msName').value     = s ? s.name : '';
      $('#msDesc').value     = s ? s.desc : '';
      $('#msVenue').value    = s ? s.venue : '';
      $('#msPerson').value   = s ? s.person : '';
      $('#msStart').value    = s ? s.start : '09:00';
      $('#msEnd').value      = s ? s.end : '11:00';
      $('#msExpected').value = s ? s.expected : 100;
      $('#msTrack').checked  = s ? s.track : true;
      var mo = $('#msOffering'); if (mo) { mo.checked = s ? s.offering : true; }
      $('#msActive').checked = s ? s.active : true;

      var type = s ? s.type : 'Weekly';
      $$('input[name="msType"]').forEach(function (r) { r.checked = r.value === type; });
      syncRecurrence();
      if (s && s.dow) { $('#msDow').value = s.dow; }
      if (s && s.dom) { $('#msDom').value = s.dom; }

      selectPick($('#msIcons'),   'data-icon',   s ? s.icon : null);
      selectPick($('#msColours'), 'data-colour', s ? s.colour : null);

      $('#msName').closest('.field').classList.remove('is-bad');
      openModal(modal);
    }

    $$('[data-add-service]').forEach(function (b) {
      b.addEventListener('click', function () { openEditor(null); });
    });

    $('#msGo').addEventListener('click', function () {
      var name = $('#msName');
      if (!name.value.trim()) {
        name.closest('.field').classList.add('is-bad');
        name.focus();
        return;
      }
      closeModal(modal);
      toast(editingId ? 'Service updated' : 'Service created', 'success');
    });
    $('#msName').addEventListener('input', function () {
      if (this.value.trim()) { this.closest('.field').classList.remove('is-bad'); }
    });

    function openDelete(id) {
      var s = byId[id];
      if (!s) { return; }
      $('[data-del-name]').textContent = s.name;
      $('[data-del-echo]').textContent = s.name;
      var inp = $('#delConfirm'), go = $('#delGo');
      inp.value = ''; go.disabled = true; inp.placeholder = s.name;
      inp.oninput = function () { go.disabled = inp.value.trim() !== s.name; };
      openModal(delModal);
    }
    $('#delGo').addEventListener('click', function () {
      closeModal(delModal);
      toast('Service deleted — past attendance kept', 'error');
    });

    document.addEventListener('click', function (e) {
      var ed = e.target.closest('[data-edit]');
      if (ed) { closeOwnMenu(ed); openEditor(ed.getAttribute('data-edit')); return; }
      var dl = e.target.closest('[data-delete]');
      if (dl) { closeOwnMenu(dl); openDelete(dl.getAttribute('data-delete')); return; }
      var dp = e.target.closest('[data-duplicate]');
      if (dp) { closeOwnMenu(dp); toast('“' + byId[dp.getAttribute('data-duplicate')].name + '” duplicated', 'success'); return; }
      var da = e.target.closest('[data-deactivate]');
      if (da) {
        closeOwnMenu(da);
        var s = byId[da.getAttribute('data-deactivate')];
        toast(s.name + (s.active ? ' deactivated' : ' activated'), 'info');
        return;
      }
      var cl = e.target.closest('[data-close]');
      if (cl) { closeModal(cl.closest('.modal-scrim')); return; }
      if (e.target.classList.contains('modal-scrim')) { closeModal(e.target); }
    }, true);

    var dEdit = $('#dEdit');
    if (dEdit) { dEdit.addEventListener('click', function () { if (current) { openEditor(current.id); } }); }
  }

  /* ────────────────────────── escape key ────────────────────────── */
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') { return; }
    var open = $$('.modal-scrim').filter(function (m) { return !m.hidden; });
    if (open.length) { open.forEach(function (m) { m.hidden = true; }); }
    else if (!drawer.hidden) { closeDrawer(); }
    if (drawer.hidden) { document.body.style.overflow = ''; }
  });

  apply();
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/../components/footer.php'; ?>

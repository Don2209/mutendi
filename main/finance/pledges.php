<?php
/**
 * Mutendi CMS — Pledges & Projects.
 *
 * Two linked concepts. A PROJECT is the goal the church is raising toward; a
 * PLEDGE is one member's promise toward that goal. The page keeps them side by
 * side because the interesting number is the gap between them: money promised
 * that has not yet arrived.
 *
 * Three tabs:
 *   Projects   the campaigns, as cards or as a table
 *   Pledges    every individual promise, with its payment schedule
 *   Analysis   the four charts that compare projects against each other
 *
 * Nothing here is stored. Statuses, schedules, percentages and days remaining
 * are all worked out from the demo figures against today's date, so the page
 * stays truthful as the date moves.
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
/* Guarded so this page can carry its own copy without colliding with any
   other page — they are never loaded together. */
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

/* The ▲/▼ the stat strip uses. */
if (!function_exists('fin_delta')) {
    function fin_delta(float $now, float $prev, string $suffix = ''): string
    {
        if ($prev == 0.0) { return '<span class="delta is-flat">&mdash;</span>'; }
        $pct = (($now - $prev) / $prev) * 100;
        if (abs($pct) < 0.05) { return '<span class="delta is-flat">&mdash; no change</span>'; }
        $cls = $pct > 0 ? 'is-up' : 'is-down';
        $ico = $pct > 0 ? 'fa-caret-up' : 'fa-caret-down';
        return '<span class="delta ' . $cls . '"><i class="fa-solid ' . $ico . '" aria-hidden="true"></i> '
             . number_format(abs($pct), 1) . '%' . $suffix . '</span>';
    }
}

/* Both modules have to be on. A pledge without a project is meaningless, and a
   project without the finance module has nowhere to record money against. */
$has_finance = mu_mod('finance');
$has_projects= mu_mod('projects');
$has_module  = $has_finance && $has_projects;
$can_view    = mu_can('finance.view');
$can_manage  = mu_can('projects.manage');
$can_add     = mu_can('finance.add');

/* ─────────────────────────── BRANCH AWARENESS ───────────────────────────
   Whose project this is. Entirely inert for a single church: is_multi_branch()
   is false, so no chip, column or filter is rendered.
   ──────────────────────────────────────────────────────────────────────── */
require_once __DIR__ . '/../components/branch-switcher.php';

$branch_aware   = is_multi_branch();
$viewing_all    = !$branch_aware || $current_branch === 'all' || $current_branch === null;
$show_branch    = $branch_aware && $viewing_all;
$branch_options = $branch_aware ? get_visible_branches() : [];

if (!function_exists('mu_branch_for')) {
    /**
     * Which branch a demo record belongs to. Deterministic from the record's
     * own key, so a project never hops between branches on reload.
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

/* ══════════════════════ WORKING OUT WHERE THINGS STAND ══════════════════════
   Every figure below is derived. Nothing about a project's health or a
   pledge's status is stored, because both are statements about today.
   ─────────────────────────────────────────────────────────────────────────── */

$cur_by_code = array_column($currencies, null, 'code');
$mem_by_id   = array_column($members_demo, null, 'id');
$today       = date('Y-m-d');
$today_ts    = strtotime($today);

/** An amount in its own currency, converted to USD. */
function pl_usd(float $amount, string $code): float
{
    global $cur_by_code;
    return $amount * ($cur_by_code[$code]['exchange_rate_to_usd'] ?? 1.0);
}

/** The currency symbol, matching finance/record.php. */
function pl_sym(string $code): string
{
    global $cur_by_code;
    return $cur_by_code[$code]['symbol'] ?? '$';
}

function pl_money(float $n, string $code = 'USD'): string
{
    return pl_sym($code) . number_format($n, 2);
}

/** Whole days from today to $iso — negative once the date has passed. */
function pl_days_to(string $iso): int
{
    global $today_ts;
    return (int) round((strtotime($iso) - $today_ts) / 86400);
}

/** When instalment $k of a plan falls due, counting from zero. */
function pl_due(string $first, string $plan, int $k): string
{
    if ($k <= 0) { return $first; }
    switch ($plan) {
        case 'weekly':    return date('Y-m-d', strtotime($first . ' +' . ($k * 7) . ' days'));
        case 'quarterly': return date('Y-m-d', strtotime($first . ' +' . ($k * 3) . ' months'));
        case 'custom':    return date('Y-m-d', strtotime($first . ' +' . ($k * 2) . ' months'));
        case 'one_off':   return $first;
        default:          return date('Y-m-d', strtotime($first . ' +' . $k . ' months'));
    }
}

/* ── the projects ── */
$projects = [];
if ($has_module && $can_view) {
    foreach ($projects_demo as $p) {
        $target      = (float) $p['target'];
        $raised      = (float) $p['raised'];
        $pledged     = (float) $p['pledged'];
        $outstanding = max(0.0, $target - $raised);

        $pct_raised  = $target > 0 ? min(100.0, ($raised  / $target) * 100) : 0.0;
        $pct_pledged = $target > 0 ? min(100.0, ($pledged / $target) * 100) : 0.0;

        /* How far through its own timeline the project is. A project that has
           spent 80% of its time and raised 20% of its money is in trouble; one
           that has spent 20% and raised 20% is simply young. */
        $span    = max(1, strtotime($p['target_date']) - strtotime($p['start_date']));
        $elapsed = min(1.0, max(0.0, ($today_ts - strtotime($p['start_date'])) / $span)) * 100;
        $days_left = pl_days_to($p['target_date']);

        if ($p['status'] === 'completed' || $pct_raised >= 100) {
            $health = 'done';
        } elseif ($p['status'] === 'cancelled') {
            $health = 'dead';
        } elseif ($days_left < 0) {
            $health = 'risk';
        } elseif ($pct_raised >= $elapsed - 10) {
            $health = 'good';
        } elseif ($pct_raised >= $elapsed - 30) {
            $health = 'warn';
        } else {
            $health = 'risk';
        }

        $projects[] = $p + [
            'outstanding' => $outstanding,
            'pct_raised'  => $pct_raised,
            'pct_pledged' => $pct_pledged,
            'elapsed'     => $elapsed,
            'days_left'   => $days_left,
            'health'      => $health,
            'people'      => array_values(array_filter(array_map(
                static fn($id) => $mem_by_id[$id] ?? null, $p['contributors']
            ))),
            '_branch'     => $branch_aware ? mu_branch_for('prj-' . $p['id']) : null,
        ];
    }
}

if ($branch_aware && !$viewing_all) {
    $projects = array_values(array_filter($projects, static function ($p) use ($current_branch) {
        return $p['_branch'] && (int) $p['_branch']['id'] === (int) $current_branch;
    }));
}
$proj_by_id = array_column($projects, null, 'id');

/* ── the pledges ── */
$STATUS_LABEL = [
    'on_track'  => 'On Track',
    'behind'    => 'Behind',
    'overdue'   => 'Overdue',
    'completed' => 'Completed',
    'defaulted' => 'Defaulted',
];

$pledges = [];
if ($has_module && $can_view) {
    foreach ($pledges_demo as $pl) {
        $project = $proj_by_id[$pl['project_id']] ?? null;
        if (!$project) { continue; }          /* filtered out with its branch */
        $member  = $mem_by_id[$pl['member_id']] ?? null;

        $amount = (float) $pl['amount'];
        $paid   = (float) $pl['paid'];
        $n      = max(1, (int) $pl['instalments']);
        $each   = $amount / $n;
        $done   = $each > 0 ? min($n, (int) floor(($paid + 0.005) / $each)) : 0;
        $complete = $paid >= $amount - 0.005;

        /* The schedule, instalment by instalment. This is what the pledge
           drawer shows and what every status below is read from. */
        $schedule = [];
        $due_by_now = 0;
        for ($k = 0; $k < $n; $k++) {
            $due  = pl_due($pl['first_due'], $pl['plan'], $k);
            $late = strtotime($due) < $today_ts;
            if ($late) { $due_by_now++; }
            $schedule[] = [
                'no'    => $k + 1,
                'due'   => $due,
                'amount'=> $each,
                'state' => $k < $done ? 'paid' : ($late ? 'overdue' : 'due'),
            ];
        }

        $next = null;
        foreach ($schedule as $s) { if ($s['state'] !== 'paid') { $next = $s['due']; break; } }

        /* Instalments the plan asked for by now, against instalments settled.
           One missed payment is "behind"; a month late, or two missed, is
           "overdue". That is the distinction a treasurer actually chases. */
        $missed    = $due_by_now - $done;
        $late_days = $next !== null ? -pl_days_to($next) : 0;

        if ($pl['defaulted'])                       { $status = 'defaulted'; }
        elseif ($complete)                          { $status = 'completed'; }
        elseif ($missed >= 2)                       { $status = 'overdue'; }
        elseif ($missed === 1 && $late_days > 30)   { $status = 'overdue'; }
        elseif ($missed === 1)                       { $status = 'behind'; }
        else                                        { $status = 'on_track'; }

        /* The most recent instalment actually settled. */
        $last_paid = $done > 0 ? $schedule[$done - 1]['due'] : null;

        $pledges[] = $pl + [
            'member'      => $member['name'] ?? 'Unknown',
            'member_no'   => $member['member_no'] ?? '',
            'project'     => $project['name'],
            'proj_colour' => $project['colour'],
            'proj_icon'   => $project['icon'],
            'outstanding' => max(0.0, $amount - $paid),
            'pct'         => $amount > 0 ? min(100.0, ($paid / $amount) * 100) : 0.0,
            'usd'         => round(pl_usd($amount, $pl['currency']), 2),
            'paid_usd'    => round(pl_usd($paid, $pl['currency']), 2),
            'each'        => $each,
            'done'        => $done,
            'schedule'    => $schedule,
            'next_due'    => $next,
            'next_days'   => $next !== null ? pl_days_to($next) : null,
            'last_paid'   => $last_paid,
            'status'      => $status,
            'status_label'=> $STATUS_LABEL[$status],
            'plan_label'  => $pledge_plans[$pl['plan']]['name'] ?? $pl['plan'],
            'plan_full'   => ($pledge_plans[$pl['plan']]['name'] ?? $pl['plan'])
                             . ($n > 1 ? ' × ' . $n : ''),
            '_branch'     => $project['_branch'],
        ];
    }
}

/* ── the aggregates the strip, the bottom cards and the Analysis tab read ── */
$active_projects = array_values(array_filter($projects, static fn($p) => $p['status'] === 'active'));

$total_target = array_sum(array_column($projects, 'target'));
$total_raised = array_sum(array_column($projects, 'raised'));
$total_pledgd = array_sum(array_column($projects, 'pledged'));
$total_out    = max(0.0, $total_pledgd - $total_raised);
$pct_collected = $total_pledgd > 0 ? ($total_raised / $total_pledgd) * 100 : 0.0;

$PS = $pledge_stats;

/* Overdue pledges, worst first — the bottom-left card. */
$overdue = array_values(array_filter($pledges, static fn($p) => $p['status'] === 'overdue'));
usort($overdue, static fn($a, $b) => ($a['next_days'] ?? 0) <=> ($b['next_days'] ?? 0));

/* Fulfilment: of every pledge made, how many are settled, part-settled, or
   have had nothing at all against them. */
$fulfil = ['full' => 0, 'part' => 0, 'none' => 0];
foreach ($pledges as $p) {
    if ($p['paid'] <= 0)            { $fulfil['none']++; }
    elseif ($p['status'] === 'completed') { $fulfil['full']++; }
    else                            { $fulfil['part']++; }
}

/* Top contributors, by what they have actually paid across every pledge. */
$top = [];
foreach ($pledges as $p) {
    $id = (int) $p['member_id'];
    if (!isset($top[$id])) {
        $top[$id] = ['id' => $id, 'name' => $p['member'], 'no' => $p['member_no'], 'paid' => 0.0, 'count' => 0];
    }
    $top[$id]['paid']  += $p['paid_usd'];
    $top[$id]['count'] += 1;
}
$top = array_values($top);
usort($top, static fn($a, $b) => $b['paid'] <=> $a['paid']);
$top = array_slice($top, 0, 8);

/* Twelve month labels ending on the current month, for the line chart. */
$trend_labels = [];
for ($i = 11; $i >= 0; $i--) { $trend_labels[] = date('M y', strtotime("-$i months")); }

$page_title = 'Pledges & Projects';
require __DIR__ . '/../components/header.php';
?>

<div class="page">

  <!-- ═════════════════════════════ PAGE HEADER ═════════════════════════════ -->
  <header class="page__head">
    <div>
      <nav class="crumbs" aria-label="Breadcrumb">
        <a href="<?= $base_url ?>index.php">Dashboard</a>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span>Finance</span>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span aria-current="page">Pledges &amp; Projects</span>
      </nav>
      <div class="title-row">
        <h1 class="page__title">Pledges &amp; Projects</h1>
      </div>
      <p class="page__sub">Track fundraising projects and member pledges.</p>
    </div>

    <?php if ($has_module && $can_view): ?>
      <div class="page__actions">
        <?php if ($can_manage): ?>
          <button class="btn" type="button" id="btnNewProject">
            <i class="fa-solid fa-plus" aria-hidden="true"></i> New Project
          </button>
        <?php endif; ?>

        <button class="btn btn--ghost" type="button" id="btnNewPledge">
          <i class="fa-solid fa-plus" aria-hidden="true"></i> Record Pledge
        </button>

        <div class="drop" data-menu>
          <button class="btn btn--ghost" type="button" aria-haspopup="true" aria-expanded="false" data-menu-btn>
            <i class="fa-solid fa-file-export" aria-hidden="true"></i> Export
            <i class="fa-solid fa-chevron-down" style="font-size:10px;opacity:.7" aria-hidden="true"></i>
          </button>
          <div class="menu" data-menu-panel hidden>
            <button class="menu__item" type="button" data-toast="CSV export started"><i class="fa-solid fa-file-csv" aria-hidden="true"></i> Export as CSV</button>
            <button class="menu__item" type="button" data-toast="Excel export started"><i class="fa-solid fa-file-excel" aria-hidden="true"></i> Export as Excel</button>
            <button class="menu__item" type="button" data-toast="PDF export started"><i class="fa-solid fa-file-pdf" aria-hidden="true"></i> Export as PDF</button>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </header>


<?php if (!$has_module): ?>

  <section class="panel">
    <div class="empty">
      <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-plug-circle-xmark"></i></span>
      <h3><?= $has_finance ? 'The Projects module is switched off' : 'The Finance module is switched off' ?></h3>
      <p>
        Pledges need both Finance and Projects switched on &mdash; a project to raise toward, and
        somewhere to record the money against.
        <?php if ($has_finance && !$has_projects): ?>
          Finance is on; Projects is not.
        <?php elseif (!$has_finance && $has_projects): ?>
          Projects is on; Finance is not.
        <?php else: ?>
          Neither is on for your church.
        <?php endif; ?>
        A church administrator can request them from the platform team.
      </p>
      <a class="btn" href="<?= $base_url ?>index.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Dashboard</a>
    </div>
  </section>

<?php elseif (!$can_view): ?>

  <section class="panel">
    <div class="empty">
      <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-lock"></i></span>
      <h3>You do not have access to giving records</h3>
      <p>Pledges are part of the church's financial record. Ask an administrator for the finance viewing permission.</p>
      <a class="btn" href="<?= $base_url ?>index.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Dashboard</a>
    </div>
  </section>

<?php else: ?>

  <!-- ═════════════════════════════ STAT STRIP ═════════════════════════════ -->
  <section class="stat-strip" aria-label="Fundraising at a glance">
    <div class="stat-tile is-static">
      <span class="stat-tile__icon tone-purple" aria-hidden="true"><i class="fa-solid fa-diagram-project"></i></span>
      <span class="stat-tile__body">
        <span class="stat-tile__value"><span data-count="<?= count($active_projects) ?>">0</span></span>
        <span class="stat-tile__label">Active Projects</span>
        <?= fin_delta((float) count($active_projects), (float) $PS['projects']['prev']) ?>
      </span>
    </div>

    <div class="stat-tile is-static">
      <span class="stat-tile__icon tone-blue" aria-hidden="true"><i class="fa-solid fa-hand-holding-heart"></i></span>
      <span class="stat-tile__body">
        <span class="stat-tile__value">$<span data-count="<?= (int) round($total_pledgd) ?>">0</span></span>
        <span class="stat-tile__label">Total Pledged</span>
        <?= fin_delta((float) $total_pledgd, (float) $PS['pledged']['prev']) ?>
      </span>
    </div>

    <div class="stat-tile is-static">
      <span class="stat-tile__icon tone-green" aria-hidden="true"><i class="fa-solid fa-sack-dollar"></i></span>
      <span class="stat-tile__body">
        <span class="stat-tile__value">$<span data-count="<?= (int) round($total_raised) ?>">0</span></span>
        <span class="stat-tile__label">Total Received</span>
        <?= fin_delta((float) $total_raised, (float) $PS['received']['prev']) ?>
      </span>
    </div>

    <!-- Outstanding is the whole point of a pledge register, so it carries the
         collected bar rather than a percentage delta. -->
    <div class="stat-tile is-static stat-tile--bar">
      <span class="stat-tile__icon tone-amber" aria-hidden="true"><i class="fa-solid fa-hourglass-half"></i></span>
      <span class="stat-tile__body">
        <span class="stat-tile__value">$<span data-count="<?= (int) round($total_out) ?>">0</span></span>
        <span class="stat-tile__label">Outstanding on pledges</span>
        <span class="collected">
          <span class="rbar rbar--sm"><span class="rbar__fill" style="width:<?= round($pct_collected, 1) ?>%;background:var(--ok)"></span></span>
          <b><?= number_format($pct_collected, 1) ?>%</b> collected
        </span>
      </span>
    </div>
  </section>


  <!-- ══════════════════════════════ THE TABS ══════════════════════════════ -->
  <div class="tabs" role="tablist" aria-label="Pledges and projects">
    <button class="tab is-on" type="button" role="tab" id="tab-projects" aria-controls="panel-projects" aria-selected="true" data-tab="projects">
      <i class="fa-solid fa-diagram-project" aria-hidden="true"></i> Projects
      <span class="tab__n"><?= count($projects) ?></span>
    </button>
    <button class="tab" type="button" role="tab" id="tab-pledges" aria-controls="panel-pledges" aria-selected="false" tabindex="-1" data-tab="pledges">
      <i class="fa-solid fa-hand-holding-heart" aria-hidden="true"></i> Pledges
      <span class="tab__n"><?= count($pledges) ?></span>
    </button>
    <button class="tab" type="button" role="tab" id="tab-analysis" aria-controls="panel-analysis" aria-selected="false" tabindex="-1" data-tab="analysis">
      <i class="fa-solid fa-chart-simple" aria-hidden="true"></i> Analysis
    </button>
  </div>


  <!-- ══════════════════════════ TAB 1 — PROJECTS ══════════════════════════ -->
  <section class="tabpanel" id="panel-projects" role="tabpanel" aria-labelledby="tab-projects">

    <div class="viewbar">
      <div class="svcviews" role="group" aria-label="How to show projects">
        <button class="svcview is-on" type="button" data-pview="cards" aria-pressed="true">
          <i class="fa-solid fa-grip" aria-hidden="true"></i> Cards
        </button>
        <button class="svcview" type="button" data-pview="table" aria-pressed="false">
          <i class="fa-solid fa-table-list" aria-hidden="true"></i> Table
        </button>
      </div>
      <p class="viewbar__note">
        <?= count($projects) ?> project<?= count($projects) === 1 ? '' : 's' ?> &middot;
        <?= pl_money((float) $total_raised) ?> raised of <?= pl_money((float) $total_target) ?> targeted
      </p>
    </div>

    <!-- ── card grid: two across, because a progress bar needs room ── -->
    <div class="prjgrid" data-pane="cards">
      <?php foreach ($projects as $p): ?>
        <?php
          $health_label = ['good' => 'On track', 'warn' => 'Behind', 'risk' => 'At risk',
                           'done' => 'Funded',   'dead' => 'Cancelled'][$p['health']];
        ?>
        <article class="prjcard prjcard--<?= $p['health'] ?>" style="--c:<?= htmlspecialchars($p['colour']) ?>"
                 data-project="<?= (int) $p['id'] ?>">

          <!-- cover: the icon on the project's own colour -->
          <div class="prjcard__cover" aria-hidden="true">
            <span class="prjcard__coverico"><i class="fa-solid <?= htmlspecialchars($p['icon']) ?>"></i></span>
          </div>

          <div class="prjcard__body">
            <header class="prjcard__head">
              <div class="prjcard__id">
                <h3 class="prjcard__name"><?= htmlspecialchars($p['name']) ?></h3>
                <span class="catchip"><?= htmlspecialchars($p['category']) ?></span>
              </div>
              <span class="pill pill--<?= htmlspecialchars($p['status']) ?>">
                <?= htmlspecialchars(ucwords(str_replace('_', ' ', $p['status']))) ?>
              </span>
            </header>

            <!-- The centrepiece. Raised against target, coloured by health. -->
            <div class="bigbar" role="img"
                 aria-label="<?= number_format($p['pct_raised'], 1) ?> percent of target raised, <?= $health_label ?>">
              <span class="bigbar__fill" style="width:<?= round($p['pct_raised'], 1) ?>%"></span>
              <span class="bigbar__pct"><?= number_format($p['pct_raised'], 1) ?>%</span>
            </div>

            <div class="prjcard__figs">
              <span><b><?= pl_money((float) $p['target'], $p['currency']) ?></b>Target</span>
              <span><b><?= pl_money((float) $p['raised'], $p['currency']) ?></b>Raised</span>
              <span><b><?= pl_money((float) $p['outstanding'], $p['currency']) ?></b>Outstanding</span>
            </div>

            <!-- The thin bar is the promise; the thick one is the money.
                 The gap between them is what has been pledged but not paid. -->
            <div class="thinbar" title="<?= pl_money((float) $p['pledged'], $p['currency']) ?> pledged of <?= pl_money((float) $p['target'], $p['currency']) ?>">
              <span class="thinbar__fill" style="width:<?= round($p['pct_pledged'], 1) ?>%"></span>
            </div>
            <p class="thinbar__cap">
              <i class="fa-regular fa-handshake" aria-hidden="true"></i>
              <?= pl_money((float) $p['pledged'], $p['currency']) ?> pledged
              <span>(<?= number_format($p['pct_pledged'], 1) ?>% of target)</span>
            </p>

            <div class="prjcard__time">
              <span><i class="fa-regular fa-flag" aria-hidden="true"></i> <?= mu_date($p['start_date'], 'd M Y') ?></span>
              <i class="fa-solid fa-arrow-right-long" aria-hidden="true"></i>
              <span><i class="fa-regular fa-calendar-check" aria-hidden="true"></i> <?= mu_date($p['target_date'], 'd M Y') ?></span>
              <?php if ($p['status'] === 'completed'): ?>
                <span class="daysleft is-done"><i class="fa-solid fa-check" aria-hidden="true"></i> Completed</span>
              <?php elseif ($p['days_left'] < 0): ?>
                <span class="daysleft is-over"><?= abs($p['days_left']) ?> days overdue</span>
              <?php elseif ($p['days_left'] < 30): ?>
                <span class="daysleft is-soon"><?= $p['days_left'] ?> days left</span>
              <?php else: ?>
                <span class="daysleft"><?= $p['days_left'] ?> days left</span>
              <?php endif; ?>
            </div>

            <div class="prjcard__foot">
              <span class="avstack" title="<?= count($p['people']) ?> contributors">
                <?php foreach (array_slice($p['people'], 0, 5) as $m): ?>
                  <?= mu_av($m['name'], 'xs') ?>
                <?php endforeach; ?>
                <?php if (count($p['people']) > 5): ?>
                  <span class="av av--xs av--more" aria-hidden="true">+<?= count($p['people']) - 5 ?></span>
                <?php endif; ?>
                <b><?= count($p['people']) ?> contributors</b>
              </span>
              <?php if ($show_branch): ?><?= mu_branch_chip($p['_branch']) ?><?php endif; ?>
            </div>

            <div class="prjcard__acts">
              <button class="btn btn--ghost btn--sm" type="button" data-project-open="<?= (int) $p['id'] ?>">
                <i class="fa-regular fa-eye" aria-hidden="true"></i> View
              </button>
              <?php if ($can_add): ?>
                <a class="btn btn--ghost btn--sm" href="<?= $base_url ?>finance/record.php">
                  <i class="fa-solid fa-plus" aria-hidden="true"></i> Record Contribution
                </a>
              <?php endif; ?>
              <?php if ($can_manage): ?>
                <button class="btn btn--ghost btn--sm" type="button" data-project-edit="<?= (int) $p['id'] ?>">
                  <i class="fa-solid fa-pen" aria-hidden="true"></i> Edit
                </button>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <!-- ── the same projects as a table ── -->
    <section class="panel" data-pane="table" hidden>
      <div class="dt-wrap">
        <table class="dt" id="prjTable">
          <thead>
            <tr>
              <th style="width:40px">#</th>
              <th>Project</th>
              <th style="text-align:right">Target</th>
              <th style="text-align:right">Pledged</th>
              <th style="text-align:right">Raised</th>
              <th style="text-align:right">Outstanding</th>
              <th style="min-width:140px">Progress</th>
              <th style="text-align:right">Contributors</th>
              <th>Start Date</th>
              <th>Target Date</th>
              <th>Status</th>
              <?php if ($show_branch): ?><th><?= htmlspecialchars(t('branch_singular')) ?></th><?php endif; ?>
              <th class="col-actions" style="text-align:right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($projects as $i => $p): ?>
              <tr data-prow data-id="<?= (int) $p['id'] ?>">
                <td class="num"><?= $i + 1 ?></td>
                <td>
                  <span class="minirow">
                    <span class="prjico" style="--c:<?= htmlspecialchars($p['colour']) ?>" aria-hidden="true">
                      <i class="fa-solid <?= htmlspecialchars($p['icon']) ?>"></i>
                    </span>
                    <span class="minirow__text">
                      <b><?= htmlspecialchars($p['name']) ?></b>
                      <span><?= htmlspecialchars($p['category']) ?></span>
                    </span>
                  </span>
                </td>
                <td class="num"><?= pl_money((float) $p['target'], $p['currency']) ?></td>
                <td class="num"><?= pl_money((float) $p['pledged'], $p['currency']) ?></td>
                <td class="num"><b><?= pl_money((float) $p['raised'], $p['currency']) ?></b></td>
                <td class="num"><?= pl_money((float) $p['outstanding'], $p['currency']) ?></td>
                <td>
                  <span class="cellbar cellbar--<?= $p['health'] ?>">
                    <span class="cellbar__track"><span class="cellbar__fill" style="width:<?= round($p['pct_raised'], 1) ?>%"></span></span>
                    <b><?= number_format($p['pct_raised'], 1) ?>%</b>
                  </span>
                </td>
                <td class="num"><?= count($p['people']) ?></td>
                <td><?= mu_date($p['start_date']) ?></td>
                <td>
                  <?= mu_date($p['target_date']) ?>
                  <?php if ($p['status'] !== 'completed'): ?>
                    <?php if ($p['days_left'] < 0): ?>
                      <span class="metaline is-over"><?= abs($p['days_left']) ?> days overdue</span>
                    <?php elseif ($p['days_left'] < 30): ?>
                      <span class="metaline is-soon"><?= $p['days_left'] ?> days left</span>
                    <?php else: ?>
                      <span class="metaline"><?= $p['days_left'] ?> days left</span>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>
                <td><span class="pill pill--<?= htmlspecialchars($p['status']) ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $p['status']))) ?></span></td>
                <?php if ($show_branch): ?><td><?= mu_branch_chip($p['_branch']) ?></td><?php endif; ?>
                <td class="col-actions">
                  <div class="rowacts">
                    <button class="iconbtn" type="button" data-project-open="<?= (int) $p['id'] ?>" aria-label="View <?= htmlspecialchars($p['name']) ?>">
                      <i class="fa-regular fa-eye" aria-hidden="true"></i>
                    </button>
                    <?php if ($can_manage): ?>
                      <button class="iconbtn" type="button" data-project-edit="<?= (int) $p['id'] ?>" aria-label="Edit <?= htmlspecialchars($p['name']) ?>">
                        <i class="fa-solid fa-pen" aria-hidden="true"></i>
                      </button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Below 768px the table becomes cards. Never a shrunken table. -->
      <div class="dt-cards">
        <?php foreach ($projects as $p): ?>
          <article class="pcard pcard--flat">
            <header class="pcard__head">
              <span class="prjico" style="--c:<?= htmlspecialchars($p['colour']) ?>" aria-hidden="true">
                <i class="fa-solid <?= htmlspecialchars($p['icon']) ?>"></i>
              </span>
              <span class="pcard__text">
                <b><?= htmlspecialchars($p['name']) ?></b>
                <span><?= htmlspecialchars($p['category']) ?></span>
              </span>
              <span class="pill pill--<?= htmlspecialchars($p['status']) ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $p['status']))) ?></span>
            </header>
            <span class="cellbar cellbar--<?= $p['health'] ?>" style="margin-bottom:10px">
              <span class="cellbar__track"><span class="cellbar__fill" style="width:<?= round($p['pct_raised'], 1) ?>%"></span></span>
              <b><?= number_format($p['pct_raised'], 1) ?>%</b>
            </span>
            <dl class="pcard__dl">
              <div><dt>Target</dt><dd><?= pl_money((float) $p['target'], $p['currency']) ?></dd></div>
              <div><dt>Pledged</dt><dd><?= pl_money((float) $p['pledged'], $p['currency']) ?></dd></div>
              <div><dt>Raised</dt><dd><?= pl_money((float) $p['raised'], $p['currency']) ?></dd></div>
              <div><dt>Outstanding</dt><dd><?= pl_money((float) $p['outstanding'], $p['currency']) ?></dd></div>
              <div><dt>Contributors</dt><dd><?= count($p['people']) ?></dd></div>
              <div><dt>Target date</dt><dd><?= mu_date($p['target_date']) ?></dd></div>
              <?php if ($show_branch): ?><div><dt><?= htmlspecialchars(t('branch_singular')) ?></dt><dd><?= mu_branch_chip($p['_branch']) ?></dd></div><?php endif; ?>
            </dl>
            <div class="pcard__acts">
              <button class="btn btn--ghost btn--sm" type="button" data-project-open="<?= (int) $p['id'] ?>"><i class="fa-regular fa-eye" aria-hidden="true"></i> View</button>
              <?php if ($can_manage): ?>
                <button class="btn btn--ghost btn--sm" type="button" data-project-edit="<?= (int) $p['id'] ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</button>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  </section>

  <!-- ══════════════════════════ TAB 2 — PLEDGES ══════════════════════════ -->
  <section class="tabpanel" id="panel-pledges" role="tabpanel" aria-labelledby="tab-pledges" hidden>

    <!-- ── filter bar ── -->
    <section class="filters" id="filters">
      <button class="filters__toggle" type="button" id="fToggle" aria-expanded="false" aria-controls="filters">
        <i class="fa-solid fa-filter" aria-hidden="true"></i> Filters
        <span class="filters__n" data-filter-n hidden>0</span>
      </button>

      <div class="filters__grid">
        <div class="field field--wide">
          <label for="fSearch">Search</label>
          <div class="search-field">
            <i class="fa-solid fa-magnifying-glass search-field__icon" aria-hidden="true"></i>
            <input class="input" type="search" id="fSearch" data-search
                   placeholder="Member, number, project or amount" autocomplete="off">
            <button class="search-field__clear" type="button" data-search-clear hidden aria-label="Clear search">
              <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
          </div>
        </div>

        <div class="field">
          <label for="fProject">Project</label>
          <select class="select" id="fProject" data-filter>
            <option value="">All</option>
            <?php foreach ($projects as $p): ?>
              <option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label for="fStatus">Status</label>
          <select class="select" id="fStatus" data-filter>
            <option value="">All</option>
            <?php foreach ($STATUS_LABEL as $k => $lab): ?>
              <option value="<?= $k ?>"><?= htmlspecialchars($lab) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label for="fPlan">Payment plan</label>
          <select class="select" id="fPlan" data-filter>
            <option value="">All</option>
            <?php foreach ($pledge_plans as $pl): ?>
              <option value="<?= htmlspecialchars($pl['key']) ?>"><?= htmlspecialchars($pl['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label for="fMin">Pledge amount (USD)</label>
          <div class="daterange">
            <input class="input" type="number" id="fMin" min="0" step="1" placeholder="Min" data-filter aria-label="Minimum pledge">
            <span aria-hidden="true">&ndash;</span>
            <input class="input" type="number" id="fMax" min="0" step="1" placeholder="Max" data-filter aria-label="Maximum pledge">
          </div>
        </div>

        <div class="field">
          <label for="fFrom">Next due between</label>
          <div class="daterange">
            <input class="input" type="date" id="fFrom" data-filter aria-label="Due from">
            <span aria-hidden="true">&ndash;</span>
            <input class="input" type="date" id="fTo" data-filter aria-label="Due to">
          </div>
        </div>

        <?php if ($show_branch): ?>
          <div class="field">
            <label for="fBranch"><?= htmlspecialchars(t('branch_singular')) ?></label>
            <select class="select" id="fBranch" data-filter>
              <option value="">All</option>
              <?php foreach ($branch_options as $b): ?>
                <option value="<?= (int) $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <div class="filters__actions">
          <button class="btn" type="button" data-toast="Filters applied"><i class="fa-solid fa-check" aria-hidden="true"></i> Apply</button>
          <button class="btn btn--ghost" type="button" data-reset-filters><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Reset</button>
        </div>
      </div>

      <!-- What is currently narrowing the table, each one removable. -->
      <div class="chips-row" data-filter-chips hidden></div>
    </section>

    <!-- ── the pledge table ── -->
    <section class="panel">
      <div class="dt-wrap">
        <table class="dt" id="plTable">
          <thead>
            <tr>
              <th style="width:34px"><input class="check" type="checkbox" data-check-all aria-label="Select all pledges"></th>
              <th style="width:40px">#</th>
              <th class="is-sortable" data-sort="name">Member <i class="fa-solid fa-sort sort" aria-hidden="true"></i></th>
              <th>Project</th>
              <th class="is-sortable" data-sort="usd" style="text-align:right">Pledged <i class="fa-solid fa-sort sort" aria-hidden="true"></i></th>
              <th style="text-align:right">Paid to Date</th>
              <th style="text-align:right">Outstanding</th>
              <th class="is-sortable" data-sort="pct" style="min-width:130px">Progress <i class="fa-solid fa-sort sort" aria-hidden="true"></i></th>
              <th>Payment Plan</th>
              <th class="is-sortable" data-sort="due">Next Due <i class="fa-solid fa-sort sort" aria-hidden="true"></i></th>
              <th>Last Payment</th>
              <th>Status</th>
              <?php if ($show_branch): ?><th><?= htmlspecialchars(t('branch_singular')) ?></th><?php endif; ?>
              <th class="col-actions" style="text-align:right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pledges as $i => $p): ?>
              <tr data-lrow data-id="<?= (int) $p['id'] ?>"
                  data-name="<?= htmlspecialchars(mb_strtolower($p['member'])) ?>"
                  data-no="<?= htmlspecialchars(mb_strtolower($p['member_no'])) ?>"
                  data-projname="<?= htmlspecialchars(mb_strtolower($p['project'])) ?>"
                  data-project="<?= (int) $p['project_id'] ?>"
                  data-status="<?= htmlspecialchars($p['status']) ?>"
                  data-plan="<?= htmlspecialchars($p['plan']) ?>"
                  data-usd="<?= number_format($p['usd'], 2, '.', '') ?>"
                  data-amount="<?= number_format((float) $p['amount'], 2, '.', '') ?>"
                  data-pct="<?= round($p['pct'], 2) ?>"
                  data-due="<?= htmlspecialchars((string) $p['next_due']) ?>"
                  <?php if ($show_branch && $p['_branch']): ?>data-branch="<?= (int) $p['_branch']['id'] ?>"<?php endif; ?>>
                <td><input class="check" type="checkbox" data-check aria-label="Select <?= htmlspecialchars($p['member']) ?>'s pledge"></td>
                <td class="num"><?= $i + 1 ?></td>
                <td>
                  <span class="minirow">
                    <?= mu_av($p['member'], 'sm') ?>
                    <span class="minirow__text">
                      <b><?= htmlspecialchars($p['member']) ?></b>
                      <span><?= htmlspecialchars($p['member_no']) ?></span>
                    </span>
                  </span>
                </td>
                <td>
                  <span class="prjchip" style="--c:<?= htmlspecialchars($p['proj_colour']) ?>">
                    <i class="fa-solid <?= htmlspecialchars($p['proj_icon']) ?>" aria-hidden="true"></i>
                    <?= htmlspecialchars($p['project']) ?>
                  </span>
                </td>
                <td class="num"><b><?= pl_money((float) $p['amount'], $p['currency']) ?></b>
                  <?php if ($p['currency'] !== 'USD'): ?><span class="metaline">&asymp; $<?= number_format($p['usd'], 2) ?></span><?php endif; ?>
                </td>
                <td class="num"><?= pl_money((float) $p['paid'], $p['currency']) ?></td>
                <td class="num"><?= pl_money((float) $p['outstanding'], $p['currency']) ?></td>
                <td>
                  <span class="cellbar cellbar--st-<?= htmlspecialchars($p['status']) ?>">
                    <span class="cellbar__track"><span class="cellbar__fill" style="width:<?= round($p['pct'], 1) ?>%"></span></span>
                    <b><?= number_format($p['pct'], 0) ?>%</b>
                  </span>
                </td>
                <td><?= htmlspecialchars($p['plan_full']) ?></td>
                <td>
                  <?php if ($p['next_due'] === null): ?>
                    <span class="muted">&mdash;</span>
                  <?php else: ?>
                    <?= mu_date($p['next_due']) ?>
                    <?php if ($p['next_days'] < 0): ?>
                      <span class="metaline is-over"><?= abs((int) $p['next_days']) ?> days overdue</span>
                    <?php elseif ($p['next_days'] < 14): ?>
                      <span class="metaline is-soon">in <?= (int) $p['next_days'] ?> days</span>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>
                <td><?= $p['last_paid'] ? mu_date($p['last_paid']) : '<span class="muted">Never</span>' ?></td>
                <td><span class="pill pill--st-<?= htmlspecialchars($p['status']) ?>"><?= htmlspecialchars($p['status_label']) ?></span></td>
                <?php if ($show_branch): ?><td><?= mu_branch_chip($p['_branch']) ?></td><?php endif; ?>
                <td class="col-actions">
                  <div class="rowacts">
                    <button class="iconbtn" type="button" data-pledge-open="<?= (int) $p['id'] ?>" aria-label="View <?= htmlspecialchars($p['member']) ?>'s pledge">
                      <i class="fa-regular fa-eye" aria-hidden="true"></i>
                    </button>
                    <?php if ($can_add): ?>
                      <button class="iconbtn" type="button" data-pay="<?= (int) $p['id'] ?>" aria-label="Record a payment">
                        <i class="fa-solid fa-money-bill-wave" aria-hidden="true"></i>
                      </button>
                    <?php endif; ?>
                    <?php if (mu_mod('communication')): ?>
                      <button class="iconbtn" type="button" data-remind="<?= (int) $p['id'] ?>" aria-label="Remind <?= htmlspecialchars($p['member']) ?>">
                        <i class="fa-regular fa-bell" aria-hidden="true"></i>
                      </button>
                    <?php endif; ?>

                    <div class="drop" data-menu>
                      <button class="iconbtn" type="button" aria-haspopup="true" aria-expanded="false" data-menu-btn aria-label="More actions">
                        <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                      </button>
                      <div class="menu menu--end" data-menu-panel hidden>
                        <button class="menu__item" type="button" data-pledge-open="<?= (int) $p['id'] ?>"><i class="fa-regular fa-eye" aria-hidden="true"></i> View Details</button>
                        <?php if ($can_add): ?>
                          <button class="menu__item" type="button" data-pay="<?= (int) $p['id'] ?>"><i class="fa-solid fa-money-bill-wave" aria-hidden="true"></i> Record Payment</button>
                        <?php endif; ?>
                        <?php if (mu_mod('communication')): ?>
                          <button class="menu__item" type="button" data-remind="<?= (int) $p['id'] ?>"><i class="fa-regular fa-bell" aria-hidden="true"></i> Send Reminder</button>
                        <?php endif; ?>
                        <button class="menu__item" type="button" data-toast="Pledge statement queued"><i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i> Pledge Statement</button>
                        <?php if ($can_manage): ?>
                          <button class="menu__item" type="button" data-pledge-edit="<?= (int) $p['id'] ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit Pledge</button>
                          <button class="menu__item" type="button" data-toast="Pledge marked completed"><i class="fa-solid fa-check" aria-hidden="true"></i> Mark Completed</button>
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

      <p class="dt-empty" data-empty hidden>
        <i class="fa-regular fa-face-frown" aria-hidden="true"></i>
        No pledge matches those filters.
        <button class="btn btn--ghost btn--sm" type="button" data-reset-filters>
          <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Reset filters
        </button>
      </p>

      <div class="dt-cards">
        <?php foreach ($pledges as $p): ?>
          <article class="pcard pcard--flat" data-lcard
                   data-name="<?= htmlspecialchars(mb_strtolower($p['member'])) ?>"
                   data-no="<?= htmlspecialchars(mb_strtolower($p['member_no'])) ?>"
                   data-projname="<?= htmlspecialchars(mb_strtolower($p['project'])) ?>"
                   data-project="<?= (int) $p['project_id'] ?>"
                   data-status="<?= htmlspecialchars($p['status']) ?>"
                   data-plan="<?= htmlspecialchars($p['plan']) ?>"
                   data-usd="<?= number_format($p['usd'], 2, '.', '') ?>"
                   data-amount="<?= number_format((float) $p['amount'], 2, '.', '') ?>"
                   data-pct="<?= round($p['pct'], 2) ?>"
                   data-due="<?= htmlspecialchars((string) $p['next_due']) ?>"
                   <?php if ($show_branch && $p['_branch']): ?>data-branch="<?= (int) $p['_branch']['id'] ?>"<?php endif; ?>>
            <header class="pcard__head">
              <?= mu_av($p['member'], 'sm') ?>
              <span class="pcard__text">
                <b><?= htmlspecialchars($p['member']) ?></b>
                <span><?= htmlspecialchars($p['member_no']) ?></span>
              </span>
              <span class="pill pill--st-<?= htmlspecialchars($p['status']) ?>"><?= htmlspecialchars($p['status_label']) ?></span>
            </header>
            <p class="prjchip" style="--c:<?= htmlspecialchars($p['proj_colour']) ?>;margin-bottom:10px">
              <i class="fa-solid <?= htmlspecialchars($p['proj_icon']) ?>" aria-hidden="true"></i>
              <?= htmlspecialchars($p['project']) ?>
            </p>
            <span class="cellbar cellbar--st-<?= htmlspecialchars($p['status']) ?>" style="margin-bottom:10px">
              <span class="cellbar__track"><span class="cellbar__fill" style="width:<?= round($p['pct'], 1) ?>%"></span></span>
              <b><?= number_format($p['pct'], 0) ?>%</b>
            </span>
            <dl class="pcard__dl">
              <div><dt>Pledged</dt><dd><?= pl_money((float) $p['amount'], $p['currency']) ?></dd></div>
              <div><dt>Paid</dt><dd><?= pl_money((float) $p['paid'], $p['currency']) ?></dd></div>
              <div><dt>Outstanding</dt><dd><?= pl_money((float) $p['outstanding'], $p['currency']) ?></dd></div>
              <div><dt>Plan</dt><dd><?= htmlspecialchars($p['plan_full']) ?></dd></div>
              <div><dt>Next due</dt><dd><?= $p['next_due'] ? mu_date($p['next_due']) : '&mdash;' ?></dd></div>
              <div><dt>Last payment</dt><dd><?= $p['last_paid'] ? mu_date($p['last_paid']) : 'Never' ?></dd></div>
            </dl>
            <div class="pcard__acts">
              <button class="btn btn--ghost btn--sm" type="button" data-pledge-open="<?= (int) $p['id'] ?>"><i class="fa-regular fa-eye" aria-hidden="true"></i> View</button>
              <?php if ($can_add): ?>
                <button class="btn btn--ghost btn--sm" type="button" data-pay="<?= (int) $p['id'] ?>"><i class="fa-solid fa-money-bill-wave" aria-hidden="true"></i> Record Payment</button>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- ── bulk actions, floating ── -->
    <div class="bulkbar" id="bulkBar" hidden>
      <span class="bulkbar__count"><b data-bulk-count>0</b> selected</span>
      <span class="bulkbar__sep" aria-hidden="true"></span>
      <?php if (mu_mod('communication')): ?>
        <button class="bulkbar__btn" type="button" id="bulkRemind">
          <i class="fa-regular fa-bell" aria-hidden="true"></i> Send Reminder
        </button>
      <?php endif; ?>
      <button class="bulkbar__btn" type="button" data-toast="Export started">
        <i class="fa-solid fa-file-export" aria-hidden="true"></i> Export Selected
      </button>
      <?php if ($can_manage): ?>
        <button class="bulkbar__btn" type="button" id="bulkComplete">
          <i class="fa-solid fa-check" aria-hidden="true"></i> Mark Completed
        </button>
      <?php endif; ?>
      <span class="bulkbar__sep" aria-hidden="true"></span>
      <button class="bulkbar__close" type="button" id="bulkClose" aria-label="Clear selection">
        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
      </button>
    </div>

    <!-- ── bottom row ── -->
    <div class="chartgrid chartgrid--2">
      <section class="panel">
        <header class="chartcard__head">
          <div>
            <h2><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> Overdue Pledges</h2>
            <p><?= count($overdue) ?> behind by more than a fortnight</p>
          </div>
        </header>
        <?php if (!$overdue): ?>
          <div class="empty">
            <span class="empty__icon" aria-hidden="true"><i class="fa-regular fa-circle-check"></i></span>
            <h3>Nothing is overdue</h3>
            <p>Every pledge is either on schedule or already settled.</p>
          </div>
        <?php else: ?>
          <ul class="odlist">
            <?php foreach ($overdue as $p): ?>
              <li class="odlist__row">
                <?= mu_av($p['member'], 'sm') ?>
                <span class="odlist__text">
                  <b><?= htmlspecialchars($p['member']) ?></b>
                  <span><?= htmlspecialchars($p['project']) ?> &middot; <?= pl_money((float) $p['outstanding'], $p['currency']) ?> outstanding</span>
                </span>
                <span class="odlist__late"><?= abs((int) $p['next_days']) ?> days</span>
                <?php if (mu_mod('communication')): ?>
                  <button class="btn btn--ghost btn--sm" type="button" data-remind="<?= (int) $p['id'] ?>">
                    <i class="fa-regular fa-bell" aria-hidden="true"></i> Remind
                  </button>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>

      <section class="panel">
        <header class="chartcard__head">
          <div>
            <h2><i class="fa-solid fa-chart-pie" aria-hidden="true"></i> Pledge Fulfilment Rate</h2>
            <p><?= count($pledges) ?> pledges</p>
          </div>
        </header>
        <div class="chartbox"><canvas id="fulfilChart"></canvas></div>
      </section>
    </div>
  </section>


  <!-- ══════════════════════════ TAB 3 — ANALYSIS ══════════════════════════ -->
  <section class="tabpanel" id="panel-analysis" role="tabpanel" aria-labelledby="tab-analysis" hidden>

    <div class="chartgrid chartgrid--2">
      <section class="chartcard">
        <header class="chartcard__head">
          <div>
            <h2>Project Funding Progress</h2>
            <p>Raised against target, active projects</p>
          </div>
        </header>
        <div class="chartbox chartbox--tall"><canvas id="progressChart"></canvas></div>
      </section>

      <section class="chartcard">
        <header class="chartcard__head">
          <div>
            <h2>Pledge vs Actual</h2>
            <p>Promised against received, per project</p>
          </div>
        </header>
        <div class="chartbox chartbox--tall"><canvas id="pvaChart"></canvas></div>
      </section>
    </div>

    <section class="chartcard">
      <header class="chartcard__head">
          <div>
            <h2>Contributions Over Time</h2>
            <p>Last twelve months, one line per project</p>
          </div>
        </header>
      <div class="chartbox chartbox--tall"><canvas id="overtimeChart"></canvas></div>
    </section>

    <section class="panel">
      <header class="chartcard__head">
          <div>
            <h2>Top Contributors</h2>
            <p>By what has actually been paid</p>
          </div>
        </header>
      <ol class="rankrow-list">
        <?php foreach ($top as $i => $m): ?>
          <li class="rankrow">
            <span class="rankrow__pos<?= $i < 3 ? ' is-medal is-m' . ($i + 1) : '' ?>">
              <?= $i < 3 ? '<i class="fa-solid fa-medal" aria-hidden="true"></i>' : ($i + 1) ?>
            </span>
            <?= mu_av($m['name'], 'sm') ?>
            <span class="rankrow__text">
              <span class="rankrow__name"><?= htmlspecialchars($m['name']) ?></span>
              <span class="rankrow__meta"><?= htmlspecialchars($m['no']) ?> &middot; <?= $m['count'] ?> pledge<?= $m['count'] === 1 ? '' : 's' ?></span>
            </span>
            <span class="rankamt">$<?= number_format($m['paid'], 2) ?></span>
          </li>
        <?php endforeach; ?>
      </ol>
    </section>
  </section>

<?php endif; ?>
</div>

<?php if ($has_module && $can_view): ?>

<div class="drawer-scrim" data-drawer-scrim hidden></div>

<!-- ══════════════════════ PROJECT DETAIL DRAWER ══════════════════════ -->
<aside class="drawer drawer--wide" id="prjDrawer" role="dialog" aria-modal="true" aria-labelledby="pdTitle" hidden>
  <header class="drawer__head drawer__head--cover" data-pd-cover>
    <span class="drawer__coverico" data-pd-icon aria-hidden="true"><i class="fa-solid fa-church"></i></span>
    <div class="drawer__title">
      <h2 id="pdTitle">Project</h2>
      <p><span data-pd-cat>&mdash;</span></p>
    </div>
    <button class="iconbtn" type="button" data-drawer-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
  </header>

  <div class="drawer__body">
    <div class="ring-wrap">
      <div class="ring" data-pd-ring style="--pct:0"><span class="ring__pct" data-pd-pct>0%</span></div>
      <span class="ring__caption" data-pd-ringcap>of target raised</span>
    </div>

    <div class="figrow">
      <span><b data-pd-target>$0</b>Target</span>
      <span><b data-pd-raised>$0</b>Raised</span>
      <span><b data-pd-out>$0</b>Outstanding</span>
    </div>

    <dl class="deflist">
      <div><dt>Status</dt><dd data-pd-status>&mdash;</dd></div>
      <div><dt>Started</dt><dd data-pd-start>&mdash;</dd></div>
      <div><dt>Target date</dt><dd data-pd-end>&mdash;</dd></div>
      <div><dt>Time remaining</dt><dd data-pd-left>&mdash;</dd></div>
      <div><dt>Pledged</dt><dd data-pd-pledged>&mdash;</dd></div>
      <?php if ($show_branch): ?><div><dt><?= htmlspecialchars(t('branch_singular')) ?></dt><dd data-pd-branch>&mdash;</dd></div><?php endif; ?>
    </dl>

    <!-- The drawer's own tabs: the story, the money, the promises, the log. -->
    <div class="tabs tabs--sub" role="tablist" aria-label="Project detail">
      <button class="is-on" type="button" role="tab" aria-selected="true" data-ptab="overview">Overview</button>
      <button type="button" role="tab" aria-selected="false" tabindex="-1" data-ptab="contributions">Contributions</button>
      <button type="button" role="tab" aria-selected="false" tabindex="-1" data-ptab="pledges">Pledges</button>
      <button type="button" role="tab" aria-selected="false" tabindex="-1" data-ptab="updates">Updates</button>
    </div>

    <div data-ppanel="overview">
      <p class="drawer__prose" data-pd-desc>&mdash;</p>
      <p class="minilist__head">Contributors</p>
      <div class="minilist" data-pd-people></div>
    </div>

    <div data-ppanel="contributions" hidden>
      <div class="minilist" data-pd-cons></div>
    </div>

    <div data-ppanel="pledges" hidden>
      <div class="minilist" data-pd-pledges></div>
    </div>

    <div data-ppanel="updates" hidden>
      <ol class="audit" data-pd-updates></ol>
    </div>
  </div>

  <footer class="drawer__foot drawer__foot--wrap">
    <?php if ($can_add): ?>
      <a class="btn" href="<?= $base_url ?>finance/record.php"><i class="fa-solid fa-plus" aria-hidden="true"></i> Record Contribution</a>
    <?php endif; ?>
    <?php if ($can_manage): ?>
      <button class="btn btn--ghost" type="button" id="pdEdit"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</button>
    <?php endif; ?>
    <button class="btn btn--ghost" type="button" data-toast="Project link copied"><i class="fa-solid fa-share-nodes" aria-hidden="true"></i> Share</button>
  </footer>
</aside>


<!-- ══════════════════════ PLEDGE DETAIL DRAWER ══════════════════════ -->
<aside class="drawer" id="plgDrawer" role="dialog" aria-modal="true" aria-labelledby="ldTitle" hidden>
  <header class="drawer__head">
    <span class="av av--lg" data-ld-av aria-hidden="true">MM</span>
    <div class="drawer__title">
      <h2 id="ldTitle">Member</h2>
      <p><span data-ld-no>&mdash;</span></p>
    </div>
    <button class="iconbtn" type="button" data-drawer-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
  </header>

  <div class="drawer__body">
    <p class="prjchip" data-ld-project style="--c:#662F97;margin-bottom:12px"></p>

    <div class="ring-wrap">
      <div class="ring" data-ld-ring style="--pct:0"><span class="ring__pct" data-ld-pct>0%</span></div>
      <span class="ring__caption" data-ld-ringcap>of the pledge paid</span>
    </div>

    <div class="figrow">
      <span><b data-ld-amount>$0</b>Pledged</span>
      <span><b data-ld-paid>$0</b>Paid</span>
      <span><b data-ld-out>$0</b>Outstanding</span>
    </div>

    <dl class="deflist">
      <div><dt>Payment plan</dt><dd data-ld-plan>&mdash;</dd></div>
      <div><dt>Status</dt><dd data-ld-status>&mdash;</dd></div>
      <div><dt>Next due</dt><dd data-ld-next>&mdash;</dd></div>
      <div><dt>Last payment</dt><dd data-ld-last>&mdash;</dd></div>
    </dl>

    <p class="minilist__head">Payment schedule</p>
    <div class="sched" data-ld-sched></div>

    <p class="minilist__head">Notes</p>
    <p class="drawer__prose" data-ld-notes>&mdash;</p>
  </div>

  <footer class="drawer__foot drawer__foot--wrap">
    <?php if ($can_add): ?>
      <button class="btn" type="button" id="ldPay"><i class="fa-solid fa-money-bill-wave" aria-hidden="true"></i> Record Payment</button>
    <?php endif; ?>
    <?php if (mu_mod('communication')): ?>
      <button class="btn btn--ghost" type="button" id="ldRemind"><i class="fa-regular fa-bell" aria-hidden="true"></i> Send Reminder</button>
    <?php endif; ?>
    <a class="btn btn--ghost" href="<?= $base_url ?>members/all.php"><i class="fa-regular fa-user" aria-hidden="true"></i> View Profile</a>
  </footer>
</aside>


<?php if ($can_manage): ?>
<!-- ══════════════════════ ADD / EDIT PROJECT ══════════════════════ -->
<div class="modal-scrim" id="modalProject" hidden>
  <div class="modal modal--wide" role="dialog" aria-modal="true" aria-labelledby="prTitle">
    <header class="modal__head">
      <h2 id="prTitle">New Project</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>

    <div class="modal__body">
      <div class="form-grid">
        <div class="field field--wide">
          <label for="prName">Project name <span class="req">*</span></label>
          <input class="input" type="text" id="prName" placeholder="Church Building Fund">
        </div>

        <div class="field field--wide">
          <label for="prDesc">Description</label>
          <textarea class="textarea" id="prDesc" rows="3" placeholder="What the money is for, and what done looks like."></textarea>
        </div>

        <div class="field">
          <label for="prCat">Category</label>
          <select class="select" id="prCat">
            <?php foreach (['Construction', 'Transport', 'Equipment', 'Missions', 'Welfare', 'Property', 'Maintenance', 'Youth'] as $c): ?>
              <option><?= $c ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label for="prStatus">Status</label>
          <select class="select" id="prStatus">
            <option value="active">Active</option>
            <option value="completed">Completed</option>
            <option value="on_hold">On Hold</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>

        <div class="field field--wide">
          <label id="prIconLbl">Icon</label>
          <div class="pickrow" role="radiogroup" aria-labelledby="prIconLbl">
            <?php
              $icon_choices = ['fa-church', 'fa-bus', 'fa-volume-high', 'fa-earth-africa',
                               'fa-hand-holding-heart', 'fa-map-location-dot', 'fa-house-chimney-crack',
                               'fa-campground', 'fa-book-bible', 'fa-utensils'];
            ?>
            <?php foreach ($icon_choices as $k => $ic): ?>
              <button class="pickico<?= $k === 0 ? ' is-on' : '' ?>" type="button" role="radio"
                      aria-checked="<?= $k === 0 ? 'true' : 'false' ?>" data-pick-icon="<?= $ic ?>"
                      aria-label="<?= str_replace('fa-', '', $ic) ?>">
                <i class="fa-solid <?= $ic ?>" aria-hidden="true"></i>
              </button>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="field field--wide">
          <label id="prColLbl">Colour</label>
          <div class="pickrow" role="radiogroup" aria-labelledby="prColLbl">
            <?php $colour_choices = ['#662F97', '#0F766E', '#B45309', '#1D4ED8', '#BE185D', '#56287F', '#B91C1C', '#047857']; ?>
            <?php foreach ($colour_choices as $k => $col): ?>
              <button class="pickcol<?= $k === 0 ? ' is-on' : '' ?>" type="button" role="radio"
                      aria-checked="<?= $k === 0 ? 'true' : 'false' ?>" data-pick-colour="<?= $col ?>"
                      style="--c:<?= $col ?>" aria-label="Colour <?= $k + 1 ?>"></button>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="field">
          <label for="prTarget">Target amount <span class="req">*</span></label>
          <div class="daterange">
            <input class="input" type="text" id="prTarget" inputmode="decimal" placeholder="0.00">
            <select class="select" id="prCurrency" aria-label="Currency">
              <?php foreach ($currencies as $c): ?>
                <option value="<?= htmlspecialchars($c['code']) ?>"><?= htmlspecialchars($c['code']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <?php if ($show_branch): ?>
          <div class="field">
            <label for="prBranch"><?= htmlspecialchars(t('branch_singular')) ?></label>
            <select class="select" id="prBranch">
              <option value="">Whole <?= htmlspecialchars(t('org_singular')) ?></option>
              <?php foreach ($branch_options as $b): ?>
                <option value="<?= (int) $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <div class="field">
          <label for="prStart">Start date</label>
          <input class="input" type="date" id="prStart" value="<?= date('Y-m-d') ?>">
        </div>

        <div class="field">
          <label for="prEnd">Target date</label>
          <input class="input" type="date" id="prEnd">
        </div>

        <div class="field field--wide">
          <label for="prCover">Cover image</label>
          <label class="dropzone" for="prCover">
            <i class="fa-regular fa-image" aria-hidden="true"></i>
            <span><strong>Choose an image</strong></span>
            <span class="hint" style="margin:0">JPG or PNG, up to 2&nbsp;MB. Optional.</span>
          </label>
          <input class="offscreen" type="file" id="prCover" accept="image/png,image/jpeg">
          <p class="hint" data-cover-name hidden></p>
        </div>

        <div class="field field--wide">
          <label class="switchrow" for="prPledges">
            <span class="switch"><input type="checkbox" id="prPledges" checked><span class="switch__track" aria-hidden="true"></span></span>
            <span class="switchrow__text"><b>Allow pledges</b><small>Members can promise an amount toward this project.</small></span>
          </label>
          <label class="switchrow" for="prPublic">
            <span class="switch"><input type="checkbox" id="prPublic" checked><span class="switch__track" aria-hidden="true"></span></span>
            <span class="switchrow__text"><b>Show progress publicly</b><small>The raised figure appears on the church's public page.</small></span>
          </label>
        </div>

        <!-- Progress updates, added a row at a time. -->
        <div class="field field--wide">
          <label>Project updates</label>
          <div class="repeat" data-updates></div>
          <button class="btn btn--ghost btn--sm" type="button" id="prAddUpdate">
            <i class="fa-solid fa-plus" aria-hidden="true"></i> Add an update
          </button>
        </div>
      </div>
    </div>

    <footer class="modal__foot">
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <button class="btn" type="button" id="prSave"><i class="fa-solid fa-check" aria-hidden="true"></i> Save Project</button>
    </footer>
  </div>
</div>
<?php endif; ?>


<!-- ══════════════════════════ RECORD PLEDGE ══════════════════════════ -->
<div class="modal-scrim" id="modalPledge" hidden>
  <div class="modal modal--wide" role="dialog" aria-modal="true" aria-labelledby="pgTitle">
    <header class="modal__head">
      <h2 id="pgTitle">Record a Pledge</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>

    <div class="modal__body">
      <div class="form-grid">
        <div class="field">
          <label for="pgMember">Member <span class="req">*</span></label>
          <select class="select" id="pgMember">
            <option value="">Choose a member&hellip;</option>
            <?php foreach ($members_demo as $m): ?>
              <option value="<?= (int) $m['id'] ?>"><?= htmlspecialchars($m['name']) ?> &mdash; <?= htmlspecialchars($m['member_no']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label for="pgProject">Project <span class="req">*</span></label>
          <select class="select" id="pgProject">
            <option value="">Choose a project&hellip;</option>
            <?php foreach ($projects as $p): ?>
              <?php if (!$p['allow_pledges']) { continue; } ?>
              <option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label for="pgAmount">Pledge amount <span class="req">*</span></label>
          <div class="daterange">
            <input class="input" type="text" id="pgAmount" inputmode="decimal" placeholder="0.00" autocomplete="off">
            <select class="select" id="pgCurrency" aria-label="Currency">
              <?php foreach ($currencies as $c): ?>
                <option value="<?= htmlspecialchars($c['code']) ?>"><?= htmlspecialchars($c['code']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="field">
          <label for="pgFirst">First payment date</label>
          <input class="input" type="date" id="pgFirst" value="<?= date('Y-m-d') ?>">
        </div>

        <div class="field field--wide">
          <label id="pgPlanLbl">Payment plan</label>
          <div class="radio-cards" role="radiogroup" aria-labelledby="pgPlanLbl">
            <?php foreach ($pledge_plans as $k => $pl): ?>
              <label class="rcard">
                <input type="radio" name="pgPlan" value="<?= htmlspecialchars($pl['key']) ?>" <?= $pl['key'] === 'monthly' ? 'checked' : '' ?>>
                <span class="rcard__box"><b><?= htmlspecialchars($pl['name']) ?></b></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="field">
          <label for="pgCount">Number of instalments</label>
          <input class="input" type="number" id="pgCount" min="1" max="60" value="12">
        </div>

        <div class="field field--wide">
          <label>Schedule preview</label>
          <!-- Worked out live from the amount, the plan and the first date, so
               the member can see exactly what they are agreeing to. -->
          <div class="schedprev" data-sched-preview>
            <p class="hint">Enter an amount to see the schedule.</p>
          </div>
        </div>

        <div class="field field--wide">
          <label for="pgNotes">Notes</label>
          <textarea class="textarea" id="pgNotes" rows="2" placeholder="Anything worth remembering about this promise."></textarea>
        </div>
      </div>
    </div>

    <footer class="modal__foot">
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <button class="btn" type="button" id="pgSave"><i class="fa-solid fa-check" aria-hidden="true"></i> Save Pledge</button>
    </footer>
  </div>
</div>


<?php if (mu_mod('communication')): ?>
<!-- ══════════════════════════ SEND REMINDER ══════════════════════════ -->
<div class="modal-scrim" id="modalRemind" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="rmTitle">
    <header class="modal__head">
      <h2 id="rmTitle">Send a Reminder</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>

    <div class="modal__body">
      <p class="modal__hint">Going to <b data-rm-count>1</b> <span data-rm-who>member</span>.</p>

      <div class="field">
        <label id="rmChanLbl">Channels</label>
        <div class="chipset" role="group" aria-labelledby="rmChanLbl">
          <label class="chipbox"><input type="checkbox" data-rm-chan value="sms" checked> <span><i class="fa-solid fa-comment-sms" aria-hidden="true"></i> SMS</span></label>
          <label class="chipbox"><input type="checkbox" data-rm-chan value="whatsapp"> <span><i class="fa-brands fa-whatsapp" aria-hidden="true"></i> WhatsApp</span></label>
          <label class="chipbox"><input type="checkbox" data-rm-chan value="email"> <span><i class="fa-regular fa-envelope" aria-hidden="true"></i> Email</span></label>
        </div>
      </div>

      <div class="field">
        <label for="rmTemplate">Template</label>
        <select class="select" id="rmTemplate">
          <option value="gentle">Gentle reminder</option>
          <option value="due">Instalment due</option>
          <option value="overdue">Overdue notice</option>
          <option value="thanks">Thank you and balance</option>
        </select>
      </div>

      <div class="field">
        <label for="rmBody">Message</label>
        <textarea class="textarea" id="rmBody" rows="5"></textarea>
        <p class="hint">
          Merge tags:
          <button class="tagbtn" type="button" data-tag="{member_name}">{member_name}</button>
          <button class="tagbtn" type="button" data-tag="{project_name}">{project_name}</button>
          <button class="tagbtn" type="button" data-tag="{pledge_balance}">{pledge_balance}</button>
          <button class="tagbtn" type="button" data-tag="{next_due_date}">{next_due_date}</button>
        </p>
      </div>
    </div>

    <footer class="modal__foot">
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <button class="btn" type="button" id="rmSend"><i class="fa-regular fa-paper-plane" aria-hidden="true"></i> Send Reminder</button>
    </footer>
  </div>
</div>
<?php endif; ?>

<?php endif; /* has_module && can_view */ ?>

<div class="toasts" data-toasts aria-live="polite" aria-atomic="false"></div>

<!-- ══════════════ DEMO ONLY — REMOVE BEFORE PRODUCTION ══════════════ -->
<details class="demo" aria-label="Demo role switcher">
  <summary class="demo__summary">
    <i class="fa-solid fa-user-gear" aria-hidden="true"></i>
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
<!-- ═══════════════════════════ END DEMO ═══════════════════════════ -->

<?php if ($has_module && $can_view): ?>
<?php
/* What the drawers and charts read. Assembled here so the browser is handed
   finished figures rather than recomputing what PHP already worked out.
   LATER: these become endpoints. */
$cons_by_project = [];
foreach ($contributions_demo as $c) {
    if ($c['project'] === null) { continue; }
    $m = $mem_by_id[$c['member_id']] ?? null;
    $cons_by_project[(int) $c['project']][] = [
        'member' => $m['name'] ?? 'Anonymous',
        'date'   => date('Y-m-d', strtotime('-' . (int) $c['days_ago'] . ' days')),
        'amount' => pl_money((float) $c['amount'], $c['currency']),
        'usd'    => round(pl_usd((float) $c['amount'], $c['currency']), 2),
    ];
}

$JS_PROJECTS = [];
foreach ($projects as $p) {
    $mine = array_values(array_filter($pledges, static fn($x) => (int) $x['project_id'] === (int) $p['id']));
    $JS_PROJECTS[] = [
        'id' => $p['id'], 'name' => $p['name'], 'category' => $p['category'],
        'icon' => $p['icon'], 'colour' => $p['colour'],
        'status' => $p['status'], 'statusLabel' => ucwords(str_replace('_', ' ', $p['status'])),
        'health' => $p['health'], 'currency' => $p['currency'],
        'target' => (float) $p['target'], 'raised' => (float) $p['raised'],
        'pledged' => (float) $p['pledged'], 'outstanding' => (float) $p['outstanding'],
        'pct' => round($p['pct_raised'], 1), 'pctPledged' => round($p['pct_pledged'], 1),
        'start' => mu_date($p['start_date']), 'end' => mu_date($p['target_date']),
        'daysLeft' => $p['days_left'], 'desc' => $p['description'],
        'branch' => $p['_branch']['name'] ?? null,
        'people' => array_map(static fn($m) => ['name' => $m['name'], 'no' => $m['member_no']], $p['people']),
        'updates' => $p['updates'],
        'cons' => $cons_by_project[(int) $p['id']] ?? [],
        'pledges' => array_map(static fn($x) => [
            'member' => $x['member'], 'amount' => pl_money((float) $x['amount'], $x['currency']),
            'paid' => pl_money((float) $x['paid'], $x['currency']),
            'status' => $x['status'], 'statusLabel' => $x['status_label'],
            'pct' => round($x['pct'], 0),
        ], $mine),
    ];
}

$JS_PLEDGES = [];
foreach ($pledges as $p) {
    $JS_PLEDGES[] = [
        'id' => $p['id'], 'member' => $p['member'], 'no' => $p['member_no'],
        'project' => $p['project'], 'colour' => $p['proj_colour'], 'icon' => $p['proj_icon'],
        'amount' => pl_money((float) $p['amount'], $p['currency']),
        'paid' => pl_money((float) $p['paid'], $p['currency']),
        'out' => pl_money((float) $p['outstanding'], $p['currency']),
        'balanceRaw' => round((float) $p['outstanding'], 2),
        'sym' => pl_sym($p['currency']),
        'pct' => round($p['pct'], 0),
        'plan' => $p['plan_full'], 'status' => $p['status'], 'statusLabel' => $p['status_label'],
        'next' => $p['next_due'] ? mu_date($p['next_due']) : null,
        'nextDays' => $p['next_days'],
        'last' => $p['last_paid'] ? mu_date($p['last_paid']) : null,
        'notes' => $p['notes'],
        'schedule' => array_map(static fn($s) => [
            'no' => $s['no'], 'due' => mu_date($s['due']),
            'amount' => pl_money($s['amount'], $p['currency']), 'state' => $s['state'],
        ], $p['schedule']),
    ];
}

/* Only active projects go on the comparison charts — a completed or cancelled
   project would flatten the scale without telling anyone anything. */
$chart_projects = array_values(array_filter($projects, static fn($p) => $p['status'] === 'active'));
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function () {
  'use strict';

  var $  = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

  var PROJECTS = <?= json_encode($JS_PROJECTS, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  var PLEDGES  = <?= json_encode($JS_PLEDGES,  JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  var prjById = {}, plgById = {};
  PROJECTS.forEach(function (p) { prjById[p.id] = p; });
  PLEDGES.forEach(function (p) { plgById[p.id] = p; });

  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ───────────────────────────── toasts ───────────────────────────── */
  var toasts = $('[data-toasts]');
  function toast(msg, kind) {
    if (!toasts) { return; }
    var icons = { success: 'fa-circle-check', error: 'fa-circle-exclamation', info: 'fa-circle-info' };
    var el = document.createElement('div');
    el.className = 'toast toast--' + (kind || 'info');
    el.innerHTML = '<i class="fa-solid ' + (icons[kind] || icons.info) + '" aria-hidden="true"></i>'
                 + '<span></span><button class="toast__close" type="button" aria-label="Dismiss">'
                 + '<i class="fa-solid fa-xmark" aria-hidden="true"></i></button>';
    $('span', el).textContent = msg;
    toasts.appendChild(el);
    var kill = function () { el.classList.add('is-out'); setTimeout(function () { el.remove(); }, 250); };
    $('.toast__close', el).addEventListener('click', kill);
    setTimeout(kill, 4200);
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-toast]');
    if (b) { closeOwnMenu(b); toast(b.getAttribute('data-toast'), 'success'); }
  }, true);

  /* footer.php stops click propagation inside [data-menu-panel], so anything
     in a dropdown is handled on the capture phase and closes its own menu. */
  function closeOwnMenu(el) {
    var drop = el.closest('[data-menu]');
    if (!drop) { return; }
    var panel = $('[data-menu-panel]', drop);
    var btn   = $('[data-menu-btn]', drop);
    if (panel) { panel.hidden = true; }
    if (btn)   { btn.setAttribute('aria-expanded', 'false'); }
    drop.classList.remove('is-open');
  }

  /* ─────────────────────── the numbers count up ─────────────────────── */
  /* Scoped to the stat strip. An unscoped [data-count] would also match a
     table row using the attribute as a sort value and wipe its cells. */
  $$('.stat-tile [data-count]').forEach(function (el) {
    var target = parseFloat(el.getAttribute('data-count')) || 0;
    if (reduced) { el.textContent = target.toLocaleString(); return; }
    var t0 = null, dur = 900;
    function step(ts) {
      if (t0 === null) { t0 = ts; }
      var k = Math.min(1, (ts - t0) / dur);
      el.textContent = Math.round(target * (1 - Math.pow(1 - k, 3))).toLocaleString();
      if (k < 1) { requestAnimationFrame(step); }
    }
    requestAnimationFrame(step);
  });

  /* ─────────────────────────── the main tabs ─────────────────────────── */
  var tabs = $$('[data-tab]');
  function setTab(name) {
    tabs.forEach(function (t) {
      var on = t.getAttribute('data-tab') === name;
      t.setAttribute('aria-selected', String(on));
      t.tabIndex = on ? 0 : -1;
      t.classList.toggle('is-on', on);
    });
    ['projects', 'pledges', 'analysis'].forEach(function (n) {
      var panel = $('#panel-' + n);
      if (panel) { panel.hidden = n !== name; }
    });
    if (name === 'analysis') { drawAnalysis(); }
    if (name === 'pledges')  { drawFulfilment(); }
  }
  tabs.forEach(function (t) {
    t.addEventListener('click', function () { setTab(t.getAttribute('data-tab')); });
    t.addEventListener('keydown', function (e) {
      var i = tabs.indexOf(t), n = null;
      if (e.key === 'ArrowRight') { n = tabs[(i + 1) % tabs.length]; }
      if (e.key === 'ArrowLeft')  { n = tabs[(i - 1 + tabs.length) % tabs.length]; }
      if (n) { e.preventDefault(); n.focus(); setTab(n.getAttribute('data-tab')); }
    });
  });

  /* ──────────────────── projects: cards or table ──────────────────── */
  $$('[data-pview]').forEach(function (b) {
    b.addEventListener('click', function () {
      var which = b.getAttribute('data-pview');
      $$('[data-pview]').forEach(function (o) {
        var on = o === b;
        o.classList.toggle('is-on', on);
        o.setAttribute('aria-pressed', String(on));
      });
      $$('[data-pane]').forEach(function (pane) {
        pane.hidden = pane.getAttribute('data-pane') !== which;
      });
    });
  });

  /* ═══════════════════════ filtering the pledges ═══════════════════════ */
  var search  = $('#fSearch');
  var chipBox = $('[data-filter-chips]');
  var emptyEl = $('[data-empty]');

  var FILTER_LABEL = {
    fProject: 'Project', fStatus: 'Status', fPlan: 'Plan',
    fMin: 'Min', fMax: 'Max', fFrom: 'Due from', fTo: 'Due to'<?php if ($show_branch): ?>,
    fBranch: '<?= addslashes(t('branch_singular')) ?>'<?php endif; ?>
  };

  function activeFilters() {
    var f = { q: (search && search.value || '').trim().toLowerCase() };
    $$('[data-filter]').forEach(function (el) { f[el.id] = el.value; });
    return f;
  }

  function matches(el, f) {
    if (f.q) {
      var hay = [el.getAttribute('data-name'), el.getAttribute('data-no'),
                 el.getAttribute('data-projname'), el.getAttribute('data-amount'),
                 el.getAttribute('data-usd')].join(' ');
      if (hay.indexOf(f.q) === -1) { return false; }
    }
    if (f.fProject && el.getAttribute('data-project') !== f.fProject) { return false; }
    if (f.fStatus  && el.getAttribute('data-status')  !== f.fStatus)  { return false; }
    if (f.fPlan    && el.getAttribute('data-plan')    !== f.fPlan)    { return false; }

    var usd = parseFloat(el.getAttribute('data-usd'));
    if (f.fMin && !isNaN(usd) && usd < parseFloat(f.fMin)) { return false; }
    if (f.fMax && !isNaN(usd) && usd > parseFloat(f.fMax)) { return false; }

    /* A settled pledge has no next due date, so a due-date filter excludes it
       rather than silently treating the blank as a match. */
    var due = el.getAttribute('data-due');
    if (f.fFrom && (!due || due < f.fFrom)) { return false; }
    if (f.fTo   && (!due || due > f.fTo))   { return false; }

    <?php if ($show_branch): ?>
    if (f.fBranch && el.getAttribute('data-branch') !== f.fBranch) { return false; }
    <?php endif; ?>
    return true;
  }

  function paintChips(f) {
    if (!chipBox) { return; }
    chipBox.innerHTML = '';
    var live = [];

    if (f.q) { live.push(['q', 'Search', f.q]); }
    $$('[data-filter]').forEach(function (el) {
      if (!el.value) { return; }
      var label = FILTER_LABEL[el.id] || el.id;
      var shown = el.tagName === 'SELECT' ? el.options[el.selectedIndex].text : el.value;
      live.push([el.id, label, shown]);
    });

    live.forEach(function (row) {
      var chip = document.createElement('span');
      chip.className = 'fchip';
      chip.innerHTML = '<span></span><button type="button" aria-label="Remove this filter">'
                     + '<i class="fa-solid fa-xmark" aria-hidden="true"></i></button>';
      $('span', chip).textContent = row[1] + ': ' + row[2];
      $('button', chip).addEventListener('click', function () {
        if (row[0] === 'q') { search.value = ''; } else { $('#' + row[0]).value = ''; }
        apply();
      });
      chipBox.appendChild(chip);
    });

    chipBox.hidden = live.length === 0;
    var n = $('[data-filter-n]');
    if (n) { n.textContent = live.length; n.hidden = live.length === 0; }
  }

  function apply() {
    var f = activeFilters(), shown = 0;
    $$('[data-lrow], [data-lcard]').forEach(function (el) {
      var ok = matches(el, f);
      el.hidden = !ok;
      if (ok && el.hasAttribute('data-lrow')) { shown++; }
    });

    /* Renumber what is left, so the # column always reads 1..n. */
    var i = 0;
    $$('[data-lrow]').forEach(function (r) {
      if (r.hidden) { return; }
      var c = $('.num', r);
      if (c) { c.textContent = ++i; }
    });

    if (emptyEl) { emptyEl.hidden = shown !== 0; }
    paintChips(f);
    paintBulk();
  }

  if (search) {
    search.addEventListener('input', function () {
      var x = $('[data-search-clear]');
      if (x) { x.hidden = !search.value; }
      apply();
    });
  }
  var clearBtn = $('[data-search-clear]');
  if (clearBtn) {
    clearBtn.addEventListener('click', function () { search.value = ''; clearBtn.hidden = true; apply(); search.focus(); });
  }
  $$('[data-filter]').forEach(function (el) {
    el.addEventListener('change', apply);
    /* A number or date field only fires change on blur, which leaves the table
       stale while someone is still typing a minimum. */
    if (el.tagName === 'INPUT') { el.addEventListener('input', apply); }
  });
  $$('[data-reset-filters]').forEach(function (b) {
    b.addEventListener('click', function () {
      if (search) { search.value = ''; }
      $$('[data-filter]').forEach(function (el) { el.value = ''; });
      if (clearBtn) { clearBtn.hidden = true; }
      apply();
      toast('Filters cleared', 'info');
    });
  });

  var fToggle = $('#fToggle');
  if (fToggle) {
    fToggle.addEventListener('click', function () {
      var open = $('#filters').classList.toggle('is-open');
      fToggle.setAttribute('aria-expanded', String(open));
    });
  }

  /* ───────────────────────────── sorting ───────────────────────────── */
  $$('#plTable th.is-sortable').forEach(function (th) {
    th.addEventListener('click', function () {
      var key  = th.getAttribute('data-sort');
      /* aria-sort still holds the previous state, so the direction has to be
         read from where the click is taking the column, not where it was. */
      var desc = th.getAttribute('aria-sort') === 'descending';
      var dir  = desc ? -1 : 1;

      $$('#plTable th').forEach(function (o) { o.removeAttribute('aria-sort'); });
      th.setAttribute('aria-sort', desc ? 'ascending' : 'descending');

      var numeric = ['usd', 'pct'].indexOf(key) !== -1;
      var body = $('#plTable tbody');
      var rows = $$('tr', body);
      rows.sort(function (a, b) {
        var x = a.getAttribute('data-' + key) || '';
        var y = b.getAttribute('data-' + key) || '';
        if (numeric) { return (parseFloat(y) - parseFloat(x)) * dir; }
        /* A blank next-due sorts last whichever way the column is pointing. */
        if (!x) { return 1; }
        if (!y) { return -1; }
        return y.localeCompare(x) * dir;
      });
      rows.forEach(function (r) { body.appendChild(r); });
      apply();
    });
  });

  /* ──────────────────────── bulk selection ──────────────────────── */
  var bulkBar = $('#bulkBar');
  function selected() { return $$('#plTable tbody [data-check]').filter(function (c) { return c.checked && !c.closest('tr').hidden; }); }
  function paintBulk() {
    if (!bulkBar) { return; }
    var n = selected().length;
    bulkBar.hidden = n === 0;
    var c = $('[data-bulk-count]');
    if (c) { c.textContent = n; }
    document.body.classList.toggle('has-bulkbar', n > 0);
    var all = $('[data-check-all]');
    if (all) {
      var boxes = $$('#plTable tbody [data-check]').filter(function (b) { return !b.closest('tr').hidden; });
      all.checked = boxes.length > 0 && n === boxes.length;
      all.indeterminate = n > 0 && n < boxes.length;
    }
  }
  $$('#plTable [data-check]').forEach(function (c) { c.addEventListener('change', paintBulk); });
  var checkAll = $('[data-check-all]');
  if (checkAll) {
    checkAll.addEventListener('change', function () {
      $$('#plTable tbody [data-check]').forEach(function (c) {
        if (!c.closest('tr').hidden) { c.checked = checkAll.checked; }
      });
      paintBulk();
    });
  }
  var bulkClose = $('#bulkClose');
  if (bulkClose) {
    bulkClose.addEventListener('click', function () {
      $$('#plTable [data-check]').forEach(function (c) { c.checked = false; });
      paintBulk();
    });
  }
  var bComplete = $('#bulkComplete');
  if (bComplete) { bComplete.addEventListener('click', function () { toast(selected().length + ' pledges marked completed', 'success'); }); }

  /* ═════════════════════════════ drawers ═════════════════════════════ */
  var scrim = $('[data-drawer-scrim]');
  var prjD  = $('#prjDrawer');
  var plgD  = $('#plgDrawer');
  var lastFocus = null;

  function openDrawer(d) {
    lastFocus = document.activeElement;
    d.hidden = false; scrim.hidden = false;
    document.body.style.overflow = 'hidden';
    $('[data-drawer-close]', d).focus();
  }
  function closeDrawers() {
    [prjD, plgD].forEach(function (d) { if (d) { d.hidden = true; } });
    scrim.hidden = true; document.body.style.overflow = '';
    if (lastFocus) { lastFocus.focus(); lastFocus = null; }
  }
  scrim.addEventListener('click', closeDrawers);
  $$('[data-drawer-close]').forEach(function (b) { b.addEventListener('click', closeDrawers); });

  function initials(name) {
    var p = String(name).trim().split(/\s+/);
    return (p[0].charAt(0) + (p.length > 1 ? p[p.length - 1].charAt(0) : '')).toUpperCase();
  }

  /* A real CRC-32, so an avatar's colour matches the one PHP picked. */
  var CRC = (function () {
    var t = [], c, n, k;
    for (n = 0; n < 256; n++) {
      c = n;
      for (k = 0; k < 8; k++) { c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1); }
      t[n] = c >>> 0;
    }
    return t;
  })();
  function crc32(str) {
    var c = 0xFFFFFFFF, i, b, bytes = unescape(encodeURIComponent(str));
    for (i = 0; i < bytes.length; i++) {
      b = bytes.charCodeAt(i);
      c = (CRC[(c ^ b) & 0xFF] ^ (c >>> 8)) >>> 0;
    }
    return (c ^ 0xFFFFFFFF) >>> 0;
  }
  function avatar(name, size) {
    return '<span class="av av--' + (size || 'sm') + ' av-c' + (crc32(name) % 10) + '" aria-hidden="true">'
         + initials(name) + '</span>';
  }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

  var currentProject = null, currentPledge = null;

  /* ── the project drawer ── */
  function openProject(id) {
    var p = prjById[id];
    if (!p) { return; }
    currentProject = p;

    $('#pdTitle').textContent = p.name;
    $('[data-pd-cat]').textContent = p.category;
    $('[data-pd-cover]').style.setProperty('--c', p.colour);
    $('[data-pd-icon]').innerHTML = '<i class="fa-solid ' + p.icon + '"></i>';

    var ring = $('[data-pd-ring]');
    ring.style.setProperty('--pct', p.pct);
    ring.style.setProperty('--c', p.colour);
    $('[data-pd-pct]').textContent = p.pct + '%';
    $('[data-pd-ringcap]').textContent = money(p.raised, p.currency) + ' of ' + money(p.target, p.currency);

    $('[data-pd-target]').textContent = money(p.target, p.currency);
    $('[data-pd-raised]').textContent = money(p.raised, p.currency);
    $('[data-pd-out]').textContent    = money(p.outstanding, p.currency);
    $('[data-pd-status]').textContent = p.statusLabel;
    $('[data-pd-start]').textContent  = p.start;
    $('[data-pd-end]').textContent    = p.end;
    $('[data-pd-left]').textContent   = p.status === 'completed' ? 'Completed'
      : (p.daysLeft < 0 ? Math.abs(p.daysLeft) + ' days overdue' : p.daysLeft + ' days left');
    $('[data-pd-pledged]').textContent = money(p.pledged, p.currency) + ' (' + p.pctPledged + '% of target)';
    var bEl = $('[data-pd-branch]');
    if (bEl) { bEl.textContent = p.branch || '—'; }
    $('[data-pd-desc]').textContent = p.desc;

    var people = $('[data-pd-people]');
    people.innerHTML = '';
    p.people.forEach(function (m) {
      var row = document.createElement('div');
      row.className = 'minirow';
      row.innerHTML = avatar(m.name, 'sm')
        + '<span class="minirow__text"><b>' + esc(m.name) + '</b><span>' + esc(m.no) + '</span></span>';
      people.appendChild(row);
    });

    var cons = $('[data-pd-cons]');
    cons.innerHTML = '';
    if (!p.cons.length) { cons.innerHTML = '<p class="hint">No contributions have been recorded against this project yet.</p>'; }
    p.cons.forEach(function (c) {
      var row = document.createElement('div');
      row.className = 'minirow';
      row.innerHTML = avatar(c.member, 'sm')
        + '<span class="minirow__text"><b>' + esc(c.member) + '</b><span>' + esc(c.date) + '</span></span>'
        + '<span class="minirow__amt">' + esc(c.amount) + '</span>';
      cons.appendChild(row);
    });

    var pl = $('[data-pd-pledges]');
    pl.innerHTML = '';
    if (!p.pledges.length) { pl.innerHTML = '<p class="hint">Nobody has pledged toward this project.</p>'; }
    p.pledges.forEach(function (x) {
      var row = document.createElement('div');
      row.className = 'minirow';
      row.innerHTML = avatar(x.member, 'sm')
        + '<span class="minirow__text"><b>' + esc(x.member) + '</b><span>' + esc(x.paid) + ' of ' + esc(x.amount) + '</span></span>'
        + '<span class="pill pill--st-' + x.status + '">' + esc(x.statusLabel) + '</span>';
      pl.appendChild(row);
    });

    var up = $('[data-pd-updates]');
    up.innerHTML = '';
    if (!p.updates.length) { up.innerHTML = '<p class="hint">No progress updates have been posted.</p>'; }
    p.updates.forEach(function (u) {
      var li = document.createElement('li');
      li.innerHTML = '<span class="audit__dot" aria-hidden="true"></span>'
        + '<span class="audit__text"><b>' + esc(u.title) + '</b>'
        + '<span>' + esc(u.date) + '</span>'
        + '<span>' + esc(u.body) + '</span>'
        + (u.photos ? '<span class="photocount"><i class="fa-regular fa-image" aria-hidden="true"></i> '
            + u.photos + ' photo' + (u.photos === 1 ? '' : 's') + '</span>' : '')
        + '</span>';
      up.appendChild(li);
    });

    setProjectTab('overview');
    openDrawer(prjD);
  }

  function money(n, code) {
    var syms = { USD: '$', ZWG: 'ZWG', ZAR: 'R', GBP: '£' };
    return (syms[code] || '$') + Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function setProjectTab(name) {
    $$('[data-ptab]').forEach(function (t) {
      var on = t.getAttribute('data-ptab') === name;
      t.setAttribute('aria-selected', String(on));
      t.tabIndex = on ? 0 : -1;
      t.classList.toggle('is-on', on);
    });
    $$('[data-ppanel]').forEach(function (pane) {
      pane.hidden = pane.getAttribute('data-ppanel') !== name;
    });
  }
  $$('[data-ptab]').forEach(function (t) {
    t.addEventListener('click', function () { setProjectTab(t.getAttribute('data-ptab')); });
  });

  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-project-open]');
    if (b) { closeOwnMenu(b); openProject(b.getAttribute('data-project-open')); }
  }, true);

  /* ── the pledge drawer ── */
  function openPledge(id) {
    var p = plgById[id];
    if (!p) { return; }
    currentPledge = p;

    var av = $('[data-ld-av]');
    av.className = 'av av--lg av-c' + (crc32(p.member) % 10);
    av.textContent = initials(p.member);
    $('#ldTitle').textContent = p.member;
    $('[data-ld-no]').textContent = p.no;

    var chip = $('[data-ld-project]');
    chip.style.setProperty('--c', p.colour);
    chip.innerHTML = '<i class="fa-solid ' + p.icon + '" aria-hidden="true"></i> ' + esc(p.project);

    var ring = $('[data-ld-ring]');
    ring.style.setProperty('--pct', p.pct);
    ring.style.setProperty('--c', p.colour);
    $('[data-ld-pct]').textContent = p.pct + '%';
    $('[data-ld-ringcap]').textContent = p.paid + ' of ' + p.amount + ' paid';

    $('[data-ld-amount]').textContent = p.amount;
    $('[data-ld-paid]').textContent   = p.paid;
    $('[data-ld-out]').textContent    = p.out;
    $('[data-ld-plan]').textContent   = p.plan;
    $('[data-ld-status]').innerHTML   = '<span class="pill pill--st-' + p.status + '">' + esc(p.statusLabel) + '</span>';
    $('[data-ld-next]').textContent   = p.next || '—';
    $('[data-ld-last]').textContent   = p.last || 'Never';
    $('[data-ld-notes]').textContent  = p.notes || 'No notes.';

    /* Every instalment, with the ones still owed offering the action. */
    var box = $('[data-ld-sched]');
    box.innerHTML = '';
    p.schedule.forEach(function (s) {
      var icons = { paid: 'fa-circle-check', due: 'fa-regular fa-clock', overdue: 'fa-circle-exclamation' };
      var row = document.createElement('div');
      row.className = 'sched__row is-' + s.state;
      row.innerHTML =
        '<span class="sched__ico" aria-hidden="true"><i class="' + (s.state === 'due' ? 'fa-regular fa-clock' : 'fa-solid ' + icons[s.state]) + '"></i></span>'
        + '<span class="sched__text"><b>Instalment ' + s.no + '</b><span>' + esc(s.due) + '</span></span>'
        + '<span class="sched__amt">' + esc(s.amount) + '</span>'
        <?php if ($can_add): ?>
        + (s.state === 'paid' ? '<span class="sched__done">Paid</span>'
            : '<button class="btn btn--ghost btn--sm" type="button" data-pay="' + p.id + '">Record Payment</button>')
        <?php else: ?>
        + '<span class="sched__done">' + (s.state === 'paid' ? 'Paid' : s.state === 'overdue' ? 'Overdue' : 'Due') + '</span>'
        <?php endif; ?>
        ;
      box.appendChild(row);
    });

    openDrawer(plgD);
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-pledge-open]');
    if (b) { closeOwnMenu(b); openPledge(b.getAttribute('data-pledge-open')); }
  }, true);

  /* ═════════════════════════════ modals ═════════════════════════════ */
  function openModal(m) { m.hidden = false; document.body.style.overflow = 'hidden'; var c = $('[data-close]', m); if (c) { c.focus(); } }
  function closeModal(m) {
    m.hidden = true;
    if ($$('.modal-scrim').every(function (x) { return x.hidden; }) && prjD.hidden && plgD.hidden) {
      document.body.style.overflow = '';
    }
  }
  document.addEventListener('click', function (e) {
    var cl = e.target.closest('[data-close]');
    if (cl) { closeModal(cl.closest('.modal-scrim')); return; }
    if (e.target.classList.contains('modal-scrim')) { closeModal(e.target); }
  });

  /* ── new / edit project ── */
  var prModal = $('#modalProject');
  if (prModal) {
    function openProjectForm(p) {
      $('#prTitle').textContent = p ? 'Edit Project' : 'New Project';
      $('#prName').value    = p ? p.name : '';
      $('#prDesc').value    = p ? p.desc : '';
      $('#prStatus').value  = p ? p.status : 'active';
      $('#prTarget').value  = p ? String(p.target) : '';
      var cat = $('#prCat');
      if (p) { $$('option', cat).forEach(function (o) { if (o.text === p.category) { cat.value = o.value; } }); }
      else   { cat.selectedIndex = 0; }

      if (p) {
        $$('[data-pick-icon]').forEach(function (b) {
          var on = b.getAttribute('data-pick-icon') === p.icon;
          b.classList.toggle('is-on', on); b.setAttribute('aria-checked', String(on));
        });
        $$('[data-pick-colour]').forEach(function (b) {
          var on = b.getAttribute('data-pick-colour').toUpperCase() === p.colour.toUpperCase();
          b.classList.toggle('is-on', on); b.setAttribute('aria-checked', String(on));
        });
      }

      var box = $('[data-updates]');
      box.innerHTML = '';
      if (p) { p.updates.forEach(function (u) { addUpdateRow(u.date, u.title, u.body); }); }
      openModal(prModal);
    }

    function addUpdateRow(date, title, body) {
      var box = $('[data-updates]');
      var row = document.createElement('div');
      row.className = 'repeat__row';
      row.innerHTML =
        '<input class="input" type="date" aria-label="Update date">'
      + '<input class="input" type="text" placeholder="What happened" aria-label="Update title">'
      + '<textarea class="textarea" rows="2" placeholder="A sentence or two" aria-label="Update detail"></textarea>'
      + '<button class="iconbtn" type="button" data-drop-row aria-label="Remove this update">'
      + '<i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>';
      $$('input, textarea', row)[0].value = date || '';
      $$('input, textarea', row)[1].value = title || '';
      $$('input, textarea', row)[2].value = body || '';
      $('[data-drop-row]', row).addEventListener('click', function () { row.remove(); });
      box.appendChild(row);
    }

    $('#prAddUpdate').addEventListener('click', function () { addUpdateRow('', '', ''); });

    $$('[data-pick-icon]').forEach(function (b) {
      b.addEventListener('click', function () {
        $$('[data-pick-icon]').forEach(function (o) {
          var on = o === b; o.classList.toggle('is-on', on); o.setAttribute('aria-checked', String(on));
        });
      });
    });
    $$('[data-pick-colour]').forEach(function (b) {
      b.addEventListener('click', function () {
        $$('[data-pick-colour]').forEach(function (o) {
          var on = o === b; o.classList.toggle('is-on', on); o.setAttribute('aria-checked', String(on));
        });
      });
    });

    var cover = $('#prCover');
    cover.addEventListener('change', function () {
      var p = $('[data-cover-name]');
      if (cover.files && cover.files[0]) { p.textContent = cover.files[0].name; p.hidden = false; }
      else { p.hidden = true; }
    });

    var nb = $('#btnNewProject');
    if (nb) { nb.addEventListener('click', function () { openProjectForm(null); }); }
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-project-edit]');
      if (b) { closeOwnMenu(b); openProjectForm(prjById[b.getAttribute('data-project-edit')]); }
    }, true);
    var pe = $('#pdEdit');
    if (pe) { pe.addEventListener('click', function () { if (currentProject) { openProjectForm(currentProject); } }); }

    $('#prSave').addEventListener('click', function () {
      if (!$('#prName').value.trim()) { toast('The project needs a name', 'error'); $('#prName').focus(); return; }
      if (!parseFloat($('#prTarget').value)) { toast('The project needs a target amount', 'error'); $('#prTarget').focus(); return; }
      closeModal(prModal);
      toast('Project saved', 'success');
    });
  }

  /* ── record a pledge, with its schedule worked out live ── */
  var pgModal = $('#modalPledge');
  if (pgModal) {
    function planMeta() {
      var v = ($('input[name="pgPlan"]:checked') || {}).value || 'monthly';
      return v;
    }
    function addPeriod(d, plan, k) {
      var x = new Date(d.getTime());
      if (plan === 'weekly')         { x.setDate(x.getDate() + 7 * k); }
      else if (plan === 'quarterly') { x.setMonth(x.getMonth() + 3 * k); }
      else if (plan === 'custom')    { x.setMonth(x.getMonth() + 2 * k); }
      else if (plan === 'one_off')   { /* one payment, one date */ }
      else                           { x.setMonth(x.getMonth() + k); }
      return x;
    }
    function paintSchedule() {
      var box   = $('[data-sched-preview]');
      var total = parseFloat(String($('#pgAmount').value).replace(/[^0-9.]/g, ''));
      var plan  = planMeta();
      var n     = plan === 'one_off' ? 1 : Math.max(1, parseInt($('#pgCount').value, 10) || 1);
      var first = $('#pgFirst').value;
      var code  = $('#pgCurrency').value;

      $('#pgCount').disabled = plan === 'one_off';

      if (!total || total <= 0 || !first) {
        box.innerHTML = '<p class="hint">Enter an amount to see the schedule.</p>';
        return;
      }
      var each = total / n;
      var d0 = new Date(first + 'T00:00:00');
      var html = '<table class="sptable"><thead><tr><th>#</th><th>Due</th><th style="text-align:right">Amount</th></tr></thead><tbody>';
      for (var k = 0; k < Math.min(n, 24); k++) {
        var d = addPeriod(d0, plan, k);
        html += '<tr><td>' + (k + 1) + '</td><td>'
             + d.toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' })
             + '</td><td style="text-align:right">' + money(each, code) + '</td></tr>';
      }
      html += '</tbody></table>';
      if (n > 24) { html += '<p class="hint">' + (n - 24) + ' further instalments not shown.</p>'; }
      html += '<p class="hint"><b>' + n + '</b> instalment' + (n === 1 ? '' : 's') + ' of <b>'
           + money(each, code) + '</b>, totalling <b>' + money(total, code) + '</b>.</p>';
      box.innerHTML = html;
    }
    ['#pgAmount', '#pgCount', '#pgFirst', '#pgCurrency'].forEach(function (sel) {
      $(sel).addEventListener('input', paintSchedule);
      $(sel).addEventListener('change', paintSchedule);
    });
    $$('input[name="pgPlan"]').forEach(function (r) { r.addEventListener('change', paintSchedule); });

    var nb2 = $('#btnNewPledge');
    if (nb2) { nb2.addEventListener('click', function () { paintSchedule(); openModal(pgModal); }); }

    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-pledge-edit]');
      if (b) { closeOwnMenu(b); paintSchedule(); openModal(pgModal); }
    }, true);

    $('#pgSave').addEventListener('click', function () {
      if (!$('#pgMember').value) { toast('Choose a member', 'error'); $('#pgMember').focus(); return; }
      if (!$('#pgProject').value) { toast('Choose a project', 'error'); $('#pgProject').focus(); return; }
      if (!parseFloat(String($('#pgAmount').value).replace(/[^0-9.]/g, ''))) { toast('Enter a pledge amount', 'error'); $('#pgAmount').focus(); return; }
      closeModal(pgModal);
      toast('Pledge recorded', 'success');
    });
  }

  /* Recording a payment belongs on the contribution form, tagged to the
     project — so this hands over rather than duplicating that page. */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-pay]');
    if (b) { closeOwnMenu(b); toast('Opening the contribution form for this pledge', 'info'); }
  }, true);

  /* ── reminders ── */
  var rmModal = $('#modalRemind');
  if (rmModal) {
    var TEMPLATES = {
      gentle:  'Dear {member_name}, thank you for your pledge toward {project_name}. Your outstanding balance is {pledge_balance}. God bless you.',
      due:     'Dear {member_name}, your next instalment toward {project_name} falls due on {next_due_date}. Balance outstanding: {pledge_balance}.',
      overdue: 'Dear {member_name}, we have not yet received your instalment toward {project_name}, which was due {next_due_date}. Outstanding: {pledge_balance}. Please contact the church office if you need to revise your plan.',
      thanks:  'Dear {member_name}, thank you for your faithfulness toward {project_name}. Your balance is now {pledge_balance}.'
    };
    function fillTemplate() { $('#rmBody').value = TEMPLATES[$('#rmTemplate').value]; }
    $('#rmTemplate').addEventListener('change', fillTemplate);
    fillTemplate();

    $$('[data-tag]').forEach(function (b) {
      b.addEventListener('click', function () {
        var ta = $('#rmBody'), tag = b.getAttribute('data-tag');
        var at = ta.selectionStart || ta.value.length;
        ta.value = ta.value.slice(0, at) + tag + ta.value.slice(ta.selectionEnd || at);
        ta.focus();
        ta.selectionStart = ta.selectionEnd = at + tag.length;
      });
    });

    function openRemind(n, who) {
      $('[data-rm-count]').textContent = n;
      $('[data-rm-who]').textContent = n === 1 ? (who || 'member') : 'members';
      openModal(rmModal);
    }
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-remind]');
      if (b) {
        closeOwnMenu(b);
        var p = plgById[b.getAttribute('data-remind')];
        openRemind(1, p ? p.member : 'member');
      }
    }, true);
    var br = $('#bulkRemind');
    if (br) { br.addEventListener('click', function () { openRemind(selected().length); }); }
    var lr = $('#ldRemind');
    if (lr) { lr.addEventListener('click', function () { openRemind(1, currentPledge ? currentPledge.member : 'member'); }); }

    $('#rmSend').addEventListener('click', function () {
      var chans = $$('[data-rm-chan]').filter(function (c) { return c.checked; });
      if (!chans.length) { toast('Choose at least one channel', 'error'); return; }
      closeModal(rmModal);
      toast('Reminder sent to ' + $('[data-rm-count]').textContent + ' member(s)', 'success');
    });
  }

  /* ═════════════════════════════ the charts ═════════════════════════════ */
  var PALETTE = ['#662F97', '#B48FDA', '#8F5CC2', '#D3BAEA', '#56287F'];
  var GRID    = '#ECE7F3';
  if (window.Chart) {
    Chart.defaults.font.family = "'Plus Jakarta Sans', system-ui, sans-serif";
    Chart.defaults.font.size = 11;
    Chart.defaults.color = '#6B6480';
    Chart.defaults.animation = reduced ? false : { duration: 700 };
  }

  var CHART_PROJECTS = <?= json_encode(array_map(static fn($p) => [
        'name'    => $p['name'],
        'target'  => (float) $p['target'],
        'raised'  => (float) $p['raised'],
        'pledged' => (float) $p['pledged'],
        'colour'  => $p['colour'],
        'trend'   => array_map('floatval', $project_trend_demo[$p['id']] ?? []),
      ], $chart_projects), JSON_UNESCAPED_UNICODE) ?>;
  var TREND_LABELS = <?= json_encode($trend_labels) ?>;
  var FULFIL = <?= json_encode([$fulfil['full'], $fulfil['part'], $fulfil['none']]) ?>;

  var drawn = {};

  function drawFulfilment() {
    if (drawn.fulfil || !window.Chart) { return; }
    var el = $('#fulfilChart');
    if (!el) { return; }
    drawn.fulfil = new Chart(el, {
      type: 'doughnut',
      data: {
        labels: ['Fully paid', 'Partially paid', 'Nothing paid'],
        datasets: [{ data: FULFIL, backgroundColor: ['#0F766E', '#B48FDA', '#D3BAEA'], borderWidth: 0 }]
      },
      options: {
        responsive: true, maintainAspectRatio: false, cutout: '62%',
        plugins: {
          legend: { display: true, position: 'bottom', labels: { boxWidth: 10, padding: 12, usePointStyle: true } },
          tooltip: { callbacks: { label: function (c) {
            var total = FULFIL.reduce(function (a, b) { return a + b; }, 0);
            var pct = total ? Math.round((c.parsed / total) * 100) : 0;
            return c.label + ': ' + c.parsed + ' (' + pct + '%)';
          } } }
        }
      }
    });
  }

  function drawAnalysis() {
    if (drawn.analysis || !window.Chart) { return; }
    drawn.analysis = true;
    var names = CHART_PROJECTS.map(function (p) { return p.name; });

    /* Funding progress — horizontal, because project names need the room. */
    new Chart($('#progressChart'), {
      type: 'bar',
      data: {
        labels: names,
        datasets: [
          { label: 'Raised', data: CHART_PROJECTS.map(function (p) { return p.raised; }), backgroundColor: '#662F97', borderRadius: 4 },
          { label: 'Target', data: CHART_PROJECTS.map(function (p) { return p.target; }), backgroundColor: '#D3BAEA', borderRadius: 4 }
        ]
      },
      options: {
        indexAxis: 'y', responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { display: true, position: 'bottom', labels: { boxWidth: 10, padding: 12, usePointStyle: true } },
          tooltip: { callbacks: { label: function (c) { return c.dataset.label + ': $' + c.parsed.x.toLocaleString(); } } }
        },
        scales: {
          x: { grid: { color: GRID }, ticks: { callback: function (v) { return '$' + (v / 1000) + 'k'; } } },
          y: { grid: { display: false } }
        }
      }
    });

    /* Pledge vs actual — the gap between the two bars is what is still owed. */
    new Chart($('#pvaChart'), {
      type: 'bar',
      data: {
        labels: names,
        datasets: [
          { label: 'Pledged',  data: CHART_PROJECTS.map(function (p) { return p.pledged; }), backgroundColor: '#B48FDA', borderRadius: 4 },
          { label: 'Received', data: CHART_PROJECTS.map(function (p) { return p.raised;  }), backgroundColor: '#662F97', borderRadius: 4 }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { display: true, position: 'bottom', labels: { boxWidth: 10, padding: 12, usePointStyle: true } },
          tooltip: { callbacks: { label: function (c) { return c.dataset.label + ': $' + c.parsed.y.toLocaleString(); } } }
        },
        scales: {
          x: { grid: { display: false }, ticks: { maxRotation: 40, minRotation: 0 } },
          y: { grid: { color: GRID }, ticks: { callback: function (v) { return '$' + (v / 1000) + 'k'; } } }
        }
      }
    });

    /* Contributions over time — one line per project. */
    new Chart($('#overtimeChart'), {
      type: 'line',
      data: {
        labels: TREND_LABELS,
        datasets: CHART_PROJECTS.map(function (p, i) {
          return {
            label: p.name, data: p.trend,
            borderColor: PALETTE[i % PALETTE.length], backgroundColor: 'transparent',
            borderWidth: 2, tension: .35, pointRadius: 0, pointHoverRadius: 4
          };
        })
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: true, position: 'bottom', labels: { boxWidth: 10, padding: 12, usePointStyle: true } },
          tooltip: { callbacks: { label: function (c) { return c.dataset.label + ': $' + c.parsed.y.toLocaleString(); } } }
        },
        scales: {
          x: { grid: { display: false } },
          y: { grid: { color: GRID }, beginAtZero: true, ticks: { callback: function (v) { return '$' + (v / 1000) + 'k'; } } }
        }
      }
    });
  }

  /* ────────────────────────── escape key ────────────────────────── */
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') { return; }
    var open = $$('.modal-scrim').filter(function (m) { return !m.hidden; });
    if (open.length) { open.forEach(function (m) { m.hidden = true; }); }
    else if (!prjD.hidden || !plgD.hidden) { closeDrawers(); }
    if (prjD.hidden && plgD.hidden && $$('.modal-scrim').every(function (m) { return m.hidden; })) {
      document.body.style.overflow = '';
    }
  });

  apply();
  paintBulk();
})();
</script>
<?php endif; ?>
<?php require __DIR__ . '/../components/footer.php'; ?>

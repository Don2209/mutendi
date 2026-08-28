<?php
/**
 * Mutendi CMS — Budgets.
 *
 * A plan for a period, and what actually happened against it. The plan on its
 * own is not interesting; the variance is. So every table, bar and chart here
 * puts budgeted and actual side by side, and judges the gap against how much
 * of the period has already gone.
 *
 * Four tabs:
 *   Overview            the shape of the period at a glance
 *   Income Budget       what was expected to come in, against what did
 *   Expenditure Budget  the same going out, plus what is committed but unpaid
 *   Variance Analysis   where the plan and reality parted company
 *
 * Actuals are read from the two ledgers this page sits on top of —
 * finance/contributions.php and finance/expenses.php — so the figures agree
 * across all three pages.
 *
 * UI only. Nothing is written anywhere.
 */

require __DIR__ . '/../includes/config.php';

/* ══════════════ DEMO ONLY — REMOVE BEFORE PRODUCTION ══════════════ */
$demo_role       = isset($_GET['role'], $demo_roles[$_GET['role']]) ? $_GET['role'] : 'treasurer';
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

    function mu_date(string $iso, string $fmt = 'd M Y'): string { return date($fmt, strtotime($iso)); }
}

$has_module = mu_mod('finance');
$can_view   = mu_can('finance.view');
$can_manage = mu_can('budgets.manage');

/* ─────────────────────────── BRANCH AWARENESS ───────────────────────────
   Which books these are. Entirely inert for a single church: is_multi_branch()
   is false, so no chip, column or selector is rendered.
   ──────────────────────────────────────────────────────────────────────── */
require_once __DIR__ . '/../components/branch-switcher.php';

$branch_aware   = is_multi_branch();
$viewing_all    = !$branch_aware || $current_branch === 'all' || $current_branch === null;
$show_branch    = $branch_aware && $viewing_all;
$branch_options = $branch_aware ? get_visible_branches() : [];

/* ══════════════════════ PLAN AGAINST REALITY ══════════════════════
   Everything below is worked out. A budget line stores what was planned; what
   actually happened is read from the ledgers and compared against how much of
   the period has already gone, because £5,000 spent is only alarming once you
   know whether it is January or December.
   ─────────────────────────────────────────────────────────────────── */

$cur_by_code = array_column($currencies, null, 'code');
$cat_by_key  = array_column($expense_categories, null, 'key');
$type_by_key = array_column($contribution_types, null, 'key');

function bud_usd(float $amount, string $code): float
{
    global $cur_by_code;
    return $amount * ($cur_by_code[$code]['exchange_rate_to_usd'] ?? 1.0);
}

/** Which budget is in view. `?budget=<id>` switches the whole page. */
$period_by_id = array_column($budget_periods, null, 'id');
$period_id    = isset($_GET['budget'], $period_by_id[(int) $_GET['budget']]) ? (int) $_GET['budget'] : $budget_periods[0]['id'];
$P            = $period_by_id[$period_id];

$STATUS = [
    'draft'  => ['label' => 'Draft',  'icon' => 'fa-pen-ruler'],
    'active' => ['label' => 'Active', 'icon' => 'fa-circle-play'],
    'closed' => ['label' => 'Closed', 'icon' => 'fa-lock'],
];

/* How far through the period we are. Everything that says "ahead" or "behind"
   is measured against this, not against the calendar year. */
$today_ts   = strtotime(date('Y-m-d'));
$start_ts   = strtotime($P['start']);
$end_ts     = strtotime($P['end']);
$span_days  = max(1, (int) round(($end_ts - $start_ts) / 86400));
$gone_days  = max(0, min($span_days, (int) round(($today_ts - $start_ts) / 86400)));
$days_left  = max(0, (int) round(($end_ts - $today_ts) / 86400));
$elapsed    = ($gone_days / $span_days) * 100;

/* ── what the ledgers say, for the period that reads from them ── */
$ledger_income = [];   /* contribution type => USD received */
$ledger_paid   = [];   /* expense category  => USD paid */
$ledger_commit = [];   /* expense category  => USD approved but not yet paid */

if ($P['derive'] && $has_module && $can_view) {
    foreach ($contributions_demo as $c) {
        $ledger_income[$c['type']] = ($ledger_income[$c['type']] ?? 0) + bud_usd((float) $c['amount'], $c['currency']);
    }
    foreach ($expenses_demo as $e) {
        $u = bud_usd((float) $e['amount'], $e['currency']);
        if ($e['status'] === 'paid')     { $ledger_paid[$e['category']]   = ($ledger_paid[$e['category']]   ?? 0) + $u; }
        if ($e['status'] === 'approved') { $ledger_commit[$e['category']] = ($ledger_commit[$e['category']] ?? 0) + $u; }
    }
}

/**
 * Where a line stands: how it is doing against the share of its budget the
 * calendar says it should have used by now.
 * `$favour_high` is true for income, where beating the line is good news.
 */
function bud_flag(float $util, float $elapsed, bool $favour_high): array
{
    $gap = $util - $elapsed;
    if     ($gap >  20) { $word = 'Significantly Over'; }
    elseif ($gap >   5) { $word = 'Over'; }
    elseif ($gap >  -5) { $word = 'On Track'; }
    elseif ($gap > -20) { $word = 'Under'; }
    else                { $word = 'Significantly Under'; }

    /* Over on income is good; over on expenditure is not. */
    if (abs($gap) <= 5)      { $tone = 'ok'; }
    elseif ($gap > 0)        { $tone = $favour_high ? 'good' : ($gap > 20 ? 'bad' : 'warn'); }
    else                     { $tone = $favour_high ? ($gap < -20 ? 'bad' : 'warn') : 'good'; }

    return ['word' => $word, 'tone' => $tone, 'gap' => $gap];
}

/**
 * The colour a variance figure takes. Deliberately not the sign: against a
 * full-period budget every income line is negative until the period ends, and
 * a wall of red in August tells the reader nothing. The tone follows the pace
 * flag instead, so it says whether the line is where it should be by now.
 */
function bud_var_class(array $flag): string
{
    return ['good' => 'is-good', 'bad' => 'is-bad', 'warn' => 'is-warn', 'ok' => 'is-neutral'][$flag['tone']];
}

/** The colour a utilisation bar takes: green under 80, amber to 100, red over. */
function bud_band(float $util): string
{
    if ($util > 100) { return 'over'; }
    if ($util >= 80) { return 'tight'; }
    return 'good';
}

/* ── the income side ── */
$income = [];
foreach ($P['income'] as $l) {
    $budget = (float) $l['budget'];
    $actual = (float) $l['prior'] + ($ledger_income[$l['type']] ?? 0);
    $util   = $budget > 0 ? ($actual / $budget) * 100 : 0.0;
    $income[] = $l + [
        'actual'   => $actual,
        'variance' => $actual - $budget,
        'var_pct'  => $budget > 0 ? (($actual - $budget) / $budget) * 100 : 0.0,
        'util'     => $util,
        'band'     => bud_band($util),
        'flag'     => bud_flag($util, $elapsed, true),
        'colour'   => $type_by_key[$l['type']]['colour'] ?? '#662F97',
        'icon'     => $type_by_key[$l['type']]['icon']   ?? 'fa-hand-holding-dollar',
    ];
}

/* ── the expenditure side, which also carries what is committed ── */
$expense = [];
foreach ($P['expense'] as $l) {
    $cat       = $cat_by_key[$l['category']] ?? null;
    $budget    = (float) $l['budget'];
    $actual    = (float) $l['prior'] + ($ledger_paid[$l['category']] ?? 0);
    $committed = (float) ($ledger_commit[$l['category']] ?? 0);
    $util      = $budget > 0 ? ($actual / $budget) * 100 : 0.0;
    $expense[] = $l + [
        'name'      => $cat['name']   ?? $l['category'],
        'icon'      => $cat['icon']   ?? 'fa-ellipsis',
        'colour'    => $cat['colour'] ?? '#662F97',
        'actual'    => $actual,
        'committed' => $committed,
        /* What is genuinely left to spend, not what the paid column implies. */
        'remaining' => $budget - $actual - $committed,
        'variance'  => $actual - $budget,
        'var_pct'   => $budget > 0 ? (($actual - $budget) / $budget) * 100 : 0.0,
        'util'      => $util,
        'band'      => bud_band($util),
        'flag'      => bud_flag($util, $elapsed, false),
    ];
}

/* ── totals ── */
$inc_budget = array_sum(array_column($income, 'budget'));
$inc_actual = array_sum(array_column($income, 'actual'));
$exp_budget = array_sum(array_column($expense, 'budget'));
$exp_actual = array_sum(array_column($expense, 'actual'));
$exp_commit = array_sum(array_column($expense, 'committed'));
$exp_remain = array_sum(array_column($expense, 'remaining'));

$inc_util = $inc_budget > 0 ? ($inc_actual / $inc_budget) * 100 : 0.0;
$exp_util = $exp_budget > 0 ? ($exp_actual / $exp_budget) * 100 : 0.0;

$planned_surplus = $inc_budget - $exp_budget;
$actual_surplus  = $inc_actual - $exp_actual;

/* ── the two Overview cards ── */
$over_budget = array_values(array_filter($expense, static fn($l) => $l['util'] > 100));
usort($over_budget, static fn($a, $b) => $b['variance'] <=> $a['variance']);

/* Well below the pace the calendar sets, not merely below budget. */
$under_used = array_values(array_filter($expense, static fn($l) => $l['util'] < $elapsed - 20));
usort($under_used, static fn($a, $b) => $a['util'] <=> $b['util']);

/* ── variance table: every line, worst deviation from pace first ── */
$variance = [];
foreach ($income as $l) {
    $variance[] = ['kind' => 'Income', 'name' => $l['item'], 'icon' => $l['icon'], 'colour' => $l['colour'],
                   'budget' => $l['budget'], 'actual' => $l['actual'], 'variance' => $l['variance'],
                   'var_pct' => $l['var_pct'], 'flag' => $l['flag']];
}
foreach ($expense as $l) {
    $variance[] = ['kind' => 'Expenditure', 'name' => $l['name'], 'icon' => $l['icon'], 'colour' => $l['colour'],
                   'budget' => $l['budget'], 'actual' => $l['actual'], 'variance' => $l['variance'],
                   'var_pct' => $l['var_pct'], 'flag' => $l['flag']];
}
usort($variance, static fn($a, $b) => abs($b['flag']['gap']) <=> abs($a['flag']['gap']));

/* ── the waterfall: pro-rata to today, so the bars reconcile ──
   Comparing a full-year budget against eight months of actuals would say
   nothing. Each expectation below is the budget scaled to the period gone. */
$exp_inc_todate = $inc_budget * ($elapsed / 100);
$exp_exp_todate = $exp_budget * ($elapsed / 100);
$expected_surplus = $exp_inc_todate - $exp_exp_todate;
$inc_var_todate = $inc_actual - $exp_inc_todate;
$exp_var_todate = -($exp_actual - $exp_exp_todate);

/* ── burn rate and where the period is heading ── */
$burn_actual = [];
$burn_budget = [];
$run_a = 0.0; $run_b = 0.0;
foreach ($P['months'] as $i => $m) {
    $run_b += (float) $P['budget_expense'][$i];
    $burn_budget[] = round($run_b, 2);
    $a = $P['actual_expense'][$i];
    if ($a === null) { $burn_actual[] = null; continue; }
    $run_a += (float) $a;
    $burn_actual[] = round($run_a, 2);
}
$months_done = count(array_filter($P['actual_expense'], static fn($v) => $v !== null));
$monthly_avg = $months_done > 0 ? $run_a / $months_done : 0.0;

/* The projection is a straight line from the current run rate. It is an
   estimate, and the page says so wherever it appears. */
$projection = [];
foreach ($P['months'] as $i => $m) {
    if ($i < $months_done) { $projection[] = null; continue; }
    if ($i === $months_done && $months_done > 0) { $projection[] = $burn_actual[$months_done - 1]; continue; }
    $projection[] = round(($months_done > 0 ? $burn_actual[$months_done - 1] : 0) + $monthly_avg * ($i - $months_done + 1), 2);
}
$projected_end = $months_done > 0 ? $monthly_avg * count($P['months']) : 0.0;

$page_title = 'Budgets';
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
        <span aria-current="page">Budgets</span>
      </nav>
      <div class="title-row">
        <h1 class="page__title">Budgets</h1>
      </div>
      <p class="page__sub">Plan and monitor your church finances.</p>
    </div>

    <?php if ($has_module && $can_view): ?>
      <div class="page__actions">
        <!-- The period selector lives in the header as well as the banner, so
             it is reachable without scrolling back up. -->
        <label class="offscreen" for="periodTop">Budget period</label>
        <select class="select" id="periodTop" data-period>
          <?php foreach ($budget_periods as $b): ?>
            <option value="<?= (int) $b['id'] ?>" <?= $b['id'] === $period_id ? 'selected' : '' ?>>
              <?= htmlspecialchars($b['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <?php if ($can_manage): ?>
          <button class="btn" type="button" id="btnNewBudget">
            <i class="fa-solid fa-plus" aria-hidden="true"></i> New Budget
          </button>
        <?php endif; ?>

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
      <h3>The Finance module is switched off</h3>
      <p>Your church's plan does not include budgeting. A church administrator can request it from the platform team.</p>
      <a class="btn" href="<?= $base_url ?>index.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Dashboard</a>
    </div>
  </section>

<?php elseif (!$can_view): ?>

  <section class="panel">
    <div class="empty">
      <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-lock"></i></span>
      <h3>You do not have access to financial records</h3>
      <p>A budget is the church's financial plan. Ask an administrator for the finance viewing permission.</p>
      <a class="btn" href="<?= $base_url ?>index.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Dashboard</a>
    </div>
  </section>

<?php else: ?>

  <!-- ════════════════════════ THE PERIOD IN VIEW ════════════════════════ -->
  <section class="periodbar">
    <div class="periodbar__pick">
      <label for="periodMain">Budget period</label>
      <select class="select select--lg" id="periodMain" data-period>
        <?php foreach ($budget_periods as $b): ?>
          <option value="<?= (int) $b['id'] ?>" <?= $b['id'] === $period_id ? 'selected' : '' ?>>
            <?= htmlspecialchars($b['name']) ?> &mdash; <?= htmlspecialchars($b['type']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="periodbar__meta">
      <span class="periodbar__range">
        <i class="fa-regular fa-calendar" aria-hidden="true"></i>
        <?= mu_date($P['start']) ?> &ndash; <?= mu_date($P['end']) ?>
      </span>
      <span class="pill pill--bg-<?= htmlspecialchars($P['status']) ?>">
        <i class="fa-solid <?= htmlspecialchars($STATUS[$P['status']]['icon']) ?>" aria-hidden="true"></i>
        <?= htmlspecialchars($STATUS[$P['status']]['label']) ?>
      </span>
      <span class="periodbar__cur"><?= htmlspecialchars($P['currency']) ?></span>
    </div>

    <?php if ($can_manage && $P['status'] !== 'closed'): ?>
      <div class="periodbar__acts">
        <button class="btn btn--ghost btn--sm" type="button" id="btnEditLines">
          <i class="fa-solid fa-table-list" aria-hidden="true"></i> Edit Budget Lines
        </button>
        <button class="btn btn--ghost btn--sm" type="button" id="btnEditBudget">
          <i class="fa-solid fa-pen" aria-hidden="true"></i> Edit
        </button>
        <button class="btn btn--ghost btn--sm" type="button" id="btnCloseBudget">
          <i class="fa-solid fa-lock" aria-hidden="true"></i> Close Budget
        </button>
      </div>
    <?php elseif ($P['status'] === 'closed'): ?>
      <div class="periodbar__acts">
        <span class="lockednote"><i class="fa-solid fa-lock" aria-hidden="true"></i> This budget is closed and read-only.</span>
      </div>
    <?php endif; ?>
  </section>


  <!-- ═════════════════════════════ STAT STRIP ═════════════════════════════ -->
  <section class="stat-strip" aria-label="Budget at a glance">
    <?php
      $tiles = [
        ['Total Budgeted Income',  $inc_budget, null,        'tone-purple', 'fa-hand-holding-dollar', null],
        ['Actual Income',          $inc_actual, $inc_budget, 'tone-green',  'fa-sack-dollar',         true],
        ['Total Budgeted Expenditure', $exp_budget, null,    'tone-blue',   'fa-scale-balanced',      null],
        ['Actual Expenditure',     $exp_actual, $exp_budget, 'tone-amber',  'fa-arrow-trend-down',    false],
      ];
    ?>
    <?php foreach ($tiles as [$label, $value, $against, $tone, $icon, $favour_high]): ?>
      <?php
        $util = $against > 0 ? ($value / $against) * 100 : null;
        $band = $util === null ? null : bud_band($util);
      ?>
      <div class="stat-tile is-static stat-tile--bar">
        <span class="stat-tile__icon <?= $tone ?>" aria-hidden="true"><i class="fa-solid <?= $icon ?>"></i></span>
        <span class="stat-tile__body">
          <span class="stat-tile__value">$<span data-count="<?= (int) round($value) ?>">0</span></span>
          <span class="stat-tile__label"><?= $label ?></span>
          <?php if ($util === null): ?>
            <span class="collected">
              <span class="rbar rbar--sm"><span class="rbar__fill" style="width:100%;background:var(--brand-300, var(--brand-500))"></span></span>
              <b>The plan</b> for this period
            </span>
          <?php else: ?>
            <?php
              /* On income, beating the line is good news; on expenditure it is not. */
              $good = $favour_high ? $util >= $elapsed - 5 : $util <= $elapsed + 5;
              $vpct = (($value - $against) / $against) * 100;
            ?>
            <span class="delta <?= $good ? 'is-up' : 'is-down' ?>">
              <i class="fa-solid <?= $vpct >= 0 ? 'fa-caret-up' : 'fa-caret-down' ?>" aria-hidden="true"></i>
              <?= number_format(abs($vpct), 1) ?>% vs budget
            </span>
            <span class="collected">
              <span class="rbar rbar--sm"><span class="rbar__fill is-<?= $band ?>" style="width:<?= min(100, round($util, 1)) ?>%"></span></span>
              <b><?= number_format($util, 1) ?>%</b> of budget
            </span>
          <?php endif; ?>
        </span>
      </div>
    <?php endforeach; ?>
  </section>


  <!-- ═══════════════════════ HEADLINE — BUDGET HEALTH ═══════════════════════ -->
  <section class="panel health">
    <header class="health__head">
      <div>
        <h2 class="health__title">Budget Health</h2>
        <p class="health__sub">Plan against reality for <?= htmlspecialchars($P['name']) ?>.</p>
      </div>
      <div class="health__result <?= $actual_surplus >= 0 ? 'is-surplus' : 'is-deficit' ?>">
        <span class="health__resultlabel"><?= $actual_surplus >= 0 ? 'Surplus' : 'Deficit' ?> to date</span>
        <span class="health__resultvalue">
          <?= $actual_surplus < 0 ? '&minus;' : '' ?>$<?= number_format(abs($actual_surplus), 2) ?>
        </span>
        <span class="health__resultnote">
          Planned <?= $planned_surplus >= 0 ? 'surplus' : 'deficit' ?> for the full period:
          <?= $planned_surplus < 0 ? '&minus;' : '' ?>$<?= number_format(abs($planned_surplus), 2) ?>
        </span>
      </div>
    </header>

    <!-- Two pairs of bars. The pale bar is the plan; the solid one is reality. -->
    <div class="hbars">
      <?php
        $scale = max(1.0, (float) max($inc_budget, $exp_budget, $inc_actual, $exp_actual));
        $pairs = [
          ['Income', $inc_budget, $inc_actual, $inc_util, 'is-income'],
          ['Expenditure', $exp_budget, $exp_actual, $exp_util, 'is-expense'],
        ];
      ?>
      <?php foreach ($pairs as [$label, $b, $a, $u, $cls]): ?>
        <div class="hbar <?= $cls ?>">
          <span class="hbar__label"><?= $label ?></span>
          <span class="hbar__track" role="img"
                aria-label="<?= $label ?>: <?= number_format($u, 1) ?> percent of the budgeted $<?= number_format($b, 0) ?>">
            <span class="hbar__plan" style="width:<?= round(($b / $scale) * 100, 2) ?>%"></span>
            <span class="hbar__real" style="width:<?= round(($a / $scale) * 100, 2) ?>%"></span>
          </span>
          <span class="hbar__figs">
            <b>$<?= number_format($a, 0) ?></b>
            <span>of $<?= number_format($b, 0) ?> &middot; <?= number_format($u, 1) ?>%</span>
          </span>
        </div>
      <?php endforeach; ?>
      <p class="hbar__key">
        <span class="hbar__keyitem"><span class="hbar__swatch is-plan" aria-hidden="true"></span> Budgeted</span>
        <span class="hbar__keyitem"><span class="hbar__swatch is-real" aria-hidden="true"></span> Actual</span>
      </p>
    </div>

    <!-- How much of the period has gone. Over- and under-spend mean nothing
         without it. -->
    <div class="elapsed">
      <div class="elapsed__top">
        <span>
          <i class="fa-regular fa-clock" aria-hidden="true"></i>
          <?php if ($days_left > 0): ?>
            <b><?= number_format($days_left) ?></b> day<?= $days_left === 1 ? '' : 's' ?> remaining in this period
          <?php else: ?>
            <b>The period has ended</b>
          <?php endif; ?>
        </span>
        <b><?= number_format($elapsed, 1) ?>% elapsed</b>
      </div>
      <span class="rbar"><span class="rbar__fill" style="width:<?= round($elapsed, 1) ?>%;background:var(--brand-500)"></span></span>
      <p class="elapsed__note">
        <?= mu_date($P['start']) ?> &ndash; <?= mu_date($P['end']) ?>.
        Spending is <b><?= number_format($exp_util, 1) ?>%</b> of budget with <b><?= number_format($elapsed, 1) ?>%</b> of the period gone
        &mdash; <?= $exp_util > $elapsed + 5 ? 'running ahead of plan' : ($exp_util < $elapsed - 5 ? 'running behind plan' : 'broadly on plan') ?>.
      </p>
    </div>
  </section>


  <!-- ══════════════════════════════ THE TABS ══════════════════════════════ -->
  <div class="tabs" role="tablist" aria-label="Budget views">
    <button class="tab is-on" type="button" role="tab" id="tab-overview" aria-controls="panel-overview" aria-selected="true" data-tab="overview">
      <i class="fa-solid fa-chart-simple" aria-hidden="true"></i> Overview
    </button>
    <button class="tab" type="button" role="tab" id="tab-income" aria-controls="panel-income" aria-selected="false" tabindex="-1" data-tab="income">
      <i class="fa-solid fa-arrow-trend-up" aria-hidden="true"></i> Income Budget
      <span class="tab__n"><?= count($income) ?></span>
    </button>
    <button class="tab" type="button" role="tab" id="tab-expense" aria-controls="panel-expense" aria-selected="false" tabindex="-1" data-tab="expense">
      <i class="fa-solid fa-arrow-trend-down" aria-hidden="true"></i> Expenditure Budget
      <span class="tab__n"><?= count($expense) ?></span>
    </button>
    <button class="tab" type="button" role="tab" id="tab-variance" aria-controls="panel-variance" aria-selected="false" tabindex="-1" data-tab="variance">
      <i class="fa-solid fa-scale-unbalanced" aria-hidden="true"></i> Variance Analysis
    </button>
  </div>

  <!-- ═══════════════════════════ TAB 1 — OVERVIEW ═══════════════════════════ -->
  <section class="tabpanel" id="panel-overview" role="tabpanel" aria-labelledby="tab-overview">

    <div class="chartgrid chartgrid--2">
      <section class="chartcard">
        <header class="chartcard__head">
          <div>
            <h2>Income vs Expenditure</h2>
            <p>Budgeted against actual, month by month.</p>
          </div>
        </header>
        <div class="chartbox chartbox--tall"><canvas id="ieChart"></canvas></div>
      </section>

      <section class="chartcard">
        <header class="chartcard__head">
          <div>
            <h2>Budget Utilisation by Category</h2>
            <p>Green under 80%, amber to 100%, red beyond.</p>
          </div>
        </header>
        <div class="chartbox chartbox--xtall"><canvas id="utilChart"></canvas></div>
      </section>
    </div>

    <div class="chartgrid chartgrid--2" style="margin-top:16px">
      <section class="chartcard">
        <header class="chartcard__head">
          <div>
            <h2>Over Budget</h2>
            <p>Categories that have already passed their line.</p>
          </div>
        </header>
        <?php if (!$over_budget): ?>
          <div class="empty">
            <span class="empty__icon" aria-hidden="true"><i class="fa-regular fa-circle-check"></i></span>
            <h3>Nothing is over budget</h3>
            <p>Every expenditure line is still within what was planned for it.</p>
          </div>
        <?php else: ?>
          <ul class="linelist">
            <?php foreach ($over_budget as $l): ?>
              <li class="linelist__row is-over">
                <span class="catico" style="--c:<?= htmlspecialchars($l['colour']) ?>" aria-hidden="true">
                  <i class="fa-solid <?= htmlspecialchars($l['icon']) ?>"></i>
                </span>
                <span class="linelist__text">
                  <b><?= htmlspecialchars($l['name']) ?></b>
                  <span>$<?= number_format($l['actual'], 0) ?> spent of $<?= number_format($l['budget'], 0) ?></span>
                </span>
                <span class="linelist__figs">
                  <b>+$<?= number_format($l['variance'], 0) ?></b>
                  <span><?= number_format($l['util'], 0) ?>% used</span>
                </span>
                <button class="iconbtn" type="button" data-line="expense:<?= htmlspecialchars($l['category']) ?>"
                        aria-label="Open <?= htmlspecialchars($l['name']) ?>">
                  <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                </button>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>

      <section class="chartcard">
        <header class="chartcard__head">
          <div>
            <h2>Under Utilised</h2>
            <p>More than 20 points behind the pace the calendar sets.</p>
          </div>
        </header>
        <?php if (!$under_used): ?>
          <div class="empty">
            <span class="empty__icon" aria-hidden="true"><i class="fa-regular fa-circle-check"></i></span>
            <h3>Every line is keeping pace</h3>
            <p>Nothing is lagging far enough behind to be worth reallocating.</p>
          </div>
        <?php else: ?>
          <ul class="linelist">
            <?php foreach ($under_used as $l): ?>
              <li class="linelist__row is-under">
                <span class="catico" style="--c:<?= htmlspecialchars($l['colour']) ?>" aria-hidden="true">
                  <i class="fa-solid <?= htmlspecialchars($l['icon']) ?>"></i>
                </span>
                <span class="linelist__text">
                  <b><?= htmlspecialchars($l['name']) ?></b>
                  <span>$<?= number_format($l['actual'], 0) ?> spent of $<?= number_format($l['budget'], 0) ?></span>
                </span>
                <span class="linelist__figs">
                  <b><?= number_format($l['util'], 0) ?>%</b>
                  <span>vs <?= number_format($elapsed, 0) ?>% elapsed</span>
                </span>
                <button class="iconbtn" type="button" data-line="expense:<?= htmlspecialchars($l['category']) ?>"
                        aria-label="Open <?= htmlspecialchars($l['name']) ?>">
                  <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                </button>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>
    </div>
  </section>

  <!-- ═════════════════════════ TAB 2 — INCOME BUDGET ═════════════════════════ -->
  <section class="tabpanel" id="panel-income" role="tabpanel" aria-labelledby="tab-income" hidden>
    <div class="at-notice" role="note">
      <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
      <div class="at-notice__body">
        <strong>Variance is measured against the whole period</strong>
        <span>With <?= number_format($elapsed, 1) ?>% of the period gone, most lines will still be short of their full-year figure. The colour tells you whether a line is keeping pace, not whether the number is negative.</span>
      </div>
    </div>

    <section class="panel">
      <div class="dt-wrap">
        <table class="dt" id="incTable">
          <thead>
            <tr>
              <th style="width:40px">#</th>
              <th>Line Item</th>
              <th>Category</th>
              <th style="text-align:right">Budgeted</th>
              <th style="text-align:right">Actual Received</th>
              <th style="text-align:right">Variance</th>
              <th style="min-width:150px">Utilisation</th>
              <th>Notes</th>
              <th class="col-actions" style="text-align:right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($income as $i => $l): ?>
              <tr>
                <td class="num"><?= $i + 1 ?></td>
                <td>
                  <span class="minirow minirow--tight">
                    <span class="catico" style="--c:<?= htmlspecialchars($l['colour']) ?>" aria-hidden="true">
                      <i class="fa-solid <?= htmlspecialchars($l['icon']) ?>"></i>
                    </span>
                    <span class="minirow__text"><b><?= htmlspecialchars($l['item']) ?></b></span>
                  </span>
                </td>
                <td><span class="excat" style="--c:<?= htmlspecialchars($l['colour']) ?>"><?= htmlspecialchars($l['group']) ?></span></td>
                <td class="num">$<?= number_format($l['budget'], 2) ?></td>
                <td class="num"><b>$<?= number_format($l['actual'], 2) ?></b></td>
                <!-- The figure is against the whole period; the colour is
                     against the pace, which is what actually matters today. -->
                <td class="num">
                  <span class="var <?= bud_var_class($l['flag']) ?>" title="<?= htmlspecialchars($l['flag']['word']) ?> for this point in the period">
                    <b><?= $l['variance'] >= 0 ? '+' : '&minus;' ?>$<?= number_format(abs($l['variance']), 2) ?></b>
                    <span><?= $l['var_pct'] >= 0 ? '+' : '&minus;' ?><?= number_format(abs($l['var_pct']), 1) ?>%</span>
                  </span>
                </td>
                <td>
                  <span class="cellbar cellbar--<?= $l['band'] === 'over' ? 'good' : ($l['band'] === 'tight' ? 'good' : 'warn') ?>">
                    <span class="cellbar__track"><span class="cellbar__fill" style="width:<?= min(100, round($l['util'], 1)) ?>%"></span></span>
                    <b><?= number_format($l['util'], 0) ?>%</b>
                  </span>
                </td>
                <td class="notecell"><?= $l['notes'] ? htmlspecialchars($l['notes']) : '<span class="muted">&mdash;</span>' ?></td>
                <td class="col-actions">
                  <div class="rowacts">
                    <?php if ($can_manage && $P['status'] !== 'closed'): ?>
                      <button class="iconbtn" type="button" data-editline="income:<?= htmlspecialchars($l['type']) ?>" aria-label="Edit <?= htmlspecialchars($l['item']) ?>">
                        <i class="fa-solid fa-pen" aria-hidden="true"></i>
                      </button>
                    <?php endif; ?>
                    <button class="iconbtn" type="button" data-line="income:<?= htmlspecialchars($l['type']) ?>" aria-label="Transactions for <?= htmlspecialchars($l['item']) ?>">
                      <i class="fa-solid fa-list-ul" aria-hidden="true"></i>
                    </button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr class="dt__total">
              <td colspan="3">Totals</td>
              <td class="num">$<?= number_format($inc_budget, 2) ?></td>
              <td class="num"><b>$<?= number_format($inc_actual, 2) ?></b></td>
              <td class="num">
                <span class="var <?= bud_var_class(bud_flag($inc_util, $elapsed, true)) ?>">
                  <b><?= $inc_actual - $inc_budget >= 0 ? '+' : '&minus;' ?>$<?= number_format(abs($inc_actual - $inc_budget), 2) ?></b>
                </span>
              </td>
              <td><b><?= number_format($inc_util, 1) ?>%</b> of budget</td>
              <td colspan="2"></td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div class="dt-cards">
        <?php foreach ($income as $l): ?>
          <article class="pcard pcard--flat">
            <header class="pcard__head">
              <span class="catico" style="--c:<?= htmlspecialchars($l['colour']) ?>" aria-hidden="true">
                <i class="fa-solid <?= htmlspecialchars($l['icon']) ?>"></i>
              </span>
              <span class="pcard__text">
                <span class="pcard__name"><?= htmlspecialchars($l['item']) ?></span>
                <span class="pcard__meta"><?= htmlspecialchars($l['group']) ?></span>
              </span>
              <span class="var <?= bud_var_class($l['flag']) ?>">
                <b><?= $l['variance'] >= 0 ? '+' : '&minus;' ?>$<?= number_format(abs($l['variance']), 0) ?></b>
              </span>
            </header>
            <span class="cellbar cellbar--good" style="margin-bottom:10px">
              <span class="cellbar__track"><span class="cellbar__fill" style="width:<?= min(100, round($l['util'], 1)) ?>%"></span></span>
              <b><?= number_format($l['util'], 0) ?>%</b>
            </span>
            <dl class="pcard__dl">
              <div><dt>Budgeted</dt><dd>$<?= number_format($l['budget'], 2) ?></dd></div>
              <div><dt>Actual received</dt><dd>$<?= number_format($l['actual'], 2) ?></dd></div>
              <div><dt>Variance</dt><dd><?= $l['var_pct'] >= 0 ? '+' : '&minus;' ?><?= number_format(abs($l['var_pct']), 1) ?>%</dd></div>
            </dl>
            <div class="pcard__acts">
              <button class="btn btn--ghost btn--sm" type="button" data-line="income:<?= htmlspecialchars($l['type']) ?>">
                <i class="fa-solid fa-list-ul" aria-hidden="true"></i> View Transactions
              </button>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  </section>


  <!-- ══════════════════════ TAB 3 — EXPENDITURE BUDGET ══════════════════════ -->
  <section class="tabpanel" id="panel-expense" role="tabpanel" aria-labelledby="tab-expense" hidden>
    <div class="at-notice" role="note">
      <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
      <div class="at-notice__body">
        <strong>Committed means approved but not yet paid</strong>
        <span>The remaining balance subtracts it, because that money is already spoken for even though it has not left the account. As on the income tab, variance is against the whole period and the colour reflects pace.</span>
      </div>
    </div>

    <section class="panel">
      <div class="dt-wrap">
        <table class="dt" id="expTable">
          <thead>
            <tr>
              <th style="width:40px">#</th>
              <th>Line Item</th>
              <th style="text-align:right">Budgeted</th>
              <th style="text-align:right">Committed</th>
              <th style="text-align:right">Actual Paid</th>
              <th style="text-align:right">Remaining</th>
              <th style="text-align:right">Variance</th>
              <th style="min-width:150px">Utilisation</th>
              <th>Notes</th>
              <th class="col-actions" style="text-align:right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($expense as $i => $l): ?>
              <tr>
                <td class="num"><?= $i + 1 ?></td>
                <td>
                  <span class="minirow minirow--tight">
                    <span class="catico" style="--c:<?= htmlspecialchars($l['colour']) ?>" aria-hidden="true">
                      <i class="fa-solid <?= htmlspecialchars($l['icon']) ?>"></i>
                    </span>
                    <span class="minirow__text"><b><?= htmlspecialchars($l['name']) ?></b></span>
                  </span>
                </td>
                <td class="num">$<?= number_format($l['budget'], 2) ?></td>
                <td class="num"><?= $l['committed'] > 0 ? '$' . number_format($l['committed'], 2) : '<span class="muted">&mdash;</span>' ?></td>
                <td class="num"><b>$<?= number_format($l['actual'], 2) ?></b></td>
                <td class="num">
                  <span class="<?= $l['remaining'] < 0 ? 'var is-bad' : '' ?>">
                    <b><?= $l['remaining'] < 0 ? '&minus;' : '' ?>$<?= number_format(abs($l['remaining']), 2) ?></b>
                  </span>
                </td>
                <!-- Same rule as the income table: the figure is against the
                     whole period, the colour against the pace. -->
                <td class="num">
                  <span class="var <?= bud_var_class($l['flag']) ?>" title="<?= htmlspecialchars($l['flag']['word']) ?> for this point in the period">
                    <b><?= $l['variance'] >= 0 ? '+' : '&minus;' ?>$<?= number_format(abs($l['variance']), 2) ?></b>
                    <span><?= $l['var_pct'] >= 0 ? '+' : '&minus;' ?><?= number_format(abs($l['var_pct']), 1) ?>%</span>
                  </span>
                </td>
                <td>
                  <span class="cellbar cellbar--<?= $l['band'] === 'over' ? 'risk' : ($l['band'] === 'tight' ? 'warn' : 'good') ?>">
                    <span class="cellbar__track"><span class="cellbar__fill" style="width:<?= min(100, round($l['util'], 1)) ?>%"></span></span>
                    <b><?= number_format($l['util'], 0) ?>%</b>
                  </span>
                </td>
                <td class="notecell"><?= $l['notes'] ? htmlspecialchars($l['notes']) : '<span class="muted">&mdash;</span>' ?></td>
                <td class="col-actions">
                  <div class="rowacts">
                    <?php if ($can_manage && $P['status'] !== 'closed'): ?>
                      <button class="iconbtn" type="button" data-editline="expense:<?= htmlspecialchars($l['category']) ?>" aria-label="Edit <?= htmlspecialchars($l['name']) ?>">
                        <i class="fa-solid fa-pen" aria-hidden="true"></i>
                      </button>
                    <?php endif; ?>
                    <button class="iconbtn" type="button" data-line="expense:<?= htmlspecialchars($l['category']) ?>" aria-label="Transactions for <?= htmlspecialchars($l['name']) ?>">
                      <i class="fa-solid fa-list-ul" aria-hidden="true"></i>
                    </button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr class="dt__total">
              <td colspan="2">Totals</td>
              <td class="num">$<?= number_format($exp_budget, 2) ?></td>
              <td class="num">$<?= number_format($exp_commit, 2) ?></td>
              <td class="num"><b>$<?= number_format($exp_actual, 2) ?></b></td>
              <td class="num"><b>$<?= number_format($exp_remain, 2) ?></b></td>
              <td class="num">
                <span class="var <?= bud_var_class(bud_flag($exp_util, $elapsed, false)) ?>">
                  <b><?= $exp_actual - $exp_budget >= 0 ? '+' : '&minus;' ?>$<?= number_format(abs($exp_actual - $exp_budget), 2) ?></b>
                </span>
              </td>
              <td><b><?= number_format($exp_util, 1) ?>%</b> of budget</td>
              <td colspan="2"></td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div class="dt-cards">
        <?php foreach ($expense as $l): ?>
          <article class="pcard pcard--flat">
            <header class="pcard__head">
              <span class="catico" style="--c:<?= htmlspecialchars($l['colour']) ?>" aria-hidden="true">
                <i class="fa-solid <?= htmlspecialchars($l['icon']) ?>"></i>
              </span>
              <span class="pcard__text">
                <span class="pcard__name"><?= htmlspecialchars($l['name']) ?></span>
                <span class="pcard__meta">$<?= number_format($l['actual'], 0) ?> of $<?= number_format($l['budget'], 0) ?></span>
              </span>
              <span class="var <?= bud_var_class($l['flag']) ?>">
                <b><?= $l['variance'] >= 0 ? '+' : '&minus;' ?>$<?= number_format(abs($l['variance']), 0) ?></b>
              </span>
            </header>
            <span class="cellbar cellbar--<?= $l['band'] === 'over' ? 'risk' : ($l['band'] === 'tight' ? 'warn' : 'good') ?>" style="margin-bottom:10px">
              <span class="cellbar__track"><span class="cellbar__fill" style="width:<?= min(100, round($l['util'], 1)) ?>%"></span></span>
              <b><?= number_format($l['util'], 0) ?>%</b>
            </span>
            <dl class="pcard__dl">
              <div><dt>Budgeted</dt><dd>$<?= number_format($l['budget'], 2) ?></dd></div>
              <div><dt>Committed</dt><dd>$<?= number_format($l['committed'], 2) ?></dd></div>
              <div><dt>Actual paid</dt><dd>$<?= number_format($l['actual'], 2) ?></dd></div>
              <div><dt>Remaining</dt><dd>$<?= number_format($l['remaining'], 2) ?></dd></div>
            </dl>
            <div class="pcard__acts">
              <button class="btn btn--ghost btn--sm" type="button" data-line="expense:<?= htmlspecialchars($l['category']) ?>">
                <i class="fa-solid fa-list-ul" aria-hidden="true"></i> View Transactions
              </button>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  </section>

  <!-- ═══════════════════════ TAB 4 — VARIANCE ANALYSIS ═══════════════════════ -->
  <section class="tabpanel" id="panel-variance" role="tabpanel" aria-labelledby="tab-variance" hidden>

    <section class="chartcard">
      <header class="chartcard__head">
        <div>
          <h2>From Plan to Position</h2>
          <p>Everything below is pro-rata to today &mdash; the budget scaled to the <?= number_format($elapsed, 1) ?>% of the period already gone, so the bars compare like with like.</p>
        </div>
      </header>
      <div class="chartbox chartbox--tall"><canvas id="waterfallChart"></canvas></div>
      <ul class="wfkey">
        <li><span>Expected surplus to date</span><b>$<?= number_format($expected_surplus, 2) ?></b></li>
        <li class="<?= $inc_var_todate >= 0 ? 'is-good' : 'is-bad' ?>">
          <span>Income variance</span>
          <b><?= $inc_var_todate >= 0 ? '+' : '&minus;' ?>$<?= number_format(abs($inc_var_todate), 2) ?></b>
        </li>
        <li class="<?= $exp_var_todate >= 0 ? 'is-good' : 'is-bad' ?>">
          <span>Expenditure variance</span>
          <b><?= $exp_var_todate >= 0 ? '+' : '&minus;' ?>$<?= number_format(abs($exp_var_todate), 2) ?></b>
        </li>
        <li class="is-total">
          <span>Actual position</span>
          <b><?= $actual_surplus < 0 ? '&minus;' : '' ?>$<?= number_format(abs($actual_surplus), 2) ?></b>
        </li>
      </ul>
    </section>

    <section class="panel" style="margin-top:16px">
      <header class="chartcard__head">
        <div>
          <h2>Variance by Line</h2>
          <p>Ranked by how far each line has drifted from the pace the calendar sets.</p>
        </div>
      </header>
      <div class="dt-wrap">
        <table class="dt" id="varTable">
          <thead>
            <tr>
              <th style="width:40px">#</th>
              <th>Line</th>
              <th>Type</th>
              <th style="text-align:right">Budgeted</th>
              <th style="text-align:right">Actual</th>
              <th style="text-align:right">Variance</th>
              <th style="text-align:right">%</th>
              <th>Assessment</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($variance as $i => $v): ?>
              <tr>
                <td class="num"><?= $i + 1 ?></td>
                <td>
                  <span class="minirow minirow--tight">
                    <span class="catico" style="--c:<?= htmlspecialchars($v['colour']) ?>" aria-hidden="true">
                      <i class="fa-solid <?= htmlspecialchars($v['icon']) ?>"></i>
                    </span>
                    <span class="minirow__text"><b><?= htmlspecialchars($v['name']) ?></b></span>
                  </span>
                </td>
                <td><span class="kindchip kindchip--<?= strtolower($v['kind']) ?>"><?= $v['kind'] ?></span></td>
                <td class="num">$<?= number_format($v['budget'], 2) ?></td>
                <td class="num"><b>$<?= number_format($v['actual'], 2) ?></b></td>
                <td class="num">
                  <b><?= $v['variance'] >= 0 ? '+' : '&minus;' ?>$<?= number_format(abs($v['variance']), 2) ?></b>
                </td>
                <td class="num"><?= $v['var_pct'] >= 0 ? '+' : '&minus;' ?><?= number_format(abs($v['var_pct']), 1) ?>%</td>
                <td>
                  <span class="pill pill--vf-<?= $v['flag']['tone'] ?>"><?= htmlspecialchars($v['flag']['word']) ?></span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="dt-cards">
        <?php foreach ($variance as $v): ?>
          <article class="pcard pcard--flat">
            <header class="pcard__head">
              <span class="catico" style="--c:<?= htmlspecialchars($v['colour']) ?>" aria-hidden="true">
                <i class="fa-solid <?= htmlspecialchars($v['icon']) ?>"></i>
              </span>
              <span class="pcard__text">
                <span class="pcard__name"><?= htmlspecialchars($v['name']) ?></span>
                <span class="pcard__meta"><?= $v['kind'] ?></span>
              </span>
              <span class="pill pill--vf-<?= $v['flag']['tone'] ?>"><?= htmlspecialchars($v['flag']['word']) ?></span>
            </header>
            <dl class="pcard__dl">
              <div><dt>Budgeted</dt><dd>$<?= number_format($v['budget'], 2) ?></dd></div>
              <div><dt>Actual</dt><dd>$<?= number_format($v['actual'], 2) ?></dd></div>
              <div><dt>Variance</dt><dd><?= $v['variance'] >= 0 ? '+' : '&minus;' ?>$<?= number_format(abs($v['variance']), 2) ?>
                (<?= $v['var_pct'] >= 0 ? '+' : '&minus;' ?><?= number_format(abs($v['var_pct']), 1) ?>%)</dd></div>
            </dl>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="chartcard" style="margin-top:16px">
      <header class="chartcard__head">
        <div>
          <h2>Monthly Burn Rate</h2>
          <p>Cumulative expenditure against the budget line, with a projection to the end of the period.</p>
        </div>
      </header>
      <div class="chartbox chartbox--tall"><canvas id="burnChart"></canvas></div>
      <div class="at-notice at-notice--warn" role="note" style="margin-top:12px">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        <div class="at-notice__body">
          <strong>The projection is an estimate</strong>
          <span>
            It simply carries the current average of <b>$<?= number_format($monthly_avg, 2) ?></b> a month forward to the end of the period,
            reaching <b>$<?= number_format($projected_end, 2) ?></b> against a budget of <b>$<?= number_format($exp_budget, 2) ?></b>
            &mdash; <?= $projected_end > $exp_budget
                  ? 'an overspend of <b>$' . number_format($projected_end - $exp_budget, 2) . '</b>'
                  : 'an underspend of <b>$' . number_format($exp_budget - $projected_end, 2) . '</b>' ?>.
            It assumes nothing changes, which nothing ever does.
          </span>
        </div>
      </div>
    </section>
  </section>

<?php endif; ?>
</div>

<?php if ($has_module && $can_view): ?>

<div class="drawer-scrim" data-drawer-scrim hidden></div>

<!-- ══════════════════════ LINE DETAIL DRAWER ══════════════════════ -->
<aside class="drawer" id="lineDrawer" role="dialog" aria-modal="true" aria-labelledby="lnTitle" hidden>
  <header class="drawer__head">
    <span class="catico catico--lg" data-ln-ico aria-hidden="true"><i class="fa-solid fa-ellipsis"></i></span>
    <div class="drawer__title">
      <h2 id="lnTitle">Budget line</h2>
      <p><span data-ln-kind>&mdash;</span></p>
    </div>
    <button class="iconbtn" type="button" data-drawer-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
  </header>

  <div class="drawer__body">
    <div class="figrow">
      <span><b data-ln-budget>$0</b>Budgeted</span>
      <span><b data-ln-actual>$0</b>Actual to date</span>
      <span><b data-ln-var>$0</b>Variance</span>
    </div>

    <span class="cellbar" data-ln-bar style="margin-bottom:16px">
      <span class="cellbar__track"><span class="cellbar__fill" style="width:0%"></span></span>
      <b data-ln-pct>0%</b>
    </span>

    <p class="minilist__head">Month by month</p>
    <div class="chartbox"><canvas id="lineChart"></canvas></div>

    <p class="minilist__head">Transactions</p>
    <div class="minilist" data-ln-txns></div>
  </div>

  <footer class="drawer__foot drawer__foot--wrap">
    <a class="btn btn--ghost" data-ln-link href="<?= $base_url ?>finance/contributions.php">
      <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> Open the ledger
    </a>
    <?php if ($can_manage && $P['status'] !== 'closed'): ?>
      <button class="btn btn--ghost" type="button" id="lnEdit"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit this line</button>
    <?php endif; ?>
  </footer>
</aside>


<?php if ($can_manage): ?>
<!-- ══════════════════════ NEW / EDIT BUDGET ══════════════════════ -->
<div class="modal-scrim" id="modalBudget" hidden>
  <div class="modal modal--wide" role="dialog" aria-modal="true" aria-labelledby="bgTitle">
    <header class="modal__head">
      <h2 id="bgTitle">New Budget</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>

    <div class="modal__body">
      <div class="form-grid">
        <div class="field field--wide">
          <label for="bgName">Budget name <span class="req">*</span></label>
          <input class="input" type="text" id="bgName" placeholder="2027 Annual Budget" autocomplete="off">
        </div>

        <div class="field field--wide">
          <label id="bgTypeLbl">Period type</label>
          <div class="radio-cards" role="radiogroup" aria-labelledby="bgTypeLbl">
            <?php foreach ([['Annual', 'fa-calendar-days'], ['Quarterly', 'fa-calendar-week'],
                            ['Monthly', 'fa-calendar-day'], ['Project', 'fa-diagram-project']] as $i => [$t, $ic]): ?>
              <label class="rcard">
                <input type="radio" name="bgType" value="<?= $t ?>" <?= $i === 0 ? 'checked' : '' ?>>
                <span class="rcard__box">
                  <i class="fa-solid <?= $ic ?>" aria-hidden="true"></i>
                  <span><strong><?= $t ?></strong></span>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="field">
          <label for="bgStart">Start date</label>
          <input class="input" type="date" id="bgStart" value="<?= date('Y-01-01') ?>">
        </div>

        <div class="field">
          <label for="bgEnd">End date</label>
          <input class="input" type="date" id="bgEnd" value="<?= date('Y-12-31') ?>">
        </div>

        <?php if ($show_branch): ?>
          <div class="field">
            <label for="bgBranch"><?= htmlspecialchars(t('branch_singular')) ?></label>
            <select class="select" id="bgBranch">
              <option value="">Whole <?= htmlspecialchars(t('org_singular')) ?></option>
              <?php foreach ($branch_options as $b): ?>
                <option value="<?= (int) $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <div class="field">
          <label for="bgCurrency">Base currency</label>
          <select class="select" id="bgCurrency">
            <?php foreach ($currencies as $c): ?>
              <option value="<?= htmlspecialchars($c['code']) ?>"><?= htmlspecialchars($c['code']) ?> &mdash; <?= htmlspecialchars($c['name'] ?? $c['code']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label for="bgStatus">Status</label>
          <select class="select" id="bgStatus">
            <option value="draft">Draft</option>
            <option value="active">Active</option>
            <option value="closed">Closed</option>
          </select>
        </div>

        <div class="field field--wide">
          <label class="switchrow" for="bgCopy">
            <span class="switch"><input type="checkbox" id="bgCopy"><span class="switch__track" aria-hidden="true"></span></span>
            <span class="switchrow__text">
              <b>Copy lines from a previous budget</b>
              <small>Brings across every line and its amount, ready to be adjusted.</small>
            </span>
          </label>
          <select class="select" id="bgCopyFrom" disabled aria-label="Which budget to copy from">
            <?php foreach ($budget_periods as $b): ?>
              <option value="<?= (int) $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <footer class="modal__foot">
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <button class="btn" type="button" id="bgSave"><i class="fa-solid fa-check" aria-hidden="true"></i> Save Budget</button>
    </footer>
  </div>
</div>


<!-- ══════════════════════ EDIT BUDGET LINES ══════════════════════ -->
<div class="modal-scrim" id="modalLines" hidden>
  <div class="modal modal--full" role="dialog" aria-modal="true" aria-labelledby="blTitle">
    <header class="modal__head">
      <h2 id="blTitle">Edit Budget Lines</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>

    <div class="modal__body">
      <p class="modal__hint">
        Amounts are for the whole period. Totals and the surplus below update as you type.
      </p>

      <section class="linesec">
        <header class="linesec__head">
          <h3><i class="fa-solid fa-arrow-trend-up" aria-hidden="true"></i> Income</h3>
          <div class="linesec__acts">
            <button class="btn btn--ghost btn--sm" type="button" data-distribute="income">
              <i class="fa-solid fa-arrows-left-right-to-line" aria-hidden="true"></i> Distribute evenly across months
            </button>
            <button class="btn btn--ghost btn--sm" type="button" data-addline="income">
              <i class="fa-solid fa-plus" aria-hidden="true"></i> Add line
            </button>
          </div>
        </header>
        <div class="lines" data-lines="income"></div>
        <p class="linesec__total">Income total <b data-total="income">$0.00</b></p>
      </section>

      <section class="linesec">
        <header class="linesec__head">
          <h3><i class="fa-solid fa-arrow-trend-down" aria-hidden="true"></i> Expenditure</h3>
          <div class="linesec__acts">
            <button class="btn btn--ghost btn--sm" type="button" data-distribute="expense">
              <i class="fa-solid fa-arrows-left-right-to-line" aria-hidden="true"></i> Distribute evenly across months
            </button>
            <button class="btn btn--ghost btn--sm" type="button" data-addline="expense">
              <i class="fa-solid fa-plus" aria-hidden="true"></i> Add line
            </button>
          </div>
        </header>
        <div class="lines" data-lines="expense"></div>
        <p class="linesec__total">Expenditure total <b data-total="expense">$0.00</b></p>
      </section>
    </div>

    <footer class="modal__foot modal__foot--split">
      <!-- The surplus indicator is the point of this screen, so it sits in the
           footer where it stays visible while the numbers are being typed. -->
      <div class="surplus" data-surplus>
        <span class="surplus__label">Planned surplus</span>
        <span class="surplus__value" data-surplus-value>$0.00</span>
      </div>
      <div class="modal__footgroup">
        <button class="btn btn--ghost" type="button" data-close>Cancel</button>
        <button class="btn" type="button" id="blSave"><i class="fa-solid fa-check" aria-hidden="true"></i> Save Lines</button>
      </div>
    </footer>
  </div>
</div>


<!-- ══════════════════════════ CLOSE BUDGET ══════════════════════════ -->
<div class="modal-scrim" id="modalClose" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="clTitle">
    <header class="modal__head">
      <h2 id="clTitle">Close <?= htmlspecialchars($P['name']) ?></h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>

    <div class="modal__body">
      <div class="at-notice at-notice--warn" role="note" style="margin-bottom:14px">
        <i class="fa-solid fa-lock" aria-hidden="true"></i>
        <div class="at-notice__body">
          <strong>A closed budget becomes read-only</strong>
          <span>Its lines and amounts can no longer be edited, and it stops accepting new actuals. Reopening it needs an administrator.</span>
        </div>
      </div>

      <p class="minilist__head">Final performance</p>
      <dl class="deflist">
        <div><dt>Budgeted income</dt><dd>$<?= number_format($inc_budget, 2) ?></dd></div>
        <div><dt>Actual income</dt><dd>$<?= number_format($inc_actual, 2) ?> &middot; <?= number_format($inc_util, 1) ?>%</dd></div>
        <div><dt>Budgeted expenditure</dt><dd>$<?= number_format($exp_budget, 2) ?></dd></div>
        <div><dt>Actual expenditure</dt><dd>$<?= number_format($exp_actual, 2) ?> &middot; <?= number_format($exp_util, 1) ?>%</dd></div>
        <div><dt>Planned surplus</dt><dd><?= $planned_surplus < 0 ? '&minus;' : '' ?>$<?= number_format(abs($planned_surplus), 2) ?></dd></div>
        <div><dt>Final position</dt><dd><b class="<?= $actual_surplus >= 0 ? 'var is-good' : 'var is-bad' ?>"><?= $actual_surplus < 0 ? '&minus;' : '' ?>$<?= number_format(abs($actual_surplus), 2) ?></b></dd></div>
      </dl>
    </div>

    <footer class="modal__foot">
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <button class="btn btn--danger" type="button" id="clGo"><i class="fa-solid fa-lock" aria-hidden="true"></i> Close Budget</button>
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
      <li><a class="demo__role<?= $key === $demo_role ? ' is-on' : '' ?>" href="?budget=<?= $period_id ?>&amp;role=<?= urlencode($key) ?>"
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
/* ── what the drawer needs: each line's monthly shape and the transactions
      behind it. Months before the ledger window carry `prior` spread evenly,
      because a lump-sum opening figure has no month-by-month detail; the
      drawer's caption says so rather than pretending otherwise. ── */
$n_months  = count($P['months']);
$start_ym  = ((int) date('Y', $start_ts)) * 12 + ((int) date('n', $start_ts)) - 1;
$month_now = ((int) date('Y')) * 12 + ((int) date('n')) - 1;
$months_elapsed = max(1, min($n_months, $month_now - $start_ym + 1));

/** Which slot of the period a date falls in, or null if it falls outside. */
$slot = static function (string $iso) use ($start_ym, $n_months): ?int {
    $ym = ((int) date('Y', strtotime($iso))) * 12 + ((int) date('n', strtotime($iso))) - 1;
    $i  = $ym - $start_ym;
    return ($i >= 0 && $i < $n_months) ? $i : null;
};

$JS_LINES = [];

foreach ($income as $l) {
    $monthly = array_fill(0, $n_months, 0.0);
    $txns    = [];
    $first_ledger = null;

    if ($P['derive']) {
        foreach ($contributions_demo as $c) {
            if ($c['type'] !== $l['type']) { continue; }
            $iso = date('Y-m-d', strtotime('-' . (int) $c['days_ago'] . ' days'));
            $i   = $slot($iso);
            if ($i === null) { continue; }
            $u = bud_usd((float) $c['amount'], $c['currency']);
            $monthly[$i] += $u;
            $first_ledger = $first_ledger === null ? $i : min($first_ledger, $i);
            $m = $c['member_id'] !== null ? (array_column($members_demo, null, 'id')[$c['member_id']]['name'] ?? null) : null;
            $txns[] = ['who' => $m ?? 'Anonymous', 'when' => mu_date($iso), 'amount' => round($u, 2), 'ref' => $c['ref']];
        }
    }
    /* Spread the opening figure over the months that precede the ledger. */
    $upto = $first_ledger ?? $months_elapsed;
    if ($upto > 0 && $l['prior'] > 0) {
        $each = $l['prior'] / $upto;
        for ($i = 0; $i < $upto; $i++) { $monthly[$i] += $each; }
    }
    usort($txns, static fn($a, $b) => strcmp($b['when'], $a['when']));

    $JS_LINES['income:' . $l['type']] = [
        'kind' => 'Income', 'name' => $l['item'], 'icon' => $l['icon'], 'colour' => $l['colour'],
        'budget' => round($l['budget'], 2), 'actual' => round($l['actual'], 2),
        'variance' => round($l['variance'], 2), 'util' => round($l['util'], 1),
        'band' => $l['band'], 'notes' => $l['notes'],
        'varClass' => bud_var_class($l['flag']), 'flag' => $l['flag']['word'],
        'monthly' => array_map(static fn($v) => round($v, 2), $monthly),
        'budgetMonthly' => array_fill(0, $n_months, round($l['budget'] / $n_months, 2)),
        'txns' => array_slice($txns, 0, 12), 'txnCount' => count($txns),
        'link' => 'contributions.php',
    ];
}

foreach ($expense as $l) {
    $monthly = array_fill(0, $n_months, 0.0);
    $txns    = [];
    $first_ledger = null;

    if ($P['derive']) {
        foreach ($expenses_demo as $e) {
            if ($e['category'] !== $l['category'] || $e['status'] !== 'paid') { continue; }
            $iso = date('Y-m-d', strtotime('-' . (int) $e['days_ago'] . ' days'));
            $i   = $slot($iso);
            if ($i === null) { continue; }
            $u = bud_usd((float) $e['amount'], $e['currency']);
            $monthly[$i] += $u;
            $first_ledger = $first_ledger === null ? $i : min($first_ledger, $i);
            $txns[] = ['who' => $e['payee'], 'when' => mu_date($iso), 'amount' => round($u, 2), 'ref' => $e['ref']];
        }
    }
    $upto = $first_ledger ?? $months_elapsed;
    if ($upto > 0 && $l['prior'] > 0) {
        $each = $l['prior'] / $upto;
        for ($i = 0; $i < $upto; $i++) { $monthly[$i] += $each; }
    }
    usort($txns, static fn($a, $b) => strcmp($b['when'], $a['when']));

    $JS_LINES['expense:' . $l['category']] = [
        'kind' => 'Expenditure', 'name' => $l['name'], 'icon' => $l['icon'], 'colour' => $l['colour'],
        'budget' => round($l['budget'], 2), 'actual' => round($l['actual'], 2),
        'variance' => round($l['variance'], 2), 'util' => round($l['util'], 1),
        'band' => $l['band'], 'notes' => $l['notes'],
        'varClass' => bud_var_class($l['flag']), 'flag' => $l['flag']['word'],
        'monthly' => array_map(static fn($v) => round($v, 2), $monthly),
        'budgetMonthly' => array_fill(0, $n_months, round($l['budget'] / $n_months, 2)),
        'txns' => array_slice($txns, 0, 12), 'txnCount' => count($txns),
        'link' => 'expenses.php',
    ];
}
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function () {
  'use strict';

  var $  = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

  var LINES  = <?= json_encode($JS_LINES, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  var MONTHS = <?= json_encode($P['months']) ?>;
  var BASE   = '<?= $base_url ?>finance/';

  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function fmt(n) { return Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function usd(n) { return (n < 0 ? '−$' : '$') + fmt(Math.abs(n)); }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

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

  /* ────────────── the period selector reloads the whole page ────────────── */
  /* Both selectors drive the same thing, so they stay in step. */
  $$('[data-period]').forEach(function (sel) {
    sel.addEventListener('change', function () {
      var url = new URL(window.location.href);
      url.searchParams.set('budget', sel.value);
      window.location.href = url.toString();
    });
  });

  /* ─────────────────────── the numbers count up ─────────────────────── */
  /* Scoped to the strip. An unscoped [data-count] would also match anything
     using the attribute as data and overwrite its contents. */
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

  /* ─────────────────────────── the tabs ─────────────────────────── */
  var tabs = $$('[data-tab]');
  function setTab(name) {
    tabs.forEach(function (t) {
      var on = t.getAttribute('data-tab') === name;
      t.setAttribute('aria-selected', String(on));
      t.tabIndex = on ? 0 : -1;
      t.classList.toggle('is-on', on);
    });
    ['overview', 'income', 'expense', 'variance'].forEach(function (n) {
      var panel = $('#panel-' + n);
      if (panel) { panel.hidden = n !== name; }
    });
    if (name === 'overview') { drawOverview(); }
    if (name === 'variance') { drawVariance(); }
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

  /* ═════════════════════════════ the drawer ═════════════════════════════ */
  var scrim = $('[data-drawer-scrim]');
  var lnD   = $('#lineDrawer');
  var lastFocus = null, currentKey = null, lineChart = null;

  function openDrawer() {
    lastFocus = document.activeElement;
    lnD.hidden = false; scrim.hidden = false;
    document.body.style.overflow = 'hidden';
    $('[data-drawer-close]', lnD).focus();
  }
  function closeDrawers() {
    lnD.hidden = true; scrim.hidden = true;
    document.body.style.overflow = '';
    if (lastFocus) { lastFocus.focus(); lastFocus = null; }
  }
  scrim.addEventListener('click', closeDrawers);
  $$('[data-drawer-close]').forEach(function (b) { b.addEventListener('click', closeDrawers); });

  function openLine(key) {
    var l = LINES[key];
    if (!l) { return; }
    currentKey = key;

    $('#lnTitle').textContent = l.name;
    $('[data-ln-kind]').textContent = l.kind + (l.notes ? ' · ' + l.notes : '');
    var ico = $('[data-ln-ico]');
    ico.style.setProperty('--c', l.colour);
    ico.innerHTML = '<i class="fa-solid ' + l.icon + '"></i>';

    $('[data-ln-budget]').textContent = usd(l.budget);
    $('[data-ln-actual]').textContent = usd(l.actual);
    var v = $('[data-ln-var]');
    /* usd() already carries the minus sign; only a positive needs a marker. */
    v.textContent = (l.variance > 0 ? '+' : '') + usd(l.variance);
    /* Same rule as the tables: the colour follows the pace, not the sign. */
    v.className = l.varClass;
    v.title = l.flag + ' for this point in the period';

    var bar = $('[data-ln-bar]');
    bar.className = 'cellbar cellbar--' + (l.kind === 'Income'
      ? 'good'
      : (l.band === 'over' ? 'risk' : l.band === 'tight' ? 'warn' : 'good'));
    $('.cellbar__fill', bar).style.width = Math.min(100, l.util) + '%';
    $('[data-ln-pct]').textContent = Math.round(l.util) + '%';

    var link = $('[data-ln-link]');
    link.href = BASE + l.link;

    var box = $('[data-ln-txns]');
    box.innerHTML = '';
    if (!l.txns.length) {
      box.innerHTML = '<p class="hint">No transactions from the current ledger window fall against this line. '
        + 'Earlier months are carried as an opening figure.</p>';
    } else {
      l.txns.forEach(function (t) {
        var row = document.createElement('div');
        row.className = 'minirow';
        row.innerHTML = '<span class="minirow__text"><b>' + esc(t.who) + '</b><span>'
          + esc(t.when) + ' · ' + esc(t.ref) + '</span></span>'
          + '<span class="minirow__amt">' + usd(t.amount) + '</span>';
        box.appendChild(row);
      });
      if (l.txnCount > l.txns.length) {
        var more = document.createElement('p');
        more.className = 'hint';
        more.textContent = (l.txnCount - l.txns.length) + ' further transactions not shown.';
        box.appendChild(more);
      }
    }

    drawLineChart(l);
    openDrawer();
  }

  function drawLineChart(l) {
    if (!window.Chart) { return; }
    if (lineChart) { lineChart.destroy(); }
    lineChart = new Chart($('#lineChart'), {
      type: 'bar',
      data: {
        labels: MONTHS,
        datasets: [
          { label: 'Budgeted', data: l.budgetMonthly, backgroundColor: '#D3BAEA', borderRadius: 3 },
          { label: 'Actual',   data: l.monthly,       backgroundColor: l.colour,  borderRadius: 3 }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { display: true, position: 'bottom', labels: { boxWidth: 10, padding: 10, usePointStyle: true } },
          tooltip: { callbacks: { label: function (c) { return c.dataset.label + ': ' + usd(c.parsed.y); } } }
        },
        scales: {
          x: { grid: { display: false } },
          y: { grid: { color: '#ECE7F3' }, beginAtZero: true, ticks: { callback: function (v) { return '$' + v.toLocaleString(); } } }
        }
      }
    });
  }

  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-line]');
    if (b) { closeOwnMenu(b); openLine(b.getAttribute('data-line')); }
  }, true);

  /* ═════════════════════════════ the charts ═════════════════════════════ */
  var GRID = '#ECE7F3';
  if (window.Chart) {
    Chart.defaults.font.family = "'Plus Jakarta Sans', system-ui, sans-serif";
    Chart.defaults.font.size = 11;
    Chart.defaults.color = '#6B6480';
    Chart.defaults.animation = reduced ? false : { duration: 700 };
  }

  var BUD_INC = <?= json_encode(array_map('floatval', $P['budget_income'])) ?>;
  var BUD_EXP = <?= json_encode(array_map('floatval', $P['budget_expense'])) ?>;
  var ACT_INC = <?= json_encode($P['actual_income']) ?>;
  var ACT_EXP = <?= json_encode($P['actual_expense']) ?>;

  var UTIL = <?= json_encode(array_map(static fn($l) => [
        'name' => $l['name'], 'util' => round($l['util'], 1),
        'actual' => round($l['actual'], 2), 'budget' => round($l['budget'], 2),
        'band' => $l['band'],
      ], $expense), JSON_UNESCAPED_UNICODE) ?>;

  var WF = {
    expected: <?= round($expected_surplus, 2) ?>,
    income:   <?= round($inc_var_todate, 2) ?>,
    expense:  <?= round($exp_var_todate, 2) ?>,
    actual:   <?= round($actual_surplus, 2) ?>
  };

  var BURN = {
    actual: <?= json_encode($burn_actual) ?>,
    budget: <?= json_encode($burn_budget) ?>,
    projected: <?= json_encode($projection) ?>
  };

  var BAND_COLOUR = { good: '#0F766E', tight: '#B45309', over: '#B91C1C' };

  var drawn = {};

  function drawOverview() {
    if (drawn.overview || !window.Chart) { return; }
    drawn.overview = true;

    new Chart($('#ieChart'), {
      type: 'bar',
      data: {
        labels: MONTHS,
        datasets: [
          { label: 'Budgeted income',      data: BUD_INC, backgroundColor: '#D3BAEA', borderRadius: 3 },
          { label: 'Actual income',        data: ACT_INC, backgroundColor: '#662F97', borderRadius: 3 },
          { label: 'Budgeted expenditure', data: BUD_EXP, backgroundColor: '#F2D6C4', borderRadius: 3 },
          { label: 'Actual expenditure',   data: ACT_EXP, backgroundColor: '#B45309', borderRadius: 3 }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { display: true, position: 'bottom', labels: { boxWidth: 10, padding: 10, usePointStyle: true } },
          tooltip: { callbacks: { label: function (c) {
            return c.dataset.label + ': ' + (c.parsed.y === null ? 'not yet' : usd(c.parsed.y));
          } } }
        },
        scales: {
          x: { grid: { display: false } },
          y: { grid: { color: GRID }, beginAtZero: true, ticks: { callback: function (v) { return '$' + (v / 1000) + 'k'; } } }
        }
      }
    });

    /* Horizontal, and coloured by band rather than by series — the whole point
       is which lines have gone past their limit. */
    new Chart($('#utilChart'), {
      type: 'bar',
      data: {
        labels: UTIL.map(function (u) { return u.name; }),
        datasets: [{
          label: 'Utilisation',
          data: UTIL.map(function (u) { return u.util; }),
          backgroundColor: UTIL.map(function (u) { return BAND_COLOUR[u.band]; }),
          borderRadius: 3
        }]
      },
      options: {
        indexAxis: 'y', responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: function (c) {
            var u = UTIL[c.dataIndex];
            return u.util + '% — ' + usd(u.actual) + ' of ' + usd(u.budget);
          } } }
        },
        scales: {
          x: { grid: { color: GRID }, suggestedMax: 100, ticks: { callback: function (v) { return v + '%'; } } },
          y: { grid: { display: false } }
        }
      }
    });
  }

  function drawVariance() {
    if (drawn.variance || !window.Chart) { return; }
    drawn.variance = true;

    /* A waterfall out of floating bars: each step is drawn as [from, to], so
       the middle bars hang off the running total the way they should. */
    var a = WF.expected;
    var b = a + WF.income;
    var c = b + WF.expense;
    new Chart($('#waterfallChart'), {
      type: 'bar',
      data: {
        labels: ['Expected surplus\nto date', 'Income variance', 'Expenditure variance', 'Actual position'],
        datasets: [{
          label: 'Position',
          data: [[0, a], [a, b], [b, c], [0, WF.actual]],
          backgroundColor: [
            '#B48FDA',
            WF.income  >= 0 ? '#0F766E' : '#B91C1C',
            WF.expense >= 0 ? '#0F766E' : '#B91C1C',
            WF.actual  >= 0 ? '#662F97' : '#B91C1C'
          ],
          borderRadius: 3
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: function (ctx) {
            var v = ctx.raw;
            var delta = Array.isArray(v) ? v[1] - v[0] : v;
            return (delta >= 0 ? '+' : '') + usd(delta) + '  (to ' + usd(Array.isArray(v) ? v[1] : v) + ')';
          } } }
        },
        scales: {
          x: { grid: { display: false } },
          y: { grid: { color: GRID }, ticks: { callback: function (v) { return '$' + (v / 1000).toFixed(1) + 'k'; } } }
        }
      }
    });

    new Chart($('#burnChart'), {
      type: 'line',
      data: {
        labels: MONTHS,
        datasets: [
          { label: 'Budget line', data: BURN.budget, borderColor: '#B48FDA', backgroundColor: 'transparent',
            borderWidth: 2, borderDash: [5, 4], tension: 0, pointRadius: 0 },
          { label: 'Actual spend', data: BURN.actual, borderColor: '#662F97', backgroundColor: 'rgba(102,47,151,.10)',
            borderWidth: 2.5, tension: .3, fill: true, pointRadius: 0, pointHoverRadius: 4 },
          { label: 'Projected (estimate)', data: BURN.projected, borderColor: '#B45309', backgroundColor: 'transparent',
            borderWidth: 2, borderDash: [3, 3], tension: .3, pointRadius: 0, spanGaps: true }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: true, position: 'bottom', labels: { boxWidth: 10, padding: 10, usePointStyle: true } },
          tooltip: { callbacks: { label: function (ctx) {
            return ctx.parsed.y === null ? null : ctx.dataset.label + ': ' + usd(ctx.parsed.y);
          } } }
        },
        scales: {
          x: { grid: { display: false } },
          y: { grid: { color: GRID }, beginAtZero: true, ticks: { callback: function (v) { return '$' + (v / 1000) + 'k'; } } }
        }
      }
    });
  }

  /* ═════════════════════════════ modals ═════════════════════════════ */
  function openModal(m) { m.hidden = false; document.body.style.overflow = 'hidden'; var c = $('[data-close]', m); if (c) { c.focus(); } }
  function closeModal(m) {
    m.hidden = true;
    if ($$('.modal-scrim').every(function (x) { return x.hidden; }) && lnD.hidden) {
      document.body.style.overflow = '';
    }
  }
  document.addEventListener('click', function (e) {
    var cl = e.target.closest('[data-close]');
    if (cl) { closeModal(cl.closest('.modal-scrim')); return; }
    if (e.target.classList.contains('modal-scrim')) { closeModal(e.target); }
  });

  /* ── new / edit budget ── */
  var bgModal = $('#modalBudget');
  if (bgModal) {
    var copy = $('#bgCopy');
    copy.addEventListener('change', function () { $('#bgCopyFrom').disabled = !copy.checked; });

    var nb = $('#btnNewBudget');
    if (nb) {
      nb.addEventListener('click', function () {
        $('#bgTitle').textContent = 'New Budget';
        $('#bgName').value = '';
        $('#bgStatus').value = 'draft';
        openModal(bgModal);
      });
    }
    var eb = $('#btnEditBudget');
    if (eb) {
      eb.addEventListener('click', function () {
        $('#bgTitle').textContent = 'Edit Budget';
        $('#bgName').value   = <?= json_encode($P['name']) ?>;
        $('#bgStart').value  = <?= json_encode($P['start']) ?>;
        $('#bgEnd').value    = <?= json_encode($P['end']) ?>;
        $('#bgStatus').value = <?= json_encode($P['status']) ?>;
        $('#bgCurrency').value = <?= json_encode($P['currency']) ?>;
        var t = $('input[name="bgType"][value="' + <?= json_encode($P['type']) ?> + '"]');
        if (t) { t.checked = true; }
        openModal(bgModal);
      });
    }
    $('#bgSave').addEventListener('click', function () {
      if (!$('#bgName').value.trim()) { toast('The budget needs a name', 'error'); $('#bgName').focus(); return; }
      if ($('#bgEnd').value && $('#bgStart').value && $('#bgEnd').value < $('#bgStart').value) {
        toast('The end date falls before the start date', 'error'); $('#bgEnd').focus(); return;
      }
      closeModal(bgModal);
      toast('Budget saved', 'success');
    });
  }

  /* ── the line editor ── */
  var blModal = $('#modalLines');
  if (blModal) {
    var SEED = {
      income:  <?= json_encode(array_map(static fn($l) => ['name' => $l['item'], 'amount' => (float) $l['budget']], $income), JSON_UNESCAPED_UNICODE) ?>,
      expense: <?= json_encode(array_map(static fn($l) => ['name' => $l['name'], 'amount' => (float) $l['budget']], $expense), JSON_UNESCAPED_UNICODE) ?>
    };

    function addRow(which, name, amount) {
      var box = $('[data-lines="' + which + '"]');
      var row = document.createElement('div');
      row.className = 'lines__row';
      row.innerHTML =
          '<span class="lines__grip" aria-hidden="true"><i class="fa-solid fa-grip-vertical"></i></span>'
        + '<input class="input" type="text" aria-label="Line name" placeholder="Line item">'
        + '<input class="input lines__amt" type="text" inputmode="decimal" aria-label="Budgeted amount" placeholder="0.00">'
        + '<span class="lines__perm" aria-label="Per month">—</span>'
        + '<span class="lines__move">'
        + '<button class="iconbtn" type="button" data-move="up" aria-label="Move up"><i class="fa-solid fa-chevron-up" aria-hidden="true"></i></button>'
        + '<button class="iconbtn" type="button" data-move="down" aria-label="Move down"><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></button>'
        + '<button class="iconbtn" type="button" data-drop-line aria-label="Remove this line"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>'
        + '</span>';
      var inputs = $$('input', row);
      inputs[0].value = name || '';
      inputs[1].value = amount ? String(amount) : '';
      inputs.forEach(function (i) { i.addEventListener('input', recalc); });
      $('[data-drop-line]', row).addEventListener('click', function () { row.remove(); recalc(); });
      $$('[data-move]', row).forEach(function (b) {
        b.addEventListener('click', function () {
          var dir = b.getAttribute('data-move');
          if (dir === 'up' && row.previousElementSibling) { box.insertBefore(row, row.previousElementSibling); }
          if (dir === 'down' && row.nextElementSibling) { box.insertBefore(row.nextElementSibling, row); }
          row.scrollIntoView({ block: 'nearest' });
        });
      });
      box.appendChild(row);
    }

    function amountOf(row) {
      return parseFloat(String($('.lines__amt', row).value).replace(/[^0-9.]/g, '')) || 0;
    }
    function sumOf(which) {
      return $$('[data-lines="' + which + '"] .lines__row').reduce(function (t, r) { return t + amountOf(r); }, 0);
    }
    function recalc() {
      var n = MONTHS.length;
      ['income', 'expense'].forEach(function (which) {
        $$('[data-lines="' + which + '"] .lines__row').forEach(function (r) {
          var a = amountOf(r);
          $('.lines__perm', r).textContent = a ? usd(a / n) + '/mo' : '—';
        });
        $('[data-total="' + which + '"]').textContent = usd(sumOf(which));
      });
      var s = sumOf('income') - sumOf('expense');
      var box = $('[data-surplus]');
      box.classList.toggle('is-deficit', s < 0);
      $('.surplus__label', box).textContent = s < 0 ? 'Planned deficit' : 'Planned surplus';
      $('[data-surplus-value]').textContent = usd(s);
    }

    function fill() {
      ['income', 'expense'].forEach(function (which) {
        $('[data-lines="' + which + '"]').innerHTML = '';
        SEED[which].forEach(function (l) { addRow(which, l.name, l.amount); });
      });
      recalc();
    }

    $$('[data-addline]').forEach(function (b) {
      b.addEventListener('click', function () { addRow(b.getAttribute('data-addline'), '', ''); recalc(); });
    });

    /* "Distribute evenly" splits the section's total equally between its
       lines — a starting point for a budget nobody has costed yet. */
    $$('[data-distribute]').forEach(function (b) {
      b.addEventListener('click', function () {
        var which = b.getAttribute('data-distribute');
        var rows  = $$('[data-lines="' + which + '"] .lines__row');
        if (!rows.length) { return; }
        var each = sumOf(which) / rows.length;
        rows.forEach(function (r) { $('.lines__amt', r).value = each.toFixed(2); });
        recalc();
        toast('Split evenly across ' + rows.length + ' lines', 'info');
      });
    });

    var bl = $('#btnEditLines');
    if (bl) { bl.addEventListener('click', function () { fill(); openModal(blModal); }); }
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-editline]');
      if (b) { closeOwnMenu(b); fill(); openModal(blModal); }
    }, true);
    var le = $('#lnEdit');
    if (le) { le.addEventListener('click', function () { closeDrawers(); fill(); openModal(blModal); }); }

    $('#blSave').addEventListener('click', function () {
      var blank = $$('.lines__row').filter(function (r) { return !$('input', r).value.trim(); });
      if (blank.length) { toast('Every line needs a name', 'error'); $('input', blank[0]).focus(); return; }
      closeModal(blModal);
      toast('Budget lines saved', 'success');
    });
  }

  /* ── closing the budget ── */
  var clModal = $('#modalClose');
  if (clModal) {
    var cb = $('#btnCloseBudget');
    if (cb) { cb.addEventListener('click', function () { openModal(clModal); }); }
    $('#clGo').addEventListener('click', function () {
      closeModal(clModal);
      toast('Budget closed and locked', 'success');
    });
  }

  /* ────────────────────────── escape key ────────────────────────── */
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') { return; }
    var open = $$('.modal-scrim').filter(function (m) { return !m.hidden; });
    if (open.length) { open.forEach(function (m) { m.hidden = true; }); }
    else if (!lnD.hidden) { closeDrawers(); }
    if (lnD.hidden && $$('.modal-scrim').every(function (m) { return m.hidden; })) {
      document.body.style.overflow = '';
    }
  });

  drawOverview();
})();
</script>
<?php endif; ?>
<?php require __DIR__ . '/../components/footer.php'; ?>

<?php
/**
 * Mutendi CMS — Financial Reports.
 *
 * The reporting layer over every other finance page. What it produces gets
 * printed and tabled at board meetings, handed to auditors and sent to
 * denominational head office, so the Income Statement is laid out as a
 * document rather than a dashboard and the print stylesheet is part of the
 * feature, not an afterthought.
 *
 * Five tabs:
 *   Summary               the shape of the period
 *   Income Statement      a formal, printable statement
 *   Giving Analysis       who gives, how much, and who has stopped
 *   Expenditure Analysis  where it goes and how long approval takes
 *   Statements            per-member, per-project and per-group statements
 *
 * Every figure is cut from one 24-month series, so a date range genuinely
 * changes the numbers rather than relabelling them.
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
    function fin_delta(float $now, float $prev, bool $favour_high = true): string
    {
        if ($prev == 0.0) { return '<span class="delta is-flat">&mdash;</span>'; }
        $pct = (($now - $prev) / $prev) * 100;
        if (abs($pct) < 0.05) { return '<span class="delta is-flat">&mdash; no change</span>'; }
        $up  = $pct > 0;
        $cls = ($up === $favour_high) ? 'is-up' : 'is-down';
        $ico = $up ? 'fa-caret-up' : 'fa-caret-down';
        return '<span class="delta ' . $cls . '"><i class="fa-solid ' . $ico . '" aria-hidden="true"></i> '
             . number_format(abs($pct), 1) . '%</span>';
    }
}

$has_module  = mu_mod('finance');
$can_report  = mu_can('finance.reports');

/* ─────────────────────────── BRANCH AWARENESS ───────────────────────────
   Whose books these are. Entirely inert for a single church: is_multi_branch()
   is false, so no chip, column, filter or statement type is rendered.
   ──────────────────────────────────────────────────────────────────────── */
require_once __DIR__ . '/../components/branch-switcher.php';

$branch_aware   = is_multi_branch();
$viewing_all    = !$branch_aware || $current_branch === 'all' || $current_branch === null;
$show_branch    = $branch_aware && $viewing_all;
$branch_options = $branch_aware ? get_visible_branches() : [];

/* ══════════════════════ CUTTING THE PERIOD ══════════════════════
   One 24-month series underlies every figure on this page. A date range picks
   a window out of it and the previous window is the same number of months
   immediately before, so "against the previous period" always compares like
   with like.
   ──────────────────────────────────────────────────────────────── */

$cur_by_code = array_column($currencies, null, 'code');
$type_by_key = array_column($contribution_types, null, 'key');
$cat_by_key  = array_column($expense_categories, null, 'key');
$meth_by_key = array_column($payment_methods, null, 'key');

function rep_usd(float $amount, string $code): float
{
    global $cur_by_code;
    return $amount * ($cur_by_code[$code]['exchange_rate_to_usd'] ?? 1.0);
}
function rep_sym(string $code): string
{
    global $cur_by_code;
    return $cur_by_code[$code]['symbol'] ?? '$';
}

$N = count($report_monthly['income']);          /* 24 */
$LAST = $N - 1;                                  /* index of the current month */

/* Month labels, oldest first, ending on the current month. */
$all_labels = [];
for ($i = 0; $i < $N; $i++) { $all_labels[] = date('M y', strtotime('-' . ($LAST - $i) . ' months')); }

$RANGES = [
    'month'    => 'This Month',
    'last'     => 'Last Month',
    'quarter'  => 'This Quarter',
    'year'     => 'This Year',
    'lastyear' => 'Last Year',
    'custom'   => 'Custom',
];
$range = isset($_GET['range'], $RANGES[$_GET['range']]) ? $_GET['range'] : 'year';

$this_month_no = (int) date('n');
$q_start_no    = (int) (floor(($this_month_no - 1) / 3) * 3) + 1;   /* first month of this quarter */

switch ($range) {
    case 'month':    $from = $LAST;                          $to = $LAST; break;
    case 'last':     $from = max(0, $LAST - 1);              $to = $from; break;
    case 'quarter':  $from = max(0, $LAST - ($this_month_no - $q_start_no)); $to = $LAST; break;
    case 'lastyear': $from = max(0, $LAST - $this_month_no - 11); $to = max(0, $LAST - $this_month_no); break;
    case 'custom':
        /* Custom takes two month inputs; anything missing falls back to the
           widest window the series can offer. */
        $cf = isset($_GET['from']) ? (int) $_GET['from'] : 0;
        $ct = isset($_GET['to'])   ? (int) $_GET['to']   : $LAST;
        $from = max(0, min($LAST, $cf));
        $to   = max($from, min($LAST, $ct));
        break;
    default:         $from = max(0, $LAST - ($this_month_no - 1)); $to = $LAST; $range = 'year';
}

$len       = $to - $from + 1;
$prev_to   = max(0, $from - 1);
$prev_from = max(0, $from - $len);
$prev_len  = $from > 0 ? $prev_to - $prev_from + 1 : 0;

$labels     = array_slice($all_labels, $from, $len);
$inc_series = array_slice($report_monthly['income'], $from, $len);
$exp_series = array_slice($report_monthly['expenditure'], $from, $len);

$income_total = array_sum($inc_series);
$expend_total = array_sum($exp_series);
$net_total    = $income_total - $expend_total;

$prev_income = $prev_len > 0 ? array_sum(array_slice($report_monthly['income'], $prev_from, $prev_len)) : 0;
$prev_expend = $prev_len > 0 ? array_sum(array_slice($report_monthly['expenditure'], $prev_from, $prev_len)) : 0;
$prev_net    = $prev_income - $prev_expend;

/* Scaled when the two windows are different lengths, which happens at the
   very start of the series. Without it the comparison would be nonsense. */
$prev_scale  = ($prev_len > 0 && $prev_len !== $len) ? $len / $prev_len : 1.0;
$prev_income = $prev_income * $prev_scale;
$prev_expend = $prev_expend * $prev_scale;
$prev_net    = $prev_income - $prev_expend;

$period_label = $range === 'custom'
    ? $all_labels[$from] . ' – ' . $all_labels[$to]
    : $RANGES[$range] . ' (' . $all_labels[$from] . ($len > 1 ? ' – ' . $all_labels[$to] : '') . ')';

/* ── the breakdowns: this window's total, split by share ── */
$income_lines = [];
foreach ($report_income_shares as $key => $share) {
    $t = $type_by_key[$key] ?? null;
    $income_lines[] = [
        'key' => $key, 'name' => $t['name'] ?? $key,
        'icon' => $t['icon'] ?? 'fa-hand-holding-dollar', 'colour' => $t['colour'] ?? '#662F97',
        'share' => $share,
        'amount' => $income_total * ($share / 100),
        'prev'   => $prev_income * ($share / 100),
    ];
}
usort($income_lines, static fn($a, $b) => $b['amount'] <=> $a['amount']);

$expense_lines = [];
foreach ($report_expense_shares as $key => $share) {
    $c = $cat_by_key[$key] ?? null;
    $expense_lines[] = [
        'key' => $key, 'name' => $c['name'] ?? $key,
        'icon' => $c['icon'] ?? 'fa-ellipsis', 'colour' => $c['colour'] ?? '#662F97',
        'share' => $share,
        'amount' => $expend_total * ($share / 100),
        'prev'   => $prev_expend * ($share / 100),
    ];
}
usort($expense_lines, static fn($a, $b) => $b['amount'] <=> $a['amount']);

/* ── the running balance behind the cash-flow chart ── */
$cash_series = [];
$running = (float) $report_opening_balance;
foreach ($inc_series as $i => $v) {
    $running += $v - $exp_series[$i];
    $cash_series[] = round($running, 2);
}
$cash_now  = (float) $report_cash_at_hand['now'];
$cash_prev = (float) $report_cash_at_hand['prev'];

/* ── giving per member, from the segmentation ── */
$giving_members = array_sum(array_column($giving_segments, 'members'));
$givers_count   = $giving_members - ($giving_segments[4]['members'] ?? 0);
$giving_total   = array_sum(array_column($giving_segments, 'total'));
$per_member     = $givers_count > 0 ? $income_total / $givers_count : 0.0;
$per_member_prev= $givers_count > 0 ? $prev_income / $givers_count : 0.0;

/* ── a twelve-month giving trend with a three-month moving average ── */
$trend_from   = max(0, $LAST - 11);
$trend_labels = array_slice($all_labels, $trend_from, 12);
$trend_series = array_slice($report_monthly['income'], $trend_from, 12);
$trend_avg = [];
foreach ($trend_series as $i => $v) {
    $slice = array_slice($trend_series, max(0, $i - 2), min(3, $i + 1));
    $trend_avg[] = round(array_sum($slice) / count($slice), 2);
}
$spend_series = array_slice($report_monthly['expenditure'], $trend_from, 12);

/* Giving by type over time, for the stacked area chart: the same shares
   applied month by month. The shape is the series; the split is the mix. */
$stack_types = array_slice($income_lines, 0, 5);
$stack = [];
foreach ($stack_types as $l) {
    $stack[] = [
        'name' => $l['name'], 'colour' => $l['colour'],
        'data' => array_map(static fn($v) => round($v * ($l['share'] / 100), 2), $trend_series),
    ];
}

/* ── vendors, straight from the expense ledger ── */
$vendors = [];
foreach ($expenses_demo as $e) {
    if (!in_array($e['status'], ['paid', 'approved'], true)) { continue; }
    $k = $e['payee'];
    if (!isset($vendors[$k])) {
        $vendors[$k] = ['payee' => $k, 'total' => 0.0, 'count' => 0, 'last_days' => PHP_INT_MAX,
                        'category' => $e['category'], 'method' => $e['method'], 'currency' => $e['currency'],
                        'native' => 0.0];
    }
    $vendors[$k]['total']     += rep_usd((float) $e['amount'], $e['currency']);
    $vendors[$k]['native']    += (float) $e['amount'];
    $vendors[$k]['count']     += 1;
    $vendors[$k]['last_days']  = min($vendors[$k]['last_days'], (int) $e['days_ago']);
}
$vendors = array_values($vendors);
usort($vendors, static fn($a, $b) => $b['total'] <=> $a['total']);

$turn = $approval_turnaround;

$page_title = 'Financial Reports';
require __DIR__ . '/../components/header.php';
?>

<div class="page">

  <!-- ═════════════════════════════ PAGE HEADER ═════════════════════════════ -->
  <header class="page__head no-print">
    <div>
      <nav class="crumbs" aria-label="Breadcrumb">
        <a href="<?= $base_url ?>index.php">Dashboard</a>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span>Finance</span>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span aria-current="page">Financial Reports</span>
      </nav>
      <div class="title-row">
        <h1 class="page__title">Financial Reports</h1>
      </div>
      <p class="page__sub">Statements and analysis of your church finances.</p>
    </div>

    <?php if ($has_module && $can_report): ?>
      <div class="page__actions">
        <label class="offscreen" for="rangePick">Date range</label>
        <select class="select" id="rangePick" data-range>
          <?php foreach ($RANGES as $k => $lab): ?>
            <option value="<?= $k ?>" <?= $k === $range ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
          <?php endforeach; ?>
        </select>

        <div class="drop" data-menu>
          <button class="btn btn--ghost" type="button" aria-haspopup="true" aria-expanded="false" data-menu-btn>
            <i class="fa-solid fa-file-export" aria-hidden="true"></i> Export
            <i class="fa-solid fa-chevron-down" style="font-size:10px;opacity:.7" aria-hidden="true"></i>
          </button>
          <div class="menu" data-menu-panel hidden>
            <button class="menu__item" type="button" data-export="PDF"><i class="fa-solid fa-file-pdf" aria-hidden="true"></i> Export as PDF</button>
            <button class="menu__item" type="button" data-export="Excel"><i class="fa-solid fa-file-excel" aria-hidden="true"></i> Export as Excel</button>
            <button class="menu__item" type="button" data-export="CSV"><i class="fa-solid fa-file-csv" aria-hidden="true"></i> Export as CSV</button>
          </div>
        </div>

        <button class="iconbtn iconbtn--bordered" type="button" id="btnPrint" aria-label="Print this report" title="Print">
          <i class="fa-solid fa-print" aria-hidden="true"></i>
        </button>

        <?php if (mu_mod('communication')): ?>
          <button class="btn" type="button" id="btnSchedule">
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
      <h3>The Finance module is switched off</h3>
      <p>Your church's plan does not include financial reporting. A church administrator can request it from the platform team.</p>
      <a class="btn" href="<?= $base_url ?>index.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Dashboard</a>
    </div>
  </section>

<?php elseif (!$can_report): ?>

  <section class="panel">
    <div class="empty">
      <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-lock"></i></span>
      <h3>You do not have access to financial reports</h3>
      <p>These statements carry the whole church's giving, including figures for named members. Ask an administrator for the financial reporting permission.</p>
      <a class="btn" href="<?= $base_url ?>index.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Dashboard</a>
    </div>
  </section>

<?php else: ?>

  <!-- ══════════════ the header that only appears on paper ══════════════ -->
  <div class="printhead" aria-hidden="true">
    <img class="printhead__logo" src="<?= htmlspecialchars($church['logo']) ?>" alt="">
    <div class="printhead__text">
      <strong><?= htmlspecialchars($church['name']) ?></strong>
      <span data-print-title>Financial Report</span>
      <span><?= htmlspecialchars($period_label) ?> &middot; prepared <?= date('d M Y') ?></span>
    </div>
  </div>

  <!-- ══════════════════════ RANGE AND CURRENCY ══════════════════════ -->
  <section class="repbar no-print">
    <div class="repbar__range">
      <span class="repbar__label">Period</span>
      <div class="svcviews" role="group" aria-label="Date range">
        <?php foreach ($RANGES as $k => $lab): ?>
          <?php if ($k === 'custom') { continue; } ?>
          <a class="svcview<?= $k === $range ? ' is-on' : '' ?>"
             href="?range=<?= $k ?>&amp;role=<?= urlencode($demo_role) ?>"
             <?= $k === $range ? 'aria-current="true"' : '' ?>><?= htmlspecialchars($lab) ?></a>
        <?php endforeach; ?>
        <button class="svcview<?= $range === 'custom' ? ' is-on' : '' ?>" type="button" id="btnCustom" aria-pressed="<?= $range === 'custom' ? 'true' : 'false' ?>">Custom</button>
      </div>
      <p class="repbar__note"><?= htmlspecialchars($period_label) ?> &middot; <?= $len ?> month<?= $len === 1 ? '' : 's' ?></p>
    </div>

    <!-- Two readings of the same figures. Nothing is recalculated — only which
         one leads — and the rate used is always stated. -->
    <div class="curtoggle">
      <span class="repbar__label">Currency</span>
      <div class="curtoggle__btns">
        <button class="curtoggle__btn is-on" type="button" data-cur-mode="usd" aria-pressed="true">All in USD</button>
        <button class="curtoggle__btn" type="button" data-cur-mode="original" aria-pressed="false">As received</button>
      </div>
      <p class="curtoggle__rates">
        <?php
          $rates = [];
          foreach ($currencies as $c) {
              if ($c['code'] === 'USD') { continue; }
              $rates[] = '1 ' . $c['code'] . ' = $' . rtrim(rtrim(number_format((float) $c['exchange_rate_to_usd'], 4, '.', ''), '0'), '.');
          }
          echo htmlspecialchars(implode('  ·  ', $rates));
        ?>
      </p>
    </div>
  </section>

  <!-- ══════════════ custom range, revealed by the Custom button ══════════════ -->
  <form class="customrange no-print" id="customRange" method="get" <?= $range === 'custom' ? '' : 'hidden' ?>>
    <input type="hidden" name="range" value="custom">
    <input type="hidden" name="role" value="<?= htmlspecialchars($demo_role) ?>">
    <div class="field">
      <label for="cFrom">From</label>
      <select class="select" id="cFrom" name="from">
        <?php foreach ($all_labels as $i => $lab): ?>
          <option value="<?= $i ?>" <?= $i === $from ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="cTo">To</label>
      <select class="select" id="cTo" name="to">
        <?php foreach ($all_labels as $i => $lab): ?>
          <option value="<?= $i ?>" <?= $i === $to ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn" type="submit"><i class="fa-solid fa-check" aria-hidden="true"></i> Apply range</button>
  </form>


  <!-- ══════════════════════════ FILTER BAR ══════════════════════════ -->
  <section class="filters no-print" id="filters">
    <button class="filters__toggle" type="button" id="fToggle" aria-expanded="false" aria-controls="filters">
      <i class="fa-solid fa-sliders" aria-hidden="true"></i> Filters
      <span class="count-chip" data-filter-n hidden>0</span>
      <span style="flex:1"></span>
      <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
    </button>

    <div class="filters__grid">
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

      <div class="field">
        <label for="fType">Contribution type</label>
        <select class="select" id="fType" data-filter>
          <option value="">All</option>
          <?php foreach ($income_lines as $l): ?>
            <option value="<?= htmlspecialchars($l['key']) ?>"><?= htmlspecialchars($l['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="fCategory">Expense category</label>
        <select class="select" id="fCategory" data-filter>
          <option value="">All</option>
          <?php foreach ($expense_lines as $l): ?>
            <option value="<?= htmlspecialchars($l['key']) ?>"><?= htmlspecialchars($l['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="fMethod">Payment method</label>
        <select class="select" id="fMethod" data-filter>
          <option value="">All</option>
          <?php foreach ($payment_methods as $m): ?>
            <option value="<?= htmlspecialchars($m['key']) ?>"><?= htmlspecialchars($m['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="fCurrency">Currency</label>
        <select class="select" id="fCurrency" data-filter>
          <option value="">All</option>
          <?php foreach ($currencies as $c): ?>
            <option value="<?= htmlspecialchars($c['code']) ?>"><?= htmlspecialchars($c['code']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filters__actions">
        <button class="btn" type="button" data-toast="Filters applied"><i class="fa-solid fa-check" aria-hidden="true"></i> Apply</button>
        <button class="btn btn--ghost" type="button" data-reset-filters><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Reset</button>
      </div>
    </div>

    <div class="chips-row" data-filter-chips hidden></div>
  </section>


  <!-- ═════════════════════════════ STAT STRIP ═════════════════════════════ -->
  <section class="stat-strip" aria-label="The period at a glance">
    <div class="stat-tile is-static">
      <span class="stat-tile__icon tone-green" aria-hidden="true"><i class="fa-solid fa-arrow-trend-up"></i></span>
      <span class="stat-tile__body">
        <span class="stat-tile__value">$<span data-count="<?= (int) round($income_total) ?>">0</span></span>
        <span class="stat-tile__label">Total Income</span>
        <?= fin_delta($income_total, $prev_income, true) ?>
      </span>
    </div>

    <div class="stat-tile is-static">
      <span class="stat-tile__icon tone-amber" aria-hidden="true"><i class="fa-solid fa-arrow-trend-down"></i></span>
      <span class="stat-tile__body">
        <span class="stat-tile__value">$<span data-count="<?= (int) round($expend_total) ?>">0</span></span>
        <span class="stat-tile__label">Total Expenditure</span>
        <?= fin_delta($expend_total, $prev_expend, false) ?>
      </span>
    </div>

    <div class="stat-tile is-static">
      <span class="stat-tile__icon <?= $net_total >= 0 ? 'tone-green' : 'tone-red' ?>" aria-hidden="true">
        <i class="fa-solid fa-scale-balanced"></i>
      </span>
      <span class="stat-tile__body">
        <span class="stat-tile__value <?= $net_total >= 0 ? 'is-surplus' : 'is-deficit' ?>">
          <?= $net_total < 0 ? '&minus;' : '' ?>$<span data-count="<?= (int) round(abs($net_total)) ?>">0</span>
        </span>
        <span class="stat-tile__label">Net Position &mdash; <?= $net_total >= 0 ? 'surplus' : 'deficit' ?></span>
        <?= fin_delta($net_total, $prev_net, true) ?>
      </span>
    </div>

    <div class="stat-tile is-static">
      <span class="stat-tile__icon tone-blue" aria-hidden="true"><i class="fa-solid fa-vault"></i></span>
      <span class="stat-tile__body">
        <span class="stat-tile__value">$<span data-count="<?= (int) round($cash_now) ?>">0</span></span>
        <span class="stat-tile__label">Cash at Hand</span>
        <?= fin_delta($cash_now, $cash_prev, true) ?>
      </span>
    </div>
  </section>


  <!-- ══════════════════════════════ THE TABS ══════════════════════════════ -->
  <div class="tabs no-print" role="tablist" aria-label="Report views">
    <?php
      $tabs = [
        ['summary',   'Summary',              'fa-chart-simple'],
        ['statement', 'Income Statement',     'fa-file-invoice-dollar'],
        ['giving',    'Giving Analysis',      'fa-hand-holding-dollar'],
        ['spending',  'Expenditure Analysis', 'fa-receipt'],
        ['statements','Statements',           'fa-file-lines'],
      ];
    ?>
    <?php foreach ($tabs as $i => [$k, $lab, $ic]): ?>
      <button class="tab<?= $i === 0 ? ' is-on' : '' ?>" type="button" role="tab"
              id="tab-<?= $k ?>" aria-controls="panel-<?= $k ?>"
              aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" <?= $i === 0 ? '' : 'tabindex="-1"' ?>
              data-tab="<?= $k ?>">
        <i class="fa-solid <?= $ic ?>" aria-hidden="true"></i> <?= $lab ?>
      </button>
    <?php endforeach; ?>
  </div>

  <!-- ═══════════════════════════ TAB 1 — SUMMARY ═══════════════════════════ -->
  <section class="tabpanel" id="panel-summary" role="tabpanel" aria-labelledby="tab-summary">

    <section class="chartcard">
      <header class="chartcard__head">
        <div>
          <h2>Income vs Expenditure</h2>
          <p>Twelve months. Bars are money in and out; the line is the net position each month.</p>
        </div>
        <button class="iconbtn no-print" type="button" data-zoomchart="ieChart" aria-label="Enlarge this chart">
          <i class="fa-solid fa-expand" aria-hidden="true"></i>
        </button>
      </header>
      <div class="chartbox chartbox--tall"><canvas id="ieChart"></canvas></div>
    </section>

    <div class="chartgrid chartgrid--2" style="margin-top:16px">
      <section class="chartcard">
        <header class="chartcard__head">
          <div>
            <h2>Income Breakdown</h2>
            <p>By contribution type, for <?= htmlspecialchars($period_label) ?>.</p>
          </div>
          <button class="iconbtn no-print" type="button" data-zoomchart="incChart" aria-label="Enlarge this chart">
            <i class="fa-solid fa-expand" aria-hidden="true"></i>
          </button>
        </header>
        <div class="chartbox chartbox--tall"><canvas id="incChart"></canvas></div>
      </section>

      <section class="chartcard">
        <header class="chartcard__head">
          <div>
            <h2>Expenditure Breakdown</h2>
            <p>By category, for the same period.</p>
          </div>
          <button class="iconbtn no-print" type="button" data-zoomchart="expChart" aria-label="Enlarge this chart">
            <i class="fa-solid fa-expand" aria-hidden="true"></i>
          </button>
        </header>
        <div class="chartbox chartbox--tall"><canvas id="expChart"></canvas></div>
      </section>
    </div>

    <section class="chartcard" style="margin-top:16px">
      <header class="chartcard__head">
        <div>
          <h2>Cash Flow</h2>
          <p>Running balance across the period, opening at $<?= number_format((float) $report_opening_balance, 2) ?>.</p>
        </div>
        <button class="iconbtn no-print" type="button" data-zoomchart="cashChart" aria-label="Enlarge this chart">
          <i class="fa-solid fa-expand" aria-hidden="true"></i>
        </button>
      </header>
      <div class="chartbox chartbox--tall"><canvas id="cashChart"></canvas></div>
    </section>

    <div class="chartgrid chartgrid--3" style="margin-top:16px">
      <section class="chartcard">
        <header class="chartcard__head">
          <div>
            <h2>Giving per Member</h2>
            <p>Across <?= number_format($givers_count) ?> people who have given.</p>
          </div>
        </header>
        <div class="bigfig">
          <span class="bigfig__value">$<?= number_format($per_member, 2) ?></span>
          <span class="bigfig__cap">
            <?= fin_delta($per_member, $per_member_prev, true) ?>
            against the previous period
          </span>
        </div>
        <div class="chartbox chartbox--short"><canvas id="pmChart"></canvas></div>
      </section>

      <section class="chartcard">
        <header class="chartcard__head">
          <div>
            <h2>Largest Income Sources</h2>
            <p>Top five for this period.</p>
          </div>
        </header>
        <ol class="rankrow-list">
          <?php foreach (array_slice($income_lines, 0, 5) as $i => $l): ?>
            <li class="rankrow">
              <span class="rankrow__pos<?= $i < 3 ? ' is-medal is-m' . ($i + 1) : '' ?>"><?= $i + 1 ?></span>
              <span class="catico" style="--c:<?= htmlspecialchars($l['colour']) ?>" aria-hidden="true">
                <i class="fa-solid <?= htmlspecialchars($l['icon']) ?>"></i>
              </span>
              <span class="rankrow__text">
                <span class="rankrow__name"><?= htmlspecialchars($l['name']) ?></span>
                <span class="rankrow__meta"><?= number_format($l['share'], 1) ?>% of income</span>
              </span>
              <span class="rankamt">$<?= number_format($l['amount'], 2) ?></span>
            </li>
          <?php endforeach; ?>
        </ol>
      </section>

      <section class="chartcard">
        <header class="chartcard__head">
          <div>
            <h2>Largest Expenses</h2>
            <p>Top five categories for this period.</p>
          </div>
        </header>
        <ol class="rankrow-list">
          <?php foreach (array_slice($expense_lines, 0, 5) as $i => $l): ?>
            <li class="rankrow">
              <span class="rankrow__pos<?= $i < 3 ? ' is-medal is-m' . ($i + 1) : '' ?>"><?= $i + 1 ?></span>
              <span class="catico" style="--c:<?= htmlspecialchars($l['colour']) ?>" aria-hidden="true">
                <i class="fa-solid <?= htmlspecialchars($l['icon']) ?>"></i>
              </span>
              <span class="rankrow__text">
                <span class="rankrow__name"><?= htmlspecialchars($l['name']) ?></span>
                <span class="rankrow__meta"><?= number_format($l['share'], 1) ?>% of spending</span>
              </span>
              <span class="rankamt">$<?= number_format($l['amount'], 2) ?></span>
            </li>
          <?php endforeach; ?>
        </ol>
      </section>
    </div>
  </section>

  <!-- ═══════════════════════ TAB 2 — INCOME STATEMENT ═══════════════════════ -->
  <!-- A document, not a dashboard. Everything here is sized and ruled for A4. -->
  <section class="tabpanel" id="panel-statement" role="tabpanel" aria-labelledby="tab-statement" hidden>
    <div class="paper">

      <header class="paper__head">
        <img class="paper__logo" src="<?= htmlspecialchars($church['logo']) ?>" alt="">
        <div class="paper__org">
          <h2><?= htmlspecialchars($church['name']) ?></h2>
          <p><?= htmlspecialchars(t('org_singular')) ?> &middot; <?= htmlspecialchars($church['code']) ?></p>
        </div>
        <div class="paper__meta">
          <span>Prepared</span>
          <b><?= date('d M Y') ?></b>
        </div>
      </header>

      <div class="paper__title">
        <h3>Statement of Income and Expenditure</h3>
        <p>For <?= htmlspecialchars($period_label) ?><?= $prev_len > 0 ? ', with the preceding ' . $prev_len . ' month' . ($prev_len === 1 ? '' : 's') . ' for comparison' : '' ?>. All figures in United States dollars.</p>
      </div>

      <div class="stmtwrap">
      <table class="stmt">
        <thead>
          <tr>
            <th scope="col">Description</th>
            <th scope="col" class="stmt__num">This period</th>
            <th scope="col" class="stmt__num">Previous period</th>
            <th scope="col" class="stmt__num">Variance</th>
          </tr>
        </thead>

        <tbody>
          <tr class="stmt__section"><th colspan="4" scope="colgroup">Income</th></tr>
          <?php foreach ($income_lines as $l): ?>
            <?php $v = $l['amount'] - $l['prev']; ?>
            <tr data-stmt-row data-kind="income" data-key="<?= htmlspecialchars($l['key']) ?>">
              <td><?= htmlspecialchars($l['name']) ?></td>
              <td class="stmt__num"><?= number_format($l['amount'], 2) ?></td>
              <td class="stmt__num"><?= number_format($l['prev'], 2) ?></td>
              <td class="stmt__num <?= $v >= 0 ? 'is-good' : 'is-bad' ?>"><?= $v >= 0 ? '' : '(' ?><?= number_format(abs($v), 2) ?><?= $v >= 0 ? '' : ')' ?></td>
            </tr>
          <?php endforeach; ?>
          <tr class="stmt__subtotal">
            <td>Total income</td>
            <td class="stmt__num" data-stmt-total="income"><?= number_format($income_total, 2) ?></td>
            <td class="stmt__num" data-stmt-total="income-prev"><?= number_format($prev_income, 2) ?></td>
            <td class="stmt__num"><?= $income_total - $prev_income >= 0 ? '' : '(' ?><?= number_format(abs($income_total - $prev_income), 2) ?><?= $income_total - $prev_income >= 0 ? '' : ')' ?></td>
          </tr>

          <tr class="stmt__section"><th colspan="4" scope="colgroup">Expenditure</th></tr>
          <?php foreach ($expense_lines as $l): ?>
            <?php $v = $l['amount'] - $l['prev']; ?>
            <tr data-stmt-row data-kind="expense" data-key="<?= htmlspecialchars($l['key']) ?>">
              <td><?= htmlspecialchars($l['name']) ?></td>
              <td class="stmt__num"><?= number_format($l['amount'], 2) ?></td>
              <td class="stmt__num"><?= number_format($l['prev'], 2) ?></td>
              <!-- On expenditure an increase is the unwelcome direction. -->
              <td class="stmt__num <?= $v > 0 ? 'is-bad' : 'is-good' ?>"><?= $v >= 0 ? '' : '(' ?><?= number_format(abs($v), 2) ?><?= $v >= 0 ? '' : ')' ?></td>
            </tr>
          <?php endforeach; ?>
          <tr class="stmt__subtotal">
            <td>Total expenditure</td>
            <td class="stmt__num" data-stmt-total="expense"><?= number_format($expend_total, 2) ?></td>
            <td class="stmt__num" data-stmt-total="expense-prev"><?= number_format($prev_expend, 2) ?></td>
            <td class="stmt__num"><?= $expend_total - $prev_expend >= 0 ? '' : '(' ?><?= number_format(abs($expend_total - $prev_expend), 2) ?><?= $expend_total - $prev_expend >= 0 ? '' : ')' ?></td>
          </tr>
        </tbody>

        <tfoot>
          <tr class="stmt__net">
            <td>Net <?= $net_total >= 0 ? 'surplus' : 'deficit' ?> for the period</td>
            <td class="stmt__num"><?= $net_total >= 0 ? '' : '(' ?><?= number_format(abs($net_total), 2) ?><?= $net_total >= 0 ? '' : ')' ?></td>
            <td class="stmt__num"><?= $prev_net >= 0 ? '' : '(' ?><?= number_format(abs($prev_net), 2) ?><?= $prev_net >= 0 ? '' : ')' ?></td>
            <td class="stmt__num"><?= $net_total - $prev_net >= 0 ? '' : '(' ?><?= number_format(abs($net_total - $prev_net), 2) ?><?= $net_total - $prev_net >= 0 ? '' : ')' ?></td>
          </tr>
        </tfoot>
      </table>
      </div>

      <p class="paper__note">
        Figures in brackets are negative. Amounts received in other currencies have been converted at
        <?= htmlspecialchars(implode('; ', $rates)) ?>.
      </p>

      <footer class="paper__foot">
        <div class="sigline">
          <span class="sigline__rule" aria-hidden="true"></span>
          <b>Prepared by</b>
          <span><?= htmlspecialchars($user['name']) ?> &middot; <?= htmlspecialchars($user['role_label']) ?></span>
        </div>
        <div class="sigline">
          <span class="sigline__rule" aria-hidden="true"></span>
          <b>Date</b>
          <span><?= date('d M Y') ?></span>
        </div>
        <div class="sigline">
          <span class="sigline__rule" aria-hidden="true"></span>
          <b>Approved by</b>
          <span>Chairperson, Finance Committee</span>
        </div>
      </footer>
    </div>
  </section>

  <!-- ═══════════════════════ TAB 3 — GIVING ANALYSIS ═══════════════════════ -->
  <section class="tabpanel" id="panel-giving" role="tabpanel" aria-labelledby="tab-giving" hidden>

    <div class="chartgrid chartgrid--2">
      <section class="chartcard">
        <header class="chartcard__head">
          <div>
            <h2>Giving Trend</h2>
            <p>Twelve months, with a three-month moving average over the top.</p>
          </div>
          <button class="iconbtn no-print" type="button" data-zoomchart="giveChart" aria-label="Enlarge this chart">
            <i class="fa-solid fa-expand" aria-hidden="true"></i>
          </button>
        </header>
        <div class="chartbox chartbox--tall"><canvas id="giveChart"></canvas></div>
      </section>

      <section class="chartcard">
        <header class="chartcard__head">
          <div>
            <h2>Giving by Type Over Time</h2>
            <p>The five largest types, stacked.</p>
          </div>
          <button class="iconbtn no-print" type="button" data-zoomchart="stackChart" aria-label="Enlarge this chart">
            <i class="fa-solid fa-expand" aria-hidden="true"></i>
          </button>
        </header>
        <div class="chartbox chartbox--tall"><canvas id="stackChart"></canvas></div>
      </section>
    </div>

    <section class="panel" style="margin-top:16px">
      <header class="chartcard__head">
        <div>
          <h2>Giver Segmentation</h2>
          <p>Where the giving comes from &mdash; and how much of it rests on how few people.</p>
        </div>
      </header>
      <?php
        $seg_top = $giving_segments[0];
        $seg_pct = $giving_total > 0 ? ($seg_top['total'] / $giving_total) * 100 : 0;
        $seg_mpc = $giving_members > 0 ? ($seg_top['members'] / $giving_members) * 100 : 0;
      ?>
      <div class="at-notice at-notice--warn" role="note">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        <div class="at-notice__body">
          <strong>Concentration risk</strong>
          <span>
            <b><?= number_format($seg_mpc, 1) ?>%</b> of members give
            <b><?= number_format($seg_pct, 1) ?>%</b> of the money.
            Losing a handful of regular givers would be felt immediately.
          </span>
        </div>
      </div>

      <div class="dt-wrap">
        <table class="dt" id="segTable">
          <thead>
            <tr>
              <th>Segment</th>
              <th style="text-align:right">Members</th>
              <th style="text-align:right">Total Contributed</th>
              <th style="text-align:right">Average</th>
              <th style="min-width:160px">% of Total Giving</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($giving_segments as $s): ?>
              <?php
                $avg = $s['members'] > 0 ? $s['total'] / $s['members'] : 0;
                $pct = $giving_total > 0 ? ($s['total'] / $giving_total) * 100 : 0;
              ?>
              <tr>
                <td>
                  <span class="minirow minirow--tight">
                    <span class="segdot" style="--c:<?= htmlspecialchars($s['colour']) ?>" aria-hidden="true"></span>
                    <span class="minirow__text">
                      <b><?= htmlspecialchars($s['name']) ?></b>
                      <span><?= htmlspecialchars($s['desc']) ?></span>
                    </span>
                  </span>
                </td>
                <td class="num"><?= number_format($s['members']) ?></td>
                <td class="num"><b>$<?= number_format($s['total'], 2) ?></b></td>
                <td class="num">$<?= number_format($avg, 2) ?></td>
                <td>
                  <span class="cellbar">
                    <span class="cellbar__track"><span class="cellbar__fill" style="width:<?= round($pct, 1) ?>%;background:<?= htmlspecialchars($s['colour']) ?>"></span></span>
                    <b><?= number_format($pct, 1) ?>%</b>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr class="dt__total">
              <td>All members</td>
              <td class="num"><?= number_format($giving_members) ?></td>
              <td class="num"><b>$<?= number_format($giving_total, 2) ?></b></td>
              <td class="num">$<?= number_format($givers_count > 0 ? $giving_total / $givers_count : 0, 2) ?></td>
              <td>100.0%</td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div class="dt-cards">
        <?php foreach ($giving_segments as $s): ?>
          <?php $pct = $giving_total > 0 ? ($s['total'] / $giving_total) * 100 : 0; ?>
          <article class="pcard pcard--flat">
            <header class="pcard__head">
              <span class="segdot" style="--c:<?= htmlspecialchars($s['colour']) ?>" aria-hidden="true"></span>
              <span class="pcard__text">
                <span class="pcard__name"><?= htmlspecialchars($s['name']) ?></span>
                <span class="pcard__meta"><?= htmlspecialchars($s['desc']) ?></span>
              </span>
            </header>
            <dl class="pcard__dl">
              <div><dt>Members</dt><dd><?= number_format($s['members']) ?></dd></div>
              <div><dt>Total</dt><dd>$<?= number_format($s['total'], 2) ?></dd></div>
              <div><dt>Average</dt><dd>$<?= number_format($s['members'] > 0 ? $s['total'] / $s['members'] : 0, 2) ?></dd></div>
              <div><dt>Share</dt><dd><?= number_format($pct, 1) ?>%</dd></div>
            </dl>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- Named giving figures. Only ever rendered behind finance.reports, and
         the page says so rather than leaving it implied. -->
    <section class="panel" style="margin-top:16px">
      <header class="chartcard__head">
        <div>
          <h2>Top Givers</h2>
          <p>Ranked by total contributed over the last twelve months.</p>
        </div>
        <span class="confchip"><i class="fa-solid fa-user-shield" aria-hidden="true"></i> Confidential</span>
      </header>
      <div class="at-notice" role="note">
        <i class="fa-solid fa-lock" aria-hidden="true"></i>
        <div class="at-notice__body">
          <strong>This table names individuals and their giving</strong>
          <span>It is visible only to those holding the financial reporting permission. Do not circulate it outside the finance committee.</span>
        </div>
      </div>
      <div class="dt-wrap">
        <table class="dt" id="topTable">
          <thead>
            <tr>
              <th style="width:40px">#</th>
              <th>Member</th>
              <th>Segment</th>
              <th style="text-align:right">Total Given</th>
              <th style="text-align:right">Contributions</th>
              <th style="text-align:right">Average</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($top_givers as $i => $g): ?>
              <tr>
                <td class="num"><?= $i + 1 ?></td>
                <td>
                  <span class="minirow minirow--tight">
                    <?= mu_av($g['name'], 'sm') ?>
                    <span class="minirow__text"><b><?= htmlspecialchars($g['name']) ?></b></span>
                  </span>
                </td>
                <td><span class="excat" style="--c:<?= $g['segment'] === 'Regular' ? '#0F766E' : '#662F97' ?>"><?= htmlspecialchars($g['segment']) ?></span></td>
                <td class="num"><b>$<?= number_format($g['total'], 2) ?></b></td>
                <td class="num"><?= $g['count'] ?></td>
                <td class="num">$<?= number_format($g['total'] / max(1, $g['count']), 2) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="dt-cards">
        <?php foreach ($top_givers as $i => $g): ?>
          <article class="pcard pcard--flat">
            <header class="pcard__head">
              <?= mu_av($g['name'], 'sm') ?>
              <span class="pcard__text">
                <span class="pcard__name"><?= $i + 1 ?>. <?= htmlspecialchars($g['name']) ?></span>
                <span class="pcard__meta"><?= htmlspecialchars($g['segment']) ?> &middot; <?= $g['count'] ?> contributions</span>
              </span>
              <span class="rankamt">$<?= number_format($g['total'], 0) ?></span>
            </header>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="chartcard" style="margin-top:16px">
      <header class="chartcard__head">
        <div>
          <h2>Giving by Demographics</h2>
          <p>Total given and the number of givers behind it.</p>
        </div>
        <div class="svcviews no-print" role="group" aria-label="Which cut to show">
          <button class="svcview is-on" type="button" data-demo-cut="age" aria-pressed="true">Age</button>
          <button class="svcview" type="button" data-demo-cut="gender" aria-pressed="false">Gender</button>
          <?php if (mu_mod('departments')): ?>
            <button class="svcview" type="button" data-demo-cut="department" aria-pressed="false">Department</button>
          <?php endif; ?>
        </div>
      </header>
      <div class="chartbox chartbox--tall"><canvas id="demoChart"></canvas></div>
    </section>

    <section class="panel" style="margin-top:16px">
      <header class="chartcard__head">
        <div>
          <h2>Lapsed Givers</h2>
          <p>Gave steadily, then stopped. <?= count($lapsed_givers) ?> people worth a phone call.</p>
        </div>
      </header>
      <ul class="linelist">
        <?php foreach ($lapsed_givers as $g): ?>
          <?php $days = (int) round((strtotime(date('Y-m-d')) - strtotime($g['last_gave'])) / 86400); ?>
          <li class="linelist__row">
            <?= mu_av($g['name'], 'sm') ?>
            <span class="linelist__text">
              <b><?= htmlspecialchars($g['name']) ?></b>
              <span>Gave in <?= $g['months_given'] ?> months &middot; $<?= number_format($g['previous_total'], 2) ?> in total</span>
            </span>
            <span class="linelist__figs">
              <b><?= mu_date($g['last_gave']) ?></b>
              <span><?= mu_ago($days) ?></span>
            </span>
            <?php if (mu_mod('communication')): ?>
              <button class="btn btn--ghost btn--sm no-print" type="button" data-followup="<?= (int) $g['member_id'] ?>"
                      data-name="<?= htmlspecialchars($g['name']) ?>">
                <i class="fa-regular fa-comment" aria-hidden="true"></i> Follow Up
              </button>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  </section>

  <!-- ════════════════════ TAB 4 — EXPENDITURE ANALYSIS ════════════════════ -->
  <section class="tabpanel" id="panel-spending" role="tabpanel" aria-labelledby="tab-spending" hidden>

    <div class="chartgrid chartgrid--2">
      <section class="chartcard">
        <header class="chartcard__head">
          <div>
            <h2>Spending Trend</h2>
            <p>Twelve months of expenditure.</p>
          </div>
          <button class="iconbtn no-print" type="button" data-zoomchart="spendChart" aria-label="Enlarge this chart">
            <i class="fa-solid fa-expand" aria-hidden="true"></i>
          </button>
        </header>
        <div class="chartbox chartbox--tall"><canvas id="spendChart"></canvas></div>
      </section>

      <section class="chartcard">
        <header class="chartcard__head">
          <div>
            <h2>Category Comparison</h2>
            <p>This period against the one before it.</p>
          </div>
          <button class="iconbtn no-print" type="button" data-zoomchart="cmpChart" aria-label="Enlarge this chart">
            <i class="fa-solid fa-expand" aria-hidden="true"></i>
          </button>
        </header>
        <div class="chartbox chartbox--tall"><canvas id="cmpChart"></canvas></div>
      </section>
    </div>

    <?php if (mu_mod('budgets')): ?>
      <section class="chartcard" style="margin-top:16px">
        <header class="chartcard__head">
          <div>
            <h2>Budget vs Actual</h2>
            <p>This period's spending against the annual budget line, pro-rated to <?= $len ?> month<?= $len === 1 ? '' : 's' ?>.</p>
          </div>
          <button class="iconbtn no-print" type="button" data-zoomchart="bvaChart" aria-label="Enlarge this chart">
            <i class="fa-solid fa-expand" aria-hidden="true"></i>
          </button>
        </header>
        <div class="chartbox chartbox--xtall"><canvas id="bvaChart"></canvas></div>
      </section>
    <?php endif; ?>

    <div class="chartgrid chartgrid--2" style="margin-top:16px">
      <section class="panel">
        <header class="chartcard__head">
          <div>
            <h2>Top Vendors and Payees</h2>
            <p>From the expense ledger, paid and approved.</p>
          </div>
        </header>
        <div class="dt-wrap">
          <table class="dt" id="venTable">
            <thead>
              <tr>
                <th style="width:40px">#</th>
                <th>Payee</th>
                <th style="text-align:right">Total Paid</th>
                <th style="text-align:right">Transactions</th>
                <th>Last Paid</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($vendors as $i => $v): ?>
                <tr data-vendor
                    data-category="<?= htmlspecialchars($v['category']) ?>"
                    data-method="<?= htmlspecialchars($v['method']) ?>"
                    data-currency="<?= htmlspecialchars($v['currency']) ?>">
                  <td class="num"><?= $i + 1 ?></td>
                  <td>
                    <b><?= htmlspecialchars($v['payee']) ?></b>
                    <span class="metaline"><?= htmlspecialchars($cat_by_key[$v['category']]['name'] ?? $v['category']) ?></span>
                  </td>
                  <td class="num">
                    <b data-amt
                       data-usd-text="$<?= number_format($v['total'], 2) ?>"
                       data-orig-text="<?= htmlspecialchars(rep_sym($v['currency'])) ?><?= number_format($v['native'], 2) ?>">$<?= number_format($v['total'], 2) ?></b>
                    <?php if ($v['currency'] !== 'USD'): ?>
                      <span class="metaline" data-amt-sub
                            data-usd-text="<?= htmlspecialchars(rep_sym($v['currency'])) ?><?= number_format($v['native'], 2) ?> as paid"
                            data-orig-text="&asymp; $<?= number_format($v['total'], 2) ?>"><?= htmlspecialchars(rep_sym($v['currency'])) ?><?= number_format($v['native'], 2) ?> as paid</span>
                    <?php endif; ?>
                  </td>
                  <td class="num"><?= $v['count'] ?></td>
                  <td class="nowrap"><?= mu_ago((int) $v['last_days']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="dt-empty" data-vendor-empty hidden>
          <i class="fa-regular fa-face-frown" aria-hidden="true"></i>
          No payee matches those filters.
        </p>
      </section>

      <section class="chartcard">
        <header class="chartcard__head">
          <div>
            <h2>Approval Turnaround</h2>
            <p>How long an expense waits for a decision.</p>
          </div>
        </header>
        <div class="bigfig">
          <span class="bigfig__value"><?= number_format($turn['average_days'], 1) ?> <small>days</small></span>
          <span class="bigfig__cap">
            <?= fin_delta((float) $turn['average_days'], (float) $turn['previous_days'], false) ?>
            average, request to decision
          </span>
        </div>
        <div class="chartbox chartbox--short"><canvas id="turnChart"></canvas></div>
      </section>
    </div>
  </section>

  <!-- ═══════════════════════ TAB 5 — STATEMENTS ═══════════════════════ -->
  <section class="tabpanel" id="panel-statements" role="tabpanel" aria-labelledby="tab-statements" hidden>
    <div class="genlayout">

      <section class="panel no-print">
        <header class="chartcard__head">
          <div>
            <h2>Generate a Statement</h2>
            <p>The preview beside this updates as you choose.</p>
          </div>
        </header>

        <div class="field">
          <label id="stTypeLbl">Statement type</label>
          <div class="radio-cards" role="radiogroup" aria-labelledby="stTypeLbl">
            <?php
              $st_types = [['member', 'Member Giving Statement', 'fa-user']];
              if (mu_mod('projects'))    { $st_types[] = ['project', 'Project Statement', 'fa-diagram-project']; }
              if ($branch_aware)         { $st_types[] = ['branch',  t('branch_singular') . ' Statement', 'fa-code-branch']; }
              if (mu_mod('departments')) { $st_types[] = ['department', 'Department Statement', 'fa-people-group']; }
            ?>
            <?php foreach ($st_types as $i => [$k, $lab, $ic]): ?>
              <label class="rcard">
                <input type="radio" name="stType" value="<?= $k ?>" <?= $i === 0 ? 'checked' : '' ?>>
                <span class="rcard__box">
                  <i class="fa-solid <?= $ic ?>" aria-hidden="true"></i>
                  <span><strong><?= htmlspecialchars($lab) ?></strong></span>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- One picker per type; only the matching one is ever shown. -->
        <div class="field">
          <label for="stWho" data-who-label>Member</label>
          <select class="select" id="stWho" data-who="member">
            <?php foreach ($members_demo as $m): ?>
              <option value="<?= (int) $m['id'] ?>"><?= htmlspecialchars($m['name']) ?> &mdash; <?= htmlspecialchars($m['member_no']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (mu_mod('projects')): ?>
            <select class="select" id="stWhoProject" data-who="project" hidden>
              <?php foreach ($projects_demo as $p): ?>
                <option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
          <?php if ($branch_aware): ?>
            <select class="select" id="stWhoBranch" data-who="branch" hidden>
              <?php foreach ($branch_options as $b): ?>
                <option value="<?= (int) $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
          <?php if (mu_mod('departments')): ?>
            <select class="select" id="stWhoDept" data-who="department" hidden>
              <?php foreach (array_keys($giving_demographics['department']) as $d): ?>
                <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </div>

        <div class="field">
          <label>Period</label>
          <div class="daterange">
            <select class="select" id="stFrom" aria-label="From month">
              <?php foreach ($all_labels as $i => $lab): ?>
                <option value="<?= $i ?>" <?= $i === $from ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="daterange__to" aria-hidden="true">&ndash;</span>
            <select class="select" id="stTo" aria-label="To month">
              <?php foreach ($all_labels as $i => $lab): ?>
                <option value="<?= $i ?>" <?= $i === $to ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="field">
          <label id="stFmtLbl">Format</label>
          <div class="radio-cards" role="radiogroup" aria-labelledby="stFmtLbl">
            <?php foreach ([['PDF', 'fa-file-pdf'], ['Excel', 'fa-file-excel'], ['Print', 'fa-print']] as $i => [$f, $ic]): ?>
              <label class="rcard">
                <input type="radio" name="stFormat" value="<?= $f ?>" <?= $i === 0 ? 'checked' : '' ?>>
                <span class="rcard__box"><i class="fa-solid <?= $ic ?>" aria-hidden="true"></i><span><strong><?= $f ?></strong></span></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="field">
          <label>Include</label>
          <label class="switchrow" for="stItems">
            <span class="switch"><input type="checkbox" id="stItems" checked data-st><span class="switch__track" aria-hidden="true"></span></span>
            <span class="switchrow__text"><b>Itemised transactions</b><small>Every contribution listed line by line.</small></span>
          </label>
          <label class="switchrow" for="stSummary">
            <span class="switch"><input type="checkbox" id="stSummary" data-st><span class="switch__track" aria-hidden="true"></span></span>
            <span class="switchrow__text"><b>Summary only</b><small>Totals per type, no individual lines.</small></span>
          </label>
          <label class="switchrow" for="stTax">
            <span class="switch"><input type="checkbox" id="stTax" checked data-st><span class="switch__track" aria-hidden="true"></span></span>
            <span class="switchrow__text"><b>Tax note</b><small>The wording ZIMRA expects on a giving receipt.</small></span>
          </label>
          <label class="switchrow" for="stThanks">
            <span class="switch"><input type="checkbox" id="stThanks" checked data-st><span class="switch__track" aria-hidden="true"></span></span>
            <span class="switchrow__text"><b>Thank-you message</b><small>A short note from the pastor.</small></span>
          </label>
        </div>

        <div class="genacts">
          <button class="btn" type="button" id="stGenerate"><i class="fa-solid fa-gears" aria-hidden="true"></i> Generate</button>
          <button class="btn btn--ghost" type="button" data-toast="Statement downloaded"><i class="fa-solid fa-download" aria-hidden="true"></i> Download</button>
          <?php if (mu_mod('communication')): ?>
            <button class="btn btn--ghost" type="button" id="stEmail"><i class="fa-regular fa-paper-plane" aria-hidden="true"></i> Email</button>
          <?php endif; ?>
          <button class="btn btn--ghost" type="button" id="stBulk"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> Bulk Generate</button>
        </div>
      </section>

      <!-- The preview is the same markup the print stylesheet formats, so what
           is on screen is what comes out of the printer. -->
      <section class="panel genpreview">
        <header class="chartcard__head no-print">
          <div>
            <h2>Preview</h2>
            <p>Exactly as it will print.</p>
          </div>
        </header>

        <div class="paper paper--sm" id="stPaper">
          <header class="paper__head">
            <img class="paper__logo" src="<?= htmlspecialchars($church['logo']) ?>" alt="">
            <div class="paper__org">
              <h2><?= htmlspecialchars($church['name']) ?></h2>
              <p><?= htmlspecialchars($church['code']) ?></p>
            </div>
            <div class="paper__meta"><span>Issued</span><b><?= date('d M Y') ?></b></div>
          </header>

          <div class="paper__title">
            <h3 data-pv-title>Member Giving Statement</h3>
            <p data-pv-sub>&mdash;</p>
          </div>

          <p class="paper__addr" data-pv-who>&mdash;</p>

          <div class="stmtwrap">
          <table class="stmt stmt--sm" data-pv-items>
            <thead>
              <tr><th>Date</th><th>Description</th><th class="stmt__num">Amount (USD)</th></tr>
            </thead>
            <tbody data-pv-rows></tbody>
            <tfoot>
              <tr class="stmt__net">
                <td colspan="2">Total for the period</td>
                <td class="stmt__num" data-pv-total>0.00</td>
              </tr>
            </tfoot>
          </table>
          </div>

          <p class="paper__note" data-pv-tax hidden>
            This statement is issued for tax purposes. <?= htmlspecialchars($church['name']) ?> is a registered
            religious organisation; contributions may be deductible in accordance with the Income Tax Act.
            Retain this document for your records.
          </p>

          <p class="paper__thanks" data-pv-thanks hidden>
            Thank you for your faithful giving. It pays the wages, keeps the lights on and feeds people who
            would otherwise go without. We are grateful for every cent of it.
          </p>

          <footer class="paper__foot">
            <div class="sigline">
              <span class="sigline__rule" aria-hidden="true"></span>
              <b>Issued by</b>
              <span><?= htmlspecialchars($user['name']) ?> &middot; <?= htmlspecialchars($user['role_label']) ?></span>
            </div>
          </footer>
        </div>
      </section>
    </div>
  </section>

<?php endif; ?>
</div>

<?php if ($has_module && $can_report): ?>

<?php if (mu_mod('communication')): ?>
<!-- ══════════════════════════ SCHEDULE REPORT ══════════════════════════ -->
<div class="modal-scrim no-print" id="modalSchedule" hidden>
  <div class="modal modal--wide" role="dialog" aria-modal="true" aria-labelledby="scTitle">
    <header class="modal__head">
      <h2 id="scTitle">Schedule a Report</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>

    <div class="modal__body">
      <div class="form-grid">
        <div class="field">
          <label for="scReport">Report</label>
          <select class="select" id="scReport">
            <option>Summary</option>
            <option>Income Statement</option>
            <option>Giving Analysis</option>
            <option>Expenditure Analysis</option>
          </select>
        </div>

        <div class="field">
          <label for="scFormat">Format</label>
          <select class="select" id="scFormat">
            <option>PDF</option><option>Excel</option><option>CSV</option>
          </select>
        </div>

        <div class="field field--wide">
          <label id="scFreqLbl">Frequency</label>
          <div class="radio-cards" role="radiogroup" aria-labelledby="scFreqLbl">
            <?php foreach ([['Weekly', 'fa-calendar-week'], ['Monthly', 'fa-calendar-days'],
                            ['Quarterly', 'fa-calendar'], ['Annually', 'fa-calendar-check']] as $i => [$f, $ic]): ?>
              <label class="rcard">
                <input type="radio" name="scFreq" value="<?= $f ?>" <?= $i === 1 ? 'checked' : '' ?>>
                <span class="rcard__box"><i class="fa-solid <?= $ic ?>" aria-hidden="true"></i><span><strong><?= $f ?></strong></span></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="field">
          <label for="scDay">Day</label>
          <select class="select" id="scDay">
            <option value="1">1st of the month</option>
            <option value="last">Last day of the month</option>
            <option value="mon">Every Monday</option>
            <option value="sun">Every Sunday</option>
          </select>
        </div>

        <div class="field">
          <label for="scTime">Time</label>
          <input class="input" type="time" id="scTime" value="07:00">
        </div>

        <div class="field field--wide">
          <label for="scTo">Recipients</label>
          <select class="select" id="scTo" multiple size="5">
            <?php foreach ($demo_roles as $k => $r): ?>
              <?php if (!in_array('finance.reports', $r['perms'], true)) { continue; } ?>
              <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($r['user']['name']) ?> &mdash; <?= htmlspecialchars($r['user']['role_label']) ?></option>
            <?php endforeach; ?>
          </select>
          <p class="hint">Only people who already hold the financial reporting permission can be sent one.</p>
        </div>

        <div class="field field--wide">
          <label for="scMessage">Message</label>
          <textarea class="textarea" id="scMessage" rows="3">Please find attached the monthly finance report for tabling at the next board meeting.</textarea>
        </div>
      </div>
    </div>

    <footer class="modal__foot">
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <button class="btn" type="button" id="scGo"><i class="fa-regular fa-clock" aria-hidden="true"></i> Schedule Report</button>
    </footer>
  </div>
</div>
<?php endif; ?>


<!-- ══════════════════════════ EXPORT OPTIONS ══════════════════════════ -->
<div class="modal-scrim no-print" id="modalExport" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="exTitle">
    <header class="modal__head">
      <h2 id="exTitle">Export Options</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>

    <div class="modal__body">
      <div class="field">
        <label id="exFmtLbl">Format</label>
        <div class="radio-cards" role="radiogroup" aria-labelledby="exFmtLbl">
          <?php foreach ([['PDF', 'fa-file-pdf'], ['Excel', 'fa-file-excel'], ['CSV', 'fa-file-csv']] as $i => [$f, $ic]): ?>
            <label class="rcard">
              <input type="radio" name="exFormat" value="<?= $f ?>" <?= $i === 0 ? 'checked' : '' ?>>
              <span class="rcard__box"><i class="fa-solid <?= $ic ?>" aria-hidden="true"></i><span><strong><?= $f ?></strong></span></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="field">
        <label>Sections</label>
        <div class="chipset">
          <?php foreach (['Summary', 'Income Statement', 'Giving Analysis', 'Expenditure Analysis'] as $i => $sec): ?>
            <label class="chipbox">
              <input type="checkbox" data-ex-section value="<?= htmlspecialchars($sec) ?>" <?= $i < 2 ? 'checked' : '' ?>>
              <span><?= htmlspecialchars($sec) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="field">
        <label>Period</label>
        <div class="daterange">
          <select class="select" id="exFrom" aria-label="From month">
            <?php foreach ($all_labels as $i => $lab): ?>
              <option value="<?= $i ?>" <?= $i === $from ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
            <?php endforeach; ?>
          </select>
          <span class="daterange__to" aria-hidden="true">&ndash;</span>
          <select class="select" id="exTo" aria-label="To month">
            <?php foreach ($all_labels as $i => $lab): ?>
              <option value="<?= $i ?>" <?= $i === $to ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <label class="switchrow" for="exCharts">
        <span class="switch"><input type="checkbox" id="exCharts" checked><span class="switch__track" aria-hidden="true"></span></span>
        <span class="switchrow__text"><b>Include charts</b><small>Rendered as images inside the document.</small></span>
      </label>
      <label class="switchrow" for="exDetail">
        <span class="switch"><input type="checkbox" id="exDetail"><span class="switch__track" aria-hidden="true"></span></span>
        <span class="switchrow__text"><b>Include transaction detail</b><small>Every underlying line. Makes for a long file.</small></span>
      </label>
    </div>

    <footer class="modal__foot">
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <button class="btn" type="button" id="exGo"><i class="fa-solid fa-file-export" aria-hidden="true"></i> Export</button>
    </footer>
  </div>
</div>


<!-- ══════════════════════════ CHART DETAIL ══════════════════════════ -->
<div class="modal-scrim no-print" id="modalChart" hidden>
  <div class="modal modal--full" role="dialog" aria-modal="true" aria-labelledby="cdTitle">
    <header class="modal__head">
      <h2 id="cdTitle">Chart</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>

    <div class="modal__body">
      <div class="chartbox chartbox--zoom"><canvas id="zoomChart"></canvas></div>
      <p class="minilist__head">The figures behind it</p>
      <div class="dt-wrap">
        <table class="dt" id="zoomTable">
          <thead><tr data-zoom-head></tr></thead>
          <tbody data-zoom-body></tbody>
        </table>
      </div>
    </div>

    <footer class="modal__foot">
      <button class="btn btn--ghost" type="button" data-close>Close</button>
      <button class="btn" type="button" data-toast="Chart data downloaded"><i class="fa-solid fa-download" aria-hidden="true"></i> Download data</button>
    </footer>
  </div>
</div>

<?php endif; ?>

<div class="toasts no-print" data-toasts aria-live="polite" aria-atomic="false"></div>

<!-- ══════════════ DEMO ONLY — REMOVE BEFORE PRODUCTION ══════════════ -->
<details class="demo no-print" aria-label="Demo role switcher">
  <summary class="demo__summary">
    <i class="fa-solid fa-user-gear" aria-hidden="true"></i>
    <span class="demo__summary-role"><?= htmlspecialchars($demo_roles[$demo_role]['user']['role_label']) ?></span>
    <i class="fa-solid fa-chevron-up demo__summary-chev" aria-hidden="true"></i>
  </summary>
  <p class="demo__warn"><i class="fa-solid fa-flask" aria-hidden="true"></i> DEMO ONLY — remove before production</p>
  <p class="demo__hint">Switch role to see this page filter itself</p>
  <ul class="demo__list">
    <?php foreach ($demo_roles as $key => $r): ?>
      <li><a class="demo__role<?= $key === $demo_role ? ' is-on' : '' ?>" href="?range=<?= urlencode($range) ?>&amp;role=<?= urlencode($key) ?>"
             <?= $key === $demo_role ? 'aria-current="true"' : '' ?>>
        <span class="demo__av" aria-hidden="true"><?= htmlspecialchars($r['user']['initials']) ?></span>
        <?= htmlspecialchars($r['user']['role_label']) ?>
      </a></li>
    <?php endforeach; ?>
  </ul>
</details>
<!-- ═══════════════════════════ END DEMO ═══════════════════════════ -->

<?php if ($has_module && $can_report): ?>
<?php
/* The annual budget line per category, so the Budget vs Actual chart can
   pro-rate it to whatever window is in view. Read straight from the active
   budget the budgets page owns, so the two never disagree. */
$expense_budget_annual = [];
foreach (($budget_periods[0]['expense'] ?? []) as $__l) {
    $expense_budget_annual[$__l['category']] = (float) $__l['budget'];
}
unset($__l);
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function () {
  'use strict';

  var $  = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

  var LABELS   = <?= json_encode($labels) ?>;
  var TLABELS  = <?= json_encode($trend_labels) ?>;
  var INC      = <?= json_encode(array_map('floatval', $inc_series)) ?>;
  var EXP      = <?= json_encode(array_map('floatval', $exp_series)) ?>;
  var CASH     = <?= json_encode($cash_series) ?>;
  var TREND    = <?= json_encode(array_map('floatval', $trend_series)) ?>;
  var TREND_AV = <?= json_encode($trend_avg) ?>;
  var SPEND    = <?= json_encode(array_map('floatval', $spend_series)) ?>;
  var STACK    = <?= json_encode($stack, JSON_UNESCAPED_UNICODE) ?>;
  var INC_LINES= <?= json_encode(array_map(static fn($l) => [
        'key' => $l['key'], 'name' => $l['name'], 'colour' => $l['colour'],
        'amount' => round($l['amount'], 2), 'prev' => round($l['prev'], 2), 'share' => $l['share'],
      ], $income_lines), JSON_UNESCAPED_UNICODE) ?>;
  var EXP_LINES= <?= json_encode(array_map(static fn($l) => [
        'key' => $l['key'], 'name' => $l['name'], 'colour' => $l['colour'],
        'amount' => round($l['amount'], 2), 'prev' => round($l['prev'], 2), 'share' => $l['share'],
        'budget' => round((($expense_budget_annual[$l['key']] ?? 0) / 12) * $len, 2),
      ], $expense_lines), JSON_UNESCAPED_UNICODE) ?>;
  var DEMOG    = <?= json_encode($giving_demographics, JSON_UNESCAPED_UNICODE) ?>;
  var TURN     = <?= json_encode($turn['buckets']) ?>;
  var PER_MEM  = <?= json_encode(array_map(static fn($v) => round($v / max(1, $givers_count), 2), $trend_series)) ?>;
  var MONTHS   = <?= json_encode($all_labels) ?>;
  var MEMBERS  = <?= json_encode(array_map(static fn($m) => ['id' => $m['id'], 'name' => $m['name'], 'no' => $m['member_no']], $members_demo), JSON_UNESCAPED_UNICODE) ?>;
  var SERIES   = { income: <?= json_encode(array_map('floatval', $report_monthly['income'])) ?> };

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

  /* ─────────────────── the range selector reloads the page ─────────────────── */
  var rangePick = $('[data-range]');
  if (rangePick) {
    rangePick.addEventListener('change', function () {
      if (rangePick.value === 'custom') {
        $('#customRange').hidden = false;
        $('#customRange').scrollIntoView({ block: 'nearest' });
        return;
      }
      var url = new URL(window.location.href);
      url.searchParams.set('range', rangePick.value);
      url.searchParams.delete('from');
      url.searchParams.delete('to');
      window.location.href = url.toString();
    });
  }
  var btnCustom = $('#btnCustom');
  if (btnCustom) {
    btnCustom.addEventListener('click', function () {
      var box = $('#customRange');
      box.hidden = !box.hidden;
      btnCustom.setAttribute('aria-pressed', String(!box.hidden));
      if (!box.hidden) { box.scrollIntoView({ block: 'nearest' }); }
    });
  }

  /* ─────────────────────────── the tabs ─────────────────────────── */
  var TABS = ['summary', 'statement', 'giving', 'spending', 'statements'];
  var tabs = $$('[data-tab]');
  function setTab(name) {
    tabs.forEach(function (t) {
      var on = t.getAttribute('data-tab') === name;
      t.setAttribute('aria-selected', String(on));
      t.tabIndex = on ? 0 : -1;
      t.classList.toggle('is-on', on);
    });
    TABS.forEach(function (n) {
      var panel = $('#panel-' + n);
      if (panel) { panel.hidden = n !== name; }
    });
    /* The print header names whichever report is on screen. */
    var titles = { summary: 'Financial Summary', statement: 'Statement of Income and Expenditure',
                   giving: 'Giving Analysis', spending: 'Expenditure Analysis', statements: 'Statement' };
    /* Two of these now: the running head and the running foot. */
    $$('[data-print-title]').forEach(function (el) { el.textContent = titles[name]; });
    document.body.setAttribute('data-printing', name);
    draw(name);
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

  /* ═══════════════════════ the currency toggle ═══════════════════════ */
  /* Two readings of the same ledger figures: converted to USD, or as they were
     paid. Nothing is recalculated — only which figure leads. */
  var curMode = 'usd';
  $$('[data-cur-mode]').forEach(function (b) {
    b.addEventListener('click', function () {
      curMode = b.getAttribute('data-cur-mode');
      $$('[data-cur-mode]').forEach(function (o) {
        var on = o === b;
        o.classList.toggle('is-on', on);
        o.setAttribute('aria-pressed', String(on));
      });
      $$('[data-amt], [data-amt-sub]').forEach(function (el) {
        el.textContent = el.getAttribute(curMode === 'usd' ? 'data-usd-text' : 'data-orig-text');
      });
      toast(curMode === 'usd' ? 'Showing every figure in USD' : 'Showing amounts as they were paid', 'info');
    });
  });

  /* ═══════════════════════════ filtering ═══════════════════════════ */
  var chipBox = $('[data-filter-chips]');
  var FILTER_LABEL = {
    fType: 'Contribution type', fCategory: 'Expense category',
    fMethod: 'Payment method', fCurrency: 'Currency'<?php if ($show_branch): ?>,
    fBranch: '<?= addslashes(t('branch_singular')) ?>'<?php endif; ?>
  };

  function activeFilters() {
    var f = {};
    $$('[data-filter]').forEach(function (el) { f[el.id] = el.value; });
    return f;
  }

  function paintChips(f) {
    if (!chipBox) { return; }
    chipBox.innerHTML = '';
    var live = [];
    $$('[data-filter]').forEach(function (el) {
      if (!el.value) { return; }
      live.push([el.id, FILTER_LABEL[el.id] || el.id, el.options[el.selectedIndex].text]);
    });
    live.forEach(function (row) {
      var chip = document.createElement('span');
      chip.className = 'fchip';
      chip.innerHTML = '<span></span><button type="button" aria-label="Remove this filter">'
                     + '<i class="fa-solid fa-xmark" aria-hidden="true"></i></button>';
      $('span', chip).textContent = row[1] + ': ' + row[2];
      $('button', chip).addEventListener('click', function () { $('#' + row[0]).value = ''; apply(); });
      chipBox.appendChild(chip);
    });
    chipBox.hidden = live.length === 0;
    var n = $('[data-filter-n]');
    if (n) { n.textContent = live.length; n.hidden = live.length === 0; }
  }

  /* The filters are not decoration: narrowing to one type or category rebuilds
     the doughnuts, hides the statement rows that no longer apply, and cuts the
     payee table down to what matches. */
  function apply() {
    var f = activeFilters();

    var inc = f.fType     ? INC_LINES.filter(function (l) { return l.key === f.fType; })     : INC_LINES;
    var exp = f.fCategory ? EXP_LINES.filter(function (l) { return l.key === f.fCategory; }) : EXP_LINES;

    if (charts.inc) { setDoughnut(charts.inc, inc); }
    if (charts.exp) { setDoughnut(charts.exp, exp); }
    if (charts.cmp) {
      charts.cmp.data.labels = exp.map(function (l) { return l.name; });
      charts.cmp.data.datasets[0].data = exp.map(function (l) { return l.amount; });
      charts.cmp.data.datasets[1].data = exp.map(function (l) { return l.prev; });
      charts.cmp.update();
    }
    if (charts.bva) {
      charts.bva.data.labels = exp.map(function (l) { return l.name; });
      charts.bva.data.datasets[0].data = exp.map(function (l) { return l.amount; });
      charts.bva.data.datasets[1].data = exp.map(function (l) { return l.budget; });
      charts.bva.update();
    }

    $$('[data-stmt-row]').forEach(function (r) {
      var kind = r.getAttribute('data-kind'), key = r.getAttribute('data-key');
      r.hidden = (kind === 'income'  && f.fType     && key !== f.fType)
              || (kind === 'expense' && f.fCategory && key !== f.fCategory);
    });
    /* The statement subtotals have to follow, or the document lies. */
    var incShown = inc.reduce(function (t, l) { return t + l.amount; }, 0);
    var incPrev  = inc.reduce(function (t, l) { return t + l.prev;   }, 0);
    var expShown = exp.reduce(function (t, l) { return t + l.amount; }, 0);
    var expPrev  = exp.reduce(function (t, l) { return t + l.prev;   }, 0);
    var set = function (k, v) { var el = $('[data-stmt-total="' + k + '"]'); if (el) { el.textContent = fmt(v); } };
    set('income', incShown); set('income-prev', incPrev);
    set('expense', expShown); set('expense-prev', expPrev);

    var shown = 0;
    $$('[data-vendor]').forEach(function (r) {
      var ok = (!f.fCategory || r.getAttribute('data-category') === f.fCategory)
            && (!f.fMethod   || r.getAttribute('data-method')   === f.fMethod)
            && (!f.fCurrency || r.getAttribute('data-currency') === f.fCurrency);
      r.hidden = !ok;
      if (ok) { shown++; }
    });
    var ve = $('[data-vendor-empty]');
    if (ve) { ve.hidden = shown !== 0; }

    paintChips(f);
  }

  $$('[data-filter]').forEach(function (el) { el.addEventListener('change', apply); });
  $$('[data-reset-filters]').forEach(function (b) {
    b.addEventListener('click', function () {
      $$('[data-filter]').forEach(function (el) { el.value = ''; });
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

  /* ═════════════════════════════ the charts ═════════════════════════════ */
  var PALETTE = ['#662F97', '#B48FDA', '#8F5CC2', '#D3BAEA', '#56287F'];
  var GRID    = '#ECE7F3';
  if (window.Chart) {
    Chart.defaults.font.family = "'Plus Jakarta Sans', system-ui, sans-serif";
    Chart.defaults.font.size = 11;
    Chart.defaults.color = '#6B6480';
    Chart.defaults.animation = reduced ? false : { duration: 700 };
  }

  var legendBottom = { display: true, position: 'bottom', labels: { boxWidth: 10, padding: 10, usePointStyle: true } };
  function moneyAxis(v) { return '$' + (Math.abs(v) >= 1000 ? (v / 1000) + 'k' : v); }
  function moneyTip(c) { return c.dataset.label + ': ' + (c.parsed.y === null ? 'not yet' : usd(c.parsed.y)); }

  var charts = {}, drawn = {};

  function setDoughnut(chart, lines) {
    chart.data.labels = lines.map(function (l) { return l.name; });
    chart.data.datasets[0].data = lines.map(function (l) { return l.amount; });
    chart.data.datasets[0].backgroundColor = lines.map(function (l) { return l.colour; });
    chart.update();
  }
  function doughnutOpts(lines) {
    return {
      responsive: true, maintainAspectRatio: false, cutout: '58%',
      plugins: {
        legend: legendBottom,
        tooltip: { callbacks: { label: function (c) {
          var total = c.dataset.data.reduce(function (a, b) { return a + b; }, 0);
          var pct = total ? ((c.parsed / total) * 100).toFixed(1) : '0.0';
          return c.label + ': ' + usd(c.parsed) + ' (' + pct + '%)';
        } } }
      }
    };
  }

  function draw(tab) {
    if (!window.Chart || drawn[tab]) { return; }
    drawn[tab] = true;

    if (tab === 'summary') {
      charts.ie = new Chart($('#ieChart'), {
        data: {
          labels: LABELS,
          datasets: [
            { type: 'bar',  label: 'Income',       data: INC, backgroundColor: '#662F97', borderRadius: 3, order: 2 },
            { type: 'bar',  label: 'Expenditure',  data: EXP, backgroundColor: '#B45309', borderRadius: 3, order: 2 },
            { type: 'line', label: 'Net position', data: INC.map(function (v, i) { return v - EXP[i]; }),
              borderColor: '#0F766E', backgroundColor: 'transparent', borderWidth: 2.5,
              tension: .3, pointRadius: 0, pointHoverRadius: 4, order: 1 }
          ]
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          interaction: { mode: 'index', intersect: false },
          plugins: { legend: legendBottom, tooltip: { callbacks: { label: moneyTip } } },
          scales: { x: { grid: { display: false } }, y: { grid: { color: GRID }, ticks: { callback: moneyAxis } } }
        }
      });

      charts.inc = new Chart($('#incChart'), {
        type: 'doughnut',
        data: { labels: INC_LINES.map(function (l) { return l.name; }),
                datasets: [{ data: INC_LINES.map(function (l) { return l.amount; }),
                             backgroundColor: INC_LINES.map(function (l) { return l.colour; }), borderWidth: 0 }] },
        options: doughnutOpts(INC_LINES)
      });

      charts.exp = new Chart($('#expChart'), {
        type: 'doughnut',
        data: { labels: EXP_LINES.map(function (l) { return l.name; }),
                datasets: [{ data: EXP_LINES.map(function (l) { return l.amount; }),
                             backgroundColor: EXP_LINES.map(function (l) { return l.colour; }), borderWidth: 0 }] },
        options: doughnutOpts(EXP_LINES)
      });

      charts.cash = new Chart($('#cashChart'), {
        type: 'line',
        data: { labels: LABELS, datasets: [{ label: 'Running balance', data: CASH,
                borderColor: '#662F97', backgroundColor: 'rgba(102,47,151,.10)',
                borderWidth: 2.5, tension: .3, fill: true, pointRadius: 0, pointHoverRadius: 4 }] },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { legend: { display: false }, tooltip: { callbacks: { label: moneyTip } } },
          scales: { x: { grid: { display: false } }, y: { grid: { color: GRID }, ticks: { callback: moneyAxis } } }
        }
      });

      charts.pm = new Chart($('#pmChart'), {
        type: 'line',
        data: { labels: TLABELS, datasets: [{ label: 'Per giver', data: PER_MEM,
                borderColor: '#8F5CC2', backgroundColor: 'rgba(143,92,194,.12)',
                borderWidth: 2, tension: .35, fill: true, pointRadius: 0 }] },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { legend: { display: false }, tooltip: { callbacks: { label: moneyTip } } },
          scales: { x: { display: false }, y: { display: false } }
        }
      });
      apply();
    }

    if (tab === 'giving') {
      charts.give = new Chart($('#giveChart'), {
        type: 'line',
        data: {
          labels: TLABELS,
          datasets: [
            { label: 'Given', data: TREND, borderColor: '#662F97', backgroundColor: 'rgba(102,47,151,.10)',
              borderWidth: 2.5, tension: .3, fill: true, pointRadius: 0, pointHoverRadius: 4 },
            { label: '3-month average', data: TREND_AV, borderColor: '#B45309', backgroundColor: 'transparent',
              borderWidth: 2, borderDash: [5, 4], tension: .3, pointRadius: 0 }
          ]
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          interaction: { mode: 'index', intersect: false },
          plugins: { legend: legendBottom, tooltip: { callbacks: { label: moneyTip } } },
          scales: { x: { grid: { display: false } }, y: { grid: { color: GRID }, ticks: { callback: moneyAxis } } }
        }
      });

      charts.stack = new Chart($('#stackChart'), {
        type: 'line',
        data: {
          labels: TLABELS,
          datasets: STACK.map(function (s) {
            return { label: s.name, data: s.data, borderColor: s.colour, backgroundColor: s.colour + '55',
                     borderWidth: 1.5, tension: .3, fill: true, pointRadius: 0 };
          })
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          interaction: { mode: 'index', intersect: false },
          plugins: { legend: legendBottom, tooltip: { callbacks: { label: moneyTip } } },
          scales: {
            x: { grid: { display: false } },
            y: { stacked: true, grid: { color: GRID }, ticks: { callback: moneyAxis } }
          }
        }
      });

      charts.demo = new Chart($('#demoChart'), demoConfig('age'));
    }

    if (tab === 'spending') {
      charts.spend = new Chart($('#spendChart'), {
        type: 'line',
        data: { labels: TLABELS, datasets: [{ label: 'Spent', data: SPEND,
                borderColor: '#B45309', backgroundColor: 'rgba(180,83,9,.10)',
                borderWidth: 2.5, tension: .3, fill: true, pointRadius: 0, pointHoverRadius: 4 }] },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { legend: { display: false }, tooltip: { callbacks: { label: moneyTip } } },
          scales: { x: { grid: { display: false } }, y: { grid: { color: GRID }, ticks: { callback: moneyAxis } } }
        }
      });

      charts.cmp = new Chart($('#cmpChart'), {
        type: 'bar',
        data: {
          labels: EXP_LINES.map(function (l) { return l.name; }),
          datasets: [
            { label: 'This period', data: EXP_LINES.map(function (l) { return l.amount; }), backgroundColor: '#662F97', borderRadius: 3 },
            { label: 'Previous',    data: EXP_LINES.map(function (l) { return l.prev;   }), backgroundColor: '#D3BAEA', borderRadius: 3 }
          ]
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { legend: legendBottom, tooltip: { callbacks: { label: moneyTip } } },
          scales: {
            x: { grid: { display: false }, ticks: { maxRotation: 45, minRotation: 0 } },
            y: { grid: { color: GRID }, ticks: { callback: moneyAxis } }
          }
        }
      });

      if ($('#bvaChart')) {
        charts.bva = new Chart($('#bvaChart'), {
          type: 'bar',
          data: {
            labels: EXP_LINES.map(function (l) { return l.name; }),
            datasets: [
              { label: 'Actual',   data: EXP_LINES.map(function (l) { return l.amount; }), backgroundColor: '#662F97', borderRadius: 3 },
              { label: 'Budgeted', data: EXP_LINES.map(function (l) { return l.budget; }), backgroundColor: '#D3BAEA', borderRadius: 3 }
            ]
          },
          options: {
            indexAxis: 'y', responsive: true, maintainAspectRatio: false,
            plugins: { legend: legendBottom, tooltip: { callbacks: { label: function (c) {
              return c.dataset.label + ': ' + usd(c.parsed.x);
            } } } },
            scales: { x: { grid: { color: GRID }, ticks: { callback: moneyAxis } }, y: { grid: { display: false } } }
          }
        });
      }

      charts.turn = new Chart($('#turnChart'), {
        type: 'bar',
        data: { labels: Object.keys(TURN),
                datasets: [{ label: 'Expenses', data: Object.keys(TURN).map(function (k) { return TURN[k]; }),
                             backgroundColor: PALETTE, borderRadius: 3 }] },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { legend: { display: false },
                     tooltip: { callbacks: { label: function (c) { return c.parsed.y + ' expenses'; } } } },
          scales: { x: { grid: { display: false } }, y: { grid: { color: GRID }, beginAtZero: true } }
        }
      });
      apply();
    }
  }

  function demoConfig(cut) {
    var d = DEMOG[cut] || {};
    var keys = Object.keys(d);
    return {
      type: 'bar',
      data: {
        labels: keys,
        datasets: [
          { label: 'Total given', data: keys.map(function (k) { return d[k].total; }),
            backgroundColor: '#662F97', borderRadius: 3, yAxisID: 'y' },
          { label: 'Givers', data: keys.map(function (k) { return d[k].members; }),
            backgroundColor: '#B48FDA', borderRadius: 3, yAxisID: 'y2' }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: legendBottom,
          tooltip: { callbacks: { label: function (c) {
            return c.datasetIndex === 0 ? 'Total given: ' + usd(c.parsed.y) : 'Givers: ' + c.parsed.y;
          } } }
        },
        scales: {
          x: { grid: { display: false }, ticks: { maxRotation: 40, minRotation: 0 } },
          y:  { position: 'left',  grid: { color: GRID }, ticks: { callback: moneyAxis } },
          y2: { position: 'right', grid: { display: false }, beginAtZero: true }
        }
      }
    };
  }
  $$('[data-demo-cut]').forEach(function (b) {
    b.addEventListener('click', function () {
      $$('[data-demo-cut]').forEach(function (o) {
        var on = o === b;
        o.classList.toggle('is-on', on);
        o.setAttribute('aria-pressed', String(on));
      });
      if (!charts.demo) { return; }
      var cfg = demoConfig(b.getAttribute('data-demo-cut'));
      charts.demo.data = cfg.data;
      charts.demo.options = cfg.options;
      charts.demo.update();
    });
  });

  /* ═════════════════════════════ modals ═════════════════════════════ */
  function openModal(m) { m.hidden = false; document.body.style.overflow = 'hidden'; var c = $('[data-close]', m); if (c) { c.focus(); } }
  function closeModal(m) {
    m.hidden = true;
    if ($$('.modal-scrim').every(function (x) { return x.hidden; })) { document.body.style.overflow = ''; }
  }
  document.addEventListener('click', function (e) {
    var cl = e.target.closest('[data-close]');
    if (cl) { closeModal(cl.closest('.modal-scrim')); return; }
    if (e.target.classList.contains('modal-scrim')) { closeModal(e.target); }
  });

  /* ── export options ── */
  var exModal = $('#modalExport');
  if (exModal) {
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-export]');
      if (!b) { return; }
      closeOwnMenu(b);
      var want = b.getAttribute('data-export');
      var r = $('input[name="exFormat"][value="' + want + '"]');
      if (r) { r.checked = true; }
      openModal(exModal);
    }, true);

    $('#exGo').addEventListener('click', function () {
      var secs = $$('[data-ex-section]').filter(function (c) { return c.checked; });
      if (!secs.length) { toast('Choose at least one section to export', 'error'); return; }
      var f = ($('input[name="exFormat"]:checked') || {}).value || 'PDF';
      closeModal(exModal);
      toast(f + ' export started — ' + secs.length + ' section' + (secs.length === 1 ? '' : 's'), 'success');
    });
  }

  /* ── schedule ── */
  var scModal = $('#modalSchedule');
  if (scModal) {
    var sb = $('#btnSchedule');
    if (sb) { sb.addEventListener('click', function () { openModal(scModal); }); }
    $('#scGo').addEventListener('click', function () {
      var to = $$('#scTo option').filter(function (o) { return o.selected; });
      if (!to.length) { toast('Choose at least one recipient', 'error'); $('#scTo').focus(); return; }
      var freq = ($('input[name="scFreq"]:checked') || {}).value || 'Monthly';
      closeModal(scModal);
      toast(freq + ' report scheduled for ' + to.length + ' recipient' + (to.length === 1 ? '' : 's'), 'success');
    });
  }

  /* ── any chart, enlarged, with its figures underneath ── */
  var cdModal = $('#modalChart');
  var zoom = null;
  if (cdModal) {
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-zoomchart]');
      if (!b) { return; }
      var id = b.getAttribute('data-zoomchart');
      var src = null;
      Object.keys(charts).forEach(function (k) { if (charts[k] && charts[k].canvas.id === id) { src = charts[k]; } });
      if (!src) { return; }

      var card = b.closest('.chartcard');
      $('#cdTitle').textContent = $('h2', card).textContent;

      /* Rebuilt from scratch rather than handed the source options: Chart.js
         resolves those into proxies that a second chart cannot consume. */
      if (zoom) { zoom.destroy(); }
      zoom = new Chart($('#zoomChart'), {
        type: src.config.type,
        data: JSON.parse(JSON.stringify({ labels: src.data.labels, datasets: src.data.datasets })),
        options: {
          responsive: true, maintainAspectRatio: false,
          indexAxis: src.options.indexAxis === 'y' ? 'y' : 'x',
          plugins: { legend: legendBottom },
          scales: src.config.type === 'doughnut' ? {} : {
            x: { grid: { display: false } },
            y: { grid: { color: GRID } }
          }
        }
      });

      var head = $('[data-zoom-head]');
      var body = $('[data-zoom-body]');
      head.innerHTML = '<th>Period</th>' + src.data.datasets.map(function (d) {
        return '<th style="text-align:right">' + esc(d.label || 'Value') + '</th>';
      }).join('');
      body.innerHTML = src.data.labels.map(function (lab, i) {
        return '<tr><td>' + esc(lab) + '</td>' + src.data.datasets.map(function (d) {
          var v = d.data[i];
          return '<td class="num">' + (v === null || v === undefined ? '—' : usd(v)) + '</td>';
        }).join('') + '</tr>';
      }).join('');

      openModal(cdModal);
    }, true);
  }

  /* ── following up a lapsed giver ── */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-followup]');
    if (b) { toast('Message composer opened for ' + b.getAttribute('data-name'), 'info'); }
  }, true);

  /* ═════════════════════ the statement generator ═════════════════════ */
  /* The preview is built from the same series the rest of the page uses, so a
     statement can never show a figure the reports disagree with. */
  var pv = $('#stPaper');
  if (pv) {
    function pickerFor(kind) {
      $$('[data-who]').forEach(function (s) { s.hidden = s.getAttribute('data-who') !== kind; });
      var labels = { member: 'Member', project: 'Project', department: 'Department'<?= $branch_aware ? ', branch: ' . json_encode(t('branch_singular')) : '' ?> };
      $('[data-who-label]').textContent = labels[kind] || 'Recipient';
    }
    function chosen() {
      var kind = ($('input[name="stType"]:checked') || {}).value || 'member';
      var sel  = $('[data-who="' + kind + '"]');
      return { kind: kind, text: sel && !sel.hidden ? sel.options[sel.selectedIndex].text : '' };
    }

    function paint() {
      var c = chosen();
      var a = parseInt($('#stFrom').value, 10);
      var b = parseInt($('#stTo').value, 10);
      if (b < a) { b = a; $('#stTo').value = String(a); }

      var titles = { member: 'Member Giving Statement', project: 'Project Statement',
                     department: 'Department Statement'<?= $branch_aware ? ",\n                     branch: " . json_encode(t('branch_singular') . ' Statement') : '' ?> };
      $('[data-pv-title]').textContent = titles[c.kind];
      $('[data-pv-sub]').textContent = 'For the period ' + MONTHS[a] + ' to ' + MONTHS[b];
      $('[data-pv-who]').textContent = c.text || '—';

      /* One row per month of the chosen window. A member's share of the
         church's giving stands in for their own ledger in this demo. */
      var summaryOnly = $('#stSummary').checked;
      var itemised    = $('#stItems').checked && !summaryOnly;
      var rows = [], total = 0;
      for (var i = a; i <= b; i++) {
        var v = Math.round((SERIES.income[i] / Math.max(1, <?= max(1, $givers_count) ?>)) * 100) / 100;
        total += v;
        rows.push([MONTHS[i], c.kind === 'member' ? 'Contributions received' : 'Receipts for the month', v]);
      }

      var tbody = $('[data-pv-rows]');
      tbody.innerHTML = '';
      if (itemised) {
        rows.forEach(function (r) {
          var tr = document.createElement('tr');
          tr.innerHTML = '<td>' + esc(r[0]) + '</td><td>' + esc(r[1]) + '</td>'
                       + '<td class="stmt__num">' + fmt(r[2]) + '</td>';
          tbody.appendChild(tr);
        });
      } else {
        var tr = document.createElement('tr');
        tr.innerHTML = '<td>' + esc(MONTHS[a]) + ' – ' + esc(MONTHS[b]) + '</td>'
                     + '<td>Summary of all contributions</td>'
                     + '<td class="stmt__num">' + fmt(total) + '</td>';
        tbody.appendChild(tr);
      }
      $('[data-pv-total]').textContent = fmt(total);
      $('[data-pv-tax]').hidden    = !$('#stTax').checked;
      $('[data-pv-thanks]').hidden = !$('#stThanks').checked;
    }

    $$('input[name="stType"]').forEach(function (r) {
      r.addEventListener('change', function () { pickerFor(r.value); paint(); });
    });
    ['#stWho', '#stWhoProject', <?= $branch_aware ? "'#stWhoBranch', " : '' ?>'#stWhoDept', '#stFrom', '#stTo'].forEach(function (sel) {
      var el = $(sel);
      if (el) { el.addEventListener('change', paint); }
    });
    /* Summary and itemised are two views of the same thing, never both. */
    $('#stSummary').addEventListener('change', function () {
      if ($('#stSummary').checked) { $('#stItems').checked = false; }
      paint();
    });
    $('#stItems').addEventListener('change', function () {
      if ($('#stItems').checked) { $('#stSummary').checked = false; }
      paint();
    });
    $$('[data-st]').forEach(function (el) { el.addEventListener('change', paint); });

    $('#stGenerate').addEventListener('click', function () {
      paint();
      toast('Statement generated for ' + (chosen().text || 'the selection'), 'success');
    });
    var em = $('#stEmail');
    if (em) { em.addEventListener('click', function () { toast('Statement emailed to ' + (chosen().text || 'the recipient'), 'success'); }); }
    $('#stBulk').addEventListener('click', function () {
      toast('Generating ' + MEMBERS.length + ' member statements', 'info');
    });

    pickerFor('member');
    paint();
  }

  /* ── printing ── */
  $('#btnPrint').addEventListener('click', function () { window.print(); });

  /* ────────────────────────── escape key ────────────────────────── */
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') { return; }
    var open = $$('.modal-scrim').filter(function (m) { return !m.hidden; });
    if (open.length) { open.forEach(function (m) { m.hidden = true; }); document.body.style.overflow = ''; }
  });

  document.body.setAttribute('data-printing', 'summary');
  draw('summary');
})();
</script>
<?php endif; ?>
<?php require __DIR__ . '/../components/footer.php'; ?>

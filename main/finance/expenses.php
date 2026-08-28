<?php
/**
 * Mutendi CMS — Expenses.
 *
 * Money going out, and who authorised it. In most churches the person who
 * spends is not the person who signs it off, so every row carries a workflow
 * state — draft · pending · approved · rejected · paid — as well as a figure.
 *
 * Three tabs:
 *   All Expenses      the ledger, with a totals row that follows the filters
 *   Pending Approval  a queue for whoever holds finance.approve
 *   By Category       where the money actually goes
 *
 * Multi-currency throughout, matching finance/record.php and
 * finance/contributions.php: every row carries its own currency and its USD
 * equivalent sits beneath it whenever the two differ.
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

$has_module  = mu_mod('finance');
$has_budgets = mu_mod('budgets');
$can_view    = mu_can('finance.view');
$can_add     = mu_can('finance.add');
$can_edit    = mu_can('finance.edit');
$can_approve = mu_can('finance.approve');
$can_delete  = mu_can('finance.delete');

/* ─────────────────────────── BRANCH AWARENESS ───────────────────────────
   Whose money this is. Entirely inert for a single church: is_multi_branch()
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
     * own key, so an expense never hops between branches on reload.
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

/* ═══════════════════════════ THE EXPENSE LEDGER ═══════════════════════════ */

$cur_by_code = array_column($currencies, null, 'code');
$cat_by_key  = array_column($expense_categories, null, 'key');
$meth_by_key = array_column($payment_methods, null, 'key');

/** An amount in its own currency, converted to USD — the same rule as the
    contribution pages, so the two ledgers can be read side by side. */
function fin_usd(float $amount, string $code): float
{
    global $cur_by_code;
    return $amount * ($cur_by_code[$code]['exchange_rate_to_usd'] ?? 1.0);
}

function fin_sym(string $code): string
{
    global $cur_by_code;
    return $cur_by_code[$code]['symbol'] ?? '$';
}

/* The five workflow states, and how each one reads. */
$STATUS = [
    'draft'    => ['label' => 'Draft',            'icon' => 'fa-pen-ruler'],
    'pending'  => ['label' => 'Pending Approval', 'icon' => 'fa-hourglass-half'],
    'approved' => ['label' => 'Approved',         'icon' => 'fa-circle-check'],
    'rejected' => ['label' => 'Rejected',         'icon' => 'fa-circle-xmark'],
    'paid'     => ['label' => 'Paid',             'icon' => 'fa-money-bill-transfer'],
];

$rows = [];
if ($has_module && $can_view) {
    foreach ($expenses_demo as $e) {
        $cat = $cat_by_key[$e['category']] ?? null;
        $rows[] = $e + [
            'date'        => date('Y-m-d', strtotime('-' . (int) $e['days_ago'] . ' days')),
            'usd'         => round(fin_usd((float) $e['amount'], $e['currency']), 2),
            'cat_name'    => $cat['name']   ?? $e['category'],
            'cat_icon'    => $cat['icon']   ?? 'fa-ellipsis',
            'cat_colour'  => $cat['colour'] ?? '#662F97',
            'meth_name'   => $meth_by_key[$e['method']]['name'] ?? $e['method'],
            'meth_icon'   => $meth_by_key[$e['method']]['icon'] ?? 'fa-money-bill-wave',
            'status_label'=> $STATUS[$e['status']]['label'],
            'status_icon' => $STATUS[$e['status']]['icon'],
            '_branch'     => $branch_aware ? mu_branch_for('exp-' . $e['id']) : null,
        ];
    }
}

if ($branch_aware && !$viewing_all) {
    $rows = array_values(array_filter($rows, static function ($r) use ($current_branch) {
        return $r['_branch'] && (int) $r['_branch']['id'] === (int) $current_branch;
    }));
}

/* Newest first — an expense ledger is read from the top. */
usort($rows, static fn($a, $b) => $a['days_ago'] <=> $b['days_ago']);

/* ── the approval queue: oldest first, because the oldest is the one holding
      somebody up ── */
$queue = array_values(array_filter($rows, static fn($r) => $r['status'] === 'pending'));
usort($queue, static fn($a, $b) => $b['days_ago'] <=> $a['days_ago']);

/* ── spending per category, over whatever the ledger currently holds ── */
$grand_usd = 0.0;
foreach ($rows as $r) { $grand_usd += $r['usd']; }

$by_cat = [];
foreach ($expense_categories as $c) {
    $mine = array_values(array_filter($rows, static fn($r) => $r['category'] === $c['key']));
    if (!$mine) { continue; }
    $sum = array_sum(array_column($mine, 'usd'));
    $trend = $expense_trend_demo[$c['key']] ?? [];
    $budget = $has_budgets ? (float) ($expense_budgets[$c['key']] ?? 0) : 0.0;
    $by_cat[] = $c + [
        'total'   => $sum,
        'count'   => count($mine),
        'share'   => $grand_usd > 0 ? ($sum / $grand_usd) * 100 : 0.0,
        'spark'   => array_slice($trend, -6),
        'budget'  => $budget,
        /* Utilisation is read against this month's line, so it can exceed
           100% — and it should say so plainly when it does. */
        'used'    => $budget > 0 ? min(999.0, ($sum / $budget) * 100) : null,
    ];
}
usort($by_cat, static fn($a, $b) => $b['total'] <=> $a['total']);
$cat_used = array_column($by_cat, 'used', 'key');

/* Headline figures. LATER: the same aggregate run over two windows. */
$ES = $expense_stats;
$share = 1.0;
if ($branch_aware && !$viewing_all) {
    $b = get_branch($current_branch);
    $share = $b ? max(0.05, (int) $b['members_count'] / max(1, (int) ($organisation['total_members'] ?? 1))) : 1.0;
}

$month_total    = 0.0;   /* everything in the last 30 days */
$approved_total = 0.0;
$pending_total  = 0.0;
foreach ($rows as $r) {
    if ($r['days_ago'] <= 30) { $month_total += $r['usd']; }
    if (in_array($r['status'], ['approved', 'paid'], true) && $r['days_ago'] <= 30) { $approved_total += $r['usd']; }
    if ($r['status'] === 'pending') { $pending_total += $r['usd']; }
}

/* Budget utilisation across every line, not per category. */
$budget_total = $has_budgets ? array_sum($expense_budgets) : 0.0;
$budget_used  = $budget_total > 0 ? min(999.0, ($month_total / $budget_total) * 100) : 0.0;

$requesters = array_values(array_unique(array_column($rows, 'by')));
sort($requesters);
$approvers = array_values(array_unique(array_filter(array_column($rows, 'approved_by'))));
sort($approvers);

/* Twelve month labels ending on the current month, for the trend chart. */
$trend_labels = [];
for ($i = 11; $i >= 0; $i--) { $trend_labels[] = date('M y', strtotime("-$i months")); }

$page_title = 'Expenses';
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
        <span aria-current="page">Expenses</span>
      </nav>
      <div class="title-row">
        <h1 class="page__title">Expenses</h1>
        <?php if ($has_module && $can_view): ?>
          <span class="count-chip" data-count="<?= count($rows) ?>">0</span>
        <?php endif; ?>
      </div>
      <p class="page__sub">Track and approve church expenditure.</p>
    </div>

    <?php if ($has_module && $can_view): ?>
      <div class="page__actions">
        <?php if ($can_add): ?>
          <button class="btn" type="button" id="btnNewExpense">
            <i class="fa-solid fa-plus" aria-hidden="true"></i> Record Expense
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

        <button class="iconbtn iconbtn--bordered" type="button" id="btnPrint" aria-label="Print this ledger" title="Print">
          <i class="fa-solid fa-print" aria-hidden="true"></i>
        </button>
      </div>
    <?php endif; ?>
  </header>


<?php if (!$has_module): ?>

  <section class="panel">
    <div class="empty">
      <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-plug-circle-xmark"></i></span>
      <h3>The Finance module is switched off</h3>
      <p>Your church's plan does not include expenditure tracking. A church administrator can request it from the platform team.</p>
      <a class="btn" href="<?= $base_url ?>index.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Dashboard</a>
    </div>
  </section>

<?php elseif (!$can_view): ?>

  <section class="panel">
    <div class="empty">
      <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-lock"></i></span>
      <h3>You do not have access to financial records</h3>
      <p>Expenditure is part of the church's financial record. Ask an administrator for the finance viewing permission.</p>
      <a class="btn" href="<?= $base_url ?>index.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Dashboard</a>
    </div>
  </section>

<?php else: ?>

  <!-- ═════════════════════════════ STAT STRIP ═════════════════════════════ -->
  <section class="stat-strip" aria-label="Expenditure at a glance">
    <div class="stat-tile is-static">
      <span class="stat-tile__icon tone-purple" aria-hidden="true"><i class="fa-solid fa-arrow-trend-down"></i></span>
      <span class="stat-tile__body">
        <span class="stat-tile__value">$<span data-count="<?= (int) round($month_total) ?>">0</span></span>
        <span class="stat-tile__label">Total This Month</span>
        <?= fin_delta($month_total, (float) $ES['month']['prev']) ?>
      </span>
    </div>

    <!-- Pending is the tile that should nag, so it carries the warning tone. -->
    <div class="stat-tile is-static stat-tile--warn">
      <span class="stat-tile__icon tone-amber" aria-hidden="true"><i class="fa-solid fa-hourglass-half"></i></span>
      <span class="stat-tile__body">
        <span class="stat-tile__value">$<span data-count="<?= (int) round($pending_total) ?>">0</span></span>
        <span class="stat-tile__label">Pending Approval</span>
        <span class="pendcount">
          <i class="fa-solid fa-file-circle-question" aria-hidden="true"></i>
          <b><?= count($queue) ?></b> awaiting a decision
        </span>
      </span>
    </div>

    <div class="stat-tile is-static">
      <span class="stat-tile__icon tone-green" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></span>
      <span class="stat-tile__body">
        <span class="stat-tile__value">$<span data-count="<?= (int) round($approved_total) ?>">0</span></span>
        <span class="stat-tile__label">Approved This Month</span>
        <?= fin_delta($approved_total, (float) $ES['approved']['prev']) ?>
      </span>
    </div>

    <?php if ($has_budgets): ?>
      <div class="stat-tile is-static stat-tile--bar">
        <span class="stat-tile__icon tone-blue" aria-hidden="true"><i class="fa-solid fa-scale-balanced"></i></span>
        <span class="stat-tile__body">
          <span class="stat-tile__value"><span data-count="<?= (int) round($budget_used) ?>">0</span>%</span>
          <span class="stat-tile__label">Budget Utilisation</span>
          <span class="collected">
            <span class="rbar rbar--sm">
              <span class="rbar__fill" style="width:<?= min(100, round($budget_used, 1)) ?>%;background:<?= $budget_used > 100 ? 'var(--danger)' : ($budget_used > 85 ? 'var(--warn)' : 'var(--ok)') ?>"></span>
            </span>
            <b>$<?= number_format($month_total, 0) ?></b> of $<?= number_format($budget_total, 0) ?>
          </span>
        </span>
      </div>
    <?php endif; ?>
  </section>


  <!-- ══════════════════════════════ THE TABS ══════════════════════════════ -->
  <div class="tabs" role="tablist" aria-label="Expenses">
    <button class="tab is-on" type="button" role="tab" id="tab-all" aria-controls="panel-all" aria-selected="true" data-tab="all">
      <i class="fa-solid fa-list" aria-hidden="true"></i> All Expenses
      <span class="tab__n"><?= count($rows) ?></span>
    </button>
    <?php if ($can_approve): ?>
      <button class="tab" type="button" role="tab" id="tab-queue" aria-controls="panel-queue" aria-selected="false" tabindex="-1" data-tab="queue">
        <i class="fa-solid fa-hourglass-half" aria-hidden="true"></i> Pending Approval
        <span class="tab__n tab__n--warn"><?= count($queue) ?></span>
      </button>
    <?php endif; ?>
    <button class="tab" type="button" role="tab" id="tab-cat" aria-controls="panel-cat" aria-selected="false" tabindex="-1" data-tab="cat">
      <i class="fa-solid fa-chart-pie" aria-hidden="true"></i> By Category
    </button>
  </div>

  <!-- ═════════════════════════ TAB 1 — ALL EXPENSES ═════════════════════════ -->
  <section class="tabpanel" id="panel-all" role="tabpanel" aria-labelledby="tab-all">

    <!-- ── filter bar ── -->
    <section class="filters" id="filters">
      <button class="filters__toggle" type="button" id="fToggle" aria-expanded="false" aria-controls="filters">
        <i class="fa-solid fa-sliders" aria-hidden="true"></i> Filters
        <span class="count-chip" data-filter-n hidden>0</span>
        <span style="flex:1"></span>
        <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
      </button>

      <div class="filters__grid">
        <div class="field field--wide">
          <label for="fSearch">Search</label>
          <div class="search-field">
            <i class="fa-solid fa-magnifying-glass search-field__icon" aria-hidden="true"></i>
            <input class="input" type="search" id="fSearch" data-search
                   placeholder="Description, reference, payee or amount&hellip;" autocomplete="off">
            <button class="search-field__clear" type="button" data-search-clear hidden aria-label="Clear search">
              <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
          </div>
        </div>

        <div class="field field--wide">
          <label>Date range</label>
          <div class="daterange">
            <input class="input" type="date" id="fFrom" data-filter aria-label="From date">
            <span class="daterange__to" aria-hidden="true">&ndash;</span>
            <input class="input" type="date" id="fTo" data-filter aria-label="To date">
          </div>
          <div class="chips-row chips-row--presets">
            <button class="rchip" type="button" data-preset="week">This Week</button>
            <button class="rchip" type="button" data-preset="month">This Month</button>
            <button class="rchip" type="button" data-preset="last">Last Month</button>
            <button class="rchip" type="button" data-preset="quarter">This Quarter</button>
            <button class="rchip" type="button" data-preset="year">This Year</button>
          </div>
        </div>

        <div class="field">
          <label for="fCategory">Category</label>
          <select class="select" id="fCategory" data-filter>
            <option value="">All</option>
            <?php foreach ($expense_categories as $c): ?>
              <option value="<?= htmlspecialchars($c['key']) ?>"><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label for="fStatus">Status</label>
          <select class="select" id="fStatus" data-filter>
            <option value="">All</option>
            <?php foreach ($STATUS as $k => $s): ?>
              <option value="<?= $k ?>"><?= htmlspecialchars($s['label']) ?></option>
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

        <div class="field">
          <label for="fMin">Amount (USD)</label>
          <div class="daterange">
            <input class="input" type="number" id="fMin" min="0" step="1" placeholder="Min" data-filter aria-label="Minimum amount">
            <span class="daterange__to" aria-hidden="true">&ndash;</span>
            <input class="input" type="number" id="fMax" min="0" step="1" placeholder="Max" data-filter aria-label="Maximum amount">
          </div>
        </div>

        <div class="field">
          <label for="fBy">Requested by</label>
          <select class="select" id="fBy" data-filter>
            <option value="">All</option>
            <?php foreach ($requesters as $p): ?>
              <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label for="fApprover">Approved by</label>
          <select class="select" id="fApprover" data-filter>
            <option value="">All</option>
            <?php foreach ($approvers as $p): ?>
              <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
            <?php endforeach; ?>
          </select>
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

        <div class="field">
          <label for="fReceipt">Has receipt</label>
          <select class="select" id="fReceipt" data-filter>
            <option value="">All</option>
            <option value="yes">Yes</option>
            <option value="no">No</option>
          </select>
        </div>

        <div class="filters__actions">
          <button class="btn" type="button" data-toast="Filters applied"><i class="fa-solid fa-check" aria-hidden="true"></i> Apply</button>
          <button class="btn btn--ghost" type="button" data-reset-filters><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Reset</button>
        </div>
      </div>

      <div class="chips-row" data-filter-chips hidden></div>
    </section>

    <!-- ── the ledger ── -->
    <section class="panel">
      <div class="dt-wrap">
        <table class="dt" id="expTable">
          <thead>
            <tr>
              <th style="width:34px"><input class="check" type="checkbox" data-check-all aria-label="Select all expenses"></th>
              <th style="width:40px">#</th>
              <th class="is-sortable" data-sort="date">Date <i class="fa-solid fa-sort sort" aria-hidden="true"></i></th>
              <th class="is-sortable" data-sort="desc">Description <i class="fa-solid fa-sort sort" aria-hidden="true"></i></th>
              <th>Category</th>
              <th class="is-sortable" data-sort="usd" style="text-align:right">Amount <i class="fa-solid fa-sort sort" aria-hidden="true"></i></th>
              <th>Method</th>
              <th>Paid To</th>
              <th>Requested By</th>
              <th>Approved By</th>
              <th style="text-align:center">Receipt</th>
              <?php if ($show_branch): ?><th><?= htmlspecialchars(t('branch_singular')) ?></th><?php endif; ?>
              <th>Status</th>
              <th class="col-actions" style="text-align:right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $i => $r): ?>
              <tr data-row data-id="<?= (int) $r['id'] ?>"
                  data-date="<?= $r['date'] ?>"
                  data-desc="<?= htmlspecialchars(mb_strtolower($r['description'])) ?>"
                  data-ref="<?= htmlspecialchars(mb_strtolower($r['ref'])) ?>"
                  data-payee="<?= htmlspecialchars(mb_strtolower($r['payee'])) ?>"
                  data-category="<?= htmlspecialchars($r['category']) ?>"
                  data-status="<?= htmlspecialchars($r['status']) ?>"
                  data-method="<?= htmlspecialchars($r['method']) ?>"
                  data-currency="<?= htmlspecialchars($r['currency']) ?>"
                  data-usd="<?= number_format($r['usd'], 2, '.', '') ?>"
                  data-amount="<?= number_format((float) $r['amount'], 2, '.', '') ?>"
                  data-by="<?= htmlspecialchars($r['by']) ?>"
                  data-approver="<?= htmlspecialchars((string) $r['approved_by']) ?>"
                  data-receipt="<?= $r['receipt'] ? 'yes' : 'no' ?>"
                  <?php if ($show_branch && $r['_branch']): ?>data-branch="<?= (int) $r['_branch']['id'] ?>"<?php endif; ?>>
                <td><input class="check" type="checkbox" data-check aria-label="Select <?= htmlspecialchars($r['ref']) ?>"></td>
                <td class="num"><?= $i + 1 ?></td>
                <td class="nowrap"><?= mu_date($r['date']) ?><span class="metaline"><?= mu_ago((int) $r['days_ago']) ?></span></td>
                <td>
                  <b class="expdesc"><?= htmlspecialchars($r['description']) ?></b>
                  <span class="metaline refcell"><?= htmlspecialchars($r['ref']) ?></span>
                </td>
                <td>
                  <span class="excat" style="--c:<?= htmlspecialchars($r['cat_colour']) ?>">
                    <i class="fa-solid <?= htmlspecialchars($r['cat_icon']) ?>" aria-hidden="true"></i>
                    <?= htmlspecialchars($r['cat_name']) ?>
                  </span>
                </td>
                <td class="num">
                  <b><?= htmlspecialchars(fin_sym($r['currency'])) ?><?= number_format((float) $r['amount'], 2) ?></b>
                  <?php if ($r['currency'] !== 'USD'): ?>
                    <span class="metaline">&asymp; $<?= number_format($r['usd'], 2) ?></span>
                  <?php endif; ?>
                </td>
                <td class="nowrap">
                  <i class="fa-solid <?= htmlspecialchars($r['meth_icon']) ?> methico" aria-hidden="true"></i>
                  <?= htmlspecialchars($r['meth_name']) ?>
                </td>
                <td><?= htmlspecialchars($r['payee']) ?></td>
                <td>
                  <span class="minirow minirow--tight">
                    <?= mu_av($r['by'], 'sm') ?>
                    <span class="minirow__text"><b><?= htmlspecialchars($r['by']) ?></b></span>
                  </span>
                </td>
                <td>
                  <?php if ($r['approved_by']): ?>
                    <span class="minirow minirow--tight">
                      <?= mu_av($r['approved_by'], 'sm') ?>
                      <span class="minirow__text"><b><?= htmlspecialchars($r['approved_by']) ?></b></span>
                    </span>
                  <?php elseif ($r['status'] === 'rejected'): ?>
                    <span class="pill pill--ex-rejected">Rejected</span>
                  <?php elseif ($r['status'] === 'draft'): ?>
                    <span class="muted">Not submitted</span>
                  <?php else: ?>
                    <span class="pill pill--ex-pending">Pending</span>
                  <?php endif; ?>
                </td>
                <td style="text-align:center">
                  <?php if ($r['receipt']): ?>
                    <button class="iconbtn" type="button" data-viewreceipt="<?= (int) $r['id'] ?>" aria-label="View receipt for <?= htmlspecialchars($r['ref']) ?>" title="Receipt attached">
                      <i class="fa-solid fa-paperclip" aria-hidden="true"></i>
                    </button>
                  <?php else: ?>
                    <span class="muted" title="No receipt attached">&mdash;</span>
                  <?php endif; ?>
                </td>
                <?php if ($show_branch): ?><td><?= mu_branch_chip($r['_branch']) ?></td><?php endif; ?>
                <td>
                  <span class="pill pill--ex-<?= htmlspecialchars($r['status']) ?>">
                    <i class="fa-solid <?= htmlspecialchars($r['status_icon']) ?>" aria-hidden="true"></i>
                    <?= htmlspecialchars($r['status_label']) ?>
                  </span>
                </td>
                <td class="col-actions">
                  <div class="rowacts">
                    <button class="iconbtn" type="button" data-open="<?= (int) $r['id'] ?>" aria-label="View <?= htmlspecialchars($r['ref']) ?>">
                      <i class="fa-regular fa-eye" aria-hidden="true"></i>
                    </button>
                    <?php if ($can_edit): ?>
                      <button class="iconbtn" type="button" data-edit="<?= (int) $r['id'] ?>" aria-label="Edit <?= htmlspecialchars($r['ref']) ?>">
                        <i class="fa-solid fa-pen" aria-hidden="true"></i>
                      </button>
                    <?php endif; ?>
                    <?php if ($can_approve && $r['status'] === 'pending'): ?>
                      <button class="iconbtn iconbtn--ok" type="button" data-approve="<?= (int) $r['id'] ?>" aria-label="Approve <?= htmlspecialchars($r['ref']) ?>">
                        <i class="fa-solid fa-check" aria-hidden="true"></i>
                      </button>
                    <?php endif; ?>

                    <div class="drop" data-menu>
                      <button class="iconbtn" type="button" aria-haspopup="true" aria-expanded="false" data-menu-btn aria-label="More actions">
                        <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                      </button>
                      <div class="menu menu--end" data-menu-panel hidden>
                        <button class="menu__item" type="button" data-open="<?= (int) $r['id'] ?>"><i class="fa-regular fa-eye" aria-hidden="true"></i> View Details</button>
                        <?php if ($can_edit): ?>
                          <button class="menu__item" type="button" data-edit="<?= (int) $r['id'] ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</button>
                        <?php endif; ?>
                        <?php if ($can_approve): ?>
                          <button class="menu__item" type="button" data-approve="<?= (int) $r['id'] ?>"><i class="fa-solid fa-check" aria-hidden="true"></i> Approve</button>
                          <button class="menu__item" type="button" data-reject="<?= (int) $r['id'] ?>"><i class="fa-solid fa-xmark" aria-hidden="true"></i> Reject</button>
                        <?php endif; ?>
                        <?php if ($can_edit): ?>
                          <button class="menu__item" type="button" data-attach="<?= (int) $r['id'] ?>"><i class="fa-solid fa-paperclip" aria-hidden="true"></i> Attach Receipt</button>
                        <?php endif; ?>
                        <?php if ($can_add): ?>
                          <button class="menu__item" type="button" data-duplicate="<?= (int) $r['id'] ?>"><i class="fa-regular fa-copy" aria-hidden="true"></i> Duplicate</button>
                        <?php endif; ?>
                        <button class="menu__item" type="button" data-voucher="<?= (int) $r['id'] ?>"><i class="fa-solid fa-print" aria-hidden="true"></i> Print Voucher</button>
                        <?php if ($can_delete): ?>
                          <button class="menu__item is-danger" type="button" data-delete="<?= (int) $r['id'] ?>"><i class="fa-regular fa-trash-can" aria-hidden="true"></i> Delete</button>
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

      <!-- Below 768px the table becomes cards. Never a shrunken table. -->
      <div class="dt-cards">
        <?php foreach ($rows as $r): ?>
          <article class="pcard" data-card
                   data-date="<?= $r['date'] ?>"
                   data-desc="<?= htmlspecialchars(mb_strtolower($r['description'])) ?>"
                   data-ref="<?= htmlspecialchars(mb_strtolower($r['ref'])) ?>"
                   data-payee="<?= htmlspecialchars(mb_strtolower($r['payee'])) ?>"
                   data-category="<?= htmlspecialchars($r['category']) ?>"
                   data-status="<?= htmlspecialchars($r['status']) ?>"
                   data-method="<?= htmlspecialchars($r['method']) ?>"
                   data-currency="<?= htmlspecialchars($r['currency']) ?>"
                   data-usd="<?= number_format($r['usd'], 2, '.', '') ?>"
                   data-amount="<?= number_format((float) $r['amount'], 2, '.', '') ?>"
                   data-by="<?= htmlspecialchars($r['by']) ?>"
                   data-approver="<?= htmlspecialchars((string) $r['approved_by']) ?>"
                   data-receipt="<?= $r['receipt'] ? 'yes' : 'no' ?>"
                   <?php if ($show_branch && $r['_branch']): ?>data-branch="<?= (int) $r['_branch']['id'] ?>"<?php endif; ?>>
            <button class="pcard__main" type="button" data-card-toggle aria-expanded="false">
              <span class="catico" style="--c:<?= htmlspecialchars($r['cat_colour']) ?>" aria-hidden="true">
                <i class="fa-solid <?= htmlspecialchars($r['cat_icon']) ?>"></i>
              </span>
              <span class="pcard__text">
                <span class="pcard__name"><?= htmlspecialchars($r['description']) ?></span>
                <span class="pcard__meta"><?= mu_date($r['date'], 'd M Y') ?> &middot; <?= htmlspecialchars($r['cat_name']) ?></span>
              </span>
              <span class="pcard__amt">
                <?= htmlspecialchars(fin_sym($r['currency'])) ?><?= number_format((float) $r['amount'], 2) ?>
                <?php if ($r['currency'] !== 'USD'): ?><small>&asymp; $<?= number_format($r['usd'], 2) ?></small><?php endif; ?>
              </span>
              <i class="fa-solid fa-chevron-down pcard__chev" aria-hidden="true"></i>
            </button>
            <div class="pcard__more">
              <div class="pcard__row"><span>Status</span><span><span class="pill pill--ex-<?= htmlspecialchars($r['status']) ?>"><?= htmlspecialchars($r['status_label']) ?></span></span></div>
              <div class="pcard__row"><span>Reference</span><span class="refcell"><?= htmlspecialchars($r['ref']) ?></span></div>
              <div class="pcard__row"><span>Method</span><span><?= htmlspecialchars($r['meth_name']) ?></span></div>
              <div class="pcard__row"><span>Paid to</span><span><?= htmlspecialchars($r['payee']) ?></span></div>
              <div class="pcard__row"><span>Requested by</span><span><?= htmlspecialchars($r['by']) ?></span></div>
              <div class="pcard__row"><span>Approved by</span><span><?= $r['approved_by'] ? htmlspecialchars($r['approved_by']) : '&mdash;' ?></span></div>
              <div class="pcard__row"><span>Receipt</span><span><?= $r['receipt'] ? 'Attached' : 'None' ?></span></div>
              <?php if ($show_branch && $r['_branch']): ?>
                <div class="pcard__row"><span><?= htmlspecialchars(t('branch_singular')) ?></span><span><?= mu_branch_chip($r['_branch']) ?></span></div>
              <?php endif; ?>
              <div class="pcard__acts">
                <button class="btn btn--ghost btn--sm" type="button" data-open="<?= (int) $r['id'] ?>"><i class="fa-regular fa-eye" aria-hidden="true"></i> View</button>
                <?php if ($can_approve && $r['status'] === 'pending'): ?>
                  <button class="btn btn--ghost btn--sm" type="button" data-approve="<?= (int) $r['id'] ?>"><i class="fa-solid fa-check" aria-hidden="true"></i> Approve</button>
                <?php endif; ?>
                <?php if ($can_edit): ?>
                  <button class="btn btn--ghost btn--sm" type="button" data-edit="<?= (int) $r['id'] ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</button>
                <?php endif; ?>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

      <div class="empty" id="listEmpty" hidden>
        <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-filter-circle-xmark"></i></span>
        <h3>No expenses match those filters</h3>
        <p>Try a wider date range or a different category, or clear the filters to see the whole ledger again.</p>
        <button class="btn btn--ghost" type="button" data-reset-filters>
          <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Reset filters
        </button>
      </div>

      <!-- Pinned above the pagination: what the current filter actually sums to. -->
      <div class="totalsrow">
        <span class="totalsrow__label">
          <i class="fa-solid fa-calculator" aria-hidden="true"></i>
          <b data-totals-count>0</b> expenses in view
        </span>
        <span class="totalsrow__cur" data-totals-cur></span>
        <span class="totalsrow__grand">
          Grand total <b data-totals-usd>$0.00</b>
        </span>
      </div>

      <nav class="pager" aria-label="Pagination">
        <button class="pager__btn" type="button" disabled><i class="fa-solid fa-chevron-left" aria-hidden="true"></i> Previous</button>
        <span class="pager__pages">Page <b>1</b> of <b>1</b></span>
        <button class="pager__btn" type="button" disabled>Next <i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
      </nav>
    </section>

    <!-- ── bulk actions, floating ── -->
    <div class="bulkbar" id="bulkBar" hidden>
      <span class="bulkbar__count"><b data-bulk-count>0</b> selected</span>
      <span class="bulkbar__sep" aria-hidden="true"></span>
      <?php if ($can_approve): ?>
        <button class="bulkbar__btn" type="button" id="bulkApprove"><i class="fa-solid fa-check" aria-hidden="true"></i> Approve</button>
        <button class="bulkbar__btn" type="button" id="bulkReject"><i class="fa-solid fa-xmark" aria-hidden="true"></i> Reject</button>
      <?php endif; ?>
      <button class="bulkbar__btn" type="button" id="bulkPaid"><i class="fa-solid fa-money-bill-transfer" aria-hidden="true"></i> Mark Paid</button>
      <button class="bulkbar__btn" type="button" data-toast="Export started"><i class="fa-solid fa-file-export" aria-hidden="true"></i> Export Selected</button>
      <?php if ($can_delete): ?>
        <button class="bulkbar__btn is-danger" type="button" id="bulkDelete"><i class="fa-regular fa-trash-can" aria-hidden="true"></i> Delete</button>
      <?php endif; ?>
      <span class="bulkbar__sep" aria-hidden="true"></span>
      <button class="bulkbar__close" type="button" id="bulkClose" aria-label="Clear selection">
        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
      </button>
    </div>
  </section>

<?php if ($can_approve): ?>
  <!-- ═══════════════════════ TAB 2 — PENDING APPROVAL ═══════════════════════ -->
  <section class="tabpanel" id="panel-queue" role="tabpanel" aria-labelledby="tab-queue" hidden>

    <?php if (!$queue): ?>
      <section class="panel">
        <div class="empty">
          <span class="empty__icon" aria-hidden="true"><i class="fa-regular fa-circle-check"></i></span>
          <h3>Nothing is waiting on you</h3>
          <p>Every expense has been decided. New requests will appear here as they are submitted.</p>
        </div>
      </section>
    <?php else: ?>

      <div class="at-notice at-notice--warn" role="note">
        <i class="fa-solid fa-hourglass-half" aria-hidden="true"></i>
        <div class="at-notice__body">
          <strong><?= count($queue) ?> expense<?= count($queue) === 1 ? '' : 's' ?> waiting, worth $<?= number_format($pending_total, 2) ?></strong>
          <span>Oldest first &mdash; the one at the top has been waiting longest.</span>
        </div>
      </div>

      <div class="queue">
        <?php foreach ($queue as $q): ?>
          <?php
            $wait = (int) $q['days_ago'];
            $wcls = $wait > 7 ? 'is-late' : ($wait > 3 ? 'is-soon' : '');
            $used = $cat_used[$q['category']] ?? null;
          ?>
          <article class="qcard <?= $wcls ?>" style="--c:<?= htmlspecialchars($q['cat_colour']) ?>" data-queue="<?= (int) $q['id'] ?>">
            <div class="qcard__main">
              <header class="qcard__head">
                <span class="catico" style="--c:<?= htmlspecialchars($q['cat_colour']) ?>" aria-hidden="true">
                  <i class="fa-solid <?= htmlspecialchars($q['cat_icon']) ?>"></i>
                </span>
                <div class="qcard__id">
                  <h3><?= htmlspecialchars($q['description']) ?></h3>
                  <p>
                    <span class="excat" style="--c:<?= htmlspecialchars($q['cat_colour']) ?>">
                      <?= htmlspecialchars($q['cat_name']) ?>
                    </span>
                    <span class="refcell"><?= htmlspecialchars($q['ref']) ?></span>
                  </p>
                </div>
                <span class="qcard__wait <?= $wcls ?>">
                  <i class="fa-regular fa-clock" aria-hidden="true"></i>
                  <?= $wait ?> day<?= $wait === 1 ? '' : 's' ?> waiting
                </span>
              </header>

              <p class="qcard__amt">
                <?= htmlspecialchars(fin_sym($q['currency'])) ?><?= number_format((float) $q['amount'], 2) ?>
                <?php if ($q['currency'] !== 'USD'): ?>
                  <small>&asymp; $<?= number_format($q['usd'], 2) ?> &middot; 1 <?= htmlspecialchars($q['currency']) ?> = $<?= rtrim(rtrim(number_format((float) $cur_by_code[$q['currency']]['exchange_rate_to_usd'], 4, '.', ''), '0'), '.') ?></small>
                <?php endif; ?>
              </p>

              <div class="qcard__who">
                <?= mu_av($q['by'], 'sm') ?>
                <span>
                  Requested by <b><?= htmlspecialchars($q['by']) ?></b>
                  on <?= mu_date($q['date'], 'd M Y') ?>
                  &middot; paid to <b><?= htmlspecialchars($q['payee']) ?></b>
                  via <?= htmlspecialchars($q['meth_name']) ?>
                </span>
              </div>

              <?php if ($q['notes']): ?>
                <blockquote class="qcard__note">
                  <i class="fa-solid fa-quote-left" aria-hidden="true"></i>
                  <?= htmlspecialchars($q['notes']) ?>
                </blockquote>
              <?php endif; ?>

              <?php if ($has_budgets && $used !== null): ?>
                <?php $bcls = $used > 100 ? 'is-over' : ($used > 85 ? 'is-tight' : ''); ?>
                <p class="budgetline <?= $bcls ?>">
                  <i class="fa-solid fa-scale-balanced" aria-hidden="true"></i>
                  This category is <b><?= number_format($used, 0) ?>%</b> utilised
                  <span>($<?= number_format($by_cat[array_search($q['category'], array_column($by_cat, 'key'), true)]['total'], 0) ?>
                  of $<?= number_format((float) $expense_budgets[$q['category']], 0) ?>)</span>
                </p>
              <?php endif; ?>
            </div>

            <aside class="qcard__side">
              <?php if ($q['receipt']): ?>
                <button class="thumb" type="button" data-viewreceipt="<?= (int) $q['id'] ?>" aria-label="View the receipt for <?= htmlspecialchars($q['ref']) ?>">
                  <span class="thumb__img" aria-hidden="true"><i class="fa-regular fa-file-image"></i></span>
                  <span class="thumb__cap">Receipt attached</span>
                </button>
              <?php else: ?>
                <span class="thumb thumb--none">
                  <span class="thumb__img" aria-hidden="true"><i class="fa-regular fa-file"></i></span>
                  <span class="thumb__cap">No receipt</span>
                </span>
              <?php endif; ?>

              <div class="qcard__acts">
                <button class="btn btn--ok" type="button" data-approve="<?= (int) $q['id'] ?>">
                  <i class="fa-solid fa-check" aria-hidden="true"></i> Approve
                </button>
                <button class="btn btn--danger" type="button" data-reject="<?= (int) $q['id'] ?>">
                  <i class="fa-solid fa-xmark" aria-hidden="true"></i> Reject
                </button>
                <button class="btn btn--ghost btn--sm" type="button" data-open="<?= (int) $q['id'] ?>">
                  <i class="fa-regular fa-eye" aria-hidden="true"></i> View full details
                </button>
              </div>
            </aside>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
<?php endif; ?>


  <!-- ═══════════════════════════ TAB 3 — BY CATEGORY ═══════════════════════════ -->
  <section class="tabpanel" id="panel-cat" role="tabpanel" aria-labelledby="tab-cat" hidden>

    <div class="catgrid">
      <?php foreach ($by_cat as $c): ?>
        <article class="catcard" style="--c:<?= htmlspecialchars($c['colour']) ?>">
          <header class="catcard__head">
            <span class="catico" style="--c:<?= htmlspecialchars($c['colour']) ?>" aria-hidden="true">
              <i class="fa-solid <?= htmlspecialchars($c['icon']) ?>"></i>
            </span>
            <div class="catcard__id">
              <h3><?= htmlspecialchars($c['name']) ?></h3>
              <p><?= $c['count'] ?> expense<?= $c['count'] === 1 ? '' : 's' ?></p>
            </div>
          </header>

          <p class="catcard__total">$<?= number_format($c['total'], 2) ?></p>

          <div class="catcard__share">
            <span class="catcard__share-top">
              <span>Share of all spending</span>
              <b><?= number_format($c['share'], 1) ?>%</b>
            </span>
            <span class="rbar"><span class="rbar__fill" style="width:<?= round($c['share'], 1) ?>%;background:var(--c)"></span></span>
          </div>

          <?php if ($has_budgets && $c['budget'] > 0): ?>
            <?php $bcls = $c['used'] > 100 ? 'is-over' : ($c['used'] > 85 ? 'is-tight' : ''); ?>
            <div class="catcard__budget <?= $bcls ?>">
              <span class="catcard__share-top">
                <span>Against budget</span>
                <b><?= number_format($c['used'], 0) ?>%</b>
              </span>
              <span class="rbar rbar--sm">
                <span class="rbar__fill" style="width:<?= min(100, round($c['used'], 1)) ?>%"></span>
              </span>
              <span class="catcard__budgetcap">
                $<?= number_format($c['total'], 0) ?> of $<?= number_format($c['budget'], 0) ?>
                <?php if ($c['used'] > 100): ?>
                  &middot; <b>$<?= number_format($c['total'] - $c['budget'], 0) ?> over</b>
                <?php endif; ?>
              </span>
            </div>
          <?php endif; ?>

          <?php if ($c['spark']): ?>
            <?php
              /* Six months, scaled to the series' own range — a zero-based
                 axis would flatten every line that matters. */
              $lo = min($c['spark']); $hi = max($c['spark']); $span = max(1, $hi - $lo);
              $n = max(1, count($c['spark']) - 1);
              $pts = [];
              foreach (array_values($c['spark']) as $i => $v) {
                  $pts[] = round(($i / $n) * 100, 2) . ',' . round(26 - (($v - $lo) / $span) * 22, 2);
              }
              $line = implode(' ', $pts);
            ?>
            <svg class="spark8 catcard__spark" viewBox="0 0 100 26" preserveAspectRatio="none" role="img"
                 aria-label="Spending over the last six months">
              <polygon class="spark8__fill" points="0,26 <?= $line ?> 100,26"></polygon>
              <polyline class="spark8__line" points="<?= $line ?>"></polyline>
            </svg>
            <p class="catcard__sparklabel">Last 6 months</p>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>

    <div class="chartgrid chartgrid--2" style="margin-top:16px">
      <section class="chartcard">
        <header class="chartcard__head">
          <div>
            <h2>Spending by Category</h2>
            <p>Share of everything going out.</p>
          </div>
        </header>
        <div class="chartbox chartbox--tall"><canvas id="catChart"></canvas></div>
      </section>

      <section class="chartcard">
        <header class="chartcard__head">
          <div>
            <h2>Category Trend</h2>
            <p>Twelve months for one category at a time.</p>
          </div>
          <select class="select select--sm" id="trendPick" aria-label="Which category to chart">
            <?php foreach ($by_cat as $c): ?>
              <option value="<?= htmlspecialchars($c['key']) ?>"><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </header>
        <div class="chartbox chartbox--tall"><canvas id="trendChart" data-axis-x="Month" data-axis-y="Spent (USD)"></canvas></div>
      </section>
    </div>
  </section>

<?php endif; ?>
</div>

<?php if ($has_module && $can_view): ?>

<div class="drawer-scrim" data-drawer-scrim hidden></div>

<!-- ══════════════════════ EXPENSE DETAIL DRAWER ══════════════════════ -->
<aside class="drawer" id="expDrawer" role="dialog" aria-modal="true" aria-labelledby="dTitle" hidden>
  <header class="drawer__head">
    <span class="catico catico--lg" data-d-catico aria-hidden="true"><i class="fa-solid fa-ellipsis"></i></span>
    <div class="drawer__title">
      <h2 id="dTitle">Expense</h2>
      <p><span class="refcell" data-d-ref>&mdash;</span></p>
    </div>
    <button class="iconbtn" type="button" data-drawer-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
  </header>

  <div class="drawer__body">
    <!-- The amount is the point of the record, so it is what you see first. -->
    <div class="bigamt">
      <span class="bigamt__value" data-d-amount>$0.00</span>
      <span class="bigamt__usd" data-d-usd hidden></span>
      <span class="excat" data-d-cat style="--c:#662F97">Category</span>
    </div>

    <p class="drawer__prose drawer__prose--lead" data-d-desc>&mdash;</p>

    <dl class="deflist">
      <div><dt>Status</dt><dd data-d-status>&mdash;</dd></div>
      <div><dt>Date</dt><dd data-d-date>&mdash;</dd></div>
      <div><dt>Paid to</dt><dd data-d-payee>&mdash;</dd></div>
      <div><dt>Payment method</dt><dd data-d-method>&mdash;</dd></div>
      <div data-d-txnrow><dt>Reference</dt><dd class="refcell" data-d-txn>&mdash;</dd></div>
      <?php if ($show_branch): ?><div><dt><?= htmlspecialchars(t('branch_singular')) ?></dt><dd data-d-branch>&mdash;</dd></div><?php endif; ?>
      <?php if ($has_budgets): ?><div><dt>Budget line</dt><dd data-d-budget>&mdash;</dd></div><?php endif; ?>
    </dl>

    <p class="minilist__head">Justification</p>
    <p class="drawer__prose" data-d-notes>&mdash;</p>

    <!-- Requested → approved → paid. A record of spending needs a trail. -->
    <p class="minilist__head">Approval timeline</p>
    <ol class="audit" data-d-timeline></ol>

    <p class="minilist__head">Receipt</p>
    <div data-d-receipt></div>
  </div>

  <footer class="drawer__foot drawer__foot--wrap">
    <?php if ($can_approve): ?>
      <button class="btn btn--ok" type="button" id="dApprove"><i class="fa-solid fa-check" aria-hidden="true"></i> Approve</button>
      <button class="btn btn--danger" type="button" id="dReject"><i class="fa-solid fa-xmark" aria-hidden="true"></i> Reject</button>
    <?php endif; ?>
    <?php if ($can_edit): ?>
      <button class="btn btn--ghost" type="button" id="dEdit"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</button>
    <?php endif; ?>
    <button class="btn btn--ghost" type="button" id="dVoucher"><i class="fa-solid fa-print" aria-hidden="true"></i> Print Voucher</button>
  </footer>
</aside>


<?php if ($can_add || $can_edit): ?>
<!-- ══════════════════════ ADD / EDIT EXPENSE ══════════════════════ -->
<div class="modal-scrim" id="modalExpense" hidden>
  <div class="modal modal--wide" role="dialog" aria-modal="true" aria-labelledby="exTitle">
    <header class="modal__head">
      <h2 id="exTitle">Record an Expense</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>

    <div class="modal__body">
      <div class="form-grid">
        <div class="field field--wide">
          <label for="exDesc">Description <span class="req">*</span></label>
          <input class="input" type="text" id="exDesc" placeholder="What the money was spent on" autocomplete="off">
        </div>

        <div class="field field--wide">
          <label id="exCatLbl">Category <span class="req">*</span></label>
          <div class="iconcards" role="radiogroup" aria-labelledby="exCatLbl">
            <?php foreach ($expense_categories as $k => $c): ?>
              <label class="iconcard" style="--c:<?= htmlspecialchars($c['colour']) ?>">
                <input type="radio" name="exCat" value="<?= htmlspecialchars($c['key']) ?>" <?= $k === 0 ? 'checked' : '' ?>>
                <span class="iconcard__box">
                  <i class="fa-solid <?= htmlspecialchars($c['icon']) ?>" aria-hidden="true"></i>
                  <span><?= htmlspecialchars($c['name']) ?></span>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="field">
          <label for="exAmount">Amount <span class="req">*</span></label>
          <div class="daterange">
            <input class="input" type="text" id="exAmount" inputmode="decimal" placeholder="0.00" autocomplete="off">
            <select class="select" id="exCurrency" aria-label="Currency">
              <?php foreach ($currencies as $c): ?>
                <option value="<?= htmlspecialchars($c['code']) ?>"><?= htmlspecialchars($c['code']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <p class="hint" data-ex-usd hidden></p>
        </div>

        <div class="field">
          <label for="exDate">Date</label>
          <input class="input" type="date" id="exDate" value="<?= date('Y-m-d') ?>">
        </div>

        <div class="field field--wide">
          <label id="exMethLbl">Payment method <span class="req">*</span></label>
          <div class="iconcards iconcards--wide" role="radiogroup" aria-labelledby="exMethLbl">
            <?php foreach ($payment_methods as $k => $m): ?>
              <label class="iconcard">
                <input type="radio" name="exMeth" value="<?= htmlspecialchars($m['key']) ?>"
                       data-needs-ref="<?= !empty($m['needs_reference']) ? '1' : '0' ?>" <?= $k === 0 ? 'checked' : '' ?>>
                <span class="iconcard__box">
                  <i class="fa-solid <?= htmlspecialchars($m['icon']) ?>" aria-hidden="true"></i>
                  <span><?= htmlspecialchars($m['name']) ?></span>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Only shown for the methods that actually produce a reference. -->
        <div class="field" id="exTxnField" hidden>
          <label for="exTxn">Transaction reference <span class="req">*</span></label>
          <input class="input" type="text" id="exTxn" placeholder="e.g. MP260828.1042.A88213" autocomplete="off">
        </div>

        <div class="field">
          <label for="exPayee">Paid to <span class="req">*</span></label>
          <input class="input" type="text" id="exPayee" placeholder="Vendor or payee" autocomplete="off" list="payeeList">
          <datalist id="payeeList">
            <?php foreach (array_values(array_unique(array_column($rows, 'payee'))) as $p): ?>
              <option value="<?= htmlspecialchars($p) ?>"></option>
            <?php endforeach; ?>
          </datalist>
        </div>

        <?php if ($show_branch): ?>
          <div class="field">
            <label for="exBranch"><?= htmlspecialchars(t('branch_singular')) ?></label>
            <select class="select" id="exBranch">
              <option value="">Whole <?= htmlspecialchars(t('org_singular')) ?></option>
              <?php foreach ($branch_options as $b): ?>
                <option value="<?= (int) $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <?php if ($has_budgets): ?>
          <div class="field">
            <label for="exBudget">Budget line</label>
            <select class="select" id="exBudget">
              <option value="">Not against a budget</option>
              <?php foreach ($expense_categories as $c): ?>
                <?php if (empty($expense_budgets[$c['key']])) { continue; } ?>
                <option value="<?= htmlspecialchars($c['key']) ?>">
                  <?= htmlspecialchars($c['name']) ?> &mdash; $<?= number_format((float) $expense_budgets[$c['key']], 0) ?>/month
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <div class="field field--wide">
          <label for="exNotes">Justification</label>
          <textarea class="textarea" id="exNotes" rows="3" placeholder="Why this was necessary. Whoever approves it reads this."></textarea>
        </div>

        <div class="field field--wide">
          <label for="exReceipt">Receipt</label>
          <label class="dropzone" for="exReceipt" id="exDrop">
            <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
            <span><strong>Drop a receipt here</strong></span>
            <span class="hint" style="margin:0">or click to choose. JPG, PNG or PDF, up to 5&nbsp;MB.</span>
          </label>
          <input class="offscreen" type="file" id="exReceipt" accept="image/png,image/jpeg,application/pdf">
          <div class="filepreview" data-ex-file hidden>
            <span class="filepreview__ico" aria-hidden="true"><i class="fa-regular fa-file-image"></i></span>
            <span class="filepreview__text"><b data-ex-filename>receipt.jpg</b><span data-ex-filesize>&mdash;</span></span>
            <button class="iconbtn" type="button" id="exDropFile" aria-label="Remove this receipt"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>
          </div>
        </div>
      </div>
    </div>

    <footer class="modal__foot modal__foot--split">
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <div class="modal__footgroup">
        <button class="btn btn--ghost" type="button" id="exDraft"><i class="fa-solid fa-pen-ruler" aria-hidden="true"></i> Save as Draft</button>
        <button class="btn" type="button" id="exSubmit"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Submit for Approval</button>
      </div>
    </footer>
  </div>
</div>
<?php endif; ?>


<?php if ($can_approve): ?>
<!-- ══════════════════════════ APPROVE ══════════════════════════ -->
<div class="modal-scrim" id="modalApprove" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="apTitle">
    <header class="modal__head">
      <h2 id="apTitle">Approve Expense</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>

    <div class="modal__body">
      <div class="exsummary" data-ap-summary></div>

      <?php if ($has_budgets): ?>
        <p class="budgetline" data-ap-budget hidden>
          <i class="fa-solid fa-scale-balanced" aria-hidden="true"></i>
          <span></span>
        </p>
      <?php endif; ?>

      <div class="field">
        <label for="apNotes">Approval notes</label>
        <textarea class="textarea" id="apNotes" rows="3" placeholder="Anything the record should carry alongside your signature."></textarea>
      </div>
    </div>

    <footer class="modal__foot">
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <button class="btn btn--ok" type="button" id="apGo"><i class="fa-solid fa-check" aria-hidden="true"></i> Approve Expense</button>
    </footer>
  </div>
</div>


<!-- ══════════════════════════ REJECT ══════════════════════════ -->
<div class="modal-scrim" id="modalReject" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="rjTitle">
    <header class="modal__head">
      <h2 id="rjTitle">Reject Expense</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>

    <div class="modal__body">
      <div class="exsummary" data-rj-summary></div>

      <div class="field">
        <label for="rjReason">Reason <span class="req">*</span></label>
        <select class="select" id="rjReason">
          <option value="">Choose a reason&hellip;</option>
          <option value="Insufficient budget">Insufficient budget</option>
          <option value="Missing receipt">Missing receipt</option>
          <option value="Not authorised">Not authorised</option>
          <option value="Duplicate">Duplicate</option>
          <option value="Other">Other</option>
        </select>
      </div>

      <div class="field">
        <label for="rjNotes">Notes</label>
        <textarea class="textarea" id="rjNotes" rows="3" placeholder="What the requester needs to do differently."></textarea>
      </div>

      <?php if (mu_mod('communication')): ?>
        <label class="switchrow" for="rjNotify">
          <span class="switch"><input type="checkbox" id="rjNotify" checked><span class="switch__track" aria-hidden="true"></span></span>
          <span class="switchrow__text">
            <b>Notify the requester</b>
            <small>Sends them the reason and your notes.</small>
          </span>
        </label>
      <?php endif; ?>
    </div>

    <footer class="modal__foot">
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <button class="btn btn--danger" type="button" id="rjGo" disabled><i class="fa-solid fa-xmark" aria-hidden="true"></i> Reject Expense</button>
    </footer>
  </div>
</div>
<?php endif; ?>


<!-- ══════════════════════════ RECEIPT VIEWER ══════════════════════════ -->
<div class="modal-scrim" id="modalReceipt" hidden>
  <div class="modal modal--wide" role="dialog" aria-modal="true" aria-labelledby="rcTitle">
    <header class="modal__head">
      <h2 id="rcTitle">Receipt</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>

    <div class="modal__body">
      <p class="modal__hint" data-rc-for>&mdash;</p>

      <div class="viewer">
        <!-- A scanned receipt stands in for the uploaded file. -->
        <div class="viewer__stage" data-rc-stage>
          <div class="viewer__paper" data-rc-paper>
            <p class="viewer__brand" data-rc-payee>Vendor</p>
            <p class="viewer__line"><span>Receipt no.</span><span data-rc-no>&mdash;</span></p>
            <p class="viewer__line"><span>Date</span><span data-rc-date>&mdash;</span></p>
            <p class="viewer__line"><span>Description</span><span data-rc-desc>&mdash;</span></p>
            <p class="viewer__line"><span>Method</span><span data-rc-method>&mdash;</span></p>
            <p class="viewer__rule" aria-hidden="true"></p>
            <p class="viewer__total"><span>Total</span><span data-rc-amount>$0.00</span></p>
            <p class="viewer__foot">Thank you for your custom</p>
          </div>
        </div>
      </div>

      <div class="viewer__bar">
        <button class="iconbtn iconbtn--bordered" type="button" data-zoom="out" aria-label="Zoom out"><i class="fa-solid fa-magnifying-glass-minus" aria-hidden="true"></i></button>
        <span class="viewer__pct" data-rc-zoom>100%</span>
        <button class="iconbtn iconbtn--bordered" type="button" data-zoom="in" aria-label="Zoom in"><i class="fa-solid fa-magnifying-glass-plus" aria-hidden="true"></i></button>
        <span class="bulkbar__sep" aria-hidden="true"></span>
        <button class="iconbtn iconbtn--bordered" type="button" data-zoom="rotate" aria-label="Rotate"><i class="fa-solid fa-rotate-right" aria-hidden="true"></i></button>
        <button class="iconbtn iconbtn--bordered" type="button" data-zoom="reset" aria-label="Reset view"><i class="fa-solid fa-arrows-to-dot" aria-hidden="true"></i></button>
        <span class="bulkbar__sep" aria-hidden="true"></span>
        <button class="btn btn--ghost btn--sm" type="button" data-toast="Receipt downloaded"><i class="fa-solid fa-download" aria-hidden="true"></i> Download</button>
      </div>
    </div>

    <footer class="modal__foot">
      <button class="btn btn--ghost" type="button" data-close>Close</button>
    </footer>
  </div>
</div>


<?php if ($can_delete): ?>
<!-- ══════════════════════════ DELETE ══════════════════════════ -->
<div class="modal-scrim" id="modalDelete" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="dlTitle">
    <header class="modal__head">
      <h2 id="dlTitle">Delete Expense</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>

    <div class="modal__body">
      <div class="at-notice at-notice--danger" role="note" style="margin-bottom:14px">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        <div class="at-notice__body">
          <strong>This removes a financial record</strong>
          <span>Deleting <b data-dl-what>this expense</b> changes your recorded totals and its approval trail. Consider rejecting it instead.</span>
        </div>
      </div>

      <div class="field">
        <label for="dlReason">Reason for deletion <span class="req">*</span></label>
        <textarea class="textarea" id="dlReason" rows="2" placeholder="Why is this record being removed?"></textarea>
      </div>

      <div class="field">
        <label for="dlConfirm">Type the amount <strong data-dl-amount>0.00</strong> to confirm</label>
        <input class="input" type="text" id="dlConfirm" placeholder="0.00" autocomplete="off">
      </div>
    </div>

    <footer class="modal__foot">
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <button class="btn btn--danger" type="button" id="dlGo" disabled><i class="fa-regular fa-trash-can" aria-hidden="true"></i> Delete Permanently</button>
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
/* What the drawer, the modals and the charts read. Assembled here so the
   browser is handed finished figures rather than recomputing what PHP already
   worked out. LATER: these become endpoints. */
$JS_ROWS = [];
foreach ($rows as $r) {
    $JS_ROWS[] = [
        'id' => $r['id'], 'ref' => $r['ref'], 'desc' => $r['description'],
        'cat' => $r['category'], 'catName' => $r['cat_name'],
        'catIcon' => $r['cat_icon'], 'catColour' => $r['cat_colour'],
        'amount' => (float) $r['amount'], 'currency' => $r['currency'],
        'sym' => fin_sym($r['currency']), 'usd' => $r['usd'],
        'rate' => (float) ($cur_by_code[$r['currency']]['exchange_rate_to_usd'] ?? 1),
        'method' => $r['method'], 'methodName' => $r['meth_name'], 'methodIcon' => $r['meth_icon'],
        'txn' => $r['txn'], 'payee' => $r['payee'],
        'by' => $r['by'], 'approvedBy' => $r['approved_by'],
        'status' => $r['status'], 'statusLabel' => $r['status_label'],
        'receipt' => (bool) $r['receipt'], 'notes' => $r['notes'],
        'date' => mu_date($r['date'], 'd M Y'), 'daysAgo' => (int) $r['days_ago'],
        'ago' => mu_ago((int) $r['days_ago']),
        'branch' => $r['_branch']['name'] ?? null,
        'used' => $has_budgets ? ($cat_used[$r['category']] ?? null) : null,
        'budget' => $has_budgets ? (float) ($expense_budgets[$r['category']] ?? 0) : null,
    ];
}
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function () {
  'use strict';

  var $  = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

  var ROWS = <?= json_encode($JS_ROWS, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  var byId = {};
  ROWS.forEach(function (r) { byId[r.id] = r; });

  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function fmt(n) { return Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function usd(n) { return '$' + fmt(n); }
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
  /* Scoped to the strip and the header chip. An unscoped [data-count] would
     also match a row using the attribute as data and wipe its cells. */
  $$('.stat-tile [data-count], .count-chip[data-count]').forEach(function (el) {
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
    ['all', 'queue', 'cat'].forEach(function (n) {
      var panel = $('#panel-' + n);
      if (panel) { panel.hidden = n !== name; }
    });
    if (name === 'cat') { drawCharts(); }
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

  /* the mobile cards expand in place */
  $$('[data-card-toggle]').forEach(function (b) {
    b.addEventListener('click', function () {
      var open = b.closest('.pcard').classList.toggle('is-open');
      b.setAttribute('aria-expanded', String(open));
    });
  });

  /* ═══════════════════════════ filtering ═══════════════════════════ */
  var search  = $('#fSearch');
  var chipBox = $('[data-filter-chips]');
  var emptyEl = $('#listEmpty');

  var FILTER_LABEL = {
    fFrom: 'From', fTo: 'To', fCategory: 'Category', fStatus: 'Status',
    fMethod: 'Method', fCurrency: 'Currency', fMin: 'Min', fMax: 'Max',
    fBy: 'Requested by', fApprover: 'Approved by', fReceipt: 'Receipt'<?php if ($show_branch): ?>,
    fBranch: '<?= addslashes(t('branch_singular')) ?>'<?php endif; ?>
  };

  function activeFilters() {
    var f = { q: (search && search.value || '').trim().toLowerCase() };
    $$('[data-filter]').forEach(function (el) { f[el.id] = el.value; });
    return f;
  }

  function matches(el, f) {
    if (f.q) {
      var hay = [el.getAttribute('data-desc'), el.getAttribute('data-ref'),
                 el.getAttribute('data-payee'), el.getAttribute('data-amount'),
                 el.getAttribute('data-usd')].join(' ');
      if (hay.indexOf(f.q) === -1) { return false; }
    }
    var d = el.getAttribute('data-date');
    if (f.fFrom && d && d < f.fFrom) { return false; }
    if (f.fTo   && d && d > f.fTo)   { return false; }

    if (f.fCategory && el.getAttribute('data-category') !== f.fCategory) { return false; }
    if (f.fStatus   && el.getAttribute('data-status')   !== f.fStatus)   { return false; }
    if (f.fMethod   && el.getAttribute('data-method')   !== f.fMethod)   { return false; }
    if (f.fCurrency && el.getAttribute('data-currency') !== f.fCurrency) { return false; }
    if (f.fBy       && el.getAttribute('data-by')       !== f.fBy)       { return false; }
    if (f.fApprover && el.getAttribute('data-approver') !== f.fApprover) { return false; }
    if (f.fReceipt  && el.getAttribute('data-receipt')  !== f.fReceipt)  { return false; }

    var u = parseFloat(el.getAttribute('data-usd'));
    if (f.fMin && !isNaN(u) && u < parseFloat(f.fMin)) { return false; }
    if (f.fMax && !isNaN(u) && u > parseFloat(f.fMax)) { return false; }

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
      var shown = el.tagName === 'SELECT' ? el.options[el.selectedIndex].text : el.value;
      live.push([el.id, FILTER_LABEL[el.id] || el.id, shown]);
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

  /* The totals row is the point of the filter bar — it has to reflect exactly
     what is on screen, per currency and as one converted figure. */
  function paintTotals() {
    var byCur = {}, total = 0, n = 0;
    $$('#expTable tr[data-row]').forEach(function (tr) {
      if (tr.hidden) { return; }
      n++;
      var code = tr.getAttribute('data-currency');
      byCur[code] = (byCur[code] || 0) + parseFloat(tr.getAttribute('data-amount'));
      total += parseFloat(tr.getAttribute('data-usd'));
    });
    $('[data-totals-count]').textContent = n;
    $('[data-totals-usd]').textContent = usd(total);
    var box = $('[data-totals-cur]');
    box.innerHTML = '';
    var syms = { USD: '$', ZWG: 'ZWG', ZAR: 'R', GBP: '£' };
    Object.keys(byCur).sort().forEach(function (c) {
      var s = document.createElement('span');
      s.className = 'totalsrow__chip';
      s.textContent = (syms[c] || '$') + fmt(byCur[c]) + ' ' + c;
      box.appendChild(s);
    });
  }

  function apply() {
    var f = activeFilters(), shown = 0;
    $$('[data-row], [data-card]').forEach(function (el) {
      var ok = matches(el, f);
      el.hidden = !ok;
      if (ok && el.hasAttribute('data-row')) { shown++; }
    });
    var i = 0;
    $$('[data-row]').forEach(function (r) {
      if (r.hidden) { return; }
      var c = $('.num', r);
      if (c) { c.textContent = ++i; }
    });
    if (emptyEl) { emptyEl.hidden = shown !== 0; }
    paintChips(f);
    paintTotals();
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
    /* A number or date field only fires change on blur, which leaves the
       table stale while somebody is still typing a minimum. */
    if (el.tagName === 'INPUT') { el.addEventListener('input', apply); }
  });
  $$('[data-reset-filters]').forEach(function (b) {
    b.addEventListener('click', function () {
      if (search) { search.value = ''; }
      $$('[data-filter]').forEach(function (el) { el.value = ''; });
      $$('[data-preset]').forEach(function (o) { o.classList.remove('is-on'); });
      if (clearBtn) { clearBtn.hidden = true; }
      apply();
      toast('Filters cleared', 'info');
    });
  });

  /* date presets */
  /* Built from the local parts, not toISOString — that converts to UTC and
     drags the first of the month back into the previous one. */
  function iso(d) {
    var m = String(d.getMonth() + 1).padStart(2, '0');
    var day = String(d.getDate()).padStart(2, '0');
    return d.getFullYear() + '-' + m + '-' + day;
  }
  $$('[data-preset]').forEach(function (b) {
    b.addEventListener('click', function () {
      var now = new Date(), from, to = new Date();
      switch (b.getAttribute('data-preset')) {
        case 'week':    from = new Date(now); from.setDate(now.getDate() - now.getDay()); break;
        case 'month':   from = new Date(now.getFullYear(), now.getMonth(), 1); break;
        case 'last':    from = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                        to   = new Date(now.getFullYear(), now.getMonth(), 0); break;
        case 'quarter': from = new Date(now.getFullYear(), Math.floor(now.getMonth() / 3) * 3, 1); break;
        default:        from = new Date(now.getFullYear(), 0, 1);
      }
      $('#fFrom').value = iso(from);
      $('#fTo').value   = iso(to);
      $$('[data-preset]').forEach(function (o) { o.classList.toggle('is-on', o === b); });
      apply();
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
  $$('#expTable th.is-sortable').forEach(function (th) {
    th.addEventListener('click', function () {
      var key = th.getAttribute('data-sort');
      /* aria-sort still holds the previous state, so the direction has to be
         read from where the click is taking the column. */
      var desc = th.getAttribute('aria-sort') === 'descending';
      var dir  = desc ? -1 : 1;

      $$('#expTable th').forEach(function (o) { o.removeAttribute('aria-sort'); });
      th.setAttribute('aria-sort', desc ? 'ascending' : 'descending');

      var numeric = key === 'usd';
      var body = $('#expTable tbody');
      var rows = $$('tr', body);
      rows.sort(function (a, b) {
        var x = a.getAttribute('data-' + key) || '';
        var y = b.getAttribute('data-' + key) || '';
        if (numeric) { return (parseFloat(y) - parseFloat(x)) * dir; }
        return y.localeCompare(x) * dir;
      });
      rows.forEach(function (r) { body.appendChild(r); });
      apply();
    });
  });

  /* ──────────────────────── bulk selection ──────────────────────── */
  var bulkBar = $('#bulkBar');
  function selected() {
    return $$('#expTable tbody [data-check]').filter(function (c) { return c.checked && !c.closest('tr').hidden; });
  }
  function paintBulk() {
    if (!bulkBar) { return; }
    var n = selected().length;
    bulkBar.hidden = n === 0;
    var c = $('[data-bulk-count]');
    if (c) { c.textContent = n; }
    document.body.classList.toggle('has-bulkbar', n > 0);
    var all = $('[data-check-all]');
    if (all) {
      var boxes = $$('#expTable tbody [data-check]').filter(function (b) { return !b.closest('tr').hidden; });
      all.checked = boxes.length > 0 && n === boxes.length;
      all.indeterminate = n > 0 && n < boxes.length;
    }
  }
  $$('#expTable [data-check]').forEach(function (c) { c.addEventListener('change', paintBulk); });
  var checkAll = $('[data-check-all]');
  if (checkAll) {
    checkAll.addEventListener('change', function () {
      $$('#expTable tbody [data-check]').forEach(function (c) {
        if (!c.closest('tr').hidden) { c.checked = checkAll.checked; }
      });
      paintBulk();
    });
  }
  var bulkClose = $('#bulkClose');
  if (bulkClose) {
    bulkClose.addEventListener('click', function () {
      $$('#expTable [data-check]').forEach(function (c) { c.checked = false; });
      paintBulk();
    });
  }
  [['#bulkApprove', 'approved', 'success'], ['#bulkReject', 'rejected', 'error'],
   ['#bulkPaid', 'marked paid', 'success'], ['#bulkDelete', 'deleted', 'error']].forEach(function (row) {
    var b = $(row[0]);
    if (b) {
      b.addEventListener('click', function () {
        var n = selected().length;
        toast(n + ' expense' + (n === 1 ? '' : 's') + ' ' + row[1], row[2]);
      });
    }
  });

  /* ═════════════════════════════ the drawer ═════════════════════════════ */
  var scrim = $('[data-drawer-scrim]');
  var expD  = $('#expDrawer');
  var lastFocus = null;
  var current = null;

  function openDrawer() {
    lastFocus = document.activeElement;
    expD.hidden = false; scrim.hidden = false;
    document.body.style.overflow = 'hidden';
    $('[data-drawer-close]', expD).focus();
  }
  function closeDrawers() {
    expD.hidden = true; scrim.hidden = true;
    document.body.style.overflow = '';
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

  function openExpense(id) {
    var r = byId[id];
    if (!r) { return; }
    current = r;

    $('#dTitle').textContent = r.catName;
    $('[data-d-ref]').textContent = r.ref;
    var ci = $('[data-d-catico]');
    ci.style.setProperty('--c', r.catColour);
    ci.innerHTML = '<i class="fa-solid ' + r.catIcon + '"></i>';

    $('[data-d-amount]').textContent = r.sym + fmt(r.amount);
    var u = $('[data-d-usd]');
    if (r.currency === 'USD') { u.hidden = true; }
    else { u.hidden = false; u.textContent = '≈ ' + usd(r.usd) + '  ·  1 ' + r.currency + ' = $' + r.rate; }

    var cb = $('[data-d-cat]');
    cb.style.setProperty('--c', r.catColour);
    cb.textContent = r.catName;

    $('[data-d-desc]').textContent = r.desc;
    $('[data-d-status]').innerHTML = '<span class="pill pill--ex-' + r.status + '">' + esc(r.statusLabel) + '</span>';
    $('[data-d-date]').textContent   = r.date + ' · ' + r.ago;
    $('[data-d-payee]').textContent  = r.payee;
    $('[data-d-method]').textContent = r.methodName;
    $('[data-d-txnrow]').hidden = !r.txn;
    $('[data-d-txn]').textContent = r.txn || '—';
    var bEl = $('[data-d-branch]');
    if (bEl) { bEl.textContent = r.branch || '—'; }
    var budEl = $('[data-d-budget]');
    if (budEl) { budEl.textContent = r.budget ? r.catName + ' — ' + usd(r.budget) + '/month' : 'Not against a budget'; }
    $('[data-d-notes]').textContent = r.notes || 'No justification was recorded.';

    /* Requested → approved → paid, with whoever did each step. */
    var tl = $('[data-d-timeline]');
    tl.innerHTML = '';
    function step(title, who, when, state) {
      var li = document.createElement('li');
      li.className = 'is-' + state;
      li.innerHTML = '<span class="audit__dot" aria-hidden="true"></span>'
        + '<span class="audit__text"><b>' + esc(title) + '</b>'
        + '<span>' + esc(who) + '</span>'
        + '<span>' + esc(when) + '</span></span>';
      tl.appendChild(li);
    }
    step('Requested', r.by, r.date, 'done');
    if (r.status === 'draft') {
      step('Not yet submitted', 'Still a draft', 'Awaiting the requester', 'todo');
    } else if (r.status === 'rejected') {
      step('Rejected', 'Returned to ' + r.by, r.ago, 'bad');
    } else if (r.approvedBy) {
      step('Approved', r.approvedBy, r.ago, 'done');
      if (r.status === 'paid') { step('Paid', 'Settled in full', r.ago, 'done'); }
      else { step('Payment', 'Not yet paid', 'Awaiting the treasurer', 'todo'); }
    } else {
      step('Approval', 'Waiting ' + r.daysAgo + ' day' + (r.daysAgo === 1 ? '' : 's'), 'Nobody has signed this off', 'todo');
      step('Payment', 'Not yet paid', 'Blocked by approval', 'todo');
    }

    var rc = $('[data-d-receipt]');
    rc.innerHTML = '';
    if (r.receipt) {
      var btn = document.createElement('button');
      btn.className = 'thumb thumb--row';
      btn.type = 'button';
      btn.setAttribute('data-viewreceipt', r.id);
      btn.innerHTML = '<span class="thumb__img" aria-hidden="true"><i class="fa-regular fa-file-image"></i></span>'
                    + '<span class="thumb__cap">Receipt attached<small>Click to open the viewer</small></span>';
      rc.appendChild(btn);
    } else {
      rc.innerHTML = '<p class="hint">No receipt was attached to this expense.</p>';
    }

    /* Approving only makes sense while it is still pending. */
    ['#dApprove', '#dReject'].forEach(function (sel) {
      var b = $(sel);
      if (b) { b.hidden = r.status !== 'pending'; }
    });

    openDrawer();
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-open]');
    if (b) { closeOwnMenu(b); openExpense(b.getAttribute('data-open')); }
  }, true);

  /* ═════════════════════════════ modals ═════════════════════════════ */
  function openModal(m) { m.hidden = false; document.body.style.overflow = 'hidden'; var c = $('[data-close]', m); if (c) { c.focus(); } }
  function closeModal(m) {
    m.hidden = true;
    if ($$('.modal-scrim').every(function (x) { return x.hidden; }) && expD.hidden) {
      document.body.style.overflow = '';
    }
  }
  document.addEventListener('click', function (e) {
    var cl = e.target.closest('[data-close]');
    if (cl) { closeModal(cl.closest('.modal-scrim')); return; }
    if (e.target.classList.contains('modal-scrim')) { closeModal(e.target); }
  });

  /* A one-line summary of what is being decided, shared by both decisions. */
  function summary(r) {
    return '<span class="catico" style="--c:' + r.catColour + '" aria-hidden="true"><i class="fa-solid ' + r.catIcon + '"></i></span>'
      + '<span class="exsummary__text"><b>' + esc(r.desc) + '</b>'
      + '<span>' + esc(r.catName) + ' · ' + esc(r.payee) + ' · requested by ' + esc(r.by) + '</span></span>'
      + '<span class="exsummary__amt">' + r.sym + fmt(r.amount)
      + (r.currency !== 'USD' ? '<small>≈ ' + usd(r.usd) + '</small>' : '') + '</span>';
  }

  /* ── the add / edit form ── */
  var exModal = $('#modalExpense');
  if (exModal) {
    function syncTxn() {
      var picked = $('input[name="exMeth"]:checked');
      $('#exTxnField').hidden = !picked || picked.getAttribute('data-needs-ref') !== '1';
    }
    $$('input[name="exMeth"]').forEach(function (r) { r.addEventListener('change', syncTxn); });

    function syncUsd() {
      var RATES = { USD: 1, ZWG: 0.0372, ZAR: 0.0545, GBP: 1.27 };
      var v = parseFloat(String($('#exAmount').value).replace(/[^0-9.]/g, ''));
      var code = $('#exCurrency').value;
      var p = $('[data-ex-usd]');
      if (!v || code === 'USD') { p.hidden = true; return; }
      p.hidden = false;
      p.textContent = '≈ ' + usd(v * RATES[code]) + ' at 1 ' + code + ' = $' + RATES[code];
    }
    $('#exAmount').addEventListener('input', syncUsd);
    $('#exCurrency').addEventListener('change', syncUsd);

    function openForm(r) {
      $('#exTitle').textContent = r ? 'Edit Expense' : 'Record an Expense';
      $('#exDesc').value   = r ? r.desc : '';
      $('#exAmount').value = r ? String(r.amount) : '';
      $('#exPayee').value  = r ? r.payee : '';
      $('#exNotes').value  = r ? r.notes : '';
      $('#exTxn').value    = r ? r.txn : '';
      if (r) {
        $('#exCurrency').value = r.currency;
        var c = $('input[name="exCat"][value="' + r.cat + '"]');
        if (c) { c.checked = true; }
        var m = $('input[name="exMeth"][value="' + r.method + '"]');
        if (m) { m.checked = true; }
        var bud = $('#exBudget');
        if (bud) { bud.value = r.budget ? r.cat : ''; }
      }
      syncTxn(); syncUsd();
      dropFile();
      openModal(exModal);
    }

    var nb = $('#btnNewExpense');
    if (nb) { nb.addEventListener('click', function () { openForm(null); }); }
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-edit]');
      if (b) { closeOwnMenu(b); openForm(byId[b.getAttribute('data-edit')]); }
    }, true);
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-duplicate]');
      if (b) {
        closeOwnMenu(b);
        var r = byId[b.getAttribute('data-duplicate')];
        openForm(r);
        $('#exTitle').textContent = 'Record an Expense';
        toast('Copied from ' + r.ref + ' — check the date and amount', 'info');
      }
    }, true);
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-attach]');
      if (b) { closeOwnMenu(b); openForm(byId[b.getAttribute('data-attach')]); $('#exReceipt').click(); }
    }, true);
    var de = $('#dEdit');
    if (de) { de.addEventListener('click', function () { if (current) { openForm(current); } }); }

    /* receipt upload, click or drop */
    var file = $('#exReceipt'), drop = $('#exDrop');
    function showFile(f) {
      if (!f) { return; }
      $('[data-ex-filename]').textContent = f.name;
      $('[data-ex-filesize]').textContent = (f.size / 1024).toFixed(0) + ' KB';
      $('[data-ex-file]').hidden = false;
      drop.hidden = true;
    }
    function dropFile() {
      file.value = '';
      $('[data-ex-file]').hidden = true;
      drop.hidden = false;
      drop.classList.remove('is-over');
    }
    file.addEventListener('change', function () { showFile(file.files && file.files[0]); });
    $('#exDropFile').addEventListener('click', dropFile);
    ['dragenter', 'dragover'].forEach(function (ev) {
      drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('is-over'); });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
      drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('is-over'); });
    });
    drop.addEventListener('drop', function (e) {
      if (e.dataTransfer && e.dataTransfer.files.length) { showFile(e.dataTransfer.files[0]); }
    });

    function validate() {
      if (!$('#exDesc').value.trim()) { toast('The expense needs a description', 'error'); $('#exDesc').focus(); return false; }
      if (!parseFloat(String($('#exAmount').value).replace(/[^0-9.]/g, ''))) { toast('Enter an amount', 'error'); $('#exAmount').focus(); return false; }
      if (!$('#exPayee').value.trim()) { toast('Say who was paid', 'error'); $('#exPayee').focus(); return false; }
      if (!$('#exTxnField').hidden && !$('#exTxn').value.trim()) {
        toast('That payment method needs a reference', 'error'); $('#exTxn').focus(); return false;
      }
      return true;
    }
    $('#exDraft').addEventListener('click', function () {
      if (!validate()) { return; }
      closeModal(exModal); toast('Saved as a draft', 'info');
    });
    $('#exSubmit').addEventListener('click', function () {
      if (!validate()) { return; }
      closeModal(exModal); toast('Submitted for approval', 'success');
    });
  }

  /* ── approve ── */
  var apModal = $('#modalApprove');
  if (apModal) {
    var apRow = null;
    function openApprove(id) {
      var r = byId[id];
      if (!r) { return; }
      apRow = r;
      $('[data-ap-summary]').innerHTML = summary(r);
      var b = $('[data-ap-budget]');
      if (b) {
        if (r.used === null || r.used === undefined) { b.hidden = true; }
        else {
          b.hidden = false;
          b.classList.toggle('is-over', r.used > 100);
          b.classList.toggle('is-tight', r.used > 85 && r.used <= 100);
          $('span', b).innerHTML = 'The <b>' + esc(r.catName) + '</b> line is <b>'
            + Math.round(r.used) + '%</b> utilised'
            + (r.used > 100 ? ' — approving this takes it further over budget.' : '.');
        }
      }
      $('#apNotes').value = '';
      openModal(apModal);
    }
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-approve]');
      if (b) { closeOwnMenu(b); openApprove(b.getAttribute('data-approve')); }
    }, true);
    var da = $('#dApprove');
    if (da) { da.addEventListener('click', function () { if (current) { openApprove(current.id); } }); }
    $('#apGo').addEventListener('click', function () {
      closeModal(apModal);
      toast((apRow ? apRow.ref : 'The expense') + ' approved', 'success');
    });
  }

  /* ── reject ── */
  var rjModal = $('#modalReject');
  if (rjModal) {
    var rjRow = null;
    function openReject(id) {
      var r = byId[id];
      if (!r) { return; }
      rjRow = r;
      $('[data-rj-summary]').innerHTML = summary(r);
      $('#rjReason').value = '';
      $('#rjNotes').value = '';
      $('#rjGo').disabled = true;
      openModal(rjModal);
    }
    /* A rejection without a reason is useless to whoever has to fix it. */
    $('#rjReason').addEventListener('change', function () {
      $('#rjGo').disabled = !$('#rjReason').value;
    });
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-reject]');
      if (b) { closeOwnMenu(b); openReject(b.getAttribute('data-reject')); }
    }, true);
    var dr = $('#dReject');
    if (dr) { dr.addEventListener('click', function () { if (current) { openReject(current.id); } }); }
    $('#rjGo').addEventListener('click', function () {
      closeModal(rjModal);
      toast((rjRow ? rjRow.ref : 'The expense') + ' rejected — ' + $('#rjReason').value.toLowerCase(), 'error');
    });
  }

  /* ── the receipt viewer ── */
  var rcModal = $('#modalReceipt');
  var zoom = 100, rot = 0;
  function paintView() {
    var paper = $('[data-rc-paper]');
    paper.style.transform = 'scale(' + (zoom / 100) + ') rotate(' + rot + 'deg)';
    $('[data-rc-zoom]').textContent = zoom + '%';
  }
  $$('[data-zoom]').forEach(function (b) {
    b.addEventListener('click', function () {
      var what = b.getAttribute('data-zoom');
      if (what === 'in')     { zoom = Math.min(220, zoom + 20); }
      if (what === 'out')    { zoom = Math.max(50,  zoom - 20); }
      if (what === 'rotate') { rot = (rot + 90) % 360; }
      if (what === 'reset')  { zoom = 100; rot = 0; }
      paintView();
    });
  });
  function openReceipt(id) {
    var r = byId[id];
    if (!r) { return; }
    $('[data-rc-for]').textContent = r.desc + ' — ' + r.ref;
    $('[data-rc-payee]').textContent  = r.payee;
    $('[data-rc-no]').textContent     = r.txn || r.ref;
    $('[data-rc-date]').textContent   = r.date;
    $('[data-rc-desc]').textContent   = r.desc;
    $('[data-rc-method]').textContent = r.methodName;
    $('[data-rc-amount]').textContent = r.sym + fmt(r.amount);
    zoom = 100; rot = 0; paintView();
    openModal(rcModal);
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-viewreceipt]');
    if (b) { closeOwnMenu(b); openReceipt(b.getAttribute('data-viewreceipt')); }
  }, true);

  /* ── vouchers and printing ── */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-voucher]');
    if (b) { closeOwnMenu(b); toast('Voucher ' + byId[b.getAttribute('data-voucher')].ref + ' sent to printer', 'success'); }
  }, true);
  var dv = $('#dVoucher');
  if (dv) { dv.addEventListener('click', function () { if (current) { toast('Voucher ' + current.ref + ' sent to printer', 'success'); } }); }
  $('#btnPrint').addEventListener('click', function () { window.print(); });

  /* ── delete ── */
  var dlModal = $('#modalDelete');
  if (dlModal) {
    var dlRow = null;
    function openDelete(id) {
      var r = byId[id];
      if (!r) { return; }
      dlRow = r;
      $('[data-dl-what]').textContent = r.sym + fmt(r.amount) + ' paid to ' + r.payee;
      $('[data-dl-amount]').textContent = fmt(r.amount);
      $('#dlReason').value = '';
      $('#dlConfirm').value = '';
      $('#dlGo').disabled = true;
      openModal(dlModal);
    }
    /* Both the reason and the typed amount, because a deletion cannot be undone. */
    function gate() {
      $('#dlGo').disabled = !$('#dlReason').value.trim()
        || $('#dlConfirm').value.trim() !== fmt(dlRow ? dlRow.amount : 0);
    }
    $('#dlReason').addEventListener('input', gate);
    $('#dlConfirm').addEventListener('input', gate);
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-delete]');
      if (b) { closeOwnMenu(b); openDelete(b.getAttribute('data-delete')); }
    }, true);
    $('#dlGo').addEventListener('click', function () {
      closeModal(dlModal);
      toast((dlRow ? dlRow.ref : 'The expense') + ' deleted', 'error');
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

  var CATS = <?= json_encode(array_map(static fn($c) => [
        'key' => $c['key'], 'name' => $c['name'], 'colour' => $c['colour'],
        'total' => round($c['total'], 2),
        'trend' => array_map('floatval', $expense_trend_demo[$c['key']] ?? []),
      ], $by_cat), JSON_UNESCAPED_UNICODE) ?>;
  var TREND_LABELS = <?= json_encode($trend_labels) ?>;

  var charts = {}, drawn = false;
  function drawCharts() {
    if (drawn || !window.Chart) { return; }
    drawn = true;

    charts.cat = new Chart($('#catChart'), {
      type: 'doughnut',
      data: {
        labels: CATS.map(function (c) { return c.name; }),
        datasets: [{ data: CATS.map(function (c) { return c.total; }),
                     backgroundColor: CATS.map(function (c) { return c.colour; }), borderWidth: 0 }]
      },
      options: {
        responsive: true, maintainAspectRatio: false, cutout: '58%',
        plugins: {
          legend: { display: true, position: 'bottom', labels: { boxWidth: 10, padding: 10, usePointStyle: true } },
          tooltip: { callbacks: { label: function (c) {
            var total = CATS.reduce(function (a, b) { return a + b.total; }, 0);
            var pct = total ? ((c.parsed / total) * 100).toFixed(1) : 0;
            return c.label + ': ' + usd(c.parsed) + ' (' + pct + '%)';
          } } }
        }
      }
    });

    charts.trend = new Chart($('#trendChart'), {
      type: 'line',
      data: { labels: TREND_LABELS, datasets: [trendSet(CATS[0])] },
      options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: function (c) { return c.dataset.label + ': ' + usd(c.parsed.y); } } }
        },
        scales: {
          x: { grid: { display: false } },
          y: { grid: { color: GRID }, beginAtZero: true, ticks: { callback: function (v) { return '$' + v.toLocaleString(); } } }
        }
      }
    });
  }
  function trendSet(c) {
    return {
      label: c.name, data: c.trend,
      borderColor: c.colour, backgroundColor: c.colour + '22',
      borderWidth: 2, tension: .35, fill: true, pointRadius: 0, pointHoverRadius: 4
    };
  }
  var pick = $('#trendPick');
  if (pick) {
    pick.addEventListener('change', function () {
      if (!charts.trend) { return; }
      var c = CATS.filter(function (x) { return x.key === pick.value; })[0];
      if (!c) { return; }
      charts.trend.data.datasets = [trendSet(c)];
      charts.trend.update();
    });
  }

  /* ────────────────────────── escape key ────────────────────────── */
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') { return; }
    var open = $$('.modal-scrim').filter(function (m) { return !m.hidden; });
    if (open.length) { open.forEach(function (m) { m.hidden = true; }); }
    else if (!expD.hidden) { closeDrawers(); }
    if (expD.hidden && $$('.modal-scrim').every(function (m) { return m.hidden; })) {
      document.body.style.overflow = '';
    }
  });

  apply();
  paintBulk();
})();
</script>
<?php endif; ?>
<?php require __DIR__ . '/../components/footer.php'; ?>

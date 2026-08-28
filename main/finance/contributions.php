<?php
/**
 * Mutendi CMS — Contributions.
 *
 * The complete record of money received. Answers three questions:
 * what came in, who gave what, and does it reconcile.
 *
 * Three views over one ledger:
 *   All Contributions  the transaction ledger, with a totals row
 *   By Member          giving per person
 *   By Type            a summary per contribution type
 *
 * Multi-currency throughout, matching finance/record.php: every row carries
 * its own currency, and the page can be read either in original currencies or
 * converted to USD — the toggle states the rate it used.
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

/* The ▲/▼ the stat strip and the tables use. */
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

    function fin_trend(int $n): string
    {
        if ($n === 0) { return '<span class="num">&mdash;</span>'; }
        $cls = $n > 0 ? 'is-up' : 'is-down';
        $ico = $n > 0 ? 'fa-caret-up' : 'fa-caret-down';
        return '<span class="delta ' . $cls . '"><i class="fa-solid ' . $ico . '" aria-hidden="true"></i> ' . abs($n) . '%</span>';
    }
}

$has_module  = mu_mod('finance');
$can_view    = mu_can('finance.view');
$can_add     = mu_can('finance.add');
$can_edit    = mu_can('finance.edit');
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
     * own key, so a contribution never hops between branches on reload.
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

/* ═════════════════════════════ THE LEDGER ═════════════════════════════ */

$cur_by_code  = array_column($currencies, null, 'code');
$type_by_key  = array_column($contribution_types, null, 'key');
$meth_by_key  = array_column($payment_methods, null, 'key');
$mem_by_id    = array_column($members_demo, null, 'id');
$proj_by_id   = array_column($projects_demo, null, 'id');

/** An amount in its own currency, converted to USD. */
function fin_usd(float $amount, string $code): float
{
    global $cur_by_code;
    $rate = $cur_by_code[$code]['exchange_rate_to_usd'] ?? 1.0;
    return $amount * $rate;
}

$rows = [];
if ($has_module && $can_view) {
    foreach ($contributions_demo as $c) {
        $m = $c['member_id'] !== null ? ($mem_by_id[$c['member_id']] ?? null) : null;
        $rows[] = $c + [
            'date'       => date('Y-m-d', strtotime('-' . (int) $c['days_ago'] . ' days')),
            'member'     => $m['name'] ?? null,
            'member_no'  => $m['member_no'] ?? null,
            'department' => $m['department'] ?? null,
            'usd'        => round(fin_usd((float) $c['amount'], $c['currency']), 2),
            'type_name'  => $type_by_key[$c['type']]['name'] ?? $c['type'],
            'type_colour'=> $type_by_key[$c['type']]['colour'] ?? '#662F97',
            'meth_name'  => $meth_by_key[$c['method']]['name'] ?? $c['method'],
            'meth_icon'  => $meth_by_key[$c['method']]['icon'] ?? 'fa-money-bill-wave',
            'proj_name'  => $c['project'] !== null ? ($proj_by_id[$c['project']]['name'] ?? null) : null,
            '_branch'    => $branch_aware ? mu_branch_for('con-' . $c['id']) : null,
        ];
    }
}

if ($branch_aware && !$viewing_all) {
    $rows = array_values(array_filter($rows, static function ($r) use ($current_branch) {
        return $r['_branch'] && (int) $r['_branch']['id'] === (int) $current_branch;
    }));
}

/* ── View B: one line per giver, built from the same ledger ── */
$by_member = [];
foreach ($rows as $r) {
    if ($r['member_id'] === null) { continue; }
    $id = (int) $r['member_id'];
    if (!isset($by_member[$id])) {
        $by_member[$id] = [
            'id' => $id, 'name' => $r['member'], 'member_no' => $r['member_no'],
            'department' => (string) ($r['department'] ?? ''),
            'total' => 0.0, 'count' => 0, 'largest' => 0.0, 'last_days' => PHP_INT_MAX,
            'consistency' => (int) ($giving_consistency_demo[$id] ?? 0),
            'trend' => 0,
        ];
    }
    $by_member[$id]['total']   += $r['usd'];
    $by_member[$id]['count']   += 1;
    $by_member[$id]['largest']  = max($by_member[$id]['largest'], $r['usd']);
    $by_member[$id]['last_days'] = min($by_member[$id]['last_days'], (int) $r['days_ago']);
}
foreach ($by_member as $id => $m) {
    $by_member[$id]['average'] = $m['count'] ? $m['total'] / $m['count'] : 0.0;
    /* Deterministic from the member's own id, so it never shuffles on reload.
       LATER: this period's total against the previous period's. */
    $by_member[$id]['trend'] = ((int) (crc32('trend-' . $id) % 41)) - 18;
}
$by_member = array_values($by_member);
usort($by_member, static fn($a, $b) => $b['total'] <=> $a['total']);

/* ── View C: one line per contribution type ── */
$by_type = [];
$grand_usd = 0.0;
foreach ($rows as $r) { $grand_usd += $r['usd']; }
foreach ($contribution_types as $t) {
    $mine = array_values(array_filter($rows, static fn($r) => $r['type'] === $t['key']));
    if (!$mine) { continue; }
    $sum = array_sum(array_column($mine, 'usd'));
    $by_type[] = [
        'key' => $t['key'], 'name' => $t['name'], 'icon' => $t['icon'], 'colour' => $t['colour'],
        'total' => $sum, 'count' => count($mine),
        'average' => $sum / count($mine),
        'share' => $grand_usd > 0 ? ($sum / $grand_usd) * 100 : 0,
        'spark' => $giving_by_type_spark_demo[$t['key']] ?? [],
    ];
}
usort($by_type, static fn($a, $b) => $b['total'] <=> $a['total']);

/* Headline figures, scaled to the branch in view the way the People pages do.
   LATER: the aggregate arrives already scoped to :branch_id. */
$S = $contribution_stats;
$share = 1.0;
if ($branch_aware && !$viewing_all) {
    $b = get_branch($current_branch);
    $share = $b ? max(0.05, (int) $b['members_count'] / max(1, (int) ($organisation['total_members'] ?? 1))) : 1.0;
}

$recorders = array_values(array_unique(array_column($rows, 'by')));
sort($recorders);

$page_title = 'Contributions';
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
        <span aria-current="page">Contributions</span>
      </nav>
      <div class="title-row">
        <h1 class="page__title">Contributions</h1>
        <?php if ($has_module && $can_view): ?>
          <span class="count-chip" data-count="<?= count($rows) ?>">0</span>
        <?php endif; ?>
      </div>
      <p class="page__sub">All contributions received by your church.</p>
    </div>

    <?php if ($has_module && $can_view): ?>
      <div class="page__actions">
        <?php if ($can_add): ?>
          <a class="btn" href="<?= $base_url ?>finance/record.php">
            <i class="fa-solid fa-plus" aria-hidden="true"></i> Record Contribution
          </a>
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
      <p>Your church's plan does not include contribution tracking. A church administrator can request it from the platform team.</p>
      <a class="btn" href="<?= $base_url ?>index.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Dashboard</a>
    </div>
  </section>

<?php elseif (!$can_view): ?>

  <section class="panel">
    <div class="empty">
      <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-lock"></i></span>
      <h3>You cannot view contributions</h3>
      <p>Reading the contribution ledger needs the <code>finance.view</code> permission. Ask a church administrator to grant it.</p>
      <a class="btn" href="<?= $base_url ?>index.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Dashboard</a>
    </div>
  </section>

<?php else: ?>

  <!-- ═════════════════════ STAT STRIP + CURRENCY TOGGLE ═════════════════════ -->
  <div class="statwrap">
    <div class="stat-strip">
      <div class="stat-tile is-static">
        <span class="stat-tile__icon tone-purple" aria-hidden="true"><i class="fa-solid fa-hand-holding-dollar"></i></span>
        <span class="stat-tile__body">
          <span class="stat-tile__value">$<span data-count="<?= (int) round($S['month']['now'] * $share) ?>">0</span></span>
          <span class="stat-tile__label">Total This Month</span>
          <?= fin_delta((float) $S['month']['now'], (float) $S['month']['prev']) ?>
        </span>
      </div>
      <div class="stat-tile is-static">
        <span class="stat-tile__icon tone-green" aria-hidden="true"><i class="fa-solid fa-sack-dollar"></i></span>
        <span class="stat-tile__body">
          <span class="stat-tile__value">$<span data-count="<?= (int) round($S['year']['now'] * $share) ?>">0</span></span>
          <span class="stat-tile__label">Total This Year</span>
          <?= fin_delta((float) $S['year']['now'], (float) $S['year']['prev']) ?>
        </span>
      </div>
      <div class="stat-tile is-static">
        <span class="stat-tile__icon tone-blue" aria-hidden="true"><i class="fa-solid fa-church"></i></span>
        <span class="stat-tile__body">
          <span class="stat-tile__value">$<span data-count="<?= (int) round($S['per_service']['now'] * $share) ?>">0</span></span>
          <span class="stat-tile__label">Average per Service</span>
          <?= fin_delta((float) $S['per_service']['now'], (float) $S['per_service']['prev']) ?>
        </span>
      </div>
      <div class="stat-tile is-static">
        <span class="stat-tile__icon tone-amber" aria-hidden="true"><i class="fa-solid fa-users"></i></span>
        <span class="stat-tile__body">
          <span class="stat-tile__value" data-count="<?= (int) round($S['givers']['now'] * $share) ?>">0</span>
          <span class="stat-tile__label">Number of Givers</span>
          <?= fin_delta((float) $S['givers']['now'], (float) $S['givers']['prev']) ?>
        </span>
      </div>
    </div>

    <!-- Converted, or as received. The rate is stated either way — a figure
         nobody can trace back to a rate is not much use in an audit. -->
    <div class="curtoggle" role="group" aria-label="Currency display">
      <div class="curtoggle__btns">
        <button class="curtoggle__btn is-on" type="button" data-cur-mode="usd" aria-pressed="true">All in USD</button>
        <button class="curtoggle__btn" type="button" data-cur-mode="original" aria-pressed="false">As received</button>
      </div>
      <p class="curtoggle__rates">
        <?php
          $bits = [];
          foreach ($currencies as $c) {
            if (!empty($c['is_default'])) { continue; }
            $bits[] = '1 ' . $c['code'] . ' = $' . rtrim(rtrim(number_format($c['exchange_rate_to_usd'], 4), '0'), '.');
          }
          echo htmlspecialchars(implode('  ·  ', $bits));
        ?>
      </p>
    </div>
  </div>


  <!-- ═════════════════════════ TOOLBAR / VIEW SWITCH ═════════════════════════ -->
  <div class="toolbar">
    <div class="svcviews" role="group" aria-label="View">
      <button class="svcview is-on" type="button" data-view="all" aria-pressed="true">
        <i class="fa-solid fa-table-list" aria-hidden="true"></i> <span>All Contributions</span>
      </button>
      <button class="svcview" type="button" data-view="member" aria-pressed="false">
        <i class="fa-solid fa-user-check" aria-hidden="true"></i> <span>By Member</span>
      </button>
      <button class="svcview" type="button" data-view="type" aria-pressed="false">
        <i class="fa-solid fa-chart-pie" aria-hidden="true"></i> <span>By Type</span>
      </button>
    </div>
    <p style="color:var(--muted);font-size:12.5px;font-weight:600">
      <span data-result-count><?= count($rows) ?></span> shown
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
                 placeholder="Member name, reference or amount&hellip;">
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
          <button class="rchip" type="button" data-preset="last">Last Month</button>
          <button class="rchip" type="button" data-preset="quarter">This Quarter</button>
          <button class="rchip" type="button" data-preset="year">This Year</button>
        </div>
      </div>

      <div class="field">
        <label for="fType">Contribution Type</label>
        <select class="select" id="fType" data-filter>
          <option>All</option>
          <?php foreach ($contribution_types as $t): ?><option><?= htmlspecialchars($t['name']) ?></option><?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="fMethod">Payment Method</label>
        <select class="select" id="fMethod" data-filter>
          <option>All</option>
          <?php foreach ($payment_methods as $m): ?><option><?= htmlspecialchars($m['name']) ?></option><?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="fCurrency">Currency</label>
        <select class="select" id="fCurrency" data-filter>
          <option>All</option>
          <?php foreach ($currencies as $c): ?><option><?= htmlspecialchars($c['code']) ?></option><?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label>Amount range (USD)</label>
        <div class="daterange">
          <input class="input" type="number" id="fMin" min="0" step="1" placeholder="Min" data-filter aria-label="Minimum amount">
          <span class="daterange__to" aria-hidden="true">to</span>
          <input class="input" type="number" id="fMax" min="0" step="1" placeholder="Max" data-filter aria-label="Maximum amount">
        </div>
      </div>

      <?php if (mu_mod('attendance')): ?>
        <div class="field">
          <label for="fService">Service</label>
          <select class="select" id="fService" data-filter>
            <option>All</option>
            <?php foreach ($service_types_demo as $s): ?><option><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <?php if ($show_branch): ?>
        <div class="field">
          <label for="fBranch"><?= htmlspecialchars(t('branch_singular')) ?></label>
          <select class="select" id="fBranch" data-filter>
            <option>All</option>
            <?php foreach ($branch_options as $b): ?><option><?= htmlspecialchars($b['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <?php if (mu_mod('projects')): ?>
        <div class="field">
          <label for="fProject">Project</label>
          <select class="select" id="fProject" data-filter>
            <option>All</option>
            <?php foreach ($projects_demo as $p): ?><option><?= htmlspecialchars($p['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <div class="field">
        <label for="fBy">Recorded By</label>
        <select class="select" id="fBy" data-filter>
          <option>All</option>
          <?php foreach ($recorders as $r): ?><option><?= htmlspecialchars($r) ?></option><?php endforeach; ?>
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
          <span><span class="sk sk--text" style="width:38%"></span><span class="sk sk--line" style="width:20%"></span></span>
          <span class="sk sk--pill" style="width:100px"></span>
        </div>
      <?php endfor; ?>
    </div>

    <div data-content>

      <!-- ══════════════════ VIEW A — ALL CONTRIBUTIONS ══════════════════ -->
      <div data-pane="all">
        <div class="dt-wrap">
          <table class="dt" id="ledger">
            <thead>
              <tr>
                <th style="width:34px"><input class="check" type="checkbox" data-check-all aria-label="Select all contributions"></th>
                <th style="width:40px">#</th>
                <th class="is-sortable" data-sort="date">Date <i class="fa-solid fa-sort sort" aria-hidden="true"></i></th>
                <th class="is-sortable" data-sort="member">Member <i class="fa-solid fa-sort sort" aria-hidden="true"></i></th>
                <th>Type</th>
                <th class="is-sortable" data-sort="usd" style="text-align:right">Amount <i class="fa-solid fa-sort sort" aria-hidden="true"></i></th>
                <th>Method</th>
                <th>Reference</th>
                <?php if (mu_mod('attendance')): ?><th>Service</th><?php endif; ?>
                <?php if ($show_branch): ?><th><?= htmlspecialchars(t('branch_singular')) ?></th><?php endif; ?>
                <?php if (mu_mod('projects')): ?><th>Project</th><?php endif; ?>
                <th>Recorded By</th>
                <th class="col-actions" style="text-align:right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $i => $r): ?>
                <tr data-row data-id="<?= (int) $r['id'] ?>"
                    data-date="<?= $r['date'] ?>"
                    data-member="<?= htmlspecialchars(mb_strtolower((string) ($r['member'] ?? 'anonymous'))) ?>"
                    data-no="<?= htmlspecialchars(mb_strtolower((string) ($r['member_no'] ?? ''))) ?>"
                    data-ref="<?= htmlspecialchars(mb_strtolower($r['ref'] . ' ' . $r['txn'])) ?>"
                    data-type="<?= htmlspecialchars($r['type_name']) ?>"
                    data-method="<?= htmlspecialchars($r['meth_name']) ?>"
                    data-currency="<?= htmlspecialchars($r['currency']) ?>"
                    data-usd="<?= $r['usd'] ?>"
                    data-amount="<?= $r['amount'] ?>"
                    data-service="<?= htmlspecialchars((string) $r['service']) ?>"
                    data-project="<?= htmlspecialchars((string) ($r['proj_name'] ?? '')) ?>"
                    data-by="<?= htmlspecialchars($r['by']) ?>"
                    <?= $show_branch && $r['_branch'] ? 'data-branch="' . htmlspecialchars($r['_branch']['name']) . '"' : '' ?>>
                  <td><input class="check" type="checkbox" data-check aria-label="Select <?= htmlspecialchars($r['ref']) ?>"></td>
                  <td class="num"><?= $i + 1 ?></td>
                  <td class="nowrap">
                    <span class="strong"><?= mu_date($r['date'], 'd M Y') ?></span>
                    <span class="tsub"><?= htmlspecialchars($r['time']) ?></span>
                  </td>
                  <td>
                    <?php if ($r['member'] === null): ?>
                      <span class="anonchip"><i class="fa-solid fa-user-secret" aria-hidden="true"></i> Anonymous</span>
                    <?php else: ?>
                      <span class="person">
                        <?= mu_av($r['member'], 'sm') ?>
                        <span class="person__text">
                          <span class="person__name"><?= htmlspecialchars($r['member']) ?></span>
                          <span class="tsub"><?= htmlspecialchars((string) $r['member_no']) ?></span>
                        </span>
                      </span>
                    <?php endif; ?>
                  </td>
                  <td><span class="tybadge" style="--c:<?= htmlspecialchars($r['type_colour']) ?>"><?= htmlspecialchars($r['type_name']) ?></span></td>
                  <td class="amtcell">
                    <span class="amtcell__main" data-amt
                          data-usd-text="$<?= number_format($r['usd'], 2) ?>"
                          data-orig-text="<?= htmlspecialchars($cur_by_code[$r['currency']]['symbol']) ?><?= number_format((float) $r['amount'], 2) ?>">
                      $<?= number_format($r['usd'], 2) ?>
                    </span>
                    <?php if ($r['currency'] !== 'USD'): ?>
                      <span class="amtcell__sub" data-amt-sub
                            data-usd-text="<?= htmlspecialchars($r['currency']) ?> <?= number_format((float) $r['amount'], 2) ?>"
                            data-orig-text="≈ $<?= number_format($r['usd'], 2) ?>">
                        <?= htmlspecialchars($r['currency']) ?> <?= number_format((float) $r['amount'], 2) ?>
                      </span>
                    <?php endif; ?>
                  </td>
                  <td><span class="mbadge"><i class="fa-solid <?= htmlspecialchars($r['meth_icon']) ?>" aria-hidden="true"></i> <?= htmlspecialchars($r['meth_name']) ?></span></td>
                  <td class="refcell" title="<?= htmlspecialchars($r['txn'] ?: $r['ref']) ?>"><?= htmlspecialchars($r['txn'] ?: $r['ref']) ?></td>
                  <?php if (mu_mod('attendance')): ?><td class="tsubcell"><?= htmlspecialchars((string) $r['service']) ?></td><?php endif; ?>
                  <?php if ($show_branch): ?><td><?= mu_branch_chip($r['_branch']) ?></td><?php endif; ?>
                  <?php if (mu_mod('projects')): ?>
                    <td><?= $r['proj_name'] ? '<span class="pchip">' . htmlspecialchars($r['proj_name']) . '</span>' : '<span class="num">&mdash;</span>' ?></td>
                  <?php endif; ?>
                  <td>
                    <span class="person">
                      <?= mu_av($r['by'], 'sm') ?>
                      <span class="person__text"><span class="person__name"><?= htmlspecialchars($r['by']) ?></span></span>
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
                      <button class="iconbtn" type="button" data-receipt="<?= (int) $r['id'] ?>" aria-label="Receipt for <?= htmlspecialchars($r['ref']) ?>">
                        <i class="fa-solid fa-receipt" aria-hidden="true"></i>
                      </button>

                      <div class="drop" data-menu>
                        <button class="iconbtn" type="button" aria-haspopup="true" aria-expanded="false" data-menu-btn aria-label="More actions">
                          <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                        </button>
                        <div class="menu menu--end" data-menu-panel hidden>
                          <button class="menu__item" type="button" data-open="<?= (int) $r['id'] ?>"><i class="fa-regular fa-eye" aria-hidden="true"></i> View Details</button>
                          <?php if ($can_edit): ?>
                            <button class="menu__item" type="button" data-edit="<?= (int) $r['id'] ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</button>
                          <?php endif; ?>
                          <button class="menu__item" type="button" data-receipt="<?= (int) $r['id'] ?>"><i class="fa-solid fa-print" aria-hidden="true"></i> Print Receipt</button>
                          <?php if (mu_mod('communication')): ?>
                            <button class="menu__item" type="button" data-toast="Receipt sent"><i class="fa-regular fa-paper-plane" aria-hidden="true"></i> Send Receipt</button>
                          <?php endif; ?>
                          <?php if ($can_add): ?>
                            <a class="menu__item" href="<?= $base_url ?>finance/record.php"><i class="fa-regular fa-copy" aria-hidden="true"></i> Duplicate</a>
                          <?php endif; ?>
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

        <!-- Stacked cards below 768px — never a shrunken table. -->
        <div class="dt-cards">
          <?php foreach ($rows as $r): ?>
            <article class="pcard" data-card data-id="<?= (int) $r['id'] ?>"
                     data-date="<?= $r['date'] ?>"
                     data-member="<?= htmlspecialchars(mb_strtolower((string) ($r['member'] ?? 'anonymous'))) ?>"
                     data-no="<?= htmlspecialchars(mb_strtolower((string) ($r['member_no'] ?? ''))) ?>"
                     data-ref="<?= htmlspecialchars(mb_strtolower($r['ref'] . ' ' . $r['txn'])) ?>"
                     data-type="<?= htmlspecialchars($r['type_name']) ?>"
                     data-method="<?= htmlspecialchars($r['meth_name']) ?>"
                     data-currency="<?= htmlspecialchars($r['currency']) ?>"
                     data-usd="<?= $r['usd'] ?>"
                     data-service="<?= htmlspecialchars((string) $r['service']) ?>"
                     data-project="<?= htmlspecialchars((string) ($r['proj_name'] ?? '')) ?>"
                     data-by="<?= htmlspecialchars($r['by']) ?>"
                     <?= $show_branch && $r['_branch'] ? 'data-branch="' . htmlspecialchars($r['_branch']['name']) . '"' : '' ?>>
              <button class="pcard__main" type="button" data-card-toggle aria-expanded="false">
                <?php if ($r['member'] === null): ?>
                  <span class="av av--md av-c8" aria-hidden="true"><i class="fa-solid fa-user-secret" style="font-size:13px"></i></span>
                <?php else: ?>
                  <?= mu_av($r['member'], 'md') ?>
                <?php endif; ?>
                <span class="pcard__text">
                  <span class="pcard__name"><?= htmlspecialchars($r['member'] ?? 'Anonymous') ?></span>
                  <span class="pcard__meta"><?= mu_date($r['date'], 'd M Y') ?> &middot; <?= htmlspecialchars($r['type_name']) ?></span>
                </span>
                <span class="pcard__amt">
                  <?= htmlspecialchars($cur_by_code[$r['currency']]['symbol']) ?><?= number_format((float) $r['amount'], 2) ?>
                  <?php if ($r['currency'] !== 'USD'): ?><small>≈ $<?= number_format($r['usd'], 2) ?></small><?php endif; ?>
                </span>
                <i class="fa-solid fa-chevron-down pcard__chev" aria-hidden="true"></i>
              </button>
              <div class="pcard__more">
                <div class="pcard__row"><span>Method</span><span><?= htmlspecialchars($r['meth_name']) ?></span></div>
                <div class="pcard__row"><span>Reference</span><span class="refcell"><?= htmlspecialchars($r['txn'] ?: $r['ref']) ?></span></div>
                <?php if (mu_mod('attendance')): ?><div class="pcard__row"><span>Service</span><span><?= htmlspecialchars((string) $r['service']) ?></span></div><?php endif; ?>
                <?php if (mu_mod('projects') && $r['proj_name']): ?><div class="pcard__row"><span>Project</span><span><?= htmlspecialchars($r['proj_name']) ?></span></div><?php endif; ?>
                <?php if ($show_branch && $r['_branch']): ?><div class="pcard__row"><span><?= htmlspecialchars(t('branch_singular')) ?></span><span><?= mu_branch_chip($r['_branch']) ?></span></div><?php endif; ?>
                <div class="pcard__row"><span>Recorded by</span><span><?= htmlspecialchars($r['by']) ?></span></div>
                <div class="pcard__acts">
                  <button class="btn btn--ghost btn--sm" type="button" data-open="<?= (int) $r['id'] ?>"><i class="fa-regular fa-eye" aria-hidden="true"></i> View</button>
                  <button class="btn btn--ghost btn--sm" type="button" data-receipt="<?= (int) $r['id'] ?>"><i class="fa-solid fa-receipt" aria-hidden="true"></i> Receipt</button>
                  <?php if ($can_edit): ?>
                    <button class="btn btn--ghost btn--sm" type="button" data-edit="<?= (int) $r['id'] ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</button>
                  <?php endif; ?>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>

        <!-- Pinned above the pagination: what the current filter actually sums to. -->
        <div class="totalsrow" data-totals>
          <span class="totalsrow__label">
            <i class="fa-solid fa-equals" aria-hidden="true"></i>
            Total for <b><span data-totals-count>0</span></b> shown
          </span>
          <span class="totalsrow__cur" data-totals-cur></span>
          <span class="totalsrow__grand">
            <span>Grand total</span>
            <b data-totals-usd>$0.00</b>
          </span>
        </div>

        <div class="pager">
          <span>Showing <b data-result-count><?= count($rows) ?></b> of <?= count($rows) ?> contributions</span>
          <div class="pager__pages">
            <button type="button" aria-current="page">1</button>
          </div>
        </div>
      </div>


      <!-- ══════════════════ VIEW B — BY MEMBER ══════════════════ -->
      <div data-pane="member" hidden>
        <div class="dt-wrap">
          <table class="dt" id="memberTable">
            <thead>
              <tr>
                <th style="width:40px">#</th>
                <th class="is-sortable" data-sort="name">Member <i class="fa-solid fa-sort sort" aria-hidden="true"></i></th>
                <?php if (mu_mod('departments')): ?><th>Department</th><?php endif; ?>
                <th class="is-sortable" data-sort="total" style="text-align:right" aria-sort="descending">Total Given <i class="fa-solid fa-sort sort" aria-hidden="true"></i></th>
                <th class="is-sortable" data-sort="ccount">Contributions <i class="fa-solid fa-sort sort" aria-hidden="true"></i></th>
                <th style="text-align:right">Average</th>
                <th style="text-align:right">Largest</th>
                <th class="is-sortable" data-sort="last">Last Given <i class="fa-solid fa-sort sort" aria-hidden="true"></i></th>
                <th style="min-width:130px">Consistency</th>
                <th>Trend</th>
                <th class="col-actions" style="text-align:right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($by_member as $i => $m): ?>
                <?php $stale = $m['last_days'] > 60; ?>
                <tr data-mrow data-id="<?= $m['id'] ?>"
                    data-name="<?= htmlspecialchars(mb_strtolower($m['name'])) ?>"
                    data-total="<?= $m['total'] ?>" data-ccount="<?= $m['count'] ?>" data-last="<?= $m['last_days'] ?>">
                  <td class="num"><?= $i + 1 ?></td>
                  <td>
                    <span class="person">
                      <?= mu_av($m['name'], 'sm') ?>
                      <span class="person__text">
                        <span class="person__name"><?= htmlspecialchars($m['name']) ?></span>
                        <span class="tsub"><?= htmlspecialchars((string) $m['member_no']) ?></span>
                      </span>
                    </span>
                  </td>
                  <?php if (mu_mod('departments')): ?>
                    <td><?= $m['department'] !== ''
                          ? '<span class="dchip">' . htmlspecialchars($m['department']) . '</span>'
                          : '<span class="num">&mdash;</span>' ?></td>
                  <?php endif; ?>
                  <td class="amtcell"><span class="amtcell__main">$<?= number_format($m['total'], 2) ?></span></td>
                  <td class="num"><?= $m['count'] ?></td>
                  <td class="amtcell"><span class="tsubcell">$<?= number_format($m['average'], 2) ?></span></td>
                  <td class="amtcell"><span class="tsubcell">$<?= number_format($m['largest'], 2) ?></span></td>
                  <td class="nowrap<?= $stale ? ' is-stale' : '' ?>"><?= mu_ago((int) $m['last_days']) ?></td>
                  <td>
                    <?php /* How many of the last twelve months they gave in. */ ?>
                    <span class="consist" title="<?= $m['consistency'] ?> of the last 12 months">
                      <span class="consist__bars" aria-hidden="true">
                        <?php for ($k = 0; $k < 12; $k++): ?>
                          <span class="consist__b<?= $k < $m['consistency'] ? ' is-on' : '' ?>"></span>
                        <?php endfor; ?>
                      </span>
                      <b><?= $m['consistency'] ?>/12</b>
                    </span>
                  </td>
                  <td class="nowrap"><?= fin_trend((int) $m['trend']) ?></td>
                  <td class="col-actions">
                    <div class="rowacts">
                      <button class="iconbtn" type="button" data-history="<?= $m['id'] ?>" aria-label="Giving history for <?= htmlspecialchars($m['name']) ?>">
                        <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                      </button>
                      <button class="iconbtn" type="button" data-statement="<?= $m['id'] ?>" aria-label="Statement for <?= htmlspecialchars($m['name']) ?>">
                        <i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i>
                      </button>
                      <?php if (mu_mod('communication')): ?>
                        <button class="iconbtn" type="button" data-toast="Message composer opened" aria-label="Message <?= htmlspecialchars($m['name']) ?>">
                          <i class="fa-regular fa-comment" aria-hidden="true"></i>
                        </button>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="dt-cards">
          <?php foreach ($by_member as $m): ?>
            <article class="pcard" data-mrow data-id="<?= $m['id'] ?>"
                     data-name="<?= htmlspecialchars(mb_strtolower($m['name'])) ?>"
                     data-total="<?= $m['total'] ?>" data-ccount="<?= $m['count'] ?>" data-last="<?= $m['last_days'] ?>">
              <button class="pcard__main" type="button" data-card-toggle aria-expanded="false">
                <?= mu_av($m['name'], 'md') ?>
                <span class="pcard__text">
                  <span class="pcard__name"><?= htmlspecialchars($m['name']) ?></span>
                  <span class="pcard__meta"><?= $m['count'] ?> contribution<?= $m['count'] === 1 ? '' : 's' ?></span>
                </span>
                <span class="pcard__amt">$<?= number_format($m['total'], 2) ?></span>
                <i class="fa-solid fa-chevron-down pcard__chev" aria-hidden="true"></i>
              </button>
              <div class="pcard__more">
                <div class="pcard__row"><span>Average</span><span>$<?= number_format($m['average'], 2) ?></span></div>
                <div class="pcard__row"><span>Largest</span><span>$<?= number_format($m['largest'], 2) ?></span></div>
                <div class="pcard__row"><span>Last given</span><span<?= $m['last_days'] > 60 ? ' class="is-stale"' : '' ?>><?= mu_ago((int) $m['last_days']) ?></span></div>
                <div class="pcard__row"><span>Consistency</span><span><?= $m['consistency'] ?> of 12 months</span></div>
                <div class="pcard__row"><span>Trend</span><span><?= fin_trend((int) $m['trend']) ?></span></div>
                <div class="pcard__acts">
                  <button class="btn btn--ghost btn--sm" type="button" data-history="<?= $m['id'] ?>"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> History</button>
                  <button class="btn btn--ghost btn--sm" type="button" data-statement="<?= $m['id'] ?>"><i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i> Statement</button>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>


      <!-- ══════════════════ VIEW C — BY TYPE ══════════════════ -->
      <div data-pane="type" hidden>
        <div class="tygrid">
          <?php foreach ($by_type as $t): ?>
            <article class="tycard" style="--c:<?= htmlspecialchars($t['colour']) ?>">
              <header class="tycard__head">
                <span class="tycard__icon" aria-hidden="true"><i class="fa-solid <?= htmlspecialchars($t['icon']) ?>"></i></span>
                <div class="tycard__id">
                  <h3><?= htmlspecialchars($t['name']) ?></h3>
                  <p><?= $t['count'] ?> contribution<?= $t['count'] === 1 ? '' : 's' ?> &middot; avg $<?= number_format($t['average'], 2) ?></p>
                </div>
              </header>

              <p class="tycard__total">$<?= number_format($t['total'], 2) ?></p>

              <div class="tycard__share">
                <span class="tycard__share-top">
                  <span>Share of all giving</span>
                  <b><?= number_format($t['share'], 1) ?>%</b>
                </span>
                <span class="rbar"><span class="rbar__fill" style="width:<?= round($t['share'], 1) ?>%;background:var(--c)"></span></span>
              </div>

              <?php if ($t['spark']): ?>
                <?php
                  /* Six months, scaled to the series' own range — a zero-based
                     plot of a type whose total barely moves is a flat wall. */
                  $lo = min($t['spark']); $hi = max($t['spark']); $span = max(1, $hi - $lo);
                  $n = max(1, count($t['spark']) - 1);
                  $pts = [];
                  foreach (array_values($t['spark']) as $i => $v) {
                    $pts[] = round(($i / $n) * 100, 2) . ',' . round(24 - (($v - $lo) / $span) * 20, 2);
                  }
                  $line = implode(' ', $pts);
                ?>
                <svg class="spark8 tycard__spark" viewBox="0 0 100 26" preserveAspectRatio="none" role="img"
                     aria-label="Six-month trend for <?= htmlspecialchars($t['name']) ?>">
                  <polygon class="spark8__fill" points="0,26 <?= $line ?> 100,26"></polygon>
                  <polyline class="spark8__line" points="<?= $line ?>"></polyline>
                </svg>
                <p class="tycard__sparklabel">Last 6 months</p>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="empty" id="listEmpty" hidden>
        <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-filter-circle-xmark"></i></span>
        <h3>No contributions match those filters</h3>
        <p>Try a wider date range or a different type, or clear the filters to see the whole ledger again.</p>
        <button class="btn btn--ghost" type="button" data-reset-filters>
          <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Reset filters
        </button>
      </div>
    </div>
  </section>


  <!-- ═════════════════════════════ BOTTOM ROW ═════════════════════════════ -->
  <div class="chartgrid chartgrid--2" style="margin-top:16px">
    <section class="chartcard">
      <header class="chartcard__head">
        <div>
          <h2>Giving Trend</h2>
          <p>Total received per month, in USD.</p>
        </div>
      </header>
      <div class="chartbox chartbox--tall">
        <canvas id="trendChart" data-axis-x="Month" data-axis-y="Total received (USD)"></canvas>
      </div>
    </section>

    <section class="chartcard">
      <header class="chartcard__head">
        <div>
          <h2>Top Contribution Types</h2>
          <p>Share of everything received in this range.</p>
        </div>
      </header>
      <div class="chartbox chartbox--tall">
        <canvas id="typeChart"></canvas>
      </div>
    </section>
  </div>

  <!-- Appears once something is selected. -->
  <div class="bulkbar" id="bulkBar" hidden>
    <span class="bulkbar__count"><b data-bulk-count>0</b> selected</span>
    <span class="bulkbar__sep" aria-hidden="true"></span>
    <button class="bulkbar__btn" type="button" data-toast="Receipts sent to printer">
      <i class="fa-solid fa-print" aria-hidden="true"></i> Print Receipts
    </button>
    <?php if (mu_mod('communication')): ?>
      <button class="bulkbar__btn" type="button" data-toast="Receipts sent">
        <i class="fa-regular fa-paper-plane" aria-hidden="true"></i> Send Receipts
      </button>
    <?php endif; ?>
    <button class="bulkbar__btn" type="button" data-toast="Export started">
      <i class="fa-solid fa-file-export" aria-hidden="true"></i> Export Selected
    </button>
    <?php if ($can_edit): ?>
      <button class="bulkbar__btn" type="button" id="bulkType">
        <i class="fa-solid fa-tags" aria-hidden="true"></i> Change Type
      </button>
    <?php endif; ?>
    <?php if ($can_delete): ?>
      <button class="bulkbar__btn is-danger" type="button" id="bulkDelete">
        <i class="fa-regular fa-trash-can" aria-hidden="true"></i> Delete
      </button>
    <?php endif; ?>
    <span class="bulkbar__sep" aria-hidden="true"></span>
    <button class="bulkbar__close" type="button" id="bulkClose" aria-label="Clear selection">
      <i class="fa-solid fa-xmark" aria-hidden="true"></i>
    </button>
  </div>

<?php endif; ?>

</div><!-- /.page -->


<?php if ($has_module && $can_view): ?>

<div class="drawer-scrim" data-drawer-scrim hidden></div>

<!-- ══════════════════════ CONTRIBUTION DETAIL DRAWER ══════════════════════ -->
<aside class="drawer" id="conDrawer" role="dialog" aria-modal="true" aria-labelledby="dTitle" hidden>
  <header class="drawer__head">
    <div class="drawer__title">
      <h2 id="dTitle">Contribution</h2>
      <p><span data-d-ref>—</span></p>
    </div>
    <button class="iconbtn" type="button" data-drawer-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
  </header>

  <div class="drawer__body">
    <!-- The amount is the point of the record, so it is what you see first. -->
    <div class="bigamt">
      <span class="bigamt__value" data-d-amount>$0.00</span>
      <span class="bigamt__usd" data-d-usd hidden></span>
      <span class="tybadge" data-d-type style="--c:#662F97">Type</span>
    </div>

    <div class="minirow" data-d-memberrow style="margin-bottom:14px">
      <span class="av av--sm" data-d-av aria-hidden="true"></span>
      <span class="minirow__text"><b data-d-member>—</b><span data-d-memberno>—</span></span>
    </div>

    <dl class="deflist">
      <div><dt>Method</dt><dd data-d-method>—</dd></div>
      <div><dt>Reference</dt><dd class="refcell" data-d-txn>—</dd></div>
      <div><dt>Date received</dt><dd data-d-date>—</dd></div>
      <?php if (mu_mod('attendance')): ?><div><dt>Service</dt><dd data-d-service>—</dd></div><?php endif; ?>
      <?php if (mu_mod('projects')): ?><div><dt>Project</dt><dd data-d-project>—</dd></div><?php endif; ?>
      <?php if ($show_branch): ?><div><dt><?= htmlspecialchars(t('branch_singular')) ?></dt><dd data-d-branch>—</dd></div><?php endif; ?>
    </dl>

    <p class="minilist__head">Notes</p>
    <p class="drawer__prose" data-d-notes>—</p>

    <!-- Who touched this record, and when. A financial record needs a trail. -->
    <p class="minilist__head">Audit trail</p>
    <ol class="audit" data-d-audit></ol>
  </div>

  <footer class="drawer__foot drawer__foot--wrap">
    <button class="btn btn--ghost" type="button" id="dReceipt">
      <i class="fa-solid fa-print" aria-hidden="true"></i> Print Receipt
    </button>
    <?php if (mu_mod('communication')): ?>
      <button class="btn btn--ghost" type="button" data-toast="Receipt sent">
        <i class="fa-regular fa-paper-plane" aria-hidden="true"></i> Send Receipt
      </button>
    <?php endif; ?>
    <?php if ($can_edit): ?>
      <button class="btn btn--ghost" type="button" id="dEdit"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</button>
    <?php endif; ?>
    <?php if ($can_delete): ?>
      <button class="btn btn--danger" type="button" id="dDelete"><i class="fa-regular fa-trash-can" aria-hidden="true"></i> Delete</button>
    <?php endif; ?>
  </footer>
</aside>


<!-- ══════════════════════ MEMBER GIVING HISTORY DRAWER ══════════════════════ -->
<aside class="drawer" id="memDrawer" role="dialog" aria-modal="true" aria-labelledby="mTitle" hidden>
  <header class="drawer__head">
    <span class="av av--lg" data-m-av aria-hidden="true">MM</span>
    <div class="drawer__title">
      <h2 id="mTitle">Member</h2>
      <p><span data-m-no>—</span></p>
    </div>
    <button class="iconbtn" type="button" data-drawer-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
  </header>

  <div class="drawer__body">
    <div class="bigamt bigamt--sm">
      <span class="bigamt__value" data-m-total>$0.00</span>
      <span class="bigamt__caption" data-m-caption>across 0 contributions</span>
    </div>

    <p class="minilist__head">Last 12 months</p>
    <div class="chartbox"><canvas id="mBarChart"></canvas></div>

    <p class="minilist__head">By type</p>
    <div class="chartbox"><canvas id="mTypeChart"></canvas></div>

    <p class="minilist__head">Contributions</p>
    <div class="minilist" data-m-list></div>
  </div>

  <footer class="drawer__foot drawer__foot--wrap">
    <button class="btn btn--ghost" type="button" id="mStatement">
      <i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i> Generate Statement
    </button>
    <?php if (mu_mod('communication')): ?>
      <button class="btn btn--ghost" type="button" data-toast="Message composer opened">
        <i class="fa-regular fa-comment" aria-hidden="true"></i> Message
      </button>
    <?php endif; ?>
    <a class="btn" href="<?= $base_url ?>members/all.php"><i class="fa-regular fa-user" aria-hidden="true"></i> View Profile</a>
  </footer>
</aside>


<?php if ($can_edit): ?>
<!-- ══════════════════════ EDIT CONTRIBUTION MODAL ══════════════════════ -->
<div class="modal-scrim" id="modalEdit" hidden>
  <div class="modal modal--wide" role="dialog" aria-modal="true" aria-labelledby="edTitle">
    <header class="modal__head">
      <h2 id="edTitle">Edit Contribution</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>

    <div class="modal__body">
      <div class="at-notice at-notice--warn" role="note" style="margin-bottom:14px">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        <div class="at-notice__body">
          <strong>You are amending a financial record</strong>
          <span>This was recorded by <span data-ed-by>—</span>. The original figures are kept and every change is logged against your name.</span>
        </div>
      </div>

      <p class="modal__hint" data-ed-ref>Reference</p>

      <div class="form-grid">
        <div class="field">
          <label for="edAmount">Amount</label>
          <input class="input" type="text" id="edAmount" inputmode="decimal">
        </div>
        <div class="field">
          <label for="edCurrency">Currency</label>
          <select class="select" id="edCurrency">
            <?php foreach ($currencies as $c): ?>
              <option value="<?= htmlspecialchars($c['code']) ?>"><?= htmlspecialchars($c['code']) ?> — <?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="edType">Contribution type</label>
          <select class="select" id="edType">
            <?php foreach ($contribution_types as $t): ?>
              <option value="<?= htmlspecialchars($t['key']) ?>"><?= htmlspecialchars($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="edMethod">Payment method</label>
          <select class="select" id="edMethod">
            <?php foreach ($payment_methods as $m): ?>
              <option value="<?= htmlspecialchars($m['key']) ?>"><?= htmlspecialchars($m['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field col-2">
          <label for="edTxn">Reference</label>
          <input class="input" type="text" id="edTxn" autocomplete="off">
        </div>
        <div class="field">
          <label for="edDate">Date received</label>
          <input class="input" type="date" id="edDate">
        </div>
        <?php if (mu_mod('attendance')): ?>
          <div class="field">
            <label for="edService">Service</label>
            <select class="select" id="edService">
              <option value="">Not tied to a service</option>
              <?php foreach ($service_types_demo as $s): ?><option><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>
        <?php if (mu_mod('projects')): ?>
          <div class="field">
            <label for="edProject">Project</label>
            <select class="select" id="edProject">
              <option value="">Not designated</option>
              <?php foreach ($projects_demo as $p): ?>
                <option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>
        <div class="field col-2">
          <label for="edNotes">Notes</label>
          <textarea class="textarea" id="edNotes" rows="2"></textarea>
        </div>
      </div>

      <div class="field" style="margin-top:16px">
        <label for="edReason">Reason for change <span class="req">*</span></label>
        <textarea class="textarea" id="edReason" rows="3" placeholder="Why is this record being amended?"></textarea>
        <p class="hint">Required. Stored with the amendment so the change can be explained in an audit.</p>
      </div>
    </div>

    <footer class="modal__foot">
      <span class="modal__spacer"></span>
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <button class="btn" type="button" id="edGo" disabled><i class="fa-solid fa-check" aria-hidden="true"></i> Save Changes</button>
    </footer>
  </div>
</div>
<?php endif; ?>


<?php if ($can_delete): ?>
<!-- ══════════════════════════ DELETE MODAL ══════════════════════════ -->
<div class="modal-scrim" id="modalDelete" hidden>
  <div class="modal modal--sm" role="dialog" aria-modal="true" aria-labelledby="delTitle">
    <header class="modal__head">
      <h2 id="delTitle">Delete this contribution?</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>

    <div class="modal__body">
      <div class="at-notice at-notice--danger" role="note" style="margin-bottom:14px">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        <div class="at-notice__body">
          <strong>This cannot be undone</strong>
          <span>Removing <b data-del-what>this contribution</b> changes your recorded totals and the giver's history. Consider amending it instead.</span>
        </div>
      </div>

      <div class="field">
        <label for="delReason">Reason for deletion <span class="req">*</span></label>
        <textarea class="textarea" id="delReason" rows="2" placeholder="Why is this record being removed?"></textarea>
      </div>

      <div class="field" style="margin-top:12px">
        <label for="delConfirm">Type the amount <strong data-del-amount>0.00</strong> to confirm</label>
        <input class="input" type="text" id="delConfirm" placeholder="0.00" autocomplete="off">
      </div>
    </div>

    <footer class="modal__foot">
      <span class="modal__spacer"></span>
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <button class="btn btn--danger" type="button" id="delGo" disabled>
        <i class="fa-regular fa-trash-can" aria-hidden="true"></i> Delete Contribution
      </button>
    </footer>
  </div>
</div>
<?php endif; ?>


<!-- ══════════════════════ GENERATE STATEMENT MODAL ══════════════════════ -->
<div class="modal-scrim" id="modalStatement" hidden>
  <div class="modal modal--wide" role="dialog" aria-modal="true" aria-labelledby="stTitle">
    <header class="modal__head">
      <h2 id="stTitle">Generate Statement</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>

    <div class="modal__body">
      <div class="stlayout">
        <div class="stlayout__form">
          <div class="field">
            <label for="stMember">Member</label>
            <input class="input" type="text" id="stMember" readonly>
          </div>

          <div class="form-grid" style="margin-top:12px">
            <div class="field"><label for="stFrom">From</label><input class="input" type="date" id="stFrom"></div>
            <div class="field"><label for="stTo">To</label><input class="input" type="date" id="stTo"></div>
          </div>

          <p class="modal__group">Format</p>
          <div class="radio-cards">
            <?php foreach ([['PDF', 'fa-file-pdf', 'Formatted, ready to send'],
                            ['Excel', 'fa-file-excel', 'Figures you can work with']] as $i => [$f, $ic, $hint]): ?>
              <label class="rcard">
                <input type="radio" name="stFormat" value="<?= $f ?>" <?= $i === 0 ? 'checked' : '' ?>>
                <span class="rcard__box">
                  <i class="fa-solid <?= $ic ?>" aria-hidden="true"></i>
                  <span><strong><?= $f ?></strong><span class="hint" style="margin:2px 0 0"><?= $hint ?></span></span>
                </span>
              </label>
            <?php endforeach; ?>
          </div>

          <p class="modal__group">Include</p>
          <div class="modal__list">
            <div class="modal__row">
              <i class="fa-solid fa-list modal__row-icon" aria-hidden="true"></i>
              <span class="modal__row-label">All contribution types</span>
              <span class="switch"><input type="checkbox" id="stAll" checked data-st><span class="switch__track" aria-hidden="true"></span></span>
            </div>
            <div class="modal__row">
              <i class="fa-solid fa-receipt modal__row-icon" aria-hidden="true"></i>
              <span class="modal__row-label">Tax-deductible only</span>
              <span class="switch"><input type="checkbox" id="stTax" data-st><span class="switch__track" aria-hidden="true"></span></span>
            </div>
            <div class="modal__row">
              <i class="fa-solid fa-compress modal__row-icon" aria-hidden="true"></i>
              <span class="modal__row-label">Summary only, no line items</span>
              <span class="switch"><input type="checkbox" id="stSummary" data-st><span class="switch__track" aria-hidden="true"></span></span>
            </div>
          </div>
        </div>

        <div class="stlayout__preview">
          <p class="minilist__head" style="margin-top:0">Preview</p>
          <div class="stpreview" data-st-preview>
            <div class="stpreview__head">
              <strong><?= htmlspecialchars($church['name']) ?></strong>
              <span>Giving Statement</span>
            </div>
            <p class="stpreview__to" data-st-name>Member</p>
            <p class="stpreview__range" data-st-range>Date range</p>
            <div class="stpreview__rows" data-st-rows></div>
            <div class="stpreview__total">
              <span>Total</span><b data-st-total>$0.00</b>
            </div>
          </div>
        </div>
      </div>
    </div>

    <footer class="modal__foot">
      <span class="modal__spacer"></span>
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <button class="btn" type="button" id="stGo"><i class="fa-solid fa-download" aria-hidden="true"></i> Generate</button>
    </footer>
  </div>
</div>


<!-- ══════════════════════════ RECEIPT PREVIEW ══════════════════════════ -->
<div class="modal-scrim" id="modalReceipt" hidden>
  <div class="modal modal--sm" role="dialog" aria-modal="true" aria-labelledby="rcTitle">
    <header class="modal__head">
      <h2 id="rcTitle">Receipt</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>

    <div class="modal__body">
      <div class="receipt" id="receiptSheet">
        <header class="receipt__head">
          <img class="receipt__logo" src="<?= htmlspecialchars($church['logo']) ?>" alt="">
          <div>
            <strong><?= htmlspecialchars($church['name']) ?></strong>
            <span><?= htmlspecialchars($church['code']) ?></span>
          </div>
          <span class="receipt__no" data-rc-no>—</span>
        </header>

        <p class="receipt__label">Received with thanks from</p>
        <p class="receipt__from" data-rc-member>—</p>

        <div class="receipt__amount">
          <span data-rc-figures>$0.00</span>
          <!-- The amount in words, the way a paper receipt has always done it. -->
          <em data-rc-words>zero dollars</em>
        </div>

        <dl class="receipt__rows">
          <div><dt>For</dt><dd data-rc-type>—</dd></div>
          <div><dt>Method</dt><dd data-rc-method>—</dd></div>
          <div><dt>Date</dt><dd data-rc-date>—</dd></div>
          <div><dt>Reference</dt><dd class="refcell" data-rc-ref>—</dd></div>
        </dl>

        <div class="receipt__sign">
          <span class="receipt__line" aria-hidden="true"></span>
          <span>Received by &mdash; <span data-rc-by>—</span></span>
        </div>
      </div>
    </div>

    <footer class="modal__foot">
      <span class="modal__spacer"></span>
      <button class="btn btn--ghost" type="button" data-close>Close</button>
      <?php if (mu_mod('communication')): ?>
        <button class="btn btn--ghost" type="button" data-toast="Receipt sent"><i class="fa-regular fa-paper-plane" aria-hidden="true"></i> Send</button>
      <?php endif; ?>
      <button class="btn" type="button" id="rcPrint"><i class="fa-solid fa-print" aria-hidden="true"></i> Print</button>
    </footer>
  </div>
</div>
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
/* Contributions — three views, currency toggle, filtering with a live totals
   row, selection, drawers and the amend/delete/statement/receipt flows.
   All client-side; nothing is written anywhere. */
(function () {
  'use strict';

  var still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var $  = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };

  var CUR     = <?= json_encode(array_column($currencies, null, 'code'), JSON_UNESCAPED_UNICODE) ?>;
  var TYPES   = <?= json_encode(array_column($contribution_types, null, 'key'), JSON_UNESCAPED_UNICODE) ?>;
  var ROWS    = <?= json_encode(array_map(static function ($r) {
                     return [
                        'id' => (int) $r['id'], 'ref' => $r['ref'], 'txn' => $r['txn'],
                        'date' => $r['date'], 'time' => $r['time'],
                        'member' => $r['member'], 'member_id' => $r['member_id'], 'no' => $r['member_no'],
                        'type' => $r['type'], 'typeName' => $r['type_name'], 'colour' => $r['type_colour'],
                        'amount' => (float) $r['amount'], 'currency' => $r['currency'], 'usd' => $r['usd'],
                        'method' => $r['meth_name'], 'service' => $r['service'],
                        'project' => $r['proj_name'], 'by' => $r['by'], 'notes' => $r['notes'],
                        'branch' => $r['_branch']['name'] ?? null,
                     ];
                  }, $rows), JSON_UNESCAPED_UNICODE) ?>;
  var TREND   = <?= json_encode($giving_trend_demo, JSON_UNESCAPED_UNICODE) ?>;
  var BYTYPE  = <?= json_encode(array_map(static function ($t) {
                     return ['name' => $t['name'], 'total' => round($t['total'], 2), 'colour' => $t['colour']];
                  }, $by_type), JSON_UNESCAPED_UNICODE) ?>;

  var byId = {};
  ROWS.forEach(function (r) { byId[r.id] = r; });

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

  /* footer.php stops propagation inside a [data-menu-panel], so anything in a
     dropdown needs a capturing listener and must close its own menu. */
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

  /* ──────────────────── skeleton → content swap ──────────────────── */
  var panel = $('#listPanel');
  setTimeout(function () { panel.classList.add('is-loaded'); }, still ? 0 : 600);

  /* Scoped deliberately. [data-count] is also a natural name for a sortable
     column's value, and an unscoped selector here would set textContent on a
     whole <tr> and wipe every cell in it. */
  $$('.stat-tile [data-count], .count-chip[data-count]').forEach(function (el) {
    var target = parseFloat(el.getAttribute('data-count')) || 0;
    if (still) { el.textContent = target.toLocaleString(); return; }
    var start = performance.now();
    (function step(now) {
      var p = Math.min(1, (now - start) / 900);
      el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3))).toLocaleString();
      if (p < 1) { requestAnimationFrame(step); }
    })(start);
  });

  /* ═════════════════════════ money ═════════════════════════ */
  function fmt(n) { return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function usd(n) { return '$' + fmt(n); }
  function sym(code) { return (CUR[code] || {}).symbol || code; }

  /* The amount in words, the way a paper receipt has always done it. */
  var ONES = ['zero','one','two','three','four','five','six','seven','eight','nine','ten','eleven','twelve',
              'thirteen','fourteen','fifteen','sixteen','seventeen','eighteen','nineteen'];
  var TENS = ['','','twenty','thirty','forty','fifty','sixty','seventy','eighty','ninety'];
  function words(n) {
    n = Math.floor(n);
    if (n < 20) { return ONES[n]; }
    if (n < 100) { return TENS[Math.floor(n / 10)] + (n % 10 ? '-' + ONES[n % 10] : ''); }
    if (n < 1000) { return ONES[Math.floor(n / 100)] + ' hundred' + (n % 100 ? ' and ' + words(n % 100) : ''); }
    if (n < 1000000) { return words(Math.floor(n / 1000)) + ' thousand' + (n % 1000 ? ' ' + words(n % 1000) : ''); }
    return String(n);
  }
  function amountInWords(n, code) {
    var whole = Math.floor(n), cents = Math.round((n - whole) * 100);
    var unit = code === 'USD' ? 'dollar' : code;
    var s = words(whole) + ' ' + (code === 'USD' ? (whole === 1 ? 'dollar' : 'dollars') : unit);
    if (cents) { s += ' and ' + words(cents) + ' cent' + (cents === 1 ? '' : 's'); }
    return s.charAt(0).toUpperCase() + s.slice(1) + ' only';
  }

  /* ═══════════════════════ the currency toggle ═══════════════════════ */
  /* Two readings of the same ledger: converted to USD, or as it came in.
     Nothing is recalculated — only which figure leads. */
  var curMode = 'usd';
  $$('[data-cur-mode]').forEach(function (b) {
    b.addEventListener('click', function () {
      curMode = b.getAttribute('data-cur-mode');
      $$('[data-cur-mode]').forEach(function (o) {
        var on = o === b;
        o.classList.toggle('is-on', on);
        o.setAttribute('aria-pressed', String(on));
      });
      $$('[data-amt]').forEach(function (el) {
        el.textContent = el.getAttribute(curMode === 'usd' ? 'data-usd-text' : 'data-orig-text');
      });
      $$('[data-amt-sub]').forEach(function (el) {
        el.textContent = el.getAttribute(curMode === 'usd' ? 'data-usd-text' : 'data-orig-text');
      });
      paintTotals();
      toast(curMode === 'usd' ? 'Showing every figure in USD' : 'Showing amounts as received', 'info');
    });
  });

  /* ═════════════════════════ view switching ═════════════════════════ */
  var view = 'all';
  function setView(next) {
    view = next;
    $$('[data-view]').forEach(function (b) {
      var on = b.getAttribute('data-view') === next;
      b.classList.toggle('is-on', on);
      b.setAttribute('aria-pressed', String(on));
    });
    $$('[data-pane]').forEach(function (p) { p.hidden = p.getAttribute('data-pane') !== next; });
    apply();
    Object.keys(charts).forEach(function (k) { if (charts[k]) { charts[k].resize(); } });
  }
  $$('[data-view]').forEach(function (b) {
    b.addEventListener('click', function () { setView(b.getAttribute('data-view')); });
  });

  /* ═════════════════════════ filtering ═════════════════════════ */
  var search = $('#fSearch'), clearBtn = $('[data-search-clear]'),
      chipsRow = $('[data-filter-chips]'), activeN = $('[data-active-filters]'),
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

  function matches(el, f) {
    if (f.q) {
      var q = f.q.toLowerCase();
      var hay = [el.getAttribute('data-member') || '', el.getAttribute('data-no') || '',
                 el.getAttribute('data-ref') || '', el.getAttribute('data-usd') || '',
                 el.getAttribute('data-amount') || ''].join(' ');
      if (hay.indexOf(q) === -1) { return false; }
    }
    var d = el.getAttribute('data-date');
    if (f.fFrom && d && d < f.fFrom) { return false; }
    if (f.fTo   && d && d > f.fTo)   { return false; }

    var u = parseFloat(el.getAttribute('data-usd'));
    if (f.fMin && !isNaN(u) && u < parseFloat(f.fMin)) { return false; }
    if (f.fMax && !isNaN(u) && u > parseFloat(f.fMax)) { return false; }

    if (f.fType     && el.getAttribute('data-type')     !== f.fType)     { return false; }
    if (f.fMethod   && el.getAttribute('data-method')   !== f.fMethod)   { return false; }
    if (f.fCurrency && el.getAttribute('data-currency') !== f.fCurrency) { return false; }
    if (f.fService  && el.getAttribute('data-service')  !== f.fService)  { return false; }
    <?php if ($show_branch): ?>
    if (f.fBranch   && el.getAttribute('data-branch')   !== f.fBranch)   { return false; }
    <?php endif; ?>
    if (f.fProject  && el.getAttribute('data-project')  !== f.fProject)  { return false; }
    if (f.fBy       && el.getAttribute('data-by')       !== f.fBy)       { return false; }
    return true;
  }

  /* The totals row is the point of the filter bar — it has to reflect exactly
     what is on screen, per currency and as one converted figure. */
  function paintTotals() {
    var byCur = {}, byCurUsd = {}, total = 0, n = 0;
    $$('#ledger tr[data-row]').forEach(function (tr) {
      if (tr.hidden) { return; }
      n++;
      var code = tr.getAttribute('data-currency');
      byCur[code] = (byCur[code] || 0) + parseFloat(tr.getAttribute('data-amount'));
      byCurUsd[code] = (byCurUsd[code] || 0) + parseFloat(tr.getAttribute('data-usd'));
      total += parseFloat(tr.getAttribute('data-usd'));
    });
    $('[data-totals-count]').textContent = n;
    $('[data-totals-usd]').textContent = usd(total);
    $('[data-totals-cur]').innerHTML = '';
    Object.keys(byCur).sort().forEach(function (c) {
      var s = document.createElement('span');
      s.className = 'totalsrow__chip';
      /* The chips follow the toggle. Leaving a ZWG figure sitting in a row
         the reader has asked to see in USD is how totals get misread. */
      var native = sym(c) + fmt(byCur[c]) + ' ' + c;
      if (curMode === 'usd' && c !== 'USD') {
        s.textContent = usd(byCurUsd[c]) + ' ' + c;
        s.title = native + ' as received';
      } else {
        s.textContent = native;
      }
      $('[data-totals-cur]').appendChild(s);
    });
  }

  function apply() {
    var f = activeFilters(), shown = 0;

    $$('[data-row], [data-card]').forEach(function (el) {
      var ok = matches(el, f);
      el.hidden = !ok;
      if (ok && el.hasAttribute('data-row')) { shown++; }
    });

    /* By Member and By Type are summaries of the ledger, so only the search
       narrows them — a payment-method filter does not describe a person. */
    $$('[data-mrow]').forEach(function (el) {
      var ok = !f.q || (el.getAttribute('data-name') || '').indexOf(f.q.toLowerCase()) !== -1;
      el.hidden = !ok;
    });

    if (view === 'all') {
      emptyState.hidden = shown !== 0;
      paintTotals();
    } else {
      emptyState.hidden = true;
    }
    $$('[data-result-count]').forEach(function (el) { el.textContent = shown; });

    var keys = Object.keys(f);
    chipsRow.innerHTML = '';
    keys.forEach(function (k) {
      var label = k === 'q' ? 'Search: ' + f[k]
                : k === 'fFrom' ? 'From ' + f[k] : k === 'fTo' ? 'To ' + f[k]
                : k === 'fMin' ? 'Min $' + f[k] : k === 'fMax' ? 'Max $' + f[k] : f[k];
      var chip = document.createElement('span');
      chip.className = 'fchip';
      chip.innerHTML = '<span></span><button type="button" aria-label="Remove filter"><i class="fa-solid fa-xmark"></i></button>';
      $('span', chip).textContent = label;
      $('button', chip).addEventListener('click', function () {
        if (k === 'q') { search.value = ''; }
        else { var el = document.getElementById(k); el.value = el.tagName === 'SELECT' ? 'All' : ''; }
        apply();
      });
      chipsRow.appendChild(chip);
    });
    chipsRow.hidden = keys.length === 0;
    if (activeN) { activeN.textContent = keys.length; activeN.hidden = keys.length === 0; }
    if (clearBtn) { clearBtn.hidden = !(search && search.value); }
  }

  if (search) { search.addEventListener('input', apply); }
  if (clearBtn) { clearBtn.addEventListener('click', function () { search.value = ''; apply(); search.focus(); }); }
  $$('[data-filter]').forEach(function (el) {
    el.addEventListener('change', apply); el.addEventListener('input', apply);
  });

  function iso(d) { return d.toISOString().slice(0, 10); }
  $$('[data-preset]').forEach(function (b) {
    b.addEventListener('click', function () {
      var now = new Date(), from = new Date(now), to = new Date(now);
      switch (b.getAttribute('data-preset')) {
        case 'week':    from.setDate(now.getDate() - ((now.getDay() + 6) % 7)); break;
        case 'month':   from = new Date(now.getFullYear(), now.getMonth(), 1); break;
        case 'last':    from = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                        to   = new Date(now.getFullYear(), now.getMonth(), 0); break;
        case 'quarter': from = new Date(now.getFullYear(), Math.floor(now.getMonth() / 3) * 3, 1); break;
        case 'year':    from = new Date(now.getFullYear(), 0, 1); break;
      }
      $('#fFrom').value = iso(from); $('#fTo').value = iso(to);
      $$('[data-preset]').forEach(function (o) { o.classList.toggle('is-on', o === b); });
      apply();
    });
  });

  $$('[data-reset-filters]').forEach(function (b) {
    b.addEventListener('click', function () {
      if (search) { search.value = ''; }
      $$('[data-filter]').forEach(function (el) { el.value = el.tagName === 'SELECT' ? 'All' : ''; });
      $$('[data-preset]').forEach(function (o) { o.classList.remove('is-on'); });
      apply(); toast('Filters cleared', 'info');
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
      var numeric = ['usd', 'total', 'ccount', 'last'].indexOf(key) !== -1;
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

  /* ═════════════════════════ selection ═════════════════════════ */
  var bulk = $('#bulkBar'), checkAll = $('[data-check-all]');

  function selected() { return $$('[data-check]').filter(function (c) { return c.checked && !c.closest('tr').hidden; }); }
  function paintBulk() {
    var n = selected().length;
    $('[data-bulk-count]').textContent = n;
    bulk.hidden = n === 0;
    var all = $$('[data-check]').filter(function (c) { return !c.closest('tr').hidden; });
    if (checkAll) {
      checkAll.checked = all.length > 0 && all.every(function (c) { return c.checked; });
      checkAll.indeterminate = !checkAll.checked && n > 0;
    }
  }
  if (checkAll) {
    checkAll.addEventListener('change', function () {
      $$('[data-check]').forEach(function (c) { if (!c.closest('tr').hidden) { c.checked = checkAll.checked; } });
      paintBulk();
    });
  }
  $$('[data-check]').forEach(function (c) { c.addEventListener('change', paintBulk); });
  $('#bulkClose').addEventListener('click', function () {
    $$('[data-check]').forEach(function (c) { c.checked = false; });
    if (checkAll) { checkAll.indeterminate = false; }
    paintBulk();
  });
  var bt = $('#bulkType');
  if (bt) { bt.addEventListener('click', function () { toast('Type changed on ' + selected().length + ' contributions', 'success'); }); }
  var bd = $('#bulkDelete');
  if (bd) { bd.addEventListener('click', function () { toast(selected().length + ' contributions deleted', 'error'); }); }

  /* ═════════════════════════ drawers ═════════════════════════ */
  var scrim = $('[data-drawer-scrim]'), conD = $('#conDrawer'), memD = $('#memDrawer'), lastFocus = null;

  function openDrawer(d) {
    lastFocus = document.activeElement;
    d.hidden = false; scrim.hidden = false;
    document.body.style.overflow = 'hidden';
    $('[data-drawer-close]', d).focus();
  }
  function closeDrawers() {
    [conD, memD].forEach(function (d) { if (d) { d.hidden = true; } });
    scrim.hidden = true; document.body.style.overflow = '';
    if (lastFocus) { lastFocus.focus(); lastFocus = null; }
  }
  scrim.addEventListener('click', closeDrawers);
  $$('[data-drawer-close]').forEach(function (b) { b.addEventListener('click', closeDrawers); });

  var current = null;

  function openContribution(id) {
    var r = byId[id];
    if (!r) { return; }
    current = r;

    $('#dTitle').textContent = r.typeName;
    $('[data-d-ref]').textContent = r.ref;
    $('[data-d-amount]').textContent = sym(r.currency) + fmt(r.amount);
    var u = $('[data-d-usd]');
    if (r.currency === 'USD') { u.hidden = true; }
    else { u.textContent = '≈ ' + usd(r.usd) + ' at ' + CUR[r.currency].exchange_rate_to_usd + ' per ' + r.currency; u.hidden = false; }

    var badge = $('[data-d-type]');
    badge.textContent = r.typeName; badge.style.setProperty('--c', r.colour);

    var av = $('[data-d-av]');
    if (r.member) {
      av.className = 'av av--sm ' + avc(r.member); av.textContent = initials(r.member);
      $('[data-d-member]').textContent = r.member;
      $('[data-d-memberno]').textContent = r.no || '';
    } else {
      av.className = 'av av--sm av-c8'; av.innerHTML = '<i class="fa-solid fa-user-secret" style="font-size:11px"></i>';
      $('[data-d-member]').textContent = 'Anonymous';
      $('[data-d-memberno]').textContent = 'Not attributed to a member';
    }

    $('[data-d-method]').textContent = r.method;
    $('[data-d-txn]').textContent = r.txn || '—';
    $('[data-d-date]').textContent = new Date(r.date + 'T00:00:00')
      .toLocaleDateString(undefined, { weekday: 'long', day: '2-digit', month: 'short', year: 'numeric' }) + ' at ' + r.time;
    var s = $('[data-d-service]'); if (s) { s.textContent = r.service || '—'; }
    var pj = $('[data-d-project]'); if (pj) { pj.textContent = r.project || '—'; }
    var br = $('[data-d-branch]'); if (br) { br.textContent = r.branch || '—'; }
    $('[data-d-notes]').textContent = r.notes || 'No notes.';

    /* The trail. Recorded always; amended only when it has been.
       LATER: SELECT * FROM contribution_audit WHERE contribution_id = :id; */
    var audit = $('[data-d-audit]');
    audit.innerHTML = '';
    var entries = [['Recorded by ' + r.by, new Date(r.date + 'T00:00:00')
      .toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' }) + ' at ' + r.time]];
    /* A deterministic slice of the ledger carries an amendment, so the trail
       is not always a single line. */
    if (r.id % 7 === 0) {
      entries.push(['Amended by Tendai Marufu', 'Reference corrected after bank reconciliation']);
    }
    entries.forEach(function (e) {
      var li = document.createElement('li');
      li.className = 'audit__row';
      li.innerHTML = '<span class="audit__dot" aria-hidden="true"></span><span class="audit__text"><b></b><span></span></span>';
      $('b', li).textContent = e[0];
      $('.audit__text span', li).textContent = e[1];
      audit.appendChild(li);
    });

    openDrawer(conD);
  }

  document.addEventListener('click', function (e) {
    var o = e.target.closest('[data-open]');
    if (o) { closeOwnMenu(o); openContribution(o.getAttribute('data-open')); }
  }, true);

  /* ── member giving history ── */
  var charts = {};

  function openMember(id) {
    var mine = ROWS.filter(function (r) { return String(r.member_id) === String(id); });
    if (!mine.length) { return; }
    var name = mine[0].member;
    var total = mine.reduce(function (a, r) { return a + r.usd; }, 0);

    var av = $('[data-m-av]');
    av.className = 'av av--lg ' + avc(name); av.textContent = initials(name);
    $('#mTitle').textContent = name;
    $('[data-m-no]').textContent = mine[0].no || '';
    $('[data-m-total]').textContent = usd(total);
    $('[data-m-caption]').textContent = 'across ' + mine.length + ' contribution' + (mine.length === 1 ? '' : 's');

    var list = $('[data-m-list]');
    list.innerHTML = '';
    mine.slice().sort(function (a, b) { return a.date < b.date ? 1 : -1; }).forEach(function (r) {
      var row = document.createElement('button');
      row.type = 'button'; row.className = 'minirow minirow--btn';
      row.innerHTML = '<span class="tybadge" style="--c:' + r.colour + '"></span>' +
        '<span class="minirow__text"><b></b><span></span></span>' +
        '<span class="minirow__amt"></span>';
      $('.tybadge', row).textContent = r.typeName;
      $('b', row).textContent = new Date(r.date + 'T00:00:00')
        .toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' });
      $('.minirow__text span', row).textContent = r.method + (r.txn ? ' · ' + r.txn : '');
      $('.minirow__amt', row).textContent = sym(r.currency) + fmt(r.amount);
      row.addEventListener('click', function () { closeDrawers(); setTimeout(function () { openContribution(r.id); }, 60); });
      list.appendChild(row);
    });

    if (window.Chart) {
      /* twelve months of this member's giving */
      var months = TREND.labels;
      var series = months.map(function (_, i) {
        /* Deterministic from the member's own id so the shape is stable.
           LATER: their actual monthly totals. */
        return Math.round((total / 12) * (0.5 + ((crc32(name + i) % 100) / 100)));
      });
      if (charts.mBar) { charts.mBar.destroy(); }
      charts.mBar = new Chart($('#mBarChart'), {
        type: 'bar',
        data: { labels: months, datasets: [{ label: 'Given', data: series, backgroundColor: '#662F97', borderRadius: 4, maxBarThickness: 18 }] },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: { x: { grid: { display: false }, border: { display: false } },
                    y: { grid: { color: '#ECE7F3' }, border: { display: false }, beginAtZero: true } }
        }
      });

      var byT = {};
      mine.forEach(function (r) { byT[r.typeName] = (byT[r.typeName] || 0) + r.usd; });
      if (charts.mType) { charts.mType.destroy(); }
      charts.mType = new Chart($('#mTypeChart'), {
        type: 'doughnut',
        data: { labels: Object.keys(byT), datasets: [{ data: Object.values(byT),
          backgroundColor: PALETTE, borderWidth: 0, hoverOffset: 6 }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '58%',
          plugins: { legend: { display: true, position: 'bottom', labels: { boxWidth: 10, padding: 12, usePointStyle: true } } } }
      });
    }

    openDrawer(memD);
    setTimeout(function () { Object.keys(charts).forEach(function (k) { if (charts[k]) { charts[k].resize(); } }); }, 80);
  }

  document.addEventListener('click', function (e) {
    var h = e.target.closest('[data-history]');
    if (h) { openMember(h.getAttribute('data-history')); }
  }, true);

  /* ═════════════════════════ modals ═════════════════════════ */
  function openModal(m) { m.hidden = false; document.body.style.overflow = 'hidden'; var c = $('[data-close]', m); if (c) { c.focus(); } }
  function closeModal(m) {
    m.hidden = true;
    if (conD.hidden && memD.hidden) { document.body.style.overflow = ''; }
  }
  document.addEventListener('click', function (e) {
    var cl = e.target.closest('[data-close]');
    if (cl) { closeModal(cl.closest('.modal-scrim')); return; }
    if (e.target.classList.contains('modal-scrim')) { closeModal(e.target); }
  }, true);

  /* ── edit ── */
  var edModal = $('#modalEdit');
  if (edModal) {
    function openEdit(id) {
      var r = byId[id];
      if (!r) { return; }
      current = r;
      $('[data-ed-by]').textContent = r.by;
      $('[data-ed-ref]').textContent = r.ref + ' · ' + (r.member || 'Anonymous');
      $('#edAmount').value = fmt(r.amount);
      $('#edCurrency').value = r.currency;
      $('#edType').value = r.type;
      $('#edTxn').value = r.txn || '';
      $('#edDate').value = r.date;
      $('#edNotes').value = r.notes || '';
      var sv = $('#edService'); if (sv) { sv.value = r.service || ''; }
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
      toast('Contribution amended — change logged', 'success');
    });
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-edit]');
      if (b) { closeOwnMenu(b); openEdit(b.getAttribute('data-edit')); }
    }, true);
    var de = $('#dEdit');
    if (de) { de.addEventListener('click', function () { if (current) { openEdit(current.id); } }); }
  }

  /* ── delete ── */
  var delModal = $('#modalDelete');
  if (delModal) {
    function openDelete(id) {
      var r = byId[id];
      if (!r) { return; }
      current = r;
      $('[data-del-what]').textContent = sym(r.currency) + fmt(r.amount) + ' from ' + (r.member || 'an anonymous giver');
      $('[data-del-amount]').textContent = fmt(r.amount);
      $('#delReason').value = ''; $('#delConfirm').value = '';
      $('#delGo').disabled = true;
      function check() {
        $('#delGo').disabled = !($('#delReason').value.trim().length >= 5 &&
                                 $('#delConfirm').value.trim().replace(/,/g, '') === String(r.amount.toFixed(2)));
      }
      $('#delReason').oninput = check;
      $('#delConfirm').oninput = check;
      openModal(delModal);
    }
    $('#delGo').addEventListener('click', function () {
      closeModal(delModal);
      toast('Contribution deleted', 'error');
    });
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-delete]');
      if (b) { closeOwnMenu(b); openDelete(b.getAttribute('data-delete')); }
    }, true);
    var dd = $('#dDelete');
    if (dd) { dd.addEventListener('click', function () { if (current) { openDelete(current.id); } }); }
  }

  /* ── statement ── */
  var stModal = $('#modalStatement');
  function openStatement(memberId) {
    var mine = ROWS.filter(function (r) { return String(r.member_id) === String(memberId); });
    if (!mine.length) { return; }
    var name = mine[0].member;
    $('#stMember').value = name;
    $('[data-st-name]').textContent = name;
    if (!$('#stFrom').value) { $('#stFrom').value = TREND.labels.length ? new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0, 10) : ''; }
    if (!$('#stTo').value) { $('#stTo').value = new Date().toISOString().slice(0, 10); }
    paintStatement(mine);
    openModal(stModal);
  }
  function paintStatement(mine) {
    var summaryOnly = $('#stSummary').checked;
    var rowsBox = $('[data-st-rows]');
    rowsBox.innerHTML = '';
    $('[data-st-range]').textContent =
      ($('#stFrom').value || '—') + '  to  ' + ($('#stTo').value || '—');

    var total = 0, byT = {};
    mine.forEach(function (r) { total += r.usd; byT[r.typeName] = (byT[r.typeName] || 0) + r.usd; });

    var entries = summaryOnly
      ? Object.keys(byT).map(function (k) { return [k, usd(byT[k])]; })
      : mine.slice().sort(function (a, b) { return a.date < b.date ? 1 : -1; })
            .map(function (r) { return [r.date + '  ·  ' + r.typeName, sym(r.currency) + fmt(r.amount)]; });

    entries.forEach(function (e) {
      var row = document.createElement('div');
      row.className = 'stpreview__row';
      row.innerHTML = '<span></span><b></b>';
      $('span', row).textContent = e[0];
      $('b', row).textContent = e[1];
      rowsBox.appendChild(row);
    });
    $('[data-st-total]').textContent = usd(total);
  }
  $$('[data-st]').forEach(function (c) {
    c.addEventListener('change', function () {
      if (current && current.member_id) {
        paintStatement(ROWS.filter(function (r) { return r.member_id === current.member_id; }));
      } else {
        var name = $('#stMember').value;
        paintStatement(ROWS.filter(function (r) { return r.member === name; }));
      }
    });
  });
  $$('#stFrom, #stTo').forEach(function (i) {
    i.addEventListener('change', function () {
      var name = $('#stMember').value;
      paintStatement(ROWS.filter(function (r) { return r.member === name; }));
    });
  });
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-statement]');
    if (b) { openStatement(b.getAttribute('data-statement')); }
  }, true);
  $('#stGo').addEventListener('click', function () {
    var f = ($('input[name="stFormat"]:checked') || {}).value || 'PDF';
    closeModal(stModal);
    toast(f + ' statement generated', 'success');
  });
  var ms = $('#mStatement');
  if (ms) {
    ms.addEventListener('click', function () {
      var name = $('#mTitle').textContent;
      var mine = ROWS.filter(function (r) { return r.member === name; });
      if (mine.length) { openStatement(mine[0].member_id); }
    });
  }

  /* ── receipt ── */
  var rcModal = $('#modalReceipt');
  function openReceipt(id) {
    var r = byId[id];
    if (!r) { return; }
    $('[data-rc-no]').textContent = r.ref;
    $('[data-rc-member]').textContent = r.member || 'Anonymous';
    $('[data-rc-figures]').textContent = sym(r.currency) + fmt(r.amount);
    $('[data-rc-words]').textContent = amountInWords(r.amount, r.currency);
    $('[data-rc-type]').textContent = r.typeName;
    $('[data-rc-method]').textContent = r.method;
    $('[data-rc-date]').textContent = new Date(r.date + 'T00:00:00')
      .toLocaleDateString(undefined, { day: '2-digit', month: 'long', year: 'numeric' });
    $('[data-rc-ref]').textContent = r.txn || r.ref;
    $('[data-rc-by]').textContent = r.by;
    openModal(rcModal);
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-receipt]');
    if (b) { closeOwnMenu(b); openReceipt(b.getAttribute('data-receipt')); }
  }, true);
  /* The drawer's own button previews the receipt, same as the row menu's. */
  var dRc = $('#dReceipt');
  if (dRc) { dRc.addEventListener('click', function () { if (current) { openReceipt(current.id); } }); }
  $('#rcPrint').addEventListener('click', function () { window.print(); });
  $('#btnPrint').addEventListener('click', function () { window.print(); });

  /* ═════════════════════════ the bottom charts ═════════════════════════ */
  var PALETTE = ['#662F97', '#B48FDA', '#8F5CC2', '#D3BAEA', '#56287F'];

  if (window.Chart) {
    Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.color = '#6E6880';
    Chart.defaults.animation = still ? false : { duration: 500 };

    charts.trend = new Chart($('#trendChart'), {
      type: 'line',
      data: { labels: TREND.labels, datasets: [{
        label: 'Total received', data: TREND.totals,
        borderColor: '#662F97', backgroundColor: 'rgba(102,47,151,.1)',
        fill: true, tension: .35, borderWidth: 2, pointRadius: 0, pointHoverRadius: 4 }] },
      options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { display: false },
          tooltip: { callbacks: { label: function (c) { return usd(c.parsed.y); } } } },
        scales: {
          x: { grid: { display: false }, border: { display: false },
               title: { display: true, text: 'Month', color: '#948CA6', font: { size: 11, weight: '600' } } },
          y: { grid: { color: '#ECE7F3' }, border: { display: false }, beginAtZero: true,
               title: { display: true, text: 'Total received (USD)', color: '#948CA6', font: { size: 11, weight: '600' } },
               ticks: { callback: function (v) { return '$' + v.toLocaleString(); } } }
        }
      }
    });

    charts.type = new Chart($('#typeChart'), {
      type: 'doughnut',
      data: { labels: BYTYPE.map(function (t) { return t.name; }),
              datasets: [{ data: BYTYPE.map(function (t) { return t.total; }),
                backgroundColor: BYTYPE.map(function (t) { return t.colour; }), borderWidth: 0, hoverOffset: 6 }] },
      options: {
        responsive: true, maintainAspectRatio: false, cutout: '56%',
        plugins: {
          legend: { display: true, position: 'bottom', labels: { boxWidth: 10, padding: 10, usePointStyle: true } },
          tooltip: { callbacks: { label: function (c) {
            var sum = c.dataset.data.reduce(function (a, v) { return a + v; }, 0);
            return c.label + ': ' + usd(c.parsed) + '  (' + ((c.parsed / sum) * 100).toFixed(1) + '%)';
          } } }
        }
      }
    });
  }

  /* ═════════════════ avatar helpers, mirroring PHP ═════════════════ */
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
    str = String(str);
    var c = 0xFFFFFFFF;
    for (var i = 0; i < str.length; i++) { c = crcTable[(c ^ str.charCodeAt(i)) & 0xFF] ^ (c >>> 8); }
    return (c ^ 0xFFFFFFFF) >>> 0;
  }
  function avc(name) { return 'av-c' + (crc32(name) % 10); }
  function initials(name) {
    var p = String(name).trim().split(/\s+/);
    return (p[0].charAt(0) + (p.length > 1 ? p[p.length - 1].charAt(0) : '')).toUpperCase();
  }

  /* ────────────────────────── escape key ────────────────────────── */
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') { return; }
    var open = $$('.modal-scrim').filter(function (m) { return !m.hidden; });
    if (open.length) { open.forEach(function (m) { m.hidden = true; }); }
    else if (!conD.hidden || !memD.hidden) { closeDrawers(); }
    if (conD.hidden && memD.hidden) { document.body.style.overflow = ''; }
  });

  apply();
  paintBulk();
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/../components/footer.php'; ?>

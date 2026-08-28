<?php
/**
 * Mutendi CMS — church dashboard.
 *
 * One dashboard, not one-per-role: main/components/widgets.php's
 * main_widgets_for_role() filters $widgets (main/includes/config.php) by the
 * same $enabled_modules / $permissions the sidebar already uses, then sorts
 * by the current role's priority. Nothing about the layout below is
 * hardcoded per role — swap $user/$permissions/$enabled_modules (as the demo
 * switcher below does) and the whole page reflows.
 */

require __DIR__ . '/includes/config.php';
require __DIR__ . '/components/widgets.php';

/* ══════════════════════════════════════════════════════════════════════════
   DEMO ONLY — REMOVE BEFORE PRODUCTION
   Swaps $user, $permissions and $enabled_modules for a hardcoded set so both
   the sidebar and this dashboard can be seen filtering themselves per role.
   Also lets ?new_church=1 preview the onboarding empty state. Nothing outside
   this block and the matching panel at the foot of the file depends on it;
   delete both (and the config.php flag they read) and the page still works.
   Its styles live in assets/css/style.css under the same DEMO ONLY heading.
   ══════════════════════════════════════════════════════════════════════════ */

$core_modules = ['members', 'attendance', 'departments', 'communication', 'reports'];

$demo_roles = [
    'church_admin' => [
        'user'    => ['name' => 'Tendai Marufu', 'role' => 'church_admin', 'role_label' => 'Church Administrator', 'initials' => 'TM', 'email' => 'tendai@mutendicentral.co.zw'],
        'perms'   => ['members.view', 'members.add', 'members.edit', 'finance.view', 'finance.add', 'finance.reports', 'payroll.view', 'settings.manage', 'branches.add', 'branches.edit', 'reports.view'],
        'modules' => array_merge($core_modules, ['finance', 'cell_groups', 'events', 'sermons', 'payroll', 'visitors', 'projects']),
    ],
    'pastor' => [
        'user'    => ['name' => 'Rev. Enock Sithole', 'role' => 'pastor', 'role_label' => 'Pastor', 'initials' => 'ES', 'email' => 'enock@mutendicentral.co.zw'],
        'perms'   => ['members.view', 'members.add', 'finance.view', 'finance.reports', 'reports.view'],
        'modules' => array_merge($core_modules, ['finance', 'cell_groups', 'events', 'sermons', 'visitors', 'projects']),
    ],
    'secretary' => [
        'user'    => ['name' => 'Grace Chikomo', 'role' => 'secretary', 'role_label' => 'Church Secretary', 'initials' => 'GC', 'email' => 'grace@mutendicentral.co.zw'],
        'perms'   => ['members.view', 'members.add', 'members.edit'],
        'modules' => array_merge($core_modules, ['events', 'visitors', 'cell_groups', 'sermons']),
    ],
    'treasurer' => [
        'user'    => ['name' => 'Farai Nyoni', 'role' => 'treasurer', 'role_label' => 'Treasurer', 'initials' => 'FN', 'email' => 'farai@mutendicentral.co.zw'],
        'perms'   => ['members.view', 'finance.view', 'finance.add', 'finance.reports', 'reports.view'],
        'modules' => array_merge($core_modules, ['finance', 'projects']),
    ],
    'dept_head' => [
        'user'    => ['name' => 'Blessing Moyo', 'role' => 'dept_head', 'role_label' => 'Department Head', 'initials' => 'BM', 'email' => 'blessing@mutendicentral.co.zw'],
        'perms'   => ['members.view'],
        'modules' => array_merge($core_modules, ['events', 'cell_groups']),
    ],
    'cell_leader' => [
        'user'    => ['name' => 'Rudo Chirwa', 'role' => 'cell_leader', 'role_label' => 'Cell Group Leader', 'initials' => 'RC', 'email' => 'rudo@mutendicentral.co.zw'],
        'perms'   => ['members.view'],
        'modules' => array_merge($core_modules, ['cell_groups']),
    ],
    'usher' => [
        'user'    => ['name' => 'Simba Dube', 'role' => 'usher', 'role_label' => 'Usher', 'initials' => 'SD', 'email' => 'simba@mutendicentral.co.zw'],
        'perms'   => [],
        'modules' => array_merge($core_modules, ['visitors']),
    ],
];

$demo_role = isset($_GET['role'], $demo_roles[$_GET['role']]) ? $_GET['role'] : 'church_admin';

/* array_merge so the scope keys from config survive the role swap. */
$user            = array_merge($user, $demo_roles[$demo_role]['user']);
$permissions     = $demo_roles[$demo_role]['perms'];
$enabled_modules = $demo_roles[$demo_role]['modules'];

if (isset($_GET['new_church'])) { $is_new_church = $_GET['new_church'] === '1'; }

/* Organisation type: single church or multi-branch. */
if (isset($_GET['orgtype']) && in_array($_GET['orgtype'], ['single', 'multi_branch'], true)) {
    $organisation['type'] = $_GET['orgtype'];
}

/* Terminology preset — the one line that relabels the whole system. */
if (isset($_GET['preset'], $terminology_presets[$_GET['preset']])) {
    $terminology_active = $_GET['preset'];
    $terminology = $terminology_presets[$_GET['preset']];
}

/* Scope: does this user see the whole organisation, or one branch? */
if (($_GET['scope'] ?? '') === 'branch') {
    $user['scope'] = 'branch';
    $pick = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : (int) ($branches[1]['id'] ?? 1);
    $own  = get_branch($pick) ?: ($branches[0] ?? null);
    $user['branch_id']   = $own['id'] ?? 1;
    $user['branch_name'] = $own['name'] ?? '';
} elseif (($_GET['scope'] ?? '') === 'organisation') {
    $user['scope'] = 'organisation';
}

/** Carries the current demo state onto any link in the panel. */
function demo_link(array $over = []): string {
    $q = array_merge($_GET, $over);
    foreach ($q as $k => $v) { if ($v === null) { unset($q[$k]); } }
    return '?' . http_build_query($q);
}
/* ═══════════════════════════════ END DEMO ═══════════════════════════════ */

/* ─────────────────────────── ORGANISATION MODE ───────────────────────────
   The dashboard reads at organisation level only when there is an
   organisation to read: a multi-branch tenant, a user who can see past one
   branch, and no single branch selected in the top bar. In every other case
   this run changes nothing — the branch-level dashboard renders exactly as
   it did before.
   ──────────────────────────────────────────────────────────────────────── */
require_once __DIR__ . '/components/branch-switcher.php';

$org_mode = is_multi_branch()
    && ($user['scope'] ?? 'organisation') !== 'branch'
    && ($current_branch === 'all' || $current_branch === null);

$org_branches = is_multi_branch() ? get_visible_branches() : [];

if (is_multi_branch()) {
    /* Aggregate across every branch in organisation mode; scale to the one in
       view otherwise. LATER: both come from a query already grouped by branch.
       Only the figures change — no widget is added, removed or reordered. */
    $sum = function (string $field) use ($org_branches) {
        return array_sum(array_column($org_branches, $field));
    };
    $sel = ($current_branch !== 'all' && $current_branch !== null) ? get_branch($current_branch) : null;

    if ($org_mode) {
        $widget_data['total_members']['value']    = number_format($sum('members_count'));
        $widget_data['attendance_last']['value']  = number_format($sum('avg_attendance'));
        $widget_data['attendance_avg']['value']   = number_format((int) round($sum('avg_attendance') / max(1, count($org_branches))));
        $widget_data['giving_month']['value']     = '$' . number_format($sum('monthly_giving'));
        $widget_data['giving_week']['value']      = '$' . number_format((int) round($sum('monthly_giving') / 4));
        $widget_data['total_members']['change']   = '+' . count($org_branches) . ' ' . mb_strtolower(t('branch_plural'));
    } elseif ($sel) {
        $share = (int) $sel['members_count'] / max(1, (int) ($organisation['total_members'] ?? 1));
        $scale = function ($txt) use ($share) {
            $n = (float) preg_replace('/[^0-9.]/', '', (string) $txt);
            $money = str_contains((string) $txt, '$');
            return ($money ? '$' : '') . number_format((int) round($n * $share));
        };
        foreach (['total_members', 'new_members', 'giving_month', 'giving_week', 'outstanding_pledges'] as $k) {
            if (isset($widget_data[$k]['value'])) { $widget_data[$k]['value'] = $scale($widget_data[$k]['value']); }
        }
        $widget_data['attendance_last']['value'] = number_format((int) $sel['avg_attendance']);
        $widget_data['attendance_avg']['value']  = number_format((int) $sel['avg_attendance']);
    }
}

/* Organisation-level entry points (Add {branch_singular}). Same gate
   everywhere: multi-branch, organisation scope, branches.add. */
$may_add_branch = $org_mode && in_array('branches.add', $permissions, true);

/* A brand-new organisation has only its head office; nudge them to add more. */
$only_one_branch = $may_add_branch && count($org_branches) === 1;

if ($may_add_branch) {
    /* First tile in Quick Actions. main_allowed() gates it exactly like the
       existing entries, so it disappears with the permission. */
    array_unshift($widget_data['quick_actions'], [
        'label' => 'Add ' . t('branch_singular'), 'icon' => 'fa-church',
        'url' => 'branches/add.php', 'module' => null, 'permission' => 'branches.add',
    ]);
}

if ($org_mode) {
    /* Data for the organisation-only widgets. */
    $growing = $org_branches;
    usort($growing, fn($a, $b) => $b['growth_percent'] <=> $a['growth_percent']);

    $today = new DateTimeImmutable(date('Y-m-d'));
    $needing = [];
    foreach ($org_branches as $b) {
        $days = (int) $today->diff(new DateTimeImmutable($b['last_activity']))->days;
        if ($b['growth_percent'] < 0 || $days >= 30) {
            $needing[] = [
                'id' => $b['id'], 'name' => $b['name'], 'leader_name' => $b['leader_name'],
                'leader_phone' => $b['leader_phone'], 'days' => $days,
                'reason' => $b['growth_percent'] < 0
                    ? 'Attendance down ' . number_format(abs($b['growth_percent']), 1) . '%'
                    : 'No activity recorded',
            ];
        }
    }
    usort($needing, fn($a, $b) => $b['days'] <=> $a['days']);

    $widget_data['total_branches'] = [
        'value' => number_format(count($org_branches)),
        'change' => '+1 this year', 'trend' => 'up', 'tone' => 'brand',
    ];
    $widget_data['org_attendance_total'] = [
        'value' => number_format(array_sum(array_column($org_branches, 'avg_attendance'))),
        'change' => 'across all ' . mb_strtolower(t('branch_plural')), 'trend' => null, 'tone' => 'info',
    ];
    $widget_data['org_giving_total'] = [
        'value' => '$' . number_format(array_sum(array_column($org_branches, 'monthly_giving'))),
        'change' => 'this month', 'trend' => 'up', 'tone' => 'ok',
    ];
    $widget_data['branch_comparison'] = [
        'can_finance' => in_array('finance.reports', $permissions, true),
        'branches' => array_map(fn($b) => [
            'name' => $b['name'], 'members' => (int) $b['members_count'],
            'attendance' => (int) $b['avg_attendance'], 'giving' => (float) $b['monthly_giving'],
            'growth' => (float) $b['growth_percent'],
        ], $org_branches),
    ];
    $widget_data['branch_leaderboard'] = array_slice($growing, 0, 5);
    $widget_data['branches_attention'] = array_slice($needing, 0, 5);

    /* One organisation-level alert, appended to the existing strip. */
    $quiet = max(1, count($needing));
    $alerts[] = [
        'key' => 'org_attendance', 'tone' => 'warn', 'icon' => 'fa-triangle-exclamation',
        'text' => $quiet . ' ' . mb_strtolower(t('branch_plural')) . ' have not recorded attendance this week.',
        'action' => 'Review', 'action_url' => 'branches/index.php',
        'module' => null, 'permission' => null, 'visible' => true,
    ];
}

/* ------------------------------------------------------------- greeting -- */

$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

/* Pastors are addressed by title; everyone else, by first name. */
if (($user['role'] ?? '') === 'pastor') {
    $parts = explode(' ', trim($user['name']));
    $greeting_name = 'Pastor ' . end($parts);
} else {
    $greeting_name = explode(' ', trim($user['name']))[0];
}

$context_line = $role_context[$user['role']] ?? $role_context_default;

if ($org_mode) {
    /* Address the organisation's leader, and speak about the whole body. */
    $parts = explode(' ', trim($user['name']));
    $greeting_name = t('org_leader_title') . ' ' . end($parts);
    $context_line  = "Here's how " . $organisation['name'] . ' is doing.';
}

/* ---------------------------------------------------- widgets for this role -- */

$role_widgets = main_widgets_for_role($widgets, $enabled_modules, $permissions, $user['role'], $org_mode);

/* Every widget this role is ALLOWED to see, grouped for the Customise modal —
   independent of $is_new_church, since the modal always reflects what the
   role could show, not what the page happens to be showing right now. */
$modal_groups = [];
foreach ($role_widgets as $w) {
    $modal_groups[widget_category($w['type'], !empty($w['org_only']))][] = $w;
}

$page_title = 'Dashboard';
require __DIR__ . '/components/header.php';
?>

<div class="page">

  <!-- ═══════════════════════════════════════ 1. WELCOME HEADER ═══════════════════════════════════════ -->
  <header class="page__head">
    <div>
      <p class="page__eyebrow"><?= htmlspecialchars(date('l, j F Y')) ?></p>
      <h1 class="page__title"><?= htmlspecialchars($greeting) ?>, <?= htmlspecialchars($greeting_name) ?></h1>
      <p class="page__sub"><?= htmlspecialchars($context_line) ?></p>
    </div>
    <div class="page__actions">
      <div class="drop" data-menu>
        <button class="btn btn--ghost" type="button" aria-haspopup="true" aria-expanded="false" data-menu-btn>
          <i class="fa-regular fa-calendar" aria-hidden="true"></i>
          <span data-range-label>This Month</span>
          <i class="fa-solid fa-chevron-down" style="font-size:10px;opacity:.7" aria-hidden="true"></i>
        </button>
        <div class="menu" data-menu-panel hidden>
          <?php foreach (['Today', 'This Week', 'This Month', 'This Year', 'Custom'] as $range): ?>
            <a class="menu__item" href="#" data-range-option><i class="fa-regular fa-clock" aria-hidden="true"></i> <?= $range ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <button class="btn" type="button" id="customiseBtn">
        <i class="fa-solid fa-sliders" aria-hidden="true"></i> Customise Dashboard
      </button>
    </div>
  </header>

  <?php if ($only_one_branch): ?>
    <div class="onebranch" data-onebranch role="status">
      <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
      <p>
        You have one <?= htmlspecialchars(mb_strtolower(t('branch_singular'))) ?> set up.
        Add more to manage your whole <?= htmlspecialchars(mb_strtolower(t('org_singular'))) ?> from one place.
      </p>
      <a class="btn" href="<?= $base_url ?>branches/add.php">
        <i class="fa-solid fa-plus" aria-hidden="true"></i> Add <?= htmlspecialchars(t('branch_singular')) ?>
      </a>
      <button class="onebranch__dismiss" type="button" data-onebranch-dismiss aria-label="Dismiss">
        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
      </button>
    </div>
  <?php endif; ?>

  <!-- ═══════════════════════════════════════ 2. ALERT STRIP ═══════════════════════════════════════ -->
  <?php $visible_alerts = array_filter($alerts, fn($a) => main_allowed($a, $enabled_modules, $permissions)); ?>
  <?php if ($visible_alerts): ?>
    <div class="alerts">
      <?php foreach ($visible_alerts as $a): ?>
        <div class="alertbar is-<?= htmlspecialchars($a['tone']) ?>" data-alert="<?= htmlspecialchars($a['key']) ?>" <?= $a['visible'] ? '' : 'hidden' ?>>
          <i class="fa-solid <?= htmlspecialchars($a['icon']) ?>" aria-hidden="true"></i>
          <p><?= htmlspecialchars($a['text']) ?></p>
          <a class="alertbar__action" href="<?= htmlspecialchars($base_url . $a['action_url']) ?>"><?= htmlspecialchars($a['action']) ?></a>
          <button class="alertbar__dismiss" type="button" aria-label="Dismiss"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($is_new_church): ?>

    <!-- ═══════════════════════════════════════ 4. NEW-CHURCH ONBOARDING ═══════════════════════════════════════ -->
    <?php
      $done_count = count(array_filter($onboarding_steps, fn($s) => $s['done']));
      $total_count = count($onboarding_steps);
      $onb_pct = (int) round(($done_count / max(1, $total_count)) * 100);
    ?>
    <section class="onboard">
      <div class="onboard__head">
        <span class="onboard__icon" aria-hidden="true"><i class="fa-solid fa-seedling"></i></span>
        <div>
          <h2>Let's set up your church</h2>
          <p><?= $done_count ?> of <?= $total_count ?> steps complete &mdash; a few minutes and you're ready to go.</p>
        </div>
      </div>
      <div class="onboard__bar"><span style="width:<?= $onb_pct ?>%"></span></div>
      <ul class="onboard__list">
        <?php foreach ($onboarding_steps as $step): ?>
          <li class="onboard__row<?= $step['done'] ? ' is-done' : '' ?>">
            <span class="onboard__tick" aria-hidden="true">
              <?php if ($step['done']): ?><i class="fa-solid fa-check"></i><?php endif; ?>
            </span>
            <span class="onboard__label"><?= htmlspecialchars($step['label']) ?></span>
            <?php if (!$step['done']): ?>
              <a class="chip-btn" href="<?= htmlspecialchars($base_url . $step['url']) ?>"><?= htmlspecialchars($step['action']) ?></a>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>

  <?php else: ?>

    <!-- ═══════════════════════════════════════ 3. WIDGET GRID ═══════════════════════════════════════ -->
    <div class="widget-grid" id="widgetGrid">
      <?php foreach ($role_widgets as $w): ?>
        <?php render_widget($w, $widget_data, $enabled_modules, $permissions, $base_url); ?>
      <?php endforeach; ?>
    </div>

  <?php endif; ?>

</div>

<!-- ═══════════════════════════════════════ CUSTOMISE DASHBOARD MODAL ═══════════════════════════════════════ -->
<div class="modal-scrim" id="customiseScrim" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="customiseTitle">
    <header class="modal__head">
      <h2 id="customiseTitle">Customise Dashboard</h2>
      <button class="iconbtn" type="button" id="customiseClose" aria-label="Close">
        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
      </button>
    </header>

    <div class="modal__body">
      <p class="modal__hint">Choose which widgets appear on your dashboard, and drag to reorder them.</p>
      <?php foreach ($modal_groups as $category => $items): ?>
        <p class="modal__group"><?= htmlspecialchars($category) ?></p>
        <ul class="modal__list">
          <?php foreach ($items as $w): ?>
            <li class="modal__row" draggable="true" data-widget-row="<?= htmlspecialchars($w['key']) ?>">
              <i class="fa-solid fa-grip-vertical modal__handle" aria-hidden="true"></i>
              <i class="fa-solid <?= htmlspecialchars($w['icon']) ?> modal__row-icon" aria-hidden="true"></i>
              <span class="modal__row-label"><?= htmlspecialchars($w['title']) ?></span>
              <label class="switch">
                <input type="checkbox" checked data-widget-toggle="<?= htmlspecialchars($w['key']) ?>">
                <span class="switch__track" aria-hidden="true"></span>
              </label>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endforeach; ?>
    </div>

    <footer class="modal__foot">
      <a href="#" id="customiseReset">Reset to default</a>
      <span class="modal__spacer"></span>
      <button class="btn btn--ghost" type="button" id="customiseCancel">Cancel</button>
      <button class="btn" type="button" id="customiseSave">Save</button>
    </footer>
  </div>
</div>

<?php /* ══════════ DEMO ONLY — REMOVE BEFORE PRODUCTION (markup) ══════════ */ ?>
<details class="demo" aria-label="Demo role switcher">
  <summary class="demo__summary">
    <i class="fa-solid fa-flask" aria-hidden="true"></i>
    <span class="demo__summary-role"><?= htmlspecialchars($demo_roles[$demo_role]['user']['role_label']) ?></span>
    <i class="fa-solid fa-chevron-up demo__summary-chev" aria-hidden="true"></i>
  </summary>
  <p class="demo__warn"><i class="fa-solid fa-flask" aria-hidden="true"></i> DEMO ONLY — remove before production</p>
  <p class="demo__group">Role</p>
  <ul class="demo__list">
    <?php foreach ($demo_roles as $key => $r): ?>
      <li>
        <a class="demo__role<?= $key === $demo_role ? ' is-on' : '' ?>"
           href="<?= htmlspecialchars(demo_link(['role' => $key])) ?>"
           <?= $key === $demo_role ? 'aria-current="true"' : '' ?>>
          <span class="demo__av" aria-hidden="true"><?= htmlspecialchars($r['user']['initials']) ?></span>
          <?= htmlspecialchars($r['user']['role_label']) ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>

  <p class="demo__group">Organisation type</p>
  <div class="demo__seg">
    <a class="<?= is_multi_branch() ? '' : 'is-on' ?>" href="<?= htmlspecialchars(demo_link(['orgtype' => 'single'])) ?>">Single Church</a>
    <a class="<?= is_multi_branch() ? 'is-on' : '' ?>" href="<?= htmlspecialchars(demo_link(['orgtype' => 'multi_branch'])) ?>">Multi-Branch</a>
  </div>

  <?php if (is_multi_branch()): ?>
    <p class="demo__group">Terminology</p>
    <div class="demo__seg demo__seg--wrap">
      <?php foreach ($terminology_presets as $key => $preset): ?>
        <a class="<?= $key === $terminology_active ? 'is-on' : '' ?>"
           href="<?= htmlspecialchars(demo_link(['preset' => $key])) ?>"><?= htmlspecialchars(ucfirst($key)) ?></a>
      <?php endforeach; ?>
    </div>

    <p class="demo__group">Scope</p>
    <div class="demo__seg">
      <a class="<?= ($user['scope'] ?? 'organisation') !== 'branch' ? 'is-on' : '' ?>"
         href="<?= htmlspecialchars(demo_link(['scope' => 'organisation', 'branch_id' => null, 'branch' => null])) ?>"><?= htmlspecialchars(t('org_singular')) ?></a>
      <a class="<?= ($user['scope'] ?? 'organisation') === 'branch' ? 'is-on' : '' ?>"
         href="<?= htmlspecialchars(demo_link(['scope' => 'branch', 'branch' => null])) ?>"><?= htmlspecialchars(t('branch_singular')) ?></a>
    </div>

    <?php if (($user['scope'] ?? 'organisation') === 'branch'): ?>
      <p class="demo__group">Which <?= htmlspecialchars(mb_strtolower(t('branch_singular'))) ?>?</p>
      <select class="select demo__select" onchange="location.href=this.value"
              aria-label="Choose a <?= htmlspecialchars(mb_strtolower(t('branch_singular'))) ?>">
        <?php foreach ($branches as $b): ?>
          <option value="<?= htmlspecialchars(demo_link(['scope' => 'branch', 'branch_id' => $b['id'], 'branch' => null])) ?>"
                  <?= (int) $b['id'] === (int) ($user['branch_id'] ?? 0) ? 'selected' : '' ?>>
            <?= htmlspecialchars($b['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    <?php endif; ?>
  <?php endif; ?>

  <a class="demo__toggle" href="<?= htmlspecialchars(demo_link(['new_church' => $is_new_church ? '0' : '1'])) ?>">
    <i class="fa-solid <?= $is_new_church ? 'fa-eye-slash' : 'fa-seedling' ?>" aria-hidden="true"></i>
    <?= $is_new_church ? 'Show normal dashboard' : 'Preview new-church state' ?>
  </a>
</details>
<?php /* ════════════════════════════ END DEMO ════════════════════════════ */ ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function () {
  'use strict';

  var still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ==================================================== chart rendering ==== */
  /* Every chart canvas carries its own {kind, labels, series} as a data
     attribute (see widgets.php); this is the one place Chart.js is touched,
     so every chart on the page shares the same palette and options. */

  if (window.Chart) {
    var PALETTE = ['#662F97', '#B48FDA', '#8F5CC2', '#D3BAEA', '#56287F'];

    Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.color = '#6E6880';
    Chart.defaults.animation = still ? false : { duration: 500 };

    [].forEach.call(document.querySelectorAll('[data-chart]'), function (canvas) {
      var payload;
      try { payload = JSON.parse(canvas.getAttribute('data-chart')); } catch (e) { return; }

      var isDough = payload.kind === 'doughnut';
      var datasets = payload.series.map(function (s, i) {
        if (isDough) {
          return { data: s.data, backgroundColor: PALETTE, borderWidth: 0, hoverOffset: 6 };
        }
        if (payload.kind === 'bar') {
          return {
            label: s.label, data: s.data,
            backgroundColor: PALETTE[i % PALETTE.length],
            borderRadius: 6, maxBarThickness: 34,
          };
        }
        return {
          label: s.label, data: s.data,
          borderColor: PALETTE[i % PALETTE.length],
          backgroundColor: 'rgba(102,47,151,.1)',
          fill: true, tension: .35, pointRadius: 0,
          pointHoverRadius: 4, borderWidth: 2,
        };
      });

      new Chart(canvas, {
        type: payload.kind,
        data: { labels: payload.labels, datasets: datasets },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: isDough, position: 'bottom', labels: { boxWidth: 10, padding: 12 } },
          },
          scales: isDough ? {} : {
            x: { grid: { display: false }, border: { display: false } },
            y: { grid: { color: '#ECE7F3' }, border: { display: false }, beginAtZero: true },
          },
        },
      });
    });
  }

  /* ─────────────── branch comparison (organisation mode) ─────────────── */
  /* Its own chart because the metric dropdown re-renders it; the shared
     [data-chart] loop above is untouched. */
  (function () {
    var cv = document.getElementById('branchCompareChart');
    if (!cv || !window.Chart) { return; }
    var rows;
    try { rows = JSON.parse(cv.getAttribute('data-branch-compare')); } catch (e) { return; }
    var sel = document.getElementById('cmpMetric'), chart = null;
    var LABELS = { members: 'Members', attendance: 'Attendance', giving: 'Giving ($)', growth: 'Growth (%)' };

    function draw() {
      var metric = sel ? sel.value : 'members';
      if (chart) { chart.destroy(); }
      chart = new Chart(cv, {
        type: 'bar',
        data: {
          labels: rows.map(function (r) { return r.name; }),
          datasets: [{
            label: LABELS[metric],
            data: rows.map(function (r) { return r[metric]; }),
            backgroundColor: rows.map(function (r) { return r.growth < 0 ? '#B4243F' : '#662F97'; }),
            borderRadius: 6, maxBarThickness: 16
          }]
        },
        options: {
          indexAxis: 'y', responsive: true, maintainAspectRatio: false,
          animation: still ? false : { duration: 500 },
          plugins: { legend: { display: false } },
          scales: {
            x: { grid: { color: '#ECE7F3' }, border: { display: false }, beginAtZero: true },
            y: { grid: { display: false }, border: { display: false } }
          }
        }
      });
    }
    if (sel) { sel.addEventListener('change', draw); }
    draw();
  })();

  /* ───────── "Add {branch_singular}" beside the count it affects ─────────
     The widget header is rendered by components/widgets.php, which is out of
     scope for this run, so the link is placed into it here instead. */
<?php if ($may_add_branch): ?>
  (function () {
    var head = document.querySelector('[data-widget="total_branches"] .widget__head');
    if (!head) { return; }
    var a = document.createElement('a');
    a.className = 'widget__add';
    a.href = <?= json_encode($base_url . 'branches/add.php') ?>;
    a.innerHTML = '<i class="fa-solid fa-plus" aria-hidden="true"></i> Add <?= addslashes(t('branch_singular')) ?>';
    var menu = head.querySelector('.widget__menu');
    head.insertBefore(a, menu || null);
  })();

  /* The one-branch nudge stays dismissed for the session. */
  (function () {
    var bar = document.querySelector('[data-onebranch]');
    if (!bar) { return; }
    var KEY = 'mutendi-onebranch-dismissed';
    try { if (sessionStorage.getItem(KEY) === '1') { bar.hidden = true; } } catch (e) {}
    bar.querySelector('[data-onebranch-dismiss]').addEventListener('click', function () {
      bar.hidden = true;
      try { sessionStorage.setItem(KEY, '1'); } catch (e) {}
    });
  })();
<?php endif; ?>

  /* ============================================================= alerts ==== */

  [].forEach.call(document.querySelectorAll('.alertbar__dismiss'), function (btn) {
    btn.addEventListener('click', function () { btn.closest('.alertbar').hidden = true; });
  });

  /* ========================================================= date range ==== */

  var rangeLabel = document.querySelector('[data-range-label]');
  [].forEach.call(document.querySelectorAll('[data-range-option]'), function (opt) {
    opt.addEventListener('click', function (e) {
      e.preventDefault();
      if (rangeLabel) { rangeLabel.textContent = opt.textContent.trim(); }
    });
  });

  /* ======================================================== widget menu ==== */

  [].forEach.call(document.querySelectorAll('[data-widget-refresh]'), function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var body = btn.closest('.widget').querySelector('.widget__body');
      body.classList.remove('is-refreshing');
      void body.offsetWidth;
      body.classList.add('is-refreshing');
    });
  });

  var HIDDEN_KEY = 'mutendi-main-hidden-widgets';

  function readHidden() {
    try { return JSON.parse(sessionStorage.getItem(HIDDEN_KEY) || '[]'); } catch (e) { return []; }
  }
  function writeHidden(list) {
    try { sessionStorage.setItem(HIDDEN_KEY, JSON.stringify(list)); } catch (e) {}
  }
  /* Applies the stored list as the whole truth: everything is shown first,
     then the hidden ones are hidden again. Only ever hiding (and never
     clearing) would make "Reset to default" — and re-ticking a toggle —
     unable to bring a widget back. */
  function applyHidden() {
    var hidden = readHidden();
    [].forEach.call(document.querySelectorAll('.widget-grid .widget'), function (w) {
      w.hidden = hidden.indexOf(w.getAttribute('data-widget')) !== -1;
    });
    [].forEach.call(document.querySelectorAll('[data-widget-toggle]'), function (cb) {
      cb.checked = hidden.indexOf(cb.getAttribute('data-widget-toggle')) === -1;
    });
  }
  applyHidden();

  [].forEach.call(document.querySelectorAll('[data-widget-hide]'), function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var card = btn.closest('.widget');
      var key = card.getAttribute('data-widget');
      var hidden = readHidden();
      if (hidden.indexOf(key) === -1) { hidden.push(key); }
      writeHidden(hidden);
      card.hidden = true;
      var cb = document.querySelector('[data-widget-toggle="' + key + '"]');
      if (cb) { cb.checked = false; }
    });
  });

  /* ================================================== customise modal ==== */

  var scrim = document.getElementById('customiseScrim');

  function openModal() {
    scrim.hidden = false;
    document.body.style.overflow = 'hidden';
  }
  function closeModal() {
    scrim.hidden = true;
    document.body.style.overflow = '';
  }

  document.getElementById('customiseBtn').addEventListener('click', openModal);
  document.getElementById('customiseClose').addEventListener('click', closeModal);
  document.getElementById('customiseCancel').addEventListener('click', function () {
    applyHidden();               /* discard any unsaved toggles */
    closeModal();
  });
  scrim.addEventListener('click', function (e) { if (e.target === scrim) { closeModal(); } });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !scrim.hidden) { closeModal(); }
  });

  document.getElementById('customiseSave').addEventListener('click', function () {
    var hidden = [];
    [].forEach.call(document.querySelectorAll('[data-widget-toggle]'), function (cb) {
      if (!cb.checked) { hidden.push(cb.getAttribute('data-widget-toggle')); }
    });
    writeHidden(hidden);
    applyHidden();
    closeModal();
  });

  document.getElementById('customiseReset').addEventListener('click', function (e) {
    e.preventDefault();
    writeHidden([]);
    applyHidden();
  });

  /* Reordering inside the modal is visual only — it never touches the
     dashboard's actual widget order, only the list the user sees here. */
  var dragged = null;
  [].forEach.call(document.querySelectorAll('.modal__row'), function (row) {
    row.addEventListener('dragstart', function () { dragged = row; row.classList.add('is-dragging'); });
    row.addEventListener('dragend',   function () { row.classList.remove('is-dragging'); dragged = null; });
    row.addEventListener('dragover',  function (e) {
      e.preventDefault();
      if (!dragged || dragged === row) { return; }
      var list = row.parentElement;
      var rows = [].slice.call(list.children);
      if (rows.indexOf(dragged) < rows.indexOf(row)) { list.insertBefore(dragged, row.nextSibling); }
      else { list.insertBefore(dragged, row); }
    });
  });

  /* ============================================================ calendar ==== */

  [].forEach.call(document.querySelectorAll('.cal'), function (cal) {
    var buttons = cal.querySelectorAll('.cal__day[data-day]');
    var side = cal.querySelector('.cal__side');
    var eventsAttr = cal.getAttribute('data-events');
    var events = {};
    try { events = JSON.parse(eventsAttr || '{}'); } catch (e) {}

    buttons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        [].forEach.call(buttons, function (b) { b.classList.remove('is-selected'); });
        btn.classList.add('is-selected');
        var day = btn.getAttribute('data-day');
        var list = events[day];
        if (list && list.length) {
          side.innerHTML = '<p class="cal__side-date">' + btn.getAttribute('data-label') + '</p><ul class="cal__side-list">' +
            list.map(function (t) { return '<li>' + t.replace(/</g, '&lt;') + '</li>'; }).join('') + '</ul>';
        } else {
          side.innerHTML = '<p class="cal__side-empty">No events on this day</p>';
        }
      });
    });
  });

})();
</script>

<?php require __DIR__ . '/components/footer.php'; ?>

<?php
/**
 * Mutendi CMS — All {branch_plural} directory.
 *
 * The organisation-wide list of local churches: cards, table, map and a
 * performance comparison. UI only — filters, sorting, selection and every
 * form are visual.
 *
 * Organisation scope only. A branch-scope user has no business here and is
 * shown a clean no-access state instead.
 */

require __DIR__ . '/../includes/config.php';

/* ══════════════ DEMO ONLY — REMOVE BEFORE PRODUCTION ══════════════ */
$demo_role       = isset($_GET['role'], $demo_roles[$_GET['role']]) ? $_GET['role'] : 'church_admin';
$user            = array_merge($user, $demo_roles[$demo_role]['user']);
$permissions     = $demo_roles[$demo_role]['perms'];
$enabled_modules = $demo_roles[$demo_role]['modules'];
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
    function mu_date(string $iso, string $fmt = 'd M Y'): string { return date($fmt, strtotime($iso)); }
    function mu_ago(?int $days): string {
        if ($days === null) { return 'Never'; }
        if ($days <= 0) { return 'Today'; }
        if ($days === 1) { return 'Yesterday'; }
        if ($days < 7)  { return $days . ' days ago'; }
        if ($days < 31) { $w = (int) floor($days / 7); return $w . ' week' . ($w > 1 ? 's' : '') . ' ago'; }
        $m = (int) floor($days / 30);
        return $m . ' month' . ($m > 1 ? 's' : '') . ' ago';
    }
}

/* The switcher's initials strip the saint/honorific; reuse it so a branch
   looks identical here and in the top bar. */
if (!function_exists('branch_initials')) {
    require_once __DIR__ . '/../components/branch-switcher.php';
}

/**
 * The leader's title, unless their name already carries one — config stores
 * the head office leader as "Bishop Nathaniel Chikomo", and "Priest Bishop
 * Nathaniel Chikomo" is nonsense.
 */
function branch_leader_label(array $b): string {
    $titled = (bool) preg_match('/^(Rev\.?|Bishop|Pastor|Minister|Fr\.?|Archdeacon|Canon|Dr\.?)\s/i', $b['leader_name']);
    if ($titled) { return $b['leader_name']; }
    $title = ($b['type'] ?? '') === 'head_office' ? t('org_leader_title') : t('leader_title');
    return $title . ' ' . $b['leader_name'];
}

$has_access = ($user['scope'] ?? 'organisation') !== 'branch';

/* --------------------------------------------------------- derived data -- */

$today = new DateTimeImmutable(date('Y-m-d'));

/**
 * Eight weeks of attendance per branch. Derived deterministically from the
 * branch's own figures so the same branch always draws the same sparkline.
 * LATER: SELECT week, SUM(present) FROM attendance
 *        WHERE branch_id = :id AND week >= :eight_weeks_ago GROUP BY week;
 */
function branch_spark(array $b): array {
    $base = max(4, (int) $b['avg_attendance']);
    $seed = crc32($b['code']);
    $out  = [];
    for ($i = 0; $i < 8; $i++) {
        $wobble = (($seed >> ($i * 3)) % 45) - 22;              /* -22%..+22% */
        $drift  = ($b['growth_percent'] / 100) * $base * ($i / 8);
        $out[]  = max(3, (int) round($base * (1 + $wobble / 100) + $drift));
    }
    return $out;
}

$rows = [];
foreach ($branches as $b) {
    if (!can_see_branch($b['id'])) { continue; }

    $lastDays = (int) $today->diff(new DateTimeImmutable($b['last_activity']))->days;
    $perMember = $b['members_count'] > 0 ? $b['monthly_giving'] / $b['members_count'] : 0;

    /* A single comparable score: growth, attendance rate and giving per head,
       each normalised and weighted. LATER: agreed with the diocese office. */
    $score = (int) round(
        min(100, max(0, ($b['growth_percent'] + 5) * 4)) * 0.35 +
        min(100, $b['attendance_rate']) * 0.40 +
        min(100, $perMember * 6) * 0.25
    );

    $b['last_days']  = $lastDays;
    $b['per_member'] = $perMember;
    $b['score']      = $score;
    $b['spark']      = branch_spark($b);
    $b['stale']      = $lastDays >= 14;
    $rows[] = $b;
}

/* Rankings for the performance view. */
$ranked = $rows;
usort($ranked, fn($x, $y) => $y['score'] <=> $x['score']);

$growing = $rows;
usort($growing, fn($x, $y) => $y['growth_percent'] <=> $x['growth_percent']);
$fastest = array_slice($growing, 0, 5);

$attention = array_values(array_filter($rows, fn($b) => $b['growth_percent'] < 0 || $b['last_days'] >= 30));
usort($attention, fn($x, $y) => $x['growth_percent'] <=> $y['growth_percent']);
$attention = array_slice($attention, 0, 5);

$total_members    = array_sum(array_column($rows, 'members_count'));
$total_attendance = array_sum(array_column($rows, 'avg_attendance'));
$total_giving     = array_sum(array_column($rows, 'monthly_giving'));
$avg_attendance   = $rows ? (int) round($total_attendance / count($rows)) : 0;
$max_members      = $rows ? max(array_column($rows, 'members_count')) : 1;

$groups = [];
foreach ($rows as $b) { $groups[$b['group_name']][] = $b; }

$group_tones = [];
$tone_pool = ['var(--info)', 'var(--brand-500)', '#0F766E', 'var(--warn)', '#6D28D9'];
foreach (array_keys($groups) as $i => $g) { $group_tones[$g] = $tone_pool[$i % count($tone_pool)]; }

/* Organisation-level entry points. Same gate as everywhere else. */
$may_add_branch = $has_access && is_multi_branch()
    && ($user['scope'] ?? 'organisation') !== 'branch'
    && mu_can('branches.add');

/* A brand-new organisation has only its head office. */
$only_one_branch = $may_add_branch && count($rows) === 1;

$page_title = 'All ' . t('branch_plural');
require __DIR__ . '/../components/header.php';
?>

<div class="page">

<?php if (!$has_access): ?>

  <section class="panel" style="margin-top:8px">
    <div class="empty">
      <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-lock"></i></span>
      <h3>You don't have access to this page</h3>
      <p>
        This directory covers every <?= htmlspecialchars(mb_strtolower(t('branch_singular'))) ?> in
        <?= htmlspecialchars($organisation['name']) ?>. Your account is scoped to
        <strong><?= htmlspecialchars($user['branch_name'] ?? t('branch_singular')) ?></strong>, so it opens on your own dashboard instead.
      </p>
      <a class="btn" href="<?= $base_url ?>index.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to my dashboard</a>
    </div>
  </section>

<?php else: ?>

  <!-- ═════════════════════════════ HEADER ═════════════════════════════ -->
  <header class="page__head">
    <div>
      <nav class="crumbs" aria-label="Breadcrumb">
        <a href="<?= $base_url ?>index.php">Dashboard</a>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span><?= htmlspecialchars(t('org_singular')) ?></span>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span aria-current="page">All <?= htmlspecialchars(t('branch_plural')) ?></span>
      </nav>
      <div class="title-row">
        <h1 class="page__title">All <?= htmlspecialchars(t('branch_plural')) ?></h1>
        <span class="count-chip" data-count="<?= count($rows) ?>">0</span>
      </div>
      <p class="page__sub">Every <?= htmlspecialchars(mb_strtolower(t('branch_singular'))) ?> under <?= htmlspecialchars($organisation['name']) ?>.</p>
    </div>

    <div class="page__actions">
      <?php if (mu_can('branches.add')): ?>
        <a class="btn" href="<?= $base_url ?>branches/add.php">
          <i class="fa-solid fa-plus" aria-hidden="true"></i> Add <?= htmlspecialchars(t('branch_singular')) ?>
        </a>
      <?php endif; ?>
      <button class="btn btn--ghost" type="button" data-open="modalCompare">
        <i class="fa-solid fa-code-compare" aria-hidden="true"></i> Compare
      </button>
      <?php if (mu_can('members.export')): ?>
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
      <?php endif; ?>
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

  <!-- ═════════════════════════════ STAT STRIP ═════════════════════════════ -->
  <div class="stat-strip<?= mu_can('finance.reports') ? '' : ' stat-strip--3' ?>">
    <?php
      $tiles = [
        ['all',    'Total ' . t('branch_plural'), count($rows),    'fa-church',        'blue'],
        ['members','Total Members',               $total_members,  'fa-users',         'green'],
        ['att',    'Average Attendance',          $avg_attendance, 'fa-clipboard-check','purple'],
      ];
      if (mu_can('finance.reports')) {
        $tiles[] = ['giving', 'Monthly Giving', $total_giving, 'fa-hand-holding-dollar', 'amber'];
      }
      foreach ($tiles as [$key, $label, $value, $icon, $tone]):
    ?>
      <button class="stat-tile<?= $key === 'all' ? ' is-on' : '' ?>" type="button"
              data-stat-filter="<?= $key ?>" aria-pressed="<?= $key === 'all' ? 'true' : 'false' ?>">
        <span class="stat-tile__icon tone-<?= $tone ?>" aria-hidden="true"><i class="fa-solid <?= $icon ?>"></i></span>
        <span class="stat-tile__body">
          <span class="stat-tile__value" data-count="<?= $value ?>"<?= $key === 'giving' ? ' data-money="1"' : '' ?>>0</span>
          <span class="stat-tile__label"><?= htmlspecialchars($label) ?></span>
        </span>
      </button>
    <?php endforeach; ?>
  </div>

  <!-- ═════════════════════════ TOOLBAR ═════════════════════════ -->
  <div class="toolbar">
    <div class="viewswitch" role="group" aria-label="View">
      <button type="button" data-view="cards" aria-pressed="true"  aria-label="Card grid view"><i class="fa-solid fa-table-cells-large" aria-hidden="true"></i></button>
      <button type="button" data-view="table" aria-pressed="false" aria-label="Table view"><i class="fa-solid fa-table-list" aria-hidden="true"></i></button>
      <button type="button" data-view="map"   aria-pressed="false" aria-label="Map view"><i class="fa-solid fa-map-location-dot" aria-hidden="true"></i></button>
      <button type="button" data-view="perf"  aria-pressed="false" aria-label="Performance view"><i class="fa-solid fa-ranking-star" aria-hidden="true"></i></button>
    </div>
    <p style="color:var(--muted);font-size:12.5px;font-weight:600"><span data-result-count><?= count($rows) ?></span> shown</p>
  </div>

  <!-- ═════════════════════════ FILTERS ═════════════════════════ -->
  <section class="filters" id="filters">
    <button class="filters__toggle" type="button" id="filtersToggle" aria-expanded="false">
      <i class="fa-solid fa-sliders" aria-hidden="true"></i> Filters
      <span class="count-chip" data-active-filters hidden>0</span>
      <span style="flex:1"></span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
    </button>
    <div class="filters__grid">
      <div class="field col-2">
        <label for="fSearch">Search</label>
        <div class="search-field">
          <i class="fa-solid fa-magnifying-glass search-field__icon" aria-hidden="true"></i>
          <input class="input" type="search" id="fSearch" data-search
                 placeholder="Search <?= htmlspecialchars(mb_strtolower(t('branch_plural'))) ?>, codes or <?= htmlspecialchars(mb_strtolower(t('leader_plural'))) ?>&hellip;">
          <button class="search-field__clear" type="button" data-search-clear hidden aria-label="Clear search"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
      </div>
      <div class="field">
        <label for="fGroup"><?= htmlspecialchars(t('group_singular')) ?></label>
        <select class="select" id="fGroup" data-filter>
          <option>All</option>
          <?php foreach (array_keys($groups) as $g): ?><option><?= htmlspecialchars($g) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="fStatus">Status</label>
        <select class="select" id="fStatus" data-filter><option>All</option><option>Active</option><option>Inactive</option><option>Planting</option></select>
      </div>
      <div class="field">
        <label for="fProvince">Province</label>
        <select class="select" id="fProvince" data-filter>
          <option>All</option>
          <?php foreach (array_unique(array_column($rows, 'province')) as $p): ?><option><?= htmlspecialchars($p) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="fSize">Size</label>
        <select class="select" id="fSize" data-filter><option>All</option><option>Under 100</option><option>100-300</option><option>Over 300</option></select>
      </div>
      <div class="field">
        <label for="fSort">Sort by</label>
        <select class="select" id="fSort"><option>Name</option><option>Largest</option><option>Fastest Growing</option><option>Needs Attention</option></select>
      </div>
      <div class="filters__actions">
        <button class="btn" type="button" data-toast="Filters applied"><i class="fa-solid fa-check" aria-hidden="true"></i> Apply</button>
        <button class="btn btn--ghost" type="button" data-reset-filters><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Reset</button>
      </div>
    </div>
    <div class="chips-row" data-filter-chips hidden></div>
  </section>

  <!-- ═════════════════════════ CONTENT ═════════════════════════ -->
  <section id="listPanel" aria-live="polite">

    <div data-skeleton>
      <div class="cardgrid cardgrid--3">
        <?php for ($i = 0; $i < 6; $i++): ?>
          <div class="sk-card" style="padding:0;overflow:hidden">
            <span class="sk" style="height:58px;display:block;border-radius:0"></span>
            <div style="padding:16px">
              <span class="sk sk--text" style="width:60%;display:block"></span>
              <span class="sk sk--line" style="width:40%"></span>
              <div style="display:flex;gap:8px;margin-top:16px"><span class="sk sk--av"></span><span class="sk sk--text" style="flex:1"></span></div>
              <span class="sk" style="height:30px;display:block;margin-top:16px"></span>
            </div>
          </div>
        <?php endfor; ?>
      </div>
    </div>

    <div data-content>

      <!-- ─────────────── CARD GRID ─────────────── -->
      <div data-view-panel="cards">
        <div class="cardgrid cardgrid--3 stagger" id="branchCards">
          <?php foreach ($rows as $b):
              $tone = $group_tones[$b['group_name']] ?? 'var(--brand-500)';
          ?>
            <article class="bcard" data-card data-id="<?= (int) $b['id'] ?>"
                     data-name="<?= htmlspecialchars(mb_strtolower($b['name'] . ' ' . $b['code'] . ' ' . $b['leader_name'])) ?>"
                     data-group="<?= htmlspecialchars($b['group_name']) ?>"
                     data-status="<?= htmlspecialchars(ucfirst($b['status'])) ?>"
                     data-province="<?= htmlspecialchars($b['province']) ?>"
                     data-members="<?= (int) $b['members_count'] ?>"
                     data-growth="<?= (float) $b['growth_percent'] ?>"
                     data-score="<?= (int) $b['score'] ?>"
                     data-stale="<?= $b['stale'] ? '1' : '0' ?>">

              <header class="bcard__strip" style="background:<?= $tone ?>">
                <span class="av av--md <?= mu_avc($b['name']) ?> bcard__av" aria-hidden="true"><?= htmlspecialchars(branch_initials($b['name'])) ?></span>
                <span class="spill is-<?= htmlspecialchars($b['status']) ?> bcard__status"><?= htmlspecialchars(ucfirst($b['status'])) ?></span>
              </header>

              <div class="bcard__body">
                <h3 class="bcard__name">
                  <?= htmlspecialchars($b['name']) ?>
                  <?php if ($b['type'] === 'head_office'): ?>
                    <span class="bswitch__ho"><?= htmlspecialchars(t('org_singular')) ?></span>
                  <?php endif; ?>
                </h3>
                <p class="bcard__meta"><?= htmlspecialchars($b['code']) ?> &middot; <?= htmlspecialchars($b['group_name']) ?></p>

                <div class="bcard__leader">
                  <?= mu_av($b['leader_name'], 'sm') ?>
                  <span style="min-width:0">
                    <span class="bcard__leadername"><?= htmlspecialchars(branch_leader_label($b)) ?></span>
                    <a class="bcard__phone" href="tel:<?= htmlspecialchars(str_replace(' ', '', $b['leader_phone'])) ?>">
                      <i class="fa-solid fa-phone" aria-hidden="true"></i> <?= htmlspecialchars($b['leader_phone']) ?>
                    </a>
                  </span>
                </div>

                <div class="bcard__stats">
                  <span><strong><?= number_format((int) $b['members_count']) ?></strong>Members</span>
                  <span><strong><?= number_format((int) $b['avg_attendance']) ?></strong>Avg Att.</span>
                  <?php if (mu_can('finance.reports')): ?>
                    <span><strong>$<?= number_format($b['monthly_giving'], 0) ?></strong>Giving</span>
                  <?php endif; ?>
                </div>

                <div class="bcard__spark">
                  <p class="bcard__sparklabel">Attendance, last 8 weeks</p>
                  <span class="spark" aria-hidden="true">
                    <?php $mx = max($b['spark']); foreach ($b['spark'] as $v): ?>
                      <span style="height:<?= max(12, (int) round($v / $mx * 100)) ?>%"></span>
                    <?php endforeach; ?>
                  </span>
                </div>

                <p class="bcard__addr">
                  <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                  <?= htmlspecialchars($b['address']) ?>, <?= htmlspecialchars($b['suburb']) ?>
                </p>
                <p class="bcard__last<?= $b['stale'] ? ' is-stale' : '' ?>">
                  <i class="fa-regular fa-clock" aria-hidden="true"></i>
                  Last activity <?= mu_ago($b['last_days']) ?>
                </p>
              </div>

              <footer class="bcard__foot">
                <button class="bcard__link" type="button" data-open-branch="<?= (int) $b['id'] ?>">View</button>
                <a class="bcard__link" href="<?= $base_url ?>index.php?branch=<?= (int) $b['id'] ?>">Switch To</a>
                <?php if (mu_mod('communication')): ?>
                  <button class="bcard__link" type="button" data-open="modalMessage" data-one="<?= htmlspecialchars($b['leader_name']) ?>">Message</button>
                <?php endif; ?>
              </footer>
            </article>
          <?php endforeach; ?>

          <?php if ($may_add_branch): ?>
            <a class="addcard" href="<?= $base_url ?>branches/add.php">
              <span class="addcard__icon" aria-hidden="true"><i class="fa-solid fa-plus"></i></span>
              <span class="addcard__label">Add <?= htmlspecialchars(t('branch_singular')) ?></span>
              <span class="addcard__hint">Register another <?= htmlspecialchars(mb_strtolower(t('branch_singular'))) ?> under <?= htmlspecialchars($organisation['name']) ?></span>
            </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- ─────────────── TABLE ─────────────── -->
      <div data-view-panel="table" hidden>
        <div class="panel">
          <div class="dt-wrap">
            <table class="dt">
              <thead>
                <tr>
                  <th style="width:38px"><input class="check" type="checkbox" data-check-all aria-label="Select all"></th>
                  <th style="width:44px">#</th>
                  <th><?= htmlspecialchars(t('branch_singular')) ?></th>
                  <th><?= htmlspecialchars(t('group_singular')) ?></th>
                  <th><?= htmlspecialchars(t('leader_title')) ?></th>
                  <th>Location</th>
                  <th>Members</th>
                  <th>Avg Attendance</th>
                  <?php if (mu_can('finance.reports')): ?><th>Monthly Giving</th><?php endif; ?>
                  <th>Growth</th>
                  <th>Status</th>
                  <th>Last Activity</th>
                  <th style="text-align:right">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($rows as $i => $b): ?>
                  <tr data-row data-id="<?= (int) $b['id'] ?>"
                      data-name="<?= htmlspecialchars(mb_strtolower($b['name'] . ' ' . $b['code'] . ' ' . $b['leader_name'])) ?>"
                      data-group="<?= htmlspecialchars($b['group_name']) ?>"
                      data-status="<?= htmlspecialchars(ucfirst($b['status'])) ?>"
                      data-province="<?= htmlspecialchars($b['province']) ?>"
                      data-members="<?= (int) $b['members_count'] ?>"
                      data-growth="<?= (float) $b['growth_percent'] ?>"
                      data-score="<?= (int) $b['score'] ?>"
                      data-stale="<?= $b['stale'] ? '1' : '0' ?>">
                    <td><input class="check" type="checkbox" data-row-check aria-label="Select <?= htmlspecialchars($b['name']) ?>"></td>
                    <td class="num"><?= $i + 1 ?></td>
                    <td>
                      <button class="person" type="button" data-open-branch="<?= (int) $b['id'] ?>">
                        <span class="av av--sm <?= mu_avc($b['name']) ?>" aria-hidden="true"><?= htmlspecialchars(branch_initials($b['name'])) ?></span>
                        <span class="person__text">
                          <span class="person__name"><?= htmlspecialchars($b['name']) ?></span>
                          <span class="tsub"><?= htmlspecialchars($b['code']) ?></span>
                        </span>
                      </button>
                    </td>
                    <td class="nowrap"><?= htmlspecialchars($b['group_name']) ?></td>
                    <td>
                      <span class="person"><?= mu_av($b['leader_name'], 'sm') ?>
                        <span class="person__text">
                          <span class="person__name"><?= htmlspecialchars($b['leader_name']) ?></span>
                          <span class="tsub"><?= htmlspecialchars($b['leader_phone']) ?></span>
                        </span>
                      </span>
                    </td>
                    <td class="nowrap"><?= htmlspecialchars($b['suburb']) ?><span class="tsub"><?= htmlspecialchars($b['city']) ?>, <?= htmlspecialchars($b['province']) ?></span></td>
                    <td style="min-width:120px">
                      <span class="minibar">
                        <strong style="color:var(--ink)"><?= number_format((int) $b['members_count']) ?></strong>
                        <span class="minibar__track"><span class="minibar__fill" style="width:<?= (int) round($b['members_count'] / $max_members * 100) ?>%;background:var(--brand-500)"></span></span>
                      </span>
                    </td>
                    <td class="nowrap"><?= number_format((int) $b['avg_attendance']) ?><span class="tsub"><?= (int) $b['attendance_rate'] ?>% rate</span></td>
                    <?php if (mu_can('finance.reports')): ?>
                      <td class="nowrap is-strong">$<?= number_format($b['monthly_giving'], 0) ?></td>
                    <?php endif; ?>
                    <td class="nowrap">
                      <span class="growth <?= $b['growth_percent'] >= 0 ? 'is-up' : 'is-down' ?>">
                        <i class="fa-solid fa-arrow-<?= $b['growth_percent'] >= 0 ? 'up' : 'down' ?>" aria-hidden="true"></i>
                        <?= number_format(abs($b['growth_percent']), 1) ?>%
                      </span>
                    </td>
                    <td><span class="spill is-<?= htmlspecialchars($b['status']) ?>"><?= htmlspecialchars(ucfirst($b['status'])) ?></span></td>
                    <td class="nowrap"<?= $b['stale'] ? ' style="color:var(--warn)"' : '' ?>><?= mu_ago($b['last_days']) ?></td>
                    <td>
                      <div class="rowacts">
                        <button class="iconbtn iconbtn--sm" type="button" data-open-branch="<?= (int) $b['id'] ?>" aria-label="View <?= htmlspecialchars($b['name']) ?>"><i class="fa-regular fa-eye" aria-hidden="true"></i></button>
                        <a class="iconbtn iconbtn--sm" href="<?= $base_url ?>index.php?branch=<?= (int) $b['id'] ?>" aria-label="Switch to <?= htmlspecialchars($b['name']) ?>"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i></a>
                        <?php if (mu_can('branches.add')): ?>
                          <button class="iconbtn iconbtn--sm" type="button" data-toast="Opening editor&hellip;" aria-label="Edit <?= htmlspecialchars($b['name']) ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i></button>
                        <?php endif; ?>
                        <?php if (mu_mod('communication')): ?>
                          <button class="iconbtn iconbtn--sm" type="button" data-open="modalMessage" data-one="<?= htmlspecialchars($b['leader_name']) ?>" aria-label="Message <?= htmlspecialchars($b['leader_name']) ?>"><i class="fa-regular fa-comment" aria-hidden="true"></i></button>
                        <?php endif; ?>
                        <div class="drop" data-menu>
                          <button class="iconbtn iconbtn--sm" type="button" aria-haspopup="true" aria-expanded="false" data-menu-btn aria-label="More actions"><i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i></button>
                          <div class="menu menu--sm" data-menu-panel hidden>
                            <a class="menu__item" href="#" data-open-branch="<?= (int) $b['id'] ?>"><i class="fa-regular fa-eye" aria-hidden="true"></i> View Profile</a>
                            <a class="menu__item" href="<?= $base_url ?>index.php?branch=<?= (int) $b['id'] ?>"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> Switch to this <?= htmlspecialchars(t('branch_singular')) ?></a>
                            <?php if (mu_can('branches.add')): ?><a class="menu__item" href="#" data-toast="Opening editor&hellip;"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</a><?php endif; ?>
                            <a class="menu__item" href="<?= $base_url ?>members/all.php?branch=<?= (int) $b['id'] ?>"><i class="fa-solid fa-users" aria-hidden="true"></i> View Members</a>
                            <a class="menu__item" href="#" data-toast="Opening reports&hellip;"><i class="fa-solid fa-chart-column" aria-hidden="true"></i> View Reports</a>
                            <?php if (mu_mod('communication')): ?><a class="menu__item" href="#" data-open="modalMessage" data-one="<?= htmlspecialchars($b['leader_name']) ?>"><i class="fa-regular fa-comment" aria-hidden="true"></i> Message <?= htmlspecialchars(t('leader_title')) ?></a><?php endif; ?>
                            <?php if (mu_can('branches.add')): ?>
                              <div class="menu__sep" role="separator"></div>
                              <a class="menu__item" href="#" data-toast="<?= htmlspecialchars(t('branch_singular')) ?> deactivated"><i class="fa-solid fa-ban" aria-hidden="true"></i> Deactivate</a>
                            <?php endif; ?>
                            <?php if (mu_can('members.delete')): ?>
                              <a class="menu__item menu__item--danger" href="#" data-open="modalDelete" data-code="<?= htmlspecialchars($b['code']) ?>" data-bname="<?= htmlspecialchars($b['name']) ?>"><i class="fa-solid fa-trash" aria-hidden="true"></i> Delete</a>
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
            <?php foreach ($rows as $b): ?>
              <article class="pcard" data-card data-id="<?= (int) $b['id'] ?>"
                       data-name="<?= htmlspecialchars(mb_strtolower($b['name'] . ' ' . $b['code'] . ' ' . $b['leader_name'])) ?>"
                       data-group="<?= htmlspecialchars($b['group_name']) ?>"
                       data-status="<?= htmlspecialchars(ucfirst($b['status'])) ?>"
                       data-province="<?= htmlspecialchars($b['province']) ?>"
                       data-members="<?= (int) $b['members_count'] ?>"
                       data-growth="<?= (float) $b['growth_percent'] ?>"
                       data-score="<?= (int) $b['score'] ?>"
                       data-stale="<?= $b['stale'] ? '1' : '0' ?>">
                <button class="pcard__main" type="button" data-card-toggle>
                  <span class="av av--md <?= mu_avc($b['name']) ?>" aria-hidden="true"><?= htmlspecialchars(branch_initials($b['name'])) ?></span>
                  <span class="pcard__text">
                    <span class="pcard__name"><?= htmlspecialchars($b['name']) ?></span>
                    <span class="pcard__meta"><?= htmlspecialchars($b['code']) ?> &middot; <?= number_format((int) $b['members_count']) ?> members</span>
                  </span>
                  <span class="spill is-<?= htmlspecialchars($b['status']) ?>"><?= htmlspecialchars(ucfirst($b['status'])) ?></span>
                  <i class="fa-solid fa-chevron-down pcard__chev" aria-hidden="true"></i>
                </button>
                <div class="pcard__more">
                  <dl>
                    <div class="pcard__row"><dt><?= htmlspecialchars(t('leader_title')) ?></dt><dd><?= htmlspecialchars($b['leader_name']) ?></dd></div>
                    <div class="pcard__row"><dt>Phone</dt><dd><?= htmlspecialchars($b['leader_phone']) ?></dd></div>
                    <div class="pcard__row"><dt><?= htmlspecialchars(t('group_singular')) ?></dt><dd><?= htmlspecialchars($b['group_name']) ?></dd></div>
                    <div class="pcard__row"><dt>Location</dt><dd><?= htmlspecialchars($b['suburb']) ?>, <?= htmlspecialchars($b['city']) ?></dd></div>
                    <div class="pcard__row"><dt>Avg attendance</dt><dd><?= number_format((int) $b['avg_attendance']) ?> (<?= (int) $b['attendance_rate'] ?>%)</dd></div>
                    <?php if (mu_can('finance.reports')): ?>
                      <div class="pcard__row"><dt>Monthly giving</dt><dd>$<?= number_format($b['monthly_giving'], 0) ?></dd></div>
                    <?php endif; ?>
                    <div class="pcard__row"><dt>Growth</dt><dd><?= $b['growth_percent'] >= 0 ? '+' : '' ?><?= number_format($b['growth_percent'], 1) ?>%</dd></div>
                    <div class="pcard__row"><dt>Last activity</dt><dd><?= mu_ago($b['last_days']) ?></dd></div>
                  </dl>
                  <div class="pcard__acts">
                    <button class="chip-btn" type="button" data-open-branch="<?= (int) $b['id'] ?>">View</button>
                    <a class="chip-btn" href="<?= $base_url ?>index.php?branch=<?= (int) $b['id'] ?>" style="text-align:center">Switch To</a>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>

          <div class="pager">
            <span>Showing <strong>1</strong> to <strong><?= count($rows) ?></strong> of <strong><?= count($rows) ?></strong> <?= htmlspecialchars(mb_strtolower(t('branch_plural'))) ?></span>
            <div class="pager__pages">
              <button type="button" disabled aria-label="Previous page"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
              <button type="button" aria-current="page">1</button>
              <button type="button" aria-label="Next page" disabled><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
            </div>
            <label class="pager__size">Show
              <select class="select"><option>12</option><option>24</option><option>48</option></select>
            </label>
          </div>
        </div>
      </div>

      <!-- ─────────────── MAP (styled placeholder) ─────────────── -->
      <div data-view-panel="map" hidden>
        <div class="mapbox">
          <div class="mapbox__canvas">
            <?php
              /* Fixed positions derived from the stored lat/long, scaled into
                 the panel. A static illustration — no map API is called. */
              $lats = array_column($rows, 'latitude');
              $lngs = array_column($rows, 'longitude');
              $latMin = min($lats); $latMax = max($lats);
              $lngMin = min($lngs); $lngMax = max($lngs);
              foreach ($rows as $b):
                  $x = $lngMax > $lngMin ? (($b['longitude'] - $lngMin) / ($lngMax - $lngMin)) * 78 + 11 : 50;
                  $y = $latMax > $latMin ? (1 - ($b['latitude'] - $latMin) / ($latMax - $latMin)) * 70 + 15 : 50;
                  $tone = $group_tones[$b['group_name']] ?? 'var(--brand-500)';
            ?>
              <button class="mappin" type="button" style="left:<?= round($x, 2) ?>%;top:<?= round($y, 2) ?>%"
                      data-open-branch="<?= (int) $b['id'] ?>" data-mappin
                      data-group="<?= htmlspecialchars($b['group_name']) ?>"
                      aria-label="<?= htmlspecialchars($b['name']) ?>">
                <span class="mappin__dot" style="background:<?= $tone ?>"><span><?= (int) round($b['members_count'] / 100) ?></span></span>
                <span class="mappin__label"><?= htmlspecialchars($b['name']) ?></span>
              </button>
            <?php endforeach; ?>
          </div>

          <aside class="mapbox__side">
            <div class="mapbox__legend">
              <p style="color:var(--faint);font-size:10px;font-weight:800;letter-spacing:.12em;text-transform:uppercase"><?= htmlspecialchars(t('group_plural')) ?></p>
              <?php foreach ($groups as $g => $list): ?>
                <button class="leg" type="button" data-group-filter="<?= htmlspecialchars($g) ?>" style="text-align:left">
                  <span class="leg__sw" style="background:<?= $group_tones[$g] ?>"></span>
                  <?= htmlspecialchars($g) ?>
                  <span style="margin-left:auto;color:var(--faint)"><?= count($list) ?></span>
                </button>
              <?php endforeach; ?>
            </div>
            <div class="mapbox__list">
              <div class="clist" style="border:0;border-radius:0">
                <?php foreach ($rows as $b): ?>
                  <button class="crow" type="button" data-map-row data-group="<?= htmlspecialchars($b['group_name']) ?>"
                          data-open-branch="<?= (int) $b['id'] ?>" style="width:100%;text-align:left">
                    <span class="crow__dot" style="background:<?= $group_tones[$b['group_name']] ?>"></span>
                    <span class="crow__name"><?= htmlspecialchars($b['name']) ?></span>
                    <span class="crow__phone"><?= number_format((int) $b['members_count']) ?></span>
                  </button>
                <?php endforeach; ?>
              </div>
            </div>
          </aside>
        </div>
      </div>

      <!-- ─────────────── PERFORMANCE ─────────────── -->
      <div data-view-panel="perf" hidden>

        <section class="panel" style="margin-bottom:16px">
          <header class="panel__head">
            <span class="stat-tile__icon tone-purple" style="width:32px;height:32px;font-size:13px" aria-hidden="true"><i class="fa-solid fa-ranking-star"></i></span>
            <h2><?= htmlspecialchars(t('branch_singular')) ?> Rankings</h2>
            <span class="count-chip"><?= count($ranked) ?></span>
          </header>
          <div class="dt-wrap">
            <table class="dt">
              <thead>
                <tr>
                  <th style="width:64px">Rank</th>
                  <th><?= htmlspecialchars(t('branch_singular')) ?></th>
                  <th>Members</th>
                  <th>Growth</th>
                  <th>Attendance Rate</th>
                  <?php if (mu_can('finance.reports')): ?><th>Giving / Member</th><?php endif; ?>
                  <th style="min-width:170px">Score</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($ranked as $i => $b):
                    $medal = [0 => 'is-gold', 1 => 'is-silver', 2 => 'is-bronze'][$i] ?? '';
                ?>
                  <tr>
                    <td>
                      <?php if ($i < 3): ?>
                        <span class="medal <?= $medal ?>" aria-label="Rank <?= $i + 1 ?>"><i class="fa-solid fa-medal" aria-hidden="true"></i><?= $i + 1 ?></span>
                      <?php else: ?>
                        <span class="num" style="padding-left:8px"><?= $i + 1 ?></span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <button class="person" type="button" data-open-branch="<?= (int) $b['id'] ?>">
                        <span class="av av--sm <?= mu_avc($b['name']) ?>" aria-hidden="true"><?= htmlspecialchars(branch_initials($b['name'])) ?></span>
                        <span class="person__text">
                          <span class="person__name"><?= htmlspecialchars($b['name']) ?></span>
                          <span class="tsub"><?= htmlspecialchars($b['group_name']) ?></span>
                        </span>
                      </button>
                    </td>
                    <td class="nowrap"><?= number_format((int) $b['members_count']) ?></td>
                    <td class="nowrap">
                      <span class="growth <?= $b['growth_percent'] >= 0 ? 'is-up' : 'is-down' ?>">
                        <i class="fa-solid fa-arrow-<?= $b['growth_percent'] >= 0 ? 'up' : 'down' ?>" aria-hidden="true"></i>
                        <?= number_format(abs($b['growth_percent']), 1) ?>%
                      </span>
                    </td>
                    <td class="nowrap"><?= (int) $b['attendance_rate'] ?>%</td>
                    <?php if (mu_can('finance.reports')): ?>
                      <td class="nowrap">$<?= number_format($b['per_member'], 2) ?></td>
                    <?php endif; ?>
                    <td>
                      <span class="minibar">
                        <strong style="color:var(--ink);min-width:28px"><?= (int) $b['score'] ?></strong>
                        <span class="minibar__track" style="height:7px">
                          <span class="minibar__fill" style="width:<?= (int) $b['score'] ?>%;background:<?= $b['score'] >= 70 ? 'var(--ok)' : ($b['score'] >= 50 ? 'var(--brand-500)' : 'var(--warn)') ?>"></span>
                        </span>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>

        <section class="panel" style="margin-bottom:16px">
          <header class="panel__head">
            <span class="stat-tile__icon tone-blue" style="width:32px;height:32px;font-size:13px" aria-hidden="true"><i class="fa-solid fa-chart-column"></i></span>
            <h2>Compare all <?= htmlspecialchars(mb_strtolower(t('branch_plural'))) ?></h2>
            <select class="select" id="perfMetric" style="width:auto" aria-label="Metric">
              <option value="members">Members</option>
              <option value="attendance">Attendance</option>
              <?php if (mu_can('finance.reports')): ?><option value="giving">Giving</option><?php endif; ?>
              <option value="growth">Growth</option>
            </select>
          </header>
          <div class="panel__body">
            <div class="chart-wrap" style="height:360px"><canvas id="perfChart" role="img" aria-label="Comparison across all branches"></canvas></div>
          </div>
        </section>

        <div class="grid grid--2">
          <section class="panel">
            <header class="panel__head">
              <span class="stat-tile__icon tone-green" style="width:32px;height:32px;font-size:13px" aria-hidden="true"><i class="fa-solid fa-arrow-trend-up"></i></span>
              <h2>Fastest Growing</h2>
            </header>
            <div class="panel__body" style="padding:0">
              <div class="clist" style="border:0;border-radius:0">
                <?php foreach ($fastest as $b): ?>
                  <div class="crow">
                    <span class="av av--xs <?= mu_avc($b['name']) ?>" aria-hidden="true"><?= htmlspecialchars(branch_initials($b['name'])) ?></span>
                    <span class="crow__name"><?= htmlspecialchars($b['name']) ?>
                      <span style="display:block;color:var(--faint);font-size:11px;font-weight:500"><?= number_format((int) $b['members_count']) ?> members</span>
                    </span>
                    <span class="growth is-up"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i> <?= number_format($b['growth_percent'], 1) ?>%</span>
                    <?php if (mu_mod('communication')): ?>
                      <button class="chip-btn" type="button" data-open="modalMessage" data-one="<?= htmlspecialchars($b['leader_name']) ?>">Contact</button>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </section>

          <section class="panel">
            <header class="panel__head">
              <span class="stat-tile__icon tone-amber" style="width:32px;height:32px;font-size:13px" aria-hidden="true"><i class="fa-solid fa-triangle-exclamation"></i></span>
              <h2>Needs Attention</h2>
              <span class="count-chip"><?= count($attention) ?></span>
            </header>
            <div class="panel__body" style="padding:0">
              <?php if (!$attention): ?>
                <div class="empty" style="padding:34px 16px">
                  <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></span>
                  <h3>Every <?= htmlspecialchars(mb_strtolower(t('branch_singular'))) ?> is on track</h3>
                  <p>None are declining or have gone quiet.</p>
                </div>
              <?php else: ?>
                <div class="clist" style="border:0;border-radius:0">
                  <?php foreach ($attention as $b): ?>
                    <div class="crow">
                      <span class="av av--xs <?= mu_avc($b['name']) ?>" aria-hidden="true"><?= htmlspecialchars(branch_initials($b['name'])) ?></span>
                      <span class="crow__name"><?= htmlspecialchars($b['name']) ?>
                        <span style="display:block;color:var(--faint);font-size:11px;font-weight:500">
                          <?= $b['growth_percent'] < 0
                              ? 'Attendance down ' . number_format(abs($b['growth_percent']), 1) . '%'
                              : 'No activity for ' . (int) $b['last_days'] . ' days' ?>
                        </span>
                      </span>
                      <?php if (mu_mod('communication')): ?>
                        <button class="chip-btn" type="button" data-open="modalMessage" data-one="<?= htmlspecialchars($b['leader_name']) ?>">Contact</button>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </section>
        </div>
      </div>

      <!-- ─────────────── EMPTY STATE ─────────────── -->
      <div class="empty" data-empty hidden>
        <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-church"></i></span>
        <h3>No <?= htmlspecialchars(mb_strtolower(t('branch_plural'))) ?> match those filters</h3>
        <p>Try a different search term or clear the filters. If <?= htmlspecialchars($organisation['name']) ?> has not been set up yet, add the first <?= htmlspecialchars(mb_strtolower(t('branch_singular'))) ?> to get started.</p>
        <?php if ($may_add_branch): ?>
          <a class="btn btn--hero" href="<?= $base_url ?>branches/add.php">
            <i class="fa-solid fa-plus" aria-hidden="true"></i> Add your first <?= htmlspecialchars(mb_strtolower(t('branch_singular'))) ?>
          </a>
        <?php endif; ?>
        <div style="display:flex;gap:9px;flex-wrap:wrap;justify-content:center">
          <button class="btn btn--ghost" type="button" data-reset-filters><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Clear filters</button>
        </div>
      </div>
    </div>
  </section>

<?php endif; ?>
</div>

<?php if ($has_access): ?>

<!-- ═════════════════════════ BULK ACTION BAR ═════════════════════════ -->
<div class="bulkbar" id="bulkbar" role="status">
  <span class="bulkbar__count"><span data-bulk-count>0</span> selected</span>
  <span class="bulkbar__sep" aria-hidden="true"></span>
  <?php if (mu_mod('communication')): ?>
    <button class="bulkbar__btn" type="button" data-open="modalMessage">
      <i class="fa-regular fa-comment" aria-hidden="true"></i> Message <?= htmlspecialchars(t('leader_plural')) ?>
    </button>
  <?php endif; ?>
  <?php if (mu_can('members.export')): ?>
    <button class="bulkbar__btn" type="button" data-toast="Export started"><i class="fa-solid fa-file-export" aria-hidden="true"></i> Export Selected</button>
  <?php endif; ?>
  <?php if (mu_can('branches.add')): ?>
    <button class="bulkbar__btn is-danger" type="button" data-toast="<?= htmlspecialchars(t('branch_plural')) ?> deactivated"><i class="fa-solid fa-ban" aria-hidden="true"></i> Deactivate</button>
  <?php endif; ?>
  <button class="bulkbar__close" type="button" data-bulk-clear aria-label="Clear selection"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
</div>

<!-- ═════════════════════ BRANCH QUICK VIEW DRAWER ═════════════════════ -->
<div class="drawer-scrim" data-drawer-scrim hidden></div>
<aside class="drawer" id="branchDrawer" role="dialog" aria-modal="true" aria-labelledby="bdName" hidden>
  <header class="drawer__strip" data-bd-strip>
    <span class="av av--lg av-c0 bcard__av" data-bd-av aria-hidden="true">?</span>
    <span class="spill is-active" data-bd-status>Active</span>
    <button class="iconbtn drawer__strip-close" type="button" data-drawer-close aria-label="Close panel"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
  </header>

  <div class="drawer__head" style="border-bottom:0;padding-bottom:0">
    <div class="drawer__title">
      <h2 id="bdName">Branch</h2>
      <p><span data-bd-code>—</span> &middot; <span data-bd-group>—</span></p>
    </div>
  </div>

  <div class="drawer__body">
    <div class="bd-leader">
      <span class="av av--sm av-c0" data-bd-lav aria-hidden="true">?</span>
      <span style="min-width:0;flex:1">
        <span class="bcard__leadername" data-bd-leader>—</span>
        <a class="bcard__phone" data-bd-phone href="#"><i class="fa-solid fa-phone" aria-hidden="true"></i> <span>—</span></a>
      </span>
      <a class="chip-btn" data-bd-mail href="#">Email</a>
    </div>

    <div class="bd-stats">
      <span><strong data-bd-members>0</strong>Members</span>
      <span><strong data-bd-att>0</strong>Avg Att.</span>
      <?php if (mu_can('finance.reports')): ?><span><strong data-bd-giving>$0</strong>Giving</span><?php endif; ?>
      <span><strong data-bd-growth>0%</strong>Growth</span>
    </div>

    <div class="tabs" role="tablist" style="margin-top:16px">
      <button role="tab" aria-selected="true"  data-tab="overview">Overview</button>
      <button role="tab" aria-selected="false" data-tab="members">Members</button>
      <button role="tab" aria-selected="false" data-tab="attendance">Attendance</button>
      <?php if (mu_can('finance.reports')): ?><button role="tab" aria-selected="false" data-tab="giving">Giving</button><?php endif; ?>
      <button role="tab" aria-selected="false" data-tab="activity">Activity</button>
    </div>

    <div class="tabpanel" data-panel="overview">
      <dl class="deflist">
        <div><dt><?= htmlspecialchars(t('group_singular')) ?></dt><dd data-bd-group2>—</dd></div>
        <div><dt>Address</dt><dd data-bd-addr>—</dd></div>
        <div><dt>City / Province</dt><dd data-bd-city>—</dd></div>
        <div><dt>Established</dt><dd data-bd-est>—</dd></div>
        <div><dt>Attendance rate</dt><dd data-bd-rate>—</dd></div>
        <div><dt>Last activity</dt><dd data-bd-last>—</dd></div>
      </dl>
    </div>

    <div class="tabpanel" data-panel="members" hidden>
      <p style="color:var(--muted);font-size:12.5px;margin-bottom:10px"><strong data-bd-members2>0</strong> members on the roll here.</p>
      <a class="btn btn--ghost" data-bd-memberlink href="#"><i class="fa-solid fa-users" aria-hidden="true"></i> Open the member directory</a>
    </div>

    <div class="tabpanel" data-panel="attendance" hidden>
      <div class="chart-wrap" style="height:200px"><canvas id="bdAttChart" role="img" aria-label="Attendance over the last 8 weeks"></canvas></div>
    </div>

    <?php if (mu_can('finance.reports')): ?>
      <div class="tabpanel" data-panel="giving" hidden>
        <dl class="deflist">
          <div><dt>Monthly giving</dt><dd data-bd-giving2>—</dd></div>
          <div><dt>Giving per member</dt><dd data-bd-per>—</dd></div>
          <div><dt>Share of <?= htmlspecialchars(mb_strtolower(t('org_singular'))) ?> total</dt><dd data-bd-share>—</dd></div>
        </dl>
      </div>
    <?php endif; ?>

    <div class="tabpanel" data-panel="activity" hidden>
      <div class="timeline">
        <?php foreach ([
          ['fa-clipboard-check', 'Attendance recorded', 'Sunday service register submitted.'],
          ['fa-user-plus', 'New members added', 'Three new members joined this month.'],
          ['fa-hand-holding-dollar', 'Giving recorded', 'Monthly returns submitted to the office.'],
        ] as $i => [$ic, $title, $note]): ?>
          <div class="tl-item">
            <div class="tl-item__head">
              <span class="tl-item__method"><i class="fa-solid <?= $ic ?>" aria-hidden="true"></i> <?= $title ?></span>
              <span class="tl-item__date" data-bd-actdate="<?= $i ?>">—</span>
            </div>
            <p class="tl-item__notes"><?= $note ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <footer class="drawer__foot">
    <a class="btn btn--ghost" data-bd-switch href="#"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> Switch To</a>
    <?php if (mu_can('branches.add')): ?>
      <button class="btn btn--ghost" type="button" data-toast="Opening editor&hellip;"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</button>
    <?php endif; ?>
    <?php if (mu_mod('communication')): ?>
      <button class="btn" type="button" data-open="modalMessage"><i class="fa-regular fa-comment" aria-hidden="true"></i> Message</button>
    <?php endif; ?>
  </footer>
</aside>

<!-- ═══════════════════════ MESSAGE LEADERS ═══════════════════════ -->
<?php if (mu_mod('communication')): ?>
<div class="modal-scrim" id="modalMessage" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="msgTitle">
    <header class="modal__head">
      <h2 id="msgTitle">Message <?= htmlspecialchars(t('leader_plural')) ?></h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>
    <div class="modal__body">
      <div class="field" style="margin-bottom:14px">
        <label>Recipients</label>
        <div class="seg" role="group" aria-label="Recipients">
          <button type="button" data-recip="all" aria-pressed="true">All <?= htmlspecialchars(t('leader_plural')) ?> (<?= count($rows) ?>)</button>
          <button type="button" data-recip="selected" aria-pressed="false">Selected only (<span data-recip-count>0</span>)</button>
        </div>
      </div>

      <div class="field" style="margin-bottom:14px">
        <label>Channel</label>
        <div class="seg">
          <input type="checkbox" id="chEmail" checked><label for="chEmail"><i class="fa-regular fa-envelope" aria-hidden="true"></i> Email</label>
          <input type="checkbox" id="chSms" checked><label for="chSms"><i class="fa-solid fa-comment-sms" aria-hidden="true"></i> SMS</label>
        </div>
      </div>

      <div class="field" style="margin-bottom:14px">
        <label for="msgTemplate">Template</label>
        <select class="select" id="msgTemplate">
          <option>No template</option><option>Monthly returns reminder</option>
          <option><?= htmlspecialchars(t('org_singular')) ?> meeting notice</option><option>Encouragement</option>
        </select>
      </div>

      <div class="field">
        <label for="msgBody">Message</label>
        <div class="chips-row" style="margin:0 0 8px">
          <button class="fchip" type="button" data-merge="{branch_name}">{branch_name}</button>
          <button class="fchip" type="button" data-merge="{leader_name}">{leader_name}</button>
          <button class="fchip" type="button" data-merge="{org_name}">{org_name}</button>
        </div>
        <textarea class="textarea" id="msgBody" rows="5" maxlength="480" placeholder="Type your message&hellip;">Dear {leader_name}, </textarea>
        <p class="hint"><span data-char-count>0</span> / 480 characters &middot; <span data-sms-count>1</span> SMS &middot; <span data-msg-to><?= count($rows) ?></span> recipients</p>
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

<!-- ═══════════════════════════ COMPARE ═══════════════════════════ -->
<div class="modal-scrim" id="modalCompare" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="cmpTitle" style="max-width:760px">
    <header class="modal__head">
      <h2 id="cmpTitle">Compare <?= htmlspecialchars(t('branch_plural')) ?></h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>
    <div class="modal__body">
      <p class="modal__hint">Pick two to four <?= htmlspecialchars(mb_strtolower(t('branch_plural'))) ?>. The best figure in each row is highlighted.</p>

      <div class="cmp-pick" id="cmpPick">
        <?php foreach ($rows as $b): ?>
          <label class="cmp-chip">
            <input type="checkbox" data-cmp value="<?= (int) $b['id'] ?>">
            <span><?= htmlspecialchars($b['name']) ?></span>
          </label>
        <?php endforeach; ?>
      </div>

      <p class="hint" style="margin-top:10px"><span data-cmp-count>0</span> of 4 chosen</p>

      <div id="cmpOut" style="margin-top:16px"></div>
    </div>
    <footer class="modal__foot">
      <a href="#" data-cmp-reset>Clear selection</a>
      <span class="modal__spacer"></span>
      <button class="btn btn--ghost" type="button" data-close>Close</button>
    </footer>
  </div>
</div>

<!-- ═══════════════════════════ DELETE ═══════════════════════════ -->
<?php if (mu_can('members.delete')): ?>
<div class="modal-scrim" id="modalDelete" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="delTitle">
    <header class="modal__head">
      <h2 id="delTitle">Delete <?= htmlspecialchars(mb_strtolower(t('branch_singular'))) ?></h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>
    <div class="modal__body">
      <div class="err-summary is-on" style="align-items:flex-start">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        <span>
          <strong data-del-name>This <?= htmlspecialchars(mb_strtolower(t('branch_singular'))) ?></strong> and everything recorded under it —
          every member, attendance register, contribution and household — is affected. This cannot be undone.
        </span>
      </div>
      <div class="field">
        <label for="delConfirm">Type the code <strong data-del-code>CODE</strong> to confirm</label>
        <input class="input" type="text" id="delConfirm" placeholder="e.g. DOH-PR-07" autocomplete="off">
      </div>
    </div>
    <footer class="modal__foot">
      <span class="modal__spacer"></span>
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <button class="btn" type="button" id="delGo" disabled style="background:linear-gradient(120deg,#8f1d33,var(--danger))">Delete permanently</button>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function () {
  'use strict';
  var still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var panel = document.getElementById('listPanel');

  /* ── toasts ── */
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

  if (!panel) { return; }                      /* no access: nothing to wire */

  var BRANCHES = <?= json_encode(array_column($rows, null, 'id'), JSON_UNESCAPED_UNICODE) ?>;
  var CAN_FIN  = <?= mu_can('finance.reports') ? 'true' : 'false' ?>;
  var TOTAL_GIVING = <?= (float) $total_giving ?>;
  var BASE = <?= json_encode($base_url) ?>;
  var LEADER_TITLE = <?= json_encode(t('leader_title')) ?>;

  setTimeout(function () { panel.classList.add('is-loaded'); }, still ? 0 : 640);

  /* ── counters ── */
  [].forEach.call(document.querySelectorAll('[data-count]'), function (el) {
    var target = parseFloat(el.getAttribute('data-count')) || 0;
    var money = el.hasAttribute('data-money');
    var fmt = function (v) { return money ? '$' + Math.round(v).toLocaleString() : Math.round(v).toLocaleString(); };
    if (still) { el.textContent = fmt(target); return; }
    var start = performance.now();
    (function step(now) {
      var p = Math.min(1, (now - start) / 900), eased = 1 - Math.pow(1 - p, 3);
      el.textContent = fmt(target * eased);
      if (p < 1) { requestAnimationFrame(step); }
    })(start);
  });

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

  /* ── view switcher ── */
  var VIEW_KEY = 'mutendi-branches-view';
  function setView(v) {
    [].forEach.call(document.querySelectorAll('[data-view]'), function (b) {
      b.setAttribute('aria-pressed', String(b.getAttribute('data-view') === v));
    });
    [].forEach.call(document.querySelectorAll('[data-view-panel]'), function (p) {
      p.hidden = p.getAttribute('data-view-panel') !== v;
    });
    try { sessionStorage.setItem(VIEW_KEY, v); } catch (e) {}
    if (v === 'perf') { drawPerf(); }
  }
  [].forEach.call(document.querySelectorAll('[data-view]'), function (b) {
    b.addEventListener('click', function () { setView(b.getAttribute('data-view')); });
  });

  /* ── filtering + sorting ── */
  var search = document.querySelector('[data-search]'),
      clearBtn = document.querySelector('[data-search-clear]'),
      resultCount = document.querySelector('[data-result-count]'),
      emptyState = document.querySelector('[data-empty]'),
      chipsRow = document.querySelector('[data-filter-chips]'),
      activeBadge = document.querySelector('[data-active-filters]'),
      statTile = 'all';

  function sizeBand(n) { return n < 100 ? 'Under 100' : (n <= 300 ? '100-300' : 'Over 300'); }

  function apply() {
    var f = {};
    ['fGroup','fStatus','fProvince','fSize'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el && el.value !== 'All') { f[id] = el.value; }
    });
    var q = search && search.value.trim() ? search.value.trim().toLowerCase() : '';
    if (q) { f.q = search.value.trim(); }

    var shown = 0;
    [].forEach.call(document.querySelectorAll('[data-row], [data-card], [data-mappin], [data-map-row]'), function (el) {
      var ok = true;
      if (q && (el.getAttribute('data-name') || '').indexOf(q) === -1 && el.hasAttribute('data-name')) { ok = false; }
      if (ok && f.fGroup && el.getAttribute('data-group') !== f.fGroup) { ok = false; }
      if (ok && f.fStatus && el.hasAttribute('data-status') && el.getAttribute('data-status') !== f.fStatus) { ok = false; }
      if (ok && f.fProvince && el.hasAttribute('data-province') && el.getAttribute('data-province') !== f.fProvince) { ok = false; }
      if (ok && f.fSize && el.hasAttribute('data-members') && sizeBand(parseInt(el.getAttribute('data-members'), 10)) !== f.fSize) { ok = false; }
      if (ok && statTile === 'att' && el.hasAttribute('data-stale') && el.getAttribute('data-stale') === '1') { ok = false; }
      el.hidden = !ok;
      if (ok && el.hasAttribute('data-row')) { shown++; }
    });

    resultCount.textContent = shown;
    emptyState.hidden = shown !== 0;

    var keys = Object.keys(f);
    chipsRow.innerHTML = '';
    keys.forEach(function (k) {
      var chip = document.createElement('span');
      chip.className = 'fchip';
      chip.innerHTML = '<span></span><button type="button" aria-label="Remove filter"><i class="fa-solid fa-xmark"></i></button>';
      chip.querySelector('span').textContent = k === 'q' ? 'Search: ' + f[k] : f[k];
      chip.querySelector('button').addEventListener('click', function () {
        if (k === 'q') { search.value = ''; } else { document.getElementById(k).value = 'All'; }
        apply();
      });
      chipsRow.appendChild(chip);
    });
    chipsRow.hidden = keys.length === 0;
    activeBadge.textContent = keys.length;
    activeBadge.hidden = keys.length === 0;
    if (clearBtn) { clearBtn.hidden = !(search && search.value); }
  }

  function sortBy(mode) {
    var cmp = {
      'Name':            function (a, b) { return (a.getAttribute('data-name') || '').localeCompare(b.getAttribute('data-name') || ''); },
      'Largest':         function (a, b) { return (+b.getAttribute('data-members')) - (+a.getAttribute('data-members')); },
      'Fastest Growing': function (a, b) { return (+b.getAttribute('data-growth')) - (+a.getAttribute('data-growth')); },
      'Needs Attention': function (a, b) { return (+a.getAttribute('data-score')) - (+b.getAttribute('data-score')); }
    }[mode];
    if (!cmp) { return; }

    var grid = document.getElementById('branchCards');
    if (grid) { [].slice.call(grid.children).sort(cmp).forEach(function (n) { grid.appendChild(n); }); }
    var tbody = document.querySelector('[data-view-panel="table"] tbody');
    if (tbody) { [].slice.call(tbody.children).sort(cmp).forEach(function (n) { tbody.appendChild(n); }); }
    toast('Sorted by ' + mode.toLowerCase(), 'info');
  }
  document.getElementById('fSort').addEventListener('change', function () { sortBy(this.value); });

  if (search) { search.addEventListener('input', apply); }
  if (clearBtn) { clearBtn.addEventListener('click', function () { search.value = ''; apply(); search.focus(); }); }
  [].forEach.call(document.querySelectorAll('[data-filter]'), function (el) { el.addEventListener('change', apply); });
  [].forEach.call(document.querySelectorAll('[data-reset-filters]'), function (b) {
    b.addEventListener('click', function () {
      if (search) { search.value = ''; }
      [].forEach.call(document.querySelectorAll('[data-filter]'), function (el) { el.value = 'All'; });
      statTile = 'all';
      [].forEach.call(document.querySelectorAll('[data-stat-filter]'), function (t) {
        var on = t.getAttribute('data-stat-filter') === 'all';
        t.classList.toggle('is-on', on); t.setAttribute('aria-pressed', String(on));
      });
      apply(); toast('Filters cleared', 'info');
    });
  });
  [].forEach.call(document.querySelectorAll('[data-stat-filter]'), function (t) {
    t.addEventListener('click', function () {
      statTile = t.getAttribute('data-stat-filter');
      [].forEach.call(document.querySelectorAll('[data-stat-filter]'), function (o) {
        var on = o === t;
        o.classList.toggle('is-on', on); o.setAttribute('aria-pressed', String(on));
      });
      if (statTile === 'members') { document.getElementById('fSort').value = 'Largest'; sortBy('Largest'); }
      apply();
    });
  });
  [].forEach.call(document.querySelectorAll('[data-group-filter]'), function (b) {
    b.addEventListener('click', function () {
      document.getElementById('fGroup').value = b.getAttribute('data-group-filter');
      apply();
      toast('Showing ' + b.getAttribute('data-group-filter'), 'info');
    });
  });

  var fToggle = document.getElementById('filtersToggle');
  if (fToggle) {
    fToggle.addEventListener('click', function () {
      var box = document.getElementById('filters');
      var on = !box.classList.contains('is-open');
      box.classList.toggle('is-open', on);
      fToggle.setAttribute('aria-expanded', String(on));
    });
  }

  [].forEach.call(document.querySelectorAll('[data-card-toggle]'), function (b) {
    b.addEventListener('click', function () { b.closest('.pcard').classList.toggle('is-open'); });
  });

  /* ── selection + bulk bar ── */
  var bulk = document.getElementById('bulkbar');
  function selectedIds() {
    return [].slice.call(document.querySelectorAll('[data-row-check]:checked'))
             .map(function (cb) { return cb.closest('tr').getAttribute('data-id'); });
  }
  function syncBulk() {
    var n = selectedIds().length;
    document.querySelector('[data-bulk-count]').textContent = n;
    bulk.classList.toggle('is-on', n > 0);
    var rc = document.querySelector('[data-recip-count]');
    if (rc) { rc.textContent = n; }
  }
  [].forEach.call(document.querySelectorAll('[data-row-check]'), function (cb) {
    cb.addEventListener('change', function () {
      cb.closest('tr').classList.toggle('is-picked', cb.checked);
      syncBulk();
    });
  });
  var all = document.querySelector('[data-check-all]');
  if (all) {
    all.addEventListener('change', function () {
      [].forEach.call(document.querySelectorAll('[data-row-check]'), function (cb) {
        if (cb.closest('tr').hidden) { return; }
        cb.checked = all.checked;
        cb.closest('tr').classList.toggle('is-picked', all.checked);
      });
      syncBulk();
    });
  }
  var bc = document.querySelector('[data-bulk-clear]');
  if (bc) {
    bc.addEventListener('click', function () {
      [].forEach.call(document.querySelectorAll('[data-row-check]'), function (cb) {
        cb.checked = false; cb.closest('tr').classList.remove('is-picked');
      });
      if (all) { all.checked = false; }
      syncBulk();
    });
  }

  /* ── drawer ── */
  var drawer = document.getElementById('branchDrawer'),
      dScrim = document.querySelector('[data-drawer-scrim]'),
      bdChart = null;

  function money(n) { return '$' + Number(n).toLocaleString(undefined, { maximumFractionDigits: 0 }); }

  function openDrawer(id) {
    var b = BRANCHES[id];
    if (!b) { return; }

    drawer.querySelector('#bdName').textContent = b.name;
    drawer.querySelector('[data-bd-code]').textContent = b.code;
    drawer.querySelector('[data-bd-group]').textContent = b.group_name;
    drawer.querySelector('[data-bd-group2]').textContent = b.group_name;

    var st = drawer.querySelector('[data-bd-status]');
    st.textContent = b.status.charAt(0).toUpperCase() + b.status.slice(1);
    st.className = 'spill is-' + b.status;

    var av = drawer.querySelector('[data-bd-av]');
    av.textContent = initials(b.name);
    av.className = 'av av--lg bcard__av av-c' + (crc32(b.name) % 10);

    var lav = drawer.querySelector('[data-bd-lav]');
    lav.textContent = initials(b.leader_name);
    lav.className = 'av av--sm av-c' + (crc32(b.leader_name) % 10);

    /* Same rule as branch_leader_label() in PHP. */
    var titled = /^(Rev\.?|Bishop|Pastor|Minister|Fr\.?|Archdeacon|Canon|Dr\.?)\s/i.test(b.leader_name);
    drawer.querySelector('[data-bd-leader]').textContent = titled ? b.leader_name : (LEADER_TITLE + ' ' + b.leader_name);
    var ph = drawer.querySelector('[data-bd-phone]');
    ph.href = 'tel:' + b.leader_phone.replace(/\s/g, '');
    ph.querySelector('span').textContent = b.leader_phone;
    drawer.querySelector('[data-bd-mail]').href = 'mailto:' + b.leader_email;

    drawer.querySelector('[data-bd-members]').textContent = Number(b.members_count).toLocaleString();
    drawer.querySelector('[data-bd-members2]').textContent = Number(b.members_count).toLocaleString();
    drawer.querySelector('[data-bd-att]').textContent = Number(b.avg_attendance).toLocaleString();
    drawer.querySelector('[data-bd-growth]').textContent = (b.growth_percent >= 0 ? '+' : '') + b.growth_percent + '%';
    if (CAN_FIN) {
      drawer.querySelector('[data-bd-giving]').textContent = money(b.monthly_giving);
      drawer.querySelector('[data-bd-giving2]').textContent = money(b.monthly_giving);
      drawer.querySelector('[data-bd-per]').textContent = '$' + (b.members_count ? (b.monthly_giving / b.members_count).toFixed(2) : '0.00');
      drawer.querySelector('[data-bd-share]').textContent = TOTAL_GIVING ? Math.round(b.monthly_giving / TOTAL_GIVING * 100) + '%' : '—';
    }

    drawer.querySelector('[data-bd-addr]').textContent = b.address + ', ' + b.suburb;
    drawer.querySelector('[data-bd-city]').textContent = b.city + ', ' + b.province;
    drawer.querySelector('[data-bd-est]').textContent = b.established_date;
    drawer.querySelector('[data-bd-rate]').textContent = b.attendance_rate + '%';
    drawer.querySelector('[data-bd-last]').textContent = b.last_activity;
    [].forEach.call(drawer.querySelectorAll('[data-bd-actdate]'), function (el, i) {
      el.textContent = [b.last_activity, b.last_activity, b.last_activity][i] || b.last_activity;
    });

    drawer.querySelector('[data-bd-switch]').href = BASE + 'index.php?branch=' + b.id;
    drawer.querySelector('[data-bd-memberlink]').href = BASE + 'members/all.php?branch=' + b.id;
    drawer.querySelector('[data-bd-strip]').style.background = groupTone(b.group_name);

    dScrim.hidden = false; drawer.hidden = false;
    document.body.style.overflow = 'hidden';
    drawer.querySelector('[data-drawer-close]').focus();

    var cv = document.getElementById('bdAttChart');
    if (cv && window.Chart) {
      if (bdChart) { bdChart.destroy(); }
      bdChart = new Chart(cv, {
        type: 'line',
        data: { labels: b.spark.map(function (_, i) { return 'W' + (i + 1); }),
                datasets: [{ label: 'Attendance', data: b.spark, borderColor: '#662F97',
                  backgroundColor: 'rgba(102,47,151,.1)', fill: true, tension: .35,
                  pointRadius: 3, borderWidth: 2 }] },
        options: { responsive: true, maintainAspectRatio: false,
          animation: still ? false : { duration: 500 },
          plugins: { legend: { display: false } },
          scales: { x: { grid: { display: false }, border: { display: false } },
                    y: { grid: { color: '#ECE7F3' }, border: { display: false }, beginAtZero: true } } }
      });
    }
  }
  function closeDrawer() { drawer.hidden = true; dScrim.hidden = true; document.body.style.overflow = ''; }

  var TONES = <?= json_encode($group_tones) ?>;
  function groupTone(g) { return TONES[g] || 'var(--brand-500)'; }

  var CRC_TABLE = (function () {
    var t = [], c, n, k;
    for (n = 0; n < 256; n++) { c = n; for (k = 0; k < 8; k++) { c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1); } t[n] = c >>> 0; }
    return t;
  })();
  function crc32(str) {
    var bytes = new TextEncoder().encode(str), crc = -1, i;
    for (i = 0; i < bytes.length; i++) { crc = (crc >>> 8) ^ CRC_TABLE[(crc ^ bytes[i]) & 0xFF]; }
    return (crc ^ -1) >>> 0;
  }
  function initials(name) {
    var clean = name.replace(/^(St\.?|Saint|Holy|All)\s+/i, '');
    var p = clean.trim().split(/\s+/);
    return ((p[0] || '')[0] + (p.length > 1 ? p[p.length - 1][0] : '')).toUpperCase();
  }

  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-open-branch]');
    if (t) { e.preventDefault(); openDrawer(parseInt(t.getAttribute('data-open-branch'), 10)); }
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

  /* ── modals ── */
  document.addEventListener('click', function (e) {
    var open = e.target.closest('[data-open]');
    if (open) {
      e.preventDefault();
      var m = document.getElementById(open.getAttribute('data-open'));
      if (!m) { return; }
      if (open.getAttribute('data-open') === 'modalDelete') {
        var code = open.getAttribute('data-code') || '';
        m.querySelector('[data-del-code]').textContent = code;
        m.querySelector('[data-del-name]').textContent = open.getAttribute('data-bname') || 'This branch';
        var inp = m.querySelector('#delConfirm'), go = m.querySelector('#delGo');
        inp.value = ''; go.disabled = true;
        inp.oninput = function () { go.disabled = inp.value.trim() !== code; };
      }
      m.hidden = false; document.body.style.overflow = 'hidden';
      return;
    }
    var close = e.target.closest('[data-close]');
    if (close) { e.preventDefault(); close.closest('.modal-scrim').hidden = true; document.body.style.overflow = ''; return; }
    if (e.target.classList.contains('modal-scrim')) { e.target.hidden = true; document.body.style.overflow = ''; }
  });
  var delGo = document.getElementById('delGo');
  if (delGo) {
    delGo.addEventListener('click', function () {
      delGo.closest('.modal-scrim').hidden = true; document.body.style.overflow = '';
      toast('Deleted', 'error');
    });
  }

  /* message modal */
  var body = document.getElementById('msgBody');
  if (body) {
    var cc = document.querySelector('[data-char-count]'), sc = document.querySelector('[data-sms-count]');
    var sync = function () {
      cc.textContent = body.value.length;
      sc.textContent = Math.max(1, Math.ceil(body.value.length / 160));
    };
    body.addEventListener('input', sync); sync();
    [].forEach.call(document.querySelectorAll('[data-merge]'), function (b) {
      b.addEventListener('click', function () { body.value += b.getAttribute('data-merge'); sync(); body.focus(); });
    });
    [].forEach.call(document.querySelectorAll('[data-recip]'), function (b) {
      b.addEventListener('click', function () {
        [].forEach.call(document.querySelectorAll('[data-recip]'), function (o) { o.setAttribute('aria-pressed', String(o === b)); });
        var n = b.getAttribute('data-recip') === 'all' ? Object.keys(BRANCHES).length : selectedIds().length;
        document.querySelector('[data-msg-to]').textContent = n;
      });
    });
  }

  /* ── compare ── */
  var cmpOut = document.getElementById('cmpOut');
  function renderCompare() {
    var picked = [].slice.call(document.querySelectorAll('[data-cmp]:checked')).map(function (c) { return BRANCHES[c.value]; });
    document.querySelector('[data-cmp-count]').textContent = picked.length;

    /* Four is the most that stays readable side by side. */
    [].forEach.call(document.querySelectorAll('[data-cmp]'), function (c) {
      c.disabled = !c.checked && picked.length >= 4;
      c.closest('.cmp-chip').classList.toggle('is-on', c.checked);
    });

    if (picked.length < 2) {
      cmpOut.innerHTML = '<p class="hint" style="text-align:center;padding:18px 0">Pick at least two to compare.</p>';
      return;
    }

    var metrics = [
      ['Members',        function (b) { return b.members_count; },  function (v) { return Number(v).toLocaleString(); }, 'high'],
      ['Avg attendance', function (b) { return b.avg_attendance; }, function (v) { return Number(v).toLocaleString(); }, 'high'],
      ['Attendance rate',function (b) { return b.attendance_rate; },function (v) { return v + '%'; }, 'high'],
      ['Growth',         function (b) { return b.growth_percent; }, function (v) { return (v >= 0 ? '+' : '') + v + '%'; }, 'high'],
      ['Score',          function (b) { return b.score; },          function (v) { return v; }, 'high']
    ];
    if (CAN_FIN) {
      metrics.splice(4, 0, ['Monthly giving', function (b) { return b.monthly_giving; }, money, 'high']);
      metrics.splice(5, 0, ['Giving / member', function (b) { return b.members_count ? b.monthly_giving / b.members_count : 0; },
                            function (v) { return '$' + Number(v).toFixed(2); }, 'high']);
    }

    var html = '<div class="dt-wrap"><table class="dt cmp-table"><thead><tr><th>Metric</th>';
    picked.forEach(function (b) { html += '<th>' + b.name + '</th>'; });
    html += '</tr></thead><tbody>';

    metrics.forEach(function (m) {
      var vals = picked.map(m[1]);
      var best = Math.max.apply(null, vals);
      html += '<tr><td class="strong">' + m[0] + '</td>';
      vals.forEach(function (v) {
        html += '<td class="' + (v === best ? 'is-best' : '') + '">' + m[2](v) + '</td>';
      });
      html += '</tr>';
    });
    html += '</tbody></table></div>';
    cmpOut.innerHTML = html;
  }
  [].forEach.call(document.querySelectorAll('[data-cmp]'), function (c) { c.addEventListener('change', renderCompare); });
  var cmpReset = document.querySelector('[data-cmp-reset]');
  if (cmpReset) {
    cmpReset.addEventListener('click', function (e) {
      e.preventDefault();
      [].forEach.call(document.querySelectorAll('[data-cmp]'), function (c) { c.checked = false; });
      renderCompare();
    });
  }
  renderCompare();

  /* ── performance chart ── */
  var perfChart = null;
  function drawPerf() {
    var cv = document.getElementById('perfChart');
    if (!cv || !window.Chart) { return; }
    var metric = document.getElementById('perfMetric').value;
    var list = Object.keys(BRANCHES).map(function (k) { return BRANCHES[k]; });

    var pick = {
      members:    [function (b) { return b.members_count; },  'Members'],
      attendance: [function (b) { return b.avg_attendance; }, 'Average attendance'],
      giving:     [function (b) { return b.monthly_giving; }, 'Monthly giving ($)'],
      growth:     [function (b) { return b.growth_percent; }, 'Growth (%)']
    }[metric];

    Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.color = '#6E6880';

    if (perfChart) { perfChart.destroy(); }
    perfChart = new Chart(cv, {
      type: 'bar',
      data: {
        labels: list.map(function (b) { return b.name; }),
        datasets: [{ label: pick[1], data: list.map(pick[0]),
          backgroundColor: list.map(function (b) { return b.growth_percent < 0 ? '#B4243F' : '#662F97'; }),
          borderRadius: 6, maxBarThickness: 20 }]
      },
      options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false,
        animation: still ? false : { duration: 500 },
        plugins: { legend: { display: false } },
        scales: { x: { grid: { color: '#ECE7F3' }, border: { display: false }, beginAtZero: true },
                  y: { grid: { display: false }, border: { display: false } } } }
    });
  }
  document.getElementById('perfMetric').addEventListener('change', drawPerf);

  /* ── row menus escape the table's scroll box ── */
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

  try { var sv = sessionStorage.getItem(VIEW_KEY); if (sv) { setView(sv); } } catch (e) {}
  apply();
})();
</script>

<?php require __DIR__ . '/../components/footer.php'; ?>

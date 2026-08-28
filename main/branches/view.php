<?php
/**
 * Mutendi CMS — single {branch_singular} profile.
 *
 * Organisation-scope users may open any branch; a branch-scope user may open
 * only their own and gets a clean no-access state for anything else.
 * UI only — every figure below is derived demo data.
 */

require __DIR__ . '/../includes/config.php';

/* ══════════════ DEMO ONLY — REMOVE BEFORE PRODUCTION ══════════════ */
$demo_role       = isset($_GET['role'], $demo_roles[$_GET['role']]) ? $_GET['role'] : 'church_admin';
/* array_merge, not assignment: the scope keys from config must survive. */
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
if (!function_exists('branch_initials')) { require_once __DIR__ . '/../components/branch-switcher.php'; }

if (!function_exists('branch_leader_label')) {
    /** Skip the title when the stored name already carries one. */
    function branch_leader_label(array $b): string {
        if (preg_match('/^(Rev\.?|Bishop|Pastor|Minister|Fr\.?|Archdeacon|Canon|Dr\.?)\s/i', $b['leader_name'])) {
            return $b['leader_name'];
        }
        $title = ($b['type'] ?? '') === 'head_office' ? t('org_leader_title') : t('leader_title');
        return $title . ' ' . $b['leader_name'];
    }
}

/* ------------------------------------------------------------- which one -- */

$want = isset($_GET['id']) ? (int) $_GET['id'] : null;
if ($want === null) {
    /* No id: a branch user lands on their own, everyone else on head office. */
    $want = ($user['scope'] ?? 'organisation') === 'branch'
        ? (int) ($user['branch_id'] ?? 0)
        : (int) ($branches[0]['id'] ?? 0);
}

$branch     = get_branch($want);
$has_access = $branch !== null && can_see_branch($want);

/* ------------------------------------------------------- derived figures -- */
/* Everything below stands in for a query scoped to this branch. Each series
   is seeded from the branch's own code, so a branch always shows the same
   numbers between page loads. */

if ($has_access) {
    $seed = crc32($branch['code']);
    $rand = function (int $i, int $spread) use ($seed) { return (($seed >> ($i * 3)) % (2 * $spread + 1)) - $spread; };

    /* LATER: SELECT week, SUM(present) FROM attendance
              WHERE branch_id = :id GROUP BY week ORDER BY week DESC LIMIT 12; */
    $att_weeks = [];
    for ($i = 0; $i < 12; $i++) {
        $drift = ($branch['growth_percent'] / 100) * $branch['avg_attendance'] * ($i / 12);
        $att_weeks[] = max(5, (int) round($branch['avg_attendance'] * (1 + $rand($i, 18) / 100) + $drift));
    }

    /* LATER: SELECT month, SUM(amount) FROM contributions
              WHERE branch_id = :id GROUP BY month ORDER BY month DESC LIMIT 6; */
    $give_months = [];
    for ($i = 0; $i < 6; $i++) {
        $give_months[] = max(50, (int) round($branch['monthly_giving'] * (1 + $rand($i + 3, 22) / 100)));
    }

    /* LATER: SELECT type, SUM(amount) FROM contributions WHERE branch_id = :id
              AND month = :this_month GROUP BY type; */
    $give_types = [];
    $split = [46, 20, 15, 11, 8];
    foreach (['Tithe', 'Offering', 'Building Fund', 'Missions', 'Pledges'] as $k => $label) {
        $give_types[$label] = (int) round($branch['monthly_giving'] * $split[$k] / 100);
    }

    /* Service-by-service history. LATER: one row per recorded service. */
    $services_hist = [];
    for ($i = 0; $i < 8; $i++) {
        $d = (new DateTimeImmutable($branch['last_activity']))->modify('-' . (7 * $i) . ' days');
        $services_hist[] = [
            'date'     => $d->format('Y-m-d'),
            'service'  => $i % 2 === 0 ? 'Sunday 9:00 AM' : 'Sunday 11:00 AM',
            'present'  => $att_weeks[$i] ?? $branch['avg_attendance'],
            'visitors' => max(0, 3 + $rand($i, 4)),
            'offering' => (int) round($branch['monthly_giving'] / 4 * (1 + $rand($i, 15) / 100)),
        ];
    }

    /* Service times. LATER: SELECT * FROM branch_services WHERE branch_id = :id; */
    $service_times = [
        ['Sunday',    '09:00', '11:00', 'First Service',        (int) round($branch['avg_attendance'] * 0.55)],
        ['Sunday',    '11:15', '13:00', 'Second Service',       (int) round($branch['avg_attendance'] * 0.45)],
        ['Wednesday', '17:30', '19:00', 'Bible Study',          (int) round($branch['avg_attendance'] * 0.22)],
        ['Friday',    '17:00', '19:00', 'Prayer Meeting',       (int) round($branch['avg_attendance'] * 0.18)],
    ];

    /* People scoped to this branch — a deterministic slice of the demo roll. */
    $offset = abs($seed) % max(1, count($members_demo) - 8);
    $branch_members = array_slice($members_demo, $offset, 8);
    $branch_depts   = array_slice($departments_demo, abs($seed >> 4) % 6, 6);
    $branch_cells   = array_slice($cells_demo, abs($seed >> 6) % 5, 5);

    $leadership = [
        [t('leader_title'), $branch['leader_name'], $branch['leader_phone'], $branch['leader_email']],
        ['Assistant ' . t('leader_title'), $members_demo[($offset + 1) % count($members_demo)]['name'],
         $members_demo[($offset + 1) % count($members_demo)]['phone'], $members_demo[($offset + 1) % count($members_demo)]['email']],
        ['Secretary', $members_demo[($offset + 2) % count($members_demo)]['name'],
         $members_demo[($offset + 2) % count($members_demo)]['phone'], $members_demo[($offset + 2) % count($members_demo)]['email']],
        ['Treasurer', $members_demo[($offset + 3) % count($members_demo)]['name'],
         $members_demo[($offset + 3) % count($members_demo)]['phone'], $members_demo[($offset + 3) % count($members_demo)]['email']],
    ];

    $last_days = (int) (new DateTimeImmutable(date('Y-m-d')))->diff(new DateTimeImmutable($branch['last_activity']))->days;

    /* Which tabs this user actually gets. Anything they lack is not rendered. */
    $tabs = [['overview', 'Overview', 'fa-house']];
    $tabs[] = ['members', 'Members', 'fa-users'];
    $tabs[] = ['leadership', 'Leadership', 'fa-user-tie'];
    if (mu_mod('departments'))     { $tabs[] = ['departments', 'Departments', 'fa-sitemap']; }
    if (mu_mod('cell_groups'))     { $tabs[] = ['cells', 'Cell Groups', 'fa-people-group']; }
    if (mu_mod('attendance'))      { $tabs[] = ['attendance', 'Attendance', 'fa-clipboard-check']; }
    if (mu_can('finance.reports')) { $tabs[] = ['giving', 'Giving', 'fa-hand-holding-dollar']; }
    if (mu_can('branches.edit'))   { $tabs[] = ['settings', 'Settings', 'fa-gear']; }
}

$page_title = $has_access ? $branch['name'] : t('branch_singular');
require __DIR__ . '/../components/header.php';
?>

<div class="page">

<?php if (!$has_access): ?>

  <section class="panel" style="margin-top:8px">
    <div class="empty">
      <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-lock"></i></span>
      <h3>You don't have access to this <?= htmlspecialchars(mb_strtolower(t('branch_singular'))) ?></h3>
      <p>
        <?php if ($branch === null): ?>
          That <?= htmlspecialchars(mb_strtolower(t('branch_singular'))) ?> does not exist, or it has been removed from
          <?= htmlspecialchars($organisation['name']) ?>.
        <?php else: ?>
          Your account is scoped to <strong><?= htmlspecialchars($user['branch_name'] ?? t('branch_singular')) ?></strong>,
          so you can only open your own <?= htmlspecialchars(mb_strtolower(t('branch_singular'))) ?>.
        <?php endif; ?>
      </p>
      <div style="display:flex;gap:9px;flex-wrap:wrap;justify-content:center">
        <a class="btn" href="<?= $base_url ?>index.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to my dashboard</a>
        <?php if (($user['scope'] ?? 'organisation') === 'branch' && !empty($user['branch_id'])): ?>
          <a class="btn btn--ghost" href="?id=<?= (int) $user['branch_id'] ?>">
            <i class="fa-solid fa-church" aria-hidden="true"></i> Open my <?= htmlspecialchars(mb_strtolower(t('branch_singular'))) ?>
          </a>
        <?php endif; ?>
      </div>
    </div>
  </section>

<?php else: ?>

  <nav class="crumbs" aria-label="Breadcrumb" style="margin-bottom:14px">
    <a href="<?= $base_url ?>index.php">Dashboard</a>
    <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
    <span><?= htmlspecialchars(t('org_singular')) ?></span>
    <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
    <a href="<?= $base_url ?>branches/index.php">All <?= htmlspecialchars(t('branch_plural')) ?></a>
    <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
    <span aria-current="page"><?= htmlspecialchars($branch['name']) ?></span>
  </nav>

  <!-- ═══════════════════════ HEADER BANNER ═══════════════════════ -->
  <section class="bhero">
    <div class="bhero__strip" aria-hidden="true"></div>
    <div class="bhero__body">
      <span class="av av--xl <?= mu_avc($branch['name']) ?> bhero__av" aria-hidden="true"><?= htmlspecialchars(branch_initials($branch['name'])) ?></span>

      <div class="bhero__text">
        <div class="title-row">
          <h1 class="page__title"><?= htmlspecialchars($branch['name']) ?></h1>
          <span class="spill is-<?= htmlspecialchars($branch['status']) ?>"><?= htmlspecialchars(ucfirst($branch['status'])) ?></span>
          <?php if ($branch['type'] === 'head_office'): ?>
            <span class="bswitch__ho"><?= htmlspecialchars(t('org_singular')) ?></span>
          <?php endif; ?>
        </div>
        <p class="bhero__meta">
          <span><i class="fa-solid fa-hashtag" aria-hidden="true"></i> <?= htmlspecialchars($branch['code']) ?></span>
          <span class="pill is-brand"><?= htmlspecialchars($branch['group_name']) ?></span>
          <span><i class="fa-regular fa-calendar" aria-hidden="true"></i> Established <?= mu_date($branch['established_date'], 'M Y') ?></span>
        </p>
      </div>

      <div class="bhero__actions">
        <a class="btn" href="<?= $base_url ?>index.php?branch=<?= (int) $branch['id'] ?>">
          <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
          Switch to this <?= htmlspecialchars(t('branch_singular')) ?>
        </a>
        <?php if (mu_can('branches.edit')): ?>
          <button class="btn btn--ghost" type="button" data-tab-jump="settings"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</button>
        <?php endif; ?>
        <?php if (mu_mod('communication')): ?>
          <button class="btn btn--ghost" type="button" data-open="modalMessage"><i class="fa-regular fa-comment" aria-hidden="true"></i> Message</button>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- ═══════════════════════ STAT STRIP ═══════════════════════ -->
  <div class="stat-strip stat-strip--auto">
    <?php
      $tiles = [
        ['Members', $branch['members_count'], 'fa-users', 'blue', ''],
        ['Average Attendance', $branch['avg_attendance'], 'fa-clipboard-check', 'purple', ''],
      ];
      if (mu_can('finance.reports')) { $tiles[] = ['Monthly Giving', $branch['monthly_giving'], 'fa-hand-holding-dollar', 'green', 'money']; }
      if (mu_mod('departments'))     { $tiles[] = ['Departments', count($branch_depts), 'fa-sitemap', 'amber', '']; }
      if (mu_mod('cell_groups'))     { $tiles[] = ['Cell Groups', count($branch_cells), 'fa-people-group', 'teal', '']; }
      $tiles[] = ['Growth This Year', $branch['growth_percent'], 'fa-arrow-trend-up', 'pink', 'pct'];
      foreach ($tiles as [$label, $value, $icon, $tone, $fmt]):
    ?>
      <div class="stat-tile" style="cursor:default">
        <span class="stat-tile__icon tone-<?= $tone ?>" aria-hidden="true"><i class="fa-solid <?= $icon ?>"></i></span>
        <span class="stat-tile__body">
          <span class="stat-tile__value" data-count="<?= $value ?>"<?= $fmt ? ' data-fmt="' . $fmt . '"' : '' ?>>0</span>
          <span class="stat-tile__label"><?= htmlspecialchars($label) ?></span>
        </span>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- ═══════════════════════════ TABS ═══════════════════════════ -->
  <div class="tabs tabs--page" role="tablist">
    <?php foreach ($tabs as $i => [$key, $label, $icon]): ?>
      <button role="tab" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" data-tab="<?= $key ?>">
        <i class="fa-solid <?= $icon ?>" aria-hidden="true"></i> <?= htmlspecialchars($label) ?>
      </button>
    <?php endforeach; ?>
  </div>

  <!-- ─────────────── OVERVIEW ─────────────── -->
  <div class="tabpanel" data-panel="overview">
    <div class="grid grid--2" style="align-items:start">

      <section class="panel">
        <header class="panel__head">
          <span class="stat-tile__icon tone-brand" style="width:32px;height:32px;font-size:13px" aria-hidden="true"><i class="fa-solid fa-user-tie"></i></span>
          <h2><?= htmlspecialchars(t('leader_title')) ?></h2>
        </header>
        <div class="panel__body">
          <div class="bd-leader" style="margin-top:0">
            <?= mu_av($branch['leader_name'], 'lg') ?>
            <span style="min-width:0;flex:1">
              <span class="bcard__leadername" style="font-size:14px"><?= htmlspecialchars(branch_leader_label($branch)) ?></span>
              <a class="bcard__phone" href="tel:<?= htmlspecialchars(str_replace(' ', '', $branch['leader_phone'])) ?>">
                <i class="fa-solid fa-phone" aria-hidden="true"></i> <?= htmlspecialchars($branch['leader_phone']) ?>
              </a>
              <a class="bcard__phone" href="mailto:<?= htmlspecialchars($branch['leader_email']) ?>">
                <i class="fa-regular fa-envelope" aria-hidden="true"></i> <?= htmlspecialchars($branch['leader_email']) ?>
              </a>
            </span>
          </div>

          <p class="modal__group" style="margin-top:18px">Address</p>
          <p style="color:var(--ink-2);font-size:13px;line-height:1.6;margin-top:6px">
            <?= htmlspecialchars($branch['address']) ?><br>
            <?= htmlspecialchars($branch['suburb']) ?>, <?= htmlspecialchars($branch['city']) ?><br>
            <?= htmlspecialchars($branch['province']) ?>
          </p>

          <div class="minimap" aria-label="Map placeholder">
            <span class="mappin" style="left:50%;top:52%">
              <span class="mappin__dot" style="background:var(--brand-500)"><span><i class="fa-solid fa-church" style="font-size:10px"></i></span></span>
            </span>
            <span class="minimap__coords"><?= number_format($branch['latitude'], 4) ?>, <?= number_format($branch['longitude'], 4) ?></span>
          </div>
        </div>
      </section>

      <section class="panel">
        <header class="panel__head">
          <span class="stat-tile__icon tone-info" style="width:32px;height:32px;font-size:13px" aria-hidden="true"><i class="fa-regular fa-clock"></i></span>
          <h2>Service times</h2>
        </header>
        <div class="dt-wrap">
          <table class="dt">
            <thead><tr><th>Day</th><th>Time</th><th>Service</th><th>Expected</th></tr></thead>
            <tbody>
              <?php foreach ($service_times as [$day, $from, $to, $name, $expect]): ?>
                <tr>
                  <td class="strong"><?= $day ?></td>
                  <td class="nowrap"><?= $from ?> &ndash; <?= $to ?></td>
                  <td><?= $name ?></td>
                  <td class="nowrap"><?= number_format($expect) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <div class="grid grid--2" style="margin-top:16px;align-items:start">
      <?php if (mu_mod('attendance')): ?>
        <section class="panel">
          <header class="panel__head">
            <span class="stat-tile__icon tone-purple" style="width:32px;height:32px;font-size:13px" aria-hidden="true"><i class="fa-solid fa-chart-line"></i></span>
            <h2>Attendance, last 12 weeks</h2>
          </header>
          <div class="panel__body">
            <div class="chart-wrap" style="height:250px"><canvas id="attChart" role="img" aria-label="Attendance over the last 12 weeks"></canvas></div>
          </div>
        </section>
      <?php endif; ?>

      <?php if (mu_can('finance.reports')): ?>
        <section class="panel">
          <header class="panel__head">
            <span class="stat-tile__icon tone-green" style="width:32px;height:32px;font-size:13px" aria-hidden="true"><i class="fa-solid fa-chart-column"></i></span>
            <h2>Giving, last 6 months</h2>
          </header>
          <div class="panel__body">
            <div class="chart-wrap" style="height:250px"><canvas id="giveChart" role="img" aria-label="Giving over the last six months"></canvas></div>
          </div>
        </section>
      <?php endif; ?>
    </div>

    <section class="panel" style="margin-top:16px">
      <header class="panel__head">
        <span class="stat-tile__icon tone-amber" style="width:32px;height:32px;font-size:13px" aria-hidden="true"><i class="fa-regular fa-clock"></i></span>
        <h2>Recent activity</h2>
      </header>
      <div class="panel__body">
        <div class="timeline">
          <?php
            $feed = [
              ['fa-clipboard-check', 'Attendance recorded', $services_hist[0]['present'] . ' present at ' . $services_hist[0]['service'] . '.', $services_hist[0]['date']],
              ['fa-user-plus', 'New members added', '3 members joined this month.', $services_hist[1]['date']],
              ['fa-hand-holding-dollar', 'Giving recorded', 'Monthly returns submitted to the ' . mb_strtolower(t('org_singular')) . ' office.', $services_hist[2]['date']],
              ['fa-people-group', 'Cell meeting logged', 'Two cell groups reported this week.', $services_hist[3]['date']],
            ];
            foreach ($feed as [$ic, $title, $note, $when]):
          ?>
            <div class="tl-item">
              <div class="tl-item__head">
                <span class="tl-item__method"><i class="fa-solid <?= $ic ?>" aria-hidden="true"></i> <?= $title ?></span>
                <span class="tl-item__date"><?= mu_date($when) ?></span>
              </div>
              <p class="tl-item__notes"><?= htmlspecialchars($note) ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  </div>

  <!-- ─────────────── MEMBERS ─────────────── -->
  <div class="tabpanel" data-panel="members" hidden>
    <section class="panel">
      <header class="panel__head">
        <span class="stat-tile__icon tone-blue" style="width:32px;height:32px;font-size:13px" aria-hidden="true"><i class="fa-solid fa-users"></i></span>
        <h2>Members at <?= htmlspecialchars($branch['name']) ?></h2>
        <span class="count-chip"><?= number_format((int) $branch['members_count']) ?></span>
        <a class="btn btn--ghost" href="<?= $base_url ?>members/all.php?branch=<?= (int) $branch['id'] ?>">
          <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> Open in directory
        </a>
      </header>
      <div class="dt-wrap">
        <table class="dt">
          <thead><tr><th>Member</th><th>Phone</th><th>Gender</th><th>Age</th><th>Status</th><th>Joined</th></tr></thead>
          <tbody>
            <?php foreach ($branch_members as $m): ?>
              <tr>
                <td>
                  <span class="person"><?= mu_av($m['name'], 'sm') ?>
                    <span class="person__text"><span class="person__name"><?= htmlspecialchars($m['name']) ?></span>
                    <span class="tsub"><?= htmlspecialchars($m['member_no']) ?></span></span>
                  </span>
                </td>
                <td class="nowrap"><?= htmlspecialchars($m['phone']) ?></td>
                <td><?= htmlspecialchars($m['gender']) ?></td>
                <td><?= (int) $m['age'] ?></td>
                <td><span class="spill is-<?= strtolower($m['status']) ?>"><?= htmlspecialchars($m['status']) ?></span></td>
                <td class="nowrap"><?= mu_date($m['joined']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="pager">
        <span>Showing <strong><?= count($branch_members) ?></strong> of <strong><?= number_format((int) $branch['members_count']) ?></strong> members</span>
        <a class="chip-btn" href="<?= $base_url ?>members/all.php?branch=<?= (int) $branch['id'] ?>">View all</a>
      </div>
    </section>
  </div>

  <!-- ─────────────── LEADERSHIP ─────────────── -->
  <div class="tabpanel" data-panel="leadership" hidden>
    <div class="cardgrid cardgrid--4 stagger">
      <?php foreach ($leadership as [$role, $name, $phone, $email]): ?>
        <article class="gcard" style="align-items:center;text-align:center">
          <?= mu_av($name, 'lg') ?>
          <p style="margin-top:11px;color:var(--faint);font-size:10.5px;font-weight:800;letter-spacing:.1em;text-transform:uppercase"><?= htmlspecialchars($role) ?></p>
          <h3 style="margin-top:5px;color:var(--ink);font-size:14px;font-weight:800"><?= htmlspecialchars($name) ?></h3>
          <a href="tel:<?= htmlspecialchars(str_replace(' ', '', $phone)) ?>" style="margin-top:9px;color:var(--muted);font-size:12px;font-weight:600">
            <i class="fa-solid fa-phone" style="color:var(--brand-500)" aria-hidden="true"></i> <?= htmlspecialchars($phone) ?>
          </a>
          <a href="mailto:<?= htmlspecialchars($email) ?>" style="margin-top:4px;color:var(--muted);font-size:11.5px;overflow:hidden;text-overflow:ellipsis;max-width:100%">
            <i class="fa-regular fa-envelope" style="color:var(--brand-300)" aria-hidden="true"></i> <?= htmlspecialchars($email) ?>
          </a>
        </article>
      <?php endforeach; ?>
    </div>

    <?php if (mu_mod('departments')): ?>
      <section class="panel" style="margin-top:16px">
        <header class="panel__head">
          <span class="stat-tile__icon tone-violet" style="width:32px;height:32px;font-size:13px" aria-hidden="true"><i class="fa-solid fa-sitemap"></i></span>
          <h2>Department heads</h2>
        </header>
        <div class="panel__body" style="padding:0">
          <div class="clist" style="border:0;border-radius:0">
            <?php foreach ($branch_depts as $d): ?>
              <div class="crow">
                <span class="stat-tile__icon tone-<?= htmlspecialchars($d['color']) ?>" style="width:28px;height:28px;font-size:11px;border-radius:8px" aria-hidden="true"><i class="fa-solid <?= htmlspecialchars($d['icon']) ?>"></i></span>
                <span class="crow__name"><?= htmlspecialchars($d['head']) ?>
                  <span style="display:block;color:var(--faint);font-size:11px;font-weight:500"><?= htmlspecialchars($d['name']) ?></span>
                </span>
                <span class="crow__phone"><?= htmlspecialchars($d['head_phone']) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </section>
    <?php endif; ?>
  </div>

  <?php if (mu_mod('departments')): ?>
    <!-- ─────────────── DEPARTMENTS ─────────────── -->
    <div class="tabpanel" data-panel="departments" hidden>
      <div class="cardgrid cardgrid--3 stagger">
        <?php foreach ($branch_depts as $d): ?>
          <article class="gcard">
            <span class="stat-tile__icon tone-<?= htmlspecialchars($d['color']) ?>" style="width:44px;height:44px;border-radius:13px;font-size:17px" aria-hidden="true">
              <i class="fa-solid <?= htmlspecialchars($d['icon']) ?>"></i>
            </span>
            <h3 style="margin-top:13px;color:var(--ink);font-size:15px;font-weight:800"><?= htmlspecialchars($d['name']) ?></h3>
            <p style="margin-top:4px;color:var(--muted);font-size:12px;line-height:1.5"><?= htmlspecialchars($d['description']) ?></p>
            <div style="display:flex;align-items:center;gap:9px;margin-top:13px">
              <?= mu_av($d['head'], 'sm') ?>
              <span style="color:var(--ink);font-size:12.5px;font-weight:700"><?= htmlspecialchars($d['head']) ?></span>
            </div>
            <div style="display:flex;gap:16px;margin-top:13px;padding-top:12px;border-top:1px solid var(--line);color:var(--muted);font-size:11.5px;font-weight:600">
              <span><?= (int) round($d['members'] / 3) ?> serving here</span>
              <span><?= htmlspecialchars($d['day']) ?>s <?= htmlspecialchars($d['time']) ?></span>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if (mu_mod('cell_groups')): ?>
    <!-- ─────────────── CELL GROUPS ─────────────── -->
    <div class="tabpanel" data-panel="cells" hidden>
      <div class="cardgrid cardgrid--3 stagger">
        <?php foreach ($branch_cells as $c): ?>
          <article class="gcard">
            <div style="display:flex;align-items:center;gap:9px;flex-wrap:wrap">
              <h3 style="color:var(--ink);font-size:15px;font-weight:800"><?= htmlspecialchars($c['name']) ?></h3>
              <span class="pill is-brand"><?= htmlspecialchars($c['zone']) ?></span>
            </div>
            <div style="display:flex;align-items:center;gap:9px;margin-top:13px">
              <?= mu_av($c['leader'], 'sm') ?>
              <span style="min-width:0">
                <span style="display:block;color:var(--ink);font-size:12.5px;font-weight:700"><?= htmlspecialchars($c['leader']) ?></span>
                <span style="color:var(--muted);font-size:11.5px"><?= htmlspecialchars($c['leader_phone']) ?></span>
              </span>
            </div>
            <p style="margin-top:12px;color:var(--muted);font-size:12px">
              <i class="fa-regular fa-calendar" style="color:var(--brand-300)" aria-hidden="true"></i> <?= htmlspecialchars($c['day']) ?>s <?= htmlspecialchars($c['time']) ?><br>
              <i class="fa-solid fa-location-dot" style="color:var(--brand-300)" aria-hidden="true"></i> <?= htmlspecialchars($c['venue']) ?>
            </p>
            <div style="display:flex;gap:16px;margin-top:12px;padding-top:12px;border-top:1px solid var(--line);color:var(--muted);font-size:11.5px;font-weight:600">
              <span><?= (int) $c['members'] ?> members</span>
              <span><?= (int) $c['avg_attendance'] ?>% attendance</span>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if (mu_mod('attendance')): ?>
    <!-- ─────────────── ATTENDANCE ─────────────── -->
    <div class="tabpanel" data-panel="attendance" hidden>
      <section class="panel" style="margin-bottom:16px">
        <header class="panel__head">
          <span class="stat-tile__icon tone-purple" style="width:32px;height:32px;font-size:13px" aria-hidden="true"><i class="fa-solid fa-chart-line"></i></span>
          <h2>Attendance trend</h2>
        </header>
        <div class="panel__body">
          <div class="chart-wrap" style="height:280px"><canvas id="attChart2" role="img" aria-label="Attendance trend"></canvas></div>
        </div>
      </section>

      <section class="panel">
        <header class="panel__head">
          <span class="stat-tile__icon tone-blue" style="width:32px;height:32px;font-size:13px" aria-hidden="true"><i class="fa-solid fa-table-list"></i></span>
          <h2>Service history</h2>
        </header>
        <div class="dt-wrap">
          <table class="dt">
            <thead><tr><th>Date</th><th>Service</th><th>Present</th><th>Visitors</th><?php if (mu_can('finance.reports')): ?><th>Offering</th><?php endif; ?><th>Rate</th></tr></thead>
            <tbody>
              <?php foreach ($services_hist as $s): ?>
                <tr>
                  <td class="nowrap strong"><?= mu_date($s['date']) ?></td>
                  <td class="nowrap"><?= htmlspecialchars($s['service']) ?></td>
                  <td><?= number_format($s['present']) ?></td>
                  <td><?= (int) $s['visitors'] ?></td>
                  <?php if (mu_can('finance.reports')): ?><td class="nowrap">$<?= number_format($s['offering']) ?></td><?php endif; ?>
                  <td style="min-width:110px">
                    <?php $rate = min(100, (int) round($s['present'] / max(1, $branch['members_count']) * 100)); ?>
                    <span class="minibar">
                      <strong style="color:var(--ink)"><?= $rate ?>%</strong>
                      <span class="minibar__track"><span class="minibar__fill" style="width:<?= $rate ?>%;background:var(--brand-500)"></span></span>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  <?php endif; ?>

  <?php if (mu_can('finance.reports')): ?>
    <!-- ─────────────── GIVING ─────────────── -->
    <div class="tabpanel" data-panel="giving" hidden>
      <div class="grid grid--2" style="align-items:start">
        <section class="panel">
          <header class="panel__head">
            <span class="stat-tile__icon tone-green" style="width:32px;height:32px;font-size:13px" aria-hidden="true"><i class="fa-solid fa-chart-pie"></i></span>
            <h2>By contribution type</h2>
          </header>
          <div class="panel__body">
            <div class="chart-wrap" style="height:250px"><canvas id="typeChart" role="img" aria-label="Giving by contribution type"></canvas></div>
          </div>
        </section>

        <section class="panel">
          <header class="panel__head">
            <span class="stat-tile__icon tone-blue" style="width:32px;height:32px;font-size:13px" aria-hidden="true"><i class="fa-solid fa-table-list"></i></span>
            <h2>Summary</h2>
          </header>
          <div class="dt-wrap">
            <table class="dt">
              <thead><tr><th>Type</th><th>This month</th><th>Share</th></tr></thead>
              <tbody>
                <?php foreach ($give_types as $label => $amount): ?>
                  <tr>
                    <td class="strong"><?= htmlspecialchars($label) ?></td>
                    <td class="nowrap">$<?= number_format($amount) ?></td>
                    <td style="min-width:110px">
                      <?php $share = (int) round($amount / max(1, $branch['monthly_giving']) * 100); ?>
                      <span class="minibar">
                        <strong style="color:var(--ink)"><?= $share ?>%</strong>
                        <span class="minibar__track"><span class="minibar__fill" style="width:<?= $share ?>%;background:var(--ok)"></span></span>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="pager">
            <span>Total this month</span>
            <strong style="color:var(--ink);font-size:15px">$<?= number_format($branch['monthly_giving']) ?></strong>
          </div>
        </section>
      </div>
    </div>
  <?php endif; ?>

  <?php if (mu_can('branches.edit')): ?>
    <!-- ─────────────── SETTINGS ─────────────── -->
    <div class="tabpanel" data-panel="settings" hidden>
      <section class="panel">
        <header class="panel__head">
          <span class="stat-tile__icon tone-slate" style="width:32px;height:32px;font-size:13px" aria-hidden="true"><i class="fa-solid fa-gear"></i></span>
          <h2><?= htmlspecialchars(t('branch_singular')) ?> details</h2>
        </header>
        <div class="panel__body">
          <div class="form-grid">
            <div class="field col-2"><label for="sName">Name</label><input class="input" id="sName" value="<?= htmlspecialchars($branch['name']) ?>"></div>
            <div class="field"><label for="sCode">Code</label><input class="input" id="sCode" value="<?= htmlspecialchars($branch['code']) ?>"></div>
            <div class="field">
              <label for="sGroup"><?= htmlspecialchars(t('group_singular')) ?></label>
              <select class="select" id="sGroup">
                <?php foreach (array_unique(array_column($branches, 'group_name')) as $g): ?>
                  <option<?= $g === $branch['group_name'] ? ' selected' : '' ?>><?= htmlspecialchars($g) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field col-2"><label for="sAddr">Address</label><input class="input" id="sAddr" value="<?= htmlspecialchars($branch['address']) ?>"></div>
            <div class="field"><label for="sSuburb">Suburb</label><input class="input" id="sSuburb" value="<?= htmlspecialchars($branch['suburb']) ?>"></div>
            <div class="field"><label for="sCity">City</label><input class="input" id="sCity" value="<?= htmlspecialchars($branch['city']) ?>"></div>
            <div class="field">
              <label for="sProvince">Province</label>
              <select class="select" id="sProvince">
                <?php foreach ($provinces_demo as $p): ?><option<?= $p === $branch['province'] ? ' selected' : '' ?>><?= htmlspecialchars($p) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label for="sStatus">Status</label>
              <select class="select" id="sStatus">
                <?php foreach (['active', 'inactive', 'planting'] as $st): ?>
                  <option value="<?= $st ?>"<?= $st === $branch['status'] ? ' selected' : '' ?>><?= ucfirst($st) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field"><label for="sLat">Latitude</label><input class="input" id="sLat" value="<?= $branch['latitude'] ?>"></div>
            <div class="field"><label for="sLng">Longitude</label><input class="input" id="sLng" value="<?= $branch['longitude'] ?>"></div>
          </div>
          <div style="display:flex;gap:9px;margin-top:18px;padding-top:16px;border-top:1px solid var(--line)">
            <span style="flex:1"></span>
            <button class="btn btn--ghost" type="button">Cancel</button>
            <button class="btn" type="button" data-toast="<?= htmlspecialchars(t('branch_singular')) ?> updated">Save changes</button>
          </div>
        </div>
      </section>
    </div>
  <?php endif; ?>

<?php endif; ?>
</div>

<?php if ($has_access && mu_mod('communication')): ?>
<div class="modal-scrim" id="modalMessage" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="msgTitle">
    <header class="modal__head">
      <h2 id="msgTitle">Message <?= htmlspecialchars($branch['name']) ?></h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>
    <div class="modal__body">
      <p class="modal__hint">To <strong><?= htmlspecialchars(branch_leader_label($branch)) ?></strong> &middot; <?= htmlspecialchars($branch['leader_phone']) ?></p>
      <div class="field" style="margin-bottom:14px">
        <label>Channel</label>
        <div class="seg">
          <input type="checkbox" id="chEmail" checked><label for="chEmail"><i class="fa-regular fa-envelope" aria-hidden="true"></i> Email</label>
          <input type="checkbox" id="chSms" checked><label for="chSms"><i class="fa-solid fa-comment-sms" aria-hidden="true"></i> SMS</label>
        </div>
      </div>
      <div class="field">
        <label for="msgBody">Message</label>
        <textarea class="textarea" id="msgBody" rows="5" maxlength="480" placeholder="Type your message&hellip;"></textarea>
        <p class="hint"><span data-char-count>0</span> / 480 characters</p>
      </div>
    </div>
    <footer class="modal__foot">
      <span class="modal__spacer"></span>
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <button class="btn" type="button" data-close data-toast="Message queued for sending">Send</button>
    </footer>
  </div>
</div>
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
  <p class="demo__hint">Switch role to see the tabs filter themselves</p>
  <ul class="demo__list">
    <?php foreach ($demo_roles as $key => $r): ?>
      <li><a class="demo__role<?= $key === $demo_role ? ' is-on' : '' ?>"
             href="?<?= http_build_query(['id' => $want, 'role' => $key]) ?>"
             <?= $key === $demo_role ? 'aria-current="true"' : '' ?>>
        <span class="demo__av" aria-hidden="true"><?= htmlspecialchars($r['user']['initials']) ?></span>
        <?= htmlspecialchars($r['user']['role_label']) ?>
      </a></li>
    <?php endforeach; ?>
  </ul>
</details>
<?php /* ═══════════════════════ END DEMO ═══════════════════════ */ ?>

<?php /* ─────────────────────────────────────────────────────────────────────
   A detail page has no menu entry of its own, so nothing in the sidebar
   lights up. The parent list entry is the right thing to mark — done here
   because components/sidebar.php is out of scope for this run.
   ───────────────────────────────────────────────────────────────────── */ ?>
<script>
(function () {
  var parent = document.querySelector('.nav-item[href$="branches/index.php"]');
  if (parent && !document.querySelector('.nav-item.is-active')) {
    parent.classList.add('is-active');
    parent.setAttribute('aria-current', 'page');
  }
})();
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function () {
  'use strict';
  var still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

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

  var tabs = document.querySelectorAll('.tabs--page [data-tab]');
  if (!tabs.length) { return; }                    /* no access: nothing else */

<?php if ($has_access): ?>
  var ATT = <?= json_encode(array_reverse($att_weeks)) ?>;
  var GIVE = <?= json_encode(array_reverse($give_months)) ?>;
  var TYPES = <?= json_encode($give_types) ?>;
  var CAN_FIN = <?= mu_can('finance.reports') ? 'true' : 'false' ?>;
  var HAS_ATT = <?= mu_mod('attendance') ? 'true' : 'false' ?>;

  /* ── counters ── */
  [].forEach.call(document.querySelectorAll('[data-count]'), function (el) {
    var target = parseFloat(el.getAttribute('data-count')) || 0;
    var fmt = el.getAttribute('data-fmt');
    var render = function (v) {
      if (fmt === 'money') { return '$' + Math.round(v).toLocaleString(); }
      if (fmt === 'pct')   { return (v >= 0 ? '+' : '') + v.toFixed(1) + '%'; }
      return Math.round(v).toLocaleString();
    };
    if (still) { el.textContent = render(target); return; }
    var start = performance.now();
    (function step(now) {
      var p = Math.min(1, (now - start) / 900), eased = 1 - Math.pow(1 - p, 3);
      el.textContent = render(target * eased);
      if (p < 1) { requestAnimationFrame(step); }
    })(start);
  });

  /* ── tabs ── */
  var built = {};
  function show(key) {
    [].forEach.call(tabs, function (b) { b.setAttribute('aria-selected', String(b.getAttribute('data-tab') === key)); });
    [].forEach.call(document.querySelectorAll('[data-panel]'), function (p) {
      p.hidden = p.getAttribute('data-panel') !== key;
    });
    try { history.replaceState(null, '', '#' + key); } catch (e) {}
    build(key);
  }
  [].forEach.call(tabs, function (b) {
    b.addEventListener('click', function () { show(b.getAttribute('data-tab')); });
  });
  [].forEach.call(document.querySelectorAll('[data-tab-jump]'), function (b) {
    b.addEventListener('click', function () { show(b.getAttribute('data-tab-jump')); });
  });

  /* ── charts, built the first time their tab is opened ── */
  if (window.Chart) {
    Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.color = '#6E6880';
  }
  var LINE = { borderColor: '#662F97', backgroundColor: 'rgba(102,47,151,.1)',
               fill: true, tension: .35, pointRadius: 3, borderWidth: 2 };
  var AXES = { x: { grid: { display: false }, border: { display: false } },
               y: { grid: { color: '#ECE7F3' }, border: { display: false }, beginAtZero: true } };

  function line(id, labels, data, label) {
    var cv = document.getElementById(id);
    if (!cv || !window.Chart || built[id]) { return; }
    built[id] = new Chart(cv, {
      type: 'line',
      data: { labels: labels, datasets: [Object.assign({ label: label, data: data }, LINE)] },
      options: { responsive: true, maintainAspectRatio: false,
        animation: still ? false : { duration: 500 },
        plugins: { legend: { display: false } }, scales: AXES }
    });
  }

  function build(key) {
    if (HAS_ATT && (key === 'overview' || key === 'attendance')) {
      var wk = ATT.map(function (_, i) { return 'W' + (i + 1); });
      line('attChart', wk, ATT, 'Attendance');
      line('attChart2', wk, ATT, 'Attendance');
    }
    if (CAN_FIN && key === 'overview') {
      var months = ['Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug'];
      var cv = document.getElementById('giveChart');
      if (cv && window.Chart && !built.giveChart) {
        built.giveChart = new Chart(cv, {
          type: 'bar',
          data: { labels: months, datasets: [{ label: 'Giving', data: GIVE, backgroundColor: '#662F97', borderRadius: 6, maxBarThickness: 34 }] },
          options: { responsive: true, maintainAspectRatio: false,
            animation: still ? false : { duration: 500 },
            plugins: { legend: { display: false } }, scales: AXES }
        });
      }
    }
    if (CAN_FIN && key === 'giving') {
      var cv2 = document.getElementById('typeChart');
      if (cv2 && window.Chart && !built.typeChart) {
        built.typeChart = new Chart(cv2, {
          type: 'doughnut',
          data: { labels: Object.keys(TYPES),
                  datasets: [{ data: Object.keys(TYPES).map(function (k) { return TYPES[k]; }),
                    backgroundColor: ['#662F97', '#B48FDA', '#8F5CC2', '#D3BAEA', '#56287F'],
                    borderWidth: 0, hoverOffset: 6 }] },
          options: { responsive: true, maintainAspectRatio: false,
            animation: still ? false : { duration: 500 },
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 12 } } } }
        });
      }
    }
  }

  /* ── modal ── */
  document.addEventListener('click', function (e) {
    var open = e.target.closest('[data-open]');
    if (open) {
      e.preventDefault();
      var m = document.getElementById(open.getAttribute('data-open'));
      if (m) { m.hidden = false; document.body.style.overflow = 'hidden'; }
      return;
    }
    var close = e.target.closest('[data-close]');
    if (close) { e.preventDefault(); close.closest('.modal-scrim').hidden = true; document.body.style.overflow = ''; return; }
    if (e.target.classList.contains('modal-scrim')) { e.target.hidden = true; document.body.style.overflow = ''; }
  });
  var body = document.getElementById('msgBody');
  if (body) {
    body.addEventListener('input', function () { document.querySelector('[data-char-count]').textContent = body.value.length; });
  }
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') { return; }
    [].forEach.call(document.querySelectorAll('.modal-scrim'), function (m) {
      if (!m.hidden) { m.hidden = true; document.body.style.overflow = ''; }
    });
  });

  /* Deep link straight to a tab. */
  var hash = (window.location.hash || '').replace('#', '');
  var valid = [].slice.call(tabs).map(function (b) { return b.getAttribute('data-tab'); });
  show(valid.indexOf(hash) !== -1 ? hash : valid[0]);
<?php endif; ?>
})();
</script>

<?php require __DIR__ . '/../components/footer.php'; ?>

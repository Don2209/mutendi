<?php
/**
 * Mutendi CMS — Visitors & Follow-Up.
 *
 * A pipeline, not a list: visitors move through five stages from first visit
 * to membership. UI only — dragging, filters and forms are visual.
 * Requires the 'visitors' module.
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
    function mu_ago(?int $days): string {
        if ($days === null) { return 'Never'; }
        if ($days <= 0) { return 'Today'; }
        if ($days === 1) { return 'Yesterday'; }
        if ($days < 7)  { return $days . ' days ago'; }
        if ($days < 31) { $w = (int) floor($days / 7); return $w . ' week' . ($w > 1 ? 's' : '') . ' ago'; }
        $m = (int) floor($days / 30);
        return $m . ' month' . ($m > 1 ? 's' : '') . ' ago';
    }
    function mu_date(string $iso, string $fmt = 'd M Y'): string { return date($fmt, strtotime($iso)); }
}

/** Days-in-stage pill: amber past 7 days, red past 14. */
function mu_age_pill(int $days): string {
    $cls = $days > 14 ? 'is-late' : ($days > 7 ? 'is-warn' : 'is-fresh');
    return '<span class="age-pill ' . $cls . '">' . $days . 'd</span>';
}

$has_module = mu_mod('visitors');
$stages = [
    'New Visitor'   => ['tone' => 'var(--info)',    'icon' => 'fa-door-open'],
    'Contacted'     => ['tone' => 'var(--brand-500)','icon' => 'fa-phone'],
    'Visited / Met' => ['tone' => '#6D28D9',        'icon' => 'fa-handshake'],
    'Ready to Join' => ['tone' => 'var(--warn)',    'icon' => 'fa-star'],
    'Converted'     => ['tone' => 'var(--ok)',      'icon' => 'fa-circle-check'],
];

$rows  = $has_module ? $visitors_demo : [];

/* ─────────────────────────── BRANCH AWARENESS ───────────────────────────
   Resolves which branch is in view (the top bar's switcher sets ?branch=) and
   scopes this page's data to it. Every addition below is inert for a single
   church: is_multi_branch() is false, so no column, chip, filter or toggle is
   rendered and the page behaves exactly as it did before.
   ──────────────────────────────────────────────────────────────────────── */
require_once __DIR__ . '/../components/branch-switcher.php';

$branch_aware   = is_multi_branch();
$viewing_all    = !$branch_aware || $current_branch === 'all' || $current_branch === null;
$show_branch    = $branch_aware && $viewing_all;      /* column, chip and filter */
$branch_options = $branch_aware ? get_visible_branches() : [];

if (!function_exists('mu_branch_for')) {
    /**
     * Which branch a demo record belongs to. Deterministic from the record's
     * own key, so a person or a group never hops between branches on reload.
     * LATER: the row carries its own branch_id and this helper disappears.
     */
    function mu_branch_for(string $key): ?array {
        static $pool = null;
        if ($pool === null) { $pool = get_visible_branches(); }
        if (!$pool) { return null; }
        return $pool[crc32($key) % count($pool)];
    }

    /** One colour per group, so branches in the same group read together. */
    function mu_branch_tone(array $b): string {
        static $tones = [];
        static $pool = ['var(--info)', 'var(--brand-500)', '#0F766E', 'var(--warn)', '#6D28D9'];
        $g = $b['group_name'] ?? '';
        if (!isset($tones[$g])) { $tones[$g] = $pool[count($tones) % count($pool)]; }
        return $tones[$g];
    }

    /** The small coloured chip naming a record's branch. */
    function mu_branch_chip(?array $b): string {
        if (!$b) { return ''; }
        return '<span class="bchip" title="' . htmlspecialchars($b['name']) . '">'
             . '<span class="bchip__dot" style="background:' . mu_branch_tone($b) . '" aria-hidden="true"></span>'
             . htmlspecialchars($b['name']) . '</span>';
    }

    /**
     * Scales an organisation-wide headline figure to the selected branch, by
     * that branch's share of the roll.
     * LATER: the figure arrives from a query already scoped to :branch_id.
     */
    function mu_branch_share($orgValue) {
        global $current_branch, $organisation;
        if ($current_branch === 'all' || $current_branch === null) { return $orgValue; }
        $b = get_branch($current_branch);
        if (!$b) { return $orgValue; }
        $total = max(1, (int) ($organisation['total_members'] ?? 1));
        return $orgValue * ((int) $b['members_count'] / $total);
    }
}

$stats = $people_stats['visitors'];

/* Scope the pipeline and the headline figures to the branch in view. */
if ($branch_aware && $rows) {
    foreach ($rows as $i => $v) { $rows[$i]['_branch'] = mu_branch_for($v['name'] . $v['id']); }
    if (!$viewing_all) {
        $rows = array_values(array_filter($rows, function ($v) use ($current_branch) {
            return $v['_branch'] && (int) $v['_branch']['id'] === (int) $current_branch;
        }));
    }
    foreach ($stats as $k => $v2) { $stats[$k] = max(0, (int) round(mu_branch_share($v2))); }
}

/* Anyone with no contact in 7+ days, or never contacted at all. */
$overdue = array_values(array_filter($rows, function ($v) {
    return $v['stage'] !== 'Converted' && ($v['last_contact_days'] === null || $v['last_contact_days'] >= 7);
}));

$stage_counts = [];
foreach (array_keys($stages) as $s) {
    $stage_counts[$s] = count(array_filter($rows, fn($v) => $v['stage'] === $s));
}

$page_title = 'Visitors & Follow-Up';
require __DIR__ . '/../components/header.php';
?>

<div class="page">

  <header class="page__head">
    <div>
      <nav class="crumbs" aria-label="Breadcrumb">
        <a href="<?= $base_url ?>index.php">Dashboard</a>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span>People</span>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span aria-current="page">Visitors &amp; Follow-Up</span>
      </nav>
      <div class="title-row">
        <h1 class="page__title">Visitors &amp; Follow-Up</h1>
        <span class="count-chip"><?= $stats['pending'] ?> pending</span>
      </div>
      <p class="page__sub">Track first-time visitors and follow up until they join.</p>
    </div>
    <?php if ($has_module): ?>
      <div class="page__actions">
        <?php if (mu_can('members.add')): ?>
          <button class="btn" type="button" data-open="modalVisitor"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add Visitor</button>
        <?php endif; ?>
        <?php if (mu_can('members.export')): ?>
          <div class="drop" data-menu>
            <button class="btn btn--ghost" type="button" aria-haspopup="true" aria-expanded="false" data-menu-btn>
              <i class="fa-solid fa-file-export" aria-hidden="true"></i> Export
              <i class="fa-solid fa-chevron-down" style="font-size:10px;opacity:.7" aria-hidden="true"></i>
            </button>
            <div class="menu" data-menu-panel hidden>
              <a class="menu__item" href="#" data-toast="CSV export started"><i class="fa-solid fa-file-csv" aria-hidden="true"></i> Export as CSV</a>
              <a class="menu__item" href="#" data-toast="PDF export started"><i class="fa-solid fa-file-pdf" aria-hidden="true"></i> Export as PDF</a>
            </div>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </header>

<?php if (!$has_module): ?>

  <section class="panel">
    <div class="empty">
      <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-plug-circle-xmark"></i></span>
      <h3>The Visitors module is switched off</h3>
      <p>Your church's plan does not include visitor follow-up. A church administrator can request it from the platform team.</p>
      <a class="btn" href="<?= $base_url ?>index.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Dashboard</a>
    </div>
  </section>

<?php else: ?>

  <div class="stat-strip">
    <?php foreach ([
      ['Visitors This Month', $stats['this_month'], 'fa-door-open',    'blue'],
      ['Pending Follow-Up',   $stats['pending'],    'fa-hourglass-half','amber'],
      ['Contacted',           $stats['contacted'],  'fa-phone-volume', 'purple'],
      ['Converted to Members',$stats['converted'],  'fa-circle-check', 'green'],
    ] as [$label, $value, $icon, $tone]): ?>
      <div class="stat-tile" style="cursor:default">
        <span class="stat-tile__icon tone-<?= $tone ?>" aria-hidden="true"><i class="fa-solid <?= $icon ?>"></i></span>
        <span class="stat-tile__body">
          <span class="stat-tile__value" data-count="<?= $value ?>">0</span>
          <span class="stat-tile__label"><?= $label ?></span>
        </span>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="toolbar">
    <div class="viewswitch" role="group" aria-label="View">
      <button type="button" data-view="board" aria-pressed="true"  aria-label="Pipeline board"><i class="fa-solid fa-diagram-project" aria-hidden="true"></i></button>
      <button type="button" data-view="table" aria-pressed="false" aria-label="Table view"><i class="fa-solid fa-table-list" aria-hidden="true"></i></button>
    </div>
    <p style="color:var(--muted);font-size:12.5px;font-weight:600"><span data-result-count><?= count($rows) ?></span> shown</p>
  </div>

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
          <input class="input" type="search" id="fSearch" data-search placeholder="Search visitors by name or phone&hellip;">
          <button class="search-field__clear" type="button" data-search-clear hidden aria-label="Clear search"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
      </div>
      <div class="field">
        <label for="fStage">Stage</label>
        <select class="select" id="fStage" data-filter>
          <option>All</option>
          <?php foreach (array_keys($stages) as $s): ?><option><?= htmlspecialchars($s) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="fAssigned">Assigned to</label>
        <select class="select" id="fAssigned" data-filter>
          <option>All</option>
          <?php foreach (array_unique(array_column($rows, 'assigned_to')) as $a): ?><option><?= htmlspecialchars($a) ?></option><?php endforeach; ?>
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
        <label for="fService">Service attended</label>
        <select class="select" id="fService" data-filter>
          <option>All</option>
          <?php foreach ($services_demo as $s): ?><option><?= htmlspecialchars($s) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label for="fFrom">First visit from</label><input class="input" type="date" id="fFrom"></div>
      <div class="field"><label for="fTo">First visit to</label><input class="input" type="date" id="fTo"></div>
      <div class="field">
        <label for="fWait">Days waiting</label>
        <select class="select" id="fWait" data-filter><option>All</option><option>Under 7</option><option>7-14</option><option>Over 14</option></select>
      </div>
      <div class="filters__actions">
        <button class="btn" type="button" data-toast="Filters applied"><i class="fa-solid fa-check" aria-hidden="true"></i> Apply</button>
        <button class="btn btn--ghost" type="button" data-reset-filters><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Reset</button>
      </div>
    </div>
    <div class="chips-row" data-filter-chips hidden></div>
  </section>

  <section id="listPanel" aria-live="polite">
    <div data-skeleton>
      <div class="board">
        <?php for ($i = 0; $i < 5; $i++): ?>
          <div class="bcol"><div class="bcol__body">
            <?php for ($j = 0; $j < 2; $j++): ?>
              <div class="sk-card" style="padding:12px">
                <div style="display:flex;gap:9px;align-items:center"><span class="sk sk--av" style="width:28px;height:28px"></span><span class="sk sk--text" style="flex:1"></span></div>
                <span class="sk sk--line" style="width:80%"></span><span class="sk sk--line" style="width:60%"></span>
              </div>
            <?php endfor; ?>
          </div></div>
        <?php endfor; ?>
      </div>
    </div>

    <div data-content>

      <!-- ─────────────── PIPELINE BOARD ─────────────── -->
      <div data-view-panel="board">
        <div class="board">
          <?php foreach ($stages as $stage => $meta): ?>
            <div class="bcol" data-stage="<?= htmlspecialchars($stage) ?>">
              <header class="bcol__head" style="color:<?= $meta['tone'] ?>">
                <i class="fa-solid <?= $meta['icon'] ?>" aria-hidden="true"></i>
                <span><?= htmlspecialchars($stage) ?></span>
                <span class="bcol__count"><b data-stage-count><?= $stage_counts[$stage] ?></b></span>
              </header>
              <div class="bcol__body" data-dropzone>
                <?php foreach (array_filter($rows, fn($v) => $v['stage'] === $stage) as $v): ?>
                  <article class="vcard" draggable="true" data-card data-visitor="<?= (int) $v['id'] ?>"
                           data-name="<?= htmlspecialchars(mb_strtolower($v['name'] . ' ' . $v['phone'])) ?>"
                           data-stage="<?= htmlspecialchars($v['stage']) ?>"
                           data-assigned="<?= htmlspecialchars($v['assigned_to']) ?>"
                           data-service="<?= htmlspecialchars($v['service']) ?>"
                           data-wait="<?= (int) $v['stage_days'] ?>"
                           <?= $branch_aware ? 'data-branch="' . htmlspecialchars($v['_branch']['name'] ?? '') . '"' : '' ?>>
                    <div class="vcard__top">
                      <?= mu_av($v['name'], 'sm') ?>
                      <span class="vcard__name"><?= htmlspecialchars($v['name']) ?></span>
                      <?= mu_age_pill((int) $v['stage_days']) ?>
                    </div>
                    <?php if ($show_branch): ?><p style="margin-top:9px"><?= mu_branch_chip($v['_branch'] ?? null) ?></p><?php endif; ?>
                    <div class="vcard__meta">
                      <span><i class="fa-solid fa-phone" aria-hidden="true"></i> <?= htmlspecialchars($v['phone']) ?></span>
                      <span><i class="fa-regular fa-calendar" aria-hidden="true"></i> First visit <?= mu_date($v['first_visit'], 'd M') ?></span>
                      <span><i class="fa-solid fa-user-plus" aria-hidden="true"></i> By <?= htmlspecialchars($v['invited_by']) ?></span>
                      <span><i class="fa-solid fa-user-shield" aria-hidden="true"></i> <?= htmlspecialchars($v['assigned_to']) ?></span>
                    </div>
                    <div class="vcard__foot">
                      <a class="iconbtn iconbtn--sm" href="tel:<?= htmlspecialchars(str_replace(' ', '', $v['phone'])) ?>" aria-label="Call <?= htmlspecialchars($v['name']) ?>"><i class="fa-solid fa-phone" aria-hidden="true"></i></a>
                      <?php if (mu_mod('communication')): ?>
                        <button class="iconbtn iconbtn--sm" type="button" data-toast="Message composer opened" aria-label="Message <?= htmlspecialchars($v['name']) ?>"><i class="fa-regular fa-comment" aria-hidden="true"></i></button>
                      <?php endif; ?>
                      <span class="spacer"></span>
                      <button class="chip-btn" type="button" data-open="modalFollowUp">Move</button>
                    </div>
                  </article>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- ─────────────── TABLE ─────────────── -->
      <div data-view-panel="table" hidden>
        <div class="panel">
          <div class="dt-wrap">
            <table class="dt">
              <thead>
                <tr>
                  <th style="width:38px"><input class="check" type="checkbox" data-check-all aria-label="Select all visitors"></th>
                  <th style="width:44px">#</th>
                  <th>Visitor</th>
                  <th>First Visit</th>
                  <?php if ($show_branch): ?><th><?= htmlspecialchars(t('branch_singular')) ?></th><?php endif; ?>
                  <th>Service Attended</th>
                  <th>Invited By</th>
                  <th>Stage</th>
                  <th>Assigned To</th>
                  <th>Last Contact</th>
                  <th>Follow-Ups</th>
                  <th style="text-align:right">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($rows as $i => $v): ?>
                  <tr data-row data-visitor="<?= (int) $v['id'] ?>"
                      data-name="<?= htmlspecialchars(mb_strtolower($v['name'] . ' ' . $v['phone'])) ?>"
                      data-stage="<?= htmlspecialchars($v['stage']) ?>"
                      data-assigned="<?= htmlspecialchars($v['assigned_to']) ?>"
                      data-service="<?= htmlspecialchars($v['service']) ?>"
                      data-wait="<?= (int) $v['stage_days'] ?>"
                      <?= $branch_aware ? 'data-branch="' . htmlspecialchars($v['_branch']['name'] ?? '') . '"' : '' ?>>
                    <td><input class="check" type="checkbox" data-row-check aria-label="Select <?= htmlspecialchars($v['name']) ?>"></td>
                    <td class="num"><?= $i + 1 ?></td>
                    <td>
                      <button class="person" type="button" data-open-visitor="<?= (int) $v['id'] ?>">
                        <?= mu_av($v['name'], 'sm') ?>
                        <span class="person__text"><span class="person__name"><?= htmlspecialchars($v['name']) ?></span>
                        <span class="tsub"><?= htmlspecialchars($v['phone']) ?></span></span>
                      </button>
                    </td>
                    <td class="nowrap"><?= mu_date($v['first_visit']) ?><span class="tsub"><?= mu_ago((int) $v['first_visit_days']) ?></span></td>
                    <?php if ($show_branch): ?><td class="nowrap"><?= mu_branch_chip($v['_branch'] ?? null) ?></td><?php endif; ?>
                    <td class="nowrap"><?= htmlspecialchars($v['service']) ?></td>
                    <td class="nowrap"><?= htmlspecialchars($v['invited_by']) ?></td>
                    <td><span class="pill is-brand"><?= htmlspecialchars($v['stage']) ?></span></td>
                    <td>
                      <span class="person"><?= mu_av($v['assigned_to'], 'xs') ?>
                        <span class="person__text"><span class="person__name" style="font-weight:600"><?= htmlspecialchars($v['assigned_to']) ?></span></span>
                      </span>
                    </td>
                    <td class="nowrap"<?= $v['last_contact_days'] === null ? ' style="color:var(--faint)"' : '' ?>><?= mu_ago($v['last_contact_days']) ?></td>
                    <td class="nowrap"><?= (int) $v['followups'] ?> attempt<?= $v['followups'] === 1 ? '' : 's' ?></td>
                    <td>
                      <div class="rowacts">
                        <a class="iconbtn iconbtn--sm" href="tel:<?= htmlspecialchars(str_replace(' ', '', $v['phone'])) ?>" aria-label="Call <?= htmlspecialchars($v['name']) ?>"><i class="fa-solid fa-phone" aria-hidden="true"></i></a>
                        <?php if (mu_mod('communication')): ?><button class="iconbtn iconbtn--sm" type="button" data-toast="Message composer opened" aria-label="Message"><i class="fa-regular fa-comment" aria-hidden="true"></i></button><?php endif; ?>
                        <?php if (mu_can('members.add')): ?><button class="iconbtn iconbtn--sm" type="button" data-open="modalConvert" aria-label="Convert <?= htmlspecialchars($v['name']) ?> to member"><i class="fa-solid fa-user-check" aria-hidden="true"></i></button><?php endif; ?>
                        <div class="drop" data-menu>
                          <button class="iconbtn iconbtn--sm" type="button" aria-haspopup="true" aria-expanded="false" data-menu-btn aria-label="More actions"><i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i></button>
                          <div class="menu menu--sm" data-menu-panel hidden>
                            <a class="menu__item" href="#" data-open-visitor="<?= (int) $v['id'] ?>"><i class="fa-regular fa-eye" aria-hidden="true"></i> View</a>
                            <?php if (mu_can('members.edit')): ?><a class="menu__item" href="#" data-open="modalVisitor"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</a><?php endif; ?>
                            <a class="menu__item" href="#" data-open="modalFollowUp"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i> Log Follow-Up</a>
                            <a class="menu__item" href="#" data-toast="Reassigned"><i class="fa-solid fa-user-shield" aria-hidden="true"></i> Reassign</a>
                            <?php if (mu_can('members.add')): ?><a class="menu__item" href="#" data-open="modalConvert"><i class="fa-solid fa-user-check" aria-hidden="true"></i> Convert to Member</a><?php endif; ?>
                            <a class="menu__item" href="#" data-toast="Marked not interested"><i class="fa-solid fa-ban" aria-hidden="true"></i> Mark Not Interested</a>
                            <?php if (mu_can('members.delete')): ?>
                              <div class="menu__sep" role="separator"></div>
                              <a class="menu__item menu__item--danger" href="#" data-toast="Visitor deleted"><i class="fa-solid fa-trash" aria-hidden="true"></i> Delete</a>
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
            <?php foreach ($rows as $v): ?>
              <article class="pcard" data-card
                       data-name="<?= htmlspecialchars(mb_strtolower($v['name'] . ' ' . $v['phone'])) ?>"
                       data-stage="<?= htmlspecialchars($v['stage']) ?>"
                       data-assigned="<?= htmlspecialchars($v['assigned_to']) ?>"
                       data-service="<?= htmlspecialchars($v['service']) ?>"
                       data-wait="<?= (int) $v['stage_days'] ?>"
                       <?= $branch_aware ? 'data-branch="' . htmlspecialchars($v['_branch']['name'] ?? '') . '"' : '' ?>>
                <button class="pcard__main" type="button" data-card-toggle>
                  <?= mu_av($v['name'], 'md') ?>
                  <span class="pcard__text">
                    <span class="pcard__name"><?= htmlspecialchars($v['name']) ?></span>
                    <span class="pcard__meta"><?= htmlspecialchars($v['phone']) ?></span>
                  </span>
                  <span class="pill is-brand"><?= htmlspecialchars($v['stage']) ?></span>
                  <i class="fa-solid fa-chevron-down pcard__chev" aria-hidden="true"></i>
                </button>
                <div class="pcard__more">
                  <dl>
                    <div class="pcard__row"><dt>First visit</dt><dd><?= mu_date($v['first_visit']) ?></dd></div>
                    <?php if ($show_branch): ?><div class="pcard__row"><dt><?= htmlspecialchars(t('branch_singular')) ?></dt><dd><?= htmlspecialchars($v['_branch']['name'] ?? '—') ?></dd></div><?php endif; ?>
                    <div class="pcard__row"><dt>Service</dt><dd><?= htmlspecialchars($v['service']) ?></dd></div>
                    <div class="pcard__row"><dt>Invited by</dt><dd><?= htmlspecialchars($v['invited_by']) ?></dd></div>
                    <div class="pcard__row"><dt>Assigned to</dt><dd><?= htmlspecialchars($v['assigned_to']) ?></dd></div>
                    <div class="pcard__row"><dt>Last contact</dt><dd><?= mu_ago($v['last_contact_days']) ?></dd></div>
                  </dl>
                  <div class="pcard__acts">
                    <button class="chip-btn" type="button" data-open-visitor="<?= (int) $v['id'] ?>">View</button>
                    <button class="chip-btn" type="button" data-open="modalFollowUp">Log Follow-Up</button>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="empty" data-empty hidden>
        <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-door-closed"></i></span>
        <h3>No visitors match those filters</h3>
        <p>Try a different search term, or clear the filters to see the whole pipeline again.</p>
        <button class="btn" type="button" data-reset-filters><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Clear filters</button>
      </div>
    </div>
  </section>

  <!-- ═══════════════════ OVERDUE + FUNNEL ═══════════════════ -->
  <div class="grid grid--2" style="margin-top:16px">
    <section class="panel">
      <header class="panel__head">
        <span class="stat-tile__icon tone-amber" style="width:32px;height:32px;font-size:13px" aria-hidden="true"><i class="fa-solid fa-hourglass-half"></i></span>
        <h2>Overdue Follow-Ups</h2>
        <span class="count-chip"><?= count($overdue) ?></span>
      </header>
      <div class="panel__body" style="padding:0">
        <?php if (!$overdue): ?>
          <div class="empty" style="padding:34px 16px">
            <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></span>
            <h3>Everyone has been contacted</h3>
            <p>No visitor has been waiting more than a week.</p>
          </div>
        <?php else: ?>
          <div class="clist" style="border:0;border-radius:0">
            <?php foreach (array_slice($overdue, 0, 6) as $v): ?>
              <div class="crow">
                <?= mu_av($v['name'], 'xs') ?>
                <span class="crow__name"><?= htmlspecialchars($v['name']) ?></span>
                <span class="crow__phone"><?= $v['last_contact_days'] === null ? 'Never contacted' : mu_ago($v['last_contact_days']) ?></span>
                <button class="chip-btn" type="button" data-open="modalFollowUp">Follow Up Now</button>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <section class="panel">
      <header class="panel__head">
        <span class="stat-tile__icon tone-purple" style="width:32px;height:32px;font-size:13px" aria-hidden="true"><i class="fa-solid fa-filter"></i></span>
        <h2>Conversion Funnel</h2>
      </header>
      <div class="panel__body">
        <div class="funnel">
          <?php
            $top = max(1, $stage_counts['New Visitor'] ?: 1);
            $totalIn = array_sum($stage_counts) ?: 1;
            foreach ($stages as $stage => $meta):
              $n = $stage_counts[$stage];
              $w = max(6, (int) round($n / max(1, max($stage_counts)) * 100));
              $pct = round($n / $totalIn * 100);
          ?>
            <div class="funnel__row">
              <span class="funnel__label"><?= htmlspecialchars($stage) ?></span>
              <span class="funnel__bar"><span class="funnel__fill" style="width:<?= $w ?>%;background:<?= $meta['tone'] ?>"><?= $n ?></span></span>
              <span class="funnel__pct"><?= $pct ?>%</span>
            </div>
          <?php endforeach; ?>
        </div>
        <p class="hint" style="margin-top:14px">
          <?= $stage_counts['Converted'] ?> of <?= $totalIn ?> visitors have become members
          (<?= $totalIn ? round($stage_counts['Converted'] / $totalIn * 100) : 0 ?>% conversion).
        </p>
      </div>
    </section>
  </div>

<?php endif; ?>
</div>

<?php if ($has_module): ?>

<!-- ═════════════════ VISITOR DETAIL DRAWER ═════════════════ -->
<div class="drawer-scrim" data-drawer-scrim hidden></div>
<aside class="drawer" id="vDrawer" role="dialog" aria-modal="true" aria-labelledby="vName" hidden>
  <header class="drawer__head">
    <span class="av av--lg av-c0" data-v-av aria-hidden="true">?</span>
    <div class="drawer__title">
      <h2 id="vName">Visitor</h2>
      <p><span class="pill is-brand" data-v-stage>New Visitor</span> &middot; <span data-v-phone>—</span></p>
    </div>
    <button class="iconbtn" type="button" data-drawer-close aria-label="Close panel"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
  </header>

  <div class="drawer__body">
    <dl class="deflist" style="margin-bottom:18px">
      <div><dt>Email</dt><dd data-v-email>—</dd></div>
      <div><dt>Suburb</dt><dd data-v-suburb>—</dd></div>
      <div><dt>Age group</dt><dd data-v-age>—</dd></div>
      <div><dt>First visit</dt><dd data-v-first>—</dd></div>
      <div><dt>Service attended</dt><dd data-v-service>—</dd></div>
      <div><dt>Invited by</dt><dd data-v-invited>—</dd></div>
      <div><dt>How they heard</dt><dd data-v-heard>—</dd></div>
      <div><dt>Assigned to</dt><dd data-v-assigned>—</dd></div>
    </dl>

    <p class="modal__group">Follow-up timeline</p>
    <div class="timeline" style="margin-top:10px">
      <?php
        $methodIcons = ['Call' => 'fa-phone', 'SMS' => 'fa-comment-sms', 'WhatsApp' => 'fa-whatsapp',
                        'Visit' => 'fa-house-user', 'Email' => 'fa-envelope'];
        foreach ($visitor_timeline_demo as $t):
      ?>
        <div class="tl-item">
          <div class="tl-item__head">
            <span class="tl-item__method">
              <i class="fa-solid <?= $methodIcons[$t['method']] ?? 'fa-circle' ?>" aria-hidden="true"></i>
              <?= htmlspecialchars($t['method']) ?>
            </span>
            <span class="tl-item__date"><?= mu_date($t['date']) ?></span>
          </div>
          <p class="tl-item__who"><?= htmlspecialchars($t['person']) ?> &middot; <?= htmlspecialchars($t['outcome']) ?></p>
          <p class="tl-item__notes"><?= htmlspecialchars($t['notes']) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <footer class="drawer__foot">
    <button class="btn btn--ghost" type="button" data-open="modalFollowUp"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i> Log Follow-Up</button>
    <?php if (mu_mod('communication')): ?>
      <button class="btn btn--ghost" type="button" data-toast="Message composer opened"><i class="fa-regular fa-comment" aria-hidden="true"></i> Message</button>
    <?php endif; ?>
    <?php if (mu_can('members.add')): ?>
      <button class="btn" type="button" data-open="modalConvert"><i class="fa-solid fa-user-check" aria-hidden="true"></i> Convert</button>
    <?php endif; ?>
  </footer>
</aside>

<!-- ═══════════════════ ADD / EDIT VISITOR ═══════════════════ -->
<?php if (mu_can('members.add') || mu_can('members.edit')): ?>
<div class="modal-scrim" id="modalVisitor" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="vModalTitle">
    <header class="modal__head">
      <h2 id="vModalTitle">Add Visitor</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>
    <div class="modal__body">
      <div class="form-grid">
        <div class="field"><label for="vFirst">Full name</label><input class="input" id="vFirst" placeholder="First and last name"></div>
        <div class="field">
          <label for="vPhone">Phone</label>
          <div class="phone-input"><span class="phone-input__prefix"><span aria-hidden="true">🇿🇼</span> +263</span><input class="input" id="vPhone" inputmode="tel" placeholder="77 123 4567"></div>
        </div>
        <div class="field"><label for="vEmail">Email</label><input class="input" type="email" id="vEmail" placeholder="name@example.com"></div>
        <div class="field">
          <label for="vSuburb">Suburb</label>
          <select class="select" id="vSuburb"><?php foreach ($suburbs_demo as $s): ?><option><?= htmlspecialchars($s) ?></option><?php endforeach; ?></select>
        </div>
        <div class="field col-2"><label for="vAddress">Address</label><input class="input" id="vAddress"></div>
        <div class="field">
          <label for="vAgeGroup">Age group</label>
          <select class="select" id="vAgeGroup"><option>Children (0-12)</option><option>Youth (13-24)</option><option selected>Adults (25-59)</option><option>Seniors (60+)</option></select>
        </div>
        <div class="field"><label for="vFirstVisit">First visit date</label><input class="input" type="date" id="vFirstVisit" value="<?= date('Y-m-d') ?>"></div>
        <div class="field">
          <label for="vService">Service attended</label>
          <select class="select" id="vService"><?php foreach ($services_demo as $s): ?><option><?= htmlspecialchars($s) ?></option><?php endforeach; ?></select>
        </div>
        <div class="field">
          <label for="vInvited">Invited by</label>
          <input class="input" id="vInvited" list="memberList" placeholder="Search members&hellip;">
        </div>
        <div class="field col-2">
          <label for="vHeard">How did they hear about the church?</label>
          <select class="select" id="vHeard">
            <option>Invited by a friend</option><option>Walked past</option><option>Social media</option>
            <option>Radio programme</option><option>Family member</option><option>Church outreach</option>
          </select>
        </div>
        <div class="field col-2">
          <label>Interests</label>
          <div class="seg" style="margin-top:4px">
            <?php foreach (['Bible study', 'Youth', 'Music', 'Outreach', 'Prayer'] as $i => $int): ?>
              <input type="checkbox" id="int<?= $i ?>"><label for="int<?= $i ?>"><?= $int ?></label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="field col-2">
          <label for="vAssign">Assigned follow-up person</label>
          <select class="select" id="vAssign">
            <?php foreach (array_unique(array_column($rows, 'assigned_to')) as $a): ?><option><?= htmlspecialchars($a) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field col-2"><label for="vNotes">Notes</label><textarea class="textarea" id="vNotes" rows="3"></textarea></div>
      </div>
      <datalist id="memberList">
        <?php foreach ($members_demo as $m): ?><option value="<?= htmlspecialchars($m['name']) ?>"></option><?php endforeach; ?>
      </datalist>
    </div>
    <footer class="modal__foot">
      <span class="modal__spacer"></span>
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <button class="btn" type="button" data-close data-toast="Visitor saved">Save Visitor</button>
    </footer>
  </div>
</div>
<?php endif; ?>

<!-- ═══════════════════════ LOG FOLLOW-UP ═══════════════════════ -->
<div class="modal-scrim" id="modalFollowUp" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="fuTitle">
    <header class="modal__head">
      <h2 id="fuTitle">Log Follow-Up</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>
    <div class="modal__body">
      <div class="field" style="margin-bottom:14px">
        <label>Method</label>
        <div class="radio-cards">
          <?php foreach ([['Call','fa-phone'],['SMS','fa-comment-sms'],['WhatsApp','fa-comment-dots'],['Visit','fa-house-user'],['Email','fa-envelope']] as $i => [$lab,$ic]): ?>
            <label class="rcard">
              <input type="radio" name="fuMethod" value="<?= $lab ?>"<?= $i === 0 ? ' checked' : '' ?>>
              <span class="rcard__box"><i class="fa-solid <?= $ic ?>" aria-hidden="true"></i><span class="rcard__label"><?= $lab ?></span></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="form-grid">
        <div class="field"><label for="fuDate">Date</label><input class="input" type="date" id="fuDate" value="<?= date('Y-m-d') ?>"></div>
        <div class="field">
          <label for="fuOutcome">Outcome</label>
          <select class="select" id="fuOutcome">
            <option>Spoke with them</option><option>No answer</option><option>Promised to come</option>
            <option>Requested a visit</option><option>Left a message</option><option>Not interested</option>
          </select>
        </div>
        <div class="field col-2"><label for="fuNotes">Notes</label><textarea class="textarea" id="fuNotes" rows="3" placeholder="What was said?"></textarea></div>
        <div class="field"><label for="fuNext">Next follow-up</label><input class="input" type="date" id="fuNext"></div>
        <div class="field">
          <label for="fuStage">Move to stage</label>
          <select class="select" id="fuStage">
            <option value="">Leave unchanged</option>
            <?php foreach (array_keys($stages) as $s): ?><option><?= htmlspecialchars($s) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>
    <footer class="modal__foot">
      <span class="modal__spacer"></span>
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <button class="btn" type="button" data-close data-toast="Follow-up logged">Save Follow-Up</button>
    </footer>
  </div>
</div>

<!-- ═══════════════════════ CONVERT TO MEMBER ═══════════════════════ -->
<?php if (mu_can('members.add')): ?>
<div class="modal-scrim" id="modalConvert" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="cvTitle">
    <header class="modal__head">
      <h2 id="cvTitle">Convert to Member</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>
    <div class="modal__body">
      <div class="err-summary is-on" style="align-items:center;border-color:#C6D7F9;border-left-color:var(--info);background:var(--info-bg);color:var(--info)">
        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
        <span>They will be added to the member directory and removed from the visitor pipeline. Their follow-up history is kept.</span>
      </div>

      <p class="modal__group">What carries over</p>
      <dl class="deflist" data-cv-summary>
        <div><dt>Name</dt><dd data-cv="name">—</dd></div>
        <div><dt>Phone</dt><dd data-cv="phone">—</dd></div>
        <div><dt>Email</dt><dd data-cv="email">—</dd></div>
        <div><dt>Suburb</dt><dd data-cv="suburb">—</dd></div>
        <div><dt>First visit</dt><dd data-cv="first_visit">—</dd></div>
        <div><dt>Invited by</dt><dd data-cv="invited_by">—</dd></div>
      </dl>

      <div class="form-grid" style="margin-top:16px">
        <div class="field">
          <label for="cvHousehold">Household (optional)</label>
          <select class="select" id="cvHousehold">
            <option value="">Not assigned</option>
            <?php foreach ($households_demo as $h): ?><option><?= htmlspecialchars($h['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <?php if (mu_mod('departments')): ?>
          <div class="field">
            <label for="cvDept">Department (optional)</label>
            <select class="select" id="cvDept">
              <option value="">Not assigned</option>
              <?php foreach ($departments_list as $d): ?><option><?= htmlspecialchars($d) ?></option><?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <footer class="modal__foot">
      <span class="modal__spacer"></span>
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <button class="btn" type="button" id="cvGo"><i class="fa-solid fa-user-check" aria-hidden="true"></i> Convert &amp; Open Form</button>
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

<script>
(function () {
  'use strict';
  var still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var panel = document.getElementById('listPanel');
  var VISITORS = <?= json_encode(array_column($rows, null, 'id'), JSON_UNESCAPED_UNICODE) ?>;

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

  if (!panel) { return; }                       /* module off: nothing else to wire */

  setTimeout(function () { panel.classList.add('is-loaded'); }, still ? 0 : 620);

  [].forEach.call(document.querySelectorAll('[data-count]'), function (el) {
    var target = parseFloat(el.getAttribute('data-count')) || 0;
    if (still) { el.textContent = target.toLocaleString(); return; }
    var start = performance.now();
    (function step(now) {
      var p = Math.min(1, (now - start) / 900), eased = 1 - Math.pow(1 - p, 3);
      el.textContent = Math.round(target * eased).toLocaleString();
      if (p < 1) { requestAnimationFrame(step); }
    })(start);
  });

  /* ── view switcher ── */
  var VIEW_KEY = 'mutendi-visitors-view';
  function setView(v) {
    [].forEach.call(document.querySelectorAll('[data-view]'), function (b) {
      b.setAttribute('aria-pressed', String(b.getAttribute('data-view') === v));
    });
    [].forEach.call(document.querySelectorAll('[data-view-panel]'), function (p) {
      p.hidden = p.getAttribute('data-view-panel') !== v;
    });
    try { sessionStorage.setItem(VIEW_KEY, v); } catch (e) {}
  }
  [].forEach.call(document.querySelectorAll('[data-view]'), function (b) {
    b.addEventListener('click', function () { setView(b.getAttribute('data-view')); });
  });
  try { var sv = sessionStorage.getItem(VIEW_KEY); if (sv) { setView(sv); } } catch (e) {}

  /* ── filtering ── */
  var search = document.querySelector('[data-search]'),
      clearBtn = document.querySelector('[data-search-clear]'),
      resultCount = document.querySelector('[data-result-count]'),
      emptyState = document.querySelector('[data-empty]'),
      chipsRow = document.querySelector('[data-filter-chips]'),
      activeBadge = document.querySelector('[data-active-filters]');

  function waitBand(d) {
    if (d < 7) return 'Under 7';
    if (d <= 14) return '7-14';
    return 'Over 14';
  }

  function apply() {
    var f = {};
    ['fStage','fAssigned','fService','fWait'<?= $branch_aware ? ",'fBranch'" : '' ?>].forEach(function (id) {
      var el = document.getElementById(id);
      if (el && el.value !== 'All') { f[id] = el.value; }
    });
    var q = search && search.value.trim() ? search.value.trim().toLowerCase() : '';
    if (q) { f.q = search.value.trim(); }

    var shown = 0;
    [].forEach.call(document.querySelectorAll('[data-row], [data-card]'), function (el) {
      var ok = true;
      if (q && (el.getAttribute('data-name') || '').indexOf(q) === -1) { ok = false; }
      if (ok && f.fStage && el.getAttribute('data-stage') !== f.fStage) { ok = false; }
      if (ok && f.fAssigned && el.getAttribute('data-assigned') !== f.fAssigned) { ok = false; }
      if (ok && f.fService && el.getAttribute('data-service') !== f.fService) { ok = false; }
      if (ok && f.fWait && waitBand(parseInt(el.getAttribute('data-wait'), 10)) !== f.fWait) { ok = false; }
<?php if ($branch_aware): ?>      if (ok && f.fBranch && el.getAttribute('data-branch') !== f.fBranch) { ok = false; }
<?php endif; ?>
      el.hidden = !ok;
      if (ok && el.hasAttribute('data-row')) { shown++; }
    });

    resultCount.textContent = shown;
    emptyState.hidden = shown !== 0;
    recount();

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
  if (search) { search.addEventListener('input', apply); }
  if (clearBtn) { clearBtn.addEventListener('click', function () { search.value = ''; apply(); search.focus(); }); }
  [].forEach.call(document.querySelectorAll('[data-filter]'), function (el) { el.addEventListener('change', apply); });
  [].forEach.call(document.querySelectorAll('[data-reset-filters]'), function (b) {
    b.addEventListener('click', function () {
      if (search) { search.value = ''; }
      [].forEach.call(document.querySelectorAll('[data-filter]'), function (el) { el.value = 'All'; });
      apply(); toast('Filters cleared', 'info');
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

  /* ── column counts follow the board ── */
  function recount() {
    [].forEach.call(document.querySelectorAll('.bcol'), function (col) {
      var n = [].slice.call(col.querySelectorAll('.vcard')).filter(function (c) { return !c.hidden; }).length;
      var out = col.querySelector('[data-stage-count]');
      if (out) { out.textContent = n; }
    });
  }

  /* ── drag and drop between columns (visual only) ── */
  var dragged = null;
  [].forEach.call(document.querySelectorAll('.vcard'), function (card) {
    card.addEventListener('dragstart', function (e) {
      dragged = card;
      card.classList.add('is-dragging');
      e.dataTransfer.effectAllowed = 'move';
      /* Firefox will not start a drag without data set. */
      e.dataTransfer.setData('text/plain', card.getAttribute('data-visitor'));
    });
    card.addEventListener('dragend', function () {
      card.classList.remove('is-dragging');
      dragged = null;
      [].forEach.call(document.querySelectorAll('.bcol'), function (c) { c.classList.remove('is-over'); });
    });
  });

  [].forEach.call(document.querySelectorAll('[data-dropzone]'), function (zone) {
    var col = zone.closest('.bcol');
    zone.addEventListener('dragover', function (e) { e.preventDefault(); col.classList.add('is-over'); });
    zone.addEventListener('dragleave', function () { col.classList.remove('is-over'); });
    zone.addEventListener('drop', function (e) {
      e.preventDefault();
      col.classList.remove('is-over');
      if (!dragged) { return; }
      zone.appendChild(dragged);
      var stage = col.getAttribute('data-stage');
      dragged.setAttribute('data-stage', stage);
      /* Keep the table row in step so both views agree. */
      var id = dragged.getAttribute('data-visitor');
      var row = document.querySelector('tr[data-visitor="' + id + '"]');
      if (row) {
        row.setAttribute('data-stage', stage);
        var badge = row.querySelector('.pill');
        if (badge) { badge.textContent = stage; }
      }
      recount();
      toast(dragged.querySelector('.vcard__name').textContent + ' moved to ' + stage);
    });
  });

  /* ── mobile card expand ── */
  [].forEach.call(document.querySelectorAll('[data-card-toggle]'), function (b) {
    b.addEventListener('click', function () { b.closest('.pcard').classList.toggle('is-open'); });
  });

  /* ── selection ── */
  var all = document.querySelector('[data-check-all]');
  if (all) {
    all.addEventListener('change', function () {
      [].forEach.call(document.querySelectorAll('[data-row-check]'), function (cb) {
        if (cb.closest('tr').hidden) { return; }
        cb.checked = all.checked;
        cb.closest('tr').classList.toggle('is-picked', all.checked);
      });
    });
  }
  [].forEach.call(document.querySelectorAll('[data-row-check]'), function (cb) {
    cb.addEventListener('change', function () { cb.closest('tr').classList.toggle('is-picked', cb.checked); });
  });

  /* ── drawer ── */
  var drawer = document.getElementById('vDrawer'), dScrim = document.querySelector('[data-drawer-scrim]');
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
  function initials(n) {
    var p = n.trim().split(/\s+/);
    return ((p[0] || '')[0] + (p.length > 1 ? p[p.length - 1][0] : '')).toUpperCase();
  }

  var lastVisitor = null;
  function openDrawer(id) {
    var v = VISITORS[id];
    if (!v) { return; }
    lastVisitor = v;
    drawer.querySelector('#vName').textContent = v.name;
    drawer.querySelector('[data-v-stage]').textContent = v.stage;
    drawer.querySelector('[data-v-phone]').textContent = v.phone;
    var av = drawer.querySelector('[data-v-av]');
    av.textContent = initials(v.name);
    av.className = 'av av--lg av-c' + (crc32(v.name) % 10);

    var map = { email: v.email, suburb: v.suburb, age: v.age_group, first: v.first_visit,
                service: v.service, invited: v.invited_by, heard: v.heard, assigned: v.assigned_to };
    Object.keys(map).forEach(function (k) {
      var el = drawer.querySelector('[data-v-' + k + ']');
      if (el) { el.textContent = map[k] || '—'; }
    });

    dScrim.hidden = false; drawer.hidden = false;
    document.body.style.overflow = 'hidden';
    drawer.querySelector('[data-drawer-close]').focus();
  }
  function closeDrawer() { drawer.hidden = true; dScrim.hidden = true; document.body.style.overflow = ''; }
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-open-visitor]');
    if (t) { e.preventDefault(); openDrawer(parseInt(t.getAttribute('data-open-visitor'), 10)); }
  });
  drawer.querySelector('[data-drawer-close]').addEventListener('click', closeDrawer);
  dScrim.addEventListener('click', closeDrawer);

  /* ── modals ── */
  document.addEventListener('click', function (e) {
    var open = e.target.closest('[data-open]');
    if (open) {
      e.preventDefault();
      var m = document.getElementById(open.getAttribute('data-open'));
      if (!m) { return; }
      /* The convert modal shows what will carry over. */
      if (open.getAttribute('data-open') === 'modalConvert') {
        var v = lastVisitor || VISITORS[Object.keys(VISITORS)[0]];
        if (v) {
          ['name','phone','email','suburb','first_visit','invited_by'].forEach(function (k) {
            var slot = m.querySelector('[data-cv="' + k + '"]');
            if (slot) { slot.textContent = v[k] || '—'; }
          });
        }
      }
      m.hidden = false; document.body.style.overflow = 'hidden';
      return;
    }
    var close = e.target.closest('[data-close]');
    if (close) { e.preventDefault(); close.closest('.modal-scrim').hidden = true; document.body.style.overflow = ''; return; }
    if (e.target.classList.contains('modal-scrim')) { e.target.hidden = true; document.body.style.overflow = ''; }
  });

  var cvGo = document.getElementById('cvGo');
  if (cvGo) {
    cvGo.addEventListener('click', function () {
      cvGo.closest('.modal-scrim').hidden = true;
      document.body.style.overflow = '';
      toast('Opening the member form, pre-filled…', 'info');
      setTimeout(function () { window.location.href = '<?= $base_url ?>members/add.php'; }, 900);
    });
  }

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

  apply();
})();
</script>

<?php require __DIR__ . '/../components/footer.php'; ?>

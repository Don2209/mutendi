<?php
/**
 * Mutendi CMS — Families & Households.
 *
 * Groups members into households for pastoral care and family records.
 * UI only: filters, selection and forms are visual.
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
    function mu_date(string $iso, string $fmt = 'd M Y'): string { return date($fmt, strtotime($iso)); }
}

/** A stacked avatar cluster with a "+n" overflow chip. */
function mu_cluster(array $members, int $max = 4, string $size = 'sm'): string {
    $out = '<span class="av-stack">';
    foreach (array_slice($members, 0, $max) as $m) {
        $out .= mu_av($m['name'], $size);
    }
    $extra = count($members) - $max;
    if ($extra > 0) { $out .= '<span class="av-stack__more">+' . $extra . '</span>'; }
    return $out . '</span>';
}

$rows  = $households_demo;

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

$stats = $people_stats['households'];

/* Scope the households and the headline figures to the branch in view. */
if ($branch_aware) {
    foreach ($rows as $i => $h) { $rows[$i]['_branch'] = mu_branch_for($h['name'] . $h['id']); }
    if (!$viewing_all) {
        $rows = array_values(array_filter($rows, function ($h) use ($current_branch) {
            return $h['_branch'] && (int) $h['_branch']['id'] === (int) $current_branch;
        }));
    }
    foreach ($stats as $k => $v) { $stats[$k] = $k === 'avg_size' ? $v : (int) round(mu_branch_share($v)); }
}

/* Members with no household yet — the actionable list at the foot of the page.
   LATER: SELECT * FROM members WHERE household_id IS NULL AND church_id = :church_id; */
$unassigned = array_slice($members_demo, 0, 6);

$page_title = 'Families & Households';
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
        <span aria-current="page">Families / Households</span>
      </nav>
      <div class="title-row">
        <h1 class="page__title">Families &amp; Households</h1>
        <span class="count-chip" data-count="<?= $stats['total'] ?>">0</span>
      </div>
      <p class="page__sub">Group members into households for pastoral care and family records.</p>
    </div>
    <div class="page__actions">
      <?php if (mu_can('members.add')): ?>
        <button class="btn" type="button" data-open="modalHousehold"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add Household</button>
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
  </header>

  <div class="stat-strip">
    <?php foreach ([
      ['Total Households',       $stats['total'],         'fa-house-chimney', 'blue'],
      ['Members in Households',  $stats['in_households'], 'fa-people-roof',   'green'],
      ['Unassigned Members',     $stats['unassigned'],    'fa-user-slash',    'amber'],
      ['Average Household Size', $stats['avg_size'],      'fa-chart-simple',  'purple'],
    ] as [$label, $value, $icon, $tone]): ?>
      <div class="stat-tile" style="cursor:default">
        <span class="stat-tile__icon tone-<?= $tone ?>" aria-hidden="true"><i class="fa-solid <?= $icon ?>"></i></span>
        <span class="stat-tile__body">
          <span class="stat-tile__value" data-count="<?= $value ?>"<?= is_float($value) ? ' data-decimal="1"' : '' ?>>0</span>
          <span class="stat-tile__label"><?= $label ?></span>
        </span>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="toolbar">
    <div class="viewswitch" role="group" aria-label="View">
      <button type="button" data-view="cards" aria-pressed="true"  aria-label="Card grid view"><i class="fa-solid fa-table-cells-large" aria-hidden="true"></i></button>
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
          <input class="input" type="search" id="fSearch" data-search placeholder="Search by household or member name&hellip;">
          <button class="search-field__clear" type="button" data-search-clear hidden aria-label="Clear search"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
      </div>
      <div class="field">
        <label for="fSize">Household size</label>
        <select class="select" id="fSize" data-filter><option>All</option><option>1</option><option>2-3</option><option>4-5</option><option>6+</option></select>
      </div>
      <div class="field">
        <label for="fSuburb">Suburb / Area</label>
        <select class="select" id="fSuburb" data-filter>
          <option>All</option>
          <?php foreach ($suburbs_demo as $s): ?><option><?= htmlspecialchars($s) ?></option><?php endforeach; ?>
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

      <?php if (mu_mod('cell_groups')): ?>
        <div class="field">
          <label for="fCell">Cell group</label>
          <select class="select" id="fCell" data-filter>
            <option>All</option>
            <?php foreach ($cells_list as $c): ?><option><?= htmlspecialchars($c) ?></option><?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
      <div class="field">
        <label for="fKids">Has children</label>
        <select class="select" id="fKids" data-filter><option>All</option><option>Yes</option><option>No</option></select>
      </div>
      <div class="filters__actions">
        <button class="btn" type="button" data-toast="Filters applied"><i class="fa-solid fa-check" aria-hidden="true"></i> Apply</button>
        <button class="btn btn--ghost" type="button" data-reset-filters><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Reset</button>
      </div>
    </div>
    <div class="chips-row" data-filter-chips hidden></div>
  </section>

  <section class="panel" id="listPanel" aria-live="polite">
    <div data-skeleton style="padding:16px">
      <div class="cardgrid cardgrid--3">
        <?php for ($i = 0; $i < 6; $i++): ?>
          <div class="sk-card">
            <span class="sk sk--text" style="width:55%;display:block"></span>
            <span class="sk sk--line" style="width:35%"></span>
            <div style="display:flex;gap:8px;margin-top:16px"><span class="sk sk--av"></span><span class="sk sk--av"></span><span class="sk sk--av"></span></div>
            <span class="sk sk--line" style="width:70%"></span>
          </div>
        <?php endfor; ?>
      </div>
    </div>

    <div data-content>

      <!-- ─────────────── CARD GRID ─────────────── -->
      <div data-view-panel="cards" style="padding:16px">
        <div class="cardgrid cardgrid--3 stagger">
          <?php foreach ($rows as $h): ?>
            <article class="gcard" data-card
                     data-name="<?= htmlspecialchars(mb_strtolower($h['name'] . ' ' . $h['head'])) ?>"
                     data-size="<?= count($h['members']) ?>"
                     data-suburb="<?= htmlspecialchars($h['suburb']) ?>"
                     data-cell="<?= htmlspecialchars($h['cell_group']) ?>"
                     data-kids="<?= $h['children'] > 0 ? 'Yes' : 'No' ?>"
                     <?= $branch_aware ? 'data-branch="' . htmlspecialchars($h['_branch']['name'] ?? '') . '"' : '' ?>>
              <h3 style="color:var(--ink);font-size:15px;font-weight:800;letter-spacing:-.02em"><?= htmlspecialchars($h['name']) ?></h3>
              <p style="margin-top:3px;color:var(--muted);font-size:12px">Head: <strong style="color:var(--ink-2)"><?= htmlspecialchars($h['head']) ?></strong></p>

              <div style="margin-top:14px"><?= mu_cluster($h['members'], 5) ?></div>

              <p style="margin-top:12px;display:flex;gap:14px;color:var(--muted);font-size:12px;font-weight:600">
                <span><i class="fa-solid fa-user" style="color:var(--brand-300)" aria-hidden="true"></i> <?= (int) $h['adults'] ?> adults</span>
                <span><i class="fa-solid fa-child" style="color:var(--brand-300)" aria-hidden="true"></i> <?= (int) $h['children'] ?> children</span>
              </p>

              <p style="margin-top:10px;color:var(--muted);font-size:12px">
                <i class="fa-solid fa-location-dot" style="color:var(--brand-300)" aria-hidden="true"></i>
                <?= htmlspecialchars($h['address']) ?>, <?= htmlspecialchars($h['suburb']) ?>
              </p>

              <?php if ($show_branch): ?><p style="margin-top:10px"><?= mu_branch_chip($h['_branch'] ?? null) ?></p><?php endif; ?>
              <?php if (mu_mod('cell_groups')): ?>
                <p style="margin-top:10px"><span class="pill is-brand"><?= htmlspecialchars($h['cell_group']) ?></span></p>
              <?php endif; ?>

              <div style="display:flex;gap:14px;margin-top:14px;padding-top:12px;border-top:1px solid var(--line)">
                <button class="chip-btn" type="button" style="border:0;padding:0;color:var(--brand-600)" data-open-household="<?= (int) $h['id'] ?>">View</button>
                <?php if (mu_can('members.edit')): ?>
                  <button class="chip-btn" type="button" style="border:0;padding:0;color:var(--brand-600)" data-open="modalHousehold">Edit</button>
                <?php endif; ?>
                <?php if (mu_can('members.add')): ?>
                  <button class="chip-btn" type="button" style="border:0;padding:0;color:var(--brand-600)" data-open="modalAssign">Add Member</button>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- ─────────────── TABLE ─────────────── -->
      <div data-view-panel="table" hidden>
        <div class="dt-wrap">
          <table class="dt">
            <thead>
              <tr>
                <th style="width:38px"><input class="check" type="checkbox" data-check-all aria-label="Select all households"></th>
                <th style="width:44px">#</th>
                <th>Household</th>
                <th>Head of Household</th>
                <th>Members</th>
                <th>Adults / Children</th>
                <th>Address</th>
                <?php if ($show_branch): ?><th><?= htmlspecialchars(t('branch_singular')) ?></th><?php endif; ?>
                <?php if (mu_mod('cell_groups')): ?><th>Cell Group</th><?php endif; ?>
                <th>Created</th>
                <th style="text-align:right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $i => $h): ?>
                <tr data-row
                    data-name="<?= htmlspecialchars(mb_strtolower($h['name'] . ' ' . $h['head'])) ?>"
                    data-size="<?= count($h['members']) ?>"
                    data-suburb="<?= htmlspecialchars($h['suburb']) ?>"
                    data-cell="<?= htmlspecialchars($h['cell_group']) ?>"
                    data-kids="<?= $h['children'] > 0 ? 'Yes' : 'No' ?>"
                    <?= $branch_aware ? 'data-branch="' . htmlspecialchars($h['_branch']['name'] ?? '') . '"' : '' ?>>
                  <td><input class="check" type="checkbox" data-row-check aria-label="Select <?= htmlspecialchars($h['name']) ?>"></td>
                  <td class="num"><?= $i + 1 ?></td>
                  <td>
                    <button class="person" type="button" data-open-household="<?= (int) $h['id'] ?>">
                      <?= mu_cluster($h['members'], 3, 'xs') ?>
                      <span class="person__text"><span class="person__name"><?= htmlspecialchars($h['name']) ?></span></span>
                    </button>
                  </td>
                  <td>
                    <span class="person"><?= mu_av($h['head'], 'sm') ?>
                      <span class="person__text"><span class="person__name"><?= htmlspecialchars($h['head']) ?></span>
                      <span class="tsub"><?= htmlspecialchars($h['head_phone']) ?></span></span>
                    </span>
                  </td>
                  <td class="nowrap"><strong><?= count($h['members']) ?></strong></td>
                  <td class="nowrap"><?= (int) $h['adults'] ?> / <?= (int) $h['children'] ?></td>
                  <td class="nowrap"><?= htmlspecialchars($h['suburb']) ?><span class="tsub"><?= htmlspecialchars($h['city']) ?></span></td>
                  <?php if ($show_branch): ?><td class="nowrap"><?= mu_branch_chip($h['_branch'] ?? null) ?></td><?php endif; ?>
                  <?php if (mu_mod('cell_groups')): ?><td class="nowrap"><?= htmlspecialchars($h['cell_group']) ?></td><?php endif; ?>
                  <td class="nowrap"><?= mu_date($h['created']) ?></td>
                  <td>
                    <div class="rowacts">
                      <button class="iconbtn iconbtn--sm" type="button" data-open-household="<?= (int) $h['id'] ?>" aria-label="View <?= htmlspecialchars($h['name']) ?>"><i class="fa-regular fa-eye" aria-hidden="true"></i></button>
                      <?php if (mu_can('members.edit')): ?><button class="iconbtn iconbtn--sm" type="button" data-open="modalHousehold" aria-label="Edit <?= htmlspecialchars($h['name']) ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i></button><?php endif; ?>
                      <?php if (mu_can('members.add')): ?><button class="iconbtn iconbtn--sm" type="button" data-open="modalAssign" aria-label="Add member to <?= htmlspecialchars($h['name']) ?>"><i class="fa-solid fa-user-plus" aria-hidden="true"></i></button><?php endif; ?>
                      <div class="drop" data-menu>
                        <button class="iconbtn iconbtn--sm" type="button" aria-haspopup="true" aria-expanded="false" data-menu-btn aria-label="More actions"><i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i></button>
                        <div class="menu menu--sm" data-menu-panel hidden>
                          <a class="menu__item" href="#" data-open-household="<?= (int) $h['id'] ?>"><i class="fa-regular fa-eye" aria-hidden="true"></i> View</a>
                          <?php if (mu_can('members.edit')): ?><a class="menu__item" href="#" data-open="modalHousehold"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</a><?php endif; ?>
                          <?php if (mu_mod('communication')): ?><a class="menu__item" href="#" data-toast="Message composer opened"><i class="fa-regular fa-comment" aria-hidden="true"></i> Message Household</a><?php endif; ?>
                          <?php if (mu_can('members.delete')): ?>
                            <div class="menu__sep" role="separator"></div>
                            <a class="menu__item menu__item--danger" href="#" data-toast="Household deleted"><i class="fa-solid fa-trash" aria-hidden="true"></i> Delete</a>
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
          <?php foreach ($rows as $h): ?>
            <article class="pcard" data-card
                     data-name="<?= htmlspecialchars(mb_strtolower($h['name'] . ' ' . $h['head'])) ?>"
                     data-size="<?= count($h['members']) ?>"
                     data-suburb="<?= htmlspecialchars($h['suburb']) ?>"
                     data-cell="<?= htmlspecialchars($h['cell_group']) ?>"
                     data-kids="<?= $h['children'] > 0 ? 'Yes' : 'No' ?>"
                     <?= $branch_aware ? 'data-branch="' . htmlspecialchars($h['_branch']['name'] ?? '') . '"' : '' ?>>
              <button class="pcard__main" type="button" data-card-toggle>
                <?= mu_av($h['head'], 'md') ?>
                <span class="pcard__text">
                  <span class="pcard__name"><?= htmlspecialchars($h['name']) ?></span>
                  <span class="pcard__meta"><?= count($h['members']) ?> members &middot; <?= htmlspecialchars($h['suburb']) ?></span>
                </span>
                <i class="fa-solid fa-chevron-down pcard__chev" aria-hidden="true"></i>
              </button>
              <div class="pcard__more">
                <dl>
                  <div class="pcard__row"><dt>Head</dt><dd><?= htmlspecialchars($h['head']) ?></dd></div>
                  <div class="pcard__row"><dt>Phone</dt><dd><?= htmlspecialchars($h['head_phone']) ?></dd></div>
                  <div class="pcard__row"><dt>Adults / Children</dt><dd><?= (int) $h['adults'] ?> / <?= (int) $h['children'] ?></dd></div>
                  <?php if ($show_branch): ?><div class="pcard__row"><dt><?= htmlspecialchars(t('branch_singular')) ?></dt><dd><?= htmlspecialchars($h['_branch']['name'] ?? '—') ?></dd></div><?php endif; ?>
                  <div class="pcard__row"><dt>Address</dt><dd><?= htmlspecialchars($h['address']) ?></dd></div>
                  <?php if (mu_mod('cell_groups')): ?><div class="pcard__row"><dt>Cell group</dt><dd><?= htmlspecialchars($h['cell_group']) ?></dd></div><?php endif; ?>
                </dl>
                <div class="pcard__acts">
                  <button class="chip-btn" type="button" data-open-household="<?= (int) $h['id'] ?>">View</button>
                  <?php if (mu_can('members.add')): ?><button class="chip-btn" type="button" data-open="modalAssign">Add Member</button><?php endif; ?>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="empty" data-empty hidden>
        <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-house-circle-xmark"></i></span>
        <h3>No households match those filters</h3>
        <p>Try a different search term, or clear the filters to see every household again.</p>
        <button class="btn" type="button" data-reset-filters><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Clear filters</button>
      </div>

      <div class="pager">
        <span>Showing <strong>1</strong> to <strong><?= count($rows) ?></strong> of <strong><?= number_format($stats['total']) ?></strong> households</span>
        <div class="pager__pages">
          <button type="button" disabled aria-label="Previous page"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
          <button type="button" aria-current="page">1</button><button type="button">2</button><button type="button">3</button>
          <span style="padding:0 4px">&hellip;</span><button type="button">26</button>
          <button type="button" aria-label="Next page"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
        </div>
      </div>
    </div>
  </section>

  <!-- ═══════════════════ UNASSIGNED MEMBERS ═══════════════════ -->
  <section class="panel" style="margin-top:16px">
    <header class="panel__head">
      <span class="stat-tile__icon tone-amber" style="width:32px;height:32px;font-size:13px" aria-hidden="true"><i class="fa-solid fa-user-slash"></i></span>
      <h2>Unassigned Members</h2>
      <span class="count-chip"><?= $stats['unassigned'] ?></span>
      <?php if (mu_can('members.edit')): ?>
        <button class="btn btn--ghost" type="button" id="bulkHousehold" disabled>
          <i class="fa-solid fa-house-circle-check" aria-hidden="true"></i> Create household from selected
        </button>
      <?php endif; ?>
    </header>
    <div class="panel__body" style="padding:0">
      <div class="clist" style="border:0;border-radius:0">
        <?php foreach ($unassigned as $m): ?>
          <div class="crow">
            <input class="check" type="checkbox" data-unassigned aria-label="Select <?= htmlspecialchars($m['name']) ?>">
            <?= mu_av($m['name'], 'xs') ?>
            <span class="crow__name"><?= htmlspecialchars($m['name']) ?></span>
            <span class="crow__phone"><?= htmlspecialchars($m['suburb']) ?></span>
            <?php if (mu_can('members.edit')): ?>
              <button class="chip-btn" type="button" data-open="modalAssign">Assign</button>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
</div>

<!-- ═════════════════ HOUSEHOLD DETAIL DRAWER ═════════════════ -->
<div class="drawer-scrim" data-drawer-scrim hidden></div>
<aside class="drawer" id="hhDrawer" role="dialog" aria-modal="true" aria-labelledby="hhName" hidden>
  <header class="drawer__head">
    <span class="stat-tile__icon tone-purple" style="width:48px;height:48px;font-size:18px" aria-hidden="true"><i class="fa-solid fa-house-chimney"></i></span>
    <div class="drawer__title">
      <h2 id="hhName">Household</h2>
      <p data-hh-address>—</p>
    </div>
    <button class="iconbtn" type="button" data-drawer-close aria-label="Close panel"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
  </header>

  <div class="drawer__body">
    <div class="tabs" role="tablist">
      <button role="tab" aria-selected="true"  data-tab="members">Members</button>
      <button role="tab" aria-selected="false" data-tab="contact">Contact</button>
      <button role="tab" aria-selected="false" data-tab="notes">Notes</button>
    </div>

    <!-- Family tree: head (with spouse beside) above, children beneath. -->
    <div class="tabpanel" data-panel="members">
      <div class="org" style="padding:6px 0">
        <div class="org__inner" style="width:100%">
          <div class="org__root" data-hh-heads style="display:flex;gap:14px"></div>
          <div class="org__row" data-hh-kids></div>
        </div>
      </div>
      <div data-hh-others style="margin-top:16px"></div>
    </div>

    <div class="tabpanel" data-panel="contact" hidden>
      <dl class="deflist">
        <div><dt>Head of household</dt><dd data-hh-head>—</dd></div>
        <div><dt>Phone</dt><dd data-hh-phone>—</dd></div>
        <div><dt>Address</dt><dd data-hh-addr2>—</dd></div>
        <div><dt>Suburb</dt><dd data-hh-suburb>—</dd></div>
        <?php if (mu_mod('cell_groups')): ?><div><dt>Cell group</dt><dd data-hh-cell>—</dd></div><?php endif; ?>
      </dl>
    </div>

    <div class="tabpanel" data-panel="notes" hidden>
      <div class="field">
        <label for="hhNotes">Pastoral notes</label>
        <textarea class="textarea" id="hhNotes" rows="6" placeholder="Visits, prayer requests, anything the team should know&hellip;"></textarea>
      </div>
      <button class="btn btn--ghost" type="button" style="margin-top:10px" data-toast="Notes saved"><i class="fa-regular fa-floppy-disk" aria-hidden="true"></i> Save notes</button>
    </div>
  </div>

  <footer class="drawer__foot">
    <?php if (mu_can('members.edit')): ?><button class="btn btn--ghost" type="button" data-open="modalHousehold"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</button><?php endif; ?>
    <?php if (mu_can('members.add')): ?><button class="btn btn--ghost" type="button" data-open="modalAssign"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Add Member</button><?php endif; ?>
    <?php if (mu_mod('communication')): ?><button class="btn" type="button" data-toast="Message composer opened"><i class="fa-regular fa-comment" aria-hidden="true"></i> Message</button><?php endif; ?>
  </footer>
</aside>

<!-- ═══════════════════ ADD / EDIT HOUSEHOLD ═══════════════════ -->
<?php if (mu_can('members.add') || mu_can('members.edit')): ?>
<div class="modal-scrim" id="modalHousehold" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="hhModalTitle">
    <header class="modal__head">
      <h2 id="hhModalTitle">Add Household</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>
    <div class="modal__body">
      <div class="form-grid">
        <div class="field col-2">
          <label for="hhTitle">Household name</label>
          <input class="input" id="hhTitle" placeholder="e.g. The Moyo Family">
        </div>
        <div class="field col-2">
          <label for="hhHead">Head of household</label>
          <div class="search-field">
            <i class="fa-solid fa-magnifying-glass search-field__icon" aria-hidden="true"></i>
            <input class="input" id="hhHead" list="memberList" placeholder="Search members&hellip;">
          </div>
        </div>
        <div class="field col-2"><label for="hhStreet">Address</label><input class="input" id="hhStreet" placeholder="House number and street"></div>
        <div class="field">
          <label for="hhSub">Suburb</label>
          <select class="select" id="hhSub"><?php foreach ($suburbs_demo as $s): ?><option><?= htmlspecialchars($s) ?></option><?php endforeach; ?></select>
        </div>
        <div class="field"><label for="hhCity">City</label><input class="input" id="hhCity" value="Harare"></div>
        <?php if (mu_mod('cell_groups')): ?>
          <div class="field col-2">
            <label for="hhCellSel">Cell group</label>
            <select class="select" id="hhCellSel"><option value="">Not assigned</option><?php foreach ($cells_list as $c): ?><option><?= htmlspecialchars($c) ?></option><?php endforeach; ?></select>
          </div>
        <?php endif; ?>
      </div>

      <p class="modal__group" style="margin-top:18px">Members</p>
      <div id="hhMembers" style="display:flex;flex-direction:column;gap:8px"></div>
      <button class="btn btn--ghost" type="button" id="hhAddRow" style="margin-top:10px">
        <i class="fa-solid fa-plus" aria-hidden="true"></i> Add another member
      </button>
      <datalist id="memberList">
        <?php foreach ($members_demo as $m): ?><option value="<?= htmlspecialchars($m['name']) ?>"></option><?php endforeach; ?>
      </datalist>
    </div>
    <footer class="modal__foot">
      <span class="modal__spacer"></span>
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <button class="btn" type="button" data-close data-toast="Household saved">Save Household</button>
    </footer>
  </div>
</div>
<?php endif; ?>

<!-- ═══════════════════ ASSIGN TO HOUSEHOLD ═══════════════════ -->
<?php if (mu_can('members.edit')): ?>
<div class="modal-scrim" id="modalAssign" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="asTitle">
    <header class="modal__head">
      <h2 id="asTitle">Assign to Household</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>
    <div class="modal__body">
      <div class="search-field" style="margin-bottom:12px">
        <i class="fa-solid fa-magnifying-glass search-field__icon" aria-hidden="true"></i>
        <input class="input" type="search" id="asSearch" placeholder="Search households&hellip;">
      </div>
      <div class="clist" style="max-height:260px;overflow-y:auto">
        <?php foreach ($rows as $h): ?>
          <label class="crow" style="cursor:pointer" data-assign-row data-name="<?= htmlspecialchars(mb_strtolower($h['name'])) ?>">
            <input class="check" type="radio" name="asPick">
            <span class="crow__name"><?= htmlspecialchars($h['name']) ?></span>
            <span class="crow__phone"><?= count($h['members']) ?> members &middot; <?= htmlspecialchars($h['suburb']) ?></span>
          </label>
        <?php endforeach; ?>
        <label class="crow" style="cursor:pointer;background:var(--brand-50)">
          <input class="check" type="radio" name="asPick">
          <span class="crow__name" style="color:var(--brand-600)"><i class="fa-solid fa-plus" aria-hidden="true"></i> Create a new household</span>
        </label>
      </div>
      <div class="field" style="margin-top:14px">
        <label for="asRel">Relationship to head</label>
        <select class="select" id="asRel"><option>Head</option><option>Spouse</option><option>Child</option><option>Relative</option><option>Other</option></select>
      </div>
    </div>
    <footer class="modal__foot">
      <span class="modal__spacer"></span>
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <button class="btn" type="button" data-close data-toast="Member assigned to household">Assign</button>
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
  var HOUSEHOLDS = <?= json_encode(array_column($households_demo, null, 'id'), JSON_UNESCAPED_UNICODE) ?>;

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

  /* ── skeleton + counters ── */
  var panel = document.getElementById('listPanel');
  setTimeout(function () { panel.classList.add('is-loaded'); }, still ? 0 : 620);

  [].forEach.call(document.querySelectorAll('[data-count]'), function (el) {
    var target = parseFloat(el.getAttribute('data-count')) || 0;
    var dec = el.hasAttribute('data-decimal') ? 1 : 0;
    if (still) { el.textContent = target.toFixed(dec); return; }
    var start = performance.now();
    (function step(now) {
      var p = Math.min(1, (now - start) / 900), eased = 1 - Math.pow(1 - p, 3);
      var v = target * eased;
      el.textContent = dec ? v.toFixed(dec) : Math.round(v).toLocaleString();
      if (p < 1) { requestAnimationFrame(step); }
    })(start);
  });

  /* ── view switcher ── */
  var VIEW_KEY = 'mutendi-households-view';
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

  function sizeBand(n) {
    if (n === 1) return '1';
    if (n <= 3) return '2-3';
    if (n <= 5) return '4-5';
    return '6+';
  }

  function apply() {
    var f = {};
    ['fSize','fSuburb','fCell','fKids'<?= $branch_aware ? ",'fBranch'" : '' ?>].forEach(function (id) {
      var el = document.getElementById(id);
      if (el && el.value !== 'All') { f[id] = el.value; }
    });
    var q = search && search.value.trim() ? search.value.trim().toLowerCase() : '';
    if (q) { f.q = search.value.trim(); }

    var shown = 0;
    [].forEach.call(document.querySelectorAll('[data-row], [data-card]'), function (el) {
      var ok = true;
      if (q && (el.getAttribute('data-name') || '').indexOf(q) === -1) { ok = false; }
      if (ok && f.fSize && sizeBand(parseInt(el.getAttribute('data-size'), 10)) !== f.fSize) { ok = false; }
      if (ok && f.fSuburb && el.getAttribute('data-suburb') !== f.fSuburb) { ok = false; }
      if (ok && f.fCell && el.getAttribute('data-cell') !== f.fCell) { ok = false; }
      if (ok && f.fKids && el.getAttribute('data-kids') !== f.fKids) { ok = false; }
<?php if ($branch_aware): ?>      if (ok && f.fBranch && el.getAttribute('data-branch') !== f.fBranch) { ok = false; }
<?php endif; ?>
      el.hidden = !ok;
      if (ok && el.hasAttribute('data-row')) { shown++; }
    });

    if (resultCount) { resultCount.textContent = shown; }
    if (emptyState) { emptyState.hidden = shown !== 0; }

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

  /* ── mobile card expand ── */
  [].forEach.call(document.querySelectorAll('[data-card-toggle]'), function (b) {
    b.addEventListener('click', function () { b.closest('.pcard').classList.toggle('is-open'); });
  });

  /* ── unassigned selection ── */
  var bulkBtn = document.getElementById('bulkHousehold');
  [].forEach.call(document.querySelectorAll('[data-unassigned]'), function (cb) {
    cb.addEventListener('change', function () {
      if (bulkBtn) { bulkBtn.disabled = document.querySelectorAll('[data-unassigned]:checked').length === 0; }
    });
  });
  if (bulkBtn) { bulkBtn.addEventListener('click', function () { toast('New household created from selection'); }); }

  /* ── household drawer with family tree ── */
  var drawer = document.getElementById('hhDrawer'), dScrim = document.querySelector('[data-drawer-scrim]');

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
  function personCard(m) {
    return '<div class="onode"><span class="av av--md av-c' + (crc32(m.name) % 10) + '" aria-hidden="true">' +
      initials(m.name) + '</span><span class="onode__name">' + m.name +
      '</span><span class="onode__count">' + m.rel + ' &middot; ' + m.age + '</span></div>';
  }

  function openDrawer(id) {
    var h = HOUSEHOLDS[id];
    if (!h) { return; }
    drawer.querySelector('#hhName').textContent = h.name;
    drawer.querySelector('[data-hh-address]').textContent = h.address + ', ' + h.suburb;

    var heads = h.members.filter(function (m) { return m.rel === 'Head' || m.rel === 'Spouse'; });
    var kids  = h.members.filter(function (m) { return m.rel === 'Child'; });
    var other = h.members.filter(function (m) { return m.rel !== 'Head' && m.rel !== 'Spouse' && m.rel !== 'Child'; });

    drawer.querySelector('[data-hh-heads]').innerHTML = heads.map(personCard).join('');
    drawer.querySelector('[data-hh-kids]').innerHTML =
      kids.map(function (m) { return '<div class="org__node">' + personCard(m) + '</div>'; }).join('');
    drawer.querySelector('[data-hh-others]').innerHTML = other.length
      ? '<p class="modal__group">Other members</p><div style="display:flex;gap:10px;flex-wrap:wrap">' + other.map(personCard).join('') + '</div>'
      : '';

    drawer.querySelector('[data-hh-head]').textContent = h.head;
    drawer.querySelector('[data-hh-phone]').textContent = h.head_phone;
    drawer.querySelector('[data-hh-addr2]').textContent = h.address;
    drawer.querySelector('[data-hh-suburb]').textContent = h.suburb;
    var cell = drawer.querySelector('[data-hh-cell]');
    if (cell) { cell.textContent = h.cell_group; }

    dScrim.hidden = false; drawer.hidden = false;
    document.body.style.overflow = 'hidden';
    drawer.querySelector('[data-drawer-close]').focus();
  }
  function closeDrawer() { drawer.hidden = true; dScrim.hidden = true; document.body.style.overflow = ''; }

  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-open-household]');
    if (t) { e.preventDefault(); openDrawer(parseInt(t.getAttribute('data-open-household'), 10)); }
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
      if (m) { m.hidden = false; document.body.style.overflow = 'hidden'; }
      return;
    }
    var close = e.target.closest('[data-close]');
    if (close) { e.preventDefault(); close.closest('.modal-scrim').hidden = true; document.body.style.overflow = ''; return; }
    if (e.target.classList.contains('modal-scrim')) { e.target.hidden = true; document.body.style.overflow = ''; }
  });

  /* member rows inside the household modal */
  var rowsBox = document.getElementById('hhMembers'), addRow = document.getElementById('hhAddRow');
  function memberRow() {
    var d = document.createElement('div');
    d.style.cssText = 'display:flex;gap:8px';
    d.innerHTML = '<input class="input" list="memberList" placeholder="Search members…" style="flex:1">' +
      '<select class="select" style="width:130px"><option>Head</option><option>Spouse</option><option>Child</option><option>Relative</option></select>' +
      '<button class="iconbtn" type="button" aria-label="Remove"><i class="fa-solid fa-xmark"></i></button>';
    d.querySelector('button').addEventListener('click', function () { d.remove(); });
    return d;
  }
  if (addRow) {
    addRow.addEventListener('click', function () { rowsBox.appendChild(memberRow()); });
    rowsBox.appendChild(memberRow());
  }

  /* assign-modal search */
  var asSearch = document.getElementById('asSearch');
  if (asSearch) {
    asSearch.addEventListener('input', function () {
      var q = asSearch.value.trim().toLowerCase();
      [].forEach.call(document.querySelectorAll('[data-assign-row]'), function (r) {
        r.hidden = q !== '' && (r.getAttribute('data-name') || '').indexOf(q) === -1;
      });
    });
  }

  /* ── row menus escape the table's scroll box ── */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-menu-btn]');
    if (!btn || !btn.closest('.dt-wrap')) { return; }
    var panel2 = btn.parentElement.querySelector('[data-menu-panel]');
    if (!panel2 || panel2.hidden) { return; }
    var r = btn.getBoundingClientRect();
    panel2.style.position = 'fixed';
    panel2.style.top = Math.min(r.bottom + 8, window.innerHeight - panel2.offsetHeight - 12) + 'px';
    panel2.style.left = Math.max(12, r.right - panel2.offsetWidth) + 'px';
    panel2.style.right = 'auto';
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

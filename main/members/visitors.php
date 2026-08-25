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
$user            = $demo_roles[$demo_role]['user'];
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
$stats = $people_stats['visitors'];

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
                           data-wait="<?= (int) $v['stage_days'] ?>">
                    <div class="vcard__top">
                      <?= mu_av($v['name'], 'sm') ?>
                      <span class="vcard__name"><?= htmlspecialchars($v['name']) ?></span>
                      <?= mu_age_pill((int) $v['stage_days']) ?>
                    </div>
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
                      data-wait="<?= (int) $v['stage_days'] ?>">
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
                       data-wait="<?= (int) $v['stage_days'] ?>">
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

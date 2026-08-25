<?php
/**
 * Mutendi CMS — All Members.
 *
 * The church's full member directory and the most-used page in the system.
 * UI only: search, filters, sorting, selection and pagination are visual.
 * Every action is gated by the same $permissions / $enabled_modules rules the
 * sidebar and dashboard use, so an Usher never sees a Delete control.
 */

require __DIR__ . '/../includes/config.php';

/* ══════════════ DEMO ONLY — REMOVE BEFORE PRODUCTION ══════════════ */
$demo_role       = isset($_GET['role'], $demo_roles[$_GET['role']]) ? $_GET['role'] : 'church_admin';
$user            = $demo_roles[$demo_role]['user'];
$permissions     = $demo_roles[$demo_role]['perms'];
$enabled_modules = $demo_roles[$demo_role]['modules'];
/* ═══════════════════════════ END DEMO ═══════════════════════════ */

/* ------------------------------------------------------------- helpers -- */
/* View helpers. Guarded so the six People pages can each carry a copy
   without colliding — they are never loaded together. */
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

    function mu_av(string $name, string $size = 'md', ?string $photo = null): string {
        $cls = 'av av--' . $size . ' ' . mu_avc($name);
        if ($photo) {
            return '<span class="' . $cls . '"><img src="' . htmlspecialchars($photo) . '" alt=""></span>';
        }
        return '<span class="' . $cls . '" aria-hidden="true">' . htmlspecialchars(mu_initials($name)) . '</span>';
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

$rows  = $members_demo;
$stats = $people_stats['members'];

$page_title = 'Members';
require __DIR__ . '/../components/header.php';
?>

<div class="page">

  <!-- ═════════════════════════════ PAGE HEADER ═════════════════════════════ -->
  <header class="page__head">
    <div>
      <nav class="crumbs" aria-label="Breadcrumb">
        <a href="<?= $base_url ?>index.php">Dashboard</a>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span>People</span>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span aria-current="page">Members</span>
      </nav>
      <div class="title-row">
        <h1 class="page__title">Members</h1>
        <span class="count-chip" data-count="<?= $stats['total'] ?>">0</span>
      </div>
      <p class="page__sub">Your complete church membership directory.</p>
    </div>

    <div class="page__actions">
      <?php if (mu_can('members.add')): ?>
        <a class="btn" href="<?= $base_url ?>members/add.php">
          <i class="fa-solid fa-plus" aria-hidden="true"></i> Add Member
        </a>
        <button class="btn btn--ghost" type="button" data-open="modalImport">
          <i class="fa-solid fa-file-import" aria-hidden="true"></i> Import
        </button>
      <?php endif; ?>

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

  <!-- ═════════════════════════════ STAT STRIP ═════════════════════════════ -->
  <div class="stat-strip">
    <?php
      $tiles = [
        ['all',      'Total Members',  $stats['total'],     'fa-users',       'blue'],
        ['active',   'Active',         $stats['active'],    'fa-circle-check','green'],
        ['new',      'New This Month', $stats['new_month'], 'fa-user-plus',   'purple'],
        ['inactive', 'Inactive',       $stats['inactive'],  'fa-user-clock',  'grey'],
      ];
      foreach ($tiles as [$key, $label, $value, $icon, $tone]):
    ?>
      <button class="stat-tile<?= $key === 'all' ? ' is-on' : '' ?>" type="button"
              data-stat-filter="<?= $key ?>" aria-pressed="<?= $key === 'all' ? 'true' : 'false' ?>">
        <span class="stat-tile__icon tone-<?= $tone ?>" aria-hidden="true"><i class="fa-solid <?= $icon ?>"></i></span>
        <span class="stat-tile__body">
          <span class="stat-tile__value" data-count="<?= $value ?>">0</span>
          <span class="stat-tile__label"><?= $label ?></span>
        </span>
      </button>
    <?php endforeach; ?>
  </div>

  <!-- ═════════════════════════ TOOLBAR / VIEW SWITCH ═════════════════════════ -->
  <div class="toolbar">
    <div class="viewswitch" role="group" aria-label="View">
      <button type="button" data-view="table"   aria-pressed="true"  aria-label="Table view"><i class="fa-solid fa-table-list" aria-hidden="true"></i></button>
      <button type="button" data-view="cards"   aria-pressed="false" aria-label="Card grid view"><i class="fa-solid fa-table-cells-large" aria-hidden="true"></i></button>
      <button type="button" data-view="compact" aria-pressed="false" aria-label="Compact list view"><i class="fa-solid fa-list" aria-hidden="true"></i></button>
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
      <div class="field col-2">
        <label for="fSearch">Search</label>
        <div class="search-field">
          <i class="fa-solid fa-magnifying-glass search-field__icon" aria-hidden="true"></i>
          <input class="input" type="search" id="fSearch" data-search
                 placeholder="Search by name, phone, membership number&hellip;">
          <button class="search-field__clear" type="button" data-search-clear hidden aria-label="Clear search">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
          </button>
        </div>
      </div>

      <div class="field">
        <label for="fStatus">Status</label>
        <select class="select" id="fStatus" data-filter>
          <option>All</option><option>Active</option><option>Inactive</option>
          <option>Transferred</option><option>Deceased</option>
        </select>
      </div>

      <div class="field">
        <label for="fGender">Gender</label>
        <select class="select" id="fGender" data-filter><option>All</option><option>Male</option><option>Female</option></select>
      </div>

      <div class="field">
        <label for="fAge">Age Group</label>
        <select class="select" id="fAge" data-filter>
          <option>All</option><option>Children (0-12)</option><option>Youth (13-24)</option>
          <option>Adults (25-59)</option><option>Seniors (60+)</option>
        </select>
      </div>

      <?php if (mu_mod('departments')): ?>
        <div class="field">
          <label for="fDept">Department</label>
          <select class="select" id="fDept" data-filter>
            <option>All</option>
            <?php foreach ($departments_list as $d): ?><option><?= htmlspecialchars($d) ?></option><?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <?php if (mu_mod('cell_groups')): ?>
        <div class="field">
          <label for="fCell">Cell Group</label>
          <select class="select" id="fCell" data-filter>
            <option>All</option>
            <?php foreach ($cells_list as $c): ?><option><?= htmlspecialchars($c) ?></option><?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <div class="field">
        <label for="fMarital">Marital Status</label>
        <select class="select" id="fMarital" data-filter>
          <option>All</option><option>Single</option><option>Married</option><option>Widowed</option><option>Divorced</option>
        </select>
      </div>

      <div class="field"><label for="fFrom">Joined From</label><input class="input" type="date" id="fFrom" data-filter></div>
      <div class="field"><label for="fTo">Joined To</label><input class="input" type="date" id="fTo" data-filter></div>

      <div class="field">
        <label for="fShow">Show</label>
        <select class="select" id="fShow" data-page-size>
          <option>20</option><option>50</option><option>100</option>
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

    <!-- Skeleton, swapped for the real content once "loaded". -->
    <div data-skeleton>
      <?php for ($i = 0; $i < 8; $i++): ?>
        <div class="sk-row">
          <span class="sk sk--av"></span>
          <span><span class="sk sk--text" style="width:40%;display:block"></span><span class="sk sk--line" style="width:24%"></span></span>
          <span class="sk sk--pill"></span>
          <span class="sk sk--text" style="width:76px"></span>
        </div>
      <?php endfor; ?>
    </div>

    <div data-content>

      <!-- ─────────────── TABLE VIEW ─────────────── -->
      <div data-view-panel="table">
        <div class="dt-wrap">
          <table class="dt">
            <thead>
              <tr>
                <th style="width:38px"><input class="check" type="checkbox" data-check-all aria-label="Select all members"></th>
                <th style="width:44px">#</th>
                <th class="is-sortable" data-sort="name" aria-sort="ascending">Member <i class="fa-solid fa-arrow-up-long sort" aria-hidden="true"></i></th>
                <th>Contact</th>
                <th class="is-sortable" data-sort="gender">Gender <i class="fa-solid fa-arrow-down-long sort" aria-hidden="true"></i></th>
                <th class="is-sortable" data-sort="age">Age <i class="fa-solid fa-arrow-down-long sort" aria-hidden="true"></i></th>
                <?php if (mu_mod('departments')): ?><th>Department</th><?php endif; ?>
                <?php if (mu_mod('cell_groups')): ?><th>Cell Group</th><?php endif; ?>
                <th>Status</th>
                <th class="is-sortable" data-sort="joined">Date Joined <i class="fa-solid fa-arrow-down-long sort" aria-hidden="true"></i></th>
                <th>Last Attended</th>
                <th style="text-align:right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $i => $m): ?>
                <tr data-row
                    data-name="<?= htmlspecialchars(mb_strtolower($m['name'])) ?>"
                    data-phone="<?= htmlspecialchars($m['phone']) ?>"
                    data-no="<?= htmlspecialchars(mb_strtolower($m['member_no'])) ?>"
                    data-status="<?= htmlspecialchars($m['status']) ?>"
                    data-gender="<?= htmlspecialchars($m['gender']) ?>"
                    data-age="<?= (int) $m['age'] ?>"
                    data-dept="<?= htmlspecialchars((string) $m['department']) ?>"
                    data-cell="<?= htmlspecialchars((string) $m['cell_group']) ?>"
                    data-marital="<?= htmlspecialchars($m['marital']) ?>"
                    data-joined="<?= htmlspecialchars($m['joined']) ?>">
                  <td><input class="check" type="checkbox" data-row-check aria-label="Select <?= htmlspecialchars($m['name']) ?>"></td>
                  <td class="num"><?= $i + 1 ?></td>
                  <td>
                    <button class="person" type="button" data-open-member="<?= (int) $m['id'] ?>" style="text-align:left">
                      <?= mu_av($m['name'], 'sm') ?>
                      <span class="person__text">
                        <span class="person__name"><?= htmlspecialchars($m['name']) ?></span>
                        <span class="tsub"><?= htmlspecialchars($m['member_no']) ?></span>
                      </span>
                    </button>
                  </td>
                  <td class="nowrap">
                    <span class="copyable"><?= htmlspecialchars($m['phone']) ?>
                      <button class="copy-btn" type="button" data-copy="<?= htmlspecialchars($m['phone']) ?>" aria-label="Copy phone number"><i class="fa-regular fa-copy" aria-hidden="true"></i></button>
                    </span>
                    <span class="tsub copyable"><?= htmlspecialchars($m['email']) ?>
                      <button class="copy-btn" type="button" data-copy="<?= htmlspecialchars($m['email']) ?>" aria-label="Copy email address"><i class="fa-regular fa-copy" aria-hidden="true"></i></button>
                    </span>
                  </td>
                  <td><?= htmlspecialchars($m['gender']) ?></td>
                  <td class="nowrap"><?= (int) $m['age'] ?><span class="tsub"><?= mu_date($m['dob']) ?></span></td>
                  <?php if (mu_mod('departments')): ?>
                    <td class="nowrap"><?= $m['department'] ? '<span class="pill is-brand">' . htmlspecialchars($m['department']) . '</span>' : '<span class="tsub">&mdash;</span>' ?></td>
                  <?php endif; ?>
                  <?php if (mu_mod('cell_groups')): ?>
                    <td class="nowrap"><?= $m['cell_group'] ? htmlspecialchars($m['cell_group']) : '<span class="tsub">&mdash;</span>' ?></td>
                  <?php endif; ?>
                  <td><span class="spill is-<?= strtolower($m['status']) ?>"><?= htmlspecialchars($m['status']) ?></span></td>
                  <td class="nowrap"><?= mu_date($m['joined']) ?></td>
                  <td class="nowrap"<?= $m['last_attended_days'] >= 30 ? ' style="color:var(--faint)"' : '' ?>><?= mu_ago($m['last_attended_days']) ?></td>
                  <td>
                    <div class="rowacts">
                      <button class="iconbtn iconbtn--sm" type="button" data-open-member="<?= (int) $m['id'] ?>" aria-label="View <?= htmlspecialchars($m['name']) ?>"><i class="fa-regular fa-eye" aria-hidden="true"></i></button>
                      <?php if (mu_can('members.edit')): ?>
                        <button class="iconbtn iconbtn--sm" type="button" data-toast="Opening editor&hellip;" aria-label="Edit <?= htmlspecialchars($m['name']) ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i></button>
                      <?php endif; ?>
                      <?php if (mu_mod('communication')): ?>
                        <button class="iconbtn iconbtn--sm" type="button" data-open="modalMessage" aria-label="Message <?= htmlspecialchars($m['name']) ?>"><i class="fa-regular fa-comment" aria-hidden="true"></i></button>
                      <?php endif; ?>
                      <div class="drop" data-menu>
                        <button class="iconbtn iconbtn--sm" type="button" aria-haspopup="true" aria-expanded="false" data-menu-btn aria-label="More actions for <?= htmlspecialchars($m['name']) ?>">
                          <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                        </button>
                        <div class="menu menu--sm" data-menu-panel hidden>
                          <a class="menu__item" href="#" data-open-member="<?= (int) $m['id'] ?>"><i class="fa-regular fa-eye" aria-hidden="true"></i> View Profile</a>
                          <?php if (mu_can('members.edit')): ?><a class="menu__item" href="#" data-toast="Opening editor&hellip;"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</a><?php endif; ?>
                          <?php if (mu_mod('communication')): ?><a class="menu__item" href="#" data-open="modalMessage"><i class="fa-regular fa-comment" aria-hidden="true"></i> Send Message</a><?php endif; ?>
                          <?php if (mu_mod('departments')): ?><a class="menu__item" href="#" data-toast="Choose a department"><i class="fa-solid fa-sitemap" aria-hidden="true"></i> Add to Department</a><?php endif; ?>
                          <?php if (mu_mod('cell_groups')): ?><a class="menu__item" href="#" data-toast="Choose a cell group"><i class="fa-solid fa-people-group" aria-hidden="true"></i> Add to Cell Group</a><?php endif; ?>
                          <?php if (mu_mod('attendance')): ?><a class="menu__item" href="<?= $base_url ?>attendance/register.php"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i> View Attendance</a><?php endif; ?>
                          <?php if (mu_can('finance.view')): ?><a class="menu__item" href="<?= $base_url ?>finance/contributions.php"><i class="fa-solid fa-coins" aria-hidden="true"></i> View Giving</a><?php endif; ?>
                          <a class="menu__item" href="#" data-toast="Membership card sent to printer"><i class="fa-solid fa-id-card" aria-hidden="true"></i> Print Card</a>
                          <?php if (mu_can('members.edit')): ?>
                            <div class="menu__sep" role="separator"></div>
                            <a class="menu__item" href="#" data-open="modalStatus"><i class="fa-solid fa-user-clock" aria-hidden="true"></i> Mark Inactive</a>
                          <?php endif; ?>
                          <?php if (mu_can('members.delete')): ?>
                            <a class="menu__item menu__item--danger" href="#" data-open="modalDelete" data-name="<?= htmlspecialchars($m['name']) ?>"><i class="fa-solid fa-trash" aria-hidden="true"></i> Delete</a>
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

        <!-- Stacked cards below 768px. -->
        <div class="dt-cards" style="padding:12px">
          <?php foreach ($rows as $m): ?>
            <article class="pcard" data-card
                     data-name="<?= htmlspecialchars(mb_strtolower($m['name'])) ?>"
                     data-status="<?= htmlspecialchars($m['status']) ?>">
              <button class="pcard__main" type="button" data-card-toggle>
                <?= mu_av($m['name'], 'md') ?>
                <span class="pcard__text">
                  <span class="pcard__name"><?= htmlspecialchars($m['name']) ?></span>
                  <span class="pcard__meta"><?= htmlspecialchars($m['member_no']) ?> &middot; <?= htmlspecialchars($m['phone']) ?></span>
                </span>
                <span class="spill is-<?= strtolower($m['status']) ?>"><?= htmlspecialchars($m['status']) ?></span>
                <i class="fa-solid fa-chevron-down pcard__chev" aria-hidden="true"></i>
              </button>
              <div class="pcard__more">
                <dl>
                  <div class="pcard__row"><dt>Email</dt><dd><?= htmlspecialchars($m['email']) ?></dd></div>
                  <div class="pcard__row"><dt>Gender / Age</dt><dd><?= htmlspecialchars($m['gender']) ?> &middot; <?= (int) $m['age'] ?></dd></div>
                  <?php if (mu_mod('departments')): ?><div class="pcard__row"><dt>Department</dt><dd><?= htmlspecialchars($m['department'] ?: '—') ?></dd></div><?php endif; ?>
                  <?php if (mu_mod('cell_groups')): ?><div class="pcard__row"><dt>Cell Group</dt><dd><?= htmlspecialchars($m['cell_group'] ?: '—') ?></dd></div><?php endif; ?>
                  <div class="pcard__row"><dt>Joined</dt><dd><?= mu_date($m['joined']) ?></dd></div>
                  <div class="pcard__row"><dt>Last attended</dt><dd><?= mu_ago($m['last_attended_days']) ?></dd></div>
                </dl>
                <div class="pcard__acts">
                  <button class="chip-btn" type="button" data-open-member="<?= (int) $m['id'] ?>">View</button>
                  <?php if (mu_can('members.edit')): ?><button class="chip-btn" type="button" data-toast="Opening editor&hellip;">Edit</button><?php endif; ?>
                  <?php if (mu_mod('communication')): ?><button class="chip-btn" type="button" data-open="modalMessage">Message</button><?php endif; ?>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- ─────────────── CARD GRID VIEW ─────────────── -->
      <div data-view-panel="cards" hidden style="padding:16px">
        <div class="cardgrid cardgrid--4 stagger">
          <?php foreach ($rows as $m): ?>
            <article class="gcard" data-card style="align-items:center;text-align:center"
                     data-name="<?= htmlspecialchars(mb_strtolower($m['name'])) ?>"
                     data-status="<?= htmlspecialchars($m['status']) ?>">
              <?= mu_av($m['name'], 'lg') ?>
              <h3 style="margin-top:12px;color:var(--ink);font-size:14px;font-weight:800"><?= htmlspecialchars($m['name']) ?></h3>
              <p style="margin-top:3px;color:var(--faint);font-size:11.5px"><?= htmlspecialchars($m['member_no']) ?></p>
              <p style="margin-top:10px"><span class="spill is-<?= strtolower($m['status']) ?>"><?= htmlspecialchars($m['status']) ?></span></p>
              <p style="margin-top:10px;display:flex;gap:6px;flex-wrap:wrap;justify-content:center">
                <?php if (mu_mod('departments') && $m['department']): ?><span class="pill is-brand"><?= htmlspecialchars($m['department']) ?></span><?php endif; ?>
                <?php if (mu_mod('cell_groups') && $m['cell_group']): ?><span class="pill is-brand"><?= htmlspecialchars($m['cell_group']) ?></span><?php endif; ?>
              </p>
              <a href="tel:<?= htmlspecialchars(str_replace(' ', '', $m['phone'])) ?>"
                 style="margin-top:12px;display:inline-flex;align-items:center;gap:7px;color:var(--muted);font-size:12px;font-weight:600">
                <i class="fa-solid fa-phone" style="color:var(--brand-500)" aria-hidden="true"></i> <?= htmlspecialchars($m['phone']) ?>
              </a>
              <div class="gcard__over">
                <a href="#" data-open-member="<?= (int) $m['id'] ?>"><i class="fa-regular fa-eye" aria-hidden="true"></i> View</a>
                <?php if (mu_can('members.edit')): ?><a href="#" data-toast="Opening editor&hellip;"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</a><?php endif; ?>
                <?php if (mu_mod('communication')): ?><a href="#" data-open="modalMessage"><i class="fa-regular fa-comment" aria-hidden="true"></i> Message</a><?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- ─────────────── COMPACT LIST VIEW ─────────────── -->
      <div data-view-panel="compact" hidden>
        <div class="clist" style="border:0;border-radius:0">
          <?php foreach ($rows as $m):
              $dot = ['Active' => 'var(--ok)', 'Inactive' => '#94A3B8', 'Transferred' => 'var(--info)', 'Deceased' => '#3F3A47'][$m['status']] ?? 'var(--muted)';
          ?>
            <button class="crow" type="button" data-card data-open-member="<?= (int) $m['id'] ?>"
                    data-name="<?= htmlspecialchars(mb_strtolower($m['name'])) ?>"
                    data-status="<?= htmlspecialchars($m['status']) ?>" style="width:100%;text-align:left">
              <?= mu_av($m['name'], 'xs') ?>
              <span class="crow__name"><?= htmlspecialchars($m['name']) ?></span>
              <span class="crow__phone"><?= htmlspecialchars($m['phone']) ?></span>
              <span class="crow__dot" style="background:<?= $dot ?>" aria-label="<?= htmlspecialchars($m['status']) ?>"></span>
              <i class="fa-solid fa-chevron-right crow__chev" aria-hidden="true"></i>
            </button>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- ─────────────── EMPTY STATE ─────────────── -->
      <div class="empty" data-empty hidden>
        <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-users-slash"></i></span>
        <h3>No members match those filters</h3>
        <p>Try a different search term, or clear the filters to see your whole directory again.</p>
        <button class="btn" type="button" data-reset-filters><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Clear filters</button>
      </div>

      <!-- ─────────────── PAGINATION ─────────────── -->
      <div class="pager">
        <span>Showing <strong>1</strong> to <strong><?= count($rows) ?></strong> of <strong><?= number_format($stats['total']) ?></strong> members</span>
        <div class="pager__pages">
          <button type="button" disabled aria-label="Previous page"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
          <button type="button" aria-current="page">1</button>
          <button type="button">2</button>
          <button type="button">3</button>
          <span style="padding:0 4px">&hellip;</span>
          <button type="button">65</button>
          <button type="button" aria-label="Next page"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
        </div>
        <label class="pager__size">Show
          <select class="select" data-page-size><option>20</option><option>50</option><option>100</option></select>
        </label>
      </div>
    </div>
  </section>
</div>

<!-- ═════════════════════════ BULK ACTION BAR ═════════════════════════ -->
<div class="bulkbar" id="bulkbar" role="status">
  <span class="bulkbar__count"><span data-bulk-count>0</span> selected</span>
  <span class="bulkbar__sep" aria-hidden="true"></span>
  <?php if (mu_mod('communication')): ?>
    <button class="bulkbar__btn" type="button" data-open="modalMessage"><i class="fa-regular fa-comment" aria-hidden="true"></i> Send Message</button>
  <?php endif; ?>
  <?php if (mu_mod('departments')): ?>
    <button class="bulkbar__btn" type="button" data-toast="Choose a department"><i class="fa-solid fa-sitemap" aria-hidden="true"></i> Department</button>
  <?php endif; ?>
  <?php if (mu_mod('cell_groups')): ?>
    <button class="bulkbar__btn" type="button" data-toast="Choose a cell group"><i class="fa-solid fa-people-group" aria-hidden="true"></i> Cell Group</button>
  <?php endif; ?>
  <button class="bulkbar__btn" type="button" data-open="modalStatus"><i class="fa-solid fa-user-clock" aria-hidden="true"></i> Change Status</button>
  <?php if (mu_can('members.export')): ?>
    <button class="bulkbar__btn" type="button" data-toast="Export started"><i class="fa-solid fa-file-export" aria-hidden="true"></i> Export</button>
  <?php endif; ?>
  <?php if (mu_can('members.delete')): ?>
    <button class="bulkbar__btn is-danger" type="button" data-open="modalDelete"><i class="fa-solid fa-trash" aria-hidden="true"></i> Delete</button>
  <?php endif; ?>
  <button class="bulkbar__close" type="button" data-bulk-clear aria-label="Clear selection"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
</div>

<!-- ═════════════════════ MEMBER QUICK VIEW DRAWER ═════════════════════ -->
<div class="drawer-scrim" data-drawer-scrim hidden></div>
<aside class="drawer" id="memberDrawer" role="dialog" aria-modal="true" aria-labelledby="drawerName" hidden>
  <header class="drawer__head">
    <span class="av av--lg av-c0" data-drawer-av aria-hidden="true">TM</span>
    <div class="drawer__title">
      <h2 id="drawerName">Member</h2>
      <p><span data-drawer-no>—</span> &middot; <span class="spill is-active" data-drawer-status>Active</span></p>
    </div>
    <button class="iconbtn" type="button" data-drawer-close aria-label="Close panel"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
  </header>

  <div class="drawer__body">
    <div class="tabs" role="tablist">
      <button role="tab" aria-selected="true"  data-tab="personal">Personal</button>
      <button role="tab" aria-selected="false" data-tab="contact">Contact</button>
      <button role="tab" aria-selected="false" data-tab="church">Church</button>
      <button role="tab" aria-selected="false" data-tab="family">Family</button>
      <?php if (mu_mod('attendance')): ?><button role="tab" aria-selected="false" data-tab="attendance">Attendance</button><?php endif; ?>
      <?php if (mu_can('finance.view')): ?><button role="tab" aria-selected="false" data-tab="giving">Giving</button><?php endif; ?>
    </div>

    <div class="tabpanel" data-panel="personal">
      <dl class="deflist">
        <div><dt>Full name</dt><dd data-f="name">—</dd></div>
        <div><dt>Gender</dt><dd data-f="gender">—</dd></div>
        <div><dt>Date of birth</dt><dd data-f="dob">—</dd></div>
        <div><dt>Age</dt><dd data-f="age">—</dd></div>
        <div><dt>Marital status</dt><dd data-f="marital">—</dd></div>
        <div><dt>Occupation</dt><dd data-f="occupation">—</dd></div>
      </dl>
    </div>

    <div class="tabpanel" data-panel="contact" hidden>
      <dl class="deflist">
        <div><dt>Phone</dt><dd data-f="phone">—</dd></div>
        <div><dt>Email</dt><dd data-f="email">—</dd></div>
        <div><dt>Suburb</dt><dd data-f="suburb">—</dd></div>
        <div><dt>City</dt><dd data-f="city">—</dd></div>
        <div><dt>Province</dt><dd data-f="province">—</dd></div>
      </dl>
    </div>

    <div class="tabpanel" data-panel="church" hidden>
      <dl class="deflist">
        <div><dt>Membership number</dt><dd data-f="member_no">—</dd></div>
        <div><dt>Date joined</dt><dd data-f="joined">—</dd></div>
        <div><dt>Status</dt><dd data-f="status">—</dd></div>
        <?php if (mu_mod('departments')): ?><div><dt>Department</dt><dd data-f="department">—</dd></div><?php endif; ?>
        <?php if (mu_mod('cell_groups')): ?><div><dt>Cell group</dt><dd data-f="cell_group">—</dd></div><?php endif; ?>
        <div><dt>Last attended</dt><dd data-f="last">—</dd></div>
      </dl>
    </div>

    <div class="tabpanel" data-panel="family" hidden>
      <div class="empty" style="padding:34px 10px">
        <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-house-user"></i></span>
        <h3>Not in a household yet</h3>
        <p>Add this member to a household so they appear in family records and pastoral visit lists.</p>
        <a class="btn" href="<?= $base_url ?>members/families.php"><i class="fa-solid fa-plus" aria-hidden="true"></i> Assign household</a>
      </div>
    </div>

    <?php if (mu_mod('attendance')): ?>
      <div class="tabpanel" data-panel="attendance" hidden>
        <div class="chart-wrap" style="height:190px">
          <canvas id="drawerAttendance" role="img" aria-label="Attendance over the last 8 services"></canvas>
        </div>
        <dl class="deflist" style="margin-top:12px">
          <div><dt>Services attended (12 weeks)</dt><dd>9 of 12</dd></div>
          <div><dt>Attendance rate</dt><dd>75%</dd></div>
        </dl>
      </div>
    <?php endif; ?>

    <?php if (mu_can('finance.view')): ?>
      <div class="tabpanel" data-panel="giving" hidden>
        <dl class="deflist">
          <div><dt>This month</dt><dd>$120.00</dd></div>
          <div><dt>Year to date</dt><dd>$1,340.00</dd></div>
          <div><dt>Last contribution</dt><dd>24 Aug 2026 &middot; Tithe</dd></div>
        </dl>
      </div>
    <?php endif; ?>
  </div>

  <footer class="drawer__foot">
    <?php if (mu_can('members.edit')): ?>
      <button class="btn btn--ghost" type="button" data-toast="Opening editor&hellip;"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</button>
    <?php endif; ?>
    <?php if (mu_mod('communication')): ?>
      <button class="btn btn--ghost" type="button" data-open="modalMessage"><i class="fa-regular fa-comment" aria-hidden="true"></i> Message</button>
    <?php endif; ?>
    <button class="btn" type="button" data-toast="Opening full profile&hellip;">View Full Profile</button>
  </footer>
</aside>

<!-- ═══════════════════════════════ MODALS ═══════════════════════════════ -->

<?php if (mu_mod('communication')): ?>
<div class="modal-scrim" id="modalMessage" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="msgTitle">
    <header class="modal__head">
      <h2 id="msgTitle">Send Message</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>
    <div class="modal__body">
      <p class="modal__hint">Sending to <strong data-msg-recipients>1 recipient</strong>.</p>

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
          <option>No template</option><option>Welcome message</option><option>Service reminder</option>
          <option>Birthday wishes</option><option>Follow-up</option>
        </select>
      </div>

      <div class="field">
        <label for="msgBody">Message</label>
        <div class="chips-row" style="margin:0 0 8px">
          <button class="fchip" type="button" data-merge="{first_name}">{first_name}</button>
          <button class="fchip" type="button" data-merge="{church_name}">{church_name}</button>
          <button class="fchip" type="button" data-merge="{service_time}">{service_time}</button>
        </div>
        <textarea class="textarea" id="msgBody" rows="5" maxlength="480" placeholder="Type your message&hellip;">Hello {first_name}, </textarea>
        <p class="hint"><span data-char-count>21</span> / 480 characters &middot; <span data-sms-count>1</span> SMS</p>
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

<div class="modal-scrim" id="modalStatus" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="stTitle">
    <header class="modal__head">
      <h2 id="stTitle">Change Status</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>
    <div class="modal__body">
      <div class="field" style="margin-bottom:14px">
        <label for="stNew">New status</label>
        <select class="select" id="stNew"><option>Active</option><option>Inactive</option><option>Transferred</option><option>Deceased</option></select>
      </div>
      <div class="field" style="margin-bottom:14px">
        <label for="stReason">Reason</label>
        <textarea class="textarea" id="stReason" rows="3" placeholder="Why is the status changing?"></textarea>
      </div>
      <div class="field">
        <label for="stDate">Effective date</label>
        <input class="input" type="date" id="stDate" value="<?= date('Y-m-d') ?>">
      </div>
    </div>
    <footer class="modal__foot">
      <span class="modal__spacer"></span>
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <button class="btn" type="button" data-close data-toast="Status updated">Save Change</button>
    </footer>
  </div>
</div>

<?php if (mu_can('members.delete')): ?>
<div class="modal-scrim" id="modalDelete" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="delTitle">
    <header class="modal__head">
      <h2 id="delTitle">Delete member</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>
    <div class="modal__body">
      <div class="err-summary is-on" style="align-items:center">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        <span>This permanently removes the member and their attendance and giving history. It cannot be undone.</span>
      </div>
      <div class="field">
        <label for="delConfirm">Type <strong data-del-name>the member's name</strong> to confirm</label>
        <input class="input" type="text" id="delConfirm" placeholder="Full name" autocomplete="off">
        <p class="hint">This check exists so a mistap cannot delete a record.</p>
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

<?php if (mu_can('members.add')): ?>
<div class="modal-scrim" id="modalImport" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="impTitle">
    <header class="modal__head">
      <h2 id="impTitle">Import Members</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>
    <div class="modal__body">
      <div class="stepper" style="margin-bottom:18px">
        <div class="stepper__item is-on"><span class="stepper__btn"><span class="stepper__num">1</span><span class="stepper__label">Upload</span></span><span class="stepper__line"></span></div>
        <div class="stepper__item"><span class="stepper__btn"><span class="stepper__num">2</span><span class="stepper__label">Map columns</span></span><span class="stepper__line"></span></div>
        <div class="stepper__item"><span class="stepper__btn"><span class="stepper__num">3</span><span class="stepper__label">Confirm</span></span></div>
      </div>

      <div class="dropzone" id="importDrop">
        <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
        <strong>Drop your spreadsheet here</strong>
        <span>or click to browse &middot; CSV or XLSX, up to 5 MB</span>
      </div>
      <p class="hint" style="text-align:center;margin-top:10px">
        <a href="#" style="color:var(--brand-600);font-weight:700" data-toast="Template downloaded">Download template</a>
      </p>

      <p class="modal__group" style="margin-top:18px">Column mapping preview</p>
      <div class="dt-wrap">
        <table class="dt" style="font-size:12px">
          <thead><tr><th>Spreadsheet column</th><th>Maps to</th><th>Sample</th></tr></thead>
          <tbody>
            <tr><td>Name</td><td><select class="select" style="height:32px"><option>First name</option><option>Full name</option></select></td><td class="tsub">Tendai</td></tr>
            <tr><td>Surname</td><td><select class="select" style="height:32px"><option>Surname</option></select></td><td class="tsub">Marufu</td></tr>
            <tr><td>Cell</td><td><select class="select" style="height:32px"><option>Phone</option><option>Cell group</option></select></td><td class="tsub">+263 77 412 8890</td></tr>
            <tr><td>DOB</td><td><select class="select" style="height:32px"><option>Date of birth</option></select></td><td class="tsub">1994-03-12</td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <footer class="modal__foot">
      <span class="modal__spacer"></span>
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <button class="btn" type="button" data-close data-toast="Import queued — 0 of 0 rows">Start Import</button>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
/* Members directory — view switching, instant filtering, selection, drawer,
   modals and toasts. All client-side: nothing here talks to a server. */
(function () {
  'use strict';

  var still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var MEMBERS = <?= json_encode(array_column($members_demo, null, 'id'), JSON_UNESCAPED_UNICODE) ?>;

  /* ───────────────────────────── toasts ───────────────────────────── */
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
    setTimeout(kill, 3600);
  }
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-toast]');
    if (t) { e.preventDefault(); toast(t.getAttribute('data-toast')); }
  });

  /* ──────────────────── skeleton → content swap ──────────────────── */
  var content = document.getElementById('listPanel');
  setTimeout(function () { content.classList.add('is-loaded'); }, still ? 0 : 650);

  /* ─────────────────────── counts tick up ─────────────────────── */
  function tick(el) {
    var target = parseFloat(el.getAttribute('data-count')) || 0;
    if (still) { el.textContent = target.toLocaleString(); return; }
    var start = performance.now(), dur = 900;
    (function step(now) {
      var p = Math.min(1, (now - start) / dur);
      var eased = 1 - Math.pow(1 - p, 3);
      el.textContent = Math.round(target * eased).toLocaleString();
      if (p < 1) { requestAnimationFrame(step); }
    })(start);
  }
  [].forEach.call(document.querySelectorAll('[data-count]'), tick);

  /* ───────────────────────── view switcher ───────────────────────── */
  var VIEW_KEY = 'mutendi-members-view';
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
  try { var saved = sessionStorage.getItem(VIEW_KEY); if (saved) { setView(saved); } } catch (e) {}

  /* ────────────────────── filtering (instant) ────────────────────── */
  var search = document.querySelector('[data-search]'),
      clearBtn = document.querySelector('[data-search-clear]'),
      resultCount = document.querySelector('[data-result-count]'),
      emptyState = document.querySelector('[data-empty]'),
      chipsRow = document.querySelector('[data-filter-chips]'),
      activeBadge = document.querySelector('[data-active-filters]');

  function currentFilters() {
    var f = {};
    ['fStatus','fGender','fAge','fDept','fCell','fMarital'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el && el.value && el.value !== 'All') { f[id] = el.value; }
    });
    if (search && search.value.trim()) { f.q = search.value.trim(); }
    return f;
  }

  function ageBand(a) {
    if (a <= 12) return 'Children (0-12)';
    if (a <= 24) return 'Youth (13-24)';
    if (a <= 59) return 'Adults (25-59)';
    return 'Seniors (60+)';
  }

  var statTile = 'all';

  function apply() {
    var f = currentFilters(), q = (f.q || '').toLowerCase(), shown = 0;

    [].forEach.call(document.querySelectorAll('[data-row], [data-card]'), function (el) {
      var name = el.getAttribute('data-name') || '',
          phone = (el.getAttribute('data-phone') || '').toLowerCase(),
          no = el.getAttribute('data-no') || '',
          status = el.getAttribute('data-status') || '',
          age = parseInt(el.getAttribute('data-age') || '0', 10);

      var ok = true;
      if (q && name.indexOf(q) === -1 && phone.indexOf(q) === -1 && no.indexOf(q) === -1) { ok = false; }
      if (ok && f.fStatus && status !== f.fStatus) { ok = false; }
      if (ok && f.fGender && el.getAttribute('data-gender') !== f.fGender) { ok = false; }
      if (ok && f.fAge && el.hasAttribute('data-age') && ageBand(age) !== f.fAge) { ok = false; }
      if (ok && f.fDept && el.getAttribute('data-dept') !== f.fDept) { ok = false; }
      if (ok && f.fCell && el.getAttribute('data-cell') !== f.fCell) { ok = false; }
      if (ok && f.fMarital && el.getAttribute('data-marital') !== f.fMarital) { ok = false; }
      if (ok && statTile === 'active' && status !== 'Active') { ok = false; }
      if (ok && statTile === 'inactive' && status !== 'Inactive') { ok = false; }

      el.hidden = !ok;
      /* Rows and cards for the same person both carry data-row/data-card, so
         count the table rows only to avoid counting each person three times. */
      if (ok && el.hasAttribute('data-row')) { shown++; }
    });

    if (resultCount) { resultCount.textContent = shown; }
    if (emptyState) { emptyState.hidden = shown !== 0; }

    /* chips */
    var keys = Object.keys(f);
    if (chipsRow) {
      chipsRow.innerHTML = '';
      keys.forEach(function (k) {
        var label = k === 'q' ? 'Search: ' + f[k] : f[k];
        var chip = document.createElement('span');
        chip.className = 'fchip';
        chip.innerHTML = '<span></span><button type="button" aria-label="Remove filter"><i class="fa-solid fa-xmark"></i></button>';
        chip.querySelector('span').textContent = label;
        chip.querySelector('button').addEventListener('click', function () {
          if (k === 'q') { search.value = ''; } else { document.getElementById(k).value = 'All'; }
          apply();
        });
        chipsRow.appendChild(chip);
      });
      chipsRow.hidden = keys.length === 0;
    }
    if (activeBadge) { activeBadge.textContent = keys.length; activeBadge.hidden = keys.length === 0; }
    if (clearBtn) { clearBtn.hidden = !(search && search.value); }
  }

  if (search) { search.addEventListener('input', apply); }
  if (clearBtn) { clearBtn.addEventListener('click', function () { search.value = ''; apply(); search.focus(); }); }
  [].forEach.call(document.querySelectorAll('[data-filter]'), function (el) { el.addEventListener('change', apply); });
  [].forEach.call(document.querySelectorAll('[data-reset-filters]'), function (b) {
    b.addEventListener('click', function () {
      if (search) { search.value = ''; }
      [].forEach.call(document.querySelectorAll('[data-filter]'), function (el) {
        if (el.tagName === 'SELECT') { el.value = 'All'; } else { el.value = ''; }
      });
      statTile = 'all';
      [].forEach.call(document.querySelectorAll('[data-stat-filter]'), function (t) {
        var on = t.getAttribute('data-stat-filter') === 'all';
        t.classList.toggle('is-on', on); t.setAttribute('aria-pressed', String(on));
      });
      apply();
      toast('Filters cleared', 'info');
    });
  });

  [].forEach.call(document.querySelectorAll('[data-stat-filter]'), function (t) {
    t.addEventListener('click', function () {
      statTile = t.getAttribute('data-stat-filter');
      [].forEach.call(document.querySelectorAll('[data-stat-filter]'), function (o) {
        var on = o === t;
        o.classList.toggle('is-on', on); o.setAttribute('aria-pressed', String(on));
      });
      apply();
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

  /* ─────────────────────────── selection ─────────────────────────── */
  var bulk = document.getElementById('bulkbar'),
      bulkCount = document.querySelector('[data-bulk-count]');

  function syncBulk() {
    var picked = document.querySelectorAll('[data-row-check]:checked').length;
    if (bulkCount) { bulkCount.textContent = picked; }
    bulk.classList.toggle('is-on', picked > 0);
    var recip = document.querySelector('[data-msg-recipients]');
    if (recip) { recip.textContent = (picked || 1) + ' recipient' + ((picked || 1) === 1 ? '' : 's'); }
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
  var bulkClear = document.querySelector('[data-bulk-clear]');
  if (bulkClear) {
    bulkClear.addEventListener('click', function () {
      [].forEach.call(document.querySelectorAll('[data-row-check]'), function (cb) {
        cb.checked = false; cb.closest('tr').classList.remove('is-picked');
      });
      if (all) { all.checked = false; }
      syncBulk();
    });
  }

  /* ─────────────────────── click-to-copy ─────────────────────── */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-copy]');
    if (!b) { return; }
    e.preventDefault();
    var v = b.getAttribute('data-copy');
    if (navigator.clipboard) { navigator.clipboard.writeText(v).then(function () { toast('Copied ' + v); }); }
    else { toast('Copied ' + v); }
  });

  /* ───────────────────── mobile card expand ───────────────────── */
  [].forEach.call(document.querySelectorAll('[data-card-toggle]'), function (b) {
    b.addEventListener('click', function () { b.closest('.pcard').classList.toggle('is-open'); });
  });

  /* ──────────────────────────── drawer ──────────────────────────── */
  var drawer = document.getElementById('memberDrawer'),
      dScrim = document.querySelector('[data-drawer-scrim]'),
      drawerChart = null;

  function openDrawer(id) {
    var m = MEMBERS[id];
    if (!m) { return; }

    drawer.querySelector('#drawerName').textContent = m.name;
    drawer.querySelector('[data-drawer-no]').textContent = m.member_no;
    var st = drawer.querySelector('[data-drawer-status]');
    st.textContent = m.status;
    st.className = 'spill is-' + m.status.toLowerCase();

    var av = drawer.querySelector('[data-drawer-av]');
    av.textContent = m.name.split(/\s+/).map(function (w, i, arr) {
      return (i === 0 || i === arr.length - 1) ? w[0] : '';
    }).join('').toUpperCase();
    av.className = 'av av--lg ' + avClass(m.name);

    var map = {
      name: m.name, gender: m.gender, dob: m.dob, age: m.age + ' years',
      marital: m.marital, occupation: m.occupation, phone: m.phone, email: m.email,
      suburb: m.suburb, city: m.city, province: m.province, member_no: m.member_no,
      joined: m.joined, status: m.status, department: m.department || '—',
      cell_group: m.cell_group || '—', last: m.last_attended_days + ' days ago'
    };
    Object.keys(map).forEach(function (k) {
      var el = drawer.querySelector('[data-f="' + k + '"]');
      if (el) { el.textContent = map[k]; }
    });

    dScrim.hidden = false; drawer.hidden = false;
    document.body.style.overflow = 'hidden';
    drawer.querySelector('[data-drawer-close]').focus();

    var cv = document.getElementById('drawerAttendance');
    if (cv && window.Chart && !drawerChart) {
      drawerChart = new Chart(cv, {
        type: 'line',
        data: { labels: ['W1','W2','W3','W4','W5','W6','W7','W8'],
                datasets: [{ label: 'Present', data: [1,1,0,1,1,1,0,1],
                  borderColor: '#662F97', backgroundColor: 'rgba(102,47,151,.1)',
                  fill: true, tension: .35, pointRadius: 3, borderWidth: 2 }] },
        options: { responsive: true, maintainAspectRatio: false,
          animation: still ? false : { duration: 500 },
          plugins: { legend: { display: false } },
          scales: { x: { grid: { display: false }, border: { display: false } },
                    y: { display: false, min: 0, max: 1.2 } } }
      });
    }
  }
  /* Mirrors PHP's crc32() exactly (IEEE 802.3 polynomial) so a name resolves
     to the same avatar colour whether PHP or JS renders it. */
  var CRC_TABLE = (function () {
    var t = [], c, n, k;
    for (n = 0; n < 256; n++) {
      c = n;
      for (k = 0; k < 8; k++) { c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1); }
      t[n] = c >>> 0;
    }
    return t;
  })();
  function crc32(str) {
    var bytes = new TextEncoder().encode(str), crc = -1, i;
    for (i = 0; i < bytes.length; i++) { crc = (crc >>> 8) ^ CRC_TABLE[(crc ^ bytes[i]) & 0xFF]; }
    return (crc ^ -1) >>> 0;
  }
  function avClass(name) { return 'av-c' + (crc32(name) % 10); }
  function closeDrawer() {
    drawer.hidden = true; dScrim.hidden = true; document.body.style.overflow = '';
  }
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-open-member]');
    if (t) { e.preventDefault(); openDrawer(parseInt(t.getAttribute('data-open-member'), 10)); }
  });
  drawer.querySelector('[data-drawer-close]').addEventListener('click', closeDrawer);
  dScrim.addEventListener('click', closeDrawer);

  /* drawer tabs */
  [].forEach.call(drawer.querySelectorAll('[data-tab]'), function (b) {
    b.addEventListener('click', function () {
      [].forEach.call(drawer.querySelectorAll('[data-tab]'), function (o) { o.setAttribute('aria-selected', String(o === b)); });
      [].forEach.call(drawer.querySelectorAll('[data-panel]'), function (p) {
        p.hidden = p.getAttribute('data-panel') !== b.getAttribute('data-tab');
      });
    });
  });

  /* ──────────────────────────── modals ──────────────────────────── */
  function openModal(id, trigger) {
    var m = document.getElementById(id);
    if (!m) { return; }
    if (id === 'modalDelete' && trigger) {
      var n = trigger.getAttribute('data-name');
      var slot = m.querySelector('[data-del-name]');
      if (slot) { slot.textContent = n || 'DELETE'; }
      var inp = m.querySelector('#delConfirm'), go = m.querySelector('#delGo');
      inp.value = ''; go.disabled = true;
      inp.oninput = function () { go.disabled = inp.value.trim() !== (n || ''); };
    }
    m.hidden = false; document.body.style.overflow = 'hidden';
  }
  function closeModal(m) { m.hidden = true; document.body.style.overflow = ''; }

  document.addEventListener('click', function (e) {
    var open = e.target.closest('[data-open]');
    if (open) { e.preventDefault(); openModal(open.getAttribute('data-open'), open); return; }
    var close = e.target.closest('[data-close]');
    if (close) { e.preventDefault(); closeModal(close.closest('.modal-scrim')); return; }
    if (e.target.classList.contains('modal-scrim')) { closeModal(e.target); }
  });
  var delGo = document.getElementById('delGo');
  if (delGo) {
    delGo.addEventListener('click', function () {
      closeModal(delGo.closest('.modal-scrim'));
      toast('Member deleted', 'error');
    });
  }

  /* message modal helpers */
  var body = document.getElementById('msgBody');
  if (body) {
    var cc = document.querySelector('[data-char-count]'), sc = document.querySelector('[data-sms-count]');
    var sync = function () {
      cc.textContent = body.value.length;
      sc.textContent = Math.max(1, Math.ceil(body.value.length / 160));
    };
    body.addEventListener('input', sync); sync();
    [].forEach.call(document.querySelectorAll('[data-merge]'), function (b) {
      b.addEventListener('click', function () {
        body.value += b.getAttribute('data-merge'); sync(); body.focus();
      });
    });
  }

  /* ───────────── row menus escape the table's scroll box ───────────── */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-menu-btn]');
    if (!btn || !btn.closest('.dt-wrap')) { return; }
    var panel = btn.parentElement.querySelector('[data-menu-panel]');
    if (!panel || panel.hidden) { return; }
    var r = btn.getBoundingClientRect();
    panel.style.position = 'fixed';
    panel.style.top = Math.min(r.bottom + 8, window.innerHeight - panel.offsetHeight - 12) + 'px';
    panel.style.left = Math.max(12, r.right - panel.offsetWidth) + 'px';
    panel.style.right = 'auto';
  });

  /* ─────────────────────────── sorting ─────────────────────────── */
  [].forEach.call(document.querySelectorAll('.dt th.is-sortable'), function (th) {
    th.addEventListener('click', function () {
      var key = th.getAttribute('data-sort');
      var asc = th.getAttribute('aria-sort') !== 'ascending';
      [].forEach.call(document.querySelectorAll('.dt th'), function (o) { o.removeAttribute('aria-sort'); });
      th.setAttribute('aria-sort', asc ? 'ascending' : 'descending');

      var tbody = th.closest('table').querySelector('tbody');
      var rows = [].slice.call(tbody.querySelectorAll('tr'));
      rows.sort(function (a, b) {
        var av = a.getAttribute('data-' + (key === 'name' ? 'name' : key)) || '';
        var bv = b.getAttribute('data-' + (key === 'name' ? 'name' : key)) || '';
        if (key === 'age') { return (asc ? 1 : -1) * (parseInt(av, 10) - parseInt(bv, 10)); }
        return (asc ? 1 : -1) * av.localeCompare(bv);
      });
      rows.forEach(function (r) { tbody.appendChild(r); });
      toast('Sorted by ' + key, 'info');
    });
  });

  /* ────────────────────────── escape key ────────────────────────── */
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') { return; }
    if (!drawer.hidden) { closeDrawer(); }
    [].forEach.call(document.querySelectorAll('.modal-scrim'), function (m) { if (!m.hidden) { closeModal(m); } });
  });

  apply();
})();
</script>

<?php require __DIR__ . '/../components/footer.php'; ?>

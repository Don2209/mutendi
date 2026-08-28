<?php
/**
 * Mutendi CMS — Announcements.
 *
 * Notices the church publishes to its members: service changes, events,
 * prayer requests, general news. They appear on the member-facing side and may
 * also be pushed out by SMS or email, so every record carries both what was
 * said and how far it actually reached.
 *
 * Three views over one list:
 *   Cards     the default, because a notice is mostly words
 *   Table     when you need to compare reach and status across many
 *   Calendar  when the question is "what goes out, and when"
 *
 * UI only. Nothing is written or sent anywhere.
 */

require __DIR__ . '/../includes/config.php';

/* ══════════════ DEMO ONLY — REMOVE BEFORE PRODUCTION ══════════════ */
$demo_role       = isset($_GET['role'], $demo_roles[$_GET['role']]) ? $_GET['role'] : 'secretary';
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

$has_module = mu_mod('communication');
$can_view   = mu_can('communication.view');
$can_manage = mu_can('announcements.manage');

/* ─────────────────────────── BRANCH AWARENESS ───────────────────────────
   Who a notice is for. Entirely inert for a single church: is_multi_branch()
   is false, so no chip, column, filter or audience option is rendered.
   ──────────────────────────────────────────────────────────────────────── */
require_once __DIR__ . '/../components/branch-switcher.php';

$branch_aware   = is_multi_branch();
$viewing_all    = !$branch_aware || $current_branch === 'all' || $current_branch === null;
$show_branch    = $branch_aware && $viewing_all;
$branch_options = $branch_aware ? get_visible_branches() : [];

/* A branch-scope user reads and posts to their own branch only; an
   organisation-scope user can address all of them, some of them, or one. */
$scoped_branch  = ($branch_aware && ($user['scope'] ?? 'organisation') === 'branch')
    ? get_branch($user['branch_id'] ?? null) : null;

if (!function_exists('mu_branch_tone')) {
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

/* ══════════════════════ PREPARING THE NOTICES ══════════════════════ */

$type_by_key = array_column($announcement_types, null, 'key');
$today       = date('Y-m-d');

$STATUS = [
    'published' => ['label' => 'Published', 'icon' => 'fa-circle-check'],
    'scheduled' => ['label' => 'Scheduled', 'icon' => 'fa-clock'],
    'draft'     => ['label' => 'Draft',     'icon' => 'fa-pen-ruler'],
    'expired'   => ['label' => 'Expired',   'icon' => 'fa-circle-xmark'],
];

$AUDIENCES = [
    'all'        => 'All Members',
    'department' => 'Department',
    'cell'       => 'Cell Group',
    'branch'     => 'Branch',
    'selected'   => 'Selected Members',
];

/** The first line of a message, for the grey preview under a title. */
function ann_snippet(string $message, int $len = 120): string
{
    $first = trim(strtok($message, "\n"));
    return mb_strlen($first) > $len ? mb_substr($first, 0, $len - 1) . '…' : $first;
}

$rows = [];
if ($has_module && $can_view) {
    foreach ($announcements as $a) {
        $t = $type_by_key[$a['type']] ?? null;

        /* Published and scheduled notices each have a date; a draft has
           neither, and the calendar simply has nothing to show for it. */
        $published_on = $a['days_ago'] !== null
            ? date('Y-m-d', strtotime('-' . (int) $a['days_ago'] . ' days')) : null;
        $scheduled_on = isset($a['scheduled_at']) ? substr($a['scheduled_at'], 0, 10) : null;

        $branches_named = [];
        foreach (($a['branch_ids'] ?? []) ?: [] as $bid) {
            $b = get_branch($bid);
            if ($b) { $branches_named[] = $b; }
        }

        $rows[] = $a + [
            'type_name'    => $t['name']   ?? $a['type'],
            'type_icon'    => $t['icon']   ?? 'fa-circle-info',
            'type_colour'  => $t['colour'] ?? '#662F97',
            'status_label' => $STATUS[$a['status']]['label'],
            'status_icon'  => $STATUS[$a['status']]['icon'],
            'published_on' => $published_on,
            'scheduled_on' => $scheduled_on,
            'calendar_on'  => $published_on ?: $scheduled_on,
            'snippet'      => ann_snippet($a['message']),
            'branches'     => $branches_named,
            /* What share of the intended audience actually opened it. */
            'reach'        => $a['recipients'] > 0 ? min(100, ($a['views'] / $a['recipients']) * 100) : 0.0,
            'scheduled_at' => $a['scheduled_at'] ?? null,
        ];
    }
}

/* A branch-scope user sees only what was addressed to their branch, plus
   anything sent to the whole organisation. */
if ($scoped_branch) {
    $rows = array_values(array_filter($rows, static function ($r) use ($scoped_branch) {
        if (!$r['branch_ids']) { return true; }
        return in_array((int) $scoped_branch['id'], array_map('intval', $r['branch_ids']), true);
    }));
}

/* Pinned first, in their display order, then newest. */
usort($rows, static function ($a, $b) {
    if ($a['pinned'] !== $b['pinned']) { return $b['pinned'] <=> $a['pinned']; }
    if ($a['pinned']) { return $a['pin_order'] <=> $b['pin_order']; }
    $ad = $a['calendar_on'] ?? '0000-00-00';
    $bd = $b['calendar_on'] ?? '0000-00-00';
    return strcmp($bd, $ad);
});

/* ── the four tiles, each of which is also a filter ── */
$counts = ['published' => 0, 'scheduled' => 0, 'draft' => 0, 'expired' => 0];
$views_total = 0;
foreach ($rows as $r) {
    $counts[$r['status']] = ($counts[$r['status']] ?? 0) + 1;
    $views_total += (int) $r['views'];
}

$pinned    = array_values(array_filter($rows, static fn($r) => $r['pinned']));
$scheduled = array_values(array_filter($rows, static fn($r) => $r['status'] === 'scheduled'));
usort($scheduled, static fn($a, $b) => strcmp((string) $a['scheduled_at'], (string) $b['scheduled_at']));

$authors = array_values(array_unique(array_column($rows, 'author')));
sort($authors);

/* ── the calendar: whichever month is in view, and what falls in it ── */
$month = isset($_GET['m']) ? substr(preg_replace('/[^0-9\-]/', '', $_GET['m']), 0, 7) : date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) { $month = date('Y-m'); }
$month_ts    = strtotime($month . '-01');
$month_days  = (int) date('t', $month_ts);
$month_first = (int) date('N', $month_ts);          /* 1 = Monday */
$prev_month  = date('Y-m', strtotime('-1 month', $month_ts));
$next_month  = date('Y-m', strtotime('+1 month', $month_ts));

$by_day = [];
foreach ($rows as $r) {
    if (!$r['calendar_on']) { continue; }
    $by_day[$r['calendar_on']][] = $r;
}

$page_title = 'Announcements';
require __DIR__ . '/../components/header.php';
?>

<div class="page">

  <!-- ═════════════════════════════ PAGE HEADER ═════════════════════════════ -->
  <header class="page__head">
    <div>
      <nav class="crumbs" aria-label="Breadcrumb">
        <a href="<?= $base_url ?>index.php">Dashboard</a>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span>Communication</span>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span aria-current="page">Announcements</span>
      </nav>
      <div class="title-row">
        <h1 class="page__title">Announcements</h1>
        <?php if ($has_module && $can_view): ?>
          <span class="count-chip" data-count="<?= count($rows) ?>">0</span>
        <?php endif; ?>
      </div>
      <p class="page__sub">Publish notices to your church members.</p>
    </div>

    <?php if ($has_module && $can_view): ?>
      <div class="page__actions">
        <?php if ($can_manage): ?>
          <button class="btn" type="button" id="btnNew">
            <i class="fa-solid fa-plus" aria-hidden="true"></i> New Announcement
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
      </div>
    <?php endif; ?>
  </header>


<?php if (!$has_module): ?>

  <section class="panel">
    <div class="empty">
      <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-plug-circle-xmark"></i></span>
      <h3>The Communication module is switched off</h3>
      <p>Your church's plan does not include announcements. A church administrator can request it from the platform team.</p>
      <a class="btn" href="<?= $base_url ?>index.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Dashboard</a>
    </div>
  </section>

<?php elseif (!$can_view): ?>

  <section class="panel">
    <div class="empty">
      <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-lock"></i></span>
      <h3>You do not have access to announcements</h3>
      <p>Ask an administrator for the communication viewing permission.</p>
      <a class="btn" href="<?= $base_url ?>index.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Dashboard</a>
    </div>
  </section>

<?php else: ?>

  <?php if ($scoped_branch): ?>
    <div class="at-notice" role="note">
      <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
      <div class="at-notice__body">
        <strong>You are seeing <?= htmlspecialchars($scoped_branch['name']) ?> only</strong>
        <span>Notices addressed to the whole <?= htmlspecialchars(t('org_singular')) ?> appear here too. Anything you publish goes to your own <?= htmlspecialchars(mb_strtolower(t('branch_singular'))) ?>.</span>
      </div>
    </div>
  <?php endif; ?>

  <!-- ═════════════════════ STAT STRIP — each tile filters ═════════════════════ -->
  <section class="stat-strip" aria-label="Announcements at a glance">
    <?php
      $tiles = [
        ['published', 'Published',   $counts['published'], 'tone-green',  'fa-circle-check'],
        ['scheduled', 'Scheduled',   $counts['scheduled'], 'tone-amber',  'fa-clock'],
        ['draft',     'Drafts',      $counts['draft'],     'tone-grey',   'fa-pen-ruler'],
      ];
    ?>
    <?php foreach ($tiles as [$key, $label, $n, $tone, $icon]): ?>
      <button class="stat-tile stat-tile--btn" type="button" data-tile="<?= $key ?>" aria-pressed="false">
        <span class="stat-tile__icon <?= $tone ?>" aria-hidden="true"><i class="fa-solid <?= $icon ?>"></i></span>
        <span class="stat-tile__body">
          <span class="stat-tile__value"><span data-count="<?= $n ?>">0</span></span>
          <span class="stat-tile__label"><?= $label ?></span>
          <span class="stat-tile__hint">Click to filter</span>
        </span>
      </button>
    <?php endforeach; ?>

    <div class="stat-tile is-static">
      <span class="stat-tile__icon tone-blue" aria-hidden="true"><i class="fa-regular fa-eye"></i></span>
      <span class="stat-tile__body">
        <span class="stat-tile__value"><span data-count="<?= $views_total ?>">0</span></span>
        <span class="stat-tile__label">Total Views</span>
        <span class="stat-tile__hint">Across every published notice</span>
      </span>
    </div>
  </section>


  <!-- ══════════════════════════ VIEW SWITCHER ══════════════════════════ -->
  <div class="viewbar">
    <div class="svcviews" role="group" aria-label="How to show announcements">
      <button class="svcview is-on" type="button" data-view="cards" aria-pressed="true">
        <i class="fa-solid fa-grip" aria-hidden="true"></i> Cards
      </button>
      <button class="svcview" type="button" data-view="table" aria-pressed="false">
        <i class="fa-solid fa-table-list" aria-hidden="true"></i> Table
      </button>
      <button class="svcview" type="button" data-view="calendar" aria-pressed="false">
        <i class="fa-regular fa-calendar" aria-hidden="true"></i> Calendar
      </button>
    </div>
    <p class="viewbar__note" data-shown-note><?= count($rows) ?> announcement<?= count($rows) === 1 ? '' : 's' ?></p>
  </div>


  <!-- ══════════════════════════ FILTER BAR ══════════════════════════ -->
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
          <input class="input" type="search" id="fSearch" data-search placeholder="Title or message&hellip;" autocomplete="off">
          <button class="search-field__clear" type="button" data-search-clear hidden aria-label="Clear search">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
          </button>
        </div>
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
        <label for="fType">Type</label>
        <select class="select" id="fType" data-filter>
          <option value="">All</option>
          <?php foreach ($announcement_types as $t): ?>
            <option value="<?= htmlspecialchars($t['key']) ?>"><?= htmlspecialchars($t['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="fAudience">Audience</label>
        <select class="select" id="fAudience" data-filter>
          <option value="">All</option>
          <?php foreach ($AUDIENCES as $k => $lab): ?>
            <?php if ($k === 'branch' && !$branch_aware) { continue; } ?>
            <option value="<?= $k ?>"><?= htmlspecialchars($lab) ?></option>
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

      <div class="field field--wide">
        <label>Date range</label>
        <div class="daterange">
          <input class="input" type="date" id="fFrom" data-filter aria-label="From date">
          <span class="daterange__to" aria-hidden="true">&ndash;</span>
          <input class="input" type="date" id="fTo" data-filter aria-label="To date">
        </div>
      </div>

      <div class="field">
        <label for="fAuthor">Author</label>
        <select class="select" id="fAuthor" data-filter>
          <option value="">All</option>
          <?php foreach ($authors as $a): ?>
            <option value="<?= htmlspecialchars($a) ?>"><?= htmlspecialchars($a) ?></option>
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

  <!-- ═══════════════════════════ CARD GRID VIEW ═══════════════════════════ -->
  <!-- The wrapper is what the view switcher hides; inside it the skeleton and
       the real grid swap over on .is-loaded, the way the other pages do it. -->
  <div data-pane="cards" id="cardsPane">
    <div class="anngrid" data-skeleton>
      <?php for ($i = 0; $i < 6; $i++): ?>
        <div class="sk-card anncard--skel" aria-hidden="true">
          <span class="sk sk--pill"></span>
          <span class="sk sk--text" style="width:78%;display:block;margin-top:12px"></span>
          <span class="sk sk--line" style="width:92%"></span>
          <span class="sk sk--line" style="width:64%"></span>
          <span class="sk sk--line" style="width:40%;margin-top:16px"></span>
        </div>
      <?php endfor; ?>
    </div>

    <div class="anngrid stagger as-grid" data-content>
    <?php foreach ($rows as $r): ?>
      <article class="anncard" style="--c:<?= htmlspecialchars($r['type_colour']) ?>"
               data-row data-id="<?= (int) $r['id'] ?>"
               data-title="<?= htmlspecialchars(mb_strtolower($r['title'])) ?>"
               data-body="<?= htmlspecialchars(mb_strtolower($r['message'])) ?>"
               data-status="<?= htmlspecialchars($r['status']) ?>"
               data-type="<?= htmlspecialchars($r['type']) ?>"
               data-audience="<?= htmlspecialchars($r['audience_kind']) ?>"
               data-author="<?= htmlspecialchars($r['author']) ?>"
               data-date="<?= htmlspecialchars((string) $r['calendar_on']) ?>"
               <?php if ($show_branch && $r['branch_ids']): ?>data-branch="<?= htmlspecialchars(implode(',', $r['branch_ids'])) ?>"<?php endif; ?>>

        <span class="anncard__strip" aria-hidden="true"></span>

        <?php if ($r['pinned']): ?>
          <span class="anncard__pin" title="Pinned to the top"><i class="fa-solid fa-thumbtack" aria-hidden="true"></i></span>
        <?php endif; ?>

        <div class="anncard__body">
          <header class="anncard__top">
            <span class="tchip" style="--c:<?= htmlspecialchars($r['type_colour']) ?>">
              <i class="fa-solid <?= htmlspecialchars($r['type_icon']) ?>" aria-hidden="true"></i>
              <?= htmlspecialchars($r['type_name']) ?>
            </span>
            <span class="pill pill--an-<?= htmlspecialchars($r['status']) ?>">
              <i class="fa-solid <?= htmlspecialchars($r['status_icon']) ?>" aria-hidden="true"></i>
              <?= htmlspecialchars($r['status_label']) ?>
            </span>
          </header>

          <h3 class="anncard__title"><?= htmlspecialchars($r['title']) ?></h3>
          <p class="anncard__snip"><?= htmlspecialchars($r['snippet']) ?></p>

          <?php if ($r['image']): ?>
            <span class="annthumb" role="img" aria-label="<?= htmlspecialchars($r['image']) ?>">
              <i class="fa-regular fa-image" aria-hidden="true"></i>
              <span><?= htmlspecialchars($r['image']) ?></span>
            </span>
          <?php endif; ?>

          <span class="audchip">
            <i class="fa-solid fa-users" aria-hidden="true"></i>
            <?= htmlspecialchars($r['audience_label']) ?>
          </span>

          <div class="anncard__who">
            <?= mu_av($r['author'], 'sm') ?>
            <span class="anncard__whotext">
              <b><?= htmlspecialchars($r['author']) ?></b>
              <span>
                <?php if ($r['published_on']): ?>
                  <?= mu_date($r['published_on']) ?> &middot; <?= mu_ago((int) $r['days_ago']) ?>
                <?php elseif ($r['scheduled_at']): ?>
                  Goes out <?= mu_date($r['scheduled_on']) ?> at <?= substr($r['scheduled_at'], 11, 5) ?>
                <?php else: ?>
                  Not published
                <?php endif; ?>
              </span>
            </span>
          </div>

          <div class="anncard__stats">
            <span title="Views"><i class="fa-regular fa-eye" aria-hidden="true"></i> <?= number_format($r['views']) ?></span>
            <?php if ($r['sms']): ?>
              <span title="Delivered by SMS"><i class="fa-solid fa-comment-sms" aria-hidden="true"></i> <?= number_format($r['sms']['delivered']) ?></span>
            <?php endif; ?>
            <?php if ($r['email']): ?>
              <span title="Delivered by email"><i class="fa-regular fa-envelope" aria-hidden="true"></i> <?= number_format($r['email']['delivered']) ?></span>
            <?php endif; ?>
          </div>

          <div class="anncard__acts">
            <button class="btn btn--ghost btn--sm" type="button" data-open="<?= (int) $r['id'] ?>">
              <i class="fa-regular fa-eye" aria-hidden="true"></i> View
            </button>
            <?php if ($can_manage): ?>
              <button class="btn btn--ghost btn--sm" type="button" data-edit="<?= (int) $r['id'] ?>">
                <i class="fa-solid fa-pen" aria-hidden="true"></i> Edit
              </button>
              <button class="btn btn--ghost btn--sm" type="button"
                      data-toggle-pub="<?= (int) $r['id'] ?>">
                <i class="fa-solid <?= $r['status'] === 'published' ? 'fa-eye-slash' : 'fa-bullhorn' ?>" aria-hidden="true"></i>
                <?= $r['status'] === 'published' ? 'Unpublish' : 'Publish' ?>
              </button>
            <?php endif; ?>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- The illustrated nothing-here state, shared by the card and table views. -->
  <section class="panel" id="annEmpty" hidden>
    <div class="empty">
      <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-bullhorn"></i></span>
      <h3 data-empty-title>No announcements yet</h3>
      <p data-empty-body>
        When you publish a notice it appears here, and on the members' side of the app.
        Start with something small &mdash; this week's service times, or a thank-you.
      </p>
      <?php if ($can_manage): ?>
        <button class="btn" type="button" data-new-from-empty>
          <i class="fa-solid fa-plus" aria-hidden="true"></i> Create your first announcement
        </button>
      <?php endif; ?>
    </div>
  </section>

  <!-- ═════════════════════════════ TABLE VIEW ═════════════════════════════ -->
  <section class="panel" data-pane="table" hidden>
    <div class="dt-wrap">
      <table class="dt" id="annTable">
        <thead>
          <tr>
            <th style="width:34px"><input class="check" type="checkbox" data-check-all aria-label="Select all announcements"></th>
            <th style="width:40px">#</th>
            <th class="is-sortable" data-sort="title">Announcement <i class="fa-solid fa-sort sort" aria-hidden="true"></i></th>
            <th>Type</th>
            <th>Audience</th>
            <?php if ($show_branch): ?><th><?= htmlspecialchars(t('branch_singular')) ?></th><?php endif; ?>
            <th>Author</th>
            <th class="is-sortable" data-sort="date">Published <i class="fa-solid fa-sort sort" aria-hidden="true"></i></th>
            <th>Expires</th>
            <th class="is-sortable" data-sort="views" style="min-width:130px">Views <i class="fa-solid fa-sort sort" aria-hidden="true"></i></th>
            <th>Status</th>
            <th class="col-actions" style="text-align:right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $i => $r): ?>
            <tr data-row data-id="<?= (int) $r['id'] ?>"
                data-title="<?= htmlspecialchars(mb_strtolower($r['title'])) ?>"
                data-body="<?= htmlspecialchars(mb_strtolower($r['message'])) ?>"
                data-status="<?= htmlspecialchars($r['status']) ?>"
                data-type="<?= htmlspecialchars($r['type']) ?>"
                data-audience="<?= htmlspecialchars($r['audience_kind']) ?>"
                data-author="<?= htmlspecialchars($r['author']) ?>"
                data-date="<?= htmlspecialchars((string) $r['calendar_on']) ?>"
                data-views="<?= (int) $r['views'] ?>"
                <?php if ($show_branch && $r['branch_ids']): ?>data-branch="<?= htmlspecialchars(implode(',', $r['branch_ids'])) ?>"<?php endif; ?>>
              <td><input class="check" type="checkbox" data-check aria-label="Select <?= htmlspecialchars($r['title']) ?>"></td>
              <td class="num"><?= $i + 1 ?></td>
              <td>
                <span class="minirow minirow--tight">
                  <span class="catico" style="--c:<?= htmlspecialchars($r['type_colour']) ?>" aria-hidden="true">
                    <i class="fa-solid <?= htmlspecialchars($r['type_icon']) ?>"></i>
                  </span>
                  <span class="minirow__text">
                    <b class="anntitle">
                      <?php if ($r['pinned']): ?><i class="fa-solid fa-thumbtack pinmark" title="Pinned" aria-hidden="true"></i><?php endif; ?>
                      <?= htmlspecialchars($r['title']) ?>
                    </b>
                    <span><?= htmlspecialchars($r['snippet']) ?></span>
                  </span>
                </span>
              </td>
              <td>
                <span class="tchip" style="--c:<?= htmlspecialchars($r['type_colour']) ?>">
                  <i class="fa-solid <?= htmlspecialchars($r['type_icon']) ?>" aria-hidden="true"></i>
                  <?= htmlspecialchars($r['type_name']) ?>
                </span>
              </td>
              <td><?= htmlspecialchars($r['audience_label']) ?></td>
              <?php if ($show_branch): ?>
                <td>
                  <?php if (!$r['branches']): ?>
                    <span class="muted">Whole <?= htmlspecialchars(mb_strtolower(t('org_singular'))) ?></span>
                  <?php else: ?>
                    <?php foreach (array_slice($r['branches'], 0, 2) as $b): ?><?= mu_branch_chip($b) ?><?php endforeach; ?>
                    <?php if (count($r['branches']) > 2): ?><span class="muted">+<?= count($r['branches']) - 2 ?></span><?php endif; ?>
                  <?php endif; ?>
                </td>
              <?php endif; ?>
              <td>
                <span class="minirow minirow--tight">
                  <?= mu_av($r['author'], 'sm') ?>
                  <span class="minirow__text"><b><?= htmlspecialchars($r['author']) ?></b></span>
                </span>
              </td>
              <td class="nowrap">
                <?php if ($r['published_on']): ?>
                  <?= mu_date($r['published_on']) ?><span class="metaline"><?= mu_ago((int) $r['days_ago']) ?></span>
                <?php elseif ($r['scheduled_at']): ?>
                  <?= mu_date($r['scheduled_on']) ?><span class="metaline is-soon">Scheduled <?= substr($r['scheduled_at'], 11, 5) ?></span>
                <?php else: ?>
                  <span class="muted">&mdash;</span>
                <?php endif; ?>
              </td>
              <td class="nowrap">
                <?= $r['expires_on'] ? mu_date($r['expires_on']) : '<span class="muted">No expiry</span>' ?>
              </td>
              <td>
                <span class="cellbar">
                  <span class="cellbar__track"><span class="cellbar__fill" style="width:<?= round($r['reach'], 1) ?>%"></span></span>
                  <b><?= number_format($r['views']) ?></b>
                </span>
                <span class="metaline"><?= number_format($r['reach'], 0) ?>% of <?= number_format($r['recipients']) ?></span>
              </td>
              <td>
                <span class="pill pill--an-<?= htmlspecialchars($r['status']) ?>">
                  <i class="fa-solid <?= htmlspecialchars($r['status_icon']) ?>" aria-hidden="true"></i>
                  <?= htmlspecialchars($r['status_label']) ?>
                </span>
              </td>
              <td class="col-actions">
                <div class="rowacts">
                  <button class="iconbtn" type="button" data-open="<?= (int) $r['id'] ?>" aria-label="View <?= htmlspecialchars($r['title']) ?>">
                    <i class="fa-regular fa-eye" aria-hidden="true"></i>
                  </button>
                  <?php if ($can_manage): ?>
                    <button class="iconbtn" type="button" data-edit="<?= (int) $r['id'] ?>" aria-label="Edit <?= htmlspecialchars($r['title']) ?>">
                      <i class="fa-solid fa-pen" aria-hidden="true"></i>
                    </button>
                    <button class="iconbtn<?= $r['pinned'] ? ' is-on' : '' ?>" type="button" data-pin="<?= (int) $r['id'] ?>"
                            aria-pressed="<?= $r['pinned'] ? 'true' : 'false' ?>" aria-label="<?= $r['pinned'] ? 'Unpin' : 'Pin' ?> <?= htmlspecialchars($r['title']) ?>">
                      <i class="fa-solid fa-thumbtack" aria-hidden="true"></i>
                    </button>
                  <?php endif; ?>

                  <div class="drop" data-menu>
                    <button class="iconbtn" type="button" aria-haspopup="true" aria-expanded="false" data-menu-btn aria-label="More actions">
                      <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                    </button>
                    <div class="menu menu--end" data-menu-panel hidden>
                      <button class="menu__item" type="button" data-open="<?= (int) $r['id'] ?>"><i class="fa-regular fa-eye" aria-hidden="true"></i> View Details</button>
                      <?php if ($can_manage): ?>
                        <button class="menu__item" type="button" data-edit="<?= (int) $r['id'] ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</button>
                        <button class="menu__item" type="button" data-duplicate="<?= (int) $r['id'] ?>"><i class="fa-regular fa-copy" aria-hidden="true"></i> Duplicate</button>
                        <button class="menu__item" type="button" data-pin="<?= (int) $r['id'] ?>"><i class="fa-solid fa-thumbtack" aria-hidden="true"></i> <?= $r['pinned'] ? 'Unpin' : 'Pin to top' ?></button>
                        <?php if ($r['status'] !== 'published'): ?>
                          <button class="menu__item" type="button" data-publish="<?= (int) $r['id'] ?>"><i class="fa-solid fa-bullhorn" aria-hidden="true"></i> Publish Now</button>
                        <?php else: ?>
                          <button class="menu__item" type="button" data-unpublish="<?= (int) $r['id'] ?>"><i class="fa-solid fa-eye-slash" aria-hidden="true"></i> Unpublish</button>
                        <?php endif; ?>
                      <?php endif; ?>
                      <button class="menu__item" type="button" data-recipients="<?= (int) $r['id'] ?>"><i class="fa-solid fa-users" aria-hidden="true"></i> View Recipients</button>
                      <?php if ($can_manage): ?>
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
        <article class="pcard pcard--flat" data-card
                 data-title="<?= htmlspecialchars(mb_strtolower($r['title'])) ?>"
                 data-body="<?= htmlspecialchars(mb_strtolower($r['message'])) ?>"
                 data-status="<?= htmlspecialchars($r['status']) ?>"
                 data-type="<?= htmlspecialchars($r['type']) ?>"
                 data-audience="<?= htmlspecialchars($r['audience_kind']) ?>"
                 data-author="<?= htmlspecialchars($r['author']) ?>"
                 data-date="<?= htmlspecialchars((string) $r['calendar_on']) ?>"
                 <?php if ($show_branch && $r['branch_ids']): ?>data-branch="<?= htmlspecialchars(implode(',', $r['branch_ids'])) ?>"<?php endif; ?>>
          <header class="pcard__head">
            <span class="catico" style="--c:<?= htmlspecialchars($r['type_colour']) ?>" aria-hidden="true">
              <i class="fa-solid <?= htmlspecialchars($r['type_icon']) ?>"></i>
            </span>
            <span class="pcard__text">
              <span class="pcard__name"><?= htmlspecialchars($r['title']) ?></span>
              <span class="pcard__meta"><?= htmlspecialchars($r['type_name']) ?> &middot; <?= htmlspecialchars($r['audience_label']) ?></span>
            </span>
            <span class="pill pill--an-<?= htmlspecialchars($r['status']) ?>"><?= htmlspecialchars($r['status_label']) ?></span>
          </header>
          <dl class="pcard__dl">
            <div><dt>Author</dt><dd><?= htmlspecialchars($r['author']) ?></dd></div>
            <div><dt>Published</dt><dd><?= $r['published_on'] ? mu_date($r['published_on']) : ($r['scheduled_on'] ? 'Scheduled ' . mu_date($r['scheduled_on']) : '&mdash;') ?></dd></div>
            <div><dt>Expires</dt><dd><?= $r['expires_on'] ? mu_date($r['expires_on']) : 'No expiry' ?></dd></div>
            <div><dt>Views</dt><dd><?= number_format($r['views']) ?> &middot; <?= number_format($r['reach'], 0) ?>%</dd></div>
          </dl>
          <div class="pcard__acts">
            <button class="btn btn--ghost btn--sm" type="button" data-open="<?= (int) $r['id'] ?>"><i class="fa-regular fa-eye" aria-hidden="true"></i> View</button>
            <?php if ($can_manage): ?>
              <button class="btn btn--ghost btn--sm" type="button" data-edit="<?= (int) $r['id'] ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</button>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <footer class="tablefoot">
      <p data-count-note>Showing 1 to <?= count($rows) ?> of <?= count($rows) ?> announcements</p>
      <nav class="pager" aria-label="Pagination">
        <button class="pager__btn" type="button" disabled><i class="fa-solid fa-chevron-left" aria-hidden="true"></i> Previous</button>
        <span class="pager__pages">Page <b>1</b> of <b>1</b></span>
        <button class="pager__btn" type="button" disabled>Next <i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
      </nav>
    </footer>
  </section>

  <!-- ═══════════════════════════ CALENDAR VIEW ═══════════════════════════ -->
  <section class="panel" data-pane="calendar" hidden>
    <header class="calhead">
      <div class="calnav">
        <a class="iconbtn iconbtn--bordered" href="?m=<?= $prev_month ?>&amp;role=<?= urlencode($demo_role) ?>&amp;v=calendar"
           aria-label="Previous month"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></a>
        <h2 class="calhead__title"><?= date('F Y', $month_ts) ?></h2>
        <a class="iconbtn iconbtn--bordered" href="?m=<?= $next_month ?>&amp;role=<?= urlencode($demo_role) ?>&amp;v=calendar"
           aria-label="Next month"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
        <a class="btn btn--ghost btn--sm" href="?m=<?= date('Y-m') ?>&amp;role=<?= urlencode($demo_role) ?>&amp;v=calendar">Today</a>
      </div>
      <ul class="callegend">
        <?php foreach ($announcement_types as $t): ?>
          <li><span class="caldot" style="--c:<?= htmlspecialchars($t['colour']) ?>" aria-hidden="true"></span> <?= htmlspecialchars($t['name']) ?></li>
        <?php endforeach; ?>
        <li><span class="caldot caldot--future" aria-hidden="true"></span> Scheduled</li>
      </ul>
    </header>

    <div class="cal" role="grid" aria-label="Announcements for <?= date('F Y', $month_ts) ?>">
      <div class="cal__dow" role="row" aria-hidden="true">
        <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d): ?>
          <span><?= $d ?></span>
        <?php endforeach; ?>
      </div>

      <div class="cal__grid" role="rowgroup">
        <?php for ($pad = 1; $pad < $month_first; $pad++): ?>
          <span class="cal__cell is-empty" aria-hidden="true"></span>
        <?php endfor; ?>

        <?php for ($d = 1; $d <= $month_days; $d++): ?>
          <?php
            $iso   = sprintf('%s-%02d', $month, $d);
            $items = $by_day[$iso] ?? [];
            $is_today = $iso === $today;
          ?>
          <?php if (!$items): ?>
            <span class="cal__cell<?= $is_today ? ' is-today' : '' ?>" role="gridcell">
              <span class="cal__num"><?= $d ?></span>
            </span>
          <?php else: ?>
            <button class="cal__cell has-items<?= $is_today ? ' is-today' : '' ?>" type="button"
                    role="gridcell" data-day="<?= $iso ?>"
                    aria-label="<?= mu_date($iso) ?>: <?= count($items) ?> announcement<?= count($items) === 1 ? '' : 's' ?>">
              <span class="cal__num"><?= $d ?></span>
              <span class="cal__dots">
                <?php foreach (array_slice($items, 0, 4) as $it): ?>
                  <span class="caldot<?= $it['status'] === 'scheduled' ? ' caldot--future' : '' ?>"
                        style="--c:<?= htmlspecialchars($it['type_colour']) ?>"
                        title="<?= htmlspecialchars($it['title']) ?>"></span>
                <?php endforeach; ?>
                <?php if (count($items) > 4): ?><span class="cal__more">+<?= count($items) - 4 ?></span><?php endif; ?>
              </span>
            </button>
          <?php endif; ?>
        <?php endfor; ?>
      </div>
    </div>
  </section>


  <!-- ═════════════════════════════ BOTTOM ROW ═════════════════════════════ -->
  <div class="chartgrid chartgrid--2" style="margin-top:16px">
    <section class="panel">
      <header class="chartcard__head">
        <div>
          <h2>Pinned Announcements</h2>
          <p>Shown at the top of the members' list, in this order.</p>
        </div>
      </header>
      <?php if (!$pinned): ?>
        <div class="empty">
          <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-thumbtack"></i></span>
          <h3>Nothing is pinned</h3>
          <p>Pin a notice to hold it at the top of the members' list until you unpin it.</p>
        </div>
      <?php else: ?>
        <ul class="pinlist" data-pinlist>
          <?php foreach ($pinned as $r): ?>
            <li class="pinlist__row" data-pin-row="<?= (int) $r['id'] ?>">
              <span class="pinlist__grip" title="Drag to reorder" aria-hidden="true"><i class="fa-solid fa-grip-vertical"></i></span>
              <span class="catico" style="--c:<?= htmlspecialchars($r['type_colour']) ?>" aria-hidden="true">
                <i class="fa-solid <?= htmlspecialchars($r['type_icon']) ?>"></i>
              </span>
              <span class="pinlist__text">
                <b><?= htmlspecialchars($r['title']) ?></b>
                <span><?= htmlspecialchars($r['type_name']) ?> &middot; <?= $r['published_on'] ? mu_ago((int) $r['days_ago']) : 'not published' ?></span>
              </span>
              <span class="pinlist__move">
                <button class="iconbtn" type="button" data-pin-move="up" aria-label="Move up"><i class="fa-solid fa-chevron-up" aria-hidden="true"></i></button>
                <button class="iconbtn" type="button" data-pin-move="down" aria-label="Move down"><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></button>
                <?php if ($can_manage): ?>
                  <button class="iconbtn" type="button" data-pin="<?= (int) $r['id'] ?>" aria-label="Unpin <?= htmlspecialchars($r['title']) ?>">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                  </button>
                <?php endif; ?>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="panel">
      <header class="chartcard__head">
        <div>
          <h2>Scheduled</h2>
          <p><?= count($scheduled) ?> waiting to go out.</p>
        </div>
      </header>
      <?php if (!$scheduled): ?>
        <div class="empty">
          <span class="empty__icon" aria-hidden="true"><i class="fa-regular fa-clock"></i></span>
          <h3>Nothing is scheduled</h3>
          <p>Notices set to publish later will queue up here.</p>
        </div>
      <?php else: ?>
        <ul class="pinlist">
          <?php foreach ($scheduled as $r): ?>
            <?php $days = (int) round((strtotime($r['scheduled_on']) - strtotime($today)) / 86400); ?>
            <li class="pinlist__row">
              <span class="catico" style="--c:<?= htmlspecialchars($r['type_colour']) ?>" aria-hidden="true">
                <i class="fa-solid <?= htmlspecialchars($r['type_icon']) ?>"></i>
              </span>
              <span class="pinlist__text">
                <b><?= htmlspecialchars($r['title']) ?></b>
                <span>
                  <?= mu_date($r['scheduled_on'], 'D d M') ?> at <?= substr($r['scheduled_at'], 11, 5) ?>
                  &middot; <?= $days <= 0 ? 'today' : 'in ' . $days . ' day' . ($days === 1 ? '' : 's') ?>
                </span>
              </span>
              <?php if ($can_manage): ?>
                <button class="btn btn--ghost btn--sm" type="button" data-publish="<?= (int) $r['id'] ?>">
                  <i class="fa-solid fa-bullhorn" aria-hidden="true"></i> Publish Now
                </button>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>

  <!-- ── bulk actions, floating ── -->
  <?php if ($can_manage): ?>
    <div class="bulkbar" id="bulkBar" hidden>
      <span class="bulkbar__count"><b data-bulk-count>0</b> selected</span>
      <span class="bulkbar__sep" aria-hidden="true"></span>
      <button class="bulkbar__btn" type="button" id="bulkPublish"><i class="fa-solid fa-bullhorn" aria-hidden="true"></i> Publish</button>
      <button class="bulkbar__btn" type="button" id="bulkUnpublish"><i class="fa-solid fa-eye-slash" aria-hidden="true"></i> Unpublish</button>
      <button class="bulkbar__btn" type="button" id="bulkPin"><i class="fa-solid fa-thumbtack" aria-hidden="true"></i> Pin</button>
      <button class="bulkbar__btn is-danger" type="button" id="bulkDelete"><i class="fa-regular fa-trash-can" aria-hidden="true"></i> Delete</button>
      <span class="bulkbar__sep" aria-hidden="true"></span>
      <button class="bulkbar__close" type="button" id="bulkClose" aria-label="Clear selection">
        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
      </button>
    </div>
  <?php endif; ?>

<?php endif; ?>
</div>

<?php if ($has_module && $can_view): ?>

<div class="drawer-scrim" data-drawer-scrim hidden></div>

<!-- ══════════════════════ ANNOUNCEMENT DETAIL DRAWER ══════════════════════ -->
<aside class="drawer drawer--wide" id="annDrawer" role="dialog" aria-modal="true" aria-labelledby="dTitle" hidden>
  <header class="drawer__head">
    <span class="catico catico--lg" data-d-ico aria-hidden="true"><i class="fa-solid fa-circle-info"></i></span>
    <div class="drawer__title">
      <h2 id="dTitle">Announcement</h2>
      <p><span data-d-type>&mdash;</span></p>
    </div>
    <button class="iconbtn" type="button" data-drawer-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
  </header>

  <div class="drawer__body">
    <div class="annhead">
      <span class="pill" data-d-status>Status</span>
      <span class="annhead__pin" data-d-pin hidden><i class="fa-solid fa-thumbtack" aria-hidden="true"></i> Pinned</span>
    </div>

    <h3 class="annfull__title" data-d-title>&mdash;</h3>

    <div class="annthumb annthumb--lg" data-d-image hidden>
      <i class="fa-regular fa-image" aria-hidden="true"></i>
      <span data-d-imagecap>Attached image</span>
    </div>

    <div class="annfull__body" data-d-message></div>

    <dl class="deflist">
      <div><dt>Audience</dt><dd data-d-audience>&mdash;</dd></div>
      <div><dt>Author</dt><dd data-d-author>&mdash;</dd></div>
      <div><dt>Published</dt><dd data-d-published>&mdash;</dd></div>
      <div><dt>Expires</dt><dd data-d-expires>&mdash;</dd></div>
      <?php if ($show_branch): ?><div><dt><?= htmlspecialchars(t('branch_singular')) ?></dt><dd data-d-branch>&mdash;</dd></div><?php endif; ?>
      <div><dt>Comments</dt><dd data-d-comments>&mdash;</dd></div>
    </dl>

    <p class="minilist__head">How far it reached</p>
    <div class="reachgrid" data-d-reach></div>

    <p class="minilist__head">Recipients</p>
    <p class="drawer__prose" data-d-recipients>&mdash;</p>
    <button class="btn btn--ghost btn--sm" type="button" data-d-recipbtn>
      <i class="fa-solid fa-users" aria-hidden="true"></i> View the full list
    </button>
  </div>

  <footer class="drawer__foot drawer__foot--wrap">
    <?php if ($can_manage): ?>
      <button class="btn" type="button" id="dEdit"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</button>
      <button class="btn btn--ghost" type="button" id="dDuplicate"><i class="fa-regular fa-copy" aria-hidden="true"></i> Duplicate</button>
      <button class="btn btn--ghost" type="button" id="dUnpublish"><i class="fa-solid fa-eye-slash" aria-hidden="true"></i> Unpublish</button>
    <?php else: ?>
      <button class="btn btn--ghost" type="button" data-drawer-close>Close</button>
    <?php endif; ?>
  </footer>
</aside>


<!-- ══════════════════════ ONE DAY FROM THE CALENDAR ══════════════════════ -->
<aside class="drawer" id="dayDrawer" role="dialog" aria-modal="true" aria-labelledby="dayTitle" hidden>
  <header class="drawer__head">
    <span class="catico catico--lg" style="--c:#662F97" aria-hidden="true"><i class="fa-regular fa-calendar"></i></span>
    <div class="drawer__title">
      <h2 id="dayTitle">Day</h2>
      <p><span data-day-count>&mdash;</span></p>
    </div>
    <button class="iconbtn" type="button" data-drawer-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
  </header>
  <div class="drawer__body">
    <div class="minilist" data-day-list></div>
  </div>
  <footer class="drawer__foot">
    <button class="btn btn--ghost" type="button" data-drawer-close>Close</button>
  </footer>
</aside>


<?php if ($can_manage): ?>
<!-- ══════════════════════ NEW / EDIT ANNOUNCEMENT ══════════════════════ -->
<div class="modal-scrim" id="modalEdit" hidden>
  <div class="modal modal--full" role="dialog" aria-modal="true" aria-labelledby="edTitle">
    <header class="modal__head">
      <h2 id="edTitle">New Announcement</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>

    <div class="modal__body">
      <div class="edlayout">
        <div class="edform">

          <section class="edsec">
            <h3 class="edsec__head">The notice</h3>
            <div class="field">
              <label for="edSubject">Title <span class="req">*</span></label>
              <input class="input" type="text" id="edSubject" placeholder="What is this about?" autocomplete="off">
            </div>

            <div class="field">
              <label for="edMessage">Message <span class="req">*</span></label>
              <!-- Visual only: the toolbar wraps the selection, nothing more. -->
              <div class="fmtbar" role="toolbar" aria-label="Formatting" aria-controls="edMessage">
                <button class="fmtbar__btn" type="button" data-fmt="bold" aria-label="Bold"><i class="fa-solid fa-bold" aria-hidden="true"></i></button>
                <button class="fmtbar__btn" type="button" data-fmt="italic" aria-label="Italic"><i class="fa-solid fa-italic" aria-hidden="true"></i></button>
                <button class="fmtbar__btn" type="button" data-fmt="link" aria-label="Insert a link"><i class="fa-solid fa-link" aria-hidden="true"></i></button>
                <button class="fmtbar__btn" type="button" data-fmt="list" aria-label="Bulleted list"><i class="fa-solid fa-list-ul" aria-hidden="true"></i></button>
              </div>
              <textarea class="textarea" id="edMessage" rows="7" placeholder="Write it the way you would say it from the front."></textarea>
            </div>

            <div class="field">
              <label id="edTypeLbl">Type</label>
              <div class="iconcards" role="radiogroup" aria-labelledby="edTypeLbl">
                <?php foreach ($announcement_types as $k => $t): ?>
                  <label class="iconcard" style="--c:<?= htmlspecialchars($t['colour']) ?>">
                    <input type="radio" name="edType" value="<?= htmlspecialchars($t['key']) ?>" <?= $k === 0 ? 'checked' : '' ?>>
                    <span class="iconcard__box">
                      <i class="fa-solid <?= htmlspecialchars($t['icon']) ?>" aria-hidden="true"></i>
                      <span><?= htmlspecialchars($t['name']) ?></span>
                    </span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="field">
              <label for="edImage">Image</label>
              <label class="dropzone" for="edImage" id="edDrop">
                <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
                <span><strong>Drop an image here</strong></span>
                <span class="hint" style="margin:0">or click to choose. JPG or PNG, up to 3&nbsp;MB. Optional.</span>
              </label>
              <input class="offscreen" type="file" id="edImage" accept="image/png,image/jpeg">
              <div class="filepreview" data-ed-file hidden>
                <span class="filepreview__ico" aria-hidden="true"><i class="fa-regular fa-image"></i></span>
                <span class="filepreview__text"><b data-ed-filename>image.jpg</b><span data-ed-filesize>&mdash;</span></span>
                <button class="iconbtn" type="button" id="edDropFile" aria-label="Remove this image"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>
              </div>
            </div>
          </section>

          <section class="edsec">
            <h3 class="edsec__head">Who it goes to</h3>
            <div class="field">
              <label id="edAudLbl">Audience</label>
              <div class="radio-cards" role="radiogroup" aria-labelledby="edAudLbl">
                <?php
                  $aud_opts = [['all', 'All Members', 'fa-users']];
                  if (mu_mod('departments')) { $aud_opts[] = ['department', 'By Department', 'fa-people-group']; }
                  if (mu_mod('cell_groups')) { $aud_opts[] = ['cell', 'By Cell Group', 'fa-house-user']; }
                  if ($branch_aware && !$scoped_branch) { $aud_opts[] = ['branch', 'By ' . t('branch_singular'), 'fa-code-branch']; }
                  $aud_opts[] = ['selected', 'Selected Members', 'fa-user-check'];
                ?>
                <?php foreach ($aud_opts as $i => [$k, $lab, $ic]): ?>
                  <label class="rcard">
                    <input type="radio" name="edAud" value="<?= $k ?>" <?= $i === 0 ? 'checked' : '' ?>>
                    <span class="rcard__box"><i class="fa-solid <?= $ic ?>" aria-hidden="true"></i><span><strong><?= htmlspecialchars($lab) ?></strong></span></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- One picker per audience; only the matching one is revealed. -->
            <?php
              $pickers = [];
              if (mu_mod('departments')) {
                  $pickers['department'] = array_values(array_filter(array_unique(array_column($members_demo, 'department'))));
              }
              if (mu_mod('cell_groups')) {
                  $pickers['cell'] = array_values(array_filter(array_unique(array_column($members_demo, 'cell_group'))));
              }
              if ($branch_aware && !$scoped_branch) {
                  $pickers['branch'] = array_column($branch_options, 'name');
              }
              $pickers['selected'] = array_column($members_demo, 'name');
            ?>
            <?php foreach ($pickers as $kind => $items): ?>
              <div class="field picker" data-picker="<?= $kind ?>" hidden>
                <label for="pick-<?= $kind ?>">Choose who</label>
                <div class="search-field">
                  <i class="fa-solid fa-magnifying-glass search-field__icon" aria-hidden="true"></i>
                  <input class="input" type="search" id="pick-<?= $kind ?>" data-picker-search placeholder="Search&hellip;" autocomplete="off">
                </div>
                <div class="pickbox">
                  <?php foreach ($items as $n => $item): ?>
                    <label class="pickrow2">
                      <input type="checkbox" data-pick value="<?= htmlspecialchars($item) ?>"
                             data-size="<?= 6 + (crc32($item . $kind) % 90) ?>">
                      <span><?= htmlspecialchars($item) ?></span>
                      <b><?= 6 + (crc32($item . $kind) % 90) ?></b>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>

            <p class="reachnote">
              <i class="fa-solid fa-users" aria-hidden="true"></i>
              This will reach <b data-recip-count>462</b> people.
            </p>
          </section>

          <section class="edsec">
            <h3 class="edsec__head">How it goes out</h3>
            <label class="switchrow" for="edPortal">
              <span class="switch"><input type="checkbox" id="edPortal" checked disabled><span class="switch__track" aria-hidden="true"></span></span>
              <span class="switchrow__text"><b>Show in the member portal</b><small>Always on. This is what an announcement is.</small></span>
            </label>
            <label class="switchrow" for="edSms">
              <span class="switch"><input type="checkbox" id="edSms"><span class="switch__track" aria-hidden="true"></span></span>
              <span class="switchrow__text"><b>Also send by SMS</b><small>Costs money. Worth it for anything urgent.</small></span>
            </label>
            <div class="smsnote" data-sms-note hidden>
              <span><b data-sms-chars>0</b> characters &middot; <b data-sms-parts>1</b> message part<span data-sms-plural></span></span>
              <span>Estimated <b data-sms-recip>462</b> recipients</span>
            </div>
            <label class="switchrow" for="edEmail">
              <span class="switch"><input type="checkbox" id="edEmail"><span class="switch__track" aria-hidden="true"></span></span>
              <span class="switchrow__text"><b>Also send by email</b><small>Only reaches members with an address on file.</small></span>
            </label>
          </section>

          <section class="edsec">
            <h3 class="edsec__head">When</h3>
            <div class="field">
              <label id="edWhenLbl">Publishing</label>
              <div class="radio-cards" role="radiogroup" aria-labelledby="edWhenLbl">
                <label class="rcard">
                  <input type="radio" name="edWhen" value="now" checked>
                  <span class="rcard__box"><i class="fa-solid fa-bolt" aria-hidden="true"></i><span><strong>Publish immediately</strong></span></span>
                </label>
                <label class="rcard">
                  <input type="radio" name="edWhen" value="later">
                  <span class="rcard__box"><i class="fa-regular fa-clock" aria-hidden="true"></i><span><strong>Schedule for later</strong></span></span>
                </label>
              </div>
            </div>

            <div class="field" data-when-later hidden>
              <label>Send at</label>
              <div class="daterange">
                <input class="input" type="date" id="edDate" value="<?= date('Y-m-d') ?>" aria-label="Date">
                <input class="input" type="time" id="edTime" value="07:00" aria-label="Time">
              </div>
            </div>

            <label class="switchrow" for="edHasExpiry">
              <span class="switch"><input type="checkbox" id="edHasExpiry"><span class="switch__track" aria-hidden="true"></span></span>
              <span class="switchrow__text"><b>Set an expiry date</b><small>It drops off the members' list on that day.</small></span>
            </label>
            <div class="field" data-expiry hidden>
              <label for="edExpiry">Expires on</label>
              <input class="input" type="date" id="edExpiry">
            </div>
          </section>

          <section class="edsec">
            <h3 class="edsec__head">Options</h3>
            <label class="switchrow" for="edPin">
              <span class="switch"><input type="checkbox" id="edPin"><span class="switch__track" aria-hidden="true"></span></span>
              <span class="switchrow__text"><b>Pin to top</b><small>Holds it above everything else until unpinned.</small></span>
            </label>
            <label class="switchrow" for="edComments">
              <span class="switch"><input type="checkbox" id="edComments" checked><span class="switch__track" aria-hidden="true"></span></span>
              <span class="switchrow__text"><b>Allow comments</b><small>Members can reply underneath it.</small></span>
            </label>
          </section>
        </div>

        <!-- What a member will actually see. Updates as the form is filled in. -->
        <aside class="edpreview">
          <p class="edpreview__label"><i class="fa-solid fa-mobile-screen" aria-hidden="true"></i> How members will see it</p>
          <div class="phone">
            <article class="anncard anncard--preview" style="--c:#662F97" data-pv-card>
              <span class="anncard__strip" aria-hidden="true"></span>
              <span class="anncard__pin" data-pv-pin hidden><i class="fa-solid fa-thumbtack" aria-hidden="true"></i></span>
              <div class="anncard__body">
                <header class="anncard__top">
                  <span class="tchip" data-pv-type style="--c:#662F97">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i> General
                  </span>
                </header>
                <h3 class="anncard__title" data-pv-title>Your title will appear here</h3>
                <p class="anncard__snip" data-pv-body>And the message underneath it, exactly as members will read it.</p>
                <span class="annthumb" data-pv-image hidden>
                  <i class="fa-regular fa-image" aria-hidden="true"></i><span data-pv-imagecap>Image</span>
                </span>
                <span class="audchip" data-pv-aud><i class="fa-solid fa-users" aria-hidden="true"></i> All Members</span>
                <div class="anncard__who">
                  <?= mu_av($user['name'], 'sm') ?>
                  <span class="anncard__whotext">
                    <b><?= htmlspecialchars($user['name']) ?></b>
                    <span data-pv-when>Just now</span>
                  </span>
                </div>
              </div>
            </article>
          </div>
        </aside>
      </div>
    </div>

    <footer class="modal__foot modal__foot--split">
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <div class="modal__footgroup">
        <button class="btn btn--ghost" type="button" id="edDraft"><i class="fa-solid fa-pen-ruler" aria-hidden="true"></i> Save as Draft</button>
        <button class="btn" type="button" id="edPublish"><i class="fa-solid fa-bullhorn" aria-hidden="true"></i> Publish</button>
      </div>
    </footer>
  </div>
</div>


<!-- ══════════════════════════════ DELETE ══════════════════════════════ -->
<div class="modal-scrim" id="modalDelete" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="dlTitle">
    <header class="modal__head">
      <h2 id="dlTitle">Delete Announcement</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>
    <div class="modal__body">
      <div class="at-notice at-notice--danger" role="note">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        <div class="at-notice__body">
          <strong>This cannot be undone</strong>
          <span>Deleting a notice removes it from the members' list and takes its view and delivery figures with it. Unpublishing keeps the record.</span>
        </div>
      </div>
      <p class="delwhat">&ldquo;<b data-dl-title>this announcement</b>&rdquo;</p>
    </div>
    <footer class="modal__foot">
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <button class="btn btn--danger" type="button" id="dlGo"><i class="fa-regular fa-trash-can" aria-hidden="true"></i> Delete</button>
    </footer>
  </div>
</div>
<?php endif; ?>


<!-- ══════════════════════════ VIEW RECIPIENTS ══════════════════════════ -->
<div class="modal-scrim" id="modalRecipients" hidden>
  <div class="modal modal--wide" role="dialog" aria-modal="true" aria-labelledby="rcTitle">
    <header class="modal__head">
      <h2 id="rcTitle">Recipients</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>
    <div class="modal__body">
      <p class="modal__hint" data-rc-for>&mdash;</p>
      <div class="field">
        <div class="search-field">
          <i class="fa-solid fa-magnifying-glass search-field__icon" aria-hidden="true"></i>
          <input class="input" type="search" id="rcSearch" placeholder="Search recipients&hellip;" autocomplete="off" aria-label="Search recipients">
        </div>
      </div>
      <div class="dt-wrap">
        <table class="dt" id="rcTable">
          <thead>
            <tr><th>Member</th><th>Channel</th><th>Status</th></tr>
          </thead>
          <tbody data-rc-rows></tbody>
        </table>
      </div>
      <p class="dt-empty" data-rc-empty hidden><i class="fa-regular fa-face-frown" aria-hidden="true"></i> Nobody matches that search.</p>
    </div>
    <footer class="modal__foot">
      <button class="btn btn--ghost" type="button" data-close>Close</button>
      <button class="btn btn--ghost" type="button" data-toast="Recipient list exported"><i class="fa-solid fa-download" aria-hidden="true"></i> Export list</button>
    </footer>
  </div>
</div>

<?php endif; ?>

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
/* What the drawers and modals read. LATER: these become endpoints. */
$JS_ROWS = [];
foreach ($rows as $r) {
    $JS_ROWS[] = [
        'id' => $r['id'], 'title' => $r['title'], 'message' => $r['message'],
        'type' => $r['type'], 'typeName' => $r['type_name'],
        'typeIcon' => $r['type_icon'], 'typeColour' => $r['type_colour'],
        'status' => $r['status'], 'statusLabel' => $r['status_label'], 'statusIcon' => $r['status_icon'],
        'audience' => $r['audience_label'], 'audienceKind' => $r['audience_kind'],
        'recipients' => (int) $r['recipients'], 'author' => $r['author'],
        'published' => $r['published_on'] ? mu_date($r['published_on']) : null,
        'ago' => $r['days_ago'] !== null ? mu_ago((int) $r['days_ago']) : null,
        'scheduled' => $r['scheduled_at'],
        'expires' => $r['expires_on'] ? mu_date($r['expires_on']) : null,
        'pinned' => (bool) $r['pinned'], 'image' => $r['image'],
        'views' => (int) $r['views'], 'reach' => round($r['reach'], 1),
        'sms' => $r['sms'], 'email' => $r['email'],
        'comments' => (bool) $r['allow_comments'],
        'branches' => array_column($r['branches'], 'name'),
    ];
}
$day_index = [];
foreach ($by_day as $iso => $items) {
    $day_index[$iso] = array_map(static fn($r) => (int) $r['id'], $items);
}

$JS_MEMBERS = array_map(static fn($m) => ['name' => $m['name'], 'no' => $m['member_no'], 'email' => $m['email'] ?? ''], $members_demo);
?>
<script>
(function () {
  'use strict';

  var $  = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

  var ROWS = <?= json_encode($JS_ROWS, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  var MEMBERS = <?= json_encode($JS_MEMBERS, JSON_UNESCAPED_UNICODE) ?>;
  var TOTAL_MEMBERS = <?= (int) $announcement_audience_total ?>;
  /* Never the word "organisation" — the preset decides what it is called. */
  var WHOLE_ORG = <?= json_encode('Whole ' . mb_strtolower(t('org_singular'))) ?>;
  /* Which notices fall on which day — built by PHP from the same value the
     calendar cells were rendered from, so the two can never disagree. */
  var DAY_INDEX = <?= json_encode($day_index) ?>;
  var byId = {};
  ROWS.forEach(function (r) { byId[r.id] = r; });

  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
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

  /* ────────────── the cards arrive once the skeleton has had its moment ────────────── */
  /* .is-loaded on the pane is what swaps the skeleton for the real grid and
     starts the stagger; the CSS owns the switch, not this script. */
  var cardsPane = $('#cardsPane');
  setTimeout(function () {
    if (cardsPane) { cardsPane.classList.add('is-loaded'); }
  }, reduced ? 0 : 420);

  /* ─────────────────────── the numbers count up ─────────────────────── */
  /* Scoped to the strip and the header chip. An unscoped [data-count] would
     also match anything using the attribute as data and overwrite it. */
  $$('.stat-tile [data-count], .count-chip[data-count]').forEach(function (el) {
    var target = parseFloat(el.getAttribute('data-count')) || 0;
    if (reduced) { el.textContent = target.toLocaleString(); return; }
    var t0 = null, dur = 800;
    function step(ts) {
      if (t0 === null) { t0 = ts; }
      var k = Math.min(1, (ts - t0) / dur);
      el.textContent = Math.round(target * (1 - Math.pow(1 - k, 3))).toLocaleString();
      if (k < 1) { requestAnimationFrame(step); }
    }
    requestAnimationFrame(step);
  });

  /* ─────────────────────────── the three views ─────────────────────────── */
  function setView(which) {
    $$('[data-view]').forEach(function (b) {
      var on = b.getAttribute('data-view') === which;
      b.classList.toggle('is-on', on);
      b.setAttribute('aria-pressed', String(on));
    });
    $$('[data-pane]').forEach(function (pane) {
      pane.hidden = pane.getAttribute('data-pane') !== which;
    });
    var e = $('#annEmpty');
    if (e && which === 'calendar') { e.hidden = true; } else { apply(); }
  }
  $$('[data-view]').forEach(function (b) {
    b.addEventListener('click', function () { setView(b.getAttribute('data-view')); });
  });
  /* Landing straight on the calendar after a month step. */
  if (new URLSearchParams(window.location.search).get('v') === 'calendar') {
    setTimeout(function () { setView('calendar'); }, reduced ? 0 : 440);
  }

  /* ═══════════════════════════ filtering ═══════════════════════════ */
  var search  = $('#fSearch');
  var chipBox = $('[data-filter-chips]');
  var emptyEl = $('#annEmpty');
  var tileFilter = '';

  var FILTER_LABEL = {
    fStatus: 'Status', fType: 'Type', fAudience: 'Audience',
    fFrom: 'From', fTo: 'To', fAuthor: 'Author'<?php if ($show_branch): ?>,
    fBranch: '<?= addslashes(t('branch_singular')) ?>'<?php endif; ?>
  };

  function activeFilters() {
    var f = { q: (search && search.value || '').trim().toLowerCase(), tile: tileFilter };
    $$('[data-filter]').forEach(function (el) { f[el.id] = el.value; });
    return f;
  }

  function matches(el, f) {
    if (f.q) {
      var hay = (el.getAttribute('data-title') || '') + ' ' + (el.getAttribute('data-body') || '');
      if (hay.indexOf(f.q) === -1) { return false; }
    }
    var status = el.getAttribute('data-status');
    if (f.tile && status !== f.tile) { return false; }
    if (f.fStatus   && status !== f.fStatus) { return false; }
    if (f.fType     && el.getAttribute('data-type')     !== f.fType)     { return false; }
    if (f.fAudience && el.getAttribute('data-audience') !== f.fAudience) { return false; }
    if (f.fAuthor   && el.getAttribute('data-author')   !== f.fAuthor)   { return false; }

    /* A draft has no date at all, so a date range excludes it rather than
       silently treating the blank as a match. */
    var d = el.getAttribute('data-date');
    if (f.fFrom && (!d || d < f.fFrom)) { return false; }
    if (f.fTo   && (!d || d > f.fTo))   { return false; }

    <?php if ($show_branch): ?>
    if (f.fBranch) {
      var bs = (el.getAttribute('data-branch') || '').split(',');
      if (bs.indexOf(f.fBranch) === -1) { return false; }
    }
    <?php endif; ?>
    return true;
  }

  function paintChips(f) {
    if (!chipBox) { return; }
    chipBox.innerHTML = '';
    var live = [];
    if (f.q) { live.push(['q', 'Search', f.q]); }
    if (f.tile) { live.push(['tile', 'Showing', f.tile.charAt(0).toUpperCase() + f.tile.slice(1)]); }
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
        if (row[0] === 'q') { search.value = ''; }
        else if (row[0] === 'tile') { setTile(''); return; }
        else { $('#' + row[0]).value = ''; }
        apply();
      });
      chipBox.appendChild(chip);
    });
    chipBox.hidden = live.length === 0;
    var n = $('[data-filter-n]');
    if (n) { n.textContent = live.length; n.hidden = live.length === 0; }
  }

  function apply() {
    var f = activeFilters(), shown = 0;
    $$('[data-row], [data-card]').forEach(function (el) {
      var ok = matches(el, f);
      el.hidden = !ok;
      if (ok && el.tagName === 'TR') { shown++; }
    });

    /* Renumber the table so the # column always reads 1..n. */
    var i = 0;
    $$('#annTable tbody tr').forEach(function (r) {
      if (r.hidden) { return; }
      var c = $('.num', r);
      if (c) { c.textContent = ++i; }
    });

    var calendarOpen = !$('[data-pane="calendar"]').hidden;
    if (emptyEl) { emptyEl.hidden = calendarOpen || shown !== 0; }
    var anyFilter = !!(f.q || f.tile || $$('[data-filter]').some(function (el) { return el.value; }));
    var t = $('[data-empty-title]'), bdy = $('[data-empty-body]');
    if (t && bdy) {
      t.textContent = anyFilter ? 'Nothing matches those filters' : 'No announcements yet';
      bdy.textContent = anyFilter
        ? 'Try a wider date range or a different status, or clear the filters to see everything again.'
        : "When you publish a notice it appears here, and on the members' side of the app. Start with something small — this week's service times, or a thank-you.";
    }

    var note = $('[data-shown-note]');
    if (note) { note.textContent = shown + ' announcement' + (shown === 1 ? '' : 's') + (anyFilter ? ' of ' + ROWS.length : ''); }
    var cn = $('[data-count-note]');
    if (cn) { cn.textContent = 'Showing 1 to ' + shown + ' of ' + shown + ' announcements'; }

    paintChips(f);
    paintBulk();
  }

  /* The stat tiles are filters. Clicking the same one again clears it. */
  function setTile(which) {
    tileFilter = which;
    $$('[data-tile]').forEach(function (b) {
      var on = b.getAttribute('data-tile') === which;
      b.classList.toggle('is-on', on);
      b.setAttribute('aria-pressed', String(on));
    });
    apply();
  }
  $$('[data-tile]').forEach(function (b) {
    b.addEventListener('click', function () {
      setTile(tileFilter === b.getAttribute('data-tile') ? '' : b.getAttribute('data-tile'));
    });
  });

  if (search) {
    search.addEventListener('input', function () {
      var x = $('[data-search-clear]');
      if (x) { x.hidden = !search.value; }
      apply();
    });
  }
  var clearBtn = $('[data-search-clear]');
  if (clearBtn) { clearBtn.addEventListener('click', function () { search.value = ''; clearBtn.hidden = true; apply(); search.focus(); }); }
  $$('[data-filter]').forEach(function (el) {
    el.addEventListener('change', apply);
    /* A date field only fires change on blur, which leaves the list stale
       while somebody is still typing. */
    if (el.tagName === 'INPUT') { el.addEventListener('input', apply); }
  });
  $$('[data-reset-filters]').forEach(function (b) {
    b.addEventListener('click', function () {
      if (search) { search.value = ''; }
      $$('[data-filter]').forEach(function (el) { el.value = ''; });
      if (clearBtn) { clearBtn.hidden = true; }
      setTile('');
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

  /* ───────────────────────────── sorting ───────────────────────────── */
  $$('#annTable th.is-sortable').forEach(function (th) {
    th.addEventListener('click', function () {
      var key = th.getAttribute('data-sort');
      /* aria-sort still holds the previous state, so the direction is read
         from where the click is taking the column. */
      var desc = th.getAttribute('aria-sort') === 'descending';
      var dir  = desc ? -1 : 1;
      $$('#annTable th').forEach(function (o) { o.removeAttribute('aria-sort'); });
      th.setAttribute('aria-sort', desc ? 'ascending' : 'descending');

      var numeric = key === 'views';
      var body = $('#annTable tbody');
      var trs = $$('tr', body);
      trs.sort(function (a, b) {
        var x = a.getAttribute('data-' + key) || '';
        var y = b.getAttribute('data-' + key) || '';
        if (numeric) { return (parseFloat(y) - parseFloat(x)) * dir; }
        if (!x) { return 1; }
        if (!y) { return -1; }
        return y.localeCompare(x) * dir;
      });
      trs.forEach(function (r) { body.appendChild(r); });
      apply();
    });
  });

  /* ──────────────────────── bulk selection ──────────────────────── */
  var bulkBar = $('#bulkBar');
  function selected() {
    return $$('#annTable tbody [data-check]').filter(function (c) { return c.checked && !c.closest('tr').hidden; });
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
      var boxes = $$('#annTable tbody [data-check]').filter(function (b) { return !b.closest('tr').hidden; });
      all.checked = boxes.length > 0 && n === boxes.length;
      all.indeterminate = n > 0 && n < boxes.length;
    }
  }
  $$('#annTable [data-check]').forEach(function (c) { c.addEventListener('change', paintBulk); });
  var checkAll = $('[data-check-all]');
  if (checkAll) {
    checkAll.addEventListener('change', function () {
      $$('#annTable tbody [data-check]').forEach(function (c) {
        if (!c.closest('tr').hidden) { c.checked = checkAll.checked; }
      });
      paintBulk();
    });
  }
  var bulkClose = $('#bulkClose');
  if (bulkClose) {
    bulkClose.addEventListener('click', function () {
      $$('#annTable [data-check]').forEach(function (c) { c.checked = false; });
      paintBulk();
    });
  }
  [['#bulkPublish', 'published', 'success'], ['#bulkUnpublish', 'unpublished', 'info'],
   ['#bulkPin', 'pinned', 'success'], ['#bulkDelete', 'deleted', 'error']].forEach(function (row) {
    var b = $(row[0]);
    if (b) {
      b.addEventListener('click', function () {
        var n = selected().length;
        toast(n + ' announcement' + (n === 1 ? '' : 's') + ' ' + row[1], row[2]);
      });
    }
  });

  /* ═════════════════════════════ drawers ═════════════════════════════ */
  var scrim = $('[data-drawer-scrim]');
  var annD  = $('#annDrawer');
  var dayD  = $('#dayDrawer');
  var lastFocus = null, current = null;

  function openDrawer(d) {
    lastFocus = document.activeElement;
    d.hidden = false; scrim.hidden = false;
    document.body.style.overflow = 'hidden';
    $('[data-drawer-close]', d).focus();
  }
  function closeDrawers() {
    [annD, dayD].forEach(function (d) { if (d) { d.hidden = true; } });
    scrim.hidden = true; document.body.style.overflow = '';
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
    for (i = 0; i < bytes.length; i++) { b = bytes.charCodeAt(i); c = (CRC[(c ^ b) & 0xFF] ^ (c >>> 8)) >>> 0; }
    return (c ^ 0xFFFFFFFF) >>> 0;
  }
  function avatar(name, size) {
    return '<span class="av av--' + (size || 'sm') + ' av-c' + (crc32(name) % 10) + '" aria-hidden="true">'
         + initials(name) + '</span>';
  }

  function openAnnouncement(id) {
    var r = byId[id];
    if (!r) { return; }
    current = r;

    $('#dTitle').textContent = r.typeName;
    $('[data-d-type]').textContent = r.audience;
    var ico = $('[data-d-ico]');
    ico.style.setProperty('--c', r.typeColour);
    ico.innerHTML = '<i class="fa-solid ' + r.typeIcon + '"></i>';

    var st = $('[data-d-status]');
    st.className = 'pill pill--an-' + r.status;
    st.innerHTML = '<i class="fa-solid ' + r.statusIcon + '" aria-hidden="true"></i> ' + esc(r.statusLabel);
    $('[data-d-pin]').hidden = !r.pinned;

    $('[data-d-title]').textContent = r.title;

    /* Paragraphs, kept as paragraphs. */
    var body = $('[data-d-message]');
    body.innerHTML = '';
    String(r.message).split(/\n{2,}/).forEach(function (para) {
      var p = document.createElement('p');
      p.textContent = para.replace(/\n/g, ' ');
      body.appendChild(p);
    });

    var img = $('[data-d-image]');
    img.hidden = !r.image;
    if (r.image) { $('[data-d-imagecap]').textContent = r.image; }

    $('[data-d-audience]').textContent  = r.audience + ' · ' + r.recipients.toLocaleString() + ' people';
    $('[data-d-author]').textContent    = r.author;
    $('[data-d-published]').textContent = r.published ? (r.published + ' · ' + r.ago)
      : (r.scheduled ? 'Scheduled for ' + r.scheduled : 'Not published');
    $('[data-d-expires]').textContent   = r.expires || 'No expiry';
    var bEl = $('[data-d-branch]');
    if (bEl) { bEl.textContent = r.branches.length ? r.branches.join(', ') : WHOLE_ORG; }
    $('[data-d-comments]').textContent  = r.comments ? 'Allowed' : 'Turned off';

    /* Views, then each channel it actually went out on. */
    var reach = $('[data-d-reach]');
    reach.innerHTML = '';
    function stat(icon, label, value, sub) {
      var d = document.createElement('div');
      d.className = 'reachgrid__cell';
      d.innerHTML = '<span class="reachgrid__ico" aria-hidden="true"><i class="fa-solid ' + icon + '"></i></span>'
        + '<span class="reachgrid__v">' + esc(value) + '</span>'
        + '<span class="reachgrid__l">' + esc(label) + '</span>'
        + (sub ? '<span class="reachgrid__s">' + esc(sub) + '</span>' : '');
      reach.appendChild(d);
    }
    stat('fa-eye', 'Views', r.views.toLocaleString(), r.reach + '% of the audience');
    if (r.sms)   { stat('fa-comment-sms', 'SMS delivered', r.sms.delivered.toLocaleString(), r.sms.failed + ' failed of ' + r.sms.sent); }
    if (r.email) { stat('fa-envelope', 'Email delivered', r.email.delivered.toLocaleString(), 'of ' + r.email.sent + ' sent'); }
    if (!r.sms && !r.email) { stat('fa-mobile-screen', 'Portal only', '—', 'Not pushed by SMS or email'); }

    $('[data-d-recipients]').textContent = r.audience + ' — ' + r.recipients.toLocaleString()
      + ' people were in the audience when this went out.';
    $('[data-d-recipbtn]').setAttribute('data-recipients', r.id);

    var un = $('#dUnpublish');
    if (un) { un.hidden = r.status !== 'published'; }

    openDrawer(annD);
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-open]');
    if (b) { closeOwnMenu(b); openAnnouncement(b.getAttribute('data-open')); }
  }, true);

  /* ── a day from the calendar ── */
  document.addEventListener('click', function (e) {
    var cell = e.target.closest('[data-day]');
    if (!cell) { return; }
    var iso = cell.getAttribute('data-day');
    var items = (DAY_INDEX[iso] || []).map(function (id) { return byId[id]; }).filter(Boolean);

    $('#dayTitle').textContent = cell.getAttribute('aria-label').split(':')[0];
    $('[data-day-count]').textContent = items.length + ' announcement' + (items.length === 1 ? '' : 's');

    var box = $('[data-day-list]');
    box.innerHTML = '';
    items.forEach(function (r) {
      var row = document.createElement('button');
      row.className = 'minirow minirow--btn';
      row.type = 'button';
      row.setAttribute('data-open', r.id);
      row.innerHTML = '<span class="catico" style="--c:' + r.typeColour + '" aria-hidden="true">'
        + '<i class="fa-solid ' + r.typeIcon + '"></i></span>'
        + '<span class="minirow__text"><b>' + esc(r.title) + '</b><span>' + esc(r.typeName)
        + ' · ' + esc(r.statusLabel) + '</span></span>';
      box.appendChild(row);
    });
    openDrawer(dayD);
  }, true);

  /* ═════════════════════════════ modals ═════════════════════════════ */
  function openModal(m) { m.hidden = false; document.body.style.overflow = 'hidden'; var c = $('[data-close]', m); if (c) { c.focus(); } }
  function closeModal(m) {
    m.hidden = true;
    if ($$('.modal-scrim').every(function (x) { return x.hidden; }) && annD.hidden && dayD.hidden) {
      document.body.style.overflow = '';
    }
  }
  document.addEventListener('click', function (e) {
    var cl = e.target.closest('[data-close]');
    if (cl) { closeModal(cl.closest('.modal-scrim')); return; }
    if (e.target.classList.contains('modal-scrim')) { closeModal(e.target); }
  });

  /* ── who received it ── */
  var rcModal = $('#modalRecipients');
  if (rcModal) {
    function openRecipients(id) {
      var r = byId[id];
      if (!r) { return; }
      $('[data-rc-for]').textContent = r.title + ' — ' + r.audience
        + ' (' + r.recipients.toLocaleString() + ' people)';
      var body = $('[data-rc-rows]');
      body.innerHTML = '';
      /* A representative sample; the real list is as long as the audience. */
      MEMBERS.forEach(function (m, i) {
        var channel = r.sms && r.email ? 'SMS + Email' : r.sms ? 'SMS' : r.email ? 'Email' : 'Portal';
        var state = 'Delivered';
        if (r.status === 'scheduled' || r.status === 'draft') { state = 'Pending'; }
        else if ((r.sms || r.email) && i % 9 === 4) { state = 'Failed'; }
        var tr = document.createElement('tr');
        tr.setAttribute('data-rc-row', m.name.toLowerCase());
        tr.innerHTML = '<td><span class="minirow minirow--tight">' + avatar(m.name, 'sm')
          + '<span class="minirow__text"><b>' + esc(m.name) + '</b><span>' + esc(m.no) + '</span></span></span></td>'
          + '<td>' + esc(channel) + '</td>'
          + '<td><span class="pill pill--dl-' + state.toLowerCase() + '">' + state + '</span></td>';
        body.appendChild(tr);
      });
      $('#rcSearch').value = '';
      $('[data-rc-empty]').hidden = true;
      openModal(rcModal);
    }
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-recipients]');
      if (b) { closeOwnMenu(b); openRecipients(b.getAttribute('data-recipients')); }
    }, true);
    $('#rcSearch').addEventListener('input', function () {
      var q = $('#rcSearch').value.trim().toLowerCase(), shown = 0;
      $$('[data-rc-row]').forEach(function (tr) {
        var ok = !q || tr.getAttribute('data-rc-row').indexOf(q) !== -1;
        tr.hidden = !ok;
        if (ok) { shown++; }
      });
      $('[data-rc-empty]').hidden = shown !== 0;
    });
  }

  /* ══════════════════ the compose / edit screen ══════════════════ */
  var edModal = $('#modalEdit');
  if (edModal) {
    var TYPES = {};
    ROWS.forEach(function (r) { TYPES[r.type] = { name: r.typeName, icon: r.typeIcon, colour: r.typeColour }; });
    $$('input[name="edType"]').forEach(function (r) {
      var box = r.nextElementSibling;
      TYPES[r.value] = TYPES[r.value] || {
        name: $('span', box).textContent.trim(),
        icon: ($('i', box).className.match(/fa-[a-z-]+$/) || ['fa-circle-info'])[0],
        colour: getComputedStyle(r.closest('.iconcard')).getPropertyValue('--c').trim() || '#662F97'
      };
    });

    /* ── the live preview ── */
    function paintPreview() {
      var type = ($('input[name="edType"]:checked') || {}).value || 'general';
      var t = TYPES[type] || { name: 'General', icon: 'fa-circle-info', colour: '#662F97' };
      var card = $('[data-pv-card]');
      card.style.setProperty('--c', t.colour);

      var chip = $('[data-pv-type]');
      chip.style.setProperty('--c', t.colour);
      chip.innerHTML = '<i class="fa-solid ' + t.icon + '" aria-hidden="true"></i> ' + esc(t.name);

      var title = $('#edSubject').value.trim();
      $('[data-pv-title]').textContent = title || 'Your title will appear here';
      var msg = $('#edMessage').value.trim();
      $('[data-pv-body]').textContent = msg
        ? (msg.length > 160 ? msg.slice(0, 159) + '…' : msg)
        : 'And the message underneath it, exactly as members will read it.';

      var f = $('#edImage').files;
      var hasImage = !!(f && f.length);
      $('[data-pv-image]').hidden = !hasImage;
      if (hasImage) { $('[data-pv-imagecap]').textContent = f[0].name; }

      $('[data-pv-aud]').innerHTML = '<i class="fa-solid fa-users" aria-hidden="true"></i> ' + esc(audienceLabel());
      $('[data-pv-pin]').hidden = !$('#edPin').checked;

      var later = ($('input[name="edWhen"]:checked') || {}).value === 'later';
      $('[data-pv-when]').textContent = later
        ? 'Goes out ' + ($('#edDate').value || 'later') + ' at ' + ($('#edTime').value || '')
        : 'Just now';
    }

    /* ── the audience pickers ── */
    function audienceKind() { return ($('input[name="edAud"]:checked') || {}).value || 'all'; }
    function picked(kind) {
      var box = $('[data-picker="' + kind + '"]');
      return box ? $$('[data-pick]', box).filter(function (c) { return c.checked; }) : [];
    }
    function audienceLabel() {
      var kind = audienceKind();
      if (kind === 'all') { return 'All Members'; }
      var chosen = picked(kind);
      if (!chosen.length) { return 'Nobody chosen yet'; }
      if (chosen.length === 1) { return chosen[0].value; }
      var noun = { department: 'Departments', cell: 'Cell Groups', branch: <?= json_encode(t('branch_plural')) ?>, selected: 'Members' }[kind] || 'Groups';
      return chosen.length + ' ' + noun;
    }
    function recipientCount() {
      var kind = audienceKind();
      if (kind === 'all') { return TOTAL_MEMBERS; }
      return picked(kind).reduce(function (n, c) { return n + (parseInt(c.getAttribute('data-size'), 10) || 0); }, 0);
    }
    function syncAudience() {
      var kind = audienceKind();
      $$('[data-picker]').forEach(function (b) { b.hidden = b.getAttribute('data-picker') !== kind; });
      var n = recipientCount();
      $('[data-recip-count]').textContent = n.toLocaleString();
      $('[data-sms-recip]').textContent = n.toLocaleString();
      paintPreview();
    }
    $$('input[name="edAud"]').forEach(function (r) { r.addEventListener('change', syncAudience); });
    $$('[data-pick]').forEach(function (c) { c.addEventListener('change', syncAudience); });
    $$('[data-picker-search]').forEach(function (inp) {
      inp.addEventListener('input', function () {
        var q = inp.value.trim().toLowerCase();
        $$('.pickrow2', inp.closest('[data-picker]')).forEach(function (row) {
          row.hidden = !!q && $('span', row).textContent.toLowerCase().indexOf(q) === -1;
        });
      });
    });

    /* ── SMS counter ── */
    function syncSms() {
      var on = $('#edSms').checked;
      $('[data-sms-note]').hidden = !on;
      if (!on) { return; }
      var len = $('#edMessage').value.length;
      /* GSM-7 fits 160 in one part, 153 per part once it splits. */
      var parts = len <= 160 ? 1 : Math.ceil(len / 153);
      $('[data-sms-chars]').textContent = len;
      $('[data-sms-parts]').textContent = parts;
      $('[data-sms-plural]').textContent = parts === 1 ? '' : 's';
      $('[data-sms-recip]').textContent = recipientCount().toLocaleString();
    }
    $('#edSms').addEventListener('change', syncSms);
    $('#edMessage').addEventListener('input', function () { syncSms(); paintPreview(); });
    $('#edSubject').addEventListener('input', paintPreview);
    $$('input[name="edType"]').forEach(function (r) { r.addEventListener('change', paintPreview); });
    $('#edPin').addEventListener('change', paintPreview);

    /* ── schedule and expiry reveal their own fields ── */
    $$('input[name="edWhen"]').forEach(function (r) {
      r.addEventListener('change', function () {
        $('[data-when-later]').hidden = r.value !== 'later' || !r.checked;
        paintPreview();
      });
    });
    ['#edDate', '#edTime'].forEach(function (sel) { $(sel).addEventListener('change', paintPreview); });
    $('#edHasExpiry').addEventListener('change', function () {
      $('[data-expiry]').hidden = !$('#edHasExpiry').checked;
    });

    /* ── the formatting toolbar wraps the selection; that is all it does ── */
    var WRAP = { bold: ['**', '**'], italic: ['_', '_'], link: ['[', '](https://)'], list: ['\n- ', ''] };
    $$('[data-fmt]').forEach(function (b) {
      b.addEventListener('click', function () {
        var ta = $('#edMessage'), w = WRAP[b.getAttribute('data-fmt')];
        var a = ta.selectionStart, z = ta.selectionEnd;
        var chosen = ta.value.slice(a, z) || '';
        ta.value = ta.value.slice(0, a) + w[0] + chosen + w[1] + ta.value.slice(z);
        ta.focus();
        ta.selectionStart = a + w[0].length;
        ta.selectionEnd = a + w[0].length + chosen.length;
        syncSms(); paintPreview();
      });
    });

    /* ── image, click or drop ── */
    var file = $('#edImage'), drop = $('#edDrop');
    function showFile(f) {
      if (!f) { return; }
      $('[data-ed-filename]').textContent = f.name;
      $('[data-ed-filesize]').textContent = (f.size / 1024).toFixed(0) + ' KB';
      $('[data-ed-file]').hidden = false;
      drop.hidden = true;
      $('[data-pv-image]').hidden = false;
      $('[data-pv-imagecap]').textContent = f.name;
    }
    function dropFile() {
      file.value = '';
      $('[data-ed-file]').hidden = true;
      drop.hidden = false;
      drop.classList.remove('is-over');
      $('[data-pv-image]').hidden = true;
    }
    file.addEventListener('change', function () { showFile(file.files && file.files[0]); });
    $('#edDropFile').addEventListener('click', dropFile);
    ['dragenter', 'dragover'].forEach(function (ev) {
      drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('is-over'); });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
      drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('is-over'); });
    });
    drop.addEventListener('drop', function (e) {
      if (e.dataTransfer && e.dataTransfer.files.length) { showFile(e.dataTransfer.files[0]); }
    });

    function openForm(r) {
      $('#edTitle').textContent = r ? 'Edit Announcement' : 'New Announcement';
      $('#edSubject').value = r ? r.title : '';
      $('#edMessage').value = r ? r.message : '';
      $('#edPin').checked = r ? r.pinned : false;
      $('#edComments').checked = r ? r.comments : true;
      $('#edSms').checked   = !!(r && r.sms);
      $('#edEmail').checked = !!(r && r.email);
      if (r) {
        var t = $('input[name="edType"][value="' + r.type + '"]');
        if (t) { t.checked = true; }
        var a = $('input[name="edAud"][value="' + r.audienceKind + '"]');
        if (a) { a.checked = true; }
      } else {
        $('input[name="edType"]').checked = true;
        $('input[name="edAud"]').checked = true;
      }
      var when = $('input[name="edWhen"][value="' + (r && r.scheduled ? 'later' : 'now') + '"]');
      if (when) { when.checked = true; }
      $('[data-when-later]').hidden = !(r && r.scheduled);
      $('#edHasExpiry').checked = !!(r && r.expires);
      $('[data-expiry]').hidden = !(r && r.expires);
      dropFile();
      syncAudience(); syncSms(); paintPreview();
      openModal(edModal);
    }

    var nb = $('#btnNew');
    if (nb) { nb.addEventListener('click', function () { openForm(null); }); }
    var ne = $('[data-new-from-empty]');
    if (ne) { ne.addEventListener('click', function () { openForm(null); }); }
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
        $('#edTitle').textContent = 'New Announcement';
        $('#edSubject').value = 'Copy of ' + r.title;
        paintPreview();
        toast('Copied from “' + r.title + '”', 'info');
      }
    }, true);
    var de = $('#dEdit');
    if (de) { de.addEventListener('click', function () { if (current) { closeDrawers(); openForm(current); } }); }
    var dd = $('#dDuplicate');
    if (dd) {
      dd.addEventListener('click', function () {
        if (!current) { return; }
        closeDrawers(); openForm(current);
        $('#edTitle').textContent = 'New Announcement';
        $('#edSubject').value = 'Copy of ' + current.title;
        paintPreview();
      });
    }

    function validate() {
      if (!$('#edSubject').value.trim()) { toast('Give the announcement a title', 'error'); $('#edSubject').focus(); return false; }
      if (!$('#edMessage').value.trim()) { toast('There is no message to send', 'error'); $('#edMessage').focus(); return false; }
      if (audienceKind() !== 'all' && !picked(audienceKind()).length) {
        toast('Choose who this goes to', 'error');
        var box = $('[data-picker="' + audienceKind() + '"] [data-picker-search]');
        if (box) { box.focus(); }
        return false;
      }
      return true;
    }
    $('#edDraft').addEventListener('click', function () {
      if (!validate()) { return; }
      closeModal(edModal); toast('Saved as a draft', 'info');
    });
    $('#edPublish').addEventListener('click', function () {
      if (!validate()) { return; }
      var later = ($('input[name="edWhen"]:checked') || {}).value === 'later';
      closeModal(edModal);
      toast(later ? 'Scheduled for ' + $('#edDate').value + ' at ' + $('#edTime').value
                  : 'Published to ' + recipientCount().toLocaleString() + ' members', 'success');
    });
  }

  /* ── delete ── */
  var dlModal = $('#modalDelete');
  if (dlModal) {
    var dlRow = null;
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-delete]');
      if (!b) { return; }
      closeOwnMenu(b);
      dlRow = byId[b.getAttribute('data-delete')];
      $('[data-dl-title]').textContent = dlRow ? dlRow.title : 'this announcement';
      openModal(dlModal);
    }, true);
    $('#dlGo').addEventListener('click', function () {
      closeModal(dlModal);
      toast('“' + (dlRow ? dlRow.title : 'Announcement') + '” deleted', 'error');
    });
  }

  /* ── the small state changes ── */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-pin]');
    if (b) {
      closeOwnMenu(b);
      var r = byId[b.getAttribute('data-pin')];
      var nowOn = b.getAttribute('aria-pressed') !== 'true';
      if (b.hasAttribute('aria-pressed')) {
        b.setAttribute('aria-pressed', String(nowOn));
        b.classList.toggle('is-on', nowOn);
      }
      toast(r ? (nowOn ? '“' + r.title + '” pinned to the top' : '“' + r.title + '” unpinned') : 'Pin updated',
            nowOn ? 'success' : 'info');
      return;
    }
    var p = e.target.closest('[data-publish]');
    if (p) { closeOwnMenu(p); var pr = byId[p.getAttribute('data-publish')];
             toast('“' + (pr ? pr.title : 'Announcement') + '” published', 'success'); return; }
    var u = e.target.closest('[data-unpublish]');
    if (u) { closeOwnMenu(u); var ur = byId[u.getAttribute('data-unpublish')];
             toast('“' + (ur ? ur.title : 'Announcement') + '” unpublished', 'info'); return; }
    var tg = e.target.closest('[data-toggle-pub]');
    if (tg) {
      var tr = byId[tg.getAttribute('data-toggle-pub')];
      var wasPublished = tr && tr.status === 'published';
      toast('“' + (tr ? tr.title : 'Announcement') + '” ' + (wasPublished ? 'unpublished' : 'published'),
            wasPublished ? 'info' : 'success');
    }
  }, true);
  var du = $('#dUnpublish');
  if (du) { du.addEventListener('click', function () { if (current) { toast('“' + current.title + '” unpublished', 'info'); } }); }

  /* ── reordering the pinned list ── */
  $$('[data-pin-move]').forEach(function (b) {
    b.addEventListener('click', function () {
      var row = b.closest('[data-pin-row]'), box = row.parentNode;
      if (b.getAttribute('data-pin-move') === 'up' && row.previousElementSibling) {
        box.insertBefore(row, row.previousElementSibling);
      }
      if (b.getAttribute('data-pin-move') === 'down' && row.nextElementSibling) {
        box.insertBefore(row.nextElementSibling, row);
      }
      row.scrollIntoView({ block: 'nearest' });
      toast('Pinned order changed', 'info');
    });
  });

  /* ────────────────────────── escape key ────────────────────────── */
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') { return; }
    var open = $$('.modal-scrim').filter(function (m) { return !m.hidden; });
    if (open.length) { open.forEach(function (m) { m.hidden = true; }); }
    else if (!annD.hidden || !dayD.hidden) { closeDrawers(); }
    if (annD.hidden && dayD.hidden && $$('.modal-scrim').every(function (m) { return m.hidden; })) {
      document.body.style.overflow = '';
    }
  });

  apply();
  paintBulk();
})();
</script>
<?php endif; ?>
<?php require __DIR__ . '/../components/footer.php'; ?>

<?php
/**
 * Mutendi Umbrella System — reusable admin sidebar.
 *
 * Drop into any page under /mus, at any folder depth:
 *
 *     <?php require __DIR__ . '/components/sidebar.php'; ?>       // mus/index.php
 *     <?php require __DIR__ . '/../components/sidebar.php'; ?>    // mus/churches/all.php
 *
 * Optional — set BEFORE the require:
 *
 *     $activePage    string  slug to highlight, e.g. 'churches/all'.
 *                            Defaults to the current script, so usually omit.
 *     $sidebarBadges array   counts keyed by an item's `badge` key,
 *                            e.g. ['pending' => 3, 'expiring' => 5]
 *     $sidebarUser   array   ['name' => '...', 'role' => '...'] for the footer.
 *
 * Everything it needs — markup, styles, behaviour — lives in this one file.
 */

/* ------------------------------------------------------------ base URL -- */

if (!defined('MUS_URL')) {
    // Locate /mus under the document root so links resolve identically
    // whether the page lives at /mus/index.php or /mus/churches/all.php.
    $docRoot = str_replace('\\', '/', rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/'));
    $musDir  = str_replace('\\', '/', dirname(__DIR__));
    $guess   = ($docRoot !== '' && strpos($musDir, $docRoot) === 0)
        ? substr($musDir, strlen($docRoot))
        : '/mus';
    define('MUS_URL', rtrim($guess, '/') ?: '/mus');
}

if (!defined('MUS_ROOT_URL')) {
    // One level above /mus — where the shared brand assets live.
    define('MUS_ROOT_URL', rtrim(dirname(MUS_URL), '/'));
}

// Browser-facing base, always with a trailing slash: "/mutendi/mus/".
if (!isset($base_url)) {
    $base_url = MUS_URL . '/';
}

if (!function_exists('mus_url')) {
    /** Absolute URL for a page slug. */
    function mus_url(string $slug): string {
        return MUS_URL . '/' . ltrim($slug, '/') . '.php';
    }
}

if (!function_exists('mus_current_slug')) {
    /** Slug of the page being served, e.g. 'churches/all'. */
    function mus_current_slug(): string {
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $rel    = ltrim(substr($script, strlen(MUS_URL)), '/');
        $rel    = (string) preg_replace('/\.php$/', '', $rel);
        return $rel !== '' ? $rel : 'index';
    }
}

// A page can `require` this once at the very top with $musPathsOnly = true to
// get $base_url and the helpers for its <head>, then require it again where
// the sidebar markup belongs.
if (!empty($musPathsOnly)) {
    return;
}

/* ----------------------------------------------------------- nav items -- */
/**
 * icon  — Font Awesome name, without the "fa-" prefix
 * slug  — page path relative to /mus, without ".php" ("index" is the dashboard)
 * badge — key looked up in $sidebarBadges
 * p     — build priority from the spec (1 or 2). Not displayed; kept as a
 *         data-priority attribute so the roadmap survives in the markup.
 */
$musNav = [
    'Main' => [
        ['label' => 'Dashboard', 'icon' => 'gauge-high', 'slug' => 'index', 'p' => 1],
    ],
    'Churches' => [
        ['label' => 'All Churches',        'icon' => 'church',        'slug' => 'churches/all',      'p' => 1],
        ['label' => 'Pending Activation',  'icon' => 'hourglass-half', 'slug' => 'churches/pending',  'p' => 1, 'badge' => 'pending'],
        ['label' => 'Expiring Soon',       'icon' => 'clock',   'slug' => 'churches/expiring', 'p' => 1, 'badge' => 'expiring'],
        ['label' => 'Expired / Suspended', 'icon' => 'ban',    'slug' => 'churches/expired',  'p' => 1],
        ['label' => 'Trial Accounts',      'icon' => 'star',           'slug' => 'churches/trials',   'p' => 2],
        ['label' => 'Archived / Deleted',  'icon' => 'box-archive',         'slug' => 'churches/archived', 'p' => 2],
    ],
    'Access & Licensing' => [
        ['label' => 'Modules',          'icon' => 'cubes', 'slug' => 'access/modules',  'p' => 1],
        ['label' => 'Plan Presets',     'icon' => 'layer-group',       'slug' => 'access/plans',    'p' => 2],
        ['label' => 'Church Admins',    'icon' => 'user-tie', 'slug' => 'access/admins',   'p' => 1],
        ['label' => 'Feature Requests', 'icon' => 'lightbulb',    'slug' => 'access/requests', 'p' => 2],
    ],
    'Communication' => [
        ['label' => 'Announcements',     'icon' => 'bullhorn',      'slug' => 'comms/announcements', 'p' => 1],
        ['label' => 'Send Notification', 'icon' => 'paper-plane',           'slug' => 'comms/notify',        'p' => 2],
        ['label' => 'SMS Credits',       'icon' => 'comment-dots',      'slug' => 'comms/sms-credits',   'p' => 1],
        ['label' => 'Message Log',       'icon' => 'clipboard-list',   'slug' => 'comms/log',           'p' => 2],
        ['label' => 'Email Templates',   'icon' => 'envelope-open-text', 'slug' => 'comms/templates',     'p' => 2],
    ],
    'Defaults & Setup Data' => [
        ['label' => 'Denominations',          'icon' => 'sitemap',         'slug' => 'setup/denominations',      'p' => 2],
        ['label' => 'Member Field Presets',   'icon' => 'i-cursor', 'slug' => 'setup/member-fields',      'p' => 2],
        ['label' => 'Contribution Types',     'icon' => 'hand-holding-dollar',         'slug' => 'setup/contribution-types', 'p' => 2],
        ['label' => 'Role Templates',         'icon' => 'user-gear',       'slug' => 'setup/role-templates',     'p' => 1],
        ['label' => 'Countries / Currencies', 'icon' => 'globe',    'slug' => 'setup/countries',          'p' => 2],
    ],
    'Reports' => [
        ['label' => 'Growth Report',      'icon' => 'arrow-trend-up', 'slug' => 'reports/growth',      'p' => 2],
        ['label' => 'Usage Report',       'icon' => 'chart-column', 'slug' => 'reports/usage',       'p' => 2],
        ['label' => 'Renewal Report',     'icon' => 'arrows-rotate',   'slug' => 'reports/renewals',    'p' => 1],
        ['label' => 'Activation History', 'icon' => 'list-check',     'slug' => 'reports/activations', 'p' => 1],
    ],
    'System' => [
        ['label' => 'General Settings',    'icon' => 'sliders',          'slug' => 'system/general',       'p' => 1],
        ['label' => 'SMS Gateway',         'icon' => 'tower-broadcast',    'slug' => 'system/sms-gateway',   'p' => 1],
        ['label' => 'Email / SMTP',        'icon' => 'at',      'slug' => 'system/email',         'p' => 1],
        ['label' => 'Payment Notes Setup', 'icon' => 'receipt',          'slug' => 'system/payment-notes', 'p' => 2],
        ['label' => 'Storage & Uploads',   'icon' => 'hard-drive',              'slug' => 'system/storage',       'p' => 2],
        ['label' => 'Backups',             'icon' => 'cloud-arrow-down', 'slug' => 'system/backups',       'p' => 1],
        ['label' => 'Maintenance Mode',    'icon' => 'screwdriver-wrench',     'slug' => 'system/maintenance',   'p' => 1],
        ['label' => 'Update / Version',    'icon' => 'circle-up',  'slug' => 'system/version',       'p' => 2],
    ],
    'Monitoring' => [
        ['label' => 'Activity Log',          'icon' => 'wave-square',             'slug' => 'monitor/activity', 'p' => 1],
        ['label' => 'Login History',         'icon' => 'right-to-bracket',   'slug' => 'monitor/logins',   'p' => 1],
        ['label' => 'Error Log',             'icon' => 'triangle-exclamation', 'slug' => 'monitor/errors',   'p' => 2],
        ['label' => 'Cron / Scheduled Jobs', 'icon' => 'stopwatch',                'slug' => 'monitor/cron',     'p' => 1],
        ['label' => 'Database Health',       'icon' => 'database',       'slug' => 'monitor/database', 'p' => 2],
    ],
    'Administration' => [
        ['label' => 'Super Admin Users',          'icon' => 'user-shield',   'slug' => 'admin/users',    'p' => 1],
        ['label' => 'Roles & Permissions',        'icon' => 'key',           'slug' => 'admin/roles',    'p' => 2],
        ['label' => 'My Profile',                 'icon' => 'circle-user', 'slug' => 'admin/profile',  'p' => 1],
        ['label' => 'Security (2FA, IP whitelist)','icon' => 'fingerprint',  'slug' => 'admin/security', 'p' => 2],
    ],
];

$musActive = $activePage    ?? mus_current_slug();
$musBadges = $sidebarBadges ?? ['pending' => 3, 'expiring' => 5];
$musUser   = ($sidebarUser  ?? []) + ['name' => 'Super Admin', 'role' => 'System Owner'];
?>
<?php if (!defined('MUS_SIDEBAR_ASSETS')): define('MUS_SIDEBAR_ASSETS', true); ?>
<link rel="stylesheet" href="<?= MUS_URL ?>/assets/css/sidebar.css">
<?php endif; ?>

<button class="mus-toggle" type="button" id="musToggle" aria-label="Open navigation" aria-controls="musSidebar" aria-expanded="false">
  <i class="fa-solid fa-bars"></i>
</button>

<aside class="mus-sidebar" id="musSidebar">

  <div class="mus-sidebar__head">
    <a class="mus-brand" href="<?= mus_url('index') ?>">
      <img class="mus-brand__logo" src="<?= MUS_ROOT_URL ?>/resources/img/logo.png" alt="Mutendi">
      <span class="mus-brand__text">
        <strong>Mutendi</strong>
        <small>Umbrella System</small>
      </span>
    </a>
    <button class="mus-collapse" type="button" id="musCollapse" aria-label="Collapse sidebar" title="Collapse sidebar">
      <i class="fa-solid fa-angles-left"></i>
    </button>
  </div>

  <nav class="mus-nav" aria-label="Admin navigation">
    <?php foreach ($musNav as $group => $items): ?>
      <?php
        // A group stays open when it holds the current page; all are open by
        // default so the sidebar still works with JavaScript disabled.
        $groupHasActive = false;
        foreach ($items as $item) {
            if ($item['slug'] === $musActive) { $groupHasActive = true; break; }
        }
        $groupId = 'musGrp' . preg_replace('/[^a-z0-9]/i', '', $group);
      ?>
      <section class="mus-group is-open<?= $groupHasActive ? ' is-current' : '' ?>" data-group="<?= htmlspecialchars($group) ?>">
        <button class="mus-group__head" type="button" aria-expanded="true" aria-controls="<?= $groupId ?>">
          <span><?= htmlspecialchars($group) ?></span>
          <i class="fa-solid fa-chevron-down mus-group__caret"></i>
        </button>

        <ul class="mus-group__list" id="<?= $groupId ?>">
          <?php foreach ($items as $item): ?>
            <?php
              $isOn  = $item['slug'] === $musActive;
              $count = isset($item['badge']) ? (int) ($musBadges[$item['badge']] ?? 0) : 0;
            ?>
            <li>
              <a class="mus-item<?= $isOn ? ' is-active' : '' ?>"
                 href="<?= mus_url($item['slug']) ?>"
                 data-label="<?= htmlspecialchars($item['label']) ?>"
                 data-priority="<?= (int) ($item['p'] ?? 0) ?>"
                 <?= $isOn ? 'aria-current="page"' : '' ?>>
                <i class="fa-solid fa-<?= $item['icon'] ?> mus-item__icon" aria-hidden="true"></i>
                <span class="mus-item__label"><?= htmlspecialchars($item['label']) ?></span>
                <?php if ($count > 0): ?>
                  <span class="mus-item__badge"><?= $count ?></span>
                <?php endif; ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endforeach; ?>
  </nav>

  <div class="mus-sidebar__foot">
    <a class="mus-item<?= $musActive === 'docs' ? ' is-active' : '' ?>" href="<?= mus_url('docs') ?>"
       data-label="Documentation" data-priority="2">
      <i class="fa-solid fa-book mus-item__icon" aria-hidden="true"></i>
      <span class="mus-item__label">Documentation</span>
    </a>
    <a class="mus-item mus-item--danger" href="<?= mus_url('logout') ?>"
       data-label="Logout" data-priority="1">
      <i class="fa-solid fa-right-from-bracket mus-item__icon" aria-hidden="true"></i>
      <span class="mus-item__label">Logout</span>
    </a>

    <div class="mus-user">
      <span class="mus-user__avatar"><i class="fa-solid fa-user" aria-hidden="true"></i></span>
      <span class="mus-user__text">
        <strong><?= htmlspecialchars($musUser['name']) ?></strong>
        <small><?= htmlspecialchars($musUser['role']) ?></small>
      </span>
    </div>
  </div>

</aside>

<?php if (!defined('MUS_SIDEBAR_JS')): define('MUS_SIDEBAR_JS', true); ?>
<script>
(function () {
  'use strict';
  var bar = document.getElementById('musSidebar');
  if (!bar) { return; }

  var store = function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} };
  var read  = function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } };

  /* Rail (icon-only) mode, remembered between pages. */
  var railBtn = document.getElementById('musCollapse');
  if (read('mus-rail') === '1') { bar.classList.add('is-rail'); }
  if (railBtn) {
    railBtn.addEventListener('click', function () {
      var railed = bar.classList.toggle('is-rail');
      railBtn.setAttribute('aria-label', railed ? 'Expand sidebar' : 'Collapse sidebar');
      store('mus-rail', railed ? '1' : '0');
    });
  }

  /* Collapsible groups, also remembered. Groups holding the current page
     are forced open so the active item is never hidden. */
  var shut = (read('mus-shut') || '').split('|').filter(Boolean);
  bar.querySelectorAll('.mus-group').forEach(function (grp) {
    var name = grp.dataset.group;
    var head = grp.querySelector('.mus-group__head');
    var hasActive = !!grp.querySelector('.mus-item.is-active');

    if (shut.indexOf(name) > -1 && !hasActive) {
      grp.classList.remove('is-open');
      head.setAttribute('aria-expanded', 'false');
    }

    head.addEventListener('click', function () {
      if (bar.classList.contains('is-rail')) { return; }
      var open = grp.classList.toggle('is-open');
      head.setAttribute('aria-expanded', String(open));
      var i = shut.indexOf(name);
      if (open && i > -1) { shut.splice(i, 1); }
      if (!open && i === -1) { shut.push(name); }
      store('mus-shut', shut.join('|'));
    });
  });

  /* Off-canvas drawer on narrow screens. */
  var toggle = document.getElementById('musToggle');
  var scrim = null;

  var setOpen = function (open) {
    bar.classList.toggle('is-open', open);
    if (toggle) { toggle.setAttribute('aria-expanded', String(open)); }
    if (open && !scrim) {
      scrim = document.createElement('button');
      scrim.className = 'mus-scrim';
      scrim.type = 'button';
      scrim.setAttribute('aria-label', 'Close navigation');
      scrim.addEventListener('click', function () { setOpen(false); });
      document.body.appendChild(scrim);
    } else if (!open && scrim) {
      scrim.remove();
      scrim = null;
    }
  };

  if (toggle) {
    toggle.addEventListener('click', function () {
      setOpen(!bar.classList.contains('is-open'));
    });
  }
  bar.addEventListener('click', function (e) {
    if (e.target.closest('a') && window.matchMedia('(max-width: 1024px)').matches) {
      setOpen(false);
    }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { setOpen(false); }
  });
})();
</script>
<?php endif; ?>

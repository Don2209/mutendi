<?php
/**
 * Mutendi CMS — church sidebar.
 *
 * Renders whatever $menu describes, filtered by $enabled_modules and
 * $permissions. There is no hardcoded list of links in this file: adding a
 * page means adding one row to $menu in includes/config.php.
 */

if (!isset($base_url)) { require_once __DIR__ . '/../includes/config.php'; }

/* ---------------------------------------------------------------- helpers -- */

if (!function_exists('main_allowed')) {
    /**
     * A group or item is allowed when its module (if any) is switched on for
     * this church AND its permission (if any) is held by this user. Anything
     * that declares neither is always allowed.
     */
    function main_allowed(array $node, array $modules, array $perms): bool
    {
        if (isset($node['module']) && !in_array($node['module'], $modules, true)) {
            return false;
        }
        if (isset($node['permission']) && !in_array($node['permission'], $perms, true)) {
            return false;
        }
        return true;
    }
}

if (!function_exists('main_visible')) {
    /**
     * Filters a list of items, recursing into children. An item that has
     * children but no visible children is dropped with them — it would
     * otherwise be a parent that opens onto nothing.
     */
    function main_visible(array $items, array $modules, array $perms): array
    {
        $out = [];
        foreach ($items as $item) {
            if (!main_allowed($item, $modules, $perms)) { continue; }

            if (!empty($item['children'])) {
                $item['children'] = main_visible($item['children'], $modules, $perms);
                if (!$item['children']) { continue; }
            }
            $out[] = $item;
        }
        return $out;
    }
}

if (!function_exists('main_is_active')) {
    /**
     * Matches a menu url against the page being viewed. Compares the file and,
     * when the url names a folder, the folder too — so members/all.php does
     * not light up while reports/all.php is open.
     */
    function main_is_active(string $url, string $page, string $dir): bool
    {
        $url  = trim($url, '/');
        $file = basename($url);
        $folder = basename(dirname($url));         /* '.' when the url is a bare file */

        if ($file !== $page) { return false; }

        if ($folder === '.' || $folder === '') {
            /* A bare url such as index.php belongs to the app root, so it must
               not also match branches/index.php or departments/index.php. */
            global $base_url;
            return $dir === basename(rtrim((string) $base_url, '/'));
        }
        return $folder === $dir;
    }
}

/* ------------------------------------------------------------ current page -- */

$current_page = basename($_SERVER['PHP_SELF']);
$current_dir  = basename(dirname($_SERVER['PHP_SELF']));

/* ------------------------------------------------- build the render model -- */
/* Done before any markup so a group can know whether it holds the current
   page (and therefore opens) before its heading is printed. */

$nav_groups   = [];
$footer_items = [];

foreach ($menu as $group) {
    if (!main_allowed($group, $enabled_modules, $permissions)) { continue; }

    $items = main_visible($group['items'] ?? [], $enabled_modules, $permissions);
    if (!$items) { continue; }                     /* never render an empty group */

    if (($group['position'] ?? '') === 'footer') {
        $footer_items = $items;
        continue;
    }

    /* Does the current page live in this group? */
    $active = false;
    foreach ($items as $item) {
        if (main_is_active($item['url'] ?? '', $current_page, $current_dir)) { $active = true; break; }
        foreach ($item['children'] ?? [] as $child) {
            if (main_is_active($child['url'] ?? '', $current_page, $current_dir)) { $active = true; break 2; }
        }
    }

    $nav_groups[] = [
        'heading' => $group['heading'] ?? '',
        'icon'    => $group['icon'] ?? 'fa-circle',
        'items'   => $items,
        'active'  => $active,
        'solo'    => count($items) === 1 && empty($items[0]['children']),
        'id'      => 'nav-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($group['heading'] ?? 'group')),
    ];
}

/* ------------------------------------------------- the organisation group -- */
/* Only for a multi-branch tenant, and only for users who can see past their
   own branch. Inserted directly beneath MAIN without touching $menu, so the
   rest of the navigation keeps its order, icons and labels exactly as they
   are. Labels come from $terminology — nothing here says "Branch". */

if (is_multi_branch() && ($user['scope'] ?? 'organisation') !== 'branch') {

    $branch_items = main_visible([
        ['label' => 'All ' . t('branch_plural'), 'icon' => 'fa-church', 'url' => 'branches/index.php'],
        ['label' => 'Add ' . t('branch_singular'), 'icon' => 'fa-plus', 'url' => 'branches/add.php',
         'permission' => 'branches.add'],
        ['label' => t('branch_singular') . ' Reports', 'icon' => 'fa-chart-column', 'url' => '#',
         'permission' => 'reports.view'],
    ], $enabled_modules, $permissions);

    if ($branch_items) {
        /* branches/view.php is a future detail page; naming it here keeps the
           group open on it without another edit to this file. */
        $branch_pages = ['branches/index.php', 'branches/add.php', 'branches/view.php'];
        $branch_active = false;
        foreach ($branch_pages as $bp) {
            if (main_is_active($bp, $current_page, $current_dir)) { $branch_active = true; break; }
        }

        $org_group = [
            'heading' => t('org_singular'),
            'icon'    => 'fa-sitemap',
            'items'   => $branch_items,
            'active'  => $branch_active,
            'solo'    => count($branch_items) === 1,
            'id'      => 'nav-organisation',
        ];

        /* Straight after MAIN, whatever MAIN happens to be called. */
        array_splice($nav_groups, 1, 0, [$org_group]);
    }
}

/* ------------------------------------------------------------ subscription -- */

$days   = (int) ($church['days_left'] ?? 0);
$isPaid = ($church['account_type'] ?? 'trial') === 'paying';
$showSub = $days <= 30;                            /* hidden while there is plenty of runway */
$subTone = $days < 7 ? 'is-danger' : ($days < 14 ? 'is-warn' : 'is-calm');
?>

<aside class="sidebar" id="sidebar" aria-label="Church navigation">

  <!-- ─────────────────────────── church identity ─────────────────────────── -->
  <div class="sidebar__head">
    <a class="church" href="<?= $base_url ?>index.php">
      <?php if (!empty($church['logo']) && @file_exists($church['logo_path'] ?? '')): ?>
        <img class="church__logo" src="<?= htmlspecialchars($church['logo']) ?>"
             alt="<?= htmlspecialchars($church['name']) ?>">
      <?php else: ?>
        <span class="church__badge" aria-hidden="true"><?= htmlspecialchars($church['initials'] ?? '?') ?></span>
      <?php endif; ?>
      <span class="church__text">
        <strong><?= htmlspecialchars($church['name']) ?></strong>
        <small><?= htmlspecialchars($church['code']) ?></small>
      </span>
    </a>

    <!-- Mobile only: closes the off-canvas panel. -->
    <button class="sidebar__close" type="button" id="navClose" aria-label="Close navigation">
      <i class="fa-solid fa-xmark" aria-hidden="true"></i>
    </button>
  </div>

  <!-- ─────────────────────────────── navigation ──────────────────────────── -->
  <nav class="nav" id="nav">
    <?php foreach ($nav_groups as $g): ?>
      <div class="nav-group<?= $g['active'] ? ' is-open' : '' ?><?= $g['solo'] ? ' nav-group--solo' : '' ?>"
           data-group="<?= htmlspecialchars($g['id']) ?>">

        <button class="nav-group__head" type="button"
                aria-expanded="<?= $g['active'] ? 'true' : 'false' ?>"
                aria-controls="<?= htmlspecialchars($g['id']) ?>">
          <i class="fa-solid <?= htmlspecialchars($g['icon']) ?> nav-group__icon" aria-hidden="true"></i>
          <span class="nav-group__label"><?= htmlspecialchars($g['heading']) ?></span>
          <i class="fa-solid fa-chevron-down nav-group__chev" aria-hidden="true"></i>
        </button>

        <ul class="nav-list" id="<?= htmlspecialchars($g['id']) ?>" data-flyout>
          <li class="nav-list__title" aria-hidden="true"><?= htmlspecialchars($g['heading']) ?></li>

          <?php foreach ($g['items'] as $item):
              $url    = $base_url . ltrim($item['url'] ?? '#', '/');
              $on     = main_is_active($item['url'] ?? '', $current_page, $current_dir);
              $kids   = $item['children'] ?? [];
          ?>
            <li class="nav-li<?= $kids ? ' has-kids' : '' ?>">
              <a class="nav-item<?= $on ? ' is-active' : '' ?>"
                 href="<?= htmlspecialchars($url) ?>"
                 <?= $on ? 'aria-current="page"' : '' ?>
                 data-tip="<?= htmlspecialchars($item['label']) ?>">
                <i class="fa-solid <?= htmlspecialchars($item['icon'] ?? 'fa-circle') ?> nav-item__icon" aria-hidden="true"></i>
                <span class="nav-item__label"><?= htmlspecialchars($item['label']) ?></span>
                <?php if (isset($item['badge'])): ?>
                  <span class="nav-item__badge"><?= (int) $item['badge'] ?></span>
                <?php endif; ?>
              </a>

              <?php if ($kids): ?>
                <ul class="nav-sub">
                  <?php foreach ($kids as $child):
                      $curl = $base_url . ltrim($child['url'] ?? '#', '/');
                      $con  = main_is_active($child['url'] ?? '', $current_page, $current_dir);
                  ?>
                    <li><a class="nav-item nav-item--sub<?= $con ? ' is-active' : '' ?>"
                           href="<?= htmlspecialchars($curl) ?>" <?= $con ? 'aria-current="page"' : '' ?>>
                      <span class="nav-item__label"><?= htmlspecialchars($child['label']) ?></span>
                    </a></li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endforeach; ?>
  </nav>

  <!-- ──────────────────────────────── the foot ───────────────────────────── -->
  <div class="sidebar__foot">

    <?php if ($showSub): ?>
      <div class="sub <?= $subTone ?>">
        <div class="sub__row">
          <span class="sub__badge <?= $isPaid ? 'is-paying' : 'is-trial' ?>">
            <?= $isPaid ? 'Paying' : 'Trial' ?>
          </span>
          <a class="sub__renew" href="<?= $base_url ?>settings/billing.php">Renew</a>
        </div>
        <p class="sub__line">
          <i class="fa-regular fa-clock" aria-hidden="true"></i>
          Expires in <strong><?= $days ?> day<?= $days === 1 ? '' : 's' ?></strong>
        </p>
      </div>
    <?php endif; ?>

    <?php if ($footer_items): ?>
      <ul class="nav-list nav-list--foot">
        <?php foreach ($footer_items as $item):
            $url = $base_url . ltrim($item['url'] ?? '#', '/');
            $on  = main_is_active($item['url'] ?? '', $current_page, $current_dir);
        ?>
          <li>
            <a class="nav-item<?= $on ? ' is-active' : '' ?><?= !empty($item['danger']) ? ' nav-item--danger' : '' ?>"
               href="<?= htmlspecialchars($url) ?>" <?= $on ? 'aria-current="page"' : '' ?>
               data-tip="<?= htmlspecialchars($item['label']) ?>">
              <i class="fa-solid <?= htmlspecialchars($item['icon'] ?? 'fa-circle') ?> nav-item__icon" aria-hidden="true"></i>
              <span class="nav-item__label"><?= htmlspecialchars($item['label']) ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <div class="who" data-menu>
      <button class="who__btn" type="button" aria-expanded="false" aria-haspopup="true"
              aria-label="Account menu" data-menu-btn>
        <span class="who__avatar" aria-hidden="true"><?= htmlspecialchars($user['initials'] ?? '?') ?></span>
        <span class="who__text">
          <strong><?= htmlspecialchars($user['name']) ?></strong>
          <small><?= htmlspecialchars($user['role_label']) ?></small>
        </span>
        <i class="fa-solid fa-chevron-up who__chev" aria-hidden="true"></i>
      </button>

      <div class="menu menu--up" data-menu-panel hidden>
        <a class="menu__item" href="<?= $base_url ?>settings/profile.php"><i class="fa-regular fa-user" aria-hidden="true"></i> My Profile</a>
        <a class="menu__item" href="<?= $base_url ?>settings/password.php"><i class="fa-solid fa-key" aria-hidden="true"></i> Change Password</a>
        <div class="menu__sep" role="separator"></div>
        <a class="menu__item menu__item--danger" href="<?= $base_url ?>logout.php"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i> Logout</a>
      </div>
    </div>
  </div>
</aside>

<div class="scrim" id="scrim" hidden></div>

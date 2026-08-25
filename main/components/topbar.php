<?php
/**
 * Mutendi CMS — church top bar.
 *
 * The Quick Add menu is filtered by exactly the same rules as the sidebar, so
 * a church without the finance module never sees "Record Contribution".
 */

if (!isset($base_url)) { require_once __DIR__ . '/../includes/config.php'; }
if (!function_exists('main_allowed')) { require_once __DIR__ . '/sidebar.php'; }

/* LATER: these become links to the real create screens. */
$quick_add = main_visible([
    ['label' => 'Add Member',          'icon' => 'fa-user-plus',   'url' => 'members/add.php',    'module' => 'members',    'permission' => 'members.add'],
    ['label' => 'Record Attendance',   'icon' => 'fa-square-check','url' => 'attendance/record.php', 'module' => 'attendance'],
    ['label' => 'Record Contribution', 'icon' => 'fa-circle-plus', 'url' => 'finance/record.php',  'module' => 'finance',    'permission' => 'finance.add'],
    ['label' => 'Add Event',           'icon' => 'fa-calendar-plus','url' => 'events/add.php',     'module' => 'events'],
], $enabled_modules, $permissions);

$unread = count($notifications ?? []);
?>

<header class="topbar">

  <!-- Collapses the sidebar on desktop, opens the off-canvas panel on mobile. -->
  <button class="iconbtn topbar__toggle" type="button" id="navToggle"
          aria-label="Toggle navigation" aria-controls="sidebar" aria-expanded="false">
    <i class="fa-solid fa-bars" aria-hidden="true"></i>
  </button>

  <form class="search" role="search" onsubmit="return false">
    <i class="fa-solid fa-magnifying-glass search__icon" aria-hidden="true"></i>
    <input class="search__input" type="search" id="globalSearch"
           placeholder="Search members, contributions, events&hellip;"
           aria-label="Search members, contributions and events">
  </form>

  <!-- Mobile only: opens the search as a full-width overlay bar. -->
  <button class="iconbtn topbar__search" type="button" id="searchOpen" aria-label="Open search">
    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
  </button>

  <div class="topbar__right">

    <?php if ($quick_add): ?>
      <div class="drop" data-menu>
        <button class="quick" type="button" aria-expanded="false" aria-haspopup="true" data-menu-btn>
          <i class="fa-solid fa-plus" aria-hidden="true"></i>
          <span>Quick Add</span>
          <i class="fa-solid fa-chevron-down quick__chev" aria-hidden="true"></i>
        </button>
        <div class="menu" data-menu-panel hidden>
          <?php foreach ($quick_add as $q): ?>
            <a class="menu__item" href="<?= htmlspecialchars($base_url . $q['url']) ?>">
              <i class="fa-solid <?= htmlspecialchars($q['icon']) ?>" aria-hidden="true"></i>
              <?= htmlspecialchars($q['label']) ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="drop" data-menu>
      <button class="iconbtn" type="button" aria-expanded="false" aria-haspopup="true"
              aria-label="Notifications<?= $unread ? ' (' . $unread . ' unread)' : '' ?>" data-menu-btn>
        <i class="fa-regular fa-bell" aria-hidden="true"></i>
        <?php if ($unread): ?><span class="iconbtn__dot"><?= $unread ?></span><?php endif; ?>
      </button>

      <div class="menu menu--wide" data-menu-panel hidden>
        <p class="menu__head">Notifications</p>
        <?php foreach ($notifications ?? [] as $n): ?>
          <a class="note" href="#">
            <span class="note__icon" aria-hidden="true"><i class="fa-solid <?= htmlspecialchars($n['icon']) ?>"></i></span>
            <span class="note__body">
              <span class="note__text"><?= htmlspecialchars($n['text']) ?></span>
              <span class="note__time"><?= htmlspecialchars($n['time']) ?></span>
            </span>
          </a>
        <?php endforeach; ?>
        <div class="menu__sep" role="separator"></div>
        <a class="menu__all" href="#">View all notifications</a>
      </div>
    </div>

    <div class="drop" data-menu>
      <button class="avatarbtn" type="button" aria-expanded="false" aria-haspopup="true"
              aria-label="Account menu" data-menu-btn>
        <span class="avatarbtn__av" aria-hidden="true"><?= htmlspecialchars($user['initials'] ?? '?') ?></span>
        <i class="fa-solid fa-chevron-down avatarbtn__chev" aria-hidden="true"></i>
      </button>

      <div class="menu" data-menu-panel hidden>
        <p class="menu__who">
          <strong><?= htmlspecialchars($user['name']) ?></strong>
          <small><?= htmlspecialchars($user['email']) ?></small>
        </p>
        <div class="menu__sep" role="separator"></div>
        <a class="menu__item" href="<?= $base_url ?>settings/profile.php"><i class="fa-regular fa-user" aria-hidden="true"></i> My Profile</a>
        <?php if (in_array('settings.manage', $permissions, true)): ?>
          <a class="menu__item" href="<?= $base_url ?>settings/profile.php"><i class="fa-solid fa-gear" aria-hidden="true"></i> Settings</a>
        <?php endif; ?>
        <a class="menu__item" href="<?= $base_url ?>help.php"><i class="fa-solid fa-circle-question" aria-hidden="true"></i> Help</a>
        <div class="menu__sep" role="separator"></div>
        <a class="menu__item menu__item--danger" href="<?= $base_url ?>logout.php"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i> Logout</a>
      </div>
    </div>
  </div>
</header>

<!-- Mobile search overlay, opened by the icon above. -->
<div class="searchbar" id="searchBar" hidden>
  <i class="fa-solid fa-magnifying-glass searchbar__icon" aria-hidden="true"></i>
  <input class="searchbar__input" type="search" id="mobileSearch"
         placeholder="Search members, contributions, events&hellip;" aria-label="Search">
  <button class="iconbtn" type="button" id="searchClose" aria-label="Close search">
    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
  </button>
</div>

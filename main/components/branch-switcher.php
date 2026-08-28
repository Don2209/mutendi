<?php
/**
 * Mutendi CMS — branch switcher.
 *
 * Mounted in the top bar, left of the search. Renders nothing at all for a
 * single-church tenant, so an independent church never sees a branch layer it
 * does not have.
 *
 * Three states:
 *   single tenant        nothing
 *   branch-scope user    a static chip naming their branch (no switching)
 *   organisation user    a pill that opens a searchable, keyboard-navigable
 *                        branch list, plus a context bar when one is picked
 *
 * Every label comes from $terminology via t(); the word "Branch" is never
 * hardcoded here.
 */

if (!isset($base_url)) { require_once __DIR__ . '/../includes/config.php'; }

/* ------------------------------------------------------------- selection -- */

if (!function_exists('branch_resolve_current')) {
    /**
     * Which branch is in view. Organisation-scope users choose with ?branch=;
     * branch-scope users are pinned to their own and the request cannot move
     * them. An id they may not see falls back to 'all'.
     */
    function branch_resolve_current()
    {
        global $user;

        if (($user['scope'] ?? 'organisation') === 'branch') {
            return $user['branch_id'] ?? null;
        }

        $req = $_GET['branch'] ?? null;
        if ($req === null || $req === '' || $req === 'all') { return 'all'; }

        $id = (int) $req;
        return (get_branch($id) && can_see_branch($id)) ? $id : 'all';
    }
}

/* The component owns the live value; config.php seeds the default. */
$current_branch = branch_resolve_current();

if (!function_exists('branch_url')) {
    /**
     * A link to the current page with ?branch= swapped, keeping whatever else
     * is on the query string (the demo role switcher rides on ?role=).
     */
    function branch_url($branch_id): string
    {
        $q = $_GET;
        if ($branch_id === 'all') { unset($q['branch']); }
        else { $q['branch'] = (int) $branch_id; }

        $path = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
        $qs   = http_build_query($q);
        return htmlspecialchars($path . ($qs !== '' ? '?' . $qs : ''));
    }
}

if (!function_exists('branch_avc')) {
    /** Deterministic avatar colour, matching the .av-c0..9 classes. */
    function branch_avc(string $name): string { return 'av-c' . (crc32($name) % 10); }
}

if (!function_exists('branch_initials')) {
    function branch_initials(string $name): string
    {
        /* Drop the saint/honorific so "St Mary's Cathedral" reads MC, not SM. */
        $clean = preg_replace('/^(St\.?|Saint|Holy|All)\s+/i', '', trim($name));
        $parts = preg_split('/\s+/', $clean ?: $name) ?: [];
        $a = mb_substr($parts[0] ?? '', 0, 1);
        $b = count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) : '';
        return mb_strtoupper($a . $b);
    }
}

if (!function_exists('branch_total_members')) {
    /** Organisation-wide member total across the branches this user can see. */
    function branch_total_members(): int
    {
        $n = 0;
        foreach (get_visible_branches() as $b) { $n += (int) ($b['members_count'] ?? 0); }
        return $n;
    }
}

/* ---------------------------------------------------------------- render -- */

if (!function_exists('branch_switcher_render')) {
    /** The pill (or static chip) that sits in the top bar. */
    function branch_switcher_render(): void
    {
        global $user, $current_branch, $organisation, $permissions;

        if (!is_multi_branch()) { return; }

        /* Branch-scope users get context, not control. */
        if (($user['scope'] ?? 'organisation') === 'branch') {
            $b = get_branch($user['branch_id'] ?? 0);
            $name = $b['name'] ?? ($user['branch_name'] ?? t('branch_singular'));
            ?>
            <span class="bswitch__static" title="<?= htmlspecialchars($name) ?>">
              <i class="fa-solid fa-church" aria-hidden="true"></i>
              <span class="bswitch__name"><?= htmlspecialchars($name) ?></span>
              <?php if ($b): ?>
                <span class="bswitch__count"><?= number_format((int) $b['members_count']) ?></span>
              <?php endif; ?>
            </span>
            <?php
            return;
        }

        $all      = $current_branch === 'all' || $current_branch === null;
        $active   = $all ? null : get_branch($current_branch);
        $label    = $all ? 'All ' . t('branch_plural') : ($active['name'] ?? 'All ' . t('branch_plural'));
        $count    = $all ? branch_total_members() : (int) ($active['members_count'] ?? 0);
        $branches = get_visible_branches();

        /* Grouped under their group_name, in the order they appear. */
        $groups = [];
        foreach ($branches as $b) { $groups[$b['group_name'] ?? t('group_plural')][] = $b; }
        ?>
        <div class="bswitch" id="bswitch">
          <button class="bswitch__btn" type="button" id="bswitchBtn"
                  aria-haspopup="listbox" aria-expanded="false" aria-controls="bswitchPanel">
            <i class="fa-solid fa-church bswitch__icon" aria-hidden="true"></i>
            <span class="bswitch__name" data-bswitch-label><?= htmlspecialchars($label) ?></span>
            <span class="bswitch__count"><?= number_format($count) ?></span>
            <i class="fa-solid fa-chevron-down bswitch__chev" aria-hidden="true"></i>
          </button>

          <div class="bswitch__panel" id="bswitchPanel" role="listbox"
               aria-label="Choose <?= htmlspecialchars(t('branch_singular')) ?>" hidden>

            <div class="bswitch__search">
              <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
              <input class="input" type="search" id="bswitchSearch" autocomplete="off"
                     placeholder="Search <?= htmlspecialchars(mb_strtolower(t('branch_plural'))) ?>&hellip;"
                     aria-label="Search <?= htmlspecialchars(mb_strtolower(t('branch_plural'))) ?>">
            </div>

            <div class="bswitch__list" id="bswitchList">

              <a class="bswitch__row bswitch__row--all<?= $all ? ' is-on' : '' ?>"
                 href="<?= branch_url('all') ?>" role="option"
                 aria-selected="<?= $all ? 'true' : 'false' ?>"
                 data-brow data-label="<?= htmlspecialchars(mb_strtolower('All ' . t('branch_plural'))) ?>">
                <span class="bswitch__allicon" aria-hidden="true"><i class="fa-solid fa-layer-group"></i></span>
                <span class="bswitch__rowtext">
                  <span class="bswitch__rowname">All <?= htmlspecialchars(t('branch_plural')) ?></span>
                  <span class="bswitch__rowmeta"><?= htmlspecialchars($organisation['name']) ?></span>
                </span>
                <span class="bswitch__rowcount"><?= number_format(branch_total_members()) ?></span>
                <?php if ($all): ?><i class="fa-solid fa-check bswitch__tick" aria-hidden="true"></i><?php endif; ?>
              </a>

              <div class="bswitch__sep" role="separator"></div>

              <?php foreach ($groups as $groupName => $list): ?>
                <p class="bswitch__group" data-bgroup><?= htmlspecialchars($groupName) ?></p>
                <?php foreach ($list as $b):
                    $on = !$all && (int) $b['id'] === (int) $current_branch;
                ?>
                  <a class="bswitch__row<?= $on ? ' is-on' : '' ?>"
                     href="<?= branch_url($b['id']) ?>" role="option"
                     aria-selected="<?= $on ? 'true' : 'false' ?>"
                     data-brow
                     data-label="<?= htmlspecialchars(mb_strtolower($b['name'] . ' ' . $b['code'] . ' ' . $b['leader_name'] . ' ' . $b['suburb'])) ?>">
                    <span class="av av--sm <?= branch_avc($b['name']) ?>" aria-hidden="true"><?= htmlspecialchars(branch_initials($b['name'])) ?></span>
                    <span class="bswitch__rowtext">
                      <span class="bswitch__rowname">
                        <?= htmlspecialchars($b['name']) ?>
                        <?php if (($b['type'] ?? '') === 'head_office'): ?>
                          <span class="bswitch__ho"><?= htmlspecialchars(t('org_singular')) ?></span>
                        <?php endif; ?>
                      </span>
                      <span class="bswitch__rowmeta">
                        <?= htmlspecialchars($b['code']) ?> &middot;
                        <?= htmlspecialchars(t('leader_title')) ?> <?= htmlspecialchars($b['leader_name']) ?>
                      </span>
                    </span>
                    <span class="bswitch__rowcount"><?= number_format((int) $b['members_count']) ?></span>
                    <span class="bswitch__dot is-<?= htmlspecialchars($b['status']) ?>"
                          title="<?= htmlspecialchars(ucfirst($b['status'])) ?>"
                          aria-label="<?= htmlspecialchars(ucfirst($b['status'])) ?>"></span>
                    <?php if ($on): ?><i class="fa-solid fa-check bswitch__tick" aria-hidden="true"></i><?php endif; ?>
                  </a>
                <?php endforeach; ?>
              <?php endforeach; ?>

              <p class="bswitch__empty" data-bempty hidden>
                No <?= htmlspecialchars(mb_strtolower(t('branch_plural'))) ?> match that search.
              </p>
            </div>

            <a class="bswitch__foot" href="<?= $GLOBALS['base_url'] ?>branches/index.php">
              Manage <?= htmlspecialchars(t('branch_plural')) ?> <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </a>

            <?php
              /* Adding a branch is an organisation-level act: only for a
                 multi-branch tenant, an organisation-scope user who holds
                 branches.add. Otherwise the row does not exist. */
              $may_add_branch = ($user['scope'] ?? 'organisation') !== 'branch'
                  && in_array('branches.add', (array) $permissions, true);
              if ($may_add_branch):
            ?>
              <a class="bswitch__add" href="<?= $GLOBALS['base_url'] ?>branches/add.php">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                Add <?= htmlspecialchars(t('branch_singular')) ?>
              </a>
            <?php endif; ?>
          </div>
        </div>
        <?php
    }
}

if (!function_exists('branch_switcher_after')) {
    /**
     * The context bar and the component's script, both rendered immediately
     * after the top bar closes.
     */
    function branch_switcher_after(): void
    {
        global $user, $current_branch;

        if (!is_multi_branch()) { return; }

        $scope_branch = ($user['scope'] ?? 'organisation') === 'branch';
        $viewing = !$scope_branch && $current_branch !== 'all' && $current_branch !== null;

        if ($viewing) {
            $b = get_branch($current_branch);
            if ($b): ?>
              <div class="bcontext" role="status">
                <i class="fa-solid fa-eye" aria-hidden="true"></i>
                <p>Viewing: <strong><?= htmlspecialchars($b['name']) ?></strong>
                  <span class="bcontext__meta"><?= htmlspecialchars($b['code']) ?> &middot; <?= number_format((int) $b['members_count']) ?> members</span>
                </p>
                <a class="bcontext__clear" href="<?= branch_url('all') ?>">
                  <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                  View all <?= htmlspecialchars(t('branch_plural')) ?>
                </a>
              </div>
            <?php endif;
        }

        /* Only organisation-scope users have an interactive panel to drive. */
        if ($scope_branch) { return; }
        ?>
        <script>
        /* Branch switcher: open/close, type-to-filter, arrow-key navigation.
           Self-contained — it deliberately avoids the shared [data-menu]
           handler in footer.php so the two cannot fight over the same panel. */
        (function () {
          'use strict';
          var root = document.getElementById('bswitch');
          if (!root) { return; }

          var btn    = document.getElementById('bswitchBtn'),
              panel  = document.getElementById('bswitchPanel'),
              search = document.getElementById('bswitchSearch'),
              list   = document.getElementById('bswitchList'),
              empty  = list.querySelector('[data-bempty]'),
              idx    = -1;

          function rows() {
            return [].slice.call(list.querySelectorAll('[data-brow]')).filter(function (r) { return !r.hidden; });
          }

          function open() {
            panel.hidden = false;
            btn.setAttribute('aria-expanded', 'true');
            idx = -1;
            search.value = '';
            filter();
            search.focus();
          }
          function close(focusBtn) {
            panel.hidden = true;
            btn.setAttribute('aria-expanded', 'false');
            clearActive();
            if (focusBtn) { btn.focus(); }
          }
          function clearActive() {
            [].forEach.call(list.querySelectorAll('.is-cursor'), function (r) { r.classList.remove('is-cursor'); });
          }
          function move(step) {
            var r = rows();
            if (!r.length) { return; }
            idx = (idx + step + r.length) % r.length;
            clearActive();
            r[idx].classList.add('is-cursor');
            r[idx].scrollIntoView({ block: 'nearest' });
          }

          function filter() {
            var q = search.value.trim().toLowerCase(), shown = 0;
            [].forEach.call(list.querySelectorAll('[data-brow]'), function (r) {
              var ok = !q || (r.getAttribute('data-label') || '').indexOf(q) !== -1;
              r.hidden = !ok;
              if (ok) { shown++; }
            });
            /* A group heading with nothing under it is noise. */
            [].forEach.call(list.querySelectorAll('[data-bgroup]'), function (h) {
              var any = false, n = h.nextElementSibling;
              while (n && !n.hasAttribute('data-bgroup')) {
                if (n.hasAttribute('data-brow') && !n.hidden) { any = true; break; }
                n = n.nextElementSibling;
              }
              h.hidden = !any;
            });
            if (empty) { empty.hidden = shown !== 0; }
            idx = -1;
            clearActive();
          }

          btn.addEventListener('click', function (e) {
            e.stopPropagation();
            panel.hidden ? open() : close(false);
          });
          search.addEventListener('input', filter);
          panel.addEventListener('click', function (e) { e.stopPropagation(); });

          document.addEventListener('click', function (e) {
            if (!panel.hidden && !root.contains(e.target)) { close(false); }
          });

          root.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { e.preventDefault(); close(true); return; }
            if (panel.hidden) { return; }
            if (e.key === 'ArrowDown') { e.preventDefault(); move(1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
            else if (e.key === 'Enter') {
              var r = rows();
              if (idx >= 0 && r[idx]) { e.preventDefault(); window.location.href = r[idx].getAttribute('href'); }
            }
          });
        })();
        </script>
        <?php
    }
}

<?php
/**
 * Mutendi CMS — dashboard widget rendering.
 *
 * index.php stays a loop over a filtered, sorted widget list; every widget's
 * actual markup lives here, one function per type (plus one per table/list
 * key, since their columns differ). Nothing in here decides WHICH widgets to
 * show — that filtering happens once, before render_widget() is ever called.
 */

if (!isset($base_url)) { require_once __DIR__ . '/../includes/config.php'; }

/* main_allowed() also lives in sidebar.php, guarded the same way — but that
   file's job is to immediately echo the <aside> markup once its functions are
   defined, so require_once-ing it here (index.php loads widgets.php before
   header.php pulls sidebar.php in) would print the sidebar a second time,
   before <!DOCTYPE html>. Defining an identical copy here avoids ever
   touching that file's side effect; whichever of the two loads first wins,
   and both implementations are the same one rule. */
if (!function_exists('main_allowed')) {
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

/* --------------------------------------------------------- filter + sort -- */

if (!function_exists('main_widgets_for_role')) {
    /**
     * The same module/permission gate the sidebar uses (main_allowed), plus a
     * check against roles_hidden, then sorted by this role's priority. A role
     * with no entry in a widget's priority map — including a role key that
     * does not exist anywhere in this file — falls back to 'default', so an
     * unknown role still gets a complete, sensibly-ordered dashboard.
     */
    function main_widgets_for_role(array $widgets, array $modules, array $perms, string $role, bool $org_mode = false): array
    {
        $out = [];
        foreach ($widgets as $key => $w) {
            if (!main_allowed($w, $modules, $perms)) { continue; }
            if (in_array($role, $w['roles_hidden'] ?? [], true)) { continue; }
            /* Organisation-level widgets exist only in organisation mode; in
               every other mode they are absent, not hidden. */
            if (!empty($w['org_only']) && !$org_mode) { continue; }
            $w['key']      = $key;
            /* Organisation widgets carry a placeholder English title in the
               registry; the client's own words come from $terminology, so an
               Anglican diocese reads "Total Parishes", not "Total Branches".
               Done here so the Customise modal gets the same labels. */
            if (!empty($w['org_only'])) { $w['title'] = widget_org_title($key, $w['title']); }
            $w['_order']   = $w['priority'][$role] ?? $w['priority']['default'] ?? 999;
            $out[]         = $w;
        }
        usort($out, fn($a, $b) => $a['_order'] <=> $b['_order']);
        return $out;
    }
}

if (!function_exists('widget_org_title')) {
    /** The organisation widgets' titles, in this client's own terminology. */
    function widget_org_title(string $key, string $fallback): string
    {
        switch ($key) {
            case 'total_branches':       return 'Total ' . t('branch_plural');
            case 'org_attendance_total': return t('org_singular') . ' Attendance';
            case 'org_giving_total':     return t('org_singular') . ' Giving';
            case 'branch_comparison':    return t('branch_singular') . ' Comparison';
            case 'branches_attention':   return t('branch_plural') . ' Needing Attention';
            case 'branch_leaderboard':   return 'Fastest Growing';
        }
        return $fallback;
    }
}

if (!function_exists('widget_category')) {
    function widget_category(string $type, bool $org_only = false): string
    {
        /* Organisation widgets group under whatever this client calls its
           organisation, so the Customise modal reads in their own language. */
        if ($org_only) { return t('org_singular'); }
        return match ($type) {
            'kpi'                          => 'Key figures',
            'chart'                        => 'Charts',
            'list', 'table', 'feed'        => 'Lists & tables',
            default                        => 'Special',
        };
    }
}

/* ----------------------------------------------------------------- tone --- */

if (!function_exists('widget_tone_class')) {
    function widget_tone_class(?string $tone): string
    {
        return 'is-' . ($tone ?: 'brand');
    }
}

/* ------------------------------------------------------------- card shell -- */

if (!function_exists('widget_open')) {
    function widget_open(array $w): void
    {
        ?>
        <section class="widget widget--<?= htmlspecialchars($w['size']) ?> widget--<?= htmlspecialchars($w['type']) ?>"
                 data-widget="<?= htmlspecialchars($w['key']) ?>">
          <header class="widget__head">
            <span class="widget__icon" aria-hidden="true"><i class="fa-solid <?= htmlspecialchars($w['icon']) ?>"></i></span>
            <h2 class="widget__title"><?= htmlspecialchars($w['title']) ?></h2>
            <div class="drop widget__menu" data-menu>
              <button class="iconbtn iconbtn--sm" type="button" aria-haspopup="true" aria-expanded="false"
                      aria-label="<?= htmlspecialchars($w['title']) ?> options" data-menu-btn>
                <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
              </button>
              <div class="menu menu--sm" data-menu-panel hidden>
                <a class="menu__item" href="#" data-widget-refresh><i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i> Refresh</a>
                <a class="menu__item" href="#" data-widget-hide><i class="fa-regular fa-eye-slash" aria-hidden="true"></i> Hide</a>
                <div class="menu__sep" role="separator"></div>
                <a class="menu__item" href="<?= htmlspecialchars($base_url . 'reports/' . $w['key'] . '.php') ?>"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> View Full Report</a>
              </div>
            </div>
          </header>
          <div class="widget__body">
        <?php
    }
}

if (!function_exists('widget_close')) {
    function widget_close(): void
    {
        ?>
          </div>
        </section>
        <?php
    }
}

if (!function_exists('widget_empty')) {
    /** The empty-state prompt any data-driven renderer falls back to. */
    function widget_empty(string $message = 'Nothing to show yet'): void
    {
        ?>
        <div class="widget__empty">
          <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
          <p><?= htmlspecialchars($message) ?></p>
        </div>
        <?php
    }
}

/* ------------------------------------------------------------------- KPI -- */

if (!function_exists('widget_render_kpi')) {
    function widget_render_kpi(array $w, array $d, string $base_url): void
    {
        if (!$d) { widget_empty(); return; }
        $tone = widget_tone_class($d['tone'] ?? null);
        ?>
        <a class="kpi <?= $tone ?>" href="<?= htmlspecialchars($base_url . 'reports/' . $w['key'] . '.php') ?>">
          <p class="kpi__value"><?= htmlspecialchars($d['value']) ?></p>
          <?php if (!empty($d['change'])): ?>
            <p class="kpi__change<?= $d['trend'] ? ' is-' . htmlspecialchars($d['trend']) : '' ?>">
              <?php if ($d['trend'] === 'up'): ?><i class="fa-solid fa-arrow-trend-up" aria-hidden="true"></i>
              <?php elseif ($d['trend'] === 'down'): ?><i class="fa-solid fa-arrow-trend-down" aria-hidden="true"></i>
              <?php endif; ?>
              <?= htmlspecialchars($d['change']) ?>
            </p>
          <?php endif; ?>
        </a>
        <?php
    }
}

/* ----------------------------------------------------------------- chart -- */

if (!function_exists('widget_render_chart')) {
    /**
     * Emits a bare <canvas> carrying its own data as a data-attribute; a
     * single script in index.php finds every [data-chart] canvas on the page
     * and builds one Chart.js instance per element. Keeps Chart.js
     * initialisation in one place instead of scattered per widget.
     */
    function widget_render_chart(array $w, array $d): void
    {
        if (empty($d['labels'])) { widget_empty(); return; }
        $payload = ['kind' => $d['kind'], 'labels' => $d['labels'], 'series' => $d['series']];
        ?>
        <div class="chart-wrap">
          <canvas class="chart__canvas" id="chart-<?= htmlspecialchars($w['key']) ?>"
                  data-chart='<?= htmlspecialchars(json_encode($payload), ENT_QUOTES) ?>'
                  role="img" aria-label="<?= htmlspecialchars($w['title']) ?> chart"></canvas>
        </div>
        <?php
    }
}

/* -------------------------------------------------------- lists & tables -- */

if (!function_exists('widget_render_recent_members')) {
    function widget_render_recent_members(array $d, string $base_url): void
    {
        if (!$d) { widget_empty(); return; }
        ?>
        <div class="table-wrap">
          <table class="mini-table">
            <thead><tr><th>Member</th><th>Phone</th><th>Joined</th></tr></thead>
            <tbody>
              <?php foreach ($d as $row): ?>
                <tr>
                  <td>
                    <span class="cell-person">
                      <span class="cell-av" aria-hidden="true"><?= htmlspecialchars($row['initials']) ?></span>
                      <?= htmlspecialchars($row['name']) ?>
                    </span>
                  </td>
                  <td class="is-muted"><?= htmlspecialchars($row['phone']) ?></td>
                  <td class="is-muted"><?= htmlspecialchars(date('d M', strtotime($row['joined']))) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <a class="widget__more" href="<?= htmlspecialchars($base_url . 'members/all.php') ?>">View all members <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
        <?php
    }
}

if (!function_exists('widget_render_upcoming_events_list')) {
    function widget_render_upcoming_events_list(array $d): void
    {
        if (!$d) { widget_empty('No upcoming events'); return; }
        ?>
        <ul class="event-list">
          <?php foreach ($d as $e): ?>
            <li class="event-row">
              <span class="event-date" aria-hidden="true">
                <strong><?= htmlspecialchars($e['day']) ?></strong>
                <small><?= htmlspecialchars($e['mon']) ?></small>
              </span>
              <span class="event-info">
                <strong><?= htmlspecialchars($e['title']) ?></strong>
                <small><i class="fa-regular fa-clock" aria-hidden="true"></i> <?= htmlspecialchars($e['time']) ?> &middot; <?= htmlspecialchars($e['venue']) ?></small>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php
    }
}

if (!function_exists('widget_render_birthdays_list')) {
    function widget_render_birthdays_list(array $d): void
    {
        if (!$d) { widget_empty('No birthdays this week'); return; }
        ?>
        <ul class="person-list">
          <?php foreach ($d as $p): ?>
            <li class="person-row">
              <span class="cell-av" aria-hidden="true"><?= htmlspecialchars($p['initials']) ?></span>
              <span class="person-info">
                <strong><?= htmlspecialchars($p['name']) ?></strong>
                <small><?= htmlspecialchars($p['date']) ?></small>
              </span>
              <button class="chip-btn" type="button"><i class="fa-regular fa-gift" aria-hidden="true"></i> Send Wish</button>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php
    }
}

if (!function_exists('widget_render_followup_list')) {
    function widget_render_followup_list(array $d): void
    {
        if (!$d) { widget_empty('No visitors awaiting follow-up'); return; }
        ?>
        <div class="table-wrap">
          <table class="mini-table">
            <thead><tr><th>Visitor</th><th>Visited</th><th>Waiting</th><th>Assigned</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($d as $row): ?>
                <tr>
                  <td><?= htmlspecialchars($row['name']) ?></td>
                  <td class="is-muted"><?= htmlspecialchars(date('d M', strtotime($row['visited']))) ?></td>
                  <td><span class="pill <?= $row['days'] >= 5 ? 'is-danger' : 'is-warn' ?>"><?= (int) $row['days'] ?>d</span></td>
                  <td class="is-muted"><?= htmlspecialchars($row['assigned']) ?></td>
                  <td><button class="chip-btn" type="button">Follow Up</button></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php
    }
}

if (!function_exists('widget_render_recent_contributions')) {
    function widget_render_recent_contributions(array $d): void
    {
        if (!$d) { widget_empty('No contributions recorded yet'); return; }
        ?>
        <div class="table-wrap">
          <table class="mini-table">
            <thead><tr><th>Member</th><th>Type</th><th>Amount</th><th>Date</th></tr></thead>
            <tbody>
              <?php foreach ($d as $row): ?>
                <tr>
                  <td><?= htmlspecialchars($row['member']) ?></td>
                  <td><span class="pill is-brand"><?= htmlspecialchars($row['type']) ?></span></td>
                  <td class="is-strong"><?= htmlspecialchars($row['amount']) ?></td>
                  <td class="is-muted"><?= htmlspecialchars(date('d M', strtotime($row['date']))) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php
    }
}

if (!function_exists('widget_render_top_givers')) {
    function widget_render_top_givers(array $d): void
    {
        if (!$d) { widget_empty(); return; }
        ?>
        <ul class="bar-list">
          <?php foreach ($d as $row): ?>
            <li class="bar-row">
              <span class="cell-av" aria-hidden="true"><?= htmlspecialchars($row['initials']) ?></span>
              <span class="bar-row__info">
                <span class="bar-row__top"><?= htmlspecialchars($row['name']) ?> <strong><?= htmlspecialchars($row['amount']) ?></strong></span>
                <span class="bar-row__track"><span class="bar-row__fill" style="width:<?= (int) $row['pct'] ?>%"></span></span>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php
    }
}

if (!function_exists('widget_render_department_summary')) {
    function widget_render_department_summary(array $d): void
    {
        if (!$d) { widget_empty(); return; }
        ?>
        <ul class="bar-list">
          <?php foreach ($d as $row): ?>
            <li class="bar-row">
              <span class="bar-row__info">
                <span class="bar-row__top"><?= htmlspecialchars($row['name']) ?> <strong><?= (int) $row['count'] ?></strong></span>
                <span class="bar-row__track"><span class="bar-row__fill" style="width:<?= (int) $row['pct'] ?>%"></span></span>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php
    }
}

if (!function_exists('widget_render_cell_group_summary')) {
    function widget_render_cell_group_summary(array $d): void
    {
        if (!$d) { widget_empty(); return; }
        ?>
        <div class="table-wrap">
          <table class="mini-table">
            <thead><tr><th>Cell Group</th><th>Leader</th><th>Members</th><th>Last Meeting</th></tr></thead>
            <tbody>
              <?php foreach ($d as $row): ?>
                <tr>
                  <td class="is-strong"><?= htmlspecialchars($row['name']) ?></td>
                  <td class="is-muted"><?= htmlspecialchars($row['leader']) ?></td>
                  <td><?= (int) $row['members'] ?></td>
                  <td class="is-muted"><?= htmlspecialchars(date('d M', strtotime($row['last_meeting']))) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php
    }
}

if (!function_exists('widget_render_recent_announcements')) {
    function widget_render_recent_announcements(array $d): void
    {
        if (!$d) { widget_empty('No announcements yet'); return; }
        ?>
        <ul class="feed-list">
          <?php foreach ($d as $row): ?>
            <li class="feed-row">
              <strong><?= htmlspecialchars($row['title']) ?></strong>
              <span class="feed-row__time"><?= htmlspecialchars($row['time']) ?></span>
              <p><?= htmlspecialchars($row['excerpt']) ?></p>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php
    }
}

if (!function_exists('widget_render_my_tasks')) {
    function widget_render_my_tasks(array $d): void
    {
        if (!$d) { widget_empty('No tasks assigned'); return; }
        ?>
        <ul class="task-list">
          <?php foreach ($d as $i => $row): ?>
            <li class="task-row">
              <label class="task-check">
                <input type="checkbox" <?= !empty($row['done']) ? 'checked' : '' ?> id="task-<?= $i ?>">
                <span class="task-check__box" aria-hidden="true"><i class="fa-solid fa-check"></i></span>
                <span class="task-check__label"><?= htmlspecialchars($row['label']) ?></span>
              </label>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php
    }
}

if (!function_exists('widget_render_inactive_members')) {
    function widget_render_inactive_members(array $d): void
    {
        if (!$d) { widget_empty('No inactive members'); return; }
        ?>
        <div class="table-wrap">
          <table class="mini-table">
            <thead><tr><th>Member</th><th>Last Seen</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($d as $row): ?>
                <tr>
                  <td>
                    <span class="cell-person">
                      <span class="cell-av" aria-hidden="true"><?= htmlspecialchars($row['initials']) ?></span>
                      <?= htmlspecialchars($row['name']) ?>
                    </span>
                  </td>
                  <td><span class="pill is-danger"><?= (int) $row['last_seen'] ?> days</span></td>
                  <td><button class="chip-btn" type="button">Contact</button></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php
    }
}

/* --------------------------------------------------------------- special -- */

if (!function_exists('widget_render_quick_actions')) {
    function widget_render_quick_actions(array $d, array $modules, array $perms, string $base_url): void
    {
        $visible = array_values(array_filter($d, fn($a) => main_allowed($a, $modules, $perms)));
        if (!$visible) { widget_empty('No quick actions available'); return; }
        ?>
        <div class="tile-row">
          <?php foreach ($visible as $a): ?>
            <a class="tile" href="<?= htmlspecialchars($base_url . $a['url']) ?>">
              <span class="tile__icon" aria-hidden="true"><i class="fa-solid <?= htmlspecialchars($a['icon']) ?>"></i></span>
              <span class="tile__label"><?= htmlspecialchars($a['label']) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
        <?php
    }
}

if (!function_exists('widget_render_calendar')) {
    function widget_render_calendar(array $d): void
    {
        $events = $d['events'] ?? [];
        $today  = (int) date('j');
        $selected = isset($events[$today]) ? $today : (array_key_first($events) ?: $today);

        $month = (int) date('n'); $year = (int) date('Y'); $daysInMonth = (int) date('t');
        $firstDow = (int) date('N', mktime(0, 0, 0, $month, 1, $year)); /* 1=Mon..7=Sun */

        /* The full event map, keyed by day number, handed to the click
           handler in index.php's script so re-selecting a day needs no
           round trip — it already has every day's list. */
        $events_json = htmlspecialchars(json_encode((object) $events), ENT_QUOTES);
        ?>
        <div class="cal" data-events='<?= $events_json ?>'>
          <div class="cal__grid">
            <p class="cal__month"><?= htmlspecialchars(date('F Y')) ?></p>
            <div class="cal__dow">
              <?php foreach (['M','T','W','T','F','S','S'] as $d0): ?><span><?= $d0 ?></span><?php endforeach; ?>
            </div>
            <div class="cal__days">
              <?php for ($i = 1; $i < $firstDow; $i++): ?><span class="cal__day is-blank" aria-hidden="true"></span><?php endfor; ?>
              <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                <button class="cal__day<?= $day === $today ? ' is-today' : '' ?><?= $day === $selected ? ' is-selected' : '' ?><?= isset($events[$day]) ? ' has-event' : '' ?>"
                        type="button" data-day="<?= $day ?>"
                        data-label="<?= htmlspecialchars(date('D, j F', mktime(0, 0, 0, $month, $day, $year))) ?>">
                  <?= $day ?>
                  <?php if (isset($events[$day])): ?><i class="cal__dot" aria-hidden="true"></i><?php endif; ?>
                </button>
              <?php endfor; ?>
            </div>
          </div>
          <div class="cal__side">
            <?php if (isset($events[$selected])): ?>
              <p class="cal__side-date"><?= htmlspecialchars(date('D, j F', mktime(0, 0, 0, $month, $selected, $year))) ?></p>
              <ul class="cal__side-list">
                <?php foreach ($events[$selected] as $title): ?><li><?= htmlspecialchars($title) ?></li><?php endforeach; ?>
              </ul>
            <?php else: ?>
              <p class="cal__side-empty">No events on this day</p>
            <?php endif; ?>
          </div>
        </div>
        <?php
    }
}

if (!function_exists('widget_render_giving_goal')) {
    function widget_render_giving_goal(array $d): void
    {
        if (empty($d['target'])) { widget_empty(); return; }
        $pct = min(100, (int) round(($d['current'] / max(1, $d['target'])) * 100));
        $deg = round($pct * 3.6);
        ?>
        <div class="ring-wrap">
          <div class="ring" style="--pct:<?= $deg ?>deg">
            <span class="ring__pct"><?= $pct ?>%</span>
          </div>
          <p class="ring__caption">$<?= number_format($d['current']) ?> of $<?= number_format($d['target']) ?></p>
        </div>
        <?php
    }
}

if (!function_exists('widget_render_project_progress')) {
    function widget_render_project_progress(array $d): void
    {
        if (!$d) { widget_empty('No active projects'); return; }
        ?>
        <ul class="proj-list">
          <?php foreach ($d as $p):
              $pct = min(100, (int) round(($p['raised'] / max(1, $p['target'])) * 100));
          ?>
            <li class="proj-row">
              <span class="proj-row__top">
                <strong><?= htmlspecialchars($p['name']) ?></strong>
                <span>$<?= number_format($p['raised']) ?> / $<?= number_format($p['target']) ?></span>
              </span>
              <span class="bar-row__track"><span class="bar-row__fill" style="width:<?= $pct ?>%"></span></span>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php
    }
}

/* ------------------------------------------------- organisation widgets -- */

if (!function_exists('widget_render_branch_comparison')) {
    /**
     * One bar per branch, on a metric the user picks. The canvas carries the
     * whole dataset so the dropdown can re-render without a round trip.
     */
    function widget_render_branch_comparison(array $d): void
    {
        if (empty($d['branches'])) { widget_empty('No ' . mb_strtolower(t('branch_plural')) . ' to compare'); return; }
        ?>
        <div class="orgwidget__bar">
          <label class="orgwidget__label" for="cmpMetric">Compare on</label>
          <select class="select" id="cmpMetric" style="width:auto">
            <option value="members">Members</option>
            <option value="attendance">Attendance</option>
            <?php if (!empty($d['can_finance'])): ?><option value="giving">Giving</option><?php endif; ?>
            <option value="growth">Growth</option>
          </select>
        </div>
        <div class="chart-wrap" style="height:250px">
          <canvas id="branchCompareChart"
                  data-branch-compare='<?= htmlspecialchars(json_encode($d['branches']), ENT_QUOTES) ?>'
                  role="img" aria-label="<?= htmlspecialchars(t('branch_plural')) ?> compared"></canvas>
        </div>
        <?php
    }
}

if (!function_exists('widget_render_branch_leaderboard')) {
    /** Top five by growth, with medals for the podium. */
    function widget_render_branch_leaderboard(array $d, string $base_url): void
    {
        if (!$d) { widget_empty(); return; }
        $max = max(1, max(array_map(fn($b) => abs($b['growth_percent']), $d)));
        ?>
        <ul class="bar-list">
          <?php foreach ($d as $i => $b):
              $medal = [0 => 'is-gold', 1 => 'is-silver', 2 => 'is-bronze'][$i] ?? '';
              $pct = (int) round(abs($b['growth_percent']) / $max * 100);
          ?>
            <li class="bar-row">
              <?php if ($i < 3): ?>
                <span class="medal <?= $medal ?>" aria-label="Rank <?= $i + 1 ?>"><i class="fa-solid fa-medal" aria-hidden="true"></i><?= $i + 1 ?></span>
              <?php else: ?>
                <span class="orgwidget__rank"><?= $i + 1 ?></span>
              <?php endif; ?>
              <span class="bar-row__info">
                <span class="bar-row__top">
                  <a href="<?= htmlspecialchars($base_url . 'branches/view.php?id=' . (int) $b['id']) ?>" class="orgwidget__link"><?= htmlspecialchars($b['name']) ?></a>
                  <strong class="growth <?= $b['growth_percent'] >= 0 ? 'is-up' : 'is-down' ?>">
                    <i class="fa-solid fa-arrow-<?= $b['growth_percent'] >= 0 ? 'up' : 'down' ?>" aria-hidden="true"></i>
                    <?= number_format(abs($b['growth_percent']), 1) ?>%
                  </strong>
                </span>
                <span class="bar-row__track"><span class="bar-row__fill" style="width:<?= $pct ?>%"></span></span>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php
    }
}

if (!function_exists('widget_render_branches_attention')) {
    /** Declining attendance, or nothing recorded in 30+ days. */
    function widget_render_branches_attention(array $d, string $base_url): void
    {
        if (!$d) {
            ?>
            <div class="widget__empty">
              <i class="fa-solid fa-circle-check" style="color:var(--ok)" aria-hidden="true"></i>
              <p>Every <?= htmlspecialchars(mb_strtolower(t('branch_singular'))) ?> is on track</p>
            </div>
            <?php
            return;
        }
        ?>
        <div class="table-wrap">
          <table class="mini-table">
            <thead><tr><th><?= htmlspecialchars(t('branch_singular')) ?></th><th><?= htmlspecialchars(t('leader_title')) ?></th><th>Inactive</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($d as $b): ?>
                <tr>
                  <td>
                    <a class="orgwidget__link" href="<?= htmlspecialchars($base_url . 'branches/view.php?id=' . (int) $b['id']) ?>"><?= htmlspecialchars($b['name']) ?></a>
                    <span class="tsub"><?= htmlspecialchars($b['reason']) ?></span>
                  </td>
                  <td class="is-muted nowrap"><?= htmlspecialchars($b['leader_name']) ?></td>
                  <td><span class="pill <?= $b['days'] >= 30 ? 'is-danger' : 'is-warn' ?>"><?= (int) $b['days'] ?>d</span></td>
                  <td><a class="chip-btn" href="tel:<?= htmlspecialchars(str_replace(' ', '', $b['leader_phone'])) ?>">Contact</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php
    }
}

/* ----------------------------------------------------------------- entry -- */

if (!function_exists('render_widget')) {
    /** The one call index.php makes per widget: chrome, then the right body. */
    function render_widget(array $w, array $widget_data, array $enabled_modules, array $permissions, string $base_url): void
    {
        $key = $w['key'];
        $d   = $widget_data[$key] ?? [];

        widget_open($w);

        switch ($key) {
            case 'recent_members':       widget_render_recent_members($d, $base_url); break;
            case 'upcoming_events_list': widget_render_upcoming_events_list($d); break;
            case 'birthdays_list':       widget_render_birthdays_list($d); break;
            case 'followup_list':        widget_render_followup_list($d); break;
            case 'recent_contributions': widget_render_recent_contributions($d); break;
            case 'top_givers':           widget_render_top_givers($d); break;
            case 'department_summary':   widget_render_department_summary($d); break;
            case 'cell_group_summary':   widget_render_cell_group_summary($d); break;
            case 'recent_announcements': widget_render_recent_announcements($d); break;
            case 'my_tasks':             widget_render_my_tasks($d); break;
            case 'inactive_members':     widget_render_inactive_members($d); break;
            case 'total_branches':       widget_render_kpi($w, $d, $base_url); break;
            case 'org_giving_total':     widget_render_kpi($w, $d, $base_url); break;
            case 'org_attendance_total': widget_render_kpi($w, $d, $base_url); break;
            case 'branch_comparison':    widget_render_branch_comparison($d); break;
            case 'branch_leaderboard':   widget_render_branch_leaderboard($d, $base_url); break;
            case 'branches_attention':   widget_render_branches_attention($d, $base_url); break;
            case 'quick_actions':        widget_render_quick_actions($d, $enabled_modules, $permissions, $base_url); break;
            case 'service_calendar':     widget_render_calendar($d); break;
            case 'giving_goal':          widget_render_giving_goal($d); break;
            case 'project_progress':     widget_render_project_progress($d); break;
            default:
                if ($w['type'] === 'kpi')   { widget_render_kpi($w, $d, $base_url); break; }
                if ($w['type'] === 'chart') { widget_render_chart($w, $d); break; }
                widget_empty();
        }

        widget_close();
    }
}

<?php
/**
 * Mutendi CMS — church side (main/) configuration.
 *
 * UI ONLY. Nothing here touches a database, a session or an authenticated
 * user: every value below is hardcoded demo data, and each block is marked
 * with the query that will eventually replace it.
 *
 * This file is the single source the sidebar and top bar read from. The menu
 * is data, not markup — switch a module off in $enabled_modules or drop a
 * permission from $permissions and the navigation re-renders accordingly,
 * with no edit to sidebar.php.
 */

/* ==========================================================================
   1. BASE PATH
   Auto-detected from the filesystem so the app works at any folder depth
   (/mutendi/main/, /main/, a vhost root) without being configured.
   ========================================================================== */

if (!isset($base_url)) {
    $docRoot = str_replace('\\', '/', rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/'));
    $mainDir = str_replace('\\', '/', dirname(__DIR__));          /* .../main */

    $path = ($docRoot !== '' && strpos($mainDir, $docRoot) === 0)
        ? substr($mainDir, strlen($docRoot))
        : '/main';

    $base_url = rtrim($path, '/') . '/';                          /* e.g. /mutendi/main/ */
}

/* The project root, one level above main/ — where shared assets live. */
if (!isset($root_url)) {
    $root_url = rtrim(dirname(rtrim($base_url, '/')), '/');       /* e.g. /mutendi */
}

/* ==========================================================================
   2. THE CHURCH
   LATER: SELECT name, code, logo, account_type, expires_on, members_count
          FROM churches WHERE id = :church_id;
   ========================================================================== */

$church = [
    'name'         => 'Mutendi Central Parish',
    'code'         => 'MCP-0142',
    'initials'     => 'MC',                    /* the badge shown when no logo is uploaded */

    /* Churches upload their own mark; the demo borrows the platform logo.
       Both a URL (for the tag) and a filesystem path (to test existence)
       are kept, so a missing file falls back to the initials badge instead
       of rendering a broken image. */
    'logo'         => $root_url . '/resources/img/logo.png',
    'logo_path'    => dirname(__DIR__, 2) . '/resources/img/logo.png',

    'account_type' => 'trial',                 /* 'trial' | 'paying' */
    'expires_on'   => '2026-09-18',
    'days_left'    => 24,
    'members'      => 1284,
];

/* ==========================================================================
   3. ENABLED MODULES
   What this church has switched on, keyed exactly as the super admin's
   Modules page keys them.
   LATER: SELECT module_key FROM church_modules
          WHERE church_id = :church_id AND enabled = 1;

   Core (always on):     members, attendance, departments, communication, reports
   Optional (per plan):  finance, cell_groups, events, sermons, assets,
                         payroll, visitors, projects, library

   'assets' and 'library' are deliberately left OFF in this demo so the
   filtering is visible: Assets & Inventory disappears, and because it is the
   only remaining item in RESOURCES once payroll is permission-gated, that
   whole group can vanish too.
   ========================================================================== */

$enabled_modules = [
    'members', 'attendance', 'departments', 'communication', 'reports',   /* core */
    'finance', 'cell_groups', 'events', 'sermons', 'payroll', 'visitors', 'projects', 'budgets',
];

/* ==========================================================================
   4. THE SIGNED-IN USER
   LATER: SELECT full_name, role_key, role_label, email FROM users
          WHERE id = :user_id;
   index.php overrides this block while the demo role switcher is in place.
   ========================================================================== */

$user = [
    'name'       => 'Tendai Marufu',
    'role'       => 'church_admin',
    'role_label' => 'Church Administrator',
    'initials'   => 'TM',
    'email'      => 'tendai@mutendicentral.co.zw',

    /* Multi-branch scope (see section 15). 'organisation' sees every branch;
       'branch' is pinned to branch_id and may see nothing else.
       LATER: SELECT scope, branch_id FROM users WHERE id = :user_id; */
    'scope'       => 'organisation',
    'branch_id'   => 1,
    'branch_name' => 'St Mary\'s Cathedral',
];

/* ==========================================================================
   5. PERMISSIONS HELD BY THAT USER'S ROLE
   LATER: SELECT permission_key FROM role_permissions WHERE role_id = :role_id;
   ========================================================================== */

$permissions = [
    'members.view', 'members.add', 'members.edit', 'members.delete', 'members.export',
    'finance.view', 'finance.add', 'finance.edit', 'finance.delete', 'finance.reports',
    'attendance.view', 'attendance.add', 'attendance.edit', 'attendance.reports', 'attendance.manage',
    'payroll.view',
    'settings.manage',
    /* Organisation structure: these unlock the branches pages in the sidebar
       (All / Add / Reports) and the Add entry points across the UI. */
    'branches.add', 'branches.edit', 'reports.view',
    /* Fundraising projects: create, edit and close them. */
    'projects.manage',
    /* Authorising expenditure. Deliberately separate from finance.add: the
       person who spends is rarely the person who signs it off. */
    'finance.approve',
    /* Drawing up and amending a budget. */
    'budgets.manage',
    /* Reading and publishing notices to the congregation. */
    'communication.view', 'announcements.manage',
];

/* ==========================================================================
   6. THE MENU
   One nested array describing the whole navigation. Every group and every
   item may declare:
       module      — rendered only when the key is in $enabled_modules
       permission  — rendered only when the key is in $permissions
       badge       — a small count pill
       children    — a nested list (rendered as an accordion, and as a flyout
                     when the sidebar is collapsed)
   A group whose items are all filtered out is never rendered, heading and all.
   ========================================================================== */

$menu = [

    [
        'heading' => 'Main',
        'icon'    => 'fa-gauge-high',
        'items'   => [
            ['label' => 'Dashboard', 'icon' => 'fa-gauge-high', 'url' => 'index.php'],
        ],
    ],

    [
        'heading' => 'People',
        'icon'    => 'fa-users',
        'module'  => 'members',
        'items'   => [
            ['label' => 'All Members',           'icon' => 'fa-address-book',        'url' => 'members/all.php'],
            ['label' => 'Add Member',            'icon' => 'fa-user-plus',           'url' => 'members/add.php',      'permission' => 'members.add'],
            ['label' => 'Families / Households', 'icon' => 'fa-house-user',          'url' => 'members/families.php'],
            ['label' => 'Visitors & Follow-Up',  'icon' => 'fa-hand-holding-heart',  'url' => 'members/visitors.php', 'module' => 'visitors', 'badge' => 4],
            ['label' => 'Departments',           'icon' => 'fa-sitemap',             'url' => 'departments/index.php', 'module' => 'departments'],
            ['label' => 'Cell Groups',           'icon' => 'fa-people-group',        'url' => 'cells/index.php',       'module' => 'cell_groups'],
        ],
    ],

    [
        'heading' => 'Attendance',
        'icon'    => 'fa-clipboard-check',
        'module'  => 'attendance',
        'items'   => [
            ['label' => 'Record Attendance',   'icon' => 'fa-square-check',    'url' => 'attendance/record.php'],
            ['label' => 'Attendance Register', 'icon' => 'fa-table-list',      'url' => 'attendance/register.php'],
            ['label' => 'Services & Meetings', 'icon' => 'fa-church',          'url' => 'attendance/services.php'],
            ['label' => 'Attendance Reports',  'icon' => 'fa-chart-column',    'url' => 'attendance/reports.php'],
        ],
    ],

    [
        'heading' => 'Finance',
        'icon'    => 'fa-hand-holding-dollar',
        'module'  => 'finance',
        'items'   => [
            ['label' => 'Contributions',       'icon' => 'fa-basket-shopping', 'url' => 'finance/contributions.php'],
            ['label' => 'Record Contribution', 'icon' => 'fa-circle-plus',     'url' => 'finance/record.php',   'permission' => 'finance.add'],
            ['label' => 'Pledges & Projects',  'icon' => 'fa-hand-holding-heart', 'url' => 'finance/pledges.php', 'module' => 'projects'],
            ['label' => 'Expenses',            'icon' => 'fa-receipt',         'url' => 'finance/expenses.php'],
            ['label' => 'Budgets',             'icon' => 'fa-scale-balanced',  'url' => 'finance/budgets.php'],
            ['label' => 'Financial Reports',   'icon' => 'fa-file-invoice-dollar', 'url' => 'finance/reports.php', 'permission' => 'finance.reports'],
        ],
    ],

    [
        'heading' => 'Events',
        'icon'    => 'fa-calendar-days',
        'module'  => 'events',
        'items'   => [
            ['label' => 'Calendar',            'icon' => 'fa-calendar',        'url' => 'events/calendar.php'],
            ['label' => 'All Events',          'icon' => 'fa-list-ul',         'url' => 'events/all.php'],
            ['label' => 'Programs & Services', 'icon' => 'fa-diagram-project', 'url' => 'events/programs.php'],
        ],
    ],

    [
        'heading' => 'Communication',
        'icon'    => 'fa-comment-dots',
        'module'  => 'communication',
        'items'   => [
            ['label' => 'Announcements',     'icon' => 'fa-bullhorn',      'url' => 'communication/announcements.php', 'badge' => 2],
        ],
    ],

    [
        'heading'    => 'Settings',
        'icon'       => 'fa-gear',
        'permission' => 'settings.manage',
        'items'      => [
            ['label' => 'Church Profile',         'icon' => 'fa-place-of-worship', 'url' => 'settings/profile.php'],
            ['label' => 'Users & Roles',          'icon' => 'fa-user-shield',      'url' => 'settings/users.php'],
            ['label' => 'Departments Setup',      'icon' => 'fa-sitemap',          'url' => 'settings/departments.php'],
            ['label' => 'Contribution Types',     'icon' => 'fa-tags',             'url' => 'settings/contribution-types.php'],
            ['label' => 'Member Fields',          'icon' => 'fa-list-check',       'url' => 'settings/member-fields.php'],
            ['label' => 'Communication Settings', 'icon' => 'fa-sliders',          'url' => 'settings/communication.php'],
        ],
    ],

    /* Rendered in the sidebar's footer rather than the scrolling nav. */
    [
        'position' => 'footer',
        'items'    => [
            ['label' => 'Help & Support', 'icon' => 'fa-circle-question', 'url' => 'help.php'],
            ['label' => 'Logout',         'icon' => 'fa-arrow-right-from-bracket', 'url' => 'logout.php', 'danger' => true],
        ],
    ],
];

/* ==========================================================================
   7. NOTIFICATIONS shown in the top bar's bell dropdown.
   LATER: SELECT * FROM notifications WHERE church_id = :church_id
          AND read_at IS NULL ORDER BY created_at DESC LIMIT 5;
   ========================================================================== */

$notifications = [
    ['icon' => 'fa-user-plus',      'text' => '3 new members joined this week',        'time' => '2h ago'],
    ['icon' => 'fa-hand-holding-heart', 'text' => '4 visitors awaiting follow-up',     'time' => '5h ago'],
    ['icon' => 'fa-calendar-day',   'text' => 'Youth Service scheduled for Saturday',  'time' => 'Yesterday'],
];

/* ==========================================================================
   8. DASHBOARD — WIDGET REGISTRY
   One dashboard, driven entirely by this table. Every widget declares which
   module and permission it needs (reusing the exact same $enabled_modules /
   $permissions arrays the sidebar filters against — see main_allowed() in
   components/sidebar.php) plus a per-role sort priority. A role absent from a
   widget's priority map falls back to 'default'. index.php filters this list
   with main_allowed(), drops anything in roles_hidden for the current role,
   then sorts by priority — nothing here is a hardcoded per-role dashboard.
   ========================================================================== */

$widgets = [
    'total_members' => [
        'title'        => 'Total Members',
        'icon'         => 'fa-users',
        'type'         => 'kpi',
        'module'       => 'members',
        'permission'   => null,
        'size'         => 'quarter',
        'priority'     => [
            'church_admin' => 1,
            'pastor' => 5,
            'secretary' => 1,
            'treasurer' => 14,
            'dept_head' => 3,
            'cell_leader' => 17,
            'usher' => 11,
            'default' => 1,
        ],
    ],
    'new_members' => [
        'title'        => 'New This Month',
        'icon'         => 'fa-user-plus',
        'type'         => 'kpi',
        'module'       => 'members',
        'permission'   => null,
        'size'         => 'quarter',
        'priority'     => [
            'church_admin' => 2,
            'pastor' => 6,
            'secretary' => 2,
            'treasurer' => 15,
            'dept_head' => 4,
            'cell_leader' => 18,
            'usher' => 12,
            'default' => 2,
        ],
    ],
    'attendance_last' => [
        'title'        => 'Last Service',
        'icon'         => 'fa-square-check',
        'type'         => 'kpi',
        'module'       => 'attendance',
        'permission'   => null,
        'size'         => 'quarter',
        'priority'     => [
            'church_admin' => 10,
            'pastor' => 7,
            'secretary' => 13,
            'treasurer' => 17,
            'dept_head' => 12,
            'cell_leader' => 3,
            'usher' => 1,
            'default' => 3,
        ],
    ],
    'attendance_avg' => [
        'title'        => 'Average Attendance',
        'icon'         => 'fa-chart-simple',
        'type'         => 'kpi',
        'module'       => 'attendance',
        'permission'   => null,
        'size'         => 'quarter',
        'priority'     => [
            'church_admin' => 11,
            'pastor' => 8,
            'secretary' => 14,
            'treasurer' => 18,
            'dept_head' => 13,
            'cell_leader' => 4,
            'usher' => 2,
            'default' => 4,
        ],
    ],
    'giving_month' => [
        'title'        => 'Giving This Month',
        'icon'         => 'fa-hand-holding-dollar',
        'type'         => 'kpi',
        'module'       => 'finance',
        'permission'   => 'finance.view',
        'size'         => 'quarter',
        'priority'     => [
            'church_admin' => 6,
            'pastor' => 26,
            'secretary' => 26,
            'treasurer' => 1,
            'dept_head' => 26,
            'cell_leader' => 25,
            'usher' => 13,
            'default' => 5,
        ],
    ],
    'giving_week' => [
        'title'        => 'This Week\'s Offering',
        'icon'         => 'fa-sack-dollar',
        'type'         => 'kpi',
        'module'       => 'finance',
        'permission'   => 'finance.view',
        'size'         => 'quarter',
        'priority'     => [
            'church_admin' => 7,
            'pastor' => 31,
            'secretary' => 27,
            'treasurer' => 2,
            'dept_head' => 27,
            'cell_leader' => 26,
            'usher' => 14,
            'default' => 6,
        ],
    ],
    'outstanding_pledges' => [
        'title'        => 'Outstanding Pledges',
        'icon'         => 'fa-hand-holding-heart',
        'type'         => 'kpi',
        'module'       => 'projects',
        'permission'   => null,
        'size'         => 'quarter',
        'priority'     => [
            'church_admin' => 8,
            'pastor' => 32,
            'secretary' => 28,
            'treasurer' => 3,
            'dept_head' => 28,
            'cell_leader' => 27,
            'usher' => 15,
            'default' => 7,
        ],
    ],
    'visitors_pending' => [
        'title'        => 'Visitors to Follow Up',
        'icon'         => 'fa-people-arrows',
        'type'         => 'kpi',
        'module'       => 'visitors',
        'permission'   => null,
        'size'         => 'quarter',
        'priority'     => [
            'church_admin' => 13,
            'pastor' => 9,
            'secretary' => 3,
            'treasurer' => 13,
            'dept_head' => 17,
            'cell_leader' => 28,
            'usher' => 7,
            'default' => 8,
        ],
    ],
    'upcoming_events' => [
        'title'        => 'Upcoming Events',
        'icon'         => 'fa-calendar-days',
        'type'         => 'kpi',
        'module'       => 'events',
        'permission'   => null,
        'size'         => 'quarter',
        'priority'     => [
            'church_admin' => 14,
            'pastor' => 10,
            'secretary' => 4,
            'treasurer' => 12,
            'dept_head' => 8,
            'cell_leader' => 10,
            'usher' => 8,
            'default' => 9,
        ],
    ],
    'my_department' => [
        'title'        => 'My Department Members',
        'icon'         => 'fa-sitemap',
        'type'         => 'kpi',
        'module'       => 'departments',
        'permission'   => null,
        'size'         => 'quarter',
        'priority'     => [
            'church_admin' => 4,
            'pastor' => 21,
            'secretary' => 19,
            'treasurer' => 31,
            'dept_head' => 1,
            'cell_leader' => 19,
            'usher' => 16,
            'default' => 10,
        ],
    ],
    'my_cell_group' => [
        'title'        => 'My Cell Group',
        'icon'         => 'fa-people-group',
        'type'         => 'kpi',
        'module'       => 'cell_groups',
        'permission'   => null,
        'size'         => 'quarter',
        'priority'     => [
            'church_admin' => 5,
            'pastor' => 22,
            'secretary' => 20,
            'treasurer' => 32,
            'dept_head' => 15,
            'cell_leader' => 1,
            'usher' => 17,
            'default' => 11,
        ],
    ],
    'birthdays_week' => [
        'title'        => 'Birthdays This Week',
        'icon'         => 'fa-cake-candles',
        'type'         => 'kpi',
        'module'       => 'members',
        'permission'   => null,
        'size'         => 'quarter',
        'priority'     => [
            'church_admin' => 3,
            'pastor' => 11,
            'secretary' => 5,
            'treasurer' => 16,
            'dept_head' => 5,
            'cell_leader' => 5,
            'usher' => 18,
            'default' => 12,
        ],
    ],
    'attendance_trend' => [
        'title'        => 'Attendance Trend',
        'icon'         => 'fa-chart-line',
        'type'         => 'chart',
        'module'       => 'attendance',
        'permission'   => null,
        'size'         => 'half',
        'priority'     => [
            'church_admin' => 17,
            'pastor' => 2,
            'secretary' => 15,
            'treasurer' => 19,
            'dept_head' => 14,
            'cell_leader' => 14,
            'usher' => 5,
            'default' => 13,
        ],
    ],
    'giving_trend' => [
        'title'        => 'Giving Trend',
        'icon'         => 'fa-chart-column',
        'type'         => 'chart',
        'module'       => 'finance',
        'permission'   => 'finance.reports',
        'size'         => 'half',
        'priority'     => [
            'church_admin' => 18,
            'pastor' => 27,
            'secretary' => 29,
            'treasurer' => 5,
            'dept_head' => 29,
            'cell_leader' => 29,
            'usher' => 19,
            'default' => 14,
        ],
    ],
    'membership_growth' => [
        'title'        => 'Membership Growth',
        'icon'         => 'fa-arrow-trend-up',
        'type'         => 'chart',
        'module'       => 'members',
        'permission'   => null,
        'size'         => 'half',
        'priority'     => [
            'church_admin' => 16,
            'pastor' => 1,
            'secretary' => 25,
            'treasurer' => 22,
            'dept_head' => 25,
            'cell_leader' => 24,
            'usher' => 20,
            'default' => 15,
        ],
    ],
    'giving_breakdown' => [
        'title'        => 'Giving Breakdown',
        'icon'         => 'fa-chart-pie',
        'type'         => 'chart',
        'module'       => 'finance',
        'permission'   => 'finance.reports',
        'size'         => 'third',
        'priority'     => [
            'church_admin' => 19,
            'pastor' => 28,
            'secretary' => 30,
            'treasurer' => 6,
            'dept_head' => 30,
            'cell_leader' => 30,
            'usher' => 21,
            'default' => 16,
        ],
    ],
    'attendance_by_service' => [
        'title'        => 'Attendance by Service',
        'icon'         => 'fa-chart-simple',
        'type'         => 'chart',
        'module'       => 'attendance',
        'permission'   => null,
        'size'         => 'third',
        'priority'     => [
            'church_admin' => 20,
            'pastor' => 18,
            'secretary' => 16,
            'treasurer' => 20,
            'dept_head' => 23,
            'cell_leader' => 23,
            'usher' => 6,
            'default' => 17,
        ],
    ],
    'age_distribution' => [
        'title'        => 'Age Distribution',
        'icon'         => 'fa-chart-column',
        'type'         => 'chart',
        'module'       => 'members',
        'permission'   => null,
        'size'         => 'third',
        'priority'     => [
            'church_admin' => 21,
            'pastor' => 17,
            'secretary' => 21,
            'treasurer' => 23,
            'dept_head' => 20,
            'cell_leader' => 21,
            'usher' => 22,
            'default' => 18,
        ],
    ],
    'gender_split' => [
        'title'        => 'Gender Split',
        'icon'         => 'fa-venus-mars',
        'type'         => 'chart',
        'module'       => 'members',
        'permission'   => null,
        'size'         => 'quarter',
        'priority'     => [
            'church_admin' => 22,
            'pastor' => 19,
            'secretary' => 22,
            'treasurer' => 24,
            'dept_head' => 21,
            'cell_leader' => 22,
            'usher' => 23,
            'default' => 19,
        ],
    ],
    'recent_members' => [
        'title'        => 'Recently Added Members',
        'icon'         => 'fa-address-book',
        'type'         => 'table',
        'module'       => 'members',
        'permission'   => null,
        'size'         => 'half',
        'priority'     => [
            'church_admin' => 23,
            'pastor' => 13,
            'secretary' => 7,
            'treasurer' => 25,
            'dept_head' => 7,
            'cell_leader' => 7,
            'usher' => 24,
            'default' => 20,
        ],
    ],
    'upcoming_events_list' => [
        'title'        => 'Upcoming Events',
        'icon'         => 'fa-calendar',
        'type'         => 'list',
        'module'       => 'events',
        'permission'   => null,
        'size'         => 'half',
        'priority'     => [
            'church_admin' => 27,
            'pastor' => 14,
            'secretary' => 9,
            'treasurer' => 26,
            'dept_head' => 9,
            'cell_leader' => 11,
            'usher' => 25,
            'default' => 21,
        ],
    ],
    'birthdays_list' => [
        'title'        => 'Birthdays This Week',
        'icon'         => 'fa-cake-candles',
        'type'         => 'list',
        'module'       => 'members',
        'permission'   => null,
        'size'         => 'third',
        'priority'     => [
            'church_admin' => 35,
            'pastor' => 4,
            'secretary' => 11,
            'treasurer' => 27,
            'dept_head' => 22,
            'cell_leader' => 8,
            'usher' => 26,
            'default' => 22,
        ],
    ],
    'followup_list' => [
        'title'        => 'Visitors Needing Follow-Up',
        'icon'         => 'fa-hand-holding-heart',
        'type'         => 'table',
        'module'       => 'visitors',
        'permission'   => null,
        'size'         => 'half',
        'priority'     => [
            'church_admin' => 24,
            'pastor' => 3,
            'secretary' => 8,
            'treasurer' => 28,
            'dept_head' => 18,
            'cell_leader' => 9,
            'usher' => 27,
            'default' => 23,
        ],
    ],
    'recent_contributions' => [
        'title'        => 'Recent Contributions',
        'icon'         => 'fa-receipt',
        'type'         => 'table',
        'module'       => 'finance',
        'permission'   => 'finance.view',
        'size'         => 'half',
        'priority'     => [
            'church_admin' => 25,
            'pastor' => 30,
            'secretary' => 31,
            'treasurer' => 7,
            'dept_head' => 31,
            'cell_leader' => 31,
            'usher' => 28,
            'default' => 24,
        ],
    ],
    'top_givers' => [
        'title'        => 'Top Givers This Month',
        'icon'         => 'fa-trophy',
        'type'         => 'list',
        'module'       => 'finance',
        'permission'   => 'finance.reports',
        'size'         => 'third',
        'roles_hidden' => ['usher', 'cell_leader', 'dept_head'],
        'priority'     => [
            'church_admin' => 26,
            'pastor' => 33,
            'secretary' => 32,
            'treasurer' => 8,
            'dept_head' => 32,
            'cell_leader' => 32,
            'usher' => 29,
            'default' => 25,
        ],
    ],
    'department_summary' => [
        'title'        => 'Department Summary',
        'icon'         => 'fa-sitemap',
        'type'         => 'list',
        'module'       => 'departments',
        'permission'   => null,
        'size'         => 'half',
        'priority'     => [
            'church_admin' => 28,
            'pastor' => 23,
            'secretary' => 17,
            'treasurer' => 29,
            'dept_head' => 2,
            'cell_leader' => 20,
            'usher' => 30,
            'default' => 26,
        ],
    ],
    'cell_group_summary' => [
        'title'        => 'Cell Groups',
        'icon'         => 'fa-people-group',
        'type'         => 'table',
        'module'       => 'cell_groups',
        'permission'   => null,
        'size'         => 'half',
        'priority'     => [
            'church_admin' => 29,
            'pastor' => 24,
            'secretary' => 18,
            'treasurer' => 30,
            'dept_head' => 16,
            'cell_leader' => 2,
            'usher' => 31,
            'default' => 27,
        ],
    ],
    'recent_announcements' => [
        'title'        => 'Recent Announcements',
        'icon'         => 'fa-bullhorn',
        'type'         => 'feed',
        'module'       => 'communication',
        'permission'   => null,
        'size'         => 'third',
        'priority'     => [
            'church_admin' => 30,
            'pastor' => 15,
            'secretary' => 10,
            'treasurer' => 33,
            'dept_head' => 10,
            'cell_leader' => 12,
            'usher' => 32,
            'default' => 28,
        ],
    ],
    'my_tasks' => [
        'title'        => 'My Tasks',
        'icon'         => 'fa-list-check',
        'type'         => 'list',
        'module'       => null,
        'permission'   => null,
        'size'         => 'third',
        'priority'     => [
            'church_admin' => 34,
            'pastor' => 25,
            'secretary' => 23,
            'treasurer' => 11,
            'dept_head' => 11,
            'cell_leader' => 13,
            'usher' => 9,
            'default' => 29,
        ],
    ],
    'inactive_members' => [
        'title'        => 'Members Needing a Visit',
        'icon'         => 'fa-user-clock',
        'type'         => 'table',
        'module'       => 'members',
        'permission'   => 'members.edit',
        'size'         => 'half',
        'priority'     => [
            'church_admin' => 31,
            'pastor' => 35,
            'secretary' => 33,
            'treasurer' => 35,
            'dept_head' => 33,
            'cell_leader' => 33,
            'usher' => 33,
            'default' => 30,
        ],
    ],
    'quick_actions' => [
        'title'        => 'Quick Actions',
        'icon'         => 'fa-bolt',
        'type'         => 'quick_actions',
        'module'       => null,
        'permission'   => null,
        'size'         => 'full',
        'priority'     => [
            'church_admin' => 15,
            'pastor' => 12,
            'secretary' => 6,
            'treasurer' => 10,
            'dept_head' => 6,
            'cell_leader' => 6,
            'usher' => 4,
            'default' => 31,
        ],
    ],
    'service_calendar' => [
        'title'        => 'Service Calendar',
        'icon'         => 'fa-calendar-days',
        'type'         => 'calendar',
        'module'       => 'events',
        'permission'   => null,
        'size'         => 'half',
        'priority'     => [
            'church_admin' => 32,
            'pastor' => 16,
            'secretary' => 12,
            'treasurer' => 34,
            'dept_head' => 19,
            'cell_leader' => 16,
            'usher' => 10,
            'default' => 32,
        ],
    ],
    'giving_goal' => [
        'title'        => 'Monthly Giving Goal',
        'icon'         => 'fa-bullseye',
        'type'         => 'progress',
        'module'       => 'finance',
        'permission'   => 'finance.view',
        'size'         => 'third',
        'priority'     => [
            'church_admin' => 9,
            'pastor' => 29,
            'secretary' => 34,
            'treasurer' => 4,
            'dept_head' => 34,
            'cell_leader' => 34,
            'usher' => 34,
            'default' => 33,
        ],
    ],
    'project_progress' => [
        'title'        => 'Active Projects',
        'icon'         => 'fa-diagram-project',
        'type'         => 'progress',
        'module'       => 'projects',
        'permission'   => null,
        'size'         => 'half',
        'priority'     => [
            'church_admin' => 33,
            'pastor' => 34,
            'secretary' => 35,
            'treasurer' => 9,
            'dept_head' => 35,
            'cell_leader' => 35,
            'usher' => 35,
            'default' => 34,
        ],
    ],
    /* ─────────────────── ORGANISATION-LEVEL WIDGETS ───────────────────
       org_only => true means these render ONLY in organisation mode: a
       multi-branch tenant, an organisation-scope user, and "All
       {branch_plural}" selected. Negative priorities sort them ahead of the
       branch-level set without renumbering anything above.
       ─────────────────────────────────────────────────────────────────── */

    'total_branches' => [
        'title'        => 'Total Branches',        /* relabelled at render from $terminology */
        'icon'         => 'fa-church',
        'type'         => 'kpi',
        'module'       => null,
        'permission'   => null,
        'size'         => 'quarter',
        'org_only'     => true,
        'priority'     => [
            'church_admin' => -60, 'pastor' => -40, 'secretary' => -60,
            'treasurer' => -30, 'dept_head' => -60, 'cell_leader' => -60,
            'usher' => -60, 'default' => -60,
        ],
    ],

    'org_attendance_total' => [
        'title'        => 'Organisation Attendance',
        'icon'         => 'fa-clipboard-check',
        'type'         => 'kpi',
        'module'       => 'attendance',
        'permission'   => null,
        'size'         => 'quarter',
        'org_only'     => true,
        'priority'     => [
            'church_admin' => -50, 'pastor' => -50, 'secretary' => -50,
            'treasurer' => -20, 'dept_head' => -50, 'cell_leader' => -50,
            'usher' => -50, 'default' => -50,
        ],
    ],

    'org_giving_total' => [
        'title'        => 'Organisation Giving',
        'icon'         => 'fa-hand-holding-dollar',
        'type'         => 'kpi',
        'module'       => 'finance',
        'permission'   => 'finance.reports',
        'size'         => 'quarter',
        'org_only'     => true,
        'roles_hidden' => ['usher', 'cell_leader', 'dept_head'],
        'priority'     => [
            'church_admin' => -45, 'pastor' => -25, 'secretary' => -45,
            'treasurer' => -60, 'dept_head' => -45, 'cell_leader' => -45,
            'usher' => -45, 'default' => -45,
        ],
    ],

    'branch_comparison' => [
        'title'        => 'Branch Comparison',
        'icon'         => 'fa-chart-column',
        'type'         => 'chart',
        'module'       => null,
        'permission'   => null,
        'size'         => 'half',
        'org_only'     => true,
        'roles_hidden' => ['usher'],
        'priority'     => [
            'church_admin' => -30, 'pastor' => -35, 'secretary' => -30,
            'treasurer' => -40, 'dept_head' => -30, 'cell_leader' => -30,
            'usher' => -30, 'default' => -30,
        ],
    ],

    'branch_leaderboard' => [
        'title'        => 'Fastest Growing',
        'icon'         => 'fa-ranking-star',
        'type'         => 'list',
        'module'       => null,
        'permission'   => null,
        'size'         => 'third',
        'org_only'     => true,
        'priority'     => [
            'church_admin' => -20, 'pastor' => -45, 'secretary' => -20,
            'treasurer' => -15, 'dept_head' => -20, 'cell_leader' => -20,
            'usher' => -20, 'default' => -20,
        ],
    ],

    'branches_attention' => [
        'title'        => 'Branches Needing Attention',
        'icon'         => 'fa-triangle-exclamation',
        'type'         => 'list',
        'module'       => null,
        'permission'   => null,
        'size'         => 'half',
        'org_only'     => true,
        'priority'     => [
            'church_admin' => -10, 'pastor' => -60, 'secretary' => -40,
            'treasurer' => -10, 'dept_head' => -10, 'cell_leader' => -10,
            'usher' => -10, 'default' => -10,
        ],
    ],

    'attendance_streak' => [
        'title'        => 'Attendance Streak',
        'icon'         => 'fa-fire',
        'type'         => 'kpi',
        'module'       => 'attendance',
        'permission'   => null,
        'size'         => 'quarter',
        'priority'     => [
            'church_admin' => 12,
            'pastor' => 20,
            'secretary' => 24,
            'treasurer' => 21,
            'dept_head' => 24,
            'cell_leader' => 15,
            'usher' => 3,
            'default' => 35,
        ],
    ],
];

/* ==========================================================================
   9. DASHBOARD — WIDGET DATA
   The body content each widget renders. Kept apart from the registry above
   because the registry is chrome (title, icon, filtering, ordering) and this
   is content — in production the two come from different places (config vs
   query). Every array below is what a real query's result set would look
   like, so components/widgets.php never has to guess a shape.
   LATER: each block below becomes the query noted above it.
   ========================================================================== */

$widget_data = [

    /* ---------------------------------------------------------------- KPIs -- */
    /* LATER: one SELECT COUNT / SUM per figure, scoped to :church_id and the
       relevant date range from the header's date-range selector. */
    'total_members'      => ['value' => '1,284', 'change' => '+12 this month',      'trend' => 'up',   'tone' => 'brand'],
    'new_members'         => ['value' => '12',    'change' => '+3 vs last month',    'trend' => 'up',   'tone' => 'ok'],
    'attendance_last'     => ['value' => '342',   'change' => '-5% vs previous',     'trend' => 'down', 'tone' => 'info'],
    'attendance_avg'      => ['value' => '318',   'change' => null,                  'trend' => null,   'tone' => 'info'],
    'giving_month'        => ['value' => '$4,280', 'change' => '+8% vs last month',  'trend' => 'up',   'tone' => 'ok'],
    'giving_week'         => ['value' => '$980',  'change' => null,                  'trend' => null,   'tone' => 'ok'],
    'outstanding_pledges' => ['value' => '$1,450', 'change' => null,                 'trend' => null,   'tone' => 'warn'],
    'visitors_pending'    => ['value' => '8',     'change' => null,                  'trend' => null,   'tone' => 'warn'],
    'upcoming_events'     => ['value' => '4',     'change' => null,                  'trend' => null,   'tone' => 'brand'],
    'my_department'       => ['value' => '46',    'change' => 'Youth Ministry',      'trend' => null,   'tone' => 'brand'],
    'my_cell_group'       => ['value' => '18',    'change' => 'Westgate Cell',       'trend' => null,   'tone' => 'brand'],
    'birthdays_week'      => ['value' => '6',     'change' => null,                  'trend' => null,   'tone' => 'brand'],
    'attendance_streak'   => ['value' => '5',     'change' => 'weeks of growth',     'trend' => 'up',   'tone' => 'ok'],

    /* --------------------------------------------------------------- charts -- */
    /* LATER: grouped/aggregated SELECTs, one per chart, over the selected
       date range. */
    'attendance_trend' => [
        'kind'   => 'line',
        'labels' => ['Wk1','Wk2','Wk3','Wk4','Wk5','Wk6','Wk7','Wk8','Wk9','Wk10','Wk11','Wk12'],
        'series' => [['label' => 'Attendance', 'data' => [286,301,295,310,318,305,330,322,338,329,345,342]]],
    ],
    'giving_trend' => [
        'kind'   => 'bar',
        'labels' => ['Mar','Apr','May','Jun','Jul','Aug'],
        'series' => [['label' => 'Giving', 'data' => [3200,3600,3100,4000,3900,4280]]],
    ],
    'membership_growth' => [
        'kind'   => 'line',
        'labels' => ['Sep','Oct','Nov','Dec','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug'],
        'series' => [['label' => 'Members', 'data' => [1050,1068,1080,1097,1110,1129,1148,1172,1201,1230,1257,1284]]],
    ],
    'giving_breakdown' => [
        'kind'   => 'doughnut',
        'labels' => ['Tithe', 'Offering', 'Building Fund', 'Missions', 'Pledges'],
        'series' => [['data' => [1980, 860, 640, 420, 380]]],
    ],
    'attendance_by_service' => [
        'kind'   => 'bar',
        'labels' => ['Sun AM', 'Sun PM', 'Wed Bible Study', 'Youth Service'],
        'series' => [['label' => 'Attendance', 'data' => [342, 168, 96, 74]]],
    ],
    'age_distribution' => [
        'kind'   => 'bar',
        'labels' => ['0-12', '13-19', '20-35', '36-50', '51-65', '66+'],
        'series' => [['label' => 'Members', 'data' => [188, 146, 402, 318, 164, 66]]],
    ],
    'gender_split' => [
        'kind'   => 'doughnut',
        'labels' => ['Female', 'Male'],
        'series' => [['data' => [702, 582]]],
    ],

    /* --------------------------------------------------------- lists/tables -- */
    /* LATER: paginated SELECTs, most recent / soonest first, LIMIT-ed to what
       the widget shows. */
    'recent_members' => [
        ['initials' => 'PN', 'name' => 'Panashe Ndlovu',  'phone' => '+263 77 210 4471', 'joined' => '2026-08-21'],
        ['initials' => 'TM', 'name' => 'Tafara Mhaka',    'phone' => '+263 71 556 0932', 'joined' => '2026-08-19'],
        ['initials' => 'CG', 'name' => 'Chipo Gumbo',     'phone' => '+263 78 340 7712', 'joined' => '2026-08-17'],
        ['initials' => 'WM', 'name' => 'Wadzanai Mutasa', 'phone' => '+263 77 902 1145', 'joined' => '2026-08-14'],
        ['initials' => 'LK', 'name' => 'Lloyd Kanyemba',  'phone' => '+263 73 118 6602', 'joined' => '2026-08-11'],
        ['initials' => 'SR', 'name' => 'Shamiso Ruwende', 'phone' => '+263 78 664 3390', 'joined' => '2026-08-08'],
        ['initials' => 'BC', 'name' => 'Blessing Chuma',  'phone' => '+263 71 887 2256', 'joined' => '2026-08-05'],
        ['initials' => 'NM', 'name' => 'Nyasha Mangwiro', 'phone' => '+263 77 445 9013', 'joined' => '2026-08-02'],
    ],

    'upcoming_events_list' => [
        ['day' => '30', 'mon' => 'AUG', 'title' => 'Sunday Service',       'time' => '9:00 AM',  'venue' => 'Main Auditorium'],
        ['day' => '02', 'mon' => 'SEP', 'title' => 'Youth Service',        'time' => '5:30 PM',  'venue' => 'Youth Hall'],
        ['day' => '06', 'mon' => 'SEP', 'title' => 'Communion Sunday',     'time' => '9:00 AM',  'venue' => 'Main Auditorium'],
        ['day' => '10', 'mon' => 'SEP', 'title' => 'Women\'s Fellowship',  'time' => '4:00 PM',  'venue' => 'Fellowship Room'],
        ['day' => '13', 'mon' => 'SEP', 'title' => 'Building Fund Drive',  'time' => '9:00 AM',  'venue' => 'Main Auditorium'],
    ],

    'birthdays_list' => [
        ['initials' => 'RC', 'name' => 'Rutendo Chikwava', 'date' => 'Mon, 26 Aug'],
        ['initials' => 'FM', 'name' => 'Farai Mudzingwa',  'date' => 'Tue, 27 Aug'],
        ['initials' => 'TN', 'name' => 'Tapiwa Ncube',     'date' => 'Wed, 28 Aug'],
        ['initials' => 'AK', 'name' => 'Anesu Karimanzira','date' => 'Thu, 29 Aug'],
        ['initials' => 'SM', 'name' => 'Sekai Marimo',     'date' => 'Fri, 30 Aug'],
        ['initials' => 'DG', 'name' => 'Denford Gwaze',    'date' => 'Sat, 31 Aug'],
    ],

    'followup_list' => [
        ['name' => 'Michael Chirwa',   'visited' => '2026-08-17', 'days' => 8,  'assigned' => 'Grace Chikomo'],
        ['name' => 'Tariro Museka',    'visited' => '2026-08-19', 'days' => 6,  'assigned' => 'Grace Chikomo'],
        ['name' => 'Ngoni Chidziva',   'visited' => '2026-08-20', 'days' => 5,  'assigned' => 'Rev. Enock Sithole'],
        ['name' => 'Precious Banda',   'visited' => '2026-08-21', 'days' => 4,  'assigned' => 'Unassigned'],
        ['name' => 'Kudzai Farai',     'visited' => '2026-08-23', 'days' => 2,  'assigned' => 'Unassigned'],
        ['name' => 'Ropafadzo Mhende', 'visited' => '2026-08-24', 'days' => 1,  'assigned' => 'Grace Chikomo'],
    ],

    'recent_contributions' => [
        ['member' => 'Tendai Marufu',    'type' => 'Tithe',         'amount' => '$120.00', 'date' => '2026-08-24'],
        ['member' => 'Grace Chikomo',    'type' => 'Offering',      'amount' => '$40.00',  'date' => '2026-08-24'],
        ['member' => 'Farai Nyoni',      'type' => 'Building Fund', 'amount' => '$200.00', 'date' => '2026-08-23'],
        ['member' => 'Blessing Moyo',    'type' => 'Tithe',         'amount' => '$85.00',  'date' => '2026-08-23'],
        ['member' => 'Rudo Chirwa',      'type' => 'Missions',      'amount' => '$60.00',  'date' => '2026-08-22'],
        ['member' => 'Simba Dube',       'type' => 'Offering',      'amount' => '$25.00',  'date' => '2026-08-21'],
        ['member' => 'Chipo Gumbo',      'type' => 'Tithe',         'amount' => '$95.00',  'date' => '2026-08-20'],
        ['member' => 'Lloyd Kanyemba',   'type' => 'Pledges',       'amount' => '$150.00', 'date' => '2026-08-19'],
    ],

    'top_givers' => [
        ['initials' => 'TM', 'name' => 'Tendai Marufu',  'amount' => '$680', 'pct' => 100],
        ['initials' => 'FN', 'name' => 'Farai Nyoni',    'amount' => '$540', 'pct' => 79],
        ['initials' => 'CG', 'name' => 'Chipo Gumbo',    'amount' => '$410', 'pct' => 60],
        ['initials' => 'BM', 'name' => 'Blessing Moyo',  'amount' => '$365', 'pct' => 54],
        ['initials' => 'LK', 'name' => 'Lloyd Kanyemba', 'amount' => '$298', 'pct' => 44],
    ],

    'department_summary' => [
        ['name' => 'Youth Ministry',   'count' => 46, 'pct' => 100],
        ['name' => 'Music & Worship',  'count' => 38, 'pct' => 83],
        ['name' => 'Ushering',         'count' => 24, 'pct' => 52],
        ['name' => 'Children\'s Church', 'count' => 41, 'pct' => 89],
        ['name' => 'Prayer Ministry',  'count' => 19, 'pct' => 41],
    ],

    'cell_group_summary' => [
        ['name' => 'Westgate Cell',   'leader' => 'Rudo Chirwa',      'members' => 18, 'last_meeting' => '2026-08-20'],
        ['name' => 'Borrowdale Cell', 'leader' => 'Kudzai Mapfumo',   'members' => 15, 'last_meeting' => '2026-08-21'],
        ['name' => 'Chitungwiza Cell','leader' => 'Tapiwa Museva',    'members' => 22, 'last_meeting' => '2026-08-19'],
        ['name' => 'Avondale Cell',   'leader' => 'Nyasha Chiweshe',  'members' => 12, 'last_meeting' => '2026-08-22'],
    ],

    'recent_announcements' => [
        ['title' => 'Building Fund update', 'time' => '1d ago',  'excerpt' => 'We have reached 68% of this quarter\'s target.'],
        ['title' => 'Youth camp registration', 'time' => '2d ago', 'excerpt' => 'Registration closes this Friday at 5pm.'],
        ['title' => 'Choir rehearsal moved',  'time' => '4d ago', 'excerpt' => 'Now Thursdays at 6:30pm in the main hall.'],
        ['title' => 'New members class',      'time' => '6d ago', 'excerpt' => 'Starts Sunday after the second service.'],
        ['title' => 'Car park resurfacing',   'time' => '1w ago', 'excerpt' => 'Use the side entrance until 14 September.'],
    ],

    'my_tasks' => [
        ['label' => 'Follow up with Sunday\'s new visitors', 'done' => true],
        ['label' => 'Confirm Saturday youth camp transport', 'done' => true],
        ['label' => 'Review this month\'s attendance register', 'done' => false],
        ['label' => 'Prepare next Sunday\'s bulletin', 'done' => false],
        ['label' => 'Call absent members from last week', 'done' => false],
    ],

    'inactive_members' => [
        ['initials' => 'JM', 'name' => 'Joseph Mutandwa', 'last_seen' => 42],
        ['initials' => 'EP', 'name' => 'Ellen Paradza',   'last_seen' => 38],
        ['initials' => 'KC', 'name' => 'Kelvin Chidembo', 'last_seen' => 35],
        ['initials' => 'VS', 'name' => 'Vimbai Saruchera','last_seen' => 33],
        ['initials' => 'HM', 'name' => 'Herbert Mazviita','last_seen' => 31],
    ],

    /* -------------------------------------------------------------- special -- */

    /* LATER: reused by both this widget and the top bar's own Quick Add —
       kept separate for now so this file's two components stay independent. */
    'quick_actions' => [
        ['label' => 'Add Member',          'icon' => 'fa-user-plus',        'url' => 'members/add.php',       'module' => 'members',    'permission' => 'members.add'],
        ['label' => 'Record Attendance',   'icon' => 'fa-square-check',     'url' => 'attendance/record.php', 'module' => 'attendance', 'permission' => null],
        ['label' => 'Record Contribution', 'icon' => 'fa-circle-plus',      'url' => 'finance/record.php',    'module' => 'finance',    'permission' => 'finance.add'],
        ['label' => 'Add Event',           'icon' => 'fa-calendar-plus',    'url' => 'events/add.php',        'module' => 'events',     'permission' => null],
        ['label' => 'Send Message',        'icon' => 'fa-paper-plane',      'url' => '#',                     'module' => 'communication', 'permission' => null],
        ['label' => 'Add Visitor',         'icon' => 'fa-hand-holding-heart','url' => 'members/visitors.php', 'module' => 'visitors',   'permission' => null],
        ['label' => 'Create Announcement', 'icon' => 'fa-bullhorn',         'url' => 'communication/announcements.php', 'module' => 'communication', 'permission' => null],
    ],

    /* Only the event days need hardcoding — the grid itself is computed from
       the server's current date by widgets.php. */
    'service_calendar' => [
        'events' => [
            3  => ['Sunday Service'],
            6  => ['Communion Sunday'],
            10 => ['Sunday Service'],
            13 => ['Building Fund Drive'],
            17 => ['Sunday Service'],
            20 => ['Women\'s Fellowship'],
            24 => ['Sunday Service', 'Youth Service'],
            27 => ['Elders\' Meeting'],
        ],
    ],

    'giving_goal' => ['current' => 4280, 'target' => 6000],

    'project_progress' => [
        ['name' => 'New Sanctuary Roof',    'raised' => 18400, 'target' => 25000],
        ['name' => 'Borehole & Water Tank',  'raised' => 6200,  'target' => 8000],
        ['name' => 'Youth Centre Furnishing','raised' => 2150,  'target' => 5000],
    ],
];

/* ==========================================================================
   10. DASHBOARD ALERTS
   Shown as dismissible bars under the greeting. 'visible' is the hardcoded
   demo state (only two of the four are "true" right now, the other two ship
   in the markup but stay hidden); module/permission are an additional gate
   applied the same way as everywhere else, so e.g. the follow-up alert still
   disappears for a church without the visitors module.
   LATER: 'visible' becomes a real check (subscription expiry math, an
   unread-flag query, etc.) instead of a hardcoded boolean.
   ========================================================================== */

$alerts = [
    [
        'key'        => 'subscription',
        'tone'       => 'warn',
        'icon'       => 'fa-triangle-exclamation',
        'text'       => 'Your subscription expires in 6 days.',
        'action'     => 'Renew',
        'action_url' => 'settings/billing.php',
        'module'     => null,
        'permission' => 'settings.manage',
        'visible'    => true,
    ],
    [
        'key'        => 'followup',
        'tone'       => 'warn',
        'icon'       => 'fa-hand-holding-heart',
        'text'       => '8 visitors are awaiting follow-up.',
        'action'     => 'Review',
        'action_url' => 'members/visitors.php',
        'module'     => 'visitors',
        'permission' => null,
        'visible'    => true,
    ],
    [
        'key'        => 'attendance_missing',
        'tone'       => 'info',
        'icon'       => 'fa-clipboard-question',
        'text'       => 'Attendance has not been recorded for Sunday\'s service.',
        'action'     => 'Record Now',
        'action_url' => 'attendance/record.php',
        'module'     => 'attendance',
        'permission' => null,
        'visible'    => false,
    ],
    [
        'key'        => 'vendor',
        'tone'       => 'neutral',
        'icon'       => 'fa-circle-info',
        'text'       => 'Mutendi CMS: a new reporting module is now available.',
        'action'     => 'Read',
        'action_url' => 'help.php',
        'module'     => null,
        'permission' => null,
        'visible'    => false,
    ],
];

/* ==========================================================================
   11. WELCOME HEADER — per-role context line
   The greeting name and hour come from $user and the server clock; only the
   second line is looked up here, by role key.
   ========================================================================== */

$role_context = [
    'church_admin' => 'Here\'s what\'s happening at ' . $church['name'] . '.',
    'pastor'       => 'Your congregation at a glance.',
    'secretary'    => 'Today\'s records and updates.',
    'treasurer'    => 'Your financial overview.',
    'dept_head'    => 'Your department at a glance.',
    'cell_leader'  => 'Your cell group at a glance.',
    'usher'        => 'Today\'s service overview.',
];
$role_context_default = 'Here\'s your overview.';

/* ==========================================================================
   12. NEW-CHURCH ONBOARDING
   Flip to true to preview the empty-state checklist in place of the widget
   grid, as if this were a freshly-signed-up church with no data yet.
   LATER: 'is_new_church' becomes a real check (e.g. $church['members'] === 0
   and no attendance/contribution rows exist yet) rather than a flag.
   ========================================================================== */

$is_new_church = false;

$onboarding_steps = [
    ['label' => 'Add your first member',        'done' => true,  'action' => 'Add Member',        'url' => 'members/add.php'],
    ['label' => 'Set up departments',            'done' => true,  'action' => 'Set Up',            'url' => 'settings/departments.php'],
    ['label' => 'Record your first service',     'done' => false, 'action' => 'Record Attendance', 'url' => 'attendance/record.php'],
    ['label' => 'Record your first contribution','done' => false, 'action' => 'Record Contribution','url' => 'finance/record.php'],
    ['label' => 'Invite your team',              'done' => false, 'action' => 'Invite',            'url' => 'settings/users.php'],
];

/* ==========================================================================
   13. PEOPLE — DEMO DATASETS
   Used by main/members/*, main/departments/ and main/cells/. Hardcoded rows
   standing in for query results; the shape of each row is what the real
   SELECT should return, so the pages never have to change when the queries
   land.
   LATER:
     $members_demo     SELECT m.*, d.name AS department, c.name AS cell_group
                       FROM members m
                       LEFT JOIN departments d ON d.id = m.department_id
                       LEFT JOIN cell_groups c ON c.id = m.cell_group_id
                       WHERE m.church_id = :church_id
                       ORDER BY m.surname LIMIT :per_page OFFSET :offset;
     $households_demo  SELECT h.*, COUNT(hm.member_id) AS size FROM households h
                       LEFT JOIN household_members hm ON hm.household_id = h.id
                       WHERE h.church_id = :church_id GROUP BY h.id;
     $visitors_demo    SELECT * FROM visitors WHERE church_id = :church_id
                       ORDER BY first_visit DESC;
     $departments_demo SELECT d.*, COUNT(md.member_id) AS members FROM departments d
                       LEFT JOIN member_departments md ON md.department_id = d.id
                       WHERE d.church_id = :church_id GROUP BY d.id;
     $cells_demo       SELECT c.*, COUNT(mc.member_id) AS members FROM cell_groups c
                       LEFT JOIN member_cells mc ON mc.cell_group_id = c.id
                       WHERE c.church_id = :church_id GROUP BY c.id;
   ========================================================================== */

$members_demo = [
    [
        'id' => 1,
        'first' => 'Tendai',
        'middle' => '',
        'surname' => 'Museka',
        'name' => 'Tendai Museka',
        'member_no' => 'MCP-2025-0100',
        'phone' => '+263 779 600 133',
        'email' => 'tendai.museka@gmail.com',
        'gender' => 'Male',
        'dob' => '1999-05-11',
        'age' => 27,
        'marital' => 'Single',
        'occupation' => 'Security Guard',
        'department' => 'Youth Ministry',
        'cell_group' => 'Budiriro Cell',
        'status' => 'Active',
        'joined' => '2025-01-12',
        'last_attended_days' => 67,
        'suburb' => 'Ruwa',
        'city' => 'Harare',
        'province' => 'Harare',
        'household' => null,
    ],
    [
        'id' => 2,
        'first' => 'Denford',
        'middle' => 'Anesu',
        'surname' => 'Masuku',
        'name' => 'Denford Masuku',
        'member_no' => 'MCP-2024-0107',
        'phone' => '+263 784 235 116',
        'email' => 'denford.masuku@gmail.com',
        'gender' => 'Male',
        'dob' => '1997-08-30',
        'age' => 29,
        'marital' => 'Single',
        'occupation' => 'Tailor',
        'department' => 'Transport',
        'cell_group' => 'Waterfalls Cell',
        'status' => 'Active',
        'joined' => '2024-10-22',
        'last_attended_days' => 67,
        'suburb' => 'Westgate',
        'city' => 'Harare',
        'province' => 'Harare',
        'household' => null,
    ],
    [
        'id' => 3,
        'first' => 'Brian',
        'middle' => 'Kuda',
        'surname' => 'Zvobgo',
        'name' => 'Brian Zvobgo',
        'member_no' => 'MCP-2022-0114',
        'phone' => '+263 789 593 103',
        'email' => 'brian.zvobgo@gmail.com',
        'gender' => 'Male',
        'dob' => '1972-07-07',
        'age' => 54,
        'marital' => 'Widowed',
        'occupation' => 'Nurse',
        'department' => 'Transport',
        'cell_group' => 'Avondale Cell',
        'status' => 'Active',
        'joined' => '2022-05-09',
        'last_attended_days' => 2,
        'suburb' => 'Marlborough',
        'city' => 'Harare',
        'province' => 'Harare',
        'household' => null,
    ],
    [
        'id' => 4,
        'first' => 'Tariro',
        'middle' => 'Rufaro',
        'surname' => 'Zvobgo',
        'name' => 'Tariro Zvobgo',
        'member_no' => 'MCP-2024-0121',
        'phone' => '+263 714 192 832',
        'email' => 'tariro.zvobgo@gmail.com',
        'gender' => 'Female',
        'dob' => '1959-03-10',
        'age' => 67,
        'marital' => 'Divorced',
        'occupation' => 'Electrician',
        'department' => 'Women\'s Fellowship',
        'cell_group' => 'Mount Pleasant Cell',
        'status' => 'Active',
        'joined' => '2024-10-09',
        'last_attended_days' => 9,
        'suburb' => 'Hatfield',
        'city' => 'Harare',
        'province' => 'Harare',
        'household' => null,
    ],
    [
        'id' => 5,
        'first' => 'Melody',
        'middle' => 'Rufaro',
        'surname' => 'Sibanda',
        'name' => 'Melody Sibanda',
        'member_no' => 'MCP-2023-0128',
        'phone' => '+263 773 953 767',
        'email' => 'melody.sibanda@gmail.com',
        'gender' => 'Female',
        'dob' => '1999-08-17',
        'age' => 27,
        'marital' => 'Married',
        'occupation' => 'Mechanic',
        'department' => 'Praise & Worship',
        'cell_group' => 'Avondale Cell',
        'status' => 'Active',
        'joined' => '2023-01-22',
        'last_attended_days' => 12,
        'suburb' => 'Westgate',
        'city' => 'Harare',
        'province' => 'Harare',
        'household' => null,
    ],
    [
        'id' => 6,
        'first' => 'Anotida',
        'middle' => 'Kuda',
        'surname' => 'Masuku',
        'name' => 'Anotida Masuku',
        'member_no' => 'MCP-2024-0135',
        'phone' => '+263 731 012 269',
        'email' => 'anotida.masuku@gmail.com',
        'gender' => 'Female',
        'dob' => '1988-03-03',
        'age' => 38,
        'marital' => 'Single',
        'occupation' => 'Electrician',
        'department' => 'Children\'s Ministry',
        'cell_group' => 'Hatfield Cell',
        'status' => 'Active',
        'joined' => '2024-02-20',
        'last_attended_days' => 3,
        'suburb' => 'Dzivarasekwa',
        'city' => 'Harare',
        'province' => 'Harare',
        'household' => null,
    ],
    [
        'id' => 7,
        'first' => 'Loveness',
        'middle' => 'Rufaro',
        'surname' => 'Moyo',
        'name' => 'Loveness Moyo',
        'member_no' => 'MCP-2020-0142',
        'phone' => '+263 781 462 704',
        'email' => 'loveness.moyo@gmail.com',
        'gender' => 'Female',
        'dob' => '1955-07-17',
        'age' => 71,
        'marital' => 'Married',
        'occupation' => 'Software Developer',
        'department' => null,
        'cell_group' => 'Borrowdale Cell',
        'status' => 'Active',
        'joined' => '2020-07-29',
        'last_attended_days' => 95,
        'suburb' => 'Eastlea',
        'city' => 'Harare',
        'province' => 'Harare',
        'household' => null,
    ],
    [
        'id' => 8,
        'first' => 'Simba',
        'middle' => 'Kuda',
        'surname' => 'Gwaze',
        'name' => 'Simba Gwaze',
        'member_no' => 'MCP-2020-0149',
        'phone' => '+263 787 015 430',
        'email' => 'simba.gwaze@gmail.com',
        'gender' => 'Male',
        'dob' => '2003-11-29',
        'age' => 22,
        'marital' => 'Married',
        'occupation' => 'Chef',
        'department' => 'Choir',
        'cell_group' => 'Borrowdale Cell',
        'status' => 'Active',
        'joined' => '2020-08-25',
        'last_attended_days' => 1,
        'suburb' => 'Vainona',
        'city' => 'Harare',
        'province' => 'Harare',
        'household' => null,
    ],
    [
        'id' => 9,
        'first' => 'Edmore',
        'middle' => 'Tanaka',
        'surname' => 'Sithole',
        'name' => 'Edmore Sithole',
        'member_no' => 'MCP-2021-0156',
        'phone' => '+263 788 963 834',
        'email' => 'edmore.sithole@gmail.com',
        'gender' => 'Male',
        'dob' => '2007-06-27',
        'age' => 19,
        'marital' => 'Single',
        'occupation' => 'Student',
        'department' => 'Children\'s Ministry',
        'cell_group' => 'Mount Pleasant Cell',
        'status' => 'Active',
        'joined' => '2021-04-08',
        'last_attended_days' => 21,
        'suburb' => 'Highfield',
        'city' => 'Harare',
        'province' => 'Harare',
        'household' => null,
    ],
    [
        'id' => 10,
        'first' => 'Anotida',
        'middle' => '',
        'surname' => 'Mabhena',
        'name' => 'Anotida Mabhena',
        'member_no' => 'MCP-2023-0163',
        'phone' => '+263 780 983 930',
        'email' => 'anotida.mabhena@gmail.com',
        'gender' => 'Female',
        'dob' => '1982-07-07',
        'age' => 44,
        'marital' => 'Single',
        'occupation' => 'Accountant',
        'department' => 'Youth Ministry',
        'cell_group' => 'Borrowdale Cell',
        'status' => 'Active',
        'joined' => '2023-10-26',
        'last_attended_days' => 5,
        'suburb' => 'Mount Pleasant',
        'city' => 'Harare',
        'province' => 'Harare',
        'household' => null,
    ],
    [
        'id' => 11,
        'first' => 'Grace',
        'middle' => 'Kuda',
        'surname' => 'Nyamande',
        'name' => 'Grace Nyamande',
        'member_no' => 'MCP-2021-0170',
        'phone' => '+263 719 973 763',
        'email' => 'grace.nyamande@gmail.com',
        'gender' => 'Female',
        'dob' => '1999-04-13',
        'age' => 27,
        'marital' => 'Single',
        'occupation' => 'Engineer',
        'department' => 'Intercession',
        'cell_group' => 'Glen View Cell',
        'status' => 'Active',
        'joined' => '2021-02-25',
        'last_attended_days' => 5,
        'suburb' => 'Mbare',
        'city' => 'Harare',
        'province' => 'Harare',
        'household' => null,
    ],
    [
        'id' => 12,
        'first' => 'Patience',
        'middle' => 'Anesu',
        'surname' => 'Zvobgo',
        'name' => 'Patience Zvobgo',
        'member_no' => 'MCP-2025-0177',
        'phone' => '+263 781 333 872',
        'email' => 'patience.zvobgo@gmail.com',
        'gender' => 'Female',
        'dob' => '2014-09-19',
        'age' => 11,
        'marital' => 'Single',
        'occupation' => 'Student',
        'department' => 'Children\'s Ministry',
        'cell_group' => 'Chitungwiza Cell',
        'status' => 'Active',
        'joined' => '2025-06-29',
        'last_attended_days' => 1,
        'suburb' => 'Emerald Hill',
        'city' => 'Harare',
        'province' => 'Harare',
        'household' => null,
    ],
    [
        'id' => 13,
        'first' => 'Precious',
        'middle' => '',
        'surname' => 'Nyoni',
        'name' => 'Precious Nyoni',
        'member_no' => 'MCP-2025-0184',
        'phone' => '+263 771 326 773',
        'email' => 'precious.nyoni@gmail.com',
        'gender' => 'Female',
        'dob' => '1981-11-29',
        'age' => 44,
        'marital' => 'Divorced',
        'occupation' => 'Accountant',
        'department' => 'Praise & Worship',
        'cell_group' => 'Glen View Cell',
        'status' => 'Active',
        'joined' => '2025-07-01',
        'last_attended_days' => 1,
        'suburb' => 'Borrowdale',
        'city' => 'Harare',
        'province' => 'Harare',
        'household' => null,
    ],
    [
        'id' => 14,
        'first' => 'Ropafadzo',
        'middle' => 'Tanaka',
        'surname' => 'Chidembo',
        'name' => 'Ropafadzo Chidembo',
        'member_no' => 'MCP-2021-0191',
        'phone' => '+263 783 098 050',
        'email' => 'ropafadzo.chidembo@gmail.com',
        'gender' => 'Female',
        'dob' => '1971-10-06',
        'age' => 54,
        'marital' => 'Single',
        'occupation' => 'Chef',
        'department' => 'Media & Sound',
        'cell_group' => 'Kuwadzana Cell',
        'status' => 'Active',
        'joined' => '2021-02-20',
        'last_attended_days' => 3,
        'suburb' => 'Budiriro',
        'city' => 'Harare',
        'province' => 'Harare',
        'household' => null,
    ],
    [
        'id' => 15,
        'first' => 'Kelvin',
        'middle' => '',
        'surname' => 'Marufu',
        'name' => 'Kelvin Marufu',
        'member_no' => 'MCP-2019-0198',
        'phone' => '+263 716 193 990',
        'email' => 'kelvin.marufu@gmail.com',
        'gender' => 'Male',
        'dob' => '2004-07-27',
        'age' => 22,
        'marital' => 'Single',
        'occupation' => 'Builder',
        'department' => 'Intercession',
        'cell_group' => 'Hatfield Cell',
        'status' => 'Active',
        'joined' => '2019-12-04',
        'last_attended_days' => 2,
        'suburb' => 'Braeside',
        'city' => 'Harare',
        'province' => 'Harare',
        'household' => null,
    ],
    [
        'id' => 16,
        'first' => 'Sekai',
        'middle' => 'Anesu',
        'surname' => 'Mhende',
        'name' => 'Sekai Mhende',
        'member_no' => 'MCP-2022-0205',
        'phone' => '+263 781 079 911',
        'email' => 'sekai.mhende@gmail.com',
        'gender' => 'Female',
        'dob' => '1999-04-20',
        'age' => 27,
        'marital' => 'Married',
        'occupation' => 'Software Developer',
        'department' => 'Women\'s Fellowship',
        'cell_group' => 'Chitungwiza Cell',
        'status' => 'Inactive',
        'joined' => '2022-02-27',
        'last_attended_days' => 73,
        'suburb' => 'Mbare',
        'city' => 'Harare',
        'province' => 'Harare',
        'household' => null,
    ],
    [
        'id' => 17,
        'first' => 'Innocent',
        'middle' => 'Kuda',
        'surname' => 'Mangwiro',
        'name' => 'Innocent Mangwiro',
        'member_no' => 'MCP-2024-0212',
        'phone' => '+263 778 412 411',
        'email' => 'innocent.mangwiro@gmail.com',
        'gender' => 'Male',
        'dob' => '1991-04-12',
        'age' => 35,
        'marital' => 'Married',
        'occupation' => 'Mechanic',
        'department' => 'Women\'s Fellowship',
        'cell_group' => 'Hatfield Cell',
        'status' => 'Inactive',
        'joined' => '2024-10-29',
        'last_attended_days' => 73,
        'suburb' => 'Mabelreign',
        'city' => 'Harare',
        'province' => 'Harare',
        'household' => null,
    ],
    [
        'id' => 18,
        'first' => 'Sekai',
        'middle' => '',
        'surname' => 'Mudzingwa',
        'name' => 'Sekai Mudzingwa',
        'member_no' => 'MCP-2023-0219',
        'phone' => '+263 776 400 524',
        'email' => 'sekai.mudzingwa@gmail.com',
        'gender' => 'Female',
        'dob' => '1976-01-01',
        'age' => 50,
        'marital' => 'Married',
        'occupation' => 'Hairdresser',
        'department' => 'Protocol',
        'cell_group' => 'Budiriro Cell',
        'status' => 'Transferred',
        'joined' => '2023-10-13',
        'last_attended_days' => 140,
        'suburb' => 'Norton',
        'city' => 'Harare',
        'province' => 'Harare',
        'household' => null,
    ],
    [
        'id' => 19,
        'first' => 'Tapiwa',
        'middle' => 'Rufaro',
        'surname' => 'Nyoni',
        'name' => 'Tapiwa Nyoni',
        'member_no' => 'MCP-2026-0226',
        'phone' => '+263 716 204 505',
        'email' => 'tapiwa.nyoni@gmail.com',
        'gender' => 'Male',
        'dob' => '2006-11-25',
        'age' => 19,
        'marital' => 'Single',
        'occupation' => 'Student',
        'department' => 'Youth Ministry',
        'cell_group' => 'Mount Pleasant Cell',
        'status' => 'Deceased',
        'joined' => '2026-03-12',
        'last_attended_days' => 210,
        'suburb' => 'Msasa Park',
        'city' => 'Harare',
        'province' => 'Harare',
        'household' => null,
    ],
    [
        'id' => 20,
        'first' => 'Kudzai',
        'middle' => 'Tanaka',
        'surname' => 'Tshuma',
        'name' => 'Kudzai Tshuma',
        'member_no' => 'MCP-2024-0233',
        'phone' => '+263 716 025 634',
        'email' => 'kudzai.tshuma@gmail.com',
        'gender' => 'Male',
        'dob' => '1984-10-23',
        'age' => 41,
        'marital' => 'Married',
        'occupation' => 'Engineer',
        'department' => 'Children\'s Ministry',
        'cell_group' => 'Westgate Cell',
        'status' => 'Active',
        'joined' => '2024-11-11',
        'last_attended_days' => 5,
        'suburb' => 'Tynwald',
        'city' => 'Harare',
        'province' => 'Harare',
        'household' => null,
    ],
];

$households_demo = [
    [
        'id' => 1,
        'name' => 'The Chuma Family',
        'head' => 'Kudzai Chuma',
        'head_phone' => '+263 774 586 850',
        'head_gender' => 'Male',
        'members' => [
            [
                'name' => 'Kudzai Chuma',
                'rel' => 'Head',
                'age' => 61,
                'gender' => 'Male',
            ],
            [
                'name' => 'Sekai Chuma',
                'rel' => 'Spouse',
                'age' => 56,
                'gender' => 'Female',
            ],
        ],
        'adults' => 2,
        'children' => 0,
        'suburb' => 'Belvedere',
        'city' => 'Harare',
        'address' => '67 Enterprise Road',
        'cell_group' => 'Hatfield Cell',
        'created' => '2024-12-04',
    ],
    [
        'id' => 2,
        'name' => 'The Mudzingwa Family',
        'head' => 'Kudzai Mudzingwa',
        'head_phone' => '+263 782 366 299',
        'head_gender' => 'Male',
        'members' => [
            [
                'name' => 'Kudzai Mudzingwa',
                'rel' => 'Head',
                'age' => 57,
                'gender' => 'Male',
            ],
            [
                'name' => 'Memory Mudzingwa',
                'rel' => 'Spouse',
                'age' => 57,
                'gender' => 'Female',
            ],
            [
                'name' => 'Talent Mudzingwa',
                'rel' => 'Relative',
                'age' => 23,
                'gender' => 'Male',
            ],
            [
                'name' => 'Patience Mudzingwa',
                'rel' => 'Child',
                'age' => 8,
                'gender' => 'Female',
            ],
            [
                'name' => 'Fadzai Mudzingwa',
                'rel' => 'Child',
                'age' => 12,
                'gender' => 'Female',
            ],
            [
                'name' => 'Nomsa Mudzingwa',
                'rel' => 'Child',
                'age' => 10,
                'gender' => 'Female',
            ],
        ],
        'adults' => 3,
        'children' => 3,
        'suburb' => 'Eastlea',
        'city' => 'Harare',
        'address' => '104 Chiremba Road',
        'cell_group' => 'Highfield Cell',
        'created' => '2024-10-30',
    ],
    [
        'id' => 3,
        'name' => 'The Chidziva Family',
        'head' => 'Denford Chidziva',
        'head_phone' => '+263 781 769 367',
        'head_gender' => 'Male',
        'members' => [
            [
                'name' => 'Denford Chidziva',
                'rel' => 'Head',
                'age' => 52,
                'gender' => 'Male',
            ],
            [
                'name' => 'Chiedza Chidziva',
                'rel' => 'Spouse',
                'age' => 49,
                'gender' => 'Female',
            ],
            [
                'name' => 'Mercy Chidziva',
                'rel' => 'Child',
                'age' => 4,
                'gender' => 'Female',
            ],
            [
                'name' => 'Prosper Chidziva',
                'rel' => 'Child',
                'age' => 4,
                'gender' => 'Male',
            ],
            [
                'name' => 'Tendai Chidziva',
                'rel' => 'Child',
                'age' => 8,
                'gender' => 'Male',
            ],
        ],
        'adults' => 2,
        'children' => 3,
        'suburb' => 'Chitungwiza',
        'city' => 'Harare',
        'address' => '63 Enterprise Road',
        'cell_group' => 'Mount Pleasant Cell',
        'created' => '2023-10-28',
    ],
    [
        'id' => 4,
        'name' => 'The Moyo Family',
        'head' => 'Tsitsi Moyo',
        'head_phone' => '+263 774 558 122',
        'head_gender' => 'Female',
        'members' => [
            [
                'name' => 'Tsitsi Moyo',
                'rel' => 'Head',
                'age' => 38,
                'gender' => 'Female',
            ],
            [
                'name' => 'Kelvin Moyo',
                'rel' => 'Spouse',
                'age' => 33,
                'gender' => 'Male',
            ],
            [
                'name' => 'Denford Moyo',
                'rel' => 'Child',
                'age' => 8,
                'gender' => 'Male',
            ],
            [
                'name' => 'Netsai Moyo',
                'rel' => 'Child',
                'age' => 12,
                'gender' => 'Female',
            ],
            [
                'name' => 'Brian Moyo',
                'rel' => 'Child',
                'age' => 16,
                'gender' => 'Male',
            ],
            [
                'name' => 'Tonderai Moyo',
                'rel' => 'Child',
                'age' => 10,
                'gender' => 'Male',
            ],
            [
                'name' => 'Tinashe Moyo',
                'rel' => 'Child',
                'age' => 16,
                'gender' => 'Male',
            ],
        ],
        'adults' => 2,
        'children' => 5,
        'suburb' => 'Hatfield',
        'city' => 'Harare',
        'address' => '99 Enterprise Road',
        'cell_group' => 'Budiriro Cell',
        'created' => '2024-11-19',
    ],
    [
        'id' => 5,
        'name' => 'The Ruwende Family',
        'head' => 'Anesu Ruwende',
        'head_phone' => '+263 780 967 054',
        'head_gender' => 'Male',
        'members' => [
            [
                'name' => 'Anesu Ruwende',
                'rel' => 'Head',
                'age' => 57,
                'gender' => 'Male',
            ],
            [
                'name' => 'Melody Ruwende',
                'rel' => 'Spouse',
                'age' => 56,
                'gender' => 'Female',
            ],
        ],
        'adults' => 2,
        'children' => 0,
        'suburb' => 'Glen Norah',
        'city' => 'Harare',
        'address' => '108 Borrowdale Road',
        'cell_group' => 'Mabelreign Cell',
        'created' => '2023-03-19',
    ],
    [
        'id' => 6,
        'name' => 'The Chuma Family',
        'head' => 'Tendai Chuma',
        'head_phone' => '+263 716 417 080',
        'head_gender' => 'Male',
        'members' => [
            [
                'name' => 'Tendai Chuma',
                'rel' => 'Head',
                'age' => 61,
                'gender' => 'Male',
            ],
            [
                'name' => 'Melody Chuma',
                'rel' => 'Spouse',
                'age' => 60,
                'gender' => 'Female',
            ],
            [
                'name' => 'Constance Chuma',
                'rel' => 'Child',
                'age' => 2,
                'gender' => 'Female',
            ],
            [
                'name' => 'Trust Chuma',
                'rel' => 'Child',
                'age' => 2,
                'gender' => 'Male',
            ],
            [
                'name' => 'Farai Chuma',
                'rel' => 'Child',
                'age' => 16,
                'gender' => 'Male',
            ],
            [
                'name' => 'Denford Chuma',
                'rel' => 'Child',
                'age' => 12,
                'gender' => 'Male',
            ],
        ],
        'adults' => 2,
        'children' => 4,
        'suburb' => 'Mbare',
        'city' => 'Harare',
        'address' => '58 Samora Machel Road',
        'cell_group' => 'Mount Pleasant Cell',
        'created' => '2024-05-12',
    ],
    [
        'id' => 7,
        'name' => 'The Dube Family',
        'head' => 'Sekai Dube',
        'head_phone' => '+263 789 124 190',
        'head_gender' => 'Female',
        'members' => [
            [
                'name' => 'Sekai Dube',
                'rel' => 'Head',
                'age' => 45,
                'gender' => 'Female',
            ],
            [
                'name' => 'Herbert Dube',
                'rel' => 'Spouse',
                'age' => 44,
                'gender' => 'Male',
            ],
            [
                'name' => 'Yeukai Dube',
                'rel' => 'Child',
                'age' => 8,
                'gender' => 'Female',
            ],
        ],
        'adults' => 2,
        'children' => 1,
        'suburb' => 'Eastlea',
        'city' => 'Harare',
        'address' => '148 Harare Drive Road',
        'cell_group' => 'Glen View Cell',
        'created' => '2025-07-19',
    ],
    [
        'id' => 8,
        'name' => 'The Kanyemba Family',
        'head' => 'Tapiwa Kanyemba',
        'head_phone' => '+263 713 799 650',
        'head_gender' => 'Male',
        'members' => [
            [
                'name' => 'Tapiwa Kanyemba',
                'rel' => 'Head',
                'age' => 45,
                'gender' => 'Male',
            ],
            [
                'name' => 'Chiedza Kanyemba',
                'rel' => 'Spouse',
                'age' => 45,
                'gender' => 'Female',
            ],
            [
                'name' => 'Fadzai Kanyemba',
                'rel' => 'Relative',
                'age' => 23,
                'gender' => 'Female',
            ],
            [
                'name' => 'Wellington Kanyemba',
                'rel' => 'Child',
                'age' => 12,
                'gender' => 'Male',
            ],
            [
                'name' => 'Thandiwe Kanyemba',
                'rel' => 'Child',
                'age' => 4,
                'gender' => 'Female',
            ],
            [
                'name' => 'Shamiso Kanyemba',
                'rel' => 'Child',
                'age' => 6,
                'gender' => 'Female',
            ],
            [
                'name' => 'Charity Kanyemba',
                'rel' => 'Child',
                'age' => 10,
                'gender' => 'Female',
            ],
            [
                'name' => 'Primrose Kanyemba',
                'rel' => 'Child',
                'age' => 10,
                'gender' => 'Female',
            ],
        ],
        'adults' => 3,
        'children' => 5,
        'suburb' => 'Vainona',
        'city' => 'Harare',
        'address' => '84 Enterprise Road',
        'cell_group' => 'Mabelreign Cell',
        'created' => '2024-12-03',
    ],
    [
        'id' => 9,
        'name' => 'The Ruwende Family',
        'head' => 'Anesu Ruwende',
        'head_phone' => '+263 785 788 568',
        'head_gender' => 'Male',
        'members' => [
            [
                'name' => 'Anesu Ruwende',
                'rel' => 'Head',
                'age' => 48,
                'gender' => 'Male',
            ],
            [
                'name' => 'Mercy Ruwende',
                'rel' => 'Spouse',
                'age' => 48,
                'gender' => 'Female',
            ],
            [
                'name' => 'Faith Ruwende',
                'rel' => 'Child',
                'age' => 8,
                'gender' => 'Female',
            ],
            [
                'name' => 'Charity Ruwende',
                'rel' => 'Child',
                'age' => 16,
                'gender' => 'Female',
            ],
            [
                'name' => 'Tatenda Ruwende',
                'rel' => 'Child',
                'age' => 10,
                'gender' => 'Male',
            ],
        ],
        'adults' => 2,
        'children' => 3,
        'suburb' => 'Southerton',
        'city' => 'Harare',
        'address' => '91 Kirkman Road',
        'cell_group' => 'Highfield Cell',
        'created' => '2022-10-07',
    ],
    [
        'id' => 10,
        'name' => 'The Banda Family',
        'head' => 'Brian Banda',
        'head_phone' => '+263 782 710 947',
        'head_gender' => 'Male',
        'members' => [
            [
                'name' => 'Brian Banda',
                'rel' => 'Head',
                'age' => 38,
                'gender' => 'Male',
            ],
            [
                'name' => 'Takudzwa Banda',
                'rel' => 'Child',
                'age' => 6,
                'gender' => 'Male',
            ],
            [
                'name' => 'Tonderai Banda',
                'rel' => 'Child',
                'age' => 10,
                'gender' => 'Male',
            ],
            [
                'name' => 'Shamiso Banda',
                'rel' => 'Child',
                'age' => 8,
                'gender' => 'Female',
            ],
            [
                'name' => 'Chipo Banda',
                'rel' => 'Child',
                'age' => 6,
                'gender' => 'Female',
            ],
        ],
        'adults' => 1,
        'children' => 4,
        'suburb' => 'Tynwald',
        'city' => 'Harare',
        'address' => '113 Seke Road',
        'cell_group' => 'Chitungwiza Cell',
        'created' => '2024-11-06',
    ],
    [
        'id' => 11,
        'name' => 'The Sibanda Family',
        'head' => 'Tatenda Sibanda',
        'head_phone' => '+263 778 120 679',
        'head_gender' => 'Male',
        'members' => [
            [
                'name' => 'Tatenda Sibanda',
                'rel' => 'Head',
                'age' => 61,
                'gender' => 'Male',
            ],
            [
                'name' => 'Chipo Sibanda',
                'rel' => 'Spouse',
                'age' => 56,
                'gender' => 'Female',
            ],
            [
                'name' => 'Munashe Sibanda',
                'rel' => 'Relative',
                'age' => 23,
                'gender' => 'Male',
            ],
            [
                'name' => 'Edmore Sibanda',
                'rel' => 'Child',
                'age' => 14,
                'gender' => 'Male',
            ],
            [
                'name' => 'Ropafadzo Sibanda',
                'rel' => 'Child',
                'age' => 16,
                'gender' => 'Female',
            ],
            [
                'name' => 'Michael Sibanda',
                'rel' => 'Child',
                'age' => 10,
                'gender' => 'Male',
            ],
            [
                'name' => 'Fadzai Sibanda',
                'rel' => 'Child',
                'age' => 8,
                'gender' => 'Female',
            ],
        ],
        'adults' => 3,
        'children' => 4,
        'suburb' => 'Tynwald',
        'city' => 'Harare',
        'address' => '75 Chiremba Road',
        'cell_group' => 'Avondale Cell',
        'created' => '2025-02-21',
    ],
    [
        'id' => 12,
        'name' => 'The Chidziva Family',
        'head' => 'Tafara Chidziva',
        'head_phone' => '+263 785 940 139',
        'head_gender' => 'Male',
        'members' => [
            [
                'name' => 'Tafara Chidziva',
                'rel' => 'Head',
                'age' => 61,
                'gender' => 'Male',
            ],
            [
                'name' => 'Ratidzo Chidziva',
                'rel' => 'Spouse',
                'age' => 55,
                'gender' => 'Female',
            ],
            [
                'name' => 'Trust Chidziva',
                'rel' => 'Relative',
                'age' => 19,
                'gender' => 'Male',
            ],
            [
                'name' => 'Farai Chidziva',
                'rel' => 'Child',
                'age' => 6,
                'gender' => 'Male',
            ],
            [
                'name' => 'Rudo Chidziva',
                'rel' => 'Child',
                'age' => 10,
                'gender' => 'Female',
            ],
            [
                'name' => 'Tariro Chidziva',
                'rel' => 'Child',
                'age' => 10,
                'gender' => 'Female',
            ],
            [
                'name' => 'Grace Chidziva',
                'rel' => 'Child',
                'age' => 16,
                'gender' => 'Female',
            ],
        ],
        'adults' => 3,
        'children' => 4,
        'suburb' => 'Avondale',
        'city' => 'Harare',
        'address' => '173 Willowvale Road',
        'cell_group' => 'Hatfield Cell',
        'created' => '2024-01-25',
    ],
];

$suburbs_demo = ['Avondale', 'Belvedere', 'Borrowdale', 'Braeside', 'Budiriro', 'Chisipite', 'Chitungwiza', 'Dzivarasekwa', 'Eastlea', 'Emerald Hill', 'Glen Norah', 'Glen View', 'Greendale', 'Hatfield', 'Highfield', 'Highlands', 'Kuwadzana', 'Mabelreign', 'Marlborough', 'Mbare', 'Milton Park', 'Mount Pleasant', 'Msasa Park', 'Mufakose', 'Norton', 'Ruwa', 'Southerton', 'Tynwald', 'Vainona', 'Warren Park', 'Waterfalls', 'Westgate'];

$provinces_demo = ['Harare', 'Bulawayo', 'Manicaland', 'Mashonaland Central', 'Mashonaland East', 'Mashonaland West', 'Masvingo', 'Matabeleland North', 'Matabeleland South', 'Midlands'];

$departments_list = ['Ushering', 'Choir', 'Praise & Worship', 'Youth Ministry', 'Women\'s Fellowship', 'Men\'s Fellowship', 'Children\'s Ministry', 'Media & Sound', 'Protocol', 'Evangelism', 'Intercession', 'Welfare', 'Sunday School', 'Transport'];

$cells_list = ['Westgate Cell', 'Borrowdale Cell', 'Chitungwiza Cell', 'Avondale Cell', 'Highfield Cell', 'Waterfalls Cell', 'Glen View Cell', 'Mabelreign Cell', 'Kuwadzana Cell', 'Hatfield Cell', 'Mount Pleasant Cell', 'Budiriro Cell'];

$occupations_demo = ['Accountant', 'Administrator', 'Builder', 'Chef', 'Civil Servant', 'Driver', 'Electrician', 'Engineer', 'Farmer', 'Hairdresser', 'Mechanic', 'Nurse', 'Retired', 'Security Guard', 'Shop Assistant', 'Software Developer', 'Student', 'Tailor', 'Teacher', 'Trader'];
$visitors_demo = [
    [
        'id' => 1,
        'name' => 'Vimbai Mutandwa',
        'gender' => 'Female',
        'age' => 67,
        'age_group' => 'Seniors (60+)',
        'phone' => '+263 775 908 301',
        'email' => 'vimbai56@gmail.com',
        'suburb' => 'Ruwa',
        'first_visit' => '2026-08-15',
        'first_visit_days' => 11,
        'service' => 'Sunday 9:00 AM',
        'invited_by' => 'Tendai Marufu',
        'stage' => 'New Visitor',
        'stage_days' => 18,
        'assigned_to' => 'Grace Chikomo',
        'last_contact_days' => null,
        'followups' => 0,
        'heard' => 'Family member',
    ],
    [
        'id' => 2,
        'name' => 'Chipo Nkomo',
        'gender' => 'Female',
        'age' => 17,
        'age_group' => 'Youth (13-24)',
        'phone' => '+263 730 308 246',
        'email' => 'chipo19@gmail.com',
        'suburb' => 'Belvedere',
        'first_visit' => '2026-08-17',
        'first_visit_days' => 9,
        'service' => 'Friday Prayer',
        'invited_by' => 'Chipo Gumbo',
        'stage' => 'New Visitor',
        'stage_days' => 19,
        'assigned_to' => 'Blessing Moyo',
        'last_contact_days' => null,
        'followups' => 0,
        'heard' => 'Church outreach',
    ],
    [
        'id' => 3,
        'name' => 'Tapiwa Masuku',
        'gender' => 'Male',
        'age' => 58,
        'age_group' => 'Adults (25-59)',
        'phone' => '+263 778 190 937',
        'email' => 'tapiwa88@gmail.com',
        'suburb' => 'Norton',
        'first_visit' => '2026-07-31',
        'first_visit_days' => 26,
        'service' => 'Youth Service',
        'invited_by' => 'Lloyd Kanyemba',
        'stage' => 'New Visitor',
        'stage_days' => 12,
        'assigned_to' => 'Blessing Moyo',
        'last_contact_days' => null,
        'followups' => 0,
        'heard' => 'Radio programme',
    ],
    [
        'id' => 4,
        'name' => 'Ropafadzo Mangwiro',
        'gender' => 'Female',
        'age' => 22,
        'age_group' => 'Youth (13-24)',
        'phone' => '+263 788 757 491',
        'email' => 'ropafadzo16@gmail.com',
        'suburb' => 'Ruwa',
        'first_visit' => '2026-07-24',
        'first_visit_days' => 33,
        'service' => 'Sunday 11:00 AM',
        'invited_by' => 'Chipo Gumbo',
        'stage' => 'New Visitor',
        'stage_days' => 3,
        'assigned_to' => 'Rev. Enock Sithole',
        'last_contact_days' => null,
        'followups' => 0,
        'heard' => 'Radio programme',
    ],
    [
        'id' => 5,
        'name' => 'Chipo Nyoni',
        'gender' => 'Female',
        'age' => 54,
        'age_group' => 'Adults (25-59)',
        'phone' => '+263 739 711 471',
        'email' => 'chipo8@gmail.com',
        'suburb' => 'Eastlea',
        'first_visit' => '2026-07-15',
        'first_visit_days' => 42,
        'service' => 'Friday Prayer',
        'invited_by' => 'Rudo Chirwa',
        'stage' => 'Contacted',
        'stage_days' => 13,
        'assigned_to' => 'Rudo Chirwa',
        'last_contact_days' => 11,
        'followups' => 2,
        'heard' => 'Social media',
    ],
    [
        'id' => 6,
        'name' => 'Anotida Marimo',
        'gender' => 'Female',
        'age' => 7,
        'age_group' => 'Children (0-12)',
        'phone' => '+263 730 342 366',
        'email' => 'anotida64@gmail.com',
        'suburb' => 'Chisipite',
        'first_visit' => '2026-07-10',
        'first_visit_days' => 47,
        'service' => 'Sunday 11:00 AM',
        'invited_by' => 'Lloyd Kanyemba',
        'stage' => 'Contacted',
        'stage_days' => 5,
        'assigned_to' => 'Rudo Chirwa',
        'last_contact_days' => 8,
        'followups' => 1,
        'heard' => 'Family member',
    ],
    [
        'id' => 7,
        'name' => 'Anotida Mutasa',
        'gender' => 'Female',
        'age' => 41,
        'age_group' => 'Adults (25-59)',
        'phone' => '+263 712 122 330',
        'email' => 'anotida63@gmail.com',
        'suburb' => 'Kuwadzana',
        'first_visit' => '2026-07-20',
        'first_visit_days' => 37,
        'service' => 'Youth Service',
        'invited_by' => 'Chipo Gumbo',
        'stage' => 'Contacted',
        'stage_days' => 14,
        'assigned_to' => 'Grace Chikomo',
        'last_contact_days' => 3,
        'followups' => 2,
        'heard' => 'Walked past',
    ],
    [
        'id' => 8,
        'name' => 'Loveness Gwaze',
        'gender' => 'Female',
        'age' => 62,
        'age_group' => 'Seniors (60+)',
        'phone' => '+263 777 866 661',
        'email' => 'loveness62@gmail.com',
        'suburb' => 'Chitungwiza',
        'first_visit' => '2026-07-15',
        'first_visit_days' => 42,
        'service' => 'Sunday 9:00 AM',
        'invited_by' => 'Tendai Marufu',
        'stage' => 'Contacted',
        'stage_days' => 18,
        'assigned_to' => 'Grace Chikomo',
        'last_contact_days' => 11,
        'followups' => 1,
        'heard' => 'Walked past',
    ],
    [
        'id' => 9,
        'name' => 'Shamiso Chirwa',
        'gender' => 'Female',
        'age' => 33,
        'age_group' => 'Adults (25-59)',
        'phone' => '+263 718 159 013',
        'email' => 'shamiso79@gmail.com',
        'suburb' => 'Glen Norah',
        'first_visit' => '2026-08-18',
        'first_visit_days' => 8,
        'service' => 'Sunday 11:00 AM',
        'invited_by' => 'Rudo Chirwa',
        'stage' => 'Contacted',
        'stage_days' => 20,
        'assigned_to' => 'Tendai Marufu',
        'last_contact_days' => 1,
        'followups' => 1,
        'heard' => 'Social media',
    ],
    [
        'id' => 10,
        'name' => 'Faith Chirwa',
        'gender' => 'Female',
        'age' => 17,
        'age_group' => 'Youth (13-24)',
        'phone' => '+263 772 154 728',
        'email' => 'faith3@gmail.com',
        'suburb' => 'Mabelreign',
        'first_visit' => '2026-06-26',
        'first_visit_days' => 61,
        'service' => 'Friday Prayer',
        'invited_by' => 'Chipo Gumbo',
        'stage' => 'Visited / Met',
        'stage_days' => 11,
        'assigned_to' => 'Rev. Enock Sithole',
        'last_contact_days' => 5,
        'followups' => 3,
        'heard' => 'Church outreach',
    ],
    [
        'id' => 11,
        'name' => 'Edmore Mabhena',
        'gender' => 'Male',
        'age' => 31,
        'age_group' => 'Adults (25-59)',
        'phone' => '+263 715 388 853',
        'email' => 'edmore79@gmail.com',
        'suburb' => 'Warren Park',
        'first_visit' => '2026-08-13',
        'first_visit_days' => 13,
        'service' => 'Sunday 11:00 AM',
        'invited_by' => 'Lloyd Kanyemba',
        'stage' => 'Visited / Met',
        'stage_days' => 13,
        'assigned_to' => 'Rev. Enock Sithole',
        'last_contact_days' => 8,
        'followups' => 3,
        'heard' => 'Walked past',
    ],
    [
        'id' => 12,
        'name' => 'Thandiwe Ncube',
        'gender' => 'Female',
        'age' => 7,
        'age_group' => 'Children (0-12)',
        'phone' => '+263 787 551 313',
        'email' => 'thandiwe61@gmail.com',
        'suburb' => 'Warren Park',
        'first_visit' => '2026-06-25',
        'first_visit_days' => 62,
        'service' => 'Youth Service',
        'invited_by' => 'Tendai Marufu',
        'stage' => 'Visited / Met',
        'stage_days' => 21,
        'assigned_to' => 'Rudo Chirwa',
        'last_contact_days' => 2,
        'followups' => 3,
        'heard' => 'Family member',
    ],
    [
        'id' => 13,
        'name' => 'Herbert Marimo',
        'gender' => 'Male',
        'age' => 67,
        'age_group' => 'Seniors (60+)',
        'phone' => '+263 732 651 676',
        'email' => 'herbert11@gmail.com',
        'suburb' => 'Budiriro',
        'first_visit' => '2026-08-09',
        'first_visit_days' => 17,
        'service' => 'Sunday 11:00 AM',
        'invited_by' => 'Tendai Marufu',
        'stage' => 'Ready to Join',
        'stage_days' => 8,
        'assigned_to' => 'Grace Chikomo',
        'last_contact_days' => 15,
        'followups' => 4,
        'heard' => 'Walked past',
    ],
    [
        'id' => 14,
        'name' => 'Chiedza Gumbo',
        'gender' => 'Female',
        'age' => 62,
        'age_group' => 'Seniors (60+)',
        'phone' => '+263 710 018 263',
        'email' => 'chiedza28@gmail.com',
        'suburb' => 'Avondale',
        'first_visit' => '2026-06-25',
        'first_visit_days' => 62,
        'service' => 'Youth Service',
        'invited_by' => 'Tendai Marufu',
        'stage' => 'Ready to Join',
        'stage_days' => 19,
        'assigned_to' => 'Tendai Marufu',
        'last_contact_days' => 2,
        'followups' => 4,
        'heard' => 'Family member',
    ],
    [
        'id' => 15,
        'name' => 'Edmore Masuku',
        'gender' => 'Male',
        'age' => 33,
        'age_group' => 'Adults (25-59)',
        'phone' => '+263 775 798 682',
        'email' => 'edmore69@gmail.com',
        'suburb' => 'Glen View',
        'first_visit' => '2026-06-16',
        'first_visit_days' => 71,
        'service' => 'Friday Prayer',
        'invited_by' => 'Walk-in',
        'stage' => 'Converted',
        'stage_days' => 6,
        'assigned_to' => 'Grace Chikomo',
        'last_contact_days' => 15,
        'followups' => 5,
        'heard' => 'Radio programme',
    ],
    [
        'id' => 16,
        'name' => 'Michael Moyo',
        'gender' => 'Male',
        'age' => 19,
        'age_group' => 'Youth (13-24)',
        'phone' => '+263 775 888 718',
        'email' => 'michael8@gmail.com',
        'suburb' => 'Msasa Park',
        'first_visit' => '2026-08-06',
        'first_visit_days' => 20,
        'service' => 'Sunday 11:00 AM',
        'invited_by' => 'Chipo Gumbo',
        'stage' => 'Converted',
        'stage_days' => 5,
        'assigned_to' => 'Grace Chikomo',
        'last_contact_days' => 8,
        'followups' => 5,
        'heard' => 'Invited by a friend',
    ],
    [
        'id' => 17,
        'name' => 'Loveness Ncube',
        'gender' => 'Female',
        'age' => 14,
        'age_group' => 'Youth (13-24)',
        'phone' => '+263 738 878 384',
        'email' => 'loveness72@gmail.com',
        'suburb' => 'Warren Park',
        'first_visit' => '2026-07-14',
        'first_visit_days' => 43,
        'service' => 'Wednesday Bible Study',
        'invited_by' => 'Tendai Marufu',
        'stage' => 'Converted',
        'stage_days' => 10,
        'assigned_to' => 'Rudo Chirwa',
        'last_contact_days' => 11,
        'followups' => 4,
        'heard' => 'Invited by a friend',
    ],
    [
        'id' => 18,
        'name' => 'Charity Mhende',
        'gender' => 'Female',
        'age' => 14,
        'age_group' => 'Youth (13-24)',
        'phone' => '+263 781 252 427',
        'email' => 'charity29@gmail.com',
        'suburb' => 'Marlborough',
        'first_visit' => '2026-07-25',
        'first_visit_days' => 32,
        'service' => 'Wednesday Bible Study',
        'invited_by' => 'Lloyd Kanyemba',
        'stage' => 'Converted',
        'stage_days' => 8,
        'assigned_to' => 'Rev. Enock Sithole',
        'last_contact_days' => 1,
        'followups' => 5,
        'heard' => 'Church outreach',
    ],
];

$visitor_timeline_demo = [
    [
        'date' => '2026-08-24',
        'method' => 'Call',
        'person' => 'Grace Chikomo',
        'outcome' => 'Spoke with them',
        'notes' => 'Very keen to join a cell group near Waterfalls.',
    ],
    [
        'date' => '2026-08-20',
        'method' => 'SMS',
        'person' => 'Rev. Enock Sithole',
        'outcome' => 'No answer',
        'notes' => 'Phone rang out — will try again on Saturday.',
    ],
    [
        'date' => '2026-08-15',
        'method' => 'WhatsApp',
        'person' => 'Tendai Marufu',
        'outcome' => 'Promised to come',
        'notes' => 'Attended Wednesday Bible Study for the first time.',
    ],
    [
        'date' => '2026-08-08',
        'method' => 'Visit',
        'person' => 'Rudo Chirwa',
        'outcome' => 'Requested a visit',
        'notes' => 'Asked about the new members class schedule.',
    ],
    [
        'date' => '2026-08-01',
        'method' => 'Email',
        'person' => 'Blessing Moyo',
        'outcome' => 'Left a message',
        'notes' => 'Welcomed after the second service.',
    ],
];

$departments_demo = [
    [
        'id' => 1,
        'name' => 'Ushering',
        'description' => 'Welcoming and seating the congregation',
        'icon' => 'fa-hands-holding-circle',
        'color' => 'brand',
        'head' => 'Blessing Chidembo',
        'head_gender' => 'Male',
        'head_phone' => '+263 713 551 505',
        'assistant' => 'Charity Ncube',
        'members' => 64,
        'active' => 58,
        'day' => 'Sunday',
        'time' => '7:30 AM',
        'venue' => 'Main Auditorium',
        'attendance_rate' => 92,
        'status' => 'Active',
        'created' => '2024-03-15',
    ],
    [
        'id' => 2,
        'name' => 'Choir',
        'description' => 'Leading the congregation in song',
        'icon' => 'fa-music',
        'color' => 'info',
        'head' => 'Mercy Nyoni',
        'head_gender' => 'Female',
        'head_phone' => '+263 771 440 242',
        'assistant' => 'Primrose Mudzingwa',
        'members' => 48,
        'active' => 42,
        'day' => 'Thursday',
        'time' => '6:00 PM',
        'venue' => 'Choir Room',
        'attendance_rate' => 88,
        'status' => 'Active',
        'created' => '2020-08-27',
    ],
    [
        'id' => 3,
        'name' => 'Praise & Worship',
        'description' => 'Worship team and instrumentalists',
        'icon' => 'fa-guitar',
        'color' => 'violet',
        'head' => 'Yeukai Mhende',
        'head_gender' => 'Female',
        'head_phone' => '+263 782 614 014',
        'assistant' => 'Michael Chuma',
        'members' => 26,
        'active' => 23,
        'day' => 'Tuesday',
        'time' => '6:00 PM',
        'venue' => 'Main Auditorium',
        'attendance_rate' => 90,
        'status' => 'Active',
        'created' => '2025-10-13',
    ],
    [
        'id' => 4,
        'name' => 'Youth Ministry',
        'description' => 'Ministry to teenagers and young adults',
        'icon' => 'fa-fire',
        'color' => 'warn',
        'head' => 'Denford Moyo',
        'head_gender' => 'Male',
        'head_phone' => '+263 719 208 312',
        'assistant' => 'Chipo Ndlovu',
        'members' => 72,
        'active' => 56,
        'day' => 'Saturday',
        'time' => '2:00 PM',
        'venue' => 'Youth Hall',
        'attendance_rate' => 78,
        'status' => 'Active',
        'created' => '2023-10-06',
    ],
    [
        'id' => 5,
        'name' => 'Women\'s Fellowship',
        'description' => 'Fellowship and outreach among women',
        'icon' => 'fa-hands-praying',
        'color' => 'pink',
        'head' => 'Mercy Ruwende',
        'head_gender' => 'Female',
        'head_phone' => '+263 782 450 400',
        'assistant' => 'Brian Nyamande',
        'members' => 86,
        'active' => 72,
        'day' => 'Wednesday',
        'time' => '3:00 PM',
        'venue' => 'Fellowship Room',
        'attendance_rate' => 84,
        'status' => 'Active',
        'created' => '2023-04-20',
    ],
    [
        'id' => 6,
        'name' => 'Men\'s Fellowship',
        'description' => 'Fellowship and mentorship among men',
        'icon' => 'fa-people-group',
        'color' => 'teal',
        'head' => 'Precious Saruchera',
        'head_gender' => 'Female',
        'head_phone' => '+263 786 786 843',
        'assistant' => 'Anesu Kanyemba',
        'members' => 54,
        'active' => 38,
        'day' => 'Saturday',
        'time' => '6:00 AM',
        'venue' => 'Fellowship Room',
        'attendance_rate' => 71,
        'status' => 'Active',
        'created' => '2021-07-06',
    ],
    [
        'id' => 7,
        'name' => 'Children\'s Ministry',
        'description' => 'Sunday care and teaching for children',
        'icon' => 'fa-child-reaching',
        'color' => 'ok',
        'head' => 'Thandiwe Sibanda',
        'head_gender' => 'Female',
        'head_phone' => '+263 784 620 168',
        'assistant' => 'Netsai Mangwiro',
        'members' => 41,
        'active' => 38,
        'day' => 'Sunday',
        'time' => '9:00 AM',
        'venue' => 'Children\'s Wing',
        'attendance_rate' => 95,
        'status' => 'Active',
        'created' => '2025-09-16',
    ],
    [
        'id' => 8,
        'name' => 'Media & Sound',
        'description' => 'Sound, streaming and photography',
        'icon' => 'fa-sliders',
        'color' => 'slate',
        'head' => 'Shamiso Mhaka',
        'head_gender' => 'Female',
        'head_phone' => '+263 715 585 304',
        'assistant' => 'Kudzai Ndlovu',
        'members' => 18,
        'active' => 15,
        'day' => 'Sunday',
        'time' => '7:00 AM',
        'venue' => 'Media Booth',
        'attendance_rate' => 86,
        'status' => 'Active',
        'created' => '2026-01-31',
    ],
    [
        'id' => 9,
        'name' => 'Protocol',
        'description' => 'Order of service and VIP care',
        'icon' => 'fa-user-tie',
        'color' => 'brand',
        'head' => 'Grace Mangoma',
        'head_gender' => 'Female',
        'head_phone' => '+263 773 801 412',
        'assistant' => 'Constance Dube',
        'members' => 22,
        'active' => 17,
        'day' => 'Sunday',
        'time' => '8:00 AM',
        'venue' => 'Foyer',
        'attendance_rate' => 81,
        'status' => 'Active',
        'created' => '2022-06-05',
    ],
    [
        'id' => 10,
        'name' => 'Evangelism',
        'description' => 'Outreach and door-to-door ministry',
        'icon' => 'fa-bullhorn',
        'color' => 'warn',
        'head' => 'Ropafadzo Chuma',
        'head_gender' => 'Female',
        'head_phone' => '+263 782 965 724',
        'assistant' => 'Farai Nyamande',
        'members' => 38,
        'active' => 26,
        'day' => 'Saturday',
        'time' => '9:00 AM',
        'venue' => 'Church Grounds',
        'attendance_rate' => 69,
        'status' => 'Active',
        'created' => '2020-09-25',
    ],
    [
        'id' => 11,
        'name' => 'Intercession',
        'description' => 'Prayer ministry and prayer chains',
        'icon' => 'fa-hands-praying',
        'color' => 'violet',
        'head' => 'Nigel Mabhena',
        'head_gender' => 'Male',
        'head_phone' => '+263 779 310 025',
        'assistant' => 'Tatenda Saruchera',
        'members' => 44,
        'active' => 40,
        'day' => 'Friday',
        'time' => '5:00 PM',
        'venue' => 'Prayer Room',
        'attendance_rate' => 93,
        'status' => 'Active',
        'created' => '2021-06-04',
    ],
    [
        'id' => 12,
        'name' => 'Welfare',
        'description' => 'Care for the bereaved and the needy',
        'icon' => 'fa-heart',
        'color' => 'danger',
        'head' => 'Trust Sithole',
        'head_gender' => 'Male',
        'head_phone' => '+263 710 718 818',
        'assistant' => 'Brian Mangoma',
        'members' => 29,
        'active' => 22,
        'day' => 'Monday',
        'time' => '4:00 PM',
        'venue' => 'Welfare Office',
        'attendance_rate' => 76,
        'status' => 'Active',
        'created' => '2023-05-12',
    ],
    [
        'id' => 13,
        'name' => 'Sunday School',
        'description' => 'Structured teaching for all ages',
        'icon' => 'fa-book-open',
        'color' => 'info',
        'head' => 'Precious Ruwende',
        'head_gender' => 'Female',
        'head_phone' => '+263 717 617 409',
        'assistant' => 'Tafara Mpofu',
        'members' => 57,
        'active' => 50,
        'day' => 'Sunday',
        'time' => '8:00 AM',
        'venue' => 'Classrooms',
        'attendance_rate' => 89,
        'status' => 'Active',
        'created' => '2020-08-19',
    ],
    [
        'id' => 14,
        'name' => 'Transport',
        'description' => 'Church vehicles and member pickups',
        'icon' => 'fa-van-shuttle',
        'color' => 'teal',
        'head' => 'Rumbidzai Banda',
        'head_gender' => 'Female',
        'head_phone' => '+263 777 074 137',
        'assistant' => 'Yeukai Mabhena',
        'members' => 16,
        'active' => 11,
        'day' => 'Sunday',
        'time' => '6:30 AM',
        'venue' => 'Car Park',
        'attendance_rate' => 74,
        'status' => 'Inactive',
        'created' => '2025-05-10',
    ],
];

$cells_demo = [
    [
        'id' => 1,
        'name' => 'Westgate Cell',
        'zone' => 'North Zone',
        'leader' => 'Charity Chirwa',
        'leader_gender' => 'Female',
        'leader_phone' => '+263 778 452 984',
        'assistant' => 'Rutendo Chidziva',
        'members' => 34,
        'day' => 'Tuesday',
        'time' => '6:30 PM',
        'venue' => '60 Seke Road',
        'suburb' => 'Vainona',
        'last_meeting' => '2026-08-07',
        'last_meeting_days' => 19,
        'last_attendance' => 13,
        'recorded' => false,
        'sparkline' => [19, 23, 17, 17, 13, 13],
        'avg_attendance' => 50,
        'status' => 'Active',
    ],
    [
        'id' => 2,
        'name' => 'Borrowdale Cell',
        'zone' => 'South Zone',
        'leader' => 'Rutendo Mhaka',
        'leader_gender' => 'Female',
        'leader_phone' => '+263 716 130 445',
        'assistant' => 'Memory Banda',
        'members' => 26,
        'day' => 'Tuesday',
        'time' => '4:00 PM',
        'venue' => '100 Harare Drive Road',
        'suburb' => 'Greendale',
        'last_meeting' => '2026-08-10',
        'last_meeting_days' => 16,
        'last_attendance' => 14,
        'recorded' => false,
        'sparkline' => [14, 10, 22, 14, 9, 14],
        'avg_attendance' => 53,
        'status' => 'Active',
    ],
    [
        'id' => 3,
        'name' => 'Chitungwiza Cell',
        'zone' => 'East Zone',
        'leader' => 'Ratidzo Chidembo',
        'leader_gender' => 'Female',
        'leader_phone' => '+263 716 068 831',
        'assistant' => 'Rudo Sibanda',
        'members' => 42,
        'day' => 'Tuesday',
        'time' => '4:00 PM',
        'venue' => '116 Harare Drive Road',
        'suburb' => 'Waterfalls',
        'last_meeting' => '2026-08-20',
        'last_meeting_days' => 6,
        'last_attendance' => 20,
        'recorded' => true,
        'sparkline' => [21, 22, 27, 30, 24, 20],
        'avg_attendance' => 57,
        'status' => 'Active',
    ],
    [
        'id' => 4,
        'name' => 'Avondale Cell',
        'zone' => 'West Zone',
        'leader' => 'Faith Sibanda',
        'leader_gender' => 'Female',
        'leader_phone' => '+263 774 786 122',
        'assistant' => 'Herbert Paradza',
        'members' => 24,
        'day' => 'Tuesday',
        'time' => '6:00 PM',
        'venue' => '129 Seke Road',
        'suburb' => 'Hatfield',
        'last_meeting' => '2026-08-17',
        'last_meeting_days' => 9,
        'last_attendance' => 18,
        'recorded' => true,
        'sparkline' => [19, 19, 18, 12, 14, 18],
        'avg_attendance' => 69,
        'status' => 'Active',
    ],
    [
        'id' => 5,
        'name' => 'Highfield Cell',
        'zone' => 'Central Zone',
        'leader' => 'Ratidzo Karimanzira',
        'leader_gender' => 'Female',
        'leader_phone' => '+263 783 066 683',
        'assistant' => 'Joseph Kanyemba',
        'members' => 18,
        'day' => 'Friday',
        'time' => '6:30 PM',
        'venue' => '87 Chiremba Road',
        'suburb' => 'Vainona',
        'last_meeting' => '2026-08-14',
        'last_meeting_days' => 12,
        'last_attendance' => 10,
        'recorded' => true,
        'sparkline' => [14, 7, 11, 9, 11, 10],
        'avg_attendance' => 57,
        'status' => 'Active',
    ],
    [
        'id' => 6,
        'name' => 'Waterfalls Cell',
        'zone' => 'North Zone',
        'leader' => 'Constance Gwaze',
        'leader_gender' => 'Female',
        'leader_phone' => '+263 770 679 701',
        'assistant' => 'Tafara Museka',
        'members' => 28,
        'day' => 'Friday',
        'time' => '4:00 PM',
        'venue' => '115 Enterprise Road',
        'suburb' => 'Marlborough',
        'last_meeting' => '2026-08-07',
        'last_meeting_days' => 19,
        'last_attendance' => 9,
        'recorded' => false,
        'sparkline' => [15, 13, 22, 22, 22, 9],
        'avg_attendance' => 61,
        'status' => 'Active',
    ],
    [
        'id' => 7,
        'name' => 'Glen View Cell',
        'zone' => 'South Zone',
        'leader' => 'Simba Gumbo',
        'leader_gender' => 'Male',
        'leader_phone' => '+263 784 248 611',
        'assistant' => 'Tonderai Zvobgo',
        'members' => 12,
        'day' => 'Tuesday',
        'time' => '6:30 PM',
        'venue' => '135 Harare Drive Road',
        'suburb' => 'Warren Park',
        'last_meeting' => '2026-08-24',
        'last_meeting_days' => 2,
        'last_attendance' => 9,
        'recorded' => true,
        'sparkline' => [10, 5, 5, 8, 4, 9],
        'avg_attendance' => 56,
        'status' => 'Active',
    ],
    [
        'id' => 8,
        'name' => 'Mabelreign Cell',
        'zone' => 'East Zone',
        'leader' => 'Tariro Chuma',
        'leader_gender' => 'Female',
        'leader_phone' => '+263 714 003 761',
        'assistant' => 'Joseph Banda',
        'members' => 26,
        'day' => 'Thursday',
        'time' => '6:00 PM',
        'venue' => '109 Willowvale Road',
        'suburb' => 'Hatfield',
        'last_meeting' => '2026-08-19',
        'last_meeting_days' => 7,
        'last_attendance' => 9,
        'recorded' => true,
        'sparkline' => [16, 17, 12, 17, 12, 9],
        'avg_attendance' => 53,
        'status' => 'Active',
    ],
    [
        'id' => 9,
        'name' => 'Kuwadzana Cell',
        'zone' => 'West Zone',
        'leader' => 'Chipo Karimanzira',
        'leader_gender' => 'Female',
        'leader_phone' => '+263 774 419 792',
        'assistant' => 'Sekai Moyo',
        'members' => 15,
        'day' => 'Wednesday',
        'time' => '4:00 PM',
        'venue' => '107 Chiremba Road',
        'suburb' => 'Glen View',
        'last_meeting' => '2026-08-19',
        'last_meeting_days' => 7,
        'last_attendance' => 12,
        'recorded' => true,
        'sparkline' => [12, 8, 9, 8, 8, 12],
        'avg_attendance' => 63,
        'status' => 'Active',
    ],
    [
        'id' => 10,
        'name' => 'Hatfield Cell',
        'zone' => 'Central Zone',
        'leader' => 'Chipo Ruwende',
        'leader_gender' => 'Female',
        'leader_phone' => '+263 772 532 870',
        'assistant' => 'Lloyd Sibanda',
        'members' => 28,
        'day' => 'Thursday',
        'time' => '4:00 PM',
        'venue' => '96 Willowvale Road',
        'suburb' => 'Mufakose',
        'last_meeting' => '2026-08-07',
        'last_meeting_days' => 19,
        'last_attendance' => 10,
        'recorded' => false,
        'sparkline' => [23, 20, 23, 14, 20, 10],
        'avg_attendance' => 65,
        'status' => 'Active',
    ],
    [
        'id' => 11,
        'name' => 'Mount Pleasant Cell',
        'zone' => 'North Zone',
        'leader' => 'Tapiwa Moyo',
        'leader_gender' => 'Male',
        'leader_phone' => '+263 775 873 557',
        'assistant' => 'Kudzai Paradza',
        'members' => 21,
        'day' => 'Tuesday',
        'time' => '4:00 PM',
        'venue' => '64 Seke Road',
        'suburb' => 'Mount Pleasant',
        'last_meeting' => '2026-08-10',
        'last_meeting_days' => 16,
        'last_attendance' => 14,
        'recorded' => false,
        'sparkline' => [12, 11, 13, 8, 7, 14],
        'avg_attendance' => 51,
        'status' => 'Active',
    ],
    [
        'id' => 12,
        'name' => 'Budiriro Cell',
        'zone' => 'South Zone',
        'leader' => 'Chipo Zvobgo',
        'leader_gender' => 'Female',
        'leader_phone' => '+263 785 440 910',
        'assistant' => 'Sekai Nyoni',
        'members' => 26,
        'day' => 'Wednesday',
        'time' => '5:30 PM',
        'venue' => '122 Seke Road',
        'suburb' => 'Glen Norah',
        'last_meeting' => '2026-08-17',
        'last_meeting_days' => 9,
        'last_attendance' => 20,
        'recorded' => true,
        'sparkline' => [14, 18, 9, 13, 20, 20],
        'avg_attendance' => 60,
        'status' => 'Inactive',
    ],
];

$cell_meetings_demo = [
    [
        'date' => '2026-08-23',
        'attendance' => 24,
        'topic' => 'Walking in faith',
        'notes' => 'Good discussion, three visitors present.',
    ],
    [
        'date' => '2026-08-16',
        'attendance' => 16,
        'topic' => 'The fruit of the Spirit',
        'notes' => 'Shorter meeting due to rain.',
    ],
    [
        'date' => '2026-08-09',
        'attendance' => 26,
        'topic' => 'Prayer and fasting',
        'notes' => 'Good discussion, three visitors present.',
    ],
    [
        'date' => '2026-08-02',
        'attendance' => 18,
        'topic' => 'Hospitality in the home',
        'notes' => 'Shorter meeting due to rain.',
    ],
    [
        'date' => '2026-07-26',
        'attendance' => 25,
        'topic' => 'Standing firm',
        'notes' => 'Good discussion, three visitors present.',
    ],
    [
        'date' => '2026-07-19',
        'attendance' => 19,
        'topic' => 'Serving one another',
        'notes' => 'Shorter meeting due to rain.',
    ],
];

$zones_demo = ['North Zone', 'South Zone', 'East Zone', 'West Zone', 'Central Zone'];

$services_demo = ['Sunday 9:00 AM', 'Sunday 11:00 AM', 'Youth Service', 'Wednesday Bible Study', 'Friday Prayer'];

/* Headline figures shown in the stat strips. Kept apart from the row data
   because in production these are cheap COUNT queries over the whole table,
   not counts of the current page.
   LATER: SELECT COUNT(*) ... per figure, scoped to :church_id. */
$people_stats = [
    'members'    => ['total' => 1284, 'active' => 1198, 'new_month' => 12, 'inactive' => 86],
    'households' => ['total' => 312, 'in_households' => 1102, 'unassigned' => 182, 'avg_size' => 3.5],
    'visitors'   => ['this_month' => 18, 'pending' => 4, 'contacted' => 9, 'converted' => 5],
    'departments'=> ['total' => 14, 'serving' => 486, 'heads' => 14, 'not_serving' => 798],
    'cells'      => ['total' => 28, 'in_cells' => 892, 'avg_size' => 32, 'meeting_week' => 24],
];

/* ==========================================================================
   13b. ATTENDANCE — SERVICES, REGISTERS AND COUNT GROUPS
   Everything the Record Attendance page needs. The member roll it marks is
   $members_demo above; only the service metadata lives here.
   ========================================================================== */

/* The services a register can be taken against. `default_start` seeds the
   Service Details time fields so the usher rarely has to type a time. `dow`
   is the weekday the service actually falls on — the register dates every
   demo row off it, so a "Sunday Service" is never dated to a Thursday.
   LATER: SELECT id, name, icon, default_start, default_end
            FROM service_types WHERE church_id = :church_id AND active = 1
        ORDER BY sort_order; */
/* ──────────────────────────── THE SERVICE LIST ────────────────────────────
   The full definition of every recurring service the church holds. This is
   what attendance/services.php manages, and it is the single source the
   record page's dropdown is built from — change a service here and the
   register, the recorder and the schedule all follow.

   `dow` is the weekday the service falls on; the register dates every demo
   row off it, so a "Sunday Service" is never dated to a Thursday.
   `spark` is the last eight occurrences, oldest first.
   LATER: SELECT * FROM services WHERE church_id = :church_id ORDER BY sort;
   ──────────────────────────────────────────────────────────────────────── */
$services_demo = [
    [
        'id' => 'sunday-first', 'name' => 'Sunday First Service', 'type' => 'Weekly',
        'icon' => 'fa-church', 'colour' => '#662F97',
        'dow' => 'Sunday', 'default_start' => '08:00', 'default_end' => '10:00',
        'venue' => 'Main Sanctuary', 'responsible' => 'Rev. Enock Sithole',
        'expected' => 420, 'average' => 331, 'active' => true, 'last_held_days' => 4,
        'description' => 'The early Sunday gathering, with the full liturgy and a shorter sermon.',
        'notes' => 'Doors open 07:30. Ushers to be seated by 07:45.',
        'track_individual' => true, 'record_offering' => true,
        'spark' => [289, 302, 358, 331, 344, 318, 327, 331],
    ],
    [
        'id' => 'sunday-second', 'name' => 'Sunday Second Service', 'type' => 'Weekly',
        'icon' => 'fa-church', 'colour' => '#8F5CC2',
        'dow' => 'Sunday', 'default_start' => '10:30', 'default_end' => '12:30',
        'venue' => 'Main Sanctuary', 'responsible' => 'Rev. Enock Sithole',
        'expected' => 500, 'average' => 418, 'active' => true, 'last_held_days' => 4,
        'description' => 'The main Sunday service. Highest attendance of the week.',
        'notes' => 'Overflow seating in the Fellowship Hall when above 460.',
        'track_individual' => true, 'record_offering' => true,
        'spark' => [372, 397, 441, 418, 402, 429, 411, 418],
    ],
    [
        'id' => 'sunday-school', 'name' => 'Sunday School', 'type' => 'Weekly',
        'icon' => 'fa-child-reaching', 'colour' => '#0F766E',
        'dow' => 'Sunday', 'default_start' => '09:00', 'default_end' => '10:15',
        'venue' => 'Education Block', 'responsible' => 'Grace Chikomo',
        'expected' => 180, 'average' => 142, 'active' => true, 'last_held_days' => 4,
        'description' => 'Age-banded classes for children while the first service runs.',
        'notes' => 'Register taken per class, then totalled.',
        'track_individual' => true, 'record_offering' => false,
        'spark' => [118, 131, 149, 142, 138, 151, 144, 142],
    ],
    [
        'id' => 'midweek', 'name' => 'Midweek Service', 'type' => 'Weekly',
        'icon' => 'fa-book-bible', 'colour' => '#1D4ED8',
        'dow' => 'Wednesday', 'default_start' => '17:30', 'default_end' => '19:00',
        'venue' => 'Main Sanctuary', 'responsible' => 'Grace Chikomo',
        'expected' => 200, 'average' => 152, 'active' => true, 'last_held_days' => 1,
        'description' => 'Teaching service working through a book of the Bible.',
        'notes' => '', 'track_individual' => true, 'record_offering' => true,
        'spark' => [139, 147, 168, 152, 161, 144, 158, 152],
    ],
    [
        'id' => 'prayer', 'name' => 'Friday Prayer Meeting', 'type' => 'Weekly',
        'icon' => 'fa-hands-praying', 'colour' => '#B45309',
        'dow' => 'Friday', 'default_start' => '05:30', 'default_end' => '07:00',
        'venue' => 'Prayer Chapel', 'responsible' => 'Blessing Moyo',
        'expected' => 100, 'average' => 64, 'active' => true, 'last_held_days' => 6,
        'description' => 'Early morning intercession before the working day.',
        'notes' => 'Numbers drop sharply in the rains.',
        'track_individual' => true, 'record_offering' => false,
        'spark' => [58, 64, 71, 64, 52, 68, 61, 71],
    ],
    [
        'id' => 'youth', 'name' => 'Youth Service', 'type' => 'Weekly',
        'icon' => 'fa-fire', 'colour' => '#BE185D',
        'dow' => 'Saturday', 'default_start' => '14:00', 'default_end' => '16:00',
        'venue' => 'Youth Hall', 'responsible' => 'Blessing Moyo',
        'expected' => 175, 'average' => 133, 'active' => true, 'last_held_days' => 12,
        'description' => 'Teens and young adults. Music-led, with a short talk.',
        'notes' => '', 'track_individual' => true, 'record_offering' => true,
        'spark' => [121, 128, 144, 133, 139, 126, 131, 144],
    ],
    [
        'id' => 'womens', 'name' => "Women's Fellowship", 'type' => 'Weekly',
        'icon' => 'fa-person-dress', 'colour' => '#6D28D9',
        'dow' => 'Thursday', 'default_start' => '14:00', 'default_end' => '16:00',
        'venue' => 'Fellowship Hall', 'responsible' => 'Grace Chikomo',
        'expected' => 140, 'average' => 108, 'active' => true, 'last_held_days' => 7,
        'description' => 'Weekly gathering of the women of the parish.',
        'notes' => 'Creche provided.', 'track_individual' => true, 'record_offering' => true,
        'spark' => [96, 104, 118, 108, 112, 101, 109, 108],
    ],
    [
        'id' => 'mens', 'name' => "Men's Fellowship", 'type' => 'Monthly',
        'icon' => 'fa-person', 'colour' => '#0369A1',
        'dow' => 'Saturday', 'default_start' => '06:00', 'default_end' => '08:00',
        'venue' => 'Fellowship Hall', 'responsible' => 'Farai Nyoni',
        'expected' => 120, 'average' => 74, 'active' => true, 'last_held_days' => 19,
        'day_of_month' => 'First Saturday',
        'description' => 'Breakfast meeting on the first Saturday of the month.',
        'notes' => '', 'track_individual' => true, 'record_offering' => true,
        'spark' => [61, 68, 82, 74, 71, 79, 66, 74],
    ],
    [
        'id' => 'overnight', 'name' => 'Overnight Prayer', 'type' => 'Monthly',
        'icon' => 'fa-moon', 'colour' => '#475569',
        'dow' => 'Friday', 'default_start' => '22:00', 'default_end' => '04:00',
        'venue' => 'Main Sanctuary', 'responsible' => 'Blessing Moyo',
        'expected' => 160, 'average' => 118, 'active' => true, 'last_held_days' => 27,
        'day_of_month' => 'Last Friday',
        'description' => 'All-night vigil on the last Friday of the month.',
        'notes' => 'Runs past midnight into Saturday morning.',
        'track_individual' => false, 'record_offering' => true,
        'spark' => [102, 118, 131, 118, 109, 124, 114, 118],
    ],
    [
        'id' => 'cell', 'name' => 'Cell Meetings', 'type' => 'Weekly',
        'icon' => 'fa-people-group', 'colour' => '#15803D',
        'dow' => 'Tuesday', 'default_start' => '18:00', 'default_end' => '19:30',
        'venue' => "Members' homes", 'responsible' => 'Rudo Chirwa',
        'expected' => 35, 'average' => 26, 'active' => true, 'last_held_days' => 16,
        'description' => 'Small groups meeting in homes across the zones.',
        'notes' => 'Figures are per cell, not the whole parish.',
        'track_individual' => true, 'record_offering' => true,
        'spark' => [22, 24, 29, 26, 27, 23, 28, 26],
    ],
    [
        'id' => 'baptism', 'name' => 'Baptism Service', 'type' => 'Special',
        'icon' => 'fa-water', 'colour' => '#0891B2',
        'dow' => 'Sunday', 'default_start' => '14:00', 'default_end' => '16:30',
        'venue' => 'Riverside, Manyame', 'responsible' => 'Rev. Enock Sithole',
        'expected' => 90, 'average' => 68, 'active' => true, 'last_held_days' => 63,
        'description' => 'Held quarterly, or when there are enough candidates.',
        'notes' => 'Transport arranged from the church at 13:00.',
        'track_individual' => true, 'record_offering' => false,
        'spark' => [54, 61, 74, 68, 59, 71, 64, 68],
    ],
    [
        'id' => 'communion', 'name' => 'Communion Service', 'type' => 'Monthly',
        'icon' => 'fa-wine-glass', 'colour' => '#B4243F',
        'dow' => 'Sunday', 'default_start' => '10:30', 'default_end' => '12:45',
        'venue' => 'Main Sanctuary', 'responsible' => 'Rev. Enock Sithole',
        'expected' => 500, 'average' => 441, 'active' => true, 'last_held_days' => 25,
        'day_of_month' => 'First Sunday',
        'description' => 'The first Sunday of the month, in place of the second service.',
        'notes' => 'Elements prepared by the Protocol team.',
        'track_individual' => true, 'record_offering' => true,
        'spark' => [402, 418, 458, 441, 436, 449, 428, 441],
    ],
    [
        /* Kept because two registers in the history are attached to it.
           Deleting the service would orphan them. */
        'id' => 'special', 'name' => 'Special Service', 'type' => 'One-off',
        'icon' => 'fa-star', 'colour' => '#CA8A04',
        'dow' => 'Sunday', 'default_start' => '09:00', 'default_end' => '12:00',
        'venue' => 'Main Sanctuary', 'responsible' => 'Tendai Marufu',
        'expected' => 650, 'average' => 560, 'active' => false, 'last_held_days' => 25,
        'on_date' => '+21 days',
        'description' => 'Conventions, ordinations and other one-off diocesan occasions.',
        'notes' => 'Scheduled individually; not part of the weekly pattern.',
        'track_individual' => true, 'record_offering' => true,
        'spark' => [0, 0, 508, 0, 0, 0, 612, 0],
    ],
];

/* The recorder's dropdown is exactly the services defined above — that is what
   makes services.php the setup page for record.php rather than a parallel
   list that can drift out of step. */
$service_types_demo = array_map(static function (array $s): array {
    return [
        'id'            => $s['id'],
        'name'          => $s['name'],
        'icon'          => $s['icon'],
        'default_start' => $s['default_start'],
        'default_end'   => $s['default_end'],
        'dow'           => $s['dow'],
    ];
}, $services_demo);

/* The pickers in the add/edit modal. */
$service_venues_demo = ['Main Sanctuary', 'Fellowship Hall', 'Education Block', 'Youth Hall',
                        'Prayer Chapel', "Members' homes", 'Riverside, Manyame', 'Church Grounds'];
$service_icons_demo  = ['fa-church', 'fa-book-bible', 'fa-hands-praying', 'fa-fire', 'fa-people-group',
                        'fa-star', 'fa-child-reaching', 'fa-person', 'fa-person-dress', 'fa-moon',
                        'fa-water', 'fa-wine-glass', 'fa-music', 'fa-dove', 'fa-heart', 'fa-hand-holding-heart'];
$service_colours_demo = ['#662F97', '#8F5CC2', '#1D4ED8', '#0369A1', '#0891B2', '#0F766E',
                         '#15803D', '#CA8A04', '#B45309', '#B4243F', '#BE185D', '#6D28D9', '#475569'];

/* Registers already captured. The setup card checks the chosen date and
   service against this list and shows the "already recorded" notice on a hit.
   Keyed "YYYY-MM-DD|service_id" so the lookup is a single array_key_exists.
   Dates are relative to today so the demo never goes stale.
   LATER: SELECT id, service_date, service_type_id, present, absent, recorded_by
            FROM attendance_registers
           WHERE church_id = :church_id AND branch_id = :branch_id
             AND service_date = :date AND service_type_id = :service; */
$attendance_recorded_demo = [
    date('Y-m-d', strtotime('last sunday')) . '|sunday-first'  => ['present' => 318, 'absent' => 74, 'recorded_by' => 'Simba Dube',    'at' => '10:12'],
    date('Y-m-d', strtotime('last sunday')) . '|sunday-second' => ['present' => 402, 'absent' => 61, 'recorded_by' => 'Grace Chikomo', 'at' => '12:48'],
    date('Y-m-d', strtotime('-4 days'))     . '|midweek'       => ['present' => 146, 'absent' => 38, 'recorded_by' => 'Simba Dube',    'at' => '19:20'],
    date('Y-m-d', strtotime('-2 days'))     . '|prayer'        => ['present' => 64,  'absent' => 12, 'recorded_by' => 'Blessing Moyo', 'at' => '07:05'],
];

/* Quick Count buckets — the head-count mode for services too large or too
   fluid to mark individually. Order is the order they appear on screen.
   LATER: SELECT bucket_key, label FROM attendance_count_groups
           WHERE church_id = :church_id ORDER BY sort_order; */
$attendance_count_groups = [
    ['key' => 'men',      'label' => 'Men',      'icon' => 'fa-person',       'tone' => 'blue'],
    ['key' => 'women',    'label' => 'Women',    'icon' => 'fa-person-dress', 'tone' => 'pink'],
    ['key' => 'youth',    'label' => 'Youth',    'icon' => 'fa-fire',         'tone' => 'purple'],
    ['key' => 'children', 'label' => 'Children', 'icon' => 'fa-child-reaching','tone' => 'teal'],
    ['key' => 'visitors', 'label' => 'Visitors', 'icon' => 'fa-user-plus',    'tone' => 'amber'],
];

/* The weather note on a register. Rain is the single biggest predictor of a
   thin Sunday in Harare, so it is worth recording alongside the count. */
$attendance_weather_demo = ['Clear', 'Cloudy', 'Light rain', 'Heavy rain', 'Storm', 'Very hot', 'Cold'];

/* ==========================================================================
   13c. ATTENDANCE REGISTER — THE HISTORICAL RECORD
   What the register page reads. Two shapes, because the page answers two
   different questions: one row per service, and one row per member.
   ========================================================================== */

/* Every recorded service, newest first. `expected` is the roll at the time,
   which is what the rate is a percentage of — a register taken when the roll
   was smaller must not look worse than it was.
   `weeks_ago` counts back from the most recent occurrence of that service's
   own weekday, so every date lands on the right day and never goes stale.
   LATER: SELECT r.id, r.service_date, r.service_type_id, r.present, r.absent,
                 r.excused, r.visitors, r.expected, r.offering, u.name
            FROM attendance_registers r JOIN users u ON u.id = r.recorded_by
           WHERE r.church_id = :church_id
             AND (:branch_id IS NULL OR r.branch_id = :branch_id)
             AND r.service_date BETWEEN :from AND :to
        ORDER BY r.service_date DESC, r.start_time DESC; */
$attendance_register_demo = [
    ['id' => 320, 'weeks_ago' =>  0, 'service' => 'prayer',        'present' =>  71, 'absent' =>  18, 'excused' =>  4, 'visitors' =>  2, 'expected' =>  93, 'offering' =>  118.00, 'by' => 'Blessing Moyo', 'preacher' => 'Rev. Enock Sithole',  'theme' => 'Psalm 121 — I lift up my eyes',        'start' => '05:30', 'end' => '07:00', 'weather' => 'Cold'],
    ['id' => 319, 'weeks_ago' =>  0, 'service' => 'midweek',       'present' => 152, 'absent' =>  34, 'excused' =>  7, 'visitors' =>  6, 'expected' => 193, 'offering' =>  264.50, 'by' => 'Simba Dube',    'preacher' => 'Grace Chikomo',       'theme' => 'Acts 2 — The early church',           'start' => '17:30', 'end' => '19:00', 'weather' => 'Clear'],
    ['id' => 318, 'weeks_ago' =>  0, 'service' => 'sunday-second', 'present' => 418, 'absent' =>  57, 'excused' => 11, 'visitors' => 23, 'expected' => 486, 'offering' => 1284.00, 'by' => 'Grace Chikomo', 'preacher' => 'Rev. Enock Sithole',  'theme' => 'Romans 8:28 — All things work together', 'start' => '10:30', 'end' => '12:30', 'weather' => 'Clear'],
    ['id' => 317, 'weeks_ago' =>  0, 'service' => 'sunday-first',  'present' => 331, 'absent' =>  68, 'excused' => 14, 'visitors' => 12, 'expected' => 413, 'offering' =>  902.25, 'by' => 'Simba Dube',    'preacher' => 'Rev. Enock Sithole',  'theme' => 'Romans 8:28 — All things work together', 'start' => '08:00', 'end' => '10:00', 'weather' => 'Clear'],
    ['id' => 316, 'weeks_ago' =>  1, 'service' => 'youth',         'present' => 144, 'absent' =>  21, 'excused' =>  5, 'visitors' =>  9, 'expected' => 170, 'offering' =>  186.00, 'by' => 'Blessing Moyo', 'preacher' => 'Blessing Moyo',       'theme' => 'Daniel 1 — Dare to be different',     'start' => '14:00', 'end' => '16:00', 'weather' => 'Very hot'],
    ['id' => 315, 'weeks_ago' =>  1, 'service' => 'prayer',        'present' =>  58, 'absent' =>  31, 'excused' =>  4, 'visitors' =>  1, 'expected' =>  93, 'offering' =>   74.00, 'by' => 'Blessing Moyo', 'preacher' => 'Farai Nyoni',         'theme' => 'Ephesians 6 — The armour of God',     'start' => '05:30', 'end' => '07:00', 'weather' => 'Heavy rain'],
    ['id' => 314, 'weeks_ago' =>  1, 'service' => 'midweek',       'present' => 168, 'absent' =>  22, 'excused' =>  3, 'visitors' =>  4, 'expected' => 193, 'offering' =>  301.75, 'by' => 'Simba Dube',    'preacher' => 'Grace Chikomo',       'theme' => 'Acts 3 — At the Beautiful Gate',      'start' => '17:30', 'end' => '19:00', 'weather' => 'Cloudy'],
    ['id' => 313, 'weeks_ago' =>  1, 'service' => 'sunday-second', 'present' => 441, 'absent' =>  38, 'excused' =>  7, 'visitors' => 31, 'expected' => 486, 'offering' => 1512.00, 'by' => 'Grace Chikomo', 'preacher' => 'Rev. Enock Sithole',  'theme' => 'Harvest Thanksgiving',                'start' => '10:30', 'end' => '12:45', 'weather' => 'Clear'],
    ['id' => 312, 'weeks_ago' =>  1, 'service' => 'sunday-first',  'present' => 358, 'absent' =>  44, 'excused' => 11, 'visitors' => 18, 'expected' => 413, 'offering' => 1046.50, 'by' => 'Simba Dube',    'preacher' => 'Rev. Enock Sithole',  'theme' => 'Harvest Thanksgiving',                'start' => '08:00', 'end' => '10:15', 'weather' => 'Clear'],
    ['id' => 311, 'weeks_ago' =>  2, 'service' => 'cell',          'present' =>  26, 'absent' =>   6, 'excused' =>  1, 'visitors' =>  3, 'expected' =>  33, 'offering' =>   42.00, 'by' => 'Rudo Chirwa',   'preacher' => 'Rudo Chirwa',         'theme' => 'Fellowship and the breaking of bread','start' => '18:00', 'end' => '19:30', 'weather' => 'Clear'],
    ['id' => 310, 'weeks_ago' =>  2, 'service' => 'midweek',       'present' => 139, 'absent' =>  48, 'excused' =>  6, 'visitors' =>  2, 'expected' => 193, 'offering' =>  221.00, 'by' => 'Simba Dube',    'preacher' => 'Grace Chikomo',       'theme' => 'Acts 4 — Boldness in prayer',         'start' => '17:30', 'end' => '19:00', 'weather' => 'Light rain'],
    ['id' => 309, 'weeks_ago' =>  2, 'service' => 'sunday-second', 'present' => 397, 'absent' =>  74, 'excused' => 15, 'visitors' => 17, 'expected' => 486, 'offering' => 1188.00, 'by' => 'Grace Chikomo', 'preacher' => 'Rev. Enock Sithole',  'theme' => 'James 1 — Count it all joy',          'start' => '10:30', 'end' => '12:30', 'weather' => 'Cloudy'],
    ['id' => 308, 'weeks_ago' =>  2, 'service' => 'sunday-first',  'present' => 302, 'absent' =>  96, 'excused' => 15, 'visitors' =>  9, 'expected' => 413, 'offering' =>  788.00, 'by' => 'Simba Dube',    'preacher' => 'Grace Chikomo',       'theme' => 'James 1 — Count it all joy',          'start' => '08:00', 'end' => '10:00', 'weather' => 'Light rain'],
    ['id' => 307, 'weeks_ago' =>  3, 'service' => 'special',       'present' => 612, 'absent' =>  21, 'excused' =>  4, 'visitors' => 88, 'expected' => 637, 'offering' => 3104.00, 'by' => 'Tendai Marufu', 'preacher' => 'Bishop S. Mutendi',   'theme' => 'Diocesan Convention Sunday',          'start' => '09:00', 'end' => '14:30', 'weather' => 'Clear'],
    ['id' => 306, 'weeks_ago' =>  4, 'service' => 'youth',         'present' => 121, 'absent' =>  44, 'excused' =>  5, 'visitors' =>  4, 'expected' => 170, 'offering' =>  143.50, 'by' => 'Blessing Moyo', 'preacher' => 'Blessing Moyo',       'theme' => 'Proverbs 4 — Guard your heart',       'start' => '14:00', 'end' => '16:00', 'weather' => 'Storm'],
    ['id' => 305, 'weeks_ago' =>  4, 'service' => 'sunday-second', 'present' => 372, 'absent' =>  98, 'excused' => 16, 'visitors' => 14, 'expected' => 486, 'offering' => 1067.00, 'by' => 'Grace Chikomo', 'preacher' => 'Rev. Enock Sithole',  'theme' => 'Isaiah 40 — They shall renew',        'start' => '10:30', 'end' => '12:30', 'weather' => 'Clear'],
    ['id' => 304, 'weeks_ago' =>  5, 'service' => 'sunday-first',  'present' => 289, 'absent' => 104, 'excused' => 20, 'visitors' => 11, 'expected' => 413, 'offering' =>  731.50, 'by' => 'Simba Dube',    'preacher' => 'Farai Nyoni',         'theme' => 'Malachi 3 — Bring the whole tithe',   'start' => '08:00', 'end' => '10:00', 'weather' => 'Heavy rain'],
    ['id' => 303, 'weeks_ago' =>  7, 'service' => 'midweek',       'present' => 147, 'absent' =>  41, 'excused' =>  5, 'visitors' =>  3, 'expected' => 193, 'offering' =>  238.00, 'by' => 'Simba Dube',    'preacher' => 'Grace Chikomo',       'theme' => 'Acts 5 — Nothing can stop it',        'start' => '17:30', 'end' => '19:00', 'weather' => 'Clear'],
    ['id' => 302, 'weeks_ago' =>  9, 'service' => 'cell',          'present' =>  22, 'absent' =>  10, 'excused' =>  1, 'visitors' =>  1, 'expected' =>  33, 'offering' =>   31.00, 'by' => 'Rudo Chirwa',   'preacher' => 'Rudo Chirwa',         'theme' => 'Carrying one another',                'start' => '18:00', 'end' => '19:30', 'weather' => 'Cloudy'],
    ['id' => 301, 'weeks_ago' => 11, 'service' => 'special',       'present' => 508, 'absent' =>  46, 'excused' =>  9, 'visitors' => 62, 'expected' => 563, 'offering' => 2418.00, 'by' => 'Tendai Marufu', 'preacher' => 'Bishop S. Mutendi',   'theme' => 'Ordination Service',                  'start' => '09:00', 'end' => '13:00', 'weather' => 'Clear'],
];

/* One row per member: how they have actually behaved over the period above.
   Keyed by the member id in $members_demo, so the two never drift apart.
   `of` is the number of services that member was expected at — a youth is not
   marked down for missing the women's midweek.
   LATER: a GROUP BY over attendance_marks, or a nightly rollup table.  */
$attendance_by_member_demo = [
    1  => ['attended' => 19, 'of' => 24, 'last_days' =>  3, 'streak' => 6, 'trend' =>  4],
    2  => ['attended' => 23, 'of' => 24, 'last_days' =>  3, 'streak' => 14, 'trend' =>  2],
    3  => ['attended' =>  6, 'of' => 24, 'last_days' => 47, 'streak' => 0, 'trend' => -18],
    4  => ['attended' => 17, 'of' => 22, 'last_days' =>  5, 'streak' => 3, 'trend' => -3],
    5  => ['attended' => 21, 'of' => 24, 'last_days' =>  3, 'streak' => 9, 'trend' =>  6],
    6  => ['attended' => 11, 'of' => 24, 'last_days' => 12, 'streak' => 1, 'trend' => -9],
    7  => ['attended' => 24, 'of' => 24, 'last_days' =>  3, 'streak' => 24, 'trend' =>  1],
    8  => ['attended' =>  3, 'of' => 20, 'last_days' => 68, 'streak' => 0, 'trend' => -22],
    9  => ['attended' => 18, 'of' => 24, 'last_days' =>  7, 'streak' => 5, 'trend' =>  3],
    10 => ['attended' => 15, 'of' => 24, 'last_days' =>  7, 'streak' => 2, 'trend' => -5],
    11 => ['attended' => 22, 'of' => 24, 'last_days' =>  3, 'streak' => 11, 'trend' =>  5],
    12 => ['attended' =>  8, 'of' => 24, 'last_days' => 34, 'streak' => 0, 'trend' => -14],
    13 => ['attended' => 20, 'of' => 24, 'last_days' =>  5, 'streak' => 7, 'trend' =>  2],
    14 => ['attended' => 13, 'of' => 22, 'last_days' =>  9, 'streak' => 1, 'trend' => -7],
    15 => ['attended' => 23, 'of' => 24, 'last_days' =>  3, 'streak' => 16, 'trend' =>  3],
    16 => ['attended' =>  9, 'of' => 24, 'last_days' => 31, 'streak' => 0, 'trend' => -11],
    17 => ['attended' => 16, 'of' => 24, 'last_days' =>  7, 'streak' => 4, 'trend' => -2],
    18 => ['attended' => 21, 'of' => 24, 'last_days' =>  3, 'streak' => 8, 'trend' =>  7],
    19 => ['attended' =>  4, 'of' => 24, 'last_days' => 92, 'streak' => 0, 'trend' => -16],
    20 => ['attended' => 18, 'of' => 22, 'last_days' =>  5, 'streak' => 5, 'trend' =>  1],
];

/* Services the calendar expected but no register was ever taken for. The one
   figure on this page that is a genuine gap rather than a low number.
   LATER: the service schedule LEFT JOINed to registers, WHERE r.id IS NULL. */
$attendance_missing_demo = [
    ['weeks_ago' => 0, 'service' => 'cell',          'note' => 'No register taken'],
    ['weeks_ago' => 0, 'service' => 'youth',         'note' => 'No register taken'],
    ['weeks_ago' => 2, 'service' => 'prayer',        'note' => 'Draft never submitted'],
    ['weeks_ago' => 4, 'service' => 'sunday-first',  'note' => 'No register taken'],
];

/* Members the register itself flags. `reason` is what the page shows; the
   rule that produced it lives in the query, not in the view.
   LATER: WHERE last_attended < NOW() - INTERVAL 30 DAY OR rate_delta < -15. */
$attendance_at_risk_demo = [
    ['member_id' => 19, 'reason' => 'Not seen in 3 months'],
    ['member_id' =>  8, 'reason' => 'Not seen in 2 months'],
    ['member_id' =>  3, 'reason' => 'Attendance down 18 points'],
    ['member_id' => 12, 'reason' => 'Not seen in 30+ days'],
    ['member_id' => 16, 'reason' => 'Not seen in 30+ days'],
];

/* Headline figures for the register's stat strip. Kept apart from the rows
   for the same reason $people_stats is: in production these are aggregates
   over the whole table, not over the page.
   LATER: one SELECT per figure, scoped to :church_id and :branch_id. */
$attendance_stats = [
    'services_month' => 9,
    'average'        => 248,
    'highest'        => 612,
    'highest_days'   => 26,
    'rate'           => 78,
];

/* ==========================================================================
   13d. ATTENDANCE REPORTS
   What attendance/reports.php charts. Every figure here is an aggregate that
   a real deployment computes in SQL; the shapes match what those queries
   would return, so the page's rendering code does not change later.
   ========================================================================== */

/* Twelve months of attendance, split by service type. `markers` names the
   months a special service landed in — the trend chart annotates those.
   LATER: SELECT DATE_FORMAT(service_date,'%b'), service_type_id, AVG(present)
            FROM attendance_registers WHERE ... GROUP BY 1, 2; */
$attendance_trend_demo = [
    'labels' => ['Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug'],
    'series' => [
        'Sunday Services' => [688, 702, 719, 806, 744, 731, 758, 772, 749, 738, 761, 749],
        'Midweek'         => [141, 148, 152, 168, 159, 146, 155, 162, 151, 144, 158, 152],
        'Prayer'          => [ 62,  68,  71,  84,  77,  64,  69,  73,  66,  58,  64,  71],
        'Youth'           => [118, 126, 133, 151, 139, 128, 136, 144, 131, 121, 128, 144],
        'Cell'            => [ 24,  25,  27,  31,  29,  26,  28,  29,  27,  22,  26,  26],
    ],
    'markers' => [3 => 'Carol Service', 7 => 'Easter Convention', 11 => 'Diocesan Convention'],
];

/* Average attendance per service, for the horizontal bar chart.
   LATER: AVG(present) GROUP BY service_type_id. */
$attendance_by_service_demo = [
    'Communion Service'     => 441, 'Sunday Second Service' => 418, 'Sunday First Service' => 331,
    'Midweek Service'       => 152, 'Sunday School'         => 142, 'Youth Service'        => 133,
    'Overnight Prayer'      => 118, "Women's Fellowship"    => 108, "Men's Fellowship"     =>  74,
    'Baptism Service'       =>  68, 'Friday Prayer Meeting' =>  64, 'Cell Meetings'        =>  26,
];

/* Which days actually fill the building.
   LATER: AVG(present) GROUP BY DAYOFWEEK(service_date). */
$attendance_by_dow_demo = [
    'Monday' => 0, 'Tuesday' => 26, 'Wednesday' => 152, 'Thursday' => 108,
    'Friday' => 91, 'Saturday' => 104, 'Sunday' => 297,
];

/* Who is in the room. LATER: GROUP BY members.age_band, members.gender. */
$attendance_demographics_demo = ['Men' => 289, 'Women' => 412, 'Youth' => 198, 'Children' => 164];

/* How the roll spreads across the rate bands the register already defines.
   LATER: COUNT(*) GROUP BY the CASE that produces the band. */
$attendance_rate_bands_demo = [
    '0-20%' => 74, '21-40%' => 118, '41-60%' => 246, '61-80%' => 418, '81-100%' => 428,
];

/* The visitor funnel. LATER: three COUNTs over visitors joined to members. */
$attendance_funnel_demo = [
    ['label' => 'Visited',  'value' => 218, 'note' => 'first-time visitors'],
    ['label' => 'Returned', 'value' =>  96, 'note' => 'came back at least once'],
    ['label' => 'Joined',   'value' =>  41, 'note' => 'became members'],
];

/* This year against last, same twelve months.
   LATER: two aggregates over different YEAR() windows. */
$attendance_yoy_demo = [
    'labels'    => ['Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug'],
    'this_year' => [688, 702, 719, 806, 744, 731, 758, 772, 749, 738, 761, 749],
    'last_year' => [641, 656, 663, 771, 698, 674, 702, 716, 688, 671, 694, 702],
];

/* Is attendance keeping pace with the roll? Two axes deliberately — the
   scales are an order of magnitude apart and sharing one hides the story.
   LATER: monthly COUNT(members) beside monthly AVG(present). */
$attendance_vs_membership_demo = [
    'labels'     => ['Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug'],
    'attendance' => [688, 702, 719, 806, 744, 731, 758, 772, 749, 738, 761, 749],
    'membership' => [1188, 1201, 1214, 1228, 1241, 1249, 1258, 1264, 1271, 1276, 1280, 1284],
];

/* Months down, weeks across. The June dip and the December peak are the two
   patterns this grid exists to make visible. A zero is a week with no service.
   LATER: AVG(present) GROUP BY MONTH(), WEEK(). */
$attendance_seasonal_demo = [
    'Sep' => [672, 688, 701, 691], 'Oct' => [694, 702, 716, 698],
    'Nov' => [708, 719, 731, 718], 'Dec' => [744, 806, 892, 703],
    'Jan' => [681, 744, 758, 751], 'Feb' => [726, 731, 742, 728],
    'Mar' => [748, 758, 766, 759], 'Apr' => [761, 772, 818, 764],
    'May' => [744, 749, 756, 748], 'Jun' => [721, 738, 742, 733],
    'Jul' => [752, 761, 768, 758], 'Aug' => [741, 749, 756, 0],
];

/* Of the members who joined in a given month, what share still attend at
   three, six and twelve months. Null where the cohort is too young to say.
   LATER: a self-join between members.joined_at and attendance_marks. */
$attendance_cohorts_demo = [
    ['month' => 'Sep', 'joined' => 34, 'm3' => 88,   'm6' => 79,   'm12' => 71],
    ['month' => 'Oct', 'joined' => 28, 'm3' => 86,   'm6' => 75,   'm12' => 68],
    ['month' => 'Nov', 'joined' => 41, 'm3' => 90,   'm6' => 81,   'm12' => 74],
    ['month' => 'Dec', 'joined' => 62, 'm3' => 74,   'm6' => 61,   'm12' => 52],
    ['month' => 'Jan', 'joined' => 47, 'm3' => 91,   'm6' => 83,   'm12' => null],
    ['month' => 'Feb', 'joined' => 31, 'm3' => 87,   'm6' => 78,   'm12' => null],
    ['month' => 'Mar', 'joined' => 26, 'm3' => 89,   'm6' => 80,   'm12' => null],
    ['month' => 'Apr', 'joined' => 38, 'm3' => 85,   'm6' => 76,   'm12' => null],
    ['month' => 'May', 'joined' => 22, 'm3' => 92,   'm6' => null, 'm12' => null],
    ['month' => 'Jun', 'joined' => 19, 'm3' => 84,   'm6' => null, 'm12' => null],
    ['month' => 'Jul', 'joined' => 24, 'm3' => null, 'm6' => null, 'm12' => null],
    ['month' => 'Aug', 'joined' => 12, 'm3' => null, 'm6' => null, 'm12' => null],
];

/* Six months forward with the band the estimate sits inside. It is a
   straight-line projection, not a forecast, and the page says so wherever it
   appears. */
$attendance_projection_demo = [
    'labels' => ['Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb'],
    'mid'    => [764, 771, 779, 848, 786, 794],
    'low'    => [712, 714, 718, 776, 715, 719],
    'high'   => [816, 828, 840, 920, 857, 869],
];

/* The comparison tables and the leaderboard, one block per grouping.
   `months` is that group's twelve-month series for the grouped bar chart.
   LATER: the same aggregate, GROUP BY whichever dimension is selected. */
$attendance_groups_demo = [
    'Department' => [
        ['name' => 'Ushering',            'members' => 46, 'avg' => 41, 'rate' => 89, 'trend' =>  4, 'best' => 'Dec', 'worst' => 'Jun', 'months' => [36, 38, 40, 44, 41, 39, 42, 43, 40, 37, 41, 41]],
        ['name' => 'Choir',               'members' => 62, 'avg' => 53, 'rate' => 85, 'trend' =>  2, 'best' => 'Dec', 'worst' => 'Jun', 'months' => [47, 49, 51, 58, 54, 50, 53, 55, 52, 46, 51, 53]],
        ['name' => 'Praise & Worship',    'members' => 38, 'avg' => 33, 'rate' => 87, 'trend' =>  6, 'best' => 'Apr', 'worst' => 'Jun', 'months' => [28, 30, 31, 35, 33, 31, 34, 36, 33, 29, 32, 33]],
        ['name' => 'Youth Ministry',      'members' => 84, 'avg' => 58, 'rate' => 69, 'trend' => -3, 'best' => 'Dec', 'worst' => 'Jul', 'months' => [56, 59, 62, 71, 64, 58, 61, 63, 57, 52, 55, 58]],
        ['name' => "Women's Fellowship",  'members' => 96, 'avg' => 71, 'rate' => 74, 'trend' =>  1, 'best' => 'Mar', 'worst' => 'Jun', 'months' => [64, 68, 70, 78, 73, 69, 74, 75, 71, 63, 69, 71]],
        ['name' => "Men's Fellowship",    'members' => 71, 'avg' => 42, 'rate' => 59, 'trend' => -6, 'best' => 'Dec', 'worst' => 'Jul', 'months' => [46, 47, 48, 54, 49, 44, 45, 44, 41, 38, 40, 42]],
        ['name' => "Children's Ministry", 'members' => 58, 'avg' => 49, 'rate' => 84, 'trend' =>  3, 'best' => 'Dec', 'worst' => 'Jun', 'months' => [43, 45, 47, 53, 50, 47, 49, 51, 48, 43, 47, 49]],
        ['name' => 'Media & Sound',       'members' => 24, 'avg' => 21, 'rate' => 88, 'trend' =>  0, 'best' => 'Apr', 'worst' => 'Jun', 'months' => [19, 20, 21, 23, 22, 20, 21, 22, 21, 18, 20, 21]],
        ['name' => 'Intercession',        'members' => 33, 'avg' => 19, 'rate' => 58, 'trend' => -8, 'best' => 'Nov', 'worst' => 'Aug', 'months' => [23, 24, 25, 26, 23, 21, 22, 21, 20, 18, 19, 19]],
        ['name' => 'Protocol',            'members' => 29, 'avg' => 24, 'rate' => 83, 'trend' =>  2, 'best' => 'Dec', 'worst' => 'Jun', 'months' => [21, 22, 23, 26, 24, 23, 24, 25, 23, 21, 23, 24]],
    ],
    'Cell Group' => [
        ['name' => 'Westgate Cell',    'members' => 34, 'avg' => 28, 'rate' => 82, 'trend' =>  5, 'best' => 'Apr', 'worst' => 'Jun', 'months' => [24, 25, 26, 30, 28, 26, 28, 30, 27, 24, 27, 28]],
        ['name' => 'Borrowdale Cell',  'members' => 29, 'avg' => 24, 'rate' => 83, 'trend' =>  3, 'best' => 'Dec', 'worst' => 'Jul', 'months' => [21, 22, 23, 27, 25, 23, 24, 25, 23, 21, 22, 24]],
        ['name' => 'Chitungwiza Cell', 'members' => 41, 'avg' => 27, 'rate' => 66, 'trend' => -4, 'best' => 'Dec', 'worst' => 'Aug', 'months' => [29, 30, 31, 34, 31, 28, 29, 29, 27, 25, 26, 27]],
        ['name' => 'Avondale Cell',    'members' => 26, 'avg' => 22, 'rate' => 85, 'trend' =>  2, 'best' => 'Mar', 'worst' => 'Jun', 'months' => [19, 20, 21, 24, 22, 21, 23, 23, 22, 19, 21, 22]],
        ['name' => 'Highfield Cell',   'members' => 38, 'avg' => 21, 'rate' => 55, 'trend' => -9, 'best' => 'Nov', 'worst' => 'Aug', 'months' => [26, 27, 28, 29, 26, 24, 24, 23, 22, 20, 21, 21]],
        ['name' => 'Waterfalls Cell',  'members' => 31, 'avg' => 25, 'rate' => 81, 'trend' =>  1, 'best' => 'Dec', 'worst' => 'Jun', 'months' => [22, 23, 24, 28, 26, 24, 25, 26, 24, 22, 24, 25]],
        ['name' => 'Glen View Cell',   'members' => 27, 'avg' => 18, 'rate' => 67, 'trend' => -2, 'best' => 'Dec', 'worst' => 'Jul', 'months' => [19, 20, 20, 23, 21, 19, 20, 20, 18, 17, 18, 18]],
        ['name' => 'Mabelreign Cell',  'members' => 23, 'avg' => 20, 'rate' => 87, 'trend' =>  4, 'best' => 'Apr', 'worst' => 'Jun', 'months' => [17, 18, 19, 21, 20, 19, 20, 21, 20, 17, 19, 20]],
    ],
    'Age Group' => [
        ['name' => 'Children (0-12)', 'members' => 268, 'avg' => 164, 'rate' => 61, 'trend' => -2, 'best' => 'Dec', 'worst' => 'Jun', 'months' => [151, 156, 161, 189, 168, 158, 163, 167, 160, 148, 158, 164]],
        ['name' => 'Youth (13-24)',   'members' => 312, 'avg' => 198, 'rate' => 63, 'trend' =>  4, 'best' => 'Apr', 'worst' => 'Jul', 'months' => [176, 182, 189, 214, 196, 186, 193, 201, 191, 178, 188, 198]],
        ['name' => 'Adults (25-59)',  'members' => 594, 'avg' => 448, 'rate' => 75, 'trend' =>  2, 'best' => 'Dec', 'worst' => 'Jun', 'months' => [411, 421, 432, 478, 442, 428, 441, 452, 439, 418, 437, 448]],
        ['name' => 'Seniors (60+)',   'members' => 110, 'avg' =>  91, 'rate' => 83, 'trend' =>  1, 'best' => 'Mar', 'worst' => 'Jun', 'months' => [ 84,  86,  88,  96,  91,  88,  92,  93,  90,  83,  89,  91]],
    ],
    'Gender' => [
        ['name' => 'Women', 'members' => 704, 'avg' => 412, 'rate' => 59, 'trend' =>  3, 'best' => 'Dec', 'worst' => 'Jun', 'months' => [378, 387, 396, 441, 408, 394, 404, 414, 401, 382, 399, 412]],
        ['name' => 'Men',   'members' => 580, 'avg' => 289, 'rate' => 50, 'trend' => -1, 'best' => 'Dec', 'worst' => 'Jul', 'months' => [271, 277, 283, 318, 292, 281, 288, 293, 284, 268, 279, 289]],
    ],
];

/* Longest unbroken run of services attended, per member id. The register
   carries the current streak; this is the record.
   LATER: the window function that produces it. */
$attendance_longest_streak_demo = [
    1 => 11, 2 => 19, 3 => 7, 4 => 9, 5 => 14, 6 => 6, 7 => 24, 8 => 5, 9 => 12, 10 => 8,
    11 => 16, 12 => 7, 13 => 13, 14 => 6, 15 => 21, 16 => 5, 17 => 10, 18 => 15, 19 => 4, 20 => 11,
];

/* The headline figures with the previous period beside them, so the strip can
   show a direction rather than a bare number.
   LATER: the same aggregate run twice, over two windows. */
$attendance_report_stats = [
    'average'  => ['now' => 749,  'prev' => 702],
    'rate'     => ['now' => 78,   'prev' => 74],
    'services' => ['now' => 96,   'prev' => 92],
    'growth'   => ['now' => 6.7,  'prev' => 4.1],
];

/* ==========================================================================
   13e. FINANCE — CURRENCIES, CONTRIBUTION TYPES, PAYMENT METHODS
   What finance/record.php captures against. Zimbabwe runs multi-currency in
   practice, so every amount carries its own currency and the USD equivalent
   is shown beside it rather than the figure being silently converted.
   ========================================================================== */

/* Exactly one currency has is_default = true; it is the one every total is
   expressed in. `exchange_rate_to_usd` multiplies an amount to reach USD.
   LATER: SELECT code, symbol, name, is_default, rate_to_usd FROM currencies
           WHERE church_id = :church_id AND active = 1 ORDER BY is_default DESC; */
$currencies = [
    ['code' => 'USD', 'symbol' => '$',   'name' => 'US Dollar',        'is_default' => true,  'exchange_rate_to_usd' => 1.0],
    ['code' => 'ZWG', 'symbol' => 'ZWG', 'name' => 'Zimbabwe Gold',    'is_default' => false, 'exchange_rate_to_usd' => 0.0372],
    ['code' => 'ZAR', 'symbol' => 'R',   'name' => 'South African Rand','is_default' => false, 'exchange_rate_to_usd' => 0.0545],
    ['code' => 'GBP', 'symbol' => '£',   'name' => 'Pound Sterling',   'is_default' => false, 'exchange_rate_to_usd' => 1.27],
];

/* `requires_member` is the rule the form enforces: a tithe or a pledge payment
   has to be attributable to somebody, a loose offering does not.
   LATER: SELECT * FROM contribution_types WHERE church_id = :church_id
           AND active = 1 ORDER BY sort_order; */
$contribution_types = [
    ['key' => 'tithe',       'name' => 'Tithe',            'icon' => 'fa-hand-holding-dollar', 'colour' => '#662F97', 'requires_member' => true],
    ['key' => 'offering',    'name' => 'Offering',         'icon' => 'fa-basket-shopping',     'colour' => '#8F5CC2', 'requires_member' => false],
    ['key' => 'thanksgiving','name' => 'Thanksgiving',     'icon' => 'fa-hands-praying',       'colour' => '#B45309', 'requires_member' => false],
    ['key' => 'building',    'name' => 'Building Fund',    'icon' => 'fa-trowel-bricks',       'colour' => '#0369A1', 'requires_member' => false],
    ['key' => 'seed',        'name' => 'Seed',             'icon' => 'fa-seedling',            'colour' => '#15803D', 'requires_member' => false],
    ['key' => 'pledge',      'name' => 'Pledge Payment',   'icon' => 'fa-file-signature',      'colour' => '#6D28D9', 'requires_member' => true],
    ['key' => 'missions',    'name' => 'Missions',         'icon' => 'fa-earth-africa',        'colour' => '#0F766E', 'requires_member' => false],
    ['key' => 'welfare',     'name' => 'Welfare',          'icon' => 'fa-heart',               'colour' => '#BE185D', 'requires_member' => false],
    ['key' => 'special',     'name' => 'Special Offering', 'icon' => 'fa-star',                'colour' => '#CA8A04', 'requires_member' => false],
    ['key' => 'firstfruits', 'name' => 'First Fruits',     'icon' => 'fa-wheat-awn',           'colour' => '#B4243F', 'requires_member' => true],
];

/* `needs_reference` drives the conditional reference field: a mobile-money or
   bank payment has a transaction id worth capturing, cash does not.
   LATER: SELECT * FROM payment_methods WHERE church_id = :church_id; */
$payment_methods = [
    ['key' => 'cash',     'name' => 'Cash',          'icon' => 'fa-money-bill-wave',   'needs_reference' => false],
    ['key' => 'ecocash',  'name' => 'EcoCash',       'icon' => 'fa-mobile-screen',     'needs_reference' => true,  'ref_label' => 'EcoCash transaction reference'],
    ['key' => 'zipit',    'name' => 'ZIPIT',         'icon' => 'fa-right-left',        'needs_reference' => true,  'ref_label' => 'ZIPIT reference'],
    ['key' => 'bank',     'name' => 'Bank Transfer', 'icon' => 'fa-building-columns',  'needs_reference' => true,  'ref_label' => 'Bank reference'],
    ['key' => 'swipe',    'name' => 'Swipe / POS',   'icon' => 'fa-credit-card',       'needs_reference' => false],
    ['key' => 'cheque',   'name' => 'Cheque',        'icon' => 'fa-money-check',       'needs_reference' => true,  'ref_label' => 'Cheque number'],
    ['key' => 'inkind',   'name' => 'In-Kind',       'icon' => 'fa-box-open',          'needs_reference' => false],
];

/* The notes and coins in circulation, for the cash-counting helper on the
   Quick Totals tab. Value is in the currency's own units. */
$cash_denominations = [
    ['currency' => 'USD', 'value' => 100, 'label' => '$100'], ['currency' => 'USD', 'value' => 50, 'label' => '$50'],
    ['currency' => 'USD', 'value' => 20,  'label' => '$20'],  ['currency' => 'USD', 'value' => 10, 'label' => '$10'],
    ['currency' => 'USD', 'value' => 5,   'label' => '$5'],   ['currency' => 'USD', 'value' => 2,  'label' => '$2'],
    ['currency' => 'USD', 'value' => 1,   'label' => '$1'],
];

/* Projects a contribution can be designated to. Shown only when the projects
   module is on.
   LATER: SELECT id, name, target, raised FROM projects
           WHERE church_id = :church_id AND status = 'active'; */
/* The projects a contribution can be tagged against. Defined in full in
   section 13g, which this page's dropdowns and the pledges page share. */

/* Contributions already captured today. The form checks a new entry against
   these to warn about a possible duplicate — same member, same amount, same
   date. It warns; it never blocks, because a member really can give twice.
   LATER: SELECT member_id, amount, currency, received_on FROM contributions
           WHERE church_id = :church_id AND received_on = :date; */
$contributions_today_demo = [
    ['member_id' => 2,  'member' => 'Denford Masuku', 'amount' => 50.00,  'currency' => 'USD', 'type' => 'tithe',    'ref' => 'MCP-C-4471'],
    ['member_id' => 7,  'member' => 'Loveness Moyo',  'amount' => 120.00, 'currency' => 'USD', 'type' => 'tithe',    'ref' => 'MCP-C-4472'],
    ['member_id' => 15, 'member' => 'Melody Sibanda', 'amount' => 850.00, 'currency' => 'ZWG', 'type' => 'offering', 'ref' => 'MCP-C-4473'],
];

/* ==========================================================================
   13f. FINANCE — THE CONTRIBUTION LEDGER
   What finance/contributions.php reads. Shapes match exactly what
   finance/record.php captures, so a row saved there would slot straight in.
   `days_ago` keeps the demo from going stale; `member_id` is null for an
   anonymous gift.
   LATER: SELECT c.*, m.name, m.member_no, u.name AS recorded_by
            FROM contributions c
            LEFT JOIN members m ON m.id = c.member_id
            JOIN users u ON u.id = c.recorded_by_id
           WHERE c.church_id = :church_id
             AND (:branch_id IS NULL OR c.branch_id = :branch_id)
             AND c.received_on BETWEEN :from AND :to
        ORDER BY c.received_on DESC, c.id DESC;
   ========================================================================== */
$contributions_demo = [
    ['id' => 4491, 'ref' => 'MCP-C-4491', 'days_ago' =>   1, 'time' => '10:42', 'member_id' =>  2, 'type' => 'tithe',       'amount' =>  120.00, 'currency' => 'USD', 'method' => 'ecocash', 'txn' => 'MP260827.1042.A88213', 'service' => 'Sunday Second Service', 'project' => null, 'by' => 'Farai Nyoni',   'notes' => ''],
    ['id' => 4490, 'ref' => 'MCP-C-4490', 'days_ago' =>   1, 'time' => '10:38', 'member_id' =>  7, 'type' => 'tithe',       'amount' =>  250.00, 'currency' => 'USD', 'method' => 'bank',    'txn' => 'CBZ-9920-4471',        'service' => 'Sunday Second Service', 'project' => null, 'by' => 'Farai Nyoni',   'notes' => ''],
    ['id' => 4489, 'ref' => 'MCP-C-4489', 'days_ago' =>   1, 'time' => '10:31', 'member_id' => null,'type' => 'offering',   'amount' => 1840.00, 'currency' => 'ZWG', 'method' => 'cash',    'txn' => '',                     'service' => 'Sunday Second Service', 'project' => null, 'by' => 'Farai Nyoni',   'notes' => 'Loose plate collection'],
    ['id' => 4488, 'ref' => 'MCP-C-4488', 'days_ago' =>   1, 'time' => '09:14', 'member_id' => 15, 'type' => 'building',    'amount' =>  500.00, 'currency' => 'USD', 'method' => 'swipe',   'txn' => '',                     'service' => 'Sunday First Service',  'project' => 1,    'by' => 'Tendai Marufu', 'notes' => 'Towards the roof'],
    ['id' => 4487, 'ref' => 'MCP-C-4487', 'days_ago' =>   1, 'time' => '09:02', 'member_id' => 11, 'type' => 'thanksgiving','amount' =>   75.00, 'currency' => 'USD', 'method' => 'cash',    'txn' => '',                     'service' => 'Sunday First Service',  'project' => null, 'by' => 'Tendai Marufu', 'notes' => ''],
    ['id' => 4486, 'ref' => 'MCP-C-4486', 'days_ago' =>   1, 'time' => '08:55', 'member_id' => null,'type' => 'offering',   'amount' =>  310.00, 'currency' => 'USD', 'method' => 'cash',    'txn' => '',                     'service' => 'Sunday First Service',  'project' => null, 'by' => 'Tendai Marufu', 'notes' => ''],
    ['id' => 4485, 'ref' => 'MCP-C-4485', 'days_ago' =>   4, 'time' => '18:20', 'member_id' =>  5, 'type' => 'pledge',      'amount' =>  400.00, 'currency' => 'USD', 'method' => 'bank',    'txn' => 'STANBIC-7741-2290',    'service' => 'Midweek Service',       'project' => 4,    'by' => 'Farai Nyoni',   'notes' => 'Second instalment'],
    ['id' => 4484, 'ref' => 'MCP-C-4484', 'days_ago' =>   4, 'time' => '18:05', 'member_id' => 18, 'type' => 'seed',        'amount' =>   60.00, 'currency' => 'USD', 'method' => 'ecocash', 'txn' => 'MP260824.1805.B31907', 'service' => 'Midweek Service',       'project' => null, 'by' => 'Farai Nyoni',   'notes' => ''],
    ['id' => 4483, 'ref' => 'MCP-C-4483', 'days_ago' =>   6, 'time' => '06:40', 'member_id' => 13, 'type' => 'tithe',       'amount' =>  180.00, 'currency' => 'USD', 'method' => 'zipit',   'txn' => 'ZIP-2260822-8841',     'service' => 'Friday Prayer Meeting', 'project' => null, 'by' => 'Farai Nyoni',   'notes' => ''],
    ['id' => 4482, 'ref' => 'MCP-C-4482', 'days_ago' =>   8, 'time' => '11:10', 'member_id' =>  1, 'type' => 'tithe',       'amount' =>   95.00, 'currency' => 'USD', 'method' => 'cash',    'txn' => '',                     'service' => 'Sunday Second Service', 'project' => null, 'by' => 'Tendai Marufu', 'notes' => ''],
    ['id' => 4481, 'ref' => 'MCP-C-4481', 'days_ago' =>   8, 'time' => '11:02', 'member_id' => 20, 'type' => 'missions',    'amount' =>  140.00, 'currency' => 'ZAR', 'method' => 'cash',    'txn' => '',                     'service' => 'Sunday Second Service', 'project' => null, 'by' => 'Tendai Marufu', 'notes' => 'Visitor from Polokwane'],
    ['id' => 4480, 'ref' => 'MCP-C-4480', 'days_ago' =>   8, 'time' => '10:48', 'member_id' => null,'type' => 'offering',   'amount' => 2210.00, 'currency' => 'ZWG', 'method' => 'cash',    'txn' => '',                     'service' => 'Sunday Second Service', 'project' => null, 'by' => 'Tendai Marufu', 'notes' => ''],
    ['id' => 4479, 'ref' => 'MCP-C-4479', 'days_ago' =>  11, 'time' => '14:25', 'member_id' =>  9, 'type' => 'welfare',     'amount' =>   45.00, 'currency' => 'USD', 'method' => 'ecocash', 'txn' => 'MP260817.1425.C77420', 'service' => "Women's Fellowship",    'project' => null, 'by' => 'Grace Chikomo', 'notes' => 'For the Mhembere family'],
    ['id' => 4478, 'ref' => 'MCP-C-4478', 'days_ago' =>  13, 'time' => '15:30', 'member_id' =>  4, 'type' => 'firstfruits', 'amount' =>  320.00, 'currency' => 'USD', 'method' => 'bank',    'txn' => 'CBZ-9920-4102',        'service' => 'Youth Service',         'project' => null, 'by' => 'Farai Nyoni',   'notes' => ''],
    ['id' => 4477, 'ref' => 'MCP-C-4477', 'days_ago' =>  15, 'time' => '10:55', 'member_id' => 16, 'type' => 'building',    'amount' =>  250.00, 'currency' => 'GBP', 'method' => 'bank',    'txn' => 'BARC-INT-33914',       'service' => 'Sunday Second Service', 'project' => 1,    'by' => 'Farai Nyoni',   'notes' => 'From the diaspora fund'],
    ['id' => 4476, 'ref' => 'MCP-C-4476', 'days_ago' =>  15, 'time' => '10:40', 'member_id' =>  3, 'type' => 'tithe',       'amount' =>   80.00, 'currency' => 'USD', 'method' => 'cash',    'txn' => '',                     'service' => 'Sunday Second Service', 'project' => null, 'by' => 'Tendai Marufu', 'notes' => ''],
    ['id' => 4475, 'ref' => 'MCP-C-4475', 'days_ago' =>  18, 'time' => '19:15', 'member_id' => 12, 'type' => 'special',     'amount' =>  650.00, 'currency' => 'ZWG', 'method' => 'inkind',  'txn' => '',                     'service' => 'Cell Meetings',         'project' => null, 'by' => 'Rudo Chirwa',   'notes' => '3 bags of maize meal, valued'],
    ['id' => 4474, 'ref' => 'MCP-C-4474', 'days_ago' =>  22, 'time' => '10:20', 'member_id' =>  6, 'type' => 'offering',    'amount' =>   35.00, 'currency' => 'USD', 'method' => 'cash',    'txn' => '',                     'service' => 'Sunday First Service',  'project' => null, 'by' => 'Tendai Marufu', 'notes' => ''],
    ['id' => 4473, 'ref' => 'MCP-C-4473', 'days_ago' =>  22, 'time' => '10:05', 'member_id' => 10, 'type' => 'tithe',       'amount' =>  210.00, 'currency' => 'USD', 'method' => 'swipe',   'txn' => '',                     'service' => 'Sunday First Service',  'project' => null, 'by' => 'Tendai Marufu', 'notes' => ''],
    ['id' => 4472, 'ref' => 'MCP-C-4472', 'days_ago' =>  29, 'time' => '11:35', 'member_id' => null,'type' => 'offering',   'amount' =>  425.00, 'currency' => 'USD', 'method' => 'cash',    'txn' => '',                     'service' => 'Communion Service',     'project' => null, 'by' => 'Farai Nyoni',   'notes' => 'Communion Sunday'],
    ['id' => 4471, 'ref' => 'MCP-C-4471', 'days_ago' =>  36, 'time' => '09:50', 'member_id' => 17, 'type' => 'thanksgiving','amount' => 1500.00, 'currency' => 'ZWG', 'method' => 'cheque',  'txn' => 'CHQ-004182',           'service' => 'Sunday First Service',  'project' => null, 'by' => 'Farai Nyoni',   'notes' => ''],
    ['id' => 4470, 'ref' => 'MCP-C-4470', 'days_ago' =>  43, 'time' => '10:15', 'member_id' =>  8, 'type' => 'pledge',      'amount' =>  200.00, 'currency' => 'USD', 'method' => 'zipit',   'txn' => 'ZIP-2260716-2204',     'service' => 'Sunday Second Service', 'project' => 2,    'by' => 'Farai Nyoni',   'notes' => 'Borehole pledge'],
    ['id' => 4469, 'ref' => 'MCP-C-4469', 'days_ago' =>  51, 'time' => '14:00', 'member_id' => 19, 'type' => 'missions',    'amount' =>   90.00, 'currency' => 'USD', 'method' => 'ecocash', 'txn' => 'MP260708.1400.D11845', 'service' => 'Youth Service',         'project' => null, 'by' => 'Blessing Moyo', 'notes' => ''],
    ['id' => 4468, 'ref' => 'MCP-C-4468', 'days_ago' =>  58, 'time' => '09:30', 'member_id' => null,'type' => 'offering',   'amount' =>  380.00, 'currency' => 'USD', 'method' => 'cash',    'txn' => '',                     'service' => 'Special Service',       'project' => null, 'by' => 'Tendai Marufu', 'notes' => 'Convention Sunday'],
    ['id' => 4467, 'ref' => 'MCP-C-4467', 'days_ago' =>  72, 'time' => '10:45', 'member_id' => 14, 'type' => 'seed',        'amount' =>  110.00, 'currency' => 'USD', 'method' => 'cash',    'txn' => '',                     'service' => 'Sunday Second Service', 'project' => null, 'by' => 'Tendai Marufu', 'notes' => ''],
];

/* Twelve months of total receipts, in USD, for the giving-trend chart.
   LATER: SELECT DATE_FORMAT(received_on,'%b'), SUM(amount * rate_to_usd)
            FROM contributions WHERE ... GROUP BY 1; */
$giving_trend_demo = [
    'labels' => ['Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug'],
    'totals' => [7420, 7860, 8140, 11280, 8940, 8310, 8720, 9410, 8880, 8260, 8990, 9340],
];

/* Six months per contribution type, for the sparkline on the By Type view.
   Keyed by the same type keys record.php captures against.
   LATER: the same aggregate, GROUP BY contribution_type_id, MONTH(). */
$giving_by_type_spark_demo = [
    'tithe'       => [3820, 3960, 4110, 3880, 4040, 4210],
    'offering'    => [1940, 2080, 2010, 1870, 2140, 2260],
    'thanksgiving'=> [ 620,  710,  680,  590,  740,  810],
    'building'    => [ 880,  940, 1120,  980, 1060, 1240],
    'seed'        => [ 310,  280,  340,  300,  360,  390],
    'pledge'      => [ 540,  600,  580,  620,  660,  700],
    'missions'    => [ 220,  260,  240,  280,  300,  320],
    'welfare'     => [ 140,  160,  180,  150,  170,  190],
    'special'     => [ 380,  420,  460,  510,  440,  480],
    'firstfruits' => [ 260,  300,  280,  320,  340,  360],
];

/* How many of the last twelve months each member gave in, for the consistency
   bar on the By Member view. Keyed by member id.
   LATER: COUNT(DISTINCT MONTH(received_on)) over the last 12 months. */
$giving_consistency_demo = [
    1 => 9, 2 => 12, 3 => 4, 4 => 8, 5 => 11, 6 => 5, 7 => 12, 8 => 3, 9 => 7, 10 => 6,
    11 => 10, 12 => 4, 13 => 11, 14 => 5, 15 => 12, 16 => 6, 17 => 8, 18 => 9, 19 => 2, 20 => 7,
];

/* Headline figures with the previous period beside them, so the strip shows a
   direction rather than a bare number. All in USD.
   LATER: the same aggregate run twice, over two windows. */
$contribution_stats = [
    'month'      => ['now' => 9340,  'prev' => 8990],
    /* Year to date, matching the series finance/reports.php reports on. */
    'year'       => ['now' => 77731, 'prev' => 71400],
    'per_service'=> ['now' => 486,   'prev' => 452],
    'givers'     => ['now' => 318,   'prev' => 296],
];

/* ==========================================================================
   13g. PLEDGES & PROJECTS  (finance/pledges.php)
   Two linked concepts. A PROJECT is the goal the church is raising toward;
   a PLEDGE is one member's promise toward that goal. Money already received
   is `raised`; money promised but not yet paid is the gap between `pledged`
   and `raised`, and the page shows that gap as its own state.

   Everything the page can derive it derives — status, schedules, percentages,
   days remaining — so the demo stays honest when the dates move.
   LATER: SELECT * FROM projects WHERE church_id = :church_id;
   ========================================================================== */

/* The richer project record. This supersedes the four-field stand-in the
   contribution pages were using; ids 1–4 are unchanged so contributions that
   already point at a project still resolve. */
$projects_demo = [
    [
        'id' => 1, 'name' => 'Church Building Fund',
        'category' => 'Construction', 'icon' => 'fa-church', 'colour' => '#662F97',
        'status' => 'active', 'currency' => 'USD',
        'target' => 120000, 'raised' => 74500, 'pledged' => 96200,
        'start_date' => '2025-02-01', 'target_date' => '2027-06-30',
        'contributors' => [1, 3, 5, 7, 9, 11, 13, 15, 17, 2, 4, 6],
        'allow_pledges' => true, 'public_progress' => true,
        'description' => 'The new 1,200-seat sanctuary on the Chitungwiza stand. Phase one is the foundation and structural shell; phase two is roofing and glazing.',
        'updates' => [
            ['date' => '2026-08-02', 'title' => 'Structural shell topped out',  'body' => 'The last of the ring beam was poured on Saturday. The contractor moves to roof trusses in September.', 'photos' => 3],
            ['date' => '2026-05-18', 'title' => 'Foundation signed off',        'body' => 'The council engineer inspected and passed the raft foundation. Certificate filed with the church office.',   'photos' => 2],
            ['date' => '2026-01-24', 'title' => 'Ground broken',                'body' => 'Bishop Mutendi turned the first sod before a congregation of about four hundred.',                            'photos' => 5],
        ],
    ],
    [
        'id' => 2, 'name' => 'Church Bus',
        'category' => 'Transport', 'icon' => 'fa-bus', 'colour' => '#0F766E',
        'status' => 'active', 'currency' => 'USD',
        'target' => 45000, 'raised' => 28900, 'pledged' => 36400,
        'start_date' => '2025-06-15', 'target_date' => '2026-11-30',
        'contributors' => [2, 4, 8, 10, 12, 14, 16, 18, 20],
        'allow_pledges' => true, 'public_progress' => true,
        'description' => 'A 32-seater to carry the youth and the women\'s fellowship to district gatherings, and to run the Sunday pickup route through Epworth and Ruwa.',
        'updates' => [
            ['date' => '2026-07-11', 'title' => 'Two quotations received', 'body' => 'A 2019 Toyota Coaster and a 2020 Higer are both within reach. The board reviews them this month.', 'photos' => 2],
            ['date' => '2026-02-09', 'title' => 'Fund opened',             'body' => 'Announced at the annual general meeting. Pledges opened the same Sunday.',                          'photos' => 0],
        ],
    ],
    [
        'id' => 3, 'name' => 'Sound System Upgrade',
        'category' => 'Equipment', 'icon' => 'fa-volume-high', 'colour' => '#B45309',
        'status' => 'active', 'currency' => 'USD',
        'target' => 12000, 'raised' => 11400, 'pledged' => 12000,
        'start_date' => '2025-09-01', 'target_date' => '2026-09-15',
        'contributors' => [1, 6, 9, 13, 19, 20],
        'allow_pledges' => true, 'public_progress' => true,
        'description' => 'A digital desk, twelve channels of radio microphone, and line array replacements for the main hall.',
        'updates' => [
            ['date' => '2026-08-20', 'title' => 'Desk ordered',       'body' => 'The mixing desk is paid for and in transit from Johannesburg.',                'photos' => 1],
            ['date' => '2026-06-01', 'title' => 'Specification fixed', 'body' => 'The technical team settled on the final specification after two trial Sundays.', 'photos' => 0],
        ],
    ],
    [
        'id' => 4, 'name' => 'Mission Trip to Mozambique',
        'category' => 'Missions', 'icon' => 'fa-earth-africa', 'colour' => '#1D4ED8',
        'status' => 'active', 'currency' => 'USD',
        'target' => 18000, 'raised' => 6200, 'pledged' => 9800,
        'start_date' => '2026-01-10', 'target_date' => '2026-09-05',
        'contributors' => [3, 7, 11, 15, 18],
        'allow_pledges' => true, 'public_progress' => false,
        'description' => 'Fourteen members to Beira for three weeks, working alongside the assemblies there. Covers transport, visas, materials and accommodation.',
        'updates' => [
            ['date' => '2026-08-14', 'title' => 'Visas lodged', 'body' => 'All fourteen applications are with the consulate. Travel is booked for October.', 'photos' => 1],
        ],
    ],
    [
        'id' => 5, 'name' => 'Orphanage Support',
        'category' => 'Welfare', 'icon' => 'fa-hand-holding-heart', 'colour' => '#BE185D',
        'status' => 'active', 'currency' => 'USD',
        'target' => 24000, 'raised' => 15600, 'pledged' => 19300,
        'start_date' => '2025-04-01', 'target_date' => '2027-03-31',
        'contributors' => [2, 5, 8, 10, 14, 16, 17, 19],
        'allow_pledges' => true, 'public_progress' => true,
        'description' => 'A standing commitment to the children\'s home at Zvishavane — school fees, uniforms and a monthly grocery run.',
        'updates' => [
            ['date' => '2026-07-30', 'title' => 'Term three fees paid', 'body' => 'Fees settled for all twenty-two children ahead of the term.', 'photos' => 4],
            ['date' => '2026-04-12', 'title' => 'Winter blankets',      'body' => 'Sixty blankets and forty pairs of shoes delivered.',            'photos' => 6],
        ],
    ],
    [
        'id' => 6, 'name' => 'Land Purchase',
        'category' => 'Property', 'icon' => 'fa-map-location-dot', 'colour' => '#56287F',
        'status' => 'on_hold', 'currency' => 'USD',
        'target' => 85000, 'raised' => 21000, 'pledged' => 33500,
        'start_date' => '2025-08-01', 'target_date' => '2027-08-31',
        'contributors' => [1, 4, 9, 12, 20],
        'allow_pledges' => false, 'public_progress' => false,
        'description' => 'Two hectares adjoining the Norton assembly. On hold while the title deed dispute between the seller and the estate is resolved.',
        'updates' => [
            ['date' => '2026-06-20', 'title' => 'Placed on hold', 'body' => 'The board paused collections until the deed is clear. Nothing already given is at risk.', 'photos' => 0],
        ],
    ],
    [
        'id' => 7, 'name' => 'Roof Repairs',
        'category' => 'Maintenance', 'icon' => 'fa-house-chimney-crack', 'colour' => '#B91C1C',
        'status' => 'completed', 'currency' => 'USD',
        'target' => 25000, 'raised' => 25400, 'pledged' => 25400,
        'start_date' => '2024-11-01', 'target_date' => '2025-08-31',
        'contributors' => [1, 2, 3, 6, 7, 11, 13, 16, 18, 19],
        'allow_pledges' => false, 'public_progress' => true,
        'description' => 'Replacing the storm-damaged roof sheeting and trusses over the main hall and the vestry.',
        'updates' => [
            ['date' => '2025-08-26', 'title' => 'Completed and handed over', 'body' => 'Final inspection passed. The project closed four hundred dollars over target, carried to general maintenance.', 'photos' => 3],
        ],
    ],
    [
        'id' => 8, 'name' => 'Youth Camp',
        'category' => 'Youth', 'icon' => 'fa-campground', 'colour' => '#047857',
        'status' => 'active', 'currency' => 'USD',
        'target' => 6000, 'raised' => 1150, 'pledged' => 2400,
        'start_date' => '2026-05-01', 'target_date' => '2026-08-20',
        'contributors' => [5, 10, 15, 17],
        'allow_pledges' => true, 'public_progress' => true,
        'description' => 'The August camp at Nyanga for the youth and Sunday school — transport, food and the campsite booking.',
        'updates' => [
            ['date' => '2026-08-10', 'title' => 'Shortfall flagged', 'body' => 'The camp is under-funded with the date passed. The youth committee is deciding whether to postpone.', 'photos' => 0],
        ],
    ],
];

/* Payment plans a pledge can be made on. `months` is the gap between
   instalments; a one-off has no gap because it has no second instalment. */
$pledge_plans = [
    'one_off'   => ['key' => 'one_off',   'name' => 'One-off',   'months' => 0],
    'weekly'    => ['key' => 'weekly',    'name' => 'Weekly',    'months' => 0.25],
    'monthly'   => ['key' => 'monthly',   'name' => 'Monthly',   'months' => 1],
    'quarterly' => ['key' => 'quarterly', 'name' => 'Quarterly', 'months' => 3],
    'custom'    => ['key' => 'custom',    'name' => 'Custom',    'months' => 2],
];

/* Twenty pledges. Only what a real row would hold is stored — the status, the
   schedule, the next due date and everything on the progress bar are worked
   out from these figures against today's date.
   LATER: SELECT * FROM pledges WHERE church_id = :church_id; */
$pledges_demo = [
    ['id' =>  1, 'member_id' =>  7, 'project_id' => 1, 'amount' => 2400.00, 'currency' => 'USD', 'paid' => 2400.00, 'plan' => 'monthly',   'instalments' => 12, 'first_due' => '2025-09-05', 'defaulted' => false, 'notes' => 'Completed ahead of the final instalment.'],
    ['id' =>  2, 'member_id' =>  1, 'project_id' => 1, 'amount' => 6000.00, 'currency' => 'USD', 'paid' => 4500.00, 'plan' => 'monthly',   'instalments' => 24, 'first_due' => '2025-03-01', 'defaulted' => false, 'notes' => ''],
    ['id' =>  3, 'member_id' => 13, 'project_id' => 1, 'amount' => 1200.00, 'currency' => 'USD', 'paid' =>  900.00, 'plan' => 'quarterly', 'instalments' =>  4, 'first_due' => '2025-11-15', 'defaulted' => false, 'notes' => 'Asked to move to a monthly plan.'],
    ['id' =>  4, 'member_id' =>  5, 'project_id' => 1, 'amount' => 15000.00,'currency' => 'USD', 'paid' => 9000.00, 'plan' => 'quarterly', 'instalments' => 10, 'first_due' => '2025-04-01', 'defaulted' => false, 'notes' => 'Pledged at the ground-breaking service.'],
    ['id' =>  5, 'member_id' =>  9, 'project_id' => 1, 'amount' =>  900.00, 'currency' => 'USD', 'paid' =>    0.00, 'plan' => 'monthly',   'instalments' =>  6, 'first_due' => '2026-02-10', 'defaulted' => true,  'notes' => 'No contact since February. Marked defaulted by the treasurer.'],
    ['id' =>  6, 'member_id' =>  3, 'project_id' => 2, 'amount' => 1800.00, 'currency' => 'USD', 'paid' => 1650.00, 'plan' => 'monthly',   'instalments' => 12, 'first_due' => '2025-10-01', 'defaulted' => false, 'notes' => ''],
    ['id' =>  7, 'member_id' => 16, 'project_id' => 2, 'amount' => 30000.00,'currency' => 'ZWG', 'paid' => 12000.00,'plan' => 'monthly',   'instalments' => 10, 'first_due' => '2025-12-01', 'defaulted' => false, 'notes' => 'Paying in local currency by agreement.'],
    ['id' =>  8, 'member_id' => 11, 'project_id' => 2, 'amount' => 3000.00, 'currency' => 'USD', 'paid' => 3000.00, 'plan' => 'one_off',   'instalments' =>  1, 'first_due' => '2026-01-20', 'defaulted' => false, 'notes' => 'Settled in full on the day.'],
    ['id' =>  9, 'member_id' => 20, 'project_id' => 2, 'amount' =>  600.00, 'currency' => 'USD', 'paid' =>  150.00, 'plan' => 'weekly',    'instalments' => 24, 'first_due' => '2026-03-08', 'defaulted' => false, 'notes' => ''],
    ['id' => 10, 'member_id' =>  6, 'project_id' => 3, 'amount' => 2000.00, 'currency' => 'USD', 'paid' => 2000.00, 'plan' => 'monthly',   'instalments' =>  8, 'first_due' => '2025-10-12', 'defaulted' => false, 'notes' => ''],
    ['id' => 11, 'member_id' => 19, 'project_id' => 3, 'amount' =>  480.00, 'currency' => 'GBP', 'paid' =>  360.00, 'plan' => 'quarterly', 'instalments' =>  4, 'first_due' => '2025-10-01', 'defaulted' => false, 'notes' => 'Gives from the United Kingdom.'],
    ['id' => 12, 'member_id' =>  1, 'project_id' => 4, 'amount' => 1500.00, 'currency' => 'USD', 'paid' =>  500.00, 'plan' => 'monthly',   'instalments' =>  6, 'first_due' => '2026-02-15', 'defaulted' => false, 'notes' => ''],
    ['id' => 13, 'member_id' => 15, 'project_id' => 4, 'amount' =>  750.00, 'currency' => 'USD', 'paid' =>  625.00, 'plan' => 'monthly',   'instalments' =>  6, 'first_due' => '2026-03-01', 'defaulted' => false, 'notes' => 'Two instalments missed.'],
    ['id' => 14, 'member_id' => 18, 'project_id' => 4, 'amount' => 4200.00, 'currency' => 'ZAR', 'paid' => 3500.00, 'plan' => 'monthly',   'instalments' =>  6, 'first_due' => '2026-04-05', 'defaulted' => false, 'notes' => ''],
    ['id' => 15, 'member_id' =>  8, 'project_id' => 5, 'amount' => 1200.00, 'currency' => 'USD', 'paid' =>  1100.00, 'plan' => 'monthly',   'instalments' => 12, 'first_due' => '2025-10-20', 'defaulted' => false, 'notes' => 'Standing order through the bank.'],
    ['id' => 16, 'member_id' => 14, 'project_id' => 5, 'amount' => 2400.00, 'currency' => 'USD', 'paid' => 1400.00, 'plan' => 'monthly',   'instalments' => 24, 'first_due' => '2025-06-01', 'defaulted' => false, 'notes' => ''],
    ['id' => 17, 'member_id' => 17, 'project_id' => 5, 'amount' =>  360.00, 'currency' => 'USD', 'paid' =>  360.00, 'plan' => 'custom',    'instalments' =>  3, 'first_due' => '2025-12-01', 'defaulted' => false, 'notes' => ''],
    ['id' => 18, 'member_id' =>  4, 'project_id' => 6, 'amount' => 5000.00, 'currency' => 'USD', 'paid' => 1000.00, 'plan' => 'quarterly', 'instalments' =>  8, 'first_due' => '2025-09-01', 'defaulted' => false, 'notes' => 'Paused with the project.'],
    ['id' => 19, 'member_id' => 10, 'project_id' => 8, 'amount' =>  400.00, 'currency' => 'USD', 'paid' =>  300.00, 'plan' => 'monthly',   'instalments' =>  4, 'first_due' => '2026-05-10', 'defaulted' => false, 'notes' => ''],
    ['id' => 20, 'member_id' =>  5, 'project_id' => 8, 'amount' =>  800.00, 'currency' => 'USD', 'paid' =>    0.00, 'plan' => 'one_off',   'instalments' =>  1, 'first_due' => '2026-06-30', 'defaulted' => false, 'notes' => 'Promised at the youth service; nothing received.'],
];

/* Money received per project, month by month, for the Analysis tab's line
   chart. Twelve points each, oldest first, in USD.
   LATER: SELECT project_id, DATE_FORMAT(received_on,'%Y-%m'), SUM(amount_usd)
           FROM contributions WHERE project_id IS NOT NULL GROUP BY 1, 2; */
$project_trend_demo = [
    1 => [2100, 2800, 3400, 4100, 3900, 5200, 6400, 5800, 7100, 8200, 7600, 9400],
    2 => [ 900, 1200, 1450, 1100, 1800, 2400, 2100, 2900, 3200, 2800, 3600, 4100],
    3 => [ 400,  650,  820,  900, 1100,  980, 1250, 1400, 1180, 1350, 1520, 1600],
    4 => [   0,    0,  180,  320,  450,  610,  780,  920, 1050,  880, 1140, 1300],
    5 => [ 620,  740,  810,  900,  980, 1120, 1240, 1180, 1360, 1420, 1580, 1700],
    6 => [1200, 1400, 1650, 1800, 2100, 2400, 1900,  800,  400,  200,    0,    0],
    7 => [1800, 2200, 2600, 3100, 3400, 3900, 4200, 3800,    0,    0,    0,    0],
    8 => [   0,    0,    0,   80,  140,  210,  260,  180,  120,   90,   50,   20],
];

/* Headline figures for the stat strip, with the previous period beside them.
   LATER: the same aggregate run over two windows. */
$pledge_stats = [
    'projects' => ['now' => 6,      'prev' => 5],
    'pledged'  => ['now' => 234600, 'prev' => 211400],
    'received' => ['now' => 183750, 'prev' => 161200],
];

/* ==========================================================================
   13h. EXPENSES  (finance/expenses.php)
   Money going out, and who authorised it. In most churches the person who
   spends is not the person who approves, so every row carries a workflow
   state as well as a figure.
   LATER: SELECT * FROM expense_categories WHERE church_id = :church_id;
   ========================================================================== */

$expense_categories = [
    ['key' => 'utilities',   'name' => 'Utilities',              'icon' => 'fa-bolt',              'colour' => '#B45309'],
    ['key' => 'rent',        'name' => 'Rent & Facilities',      'icon' => 'fa-building',          'colour' => '#662F97'],
    ['key' => 'salaries',    'name' => 'Salaries & Stipends',    'icon' => 'fa-user-tie',          'colour' => '#1D4ED8'],
    ['key' => 'maintenance', 'name' => 'Maintenance & Repairs',  'icon' => 'fa-screwdriver-wrench','colour' => '#B91C1C'],
    ['key' => 'transport',   'name' => 'Transport & Fuel',       'icon' => 'fa-gas-pump',          'colour' => '#0F766E'],
    ['key' => 'events',      'name' => 'Events & Programs',      'icon' => 'fa-calendar-star',     'colour' => '#BE185D'],
    ['key' => 'outreach',    'name' => 'Outreach & Missions',    'icon' => 'fa-earth-africa',      'colour' => '#047857'],
    ['key' => 'welfare',     'name' => 'Welfare & Benevolence',  'icon' => 'fa-hand-holding-heart','colour' => '#8F5CC2'],
    ['key' => 'office',      'name' => 'Office & Admin',         'icon' => 'fa-paperclip',         'colour' => '#56287F'],
    ['key' => 'equipment',   'name' => 'Equipment',              'icon' => 'fa-toolbox',           'colour' => '#0369A1'],
    ['key' => 'media',       'name' => 'Media & Sound',          'icon' => 'fa-volume-high',       'colour' => '#C2410C'],
    ['key' => 'refresh',     'name' => 'Refreshments',           'icon' => 'fa-mug-hot',           'colour' => '#A16207'],
    ['key' => 'bank',        'name' => 'Bank Charges',           'icon' => 'fa-building-columns',  'colour' => '#6B6480'],
    ['key' => 'other',       'name' => 'Other',                  'icon' => 'fa-ellipsis',          'colour' => '#94A3B8'],
];

/* What each category is allowed this month, in USD. Derived in section 13i
   from the active annual budget, so the budgets page and the expenses page
   read the same figure. Only used when the budgets module is on.
   LATER: SELECT category_key, amount FROM budget_lines WHERE period = :month; */

/* Twenty-five expenses across every category, status and currency.
   `status` is the workflow state: draft · pending · approved · rejected · paid.
   `approved_by` is null until somebody signs it off.
   LATER: SELECT * FROM expenses WHERE church_id = :church_id ORDER BY spent_on DESC; */
$expenses_demo = [
    ['id' =>  1, 'ref' => 'MCP-E-2041', 'days_ago' =>  1, 'description' => 'ZESA prepaid tokens — main hall', 'category' => 'utilities',   'amount' =>   180.00, 'currency' => 'USD', 'method' => 'ecocash', 'txn' => 'MP260827.1420.B31908', 'payee' => 'ZESA Holdings',            'by' => 'Farai Nyoni',    'approved_by' => null,             'status' => 'pending',  'receipt' => true,  'notes' => 'Hall meter was down to eleven units before Sunday.'],
    ['id' =>  2, 'ref' => 'MCP-E-2040', 'days_ago' =>  2, 'description' => 'Diesel for the church generator',  'category' => 'transport',   'amount' =>   240.00, 'currency' => 'USD', 'method' => 'cash',    'txn' => '',                    'payee' => 'Puma Energy Chitungwiza','by' => 'Blessing Moyo',  'approved_by' => null,             'status' => 'pending',  'receipt' => true,  'notes' => 'Two hundred litres to cover the load-shedding schedule through September.'],
    ['id' =>  3, 'ref' => 'MCP-E-2039', 'days_ago' =>  4, 'description' => 'Pastoral stipends — August',       'category' => 'salaries',    'amount' =>  3200.00, 'currency' => 'USD', 'method' => 'bank',    'txn' => 'FBC-TT-889201',       'payee' => 'Payroll run 2026-08',     'by' => 'Farai Nyoni',    'approved_by' => 'Rev. Enock Sithole','status' => 'paid',    'receipt' => true,  'notes' => 'Monthly run for the four pastoral staff.'],
    ['id' =>  4, 'ref' => 'MCP-E-2038', 'days_ago' =>  5, 'description' => 'Roof sheet replacement — vestry',  'category' => 'maintenance', 'amount' =>   860.00, 'currency' => 'USD', 'method' => 'cash',    'txn' => '',                    'payee' => 'Mudzi Hardware',          'by' => 'Blessing Moyo',  'approved_by' => 'Rev. Enock Sithole','status' => 'approved','receipt' => true,  'notes' => 'Six sheets and fasteners after the storm damage.'],
    ['id' =>  5, 'ref' => 'MCP-E-2037', 'days_ago' =>  6, 'description' => 'Communion supplies',              'category' => 'refresh',     'amount' =>   145.00, 'currency' => 'USD', 'method' => 'cash',    'txn' => '',                    'payee' => 'OK Zimbabwe',             'by' => 'Grace Chikomo',  'approved_by' => 'Tendai Marufu',  'status' => 'paid',     'receipt' => false, 'notes' => 'Bread and grape juice for the first Sunday.'],
    ['id' =>  6, 'ref' => 'MCP-E-2036', 'days_ago' =>  8, 'description' => 'Radio microphone batteries',      'category' => 'media',       'amount' =>  2400.00, 'currency' => 'ZWG', 'method' => 'ecocash', 'txn' => 'MP260820.0915.C77120','payee' => 'Sound Centre Harare',     'by' => 'Rudo Chirwa',    'approved_by' => null,             'status' => 'pending',  'receipt' => false, 'notes' => 'The lapel packs have been cutting out mid-sermon.'],
    ['id' =>  7, 'ref' => 'MCP-E-2035', 'days_ago' =>  9, 'description' => 'Youth camp transport hire',       'category' => 'events',      'amount' =>   680.00, 'currency' => 'USD', 'method' => 'bank',    'txn' => 'FBC-TT-887034',       'payee' => 'Tenda Bus Services',      'by' => 'Grace Chikomo',  'approved_by' => 'Rev. Enock Sithole','status' => 'paid',    'receipt' => true,  'notes' => 'Two buses to Nyanga and back.'],
    ['id' =>  8, 'ref' => 'MCP-E-2034', 'days_ago' => 11, 'description' => 'Groceries for bereaved family',   'category' => 'welfare',     'amount' =>   120.00, 'currency' => 'USD', 'method' => 'cash',    'txn' => '',                    'payee' => 'Mai Chikomo (welfare)',   'by' => 'Grace Chikomo',  'approved_by' => 'Tendai Marufu',  'status' => 'paid',     'receipt' => false, 'notes' => 'Approved under the standing benevolence allowance.'],
    ['id' =>  9, 'ref' => 'MCP-E-2033', 'days_ago' => 12, 'description' => 'Printer toner and A4 paper',      'category' => 'office',      'amount' =>   740.00, 'currency' => 'ZAR', 'method' => 'swipe',   'txn' => '',                    'payee' => 'Makro Polokwane',         'by' => 'Grace Chikomo',  'approved_by' => 'Tendai Marufu',  'status' => 'approved', 'receipt' => true,  'notes' => 'Bought on the Johannesburg trip; cheaper than local.'],
    ['id' => 10, 'ref' => 'MCP-E-2032', 'days_ago' => 14, 'description' => 'Monthly bank service charge',     'category' => 'bank',        'amount' =>    38.50, 'currency' => 'USD', 'method' => 'bank',    'txn' => 'FBC-CHG-0826',        'payee' => 'FBC Bank',                'by' => 'Farai Nyoni',    'approved_by' => 'Tendai Marufu',  'status' => 'paid',     'receipt' => true,  'notes' => 'Automatic deduction on the current account.'],
    ['id' => 11, 'ref' => 'MCP-E-2031', 'days_ago' => 16, 'description' => 'Hall rental — Braeside outreach', 'category' => 'rent',        'amount' =>   450.00, 'currency' => 'USD', 'method' => 'cash',    'txn' => '',                    'payee' => 'Braeside Community Hall', 'by' => 'Blessing Moyo',  'approved_by' => 'Rev. Enock Sithole','status' => 'paid',    'receipt' => true,  'notes' => 'Three Sundays while the roof was being repaired.'],
    ['id' => 12, 'ref' => 'MCP-E-2030', 'days_ago' => 18, 'description' => 'Mission trip visa fees',          'category' => 'outreach',    'amount' =>   560.00, 'currency' => 'USD', 'method' => 'bank',    'txn' => 'FBC-TT-884417',       'payee' => 'Mozambique Consulate',    'by' => 'Rudo Chirwa',    'approved_by' => 'Rev. Enock Sithole','status' => 'paid',    'receipt' => true,  'notes' => 'Fourteen applications at forty dollars each.'],
    ['id' => 13, 'ref' => 'MCP-E-2029', 'days_ago' => 19, 'description' => 'Replacement projector lamp',      'category' => 'equipment',   'amount' =>   310.00, 'currency' => 'USD', 'method' => 'zipit',   'txn' => 'ZIP-260809-44120',    'payee' => 'TechZone Harare',         'by' => 'Rudo Chirwa',    'approved_by' => null,             'status' => 'rejected', 'receipt' => false, 'notes' => 'Requested without a quotation attached.'],
    ['id' => 14, 'ref' => 'MCP-E-2028', 'days_ago' => 21, 'description' => 'Borehole pump servicing',         'category' => 'maintenance', 'amount' =>   295.00, 'currency' => 'USD', 'method' => 'cash',    'txn' => '',                    'payee' => 'Aqua Tech Services',      'by' => 'Blessing Moyo',  'approved_by' => 'Tendai Marufu',  'status' => 'paid',     'receipt' => true,  'notes' => 'Annual service, due since June.'],
    ['id' => 15, 'ref' => 'MCP-E-2027', 'days_ago' => 23, 'description' => 'City of Harare water bill',       'category' => 'utilities',   'amount' =>   410.00, 'currency' => 'USD', 'method' => 'bank',    'txn' => 'FBC-TT-881290',       'payee' => 'City of Harare',          'by' => 'Farai Nyoni',    'approved_by' => 'Tendai Marufu',  'status' => 'paid',     'receipt' => true,  'notes' => 'Quarterly account, settled in full.'],
    ['id' => 16, 'ref' => 'MCP-E-2026', 'days_ago' => 25, 'description' => 'Choir uniforms — deposit',        'category' => 'events',      'amount' =>   180.00, 'currency' => 'GBP', 'method' => 'bank',    'txn' => 'FBC-TT-880115',       'payee' => 'Sartoria Fabrics UK',     'by' => 'Grace Chikomo',  'approved_by' => null,             'status' => 'pending',  'receipt' => true,  'notes' => 'Half the cost up front; the balance falls due on delivery.'],
    ['id' => 17, 'ref' => 'MCP-E-2025', 'days_ago' => 27, 'description' => 'Sunday school teaching aids',     'category' => 'office',      'amount' =>    95.00, 'currency' => 'USD', 'method' => 'cash',    'txn' => '',                    'payee' => 'Kingstons Bookshop',      'by' => 'Grace Chikomo',  'approved_by' => 'Tendai Marufu',  'status' => 'paid',     'receipt' => false, 'notes' => 'Charts and workbooks for the term.'],
    ['id' => 18, 'ref' => 'MCP-E-2024', 'days_ago' => 30, 'description' => 'Generator fuel — August',         'category' => 'transport',   'amount' =>  8600.00, 'currency' => 'ZWG', 'method' => 'cash',    'txn' => '',                    'payee' => 'Zuva Petroleum',          'by' => 'Blessing Moyo',  'approved_by' => 'Tendai Marufu',  'status' => 'paid',     'receipt' => true,  'notes' => ''],
    ['id' => 19, 'ref' => 'MCP-E-2023', 'days_ago' => 33, 'description' => 'Speaker cable and connectors',    'category' => 'media',       'amount' =>   125.00, 'currency' => 'USD', 'method' => 'cash',    'txn' => '',                    'payee' => 'Sound Centre Harare',     'by' => 'Rudo Chirwa',    'approved_by' => 'Tendai Marufu',  'status' => 'paid',     'receipt' => true,  'notes' => ''],
    ['id' => 20, 'ref' => 'MCP-E-2022', 'days_ago' => 36, 'description' => 'Tea and refreshments — elders',   'category' => 'refresh',     'amount' =>    68.00, 'currency' => 'USD', 'method' => 'cash',    'txn' => '',                    'payee' => 'Bon Marche',              'by' => 'Grace Chikomo',  'approved_by' => 'Tendai Marufu',  'status' => 'paid',     'receipt' => false, 'notes' => ''],
    ['id' => 21, 'ref' => 'MCP-E-2021', 'days_ago' => 39, 'description' => 'Security guard stipend',          'category' => 'salaries',    'amount' =>   260.00, 'currency' => 'USD', 'method' => 'ecocash', 'txn' => 'MP260720.1730.D22841','payee' => 'Nyasha Gwenzi',           'by' => 'Farai Nyoni',    'approved_by' => 'Rev. Enock Sithole','status' => 'paid',    'receipt' => true,  'notes' => ''],
    ['id' => 22, 'ref' => 'MCP-E-2020', 'days_ago' => 42, 'description' => 'Blankets for the children\'s home','category' => 'welfare',    'amount' =>  4200.00, 'currency' => 'ZAR', 'method' => 'swipe',   'txn' => '',                    'payee' => 'Pep Stores',              'by' => 'Grace Chikomo',  'approved_by' => 'Rev. Enock Sithole','status' => 'paid',    'receipt' => true,  'notes' => 'Sixty blankets for the Zvishavane home.'],
    ['id' => 23, 'ref' => 'MCP-E-2019', 'days_ago' => 46, 'description' => 'Second-hand plastic chairs',      'category' => 'equipment',   'amount' =>   540.00, 'currency' => 'USD', 'method' => 'cheque',  'txn' => 'CHQ-004417',          'payee' => 'Ruwa Furnishers',         'by' => 'Blessing Moyo',  'approved_by' => 'Tendai Marufu',  'status' => 'paid',     'receipt' => true,  'notes' => 'One hundred chairs for the overflow tent.'],
    ['id' => 24, 'ref' => 'MCP-E-2018', 'days_ago' =>  3, 'description' => 'Tent hire for the crusade',       'category' => 'outreach',    'amount' =>   950.00, 'currency' => 'USD', 'method' => 'bank',    'txn' => '',                    'payee' => 'Harare Marquee Hire',     'by' => 'Rudo Chirwa',    'approved_by' => null,             'status' => 'draft',    'receipt' => false, 'notes' => 'Quotation still being negotiated; not yet submitted.'],
    ['id' => 25, 'ref' => 'MCP-E-2017', 'days_ago' =>  5, 'description' => 'Photocopier repair call-out',     'category' => 'other',       'amount' =>    85.00, 'currency' => 'USD', 'method' => 'cash',    'txn' => '',                    'payee' => 'Office Machines Ltd',     'by' => 'Grace Chikomo',  'approved_by' => null,             'status' => 'pending',  'receipt' => false, 'notes' => 'The feeder jams on anything above twenty pages.'],
];

/* Six months of spending per category, oldest first, in USD — the sparkline on
   each category card. Twelve months of the same feeds the trend chart.
   LATER: SELECT category_key, DATE_FORMAT(spent_on,'%Y-%m'), SUM(amount_usd)
           FROM expenses GROUP BY 1, 2; */
$expense_trend_demo = [
    'utilities'   => [ 520,  610,  480,  700,  650,  590,  720,  640,  810,  760,  690,  590],
    'rent'        => [ 450,  450,  450,  450,  450,  450,  900,  450,  450,  450,  450,  450],
    'salaries'    => [3100, 3100, 3200, 3200, 3200, 3200, 3460, 3460, 3460, 3460, 3460, 3460],
    'maintenance' => [ 210,  180,  940,  320,  260,  410,  180,  290,  620, 1120,  380, 1155],
    'transport'   => [ 380,  420,  360,  510,  470,  440,  620,  580,  690,  740,  610,  560],
    'events'      => [ 140,  620,  180,  240,  810,  190,  260,  920,  310,  280, 1140,  909],
    'outreach'    => [ 120,  180,  240,  160,  920,  210,  180,  260,  340,  610,  580,  560],
    'welfare'     => [ 180,  240,  210,  190,  260,  310,  228,  240,  180,  290,  320,  120],
    'office'      => [  90,  110,   85,  140,  120,   95,  160,  105,  130,  115,   95,  135],
    'equipment'   => [   0,  340,    0,  180,  520,    0,  260,  540,    0,  310,  180,  310],
    'media'       => [  80,  210,  140,   95,  180,  120,  240,  160,  310,  125,  190,  214],
    'refresh'     => [  55,   70,   62,   80,   68,   74,   90,   66,   85,   72,   88,  213],
    'bank'        => [  36,   36,   38,   38,   38,   38,   39,   39,   39,   39,   39,   39],
    'other'       => [  40,   85,    0,  120,   60,    0,   95,   40,  110,    0,   75,   85],
];

/* Headline figures with the previous period beside them. All in USD.
   LATER: the same aggregate run over two windows. */
$expense_stats = [
    'month'    => ['now' => 6420, 'prev' => 5880],
    'approved' => ['now' => 5140, 'prev' => 4720],
];

/* ==========================================================================
   13i. BUDGETS  (finance/budgets.php)
   A plan for a period, and what actually happened against it. The whole point
   of the page is the variance, so every line carries what was budgeted and
   enough to work out what has been received or spent.

   For the period marked `derive`, actuals are read from the live ledgers —
   $contributions_demo and $expenses_demo — and added to `prior`, which stands
   for the months of the period that fall before the demo ledger window. The
   other periods sit outside that window entirely and carry their own totals.
   LATER: SELECT * FROM budgets WHERE church_id = :church_id;
   ========================================================================== */

$budget_months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

$budget_periods = [
    [
        'id' => 1,
        'name'     => '2026 Annual Budget',
        'type'     => 'Annual',
        'start'    => '2026-01-01',
        'end'      => '2026-12-31',
        'status'   => 'active',
        'currency' => 'USD',
        'derive'   => true,
        'months'         => $budget_months,
        'budget_income'  => [9000, 9000, 9000, 9000, 9000, 9000, 9000, 9000, 9000, 9000, 9000, 9000],
        'budget_expense' => [7800, 7800, 7800, 7800, 7800, 7800, 7800, 7800, 7800, 7800, 7800, 7800],
        /* Nulls from September on: the year has not reached them yet. */
        'actual_income'  => [8200, 8900, 9600, 9100, 10200, 9800, 10800, 11131, null, null, null, null],
        'actual_expense' => [7100, 7600, 8200, 7900,  8400, 8100,  8900,  8841, null, null, null, null],
        'income' => [
            ['item' => 'Tithes',            'type' => 'tithe',       'group' => 'Regular Giving', 'budget' => 48000, 'prior' => 31200, 'notes' => 'The backbone of the budget. Assumes 320 regular givers.'],
            ['item' => 'Offerings',         'type' => 'offering',    'group' => 'Regular Giving', 'budget' => 22000, 'prior' => 14100, 'notes' => 'Sunday and midweek collections.'],
            ['item' => 'Thanksgiving',      'type' => 'thanksgiving','group' => 'Regular Giving', 'budget' =>  4500, 'prior' =>  3400, 'notes' => ''],
            ['item' => 'Building Fund',     'type' => 'building',    'group' => 'Designated',     'budget' => 15000, 'prior' => 11800, 'notes' => 'Ring-fenced for the sanctuary project.'],
            ['item' => 'Seed Offerings',    'type' => 'seed',        'group' => 'Designated',     'budget' =>  3000, 'prior' =>  1420, 'notes' => ''],
            ['item' => 'Pledge Payments',   'type' => 'pledge',      'group' => 'Designated',     'budget' =>  6000, 'prior' =>  4600, 'notes' => 'Instalments against project pledges.'],
            ['item' => 'Missions Giving',   'type' => 'missions',    'group' => 'Designated',     'budget' =>  2400, 'prior' =>  1180, 'notes' => ''],
            ['item' => 'Welfare Giving',    'type' => 'welfare',     'group' => 'Designated',     'budget' =>  1800, 'prior' =>   980, 'notes' => 'Benevolence fund top-ups.'],
            ['item' => 'Special Offerings', 'type' => 'special',     'group' => 'Special',        'budget' =>  2000, 'prior' =>  2350, 'notes' => 'Already past the line after the April convention.'],
            ['item' => 'First Fruits',      'type' => 'firstfruits', 'group' => 'Special',        'budget' =>  3300, 'prior' =>  2260, 'notes' => ''],
        ],
        'expense' => [
            ['category' => 'utilities',   'budget' => 10800, 'prior' => 6934, 'notes' => 'ZESA, water and refuse.'],
            ['category' => 'rent',        'budget' => 14400, 'prior' => 9150, 'notes' => 'Hall and office leases.'],
            ['category' => 'salaries',    'budget' => 31200, 'prior' => 17340,'notes' => 'Four pastoral staff and the caretaker.'],
            ['category' => 'maintenance', 'budget' =>  6000, 'prior' => 5225, 'notes' => 'Storm damage has taken most of this line.'],
            ['category' => 'transport',   'budget' =>  5400, 'prior' => 3680, 'notes' => ''],
            ['category' => 'events',      'budget' =>  6000, 'prior' => 2980, 'notes' => 'Convention and camps.'],
            ['category' => 'outreach',    'budget' =>  4800, 'prior' => 3232, 'notes' => ''],
            ['category' => 'welfare',     'budget' =>  3600, 'prior' => 2210, 'notes' => ''],
            ['category' => 'office',      'budget' =>  2400, 'prior' => 1755, 'notes' => ''],
            ['category' => 'equipment',   'budget' =>  3000, 'prior' =>  600, 'notes' => 'The chair replacement is still to come.'],
            ['category' => 'media',       'budget' =>  2400, 'prior' =>  670, 'notes' => 'Held back pending the sound upgrade.'],
            ['category' => 'refresh',     'budget' =>  1440, 'prior' => 1340, 'notes' => 'Communion and hospitality.'],
            ['category' => 'bank',        'budget' =>   480, 'prior' =>  305, 'notes' => ''],
            ['category' => 'other',       'budget' =>  1680, 'prior' => 2085, 'notes' => 'Absorbing what the other lines will not.'],
        ],
    ],

    [
        'id' => 2,
        'name'     => 'Q1 2026',
        'type'     => 'Quarterly',
        'start'    => '2026-01-01',
        'end'      => '2026-03-31',
        'status'   => 'closed',
        'currency' => 'USD',
        'derive'   => false,
        'months'         => ['Jan', 'Feb', 'Mar'],
        'budget_income'  => [8800, 8800, 8800],
        'budget_expense' => [7600, 7600, 7600],
        'actual_income'  => [8200, 8900, 9600],
        'actual_expense' => [7100, 7600, 8200],
        'income' => [
            ['item' => 'Tithes',            'type' => 'tithe',       'group' => 'Regular Giving', 'budget' => 11700, 'prior' => 11940, 'notes' => 'Closed slightly ahead.'],
            ['item' => 'Offerings',         'type' => 'offering',    'group' => 'Regular Giving', 'budget' =>  5400, 'prior' =>  5210, 'notes' => ''],
            ['item' => 'Thanksgiving',      'type' => 'thanksgiving','group' => 'Regular Giving', 'budget' =>  1100, 'prior' =>  1245, 'notes' => ''],
            ['item' => 'Building Fund',     'type' => 'building',    'group' => 'Designated',     'budget' =>  3600, 'prior' =>  4180, 'notes' => 'The ground-breaking service lifted this.'],
            ['item' => 'Pledge Payments',   'type' => 'pledge',      'group' => 'Designated',     'budget' =>  1500, 'prior' =>  1360, 'notes' => ''],
            ['item' => 'Special Offerings', 'type' => 'special',     'group' => 'Special',        'budget' =>  1100, 'prior' =>  1765, 'notes' => ''],
        ],
        'expense' => [
            ['category' => 'utilities',   'budget' => 2700, 'prior' => 2610, 'notes' => ''],
            ['category' => 'rent',        'budget' => 3600, 'prior' => 3600, 'notes' => 'Fixed lease, no variance.'],
            ['category' => 'salaries',    'budget' => 7800, 'prior' => 7740, 'notes' => ''],
            ['category' => 'maintenance', 'budget' => 1500, 'prior' => 2190, 'notes' => 'The February roof leak.'],
            ['category' => 'transport',   'budget' => 1350, 'prior' => 1180, 'notes' => ''],
            ['category' => 'events',      'budget' => 1500, 'prior' =>  940, 'notes' => ''],
            ['category' => 'outreach',    'budget' => 1200, 'prior' => 1320, 'notes' => ''],
            ['category' => 'office',      'budget' =>  600, 'prior' =>  520, 'notes' => ''],
            ['category' => 'refresh',     'budget' =>  360, 'prior' =>  480, 'notes' => ''],
            ['category' => 'bank',        'budget' =>  120, 'prior' =>  120, 'notes' => ''],
        ],
    ],

    [
        'id' => 3,
        'name'     => 'Building Project Budget',
        'type'     => 'Project',
        'start'    => '2026-02-01',
        'end'      => '2027-06-30',
        'status'   => 'draft',
        'currency' => 'USD',
        'derive'   => false,
        'months'         => ['Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Jan'],
        'budget_income'  => [6000, 6000, 6000, 6000, 6000, 6000, 6000, 6000, 6000, 6000, 6000, 6000],
        'budget_expense' => [5500, 5500, 5500, 5500, 5500, 5500, 5500, 5500, 5500, 5500, 5500, 5500],
        'actual_income'  => [null, null, null, null, null, null, null, null, null, null, null, null],
        'actual_expense' => [null, null, null, null, null, null, null, null, null, null, null, null],
        'income' => [
            ['item' => 'Building Fund',   'type' => 'building', 'group' => 'Designated', 'budget' => 52000, 'prior' => 0, 'notes' => 'Draft — nothing recorded against this yet.'],
            ['item' => 'Pledge Payments', 'type' => 'pledge',   'group' => 'Designated', 'budget' => 20000, 'prior' => 0, 'notes' => ''],
        ],
        'expense' => [
            ['category' => 'maintenance', 'budget' => 44000, 'prior' => 0, 'notes' => 'Structural works.'],
            ['category' => 'equipment',   'budget' => 18000, 'prior' => 0, 'notes' => 'Seating and fittings.'],
            ['category' => 'other',       'budget' =>  4000, 'prior' => 0, 'notes' => 'Council fees and contingency.'],
        ],
    ],
];

/* The per-category allowance the expenses page reads, derived from the active
   annual budget so the two pages can never disagree about what a category is
   allowed. It is scaled to the window the expense ledger actually covers —
   comparing a whole ledger against a single month's line would report almost
   every category as overspent purely because the ledger is longer than a month.
   LATER: SELECT category_key, amount FROM budget_lines WHERE period = :period; */
$__ledger_days = 1;
foreach ($expenses_demo as $__e) { $__ledger_days = max($__ledger_days, (int) $__e['days_ago']); }

$expense_budgets = [];
foreach ($budget_periods[0]['expense'] as $__line) {
    $expense_budgets[$__line['category']] = (int) round(($__line['budget'] / 365) * $__ledger_days);
}
unset($__line, $__e, $__ledger_days);

/* ==========================================================================
   13j. FINANCIAL REPORTS  (finance/reports.php)
   The reporting page sits on top of every other finance page, so its figures
   are built from one 24-month series rather than from a separate set of
   totals. A date range picks a window out of that series; the type and
   category breakdowns are that window's total split by the shares below.
   The last eight months of 2026 match the actuals the budgets page carries,
   so the two pages never disagree.
   LATER: these become GROUP BY month queries over contributions and expenses.
   ========================================================================== */

/* Twenty-four months ending with the current one, oldest first. */
$report_monthly = [
    'income' => [
        6900, 7200, 7800, 8600,                                     /* Sep–Dec 2024 */
        7100, 7400, 7900, 7600, 8200, 8000, 8400, 8100, 8800, 8300, 9000, 9600,  /* 2025 */
        8200, 8900, 9600, 9100, 10200, 9800, 10800, 11131,          /* Jan–Aug 2026 */
    ],
    'expenditure' => [
        6100, 6300, 6800, 7400,
        6200, 6400, 6700, 6500, 6900, 6800, 7100, 6900, 7300, 7000, 7500, 8000,
        7100, 7600, 8200, 7900, 8400, 8100, 8900, 8841,
    ],
];

/* The balance the running cash-flow line starts from. */
$report_opening_balance = 18400;

/* Cash actually held at the end of the current period, across all accounts.
   LATER: SELECT SUM(balance) FROM accounts WHERE church_id = :church_id; */
$report_cash_at_hand = ['now' => 31090, 'prev' => 27640];

/* How a period's income splits by contribution type, and its expenditure by
   category. Shares rather than amounts, so any window of the series above can
   be broken down without storing a figure per month per type. */
$report_income_shares = [
    'tithe' => 41.3, 'offering' => 19.8, 'building' => 16.2, 'pledge' => 6.7,
    'thanksgiving' => 4.5, 'firstfruits' => 3.3, 'special' => 3.1, 'seed' => 2.0,
    'missions' => 1.8, 'welfare' => 1.3,
];
$report_expense_shares = [
    'salaries' => 33.4, 'rent' => 15.4, 'utilities' => 11.6, 'maintenance' => 8.6,
    'transport' => 6.4, 'events' => 5.9, 'outreach' => 5.8, 'welfare' => 4.0,
    'office' => 2.9, 'refresh' => 2.4, 'equipment' => 1.8, 'media' => 1.2,
    'other' => 0.4, 'bank' => 0.2,
];

/* Where the giving actually comes from. The point of the table is the
   concentration: a small number of regular givers carry most of it.
   LATER: derived from a giving-frequency query over the last 12 months. */
$giving_segments = [
    ['key' => 'regular',    'name' => 'Regular',     'desc' => 'Gave in 9 or more of the last 12 months', 'members' => 118, 'total' => 61240, 'colour' => '#0F766E'],
    ['key' => 'occasional', 'name' => 'Occasional',  'desc' => 'Gave in 3 to 8 months',                   'members' => 142, 'total' => 21870, 'colour' => '#662F97'],
    ['key' => 'new',        'name' => 'New',         'desc' => 'First gave within the last 3 months',     'members' =>  34, 'total' =>  4180, 'colour' => '#B48FDA'],
    ['key' => 'lapsed',     'name' => 'Lapsed',      'desc' => 'Gave regularly, nothing for 3 months',    'members' =>  47, 'total' =>  2790, 'colour' => '#B45309'],
    ['key' => 'never',      'name' => 'Never Given', 'desc' => 'No contribution on record',               'members' => 121, 'total' =>     0, 'colour' => '#94A3B8'],
];

/* Confidential — only ever rendered behind finance.reports.
   LATER: SELECT member_id, SUM(amount_usd) … ORDER BY 2 DESC LIMIT 10; */
$top_givers = [
    ['member_id' => 15, 'name' => 'Kelvin Marufu',   'total' => 4820, 'count' => 34, 'segment' => 'Regular'],
    ['member_id' =>  5, 'name' => 'Nyasha Dube',     'total' => 4150, 'count' => 31, 'segment' => 'Regular'],
    ['member_id' =>  7, 'name' => 'Loveness Moyo',   'total' => 3640, 'count' => 29, 'segment' => 'Regular'],
    ['member_id' =>  1, 'name' => 'Tendai Museka',   'total' => 3180, 'count' => 27, 'segment' => 'Regular'],
    ['member_id' => 13, 'name' => 'Simbarashe Ncube','total' => 2790, 'count' => 24, 'segment' => 'Regular'],
    ['member_id' =>  9, 'name' => 'Chiedza Banda',   'total' => 2410, 'count' => 22, 'segment' => 'Regular'],
    ['member_id' => 11, 'name' => 'Tapiwa Zhou',     'total' => 2050, 'count' => 19, 'segment' => 'Regular'],
    ['member_id' => 16, 'name' => 'Melody Sibanda',  'total' => 1780, 'count' => 17, 'segment' => 'Regular'],
    ['member_id' =>  2, 'name' => 'Denford Masuku',  'total' => 1520, 'count' => 15, 'segment' => 'Occasional'],
    ['member_id' => 18, 'name' => 'Rutendo Chimuka', 'total' => 1290, 'count' => 12, 'segment' => 'Occasional'],
];

/* Giving cut three ways. Each set is [label => [members, total]] in USD.
   LATER: joins onto the member table's dob, gender and department. */
$giving_demographics = [
    'age' => [
        'Under 18' => ['members' =>  38, 'total' =>   940],
        '18–25'    => ['members' =>  74, 'total' =>  6280],
        '26–35'    => ['members' => 118, 'total' => 21440],
        '36–50'    => ['members' =>  96, 'total' => 31860],
        '51–65'    => ['members' =>  82, 'total' => 22110],
        'Over 65'  => ['members' =>  54, 'total' =>  7450],
    ],
    'gender' => [
        'Female' => ['members' => 258, 'total' => 51820],
        'Male'   => ['members' => 204, 'total' => 38260],
    ],
    'department' => [
        "Women's Fellowship" => ['members' => 86, 'total' => 18420],
        'Praise & Worship'   => ['members' => 42, 'total' =>  9860],
        'Youth Ministry'     => ['members' => 96, 'total' =>  8140],
        'Choir'              => ['members' => 38, 'total' =>  7290],
        "Children's Ministry"=> ['members' => 34, 'total' =>  4610],
        'Intercession'       => ['members' => 29, 'total' =>  6180],
        'Media & Sound'      => ['members' => 18, 'total' =>  3240],
        'Protocol'           => ['members' => 22, 'total' =>  2870],
    ],
];

/* Members who gave steadily and then stopped. The whole reason the report
   exists is so somebody follows them up.
   LATER: members with 6+ months of giving and nothing in the last 90 days. */
$lapsed_givers = [
    ['member_id' =>  3, 'name' => 'Anotida Mabhena', 'last_gave' => '2026-04-12', 'previous_total' => 1840, 'months_given' => 11],
    ['member_id' =>  8, 'name' => 'Tariro Zvobgo',   'last_gave' => '2026-03-29', 'previous_total' => 1260, 'months_given' => 9],
    ['member_id' => 12, 'name' => 'Farai Chikafu',   'last_gave' => '2026-03-08', 'previous_total' =>  980, 'months_given' => 10],
    ['member_id' => 17, 'name' => 'Rudo Mhlanga',    'last_gave' => '2026-02-15', 'previous_total' =>  740, 'months_given' => 8],
    ['member_id' => 20, 'name' => 'Brian Zvobgo',    'last_gave' => '2026-01-25', 'previous_total' => 1420, 'months_given' => 12],
    ['member_id' =>  6, 'name' => 'Memory Sibanda',  'last_gave' => '2025-12-21', 'previous_total' =>  610, 'months_given' => 7],
];

/* How long an expense waits between being requested and being decided.
   LATER: AVG(DATEDIFF(approved_at, created_at)) with a histogram beside it. */
$approval_turnaround = [
    'average_days' => 3.4,
    'previous_days' => 4.1,
    'buckets' => [
        'Same day'   => 22,
        '1–2 days'   => 41,
        '3–5 days'   => 28,
        '6–10 days'  => 14,
        'Over 10'    => 7,
    ],
];

/* ==========================================================================
   13k. ANNOUNCEMENTS  (communication/announcements.php)
   Notices the church publishes to its members. They appear on the member-
   facing side and may also be pushed out by SMS or email, so each record
   carries both what was said and how it went out.
   LATER: SELECT * FROM announcement_types WHERE church_id = :church_id;
   ========================================================================== */

/* How many members are actually on the notice list. Smaller than total
   membership, because it counts only those with a contact number or an email
   address on file — which is what an announcement can reach.
   LATER: SELECT COUNT(*) FROM members WHERE church_id = :church_id
           AND (phone IS NOT NULL OR email IS NOT NULL); */
$announcement_audience_total = 462;

$announcement_types = [
    ['key' => 'general',   'name' => 'General',        'icon' => 'fa-circle-info',        'colour' => '#662F97'],
    ['key' => 'urgent',    'name' => 'Urgent',         'icon' => 'fa-triangle-exclamation','colour' => '#B91C1C'],
    ['key' => 'event',     'name' => 'Event',          'icon' => 'fa-calendar-star',      'colour' => '#1D4ED8'],
    ['key' => 'service',   'name' => 'Service Change', 'icon' => 'fa-church',             'colour' => '#B45309'],
    ['key' => 'prayer',    'name' => 'Prayer Request', 'icon' => 'fa-hands-praying',      'colour' => '#0F766E'],
    ['key' => 'testimony', 'name' => 'Testimony',      'icon' => 'fa-comment-dots',       'colour' => '#8F5CC2'],
    ['key' => 'celebration','name'=> 'Celebration',    'icon' => 'fa-cake-candles',       'colour' => '#BE185D'],
    ['key' => 'notice',    'name' => 'Notice',         'icon' => 'fa-thumbtack',          'colour' => '#56287F'],
];

/* Fifteen notices covering every type and status.
   `status` is one of published · scheduled · draft · expired.
   `days_ago` is when it went out; `scheduled_at` is set instead for anything
   still waiting. `branch_ids` is null for a whole-organisation notice.
   LATER: SELECT * FROM announcements WHERE church_id = :church_id
           ORDER BY pinned DESC, pin_order, published_at DESC; */
$announcements = [
    [
        'id' => 1, 'title' => 'Sunday services move to the new sanctuary',
        'type' => 'service', 'status' => 'published', 'days_ago' => 2,
        'message' => "From Sunday the 6th of September all three morning services will be held in the new sanctuary rather than the main hall.\n\nThe 07:00 and 09:00 services keep their usual times. The 11:00 service moves forward by fifteen minutes to 11:15 so that the parking can clear between sittings.\n\nUshers will be on the path from the main gate to guide anyone who has not yet been inside the new building.",
        'audience_kind' => 'all', 'audience_label' => 'All Members', 'recipients' => 462,
        'branch_ids' => null, 'author' => 'Rev. Enock Sithole',
        'expires_on' => '2026-09-20', 'pinned' => true, 'pin_order' => 1,
        'image' => 'The new sanctuary interior', 'views' => 214,
        'sms' => ['sent' => 462, 'delivered' => 448, 'failed' => 14],
        'email' => ['sent' => 310, 'delivered' => 301],
        'allow_comments' => true,
    ],
    [
        'id' => 2, 'title' => 'Water shutdown at the Chitungwiza campus on Thursday',
        'type' => 'urgent', 'status' => 'published', 'days_ago' => 1,
        'message' => "The city has notified us of a planned water shutdown affecting the Chitungwiza campus this Thursday from 06:00 until roughly 18:00.\n\nThe midweek service will still go ahead. Please bring drinking water with you; the ablutions will be on tank supply only.",
        'audience_kind' => 'branch', 'audience_label' => '2 Branches', 'recipients' => 128,
        'branch_ids' => [7, 3], 'author' => 'Tendai Marufu',
        'expires_on' => '2026-09-03', 'pinned' => true, 'pin_order' => 2,
        'image' => null, 'views' => 187,
        'sms' => ['sent' => 128, 'delivered' => 126, 'failed' => 2],
        'email' => null,
        'allow_comments' => false,
    ],
    [
        'id' => 3, 'title' => 'Youth camp at Nyanga — final call for names',
        'type' => 'event', 'status' => 'published', 'days_ago' => 5,
        'message' => "Names for the August youth camp close on Friday. The camp runs from the 14th to the 17th at Nyanga, and the contribution is forty dollars a head, which covers transport, food and the campsite.\n\nSpeak to Rudo or any of the youth leaders after the second service, or send a message to the church office.",
        'audience_kind' => 'department', 'audience_label' => 'Youth Ministry', 'recipients' => 96,
        'branch_ids' => null, 'author' => 'Rudo Chirwa',
        'expires_on' => '2026-08-14', 'pinned' => false, 'pin_order' => 0,
        'image' => 'Last year at Nyanga', 'views' => 165,
        'sms' => ['sent' => 96, 'delivered' => 94, 'failed' => 2],
        'email' => ['sent' => 71, 'delivered' => 69],
        'allow_comments' => true,
    ],
    [
        'id' => 4, 'title' => 'Please pray for the Mabhena family',
        'type' => 'prayer', 'status' => 'published', 'days_ago' => 4,
        'message' => "Brother Anotida Mabhena lost his mother on Tuesday morning after a long illness. The burial is on Saturday at Zvishavane.\n\nPlease hold the family in prayer this week, and speak to the welfare team if you are able to help with transport.",
        'audience_kind' => 'all', 'audience_label' => 'All Members', 'recipients' => 462,
        'branch_ids' => null, 'author' => 'Grace Chikomo',
        'expires_on' => null, 'pinned' => false, 'pin_order' => 0,
        'image' => null, 'views' => 143,
        'sms' => null, 'email' => ['sent' => 310, 'delivered' => 305],
        'allow_comments' => true,
    ],
    [
        'id' => 5, 'title' => 'Thank you — the roof fund is closed',
        'type' => 'testimony', 'status' => 'published', 'days_ago' => 9,
        'message' => "The roof repair fund closed last Sunday at twenty-five thousand four hundred dollars, four hundred over what we asked for.\n\nThe surplus has been carried to general maintenance. Thank you to everyone who gave, and particularly to those who gave quietly and asked that nothing be said about it.",
        'audience_kind' => 'all', 'audience_label' => 'All Members', 'recipients' => 462,
        'branch_ids' => null, 'author' => 'Farai Nyoni',
        'expires_on' => null, 'pinned' => false, 'pin_order' => 0,
        'image' => 'The finished roof', 'views' => 128,
        'sms' => null, 'email' => ['sent' => 310, 'delivered' => 298],
        'allow_comments' => true,
    ],
    [
        'id' => 6, 'title' => 'Baptism service — Sunday the 13th',
        'type' => 'celebration', 'status' => 'published', 'days_ago' => 7,
        'message' => "Twenty-two candidates will be baptised at the 09:00 service on Sunday the 13th.\n\nFamilies are welcome to come early for photographs. Candidates should arrive by 08:00 with a change of clothes and a towel.",
        'audience_kind' => 'all', 'audience_label' => 'All Members', 'recipients' => 462,
        'branch_ids' => null, 'author' => 'Rev. Enock Sithole',
        'expires_on' => '2026-09-14', 'pinned' => true, 'pin_order' => 3,
        'image' => null, 'views' => 96,
        'sms' => ['sent' => 462, 'delivered' => 455, 'failed' => 7],
        'email' => null,
        'allow_comments' => true,
    ],
    [
        'id' => 7, 'title' => 'Cell group leaders — training on Saturday',
        'type' => 'notice', 'status' => 'published', 'days_ago' => 12,
        'message' => "All cell group leaders and assistant leaders are expected at the training on Saturday from 09:00 to 13:00 in the fellowship room.\n\nBring your cell register and a notebook. Tea will be provided.",
        'audience_kind' => 'cell', 'audience_label' => 'Cell Leaders', 'recipients' => 34,
        'branch_ids' => null, 'author' => 'Tendai Marufu',
        'expires_on' => '2026-08-22', 'pinned' => false, 'pin_order' => 0,
        'image' => null, 'views' => 88,
        'sms' => ['sent' => 34, 'delivered' => 34, 'failed' => 0],
        'email' => ['sent' => 29, 'delivered' => 29],
        'allow_comments' => false,
    ],
    [
        'id' => 8, 'title' => 'Church office closed on Monday',
        'type' => 'general', 'status' => 'published', 'days_ago' => 16,
        'message' => "The church office will be closed on Monday the 17th for the public holiday and will reopen at 08:00 on Tuesday.\n\nFor anything urgent over the weekend please call the duty pastor on the number in the members' directory.",
        'audience_kind' => 'all', 'audience_label' => 'All Members', 'recipients' => 462,
        'branch_ids' => null, 'author' => 'Grace Chikomo',
        'expires_on' => '2026-08-18', 'pinned' => false, 'pin_order' => 0,
        'image' => null, 'views' => 74,
        'sms' => null, 'email' => null,
        'allow_comments' => false,
    ],

    /* ── waiting to go out ── */
    [
        'id' => 9, 'title' => 'Harvest thanksgiving — bring your first fruits',
        'type' => 'event', 'status' => 'scheduled', 'days_ago' => null,
        'scheduled_at' => '2026-09-01 07:00',
        'message' => "Harvest thanksgiving is on Sunday the 20th of September. Bring your first fruits to the front during the offering.\n\nThe women's fellowship will be receiving produce from Friday for anyone who would rather bring it in early.",
        'audience_kind' => 'all', 'audience_label' => 'All Members', 'recipients' => 462,
        'branch_ids' => null, 'author' => 'Rev. Enock Sithole',
        'expires_on' => '2026-09-21', 'pinned' => false, 'pin_order' => 0,
        'image' => null, 'views' => 0,
        'sms' => ['sent' => 0, 'delivered' => 0, 'failed' => 0], 'email' => null,
        'allow_comments' => true,
    ],
    [
        'id' => 10, 'title' => 'Women\'s fellowship conference registration opens',
        'type' => 'event', 'status' => 'scheduled', 'days_ago' => null,
        'scheduled_at' => '2026-09-04 18:30',
        'message' => "Registration for the October women's conference opens on Friday. The theme this year is \"A quiet and steady faith\".\n\nEarly registration is fifteen dollars until the end of September.",
        'audience_kind' => 'department', 'audience_label' => "Women's Fellowship", 'recipients' => 86,
        'branch_ids' => null, 'author' => 'Grace Chikomo',
        'expires_on' => null, 'pinned' => false, 'pin_order' => 0,
        'image' => null, 'views' => 0,
        'sms' => null, 'email' => ['sent' => 0, 'delivered' => 0],
        'allow_comments' => true,
    ],
    [
        'id' => 11, 'title' => 'Quarterly members\' meeting',
        'type' => 'notice', 'status' => 'scheduled', 'days_ago' => null,
        'scheduled_at' => '2026-09-10 06:00',
        'message' => "The quarterly members' meeting is on Sunday the 27th of September immediately after the second service.\n\nThe finance report for the quarter will be tabled, along with the building committee's update.",
        'audience_kind' => 'all', 'audience_label' => 'All Members', 'recipients' => 462,
        'branch_ids' => null, 'author' => 'Tendai Marufu',
        'expires_on' => '2026-09-28', 'pinned' => false, 'pin_order' => 0,
        'image' => null, 'views' => 0,
        'sms' => null, 'email' => null,
        'allow_comments' => false,
    ],

    /* ── not finished ── */
    [
        'id' => 12, 'title' => 'Christmas programme — draft',
        'type' => 'event', 'status' => 'draft', 'days_ago' => null,
        'message' => "Draft outline for the December programme. Dates still to be confirmed with the choir and the children's ministry before this goes anywhere.",
        'audience_kind' => 'all', 'audience_label' => 'All Members', 'recipients' => 462,
        'branch_ids' => null, 'author' => 'Rudo Chirwa',
        'expires_on' => null, 'pinned' => false, 'pin_order' => 0,
        'image' => null, 'views' => 0, 'sms' => null, 'email' => null,
        'allow_comments' => true,
    ],
    [
        'id' => 13, 'title' => 'New giving numbers for EcoCash',
        'type' => 'general', 'status' => 'draft', 'days_ago' => null,
        'message' => "The merchant code changes at the end of the month. Waiting on written confirmation from the bank before this is sent to anybody.",
        'audience_kind' => 'selected', 'audience_label' => '6 Selected Members', 'recipients' => 6,
        'branch_ids' => null, 'author' => 'Farai Nyoni',
        'expires_on' => null, 'pinned' => false, 'pin_order' => 0,
        'image' => null, 'views' => 0, 'sms' => null, 'email' => null,
        'allow_comments' => false,
    ],

    /* ── past their expiry ── */
    [
        'id' => 14, 'title' => 'Winter clothing drive closes on Friday',
        'type' => 'notice', 'status' => 'expired', 'days_ago' => 54,
        'message' => "The winter clothing drive closes this Friday. Bring blankets, coats and shoes to the collection point at the back of the main hall.\n\nEverything collected goes to the children's home at Zvishavane.",
        'audience_kind' => 'all', 'audience_label' => 'All Members', 'recipients' => 462,
        'branch_ids' => null, 'author' => 'Grace Chikomo',
        'expires_on' => '2026-07-10', 'pinned' => false, 'pin_order' => 0,
        'image' => null, 'views' => 92,
        'sms' => ['sent' => 462, 'delivered' => 441, 'failed' => 21],
        'email' => null,
        'allow_comments' => false,
    ],
    [
        'id' => 15, 'title' => 'Choir practice moved to Thursday for June',
        'type' => 'service', 'status' => 'expired', 'days_ago' => 78,
        'message' => "For the month of June only, choir practice moves from Wednesday to Thursday at 18:00 because of the hall booking.",
        'audience_kind' => 'department', 'audience_label' => 'Choir', 'recipients' => 38,
        'branch_ids' => null, 'author' => 'Rudo Chirwa',
        'expires_on' => '2026-06-30', 'pinned' => false, 'pin_order' => 0,
        'image' => null, 'views' => 53,
        'sms' => null, 'email' => ['sent' => 34, 'delivered' => 33],
        'allow_comments' => false,
    ],
];

/* ==========================================================================
   14. DEMO ONLY — REMOVE BEFORE PRODUCTION
   The role sets behind the floating role switcher. Hardcoded stand-ins for
   what a real session would carry, kept here so every page shares one
   definition instead of repeating it. main/index.php declares its own copy
   after including this file and is therefore unaffected by this block.
   Delete this together with the switcher markup in the pages.
   ========================================================================== */

$demo_core_modules = ['members', 'attendance', 'departments', 'communication', 'reports'];

$demo_roles = [
    'church_admin' => [
        'user'    => ['name' => 'Tendai Marufu', 'role' => 'church_admin', 'role_label' => 'Church Administrator', 'initials' => 'TM', 'email' => 'tendai@mutendicentral.co.zw'],
        'perms'   => ['members.view', 'members.add', 'members.edit', 'members.delete', 'members.export',
                      'finance.view', 'finance.add', 'finance.edit', 'finance.delete', 'finance.reports', 'payroll.view', 'settings.manage',
                      'attendance.view', 'attendance.add', 'attendance.edit', 'attendance.reports', 'attendance.manage',
                      'branches.add', 'branches.edit', 'reports.view', 'projects.manage', 'finance.approve',
                      'budgets.manage', 'communication.view', 'announcements.manage'],
        'modules' => array_merge($demo_core_modules, ['finance', 'cell_groups', 'events', 'sermons', 'payroll', 'visitors', 'projects', 'budgets']),
    ],
    'pastor' => [
        'user'    => ['name' => 'Rev. Enock Sithole', 'role' => 'pastor', 'role_label' => 'Pastor', 'initials' => 'ES', 'email' => 'enock@mutendicentral.co.zw'],
        'perms'   => ['members.view', 'members.add', 'members.edit', 'members.export', 'finance.view', 'finance.reports', 'reports.view',
                      'finance.approve', 'communication.view', 'announcements.manage',
                      'attendance.view', 'attendance.add', 'attendance.edit', 'attendance.reports', 'attendance.manage'],
        'modules' => array_merge($demo_core_modules, ['finance', 'cell_groups', 'events', 'sermons', 'visitors', 'projects']),
    ],
    'secretary' => [
        'user'    => ['name' => 'Grace Chikomo', 'role' => 'secretary', 'role_label' => 'Church Secretary', 'initials' => 'GC', 'email' => 'grace@mutendicentral.co.zw'],
        'perms'   => ['members.view', 'members.add', 'members.edit', 'members.export',
                      'communication.view', 'announcements.manage',
                      'attendance.view', 'attendance.add', 'attendance.edit', 'attendance.reports'],
        'modules' => array_merge($demo_core_modules, ['events', 'visitors', 'cell_groups', 'sermons']),
    ],
    'treasurer' => [
        'user'    => ['name' => 'Farai Nyoni', 'role' => 'treasurer', 'role_label' => 'Treasurer', 'initials' => 'FN', 'email' => 'farai@mutendicentral.co.zw'],
        'perms'   => ['members.view', 'finance.view', 'finance.add', 'finance.edit', 'finance.reports', 'reports.view',
                      'budgets.manage', 'communication.view',
                      'attendance.view'],
        'modules' => array_merge($demo_core_modules, ['finance', 'projects', 'budgets']),
    ],
    'dept_head' => [
        'user'    => ['name' => 'Blessing Moyo', 'role' => 'dept_head', 'role_label' => 'Department Head', 'initials' => 'BM', 'email' => 'blessing@mutendicentral.co.zw'],
        'perms'   => ['members.view', 'attendance.view', 'communication.view'],
        'modules' => array_merge($demo_core_modules, ['events', 'cell_groups']),
    ],
    'cell_leader' => [
        'user'    => ['name' => 'Rudo Chirwa', 'role' => 'cell_leader', 'role_label' => 'Cell Group Leader', 'initials' => 'RC', 'email' => 'rudo@mutendicentral.co.zw'],
        'perms'   => ['members.view', 'attendance.view', 'attendance.add', 'communication.view'],
        'modules' => array_merge($demo_core_modules, ['cell_groups']),
        /* Which cell this leader actually leads — the cells page renders as a
           single detail view for them rather than the full directory. */
        'own_cell'=> 'Westgate Cell',
    ],
    'usher' => [
        'user'    => ['name' => 'Simba Dube', 'role' => 'usher', 'role_label' => 'Usher', 'initials' => 'SD', 'email' => 'simba@mutendicentral.co.zw'],
        'perms'   => ['attendance.view', 'attendance.add'],
        'modules' => array_merge($demo_core_modules, ['visitors']),
    ],
];

/* ==========================================================================
   15. THE ORGANISATION (TENANT)
   A client is either a single independent church or an organisation with many
   local churches under one head office. Both run on the same code: nothing
   below is duplicated per client type, and no page forks on it — pages ask
   is_multi_branch() and read their labels from $terminology.
   LATER: SELECT * FROM organisations WHERE id = :org_id;
   ========================================================================== */

$organisation = [
    'name'              => 'Diocese of Harare',
    'code'              => 'DOH-001',
    'logo'              => $root_url . '/resources/img/logo.png',

    /* 'single'      — one independent church, no branch layer
       'multi_branch'— head office with local churches beneath it */
    'type'              => 'multi_branch',

    'account_type'      => 'paying',            /* 'trial' | 'paying' */
    'expiry_date'       => '2027-03-31',
    'days_remaining'    => 217,

    'total_branches'    => 12,
    'total_members'     => 4863,

    'head_office_name'  => 'Diocesan Office, Harare',
    'head_office_address' => '14 Nelson Mandela Avenue, Harare',
];

/* ==========================================================================
   16. TERMINOLOGY
   Every user-facing label for the org/branch layer comes from here. A parish
   is not a "branch" to an Anglican, and a society is not a "branch" to a
   Methodist — so nothing in this system hardcodes the word. Later runs call
   t('branch_plural') and get whatever this client calls them.
   LATER: SELECT terminology_preset FROM organisations WHERE id = :org_id;
   ========================================================================== */

$terminology_presets = [

    'anglican' => [
        'org_singular'     => 'Diocese',
        'org_plural'       => 'Dioceses',
        'branch_singular'  => 'Parish',
        'branch_plural'    => 'Parishes',
        'leader_title'     => 'Priest',
        'leader_plural'    => 'Priests',
        'org_leader_title' => 'Bishop',
        'group_singular'   => 'Archdeaconry',
        'group_plural'     => 'Archdeaconries',
    ],

    'methodist' => [
        'org_singular'     => 'Circuit',
        'org_plural'       => 'Circuits',
        'branch_singular'  => 'Society',
        'branch_plural'    => 'Societies',
        'leader_title'     => 'Minister',
        'leader_plural'    => 'Ministers',
        'org_leader_title' => 'Superintendent',
        'group_singular'   => 'Section',
        'group_plural'     => 'Sections',
    ],

    'pentecostal' => [
        'org_singular'     => 'Headquarters',
        'org_plural'       => 'Headquarters',
        'branch_singular'  => 'Branch',
        'branch_plural'    => 'Branches',
        'leader_title'     => 'Pastor',
        'leader_plural'    => 'Pastors',
        'org_leader_title' => 'Overseer',
        'group_singular'   => 'Region',
        'group_plural'     => 'Regions',
    ],

    'generic' => [
        'org_singular'     => 'Organisation',
        'org_plural'       => 'Organisations',
        'branch_singular'  => 'Branch',
        'branch_plural'    => 'Branches',
        'leader_title'     => 'Pastor',
        'leader_plural'    => 'Pastors',
        'org_leader_title' => 'Director',
        'group_singular'   => 'Zone',
        'group_plural'     => 'Zones',
    ],
];

/* The one line that switches every label in the system. */
$terminology_active = 'anglican';

$terminology = $terminology_presets[$terminology_active] ?? $terminology_presets['generic'];

/* ==========================================================================
   17. BRANCHES
   The local churches under the organisation, plus the head office itself.
   Exactly one row is type 'head_office'; the rest are 'branch'. Grouped
   across three archdeaconries (see $terminology['group_singular']).
   Coordinates are real Harare-area positions so a later map view has
   somewhere to put its pins.
   LATER: SELECT b.*, COUNT(m.id) AS members_count
          FROM branches b LEFT JOIN members m ON m.branch_id = b.id
          WHERE b.org_id = :org_id GROUP BY b.id ORDER BY b.name;
   ========================================================================== */

$branches = [
    [
        'id' => 1, 'name' => 'St Mary\'s Cathedral', 'code' => 'DOH-HO-01',
        'type' => 'head_office', 'group_name' => 'Harare Central Archdeaconry',
        'leader_name' => 'Bishop Nathaniel Chikomo', 'leader_phone' => '+263 772 410 118',
        'leader_email' => 'bishop@dioceseofharare.org.zw', 'leader_avatar' => null,
        'address' => '14 Nelson Mandela Avenue', 'suburb' => 'Harare CBD',
        'city' => 'Harare', 'province' => 'Harare',
        'latitude' => -17.8292, 'longitude' => 31.0522,
        'members_count' => 842, 'avg_attendance' => 613, 'attendance_rate' => 73,
        'monthly_giving' => 9840.00, 'growth_percent' => 4.2,
        'established_date' => '1891-06-14', 'status' => 'active', 'last_activity' => '2026-08-25',
    ],
    [
        'id' => 2, 'name' => 'St Michael\'s Mbare', 'code' => 'DOH-PR-02',
        'type' => 'branch', 'group_name' => 'Harare Central Archdeaconry',
        'leader_name' => 'Rev. Tapiwa Mudzingwa', 'leader_phone' => '+263 771 336 204',
        'leader_email' => 'tapiwa.mudzingwa@dioceseofharare.org.zw', 'leader_avatar' => null,
        'address' => '58 Ardbennie Road', 'suburb' => 'Mbare',
        'city' => 'Harare', 'province' => 'Harare',
        'latitude' => -17.8611, 'longitude' => 31.0344,
        'members_count' => 604, 'avg_attendance' => 452, 'attendance_rate' => 75,
        'monthly_giving' => 3120.00, 'growth_percent' => 6.8,
        'established_date' => '1954-03-21', 'status' => 'active', 'last_activity' => '2026-08-24',
    ],
    [
        'id' => 3, 'name' => 'St Peter\'s Highfield', 'code' => 'DOH-PR-03',
        'type' => 'branch', 'group_name' => 'Harare Central Archdeaconry',
        'leader_name' => 'Rev. Grace Nyamande', 'leader_phone' => '+263 783 552 907',
        'leader_email' => 'grace.nyamande@dioceseofharare.org.zw', 'leader_avatar' => null,
        'address' => '12 Willowvale Road', 'suburb' => 'Highfield',
        'city' => 'Harare', 'province' => 'Harare',
        'latitude' => -17.8847, 'longitude' => 30.9994,
        'members_count' => 517, 'avg_attendance' => 361, 'attendance_rate' => 70,
        'monthly_giving' => 2480.00, 'growth_percent' => 2.1,
        'established_date' => '1961-09-08', 'status' => 'active', 'last_activity' => '2026-08-24',
    ],
    [
        'id' => 4, 'name' => 'All Saints Braeside', 'code' => 'DOH-PR-04',
        'type' => 'branch', 'group_name' => 'Harare Central Archdeaconry',
        'leader_name' => 'Rev. Edmore Chuma', 'leader_phone' => '+263 712 884 663',
        'leader_email' => 'edmore.chuma@dioceseofharare.org.zw', 'leader_avatar' => null,
        'address' => '3 Jameson Road', 'suburb' => 'Braeside',
        'city' => 'Harare', 'province' => 'Harare',
        'latitude' => -17.8489, 'longitude' => 31.0664,
        'members_count' => 289, 'avg_attendance' => 198, 'attendance_rate' => 69,
        'monthly_giving' => 1940.00, 'growth_percent' => -1.4,
        'established_date' => '1972-11-02', 'status' => 'active', 'last_activity' => '2026-08-23',
    ],
    [
        'id' => 5, 'name' => 'St Luke\'s Borrowdale', 'code' => 'DOH-PR-05',
        'type' => 'branch', 'group_name' => 'Northern Archdeaconry',
        'leader_name' => 'Rev. Patience Marimo', 'leader_phone' => '+263 774 201 559',
        'leader_email' => 'patience.marimo@dioceseofharare.org.zw', 'leader_avatar' => null,
        'address' => '221 Borrowdale Road', 'suburb' => 'Borrowdale',
        'city' => 'Harare', 'province' => 'Harare',
        'latitude' => -17.7594, 'longitude' => 31.0872,
        'members_count' => 471, 'avg_attendance' => 358, 'attendance_rate' => 76,
        'monthly_giving' => 7260.00, 'growth_percent' => 8.3,
        'established_date' => '1983-04-17', 'status' => 'active', 'last_activity' => '2026-08-25',
    ],
    [
        'id' => 6, 'name' => 'St Andrew\'s Mount Pleasant', 'code' => 'DOH-PR-06',
        'type' => 'branch', 'group_name' => 'Northern Archdeaconry',
        'leader_name' => 'Rev. Lloyd Saruchera', 'leader_phone' => '+263 776 913 428',
        'leader_email' => 'lloyd.saruchera@dioceseofharare.org.zw', 'leader_avatar' => null,
        'address' => '9 Sherwood Drive', 'suburb' => 'Mount Pleasant',
        'city' => 'Harare', 'province' => 'Harare',
        'latitude' => -17.7739, 'longitude' => 31.0503,
        'members_count' => 366, 'avg_attendance' => 251, 'attendance_rate' => 69,
        'monthly_giving' => 4880.00, 'growth_percent' => 3.6,
        'established_date' => '1988-08-29', 'status' => 'active', 'last_activity' => '2026-08-22',
    ],
    [
        'id' => 7, 'name' => 'Holy Trinity Chitungwiza', 'code' => 'DOH-PR-07',
        'type' => 'branch', 'group_name' => 'Northern Archdeaconry',
        'leader_name' => 'Rev. Nyasha Gwaze', 'leader_phone' => '+263 719 447 132',
        'leader_email' => 'nyasha.gwaze@dioceseofharare.org.zw', 'leader_avatar' => null,
        'address' => '40 Seke Road', 'suburb' => 'Chitungwiza',
        'city' => 'Chitungwiza', 'province' => 'Harare',
        'latitude' => -18.0128, 'longitude' => 31.0756,
        'members_count' => 628, 'avg_attendance' => 489, 'attendance_rate' => 78,
        'monthly_giving' => 2760.00, 'growth_percent' => 9.1,
        'established_date' => '1979-01-30', 'status' => 'active', 'last_activity' => '2026-08-25',
    ],
    [
        'id' => 8, 'name' => 'St John\'s Norton', 'code' => 'DOH-PR-08',
        'type' => 'branch', 'group_name' => 'Western Archdeaconry',
        'leader_name' => 'Rev. Shepherd Mabhena', 'leader_phone' => '+263 782 660 741',
        'leader_email' => 'shepherd.mabhena@dioceseofharare.org.zw', 'leader_avatar' => null,
        'address' => '7 Galloway Road', 'suburb' => 'Norton',
        'city' => 'Norton', 'province' => 'Mashonaland West',
        'latitude' => -17.8833, 'longitude' => 30.7000,
        'members_count' => 341, 'avg_attendance' => 236, 'attendance_rate' => 69,
        'monthly_giving' => 1620.00, 'growth_percent' => 5.4,
        'established_date' => '1994-05-11', 'status' => 'active', 'last_activity' => '2026-08-21',
    ],
    [
        'id' => 9, 'name' => 'St Francis Ruwa', 'code' => 'DOH-PR-09',
        'type' => 'branch', 'group_name' => 'Western Archdeaconry',
        'leader_name' => 'Rev. Charity Museka', 'leader_phone' => '+263 773 128 605',
        'leader_email' => 'charity.museka@dioceseofharare.org.zw', 'leader_avatar' => null,
        'address' => '18 Chiremba Road', 'suburb' => 'Ruwa',
        'city' => 'Ruwa', 'province' => 'Mashonaland East',
        'latitude' => -17.8894, 'longitude' => 31.2431,
        'members_count' => 254, 'avg_attendance' => 171, 'attendance_rate' => 67,
        'monthly_giving' => 1180.00, 'growth_percent' => 7.7,
        'established_date' => '2001-10-07', 'status' => 'active', 'last_activity' => '2026-08-20',
    ],
    [
        'id' => 10, 'name' => 'St Stephen\'s Epworth', 'code' => 'DOH-PR-10',
        'type' => 'branch', 'group_name' => 'Western Archdeaconry',
        'leader_name' => 'Rev. Innocent Chidziva', 'leader_phone' => '+263 715 336 890',
        'leader_email' => 'innocent.chidziva@dioceseofharare.org.zw', 'leader_avatar' => null,
        'address' => '22 Domboramwari Road', 'suburb' => 'Epworth',
        'city' => 'Harare', 'province' => 'Harare',
        'latitude' => -17.8942, 'longitude' => 31.1483,
        'members_count' => 198, 'avg_attendance' => 124, 'attendance_rate' => 63,
        'monthly_giving' => 720.00, 'growth_percent' => -3.8,
        'established_date' => '2008-02-24', 'status' => 'inactive', 'last_activity' => '2026-06-14',
    ],
    [
        'id' => 11, 'name' => 'St Barnabas Kuwadzana', 'code' => 'DOH-PR-11',
        'type' => 'branch', 'group_name' => 'Western Archdeaconry',
        'leader_name' => 'Rev. Wellington Bwanya', 'leader_phone' => '+263 778 052 316',
        'leader_email' => 'wellington.bwanya@dioceseofharare.org.zw', 'leader_avatar' => null,
        'address' => '31 Kuwadzana Way', 'suburb' => 'Kuwadzana',
        'city' => 'Harare', 'province' => 'Harare',
        'latitude' => -17.8256, 'longitude' => 30.9161,
        'members_count' => 287, 'avg_attendance' => 205, 'attendance_rate' => 71,
        'monthly_giving' => 1340.00, 'growth_percent' => 4.9,
        'established_date' => '1998-07-19', 'status' => 'active', 'last_activity' => '2026-08-23',
    ],
    [
        'id' => 12, 'name' => 'St Timothy\'s Domboshava', 'code' => 'DOH-PL-12',
        'type' => 'branch', 'group_name' => 'Northern Archdeaconry',
        'leader_name' => 'Rev. Talent Chigumba', 'leader_phone' => '+263 717 904 552',
        'leader_email' => 'talent.chigumba@dioceseofharare.org.zw', 'leader_avatar' => null,
        'address' => 'Plot 4, Domboshava Road', 'suburb' => 'Domboshava',
        'city' => 'Goromonzi', 'province' => 'Mashonaland East',
        'latitude' => -17.6244, 'longitude' => 31.1683,
        'members_count' => 66, 'avg_attendance' => 48, 'attendance_rate' => 73,
        'monthly_giving' => 280.00, 'growth_percent' => 22.4,
        'established_date' => '2026-02-01', 'status' => 'planting', 'last_activity' => '2026-08-25',
    ],
];

/* ==========================================================================
   18. CURRENT BRANCH
   Which branch the user is looking at: a branch id, or 'all' for the whole
   organisation. A branch-scope user can never be looking at anything but
   their own branch, so their scope decides it rather than the request.
   ========================================================================== */

$current_branch = ($user['scope'] ?? 'organisation') === 'branch'
    ? ($user['branch_id'] ?? null)
    : 'all';

/* ==========================================================================
   19. MULTI-BRANCH HELPERS
   The only sanctioned way for a page to ask about the branch layer. Every
   definition is guarded so a page that includes this file twice, or defines
   its own copy, never collides.
   ========================================================================== */

if (!function_exists('is_multi_branch')) {
    /** True when this tenant runs several churches under one head office. */
    function is_multi_branch(): bool
    {
        global $organisation;
        return ($organisation['type'] ?? 'single') === 'multi_branch';
    }
}

if (!function_exists('can_see_branch')) {
    /**
     * Organisation-scope users see every branch. Branch-scope users see only
     * their own — this is the single gate every later page should call before
     * showing another branch's data.
     */
    function can_see_branch($branch_id): bool
    {
        global $user;
        if (($user['scope'] ?? 'organisation') !== 'branch') { return true; }
        return (int) $branch_id === (int) ($user['branch_id'] ?? 0);
    }
}

if (!function_exists('get_branch')) {
    /** One branch by id, or null when it does not exist. */
    function get_branch($branch_id): ?array
    {
        global $branches;
        foreach ($branches as $b) {
            if ((int) $b['id'] === (int) $branch_id) { return $b; }
        }
        return null;
    }
}

if (!function_exists('get_visible_branches')) {
    /** Only the branches the signed-in user is allowed to see. */
    function get_visible_branches(): array
    {
        global $branches;
        return array_values(array_filter($branches, static function ($b) {
            return can_see_branch($b['id']);
        }));
    }
}

if (!function_exists('current_branch_name')) {
    /**
     * What to print in a header or a picker: the branch's own name, or
     * "All Parishes" (whatever this client calls them) when viewing the lot.
     */
    function current_branch_name(): string
    {
        global $current_branch;
        if ($current_branch === 'all' || $current_branch === null) {
            return 'All ' . t('branch_plural');
        }
        $b = get_branch($current_branch);
        return $b['name'] ?? ('All ' . t('branch_plural'));
    }
}

if (!function_exists('t')) {
    /**
     * A label from the active terminology preset. Falls back to the key
     * itself so a missing label is visible in the UI rather than blank.
     */
    function t(string $key): string
    {
        global $terminology;
        return $terminology[$key] ?? $key;
    }
}

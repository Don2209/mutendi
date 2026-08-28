# Mutendi CMS

Church management system. XAMPP / PHP 8, no database — **all data is hardcoded PHP arrays**.
Served at `http://localhost/mutendi/`. PHP binary: `/opt/lampp/bin/php`.

## Areas

| Path | Who | Stylesheet |
|---|---|---|
| `mus/` | Vendor super admin (manages churches, plans, modules) | `mus/assets/css/style.css` |
| `main/` | Church users (the tenant-facing app) | `main/assets/css/style.css` |
| `resources/` | Public landing page + the shared logo at `resources/img/logo.png` | `resources/css/style.css` |

The two app areas share **nothing** — separate stylesheets, separate components, separate look.
`mus/` is denser and more technical; `main/` is roomier. Never load one's CSS into the other.

## main/ file structure

```
main/
  index.php              dashboard (renders the widget registry)
  includes/config.php    single source of truth — everything below lives here
  components/            header · footer · sidebar · topbar · widgets · branch-switcher
  members/ cells/ departments/ branches/ attendance/
  assets/css/style.css   one stylesheet, append-only in practice
```

**Page contract:** `require config.php` → set `$page_title` → `require components/header.php`
→ page content → `require components/footer.php`. header.php opens `.app > .shell > main.content`
and pulls in sidebar + topbar; footer.php closes them and carries the shared sidebar/menu JS.

## config.php data model

Sections 1–19, plus 13b/13c/13d for the attendance datasets. Each carries a `LATER:` comment naming the SQL that replaces it.

| Variable | Shape |
|---|---|
| `$church` | name, code, initials, logo, account_type (`trial`\|`paying`), expires_on, members |
| `$organisation` | the tenant: `type` = `single` \| `multi_branch`, total_branches, total_members, head_office_* |
| `$enabled_modules` | flat list. Core: members, attendance, departments, communication, reports. Optional: finance, cell_groups, events, sermons, assets, payroll, visitors, projects, library |
| `$user` | name, role, role_label, initials, email, **`scope`** (`organisation`\|`branch`), `branch_id` |
| `$permissions` | flat list of `area.verb` strings — e.g. `members.add`, `attendance.reports`, `settings.manage` |
| `$menu` | groups → `items`; a group or item may carry `module` and/or `permission` |
| `$widgets` | keyed registry (41). Per widget: title, icon, type, module, permission, size (`quarter`\|`third`\|`half`\|`full`), `priority` per role, optional `roles_hidden`, `org_only` |
| `$terminology_presets` / `$terminology` | see below |
| `$branches` | 12 branches: id, name, code, group_name, leader_*, members_count, avg_attendance, growth_percent, status |
| `$demo_roles` | **DEMO ONLY** — the 7 role sets behind the floating role switcher |

### Helper functions

`config.php` (all guarded with `function_exists`):
`is_multi_branch()` · `can_see_branch($id)` · `get_branch($id)` · `get_visible_branches()`
· `current_branch_name()` · `t($key)`

`components/sidebar.php`: `main_allowed($node,$modules,$perms)` · `main_visible($items,…)` (recurses,
drops a group whose children all vanish) · `main_is_active($url,$page,$dir)`

`components/widgets.php`: `main_widgets_for_role($widgets,$modules,$perms,$role,$org_mode)` + `widget_*` renderers

`components/branch-switcher.php`: `branch_resolve_current()` · `branch_url($id)` ·
`branch_switcher_render()` (mounted in topbar) · `branch_switcher_after()`

## The filtering rule

One rule, everywhere:

```php
if (isset($node['module'])     && !in_array($node['module'], $enabled_modules)) hide;
if (isset($node['permission']) && !in_array($node['permission'], $permissions)) hide;
```

Applies to menu groups, menu items, widgets, columns, buttons and whole page sections.
A nav group with no surviving children disappears entirely. An usher never sees a Delete control.

## Multi-branch vs single church

`$organisation['type']` decides. **Every branch feature must be inert when `is_multi_branch()` is false** —
no chip, no column, no filter, no selector. Verify by rendering with `'type' => 'single'` and grepping
for `bchip`, `fBranch`, `data-branch`; the output should contain none of them.

`$user['scope']`:
- `organisation` — sees every branch; `?branch=<id>` picks one, `all` is the default
- `branch` — pinned to `branch_id`; the request cannot move them, and they get a static readout, not a picker

Branch-scoped figures are scaled by that branch's share of `$organisation['total_members']`.

## Terminology

`$terminology_active` selects one of four presets — `anglican` (default), `methodist`, `pentecostal`,
`generic` — exposed through `t()`. Keys: `org_singular/plural`, `branch_singular/plural`,
`leader_title/plural`, `org_leader_title`, `group_singular/plural`.

> **Never hardcode "Branch", "Parish", "Campus" or "Circuit" in UI text.** Always `t('branch_singular')`.
> Under the default preset a branch is a *Parish* and the organisation is a *Diocese*.

## UI conventions

- **Light mode only.** No dark variant, no toggle, no `prefers-color-scheme: dark` anywhere.
- **No CSS frameworks.** One stylesheet per area; append new sections, don't restructure existing ones.
- **No `<style>` blocks in PHP.** Inline `style="--c:…"` for per-record custom properties is fine.
- **Skeleton loaders** — `[data-skeleton]` swapped for `[data-content]` by adding `.is-loaded`; `.stagger` fades children in.
- **Drawers** (`.drawer` + `.drawer-scrim`) for detail views; **modals** (`.modal-scrim` > `.modal`) for actions and confirmations.
- **Tables become stacked cards below 768px** — `.dt-wrap { display:none }` / `.dt-cards { display:block }`. Never a shrunken table.
- **Chart.js from CDN** — `4.4.4` in `main/`, `4.4.1` in `mus/`. Palette `['#662F97','#B48FDA','#8F5CC2','#D3BAEA','#56287F']`, grid `#ECE7F3`,
  `Chart.defaults.font.family = 'Plus Jakarta Sans'`. Charts need an explicit container height.
- **Respect `prefers-reduced-motion`** in every animated component.
- Destructive actions need typed confirmation. Deterministic avatar colours via `crc32(name) % 10` → `.av-c0…9`
  (the JS mirror implements real CRC-32 so both sides agree).
- Accessibility: `aria-current`, `aria-expanded`, `aria-pressed`, `aria-sort`, visible focus, keyboard nav on tabs.

## Demo scaffolding

Every page carries a `?role=` switcher (`<details class="demo">`) and re-reads
`$demo_roles[$role]` into `$user`/`$permissions`/`$enabled_modules`. Use
`array_merge($user, …)` — plain assignment drops the `scope` keys. All of it is fenced in
`DEMO ONLY — REMOVE BEFORE PRODUCTION` comments.

## Known gotchas

1. **`components/footer.php` calls `stopPropagation()` on clicks inside `[data-menu-panel]`.**
   Bubble-phase delegated handlers never fire for dropdown items — register with `addEventListener(…, true)`
   and close the menu yourself. Export menus on the older People/Branches pages are inert because of this.
2. **`main/index.php` declares its own `$demo_roles`**, shadowing config's. Permission changes must be made in both.
3. **`.dt-wrap` has `overflow-x:auto`**, which forces vertical clipping, and `.panel` hides overflow too —
   a dropdown on the last table rows gets cut off. Bottom rows open their menu upward
   (`tr:nth-last-child(-n+4):nth-child(n+5) .menu`).
4. `.dt thead th` uses `top: 0`, not `var(--top)` — `.dt-wrap` is its own scrollport.

## Verifying work

Serve and check, don't eyeball: `curl` each page for `<b>Warning|Notice|Fatal error</b>`, run
`php -l`, and use Playwright to measure element rects, computed styles and
`document.documentElement.scrollWidth > clientWidth` at 1920 / 1366 / 768 / 360.
Test every role, and both `type => single` and `multi_branch` (restore config.php afterwards — verify the md5).

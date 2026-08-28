<?php
/**
 * Mutendi CMS — Record Attendance.
 *
 * The register is taken standing at the door, on a phone, often one-handed
 * while greeting people. That shapes every decision here: tall tap targets,
 * a header that keeps the counters in view, and a save bar that never scrolls
 * away. Density is traded for reach on purpose.
 *
 * Three ways to record the same service:
 *   Member List   mark each person Present / Absent / Excused
 *   Quick Count   head counts per bucket, no member records at all
 *   Scan / Search  type a name, tap the card, it marks present and leaves
 *
 * UI only. Nothing is written anywhere: Start, Save and the modal are visual.
 */

require __DIR__ . '/../includes/config.php';

/* ══════════════ DEMO ONLY — REMOVE BEFORE PRODUCTION ══════════════ */
$demo_role       = isset($_GET['role'], $demo_roles[$_GET['role']]) ? $_GET['role'] : 'church_admin';
/* array_merge, not assignment: the scope keys from config must survive
   so a branch-scope user stays scoped on this page. */
$user            = array_merge($user, $demo_roles[$demo_role]['user']);
$permissions     = $demo_roles[$demo_role]['perms'];
$enabled_modules = $demo_roles[$demo_role]['modules'];
/* ═══════════════════════════ END DEMO ═══════════════════════════ */

/* ------------------------------------------------------------- helpers -- */
/* Guarded so this page can carry its own copy without colliding with the
   People pages — they are never loaded together. */
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

    function mu_date(string $iso, string $fmt = 'd M Y'): string { return date($fmt, strtotime($iso)); }
}

$has_module = mu_mod('attendance');
$can_save   = mu_can('attendance.add');

/* ─────────────────────────── BRANCH AWARENESS ───────────────────────────
   Which branch this register belongs to. Entirely inert for a single church:
   is_multi_branch() is false, so no selector, chip or readout is rendered.
   ──────────────────────────────────────────────────────────────────────── */
require_once __DIR__ . '/../components/branch-switcher.php';

$branch_aware   = is_multi_branch();
$branch_scoped  = $branch_aware && ($user['scope'] ?? 'organisation') === 'branch';
$viewing_all    = !$branch_aware || $current_branch === 'all' || $current_branch === null;
$branch_options = $branch_aware ? get_visible_branches() : [];

if (!function_exists('mu_branch_for')) {
    /**
     * Which branch a demo record belongs to. Deterministic from the record's
     * own key, so a person never hops between branches on reload.
     * LATER: the row carries its own branch_id and this helper disappears.
     */
    function mu_branch_for(string $key): ?array {
        static $pool = null;
        if ($pool === null) { $pool = get_visible_branches(); }
        if (!$pool) { return null; }
        return $pool[crc32($key) % count($pool)];
    }
}

/* ────────────────────────────── THE ROLL ──────────────────────────────
   Who can be marked. A branch-scope user only ever sees their own branch's
   members; an organisation-scope user viewing one branch sees that branch.
   LATER: SELECT id, name, member_no, department, cell_group FROM members
           WHERE church_id = :church_id AND status = 'Active'
             AND (:branch_id IS NULL OR branch_id = :branch_id)
        ORDER BY surname, first;
   ────────────────────────────────────────────────────────────────────── */
$roll = $has_module ? $members_demo : [];

if ($branch_aware) {
    foreach ($roll as $i => $m) { $roll[$i]['_branch'] = mu_branch_for($m['member_no']); }
    if (!$viewing_all) {
        $roll = array_values(array_filter($roll, static function ($m) use ($current_branch) {
            return $m['_branch'] && (int) $m['_branch']['id'] === (int) $current_branch;
        }));
    }
}

usort($roll, static fn($a, $b) => strcmp($a['surname'] . $a['first'], $b['surname'] . $b['first']));

$roll_total = count($roll);

/* Only the departments and cells actually present on this roll — a filter
   that can only ever return nothing is worse than no filter. */
$roll_departments = array_values(array_unique(array_filter(array_column($roll, 'department'))));
$roll_cells       = array_values(array_unique(array_filter(array_column($roll, 'cell_group'))));
sort($roll_departments);
sort($roll_cells);

/* ───────────────────────────── SETUP DEFAULTS ─────────────────────────── */
$today        = date('Y-m-d');
$default_svc  = $service_types_demo[0]['id'];
$svc_by_id    = array_column($service_types_demo, null, 'id');

/* Is there already a register for today's default service? Rendered
   server-side so the notice is correct before a single line of JS runs; the
   script re-checks it whenever the date or service changes. */
$existing_key = $today . '|' . $default_svc;
$existing     = $attendance_recorded_demo[$existing_key] ?? null;

$page_title = 'Record Attendance';
require __DIR__ . '/../components/header.php';
?>

<div class="page<?= $has_module ? ' has-savebar' : '' ?>">

  <!-- ═════════════════════════════ PAGE HEADER ═════════════════════════════ -->
  <header class="page__head">
    <div>
      <nav class="crumbs" aria-label="Breadcrumb">
        <a href="<?= $base_url ?>index.php">Dashboard</a>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span>Attendance</span>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span aria-current="page">Record Attendance</span>
      </nav>
      <div class="title-row">
        <h1 class="page__title">Record Attendance</h1>
      </div>
      <p class="page__sub">Mark who attended a service, or take a head count.</p>
    </div>

    <?php if ($has_module): ?>
      <div class="page__actions">
        <a class="btn btn--ghost" href="<?= $base_url ?>attendance/register.php">
          <i class="fa-solid fa-table-list" aria-hidden="true"></i> Attendance Register
        </a>
      </div>
    <?php endif; ?>
  </header>


<?php if (!$has_module): ?>

  <!-- ══════════════════════ MODULE SWITCHED OFF ══════════════════════ -->
  <section class="panel">
    <div class="empty">
      <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-plug-circle-xmark"></i></span>
      <h3>The Attendance module is switched off</h3>
      <p>Your church's plan does not include attendance recording. A church administrator can request it from the platform team.</p>
      <a class="btn" href="<?= $base_url ?>index.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Dashboard</a>
    </div>
  </section>

<?php else: ?>

  <?php if (!$can_save): ?>
    <div class="at-notice at-notice--info" role="status">
      <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
      <div class="at-notice__body">
        <strong>You can view a register but not record one.</strong>
        <span>Recording attendance needs the <code>attendance.add</code> permission. Ask a church administrator to grant it.</span>
      </div>
    </div>
  <?php endif; ?>

  <!-- ══════════════════════════════════════════════════════════════════
       STEP 1 — SERVICE SELECTOR
       ══════════════════════════════════════════════════════════════════ -->
  <section class="at-setup" id="atSetup" aria-labelledby="atSetupTitle">
    <div class="at-setup__head">
      <span class="at-step">1</span>
      <div>
        <h2 id="atSetupTitle">Which service?</h2>
        <p>Pick the date and service, then start marking.</p>
      </div>
    </div>

    <div class="at-setup__grid">
      <div class="field">
        <label for="atDate">Date</label>
        <input class="input" type="date" id="atDate" value="<?= $today ?>" max="<?= $today ?>">
      </div>

      <div class="field">
        <label for="atService">Service</label>
        <select class="select" id="atService">
          <?php foreach ($service_types_demo as $s): ?>
            <option value="<?= htmlspecialchars($s['id']) ?>"
                    data-start="<?= htmlspecialchars($s['default_start']) ?>"
                    data-end="<?= htmlspecialchars($s['default_end']) ?>">
              <?= htmlspecialchars($s['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if ($branch_aware): ?>
        <div class="field">
          <label for="atBranch"><?= htmlspecialchars(t('branch_singular')) ?></label>
          <?php if ($branch_scoped): ?>
            <?php $ob = get_branch($current_branch); ?>
            <div class="at-branch-fixed" id="atBranch" role="note">
              <i class="fa-solid fa-lock" aria-hidden="true"></i>
              <?= htmlspecialchars($ob['name'] ?? current_branch_name()) ?>
            </div>
            <p class="hint">You record for your own <?= htmlspecialchars(strtolower(t('branch_singular'))) ?> only.</p>
          <?php else: ?>
            <select class="select" id="atBranch">
              <?php foreach ($branch_options as $b): ?>
                <option value="<?= (int) $b['id'] ?>" <?= (!$viewing_all && (int) $b['id'] === (int) $current_branch) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($b['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="field at-setup__go">
        <button class="btn btn--lg" type="button" id="atStart">
          <i class="fa-solid fa-play" aria-hidden="true"></i> Start Recording
        </button>
      </div>
    </div>

    <!-- Fires when a register already exists for this date and service. -->
    <div class="at-notice at-notice--warn" id="atDupe" role="status" <?= $existing ? '' : 'hidden' ?>>
      <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
      <div class="at-notice__body">
        <strong>Attendance already recorded for this service</strong>
        <span id="atDupeMeta">
          <?php if ($existing): ?>
            <?= (int) $existing['present'] ?> present &middot; <?= (int) $existing['absent'] ?> absent &middot;
            recorded by <?= htmlspecialchars($existing['recorded_by']) ?> at <?= htmlspecialchars($existing['at']) ?>
          <?php endif; ?>
        </span>
      </div>
      <div class="at-notice__actions">
        <a class="btn btn--ghost btn--sm" href="<?= $base_url ?>attendance/register.php">View</a>
        <?php if ($can_save): ?>
          <button class="btn btn--sm" type="button" id="atDupeEdit">Edit</button>
        <?php endif; ?>
      </div>
    </div>
  </section>


  <!-- ══════════════════════════════════════════════════════════════════
       STEP 2 — RECORDING INTERFACE
       ══════════════════════════════════════════════════════════════════ -->
  <section class="at-rec" id="atRec" aria-labelledby="atRecTitle" hidden>

    <!-- ─────────────── sticky header: identity + live counters ─────────────── -->
    <div class="at-bar" id="atBar">
      <div class="at-bar__id">
        <h2 id="atRecTitle" class="at-bar__svc" data-svc-name>Sunday First Service</h2>
        <p class="at-bar__date" data-svc-date><?= mu_date($today, 'D, d M Y') ?></p>
      </div>

      <!-- member marking -->
      <div class="at-bar__counts" data-count-mode="members">
        <span class="at-cnt at-cnt--ok">
          <b data-c-present>0</b><span>Present</span>
        </span>
        <span class="at-cnt at-cnt--no">
          <b data-c-absent>0</b><span>Absent</span>
        </span>
        <span class="at-cnt at-cnt--un">
          <b data-c-unmarked><?= $roll_total ?></b><span>Not marked</span>
        </span>
      </div>

      <!-- quick count -->
      <div class="at-bar__counts" data-count-mode="quick" hidden>
        <span class="at-cnt at-cnt--ok">
          <b data-q-total>0</b><span>Head count</span>
        </span>
      </div>

      <div class="at-prog" data-count-mode="members">
        <div class="at-prog__track">
          <span class="at-prog__fill" id="atProgFill" style="width:0%"></span>
        </div>
        <p class="at-prog__label">
          <span id="atProgText">0 of <?= $roll_total ?> marked</span>
        </p>
      </div>
    </div>

    <!-- ────────────────────────── mode switcher ────────────────────────── -->
    <div class="at-modes" role="tablist" aria-label="Recording mode">
      <button class="at-mode is-on" type="button" role="tab" id="tabMembers"
              aria-selected="true" aria-controls="paneMembers" data-mode="members">
        <i class="fa-solid fa-list-check" aria-hidden="true"></i>
        <span>Member List</span>
      </button>
      <button class="at-mode" type="button" role="tab" id="tabQuick"
              aria-selected="false" aria-controls="paneQuick" tabindex="-1" data-mode="quick">
        <i class="fa-solid fa-calculator" aria-hidden="true"></i>
        <span>Quick Count</span>
      </button>
      <button class="at-mode" type="button" role="tab" id="tabScan"
              aria-selected="false" aria-controls="paneScan" tabindex="-1" data-mode="scan">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <span>Scan / Search</span>
      </button>
    </div>


    <!-- ══════════════════ MODE A — MEMBER LIST ══════════════════ -->
    <div class="at-pane" id="paneMembers" role="tabpanel" aria-labelledby="tabMembers">

      <div class="filters">
        <div class="filters__grid">
          <div class="field">
            <label for="fSearch">Search</label>
            <div class="search-field">
              <i class="fa-solid fa-magnifying-glass search-field__icon" aria-hidden="true"></i>
              <input class="input" type="search" id="fSearch" placeholder="Name or membership number&hellip;"
                     autocomplete="off">
            </div>
          </div>

          <div class="field">
            <label for="fDept">Department</label>
            <select class="select" id="fDept">
              <option value="">All departments</option>
              <?php foreach ($roll_departments as $d): ?>
                <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <?php if (mu_mod('cell_groups')): ?>
            <div class="field">
              <label for="fCell">Cell group</label>
              <select class="select" id="fCell">
                <option value="">All cell groups</option>
                <?php foreach ($roll_cells as $c): ?>
                  <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>

          <div class="field">
            <!-- Caption only: the toggle below carries the `for`, so the
                 checkbox is not labelled twice. -->
            <label aria-hidden="true">Show</label>
            <label class="at-toggle" for="fUnmarked">
              <span class="switch">
                <input type="checkbox" id="fUnmarked">
                <span class="switch__track" aria-hidden="true"></span>
              </span>
              <span>Unmarked only</span>
            </label>
          </div>
        </div>

        <?php if ($can_save): ?>
          <div class="at-bulk">
            <button class="btn btn--ghost btn--sm" type="button" id="atAllPresent">
              <i class="fa-solid fa-check-double" aria-hidden="true"></i> Mark all present
            </button>
            <button class="btn btn--ghost btn--sm" type="button" id="atClearMarks">
              <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Clear all marks
            </button>
            <span class="at-bulk__note" id="atShowing"><?= $roll_total ?> on the roll</span>
          </div>
        <?php endif; ?>
      </div>

      <!-- Skeleton, swapped for the roll once the page settles. -->
      <div data-skeleton>
        <?php for ($i = 0; $i < 6; $i++): ?>
          <div class="sk-row">
            <span class="sk sk--av"></span>
            <span><span class="sk sk--text" style="width:44%"></span><span class="sk sk--line" style="width:26%"></span></span>
            <span class="sk sk--pill" style="width:150px"></span>
          </div>
        <?php endfor; ?>
      </div>

      <div class="at-list stagger" id="atList" data-content>
        <?php foreach ($roll as $m): ?>
          <?php
            $mid  = (int) $m['id'];
            $bch  = ($branch_aware && $viewing_all && !empty($m['_branch'])) ? $m['_branch'] : null;
          ?>
          <div class="at-row" data-row="<?= $mid ?>"
               data-name="<?= htmlspecialchars(mb_strtolower($m['name'])) ?>"
               data-no="<?= htmlspecialchars(mb_strtolower($m['member_no'])) ?>"
               data-dept="<?= htmlspecialchars($m['department']) ?>"
               data-cell="<?= htmlspecialchars($m['cell_group']) ?>"
               data-mark="">
            <div class="at-row__who">
              <?= mu_av($m['name'], 'md') ?>
              <div class="at-row__text">
                <p class="at-row__name"><?= htmlspecialchars($m['name']) ?></p>
                <p class="at-row__meta">
                  <span class="at-row__no"><?= htmlspecialchars($m['member_no']) ?></span>
                  <?php if (!empty($m['department'])): ?>
                    <span class="at-row__dept"><?= htmlspecialchars($m['department']) ?></span>
                  <?php endif; ?>
                  <?php if ($bch): ?>
                    <span class="at-row__branch"><?= htmlspecialchars($bch['name']) ?></span>
                  <?php endif; ?>
                </p>
              </div>
            </div>

            <div class="at-seg" role="group" aria-label="Attendance for <?= htmlspecialchars($m['name']) ?>">
              <button class="at-seg__btn at-seg__btn--p" type="button" data-mark="present"
                      aria-pressed="false" <?= $can_save ? '' : 'disabled' ?>>
                <i class="fa-solid fa-check" aria-hidden="true"></i><span>Present</span>
              </button>
              <button class="at-seg__btn at-seg__btn--a" type="button" data-mark="absent"
                      aria-pressed="false" <?= $can_save ? '' : 'disabled' ?>>
                <i class="fa-solid fa-xmark" aria-hidden="true"></i><span>Absent</span>
              </button>
              <button class="at-seg__btn at-seg__btn--e" type="button" data-mark="excused"
                      aria-pressed="false" <?= $can_save ? '' : 'disabled' ?>>
                <i class="fa-solid fa-user-clock" aria-hidden="true"></i><span>Excused</span>
              </button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="empty" id="atListEmpty" hidden>
        <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-user-slash"></i></span>
        <h3>Nobody matches those filters</h3>
        <p>Try a different department or cell group, or clear the search to see the whole roll again.</p>
        <button class="btn btn--ghost" type="button" id="atClearFilters">
          <i class="fa-solid fa-filter-circle-xmark" aria-hidden="true"></i> Clear filters
        </button>
      </div>
    </div>


    <!-- ══════════════════ MODE B — QUICK COUNT ══════════════════ -->
    <div class="at-pane" id="paneQuick" role="tabpanel" aria-labelledby="tabQuick" hidden>
      <div class="at-notice at-notice--info" role="note">
        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
        <div class="at-notice__body">
          <strong>Head counts only</strong>
          <span>Quick Count saves totals, not individual member records. Nobody's attendance history changes.</span>
        </div>
      </div>

      <div class="at-steppers">
        <?php foreach ($attendance_count_groups as $g): ?>
          <div class="at-stepper" data-bucket="<?= htmlspecialchars($g['key']) ?>">
            <div class="at-stepper__head">
              <span class="stat-tile__icon tone-<?= htmlspecialchars($g['tone']) ?>" aria-hidden="true">
                <i class="fa-solid <?= htmlspecialchars($g['icon']) ?>"></i>
              </span>
              <span class="at-stepper__label" id="lbl-<?= htmlspecialchars($g['key']) ?>"><?= htmlspecialchars($g['label']) ?></span>
            </div>
            <div class="at-stepper__ctl">
              <button class="at-stepper__btn" type="button" data-step="-1"
                      aria-label="One fewer <?= htmlspecialchars($g['label']) ?>" <?= $can_save ? '' : 'disabled' ?>>
                <i class="fa-solid fa-minus" aria-hidden="true"></i>
              </button>
              <input class="at-stepper__num" type="number" inputmode="numeric" min="0" max="99999" value="0"
                     aria-labelledby="lbl-<?= htmlspecialchars($g['key']) ?>" <?= $can_save ? '' : 'disabled' ?>>
              <button class="at-stepper__btn" type="button" data-step="1"
                      aria-label="One more <?= htmlspecialchars($g['label']) ?>" <?= $can_save ? '' : 'disabled' ?>>
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
              </button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="at-total" role="status" aria-live="polite">
        <span class="at-total__label">Total attendance</span>
        <span class="at-total__value" id="atQuickTotal">0</span>
        <span class="at-total__break" id="atQuickBreak">Nothing counted yet</span>
      </div>
    </div>


    <!-- ══════════════════ MODE C — SCAN / SEARCH ══════════════════ -->
    <div class="at-pane" id="paneScan" role="tabpanel" aria-labelledby="tabScan" hidden>

      <div class="at-scanbar">
        <div class="search-field at-scanbar__field">
          <i class="fa-solid fa-magnifying-glass search-field__icon" aria-hidden="true"></i>
          <input class="input at-scanbar__input" type="search" id="sSearch"
                 placeholder="Start typing a name&hellip;" autocomplete="off"
                 aria-describedby="sHint" <?= $can_save ? '' : 'disabled' ?>>
        </div>
        <span class="at-scanbar__count" role="status" aria-live="polite">
          Marked: <b id="sMarked">0</b>
        </span>
      </div>
      <p class="hint" id="sHint">Tap a card to mark that person present. They leave the list as soon as they are marked.</p>

      <div class="at-scanwrap">
        <!-- Deliberately inert: the hardware integration is not built yet and
             a button that looks live but does nothing is worse than none. -->
        <div class="at-qr" aria-hidden="true">
          <span class="at-qr__icon"><i class="fa-solid fa-qrcode"></i></span>
          <div>
            <strong>Scan a member card</strong>
            <span>QR and barcode scanning — coming soon</span>
          </div>
          <span class="pill tone-grey">Coming soon</span>
        </div>

        <div class="at-scanlist" id="sList" aria-live="polite"></div>

        <div class="empty at-scanempty" id="sEmpty">
          <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-keyboard"></i></span>
          <h3>Type to find someone</h3>
          <p>Search by name or membership number. Matches appear here as you type &mdash; tap one to mark them present.</p>
        </div>
      </div>
    </div>


    <!-- ══════════════════ VISITORS (collapsible) ══════════════════ -->
    <?php if (mu_mod('visitors')): ?>
      <details class="at-coll" id="atVisitors">
        <summary class="at-coll__head">
          <span class="at-coll__icon" aria-hidden="true"><i class="fa-solid fa-user-plus"></i></span>
          <span class="at-coll__title">Visitors</span>
          <span class="at-coll__count" id="atVisCount">0</span>
          <i class="fa-solid fa-chevron-down at-coll__chev" aria-hidden="true"></i>
        </summary>
        <div class="at-coll__body">
          <p class="hint" style="margin:0 0 12px">Walk-ins who are not on the roll yet. Add them here and they become visitor records when the register is saved.</p>

          <div class="at-vlist" id="atVList"></div>

          <button class="btn btn--ghost btn--sm" type="button" id="atVAdd" <?= $can_save ? '' : 'disabled' ?>>
            <i class="fa-solid fa-plus" aria-hidden="true"></i> Add visitor
          </button>
        </div>
      </details>

      <!-- One visitor row, cloned by script. -->
      <template id="tplVisitor">
        <div class="at-vrow">
          <div class="field">
            <label>Name</label>
            <input class="input" type="text" data-v="name" placeholder="Full name" autocomplete="off">
          </div>
          <div class="field">
            <label>Phone</label>
            <input class="input" type="tel" data-v="phone" placeholder="+263 &hellip;" autocomplete="off">
          </div>
          <div class="field">
            <label>Invited by</label>
            <input class="input" type="text" data-v="host" placeholder="Member name" autocomplete="off" list="rollNames">
          </div>
          <button class="at-vrow__del iconbtn" type="button" aria-label="Remove this visitor">
            <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
          </button>
        </div>
      </template>

      <datalist id="rollNames">
        <?php foreach ($roll as $m): ?>
          <option value="<?= htmlspecialchars($m['name']) ?>"></option>
        <?php endforeach; ?>
      </datalist>
    <?php endif; ?>


    <!-- ══════════════════ SERVICE DETAILS (collapsible) ══════════════════ -->
    <details class="at-coll" id="atDetails">
      <summary class="at-coll__head">
        <span class="at-coll__icon" aria-hidden="true"><i class="fa-solid fa-clipboard-list"></i></span>
        <span class="at-coll__title">Service details</span>
        <span class="at-coll__opt">Optional</span>
        <i class="fa-solid fa-chevron-down at-coll__chev" aria-hidden="true"></i>
      </summary>
      <div class="at-coll__body">
        <div class="form-grid at-details">
          <?php if (mu_mod('finance')): ?>
            <div class="field">
              <label for="dOffering">Offering collected</label>
              <div class="at-money">
                <span class="at-money__cur"><?= htmlspecialchars($church['currency'] ?? 'USD') ?></span>
                <input class="input" type="number" id="dOffering" min="0" step="0.01" placeholder="0.00"
                       <?= $can_save ? '' : 'disabled' ?>>
              </div>
              <p class="hint">Posts to Finance as a service collection.</p>
            </div>
          <?php endif; ?>

          <div class="field">
            <label for="dPreacher">Preacher</label>
            <input class="input" type="text" id="dPreacher" list="rollNames"
                   placeholder="Pick a member or type a name" autocomplete="off" <?= $can_save ? '' : 'disabled' ?>>
          </div>

          <div class="field col-2">
            <label for="dTheme">Theme or scripture</label>
            <input class="input" type="text" id="dTheme" placeholder="e.g. Romans 8:28 &mdash; All things work together"
                   autocomplete="off" <?= $can_save ? '' : 'disabled' ?>>
          </div>

          <div class="field">
            <label for="dStart">Start time</label>
            <input class="input" type="time" id="dStart" value="<?= htmlspecialchars($svc_by_id[$default_svc]['default_start']) ?>"
                   <?= $can_save ? '' : 'disabled' ?>>
          </div>

          <div class="field">
            <label for="dEnd">End time</label>
            <input class="input" type="time" id="dEnd" value="<?= htmlspecialchars($svc_by_id[$default_svc]['default_end']) ?>"
                   <?= $can_save ? '' : 'disabled' ?>>
          </div>

          <div class="field">
            <label for="dWeather">Weather</label>
            <select class="select" id="dWeather" <?= $can_save ? '' : 'disabled' ?>>
              <option value="">Not noted</option>
              <?php foreach ($attendance_weather_demo as $w): ?>
                <option value="<?= htmlspecialchars($w) ?>"><?= htmlspecialchars($w) ?></option>
              <?php endforeach; ?>
            </select>
            <p class="hint">Rain is the biggest single reason for a thin service.</p>
          </div>

          <div class="field col-2">
            <label for="dNotes">Notes</label>
            <textarea class="textarea" id="dNotes" rows="3"
                      placeholder="Anything worth remembering about this service&hellip;"
                      <?= $can_save ? '' : 'disabled' ?>></textarea>
          </div>
        </div>
      </div>
    </details>

  </section>


  <!-- ══════════════════════════════════════════════════════════════════
       STEP 3 — SAVE BAR
       ══════════════════════════════════════════════════════════════════ -->
  <div class="at-savebar" id="atSaveBar" hidden>
    <p class="at-savebar__summary" role="status" aria-live="polite" id="atSummary">
      Nothing marked yet
    </p>
    <div class="at-savebar__actions">
      <button class="btn btn--ghost" type="button" id="atDraft" <?= $can_save ? '' : 'disabled' ?>>
        <i class="fa-regular fa-floppy-disk" aria-hidden="true"></i>
        <span>Save as Draft</span>
      </button>
      <button class="btn" type="button" id="atSave" <?= $can_save ? '' : 'disabled' ?>>
        <i class="fa-solid fa-check" aria-hidden="true"></i>
        <span>Save Attendance</span>
      </button>
    </div>
  </div>

<?php endif; ?>

</div><!-- /.page -->


<?php if ($has_module): ?>
<!-- ══════════════════════ CONFIRMATION MODAL ══════════════════════ -->
<div class="modal-scrim" id="modalSave" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="svTitle">
    <header class="modal__head">
      <h2 id="svTitle">Save this register?</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>

    <div class="modal__body" id="svBody">
      <p class="modal__hint" id="svService">Sunday First Service &middot; today</p>

      <div class="at-sum" id="svSummary"></div>

      <!-- Only rendered when somebody is still unmarked. -->
      <div id="svUnmarkedWrap" hidden>
        <div class="at-notice at-notice--warn" role="note" style="margin:16px 0 12px">
          <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
          <div class="at-notice__body">
            <strong><span id="svUnmarkedN">0</span> people are still unmarked</strong>
            <span>Decide what happens to them before this register is saved.</span>
          </div>
        </div>

        <div class="radio-cards">
          <label class="rcard">
            <input type="radio" name="svUnmarked" value="absent" checked>
            <span class="rcard__box">
              <i class="fa-solid fa-xmark" aria-hidden="true"></i>
              <span>
                <strong>Mark remaining as absent</strong>
                <span class="hint" style="margin:2px 0 0">Counts against their attendance record.</span>
              </span>
            </span>
          </label>
          <label class="rcard">
            <input type="radio" name="svUnmarked" value="leave">
            <span class="rcard__box">
              <i class="fa-regular fa-circle" aria-hidden="true"></i>
              <span>
                <strong>Leave unmarked</strong>
                <span class="hint" style="margin:2px 0 0">No record either way for those people.</span>
              </span>
            </span>
          </label>
        </div>
      </div>
    </div>

    <footer class="modal__foot" id="svFoot">
      <span class="modal__spacer"></span>
      <button class="btn btn--ghost" type="button" data-close>Back</button>
      <button class="btn" type="button" id="svGo">
        <i class="fa-solid fa-check" aria-hidden="true"></i> Save Attendance
      </button>
    </footer>

    <!-- Swapped in once saved. -->
    <div class="at-done" id="svDone" hidden>
      <span class="at-done__tick" aria-hidden="true"><i class="fa-solid fa-check"></i></span>
      <h3>Attendance saved</h3>
      <p id="svDoneText">The register has been recorded.</p>
      <div class="at-done__actions">
        <button class="btn btn--ghost" type="button" id="svAnother">
          <i class="fa-solid fa-rotate-right" aria-hidden="true"></i> Record another service
        </button>
        <a class="btn" href="<?= $base_url ?>attendance/register.php">
          <i class="fa-solid fa-table-list" aria-hidden="true"></i> View the register
        </a>
      </div>
    </div>
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

<?php if ($has_module): ?>
<script>
/* Record Attendance — mode switching, live counters, filtering, the quick
   count steppers, the scan list, visitor rows and the save flow.
   Entirely client-side: no register leaves this page. */
(function () {
  'use strict';

  var still    = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var CAN_SAVE = <?= $can_save ? 'true' : 'false' ?>;
  var TOTAL    = <?= $roll_total ?>;
  var ROLL     = <?= json_encode(array_map(static function ($m) {
                       return ['id' => (int) $m['id'], 'name' => $m['name'], 'no' => $m['member_no'],
                               'dept' => $m['department']];
                   }, $roll), JSON_UNESCAPED_UNICODE) ?>;
  var RECORDED = <?= json_encode($attendance_recorded_demo, JSON_UNESCAPED_UNICODE) ?>;
  var SERVICES = <?= json_encode(array_column($service_types_demo, 'name', 'id'), JSON_UNESCAPED_UNICODE) ?>;

  var $  = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };

  /* ───────────────────────────── toasts ───────────────────────────── */
  var toasts = $('#toasts');
  function toast(msg, kind, action) {
    kind = kind || 'success';
    var icons = { success: 'fa-circle-check', error: 'fa-circle-exclamation', info: 'fa-circle-info' };
    var el = document.createElement('div');
    el.className = 'toast is-' + kind;
    el.innerHTML = '<i class="fa-solid ' + icons[kind] + ' toast__icon" aria-hidden="true"></i>' +
      '<div class="toast__body"><p class="toast__title"></p></div>' +
      (action ? '<button class="toast__undo" type="button"></button>' : '') +
      '<button class="toast__close" type="button" aria-label="Dismiss"><i class="fa-solid fa-xmark"></i></button>';
    $('.toast__title', el).textContent = msg;

    var kill = function () { el.classList.add('is-out'); setTimeout(function () { el.remove(); }, 250); };
    if (action) {
      var u = $('.toast__undo', el);
      u.textContent = action.label;
      u.addEventListener('click', function () { action.run(); kill(); });
    }
    $('.toast__close', el).addEventListener('click', kill);
    toasts.appendChild(el);
    setTimeout(kill, action ? 6000 : 3600);
    return kill;
  }

  /* ═════════════════════ STEP 1 — the service selector ═════════════════════ */

  var elDate   = $('#atDate'),
      elSvc    = $('#atService'),
      elDupe   = $('#atDupe'),
      elDupeMeta = $('#atDupeMeta'),
      elStart  = $('#atStart');

  function longDate(iso) {
    var d = new Date(iso + 'T00:00:00');
    if (isNaN(d)) { return iso; }
    return d.toLocaleDateString(undefined, { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' });
  }

  /* A register for this date and service already exists? */
  function checkDupe() {
    var hit = RECORDED[elDate.value + '|' + elSvc.value];
    if (!hit) { elDupe.hidden = true; return; }
    elDupeMeta.textContent = hit.present + ' present · ' + hit.absent + ' absent · recorded by ' +
                             hit.recorded_by + ' at ' + hit.at;
    elDupe.hidden = false;
  }

  /* The service dropdown carries its own default times. */
  function syncTimes() {
    var opt = elSvc.options[elSvc.selectedIndex];
    var s = $('#dStart'), e = $('#dEnd');
    if (s && opt.getAttribute('data-start')) { s.value = opt.getAttribute('data-start'); }
    if (e && opt.getAttribute('data-end'))   { e.value = opt.getAttribute('data-end'); }
  }

  elDate.addEventListener('change', checkDupe);
  elSvc.addEventListener('change', function () { checkDupe(); syncTimes(); });
  checkDupe();

  /* ═════════════════════ STEP 2 — the recorder ═════════════════════ */

  var recorder = $('#atRec'),
      saveBar  = $('#atSaveBar'),
      barSvc   = $('[data-svc-name]'),
      barDate  = $('[data-svc-date]');

  /* Keeps the toast stack and the demo switcher clear of the save bar, and
     the page's bottom padding honest, by publishing the bar's real height. */
  function syncSaveBar() {
    var open = !saveBar.hidden;
    document.body.classList.toggle('has-savebar-open', open);
    document.documentElement.style.setProperty(
      '--savebar-h', open ? saveBar.getBoundingClientRect().height + 'px' : '0px');
  }
  if (window.ResizeObserver) { new ResizeObserver(syncSaveBar).observe(saveBar); }
  window.addEventListener('resize', syncSaveBar);

  function startRecording(edit) {
    barSvc.textContent  = SERVICES[elSvc.value] || 'Service';
    barDate.textContent = longDate(elDate.value);

    recorder.hidden = false;
    saveBar.hidden  = false;
    syncSaveBar();

    /* The skeleton only ever runs on the first start. */
    if (!recorder.classList.contains('is-loaded')) {
      setTimeout(function () { recorder.classList.add('is-loaded'); }, still ? 0 : 500);
    }

    recorder.scrollIntoView({ behavior: still ? 'auto' : 'smooth', block: 'start' });
    toast(edit ? 'Editing the existing register' : 'Recording ' + (SERVICES[elSvc.value] || 'service'), 'info');
  }

  elStart.addEventListener('click', function () { startRecording(false); });
  var dupeEdit = $('#atDupeEdit');
  if (dupeEdit) { dupeEdit.addEventListener('click', function () { startRecording(true); }); }

  /* ─────────────────────────── marks and counters ─────────────────────────── */
  /* One object is the whole truth: id → 'present' | 'absent' | 'excused'.
     Everything on screen is derived from it. */
  var marks = {};

  var cPresent = $('[data-c-present]'),
      cAbsent  = $('[data-c-absent]'),
      cUnmark  = $('[data-c-unmarked]'),
      progFill = $('#atProgFill'),
      progText = $('#atProgText'),
      summary  = $('#atSummary'),
      sMarked  = $('#sMarked');

  function tally() {
    var p = 0, a = 0, e = 0;
    for (var k in marks) {
      if (marks[k] === 'present') { p++; }
      else if (marks[k] === 'absent') { a++; }
      else if (marks[k] === 'excused') { e++; }
    }
    return { present: p, absent: a, excused: e, marked: p + a + e, unmarked: TOTAL - (p + a + e) };
  }

  function paintCounts() {
    var t = tally();
    cPresent.textContent = t.present;
    cAbsent.textContent  = t.absent;
    cUnmark.textContent  = t.unmarked;
    sMarked.textContent  = t.present;

    var pct = TOTAL ? Math.round((t.marked / TOTAL) * 100) : 0;
    progFill.style.width = pct + '%';
    progText.textContent = t.marked + ' of ' + TOTAL + ' marked';

    if (mode === 'quick') {
      summary.textContent = quickTotal() + ' counted · head count only';
    } else if (!t.marked) {
      summary.textContent = 'Nothing marked yet';
    } else {
      var bits = [t.present + ' present', t.absent + ' absent'];
      if (t.excused) { bits.push(t.excused + ' excused'); }
      if (t.unmarked) { bits.push(t.unmarked + ' unmarked'); }
      summary.textContent = bits.join(' · ');
    }
  }

  /* Paint one row from `marks`, both the buttons and the row's own tint. */
  function paintRow(row) {
    var id = row.getAttribute('data-row');
    var m  = marks[id] || '';
    row.setAttribute('data-mark', m);
    $$('.at-seg__btn', row).forEach(function (b) {
      b.setAttribute('aria-pressed', String(b.getAttribute('data-mark') === m));
    });
  }

  function setMark(id, value) {
    if (!CAN_SAVE) { return; }
    if (value === null || marks[id] === value) { delete marks[id]; }
    else { marks[id] = value; }
    var row = $('.at-row[data-row="' + id + '"]');
    if (row) { paintRow(row); }
    paintCounts();
    if (unmarkedOnly.checked) { applyFilters(); }
  }

  var list = $('#atList');
  list.addEventListener('click', function (e) {
    var btn = e.target.closest('.at-seg__btn');
    if (!btn) { return; }
    setMark(btn.closest('.at-row').getAttribute('data-row'), btn.getAttribute('data-mark'));
  });

  /* ─────────────────────────────── filters ─────────────────────────────── */

  var fSearch = $('#fSearch'),
      fDept   = $('#fDept'),
      fCell   = $('#fCell'),
      unmarkedOnly = $('#fUnmarked'),
      listEmpty = $('#atListEmpty'),
      showing = $('#atShowing');

  function applyFilters() {
    var q    = (fSearch.value || '').trim().toLowerCase(),
        dept = fDept.value,
        cell = fCell ? fCell.value : '',
        only = unmarkedOnly.checked,
        shown = 0;

    $$('.at-row', list).forEach(function (row) {
      var ok = true;
      if (q && row.getAttribute('data-name').indexOf(q) === -1 &&
               row.getAttribute('data-no').indexOf(q) === -1) { ok = false; }
      if (ok && dept && row.getAttribute('data-dept') !== dept) { ok = false; }
      if (ok && cell && row.getAttribute('data-cell') !== cell) { ok = false; }
      if (ok && only && marks[row.getAttribute('data-row')]) { ok = false; }
      row.hidden = !ok;
      if (ok) { shown++; }
    });

    listEmpty.hidden = shown > 0;
    if (showing) {
      showing.textContent = shown === TOTAL ? TOTAL + ' on the roll' : shown + ' of ' + TOTAL + ' shown';
    }
  }

  [fSearch, fDept, unmarkedOnly].forEach(function (el) {
    el.addEventListener('input', applyFilters);
    el.addEventListener('change', applyFilters);
  });
  if (fCell) { fCell.addEventListener('change', applyFilters); }

  var clearFilters = $('#atClearFilters');
  if (clearFilters) {
    clearFilters.addEventListener('click', function () {
      fSearch.value = ''; fDept.value = ''; if (fCell) { fCell.value = ''; }
      unmarkedOnly.checked = false;
      applyFilters();
      toast('Filters cleared', 'info');
    });
  }

  /* ───────────────────────────── bulk actions ───────────────────────────── */

  var allPresent = $('#atAllPresent');
  if (allPresent) {
    allPresent.addEventListener('click', function () {
      /* Snapshot first so Undo can put the register back exactly as it was. */
      var before = JSON.parse(JSON.stringify(marks));
      var n = 0;
      $$('.at-row', list).forEach(function (row) {
        if (row.hidden) { return; }
        var id = row.getAttribute('data-row');
        if (marks[id] !== 'present') { n++; }
        marks[id] = 'present';
        paintRow(row);
      });
      paintCounts();
      if (unmarkedOnly.checked) { applyFilters(); }
      toast(n + (n === 1 ? ' person' : ' people') + ' marked present', 'success', {
        label: 'Undo',
        run: function () {
          marks = before;
          $$('.at-row', list).forEach(paintRow);
          paintCounts(); applyFilters();
          toast('Bulk marking undone', 'info');
        }
      });
    });
  }

  var clearMarks = $('#atClearMarks');
  if (clearMarks) {
    clearMarks.addEventListener('click', function () {
      var before = JSON.parse(JSON.stringify(marks));
      marks = {};
      $$('.at-row', list).forEach(paintRow);
      paintCounts(); applyFilters();
      toast('All marks cleared', 'info', {
        label: 'Undo',
        run: function () {
          marks = before;
          $$('.at-row', list).forEach(paintRow);
          paintCounts(); applyFilters();
        }
      });
    });
  }

  /* ═════════════════════ MODE B — quick count ═════════════════════ */

  var qTotal = $('#atQuickTotal'), qBreak = $('#atQuickBreak'), qBar = $('[data-q-total]');

  function quickTotal() {
    var n = 0;
    $$('.at-stepper__num').forEach(function (i) { n += parseInt(i.value, 10) || 0; });
    return n;
  }

  function paintQuick() {
    var n = quickTotal();
    qTotal.textContent = n.toLocaleString();
    qBar.textContent   = n.toLocaleString();

    var parts = [];
    $$('.at-stepper').forEach(function (s) {
      var v = parseInt($('.at-stepper__num', s).value, 10) || 0;
      if (v) { parts.push(v + ' ' + $('.at-stepper__label', s).textContent.toLowerCase()); }
    });
    qBreak.textContent = parts.length ? parts.join(' · ') : 'Nothing counted yet';
    if (mode === 'quick') { paintCounts(); }
  }

  $$('.at-stepper').forEach(function (s) {
    var num = $('.at-stepper__num', s);
    $$('.at-stepper__btn', s).forEach(function (b) {
      b.addEventListener('click', function () {
        var next = (parseInt(num.value, 10) || 0) + parseInt(b.getAttribute('data-step'), 10);
        num.value = Math.max(0, Math.min(99999, next));
        paintQuick();
      });
    });
    num.addEventListener('input', function () {
      /* Keep it a non-negative integer no matter what gets typed or pasted. */
      var v = parseInt(num.value, 10);
      if (isNaN(v) || v < 0) { v = 0; }
      if (num.value !== '') { num.value = Math.min(99999, v); }
      paintQuick();
    });
    num.addEventListener('blur', function () { if (num.value === '') { num.value = 0; paintQuick(); } });
  });

  /* ═════════════════════ MODE C — scan / search ═════════════════════ */

  var sSearch = $('#sSearch'), sList = $('#sList'), sEmpty = $('#sEmpty');

  function scanRender() {
    var q = (sSearch.value || '').trim().toLowerCase();
    sList.innerHTML = '';

    if (!q) { sEmpty.hidden = false; return; }

    var hits = ROLL.filter(function (m) {
      if (marks[m.id] === 'present') { return false; }   /* already in — stay out of the way */
      return m.name.toLowerCase().indexOf(q) !== -1 || m.no.toLowerCase().indexOf(q) !== -1;
    }).slice(0, 12);

    sEmpty.hidden = true;

    if (!hits.length) {
      sList.innerHTML = '<p class="at-scanlist__none">No unmarked member matches &ldquo;' +
        q.replace(/[<>&]/g, '') + '&rdquo;.</p>';
      return;
    }

    hits.forEach(function (m) {
      var card = document.createElement('button');
      card.type = 'button';
      card.className = 'at-scancard';
      card.setAttribute('data-id', m.id);
      card.innerHTML =
        '<span class="av av--md ' + avc(m.name) + '" aria-hidden="true">' + initials(m.name) + '</span>' +
        '<span class="at-scancard__text"><b></b><span></span></span>' +
        '<span class="at-scancard__go" aria-hidden="true"><i class="fa-solid fa-check"></i></span>';
      $('b', card).textContent = m.name;
      $('.at-scancard__text span', card).textContent = m.no + (m.dept ? ' · ' + m.dept : '');
      card.setAttribute('aria-label', 'Mark ' + m.name + ' present');
      sList.appendChild(card);
    });
  }

  /* Mirrors mu_initials()/mu_avc() in PHP so a face looks the same either
     side. CRC-32 written out because JS has no built-in. */
  var crcTable = (function () {
    var t = [], c, n, k;
    for (n = 0; n < 256; n++) {
      c = n;
      for (k = 0; k < 8; k++) { c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1); }
      t[n] = c >>> 0;
    }
    return t;
  })();
  function crc32(str) {
    var c = 0xFFFFFFFF;
    for (var i = 0; i < str.length; i++) {
      c = crcTable[(c ^ str.charCodeAt(i)) & 0xFF] ^ (c >>> 8);
    }
    return (c ^ 0xFFFFFFFF) >>> 0;
  }
  function avc(name) { return 'av-c' + (crc32(name) % 10); }
  function initials(name) {
    var p = name.trim().split(/\s+/);
    return (p[0].charAt(0) + (p.length > 1 ? p[p.length - 1].charAt(0) : '')).toUpperCase();
  }

  if (sSearch) {
    sSearch.addEventListener('input', scanRender);
    sList.addEventListener('click', function (e) {
      var card = e.target.closest('.at-scancard');
      if (!card) { return; }
      var id = card.getAttribute('data-id');
      var m  = ROLL.filter(function (x) { return String(x.id) === String(id); })[0];

      marks[id] = 'present';
      var row = $('.at-row[data-row="' + id + '"]');
      if (row) { paintRow(row); }
      paintCounts();

      /* Animate the card out, then re-render so the list closes the gap. */
      card.classList.add('is-gone');
      setTimeout(function () { scanRender(); }, still ? 0 : 260);

      toast((m ? m.name : 'Member') + ' marked present', 'success', {
        label: 'Undo',
        run: function () {
          delete marks[id];
          if (row) { paintRow(row); }
          paintCounts(); scanRender();
        }
      });
    });
  }

  /* ═════════════════════ mode switching ═════════════════════ */

  var mode = 'members';
  var tabs = $$('.at-mode');

  /* `focusSearch` is false when the mode was reached with the arrow keys —
     stealing focus there would strand a keyboard user mid-tablist. */
  function setMode(next, focusSearch) {
    mode = next;
    tabs.forEach(function (t) {
      var on = t.getAttribute('data-mode') === next;
      t.classList.toggle('is-on', on);
      t.setAttribute('aria-selected', String(on));
      t.tabIndex = on ? 0 : -1;
    });
    $('#paneMembers').hidden = next !== 'members';
    $('#paneQuick').hidden   = next !== 'quick';
    $('#paneScan').hidden    = next !== 'scan';

    /* The header counters answer a different question in quick mode. */
    $$('[data-count-mode]').forEach(function (el) {
      el.hidden = (el.getAttribute('data-count-mode') === 'quick') !== (next === 'quick');
    });

    paintCounts();
    if (next === 'scan' && focusSearch) { setTimeout(function () { sSearch.focus(); }, 60); }
  }

  tabs.forEach(function (t) {
    t.addEventListener('click', function () { setMode(t.getAttribute('data-mode'), true); });
    /* Arrow keys move between tabs, as a tablist should. */
    t.addEventListener('keydown', function (e) {
      var i = tabs.indexOf(t), n = null;
      if (e.key === 'ArrowRight') { n = tabs[(i + 1) % tabs.length]; }
      if (e.key === 'ArrowLeft')  { n = tabs[(i - 1 + tabs.length) % tabs.length]; }
      if (e.key === 'Home')       { n = tabs[0]; }
      if (e.key === 'End')        { n = tabs[tabs.length - 1]; }
      if (!n) { return; }
      e.preventDefault();
      setMode(n.getAttribute('data-mode'), false);
      n.focus();
    });
  });

  /* ═════════════════════ visitors ═════════════════════ */

  var vList = $('#atVList'), vAdd = $('#atVAdd'), vCount = $('#atVisCount'), vTpl = $('#tplVisitor');

  function paintVisitors() {
    if (!vCount) { return; }
    var n = vList.children.length;
    vCount.textContent = n;
    vCount.hidden = n === 0;
  }

  if (vAdd) {
    vCount.hidden = true;
    vAdd.addEventListener('click', function () {
      var row = vTpl.content.firstElementChild.cloneNode(true);
      vList.appendChild(row);
      paintVisitors();
      $('[data-v="name"]', row).focus();
    });
    vList.addEventListener('click', function (e) {
      var del = e.target.closest('.at-vrow__del');
      if (!del) { return; }
      del.closest('.at-vrow').remove();
      paintVisitors();
      toast('Visitor removed', 'info');
    });
  }

  /* ═════════════════════ STEP 3 — the save flow ═════════════════════ */

  var modal = $('#modalSave'),
      svBody = $('#svBody'), svFoot = $('#svFoot'), svDone = $('#svDone'),
      svSummary = $('#svSummary'), svService = $('#svService'),
      svUnwrap = $('#svUnmarkedWrap'), svUnN = $('#svUnmarkedN');

  function openModal() {
    var t = tally();
    svService.textContent = (SERVICES[elSvc.value] || 'Service') + ' · ' + longDate(elDate.value);

    var rows;
    if (mode === 'quick') {
      rows = [['Head count', quickTotal(), 'ok']];
      $$('.at-stepper').forEach(function (s) {
        var v = parseInt($('.at-stepper__num', s).value, 10) || 0;
        rows.push([$('.at-stepper__label', s).textContent, v, 'plain']);
      });
      svUnwrap.hidden = true;
    } else {
      rows = [['Present', t.present, 'ok'], ['Absent', t.absent, 'no'], ['Excused', t.excused, 'ex']];
      if (vList && vList.children.length) { rows.push(['Visitors added', vList.children.length, 'plain']); }
      svUnwrap.hidden = t.unmarked === 0;
      svUnN.textContent = t.unmarked;
    }

    svSummary.innerHTML = '';
    rows.forEach(function (r) {
      var li = document.createElement('div');
      li.className = 'at-sum__row is-' + r[2];
      li.innerHTML = '<span></span><b></b>';
      $('span', li).textContent = r[0];
      $('b', li).textContent = Number(r[1]).toLocaleString();
      svSummary.appendChild(li);
    });

    svBody.hidden = false; svFoot.hidden = false; svDone.hidden = true;
    modal.hidden = false;
    document.body.style.overflow = 'hidden';
    $('[data-close]', modal).focus();
  }

  function closeModal() {
    modal.hidden = true;
    document.body.style.overflow = '';
  }

  var save = $('#atSave');
  if (save) { save.addEventListener('click', openModal); }

  var draft = $('#atDraft');
  if (draft) {
    draft.addEventListener('click', function () {
      toast('Draft saved — you can finish this register later', 'info');
    });
  }

  $('#svGo').addEventListener('click', function () {
    var t = tally();
    var text;

    if (mode === 'quick') {
      text = quickTotal().toLocaleString() + ' counted for ' + (SERVICES[elSvc.value] || 'the service') + '.';
    } else {
      var choice = ($('input[name="svUnmarked"]:checked') || {}).value;
      var present = t.present, absent = t.absent;
      if (t.unmarked && choice === 'absent') { absent += t.unmarked; }
      text = present + ' present and ' + absent + ' absent recorded for ' +
             (SERVICES[elSvc.value] || 'the service') + '.';
      if (t.unmarked && choice === 'leave') {
        text += ' ' + t.unmarked + ' left unmarked.';
      }
    }

    $('#svDoneText').textContent = text;
    svBody.hidden = true; svFoot.hidden = true; svDone.hidden = false;
    toast('Attendance saved', 'success');
  });

  $('#svAnother').addEventListener('click', function () {
    closeModal();
    marks = {};
    $$('.at-row', list).forEach(paintRow);
    $$('.at-stepper__num').forEach(function (i) { i.value = 0; });
    if (vList) { vList.innerHTML = ''; paintVisitors(); }
    if (sSearch) { sSearch.value = ''; scanRender(); }
    paintQuick(); paintCounts(); applyFilters();
    recorder.hidden = true; saveBar.hidden = true; syncSaveBar();
    $('#atSetup').scrollIntoView({ behavior: still ? 'auto' : 'smooth', block: 'start' });
    elDate.focus();
  });

  modal.addEventListener('click', function (e) {
    if (e.target.closest('[data-close]') || e.target === modal) { closeModal(); }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !modal.hidden) { closeModal(); }
  });

  /* ═════════════════════ save bar above the keyboard ═════════════════════ */
  /* On a phone the on-screen keyboard shrinks the visual viewport but not the
     layout viewport, so a bottom-fixed bar ends up underneath it. Following
     visualViewport keeps the bar where a thumb can reach it. */
  if (window.visualViewport) {
    var vv = window.visualViewport;
    var lift = function () {
      var gap = Math.max(0, (window.innerHeight - vv.height - vv.offsetTop));
      saveBar.style.transform = gap > 90 ? 'translateY(-' + gap + 'px)' : '';
    };
    vv.addEventListener('resize', lift);
    vv.addEventListener('scroll', lift);
  }

  /* ─────────────────────────────── first paint ─────────────────────────────── */
  paintCounts();
  paintQuick();
  applyFilters();
  paintVisitors();
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/../components/footer.php'; ?>

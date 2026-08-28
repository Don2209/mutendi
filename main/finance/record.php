<?php
/**
 * Mutendi CMS — Record Contribution.
 *
 * Where money received gets captured. A treasurer sits down after a service
 * and enters dozens of records in one go, so speed and accuracy beat every
 * other consideration — a mistyped amount here is a real problem.
 *
 * Three modes, because churches count money three different ways:
 *   Single Entry  one contribution at a time, carefully
 *   Batch Entry   a whole service's collection, keyboard-driven
 *   Quick Totals  aggregate only, for churches that do not track individuals
 *
 * Multi-currency throughout: every amount carries its own currency and the
 * USD equivalent is shown beside it rather than the figure being silently
 * converted away.
 *
 * UI only. Nothing is written anywhere; saving is visual.
 */

require __DIR__ . '/../includes/config.php';

/* ══════════════ DEMO ONLY — REMOVE BEFORE PRODUCTION ══════════════ */
$demo_role       = isset($_GET['role'], $demo_roles[$_GET['role']]) ? $_GET['role'] : 'treasurer';
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

    function mu_date(string $iso, string $fmt = 'd M Y'): string { return date($fmt, strtotime($iso)); }
}

$has_module = mu_mod('finance');
$can_add    = mu_can('finance.add');

/* ─────────────────────────── BRANCH AWARENESS ───────────────────────────
   Which branch the money is being recorded against. Entirely inert for a
   single church: is_multi_branch() is false, so no selector or readout.
   ──────────────────────────────────────────────────────────────────────── */
require_once __DIR__ . '/../components/branch-switcher.php';

$branch_aware   = is_multi_branch();
$branch_scoped  = $branch_aware && ($user['scope'] ?? 'organisation') === 'branch';
$viewing_all    = !$branch_aware || $current_branch === 'all' || $current_branch === null;
$branch_options = $branch_aware ? get_visible_branches() : [];

/* ───────────────────────────── THE PICKERS ─────────────────────────────
   LATER: SELECT id, name, member_no FROM members
           WHERE church_id = :church_id AND status = 'Active'
             AND (:branch_id IS NULL OR branch_id = :branch_id)
        ORDER BY surname, first; */
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

$roll = ($has_module && $can_add) ? $members_demo : [];
if ($branch_aware) {
    foreach ($roll as $i => $m) { $roll[$i]['_branch'] = mu_branch_for($m['member_no']); }
    if (!$viewing_all) {
        $roll = array_values(array_filter($roll, static function ($m) use ($current_branch) {
            return $m['_branch'] && (int) $m['_branch']['id'] === (int) $current_branch;
        }));
    }
}
usort($roll, static fn($a, $b) => strcmp($a['surname'] . $a['first'], $b['surname'] . $b['first']));

$default_currency = 'USD';
foreach ($currencies as $c) { if (!empty($c['is_default'])) { $default_currency = $c['code']; } }

$today = date('Y-m-d');

$page_title = 'Record Contribution';
require __DIR__ . '/../components/header.php';
?>

<div class="page<?= $has_module && $can_add ? ' has-savebar' : '' ?>">

  <!-- ═════════════════════════════ PAGE HEADER ═════════════════════════════ -->
  <header class="page__head">
    <div>
      <nav class="crumbs" aria-label="Breadcrumb">
        <a href="<?= $base_url ?>index.php">Dashboard</a>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span>Finance</span>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span aria-current="page">Record Contribution</span>
      </nav>
      <div class="title-row">
        <h1 class="page__title">Record Contribution</h1>
      </div>
      <p class="page__sub">Capture contributions received.</p>
    </div>

    <?php if ($has_module && $can_add): ?>
      <div class="page__actions">
        <a class="btn btn--ghost" href="<?= $base_url ?>finance/contributions.php">
          <i class="fa-solid fa-basket-shopping" aria-hidden="true"></i> View All Contributions
        </a>
        <button class="btn btn--ghost" type="button" data-open-import>
          <i class="fa-solid fa-file-import" aria-hidden="true"></i> Import
        </button>
      </div>
    <?php endif; ?>
  </header>


<?php if (!$has_module): ?>

  <section class="panel">
    <div class="empty">
      <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-plug-circle-xmark"></i></span>
      <h3>The Finance module is switched off</h3>
      <p>Your church's plan does not include contribution tracking. A church administrator can request it from the platform team.</p>
      <a class="btn" href="<?= $base_url ?>index.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Dashboard</a>
    </div>
  </section>

<?php elseif (!$can_add): ?>

  <section class="panel">
    <div class="empty">
      <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-lock"></i></span>
      <h3>You cannot record contributions</h3>
      <p>Capturing money received needs the <code>finance.add</code> permission. Ask a church administrator to grant it.</p>
      <a class="btn" href="<?= $base_url ?>index.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Dashboard</a>
    </div>
  </section>

<?php else: ?>

  <!-- ═══════════════════════ RUNNING SESSION COUNTER ═══════════════════════ -->
  <div class="sesbar" role="status" aria-live="polite">
    <span class="sesbar__icon" aria-hidden="true"><i class="fa-solid fa-receipt"></i></span>
    <p class="sesbar__text">
      <b><span data-ses-count>0</span> recorded this session</b>
      <span>&middot; <span data-ses-total>$0.00</span> total</span>
    </p>
    <button class="btn btn--ghost btn--sm" type="button" data-ses-reset hidden>
      <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Clear session
    </button>
  </div>


  <!-- ═════════════════════════════ MODE SWITCHER ═════════════════════════════ -->
  <div class="toolbar">
    <div class="svcviews" role="group" aria-label="Recording mode">
      <button class="svcview is-on" type="button" data-mode="single" aria-pressed="true">
        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> <span>Single Entry</span>
      </button>
      <button class="svcview" type="button" data-mode="batch" aria-pressed="false">
        <i class="fa-solid fa-table-list" aria-hidden="true"></i> <span>Batch Entry</span>
      </button>
      <button class="svcview" type="button" data-mode="totals" aria-pressed="false">
        <i class="fa-solid fa-calculator" aria-hidden="true"></i> <span>Quick Totals</span>
      </button>
    </div>
  </div>

  <!-- Batch and Quick Totals stay reachable on a phone, but say so. -->
  <div class="at-notice at-notice--info wide-only" role="note" hidden data-wide-note>
    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
    <div class="at-notice__body">
      <strong>This mode works best on a larger screen</strong>
      <span>It is a wide table built for a keyboard. Single Entry is the comfortable way to record on a phone.</span>
    </div>
  </div>


  <!-- ══════════════════════ MODE A — SINGLE ENTRY ══════════════════════ -->
  <div data-pane="single">
    <form class="entry" id="singleForm" novalidate>

      <!-- Summary of what is still missing, shown only after a failed save. -->
      <div class="at-notice at-notice--danger" id="singleErrors" role="alert" hidden>
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        <div class="at-notice__body">
          <strong>This contribution is not ready to save</strong>
          <ul class="errlist" data-error-list></ul>
        </div>
      </div>

      <fieldset class="entry__group">
        <legend>Contribution type <span class="req">*</span></legend>
        <div class="pickcards" id="typeCards" role="radiogroup" aria-label="Contribution type">
          <?php foreach ($contribution_types as $i => $t): ?>
            <button class="pickcard<?= $i === 0 ? ' is-on' : '' ?>" type="button"
                    role="radio" aria-checked="<?= $i === 0 ? 'true' : 'false' ?>"
                    data-type="<?= htmlspecialchars($t['key']) ?>"
                    data-requires-member="<?= $t['requires_member'] ? '1' : '0' ?>"
                    style="--c:<?= htmlspecialchars($t['colour']) ?>">
              <span class="pickcard__icon" aria-hidden="true"><i class="fa-solid <?= htmlspecialchars($t['icon']) ?>"></i></span>
              <span class="pickcard__label"><?= htmlspecialchars($t['name']) ?></span>
            </button>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <fieldset class="entry__group" data-member-block>
        <legend>Member <span class="req" data-member-req>*</span></legend>

        <label class="entry__toggle">
          <span class="switch">
            <input type="checkbox" id="anonToggle">
            <span class="switch__track" aria-hidden="true"></span>
          </span>
          <span>Anonymous / Non-member</span>
        </label>

        <div class="field" data-member-picker>
          <div class="search-field">
            <i class="fa-solid fa-magnifying-glass search-field__icon" aria-hidden="true"></i>
            <input class="input" type="search" id="memberSearch" autocomplete="off"
                   placeholder="Search by name or membership number&hellip;"
                   role="combobox" aria-expanded="false" aria-controls="memberResults" aria-autocomplete="list">
          </div>
          <div class="picklist" id="memberResults" role="listbox" aria-label="Matching members" hidden></div>

          <!-- The chosen member, once picked. -->
          <div class="picked" id="memberPicked" hidden>
            <span class="picked__av" data-picked-av aria-hidden="true"></span>
            <span class="picked__text">
              <b data-picked-name></b>
              <span data-picked-no></span>
            </span>
            <button class="iconbtn" type="button" id="memberClear" aria-label="Clear selected member">
              <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
          </div>
          <p class="err" data-err-member>Choose a member, or mark this as anonymous.</p>
        </div>
      </fieldset>

      <fieldset class="entry__group">
        <legend>Amount <span class="req">*</span></legend>
        <div class="amount">
          <input class="amount__input" type="text" id="amount" inputmode="decimal"
                 placeholder="0.00" autocomplete="off" aria-describedby="amountHint">
          <select class="amount__cur select" id="currency" aria-label="Currency">
            <?php foreach ($currencies as $c): ?>
              <option value="<?= htmlspecialchars($c['code']) ?>"
                      data-rate="<?= $c['exchange_rate_to_usd'] ?>"
                      data-symbol="<?= htmlspecialchars($c['symbol']) ?>"
                      <?= !empty($c['is_default']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['code']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <p class="amount__usd" id="amountHint" data-usd hidden></p>
        <p class="err" data-err-amount>Enter an amount greater than zero.</p>
        <p class="ok-tick" data-ok-amount><i class="fa-solid fa-check" aria-hidden="true"></i> Looks right</p>

        <!-- Same member, same amount, same day — worth a look, never blocked. -->
        <div class="dupwarn" data-dup hidden>
          <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
          <span>Possible duplicate: <b data-dup-text></b> was already recorded today.</span>
          <a href="<?= $base_url ?>finance/contributions.php" data-dup-review>Review</a>
        </div>
      </fieldset>

      <fieldset class="entry__group">
        <legend>Payment method <span class="req">*</span></legend>
        <div class="pickcards pickcards--sm" id="methodCards" role="radiogroup" aria-label="Payment method">
          <?php foreach ($payment_methods as $i => $m): ?>
            <button class="pickcard<?= $i === 0 ? ' is-on' : '' ?>" type="button"
                    role="radio" aria-checked="<?= $i === 0 ? 'true' : 'false' ?>"
                    data-method="<?= htmlspecialchars($m['key']) ?>"
                    data-needs-ref="<?= !empty($m['needs_reference']) ? '1' : '0' ?>"
                    data-ref-label="<?= htmlspecialchars($m['ref_label'] ?? 'Reference') ?>">
              <span class="pickcard__icon" aria-hidden="true"><i class="fa-solid <?= htmlspecialchars($m['icon']) ?>"></i></span>
              <span class="pickcard__label"><?= htmlspecialchars($m['name']) ?></span>
            </button>
          <?php endforeach; ?>
        </div>

        <!-- Only for the methods that actually carry a transaction id. -->
        <div class="field" data-ref-field hidden style="margin-top:14px">
          <label for="reference" data-ref-label-el>Reference</label>
          <input class="input" type="text" id="reference" autocomplete="off" placeholder="e.g. MP240827.1234.A56789">
          <p class="err" data-err-ref>Enter the reference for this payment.</p>
        </div>
      </fieldset>

      <fieldset class="entry__group">
        <legend>When</legend>
        <div class="form-grid">
          <div class="field">
            <label for="received">Date received <span class="req">*</span></label>
            <input class="input" type="date" id="received" value="<?= $today ?>" max="<?= $today ?>">
            <p class="err" data-err-received>Pick the date the money was received.</p>
          </div>

          <?php if (mu_mod('attendance')): ?>
            <div class="field">
              <label for="service">Service</label>
              <select class="select" id="service">
                <option value="">Not tied to a service</option>
                <?php foreach ($service_types_demo as $s): ?>
                  <option><?= htmlspecialchars($s['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>

          <?php if ($branch_aware): ?>
            <div class="field">
              <label for="branch"><?= htmlspecialchars(t('branch_singular')) ?></label>
              <?php if ($branch_scoped): ?>
                <?php $ob = get_branch($current_branch); ?>
                <div class="lockedfield" id="branch" role="note">
                  <i class="fa-solid fa-lock" aria-hidden="true"></i>
                  <?= htmlspecialchars($ob['name'] ?? current_branch_name()) ?>
                </div>
                <p class="hint">You record for your own <?= htmlspecialchars(strtolower(t('branch_singular'))) ?> only.</p>
              <?php else: ?>
                <select class="select" id="branch">
                  <?php foreach ($branch_options as $b): ?>
                    <option value="<?= (int) $b['id'] ?>" <?= (!$viewing_all && (int) $b['id'] === (int) $current_branch) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($b['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </fieldset>

      <?php if (mu_mod('projects')): ?>
        <fieldset class="entry__group">
          <legend>Designation</legend>
          <label class="entry__toggle">
            <span class="switch">
              <input type="checkbox" id="designateToggle">
              <span class="switch__track" aria-hidden="true"></span>
            </span>
            <span>Designate to a project</span>
          </label>
          <div class="field" data-project-field hidden style="margin-top:12px">
            <label for="project">Project</label>
            <select class="select" id="project">
              <?php foreach ($projects_demo as $p): ?>
                <?php $pct = $p['target'] > 0 ? round(($p['raised'] / $p['target']) * 100) : 0; ?>
                <option value="<?= (int) $p['id'] ?>">
                  <?= htmlspecialchars($p['name']) ?> — <?= $pct ?>% of $<?= number_format($p['target']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </fieldset>
      <?php endif; ?>

      <details class="at-coll entry__notes">
        <summary class="at-coll__head">
          <span class="at-coll__icon" aria-hidden="true"><i class="fa-regular fa-note-sticky"></i></span>
          <span class="at-coll__title">Notes</span>
          <span class="at-coll__opt">Optional</span>
          <i class="fa-solid fa-chevron-down at-coll__chev" aria-hidden="true"></i>
        </summary>
        <div class="at-coll__body">
          <div class="field">
            <label for="notes" class="u-sr">Notes</label>
            <textarea class="textarea" id="notes" rows="3"
                      placeholder="Anything worth remembering about this contribution&hellip;"></textarea>
          </div>
        </div>
      </details>
    </form>
  </div>


  <!-- ══════════════════════ MODE B — BATCH ENTRY ══════════════════════ -->
  <div data-pane="batch" hidden>

    <!-- Set once, applied to every row that does not override it. -->
    <section class="panel panel--pad batchhead">
      <div class="panel__head">
        <h2>Applies to the whole batch</h2>
        <span class="pill tone-grey">Set once</span>
      </div>
      <div class="filters__grid" style="padding:14px 16px 16px">
        <div class="field">
          <label for="bDate">Date received</label>
          <input class="input" type="date" id="bDate" value="<?= $today ?>" max="<?= $today ?>">
        </div>
        <?php if (mu_mod('attendance')): ?>
          <div class="field">
            <label for="bService">Service</label>
            <select class="select" id="bService">
              <option value="">Not tied to a service</option>
              <?php foreach ($service_types_demo as $s): ?><option><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>
        <?php if ($branch_aware): ?>
          <div class="field">
            <label for="bBranch"><?= htmlspecialchars(t('branch_singular')) ?></label>
            <?php if ($branch_scoped): ?>
              <?php $ob = get_branch($current_branch); ?>
              <div class="lockedfield" id="bBranch" role="note">
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                <?= htmlspecialchars($ob['name'] ?? current_branch_name()) ?>
              </div>
            <?php else: ?>
              <select class="select" id="bBranch">
                <?php foreach ($branch_options as $b): ?>
                  <option value="<?= (int) $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                <?php endforeach; ?>
              </select>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <div class="field">
          <label for="bType">Default type</label>
          <select class="select" id="bType">
            <?php foreach ($contribution_types as $t): ?>
              <option value="<?= htmlspecialchars($t['key']) ?>"><?= htmlspecialchars($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="bMethod">Default method</label>
          <select class="select" id="bMethod">
            <?php foreach ($payment_methods as $m): ?>
              <option value="<?= htmlspecialchars($m['key']) ?>"><?= htmlspecialchars($m['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="batchacts">
        <button class="btn btn--ghost btn--sm" type="button" id="applyType">
          <i class="fa-solid fa-arrow-down" aria-hidden="true"></i> Apply type to all
        </button>
        <button class="btn btn--ghost btn--sm" type="button" id="applyMethod">
          <i class="fa-solid fa-arrow-down" aria-hidden="true"></i> Apply method to all
        </button>
        <button class="btn btn--ghost btn--sm" type="button" id="clearEmpty">
          <i class="fa-solid fa-broom" aria-hidden="true"></i> Clear empty rows
        </button>
        <button class="btn btn--ghost btn--sm" type="button" id="openPaste">
          <i class="fa-regular fa-clipboard" aria-hidden="true"></i> Paste from spreadsheet
        </button>
      </div>
    </section>

    <section class="panel">
      <div class="panel__head">
        <h2>Contributions</h2>
        <span class="pill tone-brand"><span data-batch-count>0</span> rows</span>
      </div>
      <div class="dt-wrap batchwrap">
        <table class="dt batchtable">
          <thead>
            <tr>
              <th style="width:40px">#</th>
              <th style="min-width:200px">Member</th>
              <th style="min-width:150px">Type</th>
              <th style="min-width:130px">Amount</th>
              <th style="width:96px">Currency</th>
              <th style="min-width:140px">Method</th>
              <th style="min-width:150px">Reference</th>
              <th style="width:52px"><span class="u-sr">Remove</span></th>
            </tr>
          </thead>
          <tbody id="batchBody"></tbody>
        </table>
      </div>
      <div class="batchfoot">
        <button class="btn btn--ghost btn--sm" type="button" id="addRow">
          <i class="fa-solid fa-plus" aria-hidden="true"></i> Add row
        </button>
        <p class="hint" style="margin:0">
          Tab moves across, Enter moves down. Typing in the last row's reference adds another.
        </p>
      </div>
    </section>

    <!-- One row, cloned by script. -->
    <template id="tplBatchRow">
      <tr class="batchrow">
        <td class="num"></td>
        <td>
          <div class="cellpick">
            <input class="input input--cell" type="text" data-b="member" autocomplete="off"
                   placeholder="Search member&hellip;" list="rollList">
            <span class="cellpick__no" data-b-no></span>
          </div>
        </td>
        <td>
          <select class="select select--cell" data-b="type">
            <?php foreach ($contribution_types as $t): ?>
              <option value="<?= htmlspecialchars($t['key']) ?>"><?= htmlspecialchars($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </td>
        <td>
          <input class="input input--cell input--num" type="text" data-b="amount" inputmode="decimal" placeholder="0.00">
        </td>
        <td>
          <select class="select select--cell" data-b="currency">
            <?php foreach ($currencies as $c): ?>
              <option value="<?= htmlspecialchars($c['code']) ?>" data-rate="<?= $c['exchange_rate_to_usd'] ?>"
                      <?= !empty($c['is_default']) ? 'selected' : '' ?>><?= htmlspecialchars($c['code']) ?></option>
            <?php endforeach; ?>
          </select>
        </td>
        <td>
          <select class="select select--cell" data-b="method">
            <?php foreach ($payment_methods as $m): ?>
              <option value="<?= htmlspecialchars($m['key']) ?>" data-needs-ref="<?= !empty($m['needs_reference']) ? '1' : '0' ?>">
                <?= htmlspecialchars($m['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </td>
        <td>
          <input class="input input--cell" type="text" data-b="reference" autocomplete="off" placeholder="—">
        </td>
        <td>
          <button class="iconbtn batchrow__del" type="button" data-b-del aria-label="Remove this row">
            <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
          </button>
        </td>
      </tr>
    </template>

    <datalist id="rollList">
      <?php foreach ($roll as $m): ?>
        <option value="<?= htmlspecialchars($m['name']) ?>"><?= htmlspecialchars($m['member_no']) ?></option>
      <?php endforeach; ?>
    </datalist>
  </div>


  <!-- ══════════════════════ MODE C — QUICK TOTALS ══════════════════════ -->
  <div data-pane="totals" hidden>

    <section class="panel panel--pad">
      <div class="filters__grid" style="padding:16px">
        <div class="field">
          <label for="qDate">Date received</label>
          <input class="input" type="date" id="qDate" value="<?= $today ?>" max="<?= $today ?>">
        </div>
        <?php if (mu_mod('attendance')): ?>
          <div class="field">
            <label for="qService">Service</label>
            <select class="select" id="qService">
              <option value="">Not tied to a service</option>
              <?php foreach ($service_types_demo as $s): ?><option><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>
        <?php if ($branch_aware && !$branch_scoped): ?>
          <div class="field">
            <label for="qBranch"><?= htmlspecialchars(t('branch_singular')) ?></label>
            <select class="select" id="qBranch">
              <?php foreach ($branch_options as $b): ?>
                <option value="<?= (int) $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <div class="qtgrid">
      <?php foreach ($contribution_types as $t): ?>
        <div class="qtcard" data-qt="<?= htmlspecialchars($t['key']) ?>" style="--c:<?= htmlspecialchars($t['colour']) ?>">
          <div class="qtcard__head">
            <span class="qtcard__icon" aria-hidden="true"><i class="fa-solid <?= htmlspecialchars($t['icon']) ?>"></i></span>
            <span class="qtcard__label"><?= htmlspecialchars($t['name']) ?></span>
          </div>
          <div class="qtcard__row">
            <input class="input qtcard__amount" type="text" inputmode="decimal" data-qt-amount
                   placeholder="0.00" aria-label="<?= htmlspecialchars($t['name']) ?> amount">
            <select class="select qtcard__cur" data-qt-cur aria-label="<?= htmlspecialchars($t['name']) ?> currency">
              <?php foreach ($currencies as $c): ?>
                <option value="<?= htmlspecialchars($c['code']) ?>" data-rate="<?= $c['exchange_rate_to_usd'] ?>"
                        <?= !empty($c['is_default']) ? 'selected' : '' ?>><?= htmlspecialchars($c['code']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <p class="qtcard__usd" data-qt-usd hidden></p>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Counting the cash drawer note by note, summed into the offering total. -->
    <details class="at-coll" id="denomBox">
      <summary class="at-coll__head">
        <span class="at-coll__icon" aria-hidden="true"><i class="fa-solid fa-money-bill-wave"></i></span>
        <span class="at-coll__title">Count the cash</span>
        <span class="at-coll__opt">Optional</span>
        <i class="fa-solid fa-chevron-down at-coll__chev" aria-hidden="true"></i>
      </summary>
      <div class="at-coll__body">
        <p class="hint" style="margin:0 0 12px">
          Enter how many of each note you counted. The total drops into the Offering amount above.
        </p>
        <div class="denoms">
          <?php foreach ($cash_denominations as $d): ?>
            <label class="denom">
              <span class="denom__face"><?= htmlspecialchars($d['label']) ?></span>
              <input class="input denom__count" type="number" min="0" max="9999" step="1"
                     data-denom="<?= $d['value'] ?>" placeholder="0"
                     aria-label="Number of <?= htmlspecialchars($d['label']) ?> notes">
              <span class="denom__sub" data-denom-sub>$0.00</span>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="denoms__foot">
          <span>Counted</span>
          <b data-denom-total>$0.00</b>
          <button class="btn btn--ghost btn--sm" type="button" id="denomApply">
            <i class="fa-solid fa-arrow-up" aria-hidden="true"></i> Use as Offering
          </button>
        </div>
      </div>
    </details>

    <div class="field" style="margin-top:14px">
      <label for="qNotes">Notes</label>
      <textarea class="textarea" id="qNotes" rows="3" placeholder="Anything worth remembering about this collection&hellip;"></textarea>
    </div>
  </div>


  <!-- ═══════════════════════════════ SAVE BAR ═══════════════════════════════ -->
  <div class="at-savebar" id="saveBar">
    <p class="at-savebar__summary" role="status" aria-live="polite" data-savebar>Nothing entered yet</p>
    <div class="at-savebar__actions">
      <button class="btn btn--ghost" type="button" id="saveAnother">
        <i class="fa-solid fa-plus" aria-hidden="true"></i> <span>Save &amp; Add Another</span>
      </button>
      <button class="btn" type="button" id="saveFinish">
        <i class="fa-solid fa-check" aria-hidden="true"></i> <span>Save &amp; Finish</span>
      </button>
    </div>
  </div>

<?php endif; ?>

</div><!-- /.page -->


<?php if ($has_module && $can_add): ?>

<!-- ══════════════════════════ CONFIRM SAVE MODAL ══════════════════════════ -->
<div class="modal-scrim" id="modalConfirm" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="cfTitle">
    <header class="modal__head">
      <h2 id="cfTitle">Confirm this record</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>
    <div class="modal__body">
      <p class="modal__hint" data-cf-lead>You are about to record 1 contribution.</p>

      <p class="minilist__head">Totals</p>
      <div class="sumrows" data-cf-currencies></div>

      <p class="minilist__head">By type</p>
      <div class="sumrows" data-cf-types></div>
    </div>
    <footer class="modal__foot">
      <span class="modal__spacer"></span>
      <button class="btn btn--ghost" type="button" data-close>Back</button>
      <button class="btn" type="button" id="cfGo"><i class="fa-solid fa-check" aria-hidden="true"></i> Confirm &amp; Save</button>
    </footer>
  </div>
</div>


<!-- ══════════════════════════════ SUCCESS MODAL ══════════════════════════════ -->
<div class="modal-scrim" id="modalDone" hidden>
  <div class="modal modal--sm" role="dialog" aria-modal="true" aria-labelledby="okTitle">
    <div class="modal__body">
      <div class="at-done">
        <span class="at-done__tick" aria-hidden="true"><i class="fa-solid fa-check"></i></span>
        <h3 id="okTitle">Contribution recorded</h3>
        <p data-ok-text>The record has been saved.</p>
        <div class="sumrows" data-ok-summary style="width:100%;margin-top:6px"></div>
        <div class="at-done__actions">
          <button class="btn btn--ghost" type="button" id="okAnother">
            <i class="fa-solid fa-plus" aria-hidden="true"></i> Record Another
          </button>
          <button class="btn btn--ghost" type="button" data-toast="Receipt sent to printer">
            <i class="fa-solid fa-print" aria-hidden="true"></i> Print Receipt
          </button>
          <a class="btn" href="<?= $base_url ?>finance/contributions.php">
            <i class="fa-solid fa-basket-shopping" aria-hidden="true"></i> View Contributions
          </a>
        </div>
      </div>
    </div>
  </div>
</div>


<!-- ══════════════════════════════ IMPORT MODAL ══════════════════════════════ -->
<div class="modal-scrim" id="modalImport" hidden>
  <div class="modal modal--wide" role="dialog" aria-modal="true" aria-labelledby="imTitle">
    <header class="modal__head">
      <h2 id="imTitle">Import Contributions</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>

    <div class="modal__body">
      <ol class="steps" data-im-steps>
        <li class="steps__item is-on"><span class="steps__num">1</span> Upload</li>
        <li class="steps__item"><span class="steps__num">2</span> Map columns</li>
        <li class="steps__item"><span class="steps__num">3</span> Review</li>
      </ol>

      <div class="dropzone" id="importDrop" tabindex="0" role="button"
           aria-label="Drop a spreadsheet here, or press Enter to browse">
        <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
        <strong>Drop your spreadsheet here</strong>
        <span>or press Enter to browse &middot; CSV or XLSX, up to 5&nbsp;MB</span>
      </div>
      <p class="hint" style="text-align:center;margin-top:10px">
        <a href="#" data-toast="Template downloaded" style="color:var(--brand-600);font-weight:700">Download template</a>
      </p>

      <p class="minilist__head">Column mapping</p>
      <div class="dt-wrap">
        <table class="dt" style="font-size:12px">
          <thead><tr><th>Spreadsheet column</th><th>Maps to</th><th>Sample</th></tr></thead>
          <tbody>
            <tr><td>Name</td><td><select class="select select--cell"><option>Member name</option><option>Ignore</option></select></td><td class="tsub">Tendai Museka</td></tr>
            <tr><td>Amount</td><td><select class="select select--cell"><option>Amount</option><option>Ignore</option></select></td><td class="tsub">50.00</td></tr>
            <tr><td>Cur</td><td><select class="select select--cell"><option>Currency</option><option>Ignore</option></select></td><td class="tsub">USD</td></tr>
            <tr><td>Kind</td><td><select class="select select--cell"><option>Contribution type</option><option>Ignore</option></select></td><td class="tsub">Tithe</td></tr>
            <tr><td>Paid</td><td><select class="select select--cell"><option>Payment method</option><option>Ignore</option></select></td><td class="tsub">EcoCash</td></tr>
            <tr><td>Ref</td><td><select class="select select--cell"><option>Reference</option><option>Ignore</option></select></td><td class="tsub">MP240827.1234</td></tr>
          </tbody>
        </table>
      </div>

      <p class="minilist__head">Validation</p>
      <div class="valrow is-ok">
        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
        <span><b>142 rows</b> will import cleanly.</span>
      </div>
      <div class="valrow is-bad">
        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
        <span><b>6 rows</b> have errors and will be skipped.</span>
      </div>
      <ul class="valdetail">
        <li><b>Row 18</b> — amount is not a number (<code>fifty</code>)</li>
        <li><b>Row 44</b> — no member matches &ldquo;T. Musekha&rdquo;</li>
        <li><b>Row 61</b> — currency <code>ZWL</code> is not set up for this church</li>
        <li><b>Row 77, 78, 92</b> — amount is missing</li>
      </ul>
    </div>

    <footer class="modal__foot">
      <span class="modal__spacer"></span>
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <button class="btn" type="button" id="imGo"><i class="fa-solid fa-file-import" aria-hidden="true"></i> Import 142 rows</button>
    </footer>
  </div>
</div>


<!-- ══════════════════════════ PASTE FROM SPREADSHEET ══════════════════════════ -->
<div class="modal-scrim" id="modalPaste" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="psTitle">
    <header class="modal__head">
      <h2 id="psTitle">Paste from spreadsheet</h2>
      <button class="iconbtn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </header>
    <div class="modal__body">
      <p class="modal__hint">
        Copy the cells from your spreadsheet and paste below. One row per line, columns separated by tabs:
        <b>Member</b>, <b>Amount</b>, and optionally <b>Currency</b> and <b>Reference</b>.
      </p>
      <div class="field">
        <label for="pasteBox" class="u-sr">Rows to paste</label>
        <textarea class="textarea code" id="pasteBox" rows="9"
                  placeholder="Tendai Museka&#9;50&#9;USD&#10;Loveness Moyo&#9;120&#9;USD&#10;Melody Sibanda&#9;850&#9;ZWG"></textarea>
        <p class="hint" data-paste-count>Nothing pasted yet.</p>
      </div>
    </div>
    <footer class="modal__foot">
      <span class="modal__spacer"></span>
      <button class="btn btn--ghost" type="button" data-close>Cancel</button>
      <button class="btn" type="button" id="pasteGo" disabled>
        <i class="fa-solid fa-table-list" aria-hidden="true"></i> Add rows
      </button>
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

<?php if ($has_module && $can_add): ?>
<script>
/* Record Contribution — three modes, live currency conversion, inline
   validation, duplicate detection and a session counter. All client-side;
   nothing is written anywhere. */
(function () {
  'use strict';

  var still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var $  = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };

  var CURRENCIES = <?= json_encode(array_column($currencies, null, 'code'), JSON_UNESCAPED_UNICODE) ?>;
  var TYPES      = <?= json_encode(array_column($contribution_types, null, 'key'), JSON_UNESCAPED_UNICODE) ?>;
  var METHODS    = <?= json_encode(array_column($payment_methods, null, 'key'), JSON_UNESCAPED_UNICODE) ?>;
  var ROLL       = <?= json_encode(array_map(static function ($m) {
                        return ['id' => (int) $m['id'], 'name' => $m['name'], 'no' => $m['member_no']];
                     }, $roll), JSON_UNESCAPED_UNICODE) ?>;
  var TODAY_ALREADY = <?= json_encode($contributions_today_demo, JSON_UNESCAPED_UNICODE) ?>;
  var DEFAULT_CUR   = <?= json_encode($default_currency) ?>;

  /* ───────────────────────────── toasts ───────────────────────────── */
  var toasts = $('#toasts');
  function toast(msg, kind) {
    kind = kind || 'success';
    var icons = { success: 'fa-circle-check', error: 'fa-circle-exclamation', info: 'fa-circle-info' };
    var el = document.createElement('div');
    el.className = 'toast is-' + kind;
    el.innerHTML = '<i class="fa-solid ' + icons[kind] + ' toast__icon" aria-hidden="true"></i>' +
      '<div class="toast__body"><p class="toast__title"></p></div>' +
      '<button class="toast__close" type="button" aria-label="Dismiss"><i class="fa-solid fa-xmark"></i></button>';
    $('.toast__title', el).textContent = msg;
    toasts.appendChild(el);
    var kill = function () { el.classList.add('is-out'); setTimeout(function () { el.remove(); }, 250); };
    $('.toast__close', el).addEventListener('click', kill);
    setTimeout(kill, 3600);
  }
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-toast]');
    if (t) { e.preventDefault(); toast(t.getAttribute('data-toast')); }
  }, true);

  /* ═════════════════════════ money ═════════════════════════ */

  /* Parses what a human typed. Rejects negatives outright — a contribution
     is money in; a correction is a different transaction. */
  function parseAmount(raw) {
    if (raw === null || raw === undefined) { return NaN; }
    var s = String(raw).replace(/,/g, '').trim();
    if (s === '') { return NaN; }
    if (/^-/.test(s)) { return -1; }
    if (!/^\d*\.?\d*$/.test(s)) { return NaN; }
    return parseFloat(s);
  }

  function fmt(n, dp) {
    dp = dp === undefined ? 2 : dp;
    return n.toLocaleString(undefined, { minimumFractionDigits: dp, maximumFractionDigits: dp });
  }
  function usd(n) { return '$' + fmt(n); }

  function toUsd(amount, code) {
    var c = CURRENCIES[code];
    return c ? amount * c.exchange_rate_to_usd : amount;
  }

  /* Thousand separators as you type, without fighting the caret while the
     user is still inside the decimal part. */
  function liveFormat(input) {
    var raw = input.value.replace(/,/g, '');
    if (raw === '' || /[^0-9.]/.test(raw)) { return; }
    var parts = raw.split('.');
    var whole = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    input.value = parts.length > 1 ? whole + '.' + parts[1].slice(0, 2) : whole;
  }

  /* ═════════════════════════ the session counter ═════════════════════════ */

  var session = [];   /* every record saved since the page loaded */

  function sessionTotals() {
    var byCur = {}, usdTotal = 0;
    session.forEach(function (r) {
      byCur[r.currency] = (byCur[r.currency] || 0) + r.amount;
      usdTotal += toUsd(r.amount, r.currency);
    });
    return { byCur: byCur, usd: usdTotal };
  }

  function paintSession() {
    var t = sessionTotals();
    $('[data-ses-count]').textContent = session.length;
    $('[data-ses-total]').textContent = usd(t.usd);
    $('[data-ses-reset]').hidden = session.length === 0;
  }
  $('[data-ses-reset]').addEventListener('click', function () {
    session = []; paintSession(); toast('Session cleared', 'info');
  });

  /* ═════════════════════════ mode switching ═════════════════════════ */

  var mode = 'single', wideNote = $('[data-wide-note]');

  function setMode(next) {
    mode = next;
    $$('[data-mode]').forEach(function (b) {
      var on = b.getAttribute('data-mode') === next;
      b.classList.toggle('is-on', on);
      b.setAttribute('aria-pressed', String(on));
    });
    $$('[data-pane]').forEach(function (p) { p.hidden = p.getAttribute('data-pane') !== next; });
    /* The note only means anything on a narrow screen. */
    wideNote.hidden = !(next !== 'single' && window.matchMedia('(max-width: 767px)').matches);
    paintSaveBar();
  }
  $$('[data-mode]').forEach(function (b) {
    b.addEventListener('click', function () { setMode(b.getAttribute('data-mode')); });
  });
  window.addEventListener('resize', function () {
    wideNote.hidden = !(mode !== 'single' && window.matchMedia('(max-width: 767px)').matches);
  });

  /* ═════════════════════════ MODE A — SINGLE ENTRY ═════════════════════════ */

  var form = $('#singleForm'),
      amountEl = $('#amount'), curEl = $('#currency'),
      usdEl = $('[data-usd]'), dupEl = $('[data-dup]');

  var chosenType   = <?= json_encode($contribution_types[0]['key']) ?>;
  var chosenMethod = <?= json_encode($payment_methods[0]['key']) ?>;
  var chosenMember = null;
  var dirty = false;

  function markDirty() { dirty = true; }
  form.addEventListener('input', markDirty);
  form.addEventListener('change', markDirty);

  /* ── contribution type ── */
  function setType(btn) {
    $$('#typeCards .pickcard').forEach(function (b) {
      var on = b === btn;
      b.classList.toggle('is-on', on);
      b.setAttribute('aria-checked', String(on));
    });
    chosenType = btn.getAttribute('data-type');
    /* A tithe must be attributable; a loose offering need not be. */
    var needs = btn.getAttribute('data-requires-member') === '1';
    $('[data-member-req]').hidden = !needs;
    if (needs && $('#anonToggle').checked) {
      $('#anonToggle').checked = false;
      applyAnon();
      toast(TYPES[chosenType].name + ' has to be recorded against a member', 'info');
    }
    validateMember(false);
  }
  $('#typeCards').addEventListener('click', function (e) {
    var b = e.target.closest('.pickcard'); if (b) { setType(b); markDirty(); }
  });

  /* ── member picker ── */
  var searchEl = $('#memberSearch'), resultsEl = $('#memberResults'), pickedEl = $('#memberPicked');

  function renderResults(q) {
    resultsEl.innerHTML = '';
    if (!q) { resultsEl.hidden = true; searchEl.setAttribute('aria-expanded', 'false'); return; }
    var ql = q.toLowerCase();
    var hits = ROLL.filter(function (m) {
      return m.name.toLowerCase().indexOf(ql) !== -1 || m.no.toLowerCase().indexOf(ql) !== -1;
    }).slice(0, 8);

    if (!hits.length) {
      resultsEl.innerHTML = '<p class="picklist__none">No member matches that.</p>';
      resultsEl.hidden = false; return;
    }
    hits.forEach(function (m) {
      var b = document.createElement('button');
      b.type = 'button'; b.className = 'pickrow'; b.setAttribute('role', 'option');
      b.innerHTML = '<span class="av av--sm ' + avc(m.name) + '" aria-hidden="true">' + initials(m.name) + '</span>' +
        '<span class="pickrow__text"><b></b><span></span></span>';
      $('b', b).textContent = m.name;
      $('.pickrow__text span', b).textContent = m.no;
      b.addEventListener('click', function () { pickMember(m); });
      resultsEl.appendChild(b);
    });
    resultsEl.hidden = false;
    searchEl.setAttribute('aria-expanded', 'true');
  }

  function pickMember(m) {
    chosenMember = m;
    $('[data-picked-av]').className = 'picked__av av av--sm ' + avc(m.name);
    $('[data-picked-av]').textContent = initials(m.name);
    $('[data-picked-name]').textContent = m.name;
    $('[data-picked-no]').textContent = m.no;
    pickedEl.hidden = false;
    searchEl.value = ''; searchEl.closest('.search-field').hidden = true;
    resultsEl.hidden = true;
    validateMember(true); checkDuplicate(); markDirty();
  }
  $('#memberClear').addEventListener('click', function () {
    chosenMember = null;
    pickedEl.hidden = true;
    searchEl.closest('.search-field').hidden = false;
    searchEl.focus();
    validateMember(false); checkDuplicate();
  });
  searchEl.addEventListener('input', function () { renderResults(this.value.trim()); });
  searchEl.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowDown') { var f = $('.pickrow', resultsEl); if (f) { e.preventDefault(); f.focus(); } }
    if (e.key === 'Escape') { renderResults(''); }
  });

  /* ── anonymous toggle ── */
  function applyAnon() {
    var on = $('#anonToggle').checked;
    $('[data-member-picker]').hidden = on;
    if (on) { chosenMember = null; pickedEl.hidden = true; searchEl.closest('.search-field').hidden = false; }
    validateMember(false); checkDuplicate();
  }
  $('#anonToggle').addEventListener('change', function () {
    if (this.checked && TYPES[chosenType].requires_member) {
      this.checked = false;
      toast(TYPES[chosenType].name + ' has to be recorded against a member', 'error');
      return;
    }
    applyAnon(); markDirty();
  });

  /* ── amount and currency ── */
  function paintUsd() {
    var v = parseAmount(amountEl.value), code = curEl.value;
    if (isNaN(v) || v <= 0 || code === 'USD') { usdEl.hidden = true; return; }
    usdEl.textContent = '≈ ' + usd(toUsd(v, code)) + '  at ' + CURRENCIES[code].exchange_rate_to_usd + ' per ' + code;
    usdEl.hidden = false;
  }
  amountEl.addEventListener('input', function () { liveFormat(this); paintUsd(); checkDuplicate(); });
  amountEl.addEventListener('blur', function () { validateAmount(true); });
  curEl.addEventListener('change', function () { paintUsd(); checkDuplicate(); markDirty(); });

  /* ── payment method ── */
  $('#methodCards').addEventListener('click', function (e) {
    var b = e.target.closest('.pickcard'); if (!b) { return; }
    $$('#methodCards .pickcard').forEach(function (o) {
      var on = o === b;
      o.classList.toggle('is-on', on);
      o.setAttribute('aria-checked', String(on));
    });
    chosenMethod = b.getAttribute('data-method');
    var needs = b.getAttribute('data-needs-ref') === '1';
    $('[data-ref-field]').hidden = !needs;
    $('[data-ref-label-el]').textContent = b.getAttribute('data-ref-label');
    if (!needs) { $('#reference').value = ''; setFieldState($('#reference'), null); }
    markDirty();
  });

  /* ── project designation ── */
  var desig = $('#designateToggle');
  if (desig) {
    desig.addEventListener('change', function () {
      $('[data-project-field]').hidden = !this.checked; markDirty();
    });
  }

  /* ═══════════════ inline validation — never an alert box ═══════════════ */

  function setFieldState(el, ok, msg) {
    var field = el.closest('.field') || el.closest('fieldset');
    if (!field) { return; }
    field.classList.toggle('is-bad', ok === false);
    field.classList.toggle('is-good', ok === true);
    if (msg) {
      var err = $('.err', field);
      if (err) { err.textContent = msg; }
    }
  }

  function validateAmount(show) {
    var v = parseAmount(amountEl.value);
    var ok = !isNaN(v) && v > 0;
    if (show || amountEl.value !== '') {
      setFieldState(amountEl, ok, v === -1 ? 'An amount cannot be negative.' : 'Enter an amount greater than zero.');
    }
    return ok;
  }

  function validateMember(show) {
    var needs = TYPES[chosenType].requires_member;
    var anon = $('#anonToggle').checked;
    var ok = !needs || anon || !!chosenMember;
    var block = $('[data-member-block]');
    block.classList.toggle('is-bad', show === true && !ok);
    return ok;
  }

  function validateRef() {
    var needs = METHODS[chosenMethod].needs_reference;
    var ok = !needs || $('#reference').value.trim() !== '';
    return ok;
  }
  $('#reference').addEventListener('blur', function () {
    if (METHODS[chosenMethod].needs_reference) { setFieldState(this, this.value.trim() !== ''); }
  });

  /* Same member, same amount, same day. A warning, never a block — a member
     really can give twice in one service. */
  function checkDuplicate() {
    var v = parseAmount(amountEl.value);
    if (!chosenMember || isNaN(v) || v <= 0) { dupEl.hidden = true; return; }
    var hit = TODAY_ALREADY.filter(function (c) {
      return c.member_id === chosenMember.id &&
             Math.abs(c.amount - v) < 0.005 &&
             c.currency === curEl.value;
    })[0];
    if (!hit) { dupEl.hidden = true; return; }
    $('[data-dup-text]').textContent =
      CURRENCIES[hit.currency].symbol + fmt(hit.amount) + ' from ' + hit.member + ' (' + hit.ref + ')';
    dupEl.hidden = false;
  }

  /* ═════════════════════════ MODE B — BATCH ENTRY ═════════════════════════ */

  var body = $('#batchBody'), tpl = $('#tplBatchRow');

  function renumber() {
    $$('.batchrow', body).forEach(function (r, i) { $('.num', r).textContent = i + 1; });
    $('[data-batch-count]').textContent = $$('.batchrow', body).length;
  }

  function rowValues(tr) {
    var g = function (k) { var el = $('[data-b="' + k + '"]', tr); return el ? el.value.trim() : ''; };
    return {
      member: g('member'), type: g('type'), currency: g('currency'),
      method: g('method'), reference: g('reference'),
      amount: parseAmount(g('amount'))
    };
  }

  function addRow(prefill) {
    var tr = tpl.content.firstElementChild.cloneNode(true);
    $('[data-b="type"]', tr).value    = $('#bType').value;
    $('[data-b="method"]', tr).value  = $('#bMethod').value;
    if (prefill) {
      if (prefill.member)    { $('[data-b="member"]', tr).value = prefill.member; }
      if (prefill.amount)    { $('[data-b="amount"]', tr).value = prefill.amount; }
      if (prefill.currency && CURRENCIES[prefill.currency]) { $('[data-b="currency"]', tr).value = prefill.currency; }
      if (prefill.reference) { $('[data-b="reference"]', tr).value = prefill.reference; }
    }
    body.appendChild(tr);
    renumber(); paintBatch();
    return tr;
  }

  body.addEventListener('click', function (e) {
    var del = e.target.closest('[data-b-del]');
    if (!del) { return; }
    del.closest('.batchrow').remove();
    if (!$$('.batchrow', body).length) { addRow(); }
    renumber(); paintBatch();
  });

  body.addEventListener('input', function (e) {
    var el = e.target;
    if (el.getAttribute('data-b') === 'amount') { liveFormat(el); }
    if (el.getAttribute('data-b') === 'member') {
      /* show the membership number beside a name the moment it resolves */
      var hit = ROLL.filter(function (m) { return m.name.toLowerCase() === el.value.trim().toLowerCase(); })[0];
      $('[data-b-no]', el.closest('.batchrow')).textContent = hit ? hit.no : '';
    }
    paintBatch();
  });
  body.addEventListener('change', paintBatch);

  /* Enter moves down the column; the last cell of the last row grows the table. */
  body.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter') { return; }
    e.preventDefault();
    var cell = e.target.closest('[data-b]');
    if (!cell) { return; }
    var key = cell.getAttribute('data-b');
    var tr  = cell.closest('.batchrow');
    var next = tr.nextElementSibling;
    if (!next) { next = addRow(); }
    var target = $('[data-b="' + key + '"]', next);
    if (target) { target.focus(); if (target.select) { target.select(); } }
  });

  /* Typing into the last row's reference means the treasurer is still going. */
  body.addEventListener('input', function (e) {
    if (e.target.getAttribute('data-b') !== 'reference') { return; }
    var tr = e.target.closest('.batchrow');
    if (!tr.nextElementSibling && e.target.value.trim() !== '') { addRow(); }
  });

  function batchTotals() {
    var byCur = {}, usdTotal = 0, filled = 0;
    $$('.batchrow', body).forEach(function (tr) {
      var v = rowValues(tr);
      if (isNaN(v.amount) || v.amount <= 0) { return; }
      filled++;
      byCur[v.currency] = (byCur[v.currency] || 0) + v.amount;
      usdTotal += toUsd(v.amount, v.currency);
    });
    return { byCur: byCur, usd: usdTotal, filled: filled };
  }

  function paintBatch() {
    renumber();
    paintSaveBar();
  }

  $('#addRow').addEventListener('click', function () { var r = addRow(); $('[data-b="member"]', r).focus(); });

  $('#applyType').addEventListener('click', function () {
    var v = $('#bType').value;
    $$('[data-b="type"]', body).forEach(function (s) { s.value = v; });
    toast('Type applied to every row', 'info');
  });
  $('#applyMethod').addEventListener('click', function () {
    var v = $('#bMethod').value;
    $$('[data-b="method"]', body).forEach(function (s) { s.value = v; });
    toast('Method applied to every row', 'info');
  });
  $('#clearEmpty').addEventListener('click', function () {
    var removed = 0;
    $$('.batchrow', body).forEach(function (tr) {
      var v = rowValues(tr);
      if (!v.member && (isNaN(v.amount) || v.amount <= 0)) { tr.remove(); removed++; }
    });
    if (!$$('.batchrow', body).length) { addRow(); }
    renumber(); paintBatch();
    toast(removed ? removed + ' empty row' + (removed === 1 ? '' : 's') + ' cleared' : 'No empty rows', 'info');
  });

  /* ── paste from a spreadsheet ── */
  var pasteModal = $('#modalPaste'), pasteBox = $('#pasteBox');

  function parsePaste(text) {
    return text.split(/\r?\n/).map(function (line) { return line.split('\t'); })
      .filter(function (cols) { return cols.length && cols[0].trim() !== ''; })
      .map(function (cols) {
        return {
          member: (cols[0] || '').trim(),
          amount: (cols[1] || '').trim(),
          currency: (cols[2] || '').trim().toUpperCase(),
          reference: (cols[3] || '').trim()
        };
      });
  }
  pasteBox.addEventListener('input', function () {
    var rows = parsePaste(this.value);
    $('[data-paste-count]').textContent = rows.length
      ? rows.length + ' row' + (rows.length === 1 ? '' : 's') + ' ready to add.'
      : 'Nothing pasted yet.';
    $('#pasteGo').disabled = rows.length === 0;
  });
  $('#openPaste').addEventListener('click', function () {
    pasteBox.value = ''; $('[data-paste-count]').textContent = 'Nothing pasted yet.';
    $('#pasteGo').disabled = true;
    openModal(pasteModal);
  });
  $('#pasteGo').addEventListener('click', function () {
    var rows = parsePaste(pasteBox.value);
    rows.forEach(function (r) { addRow(r); });
    closeModal(pasteModal);
    toast(rows.length + ' row' + (rows.length === 1 ? '' : 's') + ' added', 'success');
  });

  /* ═════════════════════════ MODE C — QUICK TOTALS ═════════════════════════ */

  function qtTotals() {
    var byCur = {}, usdTotal = 0, byType = {};
    $$('.qtcard').forEach(function (card) {
      var v = parseAmount($('[data-qt-amount]', card).value);
      if (isNaN(v) || v <= 0) { return; }
      var code = $('[data-qt-cur]', card).value;
      byCur[code] = (byCur[code] || 0) + v;
      usdTotal += toUsd(v, code);
      byType[card.getAttribute('data-qt')] = (byType[card.getAttribute('data-qt')] || 0) + toUsd(v, code);
    });
    return { byCur: byCur, usd: usdTotal, byType: byType };
  }

  $$('.qtcard').forEach(function (card) {
    var amt = $('[data-qt-amount]', card), cur = $('[data-qt-cur]', card), out = $('[data-qt-usd]', card);
    function paint() {
      var v = parseAmount(amt.value), code = cur.value;
      if (isNaN(v) || v <= 0 || code === 'USD') { out.hidden = true; }
      else { out.textContent = '≈ ' + usd(toUsd(v, code)); out.hidden = false; }
      paintSaveBar();
    }
    amt.addEventListener('input', function () { liveFormat(this); paint(); markDirty(); });
    cur.addEventListener('change', function () { paint(); markDirty(); });
  });

  /* ── the cash counter ── */
  function paintDenoms() {
    var total = 0;
    $$('.denom').forEach(function (d) {
      var input = $('.denom__count', d);
      var n = parseInt(input.value, 10);
      if (isNaN(n) || n < 0) { n = 0; }
      var sub = n * parseFloat(input.getAttribute('data-denom'));
      total += sub;
      $('[data-denom-sub]', d).textContent = usd(sub);
    });
    $('[data-denom-total]').textContent = usd(total);
    return total;
  }
  $$('.denom__count').forEach(function (i) { i.addEventListener('input', function () { paintDenoms(); markDirty(); }); });
  $('#denomApply').addEventListener('click', function () {
    var total = paintDenoms();
    if (total <= 0) { toast('Nothing counted yet', 'info'); return; }
    var card = $('.qtcard[data-qt="offering"]');
    $('[data-qt-amount]', card).value = fmt(total);
    $('[data-qt-cur]', card).value = 'USD';
    $('[data-qt-usd]', card).hidden = true;
    paintSaveBar();
    toast(usd(total) + ' entered as Offering', 'success');
  });

  /* ═════════════════════════ the save bar ═════════════════════════ */

  function currentTotals() {
    if (mode === 'batch')  { return batchTotals(); }
    if (mode === 'totals') { return qtTotals(); }
    var v = parseAmount(amountEl.value);
    if (isNaN(v) || v <= 0) { return { byCur: {}, usd: 0, filled: 0 }; }
    var o = {}; o[curEl.value] = v;
    return { byCur: o, usd: toUsd(v, curEl.value), filled: 1 };
  }

  function paintSaveBar() {
    var t = currentTotals();
    var parts = Object.keys(t.byCur).map(function (c) {
      return CURRENCIES[c].symbol + fmt(t.byCur[c]) + ' ' + c;
    });
    var n = mode === 'batch' ? t.filled : (mode === 'totals' ? Object.keys(t.byType || {}).length : t.filled);
    $('[data-savebar]').textContent = parts.length
      ? n + (mode === 'totals' ? ' type' : ' record') + (n === 1 ? '' : 's') + ' · ' + parts.join(' · ') +
        (Object.keys(t.byCur).length > 1 || !t.byCur.USD ? '  =  ' + usd(t.usd) : '')
      : 'Nothing entered yet';
  }

  /* ═════════════════════════ modals ═════════════════════════ */

  function openModal(m) { m.hidden = false; document.body.style.overflow = 'hidden'; var c = $('[data-close]', m); if (c) { c.focus(); } }
  function closeModal(m) { m.hidden = true; document.body.style.overflow = ''; }

  document.addEventListener('click', function (e) {
    var cl = e.target.closest('[data-close]');
    if (cl) { closeModal(cl.closest('.modal-scrim')); return; }
    if (e.target.classList.contains('modal-scrim')) { closeModal(e.target); }
  }, true);

  $$('[data-open-import]').forEach(function (b) {
    b.addEventListener('click', function () { openModal($('#modalImport')); });
  });
  $('#imGo').addEventListener('click', function () {
    closeModal($('#modalImport'));
    toast('Import queued — 142 rows', 'success');
  });
  $('#importDrop').addEventListener('click', function () { toast('File browser opened', 'info'); });
  $('#importDrop').addEventListener('keydown', function (e) {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toast('File browser opened', 'info'); }
  });

  /* ── confirm, then succeed ── */
  var pendingFinish = false;

  function sumRows(target, entries) {
    target.innerHTML = '';
    entries.forEach(function (e) {
      var row = document.createElement('div');
      row.className = 'sumrow';
      row.innerHTML = '<span></span><b></b>';
      $('span', row).textContent = e[0];
      $('b', row).textContent = e[1];
      target.appendChild(row);
    });
    if (!entries.length) {
      target.innerHTML = '<p class="sumrow__none">Nothing to show.</p>';
    }
  }

  function requestSave(finish) {
    var problems = [];

    if (mode === 'single') {
      if (!validateAmount(true)) { problems.push('Amount must be a number greater than zero.'); }
      if (!validateMember(true)) { problems.push(TYPES[chosenType].name + ' has to be recorded against a member.'); }
      if (!validateRef())        { problems.push(METHODS[chosenMethod].name + ' needs a reference.'); }
      if (!$('#received').value) { problems.push('Pick the date the money was received.'); }
    } else {
      var t = currentTotals();
      if (!t.usd) { problems.push('Enter at least one amount before saving.'); }
    }

    var box = $('#singleErrors');
    if (problems.length) {
      if (mode === 'single') {
        var ul = $('[data-error-list]');
        ul.innerHTML = '';
        problems.forEach(function (p) { var li = document.createElement('li'); li.textContent = p; ul.appendChild(li); });
        box.hidden = false;
        box.scrollIntoView({ behavior: still ? 'auto' : 'smooth', block: 'center' });
      } else {
        toast(problems[0], 'error');
      }
      return;
    }
    if (box) { box.hidden = true; }

    pendingFinish = finish;
    var tot = currentTotals();

    $('[data-cf-lead]').textContent = mode === 'batch'
      ? 'You are about to record ' + tot.filled + ' contribution' + (tot.filled === 1 ? '' : 's') + '.'
      : (mode === 'totals'
          ? 'You are about to record the totals for this collection.'
          : 'You are about to record 1 contribution.');

    sumRows($('[data-cf-currencies]'), Object.keys(tot.byCur).map(function (c) {
      return [CURRENCIES[c].name + ' (' + c + ')', CURRENCIES[c].symbol + fmt(tot.byCur[c])];
    }).concat([['Total in USD', usd(tot.usd)]]));

    var types = {};
    if (mode === 'single') { types[TYPES[chosenType].name] = toUsd(parseAmount(amountEl.value), curEl.value); }
    else if (mode === 'totals') {
      Object.keys(tot.byType).forEach(function (k) { types[TYPES[k].name] = tot.byType[k]; });
    } else {
      $$('.batchrow', body).forEach(function (tr) {
        var v = rowValues(tr);
        if (isNaN(v.amount) || v.amount <= 0) { return; }
        var n = TYPES[v.type].name;
        types[n] = (types[n] || 0) + toUsd(v.amount, v.currency);
      });
    }
    sumRows($('[data-cf-types]'), Object.keys(types).map(function (k) { return [k, usd(types[k])]; }));

    openModal($('#modalConfirm'));
  }

  $('#cfGo').addEventListener('click', function () {
    var tot = currentTotals();

    /* Record into the session so the counter is honest. */
    if (mode === 'single') {
      session.push({ amount: parseAmount(amountEl.value), currency: curEl.value, type: chosenType });
    } else if (mode === 'batch') {
      $$('.batchrow', body).forEach(function (tr) {
        var v = rowValues(tr);
        if (!isNaN(v.amount) && v.amount > 0) { session.push({ amount: v.amount, currency: v.currency, type: v.type }); }
      });
    } else {
      $$('.qtcard').forEach(function (card) {
        var v = parseAmount($('[data-qt-amount]', card).value);
        if (!isNaN(v) && v > 0) {
          session.push({ amount: v, currency: $('[data-qt-cur]', card).value, type: card.getAttribute('data-qt') });
        }
      });
    }
    paintSession();
    dirty = false;
    closeModal($('#modalConfirm'));

    if (pendingFinish) {
      $('[data-ok-text]').textContent = mode === 'batch'
        ? tot.filled + ' contributions recorded.'
        : 'The record has been saved.';
      sumRows($('[data-ok-summary]'), Object.keys(tot.byCur).map(function (c) {
        return [c, CURRENCIES[c].symbol + fmt(tot.byCur[c])];
      }).concat([['Total in USD', usd(tot.usd)]]));
      openModal($('#modalDone'));
    } else {
      resetForCurrentMode();
      toast('Saved — ' + usd(tot.usd) + ' recorded', 'success');
    }
  });

  $('#okAnother').addEventListener('click', function () {
    closeModal($('#modalDone'));
    resetForCurrentMode();
  });

  function resetForCurrentMode() {
    if (mode === 'single') {
      amountEl.value = ''; $('#reference').value = ''; $('#notes').value = '';
      usdEl.hidden = true; dupEl.hidden = true;
      chosenMember = null; pickedEl.hidden = true;
      searchEl.value = ''; searchEl.closest('.search-field').hidden = false;
      $$('.field, fieldset').forEach(function (f) { f.classList.remove('is-bad', 'is-good'); });
      amountEl.focus();
    } else if (mode === 'batch') {
      body.innerHTML = ''; addRow(); addRow(); addRow();
    } else {
      $$('[data-qt-amount]').forEach(function (i) { i.value = ''; });
      $$('[data-qt-usd]').forEach(function (o) { o.hidden = true; });
      $$('.denom__count').forEach(function (i) { i.value = ''; });
      paintDenoms();
    }
    dirty = false;
    paintSaveBar();
  }

  $('#saveAnother').addEventListener('click', function () { requestSave(false); });
  $('#saveFinish').addEventListener('click', function () { requestSave(true); });

  /* ── leaving with something unsaved ── */
  window.addEventListener('beforeunload', function (e) {
    if (!dirty) { return; }
    e.preventDefault();
    e.returnValue = '';
  });

  /* ═════════════════ avatar helpers, mirroring PHP ═════════════════ */
  /* Real CRC-32, so a face is the same colour on both sides. */
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
    for (var i = 0; i < str.length; i++) { c = crcTable[(c ^ str.charCodeAt(i)) & 0xFF] ^ (c >>> 8); }
    return (c ^ 0xFFFFFFFF) >>> 0;
  }
  function avc(name) { return 'av-c' + (crc32(name) % 10); }
  function initials(name) {
    var p = name.trim().split(/\s+/);
    return (p[0].charAt(0) + (p.length > 1 ? p[p.length - 1].charAt(0) : '')).toUpperCase();
  }

  /* ── the save bar's own footprint ──
     The toast stack and the demo switcher both sit bottom-right, exactly
     where the save bar's buttons are. They are lifted clear of it by the
     bar's measured height, so neither can swallow a tap meant for Save. */
  function syncSaveBar() {
    var bar = $('#saveBar');
    document.body.classList.add('has-savebar-open');
    document.documentElement.style.setProperty(
      '--savebar-h', bar.getBoundingClientRect().height + 'px');
  }
  if (window.ResizeObserver) { new ResizeObserver(syncSaveBar).observe($('#saveBar')); }
  window.addEventListener('resize', syncSaveBar);
  syncSaveBar();

  /* ── the save bar rides above the on-screen keyboard ── */
  if (window.visualViewport) {
    var vv = window.visualViewport, bar = $('#saveBar');
    var lift = function () {
      var gap = Math.max(0, window.innerHeight - vv.height - vv.offsetTop);
      bar.style.transform = gap > 90 ? 'translateY(-' + gap + 'px)' : '';
    };
    vv.addEventListener('resize', lift);
    vv.addEventListener('scroll', lift);
  }

  /* ────────────────────────── escape key ────────────────────────── */
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') { return; }
    $$('.modal-scrim').forEach(function (m) { if (!m.hidden) { closeModal(m); } });
  });

  /* ─────────────────────────── first paint ─────────────────────────── */
  addRow(); addRow(); addRow();
  paintDenoms();
  paintSession();
  paintSaveBar();
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/../components/footer.php'; ?>

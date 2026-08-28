<?php
/**
 * Mutendi CMS — Add Member.
 *
 * A five-step wizard with a live preview of the member card being built.
 * UI only: nothing is submitted or stored. Validation is client-side and
 * exists to demonstrate the interaction, not to protect any data.
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

if (!function_exists('mu_can')) {
    function mu_can(string $perm): bool { global $permissions; return in_array($perm, $permissions, true); }
    function mu_mod(string $mod): bool  { global $enabled_modules; return in_array($mod, $enabled_modules, true); }
}

/* Without permission to add, the form is not rendered at all. */
$may_add = mu_can('members.add');

/* ─────────────────────────── BRANCH AWARENESS ───────────────────────────
   Resolves which branch is in view (the top bar's switcher sets ?branch=) and
   scopes this page's data to it. Every addition below is inert for a single
   church: is_multi_branch() is false, so no column, chip, filter or toggle is
   rendered and the page behaves exactly as it did before.
   ──────────────────────────────────────────────────────────────────────── */
require_once __DIR__ . '/../components/branch-switcher.php';

$branch_aware   = is_multi_branch();
$viewing_all    = !$branch_aware || $current_branch === 'all' || $current_branch === null;
$show_branch    = $branch_aware && $viewing_all;      /* column, chip and filter */
$branch_options = $branch_aware ? get_visible_branches() : [];

if (!function_exists('mu_branch_for')) {
    /**
     * Which branch a demo record belongs to. Deterministic from the record's
     * own key, so a person or a group never hops between branches on reload.
     * LATER: the row carries its own branch_id and this helper disappears.
     */
    function mu_branch_for(string $key): ?array {
        static $pool = null;
        if ($pool === null) { $pool = get_visible_branches(); }
        if (!$pool) { return null; }
        return $pool[crc32($key) % count($pool)];
    }

    /** One colour per group, so branches in the same group read together. */
    function mu_branch_tone(array $b): string {
        static $tones = [];
        static $pool = ['var(--info)', 'var(--brand-500)', '#0F766E', 'var(--warn)', '#6D28D9'];
        $g = $b['group_name'] ?? '';
        if (!isset($tones[$g])) { $tones[$g] = $pool[count($tones) % count($pool)]; }
        return $tones[$g];
    }

    /** The small coloured chip naming a record's branch. */
    function mu_branch_chip(?array $b): string {
        if (!$b) { return ''; }
        return '<span class="bchip" title="' . htmlspecialchars($b['name']) . '">'
             . '<span class="bchip__dot" style="background:' . mu_branch_tone($b) . '" aria-hidden="true"></span>'
             . htmlspecialchars($b['name']) . '</span>';
    }

    /**
     * Scales an organisation-wide headline figure to the selected branch, by
     * that branch's share of the roll.
     * LATER: the figure arrives from a query already scoped to :branch_id.
     */
    function mu_branch_share($orgValue) {
        global $current_branch, $organisation;
        if ($current_branch === 'all' || $current_branch === null) { return $orgValue; }
        $b = get_branch($current_branch);
        if (!$b) { return $orgValue; }
        $total = max(1, (int) ($organisation['total_members'] ?? 1));
        return $orgValue * ((int) $b['members_count'] / $total);
    }
}


/* LATER: SELECT MAX(member_no) ... to continue the church's own sequence. */
$next_member_no = 'MCP-' . date('Y') . '-' . str_pad((string) random_int(140, 999), 4, '0', STR_PAD_LEFT);

$page_title = 'Add Member';
require __DIR__ . '/../components/header.php';
?>

<div class="page">

  <header class="page__head">
    <div>
      <nav class="crumbs" aria-label="Breadcrumb">
        <a href="<?= $base_url ?>index.php">Dashboard</a>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span>People</span>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <a href="<?= $base_url ?>members/all.php">Members</a>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span aria-current="page">Add</span>
      </nav>
      <h1 class="page__title">Add New Member</h1>
      <p class="page__sub">Register a new member of your church.</p>
    </div>
    <?php if ($may_add): ?>
      <div class="page__actions">
        <a class="btn btn--ghost" href="<?= $base_url ?>members/all.php" id="cancelBtn">Cancel</a>
        <button class="btn btn--ghost" type="button" data-toast="Draft saved"><i class="fa-regular fa-floppy-disk" aria-hidden="true"></i> Save as draft</button>
        <button class="btn" type="button" id="saveTop"><i class="fa-solid fa-check" aria-hidden="true"></i> Save Member</button>
      </div>
    <?php endif; ?>
  </header>

<?php if (!$may_add): ?>

  <section class="panel">
    <div class="empty">
      <span class="empty__icon" aria-hidden="true"><i class="fa-solid fa-lock"></i></span>
      <h3>You cannot add members</h3>
      <p>Your role does not include the <strong>members.add</strong> permission. Ask a church administrator if you need it.</p>
      <a class="btn" href="<?= $base_url ?>members/all.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Members</a>
    </div>
  </section>

<?php else: ?>

  <!-- ═══════════════════════════ STEPPER ═══════════════════════════ -->
  <section class="panel" style="padding:18px;margin-bottom:16px">
    <div class="stepper" id="stepper">
      <?php
        $steps = ['Personal', 'Contact', 'Church', 'Family', 'Review'];
        foreach ($steps as $i => $label):
          $n = $i + 1;
      ?>
        <div class="stepper__item<?= $n === 1 ? ' is-on' : '' ?>" data-step-item="<?= $n ?>">
          <button class="stepper__btn" type="button" data-goto-step="<?= $n ?>" <?= $n === 1 ? '' : 'disabled' ?>>
            <span class="stepper__num"><?= $n ?></span>
            <span class="stepper__label"><?= $label ?></span>
          </button>
          <?php if ($n < count($steps)): ?><span class="stepper__line"></span><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="stepper-mobile">
      <p class="stepper-mobile__label"><span>Step <span data-step-now>1</span> of 5</span><span data-step-name>Personal</span></p>
      <div class="progressbar"><span style="width:20%" data-step-bar></span></div>
    </div>
  </section>

  <div class="form-layout" style="display:grid;grid-template-columns:2fr 1fr;gap:16px;align-items:start">

    <!-- ═══════════════════════════ THE FORM ═══════════════════════════ -->
    <section class="panel" style="padding:20px">
      <div class="err-summary" id="errSummary" role="alert">
        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
        <div><strong>Please complete these first</strong><ul id="errList"></ul></div>
      </div>

      <!-- ─────────── STEP 1 ─────────── -->
      <div class="wizard-step" data-step="1">
        <h2 style="color:var(--ink);font-size:15px;font-weight:800;margin-bottom:4px">Personal Information</h2>
        <p style="color:var(--muted);font-size:12.5px;margin-bottom:18px">Who is joining the church?</p>

        <label class="avatar-drop" for="photo" style="margin-bottom:18px">
          <i class="fa-solid fa-camera" aria-hidden="true"></i>
          <span>Drop a photo or click to upload</span>
          <input type="file" id="photo" accept="image/*" hidden>
        </label>

        <div class="form-grid">
          <div class="field">
            <label for="firstName">First name <span class="req" aria-hidden="true">*</span></label>
            <input class="input" id="firstName" data-req data-preview="first" autocomplete="given-name">
            <p class="err">First name is required.</p><p class="ok-tick"><i class="fa-solid fa-check"></i> Looks good</p>
          </div>
          <div class="field">
            <label for="middleName">Middle name</label>
            <input class="input" id="middleName" autocomplete="additional-name">
          </div>
          <div class="field">
            <label for="surname">Surname <span class="req" aria-hidden="true">*</span></label>
            <input class="input" id="surname" data-req data-preview="surname" autocomplete="family-name">
            <p class="err">Surname is required.</p><p class="ok-tick"><i class="fa-solid fa-check"></i> Looks good</p>
          </div>
          <div class="field">
            <label for="dob">Date of birth <span class="req" aria-hidden="true">*</span></label>
            <input class="input" type="date" id="dob" data-req>
            <p class="hint" id="ageOut">Age will be calculated automatically.</p>
            <p class="err">Date of birth is required.</p>
          </div>

          <div class="field col-2">
            <label>Gender <span class="req" aria-hidden="true">*</span></label>
            <div class="seg" role="group" aria-label="Gender">
              <button type="button" data-gender="Male"   aria-pressed="false"><i class="fa-solid fa-mars" aria-hidden="true"></i> Male</button>
              <button type="button" data-gender="Female" aria-pressed="false"><i class="fa-solid fa-venus" aria-hidden="true"></i> Female</button>
            </div>
            <p class="err">Please choose a gender.</p>
          </div>

          <div class="field">
            <label for="marital">Marital status</label>
            <select class="select" id="marital" data-preview="marital">
              <option value="">Select&hellip;</option><option>Single</option><option>Married</option><option>Widowed</option><option>Divorced</option>
            </select>
          </div>
          <div class="field">
            <label for="natId">National ID</label>
            <input class="input" id="natId" placeholder="63-123456-A-42">
          </div>
          <div class="field">
            <label for="occupation">Occupation</label>
            <input class="input" id="occupation" list="occList" data-preview="occupation">
            <datalist id="occList">
              <?php foreach ($occupations_demo as $o): ?><option value="<?= htmlspecialchars($o) ?>"></option><?php endforeach; ?>
            </datalist>
          </div>
          <div class="field">
            <label for="nationality">Nationality</label>
            <input class="input" id="nationality" value="Zimbabwean">
          </div>
        </div>
      </div>

      <!-- ─────────── STEP 2 ─────────── -->
      <div class="wizard-step" data-step="2" hidden>
        <h2 style="color:var(--ink);font-size:15px;font-weight:800;margin-bottom:4px">Contact Details</h2>
        <p style="color:var(--muted);font-size:12.5px;margin-bottom:18px">How do we reach them?</p>

        <div class="form-grid">
          <div class="field">
            <label for="phone">Phone <span class="req" aria-hidden="true">*</span></label>
            <div class="phone-input">
              <span class="phone-input__prefix"><span aria-hidden="true">🇿🇼</span> +263</span>
              <input class="input" id="phone" data-req data-phone data-preview="phone" inputmode="tel" placeholder="77 123 4567">
            </div>
            <p class="err">A phone number is required.</p><p class="ok-tick"><i class="fa-solid fa-check"></i> Looks good</p>
          </div>
          <div class="field">
            <label for="phone2">Alternative phone</label>
            <div class="phone-input">
              <span class="phone-input__prefix"><span aria-hidden="true">🇿🇼</span> +263</span>
              <input class="input" id="phone2" data-phone inputmode="tel" placeholder="71 234 5678">
            </div>
          </div>
          <div class="field">
            <label for="email">Email</label>
            <input class="input" type="email" id="email" data-email placeholder="name@example.com">
            <p class="err">That email address does not look right.</p><p class="ok-tick"><i class="fa-solid fa-check"></i> Looks good</p>
          </div>
          <div class="field">
            <label for="whatsapp">WhatsApp number</label>
            <div class="phone-input">
              <span class="phone-input__prefix"><span aria-hidden="true">🇿🇼</span> +263</span>
              <input class="input" id="whatsapp" data-phone inputmode="tel">
            </div>
            <label style="display:flex;align-items:center;gap:8px;margin-top:8px;color:var(--muted);font-size:12px;font-weight:600">
              <input class="check" type="checkbox" id="sameAsPhone"> Same as phone
            </label>
          </div>

          <div class="field col-2">
            <label for="address">Physical address</label>
            <input class="input" id="address" placeholder="House number and street">
          </div>
          <div class="field">
            <label for="suburb">Suburb</label>
            <input class="input" id="suburb" list="subList" data-preview="suburb">
            <datalist id="subList">
              <?php foreach ($suburbs_demo as $s): ?><option value="<?= htmlspecialchars($s) ?>"></option><?php endforeach; ?>
            </datalist>
          </div>
          <div class="field">
            <label for="city">City</label>
            <input class="input" id="city" value="Harare">
          </div>
          <div class="field col-2">
            <label for="province">Province</label>
            <select class="select" id="province">
              <?php foreach ($provinces_demo as $p): ?><option<?= $p === 'Harare' ? ' selected' : '' ?>><?= htmlspecialchars($p) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>

        <fieldset class="fieldset" style="margin-top:20px;padding-top:16px;border:0;border-top:1px solid var(--line)">
          <legend style="padding:0;color:var(--ink);font-size:13px;font-weight:700">Emergency contact</legend>
          <div class="form-grid" style="margin-top:12px">
            <div class="field"><label for="ecName">Name</label><input class="input" id="ecName"></div>
            <div class="field">
              <label for="ecRel">Relationship</label>
              <select class="select" id="ecRel"><option value="">Select&hellip;</option><option>Spouse</option><option>Parent</option><option>Sibling</option><option>Child</option><option>Friend</option></select>
            </div>
            <div class="field col-2">
              <label for="ecPhone">Phone</label>
              <div class="phone-input"><span class="phone-input__prefix"><span aria-hidden="true">🇿🇼</span> +263</span><input class="input" id="ecPhone" data-phone inputmode="tel"></div>
            </div>
          </div>
        </fieldset>
      </div>

      <!-- ─────────── STEP 3 ─────────── -->
      <div class="wizard-step" data-step="3" hidden>
        <h2 style="color:var(--ink);font-size:15px;font-weight:800;margin-bottom:4px">Church Information</h2>
        <p style="color:var(--muted);font-size:12.5px;margin-bottom:18px">Their place in the life of the church.</p>

        <div class="form-grid">
          <?php if ($branch_aware): ?>
            <?php
              /* Organisation-scope users choose; a branch-scope user is pinned
                 to their own and the field is locked. */
              $scoped   = ($user['scope'] ?? 'organisation') === 'branch';
              $own      = $scoped ? get_branch($user['branch_id'] ?? 0) : null;
              $selected = !$viewing_all ? get_branch($current_branch) : null;
            ?>
            <div class="field col-2">
              <label for="branchPick">
                <?= htmlspecialchars(t('branch_singular')) ?>
                <?php if (!$scoped): ?><span class="req" aria-hidden="true">*</span><?php endif; ?>
              </label>

              <?php if ($scoped): ?>
                <span class="locked-field">
                  <input class="input" id="branchPick" readonly tabindex="-1"
                         value="<?= htmlspecialchars($own['name'] ?? ($user['branch_name'] ?? '')) ?>"
                         title="Your account is scoped to this <?= htmlspecialchars(mb_strtolower(t('branch_singular'))) ?>, so members are added here."
                         aria-describedby="branchLockNote">
                  <i class="fa-solid fa-lock locked-field__lock" aria-hidden="true"></i>
                </span>
                <p class="hint" id="branchLockNote">
                  <i class="fa-solid fa-lock" aria-hidden="true"></i>
                  Locked to your own <?= htmlspecialchars(mb_strtolower(t('branch_singular'))) ?>.
                </p>
              <?php else: ?>
                <select class="select" id="branchPick" data-req data-preview="branch">
                  <option value="">Select&hellip;</option>
                  <?php foreach ($branch_options as $b): ?>
                    <option<?= $selected && (int) $b['id'] === (int) $selected['id'] ? ' selected' : '' ?>>
                      <?= htmlspecialchars($b['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <p class="err">Choose a <?= htmlspecialchars(mb_strtolower(t('branch_singular'))) ?>.</p>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <div class="field">
            <label for="memberNo">Membership number</label>
            <div style="display:flex;gap:8px">
              <input class="input" id="memberNo" value="<?= htmlspecialchars($next_member_no) ?>" data-preview="member_no">
              <button class="iconbtn" type="button" id="regenNo" aria-label="Generate a new membership number"><i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i></button>
            </div>
            <p class="hint">Auto-generated. Edit it if your church numbers differently.</p>
          </div>
          <div class="field">
            <label for="joined">Date joined</label>
            <input class="input" type="date" id="joined" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="field">
            <label for="status">Membership status</label>
            <select class="select" id="status" data-preview="status"><option>Active</option><option>Inactive</option><option>Transferred</option></select>
          </div>
          <div class="field">
            <label for="ministryRole">Ministry role</label>
            <input class="input" id="ministryRole" placeholder="e.g. Deacon, Usher, Chorister">
          </div>

          <div class="field col-2">
            <label>How did they join?</label>
            <div class="radio-cards">
              <?php foreach ([['New Convert','fa-seedling'],['Transfer','fa-right-left'],['Born Into Church','fa-baby'],['Other','fa-circle-question']] as $i => [$lab, $ic]): ?>
                <label class="rcard">
                  <input type="radio" name="howJoined" value="<?= htmlspecialchars($lab) ?>"<?= $i === 0 ? ' checked' : '' ?>>
                  <span class="rcard__box"><i class="fa-solid <?= $ic ?>" aria-hidden="true"></i><span class="rcard__label"><?= $lab ?></span></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="field col-2" id="prevChurchWrap" hidden>
            <label for="prevChurch">Previous church</label>
            <input class="input" id="prevChurch" placeholder="Which church are they transferring from?">
          </div>

          <div class="field col-2">
            <label style="display:flex;align-items:center;gap:10px">
              <span class="switch"><input type="checkbox" id="baptised"><span class="switch__track" aria-hidden="true"></span></span>
              <span style="color:var(--ink-2);font-size:13px;font-weight:600">Baptised</span>
            </label>
          </div>
          <div class="field" id="baptismWrap" hidden><label for="baptismDate">Baptism date</label><input class="input" type="date" id="baptismDate"></div>
          <div class="field" id="confirmWrap" hidden><label for="confirmDate">Confirmation date</label><input class="input" type="date" id="confirmDate"></div>

          <?php if (mu_mod('departments')): ?>
            <div class="field col-2">
              <label for="depts">Departments <span class="hint" style="display:inline">(hold Ctrl to pick several)</span></label>
              <select class="select" id="depts" multiple size="5" style="height:auto" data-preview="department">
                <?php foreach ($departments_list as $d): ?><option><?= htmlspecialchars($d) ?></option><?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>

          <?php if (mu_mod('cell_groups')): ?>
            <div class="field col-2">
              <label for="cellGroup">Cell group</label>
              <select class="select" id="cellGroup" data-preview="cell_group">
                <option value="">Not assigned yet</option>
                <?php foreach ($cells_list as $c): ?><option><?= htmlspecialchars($c) ?></option><?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- ─────────── STEP 4 ─────────── -->
      <div class="wizard-step" data-step="4" hidden>
        <h2 style="color:var(--ink);font-size:15px;font-weight:800;margin-bottom:4px">Family &amp; Additional</h2>
        <p style="color:var(--muted);font-size:12.5px;margin-bottom:18px">Link them to a household and add anything else worth recording.</p>

        <div class="field" style="margin-bottom:14px">
          <label>Household</label>
          <div class="radio-cards">
            <label class="rcard">
              <input type="radio" name="household" value="new" checked>
              <span class="rcard__box"><i class="fa-solid fa-house-circle-check" aria-hidden="true"></i><span class="rcard__label">Create new household</span></span>
            </label>
            <label class="rcard">
              <input type="radio" name="household" value="existing">
              <span class="rcard__box"><i class="fa-solid fa-house-user" aria-hidden="true"></i><span class="rcard__label">Add to existing household</span></span>
            </label>
          </div>
        </div>

        <div class="field" id="hhPicker" hidden style="margin-bottom:14px">
          <label for="hhSearch">Find a household</label>
          <div class="search-field">
            <i class="fa-solid fa-magnifying-glass search-field__icon" aria-hidden="true"></i>
            <input class="input" id="hhSearch" placeholder="Search households&hellip;">
          </div>
          <div class="clist" style="margin-top:10px;max-height:190px;overflow-y:auto">
            <?php foreach (array_slice($households_demo, 0, 6) as $h): ?>
              <label class="crow" style="cursor:pointer">
                <input class="check" type="radio" name="hhPick">
                <span class="crow__name"><?= htmlspecialchars($h['name']) ?></span>
                <span class="crow__phone"><?= htmlspecialchars($h['suburb']) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="form-grid">
          <div class="field">
            <label for="hhRel">Relationship to head of household</label>
            <select class="select" id="hhRel"><option>Head</option><option>Spouse</option><option>Child</option><option>Relative</option><option>Other</option></select>
          </div>
          <div class="field">
            <label for="spouse">Spouse</label>
            <input class="input" id="spouse" list="memberList" placeholder="Search members&hellip;">
          </div>
          <div class="field col-2">
            <label for="children">Children</label>
            <input class="input" id="children" list="memberList" placeholder="Search members&hellip;">
            <p class="hint">Pick one at a time; each is added to the household.</p>
          </div>
          <datalist id="memberList">
            <?php foreach ($members_demo as $m): ?><option value="<?= htmlspecialchars($m['name']) ?>"></option><?php endforeach; ?>
          </datalist>

          <div class="field col-2">
            <label for="notes">Notes</label>
            <textarea class="textarea" id="notes" rows="4" placeholder="Anything the pastoral team should know&hellip;"></textarea>
          </div>
        </div>
      </div>

      <!-- ─────────── STEP 5 ─────────── -->
      <div class="wizard-step" data-step="5" hidden>
        <h2 style="color:var(--ink);font-size:15px;font-weight:800;margin-bottom:4px">Review &amp; Save</h2>
        <p style="color:var(--muted);font-size:12.5px;margin-bottom:18px">Check everything before saving.</p>

        <div id="reviewOut"></div>

        <fieldset style="margin-top:8px;padding-top:16px;border:0;border-top:1px solid var(--line)">
          <legend style="padding:0;color:var(--ink);font-size:13px;font-weight:700">On save</legend>
          <div style="display:flex;flex-direction:column;gap:10px;margin-top:12px">
            <?php if (mu_mod('communication')): ?>
              <label style="display:flex;align-items:center;gap:10px;color:var(--ink-2);font-size:13px;font-weight:600">
                <input class="check" type="checkbox" checked> Send welcome SMS
              </label>
            <?php endif; ?>
            <label style="display:flex;align-items:center;gap:10px;color:var(--ink-2);font-size:13px;font-weight:600">
              <input class="check" type="checkbox"> Send welcome email
            </label>
            <label style="display:flex;align-items:center;gap:10px;color:var(--ink-2);font-size:13px;font-weight:600">
              <input class="check" type="checkbox"> Print membership card
            </label>
          </div>
        </fieldset>
      </div>

      <!-- ─────────── NAV ─────────── -->
      <div class="wizard-nav">
        <button class="btn btn--ghost" type="button" id="backBtn" hidden><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back</button>
        <span class="spacer"></span>
        <button class="btn" type="button" id="nextBtn">Continue <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
        <button class="btn" type="button" id="saveBtn" hidden><i class="fa-solid fa-check" aria-hidden="true"></i> Save Member</button>
      </div>
    </section>

    <!-- ═══════════════════════ LIVE PREVIEW ═══════════════════════ -->
    <aside class="preview" aria-label="Live preview">
      <p class="preview__eyebrow">Live preview</p>
      <span class="av av--xl av-c0 preview__av" id="pvAv" aria-hidden="true">?</span>
      <p class="preview__name" id="pvName">New member</p>
      <p class="preview__no" id="pvNo"><?= htmlspecialchars($next_member_no) ?></p>
      <dl class="preview__list">
        <div><dt>Phone</dt><dd data-pv="phone" class="preview__empty">—</dd></div>
        <div><dt>Suburb</dt><dd data-pv="suburb" class="preview__empty">—</dd></div>
        <div><dt>Marital</dt><dd data-pv="marital" class="preview__empty">—</dd></div>
        <div><dt>Occupation</dt><dd data-pv="occupation" class="preview__empty">—</dd></div>
        <?php if ($branch_aware): ?><div><dt><?= htmlspecialchars(t('branch_singular')) ?></dt><dd data-pv="branch" class="preview__empty">—</dd></div><?php endif; ?>
        <?php if (mu_mod('departments')): ?><div><dt>Department</dt><dd data-pv="department" class="preview__empty">—</dd></div><?php endif; ?>
        <?php if (mu_mod('cell_groups')): ?><div><dt>Cell group</dt><dd data-pv="cell_group" class="preview__empty">—</dd></div><?php endif; ?>
        <div><dt>Status</dt><dd data-pv="status">Active</dd></div>
      </dl>
    </aside>
  </div>

<?php endif; ?>
</div>

<div class="toasts" id="toasts" aria-live="polite"></div>

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

<script>
/* Add Member wizard — step navigation, inline validation, live preview and
   the unsaved-changes guard. Entirely client-side. */
(function () {
  'use strict';

  var form = document.querySelector('.wizard-step');
  if (!form) { return; }                       /* no permission: nothing to wire */

  var still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var STEPS = 5, step = 1, dirty = false;

  /* ─────────────────────────── toasts ─────────────────────────── */
  var toasts = document.getElementById('toasts');
  function toast(msg, kind) {
    kind = kind || 'success';
    var icons = { success: 'fa-circle-check', error: 'fa-circle-exclamation', info: 'fa-circle-info' };
    var el = document.createElement('div');
    el.className = 'toast is-' + kind;
    el.innerHTML = '<i class="fa-solid ' + icons[kind] + ' toast__icon" aria-hidden="true"></i>' +
      '<div class="toast__body"><p class="toast__title">' + msg + '</p></div>' +
      '<button class="toast__close" type="button" aria-label="Dismiss"><i class="fa-solid fa-xmark"></i></button>';
    toasts.appendChild(el);
    var kill = function () { el.classList.add('is-out'); setTimeout(function () { el.remove(); }, 250); };
    el.querySelector('.toast__close').addEventListener('click', kill);
    setTimeout(kill, 3400);
  }
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-toast]');
    if (t) { e.preventDefault(); toast(t.getAttribute('data-toast')); }
  });

  /* ───────────────────── step navigation ───────────────────── */
  var stepNames = ['Personal', 'Contact', 'Church', 'Family', 'Review'];

  function show(n) {
    step = n;
    [].forEach.call(document.querySelectorAll('[data-step]'), function (s) {
      s.hidden = parseInt(s.getAttribute('data-step'), 10) !== n;
    });
    [].forEach.call(document.querySelectorAll('[data-step-item]'), function (it) {
      var i = parseInt(it.getAttribute('data-step-item'), 10);
      it.classList.toggle('is-on', i === n);
      it.classList.toggle('is-done', i < n);
      /* Completed steps stay reachable; steps ahead do not. */
      it.querySelector('[data-goto-step]').disabled = i > n;
    });
    document.querySelector('[data-step-now]').textContent = n;
    document.querySelector('[data-step-name]').textContent = stepNames[n - 1];
    document.querySelector('[data-step-bar]').style.width = (n / STEPS * 100) + '%';

    document.getElementById('backBtn').hidden = n === 1;
    document.getElementById('nextBtn').hidden = n === STEPS;
    document.getElementById('saveBtn').hidden = n !== STEPS;
    document.getElementById('errSummary').classList.remove('is-on');
    if (n === STEPS) { buildReview(); }
    window.scrollTo({ top: 0, behavior: still ? 'auto' : 'smooth' });
  }

  /* ─────────────────────── validation ─────────────────────── */
  function fieldOf(el) { return el.closest('.field'); }

  function validate(el) {
    var f = fieldOf(el);
    if (!f) { return true; }
    var v = (el.value || '').trim(), ok = true;

    if (el.hasAttribute('data-req') && !v) { ok = false; }
    if (ok && el.hasAttribute('data-email') && v && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) { ok = false; }

    f.classList.toggle('is-bad', !ok);
    f.classList.toggle('is-good', ok && v !== '');
    return ok;
  }

  [].forEach.call(document.querySelectorAll('[data-req], [data-email]'), function (el) {
    el.addEventListener('blur', function () { validate(el); });
    el.addEventListener('input', function () {
      dirty = true;
      if (fieldOf(el).classList.contains('is-bad')) { validate(el); }
    });
  });

  function validateStep(n) {
    var pane = document.querySelector('[data-step="' + n + '"]');
    var bad = [];
    [].forEach.call(pane.querySelectorAll('[data-req], [data-email]'), function (el) {
      if (!validate(el)) {
        var lab = pane.querySelector('label[for="' + el.id + '"]');
        bad.push(lab ? lab.textContent.replace('*', '').trim() : el.id);
      }
    });
    /* Gender is a button group, so it is checked separately. */
    if (n === 1 && !document.querySelector('[data-gender][aria-pressed="true"]')) {
      var gf = document.querySelector('[data-gender]').closest('.field');
      gf.classList.add('is-bad');
      bad.push('Gender');
    }

    var box = document.getElementById('errSummary'), list = document.getElementById('errList');
    if (bad.length) {
      list.innerHTML = '';
      bad.forEach(function (b) { var li = document.createElement('li'); li.textContent = b; list.appendChild(li); });
      box.classList.add('is-on');
      box.scrollIntoView({ block: 'nearest', behavior: still ? 'auto' : 'smooth' });
      return false;
    }
    box.classList.remove('is-on');
    return true;
  }

  document.getElementById('nextBtn').addEventListener('click', function () {
    if (!validateStep(step)) { toast('Some fields still need attention', 'error'); return; }
    if (step < STEPS) { show(step + 1); }
  });
  document.getElementById('backBtn').addEventListener('click', function () { if (step > 1) { show(step - 1); } });
  [].forEach.call(document.querySelectorAll('[data-goto-step]'), function (b) {
    b.addEventListener('click', function () {
      var target = parseInt(b.getAttribute('data-goto-step'), 10);
      if (target <= step) { show(target); }
    });
  });

  function save() {
    if (!validateStep(step)) { toast('Some fields still need attention', 'error'); return; }
    dirty = false;
    toast('Member saved');
    setTimeout(function () { window.location.href = '<?= $base_url ?>members/all.php'; }, 900);
  }
  document.getElementById('saveBtn').addEventListener('click', save);
  var saveTop = document.getElementById('saveTop');
  if (saveTop) { saveTop.addEventListener('click', save); }

  /* ───────────────────── live preview ───────────────────── */
  var pvName = document.getElementById('pvName'), pvAv = document.getElementById('pvAv');

  /* Mirrors PHP's crc32() exactly (IEEE 802.3 polynomial) so a name resolves
     to the same avatar colour whether PHP or JS renders it. */
  var CRC_TABLE = (function () {
    var t = [], c, n, k;
    for (n = 0; n < 256; n++) {
      c = n;
      for (k = 0; k < 8; k++) { c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1); }
      t[n] = c >>> 0;
    }
    return t;
  })();
  function crc32(str) {
    var bytes = new TextEncoder().encode(str), crc = -1, i;
    for (i = 0; i < bytes.length; i++) { crc = (crc >>> 8) ^ CRC_TABLE[(crc ^ bytes[i]) & 0xFF]; }
    return (crc ^ -1) >>> 0;
  }
  function avClass(name) { return 'av-c' + (crc32(name) % 10); }

  function syncPreview() {
    var f = (document.getElementById('firstName').value || '').trim();
    var s = (document.getElementById('surname').value || '').trim();
    var full = (f + ' ' + s).trim();

    pvName.textContent = full || 'New member';
    pvAv.textContent = ((f[0] || '') + (s[0] || '')).toUpperCase() || '?';
    pvAv.className = 'av av--xl preview__av ' + (full ? avClass(full) : 'av-c0');

    [].forEach.call(document.querySelectorAll('[data-preview]'), function (el) {
      var key = el.getAttribute('data-preview');
      var slot = document.querySelector('[data-pv="' + key + '"]');
      if (!slot) { return; }
      var val = el.multiple
        ? [].slice.call(el.selectedOptions).map(function (o) { return o.value; }).join(', ')
        : el.value;
      if (key === 'phone' && val) { val = '+263 ' + val; }
      slot.textContent = val || '—';
      slot.classList.toggle('preview__empty', !val);
    });
    var no = document.getElementById('memberNo');
    if (no) { document.getElementById('pvNo').textContent = no.value; }
  }
  [].forEach.call(document.querySelectorAll('[data-preview], #firstName, #surname'), function (el) {
    el.addEventListener('input', syncPreview);
    el.addEventListener('change', syncPreview);
  });
  syncPreview();

  /* ───────────────────── field behaviours ───────────────────── */

  /* Age from date of birth. */
  var dob = document.getElementById('dob');
  dob.addEventListener('change', function () {
    if (!dob.value) { return; }
    var d = new Date(dob.value), t = new Date();
    var age = t.getFullYear() - d.getFullYear();
    var mo = t.getMonth() - d.getMonth();
    if (mo < 0 || (mo === 0 && t.getDate() < d.getDate())) { age--; }
    document.getElementById('ageOut').textContent = age >= 0 ? ('Age: ' + age + ' years') : 'Check that date.';
  });

  /* Gender toggle buttons. */
  [].forEach.call(document.querySelectorAll('[data-gender]'), function (b) {
    b.addEventListener('click', function () {
      [].forEach.call(document.querySelectorAll('[data-gender]'), function (o) {
        o.setAttribute('aria-pressed', String(o === b));
      });
      b.closest('.field').classList.remove('is-bad');
      dirty = true;
    });
  });

  /* Phone auto-format: 77 123 4567 as it is typed. */
  [].forEach.call(document.querySelectorAll('[data-phone]'), function (el) {
    el.addEventListener('input', function () {
      var d = el.value.replace(/\D/g, '').slice(0, 9);
      var out = d.slice(0, 2);
      if (d.length > 2) { out += ' ' + d.slice(2, 5); }
      if (d.length > 5) { out += ' ' + d.slice(5, 9); }
      el.value = out;
      dirty = true;
      /* The preview listener already fired with the raw digits, so refresh it
         with the formatted value. */
      syncPreview();
    });
  });

  var same = document.getElementById('sameAsPhone');
  same.addEventListener('change', function () {
    var wa = document.getElementById('whatsapp');
    if (same.checked) { wa.value = document.getElementById('phone').value; wa.setAttribute('readonly', ''); }
    else { wa.removeAttribute('readonly'); }
  });

  /* Transfer reveals the previous-church field. */
  [].forEach.call(document.querySelectorAll('[name="howJoined"]'), function (r) {
    r.addEventListener('change', function () {
      document.getElementById('prevChurchWrap').hidden = r.value !== 'Transfer';
    });
  });

  /* Baptism toggle reveals its dates. */
  var bap = document.getElementById('baptised');
  bap.addEventListener('change', function () {
    document.getElementById('baptismWrap').hidden = !bap.checked;
    document.getElementById('confirmWrap').hidden = !bap.checked;
  });

  /* Existing-household radio reveals the picker. */
  [].forEach.call(document.querySelectorAll('[name="household"]'), function (r) {
    r.addEventListener('change', function () {
      document.getElementById('hhPicker').hidden = r.value !== 'existing';
    });
  });

  document.getElementById('regenNo').addEventListener('click', function () {
    var n = Math.floor(Math.random() * 8999 + 1000);
    document.getElementById('memberNo').value = 'MCP-<?= date('Y') ?>-' + n;
    syncPreview();
    toast('New membership number generated', 'info');
  });

  /* ───────────────────── review summary ───────────────────── */
  function val(id) { var el = document.getElementById(id); return el && el.value ? el.value : '—'; }

  function buildReview() {
    var gender = document.querySelector('[data-gender][aria-pressed="true"]');
    var how = document.querySelector('[name="howJoined"]:checked');
    var blocks = [
      ['Personal', 1, [
        ['Name', [val('firstName'), val('middleName') === '—' ? '' : val('middleName'), val('surname')].filter(Boolean).join(' ')],
        ['Date of birth', val('dob')], ['Gender', gender ? gender.getAttribute('data-gender') : '—'],
        ['Marital status', val('marital')], ['Occupation', val('occupation')]
      ]],
      ['Contact', 2, [
        ['Phone', val('phone') === '—' ? '—' : '+263 ' + val('phone')],
        ['Email', val('email')], ['Address', val('address')],
        ['Suburb', val('suburb')], ['Province', val('province')]
      ]],
      ['Church', 3, [
        <?php if ($branch_aware): ?>['<?= addslashes(t('branch_singular')) ?>', val('branchPick')],<?php endif; ?>
        ['Membership number', val('memberNo')], ['Date joined', val('joined')],
        ['Status', val('status')], ['How they joined', how ? how.value : '—'],
        ['Cell group', val('cellGroup')]
      ]],
      ['Family & additional', 4, [
        ['Relationship', val('hhRel')], ['Spouse', val('spouse')], ['Notes', val('notes')]
      ]]
    ];

    var out = document.getElementById('reviewOut');
    out.innerHTML = '';
    blocks.forEach(function (blk) {
      var wrap = document.createElement('div');
      wrap.className = 'review-block';
      var head = document.createElement('div');
      head.className = 'review-block__head';
      head.innerHTML = '<h3></h3><a href="#" data-goto-step="' + blk[1] + '">Edit</a>';
      head.querySelector('h3').textContent = blk[0];
      head.querySelector('a').addEventListener('click', function (e) { e.preventDefault(); show(blk[1]); });
      wrap.appendChild(head);

      var dl = document.createElement('dl');
      dl.className = 'deflist';
      blk[2].forEach(function (pair) {
        var row = document.createElement('div');
        var dt = document.createElement('dt'); dt.textContent = pair[0];
        var dd = document.createElement('dd'); dd.textContent = pair[1] || '—';
        row.appendChild(dt); row.appendChild(dd); dl.appendChild(row);
      });
      wrap.appendChild(dl);
      out.appendChild(wrap);
    });
  }

  /* ──────────────── unsaved-changes guard ──────────────── */
  document.addEventListener('input', function () { dirty = true; });
  window.addEventListener('beforeunload', function (e) {
    if (!dirty) { return; }
    e.preventDefault();
    e.returnValue = '';
  });
  document.getElementById('cancelBtn').addEventListener('click', function (e) {
    if (dirty && !window.confirm('You have unsaved changes. Leave without saving?')) { e.preventDefault(); }
    else { dirty = false; }
  });

  show(1);
})();
</script>

<?php require __DIR__ . '/../components/footer.php'; ?>

<?php
/**
 * Mutendi CMS — My Profile (static UI mockup).
 *
 * The signed-in staff member's own account: details, password, two-factor and
 * the devices they are currently signed in on. Security lives here as a tab
 * rather than on a page of its own.
 *
 * Every dataset is hardcoded; each block carries the query that replaces it.
 */

/* The Device & Session Details modal is written once, in monitor/activity.php. */
$deviceModalOnly = true;
require __DIR__ . '/../monitor/activity.php';
$deviceModalOnly = false;

$musPathsOnly = true;
require __DIR__ . '/../components/sidebar.php';
$musPathsOnly = false;

/* ── The signed-in account ─────────────────────────────────────────────────
   LATER:
     SELECT u.*, r.name AS role, r.tone
       FROM staff_users u JOIN staff_roles r ON r.id = u.role_id
      WHERE u.id = :current_user; */
$me = [
    'in'      => 'RM',
    'name'    => 'Rufaro Mutasa',
    'email'   => 'rufaro@mutendi.co.zw',
    'phone'   => '+263 77 412 8890',
    'title'   => 'Founder & System Owner',
    'role'    => 'Owner',
    'tone'    => 'brand',
    'since'   => '04 Jan 2025',
    'last'    => '25 Aug 2026, 12:41',
    'logins'  => 1284,
    'twofa'   => true,
];

/* ── Email preferences ─────────────────────────────────────────────────────
   LATER: SELECT key, enabled FROM staff_notification_prefs WHERE user_id = :me; */
$prefs = [
    ['new_church',  'Email me when a new church registers',   true],
    ['expiring',    'Email me about expiring subscriptions',  true],
    ['errors',      'Email me about system errors',           true],
    ['weekly',      'Email me a weekly summary',              false],
];

/* ── Recovery codes ────────────────────────────────────────────────────────
   LATER: SELECT code, used_at FROM staff_recovery_codes WHERE user_id = :me;
   Real codes are shown once at generation and stored hashed. */
$recoveryCodes = ['4KQ2-9WXT','R7MB-3LDN','8HZC-Q1PV','2NFY-6KRW','JD53-8TXM',
                  'V9AL-2CQH','7BSE-XN4G','P6TK-5RWD','M3UZ-9FHQ','C8YN-4JVB'];

/* ── Active sessions ───────────────────────────────────────────────────────
   LATER:
     SELECT s.*, d.browser, d.os, d.type
       FROM staff_sessions s JOIN devices d ON d.id = s.device_id
      WHERE s.user_id = :me AND s.expires_at > NOW()
      ORDER BY s.last_active_at DESC; */
$sessions = [
 ['laptop','Chrome 128','Windows 11','Harare','Harare','197.221.44.18','25 Aug 2026, 12:41','Active now',  true],
 ['mobile','Chrome 128','Android 14','Harare','Harare','102.130.44.7', '25 Aug 2026, 08:12','2 hours ago', false],
 ['desktop','Edge 128', 'Windows 11','Bulawayo','Bulawayo','41.221.16.90','24 Aug 2026, 17:05','Yesterday, 19:40', false],
 ['tablet','Safari 17', 'iPadOS 18','Masvingo','Masvingo','196.27.88.140','23 Aug 2026, 09:33','2 days ago', false],
 ['laptop','Firefox 128','Ubuntu 24.04','Mutare','Manicaland','41.85.220.14','21 Aug 2026, 14:20','4 days ago', false],
];

/* ── Recent activity, my own ───────────────────────────────────────────────
   LATER: SELECT description, created_at FROM activity_logs
           WHERE user_id = :me ORDER BY created_at DESC LIMIT 5; */
$myActivity = [
    ['Extended ZCC Mbungo by 12 months',              '14:32', 'blue'],
    ['Started an impersonated session as Grace Ministries','14:20','amber'],
    ['Deleted archived church Harvest Kadoma',        '12:58', 'red'],
    ['Enabled Sermons & Media for 4 churches',        '12:22', 'blue'],
    ['Activated Grace Revival Church',                '11:20', 'green'],
];

$activePage    = 'admin/profile';
$sidebarBadges = ['pending' => 10, 'expiring' => 12];
$sidebarUser   = ['name' => 'Super Admin', 'role' => 'System Owner'];
$pageTitle     = 'My Profile';
$pageHint      = 'Manage your account details and security settings.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $pageTitle ?> — Mutendi CMS Super Admin</title>
<link rel="icon" type="image/png" href="<?= MUS_ROOT_URL ?>/resources/img/logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="<?= $base_url ?>assets/css/style.css">
</head>
<body>

<div class="page-grid" aria-hidden="true"></div>
<div class="page-glow" aria-hidden="true"></div>

<header class="topbar">
  <div class="topbar__search">
    <i class="fa-solid fa-magnifying-glass"></i>
    <input type="search" placeholder="Search church, admin, phone...">
  </div>
  <div class="topbar__right">
    <button class="icon-btn icon-btn--bell" type="button" aria-label="Notifications">
      <i class="fa-regular fa-bell"></i><span class="bell-badge">5</span>
    </button>
    <a class="btn btn--primary" href="<?= $base_url ?>churches/all.php"><i class="fa-solid fa-plus"></i> <span>Add Church</span></a>
    <div class="avatar-menu">
      <button class="avatar-menu__trigger" type="button">
        <span class="avatar">SA</span><i class="fa-solid fa-chevron-down"></i>
      </button>
      <div class="avatar-menu__list">
        <a href="<?= $base_url ?>admin/profile.php"><i class="fa-regular fa-user"></i> Profile</a>
        <a href="#"><i class="fa-solid fa-gear"></i> Settings</a>
        <a href="<?= $base_url ?>logout.php" class="is-danger"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
      </div>
    </div>
  </div>
</header>

<?php require __DIR__ . '/../components/sidebar.php'; ?>

<main class="main">

  <div class="page-head">
    <div>
      <h1><?= $pageTitle ?><?php if (!empty($pageBadge)): ?> <span class="title-badge"><?= $pageBadge ?></span><?php endif; ?></h1>
      <p class="crumb">
        <a href="<?= $base_url ?>index.php">Dashboard</a> <i class="fa-solid fa-chevron-right"></i>
        Administration <i class="fa-solid fa-chevron-right"></i> <?= $pageTitle ?>
      </p>
      <p class="page-hint"><?= $pageHint ?></p>
    </div>
  </div>

  <div class="grid grid--1-2">

    <!-- ═══════════════ LEFT — SUMMARY ═══════════════ -->
    <div class="profcol">
      <div class="card">
        <div class="card__body profsum">
          <span class="profsum__avatar"><?= $me['in'] ?></span>
          <button class="btn btn--sm" type="button" data-modal="modalPhoto"><i class="fa-solid fa-camera"></i> Change Photo</button>
          <strong class="profsum__name"><?= htmlspecialchars($me['name']) ?></strong>
          <span class="role role--<?= $me['tone'] ?>"><?= $me['role'] ?></span>
          <p class="profsum__contact">
            <span><i class="fa-regular fa-envelope"></i> <?= htmlspecialchars($me['email']) ?></span>
            <span><i class="fa-solid fa-phone"></i> <?= $me['phone'] ?></span>
          </p>
        </div>
        <dl class="summary summary--stack">
          <div><dt>Member since</dt><dd><?= $me['since'] ?></dd></div>
          <div><dt>Last login</dt><dd><?= $me['last'] ?></dd></div>
          <div><dt>Total logins</dt><dd><?= number_format($me['logins']) ?></dd></div>
          <div><dt>Two-factor</dt><dd>
            <?php if ($me['twofa']): ?><i class="fa-solid fa-circle-check yn--yes"></i> Enabled
            <?php else: ?><i class="fa-solid fa-circle-xmark yn--no"></i> Disabled<?php endif; ?>
          </dd></div>
          <div><dt>Active sessions</dt><dd><?= count($sessions) ?></dd></div>
        </dl>
      </div>

      <div class="card">
        <div class="card__head"><h2>Recent Activity</h2></div>
        <ul class="feed feed--flat">
          <?php foreach ($myActivity as [$txt,$t,$tone]): ?>
            <li class="feed__row"><span class="dot dot--<?= $tone ?>"></span>
              <span class="feed__text"><?= htmlspecialchars($txt) ?><small>25 Aug 2026, <?= $t ?></small></span></li>
          <?php endforeach; ?>
        </ul>
        <div class="card__foot"><a href="<?= $base_url ?>monitor/activity.php">View the full activity log</a></div>
      </div>
    </div>

    <!-- ═══════════════ RIGHT — TABS ═══════════════ -->
    <div class="profcol">
      <div class="tabs">
        <button class="tab is-on" type="button" data-tab="details">Profile Details</button>
        <button class="tab" type="button" data-tab="password">Change Password</button>
        <button class="tab" type="button" data-tab="twofa">Two-Factor Authentication</button>
        <button class="tab" type="button" data-tab="sessions">Active Sessions</button>
      </div>

      <!-- ── TAB 1 — DETAILS ── -->
      <div class="tabpanel" data-panel="details">
        <div class="card">
          <div class="card__head"><h2>Profile Details</h2></div>
          <div class="card__body">
            <div class="field-row">
              <label class="field"><span class="field__label">Full name</span>
                <input type="text" value="<?= htmlspecialchars($me['name']) ?>"></label>
              <label class="field"><span class="field__label">Email</span>
                <input type="email" value="<?= htmlspecialchars($me['email']) ?>"></label>
            </div>
            <div class="field-row">
              <label class="field"><span class="field__label">Phone</span>
                <input type="tel" value="<?= $me['phone'] ?>"></label>
              <label class="field"><span class="field__label">Job title</span>
                <input type="text" value="<?= htmlspecialchars($me['title']) ?>"></label>
            </div>
            <div class="field-row">
              <label class="field"><span class="field__label">Timezone</span>
                <select><option>Africa/Harare (CAT, UTC+2)</option><option>Africa/Johannesburg (SAST, UTC+2)</option>
                  <option>Africa/Nairobi (EAT, UTC+3)</option><option>UTC</option></select></label>
              <label class="field"><span class="field__label">Language</span>
                <select><option>English</option><option>Shona</option><option>Ndebele</option></select></label>
            </div>
            <label class="field"><span class="field__label">Date format</span>
              <select><option>25 Aug 2026</option><option>25/08/2026</option><option>2026-08-25</option><option>Aug 25, 2026</option></select></label>
          </div>

          <div class="card__head"><h2>Email Preferences</h2></div>
          <ul class="prefs">
            <?php foreach ($prefs as [$key,$label,$on]): ?>
              <li class="prefs__row">
                <span><?= htmlspecialchars($label) ?></span>
                <label class="switch"><input type="checkbox"<?= $on ? ' checked' : '' ?>><span class="switch__track"></span></label>
              </li>
            <?php endforeach; ?>
          </ul>
          <div class="card__foot card__foot--split">
            <p class="muted">Changes take effect straight away.</p>
            <button class="btn btn--primary" type="button"><i class="fa-solid fa-check"></i> Save Changes</button>
          </div>
        </div>
      </div>

      <!-- ── TAB 2 — PASSWORD ── -->
      <div class="tabpanel" data-panel="password" hidden>
        <div class="card">
          <div class="card__head"><h2>Change Password</h2></div>
          <div class="card__body">
            <label class="field"><span class="field__label">Current password</span>
              <input type="password" placeholder="Your password right now"></label>

            <label class="field"><span class="field__label">New password</span>
              <input type="password" id="newPw" placeholder="At least 12 characters"></label>

            <div class="strength">
              <div class="strength__bar"><span id="strengthFill"></span></div>
              <p class="strength__label">Strength: <strong id="strengthWord">&mdash;</strong></p>
            </div>

            <ul class="reqlist" id="pwReqs">
              <li data-req="len"><i class="fa-regular fa-circle"></i><span>At least 12 characters</span></li>
              <li data-req="upper"><i class="fa-regular fa-circle"></i><span>One uppercase letter</span></li>
              <li data-req="num"><i class="fa-regular fa-circle"></i><span>One number</span></li>
              <li data-req="sym"><i class="fa-regular fa-circle"></i><span>One symbol</span></li>
            </ul>

            <label class="field"><span class="field__label">Confirm new password</span>
              <input type="password" id="confirmPw" placeholder="Type it once more"></label>
            <p class="fieldnote" id="pwMatch" hidden></p>

            <label class="check-row"><input type="checkbox" checked><span>Sign out of all other devices</span></label>
          </div>
          <div class="card__foot card__foot--split">
            <p class="muted">You will stay signed in on this device.</p>
            <button class="btn btn--primary" type="button"><i class="fa-solid fa-key"></i> Update Password</button>
          </div>
        </div>
      </div>

      <!-- ── TAB 3 — TWO-FACTOR ── -->
      <div class="tabpanel" data-panel="twofa" hidden>
        <div class="card">
          <div class="card__head"><h2>Two-Factor Authentication</h2></div>
          <div class="card__body">
            <div class="tfa-state tfa-state--on" id="tfaState">
              <span class="tfa-state__icon"><i class="fa-solid fa-shield-halved"></i></span>
              <span class="tfa-state__text">
                <strong>Two-factor is enabled</strong>
                <small>A code from your authenticator app is required at every sign-in. Turned on 04 Jan 2025.</small>
              </span>
              <label class="switch" title="Turn two-factor off">
                <input type="checkbox" id="tfaToggle" checked><span class="switch__track"></span>
              </label>
            </div>

            <!-- The setup flow, shown while turning 2FA on. -->
            <div class="steps" id="tfaSetup" hidden>
              <div class="step">
                <span class="step__num">1</span>
                <div class="step__body">
                  <strong>Scan this code</strong>
                  <p class="msec__note">Open Google Authenticator, Authy or 1Password and scan it.</p>
                  <div class="qrbox"><i class="fa-solid fa-qrcode"></i><span>QR code appears here</span></div>
                </div>
              </div>
              <div class="step">
                <span class="step__num">2</span>
                <div class="step__body">
                  <strong>Or type the key by hand</strong>
                  <p class="msec__note">Use this if your phone cannot scan.</p>
                  <div class="monobox">
                    <pre id="tfaKey">JBSW Y3DP EHPK 3PXP QK4T MZQW</pre>
                    <button class="btn btn--sm monobox__copy" type="button" data-copy="tfaKey"><i class="fa-regular fa-copy"></i> Copy</button>
                  </div>
                </div>
              </div>
              <div class="step">
                <span class="step__num">3</span>
                <div class="step__body">
                  <strong>Enter the 6-digit code</strong>
                  <p class="msec__note">Confirm the app and this account agree.</p>
                  <div class="codeinput">
                    <input type="text" inputmode="numeric" maxlength="6" placeholder="000000" aria-label="Verification code">
                    <button class="btn btn--primary" type="button">Verify &amp; Enable</button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="card__head"><h2>Recovery Codes</h2>
            <span class="card__note">10 codes &middot; each works once</span></div>
          <div class="card__body">
            <p class="msec__note">Keep these somewhere safe. If you lose your phone, they are the only
              way back into your account.</p>
            <ul class="codes is-hidden" id="codeGrid">
              <?php foreach ($recoveryCodes as $c): ?><li><?= $c ?></li><?php endforeach; ?>
            </ul>
            <div class="codes__acts">
              <button class="btn btn--sm" type="button" id="revealCodes"><i class="fa-regular fa-eye"></i> Reveal</button>
              <button class="btn btn--sm" type="button"><i class="fa-solid fa-file-arrow-down"></i> Download</button>
              <button class="btn btn--sm" type="button"><i class="fa-regular fa-copy"></i> Copy All</button>
              <button class="btn btn--sm btn--danger" type="button" data-modal="modalRegen"><i class="fa-solid fa-rotate-left"></i> Regenerate</button>
            </div>
            <p class="modal__warn"><i class="fa-solid fa-triangle-exclamation"></i>
              Store these away from your phone. Anyone holding a code can sign in as you.</p>
          </div>

          <div class="card__head"><h2>Backup Method</h2></div>
          <ul class="prefs">
            <li class="prefs__row">
              <span>SMS a code to <strong><?= $me['phone'] ?></strong>
                <small class="prefs__note">Used only when the authenticator app is unavailable.</small></span>
              <label class="switch"><input type="checkbox" checked><span class="switch__track"></span></label>
            </li>
          </ul>
        </div>
      </div>

      <!-- ── TAB 4 — SESSIONS ── -->
      <div class="tabpanel" data-panel="sessions" hidden>
        <div class="card">
          <div class="card__head">
            <h2>Active Sessions</h2>
            <button class="btn btn--sm btn--outline-danger" type="button" data-modal="modalRevoke">
              <i class="fa-solid fa-right-from-bracket"></i> Sign Out All Other Devices</button>
          </div>
          <div class="table-wrap">
            <table class="table table--churches">
              <thead>
                <tr><th>Device</th><th>Location</th><th>IP Address</th><th>Started</th><th>Last Active</th>
                  <th>Status</th><th class="col-actions">Actions</th></tr>
              </thead>
              <tbody>
                <?php foreach ($sessions as [$dev,$browser,$os,$city,$prov,$ip,$started,$lastActive,$current]): ?>
                  <?php $devIcon = ['laptop'=>'fa-laptop','desktop'=>'fa-desktop','mobile'=>'fa-mobile-screen','tablet'=>'fa-tablet-screen-button'][$dev]; ?>
                  <tr<?= $current ? ' class="is-current"' : '' ?>>
                    <td>
                      <button class="devcell" type="button" data-modal="modalDevice">
                        <i class="fa-solid <?= $devIcon ?> devicon"></i>
                        <span class="stack"><strong><?= $browser ?></strong><small><?= $os ?></small></span>
                      </button>
                    </td>
                    <td class="nowrap"><span class="stack"><strong><?= $city ?></strong><small><?= $prov ?></small></span></td>
                    <td><code class="keytext"><?= $ip ?></code></td>
                    <td class="nowrap muted"><?= $started ?></td>
                    <td class="nowrap <?= $current ? '' : 'muted' ?>"><?= $lastActive ?></td>
                    <td>
                      <?php if ($current): ?><span class="pill pill--active">This Device</span>
                      <?php else: ?><span class="muted">Signed in</span><?php endif; ?>
                    </td>
                    <td class="col-actions">
                      <div class="row-actions">
                        <button class="ico-btn" type="button" title="Device Details" aria-label="Device Details" data-modal="modalDevice"><i class="fa-solid fa-desktop"></i></button>
                        <?php if (!$current): ?>
                          <button class="btn btn--sm btn--danger" type="button" data-modal="modalRevoke">Revoke</button>
                        <?php else: ?>
                          <i class="fa-solid fa-lock lockmark" title="You cannot revoke the session you are using"></i>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="card__foot card__foot--split">
            <p class="muted">Anything you do not recognise should be revoked, then change your password.</p>
            <a href="<?= $base_url ?>monitor/logins.php">View full login history</a>
          </div>
        </div>
      </div>

    </div>
  </div>
  <footer class="foot">
    <p class="foot__brand">Mutendi CMS</p>
    <p class="foot__copy">&copy; <?= date('Y') ?> Mutendi. All rights reserved.</p>
    <p class="foot__meta">Super Admin &middot; v1.0</p>
  </footer>

</main>

<!-- ═══════════ CHANGE PHOTO ═══════════ -->
<div class="modal" id="modalPhoto" hidden>
  <div class="modal__box modal__box--sm">
    <div class="modal__head">
      <h2><i class="fa-solid fa-camera"></i> Change Photo</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <div class="dropzone">
        <i class="fa-solid fa-cloud-arrow-up"></i>
        <strong>Drop a picture here</strong>
        <small>or <a href="#">choose a file</a> &mdash; JPG or PNG, up to 2 MB</small>
      </div>
      <span class="field__label">Preview</span>
      <div class="croprow">
        <span class="cropbox"><span class="cropbox__ring"></span><span class="cropbox__init"><?= $me['in'] ?></span></span>
        <div class="croprow__text">
          <p class="msec__note">Drag inside the circle to reposition. The picture is cropped to a
            circle everywhere it appears.</p>
          <label class="field"><span class="field__label">Zoom</span>
            <input type="range" min="100" max="200" value="120"></label>
        </div>
      </div>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--primary" type="button">Save Photo</button>
    </div>
  </div>
</div>

<!-- ═══════════ REVOKE SESSION ═══════════ -->
<div class="modal" id="modalRevoke" hidden>
  <div class="modal__box modal__box--sm">
    <div class="modal__head">
      <h2><i class="fa-solid fa-right-from-bracket note--berry"></i> Revoke Session</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <p class="modal__lead">This signs the device out immediately. Whoever is using it will need
        your password &mdash; and a two-factor code &mdash; to get back in.</p>
      <dl class="summary">
        <div><dt>Device</dt><dd>Chrome 128 &middot; Android 14</dd></div>
        <div><dt>Location</dt><dd>Harare, Harare</dd></div>
        <div><dt>IP address</dt><dd><code class="keytext">102.130.44.7</code></dd></div>
        <div><dt>Last active</dt><dd>2 hours ago</dd></div>
      </dl>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--danger-solid" type="button">Revoke Session</button>
    </div>
  </div>
</div>

<!-- ═══════════ DISABLE 2FA ═══════════ -->
<div class="modal" id="modalDisable2fa" hidden>
  <div class="modal__box modal__box--sm">
    <div class="modal__head">
      <h2><i class="fa-solid fa-shield-halved note--berry"></i> Turn Off Two-Factor</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <p class="modal__warn"><i class="fa-solid fa-triangle-exclamation"></i>
        Your account holds the Owner role &mdash; full access to every church on the platform.
        Without two-factor, a stolen password is enough to reach all of it.</p>
      <label class="field"><span class="field__label">Confirm with your password</span>
        <input type="password" placeholder="Your password"></label>
      <p class="notebox"><i class="fa-solid fa-circle-info"></i>
        Your recovery codes stop working the moment two-factor is turned off.</p>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close id="cancel2fa">Keep It On</button>
      <button class="btn btn--danger-solid" type="button">Turn Off Two-Factor</button>
    </div>
  </div>
</div>

<!-- ═══════════ REGENERATE RECOVERY CODES ═══════════ -->
<div class="modal" id="modalRegen" hidden>
  <div class="modal__box modal__box--sm">
    <div class="modal__head">
      <h2><i class="fa-solid fa-rotate-left note--gold"></i> Regenerate Recovery Codes</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <p class="modal__warn"><i class="fa-solid fa-triangle-exclamation"></i>
        All 10 of your current codes stop working straight away. Any copy you have written down
        or saved becomes useless.</p>
      <label class="field"><span class="field__label">Confirm with your password</span>
        <input type="password" placeholder="Your password"></label>
      <p class="notebox"><i class="fa-solid fa-circle-info"></i>
        The new codes are shown once. Download or copy them before you close the box.</p>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--primary" type="button">Generate New Codes</button>
    </div>
  </div>
</div>

<?php device_modal(); ?>
<script>
/* Shared chrome: dropdowns, tabs, modals, copy buttons, bulk bars and the
   permission matrix. */
(function () {
  'use strict';
  var custom = document.getElementById('rangeCustom');
  if (custom) {
    custom.addEventListener('click', function () {
      var d = document.getElementById('rangeDates');
      d.hidden = !d.hidden; custom.classList.toggle('is-on', !d.hidden);
    });
  }
  document.addEventListener('click', function (e) {
    var trigger = e.target.closest('.dropdown__trigger');
    document.querySelectorAll('.dropdown.is-open').forEach(function (d) {
      if (!trigger || d !== trigger.parentNode) { d.classList.remove('is-open'); }
    });
    if (trigger) { e.preventDefault(); trigger.parentNode.classList.toggle('is-open'); }
  });
  var tabs = [].slice.call(document.querySelectorAll('.tab[data-tab]'));
  tabs.forEach(function (t) {
    t.addEventListener('click', function () {
      tabs.forEach(function (x) { x.classList.toggle('is-on', x === t); });
      document.querySelectorAll('.tabpanel').forEach(function (p) {
        p.hidden = p.dataset.panel !== t.dataset.tab;
      });
    });
  });
  function close(m) { m.hidden = true; document.body.classList.remove('modal-open'); }
  document.addEventListener('click', function (e) {
    var opener = e.target.closest('[data-modal]');
    if (opener) {
      e.preventDefault();
      document.querySelectorAll('.modal:not([hidden])').forEach(function (m) { m.hidden = true; });
      var m = document.getElementById(opener.dataset.modal);
      if (m) { m.hidden = false; document.body.classList.add('modal-open'); }
      return;
    }
    if (e.target.closest('[data-close]') || e.target.classList.contains('modal')) {
      var box = e.target.closest('.modal');
      if (box) { close(box); }
    }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal:not([hidden])').forEach(close);
      document.querySelectorAll('.dropdown.is-open').forEach(function (d) { d.classList.remove('is-open'); });
    }
  });

  /* Copy buttons on any monospace box. */
  document.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var src = document.getElementById(btn.dataset.copy);
      if (!src) { return; }
      try { navigator.clipboard.writeText(src.textContent.trim()); } catch (e) {}
      var was = btn.innerHTML;
      btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied';
      setTimeout(function () { btn.innerHTML = was; }, 1400);
    });
  });


  /* Bulk bars keyed by name. */
  document.querySelectorAll('[data-checkall]').forEach(function (all) {
    var key = all.dataset.checkall,
        rows = [].slice.call(document.querySelectorAll('[data-rowcheck="' + key + '"]')),
        bar = document.querySelector('[data-bulkbar="' + key + '"]'),
        count = document.querySelector('[data-bulkcount="' + key + '"]'),
        clear = document.querySelector('[data-bulkclear="' + key + '"]');
    function refresh() {
      var n = rows.filter(function (c) { return c.checked; }).length;
      count.textContent = n; bar.hidden = n === 0;
      all.checked = n === rows.length && n > 0;
      all.indeterminate = n > 0 && n < rows.length;
    }
    all.addEventListener('change', function () {
      rows.forEach(function (c) { c.checked = all.checked; }); refresh();
    });
    rows.forEach(function (c) { c.addEventListener('change', refresh); });
    if (clear) { clear.addEventListener('click', function () {
      rows.forEach(function (c) { c.checked = false; }); all.checked = false; refresh();
    }); }
  });

  /* Permission matrix — same behaviour as the church role templates in
     setup/index.php, over the platform permission catalogue. */
  var permBoxes = [].slice.call(document.querySelectorAll(".permbox")),
      permCount = document.getElementById("permCount");

  function tallyPerms() {
    if (permCount) { permCount.textContent = permBoxes.filter(function (b) { return b.checked; }).length; }
    document.querySelectorAll("[data-permall]").forEach(function (master) {
      var group = master.dataset.permall,
          kids  = permBoxes.filter(function (b) { return b.dataset.permgroup === group; }),
          on    = kids.filter(function (b) { return b.checked; }).length;
      master.checked = on === kids.length && on > 0;
      master.indeterminate = on > 0 && on < kids.length;
    });
  }
  permBoxes.forEach(function (b) { b.addEventListener("change", tallyPerms); });
  document.querySelectorAll("[data-permall]").forEach(function (master) {
    master.addEventListener("change", function () {
      permBoxes.filter(function (b) { return b.dataset.permgroup === master.dataset.permall; })
               .forEach(function (b) { b.checked = master.checked; });
      tallyPerms();
    });
  });
  document.querySelectorAll(".permgroup__toggle").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var grp = btn.closest(".permgroup"),
          open = grp.classList.toggle("is-open");
      btn.setAttribute("aria-expanded", String(open));
    });
  });
  tallyPerms();})();
</script>
<script>
/* Page-specific: password strength, the 2FA setup flow and recovery codes. */
(function () {
  /* --- Password strength and the requirements checklist ---------------- */
  var pw      = document.getElementById('newPw'),
      confirm = document.getElementById('confirmPw'),
      fill    = document.getElementById('strengthFill'),
      word    = document.getElementById('strengthWord'),
      match   = document.getElementById('pwMatch'),
      reqs    = document.getElementById('pwReqs');

  var WORDS = ['—', 'Weak', 'Fair', 'Good', 'Strong'];

  function scorePw(v) {
    return {
      len:   v.length >= 12,
      upper: /[A-Z]/.test(v),
      num:   /[0-9]/.test(v),
      sym:   /[^A-Za-z0-9]/.test(v)
    };
  }

  function gradePw() {
    var v = pw.value, met = scorePw(v),
        n = Object.keys(met).filter(function (k) { return met[k]; }).length;

    reqs.querySelectorAll('[data-req]').forEach(function (li) {
      var ok = met[li.dataset.req];
      li.classList.toggle('is-met', ok);
      li.querySelector('i').className = ok ? 'fa-solid fa-circle-check' : 'fa-regular fa-circle';
    });

    /* An empty field says nothing; anything typed is at least Weak. */
    if (v === '') { n = 0; } else if (n === 0) { n = 1; }
    fill.style.width = (n * 25) + '%';
    fill.className = 'is-' + n;
    word.textContent = WORDS[n];
    checkMatch();
  }

  function checkMatch() {
    if (!confirm.value) { match.hidden = true; return; }
    var same = confirm.value === pw.value;
    match.hidden = false;
    match.textContent = same ? 'The passwords match.' : 'The passwords do not match yet.';
    match.classList.toggle('is-bad', !same);
  }

  if (pw) {
    pw.addEventListener('input', gradePw);
    confirm.addEventListener('input', checkMatch);
    gradePw();
  }

  /* --- Two-factor: the toggle either opens the setup flow or asks first - */
  var tfa   = document.getElementById('tfaToggle'),
      setup = document.getElementById('tfaSetup'),
      state = document.getElementById('tfaState');

  if (tfa) {
    tfa.addEventListener('change', function () {
      if (tfa.checked) {
        /* Turning it on: reveal the three setup steps. */
        setup.hidden = false;
        state.classList.add('tfa-state--on');
        return;
      }
      /* Turning it off is a decision worth confirming, so put the switch
         back and let the modal ask. */
      tfa.checked = true;
      var m = document.getElementById('modalDisable2fa');
      document.querySelectorAll('.modal:not([hidden])').forEach(function (x) { x.hidden = true; });
      m.hidden = false;
      document.body.classList.add('modal-open');
    });
  }

  /* --- Recovery codes stay blurred until asked for --------------------- */
  var reveal = document.getElementById('revealCodes'),
      grid   = document.getElementById('codeGrid');

  if (reveal) {
    reveal.addEventListener('click', function () {
      var hidden = grid.classList.toggle('is-hidden');
      reveal.innerHTML = hidden
        ? '<i class="fa-regular fa-eye"></i> Reveal'
        : '<i class="fa-regular fa-eye-slash"></i> Hide';
    });
  }
})();
</script>
</body>
</html>

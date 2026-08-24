<?php
/**
 * Mutendi CMS — Send Notification (static UI mockup).
 *
 * Composer for direct email/SMS to selected churches, with a short history
 * beneath. Trial and Paying churches are the same kind of tenant — they are
 * only two of the recipient filters. Every dataset is hardcoded; each block
 * carries the query that replaces it. Nothing actually sends.
 */

/* Path constants only — the sidebar markup is required further down. */
$musPathsOnly = true;
require __DIR__ . '/../components/sidebar.php';
$musPathsOnly = false;

/* ── Recipient groups ──────────────────────────────────────────────────────
   LATER: each option maps to a WHERE clause over `churches`, e.g.
     expiring  -> expiry_date BETWEEN NOW() AND NOW() + INTERVAL 30 DAY
     dormant   -> last_login_at < NOW() - INTERVAL 30 DAY */
$audiences = [
    ['key'=>'all',      'label'=>'All Churches',                  'count'=>47],
    ['key'=>'trial',    'label'=>'Trial Churches Only',           'count'=>9],
    ['key'=>'paying',   'label'=>'Paying Churches Only',          'count'=>38],
    ['key'=>'expiring', 'label'=>'Expiring Soon (next 30 days)',  'count'=>12],
    ['key'=>'expired',  'label'=>'Expired / Suspended',           'count'=>10],
    ['key'=>'pending',  'label'=>'Pending Activation',            'count'=>10],
    ['key'=>'dormant',  'label'=>'Dormant (no login in 30+ days)','count'=>7],
    ['key'=>'manual',   'label'=>'Select Manually',               'count'=>0],
];

/* ── Churches for the manual picker ────────────────────────────────────────
   LATER: SELECT id, name, code, account_type, email, phone FROM churches; */
$churches = [
    ['ZM','ZCC Mbungo','ZCC-001','Paying','admin@zccmbungo.co.zw','+263 772 145 880'],
    ['AW','AFM Waterfalls','AFM-002','Paying','office@afmwaterfalls.org','+263 778 411 207'],
    ['GM','Grace Ministries','GRM-003','Paying','info@graceministries.co.zw','+263 771 902 335'],
    ['JM','Johane Masowe eChishanu','JME-004','Paying','admin@jmasowe.co.zw','+263 712 660 194'],
    ['CC','Celebration Church Harare','CCH-005','Paying','office@celebration.co.zw','+263 773 508 621'],
    ['FG','Family of God Bulawayo','FOG-006','Trial','admin@fogbulawayo.org','+263 776 233 018'],
    ['MM','Methodist Mutare Circuit','MMC-007','Paying','circuit@methodistmutare.org','+263 774 887 552'],
    ['NL','New Life Chitungwiza','NLC-061','Trial','hello@newlife.co.zw','+263 772 118 904'],
    ['RH','Rhema Bulawayo','RHB-063','Trial','admin@rhemabyo.org','+263 778 337 220'],
    ['AD','Anglican Diocese Masvingo','ADM-009','Paying','office@anglicanmasvingo.org','+263 779 115 442'],
    ['ST','St Thomas Mutare','STM-064','Trial','parish@stthomas.org','+263 771 446 093'],
    ['UF','UFIC Chinhoyi','UFI-013','Paying','admin@ufic-chinhoyi.org','+263 778 552 907'],
];

/* ── Message templates ─────────────────────────────────────────────────────
   LATER: SELECT id, name, subject, body FROM message_templates; */
$templates = ['Blank', 'Renewal Reminder', 'Expiry Notice', 'Payment Received',
              'Welcome / Onboarding', 'Trial Ending', 'System Maintenance', 'Follow-Up'];

/* Merge tags the composer can insert into the message. */
$mergeTags = ['{church_name}', '{contact_person}', '{expiry_date}',
              '{days_remaining}', '{church_code}', '{admin_name}'];

/* ── Stat strip ────────────────────────────────────────────────────────────
   LATER: COUNT(*) over `notifications` and `notification_recipients` for
   the current month, grouped by channel and delivery state. */
$statTiles = [
    ['label' => 'Sent This Month', 'value' => 42,  'tone' => 'indigo', 'icon' => 'fa-paper-plane',  'on' => true],
    ['label' => 'Emails Sent',     'value' => 128, 'tone' => 'brand',  'icon' => 'fa-envelope',     'on' => false],
    ['label' => 'SMS Sent',        'value' => 96,  'tone' => 'green',  'icon' => 'fa-comment-sms',  'on' => false],
    ['label' => 'Failed',          'value' => 3,   'tone' => 'berry',  'icon' => 'fa-circle-xmark', 'on' => false],
];

/* ── Recent sends ──────────────────────────────────────────────────────────
   LATER:
     SELECT n.*, COUNT(r.id) AS recipients,
            SUM(r.state = 'delivered') AS delivered
       FROM notifications n
       LEFT JOIN notification_recipients r ON r.notification_id = n.id
      GROUP BY n.id ORDER BY n.sent_at DESC LIMIT 8; */
$history = [
    ['subject'=>'Your subscription expires in 7 days','excerpt'=>'Dear {contact_person}, your Mutendi CMS subscription for {church_name} expires on...','recipients'=>12,'email'=>true,'sms'=>true,'sent'=>'24 Aug 2026','ago'=>'2 hours ago','delivered'=>11,'status'=>'Sent'],
    ['subject'=>'Payment received — thank you','excerpt'=>'We have received your payment of $360 for {church_name}. Your new expiry date is...','recipients'=>4,'email'=>true,'sms'=>false,'sent'=>'23 Aug 2026','ago'=>'Yesterday','delivered'=>4,'status'=>'Sent'],
    ['subject'=>'Trial ending in 3 days','excerpt'=>'Dear {contact_person}, your trial for {church_name} ends on {expiry_date}...','recipients'=>3,'email'=>true,'sms'=>true,'sent'=>'22 Aug 2026','ago'=>'2 days ago','delivered'=>3,'status'=>'Sent'],
    ['subject'=>'Maintenance window this Sunday','excerpt'=>'The system will be unavailable between 02:00 and 04:00 on Sunday...','recipients'=>47,'email'=>true,'sms'=>false,'sent'=>'21 Aug 2026','ago'=>'3 days ago','delivered'=>45,'status'=>'Sent'],
    ['subject'=>'Outstanding balance reminder','excerpt'=>'Our records show an outstanding balance of $30 for {church_name}...','recipients'=>6,'email'=>true,'sms'=>true,'sent'=>'19 Aug 2026','ago'=>'5 days ago','delivered'=>4,'status'=>'Failed'],
    ['subject'=>'Welcome to Mutendi CMS','excerpt'=>'Welcome {contact_person}. Your login details for {church_name} are below...','recipients'=>2,'email'=>true,'sms'=>true,'sent'=>'18 Aug 2026','ago'=>'6 days ago','delivered'=>2,'status'=>'Sent'],
    ['subject'=>'Renewal reminder — September batch','excerpt'=>'Dear {contact_person}, this is a friendly reminder that {church_name}...','recipients'=>9,'email'=>true,'sms'=>true,'sent'=>'01 Sep 2026','ago'=>'in 8 days','delivered'=>0,'status'=>'Scheduled'],
    ['subject'=>'Quarterly check-in','excerpt'=>'How are you finding the system? We would value a few minutes of your time...','recipients'=>0,'email'=>true,'sms'=>false,'sent'=>'','ago'=>'','delivered'=>0,'status'=>'Draft'],
];

$activePage    = 'comms/notify';
$sidebarBadges = ['pending' => 10, 'expiring' => 12];
$sidebarUser   = ['name' => 'Super Admin', 'role' => 'System Owner'];

function notif_pill(string $s): string { return 'pill--' . strtolower($s); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Send Notification — Mutendi CMS Super Admin</title>
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
      <h1>Send Notification</h1>
      <p class="crumb">
        <a href="<?= $base_url ?>index.php">Dashboard</a> <i class="fa-solid fa-chevron-right"></i>
        Communication <i class="fa-solid fa-chevron-right"></i> Send Notification
      </p>
      <p class="page-hint">Send a direct email or SMS to selected churches.</p>
    </div>
  </div>

  <div class="statstrip">
    <?php foreach ($statTiles as $t): ?>
      <a class="stat-tile stat-tile--<?= $t['tone'] ?><?= $t['on'] ? ' is-on' : '' ?>" href="#">
        <span class="stat-tile__icon"><i class="fa-solid <?= $t['icon'] ?>"></i></span>
        <span class="stat-tile__body">
          <span class="stat-tile__value"><?= $t['value'] ?></span>
          <span class="stat-tile__label"><?= htmlspecialchars($t['label']) ?></span>
        </span>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- ============ COMPOSER ============ -->
  <div class="grid grid--2-1">

    <!-- LEFT: the composer -->
    <div class="card">
      <div class="card__head"><h2><i class="fa-solid fa-pen-to-square"></i> Compose</h2></div>
      <div class="card__body composer">

        <section class="msec">
          <h3 class="msec__title">1 &middot; Recipients</h3>
          <div class="radios">
            <?php foreach ($audiences as $i => $a): ?>
              <label class="radio">
                <input type="radio" name="aud" data-aud="<?= $a['key'] ?>" data-count="<?= $a['count'] ?>"<?= $i === 0 ? ' checked' : '' ?>>
                <span><?= htmlspecialchars($a['label']) ?><?= $a['count'] ? ' (' . $a['count'] . ')' : '' ?></span>
              </label>
            <?php endforeach; ?>
          </div>

          <div id="manualPick" hidden>
            <div class="picklist__bar">
              <span><strong id="pickCount">0</strong> of <?= count($churches) ?> selected</span>
              <span class="picklist__links">
                <a href="#" id="pickAll">Select All</a>
                <a href="#" id="pickNone">Deselect All</a>
              </span>
            </div>
            <label class="field"><span class="field__label">Find churches</span>
              <span class="field__input"><i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" placeholder="Search by name or code..."></span></label>
            <ul class="picklist">
              <?php foreach ($churches as [$in, $name, $code, $acct, $email, $phone]): ?>
                <li>
                  <span class="church">
                    <span class="church__avatar"><?= $in ?></span>
                    <span class="church__text">
                      <strong><?= htmlspecialchars($name) ?></strong>
                      <small><?= $code ?> <span class="pill pill--<?= strtolower($acct) ?> pill--xs"><?= $acct ?></span></small>
                      <small class="muted"><?= htmlspecialchars($email) ?> &middot; <?= $phone ?></small>
                    </span>
                  </span>
                  <input type="checkbox" class="bigcheck pick-check">
                </li>
              <?php endforeach; ?>
            </ul>
          </div>

          <p class="recipline"><i class="fa-solid fa-users"></i>
            <span id="recipLine">47 churches selected &middot; 47 emails &middot; 47 SMS</span></p>
        </section>

        <section class="msec">
          <h3 class="msec__title">2 &middot; Channel</h3>
          <div class="radios">
            <label class="radio"><input type="checkbox" id="chEmail" checked><span><i class="fa-solid fa-envelope"></i> Send via Email</span></label>
            <label class="radio"><input type="checkbox" id="chSms" checked><span><i class="fa-solid fa-comment-sms"></i> Send via SMS</span></label>
          </div>
          <p class="fieldnote note--berry" id="chWarn" hidden>
            <i class="fa-solid fa-triangle-exclamation"></i> Choose at least one channel.</p>
        </section>

        <section class="msec">
          <h3 class="msec__title">3 &middot; Message</h3>
          <label class="field"><span class="field__label">Template</span>
            <select><?php foreach ($templates as $t): ?><option><?= htmlspecialchars($t) ?></option><?php endforeach; ?></select></label>

          <label class="field" id="subjField"><span class="field__label">Subject</span>
            <input type="text" id="subject" value="Your subscription expires in {days_remaining} days"></label>

          <label class="field"><span class="field__label">Message</span>
            <textarea rows="6" id="body">Dear {contact_person},

Your Mutendi CMS subscription for {church_name} ({church_code}) expires on {expiry_date}. Please arrange renewal to avoid any interruption to your records.

Thank you.</textarea></label>

          <span class="field__label">Insert a merge tag</span>
          <div class="chips">
            <?php foreach ($mergeTags as $tag): ?>
              <button class="chip" type="button" data-tag="<?= $tag ?>"><?= $tag ?></button>
            <?php endforeach; ?>
          </div>

          <p class="charcount" id="charCount"><span id="charNum">0</span> / 160 characters &middot; <span id="smsNum">1</span> SMS</p>
        </section>

        <section class="msec">
          <h3 class="msec__title">4 &middot; Delivery</h3>
          <div class="radios">
            <label class="radio"><input type="radio" name="when" data-when="now" checked><span>Send immediately</span></label>
            <label class="radio"><input type="radio" name="when" data-when="later"><span>Schedule for later</span></label>
          </div>
          <div class="field-row" id="schedFields" hidden>
            <label class="field"><span class="field__label">Date</span><input type="date"></label>
            <label class="field"><span class="field__label">Time</span><input type="time"></label>
          </div>
          <label class="check-row"><input type="checkbox"><span>Send a copy to my email</span></label>
        </section>

      </div>
      <div class="card__foot card__foot--split">
        <button class="btn" type="button">Save as Draft</button>
        <span class="composer__actions">
          <button class="btn" type="button" data-modal="modalPreview"><i class="fa-regular fa-eye"></i> Preview</button>
          <button class="btn btn--primary" type="button" data-modal="modalConfirm"><i class="fa-solid fa-paper-plane"></i> Send Notification</button>
        </span>
      </div>
    </div>

    <!-- RIGHT: sticky summary + preview -->
    <div class="sticky-col">
      <div class="card">
        <div class="card__head"><h2>Summary</h2></div>
        <ul class="sumlist">
          <li><span>Recipients</span><strong id="sumRecip">47 churches</strong></li>
          <li><span>Channels</span><strong id="sumChan">Email + SMS</strong></li>
          <li><span>Estimated emails</span><strong id="sumEmail">47</strong></li>
          <li><span>Estimated SMS</span><strong id="sumSms">47</strong></li>
          <li><span>SMS credits used</span><strong id="sumCredits">47</strong></li>
          <li><span>Delivery</span><strong id="sumWhen">Immediately</strong></li>
        </ul>
      </div>

      <div class="card">
        <div class="card__head"><h2>Preview</h2></div>
        <div class="card__body">
          <div class="tabs tabs--sm">
            <button class="tab is-on" type="button" data-pv="email">Email</button>
            <button class="tab" type="button" data-pv="sms">SMS</button>
          </div>

          <div class="pvpane" data-pvpane="email">
            <p class="pvpane__meta">To: <strong>admin@zccmbungo.co.zw</strong></p>
            <p class="pvpane__subject" id="pvSubject">Your subscription expires in 4 days</p>
            <div class="pvpane__body" id="pvBody"></div>
          </div>

          <div class="pvpane" data-pvpane="sms" hidden>
            <p class="pvpane__meta">To: <strong>+263 772 145 880</strong></p>
            <div class="pvphone"><div class="pvphone__bubble" id="pvSms"></div></div>
          </div>

          <p class="pvpane__note">Merge tags filled using <strong>ZCC Mbungo</strong> as a sample.</p>
        </div>
      </div>
    </div>
  </div>

  <!-- ============ RECENT NOTIFICATIONS ============ -->
  <div class="card">
    <div class="card__head"><h2>Recent Notifications</h2></div>
    <div class="table-wrap">
      <table class="table table--churches">
        <thead>
          <tr>
            <th class="col-num">#</th>
            <th>Subject / Message</th>
            <th>Recipients</th>
            <th>Channel</th>
            <th>Sent On</th>
            <th>Delivered</th>
            <th>Status</th>
            <th class="col-actions">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($history as $i => $h): ?>
            <tr>
              <td class="col-num muted"><?= $i + 1 ?></td>
              <td>
                <span class="church__text anntext">
                  <strong><?= htmlspecialchars($h['subject']) ?></strong>
                  <small><?= htmlspecialchars($h['excerpt']) ?></small>
                </span>
              </td>
              <td class="nowrap"><?= $h['recipients'] ?> churches</td>
              <td class="nowrap">
                <?php if ($h['email']): ?><span class="chan chan--email"><i class="fa-solid fa-envelope"></i> Email</span><?php endif; ?>
                <?php if ($h['sms']): ?><span class="chan chan--sms"><i class="fa-solid fa-comment-sms"></i> SMS</span><?php endif; ?>
              </td>
              <td class="nowrap">
                <?php if ($h['sent'] !== ''): ?>
                  <span class="stack"><strong><?= $h['sent'] ?></strong><small><?= $h['ago'] ?></small></span>
                <?php else: ?><span class="muted">&mdash;</span><?php endif; ?>
              </td>
              <td>
                <?php if ($h['recipients'] > 0): ?>
                  <span class="setup">
                    <span class="setup__num"><?= $h['delivered'] ?> / <?= $h['recipients'] ?></span>
                    <span class="bar"><i style="width: <?= (int) round($h['delivered'] / $h['recipients'] * 100) ?>%"></i></span>
                  </span>
                <?php else: ?><span class="muted">&mdash;</span><?php endif; ?>
              </td>
              <td><span class="pill <?= notif_pill($h['status']) ?>"><?= $h['status'] ?></span></td>
              <td class="col-actions">
                <div class="row-actions">
                  <button class="ico-btn" type="button" title="View" aria-label="View" data-modal="modalDetails"><i class="fa-regular fa-eye"></i></button>
                  <button class="ico-btn" type="button" title="Resend" aria-label="Resend"><i class="fa-solid fa-rotate-right"></i></button>
                  <button class="ico-btn" type="button" title="Duplicate" aria-label="Duplicate"><i class="fa-solid fa-copy"></i></button>
                  <button class="ico-btn" type="button" title="Delete" aria-label="Delete"><i class="fa-solid fa-trash"></i></button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="card__foot"><a href="#">View all &rarr;</a></div>
  </div>

  <footer class="foot">
    <p class="foot__brand">Mutendi CMS</p>
    <p class="foot__copy">&copy; <?= date('Y') ?> Mutendi. All rights reserved.</p>
    <p class="foot__meta">Super Admin &middot; v1.0</p>
  </footer>

</main>

<!-- ==================== MODALS (static) ==================== -->

<!-- a) PREVIEW -->
<div class="modal" id="modalPreview" hidden>
  <div class="modal__box">
    <div class="modal__head">
      <h2><i class="fa-regular fa-eye"></i> Message Preview</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <div class="tabs tabs--sm">
        <button class="tab is-on" type="button" data-mpv="email">Email</button>
        <button class="tab" type="button" data-mpv="sms">SMS</button>
      </div>

      <div class="pvpane" data-mpvpane="email">
        <p class="pvpane__meta">To: <strong>admin@zccmbungo.co.zw</strong></p>
        <p class="pvpane__subject" id="mpvSubject">Your subscription expires in 4 days</p>
        <div class="pvpane__body" id="mpvBody"></div>
      </div>

      <div class="pvpane" data-mpvpane="sms" hidden>
        <p class="pvpane__meta">To: <strong>+263 772 145 880</strong></p>
        <div class="pvphone"><div class="pvphone__bubble" id="mpvSms"></div></div>
      </div>

      <section class="msec">
        <h3 class="msec__title">Recipients</h3>
        <dl class="summary">
          <div><dt>Audience</dt><dd id="mpvAud">All Churches</dd></div>
          <div><dt>Churches</dt><dd id="mpvCount">47</dd></div>
          <div><dt>Channels</dt><dd id="mpvChan">Email + SMS</dd></div>
          <div><dt>Sample used</dt><dd>ZCC Mbungo (ZCC-001)</dd></div>
        </dl>
      </section>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Close</button>
      <button class="btn btn--primary" type="button" data-modal="modalConfirm">Send Notification</button>
    </div>
  </div>
</div>

<!-- b) CONFIRM SEND -->
<div class="modal" id="modalConfirm" hidden>
  <div class="modal__box modal__box--sm">
    <div class="modal__head">
      <h2><i class="fa-solid fa-paper-plane"></i> Confirm &amp; Send</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <dl class="summary">
        <div><dt>Recipients</dt><dd id="cfRecip">47 churches</dd></div>
        <div><dt>Channels</dt><dd id="cfChan">Email + SMS</dd></div>
        <div><dt>Emails</dt><dd id="cfEmail">47</dd></div>
        <div><dt>SMS</dt><dd id="cfSms">47</dd></div>
        <div><dt>SMS credits</dt><dd id="cfCredits">47</dd></div>
        <div><dt>Delivery</dt><dd id="cfWhen">Immediately</dd></div>
      </dl>
      <p class="modal__warn"><i class="fa-solid fa-triangle-exclamation"></i>
        This cannot be undone once sent.</p>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Cancel</button>
      <button class="btn btn--primary" type="button"><i class="fa-solid fa-paper-plane"></i> Confirm &amp; Send</button>
    </div>
  </div>
</div>

<!-- c) NOTIFICATION DETAILS -->
<div class="modal" id="modalDetails" hidden>
  <div class="modal__box">
    <div class="modal__head">
      <h2><i class="fa-regular fa-eye"></i> Notification Details</h2>
      <button class="modal__x" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal__body">
      <section class="msec">
        <h3 class="msec__title">Message</h3>
        <p class="pvpane__subject">Your subscription expires in 7 days</p>
        <div class="pvpane__body">Dear Bishop N. Mutendi,<br><br>Your Mutendi CMS subscription for ZCC Mbungo (ZCC-001) expires on 26 Aug 2026. Please arrange renewal to avoid any interruption to your records.<br><br>Thank you.</div>
      </section>

      <section class="msec">
        <h3 class="msec__title">Delivery &mdash; 11 of 12 delivered</h3>
        <span class="bar"><i style="width: 92%"></i></span>
        <ul class="picklist">
          <?php foreach (array_slice($churches, 0, 6) as $k => [$in, $name, $code, $acct, $email, $phone]): ?>
            <?php $state = $k === 4 ? 'Failed' : ($k === 5 ? 'Pending' : 'Delivered'); ?>
            <li>
              <span class="church">
                <span class="church__avatar"><?= $in ?></span>
                <span class="church__text">
                  <strong><?= htmlspecialchars($name) ?></strong>
                  <small><?= $code ?> <span class="pill pill--<?= strtolower($acct) ?> pill--xs"><?= $acct ?></span></small>
                </span>
              </span>
              <span class="pill pill--<?= strtolower($state) ?>"><?= $state ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    </div>
    <div class="modal__foot">
      <button class="btn" type="button" data-close>Close</button>
      <button class="btn btn--primary" type="button"><i class="fa-solid fa-rotate-right"></i> Resend Failed</button>
    </div>
  </div>
</div>

<script>
/* Composer: recipient counts, channel-dependent fields, merge tags, live
   preview and summary. Dropdowns and modals as on the other pages. */
(function () {
  'use strict';

  var SAMPLE = { '{church_name}': 'ZCC Mbungo', '{contact_person}': 'Bishop N. Mutendi',
                 '{expiry_date}': '26 Aug 2026', '{days_remaining}': '4',
                 '{church_code}': 'ZCC-001', '{admin_name}': 'Super Admin' };

  var subject = document.getElementById('subject'),
      body    = document.getElementById('body'),
      chEmail = document.getElementById('chEmail'),
      chSms   = document.getElementById('chSms'),
      picks   = [].slice.call(document.querySelectorAll('.pick-check'));

  function fill(text) {
    Object.keys(SAMPLE).forEach(function (k) { text = text.split(k).join(SAMPLE[k]); });
    return text;
  }
  function esc(t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

  function audience() {
    var a = document.querySelector('[data-aud]:checked');
    if (!a) { return { label: 'All Churches', count: 47, manual: false }; }
    if (a.dataset.aud === 'manual') {
      return { label: 'Selected manually', count: picks.filter(function (p) { return p.checked; }).length, manual: true };
    }
    return { label: a.parentNode.textContent.trim(), count: parseInt(a.dataset.count, 10), manual: false };
  }

  function render() {
    var aud = audience(),
        e = chEmail.checked, s = chSms.checked,
        chan = e && s ? 'Email + SMS' : e ? 'Email only' : s ? 'SMS only' : 'None',
        later = document.querySelector('[data-when]:checked').dataset.when === 'later',
        when = later ? 'Scheduled' : 'Immediately',
        smsBody = fill(body.value).replace(/\n+/g, ' ').trim();

    document.getElementById('manualPick').hidden = !aud.manual;
    document.getElementById('subjField').hidden = !e;
    document.getElementById('charCount').hidden = !s;
    document.getElementById('chWarn').hidden = e || s;
    document.getElementById('schedFields').hidden = !later;
    var pc = document.getElementById('pickCount');
    if (pc) { pc.textContent = picks.filter(function (p) { return p.checked; }).length; }

    document.getElementById('recipLine').textContent =
      aud.count + ' churches selected · ' + (e ? aud.count : 0) + ' emails · ' + (s ? aud.count : 0) + ' SMS';

    document.getElementById('charNum').textContent = smsBody.length;
    document.getElementById('smsNum').textContent = Math.max(1, Math.ceil(smsBody.length / 160));

    var set = function (id, val) { var el = document.getElementById(id); if (el) { el.textContent = val; } };
    set('sumRecip', aud.count + ' churches');
    set('sumChan', chan);
    set('sumEmail', e ? aud.count : 0);
    set('sumSms', s ? aud.count : 0);
    set('sumCredits', s ? aud.count * Math.max(1, Math.ceil(smsBody.length / 160)) : 0);
    set('sumWhen', when);
    set('cfRecip', aud.count + ' churches'); set('cfChan', chan);
    set('cfEmail', e ? aud.count : 0); set('cfSms', s ? aud.count : 0);
    set('cfCredits', s ? aud.count * Math.max(1, Math.ceil(smsBody.length / 160)) : 0);
    set('cfWhen', when);
    set('mpvAud', aud.label); set('mpvCount', aud.count); set('mpvChan', chan);

    ['pvSubject', 'mpvSubject'].forEach(function (id) { set(id, fill(subject.value)); });
    ['pvBody', 'mpvBody'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) { el.innerHTML = esc(fill(body.value)).replace(/\n/g, '<br>'); }
    });
    ['pvSms', 'mpvSms'].forEach(function (id) { set(id, smsBody); });
  }

  [subject, body].forEach(function (el) { el.addEventListener('input', render); });
  [chEmail, chSms].forEach(function (el) { el.addEventListener('change', render); });
  document.querySelectorAll('[data-aud], [data-when]').forEach(function (el) { el.addEventListener('change', render); });
  picks.forEach(function (p) { p.addEventListener('change', render); });
  document.getElementById('pickAll').addEventListener('click', function (e) {
    e.preventDefault(); picks.forEach(function (p) { p.checked = true; }); render();
  });
  document.getElementById('pickNone').addEventListener('click', function (e) {
    e.preventDefault(); picks.forEach(function (p) { p.checked = false; }); render();
  });

  /* Merge-tag chips insert at the cursor. */
  document.querySelectorAll('.chip[data-tag]').forEach(function (chip) {
    chip.addEventListener('click', function () {
      var pos = body.selectionStart || body.value.length;
      body.value = body.value.slice(0, pos) + chip.dataset.tag + body.value.slice(pos);
      body.focus();
      body.selectionStart = body.selectionEnd = pos + chip.dataset.tag.length;
      render();
    });
  });

  /* Preview tabs, both in the sidebar card and the modal. */
  function wireTabs(attr, paneAttr) {
    var tabs = [].slice.call(document.querySelectorAll('[' + attr + ']'));
    tabs.forEach(function (t) {
      t.addEventListener('click', function () {
        tabs.forEach(function (x) { x.classList.toggle('is-on', x === t); });
        document.querySelectorAll('[' + paneAttr + ']').forEach(function (p) {
          p.hidden = p.getAttribute(paneAttr) !== t.getAttribute(attr);
        });
      });
    });
  }
  wireTabs('data-pv', 'data-pvpane');
  wireTabs('data-mpv', 'data-mpvpane');

  /* Dropdowns + modals. */
  document.addEventListener('click', function (e) {
    var trigger = e.target.closest('.dropdown__trigger');
    document.querySelectorAll('.dropdown.is-open').forEach(function (d) {
      if (!trigger || d !== trigger.parentNode) { d.classList.remove('is-open'); }
    });
    if (trigger) { e.preventDefault(); trigger.parentNode.classList.toggle('is-open'); }
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

  render();
})();
</script>
</body>
</html>

<?php
$brand = 'Mutendi';

require __DIR__ . '/resources/partials/icons.php';

$wa   = whatsapp_link($whatsappNumber, "Hi Mutendi, I have a question about your privacy policy");
$mail = mail_link($contactEmail, 'Privacy enquiry');

$navLinks = [
    ['label' => 'Features',     'href' => 'index.php#features'],
    ['label' => 'Records',      'href' => 'index.php#records', 'icon' => 'register', 'badge' => 'New'],
    ['label' => 'For Dioceses', 'href' => 'index.php#dioceses'],
    ['label' => 'Parishes',     'href' => 'index.php#parishes'],
    ['label' => 'Contact',      'href' => 'contact.php'],
];

/* The table of contents and the headings are generated from one list, so they
   can never drift apart. */
$sections = [
    'introduction'  => 'Introduction',
    'collect'       => 'Information We Collect',
    'use'           => 'How We Use Information',
    'roles'         => 'The Church&rsquo;s Role and Ours',
    'sharing'       => 'Sharing and Disclosure',
    'security'      => 'Data Security',
    'retention'     => 'Data Retention',
    'rights'        => 'Your Rights',
    'communications'=> 'Communications',
    'cookies'       => 'Cookies and Local Storage',
    'children'      => 'Children&rsquo;s Information',
    'changes'       => 'Changes to This Policy',
    'contact'       => 'Contact Us',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Privacy Policy — <?= $brand ?></title>
<meta name="description" content="How <?= $brand ?> handles church and member information: what we collect, how it is used and secured, how long it is kept, and the difference between what the church controls and what we do.">
<meta property="og:type" content="website">
<meta property="og:title" content="Privacy Policy — <?= $brand ?>">
<meta property="og:description" content="What we collect, how it is used and secured, how long it is kept, and the difference between what the church controls and what we do.">
<meta property="og:image" content="resources/img/logo.png">
<link rel="icon" type="image/png" href="resources/img/logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="resources/css/style.css">
<script>
  // Applied before paint to avoid a flash of the wrong theme.
  try {
    var t = localStorage.getItem('mutendi-theme');
    if (t === 'dark' || (!t && matchMedia('(prefers-color-scheme: dark)').matches)) {
      document.documentElement.classList.add('dark');
    }
  } catch (e) {}
</script>
</head>
<body>

<div class="page-glow" aria-hidden="true"></div>
<div class="page-grid" aria-hidden="true"></div>

<header class="nav">
  <div class="nav__inner">
    <a class="brand" href="index.php">
      <img class="brand__mark" src="resources/img/logo.png" alt="<?= $brand ?> logo">
      <span class="brand__name"><?= $brand ?></span>
    </a>

    <nav class="nav__links" aria-label="Primary">
      <?php foreach ($navLinks as $link): ?>
        <a class="nav__link" href="<?= $link['href'] ?>">
          <?php if (!empty($link['icon'])): ?>
            <?= icon($link['icon'], 'nav__icon') ?>
          <?php endif; ?>
          <?= $link['label'] ?>
          <?php if (!empty($link['badge'])): ?>
            <span class="badge"><?= $link['badge'] ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
      <a class="nav__link nav__link--mobile" href="index.php#signin">Sign in</a>
      <a class="nav__link nav__link--mobile nav__link--cta" href="contact.php">Get Started</a>
    </nav>

    <div class="nav__actions">
      <button class="theme-toggle" type="button" aria-label="Toggle dark mode">
        <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
      </button>
      <a class="nav__signin" href="index.php#signin">Sign in</a>
      <a class="btn btn--dark" href="contact.php">Get Started</a>
    </div>

    <button class="nav__burger" type="button" aria-label="Open menu" aria-expanded="false">
      <span></span><span></span>
    </button>
  </div>
</header>

<main>

<section class="pagehero">
  <div class="pagehero__inner">
    <span class="pill">Legal</span>
    <h1 class="pagehero__title">Privacy Policy</h1>
    <p class="pagehero__lead">Last updated <?= date('j F Y') ?></p>
  </div>
</section>

<div class="doc">
  <div class="doc__inner">

    <!-- Sticky on desktop, a plain list on narrow screens. -->
    <nav class="toc" aria-label="On this page">
      <p class="toc__head">On this page</p>
      <ol class="toc__list">
        <?php foreach ($sections as $id => $title): ?>
          <li><a href="#<?= $id ?>"><?= $title ?></a></li>
        <?php endforeach; ?>
      </ol>
    </nav>

    <article class="doc__body">

      <aside class="doc__callout" role="note">
        <span class="doc__callout-ico" aria-hidden="true"><?= icon('shield') ?></span>
        <p>
          This policy is provided as a general statement of our practices. Churches using
          <?= $brand ?> remain responsible for how they collect and use their members&rsquo;
          information under applicable law.
        </p>
      </aside>

      <section id="introduction">
        <h2>1. Introduction</h2>
        <p>
          <?= $brand ?> is church management software built in Zimbabwe for parishes,
          congregations and dioceses across Africa. It keeps membership rolls, sacramental
          registers, attendance, giving and parish accounts, and rolls those records up from
          the local parish to the diocese and head office.
        </p>
        <p>
          This policy explains what information passes through the system, what we do with it,
          and what we do not do with it. It covers the <?= $brand ?> website and the
          application your church signs in to.
        </p>
        <p>
          One distinction runs through everything below and is worth stating plainly at the
          start: <strong>your church owns its members&rsquo; information. We do not.</strong>
          The church decides what to record, who may see it, and when it is deleted. We provide
          the software that holds it and act on the church&rsquo;s instructions.
        </p>
      </section>

      <section id="collect">
        <h2>2. Information We Collect</h2>

        <h3>a) Information churches provide about their members</h3>
        <p>
          A church enters this because it needs it to run church life. What is held depends on
          what that church chooses to record, and typically includes:
        </p>
        <ul>
          <li>Names, and the member number the church assigns</li>
          <li>Contact details — phone numbers, email addresses and postal or physical addresses</li>
          <li>Dates of birth, and other dates the church keeps such as baptism or marriage</li>
          <li>Household and family relationships between members</li>
          <li>Attendance records for services, meetings and cell groups</li>
          <li>Contributions and giving history, including pledges toward projects</li>
          <li>Department, ministry and cell group membership</li>
          <li>Any custom fields the church adds for its own purposes</li>
        </ul>
        <p>
          We do not decide what goes in these records and we do not add to them ourselves.
        </p>

        <h3>b) Information we collect from church staff using the system</h3>
        <p>
          This is about the people who sign in and use <?= $brand ?>, not about the
          congregation:
        </p>
        <ul>
          <li>Account details — name, email address, role and the church they belong to</li>
          <li>Login records, including the time of sign-in and whether it succeeded</li>
          <li>Device and browser information, and the IP address the request came from</li>
          <li>Usage activity — which pages were opened and what was changed, so that a
              record can be traced back to whoever changed it</li>
        </ul>
      </section>

      <section id="use">
        <h2>3. How We Use Information</h2>
        <p>We use what we hold for a short list of things:</p>
        <ul>
          <li><strong>To provide the service</strong> — to store, display and roll up the
              records your church keeps</li>
          <li><strong>To support you</strong> — so that when someone contacts us about a
              problem we can see enough to help</li>
          <li><strong>To keep the system secure</strong> — sign-in records and activity logs
              are how unauthorised access is spotted</li>
          <li><strong>To detect misuse</strong> — of the platform, or of a church&rsquo;s account</li>
          <li><strong>To improve the product</strong> — knowing which parts are used, and
              where people get stuck, is how it gets better</li>
        </ul>
        <p class="doc__emphasis">
          We do not sell data. We do not share it with data brokers. We do not use church
          members&rsquo; information for advertising, and we do not build advertising profiles
          from it.
        </p>
      </section>

      <section id="roles">
        <h2>4. The Church&rsquo;s Role and Ours</h2>
        <p>
          The church is the owner and controller of its members&rsquo; information. It decides
          what to collect, why, who inside the church may see it, and how long to keep it. If a
          member asks what is held about them, that question belongs to the church.
        </p>
        <p>
          <?= $brand ?> is the processor. We hold and process that information on the
          church&rsquo;s instructions in order to run the service. We do not use a church&rsquo;s
          member data for our own purposes, and we do not move it between churches.
        </p>
        <p>
          In practice this means the church sets the rules and we enforce them. Where a church
          is subject to a data protection law, the obligations of a controller under that law
          sit with the church, and we will help it meet them.
        </p>
      </section>

      <section id="sharing">
        <h2>5. Sharing and Disclosure</h2>
        <p>Information is not shared with anyone outside <?= $brand ?>, with three exceptions:</p>
        <ul>
          <li><strong>Service providers we need to run the system.</strong> Hosting and backup
              providers hold the data, and SMS and email delivery providers carry the messages
              your church sends. They are given only what is needed to do that, and are not
              permitted to use it for anything else.</li>
          <li><strong>Where the law requires it.</strong> If we receive a lawful order we will
              comply, and we will tell the church unless we are prohibited from doing so.</li>
          <li><strong>Where the church asks us to.</strong> If your church instructs us to
              share or export its data, we will.</li>
        </ul>
      </section>

      <section id="security">
        <h2>6. Data Security</h2>
        <p>What protects your records, in plain terms:</p>
        <ul>
          <li><strong>Encrypted connections.</strong> Everything travelling between a device
              and the system is encrypted in transit.</li>
          <li><strong>Access controls and role-based permissions.</strong> An usher does not
              see giving figures. A department head sees their department. Each church decides
              who holds which role, and the system enforces it on every page.</li>
          <li><strong>Activity logging.</strong> Changes to records are logged with who made
              them and when, so a question about a figure can be answered.</li>
          <li><strong>Regular backups.</strong> Taken routinely so that a mistake or a failure
              does not mean losing a parish register.</li>
          <li><strong>Separation between churches.</strong> Each church&rsquo;s data is kept
              apart from every other church&rsquo;s. One church cannot see another&rsquo;s
              records.</li>
        </ul>
        <p>
          No system is perfectly secure, and we will not pretend otherwise. If a breach affects
          your church&rsquo;s data we will tell you promptly and tell you what we know.
        </p>
      </section>

      <section id="retention">
        <h2>7. Data Retention</h2>
        <p>
          While your subscription is active we keep your data for as long as you want it kept.
          Records are yours and we do not delete them on our own initiative.
        </p>
        <p>
          If a subscription lapses, the account becomes read-only rather than disappearing —
          you can still sign in and export. We keep the data for a retention window of
          <strong>90 days</strong> after that, so that a church which has simply had a difficult
          few months does not lose its registers. After the window closes the data is
          permanently deleted, and deletion is not reversible.
        </p>
        <p>
          You may export your data at any time, in a usable format, whether or not your
          subscription is current. You do not need our permission and there is no charge for it.
        </p>
      </section>

      <section id="rights">
        <h2>8. Your Rights</h2>
        <p>
          Depending on where you are, you may have the right to ask for access to the
          information held about you, to have it corrected, to have it deleted, and to receive
          a copy of it.
        </p>
        <p>
          <strong>If you are a member of a church using <?= $brand ?>, please ask your church
          first.</strong> They hold the record and they are the ones who can act on it. We do
          not have the standing to change or delete a member&rsquo;s record on our own — nor
          should we.
        </p>
        <p>
          If your church needs help answering such a request, we will assist them, including
          locating, exporting or deleting the record. If you cannot get a response from your
          church, contact us and we will do what we properly can.
        </p>
      </section>

      <section id="communications">
        <h2>9. Communications</h2>
        <p>
          We send service and account messages to the staff who use the system — things like
          billing notices, security alerts and changes that affect how it works. These are part
          of the service and are not marketing.
        </p>
        <p>
          <strong>SMS and email sent to church members are initiated by the church, not by us.</strong>
          When a parish sends an announcement or a giving statement, that message is the
          church&rsquo;s, sent on its instruction through our delivery providers. We do not
          message a church&rsquo;s congregation on our own account.
        </p>
      </section>

      <section id="cookies">
        <h2>10. Cookies and Local Storage</h2>
        <p>
          The application keeps a small amount of information in your browser, and it is all
          functional:
        </p>
        <ul>
          <li><strong>A session cookie</strong> so that the system knows you are signed in as
              you move between pages. It ends when you sign out.</li>
          <li><strong>Preferences in local storage</strong> — small things like whether you
              chose light or dark mode, or which branch you were last looking at, so the
              system does not forget between visits.</li>
        </ul>
        <p>
          We do not use advertising cookies or third-party tracking cookies, and there is
          nothing here that follows you to other websites.
        </p>
      </section>

      <section id="children">
        <h2>11. Children&rsquo;s Information</h2>
        <p>
          Churches record information about children — Sunday school registers, children&rsquo;s
          ministry attendance, baptism records, family relationships. This is a normal part of
          church life and the system supports it.
        </p>
        <p>
          Where a church records information about a minor, the church is responsible for
          obtaining appropriate parental or guardian consent and for handling that information
          with the extra care it deserves. We provide the permissions and controls; the
          judgement about consent is the church&rsquo;s.
        </p>
      </section>

      <section id="changes">
        <h2>12. Changes to This Policy</h2>
        <p>
          We will update this policy as the system changes. The date at the top always shows
          when it last changed.
        </p>
        <p>
          If a change materially affects how church or member information is handled, we will
          notify churches directly rather than quietly amending the page and hoping it is
          noticed.
        </p>
      </section>

      <section id="contact">
        <h2>13. Contact Us</h2>
        <p>
          If anything here is unclear, or you want to talk through how this applies to your
          church, we would rather you asked.
        </p>

        <div class="doc__contact">
          <p class="doc__contact-title">Talk to us about privacy</p>
          <p class="doc__contact-text">
            We answer questions about data handling the same way we answer everything else —
            directly, and usually within one business day.
          </p>
          <div class="doc__contact-actions">
            <a class="btn btn--wa btn--lg" href="<?= $wa ?>" target="_blank" rel="noopener noreferrer">
              <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15s-.77.96-.94 1.16c-.17.2-.35.22-.64.08-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.6.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.61-.92-2.21-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37s-1.04 1.02-1.04 2.48 1.07 2.88 1.22 3.08c.15.2 2.1 3.2 5.08 4.49.71.3 1.26.49 1.69.63.71.22 1.36.19 1.87.12.57-.09 1.76-.72 2-1.41.25-.7.25-1.29.18-1.42-.07-.13-.27-.2-.57-.35M12.05 21.8h-.02a9.8 9.8 0 0 1-4.99-1.37l-.36-.21-3.71.97 1-3.62-.24-.37a9.79 9.79 0 0 1-1.5-5.22c0-5.41 4.41-9.81 9.83-9.81a9.75 9.75 0 0 1 6.94 2.88 9.72 9.72 0 0 1 2.87 6.94c0 5.41-4.41 9.81-9.82 9.81m8.36-18.17A11.7 11.7 0 0 0 12.05 0C5.55 0 .26 5.29.26 11.79c0 2.08.54 4.11 1.58 5.9L.16 24l6.45-1.69a11.75 11.75 0 0 0 5.44 1.34h.01c6.5 0 11.79-5.29 11.79-11.79 0-3.15-1.23-6.11-3.45-8.34"/></svg>
              Chat on WhatsApp
            </a>
            <a class="btn btn--ghost btn--lg" href="<?= $mail ?>">
              <?= icon('mail') ?>
              <?= $contactEmail ?>
            </a>
          </div>
        </div>
      </section>

    </article>
  </div>
</div>

</main>

<?php require __DIR__ . '/resources/partials/footer.php'; ?>

<a class="wa-float" href="<?= whatsapp_link($whatsappNumber) ?>" target="_blank" rel="noopener noreferrer"
   aria-label="Chat with us on WhatsApp">
  <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15s-.77.96-.94 1.16c-.17.2-.35.22-.64.08-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.6.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.61-.92-2.21-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37s-1.04 1.02-1.04 2.48 1.07 2.88 1.22 3.08c.15.2 2.1 3.2 5.08 4.49.71.3 1.26.49 1.69.63.71.22 1.36.19 1.87.12.57-.09 1.76-.72 2-1.41.25-.7.25-1.29.18-1.42-.07-.13-.27-.2-.57-.35M12.05 21.8h-.02a9.8 9.8 0 0 1-4.99-1.37l-.36-.21-3.71.97 1-3.62-.24-.37a9.79 9.79 0 0 1-1.5-5.22c0-5.41 4.41-9.81 9.83-9.81a9.75 9.75 0 0 1 6.94 2.88 9.72 9.72 0 0 1 2.87 6.94c0 5.41-4.41 9.81-9.82 9.81m8.36-18.17A11.7 11.7 0 0 0 12.05 0C5.55 0 .26 5.29.26 11.79c0 2.08.54 4.11 1.58 5.9L.16 24l6.45-1.69a11.75 11.75 0 0 0 5.44 1.34h.01c6.5 0 11.79-5.29 11.79-11.79 0-3.15-1.23-6.11-3.45-8.34"/></svg>
  <span class="wa-float__tip">Chat with us</span>
</a>

<script src="resources/js/main.js"></script>
<script>
/* Highlights the section currently in view in the table of contents. */
(function () {
  var links = Array.prototype.slice.call(document.querySelectorAll('.toc__list a'));
  var sections = links.map(function (a) { return document.querySelector(a.getAttribute('href')); })
                      .filter(Boolean);
  if (!sections.length || !('IntersectionObserver' in window)) { return; }

  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (e) {
      if (!e.isIntersecting) { return; }
      links.forEach(function (a) {
        a.classList.toggle('is-current', a.getAttribute('href') === '#' + e.target.id);
      });
    });
  }, { rootMargin: '-96px 0px -70% 0px' });

  sections.forEach(function (s) { io.observe(s); });
})();
</script>
</body>
</html>

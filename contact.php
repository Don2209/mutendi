<?php
$brand = 'Mutendi';

require __DIR__ . '/resources/partials/icons.php';

$wa   = whatsapp_link($whatsappNumber);
$mail = mail_link($contactEmail);

/* The same entries as index, but anchored back to the home page so they still
   work from here. Only the Contact link changes destination. */
$navLinks = [
    ['label' => 'Features',     'href' => 'index.php#features'],
    ['label' => 'Records',      'href' => 'index.php#records', 'icon' => 'register', 'badge' => 'New'],
    ['label' => 'For Dioceses', 'href' => 'index.php#dioceses'],
    ['label' => 'Parishes',     'href' => 'index.php#parishes'],
    ['label' => 'Contact',      'href' => 'contact.php'],
];

$faqs = [
    [
        'How much does it cost?',
        'Pricing depends on how many members you keep on the roll and which parts of the
         system you use, so there is no single figure to quote. Tell us roughly how big your
         church is and we will work out something fair — most parishes are surprised how
         little it comes to.',
    ],
    [
        'Can we try it first?',
        'Yes. We will set you up with a trial loaded with your own structure — your parishes,
         your departments, your service times — so you are testing the real thing rather than
         a demo. Message us and we will arrange it.',
    ],
    [
        'Do we need internet at the church?',
        'You need a connection to sync, but not to work. Attendance and contributions can be
         captured on a phone at the door and pushed up when a signal is available. Ask us
         about how this behaves on your setup.',
    ],
    [
        'Can it handle multiple branches or parishes?',
        'That is what it was built for. Each parish keeps its own registers and accounts, and
         everything rolls up to the diocese and head office without anyone re-typing it. Tell
         us your structure and we will show you how it maps.',
    ],
    [
        'Is our members&rsquo; data safe?',
        'Your church owns its data and decides who inside it can see what. Connections are
         encrypted, every change is logged, and each church&rsquo;s records are kept separate from
         every other church&rsquo;s. Our <a href="privacy.php">privacy policy</a> sets out the
         detail, and we are happy to talk it through.',
    ],
    [
        'How long does setup take?',
        'A single parish can be running the same week. A diocese with many parishes takes
         longer, mostly because of getting existing registers in. Send us a message and we
         will give you a realistic timeline for your size.',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Contact <?= $brand ?> — Talk to us about your church</title>
<meta name="description" content="Get in touch with <?= $brand ?>. Message us on WhatsApp or email info@mutendi.africa to ask about pricing, book a demo, or find out how church management software would work for your parish or diocese.">
<meta property="og:type" content="website">
<meta property="og:title" content="Contact <?= $brand ?> — Talk to us about your church">
<meta property="og:description" content="Message us on WhatsApp or email info@mutendi.africa. We typically respond within one business day.">
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
    <span class="pill">We&rsquo;re listening</span>
    <h1 class="pagehero__title">Get in Touch</h1>
    <p class="pagehero__lead">We&rsquo;d love to hear from your church.</p>
  </div>
</section>

<section class="contact">
  <div class="contact__inner">

    <!-- ── how to reach us ── -->
    <div class="contact__side">

      <!-- WhatsApp leads: it is how most churches will actually reach us. -->
      <article class="ccard ccard--wa">
        <span class="ccard__ico ccard__ico--wa" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15s-.77.96-.94 1.16c-.17.2-.35.22-.64.08-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.6.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.61-.92-2.21-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37s-1.04 1.02-1.04 2.48 1.07 2.88 1.22 3.08c.15.2 2.1 3.2 5.08 4.49.71.3 1.26.49 1.69.63.71.22 1.36.19 1.87.12.57-.09 1.76-.72 2-1.41.25-.7.25-1.29.18-1.42-.07-.13-.27-.2-.57-.35M12.05 21.8h-.02a9.8 9.8 0 0 1-4.99-1.37l-.36-.21-3.71.97 1-3.62-.24-.37a9.79 9.79 0 0 1-1.5-5.22c0-5.41 4.41-9.81 9.83-9.81a9.75 9.75 0 0 1 6.94 2.88 9.72 9.72 0 0 1 2.87 6.94c0 5.41-4.41 9.81-9.82 9.81m8.36-18.17A11.7 11.7 0 0 0 12.05 0C5.55 0 .26 5.29.26 11.79c0 2.08.54 4.11 1.58 5.9L.16 24l6.45-1.69a11.75 11.75 0 0 0 5.44 1.34h.01c6.5 0 11.79-5.29 11.79-11.79 0-3.15-1.23-6.11-3.45-8.34"/></svg>
        </span>
        <h2 class="ccard__title">Chat with us on WhatsApp</h2>
        <p class="ccard__text">
          The quickest way to reach a person. Send us a message and we will answer
          with something useful rather than a brochure.
        </p>
        <p class="ccard__detail"><?= whatsapp_display($whatsappNumber) ?></p>
        <a class="btn btn--wa btn--lg" href="<?= $wa ?>" target="_blank" rel="noopener noreferrer">
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15s-.77.96-.94 1.16c-.17.2-.35.22-.64.08-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.6.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.61-.92-2.21-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37s-1.04 1.02-1.04 2.48 1.07 2.88 1.22 3.08c.15.2 2.1 3.2 5.08 4.49.71.3 1.26.49 1.69.63.71.22 1.36.19 1.87.12.57-.09 1.76-.72 2-1.41.25-.7.25-1.29.18-1.42-.07-.13-.27-.2-.57-.35M12.05 21.8h-.02a9.8 9.8 0 0 1-4.99-1.37l-.36-.21-3.71.97 1-3.62-.24-.37a9.79 9.79 0 0 1-1.5-5.22c0-5.41 4.41-9.81 9.83-9.81a9.75 9.75 0 0 1 6.94 2.88 9.72 9.72 0 0 1 2.87 6.94c0 5.41-4.41 9.81-9.82 9.81m8.36-18.17A11.7 11.7 0 0 0 12.05 0C5.55 0 .26 5.29.26 11.79c0 2.08.54 4.11 1.58 5.9L.16 24l6.45-1.69a11.75 11.75 0 0 0 5.44 1.34h.01c6.5 0 11.79-5.29 11.79-11.79 0-3.15-1.23-6.11-3.45-8.34"/></svg>
          Message us on WhatsApp
        </a>
      </article>

      <article class="ccard">
        <span class="ccard__ico" aria-hidden="true"><?= icon('mail') ?></span>
        <h2 class="ccard__title">Email us</h2>
        <p class="ccard__text">
          Better for anything long, or if you would like something in writing to take
          to your council.
        </p>
        <p class="ccard__detail"><?= $contactEmail ?></p>
        <a class="btn btn--ghost btn--lg" href="<?= $mail ?>">
          <?= icon('send') ?>
          Send an email
        </a>
      </article>

      <p class="ccard__note">
        <?= icon('clock') ?>
        We typically respond within one business day.
      </p>
    </div>

    <!-- ── the form ── -->
    <div class="contact__form">
      <form class="enquiry" id="enquiryForm" novalidate>
        <h2 class="enquiry__title">Send us an enquiry</h2>
        <p class="enquiry__lead">Tell us a little about your church and we will come back to you.</p>

        <div class="field">
          <label for="fChurch">Church name <span class="req" aria-hidden="true">*</span></label>
          <input class="input" type="text" id="fChurch" name="church" required
                 autocomplete="organization" placeholder="St Mary&rsquo;s Parish">
          <p class="field__err" data-err-for="fChurch">Please tell us your church&rsquo;s name.</p>
        </div>

        <div class="field">
          <label for="fPerson">Contact person <span class="req" aria-hidden="true">*</span></label>
          <input class="input" type="text" id="fPerson" name="person" required
                 autocomplete="name" placeholder="Your name">
          <p class="field__err" data-err-for="fPerson">Please tell us who we are speaking to.</p>
        </div>

        <div class="field-row">
          <div class="field">
            <label for="fEmail">Email <span class="req" aria-hidden="true">*</span></label>
            <input class="input" type="email" id="fEmail" name="email" required
                   autocomplete="email" placeholder="you@church.org">
            <p class="field__err" data-err-for="fEmail">That does not look like an email address.</p>
          </div>

          <div class="field">
            <label for="fPhone">Phone</label>
            <div class="input-prefixed">
              <span class="input-prefixed__pre" aria-hidden="true">+263</span>
              <input class="input" type="tel" id="fPhone" name="phone"
                     autocomplete="tel" inputmode="tel" placeholder="77 123 4567"
                     aria-describedby="phoneHint">
            </div>
            <p class="field__hint" id="phoneHint">Zimbabwe number, without the leading zero.</p>
          </div>
        </div>

        <div class="field-row">
          <div class="field">
            <label for="fSize">Number of members</label>
            <select class="input select" id="fSize" name="size">
              <option value="">Choose a range&hellip;</option>
              <option>Under 100</option>
              <option>100&ndash;300</option>
              <option>300&ndash;1000</option>
              <option>Over 1000</option>
            </select>
          </div>

          <div class="field">
            <label for="fTopic">How can we help? <span class="req" aria-hidden="true">*</span></label>
            <select class="input select" id="fTopic" name="topic" required>
              <option value="">Choose one&hellip;</option>
              <option>Request a demo</option>
              <option>Pricing enquiry</option>
              <option>Technical support</option>
              <option>Partnership</option>
              <option>Other</option>
            </select>
            <p class="field__err" data-err-for="fTopic">Please choose what this is about.</p>
          </div>
        </div>

        <div class="field">
          <label for="fMessage">Message <span class="req" aria-hidden="true">*</span></label>
          <textarea class="input textarea" id="fMessage" name="message" rows="5" required
                    placeholder="What would you like to know?"></textarea>
          <p class="field__err" data-err-for="fMessage">Tell us a little about what you need.</p>
        </div>

        <button class="btn btn--dark btn--lg enquiry__submit" type="submit">
          <?= icon('send') ?>
          Send Enquiry
        </button>

        <p class="enquiry__alt">
          Prefer to talk?
          <a href="<?= $wa ?>" target="_blank" rel="noopener noreferrer">Message us on WhatsApp</a>
          for a faster response.
        </p>
      </form>

      <!-- Revealed in place of the form once it validates. -->
      <div class="enquiry__done" id="enquiryDone" hidden role="status">
        <span class="enquiry__tick" aria-hidden="true"><?= icon('check') ?></span>
        <h2>Thanks — we&rsquo;ll be in touch shortly.</h2>
        <p>
          We answer most messages within one business day. If it is urgent,
          <a href="<?= $wa ?>" target="_blank" rel="noopener noreferrer">send us a WhatsApp</a>
          and you will hear back sooner.
        </p>
      </div>
    </div>

  </div>
</section>

<!-- ── the questions churches actually ask ── -->
<section class="faq" id="faq">
  <div class="faq__inner">
    <h2 class="faq__title">Questions we get asked</h2>
    <p class="faq__lead">And if yours is not here, ask us directly.</p>

    <div class="faq__list">
      <?php foreach ($faqs as $i => [$q, $a]): ?>
        <details class="faq__item"<?= $i === 0 ? ' open' : '' ?>>
          <summary class="faq__q">
            <span><?= $q ?></span>
            <span class="faq__chev" aria-hidden="true"><?= icon('caret') ?></span>
          </summary>
          <div class="faq__a"><p><?= $a ?></p></div>
        </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>

</main>

<?php require __DIR__ . '/resources/partials/footer.php'; ?>

<a class="wa-float" href="<?= $wa ?>" target="_blank" rel="noopener noreferrer"
   aria-label="Chat with us on WhatsApp">
  <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15s-.77.96-.94 1.16c-.17.2-.35.22-.64.08-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.6.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.61-.92-2.21-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37s-1.04 1.02-1.04 2.48 1.07 2.88 1.22 3.08c.15.2 2.1 3.2 5.08 4.49.71.3 1.26.49 1.69.63.71.22 1.36.19 1.87.12.57-.09 1.76-.72 2-1.41.25-.7.25-1.29.18-1.42-.07-.13-.27-.2-.57-.35M12.05 21.8h-.02a9.8 9.8 0 0 1-4.99-1.37l-.36-.21-3.71.97 1-3.62-.24-.37a9.79 9.79 0 0 1-1.5-5.22c0-5.41 4.41-9.81 9.83-9.81a9.75 9.75 0 0 1 6.94 2.88 9.72 9.72 0 0 1 2.87 6.94c0 5.41-4.41 9.81-9.82 9.81m8.36-18.17A11.7 11.7 0 0 0 12.05 0C5.55 0 .26 5.29.26 11.79c0 2.08.54 4.11 1.58 5.9L.16 24l6.45-1.69a11.75 11.75 0 0 0 5.44 1.34h.01c6.5 0 11.79-5.29 11.79-11.79 0-3.15-1.23-6.11-3.45-8.34"/></svg>
  <span class="wa-float__tip">Chat with us</span>
</a>

<script src="resources/js/main.js"></script>
<script>
/* Visual-only validation: the form never leaves the page. */
(function () {
  var form = document.getElementById('enquiryForm');
  var done = document.getElementById('enquiryDone');
  if (!form || !done) { return; }

  function check(el) {
    var ok = el.checkValidity() && el.value.trim() !== '';
    el.classList.toggle('is-bad', !ok);
    el.classList.toggle('is-good', ok && el.value.trim() !== '');
    var err = form.querySelector('[data-err-for="' + el.id + '"]');
    if (err) { err.classList.toggle('is-shown', !ok); }
    return ok;
  }

  form.querySelectorAll('[required]').forEach(function (el) {
    el.addEventListener('blur', function () { check(el); });
    el.addEventListener('input', function () {
      if (el.classList.contains('is-bad')) { check(el); }
    });
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var bad = null;
    form.querySelectorAll('[required]').forEach(function (el) {
      if (!check(el) && !bad) { bad = el; }
    });
    if (bad) { bad.focus(); return; }
    form.hidden = true;
    done.hidden = false;
    done.focus();
  });
})();
</script>
</body>
</html>

<?php
$brand = 'Mutendi';

require __DIR__ . '/resources/partials/icons.php';
require __DIR__ . '/resources/partials/avatars.php';

// Every entry points at a real section, in the order they appear on the page.
$navLinks = [
    ['label' => 'Features',     'href' => '#features'],
    ['label' => 'Records',      'href' => '#records', 'icon' => 'register', 'badge' => 'New'],
    ['label' => 'For Dioceses', 'href' => '#dioceses'],
    ['label' => 'Parishes',     'href' => '#parishes'],
    ['label' => 'Contact',      'href' => 'contact.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $brand ?> — Parish Records, Diocese to Head Office</title>
<meta name="description" content="<?= $brand ?> is church management software built for African parishes: keep membership rolls, baptisms, marriages and parish accounts current, and roll every record up to the diocese and head office — safely backed up.">
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

<?= avatar_defs(20) ?>

<div class="page-glow" aria-hidden="true"></div>
<div class="page-grid" aria-hidden="true"></div>

<header class="nav">
  <div class="nav__inner">
    <a class="brand" href="/">
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
          <?php if (!empty($link['dropdown'])): ?>
            <svg class="nav__caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
      <a class="nav__link nav__link--mobile" href="#signin">Sign in</a>
      <a class="nav__link nav__link--mobile nav__link--cta" href="contact.php">Get Started</a>
    </nav>

    <div class="nav__actions">
      <button class="theme-toggle" type="button" aria-label="Toggle dark mode">
        <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
      </button>
      <a class="nav__signin" href="#signin">Sign in</a>
      <a class="btn btn--dark" href="contact.php">Get Started</a>
    </div>

    <button class="nav__burger" type="button" aria-label="Open menu" aria-expanded="false">
      <span></span><span></span>
    </button>
  </div>
</header>

<main>

<section class="hero">

  <div class="hero__content">
    <span class="pill">Built for Africa's parishes</span>

    <h1 class="hero__title">
      Church Management<br>
      <span class="hero__title-alt">Made simple.</span>
    </h1>

    <p class="hero__lead">From the local parish to the diocesan head office — one record</p>

    <p class="hero__sub">
      <?= $brand ?> keeps every parish register current — members, baptisms,
      marriages and parish accounts — and rolls it all up to the diocese and
      head office, safely backed up.
    </p>

    <div class="hero__cta">
      <a class="btn btn--dark btn--lg" href="#contact">
        Get Started
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
      </a>
      <a class="btn btn--ghost btn--lg" href="#contact">Book a demo</a>
    </div>

    <a class="hero__scroll" href="#features">
      <span>See it in action</span>
      <span class="hero__scroll-dot">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
      </span>
    </a>
  </div>

  <!-- Floating product snapshots -->
  <div class="floaters" aria-hidden="true">

    <div class="card card--visitor float" style="--d:0s">
      <span class="ico ico--green">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
      </span>
      <div>
        <p class="card__title">New Visitor</p>
        <p class="card__meta">First Visit Today</p>
      </div>
    </div>

    <div class="chip chip--time float" style="--d:1.2s">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg>
      <span>09:00 AM</span>
    </div>

    <div class="card card--report float" style="--d:.6s">
      <p class="card__head">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2.5H7a2 2 0 0 0-2 2v15a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7.5z"/><polyline points="14 2.5 14 7.5 19 7.5"/></svg>
        Diocesan Report
      </p>
      <div class="card__input">March 2026 return</div>
      <div class="card__row">
        <span class="card__meta">Due 5 April</span>
        <span class="mini-btn">Submit</span>
      </div>
    </div>

    <div class="card card--member float" style="--d:1.8s">
      <?= avatar('tendai-moyo', 'avatar') ?>
      <div>
        <p class="card__title">Tendai Moyo</p>
        <p class="card__meta">St Mary's Parish</p>
      </div>
    </div>

    <div class="chip chip--team float" style="--d:2.4s">Sunday School</div>

    <div class="card card--backup float" style="--d:.9s">
      <p class="card__head">
        <svg class="ico-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.5 4.5 5.8v5.4c0 4.6 3.2 8.9 7.5 10.3 4.3-1.4 7.5-5.7 7.5-10.3V5.8z"/><polyline points="9 11.8 11.4 14.2 15.4 9.6"/></svg>
        Records Backed Up
      </p>
      <p class="card__stat">100%</p>
      <p class="card__meta"><span class="up">Synced</span> 5 min ago</p>
    </div>

    <div class="card card--meeting float" style="--d:1.5s">
      <span class="date-badge"><em>WED</em><strong>17</strong></span>
      <div>
        <p class="card__title">Parish Council</p>
        <p class="card__meta">6:00 PM</p>
      </div>
    </div>

    <div class="card card--joined float" style="--d:2.1s">
      <span class="ico ico--blue">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
      </span>
      <div>
        <p class="card__title">Baptism recorded</p>
        <p class="card__meta">2 min ago</p>
      </div>
    </div>

    <div class="card card--checkin float" style="--d:.3s">
      <span class="ico ico--green ico--bare">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><polyline points="8.5 12.2 11 14.7 15.8 9.6"/></svg>
      </span>
      <div>
        <p class="card__title">Sunday Service</p>
        <p class="card__meta">847 recorded</p>
      </div>
    </div>

    <div class="card card--members float" style="--d:1.1s">
      <p class="card__head">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><line x1="6" y1="20" x2="6" y2="13"/><line x1="12" y1="20" x2="12" y2="6"/><line x1="18" y1="20" x2="18" y2="10"/></svg>
        Parish Roll
      </p>
      <p class="card__stat">1,247</p>
      <span class="meter"><i style="width:72%"></i></span>
    </div>

    <div class="chip chip--add float" style="--d:2.7s">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
      <span>Add Member</span>
    </div>

    <div class="chip chip--diocese float" style="--d:1.9s">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V6.5l7-4 7 4V21"/><path d="M10 21v-5h4v5"/></svg>
      <span>Diocese of Harare</span>
    </div>

  </div>
</section>

<?php require __DIR__ . '/resources/partials/showcase.php'; ?>

<?php require __DIR__ . '/resources/partials/features.php'; ?>

<?php require __DIR__ . '/resources/partials/trust.php'; ?>

<?php require __DIR__ . '/resources/partials/testimonials.php'; ?>

<!-- Pricing: no figures, because it genuinely depends on the size of the church. -->
<section class="pricing" id="pricing">
  <div class="pricing__inner">
    <div class="pricing__card reveal">
      <span class="pill">Pricing</span>
      <h2 class="pricing__title">Simple, church-friendly pricing</h2>
      <p class="pricing__lead">
        What you pay depends on how many members you keep on the roll and which parts of
        <?= $brand ?> you use &mdash; a single parish and a diocese with forty are not the
        same thing, and it would be wrong to price them as though they were.
      </p>
      <p class="pricing__note">
        Tell us roughly how big your church is and we will work out something fair.
      </p>
      <div class="pricing__actions">
        <a class="btn btn--wa btn--lg" href="<?= whatsapp_link($whatsappNumber) ?>" target="_blank" rel="noopener noreferrer">
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15s-.77.96-.94 1.16c-.17.2-.35.22-.64.08-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.6.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.61-.92-2.21-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37s-1.04 1.02-1.04 2.48 1.07 2.88 1.22 3.08c.15.2 2.1 3.2 5.08 4.49.71.3 1.26.49 1.69.63.71.22 1.36.19 1.87.12.57-.09 1.76-.72 2-1.41.25-.7.25-1.29.18-1.42-.07-.13-.27-.2-.57-.35M12.05 21.8h-.02a9.8 9.8 0 0 1-4.99-1.37l-.36-.21-3.71.97 1-3.62-.24-.37a9.79 9.79 0 0 1-1.5-5.22c0-5.41 4.41-9.81 9.83-9.81a9.75 9.75 0 0 1 6.94 2.88 9.72 9.72 0 0 1 2.87 6.94c0 5.41-4.41 9.81-9.82 9.81m8.36-18.17A11.7 11.7 0 0 0 12.05 0C5.55 0 .26 5.29.26 11.79c0 2.08.54 4.11 1.58 5.9L.16 24l6.45-1.69a11.75 11.75 0 0 0 5.44 1.34h.01c6.5 0 11.79-5.29 11.79-11.79 0-3.15-1.23-6.11-3.45-8.34"/></svg>
          Chat on WhatsApp
        </a>
        <a class="btn btn--ghost btn--lg" href="<?= mail_link($contactEmail, 'Pricing enquiry') ?>">
          <?= icon('mail') ?>
          Email us
        </a>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/resources/partials/cta.php'; ?>

</main>

<?php require __DIR__ . '/resources/partials/footer.php'; ?>

<a class="wa-float" href="<?= whatsapp_link($whatsappNumber) ?>" target="_blank" rel="noopener noreferrer"
   aria-label="Chat with us on WhatsApp">
  <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15s-.77.96-.94 1.16c-.17.2-.35.22-.64.08-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.6.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.61-.92-2.21-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37s-1.04 1.02-1.04 2.48 1.07 2.88 1.22 3.08c.15.2 2.1 3.2 5.08 4.49.71.3 1.26.49 1.69.63.71.22 1.36.19 1.87.12.57-.09 1.76-.72 2-1.41.25-.7.25-1.29.18-1.42-.07-.13-.27-.2-.57-.35M12.05 21.8h-.02a9.8 9.8 0 0 1-4.99-1.37l-.36-.21-3.71.97 1-3.62-.24-.37a9.79 9.79 0 0 1-1.5-5.22c0-5.41 4.41-9.81 9.83-9.81a9.75 9.75 0 0 1 6.94 2.88 9.72 9.72 0 0 1 2.87 6.94c0 5.41-4.41 9.81-9.82 9.81m8.36-18.17A11.7 11.7 0 0 0 12.05 0C5.55 0 .26 5.29.26 11.79c0 2.08.54 4.11 1.58 5.9L.16 24l6.45-1.69a11.75 11.75 0 0 0 5.44 1.34h.01c6.5 0 11.79-5.29 11.79-11.79 0-3.15-1.23-6.11-3.45-8.34"/></svg>
  <span class="wa-float__tip">Chat with us</span>
</a>

<script src="resources/js/main.js"></script>
</body>
</html>
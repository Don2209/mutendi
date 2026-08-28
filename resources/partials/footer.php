<?php
$footerColumns = [
    'Platform' => [
        ['Features',              '#features'],
        ['Member records',        '#members'],
        ['Sacramental registers', '#records'],
        ['For dioceses',          '#dioceses'],
        ['Parishes',              '#parishes'],
        ['Contact',               'contact.php'],
    ],
    'Resources' => [
        ['Help centre',        '#help'],
        ['Documentation',      '#docs'],
        ['Guides for parishes','#guides'],
        ['System status',      '#status'],
    ],
    'Legal' => [
        ['Privacy policy',   'privacy.php'],
        ['Terms of service', '#terms'],
        ['Data protection',  '#data'],
    ],
];
?>
<footer class="footer">
  <div class="footer__inner">

    <div class="footer__brand">
      <a class="brand brand--footer" href="/">
        <img class="brand__mark" src="resources/img/logo.png" alt="<?= $brand ?> logo">
        <span class="brand__name"><?= $brand ?></span>
      </a>
      <p class="footer__blurb">
        Church management software built for African parishes — one record,
        from the local parish to the diocesan head office.
      </p>
      <p class="footer__where">Harare, Zimbabwe</p>

      <ul class="footer__contact">
        <li>
          <a href="<?= whatsapp_link($whatsappNumber) ?>" target="_blank" rel="noopener noreferrer">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15s-.77.96-.94 1.16c-.17.2-.35.22-.64.08-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.6.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.61-.92-2.21-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37s-1.04 1.02-1.04 2.48 1.07 2.88 1.22 3.08c.15.2 2.1 3.2 5.08 4.49.71.3 1.26.49 1.69.63.71.22 1.36.19 1.87.12.57-.09 1.76-.72 2-1.41.25-.7.25-1.29.18-1.42-.07-.13-.27-.2-.57-.35M12.05 21.8h-.02a9.8 9.8 0 0 1-4.99-1.37l-.36-.21-3.71.97 1-3.62-.24-.37a9.79 9.79 0 0 1-1.5-5.22c0-5.41 4.41-9.81 9.83-9.81a9.75 9.75 0 0 1 6.94 2.88 9.72 9.72 0 0 1 2.87 6.94c0 5.41-4.41 9.81-9.82 9.81m8.36-18.17A11.7 11.7 0 0 0 12.05 0C5.55 0 .26 5.29.26 11.79c0 2.08.54 4.11 1.58 5.9L.16 24l6.45-1.69a11.75 11.75 0 0 0 5.44 1.34h.01c6.5 0 11.79-5.29 11.79-11.79 0-3.15-1.23-6.11-3.45-8.34"/></svg>
            <?= whatsapp_display($whatsappNumber) ?>
          </a>
        </li>
        <li>
          <a href="<?= mail_link($contactEmail) ?>">
            <?= icon('mail') ?>
            <?= $contactEmail ?>
          </a>
        </li>
      </ul>
    </div>

    <?php foreach ($footerColumns as $heading => $links): ?>
      <nav class="footer__col" aria-label="<?= $heading ?>">
        <p class="footer__heading"><?= $heading ?></p>
        <ul class="footer__list">
          <?php foreach ($links as [$label, $href]): ?>
            <li><a href="<?= $href ?>"><?= $label ?></a></li>
          <?php endforeach; ?>
        </ul>
      </nav>
    <?php endforeach; ?>

  </div>

  <div class="footer__bar">
    <p>&copy; <?= date('Y') ?> <?= $brand ?>. All rights reserved.</p>
    <p>Built for the parishes of Africa.</p>
  </div>
</footer>

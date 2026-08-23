<?php
$footerColumns = [
    'Platform' => [
        ['Features',              '#features'],
        ['Member records',        '#members'],
        ['Sacramental registers', '#records'],
        ['For dioceses',          '#dioceses'],
        ['Parishes',              '#parishes'],
        ['Contact',               '#contact'],
    ],
    'Resources' => [
        ['Help centre',        '#help'],
        ['Documentation',      '#docs'],
        ['Guides for parishes','#guides'],
        ['System status',      '#status'],
    ],
    'Legal' => [
        ['Privacy policy',   '#privacy'],
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

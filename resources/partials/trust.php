<?php
$parishes = [
    ['SM', "St Mary's Parish",     'Harare',      '1,247'],
    ['HT', 'Holy Trinity',         'Bulawayo',    '2,480'],
    ['SP', "St Peter's Cathedral", 'Harare',      '3,900'],
    ['CK', 'Christ the King',      'Gweru',       '1,860'],
    ['OL', 'Our Lady of Grace',    'Mutare',        '940'],
    ['EP', 'Emmanuel Parish',      'Masvingo',    '1,610'],
    ['SJ', "St Joseph's",          'Chitungwiza', '2,150'],
    ['SH', 'Sacred Heart',         'Kwekwe',      '1,320'],
];
?>
<section class="trust" id="parishes">
  <p class="trust__label reveal">Trusted by parishes across Africa</p>

  <div class="trust__grid reveal">
    <?php foreach ($parishes as $i => [$initials, $name, $town, $members]): ?>
      <div class="parish" style="--i:<?= $i ?>">
        <span class="parish__mark"><?= $initials ?></span>
        <div>
          <p class="parish__name"><?= $name ?></p>
          <p class="parish__meta"><?= $town ?> · <?= $members ?> members</p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="marquee reveal" aria-hidden="true">
    <div class="marquee__track">
      <?php for ($pass = 0; $pass < 2; $pass++): ?>
        <?php foreach ($parishes as [$initials, $name, $town, $members]): ?>
          <span class="marquee__item"><?= $name ?>, <?= $town ?></span>
        <?php endforeach; ?>
      <?php endfor; ?>
    </div>
  </div>
</section>

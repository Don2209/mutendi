<?php
$testimonials = [
    [
        'quote'  => 'Our baptism register goes back to 1961. Having it searchable — and backed up — means we answer the diocese in minutes instead of days.',
        'name'   => 'Fr. Tendai Chikwanha',
        'role'   => "Parish Priest • St Mary's, Harare",
        'avatar' => 'fr-chikwanha',
    ],
    [
        'quote'  => 'The monthly return used to take me three evenings at the parish office. Now it builds itself from the records we already keep.',
        'name'   => 'Sr. Grace Adeyemi',
        'role'   => 'Parish Administrator • Holy Trinity, Bulawayo',
        'avatar' => 'sr-grace',
    ],
    [
        'quote'  => 'I can see every parish roll in one place. No more chasing spreadsheets from forty parishes in the weeks before synod meets.',
        'name'   => 'Michael Okonkwo',
        'role'   => 'Diocesan Secretary • Diocese of Harare',
        'avatar' => 'michael-o',
    ],
    [
        'quote'  => 'When the parish office flooded we lost the cupboard, but not one record. Every baptism and marriage was still there next morning.',
        'name'   => 'Rutendo Ncube',
        'role'   => 'Parish Council Chair • Christ the King, Gweru',
        'avatar' => 'rutendo-ncube',
    ],
];
?>
<section class="testimonials" id="testimonials" data-spy="#parishes">
  <div class="testimonials__inner">

    <div class="testimonials__intro reveal">
      <p class="eyebrow">Testimonials</p>
      <h2 class="testimonials__title">
        Trusted by parishes<br>
        <span class="testimonials__title-alt">across Africa.</span>
      </h2>
      <p class="testimonials__lead">
        Join the priests, parish administrators and diocesan secretaries who keep
        their registers with Mutendi.
      </p>

      <div class="pager" role="tablist" aria-label="Testimonials">
        <?php foreach ($testimonials as $i => $t): ?>
          <button class="pager__dot<?= $i === 0 ? ' is-on' : '' ?>" type="button" role="tab"
                  aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
                  aria-label="Testimonial <?= $i + 1 ?>: <?= htmlspecialchars($t['name']) ?>"
                  data-slide="<?= $i ?>"></button>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="deck reveal">
      <?php foreach ($testimonials as $i => $t): ?>
        <article class="quote" data-slide="<?= $i ?>" data-pos="<?= $i ?>"
                 <?= $i === 0 ? '' : 'aria-hidden="true"' ?>>
          <span class="quote__mark">&ldquo;</span>
          <p class="quote__text"><?= $t['quote'] ?></p>
          <div class="quote__who">
            <?= avatar($t['avatar'], 'quote__avatar') ?>
            <div>
              <p class="quote__name"><?= $t['name'] ?></p>
              <p class="quote__role"><?= $t['role'] ?></p>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

  </div>
</section>

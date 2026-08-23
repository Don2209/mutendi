<?php
/**
 * Three deep-dive feature blocks. Each pairs a copy column with a small mock
 * window; `flip` swaps the two so the page alternates down the fold.
 */
$features = [
    [
        'id'      => 'members',
        'spy'     => '#records',
        'eyebrow' => 'Member records',
        'title'   => 'Every member, and every household.',
        'text'    => 'Full profiles with household links, ministry involvement and every sacrament a member has received — one record per soul, not one per form.',
        'flip'    => false,
        'bullets' => [
            ['users',    'Complete profiles',       'Contact details, household and ministry in one place.'],
            ['bolt',     'Search that works',       'Find any member by name, ward or ministry in a second.'],
            ['building', 'Ministries & departments','Choir, ushers, Sunday school — your structure, mirrored.'],
        ],
        'mock'    => [
            'title'   => 'Members',
            'filters' => ['All', 'Active', 'Catechumens'],
            'rows'    => [
                ['avatar' => 'grace-adeyemi', 'title' => 'Grace Adeyemi', 'meta' => 'Sunday School', 'tag' => 'Active', 'tone' => 'green'],
                ['avatar' => 'tendai-moyo', 'title' => 'Tendai Moyo',   'meta' => 'Youth Leader',  'tag' => 'Active', 'tone' => 'green'],
                ['avatar' => 'chipo-marange', 'title' => 'Chipo Marange', 'meta' => 'Ushers',        'tag' => 'Active', 'tone' => 'green'],
            ],
        ],
    ],
    [
        'id'      => 'records',
        'eyebrow' => 'Sacramental registers',
        'title'   => 'The registers, kept for good.',
        'text'    => 'Baptisms, confirmations, marriages and burials recorded once — indexed, searchable, and impossible to lose to damp, fire or a misplaced book.',
        'flip'    => true,
        'bullets' => [
            ['register', 'Every rite recorded',      'The four registers your parish already keeps, digitised.'],
            ['award',    'Certificates in one click','Issue a certified copy without opening a cupboard.'],
            ['shield',   'Never lost again',         'Backed up nightly and copied off-site, automatically.'],
        ],
        'mock'    => [
            'title'   => 'Registers',
            'filters' => ['All', 'Baptisms', 'Marriages'],
            'rows'    => [
                ['icon' => 'droplet', 'title' => 'Baptism — Chipo Marange',   'meta' => '14 March 2026',    'tag' => 'Signed', 'tone' => 'green'],
                ['icon' => 'rings',   'title' => 'Marriage — Moyo & Ncube',   'meta' => '28 February 2026', 'tag' => 'Signed', 'tone' => 'green'],
                ['icon' => 'award',   'title' => 'Confirmation — 12 names',   'meta' => '9 February 2026',  'tag' => 'Draft',  'tone' => 'amber'],
            ],
        ],
    ],
    [
        'id'      => 'dioceses',
        'eyebrow' => 'Diocese & head office',
        'title'   => 'From the parish to head office.',
        'text'    => 'Returns build themselves from records the parish already keeps, and the diocese sees every parish in one place — without a spreadsheet changing hands.',
        'flip'    => false,
        'bullets' => [
            ['file',     'Returns that build themselves', 'Monthly and annual returns, generated rather than typed.'],
            ['chart',    'Every parish at a glance',      'Rolls, attendance and returns across the whole diocese.'],
            ['download', "Works when the network doesn't",'Record offline in the parish; it syncs when you reconnect.'],
        ],
        'mock'    => [
            'title'   => 'Diocese of Harare',
            'filters' => ['All parishes', 'Submitted', 'Due'],
            'rows'    => [
                ['icon' => 'building', 'title' => "St Mary's, Harare",     'meta' => '1,247 members', 'tag' => 'Submitted', 'tone' => 'green'],
                ['icon' => 'building', 'title' => 'Holy Trinity, Bulawayo','meta' => '2,480 members', 'tag' => 'Submitted', 'tone' => 'green'],
                ['icon' => 'building', 'title' => 'Christ the King, Gweru','meta' => '1,860 members', 'tag' => 'Due',       'tone' => 'amber'],
            ],
        ],
    ],
];
?>
<?php foreach ($features as $f): ?>
<section class="feature<?= $f['flip'] ? ' feature--flip' : '' ?>" id="<?= $f['id'] ?>"
         <?= !empty($f['spy']) ? 'data-spy="' . $f['spy'] . '"' : '' ?>>
  <div class="feature__inner">

    <div class="feature__copy reveal">
      <p class="eyebrow"><?= $f['eyebrow'] ?></p>
      <h2 class="feature__title"><?= $f['title'] ?></h2>
      <p class="feature__text"><?= $f['text'] ?></p>

      <div class="bullets">
        <?php foreach ($f['bullets'] as [$ico, $label, $desc]): ?>
          <div class="bullet">
            <span class="bullet__ico"><?= icon($ico) ?></span>
            <div>
              <p class="bullet__title"><?= $label ?></p>
              <p class="bullet__text"><?= $desc ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="feature__media reveal">
      <div class="mock">
        <div class="mock__bar">
          <span class="dots"><i></i><i></i><i></i></span>
          <span class="mock__title"><?= $f['mock']['title'] ?></span>
        </div>
        <div class="mock__body">
          <div class="mock__filters">
            <?php foreach ($f['mock']['filters'] as $i => $filter): ?>
              <span class="filter<?= $i === 0 ? ' is-on' : '' ?>"><?= $filter ?></span>
            <?php endforeach; ?>
            <span class="mock__ghost"></span>
          </div>
          <?php foreach ($f['mock']['rows'] as $row): ?>
            <div class="row">
              <?php if (!empty($row['avatar'])): ?>
                <?= avatar($row['avatar'], 'row__avatar') ?>
              <?php else: ?>
                <span class="row__ico"><?= icon($row['icon']) ?></span>
              <?php endif; ?>
              <div class="row__body">
                <p class="row__title"><?= $row['title'] ?></p>
                <p class="row__meta"><?= $row['meta'] ?></p>
              </div>
              <span class="tag tag--<?= $row['tone'] ?>"><?= $row['tag'] ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

  </div>
</section>
<?php endforeach; ?>

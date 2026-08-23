<?php
/**
 * Tabbed module showcase. Every panel shares one shape — heading, optional
 * search and filters, then a list of rows — so a single renderer covers all six.
 */
$modules = [
    'members' => [
        'tab'     => 'Members',
        'icon'    => 'users',
        'title'   => 'Members',
        'blurb'   => 'Every soul in the parish on one roll — contact details, household, ministry and status. Find anyone in a second, and keep the register current without touching a paper file.',
        'heading' => 'Members',
        'sub'     => '1,247 on the parish roll',
        'action'  => 'Add Member',
        'search'  => 'Tenda',
        'filters' => ['All', 'Active', 'Catechumens', 'Leaders'],
        'rows'    => [
            ['avatar' => 'tendai-moyo', 'title' => 'Tendai Moyo',   'meta' => 'Youth Leader · St Mary\'s',    'tag' => 'Active', 'tone' => 'green'],
            ['avatar' => 'grace-adeyemi', 'title' => 'Grace Adeyemi', 'meta' => 'Sunday School · St Mary\'s',   'tag' => 'Active', 'tone' => 'green'],
            ['avatar' => 'rutendo-ncube', 'title' => 'Rutendo Ncube', 'meta' => 'Choir · St Mary\'s',           'tag' => 'Active', 'tone' => 'green'],
            ['avatar' => 'blessing-m', 'title' => 'Blessing Mutasa','meta' => 'Enrolled 4 March · St Mary\'s', 'tag' => 'Catechumen', 'tone' => 'purple'],
        ],
    ],
    'registers' => [
        'tab'     => 'Registers',
        'icon'    => 'register',
        'title'   => 'Registers',
        'blurb'   => 'The sacramental registers your parish already keeps, kept digitally. Baptisms, confirmations, marriages and burials recorded once, searchable forever, and never lost to a damaged book.',
        'heading' => 'Registers',
        'sub'     => 'Baptisms, confirmations, marriages, burials',
        'action'  => 'New Entry',
        'filters' => ['All', 'Baptisms', 'Confirmations', 'Marriages', 'Burials'],
        'rows'    => [
            ['icon' => 'droplet', 'title' => 'Baptism — Chipo Marange',        'meta' => '14 March 2026 · Fr. Chikwanha',  'tag' => 'Signed', 'tone' => 'green'],
            ['icon' => 'rings',   'title' => 'Marriage — T. Moyo & R. Ncube',  'meta' => '28 February 2026 · Banns read',  'tag' => 'Signed', 'tone' => 'green'],
            ['icon' => 'award',   'title' => 'Confirmation — 12 candidates',   'meta' => '9 February 2026 · Awaiting seal', 'tag' => 'Draft',  'tone' => 'amber'],
            ['icon' => 'leaf',    'title' => 'Burial — Sekai Dube',            'meta' => '2 February 2026 · Fr. Chikwanha', 'tag' => 'Signed', 'tone' => 'green'],
        ],
    ],
    'events' => [
        'tab'     => 'Events',
        'icon'    => 'calendar',
        'title'   => 'Events',
        'blurb'   => 'Services, meetings and classes on one parish calendar. Attendance is marked straight against the roll, so the numbers are already counted when the diocese asks for them.',
        'heading' => 'Events',
        'sub'     => 'This week at St Mary\'s Parish',
        'action'  => 'New Event',
        'rows'    => [
            ['date' => ['SUN', '22'], 'title' => 'Sunday Mass',        'meta' => '09:00 · three services',   'tag' => 'Scheduled', 'tone' => 'purple'],
            ['date' => ['WED', '17'], 'title' => 'Parish Council',     'meta' => '18:00 · Parish hall',      'tag' => 'Scheduled', 'tone' => 'purple'],
            ['date' => ['SAT', '20'], 'title' => 'Confirmation Class', 'meta' => '14:00 · 12 candidates',    'tag' => 'Scheduled', 'tone' => 'purple'],
            ['date' => ['THU', '18'], 'title' => 'Women\'s Fellowship', 'meta' => '15:00 · Parish hall',      'tag' => 'Scheduled', 'tone' => 'purple'],
        ],
    ],
    'notices' => [
        'tab'     => 'Notices',
        'icon'    => 'message',
        'title'   => 'Notices',
        'blurb'   => 'Announcements that reach the whole parish or a single ministry, by SMS or WhatsApp. Every notice is logged against the roll, so you can always see who was told what, and when.',
        'heading' => 'Notices',
        'sub'     => 'Reaching 1,247 members',
        'action'  => 'New Notice',
        'rows'    => [
            ['icon' => 'send',  'title' => 'Sunday Mass moves to 09:00', 'meta' => 'Sent to all members',      'tag' => 'Delivered', 'tone' => 'green'],
            ['icon' => 'users', 'title' => 'Choir practice Thursday',    'meta' => 'Choir · 38 members',       'tag' => 'Delivered', 'tone' => 'green'],
            ['icon' => 'clock', 'title' => 'Parish AGM reminder',        'meta' => 'Scheduled for 4 April',    'tag' => 'Queued',    'tone' => 'amber'],
            ['icon' => 'calendar', 'title' => 'Confirmation class moved', 'meta' => 'Candidates · 12 members',  'tag' => 'Delivered', 'tone' => 'green'],
        ],
    ],
    'reports' => [
        'tab'     => 'Reports',
        'icon'    => 'chart',
        'title'   => 'Diocesan Reports',
        'blurb'   => 'The returns your diocese asks for, built from records the parish already keeps. Head office sees every parish roll up in one place — no spreadsheets passed hand to hand.',
        'heading' => 'Diocesan Reports',
        'sub'     => 'Returns to the Diocese of Harare',
        'action'  => 'New Return',
        'filters' => ['All', 'Submitted', 'Due', 'Draft'],
        'rows'    => [
            ['icon' => 'file', 'title' => 'March 2026 Return',      'meta' => 'St Mary\'s Parish · sent 2 April', 'tag' => 'Submitted', 'tone' => 'green'],
            ['icon' => 'file', 'title' => 'February 2026 Return',   'meta' => 'St Mary\'s Parish · sent 3 March', 'tag' => 'Submitted', 'tone' => 'green'],
            ['icon' => 'file', 'title' => 'Q1 Membership Return',   'meta' => 'Due 5 April · ready to send',      'tag' => 'Due',       'tone' => 'amber'],
            ['icon' => 'file', 'title' => 'Annual Return 2025',      'meta' => 'St Mary\'s Parish · sent 14 Jan',  'tag' => 'Submitted', 'tone' => 'green'],
        ],
    ],
    'backups' => [
        'tab'     => 'Backups',
        'icon'    => 'shield',
        'title'   => 'Backups',
        'blurb'   => 'Every register is backed up each night and copied off-site. If a laptop walks or a book is damaged, the parish record is still whole — and the diocese can still see it.',
        'heading' => 'Backups',
        'sub'     => 'Every record, safely kept',
        'action'  => 'Restore',
        'rows'    => [
            ['icon' => 'cloud',    'title' => 'Automatic backup',      'meta' => 'Today, 05:00 · 1,247 records',  'tag' => 'Complete', 'tone' => 'green'],
            ['icon' => 'cloud',    'title' => 'Automatic backup',      'meta' => 'Yesterday, 05:00 · 1,246 records', 'tag' => 'Complete', 'tone' => 'green'],
            ['icon' => 'download', 'title' => 'Offline copy exported', 'meta' => '12 March 2026 · parish office', 'tag' => 'Complete', 'tone' => 'green'],
            ['icon' => 'shield',   'title' => 'Off-site copy verified', 'meta' => '11 March 2026 · Diocese',      'tag' => 'Complete', 'tone' => 'green'],
        ],
    ],
];

$first = array_key_first($modules);
?>
<section class="showcase" id="features">

  <div class="tabs reveal" role="tablist" aria-label="Product modules">
    <?php foreach ($modules as $key => $m): ?>
      <button class="tab<?= $key === $first ? ' is-active' : '' ?>" type="button"
              role="tab" id="tab-<?= $key ?>" aria-controls="panel-<?= $key ?>"
              aria-selected="<?= $key === $first ? 'true' : 'false' ?>"
              tabindex="<?= $key === $first ? '0' : '-1' ?>"
              data-module="<?= $key ?>">
        <?= icon($m['icon'], 'tab__icon') ?>
        <?= $m['tab'] ?>
      </button>
    <?php endforeach; ?>
  </div>

  <div class="window reveal">
    <div class="window__bar">
      <span class="dots"><i></i><i></i><i></i></span>
      <span class="window__url">mutendi.org</span>
      <?= icon('register', 'window__ico') ?>
    </div>

    <div class="window__body">
      <aside class="side">
        <span class="side__brand"></span>
        <span class="side__item"><?= icon('home', 'side__ico') ?>Dashboard</span>
        <?php foreach ($modules as $key => $m): ?>
          <span class="side__item<?= $key === $first ? ' is-active' : '' ?>" data-side="<?= $key ?>">
            <?= icon($m['icon'], 'side__ico') ?><?= $m['tab'] ?>
          </span>
        <?php endforeach; ?>
      </aside>

      <div class="stage">
        <?php foreach ($modules as $key => $m): ?>
          <div class="panel<?= $key === $first ? ' is-active' : '' ?>" data-panel="<?= $key ?>"
               id="panel-<?= $key ?>" role="tabpanel" aria-labelledby="tab-<?= $key ?>">

            <div class="panel__head">
              <div>
                <p class="panel__title"><?= $m['heading'] ?></p>
                <p class="panel__sub"><?= $m['sub'] ?></p>
              </div>
              <span class="panel__action"><?= icon('plus') ?><?= $m['action'] ?></span>
            </div>

            <?php if (!empty($m['search'])): ?>
              <div class="search">
                <?= icon('search') ?>
                <span><?= $m['search'] ?><i class="search__caret"></i></span>
              </div>
            <?php endif; ?>

            <?php if (!empty($m['filters'])): ?>
              <div class="filters">
                <?php foreach ($m['filters'] as $i => $f): ?>
                  <span class="filter<?= $i === 0 ? ' is-on' : '' ?>"><?= $f ?></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <div class="rowsbox">
              <?php foreach ($m['rows'] as $i => $row): ?>
                <div class="row" style="--i:<?= $i ?>">
                  <?php if (!empty($row['avatar'])): ?>
                    <?= avatar($row['avatar'], 'row__avatar') ?>
                  <?php elseif (!empty($row['date'])): ?>
                    <span class="date-badge"><em><?= $row['date'][0] ?></em><strong><?= $row['date'][1] ?></strong></span>
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
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="captions reveal">
    <?php foreach ($modules as $key => $m): ?>
      <div class="caption<?= $key === $first ? ' is-active' : '' ?>" data-caption="<?= $key ?>">
        <h2 class="caption__title"><?= $m['title'] ?></h2>
        <p class="caption__text"><?= $m['blurb'] ?></p>
      </div>
    <?php endforeach; ?>
  </div>

</section>

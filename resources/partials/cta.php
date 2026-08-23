<?php
/**
 * Congregation band: three marquee layers of avatars drifting at different
 * speeds and directions, which reads as depth rather than one flat row.
 */
$congregation = array_map(fn($i) => 'band-' . $i, range(0, 19));

/** One marquee layer: the list twice over, so the loop is seamless. */
function avatar_layer(array $people, string $name, int $offset): string {
    $out = '<div class="avatars__layer avatars__layer--' . $name . '">'
         . '<div class="avatars__track">';
    for ($pass = 0; $pass < 2; $pass++) {
        foreach ($people as $i => $face) {
            // A sine offset gives the band an organic, uneven edge.
            $y = round(sin(($i + $offset) * 0.8) * 13);
            $out .= '<span class="av" style="--y:' . $y . 'px">'
                  . avatar($face) . '</span>';
        }
    }
    return $out . '</div></div>';
}

// Each layer walks the list from a different point so no two rows line up.
$back  = $congregation;
$mid   = array_merge(array_slice($congregation, 7), array_slice($congregation, 0, 7));
$front = array_merge(array_slice($congregation, 13), array_slice($congregation, 0, 13));
?>
<section class="cta" id="contact">
  <div class="cta__glow" aria-hidden="true"></div>

  <div class="avatars" aria-hidden="true">
    <?= avatar_layer($back,  'back',  0) ?>
    <?= avatar_layer($mid,   'mid',   3) ?>
    <?= avatar_layer($front, 'front', 6) ?>
  </div>

  <div class="cta__copy reveal">
    <h2 class="cta__title">Ready to bring every record together?</h2>
    <p class="cta__text">
      Join the parishes and dioceses already keeping their registers with <?= $brand ?>.
    </p>
    <div class="cta__actions">
      <a class="btn btn--light btn--lg" href="#start">
        Get Started
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
      </a>
      <a class="btn btn--outline btn--lg" href="#demo">Book a demo</a>
    </div>
  </div>
</section>

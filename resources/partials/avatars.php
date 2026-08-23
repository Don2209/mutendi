<?php
/**
 * Flat cartoon avatars, drawn as SVG on a 100x100 grid.
 *
 * Each distinct person is emitted once as a <symbol> by avatar_defs(); every
 * place they appear is a lightweight <use>. That keeps 100+ avatars in the
 * congregation band from bloating the document.
 */

const AV_SKIN  = ['#5D3A1F', '#7A4B28', '#8D5524', '#A56A3E', '#B97A4E', '#C68E5F', '#D9A066'];
const AV_HAIR  = ['#150F0D', '#241A16', '#2E2320', '#463830', '#6E6058', '#9B9089'];
const AV_TOP   = ['#3E5C8A', '#6B3F7A', '#2F6F63', '#8A4A3C', '#4A4A6A', '#7A6A3C',
                  '#3C6B4A', '#8A5C7A', '#4F5B66', '#2F4858', '#93552F', '#5A4A8A'];
const AV_BG    = ['#E9DCF4', '#DCE7F4', '#F4E6DC', '#DCF3E7', '#F4DCE9', '#EFEBDA', '#DCEFF4'];
const AV_WRAP  = ['#D4634A', '#E0A32E', '#3F8F7A', '#B8478C', '#4A6FC4', '#C9803B'];

/**
 * Deterministic pick so a given person always renders identically.
 * Hashes the seed with a per-attribute salt — picking with `seed * n` directly
 * collapses the range whenever n shares a factor with the set size.
 */
function av_pick(array $set, int $seed, int $salt = 0): string {
    $h = (($seed + 1) * 2654435761 + $salt * 40503) & 0x7FFFFFFF;
    $h ^= $h >> 13;
    $h = ($h * 1274126177) & 0x7FFFFFFF;
    return $set[($h >> 7) % count($set)];
}

/**
 * @param array $o skin, hair (crop|afro|locs|bun|wrap|bald|fade), hairColor,
 *                 top, bg, glasses, beard, collar
 */
function avatar_symbol(string $id, array $o): string {
    $skin = $o['skin'];
    $hair = $o['hair'];
    $hc   = $o['hairColor'];
    $top  = $o['top'];
    $bg   = $o['bg'];
    // A darker shade of the skin for the neck shadow.
    $shade = 'rgba(0,0,0,.16)';

    $s  = '<symbol id="' . $id . '" viewBox="0 0 100 100">';
    $s .= '<circle cx="50" cy="50" r="50" fill="' . $bg . '"/>';
    $s .= '<g clip-path="url(#av-clip)">';

    // Hair drawn behind the head for the fuller styles.
    if ($hair === 'afro') {
        $s .= '<circle cx="50" cy="40" r="28" fill="' . $hc . '"/>';
    } elseif ($hair === 'locs') {
        $s .= '<circle cx="50" cy="40" r="26" fill="' . $hc . '"/>';
        foreach ([24, 33, 67, 76] as $x) {
            $s .= '<rect x="' . ($x - 3) . '" y="38" width="6" height="26" rx="3" fill="' . $hc . '"/>';
        }
    } elseif ($hair === 'bun') {
        $s .= '<circle cx="50" cy="17" r="9" fill="' . $hc . '"/>';
    }

    // Shoulders, neck, head.
    $s .= '<path d="M50 70c-18 0-31 11-34 30h68c-3-19-16-30-34-30z" fill="' . $top . '"/>';
    $s .= '<rect x="43" y="58" width="14" height="16" rx="7" fill="' . $skin . '"/>';
    $s .= '<rect x="43" y="58" width="14" height="7" rx="3.5" fill="' . $shade . '"/>';
    $s .= '<ellipse cx="28.5" cy="47" rx="4.5" ry="5.5" fill="' . $skin . '"/>';
    $s .= '<ellipse cx="71.5" cy="47" rx="4.5" ry="5.5" fill="' . $skin . '"/>';
    $s .= '<ellipse cx="50" cy="44" rx="21" ry="24" fill="' . $skin . '"/>';

    // Hair drawn over the head.
    if ($hair === 'crop') {
        $s .= '<path d="M29 42c0-13 9-22 21-22s21 9 21 22c0-7-9-10-21-10s-21 3-21 10z" fill="' . $hc . '"/>';
    } elseif ($hair === 'fade') {
        $s .= '<path d="M30 40c1-11 9-19 20-19s19 8 20 19c-3-5-11-8-20-8s-17 3-20 8z" fill="' . $hc . '"/>';
    } elseif ($hair === 'bun') {
        $s .= '<path d="M29 42c0-13 9-22 21-22s21 9 21 22c0-8-9-11-21-11s-21 3-21 11z" fill="' . $hc . '"/>';
    } elseif ($hair === 'afro' || $hair === 'locs') {
        $s .= '<path d="M30 40c2-10 10-17 20-17s18 7 20 17c-4-6-11-9-20-9s-16 3-20 9z" fill="' . $hc . '"/>';
    } elseif ($hair === 'wrap') {
        $w = $o['wrapColour'];
        // Fabric knot tucked behind the crown, then the dome, then the brow band.
        $s .= '<path d="M70 14q14-5 11 9q-2 11-13 7z" fill="' . $w . '"/>';
        $s .= '<path d="M70 14q14-5 11 9q-2 11-13 7z" fill="rgba(0,0,0,.14)"/>';
        $s .= '<ellipse cx="50" cy="33" rx="24.5" ry="20" fill="' . $w . '"/>';
        $s .= '<path d="M25.5 34c0 5 11 9 24.5 9s24.5-4 24.5-9v7c0 5-11 8-24.5 8S25.5 46 25.5 41z" '
            . 'fill="' . $w . '"/>';
        $s .= '<path d="M25.5 34c0 5 11 9 24.5 9s24.5-4 24.5-9v7c0 5-11 8-24.5 8S25.5 46 25.5 41z" '
            . 'fill="rgba(0,0,0,.13)"/>';
    }

    // Face.
    $s .= '<ellipse cx="42" cy="45" rx="2.4" ry="3" fill="#241A16"/>';
    $s .= '<ellipse cx="58" cy="45" rx="2.4" ry="3" fill="#241A16"/>';
    $s .= '<path d="M38 39.5q4-2.5 8 0M54 39.5q4-2.5 8 0" stroke="#241A16" stroke-width="1.8" '
        . 'stroke-linecap="round" fill="none" opacity=".75"/>';
    $s .= '<path d="M44.5 54q5.5 5 11 0" stroke="#241A16" stroke-width="2" stroke-linecap="round" fill="none"/>';

    if (!empty($o['beard'])) {
        $s .= '<path d="M31 46c0 16 8 25 19 25s19-9 19-25c0 9-8 13-19 13s-19-4-19-13z" fill="' . $hc . '" opacity=".92"/>';
    }
    if (!empty($o['glasses'])) {
        $s .= '<g stroke="#2B2B33" stroke-width="1.9" fill="none" opacity=".85">'
            . '<circle cx="42" cy="45" r="7"/><circle cx="58" cy="45" r="7"/>'
            . '<path d="M49 45h2M35 44h-4M69 44h4"/></g>';
    }
    if (!empty($o['collar'])) {
        $s .= '<path d="M50 70c-8 0-14 2-19 6l6 9 13-6 13 6 6-9c-5-4-11-6-19-6z" fill="#F5F3F7"/>';
        $s .= '<rect x="44" y="70" width="12" height="6" rx="2" fill="#FFFFFF"/>';
    }

    return $s . '</g></symbol>';
}

/** Build a spec from a seed, with optional explicit overrides. */
function avatar_spec(int $seed, array $override = []): array {
    $styles = ['crop', 'afro', 'locs', 'bun', 'wrap', 'fade', 'crop', 'wrap'];
    return $override + [
        'skin'       => av_pick(AV_SKIN, $seed, 1),
        'hair'       => $styles[$seed % count($styles)],
        'hairColor'  => av_pick(AV_HAIR, $seed, 2),
        'top'        => av_pick(AV_TOP, $seed, 3),
        'bg'         => av_pick(AV_BG, $seed, 4),
        'wrapColour' => av_pick(AV_WRAP, $seed, 5),
        'glasses'    => $seed % 7 === 3,
        'beard'      => $seed % 9 === 4,
        'collar'     => false,
    ];
}

/** The named people, so each looks the same wherever they appear. */
function avatar_people(): array {
    return [
        'tendai-moyo'   => avatar_spec(2,  ['hair' => 'fade', 'skin' => '#7A4B28']),
        'grace-adeyemi' => avatar_spec(4,  ['hair' => 'wrap', 'skin' => '#8D5524', 'glasses' => false]),
        'rutendo-ncube' => avatar_spec(6,  ['hair' => 'bun',  'skin' => '#A56A3E']),
        'blessing-m'    => avatar_spec(9,  ['hair' => 'locs', 'skin' => '#5D3A1F']),
        'chipo-marange' => avatar_spec(11, ['hair' => 'afro', 'skin' => '#B97A4E']),
        'fr-chikwanha'  => avatar_spec(13, ['hair' => 'crop', 'skin' => '#7A4B28',
                                            'collar' => true, 'beard' => true, 'top' => '#23222A']),
        'sr-grace'      => avatar_spec(15, ['hair' => 'wrap', 'skin' => '#8D5524',
                                            'wrapColour' => '#3F8F7A', 'glasses' => true]),
        'michael-o'     => avatar_spec(17, ['hair' => 'crop', 'skin' => '#5D3A1F', 'glasses' => true]),
    ];
}

/** Emit every symbol once, plus the shared clip path. */
function avatar_defs(int $bandSize = 20): string {
    $out = '<svg class="avatar-defs" aria-hidden="true" focusable="false">'
         . '<defs><clipPath id="av-clip"><circle cx="50" cy="50" r="50"/></clipPath></defs>';
    foreach (avatar_people() as $key => $spec) {
        $out .= avatar_symbol('av-' . $key, $spec);
    }
    for ($i = 0; $i < $bandSize; $i++) {
        $out .= avatar_symbol('av-band-' . $i, avatar_spec($i + 21));
    }
    return $out . '</svg>';
}

/** Reference a symbol. */
function avatar(string $key, string $class = ''): string {
    return '<svg class="' . trim('face ' . $class) . '" viewBox="0 0 100 100" aria-hidden="true">'
         . '<use href="#av-' . $key . '"/></svg>';
}

<?php
/**
 * Inline SVG icons on a 24x24 grid, drawn with strokes so they inherit currentColor.
 */
function icon(string $name, string $class = ''): string {
    static $paths = [
        'register'  => '<path d="M5 4.5A2.5 2.5 0 0 1 7.5 2H19v15.5H7.5A2.5 2.5 0 0 0 5 20z"/><line x1="8.5" y1="6.5" x2="15" y2="6.5"/>',
        'users'     => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user-plus' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/>',
        'calendar'  => '<rect x="3" y="4.5" width="18" height="17" rx="2.5"/><line x1="3" y1="9.5" x2="21" y2="9.5"/><line x1="8" y1="2.5" x2="8" y2="6.5"/><line x1="16" y1="2.5" x2="16" y2="6.5"/>',
        'message'   => '<path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5A8.4 8.4 0 0 1 12 3.1a8.4 8.4 0 0 1 9 8.4z"/>',
        'chart'     => '<line x1="6" y1="20" x2="6" y2="13"/><line x1="12" y1="20" x2="12" y2="6"/><line x1="18" y1="20" x2="18" y2="10"/>',
        'shield'    => '<path d="M12 2.5 4.5 5.8v5.4c0 4.6 3.2 8.9 7.5 10.3 4.3-1.4 7.5-5.7 7.5-10.3V5.8z"/><polyline points="9 11.8 11.4 14.2 15.4 9.6"/>',
        'droplet'   => '<path d="M12 2.7 6.8 8.5a7.2 7.2 0 1 0 10.4 0z"/>',
        'rings'     => '<circle cx="9" cy="14.5" r="5.6"/><circle cx="15" cy="9.5" r="5.6"/>',
        'award'     => '<circle cx="12" cy="8.8" r="5.8"/><polyline points="8.4 13.5 7.2 22 12 19.4 16.8 22 15.6 13.5"/>',
        'leaf'      => '<path d="M20.5 3.5C10 3.5 4 9 4 17.5c0 1 .2 2 .5 3C6 13 11 9.5 20.5 8.5z"/><path d="M4.5 20.5C7 14 12 11 19 10"/>',
        'send'      => '<line x1="21" y1="3" x2="10.5" y2="13.5"/><polygon points="21 3 14.5 21 10.5 13.5 3 9.5 21 3"/>',
        'clock'     => '<circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/>',
        'file'      => '<path d="M14 2.5H7a2 2 0 0 0-2 2v15a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7.5z"/><polyline points="14 2.5 14 7.5 19 7.5"/>',
        'download'  => '<path d="M21 15.5v3a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 3 18.5v-3"/><polyline points="7.5 10.5 12 15 16.5 10.5"/><line x1="12" y1="15" x2="12" y2="3"/>',
        'bolt'      => '<polygon points="13 2 4.6 13.4 11 13.4 10.2 22 18.9 10.6 12.4 10.6 13 2"/>',
        'search'    => '<circle cx="10.5" cy="10.5" r="7"/><line x1="21" y1="21" x2="15.5" y2="15.5"/>',
        'plus'      => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'home'      => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5.5 9.5V21h13V9.5"/>',
        'building'  => '<path d="M3 21h18"/><path d="M5 21V6.5l7-4 7 4V21"/><path d="M10 21v-5h4v5"/>',
        'cloud'     => '<path d="M18 18.5H7a4.5 4.5 0 0 1-.6-8.96A6 6 0 0 1 18 11.5a3.5 3.5 0 0 1 0 7z"/>',
        'caret'     => '<polyline points="6 9 12 15 18 9"/>',
    ];

    if (empty($paths[$name])) {
        return '';
    }
    return '<svg' . ($class ? ' class="' . $class . '"' : '') . ' viewBox="0 0 24 24" fill="none"'
         . ' stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'
         . $paths[$name] . '</svg>';
}

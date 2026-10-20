<?php

/**
 * What Settings changes on the site: its name and description, the views it
 * offers, each page's titles and texts, and the "Add me" links. Each is a
 * settings row; a missing one falls back to what the site said before it had
 * an admin, so a fresh database looks the same as the old site.
 */

const VIEWS = ['shelf' => 'Shelf', 'rows' => 'Rows', 'grid' => 'Grid', 'showcase' => 'Showcase', 'list' => 'List'];

const SITE_DEFAULTS = [
    'name'        => "Bruno's Game Shelf",
    // the words of the name in the accent colour, at its end
    'accent'      => 'Game Shelf',
];

const PAGE_DEFAULTS = [
    'shelf' => [
        'title'       => "Bruno's Game Shelf",
        'description' => "Bruno's video game collection, on the shelf.",
        'nav'         => 'All games',
    ],
    'pokemon' => [
        'nav'         => 'Pokémon',
        'title'       => 'The Pokémon Collection',
        'description' => "Bruno's Pokémon collection: every main series version, generation by generation, the spin-offs, and the ones still to catch.",
        'eyebrow'     => "Gotta catch 'em all",
        'heading'     => 'Pokémon',
        'accent'      => 'Collection',
        'intro'       => 'Every Pokémon game I have, from Red and Green in 1996 to Legends: Z-A. A dashed outline is a version still to catch.',
    ],
    'mario' => [
        'nav'         => 'Super Mario',
        'title'       => 'The Super Mario Collection',
        'description' => "Bruno's Super Mario collection: the platformers, Mario Kart, Mario Party, the RPGs, Luigi and Donkey Kong, and the ones still to find.",
        'eyebrow'     => "Let's-a go!",
        'heading'     => 'Super Mario',
        'accent'      => 'Collection',
        'intro'       => 'Every Mario game I have, from Super Mario Bros. in 1985 to Mario Kart World, series by series. A dashed outline is a game still to find, on the console it came out on.',
    ],
    'sims' => [
        'nav'         => 'The Sims',
        'title'       => 'The Sims Collection',
        'description' => "Bruno's The Sims collection: every game and pack, on disc and in the EA app, and the ones still to find.",
        'eyebrow'     => 'Sul sul!',
        'heading'     => 'The Sims',
        'accent'      => 'Collection',
        'intro'       => 'Every Sims game and pack I have, from the first one in 2000 to The Sims 4, with SimCity and MySims beside them. A solid case is on disc; an outline is in the EA app.',
    ],
    'gta' => [
        'nav'         => 'GTA',
        'title'       => 'Grand Theft Auto',
        'description' => "Bruno's Grand Theft Auto collection: every game on every platform I have it on, and the versions still to find.",
        'eyebrow'     => 'Vice City',
        'heading'     => 'Grand Theft',
        'accent'      => 'Auto',
        'intro'       => 'Every GTA I have, from the top-down original in {firstYear} to The Definitive Edition, on every platform I have it on. A dashed outline is a version still to find.',
    ],
];

const PAGE_NAMES = ['shelf' => 'All games', 'pokemon' => 'Pokémon', 'mario' => 'Super Mario', 'sims' => 'The Sims', 'gta' => 'GTA'];

const FRIEND_DEFAULTS = [
    'psn'      => ['label' => 'PSN', 'long' => 'Add me on PSN', 'aria' => 'Add me on PlayStation', 'url' => 'https://psnprofiles.com/bvidasi', 'show' => true],
    'nintendo' => ['label' => 'Nintendo', 'long' => 'Add me on Nintendo', 'aria' => 'Add me on Nintendo', 'url' => 'https://lounge.nintendo.com/friendcode/0941-9916-2497/CwbBTf2PGB', 'show' => true],
    'xbox'     => ['label' => 'Xbox', 'long' => 'Add me on Xbox', 'aria' => 'Add me on Xbox', 'url' => 'https://www.xbox.com/en-AU/play/user?gamerTag=brunovidasi', 'show' => true],
    // with no link of its own, the Steam profile the sync is connected to (its page has the Add Friend button)
    'steam'    => ['label' => 'Steam', 'long' => 'Add me on Steam', 'aria' => 'Add me on Steam', 'url' => '', 'show' => true],
    // the EA app has no link to add a friend, so its button copies the EA ID (kept in url) to search for in the app
    'ea'       => ['label' => 'EA', 'long' => 'Add me on EA app', 'aria' => 'Copy my EA ID, to add me on the EA app', 'url' => 'brunovidasi', 'show' => true, 'copy' => true],
];

const FRIEND_NAMES = ['psn' => 'PlayStation', 'nintendo' => 'Nintendo', 'xbox' => 'Xbox', 'steam' => 'Steam', 'ea' => 'EA app'];

/**
 * The collections turned off in Settings (pages_hidden). For visitors one leaves the top bar, and its address opens
 * a plain filter on the shelf, not a page not found: its games
 * only, in the shelf's own look (js/app.js, SITE.plain), without its look, header or checklist. The signed-in owner
 * still sees the whole page, to work on it before it goes back up. The shelf is always on.
 */
function hidden_pages(): array
{
    return array_values(array_intersect((array) setting('pages_hidden', []), array_diff(array_keys(PAGE_NAMES), ['shelf'])));
}

function page_shown(string $page): bool
{
    return !in_array($page, hidden_pages(), true) || signed_in();
}

function site_settings(): array
{
    return array_merge(SITE_DEFAULTS, (array) setting('site', []));
}

/**
 * The views turned on, and the one each page opens on, on a computer and on a phone
 * (defaults[page][desktop|mobile]). The Sims, GTA, Pokémon and Super Mario can open on their Checklist too.
 * Unset, the shelf opens on its bookcase on a computer (or the one view the site had
 * before there was one for each) and in rows on a phone; a collection in its grid.
 */
function views_settings(): array
{
    $saved = (array) setting('views', []);
    $enabled = array_values(array_intersect(array_keys(VIEWS), $saved['enabled'] ?? array_keys(VIEWS)));
    if (!$enabled) {
        $enabled = ['shelf'];
    }
    $fallback = [
        'shelf' => ['desktop' => $saved['default'] ?? 'shelf', 'mobile' => 'rows'],
        'pokemon' => ['desktop' => 'grid', 'mobile' => 'grid'],
        'mario' => ['desktop' => 'grid', 'mobile' => 'grid'],
        'sims'  => ['desktop' => 'grid', 'mobile' => 'grid'],
        'gta'   => ['desktop' => 'grid', 'mobile' => 'grid'],
    ];
    $defaults = [];
    foreach ($fallback as $page => $devices) {
        $offered = $page === 'shelf' ? $enabled : [...$enabled, 'checklist'];
        foreach ($devices as $device => $view) {
            $pick = $saved['defaults'][$page][$device] ?? $view;
            $defaults[$page][$device] = in_array($pick, $offered, true) ? $pick : (in_array($view, $offered, true) ? $view : $enabled[0]);
        }
    }

    return ['enabled' => $enabled, 'defaults' => $defaults];
}

function page_settings(?string $page = null): array
{
    $saved = (array) setting('pages', []);
    $pages = [];
    foreach (PAGE_DEFAULTS as $key => $defaults) {
        $pages[$key] = array_merge($defaults, (array) ($saved[$key] ?? []));
    }

    return $page === null ? $pages : $pages[$page];
}

function friend_settings(): array
{
    $saved = (array) setting('friends', []);
    $out = [];
    foreach (FRIEND_DEFAULTS as $key => $defaults) {
        $out[$key] = array_merge($defaults, (array) ($saved[$key] ?? []));
        $out[$key]['copy'] = !empty($defaults['copy']);
        if ($key === 'steam' && trim((string) $out[$key]['url']) === '' && ($sid = credential('steam', 'steam_id'))) {
            $out[$key]['url'] = 'https://steamcommunity.com/profiles/' . $sid;
        }
        $out[$key]['show'] = (bool) $out[$key]['show'] && ($out[$key]['copy'] ? trim((string) $out[$key]['url']) !== '' : safe_http_url((string) $out[$key]['url']));
    }

    return $out;
}

/** What the site's scripts are handed in window.SITE. */
function site_script_config(): array
{
    $views = views_settings();
    $admin = signed_in();

    return [
        'views'       => $views['enabled'],
        'defaultViews' => $views['defaults'],
        'pages'       => page_settings(),
        'version'     => data_version(),
        'api'         => url('api/'),
        'base'        => url(''),
        'admin'       => $admin, // the owner's own links: "Look for it" on what's still to find, a game's marks in its drawer
        'plain'       => $admin ? [] : hidden_pages(), // the collections turned off: plain filters on the shelf
        'csrf'        => $admin ? csrf_token() : null,
    ];
}

/**
 * Whether an address under the shelf is one of its pages (js/app.js draws it):
 * '' the whole shelf, 'wiiu' a console, 'ps2-final-fantasy-x' a game, and any of
 * those after a collection, 'sims/wiiu', or one of its checklist's sections,
 * 'gta/want-list' (the page itself goes back to the collection on one it hasn't).
 */
function shelf_route_exists(string $path): bool
{
    $parts = $path === '' ? [] : explode('/', $path);
    $collection = isset($parts[0], PAGE_NAMES[$parts[0]]) && $parts[0] !== 'shelf';
    if ($collection) {
        array_shift($parts);
    }
    if (count($parts) > 1) {
        return false;
    }
    if (!$parts) {
        return true;
    }
    $id = $parts[0];

    return isset(CONSOLES[$id]) || shelf_game_exists($id) || ($collection && preg_match('/^[a-z0-9-]+$/', $id));
}

/** A copy, or its twin under another console it plays on (alsoOn, 'x360-…' beside 'xone-…'). */
function shelf_game_exists(string $id): bool
{
    if (game_exists($id)) {
        return true;
    }
    [$console, $tail] = array_pad(explode('-', $id, 2), 2, '');
    if (!isset(CONSOLES[$console]) || $tail === '') {
        return false;
    }
    foreach (array_keys(CONSOLES) as $other) {
        if ($other !== $console && game_exists("$other-$tail")) {
            return true;
        }
    }

    return false;
}

/** The date the site says it was last updated: the last change, or the date the old games.json had. */
function data_updated(): string
{
    $changed = setting('data_changed_at');

    return $changed ? substr($changed, 0, 10) : (string) setting('seed_updated', date('Y-m-d'));
}

/* ---------- the Sims want list and the GTA, Pokémon and Super Mario checklists ---------- */

function wants_json(string $collection = 'sims'): array
{
    $stmt = db()->prepare('SELECT * FROM wantlist WHERE collection = ? ORDER BY position, id');
    $stmt->execute([$collection]);
    $wants = [];
    foreach ($stmt as $row) {
        $wants[] = want_from_row($row);
    }

    return ['updated' => (string) setting('wants_updated', data_updated()), 'wants' => $wants];
}

function want_from_row(array $row): array
{
    $w = ['id' => $row['id'], 'series' => $row['series'], 'title' => $row['title'], 'pack' => $row['pack']];
    if ($row['pack_code'] !== null) {
        $w['packCode'] = $row['pack_code'];
    }
    $w['released'] = $row['released'];
    $w['platform'] = $row['platform'];
    if ($row['note'] !== null) {
        $w['note'] = $row['note'];
    }

    return $w + json_column($row['extra_json']);
}

/**
 * A collection's checklist (the shape data/gta.json had): its eras in order, each
 * with its games, each game with the copies on the shelf and what is still to find.
 * A field kept for the admin alone (privateNote) is never handed out.
 */
function checklist_json(string $collection): array
{
    $eras = [];
    $stmt = db()->prepare('SELECT * FROM gta_eras WHERE collection = ? ORDER BY position, id');
    $stmt->execute([$collection]);
    foreach ($stmt as $row) {
        $eras[$row['id']] = ['id' => $row['id'], 'name' => $row['name'], 'blurb' => $row['blurb']] + checklist_extra($row['extra_json']) + ['games' => []];
    }
    $stmt = db()->prepare('SELECT * FROM gta_games WHERE collection = ? ORDER BY position, id');
    $stmt->execute([$collection]);
    foreach ($stmt as $row) {
        if (!isset($eras[$row['era_id']])) {
            continue;
        }
        $eras[$row['era_id']]['games'][] = [
            'title' => $row['title'], 'released' => $row['released'],
            'copies' => json_column($row['copies_json']), 'missing' => json_column($row['missing_json']),
        ] + checklist_extra($row['extra_json']);
    }

    return ['updated' => (string) setting($collection . '_updated', data_updated()), 'eras' => array_values($eras)];
}

const CHECKLIST_PRIVATE = ['privateNote'];

function checklist_extra(?string $raw): array
{
    return array_diff_key(json_column($raw), array_flip(CHECKLIST_PRIVATE));
}

function gta_json(): array
{
    return checklist_json('gta');
}

function pokemon_json(): array
{
    return checklist_json('pokemon');
}

function mario_json(): array
{
    return checklist_json('mario');
}

/** Puts a checklist (the shape checklist_json() hands out) in place of what the collection had. */
function load_checklist(string $collection, array $data): int
{
    return db_transaction(function (PDO $db) use ($collection, $data) {
        $db->prepare('DELETE FROM gta_games WHERE collection = ?')->execute([$collection]);
        $db->prepare('DELETE FROM gta_eras WHERE collection = ?')->execute([$collection]);
        $era = $db->prepare('INSERT INTO gta_eras (id, collection, position, name, blurb, extra_json) VALUES (?, ?, ?, ?, ?, ?)');
        $game = $db->prepare('INSERT INTO gta_games (era_id, collection, position, title, released, copies_json, missing_json, extra_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $n = 0;
        foreach ($data['eras'] ?? [] as $i => $e) {
            $extra = array_diff_key($e, array_flip(['id', 'name', 'blurb', 'games']));
            $era->execute([$e['id'], $collection, $i, $e['name'], $e['blurb'] ?? null, $extra ? to_json($extra) : null]);
            foreach ($e['games'] ?? [] as $j => $g) {
                $extra = array_diff_key($g, array_flip(['title', 'released', 'copies', 'missing']));
                $game->execute([$e['id'], $collection, $j, $g['title'], $g['released'] ?? null, to_json($g['copies'] ?? []), to_json($g['missing'] ?? []), $extra ? to_json($extra) : null]);
                $n++;
            }
        }
        set_setting($collection . '_updated', $data['updated'] ?? null);

        return $n;
    });
}

/**
 * Once, on a database that has the Pokémon games (cron/import_json.php brings them to a new one):
 * the checklist from seed/pokemon.json, Blue and Yellow made for the Game Boy (they stand with the
 * Game Boy Color's), and Yellow and Gold on the 3DS's Virtual Console beside their cartridges.
 */
function seed_pokemon(): void
{
    if (!game_exists('gbc-pokemon-blue-version')) {
        return; // a database without them: nothing to do, and import_json.php loads the checklist
    }
    db_transaction(function (PDO $db) {
        $has = $db->prepare('SELECT 1 FROM gta_eras WHERE collection = ? LIMIT 1');
        $has->execute(['pokemon']);
        if (!$has->fetchColumn()) {
            $file = __DIR__ . '/../seed/pokemon.json';
            $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
            if (!is_array($data)) {
                return; // not done until the checklist is in: tried again next time
            }
            load_checklist('pokemon', $data);
        }
        foreach (['gbc-pokemon-blue-version', 'gbc-pokemon-yellow-version'] as $id) {
            if (($g = find_game($id)) && empty($g['madeFor'])) {
                update_game(['madeFor' => 'gb'] + $g);
            }
        }
        $vc = [
            ['gbc-pokemon-yellow-version', '3ds-pokemon-yellow-version', '2016-02-27'],
            ['gbc-pokemon-gold-version', '3ds-pokemon-gold-version', '2017-09-22'],
        ];
        foreach ($vc as [$from, $id, $released]) {
            if (game_exists($id) || !($g = find_game($from))) {
                continue;
            }
            insert_game([
                'id' => $id, 'console' => '3ds', 'title' => $g['title'], 'sortAs' => $g['sortAs'] ?? null, 'edition' => 'Virtual Console',
                'region' => 'AUS', 'released' => $released, 'dateSource' => 'manual', 'publisher' => $g['publisher'] ?? null,
                'developer' => $g['developer'] ?? null, 'genre' => $g['genre'] ?? null, 'format' => 'digital',
                'cover' => isset($g['cover']['file']) ? ['file' => $g['cover']['file'], 'ratio' => $g['cover']['ratio'] ?? 1, 'source' => $g['cover']['source'] ?? null, 'ref' => $g['cover']['ref'] ?? null] : null,
                'colors' => $g['colors'] ?? null,
            ]);
        }
        $db->exec("INSERT IGNORE INTO settings (`key`, value) VALUES ('seeded_pokemon', 'true')");
    });
    touch_data();
}

/**
 * Once, on a database that has the Mario games (cron/import_json.php brings them to a new one):
 * the checklist from seed/mario.json.
 */
function seed_mario(): void
{
    if (!game_exists('snes-super-mario-world')) {
        return; // a database without them: nothing to do, and import_json.php loads the checklist
    }
    $has = db()->prepare('SELECT 1 FROM gta_eras WHERE collection = ? LIMIT 1');
    $has->execute(['mario']);
    if (!$has->fetchColumn()) {
        $file = __DIR__ . '/../seed/mario.json';
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (!is_array($data)) {
            return; // not done until the checklist is in: tried again next time
        }
        load_checklist('mario', $data);
    }
    // Super Mario Land 2, a Game Boy game, stands with the Game Boy Color's (as Pokémon Blue does)
    if (($g = find_game('gbc-super-mario-land-2-6-golden-coins')) && empty($g['madeFor'])) {
        update_game(['madeFor' => 'gb'] + $g);
    }
    db()->exec("INSERT IGNORE INTO settings (`key`, value) VALUES ('seeded_mario', 'true')");
    touch_data();
}

/** The marks on the "Add me" buttons. */
const FRIEND_ICONS = [
    'psn' => '<svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true"><path d="M8.984 2.596v17.547l3.915 1.261V6.688c0-.69.304-1.151.794-.991.636.18.76.814.76 1.505v5.875c2.441 1.193 4.362-.002 4.362-3.152 0-3.237-1.126-4.675-4.438-5.827-1.307-.448-3.728-1.186-5.39-1.502zm4.656 16.241l6.296-2.275c.715-.258.826-.625.246-.818-.586-.192-1.637-.139-2.357.123l-4.205 1.5V14.98l.24-.085s1.201-.42 2.913-.615c1.696-.18 3.785.03 5.437.661 1.848.601 2.04 1.472 1.576 2.072-.465.6-1.622 1.036-1.622 1.036l-8.544 3.107V18.86zM1.807 18.6c-1.9-.545-2.214-1.668-1.352-2.32.801-.586 2.16-1.052 2.16-1.052l5.615-2.013v2.313L4.205 17c-.705.271-.825.632-.239.826.586.195 1.637.15 2.343-.12L8.247 17v2.074c-.12.03-.256.044-.39.073-1.939.331-3.996.196-6.038-.479z"/></svg>',
    'nintendo' => '<svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true"><path d="M14.176 24h3.674c3.376 0 6.15-2.774 6.15-6.15V6.15C24 2.775 21.226 0 17.85 0H14.1c-.074 0-.15.074-.15.15v23.7c-.001.076.075.15.226.15zm4.574-13.199c1.351 0 2.399 1.125 2.399 2.398 0 1.352-1.125 2.4-2.399 2.4-1.35 0-2.4-1.049-2.4-2.4-.075-1.349 1.05-2.398 2.4-2.398zM11.4 0H6.15C2.775 0 0 2.775 0 6.15v11.7C0 21.226 2.775 24 6.15 24h5.25c.074 0 .15-.074.15-.149V.15c.001-.076-.075-.15-.15-.15zM9.676 22.051H6.15c-2.326 0-4.201-1.875-4.201-4.201V6.15c0-2.326 1.875-4.201 4.201-4.201H9.6l.076 20.102zM3.75 7.199c0 1.275.975 2.25 2.25 2.25s2.25-.975 2.25-2.25c0-1.273-.975-2.25-2.25-2.25s-2.25.977-2.25 2.25z"/></svg>',
    'xbox' => '<svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true"><path d="M4.102 21.033C6.211 22.881 8.977 24 12 24c3.026 0 5.789-1.119 7.902-2.967 1.877-1.912-4.316-8.709-7.902-11.417-3.582 2.708-9.779 9.505-7.898 11.417zm11.16-14.406c2.5 2.961 7.484 10.313 6.076 12.912C23.002 17.48 24 14.861 24 12.004c0-3.34-1.365-6.362-3.57-8.536 0 0-.027-.022-.082-.042-.063-.022-.152-.045-.281-.045-.592 0-1.985.434-4.805 3.246zM3.654 3.426c-.057.02-.082.041-.086.042C1.365 5.642 0 8.664 0 12.004c0 2.854.998 5.473 2.661 7.533-1.401-2.605 3.579-9.951 6.08-12.91-2.82-2.813-4.216-3.245-4.806-3.245-.131 0-.223.021-.281.046v-.002zM12 3.551S9.055 1.828 6.755 1.746c-.903-.033-1.454.295-1.521.339C7.379.646 9.659 0 11.984 0H12c2.334 0 4.605.646 6.766 2.085-.068-.046-.615-.372-1.52-.339C14.946 1.828 12 3.545 12 3.545v.006z"/></svg>',
    'steam' => '<svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true"><path d="M11.979 0C5.678 0 .511 4.86.022 11.037l6.432 2.658c.545-.371 1.203-.59 1.912-.59.063 0 .125.004.188.006l2.861-4.142V8.91c0-2.495 2.028-4.524 4.524-4.524 2.494 0 4.524 2.031 4.524 4.527s-2.03 4.525-4.524 4.525h-.105l-4.076 2.911c0 .052.004.105.004.159 0 1.875-1.515 3.396-3.39 3.396-1.635 0-3.016-1.173-3.331-2.727L.436 15.27C1.862 20.307 6.486 24 11.979 24c6.627 0 11.999-5.373 11.999-12S18.605 0 11.979 0zM7.54 18.21l-1.473-.61c.262.543.714.999 1.314 1.25 1.297.539 2.793-.076 3.332-1.375.263-.63.264-1.319.005-1.949s-.75-1.121-1.377-1.383c-.624-.26-1.29-.249-1.878-.03l1.523.63c.956.4 1.409 1.5 1.009 2.455-.397.957-1.497 1.41-2.454 1.012H7.54zm11.415-9.303c0-1.662-1.353-3.015-3.015-3.015-1.665 0-3.015 1.353-3.015 3.015 0 1.665 1.35 3.015 3.015 3.015 1.663 0 3.015-1.35 3.015-3.015zm-5.273-.005c0-1.252 1.013-2.266 2.265-2.266 1.249 0 2.266 1.014 2.266 2.266 0 1.251-1.017 2.265-2.266 2.265-1.253 0-2.265-1.014-2.265-2.265z"/></svg>',
    'ea' => '<svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true"><path d="M16.635 6.162l-5.928 9.377H4.24l1.508-2.3h4.024l1.474-2.335H2.264L.79 13.239h3.115L.24 18.983h12.15l4.237-6.705 1.472 2.336h-1.477l-1.474 2.299h4.405l1.096 1.722h3.11zm-11.096.004L4.069 8.437l9.509.003L15.05 6.166z"/></svg>',
];

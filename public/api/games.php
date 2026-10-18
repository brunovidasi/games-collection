<?php

/** Every copy, as the site reads it (the shape data/games.json had). Hidden ones too: the site leaves them out. */

require_once __DIR__ . '/../../includes/bootstrap.php';

// each picture of a cover with its file's time after it (?v=), as asset_url() does, so one replaced on the
// server is fetched anew instead of the old one the host lets a browser keep for a week
$version = function (array $cover) use (&$version): array {
    foreach ($cover as $k => $v) {
        if (is_array($v)) {
            $cover[$k] = $version($v);
        } elseif (is_string($v) && str_starts_with($v, 'covers/') && ($t = (int) @filemtime(public_dir() . '/' . $v))) {
            $cover[$k] = "$v?v=$t";
        }
    }

    return $cover;
};

// each with when it was added (added), for the site's Recently added sort
$added = games_added();
$games = array_map(function ($g) use ($added, $version) {
    $g = public_game($g) + ['added' => $added[$g['id']] ?? null];
    if (is_array($g['cover'] ?? null)) {
        $g['cover'] = $version($g['cover']);
    }

    return $g;
}, all_games());

send_api_json(encode_json(['updated' => data_updated(), 'games' => array_values($games)]));

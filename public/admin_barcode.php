<?php

/**
 * A copy's barcode, read off the box with the camera (js/barcode.js) from its
 * drawer on the shelf, signed in: saved here, or cleared when it comes empty.
 * Answers with the number as kept, and the other copies that have it too (two
 * copies of one print, or one scanned for the wrong game).
 */

require_once __DIR__ . '/../includes/admin.php';

admin_json_request();

$game = find_game(post('id'));
if (!$game) {
    json_response(['error' => 'There is no such copy.'], 404);
}

$code = null;
if (trim(post('barcode')) !== '') {
    [$code, $error] = clean_barcode(post('barcode'));
    if ($error) {
        json_response(['error' => $error], 422);
    }
}

if (($game['barcode'] ?? null) !== $code) {
    $game['barcode'] = $code;
    update_game($game);
}

$also = $code === null ? [] : array_filter(all_games(), fn ($g) => $g['id'] !== $game['id'] && ($g['barcode'] ?? null) === $code);
json_response([
    'barcode' => $code,
    'also'    => array_values(array_map(fn ($g) => ['id' => $g['id'], 'title' => $g['title'], 'console' => console_name($g['console'])], $also)),
]);

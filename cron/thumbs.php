<?php

/**
 * Makes every cover's smaller copies ahead of time (covers/_t, cover_thumb() in includes/images.php), so no
 * visitor waits while one is made; public/thumb.php still makes any that are missing when asked for.
 *
 *   php cron/thumbs.php
 *       every cover under public/covers, as the server names them when it runs there
 *   php cron/thumbs.php --from=https://brunovida.si/games/api/games
 *       here, for the server: the covers that site hands out, named by their time on the server (the ?v= its
 *       API gives each), so the copies made here are the ones it asks for. Then upload public/covers/_t by FTP.
 *       A cover whose file here isn't the server's (another size) is left out and listed, to make there.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Command line only.\n");
}

require_once __DIR__ . '/../includes/bootstrap.php';

$from = null;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--from=')) {
        $from = substr($arg, 7);
    }
}

$steps = array_merge(array_map('strval', THUMB_HEIGHTS), ['full']);
$covers = []; // "ps2/final-fantasy-x.jpg" => its time

if ($from === null) {
    foreach (glob(public_dir() . '/covers/*/*') ?: [] as $file) {
        $rel = substr($file, strlen(public_dir() . '/covers/'));
        if (!str_starts_with($rel, '_t/') && preg_match('/\.(jpe?g|png|webp|gif)$/i', $rel)) {
            $covers[$rel] = (int) filemtime($file);
        }
    }
} else {
    // every "covers/…?v=…" in the API's answer, wherever in a game's cover it is (file, spine, back, label, parts…)
    $json = Http::get($from)->body;
    preg_match_all('#"covers/([A-Za-z0-9_-]+/[A-Za-z0-9._-]+)\?v=(\d+)"#', $json, $m, PREG_SET_ORDER);
    foreach ($m as [, $rel, $v]) {
        $covers[$rel] = (int) $v;
    }
    if (!$covers) {
        exit("No covers in $from\n");
    }

    // the server's size of each, to make copies only of the ones that are the same picture here
    $site = preg_replace('#api/games.*$#', '', $from);
    $sizes = [];
    $queue = array_keys($covers);
    $mh = curl_multi_init();
    $running = [];
    while ($queue || $running) {
        while ($queue && count($running) < 16) {
            $rel = array_shift($queue);
            $ch = curl_init($site . 'covers/' . $rel . '?v=' . $covers[$rel]);
            curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_USERAGENT => COVER_UA]);
            curl_multi_add_handle($mh, $ch);
            $running[(int) $ch] = [$ch, $rel];
        }
        curl_multi_exec($mh, $active);
        curl_multi_select($mh, 1.0);
        while ($done = curl_multi_info_read($mh)) {
            [$ch, $rel] = $running[(int) $done['handle']];
            $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $sizes[$rel] = $code === 200 ? (int) curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD) : -$code;
            curl_multi_remove_handle($mh, $ch);
            unset($running[(int) $done['handle']]);
        }
    }

    $skipped = [];
    foreach ($covers as $rel => $v) {
        $here = (int) @filesize(public_dir() . "/covers/$rel");
        if ($sizes[$rel] !== $here) {
            $skipped[] = sprintf('  %s (server %s, here %s)', $rel, $sizes[$rel] < 0 ? 'HTTP ' . -$sizes[$rel] : $sizes[$rel] . ' bytes', $here ? "$here bytes" : 'missing');
            unset($covers[$rel]);
        }
    }
    if ($skipped) {
        echo count($skipped) . " covers here aren't the server's, left for it to make:\n" . implode("\n", $skipped) . "\n";
    }
}

$made = $failed = 0;
$bytes = 0;
foreach ($covers as $rel => $v) {
    foreach ($steps as $step) {
        try {
            $file = cover_thumb($step, $rel, $v);
        } catch (Throwable $e) {
            $file = null;
        }
        if ($file === null) {
            $failed++;
            echo "  could not make $step/$rel\n";
            continue;
        }
        $made++;
        $bytes += filesize($file);
    }
}

printf("%d copies of %d covers in public/covers/_t (%s)%s\n", $made, count($covers), format_bytes($bytes), $failed ? ", $failed failed" : '');

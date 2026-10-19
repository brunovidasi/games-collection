<?php

/**
 * A cover's smaller copy, the first time it is asked for: js/cases.js asks for each cover at the height it
 * draws it (covers/_t/330/ps2/final-fantasy-x.jpg-1792127530.webp), and .htaccess sends the ask here while
 * there is no such file yet. cover_thumb() makes it where it was asked for, so the next ask is a plain file.
 * Asked for with an older time than the cover's own, it sends the browser on to the current one; where no
 * copy can be made (GD without WebP), to the cover itself.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$step = (string) ($_GET['s'] ?? '');
$rel = (string) ($_GET['f'] ?? '');
$version = (int) ($_GET['v'] ?? 0);

$src = preg_match('#^[A-Za-z0-9_-]+/[A-Za-z0-9][A-Za-z0-9._-]*$#', $rel) ? public_dir() . "/covers/$rel" : '';
$mtime = $src !== '' ? (int) @filemtime($src) : 0;
if (!$mtime || ($step !== 'full' && !in_array((int) $step, THUMB_HEIGHTS, true))) {
    not_found();
}
if ($version !== $mtime) {
    header('Cache-Control: no-cache');
    redirect(url("covers/_t/$step/$rel-$mtime.webp"));
}

try {
    $file = cover_thumb($step, $rel, $mtime);
} catch (Throwable $e) {
    error_log('thumb: ' . $e->getMessage());
    $file = null;
}
if ($file === null) {
    header('Cache-Control: no-cache');
    redirect(url("covers/$rel?v=$mtime"));
}

header('Content-Type: image/webp');
header('Content-Length: ' . filesize($file));
header('Cache-Control: public, max-age=31536000, immutable');
header('X-Content-Type-Options: nosniff');
readfile($file);

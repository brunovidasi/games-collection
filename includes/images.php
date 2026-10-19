<?php

/**
 * Covers, with GD: every cover is saved as a JPEG 520 px tall (the size the
 * shelf draws the biggest case at), on white where it was transparent, and
 * measured for the case: its shape (width over height) and the three colours
 * that cover most of it, which the spine is drawn in. As tools/fetch-covers.mjs
 * and tools/palette.mjs did with sharp.
 */

const COVER_HEIGHT = 520;
const LABEL_HEIGHT = 400;

function image_from_bytes(string $bytes): GdImage
{
    if (!function_exists('imagecreatefromstring')) {
        throw new RuntimeException('PHP\'s GD extension is needed to save covers.');
    }
    $img = @imagecreatefromstring($bytes);
    if (!$img) {
        throw new RuntimeException('That is not a picture GD can read (JPEG, PNG, WebP, GIF, AVIF).');
    }

    return $img;
}

/** [width, height] of a picture, without keeping it. */
function image_size(string $bytes): array
{
    $info = @getimagesizefromstring($bytes);
    if ($info) {
        return [$info[0], $info[1]];
    }
    $img = image_from_bytes($bytes);

    return [imagesx($img), imagesy($img)];
}

/**
 * Saves a picture as a cover under public/ ($relPath: "covers/ps2/final-fantasy-x.jpg"),
 * at most $height tall, and returns its shape and colours.
 *
 * @return array{file: string, ratio: float, colors: string[]}
 */
function save_cover_image(string $bytes, string $relPath, int $height = COVER_HEIGHT): array
{
    $src = image_from_bytes($bytes);
    $w = imagesx($src);
    $h = imagesy($src);

    $outH = min($height, $h); // never enlarged
    $outW = max(1, (int) round($w * $outH / $h));

    $out = imagecreatetruecolor($outW, $outH);
    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
    imagealphablending($out, true);
    imagecopyresampled($out, $src, 0, 0, 0, 0, $outW, $outH, $w, $h);

    $abs = public_dir() . '/' . ltrim($relPath, '/');
    if (!is_dir(dirname($abs)) && !@mkdir(dirname($abs), 0755, true) && !is_dir(dirname($abs))) {
        throw new RuntimeException('Could not create ' . dirname($relPath));
    }
    if (!imagejpeg($out, $abs, 82)) {
        throw new RuntimeException("Could not write $relPath");
    }
    @chmod($abs, 0644);

    return ['file' => $relPath, 'ratio' => round($outW / $outH, 4), 'colors' => image_palette($src)];
}

/**
 * The three colours that cover most of a picture: a tiny k-means over a 24×24
 * thumbnail, as tools/palette.mjs did.
 *
 * @return string[] "#rrggbb"
 */
function image_palette(GdImage $src): array
{
    $thumb = imagecreatetruecolor(24, 24);
    imagefill($thumb, 0, 0, imagecolorallocate($thumb, 255, 255, 255));
    imagecopyresampled($thumb, $src, 0, 0, 0, 0, 24, 24, imagesx($src), imagesy($src));

    $px = [];
    for ($y = 0; $y < 24; $y++) {
        for ($x = 0; $x < 24; $x++) {
            $c = imagecolorat($thumb, $x, $y);
            $px[] = [($c >> 16) & 255, ($c >> 8) & 255, $c & 255];
        }
    }

    $n = count($px);
    $cs = [$px[0], $px[intdiv($n, 2)], $px[$n - 1], $px[intdiv($n, 3)]];
    $groups = [];
    for ($it = 0; $it < 12; $it++) {
        $groups = array_fill(0, count($cs), []);
        foreach ($px as $p) {
            $bi = 0;
            $bd = INF;
            foreach ($cs as $i => $c) {
                $d = ($p[0] - $c[0]) ** 2 + ($p[1] - $c[1]) ** 2 + ($p[2] - $c[2]) ** 2;
                if ($d < $bd) {
                    $bd = $d;
                    $bi = $i;
                }
            }
            $groups[$bi][] = $p;
        }
        foreach ($groups as $i => $g) {
            if ($g) {
                $k = count($g);
                $cs[$i] = [array_sum(array_column($g, 0)) / $k, array_sum(array_column($g, 1)) / $k, array_sum(array_column($g, 2)) / $k];
            }
        }
    }

    $ranked = array_map(fn ($c, $g) => ['c' => $c, 'n' => count($g)], $cs, $groups);
    usort($ranked, fn ($a, $b) => $b['n'] <=> $a['n']);

    return array_map(fn ($r) => sprintf('#%02x%02x%02x', ...array_map(fn ($v) => (int) round($v), $r['c'])), array_slice($ranked, 0, 3));
}

/** The shape and colours of a cover already on disk (one uploaded by FTP), or null when it isn't there. */
function measure_cover_file(string $relPath): ?array
{
    $abs = public_dir() . '/' . ltrim($relPath, '/');
    if (!is_file($abs)) {
        return null;
    }
    $bytes = (string) file_get_contents($abs);
    $img = image_from_bytes($bytes);

    return ['ratio' => round(imagesx($img) / imagesy($img), 4), 'colors' => image_palette($img)];
}

/* ---------- the smaller copies the shelf draws ---------- */

// the heights a cover's smaller copies come in (THUMBS in js/cases.js); 'full' is the picture's own height
const THUMB_HEIGHTS = [100, 150, 220, 330, 480];

/**
 * A cover's copy as WebP at one of THUMB_HEIGHTS (never enlarged), made the first time it is asked for
 * (public/thumb.php) and saved where it was asked for, covers/_t/<height>/<the cover's path>-<its time>.webp,
 * so Apache serves it as a plain file from then on. A newer copy of the same cover clears out the older ones.
 * Returns its path on disk, or null when there is no such cover or GD can't write WebP.
 */
function cover_thumb(string $step, string $rel, int $version): ?string
{
    if ($step !== 'full' && !in_array((int) $step, THUMB_HEIGHTS, true)) {
        return null;
    }
    // one folder deep, as every cover is (covers/ps2/final-fantasy-x.jpg): nothing that climbs out of covers/
    if (!preg_match('#^[A-Za-z0-9_-]+/[A-Za-z0-9][A-Za-z0-9._-]*\.(jpe?g|png|webp|gif)$#', $rel) || str_starts_with($rel, '_t/')) {
        return null;
    }
    $src = public_dir() . "/covers/$rel";
    if (!is_file($src) || !function_exists('imagewebp')) {
        return null;
    }
    $out = public_dir() . "/covers/_t/$step/$rel-$version.webp";
    if (is_file($out)) {
        return $out;
    }

    $img = image_from_bytes((string) file_get_contents($src));
    $w = imagesx($img);
    $h = imagesy($img);
    $outH = $step === 'full' ? $h : min((int) $step, $h);
    if ($outH !== $h) {
        $outW = max(1, (int) round($w * $outH / $h));
        $small = imagecreatetruecolor($outW, $outH);
        imagealphablending($small, false);
        imagesavealpha($small, true);
        imagecopyresampled($small, $img, 0, 0, 0, 0, $outW, $outH, $w, $h);
        $img = $small;
    } elseif (!imageistruecolor($img)) {
        imagepalettetotruecolor($img);
    }

    $dir = dirname($out);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return null;
    }
    // written beside it and moved into place, so two asking at once never see half a file
    $tmp = "$out." . getmypid() . '.tmp';
    if (!imagewebp($img, $tmp, 80)) {
        @unlink($tmp);
        return null;
    }
    @chmod($tmp, 0644);
    rename($tmp, $out);
    foreach (glob($dir . '/' . basename($rel) . '-*.webp') ?: [] as $old) {
        if ($old !== $out) {
            @unlink($old);
        }
    }

    return $out;
}

/** Where a copy's cover goes: covers/<console>/<the id without the console>.jpg, as the old tools named them. */
function cover_path_for(array $game, string $suffix = ''): string
{
    $console = $game['console'];
    $name = str_starts_with($game['id'], $console . '-') ? substr($game['id'], strlen($console) + 1) : $game['id'];

    return "covers/$console/" . $name . $suffix . '.jpg';
}

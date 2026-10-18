<?php

/**
 * The shelf: every page of the site (/sims, /gta, /pokemon and /mario are the same page in other
 * clothes, and /wiiu, /ps2-final-fantasy-x, /sims/wiiu are it with a console
 * picked or a game open, js/app.js). The texts, the views and the "Add me" links
 * come from Settings; the games from the API (api/games and the platforms beside it).
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// the address under the shelf: what isn't one of its pages is a 404
$route = trim(substr(rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'), strlen(rtrim(app_path(), '/'))), '/');
if ($route === 'index' || $route === 'index.php') {
    $route = '';
}
if (!shelf_route_exists($route)) {
    require __DIR__ . '/404.php';
    exit;
}

$site = site_settings();
$shelf = page_settings('shelf');
$pages = page_settings();
$friends = array_filter(friend_settings(), fn ($f) => $f['show']);
$config = site_script_config(); // before any output: it may open the session

// the collection in the address (/gta, /sims/ps2), dressed as it from the first paint, not as the shelf
// until js/app.js has the games
$col = explode('/', $route)[0];
$col = isset(PAGE_NAMES[$col]) && $col !== 'shelf' ? $col : '';
// a collection turned off in Settings is, for visitors, a plain filter on the shelf: dressed as the shelf
$themed = $col !== '' && page_shown($col);
$title = $col !== '' ? ($themed ? $pages[$col]['title'] : $pages[$col]['nav']) . ' · ' . $shelf['title'] : $shelf['title'];
$about = $col !== '' ? $pages[$col]['description'] : $shelf['description'];
$icon = $themed ? "favicon-$col.svg" : 'favicon.svg';
$current = fn (string $c) => $c === $col ? ' aria-current="page"' : '';
// a collection turned off in Settings is left out of the top bar for visitors (its address still opens, as a plain
// filter on the shelf); the signed-in owner still has its link, marked, and its whole page
$off = fn (string $c) => signed_in() && in_array($c, hidden_pages(), true) ? ' data-hidden title="A plain filter for visitors (Settings)"' : '';

// the name in the top bar, its last words in the accent colour
$name = (string) $site['name'];
$accent = (string) $site['accent'];
$brand = $accent !== '' && str_ends_with($name, $accent)
    ? e(rtrim(substr($name, 0, -strlen($accent)))) . ' <span>' . e($accent) . '</span>'
    : e($name);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en"<?= $themed ? ' class="collection ' . e($col) . '"' : '' ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($about) ?>">
<link rel="icon" href="<?= e(url($icon)) ?>" type="image/svg+xml">
<link rel="preload" href="<?= e(url('fonts/orbitron-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= e(url('fonts/exo2-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset_url('css/site.css')) ?>">
<link rel="stylesheet" href="<?= e(asset_url('css/cases.css')) ?>">
<link rel="stylesheet" href="<?= e(asset_url('css/collection.css')) ?>">
<link rel="stylesheet" href="<?= e(asset_url('css/sims.css')) ?>">
<link rel="stylesheet" href="<?= e(asset_url('css/gta.css')) ?>">
<link rel="stylesheet" href="<?= e(asset_url('css/pokemon.css')) ?>">
<link rel="stylesheet" href="<?= e(asset_url('css/mario.css')) ?>">
</head>
<body>

<div class="topbar">
  <button type="button" class="menu-toggle" aria-label="Menu" aria-expanded="false" aria-controls="topmenu"><span></span></button>
  <div class="brandbox">
    <a class="brand" href="<?= e(url('')) ?>"><?= $brand ?></a>
    <div class="figures" id="figures" aria-label="The collection in figures"></div>
  </div>
  <div class="topmenu" id="topmenu">
  <nav class="topnav" aria-label="Collections">
    <a href="<?= e(url('')) ?>" data-col=""<?= $current('') ?>><?= e($pages['shelf']['nav']) ?></a>
<?php if (page_shown('pokemon')): ?>
    <a href="<?= e(url('pokemon')) ?>" data-col="pokemon"<?= $current('pokemon') ?><?= $off('pokemon') ?>><img class="nav-ball" src="<?= e(url('assets/pokeball.svg')) ?>" alt="" width="13" height="13"><?= e($pages['pokemon']['nav']) ?></a>
<?php endif; ?>
<?php if (page_shown('mario')): ?>
    <a href="<?= e(url('mario')) ?>" data-col="mario"<?= $current('mario') ?><?= $off('mario') ?>><img class="nav-block" src="<?= e(url('assets/qblock.svg')) ?>" alt="" width="13" height="13"><?= e($pages['mario']['nav']) ?></a>
<?php endif; ?>
<?php if (page_shown('sims')): ?>
    <a href="<?= e(url('sims')) ?>" data-col="sims"<?= $current('sims') ?><?= $off('sims') ?>><img class="nav-bob" src="<?= e(url('assets/plumbob.svg')) ?>" alt="" width="8" height="16"><?= e($pages['sims']['nav']) ?></a>
<?php endif; ?>
<?php if (page_shown('gta')): ?>
    <a href="<?= e(url('gta')) ?>" data-col="gta"<?= $current('gta') ?><?= $off('gta') ?>><?= e($pages['gta']['nav']) ?></a>
<?php endif; ?>
  </nav>
<?php if ($friends): ?>
  <nav class="addme" aria-label="Add me">
<?php foreach ($friends as $key => $f): ?>
<?php if ($f['copy']): ?>
    <button type="button" class="<?= $key ?>" data-copy="<?= e($f['url']) ?>" aria-label="<?= e($f['aria']) ?>"><span class="lbl"><?= e($f['long']) ?></span><?= FRIEND_ICONS[$key] ?></button>
<?php else: ?>
    <a class="<?= $key ?>" href="<?= e($f['url']) ?>" target="_blank" rel="noopener" aria-label="<?= e($f['aria']) ?>"><span class="lbl"><?= e($f['long']) ?></span><?= FRIEND_ICONS[$key] ?></a>
<?php endif; ?>
<?php endforeach; ?>
  </nav>
  <nav class="addme addme-strip" aria-label="Add me">
    <span>Add me on</span>
<?php foreach ($friends as $key => $f): ?>
<?php if ($f['copy']): ?>
    <button type="button" class="<?= $key ?>" data-copy="<?= e($f['url']) ?>" aria-label="<?= e($f['aria']) ?>"><span class="lbl"><?= e($f['label']) ?></span><?= FRIEND_ICONS[$key] ?></button>
<?php else: ?>
    <a class="<?= $key ?>" href="<?= e($f['url']) ?>" target="_blank" rel="noopener" aria-label="<?= e($f['aria']) ?>"><span class="lbl"><?= e($f['label']) ?></span><?= FRIEND_ICONS[$key] ?></a>
<?php endif; ?>
<?php endforeach; ?>
  </nav>
<?php endif; ?>
  </div>
</div>

<div id="shell"></div>

<div class="tools">
  <div class="grp" id="libWrap" hidden>
    <div class="pills" id="lib" role="group" aria-label="Filter PC games by library"></div>
  </div>
</div>

<main class="case-wrap"><div class="bookcase" id="case"></div></main>
<section id="checklist" aria-label="Checklist"></section>

<footer>
  <span>© <?= date('Y') ?></span>
  <span>Made with <span class="heart" aria-label="love">♥</span> by <a href="https://brunovida.si" target="_blank" rel="noopener">brunovida.si</a></span>
</footer>

<script>window.SITE = <?= json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script>
// on a phone the pages and the add-me buttons fold into a menu behind the burger at the right of the top bar
(() => {
  const bar = document.querySelector('.topbar'), toggle = bar.querySelector('.menu-toggle');
  const setMenu = on => {
    bar.classList.toggle('menu-open', on);
    toggle.setAttribute('aria-expanded', String(on));
    toggle.setAttribute('aria-label', on ? 'Close menu' : 'Menu');
  };
  toggle.addEventListener('click', () => setMenu(!bar.classList.contains('menu-open')));
  bar.querySelectorAll('.topnav a').forEach(a => a.addEventListener('click', () => setMenu(false)));
  document.addEventListener('click', e => { if (!bar.contains(e.target)) setMenu(false); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && bar.classList.contains('menu-open')) { setMenu(false); toggle.focus(); } });
  matchMedia('(max-width: 960px)').addEventListener('change', () => setMenu(false));
})();
// an "Add me" with no link (the EA app) copies the ID to search for, and says so for a moment: on its own label,
// or in the phone menu's row of tiles (too narrow for it) over them, in place of "Add me on"
document.querySelectorAll('.addme [data-copy]').forEach(b => b.addEventListener('click', async () => {
  const lbl = b.closest('.addme-strip')?.querySelector(':scope > span') || b.querySelector('.lbl'), was = lbl.textContent;
  try { await navigator.clipboard.writeText(b.dataset.copy); lbl.textContent = 'Copied: ' + b.dataset.copy; }
  catch (e) { lbl.textContent = 'EA ID: ' + b.dataset.copy; }
  b.classList.add('copied'); lbl.classList.add('copied');
  setTimeout(() => { lbl.textContent = was; b.classList.remove('copied'); lbl.classList.remove('copied'); }, 2500);
}));
</script>
<script src="<?= e(asset_url('js/consoles.js')) ?>"></script>
<script src="<?= e(asset_url('js/console-art.js')) ?>"></script>
<script src="<?= e(asset_url('js/cases.js')) ?>"></script>
<script src="<?= e(asset_url('js/collection.js')) ?>"></script>
<script src="<?= e(asset_url('js/sims.js')) ?>"></script>
<script src="<?= e(asset_url('js/gta.js')) ?>"></script>
<script src="<?= e(asset_url('js/pokemon.js')) ?>"></script>
<script src="<?= e(asset_url('js/mario.js')) ?>"></script>
<script src="<?= e(asset_url('js/dropdown.js')) ?>"></script>
<script src="<?= e(asset_url('js/app.js')) ?>"></script>
<?php if ($config['admin']): ?><script src="<?= e(asset_url('js/barcode.js')) ?>"></script><?php endif; ?>
</body>
</html>

/* pokemon.js — the Pokémon collection (/pokemon), in the look of css/pokemon.css. data/pokemon.json lists every
   main series version generation by generation, each with the copies on the shelf (ids from data/games.json)
   and the platforms it is still to find on, its pair (the versions that came out together: Diamond & Pearl), the
   region it is set in and, for a remake, what it remakes; the spin-offs come after, in an era of their own
   (spinoff). The copies stand on the shelf (js/app.js) grouped by generation, by pair or by region (groups). The
   checklist groups them the same way: a row for each pair, its copies on a stretch of shelf and each version
   still to find an outline at its case's real size. Those are the want list at the bottom. */

(function () {
  const { card, meter, wantCard, esc } = Collection;
  // the page's names and texts, from Settings (window.SITE, from index.php)
  const PAGE = Object.assign({
    nav: 'Pokémon', title: 'The Pokémon Collection', eyebrow: 'Gotta catch \'em all', heading: 'Pokémon', accent: 'Collection',
    description: 'Bruno\'s Pokémon collection: every main series version, generation by generation, the spin-offs, and the ones still to catch.',
    intro: 'Every Pokémon game I have, from Red and Green in 1996 to Legends: Z-A. A dashed outline is a version still to catch.',
  }, window.SITE?.pages?.pokemon);

  // the regions, in the order the games first went to them
  const REGION_ORDER = ['Kanto', 'Johto', 'Hoenn', 'Sinnoh', 'Unova', 'Kalos', 'Alola', 'Galar', 'Paldea'];
  const REGION_BLURB = {
    Kanto: 'Where it started: Red and Green, Yellow, and both of its remakes.',
    Johto: 'Gold and Silver, Crystal, and HeartGold and SoulSilver.',
    Hoenn: 'Ruby and Sapphire, Emerald, and Omega Ruby and Alpha Sapphire.',
    Sinnoh: 'Diamond and Pearl, Platinum, their remakes, and Hisui long ago in Legends: Arceus.',
    Unova: 'Black and White, and the one pair of sequels.',
    Kalos: 'X and Y, and Lumiose City again in Legends: Z-A.',
    Alola: 'Sun and Moon, then Ultra Sun and Ultra Moon.',
    Galar: 'Sword and Shield.',
    Paldea: 'Scarlet and Violet.',
  };
  const SPINOFF = 'Spin-offs';

  /** An outline where a version still to find would stand, at that case's real size
      (signed in, a search for it: "Look for it"). */
  function missing(title, id) {
    const p = CONSOLE_BY_ID[id];
    const s = Collection.stageH() / 190;
    const look = !!window.SITE?.admin;
    const a = document.createElement(look ? 'a' : 'div');
    a.className = 'cell missing';
    if (look) {
      a.href = `https://www.google.com/search?q=${encodeURIComponent(`${title} ${p.name}`)}`;
      a.target = '_blank';
      a.rel = 'noopener';
      a.setAttribute('aria-label', `${title} on ${p.name}: still to find. Look for it`);
    }
    a.innerHTML = `<span class="stage"><span class="gt"><span class="ghostbox" style="width:${(p.w * s).toFixed(1)}px;height:${(p.h * s).toFixed(1)}px"><b>${esc(p.short)}</b><i>Still to catch</i></span></span></span>
      <span class="cap"><b>${esc(short(title))}</b><small>${esc(p.name)}${look ? ' · Look for it ↗' : ''}</small></span>`;
    return a;
  }

  // inside its row "Pokémon: Let's Go, Pikachu!" reads as "Let's Go, Pikachu!"
  const short = t => t.replace(/^Pokémon:? /, '');
  const year = v => (v.released || '').slice(0, 4);

  let data, byId, versions, mainline, spinoffs, wants;
  const versionOf = new Map(); // a copy's id → its version in data/pokemon.json

  // the generations, pairs and regions, each { key, label, name, blurb, order, versions }
  let GENS = [], PAIRS = [], REGIONS = [];
  function build() {
    versions = data.eras.flatMap((e, ei) => e.games.map(v => ({ ...v, era: e, eraIndex: ei })));
    mainline = versions.filter(v => !v.era.spinoff);
    spinoffs = versions.filter(v => v.era.spinoff);
    wants = versions.flatMap(v => v.missing.map(p => ({ v, p })));
    versions.forEach((v, i) => { v.order = i; v.copies.forEach(id => versionOf.has(id) || versionOf.set(id, v)); });
    GENS = data.eras.map((e, i) => ({ key: e.id, label: e.short || e.name, name: e.name, blurb: e.blurb, order: i, spinoff: !!e.spinoff, versions: versions.filter(v => v.era === e) }));
    // a pair is its versions that came out together; a spin-off is a pair of its own
    const byPair = new Map();
    versions.forEach(v => {
      const name = v.era.spinoff ? short(v.title) : v.pair;
      if (!byPair.has(name)) byPair.set(name, { key: 'pair-' + byPair.size, label: name, name, order: byPair.size, spinoff: !!v.era.spinoff, versions: [] });
      byPair.get(name).versions.push(v);
    });
    PAIRS = [...byPair.values()];
    versions.forEach(v => { v.pairGroup = PAIRS.find(p => p.versions.includes(v)); });
    REGIONS = [...REGION_ORDER.map(r => ({ name: r, versions: mainline.filter(v => v.region === r) })), { name: SPINOFF, spinoff: true, versions: spinoffs }]
      .filter(r => r.versions.length)
      .map((r, i) => ({ key: 'region-' + r.name.toLowerCase().replace(/[^a-z]+/g, '-'), label: r.name, name: r.name, blurb: r.spinoff ? GENS.find(g => g.spinoff)?.blurb : REGION_BLURB[r.name], order: i, spinoff: !!r.spinoff, versions: r.versions }));
  }
  const groupsOf = key => key === 'pair' ? PAIRS : key === 'region' ? REGIONS : GENS;
  const groupOfVersion = (key, v) => groupsOf(key).find(gr => gr.versions.includes(v));

  // the intro (PAGE.intro) is left out for now, to keep the header short. The heading's Pokémon is the series'
  // own logo (logos/pokemon.webp, from Wikimedia Commons), unless Settings has given it other words
  function hero() {
    const base = window.SITE?.base || '';
    const heading = /^pok[eé]mon$/i.test(PAGE.heading.trim())
      ? `<img class="logo" src="${base}logos/pokemon.webp" alt="${esc(PAGE.heading)}" width="816" height="300">`
      : esc(PAGE.heading);
    return `
      <header class="hero">
        <div>
          <div class="eyebrow">${esc(PAGE.eyebrow)}</div>
          <h1>${heading} <em>${esc(PAGE.accent)}</em><img class="ball" src="${base}assets/pokeball.svg" alt="" width="56" height="56"></h1>
        </div>
      </header>`;
  }

  // the figures in the top bar, beside the site's name (js/app.js)
  function figures(copies) {
    const caught = mainline.filter(v => v.copies.some(id => byId.has(id))).length;
    return [[`${caught}/${mainline.length}`, 'Versions'], [copies.length, 'Copies'], [GENS.filter(g => !g.spinoff).length, 'Generations', 'Gens'], [wants.length, 'Still to catch', 'To catch']];
  }

  /** A pair's row: its name and year, what it remakes, then its versions: the copies, and an outline for each still to find. */
  function pairRow(p, { gen = false, region = false } = {}) {
    const vs = p.versions;
    const first = vs[0];
    const have = vs.filter(v => v.copies.length).length;
    const tags = [
      gen && !first.era.spinoff ? `<span class="tag">${esc(first.era.short)}</span>` : '',
      region && first.region ? `<span class="tag">${esc(first.setting || first.region)}</span>` : '',
      first.remakes ? `<span class="tag remake">Remake of ${esc(first.remakes)}</span>` : '',
    ].join('');
    const row = document.createElement('div');
    row.className = 'title-row';
    row.innerHTML = `<div class="tr-head"><span class="yr">${esc(year(first))}</span><h3>${esc(p.name)}</h3>${tags ? `<span class="tags">${tags}</span>` : ''}
      <small>${p.spinoff ? `${vs.reduce((n, v) => n + v.copies.length, 0)} on the shelf` : `${have} of ${vs.length} ${vs.length === 1 ? 'version' : 'versions'}`}</small></div>`;
    const rack = document.createElement('div');
    rack.className = 'rack';
    vs.forEach(v => {
      v.copies.map(id => byId.get(id)).filter(Boolean).forEach(c => rack.append(card(c, short(v.title))));
      v.missing.forEach(pf => rack.append(missing(v.title, pf)));
    });
    row.append(rack);
    return row;
  }

  // the pairs of a group, in the order they came out
  const pairsIn = gr => PAIRS.filter(p => p.versions.some(v => gr.versions.includes(v)));
  const haveWant = vs => [vs.filter(v => v.copies.length).length, vs.filter(v => !v.copies.length && v.missing.length).length];

  function checklist(root, copies, grouping = {}) {
    const key = grouping.key || 'gen';
    const ways = (grouping.all || []).map(w => `<button type="button" data-grouping="${esc(w.key)}" aria-pressed="${w.key === key}"${w.key === key ? ' class="on"' : ''}>${esc(w.noun[0].toUpperCase() + w.noun.slice(1))}</button>`).join('');
    // by pair the pairs stand in one long run, the spin-offs after them; by generation or region, a section each
    const sections = key === 'pair'
      ? [{ key: 'pokemon-main-series', name: 'The main series', blurb: 'Every pair in the order it came out.', versions: mainline, pairs: PAIRS.filter(p => !p.spinoff) },
        ...(spinoffs.length ? [{ key: 'pokemon-spin-offs', name: SPINOFF, blurb: GENS.find(g => g.spinoff)?.blurb, versions: spinoffs, pairs: PAIRS.filter(p => p.spinoff), spinoff: true }] : [])]
      : groupsOf(key).map(gr => ({ ...gr, pairs: pairsIn(gr) }));

    root.innerHTML = `
      <nav class="series-nav" aria-label="Jump to"><div class="in">
        ${ways ? `<span class="grouping" role="group" aria-label="Group by">${ways}</span>` : ''}
        ${sections.map(s => `<a href="${Collection.href(s.key)}" data-section="${s.key}">${esc(s.label || s.name)} <em>${s.spinoff ? s.versions.length : s.versions.filter(v => v.copies.length).length}</em></a>`).join('')}
        <a class="want" href="${Collection.href('want-list')}" data-section="want-list">Want list <em>${wants.length}</em></a>
      </div></nav>`;
    root.querySelectorAll('[data-grouping]').forEach(b => b.addEventListener('click', () => grouping.set?.(b.dataset.grouping)));

    sections.forEach((s, i) => {
      const [have, want] = haveWant(s.versions);
      const sec = document.createElement('section');
      sec.className = 'series' + (s.spinoff ? ' spinoffs' : '');
      sec.id = s.key;
      sec.innerHTML = `<div class="shead"><div><h2>${esc(s.name)}</h2>${s.blurb ? `<p>${esc(s.blurb)}</p>` : ''}</div>${s.spinoff ? '' : meter(have, want, 'Every version')}</div>`;
      s.pairs.forEach(p => sec.append(pairRow(p, { gen: key !== 'gen', region: key === 'gen' })));
      root.append(sec);
    });

    // the want list: every version still to find, by platform, in the order the platforms came out
    const PLATFORM_ORDER = ['gb', 'gbc', 'gba', 'ds', '3ds', 'switch', 'switch2'];
    const order = [...new Set(wants.map(w => w.p))].sort((a, b) => PLATFORM_ORDER.indexOf(a) - PLATFORM_ORDER.indexOf(b));
    const [have] = haveWant(mainline);
    const sec = document.createElement('section');
    sec.className = 'series wantlist';
    sec.id = 'want-list';
    sec.innerHTML = `<div class="shead"><div><h2>The <em>want list</em></h2><p>The versions still to catch, by platform.</p></div>${meter(have, wants.length)}</div>
      <div class="packs">${order.map(p => {
        const ws = wants.filter(w => w.p === p);
        const c = CONSOLE_BY_ID[p];
        return `<div class="pgrp"><h3>${esc(c.name)} <em>${ws.length}</em></h3><div class="wants">${ws.map(({ v }) =>
          wantCard({ title: v.title, released: v.released, platform: c.name, note: v.note, pack: v.era.spinoff ? '' : v.era.short }, c.short)).join('')}</div></div>`;
      }).join('')}</div>`;
    root.append(sec);
  }

  // in a game's drawer: the other versions of its generation, the one open in bold, those still to find faded
  function drawer(g) {
    const v = versionOf.get(g.twinOf || g.id);
    if (!v || v.era.spinoff) return null;
    const gen = GENS.find(gr => gr.versions.includes(v));
    const item = x => {
      const c = x.copies.map(id => byId.get(id)).find(Boolean);
      const on = x === v;
      return c ? `<li${on ? ' class="on" aria-current="true"' : ''}><button type="button" data-go="${esc(c.id)}">${esc(short(x.title))}</button><small>${esc(PLATFORM_OF(c).short)}</small></li>` : '';
    };
    const owned = gen.versions.filter(x => x.copies.some(id => byId.has(id)));
    const still = gen.versions.filter(x => !x.copies.some(id => byId.has(id)));
    return {
      title: `${gen.name} · ${v.region}`,
      html: `<div class="dw-packs"><div><p class="dw-sub">On the shelf <em>${owned.length}</em></p><ul>${owned.map(item).join('')}</ul></div>`
        + (still.length ? `<div class="find"><p class="dw-sub">Still to catch <em>${still.length}</em></p><ul>${still.map(x => `<li><span>${esc(short(x.title))}</span><small>${esc(x.missing.map(p => CONSOLE_BY_ID[p]?.short).join(', '))}</small></li>`).join('')}</ul></div>` : '')
        + '</div>',
    };
  }

  // a copy's group in each way of grouping; one with two versions in it would stand with the first
  const grouper = key => c => {
    const v = versionOf.get(c.twinOf || c.id);
    const gr = v && groupOfVersion(key, v);
    if (!gr) return { key: 'pokemon-other', label: 'Other', name: 'Other', order: 999 };
    return { key: gr.key, label: gr.label, name: gr.name, order: gr.order };
  };

  (window.COLLECTIONS = window.COLLECTIONS || {}).pokemon = {
    name: PAGE.nav,
    title: PAGE.title,
    icon: (window.SITE?.base || '') + 'favicon-pokemon.svg',
    about: PAGE.description,
    data: ['data/pokemon.json'],
    games(db, d) {
      data = d;
      byId = new Map(db.games.map(g => [g.id, g]));
      build();
      return [...new Set(versions.flatMap(v => v.copies))].map(id => byId.get(id)).filter(Boolean);
    },
    group: grouper('gen'),
    groupNoun: 'generation',
    groups: [
      { key: 'gen', noun: 'generation', fn: grouper('gen') },
      { key: 'pair', noun: 'pair', fn: grouper('pair') },
      { key: 'region', noun: 'region', fn: grouper('region') },
    ],
    hero,
    figures,
    checklist,
    drawer,
  };
})();

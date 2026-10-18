/* mario.js — the Super Mario collection (/mario), in the look of css/mario.css. data/mario.json lists the games
   series by series (the 2D and 3D platformers, Mario Kart, Mario Party, the RPGs, Luigi's Mansion, Donkey Kong),
   each with the copies on the shelf (ids from data/games.json), the console it first came out on, and that
   console when it is still to find there; a remake says what it remakes. The spin-offs come after, in an era
   of their own (spinoff), with only what is on the shelf. A compilation (Super Mario 3D All-Stars) is a copy of
   each game in it. The copies stand on the shelf (js/app.js) grouped by series, or by the Nintendo era their
   game came out in (groups). The checklist groups them the same way: a row for each game, its copies on a
   stretch of shelf and an outline at the real case size where it is still to find. Those are the want list
   at the bottom. */

(function () {
  const { card, meter, wantCard, esc } = Collection;
  // the page's names and texts, from Settings (window.SITE, from index.php)
  const PAGE = Object.assign({
    nav: 'Super Mario', title: 'The Super Mario Collection', eyebrow: 'Let\'s-a go!', heading: 'Super Mario', accent: 'Collection',
    description: 'Bruno\'s Super Mario collection: the platformers, Mario Kart, Mario Party, the RPGs, Luigi and Donkey Kong, and the ones still to find.',
    intro: 'Every Mario game I have, from Super Mario Bros. in 1985 to Mario Kart World, series by series. A dashed outline is a game still to find, on the console it came out on.',
  }, window.SITE?.pages?.mario);

  // Nintendo's eras, each its home console and the handheld beside it, and the consoles in the order they came out
  const ERAS = [
    { id: 'nes', name: 'NES & Game Boy', label: 'NES', consoles: ['nes', 'gb'], blurb: 'The 8-bit years, on the NES and the first Game Boy.' },
    { id: 'snes', name: 'Super Nintendo', label: 'SNES', consoles: ['snes'], blurb: 'The 16-bit years.' },
    { id: 'n64', name: 'Nintendo 64 & Game Boy Color', label: 'N64', consoles: ['n64', 'gbc'], blurb: 'Mario in 3D for the first time.' },
    { id: 'gc', name: 'GameCube & Game Boy Advance', label: 'GameCube', consoles: ['gc', 'gba'], blurb: 'Sunshine, Double Dash!! and the first Mario & Luigi.' },
    { id: 'wii', name: 'Wii & DS', label: 'Wii', consoles: ['wii', 'ds'], blurb: 'The Galaxies, and New Super Mario Bros. on both.' },
    { id: 'wiiu', name: 'Wii U & 3DS', label: 'Wii U', consoles: ['wiiu', '3ds'], blurb: '3D Land and 3D World, Maker, and Mario Kart 8.' },
    { id: 'switch', name: 'Nintendo Switch', label: 'Switch', consoles: ['switch'], blurb: 'Odyssey, Wonder, and the RPGs remade.' },
    { id: 'switch2', name: 'Nintendo Switch 2', label: 'Switch 2', consoles: ['switch2'], blurb: 'Mario Kart World and Bananza.' },
  ];
  const PLATFORM_ORDER = ERAS.flatMap(e => e.consoles);
  const eraOf = id => ERAS.find(e => e.consoles.includes(id));

  /** An outline where a game still to find would stand, at that case's real size
      (signed in, a search for it: "Look for it"). */
  function missing(title, id) {
    const p = CONSOLE_BY_ID[id];
    const s = Collection.stageH() / 190;
    const look = !!window.SITE?.admin;
    const a = document.createElement(look ? 'a' : 'div');
    a.className = 'cell missing';
    a.style.minWidth = `${Math.ceil(p.w * s)}px`; // a wide box (the N64's) widens its place on the shelf to hold it
    if (look) {
      a.href = `https://www.google.com/search?q=${encodeURIComponent(`${title} ${p.name}`)}`;
      a.target = '_blank';
      a.rel = 'noopener';
      a.setAttribute('aria-label', `${title} on ${p.name}: still to find. Look for it`);
    }
    a.innerHTML = `<span class="stage"><span class="gt"><span class="ghostbox" style="width:${(p.w * s).toFixed(1)}px;height:${(p.h * s).toFixed(1)}px"><b>${esc(p.short)}</b><i>Still to find</i></span></span></span>
      <span class="cap"><b>${esc(p.name)}</b><small>Still to find${look ? ' · Look for it ↗' : ''}</small></span>`;
    return a;
  }

  const year = t => (t.released || '').slice(0, 4);
  const owned = t => t.copies.some(id => byId.has(id));

  let data, byId, titles, mainline, wants;
  const titleOf = new Map(); // a copy's id → its game in data/mario.json (a compilation's, the first game in it)

  // the console a copy is for (Pokémon-style madeFor first: a Game Boy game on the Game Boy Color's shelf)
  const consoleOf = g => g.madeFor || g.console;

  // the series and the eras, each { key, label, name, blurb, order, spinoff, rows }, a row { t, copies, missing, released }:
  // by series a game's row has all its copies; by era it has a row in each era it is on the shelf or still to find
  // in, with the copies for that era's consoles (Donkey Kong Country on the SNES, then again on the GBA)
  let SERIES = [], HW = [];
  function build() {
    titles = data.eras.flatMap(e => e.games.map(t => ({ ...t, era: e })));
    mainline = titles.filter(t => !t.era.spinoff);
    wants = titles.flatMap(t => t.missing.map(p => ({ t, p })));
    titles.forEach((t, i) => { t.order = i; t.copies.forEach(id => titleOf.has(id) || titleOf.set(id, t)); });
    SERIES = data.eras.map((e, i) => ({ key: e.id, label: e.short || e.name, name: e.name, blurb: e.blurb, order: i, spinoff: !!e.spinoff,
      rows: titles.filter(t => t.era === e).map(t => ({ t, copies: t.copies, missing: t.missing, released: t.released })) }));
    HW = ERAS.map(e => {
      const rows = titles.map(t => {
        const copies = t.copies.filter(id => byId.has(id) && eraOf(consoleOf(byId.get(id))) === e);
        const missing = t.missing.filter(p => eraOf(p) === e);
        // where it first came out in this era: the game's own date, or the first copy here of a later one
        const released = eraOf(t.console) === e ? t.released : copies.map(id => byId.get(id).released || '9999').sort()[0];
        return { t, copies, missing, released };
      }).filter(r => r.copies.length || r.missing.length)
        .sort((a, b) => (a.released || '').localeCompare(b.released || '') || a.t.order - b.t.order);
      return { key: 'mario-era-' + e.id, label: e.label, name: e.name, blurb: e.blurb, rows };
    }).filter(e => e.rows.length).map((e, i) => ({ ...e, order: i }));
  }

  // the intro (PAGE.intro) is left out for now, to keep the header short, as on the other collections.
  // The heading's letters take the logo's colours in turn (css/mario.css), and a ? block beside it bumps now
  // and then, and a coin pops out of it
  function hero() {
    const base = window.SITE?.base || '';
    const letters = [...PAGE.heading].map(ch => /\s/.test(ch) ? ch : `<span class="ltr">${esc(ch)}</span>`).join('');
    return `
      <header class="hero">
        <div>
          <div class="eyebrow">${esc(PAGE.eyebrow)}</div>
          <h1><span class="sr-only">${esc(PAGE.heading)}</span><span aria-hidden="true">${letters}</span> <em>${esc(PAGE.accent)}</em><span class="qblock" aria-hidden="true"><i class="coin"></i><img src="${base}assets/qblock.svg" alt="" width="56" height="56"></span></h1>
        </div>
      </header>`;
  }

  // the figures in the top bar, beside the site's name (js/app.js): the platformers like the stars of a save file
  function figures(copies) {
    const platformers = titles.filter(t => t.era.id === 'mario-2d' || t.era.id === 'mario-3d');
    return [[`${platformers.filter(owned).length}/${platformers.length}`, 'Platformers', 'Stars'], [copies.length, 'Copies'],
      [SERIES.filter(s => !s.spinoff).length, 'Series'], [wants.length, 'Still to find', 'To find']];
  }

  /** A game's row: its year and name, its series or its console, what it remakes (and, by era, where it first came out
      when that was an era before), then its copies and an outline where it is still to find. */
  function titleRow({ t, copies, missing: wanted, released }, key) {
    const c = CONSOLE_BY_ID[t.console];
    const later = key === 'era' && released !== t.released;
    const tags = [
      key === 'era' && !t.era.spinoff ? `<span class="tag">${esc(t.era.short || t.era.name)}</span>` : '',
      key !== 'era' && c ? `<span class="tag">${esc(c.short)}</span>` : '',
      later && c ? `<span class="tag">First on ${esc(c.short)}, ${esc(year(t))}</span>` : '',
      t.remakes ? `<span class="tag remake">Remake of ${esc(t.remakes)}</span>` : '',
    ].join('');
    const gs = copies.map(id => byId.get(id)).filter(Boolean);
    const row = document.createElement('div');
    row.className = 'title-row';
    row.innerHTML = `<div class="tr-head"><span class="yr">${esc((released || '').slice(0, 4))}</span><h3>${esc(t.title)}</h3>${tags ? `<span class="tags">${tags}</span>` : ''}
      <small>${gs.length ? `${gs.length} on the shelf` : 'Still to find'}</small></div>`;
    const rack = document.createElement('div');
    rack.className = 'rack';
    gs.forEach(g => rack.append(card(g)));
    wanted.forEach(p => rack.append(missing(t.title, p)));
    row.append(rack);
    return row;
  }

  const has = r => r.copies.some(id => byId.has(id));
  const haveWant = rows => [rows.filter(has).length, rows.filter(r => !has(r) && r.missing.length).length];

  function checklist(root, copies, grouping = {}) {
    const key = grouping.key || 'series';
    const ways = (grouping.all || []).map(w => `<button type="button" data-grouping="${esc(w.key)}" aria-pressed="${w.key === key}"${w.key === key ? ' class="on"' : ''}>${esc(w.noun[0].toUpperCase() + w.noun.slice(1))}</button>`).join('');
    const sections = key === 'era' ? HW : SERIES;

    root.innerHTML = `
      <nav class="series-nav" aria-label="Jump to"><div class="in">
        ${ways ? `<span class="grouping" role="group" aria-label="Group by">${ways}</span>` : ''}
        ${sections.map(s => `<a href="${Collection.href(s.key)}" data-section="${s.key}">${esc(s.label)} <em>${s.rows.filter(has).length}</em></a>`).join('')}
        <a class="want" href="${Collection.href('want-list')}" data-section="want-list">Want list <em>${wants.length}</em></a>
      </div></nav>`;
    root.querySelectorAll('[data-grouping]').forEach(b => b.addEventListener('click', () => grouping.set?.(b.dataset.grouping)));

    sections.forEach(s => {
      const [have, want] = haveWant(s.rows);
      const sec = document.createElement('section');
      sec.className = 'series' + (s.spinoff ? ' spinoffs' : '');
      sec.id = s.key;
      sec.innerHTML = `<div class="shead"><div><h2>${esc(s.name)}</h2>${s.blurb ? `<p>${esc(s.blurb)}</p>` : ''}</div>${s.spinoff ? '' : meter(have, want, 'Every game')}</div>`;
      s.rows.forEach(r => sec.append(titleRow(r, key)));
      root.append(sec);
    });

    // the want list: every game still to find, by console, in the order the consoles came out
    const order = [...new Set(wants.map(w => w.p))].sort((a, b) => PLATFORM_ORDER.indexOf(a) - PLATFORM_ORDER.indexOf(b));
    const have = mainline.filter(owned).length;
    const sec = document.createElement('section');
    sec.className = 'series wantlist';
    sec.id = 'want-list';
    sec.innerHTML = `<div class="shead"><div><h2>The <em>want list</em></h2><p>The games still to find, on the console each came out on.</p></div>${meter(have, wants.length)}</div>
      <div class="packs">${order.map(p => {
        const ws = wants.filter(w => w.p === p);
        const c = CONSOLE_BY_ID[p];
        return `<div class="pgrp"><h3>${esc(c.name)} <em>${ws.length}</em></h3><div class="wants">${ws.map(({ t }) =>
          wantCard({ title: t.title, released: t.released, platform: c.name, note: t.note, pack: t.era.name }, c.short)).join('')}</div></div>`;
      }).join('')}</div>`;
    root.append(sec);
  }

  // in a game's drawer: the rest of its series, the one open in bold (each game in it, for a compilation),
  // those still to find faded
  function drawer(g) {
    const id = g.twinOf || g.id;
    const t = titleOf.get(id);
    if (!t || t.era.spinoff) return null;
    const series = SERIES.find(s => s.key === t.era.id);
    const item = x => {
      const on = x.copies.includes(id);
      const c = on ? g : x.copies.map(i => byId.get(i)).find(Boolean);
      return `<li${on ? ' class="on" aria-current="true"' : ''}><button type="button" data-go="${esc(c.id)}">${esc(x.title)}</button><small>${esc(year(x))}</small></li>`;
    };
    const have = series.rows.map(r => r.t).filter(owned);
    const still = series.rows.map(r => r.t).filter(x => !owned(x));
    return {
      title: series.name,
      html: `<div class="dw-packs"><div><p class="dw-sub">On the shelf <em>${have.length}</em></p><ul>${have.map(item).join('')}</ul></div>`
        + (still.length ? `<div class="find"><p class="dw-sub">Still to find <em>${still.length}</em></p><ul>${still.map(x => `<li><span>${esc(x.title)}</span><small>${esc([year(x), ...x.missing.map(p => CONSOLE_BY_ID[p]?.short)].join(' · '))}</small></li>`).join('')}</ul></div>` : '')
        + '</div>',
    };
  }

  // a copy's group in each way of grouping: by series, its game's (a compilation's, the first game in it);
  // by era, the era of the console the copy is for
  const other = { key: 'mario-other', label: 'Other', name: 'Other', order: 999 };
  const pick = gr => gr ? { key: gr.key, label: gr.label, name: gr.name, order: gr.order } : other;
  const grouper = key => c => {
    if (key === 'era') {
      const e = eraOf(consoleOf(c));
      return pick(e && HW.find(x => x.key === 'mario-era-' + e.id));
    }
    const t = titleOf.get(c.twinOf || c.id);
    return pick(t && SERIES.find(x => x.key === t.era.id));
  };

  (window.COLLECTIONS = window.COLLECTIONS || {}).mario = {
    name: PAGE.nav,
    title: PAGE.title,
    icon: (window.SITE?.base || '') + 'favicon-mario.svg',
    about: PAGE.description,
    data: ['data/mario.json'],
    games(db, d) {
      data = d;
      byId = new Map(db.games.map(g => [g.id, g]));
      build();
      return [...new Set(titles.flatMap(t => t.copies))].map(id => byId.get(id)).filter(Boolean);
    },
    group: grouper('series'),
    groupNoun: 'series',
    groups: [
      { key: 'series', noun: 'series', fn: grouper('series') },
      { key: 'era', noun: 'era', fn: grouper('era') },
    ],
    hero,
    figures,
    checklist,
    drawer,
  };
})();

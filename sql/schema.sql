-- Bruno's Game Shelf — MySQL / MariaDB schema.
--
-- Applied by includes/db.php whenever this file changes (it keeps a hash of it
-- in settings), so every statement is CREATE ... IF NOT EXISTS. Columns added to
-- a table later go in MIGRATED_COLUMNS in includes/db.php as well, since this
-- can't add them to a table that already exists.
--
-- Every table is utf8mb4_bin: ids, keys and names compare and sort byte by
-- byte, as they did in SQLite. A query that wants case-insensitive order says
-- COLLATE utf8mb4_unicode_ci itself. Timestamps are UTC (the connection's time
-- zone is +00:00).
--
-- A `games` row is one copy on the shelf (or in a launcher's library), with
-- everything the site draws it with. What a platform says about it — play
-- time, achievements, trophies — lives apart in `platform_records`, keyed by
-- the platform's own id, and is rewritten by every sync. A sync only ever adds
-- a copy, ties one to a platform id, or fills a field that is still empty (or
-- one it filled itself and you left alone: see `field_state`); nothing you
-- typed is ever overwritten, and nothing is ever deleted.

CREATE TABLE IF NOT EXISTS users (
    id              INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email           VARCHAR(191) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT (UTC_TIMESTAMP()),
    last_login_at   DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- Small JSON blobs keyed by name: the site's titles and texts, the views, the
-- friend links, the cron token. Anything that is configuration rather than data.
CREATE TABLE IF NOT EXISTS settings (
    `key`       VARCHAR(191) NOT NULL PRIMARY KEY,
    value       MEDIUMTEXT NOT NULL,
    updated_at  DATETIME NOT NULL DEFAULT (UTC_TIMESTAMP())
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- One copy. The id is the one the site has always used ("ps5-avatar-frontiers-of-pandora"),
-- so links to #ps5-avatar-frontiers-of-pandora keep working.
--
-- The columns are the fields the admin searches, filters and sorts on, plus the
-- platform ids the syncs match on. Every other field the shelf draws a copy
-- with (its spine, case colour, cartridge, box size…) is in extra_json. Which
-- field lives where is one list, FIELDS in includes/fields.php.
CREATE TABLE IF NOT EXISTS games (
    id                  VARCHAR(191) NOT NULL PRIMARY KEY,
    -- where it stands in the list the site is handed: copies on a console in the
    -- order they were added, a new one after the last on its console
    position            INT NOT NULL DEFAULT 0,

    console             VARCHAR(64) NOT NULL,
    title               VARCHAR(255) NOT NULL,
    listed_as           VARCHAR(255),
    sort_as             VARCHAR(255),
    edition             VARCHAR(255),
    region              VARCHAR(64),
    released            VARCHAR(32),
    date_source         VARCHAR(64),
    publisher           VARCHAR(255),
    developer           VARCHAR(255),
    genre               VARCHAR(255),
    format              VARCHAR(64),
    launcher            VARCHAR(64),
    series              VARCHAR(255),
    pack                VARCHAR(255),
    pack_code           VARCHAR(64),
    note                TEXT,
    check_note          TEXT,

    fav                 TINYINT,
    hidden              TINYINT,
    also_digital        TINYINT,
    mac                 TINYINT,
    platinum            TINYINT,

    -- the platform ids: a plain value, or a JSON list for a copy with more than
    -- one game in it (a trilogy has each game's play history)
    steam_app_id        BIGINT,
    psn_title_id        TEXT,
    psn_trophy_set      TEXT,
    nintendo_title_id   TEXT,
    xbox_title_id       TEXT,
    ea_slug             VARCHAR(191),
    rockstar_title      VARCHAR(255),

    contains_json       MEDIUMTEXT,     -- a bundle's packs, by id
    cover_json          MEDIUMTEXT,     -- {file, ratio, source, ref, label, labelRef, back, …}
    colors_json         MEDIUMTEXT,     -- the three colours the spine is drawn in
    extra_json          MEDIUMTEXT,

    created_at          DATETIME NOT NULL DEFAULT (UTC_TIMESTAMP()),
    updated_at          DATETIME NOT NULL DEFAULT (UTC_TIMESTAMP()),

    KEY idx_games_console (console, position),
    KEY idx_games_steam (steam_app_id),
    KEY idx_games_ea (ea_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- Which of a copy's fields a sync wrote, and whether you have taken it over.
--
-- A field a sync filled in (a Steam copy's publisher, its cover) is the sync's
-- until you change it: the next sync may bring a newer value. Changing it in
-- the admin locks it (locked = 1), and from then on a sync only notes what it
-- would have written (synced_value), shown beside yours on the edit page.
CREATE TABLE IF NOT EXISTS field_state (
    game_id         VARCHAR(191) NOT NULL,
    field           VARCHAR(64) NOT NULL,       -- the field's name as the site knows it: "publisher", "cover"
    source          VARCHAR(32) NOT NULL,       -- steam | psn | nintendo | xbox | ea | rockstar
    synced_value    MEDIUMTEXT,                 -- JSON
    synced_at       DATETIME NOT NULL DEFAULT (UTC_TIMESTAMP()),
    locked          TINYINT NOT NULL DEFAULT 0,
    PRIMARY KEY (game_id, field),
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- What a platform says about one of its games, by its own id, as the site shows
-- it (the shape data/steam.json, psn.json… had). `collection` is which list it
-- is in: Steam, EA and Rockstar have "games", PSN "titles" and "trophySets",
-- Nintendo and Xbox "titles".
CREATE TABLE IF NOT EXISTS platform_records (
    platform        VARCHAR(32) NOT NULL,
    collection      VARCHAR(64) NOT NULL,
    ext_id          VARCHAR(191) NOT NULL,
    position        INT NOT NULL DEFAULT 0,
    data_json       MEDIUMTEXT NOT NULL,
    first_seen_at   DATETIME NOT NULL DEFAULT (UTC_TIMESTAMP()),
    synced_at       DATETIME NOT NULL DEFAULT (UTC_TIMESTAMP()),
    run_id          INT,
    -- set when a sync no longer finds it; the record stays, as the copy tied to it does
    missing_since   DATETIME NULL,
    PRIMARY KEY (platform, collection, ext_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- Every earlier version of a record a sync replaced, so a sync that brought in
-- rubbish (the APIs are unofficial) can be undone: see revert_sync_run().
CREATE TABLE IF NOT EXISTS platform_record_history (
    id              INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    run_id          INT NOT NULL,
    platform        VARCHAR(32) NOT NULL,
    collection      VARCHAR(64) NOT NULL,
    ext_id          VARCHAR(191) NOT NULL,
    data_json       MEDIUMTEXT,                 -- NULL: the run added it
    missing_since   DATETIME NULL,
    replaced_at     DATETIME NOT NULL DEFAULT (UTC_TIMESTAMP()),
    KEY idx_record_history_run (run_id),
    KEY idx_record_history_record (platform, collection, ext_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- The account-wide part of each platform's data: the PSN trophy level, the Xbox
-- gamerscore, Steam's totals, when it was last synced.
CREATE TABLE IF NOT EXISTS platform_accounts (
    platform        VARCHAR(32) NOT NULL PRIMARY KEY,
    data_json       MEDIUMTEXT NOT NULL,
    synced_at       DATETIME NOT NULL DEFAULT (UTC_TIMESTAMP())
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- A synced value you corrected by hand: hours played, trophies earned… `path` is
-- where in the record it is ("playtime.total"). With mode 'keep' it stays yours
-- whatever a sync says; with 'until_sync' it holds until a sync brings a value
-- other than base_json (what the platform said when you changed it).
CREATE TABLE IF NOT EXISTS platform_overrides (
    platform        VARCHAR(32) NOT NULL,
    collection      VARCHAR(64) NOT NULL,
    ext_id          VARCHAR(191) NOT NULL,
    path            VARCHAR(191) NOT NULL,
    value_json      MEDIUMTEXT,
    mode            VARCHAR(32) NOT NULL DEFAULT 'keep',    -- keep | until_sync
    base_json       MEDIUMTEXT,
    updated_at      DATETIME NOT NULL DEFAULT (UTC_TIMESTAMP()),
    PRIMARY KEY (platform, collection, ext_id, path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- The keys and sign-ins each platform needs, encrypted (includes/secrets.php).
CREATE TABLE IF NOT EXISTS credentials (
    platform        VARCHAR(32) NOT NULL,
    name            VARCHAR(64) NOT NULL,
    value_enc       MEDIUMTEXT NOT NULL,
    updated_at      DATETIME NOT NULL DEFAULT (UTC_TIMESTAMP()),
    PRIMARY KEY (platform, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- How each connection last fared, for the dashboard and Settings → Connections.
CREATE TABLE IF NOT EXISTS connections (
    platform        VARCHAR(32) NOT NULL PRIMARY KEY,
    status          VARCHAR(32) NOT NULL DEFAULT 'unset',   -- unset | ok | error
    message         TEXT,
    account         VARCHAR(255),                           -- the name signed in as, when the platform says
    checked_at      DATETIME NULL,
    connected_at    DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- One row per sync of one platform, so the admin can say what happened and when,
-- and so a run that dies halfway is visibly unfinished rather than silently lost.
CREATE TABLE IF NOT EXISTS sync_runs (
    id                  INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    platform            VARCHAR(32) NOT NULL,
    trigger_source      VARCHAR(32) NOT NULL DEFAULT 'admin',   -- admin | cron | cli
    status              VARCHAR(32) NOT NULL DEFAULT 'running', -- running | ok | error | reverted
    started_at          DATETIME NOT NULL DEFAULT (UTC_TIMESTAMP()),
    finished_at         DATETIME NULL,
    records_seen        INT NOT NULL DEFAULT 0,
    games_added         INT NOT NULL DEFAULT 0,
    games_linked        INT NOT NULL DEFAULT 0,
    games_changed       INT NOT NULL DEFAULT 0,
    records_changed     INT NOT NULL DEFAULT 0,
    unmatched           INT NOT NULL DEFAULT 0,
    api_calls           INT NOT NULL DEFAULT 0,
    message             TEXT,
    log                 MEDIUMTEXT,
    reverted_at         DATETIME NULL,
    KEY idx_sync_runs_started (started_at),
    KEY idx_sync_runs_platform (platform, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- Everything one run changed, field by field, with the value before and after
-- (JSON), so Sync History can show it and a run can be reverted.
--
-- kind: added (a new copy) | linked (a copy tied to a platform id) | filled (a
-- copy's field) | kept (a field you locked, left as it was) | record (a platform
-- record's value) | new_record | ambiguous | unmatched | missing
CREATE TABLE IF NOT EXISTS sync_changes (
    id              INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    run_id          INT NOT NULL,
    kind            VARCHAR(32) NOT NULL,
    game_id         VARCHAR(191),
    collection      VARCHAR(64),
    ext_id          VARCHAR(191),
    label           TEXT,                       -- the game's name, for the history page
    field           VARCHAR(64),
    old_json        MEDIUMTEXT,
    new_json        MEDIUMTEXT,
    note            TEXT,
    KEY idx_sync_changes_run (run_id),
    KEY idx_sync_changes_game (game_id),
    FOREIGN KEY (run_id) REFERENCES sync_runs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- What each platform answered, gzipped, for the last few runs of each.
CREATE TABLE IF NOT EXISTS sync_snapshots (
    run_id          INT NOT NULL PRIMARY KEY,
    platform        VARCHAR(32) NOT NULL,
    raw_gz          MEDIUMBLOB NOT NULL,
    bytes           INT NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT (UTC_TIMESTAMP()),
    FOREIGN KEY (run_id) REFERENCES sync_runs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- A platform's game a sync couldn't tie to one copy: none on the shelf, or more
-- than one it could be. The dashboard lists them to tie by hand, or to dismiss.
CREATE TABLE IF NOT EXISTS sync_unmatched (
    platform        VARCHAR(32) NOT NULL,
    collection      VARCHAR(64) NOT NULL,
    ext_id          VARCHAR(191) NOT NULL,
    name            TEXT,
    reason          VARCHAR(32) NOT NULL,       -- ambiguous | unmatched
    candidates_json MEDIUMTEXT,
    first_seen_at   DATETIME NOT NULL DEFAULT (UTC_TIMESTAMP()),
    last_seen_at    DATETIME NOT NULL DEFAULT (UTC_TIMESTAMP()),
    dismissed       TINYINT NOT NULL DEFAULT 0,
    PRIMARY KEY (platform, collection, ext_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- What is not on the shelf yet. Only the Sims want list today (collection
-- 'sims'); the site's own wantlist will be rows here too.
CREATE TABLE IF NOT EXISTS wantlist (
    id              VARCHAR(191) NOT NULL PRIMARY KEY,
    collection      VARCHAR(64) NOT NULL DEFAULT 'sims',
    position        INT NOT NULL DEFAULT 0,
    series          VARCHAR(255),
    title           VARCHAR(255) NOT NULL,
    pack            VARCHAR(255),
    pack_code       VARCHAR(64),
    released        VARCHAR(32),
    platform        VARCHAR(64),
    note            TEXT,
    extra_json      MEDIUMTEXT,
    created_at      DATETIME NOT NULL DEFAULT (UTC_TIMESTAMP()),
    updated_at      DATETIME NOT NULL DEFAULT (UTC_TIMESTAMP())
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- The collections' checklists (named for GTA's, the first): the series era by
-- era, each game with the copies on the shelf (game ids) and the platforms
-- still to find. `collection` is whose: 'gta', 'pokemon' (generation by
-- generation, a game for each version), 'mario' (series by series).
CREATE TABLE IF NOT EXISTS gta_eras (
    id              VARCHAR(191) NOT NULL PRIMARY KEY,
    collection      VARCHAR(64) NOT NULL DEFAULT 'gta',
    position        INT NOT NULL DEFAULT 0,
    name            VARCHAR(255) NOT NULL,
    blurb           TEXT,
    extra_json      MEDIUMTEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS gta_games (
    id              INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    era_id          VARCHAR(191) NOT NULL,
    collection      VARCHAR(64) NOT NULL DEFAULT 'gta',
    position        INT NOT NULL DEFAULT 0,
    title           VARCHAR(255) NOT NULL,
    released        VARCHAR(32),
    copies_json     MEDIUMTEXT,
    missing_json    MEDIUMTEXT,
    extra_json      MEDIUMTEXT,
    FOREIGN KEY (era_id) REFERENCES gta_eras(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- Failed sign-ins by address, so the wait after five wrong passwords can't be
-- skipped by dropping the session cookie. A successful sign-in clears its row.
CREATE TABLE IF NOT EXISTS login_failures (
    ip          VARCHAR(64) NOT NULL PRIMARY KEY,
    fails       INT NOT NULL DEFAULT 0,
    last_fail   BIGINT NOT NULL DEFAULT 0                -- unix time
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- The consoles themselves: one row per machine, with what is on its label. Only
-- the admin reads it for now; nothing here is in what the site is handed. The
-- serial numbers stay out of the repo too (the repo is public), so the rows
-- live only in the database: see cron/import_hardware.php.
CREATE TABLE IF NOT EXISTS hardware (
    id              VARCHAR(191) NOT NULL PRIMARY KEY,  -- "switch-oled", "wii-white"
    position        INT NOT NULL DEFAULT 0,
    console         VARCHAR(64) NOT NULL,               -- the shelf it goes with: "switch", "ps2"
    name            VARCHAR(255) NOT NULL,              -- "Nintendo Switch OLED"
    model           VARCHAR(255),                       -- "HEG-001", "CECH-3002B"
    edition         VARCHAR(255),                       -- "Pokémon: Let's Go Edition"
    color           VARCHAR(255),
    region          VARCHAR(64),                        -- a region code, as a copy's: AUS, EUR, USA…
    video           VARCHAR(32),                        -- PAL | NTSC
    region_free     TINYINT,
    year            VARCHAR(32),                        -- when that model came out
    storage         VARCHAR(64),                        -- "32 GB"
    serial          VARCHAR(255),
    manufactured    VARCHAR(64),                        -- the date on the label
    label_text      TEXT,                               -- everything the label says, as written
    extra_json      MEDIUMTEXT,                         -- other markings: product id, FCC id, the Wii U GamePad…
    note            TEXT,
    released        VARCHAR(32),
    manufacturer    VARCHAR(255),
    specs_json      MEDIUMTEXT,
    photo_json      MEDIUMTEXT,
    created_at      DATETIME NOT NULL DEFAULT (UTC_TIMESTAMP()),
    updated_at      DATETIME NOT NULL DEFAULT (UTC_TIMESTAMP()),
    KEY idx_hardware_console (console, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

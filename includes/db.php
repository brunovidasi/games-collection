<?php

function db(): PDO
{
    static $db = null;

    if ($db !== null) {
        return $db;
    }

    $c = db_config();

    try {
        $db = new PDO(
            'mysql:host=' . $c['host'] . ';port=' . $c['port'] . ';dbname=' . $c['name'] . ';charset=utf8mb4',
            $c['user'],
            $c['pass'],
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_STATEMENT_CLASS    => [DbStatement::class],
                // an UPDATE counts the rows it matched, as SQLite did, not only the ones it changed
                (PHP_VERSION_ID >= 80400 ? Pdo\Mysql::ATTR_FOUND_ROWS : PDO::MYSQL_ATTR_FOUND_ROWS) => true,
            ]
        );
    } catch (PDOException $e) {
        http_response_code(500);
        error_log('Database connection failed: ' . $e->getMessage());
        die('The database is unavailable.');
    }

    // Every timestamp is UTC, as UTC_TIMESTAMP() writes it, whatever the server's zone.
    $db->exec("SET time_zone = '+00:00', sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

    apply_schema($db);
    run_migrations($db);

    return $db;
}

/**
 * Runs sql/schema.sql when it has changed since it last ran (its hash is kept in
 * settings), then adds any MIGRATED_COLUMNS a table is still without.
 */
function apply_schema(PDO $db): void
{
    $file = __DIR__ . '/../sql/schema.sql';
    $hash = md5_file($file);

    try {
        $stmt = $db->query("SELECT value FROM settings WHERE `key` = 'schema_hash'");
        if ($stmt->fetchColumn() === json_encode($hash)) {
            return;
        }
    } catch (PDOException) {
        // no settings table yet: a new database
    }

    // one statement at a time: a batch in one exec() reports only the first one's error
    foreach (preg_split('/;\s*$/m', file_get_contents($file)) as $sql) {
        if (trim(preg_replace('/^\s*--.*$/m', '', $sql)) !== '') {
            $db->exec($sql);
        }
    }

    foreach (MIGRATED_COLUMNS as $table => $columns) {
        $stmt = $db->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?');
        $stmt->execute([$table]);
        $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($columns as $name => $type) {
            if (!in_array($name, $existing, true)) {
                $db->exec("ALTER TABLE $table ADD COLUMN $name $type");
            }
        }
    }

    $db->prepare("
        INSERT INTO settings (`key`, value) VALUES ('schema_hash', ?)
        ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = UTC_TIMESTAMP()
    ")->execute([json_encode($hash)]);
}

/**
 * Columns added after a table was first created. CREATE TABLE IF NOT EXISTS
 * can't add them to a database that already exists, so they go here as well as
 * into schema.sql.
 */
const MIGRATED_COLUMNS = [
    'hardware' => ['released' => 'VARCHAR(32)', 'manufacturer' => 'VARCHAR(255)', 'specs_json' => 'MEDIUMTEXT', 'photo_json' => 'MEDIUMTEXT'],
    'gta_eras' => ['collection' => "VARCHAR(64) NOT NULL DEFAULT 'gta'"],
    'gta_games' => ['collection' => "VARCHAR(64) NOT NULL DEFAULT 'gta'"],
];

function run_migrations(PDO $db): void
{
    // Once: a favourite used to face out on the shelf. Now Faces out is its own field, so each
    // favourite that faced out keeps doing so (one marked faceOut either way already says).
    if (!$db->query("SELECT 1 FROM settings WHERE `key` = 'migrated_fav_face_out'")->fetch()) {
        db_transaction(function (PDO $db) {
            $db->exec("
                UPDATE games SET extra_json = JSON_SET(COALESCE(extra_json, '{}'), '$.faceOut', JSON_EXTRACT('true', '$'))
                WHERE fav = 1 AND NOT JSON_CONTAINS_PATH(COALESCE(extra_json, '{}'), 'one', '$.faceOut')
            ");
            $db->exec("INSERT INTO settings (`key`, value) VALUES ('migrated_fav_face_out', 'true')");
            $db->exec("INSERT INTO settings (`key`, value) VALUES ('data_changed_at', '\"" . gmdate('c') . "\"')
                ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = UTC_TIMESTAMP()");
        });
    }

    // Once: the Pokémon collection's checklist from seed/pokemon.json, and the copies it needs (includes/site.php)
    if (function_exists('seed_pokemon') && !$db->query("SELECT 1 FROM settings WHERE `key` = 'seeded_pokemon'")->fetch()) {
        seed_pokemon();
    }

    // Once: the Super Mario collection's checklist from seed/mario.json (includes/site.php)
    if (function_exists('seed_mario') && !$db->query("SELECT 1 FROM settings WHERE `key` = 'seeded_mario'")->fetch()) {
        seed_mario();
    }
}

/**
 * Binds true and false as 1 and 0. PDO would send false as '', which a strict
 * MySQL refuses for a number column (SQLite stored whatever it was given).
 */
class DbStatement extends PDOStatement
{
    protected function __construct()
    {
    }

    public function execute(?array $params = null): bool
    {
        if ($params !== null) {
            $params = array_map(fn ($v) => is_bool($v) ? (int) $v : $v, $params);
        }

        return parent::execute($params);
    }
}

/** A settings row, JSON-decoded and cached for the rest of the request. */
function setting(string $key, mixed $default = null): mixed
{
    $cache = &settings_cache();

    if (!array_key_exists($key, $cache)) {
        $stmt = db()->prepare('SELECT value FROM settings WHERE `key` = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        $cache[$key] = $row === false ? null : json_decode($row['value'], true);
    }

    return $cache[$key] ?? $default;
}

function set_setting(string $key, mixed $value): void
{
    db()->prepare("
        INSERT INTO settings (`key`, value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = UTC_TIMESTAMP()
    ")->execute([$key, json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);

    $cache = &settings_cache();
    $cache[$key] = $value;
}

function &settings_cache(): array
{
    static $cache = [];

    return $cache;
}

/** A JSON column as an array, or $default when it is empty or not an array. */
function json_column(?string $raw, array $default = []): array
{
    if ($raw === null || $raw === '') {
        return $default;
    }

    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : $default;
}

/** Any value as JSON for a column, and back. */
function to_json(mixed $value): ?string
{
    return $value === null ? null : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function from_json(?string $raw): mixed
{
    return $raw === null || $raw === '' ? null : json_decode($raw, true);
}

/** Runs $fn inside a transaction, or inside the one already open. */
function db_transaction(callable $fn): mixed
{
    $db = db();
    if ($db->inTransaction()) {
        return $fn($db);
    }

    $db->beginTransaction();
    try {
        $result = $fn($db);
        $db->commit();
        return $result;
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Marks the public data as changed, so the API's ETag (data_version()) moves on
 * and the "updated" date the site is handed is today's.
 */
function touch_data(): void
{
    set_setting('data_changed_at', gmdate('c'));
}

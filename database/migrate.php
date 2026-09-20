<?php
/**
 * Migration runner.
 *
 * Keeps a record of which files in database/migrations/ have been applied to
 * the connected database, and applies only the ones that have not. This is
 * what makes it possible to update a database that already holds real data,
 * instead of dropping it and importing a fresh dump.
 *
 * USAGE (from the project root)
 *
 *   php database/migrate.php              Show what is applied and what is pending.
 *   php database/migrate.php run          Apply every pending migration, in order.
 *   php database/migrate.php baseline     Record every migration as applied WITHOUT
 *                                         running it. For a database that was built
 *                                         from a full dump and so already contains
 *                                         the schema, but has no record of it.
 *
 * Add --db=NAME to any of the above to target a database other than the one in
 * config.php. That is how you point this at a restored copy of the client's
 * database without editing config.
 *
 * Nothing is changed unless you pass `run` or `baseline`. Running it bare is safe.
 *
 * Always take a backup before `run`. MySQL commits schema changes immediately, so a
 * migration that fails halfway cannot be rolled back — the runner stops at the file
 * that failed and tells you which one it was, but undoing it is a restore.
 */

require_once __DIR__ . '/../includes/config.php';

const MIGRATIONS_DIR = __DIR__ . '/migrations';
const TRACKING_TABLE = 'schema_migrations';

$command = 'status';
$database = DB_NAME;

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--db=')) {
        $database = substr($arg, 5);
        continue;
    }
    $command = $arg;
}

if (!in_array($command, ['status', 'run', 'baseline'], true)) {
    fwrite(STDERR, "Unknown command '{$command}'. Use: status | run | baseline [--db=NAME]\n");
    exit(1);
}

if ($database === '') {
    fwrite(STDERR, "--db= needs a database name.\n");
    exit(1);
}

try {
    // Built directly rather than through getDB() so --db can point this at a
    // restored copy of another database without config.php being edited.
    $db = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . $database . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    fwrite(STDERR, "Could not connect to '{$database}': " . $e->getMessage() . "\n");
    exit(1);
}

/**
 * The tracking table is created by the runner rather than by a migration of its
 * own, so that it never has to appear in the list it is keeping track of.
 */
$db->exec(
    'CREATE TABLE IF NOT EXISTS `' . TRACKING_TABLE . '` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) NOT NULL,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_filename (filename)
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
);

$onDisk = glob(MIGRATIONS_DIR . '/*.sql');
if ($onDisk === false || $onDisk === []) {
    fwrite(STDERR, "No .sql files found in " . MIGRATIONS_DIR . "\n");
    exit(1);
}
$onDisk = array_map('basename', $onDisk);
sort($onDisk, SORT_STRING); // numeric filename prefixes make this the intended order

$applied = $db->query('SELECT filename FROM `' . TRACKING_TABLE . '`')
              ->fetchAll(PDO::FETCH_COLUMN);
$pending = array_values(array_diff($onDisk, $applied));

/** Records a migration as applied. Ignores a file already recorded. */
function markApplied(PDO $db, string $file): void {
    $stmt = $db->prepare('INSERT IGNORE INTO `' . TRACKING_TABLE . '` (filename) VALUES (?)');
    $stmt->execute([$file]);
}

/**
 * Splits a migration file into individual statements.
 *
 * Comments are stripped first so that a semicolon inside one cannot split a
 * statement in two. This is deliberately simple: it is safe because none of the
 * migrations define triggers or stored procedures, whose bodies contain their own
 * semicolons. If one ever does, this needs DELIMITER handling.
 */
function splitStatements(string $sql): array {
    $sql = preg_replace('!/\*.*?\*/!s', '', $sql);
    $lines = [];
    foreach (explode("\n", $sql) as $line) {
        if (str_starts_with(ltrim($line), '--')) {
            continue;
        }
        $lines[] = $line;
    }
    $parts = explode(';', implode("\n", $lines));
    return array_values(array_filter(array_map('trim', $parts), fn($s) => $s !== ''));
}

echo "Database: " . DB_NAME . "\n";
echo str_repeat('-', 58) . "\n";

// ---------------------------------------------------------------- status
if ($command === 'status') {
    foreach ($onDisk as $file) {
        $isApplied = in_array($file, $applied, true);
        printf("  %-46s %s\n", $file, $isApplied ? 'applied' : 'PENDING');
    }
    echo str_repeat('-', 58) . "\n";
    printf("%d applied, %d pending\n", count($onDisk) - count($pending), count($pending));

    if ($pending) {
        echo "\nRun `php database/migrate.php run` to apply them.\n";
        echo "Take a backup first.\n";
    }
    exit(0);
}

// -------------------------------------------------------------- baseline
if ($command === 'baseline') {
    if (!$pending) {
        echo "Nothing to baseline — every migration is already recorded.\n";
        exit(0);
    }
    foreach ($pending as $file) {
        markApplied($db, $file);
        echo "  recorded (not run)  {$file}\n";
    }
    echo str_repeat('-', 58) . "\n";
    printf("Baselined %d migration(s).\n", count($pending));
    echo "These were marked as applied WITHOUT running. Only correct if this\n";
    echo "database already contains the schema they describe.\n";
    exit(0);
}

// ------------------------------------------------------------------- run
if (!$pending) {
    echo "Nothing to do — the database is up to date.\n";
    exit(0);
}

printf("Applying %d pending migration(s):\n\n", count($pending));

foreach ($pending as $file) {
    $path = MIGRATIONS_DIR . '/' . $file;
    $sql = file_get_contents($path);

    if ($sql === false) {
        fwrite(STDERR, "\nCould not read {$path}\n");
        exit(1);
    }

    $statements = splitStatements($sql);

    if (!$statements) {
        // A file of nothing but comments still counts as applied, so it is not
        // offered again on every future run.
        markApplied($db, $file);
        printf("  %-46s no statements\n", $file);
        continue;
    }

    try {
        foreach ($statements as $statement) {
            $db->exec($statement);
        }
    } catch (PDOException $e) {
        fwrite(STDERR, "\n" . str_repeat('!', 58) . "\n");
        fwrite(STDERR, "FAILED on {$file}\n\n");
        fwrite(STDERR, $e->getMessage() . "\n\n");
        fwrite(STDERR, "Stopped here. This file is NOT recorded as applied, so it will\n");
        fwrite(STDERR, "be retried next run — but note that any statements before the\n");
        fwrite(STDERR, "failing one in this file HAVE been committed, because MySQL\n");
        fwrite(STDERR, "commits schema changes immediately. Check the database state\n");
        fwrite(STDERR, "against this file before re-running.\n");
        exit(1);
    }

    markApplied($db, $file);
    printf("  %-46s applied\n", $file);
}

echo str_repeat('-', 58) . "\n";
printf("Done. %d migration(s) applied.\n", count($pending));

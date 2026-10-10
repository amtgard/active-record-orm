<?php

/**
 * Wall-clock timings against the MariaDB container in docker-compose.dev.yml.
 *
 * php perf/run.php [output.json] [label]
 */

use Amtgard\ActiveRecordOrm\Configuration\DataAccessPolicy\InMemoryDataAccessPolicy;
use Amtgard\ActiveRecordOrm\Configuration\DataAccessPolicy\UncachedDataAccessPolicy;
use Amtgard\ActiveRecordOrm\Configuration\Repository\DatabaseConfiguration;
use Amtgard\ActiveRecordOrm\Configuration\Repository\MysqlPdoProvider;
use Amtgard\ActiveRecordOrm\Entity\EntityMapper;
use Amtgard\ActiveRecordOrm\Entity\Policy\UncachedPolicy;
use Amtgard\ActiveRecordOrm\EntityManager;
use Amtgard\ActiveRecordOrm\Factory\TableFactory;
use Amtgard\ActiveRecordOrm\Interface\DataAccessPolicy;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Table;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/Orders.php';

$dotenvPath = dirname(__DIR__) . '/test-resources';
if (!is_file($dotenvPath . '/.env')) {
    fwrite(STDERR, "Missing {$dotenvPath}/.env\n");
    exit(1);
}
Dotenv::createImmutable($dotenvPath)->safeLoad();

$outputPath = $argv[1] ?? __DIR__ . '/results/baseline.json';
$label = $argv[2] ?? 'baseline';
$repeats = 5;

$wide = 'perf_order';
$narrow = 'perf_narrow';
$draft = 'perf_order_draft';
$pages = [
    'perf_page_customer',
    'perf_page_order',
    'perf_page_line',
    'perf_page_payment',
    'perf_page_note',
];

$provider = MysqlPdoProvider::fromConfiguration(DatabaseConfiguration::fromEnvironment());
$pdo = $provider->getPdo();
$version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
prepareSchema($pdo, $wide, $narrow, $draft, $pages);

$report = [
    'label' => $label,
    'measured_at' => gmdate('c'),
    'git' => [
        'branch' => trim((string) shell_exec('git rev-parse --abbrev-ref HEAD')),
        'commit' => trim((string) shell_exec('git rev-parse HEAD')),
    ],
    'php' => PHP_VERSION,
    'database' => [
        'version' => $version,
        'image' => trim((string) shell_exec("docker inspect amtgard-orm-db --format '{{.Config.Image}}'")),
        'container' => 'amtgard-orm-db',
        'host' => $_ENV['DB_HOST'] ?? null,
        'port' => $_ENV['DB_PORT'] ?? null,
        'name' => $_ENV['DB_NAME'] ?? null,
    ],
    'shape' => [
        'wide_rows' => 200,
        'wide_columns' => 16,
        'narrow_rows' => 200,
        'narrow_columns' => 2,
        'point_fetches' => 50,
        'page_tables' => 5,
        'page_rows_each' => 20,
        'saves' => 100,
        'repeats' => $repeats,
        'cold_sample' => 'first repeat, including schema load on clear or construct',
        'warm_median' => 'median of repeats after the first',
        'memory_policy' => 'On main, InMemoryDataAccessPolicy also caches query results, so warm repeats can skip SQL. The cache stack stops caching queries. Compare uncached to uncached for the default path.',
    ],
    'policies' => [],
];

$policies = ['uncached' => 'uncached'];
$memoryProbe = probeMemoryPolicy($provider);
if ($memoryProbe === null) {
    $policies['memory'] = 'memory';
} else {
    $report['policies']['memory'] = ['error' => $memoryProbe];
}

foreach ($policies as $policyName => $_) {
    $db = Database::fromProvider($provider);
    $policy = makePolicy($db, $policyName);
    $manager = managerFor($db, $policy);
    $wideTable = TableFactory::build($db, $policy, $wide);
    $narrowTable = TableFactory::build($db, $policy, $narrow);
    $draftTable = TableFactory::build($db, $policy, $draft);
    $mapper = EntityMapper::builder()->em($manager)->table($wideTable)->name($wide)->build();
    $repository = $manager->getRepository(\Perf\PerfOrderRepository::class);

    $report['policies'][$policyName] = [
        'narrow_find_200_next' => runSamples($repeats, function () use ($narrowTable) {
            readAll($narrowTable, 200);
        }),
        'wide_find_200_next' => runSamples($repeats, function () use ($wideTable) {
            readAll($wideTable, 200);
        }),
        'wide_find_200_entity' => runSamples($repeats, function () use ($manager, $mapper) {
            $manager->clearAll();
            readEntities($mapper, 200);
        }),
        'wide_find_200_repository' => runSamples($repeats, function () use ($manager, $repository) {
            $manager->clearAll();
            readRepository($repository, 200);
        }),
        'point_fetch_50' => runSamples($repeats, function () use ($wideTable) {
            pointFetch($wideTable, 50);
        }),
        'construct_and_read_five_tables' => runSamples($repeats, function () use ($db, $policy, $pages) {
            readPages($db, $policy, $pages, 20);
        }),
        'save_100' => runSamples($repeats, function () use ($pdo, $draft, $draftTable) {
            $pdo->exec('TRUNCATE TABLE ' . $draft);
            saveDrafts($draftTable, 100);
        }),
    ];
    fwrite(STDOUT, $policyName . " done\n");
}

$dir = dirname($outputPath);
if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
    fwrite(STDERR, "Cannot create {$dir}\n");
    exit(1);
}
file_put_contents($outputPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
fwrite(STDOUT, "Wrote {$outputPath}\n");
printSummary($report);

function makePolicy(Database $db, string $name): DataAccessPolicy
{
    if ($name === 'memory') {
        return InMemoryDataAccessPolicy::builder()->database($db)->build();
    }
    return UncachedDataAccessPolicy::builder()->database($db)->build();
}

function probeMemoryPolicy(MysqlPdoProvider $provider): ?string
{
    try {
        $db = Database::fromProvider($provider);
        $policy = InMemoryDataAccessPolicy::builder()->database($db)->build();
        TableFactory::build($db, $policy, 'perf_order');
        return null;
    } catch (Throwable $e) {
        return $e::class . ': ' . $e->getMessage();
    }
}

function managerFor(Database $db, DataAccessPolicy $policy): EntityManager
{
    $manager = EntityManager::builder()
        ->database($db)
        ->dataAccessPolicy($policy)
        ->repositoryPolicy(UncachedPolicy::builder()->build())
        ->preventShutdown(true)
        ->build();
    EntityManager::configure($manager, true);
    return $manager;
}

function readAll(Table $table, int $expected): void
{
    $table->clear();
    $table->find();
    $count = 0;
    while ($table->next()) {
        $count++;
        $table->id;
    }
    expectCount($expected, $count);
}

function readEntities(EntityMapper $mapper, int $expected): void
{
    $mapper->clear();
    $mapper->find();
    $count = 0;
    while ($mapper->next()) {
        $entity = $mapper->getEntity();
        $entity->id;
        $count++;
    }
    expectCount($expected, $count);
}

function readRepository(\Perf\PerfOrderRepository $repository, int $expected): void
{
    $repository->clear();
    $repository->find();
    $count = 0;
    while ($repository->next()) {
        $entity = $repository->getCurrent();
        $entity->id;
        $count++;
    }
    expectCount($expected, $count);
}

function pointFetch(Table $table, int $count): void
{
    for ($id = 1; $id <= $count; $id++) {
        $table->clear();
        $table->id = $id;
        $found = $table->find();
        if ($found < 1 || !$table->next()) {
            throw new RuntimeException("Point fetch missed id {$id}");
        }
        $table->customer_id;
    }
}

function readPages(Database $db, DataAccessPolicy $policy, array $tables, int $expected): void
{
    foreach ($tables as $name) {
        $table = TableFactory::build($db, $policy, $name);
        readAll($table, $expected);
    }
}

function saveDrafts(Table $table, int $count): void
{
    for ($i = 0; $i < $count; $i++) {
        $table->clear();
        $table->customer_id = 1000 + $i;
        $table->status = 'draft';
        $table->currency = 'USD';
        $table->subtotal = '10.00';
        $table->notes = 'perf draft ' . $i;
        $table->save();
    }
}

function expectCount(int $expected, int $actual): void
{
    if ($actual !== $expected) {
        throw new RuntimeException("Expected {$expected} rows, got {$actual}");
    }
}

function runSamples(int $repeats, callable $scenario): array
{
    $samples = [];
    for ($i = 0; $i < $repeats; $i++) {
        $start = hrtime(true);
        $scenario();
        $samples[] = (hrtime(true) - $start) / 1e9;
    }
    $warm = array_slice($samples, 1);
    sort($warm);
    $mid = intdiv(count($warm), 2);
    $warmMedian = count($warm) % 2 === 1
        ? $warm[$mid]
        : ($warm[$mid - 1] + $warm[$mid]) / 2;

    return [
        'cold_seconds' => $samples[0],
        'warm_median_seconds' => $warmMedian,
        'samples_seconds' => $samples,
    ];
}

function printSummary(array $report): void
{
    foreach ($report['policies'] as $name => $scenarios) {
        if (isset($scenarios['error'])) {
            fwrite(STDOUT, "{$name}: {$scenarios['error']}\n");
            continue;
        }
        fwrite(STDOUT, $name . "\n");
        foreach ($scenarios as $scenario => $timing) {
            fwrite(STDOUT, sprintf(
                "  %-32s cold %.4fs  warm median %.4fs\n",
                $scenario,
                $timing['cold_seconds'],
                $timing['warm_median_seconds']
            ));
        }
    }
}

function prepareSchema(PDO $pdo, string $wide, string $narrow, string $draft, array $pages): void
{
    $names = array_merge([$wide, $narrow, $draft], $pages);
    foreach ($names as $name) {
        $pdo->exec('DROP TABLE IF EXISTS ' . $name);
    }
    $wideDdl = wideDdl($wide);
    $pdo->exec($wideDdl);
    $pdo->exec(wideDdl($draft));
    foreach ($pages as $page) {
        $pdo->exec(wideDdl($page));
    }
    $pdo->exec(
        "CREATE TABLE {$narrow} (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            label VARCHAR(64) NULL
        )"
    );

    $columns = 'customer_id, status, currency, subtotal, tax, shipping, total, notes, ship_city, ship_region, ship_postal, placed_at, updated_at, source, external_ref';
    $pdo->beginTransaction();
    $insert = $pdo->prepare(
        "INSERT INTO {$wide} ({$columns}) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
    );
    for ($i = 1; $i <= 200; $i++) {
        $insert->execute(wideRow($i));
    }
    foreach ($pages as $page) {
        $pageInsert = $pdo->prepare(
            "INSERT INTO {$page} ({$columns}) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        for ($i = 1; $i <= 20; $i++) {
            $pageInsert->execute(wideRow($i));
        }
    }
    $narrowInsert = $pdo->prepare("INSERT INTO {$narrow} (label) VALUES (?)");
    for ($i = 1; $i <= 200; $i++) {
        $narrowInsert->execute(['row-' . $i]);
    }
    $pdo->commit();
}

function wideDdl(string $table): string
{
    return "CREATE TABLE {$table} (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NULL,
        status VARCHAR(32) NULL,
        currency VARCHAR(3) NULL,
        subtotal DECIMAL(12,2) NULL,
        tax DECIMAL(12,2) NULL,
        shipping DECIMAL(12,2) NULL,
        total DECIMAL(12,2) NULL,
        notes VARCHAR(255) NULL,
        ship_city VARCHAR(80) NULL,
        ship_region VARCHAR(80) NULL,
        ship_postal VARCHAR(20) NULL,
        placed_at DATETIME NULL,
        updated_at DATETIME NULL,
        source VARCHAR(32) NULL,
        external_ref VARCHAR(64) NULL
    )";
}

function wideRow(int $i): array
{
    return [
        5000 + ($i % 40),
        $i % 3 === 0 ? 'shipped' : 'open',
        'USD',
        number_format(10 + $i, 2, '.', ''),
        '1.25',
        '4.00',
        number_format(15.25 + $i, 2, '.', ''),
        'Order note ' . $i,
        'Austin',
        'TX',
        '78701',
        '2024-06-01 12:00:00',
        '2024-06-02 08:30:00',
        'web',
        'ref-' . $i,
    ];
}


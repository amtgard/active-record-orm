<?php

/**
 * Stream-parse an Xdebug cachegrind file and rank functions by summed line time.
 *
 * Usage: php perf/summarize_cachegrind.php /path/to/file.cachegrind [--prefix=Amtgard]
 */

if ($argc < 2) {
    fwrite(STDERR, "Usage: php perf/summarize_cachegrind.php <cachegrind> [--prefix=Substring]\n");
    exit(1);
}

$path = $argv[1];
$prefix = null;
for ($i = 2; $i < $argc; $i++) {
    if (str_starts_with($argv[$i], '--prefix=')) {
        $prefix = substr($argv[$i], 9);
    }
}

if (!is_readable($path)) {
    fwrite(STDERR, "Cannot read {$path}\n");
    exit(1);
}

/** @var array<string, int> */
$totals = [];
$currentFn = '(unknown)';
$handle = fopen($path, 'rb');
while (($line = fgets($handle)) !== false) {
    $line = rtrim($line, "\r\n");
    if (preg_match('/^fn=\(\d+\)\s+(.+)$/', $line, $m)) {
        $currentFn = $m[1];
        continue;
    }
    if (preg_match('/^(\d+)\s+(\d+)\s+(\d+)$/', $line, $m)) {
        $totals[$currentFn] = ($totals[$currentFn] ?? 0) + (int) $m[2];
    }
}
fclose($handle);

arsort($totals);
$top = array_slice($totals, 0, 40, true);
$filtered = [];
if ($prefix !== null && $prefix !== '') {
    foreach ($totals as $fn => $time) {
        if (str_contains($fn, $prefix)) {
            $filtered[$fn] = $time;
        }
    }
    arsort($filtered);
    $filtered = array_slice($filtered, 0, 30, true);
}

$report = [
    'file' => $path,
    'time_unit' => '10ns (cachegrind Time_(10ns) column, summed per function block)',
    'top_inclusive_by_function_block' => mapSeconds($top),
    'filtered' => $prefix ? mapSeconds($filtered) : null,
];

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

function mapSeconds(array $totals): array
{
    $out = [];
    foreach ($totals as $fn => $tensOfNs) {
        $out[$fn] = [
            'seconds' => round($tensOfNs / 1e8, 4),
            'percent_of_parsed_total' => null,
        ];
    }
    $sum = array_sum($totals);
    if ($sum > 0) {
        foreach ($out as $fn => &$row) {
            $row['percent_of_parsed_total'] = round(100 * ($totals[$fn] / $sum), 2);
        }
    }
    return $out;
}

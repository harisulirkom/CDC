<?php

declare(strict_types=1);

$path = $argv[1] ?? 'composer-audit.json';
if (!is_file($path)) {
    fwrite(STDOUT, "composer-audit.json not found, skip policy check".PHP_EOL);
    exit(0);
}

$json = file_get_contents($path);
$data = json_decode((string) $json, true);
if (!is_array($data)) {
    fwrite(STDOUT, "composer audit output is not valid JSON, skip policy check".PHP_EOL);
    exit(0);
}

$advisories = $data['advisories'] ?? [];
$flat = [];

if (isset($advisories[0]) && is_array($advisories[0])) {
    $flat = $advisories;
} else {
    foreach ($advisories as $rows) {
        foreach ((array) $rows as $row) {
            if (is_array($row)) {
                $flat[] = $row;
            }
        }
    }
}

$bad = 0;
foreach ($flat as $row) {
    $severity = strtolower((string) ($row['severity'] ?? ''));
    if (in_array($severity, ['high', 'critical'], true)) {
        $bad++;
    }
}

if ($bad > 0) {
    fwrite(STDERR, "Found {$bad} high/critical backend advisories".PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "No high/critical backend advisories detected (or data unavailable).".PHP_EOL);
exit(0);

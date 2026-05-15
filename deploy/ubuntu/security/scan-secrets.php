<?php

declare(strict_types=1);

$root = $argv[1] ?? getcwd();
if (!$root || !is_dir($root)) {
    fwrite(STDERR, "[FAIL] Invalid repository path".PHP_EOL);
    exit(1);
}

echo "== Backend lightweight secret scan (PHP) ==".PHP_EOL;

$excludeDirs = ['.git', 'vendor', 'node_modules', 'storage', 'bootstrap/cache'];
$excludeFiles = ['.env', '.env.example', '.env.production.example'];
$excludeExt = ['lock', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'pdf', 'zip', 'gz', 'exe', 'dll'];
$patterns = [
    '/AKIA[0-9A-Z]{16}/',
    '/-----BEGIN (RSA|EC|OPENSSH|PRIVATE) KEY-----/',
    '/(?i)(api[_-]?key|secret|token|password)\s*[:=]\s*[\'"][A-Za-z0-9_\-\/+=]{16,}[\'"]/',
];

$hits = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $fileInfo) {
    /** @var SplFileInfo $fileInfo */
    if (!$fileInfo->isFile()) {
        continue;
    }

    $path = $fileInfo->getPathname();
    $relative = ltrim(str_replace(str_replace('\\', '/', $root), '', str_replace('\\', '/', $path)), '/');

    $skip = false;
    foreach ($excludeDirs as $dir) {
        if (str_starts_with($relative, trim($dir, '/').'/')) {
            $skip = true;
            break;
        }
    }
    if ($skip) {
        continue;
    }

    $filename = $fileInfo->getFilename();
    if (in_array($filename, $excludeFiles, true)) {
        continue;
    }

    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if ($ext && in_array($ext, $excludeExt, true)) {
        continue;
    }

    $content = @file_get_contents($path);
    if ($content === false || $content === '') {
        continue;
    }

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $content, $m, PREG_OFFSET_CAPTURE)) {
            $offset = $m[0][1] ?? 0;
            $line = substr_count(substr($content, 0, (int) $offset), "\n") + 1;
            $hits[] = [$relative, $line, $m[0][0]];
            break;
        }
    }
}

if ($hits) {
    echo "[FAIL] Potential hardcoded secret patterns found:".PHP_EOL;
    foreach ($hits as [$file, $line, $match]) {
        $sample = mb_substr((string) $match, 0, 80);
        echo "- {$file}:{$line} => {$sample}".PHP_EOL;
    }
    exit(2);
}

echo "[PASS] No hardcoded secret patterns found in lightweight scan.".PHP_EOL;
exit(0);

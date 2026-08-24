<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$root = dirname(__DIR__);
$copies = [
    $root . '/config.local.example.php' => $root . '/config.local.php',
    $root . '/filing.example.php' => $root . '/storage/filing.php',
];

foreach ([$root . '/out/logs', $root . '/storage/filing-backups'] as $directory) {
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        fwrite(STDERR, "Could not create {$directory}\n");
        exit(1);
    }
}

foreach ($copies as $source => $destination) {
    if (is_file($destination)) {
        echo basename($destination) . " already exists; left unchanged.\n";
        continue;
    }
    if (!copy($source, $destination)) {
        fwrite(STDERR, "Could not create {$destination}\n");
        exit(1);
    }
    echo 'Created ' . str_replace($root . '/', '', $destination) . "\n";
}

$transactionFile = $root . '/storage/transaction_id.txt';
if (!is_file($transactionFile)) {
    if (file_put_contents($transactionFile, 'ABC323456789DEF0', LOCK_EX) === false) {
        fwrite(STDERR, "Could not create storage/transaction_id.txt\n");
        exit(1);
    }
    echo "Created storage/transaction_id.txt at ABC323456789DEF0\n";
} else {
    echo "storage/transaction_id.txt already exists; left unchanged.\n";
}

echo "\nNext: edit config.local.php and storage/filing.php, then run php tools/preflight.php\n";

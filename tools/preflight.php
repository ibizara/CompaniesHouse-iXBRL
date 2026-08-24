<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$root = dirname(__DIR__);
$errors = [];
$warnings = [];
$passes = [];

function addResult(array &$bucket, string $message): void
{
    $bucket[] = $message;
}

function flattenKeys(array $data, string $prefix = ''): array
{
    $result = [];
    foreach ($data as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
        if (is_array($value)) {
            $result += flattenKeys($value, $path);
        } else {
            $result[$path] = $value;
        }
    }
    return $result;
}

try {
    $cfg = require $root . '/config.php';
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] " . $e->getMessage() . "\n");
    exit(1);
}

$required = [
    'gateway_url',
    'presenter.sender_id',
    'presenter.auth_method',
    'presenter.auth_value',
    'presenter.email',
    'company.number',
    'company.type',
    'company.name',
    'company.auth_code',
    'form.form_identifier',
    'form.package_reference',
    'form.submission_number',
    'form.customer_reference',
    'form.contact_name',
    'form.contact_number',
    'form.language',
    'form.document_filename',
    'form.document_category',
    'form.document_content_type',
    'form.date_signed',
    'form.date_document',
];

$flat = flattenKeys($cfg);
foreach ($required as $path) {
    if (!array_key_exists($path, $flat) || trim((string) $flat[$path]) === '') {
        addResult($errors, "Missing required configuration value: {$path}");
    }
}

$placeholderPattern = '/(?:REPLACE|YOUR COMPANY|YOUR NAME|YOUR TELEPHONE|DIRECTOR NAME|00000000)/i';
foreach ($flat as $path => $value) {
    if (is_scalar($value) && preg_match($placeholderPattern, (string) $value)) {
        addResult($errors, "Example placeholder remains in {$path}");
    }
}

$templatePath = (string) ($cfg['paths']['template'] ?? '');
if (!is_file($templatePath)) {
    addResult($errors, 'template.xhtml was not found.');
} else {
    $template = file_get_contents($templatePath);
    if ($template === false) {
        addResult($errors, 'template.xhtml could not be read.');
    } else {
        preg_match_all('/\{\{([A-Za-z0-9_]+)\}\}/', $template, $matches);
        $placeholders = array_values(array_unique($matches[1] ?? []));
        $missing = array_values(array_diff($placeholders, array_keys($cfg['ixbrl_vars'] ?? [])));
        $unused = array_values(array_diff(array_keys($cfg['ixbrl_vars'] ?? []), $placeholders));

        if ($missing !== []) {
            addResult($errors, 'Template values missing from configuration: ' . implode(', ', $missing));
        } else {
            addResult($passes, count($placeholders) . ' template placeholders all have configured values.');
        }
        if ($unused !== []) {
            addResult($warnings, 'Configured iXBRL values not referenced by the template: ' . implode(', ', $unused));
        }
    }
}

$submission = (string) ($cfg['form']['submission_number'] ?? '');
if (!preg_match('/^AC\d+$/', $submission)) {
    addResult($errors, 'Submission number must be AC followed by digits.');
}
$customerReference = (string) ($cfg['form']['customer_reference'] ?? '');
if (!preg_match('/^IXBRL\d+$/', $customerReference)) {
    addResult($errors, 'Customer reference must be IXBRL followed by digits.');
}

$transactionPath = (string) ($cfg['paths']['transaction_file'] ?? '');
if (is_file($transactionPath)) {
    $transaction = strtoupper(trim((string) file_get_contents($transactionPath)));
    if (!preg_match('/^[0-9A-F]+$/', $transaction)) {
        addResult($errors, 'storage/transaction_id.txt is not hexadecimal.');
    } else {
        addResult($passes, 'Transaction ID format is valid.');
    }
} else {
    addResult($warnings, 'storage/transaction_id.txt does not exist yet; it will start at ABC323456789DEF0.');
}

foreach ([
    dirname((string) $cfg['paths']['output_xhtml']),
    dirname((string) $cfg['paths']['log']),
    dirname((string) $cfg['paths']['transaction_file']),
    (string) $cfg['paths']['filing_backups'],
] as $directory) {
    if (!is_dir($directory)) {
        addResult($errors, "Required directory does not exist: {$directory}");
    } elseif (!is_writable($directory)) {
        addResult($errors, "PHP must be able to write to: {$directory}");
    }
}

$filingPath = (string) ($cfg['paths']['filing'] ?? '');
if (!is_file($filingPath)) {
    addResult($errors, 'storage/filing.php was not found.');
} elseif (!is_writable($filingPath)) {
    addResult($warnings, 'storage/filing.php is not writable; Prepare next filing will not be able to update it.');
}

if (($cfg['gateway_test'] ?? true) === false) {
    addResult($warnings, 'gateway_test is false: submissions will be sent to the LIVE gateway.');
} else {
    addResult($passes, 'Gateway test mode is enabled.');
}

if (!extension_loaded('curl')) {
    addResult($errors, 'PHP cURL extension is required.');
}
if (!extension_loaded('dom')) {
    addResult($warnings, 'PHP DOM extension is recommended for XML well-formedness checks.');
}

foreach ($passes as $message) {
    echo "[PASS] {$message}\n";
}
foreach ($warnings as $message) {
    echo "[WARN] {$message}\n";
}
foreach ($errors as $message) {
    echo "[FAIL] {$message}\n";
}

echo "\n";
if ($errors !== []) {
    echo count($errors) . " failure(s); do not submit.\n";
    exit(1);
}

echo "Preflight completed without failures. Review every filing value and the generated iXBRL before submitting.\n";

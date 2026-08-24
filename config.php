<?php

declare(strict_types=1);

/**
 * Application configuration.
 *
 * Personal credentials and company details belong in config.local.php, which
 * is intentionally ignored by Git. Annual filing values belong in
 * storage/filing.php and are managed by prepare.php after the first filing.
 */

$localPath = __DIR__ . '/config.local.php';
$filingPath = __DIR__ . '/storage/filing.php';

if (!is_file($localPath)) {
    throw new RuntimeException(
        'config.local.php is missing. Copy config.local.example.php to config.local.php and enter your details.'
    );
}
if (!is_file($filingPath)) {
    throw new RuntimeException(
        'storage/filing.php is missing. Copy filing.example.php to storage/filing.php and enter the filing values.'
    );
}

$local = require $localPath;
$filing = require $filingPath;

if (!is_array($local)) {
    throw new RuntimeException('config.local.php must return an array.');
}
if (!is_array($filing)) {
    throw new RuntimeException('storage/filing.php must return an array.');
}

$config = [
    // Input XML Gateway endpoint. Keep gateway_test=true until testing is complete.
    'gateway_url' => 'https://xmlgw.companieshouse.gov.uk/v1-0/xmlgw/Gateway',
    'gateway_test' => true,

    'presenter' => [],
    'company' => [],

    // Stable submission metadata. Annual values are merged from storage/filing.php.
    'form' => [
        'form_identifier' => 'Accounts',
        'language' => 'EN',
        'document_filename' => 'micro-accounts.html',
        'document_category' => 'ACCOUNTS',
        'document_content_type' => 'application/xml',
    ],

    'paths' => [
        'template' => __DIR__ . '/template.xhtml',
        'output_xhtml' => __DIR__ . '/out/output.xhtml',
        'transaction_file' => __DIR__ . '/storage/transaction_id.txt',
        'last_envelope' => __DIR__ . '/out/last-envelope.xml',
        'log' => __DIR__ . '/out/logs/gateway.log',
        'filing' => $filingPath,
        'local_config' => $localPath,
        'filing_backups' => __DIR__ . '/storage/filing-backups',
    ],

    // Stable variables used by template.xhtml. Company-specific values are
    // merged from config.local.php; annual values are merged from filing.php.
    'ixbrl_vars' => [
        'xmlns' => 'xmlns="http://www.w3.org/1999/xhtml" xmlns:iso4217="http://www.xbrl.org/2003/iso4217" xmlns:ix="http://www.xbrl.org/2013/inlineXBRL" xmlns:ixt2="http://www.xbrl.org/inlineXBRL/transformation/2011-07-31" xmlns:link="http://www.xbrl.org/2003/linkbase" xmlns:uk-bus="http://xbrl.frc.org.uk/cd/2023-01-01/business" xmlns:uk-core="http://xbrl.frc.org.uk/fr/2023-01-01/core" xmlns:uk-direp="http://xbrl.frc.org.uk/reports/2023-01-01/direp" xmlns:xbrldi="http://xbrl.org/2006/xbrldi" xmlns:xbrli="http://www.xbrl.org/2003/instance" xmlns:xlink="http://www.w3.org/1999/xlink"',
        'AccountsType' => 'Micro-entity',
        'EntityDormantTruefalse' => 'false',
        'schemaRef' => 'https://xbrl.frc.org.uk/FRS-102/2023-01-01/FRS-102-2023-01-01.xsd',
        'AccountingStandardsDimension' => 'uk-bus:Micro-entities',
        'AccountsTypeDimension' => 'uk-bus:FullAccounts',
        'AccountsStatusDimension' => 'uk-bus:AuditExempt-NoAccountantsReport',
        'unit' => 'GBP',
        'GBP' => 'iso4217:GBP',
        'CurrencySymbol' => '£',
    ],
];

$config['gateway_url'] = (string) ($local['gateway_url'] ?? $config['gateway_url']);
$config['gateway_test'] = (bool) ($local['gateway_test'] ?? $config['gateway_test']);
$config['presenter'] = array_replace($config['presenter'], $local['presenter'] ?? []);
$config['company'] = array_replace($config['company'], $local['company'] ?? []);
$config['form'] = array_replace($config['form'], $local['form'] ?? [], $filing['form'] ?? []);
$config['ixbrl_vars'] = array_replace(
    $config['ixbrl_vars'],
    $local['ixbrl_vars'] ?? [],
    $filing['ixbrl_vars'] ?? []
);
$config['filing_checks'] = $filing['checks'] ?? [];

return $config;

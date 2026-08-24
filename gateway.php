<?php
// Usage (CLI):
// php gateway.php build-ixbrl
// php gateway.php submit
// php gateway.php status
// php gateway.php ack
//
// This script is CLI-only. Use admin.php for browser-based actions.

require __DIR__ . '/src/Util.php';
require __DIR__ . '/src/TxStore.php';
require __DIR__ . '/src/IxbrlRenderer.php';
require __DIR__ . '/src/Envelope.php';
require __DIR__ . '/src/GovTalkClient.php';
require __DIR__ . '/src/ConfigValidator.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('gateway.php is CLI-only. Use admin.php.');
}

$cfg = require __DIR__ . '/config.php';

$action = $argv[1] ?? '';
if ($action === '') {
    fwrite(STDERR, "Usage: php gateway.php build-ixbrl|submit|status|ack\n");
    exit(2);
}

function echohr($title) { echo "\n==== $title ====" . PHP_EOL; }

try {
    $paths = $cfg['paths'];
    Util::ensureDir(dirname($paths['output_xhtml']));
    Util::ensureDir(dirname($paths['last_envelope']));
    Util::ensureDir(dirname($paths['log']));

    $tx = new TxStore($paths['transaction_file']);
    $client = new GovTalkClient($cfg['gateway_url'], $paths['log']);

    if ($action === 'build-ixbrl') {
        $renderer = new IxbrlRenderer($paths['template'], $paths['output_xhtml']);
        $content = $renderer->render($cfg['ixbrl_vars']);
        echohr('Rendered iXBRL saved to');
        echo $paths['output_xhtml'] . PHP_EOL;
        exit(0);
    }

    if ($action === 'submit') {
        $configErrors = ConfigValidator::errors($cfg);
        if ($configErrors !== []) {
            throw new RuntimeException('Configuration is incomplete: ' . implode(' ', $configErrors));
        }
        // 1) Build iXBRL
        $renderer = new IxbrlRenderer($paths['template'], $paths['output_xhtml']);
        $ix = $renderer->render($cfg['ixbrl_vars']);
        // 2) Base64
        $b64 = Util::base64($ix);
        // 3) TransactionID
        $tid = $tx->next();
        // 4) Build envelope
        $envelope = Envelope::accounts($cfg, $tid, $b64);
        file_put_contents($paths['last_envelope'], $envelope);
        // 5) POST
        $res = $client->post($envelope);
        echohr('TransactionID'); echo $tid . PHP_EOL;
        echohr('HTTP'); echo ($res['http'] ?? '?') . PHP_EOL;
        echohr('Body'); echo htmlspecialchars($res['body']);
        // Save a copy stamped
        $stamp = date('Ymd-His');
        file_put_contents(__DIR__ . "/out/response-submit-$stamp.xml", $res['body']);
        exit($res['ok'] ? 0 : 1);
    }

    if ($action === 'status') {
        $tid = $tx->next();
        $envelope = Envelope::status($cfg, $tid);
        file_put_contents($paths['last_envelope'], $envelope);
        $res = $client->post($envelope);
        echohr('TransactionID'); echo $tid . PHP_EOL;
        echohr('HTTP'); echo ($res['http'] ?? '?') . PHP_EOL;
        echohr('Body'); echo htmlspecialchars($res['body']);
        $stamp = date('Ymd-His');
        file_put_contents(__DIR__ . "/out/response-status-$stamp.xml", $res['body']);
        exit($res['ok'] ? 0 : 1);
    }

    if ($action === 'ack') {
        $tid = $tx->next();
        $envelope = Envelope::ack($cfg, $tid);
        file_put_contents($paths['last_envelope'], $envelope);
        $res = $client->post($envelope);
        echohr('TransactionID'); echo $tid . PHP_EOL;
        echohr('HTTP'); echo ($res['http'] ?? '?') . PHP_EOL;
        echohr('Body'); echo htmlspecialchars($res['body']);
        $stamp = date('Ymd-His');
        file_put_contents(__DIR__ . "/out/response-ack-$stamp.xml", $res['body']);
        exit($res['ok'] ? 0 : 1);
    }

    throw new InvalidArgumentException('Unknown action: ' . $action);

} catch (Throwable $e) {
    Util::log($cfg['paths']['log'], 'ERROR: ' . $e->getMessage());
    http_response_code(500);
    echo 'Error: ' . htmlspecialchars($e->getMessage());
    exit(1);
}
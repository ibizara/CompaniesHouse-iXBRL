<?php
session_start();

// Web administration interface. Protect this entire directory at web-server level.

require __DIR__ . '/src/Util.php';
require __DIR__ . '/src/TxStore.php';
require __DIR__ . '/src/IxbrlRenderer.php';
require __DIR__ . '/src/Envelope.php';
require __DIR__ . '/src/GovTalkClient.php';
require __DIR__ . '/src/FilingManager.php';
require __DIR__ . '/src/ConfigValidator.php';
$cfg = require __DIR__ . '/config.php';

$paths = $cfg['paths'];
Util::ensureDir(dirname($paths['output_xhtml']));
Util::ensureDir(dirname($paths['last_envelope']));
Util::ensureDir(dirname($paths['log']));

$action = $_POST['action'] ?? '';
$result = ['ok' => null, 'http' => null, 'body' => null, 'tx' => null, 'error' => null];

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(24));
}
$csrfValid = $_SERVER['REQUEST_METHOD'] !== 'POST'
    || hash_equals((string) $_SESSION['admin_csrf'], (string) ($_POST['csrf'] ?? ''));

$flash = $_SESSION['filing_flash'] ?? null;
unset($_SESSION['filing_flash']);

function submission_state(array $cfg, array $paths): array {
    $checks = $cfg['filing_checks'] ?? [];
    $currentYear = (string)($cfg['ixbrl_vars']['currentYear'] ?? '');
    $schemaRef = (string)($cfg['ixbrl_vars']['schemaRef'] ?? '');
    $accepted = FilingManager::acceptedStatus(__DIR__ . '/out', (string)$cfg['form']['submission_number']);
    $configErrors = ConfigValidator::errors($cfg);
    $checksComplete = FilingManager::checksComplete($checks, $currentYear, $schemaRef);

    $sourceTimes = [];
    foreach ([$paths['filing'] ?? null, $paths['local_config'] ?? null, $paths['template'] ?? null, __DIR__ . '/config.php'] as $source) {
        if ($source && is_file($source)) {
            $sourceTimes[] = filemtime($source) ?: 0;
        }
    }
    $latestSource = $sourceTimes ? max($sourceTimes) : 0;
    $outputCurrent = is_file($paths['output_xhtml']) && (filemtime($paths['output_xhtml']) ?: 0) >= $latestSource;

    return [
        'accepted' => $accepted,
        'checks_complete' => $checksComplete,
        'output_current' => $outputCurrent,
        'config_errors' => $configErrors,
        'submit_ready' => $checksComplete && $outputCurrent && $accepted === null && $configErrors === [],
    ];
}

$submissionState = submission_state($cfg, $paths);

function latest_response(string $dir, string $prefix): ?string {
    $files = glob($dir . '/' . $prefix . '-*.xml');
    if (!$files) return null;
    usort($files, fn($a,$b)=>strcmp($b,$a)); // newest first
    return $files[0];
}

try {
    if (!$csrfValid) {
        throw new RuntimeException('The form session expired. Reload the page and try again.');
    }

    $client = new GovTalkClient($cfg['gateway_url'], $paths['log']);
    $tx = new TxStore($paths['transaction_file']);

    if ($action === 'build-ixbrl') {
        $renderer = new IxbrlRenderer($paths['template'], $paths['output_xhtml']);
        $renderer->render($cfg['ixbrl_vars']);
        $result['ok'] = true;
    }
    if ($action === 'submit') {
        $submissionState = submission_state($cfg, $paths);
        if ($submissionState['config_errors'] !== []) {
            throw new RuntimeException('Configuration is incomplete: ' . implode(' ', $submissionState['config_errors']));
        }
        if ($submissionState['accepted'] !== null) {
            throw new RuntimeException('This submission already has an ACCEPT status. Prepare the next filing instead of submitting it again.');
        }
        if (!$submissionState['checks_complete']) {
            throw new RuntimeException('The annual filing confirmations are incomplete. Use Prepare next filing first.');
        }
        if (!$submissionState['output_current']) {
            throw new RuntimeException('Build and review the iXBRL after the latest filing changes before submitting.');
        }
        $renderer = new IxbrlRenderer($paths['template'], $paths['output_xhtml']);
        $ix = $renderer->render($cfg['ixbrl_vars']);
        $b64 = Util::base64($ix);
        $tid = $tx->next();
        $env = Envelope::accounts($cfg, $tid, $b64);
        file_put_contents($paths['last_envelope'], $env);
        $res = $client->post($env);
        $result = ['ok'=>$res['ok'],'http'=>$res['http']??null,'body'=>$res['body']??null,'tx'=>$tid,'error'=>$res['error']??null];
        $stamp = date('Ymd-His');
        file_put_contents(__DIR__ . "/out/response-submit-$stamp.xml", $res['body']??'');
    }
    if ($action === 'status') {
        $tid = $tx->next();
        $env = Envelope::status($cfg, $tid);
        file_put_contents($paths['last_envelope'], $env);
        $res = $client->post($env);
        $result = ['ok'=>$res['ok'],'http'=>$res['http']??null,'body'=>$res['body']??null,'tx'=>$tid,'error'=>$res['error']??null];
        $stamp = date('Ymd-His');
        file_put_contents(__DIR__ . "/out/response-status-$stamp.xml", $res['body']??'');
    }
    if ($action === 'ack') {
        $tid = $tx->next();
        $env = Envelope::ack($cfg, $tid);
        file_put_contents($paths['last_envelope'], $env);
        $res = $client->post($env);
        $result = ['ok'=>$res['ok'],'http'=>$res['http']??null,'body'=>$res['body']??null,'tx'=>$tid,'error'=>$res['error']??null];
        $stamp = date('Ymd-His');
        file_put_contents(__DIR__ . "/out/response-ack-$stamp.xml", $res['body']??'');
    }
} catch (Throwable $e) {
    $result = ['ok'=>false,'error'=>$e->getMessage()];
}

$latestSubmit = latest_response(__DIR__ . '/out', 'response-submit');
$latestStatus = latest_response(__DIR__ . '/out', 'response-status');
$latestAck    = latest_response(__DIR__ . '/out', 'response-ack');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
$log_tail = file_exists($paths['log']) ? h(join("
", array_slice(file($paths['log']), -200))) : '';
$last_envelope = file_exists($paths['last_envelope']) ? h(file_get_contents($paths['last_envelope'])) : '';
$rendered_ixbrl = file_exists($paths['output_xhtml']);
$submissionState = submission_state($cfg, $paths);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>CH iXBRL Admin</title>
<style>
:root { --bg:#0b0e14; --card:#11161e; --ink:#e8ecf1; --mut:#aab3be; --acc:#3ea6ff; --ok:#16a34a; --err:#ef4444; }
* { box-sizing: border-box; }
body { margin:0; font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial; background:var(--bg); color:var(--ink); }
.header { display:flex; gap:1rem; align-items:center; padding:1rem 1.25rem; border-bottom:1px solid #1f2733; }
.header h1 { font-size:1.1rem; margin:0; opacity:.9; }
.container { display:grid; grid-template-columns: 320px 1fr; gap:1rem; padding:1rem; }
.card { background:var(--card); border:1px solid #1f2733; border-radius:12px; padding:1rem; }
.btns form { display:flex; flex-direction:column; gap:.5rem; }
.btns > .button { margin-bottom:.5rem; }
.button, button { display:block; width:100%; text-align:center; text-decoration:none; appearance:none; border:1px solid #1f2733; background:#192233; color:var(--ink); padding:.6rem .8rem; border-radius:10px; cursor:pointer; font-weight:600; }
.button:hover, button:hover { border-color:#2a3648; }
button:disabled { opacity:.45; cursor:not-allowed; }
.button.primary, button.primary { background:#1b2b44; border-color:#2c456a; }
.kv { display:grid; grid-template-columns: 140px 1fr; gap:.25rem .75rem; font-size:.9rem; }
.kv div { color:var(--mut); }
pre { white-space:pre-wrap; word-wrap:break-word; background:#0f1420; border:1px solid #1f2733; padding:.75rem; border-radius:10px; max-height:300px; overflow:auto; }
.grid { display:grid; grid-template-columns: 1fr; gap:1rem; }
@media (min-width: 1100px){ .grid { grid-template-columns: 1fr 1fr; } }
.status { font-weight:700; }
.status.ok { color: var(--ok); }
.status.err { color: var(--err); }
.notice { padding:.75rem; border-radius:10px; background:#0f1420; border:1px solid #1f2733; margin:.75rem 0; }
.notice.warn { border-color:#7c5a16; }
.notice.ok { border-color:#166534; }
iframe { width:100%; height:420px; background:white; border-radius:10px; border:1px solid #1f2733; }
.small { font-size:.85rem; color:var(--mut); }
.a { color: var(--acc); text-decoration: none; }
.a:hover { text-decoration: underline; }
</style>
</head>
<body>
  <div class="header">
    <h1>Companies House iXBRL — Admin</h1>
  </div>
  <?php if ($flash): ?>
    <div style="padding:1rem 1rem 0;"><div class="notice ok"><?=h($flash)?></div></div>
  <?php endif; ?>
  <div class="container">
    <div class="card btns">
      <h3>Actions</h3>
      <a class="button primary" href="prepare.php">Prepare next filing</a>
      <form method="post">
        <input type="hidden" name="csrf" value="<?=h($_SESSION['admin_csrf'])?>" />
        <button class="primary" name="action" value="build-ixbrl">Build iXBRL</button>
        <button name="action" value="submit" <?= $submissionState['submit_ready'] ? '' : 'disabled' ?> onclick="return confirm('Submit this filing to the configured Companies House gateway?')">Submit</button>
        <button name="action" value="status">Get Status</button>
        <button name="action" value="ack">Send Status Ack</button>
      </form>
      <?php if ($submissionState['config_errors'] !== []): ?>
        <div class="notice warn"><strong>Configuration incomplete:</strong><ul><?php foreach ($submissionState['config_errors'] as $configError): ?><li><?=h($configError)?></li><?php endforeach; ?></ul></div>
      <?php elseif ($submissionState['accepted']): ?>
        <div class="notice ok"><strong>Accepted:</strong> <?=h($cfg['form']['submission_number'])?>. Submission is disabled to prevent a duplicate filing.</div>
      <?php elseif (!$submissionState['checks_complete']): ?>
        <div class="notice warn"><strong>Checks incomplete:</strong> use Prepare next filing before submission.</div>
      <?php elseif (!$submissionState['output_current']): ?>
        <div class="notice warn"><strong>Preview out of date:</strong> click Build iXBRL and review the result.</div>
      <?php else: ?>
        <div class="notice ok"><strong>Ready:</strong> checks complete and the preview matches the current filing values.</div>
      <?php endif; ?>
	  <hr style="border-color:#1f2733; margin:1rem 0;" />
	  <h3>MD5 Helper</h3>
	  <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?=h($_SESSION['admin_csrf'])?>" />
		<div class="kv">
		  <div>Presenter ID</div>
		  <div><input type="text" name="pid" value="<?=h($_POST['pid'] ?? '')?>" style="width:100%"></div>
		  <div>Presenter Code</div>
		  <div><input type="password" name="pcode" value="" autocomplete="new-password" style="width:100%"></div>
		</div>
		<button class="primary" name="action" value="hash">Generate MD5</button>
	  </form>
	  <?php if ($action === 'hash' && !empty($_POST['pid']) && !empty($_POST['pcode'])): ?>
		<?php
		  $pid = $_POST['pid'];
		  $pcode = $_POST['pcode'];
		  $senderHash = strtolower(md5($pid));
		  $authHash   = strtolower(md5($pcode));
		?>
<pre><?php
echo "SenderID (MD5, lowercase): " . h($senderHash) . "\n";
echo "Authentication Value (MD5, lowercase): " . h($authHash) . "\n";
?></pre>
	  <?php endif; ?>
      <hr style="border-color:#1f2733; margin:1rem 0;" />
      <div class="kv">
        <div>Gateway URL</div><div><?=h($cfg['gateway_url'])?></div>
        <div>GatewayTest</div><div><?= $cfg['gateway_test'] ? '1 (TEST)' : '0 (LIVE)' ?></div>
        <div>Company</div><div><?=h($cfg['company']['number'].' — '.$cfg['company']['name'])?></div>
        <div>Submission #</div><div><?=h($cfg['form']['submission_number'])?></div>
        <div>Accounts year</div><div><?=h($cfg['ixbrl_vars']['currentYear'])?></div>
        <div>Taxonomy checked</div><div><?=h($cfg['filing_checks']['taxonomy_verified_on'] ?? 'Not verified')?></div>
        <div>Preview current</div><div><?= $submissionState['output_current'] ? 'Yes' : 'No — rebuild required' ?></div>
      </div>
    </div>

    <div class="grid">
      <div class="card">
        <h3>Result</h3>
        <?php if ($action): ?>
        <div class="kv">
          <div>Action</div><div><?=h($action)?></div>
          <div>TxID</div><div><?=h($result['tx'] ?? '')?></div>
          <div>HTTP</div><div><?=h($result['http'] ?? '')?></div>
          <div>Status</div><div class="status <?=($result['ok']===true?'ok':($result['ok']===false?'err':''))?>"><?=(is_null($result['ok'])?'–':($result['ok']?'OK':'ERROR'))?></div>
        </div>
        <?php endif; ?>
        <?php if (!empty($result['error'])): ?>
          <p class="status err">Error: <?=h($result['error'])?></p>
        <?php endif; ?>
        <?php if (!empty($result['body'])): ?>
          <h4>Response body</h4>
          <pre><?=h($result['body'])?></pre>
        <?php endif; ?>
      </div>

      <div class="card">
        <h3>Rendered iXBRL (out/output.xhtml)</h3>
        <?php if ($rendered_ixbrl): ?>
		  <?php
		  clearstatcache(true, $paths['output_xhtml']);
		  $previewVersion = filemtime($paths['output_xhtml']) ?: time();
		  ?>
		  <iframe src="<?=h('out/output.xhtml?v=' . $previewVersion)?>"></iframe>
		  <p>
    		<a class="a" href="<?=h('out/output.xhtml?v=' . $previewVersion)?>" target="_blank" rel="noopener">Open current preview in a new tab</a>
		  </p>
        <?php else: ?>
          <p class="small">No rendered file yet. Click <em>Build iXBRL</em> or <em>Submit</em>.</p>
        <?php endif; ?>
      </div>

      <div class="card">
        <h3>Last Envelope</h3>
        <?php if ($last_envelope): ?>
          <pre><?=$last_envelope?></pre>
          <p><a class="a" href="out/last-envelope.xml" download>Download last-envelope.xml</a></p>
        <?php else: ?>
          <p class="small">No envelope built yet.</p>
        <?php endif; ?>
      </div>

      <div class="card">
        <h3>Latest Responses</h3>
        <ul>
          <li>Submit: <?= $latestSubmit ? '<a class="a" href="'.h(str_replace(__DIR__.'/', '', $latestSubmit)).'" target="_blank">'.h(basename($latestSubmit)).'</a>' : '<span class="small">—</span>' ?></li>
          <li>Status: <?= $latestStatus ? '<a class="a" href="'.h(str_replace(__DIR__.'/', '', $latestStatus)).'" target="_blank">'.h(basename($latestStatus)).'</a>' : '<span class="small">—</span>' ?></li>
          <li>Ack: <?= $latestAck ? '<a class="a" href="'.h(str_replace(__DIR__.'/', '', $latestAck)).'" target="_blank">'.h(basename($latestAck)).'</a>' : '<span class="small">—</span>' ?></li>
        </ul>
        <?php if ($latestSubmit): ?><p class="small">Newer files appear after each action.</p><?php endif; ?>
      </div>

      <div class="card" style="grid-column: 1 / -1;">
        <h3>Log tail (out/logs/gateway.log)</h3>
        <?php if ($log_tail): ?>
          <pre><?=$log_tail?></pre>
        <?php else: ?>
          <p class="small">No logs yet.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
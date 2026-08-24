<?php

declare(strict_types=1);

session_start();

require __DIR__ . '/src/Util.php';
require __DIR__ . '/src/FilingManager.php';

$cfg = require __DIR__ . '/config.php';
$filingPath = $cfg['paths']['filing'];
$filing = FilingManager::load($filingPath);
$schemaRef = (string) $cfg['ixbrl_vars']['schemaRef'];
$currentSubmission = (string) $cfg['form']['submission_number'];
$acceptance = FilingManager::acceptedStatus(__DIR__ . '/out', $currentSubmission);
$today = new DateTimeImmutable('today', new DateTimeZone('Europe/London'));
$error = null;

if (empty($_SESSION['prepare_csrf'])) {
    $_SESSION['prepare_csrf'] = bin2hex(random_bytes(24));
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function validDate(string $value, string $label): DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Europe/London'));
    $errors = DateTimeImmutable::getLastErrors();
    if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        throw new RuntimeException("Invalid {$label}.");
    }
    return $date;
}

function postedText(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

function financialKeys(array $vars): array
{
    $excluded = [
        'CY_StartDateForPeriodCoveredByReport',
        'CY_EndDateForPeriodCoveredByReport',
        'PY_StartDateForPeriodCoveredByReport',
        'PY_EndDateForPeriodCoveredByReport',
    ];

    $keys = [];
    foreach (array_keys($vars) as $key) {
        if ((substr($key, 0, 3) === 'CY_' || substr($key, 0, 3) === 'PY_') && !in_array($key, $excluded, true)) {
            $keys[] = $key;
        }
    }
    return $keys;
}

function fieldLabel(string $key): string
{
    $label = preg_replace('/^(CY|PY)_/', '$1 — ', $key);
    $label = preg_replace('/(?<!^)([A-Z])/', ' $1', (string) $label);
    return str_replace(['CY —', 'PY —'], ['Current year —', 'Comparative year —'], (string) $label);
}

try {
    $approvalForProposal = $today;
    $proposal = FilingManager::proposal($filing, $approvalForProposal, $schemaRef);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals((string) $_SESSION['prepare_csrf'], (string) ($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('The form session expired. Please reload and try again.');
        }
        if ($acceptance === null) {
            throw new RuntimeException('The current submission has no matching ACCEPT status file, so it cannot be rolled forward yet.');
        }
        if (postedText('expected_submission') !== $currentSubmission) {
            throw new RuntimeException('The filing changed after this page was opened. Reload before applying changes.');
        }
        if (postedText('expected_year') !== (string) $cfg['ixbrl_vars']['currentYear']) {
            throw new RuntimeException('The filing year changed after this page was opened. Reload before applying changes.');
        }

        $requiredChecks = [
            'confirm_approval',
            'confirm_company',
            'confirm_eligibility',
            'confirm_figures',
            'confirm_taxonomy',
        ];
        foreach ($requiredChecks as $check) {
            if (!isset($_POST[$check])) {
                throw new RuntimeException('Every confirmation box must be ticked before applying the rollover.');
            }
        }

        $approval = validDate(postedText('approval_date'), 'approval date');
        $proposal = FilingManager::proposal($filing, $approval, $schemaRef);

        $submissionNumber = strtoupper(postedText('submission_number'));
        $customerReference = strtoupper(postedText('customer_reference'));
        if (!preg_match('/^AC\d+$/', $submissionNumber)) {
            throw new RuntimeException('Submission number must use the form AC followed by digits.');
        }
        if (!preg_match('/^IXBRL\d+$/', $customerReference)) {
            throw new RuntimeException('Customer reference must use the form IXBRL followed by digits.');
        }

        $currentStart = validDate(postedText('current_period_start'), 'current period start');
        $currentEnd = validDate(postedText('current_period_end'), 'current period end');
        $previousStart = validDate(postedText('previous_period_start'), 'comparative period start');
        $previousEnd = validDate(postedText('previous_period_end'), 'comparative period end');

        if ($currentStart > $currentEnd) {
            throw new RuntimeException('The current period start must not be after its end.');
        }
        if ($previousStart > $previousEnd) {
            throw new RuntimeException('The comparative period start must not be after its end.');
        }
        if ($previousEnd >= $currentStart) {
            throw new RuntimeException('The comparative period must end before the current period begins.');
        }
        if ($approval <= $currentEnd) {
            throw new RuntimeException('The approval date must be after the current accounting period end.');
        }
        if ($approval > $today) {
            throw new RuntimeException('The approval date cannot be in the future.');
        }

        $proposal['form']['submission_number'] = $submissionNumber;
        $proposal['form']['customer_reference'] = $customerReference;
        $proposal['form']['date_signed'] = $approval->format('Y-m-d');
        $proposal['form']['date_document'] = $approval->format('Y-m-d');

        $proposal['ixbrl_vars']['CY_StartDateForPeriodCoveredByReport'] = $currentStart->format('Y-m-d');
        $proposal['ixbrl_vars']['CY_EndDateForPeriodCoveredByReport'] = $currentEnd->format('Y-m-d');
        $proposal['ixbrl_vars']['PY_StartDateForPeriodCoveredByReport'] = $previousStart->format('Y-m-d');
        $proposal['ixbrl_vars']['PY_EndDateForPeriodCoveredByReport'] = $previousEnd->format('Y-m-d');
        $proposal['ixbrl_vars']['BalanceSheetDate'] = $currentEnd->format('j F Y');
        $proposal['ixbrl_vars']['currentYear'] = $currentEnd->format('Y');
        $proposal['ixbrl_vars']['previousYear'] = $previousEnd->format('Y');
        $proposal['ixbrl_vars']['DateAuthorisationFinancialStatementsForIssue'] = $approval->format('j F Y');
        $proposal['ixbrl_vars']['StatementThatCompanyEntitledToExemptionFromAuditUnderSection477CompaniesAct2006RelatingToSmallCompanies'] = sprintf(
            'For the year ending %s the company was entitled to exemption under section 477 of the Companies Act 2006 relating to small companies.',
            $currentEnd->format('j F Y')
        );

        foreach (financialKeys($proposal['ixbrl_vars']) as $key) {
            $value = postedText('fact_' . $key, (string) $proposal['ixbrl_vars'][$key]);
            if (!preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
                throw new RuntimeException(fieldLabel($key) . ' must be numeric.');
            }
            if (strpos($key, 'AverageNumberEmployees') !== false && !preg_match('/^\d+$/', $value)) {
                throw new RuntimeException(fieldLabel($key) . ' must be a non-negative whole number.');
            }
            $proposal['ixbrl_vars'][$key] = $value;
        }

        $officer = postedText('NameEntityOfficer', (string) $proposal['ixbrl_vars']['NameEntityOfficer']);
        if ($officer === '') {
            throw new RuntimeException('The signing officer name cannot be blank.');
        }
        $proposal['ixbrl_vars']['NameEntityOfficer'] = $officer;

        foreach ([
            'StatementThatMembersHaveNotRequiredCompanyToObtainAnAudit',
            'StatementThatDirectorsAcknowledgeTheirResponsibilitiesUnderCompaniesAct',
            'StatementThatAccountsHaveBeenPreparedInAccordanceWithProvisionsSmallCompaniesRegime',
        ] as $statementKey) {
            $statement = postedText('statement_' . $statementKey, (string) $proposal['ixbrl_vars'][$statementKey]);
            if ($statement === '') {
                throw new RuntimeException('Statutory statements cannot be blank.');
            }
            $proposal['ixbrl_vars'][$statementKey] = $statement;
        }

        $proposal['checks']['prepared_for_year'] = $currentEnd->format('Y');
        $proposal['checks']['prepared_on'] = $today->format('Y-m-d');
        $proposal['checks']['taxonomy_verified_on'] = $today->format('Y-m-d');
        $proposal['checks']['taxonomy_schema_verified'] = $schemaRef;

        $backup = FilingManager::writeAtomic($filingPath, $proposal, $cfg['paths']['filing_backups']);
        $_SESSION['filing_flash'] = 'Next filing prepared. Backup created at ' . str_replace(__DIR__ . '/', '', $backup) . '. Nothing has been submitted.';
        header('Location: admin.php');
        exit;
    }
} catch (Throwable $exception) {
    $error = $exception->getMessage();
    try {
        $proposal = FilingManager::proposal($filing, $today, $schemaRef);
    } catch (Throwable) {
        $proposal = $filing;
    }
}

$form = $proposal['form'] ?? [];
$vars = $proposal['ixbrl_vars'] ?? [];
$factKeys = financialKeys($vars);
$proposedPeriodEnd = isset($vars['CY_EndDateForPeriodCoveredByReport'])
    ? validDate((string)$vars['CY_EndDateForPeriodCoveredByReport'], 'proposed period end')
    : $today;
$approvalWindowOpen = $today > $proposedPeriodEnd;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Prepare next Companies House filing</title>
<style>
:root { --bg:#0b0e14; --card:#11161e; --ink:#e8ecf1; --mut:#aab3be; --acc:#3ea6ff; --ok:#16a34a; --warn:#f59e0b; --err:#ef4444; }
* { box-sizing:border-box; }
body { margin:0; font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial; background:var(--bg); color:var(--ink); }
.header { display:flex; gap:1rem; align-items:center; padding:1rem 1.25rem; border-bottom:1px solid #1f2733; }
.header h1 { font-size:1.1rem; margin:0; }
.container { max-width:1200px; margin:0 auto; padding:1rem; }
.card { background:var(--card); border:1px solid #1f2733; border-radius:12px; padding:1rem; margin-bottom:1rem; }
.grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:.9rem; }
label { display:block; font-size:.88rem; color:var(--mut); margin-bottom:.25rem; }
input[type=text], input[type=date], textarea { width:100%; background:#0f1420; color:var(--ink); border:1px solid #2a3648; border-radius:8px; padding:.65rem; }
textarea { min-height:92px; resize:vertical; }
button, .button { display:inline-block; appearance:none; border:1px solid #2c456a; background:#1b2b44; color:var(--ink); padding:.7rem 1rem; border-radius:10px; cursor:pointer; font-weight:700; text-decoration:none; }
.button.secondary { background:#192233; border-color:#1f2733; }
.notice { border-left:4px solid var(--warn); background:#241b0d; padding:.85rem 1rem; border-radius:8px; }
.error { border-left-color:var(--err); background:#2a1114; }
.success { border-left-color:var(--ok); background:#102417; }
.small { color:var(--mut); font-size:.86rem; }
.compare { width:100%; border-collapse:collapse; }
.compare th,.compare td { padding:.55rem; border-bottom:1px solid #1f2733; text-align:left; vertical-align:top; }
.compare th { color:var(--mut); }
.check { display:flex; gap:.6rem; align-items:flex-start; margin:.8rem 0; }
.check input { margin-top:.2rem; }
code { overflow-wrap:anywhere; }
summary { cursor:pointer; font-weight:700; }
.actions { display:flex; gap:.7rem; flex-wrap:wrap; }
</style>
</head>
<body>
<div class="header">
  <h1>Prepare next Companies House filing</h1>
  <a class="button secondary" href="admin.php">Back to admin</a>
</div>
<div class="container">
  <div class="card notice">
    <strong>No gateway action occurs on this page.</strong>
    It only creates a timestamped backup and updates <code>filing.php</code>. You must return to the admin page, build the iXBRL, review it and submit separately.
  </div>

  <?php if ($error): ?>
    <div class="card notice error"><strong>Could not apply changes:</strong> <?=h($error)?></div>
  <?php endif; ?>

  <div class="card">
    <h2>Rollover eligibility</h2>
    <?php if ($acceptance): ?>
      <p class="notice success">The current submission <strong><?=h($currentSubmission)?></strong> has a saved <strong>ACCEPT</strong> status in <?=h(basename($acceptance['file']))?>. It can be rolled forward once.</p>
    <?php else: ?>
      <p class="notice error">No matching ACCEPT status file was found for <strong><?=h($currentSubmission)?></strong>. Applying the rollover is blocked to prevent accidental double-increments.</p>
    <?php endif; ?>
  </div>

  <?php if (!$approvalWindowOpen): ?>
    <div class="card notice">
      <strong>Preview only for now:</strong> the proposed period ends on <?=h($proposedPeriodEnd->format('j F Y'))?>, so an actual approval date cannot yet be entered. The Apply button will become available after that period has ended and the accounts have genuinely been approved.
    </div>
  <?php endif; ?>

  <form method="post" autocomplete="off">
    <input type="hidden" name="csrf" value="<?=h($_SESSION['prepare_csrf'])?>" />
    <input type="hidden" name="expected_submission" value="<?=h($currentSubmission)?>" />
    <input type="hidden" name="expected_year" value="<?=h($cfg['ixbrl_vars']['currentYear'])?>" />

    <div class="card">
      <h2>Proposed filing</h2>
      <table class="compare">
        <thead><tr><th>Value</th><th>Current</th><th>Proposed / editable</th></tr></thead>
        <tbody>
          <tr><td>Submission number</td><td><?=h($currentSubmission)?></td><td><input type="text" name="submission_number" value="<?=h($_POST['submission_number'] ?? $form['submission_number'] ?? '')?>" required /></td></tr>
          <tr><td>Customer reference</td><td><?=h($cfg['form']['customer_reference'])?></td><td><input type="text" name="customer_reference" value="<?=h($_POST['customer_reference'] ?? $form['customer_reference'] ?? '')?>" required /></td></tr>
          <tr><td>Approval, signed and document date</td><td><?=h($cfg['form']['date_signed'])?></td><td><input type="date" name="approval_date" value="<?=h($_POST['approval_date'] ?? $today->format('Y-m-d'))?>" max="<?=h($today->format('Y-m-d'))?>" required /></td></tr>
        </tbody>
      </table>
    </div>

    <div class="card">
      <h2>Accounting periods</h2>
      <div class="grid">
        <div><label for="current_period_start">Current period start</label><input id="current_period_start" type="date" name="current_period_start" value="<?=h($_POST['current_period_start'] ?? $vars['CY_StartDateForPeriodCoveredByReport'] ?? '')?>" required /></div>
        <div><label for="current_period_end">Current period end</label><input id="current_period_end" type="date" name="current_period_end" value="<?=h($_POST['current_period_end'] ?? $vars['CY_EndDateForPeriodCoveredByReport'] ?? '')?>" required /></div>
        <div><label for="previous_period_start">Comparative period start</label><input id="previous_period_start" type="date" name="previous_period_start" value="<?=h($_POST['previous_period_start'] ?? $vars['PY_StartDateForPeriodCoveredByReport'] ?? '')?>" required /></div>
        <div><label for="previous_period_end">Comparative period end</label><input id="previous_period_end" type="date" name="previous_period_end" value="<?=h($_POST['previous_period_end'] ?? $vars['PY_EndDateForPeriodCoveredByReport'] ?? '')?>" required /></div>
      </div>
      <p class="small">The balance-sheet date, year headings and section 477 sentence are derived from these dates.</p>
    </div>

    <div class="card">
      <h2>Figures carried forward for confirmation</h2>
      <p class="small">The old current-year figures have been copied into the new comparative column and prefilled into the new current-year column. Change any figure that is not correct.</p>
      <div class="grid">
        <?php foreach ($factKeys as $key): ?>
          <div>
            <label for="<?=h('fact_' . $key)?>"><?=h(fieldLabel($key))?></label>
            <input id="<?=h('fact_' . $key)?>" type="text" inputmode="decimal" name="<?=h('fact_' . $key)?>" value="<?=h($_POST['fact_' . $key] ?? $vars[$key])?>" required />
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card">
      <h2>Officer and statutory wording</h2>
      <div><label for="NameEntityOfficer">Signing officer</label><input id="NameEntityOfficer" type="text" name="NameEntityOfficer" value="<?=h($_POST['NameEntityOfficer'] ?? $vars['NameEntityOfficer'] ?? '')?>" required /></div>
      <p class="small">The section 477 sentence is generated automatically from the current period end.</p>
      <details>
        <summary>Review or amend the other statutory statements</summary>
        <?php foreach ([
          'StatementThatMembersHaveNotRequiredCompanyToObtainAnAudit',
          'StatementThatDirectorsAcknowledgeTheirResponsibilitiesUnderCompaniesAct',
          'StatementThatAccountsHaveBeenPreparedInAccordanceWithProvisionsSmallCompaniesRegime',
        ] as $statementKey): ?>
          <p><label for="<?=h('statement_' . $statementKey)?>"><?=h(fieldLabel($statementKey))?></label><textarea id="<?=h('statement_' . $statementKey)?>" name="<?=h('statement_' . $statementKey)?>" required><?=h($_POST['statement_' . $statementKey] ?? $vars[$statementKey] ?? '')?></textarea></p>
        <?php endforeach; ?>
      </details>
    </div>

    <div class="card notice">
      <h2>Taxonomy check required</h2>
      <p>Configured schema:</p>
      <p><code><?=h($schemaRef)?></code></p>
      <p class="small">A reachable schema URL does not itself prove that Companies House still accepts it. Check the current Companies House schema/taxonomy guidance before ticking the confirmation.</p>
      <p><a class="button secondary" href="https://xmlgw.companieshouse.gov.uk/SchemaStatus" target="_blank" rel="noopener">Open Companies House schema status</a></p>
    </div>

    <div class="card">
      <h2>Required confirmations</h2>
      <label class="check"><input type="checkbox" name="confirm_approval" value="1" <?=isset($_POST['confirm_approval'])?'checked':''?> /> <span>The accounts were actually approved on the date entered above.</span></label>
      <label class="check"><input type="checkbox" name="confirm_company" value="1" <?=isset($_POST['confirm_company'])?'checked':''?> /> <span>The company details, authentication details, director and contact information remain correct.</span></label>
      <label class="check"><input type="checkbox" name="confirm_eligibility" value="1" <?=isset($_POST['confirm_eligibility'])?'checked':''?> /> <span>The micro-entity, audit-exempt and non-dormant treatment has been checked for this filing.</span></label>
      <label class="check"><input type="checkbox" name="confirm_figures" value="1" <?=isset($_POST['confirm_figures'])?'checked':''?> /> <span>Every current-year, comparative and employee figure shown above has been checked.</span></label>
      <label class="check"><input type="checkbox" name="confirm_taxonomy" value="1" <?=isset($_POST['confirm_taxonomy'])?'checked':''?> /> <span>I have verified that Companies House currently accepts the configured taxonomy for this filing.</span></label>
    </div>

    <div class="card actions">
      <button type="submit" <?=($acceptance && $approvalWindowOpen) ? '' : 'disabled'?>>Apply changes and return to admin</button>
      <a class="button secondary" href="admin.php">Cancel</a>
    </div>
  </form>
</div>
</body>
</html>

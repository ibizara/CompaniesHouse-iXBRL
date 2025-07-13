<?php
$vars = [
	'xmlns' => 'xmlns="http://www.w3.org/1999/xhtml" xmlns:iso4217="http://www.xbrl.org/2003/iso4217" xmlns:ix="http://www.xbrl.org/2013/inlineXBRL" xmlns:ixt2="http://www.xbrl.org/inlineXBRL/transformation/2011-07-31" xmlns:link="http://www.xbrl.org/2003/linkbase" xmlns:uk-bus="http://xbrl.frc.org.uk/cd/2023-01-01/business" xmlns:uk-core="http://xbrl.frc.org.uk/fr/2023-01-01/core" xmlns:uk-direp="http://xbrl.frc.org.uk/reports/2023-01-01/direp" xmlns:xbrldi="http://xbrl.org/2006/xbrldi" xmlns:xbrli="http://www.xbrl.org/2003/instance" xmlns:xlink="http://www.w3.org/1999/xlink"',
	'AccountsType' => 'Micro-entity',
    'EntityCurrentLegalOrRegisteredName' => 'Example Ltd',
    'NameProductionSoftware' => 'CE.UK',
    'CY_StartDateForPeriodCoveredByReport' => '2024-01-01',
    'CY_EndDateForPeriodCoveredByReport' => '2024-12-31',
	'PY_StartDateForPeriodCoveredByReport' => '2023-01-01',
    'PY_EndDateForPeriodCoveredByReport' => '2023-12-31',
    'EntityDormantTruefalse' => 'false',
    'schemaRef' => 'https://xbrl.frc.org.uk/FRS-102/2023-01-01/FRS-102-2023-01-01.xsd',
    'UKCompaniesHouseRegisteredNumber' => '12345678',
    'AccountingStandardsDimension' => 'uk-bus:Micro-entities',
    'AccountsTypeDimension' => 'uk-bus:FullAccounts',
    'AccountsStatusDimension' => 'uk-bus:AuditExempt-NoAccountantsReport',
	'unit' => 'GBP',
    'GBP' => 'iso4217:GBP',
	'CurrencySymbol' => '£',
    'BalanceSheetDate' => '31 December 2024',
	'currentYear' => '2024',
	'previousYear' => '2023',
    'CY_CalledUpShareCapitalNotPaidNotExpressedAsCurrentAsset' => '10',
	'PY_CalledUpShareCapitalNotPaidNotExpressedAsCurrentAsset' => '10',
    'CY_CreditorsWithin' => '0',
	'PY_CreditorsWithin' => '0',
    'CY_NetCurrentAssetsLiabilities' => '0',
	'PY_NetCurrentAssetsLiabilities' => '0',
    'CY_TotalAssetsLessCurrentLiabilities' => '10',
	'PY_TotalAssetsLessCurrentLiabilities' => '10',
	'CY_CreditorsAfter' => '0',
	'PY_CreditorsAfter' => '0',
    'CY_ProvisionsForLiabilitiesBalanceSheetSubtotal' => '0',
	'PY_ProvisionsForLiabilitiesBalanceSheetSubtotal' => '0',
    'CY_AccruedLiabilitiesNotExpressedWithinCreditorsSubtotal' => '0',
	'PY_AccruedLiabilitiesNotExpressedWithinCreditorsSubtotal' => '0',
    'CY_NetAssetsLiabilities' => '10',
	'PY_NetAssetsLiabilities' => '10',
    'CY_Equity' => '10',
    'PY_Equity' => '10',
    'StatementThatCompanyEntitledToExemptionFromAuditUnderSection477CompaniesAct2006RelatingToSmallCompanies' => 'For the year ending 31 December 2024 the company was entitled to exemption under section 477 of the Companies Act 2006 relating to small companies.',
    'StatementThatMembersHaveNotRequiredCompanyToObtainAnAudit' => 'The members have not required the company to obtain an audit in accordance with section 476 of the Companies Act 2006.',
    'StatementThatDirectorsAcknowledgeTheirResponsibilitiesUnderCompaniesAct' => 'The directors acknowledge their responsibilities for complying with the requirements of the Companies Act 2006 with respect to accounting records and the preparation of accounts.',
    'StatementThatAccountsHaveBeenPreparedInAccordanceWithProvisionsSmallCompaniesRegime' => 'The accounts have been prepared in accordance with the micro-entity provisions and delivered in accordance with the provisions applicable to companies subject to the small companies regime.',
    'DateAuthorisationFinancialStatementsForIssue' => '4 July 2024',
    'NameEntityOfficer' => 'John Smith',
    'CY_AverageNumberEmployeesDuringPeriod' => '0',
    'PY_AverageNumberEmployeesDuringPeriod' => '0'
];

$template = file_get_contents('template.xhtml');

foreach ($vars as $key => $value) {
    $template = str_replace('{{' . $key . '}}', $value, $template);
}

file_put_contents('output.xhtml', $template);

?>

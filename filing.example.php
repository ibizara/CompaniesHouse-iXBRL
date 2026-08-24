<?php

declare(strict_types=1);

/**
 * Example values for an initial filing.
 *
 * Copy this file to storage/filing.php and replace every value with the
 * correct figures, dates, statutory wording and officer for the company.
 * The examples deliberately start at AC0001 / IXBRL001.
 */
return [
    'form' => [
        'submission_number' => 'AC0001',
        'customer_reference' => 'IXBRL001',
        'date_signed' => '2025-01-01',
        'date_document' => '2025-01-01',
    ],

    'ixbrl_vars' => [
        'CY_StartDateForPeriodCoveredByReport' => '2024-01-01',
        'CY_EndDateForPeriodCoveredByReport' => '2024-12-31',
        'PY_StartDateForPeriodCoveredByReport' => '2023-01-01',
        'PY_EndDateForPeriodCoveredByReport' => '2023-12-31',
        'BalanceSheetDate' => '31 December 2024',
        'currentYear' => '2024',
        'previousYear' => '2023',

        // Example figures only. Replace and verify every current/comparative fact.
        'CY_CalledUpShareCapitalNotPaidNotExpressedAsCurrentAsset' => '0',
        'PY_CalledUpShareCapitalNotPaidNotExpressedAsCurrentAsset' => '0',
        'CY_CreditorsWithin' => '0',
        'PY_CreditorsWithin' => '0',
        'CY_NetCurrentAssetsLiabilities' => '0',
        'PY_NetCurrentAssetsLiabilities' => '0',
        'CY_TotalAssetsLessCurrentLiabilities' => '0',
        'PY_TotalAssetsLessCurrentLiabilities' => '0',
        'CY_CreditorsAfter' => '0',
        'PY_CreditorsAfter' => '0',
        'CY_ProvisionsForLiabilitiesBalanceSheetSubtotal' => '0',
        'PY_ProvisionsForLiabilitiesBalanceSheetSubtotal' => '0',
        'CY_AccruedLiabilitiesNotExpressedWithinCreditorsSubtotal' => '0',
        'PY_AccruedLiabilitiesNotExpressedWithinCreditorsSubtotal' => '0',
        'CY_NetAssetsLiabilities' => '0',
        'PY_NetAssetsLiabilities' => '0',
        'CY_Equity' => '0',
        'PY_Equity' => '0',

        'StatementThatCompanyEntitledToExemptionFromAuditUnderSection477CompaniesAct2006RelatingToSmallCompanies' => 'For the year ending 31 December 2024 the company was entitled to exemption under section 477 of the Companies Act 2006 relating to small companies.',
        'StatementThatMembersHaveNotRequiredCompanyToObtainAnAudit' => 'The members have not required the company to obtain an audit in accordance with section 476 of the Companies Act 2006.',
        'StatementThatDirectorsAcknowledgeTheirResponsibilitiesUnderCompaniesAct' => 'The directors acknowledge their responsibilities for complying with the requirements of the Companies Act 2006 with respect to accounting records and the preparation of accounts.',
        'StatementThatAccountsHaveBeenPreparedInAccordanceWithProvisionsSmallCompaniesRegime' => 'The accounts have been prepared in accordance with the micro-entity provisions and delivered in accordance with the provisions applicable to companies subject to the small companies regime.',
        'DateAuthorisationFinancialStatementsForIssue' => '1 January 2025',
        'NameEntityOfficer' => 'DIRECTOR NAME',
        'CY_AverageNumberEmployeesDuringPeriod' => '0',
        'PY_AverageNumberEmployeesDuringPeriod' => '0',
    ],

    // Set these to true only after the relevant checks have genuinely been made.
    'checks' => [
        'prepared_for_year' => '2024',
        'prepared_on' => '2025-01-01',
        'approval_date_confirmed' => false,
        'company_details_confirmed' => false,
        'eligibility_confirmed' => false,
        'figures_confirmed' => false,
        'taxonomy_verified' => false,
        'taxonomy_verified_on' => '',
        'taxonomy_schema_verified' => '',
    ],
];

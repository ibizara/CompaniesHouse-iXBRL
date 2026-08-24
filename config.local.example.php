<?php

declare(strict_types=1);

/**
 * Copy this file to config.local.php and replace every example value.
 *
 * Do not commit config.local.php. It contains presenter credentials and the
 * company authentication code.
 */
return [
    // true adds <GatewayTest>1</GatewayTest>; false sends to the live service.
    'gateway_test' => true,

    'presenter' => [
        // Companies House expects the lowercase MD5 values in these fields.
        // Use the MD5 helper on admin.php, then paste only the generated hashes.
        'sender_id' => 'REPLACE_WITH_LOWERCASE_MD5_OF_PRESENTER_ID',
        'auth_method' => 'clear',
        'auth_value' => 'REPLACE_WITH_LOWERCASE_MD5_OF_PRESENTER_CODE',
        'email' => 'you@example.com',
    ],

    'company' => [
        'number' => '00000000',
        'type' => 'EW',
        'name' => 'YOUR COMPANY LIMITED',
        'auth_code' => 'REPLACE_ME',
    ],

    'form' => [
        // Use the package reference supplied or approved by Companies House.
        'package_reference' => 'REPLACE_ME',
        'contact_name' => 'YOUR NAME',
        'contact_number' => 'YOUR TELEPHONE NUMBER',
    ],

    'ixbrl_vars' => [
        // Keep these aligned with the company values above.
        'EntityCurrentLegalOrRegisteredName' => 'YOUR COMPANY LIMITED',
        'UKCompaniesHouseRegisteredNumber' => '00000000',
        'NameProductionSoftware' => 'CompaniesHouse-iXBRL',
    ],
];

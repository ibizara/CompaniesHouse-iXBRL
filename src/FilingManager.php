<?php

final class FilingManager
{
    private const REQUIRED_CHECKS = [
        'approval_date_confirmed',
        'company_details_confirmed',
        'eligibility_confirmed',
        'figures_confirmed',
        'taxonomy_verified',
    ];

    public static function load(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('filing.php was not found.');
        }

        $data = require $path;
        if (!is_array($data)) {
            throw new RuntimeException('filing.php must return an array.');
        }

        return $data;
    }

    public static function nextReference(string $value, string $prefix): string
    {
        $pattern = '/^' . preg_quote($prefix, '/') . '(\d+)$/';
        if (!preg_match($pattern, $value, $matches)) {
            throw new RuntimeException("Expected {$prefix} followed by digits; received {$value}.");
        }

        $width = strlen($matches[1]);
        $next = (int) $matches[1] + 1;
        return $prefix . str_pad((string) $next, $width, '0', STR_PAD_LEFT);
    }

    public static function proposal(array $filing, DateTimeImmutable $approvalDate, string $schemaRef): array
    {
        $form = $filing['form'] ?? [];
        $vars = $filing['ixbrl_vars'] ?? [];

        foreach ([
            'submission_number', 'customer_reference', 'date_signed', 'date_document',
        ] as $required) {
            if (!array_key_exists($required, $form)) {
                throw new RuntimeException("Missing filing form value: {$required}");
            }
        }

        foreach ([
            'CY_StartDateForPeriodCoveredByReport',
            'CY_EndDateForPeriodCoveredByReport',
            'PY_StartDateForPeriodCoveredByReport',
            'PY_EndDateForPeriodCoveredByReport',
        ] as $required) {
            if (!array_key_exists($required, $vars)) {
                throw new RuntimeException("Missing iXBRL filing value: {$required}");
            }
        }

        $oldStart = self::date($vars['CY_StartDateForPeriodCoveredByReport'], 'current period start');
        $oldEnd = self::date($vars['CY_EndDateForPeriodCoveredByReport'], 'current period end');
        $newStart = $oldStart->modify('+1 year');
        $newEnd = $oldEnd->modify('+1 year');

        $today = new DateTimeImmutable('today', new DateTimeZone('Europe/London'));

        $next = $filing;
        $next['form'] = $form;
        $next['ixbrl_vars'] = $vars;

        $next['form']['submission_number'] = self::nextReference($form['submission_number'], 'AC');
        $next['form']['customer_reference'] = self::nextReference($form['customer_reference'], 'IXBRL');
        $next['form']['date_signed'] = $approvalDate->format('Y-m-d');
        $next['form']['date_document'] = $approvalDate->format('Y-m-d');

        // Carry each old current-year fact into both columns. The user confirms or edits
        // the new current-year values before applying the rollover.
        foreach ($vars as $key => $value) {
            if (substr($key, 0, 3) !== 'CY_') {
                continue;
            }
            $previousKey = 'PY_' . substr($key, 3);
            if (array_key_exists($previousKey, $vars)) {
                $next['ixbrl_vars'][$previousKey] = $value;
                $next['ixbrl_vars'][$key] = $value;
            }
        }

        $next['ixbrl_vars']['CY_StartDateForPeriodCoveredByReport'] = $newStart->format('Y-m-d');
        $next['ixbrl_vars']['CY_EndDateForPeriodCoveredByReport'] = $newEnd->format('Y-m-d');
        $next['ixbrl_vars']['PY_StartDateForPeriodCoveredByReport'] = $oldStart->format('Y-m-d');
        $next['ixbrl_vars']['PY_EndDateForPeriodCoveredByReport'] = $oldEnd->format('Y-m-d');
        $next['ixbrl_vars']['BalanceSheetDate'] = $newEnd->format('j F Y');
        $next['ixbrl_vars']['currentYear'] = $newEnd->format('Y');
        $next['ixbrl_vars']['previousYear'] = $oldEnd->format('Y');
        $next['ixbrl_vars']['DateAuthorisationFinancialStatementsForIssue'] = $approvalDate->format('j F Y');
        $next['ixbrl_vars']['StatementThatCompanyEntitledToExemptionFromAuditUnderSection477CompaniesAct2006RelatingToSmallCompanies'] = sprintf(
            'For the year ending %s the company was entitled to exemption under section 477 of the Companies Act 2006 relating to small companies.',
            $newEnd->format('j F Y')
        );

        $next['checks'] = [
            'prepared_for_year' => $newEnd->format('Y'),
            'prepared_on' => $today->format('Y-m-d'),
            'approval_date_confirmed' => true,
            'company_details_confirmed' => true,
            'eligibility_confirmed' => true,
            'figures_confirmed' => true,
            'taxonomy_verified' => true,
            'taxonomy_verified_on' => $today->format('Y-m-d'),
            'taxonomy_schema_verified' => $schemaRef,
        ];

        return $next;
    }

    public static function checksComplete(array $checks, string $currentYear, string $schemaRef): bool
    {
        if (($checks['prepared_for_year'] ?? null) !== $currentYear) {
            return false;
        }
        if (($checks['taxonomy_schema_verified'] ?? null) !== $schemaRef) {
            return false;
        }
        foreach (self::REQUIRED_CHECKS as $key) {
            if (($checks[$key] ?? false) !== true) {
                return false;
            }
        }
        return true;
    }

    public static function writeAtomic(string $path, array $data, string $backupDir): string
    {
        Util::ensureDir($backupDir);

        $lockPath = $path . '.lock';
        $lock = fopen($lockPath, 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            throw new RuntimeException('Could not obtain the filing update lock.');
        }

        try {
            clearstatcache(true, $path);
            $stamp = (new DateTimeImmutable('now', new DateTimeZone('Europe/London')))->format('Ymd-His');
            $backup = $backupDir . '/filing-' . $stamp . '.php';
            if (!copy($path, $backup)) {
                throw new RuntimeException('Could not create a filing.php backup.');
            }

            $content = "<?php\n\n/** Generated by prepare.php. Reviewable annual filing values only. */\nreturn "
                . var_export($data, true)
                . ";\n";

            $temporary = $path . '.tmp-' . bin2hex(random_bytes(6));
            if (file_put_contents($temporary, $content, LOCK_EX) === false) {
                throw new RuntimeException('Could not write the temporary filing file.');
            }
            if (!rename($temporary, $path)) {
                @unlink($temporary);
                throw new RuntimeException('Could not replace filing.php atomically.');
            }

            return $backup;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public static function acceptedStatus(string $outDir, string $submissionNumber): ?array
    {
        $files = glob(rtrim($outDir, '/') . '/response-status-*.xml') ?: [];
        rsort($files, SORT_STRING);

        foreach ($files as $file) {
            $xml = file_get_contents($file);
            if ($xml === false) {
                continue;
            }

            preg_match_all('/<(?:[A-Za-z0-9_]+:)?Status\b[^>]*>(.*?)<\/(?:[A-Za-z0-9_]+:)?Status>/si', $xml, $blocks);
            foreach ($blocks[1] ?? [] as $block) {
                $number = self::tagValue($block, 'SubmissionNumber');
                $code = strtoupper((string) self::tagValue($block, 'StatusCode'));
                $matchesSubmission = $number !== null && ($number === $submissionNumber || substr($number, -strlen('-' . $submissionNumber)) === '-' . $submissionNumber);
                if ($code === 'ACCEPT' && $matchesSubmission) {
                    return [
                        'file' => $file,
                        'submission_number' => $number,
                        'status_code' => $code,
                    ];
                }
            }
        }

        return null;
    }

    private static function tagValue(string $xml, string $tag): ?string
    {
        $pattern = '/<(?:[A-Za-z0-9_]+:)?' . preg_quote($tag, '/') . '\b[^>]*>\s*(.*?)\s*<\/(?:[A-Za-z0-9_]+:)?' . preg_quote($tag, '/') . '>/si';
        if (!preg_match($pattern, $xml, $match)) {
            return null;
        }
        return html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function date(string $value, string $label): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Europe/London'));
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new RuntimeException("Invalid {$label}: {$value}");
        }
        return $date;
    }
}

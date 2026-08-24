<?php

final class ConfigValidator
{
    public static function errors(array $cfg): array
    {
        $errors = [];
        $flat = self::flatten($cfg);

        foreach ([
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
        ] as $path) {
            if (!array_key_exists($path, $flat) || trim((string) $flat[$path]) === '') {
                $errors[] = 'Missing required value: ' . $path;
            }
        }

        $placeholderPattern = '/(?:REPLACE|YOUR COMPANY|YOUR NAME|YOUR TELEPHONE|DIRECTOR NAME|00000000)/i';
        foreach ($flat as $path => $value) {
            if (is_scalar($value) && preg_match($placeholderPattern, (string) $value)) {
                $errors[] = 'Example placeholder remains in ' . $path;
            }
        }

        $submission = (string) ($cfg['form']['submission_number'] ?? '');
        if (!preg_match('/^AC\d+$/', $submission)) {
            $errors[] = 'Submission number must be AC followed by digits.';
        }
        $reference = (string) ($cfg['form']['customer_reference'] ?? '');
        if (!preg_match('/^IXBRL\d+$/', $reference)) {
            $errors[] = 'Customer reference must be IXBRL followed by digits.';
        }

        $template = (string) ($cfg['paths']['template'] ?? '');
        if (!is_file($template)) {
            $errors[] = 'template.xhtml was not found.';
        } else {
            $contents = file_get_contents($template);
            if ($contents === false) {
                $errors[] = 'template.xhtml could not be read.';
            } else {
                preg_match_all('/\{\{([A-Za-z0-9_]+)\}\}/', $contents, $matches);
                $required = array_values(array_unique($matches[1] ?? []));
                $missing = array_values(array_diff($required, array_keys($cfg['ixbrl_vars'] ?? [])));
                if ($missing !== []) {
                    $errors[] = 'Missing template value(s): ' . implode(', ', $missing);
                }
            }
        }

        return array_values(array_unique($errors));
    }

    private static function flatten(array $data, string $prefix = ''): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $result += self::flatten($value, $path);
            } else {
                $result[$path] = $value;
            }
        }
        return $result;
    }
}

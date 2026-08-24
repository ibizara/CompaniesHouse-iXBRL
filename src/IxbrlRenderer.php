<?php

class IxbrlRenderer
{
    private string $templatePath;
    private string $outputPath;

    public function __construct(string $templatePath, string $outputPath)
    {
        $this->templatePath = $templatePath;
        $this->outputPath = $outputPath;
    }

    public function render(array $vars): string
    {
        if (!is_file($this->templatePath)) {
            throw new RuntimeException('template.xhtml was not found.');
        }

        $template = file_get_contents($this->templatePath);
        if ($template === false) {
            throw new RuntimeException('template.xhtml could not be read.');
        }

        preg_match_all('/\{\{([A-Za-z0-9_]+)\}\}/', $template, $matches);
        $required = array_values(array_unique($matches[1] ?? []));
        $missing = array_values(array_diff($required, array_keys($vars)));
        if ($missing !== []) {
            throw new RuntimeException(
                'Missing template value(s): ' . implode(', ', $missing)
            );
        }

        foreach ($vars as $key => $value) {
            // xmlns is an intentionally raw bundle of namespace attributes.
            // All other values are XML-escaped before insertion into text or
            // attribute positions in the supplied template.
            $replacement = $key === 'xmlns'
                ? (string) $value
                : htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
            $template = str_replace('{{' . $key . '}}', $replacement, $template);
        }

        if (preg_match('/\{\{[A-Za-z0-9_]+\}\}/', $template, $unresolved)) {
            throw new RuntimeException('Unresolved template placeholder: ' . $unresolved[0]);
        }

        if (class_exists('DOMDocument')) {
            $previous = libxml_use_internal_errors(true);
            $document = new DOMDocument();
            $valid = $document->loadXML($template, LIBXML_NONET);
            $errors = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            if (!$valid) {
                $detail = $errors ? trim($errors[0]->message) : 'unknown XML error';
                throw new RuntimeException('Rendered iXBRL is not well-formed XML: ' . $detail);
            }
        }

        Util::ensureDir(dirname($this->outputPath));
        if (file_put_contents($this->outputPath, $template, LOCK_EX) === false) {
            throw new RuntimeException('Rendered iXBRL could not be written.');
        }

        return $template;
    }
}

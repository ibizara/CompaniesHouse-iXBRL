<?php

class Util
{
    public static function ensureDir(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }

    public static function log(string $file, string $message): void
    {
        self::ensureDir(dirname($file));
        $line = '[' . date('c') . "] " . $message . "\n";
        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    /** Increment a hexadecimal string (A–F allowed), uppercase result */
    public static function incrementHex(string $hex): string
    {
        $hex = strtoupper(trim($hex));
        if ($hex === '') return '1';
        $digits = '0123456789ABCDEF';
        $carry = 1; $out = '';
        for ($i = strlen($hex) - 1; $i >= 0; $i--) {
            $ch = $hex[$i];
            $pos = strpos($digits, $ch);
            if ($pos === false) {
                // Non-hex char – keep as-is, but do not carry through it
                $out = $ch . $out;
                continue;
            }
            $pos += $carry;
            if ($pos >= 16) { $pos -= 16; $carry = 1; } else { $carry = 0; }
            $out = $digits[$pos] . $out;
        }
        if ($carry) $out = '1' . $out;
        return $out;
    }

    public static function base64(string $data): string
    {
        return base64_encode($data);
    }
}
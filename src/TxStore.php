<?php

class TxStore
{
    private string $file;

    public function __construct(string $file)
    {
        $this->file = $file;
        if (!file_exists($file)) {
            Util::ensureDir(dirname($file));
            if (file_put_contents($file, 'ABC323456789DEF0', LOCK_EX) === false) {
                throw new RuntimeException('Cannot create transaction ID file.');
            }
        }
    }

    /**
     * Return the current transaction ID and immediately store the next value,
     * preventing a retry from reusing the same identifier.
     */
    public function next(): string
    {
        $fh = fopen($this->file, 'c+');
        if (!$fh) {
            throw new RuntimeException('Cannot open transaction ID file.');
        }
        if (!flock($fh, LOCK_EX)) {
            fclose($fh);
            throw new RuntimeException('Cannot lock transaction ID file.');
        }

        try {
            $current = strtoupper(trim((string) stream_get_contents($fh)));
            if ($current === '') {
                $current = 'ABC323456789DEF0';
            }
            if (!preg_match('/^[0-9A-F]+$/', $current)) {
                throw new RuntimeException('Transaction ID must contain hexadecimal characters only.');
            }

            $next = Util::incrementHex($current);
            ftruncate($fh, 0);
            rewind($fh);
            if (fwrite($fh, $next) === false) {
                throw new RuntimeException('Cannot update transaction ID file.');
            }
            fflush($fh);
            return $current;
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }
}

<?php

class GovTalkClient
{
    private string $url;
    private string $log;

    public function __construct(string $url, string $log)
    {
        $this->url = $url;
        $this->log = $log;
    }

    public function post(string $xml): array
    {
        $ch = curl_init();
        if ($ch === false) {
            throw new RuntimeException('Could not initialise cURL.');
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: text/xml; charset=UTF-8'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 60,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        $http = (int) ($info['http_code'] ?? 0);
        Util::log($this->log, 'POST ' . $this->url . ' HTTP ' . ($http ?: '?'));

        if ($error !== '') {
            Util::log($this->log, 'cURL error: ' . $error);
            return [
                'ok' => false,
                'error' => $error,
                'http' => $http,
                'body' => $response === false ? '' : (string) $response,
            ];
        }

        $ok = $http >= 200 && $http < 300;
        if (!$ok) {
            Util::log($this->log, 'Unexpected HTTP status: ' . $http);
        }

        return [
            'ok' => $ok,
            'http' => $http,
            'body' => $response === false ? '' : (string) $response,
            'error' => $ok ? null : 'Unexpected HTTP status ' . $http,
        ];
    }
}

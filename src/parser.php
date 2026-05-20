<?php

/**
 * Parser raportów HTML
 * Kompatybilny z index.php
 * Obsługuje:
 * - komentarze HTML <!-- -->
 * - komentarze //
 * - listy <li>
 * - multiline
 * - widok skanowania
 */

class RaportParser {

    private $filePath;

    public function __construct($filePath = null) {
        $this->filePath = $filePath;
    }

    public function parse() {

        if (!$this->filePath || !file_exists($this->filePath)) {
            return $this->getEmptyResponse();
        }

        $htmlContent = @file_get_contents($this->filePath);

        if (empty($htmlContent)) {
            return $this->getEmptyResponse();
        }

        // ====================================
        // USUWANIE KOMENTARZY
        // ====================================

        // <!-- komentarz -->
        $htmlContent = preg_replace('/<!--.*?-->/s', '', $htmlContent);

        // // komentarz
        $htmlContent = preg_replace('/^\s*\/\/.*$/m', '', $htmlContent);

        libxml_use_internal_errors(true);

        $dom = new DOMDocument();

        $dom->loadHTML(
            '<?xml encoding="UTF-8">' . $htmlContent,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        $rows = $xpath->query('//tr[contains(@class, "table_tbody")]');

        $results = [];

        $totalEvents = 0;

        $uniqueIps = [];

        foreach ($rows as $row) {

            $cells = $row->getElementsByTagName('td');

            if ($cells->length < 10) {
                continue;
            }

            $sourceIp = trim($cells->item(0)->textContent);

            $destIps = $this->extractComplexList(
                $cells->item(6)
            );

            $countries = $this->extractComplexList(
                $cells->item(8)
            );

            $services = $this->extractComplexList(
                $cells->item(9)
            );

            $eventsCount = count($destIps);

            $totalEvents += $eventsCount;

            $uniqueIps[$sourceIp] = true;

            $results[] = [

                'source_country' =>
                    $countries[0] ?? 'Unknown',

                'source_ip' =>
                    $sourceIp,

                'dest_ip' =>
                    implode("\n", $destIps),

                'dest_port' =>
                    'N/A',

                'events_count' =>
                    $eventsCount,

                'danger_level' =>
                    $this->calculateDangerLevel($eventsCount),

                'application' =>
                    $services[0] ?? 'Unknown',

                'protocol' =>
                    'TCP',

                'service' =>
                    $services[0] ?? 'Unknown',

                'event_info' =>
                    'Network activity detected',

                'event_desc' =>
                    'Automatically parsed from HTML report',

                'abuse_url' =>
                    'https://www.abuseipdb.com/check/' .
                    urlencode($sourceIp),

                'virustotal_url' =>
                    'https://www.virustotal.com/gui/ip-address/' .
                    urlencode($sourceIp),

                'whois_url' =>
                    'https://www.whois.com/whois/' .
                    urlencode($sourceIp)
            ];
        }

        // ====================================
        // NAJBARDZIEJ AKTYWNE IP
        // ====================================

        $topIp = 'N/A';

        $maxEvents = 0;

        foreach ($results as $r) {

            if ($r['events_count'] > $maxEvents) {

                $maxEvents = $r['events_count'];

                $topIp = $r['source_ip'];
            }
        }

        return [

            'scans' => $results,

            'meta' => [

                'timestamp' => time(),

                'suma_zdarzen' =>
                    $totalEvents,

                'unikalne_ip' =>
                    count($uniqueIps),

                'najbardziej_aktywny_ip' =>
                    $topIp,

                'urzadzenie' =>
                    'Firewall'
            ]
        ];
    }

    /**
     * Obsługa list i multiline
     */
    private function extractComplexList($tdNode) {

        if (!$tdNode) {
            return [];
        }

        // <li>
        if ($tdNode->getElementsByTagName('li')->length > 0) {
            return $this->getListItems($tdNode);
        }

        $text = trim($tdNode->textContent);

        if (empty($text)) {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $text);

        $clean = [];

        foreach ($lines as $line) {

            $line = trim($line);

            if (!empty($line)) {
                $clean[] = $line;
            }
        }

        return array_values($clean);
    }

    /**
     * Pobieranie <li>
     */
    private function getListItems($tdNode) {

        if (!$tdNode) {
            return [];
        }

        $items = [];

        $listItems = $tdNode->getElementsByTagName('li');

        foreach ($listItems as $li) {

            $text = trim($li->textContent);

            if (!empty($text)) {
                $items[] = $text;
            }
        }

        return $items;
    }

    /**
     * Wyliczanie poziomu zagrożenia
     */
    private function calculateDangerLevel($events) {

        if ($events >= 20) {
            return 'Critical';
        }

        if ($events >= 10) {
            return 'High';
        }

        if ($events >= 5) {
            return 'Medium';
        }

        return 'Low';
    }

    /**
     * Flagi państw
     */
    public function getCountryFlag($country) {

        $flags = [

            'Poland' => '🇵🇱',
            'Germany' => '🇩🇪',
            'France' => '🇫🇷',
            'United States' => '🇺🇸',
            'China' => '🇨🇳',
            'Russia' => '🇷🇺',
            'Ukraine' => '🇺🇦',
            'Unknown' => '🌍'
        ];

        return $flags[$country] ?? '🌍';
    }

    /**
     * Czyszczenie wartości
     */
    private function cleanValue($text) {

        return preg_replace(
            '/[^0-9.]/',
            '',
            str_ireplace(
                [' mb', ' gb', ' kb', ' bytes', ' '],
                '',
                $text
            )
        );
    }

    /**
     * Empty response
     */
    private function getEmptyResponse() {

        return [

            'scans' => [],

            'meta' => [

                'timestamp' => time(),

                'suma_zdarzen' => 0,

                'unikalne_ip' => 0,

                'najbardziej_aktywny_ip' => 'N/A',

                'urzadzenie' => 'Firewall'
            ]
        ];
    }
}
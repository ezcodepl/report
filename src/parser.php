<?php

/**
 * Klasa RaportParser
 * Wersja rozszerzona:
 * - obsługa list (<ul><li>)
 * - zabezpieczenia nullsafe
 * - czyszczenie danych
 * - pomijanie komentarzy:
 *   - HTML <!-- komentarz -->
 *   - linie // komentarz
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

        // =========================
        // USUWANIE KOMENTARZY
        // =========================

        // 1. Usuń komentarze HTML <!-- -->
        $htmlContent = preg_replace('/<!--.*?-->/s', '', $htmlContent);

        // 2. Usuń linie zaczynające się od //
        $htmlContent = preg_replace('/^\s*\/\/.*$/m', '', $htmlContent);

        libxml_use_internal_errors(true);

        $dom = new DOMDocument();

        $dom->loadHTML(
            '<?xml encoding="UTF-8">' . $htmlContent,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        // Szukamy wierszy z danymi
        $rows = $xpath->query('//tr[contains(@class, "table_tbody")]');

        $results = [];

        foreach ($rows as $row) {

            $cells = $row->getElementsByTagName('td');

            if ($cells->length < 10) {
                continue;
            }

            $results[] = [
                'source_ip' => trim($cells->item(0)->textContent),

                'bytes_rx' => $this->cleanValue(
                    $cells->item(1)->textContent
                ),

                'bytes_tx' => $this->cleanValue(
                    $cells->item(2)->textContent
                ),

                'bytes_tot' => $this->cleanValue(
                    $cells->item(3)->textContent
                ),

                'dest_ips' => $this->extractComplexList(
                    $cells->item(6)
                ),

                'countries' => $this->extractComplexList(
                    $cells->item(8)
                ),

                'services' => $this->extractComplexList(
                    $cells->item(9)
                )
            ];
        }

        return [
            'top_hosts' => $results,
            'meta' => [
                'timestamp' => time()
            ]
        ];
    }

    /**
     * Wyciąga dane z TD
     * Obsługuje:
     * - <li>
     * - wielolinijkowy tekst
     */
    private function extractComplexList($tdNode) {

        if (!$tdNode) {
            return [];
        }

        // Jeśli są <li>
        if ($tdNode->getElementsByTagName('li')->length > 0) {
            return $this->getListItems($tdNode);
        }

        $text = trim($tdNode->textContent);

        if (empty($text)) {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $text);

        return array_values(
            array_filter(
                array_map('trim', $lines)
            )
        );
    }

    private function getListItems($tdNode) {

        if (!$tdNode) {
            return [];
        }

        $listItems = $tdNode->getElementsByTagName('li');

        if ($listItems->length > 0) {

            $items = [];

            foreach ($listItems as $li) {

                $text = trim($li->textContent);

                if (!empty($text)) {
                    $items[] = $text;
                }
            }

            return $items;
        }

        $text = trim($tdNode->textContent);

        if (empty($text)) {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $text);

        $cleanLines = [];

        foreach ($lines as $line) {

            $line = trim($line);

            if (!empty($line)) {
                $cleanLines[] = $line;
            }
        }

        return $cleanLines;
    }

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

    private function getEmptyResponse() {

        return [
            'top_hosts' => [],
            'meta' => [
                'timestamp' => time()
            ]
        ];
    }
}
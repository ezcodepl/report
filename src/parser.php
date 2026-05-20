<?php

/**
 * Klasa RaportParser
 * Wersja finalna: Uwzględnia obsługę list (<ul><li>), zabezpieczenia nullsafe,
 * czyszczenie danych oraz ujednoliconą strukturę meta/top_hosts.
 */
class RaportParser {

   private $filePath;

    public function __construct($filePath = null) {
        $this->filePath = $filePath;
    }

    public function parse() {
        if (!$this->filePath || !file_exists($this->filePath)) return $this->getEmptyResponse();

        $htmlContent = @file_get_contents($this->filePath);
        if (empty($htmlContent)) return $this->getEmptyResponse();

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $htmlContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        // Szukamy wierszy z danymi
        $rows = $xpath->query('//tr[contains(@class, "table_tbody")]');

        $results = [];
        foreach ($rows as $row) {
            $cells = $row->getElementsByTagName('td');
            if ($cells->length < 10) continue;

            // Wyciągamy dane z uwzględnieniem, że wewnątrz td mogą być linie <br> lub lista
            $results[] = [
                'source_ip'     => trim($cells->item(0)->textContent),
                'bytes_rx'      => $this->cleanValue($cells->item(1)->textContent),
                'bytes_tx'      => $this->cleanValue($cells->item(2)->textContent),
                'bytes_tot'     => $this->cleanValue($cells->item(3)->textContent),
                'dest_ips'      => $this->extractComplexList($cells->item(6)),
                'countries'     => $this->extractComplexList($cells->item(8)),
                'services'      => $this->extractComplexList($cells->item(9))
            ];
        }

        return ['top_hosts' => $results, 'meta' => ['timestamp' => time()]];
    }

    /**
     * Nowa metoda do wyciągania danych, która radzi sobie z listami i wieloma liniami
     */
    private function extractComplexList($tdNode) {
        if (!$tdNode) return [];
        
        // Jeśli są <li>, używamy starej metody
        if ($tdNode->getElementsByTagName('li')->length > 0) {
            return $this->getListItems($tdNode);
        }

        // Jeśli nie ma list, dzielimy tekst po nowej linii (częsty przypadek w FortiGate)
        $text = trim($tdNode->textContent);
        if (empty($text)) return [];
        
        // Dzielimy na linie, usuwamy puste elementy i czyścimy
        $lines = explode("\n", $text);
        return array_values(array_filter(array_map('trim', $lines)));
    }

    private function getListItems($tdNode) {
        if (!$tdNode) return [];
        
        // 1. Sprawdź, czy są listy <li>
        $listItems = $tdNode->getElementsByTagName('li');
        if ($listItems->length > 0) {
            $items = [];
            foreach ($listItems as $li) {
                $text = trim($li->textContent);
                if (!empty($text)) $items[] = $text;
            }
            return $items;
        }

        // 2. Jeśli nie ma <li>, potraktuj zawartość jako surowy tekst podzielony liniami
        $text = trim($tdNode->textContent);
        if (empty($text)) return [];
        
        // Dzielimy po nowej linii, usuwamy puste elementy i białe znaki
        $lines = explode("\n", $text);
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
        // Usuwamy wszystko poza cyframi i kropką (obsługa MB/GB/bytes)
        return preg_replace('/[^0-9.]/', '', str_ireplace([' mb', ' gb', ' kb', ' bytes', ' '], '', $text));
    }

    private function getEmptyResponse() {
        return ['top_hosts' => [], 'meta' => ['timestamp' => time()]];
    }
}


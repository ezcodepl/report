<?php
/**
 * Klasa Parser dla pliku: Hosty_zewnetrzne_skanujace_porty_...html
 * Analizuje próby skanowania z hostów zewnętrznych, bezpiecznie przetwarzając zagnieżdżone tabele Logsign.
 */
class RaportZewnSkanujaceParser {
    private $filePath;
    private $fileName;

    public function __construct($filePath) {
        $this->filePath = $filePath;
        $this->fileName = basename($filePath);
    }

    public function getCountryFlag($countryName) {
        $countryName = trim(strtolower($countryName));
        $countries = [
            'poland' => '🇵🇱', 'polska' => '🇵🇱',
            'united states' => '🇺🇸', 'usa' => '🇺🇸',
            'germany' => '🇩🇪', 'niemcy' => '🇩🇪',
            'russia' => '🇷🇺', 'rosja' => '🇷🇺',
            'china' => '🇨🇳', 'chiny' => '🇨🇳',
            'netherlands' => '🇳🇱', 'holandia' => '🇳🇱',
            'reserved' => '🏳️'
        ];
        return $countries[$countryName] ?? '🏳️';
    }

    /**
     * Bezpiecznie odczytuje wartość komórki, rozwijając zagnieżdżone tabele Logsign na linie tekstu.
     */
    private function getCellValue($colNode) {
        if (!$colNode) return '';
        
        // Szukamy zagnieżdżonych tabel wewnątrz komórki
        $nestedTables = $colNode->getElementsByTagName('table');
        if ($nestedTables->length > 0) {
            $lines = [];
            $tds = $colNode->getElementsByTagName('td');
            foreach ($tds as $td) {
                // Wybieramy wyłącznie liście (td, które same nie posiadają kolejnych tabel)
                if ($td->getElementsByTagName('table')->length === 0) {
                    $text = trim($td->textContent);
                    if ($text !== '') {
                        $lines[] = $text;
                    }
                }
            }
            if (!empty($lines)) {
                return implode("\n", $lines);
            }
        }
        
        return trim($colNode->nodeValue);
    }

    public function parse() {
        $data = [
            'meta' => [
                'nazwa_pliku' => $this->fileName,
                'suma_zdarzen' => 0,
                'unikalne_ip' => 0,
                'najbardziej_aktywny_ip' => 'Brak',
                'urzadzenie' => 'Logsign SIEM'
            ],
            'scans' => []
        ];

        if (!file_exists($this->filePath)) return $data;

        $htmlContent = file_get_contents($this->filePath);
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $htmlContent);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $rows = $xpath->query('//table//tr');

        $headers = [];
        $uniqueIps = [];
        $ipCounts = [];

        foreach ($rows as $row) {
            $cols = $xpath->query('.//td | .//th', $row);
            if ($cols->length === 0) continue;

            // Wykrywanie i oczyszczanie nagłówków kolumn
            if (empty($headers) && $cols->length > 4) {
                foreach ($cols as $idx => $col) {
                    $headers[$idx] = trim(preg_replace('/\s*\([^)]*\)/', '', $col->nodeValue));
                }
                continue;
            }

            if (!empty($headers) && $cols->length >= count($headers)) {
                $record = [];
                foreach ($cols as $idx => $col) {
                    if (isset($headers[$idx])) {
                        $record[$headers[$idx]] = $this->getCellValue($col);
                    }
                }

                $sourceIp = '';
                $destIp = '';
                $destPort = '';
                $eventsCount = 1;
                $protocol = 'TCP';
                $service = '';
                $app = '';
                $sourceCountry = 'Unknown';
                $destCountry = 'Poland';
                $timeGenerated = '';

                foreach ($record as $key => $val) {
                    $lines = explode("\n", $val);
                    $firstLine = isset($lines[0]) ? trim($lines[0]) : '';
                    $cleanVal = trim(preg_replace('/\s*\([^)]*\)/', '', $firstLine));

                    if (stripos($key, 'Source.IP') !== false) {
                        $sourceIp = $cleanVal;
                        if (preg_match('/\(([\d\s,]+)\)/', $firstLine, $m)) {
                            $eventsCount = (int)str_replace([' ', ','], '', $m[1]);
                        }
                    } elseif (stripos($key, 'Destination.IP') !== false) {
                        $destIp = $val; // Zachowujemy kompletną listę zagnieżdżoną
                    } elseif (stripos($key, 'Destination.Port') !== false || stripos($key, 'Port') !== false) {
                        $destPort = $cleanVal;
                        if (preg_match('/\(([\d\s,]+)\)/', $firstLine, $m)) {
                            $eventsCount = (int)str_replace([' ', ','], '', $m[1]);
                        }
                    } elseif (stripos($key, 'Protocol') !== false) {
                        $protocol = $val;
                    } elseif (stripos($key, 'Service') !== false) {
                        $service = $val;
                    } elseif (stripos($key, 'Application') !== false) {
                        $app = $val;
                    } elseif (stripos($key, 'Source.Country') !== false) {
                        $sourceCountry = $cleanVal;
                    } elseif (stripos($key, 'Destination.Country') !== false) {
                        $destCountry = $cleanVal;
                    } elseif (stripos($key, 'Time') !== false) {
                        $timeGenerated = $val; // Pełna lista timestampów
                    }
                }

                // Bezpieczne mapowanie w przypadku grupowania nadrzędnego
                if (empty($sourceIp) && isset($record['Source.IP'])) {
                    $sourceIp = trim(preg_replace('/\s*\([^)]*\)/', '', explode("\n", $record['Source.IP'])[0]));
                }
                
                // Inteligentne wykrywanie pierwszej kolumny jako Source IP w razie braku dokładnego nagłówka
                if (empty($sourceIp)) {
                    $firstColVal = $this->getCellValue($cols->item(0));
                    $potentialIp = trim(preg_replace('/\s*\([^)]*\)/', '', explode("\n", $firstColVal)[0]));
                    if (filter_var($potentialIp, FILTER_VALIDATE_IP)) {
                        $sourceIp = $potentialIp;
                        if (preg_match('/\(([\d\s,]+)\)/', explode("\n", $firstColVal)[0], $m)) {
                            $eventsCount = (int)str_replace([' ', ','], '', $m[1]);
                        }
                    }
                }

                if ($sourceIp) {
                    $uniqueIps[$sourceIp] = true;
                    $ipCounts[$sourceIp] = ($ipCounts[$sourceIp] ?? 0) + $eventsCount;
                    $data['meta']['suma_zdarzen'] += $eventsCount;

                    $data['scans'][] = [
                        'source_ip' => $sourceIp,
                        'dest_ip' => $destIp ?: 'Dowolny',
                        'dest_port' => $destPort ?: 'Dowolny',
                        'protocol' => trim(preg_replace('/\s*\([^)]*\)/', '', explode("\n", $protocol)[0])),
                        'service' => trim(preg_replace('/\s*\([^)]*\)/', '', explode("\n", $service)[0])) ?: 'Nieznana',
                        'application' => trim(preg_replace('/\s*\([^)]*\)/', '', explode("\n", $app)[0])) ?: 'Skanowanie portów',
                        'source_country' => $sourceCountry ?: 'Unknown',
                        'dest_country' => $destCountry ?: 'Unknown',
                        'danger_level' => $eventsCount > 5000 ? 'Critical' : ($eventsCount > 1000 ? 'High' : 'Medium'),
                        'events_count' => $eventsCount,
                        'event_info' => 'External Scan detected',
                        'event_desc' => 'Złośliwe próby skanowania portów z adresu zewnętrznego.',
                        'time_generated' => $timeGenerated,
                        'abuse_url' => 'https://www.abuseipdb.com/check/' . urlencode($sourceIp),
                        'virustotal_url' => 'https://www.virustotal.com/gui/ip-address/' . urlencode($sourceIp),
                        'whois_url' => 'https://www.whois.com/whois/' . urlencode($sourceIp)
                    ];
                }
            }
        }

        $data['meta']['unikalne_ip'] = count($uniqueIps);
        if (!empty($ipCounts)) {
            arsort($ipCounts);
            $data['meta']['najbardziej_aktywny_ip'] = array_key_first($ipCounts);
        }

        return $data;
    }
}
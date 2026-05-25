<?php
/**
 * Klasa Parser dla pliku: Uzytkownicy_z_bednymi_probami_logowania_...html
 * Wyciąga i parsuje logi nieudanych autoryzacji pod kątem nazw użytkowników i pełnej korelacji danych.
 */
class RaportBedneLogowaniaUzytkownicyParser {
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
            'germany' => '🇩🇪', 'niemcy' => '🇩🇪'
        ];
        return $countries[$countryName] ?? '🏳️';
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
            'records' => []
        ];

        if (!file_exists($this->filePath)) return $data;

        $htmlContent = file_get_contents($this->filePath);
        
        // Konwersja tagów <br> na znak nowej linii \n przed analizą DOM.
        // Gwarantuje to poprawne zachowanie wielolinijkowego formatu Time.Generated (Term)
        $htmlContent = str_ireplace(['<br>', '<br/>', '<br />'], "\n", $htmlContent);

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

            // Wykrywanie nagłówków tabeli na podstawie thead użytkownika
            if (empty($headers)) {
                foreach ($cols as $idx => $col) {
                    $headers[$idx] = trim($col->nodeValue);
                }
                continue;
            }

            // Mapowanie wartości wierszy na nagłówki
            if (!empty($headers) && $cols->length >= count($headers)) {
                $record = [];
                foreach ($cols as $idx => $col) {
                    if (isset($headers[$idx])) {
                        $record[$headers[$idx]] = trim($col->nodeValue);
                    }
                }

                // Inicjalizacja domyślnego wiersza z mapowaniem pod nową wizualizację
                $mapped = [
                    'user' => '-',
                    'sourceIp' => '-',
                    'sourceHost' => '-',
                    'destIp' => '-',
                    'destHost' => '-',
                    'subType' => '-',
                    'timeGenerated' => '',
                    'description' => '-',
                    'eventSourceIp' => '-',
                    'serviceName' => '-'
                ];

                foreach ($record as $key => $val) {
                    // Elastyczne dopasowanie kolumn bez względu na spacje i pomocnicze dopiski "(Term)"
                    if (stripos($key, 'Source.UserName') !== false) {
                        $mapped['user'] = $val;
                    } elseif (stripos($key, 'Source.IP') !== false) {
                        $mapped['sourceIp'] = $val;
                    } elseif (stripos($key, 'Source.HostName') !== false) {
                        $mapped['sourceHost'] = $val;
                    } elseif (stripos($key, 'Destination.IP') !== false) {
                        $mapped['destIp'] = $val;
                    } elseif (stripos($key, 'Destination.HostName') !== false) {
                        $mapped['destHost'] = $val;
                    } elseif (stripos($key, 'SubType') !== false) {
                        $mapped['subType'] = $val;
                    } elseif (stripos($key, 'Time.Generated') !== false) {
                        $mapped['timeGenerated'] = $val;
                    } elseif (stripos($key, 'Description') !== false) {
                        $mapped['description'] = $val;
                    } elseif (stripos($key, 'EventSource.IP') !== false) {
                        $mapped['eventSourceIp'] = $val;
                    } elseif (stripos($key, 'Service.Name') !== false) {
                        $mapped['serviceName'] = $val;
                    }
                }

                // Pobranie sumarycznej liczby prób logowań ze statystyk użytkownika
                $userClean = preg_replace('/\s*\([^)]*\)/', '', $mapped['user']);
                $ipClean = preg_replace('/\s*\([^)]*\)/', '', $mapped['sourceIp']);
                
                $rowEvents = 1;
                if (preg_match('/\(([\d\s]+)\)/', $mapped['user'], $match)) {
                    $rowEvents = (int)str_replace(' ', '', $match[1]);
                }

                if ($userClean !== '-' || $ipClean !== '-') {
                    $uniqueIps[$ipClean] = true;
                    $ipCounts[$ipClean] = ($ipCounts[$ipClean] ?? 0) + $rowEvents;
                    $data['meta']['suma_zdarzen'] += $rowEvents;
                    $data['records'][] = $mapped;
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
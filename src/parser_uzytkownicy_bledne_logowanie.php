<?php
/**
 * Klasa Parser dla pliku: Uzytkownicy_z_bednymi_probami_logowania_...html
 * Wyciąga i parsuje logi nieudanych autoryzacji pod kątem nazw użytkowników.
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
            'united states' => '🇺🇸', 'usa' => '🇺🇸'
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
                'urzadzenie' => 'FortiGate (FG)'
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

            if (empty($headers) && $cols->length > 3) {
                foreach ($cols as $idx => $col) {
                    $headers[$idx] = trim(preg_replace('/\s*\([^)]*\)/', '', $col->nodeValue));
                }
                continue;
            }

            if (!empty($headers) && $cols->length >= count($headers)) {
                $record = [];
                foreach ($cols as $idx => $col) {
                    if (isset($headers[$idx])) {
                        $record[$headers[$idx]] = trim($col->nodeValue);
                    }
                }

                $sourceIp = '';
                $user = '';
                $eventsCount = 1;
                $service = 'SSH/VPN';
                $country = 'Poland';

                foreach ($record as $key => $val) {
                    $cleanVal = preg_replace('/\s*\([^)]*\)/', '', $val);
                    if (stripos($key, 'User') !== false) {
                        $user = $cleanVal;
                        if (preg_match('/\(([\d\s]+)\)/', $val, $m)) {
                            $eventsCount = (int)str_replace(' ', '', $m[1]);
                        }
                    } elseif (stripos($key, 'Source.IP') !== false) {
                        $sourceIp = $cleanVal;
                    } elseif (stripos($key, 'Service') !== false) {
                        $service = $cleanVal;
                    } elseif (stripos($key, 'Source.Country') !== false) {
                        $country = $cleanVal;
                    }
                }

                if ($user || $sourceIp) {
                    $uniqueIps[$sourceIp] = true;
                    $ipCounts[$sourceIp] = ($ipCounts[$sourceIp] ?? 0) + $eventsCount;
                    $data['meta']['suma_zdarzen'] += $eventsCount;

                    $data['scans'][] = [
                        'source_ip' => $sourceIp ?: 'Nieznany IP',
                        'dest_ip' => 'Serwer Uwierzytelniający',
                        'dest_port' => 'Port autoryzacyjny',
                        'protocol' => 'TCP',
                        'service' => $service,
                        'application' => 'User Failed Login',
                        'source_country' => $country,
                        'dest_country' => 'Poland',
                        'danger_level' => $eventsCount > 30 ? 'Critical' : 'High',
                        'events_count' => $eventsCount,
                        'event_info' => 'Konto: ' . ($user ?: 'nieznany'),
                        'event_desc' => 'Próba siłowego złamania hasła dla podanej nazwy użytkownika',
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

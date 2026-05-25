<?php
/**
 * Klasa Parser dla pliku: Poaczenia_wychodzace_z_hostow_wewnetrznych_na_niestandardowe_porty_hostow_zewnetrznych_...html
 * Śledzi ruch lokalnych hostów do nietypowych portów w Internecie.
 */
class RaportWychodzaceNiestandardoweParser {
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
                        $record[$headers[$idx]] = trim($col->nodeValue);
                    }
                }

                $sourceIp = '';
                $destIp = '';
                $destPort = '';
                $eventsCount = 1;
                $protocol = 'TCP';
                $service = '';
                $app = '';
                $sourceCountry = 'Poland';
                $destCountry = 'Unknown';

                foreach ($record as $key => $val) {
                    $cleanVal = preg_replace('/\s*\([^)]*\)/', '', $val);
                    if (stripos($key, 'Source.IP') !== false) {
                        $sourceIp = $cleanVal;
                        if (preg_match('/\(([\d\s]+)\)/', $val, $m)) {
                            $eventsCount = (int)str_replace(' ', '', $m[1]);
                        }
                    } elseif (stripos($key, 'Destination.IP') !== false) {
                        $destIp = $cleanVal;
                    } elseif (stripos($key, 'Destination.Port') !== false || stripos($key, 'Port') !== false) {
                        $destPort = $cleanVal;
                    } elseif (stripos($key, 'Protocol') !== false) {
                        $protocol = $cleanVal;
                    } elseif (stripos($key, 'Service') !== false) {
                        $service = $cleanVal;
                    } elseif (stripos($key, 'Application') !== false) {
                        $app = $cleanVal;
                    } elseif (stripos($key, 'Destination.Country') !== false) {
                        $destCountry = $cleanVal;
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
                        'protocol' => $protocol,
                        'service' => $service ?: 'Niestandardowa',
                        'application' => $app ?: 'Nietypowy Port Wychodzący',
                        'source_country' => $sourceCountry,
                        'dest_country' => $destCountry,
                        'danger_level' => 'Medium',
                        'events_count' => $eventsCount,
                        'event_info' => 'Non-Standard Outbound',
                        'event_desc' => 'Ruch lokalnego hosta wykryty na niestandardowym porcie docelowym ' . ($destPort ?: 'WAN'),
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


<?php

class RaportParser {

    private $filePath;
    private $filterDay;

    public function __construct($filePath = null, $filterDay = 'all') {
        $this->filePath = $filePath;
        $this->filterDay = $filterDay;
    }

    public function parse() {

        if (!$this->filePath || !file_exists($this->filePath)) {
            return $this->getEmptyResponse();
        }

        $html = @file_get_contents($this->filePath);

        if (empty($html)) {
            return $this->getEmptyResponse();
        }

        // usuwanie komentarzy
        $html = preg_replace('/<!--.*?-->/s', '', $html);
        $html = preg_replace('/^\s*\/\/.*$/m', '', $html);

        libxml_use_internal_errors(true);

        $dom = new DOMDocument();

        $dom->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        $rows = $xpath->query('//tr[contains(@class, "table_tbody")]');

        $hosts = [];

        $sumRx = 0;
        $sumTx = 0;
        $sumAll = 0;
        $sumEvents = 0;

        foreach ($rows as $index => $row) {

            $cells = $row->getElementsByTagName('td');

            if ($cells->length < 10) {
                continue;
            }
            // DEBUG
   

            $ip = trim($cells->item(0)->textContent);

            $rx = (float)$this->cleanValue($cells->item(1)->textContent);
            $tx = (float)$this->cleanValue($cells->item(2)->textContent);
            $total = (float)$this->cleanValue($cells->item(3)->textContent);
            
            $hostnameRaw = trim($cells->item(5)->textContent);
            $hostname = preg_replace('/\s*\([0-9]+\)$/', '', $hostnameRaw);

            if (empty($hostname)) {
                $hostname = 'Brak nazwy (DHCP)';
            }

            $events = rand(100, 30000);

            $countries = $this->extractComplexList($cells->item(8));
            $services = $this->extractComplexList($cells->item(9));
            $apps = $this->extractComplexList($cells->item(10));
            $destIps = $this->extractComplexList($cells->item(6));

            $sumRx += $rx;
            $sumTx += $tx;
            $sumAll += $total;
            $sumEvents += $events;

            $hosts[] = [

                'pozycja' => $index + 1,

                'ip' => $ip,

                'opis' => $hostname,

                'zdarzenia' => number_format($events, 0, ' ', ' '),

                'rx' => number_format($rx, 1) . ' MB',

                'tx' => number_format($tx, 1) . ' MB',

                'suma' => number_format($total, 1) . ' MB',

                'rx_raw' => $rx,

                'tx_raw' => $tx,

                'suma_raw' => $total,

                'procent_pasma' => 0,

                'kierunki' => $this->buildDirections($destIps),

                'geolokalizacja' => $this->buildCountries($countries),

                'uslugi' => $this->buildServices($services),
                
                'aplikacje' => $this->buildServices($apps)
            ];
        }

        // sortowanie po transferze
        usort($hosts, function($a, $b) {
            return $b['suma_raw'] <=> $a['suma_raw'];
        });

        $max = 1;

        foreach ($hosts as $h) {
            if ($h['suma_raw'] > $max) {
                $max = $h['suma_raw'];
            }
        }

        foreach ($hosts as $k => $h) {

            $hosts[$k]['pozycja'] = $k + 1;

            $hosts[$k]['procent_pasma'] = round(
                ($h['suma_raw'] / $max) * 100,
                2
            );
        }

        $selectedHost = $hosts[0] ?? [];

        if (!empty($_GET['active_ip'])) {

            foreach ($hosts as $h) {

                if ($h['ip'] === $_GET['active_ip']) {
                    $selectedHost = $h;
                    break;
                }
            }
        }

        return [

            'top_hosts' => $hosts,

            'selected_host' => [

                'ip' => $selectedHost['ip'] ?? '',

                'nazwa' => $selectedHost['opis'] ?? '',

                'domena' => 'DNS w DHCP',

                'rx' => $selectedHost['rx'] ?? '0 MB',

                'tx' => $selectedHost['tx'] ?? '0 MB',

                'suma' => $selectedHost['suma'] ?? '0 MB',

                'zdarzenia' => $selectedHost['zdarzenia'] ?? 0,

                'kierunki' => $selectedHost['kierunki'] ?? [],

                'geolokalizacja' => $selectedHost['geolokalizacja'] ?? [],

                'uslugi' => $selectedHost['uslugi'] ?? [],

                'aplikacje' => $selectedHost['aplikacje'] ?? []
            ],

            'rozkład_godzinowy' => $this->buildHours(),

            'meta' => [

                'suma_transferu' => number_format($sumAll, 1) . ' MB',

                'pobrane_rx' => number_format($sumRx, 1) . ' MB',

                'wyslane_tx' => number_format($sumTx, 1) . ' MB',

                'liczba_zdarzen' => number_format($sumEvents, 0, ',', ' '),

                'urzadzenie' => 'FortiGate (FG)',

                'available_days' => [
                    'all' => 'Łącznie (3 dni)',
                    '15' => '15 maj',
                    '16' => '16 maj',
                    '17' => '17 maj'
                ]
            ]
        ];
    }

//     private function buildDirections($ips) {

//         $out = [];

//         foreach ($ips as $ip) {

//             $out[] = [

//                 'ip' => $ip,

//                 'zdarzenia' => rand(50, 15000),

//                 'procent' => rand(10, 100),

//                 'whois_url' => 'https://www.whois.com/whois/' . urlencode($ip)
//             ];
//         }

//         return $out;
//     }

//     private function buildCountries($countries) {

//         $out = [];

//         foreach ($countries as $country) {

//             $parts = explode(' ', $country, 2);

//             $out[] = [

//                 'prefiks' => strtoupper(substr($country, 0, 2)),

//                 'kraj' => $country,

//                 'logi' => rand(50, 30000),

//                 'procent' => rand(10, 100)
//             ];
//         }

//         return $out;
//     }
// private function buildServices($services)
// {
//     $out = [];

//     foreach ($services as $service) {

//         preg_match('/^(.*?)\s*\((\d+)\)$/', $service, $match);

//         $nazwa = trim($match[1] ?? $service);
//         $zdarzenia = (int)($match[2] ?? 0);

//         $out[] = [
//             'nazwa' => $nazwa,
//             'zdarzenia' => $zdarzenia
//         ];
//     }

//     // suma wszystkich zdarzeń
//     $total = array_sum(array_column($out, 'zdarzenia'));

//     // procenty
//     foreach ($out as &$item) {
//         $item['procent'] = $total > 0
//             ? round(($item['zdarzenia'] / $total) * 100, 1)
//             : 0;
//     }

//     // sortowanie malejąco
//     usort($out, function ($a, $b) {
//         return $b['zdarzenia'] <=> $a['zdarzenia'];
//     });

//     return $out;
// }

//     private function buildHours() {

//         $hours = [];

//         for ($i = 0; $i < 8; $i++) {

//             $hours[] = [

//                 'godzina' => date('m-d H:i', strtotime("+$i hour")),

//                 'logi' => rand(1, 15)
//             ];
//         }

//         return $hours;
//     }

//     private function extractComplexList($tdNode) {

//         if (!$tdNode) {
//             return [];
//         }

//         $listItems = $tdNode->getElementsByTagName('li');

//         if ($listItems->length > 0) {

//             $items = [];

//             foreach ($listItems as $li) {

//                 $text = trim($li->textContent);

//                 if (!empty($text)) {
//                     $items[] = $text;
//                 }
//             }

//             return $items;
//         }

//         $text = trim($tdNode->textContent);

//         if (empty($text)) {
//             return [];
//         }

//         $lines = preg_split('/\r\n|\r|\n/', $text);

//         return array_values(array_filter(array_map('trim', $lines)));
//     }

//     private function cleanValue($text) {

//         return preg_replace(
//             '/[^0-9.]/',
//             '',
//             str_ireplace(
//                 [' mb', ' gb', ' kb', ' bytes', ' '],
//                 '',
//                 $text
//             )
//         );
//     }
private function buildDirections($ips)
{
    $out = [];

    foreach ($ips as $ipEntry) {

        // np. 192.168.1.1 (1234)
        preg_match('/^(.*?)\s*\((\d+)\)$/', trim($ipEntry), $match);

        $ip = trim($match[1] ?? $ipEntry);
        $zdarzenia = (int)($match[2] ?? 0);

        // typ ruchu
        if (
            filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            )
        ) {
            $typ = 'zew.';
        } else {
            $typ = 'wew.';
        }

        $out[] = [
            'ip' => $ip,
            'typ' => $typ,
            'zdarzenia' => $zdarzenia,
            'whois_url' => 'https://www.whois.com/whois/' . urlencode($ip)
        ];
    }

    // suma zdarzeń
    $total = array_sum(array_column($out, 'zdarzenia'));

    // procenty
    foreach ($out as &$item) {

        $item['procent'] = $total > 0
            ? round(($item['zdarzenia'] / $total) * 100, 1)
            : 0;
    }

    unset($item);

    // sortowanie malejąco
    usort($out, function ($a, $b) {
        return $b['zdarzenia'] <=> $a['zdarzenia'];
    });

    return $out;
}

private function buildCountries($countries)
{
    $out = [];

    foreach ($countries as $countryEntry) {

        // np. Poland (1234)
        preg_match('/^(.*?)\s*\((\d+)\)$/', trim($countryEntry), $match);

        $kraj = trim($match[1] ?? $countryEntry);
        $logi = (int)($match[2] ?? 0);

        $out[] = [
            'prefiks' => strtoupper(substr($kraj, 0, 2)),
            'kraj' => $kraj,
            'logi' => $logi
        ];
    }

    // suma logów
    $total = array_sum(array_column($out, 'logi'));

    // procenty
    foreach ($out as &$item) {

        $item['procent'] = $total > 0
            ? round(($item['logi'] / $total) * 100, 1)
            : 0;
    }

    unset($item);

    // sortowanie malejąco
    usort($out, function ($a, $b) {
        return $b['logi'] <=> $a['logi'];
    });

    return $out;
}

private function buildServices($services)
{
    $out = [];

    foreach ($services as $service) {

        // np. SSL (1276)
        preg_match('/^(.*?)\s*\((\d+)\)$/', trim($service), $match);

        $nazwa = trim($match[1] ?? $service);
        $zdarzenia = (int)($match[2] ?? 0);

        $out[] = [
            'nazwa' => $nazwa,
            'zdarzenia' => $zdarzenia
        ];
    }

    // suma wszystkich zdarzeń
    $total = array_sum(array_column($out, 'zdarzenia'));

    // procenty
    foreach ($out as &$item) {

        $item['procent'] = $total > 0
            ? round(($item['zdarzenia'] / $total) * 100, 1)
            : 0;
    }

    unset($item);

    // sortowanie malejąco
    usort($out, function ($a, $b) {
        return $b['zdarzenia'] <=> $a['zdarzenia'];
    });

    return $out;
}

private function buildHours()
{
    $hours = [];

    for ($i = 0; $i < 8; $i++) {

        $logi = rand(1, 15);

        $hours[] = [
            'godzina' => date('Y-m-d H:i:s', strtotime("+$i hour")),
            'logi' => $logi
        ];
    }

    return $hours;
}

private function extractComplexList($tdNode)
{
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

    return array_values(array_filter(array_map('trim', $lines)));
}

private function cleanValue($text)
{
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

            'selected_host' => [],

            'rozkład_godzinowy' => [],

            'meta' => [

                'suma_transferu' => '0 MB',

                'pobrane_rx' => '0 MB',

                'wyslane_tx' => '0 MB',

                'liczba_zdarzen' => 0,

                'urzadzenie' => 'FortiGate',

                'available_days' => [
                    'all' => 'Łącznie'
                ]
            ]
        ];
    }
}
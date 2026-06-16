<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

function socRequire($relative, $fallback) {
    $primary = __DIR__ . '/' . $relative;
    $secondary = __DIR__ . '/' . $fallback;
    if (file_exists($primary)) {
        require_once $primary;
        return;
    }
    if (file_exists($secondary)) {
        require_once $secondary;
    }
}

socRequire('parsers/parser.php', 'parser.php');
socRequire('parsers/parser_skanowanie_wew.php', 'parser_skanowanie_wew.php');
socRequire('parsers/parser_host_logowanie.php', 'parser_host_logowanie.php');
socRequire('parsers/parser_skanowanie_zew.php', 'parser_skanowanie_zew.php');
socRequire('parsers/parser_odrzuconych_polaczen_wew.php', 'parser_odrzuconych_polaczen_wew.php');
socRequire('parsers/parser_odrzucownych_polaczen_zew.php', 'parser_odrzucownych_polaczen_zew.php');
socRequire('parsers/parser_polaczen_niestandardowe_porty.php', 'parser_polaczen_niestandardowe_porty.php');
socRequire('parsers/parser_uzytkownicy_bledne_logowanie.php', 'parser_uzytkownicy_bledne_logowanie.php');

$danePath = __DIR__ . '/dane/';
$period = isset($_GET['period']) ? (int)$_GET['period'] : 7;
if (!in_array($period, [3, 7, 30], true)) $period = 7;

function socCleanLabel($value, $fallback = 'Nieznany') {
    $value = html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = str_replace(["\xC2\xA0", '&nbsp;', 'Â ', 'Â '], ' ', $value);
    $value = preg_replace('/\s+/u', ' ', trim($value));
    $value = preg_replace('/\s*[-–]?\s*\([\d\s,]+\)\s*$/u', '', $value);
    $value = trim($value, " \t\n\r\0\x0B-–—");
    return $value !== '' ? $value : $fallback;
}

function socAdd(&$bucket, $label, $count, $fallback = 'Nieznany') {
    $label = socCleanLabel($label, $fallback);
    if ($label === '-' || $label === '') return;
    $bucket[$label] = ($bucket[$label] ?? 0) + max(1, (int)$count);
}

function socAddTerms(&$bucket, $rawText, $fallbackCount = 1, $fallbackLabel = 'Nieznany', $skipNoise = true) {
    $rawText = html_entity_decode((string)$rawText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $rawText = str_replace(["\xC2\xA0", '&nbsp;', 'Â ', 'Â '], ' ', $rawText);
    $rawText = preg_replace('/\s+/u', ' ', trim($rawText));

    $added = false;
    if (preg_match_all('/([^()\n]+?)\s*\(([\d\s,]+)\)/u', $rawText, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $label = socCleanLabel($match[1], $fallbackLabel);
            if ($skipNoise && preg_match('/^(reserved|unknown|nieznany|brak|dowolny)$/i', $label)) continue;
            $count = (int)str_replace([' ', ','], '', $match[2]);
            if ($label !== '') {
                $bucket[$label] = ($bucket[$label] ?? 0) + max(1, $count);
                $added = true;
            }
        }
    }

    if (!$added && $rawText !== '') {
        $label = socCleanLabel($rawText, $fallbackLabel);
        if (!($skipNoise && preg_match('/^(reserved|unknown|nieznany|brak|dowolny)$/i', $label))) {
            $bucket[$label] = ($bucket[$label] ?? 0) + max(1, (int)$fallbackCount);
        }
    }
}

function socMergeHourly(&$target, $source) {
    for ($i = 0; $i < 24; $i++) {
        $target[$i] += (int)($source[$i] ?? 0);
    }
}

function socHourlyFromItem($item) {
    $hours = array_fill(0, 24, 0);
    if (!empty($item['hourly_stats']) && is_array($item['hourly_stats'])) {
        foreach ($item['hourly_stats'] as $hour => $count) {
            $hour = (int)$hour;
            if ($hour >= 0 && $hour <= 23) $hours[$hour] += (int)$count;
        }
    }
    if (!empty($item['time_generated_list']) && is_array($item['time_generated_list'])) {
        foreach ($item['time_generated_list'] as $timeItem) {
            $hour = isset($timeItem['hour']) ? (int)$timeItem['hour'] : -1;
            $count = (int)($timeItem['count'] ?? 1);
            if ($hour >= 0 && $hour <= 23) $hours[$hour] += max(1, $count);
        }
    }
    if (array_sum($hours) === 0 && !empty($item['time_generated'])) {
        $raw = (string)$item['time_generated'];
        if (preg_match_all('/\d{4}-\d{2}-\d{2}\s+(\d{2}):\d{2}:\d{2}(?:\s*\(([\d\s,]+)\))?/u', $raw, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $hour = (int)$match[1];
                $count = isset($match[2]) && trim($match[2]) !== '' ? (int)str_replace([' ', ','], '', $match[2]) : 1;
                if ($hour >= 0 && $hour <= 23) $hours[$hour] += max(1, $count);
            }
        }
    }
    return $hours;
}

function socFormatMb($mb) {
    $mb = (float)$mb;
    if ($mb >= 1000000) return number_format($mb / 1000000, 2, ',', ' ') . ' TB';
    if ($mb >= 1000) return number_format($mb / 1000, 2, ',', ' ') . ' GB';
    return number_format($mb, 1, ',', ' ') . ' MB';
}

function socTop($items, $limit = 10) {
    arsort($items);
    return array_slice($items, 0, $limit, true);
}

function socReportParserForFile($fullPath) {
    $filename = basename($fullPath);
    if (mb_stripos($filename, 'transfer') !== false || mb_stripos($filename, 'transfe') !== false) {
        return ['transfer', class_exists('RaportParser') ? new RaportParser($fullPath, 'all') : null];
    }
    if (mb_stripos($filename, 'wewnetrzne_skanujace_porty') !== false) {
        return ['skanowanie_port_host_wew', class_exists('RaportWewnSkanujaceParser') ? new RaportWewnSkanujaceParser($fullPath) : null];
    }
    if (mb_stripos($filename, 'Hosty_z_bednymi_probami_logowania') !== false) {
        return ['host_logowanie', class_exists('RaportHostLogowanieParser') ? new RaportHostLogowanieParser($fullPath) : null];
    }
    if (mb_stripos($filename, 'zewnetrzne_skanujace_porty') !== false) {
        return ['skanowanie_port_host_zew', class_exists('RaportZewnSkanujaceParser') ? new RaportZewnSkanujaceParser($fullPath) : null];
    }
    if (mb_stripos($filename, 'Odrzucone_poaczenia_z_hostow_wewnetrznych') !== false) {
        return ['skanowanie_odrzucone_host_wew', class_exists('RaportOdrzuconeWewnParser') ? new RaportOdrzuconeWewnParser($fullPath) : null];
    }
    if (mb_stripos($filename, 'Odrzucone_poaczenia_z_hostow_zewnetrznych') !== false) {
        return ['skanowanie_odrzucone_host_zew', class_exists('RaportOdrzuconeZewnParser') ? new RaportOdrzuconeZewnParser($fullPath) : null];
    }
    if (mb_stripos($filename, 'Poaczenia_wychodzace') !== false) {
        return ['skanowanie_niestandardowe_porty', class_exists('RaportWychodzaceNiestandardoweParser') ? new RaportWychodzaceNiestandardoweParser($fullPath) : null];
    }
    if (mb_stripos($filename, 'Uzytkownicy_z_bednymi_probami_logowania') !== false) {
        return ['uzytkownicy_logowanie', class_exists('RaportBedneLogowaniaUzytkownicyParser') ? new RaportBedneLogowaniaUzytkownicyParser($fullPath) : null];
    }
    return ['unknown', null];
}

$dates = [];
if (file_exists($danePath)) {
    foreach (array_diff(scandir($danePath), ['.', '..']) as $folder) {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $folder) && is_dir($danePath . $folder)) {
            $dates[] = $folder;
        }
    }
}
rsort($dates);
$latestDate = $dates[0] ?? null;
$startDate = $latestDate ? date('Y-m-d', strtotime($latestDate . ' -' . ($period - 1) . ' days')) : null;

$filesParsed = 0;
$filesFailed = 0;
$totalEvents = 0;
$transferHosts = [];
$failedUsers = [];
$attackCountries = [];
$hourCounts = array_fill(0, 24, 0);
$portCounts = [];
$appCounts = [];
$serviceCounts = [];
$filesByType = [];

foreach ($dates as $date) {
    if ($startDate && $date < $startDate) continue;
    $dir = $danePath . $date . '/';
    foreach (array_diff(scandir($dir), ['.', '..']) as $file) {
        $fullPath = realpath($dir . $file);
        if (!$fullPath || !is_file($fullPath) || strtolower(pathinfo($fullPath, PATHINFO_EXTENSION)) !== 'html') continue;

        [$type, $parser] = socReportParserForFile($fullPath);
        if (!$parser || !method_exists($parser, 'parse')) continue;

        try {
            $data = $parser->parse();
            $filesParsed++;
            $filesByType[$type] = ($filesByType[$type] ?? 0) + 1;
        } catch (Throwable $e) {
            $filesFailed++;
            continue;
        }

        if ($type === 'transfer') {
            foreach (($data['top_hosts'] ?? []) as $host) {
                $name = socCleanLabel(($host['ip'] ?? '') . (!empty($host['opis']) ? ' — ' . $host['opis'] : ''), 'Host');
                $transferHosts[$name] = ($transferHosts[$name] ?? 0) + (float)($host['suma_raw'] ?? 0);
                foreach (($host['events'] ?? []) as $event) {
                    if (!empty($event['time']) && ($ts = strtotime($event['time']))) {
                        $hourCounts[(int)date('H', $ts)]++;
                    }
                }
                foreach (($host['uslugi'] ?? []) as $service) {
                    socAdd($serviceCounts, $service['nazwa'] ?? 'Nieznana', $service['zdarzenia'] ?? 1, 'Nieznana');
                }
                foreach (($host['aplikacje'] ?? []) as $app) {
                    socAdd($appCounts, $app['nazwa'] ?? 'Nieznana', $app['zdarzenia'] ?? 1, 'Nieznana');
                }
            }
            $eventsText = (string)($data['meta']['liczba_zdarzen'] ?? '0');
            $totalEvents += (int)preg_replace('/\D+/', '', $eventsText);
            continue;
        }

        if (isset($data['meta']['suma_zdarzen'])) {
            $totalEvents += (int)$data['meta']['suma_zdarzen'];
        }

        foreach (($data['records'] ?? []) as $record) {
            $events = max(1, (int)($record['events_count'] ?? 1));
            socAdd($failedUsers, $record['user'] ?? '-', $events, 'Użytkownik');
            socAdd($serviceCounts, $record['service_name'] ?? '-', $events, 'Nieznana');
            socMergeHourly($hourCounts, socHourlyFromItem($record));
        }

        foreach (($data['scans'] ?? []) as $scan) {
            $events = max(1, (int)($scan['event_value_count'] ?? $scan['eventmap_value_count'] ?? $scan['events_count'] ?? 1));

            socAddTerms($attackCountries, $scan['source_country_raw'] ?? ($scan['source_country'] ?? ''), $events, 'Nieznany', true);
            socAddTerms($portCounts, $scan['dest_port_raw'] ?? ($scan['dest_port'] ?? ''), $events, 'Dowolny', true);
            socAddTerms($serviceCounts, $scan['service_raw'] ?? ($scan['service'] ?? $scan['application_category'] ?? ''), $events, 'Nieznana', true);
            socAddTerms($appCounts, $scan['application_raw'] ?? ($scan['application'] ?? ''), $events, 'Nieznana', true);
            socMergeHourly($hourCounts, socHourlyFromItem($scan));
        }
    }
}

$topTransferHosts = socTop($transferHosts);
$topFailedUsers = socTop($failedUsers);
$topAttackCountries = socTop($attackCountries);
$topPorts = socTop($portCounts);
$topApps = socTop($appCounts);
$topServices = socTop($serviceCounts);
$topHoursRaw = [];
foreach ($hourCounts as $hour => $count) $topHoursRaw[sprintf('%02d:00', $hour)] = $count;
$topHours = socTop($topHoursRaw);

function chartPayload($items, $format = 'number') {
    return [
        'labels' => array_keys($items),
        'values' => array_values($items),
        'format' => $format
    ];
}

$charts = [
    'transferHosts' => chartPayload($topTransferHosts, 'mb'),
    'failedUsers' => chartPayload($topFailedUsers),
    'attackCountries' => chartPayload($topAttackCountries),
    'hours' => chartPayload($topHours),
    'ports' => chartPayload($topPorts),
    'apps' => chartPayload($topApps),
    'services' => chartPayload($topServices),
];

function renderTableRows($items, $unit = 'zd.', $isTransfer = false) {
    if (empty($items)) {
        echo '<tr><td colspan="4" class="px-4 py-6 text-center text-xs font-semibold text-slate-400">Brak danych</td></tr>';
        return;
    }

    $maxValue = max(array_map('floatval', array_values($items)));
    $sumValue = array_sum(array_map('floatval', array_values($items)));
    if ($maxValue <= 0) $maxValue = 1;
    if ($sumValue <= 0) $sumValue = 1;

    $i = 1;
    foreach ($items as $label => $value) {
        $numericValue = (float)$value;
        $percentOfTop = min(100, max(0, ($numericValue / $maxValue) * 100));
        $percentOfTotal = ($numericValue / $sumValue) * 100;
        $displayValue = $isTransfer ? socFormatMb($numericValue) : number_format((int)$numericValue, 0, ',', ' ') . ' ' . $unit;
        $safeLabel = htmlspecialchars($label, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        echo '<tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50/70 transition-colors">';
        echo '<td class="w-10 px-4 py-2.5 text-xs font-bold text-slate-400">' . $i++ . '</td>';
        echo '<td class="px-4 py-2.5 text-xs font-semibold text-slate-700">';
        echo '<div class="mb-1 flex items-center justify-between gap-3">';
        echo '<span class="line-clamp-1 max-w-[320px]" title="' . $safeLabel . '">' . $safeLabel . '</span>';
        echo '<span class="shrink-0 text-[10px] font-bold text-slate-400">' . number_format($percentOfTotal, 1, ',', ' ') . '%</span>';
        echo '</div>';
        echo '<div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">';
        echo '<div class="h-full rounded-full bg-gradient-to-r from-blue-500 via-indigo-500 to-violet-600 shadow-sm" style="width: ' . number_format($percentOfTop, 2, '.', '') . '%"></div>';
        echo '</div>';
        echo '</td>';
        echo '<td class="whitespace-nowrap px-4 py-2.5 text-right text-xs font-extrabold text-blue-700">' . htmlspecialchars($displayValue, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</td>';
        echo '</tr>';
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statystyki SOC - Logsign</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .chart-canvas-wrap { position: relative; height: 280px; width: 100%; }
        .chart-canvas-wrap canvas { display: block; width: 100% !important; height: 280px !important; }
        @media print { .no-print { display: none !important; } .chart-canvas-wrap { height: 260px; } .chart-canvas-wrap canvas { height: 260px !important; } }
    </style>
</head>
<body class="text-slate-800 antialiased">
<header class="sticky top-0 z-40 w-full border-b border-slate-200 bg-white/80 backdrop-blur-md">
    <div class="flex h-16 items-center justify-between px-6">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600 text-white shadow-md shadow-blue-200">
                <i data-lucide="pie-chart" class="h-6 w-6"></i>
            </div>
            <div>
                <h1 class="text-lg font-bold leading-none text-slate-900">Statystyki SOC z raportów Logsign</h1>
                <span class="font-mono text-xs font-medium text-slate-400">Zakres: ostatnie <?php echo (int)$period; ?> dni względem najnowszego raportu</span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="index.php" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                <i data-lucide="arrow-left" class="h-4 w-4"></i>
                Raporty
            </a>
            <a href="index.php#upload" onclick="event.preventDefault(); window.location.href='index.php';" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-500">
                <i data-lucide="upload-cloud" class="h-4 w-4"></i>
                Wgraj paczkę ZIP
            </a>
        </div>
    </div>
</header>

<main class="mx-auto max-w-[1700px] p-6 lg:p-8">
    <section class="mb-6 flex flex-col justify-between gap-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm lg:flex-row lg:items-center">
        <div>
            <h2 class="text-xl font-extrabold text-slate-950">Dashboard zbiorczy</h2>
            <p class="mt-1 text-sm text-slate-500">Agregacja ze wszystkich plików HTML w katalogu <span class="font-mono">/dane</span> dla wybranego zakresu.</p>
            <p class="mt-1 text-xs font-semibold text-slate-400">
                Najnowszy raport: <?php echo htmlspecialchars($latestDate ?? 'brak'); ?> · Start zakresu: <?php echo htmlspecialchars($startDate ?? 'brak'); ?>
            </p>
        </div>
        <div class="flex flex-wrap gap-2 no-print">
            <?php foreach ([3, 7, 30] as $p): ?>
                <a href="stats.php?period=<?php echo $p; ?>" class="rounded-xl px-4 py-2 text-sm font-bold transition <?php echo $period === $p ? 'bg-blue-600 text-white shadow-sm' : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50'; ?>">
                    Ostatnie <?php echo $p; ?> dni
                </a>
            <?php endforeach; ?>
            <a href="stats.php?period=<?php echo (int)$period; ?>&print=1" target="_blank" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-slate-800">
                <i data-lucide="file-down" class="h-4 w-4"></i> Eksportuj PDF
            </a>
        </div>
    </section>

    <section class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Przetworzone pliki</p><h3 class="mt-2 text-3xl font-black text-slate-900"><?php echo number_format($filesParsed, 0, ',', ' '); ?></h3></div>
        <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Łącznie zdarzeń</p><h3 class="mt-2 text-3xl font-black text-red-600"><?php echo number_format($totalEvents, 0, ',', ' '); ?></h3></div>
        <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Zakres danych</p><h3 class="mt-2 text-xl font-black text-blue-600"><?php echo htmlspecialchars(($startDate ?? '-') . ' → ' . ($latestDate ?? '-')); ?></h3></div>
        <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Błędy parsowania</p><h3 class="mt-2 text-3xl font-black text-amber-600"><?php echo number_format($filesFailed, 0, ',', ' '); ?></h3></div>
    </section>

    <?php if ($filesParsed === 0): ?>
        <div class="rounded-2xl border border-dashed border-slate-200 bg-white p-10 text-center shadow-sm">
            <i data-lucide="folder-search" class="mx-auto mb-3 h-10 w-10 text-slate-300"></i>
            <h3 class="text-lg font-bold text-slate-900">Brak danych do statystyk</h3>
            <p class="mt-2 text-sm text-slate-500">Wgraj raporty ZIP lub sprawdź, czy katalog <span class="font-mono">/dane/YYYY-MM-DD</span> zawiera pliki HTML.</p>
        </div>
    <?php else: ?>
        <section class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <div class="chart-box rounded-2xl border border-slate-100 bg-white p-6 shadow-sm"><h3 class="mb-4 text-sm font-extrabold uppercase tracking-wide text-slate-900">Top 10 hostów z największym transferem</h3><div class="chart-canvas-wrap"><canvas id="chart-transferHosts"></canvas></div><table class="mt-4 w-full"><tbody><?php renderTableRows($topTransferHosts, 'MB', true); ?></tbody></table></div>
            <div class="chart-box rounded-2xl border border-slate-100 bg-white p-6 shadow-sm"><h3 class="mb-4 text-sm font-extrabold uppercase tracking-wide text-slate-900">Top 10 użytkowników z błędnymi próbami logowania</h3><div class="chart-canvas-wrap"><canvas id="chart-failedUsers"></canvas></div><table class="mt-4 w-full"><tbody><?php renderTableRows($topFailedUsers, 'prób'); ?></tbody></table></div>
            <div class="chart-box rounded-2xl border border-slate-100 bg-white p-6 shadow-sm"><h3 class="mb-4 text-sm font-extrabold uppercase tracking-wide text-slate-900">Top 10 krajów źródłowych ataków</h3><div class="chart-canvas-wrap"><canvas id="chart-attackCountries"></canvas></div><table class="mt-4 w-full"><tbody><?php renderTableRows($topAttackCountries, 'zd.'); ?></tbody></table></div>
            <div class="chart-box rounded-2xl border border-slate-100 bg-white p-6 shadow-sm"><h3 class="mb-4 text-sm font-extrabold uppercase tracking-wide text-slate-900">Top 10 godzin z największą ilością zdarzeń</h3><div class="chart-canvas-wrap"><canvas id="chart-hours"></canvas></div><table class="mt-4 w-full"><tbody><?php renderTableRows($topHours, 'zd.'); ?></tbody></table></div>
            <div class="chart-box rounded-2xl border border-slate-100 bg-white p-6 shadow-sm"><h3 class="mb-4 text-sm font-extrabold uppercase tracking-wide text-slate-900">Top 10 portów</h3><div class="chart-canvas-wrap"><canvas id="chart-ports"></canvas></div><table class="mt-4 w-full"><tbody><?php renderTableRows($topPorts, 'zd.'); ?></tbody></table></div>
            <div class="chart-box rounded-2xl border border-slate-100 bg-white p-6 shadow-sm"><h3 class="mb-4 text-sm font-extrabold uppercase tracking-wide text-slate-900">Top 10 aplikacji</h3><div class="chart-canvas-wrap"><canvas id="chart-apps"></canvas></div><table class="mt-4 w-full"><tbody><?php renderTableRows($topApps, 'zd.'); ?></tbody></table></div>
            <div class="chart-box rounded-2xl border border-slate-100 bg-white p-6 shadow-sm xl:col-span-2"><h3 class="mb-4 text-sm font-extrabold uppercase tracking-wide text-slate-900">Top 10 usług</h3><div class="chart-canvas-wrap"><canvas id="chart-services"></canvas></div><table class="mt-4 w-full"><tbody><?php renderTableRows($topServices, 'zd.'); ?></tbody></table></div>
        </section>
    <?php endif; ?>
</main>

<script>
lucide.createIcons();
const chartData = <?php echo json_encode($charts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const palette = ['#2563eb', '#dc2626', '#16a34a', '#f59e0b', '#7c3aed', '#0891b2', '#db2777', '#65a30d', '#ea580c', '#475569'];

const percentageLabelsPlugin = {
    id: 'percentageLabelsPlugin',
    afterDatasetsDraw(chart) {
        const dataset = chart.data.datasets && chart.data.datasets[0];
        if (!dataset || !dataset.data || dataset.data.length === 0) return;
        const values = dataset.data.map(v => Number(v) || 0);
        const total = values.reduce((a, b) => a + b, 0);
        if (total <= 0) return;

        const ctx = chart.ctx;
        const meta = chart.getDatasetMeta(0);
        ctx.save();
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.font = '500 11px Inter, Arial, sans-serif';
        ctx.fillStyle = '#ffffff';
        ctx.shadowColor = 'rgba(15, 23, 42, 0.35)';
        ctx.shadowBlur = 2;
        ctx.shadowOffsetY = 1;

        meta.data.forEach((arc, index) => {
            const value = values[index];
            const percent = total > 0 ? (value / total) * 100 : 0;
            if (percent < 3) return;
            const point = arc.tooltipPosition();
            ctx.fillText(percent.toLocaleString('pl-PL', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + '%', point.x, point.y);
        });
        ctx.restore();
    }
};

function fallbackEmptyChart(el, text) {
    const parent = el.parentElement;
    if (!parent) return;
    parent.innerHTML = '<div class="flex h-full items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50 text-xs font-semibold text-slate-400">' + text + '</div>';
}

function makePie(id, payload) {
    const el = document.getElementById('chart-' + id);
    if (!el) return;
    if (!payload || !Array.isArray(payload.labels) || !Array.isArray(payload.values) || payload.labels.length === 0 || payload.values.length === 0) {
        fallbackEmptyChart(el, 'Brak danych do wykresu');
        return;
    }

    const labels = payload.labels.map(label => String(label));
    const values = payload.values.map(value => Number(value) || 0);
    const total = values.reduce((a, b) => a + b, 0);
    if (total <= 0) {
        fallbackEmptyChart(el, 'Brak danych do wykresu');
        return;
    }

    try {
        new Chart(el, {
            type: 'pie',
            data: {
                labels,
                datasets: [{
                    data: values,
                    backgroundColor: palette,
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverOffset: 8
                }]
            },
            plugins: [percentageLabelsPlugin],
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 650 },
                layout: { padding: 8 },
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 10,
                            boxHeight: 10,
                            padding: 12,
                            font: { size: 10, weight: '400' },
                            color: '#475569',
                            generateLabels(chart) {
                                const data = chart.data;
                                return data.labels.map((label, i) => {
                                    const value = Number(data.datasets[0].data[i]) || 0;
                                    const pct = total > 0 ? (value / total) * 100 : 0;
                                    const safeLabel = String(label).length > 34 ? String(label).slice(0, 31) + '…' : String(label);
                                    return {
                                        text: safeLabel + ' — ' + pct.toLocaleString('pl-PL', { maximumFractionDigits: 1 }) + '%',
                                        fillStyle: palette[i % palette.length],
                                        strokeStyle: '#ffffff',
                                        lineWidth: 1,
                                        hidden: false,
                                        index: i
                                    };
                                });
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.94)',
                        titleFont: { size: 12, weight: '600' },
                        bodyFont: { size: 12, weight: '400' },
                        padding: 10,
                        callbacks: {
                            label: function(ctx) {
                                const value = Number(ctx.raw || 0);
                                const percent = total > 0 ? (value / total) * 100 : 0;
                                const suffix = payload.format === 'mb' ? ' MB' : ' zd.';
                                const formattedValue = value.toLocaleString('pl-PL', { maximumFractionDigits: payload.format === 'mb' ? 1 : 0 });
                                const formattedPercent = percent.toLocaleString('pl-PL', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
                                return ' ' + ctx.label + ': ' + formattedValue + suffix + ' (' + formattedPercent + '%)';
                            }
                        }
                    }
                }
            }
        });
    } catch (error) {
        console.error('Błąd renderowania wykresu', id, error, payload);
        fallbackEmptyChart(el, 'Błąd renderowania wykresu');
    }
}

function renderAllCharts() {
    ['transferHosts', 'failedUsers', 'attackCountries', 'hours', 'ports', 'apps', 'services'].forEach(key => makePie(key, chartData[key]));
}

window.addEventListener('load', function() {
    renderAllCharts();
    if (new URLSearchParams(window.location.search).get('print') === '1') {
        setTimeout(() => window.print(), 900);
    }
});
</script>
</body>
</html>

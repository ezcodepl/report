<?php
ini_set('display_errors', 1);
//error_reporting(E_ALL);
/**
 * Główny plik Dashboardu aplikacji.
 * Odpowiada za wyświetlanie drzewa plików z katalogu /dane/, dynamiczną detekcję
 * typu wybranego raportu oraz renderowanie dedykowanego interfejsu (Transfer, Skanowanie lub Logowania).
 */

require_once __DIR__ . '/parser.php';
require_once __DIR__ . '/parser_skanowanie_wew.php';
require_once __DIR__ . '/parser_bledne_proby_logowania.php';
require_once __DIR__ . '/parser_skanowanie_zew.php';
require_once __DIR__ . '/parser_odrzuconych_polaczen_wew.php';
require_once __DIR__ . '/parser_odrzucownych_polaczen_zew.php'; // Uwzględnia literówki/specyfikę serwera
require_once __DIR__ . '/parser_polaczen_niestandardowe_porty.php';
require_once __DIR__ . '/parser_uzytkownicy_bledne_logowanie.php'; // Ładuje ulepszoną wersję parsera

$danePath = __DIR__ . '/dane/';
$selectedFile = isset($_GET['file']) ? $_GET['file'] : null;
$parsedData = null;
$reportType = 'transfer'; 

$filterDay = isset($_GET['filter_day']) ? $_GET['filter_day'] : 'all';
$activeIp = isset($_GET['active_ip']) ? $_GET['active_ip'] : '';

if (!function_exists('convertToMb')) {
    function convertToMb($valueString) {
        $valueString = str_replace(' ', '', $valueString);
        $val = (float)preg_replace('/[^0-9.]/', '', str_replace(',', '.', $valueString));
        if (stripos($valueString, 'GB') !== false) {
            return $val * 1024;
        } elseif (stripos($valueString, 'KB') !== false) {
            return $val / 1024;
        } elseif (stripos($valueString, 'B') !== false && stripos($valueString, 'MB') === false) {
            return $val / (1024 * 1024);
        }
        return $val;
    }
}

// Obsługa usuwania katalogu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_dir' && isset($_POST['dir_name'])) {
    $dirToDelete = $_POST['dir_name'];
    $targetDir = realpath($danePath . $dirToDelete);

    if ($targetDir && strpos($targetDir, realpath($danePath)) === 0 && is_dir($targetDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($targetDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            @$todo($fileinfo->getRealPath());
        }
        @rmdir($targetDir);

        header("Location: index.php?delete_success=" . urlencode($dirToDelete));
        exit;
    }
}

// Drzewo plików
$tree = [];
if (file_exists($danePath)) {
    $folders = array_diff(scandir($danePath), array('..', '.'));
    rsort($folders); 
    foreach ($folders as $folder) {
        if (is_dir($danePath . $folder)) {
            $files = array_diff(scandir($danePath . $folder), array('..', '.'));
            if (!empty($files)) {
                $tree[$folder] = $files;
            }
        }
    }
}

if (!$selectedFile && !empty($tree)) {
    $firstFolder = array_key_first($tree);
    $firstFile = reset($tree[$firstFolder]);
    $selectedFile = $firstFolder . '/' . $firstFile;
}

if ($selectedFile) {
    $fullPath = realpath($danePath . $selectedFile);
    if ($fullPath && strpos($fullPath, realpath($danePath)) === 0 && file_exists($fullPath)) {
        $filename = basename($selectedFile);

        if (mb_stripos($filename, 'transfer') !== false || mb_stripos($filename, 'transfe') !== false) {
            $reportType = 'transfer';
            $parser = new RaportParser($fullPath, $filterDay);
        } elseif (mb_stripos($filename, 'wewnetrzne_skanujace_porty') !== false) {
            $reportType = 'skanowanie';
            $parser = new RaportWewnSkanujaceParser($fullPath);
        } elseif (mb_stripos($filename, 'Hosty_z_bednymi_probami_logowania') !== false) {
            $reportType = 'skanowanie';
            $parser = new RaportBedneLogowaniaHostyParser($fullPath);
        } elseif (mb_stripos($filename, 'zewnetrzne_skanujace_porty') !== false) {
            $reportType = 'skanowanie';
            $parser = new RaportZewnSkanujaceParser($fullPath);
        } elseif (mb_stripos($filename, 'Odrzucone_poaczenia_z_hostow_wewnetrznych') !== false) {
            $reportType = 'skanowanie';
            $parser = new RaportOdrzuconeWewnParser($fullPath);
        } elseif (mb_stripos($filename, 'Odrzucone_poaczenia_z_hostow_zewnetrznych') !== false) {
            $reportType = 'skanowanie';
            $parser = new RaportOdrzuconeZewnParser($fullPath);
        } elseif (mb_stripos($filename, 'Poaczenia_wychodzace') !== false) {
            $reportType = 'skanowanie';
            $parser = new RaportWychodzaceNiestandardoweParser($fullPath);
        } elseif (mb_stripos($filename, 'Uzytkownicy_z_bednymi_probami_logowania') !== false) {
            // DETEKCJA NOWEGO INTEGRALNEGO WIDOKU LOGOWAŃ UŻYTKOWNIKÓW
            $reportType = 'uzytkownicy_logowanie';
            $parser = new RaportBedneLogowaniaUzytkownicyParser($fullPath);
        } else {
            $reportType = 'transfer';
            $parser = new RaportParser($fullPath, $filterDay);
        }

        $parsedData = $parser->parse();
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analityka Sieciowa - Panel Raportów</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        .table-container { max-height: 520px; overflow-y: auto; }
    </style>
</head>
<body class="text-slate-800 antialiased">

    <!-- Górny pasek nawigacyjny -->
    <header class="sticky top-0 z-40 w-full border-b border-slate-200 bg-white/80 backdrop-blur-md">
        <div class="flex h-16 items-center justify-between px-6">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600 text-white shadow-md shadow-blue-200">
                    <i data-lucide="shield-check" class="h-6 w-6"></i>
                </div>
                <div>
                    <h1 class="font-bold text-slate-900 leading-none text-lg">Raporty z alertów SOC system Logsign</h1>
                    <span class="text-xs text-slate-400 font-medium font-mono">Status: Aktywny</span>
                </div>
            </div>
            <button onclick="document.getElementById('upload-modal').classList.remove('hidden')" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 transition-colors">
                <i data-lucide="upload-cloud" class="h-4 w-4"></i>
                Wgraj paczkę ZIP
            </button>
        </div>
    </header>

    <div class="flex min-h-[calc(100vh-4rem)]">

        <!-- Drzewo Archiwum Raportów (Sidebar) -->
        <aside class="w-80 border-r border-slate-200 bg-white p-6 shrink-0 hidden md:block">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-4">Archiwum Raportów</h2>

            <?php if (empty($tree)): ?>
                <div class="rounded-xl border border-dashed border-slate-200 p-6 text-center">
                    <i data-lucide="folder-open" class="mx-auto h-8 w-8 text-slate-300 mb-2"></i>
                    <p class="text-sm text-slate-500 font-medium">Brak wgranych raportów.</p>
                </div>
            <?php else: ?>
                <div class="space-y-3 max-h-[calc(100vh-10rem)] overflow-y-auto pr-1">
                    <?php foreach ($tree as $date => $files): ?>
                        <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-2">
                            <div class="flex items-center justify-between p-2">
                                <button onclick="toggleFolder('folder-<?php echo $date; ?>')" class="flex items-center gap-2 font-semibold text-slate-700 hover:text-blue-600 text-sm">
                                    <i data-lucide="calendar" class="h-4 w-4 text-blue-500"></i>
                                    <?php echo $date; ?>
                                </button>
                                <div class="flex items-center gap-1.5">
                                    <button onclick="confirmDeleteDir('<?php echo htmlspecialchars($date); ?>')" class="rounded-lg p-1 text-slate-400 hover:bg-red-50 hover:text-red-600 transition-colors" title="Usuń ten katalog">
                                        <i data-lucide="trash-2" class="h-4 w-4"></i>
                                    </button>
                                    <i data-lucide="chevron-down" class="h-4 w-4 text-slate-400 transition-transform duration-200 cursor-pointer" id="icon-folder-<?php echo $date; ?>" onclick="toggleFolder('folder-<?php echo $date; ?>')"></i>
                                </div>
                            </div>

                            <div class="mt-1 space-y-1 pl-6 pr-2 pb-1" id="folder-<?php echo $date; ?>">
                                <?php foreach ($files as $file):
                                    $filePathValue = $date . '/' . $file;
                                    $isActive = ($selectedFile === $filePathValue);

                                    $isScanFile = true;
                                    if (mb_stripos($file, 'transfer') !== false || mb_stripos($file, 'transfe') !== false) {
                                        $isScanFile = false;
                                    }
                                ?>
                                    <a href="index.php?file=<?php echo urlencode($filePathValue); ?>" class="group flex items-center justify-between rounded-lg p-2 text-xs font-medium transition-all <?php echo $isActive ? 'bg-blue-50 text-blue-700' : 'text-slate-500 hover:bg-slate-100/80 hover:text-slate-900'; ?>">
                                        <span class="truncate pr-2" title="<?php echo htmlspecialchars($file); ?>">
                                            <?php echo htmlspecialchars(strlen($file) > 28 ? substr($file, 0, 25) . '...' : $file); ?>
                                        </span>
                                        <i data-lucide="<?php echo $isScanFile ? 'shield-alert' : 'file-text'; ?>" class="h-3.5 w-3.5 shrink-0 <?php echo $isScanFile ? 'text-red-500 opacity-100' : 'text-slate-400 opacity-60'; ?>"></i>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </aside>

        <!-- Główny obszar roboczy -->
        <main class="flex-1 p-8 overflow-y-auto">

            <?php if (isset($_GET['delete_success'])): ?>
                <div class="mb-6 flex items-center gap-3 rounded-xl bg-amber-50 border border-amber-200 p-4 text-amber-800 shadow-sm">
                    <i data-lucide="trash-2" class="h-5 w-5 text-amber-600"></i>
                    <div>
                        <span class="font-semibold">Usunięto!</span> Katalog o dacie <b><?php echo htmlspecialchars($_GET['delete_success']); ?></b> wraz z raportami został pomyślnie skasowany.
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['upload_success'])): ?>
                <div class="mb-6 flex items-center gap-3 rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-emerald-800 shadow-sm">
                    <i data-lucide="check-circle" class="h-5 w-5 text-emerald-600"></i>
                    <div>
                        <span class="font-semibold">Import ukończony!</span> Pomyślnie zaimportowano <?php echo intval($_GET['upload_success']); ?> raportów sieciowych.
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($parsedData): ?>

                <?php if ($reportType === 'transfer'): ?>
                    <!-- ========================================== -->
                    <!-- WIDOK 1: ANALITYKA TRANSFERU DOBOWEGO      -->
                    <!-- ========================================== -->
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
                        <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Suma Transferu</p>
                                    <h3 class="mt-2 text-2xl font-bold text-slate-900"><?php echo htmlspecialchars($parsedData['meta']['suma_transferu']?? ''); ?></h3>
                                </div>
                                <div class="rounded-xl bg-blue-50 p-3 text-blue-600">
                                    <i data-lucide="arrow-left-right" class="h-6 w-6"></i>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Dane Pobrane (RX)</p>
                                    <h3 class="mt-2 text-2xl font-bold text-emerald-600"><?php echo htmlspecialchars($parsedData['meta']['pobrane_rx']?? ''); ?></h3>
                                </div>
                                <div class="rounded-xl bg-emerald-50 p-3 text-emerald-600">
                                    <i data-lucide="download-cloud" class="h-6 w-6"></i>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Dane Wysłane (TX)</p>
                                    <h3 class="mt-2 text-2xl font-bold text-amber-600"><?php echo htmlspecialchars($parsedData['meta']['wyslane_tx']?? ''); ?></h3>
                                </div>
                                <div class="rounded-xl bg-amber-50 p-3 text-amber-600">
                                    <i data-lucide="upload-cloud" class="h-6 w-6"></i>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Liczba Zdarzeń (Suma)</p>
                                    <h3 class="mt-2 text-2xl font-bold text-violet-600"><?php echo htmlspecialchars($parsedData['meta']['liczba_zdarzen']?? ''); ?></h3>
                                </div>
                                <div class="rounded-xl bg-violet-50 p-3 text-violet-600">
                                    <i data-lucide="activity" class="h-6 w-6"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
                        <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
                            <div class="flex items-center gap-2 mb-6">
                                <span class="h-2.5 w-2.5 rounded-full bg-blue-600"></span>
                                <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wide">Top 5 Najaktywniejszych Hostów</h3>
                            </div>
                            <div class="space-y-4">
                                <?php
                                $top5Hosts = array_slice($parsedData['top_hosts'], 0, 5);
                                $maxHostTransfer = 0.001;
                                foreach ($top5Hosts as $h) {
                                    $mb = convertToMb($h['suma']);
                                    if ($mb > $maxHostTransfer) $maxHostTransfer = $mb;
                                }

                                if (empty($top5Hosts)): ?>
                                    <p class="text-xs text-slate-400 font-semibold py-4 text-center">Brak danych</p>
                                <?php else: ?>
                                    <?php foreach ($top5Hosts as $h):
                                        $currentMb = convertToMb($h['suma'] ?? 0);
                                        $percent = $maxHostTransfer > 0 ? min(100, ($currentMb / $maxHostTransfer) * 100) : 0;
                                        $ip = trim(preg_replace('/\s*\([^)]*\)/', '', $h['ip'] ?? '')) ?? 'unknown';
                                        $opis = $h['opis'] ?? '';
                                        $displayName = $ip;
                                    ?>
                                        <div>
                                            <div class="flex justify-between text-xs font-semibold text-slate-700 mb-1">
                                                <span class="font-mono text-slate-900 truncate max-w-[150px] inline-block" title="<?php echo htmlspecialchars($displayName); ?>"><?php echo htmlspecialchars($displayName); ?></span><span><?php echo htmlspecialchars($opis); ?></span>
                                                <span class="text-blue-600 font-bold"><?php echo htmlspecialchars($h['suma']?? 0); ?></span>
                                            </div>
                                            <div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                                                <div class="h-full bg-gradient-to-r from-blue-500 to-indigo-600 rounded-full transition-all duration-500" style="width: <?php echo $percent; ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
                            <div class="flex items-center gap-2 mb-6">
                                <span class="h-2.5 w-2.5 rounded-full bg-emerald-600"></span>
                                <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wide">Top 5 Kierunków Docelowych</h3>
                            </div>
                            <div class="space-y-4">
                                <?php
                                $top5Kierunki = array_slice($parsedData['selected_host']['kierunki'] ?? [], 0, 5);
                                if (empty($top5Kierunki)): ?>
                                    <div class="flex flex-col items-center justify-center py-8 text-slate-400">
                                        <i data-lucide="info" class="h-8 w-8 mb-2 opacity-60"></i>
                                        <span class="text-xs font-semibold">Brak danych docelowych IP</span>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($top5Kierunki as $k): ?>
                                        <div>
                                            <div class="flex justify-between text-xs font-semibold text-slate-700 mb-1">
                                                 <a href="<?php echo $k['whois_url']; ?>" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline flex items-center gap-1">
                                                        <?php echo htmlspecialchars($k['ip']); ?>
                                                        <i data-lucide="external-link" class="h-3 w-3 opacity-60"></i>
                                                    </a>
                                                <span class="text-emerald-600 font-bold"><?php echo htmlspecialchars($k['zdarzenia']); ?></span>
                                            </div>
                                            <div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                                                <div class="h-full bg-gradient-to-r from-emerald-500 to-teal-600 rounded-full transition-all duration-500" style="width: <?php echo $k['procent']; ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
                            <div class="flex items-center gap-2 mb-6">
                                <span class="h-2.5 w-2.5 rounded-full bg-rose-600"></span>
                                <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wide">Top 5 Krajów ze Zdarzeniami</h3>
                            </div>
                            <div class="space-y-4">
                                <?php
                                $top5Kraje = array_slice($parsedData['selected_host']['geolokalizacja'] ?? [], 0, 5);
                                if (empty($top5Kraje)): ?>
                                    <div class="flex flex-col items-center justify-center py-8 text-slate-400">
                                        <i data-lucide="globe" class="h-8 w-8 mb-2 opacity-60"></i>
                                        <span class="text-xs font-semibold">Brak danych</span>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($top5Kraje as $k): ?>
                                        <div>
                                            <div class="flex justify-between text-xs font-semibold text-slate-700 mb-1">
                                                <span>
                                                    <span class="text-slate-400 uppercase text-[10px] mr-1"><?php echo htmlspecialchars($k['prefiks']); ?></span>
                                                    <?php echo htmlspecialchars($k['kraj']); ?>
                                                </span>
                                                <span class="text-rose-600 font-bold"><?php echo htmlspecialchars($k['logi']); ?></span>
                                            </div>
                                            <div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                                                <div class="h-full bg-gradient-to-r from-rose-500 to-red-600 rounded-full transition-all duration-500" style="width: <?php echo $k['procent']; ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
                            <div class="flex items-center gap-2 mb-6">
                                <span class="h-2.5 w-2.5 rounded-full bg-amber-600"></span>
                                <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wide">Top 5 Usług i Aplikacji</h3>
                            </div>
                            <div class="space-y-4">
                                <?php
                                $top5Uslugi = array_slice($parsedData['selected_host']['uslugi'] ?? [], 0, 5);
                                if (empty($top5Uslugi)): ?>
                                    <div class="flex flex-col items-center justify-center py-8 text-slate-400">
                                        <i data-lucide="layers" class="h-8 w-8 mb-2 opacity-60"></i>
                                        <span class="text-xs font-semibold">Brak danych</span>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($top5Uslugi as $u): ?>
                                        <div>
                                            <div class="flex justify-between text-xs font-semibold text-slate-700 mb-1">
                                                <span class="rounded bg-slate-100 px-2 py-0.5 font-bold text-slate-700 font-mono text-[11px]"><?php echo htmlspecialchars($u['nazwa']); ?></span>
                                                <span class="text-amber-600 font-bold"><?php echo htmlspecialchars($u['zdarzenia']); ?></span>
                                            </div>
                                            <div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                                                <div class="h-full bg-gradient-to-r from-amber-500 to-orange-600 rounded-full transition-all duration-500" style="width: <?php echo $u['procent']; ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Tabela Hostów -->
                    <div class="mb-8 rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
                        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center mb-6">
                            <div>
                                <h3 class="text-base font-bold text-slate-950 flex items-center gap-2">
                                    <span class="h-2.5 w-2.5 rounded-full bg-blue-600"></span>
                                    Top Hosty o Największym Transferze Dobowym
                                </h3>
                                <p class="text-xs text-slate-400 mt-1">Lista najaktywniejszych adresów IP na podstawie dobowego transferu danych.</p>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-slate-100 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                        <th class="py-3 px-4 text-center">Pozycja</th>
                                        <th class="py-3 px-4">Adres IP źródłowy (Host)</th>
                                        <th class="py-3 px-4 text-right">Zdarzenia</th>
                                        <th class="py-3 px-4 text-right text-emerald-600 font-bold">Odebrane (RX)</th>
                                        <th class="py-3 px-4 text-right text-amber-600 font-bold">Wysłane (TX)</th>
                                        <th class="py-3 px-4 text-right font-bold">Łącznie (Suma)</th>
                                        <th class="py-3 px-4">Wykorzystanie Pasma</th>
                                        <th class="py-3 px-4 text-center">Akcja</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50 text-xs">
                                    <?php foreach ($parsedData['top_hosts'] as $index => $host): ?>
                                        <tr class="host-row hover:bg-slate-50/50 transition-colors <?php echo $index >= 5 ? 'hidden' : ''; ?>">
                                            <td class="py-3 px-4 text-center">
                                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full <?php echo $host['pozycja'] <= 3 ? 'bg-amber-50 text-amber-700 font-bold border border-amber-200' : 'bg-slate-100 text-slate-600'; ?>">
                                                    <?php echo $host['pozycja']; ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4">
                                                <div class="font-bold text-slate-900 font-mono"><?php echo htmlspecialchars($host['ip']); ?></div>
                                                <div class="text-[10px] text-slate-400 font-medium"><?php echo htmlspecialchars($host['opis']); ?></div>
                                            </td>
                                            <td class="py-3 px-4 text-right font-semibold text-slate-600"><?php echo htmlspecialchars($host['zdarzenia']); ?></td>
                                            <td class="py-3 px-4 text-right font-bold text-emerald-600"><?php echo htmlspecialchars($host['rx']); ?></td>
                                            <td class="py-3 px-4 text-right font-bold text-amber-500"><?php echo htmlspecialchars($host['tx']); ?></td>
                                            <td class="py-3 px-4 text-right font-bold text-slate-900"><?php echo htmlspecialchars($host['suma']); ?></td>
                                            <td class="py-3 px-4 w-44">
                                                <div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                                                    <div class="h-full bg-blue-600 rounded-full" style="width: <?php echo $host['procent_pasma']; ?>%"></div>
                                                </div>
                                            </td>
                                            <td class="py-3 px-4 text-center">
                                                <a href="index.php?file=<?php echo urlencode($selectedFile); ?>&filter_day=<?php echo urlencode($filterDay); ?>&active_ip=<?php echo urlencode($host['ip']); ?>" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-bold text-slate-600 shadow-3xs hover:bg-slate-50 hover:text-blue-600 hover:border-blue-200 transition-all">
                                                    <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                                                    Analizuj
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if (count($parsedData['top_hosts']) > 5): ?>
                            <div class="mt-4 text-center border-t border-slate-100 pt-4">
                                <button id="btn-show-more" onclick="showAllHostsRows()" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-600 shadow-xs hover:bg-slate-50 hover:text-blue-600 transition-all">
                                    <i data-lucide="chevrons-down" class="h-4 w-4"></i>
                                    Pokaż więcej (<?php echo count($parsedData['top_hosts']) - 5; ?>)
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- KARTA ANALITYCZNA Z TRANSFERU DOBOWEGO -->
                    <div class="mb-8 rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-6">
                            <div>
                                <h3 class="text-base font-bold text-slate-950">Karta Analityczna Wybranego Hosta</h3>
                                <p class="text-xs text-slate-400 mt-1">Szczegółowa korelacja ruchu, lokalizacji docelowych oraz rozkładu czasowego dla wybranego IP.</p>
                            </div>
                            <span class="rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-600">Wybrany: <?php echo htmlspecialchars($parsedData['selected_host']['ip'] ?? ''); ?></span>
                        </div>

                        <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                            <div class="lg:border-r lg:border-slate-100 lg:pr-8 flex flex-col justify-between">
                                <div class="space-y-5">
                                    <div class="leading-tight">
                                        <div class="flex items-center justify-between">
                                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Identyfikacja Hosta</span>
                                            <span class="rounded-md bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-600">Lokalny IP</span>
                                        </div>
                                        <div class="mt-2">
                                            <h4 class="text-xl font-bold text-slate-900 font-mono"><?php echo htmlspecialchars($parsedData['selected_host']['ip'] ?? ''); ?></h4>
                                            <p class="text-xs font-semibold text-slate-600"><?php echo htmlspecialchars($parsedData['selected_host']['nazwa'] ?? ''); ?></p>
                                            <span class="text-[11px] text-slate-400"><?php echo htmlspecialchars($parsedData['selected_host']['domena'] ?? ''); ?></span>
                                        </div>
                                    </div>

                                    <div>
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-2">Transfer</span>
                                        <div class="space-y-3">
                                            <div>
                                                <div class="flex justify-between text-xs font-semibold mb-1">
                                                    <span class="flex items-center gap-2 text-slate-600">
                                                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span> Pobieranie
                                                    </span>
                                                    <span class="text-emerald-600 font-bold"><?php echo number_format($parsedData['selected_host']['rx_raw'] ?? 0, 1) . ' MB'; ?></span>
                                                </div>
                                                <?php
                                                $rx = (float)($parsedData['selected_host']['rx_raw'] ?? 0);
                                                $tx = (float)($parsedData['selected_host']['tx_raw'] ?? 0);
                                                $total = $rx + $tx;
                                                $total = $total > 0 ? $total : 1;
                                                $rxPercent = ($rx / $total) * 100;
                                                $txPercent = ($tx / $total) * 100;
                                                ?>
                                                <div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                                                    <div class="h-full bg-emerald-500 rounded-full" style="width: <?php echo round($rxPercent, 1); ?>%"></div>
                                                </div>
                                            </div>

                                            <div>
                                                <div class="flex justify-between text-xs font-semibold mb-1">
                                                    <span class="flex items-center gap-2 text-slate-600">
                                                        <span class="h-2 w-2 rounded-full bg-amber-500"></span> Wysyłanie
                                                    </span>
                                                    <span class="text-amber-600 font-bold"><?php echo number_format($parsedData['selected_host']['tx_raw'] ?? 0, 1) . ' MB'; ?></span>
                                                </div>
                                                <div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                                                    <div class="h-full bg-amber-500 rounded-full" style="width: <?php echo round($txPercent, 1); ?>%"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-6 pt-4 border-t border-slate-100 text-xs text-slate-500 space-y-2">
                                        <div class="flex justify-between">
                                            <span>Suma transferu:</span>
                                            <span class="font-bold text-slate-800"><?php echo htmlspecialchars($parsedData['selected_host']['suma'] ?? '0 MB'); ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Zdarzenia:</span>
                                            <span class="font-bold text-slate-800"><?php echo htmlspecialchars($parsedData['selected_host']['zdarzenia'] ?? '0'); ?></span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span>System:</span>
                                            <span class="font-semibold text-blue-600 flex items-center gap-1">
                                                <i data-lucide="shield" class="h-3 w-3"></i>
                                                <?php echo htmlspecialchars($parsedData['meta']['urzadzenie']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="lg:border-r lg:border-slate-100 lg:px-4">
                                <div class="mb-4">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Kierunki Docelowe (IP)</span>
                                    </div>
                                    <p class="text-[10px] text-slate-400 mt-1">Kliknij w adres IP, aby sprawdzić informacje w serwisie WHOIS.</p>
                                </div>
                                <div class="space-y-2 max-h-64 overflow-y-auto pr-1 mb-6">
                                    <?php if (empty($parsedData['selected_host']['kierunki'])): ?>
                                        <div class="flex flex-col items-center justify-center py-12 text-slate-400 border border-dashed border-slate-150 rounded-2xl bg-slate-50/50">
                                            <i data-lucide="info" class="h-8 w-8 mb-2 opacity-60"></i>
                                            <span class="text-xs font-semibold">Brak danych</span>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($parsedData['selected_host']['kierunki'] as $kierunek): ?>
                                            <div class="text-xs py-1.5 border-b border-slate-50 font-mono">
                                                <div class="flex items-center justify-between font-bold mb-1">
                                                    <a href="<?php echo $kierunek['whois_url']; ?>" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline flex items-center gap-1">
                                                        <?php echo htmlspecialchars($kierunek['ip']); ?>
                                                        <i data-lucide="external-link" class="h-3 w-3 opacity-60"></i>
                                                    </a>
                                                    <span class="text-slate-700"><?php echo htmlspecialchars($kierunek['zdarzenia']); ?></span>
                                                </div>
                                                <div class="h-1.5 w-full rounded-full bg-slate-100 overflow-hidden">
                                                    <div class="h-full bg-blue-500 rounded-full" style="width: <?php echo $kierunek['procent']; ?>%"></div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="border-t border-slate-100 pt-4">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-3">Rozpoznane Aplikacje</span>
                                    <div class="space-y-3">
                                        <?php if (empty($parsedData['selected_host']['aplikacje'])): ?>
                                            <div class="flex flex-col items-center justify-center py-6 text-slate-400 border border-dashed border-slate-150 rounded-2xl bg-slate-50/50">
                                                <i data-lucide="layers" class="h-8 w-8 mb-2 opacity-60"></i>
                                                <span class="text-xs font-semibold">Brak danych</span>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($parsedData['selected_host']['aplikacje'] as $ap): ?>
                                                <div>
                                                    <div class="flex justify-between text-xs font-semibold mb-1">
                                                        <span class="rounded bg-slate-100 px-2 py-0.5 font-bold text-slate-700 font-mono text-[11px]"><?php echo htmlspecialchars($ap['nazwa']); ?></span>
                                                        <span class="text-slate-600 font-bold"><?php echo htmlspecialchars($ap['zdarzenia']); ?></span>
                                                    </div>
                                                    <div class="h-1.5 w-full rounded-full bg-slate-100 overflow-hidden">
                                                        <div class="h-full bg-gradient-to-r from-amber-500 to-orange-600 rounded-full transition-all duration-500" style="width: <?php echo $ap['procent']; ?>%"></div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="lg:pl-4 space-y-6">
                                <div>
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-3">Geolokalizacja (Kraje)</span>
                                    <div class="space-y-3">
                                        <?php if (empty($parsedData['selected_host']['geolokalizacja'])): ?>
                                            <div class="flex flex-col items-center justify-center py-6 text-slate-400 border border-dashed border-slate-150 rounded-2xl bg-slate-50/50">
                                                <i data-lucide="globe" class="h-8 w-8 mb-2 opacity-60"></i>
                                                <span class="text-xs font-semibold">Brak danych</span>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($parsedData['selected_host']['geolokalizacja'] as $krajData): ?>
                                                <div>
                                                    <div class="flex justify-between text-xs font-semibold mb-1">
                                                        <span class="text-slate-600 font-bold">
                                                            <span class="text-slate-400 font-normal uppercase mr-1"><?php echo htmlspecialchars($krajData['prefiks']); ?></span>
                                                            <?php echo htmlspecialchars($krajData['kraj']); ?>
                                                        </span>
                                                        <span class="text-slate-800 font-bold"><?php echo htmlspecialchars($krajData['logi']); ?></span>
                                                    </div>
                                                    <div class="h-1.5 w-full rounded-full bg-slate-100 overflow-hidden">
                                                        <div class="h-full bg-gradient-to-r from-rose-500 to-red-600 rounded-full transition-all duration-500" style="width: <?php echo $krajData['procent']; ?>%"></div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="border-t border-slate-100 pt-4">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-3">Rozpoznane Usługi (Protokoły)</span>
                                    <div class="space-y-3">
                                        <?php if (empty($parsedData['selected_host']['uslugi'])): ?>
                                            <div class="flex flex-col items-center justify-center py-6 text-slate-400 border border-dashed border-slate-150 rounded-2xl bg-slate-50/50">
                                                <i data-lucide="layers" class="h-8 w-8 mb-2 opacity-60"></i>
                                                <span class="text-xs font-semibold">Brak danych</span>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($parsedData['selected_host']['uslugi'] as $usluga): ?>
                                                <div>
                                                    <div class="flex justify-between text-xs font-semibold mb-1">
                                                        <span class="rounded bg-slate-100 px-2 py-0.5 font-bold text-slate-700 font-mono text-[11px]"><?php echo htmlspecialchars($usluga['nazwa']); ?></span>
                                                        <span class="text-slate-600 font-bold"><?php echo htmlspecialchars($usluga['zdarzenia']); ?></span>
                                                    </div>
                                                    <div class="h-1.5 w-full rounded-full bg-slate-100 overflow-hidden">
                                                        <div class="h-full bg-indigo-500 rounded-full" style="width: <?php echo $usluga['procent']; ?>%"></div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ROZKŁAD CZASOWY ZDARZEŃ (WIDOK HEATMAPY GODZINOWEJ DLA TRANSFERU DOBOWEGO) -->
                    <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
                        <h3 class="text-base font-bold text-slate-950 mb-4">
                            Rozkład czasowy zdarzeń (Aktywność dobowo-godzinowa)
                        </h3>
                        <div class="grid grid-cols-4 gap-3 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-12">
                            <?php
                            $hours = $parsedData['selected_host']['rozkład_godzinowy'] ?? [];
                            $logiArray = array_column($hours, 'logi');
                            $maxLogi = !empty($logiArray) ? max($logiArray) : 1;

                            foreach ($hours as $godzina):
                                $logCount = intval($godzina['logi']);
                                $intensity = $maxLogi > 0 ? ($logCount / $maxLogi) * 100 : 0;

                                if ($intensity >= 85) {
                                    $bgClass = 'bg-blue-900 text-white border-blue-950';
                                } elseif ($intensity >= 70) {
                                    $bgClass = 'bg-blue-700 text-white border-blue-800';
                                } elseif ($intensity >= 50) {
                                    $bgClass = 'bg-blue-500 text-white border-blue-600';
                                } elseif ($intensity >= 30) {
                                    $bgClass = 'bg-blue-300 text-blue-950 border-blue-400';
                                } elseif ($intensity >= 15) {
                                    $bgClass = 'bg-blue-100 text-blue-900 border-blue-200';
                                } else {
                                    $bgClass = 'bg-slate-50 text-slate-500 border-slate-200';
                                }
                            ?>
                                <div class="rounded-xl p-3 text-center border shadow-sm transition-all duration-200 hover:scale-105 flex flex-col justify-between items-center min-h-[75px] <?php echo $bgClass; ?>">
                                    <span class="text-[9px] font-semibold uppercase tracking-wider opacity-85">
                                        <?php echo htmlspecialchars($godzina['godzina']); ?>
                                    </span>
                                    <span class="text-xs font-bold mt-1">
                                        <?php echo htmlspecialchars($godzina['logi']); ?> zd.
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                <?php elseif ($reportType === 'uzytkownicy_logowanie'): ?>
                    <!-- ======================================================== -->
                    <!-- WIDOK 3: NOWOCZESNY RAPORT BŁĘDNYCH PRÓB LOGOWANIA      -->
                    <!-- ======================================================== -->
                    
                    <!-- KARTY STATYSTYK (KPI) -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-5 mb-8">
                        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center space-x-4">
                            <div class="p-3.5 bg-red-50 text-red-600 rounded-xl">
                                <i data-lucide="activity" class="w-6 h-6"></i>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Suma prób</p>
                                <p id="kpi-total-attempts" class="text-2xl font-bold text-slate-900 mt-1">--</p>
                            </div>
                        </div>

                        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center space-x-4">
                            <div class="p-3.5 bg-blue-50 text-blue-600 rounded-xl">
                                <i data-lucide="users" class="w-6 h-6"></i>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Unikalni Użytkownicy</p>
                                <p id="kpi-unique-users" class="text-2xl font-bold text-slate-900 mt-1">--</p>
                            </div>
                        </div>

                        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center space-x-4">
                            <div class="p-3.5 bg-orange-50 text-orange-600 rounded-xl">
                                <i data-lucide="globe" class="w-6 h-6"></i>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Unikalne IP Źródłowe</p>
                                <p id="kpi-unique-ips" class="text-2xl font-bold text-slate-900 mt-1">--</p>
                            </div>
                        </div>

                        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center space-x-4">
                            <div class="p-3.5 bg-purple-50 text-purple-600 rounded-xl">
                                <i data-lucide="server" class="w-6 h-6"></i>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Główny Cel Ataku</p>
                                <p id="kpi-top-dest" class="text-md font-bold text-slate-900 mt-1 truncate max-w-[180px]">--</p>
                            </div>
                        </div>
                    </div>

                    <!-- SEKCJA TOP 5 LIST -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                            <div class="flex items-center space-x-2 mb-4 pb-2 border-b border-slate-100">
                                <i data-lucide="user-x" class="w-5 h-5 text-red-500"></i>
                                <h3 class="font-bold text-slate-900 text-sm">Top 5 Użytkowników</h3>
                            </div>
                            <div id="top-users-list" class="space-y-4"></div>
                        </div>

                        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                            <div class="flex items-center space-x-2 mb-4 pb-2 border-b border-slate-100">
                                <i data-lucide="cpu" class="w-5 h-5 text-indigo-500"></i>
                                <h3 class="font-bold text-slate-900 text-sm">Top 5 Usług (Protokołów)</h3>
                            </div>
                            <div id="top-services-list" class="space-y-4"></div>
                        </div>

                        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                            <div class="flex items-center space-x-2 mb-4 pb-2 border-b border-slate-100">
                                <i data-lucide="network" class="w-5 h-5 text-orange-500"></i>
                                <h3 class="font-bold text-slate-900 text-sm">Top 5 IP Source</h3>
                            </div>
                            <div id="top-ips-list" class="space-y-4"></div>
                        </div>
                    </div>

                    <!-- KARTA ANALITYCZNA WYBRANEGO HOSTA (Zaimplementowana dynamicznie w JS) -->
                    <div id="host-analysis-block" class="mb-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition-all duration-300">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-100 pb-4 mb-6 gap-4">
                            <div>
                                <h3 class="text-base font-bold text-slate-950">Karta Analityczna Wybranego Hosta (Próby Logowań)</h3>
                                <p class="text-xs text-slate-400 mt-1">Szczegółowa korelacja ruchu, profilu zachowań oraz ukierunkowanych kont dla wybranego IP.</p>
                            </div>
                            <div class="flex items-center space-x-3">
                                <span id="selected-host-badge" class="rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-600">
                                    Wybrany: Wszystkie hosty (Wybierz z tabeli poniżej)
                                </span>
                            </div>
                        </div>

                        <div id="analysis-card-content" class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                            <!-- Identyfikacja i Reputacja -->
                            <div class="lg:border-r lg:border-slate-100 lg:pr-8 flex flex-col justify-between">
                                <div class="space-y-4">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Identyfikacja i Śledztwo</span>
                                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                                        <h4 id="analysis-ip-address" class="text-lg font-bold text-slate-900 font-mono">0.0.0.0</h4>
                                        <p id="analysis-hostname" class="text-xs font-semibold text-slate-500 mt-1">Host: brak</p>
                                    </div>
                                    <div class="pt-2">
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-2">Zewnętrzne bazy reputacyjne (IP)</span>
                                        <div class="flex flex-wrap gap-1.5" id="analysis-external-links">
                                            <!-- Generowane przez JS -->
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-6 pt-4 border-t border-slate-100 text-xs text-slate-500 space-y-2">
                                    <div class="flex justify-between">
                                        <span>Zarejestrowane próby (łącznie):</span>
                                        <span id="analysis-total-attempts" class="font-bold text-slate-800">0</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Główny target (Konto):</span>
                                        <span id="analysis-primary-target" class="font-bold text-red-600">brak</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Celowane Nazwy Kont (Użytkownicy) -->
                            <div class="lg:border-r lg:border-slate-100 lg:px-4">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-3">Targetowane Nazwy Użytkowników</span>
                                <div id="analysis-targeted-users" class="space-y-3 max-h-56 overflow-y-auto pr-1">
                                    <!-- Generowane przez JS -->
                                </div>
                            </div>

                            <!-- Targetowane Serwery / Cele oraz Usługi -->
                            <div class="lg:pl-4 space-y-5">
                                <div>
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-3">Obierane cele docelowe (IP / Host)</span>
                                    <div id="analysis-destinations" class="space-y-3 max-h-28 overflow-y-auto pr-1">
                                        <!-- Generowane przez JS -->
                                    </div>
                                </div>
                                <div class="border-t border-slate-100 pt-4">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-3">Wykorzystywane protokoły / usługi</span>
                                    <div id="analysis-services" class="space-y-3 max-h-24 overflow-y-auto pr-1">
                                        <!-- Generowane przez JS -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TABELA GŁÓWNA (Top 10 z opcją Pokaż Więcej) -->
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-8">
                        <!-- Panel filtrowania -->
                        <div class="p-6 border-b border-slate-200 space-y-4 bg-slate-50/50">
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <div class="relative flex-1">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                        <i data-lucide="search" class="w-4 h-4"></i>
                                    </span>
                                    <input type="text" id="table-search" onkeyup="filterTable()" placeholder="Szukaj po użytkowniku, IP, hostach lub usłudze..." class="w-full pl-9 pr-4 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 focus:outline-none">
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button onclick="setFilterPreset('all')" class="preset-btn px-3.5 py-1.5 text-xs font-medium rounded-lg border border-blue-200 bg-blue-50 text-blue-700 transition">Wszystko</button>
                                    <button onclick="setFilterPreset('deny')" class="preset-btn px-3.5 py-1.5 text-xs font-medium rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 transition">Status: Deny / Blokada</button>
                                    <button onclick="exportToCSV()" class="inline-flex items-center space-x-1 px-3.5 py-1.5 text-xs font-medium rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition ml-auto">
                                        <i data-lucide="download" class="w-3.5 h-3.5"></i>
                                        <span>Eksport CSV</span>
                                    </button>
                                </div>
                            </div>
                            <div class="text-xs text-slate-400 flex items-center justify-between">
                                <div>Tabela wyświetla nieudane próby uwierzytelniania w kolejności chronologicznej.</div>
                                <div id="filter-stats" class="font-medium text-slate-600">Wyświetlono: -- / -- rekordów</div>
                            </div>
                        </div>

                        <!-- Kontener tabeli -->
                        <div class="overflow-x-auto table-container">
                            <table class="w-full text-left border-collapse" id="logs-table">
                                <thead>
                                    <tr class="bg-slate-100 border-b border-slate-200 sticky top-0 z-10">
                                        <th class="px-5 py-3.5 text-xs font-bold text-slate-700 tracking-wider">Source.UserName</th>
                                        <th class="px-5 py-3.5 text-xs font-bold text-slate-700 tracking-wider">Source.IP (Term)</th>
                                        <th class="px-5 py-3.5 text-xs font-bold text-slate-700 tracking-wider">Source.HostName (Term)</th>
                                        <th class="px-5 py-3.5 text-xs font-bold text-slate-700 tracking-wider">Destination.IP (Term)</th>
                                        <th class="px-5 py-3.5 text-xs font-bold text-slate-700 tracking-wider">Destination.HostName (Term)</th>
                                        <th class="px-5 py-3.5 text-xs font-bold text-slate-700 tracking-wider">EventMap.SubType (Term)</th>
                                        <th class="px-5 py-3.5 text-xs font-bold text-slate-700 tracking-wider">Time.Generated (Term)</th>
                                        <th class="px-5 py-3.5 text-xs font-bold text-slate-700 tracking-wider">EventSource.Description (Term)</th>
                                       
                                        <th class="px-5 py-3.5 text-xs font-bold text-slate-700 tracking-wider text-center">Akcja</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-xs font-medium text-slate-600" id="table-body"></tbody>
                            </table>
                        </div>

                        <!-- Dolny obszar tabeli z przyciskiem Pokaż Więcej -->
                        <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-4">
                            <button id="toggle-rows-btn" onclick="toggleTableRows()" class="inline-flex items-center space-x-2 px-5 py-2.5 bg-white border border-slate-200 text-slate-700 text-xs font-semibold rounded-lg shadow-sm hover:bg-slate-50 transition">
                                <span id="toggle-rows-text">Pokaż więcej</span>
                                <i id="toggle-rows-icon" data-lucide="chevron-down" class="w-4 h-4"></i>
                            </button>
                            <div class="text-xs text-slate-500 font-medium">
                                Pokazano: <span id="displayed-rows-count" class="text-slate-900 font-bold">10</span> z <span id="total-rows-count" class="text-slate-900 font-bold">--</span> rekordów.
                            </div>
                        </div>
                    </div>

                    <!-- ROZKŁAD GODZINOWY LOGOWAŃ (HEATMAPA) -->
                    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                        <div class="flex items-center justify-between mb-4 pb-2 border-b border-slate-100">
                            <div class="flex items-center space-x-2">
                                <i data-lucide="clock" class="w-5 h-5 text-indigo-600"></i>
                                <h3 id="heatmap-title" class="font-bold text-slate-900 text-sm">Dobowy rozkład godzinowy prób logowania (Wszystkie)</h3>
                            </div>
                            <div class="flex items-center space-x-4">
                                <button id="reset-heatmap-filter-btn" onclick="selectHost(null)" class="hidden text-xs font-bold text-blue-600 hover:text-blue-800 transition">
                                    Pokaż dla wszystkich
                                </button>
                                <div class="flex items-center space-x-2 text-[10px] text-slate-400">
                                    <span>Mniej</span>
                                    <span class="w-3 h-3 bg-indigo-50 rounded border border-indigo-100"></span>
                                    <span class="w-3 h-3 bg-indigo-300 rounded"></span>
                                    <span class="w-3 h-3 bg-indigo-600 rounded"></span>
                                    <span class="w-3 h-3 bg-indigo-900 rounded"></span>
                                    <span>Więcej</span>
                                </div>
                            </div>
                        </div>
                        <p class="text-xs text-slate-500 mb-6">
                            Wizualizacja natężenia logowań w ujęciu 24-godzinnym. Im ciemniejsza barwa kafelka, tym więcej prób logowań zarejestrowano w tej godzinie.
                        </p>
                        <div id="hourly-heatmap-grid" class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-12 gap-3"></div>
                    </div>

                    <!-- WSTRZYKNIĘCIE DANYCH SERWEROWYCH DO INTERFEJSU JAVASCRIPT -->
                    <script>
                        const activeData = <?php echo json_encode($parsedData['records'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); ?>;
                        let showAllRows = false;
                        let selectedHostIp = null; // Przechowuje aktualnie analizowany adres IP

                        // Funkcja pomocnicza parsująca sumę wystąpień np. "veeam (44)" -> { val: "veeam", count: 44 }
                        function parseValueWithCount(str) {
                            if (!str) return { val: "-", count: 0 };
                            const regex = /([^(]+)\s*\((\d+)\)/;
                            const match = str.match(regex);
                            if (match) {
                                return { val: match[1].trim(), count: parseInt(match[2], 10) };
                            }
                            return { val: str.trim(), count: 1 };
                        }

                        // Inicjalizacja widoku
                        window.addEventListener('DOMContentLoaded', () => {
                            // Jako domyślny host na start wybieramy ten z największą liczbą prób
                            if (activeData && activeData.length > 0) {
                                const ipCounts = {};
                                activeData.forEach(row => {
                                    const sourceIp = parseValueWithCount(row.sourceIp);
                                    const user = parseValueWithCount(row.user);
                                    if (sourceIp.val && sourceIp.val !== '-') {
                                        ipCounts[sourceIp.val] = (ipCounts[sourceIp.val] || 0) + user.count;
                                    }
                                });
                                const sortedIps = Object.entries(ipCounts).sort((a,b) => b[1]-a[1]);
                                if (sortedIps.length > 0) {
                                    selectedHostIp = sortedIps[0][0];
                                }
                            }
                            renderAll();
                        });

                        function renderAll() {
                            renderTable(activeData);
                            updateKPIs(activeData);
                            renderTop5Lists(activeData);
                            renderHourlyHeatmap(activeData);
                            renderHostAnalysisCard(selectedHostIp);
                        }

                        function renderTable(data) {
                            const tbody = document.getElementById('table-body');
                            tbody.innerHTML = '';
                            const totalCount = data.length;
                            document.getElementById('total-rows-count').innerText = totalCount;

                            if (totalCount === 0) {
                                tbody.innerHTML = `
                                    <tr>
                                        <td colspan="11" class="px-5 py-8 text-center text-slate-400">Brak danych</td>
                                    </tr>
                                `;
                                document.getElementById('displayed-rows-count').innerText = 0;
                                return;
                            }

                            const limit = showAllRows ? totalCount : 10;
                            const displayedData = data.slice(0, limit);
                            document.getElementById('displayed-rows-count').innerText = displayedData.length;

                            const btnText = document.getElementById('toggle-rows-text');
                            const btnIcon = document.getElementById('toggle-rows-icon');
                            if (showAllRows) {
                                btnText.innerText = "Zwiń listę";
                                btnIcon.setAttribute('data-lucide', 'chevron-up');
                            } else {
                                btnText.innerText = `Pokaż więcej (${totalCount - displayedData.length} ukrytych)`;
                                btnIcon.setAttribute('data-lucide', 'chevron-down');
                            }

                            displayedData.forEach(row => {
                                const tr = document.createElement('tr');
                                // Wyróżnienie aktualnie wybranego wiersza
                                const sourceIp = parseValueWithCount(row.sourceIp);
                                const isSelected = (selectedHostIp !== null && sourceIp.val === selectedHostIp);
                                tr.className = `hover:bg-slate-50 transition border-b border-slate-100 ${isSelected ? 'bg-blue-50/55' : ''}`;

                                const user = parseValueWithCount(row.user);
                                const sourceHost = parseValueWithCount(row.sourceHost);
                                const destIp = parseValueWithCount(row.destIp);
                                const destHost = parseValueWithCount(row.destHost);
                                const subType = parseValueWithCount(row.subType);
                                const description = parseValueWithCount(row.description);
                                const eventSourceIp = parseValueWithCount(row.eventSourceIp);
                                const serviceName = parseValueWithCount(row.serviceName);

                                // Render dat generowanych w nowej linii
                                const dateRows = row.timeGenerated.split('\n').filter(d => d.trim().length > 0);
                                let dateHtml = '';
                                dateRows.forEach(dr => {
                                    const parsedDate = parseValueWithCount(dr);
                                    dateHtml += `
                                        <div class="flex items-center justify-between space-x-2 py-0.5 border-b border-slate-100 last:border-0 font-mono text-[10px]">
                                            <span class="text-slate-700">${parsedDate.val}</span>
                                            <span class="bg-slate-100 text-slate-600 px-1 py-0.1 rounded text-[9px] font-bold">x${parsedDate.count}</span>
                                        </div>
                                    `;
                                });

                                const isDeny = subType.val.toLowerCase().includes('deny') || subType.val.toLowerCase().includes('block');
                                const badgeColor = isDeny ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-amber-50 text-amber-700 border-amber-200';

                                tr.innerHTML = `
                                    <td class="px-5 py-3.5 font-semibold text-slate-900">
                                        <div class="flex items-center space-x-1.5">
                                            <span class="w-2 h-2 rounded-full ${isDeny ? 'bg-red-500' : 'bg-orange-500'}"></span>
                                            <span>${user.val}</span>
                                            <span class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.2 rounded-full font-bold">(${user.count})</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5 font-mono text-xs font-semibold text-slate-900">
                                        <button onclick="selectHost('${sourceIp.val}')" class="hover:underline hover:text-blue-600 focus:outline-none">
                                            ${sourceIp.val}
                                        </button>
                                        <span class="text-slate-400 font-normal">(${sourceIp.count})</span>
                                    </td>
                                    <td class="px-5 py-3.5 text-slate-500">${sourceHost.val}</td>
                                    <td class="px-5 py-3.5 font-mono text-xs">${destIp.val}</td>
                                    <td class="px-5 py-3.5 text-slate-500">${destHost.val}</td>
                                    <td class="px-5 py-3.5">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold border ${badgeColor}">
                                            ${subType.val}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap">
                                        <div class="bg-slate-50/50 p-1.5 rounded-lg border border-slate-200 max-h-24 overflow-y-auto">${dateHtml}</div>
                                    </td>
                                    <td class="px-5 py-3.5 text-slate-500 max-w-xs truncate" title="${description.val}">${description.val}</td>
                                    
                                    <td class="px-5 py-3.5 text-center">
                                        <button onclick="selectHost('${sourceIp.val}')" class="inline-flex items-center gap-1.5 rounded-lg border ${isSelected ? 'border-blue-300 bg-blue-50 text-blue-700 shadow-sm' : 'border-slate-200 bg-white hover:bg-slate-50 hover:text-blue-600 hover:border-blue-200 text-slate-600'} px-3 py-2 text-xs font-bold transition-all">
                                            <i data-lucide="${isSelected ? 'check-circle-2' : 'eye'}" class="h-4 w-4"></i>
                                            <span>${isSelected ? 'Analizowany' : 'Analizuj'}</span>
                                        </button>
                                    </td>
                                `;
                                tbody.appendChild(tr);
                            });
                            updateFilterStats(displayedData.length, totalCount);
                            lucide.createIcons();
                        }

                        // Funkcja wywoływana przy wyborze hosta do analizy
                        function selectHost(ip) {
                            selectedHostIp = ip;
                            renderTable(activeData);
                            renderHostAnalysisCard(ip);
                            renderHourlyHeatmap(activeData);

                            // Płynne przewinięcie ekranu do góry do sekcji analizy
                            if (ip) {
                                document.getElementById('host-analysis-block').scrollIntoView({ behavior: 'smooth', block: 'start' });
                            }
                        }

                        function renderHostAnalysisCard(ip) {
                            const badge = document.getElementById('selected-host-badge');
                            const resetBtn = document.getElementById('reset-heatmap-filter-btn');

                            if (!ip) {
                                badge.innerText = "Wybrany: Wszystkie hosty (Wykres zbiorczy)";
                                badge.className = "rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600";
                                resetBtn.classList.add('hidden');

                                document.getElementById('analysis-ip-address').innerText = "Wszyscy użytkownicy";
                                document.getElementById('analysis-hostname').innerText = "Statystyki zbiorcze";
                                document.getElementById('analysis-total-attempts').innerText = document.getElementById('kpi-total-attempts').innerText;
                                document.getElementById('analysis-primary-target').innerText = "zbiorcze";
                                document.getElementById('analysis-targeted-users').innerHTML = `<p class="text-xs text-slate-400 py-4 text-center">Wybierz konkretnego hosta z tabeli poniżej, aby załadować szczegóły.</p>`;
                                document.getElementById('analysis-destinations').innerHTML = `<p class="text-xs text-slate-400 text-center py-2">Wybierz hosta</p>`;
                                document.getElementById('analysis-services').innerHTML = `<p class="text-xs text-slate-400 text-center py-2">Wybierz hosta</p>`;
                                document.getElementById('analysis-external-links').innerHTML = ``;
                                return;
                            }

                            badge.innerText = `Wybrany: Host ${ip}`;
                            badge.className = "rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-600 animate-pulse";
                            resetBtn.classList.remove('hidden');

                            // Filtrowanie rekordów dla tego IP
                            const records = activeData.filter(row => parseValueWithCount(row.sourceIp).val === ip);

                            let totalAttempts = 0;
                            let detectedHostname = 'Brak nazwy (DHCP)';
                            const userStats = {};
                            const destStats = {};
                            const serviceStats = {};

                            records.forEach(row => {
                                const u = parseValueWithCount(row.user);
                                const sourceHost = parseValueWithCount(row.sourceHost);
                                const destHost = parseValueWithCount(row.destHost);
                                const destIp = parseValueWithCount(row.destIp);
                                const service = parseValueWithCount(row.serviceName);

                                if (sourceHost.val && sourceHost.val !== '-') {
                                    detectedHostname = sourceHost.val;
                                }

                                const dateRows = row.timeGenerated.split('\n').filter(d => d.trim().length > 0);
                                dateRows.forEach(dr => {
                                    totalAttempts += parseValueWithCount(dr).count;
                                });

                                if (u.val) {
                                    userStats[u.val] = (userStats[u.val] || 0) + u.count;
                                }

                                const target = (destHost.val && destHost.val !== '-') ? `${destHost.val} (${destIp.val})` : destIp.val;
                                if (target) {
                                    destStats[target] = (destStats[target] || 0) + u.count;
                                }

                                if (service.val) {
                                    serviceStats[service.val] = (serviceStats[service.val] || 0) + u.count;
                                }
                            });

                            // Uzupełnienie danych w karcie
                            document.getElementById('analysis-ip-address').innerText = ip;
                            document.getElementById('analysis-hostname').innerText = `Host: ${detectedHostname}`;
                            document.getElementById('analysis-total-attempts').innerText = totalAttempts.toLocaleString();

                            // Odnośniki śledcze
                            document.getElementById('analysis-external-links').innerHTML = `
                                <a href="https://www.abuseipdb.com/check/${encodeURIComponent(ip)}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 rounded-lg border border-red-200 bg-red-50 px-2 py-1 text-[10px] font-bold text-red-700 hover:bg-red-100 transition-all">
                                    <i data-lucide="shield-alert" class="h-3 w-3"></i> AbuseIPDB
                                </a>
                                <a href="https://www.virustotal.com/gui/ip-address/${encodeURIComponent(ip)}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-[10px] font-bold text-slate-700 hover:bg-slate-100 transition-all">
                                    <i data-lucide="globe" class="h-3 w-3"></i> VT
                                </a>
                                <a href="https://www.whois.com/whois/${encodeURIComponent(ip)}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 rounded-lg border border-blue-200 bg-blue-50 px-2 py-1 text-[10px] font-bold text-blue-700 hover:bg-blue-100 transition-all">
                                    <i data-lucide="search" class="h-3 w-3"></i> WHOIS
                                </a>
                            `;

                            // Targetowane loginy (sortowanie i rendering)
                            const sortedUsers = Object.entries(userStats).sort((a,b) => b[1]-a[1]);
                            const maxUserVal = sortedUsers.length > 0 ? sortedUsers[0][1] : 1;
                            document.getElementById('analysis-primary-target').innerText = sortedUsers.length > 0 ? sortedUsers[0][0] : "Brak";

                            const usersContainer = document.getElementById('analysis-targeted-users');
                            usersContainer.innerHTML = '';
                            sortedUsers.forEach(([username, count]) => {
                                const pct = Math.round((count / maxUserVal) * 100);
                                usersContainer.innerHTML += `
                                    <div class="space-y-1">
                                        <div class="flex justify-between text-[11px] font-semibold text-slate-700">
                                            <span class="font-mono text-slate-900">${username}</span>
                                            <span>${count} prób</span>
                                        </div>
                                        <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                                            <div class="bg-gradient-to-r from-red-500 to-rose-600 h-full rounded-full transition-all duration-500" style="width: ${pct}%"></div>
                                        </div>
                                    </div>
                                `;
                            });

                            // Cele (serwery docelowe)
                            const sortedDests = Object.entries(destStats).sort((a,b) => b[1]-a[1]);
                            const maxDestVal = sortedDests.length > 0 ? sortedDests[0][1] : 1;
                            const destsContainer = document.getElementById('analysis-destinations');
                            destsContainer.innerHTML = '';
                            sortedDests.forEach(([target, count]) => {
                                const pct = Math.round((count / maxDestVal) * 100);
                                destsContainer.innerHTML += `
                                    <div class="space-y-1">
                                        <div class="flex justify-between text-[10px] font-semibold">
                                            <span class="truncate max-w-[180px] text-slate-700 font-mono">${target}</span>
                                            <span class="text-slate-900 font-bold">${count}</span>
                                        </div>
                                        <div class="w-full bg-slate-50 h-1 rounded-full overflow-hidden">
                                            <div class="bg-indigo-600 h-full rounded-full" style="width: ${pct}%"></div>
                                        </div>
                                    </div>
                                `;
                            });

                            // Usługi
                            const sortedServices = Object.entries(serviceStats).sort((a,b) => b[1]-a[1]);
                            const maxServVal = sortedServices.length > 0 ? sortedServices[0][1] : 1;
                            const servicesContainer = document.getElementById('analysis-services');
                            servicesContainer.innerHTML = '';
                            sortedServices.forEach(([srv, count]) => {
                                const pct = Math.round((count / maxServVal) * 100);
                                servicesContainer.innerHTML += `
                                    <div class="space-y-1">
                                        <div class="flex justify-between text-[10px] font-semibold">
                                            <span class="rounded bg-slate-100 px-1.5 py-0.2 font-bold text-slate-600 font-mono text-[9px]">${srv}</span>
                                            <span class="text-slate-900 font-bold">${count}</span>
                                        </div>
                                        <div class="w-full bg-slate-50 h-1 rounded-full overflow-hidden">
                                            <div class="bg-emerald-500 h-full rounded-full" style="width: ${pct}%"></div>
                                        </div>
                                    </div>
                                `;
                            });

                            lucide.createIcons();
                        }

                        function toggleTableRows() {
                            showAllRows = !showAllRows;
                            renderTable(activeData);
                        }

                        function updateKPIs(data) {
                            let totalAttempts = 0;
                            const uniqueUsers = new Set();
                            const uniqueIps = new Set();
                            const destinationCounts = {};

                            data.forEach(row => {
                                const user = parseValueWithCount(row.user);
                                const sourceIp = parseValueWithCount(row.sourceIp);
                                const destHost = parseValueWithCount(row.destHost);

                                const dateRows = row.timeGenerated.split('\n').filter(d => d.trim().length > 0);
                                dateRows.forEach(dr => {
                                    totalAttempts += parseValueWithCount(dr).count;
                                });

                                if (user.val) uniqueUsers.add(user.val);
                                if (sourceIp.val) uniqueIps.add(sourceIp.val);
                                if (destHost.val && destHost.val !== "-") {
                                    destinationCounts[destHost.val] = (destinationCounts[destHost.val] || 0) + user.count;
                                }
                            });

                            let topDest = "-";
                            let maxDestCount = -1;
                            for (const [dest, count] of Object.entries(destinationCounts)) {
                                if (count > maxDestCount) {
                                    maxDestCount = count;
                                    topDest = dest;
                                }
                            }

                            document.getElementById('kpi-total-attempts').innerText = totalAttempts.toLocaleString();
                            document.getElementById('kpi-unique-users').innerText = uniqueUsers.size;
                            document.getElementById('kpi-unique-ips').innerText = uniqueIps.size;
                            document.getElementById('kpi-top-dest').innerText = topDest;
                        }

                        function renderTop5Lists(data) {
                            const userStats = {};
                            const serviceStats = {};
                            const ipStats = {};

                            data.forEach(row => {
                                const u = parseValueWithCount(row.user);
                                const s = parseValueWithCount(row.serviceName);
                                const ip = parseValueWithCount(row.sourceIp);

                                if (u.val && u.val !== "-") userStats[u.val] = (userStats[u.val] || 0) + u.count;
                                if (s.val && s.val !== "-") serviceStats[s.val] = (serviceStats[s.val] || 0) + s.count;
                                if (ip.val && ip.val !== "-") ipStats[ip.val] = (ipStats[ip.val] || 0) + ip.count;
                            });

                            const sortedUsers = Object.entries(userStats).sort((a,b) => b[1]-a[1]).slice(0, 5);
                            const sortedServices = Object.entries(serviceStats).sort((a,b) => b[1]-a[1]).slice(0, 5);
                            const sortedIps = Object.entries(ipStats).sort((a,b) => b[1]-a[1]).slice(0, 5);

                            const maxU = sortedUsers.length > 0 ? sortedUsers[0][1] : 1;
                            const maxS = sortedServices.length > 0 ? sortedServices[0][1] : 1;
                            const maxI = sortedIps.length > 0 ? sortedIps[0][1] : 1;

                            // Render listy Top 5 Użytkowników
                            const usersContainer = document.getElementById('top-users-list');
                            usersContainer.innerHTML = '';
                            sortedUsers.forEach(([name, count], idx) => {
                                const pct = Math.round((count/maxU)*100);
                                usersContainer.innerHTML += `
                                    <div class="space-y-1">
                                        <div class="flex justify-between text-xs font-medium">
                                            <span class="text-slate-700 font-semibold">${idx+1}. ${name}</span>
                                            <span class="text-slate-900 font-bold">${count} prób</span>
                                        </div>
                                        <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                            <div class="bg-red-500 h-full rounded-full transition-all" style="width: ${pct}%"></div>
                                        </div>
                                    </div>`;
                            });

                            // Render listy Top 5 Usług
                            const servicesContainer = document.getElementById('top-services-list');
                            servicesContainer.innerHTML = '';
                            sortedServices.forEach(([name, count], idx) => {
                                const pct = Math.round((count/maxS)*100);
                                servicesContainer.innerHTML += `
                                    <div class="space-y-1">
                                        <div class="flex justify-between text-xs font-medium">
                                            <span class="text-slate-700 font-semibold">${idx+1}. ${name}</span>
                                            <span class="text-slate-900 font-bold">${count} prób</span>
                                        </div>
                                        <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                            <div class="bg-indigo-500 h-full rounded-full transition-all" style="width: ${pct}%"></div>
                                        </div>
                                    </div>`;
                            });

                            // Render listy Top 5 IP
                            const ipsContainer = document.getElementById('top-ips-list');
                            ipsContainer.innerHTML = '';
                            sortedIps.forEach(([name, count], idx) => {
                                const pct = Math.round((count/maxI)*100);
                                ipsContainer.innerHTML += `
                                    <div class="space-y-1">
                                        <div class="flex justify-between text-xs font-medium">
                                            <button onclick="selectHost('${name}')" class="text-slate-700 font-semibold text-left hover:underline hover:text-blue-600 focus:outline-none">
                                                ${idx+1}. ${name}
                                            </button>
                                            <span class="text-slate-900 font-bold">${count} prób</span>
                                        </div>
                                        <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                            <div class="bg-orange-500 h-full rounded-full transition-all" style="width: ${pct}%"></div>
                                        </div>
                                    </div>`;
                            });
                        }

                        function renderHourlyHeatmap(data) {
                            const hourlyCounts = Array(24).fill(0);
                            const titleEl = document.getElementById('heatmap-title');

                            // Filtrujemy dane na heatmapę w zależności od tego czy mamy wybrany pojedynczy host
                            let recordsToProcess = data;
                            if (selectedHostIp !== null) {
                                recordsToProcess = data.filter(row => parseValueWithCount(row.sourceIp).val === selectedHostIp);
                                titleEl.innerText = `Dobowy rozkład godzinowy prób logowania (Tylko host: ${selectedHostIp})`;
                            } else {
                                titleEl.innerText = `Dobowy rozkład godzinowy prób logowania (Wszystkie hosty)`;
                            }

                            recordsToProcess.forEach(row => {
                                const dateRows = row.timeGenerated.split('\n').filter(d => d.trim().length > 0);
                                dateRows.forEach(dr => {
                                    const parsedDate = parseValueWithCount(dr);
                                    const match = parsedDate.val.match(/\s(\d{2}):/);
                                    if (match) {
                                        const hour = parseInt(match[1], 10);
                                        if (hour >= 0 && hour < 24) hourlyCounts[hour] += parsedDate.count;
                                    }
                                });
                            });

                            const maxVal = Math.max(...hourlyCounts);
                            const grid = document.getElementById('hourly-heatmap-grid');
                            grid.innerHTML = '';

                            for (let h = 0; h < 24; h++) {
                                const count = hourlyCounts[h];
                                const opacity = count > 0 ? 0.08 + (count / (maxVal || 1)) * 0.88 : 0.03;
                                const textCol = opacity > 0.55 ? 'text-white' : 'text-slate-700';
                                const subTextCol = opacity > 0.55 ? 'text-indigo-100' : 'text-slate-400';
                                const bgStyle = count > 0 ? `background-color: rgba(79, 70, 229, ${opacity})` : `background-color: #f1f5f9`;
                                const formatted = h.toString().padStart(2, '0') + ':00';

                                grid.innerHTML += `
                                    <div class="p-3 rounded-lg flex flex-col items-center justify-between border border-slate-200/40 shadow-sm transition hover:scale-[1.04] cursor-help" style="${bgStyle}" title="Godzina ${formatted}: ${count} prób">
                                        <span class="text-[10px] font-bold ${subTextCol}">${formatted}</span>
                                        <span class="text-base font-extrabold mt-1 ${textCol}">${count}</span>
                                        <span class="text-[8px] uppercase tracking-wide mt-0.5 ${subTextCol}">prób</span>
                                    </div>`;
                            }
                        }

                        function updateFilterStats(visible, total) {
                            document.getElementById('filter-stats').innerText = `Wyświetlono: ${visible} z ${total} rekordów`;
                        }

                        function filterTable() {
                            const query = document.getElementById('table-search').value.toLowerCase();
                            const rows = document.getElementById('table-body').getElementsByTagName('tr');
                            let visibleCount = 0;

                            for (let i = 0; i < rows.length; i++) {
                                if (rows[i].innerText.toLowerCase().includes(query)) {
                                    rows[i].style.display = '';
                                    visibleCount++;
                                } else {
                                    rows[i].style.display = 'none';
                                }
                            }
                            updateFilterStats(visibleCount, activeData.length);
                        }

                        function setFilterPreset(preset) {
                            const searchInput = document.getElementById('table-search');
                            const buttons = document.querySelectorAll('.preset-btn');
                            buttons.forEach(btn => {
                                btn.className = 'preset-btn px-3.5 py-1.5 text-xs font-medium rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 transition';
                            });
                            event.target.className = 'preset-btn px-3.5 py-1.5 text-xs font-medium rounded-lg border border-blue-200 bg-blue-50 text-blue-700 transition';

                            if (preset === 'all') {
                                searchInput.value = '';
                            } else if (preset === 'deny') {
                                searchInput.value = 'deny';
                            }
                            filterTable();
                        }

                        function exportToCSV() {
                            let csv = "data:text/csv;charset=utf-8,";
                            const headers = ["Source.UserName", "Source.IP", "Source.HostName", "Destination.IP", "Destination.HostName", "EventMap.SubType", "Time.Generated", "EventSource.Description", "EventSource.IP", "Service.Name"];
                            csv += headers.join(",") + "\r\n";

                            activeData.forEach(row => {
                                const line = [
                                    `"${row.user}"`, `"${row.sourceIp}"`, `"${row.sourceHost}"`,
                                    `"${row.destIp}"`, `"${row.destHost}"`, `"${row.subType}"`,
                                    `"${row.timeGenerated.replace(/\n/g, ' | ')}"`, `"${row.description}"`, 
                                    `"${row.eventSourceIp}"`, `"${row.serviceName}"`
                                ];
                                csv += line.join(",") + "\n";
                            });

                            const link = document.createElement("a");
                            link.setAttribute("href", encodeURI(csv));
                            link.setAttribute("download", "raport_logowania_uzytkownikow.csv");
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                        }
                    </script>

                <?php else: ?>
                    <!-- ========================================== -->
                    <!-- WIDOK 2: DETEKCJA SKANOWANIA / ZDARZEŃ     -->
                    <!-- ========================================== -->
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
                        <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Wykryte Zdarzenia (Suma)</p>
                                    <h3 class="mt-2 text-2xl font-bold text-red-600"><?php echo number_format($parsedData['meta']['suma_zdarzen'], 0, ',', ' '); ?> zd.</h3>
                                </div>
                                <div class="rounded-xl bg-red-50 p-3 text-red-600 animate-pulse">
                                    <i data-lucide="shield-alert" class="h-6 w-6"></i>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Agresorzy (Unikalne IP)</p>
                                    <h3 class="mt-2 text-2xl font-bold text-slate-900"><?php echo $parsedData['meta']['unikalne_ip']; ?> hostów</h3>
                                </div>
                                <div class="rounded-xl bg-slate-50 p-3 text-slate-600">
                                    <i data-lucide="shield-off" class="h-6 w-6"></i>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Najbardziej Aktywny IP / Host</p>
                                    <h3 class="mt-2 text-md font-bold text-red-700 font-mono truncate" title="<?php echo $parsedData['meta']['najbardziej_aktywny_ip']; ?>"><?php echo htmlspecialchars($parsedData['meta']['najbardziej_aktywny_ip']); ?></h3>
                                </div>
                                <div class="rounded-xl bg-red-100 p-3 text-red-600">
                                    <i data-lucide="flame" class="h-6 w-6"></i>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Urządzenie zabezpieczające</p>
                                    <h3 class="mt-2 text-2xl font-bold text-blue-600"><?php echo htmlspecialchars($parsedData['meta']['urzadzenie']); ?></h3>
                                </div>
                                <div class="rounded-xl bg-blue-50 p-3 text-blue-600">
                                    <i data-lucide="shield-check" class="h-6 w-6"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabela Skonfigurowana dla Skanowania i Logowań -->
                    <div class="mb-8 rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
                        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center mb-6">
                            <div>
                                <h3 class="text-base font-bold text-slate-950 flex items-center gap-2">
                                    <span class="h-2.5 w-2.5 rounded-full bg-red-600 animate-ping"></span>
                                    Analiza raportu zdarzeń i naruszenia reguł bezpieczeństwa Firewall / Auth
                                </h3>
                                <p class="text-xs text-slate-400 mt-1">Zewnętrzne lub wewnętrzne hosty generujące próby połączeń, skanowań lub nieudanych logowań.</p>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-slate-100 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                        <th class="py-3 px-4">Kraj (Flaga)</th>
                                        <th class="py-3 px-4">Źródło (Source IP)</th>
                                        <th class="py-3 px-4 w-1/3">Cele (Dest IP & Port)</th>
                                        <th class="py-3 px-4 text-center">Zdarzenia / Próby</th>
                                        <th class="py-3 px-4 text-center">Zagrożenie</th>
                                        <th class="py-3 px-4">Aplikacja / Protokół</th>
                                        <th class="py-3 px-4">Szczegóły zdarzenia</th>
                                        <th class="py-3 px-4 text-center">Analiza IP źródłowego</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50 text-xs font-medium">
                                    <?php if (empty($parsedData['scans'])): ?>
                                        <tr>
                                            <td colspan="8" class="py-8 text-center text-slate-400 font-semibold">Brak wykrytych rekordów w wybranym pliku raportu HTML.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($parsedData['scans'] as $scan):
                                            $badgeClass = 'bg-blue-50 text-blue-700 border-blue-100';
                                            if ($scan['danger_level'] === 'Critical') {
                                                $badgeClass = 'bg-red-50 text-red-700 border border-red-200';
                                            } elseif ($scan['danger_level'] === 'High') {
                                                $badgeClass = 'bg-orange-50 text-orange-700 border border-orange-200';
                                            }
                                        ?>
                                            <tr class="hover:bg-slate-50/50 transition-colors">
                                                <td class="py-3.5 px-4">
                                                    <span class="text-xl inline-block align-middle" title="<?php echo htmlspecialchars($scan['source_country']); ?>">
                                                        <?php echo $parser->getCountryFlag($scan['source_country']); ?>
                                                    </span>
                                                    <span class="text-slate-500 text-[10px] ml-1.5 align-middle block sm:inline"><?php echo htmlspecialchars($scan['source_country']); ?></span>
                                                </td>
                                                <td class="py-3.5 px-4 font-bold text-slate-900 font-mono">
                                                    <div class="flex flex-col">
                                                        <span><?php echo htmlspecialchars($scan['source_ip']); ?></span>
                                                        <span class="text-[10px] text-slate-400 font-normal">Host</span>
                                                    </div>
                                                </td>
                                                <td class="py-3.5 px-4 font-mono font-medium">
                                                    <div class="flex flex-col gap-2 max-h-40 overflow-y-auto py-1">
                                                        <?php
                                                        $rawDest = $scan['dest_ip'];
                                                        $dest_ips = preg_split('/[\s,\n]+/', $rawDest);
                                                        $dest_ips = array_filter(array_map('trim', $dest_ips));

                                                        foreach ($dest_ips as $single_ip):
                                                            if (empty($single_ip)) continue;
                                                            $single_ip_clean = preg_replace('/\s*\([^)]*\)/', '', $single_ip);
                                                        ?>
                                                            <div class="flex items-center justify-between bg-slate-50 rounded-lg p-1.5 border border-slate-100 hover:bg-slate-100/70 transition-all">
                                                                <div class="flex flex-col">
                                                                    <span class="font-bold text-slate-800 text-[11px]"><?php echo htmlspecialchars($single_ip_clean); ?></span>
                                                                    <span class="text-[9px] text-slate-400 font-bold">PORT: <?php echo htmlspecialchars($scan['dest_port']); ?></span>
                                                                </div>
                                                                <div class="flex gap-1">
                                                                    <a href="https://www.abuseipdb.com/check/<?php echo urlencode($single_ip_clean); ?>" target="_blank" rel="noopener noreferrer" class="rounded p-1 bg-red-50 text-red-600 hover:bg-red-100" title="AbuseIPDB (Cel)">
                                                                        <i data-lucide="shield-alert" class="h-3 w-3"></i>
                                                                    </a>
                                                                    <a href="https://www.virustotal.com/gui/ip-address/<?php echo urlencode($single_ip_clean); ?>" target="_blank" rel="noopener noreferrer" class="rounded p-1 bg-slate-100 text-slate-700 hover:bg-slate-200" title="VirusTotal (Cel)">
                                                                        <i data-lucide="globe" class="h-3 w-3"></i>
                                                                    </a>
                                                                    <a href="https://www.whois.com/whois/<?php echo urlencode($single_ip_clean); ?>" target="_blank" rel="noopener noreferrer" class="rounded p-1 bg-blue-50 text-blue-600 hover:bg-blue-100" title="WHOIS (Cel)">
                                                                        <i data-lucide="search" class="h-3 w-3"></i>
                                                                    </a>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </td>
                                                <td class="py-3.5 px-4 text-center font-bold text-slate-900 font-mono">
                                                    <?php echo number_format($scan['events_count'], 0, ',', ' '); ?>
                                                </td>
                                                <td class="py-3.5 px-4 text-center">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold uppercase <?php echo $badgeClass; ?>">
                                                        <?php echo $scan['danger_level']; ?>
                                                    </span>
                                                </td>
                                                <td class="py-3.5 px-4">
                                                    <div class="text-slate-900 font-bold"><?php echo htmlspecialchars($scan['application']); ?></div>
                                                    <div class="text-[10px] text-slate-400 font-bold font-mono"><?php echo htmlspecialchars($scan['protocol']); ?> / <?php echo htmlspecialchars($scan['service']); ?></div>
                                                </td>
                                                <td class="py-3.5 px-4 text-slate-500 max-w-[150px] truncate" title="<?php echo htmlspecialchars($scan['event_desc']); ?>">
                                                    <div class="font-semibold text-slate-700"><?php echo htmlspecialchars($scan['event_info']); ?></div>
                                                    <div class="text-[10px]"><?php echo htmlspecialchars($scan['event_desc']); ?></div>
                                                </td>
                                                <td class="py-3.5 px-4 text-center">
                                                    <div class="flex items-center justify-center gap-1">
                                                        <a href="<?php echo $scan['abuse_url']; ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 rounded-lg border border-red-200 bg-red-50/50 px-2 py-1 text-[10px] font-bold text-red-700 hover:bg-red-100 transition-all">
                                                            <i data-lucide="shield-alert" class="h-3.5 w-3.5"></i> AbuseIPDB
                                                        </a>
                                                        <a href="<?php echo $scan['virustotal_url']; ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-[10px] font-bold text-slate-700 hover:bg-slate-100 transition-all">
                                                            <i data-lucide="globe" class="h-3.5 w-3.5"></i> VT
                                                        </a>
                                                        <a href="<?php echo $scan['whois_url']; ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 rounded-lg border border-blue-200 bg-blue-50/50 px-2 py-1 text-[10px] font-bold text-blue-700 hover:bg-blue-100 transition-all">
                                                            <i data-lucide="search" class="h-3.5 w-3.5"></i> WHOIS
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- Stan pusty -->
                <div class="flex flex-col items-center justify-center min-h-[55vh] rounded-2xl border border-dashed border-slate-200 bg-white p-8 text-center">
                    <div class="rounded-2xl bg-blue-50 p-4 text-blue-600 mb-4">
                        <i data-lucide="folder-search" class="h-10 w-10"></i>
                    </div>
                    <h3 class="text-lg font-bold text-slate-950">Brak wgranych raportów sieciowych</h3>
                    <p class="text-sm text-slate-400 max-w-md mt-2 font-normal">Wgraj plik ZIP zawierający raporty HTML wygenerowane z systemu zabezpieczającego.</p>
                    <button onclick="document.getElementById('upload-modal').classList.remove('hidden')" class="mt-5 inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 transition-colors">
                        <i data-lucide="upload-cloud" class="h-4 w-4"></i>
                        Wgraj pierwszy plik ZIP
                    </button>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <!-- Modal wgrywania ZIP -->
    <div id="upload-modal" class="fixed inset-0 z-50 hidden bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl border border-slate-100">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i data-lucide="upload-cloud" class="h-5 w-5 text-blue-600"></i>
                    Importuj nowy pakiet raportów
                </h3>
                <button onclick="document.getElementById('upload-modal').classList.add('hidden')" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            <form action="upload.php" method="POST" enctype="multipart/form-data" class="mt-4">
                <div class="rounded-xl border border-dashed border-slate-200 p-8 text-center hover:bg-slate-50/50 transition-colors cursor-pointer" onclick="document.getElementById('zip-input').click()">
                    <i data-lucide="folder-archive" class="mx-auto h-12 w-12 text-slate-300"></i>
                    <p class="mt-3 text-sm font-semibold text-slate-700">Wybierz plik ZIP z dysku</p>
                    <p class="mt-1 text-xs text-slate-400">Maksymalny rozmiar pliku: 120 MB</p>
                    <input type="file" name="zip_file" id="zip-input" class="hidden" accept=".zip" required onchange="updateFileName(this)">
                    <div id="selected-file-name" class="mt-4 hidden text-xs font-bold text-blue-600 bg-blue-50 rounded-lg p-2 truncate"></div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="document.getElementById('upload-modal').classList.add('hidden')" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Anuluj</button>
                    <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500">Rozpocznij import</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Potwierdzenia Usunięcia Katalogu -->
    <div id="delete-confirm-modal" class="fixed inset-0 z-50 hidden bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl border border-slate-100">
            <div class="flex items-center gap-3 text-red-600 mb-4">
                <i data-lucide="alert-triangle" class="h-6 w-6"></i>
                <h3 class="font-bold text-slate-900 text-lg">Potwierdź usunięcie katalogu</h3>
            </div>
            <p class="text-sm text-slate-500 leading-relaxed font-normal">
                Czy na pewno chcesz usunąć katalog <span id="delete-dir-name" class="font-bold text-slate-900"></span> wraz ze wszystkimi plikami raportów HTML? Ta operacja jest całkowicie nieodwracalna.
            </p>
            <form action="index.php" method="POST" class="mt-6 flex justify-end gap-3">
                <input type="hidden" name="action" value="delete_dir">
                <input type="hidden" name="dir_name" id="delete-input-dir" value="">
                <button type="button" onclick="document.getElementById('delete-confirm-modal').classList.add('hidden')" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Anuluj</button>
                <button type="submit" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500 shadow-sm transition-all">Tak, usuń katalog</button>
            </form>
        </div>
    </div>

    <!-- Skrypty obsługi interfejsu -->
    <script>
        lucide.createIcons();

        function toggleFolder(id) {
            const el = document.getElementById(id);
            const icon = document.getElementById('icon-' + id);
            if (el.classList.contains('hidden')) {
                el.classList.remove('hidden');
                icon.style.transform = 'rotate(0deg)';
            } else {
                el.classList.add('hidden');
                icon.style.transform = 'rotate(-90deg)';
            }
        }

        function updateFileName(input) {
            const fileNameBox = document.getElementById('selected-file-name');
            if (input.files && input.files[0]) {
                fileNameBox.textContent = "Wybrano: " + input.files[0].name;
                fileNameBox.classList.remove('hidden');
            } else {
                fileNameBox.classList.add('hidden');
            }
        }

        function confirmDeleteDir(dirName) {
            document.getElementById('delete-dir-name').textContent = dirName;
            document.getElementById('delete-input-dir').value = dirName;
            document.getElementById('delete-confirm-modal').classList.remove('hidden');
        }

        function showAllHostsRows() {
            const rows = document.querySelectorAll('.host-row');
            rows.forEach(row => { row.classList.remove('hidden'); });
            const btn = document.getElementById('btn-show-more');
            if(btn) { btn.classList.add('hidden'); }
        }
    </script>
</body>
</html>
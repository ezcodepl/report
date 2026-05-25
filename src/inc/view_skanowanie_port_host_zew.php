<!-- ========================================== -->
<!-- WIDOK: SKANOWANIE I NARUSZENIA BEZPIECZEŃSTWA -->
<!-- ========================================== -->

<!-- Główne Metryki Security (KPI) -->
<div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
    <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm hover:shadow-md transition-all duration-200">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Wykryte Zdarzenia</p>
                <h3 class="mt-2 text-2xl font-bold text-red-600"><?php echo number_format($parsedData['meta']['suma_zdarzen'], 0, ',', ' '); ?> <span class="text-xs font-medium text-slate-400">zd.</span></h3>
            </div>
            <div class="rounded-xl bg-red-50 p-3 text-red-600 animate-pulse">
                <i data-lucide="shield-alert" class="h-6 w-6"></i>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm hover:shadow-md transition-all duration-200">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Aktywni Agresorzy</p>
                <h3 class="mt-2 text-2xl font-bold text-slate-900"><?php echo $parsedData['meta']['unikalne_ip']; ?> <span class="text-xs font-medium text-slate-400">hostów</span></h3>
            </div>
            <div class="rounded-xl bg-slate-100 p-3 text-slate-600">
                <i data-lucide="shield-off" class="h-6 w-6"></i>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm hover:shadow-md transition-all duration-200">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Główny Agresor (IP)</p>
                <h3 class="mt-2 text-md font-bold text-red-700 font-mono truncate" title="<?php echo htmlspecialchars($parsedData['meta']['najbardziej_aktywny_ip']); ?>">
                    <?php echo htmlspecialchars($parsedData['meta']['najbardziej_aktywny_ip']); ?>
                </h3>
            </div>
            <div class="rounded-xl bg-red-100 p-3 text-red-600">
                <i data-lucide="flame" class="h-6 w-6"></i>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm hover:shadow-md transition-all duration-200">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400">System Zabezpieczeń</p>
                <h3 class="mt-2 text-2xl font-bold text-blue-600"><?php echo htmlspecialchars($parsedData['meta']['urzadzenie']); ?></h3>
            </div>
            <div class="rounded-xl bg-blue-50 p-3 text-blue-600">
                <i data-lucide="shield-check" class="h-6 w-6"></i>
            </div>
        </div>
    </div>
</div>

<!-- Sekcja Wykresów Podsumowujących Krajów i Usług -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <!-- Top Kraje Pochodzenia Skanów -->
    <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
        <div class="flex items-center gap-2 mb-4 border-b border-slate-100 pb-3">
            <i data-lucide="globe-2" class="h-5 w-5 text-indigo-600"></i>
            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wide">Geolokalizacja Incydentów (Kraje)</h3>
        </div>
        <div class="space-y-4">
            <?php
            $countryCounts = [];
            foreach ($parsedData['scans'] as $scan) {
                $c = $scan['source_country'] ?: 'Nieznany';
                $countryCounts[$c] = ($countryCounts[$c] ?? 0) + $scan['events_count'];
            }
            arsort($countryCounts);
            $topCountries = array_slice($countryCounts, 0, 4);
            $maxCountryEvents = !empty($topCountries) ? max($topCountries) : 1;

            if (empty($topCountries)): ?>
                <p class="text-xs text-slate-400 font-semibold py-4 text-center">Brak szczegółowych danych geolokalizacyjnych</p>
            <?php else: ?>
                <?php foreach ($topCountries as $countryName => $count):
                    $percent = min(100, round(($count / $maxCountryEvents) * 100));
                ?>
                    <div>
                        <div class="flex justify-between text-xs font-semibold text-slate-700 mb-1">
                            <span class="flex items-center gap-1.5">
                                <span class="text-lg"><?php echo $parser->getCountryFlag($countryName); ?></span>
                                <span><?php echo htmlspecialchars($countryName); ?></span>
                            </span>
                            <span class="text-indigo-600 font-bold"><?php echo number_format($count, 0, ',', ' '); ?> zd.</span>
                        </div>
                        <div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-indigo-500 to-blue-600 rounded-full transition-all duration-500" style="width: <?php echo $percent; ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top Porty i Aplikacje -->
    <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
        <div class="flex items-center gap-2 mb-4 border-b border-slate-100 pb-3">
            <i data-lucide="cpu" class="h-5 w-5 text-red-600"></i>
            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wide">Najczęściej Atakowane Usługi</h3>
        </div>
        <div class="space-y-4">
            <?php
            $serviceCounts = [];
            foreach ($parsedData['scans'] as $scan) {
                $s = ($scan['application'] ?: 'Inne') . ' (' . ($scan['dest_port'] ?: 'Dowolny') . ')';
                $serviceCounts[$s] = ($serviceCounts[$s] ?? 0) + $scan['events_count'];
            }
            arsort($serviceCounts);
            $topServices = array_slice($serviceCounts, 0, 4);
            $maxServiceEvents = !empty($topServices) ? max($topServices) : 1;

            if (empty($topServices)): ?>
                <p class="text-xs text-slate-400 font-semibold py-4 text-center">Brak sklasyfikowanych usług</p>
            <?php else: ?>
                <?php foreach ($topServices as $serviceName => $count):
                    $percent = min(100, round(($count / $maxServiceEvents) * 100));
                ?>
                    <div>
                        <div class="flex justify-between text-xs font-semibold text-slate-700 mb-1">
                            <span class="font-mono text-slate-900"><?php echo htmlspecialchars($serviceName); ?></span>
                            <span class="text-red-600 font-bold"><?php echo number_format($count, 0, ',', ' '); ?> zd.</span>
                        </div>
                        <div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-red-500 to-orange-500 rounded-full transition-all duration-500" style="width: <?php echo $percent; ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Tabela Skonfigurowana dla Skanowania -->
<div class="mb-8 rounded-2xl border border-slate-150 bg-white p-6 shadow-sm">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center mb-6 border-b border-slate-100 pb-4">
        <div>
            <h3 class="text-base font-bold text-slate-950 flex items-center gap-2">
                <span class="h-2.5 w-2.5 rounded-full bg-red-600 animate-ping"></span>
                Analiza Raportu Zdarzeń i Naruszenia Reguł Bezpieczeństwa Firewall / Auth
            </h3>
            <p class="text-xs text-slate-400 mt-1">Zewnętrzne lub wewnętrzne hosty generujące próby połączeń, skanowań lub nieudanych logowań.</p>
        </div>
        <!-- Dynamiczne Filtrowanie -->
        <div class="relative max-w-xs w-full">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                <i data-lucide="search" class="w-4 h-4"></i>
            </span>
            <input type="text" id="scan-search" onkeyup="filterScanTable()" placeholder="Szukaj IP, kraju, portu..." class="w-full pl-9 pr-4 py-2 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 focus:outline-none">
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse" id="scans-table">
            <thead>
                <tr class="border-b border-slate-100 text-[10px] font-bold uppercase tracking-wider text-slate-400 bg-slate-50/50">
                    <th class="py-3 px-4">Kraj (Flaga)</th>
                    <th class="py-3 px-4">Źródło (Source IP)</th>
                    <th class="py-3 px-4 w-1/3">Cele (Dest IP & Port / Zagnieżdżone)</th>
                    <th class="py-3 px-4 text-center">Zdarzenia</th>
                    <th class="py-3 px-4 text-center">Zagrożenie</th>
                    <th class="py-3 px-4">Aplikacja / Protokół</th>
                    <th class="py-3 px-4">Typ / Sygnatura</th>
                    <th class="py-3 px-4 text-center">Akcja</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50 text-xs font-medium" id="scans-table-body">
                <?php if (empty($parsedData['scans'])): ?>
                    <tr>
                        <td colspan="8" class="py-12 text-center text-slate-400 font-semibold bg-slate-50/30">
                            <div class="flex flex-col items-center justify-center gap-2">
                                <i data-lucide="shield-alert" class="h-8 w-8 text-slate-300"></i>
                                <span>Brak wykrytych rekordów w wybranym pliku raportu HTML.</span>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($parsedData['scans'] as $index => $scan):
                        $badgeClass = 'bg-blue-50 text-blue-700 border-blue-100';
                        $rowBorder = 'border-l-4 border-l-blue-400';
                        if ($scan['danger_level'] === 'Critical') {
                            $badgeClass = 'bg-red-50 text-red-700 border border-red-200';
                            $rowBorder = 'border-l-4 border-l-red-500';
                        } elseif ($scan['danger_level'] === 'High') {
                            $badgeClass = 'bg-orange-50 text-orange-700 border border-orange-200';
                            $rowBorder = 'border-l-4 border-l-orange-400';
                        }

                        $rowId = 'scan-row-' . $index;
                        $detailId = 'scan-detail-' . $index;
                    ?>
                        <!-- Główny Wiersz Hosta -->
                        <tr class="hover:bg-slate-50/50 transition-colors <?php echo $rowBorder; ?>" id="<?php echo $rowId; ?>">
                            <td class="py-3.5 px-4">
                                <span class="text-xl inline-block align-middle" title="<?php echo htmlspecialchars($scan['source_country']); ?>">
                                    <?php echo $parser->getCountryFlag($scan['source_country']); ?>
                                </span>
                                <span class="text-slate-500 text-[10px] ml-1.5 align-middle block sm:inline font-bold"><?php echo htmlspecialchars($scan['source_country']); ?></span>
                            </td>
                            <td class="py-3.5 px-4 font-bold text-slate-900 font-mono">
                                <div class="flex flex-col">
                                    <span><?php echo htmlspecialchars($scan['source_ip']); ?></span>
                                    <span class="text-[10px] text-slate-400 font-normal">Zewnętrzny agresor</span>
                                </div>
                            </td>
                            <td class="py-3.5 px-4 font-mono font-medium">
                                <div class="flex flex-col gap-1.5 max-h-36 overflow-y-auto py-1">
                                    <?php
                                    $rawDest = $scan['dest_ip'];
                                    $dest_ips = preg_split('/[\s,\n]+/', $rawDest);
                                    $dest_ips = array_filter(array_map('trim', $dest_ips));

                                    // Renderowanie zagnieżdżonych celów jako mini-tagów
                                    $chipCount = 0;
                                    foreach ($dest_ips as $single_ip):
                                        if (empty($single_ip)) continue;
                                        $single_ip_clean = preg_replace('/\s*\([^)]*\)/', '', $single_ip);

                                        // Wyciągnięcie liczby prób dla konkretnego IP z nawiasu
                                        $single_count = '';
                                        if (preg_match('/\(([\d\s]+)\)/', $single_ip, $cm)) {
                                            $single_count = ' (' . trim($cm[1]) . ')';
                                        }
                                        $chipCount++;
                                        if ($chipCount <= 2):
                                    ?>
                                        <div class="flex items-center justify-between bg-slate-50 rounded-lg px-2 py-1 border border-slate-100 hover:bg-slate-100/70 transition-all text-[10px]">
                                            <div class="flex flex-col">
                                                <span class="font-bold text-slate-800"><?php echo htmlspecialchars($single_ip_clean); ?><span class="text-indigo-600 font-mono text-[9px]"><?php echo $single_count; ?></span></span>
                                                <span class="text-[9px] text-slate-400 font-bold">PORT: <?php echo htmlspecialchars($scan['dest_port']); ?></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <?php endforeach; ?>

                                    <?php if (count($dest_ips) > 2): ?>
                                        <span class="text-[10px] text-blue-600 font-semibold pl-1">+<?php echo (count($dest_ips) - 2); ?> kolejnych celów (kliknij Szczegóły)</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="py-3.5 px-4 text-center font-extrabold text-slate-900 font-mono">
                                <?php echo number_format($scan['events_count'], 0, ',', ' '); ?>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-extrabold uppercase <?php echo $badgeClass; ?>">
                                    <?php echo $scan['danger_level']; ?>
                                </span>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="text-slate-900 font-bold"><?php echo htmlspecialchars($scan['application']); ?></div>
                                <div class="text-[10px] text-slate-400 font-bold font-mono"><?php echo htmlspecialchars($scan['protocol']); ?> / <?php echo htmlspecialchars($scan['service']); ?></div>
                            </td>
                            <td class="py-3.5 px-4 text-slate-500">
                                <div class="font-semibold text-slate-700"><?php echo htmlspecialchars($scan['event_info']); ?></div>
                                <div class="text-[10px] font-normal text-slate-400 truncate max-w-[120px]" title="<?php echo htmlspecialchars($scan['event_desc']); ?>">
                                    <?php echo htmlspecialchars($scan['event_desc']); ?>
                                </div>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <button onclick="toggleScanDetails('<?php echo $detailId; ?>')" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-bold text-slate-600 shadow-sm hover:bg-slate-50 hover:text-blue-600 hover:border-blue-200 transition-all">
                                    <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                                    Szczegóły
                                </button>
                            </td>
                        </tr>

                        <!-- Rozwijany Panel Szczegółów (Zagnieżdżona korelacja danych i timeline) -->
                        <tr id="<?php echo $detailId; ?>" class="hidden bg-slate-50/50">
                            <td colspan="8" class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 border-l-2 border-slate-200 pl-4 py-1">

                                    <!-- Lewa: Pełna lista wszystkich zagnieżdżonych adresów docelowych -->
                                    <div>
                                        <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider mb-3 flex items-center gap-1">
                                            <i data-lucide="network" class="h-4 w-4 text-indigo-500"></i>
                                            Wszystkie zagnieżdżone cele (Destination IPs)
                                        </h4>
                                        <div class="space-y-1.5 max-h-48 overflow-y-auto pr-1">
                                            <?php foreach ($dest_ips as $single_ip):
                                                $single_ip_clean = preg_replace('/\s*\([^)]*\)/', '', $single_ip);
                                                $single_count = '1';
                                                if (preg_match('/\(([\d\s]+)\)/', $single_ip, $cm)) {
                                                    $single_count = trim($cm[1]);
                                                }
                                            ?>
                                                <div class="flex items-center justify-between bg-white rounded-lg p-2 border border-slate-150 font-mono text-[11px] hover:border-indigo-200 transition">
                                                    <div>
                                                        <span class="font-bold text-slate-900"><?php echo htmlspecialchars($single_ip_clean); ?></span>
                                                        <div class="text-[9px] text-slate-400 font-sans font-bold">Usługa: <?php echo htmlspecialchars($scan['service'] ?: 'Port '.$scan['dest_port']); ?></div>
                                                    </div>
                                                    <span class="bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded text-[10px] font-bold">x<?php echo $single_count; ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <!-- Środek: Timeline i zdarzenia szczegółowe -->
                                    <div>
                                        <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider mb-3 flex items-center gap-1">
                                            <i data-lucide="clock" class="h-4 w-4 text-orange-500"></i>
                                            Czas generowania (Wykryte timestampy)
                                        </h4>
                                        <div class="bg-white rounded-xl border border-slate-150 p-4 space-y-2.5 max-h-48 overflow-y-auto">
                                            <?php if (!empty($scan['time_generated'])): ?>
                                                <div class="flex items-center gap-2 text-[11px] font-mono text-slate-600">
                                                    <span class="h-2 w-2 rounded-full bg-orange-400"></span>
                                                    <span><?php echo htmlspecialchars($scan['time_generated']); ?></span>
                                                </div>
                                            <?php else: ?>
                                                <div class="flex items-center gap-2 text-[11px] font-mono text-slate-600">
                                                    <span class="h-2 w-2 rounded-full bg-slate-400"></span>
                                                    <span><?php echo date('Y-m-d H:i:s'); ?> (Timestamp domyślny)</span>
                                                </div>
                                            <?php endif; ?>
                                            <div class="text-[11px] text-slate-500 pt-1 leading-relaxed">
                                                <b>Opis incydentu:</b> <?php echo htmlspecialchars($scan['event_desc'] ?: 'Wykryto złośliwe próby skanowania portów z adresu zewnętrznego.'); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Prawa: Śledztwo, Analiza reputacji i akcje -->
                                    <div>
                                        <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider mb-3 flex items-center gap-1">
                                            <i data-lucide="search" class="h-4 w-4 text-red-500"></i>
                                            Analiza Reputacyjna IP źródłowego
                                        </h4>
                                        <div class="bg-white rounded-xl border border-slate-150 p-4 space-y-3">
                                            <div class="text-[11px] text-slate-500 leading-snug">
                                                Adres IP <span class="font-mono font-bold text-slate-900"><?php echo htmlspecialchars($scan['source_ip']); ?></span> pochodzi z kraju <b><?php echo htmlspecialchars($scan['source_country']); ?></b> i wykonał łącznie <b><?php echo number_format($scan['events_count'], 0, ',', ' '); ?></b> prób połączeń.
                                            </div>
                                            <!-- Przyciski Śledztwa -->
                                            <div class="flex flex-wrap gap-2 pt-1">
                                                <a href="<?php echo $scan['abuse_url']; ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-lg border border-red-200 bg-red-50/50 px-3 py-1.5 text-[10px] font-bold text-red-700 hover:bg-red-100 transition">
                                                    <i data-lucide="shield-alert" class="h-3.5 w-3.5"></i> AbuseIPDB
                                                </a>
                                                <a href="<?php echo $scan['virustotal_url']; ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-[10px] font-bold text-slate-700 hover:bg-slate-100 transition">
                                                    <i data-lucide="globe" class="h-3.5 w-3.5"></i> VirusTotal
                                                </a>
                                                <a href="<?php echo $scan['whois_url']; ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-lg border border-blue-200 bg-blue-50/50 px-3 py-1.5 text-[10px] font-bold text-blue-700 hover:bg-blue-100 transition">
                                                    <i data-lucide="search" class="h-3.5 w-3.5"></i> WHOIS
                                                </a>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Skrypty obsługi tabeli skanowania -->
<script>
    /**
     * Filtruje wiersze tabeli skanowania w czasie rzeczywistym
     */
    function filterScanTable() {
        const query = document.getElementById('scan-search').value.toLowerCase();
        const tbody = document.getElementById('scans-table-body');
        const rows = tbody.querySelectorAll('tr[id^="scan-row-"]');

        rows.forEach(row => {
            const rowIdParts = row.id.split('-');
            const index = rowIdParts[rowIdParts.length - 1];
            const detailRow = document.getElementById('scan-detail-' + index);

            // Pobieramy całą tekstowość wiersza głównego oraz jego szczegółów
            const mainText = row.innerText.toLowerCase();
            const detailText = detailRow ? detailRow.innerText.toLowerCase() : '';
            const combinedText = mainText + ' ' + detailText;

            if (combinedText.includes(query)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
                if (detailRow) {
                    detailRow.classList.add('hidden'); // Zwiń szczegóły jeśli ukryty
                }
            }
        });
    }

    /**
     * Rozwija i zwija szczegółowy panel korelacji zdarzeń dla wybranego agresora
     */
    function toggleScanDetails(detailId) {
        const detailRow = document.getElementById(detailId);
        if (detailRow) {
            if (detailRow.classList.contains('hidden')) {
                detailRow.classList.remove('hidden');
                // Płynna animacja pojawiania się
                detailRow.style.opacity = 0;
                setTimeout(() => {
                    detailRow.style.transition = 'opacity 0.2s ease-in-out';
                    detailRow.style.opacity = 1;
                }, 10);
            } else {
                detailRow.classList.add('hidden');
            }
        }
    }
</script>

<!-- ======================================================== -->
<!-- WIDOK: RAPORT BŁĘDNYCH PRÓB LOGOWANIA UŻYTKOWNIKÓW      -->
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

<!-- KARTA ANALITYCZNA WYBRANEGO HOSTA -->
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
                    <div class="flex flex-wrap gap-1.5" id="analysis-external-links"></div>
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
            <div id="analysis-targeted-users" class="space-y-3 max-h-56 overflow-y-auto pr-1"></div>
        </div>

        <!-- Targetowane Serwery / Cele oraz Usługi -->
        <div class="lg:pl-4 space-y-5">
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-3">Obierane cele docelowe (IP / Host)</span>
                <div id="analysis-destinations" class="space-y-3 max-h-28 overflow-y-auto pr-1"></div>
            </div>
            <div class="border-t border-slate-100 pt-4">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-3">Wykorzystywane protokoły / usługi</span>
                <div id="analysis-services" class="space-y-3 max-h-24 overflow-y-auto pr-1"></div>
            </div>
        </div>
    </div>
</div>

<!-- TABELA GŁÓWNA -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-8">
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

<script>
    const activeData = <?php echo json_encode($parsedData['records'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); ?>;
    let showAllRows = false;
    let selectedHostIp = null;

    function parseValueWithCount(str) {
        if (!str) return { val: "-", count: 0 };
        const regex = /([^(]+)\s*\((\d+)\)/;
        const match = str.match(regex);
        if (match) {
            return { val: match[1].trim(), count: parseInt(match[2], 10) };
        }
        return { val: str.trim(), count: 1 };
    }

    window.addEventListener('DOMContentLoaded', () => {
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
            tbody.innerHTML = `<tr><td colspan="11" class="px-5 py-8 text-center text-slate-400">Brak danych</td></tr>`;
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
            const sourceIp = parseValueWithCount(row.sourceIp);
            const isSelected = (selectedHostIp !== null && sourceIp.val === selectedHostIp);
            tr.className = `hover:bg-slate-50 transition border-b border-slate-100 ${isSelected ? 'bg-blue-50/55' : ''}`;

            const user = parseValueWithCount(row.user);
            const sourceHost = parseValueWithCount(row.sourceHost);
            const destIp = parseValueWithCount(row.destIp);
            const destHost = parseValueWithCount(row.destHost);
            const subType = parseValueWithCount(row.subType);
            const description = parseValueWithCount(row.description);

            const dateRows = row.timeGenerated.split('\n').filter(d => d.trim().length > 0);
            let dateHtml = '';
            dateRows.forEach(dr => {
                const parsedDate = parseValueWithCount(dr);
                dateHtml += `
                    <div class="flex items-center justify-between space-x-2 py-0.5 border-b border-slate-100 last:border-0 font-mono text-[10px]">
                        <span class="text-slate-700">${parsedDate.val}</span>
                        <span class="bg-slate-100 text-slate-600 px-1 py-0.1 rounded text-[9px] font-bold">x${parsedDate.count}</span>
                    </div>`;
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
                </td>`;
            tbody.appendChild(tr);
        });
        updateFilterStats(displayedData.length, totalCount);
        lucide.createIcons();
    }

    function selectHost(ip) {
        selectedHostIp = ip;
        renderTable(activeData);
        renderHostAnalysisCard(ip);
        renderHourlyHeatmap(activeData);
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
            document.getElementById('analysis-targeted-users').innerHTML = `<p class="text-xs text-slate-400 py-4 text-center">Wybierz konkretnego hosta z tabeli poniżej.</p>`;
            document.getElementById('analysis-destinations').innerHTML = `<p class="text-xs text-slate-400 text-center py-2">Wybierz hosta</p>`;
            document.getElementById('analysis-services').innerHTML = `<p class="text-xs text-slate-400 text-center py-2">Wybierz hosta</p>`;
            document.getElementById('analysis-external-links').innerHTML = ``;
            return;
        }

        badge.innerText = `Wybrany: Host ${ip}`;
        badge.className = "rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-600 animate-pulse";
        resetBtn.classList.remove('hidden');

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

        document.getElementById('analysis-ip-address').innerText = ip;
        document.getElementById('analysis-hostname').innerText = `Host: ${detectedHostname}`;
        document.getElementById('analysis-total-attempts').innerText = totalAttempts.toLocaleString();

        document.getElementById('analysis-external-links').innerHTML = `
            <a href="https://www.abuseipdb.com/check/${encodeURIComponent(ip)}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-lg border border-red-200 bg-red-50 px-2 py-1 text-[10px] font-bold text-red-700 hover:bg-red-100 transition">
                <i data-lucide="shield-alert" class="h-3 w-3"></i> AbuseIPDB
            </a>
            <a href="https://www.virustotal.com/gui/ip-address/${encodeURIComponent(ip)}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-[10px] font-bold text-slate-700 hover:bg-slate-100 transition">
                <i data-lucide="globe" class="h-3 w-3"></i> VT
            </a>
            <a href="https://www.whois.com/whois/${encodeURIComponent(ip)}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-lg border border-blue-200 bg-blue-50 px-2 py-1 text-[10px] font-bold text-blue-700 hover:bg-blue-100 transition">
                <i data-lucide="search" class="h-3 w-3"></i> WHOIS
            </a>`;

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
                        <div class="bg-gradient-to-r from-red-500 to-rose-600 h-full rounded-full transition" style="width: ${pct}%"></div>
                    </div>
                </div>`;
        });

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
                </div>`;
        });

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
                </div>`;
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
                        <div class="bg-red-500 h-full rounded-full transition" style="width: ${pct}%"></div>
                    </div>
                </div>`;
        });

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
                        <div class="bg-indigo-500 h-full rounded-full transition" style="width: ${pct}%"></div>
                    </div>
                </div>`;
        });

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
                        <div class="bg-orange-500 h-full rounded-full transition" style="width: ${pct}%"></div>
                    </div>
                </div>`;
        });
    }

    function renderHourlyHeatmap(data) {
        const hourlyCounts = Array(24).fill(0);
        const titleEl = document.getElementById('heatmap-title');

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

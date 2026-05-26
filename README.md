Nowoczesna, wysokowydajna aplikacja webowa zaprojektowana do automatycznego parsowania, indeksowania oraz szczegółowej analityki logów bezpieczeństwa, zdarzeń sieciowych i prób uwierzytelniania eksportowanych z systemu Logsign SIEM w formacie HTML.

Platforma przetwarza surowe raporty HTML i przekształca je w gotowe do użycia informacje operacyjne z zakresu Cyber Threat Intelligence. Stworzona specjalnie pod kątem wymagań operacyjnych zespołów SOC (Security Operations Center) oraz Blue Teamów, zapewnia pełną widoczność zagrożeń, wykrywanie anomalii oraz analizę wektorów ataków za pomocą czytelnych dashboardów, heatmap i dedykowanych paneli śledczych.

🚀 Najważniejsze Funkcje
Zaawansowany Parser HTML: Bezproblemowa, zautomatyzowana ekstrakcja ustrukturyzowanych zdarzeń z plików raportów Logsign SIEM.

Interfejs w Stylu SOC / Blue Team: Nowoczesny, ciemny design (Dark Mode) zoptymalizowany pod kątem pracy w centrach monitorowania operacji bezpieczeństwa.

Mapy Aktywności Godzinowej (Heatmaps): Natychmiastowa identyfikacja okien czasowych wzmożonej aktywności agresorów, prób eksfiltracji danych oraz anomalii wolumetrycznych.

Rankingi Analityczne TOP-5: Szybkie typowanie najbardziej zagrożonych zasobów i złośliwych aktorów:

TOP-5 Atakowanych / Najaktywniejszych użytkowników

TOP-5 Hostów źródłowych i docelowych

TOP-5 Atakowanych portów oraz eksponowanych usług

Wykrywanie Rekonesansu: Dedykowane moduły analizy skanowania portów — zarówno wewnątrz infrastruktury, jak i z adresów zewnętrznych.

Audyt Uwierzytelniania: Korelacja nieudanych prób logowania w celu natychmiastowej identyfikacji ataków słownikowych oraz brute-force.

Monitorowanie Blokad Firewall: Przejrzysty wgląd w odrzucone połączenia sieciowe ułatwiający wykrywanie prób poruszania bocznego (Lateral Movement).

Panele Dochodzeniowe (Investigation Panels): Dedykowane widoki profilu konkretnego hosta bądź użytkownika, pozwalające na odtworzenie pełnej chronologii incydentu.

🛡️ Korzyści Operacyjne
Skrócenie Czasu Reakcji (IR): Szybsza korelacja zdarzeń radykalnie obniża kluczowy wskaźnik MTTR (Mean Time to Resolution).

Skalowalność Analizy: Możliwość łatwego przyswojenia i zinterpretowania potężnych zbiorów logów SIEM, nieczytelnych w surowej formie.

Automatyzacja Procesów: Koniec z ręcznym, monotonnym filtrowaniem danych w arkuszach kalkulacyjnych — aplikacja wykonuje całą pracę automatycznie.

Raportowanie dla Zarządu i Inżynierów: Czytelne, zagregowane wykresy i wskaźniki ułatwiające komunikację zarówno analitykom technicznym, jak i kadrze zarządzającej.

🛠️ Architektura i Stack Technologiczny
Silnik Backendowy: PHP 8.2-FPM — gwarantuje ultra-szybkie przetwarzanie ciągów znaków (string tokenization) oraz optymalne zarządzanie pamięcią przy dużych strumieniach danych.

Interfejs Użytkownika: TailwindCSS — elastyczny i lekki framework CSS dostarczający w pełni responsywnych komponentów.

Baza Danych: MySQL 8.0 — relacyjna baza danych z indeksacją zoptymalizowaną pod kątem szybkich zapytań analitycznych.

Serwer Web: Nginx — wydajne reverse proxy zabezpieczające stabilną obsługę dużych zapytań HTTP POST.

📥 Instrukcja Instalacji i Uruchomienia
Wymagania Wstępne
Upewnij się, że system operacyjny posiada zainstalowane środowiska:

Docker Engine (w wersji 20.10.0 lub nowszej)

Docker Compose (w wersji 2.0.0 lub nowszej)

1. Pobranie Repozytorium
Sklonuj kod źródłowy projektu do wybranego katalogu na serwerze:
2. Weryfikacja Struktury Plików
Struktura Twojego katalogu roboczego musi odpowiadać poniższemu schematowi:

```
Plaintext
.
├── docker-compose.yml     # Orkiestracja środowiska wielokontenerowego
├── Dockerfile             # Definicja kontenera PHP 8.2-FPM wraz z konfiguracją INI
├── nginx.conf             # Konfiguracja serwera Nginx oraz limitów uploadu
├── src/                   # Kod źródłowy aplikacji (główny katalog serwera WWW)
└── README.md              # Dokumentacja projektu
```
3. Budowanie i Uruchomienie Kontenerów
Uruchom cały stos aplikacji w tle przy użyciu Docker Compose:

```
Bash
docker-compose up -d --build
```
Polecenie to automatycznie pobierze obrazy bazowe, utworzy izolowaną sieć, zamontuje wolumeny i uruchomi produkcyjne mikro-usługi.

4. Dostęp do Panelu Analitycznego
Po poprawnym zainicjalizowaniu usług otwórz przeglądarkę internetową i przejdź pod adres:

```
Plaintext
Adres URL: http://localhost:8080
```
⚙️ Parametry Techniczne Środowiska
Mapowanie Portów: Aplikacja jest wystawiona na porcie hosta 8080 (przekierowanie z wewnętrznego portu 80 serwera Nginx).

Dane Połączenia z Bazą Danych:
```
Host: db (rozwiązywane automatycznie przez DNS Dockera)

Nazwa bazy danych: raporty_db

Użytkownik: root

Hasło bazy danych: root_password

Port: 3306
```

Optymalizacja pod Kątem Dużych Raportów:
Pliki eksportowane z systemów SIEM charakteryzują się dużymi rozmiarami. Środowisko zostało skonfigurowane tak, aby bezproblemowo obsługiwać duże pliki wejściowe:
```
client_max_body_size (Nginx): 120M

upload_max_filesize (PHP): 120M

post_max_size (PHP): 120M

memory_limit (PHP): 512M (zapewnia odpowiedni bufor dla drzewa obiektowego DOM parsowanego pliku)
```
🛑 Wyłączanie Środowiska
Aby bezpiecznie zatrzymać działanie aplikacji, nie narażając danych historycznych na uszkodzenie (baza danych jest trwale zapisywana w dedykowanym wolumenie izolowanym db_data), wykonaj:
```
Bash
docker-compose down
```

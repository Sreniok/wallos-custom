# Improve.md

Analiza projektu Wallos Custom Build wykonana na podstawie obecnej struktury repo. Projekt jest praktyczny i ma już kilka sensownych utwardzeń: CSRF dla endpointów aplikacyjnych, bezpieczniejszy restore ZIP, hardened remember-me cookies, throttling logowania, healthcheck Dockera i PWA cache. Największy potencjał poprawy jest teraz w spójności backendu, migracjach, testach oraz w zamknięciu kilku miejsc, gdzie custom build może się rozjechać z czystą instalacją.

## Priorytet 1: stabilność i poprawność — DONE

Status 2026-05-11: DONE. Bootstrap świeżej bazy ma poprawiony schemat startowy i działa z późniejszymi migracjami, migrator sortuje pliki deterministycznie, migracja `000045.php` jest idempotentna, a `app/tests/smoke.php` sprawdza lint PHP, świeży bootstrap + migracje, wymagane tabele/kolumny i bezpieczne rozpakowywanie ZIP.

1. Naprawić bootstrap nowej bazy w `app/endpoints/cronjobs/createdatabase.php`.
   - W definicji `subscriptions` brakuje przecinka między `FOREIGN KEY(payer_user_id)` i `FOREIGN KEY(category_id)` (`createdatabase.php:50-51`). To może zepsuć pierwsze uruchomienie na czystym wolumenie.
   - Tabela startowa nie zawiera wielu kolumn dodanych później migracjami, np. `user_id`, `url`, `inactive`, `notify_days_before`, `start_date`, `auto_renew`, `cancellation_date`, `replacement_subscription_id`, `adjust_to_working_day`. Nowa instalacja powinna powstawać z aktualnym schematem albo bootstrap powinien natychmiast rejestrować i odpalać wszystkie migracje w deterministyczny sposób.

2. Uporządkować migracje.
   - `app/endpoints/db/migrate.php` bierze `glob('migrations/*.php')` bez jawnego sortowania (`migrate.php:33-46`). W praktyce często będzie dobrze, ale bez `sort()` kolejność migracji zależy od systemu plików.
   - Migracja `000048.php` bezwarunkowo zmienia wszystkie subskrypcje `adjust_to_working_day` z `0` na `1`. Warto dopisać komentarz do changeloga i test migracji, bo to jest zmiana semantyki danych, a nie tylko dodanie kolumny.
   - Dodać prosty test: start z pustą bazą, uruchom `createdatabase.php`, potem `migrate.php`, potem sprawdź wymagane tabele/kolumny.

3. Dodać minimalny zestaw automatycznych testów.
   - W repo nie widać `composer.json`, `phpunit.xml`, `.github/workflows` ani innego CI.
   - Najpierw wystarczy mały zestaw smoke testów: `php -l` dla plików PHP, test migracji na tymczasowej SQLite, test `wallosSafeZipExtract()`, test formatowania dat i wyliczania następnej płatności.
   - To szybko wyłapie regresje typu brakujący przecinek w SQL, niedziałający restore albo literówki w cache manifestach.

## Priorytet 2: bezpieczeństwo — DONE

Status 2026-05-11: DONE. Endpoint admina używa teraz spójnego `apiError(..., 403)`, odpowiedzi helperów API ustawiają `Content-Type: application/json`, pobieranie logo i ikon płatności korzysta ze wspólnego helpera SSRF z limitami rozmiaru, typu i wymiarów, restore/import backupu waliduje ZIP i SQLite przed atomową podmianą bazy z rollbackiem, a nginx blokuje `.tmp`, `backups`, ukryte/dumpowe pliki, ogranicza wykonywanie PHP i dodaje podstawowe nagłówki bezpieczeństwa.

1. Ujednolicić odpowiedzi endpointów.
   - Część nowych endpointów używa `apiSuccess()` / `apiError()`, ale wiele starszych miejsc nadal robi `die(json_encode(...))` albo zwraca tekst błędu.
   - Przykład: `validate_endpoint_admin.php:4-8` zwraca JSON bez ustawienia HTTP 403. Warto przenieść to na `apiError(..., 403)`.
   - Zysk: frontend może konsekwentnie obsługiwać błędy, a API będzie czytelniejsze dla integracji.

2. Wzmocnić upload i pobieranie logo.
   - Upload pliku ma już limit 2 MB i `getimagesize()`, co jest dobre (`add.php:278-297`).
   - `getLogoFromUrl()` nadal pobiera obraz do pamięci przez `curl_exec()` (`add.php:52`) bez jawnego limitu rozmiaru odpowiedzi. Warto dodać limit bajtów, sprawdzenie `Content-Type`, limit wymiarów po dekodowaniu i wspólny helper SSRF zamiast osobnej logiki.
   - Obecne sprawdzenie IP blokuje prywatne zakresy, ale nie używa centralnego `ssrf_helper.php`, więc łatwo o rozjazd zachowania między webhookami i logo.

3. Dopiąć restore backupu jako operację transakcyjną operacyjnie.
   - `restore.php` usuwa obecną bazę i robi `rename()` nowej (`restore.php:37-42`). Jeśli coś pójdzie źle po usunięciu, aplikacja może zostać bez działającej bazy.
   - Lepszy przepływ: walidacja ZIP, walidacja `wallos.db` przez próbne otwarcie SQLite, kopia obecnej bazy jako rollback, atomowa podmiana, dopiero potem czyszczenie logo.
   - Dodać limit wielkości ZIP i liczbę plików, bo bezpieczna ścieżka nie chroni przed zip bombą.

4. Utwardzić nginx.
   - Obecnie blokowane są `.db`, PHP w uploadach i PHP w `.tmp` (`nginx.conf:41-53`), co jest dobrym początkiem.
   - Warto też zablokować bezpośredni dostęp do `.tmp`, `backups`, potencjalnych dumpów, ukrytych plików oraz ograniczyć wykonywanie PHP tylko do znanych ścieżek aplikacji.
   - Dodać nagłówki `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options`/CSP w trybie ostrożnym.

## Priorytet 3: architektura i utrzymanie — DONE

Status 2026-05-11: DONE. Dodano wspólne helpery request/API (`request_helpers.php`, wersjonowane odpowiedzi publiczne), publiczne `get_subscriptions.php` obsługuje teraz `Authorization: Bearer <api_key>`, waliduje listowe filtry jako positive integer lists i zwraca stabilniejszy kontrakt błędów z `api_version`. Endpoint dodawania/edycji subskrypcji korzysta z `subscription_form.php` do normalizacji, walidacji i bindowania danych, a wyliczanie postępu cyklu płatności przeniesiono do `subscription_dates.php` jako część wspólnego modelu dat. Smoke testy pokrywają nowe helpery.

1. Wydzielić wspólne warstwy backendu.
   - Aktualnie logika miesza połączenie z bazą, sesję, walidację, tłumaczenia i odpowiedź JSON w wielu plikach.
   - Dobry kierunek: `RequestContext` lub prostszy zestaw helperów: `requirePostJsonOrForm()`, `currentUserId()`, `requireAdmin()`, `jsonSuccess()`, `jsonError()`, `bindBool()`, `bindNullableDate()`.
   - Dzięki temu endpointy typu settings mogą być dużo krótsze i bardziej odporne na niespójności.

2. Przenieść logikę domenową z endpointów do helperów.
   - `app/endpoints/subscription/add.php` robi walidację, logo, daty, SQL insert/update i odpowiedź JSON w jednym pliku.
   - Warto wydzielić: walidację subskrypcji, obsługę logo, zapis subskrypcji, normalizację dat.
   - To ułatwi testowanie bez odpalania całego UI.

3. Uporządkować model dat i cykli płatności.
   - W projekcie są już `subscription_dates.php`, `stats_calculations.php`, `list_subscriptions.php`, cron `updatenextpayment.php` i iCal.
   - Najlepiej mieć jedną funkcję prawdy dla: następna płatność, ostatnia płatność, working day adjustment, status overdue, progress cyklu.
   - Potem UI, API, statystyki, cron i iCal korzystają z tych samych wyliczeń.

4. Poprawić API publiczne.
   - `app/api/subscriptions/get_subscriptions.php` przyjmuje GET/POST i API key w parametrach. Dla nowych integracji lepiej obsłużyć `Authorization: Bearer <api_key>`, zostawiając query param jako kompatybilność wsteczną.
   - Dodać wersjonowanie odpowiedzi, np. `/api/v1/...`, i stabilny kontrakt błędów.
   - Parametry listowe (`member`, `category`, `payment`) powinny być walidowane jako integer list, zanim trafią do bindowania.

## Priorytet 4: frontend i PWA — DONE

Status 2026-05-11: DONE. Service worker ma cache wersjonowany przez `app/includes/version.php`, poprawione ścieżki assetów PWA, nie prefetchuje ani nie cache'uje `login.php`/`admin.php` jako stron offline i omija API/endpointy w page cache. Frontend dostał wspólny `apiFetch()` parsujący JSON, obsługujący `success:false`, HTTP błędy i komunikaty z API. Lista subskrypcji przeszła z inline handlerów na delegację po `data-action`, a modal subskrypcji ma podstawowy focus trap, Escape close, `role="dialog"` i stabilniejszą obsługę klawiatury.

1. Poprawić service worker manifest.
   - W `service-worker.js` są literówki lub brak rozszerzeń: `images/siteicons/scg/payment.php` zamiast `svg` (`service-worker.js:89`) oraz ikony Apple bez `.png` (`service-worker.js:65-67`).
   - Cache version jest ręcznie ustawiany (`static-cache-v7`). Warto generować wersję z `app/includes/version.php` albo build arg, żeby update był przewidywalny.
   - Nie cache'ować `login.php` i `admin.php` jako zwykłych stron offline, chyba że jest jasno obsłużone wylogowanie i prywatność urządzenia.

2. Ujednolicić frontend fetch/error handling.
   - `safeFetch()` jest dobrym początkiem, ale część JS może nadal używać go niekonsekwentnie.
   - Warto dodać wrapper `apiFetch()` parsujący JSON, obsługujący `success:false`, 401, 403 i komunikaty tłumaczeń.

3. Ograniczyć inline event handlery.
   - W renderowanym PHP są `onClick`/`onKeyDown` bezpośrednio w HTML. Łatwiej utrzymać delegację zdarzeń w JS po `data-action` i `data-id`.
   - To zmniejszy ilość generowanego JS w HTML i uprości dostępność.

4. Dostępność.
   - Są już pierwsze kroki: `role`, `tabindex`, `aria-label`.
   - Dodać testy klawiatury dla modali, focus trap, Escape close, poprawne etykiety ikon i brak pułapek focusu na mobile actions.

## Priorytet 5: Docker i operacje

1. Zaktualizować README do faktycznych nazw plików.
   - README mówi o `docker-compose.yml` (`README.md:50` i `README.md:96`), a w repo widać `compose.yaml`; `docker-compose.yml` jest usunięty w statusie git.
   - To drobiazg, ale wpływa na pierwsze uruchomienie przez nową osobę.

2. Rozdzielić backupy od webroot albo zablokować je w nginx.
   - Backupy są montowane do `/var/www/html/backups`. Nawet jeśli listing katalogu nie jest włączony, lepiej trzymać je poza webroot albo jawnie zablokować `location ^~ /backups/`.

3. Dodać `.env.example`.
   - Przyda się dla `TZ`, `PUID`, `PGID`, `WALLOS_BACKUP_PATH`, `WALLOS_BACKUP_RETENTION_DAYS`, `WALLOS_TRUST_PROXY_HEADERS`, ewentualnie `DEMO_MODE`.

4. Uspójnić uprawnienia.
   - `startup.sh` tworzy katalogi i potem ustawia `chmod -R 755` na db/logos. Dla bazy i uploadów lepiej minimalne uprawnienia dla `www-data`, szczególnie jeśli host jest współdzielony.

## Szybkie wygrane

1. Dodać `sort($allMigrations);` w runnerze migracji.
2. Naprawić przecinek w `createdatabase.php`.
3. Poprawić literówki w `service-worker.js`.
4. Zmienić README z `docker-compose.yml` na `compose.yaml`.
5. Dodać `location ^~ /backups/ { deny all; }` i blokadę `.tmp`.
6. Zamienić `validate_endpoint_admin.php` na `apiError(..., 403)`.
7. Dodać `php -l` smoke check jako prosty workflow CI.

## Proponowana kolejność prac

1. Najpierw: bootstrap bazy, migracje i smoke testy. To zabezpiecza fundament.
2. Potem: restore/backup, nginx i logo SSRF/limity. To zamyka największe ryzyka operacyjne.
3. Następnie: wspólne API helpers i refaktor endpointów settings/subscription. To obniża koszt dalszych zmian.
4. Na końcu: PWA cache, dostępność, polish UI i dokumentacja operatorska.

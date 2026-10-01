# Gmail CRM

CRM w modelu SaaS działający wewnątrz Gmaila: **rozszerzenie Chrome (Manifest V3, Vue 3, InboxSDK)** połączone z **API w Laravel 11**, z tokenami Sanctum i licencjami przypisanymi do użytkowników.

```
crm-mail/
├── backend/     API w Laravel 11 (Sanctum, licencje, kontakty, zadania, dashboard)
└── extension/   Vite + CRXJS + Vue 3 + Pinia + Tailwind + InboxSDK
```

## Jak to działa

```
Karta Gmaila                                        Service worker rozszerzenia       API Laravel
┌─────────────────────────────────────────┐        ┌──────────────────────────┐      ┌───────────────────────┐
│ content.js (InboxSDK)                   │        │ background/index.js      │      │ auth:sanctum          │
│  ├ ThreadView → panel boczny            │ axios  │  • pageWorld InboxSDK    │ fetch│ license (inaczej 402) │
│  │   └ <div> ▸ shadow root ▸ SidebarApp │──msg──▶│  • dokleja token Bearer  │─────▶│ /contacts /tasks      │
│  └ własna trasa → DashboardApp          │        │  • tylko adresy API_BASE │      │ /dashboard/summary    │
│ jeden wspólny store Pinia (auth + crm)  │        │  • 401 → czyści sesję    │      │ /admin/users/*        │
└─────────────────────────────────────────┘        └──────────────────────────┘      └───────────────────────┘
                 ▲  chrome.storage.local (sesja, wspólna dla popupu i wszystkich kart)
```

* **Izolacja interfejsu:** każda aplikacja Vue jest montowana w kontenerze `<div>` z własnym Shadow DOM.
  Tailwind jest kompilowany z `?inline` i wstrzykiwany *do środka* shadow root, więc jego reset stylów
  (preflight) nie dotyka Gmaila, a style Gmaila nie wpływają na aplikację. Jednostki rem są zamieniane
  na px podczas budowania, bo bazowy rozmiar czcionki ustala Gmail.
* **Komunikacja z API:** axios w content scripcie używa własnego adaptera, który przekazuje zapytania do
  service workera. Worker ma `host_permissions` dla API, dokleja token i odrzuca wszystko, co nie
  prowadzi do `VITE_API_BASE_URL`. Dzięki temu nie zależymy od CORS dla originu `mail.google.com`.
  API i tak ma ścisłą konfigurację CORS na wypadek bezpośrednich wywołań.
* **Licencje:** logowanie działa zawsze (żeby interfejs mógł wyjaśnić problem), ale każdy endpoint CRM
  jest chroniony middlewarem `license`, który zwraca **HTTP 402** z
  `code: license_expired | license_missing`. Przy każdym 402 rozszerzenie pokazuje komunikat o odnowieniu.

## Backend

```bash
cd backend
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite        # albo ustaw DB_* dla MySQL
php artisan migrate

# utworzenie użytkownika i licencji (komenda zapyta o hasło)
php artisan crm:license ty@firma.pl --days=365 --name="Ty" --admin

php artisan serve                     # http://localhost:8000
php artisan test                      # 21 testów funkcjonalnych
```

Na produkcji uruchom scheduler (`php artisan schedule:work` albo cron), żeby codziennie usuwać wygasłe tokeny.

### API

Wszystkie ścieżki mają prefiks `/api`, przyjmują i zwracają JSON. Wysyłaj nagłówek `Authorization: Bearer <token>`.

| Metoda | Ścieżka | Middleware | Uwagi |
|---|---|---|---|
| POST | `/auth/login` | throttle 5/min | `{email, password, device_name?}` → `{token, expires_at, user}` |
| GET | `/auth/me` | auth | Dane użytkownika i status licencji (działa też przy wygasłej licencji) |
| POST | `/auth/logout` | auth | Unieważnia tylko bieżący token |
| GET | `/contacts` | auth, license | `search, status, sort, direction, page, per_page` (stronicowane, zawiera `open_tasks_count`) |
| GET | `/contacts/lookup?email=` | auth, license | Kontakt z zadaniami albo `{"data": null}` |
| POST/GET/PATCH/DELETE | `/contacts/{id}` | auth, license | `email` unikalny w obrębie użytkownika, `status ∈ lead, prospect, customer, inactive` |
| GET | `/tasks` | auth, license | `status=open\|completed\|overdue\|all`, `contact_id` |
| POST/GET/PATCH/DELETE | `/tasks/{id}` | auth, license | `{contact_id, title, due_date, is_completed}` |
| GET | `/dashboard/summary?tz=` | auth, license | Liczniki i listy przypomnień (zaległe / dziś / 7 dni) w strefie czasowej użytkownika |
| GET | `/admin/users` | auth, admin | |
| POST | `/admin/users/{id}/license` | auth, admin | `{days, regenerate_key?}`. Jeśli licencja jest aktywna, przedłuża ją od obecnej daty wygaśnięcia |
| DELETE | `/admin/users/{id}/license` | auth, admin | Kończy licencję i unieważnia wszystkie tokeny użytkownika |

Każde zapytanie jest ograniczone do zalogowanego użytkownika. Rekordy innych użytkowników zwracają 404,
a zadanie można przypiąć tylko do własnego kontaktu (walidacja `exists` z warunkiem na `user_id`).

**CORS** (`config/cors.php`): dozwolone tylko `https://mail.google.com` i originy `chrome-extension://…`,
wyłącznie tokeny Bearer (`supports_credentials=false`). Na produkcji przypnij ID swojego rozszerzenia:
`CORS_ALLOWED_ORIGINS="https://mail.google.com,chrome-extension://<id>"` oraz
`CORS_ALLOWED_ORIGIN_PATTERNS=`.

## Rozszerzenie

```bash
cd extension
npm install
cp .env.example .env
#   VITE_API_BASE_URL=http://localhost:8000/api
#   VITE_INBOXSDK_APP_ID=sdk_xxx   ← darmowe, z https://www.inboxsdk.com/register
npm run dev      # build z HMR w dist/
npm run build    # build produkcyjny w dist/ (API spoza localhost musi używać https)
```

Żeby załadować rozszerzenie, otwórz `chrome://extensions`, włącz tryb programisty, kliknij **Załaduj rozpakowane** i wskaż `extension/dist`.

| Plik | Do czego służy |
|---|---|
| `manifest.config.js` | Manifest MV3 (kompilowany do `dist/manifest.json`). Origin API trafia do `host_permissions` z `.env` |
| `vite.config.js` | Vue, CRXJS i kopiowanie `pageWorld.js` z InboxSDK do katalogu głównego rozszerzenia |
| `src/background/index.js` | Wstrzykiwanie pageWorld InboxSDK (MV3) i proxy API z autoryzacją |
| `src/content/content.js` | InboxSDK: panel boczny wątku, własna trasa, pozycja w menu, przycisk na pasku |
| `src/content/mount.js` | Montowanie Vue w Shadow DOM |
| `src/lib/api.js` | Instancja axios, adapter do service workera, ujednolicanie błędów (`ApiError`) |
| `src/stores/auth.js`, `src/stores/crm.js` | Store'y Pinia (sesja i licencja; cache kontaktów, zadań i dashboardu) |
| `src/sidebar/SidebarApp.vue` | Karta kontaktu nadawcy: dodawanie, edycja, status, zadania |
| `src/dashboard/DashboardApp.vue` | Dashboard pełnoekranowy: liczniki, przypomnienia, tabela klientów |
| `src/popup/` | Popup na pasku przeglądarki: logowanie, status licencji, wylogowanie |

## Uwagi

* Laravel 11 nie dostaje już poprawek bezpieczeństwa, a `composer audit` zgłasza podatności naprawione
  dopiero w wersjach 12.x/13.x. Przed wdrożeniem zalecana jest aktualizacja
  (`composer require laravel/framework:^12`, a potem uruchomienie testów).
* ID aplikacji InboxSDK to prawdziwy identyfikator powiązany z Twoim rozszerzeniem. Bez niego build kończy się błędem.

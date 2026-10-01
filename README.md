# Gmail CRM

A SaaS CRM that lives inside Gmail: a **Chrome extension (Manifest V3, Vue 3, InboxSDK)** backed by a **Laravel 11 API** with Sanctum tokens and per-user licenses.

```
crm-mail/
├── backend/     Laravel 11 API (Sanctum, licensing, contacts, tasks, dashboard)
└── extension/   Vite + CRXJS + Vue 3 + Pinia + Tailwind + InboxSDK
```

## How it fits together

```
Gmail tab                                           Extension service worker          Laravel API
┌─────────────────────────────────────────┐        ┌──────────────────────────┐      ┌───────────────────────┐
│ content.js (InboxSDK)                   │        │ background/index.js      │      │ auth:sanctum          │
│  ├ ThreadView → sidebar panel           │ axios  │  • InboxSDK pageWorld    │ fetch│ license (402 if not)  │
│  │   └ <div> ▸ shadow root ▸ SidebarApp │──msg──▶│  • attaches Bearer token │─────▶│ /contacts /tasks      │
│  └ custom route → DashboardApp          │        │  • only proxies API_BASE │      │ /dashboard/summary    │
│ one shared Pinia store (auth + crm)     │        │  • 401 → clears session  │      │ /admin/users/*        │
└─────────────────────────────────────────┘        └──────────────────────────┘      └───────────────────────┘
                 ▲  chrome.storage.local (session, synced to popup + every tab)
```

* **UI isolation:** every Vue app mounts into a wrapper `<div>` with its own Shadow DOM. Tailwind is
  compiled with `?inline` and injected *inside* the shadow root, so Tailwind's preflight never touches
  Gmail and Gmail's CSS never reaches the app. Rem units are converted to px at build time because
  Gmail controls the root font size.
* **Networking:** the content script's axios instance uses a custom adapter that forwards requests to
  the service worker. The worker has `host_permissions` for the API, attaches the token, and refuses to
  proxy anything outside `VITE_API_BASE_URL`. This avoids depending on CORS from the `mail.google.com`
  origin. The API still ships a strict CORS config for direct calls.
* **Licensing:** login always works (so the UI can explain the problem), but every CRM endpoint sits
  behind the `license` middleware, which returns **HTTP 402** with
  `code: license_expired | license_missing`. The extension shows a "renew" notice on any 402.

## Backend

```bash
cd backend
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite        # or configure DB_* for MySQL
php artisan migrate

# create a user + license (prompts for the password)
php artisan crm:license you@company.com --days=365 --name="You" --admin

php artisan serve                     # http://localhost:8000
php artisan test                      # 21 feature tests
```

Run the scheduler in production (`php artisan schedule:work` or cron) to prune expired tokens daily.

### API

All routes are prefixed with `/api` and accept/return JSON. Send `Authorization: Bearer <token>`.

| Method | Path | Middleware | Notes |
|---|---|---|---|
| POST | `/auth/login` | throttle 5/min | `{email, password, device_name?}` → `{token, expires_at, user}` |
| GET | `/auth/me` | auth | User and license status (works with an expired license) |
| POST | `/auth/logout` | auth | Revokes the current token only |
| GET | `/contacts` | auth, license | `search, status, sort, direction, page, per_page` (paginated, includes `open_tasks_count`) |
| GET | `/contacts/lookup?email=` | auth, license | Contact and its tasks, or `{"data": null}` |
| POST/GET/PATCH/DELETE | `/contacts/{id}` | auth, license | `email` is unique per user, `status ∈ lead, prospect, customer, inactive` |
| GET | `/tasks` | auth, license | `status=open\|completed\|overdue\|all`, `contact_id` |
| POST/GET/PATCH/DELETE | `/tasks/{id}` | auth, license | `{contact_id, title, due_date, is_completed}` |
| GET | `/dashboard/summary?tz=` | auth, license | Counters and overdue/today/7-day reminder lists, in the user's timezone |
| GET | `/admin/users` | auth, admin | |
| POST | `/admin/users/{id}/license` | auth, admin | `{days, regenerate_key?}`. Extends from the current expiry if still active |
| DELETE | `/admin/users/{id}/license` | auth, admin | Expires the license and revokes all of the user's tokens |

Every query is scoped to the authenticated user. Other tenants' records return 404, and a task can only
be attached to one of your own contacts (validated with `exists` plus a `user_id` constraint).

**CORS** (`config/cors.php`): only `https://mail.google.com` plus `chrome-extension://…` origins, bearer
tokens only (`supports_credentials=false`). In production, pin your extension ID with
`CORS_ALLOWED_ORIGINS="https://mail.google.com,chrome-extension://<id>"` and
`CORS_ALLOWED_ORIGIN_PATTERNS=`.

## Extension

```bash
cd extension
npm install
cp .env.example .env
#   VITE_API_BASE_URL=http://localhost:8000/api
#   VITE_INBOXSDK_APP_ID=sdk_xxx   ← free, from https://www.inboxsdk.com/register
npm run dev      # HMR build in dist/
npm run build    # production build in dist/ (https required for non-localhost APIs)
```

To load it, open `chrome://extensions`, enable Developer mode, choose **Load unpacked**, and select `extension/dist`.

| File | Purpose |
|---|---|
| `manifest.config.js` | MV3 manifest (compiled to `dist/manifest.json`). The API origin is injected into `host_permissions` from `.env` |
| `vite.config.js` | Vue, CRXJS, and copying of InboxSDK's `pageWorld.js` to the extension root |
| `src/background/index.js` | InboxSDK MV3 page-world injector and authenticated API proxy |
| `src/content/content.js` | InboxSDK: thread sidebar, custom route, nav item, app toolbar button |
| `src/content/mount.js` | Shadow-DOM Vue mounting helper |
| `src/lib/api.js` | axios instance, service-worker adapter, error normalisation (`ApiError`) |
| `src/stores/auth.js`, `src/stores/crm.js` | Pinia stores (session/license, contacts/tasks/dashboard cache) |
| `src/sidebar/SidebarApp.vue` | Contact card for the conversation's sender: create, edit, status, tasks |
| `src/dashboard/DashboardApp.vue` | Full-page dashboard: KPIs, reminders, client table |
| `src/popup/` | Toolbar popup: login, license status, logout |

## Notes

* Laravel 11 is past its security-fix window, and `composer audit` reports advisories that are only fixed
  in 12.x/13.x. Upgrading (`composer require laravel/framework:^12`, then run the test suite) is
  recommended before going live.
* The InboxSDK app ID is a real credential tied to your extension. The build fails if it's missing.

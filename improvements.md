# Wallos · Project-wide audit + improvement roadmap

## Context

After several sessions of mobile-first UI work (modern theme, hero card, swipe actions, settings tabs, FAB, dashboard cards), the user asked for a whole-project analysis to find where else the app can be improved. Three Explore agents audited the codebase in parallel: code quality / architecture, security, and UX / performance / accessibility. This plan summarizes their findings — grouped by area and prioritized by impact-vs-effort — so the user can pick what to tackle next.

The analysis was read-only. Nothing has been changed by this audit.

---

## 🚨 Security — fix soon

### Critical (action recommended immediately)

| # | Issue | File | What to do |
|---|-------|------|------------|
| 1 | **Hardcoded remember-me token in demo mode** lets anyone forge a `wallos_login` cookie when `DEMO_MODE` is set | [app/login.php:86](app/login.php#L86) | Replace `"abc123ABC"` with `bin2hex(random_bytes(32))` like the normal login path |
| 2 | **Zip path-traversal RCE** in DB import/restore — a malicious zip with `../` paths can write PHP into the web root | [app/endpoints/db/import.php](app/endpoints/db/import.php), [app/endpoints/db/restore.php](app/endpoints/db/restore.php) | Validate each entry name against `..` / absolute paths before extracting |
| 3 | **CSRF token missing on the multipart subscription-add form** (the only endpoint that doesn't go through `validate_endpoint.php`) | [app/endpoints/subscription/add.php](app/endpoints/subscription/add.php) | Add a hidden `csrf_token` input + verify it server-side, OR convert to JSON submission |

### High

- **No `secure` flag on `wallos_login` cookie** (should be `secure=true`, `samesite=Strict`) — [app/login.php:225-228](app/login.php#L225-L228)
- **No session regeneration after OIDC login** — fix in [app/includes/oidc/handle_oidc_callback.php](app/includes/oidc/handle_oidc_callback.php) by calling `session_regenerate_id(true)` after successful auth
- **Weak file-upload validation** — uses `mime_content_type()` (spoofable) + no enforced size limit at [app/endpoints/subscription/add.php:278-285](app/endpoints/subscription/add.php#L278-L285). Switch to `getimagesize()` and add a size cap
- **No rate limiting on login** — brute-force possible. Add IP-based throttling (5 attempts / 15 min)
- **Password-reset token can be reused** before expiry — delete the token immediately after first valid use in [app/passwordreset.php](app/passwordreset.php)

### Medium

- **Custom CSS stored verbatim** — XSS vector via CSS expressions. [app/endpoints/settings/customcss.php](app/endpoints/settings/customcss.php). Sanitize or sandbox.
- **No security headers** in [app/nginx.default.conf](app/nginx.default.conf). Add `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, and a basic `Content-Security-Policy`.
- **DEMO_MODE bypass** — only password change is gated. Audit destructive endpoints (delete account, delete subscription, settings reset) and add the same guard.

### Positive findings

- **CSRF**: 73 of 74 JSON endpoints correctly route through [app/includes/validate_endpoint.php](app/includes/validate_endpoint.php) — solid baseline.
- **SQL injection**: prepared statements used everywhere; no string-concatenated user input found.
- **Password hashing**: uses PHP `password_hash()` with `PASSWORD_DEFAULT`.

---

## 🏗 Architecture & code quality

### Must-fix

| # | Issue | Effort |
|---|-------|--------|
| 1 | **`formatPrice()`, `getPricePerMonth()`, `getPriceConverted()` defined in 6+ places** — index.php, list_subscriptions.php, stats_calculations.php, sendnotifications.php, sendcancellationnotifications.php, sendcompletednotifications.php. Bug fix has to land in 6 files. Consolidate into a new `app/includes/formatting_helpers.php`. | ~2h |
| 2 | **CSS cascade fragility** — [modern.css](app/styles/designs/modern.css) has 356 `!important` declarations and [mobile-first.css](app/styles/mobile-first.css) has 134. The two files have visible overlap (e.g., `.next-payment-hero` rules duplicated). Refactor: keep structural rules only in mobile-first.css, restrict modern.css to `:root` variables + a few aesthetic overrides. | ~3-4h |
| 3 | **Monolithic page files** mixing SQL, business logic, and rendering: subscriptions.php (575 lines), index.php (544), stats.php (369), calendar.php (313). Split queries + helpers into `app/includes/`. | ~2h/page |
| 4 | **No standard error-response format** across 99 endpoint files. Some return `{success: false}`, some `{message: "..."}`, some bare `die()`. Build `app/includes/error_handler.php` with a shared response helper. | ~2h |

### Nice-to-have

- **N+1 query patterns** in [stats_calculations.php](app/includes/stats_calculations.php) (~5-7 SELECTs in a loop). Consolidate with joins.
- **Cycle → DateInterval logic duplicated** across `subscription_dates.php`, `markpaid.php`, `list_subscriptions.php`. The clean version in [subscription_dates.php:16-32](app/includes/subscription_dates.php#L16-L32) should be the single source.
- **Migration `000035.php` is non-idempotent** — runs `DELETE FROM total_yearly_cost` unconditionally; re-running clears data. Add a guard.
- **Untyped function parameters** throughout — Dockerfile uses PHP 8.3 but most helpers don't use type declarations. Adding them is mostly mechanical and improves IDE help.
- **Missing-translation fallback** in [app/includes/i18n/getlang.php:21](app/includes/i18n/getlang.php#L21) returns `"[i18n String Missing]"` literally — ugly in UI. Return the key or empty string instead.

---

## 📱 Remaining UX & accessibility gaps

The four pages we redesigned (dashboard, subscriptions, calendar, add-form) feel modern. The rest doesn't.

### High-impact (visible to most users)

| # | Page | Issue |
|---|------|-------|
| 1 | [app/stats.php](app/stats.php) | `.filtermenu-content` is fixed at 220px and absolute-positioned — overflows on a 390px phone. Filter drawer doesn't adapt. |
| 2 | [app/profile.php](app/profile.php) | `.avatar-select` fixed at 336px width — clips on small phones. No upload-error feedback. API key + button row breaks layout. |
| 3 | [app/admin.php](app/admin.php) | "Create user" form lays out as inline row that becomes unreadable on mobile. User-list rows don't stack cleanly. |
| 4 | All async pages | **No loading state** during fetch — 71 fetch() calls across the JS, only error toast exists. No spinner / skeleton. |
| 5 | Filter UI in stats.php | `<div class="filter-title" onClick=...>` — not keyboard-accessible. Filter items also `<div>`-based. Convert to `<button>`. |
| 6 | Dashboard meta line | `modernRelativeDayLabel()` returns hardcoded English ("today", "tomorrow", "in 3 days", "X days late"). Should use translate() with new i18n keys. |

### Medium-impact

- **Empty states missing** for stats.php (zero results from filters), calendar.php (no payments this month). Both currently render a blank grid.
- **Loading states**: add a spinner overlay during chart rendering in stats.php; same for filtering subscriptions list.
- **Error states**: enhance [app/scripts/common.js](app/scripts/common.js) `showErrorMessage` to handle 401 (redirect to login) and network failures (actionable retry message).
- **`<form>` labels** — Admin "Create user" inputs lack explicit `<label>` associations.
- **Focus indicators** — subscription cards and calendar cells have no visible `:focus-visible` outline; keyboard users can't tell where they are.
- **Aria-labels missing** on icon-only buttons (FAB, swipe actions, search-logo button, autofill date button). Add `aria-label="Add subscription"` etc.

### Low-impact / polish

- **Script loading is render-blocking** — `all.js`, `common.js`, `modern.js` are loaded synchronously in `<head>` of [app/includes/header.php](app/includes/header.php). Adding `defer` would speed first paint.
- **Service worker precaches all i18n files** even though only one is used per session. Trim to active language.
- **Custom CSS textarea** in settings has no syntax highlighting / preview.
- **No PWA offline indicator** — when `navigator.onLine === false` users have no idea they're seeing stale data.
- **Notification UX**: email template lacks "Mark as paid" deep-link; no quiet-hours support; no grouping when N payments fall on the same day.

### Feature gaps people commonly ask for

- Spending forecast (projected annual spend) on dashboard / stats
- Price-change tracking — alert when a subscription's price goes up
- iCal / Google Calendar export of payment dates
- Bulk-edit (batch enable/disable/categorize)
- Multi-currency overview vs main currency on stats

---

## Implementation plan — sequenced roadmap

You picked **all three batches prioritized**, **criticals + highs** for security depth, and **iCal / Google Calendar export** as the one feature to add. Below is a phase-by-phase plan ordered by risk reduction → user-visible polish → maintainability → feature.

Each phase is independently shippable — stop and ship whenever you want.

---

### Phase 1 — Security: criticals + highs — DONE ✅

**Goal:** close the 3 critical holes and the 5 high-severity issues. Touches 6 files. Almost no UI change.

**Status (2026-05-10): completed and smoke-tested.** The original Claude pass had most Phase 1 work in place, but one important gap remained: `checksession.php` still accepted any remember-me token in `login_disabled` mode. That is now fixed by always matching the cookie token against `login_tokens`. I also added a hidden CSRF field to the subscription form, tightened the auth cookie helper to `SameSite=Strict`, cleaned logout cookie deletion, validated OIDC `state`, and made login throttling ignore spoofable proxy headers unless `WALLOS_TRUST_PROXY_HEADERS=true`.

| Step | File | Change |
|---|---|---|
| 1.1 | [app/login.php:86](app/login.php#L86) | Replace `"abc123ABC"` literal with `bin2hex(random_bytes(32))` so demo-mode remember-me cookies are unforgeable |
| 1.2 | [app/login.php:225-228](app/login.php#L225-L228) | Add `'secure' => isset($_SERVER['HTTPS'])`, `'samesite' => 'Strict'`, `'httponly' => true` to the `setcookie('wallos_login', ...)` call |
| 1.3 | [app/endpoints/db/import.php](app/endpoints/db/import.php) and [app/endpoints/db/restore.php](app/endpoints/db/restore.php) | Before `extractTo()`, iterate `$zip->numFiles` and reject any name where `strpos($name, '..') !== false`, where `$name[0] === '/'`, or where `realpath` escapes the target dir |
| 1.4 | [app/subscriptions.php](app/subscriptions.php) (the form) + [app/endpoints/subscription/add.php](app/endpoints/subscription/add.php) | Add a hidden `<input name="csrf_token" value="…">` to the multipart form; verify with a new `validate_csrf_form()` helper alongside the existing JSON validator |
| 1.5 | [app/endpoints/subscription/add.php:278-285](app/endpoints/subscription/add.php#L278-L285) | Replace `mime_content_type()` with `getimagesize()` + reject files >2 MB (`$_FILES['logo']['size']`) |
| 1.6 | [app/includes/oidc/handle_oidc_callback.php](app/includes/oidc/handle_oidc_callback.php) | After successful auth, call `session_regenerate_id(true)` |
| 1.7 | [app/passwordreset.php](app/passwordreset.php) | After verifying the token, `DELETE FROM password_resets WHERE token = ?` *before* the password-update query (single-use token) |
| 1.8 | [app/login.php](app/login.php) | Add a tiny IP throttle: track failed attempts in a new `login_attempts` table (ip, count, last_attempt) — block when `count >= 5 AND last_attempt > NOW() - 15min`. Migration `000050.php` creates the table. |

**Verification:** test demo-token forge → rejected. Upload zip with `../etc/passwd` → rejected. Submit subscription form without CSRF → 403. Upload non-image as logo → rejected. 6 failed logins → blocked. Password-reset token used twice → second use rejected.

---

### Phase 2 — Finish the mobile-first redesign — DONE ✅

**Goal:** the four pages we already polished feel modern — make the rest match.

**Status (2026-05-10): completed and smoke-tested.** Mobile layout fixes were added for stats/profile/admin, loading overlays now wrap the major async flows, stats filters are keyboard-accessible, focus states/tab stops were added for clickable subscription/dashboard/calendar UI, icon-only controls received labels, dashboard/subscription relative date labels now use i18n keys, and stats/calendar have empty states.

| Step | What | Where |
|---|---|---|
| 2.1 | **stats.php mobile** — make `.filtermenu-content` a full-width drawer below 768px (currently fixed 220px, overflows). Stack `.statistic` cards 2-per-row. | Add to [app/styles/mobile-first.css](app/styles/mobile-first.css); reference [app/stats.php](app/stats.php) |
| 2.2 | **profile.php mobile** — clamp `.avatar-select` to `width: min(336px, 90vw)`. Stack the API-key input + button vertically below 480px. Add toast on upload failure. | [app/styles/mobile-first.css](app/styles/mobile-first.css), [app/profile.php](app/profile.php) |
| 2.3 | **admin.php mobile** — make the "create user" `.form-group-inline` a column below 600px so name/email/buttons stack legibly. | [app/styles/mobile-first.css](app/styles/mobile-first.css) |
| 2.4 | **Loading spinner** — add a tiny `.mf-spinner` overlay component to [app/styles/mobile-first.css](app/styles/mobile-first.css) and a `withSpinner(promise, target)` helper in [app/scripts/common.js](app/scripts/common.js). Wrap the major fetch calls in [app/scripts/subscriptions.js](app/scripts/subscriptions.js), [app/scripts/dashboard.js](app/scripts/dashboard.js), [app/scripts/stats.js](app/scripts/stats.js), [app/scripts/calendar.js](app/scripts/calendar.js). |
| 2.5 | **Better error toast** — extend `showErrorMessage` in [app/scripts/common.js](app/scripts/common.js) to detect 401 → redirect to login, network failure → "Network error, retry" with a retry button. |
| 2.6 | **Keyboard-accessible filter menu** — convert the `<div class="filter-title" onClick=...>` and `<div class="filter-item">` patterns in [app/stats.php:46-142](app/stats.php#L46-L142) to `<button>` elements. Add Escape-to-close, arrow-key navigation. |
| 2.7 | **Focus indicators** — add `.subscription-container:focus-visible`, `.subscription-item:focus-visible`, `.cal-cell:focus-visible` outlines to [app/styles/mobile-first.css](app/styles/mobile-first.css). Make `.subscription-container`, `.subscription-item`, `.cal-cell` keyboard-focusable (`tabindex="0"` in markup). |
| 2.8 | **Aria-labels on icon buttons** — FAB, swipe actions (Edit/Delete/Clone, Missing/Paid/Edit), three-dot menu trigger, search-logo button, autofill-date button. Already partly done; sweep the remaining ones. |
| 2.9 | **Translate relative-time labels** — `wallosRelativeDayLabel()` in [app/includes/list_subscriptions.php](app/includes/list_subscriptions.php) and `modernRelativeDayLabel()` in [app/index.php](app/index.php) currently return hardcoded English. Add new keys to [app/includes/i18n/en_US.php](app/includes/i18n/en_US.php) (`today`, `tomorrow`, `in_n_days`, `n_days_late` with sprintf-style placeholders) and have the helpers use `translate()`. |
| 2.10 | **Empty states** — add an `.empty-state` block in [app/stats.php](app/stats.php) when no data rows match the filter; same in [app/calendar.php](app/calendar.php) when zero payments fall in the visible month. |

**Verification:** open every main page (dashboard, subs, calendar, stats, profile, admin, settings, login) at 390px width — no horizontal scroll, every control reachable. Tab through the subscriptions list — focus outline visible. Apply a filter — spinner appears briefly. Switch language to a non-English one — relative-time labels translated.

---

### Phase 3 — Code-quality cleanup — DONE ✅

**Goal:** dedup, consistent error responses, less brittle CSS. No user-visible change.

**Status (2026-05-10): completed and smoke-tested.** Price/date/cycle helpers were consolidated into `app/includes/formatting_helpers.php` and the duplicated dashboard/subscription/stats/notification helper definitions were removed. Subscription cycle math now uses `getSubscriptionInterval()` from `subscription_dates.php`. API responses now have shared `apiSuccess()` / `apiError()` helpers and the first batch of subscription/settings endpoints uses them. `modern.css` was trimmed by moving duplicated structural rules to `mobile-first.css`, and its `!important` count dropped under the target. Migration `000035.php` is guarded against accidental re-runs, and missing i18n keys now fall back to the key itself instead of a raw placeholder.

| Step | What | Where |
|---|---|---|
| 3.1 | **Consolidate price helpers** — create [app/includes/formatting_helpers.php](app/includes/formatting_helpers.php) with `formatPrice`, `getPricePerMonth`, `getPriceConverted`, `wallosCycleSuffix`, `wallosRelativeDayLabel`. Replace inline definitions in [app/index.php](app/index.php), [app/includes/list_subscriptions.php](app/includes/list_subscriptions.php), [app/includes/stats_calculations.php](app/includes/stats_calculations.php), [app/endpoints/cronjobs/sendnotifications.php](app/endpoints/cronjobs/sendnotifications.php), [app/endpoints/cronjobs/sendcancellationnotifications.php](app/endpoints/cronjobs/sendcancellationnotifications.php), [app/endpoints/cronjobs/sendcompletednotifications.php](app/endpoints/cronjobs/sendcompletednotifications.php). |
| 3.2 | **Standardize error response** — create [app/includes/api_response.php](app/includes/api_response.php) with `apiSuccess($data = null, $msg = null)` and `apiError($msg, $http = 400, $details = null)` helpers. Migrate ~10 endpoints first (subscription/* and settings/*) to use them; leave the rest for follow-up. |
| 3.3 | **Single source for cycle math** — delete the duplicate cycle→DateInterval logic in [app/endpoints/subscription/markpaid.php:38-47](app/endpoints/subscription/markpaid.php#L38-L47) and [app/includes/list_subscriptions.php:26-34](app/includes/list_subscriptions.php#L26-L34); call into [app/includes/subscription_dates.php](app/includes/subscription_dates.php) instead. |
| 3.4 | **CSS dedup** — strip from [app/styles/designs/modern.css](app/styles/designs/modern.css) any rule that's already in [app/styles/mobile-first.css](app/styles/mobile-first.css) (mostly the `.next-payment-hero` block, swipe actions, dashboard card grid). Modern.css keeps only `:root` + `body.light` variable overrides + a handful of modern-specific aesthetics (gradients, shadows). Target: cut `!important` count from 356 → under 100. |
| 3.5 | **Migration `000035.php` guard** — wrap the `DELETE FROM total_yearly_cost` in a "table-just-created" check or move to a one-shot. Re-running any future migration shouldn't wipe data. |
| 3.6 | **i18n missing-key fallback** — change [app/includes/i18n/getlang.php:21](app/includes/i18n/getlang.php#L21) to return the key itself (or empty string) instead of `[i18n String Missing]`. |

**Verification:** `grep -rn "function formatPrice" app/` returns one line. Smoke-test each main page — visually identical. Run a re-build, run all migrations against an existing DB — no data loss. `wc -l` on modern.css before/after — should drop by ≥30%.

---

### Phase 4 — Feature: iCal / Google Calendar export — DONE ✅

**Goal:** users subscribe to a private webcal URL and their next-payment dates land in their real calendar (Google Calendar, Apple Calendar, Outlook).

**Status (2026-05-10): completed and smoke-tested.** Added a private per-user calendar feed token, enable/disable flag, webcal URL panel in Settings → Advanced, token regeneration, and a public `text/calendar` endpoint that emits recurring all-day `VEVENT`s with stable UIDs, price/payment metadata, and `RRULE`s derived from subscription cycle/frequency. Regenerating the token invalidates the old URL immediately; disabling the feed returns 403.

**Approach:**

| Step | What |
|---|---|
| 4.1 | **New endpoint** [app/endpoints/calendar/ical.php](app/endpoints/calendar/ical.php) — returns `Content-Type: text/calendar; charset=utf-8`. Auth via a new per-user secret token (so the URL can be public-ish) — generate on first access, store in `user.ical_token`. Migration `000051.php` adds the column. |
| 4.2 | **iCal generator** — for each enabled subscription, emit a `VEVENT` with: `SUMMARY` = subscription name, `DTSTART` = next_payment, `RRULE` based on cycle (`FREQ=MONTHLY;INTERVAL=$frequency` etc.), `DESCRIPTION` = price + payment method, `UID` = stable per-subscription. Reuse cycle helpers from [app/includes/subscription_dates.php](app/includes/subscription_dates.php). Plain string assembly — no library needed. |
| 4.3 | **Settings UI** — new section in [app/settings.php](app/settings.php) under "Advanced" tab: shows the personal webcal URL, a "Copy" button, a "Regenerate token" button (calls a new [app/endpoints/calendar/regenerate_ical_token.php](app/endpoints/calendar/regenerate_ical_token.php)), and a one-line "How to subscribe" with a `webcal://` link. |
| 4.4 | **Disable per-user** — checkbox "Enable iCal feed" in the same panel. When disabled, the endpoint returns 403. |

**Verification:** copy the webcal URL, paste into Google Calendar (Other calendars → From URL) — events appear with correct dates and recurrence. Edit a subscription's next-payment date in Wallos, refresh the calendar in 12-24h (Google's poll cycle) — date updates. Regenerate token — old URL stops working immediately.

---

## Critical files index

- Auth / sessions: [app/login.php](app/login.php), [app/passwordreset.php](app/passwordreset.php), [app/includes/oidc/](app/includes/oidc/)
- DB import/restore: [app/endpoints/db/import.php](app/endpoints/db/import.php), [app/endpoints/db/restore.php](app/endpoints/db/restore.php)
- Subscription form: [app/subscriptions.php](app/subscriptions.php), [app/endpoints/subscription/add.php](app/endpoints/subscription/add.php)
- Mobile-first CSS: [app/styles/mobile-first.css](app/styles/mobile-first.css), [app/styles/designs/modern.css](app/styles/designs/modern.css)
- Helpers to consolidate: [app/index.php](app/index.php), [app/includes/list_subscriptions.php](app/includes/list_subscriptions.php), [app/includes/stats_calculations.php](app/includes/stats_calculations.php), the three cron files in [app/endpoints/cronjobs/](app/endpoints/cronjobs/)
- New files to create: `app/includes/formatting_helpers.php`, `app/includes/api_response.php`, `app/endpoints/calendar/ical.php`, `app/endpoints/calendar/regenerate_ical_token.php`, `app/migrations/000050.php` (login_attempts), `app/migrations/000051.php` (ical_token column)

---

## End-to-end verification

After all four phases:

1. `docker compose up -d --build` rebuilds without errors.
2. Container reports `(healthy)` within 10s.
3. Hit `/login.php` 7× with wrong password — last 2 are rejected with throttle message.
4. Open every main page (dashboard, subs, calendar, stats, profile, admin, settings) at 390px and at 1280px — no horizontal scroll on phone, no layout regressions on desktop.
5. Tab through subscriptions list — visible focus outline on each card; press Enter — opens edit.
6. In Settings → Advanced → Calendar feed — copy URL, paste into Google Calendar — events show with correct recurrence.
7. `grep -rn "function formatPrice" /docker/Wallos/app/` returns exactly 1 hit (the new helper).
8. `grep -c "!important" /docker/Wallos/app/styles/designs/modern.css` returns under 100.

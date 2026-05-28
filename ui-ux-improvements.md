# Wallos · UI/UX improvements (2026-05-27 review pass)

Driven headless against a logged-in instance at 1440×900 and 390×844.
Screenshots in `/tmp/wallos_shots/`. Work through one at a time.

---

## High-impact bugs

- [x] **1. Mobile bottom nav covers page content.** On dashboard the "BMW M-Sport 330E" row is hidden under the nav; on settings the Fuel-unit select is covered; on calendar the "Amount due this month" stat is pushed below it.
  - Fix: [app/styles/mobile-first.css:854](app/styles/mobile-first.css#L854) — bump `padding-bottom` from `calc(80px + safe-area)` to `calc(110px + env(safe-area-inset-bottom))` (matches the `1737` rule's floor).

- [x] **2. "Hello Łukasz" header is awkwardly small/centered on desktop dashboard.** Same weight as a subtitle, floats above the hero card with no anchor.
  - Fix: left-align and bump to ~24px, or merge it into the hero card.

- [x] **3. Duplicate "Next Payment" hero card on Dashboard and Subscriptions.** Wastes vertical space on Subscriptions.
  - Fix: drop the card on [app/subscriptions.php](app/subscriptions.php), or shrink to a 1-line "Next: AnPost €63.53 in 2 days" banner.

## Visual hierarchy / scannability

- [x] **4. Calendar markers are nearly invisible** on desktop — tiny faint blue under-bars. Mobile dots are OK.
  - Fix: in [app/calendar.php](app/calendar.php) replace the bar with a colored dot + count badge, or render the subscription logo at 16×16.

- [x] **5. Calendar header missing "previous month" arrow.** Only `>` is shown.
  - Fix: add a `<` next to "May 2026" in [app/calendar.php](app/calendar.php).

- [x] **6. Selected calendar day is solid black** — clashes with the blue brand palette.
  - Fix: use brand blue with white text.

- [x] **7. Stats page = wall of 13 single-stat tiles, no grouping.** "Last Petrol Fill" sits next to "Budget Remaining" with no logical link.
  - Fix: in [app/stats.php](app/stats.php) group into *Costs*, *Budget*, *Vehicles* — three sub-sections with 3–4 tiles each.

- [x] **8. "Most Expensive Subscription" shows logo only, no name.**
  - Fix: add the subscription name as text next to the logo on [app/stats.php](app/stats.php).

- [x] **9. Subscription rows are visually noisy** — 3–5 colored tag pills (Transport, Aviva, Direct Debit, working day…) at the same weight as the price.
  - Fix: in [app/includes/list_subscriptions.php](app/includes/list_subscriptions.php) demote secondary metadata to a smaller gray chip; reserve colored pills for category only.

## Affordance / consistency

- [x] **10. Active nav chip and "+ Add" CTA use the same primary blue.** Selection state and primary action look identical.
  - Fix: tone the active chip down to `rgba(blue, .12)` + colored text, or make the FAB a contrasting accent.

- [x] **11. Profile/Notifications page is a narrow single column on 1440px** — wastes ~60% of horizontal space.
  - Fix: in [app/profile.php](app/profile.php) use a 2-column grid (User Details | sidebar with 2FA, API Key, Account export) above ~1100px.

- [x] **12. API Key field renders empty with "API Key" placeholder** — no way to tell if a key exists.
  - Fix: if a key exists, show masked (`••••••••3a2f`) with a copy button; if not, label "No key generated yet" on [app/profile.php](app/profile.php).

- [x] **13. Mobile settings tab strip lost its icons** (only General has tinted bg on desktop; mobile shows no icons).
  - Fix: verify the media-query in [app/styles/mobile-first.css](app/styles/mobile-first.css) isn't hiding the icons unintentionally.

- [x] **14. Star ratings render as raw `★★` Unicode** on `AnPost`, `Vodafone`, `AIB Mortgage` rows — no spacing/sizing.
  - Fix: render as proper colored icons matching the chip styling in [app/includes/list_subscriptions.php](app/includes/list_subscriptions.php).

## Smaller polish

- [x] **15.** Date "Last Petrol Fill" is raw ISO `2026-05-25` while every other date uses localized "May 29 / Jun 1" — pipe through the same formatter.
- [x] **16.** "Paid This Cycle" heading has no visual separation from its empty state; cards above (Your Budget) look more like the section than its own children. Add an empty-state illustration or move the heading inside a card.
- [x] **17.** Desktop dashboard stacks 3 H1-sized visual blocks (greeting, hero, Upcoming list) — only one should dominate.

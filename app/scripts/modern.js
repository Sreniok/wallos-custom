/* Wallos · Modern theme runtime
   Provides:
   1. Swipe-to-reveal Edit/Delete/Clone on subscription rows (modern only)
   2. Floating action button wiring (mobile only)
   3. Settings tabs (universal — improves UX for all themes)
*/

(function () {
    'use strict';

    const ACTION_PX = 92;
    const REVEAL_PX = 3 * ACTION_PX;
    const TRIGGER_PX = 60;

    /* After a swipe, iOS often synthesises a `click` at the release point.
       Because the row slid sideways, that point can land on the revealed
       action panel — and the action/close/toggle handlers (registered as
       document-capture listeners at init) would fire before any per-row
       listener could stop it, snapping the row shut. This timestamp lets
       the earliest-registered guard swallow exactly that one stray click. */
    let swipeClickBlockUntil = 0;

    function wireSwipeClickGuard() {
        if (document.documentElement.dataset.modernSwipeGuard) return;
        document.documentElement.dataset.modernSwipeGuard = '1';
        document.addEventListener('click', (e) => {
            if (!swipeClickBlockUntil) return;
            if (Date.now() >= swipeClickBlockUntil) { swipeClickBlockUntil = 0; return; }
            swipeClickBlockUntil = 0;          // consume only this one click
            e.stopImmediatePropagation();
            e.preventDefault();
        }, true);
    }

    function isModernActive() {
        return document.body.classList.contains('design-modern');
    }

    /* The new mobile-first layout (FAB, swipe, hero, card grids) runs for
       every design theme — only the visual aesthetic differs by theme.
       Use this gate when the feature should be active for all themes. */
    function mobileFirstActive() {
        return window.matchMedia('(max-width: 768px)').matches;
    }

    /* ──────────────────────────────
       SWIPE-TO-REVEAL ACTIONS
       ────────────────────────────── */

    function subscriptionActionClass(action) {
        return {
            'edit-subscription': 'edit',
            'delete-subscription': 'delete',
            'clone-subscription': 'clone',
            'mark-paid': 'dash-paid',
            'renew-subscription': 'dash-missing',
            'fuel-nfc': 'clone',
            'fuel-history': 'dash-paid',
            'fuel-edit': 'edit',
            'fuel-delete': 'delete'
        }[action] || 'edit';
    }

    function escapeAttr(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function sortSubscriptionActions(actions) {
        const order = {
            'clone-subscription': 10,
            'fuel-nfc': 10,
            'delete-subscription': 20,
            'fuel-history': 20,
            'fuel-delete': 30,
            'mark-paid': 30,
            'renew-subscription': 40,
            'edit-subscription': 50,
            'fuel-edit': 50
        };

        return actions.sort((a, b) => {
            const aOrder = order[a.dataset.action] || 100;
            const bOrder = order[b.dataset.action] || 100;
            return aOrder - bOrder;
        });
    }

    function modernActionIcon(action) {
        const icons = {
            'clone-subscription': '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="8" width="11" height="11" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v1"></path></svg>',
            'delete-subscription': '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"></path><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path></svg>',
            'fuel-delete': '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"></path><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path></svg>',
            'edit-subscription': '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"></path></svg>',
            'fuel-edit': '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"></path></svg>',
            'fuel-history': '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7"></path><path d="M3 3v6h6"></path><path d="M12 7v5l3 2"></path></svg>',
            'fuel-nfc': '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 8c2 2.6 2 5.4 0 8"></path><path d="M10 5c3.8 4.6 3.8 9.4 0 14"></path><path d="M14 8c2 2.6 2 5.4 0 8"></path><path d="M18 5c3.8 4.6 3.8 9.4 0 14"></path></svg>',
            'mark-paid': '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6 9 17l-5-5"></path></svg>',
            'renew-subscription': '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12a9 9 0 0 1 15.2-6.5L21 8"></path><path d="M21 3v5h-5"></path><path d="M21 12a9 9 0 0 1-15.2 6.5L3 16"></path><path d="M3 21v-5h5"></path></svg>'
        };

        return icons[action] || icons['edit-subscription'];
    }

    function buildActionsPanel(container) {
        if (container.querySelector('.modern-swipe-actions')) return;

        const sub = container.querySelector('.subscription');
        if (!sub) return;
        const id = sub.dataset.id;
        if (!id) return;

        const panel = document.createElement('div');
        panel.className = 'modern-swipe-actions';
        const actions = sortSubscriptionActions(Array.from(sub.querySelectorAll('.actions > li[data-action]')));
        // Cap the reveal at the dashboard's fixed width. A manual subscription
        // has 5 actions (edit/delete/clone/paid/renew) → 460px, which on a
        // phone slides the whole row off-screen and clips the panel (this is
        // why the dashboard worked and the list didn't). The buttons use
        // flex:1, so they share the capped width gracefully.
        const revealPx = Math.min(actions.length * ACTION_PX, REVEAL_PX);
        container.style.setProperty('--modern-action-width', `${revealPx}px`);
        container.classList.add('modern-actions-ready');
        panel.innerHTML = actions.map((item) => {
            const action = item.dataset.action;
            const label = action === 'fuel-nfc'
                ? item.textContent.trim()
                : item.getAttribute('aria-label') || item.getAttribute('title') || item.textContent.trim();
            const nfcUrl = item.dataset.nfcUrl ? ` data-nfc-url="${escapeAttr(item.dataset.nfcUrl)}"` : '';
            return `
                <button class="modern-swipe-action ${subscriptionActionClass(action)}" data-modern-action="${action}" data-id="${id}"${nfcUrl} aria-label="${escapeAttr(label)}">
                    ${modernActionIcon(action)}
                    ${label}
                </button>
            `;
        }).join('');
        // Insert as first child so the .subscription overlays it
        container.insertBefore(panel, container.firstChild);
    }

    function closeAllExcept(except) {
        document.querySelectorAll('.subscription-container.modern-swipe-revealed').forEach(c => {
            if (c !== except) {
                c.classList.remove('modern-swipe-revealed');
                const sub = c.querySelector('.subscription');
                if (sub) sub.style.transform = '';
            }
        });
    }

    function revealWidthOf(host, fallback) {
        return parseFloat(host.style.getPropertyValue('--modern-action-width')) || fallback;
    }

    /* Unified swipe-to-reveal core, shared by subscription rows, dashboard
       cards and the next-payment hero so the gesture feels identical and the
       iOS smoothing lives in one place:
         · translate3d + a JS-managed will-change → the row gets its own GPU
           layer, so Safari doesn't repaint the card on every move frame;
         · pointermove writes are coalesced into one rAF tick (iOS fires them
           far faster than 60fps and per-event style writes stutter);
         · on release the *inline* transform is animated to the snapped
           target, then handed back to the CSS class only once the transition
           ends — this removes the inline→class swap flash iOS showed on
           release (and the dashboard's old 228px-drag → 276px-rest jump). */
    function bindSwipe(host, drag, opts) {
        opts = opts || {};
        if (drag.dataset.modernSwipeBound) return;
        drag.dataset.modernSwipeBound = '1';

        const fallbackWidth = opts.fallbackWidth || REVEAL_PX;
        let startX = 0, startY = 0, baseX = 0, lastX = 0;
        let dragging = false, axis = null, suppressClick = false;
        let rafId = 0, pendingX = 0, settleTimer = 0;

        const width = () => revealWidthOf(host, fallbackWidth);
        const paint = () => { rafId = 0; drag.style.transform = `translate3d(${pendingX}px,0,0)`; };
        const queue = (x) => { pendingX = x; if (!rafId) rafId = requestAnimationFrame(paint); };

        const settle = () => {
            if (settleTimer) { clearTimeout(settleTimer); settleTimer = 0; }
            drag.removeEventListener('transitionend', onEnd);
            if (dragging) return;            // a fresh drag took over — leave it
            drag.style.transform = '';       // hand the resting state to CSS
            drag.style.willChange = '';
        };
        const onEnd = (e) => { if (e.propertyName === 'transform') settle(); };

        const onDown = (e) => {
            if (!mobileFirstActive()) return;
            if (opts.guard && opts.guard(e)) return;
            if (dragging) return;            // ignore a re-entrant/2nd pointer
            if (settleTimer) { clearTimeout(settleTimer); settleTimer = 0; }
            drag.removeEventListener('transitionend', onEnd);
            startX = e.clientX;
            startY = e.clientY;
            baseX = host.classList.contains('modern-swipe-revealed') ? -width() : 0;
            lastX = baseX;
            dragging = true;
            axis = null;
            suppressClick = false;
            host.classList.add('modern-swipe-dragging');
            drag.style.willChange = 'transform';
            try { drag.setPointerCapture(e.pointerId); } catch (_) { }
        };

        const onMove = (e) => {
            if (!dragging) return;
            const dx = e.clientX - startX;
            const dy = e.clientY - startY;
            if (!axis) {
                if (Math.abs(dx) < 8 && Math.abs(dy) < 8) return;
                axis = Math.abs(dx) > Math.abs(dy) ? 'x' : 'y';
                if (axis === 'y') {
                    dragging = false;
                    host.classList.remove('modern-swipe-dragging');
                    drag.style.willChange = '';
                    return;
                }
                if (opts.onOpenStart) opts.onOpenStart();
            }
            let next = baseX + dx;
            const w = width();
            if (next > 0) next = 0;
            if (next < -w) next = -w - (Math.abs(next + w) * 0.2);
            lastX = next;          // remember the real rendered offset
            queue(next);
            if (Math.abs(dx) > 8) suppressClick = true;
        };

        const onUp = (e) => {
            if (rafId) { cancelAnimationFrame(rafId); rafId = 0; }
            host.classList.remove('modern-swipe-dragging');
            if (!dragging) { drag.style.willChange = ''; return; }
            dragging = false;
            try { drag.releasePointerCapture(e.pointerId); } catch (_) { }
            // Decide from the last offset we actually rendered during the
            // drag — iOS frequently delivers pointerup/pointercancel with
            // zeroed/NaN coordinates, which made `e.clientX - startX` snap
            // the row shut on release.
            const w = width();
            const shouldOpen = (baseX === 0)
                ? lastX <= -TRIGGER_PX
                : lastX <= -w + TRIGGER_PX;
            drag.style.transform = `translate3d(${shouldOpen ? -w : 0}px,0,0)`;
            host.classList.toggle('modern-swipe-revealed', shouldOpen);
            drag.addEventListener('transitionend', onEnd);
            settleTimer = setTimeout(settle, 360);
            // We actually dragged → swallow the stray click iOS fires next,
            // wherever it lands (the row has slid out from under the finger).
            if (suppressClick) swipeClickBlockUntil = Date.now() + 450;
        };

        drag.addEventListener('pointerdown', onDown);
        drag.addEventListener('pointermove', onMove);
        drag.addEventListener('pointerup', onUp);
        drag.addEventListener('pointercancel', onUp);
    }

    function attachSwipe(container) {
        const sub = container.querySelector('.subscription');
        if (!sub) return;
        bindSwipe(container, sub, {
            // Don't start a swipe from the actions menu trigger
            guard: (e) => !!e.target.closest('.actions, .actions-expand'),
            onOpenStart: () => closeAllExcept(container)
        });
    }

    function wireActionClicks() {
        // Single delegated listener — wires once, applies to dynamically added rows too
        if (document.body.dataset.modernActionsBound) return;
        document.body.dataset.modernActionsBound = '1';

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.modern-swipe-action[data-modern-action]');
            if (!btn) return;
            e.stopPropagation();
            e.preventDefault();
            const action = btn.dataset.modernAction;
            const id = parseInt(btn.dataset.id, 10);
            if (!id) return;

            // Close the row
            const container = btn.closest('.subscription-container');
            if (container) container.classList.remove('modern-swipe-revealed');

            // Delegate to existing global handlers
            if (action === 'edit-subscription' && typeof openEditSubscription === 'function') {
                openEditSubscription(e, id);
            } else if (action === 'delete-subscription' && typeof deleteSubscription === 'function') {
                deleteSubscription(e, id);
            } else if (action === 'clone-subscription' && typeof cloneSubscription === 'function') {
                cloneSubscription(e, id);
            } else if (action === 'mark-paid' && typeof markSubscriptionPaid === 'function') {
                markSubscriptionPaid(e, id);
            } else if (action === 'renew-subscription' && typeof renewSubscription === 'function') {
                renewSubscription(e, id);
            } else if (action === 'fuel-history' && typeof openFuelVehicleHistory === 'function') {
                openFuelVehicleHistory(id, 0);
            } else if (action === 'fuel-edit' && typeof openEditFuelVehicle === 'function') {
                openEditFuelVehicle(e, id);
            } else if (action === 'fuel-delete' && typeof deleteFuelVehicle === 'function') {
                deleteFuelVehicle(e, id);
            } else if (action === 'fuel-nfc' && typeof copyFuelVehicleNfcLink === 'function') {
                copyFuelVehicleNfcLink(btn);
            }
        }, true);

        // Click outside any revealed row → close
        document.addEventListener('click', (e) => {
            if (e.target.closest('.subscription-container')) return;
            closeAllExcept(null);
        });
    }

    function toggleDesktopReveal(target, event) {
        if (mobileFirstActive()) return false;
        const container = target.closest('.subscription-container, .dashboard-swipe-row, .nph-swipe');
        if (!container || event.target.closest('button, a, input, select, textarea, .actions, .actions-expand, .modern-swipe-actions')) {
            return false;
        }

        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();

        if (container.classList.contains('subscription-container')) {
            closeAllExcept(container);
        } else {
            // Dashboard "upcoming" rows and the single-hero share one
            // open-at-a-time group so they reveal identically on desktop.
            closeDashboardLike(container);
            closeAllExcept(null);
        }

        container.classList.toggle('modern-swipe-revealed');
        return true;
    }

    function wireDesktopRevealClicks() {
        if (document.body.dataset.modernDesktopRevealBound) return;
        document.body.dataset.modernDesktopRevealBound = '1';

        const revealSelector = '.subscription-container > .subscription, .dashboard-swipe-row > .subscription-item, .nph-swipe > .nph-item';

        document.addEventListener('click', (e) => {
            const card = e.target.closest(revealSelector);
            if (card) toggleDesktopReveal(card, e);
        }, true);

        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            const card = e.target.closest(revealSelector);
            if (card && toggleDesktopReveal(card, e)) {
                card.focus();
            }
        }, true);
    }

    function initSwipe() {
        document.querySelectorAll('.subscription-container').forEach(container => {
            buildActionsPanel(container);
            attachSwipe(container);
        });
        wireActionClicks();
        wireDesktopRevealClicks();
    }

    /* ──────────────────────────────
       FLOATING ACTION BUTTON (FAB)
       ────────────────────────────── */

    function initFab() {
        if (!mobileFirstActive()) return;

        // On subscriptions.php addSubscription() is in scope; elsewhere, navigate
        // to subscriptions.php?add=1 — that file already auto-opens the form on
        // load when ?add is present (see subscriptions.php line ~552).
        const trigger = () => {
            if (typeof toggleQuickAddMenu === 'function') {
                toggleQuickAddMenu(fab);
                return;
            }
            if (typeof addSubscription === 'function') {
                try { addSubscription(); return; } catch (_) { /* fall through */ }
            }
            window.location.href = 'subscriptions.php?add=1';
        };

        // Build (or rebuild) the FAB
        const existing = document.querySelector('.modern-fab');
        if (existing) existing.remove();

        const fab = document.createElement('button');
        fab.className = 'modern-fab';
        fab.type = 'button';
        fab.setAttribute('aria-label', 'Add subscription');
        fab.innerHTML = '+';
        fab.addEventListener('click', trigger);

        // Place the FAB INSIDE the bottom nav between items 2 (Subscriptions)
        // and 3 (Calendar) so the 4 nav links flex around it cleanly. This
        // avoids the absolute-positioning overlap the previous approach had.
        const mobileNav = document.querySelector('.mobile-nav');
        if (mobileNav && document.body.classList.contains('mobile-navigation')) {
            fab.classList.add('in-nav');
            const navItems = mobileNav.querySelectorAll(':scope > a');
            // Subscriptions is the 2nd nav item; insert FAB right after it
            const after = navItems[1];
            if (after && after.nextSibling) {
                mobileNav.insertBefore(fab, after.nextSibling);
            } else if (after) {
                mobileNav.appendChild(fab);
            } else {
                mobileNav.appendChild(fab);
            }
        } else {
            // No bottom nav (mobile_nav setting is off) — fall back to floating
            document.body.appendChild(fab);
        }
    }

    /* ──────────────────────────────
       SETTINGS TABS
       ────────────────────────────── */

    function initSettingsTabs() {
        const settings = document.querySelector('section.settings');
        if (!settings) return;
        const tabsNav = settings.querySelector('.settings-tabs');
        if (!tabsNav) return; // settings.php hasn't been refactored — skip
        const sections = settings.querySelectorAll('.account-section[data-settings-tab]');
        if (!sections.length) return;

        function activate(tabKey, opts) {
            const scroll = !!(opts && opts.scroll);
            tabsNav.querySelectorAll('.settings-tab').forEach(t => {
                t.classList.toggle('active', t.dataset.tab === tabKey);
            });
            sections.forEach(s => {
                s.classList.toggle('is-hidden', s.dataset.settingsTab !== tabKey);
            });
            try {
                localStorage.setItem('wallosSettingsTab', tabKey);
                if (history.replaceState) history.replaceState(null, '', '#' + tabKey);
            } catch (_) { }
            if (scroll) {
                window.scrollTo({ top: settings.offsetTop - 12, behavior: 'instant' });
            }
        }

        tabsNav.addEventListener('click', (e) => {
            const tab = e.target.closest('.settings-tab');
            if (!tab) return;
            activate(tab.dataset.tab);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        // "Stuck" state — collapses padding once the sticky nav reaches the top.
        const sentinel = document.createElement('div');
        sentinel.className = 'settings-tabs-sentinel';
        sentinel.setAttribute('aria-hidden', 'true');
        tabsNav.parentNode.insertBefore(sentinel, tabsNav);
        if ('IntersectionObserver' in window) {
            const headerEl = document.querySelector('body > header');
            const headerH = headerEl ? Math.round(headerEl.getBoundingClientRect().height) : 70;
            const io = new IntersectionObserver((entries) => {
                tabsNav.classList.toggle('is-stuck', !entries[0].isIntersecting);
            }, { rootMargin: `-${headerH + 1}px 0px 0px 0px`, threshold: 0 });
            io.observe(sentinel);
        }

        // Initial tab — from URL hash, localStorage, or first
        const fromHash = (location.hash || '').replace('#', '');
        const fromStore = localStorage.getItem('wallosSettingsTab');
        const validTabs = Array.from(tabsNav.querySelectorAll('.settings-tab')).map(t => t.dataset.tab);
        const initial = validTabs.includes(fromHash) ? fromHash
                      : validTabs.includes(fromStore) ? fromStore
                      : validTabs[0];
        if (initial) activate(initial, { scroll: false });
    }

    /* ──────────────────────────────
       BOOTSTRAP
       ────────────────────────────── */

    /* ──────────────────────────────
       DASHBOARD SWIPE — wraps each .subscription-item card on
       index.php with action buttons (Payment Missing / Already
       Paid / Edit) bound to the same handlers used by the modal.
       ────────────────────────────── */

    function extractDashboardId(card) {
        const rawArgs = card.getAttribute('data-args');
        if (!rawArgs) return null;

        try {
            const args = JSON.parse(rawArgs);
            const id = Array.isArray(args) ? parseInt(args[0], 10) : parseInt(args, 10);
            return Number.isFinite(id) ? id : null;
        } catch (_) {
            return null;
        }
    }

    function buildDashboardActionsPanel(card, id) {
        if (card.parentElement && card.parentElement.classList.contains('dashboard-swipe-row')) return null;

        const wrapper = document.createElement('div');
        wrapper.className = 'dashboard-swipe-row';
        const actions = document.createElement('div');
        actions.className = 'modern-swipe-actions';
        wrapper.style.setProperty('--modern-action-width', `${3 * ACTION_PX}px`);

        // Fuel vehicle cards get their own drawer (Edit / History / Delete)
        // wired to the global expenses.js handlers via data-action="fuel-*".
        // id here is the vehicle id (from data-args on the .petrol-subscription-card).
        if (card.classList.contains('petrol-subscription-card')) {
            wrapper.classList.add('fuel-swipe-row');
            actions.innerHTML = `
                <button class="modern-swipe-action dash-delete" data-action="fuel-delete" data-id="${id}" aria-label="Delete vehicle">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"></path><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path></svg>
                    Delete
                </button>
                <button class="modern-swipe-action dash-history" data-action="fuel-history" data-id="${id}" aria-label="Petrol history">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7"></path><path d="M3 3v6h6"></path><path d="M12 7v5l3 2"></path></svg>
                    History
                </button>
                <button class="modern-swipe-action dash-edit" data-action="fuel-edit" data-id="${id}" aria-label="Edit vehicle">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"></path></svg>
                    Edit
                </button>
            `;
        } else {
            actions.innerHTML = `
                <button class="modern-swipe-action dash-missing" data-dashboard-action="missing" data-id="${id}" aria-label="Payment Missing">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 9v4"></path><path d="M12 17h.01"></path><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"></path></svg>
                    Missing
                </button>
                <button class="modern-swipe-action dash-paid" data-dashboard-action="paid" data-id="${id}" aria-label="Already Paid">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6 9 17l-5-5"></path></svg>
                    Paid
                </button>
                <button class="modern-swipe-action dash-edit" data-dashboard-action="edit" data-id="${id}" aria-label="Edit">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"></path></svg>
                    Edit
                </button>
            `;
        }
        const parent = card.parentNode;
        parent.insertBefore(wrapper, card);
        wrapper.appendChild(actions);
        wrapper.appendChild(card);
        return wrapper;
    }

    /* Collapse any open dashboard card or hero (except `keep`). */
    function closeDashboardLike(keep) {
        document.querySelectorAll('.dashboard-swipe-row.modern-swipe-revealed, .nph-swipe.modern-swipe-revealed').forEach(h => {
            if (h === keep) return;
            h.classList.remove('modern-swipe-revealed');
            const d = h.querySelector(':scope > .subscription-item, :scope > .nph-item');
            if (d) d.style.transform = '';
        });
    }

    function attachDashboardSwipe(wrapper) {
        const card = wrapper.querySelector(':scope > .subscription-item');
        if (!card) return;
        bindSwipe(wrapper, card, {
            fallbackWidth: 3 * ACTION_PX,
            onOpenStart: () => { closeDashboardLike(wrapper); closeAllExcept(null); }
        });
    }

    /* Next-payment hero stays purely informational — no swipe actions, no
       Missing/Paid/Edit drawer. Those actions remain on the dashboard cards
       below the hero (overdue / upcoming lists). */
    function initHeroSwipe() {
        return;
    }

    function wireDashboardActionClicks() {
        if (document.body.dataset.modernDashActionsBound) return;
        document.body.dataset.modernDashActionsBound = '1';
        // Fuel action buttons handle themselves via expenses.js; just close
        // the swipe drawer so users see immediate feedback.
        document.addEventListener('click', (e) => {
            const fuelBtn = e.target.closest('.dashboard-swipe-row .modern-swipe-action[data-action^="fuel-"]');
            if (!fuelBtn) return;
            const wrapper = fuelBtn.closest('.dashboard-swipe-row');
            if (wrapper) wrapper.classList.remove('modern-swipe-revealed');
        }, true);
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.modern-swipe-action[data-dashboard-action]');
            if (!btn) return;
            e.stopPropagation();
            e.preventDefault();
            const action = btn.dataset.dashboardAction;
            const id = parseInt(btn.dataset.id, 10);
            if (!id) return;

            const wrapper = btn.closest('.dashboard-swipe-row, .nph-swipe');
            if (wrapper) wrapper.classList.remove('modern-swipe-revealed');

            const t = (key, fallback) => {
                if (typeof translateWithFallback === 'function') return translateWithFallback(key, fallback);
                if (typeof translate === 'function') { try { return translate(key) || fallback; } catch (_) { return fallback; } }
                return fallback;
            };

            if (action === 'missing' && typeof runSubscriptionModalAction === 'function') {
                runSubscriptionModalAction('endpoints/subscription/renew.php', id, t('success', 'Success'));
            } else if (action === 'paid' && typeof runSubscriptionModalAction === 'function') {
                runSubscriptionModalAction('endpoints/subscription/markpaid.php', id, t('payment_recorded', 'Payment recorded.'));
            } else if (action === 'edit') {
                if (typeof openDashboardEditModal === 'function') {
                    openDashboardEditModal(id);
                } else {
                    window.location.href = 'subscriptions.php?edit=' + id;
                }
            }
        }, true);
    }

    function initDashboardSwipe() {
        // Only wrap upcoming/overdue/paid cards — not budget/active/savings tiles
        const selectors = [
            '.upcoming-subscriptions .subscription-item.subscription-item-clickable',
            '.overdue-subscriptions .subscription-item.subscription-item-clickable',
            '.paid-this-month-subscriptions .subscription-item.subscription-item-clickable'
        ];
        document.querySelectorAll(selectors.join(',')).forEach(card => {
            const id = extractDashboardId(card);
            if (!id) return;
            const wrapper = buildDashboardActionsPanel(card, id) || card.closest('.dashboard-swipe-row');
            if (wrapper) attachDashboardSwipe(wrapper);
        });
        wireDashboardActionClicks();
        wireDesktopRevealClicks();
    }

    /* ──────────────────────────────
       FAB visibility — hide while a form/modal is open
       ────────────────────────────── */
    function initFabVisibility() {
        const openClasses = ['no-scroll'];
        const observer = new MutationObserver(() => {
            const formOpen = document.querySelector('.subscription-form.is-open, .subscription-modal.is-open, #subscriptionModal.is-open, #editEmbedOverlay.is-open');
            const noScroll = openClasses.some(c => document.body.classList.contains(c));
            document.body.classList.toggle('fab-hidden', !!(formOpen || noScroll));
        });
        observer.observe(document.body, { attributes: true, attributeFilter: ['class'], subtree: true, childList: true });
    }

    function init() {
        wireSwipeClickGuard();   // must register before the action handlers
        initSwipe();
        initDashboardSwipe();
        initHeroSwipe();
        initFab();
        initFabVisibility();
        initSettingsTabs();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Re-init swipe when the subscription list is replaced (some Wallos pages
    // re-render via fetch, e.g., after applying filters)
    const obs = new MutationObserver(() => {
        document.querySelectorAll('.subscription-container').forEach(container => {
            if (!container.querySelector('.modern-swipe-actions')) {
                buildActionsPanel(container);
                attachSwipe(container);
            }
        });
        initDashboardSwipe();
        initHeroSwipe();
    });
    if (document.body) {
        obs.observe(document.body, { childList: true, subtree: true });
    } else {
        document.addEventListener('DOMContentLoaded', () => {
            obs.observe(document.body, { childList: true, subtree: true });
        });
    }

    // Expose for theme switcher to re-init when modern is enabled at runtime
    window.__modernThemeInit = init;
})();

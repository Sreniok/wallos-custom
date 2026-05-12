/* Wallos · Modern theme runtime
   Provides:
   1. Swipe-to-reveal Edit/Delete/Clone on subscription rows (modern only)
   2. Floating action button wiring (mobile only)
   3. Settings tabs (universal — improves UX for all themes)
*/

(function () {
    'use strict';

    const REVEAL_PX = 228; // 3 buttons × 76px
    const TRIGGER_PX = 60;

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

    function buildActionsPanel(container) {
        if (container.querySelector('.modern-swipe-actions')) return;

        const sub = container.querySelector('.subscription');
        if (!sub) return;
        const id = sub.dataset.id;
        if (!id) return;

        const panel = document.createElement('div');
        panel.className = 'modern-swipe-actions';
        panel.innerHTML = `
            <button class="modern-swipe-action edit" data-action="edit" data-id="${id}" aria-label="Edit">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                Edit
            </button>
            <button class="modern-swipe-action delete" data-action="delete" data-id="${id}" aria-label="Delete">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M6 19a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                Delete
            </button>
            <button class="modern-swipe-action clone" data-action="clone" data-id="${id}" aria-label="Clone">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M16 1H4a2 2 0 0 0-2 2v14h2V3h12V1zm3 4H8a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2zm0 16H8V7h11v14z"/></svg>
                Clone
            </button>
        `;
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

    function attachSwipe(container) {
        const sub = container.querySelector('.subscription');
        if (!sub || sub.dataset.modernSwipeBound) return;
        sub.dataset.modernSwipeBound = '1';

        let startX = 0, startY = 0, baseX = 0;
        let dragging = false;
        let axis = null;
        let suppressClick = false;

        const onDown = (e) => {
            if (!mobileFirstActive()) return;
            // Don't start swipe from the actions menu trigger
            if (e.target.closest('.actions, .actions-expand')) return;
            startX = e.clientX;
            startY = e.clientY;
            baseX = container.classList.contains('modern-swipe-revealed') ? -REVEAL_PX : 0;
            dragging = true;
            axis = null;
            suppressClick = false;
            container.classList.add('modern-swipe-dragging');
            try { sub.setPointerCapture(e.pointerId); } catch (_) { }
        };

        const onMove = (e) => {
            if (!dragging) return;
            const dx = e.clientX - startX;
            const dy = e.clientY - startY;
            if (!axis) {
                if (Math.abs(dx) < 6 && Math.abs(dy) < 6) return;
                axis = Math.abs(dx) > Math.abs(dy) ? 'x' : 'y';
                if (axis === 'y') {
                    dragging = false;
                    container.classList.remove('modern-swipe-dragging');
                    return;
                }
                closeAllExcept(container);
            }
            let next = baseX + dx;
            if (next > 0) next = 0;
            if (next < -REVEAL_PX) next = -REVEAL_PX - (Math.abs(next + REVEAL_PX) * 0.2);
            sub.style.transform = `translateX(${next}px)`;
            if (Math.abs(dx) > 8) suppressClick = true;
        };

        const onUp = (e) => {
            container.classList.remove('modern-swipe-dragging');
            if (!dragging) return;
            dragging = false;
            try { sub.releasePointerCapture(e.pointerId); } catch (_) { }
            const dx = e.clientX - startX;
            const finalX = baseX + dx;
            const shouldOpen = (baseX === 0 && dx < -TRIGGER_PX) || (baseX === -REVEAL_PX && finalX < -REVEAL_PX + TRIGGER_PX);
            sub.style.transform = '';
            container.classList.toggle('modern-swipe-revealed', shouldOpen);
            // If we suppressed a click, eat the next click on the subscription
            if (suppressClick) {
                const eat = (ev) => {
                    ev.stopPropagation();
                    ev.preventDefault();
                    sub.removeEventListener('click', eat, true);
                };
                sub.addEventListener('click', eat, true);
            }
        };

        sub.addEventListener('pointerdown', onDown);
        sub.addEventListener('pointermove', onMove);
        sub.addEventListener('pointerup', onUp);
        sub.addEventListener('pointercancel', onUp);
    }

    function wireActionClicks() {
        // Single delegated listener — wires once, applies to dynamically added rows too
        if (document.body.dataset.modernActionsBound) return;
        document.body.dataset.modernActionsBound = '1';

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.modern-swipe-action');
            if (!btn) return;
            e.stopPropagation();
            e.preventDefault();
            const action = btn.dataset.action;
            const id = parseInt(btn.dataset.id, 10);
            if (!id) return;

            // Close the row
            const container = btn.closest('.subscription-container');
            if (container) container.classList.remove('modern-swipe-revealed');

            // Delegate to existing global handlers
            if (action === 'edit' && typeof openEditSubscription === 'function') {
                openEditSubscription(e, id);
            } else if (action === 'delete' && typeof deleteSubscription === 'function') {
                deleteSubscription(e, id);
            } else if (action === 'clone' && typeof cloneSubscription === 'function') {
                cloneSubscription(e, id);
            }
        }, true);

        // Click outside any revealed row → close
        document.addEventListener('click', (e) => {
            if (e.target.closest('.subscription-container')) return;
            closeAllExcept(null);
        });
    }

    function initSwipe() {
        if (!mobileFirstActive()) return;
        document.querySelectorAll('.subscription-container').forEach(container => {
            buildActionsPanel(container);
            attachSwipe(container);
        });
        wireActionClicks();
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

        function activate(tabKey) {
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
            // Scroll settings into view (top of section)
            window.scrollTo({ top: settings.offsetTop - 12, behavior: 'instant' });
        }

        tabsNav.addEventListener('click', (e) => {
            const tab = e.target.closest('.settings-tab');
            if (!tab) return;
            activate(tab.dataset.tab);
        });

        // Initial tab — from URL hash, localStorage, or first
        const fromHash = (location.hash || '').replace('#', '');
        const fromStore = localStorage.getItem('wallosSettingsTab');
        const validTabs = Array.from(tabsNav.querySelectorAll('.settings-tab')).map(t => t.dataset.tab);
        const initial = validTabs.includes(fromHash) ? fromHash
                      : validTabs.includes(fromStore) ? fromStore
                      : validTabs[0];
        if (initial) activate(initial);
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
        actions.innerHTML = `
            <button class="modern-swipe-action dash-missing" data-dashboard-action="missing" data-id="${id}" aria-label="Payment Missing">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L1 21h22L12 2zm1 16h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
                Missing
            </button>
            <button class="modern-swipe-action dash-paid" data-dashboard-action="paid" data-id="${id}" aria-label="Already Paid">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                Paid
            </button>
            <button class="modern-swipe-action dash-edit" data-dashboard-action="edit" data-id="${id}" aria-label="Edit">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                Edit
            </button>
        `;
        const parent = card.parentNode;
        parent.insertBefore(wrapper, card);
        wrapper.appendChild(actions);
        wrapper.appendChild(card);
        return wrapper;
    }

    function attachDashboardSwipe(wrapper) {
        const card = wrapper.querySelector(':scope > .subscription-item');
        if (!card || card.dataset.modernDashSwipeBound) return;
        card.dataset.modernDashSwipeBound = '1';

        let startX = 0, startY = 0, baseX = 0;
        let dragging = false, axis = null, suppressClick = false;

        const onDown = (e) => {
            if (!mobileFirstActive()) return;
            startX = e.clientX;
            startY = e.clientY;
            baseX = wrapper.classList.contains('modern-swipe-revealed') ? -228 : 0;
            dragging = true;
            axis = null;
            suppressClick = false;
            wrapper.classList.add('modern-swipe-dragging');
            try { card.setPointerCapture(e.pointerId); } catch (_) { }
        };
        const onMove = (e) => {
            if (!dragging) return;
            const dx = e.clientX - startX;
            const dy = e.clientY - startY;
            if (!axis) {
                if (Math.abs(dx) < 6 && Math.abs(dy) < 6) return;
                axis = Math.abs(dx) > Math.abs(dy) ? 'x' : 'y';
                if (axis === 'y') { dragging = false; wrapper.classList.remove('modern-swipe-dragging'); return; }
                document.querySelectorAll('.dashboard-swipe-row.modern-swipe-revealed').forEach(r => {
                    if (r !== wrapper) {
                        r.classList.remove('modern-swipe-revealed');
                        const c = r.querySelector(':scope > .subscription-item');
                        if (c) c.style.transform = '';
                    }
                });
                closeAllExcept(null);
            }
            let next = baseX + dx;
            if (next > 0) next = 0;
            if (next < -228) next = -228 - (Math.abs(next + 228) * 0.2);
            card.style.transform = `translateX(${next}px)`;
            if (Math.abs(dx) > 8) suppressClick = true;
        };
        const onUp = (e) => {
            wrapper.classList.remove('modern-swipe-dragging');
            if (!dragging) return;
            dragging = false;
            try { card.releasePointerCapture(e.pointerId); } catch (_) { }
            const dx = e.clientX - startX;
            const finalX = baseX + dx;
            const shouldOpen = (baseX === 0 && dx < -60) || (baseX === -228 && finalX < -228 + 60);
            card.style.transform = '';
            wrapper.classList.toggle('modern-swipe-revealed', shouldOpen);
            if (suppressClick) {
                const eat = (ev) => {
                    ev.stopPropagation();
                    ev.preventDefault();
                    card.removeEventListener('click', eat, true);
                };
                card.addEventListener('click', eat, true);
            }
        };

        card.addEventListener('pointerdown', onDown);
        card.addEventListener('pointermove', onMove);
        card.addEventListener('pointerup', onUp);
        card.addEventListener('pointercancel', onUp);
    }

    function wireDashboardActionClicks() {
        if (document.body.dataset.modernDashActionsBound) return;
        document.body.dataset.modernDashActionsBound = '1';
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.modern-swipe-action[data-dashboard-action]');
            if (!btn) return;
            e.stopPropagation();
            e.preventDefault();
            const action = btn.dataset.dashboardAction;
            const id = parseInt(btn.dataset.id, 10);
            if (!id) return;

            const wrapper = btn.closest('.dashboard-swipe-row');
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
        if (!mobileFirstActive()) return;
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
        initSwipe();
        initDashboardSwipe();
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
        if (!mobileFirstActive()) return;
        document.querySelectorAll('.subscription-container').forEach(container => {
            if (!container.querySelector('.modern-swipe-actions')) {
                buildActionsPanel(container);
                attachSwipe(container);
            }
        });
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

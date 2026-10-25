// Houston site: copy buttons, MCP client tabs, menus and FAQ deep links. No dependencies.
(() => {
    // Copy buttons -------------------------------------------------------------
    // <button data-copy="bare command"> or <button data-copy-target="element-id">.
    // Each button holds a copy icon and a check icon; one polite live region per page says "Copied".
    const status = document.getElementById('copy-status');

    const copyText = async (text) => {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
            return;
        }
        const area = document.createElement('textarea');
        area.value = text;
        area.setAttribute('readonly', '');
        area.style.position = 'fixed';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.select();
        const ok = document.execCommand('copy');
        area.remove();
        if (!ok) throw new Error('copy failed');
    };

    document
        .querySelectorAll('[data-copy], [data-copy-target]')
        .forEach((btn) => {
            const copyIcon = btn.querySelector('[data-icon="copy"]');
            const checkIcon = btn.querySelector('[data-icon="check"]');
            let timer;

            btn.addEventListener('click', async () => {
                const target =
                    btn.dataset.copyTarget &&
                    document.getElementById(btn.dataset.copyTarget);
                const text = (
                    target ? target.textContent : btn.dataset.copy || ''
                ).trim();
                try {
                    await copyText(text);
                    if (status) status.textContent = 'Copied';
                    copyIcon?.classList.add('hidden');
                    checkIcon?.classList.remove('hidden');
                } catch {
                    if (status)
                        status.textContent =
                            'Copy failed. Select the command and copy it manually.';
                }
                clearTimeout(timer);
                timer = setTimeout(() => {
                    if (status) status.textContent = '';
                    copyIcon?.classList.remove('hidden');
                    checkIcon?.classList.add('hidden');
                }, 2000);
            });
        });

    // Tabs -----------------------------------------------------------------------
    // [role="tablist"][data-tabs="storage-key"] with role="tab" buttons (aria-controls) and role="tabpanel"s.
    // Arrow keys, Home and End move between tabs (automatic activation). The choice is remembered.
    document.querySelectorAll('[role="tablist"][data-tabs]').forEach((list) => {
        const key = `houston:${list.dataset.tabs}`;
        const tabs = Array.from(list.querySelectorAll('[role="tab"]'));

        const select = (tab, { focus = false, remember = true } = {}) => {
            tabs.forEach((t) => {
                const selected = t === tab;
                t.setAttribute('aria-selected', String(selected));
                t.tabIndex = selected ? 0 : -1;
                const panel = document.getElementById(
                    t.getAttribute('aria-controls'),
                );
                if (panel) panel.hidden = !selected;
            });
            if (focus) tab.focus();
            if (remember) {
                try {
                    localStorage.setItem(key, tab.id);
                } catch {
                    /* storage unavailable: ignore */
                }
            }
        };

        tabs.forEach((tab, i) => {
            tab.addEventListener('click', () => select(tab));
            tab.addEventListener('keydown', (event) => {
                let next = null;
                if (event.key === 'ArrowRight')
                    next = tabs[(i + 1) % tabs.length];
                else if (event.key === 'ArrowLeft')
                    next = tabs[(i - 1 + tabs.length) % tabs.length];
                else if (event.key === 'Home') next = tabs[0];
                else if (event.key === 'End') next = tabs[tabs.length - 1];
                if (next) {
                    event.preventDefault();
                    select(next, { focus: true });
                }
            });
        });

        let saved = null;
        try {
            saved = localStorage.getItem(key);
        } catch {
            /* storage unavailable: ignore */
        }
        const initial =
            tabs.find((t) => t.id === saved) ||
            tabs.find((t) => t.getAttribute('aria-selected') === 'true') ||
            tabs[0];
        if (initial) select(initial, { remember: false });
    });

    // Menus -----------------------------------------------------------------------
    // <details data-menu> opens on click without JavaScript; this also opens it on mouse hover, and
    // closes it on Escape, on a click outside it, and once one of its links is followed.
    const menus = Array.from(document.querySelectorAll('details[data-menu]'));
    menus.forEach((menu) => {
        let timer;
        menu.addEventListener('pointerenter', (event) => {
            if (event.pointerType !== 'mouse') return;
            clearTimeout(timer);
            menu.open = true;
        });
        // A short grace period lets the pointer cross the gap between the summary and the panel
        menu.addEventListener('pointerleave', (event) => {
            if (event.pointerType !== 'mouse') return;
            timer = setTimeout(() => (menu.open = false), 150);
        });
        // Hover already opened it, so a mouse click on the summary should not toggle it shut
        menu.querySelector('summary')?.addEventListener('click', (event) => {
            if (event.pointerType === 'mouse' && menu.open)
                event.preventDefault();
        });
    });
    document.addEventListener('click', (event) => {
        menus.forEach((menu) => {
            if (!menu.contains(event.target) || event.target.closest('a'))
                menu.open = false;
        });
    });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        menus
            .filter((menu) => menu.open)
            .forEach((menu) => {
                menu.open = false;
                menu.querySelector('summary')?.focus();
            });
    });

    // FAQ deep links -------------------------------------------------------------
    // /#faq-gitlab opens that <details> and scrolls to it.
    const openFromHash = () => {
        const id = decodeURIComponent(location.hash.slice(1));
        if (!id) return;
        const el = document.getElementById(id);
        if (el && el.tagName === 'DETAILS') {
            el.open = true;
            el.scrollIntoView({ block: 'start' });
            el.querySelector('summary')?.focus({ preventScroll: true });
        }
    };
    openFromHash();
    window.addEventListener('hashchange', openFromHash);
})();

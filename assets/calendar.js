/* Calico Elementor Calendar. Independent widget instances; saved configurations only. */
(() => {
    'use strict';
    const mounted = new WeakSet();
    const assets = new Map();
    const assetKey = node => node.id || node.src || node.href || `inline:${node.textContent}`;

    async function loadAssets(markup) {
        if (!markup) return;
        const doc = new DOMParser().parseFromString(markup, 'text/html');
        for (const node of doc.querySelectorAll('link[rel="stylesheet"], style, script')) {
            const key = assetKey(node);
            if (assets.has(key)) { await assets.get(key); continue; }
            const existing = node.id && document.getElementById(node.id);
            const sameURL = (node.src || node.href) && [...document.querySelectorAll('script[src],link[href]')].some(el => (el.src || el.href) === (node.src || node.href));
            if (existing || sameURL) { assets.set(key, Promise.resolve()); continue; }
            const copy = document.createElement(node.tagName.toLowerCase());
            for (const attr of node.attributes) copy.setAttribute(attr.name, attr.value);
            copy.textContent = node.textContent;
            const pending = new Promise((resolve, reject) => {
                if (copy.src || copy.tagName === 'LINK') {
                    copy.onload = resolve;
                    copy.onerror = () => { copy.remove(); reject(new Error('Calendar asset failed to load.')); };
                }
                document.head.append(copy);
                if (!copy.src && copy.tagName !== 'LINK') resolve();
            });
            assets.set(key, pending);
            try { await pending; } catch (error) { assets.delete(key); throw error; }
        }
    }

    function initializeListings(root) {
        if (window.elementorFrontend?.elementsHandler && window.jQuery) {
            root.querySelectorAll('.elementor-element[data-element_type]').forEach(el => window.elementorFrontend.elementsHandler.runReadyTrigger(window.jQuery(el)));
        }
        root.dispatchEvent(new CustomEvent('calico-calendar:rendered', {bubbles: true}));
    }

    function mount(root) {
        if (mounted.has(root)) return;
        mounted.add(root);
        let config;
        try { config = JSON.parse(root.dataset.ccc); } catch { return; }
        const inner = root.querySelector('.ccc-inner');
        const errorBox = root.querySelector('.ccc-error');
        if (!inner || !errorBox) return;
        let active = config.month;
        let controller;
        let serial = 0;

        function resetSelects() {
            const [year, month] = active.split('-');
            const y = inner.querySelector('[data-ccc-year]');
            const m = inner.querySelector('[data-ccc-month]');
            if (y) y.value = Number(year);
            if (m) m.value = Number(month);
        }

        async function navigate(month, focus) {
            if (!/^\d{4}-(0[1-9]|1[0-2])$/.test(month) || Number(month.slice(0, 4)) < config.min || Number(month.slice(0, 4)) > config.max) { resetSelects(); return; }
            if (month === active) return;
            controller?.abort();
            controller = new AbortController();
            const request = ++serial;
            root.setAttribute('aria-busy', 'true');
            errorBox.hidden = true;
            const controls = [...inner.querySelectorAll('.ccc-nav button, .ccc-nav select')];
            const disabled = controls.map(el => el.disabled);
            controls.forEach(el => { el.disabled = true; });
            try {
                const body = new URLSearchParams({action: 'calico_calendar_month', document: config.document, widget: config.widget, token: config.token, month});
                const response = await fetch(config.url, {method: 'POST', body, credentials: 'same-origin', signal: controller.signal});
                const json = await response.json();
                if (!response.ok || !json.success || typeof json.data?.html !== 'string' || json.data.month !== month) throw new Error(json.data?.message || config.error);
                await loadAssets(json.data.assets);
                if (request !== serial || !root.isConnected) return;
                inner.innerHTML = json.data.html;
                active = month;
                initializeListings(inner);
                if (focus) {
                    const preferred = inner.querySelector(focus);
                    const target = preferred && !preferred.disabled ? preferred : inner.querySelector('[data-ccc-month]');
                    target?.focus({preventScroll: true});
                }
            } catch (error) {
                if (request !== serial || error.name === 'AbortError') return;
                errorBox.textContent = error.message || config.error;
                errorBox.hidden = false;
                resetSelects();
                controls.forEach((el, i) => { el.disabled = disabled[i]; });
            } finally {
                if (request === serial) root.removeAttribute('aria-busy');
            }
        }

        root.addEventListener('click', event => {
            const button = event.target.closest('[data-ccc-step]');
            if (!button || !root.contains(button) || button.disabled) return;
            const [year, month] = active.split('-').map(Number);
            const next = new Date(Date.UTC(year, month - 1 + Number(button.dataset.cccStep), 1));
            const target = `${next.getUTCFullYear()}-${String(next.getUTCMonth() + 1).padStart(2, '0')}`;
            navigate(target, `[data-ccc-step="${button.dataset.cccStep}"]`);
        });
        root.addEventListener('change', event => {
            if (!event.target.matches('[data-ccc-month],[data-ccc-year]')) return;
            const month = inner.querySelector('[data-ccc-month]').value;
            const year = inner.querySelector('[data-ccc-year]').value;
            navigate(`${year}-${String(month).padStart(2, '0')}`, event.target.matches('[data-ccc-year]') ? '[data-ccc-year]' : '[data-ccc-month]');
        });
    }

    function scan(scope = document) {
        if (scope.matches?.('.ccc-calendar')) mount(scope);
        scope.querySelectorAll('.ccc-calendar').forEach(mount);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => scan()); else scan();
    const registerElementor = () => window.elementorFrontend?.hooks.addAction('frontend/element_ready/calico-multi-feed-calendar.default', scope => scan(scope[0] || scope));
    if (window.elementorFrontend?.hooks) registerElementor();
    else if (window.jQuery) window.jQuery(window).on('elementor/frontend/init', registerElementor);
})();

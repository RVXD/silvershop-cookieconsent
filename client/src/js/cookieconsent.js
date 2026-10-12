import '../styles/cookieconsent.scss';

/**
 * SilverShop Cookie Consent — client runtime (ES source).
 *
 * This is the authored source; `npm run build` (Vite) compiles it + the SCSS to
 * the dependency-free IIFE at client/dist/js/cookieconsent.js and the stylesheet
 * at client/dist/styles/cookieconsent.css, which the module ships and loads.
 *
 * It reads the server-written consent state (one JSON cookie), drives Google
 * Consent Mode v2, activates `type="text/plain" data-consent-category` scripts +
 * click-to-load embeds once their category is granted, powers the accessible
 * banner + preferences centre, and exposes window.SilverShopConsent +
 * a `consentchange` event.
 */

const DEFAULT_CONFIG = {
    cookieName: 'SilverShopConsent',
    categories: ['necessary', 'preferences', 'statistics', 'marketing'],
    required: ['necessary'],
    consentModes: {
        statistics: ['analytics_storage'],
        marketing: ['ad_storage', 'ad_user_data', 'ad_personalization'],
    },
    version: 1,
    needsPrompt: true,
    autoInject: true,
    acceptAllLink: '',
    acceptNecessaryLink: '',
    saveLink: '',
    banner: { title: '', content: '', labels: {}, categories: [] },
};

// Reconcile a theme's switch names with the standard taxonomy.
const NAME_ALIASES = {
    analytics: 'statistics',
    personalisation: 'preferences',
    personalization: 'preferences',
    functional: 'preferences',
};

function esc(str) {
    return String(str == null ? '' : str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

export default class CookieConsentRuntime {
    constructor(config) {
        this.config = Object.assign({}, DEFAULT_CONFIG, config || window.SilverShopConsentConfig || {});
        this.cookieName = this.config.cookieName;
        this.state = null;
        this.granted = [];

        window.dataLayer = window.dataLayer || [];
        if (typeof window.gtag !== 'function') {
            window.gtag = function gtag() { window.dataLayer.push(arguments); };
        }

        this.setConsentModeDefault();
        this.readConsent();
        this.pushToDataLayer();

        this.activateScripts();
        this.bindEmbeds();
        this.initBanner();
        this.bindReopen();
    }

    /* state */

    readCookie(name) {
        const cookies = document.cookie ? document.cookie.split('; ') : [];
        for (let i = 0; i < cookies.length; i++) {
            const parts = cookies[i].split('=');
            if (parts[0] === name) {
                return parts.slice(1).join('=');
            }
        }
        return undefined;
    }

    readConsent() {
        const raw = this.readCookie(this.cookieName);
        let data = null;
        if (raw) {
            try { data = JSON.parse(decodeURIComponent(raw)); } catch (e) { data = null; }
        }
        this.state = data;
        this.granted = (data && Array.isArray(data.c)) ? data.c.slice() : [];
    }

    hasDecision() { return !!this.state; }

    isRequired(category) { return this.config.required.indexOf(category) !== -1; }

    has(category) { return this.isRequired(category) || this.granted.indexOf(category) !== -1; }

    normalizeCategory(name) {
        name = (name || '').toLowerCase();
        return NAME_ALIASES[name] || name;
    }

    /* consent mode v2 */

    allSignals() {
        const set = {};
        const modes = this.config.consentModes || {};
        Object.keys(modes).forEach((cat) => {
            (modes[cat] || []).forEach((sig) => { set[sig] = true; });
        });
        return Object.keys(set);
    }

    setConsentModeDefault() {
        const signals = this.allSignals();
        if (!signals.length) { return; }
        const data = {};
        signals.forEach((sig) => { data[sig] = 'denied'; });
        window.gtag('consent', 'default', data);
    }

    pushToDataLayer() {
        if (typeof window.gtag !== 'function' || typeof window.dataLayer === 'undefined') { return; }
        const modes = this.config.consentModes || {};
        Object.keys(modes).forEach((category) => {
            if (!this.has(category)) { return; }
            const update = {};
            (modes[category] || []).forEach((signal) => { update[signal] = 'granted'; });
            if (Object.keys(update).length) { window.gtag('consent', 'update', update); }
            window.dataLayer.push({ event: `cookieconsent_${category}` });
        });
    }

    /* script blocking */

    activateScripts() {
        const blocked = document.querySelectorAll('script[type="text/plain"][data-consent-category]');
        blocked.forEach((node) => {
            if (!this.has(node.getAttribute('data-consent-category'))) { return; }
            const script = document.createElement('script');
            for (let i = 0; i < node.attributes.length; i++) {
                const attr = node.attributes[i];
                if (attr.name === 'type') { continue; }
                if (attr.name === 'data-src') { script.setAttribute('src', attr.value); }
                else { script.setAttribute(attr.name, attr.value); }
            }
            script.text = node.text || node.textContent || '';
            if (node.parentNode) { node.parentNode.replaceChild(script, node); }
        });
    }

    /* embeds */

    bindEmbeds() {
        const embeds = document.querySelectorAll('[data-consent-embed]');
        embeds.forEach((embed) => {
            if (embed.getAttribute('data-loaded') === 'true') { return; }
            const category = embed.getAttribute('data-consent-category');
            if (this.has(category)) { this.loadEmbed(embed); return; }
            const loadBtn = embed.querySelector('[data-consent-embed-load]');
            if (loadBtn) { loadBtn.addEventListener('click', () => this.loadEmbed(embed)); }
            const alwaysBtn = embed.querySelector('[data-consent-embed-always]');
            if (alwaysBtn) { alwaysBtn.addEventListener('click', () => this.grant(category)); }
        });
    }

    loadEmbed(embed) {
        if (embed.getAttribute('data-loaded') === 'true') { return; }
        const src = embed.getAttribute('data-embed-src');
        if (!src) { return; }
        const iframe = document.createElement('iframe');
        iframe.setAttribute('src', src);
        iframe.setAttribute('title', embed.getAttribute('data-embed-title') || '');
        iframe.setAttribute('loading', 'lazy');
        iframe.setAttribute('allowfullscreen', 'allowfullscreen');
        iframe.className = 'consent-embed__frame';
        embed.innerHTML = '';
        embed.appendChild(iframe);
        embed.setAttribute('data-loaded', 'true');
    }

    /* server sync */

    refresh() {
        this.readConsent();
        this.pushToDataLayer();
        this.activateScripts();
        this.bindEmbeds();
        this.dispatchChange();
    }

    dispatchChange() {
        document.dispatchEvent(new CustomEvent('consentchange', {
            detail: { categories: this.granted.slice() },
        }));
    }

    sendAction(url, onDone) {
        if (!url) { return; }
        const xhr = new XMLHttpRequest();
        xhr.open('GET', url);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.addEventListener('load', () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                this.refresh();
                if (typeof onDone === 'function') { onDone(); }
            }
        });
        xhr.send();
    }

    /* public actions */

    grantAll(onDone) {
        this.sendAction(this.config.acceptAllLink, onDone || (() => this.hideBanner()));
    }

    rejectAll(onDone) {
        this.sendAction(this.config.acceptNecessaryLink, onDone || (() => this.hideBanner()));
    }

    grant(category) {
        const cats = this.granted.slice();
        if (cats.indexOf(category) === -1) { cats.push(category); }
        this.save(cats);
    }

    save(categories, onDone) {
        const link = this.config.saveLink;
        if (!link) { return; }
        const url = `${link}${link.indexOf('?') === -1 ? '?' : '&'}categories=${encodeURIComponent((categories || []).join(','))}`;
        this.sendAction(url, onDone || (() => this.hideBanner()));
    }

    collectCategories() {
        const cats = [];
        if (!this.prefs) { return cats; }
        this.prefs.querySelectorAll('input[type="checkbox"]').forEach((input) => {
            if (input.checked || input.disabled) {
                const cat = input.getAttribute('data-consent-category')
                    || this.normalizeCategory(input.value || input.name);
                if (cat && cats.indexOf(cat) === -1) { cats.push(cat); }
            }
        });
        return cats;
    }

    /* banner */

    initBanner() {
        this.banner = document.getElementById('consent');
        if (!this.banner && this.config.autoInject && this.config.needsPrompt && !this.hasDecision()) {
            this.banner = this.injectBanner();
        }
        if (!this.banner) { return; }
        this.prefs = this.banner.querySelector('#consent-prefs') || this.banner.querySelector('.consent__prefs');
        this.bindBanner();
    }

    bindBanner() {
        this.banner.addEventListener('click', (e) => {
            const b = e.target.closest ? e.target.closest('[data-consent]') : null;
            if (!b || !this.banner.contains(b)) { return; }
            const act = b.getAttribute('data-consent');
            if (act === 'accept') { e.preventDefault(); this.grantAll(); }
            else if (act === 'reject') { e.preventDefault(); this.rejectAll(); }
            else if (act === 'manage') { e.preventDefault(); this.toggleManage(); }
            else if (act === 'save') { e.preventDefault(); this.save(this.collectCategories()); }
            else if (act === 'close') { e.preventDefault(); this.close(); }
            else if (act === 'open') { e.preventDefault(); this.open(); }
        });
        if (this.prefs && this.prefs.tagName === 'FORM') {
            this.prefs.addEventListener('submit', (e) => {
                e.preventDefault();
                this.save(this.collectCategories());
            });
        }
        this.banner.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape') { return; }
            if (this.prefs && !this.prefs.hidden) { this.toggleManage(false); }
            else if (this.hasDecision()) { this.hideBanner(); }
        });
    }

    toggleManage(force) {
        if (!this.prefs) { return; }
        const expand = (typeof force === 'boolean') ? force : this.prefs.hidden;
        this.prefs.hidden = !expand;
        const manageBtn = this.banner.querySelector('[data-consent="manage"]');
        if (manageBtn) { manageBtn.setAttribute('aria-expanded', expand ? 'true' : 'false'); }
        const saveBtn = this.banner.querySelector('[data-consent="save"]');
        if (saveBtn) { saveBtn.hidden = !expand; }
        if (expand) {
            const first = this.prefs.querySelector('input:not([disabled])');
            if (first) { first.focus(); }
        }
    }

    syncToggles() {
        if (!this.prefs) { return; }
        this.prefs.querySelectorAll('input[type="checkbox"]').forEach((input) => {
            if (input.disabled) { return; }
            const cat = input.getAttribute('data-consent-category')
                || this.normalizeCategory(input.value || input.name);
            input.checked = this.has(cat);
        });
    }

    showBanner() { if (this.banner) { this.banner.hidden = false; } }

    hideBanner() { if (this.banner) { this.banner.hidden = true; } }

    open() {
        if (!this.banner) { return; }
        this.lastFocus = document.activeElement;
        this.syncToggles();
        this.showBanner();
        this.toggleManage(true);
    }

    close() {
        this.toggleManage(false);
        if (this.hasDecision()) { this.hideBanner(); }
        if (this.lastFocus && typeof this.lastFocus.focus === 'function') { this.lastFocus.focus(); }
    }

    bindReopen() {
        const openers = document.querySelectorAll('[data-consent="open"], [data-consent-reopen]');
        openers.forEach((el) => {
            if (this.banner && this.banner.contains(el)) { return; }
            el.addEventListener('click', (e) => { e.preventDefault(); this.open(); });
        });
    }

    injectBanner() {
        const cfg = this.config.banner || {};
        const labels = cfg.labels || {};
        const wrap = document.createElement('div');
        wrap.className = 'consent';
        wrap.id = 'consent';
        wrap.setAttribute('role', 'region');
        wrap.setAttribute('aria-labelledby', 'consent-title');

        let prefs = '';
        (cfg.categories || []).forEach((cat) => {
            let attrs = `name="categories[]" value="${cat.code}" data-consent-category="${cat.code}"`;
            if (cat.required) { attrs += ' checked disabled'; }
            else if (cat.granted) { attrs += ' checked'; }
            prefs += `<div class="consent__pref"><div><strong>${esc(cat.title)}</strong>`
                + `<span>${esc(cat.desc || '')}</span></div>`
                + `<label class="switch"><input class="switch__input" type="checkbox" ${attrs} />`
                + `<span class="visually-hidden">${esc(cat.title)}</span></label></div>`;
        });

        wrap.innerHTML =
            '<div class="consent__inner">'
            + `<div class="consent__copy"><h2 class="consent__title" id="consent-title">${esc(cfg.title || '')}</h2>`
            + `<div class="consent__text">${cfg.content || ''}</div></div>`
            + `<form class="consent__prefs" id="consent-prefs" method="post" action="${this.config.saveLink || '#'}" hidden>${prefs}</form>`
            + '<div class="consent__actions">'
            + `<button class="consent__btn consent__btn--ghost" type="button" data-consent="manage" aria-expanded="false" aria-controls="consent-prefs">${esc(labels.manage || 'Manage preferences')}</button>`
            + `<a class="consent__btn consent__btn--secondary" href="${this.config.acceptNecessaryLink || '#'}" rel="nofollow" data-consent="reject">${esc(labels.reject || 'Reject non-essential')}</a>`
            + `<a class="consent__btn consent__btn--primary" href="${this.config.acceptAllLink || '#'}" rel="nofollow" data-consent="accept">${esc(labels.accept || 'Accept all')}</a>`
            + `<button class="consent__btn consent__btn--save" type="submit" form="consent-prefs" data-consent="save" hidden>${esc(labels.save || 'Save preferences')}</button>`
            + '</div></div>';

        document.body.appendChild(wrap);
        return wrap;
    }
}

function boot() {
    const runtime = new CookieConsentRuntime();
    window.SilverShopConsent = {
        has: (category) => runtime.has(category),
        granted: () => runtime.granted.slice(),
        needsPrompt: () => !!runtime.config.needsPrompt,
        grantAll: (cb) => runtime.grantAll(cb),
        rejectAll: (cb) => runtime.rejectAll(cb),
        save: (categories, cb) => runtime.save(categories, cb),
        open: () => runtime.open(),
        close: () => runtime.close(),
        instance: runtime,
    };
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}

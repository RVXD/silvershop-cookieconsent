/**
 * SilverShop Cookie Consent — client runtime (compiled bundle).
 *
 * Responsibilities:
 *   - read the server-written consent state (one JSON cookie);
 *   - drive Google Consent Mode v2 from a config-supplied category -> signal map;
 *   - enforce consent by activating `type="text/plain" data-consent-category`
 *     scripts and click-to-load embeds only once their category is granted;
 *   - power the accessible banner + in-banner preferences centre (binding to a
 *     theme-hosted #consent region, or injecting a default one);
 *   - expose window.SilverShopConsent and a `consentchange` event.
 *
 * Source: client/src/js/cookieconsent.js (this file is the built, dependency-free
 * IIFE; keep the two in sync or rebuild with `npm run build`).
 */
(function () {
    'use strict';

    var DEFAULT_CONFIG = {
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
    var NAME_ALIASES = {
        analytics: 'statistics',
        personalisation: 'preferences',
        personalization: 'preferences',
        functional: 'preferences',
    };

    function CookieConsentRuntime(config) {
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

    var proto = CookieConsentRuntime.prototype;

    /* --------------------------------------------------------------- state */

    proto.readCookie = function (name) {
        var cookies = document.cookie ? document.cookie.split('; ') : [];
        for (var i = 0; i < cookies.length; i++) {
            var parts = cookies[i].split('=');
            if (parts[0] === name) {
                return parts.slice(1).join('=');
            }
        }
        return undefined;
    };

    proto.readConsent = function () {
        var raw = this.readCookie(this.cookieName);
        var data = null;
        if (raw) {
            try { data = JSON.parse(decodeURIComponent(raw)); } catch (e) { data = null; }
        }
        this.state = data;
        this.granted = (data && Array.isArray(data.c)) ? data.c.slice() : [];
    };

    proto.hasDecision = function () {
        return !!this.state;
    };

    proto.isRequired = function (category) {
        return this.config.required.indexOf(category) !== -1;
    };

    proto.has = function (category) {
        return this.isRequired(category) || this.granted.indexOf(category) !== -1;
    };

    proto.normalizeCategory = function (name) {
        name = (name || '').toLowerCase();
        return NAME_ALIASES[name] || name;
    };

    /* ----------------------------------------------------- consent mode v2 */

    proto.allSignals = function () {
        var set = {};
        var modes = this.config.consentModes || {};
        Object.keys(modes).forEach(function (cat) {
            (modes[cat] || []).forEach(function (sig) { set[sig] = true; });
        });
        return Object.keys(set);
    };

    proto.setConsentModeDefault = function () {
        var signals = this.allSignals();
        if (!signals.length) { return; }
        var data = {};
        signals.forEach(function (sig) { data[sig] = 'denied'; });
        window.gtag('consent', 'default', data);
    };

    proto.pushToDataLayer = function () {
        if (typeof window.gtag !== 'function' || typeof window.dataLayer === 'undefined') { return; }
        var self = this;
        var modes = this.config.consentModes || {};
        Object.keys(modes).forEach(function (category) {
            if (!self.has(category)) { return; }
            var update = {};
            (modes[category] || []).forEach(function (signal) { update[signal] = 'granted'; });
            if (Object.keys(update).length) {
                window.gtag('consent', 'update', update);
            }
            window.dataLayer.push({ event: 'cookieconsent_' + category });
        });
    };

    /* ----------------------------------------------------- script blocking */

    proto.activateScripts = function () {
        var self = this;
        var blocked = document.querySelectorAll('script[type="text/plain"][data-consent-category]');
        Array.prototype.forEach.call(blocked, function (node) {
            if (!self.has(node.getAttribute('data-consent-category'))) { return; }
            var script = document.createElement('script');
            for (var i = 0; i < node.attributes.length; i++) {
                var attr = node.attributes[i];
                if (attr.name === 'type') { continue; }
                if (attr.name === 'data-src') {
                    script.setAttribute('src', attr.value);
                } else {
                    script.setAttribute(attr.name, attr.value);
                }
            }
            script.text = node.text || node.textContent || '';
            if (node.parentNode) { node.parentNode.replaceChild(script, node); }
        });
    };

    /* --------------------------------------------------------- embeds */

    proto.bindEmbeds = function () {
        var self = this;
        var embeds = document.querySelectorAll('[data-consent-embed]');
        Array.prototype.forEach.call(embeds, function (embed) {
            if (embed.getAttribute('data-loaded') === 'true') { return; }
            var category = embed.getAttribute('data-consent-category');
            if (self.has(category)) {
                self.loadEmbed(embed);
                return;
            }
            var loadBtn = embed.querySelector('[data-consent-embed-load]');
            if (loadBtn) {
                loadBtn.addEventListener('click', function () { self.loadEmbed(embed); });
            }
            var alwaysBtn = embed.querySelector('[data-consent-embed-always]');
            if (alwaysBtn) {
                alwaysBtn.addEventListener('click', function () { self.grant(category); });
            }
        });
    };

    proto.loadEmbed = function (embed) {
        if (embed.getAttribute('data-loaded') === 'true') { return; }
        var src = embed.getAttribute('data-embed-src');
        if (!src) { return; }
        var iframe = document.createElement('iframe');
        iframe.setAttribute('src', src);
        iframe.setAttribute('title', embed.getAttribute('data-embed-title') || '');
        iframe.setAttribute('loading', 'lazy');
        iframe.setAttribute('allowfullscreen', 'allowfullscreen');
        iframe.className = 'consent-embed__frame';
        embed.innerHTML = '';
        embed.appendChild(iframe);
        embed.setAttribute('data-loaded', 'true');
    };

    /* ----------------------------------------------------- server sync */

    proto.refresh = function () {
        this.readConsent();
        this.pushToDataLayer();
        this.activateScripts();
        this.bindEmbeds();
        this.dispatchChange();
    };

    proto.dispatchChange = function () {
        document.dispatchEvent(new CustomEvent('consentchange', {
            detail: { categories: this.granted.slice() },
        }));
    };

    proto.sendAction = function (url, onDone) {
        if (!url) { return; }
        var self = this;
        var xhr = new XMLHttpRequest();
        xhr.open('GET', url);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.addEventListener('load', function () {
            if (xhr.status >= 200 && xhr.status < 300) {
                self.refresh();
                if (typeof onDone === 'function') { onDone(); }
            }
        });
        xhr.send();
    };

    /* ----------------------------------------------------- public actions */

    proto.grantAll = function (onDone) {
        var self = this;
        this.sendAction(this.config.acceptAllLink, onDone || function () { self.hideBanner(); });
    };

    proto.rejectAll = function (onDone) {
        var self = this;
        this.sendAction(this.config.acceptNecessaryLink, onDone || function () { self.hideBanner(); });
    };

    proto.grant = function (category) {
        var cats = this.granted.slice();
        if (cats.indexOf(category) === -1) { cats.push(category); }
        this.save(cats);
    };

    proto.save = function (categories, onDone) {
        var self = this;
        var link = this.config.saveLink;
        if (!link) { return; }
        var url = link + (link.indexOf('?') === -1 ? '?' : '&')
            + 'categories=' + encodeURIComponent((categories || []).join(','));
        this.sendAction(url, onDone || function () { self.hideBanner(); });
    };

    proto.collectCategories = function () {
        var self = this;
        var cats = [];
        if (!this.prefs) { return cats; }
        var inputs = this.prefs.querySelectorAll('input[type="checkbox"]');
        Array.prototype.forEach.call(inputs, function (input) {
            if (input.checked || input.disabled) {
                // Prefer the explicit category, then the field name, then the value.
                // A checkbox with no value attribute reports value "on", so reading
                // value first would mask a name-keyed checkbox (name="analytics").
                var cat = input.getAttribute('data-consent-category')
                    || self.normalizeCategory(input.name || input.value);
                if (cat && cats.indexOf(cat) === -1) { cats.push(cat); }
            }
        });
        return cats;
    };

    /* --------------------------------------------------------- banner */

    proto.initBanner = function () {
        this.banner = document.getElementById('consent');
        if (!this.banner && this.config.autoInject && this.config.needsPrompt && !this.hasDecision()) {
            this.banner = this.injectBanner();
        }
        if (!this.banner) { return; }
        this.prefs = this.banner.querySelector('#consent-prefs')
            || this.banner.querySelector('.consent__prefs');
        this.bindBanner();
        // Show a theme-hosted banner when a prompt is due and no decision exists,
        // so the module is self-sufficient (does not rely on the theme's own JS).
        if (this.config.needsPrompt && !this.hasDecision() && this.banner.hidden) {
            this.showBanner();
        }
    };

    proto.bindBanner = function () {
        var self = this;
        this.banner.addEventListener('click', function (e) {
            var b = e.target.closest ? e.target.closest('[data-consent]') : null;
            if (!b || !self.banner.contains(b)) { return; }
            var act = b.getAttribute('data-consent');
            if (act === 'accept') { e.preventDefault(); self.grantAll(); }
            else if (act === 'reject') { e.preventDefault(); self.rejectAll(); }
            else if (act === 'manage') { e.preventDefault(); self.toggleManage(); }
            else if (act === 'save') { e.preventDefault(); self.save(self.collectCategories()); }
            else if (act === 'close') { e.preventDefault(); self.close(); }
            else if (act === 'open') { e.preventDefault(); self.open(); }
        });
        if (this.prefs && this.prefs.tagName === 'FORM') {
            this.prefs.addEventListener('submit', function (e) {
                e.preventDefault();
                self.save(self.collectCategories());
            });
        }
        this.banner.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') { return; }
            if (self.prefs && !self.prefs.hidden) {
                self.toggleManage(false);
            } else if (self.hasDecision()) {
                self.hideBanner();
            }
        });
    };

    proto.toggleManage = function (force) {
        if (!this.prefs) { return; }
        var expand = (typeof force === 'boolean') ? force : this.prefs.hidden;
        this.prefs.hidden = !expand;
        var manageBtn = this.banner.querySelector('[data-consent="manage"]');
        if (manageBtn) { manageBtn.setAttribute('aria-expanded', expand ? 'true' : 'false'); }
        var saveBtn = this.banner.querySelector('[data-consent="save"]');
        if (saveBtn) { saveBtn.hidden = !expand; }
        if (expand) {
            var first = this.prefs.querySelector('input:not([disabled])');
            if (first) { first.focus(); }
        }
    };

    proto.syncToggles = function () {
        if (!this.prefs) { return; }
        var self = this;
        var inputs = this.prefs.querySelectorAll('input[type="checkbox"]');
        Array.prototype.forEach.call(inputs, function (input) {
            if (input.disabled) { return; }
            var cat = input.getAttribute('data-consent-category')
                || self.normalizeCategory(input.name || input.value);
            input.checked = self.has(cat);
        });
    };

    proto.showBanner = function () { if (this.banner) { this.banner.hidden = false; } };

    proto.hideBanner = function () { if (this.banner) { this.banner.hidden = true; } };

    proto.open = function () {
        if (!this.banner) { return; }
        this.lastFocus = document.activeElement;
        this.syncToggles();
        this.showBanner();
        this.toggleManage(true);
    };

    proto.close = function () {
        this.toggleManage(false);
        if (this.hasDecision()) { this.hideBanner(); }
        if (this.lastFocus && typeof this.lastFocus.focus === 'function') {
            this.lastFocus.focus();
        }
    };

    proto.bindReopen = function () {
        var self = this;
        var openers = document.querySelectorAll('[data-consent="open"], [data-consent-reopen]');
        Array.prototype.forEach.call(openers, function (el) {
            if (self.banner && self.banner.contains(el)) { return; }
            el.addEventListener('click', function (e) { e.preventDefault(); self.open(); });
        });
    };

    /* --------------------------------------------- zero-config injection */

    proto.injectBanner = function () {
        var cfg = this.config.banner || {};
        var labels = cfg.labels || {};
        var wrap = document.createElement('div');
        wrap.className = 'consent';
        wrap.id = 'consent';
        wrap.setAttribute('role', 'region');
        wrap.setAttribute('aria-labelledby', 'consent-title');

        var prefs = '';
        (cfg.categories || []).forEach(function (cat) {
            var attrs = 'name="categories[]" value="' + cat.code + '" data-consent-category="' + cat.code + '"';
            if (cat.required) { attrs += ' checked disabled'; }
            else if (cat.granted) { attrs += ' checked'; }
            prefs += '<div class="consent__pref"><div><strong>' + esc(cat.title) + '</strong>'
                + '<span>' + esc(cat.desc || '') + '</span></div>'
                + '<label class="switch"><input class="switch__input" type="checkbox" ' + attrs + ' />'
                + '<span class="visually-hidden">' + esc(cat.title) + '</span></label></div>';
        });

        wrap.innerHTML =
            '<div class="consent__inner">'
            + '<div class="consent__copy"><h2 class="consent__title" id="consent-title">' + esc(cfg.title || '') + '</h2>'
            + '<div class="consent__text">' + (cfg.content || '') + '</div></div>'
            + '<form class="consent__prefs" id="consent-prefs" method="post" action="' + (this.config.saveLink || '#') + '" hidden>' + prefs + '</form>'
            + '<div class="consent__actions">'
            + '<button class="consent__btn consent__btn--ghost" type="button" data-consent="manage" aria-expanded="false" aria-controls="consent-prefs">' + esc(labels.manage || 'Manage preferences') + '</button>'
            + '<a class="consent__btn consent__btn--secondary" href="' + (this.config.acceptNecessaryLink || '#') + '" rel="nofollow" data-consent="reject">' + esc(labels.reject || 'Reject non-essential') + '</a>'
            + '<a class="consent__btn consent__btn--primary" href="' + (this.config.acceptAllLink || '#') + '" rel="nofollow" data-consent="accept">' + esc(labels.accept || 'Accept all') + '</a>'
            + '<button class="consent__btn consent__btn--save" type="submit" form="consent-prefs" data-consent="save" hidden>' + esc(labels.save || 'Save preferences') + '</button>'
            + '</div></div>';

        document.body.appendChild(wrap);
        return wrap;
    };

    function esc(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /* ------------------------------------------------------------- boot */

    function boot() {
        var runtime = new CookieConsentRuntime();
        window.SilverShopConsent = {
            has: function (category) { return runtime.has(category); },
            granted: function () { return runtime.granted.slice(); },
            needsPrompt: function () { return !!runtime.config.needsPrompt; },
            grantAll: function (cb) { runtime.grantAll(cb); },
            rejectAll: function (cb) { runtime.rejectAll(cb); },
            save: function (categories, cb) { runtime.save(categories, cb); },
            open: function () { runtime.open(); },
            close: function () { runtime.close(); },
            instance: runtime,
        };
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();

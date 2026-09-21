/**
 * utm.js — Captura, persiste e propaga UTMs + fbclid por todo o funil
 * Usar em TODAS as páginas (index, opcoes, pagamento, obrigado)
 */
(function () {
    'use strict';

    var UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'src', 'sck', 'fbclid'];
    var STORAGE_KEY = 'utm_params';

    // ── 1. Capturar UTMs da URL atual e mesclar com as salvas ──────────────────
    function captureUTMs() {
        var params = new URLSearchParams(window.location.search);
        var saved = getSavedUTMs();
        var updated = false;

        UTM_KEYS.forEach(function (key) {
            var val = params.get(key);
            if (val) {
                saved[key] = val;
                updated = true;
            }
        });

        if (updated) {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(saved));
        }

        return saved;
    }

    // ── 2. Recuperar UTMs salvas ───────────────────────────────────────────────
    function getSavedUTMs() {
        try {
            return JSON.parse(sessionStorage.getItem(STORAGE_KEY)) || {};
        } catch (e) {
            return {};
        }
    }

    // ── 3. Montar query string com UTMs ───────────────────────────────────────
    function buildUTMQuery(extraParams) {
        var utms = getSavedUTMs();
        var merged = Object.assign({}, utms, extraParams || {});
        var parts = [];
        Object.keys(merged).forEach(function (k) {
            if (merged[k]) {
                parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(merged[k]));
            }
        });
        return parts.length ? '?' + parts.join('&') : '';
    }

    // ── 4. Injetar UTMs em todos os links internos ────────────────────────────
    function injectUTMsInLinks() {
        var utmQuery = buildUTMQuery();
        if (!utmQuery) return;

        var links = document.querySelectorAll('a[href]');
        links.forEach(function (a) {
            var href = a.getAttribute('href');
            // Apenas links internos (sem protocolo ou mesmo domínio)
            if (href && !href.startsWith('http') && !href.startsWith('//') && !href.startsWith('#') && !href.startsWith('mailto')) {
                var baseHref = href.split('?')[0];
                var existingParams = new URLSearchParams(href.includes('?') ? href.split('?')[1] : '');
                var utms = getSavedUTMs();
                Object.keys(utms).forEach(function (k) {
                    if (!existingParams.has(k) && utms[k]) {
                        existingParams.set(k, utms[k]);
                    }
                });
                var newQuery = existingParams.toString();
                a.setAttribute('href', baseHref + (newQuery ? '?' + newQuery : ''));
            }
        });
    }

    // ── 5. Criar cookie _fbc a partir de fbclid (se não existir) ──────────────
    function ensureFbcCookie() {
        var utms = getSavedUTMs();
        var fbclid = utms.fbclid || new URLSearchParams(window.location.search).get('fbclid');
        
        if (fbclid && !getCookie('_fbc')) {
            var ts = Math.floor(Date.now() / 1000);
            var fbc = 'fb.1.' + ts + '.' + fbclid;
            var expires = new Date(Date.now() + 90 * 24 * 60 * 60 * 1000).toUTCString();
            document.cookie = '_fbc=' + fbc + '; path=/; expires=' + expires + '; SameSite=Lax';
        }
    }

    // ── 6. Utilitário para ler cookie ─────────────────────────────────────────
    function getCookie(name) {
        var match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        return match ? decodeURIComponent(match[2]) : null;
    }

    // ── 7. Obter objeto completo de tracking para envio ───────────────────────
    function getTrackingParameters() {
        var utms = getSavedUTMs();
        return {
            src: utms.src || utms.utm_source || null,
            sck: utms.sck || null,
            utm_source: utms.utm_source || null,
            utm_campaign: utms.utm_campaign || null,
            utm_medium: utms.utm_medium || null,
            utm_content: utms.utm_content || null,
            utm_term: utms.utm_term || null,
            fbclid: utms.fbclid || null,
            fbp: getCookie('_fbp') || null,
            fbc: getCookie('_fbc') || null
        };
    }

    // ── 8. Navegar com UTMs preservadas ───────────────────────────────────────
    function navigateWithUTMs(basePath, extraParams) {
        var utms = getSavedUTMs();
        var merged = Object.assign({}, utms, extraParams || {});
        var parts = [];
        Object.keys(merged).forEach(function (k) {
            if (merged[k]) {
                parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(merged[k]));
            }
        });
        var query = parts.length ? '?' + parts.join('&') : '';
        window.location.href = basePath + query;
    }

    // ── Init ──────────────────────────────────────────────────────────────────
    captureUTMs();
    ensureFbcCookie();

    // Injetar nos links após DOM carregado
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', injectUTMsInLinks);
    } else {
        injectUTMsInLinks();
    }

    // Observer para links adicionados dinamicamente
    if (window.MutationObserver) {
        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                if (m.addedNodes.length) {
                    injectUTMsInLinks();
                }
            });
        });
        observer.observe(document.body || document.documentElement, { childList: true, subtree: true });
    }

    // Expor API pública
    window.UTMManager = {
        get: getSavedUTMs,
        getTracking: getTrackingParameters,
        navigate: navigateWithUTMs,
        buildQuery: buildUTMQuery,
        getCookie: getCookie
    };

})();

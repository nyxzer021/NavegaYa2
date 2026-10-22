@php
    $tracking = config('services.tracking');
    $canTrack = $tracking['enabled']
        && (! auth()->check() || $tracking['authenticated'])
        && collect([
            $tracking['google_analytics_id'],
            $tracking['google_ads_id'],
            $tracking['tiktok_pixel_id'],
            $tracking['clarity_project_id'],
        ])->filter()->isNotEmpty();
@endphp

@if($canTrack)
    <script>
        (() => {
            const configuration = @json([
                'ga' => $tracking['google_analytics_id'],
                'googleAds' => $tracking['google_ads_id'],
                'tiktok' => $tracking['tiktok_pixel_id'],
                'clarity' => $tracking['clarity_project_id'],
            ]);
            const preferenceKey = 'navegaya_tracking_consent_v1';
            const loadScript = (source, id) => {
                if (id && document.getElementById(id)) return;
                const script = document.createElement('script');
                script.async = true;
                script.src = source;
                if (id) script.id = id;
                document.head.appendChild(script);
            };
            const readPreference = () => {
                try { return JSON.parse(localStorage.getItem(preferenceKey)); } catch (_) { return null; }
            };
            const savePreference = (preference) => {
                localStorage.setItem(preferenceKey, JSON.stringify(preference));
                document.cookie = `navegaya_tracking_consent=${preference.analytics ? 'analytics' : 'essential'}${preference.marketing ? '_marketing' : ''}; Max-Age=31536000; Path=/; SameSite=Lax; Secure`;
            };
            const startGoogle = (preference) => {
                if (!configuration.ga && !configuration.googleAds) return;
                window.dataLayer = window.dataLayer || [];
                window.gtag = window.gtag || function () { dataLayer.push(arguments); };
                gtag('consent', 'default', {
                    analytics_storage: preference.analytics ? 'granted' : 'denied',
                    ad_storage: preference.marketing ? 'granted' : 'denied',
                    ad_user_data: preference.marketing ? 'granted' : 'denied',
                    ad_personalization: preference.marketing ? 'granted' : 'denied',
                });
                const primaryId = configuration.ga || configuration.googleAds;
                loadScript(`https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(primaryId)}`, 'navegaya-google-tag');
                gtag('js', new Date());
                if (configuration.ga) gtag('config', configuration.ga, { anonymize_ip: true });
                if (configuration.googleAds && preference.marketing) gtag('config', configuration.googleAds);
            };
            const startClarity = () => {
                if (!configuration.clarity || window.clarity) return;
                window.clarity = function () { (window.clarity.q = window.clarity.q || []).push(arguments); };
                loadScript(`https://www.clarity.ms/tag/${encodeURIComponent(configuration.clarity)}`, 'navegaya-clarity');
            };
            const startTikTok = () => {
                if (!configuration.tiktok || window.ttq) return;
                !function (w, d, t) {
                    w.TiktokAnalyticsObject = t;
                    const ttq = w[t] = w[t] || [];
                    ttq.methods = ['page', 'track', 'identify', 'instances', 'debug', 'on', 'off', 'once', 'ready', 'alias', 'group', 'enableCookie', 'disableCookie'];
                    ttq.setAndDefer = function (object, method) { object[method] = function () { object.push([method].concat([].slice.call(arguments, 0))); }; };
                    for (let i = 0; i < ttq.methods.length; i++) ttq.setAndDefer(ttq, ttq.methods[i]);
                    ttq.load = function (pixelId) {
                        const script = d.createElement('script');
                        script.async = true;
                        script.src = 'https://analytics.tiktok.com/i18n/pixel/events.js?sdkid=' + encodeURIComponent(pixelId) + '&lib=' + t;
                        d.head.appendChild(script);
                    };
                    ttq.load(configuration.tiktok);
                    ttq.page();
                }(window, document, 'ttq');
            };
            const applyPreference = (preference) => {
                if (preference.analytics) {
                    startGoogle(preference);
                    startClarity();
                }
                if (preference.marketing) {
                    startGoogle(preference);
                    startTikTok();
                }
                window.dispatchEvent(new CustomEvent('navegaya:tracking-ready', { detail: preference }));
            };
            window.navegaYaTrack = (eventName, parameters = {}) => {
                const preference = readPreference();
                if (preference?.analytics && window.gtag) gtag('event', eventName, parameters);
                if (preference?.marketing && window.ttq) ttq.track(eventName, parameters);
            };
            window.navegaYaSaveTrackingConsent = (preference) => {
                const previousPreference = readPreference();
                savePreference(preference);
                const consentPanel = document.getElementById('tracking-consent');
                if (consentPanel) consentPanel.style.display = 'none';
                if (previousPreference && (
                    (previousPreference.analytics && !preference.analytics)
                    || (previousPreference.marketing && !preference.marketing)
                )) {
                    window.location.reload();
                    return;
                }
                applyPreference(preference);
            };
            window.navegaYaOpenTrackingConsent = () => {
                const consentPanel = document.getElementById('tracking-consent');
                if (consentPanel) consentPanel.style.display = '';
            };
            const preference = readPreference();
            if (preference) applyPreference(preference);
        })();
    </script>

    <div id="tracking-consent" class="fixed inset-x-3 bottom-3 z-[200] mx-auto max-w-3xl rounded-2xl border border-emerald-900/10 bg-white p-4 shadow-2xl sm:flex sm:items-center sm:gap-5" style="display:none">
        <div class="min-w-0 flex-1">
            <strong class="text-sm font-black text-slate-900">Tu privacidad en NavegaYA</strong>
            <p class="mt-1 text-xs leading-5 text-slate-600">Usamos medición para mejorar la plataforma y, con tu permiso, evaluar nuestras campañas. Puedes continuar solo con cookies necesarias.</p>
        </div>
        <div class="mt-3 flex flex-wrap gap-2 sm:mt-0 sm:shrink-0">
            <button type="button" onclick="navegaYaSaveTrackingConsent({analytics:false,marketing:false})" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700">Solo necesarias</button>
            <button type="button" onclick="navegaYaSaveTrackingConsent({analytics:true,marketing:false})" class="rounded-xl border border-emerald-600 px-3 py-2 text-xs font-bold text-emerald-800">Aceptar analítica</button>
            <button type="button" onclick="navegaYaSaveTrackingConsent({analytics:true,marketing:true})" class="rounded-xl bg-emerald-800 px-3 py-2 text-xs font-bold text-white">Aceptar todo</button>
        </div>
    </div>
    <script>
        if (!localStorage.getItem('navegaya_tracking_consent_v1')) {
            document.getElementById('tracking-consent').style.display = '';
        }
    </script>
@endif

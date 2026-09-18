(function () {
    if (typeof window === 'undefined' || typeof window.fmsLogging === 'undefined') {
        return;
    }

    const config = window.fmsLogging;
    const ajaxUrl = config.ajaxUrl;
    const nonce = config.nonce;
    const language = config.language || 'en';
    const allowedEvents = Array.isArray(config.allowedEvents) ? config.allowedEvents : [];

    if (!ajaxUrl || !nonce) {
        return;
    }

    function isAllowed(eventType) {
        return allowedEvents.indexOf(eventType) !== -1;
    }

    function dispatch(payload) {
        const params = new URLSearchParams(payload);
        const body = params.toString();

        if (navigator.sendBeacon) {
            const blob = new Blob([body], { type: 'application/x-www-form-urlencoded; charset=UTF-8' });
            const sent = navigator.sendBeacon(ajaxUrl, blob);
            if (sent) {
                return;
            }
        }

        fetch(ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            credentials: 'same-origin',
            body,
        }).catch(function () {
            // Silent fail – logging should never block UX
        });
    }

    function buildPayload(data) {
        const payload = {
            action: 'fms_log_click',
            nonce,
            event_type: data.eventType,
            language_code: data.language || language,
            page_url: data.pageUrl || window.location.href,
            referrer_url: data.referrer || document.referrer || '',
        };

        if (data.doctorId) {
            payload.doctor_id = data.doctorId;
        }
        if (data.doctorName) {
            payload.doctor_name_snapshot = data.doctorName;
        }
        if (data.countryId) {
            payload.country_id = data.countryId;
        }
        if (data.countryLabel) {
            payload.country_label = data.countryLabel;
        }
        if (data.cityId) {
            payload.city_id = data.cityId;
        }
        if (data.cityLabel) {
            payload.city_label = data.cityLabel;
        }
        if (data.metadata && Object.keys(data.metadata).length) {
            payload.metadata = JSON.stringify(data.metadata);
        }

        return payload;
    }

    function logEvent(eventType, details) {
        if (!eventType || !isAllowed(eventType)) {
            return;
        }

        const payload = buildPayload(Object.assign({ eventType }, details || {}));
        dispatch(payload);
    }

    function getDropdownLabel(selector) {
        const el = document.querySelector(selector);
        return el ? el.textContent.trim() : '';
    }

    function getHiddenValue(selector) {
        const input = document.querySelector(selector);
        return input ? input.value : '';
    }

    function getLocationData() {
        return {
            countryId: getHiddenValue('#fms_country'),
            countryLabel: getDropdownLabel('#fms_country_dropdown .fms-dropdown-selected'),
            cityId: getHiddenValue('#fms_city'),
            cityLabel: getDropdownLabel('#fms_city_dropdown .fms-dropdown-selected'),
        };
    }

    function buildLocationMetadata(location) {
        const meta = {};
        if (location.countryId) {
            meta.country_id = location.countryId;
        }
        if (location.countryLabel) {
            meta.country_label = location.countryLabel;
        }
        if (location.cityId) {
            meta.city_id = location.cityId;
        }
        if (location.cityLabel) {
            meta.city_label = location.cityLabel;
        }
        return meta;
    }

    function handleSearchClick() {
        const location = getLocationData();
        const metadata = buildLocationMetadata(location);
        logEvent('search_button', {
            metadata,
            countryId: location.countryId,
            countryLabel: location.countryLabel,
            cityId: location.cityId,
            cityLabel: location.cityLabel,
        });
    }

    function handleIconClick(event) {
        const target = event.target.closest('[data-fms-event-type]');
        if (!target) {
            return;
        }

        const eventType = target.getAttribute('data-fms-event-type');
        if (!eventType) {
            return;
        }

        const location = getLocationData();
        const metadata = Object.assign(buildLocationMetadata(location), {
            target_url: target.getAttribute('href') || target.getAttribute('data-target-url') || '',
            icon_type: target.getAttribute('data-type') || '',
        });

        const details = {
            doctorId: target.getAttribute('data-doctor-id') || '',
            doctorName: target.getAttribute('data-doctor-name') || '',
            metadata,
            countryId: location.countryId,
            countryLabel: location.countryLabel,
            cityId: location.cityId,
            cityLabel: location.cityLabel,
        };

        logEvent(eventType, details);
    }

    document.addEventListener('DOMContentLoaded', function () {
        const searchButton = document.getElementById('fms_search');
        if (searchButton) {
            searchButton.addEventListener('click', handleSearchClick);
        }
        document.addEventListener('click', handleIconClick, true);
    });
})();

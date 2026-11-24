(() => {
    'use strict';

    const currency = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
    const category = document.querySelector('#membership_category_id');
    const level = document.querySelector('#membership_level_id');
    const quote = document.querySelector('#membership-quote');
    const updateQuote = () => {
        if (!quote || !category || !level) return;
        if (!category.value || !level.value) {
            quote.textContent = 'Choose a category and level';
            return;
        }
        const fee = Number(level.selectedOptions[0].dataset.fee);
        const discount = Number(category.selectedOptions[0].dataset.discount);
        const total = fee - Math.floor((fee * discount + 50) / 100);
        quote.textContent = `${currency.format(total / 100)} / ${level.selectedOptions[0].dataset.days} days`;
    };
    category?.addEventListener('change', updateQuote);
    level?.addEventListener('change', updateQuote);
    updateQuote();

    const method = document.querySelector('#method');
    const reference = document.querySelector('#external_reference');
    const updatePayment = () => {
        if (reference && method) reference.required = method.value !== 'cash';
    };
    method?.addEventListener('change', updatePayment);
    updatePayment();

    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', event => {
            if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
                event.preventDefault();
                return;
            }
            if (event.defaultPrevented || (form.getAttribute('method') || 'get').toLowerCase() !== 'post') return;
            if (form.dataset.submitting === '1') {
                event.preventDefault();
                return;
            }
            form.dataset.submitting = '1';
            form.querySelectorAll('button[type="submit"]').forEach(button => {
                button.disabled = true;
                button.dataset.originalText = button.textContent;
                button.textContent = 'Saving…';
            });
        });
    });
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('form[data-submitting]').forEach(form => {
            delete form.dataset.submitting;
            form.querySelectorAll('button[data-original-text]').forEach(button => {
                button.disabled = false;
                button.textContent = button.dataset.originalText;
            });
        });
    });
    document.querySelectorAll('[data-print]').forEach(button => button.addEventListener('click', () => window.print()));

    const widgets = [...document.querySelectorAll('[data-location-widget]')].map(element => {
        const initial = { lat: 14.6189095, lng: 120.9660386 };
        const lat = element.querySelector('[name="latitude"]');
        const lon = element.querySelector('[name="longitude"]');
        const widget = { element, lat, lon, map: null, marker: null, position: initial, request: null };
        widget.selected = Boolean(lat?.value && lon?.value);
        const startLat = Number(element.dataset.lat);
        const startLon = Number(element.dataset.lon);
        if (Number.isFinite(startLat) && Math.abs(startLat) <= 90 && Number.isFinite(startLon) && Math.abs(startLon) <= 180) {
            widget.position = { lat: startLat, lng: startLon };
        }
        return widget;
    });

    async function weather(widget) {
        widget.request?.abort();
        const controller = new AbortController();
        widget.request = controller;
        const timer = setTimeout(() => controller.abort(), 10000);
        const panel = widget.element.querySelector('[data-weather-panel]');
        panel.textContent = 'Loading current weather…';
        try {
            const url = new URL(widget.element.dataset.weatherUrl, location.origin);
            url.searchParams.set('lat', widget.position.lat.toFixed(3));
            url.searchParams.set('lon', widget.position.lng.toFixed(3));
            const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: controller.signal });
            if (!response.ok) throw new Error('Weather unavailable');
            const data = await response.json();
            if (typeof data.temperature !== 'number') throw new Error('Weather unavailable');
            panel.textContent = `${data.place} · ${Math.round(data.temperature)}°C · ${data.description}${data.humidity != null ? ` · ${data.humidity}% humidity` : ''} · OpenWeather`;
        } catch {
            if (widget.request === controller) panel.textContent = 'Current weather is temporarily unavailable. Please check again later.';
        } finally {
            clearTimeout(timer);
        }
    }

    function updatePosition(widget) {
        const hasBoth = widget.lat?.value !== '' && widget.lon?.value !== '';
        if (!hasBoth || !widget.lat?.validity.valid || !widget.lon?.validity.valid) return;
        widget.position = { lat: Number(widget.lat.value), lng: Number(widget.lon.value) };
        widget.selected = true;
        if (widget.map) {
            widget.map.panTo(widget.position);
            widget.marker.position = widget.position;
        }
        widget.element.querySelector('[data-coordinate-label]').textContent = `Selected area: ${widget.position.lat.toFixed(5)}, ${widget.position.lng.toFixed(5)}`;
        weather(widget);
    }

    widgets.forEach(widget => {
        weather(widget);
        let timer;
        [widget.lat, widget.lon].forEach(input => input?.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => updatePosition(widget), 500);
        }));
        widget.element.querySelector('[data-clear-location]')?.addEventListener('click', () => {
            clearTimeout(timer);
            widget.lat.value = '';
            widget.lon.value = '';
            widget.selected = false;
            if (widget.marker) widget.marker.position = null;
            widget.position = { lat: 14.6189095, lng: 120.9660386 };
            widget.map?.panTo(widget.position);
            widget.element.querySelector('[data-coordinate-label]').textContent = 'No location selected. You can submit without coordinates.';
            weather(widget);
        });
    });

    window.initLooksMaxxingMap = async () => {
        try {
            const { Map } = await google.maps.importLibrary('maps');
            const { AdvancedMarkerElement } = await google.maps.importLibrary('marker');
            widgets.forEach(widget => {
                const editable = widget.element.dataset.editable === '1';
                const canvas = widget.element.querySelector('[data-map-canvas]');
                canvas.replaceChildren();
                widget.map = new Map(canvas, { center: widget.position, zoom: 14, mapId: 'DEMO_MAP_ID', streetViewControl: false, mapTypeControl: false, gestureHandling: 'cooperative' });
                widget.marker = new AdvancedMarkerElement({ map: widget.map, position: editable && !widget.selected ? null : widget.position, title: editable ? 'Selected area' : 'Tondo area center' });
                if (editable) widget.map.addListener('click', event => {
                    if (!event.latLng) return;
                    widget.lat.value = event.latLng.lat().toFixed(7);
                    widget.lon.value = event.latLng.lng().toFixed(7);
                    updatePosition(widget);
                });
            });
        } catch {
            widgets.forEach(widget => {
                widget.element.querySelector('[data-map-canvas]').textContent = 'The map is unavailable. Use the Google Maps link or enter coordinates below.';
            });
        }
    };
})();

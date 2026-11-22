<script setup>
import { computed, onBeforeUnmount, onMounted, ref, shallowRef } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

/**
 * The directory map.
 *
 * Why this is a Vue island rather than server-rendered markup: a map is a
 * living thing — panning, zooming, a location fix arriving, a pin being
 * dropped. None of that survives a page render, so the component owns the
 * Leaflet instance and Blade owns everything around it.
 *
 * What it deliberately does *not* do is sort. When the customer's position
 * arrives it navigates to `?near=lat,lng` and lets the server re-order the
 * cards. That is the split the whole project is built on: Vue renders, Laravel
 * decides. Sorting in the browser would produce a list the server never saw —
 * and the pins would stop matching the cards the moment anyone shared the URL.
 */
const props = defineProps({
    pins: { type: Array, default: () => [] },
    origin: { type: Object, default: null },
    center: { type: Object, required: true },
    zoom: { type: Number, default: 11 },
});

const mapEl = ref(null);
const map = shallowRef(null);

// idle | locating | denied | failed | unsupported | picking
const status = ref('idle');
const offline = ref(false);

const hasPins = computed(() => props.pins.length > 0);
const nearest = computed(() => props.pins.find((pin) => pin.nearest) ?? null);

const caption = computed(() => {
    if (props.origin?.src === 'pin') return 'Near the pin you dropped';
    if (props.origin) return 'Nearest to your location';
    return 'Metro Manila';
});

const message = computed(() => ({
    denied: 'Location blocked. Allow it in your browser, or drop a pin on the map.',
    failed: 'Could not get a fix. Drop a pin on the map instead.',
    unsupported: 'This browser has no location support. Drop a pin on the map.',
    picking: 'Tap the map to set where you are.',
}[status.value] ?? ''));

/* ------------------------------------------------------------------ icons */

const pinIcon = (pin) => L.divIcon({
    className: 'tl-pin-wrap',
    html: `<span class="tl-pin${pin.nearest ? ' tl-pin--near' : ''}">${pin.n}</span>`,
    iconSize: [30, 30],
    iconAnchor: [15, 15],
    popupAnchor: [0, -18],
});

const meIcon = () => L.divIcon({
    className: 'tl-me-wrap',
    html: '<span class="tl-me"><span class="tl-me-ring"></span></span>',
    iconSize: [22, 22],
    iconAnchor: [11, 11],
});

/* ---------------------------------------------------------------- popups */

const esc = (value) => String(value).replace(
    /[&<>"']/g,
    (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]),
);

const popupHtml = (pin) => `
    <div class="tl-pop">
        <div class="tl-pop-head">
            <b>${esc(pin.name)}</b>
            ${pin.nearest ? '<span class="tl-pop-flag">Nearest</span>' : ''}
        </div>
        <div class="tl-pop-meta">
            ${pin.distance ? `<span>${esc(pin.distance)}</span>` : `<span>${esc(pin.city)}</span>`}
            <span>·</span>
            <span>${esc(pin.price)}/${esc(pin.unit)}</span>
            <span>·</span>
            <span>${esc(pin.rating)}★</span>
        </div>
        <div class="tl-pop-meta"><span>${esc(pin.turnaround)}</span></div>
        <a class="tl-pop-link" href="${esc(pin.url)}">View shop →</a>
    </div>
`;

/* ------------------------------------------------------------- navigation */

/**
 * Hand the position to the server and let it re-sort.
 *
 * Every existing filter is preserved: dropping a pin must narrow the list, not
 * throw away the city and price range the customer already chose.
 */
function goTo(lat, lng, src) {
    const url = new URL(window.location.href);
    url.searchParams.set('near', `${lat.toFixed(5)},${lng.toFixed(5)}`);
    url.searchParams.set('src', src);
    // Cleared so the server's "nearest when a location exists" default applies.
    url.searchParams.delete('sort');
    window.location.assign(url.toString());
}

function locate() {
    if (!('geolocation' in navigator)) {
        status.value = 'unsupported';
        return;
    }

    status.value = 'locating';

    navigator.geolocation.getCurrentPosition(
        (position) => goTo(position.coords.latitude, position.coords.longitude, 'gps'),
        (error) => {
            status.value = error.code === error.PERMISSION_DENIED ? 'denied' : 'failed';
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 },
    );
}

function togglePick() {
    status.value = status.value === 'picking' ? 'idle' : 'picking';
}

/* ------------------------------------------------------------------ setup */

let observer = null;

onMounted(() => {
    if (!mapEl.value) return;

    const instance = L.map(mapEl.value, {
        center: [props.center.lat, props.center.lng],
        zoom: props.zoom,
        zoomControl: false,
        attributionControl: true,
        scrollWheelZoom: false, // the map sits inside a scrolling page
    });

    L.control.zoom({ position: 'bottomright' }).addTo(instance);

    const tiles = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(instance);

    // Honest degradation: if the tile server is unreachable the pins are still
    // in the right place, so say that instead of showing a broken grey box.
    let tileErrors = 0;
    tiles.on('tileerror', () => {
        if (++tileErrors > 4) offline.value = true;
    });
    tiles.on('tileload', () => {
        tileErrors = 0;
        offline.value = false;
    });

    const bounds = [];

    if (props.origin) {
        L.marker([props.origin.lat, props.origin.lng], {
            icon: meIcon(),
            interactive: false,
            zIndexOffset: 500,
        }).addTo(instance);

        bounds.push([props.origin.lat, props.origin.lng]);
    }

    props.pins.forEach((pin) => {
        L.marker([pin.lat, pin.lng], { icon: pinIcon(pin), title: `${pin.n}. ${pin.name}` })
            .addTo(instance)
            .bindPopup(popupHtml(pin), { closeButton: false, offset: [0, -2] });

        bounds.push([pin.lat, pin.lng]);
    });

    if (bounds.length > 1) {
        instance.fitBounds(bounds, { padding: [46, 46], maxZoom: 14 });
    } else if (bounds.length === 1) {
        instance.setView(bounds[0], 13);
    }

    instance.on('click', (event) => {
        if (status.value !== 'picking') return;
        goTo(event.latlng.lat, event.latlng.lng, 'pin');
    });

    // Leaflet measures its container once, on creation. The panel can change
    // width without the window resizing (the filter rail collapsing, a font
    // loading), so watch the element itself rather than the viewport.
    observer = new ResizeObserver(() => instance.invalidateSize());
    observer.observe(mapEl.value);

    map.value = instance;
});

onBeforeUnmount(() => {
    observer?.disconnect();
    observer = null;
    map.value?.remove();
    map.value = null;
});
</script>

<template>
    <div class="dir-map" :class="{ 'is-picking': status === 'picking' }">
        <div ref="mapEl" class="dir-map-canvas"></div>

        <div class="dir-map-tools">
            <button type="button" class="map-btn map-btn-primary" @click="locate" :disabled="status === 'locating'">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path d="M12 21s7-6.3 7-11a7 7 0 1 0-14 0c0 4.7 7 11 7 11Z" />
                    <circle cx="12" cy="10" r="2.6" />
                </svg>
                {{ status === 'locating' ? 'Locating…' : 'Use my location' }}
            </button>
            <button type="button" class="map-btn" :class="{ on: status === 'picking' }" @click="togglePick">
                {{ status === 'picking' ? 'Cancel' : 'Pick on map' }}
            </button>
        </div>

        <div v-if="message" class="dir-map-note">{{ message }}</div>

        <div v-if="offline" class="dir-map-note dir-map-note--offline">
            Map tiles are offline — pins are still in their real positions.
        </div>

        <div class="dir-map-cap">
            <b>{{ caption }}</b>
            <span v-if="nearest && origin">· {{ nearest.name }} at {{ nearest.distance }}</span>
            <span v-else-if="hasPins">· {{ pins.length }} {{ pins.length === 1 ? 'shop' : 'shops' }} on the map</span>
        </div>
    </div>
</template>

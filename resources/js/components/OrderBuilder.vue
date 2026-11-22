<script setup>
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    shop: { type: Object, required: true },
    services: { type: Array, required: true },
    quoteUrl: { type: String, required: true },
    // Set by "Reorder" and "Edit" on a past job; null when opened cold.
    initialServiceId: { type: Number, default: null },
    initialWidth: { type: Number, default: null },
    initialHeight: { type: Number, default: null },
    initialQty: { type: Number, default: null },
});

const serviceId = ref(props.initialServiceId ?? props.services[0]?.id ?? null);
const active = computed(() => props.services.find((s) => s.id === serviceId.value) ?? null);

// Area-priced services (tarpaulin) use standard counter feet presets;
// piece-priced services (stickers, flyers, DTF shirts, business cards) derive
// their size presets directly from the shop's own price tiers so selecting a
// service never leaves the builder in an unprintable 422 state.
const tarpaulinPresets = [
    { label: '2×3 ft', w: 24, h: 36 },
    { label: '3×4 ft', w: 36, h: 48 },
    { label: '4×6 ft', w: 48, h: 72 },
    { label: '6×8 ft', w: 72, h: 96 },
];

const presetsFor = (service) => {
    if (!service) return tarpaulinPresets;
    if (service.unit === 'sqft') return tarpaulinPresets;

    const tiers = service.tiers ?? [];
    if (!tiers.length) return tarpaulinPresets;

    return tiers.map((tier) => {
        // Parse explicit dimensions like "2×2 in" or "3.5×2 in" when present,
        // or derive sensible width × height inside the tier's [min_width, max_width] bound.
        const match = String(tier.label).match(/(\d+(?:\.\d+)?)\s*[×x]\s*(\d+(?:\.\d+)?)/i);
        if (match) {
            return { label: tier.label, w: Number(match[1]), h: Number(match[2]) };
        }
        const upper = tier.max_width !== null ? Number(tier.max_width) : Number(tier.min_width) + 4;
        const lower = Number(tier.min_width) > 0 ? Number(tier.min_width) : upper;
        return {
            label: tier.label,
            w: lower,
            h: upper,
        };
    });
};

const presets = computed(() => presetsFor(active.value));

const defaultDimensionsFor = (service) => {
    const list = presetsFor(service);
    if (service?.unit === 'sqft') {
        return { w: 36, h: 48 };
    }
    return list[0] ? { w: list[0].w, h: list[0].h } : { w: 12, h: 12 };
};

const initialDefaults = defaultDimensionsFor(active.value);
const width = ref(props.initialWidth ?? initialDefaults.w);
const height = ref(props.initialHeight ?? initialDefaults.h);
const qty = ref(props.initialQty ?? 1);
const chosen = ref({});
const quote = ref(null);
const error = ref('');
const loading = ref(false);

const area = computed(() => ((Number(width.value) || 0) * (Number(height.value) || 0)) / 144);

const tierRangeHint = computed(() => {
    const tiers = active.value?.tiers ?? [];
    if (!tiers.length) return '';
    const maxes = tiers.map((t) => t.max_width);
    if (maxes.some((m) => m === null)) return 'any width';
    const maxIn = Math.max(...maxes.map(Number));
    return `up to ${maxIn} in`;
});

const peso = (cents) => {
    const amount = (Number(cents) || 0) / 100;
    const hasFraction = Math.round(Number(cents) || 0) % 100 !== 0;
    return (
        '₱' +
        amount.toLocaleString('en-PH', {
            minimumFractionDigits: hasFraction ? 2 : 0,
            maximumFractionDigits: 2,
        })
    );
};

function updateHeading() {
    const heading = document.getElementById('builder-title');
    if (heading && active.value?.name) {
        heading.textContent = `Build your ${active.value.name.toLowerCase()}`;
    }
}

watch(serviceId, () => {
    chosen.value = {};
    const def = defaultDimensionsFor(active.value);
    width.value = def.w;
    height.value = def.h;
    updateHeading();
});

let timer = null;
let requestSeq = 0;
let activeController = null;

function refresh() {
    clearTimeout(timer);
    timer = setTimeout(fetchQuote, 180);
}

async function fetchQuote() {
    if (!active.value) return;

    if (activeController) {
        activeController.abort();
    }
    const controller = new AbortController();
    activeController = controller;
    const seq = ++requestSeq;

    loading.value = true;
    error.value = '';

    try {
        const response = await fetch(props.quoteUrl, {
            method: 'POST',
            signal: controller.signal,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({
                shop: props.shop.slug,
                service_id: serviceId.value,
                width_in: Number(width.value) || 0,
                height_in: Number(height.value) || 0,
                qty: Number(qty.value) || 1,
                options: Object.entries(chosen.value)
                    .filter(([, q]) => Number(q) > 0)
                    .map(([id, q]) => ({ id: Number(id), qty: Number(q) })),
            }),
        });

        const data = await response.json();

        if (seq !== requestSeq) return;

        if (!response.ok) {
            error.value = data.message ?? 'This shop cannot print that size.';
            quote.value = null;
            return;
        }

        quote.value = data;
    } catch (e) {
        if (e?.name === 'AbortError' || seq !== requestSeq) return;
        error.value = 'Could not reach the price engine.';
    } finally {
        if (seq === requestSeq) {
            loading.value = false;
        }
    }
}

watch([serviceId, width, height, qty, chosen], refresh, { deep: true });
onMounted(() => {
    updateHeading();
    fetchQuote();
});

const checkoutHref = computed(() => {
    const opts = Object.entries(chosen.value)
        .filter(([, q]) => Number(q) > 0)
        .map(([id, q]) => `${id}:${q}`)
        .join(',');

    const params = new URLSearchParams({
        service_id: String(serviceId.value),
        width_in: String(width.value),
        height_in: String(height.value),
        qty: String(qty.value),
    });

    if (opts) params.set('options', opts);

    return `${props.shop.checkout_url}?${params.toString()}`;
});

const quickQty = [1, 3, 5, 10, 25];

const isPreset = (p) => Number(width.value) === p.w && Number(height.value) === p.h;
</script>

<template>
    <div class="build-grid">
        <div class="panel">
            <div class="step-block">
                <div class="sb-head">
                    <span class="sb-num done">✓</span>
                    <b>Service</b>
                    <span class="sb-meta">Step 1 of 4</span>
                </div>
                <div class="opt-grid" role="radiogroup" aria-label="Select print service">
                    <button
                        v-for="service in services"
                        :key="service.id"
                        type="button"
                        role="radio"
                        :aria-checked="service.id === serviceId"
                        class="opt"
                        :class="{ on: service.id === serviceId }"
                        @click="serviceId = service.id"
                    >
                        {{ service.name }}
                        <small v-if="service.from">{{ peso(service.from) }} / {{ service.unit === 'sqft' ? 'sq ft' : 'pc' }}</small>
                        <small v-else>{{ service.unit === 'sqft' ? 'per square foot' : 'per piece' }}</small>
                    </button>
                </div>
            </div>

            <div class="step-block">
                <div class="sb-head">
                    <span class="sb-num">2</span>
                    <b>Size</b>
                    <span class="sb-meta">inches<template v-if="tierRangeHint"> · {{ tierRangeHint }}</template></span>
                </div>
                <div class="size-inputs">
                    <label class="field" style="flex:1">
                        <span style="font-size:11px;color:var(--muted)">W</span>
                        <input v-model.number="width" type="number" min="0.5" step="any" aria-label="Width in inches">
                    </label>
                    <span class="x">×</span>
                    <label class="field" style="flex:1">
                        <span style="font-size:11px;color:var(--muted)">H</span>
                        <input v-model.number="height" type="number" min="0.5" step="any" aria-label="Height in inches">
                    </label>
                    <span class="x">=</span>
                    <div class="mono" style="padding:0 14px;height:42px;display:flex;align-items:center;background:var(--paper);border-radius:9px;font-size:12.5px;font-weight:600;white-space:nowrap">
                        {{ area.toFixed(1) }} sq ft
                    </div>
                </div>

                <div class="size-presets" role="group" aria-label="Size presets">
                    <button
                        v-for="preset in presets"
                        :key="preset.label"
                        type="button"
                        class="selpill"
                        :class="{ on: isPreset(preset) }"
                        :aria-pressed="isPreset(preset)"
                        @click="width = preset.w; height = preset.h"
                    >{{ preset.label }}</button>
                </div>
            </div>

            <div v-if="active && active.options.length" class="step-block">
                <div class="sb-head">
                    <span class="sb-num">3</span>
                    <b>Finishing</b>
                    <span class="sb-meta">optional</span>
                </div>
                <div v-for="option in active.options" :key="option.id" class="opt-row">
                    <label class="opt-check">
                        <input
                            type="checkbox"
                            :checked="Number(chosen[option.id] ?? 0) > 0"
                            @change="chosen[option.id] = $event.target.checked ? 1 : 0"
                        >
                        <b>{{ option.name }}</b>
                        <span class="small">{{ peso(option.price) }} / {{ option.unit }}</span>
                    </label>
                    <input
                        v-if="Number(chosen[option.id] ?? 0) > 0"
                        v-model.number="chosen[option.id]"
                        type="number"
                        min="1"
                        class="finp qty-inp"
                        :aria-label="`${option.name} quantity`"
                    >
                </div>
            </div>

            <div class="step-block">
                <div class="sb-head">
                    <span class="sb-num">4</span>
                    <b>Quantity</b>
                    <span class="sb-meta">bulk tiers apply</span>
                </div>
                <div class="qty-row" role="group" aria-label="Quantity presets">
                    <button
                        v-for="n in quickQty"
                        :key="n"
                        type="button"
                        class="selpill"
                        :class="{ on: qty === n }"
                        :aria-pressed="qty === n"
                        @click="qty = n"
                    >{{ n }}</button>
                    <label class="field" style="width:110px">
                        <input v-model.number="qty" type="number" min="1" style="text-align:center" aria-label="Custom quantity">
                    </label>
                </div>
            </div>
        </div>

        <aside class="summary">
            <div class="sum-box" aria-live="polite">
                <div class="sum-preview">
                    <div class="sheet">
                        <b>{{ active?.name }}</b>
                        <span class="dim w">{{ width }} in</span>
                        <span class="dim h">{{ height }} in</span>
                    </div>
                </div>

                <div v-if="error" class="sum-error" role="alert">{{ error }}</div>

                <div v-else-if="quote" class="sum-lines">
                    <div v-for="(line, i) in quote.lines" :key="i" class="sum-line">
                        <span>{{ line.label }}<template v-if="line.qty > 1"> × {{ line.qty }}</template></span>
                        <b class="mono">{{ peso(line.amount) }}</b>
                    </div>
                    <div v-if="quote.discount > 0" class="sum-line" style="color:var(--green)">
                        <span>Bulk tier discount</span>
                        <b class="mono" style="color:var(--green)">−{{ peso(quote.discount) }}</b>
                    </div>
                </div>

                <div v-else class="sum-lines">
                    <div class="sum-line"><span>Calculating…</span><b></b></div>
                </div>

                <div class="sum-total">
                    <div>
                        <div class="l">Total</div>
                        <div class="tiny" style="margin-top:3px">{{ quote?.tier ?? '' }}</div>
                    </div>
                    <div class="v mono">{{ quote ? peso(quote.total) : '—' }}</div>
                </div>

                <div style="padding:14px 18px 18px">
                    <a
                        class="btn btn-primary"
                        style="width:100%;justify-content:center"
                        :class="{ disabled: !quote || loading }"
                        :aria-disabled="!quote || loading ? 'true' : 'false'"
                        :tabindex="!quote || loading ? -1 : 0"
                        :href="quote ? checkoutHref : undefined"
                    >Continue to checkout</a>
                    <p class="tiny" style="margin-top:10px;text-align:center">
                        Price recalculated on the server at checkout. Pay at the counter or online.
                    </p>
                </div>
            </div>
        </aside>
    </div>
</template>

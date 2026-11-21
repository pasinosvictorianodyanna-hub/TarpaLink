<script setup>
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    shop: { type: Object, required: true },
    services: { type: Array, required: true },
    quoteUrl: { type: String, required: true },
});

const serviceId = ref(props.services[0]?.id ?? null);
const width = ref(36);
const height = ref(48);
const qty = ref(1);
const chosen = ref({});
const quote = ref(null);
const error = ref('');
const loading = ref(false);

const active = computed(() => props.services.find((s) => s.id === serviceId.value) ?? null);
const area = computed(() => ((Number(width.value) || 0) * (Number(height.value) || 0)) / 144);

const peso = (cents) =>
    '₱' + (cents / 100).toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 2 });

watch(serviceId, () => {
    chosen.value = {};
});

let timer = null;
function refresh() {
    clearTimeout(timer);
    timer = setTimeout(fetchQuote, 220);
}

async function fetchQuote() {
    if (!active.value) return;
    loading.value = true;
    error.value = '';

    try {
        const response = await fetch(props.quoteUrl, {
            method: 'POST',
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

        if (!response.ok) {
            error.value = data.message ?? 'This shop cannot print that size.';
            quote.value = null;
            return;
        }

        quote.value = data;
    } catch (e) {
        error.value = 'Could not reach the price engine.';
    } finally {
        loading.value = false;
    }
}

watch([serviceId, width, height, qty, chosen], refresh, { deep: true });
onMounted(fetchQuote);

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

// The four sizes the counter actually gets asked for, in feet as the customer
// thinks of them. Inches underneath, because that is what the matrix is in.
const presets = [
    { label: '2×3 ft', w: 24, h: 36 },
    { label: '3×4 ft', w: 36, h: 48 },
    { label: '4×6 ft', w: 48, h: 72 },
    { label: '6×8 ft', w: 72, h: 96 },
];

const isPreset = (p) => Number(width.value) === p.w && Number(height.value) === p.h;
</script>

<template>
    <div class="build-grid">
        <div class="panel">
            <div class="step-block">
                <div class="sb-head"><span class="sb-num done">✓</span><b>Service</b><span class="opt">Step 1 of 4</span></div>
                <div class="opt-grid">
                    <div
                        v-for="service in services"
                        :key="service.id"
                        class="opt"
                        :class="{ on: service.id === serviceId }"
                        @click="serviceId = service.id"
                    >
                        {{ service.name }}
                        <small v-if="service.from">{{ peso(service.from) }} / {{ service.unit === 'sqft' ? 'sq ft' : 'pc' }}</small>
                        <small v-else>{{ service.unit === 'sqft' ? 'per square foot' : 'per piece' }}</small>
                    </div>
                </div>
            </div>

            <div class="step-block">
                <div class="sb-head"><span class="sb-num">2</span><b>Size</b><span class="opt">inches</span></div>
                <div class="size-inputs">
                    <label class="field" style="flex:1">
                        <span style="font-size:11px;color:var(--muted)">W</span>
                        <input v-model.number="width" type="number" min="1" step="1">
                    </label>
                    <span class="x">×</span>
                    <label class="field" style="flex:1">
                        <span style="font-size:11px;color:var(--muted)">H</span>
                        <input v-model.number="height" type="number" min="1" step="1">
                    </label>
                    <span class="x">=</span>
                    <div style="padding:0 14px;height:42px;display:flex;align-items:center;background:var(--paper);border-radius:9px;font-size:12.5px;font-weight:600">
                        {{ area.toFixed(1) }} sq ft
                    </div>
                </div>

                <div class="size-presets">
                    <span
                        v-for="preset in presets"
                        :key="preset.label"
                        class="selpill"
                        :class="{ on: isPreset(preset) }"
                        @click="width = preset.w; height = preset.h"
                    >{{ preset.label }}</span>
                </div>
            </div>

            <div v-if="active && active.options.length" class="step-block">
                <div class="sb-head"><span class="sb-num">3</span><b>Finishing</b><span class="opt">optional</span></div>
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
                    >
                </div>
            </div>

            <div class="step-block">
                <div class="sb-head"><span class="sb-num">4</span><b>Quantity</b><span class="opt">bulk tiers apply</span></div>
                <div class="qty-row">
                    <span
                        v-for="n in quickQty"
                        :key="n"
                        class="selpill"
                        :class="{ on: qty === n }"
                        @click="qty = n"
                    >{{ n }}</span>
                    <label class="field" style="width:110px">
                        <input v-model.number="qty" type="number" min="1" style="text-align:center">
                    </label>
                </div>
            </div>
        </div>

        <aside class="summary">
            <div class="sum-box">
                <div class="sum-preview">
                    <div class="sheet">
                        <b>{{ active?.name }}</b>
                        <span class="dim w">{{ width }} in</span>
                        <span class="dim h">{{ height }} in</span>
                    </div>
                </div>

                <div v-if="error" class="sum-error">{{ error }}</div>

                <div v-else-if="quote" class="sum-lines">
                    <div v-for="(line, i) in quote.lines" :key="i" class="sum-line">
                        <span>{{ line.label }}<template v-if="line.qty > 1"> × {{ line.qty }}</template></span>
                        <b>{{ peso(line.amount) }}</b>
                    </div>
                    <div v-if="quote.discount > 0" class="sum-line" style="color:var(--green)">
                        <span>Bulk tier discount</span>
                        <b style="color:var(--green)">−{{ peso(quote.discount) }}</b>
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
                    <div class="v">{{ quote ? peso(quote.total) : '—' }}</div>
                </div>

                <div style="padding:14px 18px 18px">
                    <a
                        class="btn btn-primary"
                        style="width:100%;justify-content:center"
                        :class="{ disabled: !quote }"
                        :href="quote ? checkoutHref : '#'"
                    >Continue to checkout</a>
                    <p class="tiny" style="margin-top:10px;text-align:center">
                        Price recalculated on the server at checkout. Pay at the counter or online.
                    </p>
                </div>
            </div>
        </aside>
    </div>
</template>

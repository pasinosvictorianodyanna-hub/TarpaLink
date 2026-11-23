<script setup>
import { computed, ref } from 'vue';

/**
 * Checkout, as a Vue island.
 *
 * Why Vue and not a Blade form: a rejected field has to come back under the
 * input that caused it, and the button has to say what it is doing while a
 * payment intent is being created. A full page reload throws away the other two
 * panels and — on a slow connection — looks like the button did nothing, which
 * is exactly the bug this replaces.
 *
 * What Vue does *not* do here is price anything. The totals, the discount, the
 * tier and the line items all arrive as props from `PriceCalculator`; the
 * component renders them and posts the spec back. The one number it sends up is
 * `expected_total` — what the customer was shown — so the server can refuse to
 * charge a different amount if the shop changed its matrix while the page was
 * open.
 */
const props = defineProps({
    authenticated: { type: Boolean, required: true },
    user: { type: Object, required: true },
    shop: { type: Object, required: true },
    service: { type: Object, required: true },
    spec: { type: Object, required: true },
    quote: { type: Object, required: true },
    urls: { type: Object, required: true },
    methods: { type: Array, required: true },
});

const peso = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 2,
});

const money = (cents) => peso.format((cents ?? 0) / 100);

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

/* ------------------------------------------------------------------ form */

const form = ref({
    contact_name: props.user.name ?? '',
    contact_phone: props.user.phone ?? '',
    contact_email: props.user.email ?? '',
    fulfillment: 'pickup',
    pickup_person: '',
    notes: '',
    payment_method: 'gcash',
});

// A price that changed under the customer's feet. Held separately from `quote`
// so the summary can show the new total without pretending the rest of the
// breakdown was re-fetched.
const total = ref(props.quote.total);
const tier = ref(props.quote.tier);

const errors = ref({});
const status = ref('idle'); // idle | sending | failed

function clearError(field) {
    if (!errors.value[field]) return;

    const next = { ...errors.value };
    delete next[field];
    errors.value = next;
}

/**
 * The same rules the browser used to enforce with `required` attributes,
 * checked here so the answer is immediate rather than a round trip.
 */
function validate() {
    const found = {};

    if (!form.value.contact_name.trim()) found.contact_name = ['Tell the shop who to expect.'];
    if (!form.value.contact_phone.trim()) found.contact_phone = ['A number the shop can call.'];

    const email = form.value.contact_email.trim();
    if (!email) {
        found.contact_email = ['We send the proof here.'];
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        found.contact_email = ['That does not look like an email address.'];
    }

    errors.value = found;

    return Object.keys(found).length === 0;
}

async function placeOrder() {
    if (status.value === 'sending') return;

    if (!validate()) return;

    status.value = 'sending';

    const response = await fetch(props.urls.store, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({
            ...form.value,
            service_id: props.spec.service_id,
            width_in: props.spec.width_in,
            height_in: props.spec.height_in,
            qty: props.spec.qty,
            options: props.spec.options,
            expected_total: total.value,
        }),
    }).catch(() => null);

    if (response === null) {
        status.value = 'failed';
        errors.value = { checkout: ['Could not reach the server. Check your connection and try again.'] };

        return;
    }

    const data = await response.json().catch(() => ({}));

    if (response.ok) {
        // A full navigation rather than a router push: the job page is a
        // different Blade document, and the browser must not be able to go
        // "back" into a checkout that would create the order again.
        if (data.redirect) {
            window.location.assign(data.redirect);
        } else {
            window.location.reload();
        }

        return;
    }

    status.value = 'failed';
    errors.value = data.errors ?? { checkout: [data.message ?? 'The order could not be placed.'] };

    // The server refuses to charge a price the customer never saw. It sends the
    // new one back so the summary can correct itself and the customer can
    // confirm deliberately rather than being told to reload.
    if (typeof data.total === 'number') {
        total.value = data.total;
        tier.value = data.tier ?? tier.value;
    }
}

const busy = computed(() => status.value === 'sending');
</script>

<template>
    <div class="co-grid">
        <div>
            <div class="card co-panel">
                <div class="co-panel-head">
                    <h4>Contact details</h4>
                    <span class="note">We'll email the proof here</span>
                </div>

                <div class="form-grid">
                    <label>
                        <span class="lbl">Full name</span>
                        <input
                            v-model="form.contact_name"
                            class="inp"
                            type="text"
                            autocomplete="name"
                            placeholder="Jomar Mendoza"
                            :aria-invalid="!!errors.contact_name"
                            @input="clearError('contact_name')"
                        >
                        <span v-if="errors.contact_name" class="field-error">{{ errors.contact_name[0] }}</span>
                    </label>

                    <label>
                        <span class="lbl">Mobile number</span>
                        <input
                            v-model="form.contact_phone"
                            class="inp"
                            type="tel"
                            autocomplete="tel"
                            placeholder="+63 917 000 0000"
                            :aria-invalid="!!errors.contact_phone"
                            @input="clearError('contact_phone')"
                        >
                        <span v-if="errors.contact_phone" class="field-error">{{ errors.contact_phone[0] }}</span>
                    </label>
                </div>

                <label style="display:block;margin-top:12px">
                    <span class="lbl">Email address</span>
                    <input
                        v-model="form.contact_email"
                        class="inp"
                        type="email"
                        autocomplete="email"
                        placeholder="you@example.com"
                        :aria-invalid="!!errors.contact_email"
                        @input="clearError('contact_email')"
                    >
                    <span v-if="errors.contact_email" class="field-error">{{ errors.contact_email[0] }}</span>
                </label>
            </div>

            <div class="card co-panel" style="margin-top:16px">
                <div class="co-panel-head">
                    <h4>Fulfillment</h4>
                    <div class="seg">
                        <label :class="{ on: form.fulfillment === 'pickup' }">
                            <input v-model="form.fulfillment" type="radio" value="pickup">Pickup
                        </label>
                        <label :class="{ off: !shop.offersDelivery, on: form.fulfillment === 'delivery' }">
                            <input
                                v-model="form.fulfillment"
                                type="radio"
                                value="delivery"
                                :disabled="!shop.offersDelivery"
                            >Delivery
                        </label>
                    </div>
                </div>

                <div class="pick-block">
                    <span class="ico">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M3 10.5L12 3l9 7.5"/><path d="M5 10v10h14V10"/></svg>
                    </span>
                    <div style="min-width:0">
                        <b>{{ shop.name }}</b>
                        <div class="small">{{ shop.address }}, {{ shop.city }} · {{ shop.distance }} away</div>
                        <div class="tiny" style="color:var(--green);margin-top:3px">
                            Estimated ready {{ shop.readyAt }}
                        </div>
                    </div>
                </div>

                <label style="display:block;margin-top:14px">
                    <span class="lbl">Who is picking up?</span>
                    <input
                        v-model="form.pickup_person"
                        class="inp"
                        type="text"
                        placeholder="Name of the person collecting (optional)"
                    >
                </label>

                <label style="display:block;margin-top:12px">
                    <span class="lbl">Notes for the shop</span>
                    <textarea
                        v-model="form.notes"
                        class="inp"
                        rows="2"
                        placeholder="e.g. Leave 2 inches of white space at the bottom."
                    ></textarea>
                </label>
            </div>

            <div class="card co-panel" style="margin-top:16px">
                <div class="co-panel-head">
                    <h4>Payment method</h4>
                    <span class="tag tag-y">PayMongo · sandbox</span>
                </div>

                <div class="pay-row">
                    <label
                        v-for="method in methods"
                        :key="method.value"
                        class="pay-opt"
                        :class="{ on: form.payment_method === method.value }"
                    >
                        <input v-model="form.payment_method" type="radio" :value="method.value"> {{ method.label }}
                    </label>
                </div>

                <div class="tiny" style="margin-top:14px;padding:11px 13px;background:var(--tint-y);border-radius:9px;color:#8A5A00">
                    Sandbox mode: test keys only — no real charges are made. The webhook flow still runs, so orders move through every status exactly as they would live.
                </div>
            </div>
        </div>

        <aside>
            <div class="sum-box sticky-card">
                <div class="co-panel-head" style="padding:18px 18px 0;margin-bottom:0">
                    <h4 style="font-size:13px">Order summary</h4>
                    <a class="note" style="color:var(--ink);text-decoration:underline" :href="urls.edit">Edit</a>
                </div>

                <div style="display:flex;gap:11px;padding:14px 18px 0;align-items:center">
                    <span
                        style="width:42px;height:42px;border-radius:9px;flex:none"
                        :style="{ background: shop.gradient }"
                    ></span>
                    <div style="min-width:0">
                        <b style="display:block;font-size:12.5px">{{ service.name }} · {{ spec.sizeLabel }}</b>
                        <span class="tiny">{{ tier }} · qty {{ spec.qty }}</span>
                    </div>
                </div>

                <div class="sum-lines" style="margin-top:12px">
                    <div v-for="line in quote.lines" :key="line.label" class="sum-line">
                        <span>{{ line.label }} × {{ line.qty }}</span>
                        <b>{{ money(line.amount) }}</b>
                    </div>

                    <div v-if="quote.discount > 0" class="sum-line" style="color:var(--green)">
                        <span>Bulk tier discount</span>
                        <b style="color:var(--green)">−{{ money(quote.discount) }}</b>
                    </div>

                    <div class="sum-line">
                        <span>{{ form.fulfillment === 'delivery' ? 'Delivery' : 'Pickup' }}</span>
                        <b>{{ form.fulfillment === 'delivery' ? 'Arranged with the shop' : 'Free' }}</b>
                    </div>
                </div>

                <div class="sum-total">
                    <div>
                        <div class="l">Total due</div>
                        <div class="tiny" style="margin-top:3px">No platform fee added</div>
                    </div>
                    <div class="v">{{ money(total) }}</div>
                </div>

                <div style="padding:14px 18px 18px">
                    <div v-if="errors.checkout" class="alert alert-err" role="alert">
                        {{ errors.checkout[0] }}
                    </div>

                    <!-- The server refuses to charge a total the customer never
                         saw, and sends the new one back. Shown in the summary it
                         refers to, next to the button that was pressed. -->
                    <div v-if="errors.total" class="sum-error" role="alert" style="margin:0 0 10px">
                        {{ errors.total[0] }}
                    </div>

                    <button
                        v-if="authenticated"
                        class="btn btn-primary"
                        style="width:100%;justify-content:center"
                        type="button"
                        :disabled="busy"
                        @click="placeOrder"
                    >
                        {{ busy ? 'Placing order…' : `Pay ${money(total)}` }}
                    </button>

                    <template v-else>
                        <a class="btn btn-primary" style="width:100%;justify-content:center" :href="urls.signIn">
                            Sign in to place order
                        </a>
                        <p class="tiny" style="margin-top:10px;text-align:center">
                            Demo account: jomar@tarpalink.ph · password
                        </p>
                    </template>

                    <p v-if="authenticated" class="tiny" style="margin-top:9px;text-align:center">
                        Next: send your design file, then {{ shop.name }} sends a proof for you to approve.
                    </p>
                </div>
            </div>

            <div class="card" style="padding:14px 16px;margin-top:14px">
                <div class="tiny" style="margin-bottom:9px">Ordering from</div>
                <div style="display:flex;align-items:center;gap:10px">
                    <span class="avatar" :style="{ background: shop.gradient }">{{ shop.initials }}</span>
                    <div style="min-width:0">
                        <b style="display:block;font-size:12.5px">{{ shop.name }}</b>
                        <span class="tiny">★ {{ shop.rating }} · {{ shop.jobsDone }} jobs printed · {{ shop.turnaround }}</span>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</template>

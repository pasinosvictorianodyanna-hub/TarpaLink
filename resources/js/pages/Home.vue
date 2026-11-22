<script setup>
import { computed, ref } from 'vue';

/**
 * The landing page, as a Vue page rather than a Blade view.
 *
 * Laravel still owns the route, the session and the data: every string here
 * arrives as a prop that the controller shaped, including the headline split
 * and each shop's badge, distance and "from" price. The component only decides
 * layout. Nothing on this page computes a price or picks a badge.
 */
const props = defineProps({
    hero: { type: Object, required: true },
    banner: { type: Object, default: null },
    featured: { type: Array, default: () => [] },
    shopCount: { type: Number, default: 0 },
    testimonials: { type: Object, default: null },
    faq: { type: Object, default: null },
    routes: { type: Object, required: true },
});

const heroSpecimens = [
    {
        tab: 'Tarpaulin',
        art: 'Grand Opening',
        rows: [
            ['36 × 24 in · 13oz vinyl', '6 sq ft'],
            ['Eyelets · hemming', '₱120'],
            ['Quantity', '3 pcs'],
        ],
        total: '1,485',
        sub: 'All-in price\nincl. rush fee',
    },
    {
        tab: 'Sticker',
        art: 'Coffee Roasters',
        rows: [
            ['3 × 3 in · Die-cut vinyl', 'Waterproof'],
            ['Matte lamination', '₱200'],
            ['Quantity', '250 pcs'],
        ],
        total: '1,650',
        sub: 'Bulk tier (−10%)\nincl. lamination',
    },
    {
        tab: 'Flyer',
        art: 'Summer Menu',
        rows: [
            ['A5 (5.8 × 8.3 in) · 120gsm', 'Full colour'],
            ['Matte finish', '₱200'],
            ['Quantity', '500 pcs'],
        ],
        total: '1,870',
        sub: 'Bulk tier (−15%)\nsame-day ready',
    },
    {
        tab: 'Shirt',
        art: 'Crew 2026',
        rows: [
            ['Front + back · DTF transfer', '12 × 12 in'],
            ['Individual bagging', 'Included'],
            ['Quantity', '24 pcs'],
        ],
        total: '5,304',
        sub: 'Bulk tier (−15%)\n₱221 per shirt',
    },
];

const activeHeroTab = ref('Tarpaulin');
const activeSpecimen = computed(
    () => heroSpecimens.find((s) => s.tab === activeHeroTab.value) ?? heroSpecimens[0],
);

const marquee = [
    'Tarpaulin', 'Stickers & Labels', 'Flyers', 'Business Cards', 'DTF Shirts',
    'Standees', 'Posters', 'Vehicle Stickers', 'Mugs & Merch', 'Invitations',
];

const steps = [
    {
        num: '01',
        title: 'Find a shop near you',
        body: 'Filter by service, material, rating, lead time and distance on the map.',
        icon: 'search',
    },
    {
        num: '02',
        title: 'Build the order, see the price',
        body: 'Size, material, finishing, quantity — the quote recalculates as you type.',
        icon: 'chart',
    },
    {
        num: '03',
        title: 'Approve the proof, then collect',
        body: 'The shop sends a digital proof. Approve it, track production, scan to pick up.',
        icon: 'crop',
    },
];

const matrix = [
    { size: '24 × 36', sqft: '6.0', rate: '₱45', subtotal: '₱270', hl: false },
    { size: '36 × 48', sqft: '12.0', rate: '₱45', subtotal: '₱540', hl: true },
    { size: '48 × 72', sqft: '24.0', rate: '₱42', subtotal: '₱1,008', hl: false },
    { size: '72 × 96', sqft: '48.0', rate: '₱38', subtotal: '₱1,824', hl: false },
];

const tints = ['#0C0F0E', '#B3174A', '#05649B', '#F5B700'];
const tintFg = ['#FFFFFF', '#FFFFFF', '#FFFFFF', '#0C0F0E'];

const initialsOf = (name) =>
    String(name || '')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');

const tintOf = (index) => tints[index % tints.length];
const tintFgOf = (index) => tintFg[index % tintFg.length];
</script>

<template>
    <div v-if="banner && banner.text" class="announce">
        <div class="wrap">
            <span>{{ banner.text }}</span>
            <a v-if="banner.link" :href="banner.link">{{ banner.linkLabel || 'Learn more' }}</a>
        </div>
    </div>

    <div class="hero">
        <div class="wrap hero-grid">
            <div>
                <span class="eyebrow">{{ hero.eyebrow }}</span>

                <h1 class="display">
                    <template v-for="(line, i) in hero.lines" :key="i">{{ line }}<br></template>
                    <!-- The space lives in the expression: the template compiler
                         condenses a bare space before the next element away. -->
                    <template v-if="hero.head">{{ hero.head + ' ' }}</template>
                    <span v-if="hero.tail" class="mark-under">{{ hero.tail }}<svg viewBox="0 0 200 40" preserveAspectRatio="none" aria-hidden="true"><path d="M3 27C46 13 148 9 197 19" /></svg></span>
                </h1>

                <p class="lead">{{ hero.subheadline }}</p>

                <form class="quick-quote" method="GET" :action="routes.shops">
                    <label class="qq-field">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="M20 20l-3.5-3.5" /></svg>
                        <input type="text" name="q" placeholder="Tarpaulin, stickers, flyers…" aria-label="Search service or product">
                    </label>
                    <label class="qq-field" style="max-width:150px">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 21s7-5.4 7-11a7 7 0 10-14 0c0 5.6 7 11 7 11z" /><circle cx="12" cy="10" r="2.5" /></svg>
                        <input type="text" name="city" placeholder="Quezon City" aria-label="City or area">
                    </label>
                    <button class="btn btn-primary" type="submit">Quote</button>
                </form>

                <div class="hero-trust">
                    <div class="avatars" aria-hidden="true">
                        <span style="background:#0C0F0E">JM</span>
                        <span style="background:#B3174A">AR</span>
                        <span style="background:#05649B">KC</span>
                        <span style="background:#F5B700;color:#0C0F0E">DL</span>
                    </div>
                    <div>
                        <span class="num mono">128</span>
                        <span class="lbl">verified shops</span>
                    </div>
                    <div class="sep"></div>
                    <div>
                        <span class="num mono">4.8★</span>
                        <span class="lbl">avg. shop rating</span>
                    </div>
                    <div class="sep"></div>
                    <div>
                        <span class="num mono">36h</span>
                        <span class="lbl">avg. turnaround</span>
                    </div>
                </div>
            </div>

            <div class="hero-art">
                <div class="crop" style="color:#B9B3A4">
                    <i></i><i></i><i></i><i></i>
                    <div class="qc">
                        <div class="qc-top">
                            <span class="qc-title">Instant quote</span>
                            <span class="qc-live"><i></i>Live pricing</span>
                        </div>
                        <div class="qc-tabs" role="tablist" aria-label="Sample print quotes">
                            <button
                                v-for="spec in heroSpecimens"
                                :key="spec.tab"
                                type="button"
                                role="tab"
                                :aria-selected="activeHeroTab === spec.tab"
                                :class="{ on: activeHeroTab === spec.tab }"
                                @click="activeHeroTab = spec.tab"
                            >{{ spec.tab }}</button>
                        </div>
                        <div class="qc-preview">
                            <div class="eyelets"></div>
                            <div class="art-frame"><b>{{ activeSpecimen.art }}</b></div>
                        </div>
                        <div class="qc-rows">
                            <div v-for="([k, v], rIdx) in activeSpecimen.rows" :key="rIdx" class="qc-row">
                                <span>{{ k }}</span><b class="mono">{{ v }}</b>
                            </div>
                        </div>
                        <div class="qc-total">
                            <div><span class="cur">₱</span><span class="amt mono">{{ activeSpecimen.total }}</span></div>
                            <div class="lbl" style="white-space:pre-line">{{ activeSpecimen.sub }}</div>
                        </div>
                    </div>
                </div>
                <div class="qc-float f1">
                    <span class="ico" style="background:#E7F6EF;color:#0E9F6E">
                        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M5 13l4 4L19 7" /></svg>
                    </span>
                    <div>Proof approved<small>2 minutes ago</small></div>
                </div>
                <div class="qc-float f2">
                    <span class="ico" style="background:#FDE9EF;color:#E8265E">
                        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 7v5l3 2" /><circle cx="12" cy="12" r="9" /></svg>
                    </span>
                    <div>Ready in 24 hrs<small>Rush production</small></div>
                </div>
            </div>
        </div>

        <div class="marquee">
            <div class="marquee-track">
                <span v-for="(item, i) in [...marquee, ...marquee]" :key="i">{{ item }}</span>
            </div>
        </div>
    </div>

    <div id="how" class="sec">
        <div class="wrap">
            <div class="sec-head">
                <div>
                    <span class="eyebrow">How it works</span>
                    <h2 class="h2">Three steps from idea<br>to printed.</h2>
                </div>
                <p>Every shop publishes its own price matrix, so the number you see is the number you pay.</p>
            </div>
            <div class="steps">
                <div v-for="step in steps" :key="step.num" class="step">
                    <span class="step-num">{{ step.num }}</span>
                    <div class="step-ico">
                        <svg v-if="step.icon === 'search'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="7" /><path d="M20 20l-3.5-3.5" /></svg>
                        <svg v-else-if="step.icon === 'chart'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 20V9M10 20V4M16 20v-7M22 20H2" /></svg>
                        <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4" /><path d="M9.5 12h5" /></svg>
                    </div>
                    <h4>{{ step.title }}</h4>
                    <p>{{ step.body }}</p>
                </div>
            </div>
        </div>
    </div>

    <div id="pricing" class="sec" style="padding-top:0">
        <div class="wrap split">
            <div>
                <span class="eyebrow">Transparent pricing</span>
                <h2 class="h2" style="margin-top:14px">The price matrix,<br>out in the open.</h2>
                <p class="lead" style="margin-top:18px;font-size:15.5px">Every shop sets its own rates per square foot, per material, plus minimum charges and finishing fees. TarpaLink computes the total — including quantity discounts and rush surcharges — the moment you change a spec.</p>
                <ul class="bullets">
                    <li v-for="bullet in ['No hidden fees — the breakdown is itemised before checkout.', 'Quantity tiers apply automatically, not on request.', 'Compare the same job across shops in one table.']" :key="bullet">
                        <span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.4" stroke-linecap="round"><path d="M5 13l4 4L19 7" /></svg></span>{{ bullet }}
                    </li>
                </ul>
                <a class="btn btn-primary" style="margin-top:26px" :href="routes.shops">Try the calculator</a>
            </div>
            <div class="matrix-card">
                <div class="matrix-head">
                    <b>Tarpaulin · 13oz vinyl</b>
                    <span class="tag tag-c">Best seller</span>
                </div>
                <table class="matrix">
                    <thead>
                        <tr><th>Size (in)</th><th class="num">Sq ft</th><th class="num">Rate</th><th class="num">Subtotal</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in matrix" :key="row.size" :class="{ hl: row.hl }">
                            <td>{{ row.size }}</td>
                            <td class="num">{{ row.sqft }}</td>
                            <td class="num">{{ row.rate }}</td>
                            <td class="num">{{ row.subtotal }}</td>
                        </tr>
                    </tbody>
                </table>
                <div class="matrix-foot">
                    <span>+ Eyelets ₱10/pc · Hemming ₱15/ft</span>
                    <b style="color:var(--ink)">Min. ₱250</b>
                </div>
            </div>
        </div>
    </div>

    <div class="sec" style="padding-top:0">
        <div class="wrap">
            <div class="sec-head">
                <div>
                    <span class="eyebrow">Verified shops</span>
                    <h2 class="h2">Trusted by 4,200+ customers.</h2>
                </div>
                <a class="btn btn-ghost btn-sm" :href="routes.shops">Browse all {{ shopCount }} shops</a>
            </div>
            <div class="shops">
                <article v-for="shop in featured" :key="shop.url" class="shop">
                    <a :href="shop.url" class="shop-cover" :style="{ background: shop.cover }">
                        <div class="dots halftone"></div><div class="crop"></div>
                        <span class="label">{{ shop.name }}</span>
                    </a>
                    <div class="shop-body">
                        <div class="shop-name">{{ shop.name }}
                            <svg class="vf vf-dot" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="12" r="9" /></svg>
                        </div>
                        <div class="shop-meta">
                            <span class="st"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 6.5 7 1-5 4.9 1.2 7L12 18l-6.2 3.4L7 14.4 2 9.5l7-1z" /></svg>{{ shop.rating }}</span>
                            <span>({{ shop.jobsDone }})</span><span>·</span><span>{{ shop.distance }}</span>
                        </div>
                        <div class="shop-foot">
                            <span class="shop-price">from <b>{{ shop.price }}</b>/{{ shop.unit }}</span>
                            <span v-if="shop.badge" :class="['tag', 'tag-' + shop.badge.tone]">{{ shop.badge.label }}</span>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </div>

    <div id="owners" class="wrap">
        <div class="cta-band">
            <div class="grid-paper"></div>
            <div>
                <span class="eyebrow" style="color:#8C9691">For shop owners</span>
                <h2 class="h2" style="margin-top:14px">Stop quoting<br>on Messenger.</h2>
                <p>Publish your price matrix once. Let the platform quote, collect payment, and hand you a production-ready job order.</p>
                <a class="btn btn-lg" style="background:#fff;color:#0C0F0E" :href="routes.openShop">Open a shop account</a>
            </div>
            <div class="cta-stats">
                <div><div class="n">−72%</div><div class="l">time spent quoting</div></div>
                <div><div class="n">2.4×</div><div class="l">repeat customers</div></div>
                <div><div class="n">₱0</div><div class="l">setup fee</div></div>
                <div><div class="n">5%</div><div class="l">per completed order</div></div>
            </div>
        </div>
    </div>

    <div v-if="faq && faq.items && faq.items.length" class="sec">
        <div class="wrap">
            <div class="sec-head">
                <div>
                    <span class="eyebrow">Questions</span>
                    <h2 class="h2">{{ faq.title || 'Common questions' }}</h2>
                </div>
            </div>
            <div class="faq">
                <details v-for="(item, i) in faq.items" :key="i" class="faq-item">
                    <summary>{{ item.q }}</summary>
                    <p>{{ item.a }}</p>
                </details>
            </div>
        </div>
    </div>

    <div v-if="testimonials && testimonials.items && testimonials.items.length" class="sec">
        <div class="wrap">
            <div class="sec-head">
                <div>
                    <span class="eyebrow">Reviews</span>
                    <h2 class="h2">{{ testimonials.title || 'What customers say' }}</h2>
                </div>
            </div>
            <div class="quotes">
                <div v-for="(item, i) in testimonials.items" :key="i" :class="['quote', item.highlight ? 'hl' : '']">
                    <div class="stars">
                        <svg v-for="n in 5" :key="n" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 6.5 7 1-5 4.9 1.2 7L12 18l-6.2 3.4L7 14.4 2 9.5l7-1z" /></svg>
                    </div>
                    <p>&ldquo;{{ item.quote }}&rdquo;</p>
                    <div class="who">
                        <span class="av" :style="{ background: tintOf(i), color: tintFgOf(i) }">{{ initialsOf(item.name) }}</span>
                        <div><b>{{ item.name }}</b><span>{{ item.role }}</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

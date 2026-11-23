<script setup>
/**
 * The customer dashboard — "My jobs".
 *
 * Laravel decides everything that is a claim about the data: the three counters
 * and their notes, which job gets which button, whether the proof bar appears,
 * the tab counts and the tab URLs. The component decides layout and nothing else,
 * so the number on a counter and the length of the list below it cannot disagree.
 */
defineProps({
    summary: { type: String, default: '' },
    kpis: { type: Array, default: () => [] },
    alert: { type: Object, default: null },
    tabs: { type: Array, default: () => [] },
    search: { type: Object, required: true },
    jobs: { type: Array, default: () => [] },
    empty: { type: Object, default: null },
    account: { type: Object, default: null },
    routes: { type: Object, required: true },
});

/**
 * The action button is a flex item in a row of other flex items, so it needs
 * `flex: none` or the browser stretches it to the row's height. The magenta
 * variant is the one that needs an inline colour, because it is the only place
 * in the app a button is drawn in the accent colour.
 */
const actionStyle = (action) => ({
    flex: 'none',
    ...(action.style === 'magenta'
        ? { background: 'var(--magenta)', borderColor: 'var(--magenta)', color: '#fff' }
        : {}),
});
</script>

<template>
    <div class="app">
        <div class="app-body" style="max-width:940px">
            <div class="page-head">
                <div>
                    <h1 class="page-title">My jobs</h1>
                    <p class="page-sub">{{ summary }}</p>
                </div>
                <a class="btn btn-primary" :href="routes.shops">Start a new job</a>
            </div>

            <div class="counters">
                <div v-for="kpi in kpis" :key="kpi.label" class="counter">
                    <span class="k">{{ kpi.label }}</span>
                    <span class="n">{{ kpi.value }}</span>
                    <span :class="['s', kpi.tone === 'warn' ? 'warn' : '']">{{ kpi.note }}</span>
                </div>
            </div>

            <div v-if="alert" class="jobs-alert">
                <span class="ico">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path d="M12 8v5" /><circle cx="12" cy="16.5" r=".6" fill="currentColor" />
                        <path d="M10.3 3.9L2.6 17a2 2 0 001.7 3h15.4a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z" />
                    </svg>
                </span>
                <div>
                    <b>{{ alert.title }}</b>
                    <div class="meta">{{ alert.meta }}</div>
                </div>
                <a class="btn btn-primary btn-sm" style="background:var(--magenta);border-color:var(--magenta)" :href="alert.url">
                    {{ alert.label }}
                </a>
            </div>

            <div class="jobs-bar">
                <div class="jobs-tabs">
                    <a v-for="tab in tabs" :key="tab.key" :class="['jobs-tab', tab.on ? 'on' : '']" :href="tab.url">
                        {{ tab.label }} <em>{{ tab.count }}</em>
                    </a>
                </div>
                <form method="GET" :action="routes.account" class="field jobs-search">
                    <input v-if="search.filter !== 'all'" type="hidden" name="status" :value="search.filter">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="M20 20l-3.5-3.5" /></svg>
                    <input type="search" name="q" :value="search.q" placeholder="Search your jobs" aria-label="Search your jobs">
                </form>
            </div>

            <article v-for="(job, i) in jobs" :key="i" class="card job-row" :style="job.dim ? { opacity: 0.72 } : null">
                <span class="job-thumb" :style="{ background: job.cover }"></span>

                <div class="job-info">
                    <div style="display:flex;align-items:center;gap:9px;flex-wrap:wrap">
                        <b style="font-size:13.5px;letter-spacing:-.01em">{{ job.title }}</b>
                        <span :class="['status', 'st-' + job.tone]"><i></i>{{ job.status }}</span>
                    </div>
                    <div class="small" style="margin-top:4px">{{ job.shop }} · {{ job.meta }}</div>
                </div>

                <div class="job-price">
                    <div class="mono" style="font-size:14px;font-weight:600;letter-spacing:-.02em">{{ job.total }}</div>
                    <div class="tiny" style="margin-top:3px">{{ job.next }}</div>
                </div>

                <a
                    :class="['btn', 'btn-sm', 'job-act', job.action.style === 'ghost' ? 'btn-ghost' : '']"
                    :style="actionStyle(job.action)"
                    :href="job.action.url"
                >{{ job.action.label }}</a>
            </article>

            <div v-if="empty" class="card" style="padding:48px 24px;text-align:center">
                <b>{{ empty.title }}</b>
                <p class="small" style="margin-top:6px">{{ empty.body }}</p>
                <a v-if="empty.cta" class="btn btn-primary btn-sm" style="margin-top:16px" :href="empty.cta.url">{{ empty.cta.label }}</a>
            </div>

            <div v-if="account" class="acct-foot">
                <div class="acct-foot-h">Account</div>
                <div class="acct-foot-row">
                    <div class="avatar lg">{{ account.initials }}</div>
                    <div style="flex:1;min-width:180px">
                        <b style="font-size:13px">{{ account.name }}</b>
                        <div class="small" style="margin-top:2px">{{ account.contact }}</div>
                    </div>
                    <div class="tiny" style="text-align:right">Pickup at<br>{{ account.city }}</div>
                    <a class="btn btn-ghost btn-sm" :href="account.url">Edit profile</a>
                </div>
            </div>
        </div>
    </div>
</template>

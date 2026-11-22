<script setup>
/**
 * The shop dashboard.
 *
 * The console shell — sidebar, top bar, page header — is part of this component
 * rather than a Blade layout, because the shell is the same on every console
 * screen and will be shared by the screens still to be ported. Laravel decides
 * the numbers, the active nav item and the badge counts; the component draws
 * them.
 */
defineProps({
    csrf: { type: String, required: true },
    shell: { type: Object, required: true },
    heading: { type: Object, required: true },
    stats: { type: Array, default: () => [] },
    dueToday: { type: Object, required: true },
    week: { type: Object, required: true },
    attention: { type: Object, required: true },
    routes: { type: Object, required: true },
});

/** The design's icon set, keyed so the server can name an icon without markup. */
const icons = {
    grid: '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
    inbox: '<path d="M4 7h16v13H4z"/><path d="M8 7V4h8v3"/>',
    board: '<rect x="3" y="4" width="5" height="16" rx="1.5"/><rect x="10" y="4" width="5" height="10" rx="1.5"/><rect x="17" y="4" width="4" height="13" rx="1.5"/>',
    table: '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18M9 10v10"/>',
    chart: '<path d="M4 20V9M10 20V4M16 20v-7M22 20H2"/>',
    doc: '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
};

const logo = '<path d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4"/><path d="M9.5 12h5"/>';
</script>

<template>
    <div class="fil">
        <aside class="fil-side">
            <a class="fil-brand" :href="routes.dashboard">
                <span class="lm">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" v-html="logo"></svg>
                </span>
                {{ shell.brand }}
                <small>{{ shell.badge }}</small>
            </a>

            <nav class="fil-nav">
                <template v-for="group in shell.groups" :key="group.label">
                    <div class="fil-group">{{ group.label }}</div>
                    <a
                        v-for="item in group.items"
                        :key="item.label"
                        :class="['fil-item', item.active ? 'on' : '']"
                        :href="item.url"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" v-html="icons[item.icon] || ''"></svg>
                        {{ item.label }}
                        <span v-if="item.badge" class="bdg">{{ item.badge }}</span>
                    </a>
                </template>
            </nav>

            <div class="fil-user">
                <span class="av">{{ shell.user.initials }}</span>
                <div><b>{{ shell.user.name }}</b><span>{{ shell.user.role }}</span></div>

                <!--
                    Sign out has to be here. The console is a separate shell from
                    the storefront, so the header's sign-out is not on this page,
                    and the Filament panels put theirs behind the avatar — which
                    left an owner with no visible way out of the console at all.

                    A plain form POST, not a fetch: Laravel's logout is a POST
                    route, and this works without a line of JavaScript.
                -->
                <form method="POST" :action="routes.logout">
                    <input type="hidden" name="_token" :value="csrf">
                    <button class="out" type="submit" title="Sign out" aria-label="Sign out">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round">
                            <path d="M15 17l5-5-5-5" /><path d="M20 12H9" />
                            <path d="M12 20H5a2 2 0 01-2-2V6a2 2 0 012-2h7" />
                        </svg>
                    </button>
                </form>
            </div>
        </aside>

        <div class="fil-main">
            <div class="fil-top">
                <div class="fil-crumb">
                    <span>{{ shell.brand }}</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M9 18l6-6-6-6" /></svg>
                    <b>{{ heading.title }}</b>
                </div>
                <div class="fil-top-right">
                    <div class="fil-search">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7" /><path d="M20 20l-3.5-3.5" /></svg>
                        Search
                        <span style="margin-left:auto;font-size:10px;border:1px solid #E5E7EB;border-radius:4px;padding:0 4px">⌘K</span>
                    </div>
                    <a class="fil-ico" :href="routes.export" title="Export payouts">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3v12" /><path d="M7 10l5 5 5-5" /><path d="M4 21h16" /></svg>
                    </a>
                </div>
            </div>

            <div class="fil-page">
                <div class="fil-ph">
                    <div>
                        <h3>{{ heading.title }}</h3>
                        <p>{{ heading.sub }}</p>
                    </div>
                    <div class="fil-actions">
                        <a class="fbtn fbtn-ghost" :href="routes.export">Export</a>
                        <a class="fbtn fbtn-primary" :href="routes.newJob">+ New job</a>
                    </div>
                </div>

                <div class="fil-stats">
                    <div v-for="stat in stats" :key="stat.label" class="fil-stat">
                        <div class="l">{{ stat.label }}</div>
                        <div class="v">{{ stat.value }}</div>
                        <div :class="['d', stat.arrow === 'up' ? 'up' : stat.arrow === 'down' ? 'dn' : '']">
                            <template v-if="stat.arrow">{{ stat.arrow === 'up' ? '↑' : '↓' }}</template>
                            {{ stat.note }}
                        </div>
                    </div>
                </div>

                <div class="fil-cols two">
                    <div class="fil-card">
                        <div class="fil-toolbar">
                            <b style="font-size:12.5px">Due today</b>
                            <span style="margin-left:auto;font-size:11px;color:#9CA3AF">
                                {{ dueToday.count }} {{ dueToday.count === 1 ? 'job' : 'jobs' }}
                            </span>
                        </div>
                        <table class="ftbl">
                            <thead>
                                <tr><th>Job</th><th>Customer</th><th>Spec</th><th>Status</th><th class="r">Amount</th></tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in dueToday.rows" :key="row.reference">
                                    <td class="t">{{ row.reference }}</td>
                                    <td>{{ row.customer }}</td>
                                    <td>
                                        <div class="fil-mini">
                                            <span class="th2" :style="{ background: row.cover }"></span>
                                            <div><b>{{ row.title }}</b><span>{{ row.spec }}</span></div>
                                        </div>
                                    </td>
                                    <td><span :class="['fb', 'fb-' + row.tone]">{{ row.status }}</span></td>
                                    <td class="r" style="font-weight:600">{{ row.total }}</td>
                                </tr>
                                <tr v-if="!dueToday.rows.length">
                                    <td colspan="5" style="color:#9CA3AF">Nothing due today.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="fil-card">
                        <div class="fil-toolbar">
                            <b style="font-size:12.5px">Last 7 days</b>
                            <span style="margin-left:auto;font-size:11px;color:#9CA3AF">{{ week.total }}</span>
                        </div>
                        <div style="padding:14px 16px">
                            <div class="bars" style="height:96px">
                                <div v-for="(day, i) in week.days" :key="i" :class="['b', day.current ? 'hi' : '']" :style="{ height: day.pct + '%' }"></div>
                            </div>
                            <div class="bar-x">
                                <span v-for="(day, i) in week.days" :key="i">{{ day.label }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="fil-card" style="margin-top:16px">
                    <div class="fil-toolbar">
                        <b style="font-size:12.5px">Needs attention</b>
                        <span :class="['fb', 'fb-r']">{{ attention.count }}</span>
                    </div>
                    <table class="ftbl">
                        <thead><tr><th>Job</th><th>Issue</th><th>Waiting</th><th></th></tr></thead>
                        <tbody>
                            <tr v-for="row in attention.rows" :key="row.reference">
                                <td class="t">{{ row.reference }}</td>
                                <td>{{ row.issue }}</td>
                                <td style="color:#9CA3AF">{{ row.waiting }}</td>
                                <td class="r"><a :class="['fb', 'fb-y']" :href="row.url">Open job</a></td>
                            </tr>
                            <tr v-if="!attention.rows.length">
                                <td colspan="4" style="color:#9CA3AF">Nothing is stuck. Everything is either moving or finished.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

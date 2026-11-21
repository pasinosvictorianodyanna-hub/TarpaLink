<script setup>
import { computed, ref } from 'vue';

/**
 * The profile screen, as a Vue island.
 *
 * Why Vue and not a Blade form: both cards save without a page reload, and the
 * details card has to know whether anything actually changed before it offers
 * to save. That is state, not markup — exactly the split docs/SPLIT.md draws.
 */
const props = defineProps({
    user: { type: Object, required: true },
    updateUrl: { type: String, required: true },
    passwordUrl: { type: String, required: true },
    passwordChangedAt: { type: String, default: null },
});

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

async function send(url, method, body) {
    const response = await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(body),
    });

    const data = await response.json().catch(() => ({}));

    return { ok: response.ok, status: response.status, data };
}

/* ---------------------------------------------------------------- details */

const details = ref({ ...props.user });
const detailErrors = ref({});
const detailStatus = ref('idle'); // idle | saving | saved | failed

// Compared against the values the server sent, not against "did the user type":
// retyping the same name is not a change worth writing.
const isDirty = computed(() => JSON.stringify(details.value) !== JSON.stringify(props.user));

let savedTimer = null;

async function saveDetails() {
    if (!isDirty.value || detailStatus.value === 'saving') return;

    detailStatus.value = 'saving';
    detailErrors.value = {};

    const { ok, data } = await send(props.updateUrl, 'PATCH', details.value);

    if (ok) {
        detailStatus.value = 'saved';
        clearTimeout(savedTimer);
        savedTimer = setTimeout(() => {
            if (detailStatus.value === 'saved') detailStatus.value = 'idle';
        }, 2600);

        return;
    }

    detailStatus.value = 'failed';
    detailErrors.value = data.errors ?? { name: [data.message ?? 'Could not save.'] };
}

function clearDetailError(field) {
    if (detailErrors.value[field]) {
        const next = { ...detailErrors.value };
        delete next[field];
        detailErrors.value = next;
    }
}

/* --------------------------------------------------------------- password */

const password = ref({ current_password: '', password: '', password_confirmation: '' });
const passwordErrors = ref({});
const passwordStatus = ref('idle'); // idle | saving | saved | failed
const changedAt = ref(props.passwordChangedAt);

// All three boxes or nothing: offering a button that is guaranteed to fail
// validation just teaches the user to ignore it.
const passwordReady = computed(
    () =>
        password.value.current_password.length > 0 &&
        password.value.password.length > 0 &&
        password.value.password_confirmation.length > 0,
);

async function changePassword() {
    if (passwordStatus.value === 'saving') return;

    passwordErrors.value = {};

    // Checked here as well as on the server: a round trip to be told two boxes
    // do not match is a worse experience than being told immediately.
    if (password.value.password.length < 8) {
        passwordErrors.value = { password: ['Use at least 8 characters.'] };
        return;
    }

    if (password.value.password !== password.value.password_confirmation) {
        passwordErrors.value = { password_confirmation: ['The two passwords do not match.'] };
        return;
    }

    passwordStatus.value = 'saving';

    const { ok, data } = await send(props.passwordUrl, 'PUT', password.value);

    if (ok) {
        passwordStatus.value = 'saved';
        changedAt.value = data.changedAt ?? 'just now';
        password.value = { current_password: '', password: '', password_confirmation: '' };

        return;
    }

    passwordStatus.value = 'failed';
    passwordErrors.value = data.errors ?? { current_password: [data.message ?? 'Could not change it.'] };
}
</script>

<template>
    <div class="pf-wrap">
        <form class="card pf-card" novalidate @submit.prevent="saveDetails">
            <header class="pf-head">
                <h4>Personal details</h4>
            </header>

            <div class="pf-body">
                <div class="form-grid">
                    <label>
                        <span class="lbl">Full name</span>
                        <input
                            v-model="details.name"
                            class="inp"
                            type="text"
                            autocomplete="name"
                            placeholder="Jomar Mendoza"
                            :aria-invalid="!!detailErrors.name"
                            @input="clearDetailError('name')"
                        >
                        <span v-if="detailErrors.name" class="field-error">{{ detailErrors.name[0] }}</span>
                    </label>

                    <label>
                        <span class="lbl">Mobile number</span>
                        <input
                            v-model="details.phone"
                            class="inp"
                            type="tel"
                            autocomplete="tel"
                            placeholder="+63 917 000 0000"
                            @input="clearDetailError('phone')"
                        >
                        <span v-if="detailErrors.phone" class="field-error">{{ detailErrors.phone[0] }}</span>
                    </label>

                    <label class="full">
                        <span class="lbl">Email address</span>
                        <input
                            v-model="details.email"
                            class="inp"
                            type="email"
                            autocomplete="email"
                            placeholder="you@example.com"
                            :aria-invalid="!!detailErrors.email"
                            @input="clearDetailError('email')"
                        >
                        <span v-if="detailErrors.email" class="field-error">{{ detailErrors.email[0] }}</span>
                    </label>

                    <label class="full">
                        <span class="lbl">Preferred pickup area</span>
                        <input
                            v-model="details.city"
                            class="inp"
                            type="text"
                            autocomplete="address-level2"
                            placeholder="Quezon City"
                            @input="clearDetailError('city')"
                        >
                        <span v-if="detailErrors.city" class="field-error">{{ detailErrors.city[0] }}</span>
                    </label>
                </div>
            </div>

            <footer class="pf-foot">
                <span v-if="detailStatus === 'saved'" class="pf-note ok">Saved.</span>
                <span v-else-if="detailStatus === 'failed'" class="pf-note bad">Not saved — check the fields above.</span>
                <span v-else-if="isDirty" class="pf-note">Unsaved changes</span>

                <button class="btn btn-primary btn-sm" type="submit" :disabled="!isDirty || detailStatus === 'saving'">
                    {{ detailStatus === 'saving' ? 'Saving…' : 'Save changes' }}
                </button>
            </footer>
        </form>

        <form class="card pf-card" novalidate @submit.prevent="changePassword">
            <header class="pf-head">
                <h4>Password</h4>
                <span class="pf-meta">
                    {{ changedAt ? `Last changed ${changedAt}` : 'Never changed' }}
                </span>
            </header>

            <div class="pf-body">
                <div class="form-grid">
                    <label class="full">
                        <span class="lbl">Current password</span>
                        <input
                            v-model="password.current_password"
                            class="inp"
                            type="password"
                            autocomplete="current-password"
                            placeholder="Your current password"
                            :aria-invalid="!!passwordErrors.current_password"
                        >
                        <span v-if="passwordErrors.current_password" class="field-error">{{ passwordErrors.current_password[0] }}</span>
                    </label>

                    <label>
                        <span class="lbl">New password</span>
                        <input
                            v-model="password.password"
                            class="inp"
                            type="password"
                            autocomplete="new-password"
                            placeholder="At least 8 characters"
                            :aria-invalid="!!passwordErrors.password"
                        >
                        <span v-if="passwordErrors.password" class="field-error">{{ passwordErrors.password[0] }}</span>
                    </label>

                    <label>
                        <span class="lbl">Confirm new password</span>
                        <input
                            v-model="password.password_confirmation"
                            class="inp"
                            type="password"
                            autocomplete="new-password"
                            placeholder="Repeat password"
                            :aria-invalid="!!passwordErrors.password_confirmation"
                        >
                        <span v-if="passwordErrors.password_confirmation" class="field-error">{{ passwordErrors.password_confirmation[0] }}</span>
                    </label>
                </div>
            </div>

            <footer class="pf-foot">
                <span v-if="passwordStatus === 'saved'" class="pf-note ok">Password changed.</span>
                <span v-else-if="passwordStatus === 'failed'" class="pf-note bad">Password not changed.</span>

                <button
                    class="btn btn-primary btn-sm"
                    type="submit"
                    :disabled="!passwordReady || passwordStatus === 'saving'"
                >
                    {{ passwordStatus === 'saving' ? 'Changing…' : 'Change password' }}
                </button>
            </footer>
        </form>
    </div>
</template>

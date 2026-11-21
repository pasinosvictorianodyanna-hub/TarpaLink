import { createApp } from 'vue';
import OrderBuilder from './components/OrderBuilder.vue';
import ProfileForm from './components/ProfileForm.vue';

/**
 * Vue islands.
 *
 * The storefront is server-rendered Blade. Vue mounts only on elements that
 * declare a component, so the landing page and directory ship no JS payload.
 * See docs/SPLIT.md — "Vue renders, Filament edits, Laravel decides."
 */
const registry = {
    'order-builder': OrderBuilder,
    'profile-form': ProfileForm,
};

document.querySelectorAll('[data-component]').forEach((el) => {
    const component = registry[el.dataset.component];

    if (!component) {
        console.warn(`[TarpaLink] Unknown component: ${el.dataset.component}`);
        return;
    }

    const props = el.dataset.props ? JSON.parse(el.dataset.props) : {};

    createApp(component, props).mount(el);
});

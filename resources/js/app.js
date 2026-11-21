import { createApp } from 'vue';
import OrderBuilder from './components/OrderBuilder.vue';
import ProfileForm from './components/ProfileForm.vue';
import Home from './pages/Home.vue';

/**
 * Vue pages and islands.
 *
 * Blade still owns the document — the nav, the footer, the session and the CSRF
 * token are server-rendered — but the body of a page can now be a Vue component.
 * Vue mounts only on elements that declare a component, so a screen with no
 * `data-component` ships no JS payload at all.
 *
 * `pages/*` are whole screens; `components/*` are islands inside a Blade screen.
 * Both go through the same registry, so adding a screen is one import and one key.
 * See docs/SPLIT.md — "Vue renders, Filament edits, Laravel decides."
 */
const registry = {
    home: Home,
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

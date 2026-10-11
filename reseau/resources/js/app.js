import '../css/app.css';
import { createApp } from 'vue';
import App from './App.vue';
import { router } from './router';
import { loadSession } from './stores/session';

// iOS Safari ignore « user-scalable=no » : on bloque les gestes de pincement hors carte.
for (const type of ['gesturestart', 'gesturechange', 'gestureend']) {
    document.addEventListener(type, (e) => !e.target.closest?.('.leaflet-container') && e.preventDefault(), { passive: false });
}

loadSession().then(() => {
    createApp(App).use(router).mount('#app');
});

if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1')) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}

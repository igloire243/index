import '../css/app.css';
import { createApp } from 'vue';
import App from './App.vue';
import { router } from './router';
import { loadSession } from './stores/session';

loadSession().then(() => {
    createApp(App).use(router).mount('#app');
});

if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1')) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}

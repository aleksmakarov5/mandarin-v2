import './bootstrap';
import '../sass/app.scss'; // Подключение SCSS стилей

import 'bootstrap-icons/font/bootstrap-icons.css';
import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import router from './router';
import { initRightClickNewTab } from './plugins/contextMenuNewTab';
import App from './App.vue';
import { maskPhoneDirective } from './directives/maskPhone';

const app = createApp(App);
const pinia = createPinia();

axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 403) {
            // Если 403 пришел от роута конкретного дела
            if (error.config?.url?.includes('/api/applications/')) {
                alert('Доступ запрещен: дело относится к другому филиалу.');
                router.push('/requests');
            }
        }
        return Promise.reject(error);
    }
);

app.use(pinia);
app.use(router);
app.directive('mask-phone', maskPhoneDirective);
// Инициализируем глобальное открытие по правому клику
initRightClickNewTab(router);
app.mount('#app');
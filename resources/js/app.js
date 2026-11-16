import './bootstrap';
import '../sass/app.scss'; // Подключение SCSS стилей

import 'bootstrap-icons/font/bootstrap-icons.css';
import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import router from './router';
import App from './App.vue';
import { maskPhoneDirective } from './directives/maskPhone';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);
app.directive('mask-phone', maskPhoneDirective);
app.mount('#app');
import { createApp } from 'vue';
// Vue/PrimeVue берутся из Import Map пакета VueTools (не бандлятся).
// Всё PrimeVue — именованными импортами из единого бандла 'primevue';
// тема (Aura) и PrimeIcons тоже приходят из VueTools (vuetools.css).
import { PrimeVue, Aura, ConfirmationService, ToastService, Tooltip } from 'primevue';
import LogsList from './pages/LogsList.vue';

const app = createApp(LogsList);
app.use(PrimeVue, { theme: { preset: Aura, options: { darkModeSelector: '.mxl-dark' } } });
app.use(ConfirmationService);
app.use(ToastService);
app.directive('tooltip', Tooltip);
app.mount('#mxlogger-app');

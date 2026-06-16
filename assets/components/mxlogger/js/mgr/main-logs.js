import { createApp } from 'vue';
import 'primeicons/primeicons.css';
import PrimeVue from 'primevue/config';
import Aura from '@primevue/themes/aura';
import ConfirmationService from 'primevue/confirmationservice';
import ToastService from 'primevue/toastservice';
import Tooltip from 'primevue/tooltip';
import LogsList from './pages/LogsList.vue';

const app = createApp(LogsList);
app.use(PrimeVue, { theme: { preset: Aura, options: { darkModeSelector: '.mxl-dark' } } });
app.use(ConfirmationService);
app.use(ToastService);
app.directive('tooltip', Tooltip);
app.mount('#mxlogger-app');

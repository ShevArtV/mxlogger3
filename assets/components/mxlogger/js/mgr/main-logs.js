import { createApp } from 'vue';
import 'primeicons/primeicons.css';
import PrimeVue from 'primevue/config';
import Aura from '@primevue/themes/aura';
import { definePreset } from '@primevue/themes';
import ConfirmationService from 'primevue/confirmationservice';
import ToastService from 'primevue/toastservice';
import Tooltip from 'primevue/tooltip';
import LogsList from './pages/LogsList.vue';

// Насыщенный синий primary — кнопки и акценты контрастнее на светлом фоне менеджера.
const MxLoggerPreset = definePreset(Aura, {
    semantic: {
        primary: {
            50: '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe', 300: '#93c5fd',
            400: '#60a5fa', 500: '#2563eb', 600: '#1d4ed8', 700: '#1e40af',
            800: '#1e3a8a', 900: '#172554', 950: '#0f172a',
        },
    },
});

const app = createApp(LogsList);
app.use(PrimeVue, { theme: { preset: MxLoggerPreset, options: { darkModeSelector: '.mxl-dark' } } });
app.use(ConfirmationService);
app.use(ToastService);
app.directive('tooltip', Tooltip);
app.mount('#mxlogger-app');

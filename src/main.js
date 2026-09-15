import {createApp} from 'vue';
import App from '@/App.vue';
import router from '@/router/index';

import './assets/css/styles.scss';

import {Popover} from 'bootstrap';

const app = createApp(App);
app.use(router);
app.mount('#app');

document.querySelectorAll('[data-bs-toggle="popover"]').forEach(popover => {
    new Popover(popover);
});
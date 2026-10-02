import {createApp} from 'vue';
import App from '@/App.vue';
import router from '@/router/index';

import './assets/css/styles.scss';

import {Popover} from 'bootstrap';

// Create the Vue application, install routing, and mount the root component.
const app = createApp(App);
app.use(router);
app.mount('#app');

// Enable Bootstrap popovers on elements that declare the matching data attribute.
document.querySelectorAll('[data-bs-toggle="popover"]').forEach(popover => {
    new Popover(popover);
});
/**
 * Starts the Vue frontend and mounts App in the HTML entry page.
 * Installs routing, loads shared styles and icons, and enables Bootstrap
 * popovers on elements that request them.
 */
import App from "@/App.vue";
import router from "@/router/index";

import "./assets/css/styles.scss";
import "bootstrap-icons/font/bootstrap-icons.css";

import {Popover} from "bootstrap";
import {createApp} from "vue";

// Create the single application shell, install routing, and mount the root
// component so every view shares the same Vue instance.
const app = createApp(App);
app.use(router);
app.mount("#app");

// Popovers are opt-in through a data attribute, keeping Bootstrap behavior
// local to the elements that explicitly request it.
document.querySelectorAll('[data-bs-toggle="popover"]').forEach((popover) => {
    new Popover(popover);
});

import "./bootstrap";
import "https://code.jquery.com/jquery-3.7.1.min.js";
import "./themeassets/js/bootstrap.bundle.min.js";
// import "./themeassets/plugins/bs-stepper/js/bs-stepper.min.js";
import "./themeassets/plugins/bs-stepper/js/main.js";
import "./themeassets/js/app.js";
import "https://cdnjs.cloudflare.com/ajax/libs/izitoast/1.4.0/js/iziToast.min.js";
import "https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js";
import { createApp, markRaw } from "vue";
import { createPinia } from "pinia";
import App from "./components/App.vue";
import router from "./routers.js";
import piniaPluginPersistedstate from "pinia-plugin-persistedstate";
import { useAuthStore } from "./stores/authStore.js";
import VueQrcode from "@chenfengyuan/vue-qrcode";
import VOtpInput from "vue3-otp-input";
import VueTippy from "vue-tippy";
import VueDeviceDetect from "@tenrok/vue-device-detect";
import VWave from "v-wave";
import moment from "moment";
import Scrollbar from "vue3-perfect-scrollbar-z";
import "vue3-perfect-scrollbar-z/dist/styles.css";
// if expose with window means we can access it direct with this name in any vue
import Notification from "./Helpers/Notification.js";
window.Notification = Notification;
import MF from "./Helpers/MyFunction.js";
window.MF = MF;
import ErrorCatch from "./Helpers/ErrorCatch";
window.ErrorCatch = ErrorCatch;
// if expose with window means we can access it direct with this name in any vue

const pinia = createPinia();
const app = createApp(App);
pinia.use(piniaPluginPersistedstate);
pinia.use(({ store }) => {
    store.router = markRaw(router);
});

app.use(pinia);
app.use(VWave);
app.use(Scrollbar);
app.use(router);
app.provide("moment", moment);
app.component(VueQrcode.name, VueQrcode);
app.component("v-otp-input", VOtpInput);
app.use(VueDeviceDetect);
app.use(VueTippy, {
    directive: "tippy", // => v-tippy
    component: "tippy", // => <tippy/>
});

const authStore = useAuthStore();

app.mount("#app");

router.beforeEach(async (to, from) => {
    if (authStore.isDarkMode) {
        if (to.name == "Login") {
            $("html").attr("data-bs-theme", "light");
        } else {
            $("html").attr("data-bs-theme", "dark");
        }
    } else {
        $("html").attr("data-bs-theme", "light");
    }

    if (authStore.hasToken() && to.meta.guest) {
        return { name: "Home" };
    }

    if (!authStore.hasToken() && to.meta.auth) {
        return { name: "Login" };
    }
    if (!authStore.hasToken() && to.meta.RE) {
        return { name: "Login" };
    }
    if (!authStore.hasToken() && to.meta.OTP) {
        return { name: "Login" };
    }

    if (authStore.hasToken() && to.meta.auth) {
        if (authStore.getRequire_2fa == 1) {
            if (authStore.getotp_regisered == 0) {
                return { name: "register2fa" };
            } else if (
                authStore.getotp_regisered == 1 &&
                authStore.getotpChecked == 0
            ) {
                return { name: "otp" };
            } else if (authStore.getotpChecked == 0) {
                return { name: "otp" };
            }

        }
    }

    if (authStore.hasToken() && to.meta.RE) {
        if (!authStore.forcePassChange) {
            if (
                authStore.getRequire_2fa == 1 &&
                authStore.getotp_regisered == 1
            ) {
                return { name: "otp" };
            } else if (
                authStore.getRequire_2fa == 0 &&
                authStore.getotp_regisered == 1
            ) {
                return { name: "Home" };
            } else if (
                authStore.getRequire_2fa == 1 &&
                authStore.getotp_regisered == 1 &&
                authStore.getotpChecked == 1
            ) {
                return { name: "Home" };
            }
        } else {
            return { name: "ForcePassChange" };
        }
    }

    if (authStore.hasToken() && to.meta.OTP) {
        if (!authStore.forcePassChange) {
            if (
                authStore.getRequire_2fa == 1 &&
                authStore.getotp_regisered == 0
            ) {
                return { name: "register2fa" };
            } else if (
                authStore.getRequire_2fa == 0 &&
                authStore.getotp_regisered == 1
            ) {
                return { name: "Home" };
            } else if (
                authStore.getRequire_2fa == 1 &&
                authStore.getotp_regisered == 1 &&
                authStore.getotpChecked == 1
            ) {
                return { name: "Home" };
            }
        } else {
            return { name: "ForcePassChange" };
        }
    }
});

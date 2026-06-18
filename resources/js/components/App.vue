<script setup>
import Topbar from './layout/Topbar.vue'
import Sidebar from './layout/Sidebar.vue'
import Footer from './layout/Footer.vue'
import axiosInstance from "../axiosInstance"

import { useAuthStore } from '../stores/authStore';
import { onMounted, watchEffect, watch, computed, ref, onBeforeMount } from 'vue';
const authStore = useAuthStore();
import { useRouter } from 'vue-router';
const router = useRouter();


import { useOnline } from '@vueuse/core'
const online = useOnline()


watchEffect(async () => {
    if (authStore.showExpireWarrning) {
        iziToast.question({
            timeout: 10000,
            pauseOnHover: false,
            close: false,
            overlay: true,
            displayMode: 'once',
            id: 'question',
            zindex: 999,
            message: 'You are Going to Logout..',
            position: 'center',
            buttons: [
                ['<button><b>Refresh</b></button>', function (instance, toast) {

                    instance.hide({ transitionOut: 'fadeOut' }, toast, 'btn1');

                }, true]
            ],
            onClosed: async function (instance, toast, closedBy) {

                if (closedBy == 'btn1') {
                    if (authStore.hasToken()) {
                        let res = await refresh_token();
                        authStore.token = authStore.encryptWithAES(
                            res.data.data.access_token
                        );
                        authStore.refreshToken(res.data.data.expires_in_sec);
                    } else {
                        authStore.logout();
                        router.push({ name: 'Login' })
                    }
                } else {
                    authStore.logout();
                    router.push({ name: 'Login' })
                }

            }
        });
    }

    // let cLoc = window.location.pathname;
    // console.log('weff',cLoc);

})


onBeforeMount(async () => {

    if (authStore.hasToken() && authStore.isLogged) {
        authStore.after30sRun = 0;
        if (authStore.after30sRun == 0) {
            const authStore = useAuthStore();
            const accessToken = authStore.decryptWithAES(authStore.token);
            const sToken = accessToken.split(".")[1];
            const ReminTime = Math.round((JSON.parse(window.atob(sToken)).exp * 1000 - Date.now()) / 1000);
            authStore.refreshToken(ReminTime, false)
        }

    } else {
        // console.log("No token found, redirecting to login");

        authStore.logout();
        return { name: 'Login' };
    }

});

function refresh_token() {
    const authStore = useAuthStore();
    const accessToken = authStore.decryptWithAES(authStore.token);
    const config = {
        headers: {
            Authorization: "Bearer " + accessToken,
            Accept: "application/json",
        },
    };
    return axios.get("/api/refresh", config);
}

const text = computed(() => online.value ? 'Online' : 'Offline')

</script>
<template>

    <div class="p-0 m-0" v-if="text == 'Offline'">
        <p class="text-center w-100 bg-danger p-0 m-0">Offline..</p>
    </div>

    <div v-if="$route.path === '/' || $route.path === '/Reg2Fa' || $route.path === '/registration' || $route.path === '/otp' || $route.path === '/sendResetLinkEmail' || $route.path.substring(0, 10) === '/PassReset' || $route.path === '/ForcePassChange' ? false : true"
        class="wrapper" id="app">
        <Sidebar />
        <Topbar />

        <div class="page-wrapper">
            <div class="page-content">
                <router-view></router-view>
            </div>
        </div>
        <div class="overlay toggle-icon"></div>
        <a href="javaScript:;" class="back-to-top"><i class='bx bxs-up-arrow-alt'></i></a>
        <!-- <Footer /> -->
    </div>
    <div v-else class="login-pages">
        <router-view></router-view>
    </div>

</template>

<style>
@font-face {
    font-family: "Inter";
    src: url('../../fonts/BeVietnamPro/BeVietnamPro-Regular.ttf');
}

#app {
    font-family: 'Inter';
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    font-size: 13px;
}



.otp-input {
    width: 50px;
    height: 50px;
    padding: 2px;
    margin: 2px;
    font-size: 24px;
    border-radius: 4px;
    border: 2px solid rgba(8, 100, 239, 0.3);
    text-align: center;
}

/* Background colour of an input field with value */
.otp-input.is-complete {
    background-color: #dadfec;
    color: #027DE2;
}

.otp-input::-webkit-inner-spin-button,
.otp-input::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}


.sd input:focus {
    outline: none !important;
    border: 2px solid #027DE2;
}

.sd input::placeholder {
    font-size: 15px;
    text-align: center;
    font-weight: 600;
}

.tippy-box[data-theme~='tomato'] {
    background-color: tomato;
    color: yellow;
}

.tippy-box {
    background-color: #013DC4;
    font-size: 10px;
}

.tippy-box[data-placement^='top']>.tippy-arrow::before {
    border-top-color: #013DC4;
}

.tippy-box[data-placement^='bottom']>.tippy-arrow::before {
    border-bottom-color: #013DC4;
}

.ps__thumb-y {
    background-image: linear-gradient(blue, #00ffff, blue);
    opacity: 0.3;
}


.bg-checkbox-active {
    color: #fff;
    /* background: #1882ff; */
    background: #875ae9;
}

.form-check-input:checked {
    background-color: #875ae9;
    border-color: #ffffff;
}

.form-check-input:checked {
    background-color: #875ae9;
    border-color: #ffffff;
}

.bluesky-border-primary{
    border-color: #9368ee !important;
}
.custom-text-purple{
    color: #7944eb;
}

.bluesky-btn-primary {
    color: #fff;
    background-color: #7944eb;
    border-color: #7944eb;
    padding: 8px 18px 8px 18px;
    border-radius: 8px 8px 8px 8px;
}
.bluesky-btn-primary:hover {
    color: #fff;
    background-color: #875ae9;
    border-color: #875ae9;
    padding: 8px 18px 8px 18px;
    border-radius: 8px 8px 8px 8px;
}


.bluesky-btn-outline-primary {
    color: #875ae9;
    border-color: #875ae9;
}
.bluesky-btn-outline-primary:hover {
    color: #fff;
    background-color: #875ae9;
    border-color: #875ae9;
}

.bluesky-route-btn-primary {
    color: #fff;
    background-color: #7944eb;
    border-color: #7944eb;
    padding: 1px 1px 1px 1px;
    font-size: 12px;
}
.bluesky-route-btn-primary:hover {
    color: #fff;
    background-color: #875ae9;
    border-color: #875ae9;
}

.btn-outline-book{
    color: #25b2a7;
    border-color: #25b2a7;
}
.btn-outline-book:hover {
    color: #fff;
    background-color: #25b2a7;
    border-color: #25b2a7;
}
.bluesky-route-btn-outline-primary{
    color: #875ae9;
    border-color: #875ae9;
    padding: 1px 1px 1px 1px;
    font-size: 12px;
}

.bluesky-route-btn-outline-primary:hover {
    color: #fff;
    background-color: #875ae9;
    border-color: #875ae9;
}

.bluesky-departure-text{
    color: #7239EA;
}

.bluesky-departure-airport-text{
    background: linear-gradient(238.29deg, #1B98E6 6.66%, #8462FF 85.47%);
    color: transparent;
    background-clip: text;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}


</style>

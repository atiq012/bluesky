<script setup>
import axios from "axios";
import { reactive, ref, watch, onMounted } from "vue";
import { useRouter } from "vue-router";
const router = useRouter();
import { useAuthStore } from "../../stores/authStore";
import { useFocus } from '@vueuse/core'

import { deviceType, osName, osVersion } from '@tenrok/vue-device-detect'

const authStore = useAuthStore();

const PassType = ref(false);
const ClearButton = ref(false);
const PassImagePath = ref(getImageUrl('HidePassword.svg'));
const ClearButtonImagePath = ref(getImageUrl('Cross.svg'));

const form = reactive({ email: "", password: "" });

const loading = ref(false);
const ButtonName = ref("");
ButtonName.value = "login";


function handleSubmit() {

    if (!authStore.email) {
        Notification.showToast("e", "Please enter your email address.");
        return;
    } else if (!form.password || form.password.length < 4) {
        Notification.showToast("e", "Please enter your valid password.");
        return;
    }

    loading.value = true;
    ButtonName.value = "Loading..";
    axios.post("/api/login", { email: authStore.email, password: form.password, IPinfo: authStore.sInfo })
        .then((res) => {
            loading.value = false;


            ButtonName.value = "Login";
            authStore.token = authStore.encryptWithAES(res.data.data.access_token);
            authStore.email = res.data.data.email;
            authStore.name = res.data.data.name;
            authStore.ExpireInSec = res.data.data.expires_in_sec;


            authStore.getRequire_2fa = res.data.data.require_2fa;
            authStore.getotp_regisered = res.data.data.registered_2fa;
            authStore.getgoogle2fa_secret = res.data.data.google2fa_secret;
            authStore.getgoogle2fa_qr = res.data.data.google2fa_qr;

            if (res.data.data.registered_2fa == 1) {
                authStore.getgoogle2fa_secret = '';
                authStore.getgoogle2fa_qr = '';
            }

            if (res.data.data.require_2fa == 0) {
                authStore.getotp_regisered = 1;
                authStore.getotpChecked = 1;
                authStore.isLogged = authStore.encryptWithAES('1');
            }

            if (res.data.message == 'Your password must be change.') {
                authStore.forcePassChange = authStore.encryptWithAES('1');
                Notification.showToast("i", res.data.message);
            }

            authStore.runTaskWithTimer(res.data.data.expires_in_sec);

            router.push({ name: "register2fa" });
            // router.push({ name: "Home" });
        })
        .catch((eEes) => {
            loading.value = false;
            ButtonName.value = "Login";

            if (eEes.response.status == "404" || eEes.response.status == "422") {
                const aCont = eEes.response.data.data.RA;
                const aPE = eEes.response.data.data.PE;
                if (aCont) {
                    Notification.showToast("e", eEes.response.data.message + ' Attamps ' + aCont + ' of ' + 3);
                    authStore.loginAttapms = eEes.response.data.data.RA;
                    return;
                } else if (aPE) {
                    Notification.showToast("e", eEes.response.data.message);
                    router.push({ name: "ForcePassChange" });
                } else {
                    Notification.showToast("e", eEes.response.data.message);
                    return;
                }

            }
            Notification.showToast("e", eEes.response.data.data.error);
        });
};

function ShowPass() {

    if (PassType.value == true) {
        PassType.value = false
        PassImagePath.value = getImageUrl('HidePassword.svg')
    } else {
        PassType.value = true
        PassImagePath.value = getImageUrl('Viewpass.svg')
    }
}

function ClearPassword() {
    form.password = '';
}

function getImageUrl(name) {
    return new URL(`../../../../public/theme/appimages/${name}`, import.meta.url).href
}

watch(form, (newValue, oldValue) => {
    if (newValue.password.length > 0) {
        ClearButton.value = true
    } else {
        ClearButton.value = false
    }
    // authStore.email=newValue.email;

});

function getIPinfo() {
    fetch('https://geolocation-db.com/json/')
        .then((resp) => resp.text())
        .then(function (data) {
            const xVal = JSON.parse(data);
            xVal['devicetype'] = MF.initCap(deviceType());
            xVal['os'] = osName() + ' ' + osVersion();
            authStore.sInfo = xVal;
        });
}

onMounted(() => {
    getIPinfo()
});

</script>
<template>
    <div class="container-fluid vh-100 d-flex flex-column">
        <div class="row vh-100 content">
            <div class="d-none d-lg-flex col-lg-6 flex-column p-0 position-relative">

                <video style="z-index: 1" class="vid" src="../../../../public/theme/appimages/la.mp4" autoplay loop
                    muted />
                <img style="z-index: 2" class="mixbl shadow-lg" src="../../../../public/theme/appimages/sh3.jpg"
                    alt="" />
                <img class="mixbl2" style="z-index: 3" src="../../../../public/theme/appimages/Subtract.svg" alt="" />

                <img src="../../../../public/theme/appimages/planedowa1.png"
                    style="height: 190px; margin-top: 330px; margin-left: -425px; z-index: 2; "
                    class="img-fluid position-absolute" alt="lesstmap" />
                <img src="../../../../public/theme/appimages/map.svg"
                    style="height: 180px; margin-top: 410px; margin-left: 100px; z-index: 5"
                    class="img-fluid position-absolute" alt="leftmap" />

                <img src="../../../../public/theme/appimages/bil.svg" style="z-index: 6" class="img-fluid imgBottom"
                    alt="leftcityBottom" />

                <div style="z-index: 7;" class="position-absolute">
                    <div class="h-100 d-flex flex-column p-4 position-relative">
                        <div class="mt-2 leftfirstpara">
                            Unlocking The Future Of Travel Management <br />
                            With <span class="leftfirstparaspan">BlueSky NDC Platform</span>
                        </div>
                        <div class="mt-3 leftsecpara">
                            The perfect way to manage your B2B Agents. Automate the Sales process,<br>
                            Reduce response time and Increase growth to your business
                        </div>
                        <div class="mt-4">
                            <div class="d-flex justify-content-center leftboletpoint">
                                <div class="w-100">
                                    <i class="fas fa-star fastar"></i> Dynamic Pricing
                                </div>
                                <div class="w-100">
                                    <i class="fas fa-star fastar"></i> Inventory Management
                                </div>
                                <div class="w-100">
                                    <i class="fas fa-star fastar"></i> Inventory Management
                                </div>
                            </div>
                            <div class="d-flex justify-content-center leftboletpoint">
                                <div class="w-100"><i class="fas fa-star fastar"></i> Real-time Feed</div>
                                <div class="w-100">
                                    <i class="fas fa-star fastar"></i> Integrated Analytics
                                </div>
                                <div class="w-100"><i class="fas fa-star fastar"></i> CRM</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <div class="col-lg-6 col-md-12 col-sm-12 p-0 position-relative">
                <img src="../../../../public/theme/appimages/bubble.svg" class="buble" alt="rlogo">
                <div class="h-100 d-flex flex-column">

                    <div class="d-none d-lg-flex d-xl-flex justify-content-start mt-3">
                        <div class="p-2 align-content-center">
                            <img src="../../../../public/theme/appimages/bird.gif" width="35" height="35" alt="rbird">
                        </div>
                        <div class="p-1 align-content-center" style="margin-left: -7px;">
                            <img src="../../../../public/theme/appimages/blueskymainlogo.svg" height="35" alt="rlogo">
                        </div>

                    </div>
                    <div class="d-flex justify-content-center mt-5">
                        <!-- Show Only in LG and XL -->
                        <div class="d-none d-lg-block d-xl-block">
                            <p class="well p-0 m-0">Nice To See You !</p>
                            <p class="wellbellow p-0 m-0">Bluesky NDC Travel Platform</p>
                        </div>
                        <!-- show only in mobile view -->
                        <div class="d-lg-none d-xl-none d-sm-block mt-5">
                            <p class="well p-0 m-0">Nice To See You !</p>
                            <div class="d-flex justify-content-center justify-content-lg-start">
                                <div class="p-2">
                                    <img src="../../../../public/theme/appimages/bird.png" width="40" height="30"
                                        alt="rbird">
                                </div>
                                <div class="p-1 align-content-center" style="margin-left: -7px;">
                                    <img src="../../../../public/theme/appimages/blueskymainlogo.svg" height="35"
                                        alt="rlogo">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="container-fluid mx-auto">

                        <form @submit.prevent="handleSubmit">
                            <div class="row justify-content-center">

                                <div class="col-lg-6 col-md-6 col-sm-10">

                                    <div class="mt-3">



                                        <label for="exampleFormControlInput1" class="form-label">Email or Phone</label>
                                        <div class="position-relative">
                                            <img class="position-absolute p-2"
                                                src="../../../../public/theme/appimages/Admin.svg" height="40"
                                                width="40" alt="leftmap">
                                            <input v-model="authStore.email" class="form-control form-control-lg"
                                                type="email" required placeholder="Enter your email or phone">
                                        </div>


                                        <label for="exampleFormControlInput1" class="form-label mt-3">Password</label>
                                        <div class="position-relative">
                                            <img class="position-absolute p-2"
                                                src="../../../../public/theme/appimages/Password.svg" height="40"
                                                width="40" alt="leftmap">
                                            <input class="form-control form-control-lg pl-5" v-model="form.password"
                                                placeholder="Enter Your Password" name="password" id="password"
                                                :type="PassType ? 'text' : 'password'">
                                            <img @click="ShowPass" class="position-absolute p-2" :src="PassImagePath"
                                                height="40" width="40" id="eye" alt="leftmap"
                                                style="cursor: pointer; top: 2px; right: 0px;">
                                            <img v-show="ClearButton" @click="ClearPassword"
                                                class="position-absolute p-2" :src="ClearButtonImagePath" height="40"
                                                width="40" id="eye" alt="leftmap"
                                                style="cursor: pointer; top: 2px; right: 0px; margin-right: 30px;">
                                        </div>

                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <div class="custom-control custom-checkbox">

                                        </div>
                                        <router-link class="forgetpassword fw-bold"
                                            :to="{ name: 'sendResetLinkEmail' }">Forgot Password?</router-link>
                                    </div>

                                    <div class="mt-3 mb-1 w-100">
                                        <button :disabled="loading" type="submit" class="btn btn-primary w-100 rounded-2 position-relative">
                                            <div v-if="loading" class="center-body position-absolute" style="margin-top: -40px;">
                                                <div class="loader-circle-57">
                                                    <img class="position-absolute"
                                                        src="../../../../public/theme/appimages/blueskywings.png"
                                                        height="16" width="16" alt="">
                                                </div>
                                            </div>

                                            <span role="status">{{ ButtonName }}</span>
                                        </button>
                                    </div>

                                    <p class="clientDetails">IP : <b>{{ authStore.sInfo.IPv4 }} </b> City : <b>{{
                                        authStore.sInfo.city }}</b> Contry : <b>{{ authStore.sInfo.country_code
                                            }}</b> Device : <b>{{
                                                authStore.sInfo.devicetype }} </b> OS : <b>{{ authStore.sInfo.os }}</b>
                                    </p>

                                    <div class="mt-5 text-center w-100">
                                        <p class="it">Information Technology | Galaxy Bangladesh</p>
                                    </div>

                                </div>

                            </div>
                        </form>

                    </div>

                </div>
                <img src="../../../../public/theme/appimages/BottomrImage.svg" class="w-100 img-fluid imgBottom"
                    alt="leftcityBottom" />
            </div>
        </div>
    </div>
</template>
<style scoped>
@font-face {
    font-family: "Inter";
    src: url('../../fonts/BeVietnamPro/BeVietnamPro-Regular.ttf');
}




.center-body {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100%;
    height: 100vh;
    width: 100px;
    height: 100px;
}

.loader-circle-57 {
    width: 40px;
    height: 40px;
    display: flex;
    justify-content: center;
    align-items: center;
}

.loader-circle-57:before {
    content: "";
    color: red;
    height: 30px;
    width: 30px;
    background: #0000;
    border-radius: 50%;
    border: 5px solid #027de2d5;
    animation: loader-circle-57-spin 1s infinite
}

@keyframes loader-circle-57-spin {
    50% {
        transform: rotatez(180deg);
        border-style: dashed;
        border-color: #ffffff #e23708 #ffffff;
    }

    100% {
        transform: rotatez(360deg);
    }
}

.clientDetails {
    font-family: Inter;
    font-size: 8px;
    text-align: center;
    color: #5e6878;
}

.form-control-lg {
    height: 43px !important;
    font-size: 13px;
    padding-left: 40px;
}

::placeholder {
    font-family: Inter;
    font-size: 14px;
    font-weight: 500;
    line-height: 26px;
    letter-spacing: 0.5px;
}

::-ms-input-placeholder {
    font-family: Inter;
    font-size: 14px;
    font-weight: 500;
    line-height: 26px;
    letter-spacing: 0.5px;
}

.buble {
    position: absolute;
    right: 0px;
    top: 0px;
    width: 8%;
}


.imgBottom {
    position: absolute;
    left: 0px;
    bottom: 0px;
    width: 100%;
}

.vid {
    height: 100vh;
    width: 100%;
    object-fit: cover;
}

.mixbl {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    mix-blend-mode: screen;
}

.mixbl2 {
    position: absolute;
    top: 0;
    left: 0;
    width: 99%;
    height: 100%;
    object-fit: cover;
    opacity: .9;
}

.leftfirstpara {
    font-family: Inter;
    font-size: 26px;
    font-weight: 700;
    line-height: 40px;
    letter-spacing: 0.5px;
    text-align: left;
    color: white;
}

.leftfirstparaspan {
    font-family: Inter;
    font-size: 26px;
    font-weight: 700;
    line-height: 40px;
    letter-spacing: 0.5px;
    text-align: left;
    color: #fdcbbb;
}

.fastar {
    font-size: 10px;
    margin-bottom: 2px;
    color: white;
}

.leftsecpara {
    font-family: Inter;
    font-size: 14px;
    font-weight: 500;
    line-height: 26px;
    letter-spacing: 0.5px;
    text-align: left;
    color: white;
}

.leftboletpoint {
    font-family: Inter;
    font-size: 13px;
    font-weight: 700;
    line-height: 26px;
    letter-spacing: 0.5px;
    text-align: left;
    color: white;
}

.well {
    font-family: Inter;
    font-size: 23px;
    font-weight: 600;
    line-height: 27.84px;
    letter-spacing: 0.5px;
    justify-content: center;
    color: #3b79f2;
    text-align: center;
}

.wellbellow {
    font-family: Inter;
    font-size: 14px;
    font-weight: 500;
    line-height: 26px;
    letter-spacing: 0.5px;
    text-align: center;
    color: #5e6878;
}

.forgetpassword {
    font-family: Inter;
    font-size: 12px;
    font-weight: 600;
    line-height: 14.52px;
    letter-spacing: 0.5px;
}

.rememberme {
    font-family: Inter;
    font-size: 12px;
    color: #5e6878;
}

.form-label {
    font-family: Inter;
    font-size: 14px;
    font-weight: 600;
    line-height: 16.94px;
    letter-spacing: 0.5px;
    color: #3f4754;
}


.it {
    font-family: Inter;
    font-size: 12px;
    font-weight: 400;
    line-height: 14.52px;
    color: #5e6878;
}
</style>

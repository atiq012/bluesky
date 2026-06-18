<script setup>
import { computed } from "vue";
import { storeToRefs } from "pinia";
import { useRouter } from "vue-router";
import { useAuthStore } from "../../stores/authStore";

const router = useRouter();
const authStore = useAuthStore();
const { getgoogle2fa_secret, getgoogle2fa_qr, email } = storeToRefs(authStore);

const qrValue = computed(() => {
    const stored = (getgoogle2fa_qr.value || "").trim();
    if (stored.startsWith("otpauth://")) return stored;

    const secret = (getgoogle2fa_secret.value || "").trim();
    const holder = (email.value || "").trim();
    if (!secret || !holder) return "";

    const issuer = "BlueSky";
    return `otpauth://totp/${encodeURIComponent(issuer)}:${encodeURIComponent(holder)}?secret=${secret}&issuer=${encodeURIComponent(issuer)}&algorithm=SHA1&digits=6&period=30`;
});

function BackLogin() {
    authStore.logout();
    router.push({ name: "Login" });
}

function goOTP() {
    authStore.getotp_regisered = 1;
    router.push({ name: "otp" });
}

</script>
<template>
    <div class="container-fluid vh-100 position-relative p-0 m-0">

        <!-- Top logo and bubble -->
        <div class="d-flex justify-content-between">
            <div class="d-none d-lg-flex d-xl-flex justify-content-start mt-3 ml-4">
                <div class="p-2 align-content-center">
                    <img src="../../../../public/theme/appimages/bird.gif" width="35" height="35" alt="rbird">
                </div>
                <div class="p-1 align-content-center">
                    <img src="../../../../public/theme/appimages/blueskymainlogo.svg" height="35" alt="rlogo">
                </div>
            </div>

            <img src="../../../../public/theme/appimages/bubble.svg" class="buble" alt="rlogo">
        </div>

        <p class="p1">Google Authentication Registration</p>
        <p class="p2">Scan QR or enter below Red code to your phone authenticator, for receive a Authentication code</p>
        <p class="p3">in Google authenticator app. After get the code click Enable 2FA</p>
        <p class="p4">{{ authStore.getgoogle2fa_secret }}</p>

        <!-- QR section -->
        <div class="d-flex justify-content-center">
            <div class="qr-box">
                <vue-qrcode
                    v-if="qrValue"
                    :value="qrValue"
                    tag="svg"
                    :options="{ errorCorrectionLevel: 'H', width: 180 }"
                />
                <img src="../../../../public/theme/appimages/rqf.png" class="qr-frame" alt="qrfream">
            </div>
        </div>
        <!--End QR section -->

        <div class="row mt-5">
            <div class="col-md-4">
            </div>
            <div class="col-md-4">
                <div class="d-flex justify-content-between">
                    <button @click="goOTP" class="btn btn-primary rounded-2">
                        Enable 2FA
                    </button>
                    <button @click="BackLogin" class="btn btn-secondary rounded-2">
                        Back To Login
                    </button>
                </div>
            </div>
            <div class="col-md-4">
            </div>
        </div>

        <p class="it">Information Technology | Galaxy Bangladesh</p>

        <!-- below city -->
        <img src="../../../../public/theme/appimages/bottomfullimage.svg" class="img-fluid imgBottom"
            alt="leftcityBottom" />
    </div>
</template>

<style scoped>
@font-face {
    font-family: "Inter";
    src: url('../../fonts/BeVietnamPro/BeVietnamPro-Regular.ttf');
}

.qr-box {
    position: relative;
    width: 180px;
    height: 180px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.qr-box :deep(svg) {
    width: 180px !important;
    height: 180px !important;
    display: block;
}

.qr-frame {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 2;
}

.it {
    font-family: Inter;
    font-size: 12px;
    font-weight: 400;
    line-height: 14.52px;
    color: #5e6878;
    text-align: center;
    margin-top: 100px;
}

.p1 {
    font-family: Inter;
    font-size: 24px;
    font-weight: 900;
    line-height: 26px;
    letter-spacing: 0.5px;
    margin-top: 5px;
    text-align: center;
    color: #077cdb;
}

.p2 {
    font-family: Inter;
    font-size: 14px;
    letter-spacing: 0.5px;
    text-align: center;
    color: #5e6878;
}

.p3 {
    font-family: Inter;
    font-size: 14px;
    letter-spacing: 0.5px;
    text-align: center;
    color: #5e6878;
    margin-top: -15px;
}

.p4 {
    font-family: Inter;
    font-size: 14px;
    font-weight: 900;
    letter-spacing: 0.5px;
    text-align: center;
    color: #f32525;
    margin-top: 5px;
}


.imgBottom {
    position: absolute;
    bottom: 0px;
    width: auto;
}

.buble {
    position: absolute;
    right: 0px;
    top: 0px;
    width: 5%;
}
</style>

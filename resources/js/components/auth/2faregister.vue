<script setup>
import { computed } from "vue";
import { storeToRefs } from "pinia";
import { useRouter } from "vue-router";
import { useAuthStore } from "../../stores/authStore";
import { performLogout } from "../../Helpers/logout";

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
    void performLogout();
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

        <div class="container mt-4">
            <div class="row justify-content-center">
                <!-- title sits outside the narrow column so it can stay on one line -->
                <div class="col-12">
                    <p class="p1">Google Authentication Registration</p>
                </div>
            </div>

            <div class="row justify-content-center">
                <div class="col-11 col-sm-8 col-md-6 col-lg-4">

                    <p class="p2">
                        Scan QR or enter the Setup Key to your phone authenticator
                        for receive a Authentication code
                    </p>

                    <!-- setup key -->
                    <p class="setup-key">{{ getgoogle2fa_secret }}</p>

                    <!-- QR section -->
                    <div class="d-flex justify-content-center">
                        <div class="qr-box">
                            <vue-qrcode
                                v-if="qrValue"
                                :value="qrValue"
                                tag="svg"
                                :options="{ errorCorrectionLevel: 'H', width: 220, margin: 0, color: { dark: '#000000', light: '#ffffff' } }"
                            />
                        </div>
                    </div>
                    <!--End QR section -->

                    <button @click="goOTP" class="btn btn-primary w-100 mt-4">
                        Enable 2FA
                    </button>

                    <div class="text-center mt-3">
                        <p class="p3" @click="BackLogin"><i class="fas fa-angle-left"></i> Back to Login</p>
                    </div>

                    <p class="it">Information Technology | Galaxy Bangladesh</p>

                </div>
            </div>
        </div>

        <!-- below city -->
        <footer class="footer fixed-bottom">
            <img src="../../../../public/theme/appimages/bottomfullimage.svg" class="img-fluid w-100"
                alt="leftcityBottom" />
        </footer>
    </div>
</template>

<style scoped>
@font-face {
    font-family: "Inter";
    src: url('../../fonts/BeVietnamPro/BeVietnamPro-Regular.ttf');
}

/* white plate keeps the QR readable even if page ever renders on a dark surface */
.qr-box {
    background-color: #ffffff;
    border: 2px solid #027DE2;
    border-radius: 12px;
    padding: 16px;
    width: 252px;
    max-width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.qr-box :deep(svg) {
    width: 220px !important;
    height: 220px !important;
    max-width: 100%;
    display: block;
}

.btn-primary {
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 16px;
    background-color: #027DE2;
    border-color: #027DE2;
}

.it {
    font-family: Inter;
    font-size: 12px;
    font-weight: 400;
    line-height: 14.52px;
    color: #5e6878;
    text-align: center;
    margin-top: 40px;
}

/* one line always — font shrinks on narrow screens instead of wrapping */
.p1 {
    font-family: Inter;
    font-size: clamp(14px, 4.2vw, 24px);
    font-weight: 900;
    line-height: 32px;
    letter-spacing: 0.5px;
    margin-top: 20px;
    text-align: center;
    color: #077cdb;
    white-space: nowrap;
}

.p2 {
    font-family: Inter;
    font-size: 14px;
    line-height: 22px;
    letter-spacing: 0.5px;
    text-align: center;
    color: #5e6878;
    margin-bottom: 12px;
}

/* setup key sits between the copy and the QR, styled as the one thing to read */
.setup-key {
    font-family: Inter;
    font-size: 18px;
    font-weight: 800;
    letter-spacing: 1px;
    text-align: center;
    color: #00AEEF;
    margin-bottom: 16px;
    word-break: break-all;
}

.p3 {
    font-family: Inter;
    font-size: 13px;
    letter-spacing: 0.5px;
    text-align: center;
    color: #5E6878;
    cursor: pointer;
    margin-bottom: 0;
}

.buble {
    position: absolute;
    right: 0px;
    top: 0px;
    width: 5%;
}
</style>

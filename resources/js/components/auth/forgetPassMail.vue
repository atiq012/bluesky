<script setup>
import { reactive, ref, watch } from "vue";
import VOtpInput from "vue3-otp-input";
import { useAuthStore } from "../../stores/authStore";
const authStore = useAuthStore();

import { useRouter } from 'vue-router';
const router = useRouter();


const bindValue = ref("");
const otpValue = ref();

const loading = ref(false);
const ButtonName = ref("");
ButtonName.value = "Send";

function sendMail() {

    if (!authStore.email) {
        Notification.showToast("e", "Please enter your email address.");
        return;
    }

    loading.value = true;
    ButtonName.value = "Sending..";

    axios.post('/api/sendResetLinkEmail', { email: authStore.email }
    ).then(
        res => {
            loading.value = false;
            ButtonName.value = "Send";
            console.log(res.data);
            Notification.showToast("s", res.data.message);
        }
    )
        .catch((eEes) => {
            loading.value = false;
            ButtonName.value = "Send";
            console.log(eEes);
            Notification.showToast("e", eEes.response.data.message);
        });


}
</script>
<template>
    <div class="container-fluid vh-100 p-0 m-0 body">

        <header class="site-header">
            <a href="#" class="header-logo">
                <img src="../../../../public/theme/appimages/BS-Logo-B2C-transparent.gif" alt="BlueSky Logo"
                    class="logo-svg" />
            </a>
        </header>

        <div class="split-wrap">

            <!-- LEFT PANEL -->
            <div class="left-panel">
                <img src="../../../../public/theme/appimages/Bg-Image.png" alt="Left Panel" class="left-bg-img" />
            </div>

            <!-- RIGHT PANEL -->
            <div class="right-panel ">
                <div class="login-wrapper">
                    <div class="login-border-wrap">
                        <div class="login-card">

                            <!-- ── Logo block ───────────────────────────── -->
                            <div class="logo-wrap">
                                <img src="../../../../public/theme/appimages/BS-Logo.png" alt="BlueSky Icon"
                                    class="card-logo-icon" />
                            </div>

                            <p class="brand-tagline">BLUESKY NDC TRAVEL LTD</p>
                            <h2 class="welcome-heading">Welcome to BlueSky</h2>
                            <p class="subtitle">Easy and hassle-free flight booking portal</p>
                            <p class="notifier">Please enter your email address and we'll send you a link to reset your
                                password.</p>

                            <!-- Email -->
                            <div class="mb-3">
                                <input v-model="authStore.email" required type="email" id="email"
                                    class="form-control card-input" placeholder="Email" autocomplete="username" />
                            </div>

                            <!-- Send Reset Link -->
                            <!-- <button class="btn-login" type="button">Send Reset Link</button> -->
                             <button :disabled="loading" @click="sendMail" class="btn-login rounded-2 w-100">
                                <div v-if="loading" class="center-body position-absolute" style="margin-top: -10px; margin-left: 50px;">
                                        <div class="loader-circle-57">
                                            <img class="position-absolute"
                                                src="../../../../public/theme/appimages/blueskywings.png" height="16"
                                                width="16" alt="">
                                        </div>
                                    </div>

                                <span role="status">{{ ButtonName }}</span>
                            </button>

                            <!-- Register -->
                            <p class="register-line">Already have an account?
                                <router-link class="p3" :to="{ name: 'Login' }"> Back
                                    to Login</router-link>
                            </p>

                        </div>
                    </div>
                </div>
            </div>

        </div>


    </div>
</template>
<script>

</script>

<style scoped>
.hide {
    color: #08224f;
}

.rmbr {
    font-size: 12px !important;
    color: #1f2b99 !important;
    cursor: pointer;
}

.frgt {
    font-size: 12px !important;
    color: #1f2b99 !important;
    cursor: pointer;
    font-weight: 400 !important;
}

:root {
    --brand-deep: #1a1e6e;
    --brand-mid: #1f2b99;
    --brand-bright: #3b5bdb;
    --brand-accent: #4db8ff;
    --soft-bg: #eef2ff;
}

*,
*::before,
*::after {
    box-sizing: border-box;
}

.body {
    margin: 0;
    min-height: 90vh;
    font-family: 'Euclid Circular B', sans-serif;
    background: var(--soft-bg);
    overflow-x: hidden;
    background-image: url('../../../../public/theme/appimages/Bg_img.png');
    background-color: rgba(255, 255, 255, 0.9);
    background-size: cover;
    background-position: center;
    width: 100%;
}

/* ── Header ─────────────────────────────────────────── */
.site-header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 75px;
    background: rgba(255, 255, 255, 0.88);
    backdrop-filter: blur(14px);
    border-bottom: 1px solid rgba(59, 91, 219, .11);
    display: flex;
    align-items: center;
    padding: 0 2rem;
    z-index: 100;
    box-shadow: 0 2px 16px rgba(26, 30, 110, .06);
}

.header-logo {
    display: flex;
    align-items: center;
    text-decoration: none;
}

.header-logo .logo-svg {
    width: 200px;
    height: 50px;
}

/* ── Split layout ────────────────────────────────────── */
.split-wrap {
    display: flex;
    height: calc(100vh - 62px);
    margin-top: 62px;
}

/* ── Left panel ──────────────────────────────────────── */
.left-panel {
    flex: 1.1;
    overflow: hidden;
    position: relative;
}

.left-bg-img {
    width: 100%;
    object-fit: contain;
    object-position: center;
    display: inline-block;
    overflow: hidden;
    margin: 0;
}

/* ── Right panel ─────────────────────────────────────── */
.right-panel {
    flex: .9;
    display: flex;
    align-items: right;
    justify-content: right;
    position: relative;
    overflow-y: auto;
    padding: 7rem 3rem;
}

.login-wrapper {
    position: relative;
    z-index: 10;
    width: 100%;
    max-width: 500px;
}

/* ── Animated border wrap ────────────────────────────── */
/* .login-border-wrap {
    position: relative;
    border-radius: 26px;
    padding: 2px;
    background: conic-gradient(
      from var(--angle, 0deg),
      #3b5bdb 0%, #4db8ff 20%, #ffffff 35%,
      #9a9fff 50%, #4db8ff 65%, #ffffff 80%, #3b5bdb 100%
    );
    animation: rotateBorder 6s linear infinite;
    box-shadow: 0 0 22px rgba(59,91,219,0.25), 0 0 48px rgba(77,184,255,0.12);
  }

  @property --angle {
    syntax: '<angle>';
    initial-value: 0deg;
    inherits: false;
  }
  @keyframes rotateBorder { to { --angle: 360deg; } } */

/* ── Card ────────────────────────────────────────────── */
.login-card {
    width: 100%;
    background: rgba(255, 255, 255, 0.97);
    backdrop-filter: blur(20px);
    border-radius: 24px;
    padding: 10px 25px;
    animation: slideUp .55s cubic-bezier(.22, .68, 0, 1.2) both;
    box-shadow: 0 4px 6px rgb(197, 200, 255);
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px) scale(.97);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* ── Card logo ───────────────────────────────────────── */
.logo-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
}

.card-logo-icon {
    width: 200px;
    flex-shrink: 0;
    margin-bottom: 6px;
    align-self: center;
}

.card-brand-name {
    font-size: 1.65rem;
    font-weight: 800;
    letter-spacing: 0.07em;
    color: #1a1a2e;
    text-transform: uppercase;
}

/* ── Card headings ───────────────────────────────────── */
.brand-tagline {
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
    color: var(--brand-mid);
    text-align: center;
    margin-bottom: 4px;
}

.welcome-heading {
    font-size: 22px;
    font-weight: 700;
    color: var(--brand-mid);
    text-align: center;
    margin-bottom: 4px;
}

.subtitle {
    font-size: .87rem;
    color: #696e9b;
    text-align: center;
    margin-bottom: 1rem;
}

.notifier {
    font-size: .85rem;
    color: #183b56;
    text-align: center;
    margin-bottom: 1.5rem;
    text-align: left;
}

/* ── Inputs ──────────────────────────────────────────── */
.card-input {
    height: 40px;
    border: 1.5px solid #bcbcbc !important;
    border-radius: 10px !important;
    font-size: .95rem;
    color: #9c9c9c !important;
    background: #ffffff !important;
    transition: border-color .18s, box-shadow .18s;

}

.card-input::placeholder {
    color: #828282;
    padding-left: 7px;
}

.card-input:focus {
    border-color: var(--brand-bright) !important;
    box-shadow: 0 0 0 3px rgba(59, 91, 219, .12) !important;
    outline: none;
    background: #fff !important;
}

/* password wrapper */
.pwd-wrap {
    position: relative;
}

.pwd-wrap .card-input {
    padding-right: 44px;
}

.pwd-toggle {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    padding: 0;
    cursor: pointer;
    color: #9ca3af;
    font-size: 1.05rem;
    line-height: 1;
}

.pwd-toggle:hover {
    color: #6b7280;
}

/* ── Checkbox & forgot ───────────────────────────────── */
.card-check {
    width: 15px;
    height: 15px;
    border: 1.5px solid #9ca3af !important;
    border-radius: 3px !important;
    cursor: pointer;
}

.card-check:checked {
    background-color: var(--brand-mid) !important;
    border-color: var(--brand-mid) !important;
}

.forgot-link:hover {
    color: var(--brand-bright);
}

/* ── Login button ────────────────────────────────────── */
.btn-login {
    width: 100%;
    height: 35px;
    border-radius: 10px;
    background: #1E2B99;
    border: none;
    color: #fff;
    font-family: 'Be Vietnam Pro', sans-serif;
    font-weight: 400;
    font-size: 14px;
    letter-spacing: .02em;
    cursor: pointer;
    transition: background .18s, transform .1s, box-shadow .18s;
    box-shadow: 0 6px 20px rgba(55, 63, 220, 0.28);
    margin-bottom: 1rem;
    display: block;
}

.btn-login:hover {
    background: #1f2b80;
    box-shadow: 0 10px 28px rgba(55, 63, 220, 0.36);
    transform: translateY(-1px);
}

.btn-login:active {
    transform: scale(.98);
}

/* ── Register line ───────────────────────────────────── */
.register-line {
    text-align: center;
    margin-top: .2rem;
    font-size: 13px;
    color: #1f2b99;
    margin-bottom: 0;
}

.register-line a {
    color: #1f2b99;
    font-weight: 700;
    text-decoration: none;
}

.register-line a:hover {
    color: var(--brand-bright);
    text-decoration: underline;
}

/* ── Responsive ──────────────────────────────────────── */
@media (max-width: 900px) {
    .left-panel {
        display: none;
    }

    .right-panel {
        flex: 1;
    }
}

@media (max-width: 40px) {
    .login-card {
        padding: 2rem 1.4rem 1.8rem;
        border-radius: 20px;
    }
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
</style>

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
    <div class="container-fluid vh-100 p-0 m-0">

        <!-- top section -->
        <div class="d-flex justify-content-between">
            <div class="d-flex justify-content-start mt-3 ml-4">
                <div class="p-2 align-content-center">
                    <img src="../../../../public/theme/appimages/bird.gif" width="35" height="35" alt="rbird">
                </div>
                <div class="p-1 align-content-center">
                    <img src="../../../../public/theme/appimages/blueskymainlogo.svg" height="35" alt="rlogo">
                </div>
            </div>

            <img src="../../../../public/theme/appimages/bubble.svg" class="buble" alt="rlogo">
        </div>

        <!-- otp-section -->
        <div class="container mt-1">
            <div class="row justify-content-center">
                <div class="col-md-4">
                    <div class="text-center">
                        <img src="../../../../public/theme/appimages/passwordreset.svg" height="100" width="100" alt="">
                    </div>
                    <p class="p1">Forgot Your Password?</p>
                    <p class="p2">Enter a valid email address, a reset password link will sent
                        to your email</p>

                    <div class="position-relative">
                        <img class="position-absolute p-2" src="../../../../public/theme/appimages/email.svg"
                            height="40" width="40" alt="leftmap">
                        <input v-model="authStore.email" class="form-control form-control-lg pl-5" type="email" required
                            placeholder="Enter your email">
                    </div>

                    <div class="row justify-content-center mt-4">
                        <div class="clearfix w-100 pl-1 pr-1">

                            <button :disabled="loading" @click="sendMail" class="btn btn-primary rounded-2 w-100">
                                <img v-if="loading" src="../../../../public/theme/appimages/pp.gif" height="20"
                                    width="20" alt="" />

                                <span role="status">{{ ButtonName }}</span>
                            </button>

                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <router-link class="p3" :to="{ name: 'Login' }"><i class="fas fa-angle-left"></i> Back to
                            Login</router-link>
                    </div>


                </div>
            </div>
        </div>

        <!-- bottom-city -->
        <footer class="footer fixed-bottom">
            <img src="../../../../public/theme/appimages/bottomfullimage.svg" class="img-fluid w-100"
                alt="leftcityBottom" />
        </footer>


    </div>
</template>
<script>

</script>

<style scoped>
::placeholder {
    font-family: Inter;
    font-size: 14px;
    font-weight: 500;
    line-height: 26px;
    letter-spacing: 0.5px;
}

.form-control-lg {
    height: 43px !important;
    font-size: 13px;
    padding-left: 40px;
}

::-ms-input-placeholder {
    font-family: Inter;
    font-size: 14px;
    font-weight: 500;
    line-height: 26px;
    letter-spacing: 0.5px;
}

.btn-primary {
    color: #fff;
    background-color: #027DE2;
    border-color: #027DE2;
    padding: 8px 18px 8px 18px;
    border-radius: 8px 8px 8px 8px;
}

.btn-primary:hover,
.btn-primary:focus,
.btn-primary:active,
.btn-primary.active,
.open>.dropdown-toggle.btn-primary {
    color: #fff;
    background-color: #0589f5;
    border-color: #0589f5;
    padding: 8px 18px 8px 18px;
    border-radius: 8px 8px 8px 8px;
}

.buble {
    position: absolute;
    right: 0px;
    top: 0px;
    width: 5%;
}

.p1 {
    font-family: Inter;
    font-size: 22px;
    font-weight: 900;
    line-height: 26px;
    letter-spacing: 0.5px;
    margin-top: 35px;
    text-align: center;
    color: #027DE2;

}

.p2 {
    font-family: Inter;
    font-size: 13px;
    letter-spacing: 0.5px;
    text-align: center;
    color: #5E6878;
}

.p3 {
    font-family: Inter;
    font-size: 13px;
    letter-spacing: 0.5px;
    text-align: center;
    color: #5E6878;
    cursor: pointer;
    text-decoration: underline;
    text-underline-offset: 3px;
}
</style>

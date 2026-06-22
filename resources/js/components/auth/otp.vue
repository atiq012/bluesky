<script setup>
import { ref } from "vue";
import VOtpInput from "vue3-otp-input";
import { useAuthStore } from "../../stores/authStore";
const authStore = useAuthStore();

import { useRouter } from 'vue-router';
const router = useRouter();

const bindValue = ref("");
const otpValue = ref();

const loading = ref(false);
const ButtonName = ref("");
ButtonName.value = "Confirm";

function BackLogin() {
    authStore.logout();
    router.push({ name: "Login" });
}


const handleOnComplete = (value) => {
    otpValue.value = value;
    otpCheck(otpValue.value);
};
const handleOnChange = (value) => {
};

function otpCheck(otp) {

    loading.value = true;
    ButtonName.value = "Checking..";

    const config = {
        headers: { Authorization: 'Bearer ' + authStore.decryptWithAES(authStore.token), "Accept": "application/json", }
    };
    axios.post('/api/registerOTP', { otp: otp }, config
    ).then(
        res => {
            loading.value = false;
            ButtonName.value = "Confirm";
            // console.log(res.data);

            if (res.data.status) {
                if (res.data.data.require_2fa == 0) {
                    authStore.getRequire_2fa = res.data.data.require_2fa
                    authStore.getotp_regisered = 1
                    authStore.getotpChecked = 1
                    authStore.getgoogle2fa_secret = ''
                    authStore.getgoogle2fa_qr = ''
                    authStore.isLogged = true;
                    router.push({ name: "Home" });
                    Notification.showToast("s", 'Authorized User Login.');
                } else {

                    authStore.getRequire_2fa = res.data.data.require_2fa
                    authStore.getotp_regisered = res.data.data.registered_2fa
                    authStore.getotpChecked = 1
                    authStore.getgoogle2fa_secret = ''
                    authStore.getgoogle2fa_qr = ''
                    authStore.isLogged = true;
                    router.push({ name: "Home" });
                    Notification.showToast("s", 'Authorized User Login.');
                }

            } else {
                authStore.getotpChecked = 0
            }

        }
    )
        .catch((eEes) => {
            loading.value = false;
            ButtonName.value = "Confirm";
            authStore.getotpChecked = 0;
            if (eEes.response.status == "422") {
                // Validation errors
                Notification.showToast("e", eEes.response.data.message);
                return;
            }
            if (eEes.response.status == "404") {
                Notification.showToast("e", eEes.response.data.message);
                return;
            }
            Notification.showToast("e", eEes.response.data.data.error);
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
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-5">
                    <div class="text-center">
                        <img src="../../../../public/theme/appimages/otptop.svg" alt="">
                    </div>
                    <p class="p1">OTP Confirmation</p>
                    <p class="p2">Check your Google authenticator app and Enter the OTP code</p>

                        <div class="d-flex justify-content-center mt-3">
                            <div style="display: flex; flex-direction: row">
                                <v-otp-input ref="otpInput" input-classes="otp-input"
                                    :conditionalClass="['one', 'two', 'three', 'four', 'five', 'six']" separator=""
                                    inputType="numeric" :num-inputs="6" v-model:value="bindValue"
                                    :should-auto-focus="true" :should-focus-order="true" @on-change="handleOnChange"
                                    @on-complete="handleOnComplete" :placeholder="['*', '*', '*', '*', '*', '*']" />
                            </div>
                        </div>


                    <div class="row justify-content-center mt-4">
                        <div class="clearfix w-100">

                            <button :disabled="loading" type="submit" class="btn btn-primary rounded-2 w-100 d-flex align-items-center justify-content-center gap-2">
                                <LoadingSpinner v-if="loading" :inline="true" size="sm" class="text-white" />
                                <span role="status">{{ ButtonName }}</span>
                            </button>

                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <p class="p3" @click="BackLogin"><i class="fas fa-angle-left"></i> Back to Login</p>
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
.btn-primary {
    padding: 10px 20px 10px 20px;
    border-radius: 8px 8px 8px 8px;
    opacity: 0px;
    background-color: #027DE2;
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
    font-size: 14px;
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

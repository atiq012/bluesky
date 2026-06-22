<script setup>
import { reactive, ref, watch } from "vue";
import { useAuthStore } from "../../stores/authStore";
const authStore = useAuthStore();

import { useRouter } from 'vue-router';
const router = useRouter();

const PassType = ref(false);
const ClearButton = ref(false);
const PassImagePath = ref(getImageUrl('HidePassword.svg'));
const ClearButtonImagePath = ref(getImageUrl('Cross.svg'));

const PassType1 = ref(false);
const ClearButton1 = ref(false);
const PassImagePath1 = ref(getImageUrl('HidePassword.svg'));
const ClearButtonImagePath1 = ref(getImageUrl('Cross.svg'));

const loading = ref(false);
const ButtonName = ref("");
ButtonName.value = "Reset Password";

const DefultClass = ref('picon fas fa-circle');
// const CheckClass = ref('piconGreen fas fa-check-circle');
const CheckClass = ref('piconGreen fas fa-check-double');
const Condition1 = ref(false);
const Condition2 = ref(false);
const Condition3 = ref(false);
const Condition4 = ref(false);

const rEmail = atob(router.currentRoute.value.params.email);
const rToken = router.currentRoute.value.params.token;


const form = reactive({ password: "", password_confirmation: "", email: rEmail, token: rToken });

function ShowPass() {

    if (PassType.value == true) {
        PassType.value = false
        PassImagePath.value = getImageUrl('HidePassword.svg')
    } else {
        PassType.value = true
        PassImagePath.value = getImageUrl('Viewpass.svg')
    }
}

function ShowPass1() {
    if (PassType1.value == true) {
        PassType1.value = false
        PassImagePath1.value = getImageUrl('HidePassword.svg')
    } else {
        PassType1.value = true
        PassImagePath1.value = getImageUrl('Viewpass.svg')
    }

}

function ClearPassword() {
    form.password = '';
}

function ClearPassword1() {
    form.password_confirmation = '';
}

function getImageUrl(name) {
    return new URL(`../../../../public/theme/appimages/${name}`, import.meta.url).href
}

function resetPassword() {

    if (!form.password) {
        Notification.showToast("e", "Please enter new password.");
        return;
    } else if (form.password.length < 4) {
        Notification.showToast("e", "New Password minimum length is 4.");
        return;
    } else if (!form.password_confirmation) {
        Notification.showToast("e", "Please enter confirm password.");
        return;
    } else if (form.password_confirmation.length < 4) {
        Notification.showToast("e", "Confirm password minimum length is 4.");
        return;
    } else if (form.password_confirmation !== form.password) {
        Notification.showToast("e", "Both field password must be same.");
        return;
    }

    loading.value = true;
    ButtonName.value = "Reseting..";



    axios.post('/api/PassReset', form
    ).then(
        res => {
            loading.value = false;
            ButtonName.value = "Reset Password";
            Notification.showToast("s", res.data.message);
            router.push({ name: "Login" });
        }
    ).catch((eEes) => {
        loading.value = false;
        ButtonName.value = "Reset Password";
        Notification.showToast("e", eEes.response.data.message);
    });


}

function BackLogin() {
    authStore.logout();
    router.push({ name: "Login" });
}

watch(form, (newValue, oldValue) => {
    let lowCheck = new RegExp("^(?=.*[a-z]).+$");
    let upCheck = new RegExp("^(?=.*[A-Z]).+$");
    let spCheck = new RegExp("^(?=.*[-+_!@#$%^&*.,?]).+$");
    let nuCheck = new RegExp("^(?=.*[0-9]).+$");
    let char8 = newValue.password.length>=8 ? true: false;
    if (newValue.password.length > 0) {
        ClearButton.value = true
        if (lowCheck.test(newValue.password) && upCheck.test(newValue.password)) {
            Condition1.value = true;
        } else {
            Condition1.value = false;
        }

        if (spCheck.test(newValue.password)) {
            Condition2.value = true;
        } else {
            Condition2.value = false;
        }

        if (nuCheck.test(newValue.password)) {
            Condition3.value = true;
        } else {
            Condition3.value = false;
        }

        if (char8) {
            Condition4.value = true;
        } else {
            Condition4.value = false;
        }

    } else {
        ClearButton.value = false
        if (lowCheck.test(newValue.password) && upCheck.test(newValue.password)) {
            Condition1.value = true;
        } else {
            Condition1.value = false;
        }

        if (spCheck.test(newValue.password)) {
            Condition2.value = true;
        } else {
            Condition2.value = false;
        }

        if (nuCheck.test(newValue.password)) {
            Condition3.value = true;
        } else {
            Condition3.value = false;
        }

        if (char8) {
            Condition4.value = true;
        } else {
            Condition4.value = false;
        }
    }

    if (newValue.password_confirmation.length > 0) {
        ClearButton1.value = true
    } else {
        ClearButton1.value = false
    }

});

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

        <!-- Reset sec -->
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="text-center">
                        <img src="../../../../public/theme/appimages/passwordreset.svg" alt="">
                    </div>
                    <p class="p1">Reset Password</p>
                    <p class="p2">Protect your account by entering a strong and unique password.
                        Do not use the <span class="last3">last 3 passwords</span> that have been used.</p>

                    <div class="position-relative plate mr-3 ml-3">
                        <div class="d-flex align-items-center mb-1">
                            <i :class="[Condition1 ? CheckClass : DefultClass]"></i>
                            <span class="ptext">Must contain a Uppercase and Lowercase. (A-z)</span>
                        </div>
                        <div class="d-flex align-items-center mb-1">
                            <i :class="[Condition2 ? CheckClass : DefultClass]"></i>
                            <span class="ptext">Must contain a special character. (!, @, #, %)</span>
                        </div>
                        <div class="d-flex align-items-center mb-1">
                            <i :class="[Condition3 ? CheckClass : DefultClass]"></i>
                            <span class="ptext">Must contain a number.</span>
                        </div>
                        <div class="d-flex align-items-center mb-1">
                            <i :class="[Condition4 ? CheckClass : DefultClass]"></i>
                            <span class="ptext">Must be minimum 8 characters.</span>
                        </div>

                    </div>

                    <div class="position-relative mr-3 ml-3">
                        <img class="position-absolute p-2" src="../../../../public/theme/appimages/Key-Pass.svg"
                            height="40" width="40" alt="leftmap">
                        <input v-model="form.password" class="form-control form-control-lg pl-5"
                            placeholder="Enter New Password" name="password" id="password"
                            :type="PassType ? 'text' : 'password'">
                        <img @click="ShowPass" class="position-absolute p-2" :src="PassImagePath" height="40" width="40"
                            id="eye" alt="leftmap" style="cursor: pointer; top: 2px; right: 0px;">
                        <img v-show="ClearButton" @click="ClearPassword" class="position-absolute p-2"
                            :src="ClearButtonImagePath" height="40" width="40" id="eye" alt="leftmap"
                            style="cursor: pointer; top: 2px; right: 0px; margin-right: 30px;">
                    </div>

                    <div class="input position-relative mt-3 mr-3 ml-3">
                        <img class="position-absolute p-2" src="../../../../public/theme/appimages/Key-Pass.svg"
                            height="40" width="40" alt="leftmap">
                        <input v-model="form.password_confirmation" class="form-control form-control-lg pl-5"
                            :type="PassType1 ? 'text' : 'password'" required placeholder="Confirm Password">

                        <img @click="ShowPass1" class="position-absolute p-2" :src="PassImagePath1" height="40"
                            width="40" id="eye" alt="leftmap" style="cursor: pointer; top: 2px; right: 0px;">
                        <img v-show="ClearButton1" @click="ClearPassword1" class="position-absolute p-2"
                            :src="ClearButtonImagePath1" height="40" width="40" id="eye" alt="leftmap"
                            style="cursor: pointer; top: 2px; right: 0px; margin-right: 30px;">
                    </div>

                    <div class="row justify-content-center mt-4 mr-3 ml-3">
                        <div class="clearfix w-100 pl-1 pr-1">

                            <button :disabled="loading" @click="resetPassword" class="btn btn-primary rounded-2 w-100 d-flex align-items-center justify-content-center gap-2">
                                <LoadingSpinner v-if="loading" :inline="true" size="sm" class="text-white" />
                                <span role="status">{{ ButtonName }}</span>
                            </button>

                        </div>
                    </div>

                    <div class="text-center mt-4">
                            <p class="p3" @click="BackLogin" ><i class="fas fa-angle-left"></i> Back to Login</p>
                    </div>

                </div>
            </div>
        </div>

        <!-- bottom-city -->
        <footer class="fixed-bottom">
            <img src="../../../../public/theme/appimages/bottomfullimage.svg" class="img-fluid w-100"
                alt="leftcityBottom" />
        </footer>


    </div>
</template>
<script>

</script>

<style scoped>
.picon {
    font-size: 9px;
    color: #7E8A99;
    margin-left: 10px;
}

.piconGreen {
    font-size: 10px;
    color: #15a93a;
    margin-left: 10px;
}

.ptext {
    font-size: 12px;
    color: #7E8A99;
    margin-left: 7px;
}

.plate {
    height: 95px;
    background-color: #f2f2f237;
    margin-bottom: 10px;
    border-radius: 5px;
    padding: 6px 0px 8px 0px;
}

.last3 {
    font-family: Inter;
    font-size: 12px;
    font-weight: 900;
    line-height: 26px;
    letter-spacing: 0.5px;
    text-align: center;
}

.btn-primary {
    color: #fff;
    background-color: #027DE2;
    border-color: #027DE2;
    padding: 8px 18px 8px 18px;
    border-radius: 8px 8px 8px 8px;
}

.form-control-lg {
    height: 43px !important;
    font-size: 13px;
    padding-left: 40px;
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
    width: 5%;
}

.p1 {
    font-family: Inter;
    font-size: 22px;
    font-weight: 900;
    line-height: 26px;
    letter-spacing: 0.5px;
    margin-top: 15px;
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
</style>

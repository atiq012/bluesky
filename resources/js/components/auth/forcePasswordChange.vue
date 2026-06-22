<script setup>
import { reactive, ref, watch } from "vue";
import { useAuthStore } from "../../stores/authStore";
const authStore = useAuthStore();
import ProgressBar from "vue-3-simple-progress-bar";

import { useRouter } from "vue-router";
const router = useRouter();

const PassType = ref(false);
const ClearButton = ref(false);
const PassImagePath = ref(getImageUrl("HidePassword.svg"));
const ClearButtonImagePath = ref(getImageUrl("Cross.svg"));

const PassType1 = ref(false);
const ClearButton1 = ref(false);
const PassImagePath1 = ref(getImageUrl("HidePassword.svg"));
const ClearButtonImagePath1 = ref(getImageUrl("Cross.svg"));

const PassType2 = ref(false);
const ClearButton2 = ref(false);
const PassImagePath2 = ref(getImageUrl("HidePassword.svg"));
const ClearButtonImagePath2 = ref(getImageUrl("Cross.svg"));

const loading = ref(false);
const ButtonName = ref("");
const proGressVal = ref(0);
ButtonName.value = "Reset Password";

const form = reactive({
    old_password: "",
    password: "",
    password_confirmation: "",
    eDays: "",
});

function ShowPass() {
    if (PassType.value == true) {
        PassType.value = false;
        PassImagePath.value = getImageUrl("HidePassword.svg");
    } else {
        PassType.value = true;
        PassImagePath.value = getImageUrl("Viewpass.svg");
    }
}
function ShowPass1() {
    if (PassType1.value == true) {
        PassType1.value = false;
        PassImagePath1.value = getImageUrl("HidePassword.svg");
    } else {
        PassType1.value = true;
        PassImagePath1.value = getImageUrl("Viewpass.svg");
    }
}
function ShowPass2() {
    if (PassType2.value == true) {
        PassType2.value = false;
        PassImagePath2.value = getImageUrl("HidePassword.svg");
    } else {
        PassType2.value = true;
        PassImagePath2.value = getImageUrl("Viewpass.svg");
    }
}
function ClearPassword() {
    form.password = "";
}
function ClearPassword1() {
    form.password_confirmation = "";
}
function ClearPassword2() {
    form.old_password = "";
}
function getImageUrl(name) {
    return new URL(`../../../../public/theme/appimages/${name}`, import.meta.url).href;
}
function setNumber(params) {
    form.eDays = params;
    proGressVal.value = params;
}
function resetPassword() {
    if (!form.old_password) {
        Notification.showToast("e", "Please enter old password first.");
        return;
    } else if (!form.password) {
        Notification.showToast("e", "Please enter new password.");
        return;
    } else if (form.password.length < 8) {
        Notification.showToast("e", "New Password minimum length is 8.");
        return;
    } else if (!form.password_confirmation) {
        Notification.showToast("e", "Please enter confirm password.");
        return;
    } else if (form.password_confirmation.length < 8) {
        Notification.showToast("e", "Confirm password minimum length is 8.");
        return;
    } else if (form.password_confirmation !== form.password) {
        Notification.showToast("e", "Both field password must be same.");
        return;
    } else if (form.password == form.old_password) {
        Notification.showToast("e", "Current password can not be new password.");
        return;
    } else if (proGressVal < 100) {
        Notification.showToast("e", "Enter password as per policy.");
        return;
    } else if (!form.eDays) {
        Notification.showToast("e", "Enter expire days.");
        return;
    } else if (form.eDays <= 0 && form.eDays > 90) {
        Notification.showToast("e", "Expire days should be between 1-90.");
        return;
    }

    loading.value = true;
    ButtonName.value = "Reseting..";
    const config = {
        headers: {
            Authorization: "Bearer " + authStore.decryptWithAES(authStore.token),
            Accept: "application/json",
        },
    };
    axios
        .post("/api/ForcePassReset", form, config)
        .then((res) => {
            loading.value = false;
            ButtonName.value = "Reset Password";
            console.log(res.data);
            Notification.showToast("s", res.data.message);
            authStore.logout();
            router.push({ name: "Login" });
        })
        .catch((eEes) => {
            loading.value = false;
            ButtonName.value = "Reset Password";
            console.log(eEes);
            Notification.showToast("e", eEes.response.data.message);
        });
}

function BackLogin() {
    authStore.logout();
    router.push({ name: "Login" });
}

function isGood(password) {
    var regex = new Array();
    regex.push("[A-Z]"); //Uppercase Alphabet.
    regex.push("[a-z]"); //Lowercase Alphabet.
    regex.push("[0-9]"); //Digit.
    regex.push("[$@$!%*#?&]"); //Special Character.

    var passed = 0;

    //Validate for each Regular Expression.
    for (var i = 0; i < regex.length; i++) {
        if (new RegExp(regex[i]).test(password)) {
            passed++;
        }
    }

    switch (passed) {
        case 1:
            proGressVal.value = 20;
            break;
        case 2:
            proGressVal.value = 40;
            break;
        case 3:
            proGressVal.value = 60;
            break;
        case 4:
            proGressVal.value = 80;
            break;
    }
}

watch(form, (newValue, oldValue) => {
    // let lowCheck = new RegExp("^(?=.*[a-z]).+$");
    // let upCheck = new RegExp("^(?=.*[A-Z]).+$");
    // let spCheck = new RegExp("^(?=.*[-+_!@#$%^&*.,?]).+$");
    // let nuCheck = new RegExp("^(?=.*[0-9]).+$");
    let char8 = newValue.password.length >= 8 ? true : false;

    isGood(newValue.password);
    console.log(proGressVal.value);

    if (char8) {
        proGressVal.value = proGressVal.value + 20;
    } else {
        proGressVal.value = proGressVal.value - 20;
    }

    if (newValue.password.length > 0) {
        ClearButton.value = true;
    } else {
        ClearButton.value = false;
        proGressVal.value = 0;
    }

    if (newValue.password_confirmation.length > 0) {
        ClearButton1.value = true;
    } else {
        ClearButton1.value = false;
    }

    if (newValue.old_password.length > 0) {
        ClearButton2.value = true;
    } else {
        ClearButton2.value = false;
    }
});
</script>
<template>
    <div class="container-fluid vh-100 p-0 m-0">
        <!-- top section -->
        <div class="d-flex justify-content-between">
            <div class="d-flex justify-content-start mt-3 ml-4">
                <div class="p-2 align-content-center">
                    <img src="../../../../public/theme/appimages/bird.gif" width="35" height="35" alt="rbird" />
                </div>
                <div class="p-1 align-content-center">
                    <img src="../../../../public/theme/appimages/blueskymainlogo.svg" height="35" alt="rlogo" />
                </div>
            </div>

            <img src="../../../../public/theme/appimages/bubble.svg" class="buble" alt="rlogo" />
        </div>

        <!-- Reset sec -->
        <div class="container mt-1">
            <div class="row justify-content-center">
                <div class="col-md-5">
                    <div class="text-center">
                        <img src="../../../../public/theme/appimages/forcePassword.svg" alt="" />
                    </div>
                    <p class="p1">Password Reset</p>
                    <p class="p2">
                        Your password has been expired, set a new password. You can select a password
                        expire period limit of <span class="zero-ninty">0-90 days.</span>
                        <i class="info border rounded-circle p-1 fas fa-info-circle" v-tippy="{
                            content:
                                '* Uppercase and Lowercase   * special character   * contain a number   * Eight characters',
                            theme: 'tomato',
                        }"></i>
                    </p>

                    <progress-bar :current-value="proGressVal" height="100" color="#3eb750" :strip="true"
                        :animation="true"></progress-bar>

                    <div class="position-relative mt-3">
                        <img class="position-absolute p-2" src="../../../../public/theme/appimages/oldpassword.svg"
                            height="40" width="40" alt="leftmap" />
                        <input v-model="form.old_password" class="form-control form-control-lg pl-5"
                            placeholder="Old Password" name="old_password" id="old_password"
                            :type="PassType2 ? 'text' : 'password'" />
                        <img @click="ShowPass2" class="position-absolute p-2" :src="PassImagePath2" height="40"
                            width="40" id="eye" alt="leftmap" style="cursor: pointer; top: 2px; right: 0px" />
                        <img v-show="ClearButton2" @click="ClearPassword2" class="position-absolute p-2"
                            :src="ClearButtonImagePath2" height="40" width="40" id="eye" alt="leftmap"
                            style="cursor: pointer; top: 2px; right: 0px; margin-right: 30px" />
                    </div>

                    <div class="position-relative mt-1">
                        <img class="position-absolute p-2" src="../../../../public/theme/appimages/Key-Pass.svg"
                            height="40" width="40" alt="leftmap" />
                        <input v-model="form.password" class="form-control form-control-lg pl-5"
                            placeholder="New Password" name="password" id="password"
                            :type="PassType ? 'text' : 'password'" />
                        <img @click="ShowPass" class="position-absolute p-2" :src="PassImagePath" height="40" width="40"
                            id="eye" alt="leftmap" style="cursor: pointer; top: 2px; right: 0px" />
                        <img v-show="ClearButton" @click="ClearPassword" class="position-absolute p-2"
                            :src="ClearButtonImagePath" height="40" width="40" id="eye" alt="leftmap"
                            style="cursor: pointer; top: 2px; right: 0px; margin-right: 30px" />
                    </div>

                    <div class="position-relative mt-1">
                        <img class="position-absolute p-2" src="../../../../public/theme/appimages/Key-Pass.svg"
                            height="40" width="40" alt="leftmap" />
                        <input v-model="form.password_confirmation" class="form-control form-control-lg pl-5"
                            :type="PassType1 ? 'text' : 'password'" required placeholder="Confirm Password" />

                        <img @click="ShowPass1" class="position-absolute p-2" :src="PassImagePath1" height="40"
                            width="40" id="eye" alt="leftmap" style="cursor: pointer; top: 2px; right: 0px" />
                        <img v-show="ClearButton1" @click="ClearPassword1" class="position-absolute p-2"
                            :src="ClearButtonImagePath1" height="40" width="40" id="eye" alt="leftmap"
                            style="cursor: pointer; top: 2px; right: 0px; margin-right: 30px" />
                    </div>

                    <div class="d-flex justify-content-center mt-1">
                        <div class="col-md-6 p-0">
                            <div class="position-relative">
                                <img class="position-absolute p-2"
                                    src="../../../../public/theme/appimages/carbontime.svg" height="40" width="40"
                                    alt="leftmap" />
                                <input v-model="form.eDays" class="form-control form-control-lg pl-5" min="1" max="90"
                                    type="number" required placeholder="Days Limit" />
                            </div>
                        </div>
                        <div class="col-md-6 p-0">
                            <div class="d-flex justify-content-between h-100">
                                <button @click="setNumber(15)" class="btn btn-outline-primary ml-2">
                                    15
                                </button>
                                <button @click="setNumber(30)" class="btn btn-outline-primary ml-1">
                                    30
                                </button>
                                <button @click="setNumber(45)" class="btn btn-outline-primary ml-1">
                                    45
                                </button>
                                <button @click="setNumber(90)" class="btn btn-outline-primary ml-1">
                                    90
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-center mt-3">
                        <div class="clearfix w-100">
                            <button :disabled="loading" @click="resetPassword" class="btn btn-primary rounded-2 w-100 d-flex align-items-center justify-content-center gap-2">
                                <LoadingSpinner v-if="loading" :inline="true" size="sm" class="text-white" />
                                <span role="status">{{ ButtonName }}</span>
                            </button>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <p class="p3" @click="BackLogin">
                            <i class="fas fa-angle-left"></i> Back to Login
                        </p>
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
<script></script>

<style scoped>
.tippy-box[data-theme="tomato"] {
    background-color: tomato;
    color: yellow;
}

.info {
    color: #027de2;
    background-color: #027de21a;
    cursor: pointer;
}

.zero-ninty {
    font-family: Inter;
    font-size: 12px;
    font-weight: 900;
    line-height: 26px;
    letter-spacing: 0.5px;
    text-align: center;
}

.btn-outline-primary {
    color: #027de2;
    height: 100%;
    font-weight: bolder;
    background-color: #0589f51d;
    border-color: #0589f51d;
}

.btn-outline-primary:hover {
    color: #fff;
    height: 100%;
    font-weight: bolder;
    background-color: #0589f5b6;
    border-color: #0589f5b6;
}

.btn-primary {
    color: #fff;
    background-color: #027de2;
    border-color: #027de2;
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
    margin-top: 35px;
    text-align: center;
    color: #027de2;
}

.p2 {
    font-family: Inter;
    font-size: 14px;
    letter-spacing: 0.5px;
    text-align: center;
    color: #5e6878;
}
</style>

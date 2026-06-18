import { defineStore } from "pinia";
import { ref } from "vue";
import CryptoJS from "crypto-js";

export const useAuthStore = defineStore(
    "piniaUserAuth",
    () => {
        const token = ref("");
        const abc_timer = ref();
        const after30sRun = ref(0);
        const ExpireInSec = ref();
        const getRequire_2fa = ref(1);
        const getotp_regisered = ref(0);
        const loginAttapms = ref(0);
        const getotpChecked = ref(0);
        const forcePassChange = ref(false);
        const getgoogle2fa_secret = ref("");
        const getgoogle2fa_qr = ref("");
        const email = ref("");
        const name = ref("");
        const GlobalLoading = ref(false);

        const showExpireWarrning = ref(false);
        const isDarkMode = ref(false);
        const isLogged = ref(false);
        let intervalId = 0;

        const sInfo = ref([]);

        const encryptWithAES = (text) => {
            const passphrase = "MySecPassBlueSky";
            return CryptoJS.AES.encrypt(text, passphrase).toString();
        };

        const decryptWithAES = (ciphertext) => {
            try {
                const passphrase = "MySecPassBlueSky";
                const bytes = CryptoJS.AES.decrypt(ciphertext, passphrase);
                const originalText = bytes.toString(CryptoJS.enc.Utf8);
                return originalText;
            } catch (error) {
                return error;
            }
        };

        function hasToken() {
            if (!token.value) return false;
            try {
                const dToken = decryptWithAES(token.value);
                const payload = JSON.parse(atob(dToken.split('.')[1]));
                return Date.now() < payload.exp * 1000;
            } catch {
                return false;
            }
        }

        function logout() {
            clearInterval(intervalId);
            token.value = "";
            name.value = "";
            abc_timer.value = 0;
            ExpireInSec.value = 0;
            getRequire_2fa.value = 1;
            getotp_regisered.value = 0;
            getotpChecked.value = 0;
            getgoogle2fa_qr.value = "";
            showExpireWarrning.value = false;
            isLogged.value = false;
            getgoogle2fa_secret.value = "";
            forcePassChange.value = false;
            loginAttapms.value = 0;
            after30sRun.value = 0;
        }

        function killRunningTask() {
            clearInterval(intervalId);
            abc_timer.value = 0;
            showExpireWarrning.value = false;
        }

        function runTaskWithTimer(ProvideTimeSecends) {
            var afterDeduct = 10;

            intervalId = setInterval(() => {
                abc_timer.value = ProvideTimeSecends--;

                if (ProvideTimeSecends < afterDeduct) {
                    showExpireWarrning.value = true;
                }

                if (ProvideTimeSecends < 0) {
                    token.value = "";
                    name.value = "";
                    abc_timer.value = 0;
                    ExpireInSec.value = 0;
                    showExpireWarrning.value = false;
                    isLogged.value = false;
                    clearInterval(intervalId);
                }
            }, 1000);
        }

        function refreshToken(ePireINSec, setExpire = true) {
            killRunningTask();
            if (setExpire) ExpireInSec.value = ePireINSec;
            isLogged.value = true;
            runTaskWithTimer(ePireINSec);
        }

        return {
            getRequire_2fa,
            getotp_regisered,
            getotpChecked,
            isLogged,
            after30sRun,
            refreshToken,
            killRunningTask,
            ExpireInSec,
            runTaskWithTimer,
            abc_timer,
            showExpireWarrning,
            email,
            token,
            name,
            encryptWithAES,
            decryptWithAES,
            hasToken,
            logout,
            getgoogle2fa_secret,
            getgoogle2fa_qr,
            loginAttapms,
            forcePassChange,
            sInfo,
            isDarkMode,
            GlobalLoading
        };
    },
    { persist: true }
);

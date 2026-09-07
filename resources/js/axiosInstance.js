import axios from "axios";
import { useAuthStore } from "./stores/authStore";
import { performLogout } from "./Helpers/logout";
import Notification from "./Helpers/Notification";

//creating an axios instance
const urls = document.head.querySelector('meta[name="api-base-url"]').content;

const axiosInstance = axios.create({

    baseURL: urls+"/api/",
    headers: {
        "Content-Type": "application/json",
    },
});

async function refresh_token() {
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

// Hold / Locked / Deactivated → kick even if Pusher missed the event
function handleForceLogoutResponse(error) {
    if (!error?.response?.data?.data?.force_logout) {
        return false;
    }

    const message = error.response.data.message
        || 'Your account status has changed. You have been logged out.';
    Notification.showToast('w', message);
    void performLogout({ callApi: false, showToast: false });
    return true;
}

axiosInstance.interceptors.request.use(
    async (request) => {
        const authStore = useAuthStore();
        const accessToken = authStore.decryptWithAES(authStore.token);

        const sToken = accessToken.split(".")[1];
        const ReminTime = Math.round(
            (JSON.parse(window.atob(sToken)).exp * 1000 - Date.now()) / 1000
        );

        if (ReminTime < 30) {
            let res = await refresh_token();
            authStore.token = authStore.encryptWithAES(
                res.data.data.access_token
            );
            const at = res.data.data.access_token;
            authStore.refreshToken(res.data.data.expires_in_sec);
            authStore.after30sRun = 1;
            if (at) {
                request.headers["Authorization"] = `Bearer ${at}`;
            }
            return request;
        } else {
            request.headers["Authorization"] = `Bearer ${accessToken}`;
            return request;
        }
    },
    (error) => {
        return Promise.reject(error);
    }
);

axiosInstance.interceptors.response.use(
    (response) => response,
    (error) => {
        handleForceLogoutResponse(error);
        return Promise.reject(error);
    }
);

axios.interceptors.response.use(
    function (response) {
        return response;
    },
    function (error) {
        handleForceLogoutResponse(error);
        return Promise.reject(error);
    }
);

export default axiosInstance;

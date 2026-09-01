import { useAuthStore } from "../stores/authStore";
import router from "../routers";
import Notification from "./Notification";

export const LOGOUT_BROADCAST_KEY = "bluesky:logout";
export const LOGOUT_REQUEST_EVENT = "bluesky:logout-request";

let logoutInProgress = false;

// Single logout path: API blacklist, store reset, storage wipe, optional cross-tab broadcast.
export async function performLogout({
    callApi = true,
    redirect = true,
    broadcast = true,
    wipeStorage = true,
    showToast = false,
} = {}) {
    if (logoutInProgress) return;
    logoutInProgress = true;

    const authStore = useAuthStore();
    const lastEmail = (authStore.email || "").trim();

    try {
        if (callApi && authStore.hasToken()) {
            try {
                const tkn = authStore.decryptWithAES(authStore.token);
                const res = await axios.get("/api/logout", {
                    headers: {
                        Authorization: "Bearer " + tkn,
                        Accept: "application/json",
                    },
                });
                if (showToast) {
                    Notification.showToast("w", res.data.message);
                }
            } catch (e) {
                console.log(e);
            }
        }

        // Other tabs read this before originating tab clears storage.
        if (broadcast) {
            try {
                localStorage.setItem(LOGOUT_BROADCAST_KEY, String(Date.now()));
            } catch (_) {}
        }

        authStore.logout();

        if (wipeStorage) {
            localStorage.clear();
            sessionStorage.clear();
        }

        // Login UX: keep last email only — no token/password/session data.
        if (lastEmail) {
            authStore.email = lastEmail;
        }
    } finally {
        logoutInProgress = false;

        if (redirect && router.currentRoute.value?.name !== "Login") {
            router.push({ name: "Login" });
        }
    }
}

export function installLogoutSync() {
    window.addEventListener("storage", (e) => {
        if (e.key !== LOGOUT_BROADCAST_KEY || !e.newValue) return;
        void performLogout({
            callApi: false,
            redirect: true,
            broadcast: false,
            wipeStorage: true,
        });
    });

    window.addEventListener(LOGOUT_REQUEST_EVENT, () => {
        void performLogout();
    });
}

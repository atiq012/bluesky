import Pusher from 'pusher-js';
import { useAuthStore } from '../stores/authStore';
import { performLogout } from '../Helpers/logout';
import Notification from '../Helpers/Notification';

function resolveCurrentUserId() {
    try {
        const authStore = useAuthStore();
        if (!authStore.token) {
            return null;
        }

        const raw = authStore.decryptWithAES(authStore.token);
        const payload = JSON.parse(atob(raw.split('.')[1]));

        return payload.sub ?? null;
    } catch {
        return null;
    }
}

function createPusherClient() {
    const key = import.meta.env.VITE_PUSHER_APP_KEY;
    if (!key) {
        return null;
    }

    return new Pusher(key, {
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
        wsHost: import.meta.env.VITE_PUSHER_HOST,
        wsPort: Number(import.meta.env.VITE_PUSHER_PORT ?? 443),
        wssPort: Number(import.meta.env.VITE_PUSHER_PORT ?? 443),
        forceTLS: import.meta.env.VITE_PUSHER_SCHEME === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}

// Admin Hold / Locked / Deactivated → live kick via user-session.{id} ForceLogout
export function installForceLogoutListener() {
    const authStore = useAuthStore();
    let client = null;
    let channel = null;
    let channelKey = null;

    const cleanup = () => {
        if (channel) {
            channel.unbind('ForceLogout');
            channel = null;
        }
        if (client && channelKey) {
            client.unsubscribe(channelKey);
            client.disconnect();
            client = null;
            channelKey = null;
        }
    };

    const onForceLogout = (payload) => {
        const reason = payload?.reason
            || 'Your account status has changed. You have been logged out.';
        Notification.showToast('w', reason);
        void performLogout({ callApi: false, showToast: false });
    };

    const subscribe = () => {
        if (!authStore.isLogged || !authStore.hasToken()) {
            cleanup();
            return;
        }

        const userId = resolveCurrentUserId();
        if (userId == null) {
            cleanup();
            return;
        }

        const nextKey = `user-session.${userId}`;
        if (channelKey === nextKey && channel) {
            return;
        }

        cleanup();

        client = createPusherClient();
        if (!client) {
            return;
        }

        channelKey = nextKey;
        channel = client.subscribe(channelKey);
        channel.bind('ForceLogout', onForceLogout);
    };

    authStore.$subscribe(() => {
        if (authStore.isLogged && authStore.hasToken()) {
            subscribe();
        } else {
            cleanup();
        }
    });

    subscribe();
}

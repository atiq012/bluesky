import { onMounted, onUnmounted } from 'vue';
import Pusher from 'pusher-js';
import { useAuthStore } from '../stores/authStore';

const REALTIME_EVENTS = ['Created', 'Updated', 'Deleted'];

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

// Subscribe to Pusher public channel; refetch list on Created/Updated/Deleted unless actor is current user.
export function useRealtimeList(channelKey, onInvalidate, options = {}) {
    if (!channelKey || typeof onInvalidate !== 'function') {
        return;
    }

    const { actorIdKey = 'actor_id', enabled = true } = options;
    let client = null;
    let channel = null;

    const onMessage = (eventName, payload) => {
        payload = payload ?? {};

        if (actorIdKey) {
            const actorId = payload[actorIdKey];
            const currentUserId = resolveCurrentUserId();

            if (actorId != null && currentUserId != null && Number(actorId) === Number(currentUserId)) {
                return;
            }
        }

        onInvalidate(eventName, payload);
    };

    const subscribe = () => {
        if (!enabled) {
            return;
        }

        client = createPusherClient();
        if (!client) {
            return;
        }

        channel = client.subscribe(channelKey);
        REALTIME_EVENTS.forEach((eventName) => {
            channel.bind(eventName, (payload) => onMessage(eventName, payload));
        });
    };

    const cleanup = () => {
        if (channel) {
            REALTIME_EVENTS.forEach((eventName) => {
                channel.unbind(eventName);
            });
            channel = null;
        }

        if (client) {
            client.unsubscribe(channelKey);
            client.disconnect();
            client = null;
        }
    };

    onMounted(subscribe);
    onUnmounted(cleanup);
}

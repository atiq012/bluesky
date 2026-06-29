import { onMounted, onUnmounted } from 'vue';
import * as Ably from 'ably';
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

function createAblyClient() {
    const key = import.meta.env.VITE_ABLY_KEY;
    if (!key) {
        return null;
    }

    return new Ably.Realtime({ key, echoMessages: false });
}

// Subscribe to Ably public channel; refetch list on Created/Updated/Deleted unless actor is current user.
export function useRealtimeList(channelKey, onInvalidate, options = {}) {
    if (!channelKey || typeof onInvalidate !== 'function') {
        return;
    }

    const { actorIdKey = 'actor_id', enabled = true } = options;
    let client = null;
    let channel = null;

    const onMessage = (message) => {
        if (!message?.name) {
            return;
        }

        const payload = message.data ?? {};

        if (actorIdKey) {
            const actorId = payload[actorIdKey];
            const currentUserId = resolveCurrentUserId();

            if (actorId != null && currentUserId != null && Number(actorId) === Number(currentUserId)) {
                return;
            }
        }

        onInvalidate(message.name, payload);
    };

    const subscribe = () => {
        if (!enabled) {
            return;
        }

        client = createAblyClient();
        if (!client) {
            return;
        }

        channel = client.channels.get(channelKey);
        REALTIME_EVENTS.forEach((eventName) => {
            channel.subscribe(eventName, onMessage);
        });
    };

    const cleanup = () => {
        if (channel) {
            REALTIME_EVENTS.forEach((eventName) => {
                channel.unsubscribe(eventName, onMessage);
            });
            channel = null;
        }

        if (client) {
            client.close();
            client = null;
        }
    };

    onMounted(subscribe);
    onUnmounted(cleanup);
}

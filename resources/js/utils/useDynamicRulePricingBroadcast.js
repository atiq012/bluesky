import Echo from 'laravel-echo';
import Ably from 'ably';
import axiosInstance from '../axiosInstance';
import Notification from '../Helpers/Notification.js';

let echoInstance = null;
let subscriberCount = 0;
let activeUnsubscribe = null;

const DEFAULT_POLL_MS = 10000;

function getAblyKey() {
    return import.meta.env.VITE_ABLY_KEY || '';
}

function getBroadcastChannel() {
    return import.meta.env.VITE_DYNAMIC_RULES_BROADCAST_CHANNEL || 'dynamic-rules';
}

function getPollIntervalMs() {
    const seconds = Number(import.meta.env.VITE_DYNAMIC_RULES_POLL_INTERVAL || 0);
    return (seconds > 0 ? seconds : 10) * 1000;
}

function getEcho() {
    const key = getAblyKey();
    if (!key) {
        return null;
    }

    if (!echoInstance) {
        window.Ably = Ably;
        echoInstance = new Echo({
            broadcaster: 'ably',
            key,
        });
    }

    return echoInstance;
}

async function fetchCacheStamp() {
    const response = await axiosInstance.get('dynamic-rules/cache-stamp');
    const payload = response?.data?.data ?? response?.data ?? {};
    return `${payload.version}:${payload.stamp}`;
}

function notifyAndRefresh(onUpdated) {
    Notification.showToast(
        'i',
        'Pricing rules were updated. Refreshing search results...',
    );
    onUpdated?.();
}

function subscribeAbly(onUpdated) {
    const echo = getEcho();
    if (!echo) {
        return null;
    }

    const channelName = getBroadcastChannel();
    const channel = echo.channel(channelName);

    const handler = () => notifyAndRefresh(onUpdated);
    channel.listen('.updated', handler);

    return () => {
        channel.stopListening('.updated', handler);
        echo.leave(channelName);
    };
}

function startPolling(onUpdated, shouldRefresh) {
    let lastStamp = null;
    let inFlight = false;

    const poll = async () => {
        if (inFlight) return;
        inFlight = true;
        try {
            const nextStamp = await fetchCacheStamp();
            if (lastStamp !== null && nextStamp !== lastStamp) {
                if (!shouldRefresh || shouldRefresh()) {
                    notifyAndRefresh(onUpdated);
                } else {
                    Notification.showToast(
                        'i',
                        'Pricing rules were updated. Please search again to see new prices.',
                    );
                }
            }
            lastStamp = nextStamp;
        } catch {
            // Polling is best-effort; ignore transient network errors.
        } finally {
            inFlight = false;
        }
    };

    void poll();
    const timerId = window.setInterval(poll, getPollIntervalMs());

    return () => window.clearInterval(timerId);
}

// Search page only — Ably when configured, otherwise poll cache stamp.
export function subscribeDynamicRulePricingUpdates({ onUpdated, shouldRefresh } = {}) {
    subscriberCount += 1;

    if (activeUnsubscribe) {
        return activeUnsubscribe;
    }

    const ablyUnsub = subscribeAbly(onUpdated);
    // Always poll — Ably can fail silently (stale build, bad key, network).
    const pollUnsub = startPolling(onUpdated, shouldRefresh);

    activeUnsubscribe = () => {
        ablyUnsub?.();
        pollUnsub?.();

        subscriberCount = Math.max(0, subscriberCount - 1);
        if (subscriberCount === 0) {
            activeUnsubscribe = null;
        }
    };

    return activeUnsubscribe;
}

export function unsubscribeDynamicRulePricingUpdates(unsubscribe) {
    if (typeof unsubscribe === 'function') {
        unsubscribe();
    }
}

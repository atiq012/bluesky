import Echo from 'laravel-echo';
import Ably from 'ably';
import axiosInstance from '../../axiosInstance';
import Notification from '../../Helpers/Notification.js';

// Mirrors useDynamicRulePricingBroadcast.js (§16.6 — same shape on purpose, easy for blueskyb2b
// to port both).
let echoInstance = null;
let subscriberCount = 0;
let activeUnsubscribe = null;

function getAblyKey() {
    return import.meta.env.VITE_ABLY_KEY || '';
}

function getBroadcastChannel() {
    return import.meta.env.VITE_FARE_RULES_BROADCAST_CHANNEL || 'fare-rules';
}

function getPollIntervalMs() {
    const seconds = Number(import.meta.env.VITE_FARE_RULES_POLL_INTERVAL || 0);
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
    const response = await axiosInstance.get('fare-rules/cache-stamp');
    const payload = response?.data?.data ?? response?.data ?? {};

    return `${payload.version}:${payload.stamp}`;
}

function notifyAndRefresh(onUpdated) {
    Notification.showToast(
        'i',
        'Fare rules were updated. Refreshing search results...',
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
                        'Fare rules were updated. Please search again to see new prices.',
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
export function subscribeFareRuleUpdates({ onUpdated, shouldRefresh } = {}) {
    subscriberCount += 1;

    if (activeUnsubscribe) {
        return activeUnsubscribe;
    }

    // Echo's Ably connector can throw synchronously during construction (bad key, broadcaster
    // misconfiguration, missing peer dep) rather than failing async — a throw here must not take
    // the poll fallback down with it, since that fallback is the whole point of having one (§11.9:
    // "if Ably... fails, the poll... That ordering is deliberate").
    let ablyUnsub = null;
    try {
        ablyUnsub = subscribeAbly(onUpdated);
    } catch {
        ablyUnsub = null;
    }
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

export function unsubscribeFareRuleUpdates(unsubscribe) {
    if (typeof unsubscribe === 'function') {
        unsubscribe();
    }
}

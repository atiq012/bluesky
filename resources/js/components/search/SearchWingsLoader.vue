<script setup>
// Blue Sky wings fly-in + flap from blue_sky_wings_flyin_then_flap.html
const props = defineProps({
    variant: { type: String, default: 'loader' },
    idPrefix: { type: String, default: 'wings' },
});

const isBrand = props.variant === 'brand';
const mintId = `${props.idPrefix}-mint`;
const blueId = `${props.idPrefix}-blue`;
const purpleId = `${props.idPrefix}-purple`;
const loaderText = 'Fuelling...'.split('');
</script>

<template>
    <div class="search-wings-loader" :class="`search-wings-loader--${variant}`">
        <div class="search-wings-stage">
            <svg
                viewBox="0 0 600 340"
                :width="isBrand ? 40 : 157"
                :height="isBrand ? 22 : 89"
                style="overflow:visible;flex-shrink:0"
            >
                <defs>
                    <linearGradient :id="mintId" x1="0" y1="1" x2="1" y2="0">
                        <stop offset="0%" stop-color="#4DFFE0" stop-opacity="0" />
                        <stop offset="100%" stop-color="#3DEBCB" stop-opacity="1" />
                    </linearGradient>
                    <linearGradient :id="blueId" x1="0" y1="1" x2="1" y2="0">
                        <stop offset="0%" stop-color="#3B6FEA" stop-opacity="0" />
                        <stop offset="100%" stop-color="#2F5CE0" stop-opacity="1" />
                    </linearGradient>
                    <linearGradient :id="purpleId" x1="0" y1="1" x2="1" y2="0">
                        <stop offset="0%" stop-color="#9B4FE0" stop-opacity="0" />
                        <stop offset="100%" stop-color="#8B3FDB" stop-opacity="1" />
                    </linearGradient>
                </defs>
                <g class="bsw-glide">
                    <g transform="translate(240,-20)">
                        <path class="bsw-layer-mint" :fill="`url(#${mintId})`" d="M60 340 C40 300,120 180,220 90 C260 55,300 40,320 55 C340 70,330 110,300 150 C240 230,150 300,60 340 Z" />
                        <g transform="translate(-14,34) scale(0.9)">
                            <path class="bsw-layer-blue" :fill="`url(#${blueId})`" d="M60 340 C40 300,120 180,220 90 C260 55,300 40,320 55 C340 70,330 110,300 150 C240 230,150 300,60 340 Z" />
                        </g>
                        <g transform="translate(-28,66) scale(0.82)">
                            <path class="bsw-layer-purple" :fill="`url(#${purpleId})`" d="M60 340 C40 300,120 180,220 90 C260 55,300 40,320 55 C340 70,330 110,300 150 C240 230,150 300,60 340 Z" />
                        </g>
                    </g>
                    <g transform="translate(360,-20) scale(-1,1)">
                        <path class="bsw-layer-mint" :fill="`url(#${mintId})`" d="M60 340 C40 300,120 180,220 90 C260 55,300 40,320 55 C340 70,330 110,300 150 C240 230,150 300,60 340 Z" />
                        <g transform="translate(-14,34) scale(0.9)">
                            <path class="bsw-layer-blue" :fill="`url(#${blueId})`" d="M60 340 C40 300,120 180,220 90 C260 55,300 40,320 55 C340 70,330 110,300 150 C240 230,150 300,60 340 Z" />
                        </g>
                        <g transform="translate(-28,66) scale(0.82)">
                            <path class="bsw-layer-purple" :fill="`url(#${purpleId})`" d="M60 340 C40 300,120 180,220 90 C260 55,300 40,320 55 C340 70,330 110,300 150 C240 230,150 300,60 340 Z" />
                        </g>
                    </g>
                </g>
            </svg>
        </div>
        <div v-if="!isBrand" class="search-wings-loader-text" aria-label="Fuelling...">
            <span
                v-for="(ch, i) in loaderText"
                :key="i"
                class="search-wings-loader-text-letter"
                :style="{ animationDelay: `${i * 0.08}s` }"
            >{{ ch === ' ' ? ' ' : ch }}</span>
        </div>
    </div>
</template>

<style scoped>
.search-wings-loader {
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: visible;
    will-change: transform, opacity;
    transform: translateZ(0);
}

.search-wings-loader--loader {
    width: 200px;
    height: 200px;
    flex-direction: column;
    gap: 6px;
    animation: bsw-enter 0.5s ease-out;
}

.search-wings-loader-text {
    font-size: 1rem;
    font-weight: 600;
    color: #cbdbf2;
    letter-spacing: 0.02em;
}

html[data-bs-theme="dark"] .search-wings-loader-text {
    color: #cbd5e1;
}

.search-wings-loader-text-letter {
    display: inline-block;
    white-space: pre;
    animation: bsw-text-wave 1.1s ease-in-out infinite;
}

@keyframes bsw-text-wave {
    0%, 60%, 100% { transform: translateY(0); }
    30% { transform: translateY(-5px); }
}

.search-wings-loader--brand {
    width: 40px;
    height: 28px;
    flex-shrink: 0;
}

.search-wings-stage {
    position: relative;
    overflow: visible;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    will-change: transform;
    transform: translateZ(0);
}

.search-wings-loader--loader .search-wings-stage {
    width: 172px;
    height: 122px;
    padding-bottom: 10px;
}

.search-wings-loader--brand .search-wings-stage {
    width: 40px;
    height: 28px;
    padding-bottom: 0;
}

.bsw-layer-mint,
.bsw-layer-blue,
.bsw-layer-purple {
    transform-box: fill-box;
    transform-origin: 7% 100%;
}

.bsw-layer-mint {
    rotate: -10deg;
    animation: bsw-enter-mint 0.65s cubic-bezier(0.22, 1, 0.36, 1) both, bsw-flap-outer 0.9s infinite 0.65s;
}

.bsw-layer-blue {
    rotate: -8deg;
    animation: bsw-enter-blue 0.65s cubic-bezier(0.22, 1, 0.36, 1) both 0.15s, bsw-flap-mid 0.9s infinite 0.8s;
}

.bsw-layer-purple {
    rotate: -6deg;
    animation: bsw-enter-purple 0.65s cubic-bezier(0.22, 1, 0.36, 1) both 0.3s, bsw-flap-inner 0.9s infinite 0.95s;
}

.bsw-glide {
    transform-box: fill-box;
    animation: bsw-glide 3.6s ease-in-out infinite 0.95s backwards;
}

@keyframes bsw-enter {
    0% { opacity: 0; transform: scale(0.9); }
    100% { opacity: 1; transform: scale(1); }
}

@keyframes bsw-enter-mint {
    0% { opacity: 0; translate: 90px -70px; scale: 0.55; }
    100% { opacity: 1; translate: 0 0; scale: 1; }
}

@keyframes bsw-enter-blue {
    0% { opacity: 0; translate: 70px -55px; scale: 0.55; }
    100% { opacity: 1; translate: 0 0; scale: 1; }
}

@keyframes bsw-enter-purple {
    0% { opacity: 0; translate: 55px -40px; scale: 0.55; }
    100% { opacity: 1; translate: 0 0; scale: 1; }
}

@keyframes bsw-flap-outer {
    0% { rotate: -10deg; animation-timing-function: cubic-bezier(0.4, 0, 0.6, 1); }
    18% { rotate: 34deg; animation-timing-function: cubic-bezier(0.4, 0, 0.2, 1); }
    55% { rotate: -36deg; animation-timing-function: cubic-bezier(0.4, 0, 0.6, 1); }
    100% { rotate: -10deg; }
}

@keyframes bsw-flap-mid {
    0% { rotate: -8deg; animation-timing-function: cubic-bezier(0.4, 0, 0.6, 1); }
    18% { rotate: 26deg; animation-timing-function: cubic-bezier(0.4, 0, 0.2, 1); }
    55% { rotate: -28deg; animation-timing-function: cubic-bezier(0.4, 0, 0.6, 1); }
    100% { rotate: -8deg; }
}

@keyframes bsw-flap-inner {
    0% { rotate: -6deg; animation-timing-function: cubic-bezier(0.4, 0, 0.6, 1); }
    18% { rotate: 19deg; animation-timing-function: cubic-bezier(0.4, 0, 0.2, 1); }
    55% { rotate: -21deg; animation-timing-function: cubic-bezier(0.4, 0, 0.6, 1); }
    100% { rotate: -6deg; }
}

@keyframes bsw-glide {
    0% { transform: translateY(0); }
    50% { transform: translateY(-8px); }
    100% { transform: translateY(0); }
}
</style>

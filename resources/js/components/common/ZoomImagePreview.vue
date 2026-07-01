<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from "vue";

const defaultFallbackSrc = new URL("../../../../public/theme/appimages/profile_default_img.svg", import.meta.url).href;

const props = defineProps({
    src: { type: String, default: "" },
    alt: { type: String, default: "Preview Image" },
    thumbWidth: { type: [Number, String], default: 40 },
    thumbHeight: { type: [Number, String], default: 40 },
    rounded: { type: Boolean, default: false },
    fallbackSrc: { type: String, default: "" },
});

const resolvedFallback = computed(() => (props.fallbackSrc || defaultFallbackSrc).trim());
const displaySrc = ref("");

function applySrc(value) {
    const next = (value || "").trim();
    displaySrc.value = next || resolvedFallback.value;
}

watch(() => props.src, (value) => applySrc(value), { immediate: true });

function onThumbError() {
    if (displaySrc.value !== resolvedFallback.value) {
        displaySrc.value = resolvedFallback.value;
    }
}

const lb = ref({ open: false, scale: 1, tx: 0, ty: 0 });
const lbPanning = ref(false);
let panOrigin = { x: 0, y: 0, tx: 0, ty: 0 };

function openPreview() {
    lb.value = { open: true, scale: 1, tx: 0, ty: 0 };
    document.body.style.overflow = 'hidden';
}

function closePreview() {
    lb.value.open = false;
    lbPanning.value = false;
    document.body.style.overflow = '';
}

function lbWheel(e) {
    const zf = e.deltaY < 0 ? 1.12 : 0.89;
    const newScale = Math.min(8, Math.max(0.2, lb.value.scale * zf));
    const actual = newScale / lb.value.scale;
    const cx = window.innerWidth / 2;
    const cy = window.innerHeight / 2;
    lb.value.tx = (e.clientX - cx) * (1 - actual) + lb.value.tx * actual;
    lb.value.ty = (e.clientY - cy) * (1 - actual) + lb.value.ty * actual;
    lb.value.scale = newScale;
}

function lbDblclick() {
    Object.assign(lb.value, { scale: 1, tx: 0, ty: 0 });
}

function lbMousedown(e) {
    if (e.button !== 0) return;
    lbPanning.value = true;
    panOrigin = { x: e.clientX, y: e.clientY, tx: lb.value.tx, ty: lb.value.ty };
}
function lbMousemove(e) {
    if (!lbPanning.value) return;
    lb.value.tx = panOrigin.tx + (e.clientX - panOrigin.x);
    lb.value.ty = panOrigin.ty + (e.clientY - panOrigin.y);
}
function lbMouseup() { lbPanning.value = false; }

let touchOrigin = null;
function lbTouchstart(e) {
    if (e.touches.length !== 1) return;
    touchOrigin = { x: e.touches[0].clientX, y: e.touches[0].clientY, tx: lb.value.tx, ty: lb.value.ty };
}
function lbTouchmove(e) {
    if (!touchOrigin || e.touches.length !== 1) return;
    e.preventDefault();
    lb.value.tx = touchOrigin.tx + (e.touches[0].clientX - touchOrigin.x);
    lb.value.ty = touchOrigin.ty + (e.touches[0].clientY - touchOrigin.y);
}
function lbTouchend() { touchOrigin = null; }

function lbKeydown(e) {
    if (!lb.value.open) return;
    if (e.key === 'Escape') closePreview();
    if (e.key === '+' || e.key === '=') lb.value.scale = Math.min(8, lb.value.scale * 1.2);
    if (e.key === '-') lb.value.scale = Math.max(0.2, lb.value.scale / 1.2);
    if (e.key === '0') lbDblclick();
}

onMounted(() => document.addEventListener('keydown', lbKeydown));
onUnmounted(() => {
    document.removeEventListener('keydown', lbKeydown);
    if (lb.value.open) document.body.style.overflow = '';
});
</script>

<template>
    <img
        :src="displaySrc"
        :alt="alt"
        :width="thumbWidth"
        :height="thumbHeight"
        class="preview-thumb"
        :class="{ 'rounded rounded-2': rounded }"
        @error="onThumbError"
        @click="openPreview"
    >

    <Teleport to="body">
        <div
            v-if="lb.open"
            class="zip-backdrop"
            @click.self="closePreview"
            @wheel.prevent="lbWheel"
            @mousemove="lbMousemove"
            @mouseup="lbMouseup"
            @mouseleave="lbMouseup"
            @touchstart.passive="lbTouchstart"
            @touchmove.prevent="lbTouchmove"
            @touchend="lbTouchend"
        >
            <button class="zip-btn zip-close" title="Close (Esc)" @click="closePreview">
                <i class="bx bx-x"></i>
            </button>

            <div
                class="zip-stage"
                :style="{ cursor: lbPanning ? 'grabbing' : 'grab' }"
                @mousedown="lbMousedown"
                @dblclick="lbDblclick"
            >
                <img
                    :src="displaySrc"
                    :alt="alt"
                    class="zip-img"
                    :style="{ transform: `translate(${lb.tx}px, ${lb.ty}px) scale(${lb.scale})` }"
                    draggable="false"
                    @error="onThumbError"
                >
            </div>

            <div class="zip-footer">
                <span v-if="alt" class="zip-footer__name">{{ alt }}</span>
                <span class="zip-footer__meta">
                    <span class="zip-footer__zoom">{{ Math.round(lb.scale * 100) }}%</span>
                    <span class="zip-footer__hint">Scroll = zoom · Drag = pan · Dbl-click = reset · 0 = fit</span>
                </span>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.preview-thumb {
    object-fit: cover;
    cursor: zoom-in;
}

.zip-backdrop {
    position: fixed; inset: 0; z-index: 9999;
    background: rgba(0, 0, 0, 0.92);
    display: flex; align-items: center; justify-content: center;
    overflow: hidden;
}

.zip-stage {
    position: absolute; inset: 0;
    display: flex; align-items: center; justify-content: center;
    user-select: none;
}
.zip-img {
    max-width: 90vw;
    max-height: 88vh;
    object-fit: contain;
    transform-origin: center center;
    transition: transform 0.06s ease-out;
    border-radius: 4px;
    box-shadow: 0 8px 40px rgba(0,0,0,0.6);
    pointer-events: none;
}

.zip-btn {
    position: absolute;
    background: rgba(255,255,255,0.12);
    border: 1px solid rgba(255,255,255,0.18);
    color: #fff;
    border-radius: 50%;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: background 0.15s;
    z-index: 10;
    backdrop-filter: blur(4px);
}
.zip-btn:hover { background: rgba(114,57,234,0.75); border-color: #7239ea; }

.zip-close {
    top: 16px; right: 16px;
    width: 40px; height: 40px;
    font-size: 22px;
}

.zip-footer {
    position: absolute; bottom: 0; left: 0; right: 0;
    display: flex; align-items: center; justify-content: space-between;
    padding: 8px 20px;
    background: linear-gradient(transparent, rgba(0,0,0,0.65));
    color: rgba(255,255,255,0.85);
    font-size: 12px;
    pointer-events: none;
    z-index: 10;
}
.zip-footer__name { font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 40%; }
.zip-footer__meta { display: flex; align-items: center; gap: 14px; }
.zip-footer__zoom { font-variant-numeric: tabular-nums; min-width: 38px; text-align: right; }
.zip-footer__hint { opacity: 0.55; font-size: 10px; }
</style>

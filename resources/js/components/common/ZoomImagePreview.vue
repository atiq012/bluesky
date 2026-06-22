<script setup>
import { computed, ref, watch } from "vue";
import AppModal from "./AppModal.vue";

const defaultFallbackSrc = new URL("../../../../public/theme/appimages/profile_default_img.svg", import.meta.url).href;

const props = defineProps({
    src: { type: String, default: "" },
    alt: { type: String, default: "Preview Image" },
    thumbWidth: { type: [Number, String], default: 40 },
    thumbHeight: { type: [Number, String], default: 40 },
    previewScale: { type: Number, default: 0.7 },
    rounded: { type: Boolean, default: false },
    fallbackSrc: { type: String, default: "" },
});

const isOpen = ref(false);
const zoomLevel = ref(1);
const panX = ref(0);
const panY = ref(0);
const isDragging = ref(false);
const dragStartX = ref(0);
const dragStartY = ref(0);
const dragOriginX = ref(0);
const dragOriginY = ref(0);

const resolvedFallback = computed(() => (props.fallbackSrc || defaultFallbackSrc).trim());
const displaySrc = ref("");

function applySrc(value) {
    const next = (value || "").trim();
    displaySrc.value = next || resolvedFallback.value;
}

watch(
    () => props.src,
    (value) => applySrc(value),
    { immediate: true },
);

function onThumbError() {
    if (displaySrc.value !== resolvedFallback.value) {
        displaySrc.value = resolvedFallback.value;
    }
}

const previewStyle = computed(() => ({
    maxWidth: `${Math.round(props.previewScale * 100)}vw`,
    maxHeight: `${Math.round(props.previewScale * 100)}vh`,
    transform: `translate(${panX.value}px, ${panY.value}px) scale(${zoomLevel.value})`,
}));

function openPreview() {
    zoomLevel.value = 1;
    panX.value = 0;
    panY.value = 0;
    isOpen.value = true;
}

function closePreview() {
    isDragging.value = false;
    isOpen.value = false;
}

function handleWheel(event) {
    const step = event.deltaY < 0 ? 0.1 : -0.1;
    zoomLevel.value = Math.min(4, Math.max(0.4, zoomLevel.value + step));
    if (zoomLevel.value <= 1) {
        panX.value = 0;
        panY.value = 0;
    }
}

function handleMouseDown(event) {
    if (zoomLevel.value <= 1) return;
    isDragging.value = true;
    dragStartX.value = event.clientX;
    dragStartY.value = event.clientY;
    dragOriginX.value = panX.value;
    dragOriginY.value = panY.value;
}

function handleMouseMove(event) {
    if (!isDragging.value) return;
    panX.value = dragOriginX.value + (event.clientX - dragStartX.value);
    panY.value = dragOriginY.value + (event.clientY - dragStartY.value);
}

function handleMouseUp() {
    isDragging.value = false;
}
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

    <AppModal :is-open="isOpen" :show-header="false" :max-width="'80vw'" @close="closePreview">
        <div class="preview-wrap">
            <button type="button" class="btn-close preview-close" aria-label="Close" @click="closePreview"></button>
            <div
                class="preview-stage"
                :class="{ 'preview-stage--drag': zoomLevel > 1 }"
                @wheel.prevent="handleWheel"
                @mousedown="handleMouseDown"
                @mousemove="handleMouseMove"
                @mouseup="handleMouseUp"
                @mouseleave="handleMouseUp"
            >
                <img :src="displaySrc" :alt="alt" class="preview-image" :style="previewStyle" @error="onThumbError">
            </div>
        </div>
    </AppModal>
</template>

<style scoped>
.preview-thumb {
    object-fit: cover;
    cursor: pointer;
}

.preview-wrap {
    padding: 0.5rem;
    background: #fff;
    border-radius: 0.5rem;
}

.preview-close {
    float: right;
}

.preview-stage {
    min-height: 55vh;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    clear: both;
    cursor: default;
}

.preview-stage--drag {
    cursor: grab;
}

.preview-stage--drag:active {
    cursor: grabbing;
}

.preview-image {
    object-fit: contain;
    transition: transform 0.1s ease;
    user-select: none;
    -webkit-user-drag: none;
}
</style>

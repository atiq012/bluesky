<script setup>
import { computed } from 'vue';

const props = defineProps({
    text: { type: String, default: 'Loading' },
    size: { type: String, default: 'md' },
    inline: { type: Boolean, default: false },
});

const sizeMap = { sm: '0.9rem', md: '1.6rem', lg: '3rem' };
const iconSize = computed(() => sizeMap[props.size] || sizeMap.md);
</script>

<template>
    <span v-if="inline" class="ls-inline" aria-hidden="true">
        <svg class="ls-spin" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"
            :style="{ width: iconSize, height: iconSize }">
            <path fill="currentColor" d="M258.6 0c-1.7 0-3.4 .1-5.1 .5C168 17 115.6 102.3 130.5 189.3c2.9 17 8.4 32.9 15.9 47.4L32 224H29.4C13.2 224 0 237.2 0 253.4c0 1.7 .1 3.4 .5 5.1C17 344 102.3 396.4 189.3 381.5c17-2.9 32.9-8.4 47.4-15.9L224 480v2.6c0 16.2 13.2 29.4 29.4 29.4c1.7 0 3.4-.1 5.1-.5C344 495 396.4 409.7 381.5 322.7c-2.9-17-8.4-32.9-15.9-47.4L480 288h2.6c16.2 0 29.4-13.2 29.4-29.4c0-1.7-.1-3.4-.5-5.1C495 168 409.7 115.6 322.7 130.5c-17 2.9-32.9 8.4-47.4 15.9L288 32V29.4C288 13.2 274.8 0 258.6 0zM256 288c-17.7 0-32-14.3-32-32s14.3-32 32-32s32 14.3 32 32s-14.3 32-32 32z"/>
        </svg>
    </span>

    <div v-else class="d-flex flex-column align-items-center justify-content-center gap-2 py-2 text-primary">
        <svg class="ls-spin" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"
            :style="{ width: iconSize, height: iconSize }">
            <path fill="currentColor" d="M258.6 0c-1.7 0-3.4 .1-5.1 .5C168 17 115.6 102.3 130.5 189.3c2.9 17 8.4 32.9 15.9 47.4L32 224H29.4C13.2 224 0 237.2 0 253.4c0 1.7 .1 3.4 .5 5.1C17 344 102.3 396.4 189.3 381.5c17-2.9 32.9-8.4 47.4-15.9L224 480v2.6c0 16.2 13.2 29.4 29.4 29.4c1.7 0 3.4-.1 5.1-.5C344 495 396.4 409.7 381.5 322.7c-2.9-17-8.4-32.9-15.9-47.4L480 288h2.6c16.2 0 29.4-13.2 29.4-29.4c0-1.7-.1-3.4-.5-5.1C495 168 409.7 115.6 322.7 130.5c-17 2.9-32.9 8.4-47.4 15.9L288 32V29.4C288 13.2 274.8 0 258.6 0zM256 288c-17.7 0-32-14.3-32-32s14.3-32 32-32s32 14.3 32 32s-14.3 32-32 32z"/>
        </svg>
        <span v-if="text" class="text-muted small fw-medium">{{ text }}</span>
    </div>
</template>

<style scoped>
.ls-spin {
    animation: ls-rotate 0.8s linear infinite;
    display: inline-block;
    vertical-align: middle;
}

.ls-inline {
    display: inline-flex;
    align-items: center;
}

@keyframes ls-rotate {
    from { transform: rotate(0deg); }
    to   { transform: rotate(360deg); }
}
</style>

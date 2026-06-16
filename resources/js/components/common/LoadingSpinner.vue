<script setup>
import { computed } from 'vue';

const props = defineProps({
    text: { type: String, default: 'Loading' },
    size: { type: String, default: 'md' },
});

const spinnerClass = computed(() => {
    const map = {
        sm: 'spinner-border-sm',
        md: '',
        lg: 'spinner-border-lg',
    };
    return ['spinner-border', 'text-primary', map[props.size] || ''].filter(Boolean).join(' ');
});
</script>

<template>
    <div class="d-flex flex-column align-items-center justify-content-center gap-2 py-2">
        <div :class="spinnerClass" role="status" aria-hidden="true"></div>
        <span v-if="text" class="text-muted small fw-medium">{{ text }}</span>
    </div>
</template>

<style scoped>
.spinner-border-lg {
    width: 3rem;
    height: 3rem;
}
</style>

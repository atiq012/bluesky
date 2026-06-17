<script setup>
import { computed } from 'vue';

const props = defineProps({
    isOpen: { type: Boolean, default: false },
    title: { type: String, default: '' },
    size: { type: String, default: 'md' },
    maxWidth: { type: String, default: '' },
    maxHeight: { type: String, default: '' },
    showHeader: { type: Boolean, default: true },
    align: { type: String, default: 'center' },
    closeOnBackdrop: { type: Boolean, default: true },
});

defineEmits(['close']);

const dialogClass = computed(() => {
    const map = {
        sm: 'modal-sm',
        md: '',
        lg: 'modal-lg',
        xl: 'modal-xl',
    };
    return ['modal-dialog', map[props.size] || '', props.align === 'top' ? 'modal-dialog-scrollable mt-3' : 'modal-dialog-centered']
        .filter(Boolean)
        .join(' ');
});

const panelStyle = computed(() => {
    const style = {};
    if (props.maxWidth) style.maxWidth = props.maxWidth;
    if (props.maxHeight) style.maxHeight = props.maxHeight;
    return Object.keys(style).length ? style : undefined;
});
</script>

<template>
    <Teleport to="body">
        <Transition name="app-modal-fade">
            <div
                v-if="isOpen"
                class="app-modal-backdrop"
                :class="align === 'top' ? 'app-modal-backdrop-top' : ''"
                @click.self="closeOnBackdrop && $emit('close')"
            >
                <div :class="dialogClass" :style="panelStyle" class="app-modal-dialog">
                    <div class="modal-content app-modal-content">
                        <div v-if="showHeader" class="modal-header app-modal-header">
                            <slot name="header">
                                <h5 class="modal-title app-modal-title">{{ title }}</h5>
                            </slot>
                            <button type="button" class="btn-close" aria-label="Close" @click="$emit('close')"></button>
                        </div>
                        <slot />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.app-modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 1055;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.75rem;
    background: rgba(0, 0, 0, 0.5);
    overflow: hidden;
}

.app-modal-backdrop-top {
    align-items: flex-start;
    padding-top: 1.5rem;
    overflow-y: auto;
}

.app-modal-dialog {
    width: 100%;
    margin: 0;
    max-height: calc(100vh - 1.5rem);
}

.app-modal-content {
    display: flex;
    flex-direction: column;
    max-height: calc(100vh - 1.5rem);
    overflow: hidden;
    border-radius: 0.5rem;
    box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.2);
    background-color: #fff;
    border: 1px solid #e2e8f0;
}

.app-modal-header {
    flex-shrink: 0;
    padding: 0.65rem 1rem;
    background-color: #fff;
    border-bottom: 1px solid #e2e8f0;
}

.app-modal-title {
    font-size: 1rem;
    font-weight: 600;
    line-height: 1.3;
}

.app-modal-fade-enter-active,
.app-modal-fade-leave-active {
    transition: opacity 0.2s ease;
}

.app-modal-fade-enter-from,
.app-modal-fade-leave-to {
    opacity: 0;
}
</style>

<style>
[data-bs-theme="dark"] .app-modal-content {
    background-color: #2b3035;
    color: #dee2e6;
    border-color: #495057;
}

[data-bs-theme="dark"] .app-modal-header {
    border-color: #495057;
}
</style>

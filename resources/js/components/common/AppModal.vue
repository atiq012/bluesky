<script setup>
import { computed, useSlots } from 'vue';

const props = defineProps({
    isOpen: { type: Boolean, default: false },
    title: { type: String, default: '' },
    size: { type: String, default: 'md' },
    maxWidth: { type: String, default: '' },
    maxHeight: { type: String, default: '' },
    showHeader: { type: Boolean, default: true },
    align: { type: String, default: 'center' },
    closeOnBackdrop: { type: Boolean, default: true },
    backdropOpacity: { type: Number, default: 0.5 },
});

defineEmits(['close']);

const slots = useSlots();
const hasBodySlot = computed(() => !!slots.body);
const hasFooterSlot = computed(() => !!slots.footer);

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
                :style="{ background: `rgba(0, 0, 0, ${backdropOpacity})` }"
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

                        <!-- Named body/footer → consistent padding; default slot keeps legacy layouts -->
                        <template v-if="hasBodySlot || hasFooterSlot">
                            <div v-if="hasBodySlot" class="app-modal-body">
                                <slot name="body" />
                            </div>
                            <div v-if="hasFooterSlot" class="app-modal-footer">
                                <slot name="footer" />
                            </div>
                        </template>
                        <slot v-else />
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
    background-color: #ffffff;
}

.app-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
    padding: 0.75rem 1.25rem;
    border-bottom: 1px solid var(--bs-border-color, #dee2e6);
}

.app-modal-title {
    font-size: 1rem;
    font-weight: 600;
    line-height: 1.3;
    margin: 0;
}

.app-modal-body {
    flex: 1 1 auto;
    padding: 1.25rem 1.25rem;
    overflow-y: auto;
    min-height: 0;
}

.app-modal-footer {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 0.75rem;
    flex-shrink: 0;
    padding: 0.875rem 1.25rem;
    border-top: 1px solid var(--bs-border-color, #dee2e6);
    background-color: transparent;
}

.app-modal-footer :deep(.btn) {
    margin: 0;
    min-height: 2.125rem;
    min-width: 5.5rem;
    padding: 0.4rem 1rem;
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

[data-bs-theme="dark"] .app-modal-header,
[data-bs-theme="dark"] .app-modal-footer {
    border-color: #495057;
}
</style>

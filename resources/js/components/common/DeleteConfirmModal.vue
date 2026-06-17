<script setup>
import { computed } from 'vue';
import AppButton from './AppButton.vue';
import AppModal from './AppModal.vue';

const ICON_VARIANTS = {
    delete: { bgClass: 'bg-danger-subtle', textClass: 'text-danger' },
    add: { bgClass: 'bg-primary-subtle', textClass: 'text-primary' },
    confirm: { bgClass: 'bg-success-subtle', textClass: 'text-success' },
};

const props = defineProps({
    isOpen: { type: Boolean, default: false },
    title: { type: String, default: 'Delete' },
    itemName: { type: String, default: '' },
    message: { type: String, default: '' },
    loading: { type: Boolean, default: false },
    confirmLabel: { type: String, default: 'Delete' },
    confirmVariant: { type: String, default: 'delete' },
    loadingText: { type: String, default: 'Deleting...' },
    iconVariant: { type: String, default: 'delete' },
    iconBgClass: { type: String, default: '' },
    iconTextClass: { type: String, default: '' },
});

const emit = defineEmits(['close', 'confirm']);

const displayMessage = computed(() => {
    if (props.message) return props.message;
    return props.itemName
        ? `Are you sure you want to delete "<strong>${props.itemName}</strong>"? This action cannot be undone.`
        : 'Are you sure you want to delete this? This action cannot be undone.';
});

const iconStyles = computed(() => {
    const preset = ICON_VARIANTS[props.iconVariant] || ICON_VARIANTS.delete;
    return {
        bgClass: props.iconBgClass || preset.bgClass,
        textClass: props.iconTextClass || preset.textClass,
    };
});

function close() {
    emit('close');
}

function onConfirm() {
    emit('confirm');
}
</script>

<template>
    <AppModal :is-open="isOpen" :show-header="false" size="sm" max-width="420px" @close="close">
        <div class="modal-body p-4">
            <div class="d-flex align-items-center gap-3 mb-2">
                <span
                    class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0 delete-confirm-icon"
                    :class="iconStyles.bgClass"
                >
                    <i
                        v-if="iconVariant === 'delete'"
                        class="fa-solid fa-trash"
                        :class="iconStyles.textClass"
                        aria-hidden="true"
                    ></i>
                    <i
                        v-else-if="iconVariant === 'add'"
                        class="fa-solid fa-user-plus"
                        :class="iconStyles.textClass"
                        aria-hidden="true"
                    ></i>
                    <i
                        v-else
                        class="fa-solid fa-check"
                        :class="iconStyles.textClass"
                        aria-hidden="true"
                    ></i>
                </span>
                <h5 class="modal-title mb-0 fw-semibold">{{ title }}</h5>
            </div>
            <p class="text-muted mb-4" v-html="displayMessage"></p>
            <div class="d-flex gap-2">
                <AppButton variant="cancel" :block="true" @click="close" />
                <AppButton
                    :variant="confirmVariant"
                    :label="confirmLabel"
                    :loading="loading"
                    :loading-text="loadingText"
                    :block="true"
                    @click="onConfirm"
                />
            </div>
        </div>
    </AppModal>
</template>

<style scoped>
.delete-confirm-icon {
    width: 2.5rem;
    height: 2.5rem;
    font-size: 1rem;
}
</style>

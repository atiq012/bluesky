<script setup>
import AppTooltip from './AppTooltip.vue';

defineProps({
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    icon: { type: String, required: true },
    btnClass: { type: String, default: '' },
    tooltip: { type: String, default: '' },
});

defineEmits(['click']);
</script>

<template>
    <AppTooltip :content="tooltip" placement="top">
        <span class="action-icon-btn-wrap">
            <button
                type="button"
                class="action-icon-btn"
                :class="btnClass"
                :disabled="loading || disabled"
                @click="$emit('click')"
            >
                <span v-if="loading" class="action-icon-btn__content" role="status" aria-hidden="true">
                    <span class="spinner-border spinner-border-sm"></span>
                </span>
                <span v-else class="action-icon-btn__content" aria-hidden="true">
                    <i :class="icon"></i>
                </span>
            </button>
        </span>
    </AppTooltip>
</template>

<style scoped>
.action-icon-btn-wrap {
    display: inline-flex;
    vertical-align: middle;
}

.action-icon-btn {
    appearance: none;
    box-sizing: border-box;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    min-width: 30px;
    min-height: 30px;
    margin: 0;
    padding: 0;
    border-radius: 50%;
    border: 1px solid var(--action-btn-color, #94a3b8);
    color: var(--action-btn-color, #94a3b8);
    background-color: var(--action-btn-bg, #f8fafc);
    line-height: 0;
    cursor: pointer;
    transition: background-color 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}

.action-icon-btn__content {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 14px;
    height: 14px;
    line-height: 0;
    pointer-events: none;
}

.action-icon-btn__content i {
    display: block;
    font-size: 13px;
    line-height: 1;
}

.action-icon-btn__content i::before {
    display: block;
    line-height: 1;
}

.action-icon-btn:hover:not(:disabled) {
    color: #fff;
    background-color: var(--action-btn-color, #94a3b8);
    border-color: var(--action-btn-color, #94a3b8);
}

.action-icon-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.action-icon-btn .spinner-border {
    width: 0.85rem;
    height: 0.85rem;
    color: var(--action-btn-color, #94a3b8);
}
</style>

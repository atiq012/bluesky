<script setup>
import { computed } from 'vue';
import LoadingSpinner from './LoadingSpinner.vue';

const VARIANTS = {
    save:     { label: 'Save',         style: 'primary'   },
    create:   { label: 'Create',       style: 'success'   },
    edit:     { label: 'Edit',         style: 'warning'   },
    update:   { label: 'Update',       style: 'primary'   },
    close:    { label: 'Close',        style: 'secondary' },
    view:     { label: 'View',         style: 'info'      },
    cancel:   { label: 'Cancel',       style: 'cancel'    },
    export:   { label: 'Export',       style: 'primary'   },
    browse:   { label: 'Browse',       style: 'outline'   },
    print:    { label: 'Print',        style: 'outline'   },
    approved: { label: 'Approved',     style: 'success'   },
    reject:   { label: 'Reject',       style: 'danger'    },
    delete:   { label: 'Delete',       style: 'danger'    },
    return:   { label: 'Return',       style: 'warning'   },
    confirm:  { label: 'Yes, Confirm', style: 'success'   },
    keep:     { label: 'No, Keep It',         style: 'keep' },
    void:     { label: 'Yes, Cancel', style: 'void' },
    default:  { label: 'Button',       style: 'secondary' },
};

const ICON_CLASSES = {
    save: 'fa-solid fa-floppy-disk',
    create: 'fa-solid fa-plus',
    edit: 'fa-solid fa-pencil',
    update: 'fa-solid fa-arrows-rotate',
    close: 'fa-solid fa-xmark',
    view: 'fa-solid fa-eye',
    cancel: 'fa-solid fa-xmark',
    export: 'fa-solid fa-download',
    browse: 'fa-solid fa-folder-open',
    print: 'fa-solid fa-print',
    approved: 'fa-solid fa-circle-check',
    reject: 'fa-solid fa-xmark',
    delete: 'fa-solid fa-trash',
    confirm: 'fa-solid fa-check',
    return: 'fa-solid fa-arrow-rotate-left',
    keep: 'fa-solid fa-arrow-left',
    void: 'fa-solid fa-ban',
    default: 'fa-solid fa-circle',
};

const props = defineProps({
    variant: { type: String, default: 'default' },
    label: { type: String, default: '' },
    loading: { type: Boolean, default: false },
    loadingText: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    type: { type: String, default: 'button' },
    size: { type: String, default: 'sm' },
    block: { type: Boolean, default: false },
    customClass: { type: String, default: '' },
    title: { type: String, default: '' },
});

const emit = defineEmits(['click']);

const variantConfig = computed(() => VARIANTS[props.variant] || VARIANTS.default);
const displayLabel = computed(() => props.label || variantConfig.value.label);
const loadingDisplayText = computed(() => props.loadingText || `${displayLabel.value}...`);
const iconClass = computed(() => ICON_CLASSES[props.variant] || ICON_CLASSES.default);
const spinnerSize = computed(() => (props.size === 'lg' ? 'md' : 'sm'));

const buttonClass = computed(() => {
    const style = variantConfig.value.style;
    const sizeCls = props.size === 'lg' ? 'btn-lg' : props.size === 'md' ? '' : 'btn-sm';
    const styleCls = style === 'outline'
        ? 'btn btn-outline-secondary'
        : style === 'cancel'
            ? 'btn btn-outline-danger'
            : style === 'keep' || style === 'void'
                ? `btn btn-${style}`
                : `btn btn-${style}`;
    const blockCls = props.block ? 'w-100' : '';
    return [styleCls, sizeCls, 'd-inline-flex align-items-center justify-content-center gap-2', blockCls, props.customClass]
        .filter(Boolean)
        .join(' ');
});

function onClick(e) {
    if (props.loading || props.disabled) return;
    emit('click', e);
}
</script>

<template>
    <button
        :type="type"
        :class="buttonClass"
        :disabled="loading || disabled"
        :title="title || displayLabel"
        @click="onClick"
    >
        <span v-if="loading" role="status" aria-hidden="true">
            <LoadingSpinner :inline="true" :size="spinnerSize" />
        </span>
        <slot v-else-if="$slots.icon" name="icon" />
        <i
            v-else
            :class="iconClass"
            aria-hidden="true"
        ></i>
        <span v-if="loading">{{ loadingDisplayText }}</span>
        <slot v-else>{{ displayLabel }}</slot>
    </button>
</template>

<style scoped>
.btn-keep {
    background: #f1f5f9;
    color: #475569;
    border: 1.5px solid #e2e8f0;
    font-weight: 600;
    font-size: 0.875rem;
    padding: 0.65rem 1.25rem;
    white-space: nowrap;
    border-radius: 0.6rem;
    transition: background 0.15s, border-color 0.15s, color 0.15s;
}

.btn-keep:hover:not(:disabled):not(.disabled) {
    background: #e2e8f0;
    border-color: #cbd5e1;
    color: #1e293b;
}

.btn-void {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    color: #fff;
    border: none;
    font-weight: 700;
    font-size: 0.875rem;
    padding: 0.65rem 1.25rem;
    white-space: nowrap;
    border-radius: 0.6rem;
    box-shadow: 0 4px 14px rgba(220, 38, 38, 0.32);
    transition: background 0.18s, box-shadow 0.18s, transform 0.18s;
}

.btn-void:hover:not(:disabled):not(.disabled) {
    background: linear-gradient(135deg, #b91c1c 0%, #991b1b 100%);
    color: #fff;
    box-shadow: 0 6px 20px rgba(220, 38, 38, 0.45);
    transform: translateY(-1px);
}

.btn-void:active:not(:disabled):not(.disabled) {
    transform: translateY(0);
    box-shadow: 0 2px 8px rgba(220, 38, 38, 0.3);
}

.btn-void:disabled,
.btn-void.disabled {
    opacity: 0.65;
}
</style>

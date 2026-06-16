<script setup>
import { computed } from 'vue';

const VARIANTS = {
    save:     { label: 'Save',         style: 'primary'   },
    create:   { label: 'Create',       style: 'success'   },
    edit:     { label: 'Edit',         style: 'warning'   },
    update:   { label: 'Update',       style: 'primary'   },
    close:    { label: 'Close',        style: 'secondary' },
    view:     { label: 'View',         style: 'info'      },
    cancel:   { label: 'Cancel',       style: 'secondary' },
    export:   { label: 'Export',       style: 'primary'   },
    browse:   { label: 'Browse',       style: 'outline'   },
    print:    { label: 'Print',        style: 'outline'   },
    approved: { label: 'Approved',     style: 'success'   },
    reject:   { label: 'Reject',       style: 'danger'    },
    delete:   { label: 'Delete',       style: 'danger'    },
    return:   { label: 'Return',       style: 'warning'   },
    confirm:  { label: 'Yes, Confirm', style: 'success'   },
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

const buttonClass = computed(() => {
    const style = variantConfig.value.style;
    const sizeCls = props.size === 'lg' ? 'btn-lg' : props.size === 'md' ? '' : 'btn-sm';
    const styleCls = style === 'outline'
        ? 'btn btn-outline-secondary'
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
        <span v-if="loading" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
        <slot v-else-if="$slots.icon" name="icon" />
        <i
            v-else
            :class="[iconClass, variant === 'cancel' ? 'text-danger' : '']"
            aria-hidden="true"
        ></i>
        <span v-if="loading">{{ loadingDisplayText }}</span>
        <slot v-else>{{ displayLabel }}</slot>
    </button>
</template>

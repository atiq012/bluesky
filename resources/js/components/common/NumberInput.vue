<script setup>
import { ref, watch, computed } from 'vue';
import {
    formatNumberWithCommas,
    sanitizeNumericString,
    stripNumberCommas,
} from '../../utils/numberFormat';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    decimalPlaces: { type: Number, default: 2 },
    disabled: { type: Boolean, default: false },
    placeholder: { type: String, default: '' },
    inputClass: { type: String, default: '' },
    bare: { type: Boolean, default: false },
    error: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const focused = ref(false);
const displayValue = ref('');

function modelRaw() {
    const v = props.modelValue;
    if (v === null || v === undefined || v === '') return '';
    return stripNumberCommas(v);
}

function commitValue(raw, inputEl = null, alwaysEmit = false) {
    const sanitized = sanitizeNumericString(raw, props.decimalPlaces);
    const formatted = formatNumberWithCommas(sanitized, props.decimalPlaces);
    displayValue.value = formatted;
    if (inputEl && inputEl.value !== formatted) {
        inputEl.value = formatted;
    }
    if (alwaysEmit || sanitized !== modelRaw()) {
        emit('update:modelValue', sanitized);
    }
    return sanitized;
}

watch(
    () => [props.modelValue, props.decimalPlaces],
    () => {
        const sanitized = sanitizeNumericString(modelRaw(), props.decimalPlaces);
        if (sanitized !== modelRaw()) {
            emit('update:modelValue', sanitized);
        }
        if (!focused.value) {
            displayValue.value = formatNumberWithCommas(sanitized, props.decimalPlaces);
        }
    },
    { immediate: true },
);

const mergedClass = computed(() => {
    const parts = [];
    if (!props.bare) {
        parts.push('form-control', 'form-control-sm');
        if (props.error) parts.push('is-invalid');
    }
    if (props.inputClass) parts.push(props.inputClass);
    if (props.bare && props.error) parts.push('is-invalid');
    return parts.join(' ');
});

function onFocus() {
    focused.value = true;
    displayValue.value = formatNumberWithCommas(modelRaw(), props.decimalPlaces);
}

function onInput(e) {
    commitValue(e.target.value, e.target, true);
}

function onBlur(e) {
    focused.value = false;
    let raw = sanitizeNumericString(modelRaw(), props.decimalPlaces);
    if (raw.endsWith('.')) raw = raw.slice(0, -1);
    commitValue(raw, e.target);
}

const CONTROL_KEYS = new Set([
    'Backspace', 'Delete', 'Tab', 'Escape', 'Enter',
    'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End',
]);

function onKeydown(e) {
    if (e.ctrlKey || e.metaKey || e.altKey) return;
    if (CONTROL_KEYS.has(e.key)) return;
    if (e.key >= '0' && e.key <= '9') return;
    if (
        props.decimalPlaces > 0
        && (e.key === '.' || e.key === 'Decimal')
        && !String(modelRaw()).includes('.')
        && !String(e.target?.value ?? '').replace(/,/g, '').includes('.')
    ) {
        return;
    }
    e.preventDefault();
}

function onPaste(e) {
    e.preventDefault();
    const pasted = e.clipboardData?.getData('text') ?? '';
    commitValue(pasted, e.target, true);
}
</script>

<template>
    <input
        :value="displayValue"
        type="text"
        inputmode="decimal"
        autocomplete="off"
        :placeholder="placeholder"
        :disabled="disabled"
        :class="mergedClass"
        @focus="onFocus"
        @keydown="onKeydown"
        @paste="onPaste"
        @input="onInput"
        @blur="onBlur"
    />
</template>

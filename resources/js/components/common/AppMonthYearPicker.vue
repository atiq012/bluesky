<script setup>
import { ref, watch, computed } from 'vue';
import VueDatePicker from '@vuepic/vue-datepicker';
import { storeToRefs } from 'pinia';
import { useAuthStore } from '../../stores/authStore';
import '@vuepic/vue-datepicker/dist/main.css';

const MODEL_FORMAT = 'MMM-yyyy';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Select month' },
    disabled: { type: Boolean, default: false },
    clearable: { type: Boolean, default: true },
    inputClass: { type: String, default: 'form-control form-control-sm' },
});

const emit = defineEmits(['update:modelValue']);

const internalModel = ref('');

watch(
    () => props.modelValue,
    (v) => {
        internalModel.value = v || '';
    },
    { immediate: true },
);

function onInternalUpdate(val) {
    emit('update:modelValue', val || '');
}

const formats = computed(() => ({
    input: MODEL_FORMAT,
}));

const authStore = useAuthStore();
const { isDarkMode } = storeToRefs(authStore);
</script>

<template>
    <VueDatePicker
        v-model="internalModel"
        month-picker
        :model-type="MODEL_FORMAT"
        :placeholder="placeholder"
        teleport
        :disabled="disabled"
        :clearable="clearable"
        :auto-apply="true"
        :dark="isDarkMode"
        :formats="formats"
        :input-class-name="inputClass"
        @update:model-value="onInternalUpdate"
    />
</template>

<style>
.app-month-year-picker .dp__input {
    padding-left: 2.5rem;
}

[data-bs-theme="dark"] .dp__theme_light,
[data-bs-theme="dark"] .dp__theme_dark,
[data-bs-theme="dark"] .dp--menu-wrapper,
[data-bs-theme="dark"] .dp__menu {
    --dp-background-color: #2b3035;
    --dp-text-color: #dee2e6;
    --dp-hover-color: #343a40;
    --dp-hover-text-color: #dee2e6;
    --dp-hover-icon-color: #adb5bd;
    --dp-primary-color: var(--bs-primary);
    --dp-primary-disabled-color: #6c757d;
    --dp-primary-text-color: #fff;
    --dp-secondary-color: #adb5bd;
    --dp-border-color: #495057;
    --dp-menu-border-color: #495057;
    --dp-border-color-hover: #6c757d;
    --dp-border-color-focus: #6c757d;
    --dp-disabled-color: #495057;
    --dp-disabled-color-text: #adb5bd;
    --dp-scroll-bar-background: #2b3035;
    --dp-scroll-bar-color: #495057;
    --dp-icon-color: #adb5bd;
    --dp-danger-color: #dc3545;
    --dp-marker-color: #dc3545;
    --dp-tooltip-color: #212529;
    --dp-highlight-color: rgba(var(--bs-primary-rgb), 0.2);
}

[data-bs-theme="dark"] .dp__input {
    background-color: #2b3035 !important;
    border-color: #495057 !important;
    color: #dee2e6 !important;
}
</style>

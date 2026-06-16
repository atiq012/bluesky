<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, default: 'Email' },
    required: { type: Boolean, default: false },
    placeholder: { type: String, default: 'example@email.com' },
    error: { type: String, default: '' },
    inputClass: { type: String, default: 'form-control form-control-sm' },
    inputStyle: { type: [String, Object], default: '' },
    showLabel: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue']);

const touched = ref(false);
const localError = ref('');

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

function validate(val) {
    if (!val) return props.required ? 'Email is required.' : '';
    if (!EMAIL_RE.test(val)) return 'Enter a valid email address.';
    return '';
}

function onInput(e) {
    const val = e.target.value;
    emit('update:modelValue', val);
    if (touched.value) localError.value = validate(val);
}

function onBlur() {
    touched.value = true;
    localError.value = validate(props.modelValue);
}

const displayError = computed(() => props.error || localError.value);
const hasError = computed(() => !!displayError.value);
</script>

<template>
    <div>
        <label v-if="showLabel && label" class="form-label small mb-1">
            {{ label }}<span v-if="required" class="text-danger ms-1">*</span>
        </label>
        <input
            :value="modelValue"
            type="email"
            :placeholder="placeholder"
            autocomplete="email"
            :class="[inputClass, hasError ? 'is-invalid' : '']"
            :style="inputStyle"
            @input="onInput"
            @blur="onBlur"
        />
        <Transition name="field-error">
            <div v-if="displayError" class="invalid-feedback d-block">
                {{ displayError }}
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.field-error-enter-active,
.field-error-leave-active {
    transition: opacity 0.22s ease, transform 0.22s ease, max-height 0.25s ease;
    overflow: hidden;
    max-height: 40px;
}

.field-error-enter-from,
.field-error-leave-to {
    opacity: 0;
    transform: translateY(-5px);
    max-height: 0;
}
</style>

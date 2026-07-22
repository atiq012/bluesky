<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, default: 'NID Number' },
    required: { type: Boolean, default: false },
    placeholder: { type: String, default: 'Enter NID Number' },
    error: { type: String, default: '' },
    inputClass: { type: String, default: 'form-control' },
    inputStyle: { type: [String, Object], default: '' },
    showLabel: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue']);

const touched = ref(false);
const localError = ref('');

// Validates 10, 13, or 17 digit Bangladeshi NID numbers
function validate(val) {
    if (!val) return props.required ? 'NID number is required.' : '';
    const digitsOnly = /^\d+$/;
    if (!digitsOnly.test(val)) return 'NID must contain numbers only.';
    if (![10, 13, 17].includes(val.length)) return 'NID must be 10, 13, or 17 digits.';
    return '';
}

function onInput(e) {
    // Restrict input to digits only
    const val = e.target.value.replace(/\D/g, '');
    e.target.value = val;
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
        <label v-if="showLabel && label" class="form-label">
            {{ label }}<span v-if="required" class="text-danger ms-1">*</span>
        </label>
        <input
            :value="modelValue"
            type="text"
            inputmode="numeric"
            :placeholder="placeholder"
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
.form-label {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: .8rem;
    font-weight: 600;
    color: #475569;
    margin-bottom: 6px;
    letter-spacing: .02em;
}
</style>


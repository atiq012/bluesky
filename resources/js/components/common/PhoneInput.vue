<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';

const COUNTRIES = [
    { code: 'bd', name: 'Bangladesh',     dial: '+880', maxLen: 11, placeholder: '01XXXXXXXXX',  pattern: /^01[3-9]\d{8}$/,  hint: 'Format: 01X XXXXXXXX (11 digits)' },
    { code: 'us', name: 'United States',  dial: '+1',   maxLen: 10, placeholder: 'XXXXXXXXXX',   pattern: /^\d{10}$/,         hint: 'Format: 10 digits' },
    { code: 'gb', name: 'United Kingdom', dial: '+44',  maxLen: 11, placeholder: '07XXXXXXXXX',  pattern: /^0\d{9,10}$/,      hint: 'Format: 07XXX XXXXXX (10-11 digits)' },
    { code: 'in', name: 'India',          dial: '+91',  maxLen: 10, placeholder: 'XXXXXXXXXX',   pattern: /^[6-9]\d{9}$/,     hint: 'Format: 10 digits starting 6-9' },
    { code: 'ae', name: 'UAE',            dial: '+971', maxLen: 9,  placeholder: '5XXXXXXXX',    pattern: /^5\d{8}$/,         hint: 'Format: 5X XXX XXXX (9 digits)' },
    { code: 'sa', name: 'Saudi Arabia',   dial: '+966', maxLen: 9,  placeholder: '5XXXXXXXX',    pattern: /^5\d{8}$/,         hint: 'Format: 5X XXXX XXXX (9 digits)' },
    { code: 'sg', name: 'Singapore',      dial: '+65',  maxLen: 8,  placeholder: 'XXXXXXXX',     pattern: /^[689]\d{7}$/,     hint: 'Format: 8 digits starting 6, 8, or 9' },
    { code: 'my', name: 'Malaysia',       dial: '+60',  maxLen: 10, placeholder: '1XXXXXXXXX',   pattern: /^1\d{8,9}$/,       hint: 'Format: 1X XXXXXXXX (9-10 digits)' },
];

const props = defineProps({
    modelValue: { type: String, default: '' },
    dialCode:   { type: String, default: '+880' },
    label:      { type: String, default: 'Phone' },
    required:   { type: Boolean, default: false },
    error:      { type: String, default: '' },
    inputStyle: { type: [String, Object], default: '' },
    showLabel:  { type: Boolean, default: true },
    disabled:   { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'update:dialCode']);

const dropdownOpen   = ref(false);
const touched        = ref(false);
const localError     = ref('');
const containerRef   = ref(null);
const dropdownStyle  = ref({ top: '0px', left: '0px', minWidth: '0px' });

const selectedCountry = computed(
    () => COUNTRIES.find(c => c.dial === props.dialCode) ?? COUNTRIES[0]
);

const flagUrl = computed(
    () => `https://flagcdn.com/w40/${selectedCountry.value.code}.png`
);

function validate(val) {
    if (!val) return props.required ? 'Phone number is required.' : '';
    if (!selectedCountry.value.pattern.test(val))
        return `Invalid number. ${selectedCountry.value.hint}.`;
    return '';
}

function onInput(e) {
    const raw = e.target.value.replace(/\D/g, '').slice(0, selectedCountry.value.maxLen);
    e.target.value = raw;
    emit('update:modelValue', raw);
    if (touched.value) localError.value = validate(raw);
}

function onBlur() {
    touched.value = true;
    localError.value = validate(props.modelValue);
}

function openDropdown() {
    if (props.disabled) return;
    const rect = containerRef.value?.getBoundingClientRect();
    if (rect) {
        dropdownStyle.value = {
            top:      `${rect.bottom + 4}px`,
            left:     `${rect.left}px`,
            minWidth: `${rect.width}px`,
        };
    }
    dropdownOpen.value = true;
}

function selectCountry(c) {
    emit('update:dialCode', c.dial);
    emit('update:modelValue', '');
    localError.value = '';
    touched.value = false;
    dropdownOpen.value = false;
}

function onOutsideClick(e) {
    if (containerRef.value && !containerRef.value.contains(e.target))
        dropdownOpen.value = false;
}

watch(() => props.dialCode, () => {
    localError.value = '';
    touched.value = false;
});

onMounted(() => document.addEventListener('click', onOutsideClick));
onUnmounted(() => document.removeEventListener('click', onOutsideClick));

const displayError = computed(() => props.error || localError.value);
const hasError     = computed(() => !!displayError.value);
</script>

<template>
    <div ref="containerRef">
        <label v-if="showLabel && label" class="form-label small mb-1">
            {{ label }}<span v-if="required" class="text-danger ms-1">*</span>
        </label>

        <div class="phone-wrap" :class="{ 'phone-wrap--error': hasError, 'phone-wrap--disabled': disabled }">

            <!-- Country selector trigger -->
            <button
                type="button"
                class="phone-flag-btn"
                :disabled="disabled"
                @click.stop="openDropdown"
            >
                <img :src="flagUrl" :alt="selectedCountry.code" class="phone-flag-img" />
                <span class="phone-dial">{{ selectedCountry.dial }}</span>
                <svg class="phone-chevron" :class="{ open: dropdownOpen }" width="12" height="12" viewBox="0 0 12 12">
                    <path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>

            <div class="phone-divider"></div>

            <!-- Number input -->
            <input
                :value="modelValue"
                type="tel"
                inputmode="numeric"
                :placeholder="selectedCountry.placeholder"
                :maxlength="selectedCountry.maxLen"
                autocomplete="tel"
                :disabled="disabled"
                class="phone-input"
                :style="inputStyle"
                @input="onInput"
                @blur="onBlur"
            />
        </div>

        <!-- Country dropdown — teleported to body so it escapes overflow:hidden parents -->
        <Teleport to="body">
            <Transition name="phone-dd">
                <div v-if="dropdownOpen" class="phone-dropdown" :style="dropdownStyle">
                    <button
                        v-for="c in COUNTRIES"
                        :key="c.dial"
                        type="button"
                        class="phone-dd-item"
                        :class="{ active: c.dial === selectedCountry.dial }"
                        @click.stop="selectCountry(c)"
                    >
                        <img :src="`https://flagcdn.com/w40/${c.code}.png`" :alt="c.code" class="phone-flag-img" />
                        <span class="phone-dd-name">{{ c.name }}</span>
                        <span class="phone-dd-dial">{{ c.dial }}</span>
                    </button>
                </div>
            </Transition>
        </Teleport>

        <!-- Validation message -->
        <Transition name="field-error">
            <div v-if="displayError" class="invalid-feedback d-block">{{ displayError }}</div>
        </Transition>
    </div>
</template>

<style scoped>
.phone-wrap {
    display: flex;
    align-items: stretch;
    border: 1.5px solid #CBD5E1;
    border-radius: 10px;
    overflow: hidden;
    background: #FAFBFC;
    transition: border-color .2s, box-shadow .2s;
}

.phone-wrap:focus-within {
    border-color: #2563EB;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .15);
}

.phone-wrap--error {
    border-color: #EF4444 !important;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, .12) !important;
}

.phone-wrap--disabled {
    opacity: .6;
    pointer-events: none;
}

/* Flag / dial button */
.phone-flag-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 0 12px;
    min-height: 42px;
    background: #F1F5F9;
    border: none;
    cursor: pointer;
    white-space: nowrap;
    color: #475569;
    font-weight: 600;
    flex-shrink: 0;
    transition: background .15s;
}

.phone-flag-btn:hover {
    background: #E8EEF7;
}

.phone-flag-img {
    width: 20px;
    height: 14px;
    border-radius: 2px;
    object-fit: cover;
    flex-shrink: 0;
}

.phone-dial {
    font-size: .82rem;
}

.phone-chevron {
    color: #94A3B8;
    transition: transform .2s;
    flex-shrink: 0;
}
.phone-chevron.open { transform: rotate(180deg); }

.phone-divider {
    width: 1.5px;
    background: #E2E8F0;
    align-self: stretch;
    flex-shrink: 0;
}

/* Phone number input */
.phone-input {
    flex: 1;
    border: none;
    background: transparent;
    padding: 10px 14px;
    font-size: .9rem;
    color: #1E293B;
    outline: none;
    min-width: 0;
}
.phone-input::placeholder { color: #CBD5E1; }

/* Dropdown panel */
.phone-dropdown {
    position: fixed;
    z-index: 9999;
    background: #fff;
    border: 1.5px solid #E2E8F0;
    border-radius: 10px;
    box-shadow: 0 8px 24px rgba(30, 41, 59, .12);
    min-width: 240px;
    max-height: 280px;
    overflow-y: auto;
    padding: 4px;
}

.phone-dd-item {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 8px 12px;
    border: none;
    background: transparent;
    border-radius: 6px;
    cursor: pointer;
    text-align: left;
    font-size: .85rem;
    color: #334155;
    transition: background .12s;
}
.phone-dd-item:hover { background: #F1F5F9; }
.phone-dd-item.active {
    background: #EFF6FF;
    color: #2563EB;
    font-weight: 600;
}

.phone-dd-name { flex: 1; }

.phone-dd-dial {
    font-size: .8rem;
    color: #94A3B8;
    font-weight: 500;
}
.phone-dd-item.active .phone-dd-dial { color: #60A5FA; }

/* Transitions */
.phone-dd-enter-active,
.phone-dd-leave-active { transition: opacity .15s ease, transform .15s ease; }
.phone-dd-enter-from,
.phone-dd-leave-to { opacity: 0; transform: translateY(-6px); }

.field-error-enter-active,
.field-error-leave-active {
    transition: opacity .22s ease, transform .22s ease, max-height .25s ease;
    overflow: hidden;
    max-height: 40px;
}
.field-error-enter-from,
.field-error-leave-to { opacity: 0; transform: translateY(-5px); max-height: 0; }
</style>

<script setup>
import { ref, computed } from 'vue';
import SearchInput from './SearchInput.vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    options: { type: Array, default: () => [] },
    label: { type: String, default: '' },
    star: { type: String, default: '' },
    entityName: { type: String, default: 'item' },
    disabled: { type: Boolean, default: false },
    blockedOptions: { type: Array, default: () => [] },
    placeholder: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const pickerOpen = ref(false);
const pickerSearch = ref('');
const draft = ref('');

const blockedSet = computed(() => new Set(props.blockedOptions.filter(Boolean)));

function isOptionBlocked(opt) {
    return blockedSet.value.has(opt);
}

const selectableOptions = computed(() => props.options.filter((opt) => !isOptionBlocked(opt)));

const filteredPickerOptions = computed(() => {
    const q = pickerSearch.value.trim().toLowerCase();
    if (!q) return props.options;
    return props.options.filter((item) => item.toLowerCase().includes(q));
});

const hasSelection = computed(() => !!props.modelValue);

const displayValue = computed(() => props.modelValue || '');

function openPicker() {
    if (props.disabled) return;
    pickerSearch.value = '';
    draft.value = props.modelValue;
    pickerOpen.value = true;
}

function closePicker() {
    pickerOpen.value = false;
}

function isDraftSelected(opt) {
    return draft.value === opt;
}

function selectDraftItem(opt) {
    if (isOptionBlocked(opt)) return;
    draft.value = opt;
}

function applyPicker() {
    emit('update:modelValue', draft.value);
    closePicker();
}
</script>

<template>
    <div class="single-picker">
        <label v-if="label" class="field-label mb-1">{{ label }} <span v-if="star" class="required-star">{{ star }}</span></label>

        <div class="single-picker-box" :class="{ 'single-picker-box--empty': !hasSelection }">
            <template v-if="!hasSelection">
                <span class="single-picker-placeholder">
                    {{ placeholder || `No ${entityName} selected` }}
                </span>
            </template>

            <template v-else>
                <span class="single-picker-value">{{ displayValue }}</span>
            </template>

            <div class="single-picker-actions">
                <button
                    v-if="hasSelection"
                    type="button"
                    class="single-btn single-btn--ghost"
                    :disabled="disabled"
                    @click="emit('update:modelValue', '')"
                >
                    Clear
                </button>
                <button
                    type="button"
                    class="single-btn single-btn--primary"
                    :disabled="disabled"
                    @click="openPicker"
                >
                    Select
                </button>
            </div>
        </div>

        <!-- Picker modal -->
        <Teleport to="body">
            <div v-if="pickerOpen" class="single-overlay" @click.self="closePicker">
                <div class="single-modal">
                    <div class="single-modal-header">
                        <span class="single-modal-title">
                            <i class="fa-solid fa-pen"></i>
                            {{ label || `Select ${entityName}` }}
                        </span>
                        <button type="button" class="single-modal-close" @click="closePicker">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <div class="single-modal-search">
                        <SearchInput
                            v-model="pickerSearch"
                            :placeholder="`Search ${entityName}...`"
                            wrapper-class="w-100"
                        />
                    </div>

                    <div class="single-modal-body">
                        <label
                            v-for="opt in filteredPickerOptions"
                            :key="opt"
                            class="single-option"
                            :class="{
                                'single-option--on': isDraftSelected(opt),
                                'single-option--blocked': isOptionBlocked(opt),
                            }"
                            @click.prevent="selectDraftItem(opt)"
                        >
                            <span class="single-option-radio">
                                <span v-if="isDraftSelected(opt)" class="single-option-dot"></span>
                            </span>
                            <span>{{ opt }}</span>
                            <span v-if="isOptionBlocked(opt)" class="single-option-hint">In other list</span>
                        </label>
                        <div v-if="!filteredPickerOptions.length" class="single-empty">
                            <i class="fa-solid fa-search"></i>
                            No {{ entityName }} match "{{ pickerSearch }}"
                        </div>
                    </div>

                    <div class="single-modal-footer">
                        <button type="button" class="single-btn single-btn--ghost" @click="closePicker">
                            Cancel
                        </button>
                        <button
                            type="button"
                            class="single-btn single-btn--primary"
                            :disabled="!draft"
                            @click="applyPicker"
                        >
                            Apply
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.single-picker-box {
    display: flex;
    align-items: center;
    gap: 2px;
    min-height: 0px;
    padding: 4px 10px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #fff;
}

.single-picker-box--empty {
    justify-content: space-between;
}

.single-picker-placeholder {
    color: #94a3b8;
    font-size: 13px;
}

.single-picker-value {
    font-size: 13px;
    font-weight: 500;
    color: #1e293b;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.single-picker-actions {
    margin-left: auto;
    display: flex;
    gap: 6px;
    flex-shrink: 0;
}

.single-btn {
    border: 1px solid transparent;
    border-radius: 8px;
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
}

.single-btn--ghost {
    background: #f8fafc;
    border-color: #e2e8f0;
    color: #475569;
}

.single-btn--ghost:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
    color: #334155;
}

.single-btn--primary {
    background: #4f46e5;
    color: #fff;
}

.single-btn--primary:hover:not(:disabled) {
    background: #4338ca;
}

.single-btn--primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* ── Overlay & Modal ── */

.single-overlay {
    position: fixed;
    inset: 0;
    z-index: 1060;
    background: rgba(15, 23, 42, 0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
}
.required-star {
    color: #ef4444;
}
.single-modal {
    width: min(480px, 100%);
    height: min(80vh, 640px);
    background: #fff;
    border-radius: 14px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 50px rgba(15, 23, 42, 0.2);
}

.single-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 16px;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
}

.single-modal-title {
    font-size: 14px;
    font-weight: 600;
    color: #0f172a;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.single-modal-close {
    width: 32px;
    height: 32px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
    color: #64748b;
    cursor: pointer;
}

.single-modal-close:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
    color: #334155;
}

.single-modal-search {
    padding: 12px 16px;
    border-bottom: 1px solid #f1f5f9;
}

.single-modal-body {
    flex: 1 1 auto;
    padding: 16px;
    overflow-y: auto;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    align-content: start;
    gap: 8px;
}

/* ── Radio option ── */

.single-option {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 10px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    font-size: 12px;
    color: #334155;
    transition: border-color 0.15s, background 0.15s;
}

.single-option:hover:not(.single-option--blocked) {
    border-color: #c7d2fe;
    background: #f5f3ff;
}

.single-option--on {
    border-color: #818cf8;
    background: #eef2ff;
}

.single-option--blocked {
    opacity: 0.55;
    cursor: not-allowed;
    background: #f8fafc;
}

.single-option-hint {
    margin-left: auto;
    font-size: 10px;
    color: #94a3b8;
    white-space: nowrap;
}

/* Custom radio circle */
.single-option-radio {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    border: 2px solid #cbd5e1;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: border-color 0.15s;
}

.single-option--on .single-option-radio {
    border-color: #4f46e5;
}

.single-option-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #4f46e5;
}

/* ── Empty state ── */

.single-empty {
    grid-column: 1 / -1;
    text-align: center;
    color: #94a3b8;
    font-size: 13px;
    padding: 24px 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

/* ── Footer ── */

.single-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 12px 16px;
    border-top: 1px solid #e2e8f0;
    background: #f8fafc;
}
</style>

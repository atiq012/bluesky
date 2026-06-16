<script setup>
// Reusable date / date-range picker using @vuepic/vue-datepicker. Single and range in one component.
// Value format always "01-Jan-2026". Range: { from: "01-Jan-2026", to: "05-Jan-2026" }.
import { ref, watch, computed } from 'vue';
import VueDatePicker from '@vuepic/vue-datepicker';
import { storeToRefs } from 'pinia';
import { useAuthStore } from '../../stores/authStore';
import '@vuepic/vue-datepicker/dist/main.css';

const DISPLAY_FORMAT = 'dd-MMM-yyyy';
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

const props = defineProps({
    modelValue: {
        type: [String, Object, Array],
        default: null,
    },
    range: { type: Boolean, default: false },
    multiDates: { type: Boolean, default: false },
    placeholder: { type: String, default: 'Select date' },
    disabled: { type: Boolean, default: false },
    clearable: { type: Boolean, default: true },
    inputClass: { type: String, default: 'form-control form-control-sm' },
    inputStyle: { type: [String, Object], default: 'padding-left: 2.25rem; cursor: pointer;' },
    enableTimePicker: { type: Boolean, default: false },
    multiCalendars: { type: [Boolean, Object], default: true },
});

const emit = defineEmits(['update:modelValue']);

function parseDisplayToDate(str) {
    if (!str || typeof str !== 'string') return null;
    const s = str.trim();
    const parts = s.split('-');
    if (parts.length !== 3) return null;
    const [dStr, mStr, yStr] = parts;
    const day = parseInt(dStr, 10);
    const monthIdx = MONTHS.indexOf(mStr);
    const year = parseInt(yStr, 10);
    if (Number.isNaN(day) || monthIdx < 0 || Number.isNaN(year)) return null;
    const d = new Date(year, monthIdx, day);
    return Number.isNaN(d.getTime()) ? null : d;
}

function dateToDisplay(d) {
    if (!d || !(d instanceof Date) || Number.isNaN(d.getTime())) return '';
    const day = String(d.getDate()).padStart(2, '0');
    const month = MONTHS[d.getMonth()];
    const year = d.getFullYear();
    return `${day}-${month}-${year}`;
}

const internalModel = ref(null);

function modelToInternal() {
    if (props.multiDates) {
        const v = props.modelValue;
        if (!v || !Array.isArray(v) || v.length === 0) {
            internalModel.value = [];
            return;
        }
        const parsed = v
            .map((s) => parseDisplayToDate(s))
            .filter((d) => d && d instanceof Date && !Number.isNaN(d.getTime()));
        internalModel.value = parsed.length ? parsed : [];
    } else if (props.range) {
        const v = props.modelValue;
        if (!v || typeof v !== 'object') {
            internalModel.value = null;
            return;
        }
        const from = parseDisplayToDate(v.from);
        const to = parseDisplayToDate(v.to);
        if (!from && !to) {
            internalModel.value = null;
            return;
        }
        internalModel.value = [from || null, to || null];
    } else {
        const str = props.modelValue;
        internalModel.value = parseDisplayToDate(str) ?? null;
    }
}

function internalToModel(val) {
    if (props.multiDates) {
        if (!val || !Array.isArray(val) || val.length === 0) {
            emit('update:modelValue', []);
            return;
        }
        const out = val
            .map((d) => (d instanceof Date && !Number.isNaN(d.getTime()) ? dateToDisplay(d) : ''))
            .filter((s) => s);
        emit('update:modelValue', out);
    } else if (props.range) {
        if (!val || !Array.isArray(val)) {
            emit('update:modelValue', { from: null, to: null });
            return;
        }
        const [a, b] = val;
        const from = a ? dateToDisplay(a) : null;
        const to = b ? dateToDisplay(b) : null;
        emit('update:modelValue', { from, to });
    } else {
        emit('update:modelValue', val ? dateToDisplay(val) : '');
    }
}

watch(
    () => props.modelValue,
    () => modelToInternal(),
    { immediate: true, deep: true }
);

function onInternalUpdate(val) {
    internalToModel(val);
}

function getPresetRanges() {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const d = (n) => {
        const x = new Date(today);
        x.setDate(x.getDate() + n);
        x.setHours(0, 0, 0, 0);
        return x;
    };
    const yesterday = d(-1);
    const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
    const lastDayOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    const firstDayOfLastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
    const lastDayOfLastMonth = new Date(today.getFullYear(), today.getMonth(), 0);
    const dayOfWeek = today.getDay();
    const firstDayOfWeek = d(-dayOfWeek);
    const lastDayOfWeek = d(-dayOfWeek + 6);
    return [
        { label: 'Today', from: today, to: today },
        { label: 'Yesterday', from: yesterday, to: yesterday },
        { label: 'This week', from: firstDayOfWeek, to: lastDayOfWeek },
        { label: 'Last 7 days', from: d(-6), to: today },
        { label: 'Last 30 days', from: d(-29), to: today },
        { label: 'This month', from: firstDayOfMonth, to: lastDayOfMonth },
        { label: 'Last month', from: firstDayOfLastMonth, to: lastDayOfLastMonth },
    ];
}

function applyPreset(presetDate, from, to) {
    if (presetDate && typeof presetDate === 'function') {
        presetDate([from, to]);
    }
}

const formats = computed(() => DISPLAY_FORMAT);

const multiDatesDisplay = computed(() => {
    if (!props.multiDates) return [];
    if (Array.isArray(props.modelValue)) return props.modelValue;
    return props.modelValue ? [props.modelValue] : [];
});

// Display value for single / range modes — used in the custom #dp-input slot
const singleRangeDisplay = computed(() => {
    if (props.range) {
        const v = props.modelValue;
        if (!v || (!v.from && !v.to)) return '';
        if (v.from && v.to) return `${v.from}  →  ${v.to}`;
        return v.from || v.to || '';
    }
    return props.modelValue ?? '';
});

const authStore = useAuthStore();
const { isDarkMode } = storeToRefs(authStore);
</script>

<template>
    <div class="app-date-picker w-100">
    <VueDatePicker
        v-model="internalModel"
        :range="!multiDates && range"
        :multi-dates="multiDates"
        :multi-calendars="!multiDates && range && multiCalendars !== false ? (typeof multiCalendars === 'object' ? multiCalendars : true) : false"
        :placeholder="placeholder"
        teleport
        :disabled="disabled"
        :clearable="clearable"
        :enable-time-picker="enableTimePicker"
        :auto-apply="true"
        :dark="isDarkMode"
        :format="formats"
        :input-class-name="inputClass"
        @update:model-value="onInternalUpdate"
    >
        <!-- Replace SVG calendar icon with boxicon — SVG relies on CSS vars that may not resolve -->
        <template #input-icon>
            <i class="bx bx-calendar" style="font-size: 16px; color: #959595; padding: 0 10px;"></i>
        </template>

        <!-- Single / range: use plain Bootstrap input so it renders visibly in any container -->
        <template v-if="!multiDates" #dp-input>
            <input
                :class="inputClass"
                :style="inputStyle"
                :value="singleRangeDisplay"
                :placeholder="placeholder"
                :disabled="disabled"
                readonly
            />
        </template>

        <template v-if="multiDates" #dp-input>
            <div
                class="dp__input dp__input--multi d-flex flex-wrap align-items-center gap-1"
                :class="inputClass"
            >
                <template v-for="(d, idx) in multiDatesDisplay" :key="`${d}-${idx}`">
                    <span class="d-inline-flex align-items-center">
                        <span>{{ d }}</span>
                        <span v-if="idx < multiDatesDisplay.length - 1">;</span>
                    </span>
                </template>
            </div>
        </template>
        <template v-if="range" #left-sidebar="{ presetDate }">
            <div class="dp-preset-sidebar d-flex flex-column gap-1 p-2">
                <span class="small fw-semibold text-muted text-uppercase px-2 py-1">Preset</span>
                <button
                    v-for="preset in getPresetRanges()"
                    :key="preset.label"
                    type="button"
                    class="btn btn-sm btn-link text-start text-decoration-none px-3 py-2"
                    @click="applyPreset(presetDate, preset.from, preset.to)"
                >
                    {{ preset.label }}
                </button>
            </div>
        </template>
    </VueDatePicker>
    </div>
</template>

<style>
/* Shield vs global leaks (e.g. searchResult.vue date-card hides .dp__input_wrap) */
.app-date-picker .dp__input_wrap {
    position: relative !important;
    opacity: 1 !important;
    width: 100% !important;
    height: auto !important;
    top: auto !important;
    left: auto !important;
}
.app-date-picker .dp__input_wrap input,
.app-date-picker .dp__input_wrap .form-control {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    width: 100% !important;
}
.app-date-picker .dp__input_wrap .dp__input--multi {
    display: flex !important;
    visibility: visible !important;
    opacity: 1 !important;
    width: 100% !important;
}
.app-date-picker .dp__input_icon {
    display: flex !important;
    align-items: center !important;
}

.dp__input {
    padding-left: 2.5rem;
}
.dp__input--multi {
    padding-left: 0.75rem;
    min-height: 31px;
    max-height: 6rem;
    overflow-y: auto;
}

html[data-bs-theme="dark"] .dp__theme_light,
html[data-bs-theme="dark"] .dp__theme_dark,
html[data-bs-theme="dark"] .dp--menu-wrapper,
html[data-bs-theme="dark"] .dp__menu {
    --dp-background-color: #1a2332;
    --dp-text-color: #e5e7eb;
    --dp-hover-color: #253b5c;
    --dp-hover-text-color: #e5e7eb;
    --dp-hover-icon-color: #94a3b8;
    --dp-primary-color: #4361ee;
    --dp-primary-disabled-color: #6b7fd7;
    --dp-primary-text-color: #fff;
    --dp-secondary-color: #c0c4cc;
    --dp-border-color: #374151;
    --dp-menu-border-color: #374151;
    --dp-border-color-hover: #64748b;
    --dp-border-color-focus: #64748b;
    --dp-disabled-color: #4b5563;
    --dp-disabled-color-text: #9ca3af;
    --dp-scroll-bar-background: #1a2332;
    --dp-scroll-bar-color: #475569;
    --dp-success-color: #10b981;
    --dp-success-color-disabled: #34d399;
    --dp-icon-color: #94a3b8;
    --dp-danger-color: #ef4444;
    --dp-marker-color: #ef4444;
    --dp-tooltip-color: #1e293b;
    --dp-highlight-color: rgb(67 97 238 / 20%);
    --dp-range-between-dates-background-color: var(--dp-hover-color, #253b5c);
    --dp-range-between-dates-text-color: var(--dp-hover-text-color, #e5e7eb);
    --dp-range-between-border-color: var(--dp-hover-color, #253b5c);
}

html[data-bs-theme="dark"] .dp__menu .dp--header-wrap,
html[data-bs-theme="dark"] .dp__menu .dp__calendar_header,
html[data-bs-theme="dark"] .dp__menu .dp__calendar_header_item,
html[data-bs-theme="dark"] .dp__menu .dp__calendar_header_cell,
html[data-bs-theme="dark"] .dp__menu .dp__month_year_row,
html[data-bs-theme="dark"] .dp__menu .dp__month_year_select,
html[data-bs-theme="dark"] .dp__menu .dp--year-select,
html[data-bs-theme="dark"] .dp__menu .dp__month_year_wrap {
    color: #e5e7eb !important;
}

html[data-bs-theme="dark"] .dp__menu .dp__calendar_item,
html[data-bs-theme="dark"] .dp__menu .dp__cell_inner,
html[data-bs-theme="dark"] .dp__menu .dp__cell_offset,
html[data-bs-theme="dark"] .dp__menu .dp__selection_preview,
html[data-bs-theme="dark"] .dp__menu .dp__action_row {
    color: #e5e7eb !important;
}
html[data-bs-theme="dark"] .dp__menu .dp__inner_nav {
    color: #94a3b8 !important;
}

html[data-bs-theme="dark"] .dp__input {
    background-color: rgb(26 35 50) !important;
    border-color: rgb(55 65 81) !important;
    color: rgb(229 231 235) !important;
}
html[data-bs-theme="dark"] .dp__menu,
html[data-bs-theme="dark"] .dp--menu-wrapper .dp__menu {
    background: #1a2332 !important;
    border-color: #374151 !important;
}

.dp-preset-sidebar {
    border-right: 1px solid var(--bs-border-color);
    min-width: 140px;
}
</style>

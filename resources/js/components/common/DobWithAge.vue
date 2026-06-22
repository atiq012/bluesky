<script setup>
import { computed } from 'vue'
import dayjs from 'dayjs'
import AppDatePicker from './AppDatePicker.vue'

const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']

const props = defineProps({
    modelValue:  { type: String, default: '' },
    paxType:     { type: String, default: '' },   // 'Adult' | 'Child' | 'Infant'
    travelDate:  { type: String, default: '' },         // "dd-MMM-yyyy"
    placeholder: { type: String, default: 'Date of Birth' },
    disabled:    { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

function parseDisplay(str) {
    if (!str || typeof str !== 'string') return null
    const parts = str.trim().split('-')
    if (parts.length !== 3) return null
    const [d, m, y] = parts
    const monthIdx = MONTHS.indexOf(m)
    if (monthIdx < 0) return null
    const parsed = dayjs(new Date(parseInt(y, 10), monthIdx, parseInt(d, 10)))
    return parsed.isValid() ? parsed : null
}

const dobDay = computed(() => parseDisplay(props.modelValue))

const refDay = computed(() => {
    const td = parseDisplay(props.travelDate)
    return td?.isValid() ? td : dayjs()
})

const ageAtTravel = computed(() => {
    const dob = dobDay.value
    if (!dob) return null
    const ref = refDay.value
    const totalMonths = ref.diff(dob, 'month')
    if (totalMonths < 0) return null
    return { years: Math.floor(totalMonths / 12), months: totalMonths % 12, totalMonths }
})

const ageDisplay = computed(() => {
    if (!dobDay.value) return ''
    const a = ageAtTravel.value
    if (!a) return ''
    if (a.totalMonths < 24) return `${a.totalMonths}M`
    return `${a.years}Y`
})

const warning = computed(() => {
    const dob = dobDay.value
    if (!dob) return ''

    if (dob.isAfter(dayjs(), 'day')) {
        return 'Date of birth cannot be in the future.'
    }

    const a = ageAtTravel.value
    if (!a) return ''
    const type = props.paxType

    if (type === 'Adult') {
        if (a.years < 12) {
            return `Adult age at travel date: ${a.years}Y ${a.months}M — must be ≥ 12 years.`
        }
    }
    if (type === 'Child') {
        if (a.years < 2 || a.years >= 12) {
            return `Child age at travel date: ${a.years}Y ${a.months}M — must be 2–11 years.`
        }
    }
    if (type === 'Infant') {
        if (a.totalMonths > 24) {
            return `Infant age at travel date: ${a.totalMonths} months — must be ≤ 24 months.`
        }
    }
    if (type === 'Owner') {
        if (a.years < 12) {
            return `Owner age at date: ${a.years}Y ${a.months}M — must be ≥ 18 years.`
        }
    }
    return ''
})
</script>

<template>
    <div class="dob-age-root">
        <div class="dob-age-wrap" :class="{ 'has-age': ageDisplay }">
            <AppDatePicker
                :model-value="modelValue"
                :placeholder="placeholder"
                :disabled="disabled"
                input-class="form-control form-control-sm dob-age-input"
                @update:model-value="emit('update:modelValue', $event)"
            />
            <span v-if="ageDisplay" class="dob-age-badge" :class="warning ? 'dob-age-badge--warn' : 'dob-age-badge--ok'">
                {{ ageDisplay }}
            </span>
        </div>
        <div v-if="warning" class="dob-age-warning mt-1">
            <i class="bx bx-error-circle"></i> {{ warning }}
        </div>
    </div>
</template>

<style scoped>
.dob-age-wrap {
    position: relative;
}

/* make room for badge + datepicker clear button (×) on the right */
.dob-age-wrap.has-age :deep(.dob-age-input) {
    padding-right: 84px !important;
}

.dob-age-badge {
    position: absolute;
    right: 30px;   /* sits to the LEFT of the datepicker × clear button (~26px wide) */
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    pointer-events: none;
    white-space: nowrap;
    z-index: 2;
    padding: 2px 6px;
    border-radius: 4px;
}

.dob-age-badge--ok {
    color: #1a7a4a;
    background: rgba(26, 122, 74, 0.12);
}

.dob-age-badge--warn {
    color: #e63946;
    background: rgba(230, 57, 70, 0.10);
}

.dob-age-warning {
    font-size: 0.75rem;
    color: #e63946;
    display: flex;
    align-items: flex-start;
    gap: 4px;
}

.dob-age-warning i {
    font-size: 0.85rem;
    flex-shrink: 0;
    margin-top: 1px;
}
</style>

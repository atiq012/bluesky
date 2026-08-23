<script setup>
import { computed, ref } from 'vue'
import { brandDynamicPricing, formatFareAmount } from '../../../utils/dynamicRulePricingDisplay'
import NumberInput from '../../common/NumberInput.vue'
import AgencyPayableBreakdownModal from '../../common/AgencyPayableBreakdownModal.vue'

const props = defineProps({
    price: { type: Object, default: null },
})

const currency = computed(() => props.price?.currency ?? 'BDT')

function fmtMoney(n, spaced = true) {
    const num = Number(n)
    if (Number.isNaN(num)) return '—'
    const val = formatFareAmount(num)
    return spaced ? `${currency.value} ${val}` : `${currency.value}${val}`
}

const grossFareTotal = computed(() => Number(props.price?.total_price ?? 0))
const totalBaseFare = computed(() => Number(props.price?.base_fare ?? 0))
const totalTax = computed(() => Number(props.price?.total_taxes ?? 0))

const discountInput = ref('')
const discountMode = ref('percent') // 'percent' | 'flat'

const discountAmount = computed(() => {
    const n = Number(discountInput.value)
    if (!Number.isFinite(n) || n <= 0) return 0
    if (discountMode.value === 'percent') {
        return totalBaseFare.value * (n / 100)
    }
    return n
})

const discountedBaseFare = computed(() => Math.max(totalBaseFare.value - discountAmount.value, 0))

const paxPayable = computed(() => Math.max(grossFareTotal.value - discountAmount.value, 0))

const dynamicPricing = computed(() => brandDynamicPricing(props.price))
const breakdownModalOpen = ref(false)
</script>

<template>
    <div class="rc-fare-card">
        <div v-if="!price" class="rc-fare-card__empty">
            <i class="fa-solid fa-receipt" aria-hidden="true" />
            <span>Fare summary unavailable</span>
        </div>

        <template v-else>
            <div class="rc-fare-card__row">
                <span class="rc-fare-card__row-left">
                    <span class="rc-fare-card__icon rc-fare-card__icon--indigo" aria-hidden="true"><i class="fa-solid fa-ticket"></i></span>
                    <span class="rc-fare-card__label">Total Base Fare</span>
                </span>
                <span class="rc-fare-card__val">{{ fmtMoney(totalBaseFare) }}</span>
            </div>

            <div class="rc-fare-card__row">
                <span class="rc-fare-card__row-left">
                    <span class="rc-fare-card__icon rc-fare-card__icon--sky" aria-hidden="true"><i class="fa-solid fa-landmark"></i></span>
                    <span class="rc-fare-card__label">Total Tax</span>
                </span>
                <span class="rc-fare-card__val">{{ fmtMoney(totalTax) }}</span>
            </div>

            <div class="rc-fare-card__row rc-fare-card__row--total">
                <span class="rc-fare-card__row-left">
                    <span class="rc-fare-card__icon rc-fare-card__icon--cyan" aria-hidden="true"><i class="fa-solid fa-calculator"></i></span>
                    <span class="rc-fare-card__label rc-fare-card__label--strong">Gross Fare</span>
                </span>
                <span class="rc-fare-card__val rc-fare-card__val--strong">{{ fmtMoney(grossFareTotal) }}</span>
            </div>

            <div class="rc-fare-card__divider mb-3"></div>

            <div class="rc-fare-card__discount">
                <label class="rc-fare-card__label rc-fare-card__label--sm">Passenger Discount (Base Fare)</label>
                <div class="rc-fare-card__discount-row" role="group" aria-label="Discount">
                    <button
                        type="button"
                        class="rc-fare-card__toggle-btn"
                        :class="{ 'rc-fare-card__toggle-btn--active': discountMode === 'flat' }"
                        @click="discountMode = 'flat'"
                    >
                        FLAT
                    </button>
                    <button
                        type="button"
                        class="rc-fare-card__toggle-btn"
                        :class="{ 'rc-fare-card__toggle-btn--active': discountMode === 'percent' }"
                        @click="discountMode = 'percent'"
                    >
                        Percent
                    </button>
                    <NumberInput
                        v-model="discountInput"
                        bare
                        placeholder="Value"
                        input-class="rc-fare-card__discount-input"
                        :max="discountMode === 'percent' ? 100 : totalBaseFare"
                    />
                    <span v-if="discountMode === 'percent' && discountAmount > 0" class="rc-fare-card__discount-amount">
                        {{ fmtMoney(discountAmount) }}
                    </span>
                </div>
            </div>

            <div class="rc-fare-card__row">
                <span class="rc-fare-card__row-left">
                    <span class="rc-fare-card__icon rc-fare-card__icon--emerald" aria-hidden="true"><i class="fa-solid fa-tags"></i></span>
                    <span class="rc-fare-card__label">After Discount</span>
                </span>
                <span class="rc-fare-card__val">{{ fmtMoney(discountedBaseFare) }}</span>
            </div>

            <div class="rc-fare-card__divider mt-3" />

            <button
                type="button"
                class="rc-fare-card__highlight rc-fare-card__highlight--pax rc-fare-card__highlight--clickable"
                :disabled="!dynamicPricing"
                @click="breakdownModalOpen = true"
            >
                <span class="rc-fare-card__row-left">
                    <span class="rc-fare-card__icon rc-fare-card__icon--teal" aria-hidden="true"><i class="fa-solid fa-wallet"></i></span>
                    <span class="rc-fare-card__label rc-fare-card__label--pax">PAX Payable</span>
                </span>
                <span class="rc-fare-card__val rc-fare-card__val--lg">{{ fmtMoney(paxPayable) }}</span>
            </button>

        </template>
    </div>

    <AgencyPayableBreakdownModal
        :is-open="breakdownModalOpen"
        :pricing="dynamicPricing"
        :currency="currency"
        :gross-payment="grossFareTotal"
        @close="breakdownModalOpen = false"
    />
</template>

<style scoped>
.rc-fare-card {
    --rc-purple: #7239ea;
    --rc-primary: #027de2;
    --rc-surface: #ffffff;
    --rc-border: #e2e8f0;
    --rc-text: #0f172a;
    --rc-muted: #64748b;
    --rc-shadow: 0 1px 3px rgba(15, 23, 42, 0.06), 0 6px 20px rgba(2, 125, 226, 0.07);

    background: var(--rc-surface);
    border: 1px solid var(--rc-border);
    border-radius: 12px;
    box-shadow: var(--rc-shadow);
    padding: 0.65rem 0.7rem 0.7rem;
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
}

.rc-fare-card__empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.4rem;
    padding: 1.2rem 0.5rem;
    color: var(--rc-muted);
    font-size: 0.72rem;
    text-align: center;
}
.rc-fare-card__empty i { font-size: 1.4rem; color: #cbd5e1; }

.rc-fare-card__row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
}
.rc-fare-card__row--total {
    padding-top: 0.35rem;
    border-top: 1px dashed var(--rc-border);
}

.rc-fare-card__row-left {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    min-width: 0;
}

.rc-fare-card__icon {
    flex-shrink: 0;
    width: 20px;
    height: 20px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    font-size: 9px;
}
.rc-fare-card__icon--indigo { color: #4f46e5; background: #eef2ff; }
.rc-fare-card__icon--sky { color: #0284c7; background: #e0f2fe; }
.rc-fare-card__icon--cyan { color: #0891b2; background: #cffafe; }
.rc-fare-card__icon--emerald { color: #16a34a; background: #dcfce7; }
.rc-fare-card__icon--teal { color: #0f766e; background: #ccfbf1; }

.rc-fare-card__label {
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--rc-muted);
}
.rc-fare-card__label--sm {
    font-size: 0.65rem;
    display: block;
    margin-bottom: 0.3rem;
    color: var(--rc-purple);
}
.rc-fare-card__label--strong {
    color: var(--rc-text);
    font-weight: 700;
}
.rc-fare-card__label--pax {
    color: #0f766e;
}

.rc-fare-card__val {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--rc-text);
}
.rc-fare-card__val--strong {
    font-size: 0.85rem;
}

.rc-fare-card__discount-row {
    display: flex;
    align-items: stretch;
    min-width: 0;
    border: 1px solid var(--rc-border);
    border-radius: 8px;
    overflow: hidden;
    background: var(--rc-surface);
}
.rc-fare-card__discount-row :deep(.rc-fare-card__discount-input) {
    flex: 1 1 0;
    min-width: 0;
    max-width: 76px;
    border: none;
    border-left: 1px solid var(--rc-border);
    border-radius: 0;
    padding: 0.3rem 0.45rem;
    font-size: 0.72rem;
    color: var(--rc-text);
    background: transparent;
}
.rc-fare-card__discount-row :deep(.rc-fare-card__discount-input):focus {
    outline: none;
    box-shadow: none;
}

.rc-fare-card__discount-amount {
    flex: 1 1 auto;
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    text-align: right;
    min-width: 0;
    max-width: 100px;
    padding: 0 0.5rem;
    font-size: 0.7rem;
    font-weight: 700;
    color: #dc2626;
    border-left: 1px solid var(--rc-border);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.rc-fare-card__toggle-btn {
    flex: 0 0 auto;
    border: none;
    background: var(--rc-surface);
    color: var(--rc-muted);
    font-size: 0.68rem;
    font-weight: 700;
    padding: 0.3rem 0.55rem;
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
}
.rc-fare-card__toggle-btn:not(:last-child) {
    border-right: 1px solid var(--rc-border);
}
.rc-fare-card__toggle-btn--active {
    background: linear-gradient(135deg, var(--rc-purple), #a855f7);
    color: #fff;
}

.rc-fare-card__divider {
    height: 1px;
    background: var(--rc-border);
}

.rc-fare-card__highlight {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    padding: 0.4rem 0.5rem;
    border-radius: 10px;
    font: inherit;
}
.rc-fare-card__highlight--pax {
    background: linear-gradient(135deg, rgba(15, 118, 110, 0.1), rgba(15, 118, 110, 0.04));
    border: 1px solid rgba(15, 118, 110, 0.22);
}
.rc-fare-card__highlight--pax .rc-fare-card__val--lg { color: #0f766e; }

.rc-fare-card__highlight--clickable {
    cursor: pointer;
    transition: opacity 0.15s ease;
}
.rc-fare-card__highlight--clickable:hover:not(:disabled) { opacity: 0.85; }
.rc-fare-card__highlight--clickable:disabled { cursor: default; }

.rc-fare-card__val--lg { font-size: 0.9rem; }

[data-bs-theme="dark"] .rc-fare-card {
    --rc-surface: var(--bs-card-bg);
    --rc-border: var(--bs-border-color);
    --rc-text: var(--bs-body-color);
    --rc-muted: var(--bs-secondary-color);
    --rc-shadow: 0 1px 3px rgba(0, 0, 0, 0.25), 0 6px 20px rgba(0, 0, 0, 0.18);
}
[data-bs-theme="dark"] .rc-fare-card__toggle-btn {
    background: rgba(255, 255, 255, 0.05);
}
[data-bs-theme="dark"] .rc-fare-card__toggle-btn--active {
    background: linear-gradient(135deg, var(--rc-purple), #a855f7);
}
[data-bs-theme="dark"] .rc-fare-card__label--pax { color: #5eead4; }
[data-bs-theme="dark"] .rc-fare-card__highlight--pax .rc-fare-card__val--lg { color: #5eead4; }
[data-bs-theme="dark"] .rc-fare-card__highlight--pax {
    background: linear-gradient(135deg, rgba(94, 234, 212, 0.14), rgba(94, 234, 212, 0.05));
    border-color: rgba(94, 234, 212, 0.28);
}
[data-bs-theme="dark"] .rc-fare-card__discount-amount {
    color: #ef9a9a;
    border-left-color: var(--rc-border);
}
</style>

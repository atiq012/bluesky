<script setup>
import { computed } from 'vue';
import { formatFareAmount } from '../../utils/dynamicRulePricingDisplay';

const props = defineProps({
    pricing: { type: Object, required: true },
    currency: { type: String, default: 'BDT' },
    grossPayment: { type: Number, default: null },
    showCustomerLine: { type: Boolean, default: true },
});

const lines = computed(() => props.pricing?.pricing_breakdown ?? []);

const ruleName = computed(() => props.pricing?.rule_name ?? '');

const customerAmount = computed(() => {
    if (props.grossPayment != null) {
        return Number(props.grossPayment);
    }

    return Number(props.pricing?.gross_payment ?? props.pricing?.gross_fare ?? 0);
});

const showCustomerRow = computed(() => {
    if (!props.showCustomerLine) {
        return false;
    }

    return Math.abs(customerAmount.value - Number(props.pricing?.total_payable ?? 0)) > 0.01
        || Number(props.pricing?.markup ?? 0) > 0;
});

function rowClass(line) {
    return {
        'apb-row--subtotal': line.type === 'subtotal',
        'apb-row--total': line.type === 'total',
        'apb-row--deduction': line.type === 'deduction',
        'apb-row--addition': line.type === 'addition',
    };
}

function formatLineAmount(line) {
    const amount = Number(line.amount ?? 0);
    const formatted = formatFareAmount(Math.abs(amount));

    if (amount < 0) {
        return `− ${props.currency} ${formatted}`;
    }

    return `${props.currency} ${formatted}`;
}
</script>

<template>
    <div class="apb">
        <div v-if="lines.length" class="apb-table">
            <div
                v-for="(line, idx) in lines"
                :key="idx"
                class="apb-row"
                :class="rowClass(line)"
            >
                <span class="apb-label">{{ line.label }}</span>
                <span class="apb-value">{{ formatLineAmount(line) }}</span>
            </div>

            <div v-if="showCustomerRow" class="apb-row apb-row--customer">
                <span class="apb-label">Gross Payment (Customer)</span>
                <span class="apb-value">{{ currency }} {{ formatFareAmount(customerAmount) }}</span>
            </div>
        </div>

        <p v-else class="apb-empty">No pricing breakdown available.</p>

        <p v-if="ruleName" class="apb-rule">
            <i class="fa-solid fa-tag me-1" aria-hidden="true"></i>
            Rule: {{ ruleName }}
        </p>
    </div>
</template>

<style scoped>
.apb-table {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.apb-row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
    padding: 6px 0;
    font-size: 13px;
    color: var(--bs-body-color, #1a2436);
    border-bottom: 1px solid var(--bs-border-color-translucent, rgba(0, 0, 0, 0.08));
}

.apb-row:last-child {
    border-bottom: none;
}

.apb-label {
    color: var(--bs-secondary-color, #6b7a99);
}

.apb-value {
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    text-align: right;
    white-space: nowrap;
}

.apb-row--subtotal .apb-label,
.apb-row--subtotal .apb-value {
    font-weight: 700;
    color: var(--bs-body-color, #1a2436);
}

.apb-row--deduction .apb-value {
    color: #c62828;
}

.apb-row--addition .apb-value {
    color: #2e7d32;
}

.apb-row--total {
    border-top: 1px solid var(--bs-border-color, #dee2e6);
    margin-top: 4px;
    padding-top: 10px;
    border-bottom: none;
}

.apb-row--total .apb-label,
.apb-row--total .apb-value {
    font-size: 15px;
    font-weight: 800;
    color: #1565c0;
}

.apb-row--customer {
    margin-top: 6px;
    border-top: 1px dashed var(--bs-border-color, #cbd5e1);
    padding-top: 8px;
}

.apb-row--customer .apb-value {
    font-weight: 700;
}

.apb-row--payable {
    margin-top: 2px;
    padding-top: 8px;
    border-top: 1px solid rgba(21, 101, 192, 0.2);
}

.apb-row--payable .apb-label,
.apb-row--payable .apb-value {
    font-weight: 800;
    color: #1565c0;
}

.apb-rule {
    margin: 12px 0 0;
    font-size: 12px;
    color: var(--bs-secondary-color, #64748b);
}

.apb-empty {
    margin: 0;
    font-size: 13px;
    color: var(--bs-secondary-color, #6b7a99);
}
</style>

<style>
html[data-bs-theme='dark'] .apb-row--deduction .apb-value {
    color: #ef9a9a;
}

html[data-bs-theme='dark'] .apb-row--addition .apb-value {
    color: #81c784;
}

html[data-bs-theme='dark'] .apb-row--total .apb-label,
html[data-bs-theme='dark'] .apb-row--total .apb-value,
html[data-bs-theme='dark'] .apb-row--payable .apb-label,
html[data-bs-theme='dark'] .apb-row--payable .apb-value {
    color: #64b5f6;
}
</style>

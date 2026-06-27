<script setup>
import { computed } from 'vue';
import { formatFareAmount } from '../../utils/dynamicRulePricingDisplay';

const props = defineProps({
    pricing: { type: Object, required: true },
    currency: { type: String, default: 'BDT' },
    grossPayment: { type: Number, default: null },
    showCustomerLine: { type: Boolean, default: true },
    showRuleName: { type: Boolean, default: true },
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

function lineMeta(line) {
    const label = (line.label || '').toLowerCase();

    if (label.includes('base fare')) {
        return { icon: 'fa-ticket', tone: 'indigo' };
    }
    if (label.includes('tax')) {
        return { icon: 'fa-landmark', tone: 'sky' };
    }
    if (label.includes('gross fare')) {
        return { icon: 'fa-calculator', tone: 'cyan' };
    }
    if (label.includes('commission')) {
        return { icon: 'fa-percent', tone: 'red' };
    }
    if (label.includes('stoppage')) {
        return { icon: 'fa-tags', tone: 'orange' };
    }
    if (label.includes('ait')) {
        return { icon: 'fa-file-invoice-dollar', tone: 'green' };
    }
    if (label.includes('service')) {
        return { icon: 'fa-hand-holding-dollar', tone: 'emerald' };
    }
    if (label.includes('markup')) {
        return { icon: 'fa-arrow-trend-up', tone: 'violet' };
    }
    if (label.includes('net fare')) {
        return { icon: 'fa-wallet', tone: 'slate' };
    }
    if (label.includes('total payable')) {
        return { icon: 'fa-circle-check', tone: 'blue' };
    }

    switch (line.type) {
        case 'deduction':
            return { icon: 'fa-circle-minus', tone: 'red' };
        case 'addition':
            return { icon: 'fa-circle-plus', tone: 'green' };
        case 'subtotal':
            return { icon: 'fa-equals', tone: 'slate' };
        case 'total':
            return { icon: 'fa-circle-check', tone: 'blue' };
        default:
            return { icon: 'fa-receipt', tone: 'slate' };
    }
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
                <div class="apb-row-left">
                    <span class="apb-icon" :class="`apb-icon--${lineMeta(line).tone}`" aria-hidden="true">
                        <i :class="['fa-solid', lineMeta(line).icon]"></i>
                    </span>
                    <span class="apb-label">{{ line.label }}</span>
                </div>
                <span class="apb-value">{{ formatLineAmount(line) }}</span>
            </div>

            <div v-if="showCustomerRow" class="apb-row apb-row--customer">
                <div class="apb-row-left">
                    <span class="apb-icon apb-icon--slate" aria-hidden="true">
                        <i class="fa-solid fa-user-tag"></i>
                    </span>
                    <span class="apb-label">Gross Payment (Customer)</span>
                </div>
                <span class="apb-value">{{ currency }} {{ formatFareAmount(customerAmount) }}</span>
            </div>
        </div>

        <p v-else class="apb-empty">
            <i class="fa-solid fa-circle-info me-1"></i>
            No pricing breakdown available.
        </p>

        <div v-if="showRuleName && ruleName" class="apb-rule">
            <span class="apb-rule-icon" aria-hidden="true">
                <i class="fa-solid fa-tag"></i>
            </span>
            <span class="apb-rule-text">
                <span class="apb-rule-label">Applied Rule</span>
                <span class="apb-rule-name">{{ ruleName }}</span>
            </span>
        </div>
    </div>
</template>

<style scoped>
.apb-table {
    display: flex;
    flex-direction: column;
    gap: 0;
}

.apb-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 4px 2px;
    min-height: 0;
    font-size: 12px;
    line-height: 1.2;
    color: var(--bs-body-color, #1a2436);
    background: transparent;
    border: none;
    border-bottom: 1px solid var(--bs-border-color-translucent, rgba(0, 0, 0, 0.07));
    border-radius: 0;
}

.apb-row:last-child {
    border-bottom: none;
}

.apb-row-left {
    display: flex;
    align-items: center;
    gap: 7px;
    min-width: 0;
}

.apb-icon {
    flex-shrink: 0;
    width: 20px;
    height: 20px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 5px;
    font-size: 10px;
}

.apb-icon--indigo { color: #4f46e5; background: #eef2ff; }
.apb-icon--sky { color: #0284c7; background: #e0f2fe; }
.apb-icon--cyan { color: #0891b2; background: #cffafe; }
.apb-icon--red { color: #dc2626; background: #fee2e2; }
.apb-icon--orange { color: #ea580c; background: #ffedd5; }
.apb-icon--green { color: #059669; background: #d1fae5; }
.apb-icon--emerald { color: #16a34a; background: #dcfce7; }
.apb-icon--violet { color: #7c3aed; background: #ede9fe; }
.apb-icon--slate { color: #475569; background: #f1f5f9; }
.apb-icon--blue { color: #1565c0; background: #dbeafe; }

.apb-label {
    color: var(--bs-secondary-color, #64748b);
    font-weight: 500;
    line-height: 1.2;
}

.apb-value {
    font-weight: 600;
    font-size: 12px;
    font-variant-numeric: tabular-nums;
    text-align: right;
    white-space: nowrap;
}

.apb-row--subtotal {
    padding-top: 5px;
    padding-bottom: 5px;
    background: transparent;
    border-color: var(--bs-border-color-translucent, rgba(0, 0, 0, 0.1));
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
    margin-top: 2px;
    padding: 6px 8px;
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    border: 1px solid rgba(21, 101, 192, 0.18);
    border-radius: 6px;
    box-shadow: none;
}

.apb-row--total .apb-label,
.apb-row--total .apb-value {
    font-size: 13px;
    font-weight: 800;
    color: #1565c0;
}

.apb-row--total .apb-icon--blue {
    background: #fff;
}

.apb-row--customer {
    margin-top: 0;
    border-bottom-style: dashed;
}

.apb-row--customer .apb-value {
    font-weight: 600;
}

.apb-rule {
    display: flex;
    align-items: center;
    gap: 7px;
    margin-top: 6px;
    padding: 4px 2px 0;
    background: transparent;
    border: none;
}

.apb-rule-icon {
    flex-shrink: 0;
    width: 22px;
    height: 22px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 5px;
    color: #7c3aed;
    background: #ede9fe;
    font-size: 10px;
}

.apb-rule-text {
    display: flex;
    flex-direction: row;
    align-items: baseline;
    flex-wrap: wrap;
    gap: 4px;
    min-width: 0;
}

.apb-rule-label {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--bs-secondary-color, #64748b);
}

.apb-rule-name {
    font-size: 11px;
    font-weight: 600;
    color: var(--bs-body-color, #1a2436);
    word-break: break-word;
}

.apb-empty {
    margin: 0;
    padding: 8px;
    font-size: 12px;
    color: var(--bs-secondary-color, #6b7a99);
    text-align: center;
    border: 1px dashed var(--bs-border-color, #cbd5e1);
    border-radius: 10px;
}
</style>

<style>
html[data-bs-theme='dark'] .apb-row {
    border-color: #495057;
}

html[data-bs-theme='dark'] .apb-row--subtotal {
    background: transparent;
    border-color: #495057;
}

html[data-bs-theme='dark'] .apb-row--total {
    background: linear-gradient(135deg, #1e3a5f 0%, #1a365d 100%);
    border-color: rgba(100, 181, 246, 0.35);
}

html[data-bs-theme='dark'] .apb-row--deduction .apb-value {
    color: #ef9a9a;
}

html[data-bs-theme='dark'] .apb-row--addition .apb-value {
    color: #81c784;
}

html[data-bs-theme='dark'] .apb-row--total .apb-label,
html[data-bs-theme='dark'] .apb-row--total .apb-value {
    color: #64b5f6;
}

html[data-bs-theme='dark'] .apb-row--total .apb-icon--blue {
    background: #1e2227;
}

html[data-bs-theme='dark'] .apb-icon--slate {
    color: #cbd5e1;
    background: #343a40;
}

html[data-bs-theme='dark'] .apb-rule {
    background: transparent;
    border: none;
}

html[data-bs-theme='dark'] .apb-rule-name {
    color: #dee2e6;
}
</style>

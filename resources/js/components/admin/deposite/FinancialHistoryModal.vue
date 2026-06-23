<script setup>
import { ref, watch } from 'vue';
import { runAction } from '../../../utils/runAction';
import AppDataTable from '../../common/DataTable.vue';
import AppDatePicker from '../../common/AppDatePicker.vue';
import AppButton from '../../common/AppButton.vue';
import { fetchFinancialHistory, formatStatementMoney } from './financialHistoryApi';

const props = defineProps({
    balance: { type: Object, default: () => ({}) },
    rows: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const filterLoading = ref(false);
const balance = ref({ ...props.balance });
const rows = ref([...props.rows]);
const fromDate = ref('');
const toDate = ref('');

const columns = [
    { field: 'date', title: 'Date' },
    { field: 'description', title: 'Description' },
    { field: 'money_in', title: 'In (+)' },
    { field: 'money_out', title: 'Out (−)' },
    { field: 'balance', title: 'Balance' },
    { field: 'credit_due', title: 'Credit Due' },
];

watch(() => props.balance, (v) => { balance.value = { ...v }; }, { immediate: true });
watch(() => props.rows, (v) => { rows.value = [...v]; }, { immediate: true });

const formatMoney = formatStatementMoney;

async function reloadStatement() {
    const data = await fetchFinancialHistory(fromDate.value, toDate.value);
    balance.value = data.balance;
    rows.value = data.rows;
}

async function applyFilter() {
    await runAction(reloadStatement, { setLoading: (v) => { filterLoading.value = v; } });
}

function resetFilter() {
    fromDate.value = '';
    toDate.value = '';
    applyFilter();
}

function close() {
    emit('close');
}
</script>

<template>
    <div class="fhm-body">
        <div class="fhm-header">
            <div>
                <h5 class="fhm-title mb-0">Financial History</h5>
                <p class="fhm-subtitle mb-0">Bank-style account statement</p>
            </div>
            <button type="button" class="btn-close" aria-label="Close" :disabled="filterLoading" @click="close"></button>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-md-4">
                <div class="fhm-stat">
                    <div class="small text-muted">Current Balance</div>
                    <div class="fw-bold text-primary">৳ {{ formatMoney(balance.net_balance) }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="fhm-stat">
                    <div class="small text-muted">Credit Due</div>
                    <div class="fw-bold text-danger">৳ {{ formatMoney(balance.credit_balance) }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="fhm-stat">
                    <div class="small text-muted">Cash Portion</div>
                    <div class="fw-bold text-success">৳ {{ formatMoney(balance.cash_portion) }}</div>
                </div>
            </div>
        </div>

        <div class="row g-2 align-items-end mb-3">
            <div class="col-md-4">
                <label class="form-label small mb-1">From</label>
                <AppDatePicker v-model="fromDate" :inline="false" :full-width="true" :clear-button="true" :enable-time="false" />
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-1">To</label>
                <AppDatePicker v-model="toDate" :inline="false" :full-width="true" :clear-button="true" :enable-time="false" />
            </div>
            <div class="col-md-4 d-flex gap-2">
                <AppButton variant="save" label="Filter" size="sm" :loading="filterLoading" @click="applyFilter" />
                <AppButton variant="cancel" label="Reset" size="sm" :disabled="filterLoading" @click="resetFilter" />
            </div>
        </div>

        <AppDataTable
            table-id="financial-history-modal"
            :rows="rows"
            :columns="columns"
            :loading="filterLoading"
            :page-size="10"
            search-placeholder="Search history..."
            @refresh="applyFilter"
        />

        <div class="fhm-footer">
            <AppButton variant="close" label="Close" :disabled="filterLoading" @click="close" />
        </div>
    </div>
</template>

<style scoped>
.fhm-body { padding: 1rem 1.1rem 0.9rem; }

.fhm-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #eef0f3;
}

.fhm-title { font-size: 1rem; font-weight: 700; color: #1f2937; }
.fhm-subtitle { font-size: 0.75rem; color: #6b7280; margin-top: 0.15rem; }

.fhm-stat {
    padding: 0.65rem 0.75rem;
    border-radius: 8px;
    background: #f8fafc;
    border: 1px solid #eef2f7;
}

.fhm-footer {
    display: flex;
    justify-content: flex-end;
    margin-top: 1rem;
    padding-top: 0.75rem;
    border-top: 1px solid #eef0f3;
}
</style>

<script setup>
import { ref, onMounted } from 'vue';
import axiosInstance from '../../../axiosInstance';
import AppDataTable from '../../common/DataTable.vue';
import AppDatePicker from '../../common/AppDatePicker.vue';
import moment from 'moment';

const loading = ref(false);
const balance = ref({ net_balance: 0, credit_balance: 0, cash_portion: 0 });
const rows = ref([]);
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

async function loadBalance() {
    try {
        const res = await axiosInstance.get('agent/balance');
        balance.value = res.data.data || balance.value;
    } catch {}
}

async function loadStatement() {
    loading.value = true;
    try {
        const params = {};
        if (fromDate.value) params.from = moment(fromDate.value, 'DD-MMM-YYYY').format('YYYY-MM-DD');
        if (toDate.value) params.to = moment(toDate.value, 'DD-MMM-YYYY').format('YYYY-MM-DD');
        const res = await axiosInstance.get('agent/statement', { params });
        const items = res.data.data?.data || [];
        rows.value = items.map((row) => ({
            ...row,
            date: row.transaction_at ? moment(row.transaction_at).format('DD-MMM-YYYY HH:mm') : '-',
            money_in: row.direction === 'in' ? formatMoney(row.amount) : '-',
            money_out: row.direction === 'out' ? formatMoney(row.amount) : '-',
            balance: formatMoney(row.net_balance_after),
            credit_due: formatMoney(row.credit_balance_after),
        }));
    } catch {
        rows.value = [];
    }
    loading.value = false;
}

function formatMoney(v) {
    const n = Number(v ?? 0);
    return n.toLocaleString('en-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function applyFilter() {
    loadStatement();
}

function resetFilter() {
    fromDate.value = '';
    toDate.value = '';
    loadStatement();
}

onMounted(async () => {
    await loadBalance();
    await loadStatement();
});
</script>

<template>
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Deposit Management</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item">
                        <router-link :to="{ name: 'Home' }">Dashboard</router-link>
                    </li>
                    <li class="breadcrumb-item">
                        <router-link :to="{ name: 'depositList' }">Deposit Management</router-link>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Account Statement</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <div class="small text-muted">Current Balance</div>
                    <div class="fs-4 fw-bold text-primary">৳ {{ formatMoney(balance.net_balance) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <div class="small text-muted">Credit Due</div>
                    <div class="fs-4 fw-bold text-danger">৳ {{ formatMoney(balance.credit_balance) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <div class="small text-muted">Cash Portion</div>
                    <div class="fs-4 fw-bold text-success">৳ {{ formatMoney(balance.cash_portion) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1">From</label>
                    <AppDatePicker v-model="fromDate" :inline="false" :full-width="true" :clear-button="true" :enable-time="false" />
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">To</label>
                    <AppDatePicker v-model="toDate" :inline="false" :full-width="true" :clear-button="true" :enable-time="false" />
                </div>
                <div class="col-md-6 d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-primary" @click="applyFilter">Filter</button>
                    <button type="button" class="btn btn-sm btn-secondary" @click="resetFilter">Reset</button>
                    <router-link :to="{ name: 'depositList' }" class="btn btn-sm btn-outline-secondary ms-auto">Back to Deposits</router-link>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <AppDataTable
                table-id="agent-statement"
                :rows="rows"
                :columns="columns"
                :loading="loading"
                :page-size="20"
                search-placeholder="Search statement..."
                @refresh="loadStatement"
            />
        </div>
    </div>
</template>

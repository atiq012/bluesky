<script setup>
import AppBreadcrumbs from '../../../common/AppBreadcrumbs.vue';
import { ref, computed } from "vue";
import { useRouter } from 'vue-router';
import axiosInstance from "../../../../axiosInstance";
import AppDataTable from '../../../common/DataTable.vue';
import AppTooltip from '../../../common/AppTooltip.vue';
import ActionIconButton from '../../../common/ActionIconButton.vue';
import * as XLSX from "xlsx";
import moment from "moment";

const router = useRouter();
const rData = ref([]);
const loading = ref(false);

// filters
const filterPaymentFrom = ref("");
const filterPaymentTo = ref("");
const filterGroupFrom = ref("");
const filterGroupTo = ref("");
const filterSequence = ref("");
const filterAgency = ref("");
const filterTransactedBy = ref("");
const filterStatus = ref("");
const searchQuery = ref("");

function clearFilters() {
    filterPaymentFrom.value = "";
    filterPaymentTo.value = "";
    filterGroupFrom.value = "";
    filterGroupTo.value = "";
    filterSequence.value = "";
    filterAgency.value = "";
    filterTransactedBy.value = "";
    filterStatus.value = "";
    searchQuery.value = "";
}

const hasActiveFilters = computed(() =>
    !!(filterPaymentFrom.value || filterPaymentTo.value || filterGroupFrom.value || filterGroupTo.value ||
        filterSequence.value || filterAgency.value || filterTransactedBy.value || filterStatus.value)
);

const uniqueSequences = computed(() => {
    const set = new Set(rData.value.map(r => r.payment_sequence).filter(Boolean));
    return [...set];
});

const uniqueAgencies = computed(() => {
    const set = new Set(rData.value.map(r => r.agent_name).filter(Boolean));
    return [...set];
});

const uniqueTransactedBy = computed(() => {
    const set = new Set(rData.value.map(r => r.transacted_by).filter(Boolean));
    return [...set];
});

const filteredData = computed(() => {
    return rData.value.filter((row) => {
        if (filterPaymentFrom.value && moment(row.created_at).isBefore(moment(filterPaymentFrom.value), 'day')) return false;
        if (filterPaymentTo.value && moment(row.created_at).isAfter(moment(filterPaymentTo.value), 'day')) return false;
        if (filterGroupFrom.value && moment(row.group_created_at).isBefore(moment(filterGroupFrom.value), 'day')) return false;
        if (filterGroupTo.value && moment(row.group_created_at).isAfter(moment(filterGroupTo.value), 'day')) return false;
        if (filterSequence.value && row.payment_sequence !== filterSequence.value) return false;
        if (filterAgency.value && row.agent_name !== filterAgency.value) return false;
        if (filterTransactedBy.value && row.transacted_by !== filterTransactedBy.value) return false;
        if (filterStatus.value && row.status !== filterStatus.value) return false;

        if (searchQuery.value) {
            const q = searchQuery.value.toLowerCase();
            const hay = [row.trn_id, row.agent_name, row.agent_code, row.group_code, row.pnr, row.transacted_by]
                .filter(Boolean).join(' ').toLowerCase();
            if (!hay.includes(q)) return false;
        }

        return true;
    });
});

const statistics = computed(() => {
    const totalPayments = rData.value
        .filter(i => i.status === 'Success')
        .reduce((sum, i) => sum + (Number(i.paid_amount) || 0), 0);
    return { totalPayments };
});

const columns = [
    { field: 'sl', title: 'Sl.' },
    { field: 'date_trn', title: 'Payment Date & TRN ID' },
    { field: 'agency', title: 'Agency' },
    { field: 'amount_seq', title: 'Amount & Payment Sequence' },
    { field: 'group_details', title: 'Group Details' },
    { field: 'status', title: 'Status' },
    { field: 'action', title: 'Action' },
];

const rows = computed(() =>
    filteredData.value.map((item, index) => ({ ...item, sl: index + 1 }))
);

function formatAmount(value) {
    const n = Number(value ?? 0);
    return Number.isFinite(n) ? n.toLocaleString('en-BD', { maximumFractionDigits: 0 }) : '0';
}

function formatCompact(value) {
    const n = Number(value ?? 0);
    if (!Number.isFinite(n)) return '0';
    if (n >= 1000000) return String(Math.round(n / 1000000)).padStart(2, '0') + 'M';
    if (n >= 1000) return String(Math.round(n / 1000)).padStart(2, '0') + 'K';
    return formatAmount(n);
}

function statusConfig(status) {
    if (status === 'Void') {
        return { cls: 'status-pill status-void', label: 'Payment Void' };
    }
    return { cls: 'status-pill status-success', label: 'Success' };
}

function fareBreakdownHtml(row) {
    const currency = row.currency || 'BDT';
    const adultBase = (Number(row.adult_base_fare) || 0) * (Number(row.adult_traveler) || 0);
    const childBase = (Number(row.child_base_fare) || 0) * (Number(row.child_traveler) || 0);
    const adultTaxRate = (Number(row.adult_tax) || 0) + (Number(row.adult_ait) || 0);
    const childTaxRate = (Number(row.child_tax) || 0) + (Number(row.child_ait) || 0);
    const adultTax = adultTaxRate * (Number(row.adult_traveler) || 0);
    const childTax = childTaxRate * (Number(row.child_traveler) || 0);

    let html = `<div class="fare-breakdown-card">`;
    html += `<div class="fb-title">Base Fare</div>`;
    html += `<div class="fb-row"><span>Adult: ${row.adult_traveler || 0}x${formatAmount(row.adult_base_fare)}</span><span>${currency} ${formatAmount(adultBase)}</span></div>`;
    if (row.child_traveler > 0) {
        html += `<div class="fb-row"><span>Child: ${row.child_traveler}x${formatAmount(row.child_base_fare)}</span><span>${currency} ${formatAmount(childBase)}</span></div>`;
    }
    html += `<div class="fb-title fb-title-spaced">TAX</div>`;
    html += `<div class="fb-row"><span>Adult: ${row.adult_traveler || 0}x${formatAmount(adultTaxRate)}</span><span>${currency} ${formatAmount(adultTax)}</span></div>`;
    if (row.child_traveler > 0) {
        html += `<div class="fb-row"><span>Child: ${row.child_traveler}x${formatAmount(childTaxRate)}</span><span>${currency} ${formatAmount(childTax)}</span></div>`;
    }
    html += `<div class="fb-divider"></div>`;
    html += `<div class="fb-row fb-total-row fb-strong"><span>Total Fare</span><span>${currency} ${formatAmount(row.est_total_fare)}</span></div>`;
    html += `<div class="fb-row"><span>${row.payment_sequence || 'This Payment'}</span><span class="fb-blue">${currency} ${formatAmount(row.paid_amount)}</span></div>`;
    html += `<div class="fb-row fb-due-row fb-strong"><span>Due</span><span class="fb-red">${currency} ${formatAmount(row.due_amount)}</span></div>`;
    html += `</div>`;

    return html;
}

function wayTypeLabel(type) {
    const t = String(type || '').toLowerCase();
    if (t.includes('multi')) return 'Multi City';
    if (t.includes('round')) return 'Round Way';
    return 'One Way';
}

function requestedFareTotal(g) {
    if (!g) return 0;
    if (Number(g.per_person_fare) > 0) return Number(g.per_person_fare) * (Number(g.total_traveler) || 0);
    if (Number(g.max_proposed_fare) > 0) return Number(g.max_proposed_fare);
    if (Number(g.min_proposed_fare) > 0) return Number(g.min_proposed_fare);
    return 0;
}

async function getListValues() {
    loading.value = true;
    try {
        const response = await axiosInstance.get("get-group-payments");
        rData.value = response.data.data || [];
    } catch (error) {
        // console.log(error);
    }
    loading.value = false;
}

// Group Request Details modal
const showGroupDetailModal = ref(false);
const groupDetailLoading = ref(false);
const groupDetailData = ref(null);

async function handleGroupDetails(row) {
    showGroupDetailModal.value = true;
    groupDetailLoading.value = true;
    groupDetailData.value = null;
    try {
        const response = await axiosInstance.post('edit-group-request/data', { id: row.group_req_id });
        groupDetailData.value = response.data.data;
    } catch (error) {
        if (typeof Notification !== 'undefined' && Notification?.showToast) {
            Notification.showToast('e', 'Failed to load group details.');
        }
    } finally {
        groupDetailLoading.value = false;
    }
}

function closeGroupDetailModal() {
    showGroupDetailModal.value = false;
    groupDetailData.value = null;
}

// View details modal
const showViewModal = ref(false);
const viewItem = ref(null);
function handleView(item) {
    viewItem.value = item;
    showViewModal.value = true;
}
function closeViewModal() {
    showViewModal.value = false;
    viewItem.value = null;
}

const viewTotals = computed(() => {
    const item = viewItem.value;
    if (!item) return { netPayable: 0, paid: 0, due: 0, pct: 0, currency: 'BDT' };

    const paid = rData.value
        .filter(r => r.group_req_id === item.group_req_id && r.status === 'Success')
        .reduce((sum, r) => sum + (Number(r.paid_amount) || 0), 0);
    const netPayable = Number(item.estimate_net_payable) || 0;
    const due = Math.max(0, netPayable - paid);
    const pct = netPayable > 0 ? Math.min(100, (paid / netPayable) * 100) : 0;

    return { netPayable, paid, due, pct, currency: item.currency || 'BDT' };
});

// Payment history modal (all payments under the same group request)
const showHistoryModal = ref(false);
const historyGroupId = ref(null);
const historyGroupCode = ref('');
function handleHistory(item) {
    historyGroupId.value = item.group_req_id;
    historyGroupCode.value = item.group_code;
    showHistoryModal.value = true;
}
function closeHistoryModal() {
    showHistoryModal.value = false;
    historyGroupId.value = null;
}
const historyRows = computed(() => {
    if (!historyGroupId.value) return [];
    return rData.value
        .filter(r => r.group_req_id === historyGroupId.value)
        .slice()
        .sort((a, b) => a.id - b.id);
});

// Void modal
const showVoidModal = ref(false);
const voidItem = ref(null);
const voidNote = ref("");
const voidError = ref("");
const voiding = ref(false);
function handleVoidClick(item) {
    voidItem.value = item;
    voidNote.value = "";
    voidError.value = "";
    showVoidModal.value = true;
}
function closeVoidModal() {
    if (voiding.value) return;
    showVoidModal.value = false;
    voidItem.value = null;
    voidNote.value = "";
    voidError.value = "";
}
async function confirmVoid() {
    if (!voidNote.value || !voidNote.value.trim()) {
        voidError.value = 'Please add a note explaining the void reason.';
        return;
    }
    try {
        voiding.value = true;
        await axiosInstance.post('group-payment/void', {
            id: voidItem.value.id,
            note: voidNote.value,
        });
        await getListValues();
        if (typeof Notification !== 'undefined' && Notification?.showToast) {
            Notification.showToast('s', 'Payment voided successfully!');
        }
        closeVoidModal();
    } catch (error) {
        if (typeof Notification !== 'undefined' && Notification?.showToast) {
            Notification.showToast('e', error.response?.data?.message || 'Failed to void payment.');
        }
    } finally {
        voiding.value = false;
    }
}

// Restore
async function handleRestore(item) {
    if (typeof iziToast === 'undefined') {
        if (!confirm('Restore this voided payment?')) return;
        await doRestore(item);
        return;
    }
    iziToast.question({
        timeout: 100000,
        pauseOnHover: false,
        close: false,
        overlay: true,
        displayMode: 'once',
        id: 'question',
        zindex: 999,
        message: 'Restore this voided payment?',
        position: 'center',
        buttons: [
            ['<button><b>No</b></button>', function (instance, toast) {
                instance.hide({ transitionOut: 'fadeOut' }, toast, 'no');
            }, true],
            ['<button><b>Yes</b></button>', function (instance, toast) {
                instance.hide({ transitionOut: 'fadeOut' }, toast, 'yes');
            }, true]
        ],
        onClosed: async function (instance, toast, closedBy) {
            if (closedBy == 'yes') {
                await doRestore(item);
            }
        }
    });
}
async function doRestore(item) {
    try {
        await axiosInstance.post('group-payment/restore', { id: item.id });
        await getListValues();
        if (typeof Notification !== 'undefined' && Notification?.showToast) {
            Notification.showToast('s', 'Payment restored successfully!');
        }
    } catch (error) {
        if (typeof Notification !== 'undefined' && Notification?.showToast) {
            Notification.showToast('e', error.response?.data?.message || 'Failed to restore payment.');
        }
    }
}

// Export
function exportRows() {
    return filteredData.value.map(r => ({
        'TRN ID': r.trn_id,
        'Payment Date': r.created_at ? moment(r.created_at).format('DD-MMM-YYYY hh:mm A') : '',
        'Agency': r.agent_name,
        'Agency Code': r.agent_code,
        'Amount': Number(r.paid_amount) || 0,
        'Payment Sequence': r.payment_sequence,
        'Group Code': r.group_code,
        'PNR': r.pnr,
        'Status': statusConfig(r.status).label,
        'Transacted By': r.transacted_by,
    }));
}

function exportExcel() {
    const ws = XLSX.utils.json_to_sheet(exportRows());
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Group Payments');
    XLSX.writeFile(wb, 'group-payments.xlsx');
}

function exportCsv() {
    const ws = XLSX.utils.json_to_sheet(exportRows());
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Group Payments');
    XLSX.writeFile(wb, 'group-payments.csv', { bookType: 'csv' });
}

function exportPdf() {
    const data = exportRows();
    if (!data.length) return;
    const headers = Object.keys(data[0]);
    const win = window.open('', '_blank');
    if (!win) return;
    const tableRows = data.map(row =>
        `<tr>${headers.map(h => `<td>${row[h] ?? ''}</td>`).join('')}</tr>`
    ).join('');
    win.document.write(`
        <html>
        <head>
            <title>Group Payments</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 16px; }
                table { width: 100%; border-collapse: collapse; font-size: 12px; }
                th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
                th { background: #f3f4f6; }
            </style>
        </head>
        <body>
            <h4>Group Payments</h4>
            <table>
                <thead><tr>${headers.map(h => `<th>${h}</th>`).join('')}</tr></thead>
                <tbody>${tableRows}</tbody>
            </table>
        </body>
        </html>
    `);
    win.document.close();
    win.focus();
    win.print();
}

getListValues();
</script>

<template>
    <AppBreadcrumbs title="Payments" :back-to="{ name: 'groupList' }" :breadcrumbs="[
        { label: 'Dashboard', to: { name: 'Home' } },
        { label: 'B2B Group', to: { name: 'groupList' } },
        { label: 'Payments List' }]">
        <template #actions>
            <!-- <button type="button" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2"
                @click="handleNewPayment">
                <i class="fa fa-circle-plus"></i>
                <span>New Payment</span>
            </button> -->
        </template>
    </AppBreadcrumbs>

    <!-- Stat Card -->
    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4 col-lg-3">
            <div class="info-agency">
                <span class="info-agency-icon bg-info elevation-1"><i class="fa-solid fa-key"></i></span>
                <div class="info-agency-content">
                    <span class="info-agency-text">Total Payments</span>
                    <span class="info-agency-number">BDT {{ formatCompact(statistics.totalPayments) }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <!-- <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-3">
            <div class="d-flex flex-wrap align-items-end gap-3">
                <div class="filter-field">
                    <label class="filter-label"><i class="fa-regular fa-calendar me-1"></i>Payment Date</label>
                    <div class="d-flex gap-1">
                        <input v-model="filterPaymentFrom" type="date" class="form-control form-control-sm">
                        <input v-model="filterPaymentTo" type="date" class="form-control form-control-sm">
                    </div>
                </div>
                <div class="filter-field">
                    <label class="filter-label"><i class="fa-regular fa-calendar me-1"></i>Group Create</label>
                    <div class="d-flex gap-1">
                        <input v-model="filterGroupFrom" type="date" class="form-control form-control-sm">
                        <input v-model="filterGroupTo" type="date" class="form-control form-control-sm">
                    </div>
                </div>
                <div class="filter-field filter-field-sm">
                    <label class="filter-label">Payment Sequence</label>
                    <select v-model="filterSequence" class="form-select form-select-sm">
                        <option value="">All Sequence</option>
                        <option v-for="seq in uniqueSequences" :key="seq" :value="seq">{{ seq }}</option>
                    </select>
                </div>
                <div class="filter-field filter-field-sm">
                    <label class="filter-label">Agency</label>
                    <select v-model="filterAgency" class="form-select form-select-sm">
                        <option value="">All Agency</option>
                        <option v-for="a in uniqueAgencies" :key="a" :value="a">{{ a }}</option>
                    </select>
                </div>
                <div class="filter-field filter-field-sm">
                    <label class="filter-label">Transacted By</label>
                    <select v-model="filterTransactedBy" class="form-select form-select-sm">
                        <option value="">All Users</option>
                        <option v-for="u in uniqueTransactedBy" :key="u" :value="u">{{ u }}</option>
                    </select>
                </div>
                <div class="filter-field filter-field-sm">
                    <label class="filter-label">Status</label>
                    <select v-model="filterStatus" class="form-select form-select-sm">
                        <option value="">All Status</option>
                        <option value="Success">Success</option>
                        <option value="Void">Payment Void</option>
                    </select>
                </div>
                <div class="filter-field">
                    <button v-if="hasActiveFilters" type="button"
                        class="btn btn-link btn-sm text-decoration-none px-0" @click="clearFilters">
                        <i class="fa-solid fa-xmark me-1"></i>Clear Filters
                    </button>
                </div>
            </div>
        </div>
    </div> -->

    <!-- Export toolbar -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
        <div class="d-flex gap-2">
            <button type="button" class="btn-export btn-export-pdf" @click="exportPdf">
                <i class="fa-solid fa-file-pdf"></i> Pdf
            </button>
            <button type="button" class="btn-export btn-export-excel" @click="exportExcel">
                <i class="fa-solid fa-file-excel"></i> Excel
            </button>
            <button type="button" class="btn-export btn-export-csv" @click="exportCsv">
                <i class="fa-solid fa-file-csv"></i> CSV
            </button>
        </div>
        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input v-model="searchQuery" type="text" placeholder="Search by anything...">
        </div>
    </div>

    <!-- Table -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-2 p-3">
                <AppDataTable table-id="group-payment-list" :rows="rows" :columns="columns" :loading="loading"
                    :page-size="30" :searchable="false" @refresh="getListValues">
                    <!-- SL -->
                    <template #sl="{ value: row }">
                        <span class="text-muted fw-semibold">{{ row.sl }}</span>
                    </template>

                    <!-- Payment Date & TRN ID -->
                    <template #date_trn="{ value: row }">
                        <div class="group-info-cell">
                            <div class="cell-main">
                                <i class="fa-regular fa-calendar me-1" style="font-size: 0.65rem;"></i>
                                {{ row.created_at ? moment(row.created_at).format('DD-MMM-YYYY | hh:mm A') : 'N/A' }}
                            </div>
                            <div class="cell-link">
                                <i class="fa-regular fa-comment-dots me-1" style="font-size: 0.65rem;"></i>
                                {{ row.trn_id }}
                            </div>
                        </div>
                    </template>

                    <!-- Agency -->
                    <template #agency="{ value: row }">
                        <div class="group-info-cell">
                            <div class="cell-main">{{ row.agent_name || '-' }}</div>
                            <small class="cell-link">{{ row.agent_code }}</small>
                            <div class="cell-sub" v-if="row.agent_phone">
                                <i class="fa fa-mobile me-1" style="font-size: 0.65rem;"></i>{{ row.agent_phone }}
                            </div>
                        </div>
                    </template>

                    <!-- Amount & Payment Sequence -->
                    <template #amount_seq="{ value: row }">
                        <div class="fare-cell">
                            <div class="cell-main amount-text d-flex align-items-center gap-1">
                                {{ row.currency || 'BDT' }} {{ formatAmount(row.paid_amount) }}
                                <AppTooltip :content="fareBreakdownHtml(row)" :allow-html="true" :arrow="false"
                                    theme="fare-card" placement="right">
                                    <i class="fa-solid fa-circle-info fb-info-icon"></i>
                                </AppTooltip>
                            </div>
                            <div class="cell-link">{{ row.payment_sequence || '-' }}</div>
                        </div>
                    </template>

                    <!-- Group Details -->
                    <template #group_details="{ value: row }">
                        <div class="group-info-cell">
                            <div class="cell-main d-flex align-items-center gap-1">
                                {{ row.group_code }}
                                <i class="fa-solid fa-circle-info fb-info-icon" @click="handleGroupDetails(row)"></i>
                            </div>
                            <div class="cell-link">
                                <i class="fa-regular fa-calendar me-1" style="font-size: 0.65rem;"></i>
                                {{ row.group_created_at ? moment(row.group_created_at).format('DD-MMM-YYYY | hh:mm A') : 'N/A' }}
                            </div>
                            <div class="cell-tagline" v-if="row.pnr">PNR : {{ row.pnr }}</div>
                        </div>
                    </template>

                    <!-- Status -->
                    <template #status="{ value: row }">
                        <div class="fare-cell">
                            <div class="status-cell">
                                <span :class="['rounded-pill', statusConfig(row.status).cls]">
                                    <i class="fa-solid fa-circle me-1 tiny"></i>{{ statusConfig(row.status).label }}
                                </span>
                            </div>
                            <div class="cell-main" style="font-size: 0.75rem;">{{ row.transacted_by || '-' }}</div>
                            <div class="cell-sub">
                                {{ row.updated_at ? moment(row.updated_at).format('DD-MMM-YYYY | hh:mm A') : (row.created_at ? moment(row.created_at).format('DD-MMM-YYYY | hh:mm A') : '') }}
                            </div>
                        </div>
                    </template>

                    <!-- Action -->
                    <template #action="{ value: row }">
                        <div class="d-flex gap-1 justify-content-center">
                            <ActionIconButton icon="fa-solid fa-eye" tooltip="View Details"
                                btn-class="action-btn-view-payment" @click="handleView(row)" />
                            <!-- <ActionIconButton v-if="row.status !== 'Void'" icon="fa-solid fa-ban"
                                tooltip="Void Payment" btn-class="action-btn-void-payment"
                                @click="handleVoidClick(row)" />
                            <ActionIconButton v-if="row.status === 'Void'" icon="fa-solid fa-rotate-left"
                                tooltip="Restore Payment" btn-class="action-btn-restore-payment"
                                @click="handleRestore(row)" /> -->
                            <ActionIconButton icon="fa-solid fa-clock-rotate-left" tooltip="Payment History"
                                btn-class="action-btn-history-payment" @click="handleHistory(row)" />
                        </div>
                    </template>
                </AppDataTable>
            </div>
        </div>
    </div>

    <!-- View Details Modal -->
    <div v-if="showViewModal" class="modal-overlay" @click.self="closeViewModal">
        <div class="vdm-box">
            <button class="vdm-close" @click="closeViewModal"><i class="fa fa-times"></i></button>
            <div class="vdm-inner" v-if="viewItem">
                <div class="vdm-ring-wrap">
                    <div class="vdm-ring" :style="{ background: `conic-gradient(#3b82f6 0deg ${viewTotals.pct * 3.6}deg, #e6ecf5 ${viewTotals.pct * 3.6}deg 360deg)` }">
                        <div class="vdm-ring-inner">
                            <div class="vdm-ring-label">Net Payable</div>
                            <div class="vdm-ring-value">{{ viewTotals.currency }} {{ formatAmount(viewTotals.netPayable) }}</div>
                            <div class="vdm-ring-meta">Paid : {{ formatAmount(viewTotals.paid) }}</div>
                            <div class="vdm-ring-meta">Due : {{ formatAmount(viewTotals.due) }}</div>
                        </div>
                    </div>
                </div>

                <div class="vdm-info-row">
                    <div class="vdm-info-cell">
                        <div class="vdm-info-label">Payment Date & Ref No.</div>
                        <div class="vdm-info-value">{{ viewItem.created_at ? moment(viewItem.created_at).format('DD-MMM-YYYY | hh:mm A') : '-' }}</div>
                        <div class="vdm-info-link">{{ viewItem.trn_id }}</div>
                    </div>
                    <div class="vdm-info-cell">
                        <div class="vdm-info-label">Paid Amount & Payment Terms</div>
                        <div class="vdm-info-value">{{ viewItem.currency || 'BDT' }} {{ formatAmount(viewItem.paid_amount) }}</div>
                        <div class="vdm-info-link">{{ viewItem.payment_sequence || '-' }}</div>
                    </div>
                    <div class="vdm-info-cell vdm-info-cell-last">
                        <div class="vdm-info-label">Group Details</div>
                        <div class="vdm-info-value">{{ viewItem.group_code }}</div>
                        <div class="vdm-info-link">{{ viewItem.group_created_at ? moment(viewItem.group_created_at).format('DD-MMM-YYYY | hh:mm A') : '-' }}</div>
                    </div>
                </div>

                <div class="vdm-void-note" v-if="viewItem.status === 'Void' && viewItem.void_note">
                    <div class="vdm-info-label">Void Note</div>
                    <div class="vdm-info-value">{{ viewItem.void_note }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment History Modal -->
    <div v-if="showHistoryModal" class="modal-overlay" @click.self="closeHistoryModal">
        <div class="modal-box modal-box-lg">
            <div class="modal-header">
                <h5>Payment History — {{ historyGroupCode }}</h5>
                <button class="modal-close" @click="closeHistoryModal"><i class="fa fa-times"></i></button>
            </div>
            <div class="modal-body">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>TRN ID</th>
                            <th>Sequence</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="historyRows.length === 0">
                            <td colspan="5" class="text-center text-muted py-3">No payment history available</td>
                        </tr>
                        <tr v-for="item in historyRows" :key="item.id">
                            <td>{{ item.trn_id }}</td>
                            <td>{{ item.payment_sequence || '-' }}</td>
                            <td>{{ item.created_at ? moment(item.created_at).format('DD-MMM-YYYY hh:mm A') : '-' }}</td>
                            <td>{{ item.currency || 'BDT' }} {{ formatAmount(item.paid_amount) }}</td>
                            <td><span :class="['rounded-pill', statusConfig(item.status).cls]">{{ statusConfig(item.status).label }}</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button class="btn-kam-cancel" @click="closeHistoryModal">Close</button>
            </div>
        </div>
    </div>

    <!-- Void Payment Modal -->
    <div v-if="showVoidModal" class="modal-overlay" @click.self="closeVoidModal">
        <div class="modal-box">
            <div class="modal-header">
                <h5>Void Payment</h5>
                <button class="modal-close" @click="closeVoidModal"><i class="fa fa-times"></i></button>
            </div>
            <div class="modal-body">
                <label class="decline-label">Reason for voiding this payment</label>
                <textarea v-model="voidNote" class="decline-textarea" rows="4"
                    placeholder="Add a note explaining why this payment is being voided..."></textarea>
                <p v-if="voidError" class="error-text">{{ voidError }}</p>
            </div>
            <div class="modal-footer">
                <button class="btn-decline-cancel" :disabled="voiding" @click="closeVoidModal">Cancel</button>
                <button class="btn-decline-solid" :disabled="voiding" @click="confirmVoid">
                    {{ voiding ? 'Submitting...' : 'Void Payment' }}
                </button>
            </div>
        </div>
    </div>

    <!-- Group Request Details Modal -->
    <div v-if="showGroupDetailModal" class="modal-overlay" @click.self="closeGroupDetailModal">
        <div class="gdm-box">
            <div class="gdm-header">
                <span class="gdm-accent"></span>
                <h5>Group Request Details</h5>
                <button class="gdm-close" @click="closeGroupDetailModal"><i class="fa fa-times"></i></button>
            </div>
            <div class="gdm-body">
                <div v-if="groupDetailLoading" class="gdm-loading">
                    <i class="fa-solid fa-spinner fa-spin me-2"></i>Loading group details...
                </div>
                <div v-else-if="groupDetailData" class="gdm-grid">
                    <template v-if="groupDetailData.request_type !== 'multicity'">
                        <div class="gdm-cell gdm-col-l">
                            <div class="gdm-label">Group Type:</div>
                            <div class="gdm-value">{{ groupDetailData.group_type || '-' }}</div>
                        </div>
                        <div class="gdm-cell gdm-col-r">
                            <div class="gdm-label">Way Type:</div>
                            <div class="gdm-value">{{ wayTypeLabel(groupDetailData.request_type) }}</div>
                        </div>

                        <div class="gdm-cell gdm-col-l">
                            <div class="gdm-label">From:</div>
                            <div class="gdm-value">{{ groupDetailData.origin || '-' }}</div>
                            <div class="gdm-sub">{{ groupDetailData.departure_date ? moment(groupDetailData.departure_date).format('DD-MMM-YYYY | hh:mm A') : '-' }}</div>
                        </div>
                        <div class="gdm-cell gdm-col-r">
                            <div class="gdm-label">To:</div>
                            <div class="gdm-value">{{ groupDetailData.destination || '-' }}</div>
                            <div class="gdm-sub">{{ groupDetailData.departure_date ? moment(groupDetailData.departure_date).format('DD-MMM-YYYY | hh:mm A') : '-' }}</div>
                        </div>

                        <template v-if="wayTypeLabel(groupDetailData.request_type) === 'Round Way'">
                            <div class="gdm-cell gdm-col-l">
                                <div class="gdm-label">Return From:</div>
                                <div class="gdm-value">{{ groupDetailData.return_origin || '-' }}</div>
                                <div class="gdm-sub">{{ groupDetailData.return_date ? moment(groupDetailData.return_date).format('DD-MMM-YYYY | hh:mm A') : '-' }}</div>
                            </div>
                            <div class="gdm-cell gdm-col-r">
                                <div class="gdm-label">Return To:</div>
                                <div class="gdm-value">{{ groupDetailData.return_destination || '-' }}</div>
                                <div class="gdm-sub">{{ groupDetailData.return_date ? moment(groupDetailData.return_date).format('DD-MMM-YYYY | hh:mm A') : '-' }}</div>
                            </div>
                        </template>
                    </template>
                    <template v-else>
                        <div class="gdm-cell gdm-col-l">
                            <div class="gdm-label">Group Type:</div>
                            <div class="gdm-value">{{ groupDetailData.group_type || '-' }}</div>
                        </div>
                        <div class="gdm-cell gdm-col-r">
                            <div class="gdm-label">Way Type:</div>
                            <div class="gdm-value">{{ wayTypeLabel(groupDetailData.request_type) }}</div>
                        </div>
                        <div class="gdm-cell gdm-cell-full">
                            <div class="gdm-label">Segments:</div>
                            <div class="gdm-value gdm-segment" v-for="seg in (groupDetailData.segments || [])" :key="seg.id">
                                {{ seg.origin }} - {{ seg.destination }}
                                <span class="gdm-sub-inline">{{ seg.departure_date ? moment(seg.departure_date).format('DD-MMM-YYYY | hh:mm A') : '' }}</span>
                            </div>
                        </div>
                    </template>

                    <div class="gdm-cell gdm-col-l">
                        <div class="gdm-label">Preferred Airlines & Flight No.:</div>
                        <div class="gdm-value">{{ groupDetailData.preferred_flight || '-' }}<span v-if="groupDetailData.flight_no"> | {{ groupDetailData.flight_no }}</span></div>
                    </div>
                    <div class="gdm-cell gdm-col-r">
                        <div class="gdm-label">Preferred Class & Code:</div>
                        <div class="gdm-value">{{ groupDetailData.class_type || '-' }}<span v-if="groupDetailData.class_code"> ({{ groupDetailData.class_code }})</span></div>
                    </div>

                    <div class="gdm-cell gdm-col-l">
                        <div class="gdm-label">Total PAX:</div>
                        <div class="gdm-value gdm-pax">
                            <span><i class="fa fa-person"></i> {{ groupDetailData.adult_traveler || 0 }}</span>
                            <span><i class="fa fa-child"></i> {{ groupDetailData.child_traveler || 0 }}</span>
                            <span><i class="fa fa-baby"></i> {{ groupDetailData.infant_traveler || 0 }}</span>
                        </div>
                    </div>
                    <div class="gdm-cell gdm-col-r">
                        <div class="gdm-label">Requested Fare:</div>
                        <div class="gdm-value gdm-fare">{{ groupDetailData.currency || 'BDT' }} {{ formatAmount(requestedFareTotal(groupDetailData)) }}</div>
                    </div>

                    <div class="gdm-cell gdm-cell-full">
                        <div class="gdm-label">Special Request:</div>
                        <div class="gdm-value">{{ groupDetailData.special_requirements || '-' }}</div>
                    </div>
                    <div class="gdm-cell gdm-cell-full gdm-last">
                        <div class="gdm-label">Remarks:</div>
                        <div class="gdm-value">{{ groupDetailData.remarks || groupDetailData.details_requirements || '-' }}</div>
                    </div>
                </div>
                <div v-else class="gdm-loading">No details found.</div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Stats card */
.info-agency {
    box-shadow: 0 0 1px rgba(0, 0, 0, .125), 0 1px 3px rgba(0, 0, 0, .2);
    border-radius: .25rem;
    background-image: linear-gradient(to right top, #dae9f8, #dae9f8, #dae9f8, #dae9f8, #dae9f8, #cbdff4, #bcd6f1, #aecced, #8eb6e4, #6da1dc, #4a8bd2, #1576c9);
    display: flex;
    margin-bottom: 1rem;
    min-height: 90px;
    padding: .5rem;
    width: 100%;
}

.info-agency .info-agency-icon {
    border-radius: .25rem;
    align-items: center;
    display: flex;
    font-size: 1.875rem;
    justify-content: center;
    width: 70px;
}

.info-agency .info-agency-content {
    display: flex;
    flex-direction: column;
    justify-content: center;
    line-height: 1.5;
    flex: 1;
    padding: 0 30px;
}

.info-agency .info-agency-text {
    font-size: 16px;
    color: #838587;
}

.info-agency .info-agency-number {
    font-weight: 700;
    font-size: 22px;
}

.elevation-1 {
    box-shadow: 0 1px 3px rgba(0, 0, 0, .12), 0 1px 2px rgba(0, 0, 0, .24) !important;
}

.bg-info {
    background-color: #0880e1 !important;
    color: #fff !important;
}

/* Filters */
.filter-field {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.filter-field-sm select {
    min-width: 140px;
}

.filter-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #64748b;
    margin: 0;
}

/* Export toolbar */
.btn-export {
    border: none;
    border-radius: 6px;
    padding: 0.4rem 0.9rem;
    font-size: 0.8rem;
    font-weight: 600;
    color: #fff;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    cursor: pointer;
}

.btn-export-pdf { background: #dc2626; }
.btn-export-pdf:hover { background: #b91c1c; }
.btn-export-excel { background: #16a34a; }
.btn-export-excel:hover { background: #15803d; }
.btn-export-csv { background: #2563eb; }
.btn-export-csv:hover { background: #1d4ed8; }

.search-box {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border: 1px solid #dbe3ee;
    border-radius: 8px;
    padding: 0.4rem 0.75rem;
    background: #fff;
    min-width: 260px;
    color: #94a3b8;
}

.search-box input {
    border: none;
    outline: none;
    flex: 1;
    font-size: 0.85rem;
    color: #334155;
}

/* Table cell styles */
.cell-main {
    font-weight: 600;
    color: #33415c;
    line-height: 1.35;
}

.cell-sub {
    margin-top: 3px;
    font-size: 11px;
    color: #7c8ba5;
    line-height: 1.3;
}

.cell-link {
    margin-top: 3px;
    font-size: 11px;
    color: #3f7dd8;
    font-weight: 600;
    line-height: 1.3;
}

.cell-tagline {
    margin-top: 3px;
    font-size: 11px;
    color: #8526c4;
    font-weight: 600;
}

.amount-text {
    color: #2f557f;
}

.tiny {
    font-size: 7px;
}

.status-pill {
    border-radius: 999px;
    padding: 3px 10px;
    font-size: 10px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    text-transform: none;
}

.status-success {
    color: #0f9a59;
    background: #ecfdf3;
    border: 1px solid #b5e9cc;
}

.status-void {
    color: #c84545;
    background: #fff2f2;
    border: 1px solid #f4c5c5;
}

.fare-cell {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.status-cell {
    display: inline-flex;
    align-items: center;
}

.fb-info-icon {
    font-size: 0.78rem;
    color: #93c5fd;
    cursor: pointer;
    transition: color 0.15s ease, transform 0.15s ease;
}

.fb-info-icon:hover {
    color: #2563eb;
    transform: scale(1.1);
}

/* Action icon colors */
:deep(.action-btn-view-payment) { --action-btn-color: #f1892a; --action-btn-bg: #fef3e8; }
:deep(.action-btn-void-payment) { --action-btn-color: #dc2626; --action-btn-bg: #fdecec; }
:deep(.action-btn-history-payment) { --action-btn-color: #0891b2; --action-btn-bg: #e6f9fa; }
:deep(.action-btn-restore-payment) { --action-btn-color: #0891b2; --action-btn-bg: #e6f9fa; }

/* Modal styles */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(17, 24, 39, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1050;
    padding: 1rem;
}

.modal-box {
    background: #fff;
    border-radius: 14px;
    width: 100%;
    max-width: 440px;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
}

.modal-box-lg {
    max-width: 640px;
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.1rem 1.25rem;
    border-bottom: 1px solid #f3f4f6;
}

.modal-header h5 {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: #111827;
}

.modal-close {
    border: none;
    background: transparent;
    color: #9ca3af;
    cursor: pointer;
    font-size: 1rem;
}

.modal-close:hover {
    color: #374151;
}

.modal-body {
    padding: 1.25rem;
    max-height: 70vh;
    overflow-y: auto;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    border-top: 1px solid #f3f4f6;
}

.kam-field {
    margin-bottom: 1rem;
}

.kam-field:last-child {
    margin-bottom: 0;
}

.field-label {
    display: block;
    font-size: 0.82rem;
    font-weight: 600;
    color: #6b7280;
    margin-bottom: 0.5rem;
}

.field-input {
    width: 100%;
    border: 1.5px solid #e5e7eb;
    border-radius: 10px;
    padding: 0.65rem 0.85rem;
    font-size: 0.9rem;
    color: #111827;
    background: #f9fafb;
}

.field-input:disabled {
    background: #f3f4f6;
    color: #6b7280;
    cursor: not-allowed;
}

.decline-label {
    display: block;
    font-size: 0.82rem;
    font-weight: 600;
    color: #6b7280;
    margin-bottom: 0.5rem;
}

.decline-textarea {
    width: 100%;
    border: 1.5px solid #e5e7eb;
    border-radius: 10px;
    padding: 0.75rem;
    font-size: 0.9rem;
    font-family: inherit;
    resize: vertical;
}

.decline-textarea:focus {
    outline: none;
    border-color: #ef4444;
}

.error-text {
    color: #dc2626;
    font-size: 0.8rem;
    margin: 0.5rem 0 0;
}

.btn-decline-cancel,
.btn-kam-cancel {
    padding: 0.6rem 1.25rem;
    border-radius: 10px;
    font-size: 0.875rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    background: #f3f4f6;
    color: #374151;
}

.btn-decline-cancel:hover:not(:disabled),
.btn-kam-cancel:hover:not(:disabled) {
    background: #e5e7eb;
}

.btn-decline-cancel:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-decline-solid {
    padding: 0.6rem 1.25rem;
    border-radius: 10px;
    font-size: 0.875rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    background: #ef4444;
    color: #fff;
}

.btn-decline-solid:hover:not(:disabled) {
    background: #dc2626;
}

.btn-decline-solid:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.history-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}

.history-table th {
    text-align: left;
    padding: 0.6rem 0.75rem;
    background: #f8fafc;
    font-weight: 600;
    color: #374151;
    border-bottom: 1px solid #e5e7eb;
}

.history-table td {
    padding: 0.6rem 0.75rem;
    border-bottom: 1px solid #f1f5f9;
    color: #4b5563;
}

/* View Details modal (donut ring) */
.vdm-box {
    position: relative;
    width: 100%;
    max-width: 560px;
}

.vdm-close {
    position: absolute;
    top: -14px;
    right: -14px;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    border: none;
    background: #111827;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.9rem;
    box-shadow: 0 6px 14px rgba(0, 0, 0, 0.3);
    z-index: 1;
}

.vdm-close:hover {
    background: #1f2937;
}

.vdm-inner {
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.3);
    padding: 2rem 1.75rem 1.5rem;
}

.vdm-ring-wrap {
    display: flex;
    justify-content: center;
    margin-bottom: 1.75rem;
}

.vdm-ring {
    width: 220px;
    height: 220px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
}

.vdm-ring-inner {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: #fff;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
    text-align: center;
}

.vdm-ring-label {
    font-size: 0.8rem;
    color: #9ca3af;
    margin-bottom: 0.35rem;
}

.vdm-ring-value {
    font-size: 1.35rem;
    font-weight: 800;
    color: #2563eb;
    margin-bottom: 0.6rem;
}

.vdm-ring-meta {
    font-size: 0.8rem;
    color: #6b7280;
    line-height: 1.6;
}

.vdm-info-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    border: 1px solid #eef2f7;
    border-radius: 10px;
    overflow: hidden;
}

.vdm-info-cell {
    padding: 0.9rem 1rem;
    border-right: 1px solid #eef2f7;
}

.vdm-info-cell-last {
    border-right: none;
}

.vdm-info-label {
    font-size: 0.72rem;
    font-weight: 700;
    color: #111827;
    margin-bottom: 0.4rem;
}

.vdm-info-value {
    font-size: 0.82rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.2rem;
}

.vdm-info-link {
    font-size: 0.78rem;
    font-weight: 700;
    color: #3b82f6;
}

.vdm-void-note {
    margin-top: 1rem;
    padding: 0.9rem 1rem;
    background: #fff2f2;
    border: 1px solid #f4c5c5;
    border-radius: 10px;
}

.vdm-void-note .vdm-info-value {
    color: #b91c1c;
    margin-bottom: 0;
}

/* Group Request Details modal */
.gdm-box {
    background: #fff;
    border-radius: 16px;
    width: 100%;
    max-width: 760px;
    overflow: hidden;
    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.25);
}

.gdm-header {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 1.1rem 1.5rem;
    border-bottom: 1px solid #eef2f7;
}

.gdm-accent {
    width: 4px;
    height: 20px;
    border-radius: 3px;
    background: #3b82f6;
    flex-shrink: 0;
}

.gdm-header h5 {
    flex: 1;
    margin: 0;
    font-size: 1.05rem;
    font-weight: 700;
    color: #111827;
}

.gdm-close {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: none;
    background: #111827;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.85rem;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.25);
    flex-shrink: 0;
}

.gdm-close:hover {
    background: #1f2937;
}

.gdm-body {
    max-height: 75vh;
    overflow-y: auto;
}

.gdm-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
}

.gdm-cell {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #eef2f7;
}

.gdm-col-l {
    border-right: 1px solid #eef2f7;
}

.gdm-cell-full {
    grid-column: 1 / -1;
}

.gdm-last {
    border-bottom: none;
}

.gdm-label {
    font-size: 0.78rem;
    color: #94a3b8;
    margin-bottom: 0.3rem;
}

.gdm-value {
    font-size: 0.92rem;
    font-weight: 600;
    color: #1f2937;
}

.gdm-sub {
    margin-top: 0.3rem;
    font-size: 0.78rem;
    color: #3b82f6;
    font-weight: 600;
}

.gdm-sub-inline {
    margin-left: 0.5rem;
    font-size: 0.75rem;
    color: #3b82f6;
    font-weight: 600;
}

.gdm-segment {
    margin-bottom: 0.35rem;
}

.gdm-segment:last-child {
    margin-bottom: 0;
}

.gdm-pax {
    display: flex;
    gap: 1rem;
}

.gdm-pax i {
    color: #3b82f6;
    margin-right: 0.25rem;
}

.gdm-fare {
    color: #2563eb;
    font-weight: 700;
    font-size: 1rem;
}

.gdm-loading {
    padding: 2.5rem;
    text-align: center;
    color: #64748b;
}

/* Dark mode */
[data-bs-theme="dark"] .cell-main { color: #e2e8f0; }
[data-bs-theme="dark"] .cell-sub { color: #94a3b8; }
[data-bs-theme="dark"] .cell-link { color: #60a5fa; }
[data-bs-theme="dark"] .cell-tagline { color: #a78bfa; }
[data-bs-theme="dark"] .amount-text { color: #93c5fd; }
[data-bs-theme="dark"] .status-success { color: #4ade80; background: rgba(34, 197, 94, 0.15); border-color: rgba(34, 197, 94, 0.3); }
[data-bs-theme="dark"] .status-void { color: #f87171; background: rgba(239, 68, 68, 0.15); border-color: rgba(239, 68, 68, 0.3); }
[data-bs-theme="dark"] .search-box { background: #1e293b; border-color: #475569; }
[data-bs-theme="dark"] .search-box input { color: #e2e8f0; background: transparent; }
[data-bs-theme="dark"] .modal-overlay { background: rgba(0, 0, 0, 0.7); }
[data-bs-theme="dark"] .modal-box { background: #1e293b; }
[data-bs-theme="dark"] .modal-header { border-bottom-color: #334155; }
[data-bs-theme="dark"] .modal-header h5 { color: #e2e8f0; }
[data-bs-theme="dark"] .modal-close { color: #94a3b8; }
[data-bs-theme="dark"] .modal-footer { border-top-color: #334155; }
[data-bs-theme="dark"] .field-label,
[data-bs-theme="dark"] .decline-label,
[data-bs-theme="dark"] .filter-label { color: #94a3b8; }
[data-bs-theme="dark"] .field-input,
[data-bs-theme="dark"] .decline-textarea { background-color: #1e293b; color: #e2e8f0; border-color: #475569; }
[data-bs-theme="dark"] .field-input:disabled { background-color: #334155; }
[data-bs-theme="dark"] .btn-kam-cancel,
[data-bs-theme="dark"] .btn-decline-cancel { background: #334155; color: #e2e8f0; }
[data-bs-theme="dark"] .btn-kam-cancel:hover:not(:disabled),
[data-bs-theme="dark"] .btn-decline-cancel:hover:not(:disabled) { background: #475569; }
[data-bs-theme="dark"] .history-table th { background: #273449; color: #e2e8f0; border-color: #334155; }
[data-bs-theme="dark"] .history-table td { color: #cbd5e1; border-color: #334155; }
[data-bs-theme="dark"] .gdm-box { background: #1e293b; }
[data-bs-theme="dark"] .gdm-header { border-bottom-color: #334155; }
[data-bs-theme="dark"] .gdm-header h5 { color: #e2e8f0; }
[data-bs-theme="dark"] .gdm-cell { border-bottom-color: #334155; }
[data-bs-theme="dark"] .gdm-col-l { border-right-color: #334155; }
[data-bs-theme="dark"] .gdm-label { color: #94a3b8; }
[data-bs-theme="dark"] .gdm-value { color: #e2e8f0; }
[data-bs-theme="dark"] .gdm-loading { color: #94a3b8; }
[data-bs-theme="dark"] .fb-info-icon { color: #3b82f6; }
</style>

<style>
/* Fare breakdown card content (unscoped: used inside both the tippy tooltip's
   teleported root and the View Details modal via v-html, so scoped attrs don't reach it) */
.tippy-box.fare-card-theme {
    background: transparent;
    color: inherit;
}

.tippy-box.fare-card-theme > .tippy-content {
    padding: 0;
}

.fare-breakdown-card {
    background: #ffffff;
    border: 1px solid #dbeafe;
    border-radius: 12px;
    padding: 0.9rem 1.1rem;
    min-width: 230px;
    box-shadow: 0 12px 32px rgba(37, 99, 235, 0.16), 0 2px 8px rgba(15, 23, 42, 0.08);
}

.fare-breakdown-card .fb-title {
    position: relative;
    padding-left: 0.6rem;
    font-weight: 700;
    color: #2563eb;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    font-size: 0.68rem;
    margin-bottom: 0.3rem;
}

.fare-breakdown-card .fb-title::before {
    content: '';
    position: absolute;
    left: 0;
    top: 1px;
    bottom: 1px;
    width: 3px;
    border-radius: 2px;
    background: #3b82f6;
}

.fare-breakdown-card .fb-title-spaced {
    margin-top: 0.5rem;
}

.fare-breakdown-card .fb-row {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    font-size: 0.78rem;
    padding: 0.18rem 0;
    color: #475569;
}

.fare-breakdown-card .fb-divider {
    height: 1px;
    background: #e5edf9;
    margin: 0.45rem 0;
}

.fare-breakdown-card .fb-strong {
    font-weight: 700;
    color: #1e293b;
}

.fare-breakdown-card .fb-total-row {
    background: #eff6ff;
    margin: 0 -0.4rem;
    padding: 0.35rem 0.4rem;
    border-radius: 8px;
}

.fare-breakdown-card .fb-due-row {
    background: #fef2f2;
    margin: 0.2rem -0.4rem 0;
    padding: 0.35rem 0.4rem;
    border-radius: 8px;
}

.fare-breakdown-card .fb-blue {
    color: #2563eb;
    font-weight: 700;
}

.fare-breakdown-card .fb-red {
    color: #dc2626;
    font-weight: 700;
}

[data-bs-theme="dark"] .fare-breakdown-card {
    background: #1e293b;
    border-color: #334155;
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.4), 0 2px 8px rgba(0, 0, 0, 0.3);
}

[data-bs-theme="dark"] .fare-breakdown-card .fb-row {
    color: #cbd5e1;
}

[data-bs-theme="dark"] .fare-breakdown-card .fb-strong {
    color: #f1f5f9;
}

[data-bs-theme="dark"] .fare-breakdown-card .fb-divider {
    background: #334155;
}

[data-bs-theme="dark"] .fare-breakdown-card .fb-total-row {
    background: rgba(59, 130, 246, 0.12);
}

[data-bs-theme="dark"] .fare-breakdown-card .fb-due-row {
    background: rgba(239, 68, 68, 0.12);
}

[data-bs-theme="dark"] .fare-breakdown-card .fb-blue {
    color: #60a5fa;
}

[data-bs-theme="dark"] .fare-breakdown-card .fb-red {
    color: #f87171;
}
</style>

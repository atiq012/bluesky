<script setup>
import AppBreadcrumbs from '../../common/AppBreadcrumbs.vue';
import { ref, onMounted } from "vue";
import { useRoute } from 'vue-router';
import axiosInstance from "../../../axiosInstance";
import moment from "moment";
import * as XLSX from "xlsx";

const route = useRoute();
const loading = ref(true);
const paxList = ref([]);

const TEMPLATE_HEADERS = ['Title', 'First Name', 'Last Name', 'Date of Birth', 'Gender', 'Nationality', 'Passport No.', 'Expiry Date', 'Contact'];

onMounted(async () => {
    if (route.params.id) {
        await getUploadedPAX(route.params.id);
    }
});

function formatDate(val) {
    if (!val) return '';
    const m = moment(val);
    return m.isValid() ? m.format('DD-MMM-YYYY') : String(val);
}

async function getUploadedPAX(id) {
    try {
        loading.value = true;
        const response = await axiosInstance.get(`group-pax-upload/${id}`);
        paxList.value = response.data?.data ?? [];
    } catch (error) {
        console.error('Failed to fetch uploaded PAX:', error);
        Notification.showToast('e', error.response?.data?.message || 'Failed to load uploaded PAX information.');
    } finally {
        loading.value = false;
    }
}

function downloadExcel() {
    if (!paxList.value.length) {
        Notification.showToast('e', 'No PAX data to download.');
        return;
    }
    const rows = paxList.value.map((p) => ({
        Title: p.title,
        'First Name': p.first_name,
        'Last Name': p.last_name,
        'Date of Birth': formatDate(p.dob),
        Gender: p.gender,
        Nationality: p.nationality,
        'Passport No.': p.passport_no,
        'Expiry Date': formatDate(p.expiry_date),
        Contact: p.phone,
    }));
    const ws = XLSX.utils.json_to_sheet(rows, { header: TEMPLATE_HEADERS });
    ws['!cols'] = TEMPLATE_HEADERS.map(() => ({ wch: 16 }));
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'PAX List');
    XLSX.writeFile(wb, 'pax-list.xlsx');
}
</script>

<template>
    <div class="group-page">
        <AppBreadcrumbs title="Group Management" :back-to="{ name: 'groupList' }" :breadcrumbs="[
            { label: 'Dashboard', to: { name: 'Home' } },
            { label: 'Group Management', to: { name: 'groupList' } },
            { label: 'Groups', to: { name: 'groupList' } },
            { label: 'PAX Info' }]">
        </AppBreadcrumbs>

        <!-- Loading State -->
        <div v-if="loading" class="view-loading">
            <div class="spinner-box">
                <div class="spinner-ring"></div>
                <span>Loading PAX information...</span>
            </div>
        </div>

        <!-- Main Content -->
        <div v-else class="pax-upload-card">
            <div class="d-flex justify-content-end mb-3">
                <button type="button" class="btn-action btn-export" @click="downloadExcel">
                    <i class="fa-solid fa-file-excel me-2"></i>Download Excel
                </button>
            </div>

            <div class="table-responsive pax-table-wrap">
                <table class="table pax-table align-middle">
                    <thead>
                        <tr>
                            <th>SL</th>
                            <th>Title</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Date of Birth</th>
                            <th>Gender</th>
                            <th>Nationality</th>
                            <th>Passport No.</th>
                            <th>Expiry Date</th>
                            <th>Contact</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!paxList.length">
                            <td colspan="9" class="text-center text-muted py-4">No PAX information found for this
                                group.</td>
                        </tr>
                        <tr v-for="pax in paxList" :key="pax.id">
                            <td>{{ paxList.indexOf(pax) + 1 }}</td>
                            <td>{{ pax.title }}</td>
                            <td>{{ pax.first_name }}</td>
                            <td>{{ pax.last_name }}</td>
                            <td>{{ formatDate(pax.dob) }}</td>
                            <td>{{ pax.gender }}</td>
                            <td>{{ pax.nationality }}</td>
                            <td>{{ pax.passport_no }}</td>
                            <td>{{ formatDate(pax.expiry_date) }}</td>
                            <td>{{ pax.phone }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<style scoped>
.btn-action {
    padding: 0.6rem 1.5rem;
    border-radius: 10px;
    font-size: 0.875rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    transition: all 0.2s ease;
    font-family: inherit;
}

.btn-export {
    background: linear-gradient(135deg, #059669, #047857);
    color: #fff;
}

.btn-export:hover {
    filter: brightness(1.05);
}

.pax-upload-card {
    background: #fff;
    border-radius: 14px;
    border: 1px solid #f1f5f9;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    padding: 1.5rem;
}

.pax-table-wrap {
    border: 1px solid #f1f5f9;
    border-radius: 10px;
    overflow: hidden;
}

.pax-table {
    margin: 0;
}

.pax-table thead th {
    background: #f8fafc;
    color: #475569;
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}

.pax-table tbody tr:nth-child(even) {
    background: #f8fafc;
}

.pax-table tbody td {
    font-size: 0.875rem;
    color: #1e293b;
    white-space: nowrap;
}

/* ─── Loading State ─────────────────────────────── */
.view-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 400px;
}

.spinner-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1.25rem;
    color: #6b7280;
    font-size: 0.9rem;
    font-weight: 500;
}

.spinner-ring {
    width: 44px;
    height: 44px;
    border: 3.5px solid #e5e7eb;
    border-top-color: #3b82f6;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* ─── Dark mode ───────────────────────────────────── */
[data-bs-theme="dark"] .pax-upload-card {
    background: #1e293b;
    border-color: #334155;
}

[data-bs-theme="dark"] .pax-table-wrap {
    border-color: #334155;
}

[data-bs-theme="dark"] .pax-table thead th {
    background: #0f172a;
    color: #94a3b8;
    border-color: #334155;
}

[data-bs-theme="dark"] .pax-table tbody td {
    color: #e2e8f0;
}

[data-bs-theme="dark"] .pax-table tbody tr:nth-child(even) {
    background: #24324a;
}
</style>

<script setup>
import AppBreadcrumbs from '../../common/AppBreadcrumbs.vue';
import AppModal from '../../common/AppModal.vue';
import AppDatePicker from '../../common/AppDatePicker.vue';
import AppTooltip from '../../common/AppTooltip.vue';
import PhoneInput from '../../common/PhoneInput.vue';
import { ref, reactive, computed, onMounted } from "vue";
import { useRouter, useRoute } from 'vue-router';
import axiosInstance from "../../../axiosInstance";
import { useAuthStore } from '../../../stores/authStore';
import moment from "moment";
import * as XLSX from "xlsx";

const authStore = useAuthStore();
const router = useRouter();
const route = useRoute();
const loading = ref(true);
const data = ref(null);
const submitting = ref(false);
const isDragging = ref(false);
const fileInput = ref(null);

const TEMPLATE_HEADERS = ['Title', 'First Name', 'Last Name', 'Date of Birth', 'Gender', 'Nationality', 'Passport No.', 'Expiry Date', 'Contact'];

const paxList = ref([]);

const emptyForm = () => ({
    pax_type: 'Adult',
    title: 'Mr.',
    first_name: '',
    last_name: '',
    dob: '',
    gender: 'Male',
    nationality: '',
    passport_no: '',
    expiry_date: '',
    email: '',
    contact: '',
    dial_code: '+88',
});

const showEntryModal = ref(false);
const editIndex = ref(null);
const isPanelOpen = ref(true);
const form = reactive(emptyForm());

const existingTravelerQuery = ref('');
const showExistingList = ref(false);
const existingTravelers = ref([]);

const totalPax = computed(() => Number(data.value?.seats ?? data.value?.total_seat ?? 0) || 0);
const fillUp = computed(() => paxList.value.length);
const remainingPax = computed(() => Math.max(totalPax.value - fillUp.value, 0));
const fillRatio = computed(() => (totalPax.value ? Math.min(fillUp.value / totalPax.value, 1) : 0));
const ringCircumference = 2 * Math.PI * 42;
const ringOffset = computed(() => ringCircumference * (1 - fillRatio.value));

onMounted(async () => {
    if (route.params.id) {
        await getAllDataOfGroup(route.params.id);
    }
});

async function getAllDataOfGroup(id) {
    try {
        loading.value = true;
        const response = await axiosInstance.post('edit-group-request/data', { id: id });
        const raw = response.data.data ?? response.data[0] ?? response.data;
        data.value = raw;
    } catch (error) {
        console.error('Failed to fetch group data:', error);
        Notification.showToast('error', error.response?.data?.message || 'Failed to load group data');
    } finally {
        loading.value = false;
    }
}

function goBack() {
    router.push({ name: 'groupList' });
}

/* ─── Template / Export ─────────────────────────── */

function downloadTemplate() {
    const ws = XLSX.utils.aoa_to_sheet([TEMPLATE_HEADERS]);
    ws['!cols'] = TEMPLATE_HEADERS.map(() => ({ wch: 16 }));
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'PAX Template');
    XLSX.writeFile(wb, 'pax-template.xlsx');
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
        'Date of Birth': p.dob,
        Gender: p.gender,
        Nationality: p.nationality,
        'Passport No.': p.passport_no,
        'Expiry Date': p.expiry_date,
        Contact: p.contact,
    }));
    const ws = XLSX.utils.json_to_sheet(rows, { header: TEMPLATE_HEADERS });
    ws['!cols'] = TEMPLATE_HEADERS.map(() => ({ wch: 16 }));
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'PAX List');
    XLSX.writeFile(wb, 'pax-list.xlsx');
}

/* ─── Upload / Parse ─────────────────────────────── */

function triggerFileInput() {
    fileInput.value?.click();
}

function onFileChange(event) {
    const file = event.target.files?.[0];
    if (file) parseFile(file);
    event.target.value = '';
}

function onDrop(event) {
    isDragging.value = false;
    const file = event.dataTransfer?.files?.[0];
    if (file) parseFile(file);
}

function excelDateToDisplay(val) {
    if (val == null || val === '') return '';
    if (typeof val === 'number') {
        const parsed = XLSX.SSF.parse_date_code(val);
        if (parsed) return moment({ year: parsed.y, month: parsed.m - 1, day: parsed.d }).format('DD-MMM-YYYY');
    }
    const m = moment(val, ['DD-MMM-YYYY', 'YYYY-MM-DD', 'DD/MM/YYYY', 'MM/DD/YYYY', moment.ISO_8601], true);
    return m.isValid() ? m.format('DD-MMM-YYYY') : String(val);
}

function passportKey(passportNo) {
    return String(passportNo ?? '').trim().toUpperCase();
}

function isPassportNotExpired(expiryDate) {
    const m = moment(expiryDate, 'DD-MMM-YYYY', true);
    return m.isValid() && m.isSameOrAfter(moment(), 'day');
}

function parseFile(file) {
    const reader = new FileReader();
    reader.onload = (e) => {
        try {
            const wb = XLSX.read(e.target.result, { type: 'array', cellDates: false });
            const sheet = wb.Sheets[wb.SheetNames[0]];
            const rows = XLSX.utils.sheet_to_json(sheet, { defval: '' });

            const parsed = rows.map((row) => ({
                title: String(row['Title'] ?? row['title'] ?? '').trim() || 'Mr.',
                first_name: String(row['First Name'] ?? row['first_name'] ?? '').trim(),
                last_name: String(row['Last Name'] ?? row['last_name'] ?? '').trim(),
                dob: excelDateToDisplay(row['Date of Birth'] ?? row['dob']),
                gender: String(row['Gender'] ?? row['gender'] ?? '').trim() || 'Male',
                nationality: String(row['Nationality'] ?? row['nationality'] ?? '').trim(),
                passport_no: String(row['Passport No.'] ?? row['Passport No'] ?? row['passport_no'] ?? '').trim(),
                expiry_date: excelDateToDisplay(row['Expiry Date'] ?? row['expiry_date']),
                contact: String(row['Contact'] ?? row['contact'] ?? '').trim(),
            })).filter((p) => p.first_name || p.last_name || p.passport_no);

            if (!parsed.length) {
                Notification.showToast('e', 'No PAX rows found in file.');
                return;
            }

            const seenKeys = new Set(paxList.value.map((p) => passportKey(p.passport_no)).filter(Boolean));
            const validRows = [];
            let duplicateCount = 0;
            let expiredCount = 0;

            for (const p of parsed) {
                const key = passportKey(p.passport_no);
                if (key && seenKeys.has(key)) {
                    duplicateCount++;
                    continue;
                }
                if (p.expiry_date && !isPassportNotExpired(p.expiry_date)) {
                    expiredCount++;
                    continue;
                }
                if (key) seenKeys.add(key);
                validRows.push(p);
            }

            if (validRows.length) {
                paxList.value.push(...validRows);
                Notification.showToast('s', `${validRows.length} PAX row(s) loaded.`);
            }
            if (duplicateCount) {
                Notification.showToast('e', `${duplicateCount} row(s) skipped — duplicate passport number.`);
            }
            if (expiredCount) {
                Notification.showToast('e', `${expiredCount} row(s) skipped — passport expired or invalid expiry date.`);
            }
        } catch (error) {
            console.error('Failed to parse file:', error);
            Notification.showToast('e', 'Failed to read file. Please use the provided template.');
        }
    };
    reader.readAsArrayBuffer(file);
}

/* ─── Manual entry / edit ───────────────────────── */

function openAddModal() {
    editIndex.value = null;
    Object.assign(form, emptyForm());
    isPanelOpen.value = true;
    resetExistingTravelerSearch();
    showEntryModal.value = true;
}

function openEditModal(index) {
    editIndex.value = index;
    Object.assign(form, emptyForm(), paxList.value[index]);
    isPanelOpen.value = true;
    resetExistingTravelerSearch();
    showEntryModal.value = true;
}

function closeEntryModal() {
    showEntryModal.value = false;
}

function togglePanel() {
    isPanelOpen.value = !isPanelOpen.value;
}

function resetExistingTravelerSearch() {
    existingTravelerQuery.value = '';
    showExistingList.value = false;
    existingTravelers.value = [];
}

async function searchExistingTraveler(query) {
    existingTravelerQuery.value = query;
    if (!query) {
        showExistingList.value = false;
        return;
    }
    try {
        const response = await axiosInstance.post('get-travelers-data-by-search', { parm: query });
        existingTravelers.value = response.data || [];
        showExistingList.value = true;
    } catch (error) {
        console.error('Failed to search travelers:', error);
    }
}

async function selectExistingTraveler(traveler) {
    showExistingList.value = false;
    existingTravelerQuery.value = traveler.full_name || '';
    try {
        const response = await axiosInstance.post('viewTraveler', { id: traveler.id });
        const t = response.data;

        form.title = t.title || form.title;
        form.first_name = t.first_name || '';
        form.last_name = t.last_name || '';
        form.dob = t.dob ? moment(t.dob).format('DD-MMM-YYYY') : '';
        form.gender = t.gender || form.gender;
        form.nationality = t.nationality || '';
        form.passport_no = t.passport_number || '';
        form.expiry_date = t.passport_expiry_date ? moment(t.passport_expiry_date).format('DD-MMM-YYYY') : '';
        form.email = t.email || '';
        form.contact = t.phone || '';
    } catch (error) {
        console.error('Failed to load traveler:', error);
        Notification.showToast('e', 'Failed to load traveler details.');
    }
}

function saveEntry() {
    if (!form.first_name || !form.last_name || !form.passport_no) {
        Notification.showToast('e', 'First name, last name and passport no. are required.');
        return;
    }
    const key = passportKey(form.passport_no);
    const isDuplicate = paxList.value.some((p, idx) => idx !== editIndex.value && passportKey(p.passport_no) === key);
    if (isDuplicate) {
        Notification.showToast('e', 'This passport number is already added.');
        return;
    }
    if (form.expiry_date && !isPassportNotExpired(form.expiry_date)) {
        Notification.showToast('e', 'Passport expiry date is invalid or already expired.');
        return;
    }
    if (editIndex.value === null) {
        paxList.value.push({ ...form });
    } else {
        paxList.value.splice(editIndex.value, 1, { ...form });
    }
    showEntryModal.value = false;
}

function removeEntry(index) {
    paxList.value.splice(index, 1);
}

/* ─── Submit ─────────────────────────────────────── */

async function submitPaxList() {
    if (!paxList.value.length) {
        Notification.showToast('e', 'Please add at least one PAX.');
        return;
    }
    try {
        submitting.value = true;
        await axiosInstance.post('group-pax-upload/store', { id: route.params.id, pax: paxList.value });
        Notification.showToast('s', 'PAX information submitted successfully!');
        router.push({ name: 'groupList' });
    } catch (error) {
        console.error('Failed to submit PAX list:', error);
        Notification.showToast('e', error.response?.data?.message || 'Failed to submit PAX information.');
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div class="group-page">
        <AppBreadcrumbs title="Group Management" :back-to="{ name: 'groupList' }" :breadcrumbs="[
            { label: 'Dashboard', to: { name: 'Home' } },
            { label: 'Group Management', to: { name: 'groupList' } },
            { label: 'Groups', to: { name: 'groupList' } },
            { label: 'PAX Info' }]">
            <template #actions>
                <div class="d-flex gap-2">
                    <button type="button" class="btn-action btn-manual" @click="openAddModal">Manual Entry</button>
                    <button type="button" class="btn-action btn-template" @click="downloadTemplate">
                        <i class="fa-solid fa-download me-2"></i>Download Template
                    </button>
                </div>
            </template>
        </AppBreadcrumbs>

        <!-- Loading State -->
        <div v-if="loading" class="view-loading">
            <div class="spinner-box">
                <div class="spinner-ring"></div>
                <span>Loading group details...</span>
            </div>
        </div>

        <!-- Main Content -->
        <div v-else-if="data" class="pax-upload-card">

            <div class="info-banner">
                <i class="fa-solid fa-circle-info"></i>
                <span>For PAX Information update, please download the template and insert PAX information. Then upload
                    the
                    template to show PAX information in below data table</span>
            </div>

            <div class="drop-zone" :class="{ 'drop-zone-active': isDragging }" @dragover.prevent="isDragging = true"
                @dragleave.prevent="isDragging = false" @drop.prevent="onDrop">
                <i class="fa-solid fa-upload drop-icon"></i>
                <div class="drop-text">
                    Drag &amp; Drop File or <a href="#" @click.prevent="triggerFileInput">Choose File</a> to Upload
                </div>
                <input ref="fileInput" type="file" class="d-none" accept=".xlsx,.xls,.csv" @change="onFileChange">
            </div>

            <div class="d-flex justify-content-end mt-3 mb-2">
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
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!paxList.length">
                            <td colspan="10" class="text-center text-muted py-4">No PAX added yet. Upload a file or use
                                Manual
                                Entry.</td>
                        </tr>
                        <tr v-for="(pax, index) in paxList" :key="index">
                            <td>{{ index + 1 }}</td>
                            <td>{{ pax.title }}</td>
                            <td>{{ pax.first_name }}</td>
                            <td>{{ pax.last_name }}</td>
                            <td>{{ pax.dob }}</td>
                            <td>{{ pax.gender }}</td>
                            <td>{{ pax.nationality }}</td>
                            <td>{{ pax.passport_no }}</td>
                            <td>{{ pax.expiry_date }}</td>
                            <td>{{ pax.contact }}</td>
                            <td class="text-end">
                                <button type="button" class="btn-edit-row" @click="openEditModal(index)">
                                    <i class="fa-solid fa-pencil me-1"></i>Edit
                                </button>
                                <button type="button" class="btn-remove-row" @click="removeEntry(index)">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="nav-actions">
                <button type="button" class="btn-action btn-back" @click="goBack">Back</button>
                <button type="button" class="btn-action btn-submit" :disabled="submitting" @click="submitPaxList">
                    <span v-if="submitting" class="spinner-border spinner-border-sm me-2"></span>Submit
                </button>
            </div>
        </div>

        <!-- No Data State -->
        <div v-else class="empty-state">
            <div class="empty-icon"><i class="fa fa-inbox"></i></div>
            <h5>No Data Found</h5>
            <p>The group request you're looking for doesn't exist or has been removed.</p>
            <button @click="goBack" class="btn-action btn-back"><i class="bi bi-arrow-left me-1"></i>Go Back</button>
        </div>

        <!-- Manual Entry / Edit Modal -->
        <AppModal :is-open="showEntryModal" :show-header="false" size="xl" @close="closeEntryModal = false">
            <div class="pax-modal-scroll">
                <div class="row g-3 p-3">
                    <div class="col-lg-8">
                        <div class="traveler-panel">
                            <button type="button" class="traveler-panel-header" @click="togglePanel">
                                <i class="fa-solid fa-user"></i>
                                <span>Traveler {{ editIndex === null ? paxList.length + 1 : editIndex + 1 }}</span>
                                <i class="fa-solid fa-chevron-up ms-auto" :class="{ 'rotate-180': !isPanelOpen }"></i>
                            </button>

                            <div v-show="isPanelOpen" class="traveler-panel-body">
                                <div class="passport-notice">
                                    <i class="fa-solid fa-circle-info"></i>
                                    <span>Please fill-up all the information below as same as given in your Passport, to
                                        avoid
                                        complications at immigration process</span>
                                </div>

                                <div class="mb-3 position-relative">
                                    <label class="form-label">Existing Traveler</label>
                                    <input type="text" class="form-control" v-model="existingTravelerQuery"
                                        placeholder="Search with name, phone, email or passport number"
                                        autocomplete="off" @input="searchExistingTraveler($event.target.value)">
                                    <div v-if="showExistingList" class="existing-traveler-list">
                                        <div v-for="traveler in existingTravelers" :key="traveler.id"
                                            class="existing-traveler-item" @click="selectExistingTraveler(traveler)">
                                            {{ traveler.full_name }}
                                        </div>
                                        <div v-if="!existingTravelers.length" class="existing-traveler-item text-muted">
                                            No match found
                                        </div>
                                    </div>
                                </div>

                                <div class="divider-text"><span>Or Fill-up the information below</span></div>

                                <div class="mb-3">
                                    <label class="form-label d-block">PAX Type:</label>
                                    <div class="d-flex align-items-center gap-4">
                                        <label class="pax-type-radio">
                                            <input type="radio" value="Adult" v-model="form.pax_type">
                                            <span class="pax-type-dot"></span>Adult
                                        </label>
                                        <label class="pax-type-radio">
                                            <input type="radio" value="Children" v-model="form.pax_type">
                                            <span class="pax-type-dot"></span>Children
                                        </label>
                                        <label class="pax-type-radio">
                                            <input type="radio" value="Infant" v-model="form.pax_type">
                                            <span class="pax-type-dot"></span>Infant
                                        </label>
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-2">
                                        <label class="form-label">Title</label>
                                        <select v-model="form.title" class="form-select">
                                            <option>Mr.</option>
                                            <option>Mrs.</option>
                                            <option>Ms.</option>
                                            <option>Miss.</option>
                                            <!-- <option>Master</option> -->
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label">First Name (Given Name)</label>
                                        <input v-model="form.first_name" type="text" class="form-control"
                                            placeholder="Enter First Name">
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label">Last Name (Sur Name)</label>
                                        <input v-model="form.last_name" type="text" class="form-control"
                                            placeholder="Enter Last Name">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Date of Birth</label>
                                        <AppDatePicker v-model="form.dob" placeholder="Select Date of Birth" />
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Gender</label>
                                        <select v-model="form.gender" class="form-select">
                                            <option value="">Select Gender</option>
                                            <option>Male</option>
                                            <option>Female</option>
                                            <option>Other</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Nationality</label>
                                        <select v-model="form.nationality" class="form-select">
                                            <option value="">Select Nationality</option>
                                            <option>Bangladeshi</option>
                                            <option>Indian</option>
                                            <option>Pakistani</option>
                                            <option>American</option>
                                            <option>British</option>
                                            <option>Canadian</option>
                                            <option>Australian</option>
                                            <option>Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Passport Number</label>
                                        <input v-model="form.passport_no" type="text" class="form-control"
                                            placeholder="Enter Passport Number">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">
                                            Expiry Date
                                            <AppTooltip
                                                content="Passport should be valid for at least 6 months from the travel date.">
                                                <i class="fa-solid fa-circle-info text-muted ms-1"></i>
                                            </AppTooltip>
                                        </label>
                                        <AppDatePicker v-model="form.expiry_date"
                                            placeholder="Select Passport Expiry Date"/>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Email</label>
                                        <input v-model="form.email" type="email" class="form-control"
                                            placeholder="Enter a Valid Email">
                                    </div>
                                    <div class="col-md-6">
                                        <PhoneInput v-model="form.contact" v-model:dial-code="form.dial_code"
                                            label="Phone" />
                                    </div>
                                    <div class="col-md-12">
                                        <div class="d-flex">
                                            <button type="button" class="btn-action btn-back me-auto"
                                                @click="closeEntryModal">Back</button>
                                            <button type="button" class="btn-action btn-submit d-end"
                                                @click="saveEntry">Save</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- <div class="traveler-panel-footer">
                            <button type="button" class="btn-action btn-back" @click="closeEntryModal">Back</button>
                            <button type="button" class="btn-action btn-submit" @click="saveEntry">Save</button>
                        </div> -->
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="pax-ring-card">
                            <svg class="ring-svg" viewBox="0 0 100 100">
                                <circle class="ring-track" cx="50" cy="50" r="42" />
                                <circle class="ring-fill" cx="50" cy="50" r="42" :stroke-dasharray="ringCircumference"
                                    :stroke-dashoffset="ringOffset" />
                            </svg>
                            <div class="ring-center">
                                <span class="ring-label">Total PAX</span>
                                <span class="ring-value">{{ totalPax }}</span>
                                <span class="ring-sub">Fill-up : {{ fillUp }}</span>
                                <span class="ring-sub">Remaining : {{ remainingPax }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppModal>
    </div>
</template>

<style scoped>
/* ─── Header actions ─────────────────────────────── */
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

.btn-manual {
    background: #fff;
    color: #1d4ed8;
    border: 1.5px solid #bfdbfe;
}

.btn-manual:hover {
    background: #eff6ff;
}

.btn-template {
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #fff;
}

.btn-template:hover {
    filter: brightness(1.05);
}

.btn-export {
    background: linear-gradient(135deg, #059669, #047857);
    color: #fff;
}

.btn-export:hover {
    filter: brightness(1.05);
}

.btn-back {
    background: #f3f4f6;
    color: #374151;
}

.btn-back:hover {
    background: #e5e7eb;
    color: #111827;
}

.btn-submit {
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #fff;
}

.btn-submit:hover {
    filter: brightness(1.05);
}

.btn-submit:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

/* ─── Card ────────────────────────────────────────── */
.pax-upload-card {
    background: #fff;
    border-radius: 14px;
    border: 1px solid #f1f5f9;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    padding: 1.5rem;
}

/* ─── Info banner ─────────────────────────────────── */
.info-banner {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fde68a;
    border-radius: 10px;
    padding: 0.85rem 1.1rem;
    font-size: 0.875rem;
    margin-bottom: 1.25rem;
}

.info-banner i {
    color: #d97706;
    font-size: 1rem;
    flex-shrink: 0;
}

/* ─── Drop zone ───────────────────────────────────── */
.drop-zone {
    border: 2px dashed #93c5fd;
    background: #eff6ff;
    border-radius: 12px;
    padding: 2.5rem 1.5rem;
    text-align: center;
    transition: all 0.2s ease;
}

.drop-zone-active {
    border-color: #6366f1;
    background: #eef2ff;
}

.drop-icon {
    font-size: 1.75rem;
    color: #6366f1;
    margin-bottom: 0.75rem;
    display: block;
}

.drop-text {
    font-size: 0.95rem;
    color: #475569;
}

.drop-text a {
    color: #2563eb;
    font-weight: 600;
    text-decoration: none;
}

.drop-text a:hover {
    text-decoration: underline;
}

/* ─── Table ───────────────────────────────────────── */
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

.pax-table tbody td {
    font-size: 0.875rem;
    color: #1e293b;
    white-space: nowrap;
}

.btn-edit-row {
    background: #fff;
    color: #2563eb;
    border: 1.5px solid #bfdbfe;
    border-radius: 8px;
    padding: 0.3rem 0.75rem;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
}

.btn-edit-row:hover {
    background: #eff6ff;
}

.btn-remove-row {
    background: #fff;
    color: #dc2626;
    border: 1.5px solid #fecaca;
    border-radius: 8px;
    width: 28px;
    height: 28px;
    margin-left: 0.4rem;
    cursor: pointer;
}

.btn-remove-row:hover {
    background: #fef2f2;
}

/* ─── Nav actions ─────────────────────────────────── */
.nav-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid #f3f4f6;
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

/* ─── Empty State ───────────────────────────────── */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 400px;
    text-align: center;
    gap: 0.75rem;
}

.empty-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: #9ca3af;
    margin-bottom: 0.5rem;
}

.empty-state h5 {
    font-size: 1.15rem;
    font-weight: 600;
    color: #374151;
    margin: 0;
}

.empty-state p {
    font-size: 0.875rem;
    color: #9ca3af;
    margin: 0 0 1rem;
    max-width: 360px;
}

/* ─── Manual entry modal scroll ─────────────────── */
.pax-modal-scroll {
    flex: 1;
    min-height: 0;
    overflow-x: hidden;
    overflow-y: auto;
}

/* ─── Traveler panel (manual entry modal) ───────── */
.traveler-panel {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.traveler-panel-header {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    width: 100%;
    background: #eef2fb;
    color: #2563eb;
    border: none;
    border-radius: 10px;
    padding: 0.85rem 1.1rem;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
}

.traveler-panel-header i.fa-chevron-up {
    color: #1e293b;
    transition: transform 0.2s ease;
}

.traveler-panel-header i.fa-chevron-up.rotate-180 {
    transform: rotate(180deg);
}

.traveler-panel-body {
    padding: 1.25rem 0.25rem 0;
}

.traveler-panel-footer {
    display: flex;
    justify-content: space-between;
    margin-top: 1.25rem;
    padding-top: 1rem;
    border-top: 1px solid #f3f4f6;
}

.passport-notice {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    background: #fef9ec;
    color: #775f23;
    border-radius: 8px;
    padding: 0.75rem 1rem;
    font-size: 0.82rem;
    margin-bottom: 1.1rem;
}

.passport-notice i {
    color: #f0b41b;
    flex-shrink: 0;
}

.existing-traveler-list {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 20;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 8px 24px rgba(30, 41, 59, 0.12);
    max-height: 220px;
    overflow-y: auto;
    margin-top: 0.25rem;
}

.existing-traveler-item {
    padding: 0.6rem 1rem;
    font-size: 0.85rem;
    cursor: pointer;
}

.existing-traveler-item:hover {
    background: #f1f5f9;
}

.divider-text {
    display: flex;
    align-items: center;
    text-align: center;
    color: #a1abb7;
    font-size: 0.72rem;
    margin: 1rem 0;
}

.divider-text::before,
.divider-text::after {
    content: '';
    flex: 1;
    border-top: 1px dashed #e2e8f0;
}

.divider-text span {
    padding: 0 0.75rem;
}

.pax-type-radio {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: #1e293b;
    cursor: pointer;
}

.pax-type-radio input {
    display: none;
}

.pax-type-dot {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    border: 2px solid #cbd5e1;
    display: inline-block;
    position: relative;
}

.pax-type-radio input:checked+.pax-type-dot {
    border-color: #2563eb;
}

.pax-type-radio input:checked+.pax-type-dot::after {
    content: '';
    position: absolute;
    inset: 3px;
    border-radius: 50%;
    background: #2563eb;
}

/* ─── Total PAX ring ──────────────────────────────── */
.pax-ring-card {
    height: 100%;
    min-height: 260px;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    background: #fafbfc;
    border-radius: 14px;
    border: 1px solid #f1f5f9;
}

.ring-svg {
    width: 200px;
    height: 200px;
    transform: rotate(-90deg);
}

.ring-track {
    fill: none;
    stroke: #e5e7eb;
    stroke-width: 8;
}

.ring-fill {
    fill: none;
    stroke: #2563eb;
    stroke-width: 8;
    stroke-linecap: round;
    transition: stroke-dashoffset 0.3s ease;
}

.ring-center {
    position: absolute;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.ring-label {
    font-size: 0.8rem;
    color: #94a3b8;
}

.ring-value {
    font-size: 1.75rem;
    font-weight: 700;
    color: #2563eb;
    line-height: 1.3;
}

.ring-sub {
    font-size: 0.75rem;
    color: #94a3b8;
}

/* ─── Dark mode ───────────────────────────────────── */
[data-bs-theme="dark"] .pax-upload-card {
    background: #1e293b;
    border-color: #334155;
}

[data-bs-theme="dark"] .info-banner {
    background: #3f2d0f;
    color: #fbbf24;
    border-color: #78350f;
}

[data-bs-theme="dark"] .drop-zone {
    background: #1e2a3f;
    border-color: #3b4f7a;
}

[data-bs-theme="dark"] .drop-text {
    color: #94a3b8;
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

[data-bs-theme="dark"] .btn-manual {
    background: #1e293b;
    color: #93c5fd;
    border-color: #334155;
}

[data-bs-theme="dark"] .btn-back {
    background: #334155;
    color: #e2e8f0;
}

[data-bs-theme="dark"] .traveler-panel-header {
    background: #24324a;
    color: #93c5fd;
}

[data-bs-theme="dark"] .traveler-panel-header i.fa-chevron-up {
    color: #e2e8f0;
}

[data-bs-theme="dark"] .traveler-panel-footer {
    border-color: #334155;
}

[data-bs-theme="dark"] .passport-notice {
    background: #3f2d0f;
    color: #fbbf24;
}

[data-bs-theme="dark"] .existing-traveler-list {
    background: #1e293b;
    border-color: #334155;
}

[data-bs-theme="dark"] .existing-traveler-item:hover {
    background: #24324a;
}

[data-bs-theme="dark"] .divider-text::before,
[data-bs-theme="dark"] .divider-text::after {
    border-color: #334155;
}

[data-bs-theme="dark"] .pax-type-radio {
    color: #e2e8f0;
}

[data-bs-theme="dark"] .pax-ring-card {
    background: #1e293b;
    border-color: #334155;
}

[data-bs-theme="dark"] .ring-track {
    stroke: #334155;
}
</style>

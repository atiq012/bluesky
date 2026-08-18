<script setup>
import AppBreadcrumbs from '../../common/AppBreadcrumbs.vue';
import axiosInstance from "../../../axiosInstance";
import { ref, reactive, computed, onMounted, watch } from "vue";
import moment from "moment";
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '../../../stores/authStore';

import defaultAvatar from '../../../../../public/theme/appimages/Plane_origin.svg';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();

// Left panel: full request list (reused from the Support Request list page)
const requests = ref([]);
const selectedId = ref(route.params.ids || null);

// Middle panel state
const activeTab = ref('details');
const showNotesOnly = ref(true);

// Right panel + middle panel data for the currently selected request
const ticketData = reactive({
    idd: '',
    requestNumber: '',
    subject: '',
    description: '',
    status: '',
    categoryName: '',
    priority: '',
    requesterName: '-',
    createdAt: '',
    assignedTo: {
        name: '-',
        email: '-',
        avatar: '',
        department: '-',
        phone: '-'
    },
    notes: []
});

const historyList = ref([]);

const addNoteForm = reactive({
    note: '',
    ticketId: '',
    showToAssignee: false,
    sendAsEmail: false,
    sendAsNotification: false
});

const ticketCreatedAtLabel = computed(() => (ticketData.createdAt ? moment(ticketData.createdAt).format('DD-MMM-YYYY') : ''));

function statusMeta(status) {
    const s = String(status ?? '').toLowerCase();
    if (s === 'closed' || s === 'resolved') return 'rd-status-closed';
    if (s === 'open') return 'rd-status-open';
    if (s === 'in progress') return 'rd-status-inprogress';
    if (s === 'on hold') return 'rd-status-onhold';
    if (s === 'cancelled') return 'rd-status-cancelled';
    return 'rd-status-onhold';
}

function priorityMeta(priority) {
    const p = String(priority ?? '').toLowerCase();
    if (p === 'high') return 'rd-priority-high';
    if (p === 'medium') return 'rd-priority-medium';
    return 'rd-priority-low';
}

async function getListValues() {
    try {
        authStore.GlobalLoading = true;
        const response = await axiosInstance.get("getAllRequests");
        requests.value = response.data.data ?? [];
        authStore.GlobalLoading = false;

        if (!selectedId.value && requests.value.length) {
            selectedId.value = requests.value[0].idd;
        }
        if (selectedId.value) {
            loadDetails(selectedId.value);
        }
    } catch (error) {
        console.log(error);
        authStore.GlobalLoading = false;
    }
}

async function loadDetails(idd) {
    try {
        const response = await axiosInstance.get("getRequestDetails/" + idd);
        const data = response.data;

        ticketData.idd = idd;
        ticketData.requestNumber = data.data.request_number ?? '';
        ticketData.subject = data.data.subject ?? '-';
        ticketData.description = data.data.description ?? '';
        ticketData.status = data.data.status ?? '';
        ticketData.categoryName = data.data.category_name ?? '';
        ticketData.priority = data.data.priority ?? '';
        ticketData.requesterName = data.data.requester_name || '-';
        ticketData.createdAt = data.data.created_at ?? '';

        ticketData.assignedTo.name = data.assignee?.name || data.data.assigned_to_name || '-';
        ticketData.assignedTo.email = data.assignee?.email || '-';
        ticketData.assignedTo.avatar = data.assignee?.img_path || defaultAvatar;
        ticketData.assignedTo.department = data.assignee?.department_name || data.data.department || '-';
        ticketData.assignedTo.phone = data.assignee?.phone || data.data.phone || '-';

        // Notes / conversation thread
        ticketData.notes = (data.details || []).map((detail, index) => {
            const isOut = detail.from_user_id == data.data.requester_id;
            return {
                id: detail.idd ?? detail.id ?? index,
                authorName: isOut ? (data.me?.name || ticketData.requesterName) : (data.assignee?.name || ticketData.assignedTo.name),
                date: detail.created_at ? moment(detail.created_at).format('DD-MMM-YYYY') : '',
                note: detail.note
            };
        });

        historyList.value = data.history || [];

        addNoteForm.ticketId = idd;

    } catch (error) {
        console.log(error);
    }
}

function selectRequest(idd) {
    if (idd === selectedId.value) return;
    selectedId.value = idd;
    router.push({ name: 'requestDetails', params: { ids: idd } }).catch(() => { });
    loadDetails(idd);
}

function resetNoteForm() {
    addNoteForm.note = '';
    addNoteForm.showToAssignee = false;
    addNoteForm.sendAsEmail = false;
    addNoteForm.sendAsNotification = false;
}

// Bootstrap 5's vanilla-JS component classes (bootstrap.Modal) are loaded as
// a global script in this project, so referenced directly here.
function closeOverlay(elementId, kind) {
    const el = document.getElementById(elementId);
    if (!el || typeof bootstrap === 'undefined') return;
    const Component = kind === 'modal' ? bootstrap.Modal : bootstrap.Offcanvas;
    const instance = Component.getInstance(el);
    if (instance) {
        instance.hide();
    }
}

async function saveNote() {
    try {
        const payload = {
            note: addNoteForm.note,
            ticketId: addNoteForm.ticketId,
            show_to_assignee: addNoteForm.showToAssignee,
            send_as_email: addNoteForm.sendAsEmail,
            send_as_notification: addNoteForm.sendAsNotification
        };

        const response = await axiosInstance.post('/addRequestNote', payload);

        closeOverlay('addNoteModal', 'modal');
        resetNoteForm();
        loadDetails(selectedId.value);

        Notification.showToast('s', response.data.message);
    } catch (error) {
        console.log(error);
    }
}

// Keep the page in sync if the route id changes (e.g. browser back/forward)
watch(() => route.params.ids, (newId) => {
    if (newId && newId !== selectedId.value) {
        selectedId.value = newId;
        loadDetails(newId);
    }
});

onMounted(() => {
    getListValues();
});
</script>

<template>
    <AppBreadcrumbs title="Support Request" :back-to="{ name: 'helpDesk' }" :breadcrumbs="[
        { label: 'Dashboard', to: { name: 'Home' } },
        { label: 'Helpdesk', to: { name: 'helpDesk' } },
        { label: 'Support Request' },
    ]">
    </AppBreadcrumbs>

    <div class="card border-0 shadow-sm rd-shell overflow-hidden position-relative">
        <div v-if="authStore.GlobalLoading" class="rd-loading">
            <div class="loader-circle-57">
                <img class="position-absolute" src="../../../../../public/theme/appimages/blueskywings.png"
                    height="22" width="22" alt="">
            </div>
        </div>

        <div class="rd-shell-header d-flex align-items-center justify-content-between">
            <span>Open Request ({{ requests.length }})</span>
            <!-- <i class="fa-solid fa-chevron-down"></i> -->
        </div>

        <div class="row g-0">
            <!-- Left: request list -->
            <div class="col-12 col-lg-3 rd-list-col border-end">
                <div v-if="!requests.length" class="p-4 text-center text-muted small">No requests found</div>

                <button v-for="r in requests" :key="r.idd" type="button" class="rd-request-item w-100 text-start"
                    :class="{ 'is-active': r.idd === selectedId }" @click="selectRequest(r.idd)">
                    <div class="rd-request-title text-truncate">{{ r.request_number }} {{ r.subject }}</div>
                    <div class="rd-request-date">{{ r.created_at }}</div>
                    <div class="rd-request-requester">Requester : {{ r.requester_name || '-' }}</div>
                </button>
            </div>

            <!-- Middle: details / history -->
            <div class="col-12 col-lg-6 rd-details-col border-end" v-if="ticketData.idd">
                <div class="d-flex flex-wrap align-items-baseline gap-2 mb-2">
                    <span class="rd-ticket-id">#{{ ticketData.requestNumber }}</span>
                    <span class="rd-ticket-title">{{ ticketData.subject }}</span>
                </div>

                <span class="rd-by-pill d-inline-block mb-3">By : {{ ticketData.requesterName }} on {{
                    ticketCreatedAtLabel }}</span>

                <ul class="nav rd-tabs mb-3">
                    <li class="nav-item">
                        <button type="button" class="nav-link" :class="{ active: activeTab === 'details' }"
                            @click="activeTab = 'details'">Details</button>
                    </li>
                    <li class="nav-item">
                        <button type="button" class="nav-link" :class="{ active: activeTab === 'history' }"
                            @click="activeTab = 'history'">History</button>
                    </li>
                </ul>

                <!-- Details tab -->
                <div v-show="activeTab === 'details'">
                    <div class="rd-details-box mb-3" v-html="ticketData.description"></div>

                    <div class="rd-dropzone mb-3 text-center">
                        <i class="fa-solid fa-paperclip me-1"></i>
                        <a href="javascript:void(0)" class="rd-browse-link">Browse Files</a>
                        <span class="text-muted"> or Drag &amp; Drop Files Here (Max 4 mb)</span>
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-semibold">Conversation:</span>
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" id="notesToggle"
                                    v-model="showNotesOnly">
                                <label class="form-check-label" for="notesToggle">Notes</label>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm rd-btn-add-note text-white" data-bs-toggle="modal"
                            data-bs-target="#addNoteModal">
                            <i class="fa-solid fa-plus me-1"></i>Add Notes
                        </button>
                    </div>

                    <div v-if="showNotesOnly">
                        <div v-if="!ticketData.notes.length" class="text-muted small">No notes yet.</div>
                        <div v-for="note in ticketData.notes" :key="note.id" class="rd-note-card mb-3">
                            <div class="rd-note-header d-flex align-items-center gap-2">
                                <i class="fa-regular fa-note-sticky"></i>
                                <span class="text-muted">Notes By</span>
                                <span class="fw-semibold">{{ note.authorName }}</span>
                                <span class="rd-note-date">{{ note.date }}</span>
                            </div>
                            <div class="rd-note-body" v-html="note.note"></div>
                        </div>
                    </div>
                </div>

                <!-- History tab -->
                <div v-show="activeTab === 'history'">
                    <div v-if="!historyList.length" class="text-muted small">No history found.</div>
                    <div v-for="(h, idx) in historyList" :key="idx" class="rd-note-card mb-3">
                        <div class="rd-note-header d-flex align-items-center gap-2">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                            <span class="fw-semibold">{{ h.action || h.title }}</span>
                            <span class="rd-note-date">{{ h.created_at ? moment(h.created_at).format('DD-MMM-YYYY') :
                                '' }}</span>
                        </div>
                        <div class="rd-note-body">{{ h.description || h.note }}</div>
                    </div>
                </div>
            </div>

            <div v-else class="col-12 col-lg-6 rd-details-col border-end p-4 text-center text-muted">
                Select a request from the list to view its details.
            </div>

            <!-- Right: info panel -->
            <div class="col-12 col-lg-3 rd-info-col" v-if="ticketData.idd">
                <div class="rd-info-row">
                    <span class="rd-info-label">Category</span>
                    <span class="rd-info-value">: {{ ticketData.categoryName || '-' }}</span>
                </div>
                <div class="rd-info-row">
                    <span class="rd-info-label">Status</span>
                    <span class="rd-info-value" :class="statusMeta(ticketData.status)">: {{ ticketData.status || '-'
                    }}</span>
                </div>
                <div class="rd-info-row">
                    <span class="rd-info-label">Priority</span>
                    <span class="rd-info-value" :class="priorityMeta(ticketData.priority)">: {{ ticketData.priority
                        || '-' }}</span>
                </div>
                <div class="rd-info-row mb-0">
                    <span class="rd-info-label">Assign To</span>
                    <span class="rd-info-value rd-assign-link">: {{ ticketData.assignedTo.name }}</span>
                </div>

                <hr class="rd-divider">

                <div class="rd-assignee-card d-flex align-items-center gap-2">
                    <img :src="ticketData.assignedTo.avatar || defaultAvatar"
                        @error="$event.target.src = defaultAvatar" class="rd-assignee-avatar" alt="Avatar">
                    <div class="overflow-hidden">
                        <div class="rd-assignee-name text-truncate">{{ ticketData.assignedTo.name }}</div>
                        <div class="rd-assignee-email text-truncate">{{ ticketData.assignedTo.email }}</div>
                    </div>
                </div>

                <div class="rd-info-row mt-3">
                    <span class="rd-info-label">Department</span>
                    <span class="rd-info-value">: {{ ticketData.assignedTo.department }}</span>
                </div>
                <div class="rd-info-row mb-0">
                    <span class="rd-info-label">Phone</span>
                    <span class="rd-info-value">: {{ ticketData.assignedTo.phone }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Add note modal -->
    <div class="modal fade" id="addNoteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Notes</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <textarea v-model="addNoteForm.note" class="form-control mb-3" rows="5"
                        placeholder="Write something..."></textarea>

                    <div class="d-flex flex-column flex-sm-row flex-wrap column-gap-4 row-gap-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="rdShowToAssignee"
                                v-model="addNoteForm.showToAssignee">
                            <label class="form-check-label" for="rdShowToAssignee">Show this note to assignee</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="rdSendAsEmail"
                                v-model="addNoteForm.sendAsEmail">
                            <label class="form-check-label" for="rdSendAsEmail">Also send as Email</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="rdSendAsNotification"
                                v-model="addNoteForm.sendAsNotification">
                            <label class="form-check-label" for="rdSendAsNotification">Send as Notification</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm rd-btn-cancel px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm rd-btn-add-note px-4 text-white"
                        @click="saveNote">Save</button>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
/* ==========================================================
   Help Desk – Support Request – Details Page
   Layout/tokens sourced from the Figma spec (css.txt) + screenshot
   ========================================================== */

.rd-shell {
    background: #fff;
}

.rd-loading {
    position: absolute;
    inset: 0;
    display: grid;
    place-items: center;
    background: rgba(255, 255, 255, 0.6);
    z-index: 5;
}

.loader-circle-57 {
    width: 70px;
    height: 70px;
    display: flex;
    justify-content: center;
    align-items: center;
}

.loader-circle-57:before {
    content: "";
    color: red;
    height: 50px;
    width: 50px;
    background: #0000;
    border-radius: 50%;
    border: 5px solid #027de2d5;
    animation: rd-loader-spin 1s infinite;
}

@keyframes rd-loader-spin {
    50% {
        transform: rotatez(180deg);
        border-style: dashed;
        border-color: #9c54f0 #02b9af #4e86f4;
    }

    100% {
        transform: rotatez(360deg);
    }
}

/* Header bar */
.rd-shell-header {
    background: linear-gradient(90deg, #6C3CE0 0%, #7B4CF2 100%);
    color: #fff;
    font-weight: 600;
    font-size: 15px;
    padding: 14px 24px;
}

/* Left: request list */
.rd-list-col {
    background: #fff;
    max-height: 760px;
    overflow-y: auto;
}

.rd-request-item {
    display: block;
    background: #fff;
    border: 0;
    border-bottom: 1px solid #EEF1F5 !important;
    border-left: 3px solid transparent !important;
    padding: 14px 20px;
    transition: background .15s ease;
}

.rd-request-item:hover {
    background: #FAFAFF;
}

.rd-request-item.is-active {
    background: #F7F4FF;
    border-left-color: #7239EA !important;
}

.rd-request-item.is-active .rd-request-title {
    color: #7239EA;
}

.rd-request-title {
    font-size: 14px;
    font-weight: 600;
    color: #182432;
    margin-bottom: 4px;
}

.rd-request-date {
    font-size: 12px;
    color: #8A93A3;
    margin-bottom: 2px;
}

.rd-request-requester {
    font-size: 12px;
    color: #8A93A3;
}

/* Middle: details */
.rd-details-col {
    padding: 20px 24px;
    max-height: 760px;
    overflow-y: auto;
}

.rd-ticket-id {
    color: #7239EA;
    font-weight: 600;
    font-size: 15px;
}

.rd-ticket-title {
    color: #182432;
    font-weight: 600;
    font-size: 15px;
}

.rd-by-pill {
    background: #F5F8FA;
    color: #5E6878;
    font-size: 12px;
    padding: 5px 14px;
    border-radius: 20px;
}

.rd-tabs {
    border-bottom: 1px solid #E4EAEF;
}

.rd-tabs .nav-link {
    border: 0;
    background: transparent;
    color: #8A93A3;
    font-size: 14px;
    font-weight: 500;
    padding: 8px 4px;
    margin-right: 24px;
    border-bottom: 2px solid transparent;
    border-radius: 0;
}

.rd-tabs .nav-link.active {
    color: #7239EA;
    border-bottom-color: #7239EA;
    background: transparent;
}

.rd-details-box {
    background: #F5F8FA;
    border: 1px solid #E4EAEF;
    border-radius: 6px;
    padding: 14px 16px;
    font-size: 13px;
    line-height: 20px;
    color: #5E6878;
}

.rd-dropzone {
    border: 1px dashed #C7CEDA;
    border-radius: 6px;
    padding: 14px;
    font-size: 13px;
    color: #8A93A3;
}

.rd-browse-link {
    color: #7239EA;
    font-weight: 600;
    text-decoration: none;
}

.rd-browse-link:hover {
    text-decoration: underline;
}

.rd-btn-add-note {
    background: #7239EA;
    border-color: #7239EA;
}

.rd-btn-add-note:hover {
    background: #5f2fd0;
    border-color: #5f2fd0;
    color: #fff;
}

.rd-note-card {
    border: 1px solid #E4EAEF;
    border-radius: 6px;
    overflow: hidden;
}

.rd-note-header {
    background: #F5F8FA;
    padding: 10px 14px;
    font-size: 13px;
    color: #182432;
}

.rd-note-header i {
    color: #7239EA;
}

.rd-note-date {
    color: #A1ABB7;
    font-size: 12px;
    margin-left: auto;
}

.rd-note-body {
    padding: 12px 14px;
    font-size: 13px;
    color: #5E6878;
    line-height: 20px;
}

/* Right: info panel */
.rd-info-col {
    padding: 20px 24px;
    background: #fff;
}

.rd-info-row {
    display: flex;
    gap: 6px;
    font-size: 13px;
    margin-bottom: 14px;
}

.rd-info-label {
    min-width: 78px;
    color: #8A93A3;
}

.rd-info-value {
    color: #182432;
    font-weight: 500;
}

.rd-assign-link {
    color: #3B79F2 !important;
}

.rd-status-open,
.rd-status-closed {
    color: #05CC61 !important;
}

.rd-status-inprogress {
    color: #FB8E28 !important;
}

.rd-status-onhold {
    color: #F01B1B !important;
}

.rd-status-cancelled {
    color: #6c757d !important;
}

.rd-priority-high {
    color: #F01B1B !important;
}

.rd-priority-medium {
    color: #FB8E28 !important;
}

.rd-priority-low {
    color: #05CC61 !important;
}

.rd-divider {
    border-top: 1px solid #E4EAEF;
    margin: 16px 0;
    opacity: 1;
}

.rd-assignee-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #F5F8FA;
    flex-shrink: 0;
}

.rd-assignee-name {
    font-size: 14px;
    font-weight: 600;
    color: #182432;
}

.rd-assignee-email {
    font-size: 12px;
    color: #8A93A3;
}

.rd-btn-cancel {
    background: #E4EAEF;
    border-color: #E4EAEF;
    color: #182432;
}

.rd-btn-cancel:hover {
    background: #d7dee6;
    color: #182432;
}

/* Responsive: stack the three columns on smaller screens */
@media (max-width: 991.98px) {
    .rd-list-col {
        border-right: 0 !important;
        border-bottom: 1px solid #E4EAEF;
        max-height: 360px;
    }

    .rd-details-col {
        border-right: 0 !important;
        border-bottom: 1px solid #E4EAEF;
        max-height: none;
    }

    .rd-info-col {
        border-top: 0;
    }
}

@media (max-width: 575.98px) {
    .rd-shell-header {
        padding: 12px 16px;
        font-size: 14px;
    }

    .rd-details-col,
    .rd-info-col {
        padding: 16px;
    }

    .rd-info-row {
        flex-wrap: wrap;
    }
}
</style>
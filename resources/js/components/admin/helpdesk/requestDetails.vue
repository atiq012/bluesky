<script setup>
import AppBreadcrumbs from '../../common/AppBreadcrumbs.vue';
import axiosInstance from "../../../axiosInstance";
import { ref, reactive, computed, onMounted, onBeforeUnmount, watch } from "vue";
import moment from "moment";
import { purifyHtml } from "../../../Helpers/purifyHtml";
import Quill from 'quill';
import 'quill/dist/quill.core.css';
import 'quill/dist/quill.snow.css';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '../../../stores/authStore';

import defaultAvatar from '../../../../../public/theme/appimages/default_avatar.svg';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();

// Left panel: full request list (reused from the Support Request list page)
const requests = ref([]);
const selectedId = ref(history.state?.ids || null);
const page = ref(1);
const perPage = ref(15);
const hasMore = ref(true);
const isLoadingMore = ref(false);


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
    notes: [],
    messages: [],
    attachment: null
});

const historyList = ref([]);

// Attachment preview state
const previewFile = ref(null);   // { id, name, url, size }
const previewType = ref('');     // 'image' | 'pdf' | 'text' | 'excel' | 'word' | 'other'
const previewText = ref('');
const previewLoading = ref(false);

const addNoteForm = reactive({
    note: '',
    ticketId: '',
    showToAssignee: false,
    sendAsEmail: false,
    sendAsNotification: false
});

const editorRef = ref(null);
let quillInstance = null;

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

// The offcanvas uses the same badge tokens as the helpdesk list canvas.
function statusBadgeMeta(status) {
    const s = String(status ?? '').toLowerCase();
    if (s === 'closed' || s === 'resolved') return { cls: 'hd-badge-success', label: status };
    if (s === 'open') return { cls: 'hd-badge-success', label: status };
    if (s === 'in progress') return { cls: 'hd-badge-info', label: status };
    if (s === 'on hold') return { cls: 'hd-badge-danger', label: status };
    if (s === 'cancelled') return { cls: 'hd-badge-secondary', label: status };
    return { cls: 'hd-badge-warning', label: status };
}

function priorityBadgeMeta(priority) {
    const p = String(priority ?? '').toLowerCase();
    if (p === 'high') return 'hd-badge-danger';
    if (p === 'medium') return 'hd-badge-warning';
    return 'hd-badge-neutral';
}

// ---- Attachments: type detection, icons, preview, download ----

function getExt(filename) {
    const name = String(filename ?? '');
    const dot = name.lastIndexOf('.');
    return dot === -1 ? '' : name.slice(dot + 1).toLowerCase();
}

function fileTypeOf(file) {
    const ext = getExt(file?.name);
    if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'].includes(ext)) return 'image';
    if (ext === 'pdf') return 'pdf';
    if (['txt', 'csv', 'log', 'md', 'json'].includes(ext)) return 'text';
    if (['xls', 'xlsx'].includes(ext)) return 'excel';
    if (['doc', 'docx'].includes(ext)) return 'word';
    return 'other';
}

function fileIcon(type) {
    switch (type) {
        case 'image': return 'fa-solid fa-file-image';
        case 'pdf': return 'fa-solid fa-file-pdf';
        case 'text': return 'fa-solid fa-file-lines';
        case 'excel': return 'fa-solid fa-file-excel';
        case 'word': return 'fa-solid fa-file-word';
        default: return 'fa-solid fa-file';
    }
}

function fileIconColor(type) {
    switch (type) {
        case 'image': return '#3B79F2';
        case 'pdf': return '#F01B1B';
        case 'text': return '#8A93A3';
        case 'excel': return '#05CC61';
        case 'word': return '#3B79F2';
        default: return '#8A93A3';
    }
}

function humanFileSize(bytes) {
    if (!bytes) return '';
    const units = ['B', 'KB', 'MB', 'GB'];
    let val = Number(bytes);
    let i = 0;
    while (val >= 1024 && i < units.length - 1) {
        val /= 1024;
        i++;
    }
    return (i > 0 ? val.toFixed(1) : Math.round(val)) + ' ' + units[i];
}

function getFileNameFromPath(path) {
    const clean = String(path ?? '').split('?')[0];
    const parts = clean.split('/');
    return decodeURIComponent(parts[parts.length - 1] || 'attachment');
}


function normalizeAttachment(raw) {
    if (!raw) return null;

    if (Array.isArray(raw)) {
        return raw.length ? normalizeAttachment(raw[0]) : null;
    }

    if (typeof raw === 'string') {
        return {
            id: raw,
            name: getFileNameFromPath(raw),
            url: raw,
            size: 0
        };
    }

    if (typeof raw === 'object') {
        return {
            id: raw.idd ?? raw.id ?? raw.file_path ?? raw.url,
            name: raw.file_name || raw.name || getFileNameFromPath(raw.file_path || raw.url || raw.path || ''),
            url: raw.file_path || raw.file_url || raw.url || raw.path || '',
            size: raw.file_size || raw.size || 0
        };
    }

    return null;
}

// Triggers a normal browser download without leaving the page.
function downloadFile(file) {
    const link = document.createElement('a');
    link.href = file.url;
    link.download = file.name || '';
    link.target = '_blank';
    link.rel = 'noopener';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}


async function loadTextPreview(file) {
    previewLoading.value = true;
    previewText.value = '';
    try {
        const res = await fetch(file.url);
        previewText.value = await res.text();
    } catch (error) {
        console.log(error);
        previewText.value = 'Unable to load this file for preview. Please download it instead.';
    } finally {
        previewLoading.value = false;
    }
}


function openAttachment(file) {
    const type = fileTypeOf(file);

    if (type === 'excel' || type === 'word' || type === 'other') {
        downloadFile(file);
        return;
    }

    previewFile.value = file;
    previewType.value = type;

    if (type === 'text') {
        loadTextPreview(file);
    }

    const modalEl = document.getElementById('attachmentPreviewModal');
    if (modalEl && typeof bootstrap !== 'undefined') {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }
}

// async function getListValues() {
//     try {
//         authStore.GlobalLoading = true;
//         const response = await axiosInstance.get("getAllRequests");
//         requests.value = response.data.data ?? [];
//         console.log("list data: ",requests.value);
//         authStore.GlobalLoading = false;

//         if (!selectedId.value && requests.value.length) {
//             selectedId.value = requests.value[0].idd;
//         }
//         if (selectedId.value) {
//             loadDetails(selectedId.value);
//         }
//     } catch (error) {
//         console.log(error);
//         authStore.GlobalLoading = false;
//     }
// }

async function getListValues() {
    try {
        authStore.GlobalLoading = true;
        page.value = 1;
        hasMore.value = true;

        // Pass page & per_page params
        const response = await axiosInstance.get("getAllRequests", {
            params: { page: page.value, per_page: perPage.value }
        });

        const data = response.data.data ?? [];
        requests.value = data;

        // If received items are less than perPage, no more items exist
        if (data.length < perPage.value) {
            hasMore.value = false;
        }
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

        ticketData.messages = (data.details || []).map((detail, index) => {
            const isOut = detail.from_user_id == data.data.requester_id;
            return {
                id: detail.idd ?? detail.id ?? index,
                align: isOut ? 'out' : 'in',
                name: isOut ? (data.me?.name || ticketData.requesterName) : (data.assignee?.name || ticketData.assignedTo.name),
                avatar: isOut ? (data.me?.img_path || defaultAvatar) : (data.assignee?.img_path || defaultAvatar),
                time: detail.created_at ? moment(detail.created_at).format('DD-MMM-YYYY | hh:mm A') : '',
                note: detail.note
            };
        });

        historyList.value = data.history || [];
        ticketData.attachment = normalizeAttachment(data.data.file_path);
        //console.log(ticketData.attachment);

        addNoteForm.ticketId = idd;

    } catch (error) {
        console.log(error);
    }
}

async function loadMoreRequests() {
    if (!hasMore.value || isLoadingMore.value) return;
    try {
        isLoadingMore.value = true;
        page.value += 1;
        const response = await axiosInstance.get("getAllRequests", {
            params: { page: page.value, per_page: perPage.value }
        });
        const newItems = response.data.data ?? [];
        if (newItems.length < perPage.value) {
            hasMore.value = false; // Reached end of list
        }
        // Append new requests to existing list
        requests.value.push(...newItems);
    } catch (error) {
        //console.log(error);
        Notification.showToast('e', error.response?.data?.message || error.message);
    } finally {
        isLoadingMore.value = false;
    }
}

function handleListScroll(e) {
    const { scrollTop, clientHeight, scrollHeight } = e.target;

    // Check if scrolled within 50px of bottom
    if (scrollTop + clientHeight >= scrollHeight - 50) {
        loadMoreRequests();
    }
}

function selectRequest(idd) {
    if (idd === selectedId.value) return;
    selectedId.value = idd;
    // router.push({ name: 'requestDetails', params: { ids: idd } }).catch(() => { });
    loadDetails(idd);
}

function resetNoteForm() {
    if (quillInstance) {
        quillInstance.root.innerHTML = '<p></p>';
    }
    addNoteForm.note = '';
    addNoteForm.showToAssignee = false;
    addNoteForm.sendAsEmail = false;
    addNoteForm.sendAsNotification = false;
}

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

        // closeOverlay('addNoteModal', 'modal');
        closeOverlay('requestConversationCanvas', 'offcanvas');
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

    if (!editorRef.value) return;

    quillInstance = new Quill(editorRef.value, {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ size: ['small', false, 'large', 'huge'] }],
                ['bold', 'italic', 'underline'],
                ['blockquote', 'code-block'],
                [{ header: 1 }, { header: 2 }],
                [{ indent: '-1' }, { indent: '+1' }],
                [{ direction: 'rtl' }],
                ['clean'],
                ['link', 'image', 'video']
            ]
        },
        placeholder: 'Write something...'
    });

    quillInstance.on('text-change', () => {
        // addNoteForm.note = quillInstance.getText().trim();
        addNoteForm.note = quillInstance.root.innerHTML;
    });
});

onBeforeUnmount(() => {
    quillInstance = null;
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
                <img class="position-absolute" src="../../../../../public/theme/appimages/blueskywings.png" height="22"
                    width="22" alt="">
            </div>
        </div>

        <div class="rd-shell-header d-flex align-items-center justify-content-between">
            <span>Requests ({{ requests.length }})</span>
            <i class="fa-solid fa-chevron-down"></i>
        </div>

        <div class="row g-0">
            <!-- Left: request list -->
            <!-- <div class="col-12 col-lg-3 rd-list-col border-end">
                <div v-if="!requests.length" class="p-4 text-center text-muted small">No requests found</div>

                <button v-for="r in requests" :key="r.idd" type="button" class="rd-request-item w-100 text-start"
                    :class="{ 'is-active': String(r.idd) === String(selectedId) }" @click="selectRequest(r.idd)">
                    <div class="rd-request-title text-truncate">{{ r.request_number }} {{ r.subject }}</div>
                    <div class="rd-request-date">{{ r.created_at }}</div>
                    <div class="rd-request-requester">Requester : {{ r.requester_name || '-' }}</div>
                </button>
            </div> -->

            <div class="col-12 col-lg-3 rd-list-col border-end" @scroll="handleListScroll">
                <div v-if="!requests.length" class="p-4 text-center text-muted small">No requests found</div>
                <button v-for="r in requests" :key="r.idd" type="button" class="rd-request-item w-100 text-start"
                    :class="{ 'is-active': String(r.idd) === String(selectedId) }" @click="selectRequest(r.idd)">
                    <div class="rd-request-title text-truncate">{{ r.request_number }} {{ r.subject }}</div>
                    <div class="rd-request-date">{{ r.created_at }}</div>
                    <div class="rd-request-requester">Requester : {{ r.requester_name || '-' }}</div>
                </button>
                <!-- Loading spinner at bottom of list when fetching more -->
                <div v-if="isLoadingMore" class="p-3 text-center text-muted small">
                    <i class="fa-solid fa-spinner fa-spin me-1"></i> Loading...
                </div>
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
                    <div class="rd-details-box mb-3" v-html="purifyHtml(ticketData.description)"></div>

                    <!-- <div class="rd-dropzone mb-3 text-center">
                        <i class="fa-solid fa-paperclip me-1"></i>
                        <a href="javascript:void(0)" class="rd-browse-link">Browse Files</a>
                        <span class="text-muted"> or Drag &amp; Drop Files Here (Max 4 mb)</span>
                    </div> -->

                    <div v-if="ticketData.attachment" class="mb-3">
                        <label class="rd-section-label d-block mb-2">Attachment</label>
                        <div class="rd-attachment-grid">
                            <button type="button" class="rd-attachment-chip"
                                @click="openAttachment(ticketData.attachment)"
                                :title="['excel', 'word', 'other'].includes(fileTypeOf(ticketData.attachment)) ? 'Download ' + ticketData.attachment.name : 'Preview ' + ticketData.attachment.name">
                                <i :class="fileIcon(fileTypeOf(ticketData.attachment))"
                                    :style="{ color: fileIconColor(fileTypeOf(ticketData.attachment)) }"></i>
                                <span class="rd-attachment-name text-truncate">{{ ticketData.attachment.name }}</span>
                                <span v-if="ticketData.attachment.size" class="rd-attachment-size">{{
                                    humanFileSize(ticketData.attachment.size) }}</span>
                                <i v-if="['excel', 'word', 'other'].includes(fileTypeOf(ticketData.attachment))"
                                    class="fa-solid fa-download rd-attachment-download-badge"></i>
                            </button>
                        </div>
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
                        <button type="button" class="btn btn-sm rd-btn-add-note text-white" data-bs-toggle="offcanvas"
                            data-bs-target="#requestConversationCanvas">
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
                            <div class="rd-note-body" v-html="purifyHtml(note.note)"></div>
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
                    <img :src="ticketData.assignedTo.avatar || defaultAvatar" @error="$event.target.src = defaultAvatar"
                        class="rd-assignee-avatar" alt="Avatar">
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
    <!-- <div class="modal fade" id="addNoteModal" tabindex="-1" aria-hidden="true">
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
    </div> -->

    <!-- canvas -->
    <div class="offcanvas offcanvas-end hd-note-canvas" data-bs-scroll="true" tabindex="-1" id="requestConversationCanvas">
        <div class="offcanvas-header border-bottom py-3">
            <div class="d-flex align-items-baseline flex-wrap gap-2">
                <span class="hd-ticket-id">#{{ ticketData.requestNumber }}</span>
                <span class="fw-semibold hd-ticket-title">{{ ticketData.subject }}</span>
            </div>

            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>

        <div class="offcanvas-body d-flex flex-column p-0">
            <!-- Ticket meta: badges + details + attachment -->
            <div class="p-3 border-bottom">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <span class="hd-badge" :class="statusBadgeMeta(ticketData.status).cls">
                        {{ statusBadgeMeta(ticketData.status).label }}
                    </span>
                    <span v-if="ticketData.categoryName" class="hd-badge hd-badge-neutral">{{
                        ticketData.categoryName
                    }}</span>
                    <span v-if="ticketData.priority" class="hd-badge" :class="priorityBadgeMeta(ticketData.priority)">{{
                        ticketData.priority }}</span>
                    <span class="hd-badge hd-badge-by">By : {{ ticketData.requesterName }} on {{
                        ticketCreatedAtLabel
                    }}</span>
                </div>

                <!-- <label class="hd-section-label mb-2">Details</label>
                    <div class="ticket-details hd-details-box mb-2" v-html="purifyHtml(ticketData.description)"></div>

                    <div v-if="ticketData.attachmentsCount">
                        <button type="button" class="hd-attachment-chip">
                            <i class="fa-solid fa-paperclip"></i>{{ ticketData.attachmentsCount }} Attachment{{ ticketData.attachmentsCount > 1 ? 's' : '' }}
                        </button>
                    </div> -->
            </div>


            <!-- Conversation thread -->
            <div class="flex-grow-1 overflow-auto p-3 scrollable-messages note-messages">
                <div class="messages-list">
                    <div v-for="msg in ticketData.messages" :key="msg.id" class="hd-msg"
                        :class="msg.align === 'out' ? 'hd-msg-out' : 'hd-msg-in'">

                        <!--  Sender Info ABOVE the bubble (for Incoming / "in" messages) -->
                        <div v-if="msg.align === 'in'" class="hd-msg-meta">
                            <img :src="msg.avatar || defaultAvatar" @error="$event.target.src = defaultAvatar"
                                class="hd-avatar" alt="Avatar" />

                            <span class="hd-msg-name">{{ msg.name }}</span>
                            <span class="hd-msg-time">{{ msg.time }}</span>
                        </div>

                        <!-- Message Bubble -->
                        <div class="hd-msg-bubble" :class="msg.align === 'out' ? 'hd-bubble-out' : 'hd-bubble-in'"
                            v-html="purifyHtml(msg.note)"></div>

                        <!-- Sender Info BELOW the bubble (for Outgoing / "out" messages) -->
                        <div v-if="msg.align === 'out'" class="hd-msg-meta justify-content-end mt-1">
                            <!-- <img :src="msg.avatar" class="hd-avatar" alt="" /> -->
                            <img :src="msg.avatar || defaultAvatar" @error="$event.target.src = defaultAvatar"
                                class="hd-avatar" alt="Avatar" />
                            <span class="hd-msg-name">{{ msg.name }}</span>
                            <span class="hd-msg-time">{{ msg.time }}</span>
                        </div>

                    </div>
                </div>
            </div>


            <!-- Reply / note composer -->
            <form id="addNoteForm" class="border-top p-3">
                <div class="editor-container mb-3">
                    <div ref="editorRef" id="note"></div>
                </div>

                <div class="d-flex flex-column flex-sm-row flex-wrap column-gap-4 row-gap-2 mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="showToAssignee"
                            v-model="addNoteForm.showToAssignee">
                        <label class="form-check-label" for="showToAssignee">Show this note to assignee</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="sendAsEmail"
                            v-model="addNoteForm.sendAsEmail">
                        <label class="form-check-label" for="sendAsEmail">Also send as Email</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="sendAsNotification"
                            v-model="addNoteForm.sendAsNotification">
                        <label class="form-check-label" for="sendAsNotification">Send as Notification</label>
                    </div>
                </div>

                <div class="d-flex flex-column-reverse flex-sm-row justify-content-sm-end gap-2">
                    <button class="btn btn-sm hd-btn-cancel px-4" type="button" data-bs-dismiss="offcanvas"
                        aria-label="Close">Cancel</button>
                    <button type="button" @click="saveNote()" class="btn btn-sm hd-btn-save px-4 text-white">Save</button>
                </div>
            </form>
        </div>
    </div>
    <!-- end canvas -->

    <!-- Attachment preview modal: images, PDFs, and text render inline (larger view) -->
    <div class="modal fade" id="attachmentPreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"
            :class="previewType === 'image' || previewType === 'pdf' ? 'modal-xl' : 'modal-lg'">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title text-truncate mb-0">{{ previewFile?.name }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body rd-preview-body">
                    <div v-if="previewType === 'image'" class="text-center">
                        <img :src="previewFile?.url" class="img-fluid rd-preview-image" alt="Attachment preview">
                    </div>

                    <div v-else-if="previewType === 'pdf'" class="rd-preview-pdf">
                        <iframe :src="previewFile?.url" title="PDF preview"></iframe>
                    </div>

                    <div v-else-if="previewType === 'text'">
                        <div v-if="previewLoading" class="text-center text-muted py-5">
                            <i class="fa-solid fa-spinner fa-spin me-2"></i>Loading preview...
                        </div>
                        <pre v-else class="rd-preview-text">{{ previewText }}</pre>
                    </div>
                </div>
                <div class="modal-footer">
                    <a :href="previewFile?.url" :download="previewFile?.name"
                        class="btn btn-sm rd-btn-add-note text-white">
                        <i class="fa-solid fa-download me-1"></i>Download
                    </a>
                    <button type="button" class="btn btn-sm rd-btn-cancel px-4" data-bs-dismiss="modal">Close</button>
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

.rd-section-label {
    font-size: 13px;
    font-weight: 600;
    color: #3F4754;
}

.rd-attachment-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.rd-attachment-chip {
    position: relative;
    display: flex;
    align-items: center;
    gap: 8px;
    max-width: 220px;
    background: #fff;
    border: 1px solid #E4EAEF;
    border-radius: 8px;
    padding: 8px 12px;
    cursor: pointer;
    transition: border-color .15s ease, box-shadow .15s ease;
}

.rd-attachment-chip:hover {
    border-color: #7239EA;
    box-shadow: 0 2px 8px rgba(114, 57, 234, 0.12);
}

.rd-attachment-chip i {
    font-size: 18px;
    flex-shrink: 0;
}

.rd-attachment-name {
    font-size: 12px;
    font-weight: 500;
    color: #182432;
    max-width: 110px;
}

.rd-attachment-size {
    font-size: 11px;
    color: #A1ABB7;
    margin-left: auto;
    flex-shrink: 0;
    white-space: nowrap;
}

.rd-attachment-download-badge {
    position: absolute;
    top: -6px;
    right: -6px;
    background: #fff;
    border: 1px solid #E4EAEF;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 9px !important;
    color: #8A93A3 !important;
}

.rd-preview-body {
    max-height: 75vh;
    overflow: auto;
    background: #fafbfc;
}

.rd-preview-image {
    max-height: 70vh;
}

.rd-preview-pdf {
    height: 75vh;
}

.rd-preview-pdf iframe {
    width: 100%;
    height: 100%;
    border: 0;
}

.rd-preview-text {
    background: #F5F8FA;
    border: 1px solid #E4EAEF;
    border-radius: 6px;
    padding: 16px;
    font-size: 13px;
    line-height: 20px;
    color: #3F4754;
    white-space: pre-wrap;
    word-break: break-word;
    max-height: 70vh;
    overflow: auto;
    margin: 0;
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

/* Conversation offcanvas — kept here because this route can load directly. */
.offcanvas.offcanvas-end.hd-note-canvas {
    --bs-offcanvas-width: 50vw;
    width: 50vw;
    max-width: 100vw !important;
}

.hd-note-canvas .note-messages {
    min-height: 160px;
}

.hd-ticket-id {
    color: #7239EA;
    font-size: 13px;
    font-weight: 500;
}

.hd-ticket-title {
    color: #182432;
    font-size: 15px;
}

.hd-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 500;
    text-transform: capitalize;
    white-space: nowrap;
}

/* .hd-badge-neutral { background: #F6F6F6; color: #3F4754; }
.hd-badge-by { background: #F6F9FF; color: #3B79F2; } */

.hd-badge-success {
    background: rgba(69, 241, 42, 0.07);
    color: #05CC61;
}

.hd-badge-info {
    background: #EAF3FF;
    color: #3B79F2;
}

.hd-badge-danger {
    background: #FFF1F1;
    color: #F01B1B;
}

.hd-badge-warning {
    background: #FFF7E8;
    color: #FB8E28;
}

.hd-badge-secondary {
    background: #F6F6F6;
    color: #6c757d;
}

.hd-badge-neutral {
    background: #F6F6F6;
    color: #3F4754;
}

.hd-badge-by {
    background: #F6F9FF;
    color: #3B79F2;
}

.messages-list {
    display: flex;
    flex-direction: column;
}

.hd-msg {
    display: flex;
    flex-direction: column;
    max-width: 85%;
    margin-bottom: 18px;
}

.hd-msg-in { align-items: flex-start; margin-right: auto; }
.hd-msg-out { align-items: flex-end; margin-left: auto; }

.hd-msg-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 6px;
}

.hd-avatar {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
}

.hd-msg-name { font-size: 12px; font-weight: 500; color: #3F4754; }
.hd-msg-time { font-size: 10px; color: #A1ABB7; }

.hd-msg-bubble {
    padding: 8px 14px;
    font-size: 13px;
    line-height: 20px;
    letter-spacing: 0.2px;
    word-break: break-word;
}

.hd-bubble-in {
    background: #F6F2FF;
    color: #7239EA;
    border-radius: 4px 14px 14px 14px;
}

.hd-bubble-out {
    background: #F2F7FF;
    color: #3B79F2;
    border-radius: 14px 4px 14px 14px;
}

.hd-note-canvas .form-check-input {
    width: 18px;
    height: 18px;
    margin-top: 0;
    border-color: #E4EAEF;
    background-color: #E4EAEF;
}

.hd-note-canvas .form-check-input:checked {
    background-color: #7239EA;
    border-color: #7239EA;
}

.hd-note-canvas .form-check-label {
    font-size: 13px;
    color: #3F4754;
    margin-left: 4px;
}

.hd-btn-save { background-color: #3B79F2; border-color: #3B79F2; }
.hd-btn-save:hover { background-color: #2f66d0; border-color: #2f66d0; color: #fff; }
.hd-btn-cancel { background-color: #E4EAEF; border-color: #E4EAEF; color: #182432; }
.hd-btn-cancel:hover { background-color: #d7dee6; color: #182432; }

@media (max-width: 991.98px) {
    .offcanvas.offcanvas-end.hd-note-canvas {
        --bs-offcanvas-width: 90vw;
        width: 90vw;
        
    }
}

@media (max-width: 575.98px) {
    .hd-note-canvas .hd-btn-save,
    .hd-note-canvas .hd-btn-cancel { width: 100%; }
    .hd-msg { max-width: 95%; }
}
</style>

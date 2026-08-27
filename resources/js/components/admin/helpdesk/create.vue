<script setup>
import AppBreadcrumbs from '../../common/AppBreadcrumbs.vue';

import { useAuthStore } from "../../../stores/authStore";
import axiosInstance from "../../../axiosInstance"
import { ref, onMounted, onBeforeUnmount, reactive, watch,computed } from "vue";
import { mode } from "crypto-js";
import { useRouter } from "vue-router";
import Select2 from '../../common/Select2.vue';
import Quill from 'quill';
import "quill/dist/quill.core.css";
import "quill/dist/quill.snow.css";

const editorRef = ref(null);
let quillInstance = null;

const authStore = useAuthStore();
const router = useRouter();

const isPrimaryUser = computed(() => Boolean(authStore.sInfo?.is_primary));


const form = reactive({
    useEmail: authStore.email,
    cate_id: "",
    requester: "",
    priority: "",
    request_type: "",
    assets: "",
    mode: "",
    level: "",
    subcate_id: "",
    subject: "",
    description: "",
    assign_to: "",
    file_path: ""
});

const errors = reactive({
    requester: '',
    priority: '',
    request_type: '',
    mode: '',
    level: '',
    cate_id: '',
    subcate_id: '',
    subject: '',
    description: '',
});


const requesterOptions = ref([]);
const categoryOptions = ref([]);
const subcategoryOptions = ref([]);
const assignToOptions = ref([]);

const priorityOptions = ref([
    { value: 'low', label: 'Low' },
    { value: 'medium', label: 'Medium' },
    { value: 'high', label: 'High' },
]);

const requestTypeOptions = ref([
    { value: 'Request For Solution', label: 'Request For Solution' },
    { value: 'Request For Information', label: 'Request For Information' },
    { value: 'Incident', label: 'Incident' },
]);

const modeOptions = ref([
    { value: 'email', label: 'Email' },
    { value: 'phone', label: 'Phone' },
    { value: 'chat', label: 'Chat' },
    { value: 'web form', label: 'Web form' },
]);

const levelOptions = ref([
    { value: 'Tier 1', label: 'Tier 1' },
    { value: 'Tier 2', label: 'Tier 2' },
    { value: 'Tier 3', label: 'Tier 3' },
    { value: 'Tier 4', label: 'Tier 4' },
]);


const fileInputRef = ref(null);
const selectedFileName = ref("");
const isDragging = ref(false);
const MAX_FILE_SIZE_MB = 4;
const MAX_FILE_SIZE_BYTES = MAX_FILE_SIZE_MB * 1024 * 1024;


function goBack() {
    router.back();
}

function triggerFileBrowse() {
    fileInputRef.value?.click();
}

function onFileDrop(event) {
    isDragging.value = false;
    const dropped = event.dataTransfer?.files?.[0];
    if (dropped) {
        setSelectedFile(dropped);
    }
}
function validateFile(file) {
    if (!file) return false;
    if (file.size > MAX_FILE_SIZE_BYTES) {
        const fileSizeMB = (file.size / (1024 * 1024)).toFixed(2);

        Notification.showToast('e', `File size (${fileSizeMB} MB) exceeds the maximum allowed limit of ${MAX_FILE_SIZE_MB} MB.`
        );
        return false;
    }
    return true;
}

function setSelectedFile(file) {
    if (!file) return;

    if (!validateFile(file)) {
        
        form.file_path = null;
        selectedFileName.value = "";
        if (fileInputRef.value) {
            fileInputRef.value.value = ""; 
        }
        return;
    }
    form.file_path = file;
    selectedFileName.value = file.name;
}

function clearSelectedFile() {
    form.file_path = "";
    selectedFileName.value = "";
    if (fileInputRef.value) {
        fileInputRef.value.value = "";
    }
}

const handleFileChange = (event) => {
    const picked = event.target.files[0];
    if (!picked) return;

    setSelectedFile(picked);

    const reader = new FileReader();
    reader.readAsDataURL(form.file_path);
    // reader.onload = (e) => {
    //     previewImage.value = e.target.result;
    // };
}


async function getCate() {
    try {
        const response = await axiosInstance.get('categories');
        categoryOptions.value = (response.data || []).map(item => ({
            value: item.id,
            label: item.name
        }));
    } catch (error) {
        console.error(error);
    }
}


async function getSubCate(cate_id) {
    try {
        const response = await axiosInstance.get('subcategories', { params: { cate_id } });
        subcategoryOptions.value = (response.data || []).map(item => ({
            value: item.id,
            label: item.name
        }));
    } catch (error) {
        console.error(error);
    }
}


// async function getRequester() {
//     try {
//         const response = await axiosInstance.get('getAllUsers');
//         requesterOptions.value = (response.data || []).map(item => ({
//             value: item.id,
//             label: item.name
//         }));
//     } catch (error) {
//         console.error(error);
//     }
// }


// async function getRequester() {
//     console.log("is primary: ",isPrimaryUser.value);
//     if (isPrimaryUser.value) {
//         // Primary User: Fetch all agency users
//         try {
//             const response = await axiosInstance.get('getHelpdeskRequesters');
//             requesterOptions.value = (response.data || []).map(item => ({
//                 value: item.id,
//                 label: item.name
//             }));
//         } catch (error) {
//             console.error(error);
//         }
//     } else {
//         // Non-Primary User: Only self
//         const currentUserId = authStore.sInfo?.id || authStore.sInfo?.idd;
//         const currentUserName = authStore.name || authStore.sInfo?.name;

//         requesterOptions.value = [
//             { value: currentUserId, label: currentUserName }
//         ];
//         form.requester = currentUserId;
//     }
// }

async function getRequester() {
    try {
        const response = await axiosInstance.get('getHelpdeskRequesters');
        const users = response.data || [];

        requesterOptions.value = users.map(user => ({
            value: user.id,
            label: user.name
        }));

        // If only 1 user is returned (non-primary), select them automatically
        if (users.length === 1) {
            form.requester = users[0].id;
        }
    } catch (error) {
        console.error(error);
    }
}



async function getInternalUsers() {
    try {
        const response = await axiosInstance.get("getInternalUsers");
        assignToOptions.value = (response.data?.data || []).map(user => ({
            value: user.idd,
            label: user.name
        }));
    } catch (error) {
        console.error(error);
    }
}

function validateForm() {
    let isValid = true;

    // Reset previous error messages
    Object.keys(errors).forEach(key => errors[key] = '');

    if (!form.requester) {
        errors.requester = 'Requester is required.';
        isValid = false;
    }
    if (!form.priority) {
        errors.priority = 'Priority is required.';
        isValid = false;
    }
    if (!form.request_type) {
        errors.request_type = 'Request Type is required.';
        isValid = false;
    }
    if (!form.mode) {
        errors.mode = 'Mode is required.';
        isValid = false;
    }
    if (!form.level) {
        errors.level = 'Level is required.';
        isValid = false;
    }
    if (!form.cate_id) {
        errors.cate_id = 'Category is required.';
        isValid = false;
    }
    if (!form.subcate_id) {
        errors.subcate_id = 'Sub-Category is required.';
        isValid = false;
    }
    if (!form.subject || !form.subject.trim()) {
        errors.subject = 'Subject is required.';
        isValid = false;
    }

    // Check description (handle empty Quill HTML like '<p></p>')
    const cleanDesc = (form.description || '').replace(/<[^>]*>/g, '').trim();
    if (!cleanDesc) {
        errors.description = 'Description is required.';
        isValid = false;
    }

    return isValid;
}


function resetForm() {
    form.requester = "";
    form.cate_id = "";
    form.subcate_id = "";
    form.priority = "";
    form.request_type = "";
    form.mode = "";
    form.level = "";
    form.assets = "";
    form.subject = "";
    form.description = "";
    form.file_path = "";
    selectedFileName.value = "";

    if (quillInstance) {
        quillInstance.root.innerHTML = '<p></p>';
    }
}

async function save() {
    if (!validateForm()) {
        Notification.showToast('e', 'Please fill in all mandatory fields.');
        return;
    }
    try {
        const accessToken = authStore.decryptWithAES(authStore.token);
        const response = await axios.post('api/request/save', form, {
            headers: {
                'Content-Type': 'multipart/form-data',
                Authorization: "Bearer " + accessToken,
                Accept: "application/json",
            },
        });

        // Reset form state
        resetForm();
        Notification.showToast('s', response?.data?.message);
    } catch (error) {
        const backendMessage = error.response?.data?.message || 'Failed to submit request. Please try again.';
        Notification.showToast('e', backendMessage);
    }
}

onMounted(() => {
    getCate();
    getRequester();
    getInternalUsers();

    if (editorRef.value) {
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
            placeholder: 'Brief your problem here...',
            readOnly: false
        });
        // Sync Quill HTML content directly with form.description
        quillInstance.on('text-change', () => {
            form.description = quillInstance.root.innerHTML;
        });
    }
});

onBeforeUnmount(() => {
    if (quillInstance) {
        quillInstance = null;
    }
});

watch(() => form.cate_id, (newCateId) => {
    form.subcate_id = "";
    subcategoryOptions.value = [];
    if (newCateId) {
        getSubCate(newCateId);
    }
});

watch(form, (newForm) => {
    Object.keys(newForm).forEach((field) => {
        // If the field now has a value and has an active error, clear the error
        if (newForm[field] && errors[field]) {
            errors[field] = '';
        }
    });
}, { deep: true });


</script>
<template>
    <AppBreadcrumbs title="Setting" :back-to="{ name: 'helpDesk' }" :breadcrumbs="[
        { label: 'Dashboard', to: { name: 'Home' } },
        { label: 'Help Desk', to: { name: 'helpDesk' } },
        { label: 'Support Request', to: { name: 'helpDesk' } },
        { label: 'New Support Request' },
    ]" />

    <div class="card request-card">
        <div class="card-header request-card__header">
            <h5 class="m-0 p-0 request-card__title">&nbsp; New Request</h5>
        </div>

        <form id="addCateform">
            <div class="card-body request-card__body">
                <div class="row gy-3 gy-md-4">
                    <div class="col-12 col-md-6">
                        <label for="requester_id" class="form-label request-label">Requester
                            <span class="text-danger">*</span>
                        </label>
                        <Select2 v-model="form.requester" :options="requesterOptions" placeholder="Select Requester" />
                        <small v-if="errors.requester" class="text-danger">{{ errors.requester }}</small>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="priority" class="form-label request-label">Priority
                            <span class="text-danger">*</span>
                        </label>
                        <Select2 v-model="form.priority" :options="priorityOptions" placeholder="Select Priority" />
                        <small v-if="errors.priority" class="text-danger">{{ errors.priority }}</small>

                    </div>

                    <div class="col-12 col-md-6">
                        <label for="assets" class="form-label request-label">Assets</label>
                        <input type="text" v-model="form.assets" class="form-control form-control-sm request-input"
                            id="assets" name="assets" placeholder="Enter assets name">
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="request_type" class="form-label request-label">Request Type
                            <span class="text-danger">*</span>
                        </label>
                        <Select2 v-model="form.request_type" :options="requestTypeOptions"
                            placeholder="Select Request Type" />
                        <small v-if="errors.request_type" class="text-danger">{{ errors.request_type }}</small>

                    </div>

                    <div class="col-12 col-md-6">
                        <label for="mode" class="form-label request-label">Mode
                            <span class="text-danger">*</span>
                        </label>

                        <Select2 v-model="form.mode" :options="modeOptions" placeholder="Select Mode" />
                        <small v-if="errors.mode" class="text-danger">{{ errors.mode }}</small>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="level" class="form-label request-label">Level
                            <span class="text-danger">*</span>
                        </label>

                        <Select2 v-model="form.level" :options="levelOptions" placeholder="Select Level" />
                        <small v-if="errors.level" class="text-danger">{{ errors.level }}</small>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="cate_id" class="form-label request-label">Category
                            <span class="text-danger">*</span>
                        </label>

                        <Select2 v-model="form.cate_id" :options="categoryOptions" placeholder="Select Category" />
                        <small v-if="errors.cate_id" class="text-danger">{{ errors.cate_id }}</small>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="subcate_id" class="form-label request-label">Sub-Category
                            <span class="text-danger">*</span>
                        </label>

                        <Select2 v-model="form.subcate_id" :options="subcategoryOptions"
                            placeholder="Select Sub-Category" :disabled="!form.cate_id" />

                        <small v-if="errors.subcate_id" class="text-danger">{{ errors.subcate_id }}</small>

                    </div>

                    <div class="col-12">
                        <label for="subject" class="form-label request-label">Subject
                            <span class="text-danger">*</span>
                        </label>
                        <input type="text" v-model="form.subject" class="form-control form-control-sm request-input"
                            id="subject" name="subject" placeholder="Describe your problem shortly">
                        <small v-if="errors.subject" class="text-danger">{{ errors.subject }}</small>
                    </div>

                    <!-- <div class="col-12">
                        <label for="description" class="form-label request-label">Description</label>
                        <div class="request-editor">
                            <div class="request-editor__toolbar">
                                <button type="button" class="request-editor__btn" title="Clear formatting"><i
                                        class="bi bi-eraser"></i></button>
                                <button type="button" class="request-editor__btn" title="Bold"><i
                                        class="bi bi-type-bold"></i></button>
                                <button type="button" class="request-editor__btn" title="Italic"><i
                                        class="bi bi-type-italic"></i></button>
                                <button type="button" class="request-editor__btn" title="Underline"><i
                                        class="bi bi-type-underline"></i></button>
                                <span class="request-editor__divider"></span>
                                <button type="button" class="request-editor__btn" title="Align left"><i
                                        class="bi bi-text-left"></i></button>
                                <button type="button" class="request-editor__btn" title="Align center"><i
                                        class="bi bi-text-center"></i></button>
                                <button type="button" class="request-editor__btn" title="Align right"><i
                                        class="bi bi-text-right"></i></button>
                                <span class="request-editor__divider"></span>
                                <button type="button" class="request-editor__btn" title="Heading 1"><i
                                        class="bi bi-type-h1"></i></button>
                                <button type="button" class="request-editor__btn" title="Heading 2"><i
                                        class="bi bi-type-h2"></i></button>
                                <span class="request-editor__divider"></span>
                                <button type="button" class="request-editor__btn" title="Numbered list"><i
                                        class="bi bi-list-ol"></i></button>
                                <button type="button" class="request-editor__btn" title="Bulleted list"><i
                                        class="bi bi-list-ul"></i></button>
                                <span class="request-editor__divider"></span>
                                <button type="button" class="request-editor__btn" title="Attach"><i
                                        class="bi bi-paperclip"></i></button>
                                <button type="button" class="request-editor__btn" title="Code"><i
                                        class="bi bi-code-slash"></i></button>
                                <button type="button" class="request-editor__btn" title="Image"><i
                                        class="bi bi-image"></i></button>
                            </div>
                            <textarea v-model="form.description" class="request-editor__textarea" id="description"
                                name="description" rows="6" placeholder="Brief your problem here.."></textarea>
                        </div>
                    </div> -->

                    <div class="col-12">
                        <label class="form-label request-label">Description
                            <span class="text-danger">*</span>
                        </label>
                        <div class="quill-editor-wrapper">
                            <div ref="editorRef" style="min-height: 150px;"></div>
                        </div>
                        <small v-if="errors.description" class="text-danger">{{ errors.description }}</small>
                    </div>


                    <div class="col-12">
                        <div class="request-dropzone" :class="{ 'request-dropzone--active': isDragging }"
                            @dragover.prevent="isDragging = true" @dragleave.prevent="isDragging = false"
                            @drop.prevent="onFileDrop">
                            <i class="bi bi-paperclip request-dropzone__icon"></i>
                            <span class="request-dropzone__browse" role="button" @click="triggerFileBrowse">Browse
                                Files</span>
                            <span class="request-dropzone__or">or</span>
                            <span class="request-dropzone__hint">
                                {{ selectedFileName || 'Drag & Drop Files Here (Max 4 mb)' }}
                            </span>
                            <i v-if="selectedFileName" class="bi bi-x-circle-fill request-dropzone__clear"
                                title="Remove file" role="button" @click="clearSelectedFile"></i>
                            <input ref="fileInputRef" type="file" class="d-none" id="profile-picture"
                                @change="handleFileChange">
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer request-card__footer">
                <div class="d-flex flex-column flex-sm-row justify-content-sm-end gap-2">
                    <button type="button" @click="goBack()" class="btn request-btn request-btn--back order-2 order-sm-1">Back</button>
                    <button type="button" @click="save()"
                        class="btn request-btn request-btn--save order-1 order-sm-2">Save</button>
                </div>
            </div>
        </form>
    </div>
</template>

<style scoped>
.request-card {
    --border-color: #E4EAEF;
    --label-color: #3F4754;
    --placeholder-color: #A1ABB7;
    --muted-color: #7E8A99;
    --accent: #7239EA;
    --primary: #3B79F2;
    --neutral-7: #EFF2F5;

    border: none;
    border-radius: 8px;
    font-family: 'Be Vietnam Pro', sans-serif;
}

/* ---------- Header ---------- */
.request-card__header {
    background: #fff;
    border-bottom: 1px solid var(--neutral-7);
    padding: 16px 20px;
}

.request-card__title {
    border-left: 5px solid var(--accent);
    border-radius: 2px;
    font-size: 14px;
    font-weight: 600;
    letter-spacing: 0.5px;
    color: var(--label-color);
    line-height: 18px;
}

/* ---------- Body ---------- */
.request-card__body {
    padding: 20px;
}

.request-label {
    display: block;
    font-size: 14px;
    font-weight: 400;
    letter-spacing: 0.5px;
    color: var(--label-color);
    margin-bottom: 8px;
}

.request-input,
.request-select {
    width: 100%;
    min-height: 40px;
    border: 1px solid var(--border-color);
    border-radius: 5px;
    font-size: 13px;
    letter-spacing: 0.5px;
    color: var(--label-color);
    padding: 8px 14px;
    background-color: #fff;
}

.request-input::placeholder {
    color: var(--placeholder-color);
}

.request-select {
    color: var(--placeholder-color);
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6' fill='none'%3E%3Cpath d='M1 1L5 5L9 1' stroke='%237E8A99' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 14px center;
    background-size: 10px 6px;
}

.request-select:has(option:checked:not([value=""])) {
    color: var(--label-color);
}

.request-input:focus,
.request-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(59, 121, 242, 0.12);
    outline: none;
}

/* Make Select2 container match normal input style */
.request-card :deep(.app-select2-control) {
    width: 100%;
    min-height: 40px;
    border: 1px solid var(--border-color);
    border-radius: 5px;
    padding: 8px 14px;
    background-color: #fff;
    font-size: 13px;
    letter-spacing: 0.5px;
    color: var(--label-color);
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

/* Style inner Select2 text & placeholder */
.request-card :deep(.app-select2-input) {
    font-size: 13px;
    letter-spacing: 0.5px;
    color: var(--label-color);
}

.request-card :deep(.app-select2-input::placeholder) {
    color: var(--placeholder-color);
}

/* Match focus ring when dropdown is opened */
.request-card :deep(.app-select2-control--open) {
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px rgba(59, 121, 242, 0.12) !important;
}


/* ---------- Description / rich-text look ---------- */
.request-editor {
    border: 1px solid var(--border-color);
    border-radius: 5px;
    overflow: hidden;
}

.request-editor__toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 4px;
    padding: 8px 12px;
    border-bottom: 1px solid var(--neutral-7);
    background: #fff;
}

.request-editor__btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border: none;
    background: transparent;
    border-radius: 4px;
    color: var(--muted-color);
    font-size: 14px;
}

.request-editor__btn:hover {
    background: var(--neutral-7);
    color: var(--label-color);
}

.request-editor__divider {
    width: 1px;
    height: 18px;
    background: var(--border-color);
    margin: 0 4px;
}

.request-editor__textarea {
    width: 100%;
    border: none;
    resize: vertical;
    min-height: 140px;
    padding: 14px;
    font-family: 'Be Vietnam Pro', sans-serif;
    font-size: 13px;
    letter-spacing: 0.5px;
    color: var(--label-color);
}

.request-editor__textarea::placeholder {
    color: var(--muted-color);
}

.request-editor__textarea:focus {
    outline: none;
}

/* ---------- File drop zone ---------- */
.request-dropzone {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    align-items: center;
    gap: 9px;
    padding: 14px 16px;
    border: 1px dashed var(--border-color);
    border-radius: 5px;
    text-align: center;
    transition: background-color 0.15s ease, border-color 0.15s ease;
}

.request-dropzone--active {
    background-color: rgba(59, 121, 242, 0.05);
    border-color: var(--primary);
}

.request-dropzone__icon {
    font-size: 16px;
    background: linear-gradient(271.41deg, #965AFF 3.41%, #6A00F1 106.91%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}

.request-dropzone__browse {
    font-size: 13px;
    letter-spacing: 0.5px;
    font-weight: 400;
    background: linear-gradient(271.41deg, #965AFF 3.41%, #6A00F1 106.91%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    cursor: pointer;
}

.request-dropzone__or {
    font-size: 13px;
    letter-spacing: 0.5px;
    color: var(--muted-color);
}

.request-dropzone__hint {
    font-size: 12px;
    letter-spacing: 0.5px;
    color: var(--muted-color);
}

.request-dropzone__clear {
    font-size: 14px;
    color: var(--muted-color);
    cursor: pointer;
    line-height: 1;
}

.request-dropzone__clear:hover {
    color: #E5484D;
}

/* ---------- Footer / buttons ---------- */
.request-card__footer {
    background: #fff;
    border: none;
    padding: 16px 20px 20px;
}

.request-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 40px;
    padding: 0 24px;
    border-radius: 8px;
    font-size: 13px;
    letter-spacing: 0.5px;
    border: none;
}

.request-btn--back {
    background: var(--neutral-7);
    color: #182432;
}

.request-btn--back:hover {
    background: #e2e7ec;
    color: #182432;
}

.request-btn--save {
    background: var(--primary);
    color: #fff;
}

.request-btn--save:hover {
    background: #2f68d8;
    color: #fff;
}

/* quil editor */
.quill-editor-wrapper :deep(.ql-toolbar.ql-snow) {
    border: 1px solid var(--border-color);
    border-top-left-radius: 5px;
    border-top-right-radius: 5px;
    background-color: #fff;
}

.quill-editor-wrapper :deep(.ql-container.ql-snow) {
    border: 1px solid var(--border-color);
    border-top: none;
    border-bottom-left-radius: 5px;
    border-bottom-right-radius: 5px;
    min-height: 150px;
    background-color: #fff;
    font-family: inherit;
    font-size: 13px;
}


/* ---------- Responsive tweaks ---------- */
@media (max-width: 575.98px) {
    .request-card__body {
        padding: 16px;
    }

    .request-btn {
        width: 100%;
    }
}
</style>
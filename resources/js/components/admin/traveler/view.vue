<script setup>
import AppBreadcrumbs from '../../common/AppBreadcrumbs.vue';

import { computed, onMounted, ref, watch, reactive } from 'vue';
import moment from 'moment';
import axiosInstance from '../../../axiosInstance.js';
import ZoomImagePreview from '../../common/ZoomImagePreview.vue';
import LoadingSpinner from '../../common/LoadingSpinner.vue';

const props = defineProps({
    ids: { type: String, required: true },
});

const activeTab = ref('personal');
const loading = ref(true);
const previewImage = ref('');

const traveler = reactive({
    full_name: '',
    dob: '',
    gender: '',
    pax_type: '',
    passport_no: '',
    expiry_date: '',
    nationality: '',
    email: '',
    phone: ''
});

const planePlaceholder = new URL('../../../../../public/theme/appimages/Plane_origin.svg', import.meta.url).href;

const tabs = [
    { key: 'personal', label: 'Personal Details', icon: 'fa-solid fa-user' },
    { key: 'passport', label: 'Passport Information', icon: 'fa-solid fa-passport' },
    { key: 'usage', label: 'Travel Summary', icon: 'fa-solid fa-ticket' },
];

function displayValue(value, fallback = '—') {
    if (value == null || value === '') return fallback;
    return value;
}

const statusClass = computed(() => {
    const map = {
        'Adult': 'traveler-view-status--adult',
        'Child': 'traveler-view-status--child',
        'Infant': 'traveler-view-status--infant',
    };
    return map[traveler.pax_type] || 'traveler-view-status--default';
});

const personalFields = computed(() => {
    return [
        { icon: 'fa-solid fa-user', color: '#0880e1', label: 'Full Name', value: displayValue(traveler.full_name) },
        { icon: 'fa-regular fa-calendar', color: '#9c54f0', label: 'Date of Birth', value: displayValue(traveler.dob) },
        { icon: 'fa-solid fa-venus-mars', color: '#e7515a', label: 'Gender', value: displayValue(traveler.gender) },
        { icon: 'fa-solid fa-users', color: '#00ab55', label: 'Passenger Type', value: displayValue(traveler.pax_type) },
        { icon: 'fa-solid fa-envelope', color: '#02b9af', label: 'Email', value: displayValue(traveler.email) },
        { icon: 'fa-solid fa-phone', color: '#4e86f4', label: 'Phone', value: displayValue(traveler.phone) },
        { icon: 'fa-solid fa-flag', color: '#ca8a04', label: 'Nationality', value: displayValue(traveler.nationality) },
    ];
});

const passportFields = computed(() => {
    return [
        { icon: 'fa-solid fa-id-card', color: '#027de2', label: 'Passport Number', value: displayValue(traveler.passport_no) },
        { icon: 'fa-regular fa-calendar-check', color: '#059669', label: 'Passport Expiry Date', value: displayValue(traveler.expiry_date) },
        { icon: 'fa-solid fa-flag', color: '#ca8a04', label: 'Nationality', value: displayValue(traveler.nationality) },
    ];
});

const usageFields = computed(() => {
    return [
        { icon: 'fa-solid fa-wallet', color: '#00ab55', label: 'Total Usage', value: '৳ 445,670' },
        { icon: 'fa-solid fa-ticket', color: '#0880e1', label: 'Total Ticketed', value: '40' },
    ];
});

async function getTravelerData() {
    loading.value = true;
    try {
        const response = await axiosInstance.post('viewTraveler', { 'id': props.ids });
        traveler.full_name    = response.data.full_name;
        traveler.dob          = response.data.dob ? moment(response.data.dob).format('DD-MMM-YYYY') : '';
        traveler.gender       = response.data.gender;
        traveler.passport_no  = response.data.passport_number;
        traveler.expiry_date  = response.data.passport_expiry_date ? moment(response.data.passport_expiry_date).format('DD-MMM-YYYY') : '';
        traveler.nationality  = response.data.nationality;
        traveler.email        = response.data.email;
        traveler.phone        = response.data.phone;
        
        if (response.data.pax_type == 1)      traveler.pax_type = 'Adult';
        else if (response.data.pax_type == 2) traveler.pax_type = 'Child';
        else if (response.data.pax_type == 3) traveler.pax_type = 'Infant';
        
        previewImage.value = response.data.passport_path;
    } catch (error) {
        console.log(error);
    } finally {
        loading.value = false;
    }
}

onMounted(() => getTravelerData());

watch(
    () => props.ids,
    () => getTravelerData(),
);
</script>

<template>
    <div class="traveler-view-page">
        <AppBreadcrumbs
            title="Traveller Management"
            :back-to="{ name: 'TravelerList' }"
            :breadcrumbs="[
                { label: 'Dashboard', to: { name: 'Home' } },
                { label: 'Traveller Management', to: { name: 'TravelerList' } },
                { label: 'View Traveller' },
            ]"
        />

        <div v-if="loading" class="traveler-view-loading">
            <LoadingSpinner />
        </div>

        <div v-else class="row g-3 align-items-stretch traveler-view-layout">
            <div class="col-lg-4">
                <div class="traveler-view-left">
                    <div class="card border-0 traveler-view-profile-card">
                        <div class="traveler-view-profile-cover" aria-hidden="true"></div>
                        <div class="traveler-view-profile-body mb-3">
                            <div class="traveler-view-profile-avatar-wrap">
                                <div class="traveler-view-profile-avatar-inner">
                                    <img
                                        :src="planePlaceholder"
                                        alt="Traveler avatar"
                                        class="traveler-view-avatar__img"
                                    />
                                </div>
                            </div>
                            <h6 class="traveler-view-profile-name">{{ displayValue(traveler.full_name) }}</h6>
                            <div class="traveler-view-profile-meta">
                                <span class="traveler-view-profile-meta__item">
                                    <i class="fa-solid fa-envelope traveler-view-profile-meta__icon traveler-view-profile-meta__icon--email" />
                                    {{ displayValue(traveler.email) }}
                                </span>
                                <span class="traveler-view-profile-meta__item">
                                    <i class="fa-solid fa-phone traveler-view-profile-meta__icon traveler-view-profile-meta__icon--phone" />
                                    {{ displayValue(traveler.phone) }}
                                </span>
                            </div>
                            <span class="traveler-view-status" :class="statusClass">
                                <i class="fa-solid fa-circle traveler-view-status__dot" />{{ displayValue(traveler.pax_type) }}
                            </span>
                        </div>
                    </div>

                    <div class="card border-0 traveler-view-documents-card">
                        <div class="card-header traveler-view-card-header border-bottom py-2 px-3">
                            <h6 class="mb-0 fw-semibold">
                                <i class="fa-solid fa-folder-open text-primary me-2" />Passport Image
                            </h6>
                        </div>
                        <div class="card-body traveler-view-documents-body">
                            <div v-if="previewImage" class="row g-2">
                                <div class="col-12">
                                    <div class="traveler-view-doc">
                                        <div class="traveler-view-doc__media">
                                            <ZoomImagePreview
                                                :key="previewImage"
                                                :src="previewImage"
                                                alt="Passport Copy"
                                            />
                                        </div>
                                        <span class="traveler-view-doc__label">Passport Copy</span>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="text-muted small mb-0 text-center py-3">No passport image uploaded</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card border-0 h-100 traveler-view-details-card">
                    <div class="card-header traveler-view-card-header border-bottom traveler-view-tabs-wrap p-0">
                        <ul class="nav nav-tabs traveler-view-tabs border-0 px-2 pt-2">
                            <li v-for="tab in tabs" :key="tab.key" class="nav-item">
                                <button
                                    type="button"
                                    class="nav-link"
                                    :class="{ active: activeTab === tab.key }"
                                    @click="activeTab = tab.key"
                                >
                                    <i :class="tab.icon" class="me-1" />{{ tab.label }}
                                </button>
                            </li>
                        </ul>
                    </div>

                    <div class="card-body">
                        <div v-if="activeTab === 'personal'" class="row g-2">
                            <div v-for="field in personalFields" :key="field.label" class="col-md-6">
                                <div class="traveler-view-field">
                                    <i :class="field.icon" class="traveler-view-field__icon" :style="{ color: field.color }" />
                                    <div class="min-w-0">
                                        <div class="traveler-view-field__label">{{ field.label }}</div>
                                        <div class="traveler-view-field__value">{{ field.value }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else-if="activeTab === 'passport'" class="row g-2">
                            <div v-for="field in passportFields" :key="field.label" class="col-md-6">
                                <div class="traveler-view-field">
                                    <i :class="field.icon" class="traveler-view-field__icon" :style="{ color: field.color }" />
                                    <div class="min-w-0">
                                        <div class="traveler-view-field__label">{{ field.label }}</div>
                                        <div class="traveler-view-field__value">{{ field.value }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else-if="activeTab === 'usage'" class="row g-2">
                            <div v-for="field in usageFields" :key="field.label" class="col-md-6">
                                <div class="traveler-view-field">
                                    <i :class="field.icon" class="traveler-view-field__icon" :style="{ color: field.color }" />
                                    <div class="min-w-0">
                                        <div class="traveler-view-field__label">{{ field.label }}</div>
                                        <div class="traveler-view-field__value">{{ field.value }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.traveler-view-page {
    --traveler-view-primary: #027de2;
}

.traveler-view-loading {
    min-height: 320px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.traveler-view-layout {
    min-height: calc(100vh - 11rem);
}

.traveler-view-left {
    display: flex;
    flex-direction: column;
    gap: 10px;
    justify-content: flex-start;
}

.traveler-view-profile-card {
    flex-shrink: 0;
    height: auto;
    overflow: hidden;
    margin-bottom: 0;
    border-radius: 0.85rem;
    border: 1px solid rgba(2, 125, 226, 0.08);
}

.traveler-view-profile-cover {
    height: 3.35rem;
    background: linear-gradient(125deg, #027de2 0%, #02b9af 42%, #4e86f4 78%, #9c54f0 100%);
    position: relative;
}

.traveler-view-profile-cover::after {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 85% 15%, rgba(255, 255, 255, 0.28), transparent 42%);
    pointer-events: none;
}

.traveler-view-profile-body {
    height: auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    text-align: center;
    padding: 0 0.85rem 0.4rem;
    margin-top: -1.9rem;
    position: relative;
    z-index: 1;
}

.traveler-view-profile-avatar-wrap {
    width: 5rem;
    height: 5rem;
    border-radius: 50%;
    padding: 2px;
    background: linear-gradient(140deg, #027de2, #02b9af 50%, #9c54f0);
    margin-bottom: 0.35rem;
    box-shadow: 0 0.4rem 1rem rgba(2, 125, 226, 0.28);
    flex-shrink: 0;
}

.traveler-view-profile-avatar-inner {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: #fff;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}

.traveler-view-profile-logo {
    border-radius: 50%;
    object-fit: cover;
}

.traveler-view-profile-name {
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.95rem;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 0.25rem;
    letter-spacing: -0.01em;
}

.traveler-view-profile-meta {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    width: 100%;
    margin-bottom: 0.3rem;
}

.traveler-view-profile-meta__item {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    max-width: 100%;
    font-size: 0.72rem;
    font-weight: 500;
    color: #64748b;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.traveler-view-profile-meta__icon {
    width: 0.85rem;
    text-align: center;
    flex-shrink: 0;
    font-size: 0.68rem;
}

.traveler-view-profile-meta__icon--email { color: #02b9af; }
.traveler-view-profile-meta__icon--phone { color: #00ab55; }

.traveler-view-documents-card {
    flex: 0 0 auto;
    margin-top: 0;
    display: flex;
    flex-direction: column;
}

.traveler-view-documents-body {
    overflow-y: auto;
}

.traveler-view-doc {
    border: 1px solid #e2e8f0;
    border-radius: 0.65rem;
    overflow: hidden;
    background: #fff;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.traveler-view-doc:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.35rem 0.85rem rgba(15, 23, 42, 0.08);
}

.traveler-view-doc__media {
    width: 100%;
    aspect-ratio: 3 / 2;
    overflow: hidden;
    background: #f1f5f9;
}

.traveler-view-doc__media :deep(.preview-thumb) {
    width: 100% !important;
    height: 100% !important;
    max-width: none;
    display: block;
    object-fit: cover;
}

.traveler-view-doc__label {
    display: block;
    font-size: 0.72rem;
    font-weight: 600;
    padding: 0.35rem 0.5rem;
    text-align: center;
    color: #475569;
    background: #f8fafc;
}

.traveler-view-details-card {
    min-height: 100%;
}

.traveler-view-avatar__img {
    width: 2.15rem;
    height: 2.15rem;
    object-fit: contain;
}

.traveler-view-status {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.32rem 0.75rem;
    border-radius: 999px;
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    box-shadow: 0 0.15rem 0.45rem rgba(15, 23, 42, 0.06);
}

.traveler-view-status__dot {
    font-size: 0.45rem;
}

.traveler-view-status--adult { background: #dcfce7; color: #15803d; }
.traveler-view-status--child { background: #e8f4fd; color: #027de2; }
.traveler-view-status--infant { background: #fef9c3; color: #ca8a04; }
.traveler-view-status--default { background: #f1f5f9; color: #64748b; }

.traveler-view-tabs .nav-link {
    border: 0;
    border-bottom: 2px solid transparent;
    color: #64748b;
    font-size: 0.875rem;
    font-weight: 600;
    padding: 0.65rem 0.9rem;
    white-space: nowrap;
}

.traveler-view-tabs .nav-link.active {
    color: var(--traveler-view-primary);
    background: transparent;
    border-bottom-color: var(--traveler-view-primary);
}

.traveler-view-field {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.5rem 0.55rem;
    border: 1px solid #edf2f7;
    border-radius: 0.5rem;
    background: #fcfdff;
    height: 100%;
    white-space: nowrap;
    min-height: 0;
}

.traveler-view-field__icon {
    width: 1rem;
    text-align: center;
    flex-shrink: 0;
    font-size: 0.8rem;
}

.traveler-view-field__label {
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #94a3b8;
    margin-bottom: 0.05rem;
    line-height: 1.1;
}

.traveler-view-field__value {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #334155;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.2;
}
</style>

<style>
[data-bs-theme="dark"] .traveler-view-page .card {
    background-color: #212529;
    border-color: #495057 !important;
    color: #dee2e6;
}

[data-bs-theme="dark"] .traveler-view-page .card-body {
    background-color: #212529;
    color: #dee2e6;
}

[data-bs-theme="dark"] .traveler-view-page .traveler-view-card-header {
    background-color: #343a40 !important;
    border-color: #495057 !important;
    color: #f8f9fa;
}

[data-bs-theme="dark"] .traveler-view-page .traveler-view-field {
    background: #2b3035;
    border-color: #495057;
}

[data-bs-theme="dark"] .traveler-view-page .traveler-view-field__label {
    color: #adb5bd;
}

[data-bs-theme="dark"] .traveler-view-page .traveler-view-field__value {
    color: #e9ecef;
}

[data-bs-theme="dark"] .traveler-view-page .traveler-view-doc {
    background: #2b3035;
    border-color: #495057;
}

[data-bs-theme="dark"] .traveler-view-page .traveler-view-doc__media {
    background: #343a40;
}

[data-bs-theme="dark"] .traveler-view-page .traveler-view-doc:hover {
    box-shadow: 0 0.35rem 0.85rem rgba(0, 0, 0, 0.35);
}

[data-bs-theme="dark"] .traveler-view-page .traveler-view-doc__label {
    background: #343a40;
    color: #ced4da;
}

[data-bs-theme="dark"] .traveler-view-page .traveler-view-tabs .nav-link {
    color: #adb5bd;
}

[data-bs-theme="dark"] .traveler-view-page .traveler-view-tabs .nav-link:hover {
    color: #dee2e6;
    background: rgba(255, 255, 255, 0.04);
}

[data-bs-theme="dark"] .traveler-view-page .traveler-view-tabs .nav-link.active {
    color: #6ea8fe;
    background: transparent;
    border-bottom-color: #6ea8fe;
}

[data-bs-theme="dark"] .traveler-view-page .traveler-view-profile-card {
    border-color: #495057;
}

[data-bs-theme="dark"] .traveler-view-page .traveler-view-profile-cover {
    background: linear-gradient(125deg, #0b4f8a 0%, #0d6e6a 42%, #2f4f9e 78%, #5b3f96 100%);
}

[data-bs-theme="dark"] .traveler-view-page .traveler-view-profile-avatar-inner {
    background: #212529;
}

[data-bs-theme="dark"] .traveler-view-page .traveler-view-profile-name {
    color: #f8f9fa;
}

[data-bs-theme="dark"] .traveler-view-page .traveler-view-profile-meta__item {
    color: #adb5bd;
}
</style>

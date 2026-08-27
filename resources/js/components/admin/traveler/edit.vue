<script setup>
import { computed, reactive, ref, watch, onMounted } from 'vue';
import axios from 'axios';
import AppBreadcrumbs from '../../common/AppBreadcrumbs.vue';
import AppDatePicker from '../../common/AppDatePicker.vue';
import Select2 from '../../common/Select2.vue';
import ImageUploader from '../../common/ImageUploader.vue';
import AppButton from '../../common/AppButton.vue';
import DobWithAge from '../../common/DobWithAge.vue';
import { useAuthStore } from '../../../stores/authStore.js';
import { useRouter } from 'vue-router';
import moment from 'moment';
import axiosInstance from '../../../axiosInstance.js';

const props = defineProps(['ids']);

const form = reactive({
    pax_id: '',
    title: '', firstName: '', lastName: '', dob: '', gender: '',
    nationality: '', frequentFlyer: '', passportNo: '', passportExpiry: '',
    email: '', phone: '', passportFiles: [], visaFiles: [], meal: '', wheelchair: '',
});

const titleOptions = ['Mr.', 'Ms.', 'Mrs.', 'Mstr.'].map(value => ({ value, label: value }));
const genderOptions = ['Male', 'Female', 'Other'].map(value => ({ value, label: value }));
const nationalityOptions = ['Bangladeshi', 'American', 'Indian', 'Pakistani'].map(value => ({ value, label: value }));
const mealOptions = ['Vegetarian', 'Non-vegetarian'].map(value => ({ value, label: value }));
const wheelchairOptions = [{ value: 'yes', label: 'Yes' }, { value: 'no', label: 'No' }];

const authStore = useAuthStore();
const router = useRouter();
const isSubmitting = ref(false);
const isLoading = ref(true);
const submitError = ref('');
const isSuccessModalOpen = ref(false);

const errors = reactive({
    title: null, firstName: null, lastName: null, dob: null, gender: null, nationality: null,
    passportNo: null, passportExpiry: null, passportFiles: null, email: null, phone: null,
});

function parseDisplayDate(value) {
    if (!value || typeof value !== 'string') return null;
    const match = value.match(/^(\d{2})-([A-Za-z]{3})-(\d{4})$/);
    if (!match) return null;
    const month = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'].indexOf(match[2]);
    const date = new Date(Number(match[3]), month, Number(match[1]));
    return month >= 0 && date.getFullYear() === Number(match[3]) && date.getMonth() === month && date.getDate() === Number(match[1]) ? date : null;
}

const derivedPax = computed(() => {
    const dob = parseDisplayDate(form.dob);
    if (!dob) return null;

    const today = new Date();
    let months = (today.getFullYear() - dob.getFullYear()) * 12 + today.getMonth() - dob.getMonth();
    if (today.getDate() < dob.getDate()) months -= 1;
    if (months < 0) return null;
    if (months < 24) return { value: 3, label: 'Infant' };
    if (months < 144) return { value: 2, label: 'Child' };

    return { value: 1, label: 'Adult' };
});

const isChildOrInfant = computed(() => {
    return derivedPax.value?.label === 'Child' || derivedPax.value?.label === 'Infant';
});

// Re-check title validity when Title or DOB changes
watch([() => form.title, () => form.dob], () => {
    if (!form.title) return;
    const isChildOrInfantVal = derivedPax.value && (derivedPax.value.label === 'Child' || derivedPax.value.label === 'Infant');
    const allowedChildTitles = ['Mstr.', 'Ms.', 'Miss.'];

    if (isChildOrInfantVal && !allowedChildTitles.includes(form.title)) {
        errors.title = `Please select 'Mstr.' or 'Ms.' for ${derivedPax.value.label}.`;
    } else if (derivedPax.value?.label === 'Adult' && form.title === 'Mstr.') {
        errors.title = "Title 'Mstr.' is only allowed for Children and Infants.";
    } else {
        errors.title = null;
    }
});

watch(() => form.firstName, value => { if (value?.trim()) errors.firstName = false; });
watch(() => form.lastName, value => { if (value?.trim()) errors.lastName = false; });
watch(() => form.dob, value => { if (value && derivedPax.value) errors.dob = false; });
watch(() => form.gender, value => { if (value) errors.gender = false; });
watch(() => form.nationality, value => { if (value) errors.nationality = false; });
watch(() => form.passportNo, value => { if (value?.trim()) errors.passportNo = false; });
watch(() => form.passportExpiry, value => { if (value) errors.passportExpiry = false; });
watch(() => form.passportFiles, files => { if (files.length) errors.passportFiles = false; }, { deep: true });
watch(() => form.email, value => { if (value?.trim()) errors.email = false; });
watch(() => form.phone, value => { if (value?.trim()) errors.phone = false; });

onMounted(async () => {
    await fetchTravelerData();
});

async function fetchTravelerData() {
    isLoading.value = true;
    try {
        const travelerId = props.ids;
        const response = await axiosInstance.get('fetchTraveler', { params: { id: travelerId } });
        const d = response.data;
        //console.log('Fetched traveler data:', d);
        if (d) {
            form.pax_id = d.id;
            form.title = d.title || '';
            form.firstName = d.first_name || '';
            form.lastName = d.last_name || '';
            form.dob = d.dob ? moment(d.dob).format('DD-MMM-YYYY') : '';
            form.gender = d.gender || '';
            form.nationality = d.nationality || '';
            form.passportNo = d.passport_number || '';

            const expDate = d.passport_expiry_date || d.expiry_date;
            form.passportExpiry = expDate ? moment(expDate).format('YYYY-MM-DD') : '';
            form.email = d.email || '';
            form.phone = d.phone || '';

            if (d.passport_path) {
                form.passportFiles = [{
                    preview: d.passport_path,
                    originalName: 'Passport Image',
                    file: { size: d.passport_file_size }
                }];
            } else {
                form.passportFiles = [];
            }
        }
    } catch (err) {
        const backendMessage = err.response?.data?.message || 'Failed to load traveller information.';
        if (window.Notification?.showToast) {
            window.Notification.showToast('e', backendMessage);
        }
    } finally {
        isLoading.value = false;
    }
}

function validate() {
    Object.keys(errors).forEach(key => { errors[key] = null; });
    submitError.value = '';

    if (!form.title) {
        errors.title = 'Please select a title.';
    } else if (derivedPax.value && (derivedPax.value.label === 'Child' || derivedPax.value.label === 'Infant')) {
        const allowedChildTitles = ['Mstr.', 'Ms.', 'Miss.'];
        if (!allowedChildTitles.includes(form.title)) {
            errors.title = `Please select 'Mstr.' or 'Ms.' for ${derivedPax.value.label}.`;
        }
    } else if (derivedPax.value && derivedPax.value.label === 'Adult') {
        if (form.title === 'Mstr.') {
            errors.title = "Title 'Mstr.' is only allowed for Children and Infants.";
        }
    }

    errors.firstName = !form.firstName.trim();
    errors.lastName = !form.lastName.trim();
    errors.dob = !form.dob || !derivedPax.value;
    errors.gender = !form.gender;
    errors.nationality = !form.nationality;
    errors.passportNo = !form.passportNo.trim() || !/^[A-Za-z0-9]+$/.test(form.passportNo.trim());
    errors.passportExpiry = !form.passportExpiry;
    errors.passportFiles = !form.passportFiles.length;

    // Email validation
    if (!isChildOrInfant.value) {
        errors.email = !form.email.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim());
    } else {
        errors.email = form.email.trim() ? !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim()) : false;
    }

    // Phone validation
    if (!isChildOrInfant.value) {
        errors.phone = !form.phone.trim() || !/^(?:\+?[1-9]\d{7,14}|0\d{7,14})$/.test(form.phone.trim());
    } else {
        errors.phone = form.phone.trim() ? !/^(?:\+?[1-9]\d{7,14}|0\d{7,14})$/.test(form.phone.trim()) : false;
    }

    return !Object.values(errors).some(Boolean);
}

// function apiErrorMessage(error) {
//     const data = error.response?.data;
//     if (Array.isArray(data?.message)) return data.message.join(' ');
//     if (Array.isArray(data?.errors)) return data.errors.flat().join(' ');
//     return data?.message || 'Unable to update the traveller. Please try again.';
// }

function apiErrorMessage(error) {
    const data = error.response?.data;

    // Handle errors object returned from Laravel validation
    if (data?.errors && typeof data.errors === 'object') {
        const firstKey = Object.keys(data.errors)[0];
        if (firstKey && data.errors[firstKey]?.length) {
            return data.errors[firstKey][0];
        }
    }

    return data?.message || 'Unable to update the traveller. Please try again.';
}


function openSuccessModal() {
    isSuccessModalOpen.value = true;
}
function closeSuccessModal() {
    isSuccessModalOpen.value = false;
}
function goToTravelerList() {
    closeSuccessModal();
    router.push({ name: 'TravelerList' });
}

async function save() {
    if (!validate()) return;
    isSubmitting.value = true;
    try {
        const payload = new FormData();
        payload.append('pax_id', props.ids || form.pax_id);
        payload.append('pax_type', String(derivedPax.value.value));
        payload.append('title_val', form.title);
        payload.append('first_name', form.firstName.trim());
        payload.append('last_name', form.lastName.trim());
        payload.append('dob', form.dob);
        payload.append('gender', form.gender);
        payload.append('email', form.email ? form.email.trim() : '');
        payload.append('phone', form.phone ? form.phone.trim() : '');
        payload.append('passport_no', form.passportNo.trim());
        payload.append('p_expiry_date', form.passportExpiry);
        payload.append('nationality', form.nationality);

        if (form.passportFiles.length && form.passportFiles[0].file instanceof File) {
            payload.append('passport_picture', form.passportFiles[0].file);
        }

        const token = authStore.hasToken ? authStore.decryptWithAES(authStore.token) : '';
        const response = await axios.post('/api/traveler/data/update', payload, {
            headers: { Accept: 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) },
        });

        const message = response.data.message || 'Traveller updated successfully.';

        if (window.Notification?.showToast) {
            window.Notification.showToast('s', message);
            openSuccessModal();
        } else {
            submitError.value = message;
        }
    } catch (error) {
        const message = apiErrorMessage(error);
        if (window.Notification?.showToast) {
            window.Notification.showToast('e', message);
        } else {
            submitError.value = message;
        }
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <AppBreadcrumbs title="Traveller Management" :back-to="{ name: 'TravelerList' }" :breadcrumbs="[
        { label: 'Dashboard', to: { name: 'Home' } },
        { label: 'Traveller Management', to: { name: 'TravelerList' } },
        { label: 'Edit Traveller' },
    ]" />

    <div class="traveler-edit position-relative">
        <div v-if="isLoading" class="text-center py-5">
            <div class="spinner-border text-purple" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div class="mt-2 text-muted">Loading traveller profile...</div>
        </div>

        <div v-else class="accordion">
            <div class="accordion-item traveler-card">
                <h2 class="accordion-header">
                    <div class="accordion-button traveler-header-btn">
                        <span class="traveler-avatar traveler-avatar--adult">
                            <img src="../../../../../public/theme/Booking_Steps/traveller_icon.svg" alt="Traveller">
                        </span>
                        <span class="traveler-header-text">
                            <span class="traveler-header-index">Edit Traveller</span>
                            <span class="traveler-header-type">Update traveller profile</span>
                        </span>
                    </div>
                </h2>

                <div class="traveller-accordion-body">
                    <form @submit.prevent>
                        <div class="row">
                            <div class="col-12">
                                <div class="traveler-form-section-title">
                                    <i class="fa-solid fa-id-card"></i>
                                    <span>Personal Details</span>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 mt-2">
                                <div class="form-row">
                                    <label>Title <span class="text-danger">*</span></label>
                                    <div :class="{ 'select2-error': Boolean(errors.title) }">
                                        <Select2 v-model="form.title" :options="titleOptions"
                                            placeholder="Select Title" />
                                    </div>
                                    <div v-if="errors.title" class="invalid-feedback d-block">
                                        {{ errors.title }}
                                    </div>
                                </div>
                                <div class="form-row">
                                    <label>First Name <span class="text-danger">*</span></label>
                                    <input v-model="form.firstName" class="form-control"
                                        :class="{ 'is-invalid': errors.firstName === true }"
                                        placeholder="Enter First Name" style="font-size: 0.9rem;">
                                    <div class="invalid-feedback">Please enter first name.</div>
                                </div>
                                <div class="form-row">
                                    <label>Last Name <span class="text-danger">*</span></label>
                                    <input v-model="form.lastName" class="form-control"
                                        :class="{ 'is-invalid': errors.lastName === true }"
                                        placeholder="Enter Last Name" style="font-size: 0.9rem;">
                                    <div class="invalid-feedback">Please enter last name.</div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 mt-2">
                                <div class="form-row" :class="{ 'date-field-error': errors.dob === true }">
                                    <label>Date of Birth <span class="text-danger">*</span></label>
                                    <DobWithAge v-model="form.dob" :pax-type="derivedPax?.label"
                                        placeholder="Date of Birth" />
                                    <small v-if="derivedPax" class="text-muted">Passenger type: {{ derivedPax.label
                                    }}</small>
                                    <div v-if="errors.dob === true" class="invalid-feedback d-block">Please select a
                                        valid date of birth.</div>
                                </div>
                                <div class="form-row">
                                    <label>Gender <span class="text-danger">*</span></label>
                                    <div :class="{ 'select2-error': errors.gender === true }">
                                        <Select2 v-model="form.gender" :options="genderOptions"
                                            placeholder="Select Gender" />
                                    </div>
                                    <div v-if="errors.gender === true" class="invalid-feedback d-block">Please select
                                        gender.</div>
                                </div>
                                <div class="form-row">
                                    <label>Nationality <span class="text-danger">*</span></label>
                                    <div :class="{ 'select2-error': errors.nationality === true }">
                                        <Select2 v-model="form.nationality" :options="nationalityOptions"
                                            placeholder="Select Nationality" />
                                    </div>
                                    <div v-if="errors.nationality === true" class="invalid-feedback d-block">Please
                                        select nationality.</div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12 col-md-6 mt-5">
                                    <div class="traveler-form-section-title">
                                        <i class="fa-solid fa-passport"></i>
                                        <span>Travel Document</span>
                                    </div>
                                    <div class="col-12 mt-2">
                                        <label class="form-label">Passport Number <span
                                                class="text-danger">*</span></label>
                                        <input v-model="form.passportNo" class="form-control"
                                            :class="{ 'is-invalid': errors.passportNo === true }"
                                            placeholder="Enter Passport Number" style="font-size: 0.9rem;">
                                        <div class="invalid-feedback">Please enter passport number.</div>
                                    </div>
                                    <div class="col-12 mt-2">
                                        <label class="form-label">Expiry Date <span class="text-danger">*</span></label>
                                        <AppDatePicker v-model="form.passportExpiry" placeholder="Expiry Date"
                                            :min-date="new Date()"
                                            :input-class="errors.passportExpiry === true ? 'form-control is-invalid' : 'form-control'"
                                            input-style="font-size: 1rem; min-height: 38px;" />
                                        <div v-if="errors.passportExpiry === true" class="invalid-feedback d-block">
                                            Please select passport expiry date.</div>
                                    </div>
                                    <div class="col-12 col-md-8 mt-2">
                                        <label class="form-label">Passport Image <span
                                                class="text-danger">*</span></label>
                                        <ImageUploader v-model="form.passportFiles" :max-files="1" />
                                        <div v-if="errors.passportFiles === true" class="invalid-feedback d-block">
                                            Please upload a passport image.</div>
                                    </div>
                                </div>

                                <div class="col-12 col-md-6 mt-5">
                                    <div class="traveler-form-section-title">
                                        <i class="fa-solid fa-address-book"></i>
                                        <span>Contact</span>
                                    </div>
                                    <div class="col-12 mt-2">
                                        <label class="form-label">Email <span v-if="!isChildOrInfant"
                                                class="text-danger">*</span></label>
                                        <input v-model="form.email" type="email" class="form-control"
                                            :class="{ 'is-invalid': errors.email === true }" placeholder="Enter Email"
                                            style="font-size: 0.9rem;">
                                        <div class="invalid-feedback">Please enter a valid email address.</div>
                                    </div>
                                    <div class="col-12 mt-2">
                                        <label class="form-label">
                                            Phone
                                            <span v-if="!isChildOrInfant" class="text-danger">*</span>
                                        </label>
                                        <input v-model="form.phone" class="form-control"
                                            :class="{ 'is-invalid': errors.phone === true }" placeholder="Enter Phone"
                                            style="font-size: 0.9rem;">
                                        <div class="invalid-feedback">Please enter a valid phone number.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 text-end">
                            <div v-if="submitError" class="text-danger small mb-2">{{ submitError }}</div>
                            <AppButton variant="update" label="Update" size="md" :loading="isSubmitting"
                                @click="save" />
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ── SUCCESS MODAL ── -->
        <div class="success-modal-overlay" :class="{ show: isSuccessModalOpen }">
            <div class="success-modal">
                <div class="modal-hero-band">
                    <div class="success-checkmark">
                        <i class="fa fa-check"></i>
                    </div>
                    <h2>Traveler Updated!</h2>
                    <p>Traveler profile for <strong>{{ form.title }} {{ form.firstName }} {{ form.lastName }}</strong>
                        has been updated successfully.</p>
                </div>

                <div class="modal-body-section">
                    <div class="submission-summary">
                        <div class="summary-header">Traveler Summary</div>
                        <div class="summary-row">
                            <span class="summary-label">Passport No</span>
                            <span class="summary-val">{{ form.passportNo }}</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">PAX Type</span>
                            <span class="summary-val">{{ derivedPax?.label || 'Adult' }}</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Nationality</span>
                            <span class="summary-val">{{ form.nationality }}</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Status</span>
                            <span class="status-badge"><i class="fa fa-circle"></i> Active</span>
                        </div>
                    </div>

                    <div class="modal-cta-row">
                        <button type="button" class="btn-home w-100" @click="goToTravelerList">
                            <i class="fa fa-list"></i> Traveler List
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.text-purple {
    color: #7239ea;
}

.traveler-card {
    border: 1px solid rgba(114, 57, 234, .14);
    border-radius: 12px;
    overflow: hidden;
}

.traveler-header-btn {
    display: flex;
    align-items: center;
    gap: 12px;
    background: #fff;
    color: var(--bs-body-color);
    cursor: default;
}

.traveler-header-btn::after {
    display: none;
}

.traveler-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: rgba(114, 57, 234, .12);
    flex: 0 0 auto;
}

.traveler-avatar img {
    width: 21px;
    height: 21px;
}

.traveler-header-text {
    display: grid;
}

.traveler-header-index {
    font-size: 15px;
    font-weight: 700;
}

.traveler-header-type {
    color: #7c8797;
    font-size: 12px;
    margin-top: 1px;
}

.traveller-accordion-body {
    background: rgba(248, 252, 255, 1);
    padding: 1.25rem;
}

.traveler-form-section-title {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #7239ea;
    border-bottom: 1px solid rgba(114, 57, 234, .15);
    padding-bottom: 6px;
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
}

.form-row {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 4px;
    margin-bottom: 12px;
}

.form-row label {
    width: 135px;
    flex: 0 0 auto;
    margin: 0;
    font-size: .875rem;
}

.form-row :deep(.form-control),
.form-row :deep(.form-select),
.form-row :deep(.app-date-picker) {
    flex: 1;
}

.form-row :deep(.form-control-sm),
.form-row :deep(input.form-control) {
    min-height: 38px;
}

.select2-error :deep(.app-select2-control),
.date-field-error :deep(.dob-age-input) {
    border-color: var(--bs-form-invalid-border-color);
}

.traveller-accordion-body :deep(.img-uploader__zone),
.traveller-accordion-body :deep(.img-uploader__preview) {
    width: 100%;
    height: 160px;
    border-radius: 8px;
}

.traveller-accordion-body :deep(.img-uploader__zone) {
    display: flex;
    align-items: center;
    justify-content: center;
}

.traveller-accordion-body :deep(.img-uploader__previews) {
    width: 100%;
    margin-top: 0;
}

/* ── Success Modal ── */
.success-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(6px);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    opacity: 0;
    visibility: hidden;
    transition: all .35s ease;
}

.success-modal-overlay.show {
    opacity: 1;
    visibility: visible;
}

.success-modal {
    background: #fff;
    border-radius: 24px;
    box-shadow: 0 28px 80px rgba(15, 23, 42, 0.24);
    max-width: 480px;
    width: 100%;
    overflow: hidden;
    transform: scale(.88) translateY(24px);
    transition: transform .4s cubic-bezier(.34, 1.56, .64, 1);
}

.success-modal-overlay.show .success-modal {
    transform: scale(1) translateY(0);
}

.modal-hero-band {
    background: linear-gradient(135deg, #1D4ED8 0%, #2563EB 50%, #0891B2 100%);
    padding: 40px 32px 32px;
    text-align: center;
    position: relative;
}

.success-checkmark {
    width: 72px;
    height: 72px;
    background: rgba(255, 255, 255, 0.2);
    border: 2px solid rgba(255, 255, 255, 0.5);
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 16px;
    color: #fff;
    font-size: 2rem;
}

.modal-hero-band h2 {
    font-size: 1.5rem;
    font-weight: 800;
    color: #fff;
    margin-bottom: 8px;
}

.modal-hero-band p {
    font-size: .88rem;
    color: rgba(255, 255, 255, 0.85);
    margin: 0;
}

.modal-body-section {
    padding: 28px 32px;
}

.submission-summary {
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 24px;
}

.summary-header {
    background: #EFF6FF;
    padding: 10px 16px;
    font-size: .75rem;
    font-weight: 700;
    text-transform: uppercase;
    color: #2563EB;
    border-bottom: 1px solid #E2E8F0;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 16px;
    font-size: .85rem;
    border-bottom: 1px solid #F1F5F9;
}

.summary-row:last-child {
    border-bottom: none;
}

.summary-label {
    color: #64748B;
    font-weight: 500;
}

.summary-val {
    color: #1E293B;
    font-weight: 600;
}

.status-badge {
    background: #DCFCE7;
    color: #16A34A;
    font-size: .75rem;
    font-weight: 700;
    padding: 2px 10px;
    border-radius: 99px;
}

.modal-cta-row {
    display: flex;
    gap: 12px;
}

.btn-home {
    background: #2563EB;
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 12px;
    font-weight: 700;
    font-size: .9rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-home:hover {
    background: #1D4ED8;
}

@media (max-width: 767px) {
    .form-row {
        display: block;
        margin-bottom: 12px;
    }

    .form-row label {
        display: block;
        width: auto;
        margin-bottom: 4px;
    }
}

[data-bs-theme="dark"] .traveler-header-btn,
[data-bs-theme="dark"] .traveller-accordion-body {
    background: var(--bs-card-bg);
}

[data-bs-theme="dark"] .traveler-form-section-title {
    color: #c084fc;
    border-bottom-color: rgba(192, 132, 252, .25);
}
</style>

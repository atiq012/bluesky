<script setup>
import { reactive, ref, watch, computed, onMounted, onUnmounted } from "vue";
import VOtpInput from "vue3-otp-input";
import { useAuthStore } from "../../stores/authStore";
const authStore = useAuthStore();
import { useRouter } from 'vue-router';
const router = useRouter();

const bindValue = ref("");
const otpValue = ref();

const loading = ref(false);
const ButtonName = ref("Send");

// Form Data
const STEP_KEY = 'bluesky_reg_step';
const totalSteps = 3;
const currentStep = ref(1);
const stepLabels = ['Agency Details', 'Upload Documents', 'Primary User Info'];

// Reactive form state
const form = reactive({
    agencyName: "",
    establishedDate: "",
    agencyEmail: "",
    agencyCountryDial: "+880",
    agencyPhone: "",
    country: "",
    city: "",
    address: "",
    postalCode: "",
    cacNumber: "",
    tradeLicense: "",
    agencyType: "IATA",
    iataNumber: "",
    hajjType: "Hajj",
    hajjNumber: "",
    logoFile: null,
    logoName: "",
    firstName: "",
    lastName: "",
    nidNumber: "",
    birthDate: "",
    email: "",
    userCountryDial: "+880",
    userPhone: "",
    agreeTerms: false
});

// Uploaded files
const tradeFiles = ref([]);
const cacFiles = ref([]);
const iataFiles = ref([]);
const hajjFiles = ref([]);
const tinFiles = ref([]);
const nidFiles = ref([]);

// File Input Refs
const fileTradeInput = ref(null);
const fileCacInput = ref(null);
const fileIataInput = ref(null);
const fileHajjInput = ref(null);
const fileTinInput = ref(null);
const fileNidInput = ref(null);
const logoInput = ref(null);

// Drag status for zones
const isDragging = reactive({
    trade: false,
    cac: false,
    iata: false,
    hajj: false,
    tin: false,
    nid: false
});

// Modal state
const isSuccessModalOpen = ref(false);

// Validation errors (null = not validated, true = has error, false = valid)
const errors = reactive({
    agencyName: null,
    establishedDate: null,
    agencyEmail: null,
    agencyPhone: null,
    country: null,
    city: null,
    address: null,
    trade: null,
    cac: null,      // ← was missing
    iata: null,     // ← was missing
    hajj: null,     // ← was missing
    tin: null,      // ← was missing
    nid: null,
    firstName: null,
    designation: null,
    nidNumber: null,
    birthDate: null,
    email: null,
    userPhone: null,
    agreeTerms: null
});

// Helpers
function validateEmail(e) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e);
}

// Flags
const agencyFlag = computed(() => {
    const options = {
        '+880': 'bd',
        '+1': 'us',
        '+44': 'gb',
        '+91': 'in',
        '+971': 'ae',
        '+966': 'sa',
        '+65': 'sg',
        '+60': 'my'
    };
    return options[form.agencyCountryDial] || 'bd';
});

const userFlag = computed(() => {
    const options = {
        '+880': 'bd'
    };
    return options[form.userCountryDial] || 'bd';
});

const iataPlaceholder = computed(() => {
    if (form.agencyType === 'IATA') return 'IATA Number';
    if (form.agencyType === 'Non-IATA') return 'Non-IATA Ref. Number';
    return 'Corporate Ref. Number';
});

// Methods
function validateStep1() {
    errors.agencyName = !form.agencyName.trim();
    errors.establishedDate = !form.establishedDate;
    errors.agencyEmail = !validateEmail(form.agencyEmail);
    errors.country = !form.country;
    errors.city = !form.city;
    errors.address = !form.address.trim();
    errors.agencyPhone = form.agencyPhone.trim().length < 6;

    return (
        !errors.agencyName &&
        !errors.establishedDate &&
        !errors.agencyEmail &&
        !errors.country &&
        !errors.city &&
        !errors.address &&
        !errors.agencyPhone
    );
}

function validateStep2() {
    errors.trade = tradeFiles.value.length === 0;
    errors.cac = cacFiles.value.length === 0;
    errors.iata = iataFiles.value.length === 0;
    errors.hajj = hajjFiles.value.length === 0;
    errors.tin = tinFiles.value.length === 0;
    errors.nid = nidFiles.value.length === 0;

    const ok = !errors.trade && !errors.cac && !errors.iata
        && !errors.hajj && !errors.tin && !errors.nid;

    if (!ok) {
        alert('Please upload all required documents.');
    }
    return ok;
}

function validateStep3() {
    errors.firstName = !form.firstName.trim();
    errors.lastName = !form.lastName.trim();
    errors.nidNumber = !form.nidNumber.trim();
    errors.birthDate = !form.birthDate;
    errors.email = !validateEmail(form.email);
    errors.userPhone = form.userPhone.trim().length < 6;
    errors.agreeTerms = !form.agreeTerms;

    return (
        !errors.firstName &&
        !errors.lastName &&
        !errors.nidNumber &&
        !errors.birthDate &&
        !errors.email &&
        !errors.userPhone &&
        !errors.agreeTerms
    );
}

function goNext(step) {
    if (step === 1 && !validateStep1()) return;
    if (step === 2 && !validateStep2()) return;
    showPane(step + 1);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function goPrev(step) {
    showPane(step - 1);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function showPane(step) {
    currentStep.value = step;
    sessionStorage.setItem(STEP_KEY, step);
}

// Logo Handling
function handleLogo(e) {
    const file = e.target.files[0];
    if (file) {
        form.logoFile = file;
        form.logoName = file.name;
    }
}

// File zones logic
function triggerFile(key) {

    if (key === 'trade') fileTradeInput.value?.click();
    else if (key === 'cac') fileCacInput.value?.click();
    else if (key === 'iata') fileIataInput.value?.click();
    else if (key === 'hajj') fileHajjInput.value?.click();
    else if (key === 'tin') fileTinInput.value?.click();
    else if (key === 'nid') fileNidInput.value?.click();
    else if (key === 'logo') logoInput.value?.click();
}

function handleFileChange(e, key) {
    const file = e.target.files[0];
    if (file) {
        addFileItem(key, file);
    }
}

function handleDrop(e, key) {
    isDragging[key] = false;
    const file = e.dataTransfer.files[0];
    if (file) {
        addFileItem(key, file);
    }
}

function addFileItem(key, file) {
    const list = getFileList(key);
    list.value.push(file);
    errors[key] = false;
}

function removeFile(index, key) {
    const list = getFileList(key);
    list.value.splice(index, 1);
}

function getFileList(key) {
    if (key === 'trade') return tradeFiles;
    if (key === 'cac') return cacFiles;
    if (key === 'iata') return iataFiles;
    if (key === 'hajj') return hajjFiles;
    if (key === 'tin') return tinFiles;
    return nidFiles;
}

function hasFiles(key) {
    return getFileList(key).value.length > 0;
}

function getZoneStyle(key) {
    if (errors[key] === true) {
        return { borderColor: '#EF4444', background: '#FEF2F2' };
    }
    if (hasFiles(key)) {
        return { borderColor: 'var(--brand-primary)', background: '#EFF6FF' };
    }
    return {};
}

// Modal Success
function openSuccessModal() {
    isSuccessModalOpen.value = true;
}

function closeSuccessModal() {
    isSuccessModalOpen.value = false;
}

watch(isSuccessModalOpen, (isOpen) => {
    if (isOpen) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
});

// Submit Form
// async function submitForm() {
//     if (!validateStep3()) return;

//     try {
//         const authStore = useAuthStore();
//         const accessToken = authStore.decryptWithAES(authStore.token);
//         const response = await axios.post('/api/agent/registration', form, {
//             headers: {
//                 'Content-Type': 'multipart/form-data',
//                 Authorization: "Bearer " + accessToken,
//                 Accept: "application/json",

//             },
//         });
//         document.getElementById("addAgentForm").reset();
//         Notification.showToast('s', response.data.message);
//     } catch (error) {
//         ErrorCatch.CatchError(error);
//     }
//     // sessionStorage.removeItem(STEP_KEY);
//     // openSuccessModal();
// }

async function submitForm() {
    if (!validateStep3()) return;

    try {
        const fd = new FormData();

        // Agency Details
        fd.append('agencyName',       form.agencyName);
        fd.append('establishedDate',  form.establishedDate);
        fd.append('agencyEmail',      form.agencyEmail);
        fd.append('agencyCountryDial',form.agencyCountryDial);
        fd.append('agencyPhone',      form.agencyPhone);
        fd.append('country',          form.country);
        fd.append('city',             form.city);
        fd.append('address',          form.address);
        fd.append('postalCode',       form.postalCode);
        fd.append('cacNumber',        form.cacNumber);
        fd.append('tradeLicense',     form.tradeLicense);
        fd.append('agencyType',       form.agencyType);
        fd.append('iataNumber',       form.iataNumber);
        fd.append('hajjType',         form.hajjType);
        fd.append('hajjNumber',       form.hajjNumber);

        // Logo
        if (form.logoFile) {
            fd.append('logo', form.logoFile);
        }

        // Primary User Info
        fd.append('firstName',       form.firstName);
        fd.append('designation',     form.designation);
        fd.append('nidNumber',       form.nidNumber);
        fd.append('birthDate',       form.birthDate);
        fd.append('email',           form.email);
        fd.append('userCountryDial', form.userCountryDial);
        fd.append('userPhone',       form.userPhone);
        fd.append('agreeTerms',      form.agreeTerms ? '1' : '0');

        // File uploads — append each file in its array
        tradeFiles.value.forEach((file, i) => fd.append(`tradeFiles[${i}]`, file));
        cacFiles.value.forEach((file, i)   => fd.append(`cacFiles[${i}]`,   file));
        iataFiles.value.forEach((file, i)  => fd.append(`iataFiles[${i}]`,  file));
        hajjFiles.value.forEach((file, i)  => fd.append(`hajjFiles[${i}]`,  file));
        tinFiles.value.forEach((file, i)   => fd.append(`tinFiles[${i}]`,   file));
        nidFiles.value.forEach((file, i)   => fd.append(`nidFiles[${i}]`,   file));

        const accessToken = authStore.decryptWithAES(authStore.token);

        const response = await axios.post('/api/agent/registration', fd, {
            headers: {
                'Content-Type': 'multipart/form-data',
                Authorization: 'Bearer ' + accessToken,
                Accept: 'application/json',
            },
        });

        Notification.showToast('s', response.data.message);
        openSuccessModal();

    } catch (error) {
        ErrorCatch.CatchError(error);
    }
}

function registerAnother() {
    closeSuccessModal();
    resetForm();
}

function resetForm() {
    form.agencyName = "";
    form.establishedDate = "";
    form.agencyEmail = "";
    form.agencyCountryDial = "+880";
    form.agencyPhone = "";
    form.country = "";
    form.city = "";
    form.address = "";
    form.postalCode = "";
    form.cacNumber = "";
    form.tradeLicense = "";
    form.agencyType = "IATA";
    form.iataNumber = "";
    form.hajjType = "Hajj";
    form.hajjNumber = "";
    form.logoFile = null;
    form.logoName = "";
    form.firstName = "";
    form.lastName = "";
    form.nidNumber = "";
    form.birthDate = "";
    form.email = "";
    form.userCountryDial = "+880";
    form.userPhone = "";
    form.agreeTerms = false;

    tradeFiles.value = [];
    cacFiles.value = [];
    iataFiles.value = [];
    hajjFiles.value = [];
    tinFiles.value = [];
    nidFiles.value = [];

    Object.keys(errors).forEach(key => {
        errors[key] = null;
    });

    sessionStorage.removeItem(STEP_KEY);
    showPane(1);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function init() {
    const saved = parseInt(sessionStorage.getItem(STEP_KEY), 10);
    const startStep = (saved >= 1 && saved <= totalSteps) ? saved : 1;
    showPane(startStep);
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            document.body.classList.remove('preload');
        });
    });
}

const handleKeyDown = (e) => {
    if (e.key === 'Escape') closeSuccessModal();
};

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
    init();
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
});
</script>
<template>
    <div class="body">

        <!-- Navbar -->
        <nav class="nav-bar">
            <router-link :to="{ name: 'Login' }" class="brand">
                <div class="brand-logo">
                    <img src="../../../../public/theme/appimages/BS-Logo-B2C-transparent.gif" class="img-fluid"
                        alt="BlueSky Logo">
                </div>
            </router-link>

            <router-link :to="{ name: 'Login' }"><button class="btn-signin">Log In</button></router-link>
        </nav>

        <!-- Hero -->
        <div class="page-hero">
            <h1>Unlock Your Potential as a <span>BlueSky Agent</span></h1>
            <p>Fill up the below form and let's get started on a new journey</p>
        </div>

        <!-- Form Card -->
        <div class="form-card mx-3 mx-md-auto" style="max-width:1080px;">

            <!-- Sidebar -->
            <aside class="sidebar" id="sidebar">
                <div class="step-item" id="si-1" :class="{ active: currentStep === 1, done: currentStep > 1 }">
                    <div class="step-dot-wrap" id="sd-1">
                        <i v-if="currentStep > 1" class="fa fa-check"></i>
                        <i v-else class="fa fa-building"></i>
                    </div>
                    <div class="step-line" id="sl-1"></div>
                    <span class="step-label">Agency Details</span>
                </div>
                <div class="step-item" id="si-2" :class="{ active: currentStep === 2, done: currentStep > 2 }">
                    <div class="step-dot-wrap" id="sd-2">
                        <i v-if="currentStep > 2" class="fa fa-check"></i>
                        <i v-else class="fa fa-file"></i>
                    </div>
                    <div class="step-line" id="sl-2"></div>
                    <span class="step-label">Upload Documents</span>
                </div>
                <div class="step-item" id="si-3" :class="{ active: currentStep === 3 }">
                    <div class="step-dot-wrap" id="sd-3">
                        <i class="fa fa-user"></i>
                    </div>
                    <span class="step-label">Primary User Info</span>
                </div>
            </aside>

            <!-- Form Panel -->
            <main class="form-panel">

                <!-- Progress -->
                <div class="progress-bar-wrap" id="progress-wrap">
                    <div class="progress-bar-track">
                        <div class="progress-bar-fill" id="prog-fill"
                            :style="{ width: (currentStep / totalSteps * 100) + '%' }"></div>
                    </div>
                    <div class="progress-label" id="prog-label">Step {{ currentStep }} of {{ totalSteps }} — {{
                        stepLabels[currentStep - 1] }}</div>
                </div>

                <form id="regForm" novalidate @submit.prevent>

                    <!-- ── STEP 1 ── -->
                    <div class="step-pane" id="pane-1" :class="{ active: currentStep === 1 }">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Agency Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" placeholder="Agency name"
                                    v-model="form.agencyName"
                                    :class="{ 'is-invalid': errors.agencyName === true, 'is-valid': errors.agencyName === false }"
                                    required>
                                <div class="invalid-feedback">Please enter the agency name.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Established Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" v-model="form.establishedDate"
                                    :class="{ 'is-invalid': errors.establishedDate === true, 'is-valid': errors.establishedDate === false }"
                                    required>
                                <div class="invalid-feedback">Please select the established date.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" placeholder="agency@email.com"
                                    v-model="form.agencyEmail"
                                    :class="{ 'is-invalid': errors.agencyEmail === true, 'is-valid': errors.agencyEmail === false }"
                                    required>
                                <div class="invalid-feedback">Please enter a valid email address.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone <span class="text-danger">*</span></label>
                                <div class="phone-group" id="phoneGroup"
                                    :class="{ 'is-invalid': errors.agencyPhone === true, 'is-valid': errors.agencyPhone === false }">
                                    <div class="phone-flag">
                                        <img :src="`https://flagcdn.com/w40/${agencyFlag}.png`" alt="BD" id="flagImg">
                                        <select id="countryDialSelect" v-model="form.agencyCountryDial">
                                            <option value="+880">+880 🇧🇩</option>
                                            <option value="+1">+1 🇺🇸</option>
                                            <option value="+44">+44 🇬🇧</option>
                                            <option value="+91">+91 🇮🇳</option>
                                            <option value="+971">+971 🇦🇪</option>
                                            <option value="+966">+966 🇸🇦</option>
                                            <option value="+65">+65 🇸🇬</option>
                                            <option value="+60">+60 🇲🇾</option>
                                        </select>
                                    </div>
                                    <input class="phone-input" type="tel" placeholder="Phone number"
                                        v-model="form.agencyPhone" required>
                                </div>
                                <div class="invalid-feedback" id="phoneFeedback" v-show="errors.agencyPhone === true">
                                    Please enter a phone number.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Country <span class="text-danger">*</span></label>
                                <select class="form-select" v-model="form.country"
                                    :class="{ 'is-invalid': errors.country === true, 'is-valid': errors.country === false }"
                                    required>
                                    <option value="" disabled selected>Select country</option>
                                    <option>Bangladesh</option>
                                    <option>India</option>
                                    <option>Pakistan</option>
                                    <option>Malaysia</option>
                                    <option>United Arab Emirates</option>
                                    <option>Saudi Arabia</option>
                                    <option>Singapore</option>
                                    <option>United Kingdom</option>
                                    <option>United States</option>
                                </select>
                                <div class="invalid-feedback">Please select a country.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">City <span class="text-danger">*</span></label>
                                <select class="form-select" v-model="form.city"
                                    :class="{ 'is-invalid': errors.city === true, 'is-valid': errors.city === false }"
                                    required>
                                    <option value="" disabled selected>Select city</option>
                                    <option>Dhaka</option>
                                    <option>Chittagong</option>
                                    <option>Sylhet</option>
                                    <option>Khulna</option>
                                    <option>Rajshahi</option>
                                    <option>Barisal</option>
                                    <option>Rangpur</option>
                                    <option>Mymensingh</option>
                                </select>
                                <div class="invalid-feedback">Please select a city.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Address <span class="text-danger">*</span></label>
                                <textarea class="form-control" placeholder="Full address" rows="3"
                                    v-model="form.address"
                                    :class="{ 'is-invalid': errors.address === true, 'is-valid': errors.address === false }"
                                    required style="min-height:70px;"></textarea>
                                <div class="invalid-feedback">Please enter the address.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Postal Code</label>
                                <input type="text" class="form-control" placeholder="Postal code"
                                    v-model="form.postalCode">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Civil Aviation Certificate Number</label>
                                <input type="text" class="form-control" placeholder="CAC number"
                                    v-model="form.cacNumber">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Trade License Number</label>
                                <input type="text" class="form-control" placeholder="Trade license number"
                                    v-model="form.tradeLicense">
                            </div>
                            <div class="col-md-6">
                                <div class="radio-group" id="agencyTypeGroup">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="agencyType" id="typeIata"
                                            value="IATA" v-model="form.agencyType">
                                        <label class="form-check-label" for="typeIata">IATA</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="agencyType" id="typeNonIata"
                                            value="Non-IATA" v-model="form.agencyType">
                                        <label class="form-check-label" for="typeNonIata">Non-IATA</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="agencyType" id="typeCorp"
                                            value="Corporate" v-model="form.agencyType">
                                        <label class="form-check-label" for="typeCorp">Corporate</label>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <input type="text" class="form-control" :placeholder="iataPlaceholder"
                                        v-model="form.iataNumber">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="radio-group" id="hajjTypeGroup">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="hajjType" id="typeHajj"
                                            value="Hajj" v-model="form.hajjType">
                                        <label class="form-check-label" for="typeHajj">Hajj</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="hajjType" id="typeNonHajj"
                                            value="Non Hajj" v-model="form.hajjType">
                                        <label class="form-check-label" for="typeNonHajj">Non Hajj</label>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <input type="text" class="form-control" placeholder="Hajj Number"
                                        v-model="form.hajjNumber">
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Company Logo <small class="text-muted fw-normal">(Max 2 MB —
                                        JPEG or PNG)</small></label>
                                <label class="logo-upload-btn" for="logoFile">
                                    <span>{{ form.logoName || 'Upload Logo (Jpeg or Png)' }}</span>
                                    <div class="up-btn"><i class="fa fa-cloud-upload fs-5"></i></div>
                                </label>
                                <input type="file" id="logoFile" ref="logoInput" accept="image/jpeg,image/png"
                                    class="d-none" @change="handleLogo">
                            </div>
                        </div>
                        <div class="d-flex justify-content-end mt-4">
                            <button type="button" class="btn-next" @click="goNext(1)">Next <i
                                    class="fa fa-arrow-right"></i></button>
                        </div>
                    </div>

                    <!-- ── STEP 2 ── -->
                    <div class="step-pane" id="pane-2" :class="{ active: currentStep === 2 }">
                        <div class="row g-4">
                            <div class="col-12">
                                <div class="section-title">Required Documents</div>
                            </div>
                            <div class="col-md-6 tdl">
                                <label class="form-label">Trade License <span class="text-danger">*</span></label>
                                <div class="upload-zone" id="zone-trade" :style="getZoneStyle('trade')"
                                    :class="{ 'drag-over': isDragging.trade }" @click="triggerFile('trade')"
                                    @drop.prevent="handleDrop($event, 'trade')"
                                    @dragover.prevent="isDragging.trade = true"
                                    @dragleave.prevent="isDragging.trade = false">
                                    <div class="up-icon"><i class="bi bi-file-earmark-text"></i></div>
                                    <p>Drag & drop or <strong>Choose File</strong></p>
                                    <p class="mt-1" style="font-size:.78rem;">PDF, JPG, PNG — max 5 MB</p>
                                    <div class="file-list" id="list-trade">
                                        <div v-for="(file, index) in tradeFiles" :key="index" class="file-item">
                                            <i class="bi bi-file-earmark-check"></i>
                                            <span>{{ file.name }}</span>
                                            <i class="bi bi-x-circle rm" @click.stop="removeFile(index, 'trade')"></i>
                                        </div>
                                    </div>
                                </div>
                                <input type="file" id="file-trade" ref="fileTradeInput" class="d-none"
                                    accept=".pdf,.jpg,.jpeg,.png" @change="handleFileChange($event, 'trade')">
                            </div>
                            <div class="col-md-6 ca">
                                <label class="form-label">Civil Aviation Certificate <span
                                        class="text-danger">*</span></label>
                                <div class="upload-zone" id="zone-cac" :style="getZoneStyle('cac')"
                                    :class="{ 'drag-over': isDragging.cac }" @click="triggerFile('cac')"
                                    @drop.prevent="handleDrop($event, 'cac')" @dragover.prevent="isDragging.cac = true"
                                    @dragleave.prevent="isDragging.cac = false">
                                    <div class="up-icon"><i class="bi bi-award"></i></div>
                                    <p>Drag & drop or <strong>Choose File</strong></p>
                                    <p class="mt-1" style="font-size:.78rem;">PDF, JPG, PNG — max 5 MB</p>
                                    <div class="file-list" id="list-cac">
                                        <div v-for="(file, index) in cacFiles" :key="index" class="file-item">
                                            <i class="bi bi-file-earmark-check"></i>
                                            <span>{{ file.name }}</span>
                                            <i class="bi bi-x-circle rm" @click.stop="removeFile(index, 'cac')"></i>
                                        </div>
                                    </div>
                                </div>
                                <input type="file" id="file-cac" ref="fileCacInput" class="d-none"
                                    accept=".pdf,.jpg,.jpeg,.png" @change="handleFileChange($event, 'cac')">
                            </div>
                            <div class="col-md-6 Iac">
                                <label class="form-label">IATA Certificate <span class="text-danger">*</span></label>
                                <div class="upload-zone" id="zone-iata" :style="getZoneStyle('iata')"
                                    :class="{ 'drag-over': isDragging.iata }" @click="triggerFile('iata')"
                                    @drop.prevent="handleDrop($event, 'iata')"
                                    @dragover.prevent="isDragging.iata = true"
                                    @dragleave.prevent="isDragging.iata = false">
                                    <div class="up-icon"><i class="bi bi-person-vcard"></i></div>
                                    <p>Drag & drop or <strong>Choose File</strong></p>
                                    <p class="mt-1" style="font-size:.78rem;">PDF, JPG, PNG — max 5 MB</p>
                                    <div class="file-list" id="list-iata">
                                        <div v-for="(file, index) in iataFiles" :key="index" class="file-item">
                                            <i class="bi bi-file-earmark-check"></i>
                                            <span>{{ file.name }}</span>
                                            <i class="bi bi-x-circle rm" @click.stop="removeFile(index, 'iata')"></i>
                                        </div>
                                    </div>
                                </div>
                                <input type="file" id="file-iata" ref="fileIataInput" class="d-none"
                                    accept=".pdf,.jpg,.jpeg,.png" @change="handleFileChange($event, 'iata')">
                            </div>
                            <div class="col-md-6 Hj">
                                <label class="form-label">Hajj License <span class="text-danger">*</span></label>
                                <div class="upload-zone" id="zone-hajj" :style="getZoneStyle('hajj')"
                                    :class="{ 'drag-over': isDragging.hajj }" @click="triggerFile('hajj')"
                                    @drop.prevent="handleDrop($event, 'hajj')"
                                    @dragover.prevent="isDragging.hajj = true"
                                    @dragleave.prevent="isDragging.hajj = false">
                                    <div class="up-icon"><i class="bi bi-bank"></i></div>
                                    <p>Drag & drop or <strong>Choose File</strong></p>
                                    <p class="mt-1" style="font-size:.78rem;">PDF, JPG, PNG — max 5 MB</p>
                                    <div class="file-list" id="list-hajj">
                                        <div v-for="(file, index) in hajjFiles" :key="index" class="file-item">
                                            <i class="bi bi-file-earmark-check"></i>
                                            <span>{{ file.name }}</span>
                                            <i class="bi bi-x-circle rm" @click.stop="removeFile(index, 'hajj')"></i>
                                        </div>
                                    </div>
                                </div>
                                <input type="file" id="file-hajj" ref="fileHajjInput" class="d-none"
                                    accept=".pdf,.jpg,.jpeg,.png" @change="handleFileChange($event, 'hajj')">
                            </div>
                            <div class="col-md-6 tn">
                                <label class="form-label">TIN <span class="text-danger">*</span></label>
                                <div class="upload-zone" id="zone-tin" :style="getZoneStyle('tin')"
                                    :class="{ 'drag-over': isDragging.tin }" @click="triggerFile('tin')"
                                    @drop.prevent="handleDrop($event, 'tin')" @dragover.prevent="isDragging.tin = true"
                                    @dragleave.prevent="isDragging.tin = false">
                                    <div class="up-icon"><i class="bi bi-bank"></i></div>
                                    <p>Drag & drop or <strong>Choose File</strong></p>
                                    <p class="mt-1" style="font-size:.78rem;">PDF, JPG, PNG — max 5 MB</p>
                                    <div class="file-list" id="list-tin">
                                        <div v-for="(file, index) in tinFiles" :key="index" class="file-item">
                                            <i class="bi bi-file-earmark-check"></i>
                                            <span>{{ file.name }}</span>
                                            <i class="bi bi-x-circle rm" @click.stop="removeFile(index, 'tin')"></i>
                                        </div>
                                    </div>
                                </div>
                                <input type="file" id="file-tin" ref="fileTinInput" class="d-none"
                                    accept=".pdf,.jpg,.jpeg,.png" @change="handleFileChange($event, 'tin')">
                            </div>
                            <div class="col-md-6 nid">
                                <label class="form-label">NID <span class="text-danger">*</span></label>
                                <div class="upload-zone" id="zone-nid" :style="getZoneStyle('nid')"
                                    :class="{ 'drag-over': isDragging.nid }" @click="triggerFile('nid')"
                                    @drop.prevent="handleDrop($event, 'nid')" @dragover.prevent="isDragging.nid = true"
                                    @dragleave.prevent="isDragging.nid = false">
                                    <div class="up-icon"><i class="bi bi-person-vcard"></i></div>
                                    <p>Drag & drop or <strong>Choose File</strong></p>
                                    <p class="mt-1" style="font-size:.78rem;">PDF, JPG, PNG — max 5 MB</p>
                                    <div class="file-list" id="list-nid">
                                        <div v-for="(file, index) in nidFiles" :key="index" class="file-item">
                                            <i class="bi bi-file-earmark-check"></i>
                                            <span>{{ file.name }}</span>
                                            <i class="bi bi-x-circle rm" @click.stop="removeFile(index, 'nid')"></i>
                                        </div>
                                    </div>
                                </div>
                                <input type="file" id="file-nid" ref="fileNidInput" class="d-none"
                                    accept=".pdf,.jpg,.jpeg,.png" @change="handleFileChange($event, 'nid')">
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="btn-back" @click="goPrev(2)"><i
                                    class="bi bi-arrow-left me-1"></i>
                                Back</button>
                            <button type="button" class="btn-next" @click="goNext(2)">Next <i
                                    class="bi bi-arrow-right ms-1"></i></button>
                        </div>
                    </div>

                    <!-- ── STEP 3 ── -->
                    <div class="step-pane" id="pane-3" :class="{ active: currentStep === 3 }">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="section-title" style="margin-top:0;">Primary User Information</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Owner Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" placeholder="First name"
                                    v-model="form.firstName"
                                    :class="{ 'is-invalid': errors.firstName === true, 'is-valid': errors.firstName === false }"
                                    required>
                                <div class="invalid-feedback">Please enter the owner name.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Designation <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" placeholder="Designation"
                                    v-model="form.lastName"
                                    :class="{ 'is-invalid': errors.lastName === true, 'is-valid': errors.lastName === false }"
                                    required>
                                <div class="invalid-feedback">Please enter designation.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">NID Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" placeholder="NID Number"
                                    v-model="form.nidNumber"
                                    :class="{ 'is-invalid': errors.nidNumber === true, 'is-valid': errors.nidNumber === false }"
                                    required>
                                <div class="invalid-feedback">Please enter a valid NID number.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Birth Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" v-model="form.birthDate"
                                    :class="{ 'is-invalid': errors.birthDate === true, 'is-valid': errors.birthDate === false }"
                                    required>
                                <div class="invalid-feedback">Please select the birth date.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" placeholder="user@email.com"
                                    v-model="form.email"
                                    :class="{ 'is-invalid': errors.email === true, 'is-valid': errors.email === false }"
                                    required>
                                <div class="invalid-feedback" id="emailFeedback">Please enter a valid email address.
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone <span class="text-danger">*</span></label>
                                <div class="phone-group" id="phoneGroup2"
                                    :class="{ 'is-invalid': errors.userPhone === true, 'is-valid': errors.userPhone === false }">
                                    <div class="phone-flag">
                                        <img :src="`https://flagcdn.com/w40/${userFlag}.png`" alt="BD" id="flagImg2">
                                        <select id="countryDialSelect2" v-model="form.userCountryDial">
                                            <option value="+880">+880 🇧🇩</option>
                                        </select>
                                    </div>
                                    <input class="phone-input" type="tel" placeholder="Phone number"
                                        v-model="form.userPhone" required>
                                </div>
                                <div class="invalid-feedback" id="phoneFeedback2" v-show="errors.userPhone === true">
                                    Please enter a phone number.</div>
                            </div>
                            <div class="col-12">
                                <div class="section-title">Agreements</div>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="agreeTerms"
                                        v-model="form.agreeTerms"
                                        :class="{ 'is-invalid': errors.agreeTerms === true, 'is-valid': errors.agreeTerms === false }"
                                        required style="accent-color:#2563EB;">
                                    <label class="form-check-label" for="agreeTerms"
                                        style="font-size:.88rem;color:#475569;">
                                        I agree to the <a href="#" style="color:#2563EB;font-weight:600;">Terms
                                            of Service</a> and <a href="#"
                                            style="color:#2563EB;font-weight:600;">Privacy Policy</a>
                                    </label>
                                    <div class="invalid-feedback">You must agree to the terms before proceeding.</div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="btn-back" @click="goPrev(3)"><i
                                    class="bi bi-arrow-left me-1"></i>
                                Back</button>
                            <button type="button" class="btn-submit" @click="submitForm">
                                <i class="bi bi-send-check me-1"></i> Submit Registration
                            </button>
                        </div>
                    </div>

                </form>

            </main>
        </div>

        <!-- ══════════════ SUCCESS MODAL ══════════════ -->
        <div class="success-modal-overlay" :class="{ show: isSuccessModalOpen }" role="dialog" aria-modal="true"
            aria-labelledby="modalTitle" @click.self="closeSuccessModal">
            <div class="success-modal">

                <div class="modal-hero-band">
                    <i class="bi bi-airplane-fill modal-plane-deco"></i>
                    <div class="success-checkmark">
                        <i class="bi bi-check-lg"></i>
                    </div>
                    <h2 id="modalTitle">Registration Submitted!</h2>
                    <p>Your agency application has been received. Our team will review it and reach out within
                        <strong>2–3 business days</strong>.
                    </p>
                </div>

                <div class="modal-body-section">

                    <div class="submission-summary" id="modalSummary">
                        <div class="summary-header">Submission Summary</div>
                        <div class="summary-row">
                            <span class="summary-label">Agency Name</span>
                            <span class="summary-val">{{ form.agencyName }}</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Email</span>
                            <span class="summary-val">{{ form.agencyEmail }}</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Location</span>
                            <span class="summary-val">{{ form.city }}, {{ form.country }}</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Status</span>
                            <span class="summary-val">
                                <span class="status-badge"><i class="bi bi-circle-fill"></i> Pending Review</span>
                            </span>
                        </div>
                    </div>

                    <div class="modal-info-note">
                        <i class="bi bi-info-circle-fill"></i>
                        <span>Check your inbox for a confirmation email. You'll receive login credentials once your
                            account is approved.</span>
                    </div>

                    <div class="modal-cta-row">
                        <router-link :to="{ name: 'Login' }" class="btn-home" id="btnGoHome" @click="closeSuccessModal">
                            <i class="bi bi-house-door-fill"></i> Return to Home
                        </router-link>
                        <button type="button" class="btn-register-another" @click="registerAnother">
                            <i class="bi bi-plus-circle"></i> New Registration
                        </button>
                    </div>

                </div>
            </div>
        </div>
    </div>
</template>
<script>

</script>

<style scoped>
.body {
    font-family: 'Be Vietname Pro', sans-serif;
    background-image: url('../../../../public/theme/appimages/Reg_Bg_Image.jpg');
    background-size: contain;
    background-repeat: no-repeat;
    min-height: 100vh;
    color: #1E293B;
}

/* ── Navbar ─────────────────────────────────────── */
.nav-bar {
    background: #fff;
    box-shadow: 0 1px 0 #E2E8F0;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 40px;
    position: sticky;
    top: 0;
    z-index: 100;
}

.brand {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
}

.brand-logo {
    width: 200px;
    height: 60px;
    position: relative;
}

.btn-signin {
    background: #2563EB;
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 9px 22px;
    font-family: 'Be Vietname Pro', sans-serif;
    font-weight: 400;
    font-size: .875rem;
    cursor: pointer;
    transition: background .25s cubic-bezier(.4, 0, .2, 1), box-shadow .25s cubic-bezier(.4, 0, .2, 1);
}

.btn-signin:hover {
    background: #1D4ED8;
    box-shadow: 0 4px 14px rgba(37, 99, 235, .35);
}

/* ── Page hero ───────────────────────────────────── */
.page-hero {
    text-align: center;
    padding: 48px 20px 36px;
}

.page-hero h1 {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 2rem;
    font-weight: 800;
    color: #1E293B;
    margin: 0;
}

.page-hero h1 span {
    color: #2563EB;
}

.page-hero p {
    color: #64748B;
    margin-top: 8px;
    font-size: .95rem;
}

/* ── Main card ───────────────────────────────────── */
.form-card {
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 4px 40px rgba(30, 41, 59, .08), 0 1px 2px rgba(30, 41, 59, .04);
    max-width: 1080px;
    margin: 0 auto 60px;
    display: flex;
    overflow: hidden;
    min-height: 560px;
}

/* ── Sidebar ─────────────────────────────────────── */
.sidebar {
    width: 260px;
    flex-shrink: 0;
    background: #F0F6FF;
    border-right: 1px solid #E2EEF9;
    padding: 40px 24px;
    display: flex;
    flex-direction: column;
    gap: 0;
}

.step-item {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    position: relative;
    padding-bottom: 90px;
}

.step-item:last-child {
    padding-bottom: 0;
}

.step-line {
    position: absolute;
    left: calc(36px/2 - 1px);
    top: 36px;
    width: 2px;
    bottom: 0;
    background: #CBD5E1;
    border-radius: 2px;
    transition: background .25s cubic-bezier(.4, 0, .2, 1);
}

.step-item.done .step-line,
.step-item.active .step-line {
    background: #2563EB;
}

.step-dot-wrap {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #CBD5E1;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: .8rem;
    color: #fff;
    font-weight: 700;
    transition: background .25s cubic-bezier(.4, 0, .2, 1), box-shadow .25s cubic-bezier(.4, 0, .2, 1);
    position: relative;
    z-index: 1;
}

.step-item.active .step-dot-wrap {
    background: #2563EB;
    box-shadow: 0 0 0 4px #BFDBFE;
}

.step-item.done .step-dot-wrap {
    background: #2563EB;
}

.step-label {
    padding-top: 6px;
    font-family: 'Be Vietname Pro', sans-serif;
    font-size: .85rem;
    font-weight: 500;
    color: #94A3B8;
    transition: color .25s cubic-bezier(.4, 0, .2, 1);
}

.step-item.active .step-label,
.step-item.done .step-label {
    color: #2563EB;
    font-weight: 600;
}

/* ── Form panel ──────────────────────────────────── */
.form-panel {
    flex: 1;
    padding: 40px 44px 36px;
    display: flex;
    flex-direction: column;
}

/* suppress animations on initial page load to prevent flicker */
body.preload *,
body.preload *::before,
body.preload *::after {
    animation-duration: 0s !important;
    transition-duration: 0s !important;
}

.step-pane {
    display: none;
    animation: fadeIn .3s ease;
}

.step-pane.active {
    display: block;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(8px);
    }

    to {
        opacity: 1;
        transform: none;
    }
}

/* ── Inputs ──────────────────────────────────────── */
.form-label {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: .8rem;
    font-weight: 600;
    color: #475569;
    margin-bottom: 6px;
    letter-spacing: .02em;
}

.form-control,
.form-select {
    border: 1.5px solid #E2E8F0;
    border-radius: 10px;
    padding: 10px 14px;
    font-size: .9rem;
    color: #3F4754;
    transition: border-color .25s cubic-bezier(.4, 0, .2, 1), box-shadow .25s cubic-bezier(.4, 0, .2, 1);
}

.form-control:focus,
.form-select:focus {
    border-color: #2563EB;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .15);
    background: #fff;
    outline: none;
}

.form-control::placeholder {
    color: #CBD5E1;
}

textarea.form-control {
    resize: none;
    min-height: 90px;
}

/* phone group */
.phone-group {
    display: flex;
    border: 1.5px solid #E2E8F0;
    border-radius: 10px;
    overflow: hidden;
    background: #FAFBFC;
    transition: border-color .25s cubic-bezier(.4, 0, .2, 1), box-shadow .25s cubic-bezier(.4, 0, .2, 1);
}

.phone-group:focus-within {
    border-color: #2563EB;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .15);
}

.phone-flag {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 0 12px;
    border-right: 1.5px solid #E2E8F0;
    background: #F1F5F9;
    font-size: .85rem;
    white-space: nowrap;
    cursor: pointer;
}

.phone-flag img {
    width: 20px;
    height: 14px;
    border-radius: 2px;
    object-fit: cover;
}

.phone-flag select {
    border: none;
    background: transparent;
    font-size: .82rem;
    color: #475569;
    font-weight: 600;
    outline: none;
    cursor: pointer;
}

.phone-input {
    flex: 1;
    border: none;
    background: transparent;
    padding: 10px 14px;
    font-size: .9rem;
    color: #1E293B;
    outline: none;
}

.phone-input::placeholder {
    color: #CBD5E1;
}

/* radio group */
.radio-group {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.radio-group .form-check {
    margin: 0;
    padding: 0;
    display: flex;
    align-items: center;
    gap: 7px;
    cursor: pointer;
}

.radio-group .form-check-input {
    width: 18px;
    height: 18px;
    border: 2px solid #CBD5E1;
    background: #fff;
    margin: 0;
    cursor: pointer;
    accent-color: #2563EB;
    transition: border-color .25s cubic-bezier(.4, 0, .2, 1);
}

.radio-group .form-check-input:checked {
    border-color: #2563EB;
}

.radio-group .form-check-label {
    font-size: .88rem;
    color: #475569;
    font-weight: 500;
    cursor: pointer;
}

/* file upload zone */
.upload-zone {
    border: 2px dashed #CBD5E1;
    border-radius: 10px;
    background: #F8FAFC;
    padding: 32px 20px;
    text-align: center;
    cursor: pointer;
    transition: border-color .25s cubic-bezier(.4, 0, .2, 1), background .25s cubic-bezier(.4, 0, .2, 1);
}

.upload-zone:hover,
.upload-zone.drag-over {
    border-color: #2563EB;
    background: #EFF6FF;
}

.upload-zone .up-icon {
    width: 44px;
    height: 44px;
    background: #DBEAFE;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 10px;
    color: #2563EB;
    font-size: 1.2rem;
}

.upload-zone p {
    margin: 0;
    font-size: .85rem;
    color: #64748B;
}

.upload-zone p strong {
    color: #2563EB;
}

.file-list {
    margin-top: 12px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.file-item {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #EFF6FF;
    border-radius: 8px;
    padding: 6px 12px;
    font-size: .82rem;
    color: #334155;
}

.file-item i {
    color: #2563EB;
}

.file-item .rm {
    margin-left: auto;
    cursor: pointer;
    color: #94A3B8;
    font-size: .85rem;
}

.file-item .rm:hover {
    color: #EF4444;
}

/* logo upload */
.logo-upload-btn {
    display: flex;
    align-items: center;
    border: 1.5px solid #E2E8F0;
    border-radius: 10px;
    background: #FAFBFC;
    overflow: hidden;
    cursor: pointer;
    transition: border-color .25s cubic-bezier(.4, 0, .2, 1);
}

.logo-upload-btn:hover {
    border-color: #2563EB;
}

.logo-upload-btn span {
    flex: 1;
    padding: 10px 14px;
    font-size: .9rem;
    color: #CBD5E1;
}

.logo-upload-btn .up-btn {
    background: #2563EB;
    padding: 10px 16px;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* ── Buttons ─────────────────────────────────────── */
.btn-next,
.btn-submit {
    background: #2563EB;
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 11px 36px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-weight: 700;
    font-size: .95rem;
    cursor: pointer;
    transition: background .25s cubic-bezier(.4, 0, .2, 1), box-shadow .25s cubic-bezier(.4, 0, .2, 1), transform .25s cubic-bezier(.4, 0, .2, 1);
    letter-spacing: .01em;
}

.btn-next:hover,
.btn-submit:hover {
    background: #1D4ED8;
    box-shadow: 0 6px 20px rgba(37, 99, 235, .35);
    transform: translateY(-1px);
}

.btn-back {
    background: #F1F5F9;
    color: #475569;
    border: none;
    border-radius: 10px;
    padding: 11px 28px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-weight: 600;
    font-size: .95rem;
    cursor: pointer;
    transition: background .25s cubic-bezier(.4, 0, .2, 1);
}

.btn-back:hover {
    background: #E2E8F0;
}

/* ── Validation ──────────────────────────────────── */
.form-control.is-invalid,
.form-select.is-invalid,
.phone-group.is-invalid {
    border-color: #EF4444 !important;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, .12) !important;
}

.invalid-feedback {
    font-size: .78rem;
    color: #EF4444;
    margin-top: 4px;
    display: none;
}

.is-invalid~.invalid-feedback,
.phone-group.is-invalid~.invalid-feedback {
    display: block;
}

/* ── Responsive ──────────────────────────────────── */
@media (max-width: 768px) {
    .form-card {
        flex-direction: column;
        margin: 0 16px 40px;
    }

    .sidebar {
        width: 100%;
        flex-direction: row;
        padding: 20px;
        gap: 0;
        justify-content: space-around;
    }

    .step-item {
        flex-direction: column;
        align-items: center;
        padding-bottom: 0;
        flex: 1;
    }

    .step-line {
        display: none;
    }

    .step-label {
        font-size: .7rem;
        text-align: center;
        padding-top: 4px;
    }

    .form-panel {
        padding: 28px 20px 24px;
    }

    .nav-bar {
        padding: 0 20px;
    }

    .page-hero h1 {
        font-size: 1.4rem;
    }
}

.section-title {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: .78rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: #94A3B8;
    margin: 28px 0 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.section-title::after {
    content: '';
    flex: 1;
    height: 1px;
    background: #E2E8F0;
}

.progress-bar-wrap {
    margin-bottom: 28px;
}

.progress-bar-track {
    height: 4px;
    background: #E2E8F0;
    border-radius: 99px;
    overflow: hidden;
}

.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #2563EB, #06B6D4);
    border-radius: 99px;
    transition: width .4s ease;
}

.progress-label {
    font-size: .78rem;
    color: #94A3B8;
    margin-top: 6px;
    font-weight: 500;
}

.tdl {
    width: 33% !important;
}

.ca {
    width: 33% !important;
}

.Iac {
    width: 33% !important;
}

.Hj {
    width: 33% !important;
}

.tn {
    width: 33% !important;
}

.nid {
    width: 33% !important;
}

/* ══════════════════════════════════════════════════
       SUCCESS MODAL
    ══════════════════════════════════════════════════ */
.success-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.58);
    backdrop-filter: blur(7px);
    -webkit-backdrop-filter: blur(7px);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    opacity: 0;
    visibility: hidden;
    transition: opacity .35s ease, visibility .35s ease;
}

.success-modal-overlay.show {
    opacity: 1;
    visibility: visible;
}

.success-modal {
    background: #fff;
    border-radius: 24px;
    box-shadow: 0 28px 80px rgba(15, 23, 42, .24), 0 4px 16px rgba(15, 23, 42, .08);
    max-width: 500px;
    width: 100%;
    overflow: hidden;
    transform: scale(.88) translateY(24px);
    transition: transform .42s cubic-bezier(.34, 1.56, .64, 1);
}

.success-modal-overlay.show .success-modal {
    transform: scale(1) translateY(0);
}

/* gradient hero band */
.modal-hero-band {
    background: linear-gradient(135deg, #1D4ED8 0%, #2563EB 50%, #0891B2 100%);
    padding: 44px 40px 36px;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.modal-hero-band::before {
    content: '';
    position: absolute;
    inset: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/svg%3E");
}

.modal-plane-deco {
    position: absolute;
    right: 28px;
    top: 22px;
    font-size: 2.4rem;
    opacity: .15;
    transform: rotate(18deg);
}

.success-checkmark {
    width: 80px;
    height: 80px;
    background: rgba(255, 255, 255, .16);
    border: 2px solid rgba(255, 255, 255, .4);
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
    position: relative;
    animation: checkPop .5s cubic-bezier(.34, 1.56, .64, 1) .1s both;
}

@keyframes checkPop {
    from {
        transform: scale(0) rotate(-30deg);
        opacity: 0;
    }

    to {
        transform: scale(1) rotate(0deg);
        opacity: 1;
    }
}

.success-checkmark i {
    font-size: 2.2rem;
    color: #fff;
}

.success-checkmark::before,
.success-checkmark::after {
    content: '';
    position: absolute;
    inset: -12px;
    border-radius: 50%;
    border: 1.5px solid rgba(255, 255, 255, .2);
    animation: ripplePulse 2.4s ease-out infinite;
}

.success-checkmark::after {
    inset: -24px;
    animation-delay: .6s;
}

@keyframes ripplePulse {
    0% {
        transform: scale(.75);
        opacity: 0;
    }

    35% {
        opacity: 1;
    }

    100% {
        transform: scale(1.1);
        opacity: 0;
    }
}

.modal-hero-band h2 {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 1.6rem;
    font-weight: 800;
    color: #fff;
    margin: 0 0 8px;
}

.modal-hero-band p {
    font-size: .88rem;
    color: rgba(255, 255, 255, .82);
    margin: 0;
    line-height: 1.55;
}

.modal-hero-band p strong {
    color: #fff;
}

/* body */
.modal-body-section {
    padding: 28px 36px 34px;
}

/* summary */
.submission-summary {
    background: #F7FAFF;
    border: 1px solid #DDE9FF;
    border-radius: 14px;
    overflow: hidden;
    margin-bottom: 22px;
}

.summary-header {
    background: #EFF6FF;
    padding: 9px 16px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: .7rem;
    font-weight: 700;
    letter-spacing: .09em;
    text-transform: uppercase;
    color: #2563EB;
    border-bottom: 1px solid #DDE9FF;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 16px;
    font-size: .83rem;
}

.summary-row:not(:last-child) {
    border-bottom: 1px solid #EEF3FB;
}

.summary-label {
    color: #64748B;
    font-weight: 500;
}

.summary-val {
    color: #1E293B;
    font-weight: 600;
    max-width: 58%;
    text-align: right;
    word-break: break-word;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #DCFCE7;
    color: #16A34A;
    font-size: .77rem;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 99px;
}

.status-badge i {
    font-size: .52rem;
}

/* info note */
.modal-info-note {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    background: #FFFBEB;
    border: 1px solid #FDE68A;
    border-radius: 10px;
    padding: 11px 14px;
    margin-bottom: 24px;
    font-size: .81rem;
    color: #92400E;
    line-height: 1.5;
}

.modal-info-note i {
    font-size: 1rem;
    color: #F59E0B;
    flex-shrink: 0;
    margin-top: 1px;
}

/* CTA row */
.modal-cta-row {
    display: flex;
    gap: 12px;
}

.btn-home {
    flex: 1;
    background: #2563EB;
    color: #fff;
    border: none;
    border-radius: 11px;
    padding: 13px 20px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-weight: 700;
    font-size: .91rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: background .25s cubic-bezier(.4, 0, .2, 1), box-shadow .25s cubic-bezier(.4, 0, .2, 1), transform .25s cubic-bezier(.4, 0, .2, 1);
    text-decoration: none;
}

.btn-home:hover {
    background: #1D4ED8;
    box-shadow: 0 6px 20px rgba(37, 99, 235, .35);
    transform: translateY(-1px);
    color: #fff;
}

.btn-register-another {
    flex: 1;
    background: #F1F5F9;
    color: #475569;
    border: 1.5px solid #E2E8F0;
    border-radius: 11px;
    padding: 13px 16px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-weight: 600;
    font-size: .89rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    transition: background .25s cubic-bezier(.4, 0, .2, 1), border-color .25s cubic-bezier(.4, 0, .2, 1);
}

.btn-register-another:hover {
    background: #E8EEF7;
    border-color: #CBD5E1;
}

@media (max-width: 520px) {
    .modal-hero-band {
        padding: 34px 24px 26px;
    }

    .modal-body-section {
        padding: 22px 20px 28px;
    }

    .modal-cta-row {
        flex-direction: column;
    }
}
</style>

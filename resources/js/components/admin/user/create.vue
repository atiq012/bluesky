<script setup>
import AppBreadcrumbs from '../../common/AppBreadcrumbs.vue';

import { useAuthStore } from "../../../stores/authStore";
import axiosInstance from "../../../axiosInstance";

import { ref, onMounted, reactive, watch} from "vue";
import AppButton from '../../common/AppButton.vue';
import ImageCropUpload from '../../common/ImageCropUpload.vue';
import { useRouter } from 'vue-router';


const authStore = useAuthStore();
//**** create function start
const form = reactive({
    useEmail: authStore.email, name: '', email: "", staff_id: '',
    profile_picture: '',
    phone: '', dept_name: '', desg: '',
});

const errors = reactive({
    name: null, 
    email: null, 
    staff_id: null, 
    phone: null, 
    dept_name: null, 
    
});

const router = useRouter();
function goBack() {
    router.push({ name: 'UserList' });
}

const submitting = ref(false);
const profileImageFile = ref(null);

watch(() => form.name,      v => { if (v)        errors.name = null; });
watch(() => form.email,     v => { if (v)        errors.email = null; });
watch(() => form.staff_id,  v => { if (v)        errors.staff_id = null; });
watch(() => form.phone,     v => { if (v)        errors.phone = null; });
watch(() => form.dept_name, v => { if (v)        errors.dept_name = null; });
watch(profileImageFile, (file) => {
    form.profile_picture = file || null;
});


// Mirrors the backend rule in UserController::agntUserstore — keep both in sync.
const validEmailRegex = /^[A-Za-z0-9]+([._%+-][A-Za-z0-9]+)*@[A-Za-z0-9]+([.-][A-Za-z0-9]+)*\.[A-Za-z]{2,}$/;

function emailError(raw) {
    const value = (raw || '').trim();

    if (!value) return 'Please enter an email address.';
    if (/\s/.test(value)) return 'Email address cannot contain spaces.';
    if (value.length > 150) return 'Email address cannot be longer than 150 characters.';
    if ((value.match(/@/g) || []).length !== 1) return 'Email address must contain exactly one @ symbol.';
    if (value.includes('..')) return 'Email address cannot contain consecutive dots.';

    const [local, domain] = value.split('@');

    if (!local) return 'Please enter the part before the @ symbol.';
    if (local.length > 64) return 'The part before @ cannot be longer than 64 characters.';
    if (!domain) return 'Please enter the domain after the @ symbol.';
    if (!domain.includes('.')) return 'Domain must include a dot, e.g. example.com';
    if (!validEmailRegex.test(value)) return 'Please enter a valid email address.';

    return null;
}

function validate(type) {
    // Reset all
    Object.keys(errors).forEach(k => errors[k] = null);
    const validPhoneRegex = /^(?:\+?[1-9]\d{7,14}|0\d{7,14})$/;


    if (!form.name.trim())
        errors.name = 'Please enter a name.';
    errors.email = emailError(form.email);
    if (!form.staff_id.trim())
        errors.staff_id = 'Please enter a staff ID.';
    if (!form.phone.trim() || !validPhoneRegex.test(form.phone.trim())) 
        errors.phone = 'Please enter a valid phone number.';
    if (!form.dept_name.trim()) 
        errors.dept_name = 'Please enter a department name.';
    
    
    return !Object.values(errors).some(Boolean);
}

// Copies Laravel's errors bag onto the field messages; returns true if anything was shown.
function applyServerErrors(payload) {
    const bag = payload?.errors;

    if (!bag || typeof bag !== 'object') {
        return false;
    }

    let shown = false;

    Object.keys(errors).forEach(field => {
        const message = Array.isArray(bag[field]) ? bag[field][0] : bag[field];

        if (message) {
            errors[field] = message;
            shown = true;
        }
    });

    return shown;
}

async function save() {
    if (!validate()) {
        return;
    }

    submitting.value = true;
    // console.log(form);
    try {
        
        // const response = await axiosInstance.post("/external-user/save", form);
        const authStore = useAuthStore();
        const accessToken = authStore.decryptWithAES(authStore.token);
        const response = await axios.post('/api/agent-external-user/save', form, {
            headers: {
                'Content-Type': 'multipart/form-data',
                Authorization: "Bearer " + accessToken,
                Accept: "application/json",

            },
        });

        // Backend answers 200 with types 'e' in some paths, so never assume success.
        if (response.data?.types === 'e') {
            applyServerErrors(response.data);
            Notification.showToast('e', response.data.message);
            return;
        }

        document.getElementById("addUserform").reset();
        //previewImage.value = '';
        profileImageFile.value = null;

        Notification.showToast('s', response.data.message);
        router.push({ name: 'UserList' });


    } catch (error) {
        // Duplicate email / validation failures come back as 422 with a per-field errors bag.
        if (error?.response?.status === 422 && applyServerErrors(error.response.data)) {
            Notification.showToast('e', error.response.data.message);
            return;
        }

        ErrorCatch.CatchError(error);

    } finally {
        submitting.value = false;
    }
}
const previewImage = ref('');
const profilePicture = ref(null);

const handleFileChange = (event) => {
    form.profile_picture = event.target.files[0];
    const reader = new FileReader();
    reader.readAsDataURL(form.profile_picture);
    // console.log(reader.readAsDataURL(form.profile_picture));

    reader.onload = (e) => {
        previewImage.value = e.target.result;
    };
}

// triggers the hidden file input when the upload box or "Choose File" link is clicked
function triggerFileInput() {
    profilePicture.value.click();
}
</script>

<template>
        <AppBreadcrumbs
        title="User Managemnet"
        :back-to="{ name: 'UserList' }"
        :breadcrumbs="[
            { label: 'Dashboard', to: { name: 'Home' } },
            { label: 'User List', to: { name: 'UserList' } },
            { label: 'Create New User' },
        ]"
    />

    <div class="card user-create-card">
        <div class="card-header bg-white">
            <h5 class="m-0 p-0 card-title-accent">&nbsp; Create New User</h5>
        </div>
        <form id="addUserform">

            <div class="card-body p-4">
                <div class="row g-4">
                    <!-- Profile Image -->
                    <!-- <div class="col-lg-2">
                        <label class="form-label">Profile Image</label>

                        <div class="profile-upload-box" @click="triggerFileInput">
                            <img v-if="previewImage" :src="previewImage" alt="Profile Picture"
                                class="profile-preview-img">
                            <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="1.6">
                                <rect x="3" y="5" width="18" height="14" rx="2" />
                                <circle cx="9" cy="10.5" r="1.6" />
                                <path d="M3 16.5l5-4.5 3.5 3 4-3.5L21 16" />
                            </svg>
                        </div>

                        <button type="button" class="choose-file-btn" @click="triggerFileInput">Choose File</button>
                        <input type="file" id="profile-picture" ref="profilePicture" class="d-none"
                            @change="handleFileChange" accept="image/*">
                    </div> -->

                    <div class="col-lg-3">
                        <label class="form-label">Profile Image</label>
                        <ImageCropUpload
                            v-model="profileImageFile"
                            shape="circle"
                            size-class="profile-image-preview"
                            :max-file-size-mb="2"
                            crop-modal-title="Crop Profile Image"
                        >
                            {{ profileImageFile ? 'Click to change · drag to reposition while cropping' : 'JPEG, PNG, GIF or WebP · max 2 MB' }}
                        </ImageCropUpload>
                    </div>

                    <!-- Form Fields -->
                    <div class="col-lg-9">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Name
                                    <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control custom-input" id="name"
                                    placeholder="Enter Your Name" v-model="form.name" :class="{ 'is-invalid': errors.name }">
                                <div v-if="errors.name" class="invalid-feedback d-block">
                                    {{ errors.name }}   
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="staff_id" class="form-label">Staff ID
                                    <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control custom-input" id="staff_id"
                                    placeholder="Enter Your Staff ID" v-model="form.staff_id" :class="{ 'is-invalid': errors.staff_id }">
                                <div v-if="errors.staff_id" class="invalid-feedback d-block">
                                    {{ errors.staff_id }}
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label">Email
                                    <span class="text-danger">*</span>
                                </label>
                                <input type="email" class="form-control custom-input" id="email"
                                    placeholder="Enter Your Email" v-model="form.email" :class="{ 'is-invalid': errors.email }">
                                <div v-if="errors.email" class="invalid-feedback d-block">
                                    {{ errors.email }}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone
                                    <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control custom-input" id="phone"
                                    placeholder="Enter Your Phone Number" v-model="form.phone" :class="{ 'is-invalid': errors.phone }">
                                <div v-if="errors.phone" class="invalid-feedback d-block">
                                    {{ errors.phone }}
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="deptment_id1" class="form-label">Department
                                    <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control custom-input" id="deptment_id1"
                                    placeholder="Enter Department Name" v-model="form.dept_name" :class="{ 'is-invalid': errors.dept_name }">
                                
                                <div v-if="errors.dept_name" class="invalid-feedback d-block">
                                    {{ errors.dept_name }}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="desg_id1" class="form-label">Designation</label>
                                <input type="text" class="form-control custom-input" id="desg_id1"
                                    placeholder="Enter Your Designation" v-model="form.desg_id">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-white mb-3 gap-3 d-flex justify-content-end">
                <AppButton variant="cancel" @click="goBack"/>
                <AppButton variant="save" label="Save" :loading="submitting" @click="save()" />
                <!-- <button type="button" @click="save()" class="btn btn-save px-4 float-end ms-2 mb-3">Save</button> -->
                <!-- <button type="button" class="btn btn-back px-4 float-end mb-3" @click="$router.go(-1)">Back</button> -->
            </div>
        </form>
    </div>
</template>

<style scoped>
.user-create-card {
    border: none;
    border-radius: 14px;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
}

.card-title-accent {
    border-left: 4px solid #4f6df5;
    padding-left: 10px;
    font-weight: 600;
    color: #1d2433;
}

.form-label {
    font-size: 0.875rem;
    font-weight: 500;
    color: #344054;
    margin-bottom: 6px;
}

.custom-input {
    border: 1px solid #e3e8ef;
    border-radius: 10px;
    padding: 10px 14px;
    font-size: 0.9rem;
    background: #fff;
    width: 100%;
}

.custom-input:focus {
    border-color: #4f6df5;
    box-shadow: 0 0 0 3px rgba(79, 109, 245, 0.12);
    outline: none;
}

.profile-upload-box {
    width: 120px;
    height: 110px;
    border: 1.5px dashed #a9c1f5;
    border-radius: 12px;
    background: #f3f6ff;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    overflow: hidden;
    margin-bottom: 10px;
}

.profile-upload-box svg {
    width: 32px;
    height: 32px;
    color: #6a8bf2;
}

.profile-preview-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.choose-file-btn {
    background: none;
    border: none;
    color: #4f6df5;
    font-size: 0.85rem;
    text-decoration: underline;
    padding: 0;
    cursor: pointer;
    display: block;
}

.btn-save {
    background-color: #4f6df5;
    border: none;
    color: #fff;
    border-radius: 8px;
}

.btn-save:hover {
    background-color: #3d5ae0;
    color: #fff;
}

.btn-back {
    background-color: #f1f3f6;
    border: none;
    color: #555;
    border-radius: 8px;
}

.btn-back:hover {
    background-color: #e4e7ec;
}
</style>

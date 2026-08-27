<script setup>
import AppBreadcrumbs from '../../common/AppBreadcrumbs.vue';

import { useAuthStore } from "../../../stores/authStore";
import axiosInstance from "../../../axiosInstance";
import { ref, onMounted, reactive, watch} from "vue";
import ImageCropUpload from '../../common/ImageCropUpload.vue';
import AppButton from '../../common/AppButton.vue';
import { useRouter } from 'vue-router';


const props = defineProps(['id']);
const authStore = useAuthStore();
const previewImage = ref('');
const profilePicture = ref(null);
const profileImageFile = ref(null);
const existingImageUrl = ref('');


const router = useRouter();
function goBack() {
    router.push({ name: 'UserList' });
}


//**** create function start
const form = reactive({
    useEmail: authStore.email, name: '', email: "", staff_id: '',
    profile_picture: '',
    phone: '', dept_name: '', desg: '', user_id: ''
});

const errors = reactive({
    name: null,
    email: null,
    staff_id: null,
    phone: null,
    dept_name: null,
});

watch(() => form.name,      v => { if (v) errors.name = null; });
watch(() => form.email,     v => { if (v) errors.email = null; });
watch(() => form.staff_id,  v => { if (v) errors.staff_id = null; });
watch(() => form.phone,     v => { if (v) errors.phone = null; });
watch(() => form.dept_name, v => { if (v) errors.dept_name = null; });

// Fields come back from the API as null or as numeric IDs, so never call .trim() on them directly
function text(value) {
    return String(value ?? '').trim();
}

function validate() {
    Object.keys(errors).forEach(k => errors[k] = null);
    const validEmailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const validPhoneRegex = /^(?:\+?[1-9]\d{7,14}|0\d{7,14})$/;

    if (!text(form.name))
        errors.name = 'Please enter a name.';
    if (!text(form.email) || !validEmailRegex.test(text(form.email)))
        errors.email = 'Please enter a valid email address.';
    if (!text(form.staff_id))
        errors.staff_id = 'Please enter a staff ID.';
    if (!text(form.phone) || !validPhoneRegex.test(text(form.phone)))
        errors.phone = 'Please enter a valid phone number.';
    if (!text(form.dept_name))
        errors.dept_name = 'Please enter a department name.';

    return !Object.values(errors).some(Boolean);
}


async function update(props) {
    if (!validate()) {
        return;
    }

    form.user_id = props.id;

    // Only send a picture when a new one was cropped — otherwise the server keeps the existing image
    if (profileImageFile.value instanceof File) {
        form.profile_picture = profileImageFile.value;
    }

    try {
        // const response = await axiosInstance.post("/user-details/update", form);
        const authStore = useAuthStore();
        const accessToken = authStore.decryptWithAES(authStore.token);
        const response = await axios.post('/api/user-details/update', form, {
            headers: {
                'Content-Type': 'multipart/form-data',
                Authorization: "Bearer " + accessToken,
                Accept: "application/json",

            },
        });

        // Backend can answer 200 with types 'e', so never assume success
        if (response.data?.types === 'e') {
            Notification.showToast('e', response.data.message);
            return;
        }

        Notification.showToast('s', response.data.message);
        router.push({ name: 'UserList' });

    } catch (error) {
        //ErrorCatch.CatchError(error);
        Notification.showToast('e', error.response?.data?.message || 'An error occurred while updating user data.');
    }
}

getUserData(props);
//console.log('Props received in edit.vue:', props);
async function getUserData(props) {
    try {
        const response = await axiosInstance.post('editUser', { 'id': props });
        //console.log(response.data[0]);
        //previewImage.value =  response.data[0].img_path;

        const userData = Array.isArray(response.data) ? response.data[0] : (response.data.data || response.data);
        if (!userData) {
            throw new Error('User data not found in the response.');
        }

        //console.log('Fetched user data:', userData);

        // Nulls from the API would break the v-model inputs and the validators below
        form.name = userData.name ?? '';
        form.staff_id = userData.emp_id ?? '';
        form.email = userData.email ?? '';
        form.phone = userData.phone ?? '';
        form.desg = userData.desg_name ?? '';
        form.dept_name = userData.dept_name ?? '';

        // Existing image shows as the cropper's initial preview until a new one is cropped
        existingImageUrl.value = userData.img_path || '';

    } catch (error) {
        if (window.Notification?.showToast) {
            window.Notification.showToast('e', error.response?.data?.message || 'An error occurred while fetching user data.');
        }
    }
}



async function save() {

    try {

        const authStore = useAuthStore();
        const accessToken = authStore.decryptWithAES(authStore.token);
        const response = await axios.post('/api/external-user/save', form, {
            headers: {
                'Content-Type': 'multipart/form-data',
                Authorization: "Bearer " + accessToken,
                Accept: "application/json",

            },
        });

        document.getElementById("addUserform").reset();

        previewImage.value = '';

        Notification.showToast('s', response.data.message);


    } catch (error) {
        ErrorCatch.CatchError(error);

    }
}

// const handleFileChange = (event) => {
//     form.profile_picture = event.target.files[0];
//     const reader = new FileReader();
//     reader.readAsDataURL(form.profile_picture);

//     reader.onload = (e) => {
//         previewImage.value = e.target.result;
//     };
// }

// triggers the hidden file input when the upload box or "Choose File" link is clicked
// function triggerFileInput() {
//     profilePicture.value.click();
// }
</script>

<template>
        <AppBreadcrumbs
        title="User Managemnet"
        :back-to="{ name: 'UserList' }"
        :breadcrumbs="[
            { label: 'Dashboard', to: { name: 'Home' } },
            { label: 'User List', to: { name: 'UserList' } },
            { label: 'Update User' },
        ]"
    />

    <div class="card user-create-card">
        <div class="card-header bg-white">
            <h5 class="m-0 p-0 card-title-accent">&nbsp; Update User</h5>
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
                            :display-url="existingImageUrl"
                            shape="circle"
                            size-class="profile-image-preview"
                            :max-file-size-mb="2"
                            :removable="false"
                            crop-modal-title="Crop Profile Image"
                        >
                            {{ profileImageFile ? 'New image ready — click Update to save' : 'Click to change · JPEG, PNG, GIF or WebP · max 2 MB' }}
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
                                    placeholder="Enter Name" v-model="form.name" :class="{ 'is-invalid': errors.name }">
                                <div v-if="errors.name" class="invalid-feedback d-block">
                                    {{ errors.name }}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="staff_id" class="form-label">Staff ID
                                    <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control custom-input" id="staff_id"
                                    placeholder="Enter Staff ID" v-model="form.staff_id" :class="{ 'is-invalid': errors.staff_id }">
                                <div v-if="errors.staff_id" class="invalid-feedback d-block">
                                    {{ errors.staff_id }}
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label">Email
                                    <span class="text-danger">*</span>
                                </label>
                                <input type="email" class="form-control custom-input" id="email"
                                    placeholder="Email" v-model="form.email" :class="{ 'is-invalid': errors.email }">
                                <div v-if="errors.email" class="invalid-feedback d-block">
                                    {{ errors.email }}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone
                                    <span class="text-danger">*</span>
                                </label>
                                <input type="phone" class="form-control custom-input" id="phone"
                                    placeholder="Phone" v-model="form.phone" :class="{ 'is-invalid': errors.phone }">
                                <div v-if="errors.phone" class="invalid-feedback d-block">
                                    {{ errors.phone }}
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="deptment_id" class="form-label">Department
                                    <span class="text-danger">*</span>
                                </label>
                                <input type="email" class="form-control custom-input" id="deptment_id"
                                    placeholder="Department Name" v-model="form.dept_name" :class="{ 'is-invalid': errors.dept_name }">
                                <div v-if="errors.dept_name" class="invalid-feedback d-block">
                                    {{ errors.dept_name }}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="desg_id" class="form-label">Designation</label>
                                <input type="text" class="form-control custom-input" id="desg_id"
                                    placeholder="Designation Name" v-model="form.desg">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-white gap-3 d-flex justify-content-end">
                <AppButton variant="return" label="Back"  @click="goBack" />
                <AppButton variant="update" label="Update" @click="update(props)" />
                <!-- <button type="button" @click="update(props)" class="btn btn-save px-4 float-end ms-2 mb-4 mt-2">Update</button>
                <button type="button" class="btn btn-back px-4 float-end mb-4 mt-2">Back</button> -->
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

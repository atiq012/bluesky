<script setup>
import AppBreadcrumbs from '../../common/AppBreadcrumbs.vue';

import { useAuthStore } from "../../../stores/authStore";
import axiosInstance from "../../../axiosInstance";
import { ref, onMounted, reactive } from "vue";
const props = defineProps(['id']);
const authStore = useAuthStore();
const previewImage = ref('');
const profilePicture = ref(null);

//**** create function start
const form = reactive({
    useEmail: authStore.email, name: '', email: "", staff_id: '',
    profile_picture: '',
    phone: '', dept_name: '', desg: '', user_id: ''
});

async function update(props) {

    form.user_id = props.id;

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
        Notification.showToast('s', response.data.message);

    } catch (error) {
        ErrorCatch.CatchError(error);
    }
}

getUserData(props);

async function getUserData(props) {
    try {
        const response = await axiosInstance.post('editUser', { 'id': props });
        previewImage.value =  response.data[0].img_path;

        const name = response.data[0].name;

        form.name = name;
        const emp_id = response.data[0].emp_id;
        form.staff_id = emp_id;

        const email = response.data[0].email;
        form.email = email;

        const phone = response.data[0].phone;
        form.phone = phone;


        const designation_id = response.data[0].designation_id;

        form.desg = designation_id;

        const dept_id = response.data[0].dept_id;

        form.dept_name = dept_id;

    } catch (error) {
        console.log(error);
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

const handleFileChange = (event) => {
    form.profile_picture = event.target.files[0];
    const reader = new FileReader();
    reader.readAsDataURL(form.profile_picture);

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
                    <div class="col-lg-2">
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
                    </div>

                    <!-- Form Fields -->
                    <div class="col-lg-10">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Name</label>
                                <input type="text" class="form-control custom-input" id="name"
                                    placeholder="Enter Name" v-model="form.name">
                            </div>
                            <div class="col-md-6">
                                <label for="staff_id" class="form-label">Staff ID</label>
                                <input type="text" class="form-control custom-input" id="staff_id"
                                    placeholder="Enter Name" v-model="form.staff_id">
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control custom-input" id="email"
                                    placeholder="Email" v-model="form.email">
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="phone" class="form-control custom-input" id="phone"
                                    placeholder="Phone" v-model="form.phone">
                            </div>

                            <div class="col-md-6">
                                <label for="deptment_id" class="form-label">Department</label>
                                <input type="email" class="form-control custom-input" id="deptment_id"
                                    placeholder="Department Name" v-model="form.dept_name">
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

            <div class="card-footer bg-white">
                <button type="button" @click="update(props)" class="btn btn-save px-4 float-end ms-2 mb-4 mt-2">Update</button>
                <button type="button" class="btn btn-back px-4 float-end mb-4 mt-2">Back</button>
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

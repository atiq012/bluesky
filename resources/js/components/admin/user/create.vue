<script setup>
import { useAuthStore } from "../../../stores/authStore";
import axiosInstance from "../../../axiosInstance";

import { ref, onMounted, reactive } from "vue";
const authStore = useAuthStore();
//**** create function start
const form = reactive({
    useEmail: authStore.email, name: '', email: "", staff_id: '',
    profile_picture: '',
    phone: '', dept_name: '', desg: '',
});



async function save() {
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

        document.getElementById("addUserform").reset();
        previewImage.value = '';

        Notification.showToast('s', response.data.message);



    } catch (error) {
        ErrorCatch.CatchError(error);

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
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">User Managemnet</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item">
                        <router-link :to="{ name: 'Home' }">Dashboard</router-link>
                    </li>
                    <li class="breadcrumb-item">
                        <router-link :to="{ name: 'UserList' }">User List</router-link>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Create New User</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="card user-create-card">
        <div class="card-header bg-white">
            <h5 class="m-0 p-0 card-title-accent">&nbsp; Create New User</h5>
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
                                    placeholder="Enter Your Name" v-model="form.name">
                            </div>
                            <div class="col-md-6">
                                <label for="staff_id" class="form-label">Staff ID</label>
                                <input type="text" class="form-control custom-input" id="staff_id"
                                    placeholder="Enter Your Staff ID" v-model="form.staff_id">
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control custom-input" id="email"
                                    placeholder="Enter Your Email" v-model="form.email">
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="number" class="form-control custom-input" id="phone"
                                    placeholder="Enter Your Phone Number" v-model="form.phone">
                            </div>

                            <div class="col-md-6">
                                <label for="deptment_id1" class="form-label">Department</label>
                                <input type="text" class="form-control custom-input" id="deptment_id1"
                                    placeholder="Enter Department Name" v-model="form.deptment_id">
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

            <div class="card-footer bg-white mb-3">
                <button type="button" @click="save()" class="btn btn-save px-4 float-end ms-2 mb-3">Save</button>
                <button type="button" class="btn btn-back px-4 float-end mb-3" @click="$router.go(-1)">Back</button>
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

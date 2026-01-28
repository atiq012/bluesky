<script setup>
import { useAuthStore } from "../../../stores/authStore";
import axiosInstance from "../../../axiosInstance";

import { ref, onMounted, reactive } from "vue";
const authStore = useAuthStore();
//**** create function start
const form = reactive({
    useEmail: authStore.email, name: '', email: "", staff_id: '',
    profile_picture: '',
    phone: '', dept_name: '', desg: '', off_loct: '', report_to: '', role_id: '',
});



getDepartment();

async function getDepartment() {
    try {
        const response = await axiosInstance.get('getAllDept');

        var options = [];
        $.each(response.data, function (key, value) {
            var obj = { id: value.id, text: value.name }
            options.push(obj);

        });

        let select = $("#deptment_id")
        select.select2({
            placeholder: '=Select=',
            theme: 'bootstrap-5',
            width: '100%',
            allowClear: true,
            height: '50',
            data: options,
        });


    } catch (error) {
        // console.log(error);

    }
}

getDesignation();

async function getDesignation() {
    try {
        const response = await axiosInstance.get('getAllDesign');

        var options = [];
        $.each(response.data, function (key, value) {
            var obj = { id: value.id, text: value.name }
            options.push(obj);

        });

        let select = $("#desg_id")
        select.select2({
            placeholder: '=Select=',
            theme: 'bootstrap-5',
            width: '100%',
            allowClear: true,
            height: '50',
            data: options,
        });


    } catch (error) {
        // console.log(error);

    }
}

getOffLoc();

async function getOffLoc() {
    try {
        const response = await axiosInstance.get('getAllOffLoc');

        var options = [];
        $.each(response.data, function (key, value) {
            var obj = { id: value.id, text: value.name }
            options.push(obj);

        });

        let select = $("#off_loc")
        select.select2({
            placeholder: '=Select=',
            theme: 'bootstrap-5',
            width: '100%',
            allowClear: true,
            height: '50',
            data: options,
        });


    } catch (error) {
        // console.log(error);

    }
}
getRoles();

async function getRoles() {
    try {
        const response = await axiosInstance.get('getAllRoles');

        var options = [];
        $.each(response.data, function (key, value) {
            var obj = { id: value.id, text: value.name }
            options.push(obj);

        });

        let select = $("#role_id")
        select.select2({
            placeholder: '=Select=',
            theme: 'bootstrap-5',
            width: '100%',
            allowClear: true,
            height: '50',
            data: options,
        });


    } catch (error) {
        // console.log(error);

    }
}

getAllUsers();

async function getAllUsers() {
    try {
        const response = await axiosInstance.get('getAllUsers');

        var options = [];
        $.each(response.data, function (key, value) {
            var obj = { id: value.id, text: value.name }
            options.push(obj);
        });

        let select = $("#report_to")
        select.select2({
            placeholder: '=Select=',
            theme: 'bootstrap-5',
            width: '100%',
            allowClear: true,
            height: '50',
            data: options,
        });


    } catch (error) {
        // console.log(error);

    }
}


onMounted(() => {
    $('.dept_name').on("change", function () {
        form.dept_name = $(this).val();
    });

    $('.desg').on("change", function () {
        form.desg = $(this).val();
    });
    $('.off_loc').on("change", function () {
        form.off_loct = $(this).val();
    });
    $('.report_to').on("change", function () {
        form.report_to = $(this).val();
    });
    $('.role').on("change", function () {
        form.role_id = $(this).val();
    });
});

async function save() {
    // console.log(form);
    try {

        // const response = await axiosInstance.post("/external-user/save", form);
        const authStore = useAuthStore();
        const accessToken = authStore.decryptWithAES(authStore.token);
        const response = await axios.post('/api/internal-user/save', form, {
            headers: {
                'Content-Type': 'multipart/form-data',
                Authorization: "Bearer " + accessToken,
                Accept: "application/json",

            },
        });

        document.getElementById("addUserform").reset();

        $('.dept_name option:first').prop('selected', true).trigger(
            "change"); // reset dropdown value
        $('.desg option:first').prop('selected', true).trigger(
            "change"); // reset dropdown value
        $('.off_loc option:first').prop('selected', true).trigger(
            "change"); // reset dropdown value
        $('.report_to option:first').prop('selected', true).trigger(
            "change"); // reset dropdown value
        $('.role option:first').prop('selected', true).trigger(
            "change"); // reset dropdown value
        previewImage.value = '';

        Notification.showToast('s', response.data.message);



    } catch (error) {
        ErrorCatch.CatchError(error);

    }
}
const previewImage = ref('');


const handleFileChange = (event) => {
    form.profile_picture = event.target.files[0];
    const reader = new FileReader();
    reader.readAsDataURL(form.profile_picture);
    // console.log(reader.readAsDataURL(form.profile_picture));

    reader.onload = (e) => {
        previewImage.value = e.target.result;
    };
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
    <div class="card">
        <div class="card-header">
            <h5 class="m-0 p-0" style="border-left:5px solid #7239ea;"> &nbsp; Create New User</h5>
        </div>
        <form id="addUserform">

            <div class="card-body">
                <div class="row">
                    <div class="col-lg-2">
                        <p class="text-center">Profile Image</p>

                        <div class="mb-3 pt-0 text-center mx-auto">
                            <img v-if="previewImage" :src="previewImage" height="150" width="150"
                                class="border border-1 rounded rounded-2" alt="Profile Picture">
                            <img v-else src="../../../../../public/theme/appimages/profile_default_img.svg" height="150" width="150"
                                class="border border-1 rounded rounded-2" alt="Profile Picture">
                        </div>

                        <input type="file" id="profile-picture" ref="profilePicture" class="w-1/2"
                            @change="handleFileChange" accept="image/*">
                    </div>
                    <div class="col-lg-8" style="border-left: 2px solid #dfeffd;">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="row">
                                    <div class="col-md-12">
                                        <label for="input1" class="form-label">Name</label>
                                        <input type="text" class="form-control form-control-sm" id="name"
                                            placeholder="Enter Name" v-model="form.name">
                                    </div>
                                    <div class="col-md-12 mt-2">
                                        <label for="input4" class="form-label">Email</label><input type="email"
                                            class="form-control form-control-sm" id="input4" placeholder="Email"
                                            v-model="form.email">
                                    </div>
                                    <div class="col-md-12 mt-2">
                                        <label for="input7" class="form-label">Department</label>
                                        <select id="deptment_id" class="form-control form-control-sm dept_name">
                                            <option>Choose...</option>

                                        </select>
                                    </div>
                                    <div class="col-md-12 mt-2">
                                        <label for="input11" class="form-label">Office Location</label>
                                        <select id="off_loc" class="form-control form-control-sm off_loc">
                                            <option>Choose...</option>

                                        </select>
                                    </div>

                                    <div class="col-md-12 mt-2">
                                        <label for="input11" class="form-label">Role</label>
                                        <select id="role_id" class="form-control form-control-sm role">
                                            <option>Choose...</option>
                                        </select>
                                    </div>
                                </div>


                            </div>
                            <div class="col-md-6">
                                <div class="row">
                                    <div class="col-md-12">
                                        <label for="input1" class="form-label">Staff ID</label>
                                        <input type="text" class="form-control form-control-sm" id="input1"
                                            placeholder="Enter Name" v-model="form.staff_id">
                                    </div>
                                    <div class="col-md-12 mt-2">
                                        <label for="input4" class="form-label">Phone</label><input type="number"
                                            class="form-control form-control-sm" id="input4" placeholder="Phone"
                                            v-model="form.phone">
                                    </div>

                                    <div class="col-md-12 mt-2">
                                        <label for="input4" class="form-label">Designation</label>
                                        <select id="desg_id" class="form-control form-control-sm desg">
                                            <option>Choose...</option>

                                        </select>
                                    </div>

                                    <div class="col-md-12 mt-2">
                                        <label for="input4" class="form-label">Report To</label>
                                        <select id="report_to" class="form-control form-control-sm report_to">
                                            <option>Choose...</option>

                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button type="button" @click="save()"
                    class="m-2 btn btn-sm btn-info px-4 ms-2 float-end text-white">Save</button>
                <button class="m-2 btn btn-sm btn-danger px-4 ms-2  float-end" @click="$router.go(-1)">Back </button>
            </div>
        </form>
    </div>
</template>

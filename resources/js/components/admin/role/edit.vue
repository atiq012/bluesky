<script setup>
import AppBreadcrumbs from '../../common/AppBreadcrumbs.vue';

import { useAuthStore } from "../../../stores/authStore";
import axiosInstance from "../../../axiosInstance"
import { ref, onMounted, reactive } from "vue";

const authStore = useAuthStore();
const props = defineProps(['id']);

const form = ref({ roleName: "", status_val: '', checkedNames: [], useEmail: authStore.email, error: "", role_id: props.id });


getRolesData(props);

async function getRolesData(props) {
    try {
        const response = await axiosInstance.post('editRole', { 'id': props });
        const name = response.data[0].name;
        $("#role_name").val(name);

        const status = response.data[0].status;
        $('#status').val(status);
        $('#status').trigger('change');

        const perm_response = await axiosInstance.post('getPermissionList', { 'id': props });

        var valueArray = $('.form-check-input').map(function () {
            return this.value;
        }).get();

        $.each(perm_response.data, function (key, value) {

            if (valueArray[key] == value.feature_name) {
                $('input[id=' + value.feature_name + ']').prop('checked', true);
            }
        });

    } catch (error) {
        console.log(error);
    }
}
onMounted(() => {
    $(".form-check-input").on('change', function () {

        if ($(this).is(":checked") == true) {
            getRolesData(props);
        }
    });

    $("#status").on('change', function () {
        console.log($(this).val());

        form.status_val = $(this).val();

    });
});

async function dataSave() {
    // console.log(form.value);

    try {

        const response = await axiosInstance.post("/role/update", JSON.stringify(form.value));
        document.getElementById("editRoleForm").reset();
        if (response.data.message) {

            Notification.showToast('s', response.data.message);
        } else {
            Notification.showToast('E', 'This action is not allowed.');
        }


    } catch (error) {
        ErrorCatch.CatchError(error);
    }
    // updateRole(form);
}

// const urlss = document.head.querySelector('meta[name="api-base-url"]').content;
// const updateRole = async (formData) => {

//     try {
//         const url = urlss + "/api/role/update";

//         const config = {
//             method: 'post',
//             url: url,
//             headers: {
//                 'Content-Type': 'application/json'
//                 // headers: { Authorization: 'Bearer ' + authStore.decryptWithAES(authStore.token), "Accept": "application/json", }
//             },
//             data: JSON.stringify(formData.value)
//         }

//         const res = await axios(config);
//         if (res.data.types == 's') {
//             document.getElementById("editRoleForm").reset();
//             Notification.showToast(res.data.types, res.data.message);

//         } else if (res.data.types == "e") {
//             Notification.showToast(res.data.types, res.data.message);

//         }


//     } catch (err) {
//         Notification.showToast("e", err);

//     }
// }
</script>
<template>
        <AppBreadcrumbs
        title="Settings"
        :back-to="{ name: 'roleList' }"
        :breadcrumbs="[
            { label: 'Dashboard', to: { name: 'Home' } },
            { label: 'Role-permission List', to: { name: 'roleList' } },
            { label: 'Update Role' },
        ]"
    />
    <form @submit.prevent="dataSave" id="editRoleForm">

        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Role Name</label>
                        <input type="text" class="form-control form-control-sm" placeholder="Enter Role Name"
                            name="role_name" v-model.trim="form.roleName" id="role_name">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control form-control-sm" id="status">
                            <option value="1" selected>Active</option>
                            <option value="2">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="m-0 p-0" style="border-left:5px solid #7239ea;"> &nbsp; Assign Permission</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <div style="background-color: #746a892b;" class="card-header m-0 pl-2 pb-0">
                                <h6 class="text-blue">Role Permission</h6>
                            </div>
                            <div class="card-body">

                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="role_create" value="role_create"
                                        v-model="form.checkedNames">
                                    <label class="form-check-label" for="create">Create</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="role_edit" value="role_edit"
                                        v-model="form.checkedNames">
                                    <label class="form-check-label" for="Edit">Edit</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="role_delete" value="role_delete"
                                        v-model="form.checkedNames">
                                    <label class="form-check-label" for="Delete">Delete</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="role_assign_permission"
                                        id="role_assign_permission" v-model="form.checkedNames">
                                    <label class="form-check-label" for="Assign Permission">Assign Permission</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card">
                            <div style="background-color: #746a892b;" class="card-header m-0 pl-2 pb-0">
                                <h6 class="text-blue">B2B Agent</h6>
                            </div>
                            <div class="card-body">
                                <div class="form-check">
                                    <input class="form-check-input" v-model="form.checkedNames" type="checkbox"
                                        id="b2b_create" value="b2b_create">
                                    <label class="form-check-label" for="flexCheckChecked">Create</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" v-model="form.checkedNames" type="checkbox"
                                        id="b2b_edit" value="b2b_edit">
                                    <label class="form-check-label" for="flexCheckChecked">Edit</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" v-model="form.checkedNames" type="checkbox"
                                        id="b2b_delete" value="b2b_delete">
                                    <label class="form-check-label" for="flexCheckChecked">Delete</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" v-model="form.checkedNames" type="checkbox"
                                        id="b2b_print" value="b2b_print">
                                    <label class="form-check-label" for="flexCheckChecked">Print</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card">
                            <div style="background-color: #746a892b;" class="card-header m-0 pl-2 pb-0">
                                <h6 class="text-blue">User Management</h6>
                            </div>
                            <div class="card-body">
                                <div class="form-check">
                                    <input class="form-check-input" v-model="form.checkedNames" type="checkbox"
                                        value="user_create">
                                    <label class="form-check-label" for="flexCheckChecked">Create</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" v-model="form.checkedNames" type="checkbox"
                                        value="user_edit">
                                    <label class="form-check-label" for="flexCheckChecked">Edit</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" v-model="form.checkedNames" type="checkbox"
                                        value="user_delete">
                                    <label class="form-check-label" for="flexCheckChecked">Delete</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" v-model="form.checkedNames" type="checkbox"
                                        value="user_print">
                                    <label class="form-check-label" for="flexCheckChecked">Print</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-sm btn-success">
                    Update
                </button>
            </div>
        </div>
    </form>



</template>

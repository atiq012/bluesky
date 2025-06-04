<script setup>

import { useAuthStore } from "../../../stores/authStore";
import axiosInstance from "../../../axiosInstance"
import { ref, onMounted, reactive } from "vue";
import { data } from "jquery";

const authStore = useAuthStore();
const form = ref({ roleName: "", checkedNames: [], useEmail: authStore.email, error: "" });
async function dataSave() {

    try {

        const response = await axiosInstance.post("/role/save", JSON.stringify(form.value));
        document.getElementById("AddRoleForm").reset();
        if(response.data.message){

            Notification.showToast('s', response.data.message);
        }else{
            Notification.showToast('E', 'This action is not allowed.');
        }


    } catch (error) {
        ErrorCatch.CatchError(error);
    }
}

</script>

<template>
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Settings</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item">
                        <router-link :to="{ name: 'Home' }">Dashboard</router-link>

                    </li>
                    <li class="breadcrumb-item">
                        <router-link :to="{ name: 'roleList' }">Role-permission List</router-link>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Create New Role</li>
                </ol>
            </nav>
        </div>
    </div>

    <form @submit.prevent="dataSave" id="AddRoleForm">
        <div class="card">
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Role Name</label>
                    <input type="text" class="form-control form-control-sm" placeholder="Enter Role Name"
                        v-model.trim="form.roleName">
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="m-0 p-0" style="border-left:5px solid #7239ea;"> &nbsp; Assign Permission </h5>
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
                                    <input class="form-check-input" type="checkbox" id="create" value="role_create"
                                        v-model="form.checkedNames">
                                    <label class="form-check-label" for="create">Create</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="Edit" value="role_edit"
                                        v-model="form.checkedNames">
                                    <label class="form-check-label" for="Edit">Edit</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="Delete" value="role_delete"
                                        v-model="form.checkedNames">
                                    <label class="form-check-label" for="Delete">Delete</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="Assign Permission"
                                        value="role_assign_permission" v-model="form.checkedNames">
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
                                    <input class="form-check-input" type="checkbox" value="">
                                    <label class="form-check-label" for="flexCheckChecked">Create</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="">
                                    <label class="form-check-label" for="flexCheckChecked">Edit</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="">
                                    <label class="form-check-label" for="flexCheckChecked">Delete</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="">
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
                                    <input class="form-check-input" type="checkbox" value="">
                                    <label class="form-check-label" for="flexCheckChecked">Create</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="">
                                    <label class="form-check-label" for="flexCheckChecked">Edit</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="">
                                    <label class="form-check-label" for="flexCheckChecked">Delete</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="">
                                    <label class="form-check-label" for="flexCheckChecked">Print</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-sm btn-success">
                    Add
                </button>
            </div>
        </div>


    </form>

</template>

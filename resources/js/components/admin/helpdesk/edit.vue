<script setup>
import { useAuthStore } from "../../../stores/authStore";
import axiosInstance from "../../../axiosInstance"
import { ref, onMounted, reactive } from "vue";
const props = defineProps(['ids'])

const authStore = useAuthStore();
//**** create function start
const form = reactive({
    useEmail: authStore.email, cate_id: "", requester: "", priority: "",
    subcate_id: "", subject: "", description: "", id: "", assign_to: "", request_type: "", mode: "", level: "", assets: "", file_path: ""
});

const handleFileChange = (event) => {
    form.file_path = event.target.files[0];
    // console.log(form.file_path);

    const reader = new FileReader();
    reader.readAsDataURL(form.file_path);
    // reader.onload = (e) => {
    //     previewImage.value = e.target.result;
    // };
}
async function save() {

    try {
        const select2Value = $('#cate_id').val();
        const select2ValueSubCate = $('#subcate_id').val();
        const requesterValue = $('#requester_id').val();
        const priorityValue = $('#priority').val();
        const assignToValue = $('#assign_to').val();

        const id = props.ids;
        form.id = id;
        if (requesterValue) {
            form.requester = requesterValue;
        }

        if (priorityValue) {
            form.priority = priorityValue;
        }
        // Update the form object with the latest Select2 value
        if (select2Value) {
            form.cate_id = select2Value;
        }
        if (select2ValueSubCate) {
            form.subcate_id = select2ValueSubCate;
        }
        if (assignToValue) {
            form.assign_to = assignToValue;
        }
        // const response = await axiosInstance.post("/request/update", form);
        const authStore = useAuthStore();
        const accessToken = authStore.decryptWithAES(authStore.token);
        const response = await axios.post('/api/request/update', form, {
            headers: {
                'Content-Type': 'multipart/form-data',
                Authorization: "Bearer " + accessToken,
                Accept: "application/json",

            },
        });
        // Reset the form properly
        form.requester = "";
        form.cate_id = "";
        form.priority = "";
        form.description = "";
        form.subcate_id = "";
        form.subject = "";
        // Reset Select2 properly
        $('#cate_id').val('').trigger('change');

        // Reset the select element to show only default option
        $('#cate_id').empty().append('<option selected value="">Select Category</option>');

        // Reset radio buttons
        $('input[name="types"][value="category"]').prop('checked', true);
        $('#cate_id option:first').prop('selected', true).trigger("change");
        $('#requester_id option:first').prop('selected', true).trigger("change");
        Notification.showToast('s', response.data.message);
    } catch (error) {
        ErrorCatch.CatchError(error);
    }
}

onMounted(() => {
    $("#requester_id").select2({
        placeholder: '=Select=',
        theme: 'bootstrap-5',
        width: '90%',
        allowClear: true,
    }).on('change', function () {
        form.requester = $(this).val(); // Sync with Vue reactive state
    });
    $("#assign_to").select2({
        placeholder: '=Select=',
        theme: 'bootstrap-5',
        width: '90%',
        allowClear: true,
    }).on('change', function () {
        form.assign_to = $(this).val(); // Sync with Vue reactive state
    });

    $("#mode").select2({
        placeholder: '=Select=',
        theme: 'bootstrap-5',
        width: '90%',
        allowClear: true,
    }).on('change', function () {
        form.mode = $(this).val(); // Sync with Vue reactive state
    });
    $("#request_type").select2({
        placeholder: '=Select=',
        theme: 'bootstrap-5',
        width: '90%',
        allowClear: true,
    }).on('change', function () {
        form.request_type = $(this).val(); // Sync with Vue reactive state
    });

    $("#priority").select2({
        placeholder: '=Select=',
        theme: 'bootstrap-5',
        width: '90%',
        allowClear: true,
    }).on('change', function () {
        form.priority = $(this).val(); // Sync with Vue reactive state
    });

    $("#cate_id").select2({
        placeholder: '=Select=',
        theme: 'bootstrap-5',
        width: '90%',
        allowClear: true,
    }).on('change', function () {
        form.cate_id = $(this).val(); // Sync with Vue reactive state
        if (form.cate_id) {
            getSubCate(form.cate_id);
        } else {
            $("#subcate_id").empty().append('<option selected value="">Select Sub Category</option>').trigger('change');
            form.subcate_id = "";
        }
    });

    $("#subcate_id").select2({
        placeholder: '=Select=',
        theme: 'bootstrap-5',
        width: '90%',
        allowClear: true,
    }).on('change', function () {
        form.subcate_id = $(this).val(); // Sync with Vue reactive state
    });
    $("#level").select2({
        placeholder: '=Select=',
        theme: 'bootstrap-5',
        width: '90%',
        allowClear: true,
    }).on('change', function () {
        form.level = $(this).val(); // Sync with Vue reactive state
    });
});

getCate();

async function getCate() {
    try {

        const response = await axiosInstance.get('categories');

        var options = [];
        $.each(response.data, function (key, value) {
            var obj = { id: value.id, text: value.name, bn: value.bn_name, clr: MF.getRandomColor() }
            options.push(obj);
        });

        $("#cate_id").select2({
            placeholder: '=Select=',
            theme: 'bootstrap-5',
            width: '90%',
            allowClear: true,
            height: '50',
            data: options,
            tags: true,
            templateResult: function (state) {
                if (!state.id) {
                    return state.text;
                }
                var $state = $('<div class="clearfix"><div class="float-start"> ' + state.text + '</div></div>');

                return $state;
            }
        });
        $('#cate_id').prepend('<option selected=""></option>');

    } catch (error) {
        // console.log(error);
    }
}

// getSubCate();

async function getSubCate(cate_id) {
    try {

        const response = await axiosInstance.get('subcategories', {
            params: {
                cate_id: cate_id
            }
        });

        var options = [];
        $.each(response.data, function (key, value) {
            var obj = { id: value.id, text: value.name, bn: value.bn_name, clr: MF.getRandomColor() }
            options.push(obj);
        });

        $("#subcate_id").select2({
            placeholder: '=Select=',
            theme: 'bootstrap-5',
            width: '90%',
            allowClear: true,
            height: '50',
            data: options,
            tags: true,
            templateResult: function (state) {
                if (!state.id) {
                    return state.text;
                }
                var $state = $('<div class="clearfix"><div class="float-start"> ' + state.text + '</div></div>');

                return $state;
            }
        });
        $('#subcate_id').prepend('<option selected=""></option>');

    } catch (error) {
        // console.log(error);
    }
}

getRequester();

async function getRequester() {
    try {

        const response = await axiosInstance.get('getAllUsers');  //getInternalUsers

        var options = [];
        $.each(response.data, function (key, value) {
            var obj = { id: value.id, text: value.name, bn: value.bn_name, clr: MF.getRandomColor() }
            options.push(obj);
        });

        $("#requester_id").select2({
            placeholder: '=Select=',
            theme: 'bootstrap-5',
            width: '90%',
            allowClear: true,
            height: '50',
            data: options,
            tags: true,
            templateResult: function (state) {
                if (!state.id) {
                    return state.text;
                }
                var $state = $('<div class="clearfix"><div class="float-start"> ' + state.text + '</div></div>');

                return $state;
            }
        });
        $('#requester_id').prepend('<option selected=""></option>');

    } catch (error) {
        // console.log(error);
    }
}

getInternalUsers();

async function getInternalUsers() {
    try {
        const response = await axiosInstance.get("getInternalUsers");

        let users = response.data.data;
        let select = document.getElementById('assign_to');

        users.forEach(function (user) {

            let option = document.createElement('option');
            option.value = user.idd;
            option.text = user.name;
            select.appendChild(option);
        });

    } catch (error) {
        console.log(error);
    }
}


getEditData(props);

async function getEditData(props) {
    // console.log(props);

    try {
        authStore.GlobalLoading = true;
        // Loading.value = false;
        await getRequester();
        const response = await axiosInstance.post('editRequest', { 'id': props.ids });


        const subject = response.data.subject;
        form.subject = subject;
        const description = response.data.description;
        form.description = description;
        form.assets = response.data.asset;
        const priority = response.data.priority;
        const request_type = response.data.request_type;
        form.request_type = request_type;
        $('#request_type').val(request_type);
        $('#request_type').trigger('change');

        const mode = response.data.mode;
        form.mode = mode;
        $('#mode').val(mode);
        $('#mode').trigger('change');

        const level = response.data.level;
        form.level = level;
        const assign_to = response.data.assignee_id;
        form.assign_to = assign_to;
        $('#assign_to').val(assign_to);
        $('#assign_to').trigger('change');
        $('#level').val(level);
        $('#level').trigger('change');
        $('#priority').val(priority);
        $('#priority').trigger('change');

        const cate_id = response.data.category_id;
        $('#cate_id').val(cate_id);
        $('#cate_id').trigger('change');

        const subcate_id = response.data.subcategory_id;


        await getSubCate(cate_id);
        $('#subcate_id').val(subcate_id);
        $('#subcate_id').trigger('change');


        const requester_id = response.data.requester_id;
        $('#requester_id').val(requester_id);
        $('#requester_id').trigger('change');

        authStore.GlobalLoading = false;
        // Loading.value = true;
    } catch (error) {
        authStore.GlobalLoading = false;
        // Loading.value = true;
        console.log(error);
    }
}

</script>
<template>
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Setting</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item">
                        <router-link :to="{ name: 'Home' }">Dashboard</router-link>
                    </li>
                    <li class="breadcrumb-item">
                        <router-link :to="{ name: 'helpDesk' }">Help Desk</router-link>
                    </li>
                    <li class="breadcrumb-item">
                        <router-link :to="{ name: 'helpDesk' }">Support Request</router-link>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Edit Support Request</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="m-0 p-0" style="border-left:5px solid #7239ea;"> &nbsp; Edit</h5>
        </div>

        <form id="addCateform">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <label for="input1" class="form-label"><b>Requester</b></label>
                        <select v-model="form.requester" id="requester_id" :disabled="true"
                            class="form-select form-select-sm requester_name" aria-label="Default select example">
                            <option selected value="">Select Requester</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="input1" class="form-label"><b>Priority</b></label>
                        <select v-model="form.priority" id="priority" class="form-select form-select-sm priority"
                            aria-label="Default select example">
                            <option selected value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                </div>

                <div class="row mt-2">
                    <div class="col-md-6">
                        <label for="input1" class="form-label"><b>Assets</b></label>
                        <input type="text" v-model="form.assets" class="form-control form-control-sm" id="assets"
                            name="assets" placeholder="Enter Assets">
                    </div>

                    <div class="col-md-6">
                        <label for="input1" class="form-label"><b>Request Type</b></label>
                        <select v-model="form.request_type" id="request_type" :disabled="true"
                            class="form-select form-select-sm request_type" aria-label="Default select example">
                            <option selected value="Request For Solution">Request For Solution</option>
                            <option value="Request For Information">Request For Information</option>
                            <option value="Incident">Incident</option>
                        </select>
                    </div>
                </div>

                <div class="row mt-2">
                    <div class="col-md-6">
                        <label for="input1" class="form-label"><b>Mode</b></label>
                        <select v-model="form.mode" :disabled="true" id="mode" class="form-select form-select-sm mode"
                            aria-label="Default select example">
                            <option selected value="email">email</option>
                            <option value="phone">phone</option>
                            <option value="chat">chat</option>
                            <option value="web form">web form</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="input1" class="form-label"><b>Level</b></label>
                        <select v-model="form.level" id="level" class="form-select form-select-sm level"
                            aria-label="Default select example">
                            <option selected value="Tier 1">Tier 1</option>
                            <option value="Tier 2">Tier 2</option>
                            <option value="Tier 3">Tier 3</option>
                            <option value="Tier 4">Tier 4</option>
                        </select>
                    </div>
                </div>

                <div class="row mt-2">
                    <div class="col-md-6">
                        <label for="input1" class="form-label"><b>Category</b></label>
                        <select v-model="form.cate_id" :disabled="true" id="cate_id"
                            class="form-select form-select-sm parent_name" aria-label="Default select example">
                            <option selected value="">Select Category</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="input1" class="form-label"><b>Sub Category</b></label>
                        <select v-model="form.subcate_id" id="subcate_id"
                            class="form-select form-select-sm subcategory_name" aria-label="Default select example">
                            <option selected value="">Select Sub Category</option>
                        </select>
                    </div>
                </div>

                <div class="row mt-2">
                    <div class="col-md-6">
                        <label for="input1" class="form-label"><b>Assign</b></label>
                        <select v-model="form.assign_to" id="assign_to" class="form-select form-select-sm assign_to"
                            aria-label="Default select example">
                            <option selected value="">Select </option>
                        </select>
                    </div>
                </div>

                <div class="row mt-2">
                    <div class="col-md-12">
                        <label for="input1" class="form-label"><b>Subject</b></label>
                        <input type="text" v-model="form.subject" class="form-control form-control-sm" id="subject"
                            name="subject" placeholder="Enter Subject">
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="mt-1 col-md-12">
                        <label for="input1" class="form-label"><b>Description</b></label>
                        <textarea v-model="form.description" class="form-control form-control-sm" id="description"
                            name="description" placeholder="Enter Description"></textarea>
                    </div>
                </div>

                <div class="row mt-2">
                    <div class="col-md-6">
                        <label for="input1" class="form-label"><b>File Upload</b></label>
                        <input type="file" class="form-control" id="profile-picture" ref="profilePicture"
                            @change="handleFileChange">
                    </div>
                </div>
            </div>
            <div class="card- bg-white border-0 pt-0">
                <button type="button" @click="save()"
                    class="m-2 btn btn-sm btn-info px-4 ms-2 float-end text-white">Save</button>
                <button class="m-2 btn btn-sm btn-danger px-4 ms-2  float-end">Back</button>
            </div>
        </form>
    </div>
</template>

<style scoped>
input[type="text"].form-control.form-control-sm {
    width: 90%;
}

input[type="file"].form-control {
    width: 90%;
}

textarea.form-control.form-control-sm {
    width: 90%;
}
</style>

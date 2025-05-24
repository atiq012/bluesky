<script setup>
import { useRouter } from 'vue-router';
const router = useRouter();
import { useAuthStore } from '../../../stores/authStore';
import SimpleBar from "simplebar-vue";

const authStore = useAuthStore();
import axiosInstance from "../../../axiosInstance";
import { ref, reactive, onMounted, render } from "vue";
import moment from "moment";

const form = reactive({ pax_type: "", title_val: "", first_name: "", last_name: "", dob: "", gender: "", email: "", phone: "", passport_no: "", p_expiry_date: "", nationality: "", passport_picture: "", useEmail: authStore.email });

const previewImage = ref('');
const showTravelerList = ref(false);
const filteredTraveler = ref([]);

onMounted(() => {

    $("#title").on('change', function () {
        form.title_val = $(this).val();
    });
    $("#gender").on('change', function () {
        form.gender = $(this).val();
    });
    $("#nationality").on('change', function () {
        form.nationality = $(this).val();
    });

    $("#traveler_id").select2({
        placeholder: '=Select=',
        theme: 'bootstrap-5',
        width: '100%',
        allowClear: true,
        height: '50',
    });
});

function paxTypeChange(type) {
    form.pax_type = type;
}

async function getTravellertrData(id) {
    try {
        const response = await axiosInstance.post('viewTraveler', { 'id': id });

        form.title_val = response.data.title;
        $('#title').val(response.data.title);
        form.first_name = response.data.first_name;
        form.last_name = response.data.last_name;
        form.dob = moment(response.data.dob).format('YYYY-MM-DD');
        form.gender = response.data.gender;
        $('#gender').val(response.data.gender);
        form.email = response.data.email;
        form.phone = response.data.phone;
        form.passport_no = response.data.passport_number;
        form.p_expiry_date = moment(response.data.expiry_date).format('YYYY-MM-DD');
        form.nationality = response.data.nationality;
        $('#nationality').val(response.data.nationality);

        form.passport_picture = response.data.passport_path;
        previewImage.value = response.data.passport_path;

        if (response.data.pax_type == 1) {
            var pax_type = 'Adult';
            $('#adult').prop('checked', true);
            form.pax_type = 1;
        } else if (response.data.pax_type == 2) {
            var pax_type = 'Child';
            $('#child').prop('checked', true);
            form.pax_type = 2;
        } else if (response.data.pax_type == 3) {
            var pax_type = 'Infant';
            $('#infant').prop('checked', true);
            form.pax_type = 3;
        }
    } catch (error) {
        console.log(error);
    }
}

async function save() {
    try {
        // const response = await axiosInstance.post("/traveler/data/save", form);

        const authStore = useAuthStore();
        const accessToken = authStore.decryptWithAES(authStore.token);
        const response = await axios.post('/api/traveler/data/save', form, {
            headers: {
                'Content-Type': 'multipart/form-data',
                Authorization: "Bearer " + accessToken,
                Accept: "application/json",

            },
        });
        document.getElementById("travelerForm").reset();
        Notification.showToast('s', response.data.message);

    } catch (error) {
        ErrorCatch.CatchError(error);
    }
}

const handleFileChange = (event) => {
    form.passport_picture = event.target.files[0];
    const reader = new FileReader();
    reader.readAsDataURL(form.passport_picture);
    reader.onload = (e) => {
        previewImage.value = e.target.result;
    };
}

async function findExistanceTraveller(parm) {
    const response = await axiosInstance.post('get-travelers-data-by-search', { 'parm': parm });


    var options = [];

    if (response.data.length > 0) {
        showTravelerList.value = true;
    }

    $.each(response.data, function (key, value) {
        var obj = { id: value.id, text: value.full_name, bn: value.bn_name, clr: MF.getRandomColor() }
        options.push(obj);
    });
    filteredTraveler.value = options;


}
function selectTraveler(travl) {
    showTravelerList.value = false;
    $('#traveler_name').val(travl.text);

    getTravellertrData(travl.id);

    setTimeout(() => {
        $('#traveler_id').focus();
    }, 100);
}
</script>

<template>
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3"> Traveller Management</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item">
                        <router-link :to="{ name: 'Home' }">Dashboard</router-link>
                    </li>

                    <li class="breadcrumb-item active" aria-current="page">
                        <router-link :to="{ name: 'TravelerList' }">Traveller Management</router-link>

                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        New Traveller
                    </li>
                </ol>
            </nav>
        </div>
    </div>
    <div class="row position-relative">
        <div class="col-md-12">
            <form id="travelerForm">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="m-0 p-0" style="border-left: 5px solid rgb(114, 57, 234);"> &nbsp;
                                            Traveller Availability Check</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="d-flex flex-row bd-highlight">
                                                    <div class="p-2 bd-highlight w-100">
                                                        <input type="text" class="form-control" id="traveler_name"
                                                            name="name"
                                                            autocomplete="off"
                                                            @input="findExistanceTraveller($event.target.value)"
                                                            placeholder="Enter Traveller Passport Number"/>



                                                        <div v-if="showTravelerList" id="traveler_id"
                                                            class="position-absolute w-100 mt-2"
                                                            style="z-index: 1000; background-color: white; animation: fadeIn 0.3s ease-in-out;">
                                                            <SimpleBar style="max-height: 300px"
                                                                class="search-results-simplebar">
                                                                <div v-for="traveller in filteredTraveler"
                                                                    :key="traveller.id"
                                                                    class="cursor-pointer border-bottom border-light"
                                                                    @click="selectTraveler(traveller)">
                                                                    <div class="align-items-center">

                                                                        <div class="flex-grow-1 border-start ps-2 py-1">

                                                                            <div class="fw-bold font-12">{{
                                                                                traveller.text }}</div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div v-if="filteredTraveler.length === 0"
                                                                    class="p-3 text-center text-muted">
                                                                    No matching </div>
                                                            </SimpleBar>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="m-0 p-0" style="border-left: 5px solid rgb(114, 57, 234);"> &nbsp;
                                            Personal Information</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <span class="text-bold">
                                                    <b>PAX Type</b>
                                                </span>
                                                <div class="d-flex align-items-center gap-3 mt-2">

                                                    <div class="form-check pt-1">
                                                        <input class="form-check-input" type="radio"
                                                            name="flexRadioDefault" @click="paxTypeChange(1)"
                                                            id="adult">
                                                        <label class="form-check-label" for="flexRadioDefault1">
                                                            <b>Adult</b> </label>
                                                    </div>
                                                    <div class="form-check pt-1">
                                                        <input class="form-check-input" @click="paxTypeChange(2)"
                                                            type="radio" name="flexRadioDefault" id="child">
                                                        <label class="form-check-label" for="flexRadioSuccess">
                                                            <b>Children</b>
                                                        </label>
                                                    </div>
                                                    <div class="form-check pt-1">
                                                        <input class="form-check-input" @click="paxTypeChange(3)"
                                                            type="radio" name="flexRadioDefault"  id="infant">
                                                        <label class="form-check-label" for="flexRadioDanger">
                                                            <b>Infant</b>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row mt-4">
                                            <div class="col-md-2 bd-highlight pe-3">
                                                <label for="name" class="form-label"><b>Title</b></label>
                                                <select name="title" id="title"
                                                    class="form-select form-control form-select-sm">
                                                    <option value="">Select Title</option>
                                                    <option value="Mr.">Mr.</option>
                                                    <option value="Miss.">Miss.</option>
                                                    <option value="Mrs.">Mrs.</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4 pe-3">
                                                <label for="first_name" class="form-label"><b>First Name (Given
                                                        Name)</b></label>
                                                <input type="text" class="form-control" id="first_name"
                                                    name="first_name" placeholder="Enter First Name"
                                                    v-model="form.first_name">
                                            </div>
                                            <div class="col-md-6 pe-3">
                                                <label for="last_name" class="form-label"><b>Last Name (Sur
                                                        Name)</b></label>
                                                <input type="text" class="form-control" id="last_name" name="last_name"
                                                    placeholder="Enter Last Name" v-model="form.last_name">
                                            </div>

                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                <label for="name" class="form-label">Date of Birth</label>
                                                <input type="date" class="form-control" id="dob" name="dob"
                                                    placeholder="Enter Date of Birth" v-model="form.dob">
                                            </div>

                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                <label for="name" class="form-label">Gender</label>
                                                <select name="gender" id="gender"
                                                    class="form-select form-control form-select-sm">
                                                    <option value="">Select Gender</option>
                                                    <option value="Male">Male</option>
                                                    <option value="Female">Female</option>
                                                    <option value="Other">Other</option>
                                                </select>
                                            </div>

                                            <div class="col-6 col-sm-6 col-md-6 mt-1">
                                                <label for="email" class="form-label">Email</label>
                                                <input type="text" class="form-control" id="email" name="email"
                                                    placeholder="Enter Email" v-model="form.email">
                                            </div>

                                            <div class="col-6 col-sm-6 col-md-6 mt-1">
                                                <label for="phone" class="form-label">Phone</label>
                                                <input type="text" class="form-control" id="phone" name="phone"
                                                    placeholder="Enter Phone" v-model="form.phone">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="m-0 p-0" style="border-left: 5px solid #ffbc0f;"> &nbsp; Passport
                                            Information</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-12 col-sm-12 col-md-12">
                                                <label for="passport_no" class="form-label">Passport Number</label>
                                                <input type="text" class="form-control" id="passport_no"
                                                    name="passport_no" placeholder="Enter Passport Number"
                                                    v-model="form.passport_no">
                                            </div>

                                            <div class="col-12 col-sm-12 col-md-12 mt-2">
                                                <label for="p_expiry_date" class="form-label">Expiry Date</label>
                                                <input type="date" class="form-control" id="p_expiry_date"
                                                    name="p_expiry_date" placeholder="Enter Date of Birth"
                                                    v-model="form.p_expiry_date">
                                            </div>

                                            <div class="col-12 col-sm-12 col-md-12 mt-2">
                                                <label for="name" class="form-label">Nationality</label>
                                                <select name="nationality" id="nationality"
                                                    class="form-select form-select-sm">
                                                    <option value="Bangladeshi">Bangladeshi</option>
                                                    <option value="American">American</option>
                                                    <option value="Pakistani">Pakistani</option>
                                                    <option value="Indian">Indian</option>
                                                </select>
                                            </div>

                                            <div class="col-12 col-sm-12 col-md-12 mt-2">
                                                <label for="name" class="form-label">Passport Image (Max 2MB)</label>
                                                <div class="input-group mb-3 input-group-sm">
                                                    <input type="file" class="form-control" id="profile-picture"
                                                        ref="profilePicture" @change="handleFileChange">
                                                    <label class="input-group-text" for="profile-picture">Upload</label>
                                                </div>

                                                <img v-if="previewImage" :src="previewImage" height="150" width="150"
                                                    class="border border-1 rounded rounded-2" alt="Profile Picture">

                                            </div>


                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex">
                                <button type="button" class="btn btn-primary " @click="$router.go(-1)">Back</button>
                                <button type="button" class="btn btn-primary ms-auto bd-highligh"
                                    @click="save()">Save</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

</template>

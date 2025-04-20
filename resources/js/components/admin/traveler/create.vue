<script setup>
import { useRouter } from 'vue-router';
const router = useRouter();
import { useAuthStore } from '../../../stores/authStore';
const authStore = useAuthStore();
import axiosInstance from "../../../axiosInstance";
import { ref, reactive, onMounted, render } from "vue";

const form = reactive({ pax_type: "", title_val: "", first_name: "", last_name: "", dob: "", gender: "", email: "", phone: "", passport_no: "", p_expiry_date: "", nationality: "", passport_picture: "", useEmail: authStore.email });

const previewImage = ref('');

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
});

function paxTypeChange(type) {
    form.pax_type = type;
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
                                                        <input type="text" class="form-control" id="name" name="name"
                                                            placeholder="Enter Traveller Passport Number" />
                                                    </div>
                                                    <div class="p-2 bd-highlight">
                                                        <button class="w3-button w3-blue w3-round w3-medium">Check
                                                            Availability</button>
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
                                                            id="flexRadioDefault1">
                                                        <label class="form-check-label" for="flexRadioDefault1">
                                                            <b>Adult</b> </label>
                                                    </div>
                                                    <div class="form-check pt-1">
                                                        <input class="form-check-input" @click="paxTypeChange(2)"
                                                            type="radio" name="flexRadioDefault" id="flexRadioSuccess">
                                                        <label class="form-check-label" for="flexRadioSuccess">
                                                            <b>Children</b>
                                                        </label>
                                                    </div>
                                                    <div class="form-check pt-1">
                                                        <input class="form-check-input" @click="paxTypeChange(3)"
                                                            type="radio" name="flexRadioDefault" id="flexRadioDanger">
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
                                <button type="submit" class="btn btn-primary ">Back</button>
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

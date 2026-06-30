<script setup>
import AppBreadcrumbs from '../../common/AppBreadcrumbs.vue';

import { useAuthStore } from "../../../stores/authStore";
import axiosInstance from "../../../axiosInstance";
import { ref, onMounted, reactive } from "vue";
import moment from "moment";
const props = defineProps(['ids']);
const previewImage = ref('');


getAgentData(props.ids);

async function getAgentData(props) {

    try {
        const response = await axiosInstance.post('viewAgent', { 'id': props });

        const agency_name = response.data[0].name;
        const status = response.data[0].status;
        const email = response.data[0].email;
        const est_date = response.data[0].established_date;
        const address = response.data[0].address;
        const phone = response.data[0].phone;
        const country = response.data[0].country;
        const city = response.data[0].city;
        const zone = response.data[0].zone;
        const agent_code = response.data[0].agent_code;
        const postal_code = response.data[0].postal_code;
        const reg_number = response.data[0].reg_number;
        const fax = response.data[0].fax;
        const ca_number = response.data[0].ca_number;
        const trade_licence = response.data[0].trade_licence;
        const iata_number = response.data[0].iata_number;
        const hajj_agency_number = response.data[0].hajj_agency_number;
        const owner_name = response.data[0].owner;
        const owner_designation = response.data[0].designation;
        const owner_nid = response.data[0].owner_nid;
        const dob = response.data[0].dob;
        const owner_email = response.data[0].owner_email;
        const owner_phone = response.data[0].owner_phone;
        const kam_name = response.data[0].kam;
        const remarks = response.data[0].remarks;
        const logo = response.data[0].logo_path;
        const approver = response.data[0].approver;
        const approver_remarks = response.data[0].approver_remarks;

        previewImage.value = logo;

        if (status == 'Pending') {
            $("#status_color").addClass("text-warning bg-light-warning");
            $('#cstatus').html(status);
        } else if (status == 'Approved') {
            $("#status_color").addClass("text-success bg-light-success");
            $('#cstatus').html(status);
        } else if (status == 'Hold') {
            $("#status_color").addClass("text-warning bg-light-warning");
            $('#cstatus').html(status);
        }
        else if (status == 'Recommended') {
            $("#status_color").addClass("text-info bg-light-info");
            $('#cstatus').html(status);
        }
        else if (status == 'Reject') {
            $("#status_color").addClass("text-danger bg-light-danger");
            $('#cstatus').html(status);
        }

        $(".agency_name").html(agency_name);
        $(".email").html(email);
        $(".est_date").html(moment(est_date).format('DD-MMM-YYYY'));
        $(".ownerdob").html(moment(dob).format('DD-MMM-YYYY'));
        $(".address").html(address);
        $(".phone").html(phone);
        $(".country").html(country);
        $(".city").html(city);
        $(".zone").html(zone);
        $(".agent_code").html(agent_code);
        $(".postal_code").html(postal_code);
        $(".reg_number").html(reg_number);
        $(".fax").html(fax);
        $(".ca_number").html(ca_number);
        $(".trade_licence").html(trade_licence);
        $(".iata_number").html(iata_number);
        $(".hajj_agency_number").html(hajj_agency_number);
        $(".owner_name").html(owner_name);
        $(".owner_designation").html(owner_designation);
        $(".owner_nid").html(owner_nid);
        $(".owner_email").html(owner_email);
        $(".owner_phone").html(owner_phone);
        $(".kam_name").html(kam_name);
        $(".remarks").html(remarks);
        $(".approver").html(approver);
        $(".approver_remarks").html(approver_remarks);

    } catch (error) {
        console.log(error);
    }
}

getAgentAllImage(props.ids);
async function getAgentAllImage(props) {

    try {
        const response = await axiosInstance.post('AgentAllImage', { 'id': props });
        $.each(response.data, function (key, value) {
            var title = '';
            if (value.attachment_type == 'trade_licence_img') {
                title = 'Trade Licence';
            }
            else if (value.attachment_type == 'nid_img') {
                title = 'NID';
            }
            else if (value.attachment_type == 'ca_img') {
                title = 'Agent Owner';
            }
            else if (value.attachment_type == 'iata_img') {
                title = 'IATA';
            }
            else if (value.attachment_type == 'hajj_licence_img') {
                title = 'Hajj Licence';
            }
            else if (value.attachment_type == 'tin_img') {
                title = 'TIN';
            }
            $("#agent_images").append('<li class="list-group-item d-flex justify-content-between align-items-center flex-wrap"><h6 style="font-size: 14px;" class="mb-0">' + title + '</h6><span class="text-secondary"><img height="40" width="40" src="' + value.attachment_path + '" alt=""></span></li>');
        });

    } catch (error) {
        console.log(error);
    }
}

getAgentApprovalLog(props.ids);

async function getAgentApprovalLog(props) {
    try {
        const response = await axiosInstance.post('getAgentApprovalLog', { 'id': props });
        $.each(response.data, function (key, value) {
            console.log(value);
            if (value.status == 'Recommended') {
            $("#agent_recommended").append('<table class="table table-borderless table-sm table-responsive"> <tbody> <tr> <td class="m-0 pl-2" width="50%"> <label for=""><b>Recommended By:</b></label> <p class="m-0 approver">'+value.approver_name+'</p> </td> <td class="m-0 pl-2" width="50%"> <label for=""><b>Remarks:</b></label> <p class="m-0 approver_remarks">'+value.remarks+'</p> </td> </tr> </tbody> </table>');

            }else if (value.status == 'Approved') {

                $("#agent_approved").append('<table class="table table-borderless table-sm table-responsive"> <tbody> <tr> <td class="m-0 pl-2" width="50%"> <label for=""><b>Approved By:</b></label> <p class="m-0">'+value.approver_name+'</p> </td><td class="m-0 pl-2" width="50%"> <label for=""><b>Remarks:</b></label> <p class="m-0 approver_remarks">'+value.remarks+'</p> </td> </tr> </tbody></table>');
            }

        })
    } catch (error) {
        console.log(error);
    }
}

</script>
<template>
        <AppBreadcrumbs
        title="B2B Agency"
        :back-to="{ name: 'AgentList' }"
        :breadcrumbs="[
            { label: 'Dashboard', to: { name: 'Home' } },
            { label: 'B2B Agency List', to: { name: 'AgentList' } },
            { label: 'View Agency' },
        ]"
    />

    <div class="row">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-column align-items-center text-center">
                        <img v-if="previewImage" :src="previewImage" height="60" width="60"
                            class="border border-1 rounded rounded-2" alt="Profile Picture">
                        <img v-else src="../../../../../public/theme/appimages/Plane_origin.svg" height="60"
                            width="60" class="border border-1 rounded rounded-2" alt="Profile Picture">
                        <div class="mt-3">
                            <h4 class="agency_name"></h4>
                        </div>
                    </div>
                    <hr class="my-3">
                    <div class="text-center">
                        Status:
                        <div class="badge rounded-pill p-2 text-uppercase px-3" id="status_color"><i
                                class="bx bxs-circle me-1"></i> <span id="cstatus"></span> </div>
                    </div>
                    <hr class="my-2">
                    <ul class="list-group list-group-flush">
                        <span id="agent_images"></span>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="m-0 p-0"> &nbsp; Agency Details</h5>

                        </div>
                        <div class="card-body">
                            <table class="table table-borderless table-sm table-responsive">
                                <tbody>
                                    <tr>
                                        <td class="m-0 pl-2" width="50%">
                                            <label for=""><b>Name:</b></label>
                                            <p class="m-0 agency_name"></p>
                                        </td>
                                        <td class="m-0 pl-2" width="50%">
                                            <label for=""><b>Established Date:</b></label>
                                            <p class="m-0 est_date"></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="m-0 pl-2" width="50%">
                                            <label for=""><b>Email:</b></label>
                                            <p class="m-0 email"></p>
                                        </td>
                                        <td class="m-0 pl-2" width="50%">
                                            <label for=""><b>Phone: </b></label>
                                            <p class="m-0 phone"></p>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td class="m-0 pl-2" width="50%">
                                            <label for=""><b>Country:</b></label>
                                            <p class="m-0 country"></p>
                                        </td>
                                        <td class="m-0 pl-2" width="50%">
                                            <label for=""><b>District: </b></label>
                                            <p class="m-0 city"></p>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td class="m-0 pl-2" width="50%">
                                            <label for=""><b>Address:</b></label>
                                            <p class="m-0 address">-</p>
                                        </td>
                                        <td class="m-0 pl-2" width="50%">
                                            <label for=""><b>City: </b></label>
                                            <p class="m-0 zone"></p>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td class="m-0 pl-2" width="50%">
                                            <label for=""><b>Agent Code:</b></label>
                                            <p class="m-0 agent_code"></p>
                                        </td>
                                        <td class="m-0 pl-2" width="50%">
                                            <label for=""><b>Registration No: </b></label>
                                            <p class="m-0 reg_number"></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="m-0 pl-2" width="50%">
                                            <label for=""><b>Postal Code:</b></label>
                                            <p class="m-0 postal_code"></p>
                                        </td>
                                        <td class="m-0 pl-2" width="50%">
                                            <label for=""><b>FAX: </b></label>
                                            <p class="m-0 fax"></p>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td class="m-0 pl-2" width="50%">
                                            <label for=""><b>Civil Aviation Certificate No:</b></label>
                                            <p class="m-0 ca_number">-</p>
                                        </td>
                                        <td class="m-0 pl-2" width="50%">
                                            <label for=""><b>Trade License: </b></label>
                                            <p class="m-0 trade_licence">-</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="m-0 pl-2" width="50%">
                                            <label for=""><b>IATA:</b></label>
                                            <p class="m-0 iata_number"></p>
                                        </td>
                                        <td class="m-0 pl-2" width="50%">
                                            <label for=""><b>Hajj License: </b></label>
                                            <p class="m-0 hajj_agency_number"></p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="m-0 p-0"> &nbsp; Primary User Information</h5>

                        </div>
                        <div class="card-body">
                            <table class="table table-borderless table-sm table-responsive">
                                <tbody>
                                    <tr>
                                        <td class="m-0 pl-2" width="50%">
                                            <label for=""><b>Owner's Name:</b></label>
                                            <p class="m-0 owner_name"></p>
                                        </td>
                                        <td class="m-0 pl-2" width="50%">
                                            <label for=""><b>Designation:</b></label>
                                            <p class="m-0 owner_designation"></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="m-0 pl-2" width="50%">
                                            <label for=""><b>NID:</b></label>
                                            <p class="m-0 owner_nid"></p>
                                        </td>
                                        <td class="m-0 pl-2" width="50%">
                                            <label for=""><b>Date of Birth: </b></label>
                                            <p class="m-0 ownerdob"></p>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td class="m-0 pl-2" width="50%">
                                            <label for=""><b>Email:</b></label>
                                            <p class="m-0 owner_email"></p>
                                        </td>
                                        <td class="m-0 pl-2" width="50%">
                                            <label for=""><b>Phone: </b></label>
                                            <p class="m-0 owner_phone">x</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="m-0 p-0"> &nbsp; KAM Information & Remarks</h5>

                        </div>
                        <div class="card-body">
                            <table class="table table-borderless table-sm table-responsive">
                                <tbody>
                                    <tr>
                                        <td class="m-0 pl-2" width="50%">
                                            <label for=""><b>KAM:</b></label>
                                            <p class="m-0 kam_name"></p>
                                        </td>
                                        <td class="m-0 pl-2" width="50%">
                                            <label for=""><b>Remarks: </b></label>
                                            <p class="m-0 remarks"></p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="m-0 p-0"> &nbsp; Recommendeation</h5>
                        </div>
                        <div class="card-body">
                            <span id="agent_recommended"></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="m-0 p-0"> &nbsp; Approval</h5>
                        </div>
                        <div class="card-body">
                            <span id="agent_approved"></span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>

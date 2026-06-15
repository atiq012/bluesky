<script setup>
import DataTable from "datatables.net-vue3";
import DataBS5 from "datatables.net-bs5";
import jszip from 'jszip';
import 'datatables.net-buttons-bs5';
import 'datatables.net-buttons/js/buttons.html5.mjs';
import 'datatables.net-responsive-bs5';
import { useRouter } from 'vue-router';
const router = useRouter();
import { useAuthStore } from '../../../stores/authStore';
const authStore = useAuthStore();
import axiosInstance from "../../../axiosInstance";
import { ref, reactive, onMounted, render } from "vue";

DataTable.use(DataBS5);
DataBS5.Buttons.jszip(jszip);

const rData = ref([]);
const form = reactive({ status: '', useridStatus: '' });
onMounted(() => {
    $('#status').on("change", function () {
        form.status = $(this).val();
    });
});
getListValues();

const options = {
    responsive: true,
    destroy: true,
    pageLength: 30,
    lengthMenu: [3, 10, 20, 30],
    ordering: false,
    columnDefs: [{
        defaultContent: "0",
        targets: "_all",
    }
    ],
    dom: "<'row'<'col-sm-4'B><'d-md-flex justify-content-between align-items-center dt-layout-end col-md-auto ms-auto'f>>" + "<'row'<'col-sm-12'tr>>" +
        "<'row justify-content-between Reduct_table_gap'<'d-md-flex justify-content-between align-items-center dt-layout-start col-md-auto me-auto'i><'d-md-flex justify-content-between align-items-center dt-layout-end col-md-auto ms-auto'p>>",
    buttons: [
        {
            extend: 'excel',
            text: '<i class="fa fa-file-excel"></i> Excel',
            title: 'Your Title',
            messageTop: function () {
                return 'Fun';
            },
            className: 'btn btn-danger btn-sm text-white',
            exportOptions: {
                columns: [1, 2, 3, 5]
            }
        },
        {
            extend: 'csv',
            text: '<i class="fa fa-file-csv"></i> CSV',
            title: 'Your Title',
            messageTop: function () {

                return 'Fun';
            },
            className: 'btn btn-info btn-sm text-white',
            exportOptions: {
                columns: [1, 2, 3, 5]
            }
        }
    ],
    language: {
        search: "",
        searchPlaceholder: "Search by anything..",
    },
    columns: [
        { data: "DT_RowIndex", title: "SL" },
        {
            title: "Staff Info",
            render: function (data, type, row) {
                var html = "";
                // row
                html += '<div class="row">';
                html += '<div class="col-md-4">';
                html += '<img src="' + row.img + '" height="60"  class="w-50">';
                html += '</div>';
                html += '<div class="col-md-8" style="white-space:nowrap;">'

                html += row.name;
                html += "<br>";
                html += '<span class="text-primary">';

                html += row.emp_id + "</span>";
                html += "<br>";
                html += row.desg;
                html += " | ";
                html += row.dept;
                html += "</div>";

                html += '</div>';
                return html;
            },
            width: '200%',


        },
        {
            title: "Role & Office",
            render: function (data, type, row) {
                var html = "";
                html += row.r_name;
                html += "<br>";
                html += row.off_loc;

                return html;
            },
            width: '20%',

        },
        {
            title: "User Login Info",
            render: function (data, type, row) {
                var html = "";
                html += row.email;
                html += "<br>";
                html += row.phone;

                return html;
            }
        },
        {
            title: "Created",
            render: function (data, type, row) {
                var html = "";
                html += row.created_by;
                html += "<br>";

                html += '<span class="text-primary">';
                html += row.created_at + "</span>";
                return html;
            },
        },
        {
            title: "Updated",
            render: function (data, type, row) {
                var html = "";
                html += row.updated_by || "";
                html += "<br>";

                html += '<span class="text-primary">';
                html += row.updated_at + "</span>";
                return html;
            },
        },

        {
            title: "Status",
            render: function (data, type, row) {
                var html = "";

                if (row.status == 1) {
                    html += '<div class="badge rounded-pill text-success bg-light-success p-2 text-uppercase px-3"><i class="bx bxs-circle me-1"></i>Active </div>';
                } else if (row.status == 2) {
                    html += '<div class="badge rounded-pill text-warning bg-light-warning p-2 text-uppercase px-3"><i class="bx bxs-circle me-1"></i>On Hold </div>';
                }
                else if (row.status == 3) {
                    html += '<div class="badge rounded-pill text-info bg-light-info p-2 text-uppercase px-3"><i class="bx bxs-circle me-1"></i>Locked </div>';
                }
                else {
                    html += '<div class="badge rounded-pill text-danger bg-light-danger p-2 text-uppercase px-3"><i class="bx bxs-circle me-1"></i>Deactivated </div>';
                }

                return html;
            },
        },

        {
            title: "Action",
            render: function (data, type, row) {
                var html = "";
                var idd = row.idd;
                var status = row.status;

                html += '<button  style="size: 30px; width: 30px; height: 30px" class="btn btn-outline-only-edit rounded-circle edit-item" placement="top" id="edit_tool" data-item-id=' + idd + '> <i class="fa-solid fa-pencil" style="margin: 0px 0px 10px -5px; font-size: 14px;" ></i> </button>';

                html += '<button type="button" style="size: 30px; width: 30px; height: 30px; margin-left: 5px;" class="btn btn-outline-purple rounded-circle status-change" data-item-id=' + idd + ' data-status=' + status + '> <i class="fa fa-refresh" style="margin: 2px 0px 10px -5px; font-size: 14px;"></i> </button>';

                html += '<button type="button" v-tippy="Lock" style="size: 30px; width: 30px; height: 30px; margin-left: 5px;" class="btn btn-outline-timer rounded-circle history-data" data-item-id=' + idd + '> <i class="fa-solid fa-clock-rotate-left" style="margin: 2px 0px 10px -5px; font-size: 14px;"></i> </button>';

                html += '<button style="size: 30px; width: 30px; height: 30px; margin-left: 5px;" class="btn btn-outline-danger rounded-circle delete-item" data-item-id=' + idd + '> <i class="fa-solid fa-trash" style="margin: 2px 0px 10px  -4px; font-size: 14px;"></i> </button>';

                return html;
            },
            width: '90%'
        }
    ],
    "drawCallback": function (settings) {
        // edit function
        $(".edit-item").on('click', function (e) {

            var itemIdd = $(this).attr('data-item-id');

            router.push({ name: 'EditUser', params: { id: itemIdd } });
        });

        // delete function
        $(".delete-item").on('click', function (e) {

            var idd = $(this).attr('data-item-id');

            // delete pop up message

            iziToast.question({
                timeout: 100000,
                pauseOnHover: false,
                close: false,
                overlay: true,
                displayMode: 'once',
                id: 'question',
                zindex: 999,
                message: 'Want to delete this user?',
                position: 'center',
                buttons: [
                    ['<button><b>No</b></button>', function (instance, toast) {

                        instance.hide({ transitionOut: 'fadeOut' }, toast, 'no');

                    }, true],
                    ['<button><b>Yes</b></button>', function (instance, toast) {

                        instance.hide({ transitionOut: 'fadeOut' }, toast, 'yes');

                    }, true]
                ],
                onClosed: async function (instance, toast, closedBy) {

                    if (closedBy == 'yes') {
                        const response = axiosInstance.post("deleteUser", { 'id': idd });
                        getListValues();
                        Notification.showToast('s', 'Successfully User Deleted.');
                    } else {

                    }

                }
            });
            // delete pop up message end


        });

        // change status
        $(".status-change").on('click', function (e) {

            var idd = $(this).attr('data-item-id');
            var sta = $(this).attr('data-status');

            $('#status_change_modal').modal('show');
            $("#status").val(sta);
            $("#status").trigger('change');
            form.useridStatus = idd;
            $('#useridStatus').val(idd);
        });

        // history
        $(".history-data").on('click', function (e) {
            var itemId = $(this).attr('data-item-id');
            console.log(itemId);

            router.push({ name: 'UserLog', params: { id: itemId } });


        })
    }
};

async function getListValues() {

    try {

        authStore.GlobalLoading = true;
        const response = await axiosInstance.get("getExternalUsers");
        rData.value = response.data.data;
        authStore.GlobalLoading = false;
    } catch (error) {
        authStore.GlobalLoading = false;
        console.log(error);
    }
}

async function update() {
    try {
        const response = await axiosInstance.post("/user-status/update", form);
        getListValues();

        $('#status_change_modal').modal('hide');

        Notification.showToast('s', response.data.message);

    } catch (error) {
        ErrorCatch.CatchError(error);
    }

}
</script>
<template>
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3"> User Management</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item">
                        <router-link :to="{ name: 'Home' }">Dashboard</router-link>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">User List</li>
                </ol>
            </nav>
        </div>
        <div class="ms-auto">
            <div class="btn-group">
                <router-link :to="{ name: 'CreateUser' }" class="btn btn-primary btn-sm">
                    <i class="fa fa-circle-plus"></i> User
                </router-link>

            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12 col-sm-6 col-md-3">
            <div class="info-agency">
                <span class="info-agency-icon bg-info elevation-1"><i class="fas fa-users"></i></span>
                <div class="info-agency-content">
                    <span class="info-agency-text">Total User</span>
                    <span class="info-agency-number">
                        1200
                    </span>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-md-3">
            <div class="active-agency mb-3">
                <span class="active-agency-icon bg-success elevation-1 text-white"><i
                        class="fa-solid fa-circle-check"></i></span>
                <div class="active-agency-content">
                    <span class="active-agency-text">Active User</span>
                    <span class="active-agency-number">760</span>
                </div>

            </div>

        </div>

        <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box mb-3">
                <span class="info-box-icon bg-danger elevation-1"><i class="fa fa-pause"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">On Hold</span>
                    <span class="info-box-number">5</span>
                </div>

            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <div class="pending-agnt mb-3">
                <span class="pending-agnt-icon bg-warning elevation-1"><i class="fa fa-clock"></i></span>
                <div class="pending-agnt-content">
                    <span class="pending-agnt-text">Locked</span>
                    <span class="pending-agnt-number">20</span>
                </div>

            </div>

        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="row shadow-none rounded rounded-2 p-3">
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" id="single-select-field"
                            data-placeholder="Choose one thing">
                            <option>Select User Type</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" id="single-select-field"
                            data-placeholder="Choose one thing">
                            <option>Select Office Location</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <select class="form-select form-select-sm" id="single-select-field"
                            data-placeholder="Choose one thing">
                            <option>Select Status</option>
                        </select>
                    </div>
                    <div class="col-md-1 mt-2">
                        <i class="fa fa-times text-danger"> </i> Clear
                    </div>

                </div>
            </div>
        </div>
    </div>
    <div class="table-responsive">
        <div id="example2_wrapper" class="dataTables_wrapper dt-bootstrap5">

            <div class="card-body">

                <!-- table -->
                <DataTable :options="options" :data="rData" class="table table-sm table-striped table-bordered">
                </DataTable>
                <!-- <div class="row mt-2">
                            <div class="col-sm-12">
                                <table class="table table-sm table-striped table-bordered">
                                    <thead>
                                        <tr role="row">
                                            <th class="sorting_asc" tabindex="0" aria-controls="example2" rowspan="1"
                                                colspan="1" aria-sort="ascending"
                                                aria-label="SL: activate to sort column descending"
                                                style="width: 194.4px;">
                                                SL.</th>
                                            <th class="sorting" tabindex="0" aria-controls="example2" rowspan="1"
                                                colspan="1" aria-label="Staff Info: activate to sort column ascending"
                                                style="width: 316.087px;">Staff Info</th>
                                            <th class="sorting" tabindex="0" aria-controls="example2" rowspan="1"
                                                colspan="1" aria-label="Agency Info: activate to sort column ascending"
                                                style="width: 140.688px;">Agency Info</th>
                                            <th class="sorting" tabindex="0" aria-controls="example2" rowspan="1"
                                                colspan="1" aria-label="Area: activate to sort column ascending"
                                                style="width: 66.3875px;">Area</th>
                                            <th class="sorting" tabindex="0" aria-controls="example2" rowspan="1"
                                                colspan="1" aria-label="Role: activate to sort column ascending"
                                                style="width: 66.3875px;">Role</th>
                                            <th class="sorting" tabindex="0" aria-controls="example2" rowspan="1"
                                                colspan="1"
                                                aria-label="User Login Info: activate to sort column ascending"
                                                style="width: 66.3875px;">User Login Info</th>
                                            <th class="sorting" tabindex="0" aria-controls="example2" rowspan="1"
                                                colspan="1" aria-label="Created By: activate to sort column ascending"
                                                style="width: 131.8px;">Created By</th>
                                            <th class="sorting" tabindex="0" aria-controls="example2" rowspan="1"
                                                colspan="1" aria-label="Updated By: activate to sort column ascending"
                                                style="width: 107.037px;">Updated By</th>
                                            <th class="sorting" tabindex="0" aria-controls="example2" rowspan="1"
                                                colspan="1" aria-label="Status: activate to sort column ascending"
                                                style="width: 107.037px;">Status</th>
                                            <th class="sorting" tabindex="0" aria-controls="example2" rowspan="1"
                                                colspan="1" aria-label="Action: activate to sort column ascending"
                                                style="width: 107.037px;">Action</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <tr role="row">
                                            <td class="text-left">01</td>
                                            <td class="text-left">Abu Syed

                                                <br>
                                                <small class="text-blue">
                                                    Managing Director
                                                </small>
                                            </td>
                                            <td class="text-left">
                                                ABC Travels Ltd
                                                <br>
                                                <small class="text-blue">12345</small> | IATA
                                            </td>

                                            <td>
                                                Motijheel
                                                <br>
                                                <small class="text-blue">Dhaka-North</small>
                                            </td>

                                            <td>Agency Admin</td>
                                            <td class="text-left">
                                                abu.syed@galxybd.com
                                                <br>
                                                <small class="text-blue">017xxxxxxx</small>
                                            </td>

                                            <td class="text-left">
                                                Md. Abu Syed
                                                <br>
                                                <small class="text-blue">05-Sept-2024</small>
                                            </td>
                                            <td class="text-left">
                                                Md. Abu Syed
                                                <br>
                                                <small class="text-blue">05-Sept-2024</small>
                                            </td>
                                            <td class="text-left">
                                                <div
                                                    class="badge rounded-pill text-success bg-light-success p-2 text-uppercase px-3">
                                                    <i class="bx bxs-circle me-1"></i>Active
                                                </div>
                                                <br>
                                                <small>
                                                    21-Aug-2024
                                                </small>
                                                <br>
                                                <small class="text-blue">
                                                    Md. Abu Zafar Chowdhary
                                                </small>
                                            </td>
                                            <td class="text-left">
                                                <button type="button" style="size: 30px; width: 30px; height: 30px;"
                                                    class="btn btn-outline-only-edit rounded-circle"
                                                    v-tippy="'Profile Edit'">

                                                    <i class="fa-solid fa-pencil"
                                                        style="margin: 2px 0px 10px -4px; font-size: 14px;"></i>
                                                </button>

                                                <button type="button" v-tippy="'Profile Delete'"
                                                    style="size: 30px; width: 30px; height: 30px; margin-left: 5px;"
                                                    class="btn btn-outline-danger rounded-circle">
                                                    <i class="fa fa-trash"
                                                        style="margin: 2px 0px 10px -4px; font-size: 14px;"></i>
                                                </button>

                                                <button type="button" v-tippy="'Hold'"
                                                    style="size: 30px; width: 30px; height: 30px; margin-left: 5px;"
                                                    class="btn btn-outline-purple rounded-circle">
                                                    <i class="fa fa-refresh"
                                                        style="margin: 2px 0px 10px -5px; font-size: 14px;"></i>
                                                </button>
                                                <button type="button" v-tippy="'Lock'"
                                                    style="size: 30px; width: 30px; height: 30px; margin-left: 5px;"
                                                    class="btn btn-outline-lock rounded-circle">
                                                    <i class="fa fa-lock"
                                                        style="margin: 2px 0px 10px -5px; font-size: 14px;"></i>
                                                </button>

                                                <button type="button" v-tippy="'Lock'"
                                                    style="size: 30px; width: 30px; height: 30px; margin-left: 5px;"
                                                    class="btn btn-outline-timer rounded-circle">
                                                    <i class="fa-solid fa-clock-rotate-left"
                                                        style="margin: 2px 0px 10px -5px; font-size: 14px;"></i>
                                                </button>
                                            </td>
                                        </tr>

                                        <tr role="row">
                                            <td class="text-left">02</td>
                                            <td class="text-left">Imtiaz Ahmed

                                                <br>
                                                <small class="text-blue">
                                                    Supervisor
                                                </small>
                                            </td>
                                            <td class="text-left">
                                                ABC Travels Ltd
                                                <br>
                                                <small class="text-blue">12345</small> | IATA
                                            </td>


                                            <td>
                                                Motijheel
                                                <br>
                                                <small class="text-blue">Dhaka-North</small>
                                            </td>
                                            <td>Supervisor</td>
                                            <td class="text-left">
                                                test@galxybd.com
                                                <br>
                                                <small class="text-blue">017xxxxxxx</small>

                                            </td>
                                            <td class="text-left">
                                                Md. Abu Syed
                                                <br>
                                                <small class="text-blue">05-Sept-2024</small>
                                            </td>
                                            <td class="text-left">
                                                Md. Abu Syed
                                                <br>
                                                <small class="text-blue">05-Sept-2024</small>
                                            </td>
                                            <td class="text-left">
                                                <div
                                                    class="badge rounded-pill text-success bg-light-success p-2 text-uppercase px-3">
                                                    <i class="bx bxs-circle me-1"></i>Active
                                                </div>
                                                <br>
                                                <small>
                                                    21-Aug-2024
                                                </small>
                                                <br>
                                                <small class="text-blue">
                                                    Md. Abu Zafar Chowdhary
                                                </small>
                                            </td>
                                            <td class="text-left">
                                                <button type="button" style="size: 30px; width: 30px; height: 30px;"
                                                    class="btn btn-outline-only-edit rounded-circle"
                                                    v-tippy="'Profile Edit'">

                                                    <i class="fa-solid fa-pencil"
                                                        style="margin: 2px 0px 10px -4px; font-size: 14px;"></i>
                                                </button>

                                                <button type="button" v-tippy="'Profile Delete'"
                                                    style="size: 30px; width: 30px; height: 30px; margin-left: 5px;"
                                                    class="btn btn-outline-danger rounded-circle">
                                                    <i class="fa fa-trash"
                                                        style="margin: 2px 0px 10px -4px; font-size: 14px;"></i>
                                                </button>

                                                <button type="button" v-tippy="'Hold'"
                                                    style="size: 30px; width: 30px; height: 30px; margin-left: 5px;"
                                                    class="btn btn-outline-purple rounded-circle">
                                                    <i class="fa fa-refresh"
                                                        style="margin: 2px 0px 10px -5px; font-size: 14px;"></i>
                                                </button>
                                                <button type="button" v-tippy="'Lock'"
                                                    style="size: 30px; width: 30px; height: 30px; margin-left: 5px;"
                                                    class="btn btn-outline-lock rounded-circle">
                                                    <i class="fa fa-lock"
                                                        style="margin: 2px 0px 10px -5px; font-size: 14px;"></i>
                                                </button>

                                                <button type="button" v-tippy="'Lock'"
                                                    style="size: 30px; width: 30px; height: 30px; margin-left: 5px;"
                                                    class="btn btn-outline-timer rounded-circle">
                                                    <i class="fa-solid fa-clock-rotate-left"
                                                        style="margin: 2px 0px 10px -5px; font-size: 14px;"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div> -->
            </div>
            <!-- modal start -->
            <div class="modal fade" id="status_change_modal" tabindex="-1" aria-modal="true" role="dialog">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">

                            <h5 class="m-0 p-0 modal-title fs-6" style="border-left: 5px solid rgb(114, 57, 234);">
                                &nbsp;Change Status</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <label for="input4" class="form-label">Status</label>

                            <select id="status" name="status" class="form-control form-control-sm status">
                                <option value="">== Select ==</option>
                                <option value="1">Active</option>
                                <option value="2">On Hold</option>
                                <option value="3">Locked</option>
                                <option value="4">Deactivated</option>
                            </select>
                            <input type="hidden" id="useridStatus" name="useridStatus">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm"
                                data-bs-dismiss="modal">Close</button>
                            <button type="button" @click="update()" class="btn btn-sm btn-success">Update</button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

</template>

<style>
.text-blue {
    color: blue;
}

/* dashboard design */
.info-agency {
    box-shadow: 0 0 1px rgba(0, 0, 0, .125), 0 1px 3px rgba(0, 0, 0, .2);
    border-radius: .25rem;
    background-image: linear-gradient(to right top, #dae9f8, #dae9f8, #dae9f8, #dae9f8, #dae9f8, #cbdff4, #bcd6f1, #aecced, #8eb6e4, #6da1dc, #4a8bd2, #1576c9);
    /* background-image: linear-gradient(to right top, #dae9f8, #dae9f8, #dae9f8, #dae9f8, #dae9f8, #d6e7f8, #d1e5f8, #cde3f8, #c2def8, #b8d9f8, #add5f8, #a1d0f8); */
    display: -ms-flexbox;
    display: flex;
    margin-bottom: 1rem;
    min-height: 90px;
    padding: .5rem;
    position: relative;
    width: 100%;
}

.info-agency .info-agency-icon {
    border-radius: .25rem;
    -ms-flex-align: center;
    align-items: center;
    display: -ms-flexbox;
    display: flex;
    font-size: 1.875rem;
    -ms-flex-pack: center;
    justify-content: center;
    text-align: center;
    width: 70px;
}

.bg-info,
.bg-info>a {
    color: #fff !important;
}


.elevation-1 {
    box-shadow: 0 1px 3px rgba(0, 0, 0, .12), 0 1px 2px rgba(0, 0, 0, .24) !important;
}

.bg-info {
    background-color: #0880e1 !important;
}


.info-agency .info-agency-content {
    display: -ms-flexbox;
    display: flex;
    -ms-flex-direction: column;
    flex-direction: column;
    -ms-flex-pack: center;
    justify-content: center;
    line-height: 1.5;
    -ms-flex: 1;
    flex: 1;
    padding: 0 30px;
    overflow: hidden;
}

.info-agency .info-agency-text {
    font-size: 19px;
    letter-spacing: normal;
    color: #838587;
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.info-agency .info-agency-number {
    display: block;
    margin-top: .25rem;
    font-weight: 700;
    font-size: 22px;
}

/* active agency */

.active-agency {
    box-shadow: 0 0 1px rgba(0, 0, 0, .125), 0 1px 3px rgba(0, 0, 0, .2);
    border-radius: .25rem;
    background-image: linear-gradient(to right top, #d7f1e9, #d7f1e9, #d7f1e9, #d7f1e9, #d7f1e9, #c9f1e4, #baf1de, #acf0d7, #8cefc6, #6decb1, #4ce998, #24e57c);
    /* background-image: linear-gradient(to right top, #dbf1eb, #dbf1eb, #dbf1eb, #dbf1eb, #dbf1eb, #d2f1e8, #c9f1e5, #c0f1e1, #acf1d7, #99f0cb, #87efbe, #76eeae); */
    display: -ms-flexbox;
    display: flex;
    margin-bottom: 1rem;
    min-height: 90px;
    padding: .5rem;
    position: relative;
    width: 100%;
}

.active-agency .active-agency-icon {
    border-radius: .25rem;
    -ms-flex-align: center;
    align-items: center;
    display: -ms-flexbox;
    display: flex;
    font-size: 1.875rem;
    -ms-flex-pack: center;
    justify-content: center;
    text-align: center;
    width: 70px;
}

.bg-success,
.bg-success>a {
    color: #fff !important;
}

.bg-success {
    background-color: #05cc61 !important;
}

.elevation-1 {
    box-shadow: 0 1px 3px rgba(0, 0, 0, .12), 0 1px 2px rgba(0, 0, 0, .24) !important;
}

.bg-success {
    background-color: #05cc61 !important;
}


.active-agency .active-agency-content {
    display: -ms-flexbox;
    display: flex;
    -ms-flex-direction: column;
    flex-direction: column;
    -ms-flex-pack: center;
    justify-content: center;
    line-height: 1.5;
    -ms-flex: 1;
    flex: 1;
    padding: 0 30px;
    overflow: hidden;
}

.active-agency .active-agency-text {
    font-size: 19px;
    letter-spacing: normal;
    color: #838587;
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.active-agency .active-agency-number {
    display: block;
    margin-top: .25rem;
    font-weight: 700;
    font-size: 22px;
}

/* Pending */
.pending-agnt {
    box-shadow: 0 0 1px rgba(0, 0, 0, .125), 0 1px 3px rgba(0, 0, 0, .2);
    border-radius: .25rem;
    background-image: linear-gradient(to right top, #eee6e2, #eee6e2, #eee6e2, #eee6e2, #eee6e2, #f0ded6, #f1d7c9, #f2cfbd, #f3bea2, #f3ac88, #f29b6f, #ef8956);
    /* background-image: linear-gradient(to right top, #eee6e2, #eee6e2, #eee6e2, #eee6e2, #eee6e2, #efe2db, #efddd5, #f0d9ce, #f1d0bf, #f2c6b1, #f2bda2, #f1b494); */
    display: -ms-flexbox;
    display: flex;
    margin-bottom: 1rem;
    min-height: 90px;
    padding: .5rem;
    position: relative;
    width: 100%;
}

.pending-agnt .pending-agnt-icon {
    border-radius: .25rem;
    -ms-flex-align: center;
    align-items: center;
    display: -ms-flexbox;
    display: flex;
    font-size: 1.875rem;
    -ms-flex-pack: center;
    justify-content: center;
    text-align: center;
    width: 70px;
}

.bg-warning,
.bg-warning>a {
    color: #fff !important;
}

.bg-warning {
    background-color: #fb8e28 !important;
}

.elevation-1 {
    box-shadow: 0 1px 3px rgba(0, 0, 0, .12), 0 1px 2px rgba(0, 0, 0, .24) !important;
}

.bg-warning {
    background-color: #fb8e28 !important;
}


.pending-agnt .pending-agnt-content {
    display: -ms-flexbox;
    display: flex;
    -ms-flex-direction: column;
    flex-direction: column;
    -ms-flex-pack: center;
    justify-content: center;
    line-height: 1.5;
    -ms-flex: 1;
    flex: 1;
    padding: 0 30px;
    overflow: hidden;
}

.pending-agnt .pending-agnt-text {
    font-size: 19px;
    letter-spacing: normal;
    color: #838587;
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pending-agnt .pending-agnt-number {
    display: block;
    margin-top: .25rem;
    font-weight: 700;
    font-size: 22px;
}


/* On Hold */
.info-box {
    box-shadow: 0 0 1px rgba(0, 0, 0, .125), 0 1px 3px rgba(0, 0, 0, .2);
    border-radius: .25rem;
    background-image: linear-gradient(to right top, #eef1e2, #eef1e2, #eef1e2, #eef1e2, #eef1e2, #ebf0d6, #e9eeca, #e8ecbe, #e7e7a2, #e8e285, #ebdb66, #efd444);
    display: -ms-flexbox;
    display: flex;
    margin-bottom: 1rem;
    min-height: 90px;
    padding: .5rem;
    position: relative;
    width: 100%;
}

.info-box .info-box-icon {
    border-radius: .25rem;
    -ms-flex-align: center;
    align-items: center;
    display: -ms-flexbox;
    display: flex;
    font-size: 1.875rem;
    -ms-flex-pack: center;
    justify-content: center;
    text-align: center;
    width: 70px;
}

.bg-danger,
.bg-danger>a {
    color: #fff !important;
}

.bg-danger {
    background-color: #efb51d !important;
}

.elevation-1 {
    box-shadow: 0 1px 3px rgba(0, 0, 0, .12), 0 1px 2px rgba(0, 0, 0, .24) !important;
}

.bg-danger {
    background-color: #efb51d !important;
}


.info-box .info-box-content {
    display: -ms-flexbox;
    display: flex;
    -ms-flex-direction: column;
    flex-direction: column;
    -ms-flex-pack: center;
    justify-content: center;
    line-height: 1.5;
    -ms-flex: 1;
    flex: 1;
    padding: 0 30px;
    overflow: hidden;
}

.info-box .info-box-text {
    font-size: 19px;
    letter-spacing: normal;
    color: #838587;
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.info-box .info-box-number {
    display: block;
    margin-top: .25rem;
    font-weight: 700;
    font-size: 22px;
}


.btn-outline-only-edit {
    --bs-btn-color: #027de2;
    --bs-btn-border-color: #027de2;
    --bs-btn-hover-color: #fff;
    --bs-btn-hover-bg: #027de2;
    --bs-btn-hover-border-color: #027de2;
    --bs-btn-focus-shadow-rgb: 108, 117, 125;
    --bs-btn-active-color: #fff;
    --bs-btn-active-bg: #027de2;
    --bs-btn-active-border-color: #027de2;
    --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
    --bs-btn-disabled-color: #027de2;
    --bs-btn-disabled-bg: transparent;
    --bs-btn-disabled-border-color: #027de2;
    --bs-gradient: none;
}


.btn-outline-purple {
    --bs-btn-color: #7239ea;
    --bs-btn-border-color: #7239ea;
    --bs-btn-hover-color: #fff;
    --bs-btn-hover-bg: #7239ea;
    --bs-btn-hover-border-color: #7239ea;
    --bs-btn-focus-shadow-rgb: 108, 117, 125;
    --bs-btn-active-color: #fff;
    --bs-btn-active-bg: #7239ea;
    --bs-btn-active-border-color: #7239ea;
    --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
    --bs-btn-disabled-color: #7239ea;
    --bs-btn-disabled-bg: transparent;
    --bs-btn-disabled-border-color: #7239ea;
    --bs-gradient: none;
}

.btn-outline-lock {
    --bs-btn-color: #19c4db;
    --bs-btn-border-color: #19c4db;
    --bs-btn-hover-color: #fff;
    --bs-btn-hover-bg: #19c4db;
    --bs-btn-hover-border-color: #19c4db;
    --bs-btn-focus-shadow-rgb: 108, 117, 125;
    --bs-btn-active-color: #fff;
    --bs-btn-active-bg: #19c4db;
    --bs-btn-active-border-color: #19c4db;
    --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
    --bs-btn-disabled-color: #19c4db;
    --bs-btn-disabled-bg: transparent;
    --bs-btn-disabled-border-color: #19c4db;
    --bs-gradient: none;
}

.btn-outline-timer {
    --bs-btn-color: #1ba3f0;
    --bs-btn-border-color: #1ba3f0;
    --bs-btn-hover-color: #fff;
    --bs-btn-hover-bg: #1ba3f0;
    --bs-btn-hover-border-color: #1ba3f0;
    --bs-btn-focus-shadow-rgb: 108, 117, 125;
    --bs-btn-active-color: #fff;
    --bs-btn-active-bg: #1ba3f0;
    --bs-btn-active-border-color: #1ba3f0;
    --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
    --bs-btn-disabled-color: #1ba3f0;
    --bs-btn-disabled-bg: transparent;
    --bs-btn-disabled-border-color: #1ba3f0;
    --bs-gradient: none;
}

</style>

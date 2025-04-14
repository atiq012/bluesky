<template>
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">


        <div class="breadcrumb-title pe-3"> Deposit Management</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item">
                        <router-link :to="{ name: 'Home' }">Dashboard</router-link>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Deposit List</li>
                </ol>
            </nav>
        </div>
        <div class="ms-auto">
            <div class="btn-group">
                <router-link :to="{ name: 'CreateDeposit' }" class="btn btn-primary btn-sm">
                    <i class="fa fa-circle-plus"></i> New Deposit Request
                </router-link>

            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-12 col-sm-6 col-md-3">
            <div class="info-agency">
                <span class="info-agency-icon bg-info elevation-1"><i class="fas fa-users"></i></span>
                <div class="info-agency-content">
                    <span class="info-agency-text">Total</span>
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
                    <span class="active-agency-text">Approved</span>
                    <span class="active-agency-number">760</span>
                </div>

            </div>

        </div>

        <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box mb-3">
                <span class="info-box-icon bg-danger elevation-1"><i class="fa fa-pause"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Requested</span>
                    <span class="info-box-number">5</span>
                </div>

            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <div class="pending-agnt mb-3">
                <span class="pending-agnt-icon bg-warning elevation-1"><i class="fa fa-clock"></i></span>
                <div class="pending-agnt-content">
                    <span class="pending-agnt-text">Decline</span>
                    <span class="pending-agnt-number">20</span>
                </div>

            </div>

        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card rounded rounded-2 shadow-none p-3">
                <div class="row">
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" id="single-select-field"
                            data-placeholder="Choose one thing">
                            <option>Select Payment</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" id="single-select-field"
                            data-placeholder="Choose one thing">
                            <option>Select Bank</option>
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

    <div class="table-responsive mt-2 border rounded rounded-2 p-3">
        <div id="example2_wrapper" class="dataTables_wrapper dt-bootstrap5">
            <div class="row">
                <div class="col-sm-12 col-md-6">

                    <button class="btn btn-sm btn-danger" style="margin-right: 3px;" tabindex="0"
                        aria-controls="example2" type="button"><i class="fa-solid fa-file-pdf"
                            style="font-size: 14px !important;"></i> <span>PDF</span></button>

                    <button class="btn btn-sm btn-success" style="margin-right: 3px;" tabindex="0"
                        aria-controls="example2" type="button"> <i class="fa-regular fa-file-excel"
                            style="font-size: 14px !important;"></i><span>Excel</span></button>
                </div>
                <div class="col-md-4"></div>
                <div class="col-sm-2 col-md-2">
                    <div id="example2_filter" class="dataTables_filter"><input type="search"
                            class="form-control form-control-sm" placeholder="" aria-controls="example2">
                    </div>
                </div>
            </div>

            <div class="row mt-2">
                <div class="col-sm-12">
                    <table class="table table-sm table-striped table-bordered">
                        <thead>
                            <tr role="row">
                                <th class="sorting_asc" tabindex="0" aria-controls="example2" rowspan="1" colspan="1"
                                    aria-sort="ascending" aria-label="SL: activate to sort column descending"
                                    style="width: 10px;">
                                    SL.</th>
                                <th class="sorting" tabindex="0" aria-controls="example2" rowspan="1" colspan="1"
                                    aria-label="Agency: activate to sort column ascending" style="width: 116.087px;">
                                    Agency</th>

                                <th class="sorting" tabindex="0" aria-controls="example2" rowspan="1" colspan="1"
                                    aria-label="Payment Term: activate to sort column ascending"
                                    style="width: 107.037px;">Payment Term</th>
                                <th class="sorting" tabindex="0" aria-controls="example2" rowspan="1" colspan="1"
                                    aria-label="Bank & Balance: activate to sort column ascending"
                                    style="width: 107.037px;">Bank & Balance</th>
                                <th class="sorting" tabindex="0" aria-controls="example2" rowspan="1" colspan="1"
                                    aria-label="Ref. No: activate to sort column ascending" style="width: 107.037px;">
                                    Ref. No</th>

                                <th class="sorting" tabindex="0" aria-controls="example2" rowspan="1" colspan="1"
                                    aria-label="Total Amount: activate to sort column ascending"
                                    style="width: 107.037px;">Total Amount</th>


                                <th class="sorting" tabindex="0" aria-controls="example2" rowspan="1" colspan="1"
                                    aria-label="Remarks: activate to sort column ascending" style="width: 307.037px;">
                                    Remarks</th>
                                <th class="sorting" tabindex="0" aria-controls="example2" rowspan="1" colspan="1"
                                    aria-label="Requested By: activate to sort column ascending"
                                    style="width: 107.037px;">Requested By</th>

                                <th class="sorting" tabindex="0" aria-controls="example2" rowspan="1" colspan="1"
                                    aria-label="Status: activate to sort column ascending" style="width: 107.037px;">
                                    Status</th>
                                <th class="sorting" tabindex="0" aria-controls="example2" rowspan="1" colspan="1"
                                    aria-label="Action: activate to sort column ascending" style="width: 107.037px;">
                                    Action</th>
                            </tr>
                        </thead>
                        <tbody>

                            <tr role="row">
                                <td class="text-left">01</td>
                                <td class="text-left">ABC Travel Ltd
                                    <br>
                                    <small class="text-blue">
                                        BLU00011
                                    </small>| <small class="text-violate">
                                        IATA
                                    </small>
                                </td>
                                <td class="text-left">Bank</td>
                                <td class="text-left">Bank Asia
                                    <br>
                                    <small class="text-blue">
                                        Gulshan-1
                                    </small>
                                </td>
                                <td class="text-left">101921101
                                    <br>
                                    <small class="text-blue">
                                        25-Sept-2024
                                    </small>
                                </td>

                                <td class="text-left">
                                    <i class="fa-solid fa-bangladeshi-taka-sign"></i> 1000000
                                    <br>
                                    <small class="text-blue">
                                        (-)<i class="fa-solid fa-bangladeshi-taka-sign"></i> 70000
                                    </small>
                                </td>

                                <td class="text-left" id="pre-line">
                                    Air Ticket Reserve
                                </td>
                                <td class="text-left">
                                    Md. Atiqur Rahman
                                    <br>
                                    <small class="text-blue">
                                        25-Sept-2024
                                    </small>
                                </td>
                                <td>
                                    <div
                                        class="badge rounded-pill text-success bg-light-success p-2 text-uppercase px-3">
                                        <i class="bx bxs-circle me-1"></i>Approved
                                    </div>
                                </td>
                                <td>
                                    <button type="button" v-tippy="'Attachment'"
                                        style="size:30px;width:30px;height:30px;margin-left:5px;"
                                        class="btn btn-outline-info rounded-circle"><i class="fa-solid fa-file"
                                            style="margin:2px 0px 10px -4px;font-size:14px;"></i></button>

                                    <button type="button" v-tippy="'Log'"
                                        style="size:30px;width:30px;height:30px;margin-left:5px;"
                                        class="btn btn-outline-action-log rounded-circle"><i
                                            class="fa-solid fa-arrow-trend-up"
                                            style="margin:2px 0px 10px -6px;font-size:14px;"></i></button>
                                </td>
                            </tr>

                            <tr role="even">
                                <td class="text-left">02</td>
                                <td class="text-left">Zaima Travel Ltd
                                    <br>
                                    <small class="text-blue">
                                        BLU00012
                                    </small>| <small class="text-violate">
                                        Hajj
                                    </small>
                                </td>
                                <td class="text-left">Cash</td>
                                <td class="text-left">Cash Counter-BS
                                    <br>
                                    <small class="text-blue">
                                        Gulshan-1
                                    </small>
                                </td>
                                <td class="text-left">9001921101
                                    <br>
                                    <small class="text-blue">
                                        23-Sept-2024
                                    </small>
                                </td>

                                <td class="text-left">
                                    <i class="fa-solid fa-bangladeshi-taka-sign"></i> 109000

                                </td>

                                <td class="text-left" id="pre-line">
                                    Air Ticket Booking
                                </td>
                                <td class="text-left">
                                    Md. Sajjadul Anam
                                    <br>
                                    <small class="text-blue">
                                        23-Sept-2024
                                    </small>
                                </td>
                                <td>
                                    <div
                                        class="badge rounded-pill text-warning bg-light-warning p-2 text-uppercase px-3">
                                        <i class="bx bxs-circle me-1"></i>Requested
                                    </div>
                                </td>
                                <td>
                                    <button type="button" v-tippy="'Attachment'"
                                        style="size:30px;width:30px;height:30px;"
                                        class="btn btn-outline-info rounded-circle"><i class="fa-solid fa-file"
                                            style="margin:2px 0px 10px -4px;font-size:14px;"></i></button>
                                    <router-link :to="{ name: 'depoDetails' }" v-tippy="'Aproval'"
                                        style="size: 30px; width: 30px; height: 30px; margin-left:5px;"
                                        class="btn btn-outline-success rounded-circle" placement="top">
                                        <i class="fa fa-check" style="margin: 2px 0px 10px -4px; font-size: 14px;"></i>
                                    </router-link>

                                    <button type="button" v-tippy="'Log'"
                                        style="size:30px;width:30px;height:30px;margin-left:5px;"
                                        class="btn btn-outline-danger rounded-circle"><i class="fa fa-ban"
                                            style="margin:2px 0px 10px -5px;font-size:14px;"></i></button>
                                </td>
                            </tr>

                            <tr>
                                <td class="text-left">02</td>
                                <td class="text-left">DDD Travel Ltd
                                    <br>
                                    <small class="text-blue">
                                        BLU00014
                                    </small>| <small class="text-violate">
                                        IATA
                                    </small>
                                </td>
                                <td class="text-left">Bank</td>
                                <td class="text-left">City Bank
                                    <br>
                                    <small class="text-blue">
                                        Gulshan-2
                                    </small>
                                </td>
                                <td class="text-left">64019221101
                                    <br>
                                    <small class="text-blue">
                                        23-Sept-2024
                                    </small>
                                </td>

                                <td class="text-left">
                                    <i class="fa-solid fa-bangladeshi-taka-sign"></i> 1119000

                                </td>

                                <td class="text-left" id="pre-line">
                                    Air Ticket Booking
                                </td>
                                <td class="text-left">
                                    Md. Masiur Alam
                                    <br>
                                    <small class="text-blue">
                                        23-Sept-2024
                                    </small>
                                </td>
                                <td>
                                    <div class="badge rounded-pill text-danger bg-light-danger p-2 text-uppercase px-3">
                                        <i class="bx bxs-circle me-1"></i>Rejected
                                    </div>
                                </td>
                                <td>
                                    <button type="button" v-tippy="'Attachment'"
                                        style="size:30px;width:30px;height:30px;"
                                        class="btn btn-outline-info rounded-circle"><i class="fa-solid fa-file"
                                            style="margin:2px 0px 10px -4px;font-size:14px;"></i></button>

                                    <button type="button" title="Log" class="btn btn-outline-action-log rounded-circle"
                                        v-tippy="'Log'"
                                        style="size: 30px; width: 30px; height: 30px; margin-left: 5px;"><i
                                            class="fa-solid fa-arrow-trend-up"
                                            style="margin: 2px 0px 10px -6px; font-size: 14px;"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="row">
                <div class="col-sm-12 col-md-5">
                    <div class="dataTables_info" id="example2_info" role="status" aria-live="polite">Showing 1
                        to 5 of 10 entries</div>
                </div>
                <div class="col-sm-12 col-md-7">
                    <div class="dataTables_paginate paging_simple_numbers" id="example2_paginate">
                        <ul class="pagination">
                            <li class="paginate_button page-item previous disabled" id="example2_previous"><a href="#"
                                    aria-controls="example2" data-dt-idx="0" tabindex="0" class="page-link">Prev</a>
                            </li>
                            <li class="paginate_button page-item active"><a href="#" aria-controls="example2"
                                    data-dt-idx="1" tabindex="0" class="page-link">1</a></li>
                            <li class="paginate_button page-item "><a href="#" aria-controls="example2" data-dt-idx="2"
                                    tabindex="0" class="page-link">2</a></li>

                            <li class="paginate_button page-item next" id="example2_next"><a href="#"
                                    aria-controls="example2" data-dt-idx="7" tabindex="0" class="page-link">Next</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

        </div>
    </div>

</template>
<script>

</script>
<style>
.text-blue {
    color: blue;
}

.text-violate {
    color: #cb00fce0;
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

[data-bs-theme=dark] body .active-agency {
    box-shadow: 0 0 1px rgba(0, 0, 0, .125), 0 1px 3px rgba(0, 0, 0, .2);
    background-color: #343a40;
    border-radius: .25rem;
    display: -ms-flexbox;
    display: flex;
    margin-bottom: 1rem;
    min-height: 90px;
    padding: .5rem;
    position: relative;
    width: 100%;

}


[data-bs-theme=dark] body .bg-success,
.active-agency-icon,
.bg-success>a {
    background-color: #5b9a59 !important;
    color: #9fbe9e !important;
}

[data-bs-theme=light] body .bg-success,
.active-agency-icon,
.bg-success>a {
    background-color: #0ea209 !important;
    color: #fff !important;

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

.odd td {
    background-color: #F5F8FA;
}

.even td {
    background-color: #fff;
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

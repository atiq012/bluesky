<template>
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3"> Deposit Management</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item">
                        <router-link :to="{ name: 'Home' }">Dashboard</router-link>
                    </li>

                    <li class="breadcrumb-item active" aria-current="page">

                        <router-link :to="{ name: 'depositList' }">Deposit Management</router-link>

                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        New Deposit Request
                    </li>

                </ol>
            </nav>
        </div>

    </div>

    <div class="row position-relative">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <ul class="nav nav-tabs nav-primary mb-0" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link active" data-bs-toggle="tab" href="#cash_tab" role="tab"
                                aria-selected="true">
                                <div class="d-flex align-items-center">
                                    <div class="tab-icon">
                                    </div>
                                    <div class="tab-title"> Cash </div>
                                </div>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" data-bs-toggle="tab" href="#mfs_tab" role="tab" aria-selected="false"
                                tabindex="-1">
                                <div class="d-flex align-items-center">
                                    <div class="tab-icon">
                                    </div>
                                    <div class="tab-title">MFS</div>
                                </div>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" data-bs-toggle="tab" href="#cheque_tab" role="tab" aria-selected="false"
                                tabindex="-1">
                                <div class="d-flex align-items-center">
                                    <div class="tab-icon">
                                    </div>
                                    <div class="tab-title">Cheque/DD</div>
                                </div>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" data-bs-toggle="tab" href="#bank_transfer_tab" role="tab"
                                aria-selected="false" tabindex="-1">
                                <div class="d-flex align-items-center">
                                    <div class="tab-icon">
                                    </div>
                                    <div class="tab-title">Bank Transfer</div>
                                </div>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" data-bs-toggle="tab" href="#credit_req_tab" role="tab"
                                aria-selected="false" tabindex="-1">
                                <div class="d-flex align-items-center">
                                    <div class="tab-icon">
                                    </div>
                                    <div class="tab-title">Credit Request</div>
                                </div>
                            </a>
                        </li>
                    </ul>
                    <div class="tab-content pt-3">
                        <div class="tab-pane fade active show" id="cash_tab" role="tabpanel">
                            <div class="row">
                                <div class="col-md-9">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="m-0 p-0" style="border-left: 5px solid rgb(114, 57, 234);">
                                                &nbsp; Payment Information</h5>
                                        </div>
                                        <form id="">
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-10">
                                                        <label for="input1" class="form-label">Payment Account</label>
                                                        <select id="payment_acc" name="payment_acc"
                                                            class="form-control form-control-sm">
                                                            <option selected="" value="">Choose...</option>
                                                            <option value="Bank">Bank</option>
                                                            <option value="MFS">MFS</option>
                                                        </select>
                                                    </div>

                                                    <div class="col-md-4 mt-2">
                                                        <label for="input1" class="form-label">Request Amount</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                            id="requested_amount" name="requested_amount"
                                                            placeholder="Enter Routing Number">
                                                    </div>
                                                    <div class="col-md-3 mt-2">
                                                        <label for="input1" class="form-label">
                                                            Charge</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                            id="service_charge" name="service_charge"
                                                            placeholder="Enter Service Charge">
                                                    </div>
                                                    <div class="col-md-3 mt-2">
                                                        <label for="input1" class="form-label">
                                                            Total Amount</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                            id="total_amount" name="total_amount"
                                                            placeholder="Enter Service Charge">
                                                    </div>
                                                </div>
                                            </div>

                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-9">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="m-0 p-0" style="border-left: 5px solid rgb(114, 57, 234);">
                                                &nbsp; Cash Information</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label for="input1" class="form-label">
                                                        Referece Number</label>
                                                    <input type="text" class="form-control form-control-sm"
                                                        id="reference_number" name="reference_number"
                                                        placeholder="Enter Service Charge">
                                                </div>
                                                <div class="col-md-6 ">
                                                    <label for="input1" class="form-label">
                                                        Referece Number</label>
                                                    <input type="date" class="form-control form-control-sm"
                                                        id="reference_date" name="reference_date"
                                                        placeholder="Enter Service Charge">
                                                </div>
                                                <div class="col-md-6 mt-2">
                                                    <label for="input1" class="form-label">
                                                        Referece file</label>
                                                    <input type="file" class="form-control form-control-sm"
                                                        id="reference_file" name="reference_file"
                                                        placeholder="Enter Service Charge">
                                                </div>

                                                <div class="col-md-6 mt-2">
                                                    <label for="input1" class="form-label">
                                                        Remarks</label>

                                                    <textarea class="form-control form-control-sm" id="remarks"
                                                        name="remarks" rows="3"></textarea>

                                                </div>
                                                <div class="col-md-6">

                                                    <div class="d-flex align-items-center "><img
                                                            src="../../../../../public/theme/appimages/rqf.png"
                                                            height="60" width="60"
                                                            class="border border-1 rounded rounded-2"
                                                            alt="Profile Picture">
                                                        <div class="flex-grow-1 ms-3">
                                                            <p class="mb-0"><i
                                                                    class="btn-outline-success rounded-circle fa fa-circle-check"></i>
                                                                Uploaded successfully.</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-9">
                                    <div class="d-flex ">
                                        <button class="btn btn-sm btn-info px-4 ms-2 justify-content-start">Cancel</button>
                                        <button class="btn btn-sm btn-info px-4 ms-2 justify-content-end">Submit</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="mfs_tab" role="tabpanel">
                            <div class="row">
                                <div class="col-md-9">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="m-0 p-0" style="border-left: 5px solid rgb(114, 57, 234);">
                                                &nbsp; Payment Information</h5>
                                        </div>
                                        <form id="">
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-10">
                                                        <label for="input1" class="form-label">Payment Account</label>
                                                        <select id="payment_acc" name="payment_acc"
                                                            class="form-control form-control-sm">
                                                            <option selected="" value="">Choose...</option>
                                                            <option value="Bank">Bank</option>
                                                            <option value="MFS">MFS</option>
                                                        </select>
                                                    </div>

                                                    <div class="col-md-4 mt-2">
                                                        <label for="input1" class="form-label">Request Amount</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                            id="requested_amount" name="requested_amount"
                                                            placeholder="Enter Routing Number">
                                                    </div>
                                                    <div class="col-md-3 mt-2">
                                                        <label for="input1" class="form-label">
                                                            Charge</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                            id="service_charge" name="service_charge"
                                                            placeholder="Enter Service Charge">
                                                    </div>
                                                    <div class="col-md-3 mt-2">
                                                        <label for="input1" class="form-label">
                                                            Total Amount</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                            id="total_amount" name="total_amount"
                                                            placeholder="Enter Service Charge">
                                                    </div>
                                                </div>
                                            </div>

                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-9">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="m-0 p-0" style="border-left: 5px solid rgb(114, 57, 234);">
                                                &nbsp; MFS Information</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label for="input1" class="form-label">
                                                        Issued Bank & MFS</label>
                                                    <select name="issued_bank" id="" class="form-control">
                                                        <option value="">Select Issued Bank</option>
                                                        <option value="1">AB Bank</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6 mt-2">
                                                    <label for="input1" class="form-label">
                                                        Referece Number</label>
                                                    <input type="input" class="form-control form-control-sm"
                                                        id="reference_num" name="reference_num"
                                                        placeholder="Enter Reference Number">
                                                </div>
                                                <div class="col-md-6 mt-2">
                                                    <label for="input1" class="form-label">
                                                        Referece file</label>
                                                    <input type="file" class="form-control form-control-sm"
                                                        id="reference_file" name="reference_file"
                                                        placeholder="Enter ">
                                                </div>

                                                <div class="col-md-6 mt-2">
                                                    <label for="input1" class="form-label">
                                                        Referece file</label>
                                                    <input type="file" class="form-control form-control-sm"
                                                        id="reference_file" name="reference_file"
                                                        placeholder="Enter Service Charge">
                                                </div>

                                                <div class="col-md-6 mt-2">
                                                    <label for="input1" class="form-label">
                                                        Remarks</label>

                                                    <textarea class="form-control form-control-sm" id="remarks"
                                                        name="remarks" rows="3"></textarea>

                                                </div>
                                                <div class="col-md-6 mt-3">

                                                    <div class="d-flex align-items-center "><img
                                                            src="../../../../../public/theme/appimages/rqf.png"
                                                            height="60" width="60"
                                                            class="border border-1 rounded rounded-2"
                                                            alt="Profile Picture">
                                                        <div class="flex-grow-1 ms-3">
                                                            <p class="mb-0"><i
                                                                    class="btn-outline-success rounded-circle fa fa-circle-check"></i>
                                                                Uploaded successfully.</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-9">
                                    <div class="d-flex ">
                                        <button class="btn btn-sm btn-info px-4 ms-2 justify-content-start">Cancel</button>
                                        <button class="btn btn-sm btn-info px-4 ms-2 justify-content-end">Submit</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="cheque_tab" role="tabpanel">
                            <div class="row">
                                <div class="col-md-9">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="m-0 p-0" style="border-left: 5px solid rgb(114, 57, 234);">
                                                &nbsp; Payment Information</h5>
                                        </div>
                                        <form id="">
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-10">
                                                        <label for="input1" class="form-label">Payment Account</label>
                                                        <select id="payment_acc" name="payment_acc"
                                                            class="form-control form-control-sm">
                                                            <option selected="" value="">Choose...</option>
                                                            <option value="Bank">Bank</option>
                                                            <option value="MFS">MFS</option>
                                                        </select>
                                                    </div>

                                                    <div class="col-md-4 mt-2">
                                                        <label for="input1" class="form-label">Request Amount</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                            id="requested_amount" name="requested_amount"
                                                            placeholder="Enter Routing Number">
                                                    </div>
                                                    <div class="col-md-3 mt-2">
                                                        <label for="input1" class="form-label">
                                                            Charge</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                            id="service_charge" name="service_charge"
                                                            placeholder="Enter Service Charge">
                                                    </div>
                                                    <div class="col-md-3 mt-2">
                                                        <label for="input1" class="form-label">
                                                            Total Amount</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                            id="total_amount" name="total_amount"
                                                            placeholder="Enter Service Charge">
                                                    </div>
                                                </div>
                                            </div>

                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-9">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="m-0 p-0" style="border-left: 5px solid rgb(114, 57, 234);">
                                                &nbsp; Cheque/DD Information</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label for="input1" class="form-label">
                                                        Issued Bank & MFS</label>
                                                    <select name="issued_bank" id="" class="form-control">
                                                        <option value="">Select Issued Bank</option>
                                                        <option value="1">AB Bank</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6 mt-2">
                                                    <label for="input1" class="form-label">
                                                        Referece Number</label>
                                                    <input type="input" class="form-control form-control-sm"
                                                        id="reference_num" name="reference_num"
                                                        placeholder="Enter Reference Number">
                                                </div>
                                                <div class="col-md-6 mt-2">
                                                    <label for="input1" class="form-label">
                                                        Referece file</label>
                                                    <input type="file" class="form-control form-control-sm"
                                                        id="reference_file" name="reference_file"
                                                        placeholder="Enter ">
                                                </div>

                                                <div class="col-md-6 mt-2">
                                                    <label for="input1" class="form-label">
                                                        Referece file</label>
                                                    <input type="file" class="form-control form-control-sm"
                                                        id="reference_file" name="reference_file"
                                                        placeholder="Enter Service Charge">
                                                </div>

                                                <div class="col-md-6 mt-2">
                                                    <label for="input1" class="form-label">
                                                        Remarks</label>

                                                    <textarea class="form-control form-control-sm" id="remarks"
                                                        name="remarks" rows="3"></textarea>

                                                </div>
                                                <div class="col-md-6 mt-3">

                                                    <div class="d-flex align-items-center "><img
                                                            src="../../../../../public/theme/appimages/rqf.png"
                                                            height="60" width="60"
                                                            class="border border-1 rounded rounded-2"
                                                            alt="Profile Picture">
                                                        <div class="flex-grow-1 ms-3">
                                                            <p class="mb-0"><i
                                                                    class="btn-outline-success rounded-circle fa fa-circle-check"></i>
                                                                Uploaded successfully.</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-9">
                                    <div class="d-flex ">
                                        <button class="btn btn-sm btn-info px-4 ms-2 justify-content-start">Cancel</button>
                                        <button class="btn btn-sm btn-info px-4 ms-2 justify-content-end">Submit</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="bank_transfer_tab" role="tabpanel">
                            <div class="row">
                                <div class="col-md-9">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="m-0 p-0" style="border-left: 5px solid rgb(114, 57, 234);">
                                                &nbsp; Payment Information</h5>
                                        </div>
                                        <form id="">
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-10">
                                                        <label for="input1" class="form-label">Payment Account</label>
                                                        <select id="payment_acc" name="payment_acc"
                                                            class="form-control form-control-sm">
                                                            <option selected="" value="">Choose...</option>
                                                            <option value="Bank">Bank</option>
                                                            <option value="MFS">MFS</option>
                                                        </select>
                                                    </div>

                                                    <div class="col-md-4 mt-2">
                                                        <label for="input1" class="form-label">Request Amount</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                            id="requested_amount" name="requested_amount"
                                                            placeholder="Enter Routing Number">
                                                    </div>
                                                    <div class="col-md-3 mt-2">
                                                        <label for="input1" class="form-label">
                                                            Charge</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                            id="service_charge" name="service_charge"
                                                            placeholder="Enter Service Charge">
                                                    </div>
                                                    <div class="col-md-3 mt-2">
                                                        <label for="input1" class="form-label">
                                                            Total Amount</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                            id="total_amount" name="total_amount"
                                                            placeholder="Enter Service Charge">
                                                    </div>
                                                </div>
                                            </div>

                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-9">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="m-0 p-0" style="border-left: 5px solid rgb(114, 57, 234);">
                                                &nbsp; Bank Transfer Information</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label for="input1" class="form-label">
                                                        Issued Bank & MFS</label>
                                                    <select name="issued_bank" id="" class="form-control">
                                                        <option value="">Select Issued Bank</option>
                                                        <option value="1">AB Bank</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6 mt-2">
                                                    <label for="input1" class="form-label">
                                                        Referece Number</label>
                                                    <input type="input" class="form-control form-control-sm"
                                                        id="reference_num" name="reference_num"
                                                        placeholder="Enter Reference Number">
                                                </div>
                                                <div class="col-md-6 mt-2">
                                                    <label for="input1" class="form-label">
                                                        Referece file</label>
                                                    <input type="file" class="form-control form-control-sm"
                                                        id="reference_file" name="reference_file"
                                                        placeholder="Enter ">
                                                </div>

                                                <div class="col-md-6 mt-2">
                                                    <label for="input1" class="form-label">
                                                        Referece file</label>
                                                    <input type="file" class="form-control form-control-sm"
                                                        id="reference_file" name="reference_file"
                                                        placeholder="Enter Service Charge">
                                                </div>

                                                <div class="col-md-6 mt-2">
                                                    <label for="input1" class="form-label">
                                                        Remarks</label>

                                                    <textarea class="form-control form-control-sm" id="remarks"
                                                        name="remarks" rows="3"></textarea>

                                                </div>
                                                <div class="col-md-6 mt-3">

                                                    <div class="d-flex align-items-center "><img
                                                            src="../../../../../public/theme/appimages/rqf.png"
                                                            height="60" width="60"
                                                            class="border border-1 rounded rounded-2"
                                                            alt="Profile Picture">
                                                        <div class="flex-grow-1 ms-3">
                                                            <p class="mb-0"><i
                                                                    class="btn-outline-success rounded-circle fa fa-circle-check"></i>
                                                                Uploaded successfully.</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-9">
                                    <div class="d-flex ">
                                        <button class="btn btn-sm btn-info px-4 ms-2 justify-content-start">Cancel</button>
                                        <button class="btn btn-sm btn-info px-4 ms-2 justify-content-end">Submit</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="credit_req_tab" role="tabpanel">
                            <div class="row">
                                <div class="col-md-9">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="m-0 p-0" style="border-left: 5px solid rgb(114, 57, 234);">
                                                &nbsp; Payment Information</h5>
                                        </div>
                                        <form id="">
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-10">
                                                        <label for="input1" class="form-label">Payment Account</label>
                                                        <select id="payment_acc" name="payment_acc"
                                                            class="form-control form-control-sm">
                                                            <option selected="" value="">Choose...</option>
                                                            <option value="Bank">Bank</option>
                                                            <option value="MFS">MFS</option>
                                                        </select>
                                                    </div>

                                                    <div class="col-md-4 mt-2">
                                                        <label for="input1" class="form-label">Request Amount</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                            id="requested_amount" name="requested_amount"
                                                            placeholder="Enter Routing Number">
                                                    </div>
                                                    <div class="col-md-3 mt-2">
                                                        <label for="input1" class="form-label">
                                                            Charge</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                            id="service_charge" name="service_charge"
                                                            placeholder="Enter Service Charge">
                                                    </div>
                                                    <div class="col-md-3 mt-2">
                                                        <label for="input1" class="form-label">
                                                            Total Amount</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                            id="total_amount" name="total_amount"
                                                            placeholder="Enter Service Charge">
                                                    </div>
                                                </div>
                                            </div>

                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-9">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="m-0 p-0" style="border-left: 5px solid rgb(114, 57, 234);">
                                                &nbsp; Credit Request Information</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label for="input1" class="form-label">
                                                        Issued Bank & MFS</label>
                                                    <select name="issued_bank" id="" class="form-control">
                                                        <option value="">Select Issued Bank</option>
                                                        <option value="1">AB Bank</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6 mt-2">
                                                    <label for="input1" class="form-label">
                                                        Referece Number</label>
                                                    <input type="input" class="form-control form-control-sm"
                                                        id="reference_num" name="reference_num"
                                                        placeholder="Enter Reference Number">
                                                </div>
                                                <div class="col-md-6 mt-2">
                                                    <label for="input1" class="form-label">
                                                        Referece file</label>
                                                    <input type="file" class="form-control form-control-sm"
                                                        id="reference_file" name="reference_file"
                                                        placeholder="Enter ">
                                                </div>

                                                <div class="col-md-6 mt-2">
                                                    <label for="input1" class="form-label">
                                                        Referece file</label>
                                                    <input type="file" class="form-control form-control-sm"
                                                        id="reference_file" name="reference_file"
                                                        placeholder="Enter Service Charge">
                                                </div>

                                                <div class="col-md-6 mt-2">
                                                    <label for="input1" class="form-label">
                                                        Remarks</label>

                                                    <textarea class="form-control form-control-sm" id="remarks"
                                                        name="remarks" rows="3"></textarea>

                                                </div>
                                                <div class="col-md-6 mt-3">

                                                    <div class="d-flex align-items-center "><img
                                                            src="../../../../../public/theme/appimages/rqf.png"
                                                            height="60" width="60"
                                                            class="border border-1 rounded rounded-2"
                                                            alt="Profile Picture">
                                                        <div class="flex-grow-1 ms-3">
                                                            <p class="mb-0"><i
                                                                    class="btn-outline-success rounded-circle fa fa-circle-check"></i>
                                                                Uploaded successfully.</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-9">
                                    <div class="d-flex ">
                                        <button class="btn btn-sm btn-info px-4 ms-2 justify-content-start">Cancel</button>
                                        <button class="btn btn-sm btn-info px-4 ms-2 justify-content-end">Submit</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

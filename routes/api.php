<?php

use App\Http\Controllers\Admin\Agent\AgentController;
use App\Http\Controllers\Admin\AircraftType\AircraftTypeDesignatorController;
use App\Http\Controllers\Admin\AirlineLogo\AirlineLogoController;
use App\Http\Controllers\Admin\API\APIController;
use App\Http\Controllers\Admin\API\SearchV2Controller;
use App\Http\Controllers\Admin\API\PriceV2Controller;
use App\Http\Controllers\Admin\API\TravelportFareRulesController;
use App\Http\Controllers\Admin\API\TpV2ReservationController;
use App\Http\Controllers\Admin\API\TpV2AncillaryController;
use App\Http\Controllers\Admin\API\ReservationPaxController;
use App\Http\Controllers\Admin\API\BookingAttemptController;
use App\Http\Controllers\Admin\API\BookingAttemptAdminController;
use App\Http\Controllers\Admin\API\TpV2PreCommitController;
use App\Http\Controllers\Admin\API\TpV2TicketController;
use App\Http\Controllers\Admin\API\TpV2CancelController;
use App\Http\Controllers\Admin\API\TpV2VoidController;
use App\Http\Controllers\Admin\API\BookingActivityLogController;
use App\Http\Controllers\Admin\ApiManagement\APIManagementController;
use App\Http\Controllers\Admin\Area\AreaController;
use App\Http\Controllers\Admin\Department\DepartmentController;
use App\Http\Controllers\Admin\Deposit\DepositController;
use App\Http\Controllers\Admin\Designation\DesignationController;
use App\Http\Controllers\Admin\IssuedBankMFS\IssuedBankMFSController;
use App\Http\Controllers\Admin\OfficeLocation\LocationController;
use App\Http\Controllers\Admin\PaymentAccount\PaymentAccountSController;
use App\Http\Controllers\Admin\Role\RolePermissionController;
use App\Http\Controllers\Admin\Traveler\TravelerController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login'])->name('login');
Route::post('register', [AuthController::class, 'register'])->name('register');
Route::post('sendResetLinkEmail', [AuthController::class, 'sendResetLinkEmail'])->name('sendResetLinkEmail');
Route::post('PassReset', [AuthController::class, 'resetPassword'])->name('password.reset');

Route::get('airports', [AreaController::class, 'airports']);

Route::get('/migrate', function () {Artisan::call('migrate:refresh');return Artisan::output();})->name('migrate');

Route::middleware(['auth:api'])->group(function () {
    Route::get('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('refresh', [AuthController::class, 'refresh'])->name('refresh');
    Route::get('me', [AuthController::class, 'me'])->name('me');
    Route::post('registerOTP', [AuthController::class, 'registerOTP'])->name('registerOTP');
    Route::post('ForcePassReset', [AuthController::class, 'ForcePassReset'])->name('ForcePassReset');

    //agent section
    Route::get('getKam', [AgentController::class, 'getKam']);
    Route::get('getAgent', [AgentController::class, 'index'])->name('agent.index');
    Route::post('/agent/save', [AgentController::class, 'store']);
    Route::post('/viewAgent', [AgentController::class, 'viewAgent'])->name('agent.viewAgent');
    Route::post('/recommendedAgent', [AgentController::class, 'recommendedAgentDetails'])->name('agent.recommendedAgentDetails');
    Route::post('/AgentAllImage', [AgentController::class, 'AgentAllImage'])->name('agent.AgentAllImage');
    Route::post('/getAgentApprovalLog', [AgentController::class, 'getAgentApprovalLog'])->name('agent.getAgentApprovalLog');
    Route::post('/agentRecomendation/update', [AgentController::class, 'agentRecomendation'])->name('agent.agentRecomendation');

    Route::post('/agentApproval/update', [AgentController::class, 'agentApproval'])->name('agent.agentApproval');

    // area
    Route::get('getarea', [AreaController::class, 'index'])->name('area.index');
    Route::get('divisions', [AreaController::class, 'divisionsList']);
    Route::post('districts', [AreaController::class, 'districtList']);

    Route::post('/zone/save', [AreaController::class, 'store']);
    Route::post('/zone/update', [AreaController::class, 'update']);
    Route::post('editArea', [AreaController::class, 'edit']);
    Route::post('changeAreaStatus', [AreaController::class, 'changeAreaStatus']);
    Route::post('deletearea', [AreaController::class, 'destroy']);

    //role-permission
    Route::get('getroles', [RolePermissionController::class, 'index'])->name('roles.index');
    Route::post('/role/save', [RolePermissionController::class, 'roleSave']);
    Route::post('/role/update', [RolePermissionController::class, 'update']);
    Route::post('editRole', [RolePermissionController::class, 'edit'])->name('roles.edit');
    Route::post('getPermissionList', [RolePermissionController::class, 'getPermissionList'])->name('roles.getPermissionList');
    Route::post('/changeRoleStatus', [RolePermissionController::class, 'changeRoleStatus'])->name('roles.changeRoleStatus');
    Route::post('/deleteRole', [RolePermissionController::class, 'destroy'])->name('roles.deleteRole');
    //dropdown
    Route::get('getAllRoles', [RolePermissionController::class, 'getAllRoles']);

    // department
    Route::get('getdept', [DepartmentController::class, 'index'])->name('dept.getdept');
    Route::post('/dept/save', [DepartmentController::class, 'store']);
    Route::post('editDept', [DepartmentController::class, 'edit']);
    Route::post('/dept/update', [DepartmentController::class, 'update']);
    Route::post('changeDepartmentStatus', [DepartmentController::class, 'changeDepartmentStatus']);
    Route::post('deleteDept', [DepartmentController::class, 'destroy']);
    //dropdown
    Route::get('getAllDept', [DepartmentController::class, 'getAllDept']);

    //designtaion
    Route::get('getDesignation', [DesignationController::class, 'index'])->name('deg.getDesignation');
    Route::get('designationlog', [DesignationController::class, 'designationlog'])->name('designationlog');
    Route::post('/Designation/save', [DesignationController::class, 'store']);
    Route::post('editDesignation', [DesignationController::class, 'edit']);
    Route::post('/Designation/update', [DesignationController::class, 'update']);
    Route::post('changeDesgStatus', [DesignationController::class, 'changeDesignationStatus']);
    Route::post('deleteDesignation', [DesignationController::class, 'destroy']);
    //dropdown
    Route::get('getAllDesign', [DesignationController::class, 'getAllDesign']);

    // office location
    Route::get('getOfficeLocation', [LocationController::class, 'index'])->name('officeLocation.officelocations');
    Route::post('/loc/save', [LocationController::class, 'store']);
    Route::post('editOffLoc', [LocationController::class, 'edit']);
    Route::post('/office/location/update', [LocationController::class, 'update']);
    Route::post('changeOffLocStatus', [LocationController::class, 'changeOffLocStatus']);
    Route::post('deleteOfficeLocation', [LocationController::class, 'destroy']);
    //dropdown
    Route::get('getAllOffLoc', [LocationController::class, 'getAllOffLoc']);

    //IssuedBankMFSController
    Route::get('getBankMFS', [IssuedBankMFSController::class, 'index'])->name('settings.deposit.BankMFS');
    Route::post('/bankMfs/save', [IssuedBankMFSController::class, 'save'])->name('settings.deposit.bankMfsSave');
    Route::post('/editBankMfs', [IssuedBankMFSController::class, 'edit'])->name('settings.deposit.bankMfsEdit');
    Route::post('/bankormfs/update', [IssuedBankMFSController::class, 'update'])->name('settings.deposit.bankMfsUpdate');
    Route::post('changeIssuedBankStatus', [IssuedBankMFSController::class, 'changeIssuedBankStatus']);
    Route::post('deleteBankMFS', [IssuedBankMFSController::class, 'deleteBankMFS']);

    //payment account
    Route::get('getPaymentAcct', [PaymentAccountSController::class, 'index'])->name('settings.deposit.getPaymentAcct');
    Route::post('/paymentAcct/save', [PaymentAccountSController::class, 'store'])->name('settings.deposit.paymentAcctStore');
    Route::post('/changePaymentAcctStatus', [PaymentAccountSController::class, 'changePaymentAcctStatus'])->name('settings.deposit.changePaymentAcctStatus');
    Route::post('/deletePaymentAcct', [PaymentAccountSController::class, 'destroy'])->name('settings.deposit.destroy');
    Route::post('/editPaymentAcct', [PaymentAccountSController::class, 'edit'])->name('settings.deposit.editPaymentAcct');
    Route::post('/payment-acct/update', [PaymentAccountSController::class, 'update'])->name('settings.deposit.updatePaymentAcct');
    Route::get('getAllPaymentAccount', [PaymentAccountSController::class, 'getAllPaymentAccount'])->name('settings.deposit.getAllPaymentAccount');

    // AircraftTypeDesignator
    Route::get('getAircraftTypeDesignator', [AircraftTypeDesignatorController::class, 'index'])->name('settings.aircraft.getAircraftTypeDesignator');
    Route::post('/AircraftType/save', [AircraftTypeDesignatorController::class, 'store'])->name('settings.aircraft.store');
    Route::post('/editAircraft', [AircraftTypeDesignatorController::class, 'edit'])->name('settings.aircraft.edit');
    Route::post('/AircraftType/update', [AircraftTypeDesignatorController::class, 'update'])->name('settings.aircraft.update');
    Route::post('/deleteAircraft', [AircraftTypeDesignatorController::class, 'destroy'])->name('settings.aircraft.destroy');

    //airlines
    Route::get('getAirlines', [AirlineLogoController::class, 'index'])->name('settings.airlines.getAirlines');
    Route::post('/airlines/update', [AirlineLogoController::class, 'update'])->name('settings.airlines.update');
    Route::post('/editAirlines', [AirlineLogoController::class, 'edit'])->name('settings.airlines.edit');

    Route::post('/Airlines/save', [AirlineLogoController::class, 'store'])->name('settings.airlines.store');
    Route::post('/deleteAirlines', [AirlineLogoController::class, 'destroy'])->name('settings.airlines.destroy');

    //users managemnt
    Route::get('getExternalUsers', [UserController::class, 'index'])->name('user.getExternalUsers');
    Route::get('getAllUsers', [UserController::class, 'getAllUsers'])->name('user.getAllUsers');
    Route::post('/internal-user/save', [UserController::class, 'store'])->name('user.store');
    Route::post('/editUser', [UserController::class, 'edit'])->name('user.editUser');
    Route::post('/user-details/update', [UserController::class, 'update'])->name('user.update');
    Route::post('/deleteUser', [UserController::class, 'destroy'])->name('user.deleteUser');
    Route::post('/user-status/update', [UserController::class, 'statusUpdate'])->name('user.statusUpdate');

    //agets wise extrenal users
    Route::get('getAgentExternalUsers', [UserController::class, 'getAgentExternalUsers'])->name('user.getAgentExternalUsers');
    Route::post('/agent-external-user/save', [UserController::class, 'agntUserstore'])->name('user.agntUserstore');

    // traveler section
    Route::get('getTraveler', [TravelerController::class, 'index'])->name('traveler.getTraveler');
    Route::post('/traveler/data/save', [TravelerController::class, 'store'])->name('traveler.store');
    Route::post('/viewTraveler', [TravelerController::class, 'viewData'])->name('traveler.viewData');
    Route::post('/deleteTraveler', [TravelerController::class, 'destroy'])->name('traveler.destroy');
    Route::post('/traveler/data/update', [TravelerController::class, 'update'])->name('traveler.update');
    Route::post('get-travelers-data-by-search', [TravelerController::class, 'search'])->name('traveler.search');

    // deposit section
    Route::get('getDeposit', [DepositController::class, 'index'])->name('deposit.getDeposit');
    Route::post('/deposit/save', [DepositController::class, 'store'])->name('deposit.store');
    Route::post('/deleteDeposite', [DepositController::class, 'destroy'])->name('deposit.deleteDeposite');
    Route::post('banDeposite', [DepositController::class, 'destroy']);

    //Internal API
    Route::post('/Lowfaresearch', [APIController::class, 'Lowfaresearch']);
    Route::post('/farerules', [APIController::class, 'getFareRules']);
    Route::post('/PricingRequestBody', [APIController::class, 'PricingRequestBody'])->name('PricingRequestBody');

    // Search V2
    Route::post('/v2/search', [SearchV2Controller::class, 'search'])->middleware('throttle:search-v2')->name('search.v2');
    Route::get('/v2/search/latest-snapshot', [SearchV2Controller::class, 'latestSnapshot'])->name('search.v2.latestSnapshot');
    Route::get('/flight-search-logs', [SearchV2Controller::class, 'getFlightSearchLogs'])->name('search.v2.logs');
    Route::post('/flight-search-log/view', [SearchV2Controller::class, 'viewFlightSearchLog'])->name('search.v2.logs.view');
    Route::get('/v2/fare-rules', [TravelportFareRulesController::class, 'index'])->name('fareRules.index');
    Route::get('/v2/fare-rules/download', [TravelportFareRulesController::class, 'download'])->name('fareRules.download');

    // Price V2
    Route::post('/v2/price', [PriceV2Controller::class, 'price'])->name('price.v2');
    Route::post('/flight-price-log/view', [PriceV2Controller::class, 'viewPriceLog'])->name('price.v2.log.view');

    // Reservation V2
    Route::post('/v2/reservation/workbench/initiate', [TpV2ReservationController::class, 'initiateWorkbench'])->name('reservation.v2.workbench.initiate');
    Route::post('/v2/reservation/workbench/addoffer', [TpV2ReservationController::class, 'addOffer'])->name('reservation.v2.workbench.addoffer');
    Route::post('/v2/reservation/ancillary/shop', [TpV2AncillaryController::class, 'shop'])->name('reservation.v2.ancillary.shop');
    Route::post('/v2/reservation/ancillary/book', [TpV2AncillaryController::class, 'book'])->name('reservation.v2.ancillary.book');
    Route::post('/v2/reservation/pax', [ReservationPaxController::class, 'store'])->name('reservation.v2.pax.store');
    Route::post('/v2/reservation/pax/sync-preferences', [ReservationPaxController::class, 'syncPreferences'])->name('reservation.v2.pax.sync-preferences');
    Route::post('/v2/reservation/pax/{id}/files', [ReservationPaxController::class, 'uploadFiles'])->name('reservation.v2.pax.files');
    Route::post('/v2/reservation/ssr/apply', [TpV2PreCommitController::class, 'applySsr'])->name('reservation.v2.ssr.apply');
    Route::post('/v2/reservation/travel-agency/save', [TpV2PreCommitController::class, 'saveTravelAgency'])->name('reservation.v2.travel-agency.save');

    // Booking Attempts V2
    Route::post('/v2/booking-attempts/{id}/complete-on-search', [BookingAttemptController::class, 'completeOnSearch'])->name('booking.v2.attempt.complete-on-search');
    Route::post('/v2/booking-attempts/{id}/complete-on-price', [BookingAttemptController::class, 'completeOnPrice'])->name('booking.v2.attempt.complete-on-price');
    Route::post('/v2/booking-attempts/{id}/prepare-review', [BookingAttemptController::class, 'prepareReview'])->name('booking.v2.attempt.prepare-review');
    Route::get('/v2/booking-attempts/{id}/summary', [BookingAttemptController::class, 'summary'])->name('booking.v2.attempt.summary');
    Route::post('/v2/booking-attempts/{id}/confirm', [BookingAttemptController::class, 'confirm'])->name('booking.v2.attempt.confirm');
    Route::post('/v2/booking-attempts/{id}/retry-commit', [BookingAttemptController::class, 'retryCommit'])->name('booking.v2.attempt.retry-commit');
    Route::post('/v2/booking-attempts/{id}/issue-ticket', [TpV2TicketController::class, 'issueTicket'])->name('booking.v2.attempt.issue-ticket');
    Route::post('/v2/booking-attempts/{id}/cancel', [TpV2CancelController::class, 'cancelBooking'])->name('booking.v2.attempt.cancel');
    Route::post('/v2/booking-attempts/{id}/void-ticket', [TpV2VoidController::class, 'voidTicket'])->name('booking.v2.attempt.void-ticket');
    Route::get('/v2/booking-attempts/{id}/activity-log', [BookingActivityLogController::class, 'index'])->name('booking.v2.attempt.activity-log');
    Route::get('/v2/booking-attempts', [BookingAttemptAdminController::class, 'index'])->name('booking.v2.attempts.index');
    Route::get('/v2/booking-attempts/{id}', [BookingAttemptAdminController::class, 'show'])->name('booking.v2.attempts.show');
    Route::get('/v2/booking/search-logs/{id}/request-download', [BookingAttemptAdminController::class, 'downloadSearchLogRequest'])->name('booking.v2.search-logs.request-download');
    Route::get('/v2/booking/search-logs/{id}/response-download', [BookingAttemptAdminController::class, 'downloadSearchLogResponse'])->name('booking.v2.search-logs.response-download');
    Route::get('/v2/booking/price-logs/{id}/request-download', [BookingAttemptAdminController::class, 'downloadPriceLogRequest'])->name('booking.v2.price-logs.request-download');
    Route::get('/v2/booking/price-logs/{id}/response-download', [BookingAttemptAdminController::class, 'downloadPriceLogResponse'])->name('booking.v2.price-logs.response-download');
    Route::get('/v2/booking/sessions/{id}/request-download', [BookingAttemptAdminController::class, 'downloadSessionRequest'])->name('booking.v2.sessions.request-download');
    Route::get('/v2/booking/sessions/{id}/response-download', [BookingAttemptAdminController::class, 'downloadSessionResponse'])->name('booking.v2.sessions.response-download');

});
Route::get('airports', [AreaController::class, 'airports']);

Route::post('/agent/registration', [AgentController::class, 'registration']);

Route::get('abilities', function (Request $request) {
    return Auth::user()->role()->with('role_permissions')->get()->pluck('role_permissions')->flatten()->pluck('feature_name')->unique()->values()->toArray();
});

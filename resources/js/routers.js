import { createRouter, createWebHistory } from 'vue-router'

const Home = () => import('./components/pages/Home.vue')
const Agent = () => import('./components/pages/Agent.vue')
const Settings = () => import('./components/pages/Settings.vue')
const Logout = () => import('./components/auth/Logout.vue')
const Login = () => import('./components/auth/Login.vue')
const Reg2Fa = () => import('./components/auth/2faregister.vue')
const sendResetLinkEmail = () => import('./components/auth/forgetPassMail.vue')
const PassReset = () => import('./components/auth/reserPass.vue')
const otp = () => import('./components/auth/otp.vue')
const NF = () => import('./components/auth/NotFound.vue')
const ForcePassChange = () => import('./components/auth/forcePasswordChange.vue')

const flightSearch = () => import('./components/search/flightSearch.vue')
const searchResult = () => import('./components/search/searchResult.vue')
const backupsearchResult = () => import('./components/search/searchResult_copy.vue')

// booking
const bookingList = () => import('./components/admin/booking/index.vue')
const bookingCreate = () => import('./components/admin/booking/create.vue')

// ticketing
const manualticketing = () => import('./components/admin/ticketing/manual_ticketing.vue')
// agent
const AgentList = () => import('./components/admin/agent/index.vue')
const agentDetails = () => import('./components/admin/agent/agentDetails.vue')
const agentApproved = () => import('./components/admin/agent/agentApproved.vue')
const agentRecomanded = () => import('./components/admin/agent/agentRecomanded.vue')
const agentView = () => import('./components/admin/agent/agentView.vue')
const CreateAgency = () => import('./components/admin/agent/create.vue')

// deposit management
const depositList = () => import('./components/admin/deposite/index.vue')
const depoDetails = () => import('./components/admin/deposite/depoDetails.vue')
const CreateDeposit = () => import('./components/admin/deposite/CreateDeposit.vue')


// user management
const UserList = () => import('./components/admin/user/index.vue')
const CreateUser = () => import('./components/admin/user/create.vue')
const EditUser = () => import('./components/admin/user/edit.vue')
const UserLog = () => import('./components/admin/user/log.vue')

//Roles permissions managment
const roleList = () => import('./components/admin/role/index.vue')
const roleCreate = () => import('./components/admin/role/create.vue')
const roleEdit = () => import('./components/admin/role/edit.vue')
//Zone
const zoneList = () => import('./components/admin/zone/index.vue')
const zoneCreate = () => import('./components/admin/zone/create.vue')
const zoneEdit = () => import('./components/admin/zone/edit.vue')

//department
const departmentList = () => import('./components/admin/department/index.vue')
const deptCreate = () => import('./components/admin/department/create.vue')
const deptEdit = () => import('./components/admin/department/edit.vue')

//designation
const designationList = () => import('./components/admin/designation/index.vue')
const designationCreate = () => import('./components/admin/designation/create.vue')
const designationEdit = () => import('./components/admin/designation/edit.vue')

//office location
const offLoc = () => import('./components/admin/officeLocation/index.vue')
const addoffLoc = () => import('./components/admin/officeLocation/create.vue')
const offEdit = () => import('./components/admin/officeLocation/edit.vue')
//setting deposit
const issuedBankMFS = () => import('./components/admin/settings/deposit/issuedBankMFS/index.vue')
const bankMfsCreate = () => import('./components/admin/settings/deposit/issuedBankMFS/create.vue')
const bankMfsEdit = () => import('./components/admin/settings/deposit/issuedBankMFS/edit.vue')
//paymentAccount
const paymentacct = () => import('./components/admin/settings/deposit/paymentAccount/index.vue')
const paymentAcctCreate = () => import('./components/admin/settings/deposit/paymentAccount/create.vue')
const payAcctEdit = () => import('./components/admin/settings/deposit/paymentAccount/edit.vue')

//aircraft type designator
const aircraftList = () => import('./components/admin/settings/aircraft/index.vue')
const aircraftCreate = () => import('./components/admin/settings/aircraft/create.vue')
const aircraftEdit = () => import('./components/admin/settings/aircraft/edit.vue')

//airlines
const airlinesList = () => import('./components/admin/settings/airlines/index.vue')
const airlinesCreate = () => import('./components/admin/settings/airlines/create.vue')
const airlinesEdit = () => import('./components/admin/settings/airlines/edit.vue')

// traveler management
const TravelerList = () => import('./components/admin/traveler/index.vue')
const TravelerView = () => import('./components/admin/traveler/view.vue')

const routes = [
    { path: '/', component: Login, name: 'Login', meta: { guest: true } },
    { path: '/sendResetLinkEmail', component: sendResetLinkEmail, name: 'sendResetLinkEmail', meta: { guest: true } },
    { path: '/PassReset/:token/:email', component: PassReset, name: 'PassReset', meta: { guest: true } },

    { path: '/Reg2Fa', component: Reg2Fa, name: 'register2fa', meta: { RE: true } },
    { path: '/otp', component: otp, name: 'otp', meta: { OTP: true } },
    { path: '/ForcePassChange', component: ForcePassChange, name: 'ForcePassChange', meta: { FPC: true } },

    { path: '/home', component: Home, name: 'Home', meta: { auth: true } },
    { path: '/Agent', component: Agent, name: 'Agent', meta: { auth: true } },
    { path: '/Settings', component: Settings, name: 'Settings', meta: { auth: true } },
    { path: '/Logout', component: Logout, name: 'Logout', meta: { auth: true } },
    { path: '/:any(.*)*', component: NF, name: 'NF', meta: { auth: true } },

    //flight search
    { path: '/flight-search', component: flightSearch, name: 'flightSearch', meta: { auth: true } },
    { path: '/flight-search-way', component: searchResult, name: 'searchResult', meta: { auth: true } },
    { path: '/flight-search-result', component: backupsearchResult, name: 'backupsearchResult', meta: { auth: true } },

    //booking
    { path: '/flight-booking-list', component: bookingList, name: 'bookingList', meta: { auth: true } },
    { path: '/flight-booking', component: bookingCreate, name: 'bookingCreate', meta: { auth: true } },

    //ticketing
    { path: '/manual-ticketing', component: manualticketing, name: 'manualticketing', meta: { auth: true } },

    // agent
    { path: '/agent-list', component: AgentList, name: 'AgentList', meta: { auth: true } },
    { path: '/agent-details', component: agentDetails, name: 'agentDetails', meta: { auth: true } },
    { path: '/agent-recommended/:ids', component: agentRecomanded, name: 'agentRecomanded',  meta: { auth: true },props:true},
    { path: '/agent-approve/:ids', component: agentApproved, name: 'agentApproved', meta: { auth: true },props:true},
    { path: '/agent-view/:ids', component: agentView, name: 'agentView', meta: { auth: true } ,props:true},
    { path: '/agent-create', component: CreateAgency, name: 'CreateAgency', meta: { auth: true } },

    //deposit
    { path: '/agent-deposit-list', component: depositList, name: 'depositList', meta: { auth: true } },
    { path: '/agent-deposit-details', component: depoDetails, name: 'depoDetails', meta: { auth: true } },
    { path: '/new-deposit-request', component: CreateDeposit, name: 'CreateDeposit', meta: { auth: true } },

    //user
    { path: '/all-user-list', component: UserList, name: 'UserList', meta: { auth: true } },
    { path: '/create-user', component: CreateUser, name: 'CreateUser', meta: { auth: true } },
    { path: '/edit-user/:id', component: EditUser, name: 'EditUser', meta: { auth: true },props:true },
    { path: '/log-user/:id', component: UserLog, name: 'UserLog', meta: { auth: true },props:true },

    //roles permission
    { path: '/all-roles-list', component: roleList, name: 'roleList', meta: { auth: true } },
    { path: '/create-role', component: roleCreate, name: 'roleCreate', meta: { auth: true } },
    { path: '/edit-role/:id', component: roleEdit, name: 'roleEdit', meta: { auth: true },props:true },

    //Zone
    { path: '/all-zone-list', component: zoneList, name: 'zoneList', meta: { auth: true } },
    { path: '/create-zone', component: zoneCreate, name: 'zoneCreate', meta: { auth: true } },
    { path: '/edit-zone/:id', component: zoneEdit, name: 'zoneEdit', meta: { auth: true },props:true },
    // department
    { path: '/all-department-list', component: departmentList, name: 'departmentList', meta: { auth: true } },
    { path: '/create-department', component: deptCreate, name: 'deptCreate', meta: { auth: true } },
    { path: '/edit-department/:id', component: deptEdit, name: 'deptEdit', meta: { auth: true },props:true  },

    // designation
    { path: '/all-designation-list', component: designationList, name: 'designationList', meta: { auth: true } },
    { path: '/create-designation', component: designationCreate, name: 'designationCreate', meta: { auth: true } },
    { path: '/edit-designation/:id', component: designationEdit, name: 'designationEdit', meta: { auth: true },props:true  },

    // office location
    { path: '/all-office-location', component: offLoc, name: 'offLoc', meta: { auth: true } },
    { path: '/create-location', component: addoffLoc, name: 'addoffLoc', meta: { auth: true } },
    { path: '/office-location/:id', component: offEdit, name: 'offEdit', meta: { auth: true },props:true  },

    // sttings deposit bank and mfs
    { path: '/all-bank-&-mfs', component: issuedBankMFS, name: 'issuedBankMFS', meta: { auth: true } },
    { path: '/create-bank-mfs', component: bankMfsCreate, name: 'bankMfsCreate', meta: { auth: true } },
    { path: '/edit-bank-mfs/:id', component: bankMfsEdit, name: 'bankMfsEdit', meta: { auth: true },props:true  },
    //paymentacct
    { path: '/all-payment-accounts', component: paymentacct, name: 'paymentacct', meta: { auth: true } },
    { path: '/create-payment-account', component: paymentAcctCreate, name: 'paymentAcctCreate', meta: { auth: true } },
    { path: '/edit-payment-account/:id', component: payAcctEdit, name: 'payAcctEdit', meta: { auth: true },props:true  },

    //aircraft
    { path: '/all-aircraft-list', component: aircraftList, name: 'aircraftList', meta: { auth: true } },
    { path: '/create-aircraft', component: aircraftCreate, name: 'aircraftCreate', meta: { auth: true } },
    { path: '/edit-aircraft/:id', component: aircraftEdit, name: 'aircraftEdit', meta: { auth: true },props:true  },

    //airlines
    { path: '/all-airlinesList-list', component: airlinesList, name: 'airlinesList', meta: { auth: true } },
    { path: '/create-airline', component: airlinesCreate, name: 'airlinesCreate', meta: { auth: true } },
    { path: '/edit-airlines/:id', component: airlinesEdit, name: 'airlinesEdit', meta: { auth: true },props:true  },

    //traveler
    { path: '/all-traveler-list', component: TravelerList, name: 'TravelerList', meta: { auth: true } },
    { path: '/view-traveler/', component: TravelerView, name: 'TravelerView', meta: { auth: true } },
]

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;

<script setup>
import AppBreadcrumbs from '../../common/AppBreadcrumbs.vue';
import AppTooltip from '../../common/AppTooltip.vue';
import { ref, computed, onMounted } from "vue";
import { useRouter, useRoute } from 'vue-router';
import axiosInstance from "../../../axiosInstance";
import moment from "moment";

const router = useRouter();
const route = useRoute();

const loading = ref(true);
const data = ref(null);
const offerData = ref(null);

// Collapsible sections
const showGroupSegment = ref(true);
const showFlightPreferences = ref(true);
const showFareBuilder = ref(true);
const showPaymentTerms = ref(true);

onMounted(async () => {
    if (route.params.id) {
        await getAllDataOfGroup(route.params.id);
    }
});

async function getAllDataOfGroup(id) {
    try {
        loading.value = true;
        const response = await axiosInstance.post('get-group-request-offer-price/data', { id: id });
        const [offer, groupData] = response.data.data;

        data.value = groupData;
        offerData.value = offer;
        console.log(offerData.value);

    } catch (error) {
        console.error('Failed to fetch group data:', error);
        Notification.showToast('error', error.response?.data?.message || 'Failed to load group data');
    } finally {
        loading.value = false;
    }
}

const tripType = computed(() => data.value?.request_type || '');

const tripTypeLabel = computed(() => {
    const map = { oneway: 'One Way', roundway: 'Round Way', multicity: 'Multi City' };
    return map[tripType.value] || tripType.value || '—';
});

const segments = computed(() => data.value?.segments || []);

function formatDate(val) {
    if (!val) return '—';
    return moment(val).format('DD MMM, YYYY, hh:mm A');
}

function formatCurrency(val, curr) {
    if (val === null || val === undefined || val === '') return `${curr || 'BDT'} 0`;
    return `${curr || 'BDT'} ${Number(val).toLocaleString()}`;
}

function formatPaymentDate(val) {
    if (!val) return '';
    return moment(val).format('DD-MMM-YYYY | hh:mm A');
}

// Base fare adjusted for markup (Flat/Percent), service charge, and totals
function calcMarkedUpBaseFare(rawBase) {
    const base = Number(rawBase) || 0;
    const markupValue = Number(offerData.value?.markup_value) || 0;
    if (!markupValue) return base;
    if (offerData.value?.markup_type === 'Percent') {
        return base * (1 + markupValue / 100);
    }
    return base + markupValue;
}

function calcServiceCharge(markedUpBase) {
    const value = Number(offerData.value?.service_charge_value) || 0;
    if (offerData.value?.service_charge_type === 'Flat') return value;
    return (markedUpBase * value) / 100;
}

const adultBaseFare = computed(() => calcMarkedUpBaseFare(offerData.value?.adult_base_fare));
const childBaseFare = computed(() => calcMarkedUpBaseFare(offerData.value?.child_base_fare));
const infantBaseFare = computed(() => calcMarkedUpBaseFare(offerData.value?.infant_base_fare));

const adultServiceCharge = computed(() => calcServiceCharge(adultBaseFare.value));
const childServiceCharge = computed(() => calcServiceCharge(childBaseFare.value));
const infantServiceCharge = computed(() => calcServiceCharge(infantBaseFare.value));

const adultTotal = computed(() => {
    if (!offerData.value) return 0;
    const tax = Number(offerData.value.adult_tax) || 0;
    const ait = Number(offerData.value.adult_ait) || 0;
    const maxPax = Number(offerData.value.adult_max_pax) || 0;
    return (adultBaseFare.value + tax + ait + adultServiceCharge.value) * maxPax;
});

const childTotal = computed(() => {
    if (!offerData.value) return 0;
    const tax = Number(offerData.value.child_tax) || 0;
    const ait = Number(offerData.value.child_ait) || 0;
    const maxPax = Number(offerData.value.child_max_pax) || 0;
    return (childBaseFare.value + tax + ait + childServiceCharge.value) * maxPax;
});

const infantTotal = computed(() => {
    if (!offerData.value) return 0;
    const tax = Number(offerData.value.infant_tax) || 0;
    const ait = Number(offerData.value.infant_ait) || 0;
    const maxPax = Number(offerData.value.infant_max_pax) || 0;
    return (infantBaseFare.value + tax + ait + infantServiceCharge.value) * maxPax;
});

const estTotalFare = computed(() => offerData.value?.est_total_fare || 0);
const estimateNetPayable = computed(() => offerData.value?.estimate_net_payable || 0);

const currency = computed(() => offerData.value?.currency || 'BDT');

const estTotalFareBDT = computed(() => {
    if (currency.value !== 'USD') return estTotalFare.value;
    const rate = Number(offerData.value?.exchange_rate) || 0;
    return Math.round(estTotalFare.value * rate);
});

const estimateNetPayableBDT = computed(() => {
    if (currency.value !== 'USD') return estimateNetPayable.value;
    const rate = Number(offerData.value?.exchange_rate) || 0;
    return Math.round(estimateNetPayable.value * rate);
});

const paymentTerms = computed(() => offerData.value?.payment_terms || []);

const offerSegments = computed(() => offerData.value?.segments || []);

function goBack() {
    router.push({ name: 'groupList' });
}
const showGroupRequestModal = ref(false);

function openGroupRequestModal() {
    showGroupRequestModal.value = true;
}
function closeGroupRequestModal() {
    showGroupRequestModal.value = false;
}

// Accept/Decline functionality
const showDeclineModal = ref(false);
const declineNote = ref('');
const declining = ref(false);
const accepting = ref(false);

function openDeclineModal() {
    declineNote.value = '';
    showDeclineModal.value = true;
}

function closeDeclineModal() {
    showDeclineModal.value = false;
    declineNote.value = '';
}

async function handleAccept() {
    if (!offerData.value) return;

    try {
        accepting.value = true;
        await axiosInstance.post('group-request/offer-price/accept', {
            id: offerData.value.id
        });
        Notification.showToast('success', 'Offer confirmed successfully');
        offerData.value.status = 'Offer confirmed';
    } catch (error) {
        console.error('Failed to accept offer:', error);
        Notification.showToast('error', error.response?.data?.message || 'Failed to accept offer');
    } finally {
        accepting.value = false;
    }
}

async function handleDecline() {
    if (!offerData.value || !declineNote.value.trim()) {
        Notification.showToast('error', 'Please provide a reason for declining');
        return;
    }

    try {
        declining.value = true;
        await axiosInstance.post('group-request/offer-price/decline', {
            id: offerData.value.id,
            note: declineNote.value
        });
        Notification.showToast('success', 'Offer declined successfully');
        offerData.value.status = 'Offer Declined';
        closeDeclineModal();
    } catch (error) {
        console.error('Failed to decline offer:', error);
        Notification.showToast('error', error.response?.data?.message || 'Failed to decline offer');
    } finally {
        declining.value = false;
    }
}
</script>

<template>
    <AppBreadcrumbs title="View Offer Price" :back-to="{ name: 'groupList' }" :breadcrumbs="[
        { label: 'Dashboard', to: { name: 'Home' } },
        { label: 'Group Management', to: { name: 'groupList' } },
        { label: 'View Offer Price' }]">
    </AppBreadcrumbs>

    <div class="price-offer-page">
        <!-- Loading State -->
        <div v-if="loading" class="view-loading">
            <div class="spinner-box">
                <div class="spinner-ring"></div>
                <span>Loading offer price details...</span>
            </div>
        </div>

        <!-- Main Content -->
        <div v-else-if="data && offerData">
            <!-- Page Header -->
            <div class="page-header">
                <h4 class="page-title">Offer Price Details</h4>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <!-- Group & Segment Section -->
                    <div class="section-card">
                        <div class="section-header">
                            <div class="section-title-row">
                                <span class="section-title">Group & Segment</span>
                            </div>
                            <div class="section-actions">
                                <i class="fa-solid fa-chevron-up collapse-icon"
                                    :class="{ 'collapsed': !showGroupSegment }"></i>
                            </div>
                        </div>

                        <div class="section-body">
                            <div class="segment-cards">
                                <!-- Agency Info Card -->
                                <div class=" info-card agency-info-card">
                                    <div class="agency-name">
                                        <i class="fa-solid fa-building"></i>
                                        {{ data.agent?.name || '—' }}
                                    </div>
                                    <div class="d-flex">
                                        <div class="agency-tags">
                                            <span class="tag tag-purple">
                                                <i class="fa-solid fa-users"></i>
                                                {{ data.group_code || '—' }}
                                            </span>
                                            <span class="tag tag-gray">
                                                <i class="fa-solid fa-users"></i>
                                                {{ data.group_type || '—' }}
                                            </span>
                                            <span class="tag tag-blue">
                                                <i class="fa-solid fa-route"></i>
                                                {{ tripTypeLabel }}
                                            </span>
                                            <span class="tag tag-gray">
                                                <i class="fa-solid fa-couch"></i>
                                                {{ data.class_type || '—' }} <template v-if="data.class_code"> -{{
                                                    data.class_code }}</template>
                                            </span>
                                            <span class="tag tag-gray">
                                                Total PAX
                                                {{ data.total_traveler || 0 }}
                                            </span>
                                            <!-- <span class="tag tag-gray">
                                            <i class="fa fa-person me-1"></i> {{ data.adult_traveler }}
                                        </span>
                                        <span class="tag tag-gray">
                                            <i class="fa fa-child me-1"></i>
                                            {{ data.child_traveler }}
                                        </span>
                                        <span class="tag tag-gray">
                                            <i class="fa fa-baby me-1"></i>
                                            {{ data.infant_traveler }}
                                        </span> -->
                                        </div>
                                        <div class="ms-auto">
                                            <button class="btn btn-sm btn-primary" @click="openGroupRequestModal">View
                                                Group
                                                Request</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="row">
                        <div class="col-md-8">
                            <!-- Flight Segments Section -->
                            <div class="section-card">
                                <div class="section-header">
                                    <div class="section-title-row">
                                        <span class="section-title">Flight Segments</span>
                                    </div>
                                </div>

                                <div class="section-body">
                                    <!-- ONE WAY -->
                                    <div class="policy-grid" v-if="tripType === 'oneway'">
                                        <div class="policy-field">
                                            <label class="policy-label">From</label>
                                            <div class="readonly-field">{{ data.origin || '—' }}</div>
                                        </div>
                                        <div class="policy-field">
                                            <label class="policy-label">To</label>
                                            <div class="readonly-field">{{ data.destination || '—' }}</div>
                                        </div>
                                        <div class="policy-field">
                                            <label class="policy-label">Departure Date & Time</label>
                                            <div class="readonly-field">{{ formatDate(data.departure_date) }}</div>
                                        </div>
                                    </div>

                                    <!-- ROUND WAY -->
                                    <div v-if="tripType === 'roundway'">
                                        <div class="section-heading blue">
                                            <span class="section-bar"></span>
                                            <h6>Outbound Flight</h6>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-md-4">
                                                <label class="policy-label">From</label>
                                                <div class="readonly-field">{{ data.origin || '—' }}</div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="policy-label">To</label>
                                                <div class="readonly-field">{{ data.destination || '—' }}</div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="policy-label">Departure Date & Time</label>
                                                <div class="readonly-field">{{ formatDate(data.departure_date) }}</div>
                                            </div>
                                        </div>

                                        <div class="section-heading purple">
                                            <span class="section-bar"></span>
                                            <h6>Return Flight</h6>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <label class="policy-label">From</label>
                                                <div class="readonly-field">{{ data.return_origin || '—' }}</div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="policy-label">To</label>
                                                <div class="readonly-field">{{ data.return_destination || '—' }}</div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="policy-label">Return Date & Time</label>
                                                <div class="readonly-field">{{ formatDate(data.return_date) }}</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- MULTI CITY -->
                                    <div v-if="tripType === 'multicity'">
                                        <div v-for="(seg, index) in segments" :key="index" class="mb-3">
                                            <div class="section-heading purple">
                                                <span class="section-bar"></span>
                                                <h6>Segment {{ index + 1 }}</h6>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <label class="policy-label">From</label>
                                                    <div class="readonly-field">{{ seg.origin || '—' }}</div>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="policy-label">To</label>
                                                    <div class="readonly-field">{{ seg.destination || '—' }}</div>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="policy-label">Departure Date & Time</label>
                                                    <div class="readonly-field">{{ formatDate(seg.departure_date) }}</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Flight Preferences Section -->
                            <div class="section-card">
                                <div class="section-header" @click="showFlightPreferences = !showFlightPreferences">
                                    <div class="section-title-row">
                                        <span class="section-title">Flight Preferences</span>
                                    </div>
                                    <div class="section-actions">
                                        <i class="fa-solid fa-chevron-up collapse-icon"
                                            :class="{ 'collapsed': !showFlightPreferences }"></i>
                                    </div>
                                </div>

                                <div v-if="showFlightPreferences" class="section-body">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <label class="policy-label">Offered Airlines</label>
                                            <div class="readonly-field">{{ offerData.offered_flight || '—' }}</div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="policy-label">Class Type</label>
                                            <div class="readonly-field">{{ offerData.class_type || '—' }}</div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="policy-label">RBD Code</label>
                                            <div class="readonly-field">{{ offerData.class_code || '—' }}</div>
                                        </div>
                                        <div class="col-md-3" v-if="tripType !== 'multicity'">
                                            <label class="policy-label">Flight No.</label>
                                            <div class="readonly-field">{{ offerData.offered_flight_no || '—' }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Fare Builder Section -->
                            <div class="section-card">
                                <div class="section-header" @click="showFareBuilder = !showFareBuilder">
                                    <div class="section-title-row">
                                        <span class="section-title">Fare Builder</span>
                                    </div>
                                    <div class="section-actions">
                                        <i class="fa-solid fa-chevron-up collapse-icon" :class="{ 'collapsed': !showFareBuilder }"></i>
                                    </div>
                                </div>

                                <div v-if="showFareBuilder" class="section-body">
                                    <!-- Currency Info -->
                                    <div class="currency-section">
                                        <span class="currency-label">Currency:</span>
                                        <span class="currency-value">{{ currency }}</span>
                                        <template v-if="currency === 'USD' && offerData.exchange_rate">
                                            <span class="currency-divider"></span>
                                            <span class="currency-label">Exchange Rate:</span>
                                            <span class="currency-value">{{ offerData.exchange_rate }}</span>
                                        </template>
                                    </div>

                                    <!-- Fare Cards -->
                                    <div class="fare-cards-row">
                                        <!-- Adult Card -->
                                        <div class="fare-card">
                                            <span class="fare-badge badge-adult">Adult</span>
                                            <div class="fare-fields">
                                                <div class="fare-field">
                                                    <label>Base Fare :</label>
                                                    <span class="fare-value">{{ formatCurrency(adultBaseFare, currency) }}</span>
                                                </div>
                                                <div class="fare-field">
                                                    <label>TAX :</label>
                                                    <span class="fare-value">{{ formatCurrency(offerData.adult_tax, currency) }}</span>
                                                </div>
                                                <div class="fare-field">
                                                    <label>AIT :</label>
                                                    <span class="fare-value">{{ offerData.adult_ait || '0' }}</span>
                                                </div>
                                                <div class="fare-field">
                                                    <label>Service Charge :</label>
                                                    <span class="fare-value">{{ formatCurrency(adultServiceCharge, currency) }}</span>
                                                </div>
                                                <div class="fare-field">
                                                    <label>Max PAX :</label>
                                                    <span class="fare-value">{{ offerData.adult_max_pax || 0 }}</span>
                                                </div>
                                            </div>
                                            <div class="fare-total">Total : {{ formatCurrency(adultTotal, currency) }}</div>
                                        </div>

                                        <!-- Child Card -->
                                        <div class="fare-card">
                                            <span class="fare-badge badge-child">Child</span>
                                            <div class="fare-fields">
                                                <div class="fare-field">
                                                    <label>Base Fare :</label>
                                                    <span class="fare-value">{{ formatCurrency(childBaseFare, currency) }}</span>
                                                </div>
                                                <div class="fare-field">
                                                    <label>TAX :</label>
                                                    <span class="fare-value">{{ formatCurrency(offerData.child_tax, currency) }}</span>
                                                </div>
                                                <div class="fare-field">
                                                    <label>AIT :</label>
                                                    <span class="fare-value">{{ offerData.child_ait || '0' }}</span>
                                                </div>
                                                <div class="fare-field">
                                                    <label>Service Charge :</label>
                                                    <span class="fare-value" v-if="offerData.child_max_pax > 0">{{ formatCurrency(childServiceCharge, currency) }}</span>
                                                    <span v-else>0</span>
                                                </div>
                                                <div class="fare-field">
                                                    <label>Max PAX :</label>
                                                    <span class="fare-value">{{ offerData.child_max_pax || 0 }}</span>
                                                </div>
                                            </div>
                                            <div class="fare-total">Total : {{ formatCurrency(childTotal, currency) }}</div>
                                        </div>

                                        <!-- Infant Card -->
                                        <div class="fare-card">
                                            <span class="fare-badge badge-infant">Infant</span>
                                            <div class="fare-fields">
                                                <div class="fare-field">
                                                    <label>Base Fare :</label>
                                                    <span class="fare-value">{{ formatCurrency(infantBaseFare, currency) }}</span>
                                                </div>
                                                <div class="fare-field">
                                                    <label>TAX :</label>
                                                    <span class="fare-value">{{ formatCurrency(offerData.infant_tax, currency) }}</span>
                                                </div>
                                                <div class="fare-field">
                                                    <label>AIT :</label>
                                                    <span class="fare-value">{{ offerData.infant_ait || '0' }}</span>
                                                </div>
                                                <div class="fare-field">
                                                    <label>Service Charge :</label>
                                                    <span class="fare-value" v-if="offerData.infant_max_pax > 0">{{ formatCurrency(infantServiceCharge, currency) }}</span>
                                                    <span v-else>0</span>
                                                </div>
                                                <div class="fare-field">
                                                    <label>Max PAX :</label>
                                                    <span class="fare-value">{{ offerData.infant_max_pax || 0 }}</span>
                                                </div>
                                            </div>
                                            <div class="fare-total">Total : {{ formatCurrency(infantTotal, currency) }}</div>
                                        </div>
                                    </div>

                                    <!-- Est. Total Fare -->
                                    <!-- <div class="est-total-fare-row">
                                        <div class="est-total-fare-box">
                                            <span class="est-label">Est. Total Fare [{{ currency }}]</span>
                                            <span class="est-value">{{ formatCurrency(estTotalFare, currency) }}</span>
                                        </div>
                                        <div v-if="currency === 'USD' && offerData.exchange_rate" class="est-total-fare-box">
                                            <span class="est-label">Est. Total Fare [BDT]</span>
                                            <span class="est-value">{{ formatCurrency(estTotalFareBDT, 'BDT') }}</span>
                                        </div>
                                    </div> -->

                                    <!-- Markup & Service Charge -->
                                    <!-- <div class="markup-row">
                                        <div class="markup-field">
                                            <label class="field-label">Markup</label>
                                            <div class="readonly-inline">
                                                <span class="readonly-value">{{ offerData.markup_value || '0' }}</span>
                                                <span class="readonly-type">{{ offerData.markup_type || 'Percent' }}</span>
                                            </div>
                                        </div>
                                        <div class="markup-field">
                                            <label class="field-label">Service Charge (+)</label>
                                            <div class="readonly-inline">
                                                <span class="readonly-value">{{ offerData.service_charge_value || '0' }}</span>
                                                <span class="readonly-type">{{ offerData.service_charge_type || 'Flat' }}</span>
                                            </div>
                                        </div>
                                    </div> -->

                                    <!-- Net Payable -->
                                    <div class="net-payable-row">
                                        <div class="net-payable-box">
                                            <span class="net-label">Est. Net Payable [{{ currency }}]</span>
                                            <span class="net-value">{{ formatCurrency(estimateNetPayable, currency) }}</span>
                                        </div>
                                        <div v-if="currency === 'USD' && offerData.exchange_rate" class="net-payable-box">
                                            <span class="net-label">Est. Net Payable [BDT]</span>
                                            <span class="net-value">{{ formatCurrency(estimateNetPayableBDT, 'BDT') }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Payment Terms Section -->
                            <div class="section-card">
                                <div class="section-header" @click="showPaymentTerms = !showPaymentTerms">
                                    <div class="section-title-row">
                                        <span class="section-title">Payment Terms</span>
                                    </div>
                                    <div class="section-actions">
                                        <i class="fa-solid fa-chevron-up collapse-icon" :class="{ 'collapsed': !showPaymentTerms }"></i>
                                    </div>
                                </div>

                                <div v-if="showPaymentTerms" class="section-body">
                                    <!-- Payment Term Headers -->
                                    <div class="payment-header-row">
                                        <span class="ph-col">Payment Sequence</span>
                                        <span class="ph-col">Value</span>
                                        <span class="ph-col">Amount</span>
                                        <span class="ph-col">Last Payment Date</span>
                                    </div>

                                    <!-- Payment Term Rows -->
                                    <div v-for="(term, index) in paymentTerms" :key="index" class="payment-row">
                                        <div class="pr-col">
                                            <div class="readonly-field-sm">{{ term.sequence || '—' }}</div>
                                        </div>
                                        <div class="pr-col">
                                            <div class="readonly-field-sm">{{ term.value || 0 }} {{ term.value_type || 'Percent' }}
                                            </div>
                                        </div>
                                        <div class="pr-col">
                                            <div class="readonly-field-sm">{{ formatCurrency(term.amount, currency) }}</div>
                                        </div>
                                        <div class="pr-col">
                                            <div class="readonly-field-sm">{{ formatPaymentDate(term.due_date) || '—' }}</div>
                                        </div>
                                    </div>

                                    <!-- Policy & Fare Rules -->
                                    <div class="payment-rules-section">
                                        <label class="field-label">Policy & Fare Rules</label>
                                        <div class="rules-display">{{ offerData.policy_fare_rules || 'No rules specified.' }}</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Remarks Section -->
                            <div class="section-card" v-if="offerData.remarks">
                                <div class="section-header">
                                    <div class="section-title-row">
                                        <span class="section-title">Remarks</span>
                                    </div>
                                </div>
                                <div class="section-body">
                                    <div class="rules-display">{{ offerData.remarks }}</div>
                                </div>
                            </div>

                            <!-- Back Button -->
                            <div class="nav-actions">
                                <button @click="goBack" class="btn-action btn-back">
                                    <i class="bi bi-arrow-left me-1"></i>Back
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <!-- accept or decline -->
                            <div class="action-sidebar" v-if="offerData.status !== 'Offer Declined' && offerData.status !== 'Offer confirmed'">
                                <div class="action-card">
                                    <h6 class="action-title">
                                        <i class="fa-solid fa-gavel me-1"></i>
                                        Actions
                                    </h6>
                                    <p class="action-desc">Review the offer price and accept or decline this offer.</p>
                                    <div class="action-buttons">
                                        <button class="btn-accept" @click="handleAccept" :disabled="accepting">
                                            <i class="fa-solid fa-check me-1"></i>
                                            {{ accepting ? 'Accepting...' : 'Offer confirmed' }}
                                        </button>
                                        <button class="btn-decline" @click="openDeclineModal" :disabled="declining">
                                            <i class="fa-solid fa-times me-1"></i>
                                            Decline
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Status Card (when already accepted/declined) -->
                            <div class="action-sidebar" v-else>
                                <div class="action-card" :class="offerData.status === 'Offer confirmed' ? 'status-accepted' : 'status-declined'">
                                    <h6 class="action-title">
                                        <i :class="offerData.status === 'Offer confirmed' ? 'fa-solid fa-check-circle' : 'fa-solid fa-times-circle'"></i>
                                        {{ offerData.status }}
                                    </h6>
                                    <p class="action-desc" v-if="offerData.status === 'Offer confirmed'">
                                        This offer has been accepted successfully.
                                    </p>
                                    <p class="action-desc" v-else>
                                        This offer has been declined.
                                    </p>
                                    <p class="decline-reason" v-if="offerData.status === 'Offer Declined' && offerData.remarks">
                                        <strong>Reason:</strong> {{ offerData.remarks }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- No Data State -->
        <div v-else class="empty-state">
            <div class="empty-icon"><i class="fa fa-inbox"></i></div>
            <h5>No Data Found</h5>
            <p>The offer price you're looking for doesn't exist or has been removed.</p>
            <button @click="goBack" class="btn-back"><i class="bi bi-arrow-left me-1"></i>Go Back</button>
        </div>
    </div>

    <!-- Group Request Modal -->
    <!-- Group Request Detail Modal -->
    <div v-if="showGroupRequestModal" class="modal-overlay" @click.self="closeGroupRequestModal">
        <div class="modal-box modal-lg">
            <div class="modal-header">
                <h5>Group Request Details</h5>
                <button class="modal-close" @click="closeGroupRequestModal">
                    <i class="fa fa-times"></i>
                </button>
            </div>
            <div class="modal-body" v-if="data">
                <div class="gr-detail-grid">
                    <!-- Agency Info -->
                    <div class="gr-detail-section">
                        <h6 class="gr-section-title"><i class="fa-solid fa-building me-1"></i> Agency Information</h6>
                        <div class="gr-detail-row">
                            <span class="gr-label">Agency Name</span>
                            <span class="gr-value">{{ data.agent?.name || '—' }}</span>
                        </div>
                        <div class="gr-detail-row">
                            <span class="gr-label">Group Code</span>
                            <span class="gr-value">{{ data.group_code || '—' }}</span>
                        </div>
                        <div class="gr-detail-row">
                            <span class="gr-label">Group Type</span>
                            <span class="gr-value">{{ data.group_type || '—' }}</span>
                        </div>
                    </div>

                    <!-- Trip Info -->
                    <div class="gr-detail-section">
                        <h6 class="gr-section-title"><i class="fa-solid fa-route me-1"></i> Trip Information</h6>
                        <div class="gr-detail-row">
                            <span class="gr-label">Trip Type</span>
                            <span class="gr-value">{{ tripTypeLabel }}</span>
                        </div>
                        <div class="gr-detail-row">
                            <span class="gr-label">Class Type</span>
                            <span class="gr-value">{{ data.class_type || '—' }} <template v-if="data.class_code"> - {{
                                data.class_code }}</template></span>
                        </div>
                        <div class="gr-detail-row">
                            <span class="gr-label">Preferred Flight</span>
                            <span class="gr-value">{{ data.preferred_flight || '—' }}</span>
                        </div>
                        <div class="gr-detail-row">
                            <span class="gr-label">Flight No</span>
                            <span class="gr-value">{{ data.flight_no || '—' }}</span>
                        </div>
                    </div>

                    <!-- Passenger Info -->
                    <div class="gr-detail-section">
                        <h6 class="gr-section-title"><i class="fa-solid fa-users me-1"></i> Passenger Information</h6>
                        <div class="gr-detail-row">
                            <span class="gr-label">Total PAX</span>
                            <span class="gr-value">{{ data.total_traveler || 0 }}</span>
                        </div>
                        <div class="gr-detail-row">
                            <span class="gr-label">Adult</span>
                            <span class="gr-value">{{ data.adult_traveler || 0 }}</span>
                        </div>
                        <div class="gr-detail-row">
                            <span class="gr-label">Child</span>
                            <span class="gr-value">{{ data.child_traveler || 0 }}</span>
                        </div>
                        <div class="gr-detail-row">
                            <span class="gr-label">Infant</span>
                            <span class="gr-value">{{ data.infant_traveler || 0 }}</span>
                        </div>
                    </div>

                    <!-- Sector & Dates -->
                    <div class="gr-detail-section">
                        <h6 class="gr-section-title"><i class="fa-solid fa-plane-departure me-1"></i> Sector & Dates
                        </h6>
                        <!-- One Way -->
                        <template v-if="data.request_type === 'oneway'">
                            <div class="gr-detail-row">
                                <span class="gr-label">Route</span>
                                <span class="gr-value">{{ data.origin }} - {{ data.destination }}</span>
                            </div>
                            <div class="gr-detail-row">
                                <span class="gr-label">Departure</span>
                                <span class="gr-value">{{ formatDate(data.departure_date) }}</span>
                            </div>
                        </template>
                        <!-- Round Way -->
                        <template v-else-if="data.request_type === 'roundway'">
                            <div class="gr-detail-row">
                                <span class="gr-label">Outbound Route</span>
                                <span class="gr-value">{{ data.origin }} - {{ data.destination }}</span>
                            </div>
                            <div class="gr-detail-row">
                                <span class="gr-label">Departure</span>
                                <span class="gr-value">{{ formatDate(data.departure_date) }}</span>
                            </div>
                            <div class="gr-detail-row">
                                <span class="gr-label">Return Route</span>
                                <span class="gr-value">{{ data.return_origin }} - {{ data.return_destination }}</span>
                            </div>
                            <div class="gr-detail-row">
                                <span class="gr-label">Return Date</span>
                                <span class="gr-value">{{ formatDate(data.return_date) }}</span>
                            </div>
                        </template>
                        <!-- Multi City -->
                        <template v-else-if="data.request_type === 'multicity' && data.segments">
                            <div v-for="(seg, i) in data.segments" :key="i" class="gr-segment-item">
                                <span class="gr-segment-badge">Segment {{ i + 1 }}</span>
                                <div class="gr-detail-row">
                                    <span class="gr-label">Route</span>
                                    <span class="gr-value">{{ seg.origin }} - {{ seg.destination }}</span>
                                </div>
                                <div class="gr-detail-row">
                                    <span class="gr-label">Departure</span>
                                    <span class="gr-value">{{ formatDate(seg.departure_date) }}</span>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Status -->
                    <div class="gr-detail-section">
                        <h6 class="gr-section-title"><i class="fa-solid fa-circle-info me-1"></i> Status</h6>
                        <div class="gr-detail-row">
                            <span class="gr-label">Current Status</span>
                            <span class="gr-value">{{ data.status || '—' }}</span>
                        </div>
                        <div class="gr-detail-row">
                            <span class="gr-label">Created</span>
                            <span class="gr-value">{{ formatDate(data.created_at) }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-gr-close" @click="closeGroupRequestModal">Close</button>
            </div>
        </div>
    </div>

    <!-- Decline Modal -->
    <div v-if="showDeclineModal" class="modal-overlay" @click.self="closeDeclineModal">
        <div class="modal-box">
            <div class="modal-header">
                <h5>Decline Offer</h5>
                <button class="modal-close" @click="closeDeclineModal">
                    <i class="fa fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="decline-form">
                    <label class="decline-label">Reason for declining <span class="required">*</span></label>
                    <textarea
                        v-model="declineNote"
                        class="decline-textarea"
                        rows="4"
                        placeholder="Please provide a reason for declining this offer..."
                    ></textarea>
                    <span class="decline-hint" v-if="!declineNote.trim()">Please enter a reason before submitting.</span>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-gr-close" @click="closeDeclineModal">Cancel</button>
                <button class="btn-decline-confirm" @click="handleDecline" :disabled="declining || !declineNote.trim()">
                    <i class="fa-solid fa-times me-1"></i>
                    {{ declining ? 'Declining...' : 'Confirm Decline' }}
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.price-offer-page {
    padding: 1.5rem;
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    background: #f5f7fa;
    min-height: 100%;
}

/* Page Header */
.page-header {
    margin-bottom: 1.25rem;
}

.page-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: #111827;
    margin: 0;
}

/* Section Card */
.section-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
    margin-bottom: 1rem;
    overflow: hidden;
}

.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    cursor: pointer;
    user-select: none;
    transition: background 0.15s;
}

.section-header:hover {
    background: #f9fafb;
}

.section-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #111827;
}

.section-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.collapse-icon {
    font-size: 0.8rem;
    color: #9ca3af;
    transition: transform 0.2s;
}

.collapse-icon.collapsed {
    transform: rotate(180deg);
}

.section-body {
    padding: 0 1.25rem 1.25rem;
}

/* Segment Cards */
.segment-cards {
    display: grid;
    gap: 1rem;
}

.info-card {
    border-radius: 10px;
    padding: 1.25rem;
    border: 1.5px solid;
}

.agency-info-card {
    border-color: #93c5fd;
    background: #f0f7ff;
}

.agency-name {
    font-size: 0.95rem;
    font-weight: 700;
    color: #2563eb;
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.agency-name i {
    font-size: 0.85rem;
}

.agency-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.tag {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.65rem;
    border-radius: 6px;
    font-size: 0.78rem;
    font-weight: 600;
    white-space: nowrap;
}

.tag i {
    font-size: 0.7rem;
}

.tag-purple {
    background: #ede9fe;
    color: #7c3aed;
}

.tag-blue {
    background: #dbeafe;
    color: #2563eb;
}

.tag-gray {
    background: #f3f4f6;
    color: #374151;
}

/* Policy Grid */
.policy-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

.policy-field {
    display: flex;
    flex-direction: column;
}

.policy-label {
    font-size: 0.82rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.info-icon {
    font-size: 0.75rem;
    color: #9ca3af;
    cursor: help;
}

/* Readonly Fields */
.readonly-field {
    padding: 0.6rem 0.75rem;
    border: 1.5px solid #e5e7eb;
    border-radius: 8px;
    font-size: 0.88rem;
    color: #111827;
    background: #f9fafb;
    min-height: 38px;
    display: flex;
    align-items: center;
}

.readonly-field-sm {
    padding: 0.55rem 0.75rem;
    border: 1.5px solid #e5e7eb;
    border-radius: 8px;
    font-size: 0.85rem;
    color: #111827;
    background: #f9fafb;
    width: 100%;
}

.readonly-inline {
    display: flex;
    border: 1.5px solid #e5e7eb;
    border-radius: 10px;
    overflow: hidden;
    background: #f9fafb;
}

.readonly-value {
    flex: 1;
    padding: 0.6rem 0.75rem;
    font-size: 0.85rem;
    color: #111827;
    background: transparent;
}

.readonly-type {
    border-left: 1.5px solid #e5e7eb;
    background: #f3f4f6;
    padding: 0.6rem 0.5rem;
    font-size: 0.8rem;
    font-weight: 600;
    color: #6b7280;
    white-space: nowrap;
}

/* Section Headings */
.section-heading {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-bottom: 10px;
}

.section-heading h6 {
    font-size: 0.9rem;
    font-weight: 600;
    margin: 0;
}

.section-bar {
    display: inline-block;
    width: 4px;
    height: 20px;
    border-radius: 4px;
}

.section-heading.blue .section-bar {
    background: #3b82f6;
}

.section-heading.blue h6 {
    color: #1d4ed8;
}

.section-heading.purple .section-bar {
    background: #8b5cf6;
}

.section-heading.purple h6 {
    color: #7c3aed;
}

/* Currency Section */
.currency-section {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
    padding: 0.25rem 0;
}

.currency-label {
    font-size: 0.9rem;
    font-weight: 600;
    color: #374151;
}

.currency-value {
    font-size: 0.9rem;
    font-weight: 700;
    color: #111827;
    padding: 0.3rem 0.75rem;
    background: #f0f7ff;
    border-radius: 6px;
    border: 1px solid #bfdbfe;
}

.currency-divider {
    width: 1px;
    height: 20px;
    background: #e5e7eb;
    margin: 0 0.25rem;
}

/* Fare Cards */
.fare-cards-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
    margin-bottom: 1rem;
}

.fare-card {
    background: #fff;
    border-radius: 12px;
    padding: 1.3rem;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
    position: relative;
    border: 1.5px solid #e5e7eb;
}

.fare-badge {
    display: inline-block;
    padding: 0.2rem 0.75rem;
    border-radius: 6px;
    font-size: 0.78rem;
    font-weight: 700;
    margin-bottom: 0.75rem;
}

.badge-adult {
    background: #2563eb;
    color: #fff;
}

.badge-child {
    background: #f59e0b;
    color: #fff;
}

.badge-infant {
    background: #10b981;
    color: #fff;
}

.fare-fields {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.fare-field {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.fare-field label {
    font-size: 0.82rem;
    font-weight: 600;
    color: #6b7280;
    white-space: nowrap;
}

.fare-value {
    font-size: 0.88rem;
    font-weight: 700;
    color: #111827;
}

.fare-total {
    margin-top: 0.75rem;
    padding-top: 0.6rem;
    border-top: 1px solid #e5e7eb;
    font-size: 0.88rem;
    font-weight: 700;
    color: #2563eb;
    text-align: center;
}

/* Est. Total Fare */
.est-total-fare-row {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
}

.est-total-fare-box {
    flex: 1;
    background: #f0f7ff;
    border: 1.5px solid #93c5fd;
    border-radius: 12px;
    padding: 1.25rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.est-label {
    font-size: 0.88rem;
    font-weight: 600;
    color: #6b7280;
}

.est-value {
    font-size: 18px;
    font-weight: 800;
    color: #2563eb;
    white-space: nowrap;
}

/* Markup Row */
.markup-row {
    display: flex;
    align-items: flex-end;
    gap: 1rem;
    margin-bottom: 1rem;
}

.markup-field {
    flex: 1;
}

.field-label {
    display: block;
    font-size: 0.82rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.5rem;
}

/* Net Payable */
.net-payable-row {
    display: flex;
    gap: 1rem;
}

.net-payable-box {
    flex: 1;
    background: linear-gradient(135deg, #ede9fe, #faf5ff);
    border: 1.5px solid #d8b4fe;
    border-radius: 12px;
    padding: 1.25rem 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.net-label {
    font-size: 0.88rem;
    font-weight: 600;
    color: #6b7280;
}

.net-value {
    font-size: 18px;
    font-weight: 800;
    color: #7c3aed;
    white-space: nowrap;
}

/* Payment Terms */
.payment-header-row {
    display: grid;
    grid-template-columns: 1.3fr 1.3fr 1fr 1.5fr;
    gap: 0.75rem;
    padding: 0.5rem 0;
    margin-bottom: 0.5rem;
}

.ph-col {
    font-size: 0.8rem;
    font-weight: 600;
    color: #6b7280;
}

.payment-row {
    display: grid;
    grid-template-columns: 1.3fr 1.3fr 1fr 1.5fr;
    gap: 0.75rem;
    align-items: center;
    margin-bottom: 0.75rem;
}

.pr-col {
    display: flex;
    align-items: center;
}

/* Payment Rules */
.payment-rules-section {
    margin-top: 1rem;
}

.rules-display {
    width: 100%;
    padding: 0.75rem;
    border: 1.5px solid #e5e7eb;
    border-radius: 10px;
    font-size: 0.88rem;
    font-family: inherit;
    background: #f9fafb;
    color: #374151;
    line-height: 1.6;
    white-space: pre-wrap;
}

/* Nav Actions */
.nav-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid #f3f4f6;
}

.btn-action {
    padding: 0.6rem 1.5rem;
    border-radius: 10px;
    font-size: 0.875rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    transition: all 0.2s ease;
    font-family: inherit;
}

.btn-back {
    background: #f3f4f6;
    color: #374151;
}

.btn-back:hover {
    background: #e5e7eb;
    color: #111827;
}

/* Loading State */
.view-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 400px;
}

.spinner-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1.25rem;
    color: #6b7280;
    font-size: 0.9rem;
    font-weight: 500;
}

.spinner-ring {
    width: 44px;
    height: 44px;
    border: 3.5px solid #e5e7eb;
    border-top-color: #3b82f6;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* Empty State */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 400px;
    text-align: center;
    gap: 0.75rem;
}

.empty-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: #9ca3af;
    margin-bottom: 0.5rem;
}

.empty-state h5 {
    font-size: 1.15rem;
    font-weight: 600;
    color: #374151;
    margin: 0;
}

.empty-state p {
    font-size: 0.875rem;
    color: #9ca3af;
    margin: 0 0 1rem;
    max-width: 360px;
}

.btn-back {
    padding: 0.6rem 1.5rem;
    border-radius: 10px;
    font-size: 0.875rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    background: #f3f4f6;
    color: #374151;
}

.btn-back:hover {
    background: #e5e7eb;
}

/* Responsive */
@media (max-width: 991.98px) {
    .fare-cards-row {
        grid-template-columns: 1fr;
    }

    .payment-header-row,
    .payment-row {
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }

    .ph-col:not(:first-child) {
        display: none;
    }
}

@media (max-width: 767.98px) {
    .policy-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .markup-row {
        flex-direction: column;
        align-items: stretch;
    }

    .est-total-fare-row,
    .net-payable-row {
        flex-direction: column;
    }
}

@media (max-width: 575.98px) {
    .price-offer-page {
        padding: 1rem;
    }

    .policy-grid {
        grid-template-columns: 1fr;
    }
}

/* Dark Mode */
[data-bs-theme="dark"] .price-offer-page {
    background: #0f172a;
}

[data-bs-theme="dark"] .section-card {
    background: #1e293b;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.2);
}

[data-bs-theme="dark"] .section-header:hover {
    background: #334155;
}

[data-bs-theme="dark"] .section-title {
    color: #e2e8f0;
}

[data-bs-theme="dark"] .agency-info-card {
    border-color: #3b82f6;
    background: rgba(59, 130, 246, 0.1);
}

[data-bs-theme="dark"] .agency-name {
    color: #60a5fa;
}

[data-bs-theme="dark"] .tag-purple {
    background: rgba(124, 58, 237, 0.15);
    color: #a78bfa;
}

[data-bs-theme="dark"] .tag-blue {
    background: rgba(59, 130, 246, 0.15);
    color: #60a5fa;
}

[data-bs-theme="dark"] .tag-gray {
    background: #334155;
    color: #e2e8f0;
}

[data-bs-theme="dark"] .policy-label {
    color: #e2e8f0;
}

[data-bs-theme="dark"] .readonly-field,
[data-bs-theme="dark"] .readonly-field-sm,
[data-bs-theme="dark"] .readonly-inline,
[data-bs-theme="dark"] .readonly-value {
    background: #334155;
    border-color: #475569;
    color: #e2e8f0;
}

[data-bs-theme="dark"] .readonly-type {
    background: #475569;
    border-left-color: #475569;
    color: #94a3b8;
}

[data-bs-theme="dark"] .fare-card {
    background: #1e293b;
    border-color: #475569;
}

[data-bs-theme="dark"] .fare-field label {
    color: #94a3b8;
}

[data-bs-theme="dark"] .fare-value {
    color: #e2e8f0;
}

[data-bs-theme="dark"] .fare-total {
    border-top-color: #475569;
}

[data-bs-theme="dark"] .est-total-fare-box {
    background: rgba(59, 130, 246, 0.1);
    border-color: #3b82f6;
}

[data-bs-theme="dark"] .est-label,
[data-bs-theme="dark"] .field-label {
    color: #94a3b8;
}

[data-bs-theme="dark"] .net-payable-box {
    background: rgba(124, 58, 237, 0.1);
    border-color: #7c3aed;
}

[data-bs-theme="dark"] .rules-display {
    background: #334155;
    border-color: #475569;
    color: #e2e8f0;
}

[data-bs-theme="dark"] .currency-value {
    background: rgba(59, 130, 246, 0.1);
    border-color: #3b82f6;
    color: #60a5fa;
}

[data-bs-theme="dark"] .currency-label {
    color: #e2e8f0;
}

[data-bs-theme="dark"] .currency-radio {
    color: #e2e8f0;
}

/* Group Request Detail Modal */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(17, 24, 39, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1050;
    padding: 1rem;
}

.modal-box {
    background: #fff;
    border-radius: 14px;
    width: 100%;
    max-width: 540px;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
}

.modal-box.modal-lg {
    max-width: 720px;
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.1rem 1.25rem;
    border-bottom: 1px solid #f3f4f6;
}

.modal-header h5 {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: #111827;
}

.modal-close {
    border: none;
    background: transparent;
    color: #9ca3af;
    cursor: pointer;
    font-size: 1rem;
}

.modal-close:hover {
    color: #374151;
}

.modal-body {
    padding: 1.25rem;
    max-height: 65vh;
    overflow-y: auto;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    border-top: 1px solid #f3f4f6;
}

.gr-detail-grid {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.gr-detail-section {
    background: #f9fafb;
    border-radius: 10px;
    padding: 1rem 1.1rem;
    border: 1px solid #e5e7eb;
}

.gr-section-title {
    font-size: 0.85rem;
    font-weight: 700;
    color: #2563eb;
    margin: 0 0 0.75rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #e5e7eb;
}

.gr-detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.4rem 0;
    gap: 1rem;
}

.gr-detail-row:not(:last-child) {
    border-bottom: 1px dashed #f0f0f0;
}

.gr-label {
    font-size: 0.8rem;
    font-weight: 600;
    color: #6b7280;
    white-space: nowrap;
}

.gr-value {
    font-size: 0.85rem;
    font-weight: 600;
    color: #111827;
    text-align: right;
}

.gr-segment-item {
    margin-bottom: 0.5rem;
}

.gr-segment-item:last-child {
    margin-bottom: 0;
}

.gr-segment-badge {
    display: inline-block;
    padding: 0.15rem 0.5rem;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 700;
    background: #ede9fe;
    color: #7c3aed;
    margin-bottom: 0.35rem;
}

.btn-gr-close {
    padding: 0.6rem 1.25rem;
    border-radius: 10px;
    font-size: 0.875rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    background: #f3f4f6;
    color: #374151;
}

.btn-gr-close:hover {
    background: #e5e7eb;
}

/* Dark Mode - Modal */
[data-bs-theme="dark"] .modal-overlay {
    background: rgba(0, 0, 0, 0.7);
}

[data-bs-theme="dark"] .modal-box {
    background: #1e293b;
}

[data-bs-theme="dark"] .modal-header {
    border-bottom-color: #334155;
}

[data-bs-theme="dark"] .modal-header h5 {
    color: #e2e8f0;
}

[data-bs-theme="dark"] .modal-close {
    color: #94a3b8;
}

[data-bs-theme="dark"] .modal-close:hover {
    color: #e2e8f0;
}

[data-bs-theme="dark"] .modal-footer {
    border-top-color: #334155;
}

[data-bs-theme="dark"] .gr-detail-section {
    background: #0f172a;
    border-color: #334155;
}

[data-bs-theme="dark"] .gr-section-title {
    color: #60a5fa;
    border-bottom-color: #334155;
}

[data-bs-theme="dark"] .gr-detail-row:not(:last-child) {
    border-bottom-color: #334155;
}

[data-bs-theme="dark"] .gr-label {
    color: #94a3b8;
}

[data-bs-theme="dark"] .gr-value {
    color: #e2e8f0;
}

[data-bs-theme="dark"] .btn-gr-close {
    background: #334155;
    color: #e2e8f0;
}

[data-bs-theme="dark"] .btn-gr-close:hover {
    background: #475569;
}

/* Action Sidebar */
.action-sidebar {
    position: sticky;
    top: 1.5rem;
}

.action-card {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
    padding: 1.5rem;
    border: 1.5px solid #e5e7eb;
}

.action-card.status-accepted {
    border-color: #10b981;
    background: linear-gradient(135deg, #f0fdf4, #dcfce7);
}

.action-card.status-declined {
    border-color: #ef4444;
    background: linear-gradient(135deg, #fef2f2, #fee2e2);
}

.action-title {
    font-size: 1rem;
    font-weight: 700;
    color: #111827;
    margin: 0 0 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.status-accepted .action-title {
    color: #059669;
}

.status-declined .action-title {
    color: #dc2626;
}

.action-desc {
    font-size: 0.88rem;
    color: #6b7280;
    margin: 0 0 1.25rem;
    line-height: 1.5;
}

.decline-reason {
    font-size: 0.85rem;
    color: #374151;
    margin: 0.75rem 0 0;
    padding: 0.75rem;
    background: #fff;
    border-radius: 8px;
    border: 1px solid #fecaca;
}

.decline-reason strong {
    color: #dc2626;
}

.action-buttons {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.btn-accept {
    padding: 0.75rem 1.5rem;
    border-radius: 10px;
    font-size: 0.9rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    background: linear-gradient(135deg, #10b981, #059669);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
}

.btn-accept:hover:not(:disabled) {
    background: linear-gradient(135deg, #059669, #047857);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.btn-accept:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-decline {
    padding: 0.75rem 1.5rem;
    border-radius: 10px;
    font-size: 0.9rem;
    font-weight: 600;
    border: 1.5px solid #ef4444;
    cursor: pointer;
    background: #fff;
    color: #ef4444;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
}

.btn-decline:hover:not(:disabled) {
    background: #fef2f2;
    border-color: #dc2626;
    color: #dc2626;
}

.btn-decline:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Decline Modal */
.decline-form {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.decline-label {
    font-size: 0.88rem;
    font-weight: 600;
    color: #374151;
}

.required {
    color: #ef4444;
}

.decline-textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1.5px solid #e5e7eb;
    border-radius: 10px;
    font-size: 0.9rem;
    font-family: inherit;
    resize: vertical;
    outline: none;
    transition: border-color 0.15s;
}

.decline-textarea:focus {
    border-color: #ef4444;
}

.decline-textarea::placeholder {
    color: #9ca3af;
}

.decline-hint {
    font-size: 0.8rem;
    color: #9ca3af;
    font-style: italic;
}

.btn-decline-confirm {
    padding: 0.65rem 1.5rem;
    border-radius: 10px;
    font-size: 0.9rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    background: #ef4444;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    transition: all 0.15s;
}

.btn-decline-confirm:hover:not(:disabled) {
    background: #dc2626;
}

.btn-decline-confirm:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Dark Mode - Action Sidebar */
[data-bs-theme="dark"] .action-card {
    background: #1e293b;
    border-color: #475569;
}

[data-bs-theme="dark"] .action-card.status-accepted {
    background: rgba(16, 185, 129, 0.1);
    border-color: #10b981;
}

[data-bs-theme="dark"] .action-card.status-declined {
    background: rgba(239, 68, 68, 0.1);
    border-color: #ef4444;
}

[data-bs-theme="dark"] .action-title {
    color: #e2e8f0;
}

[data-bs-theme="dark"] .status-accepted .action-title {
    color: #34d399;
}

[data-bs-theme="dark"] .status-declined .action-title {
    color: #f87171;
}

[data-bs-theme="dark"] .action-desc {
    color: #94a3b8;
}

[data-bs-theme="dark"] .decline-reason {
    background: #0f172a;
    border-color: #7f1d1d;
    color: #e2e8f0;
}

[data-bs-theme="dark"] .btn-decline {
    background: #1e293b;
}

[data-bs-theme="dark"] .btn-decline:hover:not(:disabled) {
    background: rgba(239, 68, 68, 0.1);
}

[data-bs-theme="dark"] .decline-label,
[data-bs-theme="dark"] .action-title {
    color: #e2e8f0;
}

[data-bs-theme="dark"] .decline-textarea {
    background: #334155;
    border-color: #475569;
    color: #e2e8f0;
}

[data-bs-theme="dark"] .decline-textarea::placeholder {
    color: #64748b;
}
</style>

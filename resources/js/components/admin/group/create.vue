<script setup>
import AppBreadcrumbs from '../../common/AppBreadcrumbs.vue';
import { ref, computed, watch } from "vue";
import Select2 from '../../common/Select2.vue';
import SingleSectorPickerField from '../../common/SingleSectorPickerField.vue';
import { useRouter } from 'vue-router';
import axiosInstance from "../../../axiosInstance";
import { useAuthStore } from '../../../stores/authStore';
import moment from "moment";
import AppDatePicker from '../../common/AppDateTimePicker.vue';

const authStore = useAuthStore();
const router = useRouter();

// ---- Trip type (One Way / Round Way / Multi City) ----
const tripType = ref('oneway');

const tripTypes = [
    { value: 'oneway', label: 'One Way' },
    { value: 'roundway', label: 'Round Way' },
    { value: 'multicity', label: 'Multi City' },
];

// ---- Shared / One-way / Round-way fields ----
const form = ref({
    groupType: '',
    from: '',
    to: '',
    departureDate: '',
    returnFrom: '',
    returnTo: '',
    returnDate: '',
    preferredAirlines: '',
    flightNo: '',
    preferredClass: '',
    code: '',
    adult: '',
    children: '',
    infants: '',
    currency: 'BDT',
    perPersonFare: '',
    specialRequirements: '',
    detailsRequirements: '',
});

// ---- Multi city flights ----
const multiCityFlights = ref([
    { from: '', to: '', departureDate: '' },
    { from: '', to: '', departureDate: '' },
]);

const preferredAirlineMulti = ref('');

function addFlight() {
    multiCityFlights.value.push({ from: '', to: '', departureDate: '' });
}

function removeFlight(index) {
    if (multiCityFlights.value.length > 2) {
        multiCityFlights.value.splice(index, 1);
    }
}

// Reset fields on trip type change
watch(tripType, (newType) => {
    if (newType !== 'roundway') {
        form.value.returnFrom = '';
        form.value.returnTo = '';
        form.value.returnDate = '';
    }
    if (newType !== 'multicity') {
        multiCityFlights.value = [
            { from: '', to: '', departureDate: '' },
            { from: '', to: '', departureDate: '' },
        ];
        preferredAirlineMulti.value = '';
    } else {
        form.value.flightNo = '';
    }
});

// ---- Total passengers ----
const totalPassengers = computed(() => {
    const adult = Number(form.value.adult) || 0;
    const children = Number(form.value.children) || 0;
    const infants = Number(form.value.infants) || 0;
    const total = adult + children + infants;
    return total > 0 ? total : '';
});

// ---- Options ----
const groupTypeOptions = [
    { value: 'tour', label: 'Tour' },
    { value: 'corporate_travel', label: 'Corporate Travel' },
    { value: 'hajj', label: 'Hajj' },
    { value: 'umrah', label: 'Umrah' },
    { value: 'sports', label: 'Sports' },
    { value: 'family_event', label: 'Family Event' },
    { value: 'labour', label: 'Labour' },
    { value: 'mice', label: 'MICE' }
];

// ─── Dropdown data ───
const airportOptions = ref([]);
const airlinesOptions = ref([]);

getAllAirlines();
async function getAllAirlines() {
    try {
        const response = await axiosInstance.get('getAllAirlines');
        airlinesOptions.value = response.data.data.map(airlines => airlines.a_name);
    } catch (error) {
        console.error('Failed to fetch airlines:', error);
    }
}

getAllairports();
async function getAllairports() {
    try {
        const response = await axiosInstance.get('airports');
        airportOptions.value = response.data.map(airport => airport.code);
    } catch (error) {
        console.error('Failed to fetch airports:', error);
    }
}
const preferredClassOptions = ['Economy', 'Premium Economy', 'Business', 'First'];
const rbdCodeOptions = [
    'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'
];
const specialRequirementOptions = ['Wheelchair', 'Special Meal', 'Extra Baggage', 'Infant Bassinet', 'Medical Assistance'];
const preferredAirlineOptions = ['Emirates', 'Qatar Airways', 'Biman Bangladesh', 'Turkish Airlines'];

function goBack() {
    router.push({ name: 'groupList' });
}

async function submitForm() {
    const payload = {
        tripType: tripType.value,
        ...form.value,
        totalPassengers: totalPassengers.value,
        ...(tripType.value === 'multicity'
            ? { flights: multiCityFlights.value, preferredAirline: preferredAirlineMulti.value }
            : {}),
    };

    try {
        await axiosInstance.post('/group-requests', payload);
        router.push({ name: 'groupList' });
    } catch (error) {
        console.error('Failed to submit group request', error);
    }
}
</script>

<template>
    <div class="group-page">
        <AppBreadcrumbs title="Create Group Request" :back-to="{ name: 'groupList' }" :breadcrumbs="[
            { label: 'Dashboard', to: { name: 'Home' } },
            { label: 'Group Management', to: { name: 'groupList' } },
            { label: 'Create Group Request' }]">
        </AppBreadcrumbs>

        <div class="row g-4">
            <!-- Main Form Card -->
            <div class="col-12 col-lg-8">
                <div class="rule-card">
                    <!-- Trip Type Pills -->
                    <div class="tab-pills-wrapper">
                        <div class="tab-pills">
                            <button v-for="type in tripTypes" :key="type.value" type="button"
                                @click="tripType = type.value" class="tab-pill"
                                :class="{ active: tripType === type.value }">
                                {{ type.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Tab Content -->
                    <div class="tab-content-area">

                        <!-- Group Type Section -->
                        <div class="form-section">
                            <div class="section-heading blue">
                                <span class="section-bar"></span>
                                <h5>Group Information</h5>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="field-label">Group Type</label>
                                    <Select2 v-model="form.groupType" :options="groupTypeOptions" :clearable="false"
                                        value-key="value" label-key="label" />
                                </div>
                            </div>
                        </div>

                        <div class="section-divider"></div>

                        <!-- ONE WAY / ROUND WAY -->
                        <template v-if="tripType !== 'multicity'">
                            <div class="form-section">
                                <div class="section-heading purple">
                                    <span class="section-bar"></span>
                                    <h5>{{ tripType === 'roundway' ? 'Outbound Flight' : 'Flight Details' }}</h5>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <SingleSectorPickerField v-model="form.from" label="From"
                                            all-label="All Departure Airports" entity-name="airports"
                                            :options="airportOptions" />
                                    </div>
                                    <div class="col-md-4">
                                        <SingleSectorPickerField v-model="form.to" label="To"
                                            all-label="All Departure Airports" entity-name="airports"
                                            :options="airportOptions" />
                                    </div>
                                    <div class="col-md-4">
                                        <label class="field-label">Departure Date</label>
                                        <AppDatePicker v-model="form.departureDate" :options="datePickerOptions" :enableTimePicker="true"  :inputClass="`form-control form-control-md`"/>
                                    </div>
                                </div>
                            </div>

                            <!-- Return Flight (Round Way only) -->
                            <template v-if="tripType === 'roundway'">
                                <div class="section-divider"></div>
                                <div class="form-section">
                                    <div class="section-heading teal">
                                        <span class="section-bar"></span>
                                        <h5>Return Flight</h5>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <SingleSectorPickerField v-model="form.returnFrom" label="Return From"
                                                all-label="Return From" entity-name="airports"
                                                :options="airportOptions" />
                                        </div>
                                        <div class="col-md-4">

                                            <SingleSectorPickerField v-model="form.returnTo" label="Return To"
                                                all-label="Return From" entity-name="airports"
                                                :options="airportOptions"/>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="field-label">Return Date</label>
                                            <AppDatePicker v-model="form.returnDate" :options="datePickerOptions" :enableTimePicker="true"  :inputClass="`form-control form-control-md`"/>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <div class="section-divider"></div>

                            <div class="form-section">
                                <div class="section-heading blue">
                                    <span class="section-bar"></span>
                                    <h5>Flight Preferences</h5>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <SingleSectorPickerField v-model="form.preferredAirlines"
                                            label="Preferred Airlines" all-label="To" entity-name="airports"
                                            :options="airlinesOptions" />
                                    </div>
                                    <div class="col-md-6">
                                        <label class="field-label">Flight No.</label>
                                        <input v-model="form.flightNo" type="text" class="field-input"
                                            placeholder="Flight No." />
                                    </div>
                                    <div class="col-md-6">
                                        <label class="field-label">Preferred Class</label>
                                        <select v-model="form.preferredClass" class="field-select">
                                            <option value="" disabled selected>Select Class</option>
                                            <option v-for="opt in preferredClassOptions" :key="opt" :value="opt">{{
                                                opt }}</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="field-label">Code (RBD)</label>
                                        <select v-model="form.code" class="field-select">
                                            <option value="" disabled selected>Code</option>
                                            <option v-for="opt in rbdCodeOptions" :key="opt" :value="opt">{{ opt }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- MULTI CITY -->
                        <template v-else>
                            <div class="form-section">
                                <div class="section-heading purple">
                                    <span class="section-bar"></span>
                                    <h5>Flight Segments</h5>
                                </div>

                                <div v-for="(flight, index) in multiCityFlights" :key="index"
                                    class="flight-segment-block">
                                    <div class="segment-header" v-if="multiCityFlights.length > 1">
                                        <span class="segment-number">Segment {{ index + 1 }}</span>
                                        <button v-if="multiCityFlights.length > 2" type="button"
                                            @click="removeFlight(index)" class="segment-remove-btn"
                                            title="Remove segment">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <SingleSectorPickerField v-model="flight.from" label="From" all-label="From"
                                                entity-name="airports" :options="airportOptions" />
                                        </div>
                                        <div class="col-md-4">
                                            <SingleSectorPickerField v-model="flight.to" label="From" all-label="To"
                                                entity-name="airports" :options="airportOptions" />
                                        </div>
                                        <div class="col-md-4">
                                            <label class="field-label">Departure Date</label>
                                            <AppDatePicker v-model="flight.departureDate" :options="datePickerOptions" :enableTimePicker="true"  :inputClass="`form-control form-control-md`"/>
                                        </div>
                                    </div>
                                </div>

                                <button type="button" @click="addFlight" class="add-segment-btn">
                                    <i class="bi bi-plus-circle me-1"></i>Add Another Segment
                                </button>
                            </div>

                            <div class="section-divider"></div>

                            <div class="form-section">
                                <div class="section-heading blue">
                                    <span class="section-bar"></span>
                                    <h5>Flight Preferences</h5>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <SingleSectorPickerField v-model="form.preferredAirlines"
                                            label="Preferred Airlines" all-label="To" entity-name="airports"
                                            :options="airlinesOptions" />
                                    </div>
                                    <div class="col-md-6">
                                        <label class="field-label">Preferred Class</label>
                                        <select v-model="form.preferredClass" class="field-select">
                                            <option value="" disabled selected>Select Class</option>
                                            <option v-for="opt in preferredClassOptions" :key="opt" :value="opt">{{
                                                opt }}</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="field-label">Code (RBD)</label>
                                        <select v-model="form.code" class="field-select">
                                            <option value="" disabled selected>Code</option>
                                            <option v-for="opt in rbdCodeOptions" :key="opt" :value="opt">{{ opt }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <div class="section-divider"></div>

                        <!-- Passengers Section -->
                        <div class="form-section">
                            <div class="section-heading teal">
                                <span class="section-bar"></span>
                                <h5>Passengers</h5>
                            </div>
                            <div class="row g-3">
                                <div class="col-6 col-md-3">
                                    <label class="field-label">Adult</label>
                                    <input v-model="form.adult" type="number" min="0" class="field-input"
                                        placeholder="Adults" />
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="field-label">Children</label>
                                    <input v-model="form.children" type="number" min="0" class="field-input"
                                        placeholder="Children" />
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="field-label">Infants</label>
                                    <input v-model="form.infants" type="number" min="0" class="field-input"
                                        placeholder="Infants" />
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="field-label">Total Passengers</label>
                                    <input :value="totalPassengers" type="text" readonly class="field-input total-field"
                                        placeholder="Total" />
                                </div>
                            </div>
                        </div>

                        <div class="section-divider"></div>

                        <!-- Currency & Fare Section -->
                        <div class="form-section">
                            <div class="section-heading green">
                                <span class="section-bar"></span>
                                <h5>Fare Details</h5>
                            </div>
                            <div class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label class="field-label">Currency</label>
                                    <div class="radio-group">
                                        <label class="radio-wrap" v-for="curr in ['BDT', 'USD']" :key="curr">
                                            <input type="radio" :value="curr" v-model="form.currency"
                                                :id="'curr' + curr" class="radio-input" />
                                            <span class="radio-box"></span>
                                            <span class="radio-label">{{ curr }}</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="field-label">Per Person Requested Fare ({{ form.currency }})</label>
                                    <input v-model="form.perPersonFare" type="number" min="0" class="field-input"
                                        placeholder="Enter Fare" />
                                </div>
                            </div>
                        </div>

                        <div class="section-divider"></div>

                        <!-- Requirements Section -->
                        <div class="form-section">
                            <div class="section-heading purple">
                                <span class="section-bar"></span>
                                <h5>Requirements</h5>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="field-label">Special Requirements</label>
                                    <select v-model="form.specialRequirements" class="field-select">
                                        <option value="" disabled selected>Select Special Requirements</option>
                                        <option v-for="opt in specialRequirementOptions" :key="opt" :value="opt">{{
                                            opt }}</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="field-label">Details Requirements</label>
                                    <textarea v-model="form.detailsRequirements" rows="4"
                                        class="field-input field-textarea"
                                        placeholder="Enter details requirements of your group"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Navigation Buttons -->
                        <div class="nav-actions">
                            <button @click="goBack" class="btn-action btn-back">
                                <i class="bi bi-arrow-left me-1"></i>Back
                            </button>
                            <div class="nav-right">
                                <button @click="submitForm" class="btn-action btn-save">
                                    <i class="bi bi-send me-1"></i>Submit Request
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar: Policy Cards -->
            <div class="col-12 col-lg-4">
                <div class="sidebar-sticky">
                    <div class="policy-card" v-for="(policy, idx) in sidebarPolicies" :key="idx">
                        <div class="policy-header" :class="policy.colorClass">
                            <span class="policy-icon">
                                <i :class="policy.icon"></i>
                            </span>
                            <h6 class="policy-title">{{ policy.title }}</h6>
                        </div>
                        <ul class="policy-list">
                            <li v-for="(item, iIdx) in policy.items" :key="iIdx" v-html="item"></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    data() {
        return {
            sidebarPolicies: [
                {
                    title: 'Deposit Policy',
                    icon: 'fa fa-money-bill-transfer',
                    colorClass: 'policy-blue',
                    items: [
                        'A non-refundable deposit (usually <strong>10–30%</strong> of total fare) must be paid within <strong>24–72 hours</strong> of receiving the quotation to hold the seats.',
                        'Failure to pay deposit within the deadline will result in <strong>automatic cancellation</strong> of the reserved seats.'
                    ]
                },
                {
                    title: 'Fare Rules',
                    icon: 'fa fa-ruler',
                    colorClass: 'policy-purple',
                    items: [
                        'Group fares are negotiated and fixed once agreed but are <strong>not available publicly</strong> online.',
                        'Fares are usually higher than promotional fares but offer flexibility (name changes, payment terms).',
                        'Quoted fares include taxes and surcharges, but exclude optional services (meals, baggage, etc.).'
                    ]
                },
                {
                    title: 'Cancellation & Refund',
                    icon: 'fa fa-circle-minus',
                    colorClass: 'policy-red',
                    items: [
                        '<strong>Before ticket issuance:</strong> Deposit is usually non-refundable.',
                        '<strong>After ticket issuance:</strong> Cancellation penalties apply per ticket. Group tickets are often non-refundable or only partially refundable.',
                        'No-show passengers may be charged <strong>100% penalty</strong>.'
                    ]
                }
            ]
        };
    }
};
</script>

<style scoped>
/* ─── Page ─────────────────────────────────────── */
.group-page {
    padding: 1.5rem;
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
}

/* ─── Card ──────────────────────────────────────── */
.rule-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
    overflow: hidden;
}

/* ─── Tab Pills ─────────────────────────────────── */
.tab-pills-wrapper {
    padding: 1.25rem 1.75rem 0;
    background: #f9fafb;
    border-bottom: 1px solid #f0f0f0;
}

.tab-pills {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    padding-bottom: 1rem;
    justify-content: center;
}

.tab-pill {
    padding: 0.45rem 1.4rem;
    border-radius: 50px;
    border: 1.5px solid #e5e7eb;
    background: #fff;
    color: #6b7280;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.tab-pill:hover {
    border-color: #93c5fd;
    color: #3b82f6;
}

.tab-pill.active {
    background: #3b82f6;
    border-color: #3b82f6;
    color: #fff;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
}

/* ─── Tab Content ───────────────────────────────── */
.tab-content-area {
    padding: 2rem 1.75rem;
}

/* ─── Form Sections ─────────────────────────────── */
.form-section {
    margin-bottom: 0.5rem;
}

.section-divider {
    border: none;
    border-top: 1px dashed #e5e7eb;
    margin: 1.5rem 0;
}

.section-heading {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-bottom: 1.25rem;
}

.section-heading h5 {
    font-size: 1rem;
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

.section-heading.blue h5 {
    color: #1d4ed8;
}

.section-heading.purple .section-bar {
    background: #8b5cf6;
}

.section-heading.purple h5 {
    color: #7c3aed;
}

.section-heading.teal .section-bar {
    background: #14b8a6;
}

.section-heading.teal h5 {
    color: #0d9488;
}

.section-heading.green .section-bar {
    background: #10b981;
}

.section-heading.green h5 {
    color: #059669;
}

/* ─── Fields ────────────────────────────────────── */
.field-label {
    display: block;
    font-size: 0.82rem;
    font-weight: 500;
    color: #374151;
    margin-bottom: 0.4rem;
}

.field-input {
    width: 100%;
    padding: 0.55rem 0.85rem;
    border: 1.5px solid #e5e7eb;
    border-radius: 10px;
    font-size: 0.875rem;
    color: #111827;
    background: #fff;
    transition: all 0.2s ease;
    outline: none;
    font-family: inherit;
}

.field-input::placeholder {
    color: #9ca3af;
}

.field-input:hover {
    border-color: #93c5fd;
}

.field-input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.field-select {
    width: 100%;
    padding: 0.55rem 0.85rem;
    border: 1.5px solid #e5e7eb;
    border-radius: 10px;
    font-size: 0.875rem;
    color: #111827;
    background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") no-repeat right 0.75rem center;
    background-size: 14px;
    appearance: none;
    -webkit-appearance: none;
    cursor: pointer;
    transition: all 0.2s ease;
    outline: none;
    font-family: inherit;
    padding-right: 2.25rem;
}

.field-select:hover {
    border-color: #93c5fd;
}

.field-select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* ─── Date Input ────────────────────────────────── */
.date-input-wrap {
    position: relative;
}

.date-input-wrap .field-input {
    padding-right: 2.5rem;
}

.date-icon {
    position: absolute;
    right: 0.85rem;
    top: 50%;
    transform: translateY(-50%);
    color: #3b82f6;
    font-size: 0.9rem;
    pointer-events: none;
}

/* ─── Textarea ──────────────────────────────────── */
.field-textarea {
    resize: vertical;
    min-height: 100px;
    line-height: 1.5;
}

/* ─── Total Field ───────────────────────────────── */
.total-field {
    background: #f0f9ff;
    border-color: #bae6fd;
    color: #0369a1;
    font-weight: 600;
    cursor: default;
}

.total-field:hover {
    border-color: #bae6fd;
}

.total-field:focus {
    border-color: #bae6fd;
    box-shadow: none;
}

/* ─── Radio Group ───────────────────────────────── */
.radio-group {
    display: flex;
    gap: 1.25rem;
    padding-top: 0.35rem;
}

.radio-wrap {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    user-select: none;
}

.radio-input {
    display: none;
}

.radio-box {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    border: 2px solid #d1d5db;
    position: relative;
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.radio-input:checked+.radio-box {
    border-color: #3b82f6;
}

.radio-input:checked+.radio-box::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #3b82f6;
}

.radio-label {
    font-size: 0.875rem;
    font-weight: 500;
    color: #374151;
}

/* ─── Multi City Segments ───────────────────────── */
.flight-segment-block {
    padding: 1rem 1.15rem;
    background: #f9fafb;
    border: 1.5px solid #f3f4f6;
    border-radius: 12px;
    margin-bottom: 0.75rem;
    transition: border-color 0.2s ease;
}

.flight-segment-block:hover {
    border-color: #e5e7eb;
}

.flight-segment-block .field-label {
    font-size: 0.78rem;
}

.segment-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.75rem;
}

.segment-number {
    font-size: 0.75rem;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.segment-remove-btn {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    border: none;
    background: #fef2f2;
    color: #ef4444;
    font-size: 0.7rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.segment-remove-btn:hover {
    background: #ef4444;
    color: #fff;
}

.add-segment-btn {
    display: inline-flex;
    align-items: center;
    padding: 0.4rem 1rem;
    border: 1.5px dashed #cbd5e1;
    border-radius: 10px;
    background: transparent;
    color: #3b82f6;
    font-size: 0.82rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    font-family: inherit;
    margin-top: 0.25rem;
}

.add-segment-btn:hover {
    border-color: #3b82f6;
    background: #eff6ff;
}

/* ─── Navigation Actions ────────────────────────── */
.nav-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid #f3f4f6;
}

.nav-right {
    display: flex;
    gap: 0.75rem;
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

.btn-save {
    background: #3b82f6;
    color: #fff;
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.25);
}

.btn-save:hover {
    background: #2563eb;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.35);
    transform: translateY(-1px);
}

.btn-save:active {
    transform: translateY(0);
}

/* ─── Sidebar ───────────────────────────────────── */
.sidebar-sticky {
    position: sticky;
    top: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.policy-card {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
    overflow: hidden;
    transition: box-shadow 0.2s ease;
}

.policy-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.policy-header {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.9rem 1.15rem;
}

.policy-blue {
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    border-bottom: 2px solid #bfdbfe;
}

.policy-purple {
    background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%);
    border-bottom: 2px solid #ddd6fe;
}

.policy-red {
    background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
    border-bottom: 2px solid #fecaca;
}

.policy-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.policy-blue .policy-icon {
    background: #3b82f6;
    color: #fff;
}

.policy-purple .policy-icon {
    background: #8b5cf6;
    color: #fff;
}

.policy-red .policy-icon {
    background: #ef4444;
    color: #fff;
}

.policy-title {
    font-size: 0.88rem;
    font-weight: 600;
    margin: 0;
}

.policy-blue .policy-title {
    color: #1d4ed8;
}

.policy-purple .policy-title {
    color: #7c3aed;
}

.policy-red .policy-title {
    color: #dc2626;
}

.policy-list {
    list-style: none;
    padding: 0.85rem 1.15rem 1rem;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.policy-list li {
    font-size: 0.8rem;
    color: #6b7280;
    line-height: 1.55;
    padding-left: 1rem;
    position: relative;
}

.policy-list li::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0.5em;
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: #d1d5db;
}

.policy-blue .policy-list li::before {
    background: #93c5fd;
}

.policy-purple .policy-list li::before {
    background: #c4b5fd;
}

.policy-red .policy-list li::before {
    background: #fca5a5;
}

.policy-list li strong {
    color: #374151;
}

.policy-list li :deep(ul) {
    list-style: none;
    padding: 0.35rem 0 0 0.5rem;
    margin: 0;
}

.policy-list li :deep(ul li)::before {
    width: 4px;
    height: 4px;
    top: 0.55em;
}

/* ─── Responsive ────────────────────────────────── */
@media (max-width: 991.98px) {
    .sidebar-sticky {
        position: static;
        flex-direction: row;
        flex-wrap: wrap;
    }

    .policy-card {
        flex: 1 1 calc(50% - 0.5rem);
        min-width: 260px;
    }
}

@media (max-width: 767.98px) {
    .group-page {
        padding: 1rem;
    }

    .tab-content-area {
        padding: 1.25rem 1rem;
    }

    .tab-pills-wrapper {
        padding: 1rem 1rem 0;
    }

    .policy-card {
        flex: 1 1 100%;
    }

    .nav-actions {
        flex-direction: column-reverse;
        gap: 0.75rem;
    }

    .nav-right {
        width: 100%;
    }

    .btn-action {
        width: 100%;
        justify-content: center;
    }
}

/* ─── Number input spinner hide ─────────────────── */
.field-input[type="number"]::-webkit-inner-spin-button,
.field-input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.field-input[type="number"] {
    -moz-appearance: textfield;
}
</style>

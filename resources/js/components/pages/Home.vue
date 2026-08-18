<script setup>
import { ref, onMounted, onBeforeUnmount, reactive, computed, watch } from 'vue';
import Chart from 'chart.js/auto';
import axiosInstance from '../../axiosInstance';
// import PeriodSelector from '@/components/common/PeriodSelector.vue';
import PeriodSelector from '../common/PeriodSelector.vue';


/* ─────────────────────────────────────────
   Chart instance refs
───────────────────────────────────────── */
let bookingDonut = null;
let ticketingDonut = null;
let salesBarChart = null;
let trendingBarChart = null;
let travelerDonut = null;
let bookingClassPie = null;
let ratioAreaChart = null;
let airlinesGauge = null;
let transactionBar = null;
let searchBookingChart = null;
let supportDonut = null;

/* ─────────────────────────────────────────
   Static chart data
───────────────────────────────────────── */
const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const salesData = ref([0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]);
const travelerData = ref([0, 0, 0]);
const bookingClassData = ref([0, 0, 0, 0]);
const bookingVals = ref([0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]);
const ticketingVals = ref([0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]);
const depositData = ref([0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]);
const creditData = ref([0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]);
const airlines = ref([]);


//const totalTravelers = computed(() => travelerData.value.reduce((a, b) => a + b, 0));

//const salesData = [650000, 820000, 530000, 200000, 280000, 960000, 800000, 490000, 720000, 590000, 840000, 380000];
//const bookingVals = [300, 420, 380, 750, 620, 480, 900, 820, 700, 560, 640, 420];
//const ticketingVals = [200, 300, 260, 500, 400, 320, 620, 550, 460, 380, 450, 280];
//const depositData = [600000, 820000, 700000, 780000, 260000, 920000, 490000, 560000, 630000, 610000, 750000, 420000];
//const creditData = [200000, 300000, 280000, 300000, 100000, 420000, 210000, 240000, 280000, 250000, 310000, 150000];

const airlineColors = ['#f97316', '#06b6d4', '#eab308', '#ef4444', '#3b82f6', '#14b8a6', '#64748b', '#a855f7', '#ec4899', '#10b981'];
const airlineLabels = ['Indigo', 'Qatar Airways', 'Emirates', 'Oman Air', 'US Bangla', 'Biman Bangladesh', 'Saudia', 'Jazeera', 'Batik', 'Fly Dubai'];
const airlineValues = [18, 15, 13, 12, 10, 9, 8, 6, 5, 4];

/* ─────────────────────────────────────────
   Reactive data
───────────────────────────────────────── */
const routes = ref([]);
const searchVals = ref([0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]);
const lastTicketingInfo = ref([]);

//const bookingVals = ref([0, 0, 0, 0, 0, 60, 30, 0, 0, 0, 0, 0]);
// const routes = ref([
//   { code: 'DAC-DXB', count: 420, color: '#3b82f6', max: 500 },
//   { code: 'DAC-JFK', count: 314, color: '#06b6d4', max: 500 },
//   { code: 'DAC-JED', count: 301, color: '#8b5cf6', max: 500 },
//   { code: 'DAC-KWI', count: 257, color: '#ec4899', max: 500 },
//   { code: 'DAC-BKK', count: 167, color: '#f97316', max: 500 },
//   { code: 'DAC-MLE', count: 126, color: '#e879f9', max: 500 },
//   { code: 'DAC-DEL', count: 90, color: '#14b8a6', max: 500 },
// ]);

/* ─────────────────────────────────────────
   Upcoming Departures → per-date flight
   details modal
───────────────────────────────────────── */
const upcomingTravelDates = ref([]);
const flightDetailsByDate = ref({});

/*const upcomingTravelDates = ref([
  { date: '2026-08-12', displayDate: '12 Aug 2026', flightCount: 2, passengerCount: 14, bookingCount: 6 },
  { date: '2026-08-14', displayDate: '14 Aug 2026', flightCount: 1, passengerCount: 8, bookingCount: 3 },
  { date: '2026-08-17', displayDate: '17 Aug 2026', flightCount: 2, passengerCount: 11, bookingCount: 5 },
  { date: '2026-08-21', displayDate: '21 Aug 2026', flightCount: 1, passengerCount: 5, bookingCount: 5 },
  { date: '2026-08-25', displayDate: '25 Aug 2026', flightCount: 2, passengerCount: 9, bookingCount: 4 },
]);*/


const flightModalOpen = ref(false);
const flightModalLoading = ref(false);
const selectedDateLabel = ref('');
const selectedDateFlights = reactive({
  totalPassengers: 0,
  flights: [],
});


const openFlightModal = async (item) => {
  selectedDateLabel.value = item.displayDate;
  flightModalOpen.value = true;
  flightModalLoading.value = true;

  const details = flightDetailsByDate.value[item.date] || { totalPassengers: 0, flights: [] };
  selectedDateFlights.totalPassengers = details.totalPassengers ?? 0;
  selectedDateFlights.flights = details.flights ?? [];

  flightModalLoading.value = false;
};


const closeFlightModal = () => {
  flightModalOpen.value = false;
  document.body.style.overflow = '';
};

const handleFlightModalEsc = (e) => {
  if (e.key !== 'Escape') return;
  if (flightModalOpen.value) closeFlightModal();
  if (ticketingModalOpen.value) closeTicketingModal();
};


// Masks a phone number, keeping the leading country/area digits and the
// last two visible, e.g. "+8801700000045" -> "+880 17••••••45"
const maskPhone = (raw) => {
  if (!raw) return '';
  const digits = raw.replace(/\D/g, '');
  if (digits.length < 6) return raw;
  const hasPlus = raw.trim().startsWith('+');
  const cc = hasPlus ? '+' + digits.slice(0, digits.length - 8) : '';
  const rest = hasPlus ? digits.slice(-8) : digits;
  const visibleStart = rest.slice(0, 2);
  const visibleEnd = rest.slice(-2);
  return `${cc} ${visibleStart}${'•'.repeat(6)}${visibleEnd}`.trim();
};

const routeParts = (route) => {
  const [origin = '', destination = ''] = (route || '').split('→').map((s) => s.trim());
  return { origin, destination };
};

/* ─────────────────────────────────────────
   Ticketing Deadlines card
───────────────────────────────────────── */
const now = () => Date.now();
const hrs = (n) => n * 60 * 60 * 1000;

// Mock bookings with a last-ticketing deadline
// const ticketingBookings = ref([
//   { flightPnr: 'BG147XY', gdsPnr: '1A2B3C', airline: 'Biman Bangladesh Airlines', airlineCode: 'BG', route: 'DAC → DXB', cabinClass: 'Economy', totalPax: 1, primaryPassenger: 'Md. Rahim Ahmed', contact: '+8801700000045', lastTicketingTime: new Date(now() - hrs(2)).toISOString() },
//   { flightPnr: 'EK585QZ', gdsPnr: '4D5E6F', airline: 'Emirates', airlineCode: 'EK', route: 'DAC → DXB', cabinClass: 'Business', totalPax: 3, primaryPassenger: 'Tanvir Ahmed', contact: '+8801600000032', lastTicketingTime: new Date(now() + hrs(5)).toISOString() },
//   { flightPnr: 'SV804LM', gdsPnr: '7G8H9J', airline: 'Saudia', airlineCode: 'SV', route: 'DAC → JED', cabinClass: 'Economy', totalPax: 2, primaryPassenger: 'Jahangir Alam', contact: '+8801700000055', lastTicketingTime: new Date(now() + hrs(20)).toISOString() },
//   { flightPnr: 'QR638RT', gdsPnr: '2K3L4M', airline: 'Qatar Airways', airlineCode: 'QR', route: 'DAC → DOH', cabinClass: 'Premium Economy', totalPax: 1, primaryPassenger: 'Farhana Islam', contact: '+8801300000011', lastTicketingTime: new Date(now() + hrs(30)).toISOString() },
//   { flightPnr: 'FZ556VB', gdsPnr: '5N6P7Q', airline: 'Fly Dubai', airlineCode: 'FZ', route: 'DAC → DXB', cabinClass: 'Economy', totalPax: 4, primaryPassenger: 'Anika Tabassum', contact: '+8801900000077', lastTicketingTime: new Date(now() + hrs(60)).toISOString() },
//   { flightPnr: 'J9621CX', gdsPnr: '8R9S1T', airline: 'Jazeera Airways', airlineCode: 'J9', route: 'DAC → BKK', cabinClass: 'Economy', totalPax: 1, primaryPassenger: 'Sadia Islam', contact: '+8801900000054', lastTicketingTime: new Date(now() + hrs(95)).toISOString() },
//   { flightPnr: 'OD156GH', gdsPnr: '3U4V5W', airline: 'Batik Air', airlineCode: 'OD', route: 'DAC → BKK', cabinClass: 'First', totalPax: 2, primaryPassenger: 'Mahmuda Akter', contact: '+8801400000076', lastTicketingTime: new Date(now() + hrs(150)).toISOString() },
// ]);

// LT date is shown as two lines in the card: the date, then the time below it.
const formatLTDate = (isoTime) =>
  new Date(isoTime).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
const formatLTTime = (isoTime) =>
  new Date(isoTime).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });

const ticketingWindowOptions = [
  { value: '24h', label: '24h', ms: hrs(24) },
  { value: '3d', label: '3 Days', ms: hrs(72) },
  { value: '7d', label: '7 Days', ms: hrs(168) },
];
const ticketingWindow = ref('3d');

const ticketingUrgency = (isoTime) => {
  const diffMs = new Date(isoTime).getTime() - now();
  if (diffMs <= 0) return 'overdue';
  if (diffMs <= hrs(24)) return 'critical';
  return 'warning';
};

const ticketingCountdownLabel = (isoTime) => {
  const diffMs = new Date(isoTime).getTime() - now();
  if (diffMs <= 0) {
    const overdueBy = Math.abs(diffMs);
    const h = Math.floor(overdueBy / hrs(1));
    return h < 24 ? `${h}h ago` : `${Math.floor(h / 24)}d ago`;
  }
  const h = Math.ceil(diffMs / hrs(1));
  return h < 24 ? `${h}h left` : `${Math.ceil(h / 24)}d left`;
};


const filteredTicketingBookings = computed(() => {
  const windowMs = ticketingWindowOptions.find((o) => o.value === ticketingWindow.value)?.ms ?? hrs(72);
  return lastTicketingInfo.value
    .filter((b) => {
      const diffMs = new Date(b.lastTicketingTime).getTime() - now();
      return diffMs > 0 && diffMs <= windowMs;
    })
    .slice()
    .sort((a, b) => new Date(a.lastTicketingTime) - new Date(b.lastTicketingTime));
});

const ticketingModalOpen = ref(false);
const selectedBooking = ref(null);

const openTicketingModal = (booking) => {
  selectedBooking.value = booking;
  ticketingModalOpen.value = true;
  document.body.style.overflow = 'hidden';
};

const closeTicketingModal = () => {
  ticketingModalOpen.value = false;
  document.body.style.overflow = '';
};


const bookingStats = reactive({
  totalBookings: 0,
  confirmedBookings: 0,
  cancelledBookings: 0,
  todayBookings: 0,
  bookingRatio: 0,
});

const ticketingStats = reactive({
  totalTicketing: 0,
  ticketed: 0,
  voided: 0,
  todayTicketing: 0,
  ticketingRatio: 0,
});

const salesStats = reactive({
  totalSales: 0,
  ratio: 0,
  todaySales: 0,
})

const cabinClassData = ref({
  economy: 0,
  premium_economy: 0,
  business: 0,
  first_class: 0
});


const bookingCache = reactive({});

const bookingMonthSelection = ref(null);
const ticketingMonthSelection = ref(null);
const routesMonthSelection = ref(new Date().getMonth() + 1);
const topSellingAirlinesMonthSelection = ref(new Date().getMonth() + 1);

const fetchDashboardStats = async () => {
  try {
    const response = await axiosInstance.get('/dashboard'); // Adjust endpoint if needed
    const data = response.data?.data ?? response.data ?? {};
    console.log('Dashboard stats fetched:', data);

    // Update booking stats
    bookingStats.totalBookings = data.total_bookings ?? data.totalBookings ?? 0;
    bookingStats.confirmedBookings = data.confirmed_bookings ?? data.confirmedBookings ?? 0;
    bookingStats.cancelledBookings = data.cancelled_bookings ?? data.cancelledBookings ?? 0;
    bookingStats.todayBookings = data.today_booking ?? data.todayBookings ?? 0;
    bookingStats.bookingRatio = data.bookingRatio ?? 0;

    // Update ticketing stats
    ticketingStats.totalTicketing = data.totalTicketing ?? 0;
    ticketingStats.ticketed = data.ticketed ?? 0;
    ticketingStats.voided = data.voided ?? 0;
    ticketingStats.todayTicketing = data.todayTicketing ?? 0;
    ticketingStats.ticketingRatio = data.ticketingRatio ?? 0;

    if (data.monthlySales && Array.isArray(data.monthlySales)) {
      salesData.value = data.monthlySales;
    }
    //console.log('Monthly sales data updated:', salesData.value);

    if (data.trendingRoutes && Array.isArray(data.trendingRoutes)) {
      routes.value = data.trendingRoutes;
    }

    if (data.travelerData && Array.isArray(data.travelerData)) {
      travelerData.value = data.travelerData;
    }

    if (data.bookingClassData && Array.isArray(data.bookingClassData)) {
      bookingClassData.value = data.bookingClassData;
    }
    if (data.cabinClassData) {
      cabinClassData.value = data.cabinClassData;
    }


    if (data.monthlyBookings && Array.isArray(data.monthlyBookings)) {
      bookingVals.value = data.monthlyBookings;
    }
    if (data.monthlyTicketing && Array.isArray(data.monthlyTicketing)) {
      ticketingVals.value = data.monthlyTicketing;
    }

    if (data.depositData && Array.isArray(data.depositData)) {
      depositData.value = data.depositData;
    }
    if (data.creditData && Array.isArray(data.creditData)) {
      creditData.value = data.creditData;
    }

    if (data.topAirlines && Array.isArray(data.topAirlines)) {
      airlines.value = data.topAirlines;
    }

    if (data.monthlySearch && Array.isArray(data.monthlySearch)) {
      searchVals.value = data.monthlySearch;
    }

    if (data.lastTicketingInfo && Array.isArray(data.lastTicketingInfo)) {
      lastTicketingInfo.value = data.lastTicketingInfo;
    }

    if (data.upcomingTravelDates) {
      upcomingTravelDates.value = data.upcomingTravelDates;
    }
    if (data.flightDetailsByDate) {
      flightDetailsByDate.value = data.flightDetailsByDate;
    }



  } catch (error) {
    console.error('Error fetching dashboard stats:', error);

  }
};


/* ─────────────────────────────────────────
   Chart helpers
───────────────────────────────────────── */
const makeDonut = (id, data, colors, cutout = '72%') => {
  const el = document.getElementById(id);
  if (!el) return null;
  return new Chart(el.getContext('2d'), {
    type: 'doughnut',
    data: { datasets: [{ data, backgroundColor: colors, borderWidth: 0, cutout }] },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      plugins: { legend: { display: false }, tooltip: { enabled: false } },
      animation: { animateRotate: true, duration: 900 },
    },
  });
};

const makeGradient = (ctx, colorTop, colorBottom) => {
  const g = ctx.createLinearGradient(0, 0, 0, 380);
  g.addColorStop(0, colorTop);
  g.addColorStop(1, colorBottom);
  return g;
};

let travelerDonutCenterLabel = 'Total';
let travelerDonutCenterValue = 0;



/* ─────────────────────────────────────────
   Init all charts
───────────────────────────────────────── */
const initCharts = () => {

  /* ── KPI Donuts ── */
  // bookingDonut = makeDonut('bookingDonut',
  //   [30, 40, 15, 15],
  //   ['#f59e0b', '#10b981', '#ef4444', '#f97316']);

  // ticketingDonut = makeDonut('ticketingDonut',
  //   [70, 30],
  //   ['#10b981', '#ef4444']);

  /* ── Total Sales & Commission bar ── */
  const sbEl = document.getElementById('salesBarChart');
  if (sbEl) {
    salesBarChart = new Chart(sbEl.getContext('2d'), {
      type: 'bar',
      data: {
        labels: months,
        datasets: [{
          label: 'Total Sales',
          data: salesData.value,
          backgroundColor: '#4f7ef8',
          borderRadius: 8,
          barPercentage: 0.6,
          categoryPercentage: 0.75,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: (c) => ' BDT ' + c.parsed.y.toLocaleString() } },
        },
        scales: {
          y: {
            beginAtZero: true,
            grid: { color: '#eef1f9' },
            ticks: {
              color: '#94a3b8', font: { size: 11 },
              callback: (v) => v >= 1e6 ? (v / 1e6).toFixed(0) + 'M' : v >= 1e3 ? (v / 1e3).toFixed(0) + 'K' : v,
              stepSize: 200000,
            },
          },
          x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 11 } } },
        },
      },
    });
  }

  /* ── Trending Routes horizontal bar ── */
  const trEl = document.getElementById('trendingBarChart');
  if (trEl) {
    trendingBarChart = new Chart(trEl.getContext('2d'), {
      type: 'bar',
      data: {
        labels: routes.value.map(r => ''),   // labels hidden; route badges are in HTML overlay
        datasets: routes.value.map((r, i) => ({
          label: r.code,
          data: routes.value.map((_, j) => j === i ? r.count : null),
          backgroundColor: r.color,
          borderRadius: 6,
          barPercentage: 0.55,
          categoryPercentage: 0.75,
          skipNull: true,
        })),
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { enabled: true } },
        scales: {
          x: {
            beginAtZero: true, max: 500,
            grid: { color: '#eef1f9' },
            ticks: { stepSize: 100, color: '#94a3b8', font: { size: 11 } },
          },
          y: { display: false, grid: { display: false } },
        },
      },
    });
  }

  /* ── Total Traveler donut ── */
  // const ttEl = document.getElementById('travelerDonut');
  // if (ttEl) {
  //   travelerDonut = new Chart(ttEl.getContext('2d'), {
  //     type: 'doughnut',
  //     data: {
  //       labels: ['Adult', 'Children', 'Infant'],
  //       datasets: [{
  //         data: travelerData.value,
  //         backgroundColor: ['#3b82f6', '#8b5cf6', '#06b6d4'],
  //         borderWidth: 0, cutout: '74%',
  //       }],
  //     },
  //     options: {
  //       responsive: true, maintainAspectRatio: true,
  //       plugins: {
  //         legend: { display: false },
  //         tooltip: { callbacks: { label: (c) => ` ${c.label}: ${c.parsed}` } },
  //       },
  //       animation: { duration: 900 },
  //     },
  //   });
  // }

  // const ttEl = document.getElementById('travelerDonut');
  // if (ttEl) {
  //   travelerDonut = new Chart(ttEl.getContext('2d'), {
  //     type: 'doughnut',
  //     data: {
  //       labels: ['Adult', 'Children', 'Infant'],
  //       datasets: [{
  //         data: travelerData.value,
  //         backgroundColor: ['#3b82f6', '#8b5cf6', '#06b6d4'],
  //         borderWidth: 0,
  //         cutout: '60%',
  //         circumference: 180,
  //         rotation: -90,
  //       }],
  //     },
  //     options: {
  //       responsive: true,
  //       maintainAspectRatio: true,
  //       plugins: {
  //         legend: { display: false },
  //         tooltip: { callbacks: { label: (c) => ` ${c.label}: ${c.parsed}` } },
  //       },
  //       animation: { duration: 900 },
  //     },
  //   });
  // }

  /* ── Booking vs Ticketing area ── */
  const raEl = document.getElementById('ratioAreaChart');
  if (raEl) {
    const raCtx = raEl.getContext('2d');
    ratioAreaChart = new Chart(raCtx, {
      type: 'line',
      data: {
        labels: months,
        datasets: [
          {
            label: 'Booking',
            data: bookingVals.value,
            borderColor: '#c084fc',
            backgroundColor: makeGradient(raCtx, 'rgba(192,132,252,0.55)', 'rgba(192,132,252,0.0)'),
            borderWidth: 2.5, fill: true, tension: 0.55, pointRadius: 0,
          },
          {
            label: 'Ticketing',
            data: ticketingVals.value,
            borderColor: '#818cf8',
            backgroundColor: makeGradient(raCtx, 'rgba(129,140,248,0.45)', 'rgba(129,140,248,0.0)'),
            borderWidth: 2.5, fill: true, tension: 0.55, pointRadius: 0,
          },
        ],
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { mode: 'index', intersect: false },
        },
        scales: {
          y: { beginAtZero: true, grid: { color: '#eef1f9' }, ticks: { color: '#94a3b8', font: { size: 11 } } },
          x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 11 } } },
        },
      },
    });
  }

  /* ── Top 10 Airlines half-gauge ── */
  const taEl = document.getElementById('airlinesGauge');
  if (taEl) {
    airlinesGauge = new Chart(taEl.getContext('2d'), {
      type: 'doughnut',
      data: {
        labels: airlineLabels,
        datasets: [{
          data: airlineValues,
          backgroundColor: airlineColors,
          borderWidth: 0,
          cutout: '55%',
          circumference: 180,
          rotation: -90,
        }],
      },
      options: {
        responsive: true, maintainAspectRatio: true,
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: (c) => ` ${c.label}: ${c.parsed}%` } },
        },
        animation: { duration: 900 },
      },
    });
  }

  /* ── Total Transaction grouped bar ── */
  const txEl = document.getElementById('transactionBar');
  if (txEl) {
    transactionBar = new Chart(txEl.getContext('2d'), {
      type: 'bar',
      data: {
        labels: months,
        datasets: [
          {
            label: 'Deposit',
            data: depositData.value,
            backgroundColor: '#4f7ef8',
            borderRadius: 6,
            barPercentage: 0.55, categoryPercentage: 0.75,
          },
          {
            label: 'Credit',
            data: creditData.value,
            backgroundColor: '#f97316',
            borderRadius: 6,
            barPercentage: 0.55, categoryPercentage: 0.75,
          },
        ],
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            mode: 'index', intersect: false,
            callbacks: { label: (c) => ` ${c.dataset.label}: BDT ${c.parsed.y.toLocaleString()}` }
          },
        },
        scales: {
          y: {
            beginAtZero: true, grid: { color: '#eef1f9' },
            ticks: {
              color: '#94a3b8', font: { size: 11 },
              callback: (v) => v >= 1e6 ? (v / 1e6).toFixed(0) + 'M' : v >= 1e3 ? (v / 1e3).toFixed(0) + 'K' : v,
              stepSize: 200000,
            },
          },
          x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 11 } } },
        },
      },
    });
  }

  const total = bookingStats.totalBookings;
  const confirmed = bookingStats.confirmedBookings;

  const confirmedPercent = total > 0 ? Math.round((confirmed / total) * 100) : 0;

  const bookingCanvas = document.getElementById('bookingDonut');
  if (bookingCanvas) {
    const ctx = bookingCanvas.getContext('2d');
    bookingDonut = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Confirmed', 'Cancelled'],
        datasets: [{
          data: [confirmedPercent, 100 - confirmedPercent],
          backgroundColor: ['#10b981', '#f97316'], // Blue for confirmed, red for cancelled
          borderWidth: 0,
          cutout: '75%',
          borderRadius: 8,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        aspectRatio: 2,
        rotation: 270,
        circumference: 180,
        plugins: {
          legend: { display: false },
          tooltip: { enabled: false }
        },
        animation: { animateRotate: true, duration: 900 }
      }
    });
  }

  const ticketingCanvas = document.getElementById('ticketingDonut');
  if (ticketingCanvas) {
    const ctx = ticketingCanvas.getContext('2d');
    ticketingDonut = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Ticketed', 'Others'],
        datasets: [{
          data: [ticketingStats.ticketed, ticketingStats.voided],
          backgroundColor: ['#10b981', '#ef4444'],
          borderWidth: 0,
          cutout: '70%',
          spacing: 0,
          borderRadius: 8
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,

        plugins: {
          legend: { display: false },
          tooltip: { enabled: false }
        },
        animation: { animateRotate: true, duration: 900 }
      }
    });
  }

  // Search vs Booking Chart
  const searchCanvas = document.getElementById('searchBookingChart');
  if (searchCanvas) {
    const searchCtx = searchCanvas.getContext('2d');
    searchBookingChart = new Chart(searchCtx, {
      type: 'bar',
      data: {
        labels: months,
        datasets: [
          {
            label: 'Search',
            data: searchVals.value,
            backgroundColor: '#f59e0b',
            fill: true,
            tension: 0.4,
            pointRadius: 3,
            pointBackgroundColor: '#f59e0b',
            borderRadius: 8,
            barPercentage: 0.55,
            categoryPercentage: 0.75,
          },
          {
            label: 'Booking',
            data: bookingVals.value,
            backgroundColor: '#10b981',
            fill: true,
            tension: 0.4,
            pointRadius: 3,
            pointBackgroundColor: '#10b981',
            borderRadius: 8,
            barPercentage: 0.55,
            categoryPercentage: 0.75,
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
          legend: { display: false },
          tooltip: { mode: 'index', intersect: false }
        },
        scales: {
          y: { beginAtZero: true, grid: { color: '#e2e8f0' } },
          x: { grid: { display: false } }
        }
      }
    });
  }

  /* ── Total Traveler Multiple Band Radial Bar Chart ── */

  const travelerHoverState = reactive({
    label: 'Total',
    value: 0
  });

  const ttEl = document.getElementById('travelerDonut');

  if (ttEl) {
    const total = travelerData.value.reduce((a, b) => a + b, 0);

    travelerDonutCenterLabel = 'Total';
    travelerDonutCenterValue = total;

    const maxVal = Math.max(...travelerData.value, 1);

    travelerDonut = new Chart(ttEl.getContext('2d'), {
      type: 'doughnut',

      data: {
        labels: ['Adult', 'Children', 'Infant'],

        datasets: [
          {
            label: 'Adult',
            data: [
              travelerData.value[0],
              maxVal - travelerData.value[0]
            ],
            backgroundColor: ['#3b82f6', '#f1f5f9'],
            borderWidth: 0,
            borderRadius: 20,
            cutout: '70%',
            circumference: 270,
            rotation: -135,
            radius: '100%'
          },

          {
            label: 'Children',
            data: [
              travelerData.value[1],
              maxVal - travelerData.value[1]
            ],
            backgroundColor: ['#8b5cf6', '#f1f5f9'],
            borderWidth: 0,
            borderRadius: 20,
            cutout: '70%',
            circumference: 270,
            rotation: -135,
            radius: '90%'
          },

          {
            label: 'Infant',
            data: [
              travelerData.value[2],
              maxVal - travelerData.value[2]
            ],
            backgroundColor: ['#06b6d4', '#f1f5f9'],
            borderWidth: 0,
            borderRadius: 20,
            cutout: '70%',
            circumference: 270,
            rotation: -135,
            radius: '80%'
          }
        ]
      },

      options: {
        responsive: true,
        maintainAspectRatio: true,

        plugins: {
          legend: {
            display: false
          },

          tooltip: {
            enabled: false
          }
        },

        onHover: (event, activeElements, chart) => {

          if (activeElements.length > 0) {

            const element = activeElements[0];

            const dataset =
              chart.data.datasets[element.datasetIndex];

            travelerDonutCenterLabel = dataset.label;
            travelerDonutCenterValue = dataset.data[0];

          } else {

            travelerDonutCenterLabel = 'Total';
            travelerDonutCenterValue =
              travelerData.value.reduce((a, b) => a + b, 0);
          }

          chart.draw();
        },

        animation: {
          duration: 900
        }
      },

      plugins: [
        travelerDonutCenterText
      ]
    });
  }

  const supportCanvas = document.getElementById('supportDonut');
  if (supportCanvas) {
    const ctx = supportCanvas.getContext('2d');
    supportDonut = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Open', 'Closed', 'Hold'],
        datasets: [{
          data: [5, 7, 2],
          backgroundColor: ['#10B981', '#64748B', '#F59E0B'],
          borderWidth: 0,
          cutout: '75%',
          borderRadius: 5,
          spacing: 5
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        aspectRatio: 2,
        rotation: 270,
        circumference: 180,
        plugins: {
          legend: { display: false },
          tooltip: { enabled: false }
        },
        onHover: (event, activeElements, chart) => {
          if (activeElements.length > 0) {
            const element = activeElements[0];
            const index = element.index;
            supportDonutCenterLabel = chart.data.labels[index];
            supportDonutCenterValue = chart.data.datasets[0].data[index];
          } else {
            supportDonutCenterLabel = 'Total';
            supportDonutCenterValue = chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
          }
          chart.draw();
        },
        animation: { animateRotate: true, duration: 900 }
      },
      plugins: [supportDonutCenterText]
    });

  }

  /* ── Trending Booking Class polarArea (monochrome) ── */
  const bcEl = document.getElementById('bookingClassPie');
  if (bcEl) {
    bookingClassPie = new Chart(bcEl.getContext('2d'), {
      type: 'polarArea',
      data: {
        labels: ['Economy', 'Prem. Economy', 'Business Class', 'First Class'],
        datasets: [{
          data: bookingClassData.value,
          backgroundColor: [
            'rgba(79, 126, 248, 0.85)',   // Economy       — indigo (matches KPI cards)
            'rgba(16, 185, 129, 0.85)',   // Prem. Economy — emerald
            'rgba(249, 115, 22, 0.85)',   // Business      — orange
            'rgba(139, 92, 246, 0.85)',   // First         — purple
          ],
          borderColor: [
            '#4f7ef8',
            '#10b981',
            '#f97316',
            '#8b5cf6',
          ],
          borderWidth: 1,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: (c) => `${c.parsed.r}%`
            }
          },
        },
        scales: {
          r: {
            ticks: { display: false },
            grid: { display: false },         // no rings
            angleLines: { display: false },   // no spokes
            pointLabels: { display: false },
          }
        },
        animation: { duration: 900 },
      },
    });
  }


};



const addCenterTextToBookingGauge = (percent) => {
  if (bookingDonut) {
    const originalDraw = bookingDonut.draw;
    bookingDonut.draw = function () {
      originalDraw.apply(this, arguments);
      const ctx = this.ctx;
      const canvas = this.canvas;

      // ✅ Use chartArea for accurate center (accounts for responsive resize)
      const chartArea = this.chartArea;
      const centerX = (chartArea.left + chartArea.right) / 2;
      const centerY = chartArea.bottom;  // bottom of chart = center of half-gauge arc

      ctx.save();
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.font = 'bold 16px "Plus Jakarta Sans", sans-serif';
      ctx.fillStyle = '#0f1535';
      ctx.fillText(`${percent}%`, centerX, centerY - 22);   // ✅ relative offset

      ctx.font = '10px "Plus Jakarta Sans", sans-serif';
      ctx.fillStyle = '#0f1535';
      ctx.fillText(`Confirmed`, centerX, centerY - 8);      // ✅ relative offset
      ctx.restore();
    };
    bookingDonut.draw();
  }
};

const addCenterTextToSupportDonut = () => {
  if (supportDonut) {
    // Set initial total count dynamically
    supportDonutCenterValue = supportDonut.data.datasets[0].data.reduce((a, b) => a + b, 0);

    const originalDraw = supportDonut.draw;
    supportDonut.draw = function () {
      originalDraw.apply(this, arguments);
      const ctx = this.ctx;

      const chartArea = this.chartArea;
      const centerX = (chartArea.left + chartArea.right) / 2;
      const centerY = chartArea.bottom; // center of half-gauge arc

      ctx.save();
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';

      // Draw value (e.g. 5, 7, 2 or Total sum)
      ctx.font = 'bold 16px "Plus Jakarta Sans", sans-serif';
      ctx.fillStyle = '#0f1535';
      ctx.fillText(`${supportDonutCenterValue}`, centerX, centerY - 22);

      // Draw label (e.g. 'Open', 'Closed', 'Hold' or 'Total')
      ctx.font = '10px "Plus Jakarta Sans", sans-serif';
      ctx.fillStyle = '#64748b';
      ctx.fillText(supportDonutCenterLabel, centerX, centerY - 8);

      ctx.restore();
    };
    supportDonut.draw();
  }
};

const travelerDonutCenterText = {
  id: 'travelerDonutCenterText',

  afterDraw(chart) {
    const { ctx, chartArea } = chart;

    const centerX = (chartArea.left + chartArea.right) / 2;
    const centerY = (chartArea.top + chartArea.bottom) / 2;

    ctx.save();

    // Value
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';

    ctx.font = 'bold 18px "Plus Jakarta Sans", sans-serif';
    ctx.fillStyle = '#0f1535';

    ctx.fillText(
      `${travelerDonutCenterValue}`,
      centerX,
      centerY - 8
    );

    // Label
    ctx.font = '11px "Plus Jakarta Sans", sans-serif';
    ctx.fillStyle = '#64748b';

    ctx.fillText(
      travelerDonutCenterLabel,
      centerX,
      centerY + 10
    );

    ctx.restore();
  }
};

let supportDonutCenterLabel = 'Total';
let supportDonutCenterValue = 14;

const supportDonutCenterText = {
  id: 'supportDonutCenterText',
  afterDraw(chart) {
    const { ctx, chartArea } = chart;
    const centerX = (chartArea.left + chartArea.right) / 2;
    const centerY = chartArea.bottom; // center of half-gauge arc

    ctx.save();
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';

    // Count / Value
    ctx.font = 'bold 16px "Plus Jakarta Sans", sans-serif';
    ctx.fillStyle = '#0f1535';
    ctx.fillText(`${supportDonutCenterValue}`, centerX, centerY - 22);

    // Label ('Total', 'Open', etc.)
    ctx.font = '10px "Plus Jakarta Sans", sans-serif';
    ctx.fillStyle = '#64748b';
    ctx.fillText(supportDonutCenterLabel, centerX, centerY - 8);

    ctx.restore();
  }
};



/* ─────────────────────────────────────────
   Utilities
───────────────────────────────────────── */
const pct = (count, max) => ((count / max) * 100).toFixed(1);
//const totalTravelers = computed(() => travelerData.value.reduce((a, b) => a + b, 0))

const currentYear = new Date().getFullYear();

const selectedMonth = ref(null); // null = All

const monthsSelection = Array.from({ length: 12 }, (_, index) => ({
  value: index + 1,
  label: new Date(currentYear, index, 1).toLocaleString('en-US', {
    month: 'short'
  })
}));

const selectedMonthLabel = computed(() => {
  if (selectedMonth.value === null) {
    return 'All';
  }

  return `${monthsSelection[selectedMonth.value - 1].label} ${currentYear}`;
});

const selectMonth = (month) => {
  selectedMonth.value = month ? month.value : null;

  console.log('Selected month:', selectedMonth.value);
};

function formatTotal(value) {
  if (value >= 1000000) {
    return (value / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
  } else if (value >= 100000) { // 1 lakh = 100,000
    return (value / 1000).toFixed(1).replace(/\.0$/, '') + 'k';
  } else {
    return value.toLocaleString('en-US'); // comma-separated (Indian style)
  }
}

function formatValues(value) {
  if (value >= 1000000) {
    return (value / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
  } else if (value >= 10000) { // 10 thousand = 10,000
    return (value / 1000).toFixed(1).replace(/\.0$/, '') + 'k';
  } else {
    return value.toLocaleString('en-US'); // comma-separated (Indian style)
  }
}

onMounted(async () => {
  await fetchDashboardStats();
  setTimeout(() => {
    initCharts();
    setTimeout(() => {
      const total = bookingStats.totalBookings;
      const confirmed = bookingStats.confirmedBookings;
      const percent = total > 0 ? Math.round((confirmed / total) * 100) : 0;
      addCenterTextToBookingGauge(percent);

      const totalTravelers = travelerData.value.reduce((a, b) => a + b, 0);
      //addCenterTextToSupportDonut();

    }, 100);
  }, 100);
  window.addEventListener('keydown', handleFlightModalEsc);
});

onBeforeUnmount(() => {
  [bookingDonut, ticketingDonut, salesBarChart, trendingBarChart,
    travelerDonut, bookingClassPie, ratioAreaChart, airlinesGauge, transactionBar, searchBookingChart]
    .forEach(c => c?.destroy());
  window.removeEventListener('keydown', handleFlightModalEsc);
  document.body.style.overflow = '';
});
</script>

<template>
  <div class="dashboard-wrapper">
    <div class="container-fluid px-0">

      <!-- ══════════════════════════════════════════════════════
           ROW 1 — KPI cards (left col) + Sales & Commission bar
      ══════════════════════════════════════════════════════ -->
      <div class="row g-3 mb-3">

        <!-- Left: Total Booking + Total Ticket stacked -->
        <div class="col-12 col-lg-4 d-flex flex-column gap-3">
          <!-- Total Booking -->
          <div class="kpi-card h-auto flex-fill">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="kpi-label">Bookings</span>
              <PeriodSelector v-model="bookingMonthSelection" />

            </div>
            <div class="kpi-value-row d-flex align-items-center gap-3">
              <div class="kpi-value">{{ formatTotal(bookingStats.totalBookings) }}</div>
              <div class="kpi-badge-row">
                <span :class="['kpi-trend', bookingStats.bookingRatio >= 0 ? 'up' : 'down']">
                  <i :class="bookingStats.bookingRatio >= 0
                    ? 'fa-solid fa-arrow-trend-up'
                    : 'fa-solid fa-arrow-trend-down'
                    "></i>

                  {{ bookingStats.bookingRatio > 0 ? '+' + bookingStats.bookingRatio : bookingStats.bookingRatio }}%
                </span>

                <span class="text-secondary small">vs YD</span>
              </div>
              <span class="text-secondary small ms-auto fw-semibold today-live">
                <svg class="live-wave" viewBox="0 0 32 12" aria-hidden="true">
                  <path d="M1 6 C4 1, 7 1, 10 6 S16 11, 19 6 S25 1, 31 6" pathLength="1" />
                </svg>
                Today: {{ formatValues(bookingStats.todayBookings) }}
              </span>
            </div>


            <div class="d-flex align-items-center gap-3 mt-3">
              <div class="kpi-donut-wrap">
                <canvas id="bookingDonut" width="120" height="60"></canvas>
              </div>
              <div class="chart-legend">

                <div><span class="ldot" style="background:#10b981"></span>{{
                  formatValues(bookingStats.confirmedBookings) }} Confirmed
                </div>

                <div><span class="ldot" style="background:#f97316"></span>{{
                  formatValues(bookingStats.cancelledBookings) }} Canceled
                </div>
              </div>
            </div>
          </div>
          <!-- <div class="col-12">
            
          </div> -->

          <!-- Total Ticket -->
          <div class="kpi-card h-auto flex-fill">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="kpi-label">Tickets</span>
              <PeriodSelector v-model="ticketingMonthSelection" />
              <!-- <button class="btn-pill">All<i class="bi bi-chevron-down"></i></button> -->
            </div>

            <div class="kpi-value-row d-flex align-items-center gap-3">
              <div class="kpi-value">{{ formatTotal(ticketingStats.totalTicketing) }}</div>
              <div class="kpi-badge-row">
                <span :class="['kpi-trend', ticketingStats.ticketingRatio >= 0 ? 'up' : 'down']">
                  <i :class="bookingStats.bookingRatio >= 0
                    ? 'fa-solid fa-arrow-trend-up'
                    : 'fa-solid fa-arrow-trend-down'
                    "></i>

                  {{ ticketingStats.ticketingRatio > 0 ? '+' + ticketingStats.ticketingRatio :
                    ticketingStats.ticketingRatio }}%
                </span>
                <span class="text-secondary small">vs YD</span>
              </div>

              <span class="text-secondary small ms-auto fw-semibold today-live">
                <svg class="live-wave" viewBox="0 0 32 12" aria-hidden="true">
                  <path d="M1 6 C4 1, 7 1, 10 6 S16 11, 19 6 S25 1, 31 6" pathLength="1" />
                </svg>
                Today: {{ formatValues(ticketingStats.todayTicketing) }}
              </span>
            </div>

            <div class="d-flex align-items-center gap-3 mt-3">
              <div class="kpi-donut-wrap">
                <canvas id="ticketingDonut"></canvas>
              </div>
              <div class="chart-legend">
                <div><span class="ldot" style="background:#10b981"></span>{{ formatValues(ticketingStats.ticketed)
                }}
                  Ticketed</div>
                <div><span class="ldot" style="background:#ef4444"></span>{{ formatValues(ticketingStats.voided) }}
                  Others</div>
              </div>
            </div>
          </div>
          <!-- <div class="col-12">
            
          </div> -->
          <!-- <div class="row g-3 h-100">

          </div> -->
        </div><!-- /left col -->

        <!-- Right: Total Sales & Commission bar -->
        <div class="col-12 col-lg-8">
          <div class="dash-card d-flex flex-column h-100">
            <div class="dash-card-header">
              <h3>Total Sales &amp; Commission</h3>
              <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="d-flex align-items-center gap-2 text-muted small">
                  <span class="legend-pill-rect" style="background:#4f7ef8"></span>Total Sales
                </span>
                <button class="btn-pill">{{ currentYear }}</button>
              </div>
            </div>
            <div class="chart-canvas-wrap flex-grow-1">
              <canvas id="salesBarChart"></canvas>
            </div>
          </div>
        </div>

      </div><!-- /row 1 -->



      <!-- ══════════════════════════════════════════════════════
           ROW 2 — Trending Routes | Upcoming Departure 
      ══════════════════════════════════════════════════════ -->
      <div class="row g-3 mb-3">

        <!-- Last Ticketing Time -->
        <div class="col-12 col-lg-6">
          <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-3">

              <!-- Header -->
              <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <h6 class="mb-0 fw-bold text-dark">Last Ticketing Time</h6>
                <div class="d-flex align-items-center gap-2">
                  <!-- <span v-if="filteredTicketingBookings.length"
                    class="badge rounded-pill fw-semibold tkt-count-badge">
                    {{ filteredTicketingBookings.length }} due
                  </span> -->

                  <div class="d-flex gap-2">
                    <button v-for="opt in ticketingWindowOptions" :key="opt.value" type="button"
                      class="fdm-travel-badge border-0" :style="ticketingWindow === opt.value
                        ? 'background: #4f7ef8; color: #fff; cursor: pointer;'
                        : 'background: #eef2ff; color: #4f7ef8; cursor: pointer;'"
                      @click="ticketingWindow = opt.value">
                      {{ opt.label }}
                    </button>
                  </div>
                </div>
              </div>

              <!-- Empty State -->
              <div v-if="!filteredTicketingBookings.length" class="text-center text-muted py-4 small">
                No bookings nearing ticketing deadline.
              </div>

              <!-- Table -->
              <div v-else class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                  <!-- <thead >
                    <tr class="text-uppercase text-muted" style="font-size: 11px; letter-spacing: 0.04em;">
                      <th class="fw-semibold border-0 ps-2">LT Date</th>
                      <th class="fw-semibold border-0">Airline</th>
                      <th class="fw-semibold border-0">Passenger</th>
                      <th class="fw-semibold border-0">Contact</th>
                      <th class="fw-semibold border-0 text-end pe-2">Time Left</th>
                    </tr>
                  </thead> -->
                  <colgroup>
                    <col style="width: 30%;">
                    <col style="width: 10%;">
                    <col style="width: 35%;">
                    <col style="width: 25%;">
                  </colgroup>
                  <tbody>
                    <tr v-for="booking in filteredTicketingBookings" :key="booking.flightPnr" role="button" tabindex="0"
                      @click="openTicketingModal(booking)" @keydown.enter="openTicketingModal(booking)"
                      style="cursor: pointer;">
                      <!-- LT Date & Time -->

                      <td class="p-2">
                        <div class="d-flex align-items-center gap-2">
                          <div class="fw-semibold text-dark" style="font-size: 13px;">
                            {{ formatLTDate(booking.lastTicketingTime) }}
                          </div>

                          <span class="text-muted">|</span>

                          <div class="fw-semibold text-dark" style="font-size: 13px;">
                            {{ formatLTTime(booking.lastTicketingTime) }}
                          </div>
                        </div>
                      </td>

                      <!-- Airline -->
                      <td style="font-size: 13px;">
                        <span class="text-truncate d-block">{{ booking.airlineCode }}</span>
                      </td>

                      <!-- Passenger -->
                      <td style="font-size: 13px;">
                        <span class="text-truncate d-block" :title="booking.primaryPassenger">
                          {{ booking.primaryPassenger }}
                        </span>
                      </td>

                      <!-- Contact -->

                      <!-- Time Left Badge -->
                      <td class="text-end pe-2">
                        <span class="badge rounded-pill fw-bold" :class="{
                          'tkt-badge-overdue': ticketingUrgency(booking.lastTicketingTime) === 'overdue',
                          'tkt-badge-critical': ticketingUrgency(booking.lastTicketingTime) === 'critical',
                          'tkt-badge-warning': ticketingUrgency(booking.lastTicketingTime) === 'warning',
                        }" style="font-size: 12px; padding: 5px 11px;">
                          {{ ticketingCountdownLabel(booking.lastTicketingTime) }}
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

            </div>
          </div>
        </div>

        <!-- Ticketing Deadline booking detail modal -->
        <teleport to="body">
          <div v-if="ticketingModalOpen && selectedBooking" class="modal d-block" tabindex="-1"
            style="background: rgba(11,21,53,0.45);" @click.self="closeTicketingModal">
            <div class="modal-dialog modal-dialog-centered" style="max-width: 460px;">
              <div class="modal-content border-0 rounded-3 shadow-lg overflow-hidden">

                <!-- Modal Header -->
                <div class="modal-header border-0 text-white px-4 py-3" style="background: #0f1535;">
                  <div>
                    <h5 class="modal-title fw-bold mb-1" id="tktmTitle" style="color: #ffffff;">
                      Booking — {{ selectedBooking.flightPnr }}
                    </h5>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                      <span class="badge rounded-pill fw-bold" :class="{
                        'tkt-badge-overdue': ticketingUrgency(selectedBooking.lastTicketingTime) === 'overdue',
                        'tkt-badge-critical': ticketingUrgency(selectedBooking.lastTicketingTime) === 'critical',
                        'tkt-badge-warning': ticketingUrgency(selectedBooking.lastTicketingTime) === 'warning',
                      }">
                        {{ ticketingCountdownLabel(selectedBooking.lastTicketingTime) }}
                      </span>
                      <small class="text-white">
                        {{
                          new Date(selectedBooking.lastTicketingTime).toLocaleString('en-US', {
                            day: '2-digit', month: 'short', hour: 'numeric', minute: '2-digit', hour12: true
                          })
                        }}
                      </small>
                    </div>
                  </div>
                  <button type="button" class="btn-close btn-close-white ms-auto" aria-label="Close"
                    @click="closeTicketingModal"></button>
                </div>

                <!-- Modal Body -->
                <div class="modal-body px-4 py-3">
                  <dl class="row mb-0">
                    <dt class="col-5 fw-normal text-muted small">Airline Code</dt>
                    <dd class="col-7 text-end fw-semibold small mb-2">{{ selectedBooking.airlineCode }}</dd>

                    <dt class="col-5 fw-normal text-muted small">Airline</dt>
                    <dd class="col-7 text-end fw-semibold small mb-2">{{ selectedBooking.airline }}</dd>

                    <dt class="col-5 fw-normal text-muted small">Route</dt>
                    <dd class="col-7 text-end fw-semibold small mb-2">
                      {{ routeParts(selectedBooking.route).origin }}
                      <span class="text-secondary mx-1">→</span>
                      {{ routeParts(selectedBooking.route).destination }}
                    </dd>

                    <dt class="col-5 fw-normal text-muted small">Total Pax</dt>
                    <dd class="col-7 text-end fw-semibold small mb-2">{{ selectedBooking.totalPax }}</dd>

                    <dt class="col-5 fw-normal text-muted small">Cabin Class</dt>
                    <dd class="col-7 text-end fw-semibold small mb-2">{{ selectedBooking.cabinClass }}</dd>

                    <dt class="col-5 fw-normal text-muted small">GDS PNR</dt>
                    <dd class="col-7 text-end fw-semibold small mb-2">{{ selectedBooking.gdsPnr }}</dd>

                    <dt class="col-5 fw-normal text-muted small">Flight PNR</dt>
                    <dd class="col-7 text-end fw-semibold small mb-2">{{ selectedBooking.flightPnr }}</dd>
                  </dl>

                  <hr class="my-2">

                  <dl class="row mb-0">
                    <dt class="col-5 fw-normal text-muted small">Primary Passenger</dt>
                    <dd class="col-7 text-end fw-semibold small mb-2">{{ selectedBooking.primaryPassenger }}</dd>

                    <dt class="col-5 fw-normal text-muted small">Contact</dt>
                    <dd class="col-7 text-end mb-0">
                      <a class="text-primary text-decoration-none small"
                        :href="`tel:${selectedBooking.contact.replace(/[\s-]/g, '')}`">
                        {{ selectedBooking.contact }}
                      </a>
                    </dd>
                  </dl>
                </div>

              </div>
            </div>
          </div>
        </teleport>

        <!-- Upcoming departure -->
        <div class="col-12 col-lg-6">
          <div class="dash-card h-100">
            <div class="dash-card-header mb-2">
              <h3>Upcoming Departures</h3>
            </div>
            <div class="fdm-travel-list">
              <button v-for="item in upcomingTravelDates" :key="item.date" type="button" class="fdm-travel-row"
                @click="openFlightModal(item)">
                <span class="fdm-travel-main">
                  <span class="fdm-travel-date">{{ item.displayDate }}</span>
                  <span class="fdm-travel-sub">{{ item.flightCount }} flight{{ item.flightCount === 1 ? '' : 's'
                  }}</span>
                </span>
                <span class="fdm-travel-badge">{{ item.passengerCount }} pax</span>
              </button>
            </div>
          </div>
        </div>

        <!-- ══════════════════════════════════════════════════════
             Upcoming Flights detail modal (opened from the
             "Upcoming Departures" date list above)
        ══════════════════════════════════════════════════════ -->
        <teleport to="body">
          <div v-if="flightModalOpen" class="fdm-backdrop" @click.self="closeFlightModal">
            <div class="fdm-modal" role="dialog" aria-modal="true" aria-labelledby="fdmTitle">
              <div class="fdm-header">
                <div class="d-flex align-items-start justify-content-between gap-3">
                  <div>
                    <h2 id="fdmTitle" class="fdm-title mb-0">
                      Upcoming Departures — {{ selectedDateLabel }}
                    </h2>
                    <div class="fdm-summary mt-2">
                      <template v-if="flightModalLoading">
                        <span>Loading flight details…</span>
                      </template>
                      <template v-else>
                        <span>{{ selectedDateFlights.flights.length }} Flight{{ selectedDateFlights.flights.length === 1
                          ? '' : 's' }}</span>
                        <span class="fdm-dot"></span>
                        <span>{{ selectedDateFlights.totalPassengers }} Passengers</span>
                        <!-- <span class="fdm-dot"></span>
                        <span>{{ selectedDateFlights.totalBookings }} Bookings</span> -->
                      </template>
                    </div>
                  </div>
                  <button type="button" class="fdm-close" aria-label="Close" @click="closeFlightModal">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                      <path d="M1 1L15 15M15 1L1 15" stroke="white" stroke-width="1.6" stroke-linecap="round" />
                    </svg>
                  </button>
                </div>
              </div>

              <div class="fdm-body">
                <template v-if="flightModalLoading">
                  <div v-for="n in 2" :key="'fdm-sk-' + n" class="fdm-flight-card">
                    <div class="fdm-flight-head">
                      <div class="fdm-skeleton fdm-sk-line" style="width:40%;height:16px;"></div>
                      <div class="fdm-skeleton fdm-sk-line" style="width:60%;"></div>
                      <div class="fdm-skeleton fdm-sk-line" style="width:30%;height:18px;margin-top:.5rem;"></div>
                    </div>
                    <div class="fdm-passenger-wrap">
                      <div class="fdm-skeleton fdm-sk-line" style="width:100%;"></div>
                      <div class="fdm-skeleton fdm-sk-line" style="width:90%;"></div>
                      <div class="fdm-skeleton fdm-sk-line" style="width:95%;"></div>
                    </div>
                  </div>
                </template>

                <template v-else-if="!selectedDateFlights.flights.length">
                  <div class="fdm-empty">
                    <div class="fdm-empty-icon">
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                        <path
                          d="M21 16v-2l-8-5V3.5a1.5 1.5 0 0 0-3 0V9l-8 5v2l8-2.5V19l-2.5 2v1.5L12 21l4.5 1.5V21L14 19v-5.5l7 2.5Z"
                          fill="currentColor" />
                      </svg>
                    </div>
                    <p>No upcoming flights for this date.</p>
                  </div>
                </template>

                <template v-else>
                  <div v-for="flight in selectedDateFlights.flights" :key="flight.flightNumber" class="fdm-flight-card">
                    <div class="fdm-flight-head">
                      <div class="d-flex align-items-start justify-content-between gap-2 flex-wrap">
                        <div class="fdm-flight-id">
                          <div class="fdm-plane-icon">
                            <img v-if="flight.airlineLogo" :src="flight.airlineLogo" :alt="flight.airlineName"
                              style="width: 100%; height: 100%; object-fit: contain;" />

                            <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none">
                              <path
                                d="M21 16v-2l-8-5V3.5a1.5 1.5 0 0 0-3 0V9l-8 5v2l8-2.5V19l-2.5 2v1.5L12 21l4.5 1.5V21L14 19v-5.5l7 2.5Z"
                                fill="currentColor" />
                            </svg>
                          </div>
                          <div>
                            <div class="fdm-flight-number">{{ flight.flightNumber }}</div>
                            <div class="fdm-airline-name">{{ flight.airlineName }}</div>
                          </div>
                        </div>
                        <div class="fdm-badges">
                          <span class="fdm-badge-pill fdm-badge-pax">{{ flight.passengerCount }} Pax</span>
                          <!-- <span class="fdm-badge-pill fdm-badge-book">{{ flight.bookingCount }} Bookings</span> -->
                        </div>
                      </div>

                      <div class="fdm-route-row">
                        <span>{{ routeParts(flight.route).origin }}</span>
                        <span class="fdm-route-arrow">→</span>
                        <span>{{ routeParts(flight.route).destination }}</span>
                      </div>
                    </div>

                    <div class="fdm-perforation"></div>

                    <div class="fdm-passenger-wrap">
                      <div class="fdm-passenger-label">
                        <span>Primary Passenger</span>
                        <span>Contact</span>
                      </div>
                      <div class="fdm-passenger-list">
                        <div v-for="(booking, idx) in flight.bookings" :key="flight.flightNumber + '-' + idx"
                          class="fdm-passenger-row">
                          <span class="fdm-passenger-name" :title="booking.primaryPassenger">
                            {{ booking.primaryPassenger }}
                          </span>
                          <a class="fdm-passenger-contact" :href="`tel:${booking.contact.replace(/[\s-]/g, '')}`">
                            {{ booking.contact }}
                          </a>
                        </div>
                      </div>
                    </div>
                  </div>
                </template>
              </div>
            </div>
          </div>
        </teleport>


      </div><!-- /row 2 -->

      <!-- ══════════════════════════════════════════════════════
           ROW 3 — Search vs Booking | Booking vs Ticketing
      ══════════════════════════════════════════════════════ -->
      <div class="row g-3 mb-3">

        <!-- Search vs Booking -->
        <div class="col-12 col-lg-6">
          <div class="card h-100 border-0 rounded-4">
            <div class="card-body p-3 p-md-4">
              <div class="dash-card-header">
                <h3>Search vs Booking</h3>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                  <span class="legend-item">
                    <span class="legend-line" style="background:#f59e0b"></span>Search
                  </span>
                  <span class="legend-item">
                    <span class="legend-line" style="background:#10b981"></span>Booking
                  </span>
                  <button class="btn-pill">2026</button>
                </div>
              </div>
              <div class="chart-container">
                <canvas id="searchBookingChart"></canvas>
              </div>
            </div>
          </div>
        </div>

        <!-- Booking vs Ticketing Ratio -->
        <div class="col-12 col-lg-6">
          <div class="dash-card h-100 d-flex flex-column">
            <div class="dash-card-header">
              <h3>Booking vs Ticketing</h3>
              <div class="d-flex align-items-center gap-3 flex-wrap">
                <span class="legend-item">
                  <span class="legend-line" style="background:#c084fc"></span>Booking
                </span>
                <span class="legend-item">
                  <span class="legend-line" style="background:#818cf8"></span>Ticketing
                </span>
                <button class="btn-pill">2026</button>
              </div>
            </div>
            <div class="chart-canvas-wrap flex-grow-1">
              <canvas id="ratioAreaChart"></canvas>
            </div>
          </div>
        </div>

      </div><!-- /row 3 -->

      <!-- ══════════════════════════════════════════════════════
           ROW 4 — Total Transaction bar | Top 10 Selling Airlines
      ══════════════════════════════════════════════════════ -->
      <div class="row g-3 mb-3">

        <!-- Total Transaction -->
        <div class="col-12 col-lg-6">
          <div class="dash-card d-flex flex-column">
            <div class="dash-card-header">
              <h3>Total Transaction</h3>
              <div class="d-flex align-items-center gap-3 flex-wrap">
                <span class="legend-item">
                  <span class="legend-pill-rect" style="background:#4f7ef8"></span>Deposit
                </span>
                <span class="legend-item">
                  <span class="legend-pill-rect" style="background:#f97316"></span>Credit
                </span>
                <button class="btn-pill">2026</button>
              </div>
            </div>
            <div class="chart-canvas-wrap flex-grow-1">
              <canvas id="transactionBar"></canvas>
            </div>
          </div>
        </div>

        <!-- Top 10 Selling Airlines -->
        <div class="col-12 col-lg-6">
          <div class="dash-card h-100">
            <div class="dash-card-header">
              <h3>Top 10 Selling Airlines</h3>
              <PeriodSelector v-model="routesMonthSelection" :include-all="false" />
              <!-- <button class="btn-pill">August, 2026 <i class="bi bi-chevron-down"></i></button> -->
            </div>
            <div class="table-responsive">
              <table class="table align-middle data-table mb-0">
                <thead>
                  <tr>
                    <th></th>
                    <th>#</th>
                    <th>Airline</th>
                    <th>Ticketing</th>
                    <th>Sales (BDT)</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(airline, index) in airlines" :key="airline.name">
                    <td>
                      <i v-if="airline.trend === 'up'" class="bi bi-arrow-up-short text-success fw-bold"></i>
                      <i v-else-if="airline.trend === 'down'" class="bi bi-arrow-down-short text-danger fw-bold"></i>
                      <i v-else class="bi bi-dash text-secondary"></i>
                    </td>
                    <td><span class="rank-badge">{{ index + 1 }}</span></td>
                    <td class="airline-name">{{ airline.name }}</td>
                    <td class="ticketing-count">{{ airline.ticketing }}</td>
                    <td class="sales-amount">৳ {{ airline.sales }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>


      </div><!-- /row 4 -->

      <!-- ══════════════════════════════════════════════════════
           ROW 5 — Total Traveler | Last Ticketing Time
      ══════════════════════════════════════════════════════ -->

      <div class="row g-3 mb-3">
        <div class="col-12 col-lg-3 d-flex flex-column gap-3">
          <!-- Total Traveler -->
          <div class="dash-card">
            <div class="dash-card-header">
              <h3>Travelers</h3>
              <!-- <button class="btn-pill">August, 2026 <i class="bi bi-chevron-down"></i></button> -->
            </div>
            <div class="d-flex flex-column align-items-center gap-3">
              <div class="traveler-donut-wrap">
                <canvas id="travelerDonut"></canvas>

              </div>
              <div class="d-flex gap-3 justify-content-center flex-wrap">
                <span class="legend-item"><span class="ldot" style="background:#3b82f6"></span>Adult</span>
                <span class="legend-item"><span class="ldot" style="background:#8b5cf6"></span>Children</span>
                <span class="legend-item"><span class="ldot" style="background:#06b6d4"></span>Infant</span>
              </div>
            </div>
          </div>
          <!-- <div class="col-12 col-lg-3">

          </div> -->

          <!-- Support Tickets -->
          <div class="dash-card">
            <div class="dash-card-header">
              <h3>Suopprt Tickets</h3>
              <!-- <button class="btn-pill">August, 2026 <i class="bi bi-chevron-down"></i></button> -->
            </div>
            <div class="d-flex flex-column align-items-center gap-3 mt-3">
              <div class="support-donut-wrap">
                <canvas id="supportDonut"></canvas>

              </div>
              <div class="d-flex gap-3 justify-content-center flex-wrap">
                <span class="legend-item"><span class="ldot" style="background:#10B981"></span>Open</span>
                <span class="legend-item"><span class="ldot" style="background:#64748B"></span>Closed</span>
                <span class="legend-item"><span class="ldot" style="background:#F59E0B"></span>Hold</span>
              </div>
            </div>
          </div>
          <!-- <div class="col-12 col-lg-3">

          </div> -->

        </div>

        <!-- Booking Class -->
        <div class="col-12 col-lg-3">
          <div class="dash-card h-100">
            <div class="dash-card-header">
              <h3>Booking Classes</h3>
            </div>

            <div class="d-flex flex-column gap-3">
              <!-- Chart -->
              <div class="booking-class-chart align-item-center">
                <canvas id="bookingClassPie"></canvas>
              </div>

              <!-- Legend -->
              <div class="d-flex flex-column gap-3 booking-class-legend">

                <!-- Economy -->
                <div class="legend-item d-flex align-items-center justify-content-between w-100">
                  <div class="d-flex align-items-center gap-2">
                    <span class="ldot" style="background:#4f7ef8"></span>
                    <span>Economy</span>
                  </div>
                  <span class="fw-bold text-dark">{{ cabinClassData.economy || 0 }}</span>
                </div>

                <!-- Prem. Economy -->
                <div class="legend-item d-flex align-items-center justify-content-between w-100">
                  <div class="d-flex align-items-center gap-2">
                    <span class="ldot" style="background:#10b981"></span>
                    <span>Prem. Economy</span>
                  </div>
                  <span class="fw-bold text-dark">{{ cabinClassData.premium_economy || 0 }}</span>
                </div>

                <!-- Business -->
                <div class="legend-item d-flex align-items-center justify-content-between w-100">
                  <div class="d-flex align-items-center gap-2">
                    <span class="ldot" style="background:#f97316"></span>
                    <span>Business</span>
                  </div>
                  <span class="fw-bold text-dark">{{ cabinClassData.business || 0 }}</span>
                </div>

                <!-- First Class -->
                <div class="legend-item d-flex align-items-center justify-content-between w-100">
                  <div class="d-flex align-items-center gap-2">
                    <span class="ldot" style="background:#8b5cf6"></span>
                    <span>First</span>
                  </div>
                  <span class="fw-bold text-dark">{{ cabinClassData.first_class || 0 }}</span>
                </div>

              </div>

            </div>
          </div>
        </div>

        <!-- Trending Routes -->
        <div class="col-12 col-lg-6">
          <div class="dash-card h-100 d-flex flex-column">
            <div class="dash-card-header">
              <h3>Most Frequent Routes</h3>
              <PeriodSelector v-model="routesMonthSelection" :include-all="false" />
              <!-- <button class="btn-pill">August, 2026 <i class="bi bi-chevron-down"></i></button> -->
            </div>

            <div class="routes-list flex-grow-1">
              <div v-for="route in routes" :key="route.code" class="route-row">
                <div class="route-badge-wrap">
                  <span class="route-badge" :style="{ background: route.color, color: route.textColor }">
                    {{ route.code }}
                  </span>
                  <span class="route-num" :style="{ color: route.color }">{{ route.count }}</span>
                </div>
                <div class="route-track">
                  <div class="route-fill"
                    :style="{ width: pct(route.count, route.max) + '%', background: route.color }">
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /container-fluid -->

  </div>
</template>

<style scoped>
/* ── Root ── */
.dashboard-wrapper {
  /* padding: 24px; */
  background: #f0f4fa;
  min-height: 100vh;
  font-family: 'Plus Jakarta Sans', 'Nunito', sans-serif;
}

/* ════════════════════════════════
   KPI Card
════════════════════════════════ */
.kpi-card {
  position: relative;
  z-index: 1;
  background: #fff;
  border-radius: 18px;
  padding: 20px 22px;
  box-shadow: 0 2px 16px rgba(15, 21, 53, .06);
  height: 100%;
  transition: transform .2s, box-shadow .2s;
}

.kpi-card:has(.dropdown-menu.show) {
  z-index: 10;
  /* outranks the sibling kpi-card:hover stacking context */
}


.kpi-card:hover {
  /* transform: translateY(-2px);
  box-shadow: 0 8px 28px rgba(59, 130, 246, .1); */

  box-shadow: 0 10px 32px rgba(59, 130, 246, .15);
}

.kpi-label {
  font-size: 13px;
  font-weight: 700;
  color: #0f1535;
}

.kpi-value-row {
  display: flex;
  align-items: center;
  width: 100%;
}

.kpi-value {
  font-size: 24px;
  font-weight: 800;
  color: #0f1535;
  line-height: 1.1;
  margin: 4px 0 6px;
}

.kpi-trend {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  font-weight: 600;
  padding: 4px 10px;
  border-radius: 20px;
}

.kpi-trend.up {
  background: #ecfdf5;
  color: #10b981;
}

.kpi-trend.down {
  background: #fef2f2;
  /* Soft reddish background */
  color: #ef4444;
  /* Reddish icon & text color */
}

.kpi-badge-row {
  display: flex;
  align-items: center;
  gap: 5px;
}

#bookingDonut {
  width: 120px !important;
  height: 60px !important;
  flex-shrink: 0;
}

/* #salesBarChart {
  width: 100%;
  height: 320px !important;
} */

/* .kpi-donut-wrap {
  width: 88px;
  height: 88px;
  flex-shrink: 0;
} */

.kpi-donut-wrap canvas {
  width: 88px !important;
  height: 88px !important;
}

/* ════════════════════════════════
   Shared card
════════════════════════════════ */
.dash-card {
  background: #fff;
  border-radius: 18px;
  padding: 20px 22px;
  box-shadow: 0 2px 16px rgba(15, 21, 53, .06);
}

.dash-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 18px;
  flex-wrap: wrap;
  gap: 10px;
}

.dash-card-header h3 {
  font-size: 15px;
  font-weight: 700;
  color: #0f1535;
  margin: 0;
}

/* chart canvas container */
.chart-canvas-wrap {
  position: relative;
  min-height: 260px;
}

.chart-canvas-wrap canvas {
  width: 100% !important;
  height: 100% !important;
}

/* ════════════════════════════════
   Buttons & Legend atoms
════════════════════════════════ */
.btn-pill {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 20px;
  padding: 4px 14px;
  font-size: 12px;
  font-weight: 600;
  color: #7b8ab8;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  transition: border-color .2s, color .2s;
  white-space: nowrap;
}

.btn-pill:hover {
  border-color: #3b82f6;
  color: #3b82f6;
}

.ldot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  flex-shrink: 0;
  display: inline-block;
}

.legend-line {
  width: 22px;
  height: 3px;
  border-radius: 2px;
  display: inline-block;
  flex-shrink: 0;
}

.legend-pill-rect {
  width: 22px;
  height: 8px;
  border-radius: 4px;
  display: inline-block;
  flex-shrink: 0;
}

.legend-item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: #7b8ab8;
}

.chart-legend {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.chart-legend div {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11.5px;
  color: #7b8ab8;
}

/* ════════════════════════════════
   Trending Routes custom rows
════════════════════════════════ */
.routes-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.route-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.route-badge-wrap {
  display: flex;
  align-items: center;
  gap: 6px;
  min-width: 130px;
}

.route-badge {
  color: #fff;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 700;
  white-space: nowrap;
}

.route-num {
  font-size: 13px;
  font-weight: 700;
}

.route-track {
  flex: 1;
  height: 20px;
  background: #f4f6fb;
  border-radius: 6px;
  overflow: hidden;
}

.route-fill {
  height: 100%;
  border-radius: 6px;
  transition: width .6s ease;
}

.route-axis {
  display: flex;
  justify-content: space-between;
  padding: 4px 0 0;
  font-size: 11px;
  color: #94a3b8;
}

/* ════════════════════════════════
   Total Traveler donut
════════════════════════════════ */
.traveler-donut-wrap {
  position: relative;
  width: 120px;
  height: 120px;
  flex-shrink: 0;
}

.traveler-donut-wrap canvas {
  width: 120px !important;
  height: 120px !important;
}

.donut-label {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  pointer-events: none;
}

.donut-num {
  font-size: 24px;
  font-weight: 800;
  color: #8b5cf6;
}


.support-donut-wrap {
  position: relative;
  width: 120px;
  height: 60px;
  flex-shrink: 0;
}

.support-donut-wrap canvas {
  width: 120px !important;
  height: 60px !important;
}

/* ════════════════════════════════
   Booking Class 
════════════════════════════════ */
/* .booking-legend {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.booking-legend div {
  display: flex;
  align-items: center;
  gap: 7px;
  font-size: 12px;
  color: #7b8ab8;
} */

.booking-class-chart {
  width: 160px;
  height: 160px;
  margin: 20px auto 0;
}

.booking-class-chart canvas {
  width: 100% !important;
  height: 100% !important;
}

.booking-class-legend {
  width: 100%;
  align-items: flex-start;
}

/* ════════════════════════════════
   Airlines gauge
════════════════════════════════ */
.gauge-wrap {
  display: flex;
  justify-content: center;
  margin-bottom: 4px;
}

.gauge-wrap canvas {
  max-width: 300px;
  max-height: 170px;
}

.airlines-legend {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 5px 24px;
  padding: 0 4px;
}

.airline-legend-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: #7b8ab8;
}

/* ════════════════════════════════
   Upcoming Flights
════════════════════════════════ */
.flights-list {
  display: flex;
  flex-direction: column;
}

.flight-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 13px 0;
  border-bottom: 1px solid #f3f5fb;
  gap: 8px;
}

.flight-row:last-child {
  border-bottom: none;
}

.flight-route {
  font-size: 13px;
  font-weight: 700;
  color: #8b5cf6;
  min-width: 110px;
}

.flight-type {
  flex: 1;
  font-size: 13px;
  color: #64748b;
  text-align: center;
}

.flight-date {
  font-size: 13px;
  color: #64748b;
  white-space: nowrap;
}

/* ════════════════════════════════
   Responsive
════════════════════════════════ */
@media (max-width: 576px) {
  .dashboard-wrapper {
    padding: 12px;
  }

  .kpi-value {
    font-size: 30px;
  }

  .dash-card-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .route-badge-wrap {
    min-width: 110px;
  }
}

/* ════════════════════════════════
   Upcoming Departures — clickable
   date list (opens flight modal)
════════════════════════════════ */
.fdm-travel-list {
  display: flex;
  flex-direction: column;
}

.fdm-travel-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  width: 100%;
  padding: 13px 4px;
  border: none;
  border-bottom: 1px solid #f3f5fb;
  background: transparent;
  text-align: left;
  cursor: pointer;
  border-radius: 8px;
  transition: background 0.12s ease;
}

.fdm-travel-row:last-child {
  border-bottom: none;
}

.fdm-travel-row:hover,
.fdm-travel-row:focus-visible {
  background: #f4f7ff;
  outline: none;
}

.fdm-travel-main {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.fdm-travel-date {
  font-size: 13.5px;
  font-weight: 700;
  color: #0f1535;
}

.fdm-travel-sub {
  font-size: 12px;
  color: #94a3b8;
}

.fdm-travel-badge {
  background: #eef2ff;
  color: #4f7ef8;
  font-weight: 700;
  font-size: 12.5px;
  padding: 5px 12px;
  border-radius: 999px;
  white-space: nowrap;
  flex-shrink: 0;
}

/* ════════════════════════════════
   Flight details modal
════════════════════════════════ */
.fdm-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(11, 21, 53, 0.45);
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 3vh 1rem;
  z-index: 1050;
  overflow-y: auto;
}

.fdm-modal {
  background: #fff;
  width: 100%;
  max-width: 640px;
  border-radius: 16px;
  box-shadow: 0 20px 60px rgba(15, 21, 53, 0.25);
  display: flex;
  flex-direction: column;
  max-height: 92vh;
  overflow: hidden;
  animation: fdm-in 0.16s ease-out;
}

@keyframes fdm-in {
  from {
    opacity: 0;
    transform: translateY(8px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.fdm-header {
  background: #0f1535;
  color: #fff;
  padding: 1.25rem 1.5rem;
  flex-shrink: 0;
}

.fdm-title {
  font-size: 1.1rem;
  font-weight: 700;
  line-height: 1.3;
  color: #fff;
}

.fdm-close {
  background: rgba(255, 255, 255, 0.1);
  border: none;
  color: #fff;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  cursor: pointer;
  transition: background 0.12s ease;
}

.fdm-close:hover {
  background: rgba(255, 255, 255, 0.2);
}

.fdm-summary {
  font-size: 0.8rem;
  color: #b7c2e0;
  display: flex;
  align-items: center;
  gap: 0.4rem;
  flex-wrap: wrap;
}

.fdm-dot {
  width: 3px;
  height: 3px;
  border-radius: 50%;
  background: #5c6a94;
  display: inline-block;
}

.fdm-body {
  overflow-y: auto;
  min-height: 0;
  padding: 1.25rem 1.5rem 1.5rem;
  flex: 1;
}

.fdm-flight-card {
  border: 1px solid #e3e8ee;
  border-radius: 12px;
  margin-bottom: 1.1rem;
  overflow: hidden;
}

.fdm-flight-card:last-child {
  margin-bottom: 0;
}

.fdm-flight-head {
  padding: 1rem 1.15rem 0.95rem;
}

.fdm-flight-id {
  display: flex;
  align-items: center;
  gap: 0.55rem;
}

.fdm-plane-icon {
  width: 30px;
  height: 30px;
  border-radius: 8px;
  background: #eaf3fc;
  color: #1d6fc7;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.fdm-flight-number {
  font-weight: 700;
  font-size: 1rem;
  letter-spacing: 0.02em;
  color: #0f1535;
}

.fdm-airline-name {
  font-size: 0.8rem;
  color: #6b7a8c;
  margin-top: 0.05rem;
}

.fdm-badges {
  display: flex;
  gap: 0.4rem;
  flex-shrink: 0;
}

.fdm-badge-pill {
  font-size: 0.74rem;
  font-weight: 600;
  padding: 0.3rem 0.6rem;
  border-radius: 999px;
  white-space: nowrap;
}

.fdm-badge-pax {
  background: #eaf3fc;
  color: #15579e;
}

.fdm-badge-book {
  background: #fbf1e3;
  color: #8a5a17;
}

.fdm-route-row {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  margin-top: 0.75rem;
  font-size: 1.05rem;
  font-weight: 600;
  color: #0f1535;
}

.fdm-route-arrow {
  color: #c7d0db;
  font-size: 1.1rem;
}

.fdm-perforation {
  position: relative;
  height: 1px;
  background: repeating-linear-gradient(90deg, #c7d0db 0 6px, transparent 6px 12px);
}

.fdm-perforation::before,
.fdm-perforation::after {
  content: '';
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  width: 14px;
  height: 14px;
  background: #f5f7fa;
  border: 1px solid #e3e8ee;
  border-radius: 50%;
}

.fdm-perforation::before {
  left: -8px;
}

.fdm-perforation::after {
  right: -8px;
}

.fdm-passenger-wrap {
  padding: 0.85rem 1.15rem 1.05rem;
}

.fdm-passenger-label {
  font-size: 0.72rem;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #6b7a8c;
  display: flex;
  justify-content: space-between;
  padding: 0 0.1rem 0.5rem;
}

.fdm-passenger-list {
  max-height: 212px;
  overflow-y: auto;
  border-top: 1px solid #f1f4f8;
}

.fdm-passenger-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.55rem 0.1rem;
  border-bottom: 1px solid #f1f4f8;
  font-size: 0.87rem;
}

.fdm-passenger-row:last-child {
  border-bottom: none;
}

.fdm-passenger-name {
  font-weight: 500;
  color: #0f1535;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.fdm-passenger-contact {
  font-size: 0.82rem;
  color: #1d6fc7;
  text-decoration: none;
  white-space: nowrap;
  flex-shrink: 0;
}

.fdm-passenger-contact:hover {
  text-decoration: underline;
}

.fdm-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 3rem 1.5rem;
  color: #6b7a8c;
}

.fdm-empty-icon {
  width: 52px;
  height: 52px;
  border-radius: 50%;
  background: #f1f4f8;
  color: #c7d0db;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 1rem;
}

.fdm-empty p {
  margin: 0;
  font-size: 0.9rem;
  font-weight: 500;
  color: #3d4a59;
}

.fdm-skeleton {
  background: linear-gradient(90deg, #f1f4f8 25%, #e3e8ee 37%, #f1f4f8 63%);
  background-size: 400% 100%;
  animation: fdm-sk 1.4s ease infinite;
  border-radius: 6px;
}

.fdm-sk-line {
  height: 12px;
  margin-bottom: 0.5rem;
}

.today-live {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.live-wave {
  width: 15px;
  height: 12px;
  color: #10b981;
  overflow: hidden;
}

.live-wave path {
  fill: none;
  stroke: currentColor;
  stroke-width: 3;
  stroke-linecap: round;

  /* Animate the actual curve path */
  stroke-dasharray: 0.3 0.7;
  animation: live-wave-flow 1.2s linear infinite;
}

@keyframes live-wave-flow {
  /* from {
    stroke-dashoffset: 0;
  }

  to {
    stroke-dashoffset: -100;
  } */

  0% {
    stroke-dashoffset: 0;
  }

  100% {
    stroke-dashoffset: -1;
  }
}

@keyframes fdm-sk {
  0% {
    background-position: 100% 50%;
  }

  100% {
    background-position: 0 50%;
  }
}

@media (max-width: 480px) {
  .fdm-header {
    padding: 1rem 1.1rem;
  }

  .fdm-body {
    padding: 1rem;
  }

  .fdm-route-row {
    font-size: 0.95rem;
  }

  .fdm-passenger-contact {
    font-size: 0.76rem;
  }
}

/* ════════════════════════════════
   Ticketing Deadlines card
════════════════════════════════ */
.tkt-count-badge {
  background: #fee2e2;
  color: #b91c1c;
  font-size: 12px;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 999px;
  white-space: nowrap;
}

.tkt-window-pills {
  display: flex;
  gap: 6px;
}

.tkt-pill-active {
  border-color: #4f7ef8 !important;
  color: #4f7ef8 !important;
  background: #eef2ff !important;
}

.tkt-empty {
  padding: 24px 4px;
  text-align: center;
  font-size: 13px;
  color: #94a3b8;
}

.tkt-list {
  display: flex;
  flex-direction: column;
}

.tkt-row {
  display: grid;
  grid-template-columns: 96px 1.1fr 1.2fr 1.1fr auto;
  align-items: center;
  gap: 14px;
  width: 100%;
  padding: 12px 8px;
  border: none;
  border-bottom: 1px solid #f3f5fb;
  background: transparent;
  text-align: left;
  cursor: pointer;
  border-radius: 8px;
  transition: background 0.12s ease;
}

.tkt-row:last-child {
  border-bottom: none;
}

.tkt-row:hover,
.tkt-row:focus-visible {
  background: #f9fafc;
  outline: none;
}

.tkt-col {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.tkt-col-lt {
  display: flex;
  flex-direction: column;
  gap: 2px;
  white-space: normal;
}

.tkt-lt-date {
  font-size: 13px;
  font-weight: 700;
  color: #0f1535;
}

.tkt-lt-time {
  font-size: 11.5px;
  font-weight: 500;
  color: #94a3b8;
}

.tkt-col-airline {
  font-size: 13px;
  font-weight: 600;
  color: #0f1535;
}

.tkt-col-pax {
  display: flex;
}

.tkt-pax-name {
  font-size: 13px;
  color: #334155;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.tkt-col-contact {
  font-size: 12.5px;
  color: #4f7ef8;
  text-decoration: none;
}

.tkt-col-contact:hover {
  text-decoration: underline;
}

.tkt-col-timeleft {
  display: flex;
  justify-content: flex-end;
}

.tkt-badge {
  font-size: 12px;
  font-weight: 700;
  padding: 5px 11px;
  border-radius: 999px;
  white-space: nowrap;
  flex-shrink: 0;
}

.tkt-badge-overdue {
  background: #fee2e2;
  color: #b91c1c;
}

.tkt-badge-critical {
  background: #ffedd5;
  color: #c2410c;
}

.tkt-badge-warning {
  background: #fef9c3;
  color: #a16207;
}

.tkt-row-labels {
  cursor: default;
  padding-top: 0;
  padding-bottom: 8px;
  border-bottom: 1px solid #eef1f6;
}

.tkt-row-labels .tkt-col {
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #94a3b8;
}

.tkt-row-labels:hover {
  background: transparent;
}

@media (max-width: 767px) {
  .tkt-row-labels {
    display: none;
  }

  .tkt-row {
    grid-template-columns: 1fr auto;
    grid-template-areas:
      'lt timeleft'
      'airline airline'
      'pax pax'
      'contact contact';
    row-gap: 4px;
  }

  .tkt-col-lt {
    grid-area: lt;
  }

  .tkt-col-timeleft {
    grid-area: timeleft;
  }

  .tkt-col-airline {
    grid-area: airline;
    white-space: normal;
  }

  .tkt-col-pax {
    grid-area: pax;
  }

  .tkt-col-contact {
    grid-area: contact;
  }
}

/* ════════════════════════════════
   Ticketing Deadline booking modal
════════════════════════════════ */
.tktm-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(11, 21, 53, 0.45);
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 3vh 1rem;
  z-index: 1050;
  overflow-y: auto;
}

.tktm-modal {
  background: #fff;
  width: 100%;
  max-width: 460px;
  border-radius: 16px;
  box-shadow: 0 20px 60px rgba(15, 21, 53, 0.25);
  display: flex;
  flex-direction: column;
  max-height: 92vh;
  overflow: hidden;
  animation: fdm-in 0.16s ease-out;
}

.tktm-header {
  background: #0f1535;
  color: #fff;
  padding: 1.25rem 1.5rem;
  flex-shrink: 0;
}

.tktm-title {
  font-size: 1.1rem;
  font-weight: 700;
  line-height: 1.3;
}

.tktm-summary {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  flex-wrap: wrap;
}

.tktm-deadline-text {
  font-size: 0.8rem;
  color: #b7c2e0;
}

.tktm-body {
  overflow-y: auto;
  min-height: 0;
  padding: 1.25rem 1.5rem 1.5rem;
  flex: 1;
}

.tktm-field-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 0.6rem 0.1rem;
  font-size: 0.87rem;
}

.tktm-field-label {
  color: #6b7a8c;
  font-weight: 500;
}

.tktm-field-value {
  color: #0f1535;
  font-weight: 600;
  text-align: right;
}

.tktm-code {
  letter-spacing: 0.04em;
}

.tktm-perforation {
  margin: 0.4rem 0;
}
</style>
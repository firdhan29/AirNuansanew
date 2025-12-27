<script setup>
import { ref, computed, reactive, watch, onMounted, onUnmounted } from 'vue';
import { usePage, Link, router, Head } from '@inertiajs/vue3';
import axios from 'axios';
import { 
    Waves, User, Wallet, Trash2, Edit2, LogIn, LogOut, Plus, X, Search, ChevronRight, ChevronDown, CheckCircle2, AlertCircle, Menu, Printer, Download, Settings, Lock, QrCode, TrendingUp, TrendingDown, Scale, Home, Banknote, MapPin, MessageCircle, Megaphone, Circle, History, Calendar, Calculator 
} from 'lucide-vue-next';

// 1. PROPS DARI CONTROLLER
const props = defineProps({
    wargas: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
    expenses: { type: Array, default: () => [] },
    incomes: { type: Array, default: () => [] },
    user: { type: Object, default: null }
});

// 2. STATE
const page = usePage();
const currentUser = computed(() => props.user || page.props.auth?.user);

const activeTab = ref('dashboard');
const residents = ref(props.wargas || []); 
const adminList = ref(props.users || []);
const expenseList = ref(props.expenses || []);
const incomeList = ref(props.incomes || []);

const showAmountOptions = ref(null); 
const isMobileMenuOpen = ref(false);
const showBanner = ref(true);

// State Tahun & Keuangan
const currentYear = new Date().getFullYear();
const paymentYear = ref(currentYear); 
const selectedFinancialYear = ref(currentYear);
const financeViewMode = ref('monthly'); 
const availableYears = [2025, 2026, 2027]; 

// State Accordion
const openBlocks = reactive({}); 
const toggleBlock = (blockName) => { openBlocks[blockName] = !openBlocks[blockName]; };

const allTabs = [
    { id: 'dashboard', label: 'Data Warga', icon: Home },
    { id: 'finance', label: 'Keuangan', icon: Wallet },
    { id: 'settings', label: 'Pengaturan', icon: Settings }
];

const visibleTabs = computed(() => {
    return allTabs.filter(tab => currentUser.value || tab.id !== 'settings');
});

// Handle ESC Key
const handleKeydown = (e) => {
    if (e.key === 'Escape') {
        showResidentModal.value = false;
        showPaymentModal.value = false;
        showAdminModal.value = false;
        showQrisModal.value = false;
        showExpenseModal.value = false;
        showIncomeModal.value = false;
        showAmountOptions.value = null;
        isMobileMenuOpen.value = false;
    }
};
onMounted(() => document.addEventListener('keydown', handleKeydown));
onUnmounted(() => document.removeEventListener('keydown', handleKeydown));

// Watcher untuk update data real-time jika Inertia melakukan reload
watch(() => props.wargas, (newVal) => { residents.value = newVal || []; }, { deep: true });
watch(() => props.users, (newVal) => { adminList.value = newVal || []; }, { deep: true });
watch(() => props.expenses, (newVal) => { expenseList.value = newVal || []; }, { deep: true });
watch(() => props.incomes, (newVal) => { incomeList.value = newVal || []; }, { deep: true });

// --- FILTER & GROUPING ---
const searchQuery = ref('');
const filteredResidents = computed(() => {
    let data = residents.value;
    if (searchQuery.value) {
        const lowerSearch = searchQuery.value.toLowerCase();
        data = data.filter(r => 
            (r.nama && r.nama.toLowerCase().includes(lowerSearch)) || 
            (r.blok && r.blok.toLowerCase().includes(lowerSearch)) || 
            (r.nomor && r.nomor.toString().includes(lowerSearch))
        );
    }
    return data.sort((a, b) => {
        const blokA = (a.blok || '').replace(/\s+/g, '').toUpperCase();
        const blokB = (b.blok || '').replace(/\s+/g, '').toUpperCase();
        if (blokA === blokB) return parseInt(a.nomor) - parseInt(b.nomor);
        return blokA.localeCompare(blokB);
    });
});

const groupedResidents = computed(() => {
    const groups = {};
    filteredResidents.value.forEach(r => {
        let cleanBlok = (r.blok || 'LAINNYA').toString();
        cleanBlok = cleanBlok.replace(/^blok\s*/i, '').replace(/\s+/g, '').toUpperCase();
        if (!groups[cleanBlok]) groups[cleanBlok] = [];
        groups[cleanBlok].push(r);
    });
    const sortedKeys = Object.keys(groups).sort();
    const sortedGroups = {};
    sortedKeys.forEach(key => { sortedGroups[key] = groups[key]; });
    return sortedGroups;
});

// --- CONSTANTS ---
const MONTH_KEYS = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
const MONTH_NAMES = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const MONTHS = MONTH_KEYS.map((key, index) => ({ key, label: MONTH_NAMES[index] }));

// --- HELPER FUNCTIONS ---
const getHistoryKey = (monthKey, year) => {
    return year === 2025 ? monthKey : `${monthKey}_${year}`;
};

const selectedMonthKey = ref(MONTH_KEYS[new Date().getMonth()]); 

const getPaymentAmount = (resident, monthKey, year) => {
    const targetYear = year || paymentYear.value; 
    const key = getHistoryKey(monthKey, targetYear);
    const val = resident.payment_history?.[key];
    if (typeof val === 'number') return val; 
    if (val === true) return parseInt(resident.tarif || 20000); 
    return 0;
};

// ==========================================
// 1. LOGIKA SALDO BERJALAN (ACCUMULATED)
// ==========================================
const runningBalanceData = computed(() => {
    const targetYear = selectedFinancialYear.value;
    const targetMonthIdx = MONTH_KEYS.indexOf(selectedMonthKey.value);

    let saldoAwal = 0;
    let currentMonthInc = 0;
    let currentMonthExp = 0;
    let currentMonthResTotal = 0;
    let currentMonthManTotal = 0;

    for (const year of availableYears) {
        if (year > targetYear) break;

        for (let mIdx = 0; mIdx < 12; mIdx++) {
            if (year === targetYear && mIdx > targetMonthIdx) break;

            const isTargetMonth = (year === targetYear && mIdx === targetMonthIdx);
            const mKey = MONTH_KEYS[mIdx];
            const historyKey = getHistoryKey(mKey, year);

            let monthIn = 0;
            let monthOut = 0;
            let mResIncome = 0;
            let mManIncome = 0;

            residents.value.forEach(r => {
                const val = r.payment_history?.[historyKey];
                let amt = 0;
                if (typeof val === 'number') amt = val;
                else if (val === true) amt = parseInt(r.tarif || 20000);
                
                mResIncome += amt;
            });

            incomeList.value.forEach(inc => {
                const d = new Date(inc.date);
                if (d.getFullYear() === year && d.getMonth() === mIdx) mManIncome += parseInt(inc.amount);
            });

            expenseList.value.forEach(ex => {
                const d = new Date(ex.date);
                if (d.getFullYear() === year && d.getMonth() === mIdx) monthOut += parseInt(ex.amount);
            });

            monthIn = mResIncome + mManIncome;

            if (isTargetMonth) {
                currentMonthInc = monthIn;
                currentMonthExp = monthOut;
                currentMonthResTotal = mResIncome;
                currentMonthManTotal = mManIncome;
            } else {
                saldoAwal += (monthIn - monthOut);
            }
        }
    }

    const netMonth = currentMonthInc - currentMonthExp;
    const saldoAkhir = saldoAwal + netMonth;

    return {
        saldoAwal,
        saldoAkhir,
        netMonth,
        currentMonthInc,
        currentMonthExp,
        currentMonthResTotal,
        currentMonthManTotal
    };
});

const currentMonthResidentRevenue = computed(() => {
    let total = 0;
    let count = 0;
    const year = selectedFinancialYear.value;
    const mKey = selectedMonthKey.value;
    const historyKey = getHistoryKey(mKey, year);

    residents.value.forEach(r => {
        const val = r.payment_history?.[historyKey];
        let amount = 0;
        if (typeof val === 'number') amount = val;
        else if (val === true) amount = parseInt(r.tarif || 20000);

        if (amount > 0) {
            total += amount;
            count++;
        }
    });
    return { total, count }; 
});

const currentMonthManualIncome = computed(() => {
    if (!incomeList.value) return 0;
    return incomeList.value.filter(i => {
        const d = new Date(i.date);
        return MONTH_KEYS[d.getMonth()] === selectedMonthKey.value && d.getFullYear() === selectedFinancialYear.value;
    }).reduce((acc, curr) => acc + parseInt(curr.amount), 0);
});

const currentMonthTotalRevenue = computed(() => currentMonthResidentRevenue.value.total + currentMonthManualIncome.value);

const currentMonthExpensesList = computed(() => {
    if (!expenseList.value) return [];
    return expenseList.value.filter(e => {
        const d = new Date(e.date);
        return MONTH_KEYS[d.getMonth()] === selectedMonthKey.value && d.getFullYear() === selectedFinancialYear.value;
    });
});

const currentMonthIncomesList = computed(() => {
    if (!incomeList.value) return [];
    return incomeList.value.filter(i => {
        const d = new Date(i.date);
        return MONTH_KEYS[d.getMonth()] === selectedMonthKey.value && d.getFullYear() === selectedFinancialYear.value;
    });
});

const yearlyReportData = computed(() => {
    const report = [];
    const year = selectedFinancialYear.value;

    MONTH_KEYS.forEach((mKey, index) => {
        let resIncome = 0;
        residents.value.forEach(r => { 
            const k = getHistoryKey(mKey, year);
            const val = r.payment_history?.[k];
            if(typeof val === 'number') resIncome += val;
            else if(val === true) resIncome += parseInt(r.tarif || 20000);
        });
        
        let manIncome = 0;
        incomeList.value.forEach(inc => { const d = new Date(inc.date); if (d.getMonth() === index && d.getFullYear() === year) manIncome += parseInt(inc.amount); });
        
        let exp = 0;
        expenseList.value.forEach(ex => { const d = new Date(ex.date); if (d.getMonth() === index && d.getFullYear() === year) exp += parseInt(ex.amount); });
        
        const monthlySurplus = (resIncome + manIncome) - exp;

        report.push({ 
            monthName: MONTH_NAMES[index], 
            income: resIncome + manIncome, 
            expense: exp, 
            surplus: monthlySurplus 
        });
    });
    return report;
});

const yearlyTotalIncome = computed(() => yearlyReportData.value.reduce((acc, curr) => acc + curr.income, 0));
const yearlyTotalExpense = computed(() => yearlyReportData.value.reduce((acc, curr) => acc + curr.expense, 0));
const yearlySurplus = computed(() => yearlyTotalIncome.value - yearlyTotalExpense.value);

const downloadCSV = () => { alert("Fitur download Excel tersedia."); };

const sendWhatsapp = (resident) => {
    if (!resident.telepon) return alert('Nomor telepon belum diisi.');
    let phone = resident.telepon.toString().replace(/\D/g, '');
    if (phone.startsWith('0')) phone = '62' + phone.substring(1);
    const monthName = MONTH_NAMES[new Date().getMonth()];
    const amount = formatRupiah(resident.tarif || 20000);
    const text = `Halo Bpk/Ibu ${resident.nama} (Blok ${resident.blok}/${resident.nomor}),%0A%0AMengingatkan tagihan air bulan *${monthName} ${currentYear}* sebesar *${amount}* belum lunas.`;
    window.open(`https://wa.me/${phone}?text=${text}`, '_blank');
};

// --- MODAL & FORM ---
const showResidentModal = ref(false);
const showPaymentModal = ref(false);
const showAdminModal = ref(false);
const showQrisModal = ref(false);
const showExpenseModal = ref(false);
const showIncomeModal = ref(false);

const selectedResident = ref(null);
const isEditing = ref(false);
const editId = ref(null);

const formResident = reactive({ nama: '', blok: '', nomor: '', telepon: '', tarif: 20000 });
const formAdmin = reactive({ name: '', email: '', password: '', password_confirmation: '' });
const isAdminEditing = ref(false);
const editAdminId = ref(null);

const formExpense = reactive({ description: '', amount: '', date: new Date().toISOString().split('T')[0] });
const isExpenseEditing = ref(false);
const editExpenseId = ref(null);

const formIncome = reactive({ description: '', amount: '', date: new Date().toISOString().split('T')[0] });
const isIncomeEditing = ref(false);
const editIncomeId = ref(null);

const formatRupiah = (val) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
const formatDate = (dateString) => {
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('id-ID', options);
};
const printCard = () => { window.print(); };

// --- ACTIONS (FIXED) ---
const deleteItem = async (type, id) => {
    if(!confirm('Hapus data ini?')) return;
    try {
        if(type === 'resident') {
            await axios.post(`/wargas/delete/${id}`);
            router.reload({ only: ['wargas'] });
        }
        if(type === 'admin') {
            const response = await axios.post(`/users/delete/${id}`);
            alert(response.data.message);
            router.reload({ only: ['users'] });
        }
        if(type === 'expense') {
            const response = await axios.post(`/expenses/delete/${id}`);
            alert(response.data.message);
            router.reload({ only: ['expenses'] });
        }
        if(type === 'income') {
            const response = await axios.post(`/incomes/delete/${id}`);
            alert(response.data.message);
            router.reload({ only: ['incomes'] });
        }
    } catch (e) { alert("Gagal hapus."); }
};

const openResidentModal = (resident = null) => {
    if (resident) {
        isEditing.value = true; editId.value = resident.id;
        formResident.nama = resident.nama; formResident.blok = resident.blok; formResident.nomor = resident.nomor || ''; formResident.telepon = resident.telepon || ''; formResident.tarif = resident.tarif || 20000;
    } else {
        isEditing.value = false; editId.value = null;
        formResident.nama = ''; formResident.blok = ''; formResident.nomor = ''; formResident.telepon = ''; formResident.tarif = 20000;
    }
    showResidentModal.value = true;
};

const submitResident = async () => {
    try {
        if (isEditing.value) await axios.post(`/wargas/update/${editId.value}`, formResident);
        else await axios.post('/wargas/store', formResident);
        showResidentModal.value = false; 
        router.reload({ only: ['wargas'] });
    } catch (e) { alert("Gagal."); }
};

const openExpenseModal = (expense = null) => {
    if (expense) {
        isExpenseEditing.value = true; editExpenseId.value = expense.id;
        formExpense.description = expense.description; formExpense.amount = expense.amount; formExpense.date = expense.date; 
    } else {
        isExpenseEditing.value = false; editExpenseId.value = null;
        formExpense.description = ''; formExpense.amount = ''; formExpense.date = new Date().toISOString().split('T')[0];
    }
    showExpenseModal.value = true;
};

const submitExpense = async () => {
    try {
        const response = isExpenseEditing.value
            ? await axios.post(`/expenses/update/${editExpenseId.value}`, formExpense)
            : await axios.post('/expenses/store', formExpense);
        alert(response.data.message);
        showExpenseModal.value = false;
        router.reload({ only: ['expenses'] });
    } catch (e) { alert("Gagal menyimpan pengeluaran."); }
};

const openIncomeModal = (income = null) => {
    if (income) {
        isIncomeEditing.value = true; editIncomeId.value = income.id;
        formIncome.description = income.description; formIncome.amount = income.amount; formIncome.date = income.date;
    } else {
        isIncomeEditing.value = false; editIncomeId.value = null;
        formIncome.description = ''; formIncome.amount = ''; formIncome.date = new Date().toISOString().split('T')[0];
    }
    showIncomeModal.value = true;
};

const submitIncome = () => {
    const url = isIncomeEditing.value ? `/incomes/update/${editIncomeId.value}` : '/incomes/store';
    router.post(url, formIncome, {
        onSuccess: () => {
            showIncomeModal.value = false;
            alert("Berhasil!");
        },
        onError: () => alert("Gagal menyimpan pemasukan.")
    });
};

const openAdminModal = (admin = null) => {
    if (admin) {
        isAdminEditing.value = true; editAdminId.value = admin.id;
        formAdmin.name = admin.name; formAdmin.email = admin.email;
        formAdmin.password = ''; formAdmin.password_confirmation = '';
    } else {
        isAdminEditing.value = false; editAdminId.value = null;
        formAdmin.name = ''; formAdmin.email = ''; formAdmin.password = ''; formAdmin.password_confirmation = '';
    }
    showAdminModal.value = true;
};

const submitAdmin = async () => {
    try {
        const response = isAdminEditing.value
            ? await axios.post(`/users/update/${editAdminId.value}`, formAdmin)
            : await axios.post('/users/store', formAdmin);
        alert(response.data.message);
        showAdminModal.value = false;
        router.reload({ only: ['users'] });
    } catch (e) {
        if (e.response && e.response.data && e.response.data.error) alert(e.response.data.error);
        else alert("Gagal menyimpan data admin.");
    }
};

const openPaymentModal = (resident) => { 
    selectedResident.value = resident; 
    showPaymentModal.value = true; 
    showAmountOptions.value = null;
    paymentYear.value = new Date().getFullYear(); 
};

const togglePayment = async (monthKey, customAmount = null) => {
    if (!currentUser.value) return; 
    if (!selectedResident.value.payment_history) selectedResident.value.payment_history = {};
    const dynamicKey = getHistoryKey(monthKey, paymentYear.value);
    const currentData = selectedResident.value.payment_history[dynamicKey];
    if (currentData) {
        if(!confirm(`Batalkan pembayaran ${monthKey.toUpperCase()} ${paymentYear.value}?`)) return;
        selectedResident.value.payment_history[dynamicKey] = null;
        try { await axios.post(`/wargas/pay/${selectedResident.value.id}`, { month: dynamicKey, amount: null }); router.reload({ only: ['wargas'] }); } catch (e) {}
    } else {
        const amountToPay = customAmount || parseInt(selectedResident.value.tarif || 20000);
        selectedResident.value.payment_history[dynamicKey] = amountToPay; 
        try { await axios.post(`/wargas/pay/${selectedResident.value.id}`, { month: dynamicKey, amount: amountToPay }); router.reload({ only: ['wargas'] }); } catch (e) { selectedResident.value.payment_history[dynamicKey] = null; }
    }
    showAmountOptions.value = null; 
};

// State untuk menyimpan bulan yang dipilih di laporan tunggakan
const debtReportMonthKey = ref(['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'][new Date().getMonth()]);

// Logika untuk menyaring warga yang belum bayar berdasarkan bulan yang dipilih
const overdueResidents = computed(() => {
    const historyKey = getHistoryKey(debtReportMonthKey.value, currentYear);
    return residents.value.filter(r => {
        const payment = r.payment_history?.[historyKey];
        return !payment; // Mengambil warga yang data pembayarannya kosong atau null
    });
});

// Fungsi untuk mengirim pesan tagihan via WhatsApp secara otomatis
const sendWhatsappDebt = (resident) => {
    const monthName = MONTH_NAMES[MONTH_KEYS.indexOf(debtReportMonthKey.value)];
    const text = `Halo Bpk/Ibu ${resident.nama} (Blok ${resident.blok}/${resident.nomor}),%0A%0AMengingatkan iuran air bulan *${monthName} ${currentYear}* sebesar *${formatRupiah(resident.tarif)}* belum lunas. Mohon segera melakukan pembayaran. Terima kasih.`;
    window.open(`https://wa.me/${resident.telepon.toString().replace(/\D/g, '')}?text=${text}`, '_blank');
};
</script>

<template>
  <div> 
    <Head title="Dashboard Air" />

    <div class="min-h-screen bg-[#F8FAFC] font-sans text-slate-800 pb-20 md:pb-10 print:hidden">
        <nav class="sticky top-0 z-40 w-full bg-white/80 backdrop-blur-lg border-b border-slate-200/50 shadow-sm transition-all">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 items-center justify-between">
                    <div class="flex items-center gap-3 cursor-pointer group" @click="activeTab = 'dashboard'">
                        <div class="relative flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-500/20 transition-transform group-hover:scale-105"><Waves :size="20" class="text-white" /></div>
                        <div class="flex flex-col"><h1 class="text-xl font-extrabold tracking-tight text-slate-800">Air<span class="text-blue-600">Nuansa</span></h1></div>
                    </div>
                    <div class="hidden md:flex items-center bg-slate-100 p-1.5 rounded-full border border-slate-200">
                        <button v-for="tab in visibleTabs" :key="tab.id" @click="activeTab = tab.id" :class="['flex items-center gap-2 px-5 py-2 rounded-full text-xs font-bold transition-all duration-300', activeTab === tab.id ? 'bg-white text-blue-600 shadow-sm ring-1 ring-black/5' : 'text-slate-500 hover:text-slate-900 hover:bg-white/50']"><component :is="tab.icon" :size="16" :class="{ 'stroke-[2.5px]': activeTab === tab.id }" /> {{ tab.label }}</button>
                    </div>
                    <div class="flex items-center gap-3">
                        <div v-if="currentUser" class="hidden md:flex items-center gap-3 pl-4 border-l border-slate-200">
                            <div class="text-right"><p class="text-xs font-bold text-slate-800">{{ currentUser?.name }}</p><p class="text-[10px] text-slate-500">Admin</p></div>
                            <Link href="/logout" method="post" as="button" class="bg-white border border-slate-200 text-slate-500 hover:bg-red-50 hover:text-red-500 hover:border-red-200 p-2 rounded-xl transition-all shadow-sm" title="Logout"><LogOut :size="18" /></Link>
                        </div>
                        <div v-else class="hidden md:block"><Link href="/login" class="flex items-center gap-2 bg-slate-900 text-white px-5 py-2 rounded-xl text-xs font-bold shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition-all"><LogIn :size="16" /> Login</Link></div>
                        <button @click="isMobileMenuOpen = !isMobileMenuOpen" class="md:hidden p-2 text-slate-600 bg-white border border-slate-100 rounded-lg shadow-sm hover:bg-slate-50 transition"><Menu v-if="!isMobileMenuOpen" :size="24" /> <X v-else :size="24" /></button>
                    </div>
                </div>
            </div>
            <div v-if="isMobileMenuOpen" class="md:hidden absolute top-16 inset-x-0 bg-white border-b border-slate-100 shadow-xl p-4 flex flex-col gap-2 z-50 animate-in slide-in-from-top-2">
                <button v-for="tab in visibleTabs" :key="tab.id" @click="activeTab=tab.id; isMobileMenuOpen=false" :class="['p-4 rounded-xl text-left font-bold flex gap-3 capitalize text-sm', activeTab===tab.id ? 'bg-blue-50 text-blue-600' : 'text-slate-600 hover:bg-slate-50']"><component :is="tab.icon" :size="18" /> {{ tab.label }}</button>
                <div class="border-t pt-3 mt-1" v-if="!currentUser"><Link href="/login" class="flex justify-center items-center gap-2 bg-slate-900 text-white w-full p-3 rounded-lg text-sm font-bold shadow"><LogIn :size="14" /> Login Admin</Link></div>
                <div class="border-t pt-3 mt-1 flex justify-between items-center" v-else><span class="font-bold text-slate-700 ml-2 text-sm">Halo, {{ currentUser?.name }}</span><Link href="/logout" method="post" as="button" class="bg-red-50 text-red-600 font-bold px-3 py-1.5 rounded text-xs">Logout</Link></div>
            </div>
        </nav>

        <main class="max-w-7xl mx-auto px-4 py-8">
            <div v-if="showBanner && activeTab === 'dashboard'" class="animate-in slide-in-from-top-4 duration-500 mb-6 relative overflow-hidden rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-500/20">
                <div class="p-4 flex items-start gap-4">
                    <div class="bg-white/20 p-2 rounded-lg backdrop-blur-sm"><Megaphone :size="24" class="text-white"/></div>
                    <div class="flex-1"><h3 class="font-bold text-lg mb-1">Informasi Warga</h3><p class="text-blue-50 text-sm leading-relaxed opacity-90">Jangan lupa untuk membayar iuran air sebelum tanggal 10 setiap bulannya.</p><p class="text-blue-50 text-sm leading-relaxed opacity-90">Diberitahukan kepada seluruh warga bahwa akan dilakukan pemadaman aliran air setiap hari pada pukul 12.00–15.00.</p></div>
                    <button @click="showBanner = false" class="bg-white/10 hover:bg-white/20 p-1.5 rounded-lg transition"><X :size="16"/></button>
                </div>
            </div>

            <div v-if="activeTab === 'dashboard'" class="animate-in fade-in zoom-in duration-300">
                <div class="flex flex-col md:flex-row justify-between items-end gap-4 mb-6">
                    <div><h2 class="text-3xl font-extrabold text-slate-800 tracking-tight">Data Warga</h2><p class="text-slate-500 text-sm mt-1">Daftar hunian blok Nuansa Manisi Regency.</p></div>
                    <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
                        <div class="relative group w-full sm:w-64"><input v-model="searchQuery" type="text" placeholder="Cari nama atau blok..." class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-medium shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"><Search :size="18" class="absolute left-3 top-3 text-slate-400 group-focus-within:text-blue-500 transition-colors"/></div>
                        <button v-if="currentUser" @click="openResidentModal()" class="bg-slate-900 text-white px-5 py-2.5 rounded-xl text-sm font-bold flex items-center justify-center gap-2 transition-all hover:-translate-y-0.5 hover:shadow-lg active:scale-95"><Plus :size="18" /> <span class="hidden sm:inline">Tambah Warga</span><span class="sm:hidden">Tambah</span></button>
                    </div>
                </div>
                <div v-for="(wargas, blockName) in groupedResidents" :key="blockName" class="mb-4 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden transition-all duration-300">
                    <div @click="toggleBlock(blockName)" class="p-4 flex justify-between items-center cursor-pointer bg-slate-50 hover:bg-slate-100 transition-colors select-none">
                        <div class="flex items-center gap-3"><div class="bg-slate-800 text-white p-1.5 rounded-lg shadow-sm"><MapPin :size="18"/></div><h3 class="text-lg font-bold text-slate-800">Blok {{ blockName }}</h3><span class="text-xs font-bold bg-white border border-slate-200 text-slate-500 px-2.5 py-1 rounded-full shadow-sm">{{ wargas.length }} Rumah</span></div>
                        <ChevronDown :size="20" class="text-slate-400 transition-transform duration-300" :class="{'rotate-180': openBlocks[blockName]}" />
                    </div>
                    <div v-show="openBlocks[blockName]" class="p-4 bg-white border-t border-slate-100 animate-in slide-in-from-top-2">
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                            <div v-for="r in wargas" :key="r.id" class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm hover:border-blue-300 transition-all relative group">
                                <div class="flex justify-between items-start mb-3">
                                    <span class="bg-slate-50 text-slate-600 border border-slate-200 px-2.5 py-1 rounded-md text-xs font-bold flex items-center gap-1.5"><Home :size="12" /> {{ r.blok }} / {{ r.nomor }}</span>
                                    <div v-if="currentUser" class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button v-if="r.telepon" @click="sendWhatsapp(r)" class="text-green-500 hover:bg-green-50 p-1 rounded" title="WA"><MessageCircle :size="14"/></button>
                                        <button @click="openResidentModal(r)" class="text-slate-400 hover:text-yellow-600 p-1"><Edit2 :size="14"/></button>
                                        <button @click="deleteItem('resident', r.id)" class="text-slate-400 hover:text-red-600 p-1"><Trash2 :size="14"/></button>
                                    </div>
                                </div>
                                <h3 class="font-bold text-slate-800 text-lg mb-2 truncate" :title="r.nama">{{ r.nama }}</h3>
                                <div class="mb-4"><span :class="['text-[10px] px-2 py-1 rounded font-bold border flex w-fit items-center gap-1.5', r.tarif >= 50000 ? 'bg-purple-50 text-purple-700 border-purple-200' : 'bg-blue-50 text-blue-700 border-blue-200']"><Banknote :size="12" /> Tagihan: {{ formatRupiah(r.tarif || 20000) }}</span></div>
                                <button @click="openPaymentModal(r)" class="w-full py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-xs font-bold shadow-sm hover:bg-slate-50 hover:text-blue-600 hover:border-blue-300 transition flex items-center justify-center gap-2 group-hover:border-blue-200"><History :size="14"/> Cek & Bayar</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-if="Object.keys(groupedResidents).length === 0" class="text-center py-20 bg-white rounded-3xl border-2 border-dashed border-slate-200 mt-4"><div class="inline-flex bg-slate-50 p-4 rounded-full mb-3"><User :size="32" class="text-slate-300"/></div><p class="text-slate-500 font-medium">Tidak ada data warga ditemukan.</p></div>
            </div>

            <div v-if="activeTab === 'finance'" class="animate-in slide-in-from-bottom-4 duration-500">
                <div class="flex flex-col md:flex-row justify-between items-center bg-white p-4 rounded-2xl shadow-sm border border-slate-100 mb-6 gap-3">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">Laporan Keuangan</h3>
                        <div class="flex gap-2 mt-1">
                            <button @click="financeViewMode = 'monthly'" :class="['px-3 py-1 text-xs font-bold rounded-lg border transition', financeViewMode === 'monthly' ? 'bg-blue-600 text-white border-blue-600' : 'bg-slate-50 text-slate-500 border-slate-200']">Bulanan</button>
                            <button @click="financeViewMode = 'yearly'" :class="['px-3 py-1 text-xs font-bold rounded-lg border transition', financeViewMode === 'yearly' ? 'bg-blue-600 text-white border-blue-600' : 'bg-slate-50 text-slate-500 border-slate-200']">Tahunan</button>
                        </div>
                    </div>
                    <div class="flex gap-2 w-full md:w-auto">
                        <select v-if="financeViewMode === 'monthly'" v-model="selectedMonthKey" class="bg-slate-50 border-none text-sm font-bold text-slate-700 rounded-xl py-2 pl-3 pr-8 focus:ring-2 focus:ring-blue-500 cursor-pointer hover:bg-slate-100 transition"><option v-for="m in MONTHS" :key="m.key" :value="m.key">{{ m.label }}</option></select>
                        <select v-model="selectedFinancialYear" class="bg-slate-50 border-none text-sm font-bold text-slate-700 rounded-xl py-2 pl-3 pr-8 focus:ring-2 focus:ring-blue-500 cursor-pointer hover:bg-slate-100 transition"><option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option></select>
                        <button @click="downloadCSV" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-xl text-xs font-bold flex items-center gap-2 shadow-md active:scale-95 transition"><Download :size="16" /> <span class="hidden sm:inline">Excel</span></button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100 relative overflow-hidden group hover:shadow-lg transition-all">
                        <div class="flex items-center gap-3 mb-3"><div class="p-2.5 bg-blue-50 text-blue-600 rounded-xl"><Wallet :size="20"/></div><div><p class="text-slate-400 text-[10px] font-bold uppercase tracking-wider">Pemasukan ({{ selectedMonthKey.toUpperCase() }})</p><h3 class="text-xl font-extrabold text-slate-800 tracking-tight">{{ formatRupiah(financeViewMode === 'monthly' ? runningBalanceData.currentMonthInc : yearlyTotalIncome) }}</h3></div></div>
                        <button class="w-full mt-1 flex items-center justify-center gap-2 text-xs bg-blue-50 text-blue-600 px-3 py-2 rounded-xl font-bold hover:bg-blue-600 hover:text-white transition" @click="currentUser && openIncomeModal()"><Plus :size="14" /> Catat</button>
                    </div>

                    <div class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100 relative overflow-hidden group hover:shadow-lg transition-all">
                        <div class="flex items-center gap-3 mb-3"><div class="p-2.5 bg-red-50 text-red-600 rounded-xl"><TrendingDown :size="20"/></div><div><p class="text-slate-400 text-[10px] font-bold uppercase tracking-wider">Pengeluaran ({{ selectedMonthKey.toUpperCase() }})</p><h3 class="text-xl font-extrabold text-slate-800 tracking-tight">{{ formatRupiah(financeViewMode === 'monthly' ? runningBalanceData.currentMonthExp : yearlyTotalExpense) }}</h3></div></div>
                        <button class="w-full mt-1 flex items-center justify-center gap-2 text-xs bg-red-50 text-red-600 px-3 py-2 rounded-xl font-bold hover:bg-red-600 hover:text-white transition" @click="currentUser && openExpenseModal()"><Plus :size="14" /> Catat</button>
                    </div>

                    <div v-if="financeViewMode === 'monthly'" class="p-5 rounded-3xl shadow-sm border relative overflow-hidden transition-all bg-white border-slate-100">
                        <div class="flex items-center gap-3 mb-2">
                            <div :class="['p-2.5 rounded-xl', runningBalanceData.netMonth >= 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600']">
                                <Calculator :size="20"/>
                            </div>
                            <div>
                                <p class="text-slate-400 text-[10px] font-bold uppercase tracking-wider">Selisih Bulan Ini</p>
                                <h3 :class="['text-xl font-extrabold tracking-tight', runningBalanceData.netMonth >= 0 ? 'text-emerald-600' : 'text-red-600']">
                                    {{ runningBalanceData.netMonth >= 0 ? '+' : '' }}{{ formatRupiah(runningBalanceData.netMonth) }}
                                </h3>
                            </div>
                        </div>
                        <p class="text-[10px] text-slate-400 font-medium">Pemasukan dikurangi Pengeluaran bulan ini saja.</p>
                    </div>

                    <div class="bg-gradient-to-br from-slate-800 to-slate-900 p-5 rounded-3xl text-white shadow-xl shadow-slate-900/20 relative overflow-hidden flex flex-col justify-center">
                        <div class="absolute top-0 right-0 p-4 opacity-10"><Scale :size="80" /></div>
                        
                        <div v-if="financeViewMode === 'monthly'">
                            <p class="text-slate-300 text-[10px] font-bold uppercase tracking-wider mb-1">Total Saldo Akhir ({{ selectedMonthKey.toUpperCase() }})</p>
                            <h2 class="text-2xl font-extrabold tracking-tight mb-1">{{ formatRupiah(runningBalanceData.saldoAkhir) }}</h2>
                            <div class="text-[10px] text-slate-400 font-medium">Termasuk saldo dari bulan lalu.</div>
                        </div>

                        <div v-else>
                            <p class="text-slate-300 text-[10px] font-bold uppercase tracking-wider mb-1">Surplus / Defisit {{ selectedFinancialYear }}</p>
                            <h2 class="text-2xl font-extrabold tracking-tight">{{ formatRupiah(yearlySurplus) }}</h2>
                            <div class="mt-4 flex items-center gap-2 text-xs bg-white/20 px-3 py-2 rounded-lg inline-flex backdrop-blur-sm"><CheckCircle2 :size="12" /> Laporan Tahunan</div>
                        </div>
                    </div>
                </div>

                <div v-if="financeViewMode === 'monthly'" class="grid grid-cols-1 lg:grid-cols-2 gap-6 animate-in fade-in">
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden flex flex-col h-[400px]"><div class="p-4 border-b border-slate-50 bg-slate-50/50"><h4 class="text-sm font-bold text-slate-700 flex items-center gap-2"><TrendingUp :size="16" class="text-blue-500"/> Rincian Pemasukan ({{ selectedMonthKey.toUpperCase() }})</h4></div><div class="overflow-y-auto flex-1"><table class="w-full text-left text-sm"><thead class="sticky top-0 bg-white shadow-sm z-10"><tr class="text-slate-400 text-[10px] uppercase border-b border-slate-100"><th class="p-3 pl-4">Keterangan</th><th class="p-3 text-center">Info</th><th class="p-3 text-right pr-4">Jml</th><th v-if="currentUser" class="w-8"></th></tr></thead><tbody class="divide-y divide-slate-50"><tr v-for="r in residents.filter(x => getPaymentAmount(x, selectedMonthKey, selectedFinancialYear) > 0)" :key="'w-'+r.id"><td class="p-3 pl-4 font-medium text-slate-700">{{ r.nama }}</td><td class="p-3 text-center"><span class="bg-slate-100 px-2 py-0.5 rounded text-xs font-bold text-slate-500">{{ r.blok }}/{{ r.nomor }}</span></td><td class="p-3 text-right pr-4 font-mono font-bold text-slate-800">{{ formatRupiah(getPaymentAmount(r, selectedMonthKey, selectedFinancialYear)) }}</td><td v-if="currentUser"></td></tr><tr v-for="inc in currentMonthIncomesList" :key="'i-'+inc.id" class="bg-blue-50/30 group"><td class="p-3 pl-4 font-medium text-blue-700">{{ inc.description }} <span class="text-[10px] text-blue-400">(Manual)</span></td><td class="p-3 text-center text-xs text-slate-500">{{ formatDate(inc.date) }}</td><td class="p-3 text-right pr-4 font-mono font-bold text-blue-600">+{{ formatRupiah(inc.amount) }}</td><td v-if="currentUser" class="p-3 text-right"><div class="flex justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity"><button @click="openIncomeModal(inc)" class="text-yellow-500 hover:text-yellow-600"><Edit2 :size="14"/></button><button @click="deleteItem('income', inc.id)" class="text-red-500 hover:text-red-600"><Trash2 :size="14"/></button></div></td></tr></tbody></table></div></div>
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden flex flex-col h-[400px]"><div class="p-4 border-b border-slate-50 bg-slate-50/50 flex justify-between items-center"><h4 class="text-sm font-bold text-slate-700 flex items-center gap-2"><TrendingDown :size="16" class="text-red-500"/> Rincian Pengeluaran ({{ selectedMonthKey.toUpperCase() }})</h4></div><div class="overflow-y-auto flex-1"><table class="w-full text-left text-sm"><thead class="sticky top-0 bg-white shadow-sm z-10"><tr class="text-slate-400 text-[10px] uppercase border-b border-slate-100"><th class="p-3 pl-4">Keterangan</th><th class="p-3 text-center">Tgl</th><th class="p-3 text-right pr-4">Jml</th><th v-if="currentUser" class="p-3 w-[50px]"></th></tr></thead><tbody class="divide-y divide-slate-50"><tr v-for="ex in currentMonthExpensesList" :key="ex.id" class="group hover:bg-red-50/30"><td class="p-3 pl-4 font-medium text-slate-700">{{ ex.description }}</td><td class="p-3 text-center text-xs text-slate-500">{{ formatDate(ex.date) }}</td><td class="p-3 text-right pr-4 font-mono font-bold text-red-600">-{{ formatRupiah(ex.amount) }}</td><td v-if="currentUser" class="p-3 text-right"><div class="flex justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity"><button @click="openExpenseModal(ex)" class="text-yellow-500 hover:text-yellow-600"><Edit2 :size="14"/></button><button @click="deleteItem('expense', ex.id)" class="text-red-500 hover:text-red-600"><Trash2 :size="14"/></button></div></td></tr></tbody></table></div></div>
                </div>

                <div v-else class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden animate-in fade-in">
                    <div class="p-4 border-b border-slate-50 bg-slate-50/50"><h4 class="text-sm font-bold text-slate-700 flex items-center gap-2"><Calendar :size="16" class="text-blue-500"/> Rekapitulasi Tahunan ({{ selectedFinancialYear }})</h4></div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase font-bold"><tr><th class="p-4">Bulan</th><th class="p-4 text-right text-blue-600">Pemasukan</th><th class="p-4 text-right text-red-600">Pengeluaran</th><th class="p-4 text-right">Surplus / Defisit</th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="row in yearlyReportData" :key="row.monthName" class="hover:bg-slate-50/50 transition">
                                    <td class="p-4 font-bold text-slate-700">{{ row.monthName }}</td>
                                    <td class="p-4 text-right font-mono text-blue-600 font-bold">{{ formatRupiah(row.income) }}</td>
                                    <td class="p-4 text-right font-mono text-red-600 font-bold">{{ formatRupiah(row.expense) }}</td>
                                    <td class="p-4 text-right font-mono font-bold"><span :class="row.surplus >= 0 ? 'text-emerald-600 bg-emerald-50 px-2 py-1 rounded' : 'text-red-600 bg-red-50 px-2 py-1 rounded'">{{ row.surplus >= 0 ? '+' : '' }}{{ formatRupiah(row.surplus) }}</span></td>
                                </tr>
                                <tr class="bg-slate-100/50 font-black border-t-2 border-slate-200"><td class="p-4 text-slate-800 uppercase tracking-wider">TOTAL {{ selectedFinancialYear }}</td><td class="p-4 text-right text-blue-700">{{ formatRupiah(yearlyTotalIncome) }}</td><td class="p-4 text-right text-red-700">{{ formatRupiah(yearlyTotalExpense) }}</td><td class="p-4 text-right text-emerald-700">{{ formatRupiah(yearlySurplus) }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div v-if="activeTab === 'settings' && currentUser" class="animate-in fade-in zoom-in duration-300">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100"><div class="flex justify-between items-center mb-4"><h3 class="text-lg font-bold text-slate-800">Daftar Admin</h3><button @click="openAdminModal()" class="bg-slate-900 text-white px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-2 hover:bg-slate-800"><Plus :size="14"/> Tambah</button></div><ul class="space-y-3"><li v-for="u in adminList" :key="u.id" class="flex justify-between items-center p-3 bg-slate-50 rounded-xl border border-slate-100"><div><p class="font-bold text-sm text-slate-800">{{ u.name }}</p><p class="text-xs text-slate-500">{{ u.email }}</p></div><div class="flex gap-2"><button @click="openAdminModal(u)" class="text-yellow-600 hover:bg-yellow-50 p-2 rounded-lg transition"><Edit2 :size="16"/></button><button v-if="currentUser && u.id !== currentUser.id" @click="deleteItem('admin', u.id)" class="text-red-500 hover:bg-red-50 p-2 rounded-lg"><Trash2 :size="16"/></button></div></li></ul></div>
                    <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 flex flex-col justify-center items-center text-center"><div class="p-4 bg-yellow-50 rounded-full text-yellow-600 mb-3"><Lock :size="32"/></div><h3 class="text-lg font-bold text-slate-800">Ganti Password</h3><p class="text-xs text-slate-500 mb-4 px-4">Amankan akun anda.</p><Link href="/profile" class="bg-yellow-500 hover:bg-yellow-600 text-white px-6 py-2.5 rounded-xl font-bold text-sm shadow-lg shadow-yellow-500/30 transition">Buka Halaman Profil</Link></div>
                </div>
            </div>

            <div v-if="showResidentModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"><div class="bg-white rounded-2xl w-full max-w-sm p-6 shadow-2xl animate-in zoom-in-95 duration-200 relative max-h-[90vh] overflow-y-auto"><button @click="showResidentModal=false" class="absolute top-4 right-4 z-[70] p-2 bg-gray-100 rounded-full text-slate-500 hover:bg-gray-200 hover:text-red-600 border border-gray-200 transition-all shadow-sm active:scale-90"><X :size="20"/></button><h3 class="text-xl font-bold text-slate-800 mb-4">{{ isEditing ? 'Edit Data' : 'Tambah Warga' }}</h3><div class="space-y-4"><div><label class="text-[10px] font-bold text-slate-500 uppercase ml-1">Nama</label><input v-model="formResident.nama" class="w-full p-3 bg-slate-50 border-none rounded-xl focus:ring-2 focus:ring-blue-500 transition font-bold text-sm text-slate-800" placeholder="Nama Kepala Keluarga"></div><div><label class="text-[10px] font-bold text-slate-500 uppercase ml-1">Tipe Tarif</label><div class="grid grid-cols-2 gap-2 mt-1"><button type="button" @click="formResident.tarif = 20000" :class="['p-3 rounded-xl text-sm font-bold border transition', formResident.tarif == 20000 ? 'bg-blue-50 border-blue-500 text-blue-700 ring-1 ring-blue-500' : 'bg-slate-50 border-transparent text-slate-500 hover:bg-slate-100']">20 Ribu</button><button type="button" @click="formResident.tarif = 50000" :class="['p-3 rounded-xl text-sm font-bold border transition', formResident.tarif == 50000 ? 'bg-purple-50 border-purple-500 text-purple-700 ring-1 ring-purple-500' : 'bg-slate-50 border-transparent text-slate-500 hover:bg-slate-100']">50 Ribu</button></div></div><div class="grid grid-cols-2 gap-3"><div><label class="text-[10px] font-bold text-slate-500 uppercase ml-1">Blok</label><input v-model="formResident.blok" class="w-full p-3 bg-slate-50 border-none rounded-xl focus:ring-2 focus:ring-blue-500 transition font-bold text-center text-sm" placeholder="A"></div><div><label class="text-[10px] font-bold text-slate-500 uppercase ml-1">Nomor</label><input v-model="formResident.nomor" class="w-full p-3 bg-slate-50 border-none rounded-xl focus:ring-2 focus:ring-blue-500 transition font-bold text-center text-sm" placeholder="01"></div></div><div><label class="text-[10px] font-bold text-slate-500 uppercase ml-1">Telepon</label><input v-model="formResident.telepon" class="w-full p-3 bg-slate-50 border-none rounded-xl focus:ring-2 focus:ring-blue-500 transition font-bold text-sm" placeholder="0812..."></div><button @click="submitResident" class="w-full py-3 bg-slate-900 text-white font-bold rounded-xl hover:bg-slate-800 hover:shadow-lg transition-all active:scale-95 text-sm">Simpan Data</button></div></div></div>
            <div v-if="showIncomeModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"><div class="bg-white rounded-2xl w-full max-w-sm p-6 shadow-2xl animate-in zoom-in-95 duration-200 relative"><button @click="showIncomeModal=false" class="absolute top-4 right-4 z-[70] p-2 bg-gray-100 rounded-full text-slate-500 hover:bg-gray-200 hover:text-red-600 border border-gray-200 transition-all shadow-sm active:scale-90"><X :size="20"/></button><h3 class="text-xl font-bold text-slate-800 mb-4 flex items-center gap-2"><TrendingUp class="text-blue-500"/> {{ isIncomeEditing ? 'Edit Pemasukan' : 'Catat Pemasukan' }}</h3><div class="space-y-4"><div><label class="text-[10px] font-bold text-slate-500 uppercase ml-1">Keterangan</label><input v-model="formIncome.description" class="w-full p-3 bg-slate-50 border-none rounded-xl focus:ring-2 focus:ring-blue-500 transition font-bold text-sm text-slate-800" placeholder="Contoh: Donasi"></div><div><label class="text-[10px] font-bold text-slate-500 uppercase ml-1">Jumlah (Rp)</label><input v-model="formIncome.amount" type="number" class="w-full p-3 bg-slate-50 border-none rounded-xl focus:ring-2 focus:ring-blue-500 transition font-bold text-sm text-slate-800" placeholder="500000"></div><div><label class="text-[10px] font-bold text-slate-500 uppercase ml-1">Tanggal</label><input v-model="formIncome.date" type="date" class="w-full p-3 bg-slate-50 border-none rounded-xl focus:ring-2 focus:ring-blue-500 transition font-bold text-sm text-slate-800"></div><button @click="submitIncome" class="w-full py-3 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 hover:shadow-lg transition-all active:scale-95 text-sm">Simpan Pemasukan</button></div></div></div>
            <div v-if="showExpenseModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"><div class="bg-white rounded-2xl w-full max-w-sm p-6 shadow-2xl animate-in zoom-in-95 duration-200 relative"><button @click="showExpenseModal=false" class="absolute top-4 right-4 z-[70] p-2 bg-gray-100 rounded-full text-slate-500 hover:bg-gray-200 hover:text-red-600 border border-gray-200 transition-all shadow-sm active:scale-90"><X :size="20"/></button><h3 class="text-xl font-bold text-slate-800 mb-4 flex items-center gap-2"><TrendingDown class="text-red-500"/> {{ isExpenseEditing ? 'Edit Pengeluaran' : 'Catat Pengeluaran' }}</h3><div class="space-y-4"><div><label class="text-[10px] font-bold text-slate-500 uppercase ml-1">Keterangan</label><input v-model="formExpense.description" class="w-full p-3 bg-slate-50 border-none rounded-xl focus:ring-2 focus:ring-blue-500 transition font-bold text-sm text-slate-800" placeholder="Contoh: Beli Kaporit"></div><div><label class="text-[10px] font-bold text-slate-500 uppercase ml-1">Jumlah (Rp)</label><input v-model="formExpense.amount" type="number" class="w-full p-3 bg-slate-50 border-none rounded-xl focus:ring-2 focus:ring-blue-500 transition font-bold text-sm text-slate-800" placeholder="150000"></div><div><label class="text-[10px] font-bold text-slate-500 uppercase ml-1">Tanggal</label><input v-model="formExpense.date" type="date" class="w-full p-3 bg-slate-50 border-none rounded-xl focus:ring-2 focus:ring-blue-500 transition font-bold text-sm text-slate-800"></div><button @click="submitExpense" class="w-full py-3 bg-slate-900 text-white font-bold rounded-xl hover:bg-slate-800 hover:shadow-lg transition-all active:scale-95 text-sm">Simpan Pengeluaran</button></div></div></div>
            <div v-if="showAdminModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"><div class="bg-white rounded-2xl w-full max-w-sm p-6 shadow-2xl animate-in zoom-in-95 duration-200 relative max-h-[90vh] overflow-y-auto"><button @click="showAdminModal=false" class="absolute top-4 right-4 z-[70] p-2 bg-gray-100 rounded-full text-slate-500 hover:bg-gray-200 hover:text-red-600 border border-gray-200 transition-all shadow-sm active:scale-90"><X :size="20"/></button><h3 class="text-xl font-bold text-slate-800 mb-4">{{ isAdminEditing ? 'Edit Admin' : 'Tambah Admin' }}</h3><div class="space-y-3"><div><label class="text-[10px] font-bold text-slate-500 uppercase ml-1">Nama</label><input v-model="formAdmin.name" class="w-full p-3 bg-slate-50 border-none rounded-xl focus:ring-2 focus:ring-blue-500 font-bold text-sm" placeholder="Nama Admin"></div><div><label class="text-[10px] font-bold text-slate-500 uppercase ml-1">Email</label><input v-model="formAdmin.email" type="email" class="w-full p-3 bg-slate-50 border-none rounded-xl focus:ring-2 focus:ring-blue-500 font-bold text-sm" placeholder="email@contoh.com"></div><div class="border-t border-slate-100 pt-2 mt-2"><p class="text-[10px] text-orange-500 mb-2 italic" v-if="isAdminEditing">*Kosongkan jika tidak ingin ganti password.</p><div><label class="text-[10px] font-bold text-slate-500 uppercase ml-1">Password</label><input v-model="formAdmin.password" type="password" class="w-full p-3 bg-slate-50 border-none rounded-xl focus:ring-2 focus:ring-blue-500 font-bold text-sm" placeholder="******"></div><div class="mt-3"><label class="text-[10px] font-bold text-slate-500 uppercase ml-1">Konfirmasi Password</label><input v-model="formAdmin.password_confirmation" type="password" class="w-full p-3 bg-slate-50 border-none rounded-xl focus:ring-2 focus:ring-blue-500 font-bold text-sm" placeholder="******"></div></div><button @click="submitAdmin" class="w-full py-3 bg-slate-900 text-white font-bold rounded-xl hover:bg-slate-800 hover:shadow-lg transition-all active:scale-95 text-sm mt-2">{{ isAdminEditing ? 'Simpan Perubahan' : 'Tambah Admin' }}</button></div></div></div>
            
            <div v-if="showPaymentModal && selectedResident" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm print:bg-white print:p-0 print:absolute print:inset-0">
                <div class="bg-white rounded-2xl w-full max-w-lg p-6 shadow-2xl animate-in zoom-in-95 relative overflow-hidden print:shadow-none print:w-full print:max-w-none print:rounded-none">
                    <button @click="showPaymentModal=false" class="absolute top-4 right-4 z-[70] p-2 bg-gray-100 rounded-full text-slate-500 hover:bg-gray-200 hover:text-red-600 border border-gray-200 transition-all shadow-sm active:scale-90 print:hidden"><X :size="20"/></button>
                    
                    <div class="flex items-center gap-4 mb-4 border-b border-slate-100 pb-4">
                        <div class="h-14 w-14 bg-gradient-to-tr from-blue-600 to-indigo-500 rounded-2xl flex items-center justify-center shadow-lg text-white font-bold text-xl">{{ selectedResident.blok }}</div>
                        <div><h3 class="text-xl font-bold text-slate-800 leading-tight">{{ selectedResident.nama }}</h3><p class="text-slate-500 font-medium text-sm mt-0.5">Rumah No. {{ selectedResident.nomor }}</p></div>
                    </div>

                    <div class="mb-4 print:hidden">
                        <label class="text-[10px] font-bold text-slate-500 uppercase ml-1 mb-1 block">Tahun Pembayaran</label>
                        <div class="flex gap-2 overflow-x-auto pb-2">
                            <button v-for="y in availableYears" :key="y" @click="paymentYear = y" :class="['px-4 py-2 rounded-xl text-sm font-bold border transition', paymentYear === y ? 'bg-slate-800 text-white border-slate-800 shadow-md' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50']">{{ y }}</button>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 relative z-10 max-h-[320px] overflow-y-auto pr-2 custom-scrollbar p-1">
                        <div v-for="(m, index) in MONTHS" :key="m.key" class="relative group">
                            
                            <button 
                                @click="showAmountOptions === m.key ? showAmountOptions = null : showAmountOptions = m.key" 
                                class="w-full text-left p-3 rounded-xl border-2 transition-all duration-200 flex flex-col justify-between h-20 relative overflow-visible" 
                                :class="[getPaymentAmount(selectedResident, m.key, paymentYear) ? 'bg-emerald-50 border-emerald-500 shadow-sm' : 'bg-white border-slate-200 border-dashed hover:border-blue-400 hover:bg-blue-50']"
                            >
                                <div class="flex justify-between items-start z-10 relative">
                                    <span class="text-[10px] font-bold uppercase tracking-wider" :class="getPaymentAmount(selectedResident, m.key, paymentYear) ? 'text-emerald-700' : 'text-slate-400'">{{ m.label }}</span>
                                    <component :is="getPaymentAmount(selectedResident, m.key, paymentYear) ? CheckCircle2 : Circle" :size="16" :class="getPaymentAmount(selectedResident, m.key, paymentYear) ? 'text-emerald-600' : 'text-slate-300'"/>
                                </div>
                                <div class="z-10 relative mt-auto">
                                    <p v-if="getPaymentAmount(selectedResident, m.key, paymentYear)" class="text-lg font-bold text-emerald-700">{{ ((getPaymentAmount(selectedResident, m.key, paymentYear)) / 1000) }}k</p>
                                    <p v-else class="text-[10px] font-medium text-slate-400">Tagihan</p>
                                </div>
                                <div v-if="getPaymentAmount(selectedResident, m.key, paymentYear)" class="absolute -bottom-2 -right-2 text-emerald-100 opacity-50"><CheckCircle2 :size="48"/></div>
                            </button>

                            <div v-if="showAmountOptions === m.key && currentUser" 
                                class="absolute left-1/2 -translate-x-1/2 w-40 bg-white rounded-xl shadow-xl border border-slate-200 p-2.5 z-[100] flex flex-col gap-2 animate-in zoom-in-95"
                                :class="[
                                    index < 6 ? 'top-full mt-2 origin-top' : 'bottom-full mb-2 origin-bottom'
                                ]"
                            >
                                <button v-if="getPaymentAmount(selectedResident, m.key, paymentYear)" @click="togglePayment(m.key); showAmountOptions=null" class="w-full py-2 bg-red-50 text-red-600 text-xs font-bold rounded-lg hover:bg-red-100 flex items-center justify-center gap-2">
                                    <Trash2 :size="14"/> Batalkan
                                </button>

                                <div v-else class="flex flex-col gap-2">
                                    <p class="text-[10px] text-center text-slate-400 font-bold uppercase tracking-wider">Pilih Nominal</p>
                                    <button @click="togglePayment(m.key, parseInt(selectedResident.tarif || 20000)); showAmountOptions=null" class="w-full py-2 bg-blue-50 text-blue-700 text-xs font-bold rounded-lg hover:bg-blue-100 border border-blue-100">
                                        {{ formatRupiah(selectedResident.tarif || 20000) }} (Asli)
                                    </button>
                                    <button @click="togglePayment(m.key, (selectedResident.tarif == 50000 ? 20000 : 50000)); showAmountOptions=null" class="w-full py-2 bg-slate-50 text-slate-600 text-xs font-bold rounded-lg hover:bg-slate-100 border border-slate-200">
                                        {{ formatRupiah(selectedResident.tarif == 50000 ? 20000 : 50000) }} (Alt)
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex gap-3 relative z-10 border-t border-slate-100 pt-4 print:hidden">
                        <button @click="showQrisModal = true" class="flex-1 bg-blue-600 text-white px-4 py-3 rounded-xl text-xs font-bold flex items-center justify-center gap-2 hover:bg-blue-700 shadow-md transition-all active:scale-95"><QrCode :size="18"/> Bayar Pakai QRIS</button>
                        <button @click="printCard" class="flex-1 bg-slate-900 text-white px-4 py-3 rounded-xl text-xs font-bold flex items-center justify-center gap-2 hover:bg-slate-700 shadow-md transition-all active:scale-95"><Printer :size="18"/> Cetak Kartu</button>
                    </div>
                </div>
            </div>

            <div v-if="showQrisModal" class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-black/80 backdrop-blur-md transition-all print:hidden" @click.self="showQrisModal=false"><div class="bg-white rounded-2xl w-full max-w-sm p-6 shadow-2xl animate-in zoom-in-95 relative flex flex-col items-center"><button @click="showQrisModal=false" class="absolute top-3 right-3 z-50 p-2 bg-slate-100 rounded-full text-slate-500 hover:bg-red-100 hover:text-red-500 transition-all"><X :size="20"/></button><h3 class="text-xl font-bold text-slate-800 mb-2">Scan QRIS</h3><p class="text-xs text-slate-500 mb-4 text-center">Scan kode di bawah menggunakan e-wallet (GoPay, Dana, OVO, ShopeePay) atau m-banking.</p><div class="bg-white p-2 rounded-xl border-2 border-slate-900 mb-4"><img src="/images/qris.jpg" alt="QRIS Code" class="w-64 h-64 object-contain"></div><p class="text-xs font-bold text-slate-800 bg-yellow-100 px-3 py-1.5 rounded-lg border border-yellow-200">Wajib konfirmasi ke Admin setelah transfer!</p></div></div>


            <div v-if="currentUser" class="mt-12 bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden shadow-slate-200">
    <div class="p-6 border-b border-slate-50 bg-red-50/20 flex flex-col sm:flex-row justify-between items-center gap-4">
        <div class="flex items-center gap-4">
            <div class="h-12 w-12 flex items-center justify-center rounded-2xl bg-red-100 text-red-600 shadow-sm shadow-red-100">
                <AlertTriangle :size="24"/>
            </div>
            <div>
                <h3 class="text-xl font-black text-slate-800 uppercase tracking-tighter">Daftar Tunggakan Warga</h3>
                <p class="text-xs text-slate-400 font-bold uppercase tracking-widest">Saring warga belum lunas berdasarkan bulan</p>
            </div>
        </div>
        
        <div class="flex items-center gap-2 bg-white p-1.5 rounded-2xl border border-red-100 shadow-sm shadow-red-50">
            <select v-model="debtReportMonthKey" class="bg-transparent border-none text-xs font-black uppercase p-2 outline-none cursor-pointer text-red-600 focus:ring-0">
                <option v-for="m in MONTHS" :key="m.key" :value="m.key">{{ m.label }}</option>
            </select>
            <div class="px-4 py-2 bg-red-600 text-white rounded-xl font-black text-xs shadow-lg shadow-red-100">
                {{ overdueResidents.length }} Unit
            </div>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead class="bg-slate-50 text-slate-400 text-[10px] font-black uppercase tracking-widest border-b">
                <tr>
                    <th class="p-5 pl-8">Identitas Warga</th>
                    <th class="p-5">Unit</th>
                    <th class="p-5">Telepon</th>
                    <th class="p-5">Tarif</th>
                    <th class="p-5 text-center">Aksi Cepat</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <tr v-for="r in overdueResidents" :key="r.id" class="hover:bg-slate-50 transition-all duration-200">
                    <td class="p-5 pl-8 font-black text-slate-700 text-sm">{{ r.nama }}</td>
                    <td class="p-5">
                        <span class="bg-slate-100 px-3 py-1 rounded-xl text-[11px] font-black text-slate-600 border border-slate-200 uppercase tracking-tighter">
                            {{ r.blok }}{{ r.nomor }}
                        </span>
                    </td>
                    <td class="p-5 text-xs font-bold text-slate-400 tracking-tighter">{{ r.telepon || '-' }}</td>
                    <td class="p-5 font-black text-red-600 text-sm">{{ formatRupiah(r.tarif) }}</td>
                    <td class="p-5 text-center">
                        <div class="flex justify-center gap-2">
                            <button v-if="r.telepon" @click="sendWhatsappDebt(r)" class="flex items-center gap-2 bg-green-100 text-green-600 px-4 py-2 rounded-xl text-[10px] font-black uppercase hover:bg-green-600 hover:text-white transition-all shadow-sm">
                                <MessageCircle :size="14"/> Tagih WA
                            </button>
                            <button @click="openPaymentModal(r)" class="bg-slate-900 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase shadow-lg shadow-slate-200 hover:bg-blue-600 transition-all active:scale-95">
                                Bayar
                            </button>
                        </div>
                    </td>
                </tr>
                <tr v-if="overdueResidents.length === 0">
                    <td colspan="5" class="p-16 text-center text-slate-400 font-bold italic tracking-widest">
                        ✨ Semua warga sudah lunas untuk periode {{ debtReportMonthKey.toUpperCase() }}.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
        </main>
    </div>

    <div id="print-area" v-if="selectedResident" class="hidden print:block print:absolute print:top-0 print:left-0 print:w-full print:h-screen print:bg-white print:z-[9999] p-8 text-black">
        <div class="border-4 border-slate-800 p-6 max-w-3xl mx-auto h-[95vh] flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between border-b-2 border-slate-800 pb-4 mb-6">
                    <div class="flex items-center gap-3">
                        <div class="p-3 border-2 border-slate-800 rounded-full"><Waves :size="32" class="text-black"/></div>
                        <div><h1 class="text-2xl font-black uppercase tracking-widest">KARTU IURAN AIR</h1><h2 class="text-sm font-bold text-slate-600 tracking-wider">NUANSA MANISI REGENCY</h2></div>
                    </div>
                    <div class="text-right"><p class="text-xs font-bold text-slate-500">TAHUN</p><p class="text-3xl font-black">{{ paymentYear }}</p></div>
                </div>
                <div class="grid grid-cols-2 gap-4 mb-8 text-sm">
                    <div class="border p-2"><p class="text-xs text-slate-500 uppercase font-bold">Nama Warga</p><p class="font-bold text-lg">{{ selectedResident.nama }}</p></div>
                    <div class="border p-2"><p class="text-xs text-slate-500 uppercase font-bold">Lokasi Rumah</p><p class="font-bold text-lg">Blok {{ selectedResident.blok }} / No. {{ selectedResident.nomor }}</p></div>
                </div>
                <div class="mb-4">
                    <p class="font-bold mb-2 text-sm uppercase border-b border-black inline-block">Riwayat Pembayaran ({{ paymentYear }}):</p>
                    <div class="grid grid-cols-3 gap-0 border border-slate-800">
                        <div v-for="m in MONTHS" :key="m.key" class="border border-slate-800 p-3 flex justify-between items-center h-16">
                            <span class="font-bold uppercase text-xs">{{ m.label }}</span>
                            <div v-if="getPaymentAmount(selectedResident, m.key, paymentYear)" class="text-right"><p class="text-[10px] font-bold bg-black text-white px-1.5 py-0.5 rounded">LUNAS</p><p class="text-xs font-mono font-bold mt-0.5">{{ formatRupiah(getPaymentAmount(selectedResident, m.key, paymentYear)) }}</p></div>
                            <div v-else class="text-center w-full opacity-20">-</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-4">
                <div class="border-2 border-dashed border-slate-400 bg-slate-50 p-4 text-center mb-8"><p class="text-sm font-bold uppercase tracking-widest text-slate-800">★ BUKTI PEMBAYARAN INI ALAH SAH ★</p><p class="text-[10px] text-slate-500 mt-1">Terima kasih telah membayar iuran tepat waktu.</p></div>
                <div class="flex justify-between items-end"><div class="text-xs text-slate-400"><p>Dicetak Otomatis Oleh Sistem</p><p>Tanggal: {{ new Date().toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}</p></div><div class="text-center w-40"><p class="text-xs font-bold mb-16">Mengetahui, <br>Admin Pengelola</p><p class="border-t border-black pt-1 font-bold text-sm">{{ currentUser?.name || 'Admin' }}</p></div></div>
            </div>
        </div>
    </div>
    

  </div>
</template>

<style>
@media print {
    nav, main, .fixed.inset-0, .min-h-screen { display: none !important; }
    body { background: white !important; height: auto !important; overflow: visible !important; }
    #print-area { display: block !important; position: static !important; width: 100% !important; height: auto !important; visibility: visible !important; overflow: visible !important; background: white !important; z-index: 99999 !important; }
    #print-area * { visibility: visible !important; display: block; }
    #print-area .grid { display: grid !important; }
    #print-area .flex { display: flex !important; }
    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
}
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>
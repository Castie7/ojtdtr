<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import { useRouter } from 'vue-router'; 
import { ClockIcon, CheckCircleIcon, ExclamationTriangleIcon, PrinterIcon, CalendarIcon, PencilIcon, ArrowUpTrayIcon } from '@heroicons/vue/24/solid';
import { useToast } from '../composables/useToast';

const router = useRouter();
const { addToast } = useToast();

// State
const user = ref({ name: 'Student', hours_rendered: 0, total_hours_required: 600, percentage: 0, total_lates: 0 });
const todayLog = ref<any>(null);
const logs = ref<any[]>([]);
const loading = ref(false);
const currentTime = ref(new Date().toLocaleTimeString());
const userId = localStorage.getItem('user_id');

// Manual Clock In State
const showManualModal = ref(false);
const manualDate = ref('');
const manualTime = ref('');
const manualTimeOut = ref(''); // New State but we need to check if it's defined in the script section properly

// Edit Log State
const showEditModal = ref(false);
const editData = ref({ id: 0, date: '', time_in: '', time_out: '', remarks: '' });

// Import CSV State
const showImportModal = ref(false);
const importFile = ref<File | null>(null);

// Computed Styles
const strokeDashoffset = computed(() => {
  const circumference = 2 * Math.PI * 45; 
  return circumference - ((user.value.percentage || 0) / 100) * circumference;
});

const groupedLogs = computed(() => {
    const groups: Record<string, { date: string, am: any, pm: any, totalDuration: string }> = {};
    
    logs.value.forEach(log => {
        if (!groups[log.date]) {
            groups[log.date] = { date: log.date, am: null, pm: null, totalDuration: '0h 0m' };
        }
        
        const hour = new Date(log.time_in).getHours();
        if (hour < 12) {
            groups[log.date].am = log;
        } else {
            groups[log.date].pm = log;
        }
    });

    // Calculate totals
    Object.values(groups).forEach(g => {
        let totalMinutes = 0;
        
        const addDuration = (log: any) => {
            if (log && log.time_out) {
                const diff = (new Date(log.time_out).getTime() - new Date(log.time_in).getTime()) / 1000 / 60; // minutes
                totalMinutes += diff;
            }
        };

        addDuration(g.am);
        addDuration(g.pm);

        const h = Math.floor(totalMinutes / 60);
        const m = Math.round(totalMinutes % 60);
        g.totalDuration = `${h}h ${m}m`;
    });

    return Object.values(groups).sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime());
});

const formatTime = (isoString: string) => {
    if (!isoString) return '';
    return new Date(isoString).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

setInterval(() => { currentTime.value = new Date().toLocaleTimeString(); }, 1000);

// --- Actions ---

const fetchData = async () => {
  if (!userId) {
    router.push('/');
    return;
  }
  try {
    const [statsRes, logsRes] = await Promise.all([
        axios.get(`http://localhost:8080/api/dtr/stats/${userId}`),
        axios.get(`http://localhost:8080/api/dtr/logs/${userId}`)
    ]);
    user.value = statsRes.data;
    logs.value = logsRes.data;

    const today = new Date().toISOString().split('T')[0];
    todayLog.value = logs.value.find((l: any) => l.date === today && l.time_out === null);
  } catch (e) {
    console.error("Failed to fetch data", e);
  }
};

const handleClockIn = async () => {
    await performAction('clockIn', { userId });
};

const handleClockOut = async () => {
    // Removed prompt as requested. Just clock out.
    await performAction('clockOut', { userId, remarks: 'Daily entry' });
};

const performAction = async (endpoint: string, payload: any) => {
    loading.value = true;
    try {
        await axios.post(`http://localhost:8080/api/dtr/${endpoint}`, payload);
        await fetchData();
        addToast("Success!", "success");
    } catch (e: any) {
        addToast(e.response?.data?.messages?.error || 'Action failed', 'error');
    }
    loading.value = false;
};

// Manual In
const handleManualClockIn = async () => {
  if (!manualDate.value || !manualTime.value) return addToast('Please select date and time in', 'error');
  
  const timeIn = `${manualDate.value} ${manualTime.value}:00`;
  let timeOut = null;
  
  if (manualTimeOut.value) {
      timeOut = `${manualDate.value} ${manualTimeOut.value}:00`;
  }

  await performAction('clockIn', { userId, date: manualDate.value, timeIn, timeOut });
  showManualModal.value = false;
  
  // Reset fields
  manualDate.value = '';
  manualTime.value = '';
  manualTimeOut.value = '';
};

// Edit Log
const openEditModal = (log: any) => {
    editData.value = {
        id: log.id,
        date: log.date,
        time_in: log.time_in, // Keep full datetime for simplicity or split if needed
        time_out: log.time_out,
        remarks: log.remarks
    };
    showEditModal.value = true;
};

const handleEditLog = async () => {
    await performAction('editLog', { userId, ...editData.value });
    showEditModal.value = false;
};

// Import CSV
const handleFileUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files) importFile.value = target.files[0];
};

const handleImportCsv = async () => {
    if (!importFile.value) return addToast("Please select a file", 'info');
    
    const formData = new FormData();
    formData.append('file', importFile.value);
    formData.append('userId', userId!);

    loading.value = true;
    try {
        const res = await axios.post('http://localhost:8080/api/dtr/importCsv', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        addToast(res.data.message, 'success');
        showImportModal.value = false;
        fetchData();
    } catch (e: any) {
        addToast("Import failed", 'error');
    }
    loading.value = false;
};

const downloadTemplate = () => {
    const csvContent = "Date (YYYY-MM-DD),AM In (HH:mm),AM Out (HH:mm),PM In (HH:mm),PM Out (HH:mm),Remarks\n2024-01-01,08:00,12:00,13:00,17:00,Sample AM/PM Entry";
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', 'dtr_template.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};

const goToPrint = () => {
  router.push('/print-dtr');
};

// Quick Time Out
const showTimeoutModal = ref(false);
const timeoutData = ref<any>({});
const timeoutValue = ref('');

const openTimeoutModal = (log: any) => {
    timeoutData.value = log;
    timeoutValue.value = '';
    showTimeoutModal.value = true;
};

const handleTimeoutConfirm = async () => {
    if (!timeoutValue.value) return addToast("Please select a time", 'info');
    
    // Construct full datetime
    const fullTimeOut = `${timeoutData.value.date} ${timeoutValue.value}:00`;
    
    await performAction('editLog', { 
        userId, 
        id: timeoutData.value.id,
        date: timeoutData.value.date,
        time_in: timeoutData.value.time_in,
        time_out: fullTimeOut,
        remarks: timeoutData.value.remarks 
    });
    
    showTimeoutModal.value = false;
};

onMounted(fetchData);
</script>

<template>
  <div class="min-h-screen bg-gray-900 text-white font-sans selection:bg-indigo-500 selection:text-white relative">
    
    <!-- Background Blobs -->
    <div class="fixed top-0 left-0 w-full h-full overflow-hidden -z-10 pointer-events-none">
      <div class="absolute top-[-10%] left-[-10%] w-96 h-96 bg-purple-600 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-blob"></div>
      <div class="absolute top-[-10%] right-[-10%] w-96 h-96 bg-indigo-600 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-blob animation-delay-2000"></div>
    </div>

    <div class="container mx-auto px-4 py-12 max-w-5xl">
      
      <header class="flex flex-col md:flex-row justify-between items-center mb-12 gap-6">
        <div>
          <h1 class="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-indigo-400 to-purple-400">
            OJT Tracker
          </h1>
          <p class="text-gray-400">Welcome back, {{ user.name }}</p>
        </div>
        <div class="text-right flex flex-col items-end">
          <p class="text-2xl font-mono">{{ currentTime }}</p>
          <p class="text-sm text-gray-500 uppercase tracking-widest">{{ new Date().toDateString() }}</p>
          
          <div class="flex flex-wrap gap-2 justify-end mt-4">
             <button @click="showImportModal = true" class="px-3 py-1.5 rounded-lg bg-gray-800 hover:bg-gray-700 text-xs text-indigo-400 flex items-center gap-1 transition border border-white/5">
                <ArrowUpTrayIcon class="w-4 h-4" /> Import CSV
            </button>
            <button @click="showManualModal = true" class="px-3 py-1.5 rounded-lg bg-gray-800 hover:bg-gray-700 text-xs text-indigo-400 flex items-center gap-1 transition border border-white/5">
                <CalendarIcon class="w-4 h-4" /> Manual In
            </button>
            <button @click="goToPrint" class="px-3 py-1.5 rounded-lg bg-gray-800 hover:bg-gray-700 text-xs text-indigo-400 flex items-center gap-1 transition border border-white/5">
                <PrinterIcon class="w-4 h-4" /> Print DTR
            </button>
          </div>
        </div>
      </header>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-12">
        <!-- Progress Circle -->
        <div class="bg-white/10 backdrop-blur-lg border border-white/20 rounded-3xl p-8 flex flex-col items-center justify-center shadow-2xl relative overflow-hidden group min-h-[300px]">
          <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/10 to-purple-500/10 opacity-0 group-hover:opacity-100 transition duration-500"></div>
          
          <div class="relative w-48 h-48">
            <svg class="w-full h-full transform -rotate-90" viewBox="0 0 100 100">
              <circle cx="50" cy="50" r="45" fill="none" stroke="#374151" stroke-width="8" />
              <circle cx="50" cy="50" r="45" fill="none" stroke="url(#gradient)" stroke-width="8" 
                      stroke-dasharray="283" :stroke-dashoffset="strokeDashoffset" 
                      class="transition-all duration-1000 ease-out" />
              <defs>
                <linearGradient id="gradient" x1="0%" y1="0%" x2="100%" y2="0%">
                  <stop offset="0%" stop-color="#818cf8" />
                  <stop offset="100%" stop-color="#c084fc" />
                </linearGradient>
              </defs>
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
              <span class="text-4xl font-bold">{{ Math.round(user.percentage || 0) }}%</span>
              <span class="text-xs text-gray-400 uppercase tracking-wide">Completed</span>
            </div>
          </div>
          
          <div class="mt-6 text-center">
            <p class="text-gray-300 text-sm">Hours Rendered</p>
            <p class="text-2xl font-semibold">{{ Number(user.hours_rendered).toFixed(2) }} <span class="text-gray-500 text-base">/ {{ user.total_hours_required }}</span></p>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-col gap-6">
          <button 
            @click="todayLog ? handleClockOut() : handleClockIn()"
            :disabled="loading"
            class="grow rounded-3xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 transition-all shadow-lg hover:shadow-indigo-500/25 flex items-center justify-center overflow-hidden disabled:opacity-50 relative group min-h-[160px]"
          >
            <div class="absolute inset-0 bg-white/20 group-hover:translate-x-full transition-transform duration-700 ease-in-out skew-x-12 -translate-x-full"></div>
            <div class="flex items-center gap-3">
              <ClockIcon class="w-10 h-10 text-white" />
              <span class="text-3xl font-bold tracking-wider">
                {{ loading ? 'PROCESSING...' : (todayLog ? 'CLOCK OUT' : 'CLOCK IN') }}
              </span>
            </div>
          </button>

          <div class="grid grid-cols-2 gap-4 h-full">
            <div class="bg-white/5 border border-white/10 rounded-2xl p-6 flex flex-col justify-between">
              <CheckCircleIcon class="w-8 h-8 text-emerald-400" />
              <div>
                <p class="text-3xl font-bold">{{ logs.length }}</p>
                <p class="text-xs text-gray-400">Total Logs</p>
              </div>
            </div>
            <div class="bg-white/5 border border-white/10 rounded-2xl p-6 flex flex-col justify-between">
              <ExclamationTriangleIcon class="w-8 h-8 text-amber-400" />
              <div>
                <p class="text-3xl font-bold">{{ user.total_lates || 0 }}</p>
                <p class="text-xs text-gray-400">Late Arrivals</p>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Logs Table -->
      <div>
        <h3 class="text-xl font-semibold mb-4 flex items-center gap-2">
          <span class="w-2 h-8 bg-indigo-500 rounded-full"></span>
          Recent Logs
        </h3>
        <div class="bg-white/5 rounded-2xl p-6 border border-white/10 overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="text-gray-400 border-b border-gray-700 text-xs uppercase tracking-wider">
                <th class="pb-3 pl-2">Date</th>
                <th class="pb-3 text-center border-l border-gray-700/50">AM In</th>
                <th class="pb-3 text-center border-r border-gray-700/50">AM Out</th>
                <th class="pb-3 text-center">PM In</th>
                <th class="pb-3 text-center border-r border-gray-700/50">PM Out</th>
                <th class="pb-3 text-center w-24">Hours</th>
                <th class="pb-3 pl-4">Remarks</th>
                <th class="pb-3 text-right pr-2">Action</th>
              </tr>
            </thead>
            <tbody class="text-gray-300">
              <tr v-for="group in groupedLogs" :key="group.date" class="border-b border-gray-800 hover:bg-white/5 transition group">
                <td class="py-4 pl-2 text-sm font-medium">{{ group.date }}</td>
                
                <!-- AM Shift -->
                <td class="py-4 text-center text-sm border-l border-gray-700/50">
                    <span v-if="group.am" :class="{'text-emerald-400': group.am.status === 'PRESENT', 'text-amber-400': group.am.status === 'LATE'}">{{ formatTime(group.am.time_in) }}</span>
                    <span v-else class="text-gray-600">-</span>
                </td>
                <td class="py-4 text-center text-sm border-r border-gray-700/50">
                    <span v-if="group.am && group.am.time_out">{{ formatTime(group.am.time_out) }}</span>
                    <span v-else-if="group.am" class="text-amber-400 animate-pulse">Active</span>
                    <span v-else class="text-gray-600">-</span>
                </td>

                <!-- PM Shift -->
                <td class="py-4 text-center text-sm">
                    <span v-if="group.pm" :class="{'text-emerald-400': group.pm.status === 'PRESENT', 'text-amber-400': group.pm.status === 'LATE'}">{{ formatTime(group.pm.time_in) }}</span>
                    <span v-else class="text-gray-600">-</span>
                </td>
                <td class="py-4 text-center text-sm border-r border-gray-700/50">
                    <span v-if="group.pm && group.pm.time_out">{{ formatTime(group.pm.time_out) }}</span>
                    <span v-else-if="group.pm" class="text-amber-400 animate-pulse">Active</span>
                    <span v-else class="text-gray-600">-</span>
                </td>

                <!-- Daily Total -->
                <td class="py-4 text-center text-sm font-mono text-indigo-300">
                    {{ group.totalDuration }}
                </td>

                <td class="py-4 pl-4 text-sm text-gray-400 truncate max-w-xs">
                    <div v-if="group.am && group.am.remarks" class="text-xs">AM: {{ group.am.remarks }}</div>
                    <div v-if="group.pm && group.pm.remarks" class="text-xs">PM: {{ group.pm.remarks }}</div>
                </td>
                
                <td class="py-4 text-right pr-2 flex justify-end gap-1">
                    <!-- AM Actions -->
                    <div v-if="group.am" class="flex gap-1">
                         <button v-if="!group.am.time_out" @click="openTimeoutModal(group.am)" class="text-amber-400 hover:text-amber-300 transition p-1 rounded-md hover:bg-white/10" title="Time Out AM">
                            <span class="text-[10px] font-bold mr-1">AM</span><ArrowUpTrayIcon class="w-3 h-3 inline rotate-90" />
                        </button>
                        <button @click="openEditModal(group.am)" class="text-gray-500 hover:text-indigo-400 transition p-1 rounded-md hover:bg-white/10" title="Edit AM">
                            <span class="text-[10px] font-bold mr-1">AM</span><PencilIcon class="w-3 h-3 inline" />
                        </button>
                    </div>

                    <!-- PM Actions -->
                     <div v-if="group.pm" class="flex gap-1 ml-2 border-l border-gray-700 pl-2">
                         <button v-if="!group.pm.time_out" @click="openTimeoutModal(group.pm)" class="text-amber-400 hover:text-amber-300 transition p-1 rounded-md hover:bg-white/10" title="Time Out PM">
                            <span class="text-[10px] font-bold mr-1">PM</span><ArrowUpTrayIcon class="w-3 h-3 inline rotate-90" />
                        </button>
                        <button @click="openEditModal(group.pm)" class="text-gray-500 hover:text-indigo-400 transition p-1 rounded-md hover:bg-white/10" title="Edit PM">
                             <span class="text-[10px] font-bold mr-1">PM</span><PencilIcon class="w-3 h-3 inline" />
                        </button>
                    </div>
                </td>
              </tr>
              <tr v-if="groupedLogs.length === 0">
                <td colspan="7" class="py-8 text-center text-gray-500">No logs found. Clock in to start!</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <!-- Modal Layout -->
    <div v-if="showManualModal || showEditModal || showImportModal || showTimeoutModal" class="fixed inset-0 bg-black/80 flex items-center justify-center z-50 backdrop-blur-sm p-4">
        
        <!-- Manual Clock In Modal -->
        <div v-if="showManualModal" class="bg-gray-800 p-8 rounded-2xl w-full max-w-md border border-white/10 shadow-2xl">
            <h2 class="text-2xl font-bold mb-6 text-indigo-400">Manual Clock In</h2>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Date</label>
                    <input v-model="manualDate" type="date" class="w-full bg-gray-900 border border-gray-700 rounded-lg p-3 text-white focus:outline-none focus:border-indigo-500" />
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Time In</label>
                    <input v-model="manualTime" type="time" class="w-full bg-gray-900 border border-gray-700 rounded-lg p-3 text-white focus:outline-none focus:border-indigo-500" />
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Time Out (Optional)</label>
                    <input v-model="manualTimeOut" type="time" class="w-full bg-gray-900 border border-gray-700 rounded-lg p-3 text-white focus:outline-none focus:border-indigo-500" />
                </div>
            </div>
            <div class="flex gap-4 mt-8">
                <button @click="showManualModal = false" class="flex-1 py-3 rounded-xl bg-gray-700 hover:bg-gray-600 transition text-gray-300">Cancel</button>
                <button @click="handleManualClockIn" class="flex-1 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 transition font-bold shadow-lg shadow-indigo-500/20">Confirm</button>
            </div>
        </div>

        <!-- Edit Log Modal -->
        <div v-if="showEditModal" class="bg-gray-800 p-8 rounded-2xl w-full max-w-md border border-white/10 shadow-2xl">
            <h2 class="text-2xl font-bold mb-6 text-indigo-400">Edit Log</h2>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Date</label>
                    <input v-model="editData.date" type="date" class="w-full bg-gray-900 border border-gray-700 rounded-lg p-3 text-white" />
                </div>
                <!-- Note: Time inputs here require stripping seconds for proper display in some browsers, but keeping simple for now. -->
                <!-- Ideally, convert ISO datetime string to HH:MM for input type="time" or use datetime-local -->
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Time In (YYYY-MM-DD HH:MM:SS)</label>
                    <input v-model="editData.time_in" type="text" class="w-full bg-gray-900 border border-gray-700 rounded-lg p-3 text-white" placeholder="2024-01-01 08:00:00" />
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Time Out (YYYY-MM-DD HH:MM:SS)</label>
                    <input v-model="editData.time_out" type="text" class="w-full bg-gray-900 border border-gray-700 rounded-lg p-3 text-white" placeholder="2024-01-01 17:00:00" />
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Remarks</label>
                    <input v-model="editData.remarks" type="text" class="w-full bg-gray-900 border border-gray-700 rounded-lg p-3 text-white" />
                </div>
            </div>
             <div class="flex gap-4 mt-8">
                <button @click="showEditModal = false" class="flex-1 py-3 rounded-xl bg-gray-700 hover:bg-gray-600 transition text-gray-300">Cancel</button>
                <button @click="handleEditLog" class="flex-1 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 transition font-bold shadow-lg shadow-emerald-500/20">Save Changes</button>
            </div>
        </div>

        <!-- Import CSV Modal -->
         <div v-if="showImportModal" class="bg-gray-800 p-8 rounded-2xl w-full max-w-md border border-white/10 shadow-2xl">
            <h2 class="text-2xl font-bold mb-6 text-indigo-400">Import CSV</h2>
            
            <div class="bg-gray-900 p-4 rounded-lg mb-4 text-sm text-gray-400 border border-gray-700">
                <p class="font-bold text-white mb-2">Format:</p>
                <code>Date, AM In, AM Out, PM In, PM Out, Remarks</code>
                <p class="mt-2 text-xs">Example: 2024-01-01, 08:00, 12:00, 13:00, 17:00, Task</p>
            </div>

            <div class="space-y-4">
                <input type="file" accept=".csv" @change="handleFileUpload" class="block w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-500" />
            </div>
            
            <div class="flex gap-4 mt-8">
                <button @click="downloadTemplate" class="flex-1 py-3 rounded-xl bg-gray-700 hover:bg-gray-600 transition text-gray-300 border border-white/5">Download Template</button>
                <button @click="showImportModal = false" class="flex-1 py-3 rounded-xl bg-gray-700 hover:bg-gray-600 transition text-gray-300">Cancel</button>
                <button @click="handleImportCsv" class="flex-1 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 transition font-bold shadow-lg shadow-indigo-500/20">Upload</button>
            </div>
        </div>

        <!-- Time Out Modal -->
        <div v-if="showTimeoutModal" class="bg-gray-800 p-8 rounded-2xl w-full max-w-md border border-white/10 shadow-2xl">
            <h2 class="text-2xl font-bold mb-6 text-amber-400">Time Out</h2>
            <p class="text-gray-400 text-sm mb-4">Set time out for {{ timeoutData.date }}</p>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Time Out</label>
                    <input v-model="timeoutValue" type="time" class="w-full bg-gray-900 border border-gray-700 rounded-lg p-3 text-white focus:outline-none focus:border-indigo-500" />
                </div>
            </div>
            <div class="flex gap-4 mt-8">
                <button @click="showTimeoutModal = false" class="flex-1 py-3 rounded-xl bg-gray-700 hover:bg-gray-600 transition text-gray-300">Cancel</button>
                <button @click="handleTimeoutConfirm" class="flex-1 py-3 rounded-xl bg-amber-600 hover:bg-amber-500 transition font-bold shadow-lg shadow-amber-500/20">Confirm</button>
            </div>
        </div>

    </div>
  </div>
</template>


<style scoped>
/* Reuse existing animation styles */
@keyframes blob {
  0% { transform: translate(0px, 0px) scale(1); }
  33% { transform: translate(30px, -50px) scale(1.1); }
  66% { transform: translate(-20px, 20px) scale(0.9); }
  100% { transform: translate(0px, 0px) scale(1); }
}
.animate-blob {
  animation: blob 7s infinite;
}
.animation-delay-2000 { animation-delay: 2s; }
</style>
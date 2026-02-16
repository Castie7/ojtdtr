<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import { useRouter } from 'vue-router';

// State
const user = ref<any>({});
const logs = ref<any[]>([]);
const router = useRouter();
const userId = localStorage.getItem('user_id');

const fetchData = async () => {
    if (!userId) {
        router.push('/');
        return;
    }
    try {
        const [userRes, logsRes] = await Promise.all([
            axios.get(`http://localhost:8080/api/dtr/stats/${userId}`),
            axios.get(`http://localhost:8080/api/dtr/logs/${userId}`)
        ]);
        user.value = userRes.data;
        logs.value = logsRes.data;
        
        // Auto-print after a short delay to ensure rendering
        setTimeout(() => window.print(), 500);
    } catch (e) {
        alert("Failed to load data for printing.");
    }
};

const groupedLogs = computed(() => {
    const groups: Record<string, { date: string, am: any, pm: any }> = {};
    
    // Sort logs by date ascending for DTR
    const sortedLogs = [...logs.value].sort((a, b) => new Date(a.date).getTime() - new Date(b.date).getTime());

    sortedLogs.forEach(log => {
        if (!groups[log.date]) {
            groups[log.date] = { date: log.date, am: null, pm: null };
        }
        
        const hour = new Date(log.time_in).getHours();
        if (hour < 12) {
            groups[log.date].am = log;
        } else {
            groups[log.date].pm = log;
        }
    });

    return Object.values(groups);
});

const formatTime = (isoString: string) => {
    if (!isoString) return '---';
    return new Date(isoString).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

onMounted(fetchData);
</script>

<template>
    <div class="p-8 bg-white text-black font-serif print-container">
        
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold uppercase tracking-widest border-b-2 border-black pb-2 inline-block">Daily Time Record</h1>
            <div class="mt-4 flex justify-between items-end border-b border-black pb-2">
                <div>
                    <p class="text-sm text-gray-600">Name of Trainee:</p>
                    <p class="text-xl font-bold uppercase">{{ user.name }}</p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-600">Student ID:</p>
                    <p class="text-lg font-bold">{{ user.student_id || '---' }}</p>
                </div>
            </div>
            <div class="mt-2 flex justify-between items-end text-sm">
                <p>Required Hours: <strong>{{ user.total_hours_required }}</strong></p>
                <p>Hours Rendered: <strong>{{ user.hours_rendered }}</strong></p>
                <p>Remaining: <strong>{{ user.remaining }}</strong></p>
            </div>
        </div>

        <table class="w-full border-collapse border border-black text-sm">
            <thead>
                <tr class="bg-gray-200">
                    <th rowspan="2" class="border border-black p-2 text-left w-24">Date</th>
                    <th colspan="2" class="border border-black p-2 text-center">AM</th>
                    <th colspan="2" class="border border-black p-2 text-center">PM</th>
                    <th rowspan="2" class="border border-black p-2 text-left">Accomplishment / Remarks</th>
                </tr>
                <tr class="bg-gray-200">
                    <th class="border border-black p-1 text-center w-16">In</th>
                    <th class="border border-black p-1 text-center w-16">Out</th>
                    <th class="border border-black p-1 text-center w-16">In</th>
                    <th class="border border-black p-1 text-center w-16">Out</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="group in groupedLogs" :key="group.date">
                    <td class="border border-black p-2">{{ group.date }}</td>
                    
                    <!-- AM -->
                    <td class="border border-black p-2 text-center">
                        {{ group.am ? formatTime(group.am.time_in) : '---' }}
                    </td>
                    <td class="border border-black p-2 text-center">
                        {{ group.am ? formatTime(group.am.time_out) : '---' }}
                    </td>

                    <!-- PM -->
                    <td class="border border-black p-2 text-center">
                        {{ group.pm ? formatTime(group.pm.time_in) : '---' }}
                    </td>
                    <td class="border border-black p-2 text-center">
                        {{ group.pm ? formatTime(group.pm.time_out) : '---' }}
                    </td>

                    <!-- Remarks (Combine or show preferred) -->
                    <td class="border border-black p-2">
                        <div v-if="group.am && group.am.remarks"><span class="font-bold text-xs mr-1">AM:</span>{{ group.am.remarks }}</div>
                        <div v-if="group.pm && group.pm.remarks"><span class="font-bold text-xs mr-1">PM:</span>{{ group.pm.remarks }}</div>
                    </td>
                </tr>
                <tr v-if="groupedLogs.length === 0">
                    <td colspan="6" class="border border-black p-4 text-center italic">No records found.</td>
                </tr>
            </tbody>
        </table>

        <div class="mt-12 flex justify-between">
            <div class="text-center w-1/3">
                <div class="border-b border-black mb-2 h-8"></div>
                <p class="text-xs uppercase">Trainee Signature</p>
            </div>
            <div class="text-center w-1/3">
                <div class="border-b border-black mb-2 h-8"></div>
                <p class="text-xs uppercase">Supervisor Signature</p>
            </div>
        </div>

        <div class="mt-8 text-center print:hidden">
            <button @click="router.push('/dashboard')" class="bg-gray-800 text-white px-4 py-2 rounded hover:bg-gray-700">Back to Dashboard</button>
        </div>

    </div>
</template>

<style>
@media print {
    .print:hidden { display: none; }
    body { background: white; }
}
.print-container {
    max-width: 800px;
    margin: 0 auto;
}
</style>

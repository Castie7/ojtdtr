<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios'; // Or import api from '../lib/axios' if you set that up
import { UserIcon, LockClosedIcon, ArrowRightIcon } from '@heroicons/vue/24/solid';

const router = useRouter();

// State
const studentId = ref('');
const password = ref(''); // In a real app, use a password field
const isLoading = ref(false);
const errorMessage = ref('');

// Login Logic
const handleLogin = async () => {
  // Reset states
  isLoading.value = true;
  errorMessage.value = '';

  try {
    // 1. Call your CI4 API
    // Note: Adjust URL if you aren't using the axios instance we made earlier
    const response = await axios.post('http://localhost:8080/api/login', {
      student_id: studentId.value,
      password: password.value
    });

    // 2. Save the "Token" (Simulated for now)
    // In a real JWT app, you store the token. Here we store the user ID.
    localStorage.setItem('token', response.data.token || 'logged-in'); 
    localStorage.setItem('user_id', response.data.user_id);

    // 3. Animate & Redirect
    setTimeout(() => {
      router.push('/dashboard');
    }, 500); // Small delay for effect

  } catch (error: any) {
    console.error(error);
    errorMessage.value = error.response?.data?.message || 'Invalid Student ID or Connection Error';
  } finally {
    isLoading.value = false;
  }
};
</script>

<template>
  <div class="min-h-screen flex items-center justify-center relative overflow-hidden bg-gray-900">
    
    <div class="absolute inset-0 w-full h-full">
      <div class="absolute top-[-10%] left-[-10%] w-96 h-96 bg-purple-600/30 rounded-full mix-blend-screen filter blur-3xl animate-blob"></div>
      <div class="absolute top-[20%] right-[-10%] w-96 h-96 bg-indigo-600/30 rounded-full mix-blend-screen filter blur-3xl animate-blob animation-delay-2000"></div>
      <div class="absolute bottom-[-20%] left-[20%] w-96 h-96 bg-blue-600/30 rounded-full mix-blend-screen filter blur-3xl animate-blob animation-delay-4000"></div>
    </div>

    <div class="relative z-10 w-full max-w-md p-8 bg-white/10 backdrop-blur-xl border border-white/20 rounded-3xl shadow-2xl transform transition-all hover:scale-[1.01]">
      
      <div class="text-center mb-10">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gradient-to-tr from-indigo-500 to-purple-500 mb-4 shadow-lg shadow-indigo-500/30">
          <UserIcon class="w-8 h-8 text-white" />
        </div>
        <h2 class="text-3xl font-bold text-white tracking-tight">Welcome Back</h2>
        <p class="text-gray-400 mt-2 text-sm">Enter your Student ID to access your DTR</p>
      </div>

      <form @submit.prevent="handleLogin" class="space-y-6">
        
        <div class="space-y-2">
          <label class="text-xs font-semibold text-gray-300 uppercase tracking-wider ml-1">Student ID</label>
          <div class="relative group">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <UserIcon class="h-5 w-5 text-gray-400 group-focus-within:text-indigo-400 transition-colors" />
            </div>
            <input 
              v-model="studentId" 
              type="text" 
              required
              class="w-full pl-10 pr-4 py-3 bg-gray-800/50 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
              placeholder="e.g., 2023-1001"
            />
          </div>
        </div>

        <div class="space-y-2">
          <label class="text-xs font-semibold text-gray-300 uppercase tracking-wider ml-1">Password</label>
          <div class="relative group">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <LockClosedIcon class="h-5 w-5 text-gray-400 group-focus-within:text-indigo-400 transition-colors" />
            </div>
            <input 
              v-model="password" 
              type="password" 
              required
              class="w-full pl-10 pr-4 py-3 bg-gray-800/50 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
              placeholder="••••••••"
            />
          </div>
        </div>

        <div v-if="errorMessage" class="p-3 rounded-lg bg-red-500/20 border border-red-500/50 text-red-200 text-sm text-center animate-pulse">
          {{ errorMessage }}
        </div>

        <button 
          type="submit" 
          :disabled="isLoading"
          class="w-full group relative flex justify-center py-3 px-4 border border-transparent rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 font-medium transition-all shadow-lg hover:shadow-indigo-500/50 disabled:opacity-70 disabled:cursor-not-allowed overflow-hidden"
        >
          <div class="absolute inset-0 w-full h-full bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover:animate-shimmer"></div>
          
          <span v-if="isLoading" class="flex items-center gap-2">
            <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Verifying...
          </span>
          <span v-else class="flex items-center gap-2">
            Sign In <ArrowRightIcon class="w-4 h-4 group-hover:translate-x-1 transition-transform" />
          </span>
        </button>

      </form>
    </div>

    <div class="absolute bottom-6 text-center w-full text-gray-500 text-xs">
      OJT DTR System &copy; 2024
    </div>

  </div>
</template>

<style scoped>
/* Shimmer Effect for Button */
@keyframes shimmer {
  100% { transform: translateX(100%); }
}
.group-hover\:animate-shimmer:hover {
  animation: shimmer 1s infinite;
}

/* Background Blob Animation */
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
.animation-delay-4000 { animation-delay: 4s; }
</style>
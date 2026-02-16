<script setup lang="ts">
import { RouterView } from 'vue-router'
import ToastNotification from './components/ToastNotification.vue';
// We don't need to import specific components like HelloWorld anymore.
// The Router will handle loading 'LoginView' or 'OjtDashboard' automatically.
</script>

<template>
  <div class="min-h-screen bg-gray-900 text-white font-sans antialiased">
    <ToastNotification />
    
    <router-view v-slot="{ Component }">
      <transition name="page-slide" mode="out-in">
        <component :is="Component" />
      </transition>
    </router-view>
  </div>
</template>

<style>
/* Global Transition Styles 
  This creates the smooth "drift" effect when switching between Login and Dashboard
*/

.page-slide-enter-active,
.page-slide-leave-active {
  transition: opacity 0.4s ease, transform 0.4s ease;
}

.page-slide-enter-from {
  opacity: 0;
  transform: translateY(15px); /* Enters from slightly below */
}

.page-slide-leave-to {
  opacity: 0;
  transform: translateY(-15px); /* Exits slightly above */
}

/* Optional: Scrollbar styling for a premium feel */
::-webkit-scrollbar {
  width: 8px;
}
::-webkit-scrollbar-track {
  background: #111827; 
}
::-webkit-scrollbar-thumb {
  background: #374151; 
  border-radius: 4px;
}
::-webkit-scrollbar-thumb:hover {
  background: #4b5563; 
}
</style>
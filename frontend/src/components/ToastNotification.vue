<script setup lang="ts">
import { useToast } from '../composables/useToast';
import { XMarkIcon } from '@heroicons/vue/24/solid';

const { toasts, removeToast } = useToast();
</script>

<template>
  <div class="fixed top-4 right-4 z-50 flex flex-col gap-2 w-full max-w-xs pointer-events-none">
    <TransitionGroup 
      enter-active-class="transition ease-out duration-300"
      enter-from-class="transform translate-x-full opacity-0"
      enter-to-class="translate-x-0 opacity-100"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0 scale-90"
    >
      <div 
        v-for="toast in toasts" 
        :key="toast.id" 
        class="pointer-events-auto flex items-center justify-between p-4 rounded-lg shadow-lg border border-white/10 backdrop-blur-md"
        :class="{
          'bg-emerald-600/90 text-white': toast.type === 'success',
          'bg-red-600/90 text-white': toast.type === 'error',
          'bg-blue-600/90 text-white': toast.type === 'info'
        }"
      >
        <div class="text-sm font-medium">{{ toast.message }}</div>
        <button @click="removeToast(toast.id)" class="ml-4 opacity-70 hover:opacity-100 transition">
          <XMarkIcon class="w-4 h-4" />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

// src/main.ts
import { createApp } from 'vue'
import './style.css' // or index.css
import App from './App.vue'
import router from './router' // <--- Make sure this is imported

createApp(App)
    .use(router) // <--- And this is used
    .mount('#app')
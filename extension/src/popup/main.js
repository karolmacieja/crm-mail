import { createApp } from 'vue'
import { createPinia } from 'pinia'
import '@/styles/tailwind.css'
import PopupApp from './PopupApp.vue'

createApp(PopupApp).use(createPinia()).mount('#app')

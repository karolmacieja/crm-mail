import { createApp } from 'vue'
import { createPinia } from 'pinia'
import '@/shared/styles/tailwind.css'
import { initLocale } from '@/shared/lib/i18n.js'
import AdminApp from './AdminApp.vue'
import { createAdminRouter } from './router.js'

const pinia = createPinia()
const app = createApp(AdminApp).use(pinia)
app.use(createAdminRouter(pinia))
app.config.errorHandler = (error, _instance, info) => console.error(`[GastroFlowx admin] ${info}:`, error)

initLocale().finally(() => app.mount('#app'))

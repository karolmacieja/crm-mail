<template>
  <div class="flex items-center gap-4">
    <Avatar :name="client.displayName" :email="client.email" size="lg" gradient />
    <div class="min-w-0">
      <h2 class="truncate text-2xl font-bold text-gray-900">{{ client.displayName }}</h2>
      <p class="truncate text-sm text-gray-500">
        {{ contact?.company || (contact ? (contact.is_client ? t('crm.client.individual') : t('crm.client.contactOnly')) : client.email) }}
      </p>
      <div v-if="contact" class="mt-2 flex flex-wrap items-center gap-2">
        <CategoryBadge :category="contact.category" />
        <StatusBadge :status="contact.status" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import Avatar from '@/shared/components/Avatar.vue'
import CategoryBadge from '@/shared/components/CategoryBadge.vue'
import StatusBadge from '@/crm/components/StatusBadge.vue'
import { t } from '@/shared/lib/i18n.js'
import { useClientStore } from '@/crm/stores/client.js'

const client = useClientStore()
const contact = computed(() => client.contact)
</script>

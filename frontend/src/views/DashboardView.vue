<template>
  <div>
    <h2 class="text-2xl font-semibold mb-6">Dashboard</h2>

    <!-- Stats Tiles -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <div
        v-for="stat in statsData"
        :key="stat.label"
        @click="stat.onClick"
        class="bg-white rounded-lg shadow tile_stats_count"
      >
        <div class="flex items-center justify-between">
          <div>
            <div class="count_top">
              <i :class="stat.icon"></i>
              {{ stat.label }}
            </div>
            <div class="count" :class="stat.colorClass">{{ stat.value }}</div>
          </div>
        </div>
      </div>
    </div>

    <!-- System Info -->
    <div class="bg-white rounded-lg shadow p-6">
      <h3 class="text-lg font-semibold mb-4">System Resources</h3>

      <div class="space-y-6">
        <div v-for="resource in systemResources" :key="resource.label">
          <div class="flex justify-between mb-2">
            <span class="text-sm font-medium">{{ resource.label }}</span>
            <span class="text-sm text-gray-600">
              {{ resource.count }} / {{ resource.total }}
            </span>
          </div>
          <div class="w-full bg-gray-200 rounded-full h-2.5">
            <div
              class="bg-[#26B99A] h-2.5 rounded-full transition-all"
              :style="{ width: resource.pr + '%' }"
            ></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useMainStore } from '@/stores/main'
import { storeToRefs } from 'pinia'

const router = useRouter()
const store = useMainStore()
const { stats, systemInfo } = storeToRefs(store)

const statsData = computed(() => [
  {
    label: 'Online Streams',
    value: stats.value.online,
    icon: 'fa fa-user',
    colorClass: 'text-green-600',
    onClick: () => router.push('/streams?filter=running')
  },
  {
    label: 'Offline Streams',
    value: stats.value.offline,
    icon: 'fa fa-clock-o',
    colorClass: 'text-gray-600',
    onClick: () => router.push('/streams?filter=stopped')
  },
  {
    label: 'Total Streams',
    value: stats.value.all,
    icon: 'fa fa-user',
    colorClass: 'text-blue-600',
    onClick: () => router.push('/streams')
  }
])

const systemResources = computed(() => [
  {
    label: 'DISK SPACE',
    pr: systemInfo.value.space.pr,
    count: systemInfo.value.space.count + ' MB',
    total: systemInfo.value.space.total + ' MB'
  },
  {
    label: 'CPU USAGE',
    pr: systemInfo.value.cpu.pr,
    count: systemInfo.value.cpu.pr.toFixed(1) + '%',
    total: '100%'
  },
  {
    label: 'MEMORY',
    pr: systemInfo.value.mem.pr,
    count: (systemInfo.value.mem.count / 1024).toFixed(1) + ' MB',
    total: (systemInfo.value.mem.total / 1024).toFixed(1) + ' MB'
  }
])

onMounted(() => {
  store.fetchDashboardData()
  // Refresh every 5 seconds
  setInterval(() => {
    store.fetchDashboardData()
  }, 5000)
})
</script>

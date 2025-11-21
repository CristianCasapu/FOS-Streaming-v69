<template>
  <div class="min-h-screen bg-gray-100">
    <!-- Top Navigation -->
    <nav class="bg-[#2A3F54] text-white shadow-lg">
      <div class="px-4 py-3">
        <div class="flex items-center justify-between">
          <div class="flex items-center space-x-4">
            <h1 class="text-xl font-semibold">FOS-Streaming v70</h1>
          </div>
          <div class="flex items-center space-x-4">
            <span class="text-sm">{{ user?.username || 'Admin' }}</span>
            <button @click="handleLogout" class="px-3 py-1 bg-red-600 hover:bg-red-700 rounded text-sm">
              Logout
            </button>
          </div>
        </div>
      </div>
    </nav>

    <div class="flex">
      <!-- Sidebar -->
      <aside class="w-64 bg-[#2A3F54] min-h-[calc(100vh-56px)] text-gray-300">
        <div class="p-4">
          <h3 class="text-xs font-semibold uppercase text-gray-400 mb-3">Main Navigation</h3>
          <nav class="space-y-1">
            <router-link
              v-for="item in menuItems"
              :key="item.path"
              :to="item.path"
              class="flex items-center px-3 py-2 rounded hover:bg-[#1f2f3e] transition-colors"
              active-class="bg-[#1f2f3e] text-white"
            >
              <i :class="item.icon" class="mr-3"></i>
              {{ item.label }}
            </router-link>
          </nav>
        </div>
      </aside>

      <!-- Main Content -->
      <main class="flex-1 p-6">
        <router-view />
      </main>
    </div>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'
import { useMainStore } from '@/stores/main'
import { storeToRefs } from 'pinia'

const router = useRouter()
const store = useMainStore()
const { user } = storeToRefs(store)

const menuItems = [
  { path: '/', label: 'Dashboard', icon: 'fa fa-dashboard' },
  { path: '/streams', label: 'Streams', icon: 'fa fa-video-camera' },
  { path: '/users', label: 'Users', icon: 'fa fa-users' },
  { path: '/settings', label: 'Settings', icon: 'fa fa-cog' }
]

const handleLogout = async () => {
  await store.logout()
  router.push('/login')
}
</script>

import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

export const useMainStore = defineStore('main', () => {
  // State
  const user = ref(null)
  const stats = ref({
    all: 0,
    online: 0,
    offline: 0
  })
  const systemInfo = ref({
    space: { pr: 0, count: 0, total: 0 },
    cpu: { pr: 0, count: 0, total: 0 },
    mem: { pr: 0, count: 0, total: 0 }
  })

  // Getters
  const isAuthenticated = computed(() => !!user.value)

  // Actions
  async function login(username, password) {
    try {
      const response = await axios.post('/api/login', { username, password })
      user.value = response.data.user
      localStorage.setItem('auth_token', response.data.token)
      return { success: true }
    } catch (error) {
      return { success: false, error: error.response?.data?.message || 'Login failed' }
    }
  }

  async function logout() {
    user.value = null
    localStorage.removeItem('auth_token')
  }

  async function fetchDashboardData() {
    try {
      const response = await axios.get('/api/dashboard')
      stats.value = {
        all: response.data.all,
        online: response.data.online,
        offline: response.data.offline
      }
      systemInfo.value = {
        space: response.data.space,
        cpu: response.data.cpu,
        mem: response.data.mem
      }
    } catch (error) {
      console.error('Failed to fetch dashboard data:', error)
    }
  }

  return {
    // State
    user,
    stats,
    systemInfo,
    // Getters
    isAuthenticated,
    // Actions
    login,
    logout,
    fetchDashboardData
  }
})

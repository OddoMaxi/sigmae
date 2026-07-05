import { create } from 'zustand'
import { persist } from 'zustand/middleware'
import api from '@/lib/api'

interface User {
  id: number
  name: string
  email: string
  role: 'admin_mae' | 'gestionnaire' | 'superviseur' | 'agent_ambassade'
  ambassade_id: number | null
  ambassade?: { id: number; nom: string; pays: string }
  is_active: boolean
}

interface AuthState {
  user: User | null
  token: string | null
  isAuthenticated: boolean
  login: (email: string, password: string) => Promise<void>
  logout: () => Promise<void>
  fetchMe: () => Promise<void>
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set, get) => ({
      user: null,
      token: null,
      isAuthenticated: false,

      login: async (email, password) => {
        const { data } = await api.post('/auth/login', { email, password })
        localStorage.setItem('sgp_token', data.token)
        set({ token: data.token, user: data.user, isAuthenticated: true })
      },

      logout: async () => {
        try { await api.post('/auth/logout') } catch {}
        localStorage.removeItem('sgp_token')
        set({ user: null, token: null, isAuthenticated: false })
      },

      fetchMe: async () => {
        const { data } = await api.get('/auth/me')
        set({ user: data, isAuthenticated: true })
      },
    }),
    { name: 'sgp-auth', partialize: (s) => ({ token: s.token, user: s.user, isAuthenticated: s.isAuthenticated }) }
  )
)

export const hasRole = (user: User | null, ...roles: string[]) =>
  user ? roles.includes(user.role) : false

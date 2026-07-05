import { create } from 'zustand'
import { persist, createJSONStorage } from 'zustand/middleware'
import api from '@/lib/api'

export interface User {
  id: number
  name: string
  email: string
  role: string
  role_label?: string
  ambassade_id: number | null
  ambassade?: { id: number; nom: string; code?: string; ville?: string }
  is_active: boolean
  permissions: string[]
  permissions_by_module: Record<string, string[]>
}

interface AuthState {
  user: User | null
  token: string | null
  isAuthenticated: boolean
  login: (email: string, password: string) => Promise<void>
  logout: () => Promise<void>
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set) => ({
      user: null,
      token: null,
      isAuthenticated: false,

      login: async (email, password) => {
        const { data } = await api.post('/auth/login', { email, password })
        api.defaults.headers.common['Authorization'] = `Bearer ${data.token}`
        set({ token: data.token, user: data.user, isAuthenticated: true })
      },

      logout: async () => {
        try { await api.post('/auth/logout') } catch {}
        delete api.defaults.headers.common['Authorization']
        set({ user: null, token: null, isAuthenticated: false })
      },
    }),
    {
      name: 'sgp-auth',
      storage: createJSONStorage(() => localStorage),
      partialize: (s) => ({ token: s.token, user: s.user, isAuthenticated: s.isAuthenticated }),
      onRehydrateStorage: () => (state) => {
        if (state?.token) {
          api.defaults.headers.common['Authorization'] = `Bearer ${state.token}`
        }
      },
    }
  )
)

/** Vérifie si l'utilisateur possède une permission donnée. */
export const hasPermission = (user: User | null, permission: string): boolean =>
  user?.permissions?.includes(permission) ?? false

/** Vérifie si l'utilisateur possède au moins une des permissions listées. */
export const hasAnyPermission = (user: User | null, ...permissions: string[]): boolean =>
  permissions.some((p) => hasPermission(user, p))

/** Rétrocompatibilité — vérifie le champ role (slug) directement. */
export const hasRole = (user: User | null, ...roles: string[]) =>
  user ? roles.includes(user.role) : false

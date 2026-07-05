'use client'

import { useEffect, useState } from 'react'
import { useAuthStore } from '@/stores/authStore'
import Sidebar from '@/components/layout/Sidebar'
import { Shield } from 'lucide-react'

export default function DashboardLayout({ children }: { children: React.ReactNode }) {
  const { isAuthenticated } = useAuthStore()
  const [hydrated, setHydrated] = useState(false)

  useEffect(() => {
    if (useAuthStore.persist.hasHydrated()) {
      setHydrated(true)
      return
    }
    const unsub = useAuthStore.persist.onFinishHydration(() => setHydrated(true))
    return unsub
  }, [])

  useEffect(() => {
    if (hydrated && !isAuthenticated) {
      window.location.replace('/login')
    }
  }, [hydrated, isAuthenticated])

  if (!hydrated || !isAuthenticated) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center space-y-3">
          <Shield className="mx-auto text-[#1a5276]" size={36} />
          <div className="animate-spin rounded-full h-7 w-7 border-2 border-[#1a5276] border-t-transparent mx-auto" />
        </div>
      </div>
    )
  }

  return (
    <div className="flex h-full min-h-screen">
      <Sidebar />
      <main className="flex-1 overflow-y-auto">
        <div className="max-w-7xl mx-auto p-8">
          {children}
        </div>
      </main>
    </div>
  )
}

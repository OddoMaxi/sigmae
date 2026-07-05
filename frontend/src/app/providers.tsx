'use client'

import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { Toaster } from 'react-hot-toast'
import { useState } from 'react'

export default function Providers({ children }: { children: React.ReactNode }) {
  const [queryClient] = useState(() => new QueryClient({
    defaultOptions: {
      queries: {
        staleTime:            2 * 60 * 1000,  // données fraîches 2 min → pas de refetch en navigation
        gcTime:               10 * 60 * 1000, // cache conservé 10 min
        refetchOnWindowFocus: false,           // pas de refetch au retour sur l'onglet
        retry:                1,
      },
    },
  }))

  return (
    <QueryClientProvider client={queryClient}>
      {children}
      <Toaster position="top-right" toastOptions={{
        duration: 4000,
        style: { fontSize: '14px' },
      }} />
    </QueryClientProvider>
  )
}

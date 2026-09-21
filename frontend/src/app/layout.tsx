import type { Metadata } from 'next'
import { Manrope } from 'next/font/google'
import './globals.css'
import Providers from './providers'

const manrope = Manrope({ subsets: ['latin'], variable: '--font-manrope' })

export const metadata: Metadata = {
  title: 'SGP-GE — Gestion des Passeports',
  description: 'Système de Gestion des Passeports des Guinéens Établis à l\'Étranger',
}

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="fr" className={`h-full ${manrope.variable}`}>
      <body className="h-full font-sans antialiased">
        <Providers>{children}</Providers>
      </body>
    </html>
  )
}

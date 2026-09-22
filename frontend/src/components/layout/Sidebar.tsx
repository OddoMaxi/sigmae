'use client'

import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { useAuthStore, hasPermission } from '@/stores/authStore'
import {
  LayoutDashboard, FileText, Package, Building2,
  Truck, AlertTriangle, BarChart3, ClipboardList,
  Users, LogOut, Archive, ScanLine, Fingerprint, Printer, KeyRound, BookOpen, HandCoins,
} from 'lucide-react'
import { cn } from '@/lib/utils'

const navItems = [
  { href: '/dashboard',     label: 'Tableau de bord',       icon: LayoutDashboard, permission: null },
  { href: '/guide',         label: 'Guide',                 icon: BookOpen,        permission: null },
  { href: '/enrolements',   label: 'Enrôlements',           icon: Fingerprint,     permission: 'enrolement.view' },
  { href: '/impression',    label: 'Impression',            icon: Printer,         permission: 'passeports.create' },
  { href: '/reception-mae', label: 'Réception MAE',         icon: ScanLine,        permission: 'passeports.create' },
  { href: '/retrait',       label: 'Retrait citoyens',      icon: HandCoins,       permission: 'lots.receive' },
  { href: '/stock',         label: 'Stock Central',         icon: Archive,         permission: 'passeports.view' },
  { href: '/passeports',    label: 'Passeports',            icon: FileText,        permission: 'passeports.view' },
  { href: '/lots',          label: 'Lots',            icon: Package,         permission: 'lots.view' },
  { href: '/ambassades',    label: 'Ambassades',      icon: Building2,       permission: 'ambassades.view' },
  { href: '/transporteurs', label: 'Transporteurs',   icon: Truck,           permission: 'transporteurs.view' },
  { href: '/anomalies',     label: 'Anomalies',       icon: AlertTriangle,   permission: 'anomalies.view' },
  { href: '/reporting',     label: 'Reporting',       icon: BarChart3,       permission: 'reporting.view' },
  { href: '/audit',         label: 'Audit',           icon: ClipboardList,   permission: 'audit.view' },
  { href: '/utilisateurs',  label: 'Utilisateurs',    icon: Users,           permission: 'users.view' },
  { href: '/roles',         label: 'Rôles & Droits',  icon: KeyRound,        permission: 'roles.view' },
]

// Couleur d'avatar déterministe à partir du nom — pas de photo, mais pas
// non plus un unique bleu générique pour tout le monde.
const AVATAR_HUES = ['#c99a2e', '#3d87b2', '#7c9885', '#b06b5f', '#8a7ab5']
function avatarColor(name: string) {
  const sum = [...name].reduce((acc, c) => acc + c.charCodeAt(0), 0)
  return AVATAR_HUES[sum % AVATAR_HUES.length]
}
function initials(name?: string) {
  if (!name) return '?'
  const parts = name.trim().split(/\s+/)
  return ((parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '')).toUpperCase()
}

export default function Sidebar() {
  const pathname  = usePathname()
  const { user, logout } = useAuthStore()

  const visible = navItems.filter(
    (item) => item.permission === null || hasPermission(user, item.permission)
  )

  return (
    <aside
      className="flex flex-col w-64 text-white/90 min-h-screen shrink-0"
      style={{ background: 'linear-gradient(180deg, var(--color-navy-900) 0%, var(--color-navy-950) 100%)' }}
    >
      <div className="flex items-center gap-2.5 px-5 pt-6 pb-5">
        <img src="/images/logo-maeiage.jpg" alt="MAEIAGE" className="h-9 w-9 rounded-full ring-2 ring-white/15" />
        <div className="min-w-0">
          <p className="font-bold text-[15px] leading-tight tracking-tight text-white">SGP-GE</p>
          <p className="text-[11px] text-white/45 leading-tight">Gestion des Passeports</p>
        </div>
      </div>

      <nav className="flex-1 px-3 pb-4 space-y-0.5 overflow-y-auto">
        {visible.map((item) => {
          const active = pathname.startsWith(item.href)
          return (
            <Link key={item.href} href={item.href}
              className={cn(
                'relative flex items-center gap-3 pl-3.5 pr-3 py-2.5 rounded-lg text-[13.5px] transition-colors',
                active
                  ? 'bg-white/[0.08] text-white font-semibold'
                  : 'text-white/55 hover:bg-white/[0.05] hover:text-white/90'
              )}>
              {active && (
                <span
                  className="absolute left-0 top-1.5 bottom-1.5 w-[3px] rounded-full"
                  style={{ background: 'var(--color-gold-400)' }}
                />
              )}
              <item.icon size={17} strokeWidth={2} className={active ? 'text-white' : 'text-white/45'} />
              {item.label}
            </Link>
          )
        })}
      </nav>

      <div className="mx-3 mb-4 p-3 rounded-xl bg-white/[0.04] border border-white/[0.06]">
        <div className="flex items-center gap-2.5 mb-2.5">
          <div
            className="h-8 w-8 shrink-0 rounded-full flex items-center justify-center text-[11px] font-bold text-white"
            style={{ background: avatarColor(user?.name ?? '?') }}
          >
            {initials(user?.name)}
          </div>
          <div className="min-w-0">
            <p className="text-[13px] font-semibold text-white truncate">{user?.name}</p>
            <p className="text-[10.5px] text-white/45 truncate capitalize">{user?.role_label ?? user?.role?.replace('_', ' ')}</p>
          </div>
        </div>
        <button
          onClick={logout}
          className="flex items-center justify-center gap-1.5 w-full text-[11.5px] font-medium text-white/60 hover:text-white bg-white/[0.03] hover:bg-white/[0.08] rounded-lg py-1.5 transition-colors"
        >
          <LogOut size={12.5} />
          Déconnexion
        </button>
      </div>
    </aside>
  )
}

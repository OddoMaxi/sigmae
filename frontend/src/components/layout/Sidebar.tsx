'use client'

import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { useAuthStore, hasPermission } from '@/stores/authStore'
import {
  LayoutDashboard, FileText, Package, Building2,
  Truck, AlertTriangle, BarChart3, ClipboardList,
  Users, LogOut, Shield, Archive, ScanLine, Fingerprint, Printer, KeyRound, BookOpen, HandCoins,
} from 'lucide-react'
import { cn } from '@/lib/utils'

const navItems = [
  { href: '/dashboard',     label: 'Tableau de bord',       icon: LayoutDashboard, permission: null },
  { href: '/guide',         label: 'Guide',                 icon: BookOpen,        permission: null },
  { href: '/enrolements',   label: 'Enrôlements',           icon: Fingerprint,     permission: 'passeports.create' },
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

export default function Sidebar() {
  const pathname  = usePathname()
  const { user, logout } = useAuthStore()

  const visible = navItems.filter(
    (item) => item.permission === null || hasPermission(user, item.permission)
  )

  return (
    <aside className="flex flex-col w-64 bg-[#1a5276] text-white min-h-screen">
      <div className="p-6 border-b border-white/10">
        <div className="flex items-center gap-2 mb-1">
          <Shield size={20} />
          <span className="font-bold text-lg">SGP-GE</span>
        </div>
        <p className="text-xs text-white/60">Gestion des Passeports</p>
      </div>

      <nav className="flex-1 p-4 space-y-1 overflow-y-auto">
        {visible.map((item) => {
          const active = pathname.startsWith(item.href)
          return (
            <Link key={item.href} href={item.href}
              className={cn(
                'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition',
                active
                  ? 'bg-white/20 font-medium'
                  : 'hover:bg-white/10 text-white/80 hover:text-white'
              )}>
              <item.icon size={18} />
              {item.label}
            </Link>
          )
        })}
      </nav>

      <div className="p-4 border-t border-white/10">
        <div className="text-xs text-white/60 mb-3">
          <p className="font-medium text-white/90 truncate">{user?.name}</p>
          <p className="capitalize">{user?.role_label ?? user?.role?.replace('_', ' ')}</p>
        </div>
        <button
          onClick={logout}
          className="flex items-center gap-2 text-xs text-white/70 hover:text-white transition w-full"
        >
          <LogOut size={14} />
          Déconnexion
        </button>
      </div>
    </aside>
  )
}

'use client'

import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useAuthStore } from '@/stores/authStore'
import toast from 'react-hot-toast'
import { Eye, EyeOff, Mail, Lock, ArrowRight } from 'lucide-react'

const schema = z.object({
  email:    z.string().email('Email invalide'),
  password: z.string().min(1, 'Mot de passe requis'),
})

type FormData = z.infer<typeof schema>

export default function LoginPage() {
  const login = useAuthStore((s) => s.login)
  const [show, setShow] = useState(false)

  const { register, handleSubmit, formState: { errors, isSubmitting } } = useForm<FormData>({
    resolver: zodResolver(schema),
  })

  const onSubmit = async (data: FormData) => {
    try {
      await login(data.email, data.password)
      toast.success('Connexion réussie')
      // window.location évite le bug Next.js 16 Turbopack avec useRouter
      window.location.href = '/dashboard'
    } catch (e: any) {
      toast.error(e.response?.data?.message || 'Identifiants incorrects')
    }
  }

  return (
    <div className="min-h-screen flex">
      {/* Panneau institutionnel */}
      <div
        className="hidden lg:flex w-[44%] flex-col justify-between p-12 text-white relative overflow-hidden"
        style={{ background: 'linear-gradient(160deg, var(--color-navy-900) 0%, var(--color-navy-950) 100%)' }}
      >
        <div
          className="absolute inset-0 opacity-[0.07] pointer-events-none"
          style={{
            backgroundImage: 'radial-gradient(circle, #ffffff 1px, transparent 1px)',
            backgroundSize: '22px 22px',
          }}
        />
        <div className="relative flex items-center gap-3">
          <img src="/images/logo-maeiage.jpg" alt="MAEIAGE" className="h-11 w-11 rounded-full ring-2 ring-white/15" />
          <div>
            <p className="font-bold text-[15px] leading-tight">SGP-GE</p>
            <p className="text-[11px] text-white/45">République de Guinée</p>
          </div>
        </div>

        <div className="relative space-y-5 max-w-sm">
          <div className="h-[3px] w-10 rounded-full" style={{ background: 'var(--color-gold-400)' }} />
          <h1 className="text-[26px] font-bold leading-[1.2] tracking-tight">
            Système de Gestion des Passeports des Guinéens Établis à l&apos;Étranger
          </h1>
          <p className="text-[13.5px] text-white/55 leading-relaxed">
            De l&apos;enrôlement à l&apos;ambassade jusqu&apos;à la remise du passeport —
            un circuit numérique unique, traçable à chaque étape.
          </p>
        </div>

        <p className="relative text-[11px] text-white/35">
          Ministère des Affaires Étrangères, de l&apos;Intégration Africaine
          et des Guinéens Établis à l&apos;Étranger
        </p>
      </div>

      {/* Formulaire */}
      <div className="flex-1 flex items-center justify-center p-6 bg-[var(--background)]">
        <div className="w-full max-w-sm">
          <div className="lg:hidden flex items-center gap-2.5 justify-center mb-8">
            <img src="/images/logo-maeiage.jpg" alt="MAEIAGE" className="h-10 w-10 rounded-full" />
            <span className="font-bold text-lg text-[color:var(--color-navy-900)]">SGP-GE</span>
          </div>

          <h2 className="text-[22px] font-bold text-[color:var(--color-navy-900)] tracking-tight">Connexion</h2>
          <p className="text-[13px] text-slate-500 mt-1 mb-8">Accédez à votre espace de travail.</p>

          <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
            <div>
              <label className="block text-[12.5px] font-semibold text-slate-600 mb-1.5">Adresse email</label>
              <div className="relative">
                <Mail size={15} className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
                <input
                  {...register('email')}
                  type="email"
                  autoComplete="email"
                  className="w-full border border-slate-200 rounded-lg pl-10 pr-4 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[color:var(--color-navy-600)]/30 focus:border-[color:var(--color-navy-600)] transition-colors"
                  placeholder="votre.email@mae.gov.gn"
                />
              </div>
              {errors.email && <p className="text-red-500 text-xs mt-1">{errors.email.message}</p>}
            </div>

            <div>
              <label className="block text-[12.5px] font-semibold text-slate-600 mb-1.5">Mot de passe</label>
              <div className="relative">
                <Lock size={15} className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
                <input
                  {...register('password')}
                  type={show ? 'text' : 'password'}
                  autoComplete="current-password"
                  className="w-full border border-slate-200 rounded-lg pl-10 pr-10 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[color:var(--color-navy-600)]/30 focus:border-[color:var(--color-navy-600)] transition-colors"
                />
                <button type="button" onClick={() => setShow(!show)}
                  className="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                  {show ? <EyeOff size={15} /> : <Eye size={15} />}
                </button>
              </div>
              {errors.password && <p className="text-red-500 text-xs mt-1">{errors.password.message}</p>}
            </div>

            <button
              type="submit"
              disabled={isSubmitting}
              className="w-full flex items-center justify-center gap-2 text-white font-semibold py-2.5 rounded-lg text-sm transition disabled:opacity-60 mt-2"
              style={{ background: 'var(--color-navy-900)' }}
            >
              {isSubmitting ? 'Connexion…' : (<>Se connecter <ArrowRight size={15} /></>)}
            </button>
          </form>
        </div>
      </div>
    </div>
  )
}

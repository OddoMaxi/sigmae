'use client'

import { useState } from 'react'
import {
  Fingerprint, Printer, ScanLine, Archive, Package,
  CheckCircle, Send, QrCode, MapPin, UserCheck,
  AlertTriangle, ChevronDown, ChevronUp, Shield,
  Clock, ArrowRight, Users, Building2, Truck, Info,
} from 'lucide-react'
import { cn } from '@/lib/utils'

/* ─── Types ─────────────────────────────────────────────────────────────── */

interface Step {
  id: number
  icon: React.ElementType
  color: string
  bg: string
  badge: string
  title: string
  actor: string
  actorIcon: React.ElementType
  lieu: string
  statut: { from: string; to: string; fromColor: string; toColor: string }
  description: string
  details: string[]
  tip?: string
}

/* ─── Données ────────────────────────────────────────────────────────────── */

const STEPS: Step[] = [
  {
    id: 1,
    icon: Fingerprint,
    color: 'text-violet-600',
    bg: 'bg-violet-50 border-violet-200',
    badge: 'bg-violet-100 text-violet-700',
    title: 'Enrôlement biométrique',
    actor: 'Agent ambassade',
    actorIcon: Users,
    lieu: 'Ambassade (pays de résidence)',
    statut: { from: '—', to: 'ENRÔLÉ', fromColor: 'bg-gray-100 text-gray-500', toColor: 'bg-violet-100 text-violet-700' },
    description:
      "Le citoyen se présente à l'ambassade de son pays de résidence. L'agent saisit sa demande dans le système et capture ses données biométriques.",
    details: [
      'Saisie des informations du citoyen : nom, prénom, date de naissance',
      'Enregistrement de l\'email et du numéro de téléphone pour les notifications',
      'Le système génère automatiquement une référence unique : DEM-FRPAR-20260621-X7K2P',
      'Le dossier est transmis électroniquement au Ministère des Affaires Étrangères à Conakry',
    ],
    tip: 'La référence de demande permet au citoyen de suivre son dossier à tout moment.',
  },
  {
    id: 2,
    icon: Printer,
    color: 'text-gray-600',
    bg: 'bg-gray-50 border-gray-200',
    badge: 'bg-gray-100 text-gray-700',
    title: 'Impression du passeport',
    actor: 'Gestionnaire MAE',
    actorIcon: Shield,
    lieu: 'Ministère des Affaires Étrangères — Conakry',
    statut: { from: 'ENRÔLÉ', to: 'IMPRIMÉ', fromColor: 'bg-violet-100 text-violet-700', toColor: 'bg-gray-200 text-gray-700' },
    description:
      'Le dossier arrive au MAE à Conakry. Le gestionnaire consulte la liste des demandes enrôlées et assigne un numéro de passeport physique à chaque dossier.',
    details: [
      'Consultation de la liste des enrôlements en attente d\'impression',
      'Assignation du numéro de passeport officiel au dossier',
      'Enregistrement de la date d\'impression',
      'Le document physique est produit par l\'imprimerie centrale',
    ],
    tip: 'Un passeport ne peut pas avoir deux numéros — chaque numéro est unique dans le système.',
  },
  {
    id: 3,
    icon: ScanLine,
    color: 'text-blue-600',
    bg: 'bg-blue-50 border-blue-200',
    badge: 'bg-blue-100 text-blue-700',
    title: 'Réception au MAE',
    actor: 'Gestionnaire MAE',
    actorIcon: Shield,
    lieu: 'Ministère des Affaires Étrangères — Conakry',
    statut: { from: 'IMPRIMÉ', to: 'REÇU MAE', fromColor: 'bg-gray-200 text-gray-700', toColor: 'bg-blue-100 text-blue-700' },
    description:
      "Le passeport physique imprimé est remis au bureau de gestion du MAE. Le gestionnaire confirme dans le système la réception physique du document.",
    details: [
      'Réception physique du passeport depuis l\'imprimerie',
      'Vérification de la conformité du document',
      'Confirmation de réception dans le système',
      'Le document est désormais sous la responsabilité du MAE',
    ],
  },
  {
    id: 4,
    icon: Archive,
    color: 'text-indigo-600',
    bg: 'bg-indigo-50 border-indigo-200',
    badge: 'bg-indigo-100 text-indigo-700',
    title: 'Mise en stock',
    actor: 'Gestionnaire MAE',
    actorIcon: Shield,
    lieu: 'Ministère des Affaires Étrangères — Conakry',
    statut: { from: 'REÇU MAE', to: 'EN STOCK', fromColor: 'bg-blue-100 text-blue-700', toColor: 'bg-indigo-100 text-indigo-700' },
    description:
      "Le passeport est intégré au stock central du MAE, en attente d'être inclus dans un lot d'expédition vers son ambassade de destination.",
    details: [
      'Le passeport est enregistré dans le stock central',
      'Il est visible dans la page « Stock Central »',
      'Il peut être ajouté à un lot d\'expédition à tout moment',
      'Le stock est filtrable par ambassade de destination',
    ],
    tip: 'Un passeport en stock ne peut appartenir qu\'à un seul lot actif à la fois.',
  },
  {
    id: 5,
    icon: Package,
    color: 'text-yellow-600',
    bg: 'bg-yellow-50 border-yellow-200',
    badge: 'bg-yellow-100 text-yellow-700',
    title: 'Constitution du lot',
    actor: 'Gestionnaire MAE',
    actorIcon: Shield,
    lieu: 'Ministère des Affaires Étrangères — Conakry',
    statut: { from: 'EN STOCK', to: 'EN LOT', fromColor: 'bg-indigo-100 text-indigo-700', toColor: 'bg-yellow-100 text-yellow-700' },
    description:
      "Les passeports destinés à la même ambassade sont regroupés dans un lot d'expédition. Chaque lot a une référence unique et est associé à une ambassade.",
    details: [
      'Création d\'un lot : LOT-2026-0042 → Ambassade de Paris',
      'Ajout des passeports du stock destinés à cette ambassade',
      'Vérification du contenu avant soumission à la validation',
      'Le lot est d\'abord en statut « Brouillon » — modifiable à tout moment',
    ],
  },
  {
    id: 6,
    icon: CheckCircle,
    color: 'text-green-600',
    bg: 'bg-green-50 border-green-200',
    badge: 'bg-green-100 text-green-700',
    title: 'Validation du lot',
    actor: 'Gestionnaire / Responsable Cellule',
    actorIcon: Shield,
    lieu: 'Ministère des Affaires Étrangères — Conakry',
    statut: { from: 'BROUILLON', to: 'VALIDÉ', fromColor: 'bg-gray-100 text-gray-600', toColor: 'bg-green-100 text-green-700' },
    description:
      'Le gestionnaire ou le responsable de cellule contrôle le contenu du lot et valide avant l\'expédition. Cette validation est irréversible.',
    details: [
      'Contrôle du nombre de passeports et des ambassades de destination',
      'Validation officielle dans le système',
      'Le lot ne peut plus être modifié après validation',
      'Le gestionnaire peut ensuite procéder à l\'expédition',
    ],
    tip: 'Le Gestionnaire, le Responsable Cellule et l\'Admin peuvent valider un lot.',
  },
  {
    id: 7,
    icon: Send,
    color: 'text-orange-600',
    bg: 'bg-orange-50 border-orange-200',
    badge: 'bg-orange-100 text-orange-700',
    title: 'Expédition du lot',
    actor: 'Gestionnaire MAE',
    actorIcon: Shield,
    lieu: 'Ministère des Affaires Étrangères — Conakry',
    statut: { from: 'VALIDÉ', to: 'EXPÉDIÉ', fromColor: 'bg-green-100 text-green-700', toColor: 'bg-orange-100 text-orange-700' },
    description:
      'Le lot est remis au transporteur. Le système génère automatiquement un bordereau PDF avec un QR Code sécurisé qui accompagne le colis physique.',
    details: [
      'Sélection du transporteur (DHL, etc.) et saisie du numéro de suivi',
      'Génération automatique du bordereau PDF officiel',
      'QR Code sécurisé intégré au bordereau (signé HMAC-SHA256)',
      'Le bordereau est imprimé et glissé dans le colis avec les passeports',
      'Statut des passeports : EN LOT → EXPÉDIÉ',
    ],
    tip: 'Le QR Code est cryptographiquement signé — il ne peut pas être falsifié.',
  },
  {
    id: 8,
    icon: Clock,
    color: 'text-purple-600',
    bg: 'bg-purple-50 border-purple-200',
    badge: 'bg-purple-100 text-purple-700',
    title: 'Transit (optionnel)',
    actor: 'Transporteur',
    actorIcon: Truck,
    lieu: 'En route vers l\'ambassade',
    statut: { from: 'EXPÉDIÉ', to: 'EN TRANSIT', fromColor: 'bg-orange-100 text-orange-700', toColor: 'bg-purple-100 text-purple-700' },
    description:
      "Suivi intermédiaire du colis pendant son acheminement. Cette étape est optionnelle — un lot peut passer directement d'EXPÉDIÉ à REÇU AMBASSADE.",
    details: [
      'Mise à jour du statut si le transporteur fournit un suivi intermédiaire',
      'Délai habituel : 3 à 10 jours ouvrables selon la destination',
      'Visible dans la page Lots avec indication du transporteur',
    ],
  },
  {
    id: 9,
    icon: QrCode,
    color: 'text-teal-600',
    bg: 'bg-teal-50 border-teal-200',
    badge: 'bg-teal-100 text-teal-700',
    title: 'Réception à l\'ambassade',
    actor: 'Agent ambassade',
    actorIcon: Building2,
    lieu: 'Ambassade (pays de résidence)',
    statut: { from: 'EN TRANSIT', to: 'REÇU AMBASSADE', fromColor: 'bg-purple-100 text-purple-700', toColor: 'bg-teal-100 text-teal-700' },
    description:
      "À la réception du colis, l'agent scanne le QR Code du bordereau avec son téléphone. Une page mobile s'ouvre et il confirme chaque passeport un par un.",
    details: [
      'Scan du QR Code imprimé sur le bordereau avec un smartphone',
      'Ouverture automatique de la page de réception (/reception/scan/...)',
      'Confirmation passeport par passeport : Confirmé ou Anomalie',
      'Si tout est reçu → statut REÇU AMBASSADE',
      'Si réception partielle → statut REÇU PARTIEL + anomalies créées',
    ],
    tip: 'La page de scan est optimisée mobile — aucune application à installer.',
  },
  {
    id: 10,
    icon: MapPin,
    color: 'text-green-600',
    bg: 'bg-green-50 border-green-200',
    badge: 'bg-green-100 text-green-700',
    title: 'Disponible au retrait',
    actor: 'Agent ambassade',
    actorIcon: Building2,
    lieu: 'Ambassade (pays de résidence)',
    statut: { from: 'REÇU AMBASSADE', to: 'DISPONIBLE RETRAIT', fromColor: 'bg-teal-100 text-teal-700', toColor: 'bg-green-100 text-green-700' },
    description:
      "L'agent marque le passeport comme prêt à être remis au citoyen. Le système envoie automatiquement un email de notification au citoyen.",
    details: [
      'Marquage du passeport comme disponible au retrait',
      'Envoi automatique d\'un email au citoyen à son adresse enregistrée',
      'L\'email contient les informations de l\'ambassade et les horaires de retrait',
      'Le passeport attend le citoyen au guichet de l\'ambassade',
    ],
    tip: 'Le citoyen est notifié par email dès que son passeport est prêt.',
  },
  {
    id: 11,
    icon: UserCheck,
    color: 'text-emerald-600',
    bg: 'bg-emerald-50 border-emerald-200',
    badge: 'bg-emerald-100 text-emerald-700',
    title: 'Remise au citoyen',
    actor: 'Agent ambassade',
    actorIcon: Building2,
    lieu: 'Ambassade (pays de résidence)',
    statut: { from: 'DISPONIBLE RETRAIT', to: 'REMIS AU CITOYEN', fromColor: 'bg-green-100 text-green-700', toColor: 'bg-emerald-100 text-emerald-700' },
    description:
      "Le citoyen se présente à l'ambassade, présente sa pièce d'identité et signe le registre. L'agent confirme la remise dans le système. Le processus est terminé.",
    details: [
      'Vérification de l\'identité du citoyen au guichet',
      'Signature du registre de remise',
      'Confirmation de la remise dans le système avec horodatage',
      'La date de délivrance est enregistrée définitivement',
      'Statut final : REMIS AU CITOYEN ✓',
    ],
  },
]

const ROLES = [
  {
    icon: Shield,
    color: 'bg-[#1a5276]',
    title: 'Administrateur Central',
    desc: 'Accès complet au système. Gère les utilisateurs, ambassades, transporteurs et paramètres globaux.',
  },
  {
    icon: Shield,
    color: 'bg-blue-600',
    title: 'Gestionnaire MAE',
    desc: 'Crée et gère les lots, importe les passeports, pilote les expéditions depuis Conakry.',
  },
  {
    icon: Shield,
    color: 'bg-indigo-600',
    title: 'Superviseur MAE',
    desc: 'Surveille les indicateurs, consulte les rapports et traite les anomalies. Accès en lecture sur les lots.',
  },
  {
    icon: Building2,
    color: 'bg-teal-600',
    title: 'Agent Ambassade',
    desc: 'Enrôle les demandes, réceptionne les lots via QR Code et remet les passeports aux citoyens.',
  },
]

const ANOMALY_TYPES = [
  { type: 'Manquant', desc: 'Passeport absent du colis à la réception', color: 'text-red-600 bg-red-50 border-red-200' },
  { type: 'Endommagé', desc: 'Document physiquement dégradé ou illisible', color: 'text-orange-600 bg-orange-50 border-orange-200' },
  { type: 'Erroné', desc: 'Informations incorrectes sur le document', color: 'text-yellow-600 bg-yellow-50 border-yellow-200' },
  { type: 'Autre', desc: 'Tout autre problème nécessitant un traitement', color: 'text-gray-600 bg-gray-50 border-gray-200' },
]

/* ─── Composants ─────────────────────────────────────────────────────────── */

function StatusBadge({ label, color }: { label: string; color: string }) {
  return (
    <span className={cn('text-xs font-semibold px-2.5 py-1 rounded-full', color)}>
      {label}
    </span>
  )
}

function StepCard({ step, index }: { step: Step; index: number }) {
  const [open, setOpen] = useState(false)
  const Icon = step.icon
  const ActorIcon = step.actorIcon

  return (
    <div className={cn('border rounded-xl overflow-hidden transition-shadow hover:shadow-md', step.bg)}>
      {/* Header */}
      <button
        className="w-full text-left p-5 flex items-start gap-4"
        onClick={() => setOpen((v) => !v)}
      >
        {/* Numéro */}
        <div className="flex-shrink-0 w-9 h-9 rounded-full bg-white shadow-sm flex items-center justify-center font-bold text-gray-700 text-sm border">
          {index + 1}
        </div>

        {/* Icône */}
        <div className="flex-shrink-0 mt-0.5">
          <Icon size={22} className={step.color} />
        </div>

        {/* Contenu */}
        <div className="flex-1 min-w-0">
          <div className="flex flex-wrap items-center gap-2 mb-1">
            <h3 className="font-semibold text-gray-800 text-base">{step.title}</h3>
            <span className={cn('text-xs font-medium px-2 py-0.5 rounded-full', step.badge)}>
              Étape {index + 1}
            </span>
          </div>
          <div className="flex flex-wrap items-center gap-3 text-xs text-gray-500">
            <span className="flex items-center gap-1">
              <ActorIcon size={12} />
              {step.actor}
            </span>
            <span className="flex items-center gap-1">
              <MapPin size={12} />
              {step.lieu}
            </span>
          </div>
          {/* Transition de statut */}
          <div className="flex items-center gap-2 mt-2">
            <StatusBadge label={step.statut.from} color={step.statut.fromColor} />
            <ArrowRight size={14} className="text-gray-400 flex-shrink-0" />
            <StatusBadge label={step.statut.to} color={step.statut.toColor} />
          </div>
        </div>

        {/* Chevron */}
        <div className="flex-shrink-0 mt-1 text-gray-400">
          {open ? <ChevronUp size={18} /> : <ChevronDown size={18} />}
        </div>
      </button>

      {/* Détails dépliables */}
      {open && (
        <div className="px-5 pb-5 space-y-4 border-t border-white/60 pt-4">
          <p className="text-sm text-gray-700 leading-relaxed">{step.description}</p>

          <ul className="space-y-2">
            {step.details.map((d, i) => (
              <li key={i} className="flex items-start gap-2 text-sm text-gray-600">
                <CheckCircle size={14} className="mt-0.5 flex-shrink-0 text-green-500" />
                {d}
              </li>
            ))}
          </ul>

          {step.tip && (
            <div className="flex items-start gap-2 bg-white/70 rounded-lg px-3 py-2.5 border border-white">
              <Info size={14} className="mt-0.5 flex-shrink-0 text-blue-500" />
              <p className="text-xs text-blue-700">{step.tip}</p>
            </div>
          )}
        </div>
      )}
    </div>
  )
}

/* ─── Page principale ────────────────────────────────────────────────────── */

export default function GuidePage() {
  const [allOpen, setAllOpen] = useState(false)

  return (
    <div className="space-y-10 max-w-4xl">

      {/* En-tête */}
      <div>
        <div className="flex items-center gap-3 mb-2">
          <div className="p-2.5 bg-[#1a5276] rounded-xl">
            <Shield size={22} className="text-white" />
          </div>
          <div>
            <h1 className="text-2xl font-bold text-gray-800">Guide du système SGP-GE</h1>
            <p className="text-sm text-gray-500">Processus complet — de l'enrôlement à la délivrance du passeport</p>
          </div>
        </div>
      </div>

      {/* Vue synthétique du flux */}
      <section className="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
        <h2 className="font-semibold text-gray-700 mb-5 flex items-center gap-2">
          <ArrowRight size={16} className="text-[#1a5276]" />
          Flux simplifié
        </h2>
        <div className="flex flex-wrap gap-2 items-center">
          {[
            { label: 'ENRÔLÉ', color: 'bg-violet-100 text-violet-700' },
            { label: 'IMPRIMÉ', color: 'bg-gray-200 text-gray-700' },
            { label: 'REÇU MAE', color: 'bg-blue-100 text-blue-700' },
            { label: 'EN STOCK', color: 'bg-indigo-100 text-indigo-700' },
            { label: 'EN LOT', color: 'bg-yellow-100 text-yellow-700' },
            { label: 'EXPÉDIÉ', color: 'bg-orange-100 text-orange-700' },
            { label: 'EN TRANSIT', color: 'bg-purple-100 text-purple-700' },
            { label: 'REÇU AMBASSADE', color: 'bg-teal-100 text-teal-700' },
            { label: 'DISPONIBLE RETRAIT', color: 'bg-green-100 text-green-700' },
            { label: 'REMIS AU CITOYEN', color: 'bg-emerald-100 text-emerald-700' },
          ].map((s, i, arr) => (
            <div key={s.label} className="flex items-center gap-2">
              <span className={cn('text-xs font-semibold px-2.5 py-1 rounded-full', s.color)}>
                {s.label}
              </span>
              {i < arr.length - 1 && <ArrowRight size={14} className="text-gray-300 flex-shrink-0" />}
            </div>
          ))}
        </div>
        <p className="text-xs text-gray-400 mt-4 flex items-center gap-1">
          <AlertTriangle size={12} />
          À n'importe quelle étape, un problème peut créer une <strong className="text-red-500 ml-1">ANOMALIE</strong>
        </p>
      </section>

      {/* Rôles */}
      <section>
        <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Les acteurs du système</h2>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          {ROLES.map((r) => (
            <div key={r.title} className="bg-white rounded-xl border border-gray-100 shadow-sm p-4 flex items-start gap-3">
              <div className={cn('p-2 rounded-lg flex-shrink-0', r.color)}>
                <r.icon size={16} className="text-white" />
              </div>
              <div>
                <p className="font-medium text-gray-800 text-sm">{r.title}</p>
                <p className="text-xs text-gray-500 mt-0.5 leading-relaxed">{r.desc}</p>
              </div>
            </div>
          ))}
        </div>
      </section>

      {/* Étapes détaillées */}
      <section>
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wider">
            Les 11 étapes du processus
          </h2>
          <button
            onClick={() => setAllOpen((v) => !v)}
            className="text-xs text-[#1a5276] hover:underline flex items-center gap-1"
          >
            {allOpen ? <ChevronUp size={14} /> : <ChevronDown size={14} />}
            {allOpen ? 'Tout réduire' : 'Tout développer'}
          </button>
        </div>
        <div className="space-y-3">
          {STEPS.map((step, i) => (
            <StepCard key={step.id} step={step} index={i} />
          ))}
        </div>
      </section>

      {/* Gestion des anomalies */}
      <section className="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
        <h2 className="font-semibold text-gray-700 mb-1 flex items-center gap-2">
          <AlertTriangle size={16} className="text-red-500" />
          Gestion des anomalies
        </h2>
        <p className="text-sm text-gray-500 mb-5">
          Une anomalie peut être déclarée à tout moment du processus. Elle suit son propre cycle de vie jusqu'à résolution.
        </p>

        {/* Types d'anomalies */}
        <div className="grid grid-cols-2 gap-3 mb-6">
          {ANOMALY_TYPES.map((a) => (
            <div key={a.type} className={cn('border rounded-lg px-3 py-2.5', a.color)}>
              <p className="font-semibold text-sm">{a.type}</p>
              <p className="text-xs mt-0.5 opacity-80">{a.desc}</p>
            </div>
          ))}
        </div>

        {/* Cycle de l'anomalie */}
        <div>
          <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Cycle de traitement</p>
          <div className="flex items-center gap-2 flex-wrap">
            {[
              { label: 'OUVERT', color: 'bg-red-100 text-red-700' },
              { label: 'EN TRAITEMENT', color: 'bg-orange-100 text-orange-700' },
              { label: 'RÉSOLU', color: 'bg-green-100 text-green-700' },
            ].map((s, i, arr) => (
              <div key={s.label} className="flex items-center gap-2">
                <StatusBadge label={s.label} color={s.color} />
                {i < arr.length - 1 && <ArrowRight size={14} className="text-gray-300" />}
              </div>
            ))}
          </div>
          <p className="text-xs text-gray-400 mt-3">
            Après résolution, le passeport peut être réintégré en stock, marqué reçu en ambassade, ou disponible au retrait selon la situation.
          </p>
        </div>
      </section>

      {/* Journal d'audit */}
      <section className="bg-[#1a5276]/5 border border-[#1a5276]/20 rounded-xl p-6">
        <h2 className="font-semibold text-[#1a5276] mb-2 flex items-center gap-2">
          <Shield size={16} />
          Traçabilité complète
        </h2>
        <p className="text-sm text-gray-600 leading-relaxed">
          Chaque action dans le système est enregistrée dans le <strong>journal d'audit</strong> :
          qui a fait quoi, quand, et sur quel enregistrement. Les valeurs avant et après
          chaque modification sont conservées. De plus, chaque passeport possède son propre
          <strong> historique de suivi</strong> (tracking events) consultable depuis sa fiche.
        </p>
      </section>

    </div>
  )
}

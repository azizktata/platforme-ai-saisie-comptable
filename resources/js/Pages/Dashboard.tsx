import { Head, Link, router } from '@inertiajs/react';
import { Activity, AlertTriangle, BadgeCheck, Building2, Clock3, FileCheck2, FileText, Percent, ScanText, Users } from 'lucide-react';
import { toast } from 'sonner';
import AppShell from '../Components/AppShell';
import type { ReactNode } from 'react';
import type { CabinetSummary, CompanySummary, SharedAuthProps } from '../types';

type Metrics = { imported: number; analyzed: number; validated: number; pending: number; intervention: number; recognition_rate: number; validation_rate: number; errors: number; estimated_minutes_saved: number; journal_entries: number; manual_minutes_per_invoice: number };
type ActivityItem = { id: string; label: string; detail: string; date: string | null; url: string };
type Props = { cabinet: CabinetSummary; companies: CompanySummary[]; canManageCabinet: boolean; auth?: SharedAuthProps; selectedCompanyId: number | null; dashboard: Metrics | null; activity: ActivityItem[] };

export default function Dashboard({ cabinet, companies, canManageCabinet, auth, selectedCompanyId, dashboard, activity }: Props) {
  const company = companies.find((item) => item.id === selectedCompanyId) ?? null;
  const logout = auth?.user ? () => router.post('/logout', {}, { onSuccess: () => toast.success('Déconnexion réussie.'), onError: () => toast.error('La déconnexion a échoué. Réessayez.') }) : undefined;
  const cards = dashboard ? [
    { label: 'Factures importées', value: dashboard.imported, icon: FileText, tint: 'text-blue-700 bg-blue-50' },
    { label: 'Factures analysées', value: dashboard.analyzed, icon: ScanText, tint: 'text-violet-700 bg-violet-50' },
    { label: 'Factures validées', value: dashboard.validated, icon: BadgeCheck, tint: 'text-emerald-700 bg-emerald-50' },
    { label: 'En attente', value: dashboard.pending, icon: Clock3, tint: 'text-amber-700 bg-amber-50' },
    { label: 'À traiter', value: dashboard.intervention, icon: AlertTriangle, tint: 'text-orange-700 bg-orange-50' },
    { label: 'Taux de reconnaissance', value: `${dashboard.recognition_rate}%`, icon: ScanText, tint: 'text-teal-700 bg-teal-50' },
    { label: 'Taux de validation', value: `${dashboard.validation_rate}%`, icon: Percent, tint: 'text-teal-700 bg-teal-50' },
    { label: 'Erreurs de traitement', value: dashboard.errors, icon: AlertTriangle, tint: 'text-red-700 bg-red-50' },
    { label: 'Temps économisé estimé', value: `${Math.floor(dashboard.estimated_minutes_saved / 60)} h ${dashboard.estimated_minutes_saved % 60} min`, icon: Clock3, tint: 'text-indigo-700 bg-indigo-50' },
    { label: 'Écritures générées', value: dashboard.journal_entries, icon: FileCheck2, tint: 'text-emerald-700 bg-emerald-50' },
  ] : [];

  return <AppShell activeSection="overview" cabinetName={cabinet.name} canManageCabinet={canManageCabinet} user={auth?.user} onLogout={logout}>
    <Head title="Tableau de bord" />
    <section className="mx-auto max-w-7xl space-y-6">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div><p className="text-sm font-semibold uppercase tracking-[0.16em] text-teal-700">{cabinet.name}</p><h1 className="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Tableau de bord</h1><p className="mt-2 text-sm text-slate-600">Suivez l’activité des factures et des propositions comptables d’une société.</p></div>
        <div className="flex flex-wrap gap-2"><select aria-label="Société" className="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm" value={selectedCompanyId ?? ''} onChange={(event) => router.get('/', event.target.value ? { company_id: Number(event.target.value) } : {}, { preserveScroll: true, replace: true })}><option value="" disabled>Sélectionner une société</option>{companies.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select><button type="button" onClick={() => router.visit('/companies')} className="inline-flex items-center gap-2 rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800"><Building2 size={16} /> Sociétés</button></div>
      </header>
      {company && dashboard ? <>
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-teal-100 bg-teal-50/70 px-4 py-3"><div><p className="text-xs font-semibold uppercase tracking-wide text-teal-800">Société sélectionnée</p><p className="mt-1 font-semibold text-slate-900">{company.name}</p></div><div className="flex gap-4"><Link className="text-sm font-semibold text-teal-800 hover:underline" href={`/companies/${company.id}/invoices`}>Voir les factures</Link><Link className="text-sm font-semibold text-teal-800 hover:underline" href={`/companies/${company.id}/accounting-data`}>Données comptables</Link></div></div>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">{cards.map(({ label, value, icon: Icon, tint }) => <MetricCard key={label} label={label} value={value} icon={<Icon size={17} />} tint={tint} />)}</div>
        <p className="-mt-3 text-[11px] text-slate-500">Le taux de validation mesure les propositions acceptées par un comptable parmi les factures analysées. Le temps économisé est une estimation indicative basée sur {dashboard.manual_minutes_per_invoice} min par facture avec proposition.</p>
        <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><div className="mb-4 flex items-center justify-between"><div><h2 className="text-lg font-semibold text-slate-900">Activité récente</h2><p className="mt-1 text-sm text-slate-500">Imports, propositions et décisions pour {company.name}.</p></div><Activity className="text-teal-700" size={19} /></div>{activity.length ? <ol className="divide-y divide-slate-100">{activity.map((item) => <li key={item.id} className="flex flex-wrap items-center justify-between gap-3 py-3"><div><Link href={item.url} className="text-sm font-semibold text-slate-800 hover:text-teal-800">{item.label}</Link><p className="mt-0.5 text-xs text-slate-500">{item.detail}</p></div><time className="text-xs text-slate-500">{item.date ? new Intl.DateTimeFormat('fr-TN', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(item.date)) : '—'}</time></li>)}</ol> : <p className="rounded-lg bg-slate-50 p-5 text-center text-sm text-slate-500">Aucune activité enregistrée pour cette société.</p>}</section>
      </> : <div className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center"><Building2 className="mx-auto text-slate-400" size={28} /><h2 className="mt-3 font-semibold text-slate-900">Aucune société accessible</h2><p className="mt-1 text-sm text-slate-500">Une société apparaîtra ici dès qu’elle sera affectée à votre compte.</p>{canManageCabinet && <button type="button" onClick={() => router.visit('/companies')} className="mt-4 rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white">Ajouter une société</button>}</div>}
    </section>
  </AppShell>;
}

function MetricCard({ label, value, icon, tint }: { label: string; value: string | number; icon: ReactNode; tint: string }) { return <article className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><div className="flex items-center justify-between gap-2"><p className="text-xs font-semibold text-slate-500">{label}</p><span className={`flex h-8 w-8 items-center justify-center rounded-lg ${tint}`}>{icon}</span></div><p className="mt-3 text-2xl font-semibold tabular-nums text-slate-950">{value}</p></article>; }

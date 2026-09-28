import { router } from '@inertiajs/react';
import { ArrowRight, Building2, CircleCheck, FileText, ShieldCheck, Users } from 'lucide-react';
import { toast } from 'sonner';
import AppShell from '../Components/AppShell';
import type { ReactNode } from 'react';
import type { CabinetSummary, CompanySummary, SharedAuthProps } from '../types';

type Props = {
  cabinet: CabinetSummary;
  companies: CompanySummary[];
  canManageCabinet: boolean;
  auth?: SharedAuthProps;
};

const roleLabels: Record<string, string> = {
  cabinet_admin: 'Administrateur du cabinet',
  invoice_manager: 'Gestionnaire de factures',
  company_user: 'Consultation et revue',
};

export default function Dashboard({ cabinet, companies, canManageCabinet, auth }: Props) {
  return (
    <AppShell
      activeSection="overview"
      cabinetName={cabinet.name}
      canManageCabinet={canManageCabinet}
      user={auth?.user}
      onLogout={auth?.user ? () => router.post('/logout', {}, {
        onSuccess: () => toast.success('Déconnexion réussie.'),
        onError: () => toast.error('La déconnexion a échoué. Réessayez.'),
      }) : undefined}
    >
      <section className="mx-auto max-w-7xl space-y-8">
        <div className="flex flex-wrap items-end justify-between gap-4">
          <div>
            <p className="text-sm font-semibold uppercase tracking-[0.16em] text-teal-700">Espace de travail</p>
            <h1 className="mt-2 text-3xl font-semibold tracking-tight text-slate-900">Bonjour{auth?.user?.name ? `, ${auth.user.name.split(' ')[0]}` : ''}</h1>
            <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
              Gérez les entreprises du cabinet et leurs accès comptables depuis un espace sécurisé.
            </p>
          </div>
          {canManageCabinet && (
            <button
              type="button"
              onClick={() => router.visit('/companies')}
              className="inline-flex items-center gap-2 rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-800"
            >
              <Building2 size={17} /> Gérer les sociétés
            </button>
          )}
        </div>

        <div className="grid gap-4 sm:grid-cols-3">
          <SummaryCard icon={<Building2 size={19} />} label="Sociétés accessibles" value={companies.length} />
          <SummaryCard icon={<Users size={19} />} label="Rôle dans le cabinet" value={canManageCabinet ? 'Administrateur' : 'Collaborateur'} />
          <SummaryCard icon={<ShieldCheck size={19} />} label="Isolation des données" value="Par société" />
        </div>

        <div>
          <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div>
              <h2 className="text-lg font-semibold text-slate-900">Vos sociétés</h2>
              <p className="mt-1 text-sm text-slate-500">Les factures, données Sage et accès seront isolés pour chaque société.</p>
            </div>
            {canManageCabinet && (
              <button type="button" onClick={() => router.visit('/cabinet/users')} className="text-sm font-semibold text-teal-700 hover:text-teal-900">
                Gérer les utilisateurs <ArrowRight className="ml-1 inline" size={15} />
              </button>
            )}
          </div>

          {companies.length > 0 ? (
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
              {companies.map((company) => (
                <article key={company.id} className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                  <div className="flex items-start justify-between gap-3">
                    <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-teal-50 text-teal-700">
                      <Building2 size={20} />
                    </div>
                    <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">
                      {roleLabels[company.access_role || ''] || 'Accès accordé'}
                    </span>
                  </div>
                  <h3 className="mt-4 text-base font-semibold text-slate-900">{company.name}</h3>
                  <p className="mt-1 min-h-5 text-sm text-slate-500">{company.activity || company.legal_name || 'Activité non renseignée'}</p>
                  <div className="mt-5 flex items-center justify-between border-t border-slate-100 pt-4 text-xs text-slate-500">
                    <span>{company.tax_identifier || 'Matricule fiscal à renseigner'}</span>
                    <span className="inline-flex items-center gap-1"><Users size={13} /> {company.users_count} membre{company.users_count > 1 ? 's' : ''}</span>
                  </div>
                  <div className="mt-4 flex flex-wrap gap-x-5 gap-y-2">
                    <button
                      type="button"
                      onClick={() => router.visit(`/companies/${company.id}/accounting-data`)}
                      className="inline-flex items-center gap-1 text-sm font-semibold text-teal-700 hover:text-teal-900"
                    >
                      Données comptables <ArrowRight size={15} />
                    </button>
                    <button
                      type="button"
                      onClick={() => router.visit(`/companies/${company.id}/invoices`)}
                      className="inline-flex items-center gap-1 text-sm font-semibold text-teal-700 hover:text-teal-900"
                    >
                      <FileText size={15} /> Factures <ArrowRight size={15} />
                    </button>
                  </div>
                </article>
              ))}
            </div>
          ) : (
            <div className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
              <CircleCheck className="mx-auto text-slate-400" size={28} />
              <h3 className="mt-3 font-semibold text-slate-900">Aucune société n’est encore affectée</h3>
              <p className="mx-auto mt-1 max-w-md text-sm text-slate-500">
                {canManageCabinet ? 'Ajoutez votre première société pour commencer à configurer le cabinet.' : 'Demandez à un administrateur du cabinet de vous donner accès à une société.'}
              </p>
              {canManageCabinet && (
                <button type="button" onClick={() => router.visit('/companies')} className="mt-5 rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">
                  Ajouter une société
                </button>
              )}
            </div>
          )}
        </div>
      </section>
    </AppShell>
  );
}

function SummaryCard({ icon, label, value }: { icon: ReactNode; label: string; value: string | number }) {
  return (
    <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
      <div className="flex items-center gap-2 text-sm text-slate-500">{icon}<span>{label}</span></div>
      <p className="mt-3 text-2xl font-semibold text-slate-900">{value}</p>
    </div>
  );
}

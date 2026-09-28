import { useState } from 'react';
import { ArrowRight, BookOpenText, Building2, Users } from 'lucide-react';
import AppShell from './Components/AppShell';
import type { CabinetUserSummary, CompanySummary } from './types';

const initialCompanies: CompanySummary[] = [
  { id: 1, name: 'Atlas Informatique', legal_name: 'Atlas Informatique SARL', tax_identifier: '1234567A', activity: 'Vente et services informatiques', sector: 'Technologies', country_code: 'TN', currency: 'TND', users_count: 3, access_role: 'cabinet_admin' },
  { id: 2, name: 'Bureau El Amen', legal_name: 'Bureau El Amen SARL', tax_identifier: '7654321B', activity: 'Services professionnels', sector: 'Conseil', country_code: 'TN', currency: 'TND', users_count: 2, access_role: 'cabinet_admin' },
  { id: 3, name: 'Carthage Négoce', legal_name: 'Carthage Négoce SARL', tax_identifier: '2345678C', activity: 'Commerce de gros', sector: 'Commerce', country_code: 'TN', currency: 'TND', users_count: 1, access_role: 'cabinet_admin' },
];

const initialUsers: CabinetUserSummary[] = [
  { id: 1, name: 'Alexandre Chen', email: 'alexandre@example.test', cabinet_role: 'cabinet_admin', companies: [] },
  { id: 2, name: 'Sonia Ben Salah', email: 'sonia@example.test', cabinet_role: 'member', companies: [{ id: 1, name: 'Atlas Informatique', role: 'invoice_manager' }, { id: 2, name: 'Bureau El Amen', role: 'company_user' }] },
];

export default function PreviewApp() {
  const [path, setPath] = useState(window.location.pathname);
  const [companies, setCompanies] = useState(initialCompanies);
  const [users] = useState(initialUsers);

  const navigate = (href: string) => {
    window.history.pushState({}, '', href);
    setPath(href);
  };

  const activeSection = path.includes('/accounting-data') ? 'accounting' : path === '/companies' ? 'companies' : path === '/cabinet/users' ? 'users' : 'overview';
  const title = activeSection === 'accounting' ? 'Données comptables' : activeSection === 'companies' ? 'Sociétés' : activeSection === 'users' ? 'Utilisateurs' : 'Vue d’ensemble';
  const companyId = Number(path.match(/\/companies\/(\d+)\/accounting-data/)?.[1]);
  const selectedCompany = companies.find((company) => company.id === companyId);

  return (
    <AppShell
      activeSection={activeSection}
      cabinetName="Cabinet de démonstration"
      canManageCabinet
      user={{ name: 'Alexandre Chen', email: 'alexandre@example.test' }}
      onNavigate={navigate}
    >
      <section className="mx-auto max-w-7xl space-y-6">
        <div>
          <p className="text-sm font-semibold uppercase tracking-[0.16em] text-teal-700">Aperçu interactif · données fictives</p>
          <h1 className="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{title}</h1>
          <p className="mt-2 text-sm text-slate-600">Les changements restent dans la mémoire du navigateur. Aucun document n’est envoyé à un serveur.</p>
        </div>

        {activeSection === 'overview' && (
          <>
            <div className="grid gap-4 sm:grid-cols-3">
              <Summary label="Sociétés gérées" value={companies.length} />
              <Summary label="Utilisateurs du cabinet" value={users.length} />
              <Summary label="Données Sage" value="Phase 2" />
            </div>
            <CompanyCards companies={companies} onNavigate={navigate} />
          </>
        )}

        {activeSection === 'companies' && (
          <>
            <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
              <div><h2 className="font-semibold text-slate-900">Sociétés du cabinet</h2><p className="mt-1 text-sm text-slate-500">Exemples de profils d’entreprise pour préparer le contexte comptable.</p></div>
              <button
                type="button"
                onClick={() => setCompanies((current) => [...current, {
                  id: Math.max(0, ...current.map((company) => company.id)) + 1,
                  name: `Nouvelle société ${current.length + 1}`,
                  legal_name: null,
                  tax_identifier: null,
                  activity: null,
                  sector: null,
                  country_code: 'TN',
                  currency: 'TND',
                  users_count: 0,
                  access_role: 'cabinet_admin',
                }])}
                className="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800"
              >Ajouter un exemple</button>
            </div>
            <CompanyCards companies={companies} onNavigate={navigate} />
          </>
        )}

        {activeSection === 'accounting' && selectedCompany && (
          <AccountingPreview company={selectedCompany} />
        )}

        {activeSection === 'users' && (
          <div className="grid gap-4 md:grid-cols-2">
            {users.map((user) => (
              <article key={user.id} className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div className="flex items-center gap-3"><span className="flex h-10 w-10 items-center justify-center rounded-full bg-teal-50 text-teal-700"><Users size={18} /></span><div><h2 className="font-semibold text-slate-900">{user.name}</h2><p className="text-sm text-slate-500">{user.email}</p></div></div>
                <p className="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-400">{user.cabinet_role === 'cabinet_admin' ? 'Administrateur du cabinet' : 'Accès société'}</p>
                <div className="mt-2 flex flex-wrap gap-2">
                  {user.cabinet_role === 'cabinet_admin' ? <span className="rounded bg-teal-50 px-2 py-1 text-xs text-teal-800">Toutes les sociétés</span> : user.companies.map((company) => <span key={company.id} className="rounded bg-slate-100 px-2 py-1 text-xs text-slate-700">{company.name} · {company.role}</span>)}
                </div>
              </article>
            ))}
          </div>
        )}
      </section>
    </AppShell>
  );
}

function AccountingPreview({ company }: { company: CompanySummary }) {
  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-start justify-between gap-4 rounded-xl border border-sky-200 bg-sky-50 p-5">
        <div>
          <p className="flex items-center gap-2 text-sm font-semibold text-sky-900"><BookOpenText size={17} /> Référentiels propres à {company.name}</p>
          <p className="mt-2 max-w-2xl text-sm leading-6 text-sky-800">Données comptables simulées pour illustrer le contexte Sage. Aucun fichier .mae n’a été fourni et aucun import réel n’a été effectué.</p>
        </div>
        <span className="rounded-full bg-white px-3 py-1 text-xs font-semibold text-sky-800">Exemple local</span>
      </div>
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <Summary label="Comptes généraux" value={12} />
        <Summary label="Comptes analytiques" value={3} />
        <Summary label="Tiers" value={3} />
        <Summary label="Journaux" value={4} />
        <Summary label="Écritures historiques" value={2} />
      </div>
      <div className="grid gap-4 xl:grid-cols-2">
        <article className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
          <header className="border-b border-slate-100 px-5 py-4"><h2 className="font-semibold text-slate-900">Plan comptable</h2><p className="mt-1 text-xs text-slate-500">Codes conservés sous forme de texte, y compris les zéros initiaux.</p></header>
          <table className="min-w-full text-left text-sm"><tbody className="divide-y divide-slate-100">
            {[
              ['401000', 'Fournisseurs'],
              ['411000', 'Clients'],
              ['445660', 'TVA déductible'],
              ['606400', 'Fournitures administratives'],
              ['000012', 'Compte auxiliaire de démonstration'],
            ].map(([code, label]) => <tr key={code}><td className="px-5 py-3 font-mono text-xs font-semibold text-slate-700">{code}</td><td className="px-5 py-3 text-slate-600">{label}</td></tr>)}
          </tbody></table>
        </article>
        <article className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
          <header className="border-b border-slate-100 px-5 py-4"><h2 className="font-semibold text-slate-900">Journaux et écritures</h2><p className="mt-1 text-xs text-slate-500">Historique équilibré, stocké par société.</p></header>
          <div className="divide-y divide-slate-100">
            <div className="px-5 py-4"><div className="flex justify-between gap-3"><strong className="font-mono text-sm text-slate-800">ACH-2026-001</strong><span className="text-xs text-slate-500">ACH · Achats</span></div><p className="mt-1 text-sm text-slate-600">Maintenance du parc informatique</p><p className="mt-2 text-xs font-medium text-emerald-700">Débit 119,000 {company.currency} · Crédit 119,000 {company.currency}</p></div>
            <div className="px-5 py-4"><div className="flex justify-between gap-3"><strong className="font-mono text-sm text-slate-800">VTE-2026-004</strong><span className="text-xs text-slate-500">VTE · Ventes</span></div><p className="mt-1 text-sm text-slate-600">Vente à Carthage Négoce</p><p className="mt-2 text-xs font-medium text-emerald-700">Débit 238,000 {company.currency} · Crédit 238,000 {company.currency}</p></div>
          </div>
        </article>
      </div>
    </div>
  );
}

function Summary({ label, value }: { label: string; value: string | number }) {
  return <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><p className="text-sm text-slate-500">{label}</p><p className="mt-2 text-2xl font-semibold text-slate-900">{value}</p></div>;
}

function CompanyCards({ companies, onNavigate }: { companies: CompanySummary[]; onNavigate: (href: string) => void }) {
  return (
    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
      {companies.map((company) => (
        <article key={company.id} className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-teal-50 text-teal-700"><Building2 size={19} /></span>
          <h2 className="mt-4 font-semibold text-slate-900">{company.name}</h2>
          <p className="mt-1 text-sm text-slate-500">{company.activity || 'Activité non renseignée'}</p>
          <div className="mt-4 flex justify-between border-t border-slate-100 pt-3 text-xs text-slate-500"><span>{company.tax_identifier || 'Matricule fiscal à renseigner'}</span><span>{company.currency}</span></div>
          <button type="button" onClick={() => onNavigate(`/companies/${company.id}/accounting-data`)} className="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-teal-700 hover:text-teal-900">Données comptables <ArrowRight size={15} /></button>
        </article>
      ))}
    </div>
  );
}

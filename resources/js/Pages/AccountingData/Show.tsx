import { Head, Link, router, useForm } from '@inertiajs/react';
import { BookOpenText, Building2, ContactRound, FileClock, Layers3, LoaderCircle, ScrollText } from 'lucide-react';
import { toast } from 'sonner';
import type { ReactNode } from 'react';
import AppShell from '../../Components/AppShell';
import type { SharedAuthProps } from '../../types';

type Company = {
  id: number;
  name: string;
  currency: string;
};

type ChartAccount = {
  id: number;
  code: string;
  label: string;
  account_type: string;
  is_active: boolean;
};

type AnalyticalAccount = {
  id: number;
  code: string;
  label: string;
  is_active: boolean;
};

type ThirdParty = {
  id: number;
  code: string;
  party_type: string;
  name: string;
  tax_identifier: string | null;
  is_active: boolean;
};

type Journal = {
  id: number;
  code: string;
  label: string;
  journal_type: string;
  is_active: boolean;
};

type JournalEntryLine = {
  line_number: number;
  account_code: string;
  account_label: string;
  third_party: string | null;
  analytical_account: string | null;
  description: string | null;
  debit: string;
  credit: string;
};

type JournalEntry = {
  id: number;
  reference: string;
  entry_date: string;
  description: string;
  source: string;
  journal: { code: string; label: string };
  lines: JournalEntryLine[];
};

type Paginated<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  from: number | null;
  to: number | null;
  total: number;
  prev_page_url: string | null;
  next_page_url: string | null;
};

type Props = {
  companies: { id: number; name: string }[];
  company: Company;
  chartAccounts: Paginated<ChartAccount>;
  analyticalAccounts: Paginated<AnalyticalAccount>;
  thirdParties: Paginated<ThirdParty>;
  journals: Paginated<Journal>;
  entries: Paginated<JournalEntry>;
  canLoadDemoData: boolean;
  auth?: SharedAuthProps;
};

const accountTypeLabels: Record<string, string> = {
  asset: 'Actif',
  liability: 'Passif',
  equity: 'Capitaux propres',
  expense: 'Charge',
  revenue: 'Produit',
  other: 'Autre',
};

const partyTypeLabels: Record<string, string> = {
  supplier: 'Fournisseur',
  customer: 'Client',
  both: 'Client et fournisseur',
};

export default function AccountingDataShow({
  companies,
  company,
  chartAccounts,
  analyticalAccounts,
  thirdParties,
  journals,
  entries,
  canLoadDemoData,
  auth,
}: Props) {
  const demoForm = useForm({});
  const formatNumber = new Intl.NumberFormat('fr-TN', {
    minimumFractionDigits: 3,
    maximumFractionDigits: 3,
  });
  const formatAmount = (amount: number | string) => `${formatNumber.format(Number(amount))} ${company.currency}`;
  const hasAnyData = chartAccounts.total > 0
    || analyticalAccounts.total > 0
    || thirdParties.total > 0
    || journals.total > 0
    || entries.total > 0;

  const loadDemoData = () => {
    demoForm.post(`/companies/${company.id}/accounting-data/demo`, {
      preserveScroll: true,
      onSuccess: () => toast.success('Les données comptables de démonstration ont été chargées.'),
      onError: () => toast.error('Le jeu de démonstration n’a pas pu être chargé. Réessayez.'),
    });
  };

  return (
    <AppShell
      activeSection="accounting"
      cabinetName={auth?.cabinet?.name}
      canManageCabinet={auth?.canManageCabinet}
      user={auth?.user}
      onLogout={auth?.user ? () => router.post('/logout', {}, {
        onSuccess: () => toast.success('Déconnexion réussie.'),
        onError: () => toast.error('La déconnexion a échoué. Réessayez.'),
      }) : undefined}
    >
      <Head title={`Données comptables · ${company.name}`} />
      <section className="mx-auto max-w-7xl space-y-6">
        <header className="flex flex-wrap items-end justify-between gap-4">
          <div>
            <p className="flex items-center gap-2 text-sm font-semibold uppercase tracking-[0.16em] text-teal-700">
              <Building2 size={16} /> {company.name}
            </p>
            <h1 className="mt-2 text-3xl font-semibold tracking-tight text-slate-900">Données comptables</h1>
            <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
              Référentiels et extraits historiques propres à cette société, préparés pour le contexte comptable des prochaines phases.
            </p>
          </div>
          <div className="flex flex-wrap items-end gap-3">
            <label className="block min-w-56 text-sm font-medium text-slate-700">
              Société consultée
              <select
                className="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                value={company.id}
                disabled={demoForm.processing}
                onChange={(event) => router.get('/accounting-data', { company_id: Number(event.target.value) }, { preserveScroll: true, replace: true })}
              >
                {companies.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
              </select>
            </label>
            {canLoadDemoData && (
              <button
                type="button"
                onClick={loadDemoData}
                disabled={demoForm.processing}
                className="inline-flex items-center gap-2 rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-60"
              >
                {demoForm.processing ? <LoaderCircle className="animate-spin" size={17} /> : <BookOpenText size={17} />}
                {demoForm.processing ? 'Chargement…' : 'Charger les données d’exemple'}
              </button>
            )}
          </div>
        </header>

        <div className="flex gap-3 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm leading-6 text-sky-950">
          <FileClock className="mt-0.5 shrink-0 text-sky-700" size={18} />
          <p>
            Cette phase stocke les données comptables dans l’application. Les exemples sont simulés à partir de conventions Sage et ne sont pas importés d’un fichier réel : aucun fichier <code className="rounded bg-white/70 px-1">.mae</code> n’a été fourni.
          </p>
        </div>

        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
          <SummaryCard icon={<BookOpenText size={17} />} label="Comptes généraux" value={chartAccounts.total} />
          <SummaryCard icon={<Layers3 size={17} />} label="Comptes analytiques" value={analyticalAccounts.total} />
          <SummaryCard icon={<ContactRound size={17} />} label="Tiers" value={thirdParties.total} />
          <SummaryCard icon={<ScrollText size={17} />} label="Journaux" value={journals.total} />
          <SummaryCard icon={<FileClock size={17} />} label="Écritures historiques" value={entries.total} />
        </div>

        {!hasAnyData && (
          <div className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center">
            <BookOpenText className="mx-auto text-slate-400" size={28} />
            <h2 className="mt-3 font-semibold text-slate-900">Aucune donnée comptable disponible</h2>
            <p className="mx-auto mt-1 max-w-xl text-sm leading-6 text-slate-500">
              Les comptes, tiers, journaux et écritures apparaîtront ici après leur import. En environnement local, un administrateur ou un gestionnaire peut charger un petit jeu de démonstration.
            </p>
          </div>
        )}

        {hasAnyData && (
          <>
            <div className="grid gap-5 xl:grid-cols-2">
              <DataSection title="Plan comptable" subtitle="Comptes généraux rattachés à la société" count={chartAccounts.total}>
                {chartAccounts.total ? (
                  <>
                    <div className="overflow-x-auto">
                      <table className="min-w-full text-left text-sm">
                        <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                          <tr><th className="px-4 py-3 font-semibold">Code</th><th className="px-4 py-3 font-semibold">Intitulé</th><th className="px-4 py-3 font-semibold">Nature</th><th className="px-4 py-3 font-semibold">Statut</th></tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                          {chartAccounts.data.map((account) => (
                            <tr key={account.id}>
                              <td className="whitespace-nowrap px-4 py-3 font-mono text-xs font-semibold text-slate-700">{account.code}</td>
                              <td className="px-4 py-3 text-slate-700">{account.label}</td>
                              <td className="px-4 py-3 text-slate-500">{accountTypeLabels[account.account_type] || account.account_type}</td>
                              <td className="px-4 py-3"><StatusBadge active={account.is_active} /></td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                    <DataPaginator page={chartAccounts} />
                  </>
                ) : <EmptyTable />}
              </DataSection>

              <DataSection title="Comptes analytiques" subtitle="Axes et centres analytiques" count={analyticalAccounts.total}>
                {analyticalAccounts.total ? (
                  <>
                    <div className="overflow-x-auto">
                      <table className="min-w-full text-left text-sm">
                        <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                          <tr><th className="px-4 py-3 font-semibold">Code</th><th className="px-4 py-3 font-semibold">Intitulé</th><th className="px-4 py-3 font-semibold">Statut</th></tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                          {analyticalAccounts.data.map((account) => (
                            <tr key={account.id}>
                              <td className="whitespace-nowrap px-4 py-3 font-mono text-xs font-semibold text-slate-700">{account.code}</td>
                              <td className="px-4 py-3 text-slate-700">{account.label}</td>
                              <td className="px-4 py-3"><StatusBadge active={account.is_active} /></td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                    <DataPaginator page={analyticalAccounts} />
                  </>
                ) : <EmptyTable />}
              </DataSection>

              <DataSection title="Tiers" subtitle="Clients et fournisseurs importés ou préparés" count={thirdParties.total}>
                {thirdParties.total ? (
                  <>
                    <div className="overflow-x-auto">
                      <table className="min-w-full text-left text-sm">
                        <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                          <tr><th className="px-4 py-3 font-semibold">Code</th><th className="px-4 py-3 font-semibold">Nom</th><th className="px-4 py-3 font-semibold">Type</th><th className="px-4 py-3 font-semibold">Matricule fiscal</th></tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                          {thirdParties.data.map((party) => (
                            <tr key={party.id}>
                              <td className="whitespace-nowrap px-4 py-3 font-mono text-xs font-semibold text-slate-700">{party.code}</td>
                              <td className="px-4 py-3 text-slate-700">{party.name}</td>
                              <td className="px-4 py-3 text-slate-500">{partyTypeLabels[party.party_type] || party.party_type}</td>
                              <td className="px-4 py-3 text-slate-500">{party.tax_identifier || '—'}</td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                    <DataPaginator page={thirdParties} />
                  </>
                ) : <EmptyTable />}
              </DataSection>

              <DataSection title="Journaux" subtitle="Journaux comptables disponibles pour la société" count={journals.total}>
                {journals.total ? (
                  <>
                    <div className="overflow-x-auto">
                      <table className="min-w-full text-left text-sm">
                        <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                          <tr><th className="px-4 py-3 font-semibold">Code</th><th className="px-4 py-3 font-semibold">Intitulé</th><th className="px-4 py-3 font-semibold">Type</th><th className="px-4 py-3 font-semibold">Statut</th></tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                          {journals.data.map((journal) => (
                            <tr key={journal.id}>
                              <td className="whitespace-nowrap px-4 py-3 font-mono text-xs font-semibold text-slate-700">{journal.code}</td>
                              <td className="px-4 py-3 text-slate-700">{journal.label}</td>
                              <td className="px-4 py-3 capitalize text-slate-500">{journal.journal_type}</td>
                              <td className="px-4 py-3"><StatusBadge active={journal.is_active} /></td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                    <DataPaginator page={journals} />
                  </>
                ) : <EmptyTable />}
              </DataSection>
            </div>

            <DataSection title="Historique des écritures" subtitle="Extrait des écritures comptables de cette société" count={entries.total}>
              {entries.total ? (
                <>
                  <div className="space-y-4 p-4 sm:p-5">
                    {entries.data.map((entry) => {
                      const debitMillimes = entry.lines.reduce((total, line) => total + Math.round(Number(line.debit) * 1000), 0);
                      const creditMillimes = entry.lines.reduce((total, line) => total + Math.round(Number(line.credit) * 1000), 0);
                      const isBalanced = debitMillimes === creditMillimes;

                      return (
                        <article key={entry.id} className="overflow-hidden rounded-lg border border-slate-200">
                          <header className="flex flex-wrap items-start justify-between gap-3 bg-slate-50 px-4 py-3">
                            <div>
                              <div className="flex flex-wrap items-center gap-2">
                                <span className="font-mono text-sm font-bold text-slate-800">{entry.reference}</span>
                                <span className="rounded bg-white px-2 py-0.5 text-xs font-medium text-slate-600">{entry.journal.code} · {entry.journal.label}</span>
                                <span className={`rounded px-2 py-0.5 text-xs font-semibold ${isBalanced ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'}`}>
                                  {isBalanced ? 'Équilibrée' : 'À vérifier'}
                                </span>
                              </div>
                              <p className="mt-1 text-xs text-slate-500">{formatDate(entry.entry_date)} · {entry.description}</p>
                            </div>
                            <span className="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs text-slate-500">
                              {entry.source === 'sage_demo' ? 'Exemple Sage simulé' : entry.source === 'sage_import' ? 'Import Sage' : 'Saisie interne'}
                            </span>
                          </header>
                          <div className="overflow-x-auto">
                            <table className="min-w-full text-left text-xs sm:text-sm">
                              <thead className="text-[11px] uppercase tracking-wide text-slate-500">
                                <tr><th className="px-3 py-2.5 font-semibold">Compte</th><th className="px-3 py-2.5 font-semibold">Intitulé / tiers</th><th className="px-3 py-2.5 text-right font-semibold">Débit</th><th className="px-3 py-2.5 text-right font-semibold">Crédit</th></tr>
                              </thead>
                              <tbody className="divide-y divide-slate-100">
                                {entry.lines.map((line) => (
                                  <tr key={`${entry.id}-${line.line_number}`}>
                                    <td className="whitespace-nowrap px-3 py-2 font-mono text-xs text-slate-700">{line.account_code}</td>
                                    <td className="min-w-48 px-3 py-2 text-slate-600">
                                      <span>{line.description || line.account_label}</span>
                                      <span className="mt-0.5 block text-[11px] text-slate-400">{line.account_label}{line.third_party ? ` · ${line.third_party}` : ''}{line.analytical_account ? ` · Analytique ${line.analytical_account}` : ''}</span>
                                    </td>
                                    <td className="whitespace-nowrap px-3 py-2 text-right tabular-nums text-slate-700">{Number(line.debit) ? formatAmount(line.debit) : '—'}</td>
                                    <td className="whitespace-nowrap px-3 py-2 text-right tabular-nums text-slate-700">{Number(line.credit) ? formatAmount(line.credit) : '—'}</td>
                                  </tr>
                                ))}
                              </tbody>
                              <tfoot className="border-t border-slate-200 bg-slate-50 font-semibold text-slate-700">
                                <tr>
                                  <td colSpan={2} className="px-3 py-2.5 text-right">Totaux</td>
                                  <td className="whitespace-nowrap px-3 py-2.5 text-right tabular-nums">{formatAmount(debitMillimes / 1000)}</td>
                                  <td className="whitespace-nowrap px-3 py-2.5 text-right tabular-nums">{formatAmount(creditMillimes / 1000)}</td>
                                </tr>
                              </tfoot>
                            </table>
                          </div>
                        </article>
                      );
                    })}
                  </div>
                  <DataPaginator page={entries} />
                </>
              ) : <EmptyTable label="Aucune écriture historique disponible." />}
            </DataSection>
          </>
        )}
      </section>
    </AppShell>
  );
}

function SummaryCard({ icon, label, value }: { icon: ReactNode; label: string; value: number }) {
  return (
    <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
      <div className="flex items-center gap-2 text-sm text-slate-500">{icon}<span>{label}</span></div>
      <p className="mt-3 text-2xl font-semibold text-slate-900">{value}</p>
    </div>
  );
}

function DataSection({ title, subtitle, count, children }: { title: string; subtitle: string; count: number; children: ReactNode }) {
  return (
    <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
      <header className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-4 sm:px-5">
        <div><h2 className="font-semibold text-slate-900">{title}</h2><p className="mt-0.5 text-xs text-slate-500">{subtitle}</p></div>
        <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{count}</span>
      </header>
      {children}
    </section>
  );
}

function DataPaginator({ page }: { page: Paginated<unknown> }) {
  if (page.last_page < 2) return null;

  const linkClass = 'rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-teal-300 hover:text-teal-800';
  const disabledClass = 'rounded-md border border-slate-100 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-400';

  return (
    <nav className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3" aria-label="Pagination des données comptables">
      <span className="text-xs text-slate-500">{page.from ?? 0}–{page.to ?? 0} sur {page.total}</span>
      <div className="flex items-center gap-2">
        {page.prev_page_url ? <Link href={page.prev_page_url} preserveScroll className={linkClass}>Précédent</Link> : <span className={disabledClass}>Précédent</span>}
        <span className="text-xs tabular-nums text-slate-500">Page {page.current_page} / {page.last_page}</span>
        {page.next_page_url ? <Link href={page.next_page_url} preserveScroll className={linkClass}>Suivant</Link> : <span className={disabledClass}>Suivant</span>}
      </div>
    </nav>
  );
}

function StatusBadge({ active }: { active: boolean }) {
  return <span className={`rounded-full px-2 py-1 text-[11px] font-semibold ${active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}>{active ? 'Actif' : 'Inactif'}</span>;
}

function EmptyTable({ label = 'Aucune donnée à afficher.' }: { label?: string }) {
  return <p className="px-4 py-8 text-center text-sm text-slate-500">{label}</p>;
}

function formatDate(date: string): string {
  const [year, month, day] = date.split('-').map(Number);
  return new Intl.DateTimeFormat('fr-TN', { dateStyle: 'medium' }).format(new Date(year, month - 1, day));
}

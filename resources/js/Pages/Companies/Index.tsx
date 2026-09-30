import { router, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { Building2, Plus } from 'lucide-react';
import { toast } from 'sonner';
import AppShell from '../../Components/AppShell';
import CompanyActivitySelect from '../../Components/CompanyActivitySelect';
import CompanyProfileCard from '../../Components/CompanyProfileCard';
import type { CompanySummary, SharedAuthProps } from '../../types';

type Props = {
  companies: CompanySummary[];
  canCreateCompany: boolean;
  canManageActivities: boolean;
  activities: string[];
  auth?: SharedAuthProps;
};

type CompanyForm = {
  name: string;
  legal_name: string;
  tax_identifier: string;
  activity: string;
  sector: string;
  country_code: string;
  currency: string;
  vat_rates: string;
  fiscal_year_start: string;
  fiscal_year_end: string;
  capitalization_threshold: string;
};

const inputClass = 'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-teal-600 focus:ring-2 focus:ring-teal-100';

export default function CompaniesIndex({ companies, canCreateCompany, canManageActivities, activities, auth }: Props) {
  const form = useForm<CompanyForm>({
    name: '',
    legal_name: '',
    tax_identifier: '',
    activity: '',
    sector: '',
    country_code: 'TN',
    currency: 'TND',
    vat_rates: '',
    fiscal_year_start: '',
    fiscal_year_end: '',
    capitalization_threshold: '',
  });

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    form.transform((data) => ({ ...data, vat_rates: data.vat_rates.split(/[;,\s]+/).map((rate) => rate.trim()).filter(Boolean) }));
    form.post('/companies', {
      preserveScroll: true,
      onSuccess: () => {
        form.reset();
        toast.success('Société ajoutée au cabinet.');
      },
      onError: () => toast.error('La société n’a pas pu être créée. Vérifiez les champs indiqués.'),
    });
  };

  return (
    <AppShell
      activeSection="companies"
      cabinetName={auth?.cabinet?.name}
      canManageCabinet={auth?.canManageCabinet}
      user={auth?.user}
      onLogout={auth?.user ? () => router.post('/logout', {}, {
        onSuccess: () => toast.success('Déconnexion réussie.'),
        onError: () => toast.error('La déconnexion a échoué. Réessayez.'),
      }) : undefined}
    >
      <section className="mx-auto max-w-7xl space-y-6">
        <header>
          <p className="text-sm font-semibold uppercase tracking-[0.16em] text-teal-700">Cabinet</p>
          <h1 className="mt-2 text-3xl font-semibold tracking-tight text-slate-900">Sociétés</h1>
          <p className="mt-2 text-sm text-slate-600">Consultez les sociétés auxquelles vous avez accès et leur profil de base.</p>
        </header>

        {canCreateCompany && (
          <details className="group rounded-xl border border-slate-200 bg-white shadow-sm" open={companies.length === 0}>
            <summary className="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 [&::-webkit-details-marker]:hidden">
              <span className="flex items-center gap-2 font-semibold text-slate-900"><Plus size={18} className="text-teal-700" /> Ajouter une société</span>
              <span className="text-xs text-slate-500 group-open:hidden">Ouvrir le formulaire</span>
              <span className="hidden text-xs text-slate-500 group-open:block">Fermer</span>
            </summary>
            <form onSubmit={submit} className="grid gap-4 border-t border-slate-100 p-5 md:grid-cols-2">
              <Field label="Nom d’usage" error={form.errors.name}>
                <input className={inputClass} value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required maxLength={160} />
              </Field>
              <Field label="Raison sociale" error={form.errors.legal_name}>
                <input className={inputClass} value={form.data.legal_name} onChange={(event) => form.setData('legal_name', event.target.value)} maxLength={190} />
              </Field>
              <Field label="Matricule fiscal" error={form.errors.tax_identifier}>
                <input className={inputClass} value={form.data.tax_identifier} onChange={(event) => form.setData('tax_identifier', event.target.value)} maxLength={80} />
              </Field>
              <div>
                <label htmlFor="new-company-activity" className="block text-sm font-medium text-slate-700">Activité principale</label>
                <CompanyActivitySelect id="new-company-activity" value={form.data.activity} activities={activities} canAdd={canManageActivities} onChange={(activity) => form.setData('activity', activity)} />
                {form.errors.activity && <p className="mt-1 text-xs text-red-700">{form.errors.activity}</p>}
              </div>
              <Field label="Secteur" error={form.errors.sector}>
                <input className={inputClass} value={form.data.sector} onChange={(event) => form.setData('sector', event.target.value)} maxLength={190} />
              </Field>
              <div className="grid grid-cols-2 gap-3">
                <Field label="Pays (ISO)" error={form.errors.country_code}>
                  <input className={inputClass} value={form.data.country_code} onChange={(event) => form.setData('country_code', event.target.value.toUpperCase())} maxLength={2} />
                </Field>
                <Field label="Devise (ISO)" error={form.errors.currency}>
                  <input className={inputClass} value={form.data.currency} onChange={(event) => form.setData('currency', event.target.value.toUpperCase())} maxLength={3} />
                </Field>
              </div>
              <PolicyFields data={form.data} setData={form.setData} errors={form.errors} />
              <div className="flex items-center justify-end gap-3 md:col-span-2">
                {form.errors.name && <p className="mr-auto text-sm text-red-700">{form.errors.name}</p>}
                <button type="submit" disabled={form.processing} className="rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-60">
                  {form.processing ? 'Enregistrement…' : 'Créer la société'}
                </button>
              </div>
            </form>
          </details>
        )}

        {companies.length ? (
          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {companies.map((company) => (
              <CompanyProfileCard key={company.id} company={company} canEdit={canCreateCompany} activities={activities} canManageActivities={canManageActivities} />
            ))}
          </div>
        ) : (
          <div className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
            <Building2 className="mx-auto text-slate-400" size={28} />
            <h2 className="mt-3 font-semibold text-slate-900">Aucune société accessible</h2>
            <p className="mt-1 text-sm text-slate-500">Un administrateur du cabinet peut créer une société ou vous accorder un accès.</p>
          </div>
        )}
      </section>
    </AppShell>
  );
}

function PolicyFields({ data, setData, errors }: { data: CompanyForm; setData: (key: keyof CompanyForm, value: string) => void; errors: Record<string, string> }) {
  return <>
    <Field label="Taux TVA autorisés (%) — séparés par des virgules" error={errors['vat_rates.0'] || errors.vat_rates}>
      <input className={inputClass} value={data.vat_rates} onChange={(event) => setData('vat_rates', event.target.value)} placeholder="0, 7, 13, 19" />
    </Field>
    <div className="grid grid-cols-2 gap-3">
      <Field label="Exercice fiscal · début" error={errors.fiscal_year_start}><input type="date" className={inputClass} value={data.fiscal_year_start} onChange={(event) => setData('fiscal_year_start', event.target.value)} /></Field>
      <Field label="Exercice fiscal · fin" error={errors.fiscal_year_end}><input type="date" className={inputClass} value={data.fiscal_year_end} onChange={(event) => setData('fiscal_year_end', event.target.value)} /></Field>
    </div>
    <Field label="Seuil d’immobilisation (DT) / Capitalization Threshold" error={errors.capitalization_threshold}><input type="number" min="0" step="0.001" className={inputClass} value={data.capitalization_threshold} onChange={(event) => setData('capitalization_threshold', event.target.value)} placeholder="Ex. 1000.000" /></Field>
  </>;
}

function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
  return (
    <label className="block text-sm font-medium text-slate-700">
      {label}
      {children}
      {error && <span className="mt-1 block text-xs font-normal text-red-700">{error}</span>}
    </label>
  );
}

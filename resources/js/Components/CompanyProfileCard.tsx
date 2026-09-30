import { Link, useForm } from '@inertiajs/react';
import { BookOpenText, Building2, FileText, PencilLine, Users } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { toast } from 'sonner';
import CompanyActivitySelect from './CompanyActivitySelect';
import type { CompanySummary } from '../types';

type Props = {
  company: CompanySummary;
  canEdit: boolean;
  activities: string[];
  canManageActivities: boolean;
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

export default function CompanyProfileCard({ company, canEdit, activities, canManageActivities }: Props) {
  const form = useForm<CompanyForm>({
    name: company.name,
    legal_name: company.legal_name || '',
    tax_identifier: company.tax_identifier || '',
    activity: company.activity || '',
    sector: company.sector || '',
    country_code: company.country_code || '',
    currency: company.currency || '',
    vat_rates: company.vat_rates?.join(', ') || '',
    fiscal_year_start: company.fiscal_year_start || '',
    fiscal_year_end: company.fiscal_year_end || '',
    capitalization_threshold: company.capitalization_threshold || '',
  });

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    form.transform((data) => ({ ...data, vat_rates: data.vat_rates.split(/[;,\s]+/).map((rate) => rate.trim()).filter(Boolean) }));
    form.put(`/companies/${company.id}`, {
      preserveScroll: true,
      onSuccess: () => toast.success('Profil de la société mis à jour.'),
      onError: () => toast.error('Le profil n’a pas pu être enregistré. Vérifiez les champs indiqués.'),
    });
  };

  return (
    <article className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
      <div className="flex items-center justify-between">
        <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-teal-50 text-teal-700"><Building2 size={19} /></span>
        <span className="inline-flex items-center gap-1.5 text-xs text-slate-500"><Users size={14} /> {company.users_count} membre{company.users_count > 1 ? 's' : ''} affecté{company.users_count > 1 ? 's' : ''}</span>
      </div>
      <h2 className="mt-4 font-semibold text-slate-900">{company.name}</h2>
      <p className="mt-1 text-sm text-slate-500">{company.legal_name || 'Raison sociale non renseignée'}</p>
      <dl className="mt-4 space-y-2 border-t border-slate-100 pt-4 text-sm">
        <div className="flex justify-between gap-3"><dt className="text-slate-500">Matricule fiscal</dt><dd className="text-right font-medium text-slate-700">{company.tax_identifier || '—'}</dd></div>
        <div className="flex justify-between gap-3"><dt className="text-slate-500">Activité</dt><dd className="text-right font-medium text-slate-700">{company.activity || '—'}</dd></div>
        <div className="flex justify-between gap-3"><dt className="text-slate-500">Devise</dt><dd className="text-right font-medium text-slate-700">{company.currency || 'À configurer'}</dd></div>
        <div className="flex justify-between gap-3"><dt className="text-slate-500">Taux TVA</dt><dd className="text-right font-medium text-slate-700">{company.vat_rates?.length ? `${company.vat_rates.join(' %, ')} %` : 'À configurer'}</dd></div>
        <div className="flex justify-between gap-3"><dt className="text-slate-500">Exercice fiscal</dt><dd className="text-right font-medium text-slate-700">{company.fiscal_year_start && company.fiscal_year_end ? `${company.fiscal_year_start} – ${company.fiscal_year_end}` : 'À configurer'}</dd></div>
        <div className="flex justify-between gap-3"><dt className="text-slate-500">Seuil d’immobilisation</dt><dd className="text-right font-medium text-slate-700">{company.capitalization_threshold ? `${company.capitalization_threshold} DT` : 'À configurer'}</dd></div>
      </dl>

      <div className="mt-4 flex flex-wrap gap-2">
        <Link
          href={`/companies/${company.id}/accounting-data`}
          className="inline-flex items-center gap-2 rounded-lg bg-teal-50 px-3 py-2 text-sm font-semibold text-teal-800 transition hover:bg-teal-100"
        >
          <BookOpenText size={16} /> Données comptables
        </Link>
        <Link
          href={`/companies/${company.id}/invoices`}
          className="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-teal-300 hover:bg-teal-50 hover:text-teal-800"
        >
          <FileText size={16} /> Factures
        </Link>
      </div>

      {canEdit && (
        <details className="group mt-4 border-t border-slate-100 pt-3">
          <summary className="flex cursor-pointer list-none items-center gap-2 text-sm font-semibold text-teal-700 [&::-webkit-details-marker]:hidden">
            <PencilLine size={15} /> Modifier le profil
          </summary>
          <form onSubmit={submit} className="mt-4 space-y-3">
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
              <label htmlFor={`company-${company.id}-activity`} className="block text-sm font-medium text-slate-700">Activité principale</label>
              <CompanyActivitySelect id={`company-${company.id}-activity`} value={form.data.activity} activities={activities} canAdd={canManageActivities} onChange={(activity) => form.setData('activity', activity)} />
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
            <Field label="Taux TVA autorisés (%) — séparés par des virgules" error={form.errors.vat_rates || (form.errors as Record<string, string | undefined>)['vat_rates.0']}><input className={inputClass} value={form.data.vat_rates} onChange={(event) => form.setData('vat_rates', event.target.value)} placeholder="0, 7, 13, 19" /></Field>
            <div className="grid grid-cols-2 gap-3">
              <Field label="Exercice fiscal · début" error={form.errors.fiscal_year_start}><input type="date" className={inputClass} value={form.data.fiscal_year_start} onChange={(event) => form.setData('fiscal_year_start', event.target.value)} /></Field>
              <Field label="Exercice fiscal · fin" error={form.errors.fiscal_year_end}><input type="date" className={inputClass} value={form.data.fiscal_year_end} onChange={(event) => form.setData('fiscal_year_end', event.target.value)} /></Field>
            </div>
            <Field label="Seuil d’immobilisation (DT) / Capitalization Threshold" error={form.errors.capitalization_threshold}><input type="number" min="0" step="0.001" className={inputClass} value={form.data.capitalization_threshold} onChange={(event) => form.setData('capitalization_threshold', event.target.value)} placeholder="Ex. 1000.000" /></Field>
            <button type="submit" disabled={form.processing} className="w-full rounded-lg border border-teal-700 px-3 py-2 text-sm font-semibold text-teal-800 hover:bg-teal-50 disabled:opacity-60">
              {form.processing ? 'Enregistrement…' : 'Enregistrer le profil'}
            </button>
          </form>
        </details>
      )}
    </article>
  );
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

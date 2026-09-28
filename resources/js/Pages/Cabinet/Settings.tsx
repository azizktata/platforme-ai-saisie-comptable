import { Head, router, useForm } from '@inertiajs/react';
import { Building2, Save } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { toast } from 'sonner';
import AppShell from '../../Components/AppShell';
import type { SharedAuthProps } from '../../types';

type Props = {
  cabinet: { id: number; name: string; slug: string };
  auth?: SharedAuthProps;
};

type CabinetForm = { name: string; slug: string };
const inputClass = 'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-teal-600 focus:ring-2 focus:ring-teal-100';

export default function CabinetSettings({ cabinet, auth }: Props) {
  const form = useForm<CabinetForm>({ name: cabinet.name, slug: cabinet.slug });

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    form.patch('/cabinet/settings', {
      preserveScroll: true,
      onSuccess: () => toast.success('Les informations du cabinet ont été mises à jour.'),
      onError: () => toast.error('Les informations du cabinet n’ont pas pu être enregistrées.'),
    });
  };

  return (
    <AppShell
      activeSection="cabinet"
      cabinetName={auth?.cabinet?.name}
      canManageCabinet={auth?.canManageCabinet}
      user={auth?.user}
      onLogout={auth?.user ? () => router.post('/logout', {}, {
        onSuccess: () => toast.success('Déconnexion réussie.'),
        onError: () => toast.error('La déconnexion a échoué. Réessayez.'),
      }) : undefined}
    >
      <Head title="Paramètres du cabinet" />
      <section className="mx-auto max-w-4xl space-y-6">
        <header>
          <p className="text-sm font-semibold uppercase tracking-[0.16em] text-teal-700">Administration</p>
          <h1 className="mt-2 text-3xl font-semibold tracking-tight text-slate-900">Informations du cabinet</h1>
          <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-600">Mettez à jour le nom affiché dans l’espace de travail et l’identifiant unique du cabinet.</p>
        </header>

        <form onSubmit={submit} className="rounded-xl border border-slate-200 bg-white shadow-sm">
          <div className="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
            <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-teal-50 text-teal-700"><Building2 size={19} /></span>
            <div>
              <h2 className="font-semibold text-slate-900">Profil du cabinet</h2>
              <p className="mt-0.5 text-xs text-slate-500">Cabinet #{cabinet.id}</p>
            </div>
          </div>
          <div className="grid gap-5 p-5 md:grid-cols-2">
            <Field label="Nom du cabinet" error={form.errors.name}>
              <input className={inputClass} value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} maxLength={255} required />
            </Field>
            <Field label="Identifiant unique" error={form.errors.slug}>
              <input className={inputClass} value={form.data.slug} onChange={(event) => form.setData('slug', event.target.value.toLowerCase().replace(/[^a-z0-9_-]/g, '-'))} maxLength={190} required aria-describedby="cabinet-slug-help" />
              <span id="cabinet-slug-help" className="mt-1 block text-xs font-normal text-slate-500">Lettres sans accent, chiffres, tirets et underscores uniquement.</span>
            </Field>
            <div className="flex justify-end md:col-span-2">
              <button type="submit" disabled={form.processing} className="inline-flex items-center gap-2 rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-60">
                <Save size={16} /> {form.processing ? 'Enregistrement…' : 'Enregistrer les modifications'}
              </button>
            </div>
          </div>
        </form>
      </section>
    </AppShell>
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

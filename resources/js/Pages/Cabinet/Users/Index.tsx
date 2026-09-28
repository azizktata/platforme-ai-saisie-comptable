import { router, useForm } from '@inertiajs/react';
import { KeyRound, ShieldCheck, UserPlus, Users } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { toast } from 'sonner';
import AppShell from '../../../Components/AppShell';
import type { CabinetUserSummary, SelectableCompany, SharedAuthProps } from '../../../types';

type CompanyRole = 'invoice_manager' | 'company_user';
type CompanyAccessInput = { company_id: number; role: CompanyRole };
type UserForm = {
  name: string;
  email: string;
  password: string;
  cabinet_role: 'member' | 'cabinet_admin';
  company_access: CompanyAccessInput[];
};
type Props = {
  users: CabinetUserSummary[];
  companies: SelectableCompany[];
  auth?: SharedAuthProps;
};

const inputClass = 'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-teal-600 focus:ring-2 focus:ring-teal-100';

export default function CabinetUsersIndex({ users, companies, auth }: Props) {
  const form = useForm<UserForm>({
    name: '',
    email: '',
    password: '',
    cabinet_role: 'member',
    company_access: [],
  });

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    form.post('/cabinet/users', {
      preserveScroll: true,
      onSuccess: () => {
        form.reset();
        toast.success('Utilisateur ajouté au cabinet.');
      },
      onError: () => toast.error('L’utilisateur n’a pas pu être créé. Vérifiez les champs indiqués.'),
    });
  };

  const toggleCompany = (companyId: number, selected: boolean) => {
    const current = form.data.company_access.filter((access) => access.company_id !== companyId);
    form.setData('company_access', selected ? [...current, { company_id: companyId, role: 'company_user' }] : current);
  };

  const updateCompanyRole = (companyId: number, role: CompanyRole) => {
    form.setData('company_access', form.data.company_access.map((access) => (
      access.company_id === companyId ? { ...access, role } : access
    )));
  };

  return (
    <AppShell
      activeSection="users"
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
          <p className="text-sm font-semibold uppercase tracking-[0.16em] text-teal-700">Administration</p>
          <h1 className="mt-2 text-3xl font-semibold tracking-tight text-slate-900">Utilisateurs du cabinet</h1>
          <p className="mt-2 text-sm text-slate-600">Créez des comptes et attribuez des rôles d’accès société par société.</p>
        </header>

        <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(320px,0.8fr)]">
          <form onSubmit={submit} className="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex items-center gap-2 border-b border-slate-100 pb-4">
              <UserPlus className="text-teal-700" size={19} />
              <h2 className="font-semibold text-slate-900">Ajouter un utilisateur</h2>
            </div>
            <Field label="Nom complet" error={form.errors.name}>
              <input className={inputClass} value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} autoComplete="name" required />
            </Field>
            <Field label="Adresse e-mail" error={form.errors.email}>
              <input className={inputClass} type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} autoComplete="email" required />
            </Field>
            <Field label="Mot de passe initial (12 caractères minimum)" error={form.errors.password}>
              <input className={inputClass} type="password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} autoComplete="new-password" minLength={12} required />
            </Field>
            <Field label="Rôle cabinet" error={form.errors.cabinet_role}>
              <select
                className={inputClass}
                value={form.data.cabinet_role}
                onChange={(event) => {
                  const role = event.target.value as UserForm['cabinet_role'];
                  form.setData('cabinet_role', role);
                  if (role === 'cabinet_admin') form.setData('company_access', []);
                }}
              >
                <option value="member">Collaborateur</option>
                <option value="cabinet_admin">Administrateur du cabinet</option>
              </select>
            </Field>

            {form.data.cabinet_role === 'member' && (
              <fieldset className="space-y-3 rounded-lg border border-slate-200 p-4">
                <legend className="px-1 text-sm font-medium text-slate-700">Accès aux sociétés</legend>
                {companies.length ? (
                  <>
                    <div className="max-h-56 space-y-2 overflow-auto">
                      {companies.map((company) => {
                        const access = form.data.company_access.find((item) => item.company_id === company.id);

                        return (
                          <div key={company.id} className="flex flex-wrap items-center justify-between gap-3 rounded-md px-2 py-1.5 hover:bg-slate-50">
                            <label className="flex min-w-40 flex-1 items-center gap-3 text-sm text-slate-700">
                              <input
                                type="checkbox"
                                checked={Boolean(access)}
                                onChange={(event) => toggleCompany(company.id, event.target.checked)}
                                className="h-4 w-4 rounded border-slate-300 text-teal-700 focus:ring-teal-600"
                              />
                              {company.name}
                            </label>
                            {access && (
                              <select
                                aria-label={`Rôle pour ${company.name}`}
                                className="rounded-md border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-700"
                                value={access.role}
                                onChange={(event) => updateCompanyRole(company.id, event.target.value as CompanyRole)}
                              >
                                <option value="company_user">Consultation / revue</option>
                                <option value="invoice_manager">Gestionnaire de factures</option>
                              </select>
                            )}
                          </div>
                        );
                      })}
                    </div>
                    {form.errors.company_access && <p className="text-xs text-red-700">{form.errors.company_access}</p>}
                  </>
                ) : (
                  <p className="text-sm text-slate-500">Créez d’abord une société avant d’attribuer un accès.</p>
                )}
              </fieldset>
            )}

            <button type="submit" disabled={form.processing} className="inline-flex items-center gap-2 rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-60">
              <UserPlus size={16} /> {form.processing ? 'Création…' : 'Créer le compte'}
            </button>
          </form>

          <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex items-center gap-2 border-b border-slate-100 pb-4">
              <Users className="text-teal-700" size={19} />
              <h2 className="font-semibold text-slate-900">Membres ({users.length})</h2>
            </div>
            <div className="divide-y divide-slate-100">
              {users.map((member) => (
                <article key={member.id} className="py-4 first:pt-4 last:pb-0">
                  <div className="flex items-start justify-between gap-3">
                    <div>
                      <h3 className="font-medium text-slate-900">{member.name}</h3>
                      <p className="mt-0.5 text-sm text-slate-500">{member.email}</p>
                    </div>
                    <span className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">
                      {member.cabinet_role === 'cabinet_admin' ? <ShieldCheck size={13} /> : <KeyRound size={13} />}
                      {member.cabinet_role === 'cabinet_admin' ? 'Admin cabinet' : 'Collaborateur'}
                    </span>
                  </div>
                  <p className="mt-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Accès sociétés</p>
                  <div className="mt-1 flex flex-wrap gap-1.5">
                    {member.cabinet_role === 'cabinet_admin' ? (
                      <span className="rounded-md bg-teal-50 px-2 py-1 text-xs text-teal-800">Toutes les sociétés du cabinet</span>
                    ) : member.companies.length ? member.companies.map((company) => (
                      <span key={company.id} className="rounded-md bg-slate-100 px-2 py-1 text-xs text-slate-700">
                        {company.name} · {company.role === 'invoice_manager' ? 'Gestion' : 'Consultation'}
                      </span>
                    )) : <span className="text-xs text-slate-500">Aucun accès affecté</span>}
                  </div>
                  <MemberAccessEditor key={member.id} member={member} companies={companies} />
                </article>
              ))}
            </div>
          </section>
        </div>
      </section>
    </AppShell>
  );
}

function MemberAccessEditor({ member, companies }: { member: CabinetUserSummary; companies: SelectableCompany[] }) {
  const form = useForm<UserForm>({
    name: member.name,
    email: member.email,
    password: '',
    cabinet_role: member.cabinet_role === 'cabinet_admin' ? 'cabinet_admin' : 'member',
    company_access: member.companies.map((company) => ({
      company_id: company.id,
      role: company.role === 'invoice_manager' ? 'invoice_manager' : 'company_user',
    })),
  });

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    form.put(`/cabinet/users/${member.id}`, {
      preserveScroll: true,
      onSuccess: () => toast.success('Accès utilisateur mis à jour.'),
      onError: () => toast.error('Les accès n’ont pas pu être enregistrés. Vérifiez les champs indiqués.'),
    });
  };

  const toggleCompany = (companyId: number, selected: boolean) => {
    const current = form.data.company_access.filter((access) => access.company_id !== companyId);
    form.setData('company_access', selected ? [...current, { company_id: companyId, role: 'company_user' }] : current);
  };

  const updateCompanyRole = (companyId: number, role: CompanyRole) => {
    form.setData('company_access', form.data.company_access.map((access) => (
      access.company_id === companyId ? { ...access, role } : access
    )));
  };

  return (
    <details className="group mt-4 border-t border-slate-100 pt-3">
      <summary className="cursor-pointer list-none text-sm font-semibold text-teal-700 [&::-webkit-details-marker]:hidden">Modifier le compte et les accès</summary>
      <form onSubmit={submit} className="mt-4 space-y-3">
        <Field label="Nom" error={form.errors.name}>
          <input className={inputClass} value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required />
        </Field>
        <Field label="E-mail" error={form.errors.email}>
          <input className={inputClass} type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} required />
        </Field>
        <Field label="Nouveau mot de passe (facultatif, 12 caractères minimum)" error={form.errors.password}>
          <input className={inputClass} type="password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} minLength={12} autoComplete="new-password" />
        </Field>
        <Field label="Rôle cabinet" error={form.errors.cabinet_role}>
          <select
            className={inputClass}
            value={form.data.cabinet_role}
            onChange={(event) => {
              const role = event.target.value as UserForm['cabinet_role'];
              form.setData('cabinet_role', role);
              if (role === 'cabinet_admin') form.setData('company_access', []);
            }}
          >
            <option value="member">Collaborateur</option>
            <option value="cabinet_admin">Administrateur du cabinet</option>
          </select>
        </Field>
        {form.data.cabinet_role === 'member' && (
          <fieldset className="space-y-2 rounded-lg border border-slate-200 p-3">
            <legend className="px-1 text-xs font-medium text-slate-600">Accès société</legend>
            {companies.map((company) => {
              const access = form.data.company_access.find((item) => item.company_id === company.id);

              return (
                <div key={company.id} className="flex flex-wrap items-center justify-between gap-2">
                  <label className="flex flex-1 items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" checked={Boolean(access)} onChange={(event) => toggleCompany(company.id, event.target.checked)} className="h-4 w-4 rounded border-slate-300 text-teal-700 focus:ring-teal-600" />
                    {company.name}
                  </label>
                  {access && (
                    <select aria-label={`Rôle pour ${company.name}`} className="rounded-md border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-700" value={access.role} onChange={(event) => updateCompanyRole(company.id, event.target.value as CompanyRole)}>
                      <option value="company_user">Consultation / revue</option>
                      <option value="invoice_manager">Gestionnaire de factures</option>
                    </select>
                  )}
                </div>
              );
            })}
            {form.errors.company_access && <p className="text-xs text-red-700">{form.errors.company_access}</p>}
          </fieldset>
        )}
        <button type="submit" disabled={form.processing} className="rounded-lg border border-teal-700 px-3 py-2 text-sm font-semibold text-teal-800 hover:bg-teal-50 disabled:opacity-60">
          {form.processing ? 'Enregistrement…' : 'Enregistrer les accès'}
        </button>
      </form>
    </details>
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

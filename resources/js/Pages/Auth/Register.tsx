import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowRight, Building2, Eye, EyeOff, LockKeyhole, UserRound } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { toast } from 'sonner';

type RegistrationFields = {
  cabinet_name: string;
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
};

export default function Register() {
  const form = useForm<RegistrationFields>({
    cabinet_name: '',
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
  });
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmation, setShowConfirmation] = useState(false);

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    form.post('/register', {
      onSuccess: () => toast.success('Le cabinet a été créé. Vous êtes connecté en tant qu’administrateur.'),
      onError: () => toast.error('La création du cabinet a échoué. Vérifiez les informations saisies.'),
    });
  };

  return (
    <div className="login-page">
      <Head title="Créer un cabinet · ComptaFlow" />
      <aside className="login-aside">
        <div className="login-brand">
          <div className="brand-mark"><span>c</span><i /></div>
          <div><div className="brand-name">compta<span>flow</span></div><div className="brand-caption">GESTION COMPTABLE</div></div>
        </div>
        <div className="login-aside-content">
          <div className="login-eyebrow"><span /> DÉMARRER VOTRE ESPACE</div>
          <h1>Votre cabinet.<br /><em>Votre espace.</em></h1>
          <p>Créez un espace privé pour organiser vos sociétés, inviter votre équipe et suivre les documents comptables.</p>
          <div className="login-preview-card">
            <div className="login-preview-top"><span>Un espace séparé</span><span className="login-live"><i /> Privé</span></div>
            <div className="login-preview-row"><div className="login-preview-icon"><Building2 size={15} /></div><div><strong>Un cabinet</strong><span>Vos sociétés et paramètres regroupés</span></div></div>
            <div className="login-preview-row"><div className="login-preview-icon login-preview-icon--amber"><UserRound size={15} /></div><div><strong>Un administrateur</strong><span>Vous gérez les accès de votre équipe</span></div></div>
            <div className="login-preview-footer"><div className="login-mini-stat"><strong>1</strong><span>espace indépendant</span></div><div className="login-mini-divider" /><div className="login-mini-stat"><strong>∞</strong><span>possibilités d’organisation</span></div></div>
          </div>
        </div>
        <div className="login-aside-footer"><span>Un suivi clair, du document à l’écriture.</span><span>© {new Date().getFullYear()} ComptaFlow</span></div>
      </aside>

      <main className="login-main">
        <div className="login-mobile-brand">
          <div className="brand-mark"><span>c</span><i /></div><div className="brand-name">compta<span>flow</span></div>
        </div>
        <div className="login-card-wrap">
          <div className="login-card-heading">
            <div className="login-lock"><Building2 size={19} /></div>
            <div className="login-eyebrow login-eyebrow--light">NOUVEAU CABINET</div>
            <h2>Créer votre espace</h2>
            <p>Vous deviendrez administrateur du cabinet.</p>
          </div>
          <form className="login-form" onSubmit={submit}>
            <label className="login-field">
              <span>Nom du cabinet</span>
              <input autoComplete="organization" placeholder="Cabinet Conseil" value={form.data.cabinet_name} onChange={(event) => form.setData('cabinet_name', event.target.value)} aria-invalid={Boolean(form.errors.cabinet_name)} />
              {form.errors.cabinet_name && <small className="login-field-error">{form.errors.cabinet_name}</small>}
            </label>
            <label className="login-field">
              <span>Votre nom</span>
              <input autoComplete="name" placeholder="Nom et prénom" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} aria-invalid={Boolean(form.errors.name)} />
              {form.errors.name && <small className="login-field-error">{form.errors.name}</small>}
            </label>
            <label className="login-field">
              <span>Adresse e-mail</span>
              <input type="email" autoComplete="email" placeholder="vous@entreprise.tn" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} aria-invalid={Boolean(form.errors.email)} />
              {form.errors.email && <small className="login-field-error">{form.errors.email}</small>}
            </label>
            <label className="login-field">
              <span>Mot de passe <small>(12 caractères minimum)</small></span>
              <div className="login-password-wrap">
                <input type={showPassword ? 'text' : 'password'} autoComplete="new-password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} aria-invalid={Boolean(form.errors.password)} />
                <button type="button" className="password-toggle" aria-label={showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'} onClick={() => setShowPassword((visible) => !visible)}>
                  {showPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                </button>
              </div>
              {form.errors.password && <small className="login-field-error">{form.errors.password}</small>}
            </label>
            <label className="login-field">
              <span>Confirmer le mot de passe</span>
              <div className="login-password-wrap">
                <input type={showConfirmation ? 'text' : 'password'} autoComplete="new-password" value={form.data.password_confirmation} onChange={(event) => form.setData('password_confirmation', event.target.value)} aria-invalid={Boolean(form.errors.password_confirmation)} />
                <button type="button" className="password-toggle" aria-label={showConfirmation ? 'Masquer la confirmation' : 'Afficher la confirmation'} onClick={() => setShowConfirmation((visible) => !visible)}>
                  {showConfirmation ? <EyeOff size={16} /> : <Eye size={16} />}
                </button>
              </div>
              {form.errors.password_confirmation && <small className="login-field-error">{form.errors.password_confirmation}</small>}
            </label>
            <button className="button button--primary login-submit" disabled={form.processing}>
              {form.processing ? <span className="button-spinner" /> : <>Créer mon cabinet <ArrowRight size={16} /></>}
            </button>
          </form>
          <div className="login-security"><LockKeyhole size={13} /><span>Votre compte sera l’administrateur de ce nouvel espace.</span></div>
          <p className="mt-5 text-center text-sm text-slate-600">Vous avez déjà un compte ? <Link href="/login" className="font-semibold text-teal-700 hover:text-teal-900">Se connecter</Link></p>
        </div>
        <div className="login-main-footer">En créant un cabinet, vous acceptez de gérer son accès et ses utilisateurs.</div>
      </main>
    </div>
  );
}

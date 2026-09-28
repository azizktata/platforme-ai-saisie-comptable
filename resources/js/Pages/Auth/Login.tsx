import { Head, useForm } from '@inertiajs/react';
import { ArrowRight, Check, Eye, EyeOff, FileText, LockKeyhole } from 'lucide-react';
import { useState, type FormEvent } from 'react';

type LoginFields = {
  email: string;
  password: string;
  remember: boolean;
};

export default function Login() {
  const form = useForm<LoginFields>({ email: '', password: '', remember: false });
  const [showPassword, setShowPassword] = useState(false);

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    form.post('/login');
  };

  return (
    <div className="login-page">
      <Head title="Connexion · ComptaFlow" />
      <aside className="login-aside">
        <div className="login-brand">
          <div className="brand-mark"><span>c</span><i /></div>
          <div><div className="brand-name">compta<span>flow</span></div><div className="brand-caption">GESTION COMPTABLE</div></div>
        </div>
        <div className="login-aside-content">
          <div className="login-eyebrow"><span /> VOTRE ESPACE COMPTABLE</div>
          <h1>Les factures<br />bien <em>comptabilisées.</em></h1>
          <p>Importez vos documents, vérifiez les informations essentielles et retrouvez vos écritures en un seul endroit.</p>
          <div className="login-preview-card">
            <div className="login-preview-top"><span>Aperçu du flux</span><span className="login-live"><i /> Exemple</span></div>
            <div className="login-preview-row"><div className="login-preview-icon"><FileText size={15} /></div><div><strong>Atelier Mistral</strong><span>AM-2026-0921 · 28 sept.</span></div><span className="login-preview-check"><Check size={13} /></span></div>
            <div className="login-preview-row"><div className="login-preview-icon login-preview-icon--amber"><FileText size={15} /></div><div><strong>L’Épicerie Moderne</strong><span>LEM-2026-0842 · 27 sept.</span></div><span className="login-preview-pending">À vérifier</span></div>
            <div className="login-preview-footer"><div className="login-mini-stat"><strong>8</strong><span>documents reçus</span></div><div className="login-mini-divider" /><div className="login-mini-stat"><strong>3</strong><span>écritures validées</span></div></div>
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
            <div className="login-lock"><LockKeyhole size={19} /></div>
            <div className="login-eyebrow login-eyebrow--light">BON RETOUR</div>
            <h2>Connectez-vous</h2>
            <p>Accédez à votre espace de travail.</p>
          </div>
          <form className="login-form" onSubmit={submit}>
            <label className="login-field">
              <span>Adresse e-mail</span>
              <input
                type="email"
                autoComplete="username"
                autoFocus
                placeholder="vous@entreprise.fr"
                value={form.data.email}
                onChange={(event) => form.setData('email', event.target.value)}
                aria-invalid={Boolean(form.errors.email)}
              />
              {form.errors.email && <small className="login-field-error">{form.errors.email}</small>}
            </label>
            <label className="login-field">
              <span>Mot de passe</span>
              <div className="login-password-wrap">
                <input
                  type={showPassword ? 'text' : 'password'}
                  autoComplete="current-password"
                  placeholder="Votre mot de passe"
                  value={form.data.password}
                  onChange={(event) => form.setData('password', event.target.value)}
                  aria-invalid={Boolean(form.errors.password)}
                />
                <button type="button" className="password-toggle" aria-label={showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'} onClick={() => setShowPassword((visible) => !visible)}>
                  {showPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                </button>
              </div>
              {form.errors.password && <small className="login-field-error">{form.errors.password}</small>}
            </label>
            <label className="remember-option"><input type="checkbox" checked={form.data.remember} onChange={(event) => form.setData('remember', event.target.checked)} /><span>Rester connecté</span></label>
            <button className="button button--primary login-submit" disabled={form.processing}>
              {form.processing ? <span className="button-spinner" /> : <>Se connecter <ArrowRight size={16} /></>}
            </button>
          </form>
          <div className="login-security"><LockKeyhole size={13} /><span>Connexion sécurisée à votre espace personnel.</span></div>
        </div>
        <div className="login-main-footer">Besoin d’aide ? Contactez votre administrateur.</div>
      </main>
    </div>
  );
}

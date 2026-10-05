import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowRight, Building2, Eye, EyeOff, LockKeyhole } from 'lucide-react';
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
    <div className="min-h-screen flex flex-col items-center justify-center bg-white px-6 py-10">
      <Head title="Créer un cabinet · ComptaFlow" />
      {/* Logo Section Below Form */}
      <div className="w-full max-w-[360px] mx-auto pt-2  flex flex-col items-center gap-3 text-center">
        <div className="flex items-center gap-[11px]">
          <div className="w-[38px] h-[38px] rounded-[10px] bg-gradient-to-br from-[#168575] to-[#0f5e52] flex items-center justify-center text-white font-black text-lg shadow-md">
            <span>c</span>
          </div>
          <div className="text-left">
            <div className="text-[17px] font-bold text-[#263f39] tracking-tight">
              compta<span className="text-[#168575]">flow</span>
            </div>
            <div className="text-[8px] font-semibold text-[#8ca9a3] tracking-widest uppercase">
              GESTION COMPTABLE
            </div>
          </div>
        </div>

      </div>
      {/* Main Container centered */}
      <div className="w-full max-w-[360px] mx-auto flex flex-col items-center justify-center my-auto">
        <div className="w-full">
          <div className="relative pt-6 text-center">
           
            <h2 className="mt-[9px] mb-[5px] text-[#223733] font-sans text-[25px] font-bold tracking-[-0.75px]">
              Créer votre espace
            </h2>
            <p className="m-0 text-[#879490] text-[11px]">Vous deviendrez administrateur du cabinet.</p>
          </div>

          <form className="flex flex-col gap-[16px] mt-[27px]" onSubmit={submit}>
            <label className="flex flex-col gap-[7px] text-[#52645f] text-[10px] font-semibold">
              <span>Nom du cabinet</span>
              <input
                autoComplete="organization"
                placeholder="Cabinet Conseil"
                value={form.data.cabinet_name}
                onChange={(event) => form.setData('cabinet_name', event.target.value)}
                className="w-full h-[43px] px-[12px] border border-[#e1e9e5] rounded-[8px] outline-none bg-white text-[#334a44] text-[11px] font-normal transition-all placeholder:text-[#b0bbb7] focus:border-[#88beaa] focus:shadow-[0_0_0_3px_rgba(22,133,117,0.09)]"
                aria-invalid={Boolean(form.errors.cabinet_name)}
              />
              {form.errors.cabinet_name && <small className="text-[#b54f41] text-[9px] font-medium">{form.errors.cabinet_name}</small>}
            </label>

            <label className="flex flex-col gap-[7px] text-[#52645f] text-[10px] font-semibold">
              <span>Votre nom</span>
              <input
                autoComplete="name"
                placeholder="Nom et prénom"
                value={form.data.name}
                onChange={(event) => form.setData('name', event.target.value)}
                className="w-full h-[43px] px-[12px] border border-[#e1e9e5] rounded-[8px] outline-none bg-white text-[#334a44] text-[11px] font-normal transition-all placeholder:text-[#b0bbb7] focus:border-[#88beaa] focus:shadow-[0_0_0_3px_rgba(22,133,117,0.09)]"
                aria-invalid={Boolean(form.errors.name)}
              />
              {form.errors.name && <small className="text-[#b54f41] text-[9px] font-medium">{form.errors.name}</small>}
            </label>

            <label className="flex flex-col gap-[7px] text-[#52645f] text-[10px] font-semibold">
              <span>Adresse e-mail</span>
              <input
                type="email"
                autoComplete="email"
                placeholder="vous@entreprise.tn"
                value={form.data.email}
                onChange={(event) => form.setData('email', event.target.value)}
                className="w-full h-[43px] px-[12px] border border-[#e1e9e5] rounded-[8px] outline-none bg-white text-[#334a44] text-[11px] font-normal transition-all placeholder:text-[#b0bbb7] focus:border-[#88beaa] focus:shadow-[0_0_0_3px_rgba(22,133,117,0.09)]"
                aria-invalid={Boolean(form.errors.email)}
              />
              {form.errors.email && <small className="text-[#b54f41] text-[9px] font-medium">{form.errors.email}</small>}
            </label>

            <label className="flex flex-col gap-[7px] text-[#52645f] text-[10px] font-semibold">
              <span>Mot de passe <small className="font-normal text-slate-400">(12 caractères minimum)</small></span>
              <div className="relative">
                <input
                  type={showPassword ? 'text' : 'password'}
                  autoComplete="new-password"
                  value={form.data.password}
                  onChange={(event) => form.setData('password', event.target.value)}
                  className="w-full h-[43px] pl-[12px] pr-[42px] border border-[#e1e9e5] rounded-[8px] outline-none bg-white text-[#334a44] text-[11px] font-normal transition-all focus:border-[#88beaa] focus:shadow-[0_0_0_3px_rgba(22,133,117,0.09)]"
                  aria-invalid={Boolean(form.errors.password)}
                />
                <button
                  type="button"
                  className="absolute top-1/2 right-[9px] grid w-[26px] h-[26px] place-items-center border-0 rounded-[6px] bg-transparent text-[#91a09b] cursor-pointer -translate-y-1/2 hover:bg-[#f3f7f5] hover:text-[#526c62]"
                  aria-label={showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'}
                  onClick={() => setShowPassword((visible) => !visible)}
                >
                  {showPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                </button>
              </div>
              {form.errors.password && <small className="text-[#b54f41] text-[9px] font-medium">{form.errors.password}</small>}
            </label>

            <label className="flex flex-col gap-[7px] text-[#52645f] text-[10px] font-semibold">
              <span>Confirmer le mot de passe</span>
              <div className="relative">
                <input
                  type={showConfirmation ? 'text' : 'password'}
                  autoComplete="new-password"
                  value={form.data.password_confirmation}
                  onChange={(event) => form.setData('password_confirmation', event.target.value)}
                  className="w-full h-[43px] pl-[12px] pr-[42px] border border-[#e1e9e5] rounded-[8px] outline-none bg-white text-[#334a44] text-[11px] font-normal transition-all focus:border-[#88beaa] focus:shadow-[0_0_0_3px_rgba(22,133,117,0.09)]"
                  aria-invalid={Boolean(form.errors.password_confirmation)}
                />
                <button
                  type="button"
                  className="absolute top-1/2 right-[9px] grid w-[26px] h-[26px] place-items-center border-0 rounded-[6px] bg-transparent text-[#91a09b] cursor-pointer -translate-y-1/2 hover:bg-[#f3f7f5] hover:text-[#526c62]"
                  aria-label={showConfirmation ? 'Masquer la confirmation' : 'Afficher la confirmation'}
                  onClick={() => setShowConfirmation((visible) => !visible)}
                >
                  {showConfirmation ? <EyeOff size={16} /> : <Eye size={16} />}
                </button>
              </div>
              {form.errors.password_confirmation && <small className="text-[#b54f41] text-[9px] font-medium">{form.errors.password_confirmation}</small>}
            </label>

            <button
              type="submit"
              disabled={form.processing}
              className="w-full min-h-[44px] justify-center mt-[2px] text-[11px] flex items-center gap-2 py-2.5 px-4 bg-[#168575] hover:bg-[#126d60] text-white font-medium rounded-lg transition-colors shadow-sm disabled:opacity-50"
            >
              {form.processing ? (
                <span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
              ) : (
                <p className='text-white flex items-center gap-2'>Créer mon cabinet <ArrowRight size={16} /></p>
              )}
            </button>
          </form>

          <div className="flex items-center justify-center gap-[6px] mt-[19px] text-[#9ba7a3] text-[8px]">
            <LockKeyhole size={13} className="text-[#84a095]" />
            <span>Votre compte sera l’administrateur de ce nouvel espace.</span>
          </div>

          <p className="mt-[15px] text-center text-[10px] text-[#75847f]">
            Vous avez déjà un compte ? <Link href="/login" className="font-semibold text-[#488b76] hover:text-[#226b59] hover:underline">Se connecter</Link>
          </p>
        </div>
      </div>


    </div>
  );
}
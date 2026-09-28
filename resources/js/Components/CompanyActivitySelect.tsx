import { useForm } from '@inertiajs/react';
import { Check, Plus, X } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

type Props = {
  id: string;
  value: string;
  activities: string[];
  canAdd: boolean;
  onChange: (value: string) => void;
};

const inputClass = 'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-100';
const ADD_ACTIVITY = '__add_activity__';

export default function CompanyActivitySelect({ id, value, activities, canAdd, onChange }: Props) {
  const [isAdding, setIsAdding] = useState(false);
  const form = useForm({ name: '' });
  const knownValue = value !== '' && !activities.includes(value);

  const createActivity = () => {
    if (!form.data.name.trim() || form.processing) return;

    form.post('/cabinet/activities', {
      preserveScroll: true,
      onSuccess: () => {
        const name = form.data.name.trim().replace(/\s+/g, ' ');
        onChange(name);
        form.reset();
        setIsAdding(false);
        toast.success('Activité ajoutée au catalogue du cabinet.');
      },
      onError: () => toast.error('Cette activité n’a pas pu être ajoutée. Vérifiez qu’elle n’existe pas déjà.'),
    });
  };

  return (
    <div>
      <select
        id={id}
        className={inputClass}
        value={value || ''}
        onChange={(event) => {
          if (event.target.value === ADD_ACTIVITY) {
            setIsAdding(true);
            return;
          }

          onChange(event.target.value);
        }}
      >
        <option value="">Sélectionner une activité</option>
        {knownValue && <option value={value}>{value}</option>}
        {activities.map((activity) => <option key={activity} value={activity}>{activity}</option>)}
        {canAdd && <option value={ADD_ACTIVITY}>＋ Ajouter une activité au catalogue…</option>}
      </select>

      {isAdding && canAdd && (
        <div className="mt-2 rounded-lg border border-teal-200 bg-teal-50/70 p-3">
          <label htmlFor={`${id}-new`} className="block text-xs font-semibold text-teal-900">Nouvelle activité</label>
          <div className="mt-1 flex gap-2">
            <input
              id={`${id}-new`}
              autoFocus
              className="min-w-0 flex-1 rounded-md border border-teal-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
              value={form.data.name}
              maxLength={190}
              placeholder="Ex. Services médicaux"
              onChange={(event) => form.setData('name', event.target.value)}
              onKeyDown={(event) => {
                if (event.key === 'Enter') {
                  event.preventDefault();
                  createActivity();
                }
              }}
            />
            <button type="button" onClick={createActivity} disabled={form.processing || !form.data.name.trim()} className="inline-flex items-center gap-1 rounded-md bg-teal-700 px-3 py-2 text-xs font-semibold text-white hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-50">
              <Check size={14} /> Ajouter
            </button>
            <button type="button" onClick={() => { setIsAdding(false); form.reset(); }} aria-label="Annuler l’ajout d’activité" className="rounded-md p-2 text-slate-500 hover:bg-white hover:text-slate-800">
              <X size={15} />
            </button>
          </div>
          {form.errors.name && <p className="mt-1 text-xs text-red-700">{form.errors.name}</p>}
          <p className="mt-1 flex items-center gap-1 text-xs text-teal-800"><Plus size={12} /> L’activité sera disponible aux sociétés de ce cabinet.</p>
        </div>
      )}
    </div>
  );
}

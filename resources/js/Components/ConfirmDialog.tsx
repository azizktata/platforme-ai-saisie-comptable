import { AlertTriangle } from 'lucide-react';

type Props = {
  open: boolean;
  title: string;
  description: string;
  confirmLabel?: string;
  destructive?: boolean;
  onCancel: () => void;
  onConfirm: () => void;
};

export default function ConfirmDialog({ open, title, description, confirmLabel = 'Confirmer', destructive = false, onCancel, onConfirm }: Props) {
  if (!open) return null;

  return (
    <div className="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/40 p-4" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) onCancel(); }}>
      <section role="alertdialog" aria-modal="true" aria-labelledby="confirm-dialog-title" aria-describedby="confirm-dialog-description" className="w-full max-w-md rounded-xl border border-slate-200 bg-white p-5 shadow-2xl">
        <div className="flex items-start gap-3">
          <span className={`mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full ${destructive ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700'}`}><AlertTriangle size={18} /></span>
          <div><h2 id="confirm-dialog-title" className="text-base font-semibold text-slate-950">{title}</h2><p id="confirm-dialog-description" className="mt-1 text-sm leading-5 text-slate-600">{description}</p></div>
        </div>
        <div className="mt-5 flex justify-end gap-2">
          <button type="button" onClick={onCancel} className="rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Annuler</button>
          <button type="button" onClick={onConfirm} className={`rounded-lg px-3.5 py-2 text-sm font-semibold text-white ${destructive ? 'bg-red-700 hover:bg-red-800' : 'bg-teal-700 hover:bg-teal-800'}`}>{confirmLabel}</button>
        </div>
      </section>
    </div>
  );
}

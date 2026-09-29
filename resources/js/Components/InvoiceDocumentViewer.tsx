import { ArrowDownToLine, ChevronLeft, ChevronRight, Maximize2, RotateCcw, RotateCw, ZoomIn, ZoomOut } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';

type Props = {
  filename: string;
  mimeType: string;
  previewUrl: string;
  downloadUrl: string;
};

export default function InvoiceDocumentViewer({ filename, mimeType, previewUrl, downloadUrl }: Props) {
  const container = useRef<HTMLElement>(null);
  const isPdf = mimeType === 'application/pdf' || filename.toLowerCase().endsWith('.pdf');
  const [zoom, setZoom] = useState(1);
  const [rotation, setRotation] = useState(0);
  const [fit, setFit] = useState<'width' | 'page' | 'custom'>('width');
  const [page, setPage] = useState(1);

  const documentUrl = useMemo(() => {
    if (!isPdf) return previewUrl;
    const view = fit === 'width' ? 'FitH' : fit === 'page' ? 'Fit' : null;
    const fragment = new URLSearchParams({ toolbar: '1', navpanes: '0', page: String(page) });
    if (view) fragment.set('view', view);
    else fragment.set('zoom', String(Math.round(zoom * 100)));
    return `${previewUrl}#${fragment.toString()}`;
  }, [fit, isPdf, page, previewUrl, zoom]);

  const setCustomZoom = (amount: number) => {
    setFit('custom');
    setZoom((current) => Math.min(2.5, Math.max(0.5, current + amount)));
  };

  const fitDocument = (mode: 'width' | 'page') => {
    setFit(mode);
    setZoom(1);
    setRotation(0);
  };

  const rotate = (amount: number) => setRotation((current) => (current + amount + 360) % 360);

  return (
    <section ref={container} className="flex h-[65vh] min-h-[380px] flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm xl:h-full xl:min-h-0" aria-label="Document original">
      <header className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-3 py-3">
        <div className="min-w-0">
          <h2 className="text-sm font-semibold text-slate-900">Document original</h2>
          <p className="max-w-[50vw] truncate text-xs text-slate-500" title={filename}>{filename}</p>
        </div>
        <a href={downloadUrl} className="inline-flex items-center gap-1.5 rounded-md border border-slate-200 px-2.5 py-2 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-800">
          <ArrowDownToLine size={14} /> Télécharger
        </a>
      </header>

      <div className="flex flex-wrap items-center gap-1 border-b border-slate-200 bg-slate-50 px-2 py-2" aria-label="Outils document">
        <button type="button" onClick={() => setCustomZoom(-0.15)} className="rounded p-2 text-slate-700 hover:bg-white" aria-label="Zoom arrière"><ZoomOut size={16} /></button>
        <span className="min-w-12 text-center text-xs tabular-nums text-slate-600">{Math.round(zoom * 100)}%</span>
        <button type="button" onClick={() => setCustomZoom(0.15)} className="rounded p-2 text-slate-700 hover:bg-white" aria-label="Zoom avant"><ZoomIn size={16} /></button>
        <span className="mx-1 h-5 border-l border-slate-200" />
        <button type="button" onClick={() => fitDocument('width')} className={`rounded px-2 py-1.5 text-xs font-medium ${fit === 'width' ? 'bg-teal-100 text-teal-900' : 'text-slate-700 hover:bg-white'}`}>Largeur</button>
        <button type="button" onClick={() => fitDocument('page')} className={`rounded px-2 py-1.5 text-xs font-medium ${fit === 'page' ? 'bg-teal-100 text-teal-900' : 'text-slate-700 hover:bg-white'}`}>Page</button>
        {!isPdf && <button type="button" onClick={() => rotate(-90)} className="rounded p-2 text-slate-700 hover:bg-white" aria-label="Rotation antihoraire"><RotateCcw size={16} /></button>}
        {!isPdf && <button type="button" onClick={() => rotate(90)} className="rounded p-2 text-slate-700 hover:bg-white" aria-label="Rotation horaire"><RotateCw size={16} /></button>}
        {isPdf && (
          <div className="ml-auto flex items-center gap-1 text-xs text-slate-600" aria-label="Navigation des pages PDF">
            <button type="button" onClick={() => setPage((current) => Math.max(1, current - 1))} disabled={page <= 1} className="rounded p-1.5 hover:bg-white disabled:opacity-40" aria-label="Page précédente"><ChevronLeft size={16} /></button>
            <label className="flex items-center gap-1">Page <input type="number" min={1} value={page} onChange={(event) => setPage(Math.max(1, Number(event.target.value) || 1))} className="w-14 rounded border border-slate-300 bg-white px-1.5 py-1 text-center" /></label>
            <button type="button" onClick={() => setPage((current) => current + 1)} className="rounded p-1.5 hover:bg-white" aria-label="Page suivante"><ChevronRight size={16} /></button>
          </div>
        )}
        <button type="button" onClick={() => container.current?.requestFullscreen?.()} className="ml-auto rounded p-2 text-slate-700 hover:bg-white" aria-label="Plein écran"><Maximize2 size={16} /></button>
      </div>

      <div className="min-h-0 flex-1 overflow-auto bg-slate-100">
        {isPdf ? (
          <iframe key={documentUrl} src={documentUrl} title={`Aperçu PDF : ${filename}`} className="h-full min-h-[420px] w-full border-0" />
        ) : mimeType.startsWith('image/') ? (
          <div className="flex h-full min-h-full min-w-full items-center justify-center overflow-auto p-4">
            <img
              src={previewUrl}
              alt={`Facture originale : ${filename}`}
              className={fit === 'page' ? 'max-h-full max-w-full object-contain shadow-md' : 'h-auto max-w-none object-contain shadow-md'}
              style={{ width: fit === 'page' ? 'auto' : `${Math.round(zoom * 100)}%`, transform: `rotate(${rotation}deg)` }}
            />
          </div>
        ) : (
          <div className="m-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            L’aperçu de ce format n’est pas disponible dans le navigateur. Téléchargez le document pour le consulter.
          </div>
        )}
      </div>
    </section>
  );
}

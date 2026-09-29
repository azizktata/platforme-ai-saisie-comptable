import { Head, Link, router } from '@inertiajs/react';
import { ArrowDownToLine, FileText, LoaderCircle, RotateCcw, Upload, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import AppShell from '../../Components/AppShell';
import type { SharedAuthProps } from '../../types';

type InvoiceSummary = {
  id: number;
  original_filename: string;
  size_bytes: number;
  supplier_name: string | null;
  invoice_number: string | null;
  invoice_date: string | null;
  total_amount: string | null;
  currency: string | null;
  description: string | null;
  status: string;
  ocr_attempts: number;
  ocr_error_message: string | null;
  ocr_warnings: string[];
  ocr_reviewed_at: string | null;
  created_at: string;
  download_url: string;
};

type Paginator<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  from: number | null;
  to: number | null;
  total: number;
  prev_page_url: string | null;
  next_page_url: string | null;
};

type InvoiceUploadFailure = {
  filename: string;
  message: string;
};

type Props = {
  mode: 'workspace' | 'history';
  ocrProvider: 'ocr_space' | 'mistral';
  maxUploadFileSizeBytes: number;
  companies: { id: number; name: string }[];
  company: { id: number; name: string; currency: string } | null;
  invoices: Paginator<InvoiceSummary> | null;
  canUploadInvoices: boolean;
  canReviewInvoices: boolean;
  auth?: SharedAuthProps;
};

type UploadResponse = {
  invoice?: { id: number; original_filename: string; status: string };
  message?: string;
  errors?: Record<string, string[]>;
};

const MAX_FILES_PER_BATCH = 300;

export default function InvoicesIndex({ mode, ocrProvider, maxUploadFileSizeBytes, companies, company, invoices, canUploadInvoices, canReviewInvoices, auth }: Props) {
  const fileInput = useRef<HTMLInputElement>(null);
  const [selectedFiles, setSelectedFiles] = useState<File[]>([]);
  const [isUploading, setIsUploading] = useState(false);
  const [uploadProgress, setUploadProgress] = useState({ completed: 0, total: 0 });
  const [confirmDuplicates, setConfirmDuplicates] = useState(false);
  const [uploadFailures, setUploadFailures] = useState<InvoiceUploadFailure[]>([]);
  const [selectedInvoiceIds, setSelectedInvoiceIds] = useState<number[]>([]);
  const invoiceRows = invoices?.data ?? [];
  const hasPendingOcr = invoiceRows.some((invoice) => ['ocr_queued', 'ocr_processing'].includes(invoice.status));
  const reviewableSelectedIds = invoiceRows
    .filter((invoice) => selectedInvoiceIds.includes(invoice.id) && invoice.status === 'ocr_completed' && !invoice.ocr_reviewed_at)
    .map((invoice) => invoice.id);
  const allVisibleSelected = invoiceRows.length > 0 && invoiceRows.every((invoice) => selectedInvoiceIds.includes(invoice.id));

  useEffect(() => {
    if (!hasPendingOcr) return;

    const interval = window.setInterval(() => {
      router.reload({ only: ['invoices'] });
    }, 5000);

    return () => window.clearInterval(interval);
  }, [hasPendingOcr]);

  useEffect(() => {
    setSelectedInvoiceIds([]);
  }, [company?.id, invoices?.current_page, mode]);

  useEffect(() => {
    setSelectedFiles([]);
    setUploadFailures([]);
    setConfirmDuplicates(false);
    if (fileInput.current) fileInput.current.value = '';
  }, [company?.id, mode]);

  const retryOcr = (invoiceId: number) => {
    if (!company) return;

    router.post(`/companies/${company.id}/invoices/${invoiceId}/ocr/retry`, {}, {
      preserveScroll: true,
      onSuccess: () => toast.success('Relance OCR planifiée.'),
      onError: () => toast.error('La relance OCR n’a pas pu être planifiée.'),
    });
  };

  const toggleInvoice = (invoiceId: number) => {
    setSelectedInvoiceIds((current) => current.includes(invoiceId)
      ? current.filter((id) => id !== invoiceId)
      : [...current, invoiceId]);
  };

  const toggleVisibleInvoices = () => {
    setSelectedInvoiceIds(allVisibleSelected ? [] : invoiceRows.map((invoice) => invoice.id));
  };

  const reviewSelected = () => {
    if (!company || reviewableSelectedIds.length === 0) return;

    router.post(`/companies/${company.id}/invoices/bulk-review`, { invoice_ids: reviewableSelectedIds }, {
      preserveScroll: true,
      onSuccess: () => {
        setSelectedInvoiceIds([]);
        toast.success(`${reviewableSelectedIds.length} extraction${reviewableSelectedIds.length > 1 ? 's' : ''} marquée${reviewableSelectedIds.length > 1 ? 's' : ''} comme vérifiée${reviewableSelectedIds.length > 1 ? 's' : ''}.`);
      },
      onError: () => toast.error('La vérification groupée a échoué. Actualisez la liste et réessayez.'),
    });
  };

  const exportSelected = () => {
    const selected = invoiceRows.filter((invoice) => selectedInvoiceIds.includes(invoice.id));
    if (!selected.length) return;

    const escapeCell = (value: string | number | null, protectFormula = true) => {
      const raw = String(value ?? '');
      const safe = protectFormula && /^[\s]*[=+@-]/.test(raw) ? `'${raw}` : raw;
      return `"${safe.replaceAll('"', '""')}"`;
    };
    const rows = [
      ['Fichier', 'Fournisseur', 'Référence', 'Date facture', 'Total', 'Devise', 'État', 'Vérifiée le'].map((value) => escapeCell(value)),
      ...selected.map((invoice) => [
        escapeCell(invoice.original_filename),
        escapeCell(invoice.supplier_name),
        escapeCell(invoice.invoice_number),
        escapeCell(invoice.invoice_date),
        escapeCell(invoice.total_amount, false),
        escapeCell(invoice.currency || company?.currency || 'TND'),
        escapeCell(invoice.status),
        escapeCell(invoice.ocr_reviewed_at),
      ]),
    ];
    const csv = rows.map((row) => row.join(';')).join('\r\n');
    const blob = new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = `factures-${company?.id ?? 'selection'}-${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
    toast.success(`${selected.length} facture${selected.length > 1 ? 's exportées' : ' exportée'} en CSV.`);
  };

  const chooseFiles = (files: FileList | null) => {
    const nextFiles = Array.from(files ?? []);

    if (nextFiles.length > MAX_FILES_PER_BATCH) {
      toast.error(`Sélectionnez au maximum ${MAX_FILES_PER_BATCH} fichiers par lot.`);
      if (fileInput.current) fileInput.current.value = '';
      return;
    }

    setSelectedFiles(nextFiles);
    setUploadFailures([]);
  };

  const uploadFiles = async () => {
    if (!company || !selectedFiles.length || isUploading) return;

    const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;

    if (!csrfToken) {
      toast.error('Jeton de sécurité absent. Actualisez la page et réessayez.');
      return;
    }

    const batch = selectedFiles;
    const failures: InvoiceUploadFailure[] = [];
    let successfulUploads = 0;

    setIsUploading(true);
    setUploadFailures([]);
    setUploadProgress({ completed: 0, total: batch.length });

    for (const [index, file] of batch.entries()) {
      const body = new FormData();
      body.append('file', file, file.name);
      if (confirmDuplicates) body.append('confirm_duplicate', '1');

      try {
        const response = await fetch(`/companies/${company.id}/invoices/upload`, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
          },
          body,
        });
        const contentType = response.headers.get('content-type') || '';
        const payload: UploadResponse | null = contentType.includes('application/json')
          ? await response.json()
          : null;

        if (response.ok && payload?.invoice) {
          successfulUploads += 1;
        } else {
          failures.push({
            filename: file.name,
            message: payload?.errors?.file?.[0] || payload?.message || 'Le fichier n’a pas pu être importé.',
          });
        }
      } catch {
        failures.push({ filename: file.name, message: 'Erreur réseau. Vérifiez votre connexion et réessayez.' });
      }

      setUploadProgress({ completed: index + 1, total: batch.length });
    }

    setUploadFailures(failures);
    setIsUploading(false);
    setSelectedFiles([]);
    setConfirmDuplicates(false);
    if (fileInput.current) fileInput.current.value = '';

    if (successfulUploads > 0) {
      toast.success(`${successfulUploads} fichier${successfulUploads > 1 ? 's' : ''} importé${successfulUploads > 1 ? 's' : ''}.`);
      router.get('/invoices', { company_id: company.id }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
      });
    }

    if (failures.length > 0) {
      toast.error(`${failures.length} fichier${failures.length > 1 ? 's' : ''} à vérifier. Les autres imports ne sont pas bloqués.`);
    }
  };

  return (
    <AppShell
      activeSection="invoices"
      cabinetName={auth?.cabinet?.name}
      canManageCabinet={auth?.canManageCabinet}
      user={auth?.user}
      onLogout={auth?.user ? () => router.post('/logout', {}, {
        onSuccess: () => toast.success('Déconnexion réussie.'),
        onError: () => toast.error('La déconnexion a échoué. Réessayez.'),
      }) : undefined}
    >
      <Head title={mode === 'history' ? `Historique · ${company?.name || 'Factures'}` : 'Factures'} />
      <section className="mx-auto max-w-7xl space-y-6">
        <header className="flex flex-wrap items-end justify-between gap-4">
          <div>
            {company && <p className="text-sm font-semibold uppercase tracking-[0.16em] text-teal-700">{company.name}</p>}
            <h1 className="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{mode === 'history' ? 'Historique des factures' : 'Factures'}</h1>
            <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
              {mode === 'history'
                ? 'Consultez les documents déposés pour cette société, leur état OCR et les résultats de vérification.'
                : ocrProvider === 'ocr_space'
                  ? 'La première société accessible est sélectionnée automatiquement. OCR.space Engine 3 retranscrit le texte ; les champs fournisseur, date et montants ne sont pas structurés automatiquement.'
                  : 'La première société accessible est sélectionnée automatiquement. Chaque fichier est traité séparément par Mistral OCR.'}
            </p>
          </div>
          {mode === 'workspace' ? (
            <label className="block min-w-64 text-sm font-medium text-slate-700">
              Société
              <select
                className="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100 disabled:cursor-not-allowed disabled:bg-slate-100"
                value={company?.id ?? ''}
                disabled={isUploading}
                onChange={(event) => router.get('/invoices', event.target.value ? { company_id: Number(event.target.value) } : {}, { preserveScroll: true, replace: true })}
              >
                <option value="" disabled={companies.length > 0}>Sélectionner une société</option>
                {companies.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
              </select>
            </label>
          ) : company ? (
            <Link href={`/invoices?company_id=${company.id}`} className="inline-flex items-center gap-2 rounded-lg border border-teal-200 bg-teal-50 px-4 py-2.5 text-sm font-semibold text-teal-800 hover:bg-teal-100">
              <Upload size={16} /> Déposer des factures
            </Link>
          ) : null}
        </header>

        {mode === 'workspace' && company && canUploadInvoices ? (
          <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex items-start gap-3">
              <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-teal-50 text-teal-700"><Upload size={19} /></span>
              <div>
                <h2 className="font-semibold text-slate-900">Importer des factures</h2>
                <p className="mt-1 text-sm leading-5 text-slate-500">PDF, JPG, JPEG ou PNG · {formatFileSize(maxUploadFileSizeBytes)} maximum par fichier · jusqu’à {MAX_FILES_PER_BATCH} fichiers.</p>
              </div>
            </div>

            <div className="mt-5 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-5">
              <label className="block text-sm font-medium text-slate-700" htmlFor="invoice-files">Fichiers à importer</label>
              <input
                ref={fileInput}
                id="invoice-files"
                type="file"
                multiple
                accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                disabled={isUploading}
                onChange={(event) => chooseFiles(event.target.files)}
                className="mt-2 block w-full cursor-pointer rounded-lg border border-slate-300 bg-white text-sm text-slate-600 file:mr-3 file:rounded-l-lg file:border-0 file:bg-teal-50 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-teal-800 hover:file:bg-teal-100 disabled:cursor-not-allowed"
              />
              {selectedFiles.length > 0 && (
                <div className="mt-3 flex items-center justify-between gap-3 text-xs text-slate-500">
                  <span>{selectedFiles.length} fichier{selectedFiles.length > 1 ? 's' : ''} sélectionné{selectedFiles.length > 1 ? 's' : ''} · {formatFileSize(selectedFiles.reduce((sum, file) => sum + file.size, 0))} au total</span>
                  {!isUploading && (
                    <button type="button" onClick={() => chooseFiles(null)} className="inline-flex items-center gap-1 font-semibold text-slate-600 hover:text-red-700">
                      <X size={14} /> Effacer
                    </button>
                  )}
                </div>
              )}
            </div>

            <label className="mt-4 flex cursor-pointer items-start gap-2.5 text-sm text-slate-600">
              <input
                type="checkbox"
                checked={confirmDuplicates}
                disabled={isUploading}
                onChange={(event) => setConfirmDuplicates(event.target.checked)}
                className="mt-0.5 h-4 w-4 rounded border-slate-300 text-teal-700 focus:ring-teal-600"
              />
              <span>J’ai vérifié les doublons éventuels et souhaite importer quand même un fichier identique déjà enregistré.</span>
            </label>

            {isUploading && (
              <div className="mt-4" role="status" aria-live="polite">
                <div className="flex items-center justify-between text-xs font-medium text-slate-600">
                  <span>Import en cours — un fichier à la fois</span>
                  <span>{uploadProgress.completed} / {uploadProgress.total}</span>
                </div>
                <div className="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                  <div className="h-full rounded-full bg-teal-600 transition-all" style={{ width: `${uploadProgress.total ? (uploadProgress.completed / uploadProgress.total) * 100 : 0}%` }} />
                </div>
              </div>
            )}

            <button
              type="button"
              onClick={uploadFiles}
              disabled={!selectedFiles.length || isUploading}
              className="mt-5 inline-flex items-center gap-2 rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-50"
            >
              {isUploading ? <LoaderCircle className="animate-spin" size={17} /> : <Upload size={17} />}
              {isUploading
                ? 'Import en cours…'
                : selectedFiles.length > 0
                  ? `Importer ${selectedFiles.length} fichier${selectedFiles.length > 1 ? 's' : ''}`
                  : 'Importer des fichiers'}
            </button>

            <p className="mt-3 text-xs leading-5 text-slate-500">
              Les originaux restent dans le stockage privé. {ocrProvider === 'ocr_space' ? 'OCR.space transcrit le texte sans remplir automatiquement les champs structurés ; ouvrez « Afficher le texte OCR » pour le vérifier.' : 'Mistral extrait des champs et des montants qui doivent toujours être vérifiés.'} Aucune proposition comptable n’est générée à cette phase.
            </p>
          </section>
        ) : mode === 'workspace' && company ? (
          <div className="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-600 shadow-sm">
            Votre accès permet de consulter les factures et de télécharger leurs documents, mais pas d’en importer.
          </div>
        ) : mode === 'workspace' ? (
          <div className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center text-sm text-slate-600">
            {companies.length ? 'Sélectionnez une société pour afficher les factures récentes et déposer des documents.' : 'Aucune société n’est accessible à votre compte. Demandez un accès à un administrateur du cabinet.'}
          </div>
        ) : null}

        {uploadFailures.length > 0 && (
          <section className="rounded-xl border border-amber-200 bg-amber-50 p-4" aria-live="polite">
            <h2 className="font-semibold text-amber-950">Fichiers non importés ({uploadFailures.length})</h2>
            <ul className="mt-2 space-y-1 text-sm text-amber-900">
              {uploadFailures.map((failure, index) => (
                <li key={`${failure.filename}-${index}`}><span className="font-medium">{failure.filename}</span> — {failure.message}</li>
              ))}
            </ul>
            <p className="mt-3 text-xs text-amber-800">Les fichiers acceptés ont été conservés. Vous pouvez sélectionner à nouveau ceux à corriger.</p>
          </section>
        )}

        {company && invoices && (
          <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <header className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
              <div>
                <h2 className="font-semibold text-slate-900">{mode === 'history' ? 'Historique complet' : 'Imports récents'}</h2>
                <p className="mt-0.5 text-xs text-slate-500">{invoices.total} facture{invoices.total > 1 ? 's' : ''} · documents privés</p>
              </div>
              {mode === 'workspace'
                ? <Link href={`/companies/${company.id}/invoices`} className="text-sm font-semibold text-teal-700 hover:text-teal-900">Voir tout l’historique →</Link>
                : <FileText className="text-slate-400" size={19} />}
            </header>

            {mode === 'workspace' && invoiceRows.length > 0 && (
              <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/70 px-5 py-3">
                <p className="text-xs text-slate-600">{selectedInvoiceIds.length} sélectionnée{selectedInvoiceIds.length === 1 ? '' : 's'} sur cette page</p>
                <div className="flex flex-wrap gap-2">
                  <button type="button" onClick={exportSelected} disabled={!selectedInvoiceIds.length} className="rounded-md border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:border-teal-300 disabled:cursor-not-allowed disabled:opacity-50">Exporter la sélection CSV</button>
                  {canReviewInvoices && reviewableSelectedIds.length > 0 && (
                    <button type="button" onClick={reviewSelected} className="rounded-md bg-teal-700 px-3 py-2 text-xs font-semibold text-white hover:bg-teal-800">Marquer les extractions vérifiées ({reviewableSelectedIds.length})</button>
                  )}
                </div>
              </div>
            )}

            {invoiceRows.length ? (
            <div className="overflow-x-auto">
              <table className="min-w-full text-left text-sm">
                <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                  <tr>
                    {mode === 'workspace' && (
                      <th className="px-4 py-3">
                        <input type="checkbox" checked={allVisibleSelected} onChange={toggleVisibleInvoices} aria-label="Sélectionner toutes les factures de cette page" className="h-4 w-4 rounded border-slate-300 text-teal-700 focus:ring-teal-600" />
                      </th>
                    )}
                    <th className="px-5 py-3 font-semibold">Document</th>
                    <th className="px-5 py-3 font-semibold">Fournisseur / référence</th>
                    <th className="px-5 py-3 font-semibold">Date facture</th>
                    <th className="px-5 py-3 font-semibold text-right">Total</th>
                    <th className="px-5 py-3 font-semibold">État</th>
                    <th className="px-5 py-3 font-semibold"><span className="sr-only">Actions</span></th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {invoiceRows.map((invoice) => (
                    <tr key={invoice.id}>
                      {mode === 'workspace' && (
                        <td className="px-4 py-3">
                          <input type="checkbox" checked={selectedInvoiceIds.includes(invoice.id)} onChange={() => toggleInvoice(invoice.id)} aria-label={`Sélectionner ${invoice.original_filename}`} className="h-4 w-4 rounded border-slate-300 text-teal-700 focus:ring-teal-600" />
                        </td>
                      )}
                      <td className="min-w-56 px-5 py-3">
                        <span className="block max-w-72 truncate font-medium text-slate-800" title={invoice.original_filename}>{invoice.original_filename}</span>
                        <span className="mt-0.5 block text-xs text-slate-400">{formatFileSize(invoice.size_bytes)} · {formatTimestamp(invoice.created_at)}</span>
                        {invoice.status === 'ocr_completed' && invoice.description && (
                          <details className="mt-1 max-w-72 text-xs">
                            <summary className="cursor-pointer font-medium text-teal-700 hover:text-teal-900">
                              {ocrProvider === 'ocr_space' ? 'Afficher le texte OCR' : 'Afficher la description'}
                            </summary>
                            <pre className="mt-2 max-h-72 overflow-auto whitespace-pre-wrap break-words rounded-md bg-slate-50 p-3 font-sans leading-5 text-slate-700">{invoice.description}</pre>
                          </details>
                        )}
                      </td>
                      <td className="px-5 py-3 text-slate-600">
                        {invoice.supplier_name || 'Fournisseur à identifier'}
                        <span className="mt-0.5 block text-xs text-slate-400">{invoice.invoice_number || 'Référence à extraire'}</span>
                      </td>
                      <td className="whitespace-nowrap px-5 py-3 text-slate-600">{invoice.invoice_date ? formatDate(invoice.invoice_date) : '—'}</td>
                      <td className="whitespace-nowrap px-5 py-3 text-right tabular-nums text-slate-700">{formatAmount(invoice.total_amount, invoice.currency || company.currency || 'TND')}</td>
                      <td className="min-w-40 px-5 py-3">
                        <StatusBadge status={invoice.status} reviewedAt={invoice.ocr_reviewed_at} />
                        {invoice.status === 'ocr_failed' && (
                          <p className="mt-1 max-w-64 text-xs leading-4 text-red-700">
                            {invoice.ocr_error_message || 'Le traitement OCR a échoué.'}
                            {invoice.ocr_attempts > 0 && <span className="block text-red-600">Tentatives : {invoice.ocr_attempts}</span>}
                          </p>
                        )}
                        {invoice.ocr_warnings.includes('invoice_total_mismatch') && (
                          <p className="mt-1 max-w-64 text-xs leading-4 text-red-700">Incohérence de total détectée. Vérifiez les montants extraits et le document original.</p>
                        )}
                        {invoice.ocr_warnings.includes('invoice_totals_unverified') && (
                          <p className="mt-1 max-w-64 text-xs leading-4 text-amber-800">Contrôle des totaux non conclusif : des montants nécessaires sont absents ou illisibles.</p>
                        )}
                      </td>
                      <td className="min-w-52 px-5 py-3 text-right">
                        <div className="flex flex-wrap justify-end gap-2">
                          {canUploadInvoices && ['uploaded', 'ocr_failed'].includes(invoice.status) && (
                            <button
                              type="button"
                              onClick={() => retryOcr(invoice.id)}
                              className="inline-flex items-center gap-1.5 rounded-md border border-amber-300 px-2.5 py-1.5 text-xs font-semibold text-amber-900 hover:bg-amber-50"
                            >
                              <RotateCcw size={14} /> {invoice.status === 'uploaded' ? 'Démarrer OCR' : 'Relancer OCR'}
                            </button>
                          )}
                          <a href={invoice.download_url} className="inline-flex items-center gap-1.5 rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-teal-800 hover:border-teal-300 hover:bg-teal-50">
                            <ArrowDownToLine size={14} /> Télécharger
                          </a>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <div className="px-5 py-10 text-center">
              <FileText className="mx-auto text-slate-300" size={30} />
              <h3 className="mt-3 font-semibold text-slate-900">Aucune facture importée</h3>
              <p className="mt-1 text-sm text-slate-500">Les documents ajoutés à cette société apparaîtront ici.</p>
            </div>
          )}

            <Paginator page={invoices} />
          </section>
        )}
      </section>
    </AppShell>
  );
}

function StatusBadge({ status, reviewedAt }: { status: string; reviewedAt: string | null }) {
  const labels: Record<string, string> = {
    uploaded: 'Importée · en attente OCR',
    ocr_queued: 'OCR en attente',
    ocr_processing: 'OCR en cours',
    ocr_completed: 'OCR terminé · à vérifier',
    ocr_failed: 'Échec OCR',
  };
  const tone = status === 'ocr_completed'
    ? 'bg-emerald-50 text-emerald-800'
    : status === 'ocr_failed'
      ? 'bg-red-50 text-red-800'
      : status === 'ocr_queued' || status === 'ocr_processing'
        ? 'bg-amber-50 text-amber-900'
        : 'bg-sky-50 text-sky-800';
  const isPending = status === 'ocr_queued' || status === 'ocr_processing';

  return (
    <div>
      <span className={`inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold ${tone}`}>
        {isPending && <LoaderCircle className="animate-spin" size={13} />}
        {status === 'ocr_completed' && reviewedAt ? 'OCR vérifié' : labels[status] || status}
      </span>
      {reviewedAt && <span className="mt-1 block text-xs text-emerald-700">Vérifiée le {formatTimestamp(reviewedAt)}</span>}
    </div>
  );
}

function Paginator({ page }: { page: Paginator<InvoiceSummary> }) {
  if (page.last_page < 2) return null;
  const linkClass = 'rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-800';
  const disabledClass = 'rounded-md border border-slate-100 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-400';

  return (
    <nav className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-3" aria-label="Pagination des factures">
      <span className="text-xs text-slate-500">{page.from ?? 0}–{page.to ?? 0} sur {page.total}</span>
      <div className="flex items-center gap-2">
        {page.prev_page_url ? <Link href={page.prev_page_url} preserveScroll className={linkClass}>Précédent</Link> : <span className={disabledClass}>Précédent</span>}
        <span className="text-xs tabular-nums text-slate-500">Page {page.current_page} / {page.last_page}</span>
        {page.next_page_url ? <Link href={page.next_page_url} preserveScroll className={linkClass}>Suivant</Link> : <span className={disabledClass}>Suivant</span>}
      </div>
    </nav>
  );
}

function formatFileSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} o`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} Ko`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} Mo`;
}

function formatAmount(amount: string | null, currency: string): string {
  if (amount === null) return '—';
  const formatted = new Intl.NumberFormat('fr-TN', { minimumFractionDigits: 3, maximumFractionDigits: 3 }).format(Number(amount));
  return `${formatted} ${currency}`;
}

function formatDate(date: string): string {
  const [year, month, day] = date.split('-').map(Number);
  return new Intl.DateTimeFormat('fr-TN', { dateStyle: 'medium' }).format(new Date(year, month - 1, day));
}

function formatTimestamp(timestamp: string): string {
  return new Intl.DateTimeFormat('fr-TN', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(timestamp));
}

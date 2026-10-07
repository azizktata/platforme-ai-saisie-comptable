import { Head, Link, router } from '@inertiajs/react';
import {
  Activity,
  AlertTriangle,
  ArrowDownToLine,
  BadgeCheck,
  Eye,
  FileText,
  LoaderCircle,
  RotateCcw,
  Sparkles,
  Trash2,
  Upload,
  type LucideIcon,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import AppShell from '../../Components/AppShell';
import ConfirmDialog from '../../Components/ConfirmDialog';
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
  confidence: number | null;
  accounting_exported_at: string | null;
  created_at: string;
  download_url: string;
};

type InvoiceStats = {
  total: number;
  to_analyze: number;
  in_analysis: number;
  to_review: number;
  validated: number;
  exported: number;
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
  maxUploadFileSizeBytes: number;
  companies: { id: number; name: string }[];
  company: { id: number; name: string; currency: string } | null;
  invoices: Paginator<InvoiceSummary> | null;
  invoiceStats: InvoiceStats | null;
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
const retryableStatuses = ['uploaded', 'ocr_failed', 'data_extraction_failed', 'accounting_analysis_failed'];
const activeStatuses = ['ocr_queued', 'ocr_processing', 'data_extraction', 'accounting_analysis'];

export default function InvoicesIndex({
  mode,
  maxUploadFileSizeBytes,
  companies,
  company,
  invoices,
  invoiceStats,
  canUploadInvoices,
  canReviewInvoices,
  auth,
}: Props) {
  const fileInput = useRef<HTMLInputElement>(null);
  const [isUploading, setIsUploading] = useState(false);
  const [isDragging, setIsDragging] = useState(false);
  const [isAnalyzingAll, setIsAnalyzingAll] = useState(false);
  const [uploadProgress, setUploadProgress] = useState({ completed: 0, total: 0 });
  const [uploadFailures, setUploadFailures] = useState<InvoiceUploadFailure[]>([]);
  const [selectedInvoiceIds, setSelectedInvoiceIds] = useState<number[]>([]);
  const [clearConfirmationOpen, setClearConfirmationOpen] = useState(false);
  const invoiceRows = invoices?.data ?? [];
  const hasPendingAnalysis = (invoiceStats?.in_analysis ?? 0) > 0
    || invoiceRows.some((invoice) => activeStatuses.includes(invoice.status));
  const reviewableSelectedIds = invoiceRows
    .filter((invoice) => selectedInvoiceIds.includes(invoice.id) && invoice.status === 'ocr_completed' && !invoice.ocr_reviewed_at)
    .map((invoice) => invoice.id);
  const allVisibleSelected = invoiceRows.length > 0 && invoiceRows.every((invoice) => selectedInvoiceIds.includes(invoice.id));

  useEffect(() => {
    if (!hasPendingAnalysis) return;

    const interval = window.setInterval(() => {
      router.reload({ only: ['invoices', 'invoiceStats'] });
    }, 5000);

    return () => window.clearInterval(interval);
  }, [hasPendingAnalysis]);

  useEffect(() => {
    setSelectedInvoiceIds([]);
  }, [company?.id, invoices?.current_page, mode]);

  useEffect(() => {
    setUploadFailures([]);
    setIsDragging(false);
    if (fileInput.current) fileInput.current.value = '';
  }, [company?.id, mode]);

  const retryAnalysis = (invoiceId: number) => {
    if (!company) return;

    router.post(`/companies/${company.id}/invoices/${invoiceId}/ocr/retry`, {}, {
      preserveScroll: true,
      onSuccess: () => toast.success('L’analyse de cette facture a été relancée.'),
      onError: () => toast.error('La relance du traitement n’a pas pu être planifiée.'),
    });
  };

  const analyzeAll = () => {
    if (!company || isAnalyzingAll || !invoiceStats?.to_analyze) return;

    setIsAnalyzingAll(true);
    router.post(`/companies/${company.id}/invoices/analyze-all`, {}, {
      preserveScroll: true,
      onSuccess: () => toast.success('Les factures éligibles ont été ajoutées à la file d’analyse.'),
      onError: () => toast.error('L’analyse groupée n’a pas pu être planifiée. Réessayez.'),
      onFinish: () => setIsAnalyzingAll(false),
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
      ['Fichier', 'Fournisseur', 'N° facture', 'Date facture', 'Total TTC', 'Devise', 'État', 'Vérifiée le'].map((value) => escapeCell(value)),
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

  const clearWorkspace = () => {
    if (!company || !invoiceRows.length) return;
    setClearConfirmationOpen(true);
  };

  const confirmClearWorkspace = () => {
    setClearConfirmationOpen(false);
    if (!company || !invoiceRows.length) return;
    router.post(`/companies/${company.id}/invoices/clear-workspace`, {}, {
      preserveScroll: true,
      onSuccess: () => {
        setSelectedInvoiceIds([]);
        toast.success('Espace vidé. Les documents restent disponibles dans l’historique complet.');
      },
      onError: () => toast.error('L’espace de travail n’a pas pu être vidé. Réessayez.'),
    });
  };

  const [deleteConfirmationOpen, setDeleteConfirmationOpen] = useState(false);
  const [deleteInvoiceId, setDeleteInvoiceId] = useState<number | null>(null);

  const deleteInvoice = (invoiceId: number) => {
    setDeleteInvoiceId(invoiceId);
    setDeleteConfirmationOpen(true);
  };

  const confirmDeleteInvoice = () => {
    setDeleteConfirmationOpen(false);
    if (!deleteInvoiceId || !company) return;
    router.post(`/companies/${company.id}/invoices/${deleteInvoiceId}`, {}, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Facture supprimée.');
        setDeleteInvoiceId(null);
        router.reload({ only: ['invoices', 'invoiceStats'] });
      },
      onError: () => toast.error('La suppression de la facture a échoué. Réessayez.'),
    });
    setDeleteInvoiceId(null);
  };

  const uploadFiles = async (batch: File[]) => {
    if (!company || !batch.length || isUploading) return;

    const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
    if (!csrfToken) {
      toast.error('Jeton de sécurité absent. Actualisez la page et réessayez.');
      return;
    }

    const failures: InvoiceUploadFailure[] = [];
    let successfulUploads = 0;

    setIsUploading(true);
    setUploadFailures([]);
    setUploadProgress({ completed: 0, total: batch.length });

    for (const [index, file] of batch.entries()) {
      const body = new FormData();
      body.append('file', file, file.name);
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
            message: payload?.errors?.file?.[0] || payload?.message || 'Le fichier n’a pas pu être importé. S’il s’agit d’un doublon, vérifiez-le dans l’historique.',
          });
        }
      } catch {
        failures.push({ filename: file.name, message: 'Erreur réseau. Vérifiez votre connexion et réessayez.' });
      }

      setUploadProgress({ completed: index + 1, total: batch.length });
    }

    setUploadFailures(failures);
    setIsUploading(false);
    if (fileInput.current) fileInput.current.value = '';

    if (successfulUploads > 0) {
      toast.success(`${successfulUploads} fichier${successfulUploads > 1 ? 's' : ''} importé${successfulUploads > 1 ? 's' : ''}. L’analyse IA démarre automatiquement.`);
      router.reload({ only: ['invoices', 'invoiceStats'] });
    }

    if (failures.length > 0) {
      toast.error(`${failures.length} fichier${failures.length > 1 ? 's' : ''} à vérifier. Les autres imports ne sont pas bloqués.`);
    }
  };

  const chooseFiles = (files: FileList | null) => {
    const nextFiles = Array.from(files ?? []);
    if (nextFiles.length === 0 || isUploading) return;
    if (nextFiles.length > MAX_FILES_PER_BATCH) {
      toast.error(`Sélectionnez au maximum ${MAX_FILES_PER_BATCH} fichiers par lot.`);
      if (fileInput.current) fileInput.current.value = '';
      return;
    }
    setUploadFailures([]);
    setUploadProgress({ completed: 0, total: 0 });
    if (fileInput.current) fileInput.current.value = '';
    void uploadFiles(nextFiles);
  };

  const handleDrop = (event: React.DragEvent<HTMLDivElement>) => {
    event.preventDefault();
    setIsDragging(false);
    if (!isUploading) chooseFiles(event.dataTransfer.files);
  };

  const onLogout = auth?.user ? () => router.post('/logout', {}, {
    onSuccess: () => toast.success('Déconnexion réussie.'),
    onError: () => toast.error('La déconnexion a échoué. Réessayez.'),
  }) : undefined;

  return (
    <AppShell
      activeSection="invoices"
      cabinetName={auth?.cabinet?.name}
      canManageCabinet={auth?.canManageCabinet}
      user={auth?.user}
      onLogout={onLogout}
    >
      <Head title={mode === 'history' ? `Historique · ${company?.name || 'Factures'}` : 'Factures'} />
      <section className="mx-auto max-w-7xl space-y-6">
        <header className="flex flex-wrap items-end justify-between gap-4">
          <div className="min-w-0">
            {company && <p className="text-xs font-semibold uppercase tracking-[0.18em] text-teal-700">{company.name}</p>}
            <h1 className="mt-2 text-3xl font-semibold tracking-tight text-slate-950">
              {mode === 'history' ? 'Historique des factures' : 'Factures fournisseurs'}
            </h1>
            <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
              {mode === 'history'
                ? 'Ouvrez un document pour comparer l’original, les données extraites et les propositions comptables générées.'
                : 'Importez vos documents : les données sont reconnues puis une proposition comptable est préparée pour votre revue.'}
            </p>
          </div>

          <div className="flex flex-wrap items-end gap-2">
            {mode === 'workspace' && (
              <label className="block min-w-52 text-xs font-semibold text-slate-600">
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
            )}
            {mode === 'workspace' && company && canReviewInvoices && (
              <button
                type="button"
                onClick={analyzeAll}
                disabled={!invoiceStats?.to_analyze || isAnalyzingAll}
                className="inline-flex items-center gap-2 rounded-lg border border-teal-200 bg-white px-4 py-2.5 text-sm font-semibold text-teal-800 shadow-sm transition hover:border-teal-300 hover:bg-teal-50 disabled:cursor-not-allowed disabled:opacity-50"
              >
                {isAnalyzingAll ? <LoaderCircle className="animate-spin" size={16} /> : <Sparkles size={16} />}
                {isAnalyzingAll ? 'Mise en file…' : `Tout analyser${invoiceStats?.to_analyze ? ` · ${invoiceStats.to_analyze}` : ''}`}
              </button>
            )}
            {mode === 'workspace' && company && <Link href={`/companies/${company.id}/invoices`} className="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-800">Historique complet</Link>}
            {mode === 'history' && company && <Link href={`/invoices?company_id=${company.id}`} className="inline-flex items-center gap-2 rounded-lg border border-teal-200 bg-teal-50 px-4 py-2.5 text-sm font-semibold text-teal-800 hover:bg-teal-100">Espace de travail</Link>}
          </div>
        </header>

        {company && invoiceStats && <InvoiceStatsCards stats={invoiceStats} />}

        {mode === 'workspace' && company && canUploadInvoices ? (
          <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <p className="mb-3 text-xs text-slate-500">PDF, JPG, JPEG ou PNG · {formatFileSize(maxUploadFileSizeBytes)} maximum par fichier · jusqu’à {MAX_FILES_PER_BATCH} fichiers par lot.</p>

            <input
              ref={fileInput}
              type="file"
              multiple
              accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
              disabled={isUploading}
              onChange={(event) => chooseFiles(event.target.files)}
              className="sr-only"
              aria-label="Choisir les factures à importer"
            />

            <div
              onDragEnter={(event) => { event.preventDefault(); if (!isUploading) setIsDragging(true); }}
              onDragOver={(event) => { event.preventDefault(); if (!isUploading) setIsDragging(true); }}
              onDragLeave={(event) => { if (event.currentTarget === event.target) setIsDragging(false); }}
              onDrop={handleDrop}
              className={`mt-5 rounded-xl border-2 border-dashed px-5 py-8 text-center transition sm:py-10 ${isDragging ? 'border-teal-500 bg-teal-50' : 'border-slate-300 bg-slate-50/80 hover:border-teal-300 hover:bg-teal-50/50'} ${isUploading ? 'pointer-events-none opacity-60' : ''}`}
            >
              <span className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-white text-teal-700 shadow-sm"><Upload size={21} /></span>
              <p className="mt-3 text-sm font-semibold text-slate-900">Glissez-déposez vos factures ici</p>
              <p className="mt-1 text-xs text-slate-500">ou sélectionnez des fichiers depuis votre appareil</p>
              <button
                type="button"
                disabled={isUploading}
                onClick={() => fileInput.current?.click()}
                className="mt-4 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:border-teal-300 hover:text-teal-800 disabled:cursor-not-allowed"
              >
                Parcourir les fichiers
              </button>
            </div>

            {isUploading && (
              <div className="mt-4" role="status" aria-live="polite">
                <div className="flex items-center justify-between text-xs font-medium text-slate-600">
                  <span>Import en cours — un fichier à la fois</span><span>{uploadProgress.completed} / {uploadProgress.total}</span>
                </div>
                <div className="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                  <div className="h-full rounded-full bg-teal-600 transition-all" style={{ width: `${uploadProgress.total ? (uploadProgress.completed / uploadProgress.total) * 100 : 0}%` }} />
                </div>
              </div>
            )}

            {isUploading && <p className="mt-3 text-xs font-medium text-teal-800">Les fichiers sont importés et le traitement démarre automatiquement.</p>}

            {uploadFailures.length > 0 && (
              <div className="mt-4 rounded-xl border border-red-200 bg-red-50 p-4" role="alert">
                <p className="flex items-center gap-2 text-sm font-semibold text-red-900"><AlertTriangle size={16} /> Certains fichiers n’ont pas été importés</p>
                <ul className="mt-2 space-y-1 text-xs leading-5 text-red-800">
                  {uploadFailures.map((failure, index) => <li key={`${failure.filename}-${index}`}><strong>{failure.filename} :</strong> {failure.message}</li>)}
                </ul>
              </div>
            )}
          </section>
        ) : mode === 'workspace' && company ? (
          <div className="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-600 shadow-sm">
            Votre accès permet de consulter les factures et de télécharger leurs documents, mais pas d’en importer ni de lancer leur analyse.
          </div>
        ) : mode === 'workspace' ? (
          <div className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center text-sm text-slate-600">
            {companies.length ? 'Sélectionnez une société pour afficher les factures et déposer des documents.' : 'Aucune société n’est accessible à votre compte. Demandez un accès à un administrateur de cabinet.'}
          </div>
        ) : null}

        {company && invoices && (
          <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="flex flex-wrap items-end justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-6">
              <div>
                <h2 className="text-lg font-semibold text-slate-950">Factures de {company.name}</h2>
                <p className="mt-1 text-sm text-slate-500">Ouvrez une facture pour contrôler l’original, les champs extraits et la proposition comptable.</p>
              </div>
              <div className="flex items-center gap-2">
                {mode === 'workspace' && canReviewInvoices && invoices.total > 0 && <button type="button" onClick={clearWorkspace} className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:border-amber-300 hover:text-amber-800">Vider mon espace</button>}
                <span className="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600">{invoices.total} document{invoices.total === 1 ? '' : 's'}</span>
              </div>
            </div>

            {mode === 'workspace' && selectedInvoiceIds.length > 0 && (
              <div className="flex flex-wrap items-center justify-between gap-3 border-b border-teal-100 bg-teal-50/70 px-5 py-3">
                <p className="text-xs font-semibold text-teal-900">{selectedInvoiceIds.length} facture{selectedInvoiceIds.length > 1 ? 's' : ''} sélectionnée{selectedInvoiceIds.length > 1 ? 's' : ''}</p>
                <div className="flex flex-wrap gap-2">
                  <button type="button" onClick={exportSelected} disabled={!selectedInvoiceIds.length} className="rounded-md border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:border-teal-300 disabled:cursor-not-allowed disabled:opacity-50">Exporter les informations CSV</button>
                  {canReviewInvoices && reviewableSelectedIds.length > 0 && (
                    <button type="button" onClick={reviewSelected} className="rounded-md bg-teal-700 px-3 py-2 text-xs font-semibold text-white hover:bg-teal-800">Marquer les extractions vérifiées ({reviewableSelectedIds.length})</button>
                  )}
                </div>
              </div>
            )}

            {invoiceRows.length > 0 ? (
              <div className="overflow-x-auto">
                <table className="min-w-[1000px] w-full text-left text-sm">
                  <thead className="bg-slate-50 text-[11px] uppercase tracking-wide text-slate-500">
                    <tr>
                      {mode === 'workspace' && <th className="px-4 py-3"><input type="checkbox" checked={allVisibleSelected} onChange={toggleVisibleInvoices} aria-label="Sélectionner toutes les factures de cette page" className="h-4 w-4 rounded border-slate-300 text-teal-700 focus:ring-teal-600" /></th>}
                      <th className="px-5 py-3 font-semibold">Document</th>
                      <th className="px-5 py-3 font-semibold">Fournisseur</th>
                      <th className="px-5 py-3 font-semibold">N° facture</th>
                      <th className="px-5 py-3 font-semibold">Date</th>
                      <th className="px-5 py-3 text-right font-semibold">Total TTC</th>
                      <th className="px-5 py-3 text-right font-semibold">Confiance</th>
                      <th className="px-5 py-3 font-semibold">État</th>
                      <th className="px-5 py-3 text-right font-semibold">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {invoiceRows.map((invoice) => (
                      <tr key={invoice.id} className="transition hover:bg-slate-50/70">
                        {mode === 'workspace' && <td className="px-4 py-4"><input type="checkbox" checked={selectedInvoiceIds.includes(invoice.id)} onChange={() => toggleInvoice(invoice.id)} aria-label={`Sélectionner ${invoice.original_filename}`} className="h-4 w-4 rounded border-slate-300 text-teal-700 focus:ring-teal-600" /></td>}
                        <td className="min-w-56 px-5 py-4">
                          <Link href={`/companies/${company.id}/invoices/${invoice.id}`} className="block max-w-72 truncate font-semibold text-slate-900 hover:text-teal-800 hover:underline" title={invoice.original_filename}>{invoice.original_filename}</Link>
                          <span className="mt-1 block text-xs text-slate-400">{formatFileSize(invoice.size_bytes)} · {formatTimestamp(invoice.created_at)}</span>
                        </td>
                        <td className="px-5 py-4 text-slate-700">{invoice.supplier_name || <span className="text-slate-400">À identifier</span>}</td>
                        <td className="px-5 py-4 font-mono text-xs text-slate-600">{invoice.invoice_number || '—'}</td>
                        <td className="whitespace-nowrap px-5 py-4 text-slate-600">{invoice.invoice_date ? formatDate(invoice.invoice_date) : '—'}</td>
                        <td className="whitespace-nowrap px-5 py-4 text-right font-semibold tabular-nums text-slate-900">{formatAmount(invoice.total_amount, invoice.currency || company.currency || 'TND')}</td>
                        <td className="whitespace-nowrap px-5 py-4 text-right tabular-nums text-slate-700">{invoice.confidence === null ? <span className="text-slate-400">—</span> : `${Math.round(invoice.confidence * 100)}%`}</td>
                        <td className="min-w-40 px-5 py-4">
                          <StatusBadge status={invoice.status} reviewedAt={invoice.ocr_reviewed_at} exportedAt={invoice.accounting_exported_at} />
                          {retryableStatuses.includes(invoice.status) && (
                            <p className="mt-1 text-[11px] leading-4 text-amber-700">
                              Traitement à relancer
                            </p>
                          )}
                          {invoice.ocr_warnings.some((warning) => warning.includes('mismatch')) && <p className="mt-1 text-[11px] leading-4 text-amber-700">Montants à contrôler</p>}
                          {/* {invoice.ocr_warnings.includes('invoice_totals_unverified') && <p className="mt-1 text-[11px] leading-4 text-amber-700">Totaux à vérifier</p>} */}
                        </td>
                        <td className="whitespace-nowrap px-5 py-4 text-right">
                          <div className="flex items-center justify-end gap-1.5">
                            {canReviewInvoices && retryableStatuses.includes(invoice.status) && (
                              <button type="button" onClick={() => retryAnalysis(invoice.id)} title={retryLabel(invoice.status)} aria-label={retryLabel(invoice.status)} className="inline-flex h-9 w-9 items-center justify-center rounded-md border border-amber-200 text-amber-800 hover:bg-amber-50">
                                <RotateCcw size={15} />
                              </button>
                            )}
                            <Link href={`/companies/${company.id}/invoices/${invoice.id}`} title="Ouvrir la facture" aria-label={`Ouvrir ${invoice.original_filename}`} className="inline-flex h-9 w-9 items-center justify-center rounded-md bg-teal-700 text-white hover:bg-teal-800">
                              <Eye size={15} />
                            </Link>
                            <a href={invoice.download_url} title="Télécharger le document" className="inline-flex h-9 w-9 items-center justify-center rounded-md border border-slate-200 text-slate-700 hover:border-teal-300 hover:text-teal-800" aria-label={`Télécharger ${invoice.original_filename}`}>
                              <ArrowDownToLine size={15} />
                            </a>
                            {canReviewInvoices && (
                              <button
                                type="button"
                                onClick={() => deleteInvoice(invoice.id)}
                                title="Supprimer cette facture"
                                aria-label={`Supprimer ${invoice.original_filename}`}
                                className="inline-flex h-9 w-9 items-center justify-center rounded-md border border-red-200 text-red-600 hover:bg-red-100"
                              >
                               <Trash2 size={15} />
                              </button>
                            )}
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <div className="px-5 py-12 text-center">
                <span className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400"><FileText size={23} /></span>
                <h3 className="mt-3 font-semibold text-slate-900">Aucune facture importée</h3>
                <p className="mx-auto mt-1 max-w-lg text-sm text-slate-500">Les factures ajoutées à cette société apparaîtront ici. Déposez vos documents dans la zone d’import ci-dessus pour commencer.</p>
              </div>
            )}

            <Paginator page={invoices} />
          </section>
        )}
      </section>
      <ConfirmDialog open={clearConfirmationOpen} title="Vider l’espace de travail ?" description="Les factures resteront dans l’historique complet de la société. Cette action ne supprime aucun document." confirmLabel="Vider l’espace" destructive onCancel={() => setClearConfirmationOpen(false)} onConfirm={confirmClearWorkspace} />

  <ConfirmDialog open={deleteConfirmationOpen} title="Supprimer la facture ?" description="Cette action supprime définitivement la facture de la société. Cette opération est irréversible." confirmLabel="Supprimer" destructive onCancel={() => setDeleteConfirmationOpen(false)} onConfirm={confirmDeleteInvoice} />
    </AppShell>
  );
}

function InvoiceStatsCards({ stats }: { stats: InvoiceStats }) {
  const cards: { label: string; value: number; helper: string; icon: LucideIcon; color: string }[] = [
    { label: 'À analyser', value: stats.to_analyze, helper: 'Éligibles à une relance', icon: RotateCcw, color: 'text-amber-700 bg-amber-50' },
    { label: 'En analyse', value: stats.in_analysis, helper: 'Traitement automatique', icon: Activity, color: 'text-blue-700 bg-blue-50' },
    { label: 'À vérifier', value: stats.to_review, helper: 'Revue du comptable', icon: Eye, color: 'text-teal-700 bg-teal-50' },
    { label: 'Validées', value: stats.validated, helper: 'Écriture comptable créée', icon: BadgeCheck, color: 'text-emerald-700 bg-emerald-50' },
    { label: 'Exportées', value: stats.exported, helper: 'CSV comptable généré', icon: ArrowDownToLine, color: 'text-indigo-700 bg-indigo-50' },
  ];

  return (
    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
      {cards.map(({ label, value, helper, icon: Icon, color }) => (
        <div key={label} className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <div className="flex items-center justify-between gap-2">
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</p>
            <span className={`flex h-8 w-8 items-center justify-center rounded-lg ${color}`}><Icon size={16} /></span>
          </div>
          <p className="mt-3 text-2xl font-semibold tabular-nums text-slate-950">{value}</p>
          <p className="mt-1 text-xs text-slate-500">{helper}</p>
        </div>
      ))}
    </div>
  );
}

function StatusBadge({ status, reviewedAt, exportedAt }: { status: string; reviewedAt: string | null; exportedAt: string | null }) {
  const labels: Record<string, string> = {
    uploaded: 'À analyser',
    ocr_queued: 'En analyse',
    ocr_processing: 'En analyse',
    data_extraction: 'En analyse',
    invoice_incomplete: 'À analyser',
    accounting_analysis: 'En analyse',
    proposal_ready: 'À valider',
    accounting_validated: 'Validé',
    accounting_exported: 'Exporté',
    proposal_rejected: 'Rejeté',
    ocr_completed: 'À analyser',
    ocr_failed: 'À analyser',
    data_extraction_failed: 'À analyser',
    accounting_analysis_failed: 'À analyser',
  };
  const isFailed = ['ocr_failed', 'data_extraction_failed', 'accounting_analysis_failed'].includes(status);
  const isPending = activeStatuses.includes(status);
  const isComplete = ['proposal_ready', 'accounting_validated', 'accounting_exported', 'ocr_completed'].includes(status);
  const tone = isComplete
    ? 'bg-emerald-50 text-emerald-800'
    : isFailed
      ? 'bg-red-50 text-red-800'
      : ['invoice_incomplete', 'proposal_rejected'].includes(status)
        ? 'bg-amber-50 text-amber-900'
        : isPending
          ? 'bg-blue-50 text-blue-800'
          : 'bg-slate-100 text-slate-700';

  return (
    <div>
      <span className={`inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold ${tone}`}>
        {isPending && <LoaderCircle className="animate-spin" size={13} />}
        {status === 'ocr_completed' && reviewedAt ? 'OCR vérifié' : labels[status] || status}
      </span>
      {reviewedAt && <span className="mt-1 block text-xs text-emerald-700">Vérifiée le {formatTimestamp(reviewedAt)}</span>}
      {status === 'accounting_exported' && exportedAt && <span className="mt-1 block text-xs text-indigo-700">CSV · {formatTimestamp(exportedAt)}</span>}
    </div>
  );
}

function retryLabel(status: string): string {
  if (status === 'uploaded') return 'Lancer l’analyse';
  if (status === 'data_extraction_failed') return 'Relancer extraction';
  if (status === 'accounting_analysis_failed') return 'Relancer analyse';
  return 'Relancer OCR';
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
  const numericAmount = Number(amount);
  if (!Number.isFinite(numericAmount)) return '—';
  const formatted = new Intl.NumberFormat('fr-TN', { minimumFractionDigits: 3, maximumFractionDigits: 3 }).format(numericAmount);
  return `${formatted} ${currency}`;
}

function formatDate(date: string): string {
  const [year, month, day] = date.split('-').map(Number);
  return new Intl.DateTimeFormat('fr-TN', { dateStyle: 'medium' }).format(new Date(year, month - 1, day));
}

function formatTimestamp(timestamp: string): string {
  return new Intl.DateTimeFormat('fr-TN', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(timestamp));
}

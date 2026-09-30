import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, FileCheck2, FileText, ReceiptText } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import AppShell from '../../Components/AppShell';
import InvoiceDocumentViewer from '../../Components/InvoiceDocumentViewer';
import InvoiceReviewPanel, { type InvoiceReviewDetail } from '../../Components/InvoiceReviewPanel';
import type { SharedAuthProps } from '../../types';

type Props = {
  company: { id: number; name: string; currency: string };
  invoice: {
    id: number;
    original_filename: string;
    mime_type: string;
    preview_url: string;
    download_url: string;
  };
  back_url: string;
  auth?: SharedAuthProps;
};

export default function InvoiceShow({ company, invoice, back_url, auth }: Props) {
  const [reviewDetail, setReviewDetail] = useState<InvoiceReviewDetail | null>(null);
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
      <Head title={`Revue facture · ${invoice.original_filename}`} />
      <div className="mx-auto max-w-[1700px] space-y-4">
        <header className="flex flex-wrap items-center justify-between gap-3">
          <div className="min-w-0">
            <p className="text-xs font-semibold uppercase tracking-[0.16em] text-teal-700">{company.name}</p>
            <h1 className="mt-1 truncate text-xl font-semibold tracking-tight text-slate-950">Revue et validation de facture</h1>
          </div>
          <Link href={back_url} className="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-800">
            <ArrowLeft size={16} /> Retour aux factures
          </Link>
        </header>

        <div className="grid min-w-0 grid-cols-1 gap-4 xl:grid-cols-[minmax(0,0.96fr)_minmax(0,1.04fr)]">
          <div className="min-w-0 self-stretch">
            <div className="space-y-3 xl:sticky xl:top-4 xl:self-start">
              <div className="min-h-[420px]">
                <InvoiceDocumentViewer
                  filename={invoice.original_filename}
                  mimeType={invoice.mime_type}
                  previewUrl={invoice.preview_url}
                  downloadUrl={invoice.download_url}
                />
              </div>
              <InvoiceSnapshot detail={reviewDetail} currency={company.currency} />
            </div>
          </div>
          <InvoiceReviewPanel
            companyId={company.id}
            invoiceId={invoice.id}
            onDetailChange={setReviewDetail}
          />
        </div>
      </div>
    </AppShell>
  );
}

function InvoiceSnapshot({ detail, currency }: { detail: InvoiceReviewDetail | null; currency: string }) {
  if (!detail) {
    return (
      <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm" aria-live="polite">
        <p className="flex items-center gap-2 text-xs font-semibold text-slate-500"><FileText size={14} /> Chargement des données facture…</p>
        <div className="mt-3 grid gap-2 sm:grid-cols-3"><div className="h-10 animate-pulse rounded-lg bg-slate-100" /><div className="h-10 animate-pulse rounded-lg bg-slate-100" /><div className="h-10 animate-pulse rounded-lg bg-slate-100" /></div>
      </section>
    );
  }

  const data = detail.invoice.ocr_data;
  const hasTotalsWarning = detail.checks.totals.status === 'warning' || detail.checks.totals.status === 'pending';

  return (
    <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm" aria-label="Résumé des informations extraites">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500"><ReceiptText size={13} /> Résumé extrait · à comparer à l’original</p>
          <h2 className="mt-1 truncate text-sm font-semibold text-slate-950">{data.supplier_name || 'Fournisseur non identifié'}</h2>
          <p className="mt-0.5 text-xs text-slate-500">{data.invoice_number || 'N° facture à vérifier'} · {data.invoice_date ? formatDate(data.invoice_date) : 'Date à vérifier'}</p>
          <p className="mt-0.5 truncate text-xs text-slate-500">{data.customer_name ? `Client : ${data.customer_name}` : 'Client à vérifier'}{data.due_date ? ` · Échéance ${formatDate(data.due_date)}` : ''}</p>
          {data.description && <p className="mt-1 truncate text-xs text-slate-600" title={data.description}>{data.description}</p>}
        </div>
        <span className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-semibold text-slate-700"><FileCheck2 size={12} /> {statusLabel(detail.invoice.status)}</span>
      </div>

      <div className="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
        <SnapshotValue label="Total HT" value={data.subtotal} currency={data.currency || currency} />
        <SnapshotValue label="TVA" value={data.vat_amount} currency={data.currency || currency} />
        <SnapshotValue label="TTC" value={data.total_amount} currency={data.currency || currency} strong />
        <SnapshotValue label="Net à payer" value={data.net_to_pay_amount} currency={data.currency || currency} />
      </div>

      {hasTotalsWarning && (
        <p className="mt-3 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] leading-4 text-amber-900">
          <AlertTriangle size={13} className="mt-0.5 shrink-0" />
          {detail.checks.totals.detail}
        </p>
      )}
    </section>
  );
}

function SnapshotValue({ label, value, currency, strong = false }: { label: string; value: string | null; currency: string; strong?: boolean }) {
  return (
    <div className={`rounded-lg border p-2.5 ${strong ? 'border-teal-200 bg-teal-50' : 'border-slate-200 bg-slate-50/60'}`}>
      <p className="text-[10px] text-slate-500">{label}</p>
      <p className={`mt-1 truncate text-xs font-semibold tabular-nums ${strong ? 'text-teal-900' : 'text-slate-800'}`} title={value ? `${value} ${currency}` : undefined}>{value ? formatAmount(value, currency) : '—'}</p>
    </div>
  );
}

function formatAmount(amount: string, currency: string): string {
  const value = Number(amount);
  if (!Number.isFinite(value)) return '—';
  return `${new Intl.NumberFormat('fr-TN', { minimumFractionDigits: 3, maximumFractionDigits: 3 }).format(value)} ${currency}`;
}

function formatDate(date: string): string {
  const [year, month, day] = date.split('-').map(Number);
  return new Intl.DateTimeFormat('fr-TN', { dateStyle: 'medium' }).format(new Date(year, month - 1, day));
}

function statusLabel(status: string): string {
  const labels: Record<string, string> = {
    uploaded: 'À analyser', ocr_queued: 'En analyse', ocr_processing: 'En analyse', data_extraction: 'Extraction',
    invoice_incomplete: 'À compléter', accounting_analysis: 'Analyse comptable', proposal_ready: 'À vérifier',
    accounting_validated: 'Validée', accounting_exported: 'Exportée', proposal_rejected: 'Rejetée',
    ocr_completed: 'OCR terminé', ocr_failed: 'Échec OCR', data_extraction_failed: 'Échec extraction', accounting_analysis_failed: 'Échec analyse',
  };
  return labels[status] || status;
}

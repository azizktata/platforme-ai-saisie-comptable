import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { toast } from 'sonner';
import AppShell from '../../Components/AppShell';
import InvoiceDocumentViewer from '../../Components/InvoiceDocumentViewer';
import InvoiceReviewPanel from '../../Components/InvoiceReviewPanel';
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
      <Head title={`Revue facture · ${invoice.original_filename}`} />
      <div className="mx-auto max-w-[1600px] space-y-4">
        <header className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p className="text-xs font-semibold uppercase tracking-[0.16em] text-teal-700">{company.name}</p>
            <h1 className="mt-1 text-xl font-semibold tracking-tight text-slate-900">Revue et validation de facture</h1>
          </div>
          <Link href={back_url} className="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-800">
            <ArrowLeft size={16} /> Retour aux factures
          </Link>
        </header>

        <div className="grid grid-cols-1 gap-4 xl:h-[calc(100vh-12rem)] xl:min-h-[640px] xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
          <InvoiceDocumentViewer
            filename={invoice.original_filename}
            mimeType={invoice.mime_type}
            previewUrl={invoice.preview_url}
            downloadUrl={invoice.download_url}
          />
          <InvoiceReviewPanel companyId={company.id} invoiceId={invoice.id} />
        </div>
      </div>
    </AppShell>
  );
}

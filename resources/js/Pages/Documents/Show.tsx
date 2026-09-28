import { router, useForm } from '@inertiajs/react';
import AppShell from '../../Components/AppShell';
import DocumentReviewView from '../../Components/DocumentReviewView';
import type { DocumentDetail, DocumentFields, FlashProps, SharedAuthProps } from '../../types';

type Props = {
  document: DocumentDetail;
  auth?: SharedAuthProps;
  flash?: FlashProps;
};

function initialFields(document: DocumentDetail): DocumentFields {
  return {
    supplier_name: document.supplier_name ?? '',
    invoice_number: document.invoice_number ?? '',
    invoice_date: document.invoice_date ?? '',
    due_date: document.due_date ?? '',
    currency: document.currency || 'EUR',
    account_code: document.account_code ?? '',
    description: document.description ?? '',
    subtotal: document.subtotal == null ? '' : String(document.subtotal),
    vat_amount: document.vat_amount == null ? '' : String(document.vat_amount),
    total_amount: document.total_amount == null ? '' : String(document.total_amount),
  };
}

export default function Show({ document, auth, flash }: Props) {
  const form = useForm<DocumentFields>(initialFields(document));

  return (
    <AppShell
      activeSection="documents"
      successMessage={flash?.success}
      user={auth?.user}
      onLogout={auth?.user ? () => router.post('/logout') : undefined}
    >
      <DocumentReviewView
        document={document}
        values={form.data}
        errors={form.errors as Record<string, string>}
        onChange={(field, value) => form.setData(field, value)}
        onBack={() => router.visit('/documents')}
        onSave={() => form.put(`/documents/${document.id}`, { preserveScroll: true })}
        onPost={() => form.post(`/documents/${document.id}/post`, { preserveScroll: true })}
        isSaving={form.processing}
        isPosting={form.processing}
      />
    </AppShell>
  );
}

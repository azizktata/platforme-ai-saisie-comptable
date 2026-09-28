import { useEffect, useMemo, useState } from 'react';
import AppShell from './Components/AppShell';
import DashboardView from './Components/DashboardView';
import DocumentReviewView from './Components/DocumentReviewView';
import DocumentsView from './Components/DocumentsView';
import { demoDocuments } from './demoData';
import type { DashboardStats, DocumentDetail, DocumentFields, DocumentSummary, MonthlySpend } from './types';

const demoUser = { id: 1, name: 'Alexandre Chen', email: 'demo@example.test' };

function initialPath(): string {
  return `${window.location.pathname}${window.location.search}`;
}

function toFields(document: DocumentDetail): DocumentFields {
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

function makeMonthlySpend(documents: DocumentDetail[]): MonthlySpend[] {
  const current = new Date();

  return Array.from({ length: 6 }, (_, index) => {
    const month = new Date(current.getFullYear(), current.getMonth() - 5 + index, 1);
    const amount = documents
      .filter((document) => {
        if (document.status !== 'posted' || !document.invoice_date) return false;
        const date = new Date(`${document.invoice_date}T00:00:00`);
        return date.getFullYear() === month.getFullYear() && date.getMonth() === month.getMonth();
      })
      .reduce((sum, document) => sum + Number(document.total_amount ?? 0), 0);

    return {
      label: new Intl.DateTimeFormat('fr-FR', { month: 'short' }).format(month).replace('.', ''),
      amount,
    };
  });
}

export default function PreviewApp() {
  const [path, setPath] = useState(initialPath);
  const [documents, setDocuments] = useState<DocumentDetail[]>(demoDocuments);
  const [notice, setNotice] = useState<string | null>(null);
  const [filters, setFilters] = useState({ search: '', status: 'all' });
  const [fieldValues, setFieldValues] = useState<Record<number, DocumentFields>>({});
  const [fieldErrors, setFieldErrors] = useState<Record<number, Record<string, string>>>({});

  useEffect(() => {
    const handlePopState = () => {
      setPath(initialPath());
      setNotice(null);
    };
    window.addEventListener('popstate', handlePopState);
    return () => window.removeEventListener('popstate', handlePopState);
  }, []);

  const navigate = (href: string) => {
    window.history.pushState({}, '', href);
    setPath(initialPath());
    setNotice(null);
  };

  const uploadDocument = (file: File) => {
    const id = Math.max(0, ...documents.map((document) => document.id)) + 1;
    const document: DocumentDetail = {
      id,
      original_filename: file.name,
      supplier_name: null,
      invoice_number: null,
      invoice_date: null,
      due_date: null,
      total_amount: null,
      currency: 'EUR',
      status: 'needs_review',
      created_at: new Date().toISOString().slice(0, 10),
      file_url: null,
      mime_type: file.type || 'application/octet-stream',
      size_bytes: file.size,
      account_code: null,
      description: null,
      subtotal: null,
      vat_amount: null,
    };

    setDocuments((current) => [document, ...current]);
    window.history.pushState({}, '', '/documents');
    setPath('/documents');
    setNotice(`Document importé : ${file.name}. Vérifiez les informations avant comptabilisation.`);
  };

  const routePath = path.split('?')[0];
  const detailMatch = routePath.match(/^\/documents\/(\d+)$/);
  const selectedDocument = detailMatch
    ? documents.find((document) => document.id === Number(detailMatch[1])) ?? null
    : null;
  const activeSection = routePath === '/' ? 'overview' : 'documents';

  const stats: DashboardStats = useMemo(() => {
    const now = new Date();
    const postedThisMonth = documents.filter((document) => {
      if (document.status !== 'posted' || !document.invoice_date) return false;
      const date = new Date(`${document.invoice_date}T00:00:00`);
      return date.getFullYear() === now.getFullYear() && date.getMonth() === now.getMonth();
    });

    return {
      to_review: documents.filter((document) => document.status === 'needs_review').length,
      posted_this_month: postedThisMonth.length,
      expenses_this_month: postedThisMonth.reduce((sum, document) => sum + Number(document.total_amount ?? 0), 0),
      documents_this_month: documents.filter((document) => {
        if (!document.created_at) return false;
        const date = new Date(`${document.created_at}T00:00:00`);
        return date.getFullYear() === now.getFullYear() && date.getMonth() === now.getMonth();
      }).length,
    };
  }, [documents]);

  const monthlySpend = useMemo(() => makeMonthlySpend(documents), [documents]);
  const recentDocuments: DocumentSummary[] = documents.slice(0, 6);
  const visibleDocuments = documents.filter((document) => {
    const search = filters.search.toLowerCase();
    const matchesSearch = !search || [document.supplier_name, document.invoice_number, document.original_filename]
      .some((value) => value?.toLowerCase().includes(search));
    const matchesStatus = filters.status === 'all' || document.status === filters.status;
    return matchesSearch && matchesStatus;
  });

  const handleFieldChange = (documentId: number, field: keyof DocumentFields, value: string) => {
    const document = documents.find((item) => item.id === documentId);
    if (!document) return;

    setFieldValues((current) => ({
      ...current,
      [documentId]: { ...(current[documentId] ?? toFields(document)), [field]: value },
    }));
    setFieldErrors((current) => ({ ...current, [documentId]: {} }));
  };

  const saveDocument = (document: DocumentDetail) => {
    const values = fieldValues[document.id] ?? toFields(document);
    setDocuments((current) => current.map((item) => item.id === document.id ? {
      ...item,
      supplier_name: values.supplier_name,
      invoice_number: values.invoice_number,
      invoice_date: values.invoice_date,
      due_date: values.due_date || null,
      currency: values.currency.toUpperCase(),
      account_code: values.account_code,
      description: values.description,
      subtotal: values.subtotal,
      vat_amount: values.vat_amount,
      total_amount: values.total_amount,
    } : item));
    setNotice('Les informations de la facture ont été enregistrées.');
  };

  const postDocument = (document: DocumentDetail) => {
    const values = fieldValues[document.id] ?? toFields(document);
    const errors: Record<string, string> = {};
    ['supplier_name', 'invoice_number', 'invoice_date', 'account_code', 'description', 'subtotal', 'vat_amount', 'total_amount'].forEach((field) => {
      if (!values[field as keyof DocumentFields]) errors[field] = 'Ce champ est obligatoire.';
    });

    const subtotal = Number(values.subtotal);
    const vatAmount = Number(values.vat_amount);
    const totalAmount = Number(values.total_amount);

    if (values.subtotal && (!Number.isFinite(subtotal) || subtotal < 0)) errors.subtotal = 'Le montant HT doit être positif ou nul.';
    if (values.vat_amount && (!Number.isFinite(vatAmount) || vatAmount < 0)) errors.vat_amount = 'La TVA doit être positive ou nulle.';
    if (values.total_amount && (!Number.isFinite(totalAmount) || totalAmount <= 0)) errors.total_amount = 'Le montant TTC doit être supérieur à zéro.';

    const expected = subtotal + vatAmount;
    if (values.total_amount && Math.abs(expected - totalAmount) > 0.01) {
      errors.total_amount = 'Le montant TTC doit correspondre au montant HT plus la TVA.';
    }
    if (Object.keys(errors).length > 0) {
      setFieldErrors((current) => ({ ...current, [document.id]: errors }));
      return;
    }

    saveDocument(document);
    setDocuments((current) => current.map((item) => item.id === document.id ? { ...item, status: 'posted' } : item));
    setNotice('L’écriture a été comptabilisée.');
  };

  return (
    <AppShell
      activeSection={activeSection}
      onNavigate={navigate}
      pendingReviewCount={stats.to_review}
      successMessage={notice}
      user={demoUser}
    >
      {routePath === '/' && (
        <DashboardView
          stats={stats}
          recentDocuments={recentDocuments}
          monthlySpend={monthlySpend}
          userName={demoUser.name}
          onOpenDocument={(id) => navigate(`/documents/${id}`)}
          onViewDocuments={() => navigate('/documents')}
          onUpload={uploadDocument}
        />
      )}

      {routePath === '/documents' && (
        <DocumentsView
          documents={visibleDocuments}
          filters={filters}
          onFilter={(search, status) => setFilters({ search, status })}
          onOpenDocument={(id) => navigate(`/documents/${id}`)}
          onUpload={uploadDocument}
          successMessage={notice}
        />
      )}

      {selectedDocument && (
        <DocumentReviewView
          document={selectedDocument}
          values={fieldValues[selectedDocument.id] ?? toFields(selectedDocument)}
          errors={fieldErrors[selectedDocument.id] ?? {}}
          onChange={(field, value) => handleFieldChange(selectedDocument.id, field, value)}
          onBack={() => navigate('/documents')}
          onSave={() => saveDocument(selectedDocument)}
          onPost={() => postDocument(selectedDocument)}
        />
      )}

      {routePath.startsWith('/documents/') && !selectedDocument && (
        <div className="not-found-panel"><div className="empty-state__icon">404</div><h1>Document introuvable</h1><button className="button button--primary" onClick={() => navigate('/documents')}>Retour aux documents</button></div>
      )}
    </AppShell>
  );
}

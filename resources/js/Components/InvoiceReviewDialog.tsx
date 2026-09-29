import { router } from '@inertiajs/react';
import { AlertTriangle, ClipboardCheck, FileText, LoaderCircle, Plus, Save, Trash2, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';

type InvoiceLineData = {
  reference: string | null;
  description: string | null;
  quantity: string | null;
  unit_price: string | null;
  subtotal: string | null;
  vat_rate: string | null;
  vat_amount: string | null;
  fodec_rate: string | null;
  fodec_amount: string | null;
  other_tax_amount: string | null;
  total_amount: string | null;
};

type InvoiceData = {
  supplier_name: string | null;
  supplier_tax_identifier: string | null;
  supplier_address: string | null;
  customer_name: string | null;
  customer_tax_identifier: string | null;
  invoice_number: string | null;
  purchase_order_reference: string | null;
  invoice_date: string | null;
  due_date: string | null;
  currency: string | null;
  vat_rate: string | null;
  fodec_rate: string | null;
  subtotal: string | null;
  vat_amount: string | null;
  fodec_amount: string | null;
  other_tax_amount: string | null;
  stamp_amount: string | null;
  withholding_rate: string | null;
  withholding_amount: string | null;
  total_amount: string | null;
  payment_terms: string | null;
  bank_name: string | null;
  bank_account_reference: string | null;
  description: string | null;
  lines: InvoiceLineData[];
};

type ProposalLine = {
  id?: number;
  chart_account_id: number;
  chart_account_code?: string | null;
  chart_account_label?: string | null;
  third_party_id: number | null;
  third_party_code?: string | null;
  third_party_name?: string | null;
  analytical_account_id: number | null;
  analytical_account_code?: string | null;
  analytical_account_label?: string | null;
  description: string | null;
  debit: string;
  credit: string;
  confidence: string | number | null;
};

type Proposal = {
  id: number;
  status: string;
  journal_id: number;
  journal_code: string | null;
  journal_label: string | null;
  entry_description: string;
  explanation: string | null;
  warnings: string[];
  model: string;
  modified_at: string | null;
  journal_entry_id: number | null;
  lines: ProposalLine[];
};

type InvoiceDetail = {
  id: number;
  status: string;
  original_filename: string;
  ocr_text: string | null;
  ocr_data: InvoiceData;
  ocr_warnings: string[];
  ocr_error_message: string | null;
  ocr_model: string | null;
  extraction_model: string | null;
  extraction_corrected_at: string | null;
};

type Options = {
  chart_accounts: { id: number; code: string; label: string; account_type: string }[];
  journals: { id: number; code: string; label: string; journal_type: string }[];
  third_parties: { id: number; code: string; name: string }[];
  analytical_accounts: { id: number; code: string; label: string }[];
};

type DetailResponse = {
  invoice: InvoiceDetail;
  proposal: Proposal | null;
  options: Options;
  can_manage: boolean;
};

type Tab = 'ocr' | 'invoice' | 'proposal';

type Props = {
  companyId: number;
  invoiceId: number;
  initialTab: Tab;
  onClose: () => void;
  onChanged: () => void;
};

const editableInvoiceStatuses = ['invoice_incomplete', 'data_extraction_failed', 'accounting_analysis_failed', 'proposal_ready', 'proposal_rejected'];
const inputClass = 'mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100';
const selectClass = inputClass;

export default function InvoiceReviewDialog({ companyId, invoiceId, initialTab, onClose, onChanged }: Props) {
  const [tab, setTab] = useState<Tab>(initialTab);
  const [detail, setDetail] = useState<DetailResponse | null>(null);
  const [invoiceData, setInvoiceData] = useState<InvoiceData | null>(null);
  const [proposalForm, setProposalForm] = useState<Proposal | null>(null);
  const [loading, setLoading] = useState(true);
  const [savingInvoice, setSavingInvoice] = useState(false);
  const [savingProposal, setSavingProposal] = useState(false);
  const [approving, setApproving] = useState(false);
  const [rejecting, setRejecting] = useState(false);
  const observedStatus = useRef<string | null>(null);

  const loadDetails = useCallback(async (showLoader = true, showError = true) => {
    if (showLoader) setLoading(true);
    try {
      const response = await fetch(`/companies/${companyId}/invoices/${invoiceId}/details`, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      if (!response.ok) throw new Error('La facture n’a pas pu être chargée.');
      const data = await response.json() as DetailResponse;
      const previousStatus = observedStatus.current;
      observedStatus.current = data.invoice.status;

      if (previousStatus !== null && previousStatus !== data.invoice.status) {
        if (data.invoice.status === 'proposal_ready') {
          toast.success('La proposition comptable est disponible. Vérifiez les alertes et les lignes avant validation.');
        } else if (['ocr_failed', 'data_extraction_failed', 'accounting_analysis_failed'].includes(data.invoice.status)) {
          toast.error(data.invoice.ocr_error_message || 'Le traitement de la facture a échoué.');
        }
      }

      setDetail(data);
      setInvoiceData(data.invoice.ocr_data);
      setProposalForm(data.proposal ? structuredClone(data.proposal) : null);
    } catch (error) {
      if (showError) toast.error(error instanceof Error ? error.message : 'La facture n’a pas pu être chargée.');
    } finally {
      if (showLoader) setLoading(false);
    }
  }, [companyId, invoiceId]);

  useEffect(() => {
    void loadDetails();
  }, [loadDetails]);

  useEffect(() => {
    const pendingStatuses = ['ocr_queued', 'ocr_processing', 'data_extraction', 'accounting_analysis'];
    if (!detail || !pendingStatuses.includes(detail.invoice.status)) return;

    const interval = window.setInterval(() => {
      void loadDetails(false, false);
    }, 5000);

    return () => window.clearInterval(interval);
  }, [detail?.invoice.status, loadDetails]);

  useEffect(() => {
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape' && !savingInvoice && !savingProposal && !approving) onClose();
    };
    document.addEventListener('keydown', onKeyDown);
    return () => document.removeEventListener('keydown', onKeyDown);
  }, [onClose, savingInvoice, savingProposal, approving]);

  const updateInvoiceField = <K extends keyof InvoiceData>(key: K, value: InvoiceData[K]) => {
    setInvoiceData((current) => current ? { ...current, [key]: value } : current);
  };

  const updateInvoiceLine = <K extends keyof InvoiceLineData>(index: number, key: K, value: InvoiceLineData[K]) => {
    setInvoiceData((current) => {
      if (!current) return current;
      const lines = [...current.lines];
      lines[index] = { ...lines[index], [key]: value };
      return { ...current, lines };
    });
  };

  const saveInvoiceData = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!detail || !invoiceData || savingInvoice) return;
    setSavingInvoice(true);
    router.put(`/companies/${companyId}/invoices/${invoiceId}/extraction`, { invoice_data: invoiceData }, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Les données de la facture sont enregistrées.');
        onChanged();
        void loadDetails();
      },
      onError: (errors) => toast.error(firstError(errors) || 'Les données de la facture n’ont pas pu être enregistrées.'),
      onFinish: () => setSavingInvoice(false),
    });
  };

  const updateProposalField = <K extends keyof Proposal>(key: K, value: Proposal[K]) => {
    setProposalForm((current) => current ? { ...current, [key]: value } : current);
  };

  const updateProposalLine = <K extends keyof ProposalLine>(index: number, key: K, value: ProposalLine[K]) => {
    setProposalForm((current) => {
      if (!current) return current;
      const lines = [...current.lines];
      lines[index] = { ...lines[index], [key]: value };
      return { ...current, lines };
    });
  };

  const saveProposal = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!proposalForm || savingProposal) return;
    setSavingProposal(true);
    router.put(`/companies/${companyId}/invoices/${invoiceId}/proposal`, {
      journal_id: proposalForm.journal_id,
      entry_description: proposalForm.entry_description,
      lines: proposalForm.lines.map((line) => ({
        id: line.id ?? null,
        chart_account_id: Number(line.chart_account_id),
        third_party_id: line.third_party_id ? Number(line.third_party_id) : null,
        analytical_account_id: line.analytical_account_id ? Number(line.analytical_account_id) : null,
        description: line.description,
        debit: line.debit,
        credit: line.credit,
      })),
    }, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('La proposition comptable est enregistrée.');
        onChanged();
        void loadDetails();
      },
      onError: (errors) => toast.error(firstError(errors) || 'La proposition comptable n’a pas pu être enregistrée.'),
      onFinish: () => setSavingProposal(false),
    });
  };

  const approveProposal = () => {
    if (approving) return;
    setApproving(true);
    router.post(`/companies/${companyId}/invoices/${invoiceId}/proposal/approve`, {}, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Proposition validée. L’écriture comptable est maintenant créée.');
        onChanged();
        void loadDetails();
      },
      onError: (errors) => toast.error(firstError(errors) || 'La proposition ne peut pas encore être validée.'),
      onFinish: () => setApproving(false),
    });
  };

  const rejectProposal = () => {
    if (rejecting || !window.confirm('Rejeter cette proposition ? Aucune écriture comptable ne sera créée.')) return;
    setRejecting(true);
    router.post(`/companies/${companyId}/invoices/${invoiceId}/proposal/reject`, {}, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Proposition rejetée. Aucune écriture n’a été créée.');
        onChanged();
        void loadDetails();
      },
      onError: (errors) => toast.error(firstError(errors) || 'La proposition n’a pas pu être rejetée.'),
      onFinish: () => setRejecting(false),
    });
  };

  const canEditInvoice = Boolean(detail?.can_manage && detail.invoice && editableInvoiceStatuses.includes(detail.invoice.status));
  const canEditProposal = Boolean(detail?.can_manage && detail.proposal?.status === 'ready' && detail.invoice.status === 'proposal_ready');
  const proposalNeedsSave = Boolean(detail?.proposal && proposalForm && JSON.stringify(detail.proposal) !== JSON.stringify(proposalForm));

  return (
    <div className="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/50 p-3 sm:p-6" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose(); }}>
      <section role="dialog" aria-modal="true" aria-labelledby="invoice-review-title" className="flex max-h-[94vh] w-full max-w-6xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl">
        <header className="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-6">
          <div className="min-w-0">
            <p className="text-xs font-semibold uppercase tracking-wide text-teal-700">Traitement de la facture</p>
            <h2 id="invoice-review-title" className="mt-1 truncate text-lg font-semibold text-slate-900">
              {detail?.invoice.original_filename || 'Détails de la facture'}
            </h2>
            {detail && <p className="mt-1 text-xs text-slate-500">{statusLabel(detail.invoice.status)}{detail.invoice.ocr_model ? ` · OCR : ${detail.invoice.ocr_model}` : ''}{detail.invoice.extraction_model ? ` · Extraction : ${detail.invoice.extraction_model}` : ''}</p>}
          </div>
          <button type="button" onClick={onClose} aria-label="Fermer la fenêtre" className="rounded-md p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900"><X size={19} /></button>
        </header>

        <nav className="flex flex-wrap gap-1 border-b border-slate-200 px-4 pt-2" aria-label="Sections de la facture">
          {([
            ['ocr', 'Texte OCR'],
            ['invoice', 'Données facture'],
            ['proposal', 'Proposition comptable'],
          ] as const).map(([value, label]) => (
            <button key={value} type="button" onClick={() => setTab(value)} className={`rounded-t-md border-b-2 px-3 py-2.5 text-sm font-semibold ${tab === value ? 'border-teal-700 text-teal-800' : 'border-transparent text-slate-500 hover:text-slate-800'}`}>
              {label}
            </button>
          ))}
        </nav>

        {loading || !detail || !invoiceData ? (
          <div className="flex min-h-64 items-center justify-center gap-2 text-sm text-slate-500" role="status"><LoaderCircle className="animate-spin" size={18} /> Chargement des informations…</div>
        ) : (
          <div className="min-h-0 flex-1 overflow-y-auto p-4 sm:p-6">
            {tab === 'ocr' && (
              <section>
                <div className="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-800"><FileText size={17} className="text-teal-700" /> Texte reconnu par OCR</div>
                {invoiceData && detail.invoice.ocr_text ? (
                  <pre className="max-h-[65vh] overflow-auto whitespace-pre-wrap break-words rounded-lg border border-slate-200 bg-slate-50 p-4 font-mono text-xs leading-6 text-slate-800">{detail.invoice.ocr_text}</pre>
                ) : (
                  <p className="rounded-lg bg-slate-50 p-4 text-sm text-slate-600">Aucun texte OCR n’est disponible pour cette étape du traitement.{detail.invoice.ocr_error_message ? ` ${detail.invoice.ocr_error_message}` : ''}</p>
                )}
                {detail.invoice.ocr_warnings.length > 0 && <WarningList warnings={detail.invoice.ocr_warnings} />}
              </section>
            )}

            {tab === 'invoice' && (
              <section className="space-y-4">
                {detail.invoice.ocr_warnings.length > 0 && <WarningList warnings={detail.invoice.ocr_warnings} />}
                {detail.invoice.extraction_corrected_at && <p className="text-xs text-slate-500">Données corrigées le {new Intl.DateTimeFormat('fr-TN', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(detail.invoice.extraction_corrected_at))}.</p>}
                {canEditInvoice ? (
                  <form onSubmit={saveInvoiceData} className="space-y-5">
                    <fieldset disabled={savingInvoice} className="space-y-5 disabled:opacity-70">
                      <FieldGroup title="Identification">
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                          <TextField label="Fournisseur *" value={invoiceData.supplier_name} onChange={(value) => updateInvoiceField('supplier_name', value)} />
                          <TextField label="Matricule fiscal fournisseur" value={invoiceData.supplier_tax_identifier} onChange={(value) => updateInvoiceField('supplier_tax_identifier', value)} />
                          <TextField label="Numéro de facture *" value={invoiceData.invoice_number} onChange={(value) => updateInvoiceField('invoice_number', value)} />
                          <TextField label="Client facturé" value={invoiceData.customer_name} onChange={(value) => updateInvoiceField('customer_name', value)} />
                          <TextField label="Matricule fiscal client" value={invoiceData.customer_tax_identifier} onChange={(value) => updateInvoiceField('customer_tax_identifier', value)} />
                          <TextField label="Référence commande" value={invoiceData.purchase_order_reference} onChange={(value) => updateInvoiceField('purchase_order_reference', value)} />
                        </div>
                        <TextAreaField label="Adresse fournisseur" value={invoiceData.supplier_address} onChange={(value) => updateInvoiceField('supplier_address', value)} />
                      </FieldGroup>

                      <FieldGroup title="Dates, description et devise">
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                          <TextField label="Date facture *" type="date" value={invoiceData.invoice_date} onChange={(value) => updateInvoiceField('invoice_date', value)} />
                          <TextField label="Date d’échéance" type="date" value={invoiceData.due_date} onChange={(value) => updateInvoiceField('due_date', value)} />
                          <TextField label="Devise ISO *" value={invoiceData.currency} onChange={(value) => updateInvoiceField('currency', value?.toUpperCase() ?? null)} placeholder="TND" maxLength={3} />
                          <TextField label="Conditions de paiement" value={invoiceData.payment_terms} onChange={(value) => updateInvoiceField('payment_terms', value)} />
                        </div>
                        <TextAreaField label="Description de la facture *" value={invoiceData.description} onChange={(value) => updateInvoiceField('description', value)} />
                      </FieldGroup>

                      <FieldGroup title="Montants et taxes">
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                          <DecimalField label="Sous-total HT" value={invoiceData.subtotal} onChange={(value) => updateInvoiceField('subtotal', value)} />
                          <DecimalField label="Taux TVA (%)" value={invoiceData.vat_rate} onChange={(value) => updateInvoiceField('vat_rate', value)} />
                          <DecimalField label="Montant TVA" value={invoiceData.vat_amount} onChange={(value) => updateInvoiceField('vat_amount', value)} />
                          <DecimalField label="Taux FODEC (%)" value={invoiceData.fodec_rate} onChange={(value) => updateInvoiceField('fodec_rate', value)} />
                          <DecimalField label="Montant FODEC" value={invoiceData.fodec_amount} onChange={(value) => updateInvoiceField('fodec_amount', value)} />
                          <DecimalField label="Autres taxes" value={invoiceData.other_tax_amount} onChange={(value) => updateInvoiceField('other_tax_amount', value)} />
                          <DecimalField label="Timbre fiscal" value={invoiceData.stamp_amount} onChange={(value) => updateInvoiceField('stamp_amount', value)} />
                          <DecimalField label="Taux retenue (%)" value={invoiceData.withholding_rate} onChange={(value) => updateInvoiceField('withholding_rate', value)} />
                          <DecimalField label="Retenue à la source" value={invoiceData.withholding_amount} onChange={(value) => updateInvoiceField('withholding_amount', value)} />
                          <DecimalField label="Total à payer *" value={invoiceData.total_amount} onChange={(value) => updateInvoiceField('total_amount', value)} />
                        </div>
                      </FieldGroup>

                      <FieldGroup title={`Lignes de facture (${invoiceData.lines.length})`}>
                        <div className="space-y-4">
                          {invoiceData.lines.map((line, index) => (
                            <div key={index} className="rounded-lg border border-slate-200 bg-slate-50 p-3">
                              <div className="mb-3 flex items-center justify-between">
                                <span className="text-xs font-semibold uppercase tracking-wide text-slate-600">Ligne {index + 1}</span>
                                <button type="button" onClick={() => setInvoiceData((current) => current ? { ...current, lines: current.lines.filter((_, lineIndex) => lineIndex !== index) } : current)} className="inline-flex items-center gap-1 text-xs font-semibold text-red-700 hover:text-red-900"><Trash2 size={13} /> Supprimer</button>
                              </div>
                              <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                <TextField label="Référence" value={line.reference} onChange={(value) => updateInvoiceLine(index, 'reference', value)} />
                                <DecimalField label="Quantité" value={line.quantity} onChange={(value) => updateInvoiceLine(index, 'quantity', value)} />
                                <DecimalField label="Prix unitaire" value={line.unit_price} onChange={(value) => updateInvoiceLine(index, 'unit_price', value)} />
                                <DecimalField label="Sous-total" value={line.subtotal} onChange={(value) => updateInvoiceLine(index, 'subtotal', value)} />
                                <DecimalField label="Taux TVA (%)" value={line.vat_rate} onChange={(value) => updateInvoiceLine(index, 'vat_rate', value)} />
                                <DecimalField label="Montant TVA" value={line.vat_amount} onChange={(value) => updateInvoiceLine(index, 'vat_amount', value)} />
                                <DecimalField label="Taux FODEC (%)" value={line.fodec_rate} onChange={(value) => updateInvoiceLine(index, 'fodec_rate', value)} />
                                <DecimalField label="Montant FODEC" value={line.fodec_amount} onChange={(value) => updateInvoiceLine(index, 'fodec_amount', value)} />
                                <DecimalField label="Autres taxes" value={line.other_tax_amount} onChange={(value) => updateInvoiceLine(index, 'other_tax_amount', value)} />
                                <DecimalField label="Total ligne" value={line.total_amount} onChange={(value) => updateInvoiceLine(index, 'total_amount', value)} />
                              </div>
                              <TextAreaField label="Description de la ligne" value={line.description} onChange={(value) => updateInvoiceLine(index, 'description', value)} />
                            </div>
                          ))}
                          <button type="button" onClick={() => setInvoiceData((current) => current ? { ...current, lines: [...current.lines, emptyLine()] } : current)} className="inline-flex items-center gap-1.5 rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:border-teal-400 hover:text-teal-800"><Plus size={14} /> Ajouter une ligne</button>
                        </div>
                      </FieldGroup>

                      <FieldGroup title="Coordonnées bancaires">
                        <div className="grid gap-3 sm:grid-cols-2">
                          <TextField label="Banque" value={invoiceData.bank_name} onChange={(value) => updateInvoiceField('bank_name', value)} />
                          <TextField label="RIB / IBAN" value={invoiceData.bank_account_reference} onChange={(value) => updateInvoiceField('bank_account_reference', value)} />
                        </div>
                      </FieldGroup>
                    </fieldset>
                    <button type="submit" disabled={savingInvoice} className="inline-flex items-center gap-2 rounded-md bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-60">
                      {savingInvoice ? <LoaderCircle className="animate-spin" size={16} /> : <Save size={16} />}
                      {savingInvoice ? 'Enregistrement…' : 'Enregistrer les données et analyser'}
                    </button>
                    <p className="text-xs text-slate-500">L’analyse comptable démarre automatiquement dès que les champs obligatoires sont présents. Une correction des données relance l’analyse ; les versions précédentes restent conservées pour audit.</p>
                  </form>
                ) : (
                  <InvoiceDataSummary data={invoiceData} />
                )}
              </section>
            )}

            {tab === 'proposal' && (
              <section className="space-y-4">
                {detail.proposal ? (
                  <>
                    {detail.invoice.status === 'accounting_analysis' && detail.proposal.status === 'superseded' && <p className="rounded-lg border border-sky-200 bg-sky-50 p-3 text-sm text-sky-900">Une nouvelle proposition est en cours de génération. La version précédente reste conservée pour audit.</p>}
                    <div className="rounded-lg border border-slate-200 bg-slate-50 p-4">
                      <div className="flex flex-wrap items-center justify-between gap-2">
                        <div>
                          <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Journal · {detail.proposal.journal_code || 'à sélectionner'}</p>
                          <h3 className="mt-1 font-semibold text-slate-900">{detail.proposal.journal_label || 'Proposition comptable'}</h3>
                        </div>
                        <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${detail.proposal.status === 'approved' ? 'bg-emerald-100 text-emerald-800' : detail.proposal.status === 'rejected' ? 'bg-red-100 text-red-800' : detail.proposal.status === 'superseded' ? 'bg-slate-200 text-slate-700' : 'bg-amber-100 text-amber-900'}`}>{detail.proposal.status === 'approved' ? 'Validée' : detail.proposal.status === 'rejected' ? 'Rejetée' : detail.proposal.status === 'superseded' ? 'Remplacée' : 'À vérifier'}</span>
                      </div>
                      <p className="mt-3 text-sm text-slate-700">{detail.proposal.explanation}</p>
                      <p className="mt-2 text-xs text-slate-500">Modèle OpenRouter : {detail.proposal.model}{detail.proposal.modified_at ? ` · dernière correction le ${new Intl.DateTimeFormat('fr-TN', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(detail.proposal.modified_at))}` : ''}</p>
                      {detail.proposal.journal_entry_id && <p className="mt-2 text-sm font-semibold text-emerald-800">Écriture créée · n° {detail.proposal.journal_entry_id}</p>}
                    </div>
                    {detail.proposal.warnings.length > 0 && <WarningList warnings={detail.proposal.warnings} />}

                    {canEditProposal && proposalForm ? (
                      <form onSubmit={saveProposal} className="space-y-4">
                        <div className="grid gap-3 sm:grid-cols-2">
                          <label className="block text-xs font-semibold text-slate-700">Journal
                            <select className={selectClass} value={proposalForm.journal_id} onChange={(event) => updateProposalField('journal_id', Number(event.target.value))}>
                              {detail.options.journals.map((journal) => <option key={journal.id} value={journal.id}>{journal.code} — {journal.label}</option>)}
                            </select>
                          </label>
                          <TextField label="Libellé d’écriture" value={proposalForm.entry_description} onChange={(value) => updateProposalField('entry_description', value ?? '')} />
                        </div>
                        <div className="overflow-x-auto rounded-lg border border-slate-200">
                          <table className="min-w-[920px] w-full text-left text-xs">
                            <thead className="bg-slate-50 text-slate-600"><tr><th className="px-3 py-2">Compte</th><th className="px-3 py-2">Tiers</th><th className="px-3 py-2">Analytique</th><th className="px-3 py-2">Libellé</th><th className="px-3 py-2 text-right">Débit</th><th className="px-3 py-2 text-right">Crédit</th><th className="px-3 py-2 text-right">Confiance IA</th><th className="px-2 py-2"><span className="sr-only">Supprimer</span></th></tr></thead>
                            <tbody className="divide-y divide-slate-100">
                              {proposalForm.lines.map((line, index) => (
                                <tr key={line.id ?? `line-${index}`}>
                                  <td className="px-2 py-2"><select className="w-44 rounded border border-slate-300 px-2 py-1.5" value={line.chart_account_id || ''} onChange={(event) => updateProposalLine(index, 'chart_account_id', Number(event.target.value))}><option value="">Choisir un compte</option>{detail.options.chart_accounts.map((account) => <option key={account.id} value={account.id}>{account.code} — {account.label}</option>)}</select></td>
                                  <td className="px-2 py-2"><select className="w-40 rounded border border-slate-300 px-2 py-1.5" value={line.third_party_id ?? ''} onChange={(event) => updateProposalLine(index, 'third_party_id', event.target.value ? Number(event.target.value) : null)}><option value="">Aucun tiers</option>{detail.options.third_parties.map((party) => <option key={party.id} value={party.id}>{party.code} — {party.name}</option>)}</select></td>
                                  <td className="px-2 py-2"><select className="w-40 rounded border border-slate-300 px-2 py-1.5" value={line.analytical_account_id ?? ''} onChange={(event) => updateProposalLine(index, 'analytical_account_id', event.target.value ? Number(event.target.value) : null)}><option value="">Aucun axe</option>{detail.options.analytical_accounts.map((item) => <option key={item.id} value={item.id}>{item.code} — {item.label}</option>)}</select></td>
                                  <td className="px-2 py-2"><input className="w-44 rounded border border-slate-300 px-2 py-1.5" value={line.description ?? ''} onChange={(event) => updateProposalLine(index, 'description', event.target.value || null)} /></td>
                                  <td className="px-2 py-2"><input inputMode="decimal" className="w-28 rounded border border-slate-300 px-2 py-1.5 text-right tabular-nums" value={line.debit} onChange={(event) => updateProposalLine(index, 'debit', event.target.value)} /></td>
                                  <td className="px-2 py-2"><input inputMode="decimal" className="w-28 rounded border border-slate-300 px-2 py-1.5 text-right tabular-nums" value={line.credit} onChange={(event) => updateProposalLine(index, 'credit', event.target.value)} /></td>
                                  <td className="px-3 py-2 text-right tabular-nums text-slate-500">{line.confidence === null ? '—' : `${(Number(line.confidence) * 100).toFixed(0)}%`}</td>
                                  <td className="px-2 py-2"><button type="button" aria-label={`Supprimer la ligne ${index + 1}`} onClick={() => updateProposalField('lines', proposalForm.lines.filter((_, lineIndex) => lineIndex !== index))} className="rounded p-1.5 text-red-600 hover:bg-red-50"><Trash2 size={14} /></button></td>
                                </tr>
                              ))}
                            </tbody>
                          </table>
                        </div>
                        <div className="flex flex-wrap justify-between gap-2">
                          <button type="button" onClick={() => updateProposalField('lines', [...proposalForm.lines, { chart_account_id: detail.options.chart_accounts[0]?.id ?? 0, third_party_id: null, analytical_account_id: null, description: '', debit: '0.000', credit: '0.000', confidence: null }])} className="inline-flex items-center gap-1.5 rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:border-teal-400"><Plus size={14} /> Ajouter une ligne</button>
                          <button type="submit" disabled={savingProposal || detail.options.journals.length === 0 || detail.options.chart_accounts.length === 0} className="inline-flex items-center gap-2 rounded-md bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-60">{savingProposal ? <LoaderCircle className="animate-spin" size={16} /> : <Save size={16} />}{savingProposal ? 'Enregistrement…' : 'Enregistrer les corrections'}</button>
                        </div>
                        <p className="text-xs text-slate-500">Les comptes, journaux, tiers et axes sont limités aux données actives de cette société. Une proposition déséquilibrée ne peut pas être validée.</p>
                      </form>
                    ) : (
                      <ProposalReadOnly proposal={detail.proposal} />
                    )}

                    {detail.proposal.status === 'ready' && detail.can_manage && (
                      <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4">
                        <p className="max-w-2xl text-xs leading-5 text-amber-950">La validation humaine crée une écriture dans le journal sélectionné. Vérifiez les comptes, les montants et le document original avant de confirmer.{proposalNeedsSave ? ' Enregistrez d’abord vos corrections.' : ''}</p>
                        <div className="flex flex-wrap gap-2">
                          <button type="button" onClick={rejectProposal} disabled={rejecting || approving} className="inline-flex items-center gap-2 rounded-md border border-red-300 bg-white px-3 py-2.5 text-sm font-semibold text-red-800 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50">{rejecting ? <LoaderCircle className="animate-spin" size={16} /> : <X size={16} />}{rejecting ? 'Rejet…' : 'Rejeter'}</button>
                          <button type="button" onClick={approveProposal} disabled={approving || rejecting || proposalNeedsSave || detail.proposal.warnings.some(isBlockingProposalWarning)} className="inline-flex items-center gap-2 rounded-md bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50">{approving ? <LoaderCircle className="animate-spin" size={16} /> : <ClipboardCheck size={16} />}{approving ? 'Validation…' : 'Valider et créer l’écriture'}</button>
                        </div>
                      </div>
                    )}
                  </>
                ) : (
                  <div className="rounded-lg border border-slate-200 bg-slate-50 p-5 text-sm text-slate-700">
                    {['accounting_analysis', 'proposal_ready'].includes(detail.invoice.status)
                      ? <span className="inline-flex items-center gap-2"><LoaderCircle className="animate-spin" size={16} /> L’analyse comptable de la société est en cours. La proposition apparaîtra ici dès qu’elle sera prête.</span>
                      : detail.invoice.status === 'accounting_analysis_failed'
                        ? detail.invoice.ocr_error_message || 'L’analyse comptable a échoué.'
                        : 'Aucune proposition comptable n’est disponible pour cette facture.'}
                  </div>
                )}
              </section>
            )}
          </div>
        )}

        <footer className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 px-5 py-3">
          <p className="text-xs text-slate-500">Aucune écriture n’est créée avant votre validation explicite.</p>
          <button type="button" onClick={onClose} className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Fermer</button>
        </footer>
      </section>
    </div>
  );
}

function FieldGroup({ title, children }: { title: string; children: React.ReactNode }) {
  return <section className="space-y-3 rounded-lg border border-slate-200 p-4"><h3 className="text-sm font-semibold text-slate-900">{title}</h3>{children}</section>;
}

function TextField({ label, value, onChange, type = 'text', placeholder, maxLength }: { label: string; value: string | null; onChange: (value: string | null) => void; type?: string; placeholder?: string; maxLength?: number }) {
  return <label className="block text-xs font-semibold text-slate-700">{label}<input className={inputClass} type={type} value={value ?? ''} placeholder={placeholder} maxLength={maxLength} onChange={(event) => onChange(event.target.value === '' ? null : event.target.value)} /></label>;
}

function DecimalField({ label, value, onChange }: { label: string; value: string | null; onChange: (value: string | null) => void }) {
  return <TextField label={label} value={value} onChange={onChange} placeholder="—" />;
}

function TextAreaField({ label, value, onChange }: { label: string; value: string | null; onChange: (value: string | null) => void }) {
  return <label className="mt-3 block text-xs font-semibold text-slate-700">{label}<textarea className={`${inputClass} min-h-20`} value={value ?? ''} onChange={(event) => onChange(event.target.value === '' ? null : event.target.value)} /></label>;
}

function InvoiceDataSummary({ data }: { data: InvoiceData }) {
  const fields: [string, string | null][] = [
    ['Fournisseur', data.supplier_name], ['Matricule fiscal fournisseur', data.supplier_tax_identifier], ['N° facture', data.invoice_number],
    ['Date', data.invoice_date], ['Échéance', data.due_date], ['Devise', data.currency], ['Sous-total', data.subtotal], ['TVA', data.vat_amount],
    ['FODEC', data.fodec_amount], ['Autres taxes', data.other_tax_amount], ['Timbre', data.stamp_amount], ['Retenue', data.withholding_amount], ['Total', data.total_amount],
  ];
  return <div className="space-y-4"><dl className="grid gap-3 rounded-lg border border-slate-200 p-4 sm:grid-cols-2 lg:grid-cols-3">{fields.map(([label, value]) => <div key={label}><dt className="text-xs text-slate-500">{label}</dt><dd className="mt-1 break-words text-sm font-medium text-slate-900">{value || '—'}</dd></div>)}</dl><div className="rounded-lg border border-slate-200 p-4"><h3 className="text-sm font-semibold text-slate-900">Description</h3><p className="mt-2 whitespace-pre-wrap text-sm text-slate-700">{data.description || '—'}</p></div><div className="space-y-2"><h3 className="text-sm font-semibold text-slate-900">Lignes · {data.lines.length}</h3>{data.lines.map((line, index) => <div key={index} className="rounded-md border border-slate-200 p-3 text-sm"><p className="font-medium text-slate-800">{line.description || 'Ligne sans description'}</p><p className="mt-1 text-xs text-slate-500">Référence {line.reference || '—'} · HT {line.subtotal || '—'} · TVA {line.vat_amount || '—'} · Total {line.total_amount || '—'}</p></div>)}</div></div>;
}

function ProposalReadOnly({ proposal }: { proposal: Proposal }) {
  return <div className="overflow-x-auto rounded-lg border border-slate-200"><table className="min-w-full text-left text-xs"><thead className="bg-slate-50 text-slate-600"><tr><th className="px-3 py-2">Compte</th><th className="px-3 py-2">Tiers</th><th className="px-3 py-2">Analytique</th><th className="px-3 py-2">Libellé</th><th className="px-3 py-2 text-right">Débit</th><th className="px-3 py-2 text-right">Crédit</th><th className="px-3 py-2 text-right">Confiance IA</th></tr></thead><tbody className="divide-y divide-slate-100">{proposal.lines.map((line, index) => <tr key={line.id ?? index}><td className="px-3 py-2">{line.chart_account_code} — {line.chart_account_label}</td><td className="px-3 py-2">{line.third_party_code ? `${line.third_party_code} — ${line.third_party_name}` : '—'}</td><td className="px-3 py-2">{line.analytical_account_code ? `${line.analytical_account_code} — ${line.analytical_account_label}` : '—'}</td><td className="px-3 py-2">{line.description || '—'}</td><td className="px-3 py-2 text-right tabular-nums">{line.debit}</td><td className="px-3 py-2 text-right tabular-nums">{line.credit}</td><td className="whitespace-nowrap px-3 py-2 text-right tabular-nums text-slate-500">{line.confidence === null ? '—' : `${(Number(line.confidence) * 100).toFixed(0)}%`}</td></tr>)}</tbody></table></div>;
}

function WarningList({ warnings }: { warnings: string[] }) {
  return <div className="rounded-lg border border-amber-200 bg-amber-50 p-4" role="status"><p className="flex items-center gap-2 text-sm font-semibold text-amber-950"><AlertTriangle size={16} /> Points à vérifier</p><ul className="mt-2 space-y-1 text-xs leading-5 text-amber-900">{warnings.map((warning) => <li key={warning}>{warningLabel(warning)}</li>)}</ul></div>;
}

function statusLabel(status: string): string {
  const labels: Record<string, string> = {
    uploaded: 'Importée', ocr_queued: 'OCR en attente', ocr_processing: 'OCR en cours', data_extraction: 'Structuration des champs',
    accounting_analysis: 'Analyse comptable en cours', invoice_incomplete: 'Informations à compléter', proposal_ready: 'Proposition disponible',
    accounting_validated: 'Écriture comptable validée', proposal_rejected: 'Proposition rejetée', ocr_failed: 'Échec OCR', data_extraction_failed: 'Échec extraction',
    accounting_analysis_failed: 'Échec analyse comptable', ocr_completed: 'OCR terminé',
  };
  return labels[status] || status;
}

function warningLabel(warning: string): string {
  const labels: Record<string, string> = {
    missing_supplier_name: 'Nom du fournisseur manquant.',
    missing_invoice_number: 'Numéro de facture manquant.',
    missing_invoice_date: 'Date de facture manquante ou illisible.',
    missing_currency: 'Devise manquante ou illisible.',
    missing_total_amount: 'Total à payer manquant ou illisible.',
    missing_invoice_description: 'Description ou lignes de facture manquantes.',
    invoice_total_mismatch: 'Le calcul des totaux ne correspond pas aux montants extraits.',
    invoice_totals_unverified: 'Le contrôle des totaux n’est pas conclusif : certains montants sont absents.',
    proposal_unbalanced: 'La proposition n’est pas équilibrée : les débits et crédits doivent être égaux.',
    proposal_invoice_total_mismatch: 'Le total de l’écriture ne correspond pas au total de la facture et à la retenue explicite.',
    supplier_not_linked: 'Aucun tiers fournisseur de la société n’a été associé à une ligne.',
    proposal_low_confidence: 'Au moins une ligne a une confiance faible ; vérifiez l’imputation.',
    withholding_not_verified: 'La retenue à la source n’a pas été confirmée sur la facture.',
  };
  return labels[warning] || warning.replaceAll('_', ' ');
}

function isBlockingProposalWarning(warning: string): boolean {
  return warning === 'proposal_unbalanced' || warning === 'proposal_invoice_total_mismatch';
}

function emptyLine(): InvoiceLineData {
  return { reference: null, description: null, quantity: null, unit_price: null, subtotal: null, vat_rate: null, vat_amount: null, fodec_rate: null, fodec_amount: null, other_tax_amount: null, total_amount: null };
}

function firstError(errors: Record<string, string | string[]>): string | null {
  const value = Object.values(errors).flat()[0];
  return typeof value === 'string' ? value : null;
}

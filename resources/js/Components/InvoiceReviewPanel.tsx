import { router } from '@inertiajs/react';
import {
  AlertTriangle,
  BadgeCheck,
  CheckCircle2,
  Circle,
  CircleHelp,
  ClipboardCheck,
  Calculator,
  Download,
  FileText,
  Info,
  LoaderCircle,
  Plus,
  RotateCcw,
  Save,
  ShieldCheck,
  Sparkles,
  Trash2,
  X,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';

export type InvoiceLineData = {
  reference: string | null;
  description: string | null;
  quantity: string | null;
  unit_price: string | null;
  discount_amount: string | null;
  subtotal: string | null;
  vat_rate: string | null;
  vat_amount: string | null;
  fodec_rate: string | null;
  fodec_amount: string | null;
  other_tax_amount: string | null;
  total_amount: string | null;
};

export type InvoiceData = {
  supplier_name: string | null;
  supplier_tax_identifier: string | null;
  supplier_address: string | null;
  supplier_phone: string | null;
  supplier_mobile: string | null;
  supplier_email: string | null;
  customer_name: string | null;
  customer_tax_identifier: string | null;
  customer_reference: string | null;
  customer_address: string | null;
  customer_phone: string | null;
  invoice_number: string | null;
  purchase_order_reference: string | null;
  payment_method: string | null;
  invoice_date: string | null;
  due_date: string | null;
  currency: string | null;
  vat_rate: string | null;
  fodec_rate: string | null;
  subtotal: string | null;
  total_discount_amount: string | null;
  vat_amount: string | null;
  fodec_amount: string | null;
  other_tax_amount: string | null;
  stamp_amount: string | null;
  withholding_rate: string | null;
  withholding_amount: string | null;
  total_amount: string | null;
  net_to_pay_amount: string | null;
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
  invoice_type: string | null;
  invoice_type_label: string | null;
  journal_code: string | null;
  journal_label: string | null;
  entry_description: string;
  explanation: string | null;
  warnings: string[];
  model: string;
  modified_at: string | null;
  journal_entry_id: number | null;
  confidence: number | null;
  lines: ProposalLine[];
};

type InvoiceDetail = {
  id: number;
  status: string;
  original_filename: string;
  invoice_number: string | null;
  ocr_text: string | null;
  ocr_display_text: string | null;
  ocr_data: InvoiceData;
  ocr_warnings: string[];
  ocr_error_message: string | null;
  ocr_model: string | null;
  extraction_provider: string;
  extraction_model: string | null;
  extraction_corrected_at: string | null;
  accounting_exported_at: string | null;
};

type Options = {
  chart_accounts: { id: number; code: string; label: string; account_type: string }[];
  journals: { id: number; code: string; label: string; journal_type: string }[];
  third_parties: { id: number; code: string; name: string }[];
  analytical_accounts: { id: number; code: string; label: string }[];
  invoice_types: { value: string; label: string }[];
};

type CheckStatus = 'passed' | 'warning' | 'blocking' | 'pending' | 'unavailable';
type AccountingCheck = { status: CheckStatus; detail: string };

export type InvoiceReviewDetail = {
  invoice: InvoiceDetail;
  proposal: Proposal | null;
  options: Options;
  company_profile: { activity: string | null; sector: string | null };
  checks: {
    supplier: AccountingCheck;
    invoice_number: AccountingCheck;
    vat: AccountingCheck;
    balance: AccountingCheck;
    totals: AccountingCheck;
    duplicate: AccountingCheck;
    accounts: AccountingCheck;
    fiscal_year: AccountingCheck;
    currency: AccountingCheck;
  };
  can_manage: boolean;
};

type Props = {
  companyId: number;
  invoiceId: number;
  onChanged?: () => void;
  onDetailChange?: (detail: InvoiceReviewDetail) => void;
};

const activeStatuses = ['ocr_queued', 'ocr_processing', 'data_extraction', 'accounting_analysis'];
const failedStatuses = ['ocr_failed', 'data_extraction_failed', 'accounting_analysis_failed'];
const editableInvoiceStatuses = ['ocr_completed', 'invoice_incomplete', 'data_extraction_failed', 'accounting_analysis_failed', 'proposal_ready', 'proposal_rejected'];
const inputClass = 'mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100';
const selectClass = inputClass;

export default function InvoiceReviewPanel({ companyId, invoiceId, onChanged, onDetailChange }: Props) {
  const [detail, setDetail] = useState<InvoiceReviewDetail | null>(null);
  const [invoiceData, setInvoiceData] = useState<InvoiceData | null>(null);
  const [proposalForm, setProposalForm] = useState<Proposal | null>(null);
  const [loading, setLoading] = useState(true);
  const [savingInvoice, setSavingInvoice] = useState(false);
  const [savingProposal, setSavingProposal] = useState(false);
  const [regeneratingProposal, setRegeneratingProposal] = useState(false);
  const [approving, setApproving] = useState(false);
  const [rejecting, setRejecting] = useState(false);
  const [reextracting, setReextracting] = useState(false);
  const [retryingOcr, setRetryingOcr] = useState(false);
  const [exportingCsv, setExportingCsv] = useState(false);
  const observedStatus = useRef<string | null>(null);
  const initialAnchor = useRef(typeof window === 'undefined' ? '' : window.location.hash.slice(1));

  const loadDetails = useCallback(async (showLoader = true, showError = true) => {
    if (showLoader) setLoading(true);

    try {
      const response = await fetch(`/companies/${companyId}/invoices/${invoiceId}/details`, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      if (!response.ok) throw new Error('La facture n’a pas pu être chargée.');
      const data = await response.json() as InvoiceReviewDetail;
      const previousStatus = observedStatus.current;
      observedStatus.current = data.invoice.status;

      if (previousStatus !== null && previousStatus !== data.invoice.status) {
        if (data.invoice.status === 'proposal_ready') {
          toast.success('La proposition comptable est disponible. Vérifiez les contrôles et les lignes avant décision.');
        } else if (failedStatuses.includes(data.invoice.status)) {
          toast.error(data.invoice.ocr_error_message || 'Le traitement de la facture a échoué.');
        }
      }

      setDetail(data);
      setInvoiceData(data.invoice.ocr_data);
      setProposalForm(data.proposal ? structuredClone(data.proposal) : null);
      onDetailChange?.(data);

      if (initialAnchor.current) {
        requestAnimationFrame(() => document.getElementById(initialAnchor.current)?.scrollIntoView({ block: 'start' }));
        initialAnchor.current = '';
      }
    } catch (error) {
      if (showError) toast.error(error instanceof Error ? error.message : 'La facture n’a pas pu être chargée.');
    } finally {
      if (showLoader) setLoading(false);
    }
  }, [companyId, invoiceId, onDetailChange]);

  useEffect(() => {
    void loadDetails();
  }, [loadDetails]);

  useEffect(() => {
    if (!detail || !activeStatuses.includes(detail.invoice.status)) return;

    const interval = window.setInterval(() => {
      void loadDetails(false, false);
    }, 5000);

    return () => window.clearInterval(interval);
  }, [detail?.invoice.status, loadDetails]);

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

  const recalculateInvoiceTotals = () => {
    if (!invoiceData || savingInvoice) return;

    const subtotal = parseMilliAmount(invoiceData.subtotal);
    if (subtotal === null) {
      toast.error('Saisissez un total HT valide avant de recalculer.');
      return;
    }

    const missingComponents: string[] = [];
    const amountOrZero = (label: string, value: string | null): bigint => {
      if (!value?.trim()) {
        missingComponents.push(label);
        return 0n;
      }

      const amount = parseMilliAmount(value);
      if (amount === null) throw new Error(`Le montant « ${label} » n’est pas valide.`);
      return amount;
    };
    const taxAmount = (label: string, amountValue: string | null, rateValue: string | null): bigint => {
      if (rateValue?.trim()) {
        const rate = parseMilliAmount(rateValue);
        if (rate === null || rate < 0n || rate > 100_000n) throw new Error(`Le taux « ${label} » doit être compris entre 0 et 100.`);
        return percentageOf(subtotal, rate);
      }

      return amountOrZero(label, amountValue);
    };

    try {
      const vat = taxAmount('TVA', invoiceData.vat_amount, invoiceData.vat_rate);
      const fodec = taxAmount('FODEC', invoiceData.fodec_amount, invoiceData.fodec_rate);
      const otherTaxes = amountOrZero('Autres taxes', invoiceData.other_tax_amount);
      const stamp = amountOrZero('Droit de timbre', invoiceData.stamp_amount);
      const withholding = amountOrZero('Retenue à la source', invoiceData.withholding_amount);

      if (missingComponents.length > 0 && !window.confirm(`Les champs suivants sont vides : ${missingComponents.join(', ')}. Les traiter comme 0,000 pour ce calcul déterministe ?`)) return;

      const grossTotal = subtotal + vat + fodec + otherTaxes + stamp;
      const netToPay = grossTotal - withholding;

      setInvoiceData((current) => current ? {
        ...current,
        vat_amount: formatMilliAmount(vat),
        fodec_amount: formatMilliAmount(fodec),
        other_tax_amount: formatMilliAmount(otherTaxes),
        stamp_amount: formatMilliAmount(stamp),
        withholding_amount: formatMilliAmount(withholding),
        total_amount: formatMilliAmount(grossTotal),
        net_to_pay_amount: formatMilliAmount(netToPay),
      } : current);
      toast.success('Totaux recalculés localement. Vérifiez-les puis enregistrez les données facture.');
    } catch (error) {
      toast.error(error instanceof Error ? error.message : 'Les montants ne peuvent pas être recalculés.');
    }
  };

  const saveInvoiceData = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!detail || !invoiceData || savingInvoice) return;

    setSavingInvoice(true);
    router.put(`/companies/${companyId}/invoices/${invoiceId}/extraction`, { invoice_data: invoiceData }, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Les données de la facture sont enregistrées.');
        onChanged?.();
        void loadDetails(false, false);
      },
      onError: (errors) => toast.error(firstError(errors) || 'Les données de la facture n’ont pas pu être enregistrées.'),
      onFinish: () => setSavingInvoice(false),
    });
  };

  const rerunExtraction = () => {
    if (!detail?.can_manage || !detail.invoice.ocr_text?.trim() || reextracting) return;

    setReextracting(true);
    router.post(`/companies/${companyId}/invoices/${invoiceId}/extraction/retry`, {}, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('L’extraction est relancée depuis le texte OCR existant.');
        void loadDetails(false, false);
      },
      onError: (errors) => toast.error(firstError(errors) || 'L’extraction n’a pas pu être relancée.'),
      onFinish: () => setReextracting(false),
    });
  };

  const retryOcr = () => {
    if (!detail?.can_manage || retryingOcr || !['uploaded', ...failedStatuses].includes(detail.invoice.status)) return;

    setRetryingOcr(true);
    router.post(`/companies/${companyId}/invoices/${invoiceId}/ocr/retry`, {}, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Le traitement IA est relancé à partir de l’étape en échec.');
        void loadDetails(false, false);
      },
      onError: (errors) => toast.error(firstError(errors) || 'Le traitement IA n’a pas pu être relancé.'),
      onFinish: () => setRetryingOcr(false),
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
      invoice_type: proposalForm.invoice_type,
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
        toast.success('La proposition comptable est enregistrée. Les contrôles serveur ont été actualisés.');
        onChanged?.();
        void loadDetails(false, false);
      },
      onError: (errors) => toast.error(firstError(errors) || 'La proposition comptable n’a pas pu être enregistrée.'),
      onFinish: () => setSavingProposal(false),
    });
  };

  const regenerateProposal = () => {
    if (!detail?.can_manage || regeneratingProposal || invoiceNeedsSave || proposalNeedsSave) return;
    if (!window.confirm('Générer une nouvelle proposition IA ? La version actuelle restera conservée pour audit et ne pourra plus être validée.')) return;

    setRegeneratingProposal(true);
    router.post(`/companies/${companyId}/invoices/${invoiceId}/proposal/regenerate`, {}, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('La régénération de la proposition comptable a démarré.');
        onChanged?.();
        void loadDetails(false, false);
      },
      onError: (errors) => toast.error(firstError(errors) || 'La proposition IA n’a pas pu être régénérée.'),
      onFinish: () => setRegeneratingProposal(false),
    });
  };

  const exportProposalCsv = async (afterValidation = false): Promise<void> => {
    if (!detail?.can_manage || exportingCsv) return;

    const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
    if (!csrfToken) {
      toast.error('Jeton de sécurité absent. Actualisez la page et réessayez.');
      return;
    }

    setExportingCsv(true);

    try {
      const response = await fetch(`/companies/${companyId}/invoices/${invoiceId}/proposal/export-csv`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          Accept: 'text/csv, application/json',
          'X-CSRF-TOKEN': csrfToken,
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      if (!response.ok) {
        const contentType = response.headers.get('content-type') || '';
        if (contentType.includes('application/json')) {
          const payload = await response.json() as { message?: string };
          throw new Error(payload.message || 'Le fichier CSV n’a pas pu être exporté.');
        }
        throw new Error('Le fichier CSV n’a pas pu être exporté. Vérifiez les droits et l’état de la proposition.');
      }

      const blob = await response.blob();
      if (blob.size === 0) throw new Error('Le fichier CSV exporté est vide.');

      const contentDisposition = response.headers.get('content-disposition') || '';
      const filename = contentDisposition.match(/filename="?([^";]+)"?/i)?.[1] || `ecriture-facture-${invoiceId}.csv`;
      const url = URL.createObjectURL(blob);
      const anchor = document.createElement('a');
      anchor.href = url;
      anchor.download = filename;
      document.body.appendChild(anchor);
      anchor.click();
      anchor.remove();
      window.setTimeout(() => URL.revokeObjectURL(url), 1000);

      toast.success(!afterValidation && detail.invoice.status === 'proposal_ready'
        ? 'Le brouillon comptable a été exporté en CSV. Ce fichier n’est pas un export Sage certifié.'
        : 'L’écriture validée a été exportée en CSV. Ce fichier n’est pas un export Sage certifié.');
      onChanged?.();
      await loadDetails(false, false);
    } catch (error) {
      toast.error(error instanceof Error ? error.message : 'Le fichier CSV n’a pas pu être exporté.');
    } finally {
      setExportingCsv(false);
    }
  };

  const approveProposal = (exportAfterValidation = false) => {
    if (approving) return;

    setApproving(true);
    router.post(`/companies/${companyId}/invoices/${invoiceId}/proposal/approve`, {}, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Proposition validée. L’écriture comptable a été créée.');
        onChanged?.();
        void loadDetails(false, false);
        if (exportAfterValidation) void exportProposalCsv(true);
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
        onChanged?.();
        void loadDetails(false, false);
      },
      onError: (errors) => toast.error(firstError(errors) || 'La proposition n’a pas pu être rejetée.'),
      onFinish: () => setRejecting(false),
    });
  };

  const extractionInProgress = Boolean(detail && activeStatuses.includes(detail.invoice.status));
  const canRerunExtraction = Boolean(detail?.can_manage
    && detail.invoice.ocr_text?.trim()
    && !extractionInProgress
    && !['accounting_validated', 'accounting_exported'].includes(detail.invoice.status));
  const canRetryAnalysis = Boolean(detail?.can_manage && ['uploaded', ...failedStatuses].includes(detail.invoice.status));
  const canEditInvoice = Boolean(detail?.can_manage && editableInvoiceStatuses.includes(detail.invoice.status));
  const canEditProposal = Boolean(detail?.can_manage && detail.proposal?.status === 'ready' && detail.invoice.status === 'proposal_ready');
  const canRegenerateProposal = Boolean(detail?.can_manage
    && ['proposal_ready', 'proposal_rejected', 'accounting_analysis_failed'].includes(detail.invoice.status)
    && detail.proposal?.journal_entry_id == null);
  const invoiceNeedsSave = Boolean(detail && invoiceData && JSON.stringify(detail.invoice.ocr_data) !== JSON.stringify(invoiceData));
  const proposalNeedsSave = Boolean(detail?.proposal && proposalForm && JSON.stringify(detail.proposal) !== JSON.stringify(proposalForm));
  const proposalWarnings = detail?.proposal?.warnings ?? [];
  const blockingChecks = Boolean(detail && (
    detail.checks.balance.status === 'blocking'
    || detail.checks.totals.status === 'blocking'
    || detail.checks.vat.status === 'blocking'
    || detail.checks.accounts.status === 'blocking'
    || detail.checks.currency.status === 'blocking'
  ));
  const canValidate = Boolean(detail?.can_manage
    && detail.invoice.status === 'proposal_ready'
    && detail.proposal?.status === 'ready'
    && Boolean(proposalForm?.invoice_type)
    && !invoiceNeedsSave
    && !proposalNeedsSave
    && !proposalWarnings.some(isBlockingProposalWarning)
    && !blockingChecks);
  const canExportCsv = Boolean(detail?.can_manage && detail.proposal && (
    (detail.invoice.status === 'proposal_ready' && detail.proposal.status === 'ready')
    || (['accounting_validated', 'accounting_exported'].includes(detail.invoice.status) && detail.proposal.journal_entry_id !== null)
  ));
  const draftDebitTotal = proposalForm ? sumProposalAmounts(proposalForm.lines, 'debit') : null;
  const draftCreditTotal = proposalForm ? sumProposalAmounts(proposalForm.lines, 'credit') : null;

  return (
    <section aria-labelledby="invoice-review-title" className="flex h-full min-h-[560px] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm xl:min-h-0">
      <header className="shrink-0 border-b border-slate-200 bg-white px-4 py-4 sm:px-5">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div className="min-w-0">
            <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-teal-700">Espace de revue · facture #{invoiceId}</p>
            <h2 id="invoice-review-title" className="mt-1 truncate text-lg font-semibold text-slate-950">
              {invoiceData?.invoice_number || detail?.invoice.original_filename || 'Détails de la facture'}
            </h2>
            <p className="mt-1 truncate text-xs text-slate-500">{detail?.invoice.original_filename || 'Chargement du document…'}</p>
          </div>
          {detail && <StatusPill status={detail.invoice.status} />}
        </div>
      </header>

      {loading || !detail || !invoiceData ? (
        <div className="flex min-h-64 flex-1 items-center justify-center gap-2 text-sm text-slate-500" role="status"><LoaderCircle className="animate-spin" size={18} /> Chargement des informations…</div>
      ) : (
        <div className="min-h-0 flex-1 space-y-4 overflow-y-auto bg-slate-50/70 p-3 sm:p-4">
          <section aria-label="Confiance IA" className="rounded-xl border border-teal-100 bg-gradient-to-br from-teal-50/80 to-white p-4">
            <div className="flex items-start justify-between gap-3">
              <div>
                <h3 className="flex items-center gap-2 text-sm font-semibold text-slate-900"><Sparkles size={16} className="text-teal-700" /> Confiance IA</h3>
                <p className="mt-0.5 text-[11px] text-slate-500">Indicateurs disponibles pour cette facture, sans score estimé côté interface.</p>
              </div>
              {detail.proposal && <span className="rounded-full bg-white px-2.5 py-1 text-[10px] font-semibold text-slate-600">Proposition · {detail.proposal.model}</span>}
            </div>
            <div className="mt-4 grid gap-4 sm:grid-cols-[112px_minmax(0,1fr)]">
              <div className="flex flex-col items-center justify-center rounded-lg border border-white/80 bg-white/80 p-3 text-center">
                <ConfidenceRing value={detail.proposal?.confidence ?? null} />
                <p className="mt-2 text-[10px] font-semibold uppercase tracking-wide text-slate-500">Moyenne des lignes comptables</p>
                <p className="mt-0.5 text-[10px] text-slate-400">Scores réels enregistrés</p>
              </div>
              <div className="grid gap-x-5 gap-y-3 sm:grid-cols-2">
                <ConfidenceBar label="Fournisseur" value={null} />
                <ConfidenceBar label="Montants" value={null} />
                <ConfidenceBar label="TVA" value={null} />
                <ConfidenceBar label="Type de facture" value={null} />
                <ConfidenceBar label="Compte comptable" value={detail.proposal?.confidence ?? null} />
              </div>
            </div>
            <p className="mt-3 text-[10px] leading-4 text-slate-500">Les modèles ne renvoient pas de scores calibrés distincts pour fournisseur, montants, TVA ou type : ces barres restent non disponibles. La confiance comptable est la moyenne des confiances réelles de lignes.</p>
            <div className="mt-3 grid gap-2 sm:grid-cols-3">
              <ModelIndicator label="Lecture OCR" value={detail.invoice.ocr_model || 'En attente'} complete={Boolean(detail.invoice.ocr_text || detail.invoice.ocr_model)} />
              <ModelIndicator label="Extraction" value={detail.invoice.extraction_model || 'En attente'} complete={Boolean(detail.invoice.extraction_model)} />
              <ModelIndicator label="Proposition" value={detail.proposal?.model || 'En attente'} complete={Boolean(detail.proposal)} />
            </div>
          </section>

          <section className="rounded-xl border border-teal-100 bg-gradient-to-br from-teal-50/90 to-white p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div className="min-w-0">
                <div className="flex items-center gap-2 text-sm font-semibold text-slate-900"><Sparkles size={17} className="text-teal-700" /> {workflowTitle(detail.invoice.status)}</div>
                <p className="mt-1 max-w-xl text-xs leading-5 text-slate-600">{workflowDescription(detail.invoice.status, detail.invoice.ocr_error_message)}</p>
              </div>
              {canRetryAnalysis && (
                <button type="button" onClick={retryOcr} disabled={retryingOcr} className="inline-flex shrink-0 items-center gap-2 rounded-lg bg-teal-700 px-3 py-2 text-xs font-semibold text-white hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-60">
                  {retryingOcr ? <LoaderCircle className="animate-spin" size={15} /> : <RotateCcw size={15} />}
                  {retryingOcr ? 'Relance…' : detail.invoice.status === 'uploaded' ? 'Lancer l’analyse IA' : 'Relancer l’analyse IA'}
                </button>
              )}
              {extractionInProgress && <span className="inline-flex shrink-0 items-center gap-2 rounded-lg border border-blue-200 bg-white px-3 py-2 text-xs font-semibold text-blue-800"><LoaderCircle className="animate-spin" size={14} /> Traitement automatique</span>}
            </div>
          </section>

          <section aria-label="Progression de l’analyse" className="rounded-xl border border-slate-200 bg-white p-4">
            <div className="flex items-center justify-between gap-3">
              <div>
                <h3 className="text-sm font-semibold text-slate-900">Étapes de traitement</h3>
                <p className="mt-0.5 text-[11px] text-slate-500">L’import lance le workflow automatiquement.</p>
              </div>
              <span className="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-slate-500">OCR → contrôles</span>
            </div>
            <ol className="mt-3 grid gap-x-4 gap-y-2 sm:grid-cols-2">
              {workflowSteps(detail).map((step, index) => (
                <li key={step.label} className="flex min-w-0 items-start gap-2">
                  <WorkflowIcon state={step.state} />
                  <span className="min-w-0 text-xs leading-4">
                    <strong className={`block font-medium ${step.state === 'current' ? 'text-teal-800' : step.state === 'warning' || step.state === 'failed' ? 'text-amber-900' : 'text-slate-700'}`}>{index + 1}. {step.label}</strong>
                    <span className="text-[10px] text-slate-400">{step.caption}</span>
                  </span>
                </li>
              ))}
            </ol>
          </section>

          <section id="invoice-fields" className="scroll-mt-3 rounded-xl border border-slate-200 bg-white p-4">
            <div className="mb-3 flex flex-wrap items-start justify-between gap-3">
              <div>
                <h3 className="text-sm font-semibold text-slate-900">1 Données extraites (modifiables)</h3>
                <p className="mt-0.5 text-[11px] text-slate-500">Comparez chaque valeur avec le document original. Aucun calcul n’est automatique.</p>
              </div>
              {detail.can_manage && canRerunExtraction && (
                <button type="button" onClick={rerunExtraction} disabled={reextracting} className="inline-flex items-center gap-1.5 rounded-md border border-slate-200 px-2.5 py-1.5 text-[11px] font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-800 disabled:opacity-50">
                  {reextracting ? <LoaderCircle className="animate-spin" size={13} /> : <RotateCcw size={13} />}
                  {reextracting ? 'Extraction…' : 'Reprendre depuis le texte OCR'}
                </button>
              )}
            </div>
            {detail.invoice.ocr_warnings.length > 0 && <WarningList warnings={detail.invoice.ocr_warnings} />}
            {detail.invoice.extraction_corrected_at && <p className="mt-3 text-[11px] text-slate-500">Données corrigées le {formatTimestamp(detail.invoice.extraction_corrected_at)}.</p>}
            {canEditInvoice ? (
              <form onSubmit={saveInvoiceData} className="mt-4 space-y-4">
                <fieldset disabled={savingInvoice} className="space-y-4 disabled:opacity-70">
                  <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <TextField label="Fournisseur *" value={invoiceData.supplier_name} onChange={(value) => updateInvoiceField('supplier_name', value)} />
                    <TextField label="Matricule fiscal fournisseur" value={invoiceData.supplier_tax_identifier} onChange={(value) => updateInvoiceField('supplier_tax_identifier', value)} />
                    <TextField label="Numéro de facture *" value={invoiceData.invoice_number} onChange={(value) => updateInvoiceField('invoice_number', value)} />
                    <TextField label="Date de facture *" type="date" value={invoiceData.invoice_date} onChange={(value) => updateInvoiceField('invoice_date', value)} />
                    <TextField label="Date d’échéance" type="date" value={invoiceData.due_date} onChange={(value) => updateInvoiceField('due_date', value)} />
                    <TextField label="Mode de paiement" value={invoiceData.payment_method} onChange={(value) => updateInvoiceField('payment_method', value)} />
                  </div>
                  <TextAreaField label="Description / nature de la facture *" value={invoiceData.description} onChange={(value) => updateInvoiceField('description', value)} />

                  <FieldGroup title="Montants et taxes (valeurs imprimées)">
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                      <DecimalField label="Total HT" value={invoiceData.subtotal} onChange={(value) => updateInvoiceField('subtotal', value)} />
                      <DecimalField label="Taux TVA (%)" value={invoiceData.vat_rate} onChange={(value) => updateInvoiceField('vat_rate', value)} />
                      <DecimalField label="Montant TVA" value={invoiceData.vat_amount} onChange={(value) => updateInvoiceField('vat_amount', value)} />
                      <DecimalField label="Taux FODEC (%)" value={invoiceData.fodec_rate} onChange={(value) => updateInvoiceField('fodec_rate', value)} />
                      <DecimalField label="Montant FODEC" value={invoiceData.fodec_amount} onChange={(value) => updateInvoiceField('fodec_amount', value)} />
                      <DecimalField label="Autres taxes" value={invoiceData.other_tax_amount} onChange={(value) => updateInvoiceField('other_tax_amount', value)} />
                      <DecimalField label="Droit de timbre" value={invoiceData.stamp_amount} onChange={(value) => updateInvoiceField('stamp_amount', value)} />
                      <DecimalField label="Total TTC brut" value={invoiceData.total_amount} onChange={(value) => updateInvoiceField('total_amount', value)} />
                      <DecimalField label="Retenue à la source" value={invoiceData.withholding_amount} onChange={(value) => updateInvoiceField('withholding_amount', value)} />
                      <TextField label="Devise ISO *" value={invoiceData.currency} onChange={(value) => updateInvoiceField('currency', value?.toUpperCase() ?? null)} placeholder="TND" maxLength={3} />
                    </div>
                    <button type="button" onClick={recalculateInvoiceTotals} disabled={savingInvoice} className="inline-flex items-center gap-2 rounded-md border border-teal-200 bg-teal-50 px-3 py-2 text-xs font-semibold text-teal-900 hover:bg-teal-100 disabled:cursor-not-allowed disabled:opacity-60">
                      <Calculator size={14} /> ↻ Recalculer à partir de ces montants
                    </button>
                    <p className="text-[10px] leading-4 text-slate-500">Calcul local déterministe : HT imprimé + TVA + FODEC + autres taxes + timbre = TTC brut ; la remise séparée n’est pas déduite une seconde fois et la retenue réduit uniquement le net à payer. Aucun appel IA n’est effectué.</p>
                  </FieldGroup>

                  <details className="rounded-lg border border-slate-200 px-3 py-2">
                    <summary className="cursor-pointer text-xs font-semibold text-slate-700">Coordonnées et champs complémentaires</summary>
                    <div className="mt-3 grid gap-4">
                      <FieldGroup title="Coordonnées fournisseur et client">
                        <div className="grid gap-3 sm:grid-cols-2">
                          <TextField label="Adresse du fournisseur" value={invoiceData.supplier_address} onChange={(value) => updateInvoiceField('supplier_address', value)} />
                          <TextField label="Téléphone fournisseur" value={invoiceData.supplier_phone} onChange={(value) => updateInvoiceField('supplier_phone', value)} />
                          <TextField label="Mobile fournisseur" value={invoiceData.supplier_mobile} onChange={(value) => updateInvoiceField('supplier_mobile', value)} />
                          <TextField label="E-mail fournisseur" value={invoiceData.supplier_email} onChange={(value) => updateInvoiceField('supplier_email', value)} />
                          <TextField label="Nom du client" value={invoiceData.customer_name} onChange={(value) => updateInvoiceField('customer_name', value)} />
                          <TextField label="Référence client" value={invoiceData.customer_reference} onChange={(value) => updateInvoiceField('customer_reference', value)} />
                          <TextField label="Matricule fiscal client" value={invoiceData.customer_tax_identifier} onChange={(value) => updateInvoiceField('customer_tax_identifier', value)} />
                          <TextField label="Téléphone client" value={invoiceData.customer_phone} onChange={(value) => updateInvoiceField('customer_phone', value)} />
                          <div className="sm:col-span-2"><TextAreaField label="Adresse du client" value={invoiceData.customer_address} onChange={(value) => updateInvoiceField('customer_address', value)} /></div>
                        </div>
                      </FieldGroup>
                      <FieldGroup title="Autres informations de facture">
                        <div className="grid gap-3 sm:grid-cols-2">
                          <DecimalField label="Remise totale (référence)" value={invoiceData.total_discount_amount} onChange={(value) => updateInvoiceField('total_discount_amount', value)} />
                          <DecimalField label="Taux de retenue (%)" value={invoiceData.withholding_rate} onChange={(value) => updateInvoiceField('withholding_rate', value)} />
                          <DecimalField label="Net à payer imprimé" value={invoiceData.net_to_pay_amount} onChange={(value) => updateInvoiceField('net_to_pay_amount', value)} />
                          <TextField label="Conditions de paiement" value={invoiceData.payment_terms} onChange={(value) => updateInvoiceField('payment_terms', value)} />
                          <TextField label="Référence de commande" value={invoiceData.purchase_order_reference} onChange={(value) => updateInvoiceField('purchase_order_reference', value)} />
                          <TextField label="Banque" value={invoiceData.bank_name} onChange={(value) => updateInvoiceField('bank_name', value)} />
                          <TextField label="RIB / IBAN" value={invoiceData.bank_account_reference} onChange={(value) => updateInvoiceField('bank_account_reference', value)} />
                        </div>
                      </FieldGroup>
                    </div>
                  </details>

                  <FieldGroup title={`Lignes de facture (${invoiceData.lines.length})`}>
                    <div className="overflow-x-auto rounded-lg border border-slate-200">
                      <table className="min-w-[700px] w-full text-left text-xs">
                        <thead className="bg-slate-50 text-slate-600"><tr><th className="px-3 py-2">Description</th><th className="px-3 py-2">Quantité</th><th className="px-3 py-2">Prix unitaire HT</th><th className="px-3 py-2">Remise</th><th className="px-3 py-2">TVA</th><th className="px-3 py-2">Total TTC</th><th className="px-2 py-2"><span className="sr-only">Supprimer</span></th></tr></thead>
                        <tbody className="divide-y divide-slate-100">
                          {invoiceData.lines.map((line, index) => (
                            <tr key={index}>
                              <td className="min-w-44 px-2 py-2"><input aria-label={`Description de la ligne ${index + 1}`} className="w-full min-w-40 rounded border border-slate-300 px-2 py-1.5" value={line.description ?? ''} onChange={(event) => updateInvoiceLine(index, 'description', event.target.value || null)} /></td>
                              <td className="px-2 py-2"><input aria-label={`Quantité de la ligne ${index + 1}`} inputMode="decimal" className="w-20 rounded border border-slate-300 px-2 py-1.5" value={line.quantity ?? ''} onChange={(event) => updateInvoiceLine(index, 'quantity', event.target.value || null)} /></td>
                              <td className="px-2 py-2"><input aria-label={`Prix unitaire HT de la ligne ${index + 1}`} inputMode="decimal" className="w-24 rounded border border-slate-300 px-2 py-1.5" value={line.unit_price ?? ''} onChange={(event) => updateInvoiceLine(index, 'unit_price', event.target.value || null)} /></td>
                              <td className="px-2 py-2"><input aria-label={`Remise de la ligne ${index + 1}`} inputMode="decimal" className="w-20 rounded border border-slate-300 px-2 py-1.5" value={line.discount_amount ?? ''} onChange={(event) => updateInvoiceLine(index, 'discount_amount', event.target.value || null)} /></td>
                              <td className="px-2 py-2"><input aria-label={`Taux TVA de la ligne ${index + 1}`} inputMode="decimal" className="w-20 rounded border border-slate-300 px-2 py-1.5" value={line.vat_rate ?? ''} onChange={(event) => updateInvoiceLine(index, 'vat_rate', event.target.value || null)} /></td>
                              <td className="px-2 py-2"><input aria-label={`Total TTC de la ligne ${index + 1}`} inputMode="decimal" className="w-24 rounded border border-slate-300 px-2 py-1.5" value={line.total_amount ?? ''} onChange={(event) => updateInvoiceLine(index, 'total_amount', event.target.value || null)} /></td>
                              <td className="px-2 py-2"><button type="button" aria-label={`Supprimer la ligne ${index + 1}`} onClick={() => setInvoiceData((current) => current ? { ...current, lines: current.lines.filter((_, lineIndex) => lineIndex !== index) } : current)} className="rounded p-1 text-red-600 hover:bg-red-50"><Trash2 size={14} /></button></td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                    <button type="button" onClick={() => setInvoiceData((current) => current ? { ...current, lines: [...current.lines, emptyLine()] } : current)} className="inline-flex items-center gap-1.5 rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:border-teal-400 hover:text-teal-800"><Plus size={14} /> Ajouter une ligne de facture</button>
                    {invoiceData.lines.map((line, index) => (
                      <details key={`more-${index}`} className="rounded-md border border-slate-200 px-3 py-2">
                        <summary className="cursor-pointer text-xs font-semibold text-slate-700">Détails de ligne {index + 1}</summary>
                        <div className="mt-3 grid gap-3 sm:grid-cols-2">
                          <TextField label="Référence" value={line.reference} onChange={(value) => updateInvoiceLine(index, 'reference', value)} />
                          <DecimalField label="Total HT de ligne" value={line.subtotal} onChange={(value) => updateInvoiceLine(index, 'subtotal', value)} />
                          <DecimalField label="Montant TVA" value={line.vat_amount} onChange={(value) => updateInvoiceLine(index, 'vat_amount', value)} />
                          <DecimalField label="Taux FODEC (%)" value={line.fodec_rate} onChange={(value) => updateInvoiceLine(index, 'fodec_rate', value)} />
                          <DecimalField label="Montant FODEC" value={line.fodec_amount} onChange={(value) => updateInvoiceLine(index, 'fodec_amount', value)} />
                          <DecimalField label="Autres taxes" value={line.other_tax_amount} onChange={(value) => updateInvoiceLine(index, 'other_tax_amount', value)} />
                        </div>
                      </details>
                    ))}
                  </FieldGroup>
                </fieldset>
                <div className="flex flex-wrap items-center justify-between gap-2">
                  {invoiceNeedsSave && <span className="text-[11px] font-medium text-amber-800">Modifications non enregistrées</span>}
                  <button type="submit" disabled={savingInvoice || !invoiceNeedsSave} className="ml-auto inline-flex items-center gap-2 rounded-md bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-60">
                    {savingInvoice ? <LoaderCircle className="animate-spin" size={16} /> : <Save size={16} />}
                    {savingInvoice ? 'Enregistrement…' : 'Enregistrer les données'}
                  </button>
                </div>
                <p className="text-[11px] leading-5 text-slate-500">Quand les champs obligatoires sont complets, l’analyse comptable redémarre automatiquement. Les versions précédentes restent conservées pour audit.</p>
              </form>
            ) : (
              <InvoiceDataSummary data={invoiceData} />
            )}
          </section>

          <section className="rounded-xl border border-slate-200 bg-white p-4">
            <div className="flex items-start justify-between gap-3">
              <div>
                <h3 className="text-sm font-semibold text-slate-900">2 Type de facture</h3>
                <p className="mt-0.5 text-[11px] text-slate-500">Choix métier à confirmer par le comptable.</p>
              </div>
              {detail.proposal?.invoice_type_label && <span className="rounded-full border border-teal-100 bg-teal-50 px-2.5 py-1 text-[11px] font-semibold text-teal-800">{detail.proposal.invoice_type_label}</span>}
            </div>
            {canEditProposal && proposalForm ? (
              <label className="mt-3 block text-xs font-semibold text-slate-700">Catégorie de dépense / facture
                <select required className={selectClass} value={proposalForm.invoice_type ?? ''} onChange={(event) => updateProposalField('invoice_type', event.target.value || null)}>
                  <option value="">Choisir un type de facture</option>
                  {detail.options.invoice_types.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                </select>
              </label>
            ) : (
              <p className="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-sm font-medium text-slate-800">{detail.proposal?.invoice_type_label || (detail.invoice.status === 'accounting_analysis' ? 'Classification en cours…' : 'Type non disponible ou non renseigné')}</p>
            )}
            <p className="mt-2 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-[11px] leading-4 text-slate-600">
              Profil d’activité de la société : {[detail.company_profile.activity, detail.company_profile.sector].filter(Boolean).join(' · ') || 'non renseigné ; sélection à confirmer manuellement'}.
            </p>
            {canEditProposal && proposalForm && !proposalForm.invoice_type && <p className="mt-2 text-[10px] font-medium text-amber-800">Sélectionnez un type avant d’enregistrer ou de valider l’écriture.</p>}
            {proposalNeedsSave && <p className="mt-2 text-[10px] font-medium text-amber-800">Les modifications de proposition ne sont pas encore enregistrées.</p>}
          </section>

          <section className="rounded-xl border border-slate-200 bg-white p-4">
            <div className="mb-3 flex flex-wrap items-start justify-between gap-3">
              <div>
                <h3 className="text-sm font-semibold text-slate-900">3 Écriture comptable proposée</h3>
                <p className="mt-0.5 text-[11px] text-slate-500">Brouillon à vérifier par un humain. Journal, libellé, comptes et lignes sont modifiables.</p>
              </div>
              <div className="flex flex-wrap items-center gap-2">
                {detail.proposal && <span className="rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-semibold text-amber-900">Brouillon · {detail.proposal.journal_code || 'journal à vérifier'}</span>}
                {canRegenerateProposal && <button type="button" onClick={regenerateProposal} disabled={regeneratingProposal || invoiceNeedsSave || proposalNeedsSave} className="inline-flex items-center gap-1.5 rounded-md border border-teal-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-teal-800 hover:bg-teal-50 disabled:cursor-not-allowed disabled:opacity-50">{regeneratingProposal ? <LoaderCircle className="animate-spin" size={13} /> : <Sparkles size={13} />}{regeneratingProposal ? 'Régénération…' : 'Régénérer la proposition IA'}</button>}
              </div>
            </div>
            {detail.proposal ? (
              <>
                <div id="proposal" className="scroll-mt-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                  <div className="flex flex-wrap items-start justify-between gap-2">
                    <div>
                      <p className="text-xs font-semibold text-slate-900">{detail.proposal.entry_description || 'Écriture proposée'}</p>
                      <p className="mt-1 text-[11px] text-slate-500">{detail.proposal.journal_label || 'Journal non disponible'} · modèle {detail.proposal.model}</p>
                    </div>
                    <ProposalStatusPill status={detail.proposal.status} />
                  </div>
                  {detail.proposal.journal_entry_id && <p className="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-800"><BadgeCheck size={14} /> Écriture créée · n° {detail.proposal.journal_entry_id}</p>}
                  {detail.invoice.accounting_exported_at && <p className="mt-1 text-[11px] text-indigo-700">Dernier export CSV · {formatTimestamp(detail.invoice.accounting_exported_at)}</p>}
                </div>
                {detail.proposal.warnings.length > 0 && <div className="mt-3"><WarningList warnings={detail.proposal.warnings} /></div>}

                {canEditProposal && proposalForm ? (
                  <form onSubmit={saveProposal} className="mt-4 space-y-3">
                    <div className="grid gap-3 sm:grid-cols-2">
                      <label className="block text-xs font-semibold text-slate-700">Journal
                        <select className={selectClass} value={proposalForm.journal_id} onChange={(event) => updateProposalField('journal_id', Number(event.target.value))}>
                          {detail.options.journals.map((journal) => <option key={journal.id} value={journal.id}>{journal.code} — {journal.label}</option>)}
                        </select>
                      </label>
                      <TextField label="Libellé d’écriture" value={proposalForm.entry_description} onChange={(value) => updateProposalField('entry_description', value ?? '')} />
                    </div>
                    <div className="overflow-x-auto rounded-lg border border-slate-200">
                      <table className="min-w-[850px] w-full text-left text-xs">
                        <thead className="bg-slate-50 text-slate-600"><tr><th className="px-3 py-2">Compte</th><th className="px-3 py-2">Tiers</th><th className="px-3 py-2">Analytique</th><th className="px-3 py-2">Libellé</th><th className="px-3 py-2 text-right">Débit</th><th className="px-3 py-2 text-right">Crédit</th><th className="px-3 py-2 text-right">Confiance</th><th className="px-2 py-2"><span className="sr-only">Supprimer</span></th></tr></thead>
                        <tbody className="divide-y divide-slate-100">
                          {proposalForm.lines.map((line, index) => (
                            <tr key={line.id ?? `line-${index}`}>
                              <td className="px-2 py-2"><select aria-label={`Compte comptable de la ligne ${index + 1}`} className="w-40 rounded border border-slate-300 px-2 py-1.5" value={line.chart_account_id || ''} onChange={(event) => updateProposalLine(index, 'chart_account_id', Number(event.target.value))}><option value="">Choisir un compte</option>{detail.options.chart_accounts.map((account) => <option key={account.id} value={account.id}>{account.code} — {account.label}</option>)}</select></td>
                              <td className="px-2 py-2"><select aria-label={`Tiers de la ligne ${index + 1}`} className="w-36 rounded border border-slate-300 px-2 py-1.5" value={line.third_party_id ?? ''} onChange={(event) => updateProposalLine(index, 'third_party_id', event.target.value ? Number(event.target.value) : null)}><option value="">Aucun tiers</option>{detail.options.third_parties.map((party) => <option key={party.id} value={party.id}>{party.code} — {party.name}</option>)}</select></td>
                              <td className="px-2 py-2"><select aria-label={`Axe analytique de la ligne ${index + 1}`} className="w-36 rounded border border-slate-300 px-2 py-1.5" value={line.analytical_account_id ?? ''} onChange={(event) => updateProposalLine(index, 'analytical_account_id', event.target.value ? Number(event.target.value) : null)}><option value="">Aucun axe</option>{detail.options.analytical_accounts.map((item) => <option key={item.id} value={item.id}>{item.code} — {item.label}</option>)}</select></td>
                              <td className="px-2 py-2"><input aria-label={`Libellé de la ligne ${index + 1}`} className="w-40 rounded border border-slate-300 px-2 py-1.5" value={line.description ?? ''} onChange={(event) => updateProposalLine(index, 'description', event.target.value || null)} /></td>
                              <td className="px-2 py-2"><input aria-label={`Débit de la ligne ${index + 1}`} inputMode="decimal" className="w-24 rounded border border-slate-300 px-2 py-1.5 text-right tabular-nums" value={line.debit} onChange={(event) => updateProposalLine(index, 'debit', event.target.value)} /></td>
                              <td className="px-2 py-2"><input aria-label={`Crédit de la ligne ${index + 1}`} inputMode="decimal" className="w-24 rounded border border-slate-300 px-2 py-1.5 text-right tabular-nums" value={line.credit} onChange={(event) => updateProposalLine(index, 'credit', event.target.value)} /></td>
                              <td className="whitespace-nowrap px-3 py-2 text-right tabular-nums text-slate-500">{line.confidence === null ? '—' : `${(Number(line.confidence) * 100).toFixed(0)}%`}</td>
                              <td className="px-2 py-2"><button type="button" aria-label={`Supprimer la ligne ${index + 1}`} onClick={() => updateProposalField('lines', proposalForm.lines.filter((_, lineIndex) => lineIndex !== index))} className="rounded p-1.5 text-red-600 hover:bg-red-50"><Trash2 size={14} /></button></td>
                            </tr>
                          ))}
                        </tbody>
                        <tfoot className="border-t border-slate-200 bg-slate-50 font-semibold text-slate-800">
                          <tr>
                            <td colSpan={4} className="px-3 py-2">Totaux de l’écriture{proposalNeedsSave ? ' · non enregistrés' : ''}</td>
                            <td className="px-3 py-2 text-right tabular-nums">{formatMilliCurrency(draftDebitTotal, invoiceData.currency)}</td>
                            <td className="px-3 py-2 text-right tabular-nums">{formatMilliCurrency(draftCreditTotal, invoiceData.currency)}</td>
                            <td colSpan={2} />
                          </tr>
                        </tfoot>
                      </table>
                    </div>
                    <div className="flex flex-wrap items-center justify-between gap-2">
                      <button type="button" onClick={() => updateProposalField('lines', [...proposalForm.lines, { chart_account_id: detail.options.chart_accounts[0]?.id ?? 0, third_party_id: null, analytical_account_id: null, description: '', debit: '0.000', credit: '0.000', confidence: null }])} className="inline-flex items-center gap-1.5 rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:border-teal-400"><Plus size={14} /> Ajouter une ligne</button>
                      {proposalNeedsSave && <span className="text-[11px] font-medium text-amber-800">Modifications non enregistrées</span>}
                      <button type="submit" disabled={savingProposal || !proposalForm.invoice_type || detail.options.journals.length === 0 || detail.options.chart_accounts.length === 0 || !proposalNeedsSave} className="ml-auto inline-flex items-center gap-2 rounded-md bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-60">{savingProposal ? <LoaderCircle className="animate-spin" size={16} /> : <Save size={16} />}{savingProposal ? 'Enregistrement…' : 'Enregistrer la proposition'}</button>
                    </div>
                    <p className="text-[11px] leading-5 text-slate-500">Les comptes, journaux, tiers et axes proposés sont vérifiés côté serveur. Une proposition déséquilibrée ne peut pas être validée.</p>
                  </form>
                ) : (
                  <div className="mt-3"><ProposalReadOnly proposal={detail.proposal} currency={invoiceData.currency} /></div>
                )}
              </>
            ) : (
              <div className="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                {detail.invoice.status === 'accounting_analysis'
                  ? <span className="inline-flex items-center gap-2"><LoaderCircle className="animate-spin" size={16} /> L’analyse des comptes et la génération de l’écriture sont en cours.</span>
                  : detail.invoice.status === 'accounting_analysis_failed'
                    ? detail.invoice.ocr_error_message || 'L’analyse comptable a échoué.'
                    : 'La proposition apparaîtra après l’extraction complète des données de facture.'}
              </div>
            )}
          </section>

          <section aria-label="Contrôles comptables" className="rounded-xl border border-slate-200 bg-white p-4">
            <div className="flex items-start justify-between gap-3">
              <div>
                <h3 className="flex items-center gap-2 text-sm font-semibold text-slate-900"><ShieldCheck size={16} className="text-teal-700" /> 4 Contrôles comptables</h3>
                <p className="mt-0.5 text-[11px] text-slate-500">Vérifications déterministes côté serveur ; un avertissement reste une décision humaine.</p>
              </div>
              {(invoiceNeedsSave || proposalNeedsSave) && <span className="rounded-full bg-amber-50 px-2 py-1 text-[10px] font-semibold text-amber-800">En attente d’enregistrement</span>}
            </div>
            <div className="mt-3 space-y-2">
              <CheckCard label="Fournisseur / tiers actif" check={detail.checks.supplier} />
              <CheckCard label="Référence de facture" check={detail.checks.invoice_number} />
              <CheckCard label="Taux et montant de TVA" check={detail.checks.vat} />
              <CheckCard label="TTC brut, retenue et net à payer" check={detail.checks.totals} />
              <CheckCard label="Équilibre débit / crédit" check={detail.checks.balance} />
              <CheckCard label="Comptes et journaux actifs de la société" check={detail.checks.accounts} />
              <CheckCard label="Doublon de fichier" check={detail.checks.duplicate} />
              <CheckCard label="Exercice fiscal" check={detail.checks.fiscal_year} />
              <CheckCard label="Devise" check={detail.checks.currency} />
            </div>
          </section>

          <details id="ocr-text" className="scroll-mt-3 rounded-xl border border-slate-200 bg-white p-4">
            <summary className="flex cursor-pointer list-none items-center justify-between gap-3 text-sm font-semibold text-slate-800">
              <span className="flex items-center gap-2"><FileText size={16} className="text-teal-700" /> Texte reconnu par OCR</span>
              <span className="text-[11px] font-normal text-slate-400">{detail.invoice.ocr_text ? 'Afficher' : 'Indisponible'}</span>
            </summary>
            <div className="mt-3">
              {detail.invoice.ocr_display_text
                ? <pre className="max-h-[50vh] overflow-auto whitespace-pre-wrap break-words rounded-lg border border-slate-200 bg-slate-50 p-3 font-mono text-xs leading-5 text-slate-800">{detail.invoice.ocr_display_text}</pre>
                : <p className="rounded-lg bg-slate-50 p-3 text-xs text-slate-600">Aucun texte OCR n’est disponible pour cette étape.{detail.invoice.ocr_error_message ? ` ${detail.invoice.ocr_error_message}` : ''}</p>}
            </div>
          </details>
        </div>
      )}

      <footer className="shrink-0 border-t border-slate-200 bg-white px-3 py-3 sm:px-4">
        <div className="mb-2 flex items-start justify-between gap-3">
          <div>
            <h3 className="text-sm font-semibold text-slate-900">5 Décision du comptable</h3>
            <p className="mt-0.5 text-[10px] leading-4 text-slate-500">L’IA propose, les règles contrôlent, le comptable décide. Le CSV est un format interne ; aucune synchronisation Sage n’est effectuée.</p>
          </div>
        </div>
        {detail?.can_manage && detail.proposal?.status === 'ready' && detail.invoice.status === 'proposal_ready' ? (
          <>
            <div className="grid grid-cols-2 gap-2">
              <button type="button" onClick={() => approveProposal(false)} disabled={!canValidate || approving || rejecting} className="inline-flex items-center justify-center gap-1.5 rounded-lg border border-teal-200 bg-teal-50 px-2 py-2 text-xs font-semibold text-teal-900 hover:bg-teal-100 disabled:cursor-not-allowed disabled:opacity-50">{approving ? <LoaderCircle className="animate-spin" size={14} /> : <ClipboardCheck size={14} />}{approving ? 'Validation…' : 'Valider'}</button>
              <button type="button" onClick={() => approveProposal(true)} disabled={!canValidate || approving || rejecting || exportingCsv} className="inline-flex items-center justify-center gap-1.5 rounded-lg bg-emerald-700 px-2 py-2 text-xs font-semibold text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50">{approving || exportingCsv ? <LoaderCircle className="animate-spin" size={14} /> : <Download size={14} />}{approving ? 'Validation…' : exportingCsv ? 'Export…' : 'Valider & exporter'}</button>
              <button type="button" onClick={() => void exportProposalCsv()} disabled={!canExportCsv || exportingCsv || invoiceNeedsSave || proposalNeedsSave} className="inline-flex items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2 py-2 text-xs font-semibold text-slate-700 hover:border-teal-300 disabled:cursor-not-allowed disabled:opacity-50">{exportingCsv ? <LoaderCircle className="animate-spin" size={14} /> : <Download size={14} />}{exportingCsv ? 'Export…' : 'Export CSV'}</button>
              <button type="button" onClick={rejectProposal} disabled={rejecting || approving || invoiceNeedsSave || proposalNeedsSave} className="inline-flex items-center justify-center gap-1.5 rounded-lg border border-red-200 bg-white px-2 py-2 text-xs font-semibold text-red-800 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50">{rejecting ? <LoaderCircle className="animate-spin" size={14} /> : <X size={14} />}{rejecting ? 'Rejet…' : 'Rejeter'}</button>
            </div>
            {invoiceNeedsSave && <p className="mt-2 text-[10px] font-medium text-amber-800">Enregistrez les données facture avant toute décision.</p>}
            {proposalNeedsSave && <p className="mt-1 text-[10px] font-medium text-amber-800">Enregistrez la proposition et son type avant toute décision.</p>}
          </>
        ) : detail?.can_manage && canExportCsv ? (
          <button type="button" onClick={() => void exportProposalCsv()} disabled={exportingCsv} className="inline-flex items-center gap-2 rounded-lg bg-teal-700 px-3 py-2 text-xs font-semibold text-white hover:bg-teal-800 disabled:opacity-50">{exportingCsv ? <LoaderCircle className="animate-spin" size={14} /> : <Download size={14} />}{exportingCsv ? 'Export en cours…' : 'Export CSV'}</button>
        ) : (
          <p className="text-[10px] leading-4 text-slate-500">Aucune décision n’est disponible à cette étape ou pour ces permissions. Une écriture définitive n’est créée qu’après validation humaine.</p>
        )}
      </footer>
    </section>
  );
}

function ModelIndicator({ label, value, complete }: { label: string; value: string; complete: boolean }) {
  return (
    <div className="min-w-0 rounded-lg border border-white/80 bg-white/85 p-2.5">
      <p className="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{label}</p>
      <p className={`mt-1 flex items-center gap-1.5 truncate text-[11px] font-medium ${complete ? 'text-slate-800' : 'text-slate-400'}`} title={value}>
        {complete ? <CheckCircle2 size={12} className="shrink-0 text-emerald-600" /> : <Circle size={12} className="shrink-0" />}{value}
      </p>
    </div>
  );
}

function ConfidenceRing({ value }: { value: number | null }) {
  const progress = value === null ? 0 : Math.max(0, Math.min(1, value)) * 360;
  const color = confidenceColor(value);
  return (
    <div className="grid h-[76px] w-[76px] place-items-center rounded-full" style={{ background: `conic-gradient(${color} ${progress}deg, #e2e8f0 ${progress}deg)` }} aria-label={value === null ? 'Moyenne des confidences comptables non disponible' : `Moyenne des confidences de lignes ${Math.round(value * 100)} pour cent`}>
      <div className="grid h-[60px] w-[60px] place-items-center rounded-full bg-white text-lg font-bold tabular-nums text-slate-900">{value === null ? 'N/D' : `${Math.round(value * 100)}%`}</div>
    </div>
  );
}

function ConfidenceBar({ label, value }: { label: string; value: number | null }) {
  const color = confidenceColor(value);
  const percentage = value === null ? 0 : Math.max(0, Math.min(1, value)) * 100;
  return (
    <div>
      <div className="mb-1 flex items-center justify-between gap-2 text-[11px]">
        <span className="font-medium text-slate-700">{label}</span>
        <span className={`font-semibold tabular-nums ${value === null ? 'text-slate-400' : 'text-slate-700'}`}>{value === null ? 'N/D' : `${Math.round(value * 100)}%`}</span>
      </div>
      <div className="h-1.5 overflow-hidden rounded-full bg-slate-200" role="img" aria-label={value === null ? `${label} : score non disponible` : `${label} : ${Math.round(value * 100)} pour cent`}>
        {value !== null && <div className="h-full rounded-full transition-[width]" style={{ width: `${percentage}%`, backgroundColor: color }} />}
      </div>
    </div>
  );
}

function confidenceColor(value: number | null): string {
  if (value === null) return '#cbd5e1';
  if (value >= 0.8) return '#059669';
  if (value >= 0.6) return '#d97706';
  return '#dc2626';
}

type WorkflowState = 'complete' | 'current' | 'warning' | 'failed' | 'pending';
type WorkflowStep = { label: string; caption: string; state: WorkflowState };

function workflowSteps(detail: InvoiceReviewDetail): WorkflowStep[] {
  const { invoice, proposal, checks } = detail;
  const status = invoice.status;
  const proposalExists = Boolean(proposal);
  const structured = Boolean(invoice.extraction_model || invoice.ocr_data.supplier_name || invoice.ocr_data.invoice_number);
  const failed = failedStatuses.includes(status);
  const accountingChecks = [checks.supplier, checks.invoice_number, checks.vat, checks.balance, checks.totals, checks.duplicate, checks.accounts, checks.fiscal_year, checks.currency];
  const checkWarning = accountingChecks.some((check) => check.status === 'warning' || check.status === 'blocking');
  const checkPending = accountingChecks.some((check) => check.status === 'pending' || check.status === 'unavailable');

  return [
    { label: 'Lecture du document', caption: 'Fichier privé reçu', state: 'complete' },
    {
      label: 'OCR',
      caption: invoice.ocr_text ? 'Texte reconnu' : status === 'ocr_queued' || status === 'ocr_processing' ? 'Lecture automatique' : 'Transcription',
      state: invoice.ocr_text ? 'complete' : status === 'ocr_queued' || status === 'ocr_processing' ? 'current' : status === 'ocr_failed' ? 'failed' : 'pending',
    },
    {
      label: 'Identification du fournisseur',
      caption: invoice.ocr_data.supplier_name || 'Extraction des champs',
      state: invoice.ocr_data.supplier_name ? 'complete' : status === 'data_extraction' ? 'current' : status === 'data_extraction_failed' ? 'failed' : 'pending',
    },
    {
      label: 'Classification comptable',
      caption: proposal ? 'Proposition à confirmer' : 'À partir des lignes extraites',
      state: proposalExists ? 'complete' : status === 'accounting_analysis' ? 'current' : status === 'accounting_analysis_failed' ? 'failed' : 'pending',
    },
    {
      label: 'Recherche des comptes',
      caption: proposal ? `${proposal.lines.length} ligne${proposal.lines.length === 1 ? '' : 's'} proposée${proposal.lines.length === 1 ? '' : 's'}` : 'Référentiels actifs de la société',
      state: proposalExists ? 'complete' : status === 'accounting_analysis' ? 'current' : status === 'accounting_analysis_failed' ? 'failed' : 'pending',
    },
    {
      label: 'Génération de l’écriture',
      caption: proposal ? 'Brouillon modifiable' : structured ? 'Après contrôles de complétude' : 'Après extraction',
      state: proposalExists ? 'complete' : status === 'accounting_analysis' ? 'current' : status === 'accounting_analysis_failed' ? 'failed' : 'pending',
    },
    {
      label: 'Contrôles comptables',
      caption: !proposal
        ? 'En attente de la proposition'
        : checkWarning
          ? 'Un ou plusieurs points à vérifier'
          : checkPending
            ? 'Contrôle en attente ou non configuré'
            : 'Contrôles serveur exécutés',
      state: !proposal ? (failed ? 'warning' : 'pending') : checkWarning ? 'warning' : checkPending ? 'pending' : 'complete',
    },
  ];
}

function WorkflowIcon({ state }: { state: WorkflowState }) {
  if (state === 'complete') return <CheckCircle2 size={15} className="mt-0.5 shrink-0 text-emerald-600" />;
  if (state === 'current') return <LoaderCircle size={15} className="mt-0.5 shrink-0 animate-spin text-teal-700" />;
  if (state === 'warning' || state === 'failed') return <AlertTriangle size={15} className="mt-0.5 shrink-0 text-amber-600" />;
  return <Circle size={15} className="mt-0.5 shrink-0 text-slate-300" />;
}

function workflowTitle(status: string): string {
  if (status === 'ocr_queued') return 'Analyse IA en attente';
  if (activeStatuses.includes(status)) return 'Analyse IA en cours';
  if (failedStatuses.includes(status)) return 'Analyse IA à relancer';
  if (status === 'invoice_incomplete') return 'Informations à compléter';
  if (status === 'proposal_ready') return 'Proposition prête pour la revue';
  if (status === 'accounting_validated') return 'Écriture comptable validée';
  if (status === 'accounting_exported') return 'Écriture exportée en CSV';
  if (status === 'proposal_rejected') return 'Proposition rejetée';
  if (status === 'ocr_completed') return 'Texte OCR prêt à vérifier';
  return 'Traitement de la facture';
}

function workflowDescription(status: string, error: string | null): string {
  if (status === 'ocr_queued') return 'L’import a automatiquement ajouté ce document à la file de traitement. Les étapes suivantes se mettront à jour sans démarrage manuel.';
  if (status === 'ocr_processing') return 'Le document est en cours de lecture OCR ; les champs et la proposition comptable suivront automatiquement.';
  if (status === 'data_extraction') return 'Le texte OCR est disponible. L’extraction structurée identifie actuellement le fournisseur, les références et les montants.';
  if (status === 'accounting_analysis') return 'Les règles métier et les comptes actifs de la société sont consultés pour produire un brouillon comptable.';
  if (failedStatuses.includes(status)) return error || 'Une étape du traitement a échoué. Vous pouvez relancer l’étape concernée.';
  if (status === 'invoice_incomplete') return 'Corrigez les champs signalés. L’analyse comptable reprendra automatiquement lorsque les données obligatoires seront complètes.';
  if (status === 'proposal_ready') return 'L’IA a préparé un brouillon. Vérifiez les indicateurs, les contrôles et le document avant de décider.';
  if (status === 'accounting_validated') return 'La décision humaine a créé une écriture liée à cette facture. Vous pouvez maintenant exporter son CSV.';
  if (status === 'accounting_exported') return 'Un CSV comptable interne a été généré. Ce transfert ne constitue pas une synchronisation Sage.';
  if (status === 'proposal_rejected') return 'La proposition a été rejetée ; aucune écriture n’a été créée.';
  if (status === 'ocr_completed') return 'La transcription est disponible ; vérifiez le document et les champs avant de poursuivre.';
  return 'L’IA extrait et propose ; les règles vérifient ; le comptable prend la décision finale.';
}

function StatusPill({ status }: { status: string }) {
  const isActive = activeStatuses.includes(status);
  const isError = failedStatuses.includes(status);
  const isValidated = ['accounting_validated', 'accounting_exported'].includes(status);
  const label = status === 'ocr_queued' ? 'En attente'
    : isActive ? 'En cours'
      : status === 'proposal_ready' ? 'À vérifier'
        : status === 'invoice_incomplete' ? 'À compléter'
          : status === 'accounting_exported' ? 'Exportée'
            : status === 'accounting_validated' ? 'Validée'
              : status === 'proposal_rejected' ? 'Rejetée'
                : isError ? 'Échec'
                  : status === 'ocr_completed' ? 'OCR terminé' : 'Importée';
  const tone = isError ? 'bg-red-50 text-red-800'
    : isValidated ? 'bg-emerald-50 text-emerald-800'
      : isActive ? 'bg-blue-50 text-blue-800'
        : status === 'proposal_ready' ? 'bg-teal-50 text-teal-800'
          : 'bg-amber-50 text-amber-900';

  return <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold ${tone}`}>{isActive && <LoaderCircle className="animate-spin" size={12} />}{label}</span>;
}

function CheckCard({ label, check }: { label: string; check: AccountingCheck }) {
  const states: Record<CheckStatus, { text: string; className: string }> = {
    passed: { text: 'Vérifié', className: 'text-emerald-700 bg-emerald-50' },
    warning: { text: 'À vérifier', className: 'text-amber-800 bg-amber-50' },
    blocking: { text: 'Bloquant', className: 'text-red-800 bg-red-50' },
    pending: { text: 'En attente', className: 'text-slate-600 bg-slate-100' },
    unavailable: { text: 'Non configuré', className: 'text-slate-600 bg-slate-100' },
  };
  const badge = states[check.status];
  const Icon = check.status === 'passed' ? CheckCircle2 : check.status === 'warning' ? AlertTriangle : check.status === 'blocking' ? X : check.status === 'unavailable' ? Info : CircleHelp;

  return (
    <div className="rounded-lg border border-slate-200 p-3">
      <div className="flex items-start justify-between gap-2">
        <p className="text-xs font-semibold text-slate-800">{label}</p>
        <span className={`inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold ${badge.className}`}><Icon size={11} />{badge.text}</span>
      </div>
      <p className="mt-1.5 text-[10px] leading-4 text-slate-500">{check.detail}</p>
    </div>
  );
}

function ProposalStatusPill({ status }: { status: string }) {
  const label = status === 'approved' ? 'Validée' : status === 'rejected' ? 'Rejetée' : status === 'superseded' ? 'Remplacée' : 'À vérifier';
  const tone = status === 'approved' ? 'bg-emerald-100 text-emerald-800' : status === 'rejected' ? 'bg-red-100 text-red-800' : status === 'superseded' ? 'bg-slate-200 text-slate-700' : 'bg-amber-100 text-amber-900';
  return <span className={`rounded-full px-2.5 py-1 text-[10px] font-semibold ${tone}`}>{label}</span>;
}

function FieldGroup({ title, children }: { title: string; children: React.ReactNode }) {
  return <section className="space-y-3 rounded-lg border border-slate-200 p-3"><h4 className="text-xs font-semibold text-slate-900">{title}</h4>{children}</section>;
}

function TextField({ label, value, onChange, type = 'text', placeholder, maxLength, inputMode }: { label: string; value: string | null; onChange: (value: string | null) => void; type?: string; placeholder?: string; maxLength?: number; inputMode?: React.HTMLAttributes<HTMLInputElement>['inputMode'] }) {
  return <label className="block text-[11px] font-semibold text-slate-700">{label}<input className={inputClass} type={type} value={value ?? ''} placeholder={placeholder} maxLength={maxLength} inputMode={inputMode} onChange={(event) => onChange(event.target.value === '' ? null : event.target.value)} /></label>;
}

function DecimalField({ label, value, onChange }: { label: string; value: string | null; onChange: (value: string | null) => void }) {
  return <TextField label={label} value={value} onChange={onChange} placeholder="—" inputMode="decimal" />;
}

function TextAreaField({ label, value, onChange }: { label: string; value: string | null; onChange: (value: string | null) => void }) {
  return <label className="mt-3 block text-[11px] font-semibold text-slate-700">{label}<textarea className={`${inputClass} min-h-20`} value={value ?? ''} onChange={(event) => onChange(event.target.value === '' ? null : event.target.value)} /></label>;
}

function InvoiceDataSummary({ data }: { data: InvoiceData }) {
  const fields: [string, string | null][] = [
    ['Fournisseur', data.supplier_name],
    ['Immatriculation fiscale', data.supplier_tax_identifier],
    ['Adresse fournisseur', data.supplier_address],
    ['Téléphone fournisseur', data.supplier_phone],
    ['E-mail fournisseur', data.supplier_email],
    ['Client', data.customer_name],
    ['N° facture', data.invoice_number],
    ['Date facture', data.invoice_date],
    ['Échéance', data.due_date],
    ['Devise', data.currency],
    ['Mode de paiement', data.payment_method],
    ['Référence commande', data.purchase_order_reference],
  ];

  return (
    <div className="space-y-3">
      <dl className="grid gap-2 rounded-lg border border-slate-200 p-3 sm:grid-cols-2">
        {fields.map(([label, value]) => <div key={label}><dt className="text-[10px] text-slate-500">{label}</dt><dd className="mt-0.5 break-words text-xs font-medium text-slate-900">{value || '—'}</dd></div>)}
      </dl>
      <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
        <AmountCard label="Total HT" value={data.subtotal} currency={data.currency} />
        <AmountCard label="TVA" value={data.vat_amount} currency={data.currency} />
        <AmountCard label="FODEC" value={data.fodec_amount} currency={data.currency} />
        <AmountCard label="Droit de timbre" value={data.stamp_amount} currency={data.currency} />
        <AmountCard label="TTC brut" value={data.total_amount} currency={data.currency} emphasis />
        <AmountCard label="Retenue à la source" value={data.withholding_amount} currency={data.currency} />
        <AmountCard label="Net à payer" value={data.net_to_pay_amount} currency={data.currency} />
      </div>
      <p className="rounded-lg bg-slate-50 p-3 text-xs leading-5 text-slate-600">{data.description || 'Aucune description extraite.'}</p>
      {data.lines.length > 0 && <p className="text-[11px] text-slate-500">{data.lines.length} ligne{data.lines.length === 1 ? '' : 's'} de facture extraite{data.lines.length === 1 ? '' : 's'}.</p>}
    </div>
  );
}

function AmountCard({ label, value, currency, emphasis = false }: { label: string; value: string | null; currency: string | null; emphasis?: boolean }) {
  return <div className={`rounded-lg border p-3 ${emphasis ? 'border-teal-200 bg-teal-50' : 'border-slate-200 bg-white'}`}><p className="text-[10px] text-slate-500">{label}</p><p className={`mt-1 text-sm font-semibold tabular-nums ${emphasis ? 'text-teal-900' : 'text-slate-900'}`}>{value ? `${value} ${currency || ''}` : '—'}</p></div>;
}

function ProposalReadOnly({ proposal, currency }: { proposal: Proposal; currency: string | null }) {
  return (
    <div className="overflow-x-auto rounded-lg border border-slate-200">
      <table className="min-w-[680px] w-full text-left text-[11px]">
        <thead className="bg-slate-50 text-slate-600"><tr><th className="px-3 py-2">Compte</th><th className="px-3 py-2">Tiers</th><th className="px-3 py-2">Analytique</th><th className="px-3 py-2">Libellé</th><th className="px-3 py-2 text-right">Débit</th><th className="px-3 py-2 text-right">Crédit</th></tr></thead>
        <tbody className="divide-y divide-slate-100">
          {proposal.lines.map((line, index) => <tr key={line.id ?? index}>
            <td className="px-3 py-2">{line.chart_account_code || '—'} · {line.chart_account_label || 'Compte'}</td>
            <td className="px-3 py-2">{line.third_party_code ? `${line.third_party_code} · ${line.third_party_name}` : '—'}</td>
            <td className="px-3 py-2">{line.analytical_account_code ? `${line.analytical_account_code} · ${line.analytical_account_label}` : '—'}</td>
            <td className="px-3 py-2">{line.description || '—'}</td>
            <td className="px-3 py-2 text-right tabular-nums">{line.debit}</td>
            <td className="px-3 py-2 text-right tabular-nums">{line.credit}</td>
          </tr>)}
        </tbody>
        <tfoot className="border-t border-slate-200 bg-slate-50 font-semibold text-slate-800">
          <tr>
            <td colSpan={4} className="px-3 py-2">Totaux</td>
            <td className="px-3 py-2 text-right tabular-nums">{formatMilliCurrency(sumProposalAmounts(proposal.lines, 'debit'), currency)}</td>
            <td className="px-3 py-2 text-right tabular-nums">{formatMilliCurrency(sumProposalAmounts(proposal.lines, 'credit'), currency)}</td>
          </tr>
        </tfoot>
      </table>
      <p className="border-t border-slate-100 px-3 py-2 text-[10px] text-slate-500">Confiance moyenne des lignes comptables : {proposal.confidence === null ? 'non disponible' : `${Math.round(proposal.confidence * 100)}%`}</p>
    </div>
  );
}

function WarningList({ warnings }: { warnings: string[] }) {
  return <div className="rounded-lg border border-amber-200 bg-amber-50 p-3" role="status"><p className="flex items-center gap-2 text-xs font-semibold text-amber-950"><AlertTriangle size={14} /> Points à vérifier</p><ul className="mt-1.5 space-y-1 text-[11px] leading-4 text-amber-900">{warnings.map((warning) => <li key={warning}>{warningLabel(warning)}</li>)}</ul></div>;
}

function warningLabel(warning: string): string {
  const labels: Record<string, string> = {
    missing_supplier_name: 'Nom du fournisseur manquant.',
    missing_invoice_number: 'Numéro de facture manquant.',
    missing_invoice_date: 'Date de facture manquante ou illisible.',
    missing_currency: 'Devise manquante ou illisible.',
    missing_total_amount: 'Total à payer manquant ou illisible.',
    missing_invoice_description: 'Description ou lignes de facture manquantes.',
    invoice_total_mismatch: 'La somme HT + taxes + timbre ne correspond pas au TTC brut imprimé (hors retenue).',
    invoice_vat_mismatch: 'Le montant de TVA ne correspond pas au taux et à la base déclarés.',
    invoice_net_to_pay_mismatch: 'Le net à payer ne correspond pas au TTC diminué de la retenue.',
    invoice_totals_unverified: 'Le contrôle des totaux n’est pas conclusif : certains montants sont absents.',
    proposal_unbalanced: 'La proposition n’est pas équilibrée : les débits et crédits doivent être égaux.',
    proposal_invoice_total_mismatch: 'Le total des débits ne correspond pas au TTC brut de la facture.',
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
  return { reference: null, description: null, quantity: null, unit_price: null, discount_amount: null, subtotal: null, vat_rate: null, vat_amount: null, fodec_rate: null, fodec_amount: null, other_tax_amount: null, total_amount: null };
}

function parseMilliAmount(value: string | null): bigint | null {
  if (!value?.trim()) return null;
  const normalized = value.trim().replace(/\s+/g, '').replace(',', '.');
  const match = normalized.match(/^(-?)(\d{1,15})(?:\.(\d{1,3}))?$/);
  if (!match) return null;
  const whole = BigInt(match[2]);
  const fraction = BigInt((match[3] ?? '').padEnd(3, '0') || '0');
  const amount = whole * 1000n + fraction;
  return match[1] === '-' ? -amount : amount;
}

function percentageOf(amountMilli: bigint, rateMilli: bigint): bigint {
  const sign = amountMilli < 0n ? -1n : 1n;
  const absoluteAmount = amountMilli < 0n ? -amountMilli : amountMilli;
  const denominator = 100_000n;
  const whole = (absoluteAmount / denominator) * rateMilli;
  const remainder = (absoluteAmount % denominator) * rateMilli;
  return sign * (whole + (remainder + denominator / 2n) / denominator);
}

function formatMilliAmount(amount: bigint): string {
  const sign = amount < 0n ? '-' : '';
  const absolute = amount < 0n ? -amount : amount;
  return `${sign}${absolute / 1000n}.${(absolute % 1000n).toString().padStart(3, '0')}`;
}

function sumProposalAmounts(lines: ProposalLine[], field: 'debit' | 'credit'): bigint | null {
  let total = 0n;
  for (const line of lines) {
    const amount = parseMilliAmount(line[field]);
    if (amount === null) return null;
    total += amount;
  }
  return total;
}

function formatMilliCurrency(amount: bigint | null, currency: string | null): string {
  if (amount === null) return '—';
  const unit = currency?.toUpperCase() === 'TND' ? 'DT' : currency?.toUpperCase() || '';
  return `${formatMilliAmount(amount)}${unit ? ` ${unit}` : ''}`;
}

function firstError(errors: Record<string, string | string[]>): string | null {
  const value = Object.values(errors).flat()[0];
  return typeof value === 'string' ? value : null;
}

function formatTimestamp(timestamp: string): string {
  return new Intl.DateTimeFormat('fr-TN', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(timestamp));
}

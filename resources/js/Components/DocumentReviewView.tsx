import { ArrowLeft, Check, CheckCircle2, ClipboardCheck, ExternalLink, FileText, LockKeyhole, Save, ShieldCheck } from 'lucide-react';
import type { DocumentDetail, DocumentFields } from '../types';
import { formatCurrency, formatDate, formatFileSize } from '../utils/format';
import StatusBadge from './StatusBadge';
import StatusNotice from './StatusNotice';

type Props = {
  document: DocumentDetail;
  values: DocumentFields;
  errors: Record<string, string>;
  onChange: (field: keyof DocumentFields, value: string) => void;
  onBack: () => void;
  onSave: () => void;
  onPost: () => void;
  isSaving?: boolean;
  isPosting?: boolean;
  successMessage?: string | null;
};

const fields: Array<{ key: keyof DocumentFields; label: string; type?: string; placeholder?: string; wide?: boolean }> = [
  { key: 'supplier_name', label: 'Fournisseur', placeholder: 'Nom du fournisseur', wide: true },
  { key: 'invoice_number', label: 'N° de facture', placeholder: 'Ex. FAC-2026-001' },
  { key: 'invoice_date', label: 'Date de facture', type: 'date' },
  { key: 'due_date', label: 'Date d’échéance', type: 'date' },
  { key: 'currency', label: 'Devise', placeholder: 'EUR' },
  { key: 'account_code', label: 'Compte comptable', placeholder: 'Ex. 606100' },
  { key: 'description', label: 'Libellé de l’écriture', placeholder: 'Description de la dépense', wide: true },
];

export default function DocumentReviewView({
  document,
  values,
  errors,
  onChange,
  onBack,
  onSave,
  onPost,
  isSaving = false,
  isPosting = false,
  successMessage,
}: Props) {
  const isPosted = document.status === 'posted';
  const fileIsPdf = document.mime_type === 'application/pdf' || document.original_filename.toLowerCase().endsWith('.pdf');

  return (
    <>
      <button className="back-link" onClick={onBack}><ArrowLeft size={16} /> Retour aux documents</button>
      <section className="review-page-heading">
        <div>
          <div className="eyebrow">VÉRIFICATION DE FACTURE</div>
          <h1>{document.supplier_name || 'Nouveau document'}</h1>
          <p>{document.invoice_number || document.original_filename} <span className="heading-separator">·</span> Importé le {formatDate(document.created_at)}</p>
        </div>
        <StatusBadge status={document.status} />
      </section>

      {successMessage && <StatusNotice message={successMessage} />}

      {isPosted && (
        <div className="posted-banner"><CheckCircle2 size={18} /><div><strong>Écriture comptabilisée</strong><span>Cette facture a été ajoutée aux écritures comptables et ne peut plus être modifiée.</span></div></div>
      )}

      <div className="review-layout">
        <section className="panel source-panel">
          <div className="panel-heading source-panel-heading">
            <div><div className="panel-eyebrow">DOCUMENT SOURCE</div><h2>Facture importée</h2></div>
            {document.file_url && <a className="icon-button subtle-icon-button" href={document.file_url} target="_blank" rel="noreferrer" aria-label="Ouvrir la facture"><ExternalLink size={16} /></a>}
          </div>
          <div className="source-preview">
            {document.file_url ? (
              fileIsPdf
                ? <iframe className="source-frame" src={document.file_url} title={`Facture ${document.original_filename}`} />
                : <img className="source-image" src={document.file_url} alt={`Facture ${document.original_filename}`} />
            ) : (
              <div className="source-paper">
                <div className="paper-topline"><div className="paper-logo">{(document.supplier_name || 'F').slice(0, 1).toUpperCase()}</div><div className="paper-lines"><i /><i /><i /></div></div>
                <div className="paper-title">FACTURE</div>
                <div className="paper-reference">{document.invoice_number || 'N° de facture'}</div>
                <div className="paper-rule" />
                <div className="paper-columns"><span>ÉMETTEUR</span><span>DATE</span></div>
                <div className="paper-lines paper-lines--wide"><i /><i /><i /></div>
                <div className="paper-rule paper-rule--spaced" />
                <div className="paper-items"><i /><i /><i /></div>
                <div className="paper-total"><span>Total TTC</span><strong>{formatCurrency(document.total_amount, document.currency)}</strong></div>
                <div className="paper-footer" />
              </div>
            )}
            <div className="source-file-meta">
              <div className="source-file-icon"><FileText size={18} /></div>
              <div className="source-file-name"><strong>{document.original_filename}</strong><span>{fileIsPdf ? 'Document PDF' : document.mime_type || 'Image'}{document.size_bytes ? ` · ${formatFileSize(document.size_bytes)}` : ''}</span></div>
              {document.file_url && <a href={document.file_url} target="_blank" rel="noreferrer" className="source-download" aria-label="Ouvrir le document"><ExternalLink size={16} /></a>}
            </div>
          </div>
          <div className="source-footnote"><LockKeyhole size={14} /><span>Le document source est conservé avec son écriture.</span></div>
        </section>

        <section className="panel review-form-panel">
          <div className="panel-heading review-form-heading">
            <div>
              <div className="panel-eyebrow">INFORMATIONS COMPTABLES</div>
              <h2>Vérifiez les données</h2>
              <p>Complétez ou corrigez les champs avant comptabilisation.</p>
            </div>
            <div className="review-sparkle"><ClipboardCheck size={17} /></div>
          </div>

          <div className="review-fields">
            {fields.map(({ key, label, type = 'text', placeholder, wide }) => (
              <label className={`form-field ${wide ? 'form-field--wide' : ''}`} key={key}>
                <span>{label}</span>
                <input
                  type={type}
                  value={values[key]}
                  onChange={(event) => onChange(key, event.target.value)}
                  placeholder={placeholder}
                  disabled={isPosted || key === 'currency'}
                  aria-invalid={Boolean(errors[key])}
                />
                {errors[key] && <small className="field-error">{errors[key]}</small>}
              </label>
            ))}
          </div>

          <div className="amount-fields">
            <label className="form-field"><span>Montant HT</span><div className="amount-input-wrap"><input type="number" step="0.01" min="0" value={values.subtotal} onChange={(event) => onChange('subtotal', event.target.value)} disabled={isPosted} placeholder="0,00" /><span>€</span></div>{errors.subtotal && <small className="field-error">{errors.subtotal}</small>}</label>
            <label className="form-field"><span>TVA</span><div className="amount-input-wrap"><input type="number" step="0.01" min="0" value={values.vat_amount} onChange={(event) => onChange('vat_amount', event.target.value)} disabled={isPosted} placeholder="0,00" /><span>€</span></div>{errors.vat_amount && <small className="field-error">{errors.vat_amount}</small>}</label>
            <label className="form-field"><span>Montant TTC</span><div className="amount-input-wrap amount-input-wrap--total"><input type="number" step="0.01" min="0" value={values.total_amount} onChange={(event) => onChange('total_amount', event.target.value)} disabled={isPosted} placeholder="0,00" /><span>€</span></div>{errors.total_amount && <small className="field-error">{errors.total_amount}</small>}</label>
          </div>

          {!isPosted ? (
            <div className="review-actions">
              <button className="button button--secondary" onClick={onSave} disabled={isSaving || isPosting}>
                {isSaving ? <span className="button-spinner button-spinner--dark" /> : <Save size={16} />}
                {isSaving ? 'Enregistrement…' : 'Enregistrer'}
              </button>
              <button className="button button--primary" onClick={onPost} disabled={isSaving || isPosting}>
                {isPosting ? <span className="button-spinner" /> : <Check size={17} />}
                {isPosting ? 'Comptabilisation…' : 'Comptabiliser'}
              </button>
            </div>
          ) : (
            <div className="review-actions review-actions--posted"><span><CheckCircle2 size={16} /> Écriture validée</span><button className="button button--secondary" onClick={onBack}>Retour aux documents</button></div>
          )}
        </section>
      </div>

      <div className="review-disclaimer"><ShieldCheck size={15} /><span>Vérifiez les montants et le compte comptable avant de créer une écriture. La comptabilisation est définitive.</span></div>
    </>
  );
}

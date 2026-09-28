import { ArrowUpRight, FileText } from 'lucide-react';
import type { DocumentSummary } from '../types';
import { formatCurrency, formatDate } from '../utils/format';
import StatusBadge from './StatusBadge';

type Props = {
  documents: DocumentSummary[];
  onOpenDocument: (id: number) => void;
  emptyTitle?: string;
  emptyDescription?: string;
};

const avatarColors = ['avatar--mint', 'avatar--lavender', 'avatar--peach', 'avatar--blue'];

function getInitials(name: string | null, filename: string): string {
  const source = name || filename.replace(/\.[^.]+$/, '').replace(/[-_]/g, ' ');
  return source
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0])
    .join('')
    .toUpperCase();
}

export default function DocumentTable({
  documents,
  onOpenDocument,
  emptyTitle = 'Aucun document pour le moment',
  emptyDescription = 'Importez une facture pour commencer votre suivi comptable.',
}: Props) {
  if (documents.length === 0) {
    return (
      <div className="empty-state">
        <div className="empty-state__icon"><FileText size={21} /></div>
        <strong>{emptyTitle}</strong>
        <p>{emptyDescription}</p>
      </div>
    );
  }

  return (
    <div className="table-scroll">
      <table className="document-table">
        <thead>
          <tr>
            <th>Fournisseur</th>
            <th>Date de facture</th>
            <th>Montant TTC</th>
            <th>Statut</th>
            <th><span className="sr-only">Ouvrir</span></th>
          </tr>
        </thead>
        <tbody>
          {documents.map((document, index) => (
            <tr
              key={document.id}
              tabIndex={0}
              aria-label={`Ouvrir la facture ${document.supplier_name || document.original_filename}`}
              onClick={() => onOpenDocument(document.id)}
              onKeyDown={(event) => {
                if (event.target === event.currentTarget && (event.key === 'Enter' || event.key === ' ')) {
                  event.preventDefault();
                  onOpenDocument(document.id);
                }
              }}
            >
              <td>
                <div className="document-vendor">
                  <div className={`vendor-avatar ${avatarColors[index % avatarColors.length]}`}>
                    {getInitials(document.supplier_name, document.original_filename)}
                  </div>
                  <div className="vendor-copy">
                    <strong>{document.supplier_name || 'Fournisseur à renseigner'}</strong>
                    <span>{document.invoice_number || document.original_filename}</span>
                  </div>
                </div>
              </td>
              <td className="table-date">{formatDate(document.invoice_date)}</td>
              <td className="table-amount">{formatCurrency(document.total_amount, document.currency)}</td>
              <td><StatusBadge status={document.status} /></td>
              <td className="table-action"><button type="button" aria-label={`Ouvrir ${document.supplier_name || document.original_filename}`}><ArrowUpRight size={16} /></button></td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

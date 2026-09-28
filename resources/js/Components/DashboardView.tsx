import { ArrowRight, ArrowUpRight, CircleHelp, FilePlus2, Files, ReceiptText, WalletCards } from 'lucide-react';
import { useState } from 'react';
import type { DashboardStats, DocumentSummary, MonthlySpend } from '../types';
import { formatCurrency } from '../utils/format';
import DocumentTable from './DocumentTable';
import StatusBadge from './StatusBadge';
import UploadDocumentDialog from './UploadDocumentDialog';

type Props = {
  stats: DashboardStats;
  recentDocuments: DocumentSummary[];
  monthlySpend: MonthlySpend[];
  onOpenDocument: (id: number) => void;
  onViewDocuments: () => void;
  onUpload: (file: File) => void;
  isUploading?: boolean;
  uploadError?: string | null;
  userName?: string;
};

const cardDetails = [
  { key: 'to_review', label: 'À vérifier', icon: ReceiptText, tone: 'amber', footnote: 'Documents en attente' },
  { key: 'posted_this_month', label: 'Écritures comptabilisées', icon: WalletCards, tone: 'green', footnote: 'Ce mois-ci' },
  { key: 'expenses_this_month', label: 'Dépenses TTC', icon: Files, tone: 'blue', footnote: 'Comptabilisées ce mois-ci' },
  { key: 'documents_this_month', label: 'Documents reçus', icon: FilePlus2, tone: 'violet', footnote: 'Importés ce mois-ci' },
] as const;

export default function DashboardView({
  stats,
  recentDocuments,
  monthlySpend,
  onOpenDocument,
  onViewDocuments,
  onUpload,
  isUploading = false,
  uploadError,
  userName,
}: Props) {
  const [uploadOpen, setUploadOpen] = useState(false);
  const maxSpend = Math.max(...monthlySpend.map((month) => month.amount), 1);
  const axisMax = Math.max(1000, Math.ceil(maxSpend / 1000) * 1000);
  const documentsToReview = recentDocuments.filter((document) => document.status === 'needs_review').slice(0, 4);
  const displayDate = new Intl.DateTimeFormat('fr-FR', {
    weekday: 'long',
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  }).format(new Date()).toLocaleUpperCase('fr-FR');
  const firstName = userName?.trim().split(/\s+/)[0];

  const cardValue = (key: (typeof cardDetails)[number]['key']) => {
    if (key === 'expenses_this_month') return formatCurrency(stats[key]);
    return new Intl.NumberFormat('fr-FR').format(stats[key]);
  };

  const axisLabel = (step: number) => {
    const amount = axisMax * (1 - step / 4);
    if (amount === 0) return '0 €';
    return `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(amount / 1000)} k€`;
  };

  return (
    <>
      <section className="page-heading dashboard-heading">
        <div>
          <div className="eyebrow">{displayDate} <span className="eyebrow-dot" /> ESPACE COMPTABLE</div>
          <h1>Bonjour{firstName ? `, ${firstName}` : ''} <span className="heading-wave">✳</span></h1>
          <p>Voici le résumé de votre activité comptable.</p>
        </div>
        <button className="button button--primary" onClick={() => setUploadOpen(true)}>
          <FilePlus2 size={17} /> Importer un document
        </button>
      </section>

      <section className="stats-grid" aria-label="Indicateurs clés">
        {cardDetails.map(({ key, label, icon: Icon, tone, footnote }) => (
          <article className="stat-card" key={key}>
            <div className={`stat-icon stat-icon--${tone}`}><Icon size={18} strokeWidth={1.9} /></div>
            <div className="stat-label">{label}</div>
            <div className="stat-value">{cardValue(key)}</div>
            <div className="stat-footnote"><span className={`stat-footnote__dot stat-footnote__dot--${tone}`} />{footnote}</div>
          </article>
        ))}
      </section>

      <section className="dashboard-overview-grid">
        <article className="panel spend-panel">
          <div className="panel-heading">
            <div>
              <div className="panel-eyebrow">SUIVI DES DÉPENSES</div>
              <h2>Évolution mensuelle</h2>
            </div>
            <div className="period-select" aria-label="Période affichée">6 derniers mois <span>⌄</span></div>
          </div>
          <div className="chart-summary">
            <div className="chart-total">{formatCurrency(monthlySpend.reduce((sum, month) => sum + month.amount, 0))}</div>
            <span className="chart-caption">Total comptabilisé sur la période</span>
          </div>
          <div className="bar-chart" role="img" aria-label="Dépenses comptabilisées par mois">
            <div className="chart-y-labels">{[0, 1, 2, 3, 4].map((step) => <span key={step}>{axisLabel(step)}</span>)}</div>
            <div className="chart-plot">
              <div className="chart-gridlines"><i /><i /><i /><i /><i /></div>
              <div className="chart-bars">
                {monthlySpend.map((month, index) => (
                  <div className="chart-bar-column" key={`${month.label}-${index}`}>
                    <div className={`chart-bar ${index === monthlySpend.length - 1 ? 'chart-bar--current' : ''}`} style={{ height: `${month.amount > 0 ? Math.max(5, (month.amount / axisMax) * 100) : 0}%` }}>
                      <span className="chart-tooltip">{formatCurrency(month.amount)}</span>
                    </div>
                    <span className="chart-month">{month.label}</span>
                  </div>
                ))}
              </div>
            </div>
          </div>
          <div className="chart-legend"><span className="chart-legend__swatch" /> Montant TTC comptabilisé</div>
        </article>

        <article className="panel review-panel">
          <div className="panel-heading panel-heading--compact">
            <div>
              <div className="panel-eyebrow">À NE PAS OUBLIER</div>
              <h2>À vérifier <span className="heading-count">{stats.to_review}</span></h2>
            </div>
            <button className="icon-button subtle-icon-button" onClick={onViewDocuments} aria-label="Voir tous les documents"><ArrowUpRight size={17} /></button>
          </div>
          {documentsToReview.length > 0 ? (
            <div className="review-list">
              {documentsToReview.map((document) => (
                <button className="review-item" key={document.id} onClick={() => onOpenDocument(document.id)}>
                  <div className="review-item__mark">{(document.supplier_name || 'D').slice(0, 1).toUpperCase()}</div>
                  <div className="review-item__copy">
                    <strong>{document.supplier_name || 'Fournisseur à renseigner'}</strong>
                    <span>{document.invoice_number || 'Référence à compléter'}</span>
                  </div>
                  <div className="review-item__right">
                    <strong>{formatCurrency(document.total_amount, document.currency)}</strong>
                    <StatusBadge status={document.status} />
                  </div>
                </button>
              ))}
            </div>
          ) : (
            <div className="review-empty"><span className="review-empty__check">✓</span><strong>Tout est à jour</strong><span>Aucune facture en attente de vérification.</span></div>
          )}
          <button className="text-action" onClick={onViewDocuments}>Voir tous les documents <ArrowRight size={15} /></button>
        </article>
      </section>

      <section className="panel recent-panel">
        <div className="panel-heading recent-heading">
          <div>
            <div className="panel-eyebrow">VOTRE FLUX</div>
            <h2>Documents récents</h2>
          </div>
          <button className="button button--secondary button--small" onClick={onViewDocuments}>Tous les documents <ArrowRight size={15} /></button>
        </div>
        <DocumentTable documents={recentDocuments} onOpenDocument={onOpenDocument} />
      </section>

      <div className="dashboard-footnote"><CircleHelp size={14} /><span>Les écritures comptabilisées sont créées à partir des informations vérifiées dans chaque document.</span></div>

      <UploadDocumentDialog
        open={uploadOpen}
        onClose={() => setUploadOpen(false)}
        onSubmit={onUpload}
        isSubmitting={isUploading}
        error={uploadError}
      />
    </>
  );
}

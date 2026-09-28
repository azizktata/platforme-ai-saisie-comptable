import { ArrowRight, FilePlus2, Filter, Search, SlidersHorizontal } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import type { DocumentSummary } from '../types';
import DocumentTable from './DocumentTable';
import UploadDocumentDialog from './UploadDocumentDialog';

type Pagination = {
  current_page: number;
  last_page: number;
  total: number;
  from: number | null;
  to: number | null;
  prev_page_url: string | null;
  next_page_url: string | null;
};

type Props = {
  documents: DocumentSummary[];
  filters: { search: string; status: string };
  pagination?: Pagination;
  onFilter: (search: string, status: string) => void;
  onOpenDocument: (id: number) => void;
  onPageChange?: (url: string) => void;
  onUpload: (file: File) => void;
  isUploading?: boolean;
  uploadError?: string | null;
  successMessage?: string | null;
};

export default function DocumentsView({
  documents,
  filters,
  pagination,
  onFilter,
  onOpenDocument,
  onPageChange,
  onUpload,
  isUploading = false,
  uploadError,
  successMessage,
}: Props) {
  const [uploadOpen, setUploadOpen] = useState(false);
  const [search, setSearch] = useState(filters.search);
  const [status, setStatus] = useState(filters.status);

  useEffect(() => {
    setSearch(filters.search);
    setStatus(filters.status);
  }, [filters.search, filters.status]);

  useEffect(() => {
    if (successMessage) setUploadOpen(false);
  }, [successMessage]);

  const submitFilters = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    onFilter(search.trim(), status);
  };

  return (
    <>
      <section className="page-heading documents-heading">
        <div>
          <div className="eyebrow">VOTRE ESPACE COMPTABLE</div>
          <h1>Documents</h1>
          <p>Retrouvez vos factures, vérifiez les informations et suivez leur comptabilisation.</p>
        </div>
        <button className="button button--primary" onClick={() => setUploadOpen(true)}>
          <FilePlus2 size={17} /> Importer un document
        </button>
      </section>

      <section className="panel documents-panel">
        <div className="documents-toolbar">
          <div className="documents-count"><strong>{pagination?.total ?? documents.length}</strong> documents <span className="count-divider">·</span> <span>Vue d’ensemble</span></div>
          <form className="filter-form" onSubmit={submitFilters}>
            <label className="search-field">
              <Search size={16} />
              <span className="sr-only">Rechercher un fournisseur ou une référence</span>
              <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Rechercher…" />
            </label>
            <label className="select-field">
              <SlidersHorizontal size={15} />
              <span className="sr-only">Filtrer par statut</span>
              <select value={status} onChange={(event) => setStatus(event.target.value)}>
                <option value="all">Tous les statuts</option>
                <option value="needs_review">À vérifier</option>
                <option value="posted">Comptabilisés</option>
              </select>
            </label>
            <button type="submit" className="filter-submit" aria-label="Appliquer les filtres"><Filter size={15} /><span>Filtrer</span></button>
          </form>
        </div>

        <DocumentTable
          documents={documents}
          onOpenDocument={onOpenDocument}
          emptyTitle="Aucun document trouvé"
          emptyDescription="Modifiez vos filtres ou importez une nouvelle facture."
        />

        {pagination && pagination.last_page > 1 && (
          <div className="pagination-row">
            <span>{pagination.from ?? 0}–{pagination.to ?? 0} sur {pagination.total} documents</span>
            <div className="pagination-actions">
              <button className="button button--secondary button--small" disabled={!pagination.prev_page_url} onClick={() => pagination.prev_page_url && onPageChange?.(pagination.prev_page_url)}>Précédent</button>
              <span className="pagination-page">{pagination.current_page} <span>/</span> {pagination.last_page}</span>
              <button className="button button--secondary button--small" disabled={!pagination.next_page_url} onClick={() => pagination.next_page_url && onPageChange?.(pagination.next_page_url)}>Suivant <ArrowRight size={14} /></button>
            </div>
          </div>
        )}
      </section>

      <div className="documents-tip"><span className="tip-icon">i</span><span>Un document importé reste à vérifier jusqu’à la création de son écriture comptable.</span></div>

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

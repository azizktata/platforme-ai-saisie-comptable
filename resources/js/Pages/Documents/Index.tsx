import { router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Components/AppShell';
import DocumentsView from '../../Components/DocumentsView';
import type { DocumentSummary, FlashProps, SharedAuthProps } from '../../types';

type PaginatedDocuments = {
  data: DocumentSummary[];
  current_page: number;
  last_page: number;
  total: number;
  from: number | null;
  to: number | null;
  prev_page_url: string | null;
  next_page_url: string | null;
};

type Props = {
  documents: PaginatedDocuments;
  filters: { search: string; status: string };
  auth?: SharedAuthProps;
  flash?: FlashProps;
};

export default function Index({ documents, filters, auth, flash }: Props) {
  const [isUploading, setIsUploading] = useState(false);
  const [uploadError, setUploadError] = useState<string | null>(null);

  const uploadDocument = (file: File) => {
    setIsUploading(true);
    setUploadError(null);
    router.post('/documents', { file }, {
      forceFormData: true,
      onError: (errors) => setUploadError(errors.file || 'Le document n’a pas pu être importé.'),
      onFinish: () => setIsUploading(false),
    });
  };

  return (
    <AppShell
      activeSection="documents"
      successMessage={flash?.success}
      user={auth?.user}
      onLogout={auth?.user ? () => router.post('/logout') : undefined}
    >
      <DocumentsView
        documents={documents.data}
        filters={filters}
        pagination={documents}
        onFilter={(search, status) => router.get('/documents', { search, status }, { preserveState: true, replace: true })}
        onOpenDocument={(id) => router.visit(`/documents/${id}`)}
        onPageChange={(url) => router.get(url)}
        onUpload={uploadDocument}
        isUploading={isUploading}
        uploadError={uploadError}
        successMessage={flash?.success}
      />
    </AppShell>
  );
}

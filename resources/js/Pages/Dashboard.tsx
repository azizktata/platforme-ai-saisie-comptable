import { router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../Components/AppShell';
import DashboardView from '../Components/DashboardView';
import type { DashboardStats, DocumentSummary, FlashProps, MonthlySpend, SharedAuthProps } from '../types';

type Props = {
  stats: DashboardStats;
  recentDocuments: DocumentSummary[];
  monthlySpend: MonthlySpend[];
  auth?: SharedAuthProps;
  flash?: FlashProps;
};

export default function Dashboard({ stats, recentDocuments, monthlySpend, auth, flash }: Props) {
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
      activeSection="overview"
      successMessage={flash?.success}
      pendingReviewCount={stats.to_review}
      user={auth?.user}
      onLogout={auth?.user ? () => router.post('/logout') : undefined}
    >
      <DashboardView
        stats={stats}
        recentDocuments={recentDocuments}
        monthlySpend={monthlySpend}
        userName={auth?.user?.name}
        onOpenDocument={(id) => router.visit(`/documents/${id}`)}
        onViewDocuments={() => router.visit('/documents')}
        onUpload={uploadDocument}
        isUploading={isUploading}
        uploadError={uploadError}
      />
    </AppShell>
  );
}

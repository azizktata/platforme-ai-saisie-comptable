export type DocumentStatus = 'needs_review' | 'posted';

export type DocumentSummary = {
  id: number;
  original_filename: string;
  supplier_name: string | null;
  invoice_number: string | null;
  invoice_date: string | null;
  total_amount: string | number | null;
  currency: string;
  status: DocumentStatus;
  created_at?: string | null;
};

export type DocumentDetail = DocumentSummary & {
  file_url: string | null;
  mime_type: string | null;
  size_bytes: number;
  due_date: string | null;
  account_code: string | null;
  description: string | null;
  subtotal: string | number | null;
  vat_amount: string | number | null;
};

export type DocumentFields = {
  supplier_name: string;
  invoice_number: string;
  invoice_date: string;
  due_date: string;
  currency: string;
  account_code: string;
  description: string;
  subtotal: string;
  vat_amount: string;
  total_amount: string;
};

export type DashboardStats = {
  to_review: number;
  posted_this_month: number;
  expenses_this_month: number;
  documents_this_month: number;
};

export type MonthlySpend = {
  label: string;
  amount: number;
};

export type AuthUser = {
  id: number;
  name: string;
  email: string;
};

export type SharedAuthProps = {
  user: AuthUser | null;
};

export type FlashProps = {
  success?: string | null;
};

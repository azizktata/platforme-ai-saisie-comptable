export type AuthUser = {
  id?: number;
  name: string;
  email: string;
};

export type CabinetSummary = {
  id?: number;
  name: string;
  slug?: string;
};

export type SharedAuthProps = {
  user?: AuthUser | null;
  cabinet?: CabinetSummary | null;
  canManageCabinet?: boolean;
};

export type CompanySummary = {
  id: number;
  name: string;
  legal_name: string | null;
  tax_identifier: string | null;
  activity: string | null;
  sector: string | null;
  country_code?: string | null;
  currency: string | null;
  vat_rates?: string[];
  fiscal_year_start?: string | null;
  fiscal_year_end?: string | null;
  capitalization_threshold?: string | null;
  users_count: number;
  access_role: string | null;
};

export type CompanyAccess = {
  id: number;
  name: string;
  role: string;
};

export type CabinetUserSummary = {
  id: number;
  name: string;
  email: string;
  cabinet_role: string;
  companies: CompanyAccess[];
};

export type SelectableCompany = {
  id: number;
  name: string;
};

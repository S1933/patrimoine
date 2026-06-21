export type User = {
  id: string;
  name: string;
  email: string;
  base_currency: string;
  email_verified_at: string | null;
  created_at: string | null;
};

export type AssetTypeCode =
  | "stock"
  | "etf"
  | "etn_crypto"
  | "crypto"
  | "gold"
  | "real_estate"
  | "cash"
  | "livret_a"
  | "ldds"
  | "other";

export type AssetType = {
  id: number;
  code: AssetTypeCode;
  label: string;
  default_provider: string | null;
  default_unit: string | null;
  is_priced_externally: boolean;
};

export type PriceProvider = {
  id: string;
  code: string;
  label: string;
  base_url: string | null;
  rate_limit_per_min: number;
  is_active: boolean;
  priority: number;
};

export type Currency = {
  code: string;
  label: string;
  symbol: string;
};

export type InvestmentStatus = "active" | "sold" | "archived";

export type Investment = {
  id: string;
  asset_type?: AssetType;
  asset_type_id: number;
  name: string;
  isin: string | null;
  symbol: string | null;
  quantity: number;
  unit: string;
  geography: string | null;
  country_allocations: Array<{country: string; percent: number}> | null;
  sector_allocations: Array<{sector: string; percent: number}> | null;
  purchase_price: number | null;
  purchase_currency: string | null;
  purchase_date: string | null;
  manual_value: number | null;
  manual_value_updated_at: string | null;
  currency: string;
  provider_id: string | null;
  notes: string | null;
  status: InvestmentStatus;
  created_at: string | null;
  updated_at: string | null;
  current_price: number | null;
  current_price_fetched_at: string | null;
  current_price_source: string | null;
  current_price_provider: string | null;
  current_value: number | null;
  purchase_value: number | null;
  pnl_absolute: number | null;
  pnl_percent: number | null;
  market_data?: {
    source: string | null;
    isin: string | null;
    ticker: string | null;
    name: string | null;
    exchange: string | null;
    volume: number | null;
    day_change: number | null;
    day_change_percent: number | null;
    previous_close: number | null;
    high_52w: number | null;
    low_52w: number | null;
    performance: Record<string, number> | null;
  } | null;
};

export type Paginated<T> = {
  data: T[];
  links: {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
  };
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
};

export type DashboardSummary = {
  total_value: number;
  total_cost: number;
  pnl_absolute: number;
  pnl_percent: number | null;
  active_count: number;
  last_updated_at: string | null;
  currency: string;
};

export type AllocationItem = {
  code: string;
  label: string;
  value: number;
  percent: number;
  count: number;
  target_percent: number | null;
  deviation_points: number | null;
};

export type StrategyAllocation = {
  asset_type_id: number;
  code: AssetTypeCode;
  label: string;
  target_percent: number;
};

export type InvestmentStrategy = {
  allocations: StrategyAllocation[];
  total_percent: number;
};

export type InvestmentStrategyInput = {
  allocations: Array<{
    asset_type_id: number;
    target_percent: number;
  }>;
};

export type BreakdownItem = {
  id: string;
  name: string;
  asset_type_code: string;
  asset_type_label: string;
  current_value: number;
  purchase_value: number | null;
  pnl_absolute: number | null;
  pnl_percent: number | null;
  weight: number;
  status: InvestmentStatus;
};

export type SectorAllocationItem = {
  sector: string;
  value: number;
  percent: number;
  count: number;
};

export type CountryAllocationItem = {
  country_code: string;
  value: number;
  percent: number;
  count: number;
};

export type GeographyItem = {
  geography: string;
  value: number;
  percent: number;
  count: number;
};

export type PerformancePoint = {
  date: string;
  total_value: number;
  total_cost: number;
};

export type ApiError = {
  message: string;
  errors?: Record<string, string[]>;
};

export type InvestmentInput = {
  asset_type_id: number;
  name: string;
  isin?: string | null;
  symbol?: string | null;
  quantity: number;
  unit: string;
  geography?: string | null;
  country_allocations?: Array<{country: string; percent: number}> | null;
  sector_allocations?: Array<{sector: string; percent: number}> | null;
  purchase_price?: number | null;
  purchase_currency?: string | null;
  purchase_date?: string | null;
  manual_value?: number | null;
  currency: string;
  provider_id?: string | null;
  notes?: string | null;
  status?: InvestmentStatus;
};

export type ChatMessage = {
  role: "user" | "assistant" | "system";
  content: string;
};

export type ChatModel = {
  id: string;
  label: string;
  group: string;
};

export type ChatSettingsData = {
  models: ChatModel[];
  has_key: boolean;
  model: string;
  provider: "zen" | "go";
};

export type ChatSettingsResponse = {
  data: ChatSettingsData;
};

export type ChatApiKeyResponse = {
  data: { has_key: boolean; model: string | null; provider?: "zen" | "go" | null };
};

export type RefreshPriceMeta = {
  pricing_status: string;
  pricing_source: string;
  pricing_error: string | null;
  pricing_fetched_at: string;
};

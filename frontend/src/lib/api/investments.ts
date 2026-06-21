import { api } from "@/lib/api/client";
import type {
  AssetType,
  Currency,
  Investment,
  InvestmentInput,
  Paginated,
  PriceProvider,
  RefreshPriceMeta,
} from "@/lib/types";

type ListParams = {
  status?: string;
  type?: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
  page?: number;
  per_page?: number;
};

type InvestmentResponse = { data: Investment };
type ListResponse = Paginated<Investment>;
type RefResponse = { data: AssetType[] | PriceProvider[] | Currency[] };
type RefreshResponse = Investment & { meta?: RefreshPriceMeta };

export const investmentsApi = {
  list: (params: ListParams = {}) =>
    api.get<ListResponse>("/investments?" + new URLSearchParams(
      Object.entries(params).filter(([, v]) => v != null && v !== "") as [string, string][]
    ).toString()),
  get: (id: string) => api.get<InvestmentResponse>(`/investments/${id}`),
  create: (data: InvestmentInput) => api.post<InvestmentResponse>("/investments", data),
  update: (id: string, data: Partial<InvestmentInput>) =>
    api.put<InvestmentResponse>(`/investments/${id}`, data),
  remove: (id: string) => api.delete<void>(`/investments/${id}`),
  setManualValue: (id: string, value: number, currency?: string) =>
    api.post<InvestmentResponse>(`/investments/${id}/manual-value`, { value, currency }),
  refreshPrice: (id: string) => api.post<RefreshResponse>(`/investments/${id}/refresh-price`),
};

export const referenceApi = {
  assetTypes: () => api.get<RefResponse>("/asset-types").then((r) => r.data as AssetType[]),
  providers: () => api.get<RefResponse>("/price-providers").then((r) => r.data as PriceProvider[]),
  currencies: () => api.get<RefResponse>("/currencies").then((r) => r.data as Currency[]),
};

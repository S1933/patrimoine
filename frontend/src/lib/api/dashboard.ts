import { api } from "@/lib/api/client";
import type {
  AllocationItem,
  BreakdownItem,
  CountryAllocationItem,
  DashboardSummary,
  GeographyItem,
  PerformancePoint,
  SectorAllocationItem,
} from "@/lib/types";

type SummaryResponse = { data: DashboardSummary };
type AllocationResponse = { data: AllocationItem[] };
type SectorAllocationResponse = { data: SectorAllocationItem[] };
type CountryAllocationResponse = { data: CountryAllocationItem[] };
type GeographyResponse = { data: GeographyItem[] };
type BreakdownResponse = { data: BreakdownItem[] };
type PerformanceResponse = { data: PerformancePoint[] };

export const dashboardApi = {
  summary: () => api.get<SummaryResponse>("/dashboard/summary").then((r) => r.data),
  allocation: () => api.get<AllocationResponse>("/dashboard/allocation").then((r) => r.data),
  geography: () => api.get<GeographyResponse>("/dashboard/geography").then((r) => r.data),
  countryAllocation: () => api.get<CountryAllocationResponse>("/dashboard/country-allocation").then((r) => r.data),
  sectorAllocation: () => api.get<SectorAllocationResponse>("/dashboard/sector-allocation").then((r) => r.data),
  breakdown: () => api.get<BreakdownResponse>("/dashboard/breakdown").then((r) => r.data),
  performance: (range: string = "all") =>
    api.get<PerformanceResponse>(`/dashboard/performance?range=${range}`).then((r) => r.data),
};

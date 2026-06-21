import { api } from "@/lib/api/client";
import type { InvestmentStrategy, InvestmentStrategyInput } from "@/lib/types";

type InvestmentStrategyResponse = { data: InvestmentStrategy };

export const investmentStrategyApi = {
  get: () =>
    api.get<InvestmentStrategyResponse>("/investment-strategy").then((response) => response.data),
  update: (input: InvestmentStrategyInput) =>
    api.put<InvestmentStrategyResponse>("/investment-strategy", input).then((response) => response.data),
};

"use client";

import { useRouter } from "next/navigation";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useSearchParams } from "next/navigation";
import { Suspense, useState, type FormEvent } from "react";
import { investmentsApi, referenceApi } from "@/lib/api/investments";
import { extractError } from "@/lib/api/client";
import type { AssetTypeCode, InvestmentInput } from "@/lib/types";

const GICS_SECTORS = [
  "Information Technology", "Health Care", "Financials", "Energy",
  "Industrials", "Consumer Discretionary", "Consumer Staples",
  "Materials", "Real Estate", "Communication Services", "Utilities",
];

const marketTypeCodes: AssetTypeCode[] = ["stock", "etf", "etn_crypto"];

const symbolHelp: Partial<Record<AssetTypeCode, { placeholder: string; hint: string }>> = {
  stock: {
    placeholder: "AAPL ou AIR.PAR",
    hint: "Ticker ou code marché. L’ISIN est aussi accepté pour la résolution automatique.",
  },
  etf: {
    placeholder: "SPY ou CW8.PAR",
    hint: "Ticker ou code marché. L’ISIN est aussi accepté pour la résolution automatique.",
  },
  etn_crypto: {
    placeholder: "Ticker.ÉCHANGE",
    hint: "Utilisez le ticker coté, ou renseignez l’ISIN si vous ne l’avez pas.",
  },
  crypto: {
    placeholder: "bitcoin",
    hint: "Identifiant CoinGecko, par exemple bitcoin ou ethereum.",
  },
  gold: {
    placeholder: "XAU",
    hint: "Code métal GoldAPI, généralement XAU.",
  },
};

function InvestmentForm() {
  const router = useRouter();
  const params = useSearchParams();
  const editId = params.get("edit");
  const queryClient = useQueryClient();

  const { data: assetTypes } = useQuery({
    queryKey: ["asset-types"],
    queryFn: referenceApi.assetTypes,
  });
  const { data: currencies } = useQuery({
    queryKey: ["currencies"],
    queryFn: referenceApi.currencies,
  });

  const { data: existing } = useQuery({
    queryKey: ["investment", editId],
    queryFn: () => investmentsApi.get(editId!).then((r) => r.data),
    enabled: !!editId,
  });

  const [form, setForm] = useState<InvestmentInput>(() => ({
    asset_type_id: 0,
    name: "",
    isin: "",
    symbol: "",
    quantity: 0,
    unit: "unit",
    purchase_price: null,
    purchase_currency: "EUR",
    purchase_date: "",
    manual_value: null,
    currency: "EUR",
    provider_id: null,
    notes: "",
    status: "active",
    sector_allocations: null,
  }));

  if (existing && form.name === "" && existing.name) {
    setForm({
      asset_type_id: existing.asset_type_id,
      name: existing.name,
      isin: existing.isin ?? "",
      symbol: existing.symbol ?? "",
      quantity: existing.quantity,
      unit: existing.unit,
      purchase_price: existing.purchase_price,
      purchase_currency: existing.purchase_currency ?? "EUR",
      purchase_date: existing.purchase_date ?? "",
      manual_value: existing.manual_value,
      currency: existing.currency,
      provider_id: existing.provider_id,
      notes: existing.notes ?? "",
      status: existing.status,
      sector_allocations: existing.sector_allocations ?? null,
    });
  }

  const saveMutation = useMutation({
    mutationFn: (data: InvestmentInput) =>
      editId ? investmentsApi.update(editId, data) : investmentsApi.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["investments"] });
      if (editId) {
        queryClient.invalidateQueries({ queryKey: ["investment", editId] });
      }
      router.push("/investments");
    },
  });

  function set<K extends keyof InvestmentInput>(key: K, value: InvestmentInput[K]) {
    setForm((f) => ({ ...f, [key]: value }));
  }

  function onSubmit(e: FormEvent) {
    e.preventDefault();
    const payload: InvestmentInput = {
      ...form,
      asset_type_id: Number(form.asset_type_id),
      quantity: Number(form.quantity),
      purchase_price: form.purchase_price ? Number(form.purchase_price) : null,
      manual_value: form.manual_value ? Number(form.manual_value) : null,
      purchase_date: form.purchase_date || null,
      isin: form.isin || null,
      symbol: form.symbol || null,
      notes: form.notes || null,
    };
    saveMutation.mutate(payload);
  }

  const selectedType = assetTypes?.find((t) => t.id === Number(form.asset_type_id));
  const selectedSymbolHelp = selectedType ? symbolHelp[selectedType.code] : undefined;
  const isMarketType = selectedType ? marketTypeCodes.includes(selectedType.code) : false;

  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">
          {editId ? "Modifier l'investissement" : "Nouvel investissement"}
        </h1>
      </div>

      {saveMutation.isError && (
        <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">
          {extractError(saveMutation.error)}
        </p>
      )}

      <form onSubmit={onSubmit} className="space-y-4">
        <Field label="Type d'actif" required>
          <select
            required
            value={form.asset_type_id || ""}
            onChange={(e) => {
              const id = Number(e.target.value);
              const t = assetTypes?.find((a) => a.id === id);
              set("asset_type_id", id);
              if (t?.default_unit) set("unit", t.default_unit);
            }}
            className={inputClass}
          >
            <option value="" disabled>Choisir...</option>
            {assetTypes?.map((t) => (
              <option key={t.id} value={t.id}>{t.label}</option>
            ))}
          </select>
        </Field>

        <Field label="Nom" required>
          <input required value={form.name} onChange={(e) => set("name", e.target.value)} className={inputClass} />
        </Field>

        <div className="grid grid-cols-2 gap-4">
          <Field
            label="ISIN"
            hint={selectedType?.is_priced_externally ? "Recommandé pour une valorisation automatique plus fiable." : "Optionnel."}
          >
            <input
              value={form.isin ?? ""}
              onChange={(e) => set("isin", e.target.value.toUpperCase())}
              className={inputClass}
              placeholder="FR001400RWK6"
              maxLength={12}
            />
          </Field>
          <Field
            label="Symbole / ticker"
            hint={selectedSymbolHelp?.hint ?? (selectedType?.is_priced_externally ? "Facultatif si l’ISIN est renseigné." : undefined)}
          >
            <input
              value={form.symbol ?? ""}
              onChange={(e) => set("symbol", e.target.value)}
              className={inputClass}
              placeholder={selectedSymbolHelp?.placeholder ?? "Symbole ou identifiant"}
            />
          </Field>
          <Field label="Unité" required>
            <input required value={form.unit} onChange={(e) => set("unit", e.target.value)} className={inputClass} />
          </Field>
        </div>

        {isMarketType && (
          <div className="rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-800 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200">
            Valorisation automatique : ISIN → Finnhub → Twelve Data → dernière valeur connue.
          </div>
        )}

        <div className="grid grid-cols-2 gap-4">
          <Field label="Quantité" required>
            <input type="number" step="any" min="0" required value={form.quantity || ""} onChange={(e) => set("quantity", Number(e.target.value))} className={inputClass} />
          </Field>
          <Field label="Devise" required>
            <select required value={form.currency} onChange={(e) => set("currency", e.target.value)} className={inputClass}>
              {currencies?.map((c) => (
                <option key={c.code} value={c.code}>{c.code} — {c.label}</option>
              ))}
            </select>
          </Field>
        </div>

        <div className="grid grid-cols-2 gap-4">
          <Field label="Prix d'achat unitaire" hint="Facultatif">
            <input type="number" step="any" min="0" value={form.purchase_price ?? ""} onChange={(e) => set("purchase_price", e.target.value ? Number(e.target.value) : null)} className={inputClass} />
          </Field>
          <Field label="Date d'achat" hint="Facultatif">
            <input type="date" value={form.purchase_date ?? ""} onChange={(e) => set("purchase_date", e.target.value)} className={inputClass} />
          </Field>
        </div>

        <Field label="Valeur manuelle" hint="Pour immobilier, cash, Livret A, LDDS ou tout actif non valorisable automatiquement">
          <input type="number" step="any" min="0" value={form.manual_value ?? ""} onChange={(e) => set("manual_value", e.target.value ? Number(e.target.value) : null)} className={inputClass} placeholder="Laisser vide si prix automatique" />
        </Field>

        <Field label="Statut">
          <select value={form.status} onChange={(e) => set("status", e.target.value as InvestmentInput["status"])} className={inputClass}>
            <option value="active">Actif</option>
            <option value="sold">Vendu</option>
            <option value="archived">Archivé</option>
          </select>
        </Field>

        <div className="space-y-2">
          <div className="flex items-center justify-between">
            <label className="text-sm font-medium">
              Allocations sectorielles <span className="text-xs text-neutral-400">(GICS)</span>
            </label>
            <button
              type="button"
              onClick={() => {
                const current = form.sector_allocations ?? [];
                set("sector_allocations", [...current, { sector: "", percent: 0 }]);
              }}
              className="text-xs text-blue-600 hover:underline dark:text-blue-400"
            >
              + Ajouter un secteur
            </button>
          </div>
          {form.sector_allocations && form.sector_allocations.length > 0 && (
            <div className="space-y-2">
              {form.sector_allocations.map((alloc, idx) => (
                <div key={idx} className="flex items-center gap-2">
                  <select
                    value={alloc.sector}
                    onChange={(e) => {
                      const list = [...(form.sector_allocations ?? [])];
                      list[idx] = { ...list[idx], sector: e.target.value };
                      set("sector_allocations", list);
                    }}
                    className={inputClass}
                  >
                    <option value="">Sélectionner...</option>
                    {GICS_SECTORS.map((s) => (
                      <option key={s} value={s}>{s}</option>
                    ))}
                  </select>
                  <input
                    type="number"
                    min="0"
                    max="100"
                    step="0.1"
                    placeholder="%"
                    value={alloc.percent || ""}
                    onChange={(e) => {
                      const list = [...(form.sector_allocations ?? [])];
                      list[idx] = { ...list[idx], percent: Number(e.target.value) };
                      set("sector_allocations", list);
                    }}
                    className={`${inputClass} w-20`}
                  />
                  <button
                    type="button"
                    onClick={() => {
                      const list = form.sector_allocations?.filter((_, i) => i !== idx) ?? null;
                      set("sector_allocations", list?.length ? list : null);
                    }}
                    className="text-red-500 hover:text-red-700"
                  >
                    ✕
                  </button>
                </div>
              ))}
            </div>
          )}
          <p className="text-xs text-neutral-400">
            Répartition sectorielle GICS. Les pourcentages doivent totaliser ~100%.
          </p>
        </div>

        <Field label="Notes personnelles" hint="Chiffré au repos">
          <textarea value={form.notes ?? ""} onChange={(e) => set("notes", e.target.value)} rows={3} className={inputClass} />
        </Field>

        <div className="flex justify-end gap-3 pt-4">
          <button type="button" onClick={() => router.back()} className="rounded-md border border-neutral-300 px-4 py-2 text-sm hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800">
            Annuler
          </button>
          <button type="submit" disabled={saveMutation.isPending} className="rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-700 disabled:opacity-60 dark:bg-white dark:text-neutral-900">
            {saveMutation.isPending ? "Enregistrement..." : editId ? "Mettre à jour" : "Créer"}
          </button>
        </div>
      </form>
    </div>
  );
}

const inputClass =
  "w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm outline-none focus:border-neutral-900 focus:ring-1 focus:ring-neutral-900 dark:border-neutral-700 dark:bg-neutral-900";

function Field({
  label,
  required,
  hint,
  children,
}: {
  label: string;
  required?: boolean;
  hint?: string;
  children: React.ReactNode;
}) {
  return (
    <div className="space-y-1.5">
      <label className="text-sm font-medium">
        {label} {required && <span className="text-red-500">*</span>}
      </label>
      {children}
      {hint && <p className="text-xs text-neutral-400">{hint}</p>}
    </div>
  );
}

export default function NewInvestmentPage() {
  return (
    <Suspense fallback={<p className="text-sm text-neutral-500">Chargement...</p>}>
      <InvestmentForm />
    </Suspense>
  );
}

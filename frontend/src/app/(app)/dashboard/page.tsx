"use client";

import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import Link from "next/link";
import { ComposableMap, Geographies, Geography } from "react-simple-maps";
import { scaleQuantize } from "d3-scale";
import { schemeOrRd } from "d3-scale-chromatic";
import { dashboardApi } from "@/lib/api/dashboard";
import { formatCurrency, formatDate, formatPercent } from "@/lib/utils";

const COLORS = ["#0ea5e9", "#8b5cf6", "#10b981", "#f59e0b", "#ef4444", "#6366f1", "#ec4899", "#14b8a6"];

const COUNTRY_TO_NUMERIC: Record<string, string> = {
  FRA: "250", DEU: "276", GBR: "826", ITA: "380", ESP: "724",
  NLD: "528", BEL: "056", CHE: "756", SWE: "752", NOR: "578",
  DNK: "208", FIN: "246", AUT: "040", POL: "616", PRT: "620",
  IRL: "372", GRC: "300", CZE: "203", HUN: "348", ROU: "642",
  USA: "840", CAN: "124", MEX: "484", BRA: "076", ARG: "032",
  JPN: "392", CHN: "156", IND: "356", KOR: "410", TWN: "158",
  HKG: "344", SGP: "702", AUS: "036", NZL: "554", ZAF: "710",
  RUS: "643", TUR: "792", SAU: "682", IDN: "360", THA: "764",
  MYS: "458", PHL: "608", VNM: "704", EGY: "818", NGA: "566",
  KEN: "404", MAR: "504", TUN: "788", DZA: "012", ISR: "376",
};

const NUMERIC_TO_ALPHA3: Record<string, string> = {};
for (const [a3, num] of Object.entries(COUNTRY_TO_NUMERIC)) {
  NUMERIC_TO_ALPHA3[num] = a3;
}

const WORLD_ATLAS = "https://cdn.jsdelivr.net/npm/world-atlas@2/countries-110m.json";

export default function DashboardPage() {
  const { data: summary } = useQuery({ queryKey: ["dashboard", "summary"], queryFn: dashboardApi.summary });
  const { data: allocation } = useQuery({ queryKey: ["dashboard", "allocation"], queryFn: dashboardApi.allocation });
  const { data: countryAlloc } = useQuery({ queryKey: ["dashboard", "country-allocation"], queryFn: dashboardApi.countryAllocation });
  const { data: sectorAlloc } = useQuery({ queryKey: ["dashboard", "sector-allocation"], queryFn: dashboardApi.sectorAllocation });
  const { data: breakdown } = useQuery({ queryKey: ["dashboard", "breakdown"], queryFn: dashboardApi.breakdown });

  const pnlPositive = (summary?.pnl_absolute ?? 0) >= 0;

  const [tooltipContent, setTooltipContent] = useState("");

  const countryValues = new Map<string, number>();
  countryAlloc?.forEach((c) => countryValues.set(c.country_code, c.value));
  const maxValue = Math.max(...(countryAlloc?.map((c) => c.value) ?? [0]), 1);
  const colorScale = scaleQuantize<string>().domain([0, maxValue]).range(schemeOrRd[5]);

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Dashboard</h1>
          <p className="text-sm text-neutral-500">
            {summary ? `${summary.active_count} actif(s) · dernière MAJ ${formatDate(summary.last_updated_at)}` : "Chargement..."}
          </p>
        </div>
        <Link
          href="/investments/new"
          className="rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-700 dark:bg-white dark:text-neutral-900"
        >
          + Investissement
        </Link>
      </div>

      {/* Summary cards */}
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Card
          label="Valeur totale"
          value={summary ? formatCurrency(summary.total_value, summary.currency) : "—"}
        />
        <Card
          label="Coût d'achat"
          value={summary ? formatCurrency(summary.total_cost, summary.currency) : "—"}
        />
        <Card
          label="Plus/moins-value"
          value={summary ? formatCurrency(summary.pnl_absolute, summary.currency) : "—"}
          accent={pnlPositive ? "green" : "red"}
        />
        <Card
          label="Performance"
          value={summary?.pnl_percent != null ? formatPercent(summary.pnl_percent) : "—"}
          accent={pnlPositive ? "green" : "red"}
        />
      </div>

      <div className="grid gap-6 lg:grid-cols-2">
        <div className="rounded-lg border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-900">
          <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-neutral-500">
            Répartition par classe d&apos;actifs
          </h2>
          {allocation && allocation.length > 0 ? (
            <div className="space-y-5 py-2">
              {allocation.map((item, i) => (
                <div key={item.code}>
                  <div className="mb-2 flex items-end justify-between gap-4">
                    <div>
                      <p className="font-medium text-neutral-900 dark:text-neutral-100">{item.label}</p>
                      <p className="text-xs text-neutral-500">
                        {formatCurrency(item.value, summary?.currency)} · {item.count} actif{item.count > 1 ? "s" : ""}
                      </p>
                      {item.target_percent != null && item.deviation_points != null && (
                        <p className="mt-1 text-xs text-neutral-500">
                          Cible {item.target_percent.toFixed(1)} % · écart{" "}
                          <span className={item.deviation_points > 0 ? "text-amber-600" : item.deviation_points < 0 ? "text-sky-600" : "text-green-600"}>
                            {item.deviation_points > 0 ? "+" : ""}{item.deviation_points.toFixed(1)} pts
                          </span>
                        </p>
                      )}
                    </div>
                    <div className="text-right">
                      <span className="text-2xl font-semibold tabular-nums tracking-tight">
                        {item.percent.toFixed(1)}%
                      </span>
                      {item.target_percent != null && (
                        <p className="text-[11px] uppercase tracking-wide text-neutral-400">Réel</p>
                      )}
                    </div>
                  </div>
                  <div className="h-2.5 overflow-hidden rounded-full bg-neutral-100 dark:bg-neutral-800">
                    <div
                      className="h-full rounded-full transition-[width] duration-700"
                      style={{
                        width: `${Math.min(Math.max(item.percent, 0), 100)}%`,
                        backgroundColor: COLORS[i % COLORS.length],
                      }}
                    />
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <EmptyState text="Aucune donnée d'allocation." />
          )}
        </div>

        <div className="rounded-lg border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-900">
          <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-neutral-500">
            Répartition par secteur d&apos;activité
          </h2>
          {sectorAlloc && sectorAlloc.length > 0 ? (
            <div className="space-y-5 py-2">
              {sectorAlloc.map((item, i) => (
                <div key={item.sector}>
                  <div className="mb-2 flex items-end justify-between gap-4">
                    <div>
                      <p className="font-medium text-neutral-900 dark:text-neutral-100">{item.sector}</p>
                      <p className="text-xs text-neutral-500">
                        {formatCurrency(item.value, summary?.currency)} · {item.count} investissement{item.count > 1 ? "s" : ""}
                      </p>
                    </div>
                    <span className="text-2xl font-semibold tabular-nums tracking-tight">
                      {item.percent.toFixed(1)}%
                    </span>
                  </div>
                  <div className="h-2.5 overflow-hidden rounded-full bg-neutral-100 dark:bg-neutral-800">
                    <div
                      className="h-full rounded-full transition-[width] duration-700"
                      style={{
                        width: `${Math.min(Math.max(item.percent, 0), 100)}%`,
                        backgroundColor: COLORS[i % COLORS.length],
                      }}
                    />
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <EmptyState text="Aucune donnée secteur. Renseignez les allocations sectorielles dans chaque investissement." />
          )}
        </div>

      </div>

      {/* World map */}
      <div className="grid gap-6 lg:grid-cols-1">
        <div className="rounded-lg border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-900">
          <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-neutral-500">
            Couverture géographique
          </h2>
          {countryAlloc && countryAlloc.length > 0 ? (
            <div className="relative">
              <ComposableMap
                projection="geoMercator"
                projectionConfig={{ scale: 130, center: [10, 30] }}
                style={{ width: "100%", height: "auto" }}
              >
                <Geographies geography={WORLD_ATLAS}>
                  {({ geographies }) =>
                    geographies.map((geo) => {
                      const numId = geo.id;
                      const alpha3 = NUMERIC_TO_ALPHA3[numId];
                      const val = alpha3 ? countryValues.get(alpha3) : undefined;
                      return (
                        <Geography
                          key={geo.rsmKey}
                          geography={geo}
                          fill={val !== undefined ? colorScale(val) : "#e5e5e5"}
                          stroke="#fff"
                          strokeWidth={0.5}
                          style={{
                            default: { outline: "none" },
                            hover: { fill: val !== undefined ? colorScale(val) : "#d4d4d4", outline: "none" },
                          }}
                          onMouseEnter={() => {
                            if (alpha3 && val !== undefined) {
                              const entry = countryAlloc.find((c) => c.country_code === alpha3);
                              setTooltipContent(
                                `${geo.properties.name}: ${formatCurrency(val)} (${entry?.percent.toFixed(1)}%)`
                              );
                            } else {
                              setTooltipContent(geo.properties.name);
                            }
                          }}
                          onMouseLeave={() => setTooltipContent("")}
                        />
                      );
                    })
                  }
                </Geographies>
              </ComposableMap>
              {tooltipContent && (
                <div className="pointer-events-none absolute left-1/2 top-2 -translate-x-1/2 rounded-md bg-neutral-900 px-3 py-1.5 text-sm text-white shadow-md dark:bg-neutral-700">
                  {tooltipContent}
                </div>
              )}
              <div className="mt-3 flex items-center justify-center gap-2 text-xs text-neutral-500">
                <span>Faible</span>
                <div className="flex h-3 w-48 rounded-full overflow-hidden">
                  {schemeOrRd[5].map((c, i) => (
                    <div key={i} className="flex-1" style={{ backgroundColor: c }} />
                  ))}
                </div>
                <span>Élevé</span>
              </div>
            </div>
          ) : (
            <EmptyState text="Aucune donnée pays. Modifiez vos investissements pour renseigner les allocations par pays." />
          )}
        </div>
      </div>

      {/* Breakdown table */}
      <div className="rounded-lg border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-900">
        <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-neutral-500">
          Détail par investissement
        </h2>
        {breakdown && breakdown.length > 0 ? (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="text-left text-xs uppercase tracking-wide text-neutral-500">
                <tr>
                  <th className="pb-2">Nom</th>
                  <th className="pb-2">Type</th>
                  <th className="pb-2 text-right">Valeur</th>
                  <th className="pb-2 text-right">Poids</th>
                  <th className="pb-2 text-right">P/L</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                {breakdown.map((b) => (
                  <tr key={b.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-900/50">
                    <td className="py-2">
                      <Link href={`/investments/${b.id}`} className="font-medium hover:underline">
                        {b.name}
                      </Link>
                    </td>
                    <td className="py-2 text-neutral-500">{b.asset_type_label}</td>
                    <td className="py-2 text-right tabular-nums font-medium">
                      {formatCurrency(b.current_value)}
                    </td>
                    <td className="py-2 text-right tabular-nums text-neutral-500">
                      {b.weight.toFixed(1)}%
                    </td>
                    <td className={`py-2 text-right tabular-nums ${b.pnl_percent != null ? (b.pnl_percent >= 0 ? "text-green-600" : "text-red-600") : ""}`}>
                      {b.pnl_percent != null ? formatPercent(b.pnl_percent) : "—"}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <EmptyState text="Aucun investissement actif." />
        )}
      </div>
    </div>
  );
}

function Card({
  label,
  value,
  accent,
}: {
  label: string;
  value: string;
  accent?: "green" | "red";
}) {
  const colorClass =
    accent === "green" ? "text-green-600 dark:text-green-400"
    : accent === "red" ? "text-red-600 dark:text-red-400"
    : "";
  return (
    <div className="rounded-lg border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
      <p className="text-xs font-medium uppercase tracking-wide text-neutral-500">{label}</p>
      <p className={`mt-2 text-2xl font-semibold tabular-nums ${colorClass}`}>{value}</p>
    </div>
  );
}

function EmptyState({ text }: { text: string }) {
  return (
    <div className="flex h-48 items-center justify-center">
      <p className="text-sm text-neutral-400">{text}</p>
    </div>
  );
}

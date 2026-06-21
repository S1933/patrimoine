"use client";

import Link from "next/link";
import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { investmentsApi } from "@/lib/api/investments";
import { extractError } from "@/lib/api/client";
import { formatCurrency, formatPercent } from "@/lib/utils";
import type { Investment, InvestmentStatus } from "@/lib/types";

const STATUS_LABELS: Record<InvestmentStatus, string> = {
  active: "Actif",
  sold: "Vendu",
  archived: "Archivé",
};

const STATUS_COLORS: Record<InvestmentStatus, string> = {
  active: "bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200",
  sold: "bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300",
  archived: "bg-neutral-100 text-neutral-400 dark:bg-neutral-800 dark:text-neutral-500",
};

export default function InvestmentsPage() {
  const queryClient = useQueryClient();
  const [status, setStatus] = useState<string>("");
  const [search, setSearch] = useState("");

  const { data, isLoading, error } = useQuery({
    queryKey: ["investments", { status, search }],
    queryFn: () => investmentsApi.list({ status: status || undefined, search: search || undefined }),
  });

  const deleteMutation = useMutation({
    mutationFn: investmentsApi.remove,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["investments"] }),
  });

  async function onDelete(inv: Investment) {
    if (!confirm(`Supprimer "${inv.name}" ?`)) return;
    try {
      await deleteMutation.mutateAsync(inv.id);
    } catch (err) {
      alert(extractError(err));
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Investissements</h1>
          <p className="text-sm text-neutral-500">{data?.meta.total ?? 0} investissement(s) au total</p>
        </div>
        <Link
          href="/investments/new"
          className="rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-700 dark:bg-white dark:text-neutral-900"
        >
          + Nouvel investissement
        </Link>
      </div>

      <div className="flex flex-wrap gap-3">
        <input
          type="search"
          placeholder="Rechercher par nom, ISIN ou symbole..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="flex-1 rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900"
        />
        <select
          value={status}
          onChange={(e) => setStatus(e.target.value)}
          className="rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900"
        >
          <option value="">Tous les statuts</option>
          <option value="active">Actif</option>
          <option value="sold">Vendu</option>
          <option value="archived">Archivé</option>
        </select>
      </div>

      {error && (
        <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">
          {extractError(error)}
        </p>
      )}

      <div className="overflow-x-auto rounded-lg border border-neutral-200 dark:border-neutral-800">
        <table className="w-full text-sm">
          <thead className="bg-neutral-50 text-left text-xs uppercase tracking-wide text-neutral-500 dark:bg-neutral-900">
            <tr>
              <th className="px-4 py-3">Nom</th>
              <th className="px-4 py-3">Type</th>
              <th className="px-4 py-3 text-right">Quantité</th>
              <th className="px-4 py-3 text-right">Valeur actuelle</th>
              <th className="px-4 py-3 text-right">P/L</th>
              <th className="px-4 py-3">Statut</th>
              <th className="sticky right-0 bg-neutral-50 px-4 py-3 text-right dark:bg-neutral-900">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
            {isLoading ? (
              <tr>
                <td colSpan={7} className="px-4 py-8 text-center text-neutral-500">
                  Chargement...
                </td>
              </tr>
            ) : data?.data.length === 0 ? (
              <tr>
                <td colSpan={7} className="px-4 py-8 text-center text-neutral-500">
                  Aucun investissement. Cliquez sur &quot;+ Nouvel investissement&quot; pour commencer.
                </td>
              </tr>
            ) : (
              data?.data.map((inv) => (
                <tr key={inv.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-900/50">
                  <td className="px-4 py-3">
                    <div className="flex flex-col">
                      <Link href={`/investments/${inv.id}`} className="font-medium hover:underline">
                        {inv.name}
                      </Link>
                      <div className="mt-1 flex flex-wrap gap-2 text-xs text-neutral-400">
                        {inv.isin && <span>{inv.isin}</span>}
                        {inv.symbol && <span>{inv.symbol}</span>}
                      </div>
                    </div>
                  </td>
                  <td className="px-4 py-3 text-neutral-600 dark:text-neutral-400">
                    {inv.asset_type?.label ?? "—"}
                  </td>
                  <td className="px-4 py-3 text-right tabular-nums">
                    {inv.quantity} {inv.unit}
                  </td>
                  <td className="px-4 py-3 text-right tabular-nums font-medium">
                    {inv.current_value != null ? formatCurrency(inv.current_value, inv.currency) : "—"}
                  </td>
                  <td className="px-4 py-3 text-right tabular-nums">
                    {inv.pnl_percent != null ? (
                      <span className={inv.pnl_percent >= 0 ? "text-green-600" : "text-red-600"}>
                        {formatPercent(inv.pnl_percent)}
                      </span>
                    ) : "—"}
                  </td>
                  <td className="px-4 py-3">
                    <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_COLORS[inv.status]}`}>
                      {STATUS_LABELS[inv.status]}
                    </span>
                  </td>
                  <td className="sticky right-0 bg-white px-4 py-3 text-right dark:bg-neutral-900">
                    <div className="flex justify-end gap-2">
                      <Link
                        href={`/investments/new?edit=${inv.id}`}
                        className="rounded-md border border-neutral-300 px-2.5 py-1 text-xs font-medium text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                      >
                        Modifier
                      </Link>
                      <button
                        onClick={() => onDelete(inv)}
                        className="rounded-md border border-red-200 px-2.5 py-1 text-xs font-medium text-red-600 hover:bg-red-50 disabled:opacity-50 dark:border-red-900 dark:hover:bg-red-950"
                        disabled={deleteMutation.isPending}
                      >
                        Supprimer
                      </button>
                    </div>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}

"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { investmentsApi } from "@/lib/api/investments";
import { extractError } from "@/lib/api/client";
import { formatCurrency, formatDate, formatPercent } from "@/lib/utils";

export default function InvestmentDetailPage() {
  const params = useParams<{ id: string }>();
  const queryClient = useQueryClient();
  const id = params.id;

  const { data: inv, isLoading, error } = useQuery({
    queryKey: ["investment", id],
    queryFn: () => investmentsApi.get(id).then((r) => r.data),
  });

  const refreshMutation = useMutation({
    mutationFn: () => investmentsApi.refreshPrice(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["investment", id] });
      queryClient.invalidateQueries({ queryKey: ["investments"] });
    },
  });

  if (isLoading) return <p className="text-sm text-neutral-500">Chargement...</p>;
  if (error) return <p className="text-sm text-red-600">{extractError(error)}</p>;
  if (!inv) return <p className="text-sm text-neutral-500">Introuvable.</p>;

  return (
    <div className="space-y-6">
      <div className="flex items-start justify-between">
        <div>
          <Link href="/investments" className="text-sm text-neutral-500 hover:underline">
            ← Retour à la liste
          </Link>
          <h1 className="mt-2 text-2xl font-semibold tracking-tight">{inv.name}</h1>
          <div className="mt-1 flex flex-wrap gap-3 text-sm text-neutral-500">
            {inv.isin && <p>ISIN : {inv.isin}</p>}
            {inv.symbol && <p>Symbole : {inv.symbol}</p>}
          </div>
        </div>
        <div className="flex gap-2">
          <Link
            href={`/investments/new?edit=${inv.id}`}
            className="rounded-md border border-neutral-300 px-3 py-1.5 text-sm hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800"
          >
            Modifier
          </Link>
          <button
            onClick={() => refreshMutation.mutate()}
            disabled={refreshMutation.isPending}
            className="rounded-md bg-neutral-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-neutral-700 disabled:opacity-60 dark:bg-white dark:text-neutral-900"
          >
            {refreshMutation.isPending ? "Rafraîchissement..." : "Rafraîchir le prix"}
          </button>
        </div>
      </div>

      {refreshMutation.isError && (
        <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">
          {extractError(refreshMutation.error)}
        </p>
      )}
      {refreshMutation.data?.meta && (
        <p className="rounded-md bg-blue-50 px-3 py-2 text-sm text-blue-800 dark:bg-blue-950 dark:text-blue-200">
          Prix mis à jour via <strong>{refreshMutation.data.meta.pricing_source}</strong> — statut : {refreshMutation.data.meta.pricing_status}
          {refreshMutation.data.meta.pricing_error && ` (${refreshMutation.data.meta.pricing_error})`}
        </p>
      )}

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Card label="Valeur actuelle" value={inv.current_value != null ? formatCurrency(inv.current_value, inv.currency) : "—"} />
        <Card label="Coût d'achat" value={inv.purchase_value != null ? formatCurrency(inv.purchase_value, inv.currency) : "—"} />
        <Card
          label="Plus/moins-value"
          value={inv.pnl_absolute != null ? formatCurrency(inv.pnl_absolute, inv.currency) : "—"}
          accent={inv.pnl_absolute != null ? (inv.pnl_absolute >= 0 ? "green" : "red") : undefined}
        />
        <Card
          label="Performance"
          value={inv.pnl_percent != null ? formatPercent(inv.pnl_percent) : "—"}
          accent={inv.pnl_percent != null ? (inv.pnl_percent >= 0 ? "green" : "red") : undefined}
        />
      </div>

      <div className="grid gap-6 lg:grid-cols-2">
        <Section title="Détails">
          <dl className="space-y-2 text-sm">
            <Row label="Type d'actif" value={inv.asset_type?.label ?? "—"} />
            <Row label="ISIN" value={inv.isin ?? "—"} />
            <Row label="Quantité" value={`${inv.quantity} ${inv.unit}`} />
            <Row label="Prix d'achat unitaire" value={inv.purchase_price != null ? formatCurrency(inv.purchase_price, inv.purchase_currency ?? inv.currency) : "—"} />
            <Row label="Date d'achat" value={inv.purchase_date ? formatDate(inv.purchase_date) : "—"} />
            <Row label="Valeur manuelle" value={inv.manual_value != null ? formatCurrency(inv.manual_value, inv.currency) : "—"} />
            <Row label="Statut" value={inv.status} />
          </dl>
        </Section>

        <Section title="Valorisation">
          <dl className="space-y-2 text-sm">
            <Row label="Prix actuel" value={inv.current_price != null ? formatCurrency(inv.current_price, inv.currency) : "—"} />
            <Row label="Source" value={inv.current_price_provider ?? "—"} />
            <Row label="Statut source" value={inv.current_price_source ?? "—"} />
            <Row label="Dernière récupération" value={formatDate(inv.current_price_fetched_at)} />
            <Row label="Valeur manuelle mise à jour le" value={formatDate(inv.manual_value_updated_at)} />
          </dl>
        </Section>
      </div>

      {inv.market_data && (
        <Section title="Marché">
          <dl className="grid gap-2 text-sm sm:grid-cols-2">
            <Row label="Source marché" value={inv.market_data.source ?? "—"} />
            <Row label="Ticker résolu" value={inv.market_data.ticker ?? "—"} />
            <Row label="Volume" value={inv.market_data.volume != null ? new Intl.NumberFormat("fr-FR").format(inv.market_data.volume) : "—"} />
            <Row label="Variation jour" value={inv.market_data.day_change_percent != null ? formatPercent(inv.market_data.day_change_percent) : "—"} />
            <Row label="Perf. 1W" value={inv.market_data.performance?.["1w"] != null ? formatPercent(inv.market_data.performance["1w"]) : "—"} />
            <Row label="Perf. 1M" value={inv.market_data.performance?.["1m"] != null ? formatPercent(inv.market_data.performance["1m"]) : "—"} />
            <Row label="Perf. 3M" value={inv.market_data.performance?.["3m"] != null ? formatPercent(inv.market_data.performance["3m"]) : "—"} />
            <Row label="Perf. 1Y" value={inv.market_data.performance?.["1y"] != null ? formatPercent(inv.market_data.performance["1y"]) : "—"} />
          </dl>
        </Section>
      )}

      {inv.notes && (
        <Section title="Notes">
          <p className="whitespace-pre-wrap text-sm text-neutral-600 dark:text-neutral-400">{inv.notes}</p>
        </Section>
      )}
    </div>
  );
}

function Card({ label, value, accent }: { label: string; value: string; accent?: "green" | "red" }) {
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

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="rounded-lg border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-900">
      <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-neutral-500">{title}</h2>
      {children}
    </div>
  );
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex justify-between">
      <dt className="text-neutral-500">{label}</dt>
      <dd className="font-medium tabular-nums">{value}</dd>
    </div>
  );
}

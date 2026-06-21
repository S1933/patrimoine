"use client";

import { useEffect, useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { referenceApi } from "@/lib/api/investments";
import { useAuth } from "@/lib/auth-context";
import { chatApi } from "@/lib/api/chat";
import { investmentStrategyApi } from "@/lib/api/investment-strategy";
import { extractError } from "@/lib/api/client";

const providerUsage: Record<string, string> = {
  twelve_data: "Actions, ETF & ETN — principal",
  finnhub: "Actions, ETF & ETN — fallback",
  coingecko: "Cryptomonnaies",
  goldapi: "Métaux précieux",
  "exchangerate-host": "Taux de change",
  manual: "Saisie manuelle",
};

export default function SettingsPage() {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const [targets, setTargets] = useState<Record<number, number>>({});
  const [saveError, setSaveError] = useState("");
  const [saved, setSaved] = useState(false);
  const { data: providers } = useQuery({
    queryKey: ["price-providers"],
    queryFn: referenceApi.providers,
  });
  const { data: chatSettings } = useQuery({
    queryKey: ["chat-settings"],
    queryFn: () => chatApi.models().then((r) => r.data),
  });
  const { data: strategy, isLoading: strategyLoading } = useQuery({
    queryKey: ["investment-strategy"],
    queryFn: investmentStrategyApi.get,
  });
  const updateStrategy = useMutation({
    mutationFn: investmentStrategyApi.update,
    onSuccess: async (data) => {
      setSaveError("");
      setSaved(true);
      setTargets(Object.fromEntries(data.allocations.map((allocation) => [
        allocation.asset_type_id,
        allocation.target_percent,
      ])));
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ["investment-strategy"] }),
        queryClient.invalidateQueries({ queryKey: ["dashboard", "allocation"] }),
      ]);
    },
    onError: (error) => {
      setSaved(false);
      setSaveError(extractError(error));
    },
  });

  useEffect(() => {
    if (!strategy) return;
    setTargets(Object.fromEntries(strategy.allocations.map((allocation) => [
      allocation.asset_type_id,
      allocation.target_percent,
    ])));
  }, [strategy]);

  const totalTarget = useMemo(
    () => Math.round(Object.values(targets).reduce((sum, value) => sum + (Number(value) || 0), 0) * 100) / 100,
    [targets],
  );
  const totalIsValid = totalTarget === 100;

  function saveStrategy() {
    if (!strategy || !totalIsValid) return;
    setSaved(false);
    setSaveError("");
    updateStrategy.mutate({
      allocations: strategy.allocations.map((allocation) => ({
        asset_type_id: allocation.asset_type_id,
        target_percent: Number(targets[allocation.asset_type_id] ?? 0),
      })),
    });
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Paramètres</h1>
        <p className="text-sm text-neutral-500">Profil, stratégie d&apos;investissement et providers de prix.</p>
      </div>

      <div className="rounded-lg border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-900">
        <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2 className="text-sm font-semibold uppercase tracking-wide text-neutral-500">
              Stratégie d&apos;investissement
            </h2>
            <p className="mt-1 text-xs text-neutral-400">
              Définissez l&apos;allocation cible de votre patrimoine par type d&apos;actif.
            </p>
          </div>
          <div className={`rounded-md px-3 py-1.5 text-sm font-semibold tabular-nums ${
            totalIsValid
              ? "bg-green-50 text-green-700 dark:bg-green-950/40 dark:text-green-400"
              : "bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400"
          }`}>
            Total {totalTarget.toFixed(2)} %
          </div>
        </div>

        {strategyLoading ? (
          <p className="text-sm text-neutral-400">Chargement de la stratégie…</p>
        ) : strategy ? (
          <>
            <div className="grid gap-x-6 gap-y-3 sm:grid-cols-2">
              {strategy.allocations.map((allocation) => (
                <label
                  key={allocation.asset_type_id}
                  className="flex items-center justify-between gap-4 rounded-md border border-neutral-200 px-3 py-2 dark:border-neutral-800"
                >
                  <span className="text-sm font-medium">{allocation.label}</span>
                  <span className="flex items-center gap-2">
                    <input
                      type="number"
                      min="0"
                      max="100"
                      step="0.01"
                      value={targets[allocation.asset_type_id] ?? 0}
                      onChange={(event) => {
                        setSaved(false);
                        setTargets((current) => ({
                          ...current,
                          [allocation.asset_type_id]: event.target.value === "" ? 0 : Number(event.target.value),
                        }));
                      }}
                      className="w-24 rounded-md border border-neutral-300 bg-white px-2 py-1.5 text-right text-sm tabular-nums outline-none focus:border-neutral-500 dark:border-neutral-700 dark:bg-neutral-950"
                    />
                    <span className="text-sm text-neutral-500">%</span>
                  </span>
                </label>
              ))}
            </div>

            {!totalIsValid && (
              <p className="mt-3 text-sm text-amber-700 dark:text-amber-400">
                Le total doit être exactement égal à 100 %.
              </p>
            )}
            {saveError && <p className="mt-3 text-sm text-red-600">{saveError}</p>}
            {saved && <p className="mt-3 text-sm text-green-600">Stratégie enregistrée.</p>}

            <div className="mt-4 flex justify-end">
              <button
                type="button"
                onClick={saveStrategy}
                disabled={!totalIsValid || updateStrategy.isPending}
                className="rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-700 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white dark:text-neutral-900"
              >
                {updateStrategy.isPending ? "Enregistrement…" : "Enregistrer la stratégie"}
              </button>
            </div>
          </>
        ) : (
          <p className="text-sm text-red-600">Impossible de charger la stratégie.</p>
        )}
      </div>

      <div className="rounded-lg border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-900">
        <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-neutral-500">Assistant IA</h2>
        <dl className="space-y-2 text-sm">
          <div className="flex justify-between">
            <dt className="text-neutral-500">Clé API OpenCode</dt>
            <dd className={chatSettings?.has_key ? "font-medium text-green-600" : "text-neutral-400"}>
              {chatSettings?.has_key ? "Configurée ✓" : "Non configurée"}
            </dd>
          </div>
          <div className="flex justify-between">
            <dt className="text-neutral-500">Modèle actif</dt>
            <dd className="font-medium">{chatSettings?.model ?? "—"}</dd>
          </div>
          <div className="flex justify-between">
            <dt className="text-neutral-500">Provider</dt>
            <dd className="font-medium uppercase">{chatSettings?.provider ?? "—"}</dd>
          </div>
        </dl>
        <p className="mt-3 text-xs text-neutral-400">
          La clé est chiffrée et stockée sur le serveur. Configure-la dans la barre latérale de l&apos;assistant.
        </p>
      </div>

      <div className="rounded-lg border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-900">
        <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-neutral-500">Profil</h2>
        <dl className="space-y-2 text-sm">
          <div className="flex justify-between">
            <dt className="text-neutral-500">Nom</dt>
            <dd className="font-medium">{user?.name ?? "—"}</dd>
          </div>
          <div className="flex justify-between">
            <dt className="text-neutral-500">Email</dt>
            <dd className="font-medium">{user?.email ?? "—"}</dd>
          </div>
          <div className="flex justify-between">
            <dt className="text-neutral-500">Devise de référence</dt>
            <dd className="font-medium">{user?.base_currency ?? "EUR"}</dd>
          </div>
        </dl>
      </div>

      <div className="rounded-lg border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-900">
        <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-neutral-500">
          Providers de prix actifs
        </h2>
        {providers && providers.length > 0 ? (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="text-left text-xs uppercase tracking-wide text-neutral-500">
                <tr>
                  <th className="pb-2">Provider</th>
                  <th className="pb-2">Usage</th>
                  <th className="pb-2">Code</th>
                  <th className="pb-2 text-right">Limite (req/min)</th>
                  <th className="pb-2 text-right">Priorité</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                {providers.map((p) => (
                  <tr key={p.id}>
                    <td className="py-2 font-medium">{p.label}</td>
                    <td className="py-2 text-neutral-500">{providerUsage[p.code] ?? "Autre"}</td>
                    <td className="py-2 text-neutral-500">{p.code}</td>
                    <td className="py-2 text-right tabular-nums">{p.rate_limit_per_min}</td>
                    <td className="py-2 text-right tabular-nums">{p.priority}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <p className="text-sm text-neutral-400">Aucun provider actif.</p>
        )}
        <p className="mt-4 text-xs text-neutral-400">
          Les clés API sont configurées via les variables d&apos;environnement (.env) et ne sont jamais
          exposées à l&apos;interface. Voir <code className="rounded bg-neutral-100 px-1 dark:bg-neutral-800">PROVIDER_*</code> dans <code className="rounded bg-neutral-100 px-1 dark:bg-neutral-800">.env.example</code>.
        </p>
      </div>
    </div>
  );
}

"use client";

import { useState } from "react";
import { X, Key, ExternalLink, Trash2 } from "lucide-react";

export function ChatApiKeyModal({
  open,
  hasKey,
  provider,
  onProviderChange,
  onSave,
  onClear,
  onClose,
}: {
  open: boolean;
  hasKey: boolean;
  provider: "zen" | "go";
  onProviderChange: (provider: "zen" | "go") => void;
  onSave: (key: string, provider: "zen" | "go") => Promise<unknown>;
  onClear: () => Promise<unknown>;
  onClose: () => void;
}) {
  const [value, setValue] = useState("");
  const [saving, setSaving] = useState(false);

  if (!open) return null;

  async function handleSave() {
    if (!value.trim() && !hasKey) return;
    setSaving(true);
    try {
      await onSave(value.trim(), provider);
      setValue("");
      onClose();
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/30">
      <div className="mx-4 w-full max-w-sm rounded-2xl border border-neutral-200 bg-white p-5 shadow-lg dark:border-neutral-700 dark:bg-neutral-900">
        <div className="mb-4 flex items-center justify-between">
          <h3 className="flex items-center gap-2 text-sm font-semibold">
            <Key className="h-4 w-4" />
            Clé API OpenCode
          </h3>
          <button onClick={onClose} className="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300">
            <X className="h-4 w-4" />
          </button>
        </div>

        <p className="mb-3 text-xs text-neutral-500 dark:text-neutral-400">
          Ta clé est chiffrée et stockée sur le serveur. Elle n&apos;est jamais exposée côté client.
        </p>

        {hasKey && (
          <div className="mb-3 rounded-lg bg-green-50 px-3 py-2 text-xs text-green-700 dark:bg-green-950 dark:text-green-300">
            Clé configurée ✓
          </div>
        )}

        <input
          type="password"
          value={value}
          onChange={(e) => setValue(e.target.value)}
          placeholder={hasKey ? "Nouvelle clé (laisse vide pour garder l'actuelle)" : "sk-..."}
          className="mb-3 w-full rounded-xl border border-neutral-300 bg-white px-3 py-2 text-sm outline-none transition focus:border-neutral-500 focus:ring-1 focus:ring-neutral-500 dark:border-neutral-600 dark:bg-neutral-800 dark:focus:border-neutral-400"
        />

        <label className="mb-3 block text-xs text-neutral-500 dark:text-neutral-400">
          Provider OpenCode
          <select
            value={provider}
            onChange={(e) => onProviderChange(e.target.value as "zen" | "go")}
            className="mt-1 w-full rounded-xl border border-neutral-300 bg-white px-3 py-2 text-sm outline-none transition focus:border-neutral-500 focus:ring-1 focus:ring-neutral-500 dark:border-neutral-600 dark:bg-neutral-800 dark:focus:border-neutral-400"
          >
            <option value="zen">Zen</option>
            <option value="go">Go</option>
          </select>
        </label>

        <div className="flex items-center gap-2">
          <button
            onClick={handleSave}
            disabled={saving || (!hasKey && !value.trim())}
            className="flex-1 rounded-xl bg-neutral-900 px-3 py-2 text-sm text-white transition hover:bg-neutral-700 disabled:opacity-40 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-300"
          >
            {saving ? "Enregistrement..." : "Enregistrer"}
          </button>
          {hasKey && (
            <button
              onClick={onClear}
              className="flex items-center gap-1 rounded-xl border border-red-300 px-3 py-2 text-sm text-red-600 transition hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950"
            >
              <Trash2 className="h-3.5 w-3.5" />
              Effacer
            </button>
          )}
        </div>

        <a
          href="https://opencode.ai/auth"
          target="_blank"
          rel="noopener noreferrer"
          className="mt-3 flex items-center justify-center gap-1 text-xs text-neutral-500 underline underline-offset-2 hover:text-neutral-700 dark:hover:text-neutral-300"
        >
          Obtenir une clé OpenCode <ExternalLink className="h-3 w-3" />
        </a>
      </div>
    </div>
  );
}

"use client";

import type { ChatModel } from "@/lib/types";

export function ChatModelSelector({
  models,
  current,
  onChange,
  disabled,
}: {
  models: ChatModel[];
  current: string | null;
  onChange: (id: string) => void;
  disabled: boolean;
}) {
  const grouped = models.reduce<
    Record<string, { id: string; label: string }[]>
  >((acc, m) => {
    const group = m.group || "autres";
    if (!acc[group]) acc[group] = [];
    acc[group].push({ id: m.id, label: m.label });
    return acc;
  }, {});

  const order = ["recommandés", "gratuits", "autres"];

  return (
    <select
      value={current ?? ""}
      onChange={(e) => onChange(e.target.value)}
      disabled={disabled}
      className="max-w-[160px] truncate rounded-lg border border-neutral-300 bg-white px-2 py-1 text-xs outline-none transition focus:border-neutral-500 dark:border-neutral-600 dark:bg-neutral-800 dark:focus:border-neutral-400"
    >
      {order.map((group) => {
        const items = grouped[group];
        if (!items || items.length === 0) return null;
        return (
          <optgroup key={group} label={group === "recommandés" ? "Recommandés" : group === "gratuits" ? "Gratuits" : "Autres"}>
            {items.map((m) => (
              <option key={m.id} value={m.id}>
                {m.label}
              </option>
            ))}
          </optgroup>
        );
      })}
    </select>
  );
}

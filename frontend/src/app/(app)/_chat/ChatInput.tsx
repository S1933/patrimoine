"use client";

import { useState, useRef, useEffect } from "react";
import { Send, Square } from "lucide-react";

export function ChatInput({
  onSend,
  onStop,
  streaming,
  disabled,
}: {
  onSend: (text: string) => void;
  onStop: () => void;
  streaming: boolean;
  disabled: boolean;
}) {
  const [value, setValue] = useState("");
  const textareaRef = useRef<HTMLTextAreaElement>(null);

  useEffect(() => {
    if (!streaming) {
      textareaRef.current?.focus();
    }
  }, [streaming]);

  function handleSubmit() {
    const text = value.trim();
    if (!text || streaming) return;
    onSend(text);
    setValue("");
  }

  function handleKeyDown(e: React.KeyboardEvent) {
    if (e.key === "Enter" && !e.shiftKey) {
      e.preventDefault();
      handleSubmit();
    }
  }

  return (
    <div className="border-t border-neutral-200 p-3 dark:border-neutral-700">
      <div className="flex items-end gap-2">
        <textarea
          ref={textareaRef}
          value={value}
          onChange={(e) => setValue(e.target.value)}
          onKeyDown={handleKeyDown}
          placeholder="Pose une question..."
          rows={1}
          disabled={streaming}
          className="min-h-[36px] flex-1 resize-none rounded-xl border border-neutral-300 bg-white px-3 py-2 text-sm placeholder-neutral-400 outline-none transition focus:border-neutral-500 focus:ring-1 focus:ring-neutral-500 disabled:opacity-50 dark:border-neutral-600 dark:bg-neutral-800 dark:placeholder-neutral-500 dark:focus:border-neutral-400 dark:focus:ring-neutral-400"
        />
        {streaming ? (
          <button
            onClick={onStop}
            className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-red-500 text-white transition hover:bg-red-600"
            title="Arrêter"
          >
            <Square className="h-4 w-4 fill-current" />
          </button>
        ) : (
          <button
            onClick={handleSubmit}
            disabled={disabled || !value.trim()}
            className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-neutral-900 text-white transition hover:bg-neutral-700 disabled:opacity-40 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-300"
            title="Envoyer"
          >
            <Send className="h-4 w-4" />
          </button>
        )}
      </div>
    </div>
  );
}

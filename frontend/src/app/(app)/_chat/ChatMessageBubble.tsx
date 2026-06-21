"use client";

import { cn } from "@/lib/utils";
import type { ChatMessage } from "@/lib/types";

export function ChatMessageBubble({
  message,
  streaming,
}: {
  message: ChatMessage;
  streaming?: boolean;
}) {
  const isUser = message.role === "user";

  return (
    <div
      className={cn(
        "flex",
        isUser ? "justify-end" : "justify-start"
      )}
    >
      <div
        className={cn(
          "max-w-[85%] rounded-2xl px-3.5 py-2 text-sm leading-relaxed",
          isUser
            ? "bg-neutral-900 text-white dark:bg-white dark:text-neutral-900"
            : "bg-neutral-100 text-neutral-900 dark:bg-neutral-800 dark:text-neutral-100"
        )}
      >
        <p className="whitespace-pre-wrap break-words">
          {message.content}
          {streaming && (
            <span className="inline-block w-1.5 animate-pulse bg-current ml-0.5" />
          )}
        </p>
      </div>
    </div>
  );
}

"use client";

import { useEffect, useRef } from "react";
import type { ChatMessage } from "@/lib/types";
import { ChatMessageBubble } from "./ChatMessageBubble";

export function ChatMessageList({
  messages,
  streaming,
}: {
  messages: ChatMessage[];
  streaming?: boolean;
}) {
  const bottomRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages]);

  if (messages.length === 0) {
    return (
      <div className="flex min-h-0 flex-1 items-center justify-center p-4 text-center">
        <div className="space-y-2">
          <p className="text-sm font-medium text-neutral-500 dark:text-neutral-400">
            Assistant Patrimoine
          </p>
          <p className="text-xs text-neutral-400 dark:text-neutral-500">
            Pose-moi des questions sur ton portefeuille.
          </p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-0 flex-1 space-y-3 overflow-y-auto px-3 py-4">
      {messages.map((msg, i) => (
        <ChatMessageBubble
          key={i}
          message={msg}
          streaming={streaming && i === messages.length - 1 && msg.role === "assistant"}
        />
      ))}
      <div ref={bottomRef} />
    </div>
  );
}

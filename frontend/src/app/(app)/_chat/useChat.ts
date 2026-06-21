"use client";

import { useState, useRef, useCallback } from "react";
import { chatApi } from "@/lib/api/chat";
import type { ChatMessage } from "@/lib/types";

export function useChat() {
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [streaming, setStreaming] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const abortRef = useRef<AbortController | null>(null);

  const send = useCallback(
    async (content: string, model: string | undefined) => {
      setError(null);

      const userMsg: ChatMessage = { role: "user", content };
      const current = [...messages, userMsg];
      setMessages(current);
      setStreaming(true);

      const assistantMsg: ChatMessage = { role: "assistant", content: "" };
      setMessages((prev) => [...prev, assistantMsg]);

      const abort = new AbortController();
      abortRef.current = abort;

      let done = false;

      await chatApi.stream(
        current.map(({ role, content }) => ({ role, content })),
        model,
        abort.signal,
        (token) => {
          setMessages((prev) => {
            const copy = [...prev];
            const last = { ...copy[copy.length - 1] };
            last.content += token;
            copy[copy.length - 1] = last;
            return copy;
          });
        },
        () => {
          done = true;
          setStreaming(false);
          abortRef.current = null;
        },
        (err) => {
          setError(err);
          setStreaming(false);
          abortRef.current = null;
          setMessages((prev) => prev.slice(0, -1));
        }
      );

      if (!done) {
        setStreaming(false);
        abortRef.current = null;
      }
    },
    [messages]
  );

  const stop = useCallback(() => {
    abortRef.current?.abort();
    setStreaming(false);
    abortRef.current = null;
  }, []);

  const clear = useCallback(() => {
    setMessages([]);
    setError(null);
  }, []);

  return { messages, streaming, error, send, stop, clear };
}

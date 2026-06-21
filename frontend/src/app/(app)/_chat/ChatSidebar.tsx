"use client";

import { useState, useEffect, useCallback } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { MessageSquare, Settings, Trash2 } from "lucide-react";
import { chatApi } from "@/lib/api/chat";
import { useChat } from "./useChat";
import { ChatMessageList } from "./ChatMessageList";
import { ChatInput } from "./ChatInput";
import { ChatModelSelector } from "./ChatModelSelector";
import { ChatApiKeyModal } from "./ChatApiKeyModal";

export function ChatSidebar() {
  const queryClient = useQueryClient();
  const [settingsOpen, setSettingsOpen] = useState(false);
  const [selectedModel, setSelectedModel] = useState<string | null>(null);
  const [selectedProvider, setSelectedProvider] = useState<"zen" | "go">("zen");

  const { data: settings } = useQuery({
    queryKey: ["chat-settings"],
    queryFn: () => chatApi.models().then((r) => r.data),
    staleTime: 60_000,
  });

  const { messages, streaming, error, send, stop, clear } = useChat();

  const setKeyMutation = useMutation({
    mutationFn: ({ key, provider }: { key: string; provider: "zen" | "go" }) =>
      chatApi.setApiKey(key, provider),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["chat-settings"] }),
  });

  const clearKeyMutation = useMutation({
    mutationFn: () => chatApi.clearApiKey(),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["chat-settings"] }),
  });

  const setModelMutation = useMutation({
    mutationFn: (model: string) => chatApi.setModel(model),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["chat-settings"] }),
  });

  useEffect(() => {
    if (settings?.model && (!selectedModel || !settings.models.some((m) => m.id === selectedModel))) {
      setSelectedModel(settings.model);
    }
    if (settings?.provider) {
      setSelectedProvider(settings.provider);
    }
  }, [settings?.model, settings?.provider, settings?.models, selectedModel]);

  const handleModelChange = useCallback(
    (model: string) => {
      setSelectedModel(model);
      setModelMutation.mutate(model);
    },
    [setModelMutation]
  );

  const hasKey = settings?.has_key ?? false;
  const models = settings?.models ?? [];

  return (
    <>
      <aside className="fixed right-0 top-0 z-30 flex h-dvh max-h-dvh w-96 flex-col border-l border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-950">
        {/* Header */}
        <div className="flex shrink-0 items-center justify-between border-b border-neutral-200 px-3 py-2.5 dark:border-neutral-800">
          <div className="flex items-center gap-2">
            <MessageSquare className="h-4 w-4 text-neutral-500" />
            <span className="text-sm font-medium">Assistant</span>
          </div>
          <div className="flex items-center gap-1">
            {messages.length > 0 && (
              <button
                onClick={clear}
                className="rounded-lg p-1.5 text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-600 dark:hover:bg-neutral-800 dark:hover:text-neutral-300"
                title="Nouvelle conversation"
              >
                <Trash2 className="h-3.5 w-3.5" />
              </button>
            )}
            {models.length > 0 && (
              <ChatModelSelector
                models={models}
                current={selectedModel}
                onChange={handleModelChange}
                disabled={streaming}
              />
            )}
            <button
              onClick={() => setSettingsOpen(true)}
              className="rounded-lg p-1.5 text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-600 dark:hover:bg-neutral-800 dark:hover:text-neutral-300"
              title="Paramètres"
            >
              <Settings className="h-3.5 w-3.5" />
            </button>
          </div>
        </div>

        {/* No key state */}
        {!hasKey && (
          <div className="flex flex-col items-center gap-3 px-6 py-12 text-center">
            <MessageSquare className="h-8 w-8 text-neutral-300 dark:text-neutral-600" />
            <p className="text-sm font-medium text-neutral-600 dark:text-neutral-400">
              Configure ta clé API OpenCode
            </p>
            <p className="text-xs text-neutral-400 dark:text-neutral-500">
              Pour utiliser l&apos;assistant, ajoute ta clé OpenCode.
            </p>
            <button
              onClick={() => setSettingsOpen(true)}
              className="rounded-xl bg-neutral-900 px-4 py-2 text-sm text-white transition hover:bg-neutral-700 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-300"
            >
              Ajouter ma clé
            </button>
          </div>
        )}

        <div className="flex min-h-0 flex-1 flex-col overflow-hidden">
          {/* Error banner */}
          {error && (
            <div className="mx-3 mt-2 shrink-0 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700 dark:bg-red-950 dark:text-red-300">
              {error}
            </div>
          )}

          {/* Messages */}
          {hasKey && (
            <ChatMessageList messages={messages} streaming={streaming} />
          )}

          {/* Input */}
          {hasKey && (
            <ChatInput
              onSend={(text) => send(text, selectedModel ?? undefined)}
              onStop={stop}
              streaming={streaming}
              disabled={!hasKey}
            />
          )}
        </div>
      </aside>

      <ChatApiKeyModal
        open={settingsOpen}
        hasKey={hasKey}
        provider={selectedProvider}
        onProviderChange={setSelectedProvider}
        onSave={async (key, provider) => setKeyMutation.mutateAsync({ key, provider })}
        onClear={async () => clearKeyMutation.mutateAsync()}
        onClose={() => setSettingsOpen(false)}
      />
    </>
  );
}

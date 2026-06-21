import { api, ensureCsrfToken, readCookie, API_BASE } from "@/lib/api/client";
import type { ChatMessage, ChatSettingsResponse, ChatApiKeyResponse } from "@/lib/types";

export const chatApi = {
  models: () => api.get<ChatSettingsResponse>("/chat/models"),

  setApiKey: (key: string, provider: "zen" | "go") =>
    api.put<ChatApiKeyResponse>("/chat/api-key", {
      opencode_api_key: key,
      opencode_provider: provider,
    }),

  setModel: (model: string) =>
    api.put<ChatApiKeyResponse>("/chat/api-key", { opencode_model: model }),

  clearApiKey: () => api.delete<ChatApiKeyResponse>("/chat/api-key"),

  stream: async (
    messages: Pick<ChatMessage, "role" | "content">[],
    model: string | undefined,
    signal: AbortSignal,
    onToken: (token: string) => void,
    onDone: () => void,
    onError: (err: string) => void
  ): Promise<void> => {
    await ensureCsrfToken();

    const xsrf = readCookie("XSRF-TOKEN");
    const headers: Record<string, string> = {
      Accept: "text/event-stream",
      "Content-Type": "application/json",
      "Cache-Control": "no-cache",
    };
    if (xsrf) {
      headers["X-XSRF-TOKEN"] = xsrf;
    }

    const body: Record<string, unknown> = { messages };
    if (model) {
      body.model = model;
    }

    const response = await fetch(`${API_BASE}/chat`, {
      method: "POST",
      credentials: "include",
      headers,
      body: JSON.stringify(body),
      signal,
    });

    if (!response.ok) {
      const text = await response.text().catch(() => "");
      onError(text || `Erreur ${response.status}`);
      return;
    }

    const reader = response.body?.getReader();
    if (!reader) {
      onError("Pas de flux disponible");
      return;
    }

    const decoder = new TextDecoder();
    let buffer = "";

    try {
      while (true) {
        const { done, value } = await reader.read();
        if (done) break;

        buffer += decoder.decode(value, { stream: true });
        const lines = buffer.split("\n");
        buffer = lines.pop() ?? "";

        for (const line of lines) {
          const trimmed = line.trim();
          if (!trimmed.startsWith("data: ")) continue;

          const data = trimmed.slice(6);
          if (data === "[DONE]") {
            onDone();
            return;
          }

          try {
            const parsed = JSON.parse(data);
            if (parsed.error) {
              onError(parsed.error);
              return;
            }
            if (parsed.content) {
              onToken(parsed.content);
            }
          } catch {
            // skip malformed
          }
        }
      }
    } catch (err: unknown) {
      if ((err as Error).name === "AbortError") return;
      onError((err as Error).message || "Erreur de lecture du flux");
    }
  },
};

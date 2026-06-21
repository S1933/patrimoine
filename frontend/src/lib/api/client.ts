import type { ApiError } from "@/lib/types";

/**
 * API client for the Laravel Sanctum stateful SPA backend.
 *
 * - Same-origin via nginx (recommended entry: http://localhost:8080).
 * - Credentials always included (cookies).
 * - CSRF: GET /sanctum/csrf-cookie once, then send X-XSRF-TOKEN header on
 *   unsafe methods. Token is read from the XSRF-TOKEN cookie set by Sanctum.
 */

export const API_BASE =
  process.env.NEXT_PUBLIC_API_URL || "/api/v1";

const UNSAFE_METHODS = ["POST", "PUT", "PATCH", "DELETE"];

let csrfPromise: Promise<void> | null = null;

export function readCookie(name: string): string | null {
  if (typeof document === "undefined") return null;
  const match = document.cookie.match(
    new RegExp("(?:^|; )" + name.replace(/[.$?*|{}()[\]\\/+^]/g, "\\$&") + "=([^;]*)")
  );
  return match ? decodeURIComponent(match[1]) : null;
}

export async function ensureCsrfToken(): Promise<void> {
  if (readCookie("XSRF-TOKEN")) return;
  if (!csrfPromise) {
    const base = API_BASE.replace(/\/api\/v\d+$/, "");
    csrfPromise = fetch(`${base}/sanctum/csrf-cookie`, {
      credentials: "include",
      headers: { Accept: "application/json" },
    }).then((res) => {
      if (!res.ok) throw new Error(`CSRF cookie failed: ${res.status}`);
    });
  }
  await csrfPromise;
  csrfPromise = null;
}

export class ApiRequestError extends Error {
  status: number;
  errors?: Record<string, string[]>;
  constructor(message: string, status: number, errors?: Record<string, string[]>) {
    super(message);
    this.status = status;
    this.errors = errors;
  }
}

type RequestOptions = {
  method?: string;
  body?: unknown;
  headers?: Record<string, string>;
  signal?: AbortSignal;
  skipAuthRedirect?: boolean;
};

async function request<T>(path: string, opts: RequestOptions = {}): Promise<T> {
  const method = (opts.method || "GET").toUpperCase();

  if (UNSAFE_METHODS.includes(method)) {
    await ensureCsrfToken();
  }

  const headers: Record<string, string> = {
    Accept: "application/json",
    ...(opts.headers ?? {}),
  };

  if (opts.body !== undefined && !(opts.body instanceof FormData)) {
    headers["Content-Type"] = "application/json";
  }

  const xsrf = readCookie("XSRF-TOKEN");
  if (xsrf && UNSAFE_METHODS.includes(method)) {
    headers["X-XSRF-TOKEN"] = xsrf;
  }

  const res = await fetch(`${API_BASE}${path}`, {
    method,
    credentials: "include",
    headers,
    body:
      opts.body === undefined
        ? undefined
        : opts.body instanceof FormData
        ? opts.body
        : JSON.stringify(opts.body),
    signal: opts.signal,
  });

  const isJson = res.headers.get("content-type")?.includes("application/json");
  const payload = isJson ? await res.json().catch(() => null) : null;

  if (!res.ok) {
    if (res.status === 401 && !opts.skipAuthRedirect && typeof window !== "undefined") {
      window.location.href = "/login";
      throw new ApiRequestError("Non authentifié", 401);
    }
    const message =
      (payload && (payload.message || (payload.errors && "Validation error"))) ||
      `Erreur ${res.status}`;
    throw new ApiRequestError(message, res.status, payload?.errors);
  }

  if (res.status === 204) return undefined as T;
  return payload as T;
}

export const api = {
  get: <T>(path: string, opts?: Omit<RequestOptions, "method" | "body">) =>
    request<T>(path, { ...opts, method: "GET" }),
  post: <T>(path: string, body?: unknown, opts?: Omit<RequestOptions, "method" | "body">) =>
    request<T>(path, { ...opts, method: "POST", body }),
  put: <T>(path: string, body?: unknown, opts?: Omit<RequestOptions, "method" | "body">) =>
    request<T>(path, { ...opts, method: "PUT", body }),
  patch: <T>(path: string, body?: unknown, opts?: Omit<RequestOptions, "method" | "body">) =>
    request<T>(path, { ...opts, method: "PATCH", body }),
  delete: <T>(path: string, opts?: Omit<RequestOptions, "method" | "body">) =>
    request<T>(path, { ...opts, method: "DELETE" }),
};

export function extractError(err: unknown): string {
  if (err instanceof ApiRequestError) {
    if (err.errors) {
      const first = Object.values(err.errors)[0]?.[0];
      if (first) return first;
    }
    return err.message;
  }
  if (err instanceof Error) return err.message;
  return "Une erreur est survenue.";
}

export type { ApiError };

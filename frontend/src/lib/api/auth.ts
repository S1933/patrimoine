import { api } from "@/lib/api/client";
import type { User } from "@/lib/types";

type AuthResponse = { data: User };

export async function login(email: string, password: string): Promise<User> {
  const res = await api.post<AuthResponse>("/auth/login", { email, password });
  return res.data;
}

export async function register(name: string, email: string, password: string): Promise<User> {
  const res = await api.post<AuthResponse>("/auth/register", {
    name,
    email,
    password,
    password_confirmation: password,
  });
  return res.data;
}

export async function logout(): Promise<void> {
  await api.post<void>("/auth/logout");
}

export async function me(): Promise<User> {
  const res = await api.get<AuthResponse>("/auth/me", { skipAuthRedirect: true });
  return res.data;
}

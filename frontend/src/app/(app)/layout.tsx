"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useEffect } from "react";
import { useAuth } from "@/lib/auth-context";
import { cn } from "@/lib/utils";
import { ChatSidebar } from "./_chat/ChatSidebar";

const NAV = [
  { href: "/dashboard", label: "Dashboard" },
  { href: "/investments", label: "Investissements" },

  { href: "/settings", label: "Paramètres" },
];

export default function AppLayout({ children }: { children: React.ReactNode }) {
  const { user, loading, logout } = useAuth();
  const router = useRouter();
  const pathname = usePathname();

  const bypass = process.env.NEXT_PUBLIC_AUTH_BYPASS === "true";

  useEffect(() => {
    if (!bypass && !loading && !user) {
      const redirect = encodeURIComponent(pathname);
      router.replace(`/login?redirect=${redirect}`);
    }
  }, [bypass, loading, user, pathname, router]);

  async function onLogout() {
    await logout();
    router.replace("/login");
  }

  if (!bypass && (loading || !user)) {
    return (
      <div className="flex min-h-screen items-center justify-center">
        <p className="text-sm text-neutral-500">Chargement…</p>
      </div>
    );
  }

  return (
    <div className="flex min-h-screen flex-col">
      <header className="flex h-14 items-center justify-between border-b border-neutral-200 px-4 lg:pr-96 dark:border-neutral-800">
        <Link href="/dashboard" className="font-semibold tracking-tight">
          Patrimoine
        </Link>
        <nav className="flex items-center gap-1">
          {NAV.map((item) => {
            const active = pathname === item.href || pathname.startsWith(`${item.href}/`);
            return (
              <Link
                key={item.href}
                href={item.href}
                className={cn(
                  "rounded-md px-3 py-1.5 text-sm transition",
                  active
                    ? "bg-neutral-100 font-medium text-neutral-900 dark:bg-neutral-800 dark:text-white"
                    : "text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white"
                )}
              >
                {item.label}
              </Link>
            );
          })}
        </nav>
        <div className="flex items-center gap-3">
          {user && <span className="hidden text-sm text-neutral-500 sm:inline">{user.email}</span>}
          {user && (
            <button
              onClick={onLogout}
              className="rounded-md border border-neutral-300 px-3 py-1.5 text-sm transition hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800"
            >
              Déconnexion
            </button>
          )}
        </div>
      </header>

      <div className="flex flex-1 overflow-hidden">
        <main className="flex-1 overflow-y-auto px-4 py-6 sm:px-6 lg:px-8 lg:pr-96">{children}</main>
        <ChatSidebar />
      </div>
    </div>
  );
}

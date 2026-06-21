"use client";

export default function AppError({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  return (
    <div className="flex min-h-[60vh] items-center justify-center px-4">
      <div className="max-w-md rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
        <h1 className="text-lg font-semibold">Le tableau de bord a planté</h1>
        <p className="mt-2 text-sm text-neutral-500">{error.message}</p>
        <button
          onClick={() => reset()}
          className="mt-4 rounded-md bg-neutral-900 px-3 py-2 text-sm text-white dark:bg-white dark:text-neutral-900"
        >
          Recharger
        </button>
      </div>
    </div>
  );
}

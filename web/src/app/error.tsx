"use client";
import * as Sentry from "@sentry/nextjs";
import { useEffect } from "react";

export default function Error({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  useEffect(() => {
    Sentry.captureException(error);
  }, [error]);

  return (
    <div className="flex min-h-screen flex-col items-center justify-center gap-4 px-4 text-center">
      <div className="text-6xl font-bold text-[var(--color-danger)]">!</div>
      <h1 className="text-2xl font-semibold">Terjadi Kesalahan</h1>
      <p className="max-w-md text-[var(--color-fg-muted)]">
        Terjadi kesalahan yang tidak terduga. Silakan coba lagi.
      </p>
      <button
        onClick={reset}
        className="mt-4 inline-flex items-center gap-2 rounded-xl bg-[var(--color-brand)] px-5 py-2.5 text-sm font-medium text-white transition hover:opacity-90"
      >
        Coba Lagi
      </button>
    </div>
  );
}

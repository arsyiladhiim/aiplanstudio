"use client";
import * as Sentry from "@sentry/nextjs";
import { useEffect } from "react";

export default function GlobalError({
  error,
}: {
  error: Error & { digest?: string };
}) {
  useEffect(() => {
    Sentry.captureException(error);
  }, [error]);

  return (
    <html>
      <body>
        <div className="flex min-h-screen flex-col items-center justify-center gap-4 px-4 text-center">
          <h1 className="text-2xl font-semibold">Terjadi kesalahan aplikasi</h1>
          <p className="text-sm opacity-70">Silakan muat ulang halaman.</p>
          <button
            onClick={() => window.location.reload()}
            className="rounded-lg border border-white/20 px-4 py-2 text-sm"
          >
            Muat Ulang
          </button>
        </div>
      </body>
    </html>
  );
}

"use client";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useState } from "react";
import { Button } from "@/components/ui/Button";
import { Input, Label } from "@/components/ui";
import { apiPost } from "@/lib/api";

export default function LoginForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const pendingNotice = searchParams.get("status") === "pending";
  const googleError = searchParams.get("error") === "google";

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  async function handleSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setLoading(true);
    setError("");
    const fd = new FormData(e.currentTarget);
    const email = fd.get("email") as string;
    const password = fd.get("password") as string;

    try {
      const res = await apiPost<{ two_factor_required?: boolean }>("/login", { email, password });
      if (res?.two_factor_required) {
        router.push("/login/2fa");
        return;
      }
      router.push("/dashboard");
    } catch (err: unknown) {
      const msg =
        err instanceof Error
          ? err.message
          : "Login gagal.";
      setError(msg);
    } finally {
      setLoading(false);
    }
  }

  return (
    <div>
      <h1 className="text-2xl font-bold">Selamat datang kembali</h1>
      <p className="mt-1.5 text-sm text-[var(--color-fg-muted)]">
        Masuk untuk melanjutkan planning-mu.
      </p>

      {pendingNotice && (
        <div className="mt-4 rounded-lg border border-[var(--color-warning)]/40 bg-[var(--color-warning)]/10 px-4 py-3 text-sm text-[var(--color-warning)]">
          Akun kamu berhasil dibuat. Silakan tunggu persetujuan admin sebelum
          bisa masuk.
        </div>
      )}

      {googleError && (
        <div className="mt-4 rounded-lg border border-[var(--color-danger)]/40 bg-[var(--color-danger)]/10 px-4 py-3 text-sm text-[var(--color-danger)]">
          Login Google gagal. Pastikan kredensial Google sudah dikonfigurasi,
          lalu coba lagi.
        </div>
      )}

      {error && (
        <div className="mt-4 rounded-lg border border-[var(--color-danger)]/40 bg-[var(--color-danger)]/10 px-4 py-3 text-sm text-[var(--color-danger)]">
          {error}
        </div>
      )}

      <form
        className="mt-6 space-y-4"
        onSubmit={handleSubmit}
        data-testid="login-form"
      >
        <div>
          <Label htmlFor="email">Email</Label>
          <Input
            id="email"
            name="email"
            type="email"
            placeholder="kamu@email.com"
            required
          />
        </div>
        <div>
          <div className="flex items-center justify-between">
            <Label htmlFor="password">Password</Label>
            <Link
              href="/forgot-password"
              className="text-sm font-medium text-[var(--color-brand)] hover:underline"
            >
              Lupa password?
            </Link>
          </div>
          <Input
            id="password"
            name="password"
            type="password"
            placeholder="••••••••"
            required
          />
        </div>
        <Button
          type="submit"
          className="w-full"
          disabled={loading}
          data-testid="login-submit"
        >
          {loading ? "Masuk…" : "Masuk"}
        </Button>
      </form>

      <div className="mt-6 flex items-center gap-3">
        <div className="h-px flex-1 bg-[var(--color-border)]" />
        <span className="text-xs text-[var(--color-fg-muted)]">atau</span>
        <div className="h-px flex-1 bg-[var(--color-border)]" />
      </div>

      <a
        href={`${process.env.NEXT_PUBLIC_API_URL ?? ""}/api/auth/google/redirect`}
        className="mt-6 inline-flex h-11 w-full items-center justify-center gap-2 rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-surface-2)] text-sm font-medium text-[var(--color-fg)] transition hover:bg-[var(--color-surface)]"
        data-testid="login-google"
      >
        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
          <path
            fill="#4285F4"
            d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.27-4.74 3.27-8.1z"
          />
          <path
            fill="#34A853"
            d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A11 11 0 0 0 12 23z"
          />
          <path
            fill="#FBBC05"
            d="M5.84 14.1a6.6 6.6 0 0 1 0-4.2V7.06H2.18a11 11 0 0 0 0 9.88l3.66-2.84z"
          />
          <path
            fill="#EA4335"
            d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1A11 11 0 0 0 2.18 7.06l3.66 2.84C6.71 7.3 9.14 5.38 12 5.38z"
          />
        </svg>
        Masuk dengan Google
      </a>

      <p className="mt-6 text-center text-sm text-[var(--color-fg-muted)]">
        Belum punya akun?{" "}
        <Link
          href="/register"
          className="font-medium text-[var(--color-brand)] hover:underline"
        >
          Daftar gratis
        </Link>
      </p>
    </div>
  );
}

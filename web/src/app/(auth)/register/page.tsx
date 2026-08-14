"use client";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { Button } from "@/components/ui/Button";
import { Input, Label } from "@/components/ui";
import { fetchCsrfCookie } from "@/lib/api";

export default function RegisterPage() {
  const router = useRouter();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [pwError, setPwError] = useState("");

  async function handleSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const fd = new FormData(e.currentTarget);
    const pw = fd.get("password") as string;
    const confirm = fd.get("password_confirmation") as string;
    if (pw !== confirm) {
      setPwError("Password dan konfirmasi password tidak sama.");
      return;
    }
    setPwError("");
    setLoading(true);
    setError("");
    try {
      await fetchCsrfCookie();

      const xsrfCookie = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);
      const xsrfToken = xsrfCookie ? decodeURIComponent(xsrfCookie[1]) : '';
      const res = await fetch("/api/register", {
        method: "POST",
        headers: { "Content-Type": "application/json", ...(xsrfToken ? { "X-XSRF-TOKEN": xsrfToken } : {}) },
        credentials: "include",
        body: JSON.stringify({
          name: fd.get("name"),
          email: fd.get("email"),
          password: pw,
          password_confirmation: confirm,
        }),
      });

      if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        throw new Error(data.message || data.errors?.email?.[0] || "Registrasi gagal");
      }

      const data = await res.json().catch(() => ({}));
      if (data.pending) {
        router.push("/login?status=pending");
      } else {
        router.push("/dashboard");
      }
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "Registrasi gagal.");
    } finally {
      setLoading(false);
    }
  }

  return (
    <div>
      <h1 className="text-2xl font-bold">Buat akun</h1>
      <p className="mt-1.5 text-sm text-[var(--color-fg-muted)]">Mulai ubah ide jadi blueprint aplikasi.</p>

      {error && (
        <div className="mt-4 rounded-lg border border-[var(--color-danger)]/40 bg-[var(--color-danger)]/10 px-4 py-3 text-sm text-[var(--color-danger)]">{error}</div>
      )}

      <form className="mt-6 space-y-4" onSubmit={handleSubmit} data-testid="register-form">
        <div>
          <Label htmlFor="name">Nama</Label>
          <Input id="name" name="name" placeholder="Nama lengkap" required />
        </div>
        <div>
          <Label htmlFor="email">Email</Label>
          <Input id="email" name="email" type="email" placeholder="kamu@email.com" required />
        </div>
        <div>
          <Label htmlFor="password">Password</Label>
          <Input id="password" name="password" type="password" placeholder="Minimal 8 karakter" required />
        </div>
        <div>
          <Label htmlFor="password_confirmation">Konfirmasi Password</Label>
          <Input id="password_confirmation" name="password_confirmation" type="password" placeholder="Ulangi password" required />
          {pwError && <p className="mt-1 text-xs text-[var(--color-danger)]">{pwError}</p>}
        </div>
        <Button type="submit" className="w-full" disabled={loading} data-testid="register-submit">
          {loading ? "Mendaftar…" : "Daftar"}
        </Button>
      </form>

      <div className="mt-6 flex items-center gap-3">
        <div className="h-px flex-1 bg-[var(--color-border)]" />
        <span className="text-xs text-[var(--color-fg-muted)]">atau</span>
        <div className="h-px flex-1 bg-[var(--color-border)]" />
      </div>

      <a
        href="/api/auth/google"
        className="mt-6 inline-flex h-11 w-full items-center justify-center gap-2 rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-surface-2)] text-sm font-medium text-[var(--color-fg)] transition hover:bg-[var(--color-surface)]"
        data-testid="register-google"
      >
        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
          <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.27-4.74 3.27-8.1z" />
          <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A11 11 0 0 0 12 23z" />
          <path fill="#FBBC05" d="M5.84 14.1a6.6 6.6 0 0 1 0-4.2V7.06H2.18a11 11 0 0 0 0 9.88l3.66-2.84z" />
          <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1A11 11 0 0 0 2.18 7.06l3.66 2.84C6.71 7.3 9.14 5.38 12 5.38z" />
        </svg>
        Daftar dengan Google
      </a>

      <p className="mt-6 text-center text-sm text-[var(--color-fg-muted)]">
        Sudah punya akun?{" "}
        <Link href="/login" className="font-medium text-[var(--color-brand)] hover:underline">Masuk</Link>
      </p>
    </div>
  );
}

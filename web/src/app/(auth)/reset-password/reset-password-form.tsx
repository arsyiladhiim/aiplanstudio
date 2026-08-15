"use client";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { useState } from "react";
import { Button } from "@/components/ui/Button";
import { Input, Label } from "@/components/ui";
import { apiPost } from "@/lib/api";

export default function ResetPasswordForm() {
  const searchParams = useSearchParams();
  const token = searchParams.get("token") ?? "";
  const email = searchParams.get("email") ?? "";

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [done, setDone] = useState(false);

  async function handleSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const fd = new FormData(e.currentTarget);
    const password = fd.get("password") as string;
    const confirmation = fd.get("password_confirmation") as string;

    if (password !== confirmation) {
      setError("Password dan konfirmasi tidak sama.");
      return;
    }

    setLoading(true);
    setError("");
    try {
      await apiPost("/reset-password", { token, email, password, password_confirmation: confirmation });
      setDone(true);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "Reset password gagal.");
    } finally {
      setLoading(false);
    }
  }

  if (!token || !email) {
    return (
      <div>
        <h1 className="text-2xl font-bold">Tautan reset tidak valid</h1>
        <p className="mt-1.5 text-sm text-[var(--color-fg-muted)]">Tautan reset password tidak lengkap atau sudah kedaluwarsa.</p>
        <p className="mt-6 text-center text-sm text-[var(--color-fg-muted)]">
          <Link href="/forgot-password" className="font-medium text-[var(--color-brand)] hover:underline">Minta tautan baru</Link>
        </p>
      </div>
    );
  }

  return (
    <div>
      <h1 className="text-2xl font-bold">Atur password baru</h1>
      <p className="mt-1.5 text-sm text-[var(--color-fg-muted)]">Buat password baru untuk {email}.</p>

      {done ? (
        <div className="mt-6 rounded-lg border border-green-500/40 bg-green-500/10 px-4 py-3 text-sm text-green-500">
          Password berhasil diubah. Kamu bisa login sekarang.
          <Link href="/login" className="ml-1 font-medium text-[var(--color-brand)] hover:underline">Ke halaman login</Link>
        </div>
      ) : (
        <>
          {error && (
            <div className="mt-4 rounded-lg border border-[var(--color-danger)]/40 bg-[var(--color-danger)]/10 px-4 py-3 text-sm text-[var(--color-danger)]">{error}</div>
          )}

          <form className="mt-6 space-y-4" onSubmit={handleSubmit} data-testid="reset-password-form">
            <div>
              <Label htmlFor="password">Password Baru</Label>
              <Input id="password" name="password" type="password" placeholder="••••••••" minLength={8} required />
            </div>
            <div>
              <Label htmlFor="password_confirmation">Konfirmasi Password</Label>
              <Input id="password_confirmation" name="password_confirmation" type="password" placeholder="••••••••" minLength={8} required />
            </div>
            <Button type="submit" className="w-full" disabled={loading} data-testid="reset-password-submit">
              {loading ? "Menyimpan…" : "Simpan Password Baru"}
            </Button>
          </form>
        </>
      )}
    </div>
  );
}

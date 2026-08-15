"use client";
import Link from "next/link";
import { useState } from "react";
import { Button } from "@/components/ui/Button";
import { Input, Label } from "@/components/ui";
import { apiPost } from "@/lib/api";

export default function ForgotPasswordPage() {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [sent, setSent] = useState(false);

  async function handleSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const fd = new FormData(e.currentTarget);
    const email = fd.get("email") as string;
    setLoading(true);
    setError("");
    try {
      await apiPost("/forgot-password", { email });
      setSent(true);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "Gagal mengirim tautan reset.");
    } finally {
      setLoading(false);
    }
  }

  return (
    <div>
      <h1 className="text-2xl font-bold">Lupa password?</h1>
      <p className="mt-1.5 text-sm text-[var(--color-fg-muted)]">Masukkan emailmu, kami kirim tautan untuk reset password.</p>

      {sent ? (
        <div className="mt-6 rounded-lg border border-green-500/40 bg-green-500/10 px-4 py-3 text-sm text-green-500">
          Jika email terdaftar, tautan reset password telah dikirim. Cek kotak masukmu.
        </div>
      ) : (
        <>
          {error && (
            <div className="mt-4 rounded-lg border border-[var(--color-danger)]/40 bg-[var(--color-danger)]/10 px-4 py-3 text-sm text-[var(--color-danger)]">{error}</div>
          )}

          <form className="mt-6 space-y-4" onSubmit={handleSubmit} data-testid="forgot-password-form">
            <div>
              <Label htmlFor="email">Email</Label>
              <Input id="email" name="email" type="email" placeholder="kamu@email.com" required />
            </div>
            <Button type="submit" className="w-full" disabled={loading} data-testid="forgot-password-submit">
              {loading ? "Mengirim…" : "Kirim Tautan Reset"}
            </Button>
          </form>
        </>
      )}

      <p className="mt-6 text-center text-sm text-[var(--color-fg-muted)]">
        Ingat password?{" "}
        <Link href="/login" className="font-medium text-[var(--color-brand)] hover:underline">Kembali ke login</Link>
      </p>
    </div>
  );
}

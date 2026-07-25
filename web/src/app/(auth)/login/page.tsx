"use client";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { Button } from "@/components/ui/Button";
import { Input, Label } from "@/components/ui";
import { fetchCsrfCookie } from "@/lib/api";

export default function LoginPage() {
  const router = useRouter();
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
      await fetchCsrfCookie();

      const res = await fetch("/api/login", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "include",
        body: JSON.stringify({ email, password }),
      });

      if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        throw new Error(data.message || data.errors?.email?.[0] || "Login gagal");
      }

      window.location.href = "/dashboard";
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "Login gagal.");
    } finally {
      setLoading(false);
    }
  }

  return (
    <div>
      <h1 className="text-2xl font-bold">Selamat datang kembali</h1>
      <p className="mt-1.5 text-sm text-[var(--color-fg-muted)]">Masuk untuk melanjutkan planning-mu.</p>

      {error && (
        <div className="mt-4 rounded-lg border border-[var(--color-danger)]/40 bg-[var(--color-danger)]/10 px-4 py-3 text-sm text-[var(--color-danger)]">{error}</div>
      )}

      <form className="mt-6 space-y-4" onSubmit={handleSubmit} data-testid="login-form">
        <div>
          <Label htmlFor="email">Email</Label>
          <Input id="email" name="email" type="email" placeholder="kamu@email.com" required />
        </div>
        <div>
          <Label htmlFor="password">Password</Label>
          <Input id="password" name="password" type="password" placeholder="••••••••" required />
        </div>
        <Button type="submit" className="w-full" disabled={loading} data-testid="login-submit">
          {loading ? "Masuk…" : "Masuk"}
        </Button>
      </form>

      <p className="mt-6 text-center text-sm text-[var(--color-fg-muted)]">
        Belum punya akun?{" "}
        <Link href="/register" className="font-medium text-[var(--color-brand)] hover:underline">Daftar gratis</Link>
      </p>
    </div>
  );
}

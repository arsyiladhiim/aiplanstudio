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

  async function handleSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setLoading(true);
    setError("");
    const fd = new FormData(e.currentTarget);
    try {
      await fetchCsrfCookie();

      const res = await fetch("/api/register", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "include",
        body: JSON.stringify({
          name: fd.get("name"),
          email: fd.get("email"),
          password: fd.get("password"),
        }),
      });

      if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        throw new Error(data.message || data.errors?.email?.[0] || "Registrasi gagal");
      }

      router.push("/dashboard");
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
        <Button type="submit" className="w-full" disabled={loading} data-testid="register-submit">
          {loading ? "Mendaftar…" : "Daftar"}
        </Button>
      </form>

      <p className="mt-6 text-center text-sm text-[var(--color-fg-muted)]">
        Sudah punya akun?{" "}
        <Link href="/login" className="font-medium text-[var(--color-brand)] hover:underline">Masuk</Link>
      </p>
    </div>
  );
}

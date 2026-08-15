"use client";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { Button } from "@/components/ui/Button";
import { Input, Label } from "@/components/ui";
import { apiPost } from "@/lib/api";
import { ShieldCheck, Loader2 } from "lucide-react";

export default function TwoFactorPage() {
  const router = useRouter();
  const [code, setCode] = useState("");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  async function handleSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setLoading(true);
    setError("");
    try {
      await apiPost("/login/2fa", { code: code.trim() });
      router.push("/dashboard");
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "Kode 2FA salah.");
      setCode("");
    } finally {
      setLoading(false);
    }
  }

  async function cancel() {
    try {
      await apiPost("/login/2fa/cancel", {});
    } catch {
      /* ignore */
    }
    router.push("/login");
  }

  return (
    <div>
      <div className="flex items-center gap-3">
        <div className="grid h-10 w-10 place-items-center rounded-full bg-[var(--color-brand)]/10 text-[var(--color-brand)]">
          <ShieldCheck size={20} />
        </div>
        <div>
          <h1 className="text-2xl font-bold">Verifikasi 2FA</h1>
          <p className="text-sm text-[var(--color-fg-muted)]">
            Masukkan 6-digit kode dari authenticator app.
          </p>
        </div>
      </div>

      {error && (
        <div className="mt-4 rounded-lg border border-[var(--color-danger)]/40 bg-[var(--color-danger)]/10 px-4 py-3 text-sm text-[var(--color-danger)]">
          {error}
        </div>
      )}

      <form
        className="mt-6 space-y-4"
        onSubmit={handleSubmit}
        data-testid="twofa-form"
      >
        <div>
          <Label htmlFor="code">Kode 2FA / Recovery Code</Label>
          <Input
            id="code"
            name="code"
            type="text"
            inputMode="numeric"
            autoComplete="one-time-code"
            value={code}
            onChange={(e) => setCode(e.target.value)}
            placeholder="123456 atau recovery code"
            required
            autoFocus
          />
          <p className="mt-1 text-xs text-[var(--color-fg-subtle)]">
            Bisa juga pakai 8-character recovery code (single-use).
          </p>
        </div>
        <Button
          type="submit"
          className="w-full"
          disabled={loading || code.length < 6}
          data-testid="twofa-submit"
        >
          {loading ? <Loader2 size={15} className="animate-spin" /> : "Verifikasi"}
        </Button>
        <Button
          type="button"
          variant="ghost"
          className="w-full"
          onClick={cancel}
          disabled={loading}
        >
          Batal & kembali ke login
        </Button>
      </form>
    </div>
  );
}

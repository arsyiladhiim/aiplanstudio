"use client";
import { useState, useEffect } from "react";
import { Card, Input, Label } from "@/components/ui";
import { Button } from "@/components/ui/Button";
import { apiGet, apiPatch, fetchCsrfCookie } from "@/lib/api";
import { Loader2, Check, AlertCircle, Eye, EyeOff } from "lucide-react";

const PRESETS = [
  { name: "Ungu", value: "#7c3aed" },
  { name: "Cyan", value: "#06b6d4" },
  { name: "Hijau", value: "#10b981" },
  { name: "Oranye", value: "#f59e0b" },
  { name: "Merah", value: "#ef4444" },
  { name: "Pink", value: "#ec4899" },
];

function applyAccent(color: string | null) {
  if (typeof document === "undefined") return;
  if (color) document.documentElement.style.setProperty("--color-brand", color);
  else document.documentElement.style.removeProperty("--color-brand");
}

export default function ProfilePage() {
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [accentColor, setAccentColor] = useState<string>("");
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
  const [error, setError] = useState("");

  useEffect(() => {
    apiGet<{ id: number; name: string; email: string; role: string; accent_color: string | null }>("/settings/profile")
      .then(data => {
        setName(data.name);
        setEmail(data.email);
        setAccentColor(data.accent_color ?? "");
        applyAccent(data.accent_color ?? null);
      })
      .catch(err => setError(err instanceof Error ? err.message : "Gagal memuat profil"))
      .finally(() => setLoading(false));
  }, []);

  async function handleSave() {
    setSaving(true);
    setMessage(null);
    try {
      await fetchCsrfCookie();
      const body: Record<string, string | null> = {};
      if (name.trim()) body.name = name.trim();
      if (email.trim()) body.email = email.trim();
      if (password) {
        body.password = password;
        body.password_confirmation = passwordConfirmation;
      }
      body.accent_color = accentColor.trim() || null;
      await apiPatch("/settings/profile", body);
      applyAccent(accentColor.trim() || null);
      setMessage({ type: 'success', text: 'Profil berhasil diperbarui.' });
      setPassword("");
      setPasswordConfirmation("");
      window.dispatchEvent(new CustomEvent('profile-updated'));
    } catch (err) {
      setMessage({ type: 'error', text: err instanceof Error ? err.message : 'Gagal menyimpan profil' });
    } finally {
      setSaving(false);
    }
  }

  if (loading) return <div className="text-center py-8 text-sm text-[var(--color-fg-muted)]"><Loader2 size={16} className="animate-spin inline" /> Memuat profil...</div>;
  if (error) return <div className="rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-500">{error}</div>;

  return (
    <Card className="p-6">
      <h2 className="mb-4 text-lg font-semibold">Edit Profil</h2>
      <div className="space-y-4">
        <div>
          <Label>Warna Aksen</Label>
          <div className="mt-2 flex flex-wrap items-center gap-2">
            {PRESETS.map((p) => (
              <button
                key={p.value}
                type="button"
                aria-label={p.name}
                title={p.name}
                onClick={() => setAccentColor(p.value)}
                className={`h-8 w-8 rounded-full border-2 transition ${
                  accentColor.toLowerCase() === p.value.toLowerCase()
                    ? "border-[var(--color-fg)] scale-110"
                    : "border-transparent hover:scale-105"
                }`}
                style={{ background: p.value }}
                data-testid={`accent-${p.value}`}
              />
            ))}
            <input
              type="color"
              value={accentColor || "#7c3aed"}
              onChange={(e) => setAccentColor(e.target.value)}
              className="h-8 w-8 cursor-pointer rounded-full border-0 bg-transparent"
              aria-label="Pilih warna kustom"
              data-testid="accent-custom"
            />
            <Input
              type="text"
              value={accentColor}
              onChange={(e) => setAccentColor(e.target.value)}
              placeholder="#7c3aed"
              className="w-32 font-mono text-xs"
              data-testid="accent-hex"
            />
            {accentColor && (
              <Button variant="ghost" size="sm" onClick={() => setAccentColor("")}>Reset</Button>
            )}
          </div>
        </div>
        <hr className="border-[var(--color-border)]" />
        <div>
          <Label htmlFor="name">Nama</Label>
          <Input id="name" value={name} onChange={(e) => setName(e.target.value)} />
        </div>
        <div>
          <Label htmlFor="email">Email</Label>
          <Input id="email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} />
        </div>
        <hr className="border-[var(--color-border)]" />
        <div>
          <Label htmlFor="password">Password Baru (kosongkan jika tidak ingin mengubah)</Label>
          <div className="relative">
            <Input
              id="password" type={showPassword ? "text" : "password"}
              value={password} onChange={(e) => setPassword(e.target.value)}
            />
            <button
              type="button" onClick={() => setShowPassword(!showPassword)}
              className="absolute right-3 top-1/2 -translate-y-1/2 text-[var(--color-fg-muted)]"
            >
              {showPassword ? <EyeOff size={16} /> : <Eye size={16} />}
            </button>
          </div>
        </div>
        {password && (
          <div>
            <Label htmlFor="password_confirmation">Konfirmasi Password Baru</Label>
            <Input
              id="password_confirmation" type="password"
              value={passwordConfirmation} onChange={(e) => setPasswordConfirmation(e.target.value)}
            />
          </div>
        )}
        {message && (
          <div className={`flex items-center gap-2 rounded-lg px-4 py-3 text-sm ${
            message.type === 'success' ? 'border border-green-500/40 bg-green-500/10 text-green-600' : 'border border-red-500/40 bg-red-500/10 text-red-500'
          }`}>
            {message.type === 'success' ? <Check size={16} /> : <AlertCircle size={16} />}
            {message.text}
          </div>
        )}
        <div className="flex justify-end">
          <Button onClick={handleSave} disabled={saving}>
            {saving ? <Loader2 size={15} className="animate-spin" /> : <Check size={15} />} Simpan
          </Button>
        </div>
      </div>
    </Card>
  );
}

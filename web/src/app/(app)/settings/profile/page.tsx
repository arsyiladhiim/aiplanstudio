"use client";
import { useState, useEffect } from "react";
import { Card, Input, Label, Modal } from "@/components/ui";
import { Button } from "@/components/ui/Button";
import { apiGet, apiPatch, apiPost } from "@/lib/api";
import { Loader2, Check, AlertCircle, Eye, EyeOff, ShieldCheck, Copy, Ban } from "lucide-react";

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
  const [currentPassword, setCurrentPassword] = useState("");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [accentColor, setAccentColor] = useState<string>("");
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
  const [error, setError] = useState("");
  const [role, setRole] = useState<string>("");
  // CP-18.F1: 2FA state (admin only).
  const [twoFAEnabled, setTwoFAEnabled] = useState(false);
  const [showSetupModal, setShowSetupModal] = useState(false);
  const [setupData, setSetupData] = useState<{ secret: string; otpauth_url: string } | null>(null);
  const [confirmCode, setConfirmCode] = useState("");
  const [recoveryCodes, setRecoveryCodes] = useState<string[] | null>(null);
  const [showDisableModal, setShowDisableModal] = useState(false);
  const [disablePassword, setDisablePassword] = useState("");
  const [twoFABusy, setTwoFABusy] = useState(false);
  const [twoFAError, setTwoFAError] = useState("");

  useEffect(() => {
    apiGet<{ id: number; name: string; email: string; role: string; accent_color: string | null }>("/settings/profile")
      .then(data => {
        setName(data.name);
        setEmail(data.email);
        setRole(data.role);
        setAccentColor(data.accent_color ?? "");
        applyAccent(data.accent_color ?? null);
        if (data.role === "admin") {
          apiGet<{ enabled: boolean }>("/settings/2fa")
            .then(s => setTwoFAEnabled(s.enabled))
            .catch(() => { /* ignore */ });
        }
      })
      .catch(err => setError(err instanceof Error ? err.message : "Gagal memuat profil"))
      .finally(() => setLoading(false));
  }, []);

  async function handleSave() {
    setSaving(true);
    setMessage(null);
    try {
      const body: Record<string, string | null> = {};
      if (name.trim()) body.name = name.trim();
      if (email.trim()) body.email = email.trim();
      if (password) {
        if (!currentPassword) {
          setMessage({ type: 'error', text: 'Password saat ini wajib diisi untuk mengubah password.' });
          setSaving(false);
          return;
        }
        body.password = password;
        body.password_confirmation = passwordConfirmation;
        body.current_password = currentPassword;
      }
      body.accent_color = accentColor.trim() || null;
      await apiPatch("/settings/profile", body);
      applyAccent(accentColor.trim() || null);
      setMessage({ type: 'success', text: 'Profil berhasil diperbarui.' });
      setCurrentPassword("");
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

  async function startSetup() {
    setTwoFABusy(true);
    setTwoFAError("");
    try {
      const data = await apiPost<{ secret: string; otpauth_url: string }>("/settings/2fa/setup", {});
      setSetupData(data);
      setShowSetupModal(true);
      setConfirmCode("");
      setRecoveryCodes(null);
    } catch (err) {
      setTwoFAError(err instanceof Error ? err.message : "Gagal memulai setup 2FA");
    } finally {
      setTwoFABusy(false);
    }
  }

  async function confirmSetup() {
    if (!setupData) return;
    setTwoFABusy(true);
    setTwoFAError("");
    try {
      const res = await apiPost<{ confirmed_at: string; recovery_codes: string[] }>("/settings/2fa/confirm", { code: confirmCode });
      setRecoveryCodes(res.recovery_codes);
      setTwoFAEnabled(true);
      setConfirmCode("");
    } catch (err) {
      setTwoFAError(err instanceof Error ? err.message : "Kode salah");
    } finally {
      setTwoFABusy(false);
    }
  }

  function closeSetup() {
    setShowSetupModal(false);
    setSetupData(null);
    setRecoveryCodes(null);
    setConfirmCode("");
    setTwoFAError("");
  }

  async function disable2FA() {
    setTwoFABusy(true);
    setTwoFAError("");
    try {
      await apiPost("/settings/2fa/disable", { password: disablePassword });
      setTwoFAEnabled(false);
      setDisablePassword("");
      setShowDisableModal(false);
    } catch (err) {
      setTwoFAError(err instanceof Error ? err.message : "Gagal disable 2FA");
    } finally {
      setTwoFABusy(false);
    }
  }

  return (
    <>
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
              placeholder="Minimal 8 karakter"
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
          <>
            <div>
              <Label htmlFor="current_password">Password Saat Ini (wajib untuk konfirmasi)</Label>
              <Input
                id="current_password" type="password"
                value={currentPassword} onChange={(e) => setCurrentPassword(e.target.value)}
                autoComplete="current-password"
              />
            </div>
            <div>
              <Label htmlFor="password_confirmation">Konfirmasi Password Baru</Label>
              <Input
                id="password_confirmation" type="password"
                value={passwordConfirmation} onChange={(e) => setPasswordConfirmation(e.target.value)}
                autoComplete="new-password"
              />
            </div>
          </>
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

    {role === "admin" && (
      <Card className="mt-4 p-6">
        <div className="flex items-start justify-between gap-4">
          <div>
            <h2 className="flex items-center gap-2 text-lg font-semibold">
              <ShieldCheck size={18} /> Two-Factor Authentication
            </h2>
            <p className="mt-1 text-sm text-[var(--color-fg-muted)]">
              Tambah lapisan keamanan ekstra. Setelah aktif, login butuh kode 6-digit dari authenticator app.
            </p>
          </div>
          {twoFAEnabled ? (
            <span className="inline-flex items-center gap-1.5 rounded-full bg-green-500/10 px-3 py-1 text-xs font-medium text-green-600">
              <Check size={12} /> Aktif
            </span>
          ) : (
            <span className="inline-flex items-center gap-1.5 rounded-full bg-[var(--color-surface-2)] px-3 py-1 text-xs font-medium text-[var(--color-fg-muted)]">
              Tidak aktif
            </span>
          )}
        </div>
        {twoFAError && (
          <div className="mt-3 flex items-center gap-2 rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-500">
            <AlertCircle size={16} /> {twoFAError}
          </div>
        )}
        <div className="mt-4">
          {twoFAEnabled ? (
            <Button variant="secondary" size="sm" onClick={() => setShowDisableModal(true)} disabled={twoFABusy} data-testid="2fa-disable">
              <Ban size={14} /> Nonaktifkan 2FA
            </Button>
          ) : (
            <Button size="sm" onClick={startSetup} disabled={twoFABusy} data-testid="2fa-enable">
              {twoFABusy ? <Loader2 size={14} className="animate-spin" /> : <ShieldCheck size={14} />} Aktifkan 2FA
            </Button>
          )}
        </div>
      </Card>
    )}

    <Modal open={showSetupModal} onClose={closeSetup} title="Setup 2FA" size="md">
      {setupData && (
        <div className="space-y-4">
          {!recoveryCodes ? (
            <>
              <p className="text-sm text-[var(--color-fg-muted)]">
                Scan QR code di bawah dengan Google Authenticator / Authy / 1Password,
                atau masukkan secret secara manual.
              </p>
              <div className="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-2)] p-4">
                <div className="text-xs uppercase tracking-wide text-[var(--color-fg-subtle)]">Secret</div>
                <div className="mt-1 flex items-center gap-2">
                  <code className="flex-1 break-all font-mono text-sm" data-testid="2fa-secret">{setupData.secret}</code>
                  <button
                    type="button"
                    onClick={() => navigator.clipboard.writeText(setupData.secret)}
                    className="text-[var(--color-fg-muted)] hover:text-[var(--color-fg)]"
                    aria-label="Copy secret"
                  >
                    <Copy size={14} />
                  </button>
                </div>
                <div className="mt-2 text-xs text-[var(--color-fg-subtle)]">
                  otpauth URL: <code className="break-all">{setupData.otpauth_url}</code>
                </div>
              </div>
              <div>
                <Label htmlFor="confirm_code">Kode 6-digit dari authenticator</Label>
                <Input
                  id="confirm_code"
                  type="text"
                  inputMode="numeric"
                  maxLength={6}
                  value={confirmCode}
                  onChange={(e) => setConfirmCode(e.target.value.replace(/\D/g, ""))}
                  placeholder="123456"
                  data-testid="2fa-confirm-code"
                />
              </div>
              {twoFAError && (
                <div className="rounded-lg border border-red-500/40 bg-red-500/10 px-3 py-2 text-sm text-red-500">
                  {twoFAError}
                </div>
              )}
              <div className="flex gap-2 pt-2">
                <Button onClick={confirmSetup} disabled={twoFABusy || confirmCode.length !== 6} data-testid="2fa-confirm-submit" className="flex-1">
                  {twoFABusy ? <Loader2 size={14} className="animate-spin" /> : "Konfirmasi & Aktifkan"}
                </Button>
                <Button variant="secondary" onClick={closeSetup}>Batal</Button>
              </div>
            </>
          ) : (
            <>
              <div className="rounded-lg border border-green-500/40 bg-green-500/10 px-4 py-3 text-sm text-green-600">
                2FA berhasil diaktifkan. Simpan recovery codes berikut di tempat aman — bisa dipakai untuk login kalau kehilangan authenticator.
              </div>
              <div className="grid grid-cols-2 gap-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-2)] p-4">
                {recoveryCodes.map((c, i) => (
                  <code key={i} className="font-mono text-sm" data-testid="2fa-recovery-code">{c}</code>
                ))}
              </div>
              <Button onClick={closeSetup} className="w-full">Saya sudah simpan</Button>
            </>
          )}
        </div>
      )}
    </Modal>

    <Modal open={showDisableModal} onClose={() => { setShowDisableModal(false); setDisablePassword(""); setTwoFAError(""); }} title="Nonaktifkan 2FA" size="sm">
      <div className="space-y-4">
        <p className="text-sm text-[var(--color-fg-muted)]">
          Masukkan password saat ini untuk konfirmasi.
        </p>
        <Input
          type="password"
          value={disablePassword}
          onChange={(e) => setDisablePassword(e.target.value)}
          placeholder="Password"
          autoComplete="current-password"
          data-testid="2fa-disable-password"
        />
        {twoFAError && (
          <div className="rounded-lg border border-red-500/40 bg-red-500/10 px-3 py-2 text-sm text-red-500">
            {twoFAError}
          </div>
        )}
        <div className="flex gap-2">
          <Button onClick={disable2FA} disabled={twoFABusy || !disablePassword} className="flex-1" data-testid="2fa-disable-submit">
            {twoFABusy ? <Loader2 size={14} className="animate-spin" /> : "Nonaktifkan"}
          </Button>
          <Button variant="secondary" onClick={() => { setShowDisableModal(false); setDisablePassword(""); setTwoFAError(""); }}>Batal</Button>
        </div>
      </div>
    </Modal>
    </>
  );
}

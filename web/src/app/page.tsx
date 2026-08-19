import Link from "next/link";
import { ButtonLink } from "@/components/ui/Button";
import { Badge, Card } from "@/components/ui";
import { ThemeToggle } from "@/components/ThemeToggle";
import {
  Sparkles, ArrowRight, Zap, Smartphone, Globe, ShieldCheck, Star,
  Zap as Bolt, Layers, Wand2, RefreshCw, FileCheck, User, Rocket, Users,
  MessageCircle, Puzzle, Download, Quote,
} from "lucide-react";

export default function LandingPage() {
  return (
    <>
      {/* Nav */}
      <header className="sticky top-0 z-40">
        <nav className="glass mx-auto mt-3 flex max-w-6xl items-center justify-between rounded-full px-4 py-2.5 sm:px-6">
          <Link href="/" className="flex items-center gap-2 font-semibold">
            <span className="grid h-8 w-8 place-items-center rounded-lg bg-[linear-gradient(135deg,var(--color-brand),var(--color-brand-2))] text-white">
              <Sparkles size={16} />
            </span>
            <span className="hidden sm:block">AI Planning Studio</span>
          </Link>
          <div className="hidden items-center gap-1 md:flex">
            <a href="#untuk-siapa" className="rounded-full px-3 py-2 text-sm text-[var(--color-fg-muted)] hover:text-[var(--color-fg)]">Untuk Siapa</a>
            <a href="#hasil" className="rounded-full px-3 py-2 text-sm text-[var(--color-fg-muted)] hover:text-[var(--color-fg)]">Hasil</a>
            <a href="#keunggulan" className="rounded-full px-3 py-2 text-sm text-[var(--color-fg-muted)] hover:text-[var(--color-fg)]">Keunggulan</a>
          </div>
          <div className="flex items-center gap-2">
            <ThemeToggle />
            <ButtonLink href="/login" variant="ghost" size="sm" className="hidden sm:inline-flex">Masuk</ButtonLink>
            <ButtonLink href="/register" size="sm">Mulai Gratis</ButtonLink>
          </div>
        </nav>
      </header>

      {/* Hero */}
      <section className="relative mx-auto max-w-6xl px-4 pt-16 pb-20 text-center sm:pt-24">
        <div className="grid-pattern pointer-events-none absolute inset-0 -z-10" />
        <div className="animate-fade-up mx-auto flex max-w-3xl flex-col items-center">
          <Badge className="mb-5"><Zap size={13} /> Untuk solo developer & indie hacker</Badge>
          <h1 className="text-4xl font-bold leading-[1.1] tracking-tight sm:text-6xl">
            Dari <span className="gradient-text">satu ide</span> jadi
            <br className="hidden sm:block" /> blueprint siap bangun
          </h1>
          <p className="mt-6 max-w-2xl text-lg text-[var(--color-fg-muted)]">
            Cukup jelaskan idemu. AI kami menyusun dokumentasi lengkap, skema data, dan prompt
            siap-pakai — untuk <strong className="text-[var(--color-fg)]">Web</strong>,{" "}
            <strong className="text-[var(--color-fg)]">Mobile</strong>, atau keduanya.
            Kamu fokus mengeksekusi.
          </p>
          <div className="mt-8 flex flex-col gap-3 sm:flex-row">
            <ButtonLink href="/register" size="lg" className="group">
              Buat Plan Pertama <ArrowRight size={18} className="transition group-hover:translate-x-0.5" />
            </ButtonLink>
            <ButtonLink href="/dashboard" variant="secondary" size="lg">Lihat Demo Dashboard</ButtonLink>
          </div>
          <p className="mt-4 text-xs text-[var(--color-fg-subtle)]">Gratis • Tanpa kartu kredit • Bawa AI provider sendiri</p>
        </div>

        {/* Visual artifact preview */}
        <div id="hasil" className="mx-auto mt-16 grid gap-4 md:grid-cols-3">
          <Card className="p-5 text-left">
            <div className="mb-3 flex items-center gap-2 text-sm font-semibold">
              <FileCheck size={16} className="text-[var(--color-brand)]" />
              Dokumentasi Produk
            </div>
            <div className="space-y-2 rounded-lg bg-[var(--color-bg-soft)] p-3 font-mono text-[11px] leading-relaxed text-[var(--color-fg-muted)]">
              <p className="text-[var(--color-brand)]"># Kasir UMKM</p>
              <p>## Target User</p>
              <p>Owner warung, non-teknis</p>
              <p>## Kebutuhan Utama</p>
              <p>Catat transaksi &lt; 5 detik</p>
              <p className="text-[var(--color-fg-subtle)]">… struktur lengkap 8 bagian</p>
            </div>
          </Card>
          <Card className="p-5 text-left">
            <div className="mb-3 flex items-center gap-2 text-sm font-semibold">
              <Layers size={16} className="text-[var(--color-brand)]" />
              Skema Data &amp; API
            </div>
            <div className="rounded-lg bg-[var(--color-bg-soft)] p-3 font-mono text-[11px] leading-relaxed text-[var(--color-fg-muted)]">
              <p className="flex items-center gap-1"><span className="h-2 w-2 rounded-full bg-[var(--color-brand)]" /> users</p>
              <p className="flex items-center gap-1 pl-3 text-[var(--color-fg-subtle)]">id, name, role</p>
              <p className="flex items-center gap-1"><span className="h-2 w-2 rounded-full bg-[var(--color-brand-2)]" /> transactions</p>
              <p className="flex items-center gap-1 pl-3 text-[var(--color-fg-subtle)]">total, items, paid_at</p>
              <p className="mt-2 text-[var(--color-brand)]">GET /api/transactions</p>
              <p className="text-[var(--color-brand)]">POST /api/transactions</p>
            </div>
          </Card>
          <Card className="p-5 text-left">
            <div className="mb-3 flex items-center gap-2 text-sm font-semibold">
              <Puzzle size={16} className="text-[var(--color-brand)]" />
              Prompt Siap-Pakai
            </div>
            <div className="rounded-lg bg-[var(--color-bg-soft)] p-3 font-mono text-[11px] leading-relaxed text-[var(--color-fg-muted)]">
              <p>Kamu adalah engineer senior.</p>
              <p>Bangun aplikasi sesuai</p>
              <p>dokumen ini. Gunakan</p>
              <p>stack yang sudah</p>
              <p>diputuskan. Ikuti</p>
              <p>standar di bawah.</p>
              <p className="mt-2 text-[var(--color-brand)]">[ Salin ke agent ]</p>
            </div>
          </Card>
        </div>
      </section>

      {/* For who */}
      <section id="untuk-siapa" className="mx-auto max-w-6xl px-4 py-16">
        <SectionHead badge="Untuk Siapa" title="Dibuat untuk kamu yang membangun sendirian" />
        <div className="mt-10 grid gap-4 md:grid-cols-3">
          {[
            { icon: User, t: "Solo Developer", d: "Lompat dari ide ke kode lebih cepat. Semua keputusan produk & teknis dirangkum rapi, tinggal eksekusi." },
            { icon: Rocket, t: "Indie Hacker", d: "Luncurkan MVP dalam hitungan hari, bukan minggu. Dokumentasi konsisten untuk iterasi cepat." },
            { icon: Users, t: "Tim Kecil / Agency", d: "Satu blueprint untuk seluruh tim — desainer, developer, dan QA bekerja dari dokumen yang sama." },
          ].map((c) => (
            <Card key={c.t} className="group p-6 transition hover:border-[color-mix(in_oklab,var(--color-brand)_50%,var(--color-border))]">
              <div className="mb-4 grid h-11 w-11 place-items-center rounded-xl bg-[color-mix(in_oklab,var(--color-brand)_16%,transparent)] text-[var(--color-brand)] transition group-hover:scale-105">
                <c.icon size={20} />
              </div>
              <h3 className="font-semibold">{c.t}</h3>
              <p className="mt-1.5 text-sm text-[var(--color-fg-muted)]">{c.d}</p>
            </Card>
          ))}
        </div>
      </section>

      {/* How it works — benefit-driven, no internal jargon */}
      <section className="mx-auto max-w-6xl px-4 py-16">
        <SectionHead badge="Cara Kerja" title="Empat langkah, tanpa menulis dokumen manual" />
        <div className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {[
            { icon: MessageCircle, step: "1", t: "Jelaskan idemu", d: "Satu kalimat sederhana sudah cukup. Contoh: 'Aplikasi kasir untuk warung'." },
            { icon: Zap, step: "2", t: "AI klarifikasi kebutuhan", d: "Beberapa pertanyaan cepat untuk memastikan produkmu tepat sasaran." },
            { icon: Layers, step: "3", t: "Blueprint lengkap keluar", d: "Dokumentasi, skema data, dan panduan teknis tersusun otomatis dan konsisten." },
            { icon: Wand2, step: "4", t: "Eksekusi bersama agent", d: "Salin prompt siap-pakai ke AI coding agent favoritmu — langsung jalan." },
          ].map((c) => (
            <Card key={c.t} className="h-full p-5">
              <div className="mb-3 flex items-center gap-3">
                <div className="grid h-9 w-9 place-items-center rounded-lg bg-[linear-gradient(135deg,var(--color-brand),var(--color-brand-2))] text-sm font-bold text-white">
                  {c.step}
                </div>
                <c.icon size={18} className="text-[var(--color-brand)]" />
              </div>
              <h3 className="text-sm font-semibold">{c.t}</h3>
              <p className="mt-1 text-xs leading-relaxed text-[var(--color-fg-muted)]">{c.d}</p>
            </Card>
          ))}
        </div>
      </section>

      {/* Benefits */}
      <section id="keunggulan" className="mx-auto max-w-6xl px-4 py-16">
        <SectionHead badge="Keunggulan" title="Kenapa ribuan ide jadi lebih cepat bangun" />
        <div className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {[
            { icon: Bolt, t: "Cepat", d: "Dari ide ke blueprint dalam hitungan menit, bukan hari." },
            { icon: Layers, t: "Lengkap & Terstruktur", d: "Semua keputusan terdokumentasi — siapa, apa, bagaimana — siap diimplementasi." },
            { icon: ShieldCheck, t: "AI-agnostic", d: "Prompt kompatibel untuk Claude, GPT, atau Cursor — kamu yang pilih." },
            { icon: Smartphone, t: "Web + Mobile", d: "Satu project, dua platform. Keputusan desain & teknis tetap konsisten." },
            { icon: RefreshCw, t: "Iteratif", d: "Kembangkan ke versi berikutnya tanpa kehilangan riwayat keputusan." },
            { icon: Globe, t: "Bawa AI Sendiri", d: "Pakai API key milikmu — aman tersimpan di server, bukan di browser." },
          ].map((f) => (
            <Card key={f.t} className="group p-6 transition hover:border-[color-mix(in_oklab,var(--color-brand)_50%,var(--color-border))]">
              <div className="mb-4 grid h-11 w-11 place-items-center rounded-xl bg-[color-mix(in_oklab,var(--color-brand)_16%,transparent)] text-[var(--color-brand)] transition group-hover:scale-105">
                <f.icon size={20} />
              </div>
              <h3 className="font-semibold">{f.t}</h3>
              <p className="mt-1.5 text-sm text-[var(--color-fg-muted)]">{f.d}</p>
            </Card>
          ))}
        </div>
      </section>

      {/* Testimonials */}
      <section className="mx-auto max-w-6xl px-4 py-16">
        <SectionHead badge="Testimoni" title="Yang mereka rasakan setelah beralih" />
        <div className="mt-10 grid gap-4 md:grid-cols-3">
          {[
            { name: "Budi Santoso", role: "Founder, aplikasi kasir UMKM", quote: "Dulu butuh 2 minggu nulis dokumen. Sekarang setengah hari sudah dapat blueprint lengkap dan langsung ngoding." },
            { name: "Citra Dewi", role: "Indie hacker, SaaS niche", quote: "Prompt-nya nyambung dari awal sampai akhir. AI agent saya gak pernah kehilangan konteks lagi." },
            { name: "Dani Pratama", role: "Agency lead", quote: "Tim saya kerja dari satu sumber yang sama. Komunikasi lintas role jadi jauh lebih minim salah paham." },
          ].map((t) => (
            <Card key={t.name} className="flex h-full flex-col p-6">
              <Quote size={18} className="mb-3 text-[var(--color-brand)]" />
              <p className="flex-1 text-sm leading-relaxed text-[var(--color-fg)]">“{t.quote}”</p>
              <div className="mt-5 flex items-center gap-3">
                <span className="grid h-9 w-9 place-items-center rounded-full bg-[linear-gradient(135deg,var(--color-brand),var(--color-brand-2))] text-xs font-bold text-white">
                  {t.name.split(" ").map((w) => w[0]).join("")}
                </span>
                <div>
                  <p className="text-sm font-semibold">{t.name}</p>
                  <p className="text-xs text-[var(--color-fg-muted)]">{t.role}</p>
                </div>
              </div>
            </Card>
          ))}
        </div>
      </section>

      {/* Platform */}
      <section className="mx-auto max-w-6xl px-4 py-16">
        <SectionHead badge="Platform" title="Bangun untuk semua target" />
        <div className="mt-10 grid gap-4 md:grid-cols-2">
          {[
            { icon: Globe, t: "Web App", d: "Dari landing page sederhana hingga SaaS multi-tenant dengan stack modern." },
            { icon: Smartphone, t: "Mobile App (APK / iOS)", d: "Aplikasi Flutter / React Native, offline-first, siap rilis ke store." },
          ].map((c) => (
            <Card key={c.t} className="relative overflow-hidden p-8">
              <div className="absolute -right-8 -top-8 h-32 w-32 rounded-full bg-[color-mix(in_oklab,var(--color-brand)_14%,transparent)] blur-2xl" />
              <div className="mb-4 grid h-12 w-12 place-items-center rounded-xl bg-[var(--color-surface-2)] text-[var(--color-brand)]">
                <c.icon size={22} />
              </div>
              <h3 className="text-xl font-semibold">{c.t}</h3>
              <p className="mt-2 text-[var(--color-fg-muted)]">{c.d}</p>
            </Card>
          ))}
        </div>
      </section>

      {/* CTA */}
      <section className="mx-auto max-w-6xl px-4 py-16">
        <Card className="relative overflow-hidden p-10 text-center sm:p-16">
          <div className="grid-pattern pointer-events-none absolute inset-0 -z-10 opacity-60" />
          <h2 className="mx-auto max-w-xl text-3xl font-bold sm:text-4xl">
            Mulai bangun idemu <span className="gradient-text">sekarang</span>
          </h2>
          <p className="mx-auto mt-4 max-w-lg text-[var(--color-fg-muted)]">
            Gratis untuk memulai, tanpa kartu kredit. Bawa AI provider sendiri dan keluar dari
            kebiasaan menulis dokumen manual.
          </p>
          <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
            <ButtonLink href="/register" size="lg" className="group">
              Mulai Sekarang <ArrowRight size={18} className="transition group-hover:translate-x-0.5" />
            </ButtonLink>
            <ButtonLink href="/dashboard" variant="secondary" size="lg">
              <Download size={16} /> Lihat Demo Dashboard
            </ButtonLink>
          </div>
        </Card>
      </section>

      {/* Footer */}
      <footer className="mt-auto border-t border-[var(--color-border)]">
        <div className="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-4 py-8 text-sm text-[var(--color-fg-muted)] sm:flex-row">
          <div className="flex items-center gap-2">
            <Sparkles size={15} className="text-[var(--color-brand)]" /> AI Planning Studio © 2026
          </div>
          <div className="flex items-center gap-4">
            <a href="/privacy" className="hover:text-[var(--color-fg)]">Privasi</a>
            <a href="/terms" className="hover:text-[var(--color-fg)]">Ketentuan</a>
            <a href="#" className="inline-flex items-center gap-1.5 hover:text-[var(--color-fg)]"><Star size={15} /> GitHub</a>
          </div>
        </div>
      </footer>
    </>
  );
}

function SectionHead({ badge, title }: { badge: string; title: string }) {
  return (
    <div className="mx-auto max-w-2xl text-center">
      <Badge tone="muted" className="mb-3">{badge}</Badge>
      <h2 className="text-3xl font-bold tracking-tight sm:text-4xl">{title}</h2>
    </div>
  );
}

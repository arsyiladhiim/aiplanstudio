import Link from "next/link";
import { ButtonLink } from "@/components/ui/Button";
import { Badge, Card } from "@/components/ui";
import { ThemeToggle } from "@/components/ThemeToggle";
import { STAGES } from "@/lib/mock";
import {
  Sparkles, ArrowRight, Wand2, Database, ListChecks, FileText,
  Smartphone, Globe, ShieldCheck, Zap, GitBranch, Star,
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
            <a href="#fitur" className="rounded-full px-3 py-2 text-sm text-[var(--color-fg-muted)] hover:text-[var(--color-fg)]">Fitur</a>
            <a href="#alur" className="rounded-full px-3 py-2 text-sm text-[var(--color-fg-muted)] hover:text-[var(--color-fg)]">Cara Kerja</a>
            <a href="#target" className="rounded-full px-3 py-2 text-sm text-[var(--color-fg-muted)] hover:text-[var(--color-fg)]">Platform</a>
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
            <br className="hidden sm:block" /> dokumentasi & prompt siap-pakai
          </h1>
          <p className="mt-6 max-w-2xl text-lg text-[var(--color-fg-muted)]">
            Rancang aplikasi <strong className="text-[var(--color-fg)]">Web</strong> maupun{" "}
            <strong className="text-[var(--color-fg)]">Mobile (APK / iOS)</strong> lewat wizard berbasis AI —
            PRD, ERD, hingga master prompt yang saling nyambung untuk disuapkan ke AI coding agent.
          </p>
          <div className="mt-8 flex flex-col gap-3 sm:flex-row">
            <ButtonLink href="/register" size="lg" className="group">
              Buat Plan Pertama <ArrowRight size={18} className="transition group-hover:translate-x-0.5" />
            </ButtonLink>
            <ButtonLink href="/dashboard" variant="secondary" size="lg">Lihat Demo Dashboard</ButtonLink>
          </div>
          <p className="mt-4 text-xs text-[var(--color-fg-subtle)]">Tanpa kartu kredit • Bawa AI Provider sendiri</p>
        </div>

        {/* Hero preview card */}
        <Card className="animate-fade-up mx-auto mt-14 max-w-4xl overflow-hidden p-0 text-left shadow-2xl">
          <div className="flex items-center gap-1.5 border-b border-[var(--color-border)] px-4 py-3">
            <span className="h-3 w-3 rounded-full bg-[#ff5f57]" />
            <span className="h-3 w-3 rounded-full bg-[#febc2e]" />
            <span className="h-3 w-3 rounded-full bg-[#28c840]" />
            <span className="ml-3 text-xs text-[var(--color-fg-subtle)]">aistack — Buat Plan</span>
          </div>
          <div className="grid gap-4 p-5 sm:grid-cols-3">
            {STAGES.map((s, i) => (
              <div key={s.key} className="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg-soft)] p-4">
                <div className="mb-2 flex items-center justify-between">
                  <span className="text-xs font-medium text-[var(--color-fg-subtle)]">Tahap {i + 1}</span>
                  <span className={`h-2 w-2 rounded-full ${i < 3 ? "bg-[var(--color-success)]" : i === 3 ? "bg-[var(--color-warning)] animate-pulse" : "bg-[var(--color-border)]"}`} />
                </div>
                <div className="text-sm font-semibold">{s.label}</div>
                <div className="mt-1 text-xs text-[var(--color-fg-muted)] line-clamp-2">{s.desc}</div>
              </div>
            ))}
          </div>
        </Card>
      </section>

      {/* Features */}
      <section id="fitur" className="mx-auto max-w-6xl px-4 py-16">
        <SectionHead badge="Fitur" title="Semua yang solo dev butuhkan untuk memulai" />
        <div className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {[
            { icon: Wand2, t: "Wizard 6 Tahap", d: "Analisa → PRD → Arsitektur → ERD → Phase → Master Prompt, dengan checkpoint tiap tahap." },
            { icon: Database, t: "ERD Otomatis", d: "Diagram database interaktif (React Flow) langsung dari kebutuhanmu." },
            { icon: FileText, t: "Prompt Nyambung", d: "Setiap prompt fase membawa konteks fase sebelumnya — AI agent tak kehilangan benang merah." },
            { icon: ListChecks, t: "Tracking Progress", d: "Checklist per fase & progress bar realtime tiap project." },
            { icon: GitBranch, t: "Versioning", d: "Kembangkan ke Versi 2, 3, … tanpa kehilangan riwayat plan sebelumnya." },
            { icon: ShieldCheck, t: "Bawa AI Sendiri", d: "Custom AI Provider (OpenAI-compatible) — key aman di server, bukan di browser." },
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

      {/* Flow */}
      <section id="alur" className="mx-auto max-w-6xl px-4 py-16">
        <SectionHead badge="Cara Kerja" title="Ide masuk, dokumentasi lengkap keluar" />
        <div className="mt-10 grid gap-4 md:grid-cols-6">
          {STAGES.map((s, i) => (
            <Card key={s.key} className="h-full p-5">
              <div className="mb-3 grid h-9 w-9 place-items-center rounded-lg bg-[linear-gradient(135deg,var(--color-brand),var(--color-brand-2))] text-sm font-bold text-white">
                {i + 1}
              </div>
              <div className="text-sm font-semibold">{s.label}</div>
              <div className="mt-1 text-xs text-[var(--color-fg-muted)]">{s.desc}</div>
            </Card>
          ))}
        </div>
      </section>

      {/* Target platform */}
      <section id="target" className="mx-auto max-w-6xl px-4 py-16">
        <div className="grid gap-4 md:grid-cols-2">
          {[
            { icon: Globe, t: "Web App", d: "Next.js, Laravel, dan stack modern. Dari SPA hingga SaaS multi-tenant." },
            { icon: Smartphone, t: "Mobile App (APK / iOS)", d: "Flutter / React Native, build APK & IPA, offline-first, submission store." },
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
            Berhenti menulis dokumen manual. <span className="gradient-text">Mulai bangun.</span>
          </h2>
          <p className="mx-auto mt-4 max-w-lg text-[var(--color-fg-muted)]">
            Biarkan AI menyiapkan blueprint lengkap, kamu fokus mengeksekusi bersama agent favoritmu.
          </p>
          <ButtonLink href="/register" size="lg" className="mt-8">
            Mulai Sekarang <ArrowRight size={18} />
          </ButtonLink>
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

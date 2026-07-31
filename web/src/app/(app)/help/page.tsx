import { PageHeader } from "@/components/common";
import { Card } from "@/components/ui";
import { HelpCircle, FileText, Smartphone, Layers, ArrowRight } from "lucide-react";
import Link from "next/link";

const FAQS = [
  { q: "Apa itu AI Plan Studio?", a: "Platform AI yang mengubah ide aplikasi menjadi blueprint lengkap — dari analisa, PRD, arsitektur, ERD, hingga phased master prompt siap pakai untuk AI coding agent." },
  { q: "Berapa lama proses pembuatan plan?", a: "Sekitar 1-3 menit tergantung kompleksitas. Ada 6-7 tahap yang berjalan berurutan." },
  { q: "Apa beda target Web, Mobile, dan Web+Mobile?", a: "Web: Next.js + Laravel. Mobile: Flutter APK. Web+Mobile: keduanya — pipeline menghasilkan dual master prompt." },
  { q: "Bagaimana cara menggunakan master prompt?", a: "Salin master prompt dari halaman project, lalu tempel ke AI coding agent (Cursor, Windsurf, GitHub Copilot)." },
  { q: "Apa itu STANDARDS.md dan AGENTS.md?", a: "File panduan yang mendefinisikan struktur kode, aturan, dan environment. Download dan letakkan di root project sebelum AI agent mulai coding." },
  { q: "Bagaimana cara tracking progress pembangunan?", a: "Gunakan API token di halaman project. AI agent mengirim webhook ke endpoint phase-complete untuk update progress real-time." },
];

export default function HelpPage() {
  return (
    <>
      <PageHeader
        title="Bantuan"
        subtitle="Pertanyaan umum seputar AI Plan Studio."
      />

      <div className="mx-auto max-w-3xl space-y-6">
        <Card className="p-6">
          <h2 className="mb-4 text-lg font-semibold">Cara Kerja</h2>
          <ol className="space-y-3 text-sm text-[var(--color-fg-muted)]">
            <li className="flex items-start gap-3">
              <span className="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-[var(--color-brand)] text-xs text-white">1</span>
              <span>Masukkan ide aplikasi kamu di halaman <Link href="/new" className="text-[var(--color-brand)] hover:underline">Buat Plan</Link></span>
            </li>
            <li className="flex items-start gap-3">
              <span className="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-[var(--color-brand)] text-xs text-white">2</span>
              <span>Pilih target platform: <FileText size={14} className="inline" /> Web, <Smartphone size={14} className="inline" /> Mobile, atau <Layers size={14} className="inline" /> Web+Mobile</span>
            </li>
            <li className="flex items-start gap-3">
              <span className="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-[var(--color-brand)] text-xs text-white">3</span>
              <span>Pipeline otomatis menjalankan tahap klarifikasi → analisa → PRD → arsitektur → ERD → master prompt</span>
            </li>
            <li className="flex items-start gap-3">
              <span className="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-[var(--color-brand)] text-xs text-white">4</span>
              <span>Approve tiap tahap dan dapatkan output lengkap untuk coding</span>
            </li>
          </ol>
        </Card>

        <Card className="p-6">
          <h2 className="mb-4 text-lg font-semibold">FAQ</h2>
          <div className="space-y-4">
            {FAQS.map((faq, i) => (
              <details key={i} className="group rounded-lg border border-[var(--color-border)]">
                <summary className="flex cursor-pointer items-center justify-between px-4 py-3 text-sm font-medium">
                  {faq.q}
                  <ArrowRight size={14} className="transition group-open:rotate-90" />
                </summary>
                <p className="border-t border-[var(--color-border)] px-4 py-3 text-sm text-[var(--color-fg-muted)]">{faq.a}</p>
              </details>
            ))}
          </div>
        </Card>

        <Card className="p-6 text-center">
          <HelpCircle size={24} className="mx-auto mb-2 text-[var(--color-fg-muted)]" />
          <p className="text-sm text-[var(--color-fg-muted)]">Masih butuh bantuan? Hubungi tim support melalui email atau buka issue di repository.</p>
        </Card>
      </div>
    </>
  );
}

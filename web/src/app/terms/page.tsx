import type { Metadata } from "next";
import Link from "next/link";
import { Sparkles, ArrowLeft } from "lucide-react";

export const metadata: Metadata = {
  title: "Syarat & Ketentuan — AI Planning Studio",
};

export default function TermsPage() {
  return (
    <div className="mx-auto max-w-3xl px-4 py-12">
      <Link href="/" className="mb-6 inline-flex items-center gap-1.5 text-sm text-[var(--color-fg-muted)] hover:text-[var(--color-fg)]">
        <ArrowLeft size={14} /> Kembali
      </Link>

      <div className="mb-8 flex items-center gap-2">
        <span className="grid h-8 w-8 place-items-center rounded-lg bg-[linear-gradient(135deg,var(--color-brand),var(--color-brand-2))] text-white">
          <Sparkles size={16} />
        </span>
        <span className="font-semibold">AI Planning Studio</span>
      </div>

      <h1 className="text-3xl font-bold tracking-tight sm:text-4xl">Syarat & Ketentuan</h1>
      <p className="mt-2 text-sm text-[var(--color-fg-muted)]">Terakhir diperbarui: Juli 2026</p>

      <div className="mt-8 space-y-8 text-sm leading-relaxed text-[var(--color-fg)]">
        <section>
          <h2 className="mb-3 text-lg font-semibold">1. Penerimaan</h2>
          <p className="text-[var(--color-fg-muted)]">
            Dengan menggunakan AI Planning Studio, Anda menyetujui syarat dan ketentuan ini. Jika tidak setuju, jangan gunakan layanan kami.
          </p>
        </section>

        <section>
          <h2 className="mb-3 text-lg font-semibold">2. Akun</h2>
          <p className="text-[var(--color-fg-muted)]">
            Anda bertanggung jawab menjaga kerahasiaan kredensial akun. Setiap aktivitas yang terjadi di bawah akun Anda
            adalah tanggung jawab Anda. Anda harus berusia minimal 18 tahun atau memiliki izin orang tua.
          </p>
        </section>

        <section>
          <h2 className="mb-3 text-lg font-semibold">3. Lisensi Penggunaan</h2>
          <p className="text-[var(--color-fg-muted)]">
            Kami memberi Anda lisensi terbatas untuk menggunakan platform sesuai dengan fungsinya. Output yang dihasilkan
            (dokumentasi, prompt, dll.) sepenuhnya milik Anda dan dapat digunakan untuk tujuan komersial.
          </p>
        </section>

        <section>
          <h2 className="mb-3 text-lg font-semibold">4. Batasan</h2>
          <p className="text-[var(--color-fg-muted)]">
            Anda tidak diperbolehkan: menyalahgunakan API, mencoba mengakses data pengguna lain, menggunakan platform
            untuk aktivitas ilegal, atau melakukan rekayasa balik terhadap sistem kami.
          </p>
        </section>

        <section>
          <h2 className="mb-3 text-lg font-semibold">5. Layanan & AI Provider</h2>
          <p className="text-[var(--color-fg-muted)]">
            Kami menyediakan platform sebagai perantara — Anda membawa sendiri AI provider (OpenAI-compatible). Kualitas
            output tergantung pada provider yang Anda pilih. Kami tidak bertanggung jawab atas konten yang dihasilkan AI.
          </p>
        </section>

        <section>
          <h2 className="mb-3 text-lg font-semibold">6. Penghentian</h2>
          <p className="text-[var(--color-fg-muted)]">
            Kami dapat menghentikan akses Anda jika melanggar syarat ini. Anda dapat menghentikan penggunaan kapan saja
            dengan menghapus akun. Data Anda akan dihapus dalam waktu 30 hari setelah penghentian.
          </p>
        </section>

        <section>
          <h2 className="mb-3 text-lg font-semibold">7. Batasan Tanggung Jawab</h2>
          <p className="text-[var(--color-fg-muted)]">
            Platform disediakan &ldquo;sebagaimana adanya&rdquo; tanpa jaminan tersirat. Kami tidak bertanggung jawab atas
            kerugian tidak langsung yang timbul dari penggunaan platform, termasuk downtime atau kehilangan data.
          </p>
        </section>

        <section>
          <h2 className="mb-3 text-lg font-semibold">8. Perubahan</h2>
          <p className="text-[var(--color-fg-muted)]">
            Syarat ini dapat diperbarui sewaktu-waktu. Perubahan akan diberitahukan melalui email atau pengumuman di platform.
            Penggunaan lanjutan setelah perubahan berarti persetujuan Anda.
          </p>
        </section>
      </div>
    </div>
  );
}

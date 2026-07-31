import type { Metadata } from "next";
import Link from "next/link";
import { Sparkles, ArrowLeft } from "lucide-react";

export const metadata: Metadata = {
  title: "Kebijakan Privasi — AI Planning Studio",
};

export default function PrivacyPage() {
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

      <h1 className="text-3xl font-bold tracking-tight sm:text-4xl">Kebijakan Privasi</h1>
      <p className="mt-2 text-sm text-[var(--color-fg-muted)]">Terakhir diperbarui: Juli 2026</p>

      <div className="mt-8 space-y-8 text-sm leading-relaxed text-[var(--color-fg)]">
        <section>
          <h2 className="mb-3 text-lg font-semibold">1. Data yang Kami Kumpulkan</h2>
          <p className="text-[var(--color-fg-muted)]">
            Kami mengumpulkan data yang Anda berikan saat mendaftar, termasuk nama, alamat email, dan password yang dienkripsi.
            Kami juga menyimpan data project, versi, jawaban klarifikasi, dan output AI yang Anda hasilkan melalui platform.
          </p>
        </section>

        <section>
          <h2 className="mb-3 text-lg font-semibold">2. Penggunaan Data</h2>
          <p className="text-[var(--color-fg-muted)]">
            Data Anda digunakan untuk menjalankan layanan inti platform: menghasilkan dokumentasi dan prompt AI berdasarkan input Anda.
            Kami tidak menggunakan data Anda untuk melatih model AI di luar konteks project Anda sendiri.
          </p>
        </section>

        <section>
          <h2 className="mb-3 text-lg font-semibold">3. Penyimpanan & Keamanan</h2>
          <p className="text-[var(--color-fg-muted)]">
            Semua data disimpan di server dengan enkripsi. Koneksi diamankan dengan HTTPS. API key AI provider Anda dienkripsi
            di database dan tidak pernah terekspos ke browser. Kami menerapkan backup rutin dan akses terbatas ke infrastruktur.
          </p>
        </section>

        <section>
          <h2 className="mb-3 text-lg font-semibold">4. Berbagi Data</h2>
          <p className="text-[var(--color-fg-muted)]">
            Kami tidak menjual data pribadi Anda. Data project bersifat privat dan hanya dapat diakses oleh Anda.
            Kami dapat membagikan data jika diwajibkan oleh hukum atau untuk melindungi hak hukum kami.
          </p>
        </section>

        <section>
          <h2 className="mb-3 text-lg font-semibold">5. Hak Pengguna</h2>
          <p className="text-[var(--color-fg-muted)]">
            Anda berhak mengakses, memperbarui, mengekspor, dan menghapus data Anda kapan saja melalui dashboard pengaturan.
            Penghapusan akun akan menghapus semua data terkait secara permanen.
          </p>
        </section>

        <section>
          <h2 className="mb-3 text-lg font-semibold">6. Cookie</h2>
          <p className="text-[var(--color-fg-muted)]">
            Kami menggunakan cookie sesi untuk autentikasi (HttpOnly) dan cookie CSRF untuk keamanan. Tidak ada cookie pelacakan
            atau iklan pihak ketiga.
          </p>
        </section>

        <section>
          <h2 className="mb-3 text-lg font-semibold">7. Kontak</h2>
          <p className="text-[var(--color-fg-muted)]">
            Jika ada pertanyaan tentang kebijakan privasi ini, silakan hubungi tim kami melalui email atau buka issue di repository GitHub.
          </p>
        </section>
      </div>
    </div>
  );
}

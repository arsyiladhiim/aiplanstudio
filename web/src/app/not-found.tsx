import Link from "next/link";

export default function NotFound() {
  return (
    <div className="flex min-h-screen flex-col items-center justify-center gap-4 px-4 text-center">
      <div className="text-6xl font-bold text-[var(--color-brand)]">404</div>
      <h1 className="text-2xl font-semibold">Halaman tidak ditemukan</h1>
      <p className="max-w-md text-[var(--color-fg-muted)]">Halaman yang kamu cari tidak ada atau telah dipindahkan.</p>
      <Link
        href="/"
        className="mt-4 inline-flex items-center gap-2 rounded-xl bg-[var(--color-brand)] px-5 py-2.5 text-sm font-medium text-white transition hover:opacity-90"
      >
        Kembali ke Beranda
      </Link>
    </div>
  );
}

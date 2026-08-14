import Link from "next/link";
import { Sparkles } from "lucide-react";
import { ThemeToggle } from "@/components/ThemeToggle";

export default function AuthLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="grid min-h-screen lg:grid-cols-2">
      {/* Form side */}
      <div className="flex flex-col px-6 py-8 sm:px-12">
        <div className="flex items-center justify-between">
          <Link href="/" className="flex items-center gap-2 font-semibold">
            <span className="grid h-8 w-8 place-items-center rounded-xl bg-[linear-gradient(135deg,var(--color-brand),var(--color-brand-2))] text-white shadow-sm">
              <Sparkles size={16} />
            </span>
            AI Planning Studio
          </Link>
          <ThemeToggle />
        </div>
        <div className="flex flex-1 items-center justify-center">
          <div className="w-full max-w-sm animate-fade-up">{children}</div>
        </div>
      </div>

      {/* Visual side */}
      <div className="relative hidden overflow-hidden lg:block">
        <div className="absolute inset-0 bg-[linear-gradient(135deg,color-mix(in_oklab,var(--color-brand)_30%,var(--color-bg)),var(--color-bg))]" />
        <div className="grid-pattern absolute inset-0 opacity-40" />
        <div className="absolute left-1/2 top-1/2 h-[36rem] w-[36rem] -translate-x-1/2 -translate-y-1/2 rounded-full bg-[color-mix(in_oklab,var(--color-brand-2)_28%,transparent)] blur-[100px]" />
        <div className="relative flex h-full flex-col justify-end p-12">
          <blockquote className="max-w-md text-2xl font-medium leading-snug">
            &ldquo;Dari ide di kepala jadi blueprint lengkap dalam hitungan menit — lalu tinggal disuapkan ke AI agent.&rdquo;
          </blockquote>
          <p className="mt-4 text-sm text-[var(--color-fg-muted)]">Alur kerja solo developer modern.</p>
        </div>
      </div>
    </div>
  );
}

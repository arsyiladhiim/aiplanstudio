"use client";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useState, useCallback, useEffect } from "react";
import dynamic from "next/dynamic";
import { ThemeToggle } from "@/components/ThemeToggle";
import { Button, ButtonLink } from "@/components/ui/Button";
import { apiPost } from "@/lib/api";
import { useUser } from "@/components/UserContext";
import { ToastProvider } from "@/components/Toast";
import { CommandPalette } from "@/components/CommandPalette";
import { Footer } from "@/components/Footer";
import {
  Sparkles, LayoutDashboard, FolderKanban, Wand2, LayoutTemplate,
  Settings, Menu, X, Plus, LogOut, Search, Star, Archive, Bell, BellOff,
} from "lucide-react";

const LiveProgressWidget = dynamic(
  () => import("@/components/LiveProgressWidget").then(m => ({ default: m.LiveProgressWidget })),
  { ssr: false },
);

const WhatsNewModal = dynamic(
  () => import("@/components/WhatsNewModal").then(m => ({ default: m.WhatsNewModal })),
  { ssr: false },
);

const OnboardingTour = dynamic(
  () => import("@/components/OnboardingTour").then(m => ({ default: m.OnboardingTour })),
  { ssr: false },
);

const nav = [
  { href: "/dashboard", label: "Dashboard", icon: LayoutDashboard },
  { href: "/projects", label: "Projects", icon: FolderKanban },
  { href: "/projects?pinned=1", label: "Favorit", icon: Star },
  { href: "/projects/archived", label: "Arsip", icon: Archive },
  { href: "/new", label: "Buat Plan", icon: Wand2 },
  { href: "/templates", label: "Templates", icon: LayoutTemplate },
  { href: "/settings/provider", label: "Settings", icon: Settings, match: "/settings" },
];
export function AppShell({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const router = useRouter();
  const [open, setOpen] = useState(false);
  const [searchQ, setSearchQ] = useState("");
  const [chimeOn, setChimeOn] = useState<boolean>(true);
  const { user } = useUser();

  useEffect(() => {
    import("@/lib/chime").then(m => setChimeOn(m.isChimeEnabled()));
  }, []);

  const toggleChime = useCallback(() => {
    import("@/lib/chime").then(m => {
      const next = !m.isChimeEnabled();
      m.setChimeEnabled(next);
      setChimeOn(next);
    });
  }, []);

  const handleSearch = useCallback((e: React.FormEvent) => {
    e.preventDefault();
    const q = searchQ.trim();
    if (q) router.push(`/projects?q=${encodeURIComponent(q)}`);
    else router.push("/projects");
  }, [searchQ, router]);

  const isActive = (item: (typeof nav)[number]) =>
    pathname === item.href || (item.match !== undefined && pathname.startsWith(item.match));

  async function logout() {
    try {
      await apiPost("/logout");
    } catch {
      // proceed to login page regardless
    }
    router.push("/login");
  }

  return (
    <div className="flex min-h-screen">
      <aside
        className={`glass fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-[var(--color-border)] p-4 transition-transform lg:static lg:translate-x-0 ${open ? "translate-x-0" : "-translate-x-full"}`}
      >
        <div className="flex items-center justify-between">
          <Link href="/dashboard" className="flex items-center gap-2 font-semibold">
            <span className="grid h-8 w-8 place-items-center rounded-xl bg-[linear-gradient(135deg,var(--color-brand),var(--color-brand-2))] text-white shadow-sm">
              <Sparkles size={16} />
            </span>
            AI Studio
          </Link>
          <button className="lg:hidden" onClick={() => setOpen(false)} aria-label="Tutup menu"><X size={20} /></button>
        </div>

        <ButtonLink href="/new" size="sm" className="mt-6" data-testid="nav-new-plan" data-onboarding="new-plan">
          <Plus size={16} /> Buat Plan Baru
        </ButtonLink>

        <nav className="mt-6 flex flex-1 flex-col gap-1">
          {nav.map((item) => (
            <Link
              key={item.href}
              href={item.href}
              onClick={() => setOpen(false)}
              data-testid={`nav-${item.label.toLowerCase().replace(/\s/g, "-")}`}
              data-onboarding={item.href === "/projects" ? "projects-nav" : item.href === "/settings/provider" ? "settings-nav" : undefined}
              className={`flex items-center gap-3 rounded-[var(--radius)] px-3 py-2.5 text-sm font-medium transition ${
                isActive(item)
                  ? "bg-[color-mix(in_oklab,var(--color-brand)_16%,transparent)] text-[var(--color-brand)]"
                  : "text-[var(--color-fg-muted)] hover:bg-[var(--color-surface-2)] hover:text-[var(--color-fg)]"
              }`}
            >
              <item.icon size={18} /> {item.label}
            </Link>
          ))}
        </nav>

        <div className="mt-auto border-t border-[var(--color-border)] pt-4">
          <div className="flex items-center gap-3 rounded-[var(--radius)] px-2 py-2">
            <div className="grid h-9 w-9 place-items-center rounded-full bg-[linear-gradient(135deg,color-mix(in_oklab,var(--color-brand)_22%,transparent),color-mix(in_oklab,var(--color-brand-2)_22%,transparent))] text-sm font-semibold text-[var(--color-brand)]">
              {(user?.name ?? "A").charAt(0).toUpperCase()}
            </div>
            <div className="min-w-0 flex-1">
              <div className="truncate text-sm font-medium">{user?.name ?? "Admin"}</div>
              <div className="truncate text-xs text-[var(--color-fg-subtle)]">{user?.role ?? "admin"}</div>
            </div>
            <Button variant="ghost" size="icon" onClick={logout} aria-label="Keluar"><LogOut size={16} /></Button>
          </div>
        </div>
      </aside>

      {open && <div className="fixed inset-0 z-40 bg-black/50 lg:hidden" onClick={() => setOpen(false)} />}

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="glass sticky top-0 z-30 flex items-center gap-3 border-b border-[var(--color-border)] px-4 py-3">
          <button className="lg:hidden" onClick={() => setOpen(true)} aria-label="Buka menu" data-testid="menu-open"><Menu size={22} /></button>
          <form onSubmit={handleSearch} className="relative hidden flex-1 sm:block" data-onboarding="search">
            <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-[var(--color-fg-subtle)]" />
            <input
              placeholder="Cari project…"
              value={searchQ}
              onChange={e => setSearchQ(e.target.value)}
              className="h-10 w-full max-w-xs rounded-full border border-[var(--color-border)] bg-[var(--color-bg-soft)] pl-9 pr-4 text-sm outline-none focus:border-[var(--color-brand)]"
            />
          </form>
          <div className="ml-auto flex items-center gap-2">
            <button
              type="button"
              onClick={toggleChime}
              title={chimeOn ? "Matikan chime" : "Nyalakan chime"}
              aria-label={chimeOn ? "Matikan chime" : "Nyalakan chime"}
              className="grid h-9 w-9 place-items-center rounded-full border border-[var(--color-border)] bg-[var(--color-bg-soft)] text-[var(--color-fg-muted)] transition hover:bg-[var(--color-surface)]"
            >
              {chimeOn ? <Bell size={16} /> : <BellOff size={16} />}
            </button>
            <ThemeToggle />
            <ButtonLink variant="secondary" size="sm" href="/help">Bantuan</ButtonLink>
          </div>
        </header>
        <main className="flex-1 p-4 sm:p-6 lg:p-8"><ToastProvider>{children}<CommandPalette /></ToastProvider>
        </main>
        <Footer />
      </div>
      <LiveProgressWidget />
      <WhatsNewModal />
      <OnboardingTour />
    </div>
  );
}

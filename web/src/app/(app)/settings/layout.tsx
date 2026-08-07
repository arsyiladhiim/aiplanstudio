"use client";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { PageHeader } from "@/components/common";
import { Cpu, Users, User } from "lucide-react";
import { useUser } from "@/components/UserContext";

export default function SettingsLayout({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const { user } = useUser();

  const baseTabs = [
    { href: "/settings/profile", label: "Profile", icon: User },
    { href: "/settings/provider", label: "AI Provider", icon: Cpu },
    { href: "/settings/users", label: "User Management", icon: Users },
  ];
  const tabs = user?.role === "admin" ? baseTabs : baseTabs.filter((t) => t.href === "/settings/profile");

  return (
    <>
      <PageHeader title="Settings" subtitle="Kelola profil dan pengaturan akun." />
      <div className="flex gap-2 border-b border-[var(--color-border)]">
        {tabs.map((t) => {
          const active = pathname === t.href;
          return (
            <Link
              key={t.href}
              href={t.href}
              data-testid={`settings-tab-${t.label.toLowerCase().replace(/\s/g, "-")}`}
              className={`flex items-center gap-2 px-4 py-2.5 text-sm font-medium transition ${
                active
                  ? "border-b-2 border-[var(--color-brand)] text-[var(--color-fg)]"
                  : "text-[var(--color-fg-muted)] hover:text-[var(--color-fg)]"
              }`}
            >
              <t.icon size={16} /> {t.label}
            </Link>
          );
        })}
      </div>
      <div className="mt-6 max-w-2xl">{children}</div>
    </>
  );
}

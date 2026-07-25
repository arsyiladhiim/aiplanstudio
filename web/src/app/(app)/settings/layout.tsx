"use client";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { PageHeader } from "@/components/common";
import { Cpu, Users } from "lucide-react";

const tabs = [
  { href: "/settings/provider", label: "AI Provider", icon: Cpu },
  { href: "/settings/users", label: "User Management", icon: Users },
];

export default function SettingsLayout({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  return (
    <>
      <PageHeader title="Settings" subtitle="Kelola AI Provider dan pengguna." />
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

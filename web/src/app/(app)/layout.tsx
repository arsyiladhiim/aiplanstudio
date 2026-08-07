import { AppShell } from "@/components/AppShell";
import { UserProvider } from "@/components/UserContext";

export default function AppGroupLayout({ children }: { children: React.ReactNode }) {
  return (
    <UserProvider>
      <AppShell>{children}</AppShell>
    </UserProvider>
  );
}

import { Suspense } from "react";
import LoginForm from "./login-form";

export default function LoginPage() {
  return (
    <Suspense fallback={<div className="text-sm text-[var(--color-fg-muted)]">Memuat…</div>}>
      <LoginForm />
    </Suspense>
  );
}

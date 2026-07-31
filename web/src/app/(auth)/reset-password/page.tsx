import { Suspense } from "react";
import ResetPasswordForm from "./reset-password-form";

export default function ResetPasswordPage() {
  return (
    <Suspense fallback={<div className="text-sm text-[var(--color-fg-muted)]">Memuat…</div>}>
      <ResetPasswordForm />
    </Suspense>
  );
}

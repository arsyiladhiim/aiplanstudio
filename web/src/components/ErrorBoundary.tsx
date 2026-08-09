"use client";
import { Component, type ReactNode } from "react";

type Props = { children: ReactNode };
type State = { error: Error | null };

/** ErrorBoundary ringan: tampilkan pesan error asli + tombol Muat Ulang. */
export class ErrorBoundary extends Component<Props, State> {
  state: State = { error: null };

  static getDerivedStateFromError(error: Error): State {
    return { error };
  }

  componentDidCatch(error: Error, info: unknown): void {
    console.error("[ErrorBoundary]", error.message, info);
  }

  render() {
    if (this.state.error) {
      return (
        <div className="rounded-xl border border-[var(--color-danger)]/40 bg-[var(--color-danger)]/10 p-6 text-center">
          <h2 className="text-lg font-semibold text-[var(--color-danger)]">Terjadi Kesalahan</h2>
          <p className="mt-1 text-sm text-[var(--color-fg-muted)]">
            Terjadi kesalahan yang tidak terduga. Silakan coba lagi.
          </p>
          <p className="mt-3 break-all rounded bg-[var(--color-surface-1)] p-2 text-left font-mono text-xs text-[var(--color-fg-muted)]">
            {this.state.error.message}
          </p>
          <button
            onClick={() => this.setState({ error: null })}
            className="mt-4 inline-flex items-center gap-2 rounded-lg bg-[var(--color-accent)] px-4 py-2 text-sm font-medium text-white hover:opacity-90"
          >
            Coba Lagi
          </button>
        </div>
      );
    }
    return this.props.children;
  }
}
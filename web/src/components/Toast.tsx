"use client";
import { createContext, useContext, useState, useCallback, type ReactNode } from "react";
import { X, Check, AlertCircle, Info } from "lucide-react";

type ToastType = "success" | "error" | "info";

interface Toast {
  id: number;
  type: ToastType;
  message: string;
}

interface ToastContextValue {
  toast: (type: ToastType, message: string) => void;
}

const ToastContext = createContext<ToastContextValue | null>(null);

let nextId = 0;

export function ToastProvider({ children }: { children: ReactNode }) {
  const [toasts, setToasts] = useState<Toast[]>([]);

  const addToast = useCallback((type: ToastType, message: string) => {
    const id = nextId++;
    setToasts(prev => {
      // P3: cap di 3 toasts; drop oldest jika over.
      const next = [...prev, { id, type, message }];
      return next.length > 3 ? next.slice(next.length - 3) : next;
    });
    setTimeout(() => {
      setToasts(prev => prev.filter(t => t.id !== id));
    }, 4000);
  }, []);

  const removeToast = useCallback((id: number) => {
    setToasts(prev => prev.filter(t => t.id !== id));
  }, []);

  return (
    <ToastContext.Provider value={{ toast: addToast }}>
      {children}
      <div className="fixed bottom-4 right-4 z-[100] flex flex-col gap-2" role="status" aria-live="polite">
        {toasts.map(t => {
          const bgMap: Record<ToastType, string> = {
            success: "border-green-500/40 bg-green-600 text-white",
            error: "border-red-500/40 bg-red-600 text-white",
            info: "border-[var(--color-brand)]/40 bg-[var(--color-brand)] text-white",
          };
          const iconMap: Record<ToastType, ReactNode> = {
            success: <Check size={16} />,
            error: <AlertCircle size={16} />,
            info: <Info size={16} />,
          };
          return (
            <div
              key={t.id}
              className={`flex items-center gap-3 rounded-xl border px-4 py-3 text-sm shadow-lg transition-all animate-in slide-in-from-right ${bgMap[t.type]}`}
            >
              {iconMap[t.type]}
              <span className="flex-1">{t.message}</span>
              <button onClick={() => removeToast(t.id)} aria-label="Tutup" className="opacity-70 hover:opacity-100">
                <X size={14} />
              </button>
            </div>
          );
        })}
      </div>
    </ToastContext.Provider>
  );
}

export function useToast(): ToastContextValue {
  const ctx = useContext(ToastContext);
  if (!ctx) throw new Error("useToast must be used within ToastProvider");
  return ctx;
}

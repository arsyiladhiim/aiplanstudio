"use client"
import { useEffect, useRef, useId, type ReactNode } from "react"
import { X } from "lucide-react"
import { cx } from "./cva"

type ModalSize = "sm" | "md" | "lg" | "xl"

const sizes: Record<ModalSize, string> = {
  sm: "max-w-md",
  md: "max-w-lg",
  lg: "max-w-2xl",
  xl: "max-w-4xl",
}

type ModalProps = {
  open: boolean
  onClose: () => void
  title: string
  children: ReactNode
  size?: ModalSize
  className?: string
  closeOnBackdrop?: boolean
}

export function Modal({
  open,
  onClose,
  title,
  children,
  size = "md",
  className,
  closeOnBackdrop = true,
}: ModalProps) {
  const dialogRef = useRef<HTMLDivElement>(null)
  const onCloseRef = useRef(onClose)
  const titleId = useId()

  useEffect(() => {
    onCloseRef.current = onClose
  }, [onClose])

  useEffect(() => {
    if (!open) return
    const prevActive = document.activeElement as HTMLElement | null
    const dialog = dialogRef.current
    if (!dialog) return

    const focusable = dialog.querySelectorAll<HTMLElement>(
      'button, a[href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    )
    const first = focusable[0]
    first?.focus()

    const handleKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") {
        e.preventDefault()
        onCloseRef.current?.()
        return
      }
      if (e.key !== "Tab" || focusable.length === 0) return
      const active = document.activeElement as HTMLElement
      const firstEl = focusable[0]
      const lastEl = focusable[focusable.length - 1]
      if (e.shiftKey && active === firstEl) {
        e.preventDefault()
        lastEl.focus()
      } else if (!e.shiftKey && active === lastEl) {
        e.preventDefault()
        firstEl.focus()
      }
    }

    document.addEventListener("keydown", handleKey)
    document.body.style.overflow = "hidden"

    return () => {
      document.removeEventListener("keydown", handleKey)
      document.body.style.overflow = ""
      prevActive?.focus?.()
    }
  }, [open])

  if (!open) return null

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm"
      onClick={closeOnBackdrop ? onClose : undefined}
      role="presentation"
    >
      <div
        ref={dialogRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        className={cx(
          "mx-4 max-h-[90vh] w-full overflow-y-auto rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-6 shadow-2xl",
          sizes[size],
          className
        )}
        onClick={(e) => e.stopPropagation()}
      >
        <div className="mb-4 flex items-center justify-between gap-3">
          <h3
            id={titleId}
            className="min-w-0 text-lg font-semibold break-words"
          >
            {title}
          </h3>
          <button
            onClick={onClose}
            aria-label="Tutup"
            className="text-[var(--color-fg-muted)] hover:text-[var(--color-fg)]"
          >
            <X size={18} />
          </button>
        </div>
        {children}
      </div>
    </div>
  )
}

"use client";
import { Modal } from "./Modal";
import { Button } from "./Button";
import { AlertCircle } from "lucide-react";

type ConfirmDialogProps = {
  open: boolean;
  onClose: () => void;
  onConfirm: () => void;
  title: string;
  message: string;
  confirmLabel?: string;
  cancelLabel?: string;
  variant?: "danger" | "primary";
};

export function ConfirmDialog({
  open,
  onClose,
  onConfirm,
  title,
  message,
  confirmLabel = "Ya, Lanjutkan",
  cancelLabel = "Batal",
  variant = "danger",
}: ConfirmDialogProps) {
  return (
    <Modal open={open} onClose={onClose} title={title} size="sm">
      <div className="mt-2 flex items-start gap-3">
        {variant === "danger" && (
          <div className="shrink-0 rounded-lg bg-[var(--color-danger)]/10 p-2 text-[var(--color-danger)]">
            <AlertCircle size={20} />
          </div>
        )}
        <p className="text-sm text-[var(--color-fg-muted)]">{message}</p>
      </div>
      <div className="mt-5 flex justify-end gap-2">
        <Button variant="secondary" size="sm" onClick={onClose}>{cancelLabel}</Button>
        <Button variant={variant} size="sm" onClick={onConfirm}>{confirmLabel}</Button>
      </div>
    </Modal>
  );
}

"use client";
import { Badge, Button, Card } from "@/components/ui";
import { AlertTriangle, Check, ClipboardCopy } from "lucide-react";
import { useState } from "react";

export interface WizardErrorProps {
  message: string;
  stage?: string;
  versionId?: number;
  runId?: string;
  retryAttempt?: number;
  gateState?: { gate: string; reason: string } | null;
}

/**
 * CP-46.D step 5 + CP-46.G9 — error UI dengan diagnostic pack "Salin".
 */
export function WizardError({
  message,
  stage,
  versionId,
  runId,
  retryAttempt,
  gateState,
}: WizardErrorProps) {
  const [copied, setCopied] = useState(false);

  const diagnostic = JSON.stringify(
    {
      ts: new Date().toISOString(),
      version_id: versionId,
      stage,
      run_id: runId,
      retry_attempt: retryAttempt,
      gate: gateState,
      message,
    },
    null,
    2
  );

  async function copyDiagnostic() {
    try {
      await navigator.clipboard.writeText(diagnostic);
      setCopied(true);
      setTimeout(() => setCopied(false), 1500);
    } catch {
      // silent
    }
  }

  return (
    <Card
      data-testid="wizard-error"
      className="border-rose-500/40 bg-rose-500/5 p-4 text-sm"
    >
      <div className="flex items-start gap-3">
        <AlertTriangle size={16} className="mt-0.5 shrink-0 text-rose-500" />
        <div className="flex-1 space-y-2">
          <div className="font-medium text-rose-600">
            {stage ? `Stage "${stage}" gagal` : "Pipeline gagal"}
          </div>
          <div className="text-[var(--color-fg-muted)]">{message}</div>
          {gateState && (
            <div className="flex flex-wrap items-center gap-2">
              <Badge tone="warning">{gateState.gate}</Badge>
              <span className="text-xs text-[var(--color-fg-muted)]">
                {gateState.reason}
              </span>
            </div>
          )}
          <div className="flex justify-end pt-1">
            <Button
              variant="outline"
              size="sm"
              onClick={copyDiagnostic}
              data-testid="copy-diagnostic"
            >
              {copied ? <Check size={12} /> : <ClipboardCopy size={12} />}
              {copied ? "Disalin" : "Salin Diagnostic"}
            </Button>
          </div>
        </div>
      </div>
    </Card>
  );
}

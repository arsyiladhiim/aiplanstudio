import { Button } from "@/components/ui/Button";
import { ArrowRight } from "lucide-react";
import type { McqData, McqQuestion, McqAnswer } from "@/lib/api";

export function McqForm({
  mcqData,
  answers,
  onAnswerChange,
  onSubmit,
  submitLabel,
  ambiguitiesLabel,
}: {
  mcqData: McqData;
  answers: Record<string, McqAnswer>;
  onAnswerChange: (qId: string, answer: McqAnswer) => void;
  onSubmit: () => void;
  submitLabel: string;
  ambiguitiesLabel?: string;
}) {
  return (
    <div className="space-y-4">
      {mcqData.ambiguities.length > 0 && (
        <div className="mb-2 rounded-lg border border-amber-500/30 bg-amber-500/10 p-3">
          <p className="mb-1 text-xs font-semibold text-amber-600">
            {ambiguitiesLabel ?? "Area yang perlu diperjelas:"}
          </p>
          <ul className="space-y-0.5">
            {mcqData.ambiguities.map((a, i) => (
              <li key={i} className="text-xs text-amber-700">• {a}</li>
            ))}
          </ul>
        </div>
      )}
      {mcqData.questions.map((q: McqQuestion, i: number) => {
        // Guard: skip item rusak (question kosong / options bukan array) agar tidak tampil
        // nomor kosong di tengah list. Backend sudah filter saat simpan; ini guard lapis kedua.
        const qText = typeof q.question === "string" ? q.question.trim() : "";
        const options = Array.isArray(q.options) ? q.options : [];
        if (qText === "" || options.length === 0) return null;
        return (
        <div key={q.id || i} className="rounded-xl border border-[var(--color-border)] p-4">
          <p className="mb-3 font-medium" id={`mcq-q-${q.id}`}>{i + 1}. {qText}</p>
          <div className="space-y-2" role="radiogroup" aria-labelledby={`mcq-q-${q.id}`}>
            {options.map((opt) => {
              const isSelected = answers[q.id]?.selected === opt.key;
              return (
                <button
                  key={opt.key}
                  role="radio"
                  aria-checked={isSelected}
                  onClick={() => onAnswerChange(q.id, { selected: opt.key, custom_text: opt.key === "E" ? "" : undefined })}
                  className={`w-full rounded-lg border p-3 text-left text-sm transition ${
                    isSelected
                      ? "border-[var(--color-brand)] bg-[color-mix(in_oklab,var(--color-brand)_10%,transparent)]"
                      : "border-[var(--color-border)] hover:border-[var(--color-brand)]/50"
                  }`}
                >
                  <span className="mr-2 font-mono text-xs font-bold">{opt.key}.</span>
                  {opt.text}
                  {opt.recommended && (
                    <span className="ml-2 rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700">(Rekomendasi AI)</span>
                  )}
                </button>
              );
            })}
            {answers[q.id]?.selected === "E" && (
              <textarea
                rows={2}
                value={answers[q.id]?.custom_text || ""}
                onChange={(e) => onAnswerChange(q.id, { ...answers[q.id], custom_text: e.target.value })}
                className="w-full rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-1)] px-3 py-2 text-sm"
                placeholder="Jelaskan pilihan Anda..."
              />
            )}
            {q.recommendation_reason && answers[q.id] && (
              <p className="mt-1 rounded bg-[var(--color-surface-2)] p-2 text-xs text-[var(--color-fg-muted)] italic">
                💡 {q.recommendation_reason}
              </p>
            )}
          </div>
        </div>
        );
      })}
      <Button
        onClick={onSubmit}
        disabled={mcqData.questions.some((q: McqQuestion) => !answers[q.id])}
      >
        <ArrowRight size={15} /> {submitLabel}
      </Button>
    </div>
  );
}

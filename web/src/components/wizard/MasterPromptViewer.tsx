"use client";
import { useMemo, useState } from "react";
import { Card, Badge } from "@/components/ui";
import { Button } from "@/components/ui/Button";
import { CopyField } from "./CopyField";
import { SetupTrackingCard } from "./SetupTrackingCard";
import { parseSections } from "./SectionRenderer";
import { ChevronDown, ChevronRight, Copy, Check, Download, Edit3, X, Save } from "lucide-react";

interface Props {
  projectId: number;
  versionId: number;
  versionLabel: string;
  artifact: string;
}

const SECTION_LABELS: Record<string, string> = {
  "Meta": "0. Meta",
  "Context": "1. Context",
  "Tech Stack": "2. Tech Stack",
  "Stack": "2. Tech Stack",
  "Folder Structure": "3. Folder Structure",
  "Implementation Phases": "4. Implementation Phases",
  "Phases": "4. Implementation Phases",
  "Coding Standards": "5. Coding Standards",
  "Standards": "5. Coding Standards",
  "Tracking Webhook": "6. Tracking Webhook",
  "Webhook": "6. Tracking Webhook",
  "Self-Verify Checklist": "7. Self-Verify Checklist",
  "Output Instructions": "8. Output Instructions",
};

function getSectionLabel(title: string): string {
  const trimmed = title.trim();
  if (SECTION_LABELS[trimmed]) return SECTION_LABELS[trimmed];
  for (const key of Object.keys(SECTION_LABELS)) {
    if (trimmed.toLowerCase().includes(key.toLowerCase())) return SECTION_LABELS[key];
  }
  return trimmed;
}

export function MasterPromptViewer({ projectId, versionId, versionLabel, artifact }: Props) {
  const [editing, setEditing] = useState(false);
  const [editedSections, setEditedSections] = useState<Record<number, string>>({});
  const [globalEdit, setGlobalEdit] = useState("");
  const [copied, setCopied] = useState(false);

  const { beforeFirstSection, sections } = useMemo(() => parseSections(artifact), [artifact]);
  const [openMap, setOpenMap] = useState<Record<number, boolean>>(() => {
    const init: Record<number, boolean> = {};
    sections.forEach((_, i) => (init[i] = i === 0));
    return init;
  });

  const fullText = useMemo(() => {
    if (Object.keys(editedSections).length === 0) return artifact;
    const out: string[] = [];
    if (beforeFirstSection) out.push(beforeFirstSection);
    sections.forEach((sec, i) => {
      const hashes = "#".repeat(sec.level);
      out.push(`${hashes} ${sec.title}`);
      out.push(editedSections[i] ?? sec.content);
      out.push("");
    });
    return out.join("\n").trim();
  }, [artifact, beforeFirstSection, sections, editedSections]);

  function startEdit() {
    const initial: Record<number, string> = {};
    sections.forEach((sec, i) => (initial[i] = sec.content));
    setEditedSections(initial);
    setGlobalEdit(artifact);
    setEditing(true);
  }

  function cancelEdit() {
    setEditedSections({});
    setGlobalEdit("");
    setEditing(false);
  }

  function saveEdit() {
    setEditing(false);
  }

  function updateSection(index: number, value: string) {
    setEditedSections((prev) => ({ ...prev, [index]: value }));
  }

  async function handleCopyAll() {
    try {
      await navigator.clipboard.writeText(fullText);
      setCopied(true);
      setTimeout(() => setCopied(false), 1500);
    } catch {
      // silent fail
    }
  }

  function handleDownload() {
    const blob = new Blob([fullText], { type: "text/markdown;charset=utf-8" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `master-prompt-${versionLabel}.md`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  if (sections.length === 0) {
    return (
      <div className="space-y-3">
        <MasterHeader
          versionLabel={versionLabel}
          fullText={artifact}
          editing={false}
          copied={copied}
          onCopy={handleCopyAll}
          onDownload={handleDownload}
          onStartEdit={startEdit}
        />
        <SetupTrackingCard projectId={projectId} versionId={versionId} />
        <Card className="p-4">
          {editing ? (
            <textarea
              value={globalEdit}
              onChange={(e) => setGlobalEdit(e.target.value)}
              className="min-h-[400px] w-full rounded-md border border-[var(--color-border)] bg-[var(--color-bg-soft)] p-3 font-mono text-xs"
            />
          ) : (
            <pre className="whitespace-pre-wrap text-sm text-[var(--color-fg-muted)]">{artifact}</pre>
          )}
        </Card>
        {editing && (
          <div className="flex justify-end gap-2">
            <Button variant="ghost" size="sm" onClick={cancelEdit}>
              <X size={12} /> Batal
            </Button>
            <Button variant="primary" size="sm" onClick={saveEdit}>
              <Save size={12} /> Simpan
            </Button>
          </div>
        )}
      </div>
    );
  }

  return (
    <div className="space-y-3">
      <MasterHeader
        versionLabel={versionLabel}
        fullText={fullText}
        editing={editing}
        copied={copied}
        onCopy={handleCopyAll}
        onDownload={handleDownload}
        onStartEdit={startEdit}
      />

      <SetupTrackingCard projectId={projectId} versionId={versionId} />

      <div className="space-y-2">
        {sections.map((sec, i) => {
          const isOpen = openMap[i] ?? false;
          const toggle = () => setOpenMap((prev) => ({ ...prev, [i]: !prev[i] }));
          return (
            <Card key={i} className="overflow-hidden p-0">
              <button
                type="button"
                onClick={toggle}
                className="flex w-full items-center gap-2 px-4 py-2.5 text-left font-semibold text-sm hover:bg-[var(--color-surface-2)]"
              >
                {isOpen ? <ChevronDown size={14} /> : <ChevronRight size={14} />}
                <span>{getSectionLabel(sec.title)}</span>
                <Badge tone="muted" className="ml-auto">
                  {editing ? "editing" : "view"}
                </Badge>
              </button>
              {isOpen && (
                <div className="border-t border-[var(--color-border)] p-4">
                  {editing ? (
                    <textarea
                      value={editedSections[i] ?? sec.content}
                      onChange={(e) => updateSection(i, e.target.value)}
                      className="min-h-[150px] w-full rounded-md border border-[var(--color-border)] bg-[var(--color-bg-soft)] p-3 font-mono text-xs"
                    />
                  ) : (
                    <pre className="whitespace-pre-wrap text-sm leading-relaxed text-[var(--color-fg-muted)]">{sec.content}</pre>
                  )}
                </div>
              )}
            </Card>
          );
        })}
      </div>

      {editing && (
        <div className="flex justify-end gap-2">
          <Button variant="ghost" size="sm" onClick={cancelEdit}>
            <X size={12} /> Batal
          </Button>
          <Button variant="primary" size="sm" onClick={saveEdit}>
            <Save size={12} /> Simpan
          </Button>
        </div>
      )}
    </div>
  );
}

function MasterHeader({
  versionLabel,
  fullText,
  editing,
  copied,
  onCopy,
  onDownload,
  onStartEdit,
}: {
  versionLabel: string;
  fullText: string;
  editing: boolean;
  copied: boolean;
  onCopy: () => void;
  onDownload: () => void;
  onStartEdit: () => void;
}) {
  return (
    <div className="flex items-center justify-between gap-2 border-b border-[var(--color-border)] pb-3">
      <div>
        <h2 className="text-lg font-semibold">Master Prompt</h2>
        <p className="text-xs text-[var(--color-fg-subtle)]">{versionLabel}</p>
      </div>
      <div className="flex items-center gap-2">
        {!editing && (
          <>
            <Button variant="ghost" size="sm" onClick={onStartEdit} data-testid="edit-master-prompt">
              <Edit3 size={12} /> Edit
            </Button>
            <Button variant="outline" size="sm" onClick={onDownload} data-testid="download-master-prompt">
              <Download size={12} /> .md
            </Button>
            <Button variant="primary" size="sm" onClick={onCopy} data-testid="copy-master-prompt">
              {copied ? <Check size={12} /> : <Copy size={12} />}
              {copied ? "Copied" : "Copy All"}
            </Button>
          </>
        )}
      </div>
    </div>
  );
}

export function hasMasterPromptArtifact(artifact: string | null | undefined): boolean {
  if (!artifact) return false;
  const trimmed = artifact.trim();
  return trimmed.length > 50;
}

export function masterPromptCharCount(artifact: string | null | undefined): number {
  return artifact?.trim().length ?? 0;
}

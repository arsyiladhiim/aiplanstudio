export type { Target, Template } from './api';
import type { Target } from './api';

export type StageKey = "pertanyaan" | "analisa" | "prd" | "architecture" | "erd" | "api_contract" | "design_system" | "phases_web" | "standards_web" | "master_web" | "app_spec_web" | "design_system_mobile" | "pertanyaan_mobile" | "phases_mobile" | "standards_mobile" | "master_mobile" | "app_spec_mobile" | "env_config" | "security" | "deployment" | "observability" | "agents";
export type StageState = "pending" | "running" | "done" | "error" | "skipped";

export type StageGroup = { key: string; label: string; stages: StageKey[] };

// Urutan & key stage = cermin api/app/Services/StageRegistry.php (source of truth, GET /api/stages).
export const STAGE_GROUPS: StageGroup[] = [
  { key: "discovery", label: "Klarifikasi & Analisa", stages: ["pertanyaan", "analisa"] },
  { key: "definition", label: "Dokumen Produk", stages: ["prd"] },
  { key: "design", label: "Arsitektur & Desain", stages: ["architecture", "erd", "api_contract", "design_system"] },
  { key: "web-build", label: "Web — Rencana Bangun", stages: ["phases_web", "standards_web", "master_web", "app_spec_web"] },
  { key: "mobile-build", label: "Mobile — Rencana Bangun", stages: ["design_system_mobile", "pertanyaan_mobile", "standards_mobile", "phases_mobile", "master_mobile", "app_spec_mobile"] },
  { key: "launch", label: "Operasional & Keamanan", stages: ["env_config", "security", "deployment", "observability", "agents"] },
];

export function getStageGroups(target: Target): StageGroup[] {
  return STAGE_GROUPS
    .map(g => ({
      ...g,
      stages: g.stages.filter(s => target === 'both' || !s.includes('mobile')),
    }))
    .filter(g => g.stages.length > 0);
}

const ALL_STAGES: { key: StageKey; label: string; desc: string }[] = [
  { key: "pertanyaan", label: "Klarifikasi", desc: "AI mengajukan pertanyaan klarifikasi (MCQ) tentang ide kamu." },
  { key: "analisa", label: "Analisa", desc: "AI menganalisa ide berdasarkan jawaban kamu." },
  { key: "prd", label: "PRD", desc: "Dokumen kebutuhan produk terstruktur." },
  { key: "architecture", label: "Arsitektur", desc: "Struktur folder & pilihan teknologi." },
  { key: "erd", label: "ERD", desc: "Skema data + relasi tabel." },
  { key: "api_contract", label: "API Contract", desc: "Daftar endpoint REST per resource." },
  { key: "design_system", label: "Design System", desc: "Design tokens + signature element + anti-pattern checklist untuk web." },
  { key: "phases_web", label: "Web — Phases", desc: "Breakdown fase pembangunan web." },
  { key: "standards_web", label: "Web — Standards", desc: "STANDARDS.md untuk proyek web." },
  { key: "master_web", label: "Web — Master Prompt", desc: "Master prompt self-contained untuk AI agent web." },
  { key: "app_spec_web", label: "App Spec — Web", desc: "Registry halaman, navigation, flows, dan components (JSON)." },
  { key: "design_system_mobile", label: "Design System Mobile", desc: "Design tokens Material 3 + signature element untuk Flutter." },
  { key: "pertanyaan_mobile", label: "Mobile — Klarifikasi", desc: "AI mengajukan pertanyaan klarifikasi khusus mobile (MCQ)." },
  { key: "standards_mobile", label: "Mobile — Standards", desc: "STANDARDS.md untuk proyek mobile." },
  { key: "phases_mobile", label: "Mobile — Phases", desc: "Breakdown fase pembangunan mobile." },
  { key: "master_mobile", label: "Mobile — Master Prompt", desc: "Master prompt self-contained untuk AI agent mobile." },
  { key: "app_spec_mobile", label: "App Spec — Mobile", desc: "Registry screens, navigation, flows, dan widgets Flutter (JSON)." },
  { key: "env_config", label: "Env & Config", desc: "Dokumen environment variables per platform." },
  { key: "security", label: "Security Checklist", desc: "OWASP checklist production-ready." },
  { key: "deployment", label: "Deployment", desc: "Guide deploy Docker + Tunnel + backup + rollback." },
  { key: "observability", label: "Observability", desc: "Health check + Sentry + runbook." },
  { key: "agents", label: "Agents", desc: "AGENTS.md — spesifikasi AI agent." },
];

const WEB_STAGES = ALL_STAGES.filter(s => !s.key.includes('mobile'));

export function getStages(target: Target): { key: StageKey; label: string; desc: string }[] {
  if (target === 'both') return ALL_STAGES;
  return WEB_STAGES;
}

export const STAGES = ALL_STAGES;






export const sampleErd = {
  nodes: [
    { id: "users", label: "users", fields: ["id", "name", "email", "role"] },
    { id: "products", label: "products", fields: ["id", "name", "price", "stock"] },
    { id: "orders", label: "orders", fields: ["id", "user_id", "total", "status"] },
    { id: "order_items", label: "order_items", fields: ["id", "order_id", "product_id", "qty"] },
  ],
  edges: [
    { from: "users", to: "orders", relation: "1:N" },
    { from: "orders", to: "order_items", relation: "1:N" },
    { from: "products", to: "order_items", relation: "1:N" },
  ],
};

export interface SubItem {
  key: string;
  title: string;
  desc?: string;
}

export interface PhaseItem {
  key: string;
  title: string;
  tasks: string[];
  prompt?: string;
  halaman?: SubItem[];
  menu?: SubItem[];
  fitur?: SubItem[];
  flow?: SubItem[];
  api?: SubItem[];
}

export const samplePhases: PhaseItem[] = [
  { key: "setup", title: "Fase 1 — Setup Proyek", tasks: ["Init repo", "Konfigurasi env", "CI dasar"] },
  { key: "auth", title: "Fase 2 — Autentikasi", tasks: ["Register/Login", "Session", "RBAC"] },
  { key: "core", title: "Fase 3 — Fitur Inti", tasks: ["CRUD produk", "Keranjang", "Checkout"] },
  { key: "deploy", title: "Fase 4 — Deploy", tasks: ["Build", "Hosting", "Monitoring"] },
];

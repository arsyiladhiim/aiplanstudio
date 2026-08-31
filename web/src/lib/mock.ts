export type { Target, Template } from "./api"
import type { Target } from "./api"

export type StageKey =
  | "pertanyaan"
  | "analisa"
  | "prd"
  | "architecture"
  | "erd"
  | "api_contract"
  | "design_system"
  | "phases_web"
  | "standards_web"
  | "testing_strategy"
  | "master_web"
  | "app_spec_web"
  | "design_system_mobile"
  | "pertanyaan_mobile"
  | "phases_mobile"
  | "standards_mobile"
  | "master_mobile"
  | "app_spec_mobile"
  | "env_config"
  | "security"
  | "deployment"
  | "observability"
  | "agents"
  | "verify.review"
  | "smoke_test"
  | "verify.production_readiness"
export type StageState =
  | "pending"
  | "ready"
  | "running"
  | "done"
  | "error"
  | "skipped"
  | "blocked"
  | "retrying"
export type StageGateName =
  | "DiscoveryGate"
  | "SpecGate"
  | "ArchGate"
  | "SecurityGate"
  | "DeployGate"
  | "ReviewGate"
  | "SmokeTestGate"
  | "ProductionReadinessGate"
  | null

export type StageGroup = { key: string; label: string; stages: StageKey[] }

// Urutan & key stage = cermin api/app/Services/StageRegistry.php (source of truth, GET /api/stages).
export const STAGE_GROUPS: StageGroup[] = [
  {
    key: "discovery",
    label: "Klarifikasi & Analisa",
    stages: ["pertanyaan", "analisa"],
  },
  { key: "definition", label: "Dokumen Produk", stages: ["prd"] },
  {
    key: "design",
    label: "Arsitektur & Desain",
    stages: ["architecture", "erd", "api_contract", "design_system"],
  },
  {
    key: "web-build",
    label: "Web — Rencana Bangun",
    stages: [
      "phases_web",
      "standards_web",
      "testing_strategy",
      "master_web",
      "app_spec_web",
    ],
  },
  {
    key: "mobile-build",
    label: "Mobile — Rencana Bangun",
    stages: [
      "design_system_mobile",
      "pertanyaan_mobile",
      "standards_mobile",
      "phases_mobile",
      "master_mobile",
      "app_spec_mobile",
    ],
  },
  {
    key: "launch",
    label: "Operasional & Keamanan",
    stages: ["env_config", "security", "deployment", "observability", "agents"],
  },
  {
    key: "verification",
    label: "Verifikasi & Production Readiness",
    stages: ["verify.review", "smoke_test", "verify.production_readiness"],
  },
]

export function getStageGroups(target: Target): StageGroup[] {
  return STAGE_GROUPS.map((g) => ({
    ...g,
    stages: g.stages.filter((s) => target === "both" || !s.includes("mobile")),
  })).filter((g) => g.stages.length > 0)
}

// CP-46.A: mirror StageGateRegistry::gateMap() dari backend.
// Stage tanpa gate = null.
const GATE_MAP: Record<StageKey, StageGateName> = {
  pertanyaan: "DiscoveryGate",
  pertanyaan_mobile: "DiscoveryGate",
  analisa: "SpecGate",
  prd: "SpecGate",
  app_spec_web: "SpecGate",
  app_spec_mobile: "SpecGate",
  design_system: "SpecGate",
  design_system_mobile: "SpecGate",
  standards_web: "SpecGate",
  standards_mobile: "SpecGate",
  phases_web: "SpecGate",
  phases_mobile: "SpecGate",
  testing_strategy: "SpecGate",
  architecture: "ArchGate",
  erd: "ArchGate",
  api_contract: "ArchGate",
  security: "SecurityGate",
  env_config: "DeployGate",
  deployment: "DeployGate",
  observability: "DeployGate",
  agents: "DeployGate",
  master_web: null,
  master_mobile: null,
  "verify.review": "ReviewGate",
  smoke_test: "SmokeTestGate",
  "verify.production_readiness": "ProductionReadinessGate",
}

const ALL_STAGES: {
  key: StageKey
  label: string
  desc: string
  gate: StageGateName
}[] = [
  {
    key: "pertanyaan",
    label: "Klarifikasi",
    desc: "AI mengajukan pertanyaan klarifikasi (MCQ) tentang ide kamu.",
    gate: GATE_MAP.pertanyaan,
  },
  {
    key: "analisa",
    label: "Analisa",
    desc: "AI menganalisa ide berdasarkan jawaban kamu.",
    gate: GATE_MAP.analisa,
  },
  {
    key: "prd",
    label: "PRD",
    desc: "Dokumen kebutuhan produk terstruktur.",
    gate: GATE_MAP.prd,
  },
  {
    key: "architecture",
    label: "Arsitektur",
    desc: "Struktur folder & pilihan teknologi.",
    gate: GATE_MAP.architecture,
  },
  {
    key: "erd",
    label: "ERD",
    desc: "Skema data + relasi tabel.",
    gate: GATE_MAP.erd,
  },
  {
    key: "api_contract",
    label: "API Contract",
    desc: "Daftar endpoint REST per resource.",
    gate: GATE_MAP.api_contract,
  },
  {
    key: "design_system",
    label: "Design System",
    desc: "Design tokens + signature element + anti-pattern checklist untuk web.",
    gate: GATE_MAP.design_system,
  },
  {
    key: "phases_web",
    label: "Web — Phases",
    desc: "Breakdown fase pembangunan web.",
    gate: GATE_MAP.phases_web,
  },
  {
    key: "standards_web",
    label: "Web — Standards",
    desc: "STANDARDS.md untuk proyek web.",
    gate: GATE_MAP.standards_web,
  },
  {
    key: "testing_strategy",
    label: "Testing Strategy",
    desc: "Strategi pengujian: pyramid, coverage target, critical paths, smoke test scope.",
    gate: GATE_MAP.testing_strategy,
  },
  {
    key: "master_web",
    label: "Web — Master Prompt",
    desc: "Master prompt self-contained untuk AI agent web.",
    gate: GATE_MAP.master_web,
  },
  {
    key: "app_spec_web",
    label: "App Spec — Web",
    desc: "Registry halaman, navigation, flows, dan components (JSON).",
    gate: GATE_MAP.app_spec_web,
  },
  {
    key: "design_system_mobile",
    label: "Design System Mobile",
    desc: "Design tokens Material 3 + signature element untuk Flutter.",
    gate: GATE_MAP.design_system_mobile,
  },
  {
    key: "pertanyaan_mobile",
    label: "Mobile — Klarifikasi",
    desc: "AI mengajukan pertanyaan klarifikasi khusus mobile (MCQ).",
    gate: GATE_MAP.pertanyaan_mobile,
  },
  {
    key: "standards_mobile",
    label: "Mobile — Standards",
    desc: "STANDARDS.md untuk proyek mobile.",
    gate: GATE_MAP.standards_mobile,
  },
  {
    key: "phases_mobile",
    label: "Mobile — Phases",
    desc: "Breakdown fase pembangunan mobile.",
    gate: GATE_MAP.phases_mobile,
  },
  {
    key: "master_mobile",
    label: "Mobile — Master Prompt",
    desc: "Master prompt self-contained untuk AI agent mobile.",
    gate: GATE_MAP.master_mobile,
  },
  {
    key: "app_spec_mobile",
    label: "App Spec — Mobile",
    desc: "Registry screens, navigation, flows, dan widgets Flutter (JSON).",
    gate: GATE_MAP.app_spec_mobile,
  },
  {
    key: "env_config",
    label: "Env & Config",
    desc: "Dokumen environment variables per platform.",
    gate: GATE_MAP.env_config,
  },
  {
    key: "security",
    label: "Security Checklist",
    desc: "OWASP checklist production-ready.",
    gate: GATE_MAP.security,
  },
  {
    key: "deployment",
    label: "Deployment",
    desc: "Guide deploy Docker + Tunnel + backup + rollback.",
    gate: GATE_MAP.deployment,
  },
  {
    key: "observability",
    label: "Observability",
    desc: "Health check + Sentry + runbook.",
    gate: GATE_MAP.observability,
  },
  {
    key: "agents",
    label: "Agents",
    desc: "AGENTS.md — spesifikasi AI agent.",
    gate: GATE_MAP.agents,
  },
  {
    key: "verify.review",
    label: "Verify — Code/Sec/Perf Review",
    desc: "Composite gate: agent posts code/security/performance review evidence (security_passed + perf_passed).",
    gate: GATE_MAP["verify.review"],
  },
  {
    key: "smoke_test",
    label: "Smoke Test",
    desc: "Composite gate: agent runs smoke tests dan posts evidence (tests_passed + build_passed).",
    gate: GATE_MAP.smoke_test,
  },
  {
    key: "verify.production_readiness",
    label: "Verify — Production Readiness",
    desc: "Aggregate gate 7-day window: semua verify.* evidence passed.",
    gate: GATE_MAP["verify.production_readiness"],
  },
]

const WEB_STAGES = ALL_STAGES.filter((s) => !s.key.includes("mobile"))

export function getStages(
  target: Target
): { key: StageKey; label: string; desc: string; gate: StageGateName }[] {
  if (target === "both") return ALL_STAGES
  return WEB_STAGES
}

export const STAGES = ALL_STAGES

export const sampleErd = {
  nodes: [
    { id: "users", label: "users", fields: ["id", "name", "email", "role"] },
    {
      id: "products",
      label: "products",
      fields: ["id", "name", "price", "stock"],
    },
    {
      id: "orders",
      label: "orders",
      fields: ["id", "user_id", "total", "status"],
    },
    {
      id: "order_items",
      label: "order_items",
      fields: ["id", "order_id", "product_id", "qty"],
    },
  ],
  edges: [
    { from: "users", to: "orders", relation: "1:N" },
    { from: "orders", to: "order_items", relation: "1:N" },
    { from: "products", to: "order_items", relation: "1:N" },
  ],
}

export interface SubItem {
  key: string
  title: string
  desc?: string
}

export interface PhaseItem {
  key: string
  title: string
  tasks: string[]
  prompt?: string
  halaman?: SubItem[]
  menu?: SubItem[]
  fitur?: SubItem[]
  flow?: SubItem[]
  api?: SubItem[]
}

export const samplePhases: PhaseItem[] = [
  {
    key: "setup",
    title: "Fase 1 — Setup Proyek",
    tasks: ["Init repo", "Konfigurasi env", "CI dasar"],
  },
  {
    key: "auth",
    title: "Fase 2 — Autentikasi",
    tasks: ["Register/Login", "Session", "RBAC"],
  },
  {
    key: "core",
    title: "Fase 3 — Fitur Inti",
    tasks: ["CRUD produk", "Keranjang", "Checkout"],
  },
  {
    key: "deploy",
    title: "Fase 4 — Deploy",
    tasks: ["Build", "Hosting", "Monitoring"],
  },
]

/**
 * Stage key → kolom artifact pada tabel versions (mirror dari
 * PipelineRunner::artifactColumn). SATU-SATUNYA sumber; dipakai useResume,
 * fallback fetch di /new, dan review fetch. Menambah stage baru WAJIB update di sini.
 */
export const ARTIFACT_COL_MAP: Record<string, string> = {
  pertanyaan: "pertanyaan",
  pertanyaan_mobile: "pertanyaan_mobile",
  analisa: "analysis",
  prd: "prd",
  architecture: "architecture",
  erd: "erd",
  api_contract: "api_contract",
  design_system: "design_system",
  design_system_mobile: "design_system_mobile",
  phases_web: "phases",
  standards_web: "standards",
  testing_strategy: "testing_strategy",
  master_web: "master_prompt",
  phases_mobile: "mobile_phases",
  standards_mobile: "mobile_standards",
  master_mobile: "mobile_master_prompt",
  app_spec_web: "app_spec_web",
  app_spec_mobile: "app_spec_mobile",
  env_config: "env_config",
  security: "security",
  deployment: "deployment",
  observability: "observability",
  agents: "agents",
}

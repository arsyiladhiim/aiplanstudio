export type { Target, Template } from './api';
import type { Target } from './api';

export type StageKey = "pertanyaan" | "analisa" | "prd" | "architecture" | "erd" | "api_contract" | "phases_web" | "standards_web" | "master_web" | "pertanyaan_mobile" | "phases_mobile" | "standards_mobile" | "master_mobile" | "agents";
export type StageState = "pending" | "running" | "done" | "error";

const ALL_STAGES: { key: StageKey; label: string; desc: string }[] = [
  { key: "pertanyaan", label: "Klarifikasi", desc: "AI mengajukan pertanyaan klarifikasi (MCQ) tentang ide kamu." },
  { key: "analisa", label: "Analisa", desc: "AI menganalisa ide berdasarkan jawaban kamu." },
  { key: "prd", label: "PRD", desc: "Dokumen kebutuhan produk terstruktur." },
  { key: "architecture", label: "Arsitektur", desc: "Struktur folder & pilihan teknologi." },
  { key: "erd", label: "ERD", desc: "Skema data dalam bahasa sederhana." },
  { key: "api_contract", label: "API Contract", desc: "Daftar endpoint REST lengkap dengan parameter." },
  { key: "phases_web", label: "Web — Phases", desc: "Breakdown fase pembangunan web." },
  { key: "standards_web", label: "Web — Standards", desc: "STANDARDS.md untuk proyek web." },
  { key: "master_web", label: "Web — Master Prompt", desc: "Master prompt self-contained untuk AI agent web." },
  { key: "pertanyaan_mobile", label: "Mobile — Klarifikasi", desc: "AI mengajukan pertanyaan klarifikasi khusus mobile (MCQ)." },
  { key: "phases_mobile", label: "Mobile — Phases", desc: "Breakdown fase pembangunan mobile." },
  { key: "standards_mobile", label: "Mobile — Standards", desc: "STANDARDS.md untuk proyek mobile." },
  { key: "master_mobile", label: "Mobile — Master Prompt", desc: "Master prompt self-contained untuk AI agent mobile." },
  { key: "agents", label: "Agents", desc: "AGENTS.md — spesifikasi AI agent." },
];

const WEB_STAGES = ALL_STAGES.filter(s => !s.key.includes('mobile'));

export function getStages(target: Target): { key: StageKey; label: string; desc: string }[] {
  if (target === 'both') return ALL_STAGES;
  return WEB_STAGES;
}

export const STAGES = ALL_STAGES;

export type Project = {
  id: string;
  title: string;
  idea: string;
  target: Target;
  updatedAt: string;
  versions: number;
  progress: number;
  tags: string[];
};

export const projects: Project[] = [
  { id: "p1", title: "Kasir UMKM Mobile", idea: "Aplikasi kasir untuk warung dengan stok & laporan harian.", target: "both", updatedAt: "2 jam lalu", versions: 3, progress: 72, tags: ["POS", "Flutter"] },
  { id: "p2", title: "SaaS Manajemen Proyek", idea: "Dashboard tim untuk task, timeline, dan billing.", target: "web", updatedAt: "kemarin", versions: 2, progress: 45, tags: ["SaaS", "Next.js"] },
  { id: "p3", title: "Marketplace Jasa Lokal", idea: "Platform mempertemukan penyedia jasa & pelanggan sekitar.", target: "both", updatedAt: "3 hari lalu", versions: 1, progress: 18, tags: ["Marketplace"] },
  { id: "p4", title: "Habit Tracker", idea: "Pelacak kebiasaan dengan streak & pengingat.", target: "both", updatedAt: "1 minggu lalu", versions: 4, progress: 100, tags: ["Mobile", "RN"] },
];

type MockTemplate = {
  id: string;
  name: string;
  target: Target;
  description: string;
  icon: string;
  seed?: Record<string, string>;
};

export const templates: MockTemplate[] = [
  { id: "t1", name: "SaaS Dashboard", target: "web", description: "Auth, billing, multi-tenant, dashboard analytics.", icon: "layout-dashboard" },
  { id: "t2", name: "E-Commerce", target: "both", description: "Katalog, keranjang, checkout, pembayaran.", icon: "shopping-cart" },
  { id: "t3", name: "Mobile CRUD", target: "both", description: "App data sederhana dengan sync offline.", icon: "smartphone" },
  { id: "t4", name: "Marketplace", target: "both", description: "Dua sisi: penjual & pembeli, rating, chat.", icon: "store" },
  { id: "t5", name: "Landing + Waitlist", target: "web", description: "Halaman peluncuran dengan pengumpulan email.", icon: "rocket" },
  { id: "t6", name: "Internal Tool", target: "web", description: "Admin panel + tabel data + role.", icon: "wrench" },
];

export const users = [
  { id: "u1", name: "Arsyila (Admin)", email: "admin@aistack.dev", role: "admin", joined: "12 Jul 2026" },
  { id: "u2", name: "Budi Santoso", email: "budi@example.com", role: "member", joined: "15 Jul 2026" },
  { id: "u3", name: "Citra Dewi", email: "citra@example.com", role: "member", joined: "18 Jul 2026" },
];

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

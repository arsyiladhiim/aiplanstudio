// Mock data untuk preview UI. ponytail: diganti fetch /api saat F3–F7 backend siap.
export type Target = "web" | "mobile" | "both";
export type StageKey = "analisa" | "prd" | "architecture" | "erd" | "phased_master";
export type StageState = "pending" | "running" | "done" | "error";

export const STAGES: { key: StageKey; label: string; desc: string }[] = [
  { key: "analisa", label: "Analisa & Klarifikasi", desc: "AI menganalisa ide, target user, dan asumsi." },
  { key: "prd", label: "PRD", desc: "Dokumen kebutuhan produk terstruktur." },
  { key: "architecture", label: "Arsitektur & Stack", desc: "Struktur folder & pilihan teknologi." },
  { key: "erd", label: "ERD + API", desc: "Skema database & kontrak endpoint." },
  { key: "phased_master", label: "Phases & Master Prompt", desc: "Fase pembangunan + master prompt + standards + rules." },
];

export type Project = {
  id: string;
  title: string;
  idea: string;
  target: Target;
  updatedAt: string;
  versions: number;
  progress: number; // 0..100
  tags: string[];
};

export const projects: Project[] = [
  { id: "p1", title: "Kasir UMKM Mobile", idea: "Aplikasi kasir untuk warung dengan stok & laporan harian.", target: "mobile", updatedAt: "2 jam lalu", versions: 3, progress: 72, tags: ["POS", "Flutter"] },
  { id: "p2", title: "SaaS Manajemen Proyek", idea: "Dashboard tim untuk task, timeline, dan billing.", target: "web", updatedAt: "kemarin", versions: 2, progress: 45, tags: ["SaaS", "Next.js"] },
  { id: "p3", title: "Marketplace Jasa Lokal", idea: "Platform mempertemukan penyedia jasa & pelanggan sekitar.", target: "both", updatedAt: "3 hari lalu", versions: 1, progress: 18, tags: ["Marketplace"] },
  { id: "p4", title: "Habit Tracker", idea: "Pelacak kebiasaan dengan streak & pengingat.", target: "mobile", updatedAt: "1 minggu lalu", versions: 4, progress: 100, tags: ["Mobile", "RN"] },
];

export type Template = {
  id: string;
  name: string;
  target: Target;
  description: string;
  icon: string;
};

export const templates: Template[] = [
  { id: "t1", name: "SaaS Dashboard", target: "web", description: "Auth, billing, multi-tenant, dashboard analytics.", icon: "layout-dashboard" },
  { id: "t2", name: "E-Commerce", target: "both", description: "Katalog, keranjang, checkout, pembayaran.", icon: "shopping-cart" },
  { id: "t3", name: "Mobile CRUD", target: "mobile", description: "App data sederhana dengan sync offline.", icon: "smartphone" },
  { id: "t4", name: "Marketplace", target: "both", description: "Dua sisi: penjual & pembeli, rating, chat.", icon: "store" },
  { id: "t5", name: "Landing + Waitlist", target: "web", description: "Halaman peluncuran dengan pengumpulan email.", icon: "rocket" },
  { id: "t6", name: "Internal Tool", target: "web", description: "Admin panel + tabel data + role.", icon: "wrench" },
];

export const users = [
  { id: "u1", name: "Arsyila (Admin)", email: "admin@aistack.dev", role: "admin", joined: "12 Jul 2026" },
  { id: "u2", name: "Budi Santoso", email: "budi@example.com", role: "member", joined: "15 Jul 2026" },
  { id: "u3", name: "Citra Dewi", email: "citra@example.com", role: "member", joined: "18 Jul 2026" },
];

// Contoh ERD untuk React Flow preview
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

export const samplePhases = [
  { key: "setup", title: "Fase 1 — Setup Proyek", tasks: ["Init repo", "Konfigurasi env", "CI dasar"] },
  { key: "auth", title: "Fase 2 — Autentikasi", tasks: ["Register/Login", "Session", "RBAC"] },
  { key: "core", title: "Fase 3 — Fitur Inti", tasks: ["CRUD produk", "Keranjang", "Checkout"] },
  { key: "deploy", title: "Fase 4 — Deploy", tasks: ["Build", "Hosting", "Monitoring"] },
];

<?php

return fn (string $target) => 'Anda senior product architect. Buat APP SPECIFICATION dalam format JSON VALID yang jadi single source of truth untuk seluruh struktur aplikasi web — registry halaman, navigation graph, user flows, dan component inventory. AI coding agent WAJIB baca JSON ini untuk menavigasi build.

OUTPUT HANYA JSON VALID. Mulai dengan `{` dan akhiri dengan `}`. TIDAK ada markdown fence ```json. TIDAK ada komentar. TIDAK ada intro/closing.

SCHEMA WAJIB:

{
  "version": "1",
  "generated_at": "<YYYY-MM-DD>",
  "generated_from_stages": ["analisa", "phases_web", "design_system"],
  "halaman": [
    {
      "key": "halaman_<snake_case>",
      "title": "<Nama Halaman>",
      "route": "/<path>",
      "phase_owner": "<fase_key_dari_phases_web>",
      "description": "<1-2 kalimat>",
      "components_used": ["<comp_key>"],
      "design_signature": "<reference ke design_system.signature_element>"
    }
  ],
  "navigation": {
    "primary_menu": [
      { "key": "menu_<snake_case>", "title": "<Label>", "icon": "<lucide icon name>", "route": "/<path>" }
    ],
    "user_menus": [...],
    "admin_menus": [...]
  },
  "flows": [
    {
      "key": "flow_<snake_case>",
      "title": "<Nama Flow End-to-End>",
      "steps": [
        { "order": 1, "from": "<halaman_key>", "action": "<verb phrase>", "to": "<halaman_key>" }
      ]
    }
  ],
  "components": [
    {
      "key": "comp_<snake_case>",
      "title": "<Nama Component>",
      "type": "primitive|composite",
      "used_in": ["<halaman_key>"],
      "props_signature": "<TypeScript interface 1-line>"
    }
  ]
}

ATURAN KERAS:
- `halaman` WAJIB minimal 3 entry.
- Setiap halaman WAJIB punya: key, title, route, phase_owner, description, components_used, design_signature.
- `route` WAJIB dimulai dengan `/`.
- `phase_owner` HARUS ada di phases_web[].key — cross-reference WAJIB valid.
- `components_used` array WAJIB reference key yang ada di `components[].key` — cross-reference WAJIB valid.
- `navigation.primary_menu` minimal 2 entry, key + title + icon + route.
- `flows` minimal 1 entry, setiap flow `steps` minimal 2, setiap step punya order + from + action + to.
- Flow `steps[].from` dan `steps[].to` WAJIB reference key yang ada di `halaman[].key`.
- `components` minimal 3 entry, setiap component WAJIB punya key, title, type, used_in, props_signature.
- `components[].used_in` WAJIB reference key yang ada di `halaman[].key` — cross-reference WAJIB valid.
- Semua `key` snake_case (lowercase + underscore).
- TIDAK ada trailing comma.
- TIDAK ada single-quote — pakai double-quote.
- Setiap halaman yang muncul di analisa WAJIB ada di registry.
- Setiap sub-item (HALAMAN/MENU/FITUR/FLOW/API) dari phases_web yang terkait page WAJIB muncul.

CONTOH SINGKAT (referensi, jangan copy paste):

{
  "version": "1",
  "generated_at": "2026-08-18",
  "generated_from_stages": ["analisa", "phases_web", "design_system"],
  "halaman": [
    {
      "key": "halaman_login",
      "title": "Halaman Login",
      "route": "/login",
      "phase_owner": "fase3_auth",
      "description": "Form login dengan email + password + OAuth Google button",
      "components_used": ["comp_action_button", "comp_text_field"],
      "design_signature": "Glass panel di tengah, gradient ambient di belakang"
    },
    {
      "key": "halaman_dashboard",
      "title": "Dashboard",
      "route": "/dashboard",
      "phase_owner": "fase5_features",
      "description": "Dashboard utama dengan ringkasan data + quick actions",
      "components_used": ["comp_data_card", "comp_action_button"],
      "design_signature": "Asymmetric grid: 1 wide metric + 4 compact cards"
    },
    {
      "key": "halaman_projects",
      "title": "Daftar Projects",
      "route": "/projects",
      "phase_owner": "fase5_features",
      "description": "List semua project dengan filter + search + pagination",
      "components_used": ["comp_data_table", "comp_filter_bar"],
      "design_signature": "Sticky table header + inline action buttons per row"
    }
  ],
  "navigation": {
    "primary_menu": [
      { "key": "menu_dashboard", "title": "Dashboard", "icon": "home", "route": "/dashboard" },
      { "key": "menu_projects", "title": "Projects", "icon": "folder", "route": "/projects" }
    ]
  },
  "flows": [
    {
      "key": "flow_first_login",
      "title": "First-time User Login & Onboarding",
      "steps": [
        { "order": 1, "from": "halaman_login", "action": "submit email + password", "to": "halaman_dashboard" },
        { "order": 2, "from": "halaman_dashboard", "action": "klik Create Project", "to": "halaman_projects" }
      ]
    }
  ],
  "components": [
    {
      "key": "comp_action_button",
      "title": "Action Button",
      "type": "primitive",
      "used_in": ["halaman_login", "halaman_dashboard"],
      "props_signature": "interface ActionButtonProps { variant: \'primary\' | \'secondary\' | \'ghost\'; size: \'sm\' | \'md\' | \'lg\'; loading?: boolean; onClick: () => void; }"
    },
    {
      "key": "comp_text_field",
      "title": "Text Field",
      "type": "primitive",
      "used_in": ["halaman_login"],
      "props_signature": "interface TextFieldProps { label: string; value: string; onChange: (v: string) => void; error?: string; }"
    },
    {
      "key": "comp_data_card",
      "title": "Data Card",
      "type": "composite",
      "used_in": ["halaman_dashboard"],
      "props_signature": "interface DataCardProps { title: string; value: string | number; trend?: { direction: \'up\' | \'down\'; pct: number }; }"
    }
  ]
}

'.platformSuffix($target);

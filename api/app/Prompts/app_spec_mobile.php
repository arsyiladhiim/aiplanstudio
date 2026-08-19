<?php

return fn (string $target) => 'Anda senior Flutter architect. Buat APP SPECIFICATION MOBILE dalam format JSON VALID yang jadi single source of truth untuk seluruh struktur aplikasi Flutter/Android — registry screens, navigation graph, user flows, dan widget inventory. AI coding agent WAJIB baca JSON ini untuk menavigasi build.

OUTPUT HANYA JSON VALID. Mulai dengan `{` dan akhiri dengan `}`. TIDAK ada markdown fence ```json. TIDAK ada komentar. TIDAK ada intro/closing.

SCHEMA WAJIB:

{
  "version": "1",
  "generated_at": "<YYYY-MM-DD>",
  "generated_from_stages": ["app_spec_web", "design_system_mobile", "phases_mobile"],
  "screens": [
    {
      "key": "screen_<snake_case>",
      "title": "<Nama Screen>",
      "route": "/<path>",
      "dart_path": "lib/features/<feature>/presentation/<screen>.dart",
      "phase_owner": "<fase_key_dari_phases_mobile>",
      "description": "<1-2 kalimat>",
      "widgets_used": ["<widget_key>"],
      "design_signature": "<reference ke design_system_mobile.signature_element>"
    }
  ],
  "navigation": {
    "primary_menu": [
      { "key": "nav_<snake_case>", "title": "<Label>", "icon": "<Material Icon codepoint name>", "route": "/<path>" }
    ],
    "bottom_nav": [...],
    "drawer_items": [...]
  },
  "flows": [
    {
      "key": "flow_<snake_case>",
      "title": "<Nama Flow End-to-End>",
      "steps": [
        { "order": 1, "from": "<screen_key>", "action": "<verb phrase>", "to": "<screen_key>" }
      ]
    }
  ],
  "widgets": [
    {
      "key": "widget_<snake_case>",
      "title": "<Nama Widget>",
      "type": "primitive|composite",
      "used_in": ["<screen_key>"],
      "props_signature": "<Dart class signature 1-line>"
    }
  ]
}

ATURAN KERAS:
- `screens` WAJIB minimal 3 entry.
- Setiap screen WAJIB punya: key, title, route, dart_path, phase_owner, description, widgets_used, design_signature.
- `dart_path` HARUS dimulai dengan `lib/features/`.
- `phase_owner` HARUS ada di phases_mobile[].key — cross-reference WAJIB valid.
- `widgets_used` array WAJIB reference key yang ada di `widgets[].key`.
- `navigation.primary_menu` minimal 2 entry.
- `flows` minimal 1 entry, setiap flow `steps` minimal 2.
- Flow steps `from`/`to` WAJIB reference key yang ada di `screens[].key`.
- `widgets` minimal 3 entry, setiap widget WAJIB punya key, title, type, used_in, props_signature.
- `widgets[].used_in` WAJIB reference key yang ada di `screens[].key`.
- Cross-reference dengan app_spec_web: setiap screen di mobile yang punya padanan web page WAJIB ada di sini (konsistensi cross-platform).
- Semua `key` snake_case.
- TIDAK ada trailing comma.
- TIDAK ada single-quote.

CONTOH SINGKAT:

{
  "version": "1",
  "generated_at": "2026-08-18",
  "generated_from_stages": ["app_spec_web", "design_system_mobile", "phases_mobile"],
  "screens": [
    {
      "key": "screen_login",
      "title": "Login Screen",
      "route": "/login",
      "dart_path": "lib/features/auth/presentation/login_screen.dart",
      "phase_owner": "m_auth",
      "description": "Form login dengan email + password + biometric option",
      "widgets_used": ["widget_primary_button", "widget_text_input"],
      "design_signature": "Hero morph dari list ke detail + tactile drag handle bottom sheet"
    },
    {
      "key": "screen_dashboard",
      "title": "Dashboard",
      "route": "/dashboard",
      "dart_path": "lib/features/dashboard/presentation/dashboard_screen.dart",
      "phase_owner": "m_dashboard",
      "description": "Dashboard dengan ringkasan + quick actions + sync status",
      "widgets_used": ["widget_metric_card", "widget_primary_button"],
      "design_signature": "Pull-to-refresh dengan tactile feedback + asymmetric card grid"
    },
    {
      "key": "screen_crud_list",
      "title": "CRUD List",
      "route": "/items",
      "dart_path": "lib/features/items/presentation/list_screen.dart",
      "phase_owner": "m_crud",
      "description": "List items dengan search + filter + FAB create",
      "widgets_used": ["widget_search_bar", "widget_item_tile"],
      "design_signature": "Sticky search header + floating action morph ke form"
    }
  ],
  "navigation": {
    "primary_menu": [
      { "key": "nav_dashboard", "title": "Dashboard", "icon": "dashboard", "route": "/dashboard" },
      { "key": "nav_items", "title": "Items", "icon": "list", "route": "/items" }
    ]
  },
  "flows": [
    {
      "key": "flow_first_launch",
      "title": "First-time User Launch & Login",
      "steps": [
        { "order": 1, "from": "screen_login", "action": "submit email + password", "to": "screen_dashboard" },
        { "order": 2, "from": "screen_dashboard", "action": "tap FAB create", "to": "screen_crud_list" }
      ]
    }
  ],
  "widgets": [
    {
      "key": "widget_primary_button",
      "title": "Primary Button",
      "type": "primitive",
      "used_in": ["screen_login", "screen_dashboard"],
      "props_signature": "@freezed class PrimaryButtonProps with _$PrimaryButtonProps { const factory PrimaryButtonProps({ required String label, required VoidCallback onPressed, bool loading = false, }) = _PrimaryButtonProps; }"
    },
    {
      "key": "widget_text_input",
      "title": "Text Input",
      "type": "primitive",
      "used_in": ["screen_login"],
      "props_signature": "@freezed class TextInputProps with _$TextInputProps { const factory TextInputProps({ required String label, required String value, required ValueChanged<String> onChanged, String? error, }) = _TextInputProps; }"
    },
    {
      "key": "widget_metric_card",
      "title": "Metric Card",
      "type": "composite",
      "used_in": ["screen_dashboard"],
      "props_signature": "@freezed class MetricCardProps with _$MetricCardProps { const factory MetricCardProps({ required String label, required num value, String? trend, }) = _MetricCardProps; }"
    }
  ]
}

'.platformSuffix($target);

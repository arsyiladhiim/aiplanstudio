# Plan 45 — Perombakan Penuh Menu Buat Plan & Projects

> Konteks: master prompt v306 §6 berisi placeholder `<TOKEN>`/`<SECRET>` dan tanpa `version_id` → webhook agent gagal → tracking null. Root cause: penyematan data teknis diserahkan ke kepatuhan LLM (tidak deterministik). Prinsip perbaikan: **server-side injection; AI hanya naratif**.
>
> Target agent: OpenCode serve + ChatGPT/Claude Code (multi). Snippet koneksi disediakan 3 varian: bash/curl, python, node.
>
> **Status**: CP-45.A (Tracking Deterministik) selesai 2026-08-23. CP-45.B (Wizard Buat Plan) dan CP-45.C (Projects) **di-supersede** oleh Plan 46 — lihat `docs/plan/46-production-ready-wizard.md`. Plan 46 adalah single source of truth untuk semua perubahan wizard/projects setelah CP-45.A.

## Fase 0 — CP-45.A Tracking Deterministik (P0)

- [x] `App\Services\TrackingInjector`: `inject(Version, string): string` — timpa/tambah blok `<!-- cp45:tracking-live -->` berisi URL publik, version_id, daftar phase_key DB, TOKEN/SECRET nyata bila token auto-tracking ada, snippet curl+python+node siap eksekusi.
- [x] Entry: on-save (`saveArtifact` master_web/master_mobile) + on-read (`VersionController::show`) + on-PATCH (`updateArtifact`) — idempotent via marker.
- [x] Prompt §6 dipangkas: narasi kapan kirim saja; hapus instruksi "salin persis kredensial"; pointer ke blok TRACKING CREDENTIALS.
- [x] MasterPromptViewer badge "Tracking Live: siap" / "Token belum ada" (state deteksi via marker + regex Bearer).
- [x] Test deterministik (`TrackingInjectorTest`): marker+url+vid, snippet bash+python+node lengkap, idempotent, placeholder aman saat token kosong, payload `build()`.
- [x] Verifikasi PHP: pint clean, 424 test pass (1 flake pre-existing SocialiteController). Frontend: lint+tsc clean.
- [ ] Inspeksi log nginx bukti percobaan 401 agent; catat di report.

## Fase 1 — CP-45.B Wizard Buat Plan

- [ ] B1 State machine `useWizardMachine` (idle|starting|streaming|waiting_mcq|awaiting_approve|error|complete|cancelled; stage pending|running|done|error|skipped + retryCount).
- [ ] B2 Decompose: hooks usePipelineStream/usePhaseTracking/useResume; komponen WizardStageRail/WizardArtifactPanel/WizardCheckpointBar/WizardStartForm/WizardCompleteCard/WizardError. Target new/page.tsx ≤1000 LOC.
- [ ] B3 Stage runtime-sync dari GET /api/stages; mock.ts fallback saja.
- [ ] B4 Gate unified (tracking-gate/MCQ/restart) satu komponen.
- [ ] B5 Error UX: diagnostic pack bisa disalin (stage, message, tail buffer, retry).
- [ ] B6 Build Package card: "Salin paket agent" = master prompt injected + AGENTS.md + standards + skrip tracking 3 varian.
- [ ] B7 Panel metrik kanan (durasi/token/retry per stage).
- [ ] Validasi: lint+tsc; E2E happy-path web & both.

## Fase 2 — CP-45.C Projects

Daftar:
- [ ] C1 Health badge realtime (agent_events <60m pulse hijau; >24h amber).
- [ ] C2 Filter butuh perhatian (pipeline_error | blocked | stale).
Detail (sidebar PLANNING|BUILD|EXECUTION|QUALITY|HISTORY):
- [ ] C4 Restructure grup tab.
- [ ] C5 Overview ringkas: progress plan %, exec %, last webhook, fase berjalan.
- [ ] C6 Stale detection via STAGE_DEPENDENTS + tombol "Regenerate affected".
- [ ] C7 Tracking tab fusion (TrackingPanel + AgentEventFeed per fase) + Salin Paket Tracking (bash/python/node) + rotate token.
- [ ] C8 Blocked panel highlight + salin konteks untuk agent.
- [ ] C9 Export package ZIP: MASTER.md/AGENTS.md/STANDARDS.md/ERD.json/API-CONTRACT.json/PHASES.json/TRACKING.md (injected).
- [ ] C10 Token lifecycle UI (last_used/created/expires + rotate ⇒ regen master).
- [ ] C11 AI-ready diff summary (markdown ringkas siap tempel).

## Fase 3 — CP-45.D Verifikasi
- [ ] E2E Playwright: login→new→setup tracking→regen master→assert blob version_id+creds+URL publik.
- [ ] Smoke manual OpenCode serve lintas mesin → UI tracking update.
- [ ] Checklist update per CP + graphify update + commit per CP.

Keputusan default: inject on-save AND on-read (idempotent); tanpa state library baru; token rotate ⇒ auto-regen master; label grup eksplisit.

# 53 — Review Mode DB-First + Layout Responsif

Keputusan user (2 pertanyaan dari sesi audit 51/52):
1. **Review stage selalu fetch dari DB** (sumber kebenaran tunggal), state in-memory hanya cache.
2. Komponen mati **dihapus**, kecuali `WizardEvidenceBadge` (dipakai fase evidence nanti).

## Fase L — Review mode artifact selalu dari DB

- [x] L1. Shared `ARTIFACT_COL_MAP` di `web/src/lib/mock.ts`; pakai di `useResume.ts` + fallback fetch `new/page.tsx` + review fetch (hapus duplikasi; fix `testing_strategy` missing)
- [x] L2. `reviewArtifacts` state + effect: viewingKey berubah → fetch `/versions/{id}` sekali per stage (cache `reviewFetchedRef`, retry-on-error delete ref)
- [x] L3. Renderer memakai `view = {...artifacts, ...reviewArtifacts}` — semua per-stage render dari view
- [x] L4. Fix `activeKey→displayKey` di 3 branch tersisa (phases_web/mobile/master guard)
- [x] L5. api_contract renderer pakai view; hapus effect khusus `apiContractFetchRef` (superseded L2)
- [x] L6. Empty state ERD done-tanpa-artifact; exclude verify.*/smoke_test dari `reviewable`
- [x] L7. Invalidasi reviewArtifacts saat regen/retry/reset/edit-save; hapus komponen mati (WizardStageRail/WizardCheckpointBar/WizardError/WizardCompleteCard) — cek import dulu

## Fase R — Layout responsif

- [x] R1. `/new` grid konstan `lg:grid-cols-[280px_minmax(0,1fr)_340px]`; panel ke-3 `hidden` → jangan ubah template grid (akhiri jump panel)
- [x] R2. `min-w-0` pada anak grid artifact panel; `minmax(0,1fr)` di semua grid kolom-fluid lain (`projects/[id]` L630)
- [x] R3. `Markdown.tsx`: `break-words` pada p/li
- [x] R4. `projects/[id]` tab bar overflow-x-auto; `diff` grid-cols-1 md:grid-cols-2; `settings/layout` tab overflow-x-auto; OnboardingTour clamp width; `body { overflow-x: clip }`
- [x] R5. Modal header: h3 min-w-0 + gap
- [x] R6. Verify: tsc + lint + prettier, rebuild web, manual cek wizard, commit/push

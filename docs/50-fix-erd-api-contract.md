# 50 — Fix ERD Diagram Hang + API Contract "Belum Tersedia"

## Temuan

1. **ERD hang (tab freeze)** — `web/src/components/wizard/ErdDiagram.tsx:83-92`: BFS levelization tanpa cycle guard; siklus relasi → while-loop tak berujung → tab kunci.
2. **"API contract belum tersedia"** — `web/src/app/(app)/new/page.tsx:1537-1552`: renderer hanya coba `JSON.parse(array)`; saat stream, artifact belum normalized → parse gagal → pesan misleading, padahal kolom DB valid (verified: version 2, 16KB array OK). Mismatch tipe `auth` (string `"none"` vs boolean) juga ada.
3. **Parse ERD tiap render mid-stream** — `page.tsx:1654` parse JSON besar tiap token SSE walau belum done.

## Checklist

- [x] I1. `ErdDiagram.layoutGraph`: anti-cycle (visited + level cap)
- [x] I2. `new/page.tsx`: parse ERD hanya saat `status.erd === 'done'` (memo)
- [x] I3. `new/page.tsx` api_contract renderer: parser toleran (array | `{endpoints}`), fallback fetch `/versions/{id}` saat parse gagal & done
- [x] I4. `ApiContractTable`: map `auth` string ('none'|'required') → boolean
- [x] I5. Verify: tsc + lint + prettier, rebuild web, test ERD siklik manual (unit test layoutGraph)

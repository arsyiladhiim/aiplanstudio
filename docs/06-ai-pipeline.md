# 06 — AI Pipeline

> Lihat juga: [05-wizard-flow](05-wizard-flow.md) · [04-api-contract](04-api-contract.md) · [03-database-schema](03-database-schema.md)
> Semua pemanggilan AI dilakukan **backend Laravel**. Rahasia tak pernah ke client.

## Komponen
```
app/Services/AiClient.php        # low-level: panggil provider OpenAI-compatible (streaming)
app/Services/PipelineRunner.php  # orkestrasi 6 stage, simpan artefak, emit SSE
app/Prompts/*.php                # template prompt per stage (target-aware)
```

## AiClient
- Baca konfigurasi dari tabel `ai_providers` (singleton). `api_key` di-decrypt via cast Eloquent.
- Request: `POST {base_url}/chat/completions` dengan header `Authorization: Bearer {api_key}`, body `{model, messages, stream:true}`.
- Relay stream token (SSE OpenAI-style `data: {...}`) ke pemanggil.
- Kompatibel OpenAI, Deepseek, Groq, OpenRouter, Ollama, LM Studio (cukup ganti `base_url` + `model`).
- **Anthropic/Claude** didukung via `provider_type: 'anthropic'` — endpoint `/messages`, header `x-api-key`, dan format response berbeda (parse `content_block_delta` + `message_stop` untuk stream).
- **Error handling:** timeout, 4xx/5xx dari provider → lempar error yang **tidak** membocorkan `api_key`; teruskan sebagai SSE `event: error`.

## PipelineRunner
- Method utama: `run(Version $v, string $stage, bool $auto)`.
- Susun `messages`:
  - `system` = template prompt stage (dari `app/Prompts`, dipilih berdasar `stage` + `target`).
  - `user`/`assistant` = konteks: ide + artefak stage-stage sebelumnya (analysis, prd, architecture, erd, ...).
- Stream token → emit SSE `token`; saat selesai → parse & simpan ke kolom `versions` sesuai stage → emit `artifact` lalu `done`.
- Update `versions.stage_status` (`running`/`done`/`error`) tiap transisi.
- Jika `auto=true`: setelah `done`, lanjut stage berikutnya otomatis dalam koneksi SSE yang sama sampai `master`.

## Urutan Stage & Konteks
| Stage | Konteks masuk | Simpan ke |
|-------|---------------|-----------|
| `analisa` | idea, target, stack | `analysis` |
| `prd` | analysis + idea | `prd` |
| `architecture` | prd, target | `architecture` |
| `erd` | prd, architecture | `erd` (jsonb), `api_contract` (jsonb) |
| `phases` | prd, architecture, erd | `phases` (jsonb) |
| `master` | prd, architecture, erd, phases | `master_prompt` |

## Output Terstruktur (JSON stage)
- Stage `erd`, `phases`, dan `master` **wajib JSON valid**. Prompt memaksa format:
  - ERD: `{ "nodes": [{id,label,fields:[...]}], "edges": [{from,to,relation}] }`.
  - Phases: `[{ "key","title","tasks":[...],"prompt":"..." }]`.
  - Master: `{ "master": "...", "phases": [...] }`.
- Backend **validasi** JSON via `json_decode`. Bila gagal parse:
  1. Retry **sekali** dengan instruksi perbaikan format.
  2. Jika retry masih gagal → lempar `RuntimeException` dan stage ditandai `error`.
- **Jangan** percaya output AI mentah — selalu validasi sebelum simpan/render. Lihat [11-development-rules](11-development-rules.md).

## Prompt Template (`app/Prompts`)
- Bahasa Indonesia.
- Per stage + branch target (Web / Mobile / Both).
- Tekankan: konsistensi antar stage, prompt phase membawa konteks phase sebelumnya (lihat [05-wizard-flow](05-wizard-flow.md) tahap 6).

## Antrian (opsional)
- Untuk run panjang, PipelineRunner bisa dijalankan lewat queue (`redis` + `php artisan queue:work`). MVP boleh sinkron dalam koneksi SSE. Lihat [02-architecture](02-architecture.md).

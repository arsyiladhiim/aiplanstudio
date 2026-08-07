# 06 — AI Pipeline

> Lihat juga: [05-wizard-flow](05-wizard-flow.md) · [04-api-contract](04-api-contract.md) · [03-database-schema](03-database-schema.md)
> Semua pemanggilan AI dilakukan **backend Laravel**. Rahasia tak pernah ke client.

## Komponen
```
app/Services/AiClient.php        # low-level: panggil provider OpenAI-compatible (streaming)
app/Services/PipelineRunner.php  # orkestrasi 7 stage, simpan artefak, emit SSE
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
- Method utama: `run(string|null $stage, bool $auto)`.
- `ALL_STAGES` constant: `['pertanyaan', 'analisa', 'prd', 'architecture', 'erd', 'phased_master', 'phased_master_mobile']`
- Susun `messages`:
  - `system` = template prompt stage (dari `app/Prompts`, dipilih berdasar `stage` + `target`).
  - `user` = konteks: ide + target + stack + jawaban + artefak stage-stage sebelumnya.
- Stream token → emit SSE `token`; saat selesai → parse & simpan ke kolom `versions` sesuai stage → emit `artifact` lalu `done`.
- Update `versions.stage_status` (`running`/`done`/`error`) tiap transisi.
- Jika `auto=true`: setelah `done`, lanjut stage berikutnya otomatis dalam koneksi SSE yang sama sampai selesai (atau `phased_master_mobile` jika target=both).
- **Continuations:** Jika AI output terpotong (finish_reason=`length`), auto-prompt lanjutan sampai selesai natural.
- **Race condition prevention:** `DB::transaction()` + `Version::lockForUpdate()` sebelum update `stage_status`.

## Urutan Stage & Konteks

| Stage | Konteks masuk | Simpan ke |
|-------|---------------|-----------|
| `pertanyaan` | idea, target, stack | → `pertanyaan` (text) — disimpan ke DB |
| `analisa` | idea, target, stack, jawaban pertanyaan | `analysis` |
| `prd` | idea, target, stack, jawaban, analysis | `prd` |
| `architecture` | PRD, target, stack | `architecture` |
| `erd` | PRD, arsitektur | `erd` (jsonb) + `api_contract` (jsonb, extracted from AI text output) |
| `phased_master` | PRD, arsitektur, ERD | `phases` (jsonb), `master_prompt`, `standards`, `agents` |
| `phased_master_mobile` | PRD, arsitektur, ERD (mobile context) | `mobile_phases` (jsonb), `mobile_master_prompt`, `mobile_standards`, `mobile_agents` |

> `phased_master_mobile` hanya dijalankan jika `project.target === 'both'`. Untuk target `web`/`mobile`, tahap ini di-skip dan langsung ditandai `done`.

## Output Terstruktur (JSON stage)
- Stage `erd` dan `phased_master`/`phased_master_mobile` **wajib JSON valid**.
  - ERD: `{ "nodes": [{id,label,fields:[...]}], "edges": [{from,to,relation}] }`.
  - Phased Master: parsed via separator markers `===PHASES===`, `===MASTER===`, `===STANDARDS===`, `===AGENTS===`; phases sub-blok pakai format `FASE:`, `TASK:`, `PROMPT:`.
- Backend **validasi** JSON via multi-strategy decoder:
  1. Direct `json_decode`
  2. Strip control characters
  3. Balance braces
  4. Fix missing `[` after known keys
  5. Single-quote → double-quote fix
- Jika gagal parse → retry **sekali** dengan instruksi perbaikan format, baru throw error jika gagal lagi.
- **Jangan** percaya output AI mentah — selalu validasi sebelum simpan/render. Lihat [11-development-rules](11-development-rules.md).

## Prompt Template (`app/Prompts`)
- Bahasa Indonesia.
- Per stage + branch target (Web / Mobile / Both).
- Tekankan: konsistensi antar stage, prompt phase membawa konteks phase sebelumnya (lihat [05-wizard-flow](05-wizard-flow.md) tahap 6).

## SSE Event Format
```
event: status      data: {"stage":"analisa","state":"running"}
event: token      data: {"stage":"analisa","delta":"teks..."}
event: artifact    data: {"stage":"analisa","content":"...final..."}
event: done       data: {"stage":"analisa"}
event: fail       data: {"stage":"analisa","message":"..."}
```

## Antrian (opsional)
- Untuk run panjang, PipelineRunner bisa dijalankan lewat queue (`redis` + `php artisan queue:work`). MVP berjalan sinkron dalam koneksi SSE. Lihat [02-architecture](02-architecture.md).

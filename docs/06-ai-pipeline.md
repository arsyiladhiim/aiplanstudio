# 06 — AI Pipeline

> Lihat juga: [05-wizard-flow](05-wizard-flow.md) · [04-api-contract](04-api-contract.md) · [03-database-schema](03-database-schema.md)
> Semua pemanggilan AI dilakukan **backend Laravel**. Rahasia tak pernah ke client.

## Komponen
```
app/Services/AiClient.php        # low-level: panggil provider OpenAI-compatible (streaming)
app/Services/PipelineRunner.php  # orkestrasi 14 stage (both) / 10 stage (web), simpan artefak, emit SSE
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
- `ALL_STAGES` constant: `['pertanyaan', 'analisa', 'prd', 'architecture', 'erd', 'api_contract', 'phases_web', 'standards_web', 'master_web', 'pertanyaan_mobile', 'phases_mobile', 'standards_mobile', 'master_mobile', 'agents']`
- `MOBILE_STAGES`: `['pertanyaan_mobile', 'phases_mobile', 'standards_mobile', 'master_mobile']` — hanya untuk target `both`, gate menunggu `master_web` done.
- Susun `messages`:
  - `system` = template prompt stage (dari `app/Prompts`, dipilih berdasar `stage` + `target`).
  - `user` = konteks: ide + target + stack + jawaban + artefak stage-stage sebelumnya.
- Stream token → emit SSE `token`; saat selesai → parse & simpan ke kolom `versions` sesuai stage → emit `artifact` lalu `done`.
- Update `versions.stage_status` (`running`/`done`/`error`) tiap transisi.
- Jika `auto=true`: setelah `done`, lanjut stage berikutnya otomatis dalam koneksi SSE yang sama sampai selesai (atau `master_mobile` jika target=both, dengan gate menunggu `master_web` done).
- **Continuations:** Jika AI output terpotong (finish_reason=`length`), auto-prompt lanjutan sampai selesai natural.
- **Race condition prevention:** `DB::transaction()` + `Version::lockForUpdate()` sebelum update `stage_status`.

## Urutan Stage & Konteks

| Stage | Konteks masuk | Simpan ke |
|-------|---------------|-----------|
| `pertanyaan` | idea, target, stack | → `pertanyaan` (text JSON MCQ) — disimpan ke DB; jawaban → `answers` |
| `analisa` | idea, target, stack, jawaban pertanyaan | `analysis` |
| `prd` | idea, target, stack, jawaban, analysis | `prd` |
| `architecture` | PRD, target, stack | `architecture` |
| `erd` | PRD, arsitektur | `erd` (jsonb) + seed `api_contract` |
| `api_contract` | PRD, arsitektur, ERD | `api_contract` (jsonb, array endpoint) |
| `phases_web` | standards, agents, PRD, arsitektur, ERD | `phases` (jsonb) — breakdown fase web |
| `standards_web` | PRD, arsitektur, ERD | `standards` (STANDARDS.md web) |
| `master_web` | standards, agents, analisa, PRD, arsitektur, ERD, **phases (fase dari phases_web)** | `master_prompt` (self-contained + auto token tracking; **fase wajib pakai dari phases_web, bukan buat baru**) |
| `pertanyaan_mobile` | master_web, api_contract, erd | `pertanyaan_mobile` (JSON MCQ) + `mobile_answers` |
| `phases_mobile` | mobile_answers, mobile_standards, master_web, PRD, arsitektur, ERD | `mobile_phases` (jsonb) |
| `standards_mobile` | mobile_answers, PRD, arsitektur, ERD, master_web | `mobile_standards` (STANDARDS.md mobile) |
| `master_mobile` | mobile_answers, mobile_standards, mobile_agents, analisa, PRD, arsitektur, ERD, **mobile_phases** , master_web | `mobile_master_prompt` |
| `agents` | master_web (+ master_mobile jika both) | `agents` (AGENTS.md) |

> Mobile track (stage 10-13) hanya dijalankan jika `project.target === 'both'` **dan** `master_web` sudah done (gate). Untuk target `web`, mobile track di-skip dan langsung ditandai `done`.

## Output Terstruktur (JSON stage)
- Stage `pertanyaan`, `pertanyaan_mobile`, `api_contract`, `erd` **wajib JSON valid**.
  - `pertanyaan`/`pertanyaan_mobile`: `{ambiguities, questions:[{id, question, options:[{key,text,recommended,custom}], recommendation_reason}]}`
  - `api_contract`: array `[{method,path,description,auth}]` (normalisasi: terima objek `{endpoints}` juga).
  - ERD: `{ nodes:[{id,label,fields:[...]}], edges:[{from,to,relation}] }`.
  - Phases (`phases_web`/`phases_mobile`): parsed dari format teks `FASE:`/`TASK:`/`PROMPT:` → jsonb array.
- Stage `standards_web`, `master_web`, `standards_mobile`, `master_mobile`, `agents` menghasilkan teks/markdown langsung (tanpa parsing JSON).
- Backend **validasi** JSON via multi-strategy decoder:
  1. Direct `json_decode` · 2. strip control chars · 3. quote unquoted keys · 4. balance missing closers · 5. trim trailing annotation · 6. single-quote → double-quote
- Jika gagal parse → error stage (bukan disimpan mentah). MCQ `pertanyaan`: auto-retry sampai ≥5 pertanyaan (guard 180, hasil terbaik disimpan).
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

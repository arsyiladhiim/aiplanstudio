<?php

$flutterStandards = '
## Tech Stack
- Flutter SDK stable, Dart null safety WAJIB
- State: Riverpod 2.x (`flutter_riverpod`)
- Routing: GoRouter 14.x
- HTTP: dio + dio_cookie_manager (untuk session cookie)
- Local DB: drift (preferred) atau sqflite
- UI: Material Design 3 + dynamic color
- Code gen: build_runner + freezed + json_serializable

## Coding Standards

### Naming
- `snake_case` untuk folder dan file names (kecuali file yang contain class → `snake_case.dart` dengan class `PascalCase`)
- `PascalCase` untuk class, widget, enum
- `camelCase` untuk variabel, fungsi, method, parameter
- `SCREAMING_SNAKE_CASE` untuk const + enum values
- Prefix private dengan `_` (e.g. `_internalState`)

### Structure (WAJIB feature-first)
```
lib/features/<feature>/
├── data/           # repositories, remote/local sources, DTOs
├── domain/         # entities, use cases, repository interfaces
└── presentation/   # screens, widgets, controllers (Riverpod)
```

### State Management (Riverpod)
- `Provider` untuk immutable derived state
- `StateNotifierProvider` atau `AsyncNotifierProvider` untuk mutable state
- `ConsumerWidget` / `Consumer` untuk read
- JANGAN pakai `setState` untuk business logic — hanya untuk pure UI local state

### Code Style
```dart
// ✅ Good
@freezed
class UserProfile with _$UserProfile {
  const factory UserProfile({
    required String id,
    required String email,
    String? displayName,
  }) = _UserProfile;

  factory UserProfile.fromJson(Map<String, dynamic> json) =>
      _$UserProfileFromJson(json);
}

// ❌ Bad — manual JSON parsing
class UserProfile {
  UserProfile({required this.id, required this.email});
  final String id;
  final String email;
}
```

### Error Handling
```dart
// ✅ Good — sealed class for result type
sealed class Result<T> {}
class Success<T> extends Result<T> { final T data; Success(this.data); }
class Failure<T> extends Result<T> { final String message; Failure(this.message); }

// Use in repository
Future<Result<UserProfile>> getProfile() async { ... }
```

## Database Conventions
- SQLite via drift → type-safe queries, reactive streams
- `@DataClassName` untuk table → entity mapping
- Migration file naming: `drift_dev/migrations/V<NNN>__<description>.drift`
- `snake_case` untuk semua field names di JSON API response (mapping via freezed)

## Testing
- `flutter_test` untuk unit + widget tests
- `mocktail` untuk mocking (bukan mockito — lebih simple)
- `integration_test` untuk e2e flow
- Coverage target: 70% untuk `domain/`, 50% untuk `presentation/`

## Git Convention (Conventional Commits)
- `feat:` fitur baru
- `fix:` bug fix
- `chore:` maintenance (deps, config)
- `docs:` dokumentasi
- `refactor:` code restructure tanpa behavior change
- `test:` add/update test
- Format: `type(scope): deskripsi singkat`
- Example: `feat(auth): add biometric login`

## AI Coding Rules
1. WAJIB baca STANDARDS.md ini sebelum menulis kode
2. JANGAN hapus/rename file existing tanpa konfirmasi user
3. JANGAN tambah dependency baru kalau ada stdlib/library existing yang bisa pakai
4. Setiap public function WAJIB punya unit test
5. Jalankan `flutter analyze` + `flutter test` sebelum commit
6. Ikuti struktur feature-first — JANGAN campur features
';

$webStandards = '
## Tech Stack
- Backend: Laravel 11 (PHP 8.4) + Sanctum SPA Session
- Frontend: Next.js 15 (App Router) + React 19 + TypeScript strict
- DB: PostgreSQL 16 (3 schemas: master, project, settings)
- Styling: Tailwind CSS v4 (utility-first, design tokens via CSS vars)
- Test: PHPUnit FeatureTest (backend), Playwright (e2e)

## Coding Standards

### PHP / Laravel
- PSR-12 coding style (enforced via Pint)
- Type hints untuk SEMUA parameters dan return types
- DocBlock untuk setiap public method
- `FormRequest` untuk validasi (JANGAN inline `validate()` di controller)
- `API Resource` untuk response formatting
- `Policy` untuk authorization
- `snake_case` untuk DB columns, `camelCase` untuk method/property

```php
// ✅ Good
public function store(StoreProjectRequest $request): JsonResponse
{
    $project = $this->service->create($request->validated(), $request->user());
    return (new ProjectResource($project))->response()->setStatusCode(201);
}

// ❌ Bad — inline validation, no type hints
public function store(Request $request) {
    $data = $request->validate([...]);
    $project = Project::create($data);
    return response()->json($project);
}
```

### Next.js / TypeScript
- App Router (BUKAN Pages Router)
- Server Components by default — `"use client"` hanya kalau perlu interactivity
- TypeScript strict mode ON
- `PascalCase` untuk components, `camelCase` untuk functions/variables
- Folder: `app/(group)/page.tsx` untuk routes, `components/<area>/` untuk components

```tsx
// ✅ Good — Server Component default
export default async function ProjectsPage() {
  const projects = await apiGet<Project[]>("/projects");
  return <ProjectList projects={projects} />;
}

// ✅ Good — Client Component when needed
"use client";
export function ProjectForm() {
  const [name, setName] = useState("");
  // ...
}
```

### React 19 Compiler Rules (WAJIB)
- JANGAN `setState` di dalam `useEffect` dengan non-trivial deps
- JANGAN baca `ref.current` di render body
- Pakai `useReducer` untuk state derivation yang kompleks
- Pakai `useId()` untuk unique IDs

### Tailwind CSS
- Utility-first, NO custom CSS file kecuali global tokens
- Class order: layout → spacing → typography → colors → states
- Pakai `var(--color-*)` design tokens, BUKAN hardcoded colors

```tsx
// ✅ Good
<button className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-[var(--color-fg)] bg-[var(--color-brand)] hover:brightness-110">

// ❌ Bad — hardcoded
<button className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-500 hover:brightness-110">
```

## Database Conventions
- `snake_case` untuk table + column names
- `created_at`, `updated_at` timestamps di setiap tabel
- `deleted_at` untuk soft delete (gunakan `SoftDeletes` trait)
- FK naming: `<table_singular>_id` (e.g. `user_id`, `project_id`)
- Index untuk kolom yang sering di-query (`user_id`, `created_at`, `status`)
- Migration forward-only — JANGAN edit applied migration

## Architecture Patterns
- **Backend:** Controllers → Services → Repositories → Models
- **Frontend:** Direct fetch wrappers (`web/src/lib/api.ts` dengan Sanctum cookie session + CSRF) → components
- **State:** Server Components untuk data fetch, `useState`/`useReducer` untuk local, NO Redux/Zustand kecuali justified
- **Validation:** DB constraints > FormRequest > client-side (defense in depth)

## Testing
- Backend: PHPUnit `FeatureTest` untuk semua endpoint
- Frontend: Playwright untuk e2e flow kritis
- Test naming: `test_<action>_<expected_result>`
- Coverage target: 60% backend, 40% frontend

## Git Convention (Conventional Commits)
- `feat:` fitur baru
- `fix:` bug fix
- `chore:` maintenance (deps, config)
- `docs:` dokumentasi
- `refactor:` code restructure tanpa behavior change
- `test:` add/update test
- `security:` security fix
- Format: `type(scope): deskripsi singkat`
- Example: `feat(projects): add bulk archive action`

## AI Coding Rules
1. WAJIB baca STANDARDS.md ini sebelum menulis kode
2. JANGAN hapus/rename file existing tanpa konfirmasi user
3. JANGAN tambah dependency baru kalau ada stdlib/library existing yang bisa pakai
4. Setiap public function/class WAJIB punya test
5. Jalankan `php artisan test` + `npm run lint` + `npx tsc --noEmit` sebelum commit
6. Format PHP dengan Pint, TS/JS dengan Prettier
7. Ikuti struktur folder yang sudah ada di project
';

return fn (string $target) => 'Buat STANDARDS.md untuk proyek ini. Output dalam format Markdown. STANDARDS adalah satu-satunya sumber kebenaran untuk coding conventions — setiap AI agent WAJIB baca sebelum menulis kode.

# STANDARDS.md
' . ($target === 'mobile' || $target === 'both'
    ? $flutterStandards
    : $webStandards) . '

' . platformSuffix($target) . PHP_EOL . '

[ATURAN KERAS]
- Setiap section WAJIB ada minimal 1 code snippet ✅ vs ❌.
- BUKAN teori — langsung pakai contoh code yang applicable.
- Section "AI Coding Rules" WAJIB di akhir dan jadi hard constraint.
- Pakai Bahasa Indonesia untuk penjelasan, English untuk code identifier + technical terms.

[OUTPUT INSTRUCTIONS]
- Jawab HANYA dengan STANDARDS.md di atas.
- Tidak ada intro/closing.
- Code snippet WAJIB fenced dengan bahasa (```php, ```tsx, ```dart).

VERIFY: Apakah semua section ada code snippet? Apakah "AI Coding Rules" hard constraint? Apakah React 19 / Laravel 11 conventions reflect codebase real?';

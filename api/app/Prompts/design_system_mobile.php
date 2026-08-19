<?php

return fn (string $target) => 'Anda senior Flutter product designer dengan track record anti-template. Buat DESIGN SYSTEM MOBILE dalam format Markdown yang jadi single source of truth untuk seluruh visual decisions aplikasi Flutter/Android. AI coding agent WAJIB baca dokumen ini sebelum generate widget manapun.

REFERENSI SKILL WAJIB:
- "ui-ux-original" — design yang punya point of view, anti-AI-generic.
- "web-designer" — keputusan MOOD → PALETTE → TYPE → LAYOUT → MOTION → SIGNATURE.

OUTPUT HANYA Markdown. Mulai dari `# Design System Mobile — <NAMA_PROYEK>`. TIDAK ada markdown fence pembungkus. TIDAK ada intro/closing.

# Design System Mobile — <NAMA_PROYEJ>

## 0. Pin the Subject
- Domain spesifik: <1 kalimat domain app ini, BUKAN "mobile app generik">
- Audience konkret: <1 kalimat siapa user + konteks pakai (kapan, dimana, berapa lama per sesi)>
- Page\'s single job: <1 kalimat job 1 screen paling kritis>

## 1. Design Philosophy
- 1 kalimat value/vibe (e.g. "tactile-pragmatic untuk field worker", "calm-immersive untuk evening reader", "burst-energetic untuk morning commute")
- Anti-stock phrases: 3 frasa yang JANGAN dipakai
- Rujuk ke nama persona dari stage analisa + design system web (WAJIB konsisten cross-platform)

## 2. Material 3 Token System
WAJIB output code fence ```dart dengan ThemeData + ColorScheme.fromSeed + TextTheme. Bukan CSS @theme — Flutter pakai widget ThemeData:

```dart
import \'package:flutter/material.dart\';

ThemeData buildAppTheme() {
  final colorScheme = ColorScheme.fromSeed(
    seedColor: const Color(0xFF<HEX>), // pilih warna signature
    brightness: Brightness.light, // atau .dark
  );

  final textTheme = TextTheme(
    displayLarge: TextStyle(fontFamily: \'<Nama Font Google Fonts>\', fontSize: 57, fontWeight: FontWeight.w400),
    headlineMedium: TextStyle(fontFamily: \'<Nama Font Google Fonts>\', fontSize: 28, fontWeight: FontWeight.w600),
    titleLarge: TextStyle(fontFamily: \'<Nama Font Google Fonts>\', fontSize: 22, fontWeight: FontWeight.w500),
    bodyLarge: TextStyle(fontFamily: \'<Nama Font Google Fonts>\', fontSize: 16, fontWeight: FontWeight.w400),
    labelLarge: TextStyle(fontFamily: \'<Nama Font Google Fonts>\', fontSize: 14, fontWeight: FontWeight.w500),
  );

  return ThemeData(
    useMaterial3: true,
    colorScheme: colorScheme,
    textTheme: textTheme,
    // Custom tweaks — JANGAN default M3 purple
    appBarTheme: AppBarTheme(
      backgroundColor: colorScheme.surface,
      elevation: 0,
      scrolledUnderElevation: 1,
    ),
  );
}
```

WAJIB: ColorScheme.fromSeed ada, useMaterial3: true, TextTheme dengan ≥4 style, custom AppBarTheme/ButtonTheme override.

## 3. Signature Element
Untuk 3-5 screen utama dari phases_mobile, definisikan SIGNATURE pattern unik mobile (Hero/PageRoute/Transition):

### Screen 1: <Nama Screen>
- **Pattern**: <nama signature unik, e.g. "Hero Morph dari list ke detail", "Custom PageRoute dengan asymmetric slide", "Bottom Sheet dengan tactile drag handle">
- **ASCII Wireframe mobile**: 5-8 baris ASCII (mobile aspect ratio)
- **Implementation hint**: 1-2 line Dart code (Hero widget / PageRouteBuilder / Custom widget)
- **Kenapa memorable**: 1 kalimat rasional

### Screen 2: <Nama Screen>
(structure sama, total minimal 3 screens)

### Screen 3: <Nama Screen>
(structure sama)

## 4. Widget Patterns
5-8 widget custom yang BUKAN ElevatedButton/Card stock. Untuk tiap widget:
- **Nama**: <Nama Widget>
- **Kapan pakai**: <1 kalimat use case spesifik>
- **Visual cue unik**: <apa twist signature — bukan stock M3>
- **Props signature (@freezed)**: <2-5 lines Dart>

## 5. State Vocabulary (mobile-specific)
- **Empty state**: <visual cue + microcopy spesifik, BUKAN stock "No items" dari flutter create>
- **Loading state**: <Skeletonizer / shimmer pattern sesuai domain — BUKAN CircularProgressIndicator default>
- **Error state**: <SnackBar custom dengan action button, BUKAN default>
- **Success state**: <micro-interaction signature — haptic + morph icon>

## 6. Anti-Pattern Checklist (mobile-specific)
WAJIB ≥7 item dengan `- [ ]`:
- [ ] <anti-pattern spesifik>
- [ ] <anti-pattern 2>
- [ ] <anti-pattern 3>
- [ ] <anti-pattern 4>
- [ ] <anti-pattern 5>
- [ ] <anti-pattern 6>
- [ ] <anti-pattern 7>

Referensi: default M3 purple ColorScheme.fromSeed tanpa override, FAB stock sebagai primary action, Card shape: RoundedRectangleBorder(16) di semua tempat, bottom navigation 5 tab stock Material icons, stock empty state dari flutter create, placeholder image random package tanpa konteks, "Welcome to ..." sebagai hero default mobile.

## 7. Layout Rhythm
3 section treatment berbeda:
- Section A: <pattern, e.g. "bottom sheet → full screen → split view">
- Section B: <pattern berbeda>
- Section C: <pattern berbeda lagi>

## 8. Motion Choreography (Flutter-specific)
- 1 orchestrated signature moment: <Hero transition atau PageRouteBuilder dengan curve + duration>
- Reduced-motion fallback: <AnimationController auto-stop, atau MediaQuery.disableAnimations check>

## 9. Microcopy Voice
- 3 contoh microcopy mobile: <button labels, SnackBar text, empty messages>
- Tone guideline: <1 kalimat konsisten>

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
VERIFY sebelum respond:
1. 9 heading "## N." ada (0-9).
2. Section 2 code fence ```dart ada, dengan ColorScheme.fromSeed + TextTheme ≥4 style.
3. Section 3 minimal 3 screens (### Screen N: ...).
4. Section 4 minimal 5 widget.
5. Section 6 checklist `- [ ]` minimal 7 item.
6. Panjang total ≥2500 chars.
7. Konsistensi dengan design system web (WAJIB sebut referensi di Section 1).

'.platformSuffix($target);

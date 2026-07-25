<?php

return fn(string $target) => 'Dari PRD, breakdown jadi fase pembangunan. FORMAT JSON ONLY. [{"key":"setup","title":"Fase 1 — Setup","tasks":["Init repo"],"prompt":"..."}]

' . platformSuffix($target) . '

Jawab langsung dengan output yang diminta, tanpa basa-basi pembuka.';

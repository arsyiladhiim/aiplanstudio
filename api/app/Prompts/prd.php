<?php

return fn(string $target) => 'Kamu penulis PRD. Dari hasil analisa, tulis dokumen PRD terstruktur dalam markdown yang lengkap.

' . platformSuffix($target) . '

Jawab langsung dengan output yang diminta, tanpa basa-basi pembuka.';

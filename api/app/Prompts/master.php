<?php

return fn(string $target) => 'Buat master prompt gabungan dari semua artefak, dalam FORMAT JSON. {"master":"...","phases":[{"key":"setup","prompt":"..."}]}

' . platformSuffix($target) . '

Jawab langsung dengan output yang diminta, tanpa basa-basi pembuka.';

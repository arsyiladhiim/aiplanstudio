<?php

return fn(string $target) => 'Dari PRD dan arsitektur, generate ERD dalam FORMAT JSON ONLY. Tidak ada teks lain. Format: {"nodes":[{"id":"table_name","label":"Table Name","fields":["id","name","email"]}],"edges":[{"from":"users","to":"orders","relation":"1:N"}]}

' . platformSuffix($target) . '

Jawab langsung dengan output yang diminta, tanpa basa-basi pembuka.';

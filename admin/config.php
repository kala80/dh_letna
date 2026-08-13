<?php
// ============================================================
//  DH Letná – nastavení administrace ceníku
//  https://dh-letna.cz/admin/
// ============================================================

// Přihlašovací jméno.
define('ADMIN_USER', 'Wendy_N');

// Heslo je uložené bezpečně jako otisk (hash), ne čitelně.
// Nastavené heslo:  Hygiena-Letna-7656
// Změnit se dá přímo v administraci dole v části „Změna hesla“.
define('ADMIN_HASH', '$2y$12$G.ErWVsSPoQDQU7HiTQf8eqDWZl9gni4.f8LTea1Xy4BSWZB3RWTm');

// Kolik záloh ceníku se má uchovávat (starší se mažou automaticky).
define('BACKUP_KEEP', 20);

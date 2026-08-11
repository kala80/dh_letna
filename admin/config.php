<?php
// ============================================================
//  DH Letná – nastavení administrace ceníku
// ============================================================

// Přihlašovací heslo (uloženo bezpečně jako hash, ne čitelně).
// Výchozí heslo je:  DHletna2026
// Po prvním přihlášení si ho prosím změňte přímo v administraci.
define('ADMIN_HASH', '$2y$12$wH3n0ZafB6EgQRmeIUpWW.VCnOMKRsN.kxYqxYQLfu.aVm2F8L.1W');

// Kolik záloh ceníku se má uchovávat (starší se mažou automaticky).
define('BACKUP_KEEP', 20);

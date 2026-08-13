# Správa ceníku – návod

## Přihlášení

Adresa: **https://dh-letna.cz/admin/**

- Přihlašovací jméno: **Wendy_N**
- Heslo: **Hygiena-Letna-7656**

Heslo se dá kdykoli změnit dole na stránce v části „Změna hesla“.
Jméno se mění v souboru `admin/config.php` (řádek `ADMIN_USER`).

## Úprava ceníku

1. Přepište, co potřebujete – název, upřesnění nebo cenu.
2. Klikněte na **Uložit změny**.
3. Hotovo, změna je na webu okamžitě. Přes tlačítko **Zobrazit ceník** si ji můžete rovnou zkontrolovat.

Pokud novou cenu na webu nevidíte, dejte v prohlížeči tvrdé obnovení: **Ctrl + F5** (na Macu **Cmd + Shift + R**).

### Co znamenají jednotlivá pole

| Pole | K čemu je |
|---|---|
| **Název** | Hlavní název položky, tučně. |
| **Upřesnění** | Menší šedý text za názvem, například „včetně Air Flow“. Může zůstat prázdné. |
| **Cena** | Pište jen číslo, třeba `2080`. Samo se z toho udělá „2 080 Kč“. Můžete napsat i text, například `na dotaz`. |
| **zvýraznit** | Udělá z řádku barevný pruh (teď to má bělení). |
| **na úvodku** | Určuje, která cena se ukazuje v bloku „Bělení zubů“ na hlavní stránce. Může být zaškrtnutá jen jedna položka. |

### Přidání, smazání a pořadí

- **+ Přidat položku** přidá prázdný řádek dole.
- **↑ ↓** posunou řádek nahoru nebo dolů.
- **×** řádek smaže (zeptá se na potvrzení).

Nezapomeňte pak kliknout na **Uložit změny**, jinak se úpravy neprojeví.

## Co se děje na pozadí

Po uložení se přepíše:

- tabulka na stránce **Ceník**
- **strukturovaná data pro Google** (ceny ve vyhledávání)
- **cena bělení na úvodní stránce**

Před každým uložením se udělá záloha do složky `data/backups/`. Drží se posledních 20 verzí,
takže když se něco pokazí, dá se to vrátit zkopírováním souborů ze zálohy.

## Když něco nefunguje

**„Nepodařilo se zapsat…“** – hosting nemá povolený zápis do souborů. Ve správci souborů u Wedosu
nastavte práva: soubory `index.html`, `cenik/index.html`, `data/cenik.json` a `admin/config.php`
na **664**, složky `data` a `data/backups` na **775**.

**Zapomenuté heslo** – v souboru `admin/config.php` nahraďte řádek s `ADMIN_HASH` tímto
(vrátí heslo na `Hygiena-Letna-7656`):

```php
define('ADMIN_HASH', '$2y$12$G.ErWVsSPoQDQU7HiTQf8eqDWZl9gni4.f8LTea1Xy4BSWZB3RWTm');
```

## Pozor na jednu věc

V popisku stránky Ceník pro vyhledávače (`meta description`) jsou ceny napsané natvrdo
(„vstupní hygiena od 2 080 Kč … bělení 9 900 Kč“). Ten se z administrace nepřepisuje –
kdyby se ceny výrazně změnily, je potřeba ho upravit ručně v souboru `cenik/index.html`,
nebo mi dejte vědět a doplním to.

<?php
// ============================================================
//  DH Letná – administrace ceníku
//  Po uložení přepíše statické HTML (ceník + úvodní stránka).
// ============================================================
declare(strict_types=1);
session_start();

$CONFIG = __DIR__ . '/config.php';
require $CONFIG;

$ROOT        = dirname(__DIR__);
$JSON_FILE   = $ROOT . '/data/cenik.json';
$CENIK_HTML  = $ROOT . '/cenik/index.html';
$HOME_HTML   = $ROOT . '/index.html';
$BACKUP_DIR  = $ROOT . '/data/backups';

// ---------- pomocné funkce ----------------------------------

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** "2080" nebo "2 080" -> "2 080 Kč"; volný text (např. "na dotaz") nechá být */
function fmt_price(string $raw): string {
    $t = trim(str_replace(["\xC2\xA0", 'Kč', 'kč', 'KČ'], [' ', '', '', ''], $raw));
    $t = trim($t);
    if ($t === '') return '';
    if (preg_match('/^\d[\d\s]*$/u', $t)) {
        $n = (int) preg_replace('/\D/', '', $t);
        return number_format($n, 0, ',', ' ') . ' Kč';
    }
    return $t;
}

/** číselná hodnota ceny pro strukturovaná data Google, jinak null */
function price_number(string $raw): ?int {
    $t = trim(str_replace(["\xC2\xA0", 'Kč', 'kč', 'KČ'], [' ', '', '', ''], $raw));
    if (preg_match('/^\d[\d\s]*$/u', trim($t))) {
        return (int) preg_replace('/\D/', '', $t);
    }
    return null;
}

function replace_between(string $html, string $start, string $end, string $new): ?string {
    $a = strpos($html, $start);
    $b = strpos($html, $end);
    if ($a === false || $b === false || $b < $a) return null;
    $a += strlen($start);
    return substr($html, 0, $a) . $new . substr($html, $b);
}

function render_rows(array $items): string {
    $out = [];
    foreach ($items as $it) {
        $cls  = !empty($it['feature']) ? 'price-row is-feature reveal' : 'price-row reveal';
        $name = h($it['name']);
        if (trim($it['note']) !== '') {
            $name .= ' <small>' . h($it['note']) . '</small>';
        }
        $out[] = '      <div class="' . $cls . '">';
        $out[] = '        <div class="price-row__name">' . $name . '</div>';
        $out[] = '        <div class="price-row__tag">' . h(fmt_price($it['price'])) . '</div>';
        $out[] = '      </div>';
    }
    return "\n" . implode("\n", $out) . "\n      ";
}

/** aktualizuje offers ve strukturovaných datech (JSON-LD) */
function update_ldjson(string $html, array $items): string {
    if (!preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m, PREG_SET_ORDER)) {
        return $html;
    }
    foreach ($m as $block) {
        if (strpos($block[1], 'OfferCatalog') === false) continue;
        $data = json_decode(trim($block[1]), true);
        if (!is_array($data) || !isset($data['mainEntity']['itemListElement'])) continue;

        $offers = [];
        foreach ($items as $it) {
            $num = price_number($it['price']);
            if ($num === null) continue;
            $label = trim($it['name'] . ' ' . trim($it['note']));
            $offers[] = [
                '@type'         => 'Offer',
                'priceCurrency' => 'CZK',
                'price'         => (string) $num,
                'itemOffered'   => ['@type' => 'Service', 'name' => $label],
            ];
        }
        $data['mainEntity']['itemListElement'] = $offers;
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $html = str_replace($block[0], '<script type="application/ld+json">' . "\n" . $json . "\n" . '</script>', $html);
    }
    return $html;
}

function rotate_backups(string $dir, int $keep): void {
    $all = glob($dir . '/*', GLOB_ONLYDIR) ?: [];
    if (count($all) <= $keep) return;
    sort($all);
    foreach (array_slice($all, 0, count($all) - $keep) as $old) {
        foreach (glob($old . '/*') ?: [] as $f) @unlink($f);
        @rmdir($old);
    }
}

// ---------- přihlášení --------------------------------------

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf_ok = isset($_POST['csrf']) && hash_equals($_SESSION['csrf'], (string) $_POST['csrf']);

if (isset($_GET['odhlasit'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$login_error = '';
if (isset($_POST['heslo']) && !isset($_SESSION['auth'])) {
    if (!empty($_SESSION['blok']) && $_SESSION['blok'] > time()) {
        $login_error = 'Příliš mnoho pokusů. Zkuste to prosím za chvíli.';
    } elseif (password_verify((string) $_POST['heslo'], ADMIN_HASH)) {
        session_regenerate_id(true);
        $_SESSION['auth'] = true;
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
        header('Location: index.php');
        exit;
    } else {
        $_SESSION['pokusy'] = ($_SESSION['pokusy'] ?? 0) + 1;
        if ($_SESSION['pokusy'] >= 5) {
            $_SESSION['blok']    = time() + 120;
            $_SESSION['pokusy']  = 0;
        }
        sleep(1);
        $login_error = 'Nesprávné heslo.';
    }
}

$authed = !empty($_SESSION['auth']);

// ---------- uložení ceníku ----------------------------------

$msg = '';
$err = '';

if ($authed && isset($_POST['akce']) && $_POST['akce'] === 'ulozit') {
    if (!$csrf_ok) {
        $err = 'Vypršelo přihlášení, načtěte stránku znovu.';
    } else {
        $payload = json_decode((string) ($_POST['payload'] ?? ''), true);
        if (!is_array($payload) || !$payload) {
            $err = 'Nepodařilo se načíst data z formuláře.';
        } else {
            $items = [];
            foreach ($payload as $row) {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '') continue;
                $items[] = [
                    'name'    => $name,
                    'note'    => trim((string) ($row['note'] ?? '')),
                    'price'   => trim((string) ($row['price'] ?? '')),
                    'feature' => !empty($row['feature']),
                    'promo'   => !empty($row['promo']),
                ];
            }
            if (!$items) {
                $err = 'Ceník musí mít alespoň jednu položku.';
            } else {
                // záloha
                $stamp = date('Y-m-d_His');
                @mkdir($BACKUP_DIR . '/' . $stamp, 0775, true);
                foreach ([$JSON_FILE => 'cenik.json', $CENIK_HTML => 'cenik.html', $HOME_HTML => 'index.html'] as $src => $as) {
                    if (is_file($src)) @copy($src, $BACKUP_DIR . '/' . $stamp . '/' . $as);
                }
                rotate_backups($BACKUP_DIR, BACKUP_KEEP);

                $problems = [];

                // 1) datový soubor
                $data = ['items' => $items, 'updated' => date('c')];
                if (@file_put_contents($JSON_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
                    $problems[] = 'data/cenik.json';
                }

                // 2) tabulka na stránce Ceník + strukturovaná data
                $html = @file_get_contents($CENIK_HTML);
                if ($html !== false) {
                    $new = replace_between($html, '<!-- CENIK:ROWS:START -->', '<!-- CENIK:ROWS:END -->', render_rows($items));
                    if ($new === null) {
                        $problems[] = 'cenik/index.html (chybí značky CENIK:ROWS)';
                    } else {
                        $new = update_ldjson($new, $items);
                        if (@file_put_contents($CENIK_HTML, $new) === false) $problems[] = 'cenik/index.html';
                    }
                } else {
                    $problems[] = 'cenik/index.html';
                }

                // 3) cena na úvodní stránce
                $promo = null;
                foreach ($items as $it) if (!empty($it['promo'])) { $promo = $it; break; }
                if ($promo) {
                    $home = @file_get_contents($HOME_HTML);
                    if ($home !== false) {
                        $txt = str_replace(' ', '&nbsp;', fmt_price($promo['price']));
                        $new = replace_between($home, '<!-- CENIK:PROMO:START -->', '<!-- CENIK:PROMO:END -->', $txt);
                        if ($new !== null && @file_put_contents($HOME_HTML, $new) === false) {
                            $problems[] = 'index.html';
                        }
                    }
                }

                if ($problems) {
                    $err = 'Nepodařilo se zapsat: ' . implode(', ', $problems)
                         . '. Zkontrolujte prosím práva k zápisu (chmod 664 pro soubory, 775 pro složky).';
                } else {
                    $msg = 'Ceník byl uložen a je vidět na webu.';
                }
            }
        }
    }
}

// ---------- změna hesla -------------------------------------

if ($authed && isset($_POST['akce']) && $_POST['akce'] === 'heslo') {
    if (!$csrf_ok) {
        $err = 'Vypršelo přihlášení, načtěte stránku znovu.';
    } else {
        $n1 = (string) ($_POST['nove1'] ?? '');
        $n2 = (string) ($_POST['nove2'] ?? '');
        if (mb_strlen($n1) < 8) {
            $err = 'Nové heslo musí mít alespoň 8 znaků.';
        } elseif ($n1 !== $n2) {
            $err = 'Hesla se neshodují.';
        } else {
            $hash = password_hash($n1, PASSWORD_BCRYPT, ['cost' => 12]);
            $cfg  = (string) @file_get_contents($CONFIG);
            $cfg2 = preg_replace("/define\('ADMIN_HASH', '.*?'\);/", "define('ADMIN_HASH', '" . $hash . "');", $cfg, 1);
            if ($cfg2 && @file_put_contents($CONFIG, $cfg2) !== false) {
                $msg = 'Heslo bylo změněno.';
            } else {
                $err = 'Soubor admin/config.php nejde zapsat. Vložte do něj prosím ručně tento řádek: '
                     . "define('ADMIN_HASH', '" . $hash . "');";
            }
        }
    }
}

// ---------- načtení dat -------------------------------------

$items = [];
if (is_file($JSON_FILE)) {
    $d = json_decode((string) file_get_contents($JSON_FILE), true);
    $items = $d['items'] ?? [];
}
$updated = $d['updated'] ?? '';
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Správa ceníku – DH Letná</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root { --blue:#276ad6; --blue-dark:#1f57b4; --blue-soft:#eaf1fd; --ink:#1f2430; --muted:#6b7280; --line:#e3e8ef; }
  * { box-sizing:border-box; font-family:'Plus Jakarta Sans',system-ui,sans-serif; }
  body { margin:0; background:#f4f7fb; color:var(--ink); }
  header { background:#fff; border-bottom:1px solid var(--line); padding:16px 24px; display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; }
  header b { font-size:1.05rem; }
  header span { color:var(--muted); font-size:.85rem; }
  main { max-width:960px; margin:0 auto; padding:28px 20px 80px; }
  .card { background:#fff; border:1px solid var(--line); border-radius:14px; padding:22px; box-shadow:0 6px 20px rgba(20,35,70,.05); margin-bottom:22px; }
  h1 { font-size:1.5rem; margin:0 0 6px; }
  h2 { font-size:1.05rem; margin:0 0 14px; }
  .hint { color:var(--muted); font-size:.9rem; margin:0 0 18px; line-height:1.6; }
  table { width:100%; border-collapse:collapse; }
  th { text-align:left; font-size:.75rem; text-transform:uppercase; letter-spacing:.08em; color:var(--muted); padding:0 8px 10px; font-weight:600; }
  td { padding:6px 8px; vertical-align:middle; border-top:1px solid var(--line); }
  input[type=text] { width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:8px; font-size:.92rem; background:#fff; }
  input[type=text]:focus { outline:2px solid var(--blue); outline-offset:-1px; border-color:transparent; }
  .col-price input { text-align:right; }
  .btn { display:inline-flex; align-items:center; gap:8px; border:none; cursor:pointer; font-weight:600; font-size:.92rem; padding:11px 20px; border-radius:8px; background:var(--blue); color:#fff; text-decoration:none; }
  .btn:hover { background:var(--blue-dark); }
  .btn--ghost { background:#fff; color:var(--blue-dark); border:1px solid var(--line); }
  .btn--ghost:hover { background:var(--blue-soft); }
  .icon-btn { border:1px solid var(--line); background:#fff; border-radius:7px; width:32px; height:32px; cursor:pointer; color:var(--muted); font-size:.95rem; line-height:1; }
  .icon-btn:hover { border-color:var(--blue); color:var(--blue); }
  .icon-btn.del:hover { border-color:#dc2626; color:#dc2626; }
  .row-actions { display:flex; gap:5px; }
  .msg { padding:13px 16px; border-radius:10px; margin-bottom:20px; font-size:.93rem; }
  .msg--ok { background:#e7f6ec; color:#166534; }
  .msg--err { background:#fdeaea; color:#a1231f; }
  .bar { display:flex; gap:12px; align-items:center; margin-top:20px; flex-wrap:wrap; }
  label.chk { display:flex; align-items:center; gap:7px; font-size:.85rem; color:var(--muted); white-space:nowrap; cursor:pointer; }
  .login { max-width:380px; margin:12vh auto; }
  .login input[type=password] { width:100%; padding:12px 14px; border:1px solid var(--line); border-radius:8px; font-size:1rem; margin-bottom:14px; }
  details { margin-top:6px; }
  summary { cursor:pointer; color:var(--muted); font-size:.9rem; }
  @media (max-width:760px) {
    thead { display:none; }
    td { display:block; border:none; padding:4px 0; }
    tbody tr { display:block; border-top:1px solid var(--line); padding:14px 0; }
    td::before { content:attr(data-l); display:block; font-size:.72rem; text-transform:uppercase; letter-spacing:.07em; color:var(--muted); margin-bottom:4px; }
    .col-price input { text-align:left; }
  }
</style>
</head>
<body>

<?php if (!$authed): ?>
  <main>
    <div class="card login">
      <h1>Správa ceníku</h1>
      <p class="hint">Zadejte prosím heslo.</p>
      <?php if ($login_error): ?><div class="msg msg--err"><?= h($login_error) ?></div><?php endif; ?>
      <form method="post">
        <input type="password" name="heslo" placeholder="Heslo" autofocus required>
        <button class="btn" type="submit" style="width:100%; justify-content:center;">Přihlásit se</button>
      </form>
    </div>
  </main>

<?php else: ?>
  <header>
    <div>
      <b>Správa ceníku – DH Letná</b><br>
      <span><?php if ($updated) { echo 'Naposledy upraveno: ' . h(date('j. n. Y H:i', strtotime($updated))); } ?></span>
    </div>
    <div style="display:flex; gap:10px;">
      <a class="btn btn--ghost" href="../cenik/" target="_blank">Zobrazit ceník</a>
      <a class="btn btn--ghost" href="?odhlasit=1">Odhlásit</a>
    </div>
  </header>

  <main>
    <?php if ($msg): ?><div class="msg msg--ok"><?= h($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="msg msg--err"><?= h($err) ?></div><?php endif; ?>

    <form method="post" id="form" class="card">
      <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
      <input type="hidden" name="akce" value="ulozit">
      <input type="hidden" name="payload" id="payload">

      <h1>Ceník</h1>
      <p class="hint">
        Cenu pište jen číslem, například <strong>2080</strong> – doplní se automaticky jako „2 080 Kč“.
        Můžete napsat i text, třeba „na dotaz“.<br>
        <strong>Upřesnění</strong> je menší šedý text pod názvem. <strong>Zvýraznit</strong> udělá z řádku barevný pruh.
        <strong>Na úvodku</strong> určuje, která cena se ukazuje v bloku Bělení zubů na hlavní stránce.
      </p>

      <table>
        <thead>
          <tr>
            <th style="width:33%">Název</th>
            <th style="width:33%">Upřesnění</th>
            <th style="width:14%">Cena</th>
            <th style="width:12%">Zobrazení</th>
            <th style="width:8%"></th>
          </tr>
        </thead>
        <tbody id="rows">
          <?php foreach ($items as $i => $it): ?>
          <tr>
            <td data-l="Název"><input type="text" class="f-name" value="<?= h($it['name']) ?>"></td>
            <td data-l="Upřesnění"><input type="text" class="f-note" value="<?= h($it['note'] ?? '') ?>"></td>
            <td data-l="Cena" class="col-price"><input type="text" class="f-price" value="<?= h($it['price'] ?? '') ?>"></td>
            <td data-l="Zobrazení">
              <label class="chk"><input type="checkbox" class="f-feature" <?= !empty($it['feature']) ? 'checked' : '' ?>> zvýraznit</label>
              <label class="chk"><input type="radio" name="promo" class="f-promo" <?= !empty($it['promo']) ? 'checked' : '' ?>> na úvodku</label>
            </td>
            <td data-l="Pořadí">
              <div class="row-actions">
                <button type="button" class="icon-btn up" title="Nahoru">&uarr;</button>
                <button type="button" class="icon-btn down" title="Dolů">&darr;</button>
                <button type="button" class="icon-btn del" title="Smazat">&times;</button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div class="bar">
        <button type="button" class="btn btn--ghost" id="add">+ Přidat položku</button>
        <button type="submit" class="btn">Uložit změny</button>
      </div>
    </form>

    <div class="card">
      <h2>Změna hesla</h2>
      <form method="post" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
        <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
        <input type="hidden" name="akce" value="heslo">
        <input type="password" name="nove1" placeholder="Nové heslo" required style="padding:10px 12px; border:1px solid var(--line); border-radius:8px;">
        <input type="password" name="nove2" placeholder="Heslo znovu" required style="padding:10px 12px; border:1px solid var(--line); border-radius:8px;">
        <button class="btn btn--ghost" type="submit">Změnit heslo</button>
      </form>
    </div>
  </main>

  <template id="tpl">
    <tr>
      <td data-l="Název"><input type="text" class="f-name" value=""></td>
      <td data-l="Upřesnění"><input type="text" class="f-note" value=""></td>
      <td data-l="Cena" class="col-price"><input type="text" class="f-price" value=""></td>
      <td data-l="Zobrazení">
        <label class="chk"><input type="checkbox" class="f-feature"> zvýraznit</label>
        <label class="chk"><input type="radio" name="promo" class="f-promo"> na úvodku</label>
      </td>
      <td data-l="Pořadí">
        <div class="row-actions">
          <button type="button" class="icon-btn up" title="Nahoru">&uarr;</button>
          <button type="button" class="icon-btn down" title="Dolů">&darr;</button>
          <button type="button" class="icon-btn del" title="Smazat">&times;</button>
        </div>
      </td>
    </tr>
  </template>

  <script>
    var rows = document.getElementById('rows');

    rows.addEventListener('click', function (e) {
      var b = e.target.closest('button'); if (!b) return;
      var tr = b.closest('tr');
      if (b.classList.contains('up')   && tr.previousElementSibling) tr.parentNode.insertBefore(tr, tr.previousElementSibling);
      if (b.classList.contains('down') && tr.nextElementSibling)     tr.parentNode.insertBefore(tr.nextElementSibling, tr);
      if (b.classList.contains('del')) {
        if (rows.children.length === 1) { alert('Ceník musí mít alespoň jednu položku.'); return; }
        if (confirm('Opravdu smazat tuto položku?')) tr.remove();
      }
    });

    document.getElementById('add').addEventListener('click', function () {
      rows.appendChild(document.getElementById('tpl').content.cloneNode(true));
      rows.lastElementChild.querySelector('.f-name').focus();
    });

    document.getElementById('form').addEventListener('submit', function (e) {
      var data = [];
      Array.prototype.forEach.call(rows.children, function (tr) {
        var name = tr.querySelector('.f-name').value.trim();
        if (!name) return;
        data.push({
          name:    name,
          note:    tr.querySelector('.f-note').value.trim(),
          price:   tr.querySelector('.f-price').value.trim(),
          feature: tr.querySelector('.f-feature').checked,
          promo:   tr.querySelector('.f-promo').checked
        });
      });
      if (!data.length) { e.preventDefault(); alert('Ceník musí mít alespoň jednu položku.'); return; }
      document.getElementById('payload').value = JSON.stringify(data);
    });
  </script>
<?php endif; ?>

</body>
</html>

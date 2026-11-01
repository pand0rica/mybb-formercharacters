# Integration mit Spielerverzeichnis

## Strategie: Zwei unabhängige Ansätze

Die Integration nutzt je nach Feature zwei verschiedene Ansätze:

| Feature | Ansatz | playerdirectory.php ändern? |
|---------|--------|----------------------------|
| Archivierte Charaktere-Box im Playerstat | Hook `playerdirectory_playerstat_characters_html` | **Ja (Änderung 2)** |
| Post/Szenen-Summen mit archivierten Chars | Hook-Calls in playerdirectory.php | **Ja (Änderungen 0–3) – vollständig implementiert** |

---

## Ansatz A – Template-Cache-Injection (veraltet, nicht mehr aktiv)

**Implementiert in:** `char_archive_misc_playerstat_inject()` in `char_archive.php`

**Nicht mehr registriert**, seit Hook 2 (`playerdirectory_playerstat_characters_html`) die Charakterliste direkt in `$characters_bit` einfügt. Beide Ansätze gleichzeitig aktiv würden die archivierten Charaktere doppelt anzeigen.

Die Funktion `char_archive_misc_playerstat_inject()` bleibt im Code als Fallback erhalten, wird aber nicht über `$plugins->add_hook()` registriert.

---

## Ansatz B – Hook-Calls in playerdirectory.php (für Summen-Integration)

**Status: Vollständig implementiert.**

Alle Änderungen erfolgen in `inc/plugins/playerdirectory.php`:

| # | Was | Wo | Status |
|---|-----|----|----|
| 0 | `$plugins` zur `global`-Deklaration hinzufügen | Anfang von `playerdirectory_misc()` | ✓ |
| 1 | Hook `playerdirectory_directory_user_stats` | Nach Berechnung der Spieler-Summen im Verzeichnis | ✓ |
| 2 | Hook `playerdirectory_playerstat_characters_html` | Nach der Charakter-Schleife im Playerstat | ✓ |
| 3 | Hook `playerdirectory_playerstat_totals` | Nach Neuberechnung der Post/Szenen-Summen im Playerstat | ✓ |

---

## Änderung 0 – `$plugins` als global deklarieren

**Ohne diese Zeile schlagen alle `run_hooks()`-Aufrufe lautlos fehl** (`$plugins` ist `null` im Funktionsscope). Das ist die häufigste Fehlerquelle.

Suche nach dem Anfang der Funktion:

```php
function playerdirectory_misc()
{
    global $db, $cache, $mybb, $lang, $templates, ...;
```

`, $plugins` ans **Ende** der bestehenden `global`-Zeile anhängen:

```php
function playerdirectory_misc()
{
    global $db, $cache, $mybb, $lang, $templates, ..., $plugins;
```

---

## Änderung 1 – Inplay-Summen im Verzeichnis (`action == playerdirectory`)

### Wo im Code

Suche nach dieser Zeile (zuletzt bei ca. Zeile 2788 in der xampp74-Version):

```php
$player_inplaystat = $lang->sprintf($lang->playerdirectory_directory_user_inplaystat, $allinplayposts_formatted, $allinplayscenes_formatted);
```

### Kontext

Unmittelbar davor berechnet das Plugin die Spieler-Summen und formatiert sie:

```php
if($allinplayscenes > 0) {
    $allinplayscenes_formatted = number_format($allinplayscenes, '0', ',', '.');
} else {
    $allinplayscenes_formatted = 0;
}

// <-- HIER einfügen

$player_inplaystat = $lang->sprintf($lang->playerdirectory_directory_user_inplaystat, $allinplayposts_formatted, $allinplayscenes_formatted);
```

### Einzufügender Code

```php
// Character Archive: add archived characters to player totals
$pd_stats = array(
    'main_uid'            => intval($playerID),
    'player_uid'          => intval($playerID),
    'allinplayposts'      => intval($count_allinplayposts),
    'allinplayscenes'     => intval($allinplayscenes),
    'inplaytrackersystem' => intval($inplaytrackersystem),
);
$plugins->run_hooks('playerdirectory_directory_user_stats', $pd_stats);
$count_allinplayposts = max(0, intval($pd_stats['allinplayposts']));
$allinplayscenes      = max(0, intval($pd_stats['allinplayscenes']));
$allinplayposts_formatted = $count_allinplayposts > 0
    ? number_format($count_allinplayposts, '0', ',', '.')
    : 0;
$allinplayscenes_formatted = $allinplayscenes > 0
    ? number_format($allinplayscenes, '0', ',', '.')
    : 0;
```

> Die formatierten Variablen werden nach dem Hook neu berechnet, weil der Hook die Rohwerte verändert. Erst danach darf `$player_inplaystat` gebaut werden.

---

## Änderung 2 – Charakter-HTML in der Spielerstatistik (`action == playerstatistic`)

### Wo im Code

Suche nach dieser Zeile (zuletzt bei ca. Zeile 3393) – letzte Zeile innerhalb der Charakter-Schleife:

```php
eval("\$characters_bit .= \"".$templates->get("playerdirectory_playerstat_characters")."\";");
```

### Kontext

```php
        eval("\$characters_bit .= \"".$templates->get("playerdirectory_playerstat_characters")."\";");
    }   // <-- Ende der Charakter-Schleife

    // letztes Komma vom UID String entfernen
    $userids_string = substr($userids, 0, -1);
```

### Einzufügender Code

Einfügen **nach** der schließenden `}` und **vor** `$userids_string = substr(...)`:

```php
// Character Archive: add archived characters to character list and totals
$pd_characters = array(
    'main_uid'                  => intval($mainID),
    'player_uid'                => intval($playerID),
    'characters_html'           => (string)$characters_bit,
    'allinplayposts'            => intval($count_allinplayposts),
    'allinplayscenes'           => intval($allinplayscenes),
    'allinplayposts_formatted'  => (string)$allinplayposts_formatted,
    'allinplayscenes_formatted' => (string)$allinplayscenes_formatted,
    'inplaytrackersystem'       => intval($inplaytrackersystem),
);
$plugins->run_hooks('playerdirectory_playerstat_characters_html', $pd_characters);
$characters_bit            = (string)$pd_characters['characters_html'];
$count_allinplayposts      = max(0, intval($pd_characters['allinplayposts']));
$allinplayscenes           = max(0, intval($pd_characters['allinplayscenes']));
$allinplayposts_formatted  = $pd_characters['allinplayposts_formatted'];
$allinplayscenes_formatted = $pd_characters['allinplayscenes_formatted'];
```

> **Wichtig:** Spielerverzeichnis berechnet `$count_allinplayposts` und `$allinplayscenes` weiter unten im Code erneut aus der Datenbank und überschreibt damit die hier gesetzten Werte. Nur `$characters_bit` (die HTML-Ausgabe) bleibt dauerhaft erhalten. Die korrekten Gesamt-Summen mit archivierten Charakteren werden von **Änderung 3** gesetzt.

> `$mainID` ist die UID des Hauptaccounts (Spielers). `$playerID` kann je nach Spielerverzeichnis-Version identisch sein.

---

## Änderung 3 – Post/Szenen-Gesamtzahl nach Neuberechnung (`action == playerstatistic`)

### Warum ein dritter Hook nötig ist

Spielerverzeichnis berechnet `$count_allinplayposts` und `$allinplayscenes` nach dem `playerstat_characters_html`-Hook **erneut** aus der Datenbank (nur registrierte Charaktere, via `$userids_string`). Werte, die Änderung 2 setzt, werden dadurch überschrieben. Dieser Hook feuert **nach** dieser Neuberechnung.

### Wo im Code

Suche nach diesem Block (zuletzt bei ca. Zeile 3675 in der xampp74-Version):

```php
        // Anzahl Inplay-Szenen
        if ($sceneTIDs_array != 0) {
            $allinplayscenes = count($sceneTIDs_array);
        } else {
            $allinplayscenes = 0;
        }
        if($allinplayscenes > 0) {
            $allinplayscenes_formatted = number_format($allinplayscenes, '0', ',', '.');
        } else {
            $allinplayscenes_formatted = 0;
        }
```

Direkt danach folgt der Kommentar: `// heißeste Szene => die meisten Posts`

### Einzufügender Code

Einfügen **nach** dem `$allinplayscenes_formatted`-Block und **vor** dem Kommentar zur heißesten Szene:

```php
// Character Archive: add archived characters after recalculation of totals
$pd_totals = array(
    'main_uid'                  => intval($mainID),
    'player_uid'                => intval($playerID),
    'allinplayposts'            => intval($count_allinplayposts),
    'allinplayscenes'           => intval($allinplayscenes),
    'allinplayposts_formatted'  => (string)$allinplayposts_formatted,
    'allinplayscenes_formatted' => (string)$allinplayscenes_formatted,
    'inplaytrackersystem'       => intval($inplaytrackersystem),
);
$plugins->run_hooks('playerdirectory_playerstat_totals', $pd_totals);
$count_allinplayposts      = max(0, intval($pd_totals['allinplayposts']));
$allinplayscenes           = max(0, intval($pd_totals['allinplayscenes']));
$allinplayposts_formatted  = $pd_totals['allinplayposts_formatted'];
$allinplayscenes_formatted = $pd_totals['allinplayscenes_formatted'];
```

---

## Reihenfolge der Hooks im Playerstat

```
Charakter-Schleife (registrierte Charaktere)
    └─ eval characters_bit
    └─ Hook: playerstat_characters_html  ← fügt archivierte Chars zur HTML-Liste hinzu
        └─ characters_bit = aktualisiert (enthält jetzt auch archivierte Chars)

$userids_string, $userids_array aufbauen (nur registrierte UIDs)
Szenen-TIDs aller registrierten Chars abfragen
$count_allinplayposts = DB-Abfrage (nur registrierte Chars)  ← überschreibt Hook-2-Wert
$allinplayscenes = count($sceneTIDs_array)                   ← überschreibt Hook-2-Wert

Hook: playerstat_totals  ← addiert archivierte Post/Szenen-Summen auf registrierte Summen
    └─ $count_allinplayposts = registriert + archiviert (korrekt)
    └─ $allinplayscenes = registriert + archiviert (korrekt)

Weitere Statistiken (heißeste Szene, Wörter, Zeichen …)
```

---

## Warum Hook-only

- Spielerverzeichnis erhält drei `$plugins->run_hooks()`-Aufrufe und eine `global`-Ergänzung.
- Kein SQL gegen Archivtabellen in Spielerverzeichnis.
- Keine Archiv-Logik in Spielerverzeichnis.
- Die Integration kann pro Plugin ein- oder ausgeschaltet werden, ohne Spielerverzeichnis erneut anzufassen.

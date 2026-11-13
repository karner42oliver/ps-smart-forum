# Quick Forum

**Deutsch** | [English](README.en.md)

[![Version](https://img.shields.io/badge/Version-1.0.0-2271b1?style=flat-square)](readme.txt)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-777bb4?style=flat-square&logo=php&logoColor=white)
![WordPress](https://img.shields.io/badge/WordPress-bis%207.1.4-21759b?style=flat-square&logo=wordpress&logoColor=white)
![ClassicPress](https://img.shields.io/badge/ClassicPress-2.7.4-03768e?style=flat-square)
[![Lizenz](https://img.shields.io/badge/Lizenz-GPL--3.0--or--later-2ea44f?style=flat-square)](https://www.gnu.org/licenses/gpl-3.0.html)

Ein Forum direkt auf Deiner Website: Mit Quick Forum bindest Du Diskussionen per
Shortcode in Seiten oder Beiträge ein. Du brauchst dafür kein separates
Forum-Portal.

**Version: 1.0.0**

## Das bietet Quick Forum

- Mehrere Foren mit eigenen Namen, Beschreibungen und Farben.
- Themen und Antworten direkt auf Deiner Website.
- Suche innerhalb eines Forums.
- Beitragsvorschau vor dem Absenden.
- Angeheftete und geschlossene Themen.
- Direktlinks zur letzten Antwort.
- Einstellbare Berechtigungen zum Bearbeiten von Thementiteln.
- Eine Oberfläche, die sich auch auf Smartphones bedienen lässt.
- Unterstützung für Multisite-Netzwerke mit eigenen Foren je Website.

## Installation

Du benötigst WordPress oder ClassicPress und mindestens PHP 8.0.

1. Lade das Plugin im Administrationsbereich unter **Plugins → Installieren →
   Plugin hochladen** als ZIP-Datei hoch. Alternativ kannst Du den Plugin-Ordner
   in das Verzeichnis `wp-content/plugins/` kopieren.
2. Aktiviere **Quick Forum**.
3. Öffne den neuen Menüpunkt **Foren**.

In einer Multisite-Installation kannst Du das Plugin über die
Netzwerkverwaltung für das Netzwerk aktivieren.

## Dein erstes Forum

1. Öffne **Foren → Neues Forum**.
2. Vergib einen **Foren-Namen** und auf Wunsch eine Beschreibung.
3. Wähle die Farben und die gewünschte Rahmenbreite.
4. Klicke auf **Speichern**.
5. Kopiere den Shortcode aus der Forenübersicht.
6. Füge ihn in eine Seite oder einen Beitrag ein und veröffentliche diese.

Beispiel:

```text
[forum id="123"]
```

Ersetze `123` durch die ID Deines Forums. Am einfachsten kopierst Du den
fertigen Shortcode aus der Übersicht, statt ihn selbst zu schreiben.

### Mehrere Foren auf einer Seite

Du kannst unterschiedliche Foren auf derselben Seite einbinden:

```text
[forum id="123"]

[forum id="456"]
```

Suche, Formulare und Seitenwechsel bleiben dem jeweiligen Forum zugeordnet.

## Themen und Antworten

Zum Erstellen von Themen und Antworten müssen Besucher angemeldet sein.
In einem Multisite-Netzwerk müssen sie außerdem zur jeweiligen Website gehören.

- **Thema erstellen:** Öffne die Seite mit Deinem Forum und wähle die Funktion
  zum Erstellen eines neuen Themas. Gib einen Titel und Deinen Beitrag ein.
- **Antworten:** Öffne ein Thema und nutze das Antwortformular.
- **Vorschau:** Prüfe Deinen Beitrag vor dem Absenden. Die Vorschau speichert
  noch nichts.
- **Suchen:** Nutze das Suchfeld, um Beiträge im jeweiligen Forum zu finden.
- **Letzte Antwort:** Der entsprechende Link in der Themenübersicht führt
  direkt zum letzten Beitrag, auch wenn dieser auf einer späteren Seite steht.

Eigene Beiträge können Autoren innerhalb von 30 Minuten bearbeiten.
Administratoren sind dabei nicht an dieses Zeitfenster gebunden.

Die Vorschau benötigt JavaScript. Themen und Antworten lassen sich auch ohne
JavaScript absenden.

## Themen moderieren

Die Moderationsfunktionen stehen Benutzern mit Administratorrechten auf der
jeweiligen Website zur Verfügung.

- **Anheften:** Wichtige Themen erscheinen vor den übrigen Themen.
- **Loslösen:** Hebt die Anheftung wieder auf.
- **Schließen:** Verhindert neue Antworten in einem Thema.
- **Öffnen:** Gibt ein geschlossenes Thema wieder für Antworten frei.
- **Bearbeiten:** Ermöglicht die Korrektur von Beiträgen und Thementiteln.
- **Löschen:** Entfernt Themen oder einzelne Beiträge. Der letzte Beitrag
  eines Themas kann nicht einzeln gelöscht werden; lösche dafür das Thema.

## Einstellungen

Unter **Einstellungen → Foren** legst Du fest:

- Wie viele Themen pro Seite angezeigt werden.
- Wie viele Beiträge pro Seite angezeigt werden.
- Wie viele Foren pro Website angelegt werden dürfen.
- Wer Thementitel bearbeiten darf.

Für **Thementitel bearbeiten** stehen drei Möglichkeiten zur Auswahl:

| Einstellung | Wer darf den Titel ändern? |
|---|---|
| Nur Moderatoren | Nur Administratoren der Website. |
| Autoren 30 Minuten, Moderatoren jederzeit | Themenautoren innerhalb der ersten 30 Minuten, Administratoren jederzeit. Dies ist die Standardeinstellung. |
| Autoren und Moderatoren jederzeit | Themenautoren können ihre eigenen Titel jederzeit ändern, Administratoren alle Titel. |

In Multisite findest Du diese Einstellungen unter **Netzwerkverwaltung →
Einstellungen → Foren**. Dort gelten sie für das gesamte Netzwerk.

## Aussehen anpassen

Öffne **Foren** und klicke beim gewünschten Forum auf **Bearbeiten**.
Dort kannst Du Namen, Beschreibung, Hauptfarbe, sekundäre Farbe, Headerfarbe,
Rahmenfarbe und Rahmenbreite ändern.

Die Farbauswahl zeigt Dir die gewählte Farbe direkt an. Achte auf ausreichend
Kontrast, damit Texte und Links gut lesbar bleiben.

## Häufige Fragen

### Warum kann ich nichts schreiben?

Melde Dich zuerst an. In Multisite muss Dein Benutzerkonto Mitglied der
betreffenden Website sein. Ist nur ein bestimmtes Thema betroffen, prüfe, ob
es geschlossen wurde.

### Warum kann ich kein weiteres Forum anlegen?

Wenn Du kein weiteres Forum erstellen kannst, kann die eingestellte maximale
Anzahl von Foren erreicht sein. Prüfe **Einstellungen → Foren** beziehungsweise
die Einstellungen in der Netzwerkverwaltung.

### Warum erscheint mein Forum nicht?

Prüfe, ob das Plugin aktiv ist und die ID im Shortcode mit der Forenübersicht
übereinstimmt. Das Forum muss zur Website gehören, auf der Du es einbindest.

### Was passiert beim Entfernen eines Forums?

Über **Foren → Entfernen** löschst Du nach einer Bestätigung das Forum
einschließlich seiner Themen und Beiträge. Entferne anschließend auch den
Shortcode von den betroffenen Seiten. Erstelle vor größeren Löschaktionen
eine Sicherung Deiner Website.

## Lizenz

Quick Forum ist freie Software unter der GPLv2 oder einer späteren Version.

# contentmigrator-AddOn für REDAXO 5

Das Addon ermöglicht das Exportieren und Importieren von mehreren Artikeln.

- Kategorien und Artikel inkl. Meta-Daten mit verschiedenen Sprachen
- Verwendete Medien werden gesammelt und angezeigt ob bereits vorhanden oder nicht
- Die Medien können dann in einen definierbaren Medienpool-Ordner importiert werden

## Achtung

- Beta! Bitte nicht in Live-Systemen testen!
- Meta-Info-Felder, Modul- und Template-IDs müssen auf beiden System übereinstimmen!

## Bugs

- Jede Menge :)
- SVGs werden als nicht vorhanden angezeigt obwohl vorhanden

## Todos

- Medien > Title, Meta-Infos übernehmen
- Interne Verlinkungen: evtl. über eine Copy-Table Links neu zuordnen?
- Modul- und Template-Key verwenden statt ID
- Timeout falls Export zu lange dauert?
- Revision?
- Überarbeitung des Image-Imports
- Statt JSON-File via API? siehe ff_copy_tool
- Code aufräumen und optimieren (viel Spaghetti)

## Abgeleitet von:

nvContentMigrator von Daniel Steffen

https://github.com/novinet-git/contentmigrator
#######################################################
##
##      Projekt:   X-ARF
##      Datei:     validator.class.php
##      Version:   1.0
##      Datum:     16.05.2010
##      Copyright: Martin Schiftan
##      license:   http://opensource.org/licenses/gpl-license.php GNU Public License
##
#######################################################


1. example.php oeffnen und anschauen ;-)
2. Einstellung in der validator.class.php anpassen oder in eine eigene config auslagern.
3. ausfuehren.


Allgemein braucht man nur folgende Daten:

Postfach, Passwort, Server-Verbindungsdaten,
Was mit geparsten Mails geschehen soll,
Was mit richtigen und fehlerhaften Reports geschehen soll.


Dann kann man mit:

$xarf         = new parsexarf($config);
$mails        = $xarf->getmails();
die Mails holen und dann mit:
> if($imap->checkstructur('x-arf', $value) == 0)
die Struktur ueberpruefen
($value enthaelt nen array mit dem Header, Body, Report, Logs)

dann weiter mit:
> if($imap->checkreport('x-arf', $value['report']) == 0)
wenn 0, dann ist es mit dem Schema und anderen Vorgaben korrekt.



Bei Fragen, einfach mailen :-)

root@blocklist.de



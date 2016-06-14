#######################################################
##
##      Projekt:   X-ARF
##      Datei:     validator.class.php
##      Version:   1.3
##      Datum:     21.12.2012
##      Copyright: Martin Schiftan
##      license:   http://opensource.org/licenses/gpl-license.php GNU Public License
##
#######################################################


1. open the example.php and look ;-)
2. Config in the validator.class.php configure or add it in a own file
3. run.


Generally you only need the following data:

Mailbox, Password, Server-Connection,
What do with the parsed Mails,
What do with the right and wrong reports.


Then you can with:

$xarf         = new parsexarf($config);
$mails        = $xarf->getmails();
open the Mailbox and then:
> if($imap->checkstructur('x-arf', $value) == 0)
check the structur
($value is an array with the Header, Body, Yaml-Report and Logs)

then go away with:
> if($imap->checkreport('x-arf', $value['report']) == 0)
if 0, then the Value is right the Data from the Schema



If you have Questions, please ask us :-)

root@blocklist.de


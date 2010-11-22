<?php
#######################################################
##
##      Projekt:   X-ARF
##      Datei:     example.php
##      Version:   1.0
##      Datum:     16.05.2010
##      Copyright: Martin Schiftan
##      license:   http://opensource.org/licenses/gpl-license.php GNU Public License
##
#######################################################

require_once('./validator.class.php');

# You need "addmail", when you not parse a mailbox via imap_open():
#       $imap  = new parsexarf($config);
#      $input = $imap->addmail($_POST['xarf']);    
#      if($input == 0)
#        {
#           # wenn 0, dann wurde die Mail erfolgreich im Postfach gespeichert und kann dann verwendet werden (also weiter im Beispiel)
#        }



# When you get mails via mailbox, use getmails();

        $imap = new parsexarf($config);
        $mail = $imap->getmails();

        foreach($mail as $key => $value)
         {
            #
            # Pruefen ob die E-Mail allgemein X-ARF ist (Header-Tag, Name, Attachments...)
            #

            if($imap->checkstructur('x-arf', $value) == 0)
              {
                #
                # Pruefen ob der Report Yaml usw. ist und mit dem Schema vergleichen.
                #

            if($imap->checkreport('x-arf', $value['report']) == 0)
              {
                #
                # Anstatt Echo kann man sich auch $imap->data per SQl usw. verarbeiten (nur Pflichtfelder sind drin)
                #

                $text  = '<strong style="color:green">'.$value['all']->subject.'</strong><br /><br />';
                $text .= '<strong style="color:green">Data:</strong><br />';
                foreach($imap->data as $key => $value)
                  {
                    $text .= '<strong>'.$key.':</strong> &nbsp; '.$value.'<br />';
                  }
                $text .= '<p><br /><br /><br /></p>';
                $errormsg = '<br /><span style="color:green">Guuutttt, keine Fehler! :-)</span><br />';
                echo $errormsg;
                echo $text;
              }
            else
              {
                #
            # Ausgabe von Fehler oder ne Mail fuers manuelle pruefen erstellen:
            # $value['header'] == Original-Header der Mail
            # $value['body']   == Original-Body der Mail
            # $value['report'] == Original-Report der Mail
            # $value['logs']   == Original-Logs der Mail (wenn welche vorhanden sind)
            #

                $text = '<i style="color:red">'.$value['all']->subject.'</i><br /><br />';
                foreach($imap->data as $key => $value)
                  {
                     $text .= '<strong>'.$key.':</strong> &nbsp; '.$value.'<br />';
                  }
               $errormsg = '<br />Es sind Fehler im Report (CHECKREPORT):<br />';
               foreach($imap->geterrormsg() as $key => $value)
                 {
                    $errormsg .= '<span style="color:red">'.$value.'</span><br />';
                 }
                echo $errormsg;
                echo $text;
              }
          }
        else
          {
            #
            # E-Mail entspricht nicht der X-ARF-Mail-Struktur
            # Entweder verwerfen oder zum manuellen bearbeiten weiterleiten...
            #

            $text = '<i style="color:red">'.$value['all']->subject.'</i><br /><br />';
            $data = $imap->data;
            if(is_array($data))
              {
                foreach($imap->data as $key => $value)
                  {
                    $text .= '<strong>'.$key.':</strong> &nbsp; '.$value.'<br />';
                  }
              }
            $errormsg = '<br />Es sind Fehler im Report (CHECKREPORT):<br />';
            foreach($imap->geterrormsg() as $key => $value)
              {
                  $errormsg .= '<span style="color:red">'.$value.'</span><br />';
              }
            echo $errormsg;
            echo $text;
          }
        unset($value);
  }

?>
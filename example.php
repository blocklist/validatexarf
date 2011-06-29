<?php
#######################################################
##
##      Projekt:   X-ARF-Validator/Parser
##      Datei:     validator.php
##      Version:   1.1
##      Datum:     29.06.2011
##      Copyright: Martin Schiftan
##      license:   http://opensource.org/licenses/gpl-license.php GNU Public License
##
#######################################################

require_once('./validator.class.php');

# You need "addmail", when you not parse a mailbox via imap_open():
#        $imap  = new parsexarf($config);
#   $input = $imap->addmail($_POST['xarf']);
#   if($input == 0)
#     {
#       header('Location: http://www.blocklist.de/xarf-validator.html?send=1&subject='.urlencode($imap->subject).'&cache='.$_POST['cache']);
#       exit();
#     }


# When you get the mails via a mailbox, use getmails();

        $imap = new parsexarf($config);
        $mail = $imap->getmails();

        echo '<pre>';
        foreach($mail as $key => $value)
           {
             # Check Structru from Mail.
            if($imap->checkstructur('x-arf', $value) == 0)
              {
                # Check Yaml-Report (checkreport($typ, $report))
                if($imap->checkreport('x-arf', $value['report']) == 0)
                  {
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

    echo '</pre>';

?>
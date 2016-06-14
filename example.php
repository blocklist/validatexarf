<?php
#######################################################
##
##      Projekt:   X-ARF-Validator/Parser
##      Datei:     example.php
##      Version:   1.5
##      Datum:     13.09.2014
##      Copyright: Martin Schiftan
##      license:   http://opensource.org/licenses/gpl-license.php GNU Public License
##
#######################################################

require_once('./validator.class.php');

# You need "addmail", when you not parse a mailbox via imap_open():
#   $imap  = new parsexarf($config);
#   $input = $imap->addmail($_POST['xarf']);
#   if($input == 0)
#     {
#       header('Location: http://www.blocklist.de/xarf-validator.html?send=1&subject='.urlencode($imap->subject).'&cache='.$_POST['cache']);
#       exit();
#     }


#
# All echo/Oput can be changed to a variable for better using in other scripts.
# This is only an example for use the validator.class
# You can use only the functions from the class and create your own scripts/classes
#

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
                    $text  = '<strong style="color:green">'.$value['all']->subject.'<br /><br />DATA:</strong><br />';
                    foreach($imap->data as $key => $value)
                      {
                        $text .= '<strong>'.$key.':</strong> &nbsp; '.$value.'<br />';
                      }
                    $text .= '<p><br /><br /><br /></p>';
                    $errormsg = '<br /><span style="color:green">Guuutttt, keine Fehler! NOOOO ERROR! :-)</span><br />';
                    echo $errormsg;
                    echo $text;
                    # End of Script logik
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
                    # End of script logik on Errors CHECKREPORT
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
                 $errormsg = '<br />Es sind Fehler im Report (CHECKSTRUCTUR):<br />';
                 foreach($imap->geterrormsg() as $key => $value)
                   {
                     $errormsg .= '<span style="color:red">'.$value.'</span><br />';
                   }
                echo $errormsg;
                echo $text;
                # End of Script logik
              }
            unset($value);
         }

    echo '</pre>';



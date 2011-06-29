<?php
#######################################################
##
##      Projekt:   X-ARF-Validator/Parser
##      Datei:     validator.class.php
##      Version:   1.1
##      Datum:     29.06.2011
##      Copyright: Martin Schiftan
##      license:   http://opensource.org/licenses/gpl-license.php GNU Public License
##
#######################################################

$config['counter']      = '100';
$config['server']       = 'localhost';
$config['username']     = 'validation-x-arf@blocklist.de';
$config['password']     = '';
$config['conntyp']      = 'imap';
$config['port']         = '993';
$config['extras']       = 'ssl/novalidate-cert';
$config['ordner']       = 'INBOX';
$config['cache']        = './cache/';                           # Pfad zum Caching-Ordner mit / am ENDE!
$config['cachetimeout'] = 10800;                                # Anzahl der sekunden, wie alt die gecachten Schemas werden duerfen
$config['movebox']      = '.geparst';                           # Name des Ordners, wenn die reports nach dem parsen verschoben weden sollen
$config['afterparse']   = 0;
#$config['afterparse']   = '0 || move || delete || delete_all'; # Ob die Nachrichten nach dem Parsen "verschoben -> move", "delete -> einzeln geloescht" oder "delete_all -> alle Nachrichten im Postfach loeschen" werden sollen....



/**
  * @package: X-Arf-Validator
  * @author:  Martin Schiftan
  * @version: 1.0$
  * @descrip: Hauptclasse zum parsen/validieren der X-ARF-Reports
  * @Classes: parsexarf
  *
 * Die Classe liest die E-Mails ein und ueberprueft diese nach dem verlinkten JSON-Schema
 */



class parsexarf
  {

    function __construct($config)
      {
        $this->config = $config;
        $this->connection = $this->connectionopen($config);
      }

    private function connectionopen($config)
      {
        $mbox = @imap_open('{'.$config['server'].':'.$config['port'].'/'.$config['conntyp'].'/'.$config['extras'].'}'.$config['ordner'], $config['username'], $config['password']);
        if(!$mbox)
          {
            $mbox = imap_last_error();
            $this->setfehler($mbox);
          }
        return($mbox);
      }

    public function setfehler($msg)
      {
        die("\n\n".$msg."\n\n");
      }


    /**
      * @name: addmail
      * Wenn die Mails nicht per imap geholt werden, fuege die Mail mit allen header-Daten aus $xarf als Mail ein.
      *
      * @param $xarf-report
      * @return Boolean
    */
    public function addmail($xarf)
      {
        $config = $this->config;
        preg_match('/subject: (.*)/im', $xarf, $subject);
        $this->subject = $subject[1];

        $xarf = str_replace("\n", "\r\n", $xarf);
        $check = imap_check($this->connection);
        $add = imap_append($this->connection, '{'.$config['server'].':'.$config['port'].'/'.$config['conntyp'].'/'.$config['extras'].'}'.$config['ordner'], stripslashes($xarf));
        $check1 = imap_check($this->connection);
        if(($check < $check1) && ($add == 1))
          {
            return(0);
          }
        else
          {
            return(1);
          }
      }


    /**
      * @name: getmails
      * holt x Mails aus dem Postfach und parst diese nach Parts, oder sucht nur nach $subject
      *
      * @param $subject
      * @return Boolean
    */
    public function getmails($subject=0)
      {
        imap_expunge($this->connection);
        $check = imap_mailboxmsginfo($this->connection);
        if((isset($subject)) && (!empty($subject)))
          {
            $mails = imap_search($this->connection, 'UNSEEN SUBJECT "'.substr(urldecode(subject),0 ,45).'"', SE_UID);
          }
        else
          {
            $mails[0] = '*';
          }
        $mails = imap_fetch_overview($this->connection, "1:".$mails[0] , FT_UID); // Holt eine Uebersicht aller Emails
        $size = count($mails); // Anzahl der Nachrichten
        if($size >= $this->config['counter'])
          {
            $size = $this->config['counter'];
          }
        for($i = 0; $i < $size; $i++)
          {
            if($i >= $this->config['counter'])
              {
                 break;
              }
            $mails[$i]->subject = imap_utf8($mails[$i]->subject);
            $header    = imap_fetchheader($this->connection, $mails[$i]->msgno);
            $struct    = imap_fetchstructure($this->connection, $mails[$i]->msgno);
            $report    = imap_fetchbody($this->connection, $mails[$i]->msgno, 2);
            $body      = imap_fetchbody($this->connection, $mails[$i]->msgno, 1);
            $logs      = imap_fetchbody($this->connection, $mails[$i]->msgno, 3);
            $this->mail[$i] = array(
                                'header'   => $header,
                                'body'     => $body,
                                'report'   => $report,
                                'logs'     => $logs,
                                'structur' => $struct,
                                'all'      => $mails[$i]
                           );
            if($this->config['afterparse'] == 'delete')
              {
                imap_delete($this->connection, '"'.$mails[$i]->msgno.'"');
              }
            elseif($this->config['afterparse'] == 'move')
              {
                imap_mail_move($this->connection, $mails[$i]->msgno, $config['ordner'].'.'.$this->config['movebox']);
              }
          }
        if($this->config['afterpase'] == 'delete_all')
          {
            imap_delete($this->connection, "*");
          }
        imap_expunge($this->connection);
        if(!is_array($mails))
          {
            $this->mail = 0;
          }
        return($this->mail);
      }


    /**
      * @name: checkstructur
      * prueft die Header und die Parts des Reports
      *
      * @param $arf (Typ: xarf, marf), $mail (Report with all header)
      * @return Boolean
    */
    public function checkstructur($arf, $mail)
      {
        $error  = 0;
        if(preg_match('/^x-arf: yes/im', strtolower($mail['header'])) == 1)
          {

            # All (Haupt-Teil) = Attachment parts[0]
            if(!is_array($mail['structur']->parameters))
              {
                $error++;
                $errormsg[] = 'Report-Part[0] enthaelt keine Charset';
              }
            else
              {
                if(!is_array($mail['structur']->parameters))
                  {
                    $mail['structur']->parameters = array();
                  }
                foreach($mail['structur']->parameters as $key => $value)
                  {
                    $parameter[strtolower($value->attribute)] = $value->value;
                  }
#                if(($parameter['report-type'] != 'multipart/mixed') && ($parameter['report-type'] != 'report'))
#                  {
#                    $error++;
#                    $errormsg[] = 'Haupt-Teil ist nicht vom Content-Type: report-type: mixed';
#                  }
                if(!is_array($mail['structur']->parts[0]->parameters))
                  {
                    $mail['structur']->parts[0]->parameters = array();
                  }
                foreach($mail['structur']->parts[0]->parameters as $key => $value)
                  {
                    $parameter[strtolower($value->attribute)] = $value->value;
                  }
                if(($parameter['charset'] != 'utf8') && ($parameter['charset'] != 'utf-8'))
                  {
                    $error++;
                    $errormsg[] = 'Haupt-Teil ist nicht vom Charset utf8 || utf-8';
                  }
              }



            # Yaml-Report = Attachment parts[1]
            if(!is_array($mail['structur']->parts[1]->parameters))
              {
                $error++;
                $errormsg[] = 'Report enthaelt keine charset und name';
              }
            else
              {
                foreach($mail['structur']->parts[1]->parameters as $key => $value)
                  {
                    $parameter[strtolower($value->attribute)] = $value->value;
                  }
                if(($parameter['charset'] != 'utf8') && ($parameter['charset'] != 'utf-8'))
                  {
                    $error++;
                    $errormsg[] = 'Charset vom Report ist nicht utf8 || utf-8';
                  }
                if(($parameter['name'] != 'report.txt') && ($parameter['name'] != 'report.yaml')  && ($parameter['name'] != 'report.yml'))
                  {
                    $error++;
                    $errormsg[] = 'Name vom Yaml-Report ist nicht report.(txt|yaml|yml), sondern "'.$parameter['name'].'".';
                  }
              }
          }
        else
          {
            $error++;
            $errormsg[] = 'Header enthaelt kein X-ARF-Tag, daher wird nicht weiter geprueft.';
          }
        $this->error = $error;
        $this->errormsg = $errormsg;

        return($error);
      }


    /**
      * @name: checkreport
      * ueberprueft den yamp-Report ob z.B. --- vorkommt, das JSON-Schema geladen werden, ob die mandory-felder korrekt sind...
      *
      * @param $arf (Typ: xarf,marf), $report
      * @return Boolean
    */
    public function checkreport($arf, $report)
      {
        $return = array();
        $error = 0;
        if(empty($report))
          {
            $error++;
            $errormsg[] = 'report ist leer';
          }
        else
          {
            $lines  = explode("\n", $report);
            $counts = count($lines);
            for($i = 0; $i < $counts; $i++)
              {
                $lines[$i] = trim($lines[$i]);
                if($error >= 1)
                  {
                    break;
                  }
                if($lines[0] != '---')
                  {
                    $error++;
                    $errormsg[] = 'Report faengt nicht mit "---" an';
                  }
                elseif(($i >= 1) && (!empty($lines[$i])))
                  {
                    $params = explode(':', $lines[$i], 2);
                    $parameter[strtolower($params[0])] = str_replace('@', '-a#t-', trim($params[1]));
                  }
              }
          }
        if((!isset($parameter['schema-url'])) || (empty($parameter['schema-url'])))
          {
            $error++;
            $errormsg[] = 'Yaml-Report hat kein <strong>Schema-URL</strong>';
            $return[]   = 'Kann nicht pruefen, was pflicht ist.... daher keine Ausgabe....';
          }
        else
          {
            $schema = $this->getschema($parameter['schema-url'], $this->config['cachetimeout']);
            $parameter['schema-url'] = '<a href="'.$parameter['schema-url'].'" target="_blank">'.$parameter['schema-url'].'</a>';
            if(empty($schema))
              {
                $error++;
                $errormsg[] = 'Konnte das JSON-Schema von '.$parameter['schema-url'].' nicht laden... evtl. Server down?';
              }
            else
              {
                    #
                # Anstatt auch bei Fehlern $return[$key] mit Inhalt zu belegen, welcher nicht korrekt ist,
                # kann man diesen auch leer lassen oder formatieren....
                #

                $phpschema = json_decode($schema);
                foreach($phpschema->properties as $key => $value)
                  {
                    $key = strtolower($key);
                    if(!isset($value->optional))
                      {
                        $schem[$key] = $value;
                        if(!isset($parameter[$key]))
                          {
                            $error++;
                            $errormsg[] = 'Pflichtfeld: "'.$key.'" nicht im Report enthalten.';
                            $return[$key] = $parameter[$key];
                          }
                        elseif((isset($value->enum)) && (!in_array($parameter[$key], $value->enum)))
                          {
                            $error++;
                            $errormsg[] = 'Im Schema bei "'.$key.'" gibt es den Wert "'.$parameter[$key].'" nicht.';
                            $return[$key] = $parameter[$key];
                          }
                        else
                          {
                            if(!isset($value->format))
                              {
                                $format = $value->type;
                              }
                            elseif(isset($value->format))
                              {
                                $format = $value->type.' ('.$value->format.')';
                              }
                            $return[$key] = $parameter[$key];
                          }
                      }
                    else
                      {
                            #
                            # check optional fields
                            #
                      }
                  }
              }
          }
        $this->data  = $return;
        $this->error = $error;
        $this->errormsg = $errormsg;
        return($error);
      }

                         

    /**
      * @name: getschema
      * holt das JSON-Schema via curl und legt es im Cache-Ordner ab.
      *
      * @param $url, $cach (1,0)
      * @return Boolean
    */
    private function getschema($url, $cache=0)
      {
        $get = 0;
        if((!isset($cache)) || ($cache == 0))
          {
            $get   = 1;
            $cache = 0;
          }
        if(empty($url))
          {
        #       $this->setfehler('URL zum JSON-Schema ist leer');
            return(0);
          }
        else
          {
            $url = str_replace('https://', '', str_replace('http://', '', trim($url)));
          }
        $curl = str_replace('/', '_', str_replace('\\', '_', $url));
        if($cache != 1)
          {
            $get = 1;
          }
        elseif((isset($cache)) && ($cache == 1))
          {
            $get = 0;
            if((!file_exists($this->config['cache'].$curl)) || (filesize($this->config['cache'].$curl) <= 10))
              {
                $get = 1;
              }
            else
              {
                $ftime = filemtime($this->config['cache'].$curl);
                $diff  = time() - $ftime;
                if($diff >= $this->config['cachetimeout'])
                  {
                    $get = 1;
                  }
              }
          }

        $user_agent = "blocklist.de X-ARF-VALIDATOR --- Mozilla/5.0 (compatible; MSIE 9.4; Windows NT 6.1)";

        $ch = curl_init();                            // initialize curl handle
        curl_setopt($ch, CURLOPT_URL, $url);          // set url to post to
        curl_setopt($ch, CURLOPT_FAILONERROR, 1);     // Fail on errors
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);   // return into a variable
        curl_setopt($ch, CURLOPT_PORT, 80);           //Set the port number
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);       // times out after 15s
        curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);

        if($get == 1)
          {
            $document = curl_exec($ch);
          }
        else
          {
            $document = file_get_contents($this->config['cache'].$curl);
          }
        if(($document === FALSE) || (empty($document)))
          {
            #$document = curl_error($ch);
          }
        else
          {
            $fp = @fopen($this->config['cache'].$curl, 'w+');
            if($fp)
              {
                fputs($fp, $document);
              }
            @fclose($fp);
          }
        return($document);
      }


    /**
      * @name: geterror
      * gibt die anzahl von $this->error ($error++) zurueck
      *
      * @param
      * @return int
    */
    public function geterror()
      {
        return($this->error);
      }


    /**
      * @name: geterrormsg
      * gibt die Fehlermeldungen zurueck
      *
      * @param
      * @return array
    */
    public function geterrormsg()
      {
        foreach($this->errormsg as $key => $value)
          {
            $this->errormsg[$key] = '<span style="color:red">'.$value.'</span>';
          }
        return($this->errormsg);
      }

    function __destruct()
      {
        @imap_close($this->connection);
        return(1);
      }
  }

?>
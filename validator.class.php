<?php
#######################################################
##
##      Projekt:   X-ARF-Validator/Parser
##      Datei:     validator.class.php
##      Version:   1.7
##      Datum:     21.04.2016
##      Copyright: Martin Schiftan
##      license:   http://opensource.org/licenses/gpl-license.php GNU Public License
##
#######################################################

@ignore_user_abort(true);

$config['counter']      = '100';
$config['server']       = '127.0.0.1';
$config['username']     = 'parser@blocklist.de';
$config['password']     = '';
$config['conntyp']      = 'imap';
$config['port']         = '993';
$config['extras']       = 'ssl/novalidate-cert';
$config['ordner']       = 'INBOX';
$config['cache']        = './cache/';                           # Folder from the Cache-Dir with trailing Slash!
$config['cachetimeout'] = 10800;                                # Counts of Seconds, how long the schema is cached
$config['useragent']    = 'blocklist.de X-ARF-VALIDATOR --- Mozilla/5.0 (compatible; MSIE 9.4; Windows NT 6.1)';   # UserAgent for Curl to loading the schema
$config['movebox']      = '.geparst';                           # When the Report is parsed, the Imap-Dir to move the E-Mail into
$config['afterparse']   = 'delete';
#$config['afterparse']   = '0 || move || delete || delete_all'; #



/**
  * @package: X-Arf-Validator
  * @author:  Martin Schiftan
  * @version: 1.4$
  * @descrip: Mainclass for parsing/validating X-ARF-Reports
  * @Classes: parsexarf
  *
 * The Class loads the Mail and validate the input with the JSON-Schema
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
      * Add the Mail to a Mailserver (with imap), when you have the mail not on a e-mailserver
      *
      * @param $xarf-report
      * @return Boolean
    */
    public function addmail($xarf)
      {
        $return = 1;
        $config = $this->config;
        preg_match('/subject: (.*)/im', $xarf, $subject);
        $this->subject = $subject[1];

        $xarf   = str_replace("\n", "\r\n", $xarf);
        $check  = imap_check($this->connection);
        $add    = imap_append($this->connection, '{'.$config['server'].':'.$config['port'].'/'.$config['conntyp'].'/'.$config['extras'].'}'.$config['ordner'], stripslashes($xarf));
        $check1 = imap_check($this->connection);
        if(($check < $check1) && ($add == 1))
          {
            $return = 0;
          }
        return($return);
      }



    /**
      * @name: getmails
      * get the x Mails from the Server and looks for the Mail-Parts or search for a Mail with a subject
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
        $size  = count($mails); // Numbers of Mails
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
            $this->msgnr = $mails[$i]->msgno;
            if($this->config['afterparse'] == 'delete')
              {
                imap_delete($this->connection, $mails[$i]->msgno.':*');
              }
            elseif($this->config['afterparse'] == 'move')
              {
                imap_mail_move($this->connection, $mails[$i]->msgno, $config['ordner'].'.'.$this->config['movebox']);
              }
          }
        if($this->config['afterparse'] == 'delete_all')
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
      * checks the Header and the Parts of the X-ARF-Report
      *
      * @param $arf (Typ: xarf, marf), $mail (Report with all header)
      * @return Boolean
    */
    public function checkstructur($arf, $mail)
      {
        $error  = 0;
        $checkxarf = preg_match('/^x-.?arf:.(yes|plain|secure|bulk)/im', strtolower($mail['header']), $typ);
        if(isset($typ[1]))
          {
            $typs = array('yes', 'plain');
            if(!in_array(strtolower($typ[1]), $typs))
              {
                $checkxarf = 0;
                $error++;
                $errormsg[] = 'Sorry, but i can only parse Version 0.1 or Version 0.2 with PLAIN';
              }
          }
        if($checkxarf == 1)
          {
            # All (Main-Part) = Attachment parts[0]
            if(!is_array($mail['structur']->parameters))
              {
                $error++;
                $errormsg[] = 'Report-Part[0] has NO Charset';
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
#                    $errormsg[] = 'Main-Part is not from the Typ "report-type: mixed"
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
                    $errormsg[] = 'Main-Part is NOT from the Charset utf8 or utf-8';
                  }
              }



            # Yaml-Report = Attachment parts[1]
            if(!is_array($mail['structur']->parts[1]->parameters))
              {
                $error++;
                $errormsg[] = 'Yaml-Report has no Charset and Name';
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
                    $errormsg[] = 'Charset of Report ist not utf8 or utf-8';
                  }
                if(($parameter['name'] != 'report.txt') && ($parameter['name'] != 'report.yaml')  && ($parameter['name'] != 'report.yml'))
                  {
                    $error++;
                    $errormsg[] = 'Name of the Yaml-Report is not "report.(txt|yaml|yml)", but "'.$parameter['name'].'".';
                  }
              }
          }
        else
          {
            $error++;
            $errormsg[] = 'Header has NO X-ARF-Tag, hence i stop parsing....';
          }
        $this->error    = @$error;
        $this->errormsg = @$errormsg;
        return($error);
      }



    /**
      * @name: checkreport
      * checks the Yaml-Report vor "---", loads the JSON-Schema and checks the mandory-fields
      *
      * @param $arf (Typ: xarf,marf), $report
      * @return Boolean
    */
    public function checkreport($arf, $report)
      {
        $return = array();
        $error  = 0;
        if(empty($report))
          {
            $error++;
            $errormsg[] = 'Report is empty';
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
                if(($lines[$i] != '---') && (!empty($lines[$i])))
                  {
                    $params = explode(':', $lines[$i], 2);
                    $parameter[strtolower($params[0])] = trim($params[1]);
                  }
              }
          }
        if((!isset($parameter['schema-url'])) || (empty($parameter['schema-url'])))
          {
            $error++;
            $errormsg[] = 'Yaml-Report has no Schema-URL';
            $return[]   = 'Kann not check witch fields ar mandory... because no output :-(';
          }
        else
          {
            $schema = $this->getschema($parameter['schema-url'], $this->config['cachetimeout']);
            $parameter['schema-url'] = $parameter['schema-url'];
            if(empty($schema))
              {
                $error++;
                $errormsg[] = 'Could not load the JSON-Schema from '.$parameter['schema-url'].'. Is the Remote-Server down???';
              }
            else
              {
                #
                # Here you can makes error-messages for the false-fields or leave no return-value and looks only for one false value
                #
                $this->getkeys = array();
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
                            $errormsg[] = 'Mandory-Field: "'.$key.'" is not in the Report.';
                            $this->return[$key] = $parameter[$key];
                            array_push($this->getkeys, $key);
                          }
                        elseif((isset($value->enum)) && (!in_array($parameter[$key], $value->enum)))
                          {
                            $error++;
                            $errormsg[] = 'In the Schema on "'.$key.'" there are no Value with "'.$parameter[$key].'".';
                            $this->return[$key] = $parameter[$key];
                            array_push($this->getkeys, $key);
                          }
                        else
                          {
                            if(!isset($value->format))
                              {
                                $type = $value->type;;
                                $format = '';
                              }
                            elseif(isset($value->format))
                              {
                                $type   = $value->type;
                                $format = $value->format;
                              }
                            $this->return[$key] = $parameter[$key];
                            $valid = $this->validateformat($key, $type, $format, $parameter[$key]);
                            if($valid == 0)
                              {
                                $error++;
                                $errormsg[] = 'Valule of "'.$key.'" is not in the right Format/Type: '.$format.'/'.$type;
                                array_push($this->getkeys, $key);
                              }
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
        $this->data  = @$this->return;
        $this->error = @$error;
        $this->errormsg = @$errormsg;
        return($error);
      }



    /**
      * @name: validateformat
      * checks the Values with the Type/Format Data (Email, Datum, IP, Number....)
      *
      * @param $key (Feldname), $type (string, number...), $format (date-time, email...), $wert (der Inhalt)
      * @return Boolean
    */
    private function validateformat($key, $type, $format, $wert)
      {
        $return = 0;
        if(($type == 'string') && (empty($format)))
          {
            return(1);
          }
        if($type == 'integer')
          {
            $return = $this->validinteger($wert);
          }
        elseif($type == 'number')
          {
            $return = $this->validnumber($wert);
          }
        elseif($format == 'email')
          {
            $return = $this->validemail($wert);
          }
        elseif($format == 'date-time')
          {
            $return = $this->validdate($wert);
          }
        elseif($format == 'uri')
          {
            $return = $this->validuri($wert);
          }
        if(($key == 'source') && ($this->return['source-type'] == 'ipv4' || 'ipv6' || 'ip-address'))
          {
            $return = $this->validip($wert);
          }
        return($return);
      }



    /**
      * @name: valid***
      * checks the data for the right typ and input.... look self:
      *
      * @param $wert
      * @return Boolean
    */
    private function validinteger($wert)
      {
        $return = 0;
        if(filter_var($wert, FILTER_VALIDATE_INT))
          {
            $return = 1;
          }
        return($return);
      }

    private function validnumber($wert)
      {
         $return = 0;
         if(is_float($wert))
           {
             $return = 1;
           }
        return($return);
      }

    private function validemail($wert)
      {
        $return = 0;
        if(filter_var($wert, FILTER_VALIDATE_EMAIL))
          {
            $return = 1;
          }
        return($return);
      }

    private function validip($wert)
      {
        $return = 0;
        if(filter_var($wert, FILTER_VALIDATE_IP))
          {
            $return = 1;
          }
        return($return);
      }

    private function validdate($wert)
      {
        $return = 0;
        $unixt  = strtotime($wert);
        if(is_numeric($unixt))
          {
            if(checkdate(date('m', $unixt), date('d', $unixt), date('Y', $unixt)) !== FALSE)
              {
                $return = 1;
              }
          }
        else
          {
            $text   = preg_match('/(([\w]{2,3}), ([\d]{1,2}) ([\w]{3}) ([\d]{4}) ([\d]{2}:[\d]{2}:[\d]{2}) (\+|\-)([\d]{4}))/im', $wert);
            if($text == 1)
              {
                $return = 1;
              }
            elseif($text != 1)
              {
                $text = preg_match('/^([\+-]?\d{4}(?!\d{2}\b))((-?)((0[1-9]|1[0-2])(\3([12]\d|0[1-9]|3[01]))?|W([0-4]\d|5[0-2])(-?[1-7])?|(00[1-9]|0[1-9]\d|[12]\d{2}|3([0-5]\d|6[1-6])))([T\s]((([01]\d|2[0-3])((:?)[0-5]\d)?|24\:?00)([\.,]\d+(?!:))?)?(\17[0-5]\d([\.,]\d+)?)?([zZ]|([\+-])([01]\d|2[0-3]):?([0-5]\d)?)?)?)?$/i', $wert);
                if($text == 1)
                  {
                    $return = 1;
                  }
               }
          }
        return($return);
      }

    private function validuri($wert)
      {
        $return = 0;
        if(filter_var($wert, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED))
          {
            $return = 1;
          }
         return($return);
      }



    /**
      * @name: getschema
      * loads the JSON-Chema with curl and save it to the cache-Dir.
      *
      * @param $url, $cach (1,0)
      * @return Boolean
    */
    private function getschema($url, $cache=0)
      {
        $get = 0;
        $url = trim($url);
        if((!isset($cache)) || ($cache == 0))
          {
            $get   = 1;
            $cache = 0;
          }
        if(empty($url))
          {
#           $this->setfehler('URL zum JSON-Schema ist leer');
            return(0);
          }
        else
          {
            $port = 80;
            if(stripos($url, 's://') !== FALSE)
              {
                $port = 443;
              }
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

        $ch = curl_init();                            // initialize curl handle
        curl_setopt($ch, CURLOPT_URL, $url);          // set url to post to
        curl_setopt($ch, CURLOPT_FAILONERROR, 1);     // Fail on errors
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);   // return into a variable
        curl_setopt($ch, CURLOPT_PORT, $port);        //Set the port number
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);        // times out after 15s
        curl_setopt($ch, CURLOPT_USERAGENT, $this->config['useragent']);

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
      * returns the numbers of Erros (this->error) back
      *
      * @param
      * @return int
    */
    public function geterror()
      {
        return($this->error);
      }

    public function getkeysreturn()
      {
        if(!isset($this->getkeys))
          {
            $this->getkeys = array();
          }
        return(@$this->getkeys);
      }



    /**
      * @name: geterrormsg
      * returns the Value of Error-Messages back
      *
      * @param
      * @return array
    */
    public function geterrormsg()
      {
        # the following foreach you need only, when you change the color from the message....
        foreach($this->errormsg as $key => $value)
          {
            # Make the Massage coloring:
            # $this->errormsg[$key] = '<span style="color:red">'.$value.'</span>';
          }
        return($this->errormsg);
      }



    function __destruct()
      {
        imap_delete($this->connection, $this->msgnr.':*');
        @imap_close($this->connection);
        return(1);
      }
  }

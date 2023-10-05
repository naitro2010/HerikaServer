<?php

/* kolboldcpp connector */

class connector
{
    public $primary_handler;
    public $name;

    private $_functionMode;
    private $_functionRawName;
    private $_functionName;
    private $_parameterBuff;
    private $_commandBuffer;

    public function __construct()
    {
        $this->name="koboldcpp";
    }


    public function open($contextData, $customParms)
    {
        $path='/api/extra/generate/stream/';
        $url=$GLOBALS["CONNECTOR"][$this->name]["url"].$path;
        $context="";


        foreach ($contextData as $s_role=>$s_msg) {	// Have to mangle context format

            if (empty(trim($s_msg["content"]))) {
                continue;
            } else {
                // This should be customizable per model
                /*
                if ($s_msg["role"]=="user")
                    $normalizedContext[]="### Instruction: ".$s_msg["content"];
                else if ($s_msg["role"]=="assistant")
                    $normalizedContext[]="### Response: ".$s_msg["content"];
                else if ($s_msg["role"]=="system")
                    $normalizedContext[]=$s_msg["content"];
                */
                $normalizedContext[]=$s_msg["content"];
            }
        }

        foreach ($normalizedContext as $n=>$s_msg) {
            if ($n==(sizeof($normalizedContext)-1)) {
                $context.="### Instruction: ".$s_msg.". Write a single reply only.\n";
                $GLOBALS["DEBUG_DATA"][]="### Instruction: ".$s_msg."";

            } else {
                $s_msg_p = preg_replace('/^(The Narrator:)(.*)/m', '[Author\'s notes: $2 ]', $s_msg);
                $context.="$s_msg_p\n";
                $GLOBALS["DEBUG_DATA"][]=$s_msg_p;
            }

        }

        $context.="### Response: ";
        $GLOBALS["DEBUG_DATA"][]="### Response:";


        $GLOBALS["DEBUG_DATA"]["prompt"]=$context;

        $TEMPERATURE=((isset($GLOBALS["CONNECTOR"][$this->name]["temperature"]) ? $GLOBALS["CONNECTOR"][$this->name]["temperature"] : 0.9)+0);
        $REP_PEN=((isset($GLOBALS["CONNECTOR"][$this->name]["rep_pen"]) ? $GLOBALS["CONNECTOR"][$this->name]["rep_pen"] : 1.12)+0);
        $TOP_P=((isset($GLOBALS["CONNECTOR"][$this->name]["top_p"]) ? $GLOBALS["CONNECTOR"][$this->name]["top_p"] : 0.9)+0);

        $MAX_TOKENS=((isset($GLOBALS["CONNECTOR"][$this->name]["max_tokens"]) ? $GLOBALS["CONNECTOR"][$this->name]["max_tokens"] : 48)+0);
        $stop_sequence=["{$GLOBALS["PLAYER_NAME"]}:","\n{$GLOBALS["PLAYER_NAME"]} ","Author\'s notes"];

        if ($GLOBALS["CONNECTOR"][$this->name]["newline_as_stopseq"]) {
            $stop_sequence[]="\n";
        }
        $postData = array(

            "prompt"=>$context,
            "temperature"=> $TEMPERATURE,
            "top_p"=>$TOP_P,
            "max_context_length"=>1024,
            "max_length"=>$MAX_TOKENS,
            "rep_pen"=>$REP_PEN,
            "stop_sequence"=>$stop_sequence,
            "use_default_badwordsids"=>$GLOBALS["CONNECTOR"][$this->name]["use_default_badwordsids"]

        );

        $GLOBALS["DEBUG_DATA"]["koboldcpp_prompt"]=$postData;


        if (isset($customParms["MAX_TOKENS"])) {
            if ($customParms["MAX_TOKENS"]==null) {
                unset($postData["max_length"]);
            } elseif ($customParms["MAX_TOKENS"]) {
                $postData["max_length"]=$customParms["MAX_TOKENS"]+0;
            }
        }

        if (isset($GLOBALS["FORCE_MAX_TOKENS"])) {
            if ($GLOBALS["FORCE_MAX_TOKENS"]==null) {
                unset($postData["max_length"]);
            } else {
                $postData["max_length"]=$GLOBALS["FORCE_MAX_TOKENS"]+0;
            }

        }


        $headers = array(
            'Content-Type: application/json'
        );

        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => json_encode($postData)
            )
        );
        error_reporting(E_ALL);
        $context = stream_context_create($options);


        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT);


        // Data to send in JSON format
        $dataJson = json_encode($postData);

        $request = "POST $path HTTP/1.1\r\n";
        $request .= "Host: $host\r\n";
        $request .= "Content-Type: application/json\r\n";
        $request .= "Content-Length: " . strlen($dataJson) . "\r\n";
        $request .= "Connection: close\r\n\r\n";
        $request .= $dataJson;

        // Open a TCP connection
        $this->primary_handler = fsockopen('tcp://' . $host, $port, $errno, $errstr, 30);

        // Send the HTTP request
        if ($this->primary_handler !== false) {
            fwrite($this->primary_handler, $request);
        } else {
            return false;
        }

        // Initialize variables for response
        $responseHeaders = '';
        $responseBody = '';

        return true;

    }


    public function process()
    {
        $line = fgets($this->primary_handler);
        $buffer="";
        $totalBuffer="";
        file_put_contents(__DIR__."/../log/debugStream.log", $line, FILE_APPEND);



        if (strpos($line, 'data: {') !== 0) {
            return "";
        }

        if (strpos($line, 'data: {"token": "#"}') === 0) {

            $this->_functionMode=true;
            return "";
        }

        $data=json_decode(substr($line, 6), true);

        if ((isset($this->_functionMode))&&($this->_functionMode)) {

            $this->_functionRawName.=$data["token"];
            return "";


        }

        if (isset($data["token"])) {
            if (strlen(trim($data["token"]))>0) {
                $buffer.=$data["token"];
            }
            $totalBuffer.=$data["token"];
        }

        return $buffer;
    }


    public function close()
    {
        fclose($this->primary_handler);
    }

    public function isDone()
    {
        return feof($this->primary_handler);
    }

    public function processActions()
    {
        global $alreadysent;
        if ((isset($this->_functionMode))&&($this->_functionMode)) {
            $alreadysent[md5("Herika|command|{$this->_functionRawName}r\r\n")] = "Herika|command|{$this->_functionRawName}\r\n";
            return $alreadysent;
        } else {
            return [];
        }
    }


}
?>

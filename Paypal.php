<?php
class Paypal
{
    protected $rawPost = null;
    protected $post = array();
    protected $validated = null;
    protected $response = null;
    protected $url = "https://www.paypal.com/cgi-bin/webscr";
    protected $debug = false;
    protected $logFile = null;

    function __construct()
    {
        $raw = file_get_contents('php://input');
        $this->rawPost = $raw;
        $post = explode('&', file_get_contents('php://input'));
        $mq = function_exists('get_magic_quotes_gpc');

        foreach ($post as $v) {
            $v = explode ('=', $v);
            if (count($v) == 2) {
                $value = urldecode($v[1]);
                if($mq) {
                    $value = stripslashes($value);
                }
                $this->post[$v[0]] = $value;
            }
        }
    }

    function sandbox()
    {
        $this->url = "https://www.sandbox.paypal.com/cgi-bin/webscr";

        if($this->debug) {
            $this->log("SANDBOX MODE ON");
        }

        return $this;
    }

    function debug($logFile = "paypal.log")
    {
        $this->debug = true;
        $this->logFile = $logFile;
        $this->log("--------------------------------");
        $this->log("DEBUG MODE ON");
        return $this;
    }

    function validate()
    {
        if($this->validated !== null) {
            return $this->validated;
        }

        $request = 'cmd=_notify-validate';
        foreach($this->post as $k => $v) {
            $request .= "&" . $k . "=" . urlencode($v);
        }

        $ch = curl_init($this->url);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));

        $cert = dirname(__FILE__) . '/cacert.pem';
        if(file_exists($cert)) {
            curl_setopt($ch, CURLOPT_CAINFO, $cert);
        }

        if(!($res = curl_exec($ch))) {
            curl_close($ch);
        }

        $this->response = $res;

        if ($this->responseStartsWith("VERIFIED")) {
            $this->validated = true;

            if($this->debug) {
                $this->log("PayPal Validated!" . PHP_EOL . "Request: " . $this->rawPost . PHP_EOL . "Response: " . $this->response);
            }
        } else {
            $this->validated = false;

            if($this->debug) {
                $this->log("PayPal FAILED!" . PHP_EOL . "Request: " . $this->rawPost . PHP_EOL . "Response: " . $this->response);
            }
        }

        return $this->validated;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function responseStartsWith($str)
    {
        return strcmp($this->response, $str) == 0;
    }

    public function log($str)
    {
        file_put_contents($this->logFile, $str . PHP_EOL, FILE_APPEND);
        return $this;
    }
}
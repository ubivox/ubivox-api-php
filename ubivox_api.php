<?php

define("UBIVOX_API_CLIENT_VERSION", "0.1");

require_once dirname(__FILE__)."/IXR_Library.php";

class UbivoxAPIException extends Exception { }
class UbivoxAPIUnavailable extends UbivoxAPIException { }
class UbivoxAPIError extends UbivoxAPIException { }
class UbivoxAPIUnauthorized extends UbivoxAPIException { }
class UbivoxAPIConnectionError extends UbivoxAPIException { }
class UbivoxAPINotFound extends UbivoxAPIException { }

class UbivoxAPI {

    function __construct($username, $password, $url, $encoding="utf-8") {
        $this->username = $username;
        $this->password = $password;
        $this->url = $url;
        $this->encoding = $encoding;
    }

    public function call($method, $params = null) {

        $auth = $this->username.":".$this->password;

        $request = new IXR_Request($method, $params, $this->encoding);
        $post = $request->xml;

        $c = curl_init($this->url);

        curl_setopt($c, CURLOPT_USERAGENT, "Ubivox API PHP client ".UBIVOX_API_CLIENT_VERSION);
        curl_setopt($c, CURLOPT_POST, true);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($c, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($c, CURLOPT_USERPWD, $auth);
        curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($c, CURLOPT_POSTFIELDS, $post);
        curl_setopt($c, CURLOPT_HEADER, true);
        curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($c, CURLOPT_HTTPHEADER, array(
            "Content-Type: text/xml; charset=".$this->encoding,
            "Expect:"
        ));

        $http_response = curl_exec($c);

        $info = curl_getinfo($c);

        list($header, $data) = explode("\r\n\r\n", $http_response, 2);

        if ($info["http_code"] == 200) {

            # Support for 100 Continue HTTP header

            if (strpos($data, "HTTP/1.1") === 0) {
                list($header, $data) = explode("\r\n\r\n", $data, 2);
            }

            $message = new IXR_Message($data);
            $message->parse();

            if ($message->messageType == "fault") {
                throw new UbivoxAPIError($message->faultString, $message->faultCode);
            }

            return $message->params[0];

        }

        if ($info["http_code"] == 401) {
            throw new UbivoxAPIUnauthorized();
        }

        if ($info["http_code"] == 503) {
            throw new UbivoxAPIUnavailable();
        }

        if ($info["http_code"] == 404) {
            throw new UbivoxAPINotFound();
        }

        if ($info["http_code"] == 0) {
            throw new UbivoxAPIConnectionError();
        }

        throw new UbivoxAPIException($header);

    }
}

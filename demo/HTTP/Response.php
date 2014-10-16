<?php

class HTTP_Response {

    protected $code;
    protected $headers = array();
    protected $cookies = array();

    public function __construct($code, $resp) {
        $this->code = $code;

        if ($idx = strpos($resp, "\r\n\r\n")) {
            $this->_parseHeaders(substr($resp, 0, $idx));
            $this->body = substr($resp, $idx + 4);
        } else {
            $this->_parseHeaders($resp);
        }
    }

    protected function _parseHeaders($headers) {
        $headerLines = explode("\r\n", $headers);
        array_shift($headerLines);
        foreach ($headerLines as $headerLine) {
            $this->_parseHeaderLine($headerLine);
        }
        if (array_key_exists('set-cookie', $this->headers)) {

            if (is_array($this->headers['set-cookie'])) {
                $cookies = $this->headers['set-cookie'];
            } else {
                $cookies = array($this->headers['set-cookie']);
            }

            foreach ($cookies as $cookieString) {
                $this->_parseCookie($cookieString);
            }
            unset($this->headers['set-cookie']);
        }
        foreach (array_keys($this->headers) as $k) {
            if (is_array($this->headers[$k])) {
                $this->headers[$k] = implode(', ', $this->headers[$k]);
            }
        }
    }
    
    /**
     * Parses the line from HTTP response filling $headers array
     *
     * The method should be called after reading the line from socket or receiving-
     * it into cURL callback. Passing an empty string here indicates the end of
     * response headers and triggers additional processing, so be sure to pass an
     * empty string in the end.
     *
     * @param    string  Line from HTTP response
     */
    protected function _parseHeaderLine($headerLine) {
        $headerLine = trim($headerLine, "\r\n");

        // string of the form header-name: header value
        if (preg_match('!^([^\x00-\x1f\x7f-\xff()<>@,;:\\\\"/\[\]?={}\s]+):(.+)$!', $headerLine, $m)) {
            $name  = strtolower($m[1]);
            $value = trim($m[2]);
            if (empty($this->headers[$name])) {
                $this->headers[$name] = $value;
            } else {
                if (!is_array($this->headers[$name])) {
                    $this->headers[$name] = array($this->headers[$name]);
                }
                $this->headers[$name][] = $value;
            }
            $this->lastHeader = $name;

            // string-
        } elseif (preg_match('!^\s+(.+)$!', $headerLine, $m) && $this->lastHeader) {
            if (!is_array($this->headers[$this->lastHeader])) {
                $this->headers[$this->lastHeader] .= ' ' . trim($m[1]);
            } else {
                $key = count($this->headers[$this->lastHeader]) - 1;
                $this->headers[$this->lastHeader][$key] .= ' ' . trim($m[1]);
            }
        }
    }

    /**
     * Parses a Set-Cookie header to fill $cookies array
     *
     * @param    string    value of Set-Cookie header
     * @link     http://cgi.netscape.com/newsref/std/cookie_spec.html
     */
    protected function _parseCookie($cookieString) {

        $cookie = array(
            'expires' => null,
            'domain'  => null,
            'path'    => null,
            'secure'  => false
        );

        // Only a name=value pair
        if (!strpos($cookieString, ';')) {
            $pos = strpos($cookieString, '=');
            $cookie['name']  = trim(substr($cookieString, 0, $pos));
            $cookie['value'] = trim(substr($cookieString, $pos + 1));

            // Some optional parameters are supplied
        } else {
            $elements = explode(';', $cookieString);
            $pos = strpos($elements[0], '=');
            $cookie['name']  = trim(substr($elements[0], 0, $pos));
            $cookie['value'] = trim(substr($elements[0], $pos + 1));

            for ($i = 1; $i < count($elements); $i++) {
                if (false === strpos($elements[$i], '=')) {
                    $elName  = trim($elements[$i]);
                    $elValue = null;
                } else {
                    list ($elName, $elValue) = array_map('trim', explode('=', $elements[$i]));
                }
                $elName = strtolower($elName);
                if ('secure' == $elName) {
                    $cookie['secure'] = true;
                } elseif ('expires' == $elName) {
                    $cookie['expires'] = str_replace('"', '', $elValue);
                } elseif ('path' == $elName || 'domain' == $elName) {
                    $cookie[$elName] = urldecode($elValue);
                } else {
                    $cookie[$elName] = $elValue;
                }
            }
        }
        $this->cookies[] = $cookie;
    }

    public function getHeader($name) {
        $name = strtolower($name);
        if (array_key_exists($name, $this->headers)) {
            return $this->headers[$name];
        }
        return null;
    }

    public function getVersion() {
        
    }

    public function getHeaders() {
        return $this->headers;
    }

    public function getStatus() {
        return $this->code;
    }

    public function getCookies() {
        return $this->cookies;
    }

    public function getBody() {
        return $this->body;
    }
}

?>

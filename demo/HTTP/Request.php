<?php
class HTTP_Request {

    const METHOD_OPTIONS = 'OPTIONS';
    const METHOD_GET     = 'GET';
    const METHOD_HEAD    = 'HEAD';
    const METHOD_POST    = 'POST';
    const METHOD_PUT     = 'PUT';
    const METHOD_DELETE  = 'DELETE';
    const METHOD_TRACE   = 'TRACE';
    const METHOD_CONNECT = 'CONNECT';

    protected $url;
    protected $method;
    protected $version;
    protected $body;
    protected $headers = array(
                               'user-agent' => 'myop/1.0',
                              );
    protected $postParams = array();
    protected $config = array(
                              'connect_timeout' => 10,
                              'timeout' => 50,
                              'dnscache' => false,
                              'proxy' => false,
                              'maxredirs' => 0,
                             );

    public function __construct($url = null, $method = self::METHOD_GET, array $config = array(), $version = '1.0') {
        $this->url = $url;
        $this->method = $method;

        $this->setConfig($config);
    }

    public function setConfig($key, $value = null) {
        if (is_array($key)) {
            foreach ($key as $k=>$v) {
                $this->setConfig($k, $v);
            }
        } else {
            $this->config[$key] = $value;
        }
    }

    public function getConfig($name) {
        if (array_key_exists($name, $this->config)) {
            return $this->config[$name];
        }
        return null;
    }

    public function setURL($url) {
        $this->url = $url;
    }

    public function getURL() {
        return $this->url;
    }

    public function setBody($body) {
        $this->body = $body;
    }

    public function getBody() {
        return $this->body;
    }

    /**
     * Sets request header(s)
     *
     * The first parameter may be either a full header string 'header: value' or
     * header name. In the former case $value parameter is ignored, in the latter-
     * the header's value will either be set to $value or the header will be
     * removed if $value is null. The first parameter can also be an array of
     * headers, in that case method will be called recursively.
     *
     * Note that headers are treated case insensitively as per RFC 2616.
     *-
     * <code>
     * $req->setHeader('Foo: Bar'); // sets the value of 'Foo' header to 'Bar'
     * $req->setHeader('FoO', 'Baz'); // sets the value of 'Foo' header to 'Baz'
     * $req->setHeader(array('foo' => 'Quux')); // sets the value of 'Foo' header to 'Quux'
     * $req->setHeader('FOO'); // removes 'Foo' header from request
     * </code>
     *
     * @param    string|array    header name, header string ('Header: value')
     *                           or an array of headers
     * @param    string|null     header value, header will be removed if null
     * @return   HTTP_Request
     * @throws   HTTP_Request_Exception
     */
    public function setHeader($name, $value = null) {
        if (is_array($name)) {
            foreach ($name as $k => $v) {
                if (is_string($k)) {
                    $this->setHeader($k, $v);
                } else {
                    $this->setHeader($v);
                }
            }
        } else {
            if (!$value && strpos($name, ':')) {
                list($name, $value) = array_map('trim', explode(':', $name, 2));
            }
            // Header name should be a token: http://tools.ietf.org/html/rfc2616#section-4.2
            if (preg_match('![\x00-\x1f\x7f-\xff()<>@,;:\\\\"/\[\]?={}\s]!', $name)) {
                throw new HTTP_Request_Exception("Invalid header name '{$name}'");
            }
            // Header names are case insensitive anyway
            $name = strtolower($name);
            if (!$value) {
                unset($this->headers[$name]);
            } else {
                $this->headers[$name] = $value;
            }
        }
        return $this;
    }

   /**
    * Returns the request headers
    *
    * The array is of the form ('header name' => 'header value'), header names
    * are lowercased
    *
    * @return   array
    */
    public function getHeaders() {
        return $this->headers;
    }

   /**
    * Appends a cookie to "Cookie:" header
    *
    * @param    string  cookie name
    * @param    string  cookie value
    * @return   HTTP_Request
    * @throws   HTTP_Request_Exception
    */
    public function addCookie($name, $value) {
        $cookie = $name . '=' . $value;
        // Disallowed characters: http://cgi.netscape.com/newsref/std/cookie_spec.html
        if (preg_match('/[\s;]/', $cookie)) {
            throw new HTTP_Request_Exception("Invalid cookie: '{$cookie}'");
        }
        if (array_key_exists('cookie', $this->headers)) {
            $cookies = $this->headers['cookie'] . '; ';
        }
        $this->setHeader('cookie', $cookies . $cookie);

        return $this;
    }

   /**
    * Sets the request method
    *
    * @param    string
    * @return   HTTP_Request
    * @throws   HTTP_Request_Exception if the method name is invalid
    */
    public function setMethod($method) {
        // Method name should be a token: http://tools.ietf.org/html/rfc2616#section-5.1.1
        if (preg_match('![\x00-\x1f\x7f-\xff()<>@,;:\\\\"/\[\]?={}\s]!', $method)) {
            throw new HTTP_Request_Exception("Invalid request method '{$method}'");
        }
        $this->method = $method;

        return $this;
    }

   /**
    * Returns the request method
    *
    * @return   string
    */
    public function getMethod() {
        return $this->method;
    }

    /**
     * Adds POST parameter(s) to the request.
     *
     * @param    string|array    parameter name or array ('name' => 'value')
     * @param    mixed           parameter value (can be an array)
     * @return   HTTP_Request
     */
    public function addPostParameter($name, $value = null) {

        if (!array_key_exists('content-type', $this->headers)) {
            $this->setHeader('Content-Type', 'application/x-www-form-urlencoded');
        }

        $this->_addMultiParameter($name, $value, false);

        return $this;
    }

    /**
     * Adds FILE parameter(s) (uploaded files path) to the request.
     *
     * @param    string|array    parameter name or array ('name' => 'value')
     * @param    mixed           parameter value (can be an array)
     * @return   HTTP_Request
     */
    public function addFileParameter($name, $value = null) {

        // Use Content-Type=multipart/form-data to upload files
        $this->setHeader('Content-Type', 'multipart/form-data');

        $this->_addMultiParameter($name, $value, true);

        return $this;
    }

    function proxyRewrite($prefix, $name, $suffix, &$hostname) {
        $hostname = $name;
        $name = $this->config['proxy'];
        return $prefix . $name . $suffix;
    }

    function hostResolve($prefix, $name, $suffix, &$hostname) {

        $hostname = $name;

        if ($this->config['dnscache']) {
            $key = 'host_' . $name;
            $host = apc_fetch($key);
            if ($host) {
                // error_log($key . " cache hit");
                return $prefix . $host . $suffix;
            }
            // error_log($key . " cache miss");
        }

        $host = gethostbyname($name);

        if (preg_match('/^\d+\.\d+\.\d+\.\d+$/', $host)) {
            if ($this->config['dnscache']) {
                apc_store($key, $host, 3600);
            }
            return $prefix . $host . $suffix;
        }
        return $prefix . $name . $suffix;
    }

    public function send() {

        $ch = curl_init();

        switch ($this->version) {
            case '1.1':
                curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
                break;
            case '1.0':
            default:
                curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
                break;
        }
        if ($this->config['proxy']) {
            // todo: preg_replace()函数与Apache 2.4.3、PHP 5.3.18的兼容问题
            //$url = preg_replace('/(https?:\/\/)([\w\.\-]+)(\/?)/e', '$this->proxyRewrite("\1", "\2", "\3", $hostname)', $this->url);
            preg_match('/(https?:\/\/)([^\/]+)(.*)/i', $this->url, $matches);
            $url = $this->proxyRewrite($matches[1], $matches[2], $matches[3], $hostname);
            $this->headers['host'] = $hostname;
        } else {
            if ($this->config['dnscache']) {
                preg_match('/(https?:\/\/)([\w\.\-]+)(\/?)/i', $this->url, $matches);
                $url = $this->hostResolve($matches[1], $matches[2], $matches[3], $hostname);
                $this->headers['host'] = $hostname;
            } else {
                $url = $this->url;
            }
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        // set headers not having special keys
        $headersFmt = array();
        foreach ($this->headers as $name => $value) {
            $canonicalName = implode('-', array_map('ucfirst', explode('-', $name)));
            $headersFmt[]  = $canonicalName . ': ' . $value;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headersFmt);
        //curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);

        if (array_key_exists('cookie', $this->headers)) {
            curl_setopt($ch, CURLOPT_COOKIE, $this->headers['cookie']);
        }

        if (array_key_exists('user-agent', $this->headers)) {
            curl_setopt($ch, CURLOPT_USERAGENT, $this->headers['user-agent']);
        }

        if (array_key_exists('referer', $this->headers)) {
            curl_setopt($ch, CURLOPT_REFERER, $this->headers['referer']);
        }

        if (array_key_exists('accept-encoding', $this->headers)) {
            curl_setopt($ch, CURLOPT_ENCODING, $this->headers['accept-encoding']);
        }

        switch ($this->method) {
            case self::METHOD_POST:
            case self::METHOD_PUT:
                curl_setopt($ch, CURLOPT_POST, true);
                if (!$this->body) {
                    // fix: content-type support not only 'application/x-www-form-urlencoded'
                    if (!array_key_exists('content-type', $this->headers) || $this->headers['content-type'] == 'application/x-www-form-urlencoded') {
                        $this->body = http_build_query($this->postParams, '', '&');
                    } else {
                        $this->body = $this->postParams;
                    }
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->body);
                break;
        }

        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->getConfig('connect_timeout'));
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->getConfig('timeout'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // fix: dnscache's problem with https://
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 忽略ssl 证书

        if ($this->config['maxredirs']) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, $this->config['maxredirs']);
        }

        $result = curl_exec($ch);

        if ($result === false) {
            throw new HTTP_Request_Exception(sprintf('Error sending request: #%s %s at %s -H \'HOST: %s\'',
                                                     curl_errno($ch), curl_error($ch), $url, $this->headers['host']));
        }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $info = curl_getinfo($ch);
        if ($info['redirect_count'] > 0) {
            for ($i = 0; $i < $info['redirect_count']; $i++) {
                $idx = strpos($result, "\r\n\r\n");
                $result = substr($result, $idx + 4);
            }
        }

        curl_close($ch);

        return new HTTP_Response($code, $result);
    }

    /**
     * Adds POST parameter(s) (including uploaded files) to the request.
     *
     * @param    string|array    parameter name or array ('name' => 'value')
     * @param    mixed           parameter value (can be an array)
     * @param    bool            parameter isFile (Is the param a file path)
     * @return   HTTP_Request
     */
    private function _addMultiParameter($name, $value = null, $isFile = false) {

        if (is_array($name)) {
            foreach ($name as $k => $v) {
                $this->_addMultiParameter($k, $v, $isFile);
            }
        } else {
            if ($isFile) {
                $value = '@' . $value . ';type=' . mime_content_type($value);
            }
            $this->postParams[$name] = $value;
        }

        return $this;
    }
}

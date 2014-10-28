<?php
class WSQ {
    /**
     * api 域名
     */
    public $apiHost = 'https://openapi.wsq.qq.com';

    /**
     * appId
     */
    protected $appId = '';

    /**
     * appSecret
     */
    protected $appSecret = '';

    /**
     * code
     */
    protected $code = '';

    /**
     * accessToken
     */
    protected $accessToken = '';

    /**
     * noNeedAppToken
     */
    protected $noNeedAppToken = array(
        '/v1/app/token',
    );

    /**
     * noNeedAccessToken
     */
    protected $noNeedAccessToken = array(
        '/v1/app/token',
        '/v1/user/auth',
        '/v1/app/sites',
        '/v1/site/info',
        '/v1/site/threads',
        '/v1/thread/get',
    );

    public function __construct($appId, $appSecret, $code) {

        $this->appId = $appId;
        $this->appSecret = $appSecret;
        $this->code = $code;
    }

    /**
     * _request
     * 发出请求，统一错误解析
     *
     * @param string $type
     * @param array $data
     * @param bool $isPost
     *
     * @return array
     */
    private function _request($type, $data = array(), $isPost = false) {

        // 需要 appToken
        if (!$data['appToken'] && !in_array($type, $this->noNeedAppToken)) {
            $data['appToken'] = $this->getAppToken();
        }

        // 需要 accessToken
        if (!$data['accessToken'] && !in_array($type, $this->noNeedAccessToken)) {
            $data['accessToken'] = $this->getAccessToken();
        }

        // 请求地址
        $requestUrl = $this->apiHost . $type;

        // 指定请求的超时时间
        $httpRequest = new HTTP_Request($requestUrl, HTTP_Request::METHOD_GET, array('connect_timeout' => 1, 'timeout' => 2));

        // 文件上传
        if ($type == '/v1/thread/upload') {
            $httpRequest->addFileParameter('pic', $data['pic']);
            unset($data['pic']);
        }

        // post数据
        if ($isPost) {
            $httpRequest->setMethod(HTTP_Request::METHOD_POST);
            foreach ($data as $name => $value) {
                $httpRequest->addPostParameter($name, $value);
            }
        } else {
            $httpRequest->setUrl($requestUrl . '?' . http_build_query($data));
        }

        // 发送请求
        try {
            $response = $httpRequest->send();
            $result = json_decode($response->getBody(), true);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }

        if ($response->getStatus() != 200) {
            throw new Exception('接口请求失败', $response->getStatus());
            return false;
        }

        if ($result['errCode']) {
            throw new Exception($result['errMsg'], $result['errCode']);
            return false;
        }

        return $result['data'];
    }

    /**
     * getAppToken
     * 获取appToken
     *
     * @param viod
     *
     * @return string
     */
    public function getAppToken() {

        $now = time();

        // 本地文件缓存
        $logFile = '/tmp/wsq_app_token.log';
        if (!file_exists($logFile)) {
            touch($logFile);
        }

        $content = file_get_contents($logFile);
        list($time, $token) = explode('|', $content);
        if ($time > time() && $token) {
            $data = array(
                'appToken' => $token
            );
        } else {
            $getData = array(
                'appId' => $this->appId,
                'type' => 'security',
                'time' => $now,
                'sig' => sha1($this->appId . $this->appSecret . $now)
            );
            $data = $this->_request('/v1/app/token', $getData);
            $content = sprintf('%d|%s', time() + $data['expires'], $data['appToken']);
            file_put_contents($logFile, $content);
        }

        return $data['appToken'];
    }

    /**
     * getAccessToken
     * 获取accessToken
     *
     * @param void
     *
     * @return string
     */
    public function getAccessToken() {

        if (!$this->accessToken) {
            $getData = array(
                'code' => $this->code
            );
            $data = $this->_request('/v1/user/auth', $getData);
            $this->accessToken = $data['accessToken'];
        }

        return $this->accessToken;
    }

    /**
     * getSiteInfo
     * 获取站点信息
     *
     * @param int $sId
     *
     * @return array
     */
    public function getSiteInfo($sId) {

        $getData = array(
            'sId' => $sId
        );

        $data = $this->_request('/v1/site/info', $getData);

        return $data;
    }

    /**
     * getThreadList
     * 获取站点主题列表
     *
     * @param int $sId
     * @param int $start
     *
     * @return array
     */
    public function getThreadList($sId, $start = 0) {

        $getData = array(
            'sId' => $sId,
            'start' => $start
        );

        $data = $this->_request('/v1/site/threads', $getData);

        return $data;
    }

    /**
     * getThread
     * 获取单个主题
     *
     * @param int $sId
     * @param int $tId
     * @param bool $isGetReply
     * @param int $start
     *
     * @return array
     */
    public function getThread($sId, $tId, $isGetReply = false, $start = 0) {

        $getData = array(
            'sId' => $sId,
            'tId' => $tId,
            'isGetReply' => $isGetReply,
            'start' => $start
        );

        $data = $this->_request('/v1/thread/get', $getData);

        return $data;
    }

    /**
     * newThread
     * 发表主题
     *
     * @param int $sId
     * @param string $content
     * @param array $picIds
     *
     * @return array
     */
    public function newThread($sId, $content , $picIds = array()) {

        $postData = array(
            'sId' => $sId,
            'content' => $content,
            'picIds' => implode(',', $picIds)
        );

        $data = $this->_request('/v1/thread/new', $postData, true);

        return $data;
    }

    /**
     * uploadPic
     * 上传图片
     *
     * @param int $sId
     * @param string $pic
     *
     * @return array
     */
    public function uploadPic($sId, $pic) {

        $postData = array(
            'sId' => $sId,
            'pic' => $pic,
        );

        $data = $this->_request('/v1/thread/upload', $postData, true);

        return $data;
    }

    /**
     * deleteThread
     * 删除主题
     *
     * @param int $sId
     * @param int $tId
     *
     * @return bool
     */
    public function deleteThread($sId, $tId) {

        $postData = array(
            'sId' => $sId,
            'tId' => $tId,
        );

        $data = $this->_request('/v1/thread/del', $postData, true);

        return $data;
    }

    /**
     * newReply
     * 回复主题
     *
     * @param int $sId
     * @param int $tId
     * @param string content
     *
     * @return array
     */
    public function newReply($sId, $tId, $content) {

        $postData = array(
            'sId' => $sId,
            'tId' => $tId,
            'content' => $content,
        );

        $data = $this->_request('/v1/reply/new', $postData, true);

        return $data;
    }

    /**
     * deleteReply
     * 删除回复
     *
     * @param int $sId
     * @param int $tId
     * @param int $pId
     *
     * @return bool
     */
    public function deleteReply($sId, $tId, $pId) {

        $postData = array(
            'sId' => $sId,
            'tId' => $tId,
            'pId' => $pId,
        );

        $data = $this->_request('/v1/reply/del', $postData, true);

        return $data;
    }

    /**
     * getUserInfo
     * 获取用户信息
     *
     * @param bool $isGetCoin
     *
     * @return array
     */
    public function getUserInfo($isGetCoin = false) {

        $getData = array(
            'isGetCoin' => $isGetCoin,
        );

        $data = $this->_request('/v1/user/info', $getData);

        return $data;
    }

    /**
     * getUserMessages
     * 获取用户消息列表
     *
     * @param int $start
     *
     * @return array
     */
    public function getUserMessages($start = 0) {

        $getData = array(
            'start' => $start,
        );

        $data = $this->_request('/v1/user/message', $getData);

        return $data;
    }

    /**
     * getAppSites
     * 获取启用本应用的站点列表
     *
     * @param int $start
     *
     * @return array
     */
    public function getAppSites($start = 0) {

        $getData = array(
            'start' => $start,
        );

        $data = $this->_request('/v1/app/sites', $getData);

        return $data;
    }

    /**
     * checkAdmin
     * 当前用户是否是站点管理员
     *
     * @param int $sId
     *
     * @return bool
     */
    public function checkAdmin($sId) {

        $getData = array(
            'sId' => $sId,
        );

        $data = $this->_request('/v1/user/checkAdmin', $getData);

        return $data['isAdmin'];
    }
}

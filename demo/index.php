<?php
/**
 * 自动加载类文件
 *
 * @param string $className
 *
 * @return bool
 */
function __autoload($className) {

    // 把类名转换为文件路径
    $classFile = ROOT_PATH . '/' . str_replace('_', '/', $className) . '.php';

    if (file_exists($classFile)) {
        require $classFile;
    } else {
        var_dump($className . ' 缺少类文件' . $classFile);
    }

    return true;
}

// 根目录
define('ROOT_PATH', dirname(__FILE__));

// 换成应用自己的appId、appSecret
$wsq = new WSQ('wsq09fd1510690413', '8f92091ad07bb0c7aaa954d88f3ea548', $_GET['code']);
try {
    // 获取站点信息
    // var_dump($wsq->getSiteInfo($_GET['sId']));

    // 获取主题列表
    // var_dump($wsq->getThreadList($_GET['sId']));

    // 上传图片
    var_dump($pic = $wsq->uploadPic($_GET['sId'], '/tmp/46709-106.jpg'));

    // 发表主题
    var_dump($thread = $wsq->newThread($_GET['sId'], '我是从demo发表的主题', array($pic['picId'])));

    // 获取主题
    // var_dump($thread = $wsq->getThread($_GET['sId'], $thread['tId']));

    // 发表回复
    var_dump($reply = $wsq->newReply($_GET['sId'], $thread['tId'], '我是从demo发表的回复'));

    // 获取主题+回复
    var_dump($thread = $wsq->getThread($_GET['sId'], $thread['tId'], true));

    // 获取用户信息
    // var_dump($wsq->getUserInfo());

    // 获取用户消息列表
    // var_dump($wsq->getUserMessages());

    // 当前用户是否是站点管理员
    // var_dump($wsq->checkAdmin($_GET['sId']));
} catch (Exception $e) {
    echo sprintf('错误信息：%s <br/> 错误号：%s <br/>', $e->getMessage(), $e->getCode());
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no"/>
    <meta name="format-detection" content="telephone=no"/>
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="baidu-tc-cerfication" content="5f496895eb9bff9ec4db4512a7e4e95c" />
    <meta name="description" content=""/>
    <meta name="keywords" content="" />
    <script src="http://dzqun.gtimg.cn/openapp/scripts/interface.js" type="text/javascript" charset="utf-8"></script>
</head>
<body>
<br />
<input type="button" value="改变高度" onclick="wsqOpenApp.resizeFrame({h:1000});">
<br />
<br />
<input type="button" value="刷新页面" onclick="wsqOpenApp.resizeFrame({r:1});">
<br />
<br />
<input type="button" value="分享到社区" onclick="wsqOpenApp.share();">
<br />

<script type="text/javascript">
// 调整页面高度
if (typeof wsqOpenApp == 'undefined') {
    console.info(2);
    if (document.addEventListener) {
        document.addEventListener('wsqOpenAppReady', function(e) {
            wsqOpenApp.resizeFrame({h:200});
        }, false);
    }
} else {
    console.info(wsqOpenApp);
    wsqOpenApp.resizeFrame({h:200});
}

// 微信or手Q系统自带分享
var opts = {
    title:'快来一起玩这个应用',
    desc:'我在应用上抽中了一等奖',
    img:'http://dzqun.gtimg.cn/quan/images/loginLogo.png',
    callback: function(re) {
    }
};
if (typeof wsqOpenApp == 'undefined') {
    document.addEventListener('wsqOpenAppReady', function(e) {
        wsqOpenApp.initShare(opts);
    }, false);
} else {
    wsqOpenApp.initShare(opts);
}
</script>
</body>
</html>

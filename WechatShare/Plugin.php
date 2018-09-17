<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
require_once 'memcached/MemcachedClient.php';
/**
 * 利用微信公众号将文章分享到微信的插件，可以在朋友圈和好友分享中生成缩略图和简介
 *
 * @package WechatShare
 * @author huangfeiqiao
 * @version 1.0.0
 * @link https://www.huangfeiqiao.com
 */
class WechatShare_Plugin implements Typecho_Plugin_Interface
{

    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     *
     * @access public
     * @return string
     * @throws Typecho_Plugin_Exception
     */
    public static function activate() {
        if (!class_exists('Memcached')) {
            throw new Typecho_Plugin_Exception('当前php没有Memcached，无法激活插件');
        }
        Typecho_Plugin::factory('Widget_Archive')->header = array('WechatShare_Plugin', 'output');
        return '插件安装成功，请进行设置';
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     *
     * @static
     * @access public
     * @return void
     */
    public static function deactivate() {

    }
    
    /**
     * 获取插件配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form) {
        $imgUrl = new Typecho_Widget_Helper_Form_Element_Text(
            'imeUrl',
            NULL,
            '',
            _t('默认缩略图URL'),
            _t('插件会使用文章中第一张图片，如果没有的话会使用此url，如果留空会使用favicon.ico')
        );
        $appId = new Typecho_Widget_Helper_Form_Element_Text(
            'wechatAppId',
            NULL,
            '',
            _t('微信AppID')
        );
        $secret = new Typecho_Widget_Helper_Form_Element_Text(
            'wechatAppSecret',
            NULL,
            '',
            _t('微信AppSecret')
        );
        $memcachedHost = new Typecho_Widget_Helper_Form_Element_Text(
            'memcachedHost',
            NULL,
            '',
            _t('Memcached 服务器地址')
        );
        $memcachedPort = new Typecho_Widget_Helper_Form_Element_Text(
            'memcachedPort',
            NULL,
            '',
            _t('Memcached 服务器端口')
        );
        $debug = new Typecho_Widget_Helper_Form_Element_Checkbox(
            'isDebug',
            array('@isDebugWechatJsAPI@' => 'DEBUG微信JsAPI'),
            array(),
            _t('DEBUG选项')
        );
        $form->addInput($imgUrl);
        $form->addInput($appId);
        $form->addInput($secret);
        $form->addInput($memcachedHost);
        $form->addInput($memcachedPort);
        $form->addInput($debug);

    }
    
    /**
     * 个人用户的配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form) {

    }

    /**
     * 插件实现方法
     *
     * @access public
     * @param Widget_Archive $archive
     * @return void
     * @throws Typecho_Exception
     */
    public static function output($header, $archive) {

        $appId = Typecho_Widget::widget('Widget_Options')->plugin('WechatShare')->wechatAppId;
        $appSecret = Typecho_Widget::widget('Widget_Options')->plugin('WechatShare')->wechatAppSecret;
        $ua = strtolower(Typecho_Request::getInstance()->getAgent());
        $isWechat = (strpos($ua, 'micromessenger') > 0);
        if (!$appId || !$appSecret || !$archive->is('single') || !$isWechat) {
            return;
        }
        $url = Typecho_Request::getInstance()->getRequestUrl();
        $ticket = self::getJsTicket($appId, $appSecret);
        $nonceStr = Typecho_Common::randString(10);
        $timeStamp = (int)time();
        $sig = self::generateSig($ticket, $nonceStr, $timeStamp, $url);
        $wxConfig = array();

        $debug = false;
        $isDebugArr = Typecho_Widget::widget('Widget_Options')->plugin('WechatShare')->isDebug;
        if (is_array($isDebugArr) && in_array('@isDebugWechatJsAPI@', $isDebugArr)) {
            $debug = true;
        }

        $wxConfig['debug'] = $debug;
        $wxConfig['appId'] = $appId;
        $wxConfig['timestamp'] = $timeStamp;
        $wxConfig['nonceStr'] = $nonceStr;
        $wxConfig['signature'] = $sig;
        $wxConfig['jsApiList'] = array(
            'updateAppMessageShareData',
            'updateTimelineShareData',
        );

        $wxConfigStr = json_encode($wxConfig, JSON_UNESCAPED_UNICODE);
        echo '<script src="https://res.wx.qq.com/open/js/jweixin-1.4.0.js"></script>'.PHP_EOL;
        echo '<script>var wxcfg='.$wxConfigStr.';'.PHP_EOL.'wx.config(wxcfg);</script>'.PHP_EOL;

        $shareData = array();
        $shareData['title'] = $archive->title;
        $shareData['desc'] = mb_substr($archive->description, 0, 60, 'utf-8');
        $shareData['link'] = $archive->permalink;

        $shareData['imgUrl'] = Typecho_Request::getUrlPrefix().'/favicon.ico';

        $imgUrl = Typecho_Widget::widget('Widget_Options')->plugin('WechatShare')->imgUrl;
        if ($imgUrl) {
            $shareData['imgUrl'] = $imgUrl;
        }
        $imgUrl = self::findImage($archive->content);
        if ($imgUrl) {
            $shareData['imgUrl'] = $imgUrl;
        }
        $shareDataStr = json_encode($shareData, JSON_UNESCAPED_UNICODE);

        echo '<script>'.
            'var wxShareData='.$shareDataStr.';'. PHP_EOL.
            'wx.ready(function(){'.PHP_EOL.
            'wx.updateAppMessageShareData(wxShareData, function(res) {});'.PHP_EOL.
            'wx.updateTimelineShareData(wxShareData, function(res) {});'.PHP_EOL.
            '});'.PHP_EOL.
            'wx.error(function(res){console.log(res)});'.PHP_EOL.
            '</script>'.PHP_EOL;
    }

    /**
     * 获取正文中的第一张图片
     * @param string $txt
     * @return null|string
     */
    private static function findImage($txt) {
        $pattern = '/(http(s)?:\/\/.+?\.(png|jpg|jpeg|gif|ico))/i';
        preg_match($pattern, $txt, $matches);
        if (!empty($matches)) {
            return $matches[0];
        }
        return null;
    }

    /**
     * 计算签名
     * @param string $ticket
     * @param string $nonceStr
     * @param int $timeStamp
     * @param string $url
     * @return string
     */
    private static function generateSig($ticket, $nonceStr, $timeStamp, $url) {
        $input = array(
            'jsapi_ticket' => $ticket,
            'noncestr' => $nonceStr,
            'timestamp' => $timeStamp,
            'url' => $url,
        );
        $inputArray = array();
        foreach ($input as $k => $v) {
            $inputStr = $k . "=" . $v;
            $inputArray[] = $inputStr;
        }
        $finalStr = implode('&', $inputArray);
        $sig = sha1($finalStr);
        return $sig;
    }

    /**
     * @return MemcachedClient|null
     * @throws Typecho_Exception
     * @throws Typecho_Plugin_Exception
     */
    private static function getMemcachedClient() {
        $memcachedHost = Typecho_Widget::widget('Widget_Options')->plugin('WechatShare')->memcachedHost;
        $memcachedPort = Typecho_Widget::widget('Widget_Options')->plugin('WechatShare')->memcachedPort;
        if (!$memcachedPort || !$memcachedHost) {
            throw new Typecho_Plugin_Exception('没有配置memcached服务！');
        }
        $option = array();
        $option['host'] = $memcachedHost;
        $option['port'] = $memcachedPort;
        $option['expire'] = 7100;
        $client = MemcachedClient::getInstance($option);
        return $client;
    }

    /**
     * 获取js ticket并缓存
     * @param string $appId
     * @param string $appSecret
     * @return mixed|null|string
     * @throws Typecho_Exception
     * @throws Typecho_Plugin_Exception
     */
    private static function getJsTicket($appId, $appSecret) {
        $accessToken = self::getAccessToken($appId, $appSecret);
        $client = self::getMemcachedClient();
        $key = 'wechat_jsapi_ticket_' . $accessToken;
        $ticket = $client->get($key);
        if (!$ticket) {
            $apiUrl = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket';
            $param = array(
                'access_token' => $accessToken,
                'type' => 'jsapi',
            );
            $response = self::HttpRequest($apiUrl, $param);
            $ret = json_decode($response, true);
            if ($ret !== false && isset($ret['ticket'])) {
                $ticket = (string)$ret['ticket'];
                $expire = (int)$ret['expires_in'];
                // 缓存token
                if (!empty($ticket)) {
                    $client->add($key, $ticket, $expire - 100);
                }
                return $ticket;
            }
        } else {
            return $ticket;
        }
        return null;

    }

    /**
     * 获取access token并缓存
     * @param string $appId
     * @param string $appSecret
     * @return mixed|null|string
     * @throws Typecho_Exception
     * @throws Typecho_Plugin_Exception
     */
    private static function getAccessToken($appId, $appSecret) {
        $client = self::getMemcachedClient();
        $key = 'wechat_access_token_' . $appId . '_' . $appSecret;
        $result = $client->get($key);
        if (!$result) {
            $param = array(
                'appid' => $appId,
                'secret' => $appSecret,
                'grant_type' => 'client_credential',
            );
            $apiUrl = 'https://api.weixin.qq.com/cgi-bin/token';
            $response = self::HttpRequest($apiUrl, $param);
            $ret = json_decode($response, true);
            if ($ret !== false && isset($ret['access_token'])) {
                $accessToken = (string)$ret['access_token'];
                $expire = (int)$ret['expires_in'];
                // 缓存token
                if (!empty($accessToken)) {
                    $client->add($key, $accessToken, $expire - 100);
                }
                return $accessToken;
            }
        } else {
            return $result;
        }
        return null;
    }

    /**
     * 微信api请求工具
     * @param $url
     * @param $params
     * @param string $method
     * @param bool $paramIsJson
     * @return bool|string
     */
    private static function HttpRequest($url, $params, $method='GET', $paramIsJson = false) {

        $httpContext = array(
            'timeout' => 3,
            'method' => $method,
            'header' => '',
        );
        // php5.6+默认会验证https证书，需要规避掉
        $sslContext = array(
            'verify_peer' => false,
            'verify_peer_name' => false,
        );

        if ($paramIsJson) {
            $content = json_encode($params);
        } else {
            $content = is_string($params) ? $params : http_build_query($params, '', '&');
        }

        if ($method === 'POST' || $paramIsJson) {
            $httpContext['content'] = $content;
        } elseif ($method === 'GET') {
            $url .= '?' . $content;
        }

        $context = @stream_context_create(array(
            'http' => $httpContext,
            'ssl' => $sslContext,
        ));

        $response = @file_get_contents($url, 0, $context);
        return $response;
    }
}

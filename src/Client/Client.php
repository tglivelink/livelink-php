<?php

namespace Livelink\Client;

const CODE_ECB = "ecb";
const SIGN_MD5 = "md5";
const SIGN_MD5_FIXED = "md5_fixed";

/**
 * PlatUser 平台用户信息 
 */
class PlatUser
{
    /**
     * userId
     *
     * @var string 用户id 
     */
    public $userid;
    /**
     * clentIp
     *
     * @var string 用户ip 0.0.0.0
     */
    public $clentIp;

    function __construct($userid, $clientIp = "0.0.0.0")
    {
        $this->userid = $userid;
    }
}

/**
 * ReqParam 请求参数，会放到url参数中
 */
class ReqParam
{
    /**
     * actId
     *
     * @var int 活动ID
     */
    public $actId;
    /**
     * gameId
     *
     * @var string 游戏code  
     */
    public $gameId;
    /**
     * livePlatId
     *
     * @var string 平台code
     */
    public $livePlatId;
    /**
     * user
     *
     * @var PlatUser 用户信息
     */
    public $user;
    /**
     * ext
     *
     * @var array 扩展信息 
     */
    public $ext;

    public function __construct($actId, $gameId, $livePlatId, $user)
    {
        $this->actId = $actId;
        $this->gameId = $gameId;
        $this->livePlatId = $livePlatId;
        $this->user = $user;
    }

    public function to_kvs()
    {
        $kvs = array();
        $kvs["livePlatId"] = $this->livePlatId;
        $kvs["actId"] = $this->actId;
        $kvs["gameId"] = $this->gameId;
        $kvs["gameIdList"] = $this->gameId;
        $kvs["t"] = time();
        $kvs["nonce"] = $this->rand_str(6);
        if ($this->ext != null) {
            foreach ($this->ext as $k => $v) {
                $kvs[$k] = $v;
            }
        }

        return $kvs;
    }

    private function rand_str($length)
    {
        $str = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $len = strlen($str) - 1;
        $randstr = '';
        for ($i = 0; $i < $length; $i++) {
            $num = mt_rand(0, $len);
            $randstr .= $str[$num];
        }
        return $randstr;
    }
}

class Client
{
    /**
     * signer
     *
     * @var string 签名方式 默认值md5，如果是要拉起小程序，使用 md5_fixed
     */
    private $signer;
    /**
     * coder
     *
     * @var string code计算方式 默认值ecb 
     */
    private $coder;

    /**
     * secKey
     *
     * @var string 计算code需要的秘钥
     */
    private $secKey;
    /**
     * sigKey
     *
     * @var string 计算签名需要的秘钥 
     */
    private $sigKey;

    /**
     * domain
     *
     * @var string 
     */
    public $domain;

    function __construct($secKey, $sigKey, $signer = SIGN_MD5, $coder = CODE_ECB)
    {
        $this->secKey = $secKey;
        $this->sigKey = $sigKey;
        $this->signer = $signer;
        $this->coder = $coder;
        $this->domain = "https://s1.livelink.qq.com";
    }

    /**
     * do_request 发起请求 
     *
     * @param  ReqParam $req_param 请求参数，会放到url参数中
     * @param  string $pathOrApiName 请求路径或方法名
     * @param  array $body 对应POST的请求体
     * @return string 响应体
     */
    public function do_request($req_param, $pathOrApiName = "", $body)
    {

        $domain = $this->domain;

        // 拼接请求路径 
        if ($pathOrApiName[0] == "/") {
            $domain .= $pathOrApiName;
        } else { // 一部分接口采用 ?apiName=xxxx 标识请求路径 
            $domain .= "/livelink";
            if ($req_param->ext == null) {
                $req_param->ext = array();
            }
            $req_param->ext["apiName"] = $pathOrApiName;
        }

        $query = http_build_query($this->trans_args($req_param));
        $url = $domain . "?" . $query;

        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => 'Content-type: application/json',
                'content' => json_encode($body, JSON_FORCE_OBJECT),
            )
        );
        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        return $response;
    }

    /**
     * sign 封装签名、code等信息 
     *
     * @param ReqParam $req_param
     * @param  bool $tostring 
     * @return array
     */
    public function trans_args($req_param)
    {
        $kvs = $req_param->to_kvs();

        $userStr = json_encode($req_param->user);
        $kvs["code"] = $this->encrypt_code($userStr);

        $kvs["sig"] = $this->sign_with_kvs($kvs);

        return $kvs;
    }

    /**
     * encrypt_code 计算用户code 
     *
     * @param  string $text
     * @return string
     */
    public function encrypt_code($text)
    {
        if ($this->coder == CODE_ECB) {
            return base64_encode(openssl_encrypt($text, "AES-128-ECB", $this->secKey, OPENSSL_RAW_DATA));
        }
        return "";
    }

    /**
     * decrypt_code 解析用户code
     *
     * @param  string $text
     * @return string
     */
    public function decrypt_code($text)
    {
        if ($this->coder == CODE_ECB) {
            return openssl_decrypt(base64_decode($text), "AES-128-ECB", $this->secKey, OPENSSL_RAW_DATA);
        }
        return "";
    }

    /**
     * sign_with_kvs 计算签名 
     *
     * @param  array $kvs
     * @return string 
     */
    public function sign_with_kvs($kvs)
    {
        if ($this->signer == SIGN_MD5) {
            return $this->sign_with_md5($kvs);
        } else if ($this->signer == SIGN_MD5_FIXED) {
            return $this->sign_with_md5_fixed($kvs);
        }
        return "";
    }

    private function sign_with_md5($kvs)
    {
        $unsignKeys = ["c", "apiName", "sig", "fromGame", "backUrl", "a"];
        $t = array();
        foreach ($kvs as $k => $v) {
            if (!in_array($k, $unsignKeys)) {
                $t[$k] = urlencode($v);
            }
        }

        ksort($t);
        $str = join($t, "+");
        $str .= "+" . $this->sigKey;

        return md5($str);
    }

    private function sign_with_md5_fixed($kvs)
    {
        $signKeys = ["livePlatId", "gameIdList", "t", "code", "gameAuthScene"];
        $t = array();
        foreach ($kvs as $k => $v) {
            if (in_array($k, $signKeys)) {
                $t[$k] = urlencode($v);
            }
        }

        ksort($t);
        $str = join($t, "+");
        $str .= "+" . $this->sigKey;

        return md5($str);
    }
}

<?php

namespace xsframe\service;

use AntCloudSDKCore\AntCloudClient;
use xsframe\base\BaseService;

class JingTanService extends BaseService
{
    // 测试环境
    private $devUrl = "http://openapi-sit.antchain.dl.alipaydev.com/gateway.do";
    // 预发环境
    private $preUrl = "https://openapi-pre.antchain.antgroup.com/gateway.do";
    // 正式环境
    private $proUrl = "https://openapi.antchain.antgroup.com/gateway.do";

    // 当前请求url
    public $requestUrl = "";

    public $client = null;
    public $config = null;

    public function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub

        // 初始化客户端
        if (!$this->client instanceof AntCloudClient) {
            $this->requestUrl = $this->proUrl;
            $this->config = $this->moduleSetting['jt'];

            $client = new AntCloudClient(
                "{$this->proUrl}",
                "{$this->config['ak']}",
                "{$this->config['sk']}"
            );
            $this->client = $client;
        }
    }

    // 授权获取token
    public function getAccessToken($authCode)
    {
        $request = array(
            "method"     => "antchain.nftx.oauth.token.apply",
            "grant_type" => "authorization_code", // 1.authorization_code，表示换取使⽤⽤户授权码code换取授权令牌access_token。2.refresh_token，表示使⽤refresh_token刷新获取新授权令牌 本期只⽀持 authorization_code
            "auth_code"  => "{$authCode}", // 授权码 授权码，⽤户对应⽤授权后得到。为 refresh_token 时不填。
            "version"    => "1.0",
        );
        // 发送调用请求，解析响应结果
        //  {"access_token": "eyJhbGciOiJSUzI1NiJ9.eyJzY29w", "result_msg": "OK", "refresh_token": "eyJhbGciOiJSUzI1NiJ9.eyJvcGVuVWlkIjoiNS", "req_msg_id": "2895757e04de6edfec3a7779db4bb478", "refresh_expire_time": "2024-04-15T11:52:01.958+08:00", "expire_time": "2024-01-17T11:52:01.958+08:00", "open_user_id": "5/FX1kUwqmmwLDl1ErFGon2+riQAGYclegMwbzHA0Pc=", "result_code": "OK"}
        return $this->client->execute($request);
    }

    // 获取用户信息
    public function getUserInfo($accessToken){
        $request = array(
            "method"     => "antchain.nftx.oauth.userinfo.query",
            "access_token" => "{$accessToken}",
            "version"    => "1.0",
        );
        // 发送调用请求，解析响应结果
        return $this->client->execute($request);
    }

    // 获取商品详情
    public function getAssetInfo($phone, $nftId)
    {
        $request = array(
            "method"  => "antchain.nftx.nft.customer.query",
            "idNo"    => "{$phone}",
            "idType"  => "PHONE_NO",
            "nftId"   => "{$nftId}",
            "version" => "1.0",
        );
        // 发送调用请求，解析响应结果
        // {"author_name":"杭州乐水文化创意有限公司","result_msg":"SUCCESS","req_msg_id":"5265fa9e682f16634d2dbc8444e5d2eb","nft_id":"AC13470#0776\/2000","result_code":"OK","sku_id":13470,"sku_name":"飞天·扶摇","mini_image_path":"https:\/\/mdn.alipayobjects.com\/chain_myent\/afts\/img\/sp48RY8aeXwAAAAAAAAAAAAADvN2AQBr\/original","issuer_name":"杭州乐水文化创意有限公司"}
        return $this->client->execute($request);
    }

    // 获取商品列表
    public function getAssetList($phone, $pIndex = 1, $pageSize = 10)
    {
        // 构建请求  藏品持有信息查询
        $request = array(
            "method"   => "antchain.nftx.nft.customer.pagequery",
            "page"     => "{$pIndex}",
            "pageSize" => "{$pageSize}",
            "idNo"     => "{$phone}", // 客户有藏品 15825546043 total_count = 1
            "idType"   => "PHONE_NO",
            "version"  => "1.0",
        );

        // 发送调用请求，解析响应结果
        // {"asset_list":[{"author_name":"杭州乐水文化创意有限公司","nft_id":"AC13470#0776\/2000","sku_id":13470,"sku_name":"飞天·扶摇","mini_image_path":"https:\/\/mdn.alipayobjects.com\/chain_myent\/afts\/img\/sp48RY8aeXwAAAAAAAAAAAAADvN2AQBr\/original","issuer_name":"杭州乐水文化创意有限公司"}],"result_msg":"SUCCESS","req_msg_id":"1dcb255571ca7f575c960d40361d30cf","total_count":1,"result_code":"OK","page":1,"page_size":10}
        return $this->client->execute($request);
    }
}
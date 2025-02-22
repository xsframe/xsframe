<?php

// +----------------------------------------------------------------------
// | 星数 [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2023~2024 http://xsframe.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: guiHai <786824455@qq.com>
// +----------------------------------------------------------------------

namespace xsframe\facade\service;


use xsframe\service\WxappService;
use think\Facade;

/**
 * @method static getAccessToken(string $appId, string $secret, $expire = 7000, $isReload = false)
 * @method static sendTplNotice($appid, $secret, $wxapp_openid, string $templateId, array $postData, string $url)
 * @method static getPhoneNumber($appid, $secret, string $code, bool $isReload = false)
 * @method static getOpenid($appid, $secret, string $code)
 */
class WxappServiceFacade extends Facade
{
    protected static function getFacadeClass()
    {
        return WxappService::class;
    }
}
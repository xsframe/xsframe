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

declare (strict_types=1);

namespace xsframe\middleware;

use xsframe\wrapper\AccountHostWrapper;
use xsframe\wrapper\UserWrapper;

/**
 * 域名访问默认应用
 */
class DomainBindMiddleware
{
    protected $accountHostWrapper;

    public function __construct()
    {
        if (!$this->accountHostWrapper instanceof AccountHostWrapper) {
            $this->accountHostWrapper = new AccountHostWrapper();
        }
    }

    /**
     * 执行域名访问默认应用
     * @param $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        $module = app('http')->getName();

        if (empty($module) || empty($request->root())) { // 独立域名访问逻辑
            $url = $request->header()['host'];
            $domainMappingArr = $this->accountHostWrapper->getAccountHost();
            if (!empty($domainMappingArr) && !empty($domainMappingArr[$url])) {
                $module = $domainMappingArr[$url]['default_module'];

                // 未设置默认访问应用时取第一个授权应用
                if (empty($module)) {
                    $module = $this->accountHostWrapper->getAccountModuleDefault($domainMappingArr[$url]['uniacid']);
                    $this->accountHostWrapper->setAccountModuleDefault($domainMappingArr[$url]['uniacid'], $url, $module);
                }

                $appMap = array_flip(config('app.app_map'));
                $realModuleName = array_key_exists($module, $appMap) ? $appMap[$module] : '';
                $url = UserWrapper::getModuleOneUrl($realModuleName ?: $module);

                header("location:" . $url);
                exit();
            }
        } else { // 自动访问逻辑
            $pathInfo = $request->pathinfo();
            if (empty($pathInfo)) {
                $url = UserWrapper::getModuleOneUrl($module);
                if ($url != $module) {
                    header("location:" . $url);
                    exit();
                }
            }
        }

        return $next($request);
    }

}

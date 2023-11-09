<?php


namespace xsframe\base;

use think\facade\Cache;
use xsframe\traits\ServiceTraits;

class BaseService extends BaseController
{
    protected $expire = 7200;

    /**
     * 引入后台通用的traits
     */
    use ServiceTraits;

    // 获取KEY
    protected function getKey($key)
    {
        $key = strtolower($this->module) . "_" . md5($this->uniacid . $key);
        return $key;
    }

    // 设置缓存
    protected function setCache($key, $data, $expire = null)
    {
        Cache::set($key, $data, $expire ?? $this->expire);
    }

    // 获取缓存
    protected function getCache($key)
    {
        return Cache::get($key);
    }

    // 清空缓存
    protected function clearCache($key)
    {
        Cache::set($key, false, -100);
    }

}
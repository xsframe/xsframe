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

namespace xsframe\base;


use xsframe\util\FileUtil;
use xsframe\util\StringUtil;
use xsframe\wrapper\MenuWrapper;
use xsframe\enum\SysSettingsKeyEnum;
use xsframe\util\RandomUtil;

abstract class MobileBaseController extends BaseController
{
    protected function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub

        if (method_exists($this, '_mobile_initialize')) {
            $this->_mobile_initialize();
        }
    }

    // 初始化
    protected function _mobile_initialize()
    {

    }

    // 引入后端模板
    protected function template($name, $var = null)
    {
        $var = $this->getDefaultVars($var);

        if (!empty($name) && $name[0] !== '/') {
            $name = "mobile/{$name}";
        }

        return view($name, $var);
    }

    // 设置默认参数
    private function getDefaultVars($params = null)
    {
        if (!empty($this->moduleSetting['basic'])) {
            if (!empty($this->moduleSetting['basic']['name'])) {
                $this->moduleInfo['name'] = $this->moduleSetting['basic']['name'];
            }
            if (!empty($this->moduleSetting['basic']['logo'])) {
                $this->moduleInfo['logo'] = $this->moduleSetting['basic']['logo'];
            }
        }

        $var = [];
        $var['module'] = $this->module;
        $var['controller'] = $this->controller;
        $var['action'] = $this->action;
        $var['uniacid'] = $this->uniacid;
        $var['uid'] = $this->userId;
        $var['url'] = $this->url;
        $var['siteRoot'] = $this->siteRoot;
        $var['iaRoot'] = $this->iaRoot;
        $var['websiteSets'] = $this->websiteSets;

        $var['moduleSiteRoot'] = $this->moduleSiteRoot;
        $var['moduleAttachUrl'] = $this->moduleAttachUrl;

        $var['account'] = $this->account;
        $var['moduleInfo'] = $this->moduleInfo;
        $var['attachUrl'] = getAttachmentUrl() . "/";
        $var['isLogin'] = $this->isLogin;

        if (!empty($params)) {
            $var = array_merge($var, $params);
        }

        return $var;
    }

    // 自动执行客户端页面
    public function runMobile($filename = 'index', $version = null)
    {
        $addonsName = $this->module;

        $template = "{$addonsName}/mobile/{$filename}.html";

        $source = IA_ROOT . "/public/app/" . $template;

        if (!strexists($filename, 'version')) {
            $versionPath = IA_ROOT . "/public/app/" . $addonsName . "/mobile/version";

            if (empty($version)) {
                $trees = FileUtil::dirsOnes($versionPath);
                $version = end($trees);
                if (!empty($version)) {
                    $template = "{$addonsName}/mobile/version/{$version}/{$filename}.html";
                }
            }

            $source = IA_ROOT . "/public/app/{$addonsName}/mobile/version/{$version}/{$filename}.html";
        }

        if (!is_file($source)) {
            exit("template source '{$template}' is not exist!");
        } else {
            echo "<script>let uniacid = `{$this->uniacid}`;</script>";
            echo "<script>let version = '1.0';</script>";
            echo "<script>let module = `{$this->module}`;</script>";
            /*echo "<script>let apiroot = `{$this->siteRoot}`;</script>";*/
            require_once $source;
        }
    }

    /**
     * 正确的数组数据
     * @param array $data
     * @param string $code
     * @param string $message
     * @return \think\response\Json
     */
    protected function success(array $data = [], string $code = "200", string $message = 'success'): \think\response\Json
    {
        $code = $data['code'] ?? $code;
        $message = $data['msg'] ?? $message;
        $data = $data['data'] ?? $data;

        $retData = [
            'code' => (string)$code,
            'msg'  => $message,
            'data' => $data
        ];
        return json($retData);
    }

    /**
     * 错误的数组数据
     * @param string $message
     * @param string $code
     * @return array
     */
    protected function error(string $message = 'fail', string $code = "404"): array
    {
        $code = $data['code'] ?? $code;
        $message = $data['msg'] ?? $message;

        $retData = [
            'code' => (string)$code,
            'msg'  => $message,
            'data' => [],
        ];
        die(json_encode($retData));
    }
}
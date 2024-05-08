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

use think\App;
use think\Request;
use think\response\Json;
use xsframe\enum\SysSettingsKeyEnum;

abstract class WebBaseController extends BaseController
{
    protected $pIndex;
    protected $pSize;

    protected $app;
    protected $header;
    protected $params;

    protected $siteRoot;
    protected $view;

    protected $controller;
    protected $action;
    protected $url;
    protected $iaRoot;

    protected $expire = 86400;

    public function __construct(Request $request, App $app)
    {
        parent::__construct($request, $app);

        $this->header = $this->request->header();
        $this->app = $app;
        $this->params = $this->request->param();

        if (method_exists($this, '_home_initialize')) {
            $this->_home_initialize();
        }
    }

    // 初始化
    protected function _home_initialize()
    {
        $this->view = $this->app['view'];
        $this->pIndex = $this->request->param('page') ?? 1;
        $this->pSize = $this->request->param('size') ?? ($this->request->param('limit') ?? 16);

        $this->_init();
    }

    // 初始化
    protected function _init()
    {
    }

    // 引入模板
    protected function template($name, $var = []): \think\response\View
    {
        $var = $this->getDefaultVars($var);

        if (!('' == pathinfo($name, PATHINFO_EXTENSION))) {
            $moduleName = app('http')->getName();
            $name = APP_PATH . "/" . $moduleName . "/view/" . ltrim($name, '/');
        }

        return view($name, $var); // TODO: Change the autogenerated stub
    }

    // 设置默认参数
    private function getDefaultVars($params = null): array
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

        # 收缩菜单
        $var['foldNav'] = intval($_COOKIE["foldnav"] ?? 0);

        if (!empty($params)) {
            $var = array_merge($var, $params);
        }

        return $var;
    }
}
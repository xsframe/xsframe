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

use app\admin\enum\CacheKeyEnum;
use think\facade\Cache;
use xsframe\exception\ApiException;
use xsframe\util\StringUtil;
use xsframe\wrapper\AccountHostWrapper;
use xsframe\wrapper\SettingsWrapper;
use xsframe\enum\SysSettingsKeyEnum;
use xsframe\util\FileUtil;
use think\App;
use think\Request;
use think\route\dispatch\Controller;

abstract class BaseController extends Controller
{
    protected $uniacid;
    protected $request;
    protected $header;
    protected $params;
    protected $pIndex;
    protected $pSize;
    protected $app;
    protected $siteRoot;
    protected $ip;
    protected $view;
    protected $clientBaseType;

    protected $module;
    protected $controller;
    protected $action;

    protected $url;

    protected $isLogin;
    protected $userId;
    protected $userInfo;
    protected $memberInfo;
    protected $iaRoot;
    protected $moduleSiteRoot;
    protected $moduleAttachUrl;
    protected $moduleIaRoot;
    protected $authkey;
    protected $expire;
    protected $attachment;
    protected $settingsController;
    protected $accountHostController;

    protected $websiteSets = [];
    protected $account = [];
    protected $accountSetting = [];
    protected $moduleInfo = [];
    protected $moduleSetting = [];

    public function __construct(Request $request, App $app)
    {
        $this->request = $request;
        $this->header = $request->header();
        $this->app = $app;
        $this->params = $this->request->param();

        if (!$this->settingsController instanceof SettingsWrapper) {
            $this->settingsController = new SettingsWrapper();
        }

        if (!$this->accountHostController instanceof AccountHostWrapper) {
            $this->accountHostController = new AccountHostWrapper();
        }

        if (method_exists($this, '_initialize')) {
            $this->_initialize();
        }
    }

    /**
     * @throws ApiException
     */
    protected function _initialize()
    {
        $this->authkey = "xsframe_";
        $this->expire = 3600 * 24 * 10; // 10天有效期

        $this->view = $this->app['view'];

        $this->pIndex = $this->request->param('page') ?? 1;
        $this->pSize = $this->request->param('size') ?? 10;

        $this->siteRoot = request()->domain();
        if (StringUtil::strexists($this->request->server('HTTP_REFERER'), 'https')) {
            $this->siteRoot = str_replace("http:", "https:", $this->siteRoot);
        }

        $this->iaRoot = str_replace("\\", '/', dirname(dirname(dirname(dirname(__FILE__)))));

        $this->module = app('http')->getName(); // 获取的是真实应用名称不是别名

        // 后台运行时需要获取到module的值时可以设置下cookie start
        if (empty($this->module) && !empty($_COOKIE['module'])) {
            $this->module = $_COOKIE['module'];
        }
        // end

        $this->ip = $this->request->ip();

        $this->moduleSiteRoot = $this->siteRoot . "/" . $this->module;
        $this->moduleAttachUrl = $this->siteRoot . "/app/" . $this->module;
        $this->moduleIaRoot = $this->iaRoot . "/app/" . $this->module;

        $this->controller = strtolower($this->request->controller());
        $this->action = strtolower($this->request->action());
        $this->url = $this->request->url();

        $this->checkCors();
        $this->autoLoad();

        # 验证是否独立数据库应用 start
        if (!is_file($this->moduleIaRoot . "/config/database.php")) {
            $this->getDefaultSets();
        } else {
            $this->getUniacid();
        }
        # 验证是否独立数据库应用 end
    }

    // 解决重复提交与跨域问题
    private function checkCors()
    {
        header('Access-Control-Allow-Origin:*');
        header("Access-Control-Allow-Headers: Authorization, Content-Type, If-Match, If-Modified-Since, If-None-Match, If-Unmodified-Since, X-CSRF-TOKEN, X-Requested-With , X-Access-Token");
        header('Access-Control-Allow-Methods: PUT,POST,GET,DELETE,OPTIONS');

        if ($this->request->method() === "OPTIONS") {
            exit();
        }
    }

    protected function autoLoad()
    {
        $path = $this->iaRoot . '/extend/xsframe/function';
        $files = FileUtil::getDir($path);

        if (!empty($files)) {
            foreach ($files as $fileInfo) {
                if (is_file($fileInfo['path'])) {
                    if (!class_exists($fileInfo['path'])) {
                        include_once $fileInfo['path'];
                    }
                }
            }
        }
    }

    protected function success()
    {

    }

    protected function error()
    {

    }

    /** 加载默认配置信息
     * @throws ApiException
     */
    protected function getDefaultSets()
    {
        # 系统网站设置
        $this->websiteSets = $this->settingsController->getSysSettings(SysSettingsKeyEnum::WEBSITE_KEY);

        # 设置模块
        $uniacid = $this->getUniacid();

        # 附件设置
        $attachmentSets = $this->settingsController->getSysSettings(SysSettingsKeyEnum::ATTACHMENT_KEY);

        # 项目信息
        $this->account = $this->settingsController->getAccountSettings($uniacid);
        $this->accountSetting = $this->settingsController->getAccountSettings($uniacid, SysSettingsKeyEnum::SETTING_KEY);

        # 模块信息
        $this->moduleInfo = $this->settingsController->getModuleInfo($this->module);
        $this->moduleSetting = $this->getModuleSettings($uniacid);

        if ($uniacid > 0) {
            $accountSets = $this->account['settings'] ?? [];
            if (!empty($accountSets) && !empty($accountSets['attachment'])) {
                $attachmentSets['remote'] = $accountSets['attachment']['remote'];
            }
        }

        $this->attachment = $attachmentSets;
    }

    // 获取模块配置信息
    protected function getModuleSettings($uniacid)
    {
        $moduleSetting = $this->settingsController->getModuleSettings(null, $this->module, $uniacid);
        if (!empty($moduleSetting)) {
            if (!empty($moduleSetting['basic']))
                $moduleSetting['basic']['logo'] = tomedia($moduleSetting['basic']['logo']);
            if (!empty($moduleSetting['share']))
                $moduleSetting['share']['imageUrl'] = tomedia($moduleSetting['share']['imageUrl']);
            if (!empty($moduleSetting['website'])) {
                $moduleSetting['website']['logo'] = tomedia($moduleSetting['website']['logo']);
                $moduleSetting['website']['favicon'] = tomedia($moduleSetting['website']['favicon']);
                $this->websiteSets = $moduleSetting['website'];
            }
        }
        return $moduleSetting;
    }

    // 获取项目uniacid
    protected function getUniacid($checkUrl = false)
    {
        $uniacid = $this->params['uniacid'] ?? ($_GET['i'] ?? ($_COOKIE['uniacid'] ?? 0));
        $this->module = empty($this->module) ? app('http')->getName() : $this->module;

        # 校验域名路由 start
        if (empty($uniacid) && $this->module != 'admin' && !empty($_SERVER['HTTP_HOST'])) { // 域名路由
            $uniacid = $this->accountHostController->getAccountHostUniacid($_SERVER['HTTP_HOST']);
        } else {
            if ($checkUrl && empty($this->params['uniacid']) && empty($this->params['i'])) {
                $uniacid = $this->accountHostController->getAccountHostUniacid($_SERVER['HTTP_HOST']);
            }
        }

        // 判断系统是否存在uniacid start
        if (!empty($uniacid)) {

            $uniacidList = Cache::get(CacheKeyEnum::SYSTEM_UNIACID_LIST_KEY);
            if ($uniacidList && !in_array($uniacid, $uniacidList)) {
                if ($this->module != 'admin' && !empty($_SERVER['HTTP_HOST'])) { // 域名路由
                    $uniacid = $this->accountHostController->getAccountHostUniacid($_SERVER['HTTP_HOST']);
                } else {
                    $uniacid = 0;
                }
            } else {
                // 还需要判定商户是否有应用权限
                if ($this->module && $this->module != 'admin') {
                    $uniacidModuleList = Cache::get(CacheKeyEnum::UNIACID_MODULE_LIST_KEY . "_{$uniacid}");
                    $systemModuleList = Cache::get(CacheKeyEnum::SYSTEM_MODULE_LIST_KEY);

                    if (($uniacidModuleList && !in_array($this->module, $uniacidModuleList)) || ($systemModuleList && !in_array($this->module, $systemModuleList))) {

                        if (!$checkUrl) {
                            self::getUniacid(true);
                        }

                        if (env('DEFAULT_APP') == $this->module) {
                            $uniacid = 0; // 当访问域名且没有访问权限时，默认跳转到系统默认应用
                        } else {
                            if ($this->request->isAjax()) {
                                throw new ApiException("商户暂无该应用的访问权限，禁止访问!", 403);
                            } else {
                                exit('<!DOCTYPE html> <html> <head> <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"> <title>权限不足</title> <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0"> <meta http-equiv="X-UA-Compatible" content="IE=EmulateIE7"> <style> html{height:100%;}@media only screen and (max-width: 320px){html {font-size: 28px !important; }}@media (min-width: 320.99px) and (max-width: 428px){html {font-size: 30px !important;}}@media (min-width: 428.99px) and (max-width: 481px){html { font-size: 32px !important; }}@media (min-width: 481.99px) and (max-width: 640.99px){html {font-size: 35px !important; }}@media (min-width: 641px){html {font-size: 40px !important; }}p img{max-width:100%;max-height:300px;}p{height:auto;width:100%;font-size: .6rem;}body{height:96%;}.pic{ padding: 0 15px;opacity: 0.6;box-sizing: border-box;position: absolute;top: 45%;left: 50%;-webkit-transform: translate(-50%, -50%);-moz-transform: translate(-50%, -50%);-ms-transform: translate(-50%, -50%);-o-transform: translate(-50%, -50%);transform: translate(-50%, -50%); }@media (max-width:767px){.pic{ position: absolute;opacity: 0.6;top:45%;width:96%;text-align:center;}} </style> </head> <body oncontextmenu="self.event.returnValue=false" onselectstart="return false"> <div class="pic"> <div style="text-align: center;"> <svg t="1715849548098" class="icon" viewBox="0 0 2119 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="6799" width="200" height="200"><path d="M1353.143009 250.372941h-5.801735c19.239302 0 34.847841-15.596062 34.847841-34.847841s-15.596062-34.847841-34.847841-34.847841h-58.079735c-19.239302 0-34.835364-15.596062-34.835364-34.847841s15.596062-34.847841 34.835364-34.847841h209.062092c19.239302 0 34.835364 15.596062 34.835364 34.847841s-15.596062 34.847841-34.835364 34.847841-34.847841 15.596062-34.847841 34.847841c0 19.239302 15.596062 34.847841 34.847841 34.847841h133.564675c19.251779 0 34.847841 15.596062 34.847841 34.847841 0 19.239302-15.596062 34.847841-34.847841 34.847841H1353.143009c-19.239302 0-34.847841-15.596062-34.847841-34.847841s15.608539-34.847841 34.847841-34.847841zM935.006348 749.808753c-19.239302 0-34.835364 15.596062-34.835364 34.847841s15.596062 34.847841 34.835364 34.847841h-5.801735c19.239302 0 34.847841 15.596062 34.847841 34.847841 0 19.239302-15.596062 34.847841-34.847841 34.847841H789.825727c-19.239302 0-34.847841-15.596062-34.847841-34.847841s15.596062-34.847841 34.847841-34.847841c19.239302 0 34.847841-15.596062 34.84784-34.847841s-15.596062-34.847841-34.84784-34.847841H563.333476c-19.239302 0-34.835364 15.596062-34.835364 34.847841s15.596062 34.847841 34.835364 34.847841H667.876999c19.239302 0 34.847841 15.596062 34.847841 34.847841 0 19.239302-15.596062 34.847841-34.847841 34.847841h-261.340092c-19.239302 0-34.847841-15.596062-34.847841-34.847841s15.596062-34.847841 34.847841-34.847841h11.615947c19.239302 0 34.835364-15.596062 34.835364-34.847841s-15.596062-34.847841-34.835364-34.847841h-133.564675c-19.239302 0-34.847841-15.596062-34.847841-34.847841 0-19.239302 15.596062-34.847841 34.847841-34.847841h360.056926c19.251779 0 34.847841-15.596062 34.847841-34.84784s-15.596062-34.847841-34.847841-34.847841h-58.067258c-19.251779 0-34.847841-15.596062-34.847841-34.847841s15.596062-34.847841 34.847841-34.847841h261.327614c19.251779 0 34.847841 15.596062 34.847841 34.847841 0 19.239302-15.596062 34.847841-34.847841 34.847841-19.239302 0-34.847841 15.596062-34.847841 34.847841 0 19.239302 15.596062 34.847841 34.847841 34.84784h162.610781c19.239302 0 34.847841 15.596062 34.847841 34.847841s-15.596062 34.847841-34.847841 34.847841h-75.509894z m0 0" p-id="6800"></path><path d="M1016.317977 99.378107c-211.68223 0-383.28882 171.594112-383.288819 383.28882 0 211.68223 171.606589 383.28882 383.288819 383.288819s383.28882-171.606589 383.28882-383.288819c0-211.694707-171.606589-383.28882-383.28882-383.28882zM1168.760126 757.34477c-103.320792 59.651818-230.622088 59.651818-333.930403 0C731.508932 697.692953 667.864522 587.44751 667.876999 468.143874c-0.012477-119.303636 63.631933-229.549079 166.952724-289.200897s230.622088-59.651818 333.930403 0c103.320792 59.651818 166.965201 169.897261 166.952724 289.200897 0.012477 119.303636-63.631933 229.549079-166.952724 289.200896z" p-id="6801"></path><path d="M655.94913 612.837898c-69.496052 55.571888-105.06755 107.076323-90.868895 142.211132 29.432888 72.852325 261.826689 47.674042 519.061897-56.258115 257.235208-103.932157 441.905059-247.241252 412.459694-320.106053-12.027683-29.757286-57.905059-43.157423-125.517107-41.510479l8.035091 9.519837c57.78029-0.723657 96.458524 10.992104 106.702018 36.320109 27.037333 66.900868-154.762842 203.44751-406.046594 304.984111-251.283751 101.524125-476.902622 129.58456-503.939955 62.671216-12.801248-31.666244 21.198167-78.953504 87.213179-130.73243l-7.099328-7.099328z m0 0M144.123806 407.16951h-88.922507c-17.642265 0-31.940735-14.29847-31.940735-31.940735v-5.814212c0-17.642265 14.29847-31.940735 31.940735-31.940735h134.163564l18.85252-29.033629h-54.29925c-17.642265 0-31.940735-14.29847-31.940735-31.940735v-5.801735c0-17.642265 14.29847-31.940735 31.940735-31.940735h99.552783l19.588654-30.169022h143.995321v350.300029h77.468759v103.582805h-77.468759v128h-122.09845v-128.012477H68.439237l-26.10157-98.517204 101.786139-156.771615z m159.242032-67.225266h-3.36875c-17.679696 34.523443-39.576567 73.264061-61.473438 108.623453l-68.210936 110.307827h124.63125v-46.314066c0-77.456282 3.368749-125.454723 8.421874-172.617214z m0 0" p-id="6802"></path><path d="M392.013854 227.141047c17.417682 0 26.139 8.708841 26.139 26.139v278.757773c0 17.417682-8.708841 26.126523-26.139 26.126524-17.417682 0-26.139-8.708841-26.139-26.126524V253.26757c0.012477-17.417682 8.721318-26.126523 26.139-26.126523z m0 0M1592.349169 355.714982l110.482504 9.906619c2.93206-41.62277 26.962472-66.85096 65.316307-66.850961 42.433765 0 63.906424 24.067843 63.906424 69.807974 0 57.430939-25.627449 81.58612-84.967346 81.586119h-32.090457v92.802808h32.926406c69.3089 0 105.167365 26.313676 105.167365 74.861097 0 54.299249-25.827079 82.434545-77.381421 82.434546-44.816844 0-73.638366-27.012379-93.064821-69.670729l-108.486207 49.233649c25.727264 67.262696 93.127205 113.22741 185.54323 113.22741 130.170972 0 207.240472-65.453553 207.240472-181.138902 0-44.330247-14.610391-75.884199-40.899113-95.585145l-13.961595-10.4556h129.210255c6.937128 0 13.574812-2.757384 18.478214-7.648309 4.903402-4.903402 7.648309-11.553563 7.648309-18.478214v-5.814212c0-6.937128-2.757384-13.574812-7.648309-18.478214a26.142743 26.142743 0 0 0-18.478214-7.660786h-127.96257l7.124281-9.332684c9.756896-12.776294 16.906131-27.673652 21.098353-44.3552l1.110439-4.391851h46.363974c6.924652 0 13.574812-2.757384 18.478214-7.660786 4.903402-4.903402 7.648309-11.541086 7.648309-18.478214v-5.814212c0-6.924652-2.757384-13.574812-7.648309-18.478214a26.142743 26.142743 0 0 0-18.478214-7.660786h-44.455016l-0.698703-5.015693c-11.229165-80.899893-69.358807-120.688566-176.584852-120.688566-103.24593 0.049907-169.061312 60.71235-174.937909 149.797056z m0 0" p-id="6803"></path><path d="M1887.426662 314.017351c-3.431134-35.44673-51.379667-56.906911-110.345258-57.817721v-0.024954c-8.459304 0-16.257335-4.616434-20.337264-12.015206a23.253105 23.253105 0 0 1 0.698703-23.618677 23.204445 23.204445 0 0 1 21.011015-10.779998c82.546837 1.497222 142.635345 41.310849 154.201384 97.057413 0.773565 2.270787 1.197778 4.703772 1.222731 7.236573l0.012477 0.124769c0.012477 0.062384 0 0.137245-0.012477 0.199629-0.049907 11.990252-9.207915 21.959255-21.135783 23.032265-11.927868 1.073009-22.720343-7.099327-24.928745-18.877474-0.124768-0.112292-0.187153-0.249537-0.19963-0.411736v-0.898333c-0.137245-1.060532-0.19963-2.133541-0.187153-3.20655z m0 0M1631.900783 55.821425c0 17.642265 14.29847 31.940735 31.940735 31.940735s31.940735-14.29847 31.940735-31.940735-14.29847-31.940735-31.940735-31.940735-31.940735 14.29847-31.940735 31.940735z m0 0" p-id="6804"></path><path d="M1627.558839 76.146213c-4.965786-19.226825 2.844722-39.489229 19.426455-50.418949 16.59421-10.92972 38.291451-10.093771 53.999805 2.071157S1722.931877 60.762258 1716.5063 79.552393c-6.425578 18.802612-24.08032 31.429184-43.955941 31.441661v-11.615947c15.171849-0.012477 28.609416-9.831757 33.200896-24.304903 4.591481-14.460669-0.71118-30.243883-13.100692-39.002632-12.389512-8.758748-29.033629-8.484258-41.136173 0.67375s-16.893654 25.090945-11.828053 39.401891h-12.127498z m0 0M557.531741 122.610001l0 0z m0 0" p-id="6805"></path><path d="M673.678734 110.994054h-11.615947c0-24.11775-16.506872-45.116288-39.950873-50.793255-23.444-5.689443-47.72395 5.402476-58.778438 26.850181-11.054489 21.447704-5.988888 47.661565 12.227312 63.457257 18.228677 15.795692 44.904182 17.055853 64.542743 3.069305l7.585925 8.858563c-10.967151 8.109952-24.267472 12.47685-37.904669 12.451896-35.284531 0-63.88147-28.609416-63.88147-63.88147 0-35.284531 28.596939-63.88147 63.88147-63.88147 35.297008-0.012477 63.893947 28.584462 63.893947 63.868993z m0 0M1399.606797 761.4247c0 10.368262 5.527244 19.962959 14.510576 25.140852 8.983332 5.190369 20.050297 5.190369 29.033629 0a29.044858 29.044858 0 0 0 14.523053-25.140852c0-10.380739-5.527244-19.962959-14.523053-25.153329a29.013666 29.013666 0 0 0-29.033629 0 29.037372 29.037372 0 0 0-14.510576 25.153329z m0 0" p-id="6806"></path><path d="M1397.173811 784.644117c-7.660786-18.939858-2.05868-40.662053 13.811873-53.513208 15.870553-12.851155 38.278975-13.849303 55.210059-2.432985 16.943562 11.416317 24.429672 32.552101 18.465738 52.090847-5.963934 19.538746-23.992982 32.888976-44.417585 32.888975v-11.615947c15.770738 0 29.570134-10.592845 33.650063-25.827078 4.07993-15.234233-2.582708-31.304416-16.232381-39.189785a34.839107 34.839107 0 0 0-42.05946 5.539721c-11.154304 11.154304-13.42509 28.409787-5.539721 42.05946h-12.888586z m0 0M801.441674 15.171849c0 8.010137 6.500439 14.523053 14.510576 14.523053 8.022614 0 14.523053-6.500439 14.523053-14.523053 0-8.022614-6.500439-14.523053-14.523053-14.523053-8.010137 0-14.510576 6.500439-14.510576 14.523053z m0 0M1277.645592 369.414563c0 5.190369 2.757384 9.98148 7.261526 12.576664A14.524301 14.524301 0 0 0 1299.430171 356.837898c-4.491666-2.595185-10.01891-2.595185-14.523053 0-4.491666 2.595185-7.261526 7.398772-7.261526 12.576665z m0 0M946.622295 799.16717c0 11.229165 9.095623 20.324788 20.324788 20.324788s20.324788-9.095623 20.324788-20.324788-9.1081-20.324788-20.324788-20.324788c-11.216688 0-20.324788 9.095623-20.324788 20.324788z m0 0M1649.318465 999.520421c0 12.826201 10.405693 23.231894 23.231894 23.231894 12.826201 0 23.231894-10.405693 23.231894-23.231894s-10.405693-23.231894-23.231894-23.231894c-12.826201 0-23.231894 10.405693-23.231894 23.231894z m0 0M876.93909 395.553563c-41.697631 0-75.497417-33.799786-75.497416-75.497417s33.799786-75.497417 75.497416-75.497417 75.497417 33.799786 75.497417 75.497417-33.799786 75.497417-75.497417 75.497417z m0-11.615947c35.284531 0 63.88147-28.596939 63.88147-63.88147s-28.596939-63.88147-63.88147-63.88147-63.88147 28.596939-63.88147 63.88147 28.596939 63.88147 63.88147 63.88147z m29.021153 54.598694a122.225714 122.225714 0 0 1-29.021153 3.481041c-38.85291 0-73.46369-18.17877-95.797251-46.476265l8.696364-7.735647c20.187543 25.926893 51.691588 42.595965 87.100887 42.595965 9.145531 0 18.029048-1.11044 26.525783-3.206551l2.49537 11.341457z m28.846476-11.104397l-4.541573-10.755044c33.986938-18.802612 57.006726-55.022907 57.006725-96.620723 0-12.090067-1.946389-23.743445-5.539721-34.623258l11.254118-2.957013a121.590642 121.590642 0 0 1 5.90155 37.580271c0.012477 46.41388-25.914417 86.764012-64.081099 107.375767z m113.451993 130.732431c-20.848816 0-37.74247-16.906131-37.74247-37.742471 0-20.848816 16.893654-37.74247 37.74247-37.74247s37.74247 16.893654 37.74247 37.74247c0 20.836339-16.893654 37.74247-37.74247 37.742471z m0-11.615947c9.332683 0 17.966663-4.978263 22.633005-13.063262a26.152724 26.152724 0 0 0 0-26.139 26.135257 26.135257 0 0 0-22.633005-13.063261c-14.435715 0-26.139 11.703285-26.139 26.126523 0 14.435715 11.703285 26.139 26.139 26.139z m78.342139-29.046106h-11.615947a66.526562 66.526562 0 0 0-19.127011-43.930988l8.858564-7.548494a78.120051 78.120051 0 0 1 21.884394 51.479482z m-1.285116 17.430159c-5.46486 28.896384-26.625597 52.31543-54.8108 60.687396l-2.620138-11.353933a66.903363 66.903363 0 0 0 45.577931-49.333463h11.853007zM807.243409 616.231602c-28.87143 0-52.265523-23.40657-52.265523-52.265523 0-28.87143 23.394093-52.265523 52.265523-52.265523s52.265523 23.394093 52.265523 52.265523-23.394093 52.265523-52.265523 52.265523z m0-11.615947c14.523053 0 27.948143-7.748124 35.209669-20.324788a40.672034 40.672034 0 0 0 0-40.662053 40.659557 40.659557 0 0 0-35.209669-20.324788c-22.445852 0-40.649576 18.203724-40.649576 40.649576 0 22.458329 18.203724 40.662053 40.649576 40.662053z m-84.593041 10.268447l9.469929-6.81236c15.134419 25.727264 43.107515 42.995224 75.123112 42.995224 21.846964 0 41.809923-8.047568 57.10654-21.322936l7.660786 8.721317a98.348767 98.348767 0 0 1-64.767326 24.217566c-35.895896 0.012477-67.312604-19.151964-84.593041-47.798811z m563.704065 47.811287c-17.642265 0-31.940735-14.29847-31.940735-31.940735s14.29847-31.940735 31.940735-31.940735 31.940735 14.29847 31.940735 31.940735-14.29847 31.940735-31.940735 31.940735z m0-11.615947c11.229165 0 20.324788-9.095623 20.324788-20.324788s-9.1081-20.324788-20.324788-20.324788c-11.229165 0-20.324788 9.095623-20.324788 20.324788s9.1081 20.324788 20.324788 20.324788z m-65.578321 10.817429l10.667706-4.616434c10.181109 21.060922 31.516522 34.448582 54.910615 34.436105 5.627059 0 11.066966-0.761088 16.244858-2.183449l3.505995 11.079442a72.827371 72.827371 0 0 1-19.750853 2.719954c-28.946291 0.012477-53.924944-16.918608-65.578321-41.435618z m0 0" p-id="6807"></path></svg></div> <p > <span style="font-weight: bold;">商户暂无该应用的访问权限，禁止访问 !</span> <br> <br> </p> <p><div style="width: 100px; height: 40px; font-size: 15px; color: #fff; background: #ff0000; opacity: 1; line-height: 40px; text-align: center; margin: 0 auto; border-radius: 4px; cursor: pointer;" onclick="window.close()">知道了</div></p> </div> </body> </html>');
                            }
                        }
                    }
                }
            }
        }
        # end

        # 支付回调 start
        if (!empty($this->params['attach']) || !empty($this->params['body'])) {
            $attachArr = $this->params['attach'] ?? $this->params['body'];
            $attachArr = explode(":", $attachArr);
            $uniacid = $attachArr[1] ?? 0;
        }
        # end

        # 这里是后台首页默认的uniacid 不计入cookie start
        if (empty($uniacid)) {
            $uniacid = $this->websiteSets['uniacid'] ?? 0; // 默认uniacid
        } else {
            isetcookie('uniacid', $uniacid); // 缓存当前所选商户uniacid
        }
        // end

        empty($uniacid) && exit("<p style='width:100%;height:80px;line-height:80px;text-align: center;font-size: 15px;'>商户不存在,请联系管理员配置默认商户</p>");

        $this->uniacid = intval($uniacid);
        return $uniacid;
    }

    // 校验插件路由
    protected function checkAppRouter(): string
    {
        $url = $this->url;

        $appControllerPath = $this->iaRoot . "/app/{$this->module}/controller";
        // $appControllerDirs = FileUtil::dirsOnes($appControllerPath);

        $urlPath = $appControllerPath . "/{$this->controller}/" . ucfirst($this->action) . ".php";

        # 校验控制器是否存在
        if (!is_file($urlPath)) {
            if (is_mobile()) {
                $this->controller = 'mobile';
            } else {
                $this->controller = 'web';
            }
        }

        if (count(explode("/", $url)) > 2) {
            $url = "{$this->controller}.{$this->action}";
        } else {
            $url = "{$this->module}/{$this->controller}.{$this->action}";
        }

        return $url;
    }
}
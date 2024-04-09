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
        $url = $request->header()['host'];
        $module = app('http')->getName();

        if (empty($module) || empty($request->root())) { // 独立域名访问逻辑
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
            } else { // 空应用的处理逻辑
                $pathInfo = $request->pathinfo();
                if (!empty($pathInfo)) {
                    exit('<!DOCTYPE html> <html> <head> <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"> <title>很抱歉，您访问的地址不存在</title> <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0"> <meta http-equiv="X-UA-Compatible" content="IE=EmulateIE7"> <style> html{height:100%;}@media only screen and (max-width: 320px){html {font-size: 28px !important; }}@media (min-width: 320.99px) and (max-width: 428px){html {font-size: 30px !important;}}@media (min-width: 428.99px) and (max-width: 481px){html { font-size: 32px !important; }}@media (min-width: 481.99px) and (max-width: 640.99px){html {font-size: 35px !important; }}@media (min-width: 641px){html {font-size: 40px !important; }}p img{max-width:100%;max-height:300px;}p{height:auto;width:100%;font-size: .6rem;}body{height:96%;}.pic{ padding: 0 15px;opacity: 0.6;box-sizing: border-box;position: absolute;top: 45%;left: 50%;-webkit-transform: translate(-50%, -50%);-moz-transform: translate(-50%, -50%);-ms-transform: translate(-50%, -50%);-o-transform: translate(-50%, -50%);transform: translate(-50%, -50%); }@media (max-width:767px){.pic{ position: absolute;opacity: 0.6;top:45%;width:96%;text-align:center;}} </style> </head> <body oncontextmenu="self.event.returnValue=false" onselectstart="return false"> <div class="pic"> <div style="text-align: center;"> <svg t="1712557302958" class="icon" viewBox="0 0 2426 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="8648" width="200" height="200"><path d="M2085.64082571 721.87954624v101.55414318c0 52.47062414-35.54395009 96.4774196-84.62845264 108.32540297 246.26928915 7.61705245 401.98662562 18.61825955 401.98662554 30.46624291 0 22.00339764-534.00799537 40.62165724-1193.2670841 40.62165741S16.46483051 985.07550939 16.46483051 962.2253353c0-14.38634521 228.49780578-27.08110481 571.24451441-34.69717384-41.46745006-16.07989784-71.08790031-56.70155508-71.08790029-104.09348852V721.87954624H323.66758895c-9.30962154 0-18.61825955-1.69256904-27.0811049-4.2309309-55.00898599-6.7712597-98.16998856-53.31641699-98.16998863-110.86474832V275.88612521c0-61.77827875 49.93127885-111.70955759 111.71054111-111.70955761s111.71054105 49.93127885 111.71054109 111.70955764V545.85138105h94.78386701V417.21613298c0-61.77926229 49.93127885-111.71054105 111.71054104-111.71054112 61.77827875 0 111.70955759 49.93127885 111.70955758 111.71054112V545.85138105h37.23651921c48.23870974 0 88.01457431 38.92908823 88.01457428 88.01457434s-38.92908823 88.01359081-88.01457428 88.01359085h-37.23651921v101.55414318c0 45.70034791-27.92788114 85.47522888-66.85696938 102.401903a34411.75814483 34411.75814483 0 0 1 537.39313347-4.23191434c275.04394658 0 528.93127167 3.38513806 730.34797257 8.46284526-45.70034791-14.38634521-78.70495276-56.70155508-78.70495284-106.6318504V721.87954624h-192.95385567c-9.30962154 0-18.61825955-1.69256904-27.08110483-4.2309309-55.00898599-6.7712597-98.16998856-53.31641699-98.16998861-110.86474832V275.88612521c0-61.77827875 49.93127885-111.70955759 111.71054104-111.70955761 61.77827875 0 111.70955759 49.93127885 111.70955758 111.70955764V545.85138105h94.78485049V417.21613298c0-61.77926229 49.93127885-111.71054105 111.70955766-111.71054112s111.71054105 49.93127885 111.710541 111.71054112V545.85138105h37.23651912c48.23870974 0 88.01359081 38.92908823 88.01359079 88.01457434s-38.92908823 88.01359081-88.01359079 88.01359085h-37.23651912z m-872.52377313 97.32321227c-130.32880064 0-236.96065106-105.78605765-236.960651-236.96065105s105.78605765-236.96065106 236.95966753-236.96065101 236.96163451 105.78605765 236.96163454 236.95966751-106.63185042 236.96163451-236.96065107 236.96163455z m0-114.24890284c66.85696938 0 121.86595539-54.16220974 121.8659554-121.86595531s-54.1631932-121.8649719-121.8659554-121.86497193-121.86595539 54.16220974-121.86595535 121.86497188 55.00898599 121.86595539 121.86595535 121.86595536z" p-id="8649"></path><path d="M986.31181594 650.79164596H958.38295126c-7.61606901-28.77367388-10.15541428-59.24090033-7.61606904-90.55293622 12.69377621-144.71514586 140.48323149-252.19377254 286.04515367-239.49999635 7.61705245 0.84579276 12.69475963 7.61705245 12.69475963 14.38732865s-7.61705245 12.69377621-14.3873287 12.6937762c-130.32880064-11.00120716-244.57770358 84.62845269-256.42568689 214.95725333-4.23093089 30.46624293-0.84579276 60.08669322 7.61705246 88.01457439z m453.61047346 0c4.23191429-15.23410494 7.61705245-30.46624293 9.30962151-47.39291702 7.61606901-85.4742455-32.15979552-167.56531988-101.55414325-214.95725333-5.9244834-4.23093089-7.61705245-12.69377621-3.38612162-18.61727617 4.23191429-5.9244834 12.69475963-7.61705245 18.61825964-3.3861215 77.0123837 53.31641699 121.01917915 144.71514586 112.55633382 240.34578908-1.69158558 15.23410494-4.23093089 30.46722647-7.616069 44.85357168h-27.9278811z m-120.17338635-308.89532767c6.7712597 3.38513806 10.15639777 11.00120716 6.77125965 17.77148333-3.38612161 6.77027624-11.00219055 10.15541428-17.77246681 6.77027632-10.15541428-4.23093089-20.31082869-8.46284534-31.31301928-11.00120721-6.77027624-1.69256904-11.00120716-9.30962154-9.308638-16.92569054 1.69158558-6.77027624 9.30863807-11.00219055 16.92569054-9.30962158 11.84798336 3.38513806 22.84919047 7.61705245 34.6971739 12.69475968z m115.94245537 159.94826743h27.92689768c8.46284534 25.38853578 12.69475963 53.31641699 12.69475967 81.24331464 0 145.56192206-117.63404098 263.19596312-264.04273939 263.19596305-7.61606901 0-13.54055239-5.92349997-13.54055247-13.54055241s5.9244834-13.54055239 13.54055247-13.54055249c130.32880064 0 236.96163451-105.78605765 236.96163453-236.96065105 0.84579276-27.92788114-4.23191429-55.00898599-13.54055249-80.39752174zM1023.54833498 440.91209969c-29.62045021 39.77488105-47.39193351 88.86036712-47.3919334 142.17580062 0 55.00898599 18.61727605 106.63283388 52.46964064 148.94804373 20.31082869 24.54175953 44.85357169 44.8525882 72.78046932 60.08570966 7.61705245 4.23093089 15.23410494 7.61606901 22.85017399 11.00120706 6.77027624 2.53934534 10.15541428 11.00219055 7.6170525 17.77246682-2.53934534 6.77027624-11.00219055 10.15541428-17.77246682 7.61705247-8.46284534-3.38513806-17.77148332-7.61705245-25.3885358-11.84798334-31.31301929-16.92569059-58.39412413-39.77586446-81.24429806-66.85696936-37.23651921-46.54614076-58.39314062-104.09348854-58.39314068-165.87373426 0-71.08691678 27.92689764-136.25131717 74.47303831-183.64423414v40.62264069z m189.5687176 276.73651565c-74.47303836 0-135.40552436-60.93346942-135.40552427-135.40650788s60.93248596-135.40552436 135.40552427-135.40552431 135.40552436 60.93150247 135.40552444 135.40552431c0 75.31981464-60.93248596 135.40552436-135.40552444 135.40552436z m0-26.23531207c60.08669322 0 108.32441942-48.23870974 108.32441949-108.32540296s-48.23772631-108.32441942-108.32441949-108.32441947-108.32540295 48.23870974-108.3254029 108.32441947 48.23969326 108.32540295 108.3254029 108.32540296z m5.07770726-170.95045796l57.54734779 57.54734792c2.53934534 2.53934534 2.53934534 6.77027624 0 9.30962142l-57.54734779 57.5473479c-2.53934534 2.53934534-6.77027624 2.53934534-9.3096216 0l-57.54734786-57.5473479c-2.53836184-2.53934534-2.53836184-6.77027624 0-9.30962142l57.54734786-57.54734792c1.69256904-2.53836184 6.77027624-2.53836184 9.3096216 0z m-5.07770726 33.85138108l-28.77367384 28.77367392 28.77367384 28.77367382 28.7736739-28.77367382-28.7736739-28.77367392zM226.34437658 454.45166873v-33.85138112c0-7.61606901 5.9244834-13.54055239 13.54055249-13.5405524 7.61705245 0 13.54055239 5.9244834 13.54055239 13.54153585v33.85138102c0 7.61606901-5.92349997 13.54055239-13.54055239 13.5405525-7.61606901 0-13.54055239-5.9244834-13.54055249-13.54055249z m222.5743058 236.96163452h94.78386709c15.23410494 0 27.08110481 11.84798336 27.08110487 27.08110486v104.94026483c0 46.54515725 38.08329541 84.62845269 84.62845275 84.62845275s84.62943622-38.08329541 84.62943611-84.62845275V718.49440814c0-15.23410494 11.84798336-27.08110481 27.08110487-27.08110489h33.85138113c33.85138105 0 60.93346942-27.08110481 60.93346943-60.93248598s-27.08110481-60.93346942-60.93346943-60.93346936h-33.85138113c-15.23312146 0-27.08110481-11.84798336-27.08110487-27.08110498v-67.70276214c0-7.61705245 5.9244834-13.54055239 13.5405525-13.54055236 7.61705245 0 13.54055239 5.92349997 13.54055237 13.54055236v60.93248593c0 3.38513806 3.38513806 6.77027624 6.77027624 6.77027621h27.08110489c48.23969326 0 88.01457431 38.92908823 88.01457436 88.01457434s-38.92908823 88.01359081-88.01457436 88.01359084h-27.08110489c-3.38513806 0-6.77027624 3.38513806-6.77027624 6.77027617v98.16998866c0 61.77827875-49.93127885 111.70955759-111.70955747 111.70955759s-111.71054105-49.93127885-111.71054113-111.70955759v-98.16998866c0-3.38513806-3.38513806-6.77027624-6.77027616-6.77027617h-88.01359093c-7.61705245 0-13.54055239-5.9244834-13.54055234-13.54055244 0-7.61705245 5.92349997-13.54055239 13.54055234-13.54055242z m-54.16319315 26.23531209H333.8239868c-60.08669322-1.69256904-108.32441942-50.77707157-108.32441948-111.71054107V494.22753314c0-7.61606901 5.92349997-13.54055239 13.54055244-13.54055243 7.61606901 0 13.54055239 5.9244834 13.54055245 13.54055243v111.71054113c0 45.69936444 36.38974286 82.93686716 81.24331459 84.62845276h60.93248596c7.61705245 0 13.54055239 5.9244834 13.54055242 13.54055239 0 7.61705245-5.92349997 13.54153592-13.54055242 13.54153592z m372.36715884-297.04734428c0 7.61606901-5.92349997 13.54055239-13.54055237 13.54055244-7.61606901 0-13.54055239-5.9244834-13.54055245-13.54055244v-3.38513808c0-46.54614076-38.08329541-84.62845269-84.62845265-84.62845269s-84.62845269 38.08231194-84.62845278 84.62845269v125.25010995c0 15.23410494-11.84896691 27.08110481-27.08208834 27.08110498h-94.7838671c-15.23410494 0-27.08110481-11.84798336-27.08110479-27.08110498V275.88612521c0-46.54515725-38.08329541-84.62845269-84.6284527-84.62845268s-84.62943622 38.08329541-84.62943621 84.62845271v104.94026483c0 7.61606901-5.9244834 13.54055239-13.54055241 13.54055242-7.61705245 0-13.54055239-5.9244834-13.54055246-13.54055242V275.88514173c0-61.77827875 49.93127885-111.70955759 111.70955757-111.70955758 61.77926229 0 111.71054105 49.93127885 111.710541 111.70955756v259.81082503c0 3.38513806 3.38513806 6.77027624 6.77027631 6.77027619h81.24331464c3.38513806 0 6.77027624-3.38513806 6.77027614-6.77027619v-118.47983376c0-61.77926229 49.93127885-111.71054105 111.71054113-111.71054109s111.70955759 49.93127885 111.70955747 111.71054109v3.38513808z m803.97521826 33.85138102v-33.85138102c0-7.61705245 5.92349997-13.54153592 13.53956887-13.54153585s13.54055239 5.9244834 13.54055248 13.54153585v33.85138102c0 7.61606901-5.92349997 13.54055239-13.54055248 13.5405525s-13.54055239-5.9244834-13.54055241-13.5405525z m223.41911511 236.95966762h94.78485045c15.23312146 0 27.08110481 11.84896691 27.0811049 27.08208844v104.9402648c0 46.54515725 38.08329541 84.62845269 84.62845274 84.62845275s84.62845269-38.08329541 84.62845269-84.62845275V718.49440814c0-15.23410494 11.84896691-27.08110481 27.08208839-27.08110489h33.85138111c33.85138105 0 60.93248596-27.08110481 60.93248586-60.93248598s-27.08110481-60.93346942-60.93248586-60.93346936h-33.85138111c-15.23410494 0-27.08110481-11.84798336-27.08110488-27.08110498v-67.70276214c0-7.61705245 5.92349997-13.54055239 13.54055243-13.54055236s13.54055239 5.92349997 13.54055245 13.54055236v60.93248593c0 3.38513806 3.38513806 6.77027624 6.77027618 6.77027621h27.08110493c48.23870974 0 88.01457431 38.92908823 88.01457421 88.01457434s-38.93007171 88.01359081-88.01457421 88.01359084h-27.08110493c-3.38513806 0-6.77027624 3.38513806-6.77027618 6.77027617v98.16998866c0 61.77827875-49.93127885 111.70955759-111.71054108 111.70955759s-111.70955759-49.93127885-111.70955764-111.70955759v-98.16998866c0-3.38513806-3.38513806-6.77027624-6.77027612-6.77027617h-88.01457433c-7.61606901 0-13.54055239-5.9244834-13.54055249-13.54055244 0-7.61705245 5.9244834-13.54055239 13.54055249-13.54055242z m-54.16220975 26.23629564h-60.93248593c-60.08669322-1.69256904-108.32540295-50.77707157-108.32540297-111.71054107V494.22753314c0-7.61606901 5.9244834-13.54055239 13.54055241-13.54055243s13.54055239 5.9244834 13.54055248 13.54055243v111.71054113c0 45.69936444 36.39072639 82.93686716 81.24429808 84.62845276h60.93248593c7.61705245 0 13.54055239 5.9244834 13.54055242 13.54055239 0 7.61705245-5.92349997 13.54153592-13.54055242 13.54153592z m371.52038259-297.04734428c0 7.61606901-5.92349997 13.54055239-13.54055237 13.54055244s-13.54055239-5.9244834-13.54055244-13.54055244v-3.38513808c0-46.54614076-38.08231194-84.62845269-84.62845271-84.62845269s-84.62845269 38.08231194-84.62845275 84.62845269v125.25010995c0 15.23410494-11.84896691 27.08110481-27.08208832 27.08110498h-93.93807425c-15.23312146 0-27.08110481-11.84798336-27.08110486-27.08110498V275.88612521c0-46.54515725-38.08231194-84.62845269-84.62845275-84.62845268s-84.62845269 38.08329541-84.62845267 84.62845271v104.94026483c0 7.61606901-5.9244834 13.54055239-13.54153596 13.54055242s-13.54055239-5.9244834-13.54055241-13.54055242V275.88514173c0-61.77827875 49.93127885-111.70955759 111.71054104-111.70955758s111.70955759 49.93127885 111.70955761 111.70955756v259.81082503c0 3.38513806 3.38513806 6.77027624 6.77027621 6.77027619h81.24429812c3.38513806 0 6.77027624-3.38513806 6.77027612-6.77027619v-118.47983376c0-61.77926229 49.93127885-111.71054105 111.70955764-111.71054109s111.71054105 49.93127885 111.71054108 111.71054109v3.38513808zM814.51458168 49.07990492c-11.00120716 0-20.31082869 9.30962154-20.31082867 20.31082879 0-11.00120716-9.30863807-20.31082869-20.3108287-20.31082879 11.00219055 0 20.31082869-9.30863807 20.3108287-20.31082859 0 11.00219055 8.46284534 20.31082869 20.31082867 20.31082859z m837.8265992 878.44825655c-11.00219055 0-20.31082869 9.30863807-20.31082863 20.31082865 0-11.00219055-9.30962154-20.31082869-20.31181211-20.31082865 11.00219055 0 20.31181214-9.30962154 20.31181211-20.31181214 0 11.00219055 9.30863807 20.31181214 20.31082863 20.31181214zM895.75887975 28.76907628c-11.00219055 0-20.31082869 9.30962154-20.3108287 20.31082864 0-11.00120716-9.30962154-20.31082869-20.31082863-20.31082864 11.00120716 0 20.31082869-9.30863807 20.31082863-20.31082853 0 11.00219055 8.46284534 20.31082869 20.3108287 20.31082853z m837.82561571 878.44727305c-11.00219055 0-20.31082869 9.30962154-20.31082859 20.31181214 0-11.00219055-9.30962154-20.31181214-20.31082868-20.31181214 11.00120716 0 20.31082869-9.30863807 20.31082868-20.31082861 0 11.00219055 9.30863807 20.31082869 20.31082859 20.31082861zM875.44805105 96.47183853c-22.00339764 0-40.62165724 17.77246686-40.62165728 40.62264075 0-22.00339764-17.77246686-40.62264076-40.62264076-40.62264075 22.00339764 0 40.62264076-17.77148332 40.62264076-40.62165735 0 22.00339764 17.77148332 40.62165724 40.62165728 40.62165735z m837.82561575 878.44825655c-22.00339764 0-40.62165724 17.77148332-40.62165724 40.6216572 0-22.00339764-17.77148332-40.62165724-40.62165731-40.6216572 22.00339764 0 40.62165724-17.77148332 40.62165731-40.62165738 0 22.84919047 18.61727605 40.62165724 40.62165731 40.62165738z" p-id="8650"></path></svg></div> <p > <span style="font-weight: bold;">很抱歉，您访问的地址不存在 !</span> <br> <br> </p> </div> </body> </html>');
                }
            }
        } else { // 应用非空 自动访问逻辑 route > index > web
            $pathInfo = $request->pathinfo();
            if (empty($pathInfo)) { // 参数为空访问默认应用
                $appMap = config('app.app_map');
                $realModuleName = array_key_exists($url, $appMap) ? $appMap[$url] : '';
                if ($realModuleName && $realModuleName != $module) {
                    $url = UserWrapper::getModuleOneUrl($module);
                    $url = strval($url);

                    if ($url && $url != $module) {
                        header("location:" . $url);
                        exit();
                    }
                }
            }
        }

        return $next($request);
    }

}

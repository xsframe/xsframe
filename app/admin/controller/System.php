<?php

// +----------------------------------------------------------------------
// | 星数 [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2020~2021 http://xsframe.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: guiHai <786824455@qq.com>
// +----------------------------------------------------------------------

namespace app\admin\controller;

use xsframe\base\AdminBaseController;
use xsframe\enum\SysSettingsKeyEnum;
use xsframe\util\FileUtil;
use xsframe\util\StringUtil;
use xsframe\wrapper\AttachmentWrapper;
use think\facade\Db;

class System extends AdminBaseController
{
    protected $uniacid;

    // 应用列表
    public function index()
    {
        $condition = [
            'am.uniacid' => $this->uniacid,
            'am.deleted' => 0
        ];

        $field = "am.id,am.module,am.settings," . "m.name,m.identifie,m.logo";
        $list = Db::name('sys_account_modules')->alias('am')->field($field)->leftJoin("sys_modules m", "m.identifie = am.module")->where($condition)->order("am.displayorder asc")->page($this->pIndex, $this->pSize)->select()->toArray();
        $total = Db::name('sys_account_modules')->alias('am')->field($field)->leftJoin("sys_modules m", "m.identifie = am.module")->where($condition)->count();
        $pager = pagination2($total, $this->pIndex, $this->pSize);

        foreach ($list as &$item) {
            $settings = unserialize($item['settings']);
            if (!empty($settings)) {
                if (!empty($settings['basic'])) {
                    if (!empty($settings['basic']['logo'])) {
                        $basicLogo = tomedia($settings['basic']['logo']);
                        $item['logo'] = $basicLogo;
                        $settings['basic']['logo'] = $basicLogo;
                    }
                    if (!empty($settings['basic']['name'])) {
                        $item['name'] = $settings['basic']['name'];
                    }
                }
                $item['settings'] = $settings;
            }
            $item['logo'] = tomedia($item['logo']);

            // 获取后台访问地址
            $item['url'] = $this->getRealModuleUrl($item['module']);
        }
        $list = set_medias($list, ['logo']);

        $result = [
            'list'  => $list,
            'pager' => $pager,
            'total' => $total,
        ];
        return $this->template('index', $result);
    }

    // 获取后端访问入口
    private function getRealModuleUrl($moduleName)
    {
        $realModuleName = realModuleName($moduleName);

        $moduleMenuConfigFile = IA_ROOT . "/app/" . $moduleName . "/config/menu.php";
        if (is_file($moduleMenuConfigFile)) {
            $menuConfig = include($moduleMenuConfigFile);
            $oneMenus = array_slice($menuConfig, 0, 1);
            $oneMenusKeys = array_keys($oneMenus);
            $actionUrl = $oneMenus[$oneMenusKeys[0]]['items'][0]['route'];

            if (StringUtil::strexists($actionUrl, "/")) {
                $actionUrl = "." . $actionUrl;
            } else {
                $actionUrl = "/" . $actionUrl;
            }
            $url = webUrl('/' . $realModuleName . "/{$oneMenusKeys[0]}{$actionUrl}", ['i' => $this->uniacid]);
        } else {
            $url = webUrl('/' . $realModuleName . "/web.index", ['i' => $this->uniacid]);
        }

        return $url;
    }

    // 基本信息
    public function account()
    {
        $uniacid = $this->uniacid;
        $accountSettings = $this->settingsController->getAccountSettings($this->uniacid, 'settings');

        if ($this->request->isPost()) {
            $settingsData = $this->params['data'] ?? [];
            $settingsData = array_merge($accountSettings, $settingsData);

            if (empty($uniacid)) {
                show_json(0, "更新失败");
            }

            $data = array(
                "name"         => trim($this->params["name"]),
                "logo"         => trim($this->params["logo"]),
                "keywords"     => trim($this->params["keywords"]),
                "description"  => trim($this->params["description"]),
                "copyright"    => trim($this->params["copyright"]),
                "displayorder" => intval($this->params["displayorder"]),
                "status"       => intval($this->params["status"]),
            );
            $data['settings'] = serialize($settingsData);

            Db::name('sys_account')->where(['uniacid' => $uniacid])->update($data);

            if (!empty($settingsData)) {
                $data['settings'] = serialize($settingsData);
                Db::name('sys_account')->where(["uniacid" => $uniacid])->update($data);
                # 更新缓存
                $this->settingsController->reloadAccountSettings($uniacid);
            }

            $this->success(array("url" => webUrl("account", ['tab' => str_replace("#tab_", "", $this->params['tab'])])));
        }

        $attachmentPath = IA_ROOT . "/public/attachment/images/{$this->uniacid}";
        $localAttachment = FileUtil::fileDirExistImage($attachmentPath);

        $hostList = Db::name('sys_account_host')->where(['uniacid' => $uniacid])->order('id asc')->select();
        $item = Db::name('sys_account')->where(['uniacid' => $uniacid])->find();

        $result = [
            'item'             => $item,
            'hostList'         => $hostList,
            'accountSettings'  => $accountSettings,
            'local_attachment' => $localAttachment,
        ];

        return $this->template('account', $result);
    }

    // 附件设置
    public function attachment()
    {
        $attachmentPath = IA_ROOT . "/public/attachment/";

        $type = $this->params['type'];

        # 测试配置
        $attachmentController = new AttachmentWrapper();

        switch ($type) {
            case 'alioss':
                $attachmentController->aliOss($this->params['key'], $this->params['secret'], $this->params['url'], $this->params['bucket']);
                show_json(1);
                break;
            case 'qiniu':
                // $attachmentController->qiNiu();
                break;
            case 'cos':
                // $attachmentController->cos();
                break;
            case 'buckets':
                $ret = $attachmentController->buckets($this->params['key'], $this->params['secret']);
                show_json(1, ['data' => $ret]);
                break;
            case 'upload_remote':
                $setting = $this->settingsController->getAccountSettings($this->uniacid, 'settings');
                $attachmentController->fileDirRemoteUpload($setting, $attachmentPath, $attachmentPath . "images/{$this->uniacid}");
                show_json(1, "上传成功");
        }

        $this->success(array("url" => webUrl("account", ['tab' => str_replace("#tab_", "", $this->params['tab'])])));
    }

}
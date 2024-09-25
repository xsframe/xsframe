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

namespace app\admin\controller;

use think\facade\Db;
use xsframe\facade\service\DbServiceFacade;
use xsframe\facade\wrapper\SystemWrapperFacade;
use xsframe\util\ArrayUtil;
use xsframe\util\FileUtil;
use xsframe\util\RandomUtil;
use xsframe\wrapper\AccountHostWrapper;
use xsframe\wrapper\UserWrapper;

class Account extends Base
{
    public function index()
    {
        return redirect('/admin/account/list');
    }

    public function list()
    {
        $condition = ['deleted' => 0];

        $list = Db::name('sys_account')->where($condition)->order('displayorder desc,uniacid desc')->page($this->pIndex, $this->pSize)->select();
        $total = Db::name("sys_account")->where($condition)->count();
        $pager = pagination2($total, $this->pIndex, $this->pSize);

        $list = $list->toArray();
        foreach ($list as $key => &$item) {
            $accountModulesTotal = Db::name("sys_account_modules")->where(['uniacid' => $item['uniacid'], 'deleted' => 0])->count();
            $item['total'] = $accountModulesTotal;

            $hostTotal = Db::name("sys_account_host")->where(['uniacid' => $item['uniacid']])->count();
            $item['hostTotal'] = $hostTotal;

            $memberTotal = Db::name("sys_member")->where(['uniacid' => $item['uniacid']])->count();
            $item['memberTotal'] = $memberTotal;
        }

        // 更新uniacid列表
        $this->reloadUniacidList();

        $vars = [
            'hostUrl' => $this->request->host(),
            'list'    => $list,
            'total'   => $total,
            'pager'   => $pager,
        ];
        return $this->template('list', $vars);
    }

    public function add()
    {
        return $this->post();
    }

    public function edit()
    {
        return $this->post();
    }

    public function post()
    {
        $uniacid = $this->params['id'];

        # 配置
        $accountSettings = $this->settingsController->getAccountSettings($uniacid, 'settings');

        if ($this->request->isPost()) {
            $settingsData = $this->params['data'] ?? [];

            if (!empty($_FILES)) {
                if ($_FILES['wxpay_cert_file']['name']) {
                    $settingsData['wxpay']['cert_file'] = $this->upload_cert('wxpay_cert_file');
                }

                if ($_FILES['wxpay_key_file']['name']) {
                    $settingsData['wxpay']['key_file'] = $this->upload_cert('wxpay_key_file');
                }
            }

            $settingsData = ArrayUtil::customMergeArrays($accountSettings, $settingsData);

            $data = [
                "name"         => trim($this->params["name"]),
                "logo"         => tomedia(trim($this->params["logo"])),
                "keywords"     => trim($this->params["keywords"]),
                "description"  => trim($this->params["description"]),
                "copyright"    => trim($this->params["copyright"]),
                "displayorder" => intval($this->params["displayorder"]),
                "status"       => intval($this->params["status"]),
            ];
            $data['settings'] = serialize($settingsData);

            if (!empty($uniacid)) {
                Db::name('sys_account')->where(['uniacid' => $uniacid])->update($data);
            } else {
                $data['createtime'] = time();
                $uniacid = Db::name('sys_account')->insertGetId($data);
            }

            # 分配应用
            $this->setAccountModules($uniacid);

            # 绑定域名
            $this->bindHost($uniacid);

            # 重新加载商户配置信息
            $this->settingsController->reloadAccountSettings($uniacid);

            # 管理员账号
            $this->accountUser($uniacid);

            $this->success(["url" => webUrl("account/edit", ['id' => $uniacid, 'tab' => str_replace("#tab_", "", $this->params['tab'])])]);
        }

        $item = Db::name('sys_account')->where(['uniacid' => $uniacid])->find();
        $identifies = Db::name('sys_account_modules')->where(['uniacid' => $uniacid, 'deleted' => 0])->order('displayorder asc,id asc')->column('module');
        $hostList = Db::name('sys_account_host')->where(['uniacid' => $uniacid])->order('id asc')->select();
        $modules = Db::name('sys_modules')->where(['identifie' => $identifies])->orderRaw("FIELD(identifie," . "'" . implode("','", $identifies) . "'" . ")")->select()->toArray();

        $accountUserInfo = DbServiceFacade::name('sys_account_users')->getInfo(['uniacid' => $uniacid]);
        if (!empty($accountUserInfo)) {
            $userInfo = DbServiceFacade::name('sys_users')->getInfo(['id' => $accountUserInfo['user_id']]);
            $item['username'] = $userInfo['username'];
        }

        foreach ($modules as &$module) {
            $module['logo'] = !empty($module['logo']) ? tomedia($module['logo']) : $this->siteRoot . "/app/{$module['identifie']}/icon.png";
        }

        // 更新uniacid列表
        $this->reloadUniacidList();

        $attachmentPath = IA_ROOT . "/public/attachment/";
        $localAttachment = FileUtil::fileDirExistImage($attachmentPath . 'images/' . $uniacid);

        $result = [
            'item'             => $item,
            'uniacid'          => $uniacid,
            'hostList'         => $hostList,
            'accountSettings'  => $accountSettings,
            'modules'          => $modules,
            'postUrl'          => strval(url('sysset/attachment')),
            'upload'           => (array)$accountSettings['attachment'],
            'local_attachment' => $localAttachment,
        ];
        return $this->template('post', $result);
    }

    // 通用更新数据
    public function change()
    {
        $id = intval($this->params["id"]);
        if (empty($id)) {
            $id = $this->params["ids"];
        }
        if (empty($id)) {
            $this->error(["message" => "参数错误"]);
        }

        $type = trim($this->params["type"]);
        $value = trim($this->params["value"]);

        $items = Db::name('sys_account')->field("uniacid,name")->where(['uniacid' => $id])->select()->toArray();

        foreach ($items as $item) {
            $uniacid = $item['uniacid'];
            Db::name('sys_account')->where(['uniacid' => $item['uniacid']])->update([$type => $value]);
            // 更新uniacid列表
            $this->reloadUniacidList();
            // 更新uniacid的应用列表
            SystemWrapperFacade::reloadAccountModuleList($item['uniacid']);
        }

        $this->success();
    }

    // 删除域名
    public function hostDelete()
    {
        $uniacid = intval($this->params["uniacid"]);
        $id = intval($this->params["id"]);

        Db::name('sys_account_host')->where(['id' => $id, 'uniacid' => $uniacid])->delete();
        $this->success();
    }

    // 跳转商户管理
    public function manager()
    {
        $uniacid = intval($this->params["id"]);

        # 进入当前商户默认应用
        $defaultModuleInfo = Db::name("sys_account_modules")->where(['uniacid' => $uniacid, 'deleted' => 0])->order("is_default desc")->find();
        if (empty($defaultModuleInfo)) {
            $this->error(["message" => "该商户没有分配应用"]);
        }

        # 获取后台地址
        $systemModuleList = SystemWrapperFacade::getAllModuleList();
        $accountModuleList = SystemWrapperFacade::getAccountModuleList($uniacid);
        if (empty($accountModuleList) || empty($systemModuleList) || !in_array($this->module, $accountModuleList) || !in_array($this->module, $systemModuleList)) {
            $url = strval(url('admin/system/index', ['i' => $uniacid]));
        } else {
            $realUrl = UserWrapper::getModuleOneUrl($defaultModuleInfo['module'], true);
            $url = webUrl(str_replace('.html', "", $realUrl), ['i' => $uniacid]);
        }

        $this->success(['url' => $url]);
    }

    public function query()
    {
        $kwd = trim($this->params['keyword']);

        $where = [
            'deleted' => 0
        ];

        if (!empty($kwd)) {
            $where['name'] = Db::Raw("like '%" . $kwd . "%'");
        }

        $list = Db::name('sys_account')->field("uniacid,uniacid id,name,logo")->where($where)->select();
        $list = set_medias($list, ['logo']);
        if ($this->params['suggest']) {
            exit(json_encode(['value' => $list]));
        }

        $result = [
            'list' => $list
        ];
        return $this->template('query', $result);
    }

    // 分配应用
    private function setAccountModules($uniacid): bool
    {
        $modulesIds = $this->params['modulesids'] ?? [];
        if (!empty($modulesIds)) {
            Db::name('sys_account_modules')->where(['uniacid' => $uniacid, 'module' => Db::raw("not in ('" . implode("','", $modulesIds) . "')")])->update(['deleted' => 1]);
            foreach ($modulesIds as $key => $identifie) {
                $moduleInfo = Db::name('sys_account_modules')->where(['uniacid' => $uniacid, 'module' => $identifie])->find();
                $updateData = [
                    'uniacid'      => $uniacid,
                    'displayorder' => $key + 1,
                    'module'       => $identifie,
                    'deleted'      => 0,
                ];
                if ($moduleInfo) {
                    Db::name('sys_account_modules')->where(['id' => $moduleInfo['id']])->update($updateData);
                } else {
                    Db::name('sys_account_modules')->insert($updateData);
                }
            }
            $isDefaultModule = Db::name('sys_account_modules')->where(['uniacid' => $uniacid, 'is_default' => 1])->count();
            if (empty($isDefaultModule)) {
                Db::name('sys_account_modules')->where(['uniacid' => $uniacid])->update(['is_default' => 0]);
                Db::name('sys_account_modules')->where(['uniacid' => $uniacid, 'module' => $modulesIds[0]])->update(['is_default' => 1]);
            }
        } else {
            Db::name('sys_account_modules')->where(['uniacid' => $uniacid])->update(['deleted' => 1]);
        }

        // 更新uniacid的应用列表
        SystemWrapperFacade::reloadAccountModuleList($uniacid);

        return true;
    }

    // 绑定域名
    private function bindHost($uniacid)
    {
        $hostIds = $this->params["hostIds"];
        if (!empty($hostIds)) {
            foreach ($hostIds as $k => $v) {
                $hostUrl = trim($this->params["hostUrls"][$k]);
                $data = [
                    "uniacid"        => $uniacid,
                    "host_url"       => $hostUrl,
                    "default_module" => trim($this->params["hostModules"][$k]),
                    "default_url"    => trim($this->params["hostModulesUrls"][$k]),
                    "displayorder"   => $k,
                ];
                if (empty($v)) {
                    $exitHostInfo = Db::name('sys_account_host')->where(['host_url' => $hostUrl])->find();
                    if (!empty($exitHostInfo)) {
                        $this->error(["message" => "该地址" . $hostUrl . "已存在,不可重复添加"]);
                    }
                    Db::name('sys_account_host')->insert($data);
                } else {
                    Db::name('sys_account_host')->where(['id' => $v])->update($data);
                }
            }
        }
        return true;
    }

    // 加载uniacid列表
    private function reloadUniacidList()
    {
        # 更新现有uniacid列表
        SystemWrapperFacade::reloadUniacidList();
        # 更新禁用的uniacid列表
        SystemWrapperFacade::reloadDisabledUniacidList();

        # 重新加载域名映射关系列表
        $accountHost = new AccountHostWrapper();
        $accountHost->reloadAccountHost();
    }

    // 解析证书
    private function upload_cert($fileinput)
    {
        $filename = $_FILES[$fileinput]['name'];
        $tmp_name = $_FILES[$fileinput]['tmp_name'];
        if (!empty($filename) && !empty($tmp_name)) {
            $ext = strtolower(substr($filename, strrpos($filename, '.')));

            if ($ext != '.pem') {
                $errinput = '';

                if ($fileinput == 'cert_file') {
                    $errinput = 'CERT文件格式错误';
                } else if ($fileinput == 'key_file') {
                    $errinput = 'KEY文件格式错误';
                }

                show_json(0, $errinput . ',请重新上传!');
            }

            return file_get_contents($tmp_name);
        }

        return '';
    }

    // 管理员管理
    private function accountUser($uniacid)
    {
        $username = $this->params['username'] ?? '';
        $password = $this->params['password'] ?? '';

        if (!empty($username) && !empty($password)) {
            $salt = RandomUtil::random(6);
            $newPassword = md5($password . $salt);

            $userInfo = DbServiceFacade::name('sys_users')->getInfo(['username' => $username]);

            if (!empty($userInfo)) {
                $accountUserInfo = DbServiceFacade::name('sys_account_users')->getInfo(['user_id' => $userInfo['id']]);
                if (!empty($accountUserInfo)) {
                    if ($accountUserInfo['uniacid'] == $uniacid) {
                        DbServiceFacade::name('sys_users')->updateInfo(['id' => $userInfo['id'], 'salt' => $salt, 'password' => $newPassword]);
                    }
                }
            } else {
                $userData = [
                    "username"   => $username,
                    "password"   => $newPassword,
                    "salt"       => $salt,
                    "role"       => 'manager',
                    "createtime" => TIMESTAMP,
                    "status"     => 1,
                ];
                $userId = DbServiceFacade::name('sys_users')->insertInfo($userData);
                $accountUserData = [
                    "uniacid" => $uniacid,
                    "user_id" => $userId,
                    "module"  => '',
                ];
                DbServiceFacade::name('sys_account_users')->insertInfo($accountUserData);
            }
        }
    }
}
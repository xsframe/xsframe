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

namespace app\xs_cloud\controller\web;

use xsframe\base\AdminBaseController;
use xsframe\enum\UserRoleKeyEnum;
use xsframe\util\RandomUtil;
use think\facade\Db;

class Users extends AdminBaseController
{
    protected $tableName = 'sys_users';

    public function index()
    {
        return redirect('/web.users/profile');
    }

    public function profile()
    {
        if ($this->request->isPost()) {
            $username    = $this->params['username'];
            $password    = $this->params['password'];
            $newPassword = $this->params['newPassword'];

            $adminSession = $this->adminSession;
            $userInfo     = Db::name('sys_users')->field("id,username,password,salt")->where(['id' => $adminSession['uid']])->find();
            $password     = md5($password . $userInfo['salt']);
            if (md5($password . $userInfo['salt']) != $adminSession['hash']) {
                show_json(0, "原始密码错误，请重新输入");
            }
            if (strlen($newPassword) < 6) {
                show_json(0, "请输入不小于6位数的密码");
            }
            if (empty($username)) {
                show_json(0, "登录账号不能为空");
            }

            $salt = RandomUtil::random(6);
            Db::name('sys_users')->where(['id' => $userInfo['id']])->update(['username' => $username, 'password' => md5($newPassword . $salt), 'salt' => $salt]);
            show_json(1, "密码已修改请重新登录");
        }

        return $this->template('profile');
    }

    public function list()
    {
        $condition = [
            'id'      => Db::raw("> 1"),
            'deleted' => 0
        ];

        $keyword = trim($this->params['keyword']);
        if (!empty($keyword)) {
            $condition['username'] = Db::raw(" like '%" . trim($keyword) . "%'");
        }

        $role = trim($this->params['role']);
        if (!empty($role)) {
            $condition['role'] = $role;
        }

        $list  = Db::name("sys_users")->where($condition)->order('id desc')->page($this->pIndex, $this->pSize)->select();
        $total = Db::name("sys_users")->where($condition)->count();
        $pager = pagination2($total, $this->pIndex, $this->pSize);

        $list = $list->toArray();
        foreach ($list as &$item) {
            $usersAccountInfo = Db::name("sys_account_users")->field("uniacid")->where(['user_id' => $item['id']])->find();
            $accountInfo      = Db::name("sys_account")->field("uniacid,name,logo")->where(['uniacid' => $usersAccountInfo['uniacid']])->find();
            $item['account']  = $accountInfo;
        }

        $var = [
            'list'  => $list,
            'pager' => $pager,
        ];
        return $this->template('list', $var);
    }

    public function post()
    {
        $id      = $this->params['id'];
        $uniacid = $this->params['uniacid'];
        $role    = trim($this->params['role']);
        $module  = $this->params['module'] ?? '';

        if ($this->request->isPost()) {
            $salt     = RandomUtil::random(6);
            $username = trim($this->params["username"]);
            $password = trim($this->params['password']);

            $data = array(
                "username" => $username,
                "role"     => $role,
                "status"   => trim($this->params["status"]),
            );

            if (!empty($password)) {
                $data['salt']     = $salt;
                $data['password'] = md5($password . $salt);
            }
            if (!empty($id)) {
                Db::name('sys_users')->where(['id' => $id])->update($data);
            } else {
                $data['createtime'] = time();

                $isExitUser = Db::name('sys_users')->where(['username' => $username])->count();
                if ($isExitUser) {
                    $this->error('当前账号已存在，请更换管理账号');
                }

                $id = Db::name('sys_users')->insertGetId($data);
            }

            # 非超级管理员分配项目 start
            if ($role != UserRoleKeyEnum::OWNER_KEY && !empty($uniacid)) {
                $usersAccount     = Db::name('sys_account_users')->where(['user_id' => $id])->count();
                $usersAccountData = [
                    'user_id' => $id,
                    'uniacid' => $uniacid,
                ];
                if (!empty($module)) {
                    $usersAccountData['module'] = $module;
                }
                if (!empty($usersAccount)) {
                    Db::name('sys_account_users')->where(['user_id' => $id])->update($usersAccountData);
                } else {
                    Db::name('sys_account_users')->insertGetId($usersAccountData);
                }
            }
            # 非超级管理员分配项目 end

            $this->success(array("url" => webUrl("users/list")));
        }

        $item        = Db::name('sys_users')->where(['id' => $id])->find();
        $accountInfo = [];

        if (!empty($item)) {
            $usersAccount        = Db::name('sys_account_users')->field("id,uniacid,module")->where(['user_id' => $id])->find();
            $accountInfo         = Db::name('sys_account')->where(['uniacid' => $usersAccount['uniacid']])->find();
            $accountInfo['logo'] = tomedia($accountInfo['logo']);
        }
        $var = [
            'item'        => $item,
            'accountInfo' => $accountInfo,
        ];
        return $this->template('post', $var);
    }

    public function role()
    {
        return $this->template('role');
    }

    public function log()
    {
        return $this->template('log');
    }
}
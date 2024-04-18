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

namespace xsframe\wrapper;

use think\facade\Config;

class MenuWrapper
{
    // 获取菜单
    public static function getMenusList($role, $module, $controller, $action, $full = true)
    {
        $allMenus = Config::get('menu');
        $menusList = self::buildMenu($role, $allMenus, $module, $controller, $action, $full);
        return $menusList;
    }

    // 定义菜单结构
    private static function buildMenu($role, $allMenus, $module, $controller, $action, $full)
    {
        $return_menu = [];
        $return_submenu = [];
        $submenu = [];

        $module = realModuleName($module);

        if ($controller != 'login') {
            foreach ($allMenus as $key => $val) {
                $menu_item = [
                    'route'    => empty($val['route']) ? $key : $val['route'],
                    'text'     => $val['title'],
                    'subtitle' => $val['subtitle'],
                    'active'   => 0,
                ];

                if (!strexists($menu_item['route'], 'web.') && $module != 'admin') {
                    $menu_item['route'] = "web." . $menu_item['route'];
                }

                if (!empty($val['icon'])) {
                    $menu_item['icon'] = $val['icon'];
                }

                if (strexists($controller, $menu_item['route'])) {
                    $menu_item['active'] = 1;
                    $submenu = $val;
                    $return_submenu['subtitle'] = $submenu['subtitle'];
                }

                # 设置一级目录路由 start
                if (!empty($val['items'])) {
                    $itemsOneRoute = $val['items'][0]['route'];

                    // 操作员权限验证 start
                    if (!in_array($role, ['founder', 'manager', 'owner'])) {
                        if ($val['items'][0]['perm']) {
                            foreach ($val['items'] as $itemsKey => $itemInfo) {
                                if ($itemInfo['perm']) {
                                    $permUrl = $module . "/" . $menu_item['route'] . "." . $itemInfo['route'];
                                    $isAuthPerm = cs($permUrl);

                                    if ($isAuthPerm) {
                                        $itemsOneRoute = $val['items'][$itemsKey]['route'];
                                    }
                                }
                            }
                        }
                    }
                    // 操作员权限验证 end

                    if (strexists($itemsOneRoute, '/')) {
                        $menu_item['route'] = $module . "/" . $menu_item['route'] . "." . $itemsOneRoute;
                    } else {
                        $menu_item['route'] = $module . "/" . $menu_item['route'] . "/" . $itemsOneRoute;
                    }

                } else {
                    $menu_item['route'] = $module . "/" . $menu_item['route'] . "/index";
                }
                # 设置一级目录路由 end

                if ($full) {
                    $menu_item['url'] = getSiteRoot() . $menu_item['route'];
                }

                // 主菜单权限验证 登录会报错
                if (cm($menu_item['route'])) {
                    $return_menu[] = $menu_item;
                }
            }
            unset($val);

            if (!empty($submenu)) {
                $menuRoute = $controller;

                # 是否存在多级目录
                $isMoreDir = false;
                if (count(explode(".", $controller)) == 3) {
                    $index = strripos($menuRoute, ".", 0);
                    $menuRoute = substr($menuRoute, 0, $index);
                    $isMoreDir = true;
                }

                // dump($controller);
                // dump($submenu['items']);
                // die;

                if (!empty($submenu['items'])) {

                    foreach ($submenu['items'] as $i => $child) {

                        // 操作员权限验证 start
                        if (!in_array($role, ['founder', 'manager', 'owner'])) {
                            if ($child['perm']) {
                                $controllerArr = explode(".", $controller);
                                $permUrl = $module . "/" . $controllerArr[0] . "." . $controllerArr[1] . "." . $child['route'];
                                $currentUrl = $module . "/" . $controller . "/" . $action;
                                $isAuthPerm = cs($permUrl);

                                // 权限不足提示 start
                                if ($permUrl == $currentUrl && !$isAuthPerm) {
                                    exit('Your permission is insufficient !');
                                }
                                // 权限不足提示 end

                                if (!$isAuthPerm) {
                                    unset($submenu['items'][$i]);
                                    continue;
                                }
                            }
                        }
                        // 操作员权限验证 end

                        $actionTmp = $action;

                        # 如果是二级目录补全路径 start
                        if ($isMoreDir) {
                            $controllerName = explode('.', $controller)[2];
                            $actionTmp = $controllerName . "/" . $actionTmp;
                        }
                        # 如果是二级目录补全路径 end

                        # 二级目录
                        if (empty($child['items'])) {
                            $return_menu_child = [
                                'title' => $child['title'],
                                'route' => $child['route'],
                            ];

                            $return_menu_child['active'] = 0;

                            $actionTmpArr = explode("/", $actionTmp);

                            if (strexists($return_menu_child['route'], $actionTmpArr[0]) || (strexists($return_menu_child['route'], 'main') && in_array($actionTmp, ['add', 'edit', 'post']))) {
                                if ($return_menu_child['route'] != $actionTmp && !in_array($actionTmp, ['add', 'edit', 'post'])) {
                                    $return_menu_child['active'] = 0;
                                } else {
                                    $return_menu_child['active'] = 1;
                                }
                            }

                            if ($isMoreDir) {
                                $return_menu_child['route'] = $module . "/" . $menuRoute . "." . $return_menu_child['route'];
                            } else {
                                $return_menu_child['route'] = $module . "/" . $menuRoute . "/" . $return_menu_child['route'];
                            }

                            if ($full) {
                                $return_menu_child['url'] = getSiteRoot() . $return_menu_child['route'];
                            }

                            // 子菜单权限验证
                            if (cs($return_menu_child['route'])) {
                                $return_submenu['items'][] = $return_menu_child;
                            }
                        } else {
                            # 三级目录 向下展开
                            $return_menu_child = [
                                'title'  => $child['title'],
                                'items'  => [],
                                'active' => 1, // 默认展开所有子菜单
                            ];

                            foreach ($child['items'] as $ii => $three) {
                                $return_submenu_three = [
                                    'title'  => $three['title'],
                                    'active' => 0,
                                ];

                                if (!empty($three['route'])) {
                                    $return_submenu_three['route'] = $three['route'];
                                } else {
                                    $return_submenu_three['route'] = $child['route'];
                                }

                                $return_submenu_three['route'] = $module . "/" . $menuRoute . "." . $return_submenu_three['route'];

                                if (strexists($return_submenu_three['route'], $action)) {
                                    if (strexists($return_submenu_three['route'], $controller)) {
                                        $return_submenu_three['active'] = 1; // 是否选中
                                    }
                                }

                                if ($full) {
                                    $return_submenu_three['url'] = getSiteRoot() . $return_submenu_three['route'];
                                }

                                if (cs($return_submenu_three['route'])) {
                                    $return_menu_child['items'][] = $return_submenu_three;
                                }
                            }

                            $return_submenu['items'][] = $return_menu_child;
                            unset($ii, $three);
                        }

                    }

                }
            }
        }

        return [
            'menu'    => $return_menu,
            'submenu' => $return_submenu
        ];
    }
}
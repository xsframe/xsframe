<?php

namespace xsframe\traits;

use think\facade\Db;
use xsframe\util\ExcelUtil;

trait AdminTraits
{
    protected $tableName = '';
    protected $orderBy = "id desc";

    public function index()
    {
        return $this->main();
    }

    public function main()
    {
        $result = [];

        if (!empty($this->tableName)) {
            $keyword = $this->params['keyword'] ?? '';
            $kwFields = $this->params['kwFields'] ?? '';
            $field = $this->params['field'] ?? '';
            $status = $this->params['status'] ?? '';
            $enabled = $this->params['enabled'] ?? '';
            $searchTime = trim($this->params["searchtime"] ?? 0);

            $export = $this->params['export'];
            $exportTitle = $this->params['export_title'];
            $exportColumns = $this->params['export_columns'];
            $exportKeys = $this->params['export_keys'];

            $startTime = strtotime("-1 month");
            $endTime = time();

            $condition = [
                'uniacid' => $this->uniacid,
            ];

            $fieldList = Db::name($this->tableName)->getFields();
            if (isset($fieldList['deleted'])) {
                $condition['deleted'] = 0;
            }
            if (isset($fieldList['is_deleted'])) {
                $condition['is_deleted'] = 0;
            }

            if (is_numeric($status)) {
                $condition['status'] = $status;
            }

            if (is_numeric($enabled)) {
                $condition['enabled'] = $enabled;
            }

            if (!empty($searchTime) && is_array($this->params["time"]) && in_array($searchTime, ["create"])) {
                $startTime = strtotime($this->params["time"]["start"]);
                $endTime = strtotime($this->params["time"]["end"]);

                $condition[$searchTime . "time"] = Db::raw("between {$startTime} and {$endTime} ");
            }

            if (!empty($keyword) && !empty($kwFields)) {
                $kwFields = str_replace(",", "|", $kwFields);
                $condition[] = [$kwFields, 'like', "%" . trim($keyword) . "%"];
            }

            if (!empty($keyword) && !empty($field)) {
                $field = str_replace(",", "|", $field);
                $condition[] = [$field, 'like', "%" . trim($keyword) . "%"];
            }

            $field = "*";

            if ($export) {
                $list = Db::name($this->tableName)->field($field)->where($condition)->order($this->orderBy)->select()->toArray();
            } else {
                $list = Db::name($this->tableName)->field($field)->where($condition)->order($this->orderBy)->page($this->pIndex, $this->pSize)->select()->toArray();
            }

            if ($export) {
                // 导出支持简单导出列表功能，复杂导出可以自行实现 exportExcelData
                foreach ($list as &$item) {
                    if (array_key_exists('createtime', $item)) {
                        $item['createtime'] = date('Y-m-d H:i:s', $item['createtime']);
                    }
                    if (array_key_exists('create_time', $item)) {
                        $item['create_time'] = date('Y-m-d H:i:s', $item['create_time']);
                    }
                    if (array_key_exists('updatetime', $item)) {
                        $item['updatetime'] = date('Y-m-d H:i:s', $item['updatetime']);
                    }
                    if (array_key_exists('update_time', $item)) {
                        $item['update_time'] = date('Y-m-d H:i:s', $item['update_time']);
                    }
                    if (array_key_exists('finishtime', $item)) {
                        $item['finishtime'] = date('Y-m-d H:i:s', $item['finishtime']);
                    }
                    if (array_key_exists('finish_time', $item)) {
                        $item['finish_time'] = date('Y-m-d H:i:s', $item['finish_time']);
                    }
                    if (array_key_exists('canceltime', $item)) {
                        $item['canceltime'] = date('Y-m-d H:i:s', $item['canceltime']);
                    }
                    if (array_key_exists('cancel_time', $item)) {
                        $item['cancel_time'] = date('Y-m-d H:i:s', $item['cancel_time']);
                    }
                }
                unset($item);
                $this->exportExcelData($list, $exportColumns, $exportKeys, $exportTitle);
            }

            $total = Db::name($this->tableName)->where($condition)->count();
            $pager = pagination2($total, $this->pIndex, $this->pSize);

            $result = [
                'list'  => $list,
                'pager' => $pager,
                'total' => $total,

                'starttime' => $startTime,
                'endtime'   => $endTime,
            ];
        }

        return $this->template('list', $result);
    }

    // 导出列表
    public function exportExcelData($list, $column = null, $keys = null, $title = null, $last = null)
    {
        if (!empty($column) && !empty($keys)) {
            $title = ($title ?? "数据列表") . "_" . date('YmdHi');
            $column = explode(",", $column);
            $keys = explode(",", $keys);
            $last = explode(",", $last);

            $setWidth = [];
            for ($i = 0; $i < count($column); $i++) {
                $setWidth[$i] = 30;
            }

            $filename = $title;
            ExcelUtil::export($title, $column, $setWidth, $list, $keys, $last, $filename);
        }
    }

    public function edit()
    {
        return $this->post();
    }

    public function add()
    {
        return $this->post();
    }

    public function post()
    {
        $result = [];

        if (!empty($this->tableName)) {
            $id = intval($this->params["id"] ?? 0);

            if ($this->request->isPost()) {
                $fieldList = Db::name($this->tableName)->getFields();
                $updateData = [];
                foreach ($fieldList as $filed => $fieldItem) {
                    $updateData[$filed] = $this->params[$filed] ?? '';

                    switch ($fieldItem['type']) {
                        case 'text':
                            $updateData[$filed] = htmlspecialchars_decode($updateData[$filed]);
                            break;
                        case 'datetime':
                            $updateData[$filed] = strtotime($updateData[$filed]);
                            break;
                        case 'decimal':
                            $updateData[$filed] = floatval($updateData[$filed]);
                            break;
                        default:
                            $updateData[$filed] = trim($updateData[$filed]);
                    }

                    if (empty($updateData[$filed])) {
                        switch ($filed) {
                            case 'uniacid':
                                $updateData[$filed] = $this->uniacid;
                                break;
                            case 'create_time':
                            case 'createtime':
                                $updateData[$filed] = TIMESTAMP;
                                break;
                            case 'deleted':
                                $updateData[$filed] = 0;
                                break;
                        }
                    }
                }

                if (!empty($id)) {
                    Db::name($this->tableName)->where(['id' => $id])->update($updateData);
                } else {
                    $id = Db::name($this->tableName)->insertGetId($updateData);
                }
                if ($this->params['isModel']) {
                    $this->success();
                } else {
                    $this->success(["url" => url("", ['id' => $id, 'tab' => str_replace("#tab_", "", $this->params['tab'])])]);
                }
            }

            $field = "*";
            $condition = ['id' => $id];
            $item = Db::name($this->tableName)->field($field)->where($condition)->find();

            $result = [
                'item' => $item
            ];
        }

        return $this->template('post', $result);
    }

    public function change()
    {
        if (!empty($this->tableName)) {
            $id = intval($this->params["id"]);

            if (empty($id)) {
                $id = $this->params["ids"];
            }

            if (empty($id)) {
                $this->error("参数错误");
            }

            $type = trim($this->params["type"]);
            $value = trim($this->params["value"]);

            $items = Db::name($this->tableName)->where(['id' => $id])->select();
            foreach ($items as $item) {
                Db::name($this->tableName)->where("id", '=', $item['id'])->update([$type => $value]);
            }
        }

        $this->success();
    }

    public function delete()
    {
        if (!empty($this->tableName)) {
            $id = intval($this->params["id"]);

            if (empty($id)) {
                $id = $this->params["ids"];
            }

            if (empty($id)) {
                $this->error("参数错误");
            }

            $updateData = [];
            $fieldList = Db::name($this->tableName)->getFields();
            if (array_key_exists('deleted', $fieldList)) {
                $updateData['deleted'] = 1;
            }
            if (array_key_exists('delete_time', $fieldList)) {
                $updateData['delete_time'] = TIMESTAMP;
            }
            if (array_key_exists('is_deleted', $fieldList)) {
                $updateData['is_deleted'] = 1;
            }

            $items = Db::name($this->tableName)->where(['id' => $id])->select();
            foreach ($items as $item) {
                if (!empty($item['is_default'])) {
                    $this->error("默认项不能被删除");
                }
                Db::name($this->tableName)->where(['uniacid' => $this->uniacid, "id" => $item['id']])->update($updateData);
            }
        }
        $this->success(["url" => referer()]);
    }

    // 真实删除
    public function destroy()
    {
        if (!empty($this->tableName)) {
            $id = intval($this->params["id"]);

            if (empty($id)) {
                $id = $this->params["ids"];
            }

            if (empty($id)) {
                $this->error("参数错误");
            }

            $items = Db::name($this->tableName)->where(['uniacid' => $this->uniacid, 'id' => $id])->select();
            foreach ($items as $item) {
                if (!empty($item['is_default'])) {
                    $this->error("默认项不能被删除");
                }
                Db::name($this->tableName)->where(["id" => $item['id']])->delete();
            }
        }
        $this->success(["url" => referer()]);
    }

    // 还原数据
    public function restore()
    {
        if (!empty($this->tableName)) {
            $id = intval($this->params["id"]);

            if (empty($id)) {
                $id = $this->params["ids"];
            }

            if (empty($id)) {
                $this->error("参数错误");
            }

            $updateData = [];
            $fieldList = Db::name($this->tableName)->getFields();
            if (array_key_exists('deleted', $fieldList)) {
                $updateData['deleted'] = 0;
            }
            if (array_key_exists('is_deleted', $fieldList)) {
                $updateData['is_deleted'] = 0;
            }

            $items = Db::name($this->tableName)->where(['id' => $id])->select();
            foreach ($items as $item) {
                Db::name($this->tableName)->where(["id" => $item['id']])->update($updateData);
            }
        }
        $this->success(["url" => referer()]);
    }

    // 回收站
    public function recycle()
    {
        $result = [];

        if (!empty($this->tableName)) {
            $condition = [
                'uniacid' => $this->uniacid,
                'deleted' => 1,
            ];

            $field = "*";
            $order = "id desc";
            $list = Db::name($this->tableName)->field($field)->where($condition)->order($order)->page($this->pIndex, $this->pSize)->select()->toArray();
            $total = Db::name($this->tableName)->where($condition)->count();
            $pager = pagination2($total, $this->pIndex, $this->pSize);

            $result = [
                'list'  => $list,
                'pager' => $pager,
                'total' => $total,
            ];
        }

        return $this->template('recycle', $result);
    }

    // 访问入口
    public function cover()
    {
        $moduleName = realModuleName($this->module);
        $coverUrl = $this->siteRoot . "/{$moduleName}.html?i=" . $this->uniacid;
        return $this->template('cover', ['coverUrl' => $coverUrl]);
    }

    // 设置项目应用配置信息
    public function moduleSettings()
    {
        $moduleSettings = $this->settingsController->getModuleSettings(null, $this->module, $this->uniacid);
        if ($this->request->isPost()) {
            $settingsData = $this->params['data'] ?? [];

            if (!empty($settingsData['contact'])) {
                $settingsData['contact']['about'] = htmlspecialchars_decode($settingsData['contact']['about']);
            }
            if (!empty($settingsData['user'])) {
                $settingsData['user']['agreement'] = htmlspecialchars_decode($settingsData['user']['agreement']);
            }

            $settingsData = array_merge($moduleSettings, $settingsData);

            if (!empty($settingsData)) {
                $data['settings'] = serialize($settingsData);
                Db::name('sys_account_modules')->where(["uniacid" => $this->uniacid, 'module' => $this->module])->update($data);
                # 更新缓存
                $this->settingsController->reloadModuleSettings($this->module, $this->uniacid);
            }

            $moduleSettings = $settingsData;
        }
        return $moduleSettings;
    }

    // 当前项目应用配置信息
    public function module()
    {
        $moduleSettings = $this->moduleSettings();
        if ($this->request->isPost()) {
            $this->success(["url" => webUrl("sets/module", ['tab' => str_replace("#tab_", "", $this->params['tab'])])]);
        }

        $var = [
            'moduleSettings' => $moduleSettings
        ];
        return $this->template('module', $var);
    }
}

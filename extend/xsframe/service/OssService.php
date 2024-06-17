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

namespace xsframe\service;

use OSS\Core\OssException;
use OSS\OssClient;
use xsframe\base\BaseService;

/*
 * 阿里云OSS
 */

class OssService extends BaseService
{
    private $client, $bucket;

    public function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub

        if ($this->accountSetting && $this->accountSetting['remote'] && $this->accountSetting['remote']['type'] == 2) {
            $aliOss = $this->accountSetting['remote']['alioss'];

            $accessKeyId = $aliOss['key'] ?? '';
            $accessKeySecret = $aliOss['secret'] ?? '';
            $internal = $aliOss['internal'] ?? 0; // 是否开启内网上传 0否 1是

            try {
                list($bucket, $url) = explode('@@', $aliOss['bucket'] ?? '');
                $this->bucket = $bucket ?? '';

                $host = $internal ? '-internal.aliyuncs.com' : '.aliyuncs.com';
                $endpoint = 'http://' . $url . $host;

                $this->client = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
            } catch (OssException $e) {

            }
        }
    }

    // 获取alioss仓库
    private function attachmentAliossBuctkets($key, $secret)
    {
        $url = 'http://oss-cn-beijing.aliyuncs.com';

        $ossClient = null;
        try {
            $ossClient = new OssClient($key, $secret, $url);
        } catch (OssException $e) {
            show_json(-1, $e->getMessage());
        }

        $bucketListInfo = null;
        try {
            $bucketListInfo = $ossClient->listBuckets();
        } catch (OssException $e) {
            show_json(-1, $e->getMessage());
        }

        $bucketListInfo = $bucketListInfo->getBucketList();
        $bucketList = array();
        foreach ($bucketListInfo as &$bucket) {
            $bucketList[$bucket->getName()] = array('name' => $bucket->getName(), 'location' => $bucket->getLocation());
        }

        return $bucketList;
    }

    public function getVideoUrl($videoPath, $bucket = null, $timeout = 3600)
    {
        $bucket = $bucket ?? $this->bucket;
        // $result = $this->client->signUrl($bucket, $videoPath, $timeout, 'GET');
        return $this->client->signUrl($bucket, $videoPath, $timeout, 'GET', ['x-oss-process' => 'hls/sign']);
    }

    //上传字符串
    public function putObject($filename, $content, $path = '')
    {
        $object = $path . $filename;
        try {
            dump($this->client->putObject($this->bucket, $object, $content));
        } catch (OssException $e) {
            dump($e->getMessage());
        }
    }

    //上传文件
    public function uploadFile($filename, $path = '', $bucket = null): string
    {
        try {
            if (empty($bucket)) {
                $bucket = $this->bucket;
            }
            if (!empty($bucket)) {
                if (is_file($path)) {
                    $this->client->uploadFile($bucket, $filename, $path);
                    @unlink($path); // 上传成功后 删除本地文件
                }
            }

            return $filename;
        } catch (OssException $e) {
            return $e->getMessage();
        }
    }

    //删除文件
    public function deleteFile($filename, $bucket = null)
    {
        try {
            if (empty($bucket)) {
                $bucket = $this->bucket;
            }
            return $this->client->deleteObject($bucket, $filename);
        } catch (OssException $e) {
            return $e->getMessage();
        }
    }


    //列出对象
    public function listObj()
    {
        try {
            return $this->client->listObjects($this->bucket);
        } catch (OssException $e) {
            return $e->getMessage();
        }
    }

    //获取Cname
    public function getBucketCname()
    {
        try {
            dump($this->client->getBucketCname($this->bucket));
        } catch (OssException $e) {
            dump($e->getMessage());
        }
    }
}
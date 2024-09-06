<?php

namespace xsframe\chinaums\Service\Wechat;

use xsframe\chinaums\Service\Wechat\Base;

/**
 * 退款接口
 */
class Refund extends Base
{
    /**
     * @var string 接口地址
     */
    protected $api = '/netpay/refund';
    /**
     * @var array $body 请求参数
     */
    protected $body = [];
    /**
     * 必传的值
     * @var array
     */
    protected $require = ['requestTimestamp', 'merOrderId', 'mid', 'tid', 'refundAmount'];
}

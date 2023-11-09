<?php

namespace xsframe\service;

use xsframe\base\BaseService;
use xsframe\exception\ApiException;
use xsframe\util\PriceUtil;
use xsframe\util\RandomUtil;

class PayService extends BaseService
{
    private $wxPayService;
    private $aliPayService;

    /**
     * wap - 微信js sdk支付
     * @param $ordersn
     * @param $price
     * @param string $title
     * @param int $serviceType
     * @param string $openid
     * @return array
     * @throws ApiException
     */
    public function wxPay($ordersn, $price, $title = '', $serviceType = 0, $openid = '')
    {
        try {
            $body       = $title;
            $orderPrice = PriceUtil::yuan2fen($price); // 订单总金额，单位为：分
            $outTradeNo = $ordersn; // 订单号
            $attach     = $this->module . ":" . $this->uniacid . ":" . $serviceType;// 商品附加信息（订单支付成功回调原样返回数据）
            $tradeType  = 'JSAPI'; // 支付类型
            $goodsTag   = ""; // 商品优惠信息
            // $openid     = "oDQpnwZpjj2Ysu5YRH9ZsR5rniFs"; // 微信公号支付用到 鹿隐
            // $openid     = "oaafU6J1RNSW-wDUFSOWjndpWDms"; // 微信公号支付用到 星数
            $bundleName = "WEB";
            $timeExpire = date('YmdHis', time() + 600); // 订单过期时间 10分钟

            $paymentSet = $this->account['settings']['wxpay'];

            if (!$this->wxPayService instanceof wxPayService) {
                $notifyUrl          = $this->siteRoot . "/" . $this->module . "/wechat/notify";
                $this->wxPayService = new WxPayService($paymentSet['appid'], $paymentSet['mchid'], $paymentSet['apikey'], $notifyUrl);
            }

            if (empty($openid)) {
                throw new ApiException("微信授权失效，请刷新下页面再付费即可");
            }

            $unifiedReturn = $this->wxPayService->unifiedOrder($body, $orderPrice, $outTradeNo, $attach, $tradeType, $goodsTag, $openid, $bundleName, $timeExpire);

            $wOpt              = array();
            $string            = "";
            $wOpt['appId']     = $paymentSet['appid'];
            $wOpt['timeStamp'] = strval(time());
            $wOpt['nonceStr']  = RandomUtil::random(8);
            $wOpt['package']   = 'prepay_id=' . $unifiedReturn['prepay_id'];
            $wOpt['signType']  = 'MD5';
            ksort($wOpt, SORT_STRING);
            foreach ($wOpt as $key => $v) {
                $string .= "{$key}={$v}&";
            }
            $string          .= "key=" . $paymentSet['apikey'];
            $wOpt['paySign'] = strtoupper(md5($string));

            return $wOpt;
        } catch (ApiException $e) {
            throw new ApiException($e->getMessage());
        }
    }

    /**
     * wap - 微信native支付
     * @param $ordersn
     * @param $price
     * @param string $title
     * @param int $serviceType
     * @return string
     * @throws ApiException
     */
    public function wxNative($ordersn, $price, $serviceType = 0, $title = '')
    {
        try {
            $body       = $title;
            $totalFee   = PriceUtil::yuan2fen($price);
            $outTradeNo = $ordersn;
            $attach     = $this->module . ":" . $this->uniacid . ":" . $serviceType;
            $tradeType  = "NATIVE";
            $goodsTag   = "";

            if (!$this->wxPayService instanceof WxPayService) {
                $paymentSet = $this->account['settings']['wxpay'];
                $notifyUrl  = $this->siteRoot . "/" . $this->module . "/wechat/notify";

                $this->wxPayService = new WxPayService($paymentSet['appid'], $paymentSet['mchid'], $paymentSet['apikey'], $notifyUrl);
            }

            $unifiedReturn = $this->wxPayService->unifiedOrder($body, $totalFee, $outTradeNo, $attach, $tradeType, $goodsTag);

            return $unifiedReturn;
        } catch (ApiException $e) {
            throw new ApiException($e->getMessage());
        }
    }

    /**
     * web - 支付宝支付
     * @param $ordersn
     * @param $price
     * @param int $serviceType
     * @param string $title
     * @param null $returnUrl
     * @param bool $returnQrcode
     * @param int $qrcodeWidth
     * @return string
     * @throws ApiException
     */
    public function aliPagePay($ordersn, $price, $serviceType = 0, $title = '', $returnUrl = null, $returnQrcode = false, $qrcodeWidth = 300)
    {
        try {
            $params = [
                'out_trade_no' => $ordersn,
                'total_amount' => $price,
                'subject'      => $title,
                'body'         => $this->module . ":" . $this->uniacid . ":" . $serviceType,
                'product_code' => 'FAST_INSTANT_TRADE_PAY',
            ];

            if ($returnQrcode) {
                $params['qr_pay_mode']  = 4;
                $params['qrcode_width'] = $qrcodeWidth;
            }

            $params['return_url'] = $returnUrl;

            if (!$this->aliPayService instanceof AliPayService) {
                $paymentSet = $this->account['settings']['alipay'];
                $gatewayUrl = "https://openapi.alipay.com/gateway.do";
                $notifyUrl  = $this->siteRoot . "/" . $this->module . "/alipay/notify";

                $this->aliPayService = new AliPayService($gatewayUrl, $paymentSet['appid'], $paymentSet['encrypt_key'], $paymentSet['private_key'], $paymentSet['public_key'], $notifyUrl, $returnUrl);
            }

            return $this->aliPayService->pagePay($params);
        } catch (ApiException $e) {
            throw new ApiException($e->getMessage());
        } catch (\Exception $e) {
            throw new ApiException($e->getMessage());
        }
    }

    /**
     * 验证支付宝支付回调参数
     * @param $postData
     * @param $signType
     * @return bool
     */
    public function aliRsaCheck($postData, $signType)
    {
        if (!$this->aliPayService instanceof AliPayService) {
            $paymentSet = $this->account['settings']['alipay'];
            $gatewayUrl = "https://openapi.alipay.com/gateway.do";
            $notifyUrl  = $this->siteRoot . "/alipay/notify";

            $this->aliPayService = new AliPayService($gatewayUrl, $paymentSet['appid'], $paymentSet['encrypt_key'], $paymentSet['private_key'], $paymentSet['public_key'], $notifyUrl);
        }
        return $this->aliPayService->rsaCheck($postData, $signType);
    }
}